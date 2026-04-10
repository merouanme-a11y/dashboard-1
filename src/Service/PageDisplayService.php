<?php

namespace App\Service;

use App\Entity\PageIcon;
use App\Entity\PageTitle;
use App\Entity\ThemeSetting;
use App\Repository\PageIconRepository;
use App\Repository\PageRepository;
use App\Repository\PageTitleRepository;
use App\Repository\ThemeSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class PageDisplayService
{
    public const PAGE_ICON_LIBRARY_SETTING_KEY = 'page_icon_library';
    public const PAGE_ICONS_MODULE = 'page_title_icons';
    public const PAGE_TITLES_MODULE = 'page_titles';
    public const BOOTSTRAP_ICON_STYLESHEET = 'assets/bootstrap-icons/bootstrap-icons.css';
    private const EXCLUDED_ROUTE_NAMES = [
        'app_login',
        'app_logout',
        'app_forgot_password',
        'app_reset_password',
        'theme_template_stylesheet',
        'admin_modules_toggle',
        'admin_permissions',
        'admin_pages_builder',
        'app_livre_de_caisse_management_detail',
        'app_bi_connections',
        'app_bi_files',
        'app_bi_dataset',
        'app_bi_preferences',
        'app_bi_settings',
        'app_bi_upload_source',
    ];
    private const EXCLUDED_CONTROLLER_CLASSES = [
        'App\\Controller\\SecurityController',
        'App\\Controller\\PasswordResetController',
        'App\\Controller\\ThemeTemplateAssetController',
    ];

    private ?array $configurablePagesCache = null;
    private ?array $configurableControllerClassesCache = null;
    private ?array $configuredTitleOverridesCache = null;
    private ?array $configuredIconsCache = null;
    private ?string $activeIconLibraryCache = null;
    private array $iconLibraryStylesheetsCache = [];
    private ?array $allowedPageMapCache = null;
    private ?array $modulesByRouteCache = null;
    private ?array $availableIconLibrariesCache = null;
    private ?array $defaultPageTitleMapCache = null;
    private ?array $defaultPageEmojiIconMapCache = null;
    private ?array $iconFallbackMapsCache = null;

    public function __construct(
        private KernelInterface $kernel,
        private RouterInterface $router,
        private PageTitleRepository $pageTitleRepository,
        private PageIconRepository $pageIconRepository,
        private PageRepository $pageRepository,
        private ThemeSettingRepository $themeSettingRepository,
        private ModuleService $moduleService,
        private EntityManagerInterface $em,
        private CacheInterface $cache,
    ) {}

    public function getConfigurablePages(): array
    {
        if (is_array($this->configurablePagesCache)) {
            return $this->configurablePagesCache;
        }

        $cacheKey = 'page_display_configurable_pages_' . $this->buildConfigurablePagesFingerprint();

        return $this->configurablePagesCache = $this->cache->get($cacheKey, function (ItemInterface $item): array {
            $item->expiresAfter(86400);

            $pages = [];
            $controllerClasses = $this->discoverConfigurableControllerClasses();

            foreach ($this->router->getRouteCollection()->all() as $routeName => $route) {
                $controllerMetadata = $this->resolveControllerMetadataForRoute($route, $controllerClasses);
                if ($controllerMetadata === null || !$this->isConfigurableRoute($routeName, $route, $controllerMetadata)) {
                    continue;
                }

                $routePath = (string) $route->getPath();
                $defaultTitle = $this->getDefaultPageTitle($routeName, $routePath);
                $folder = $this->getRouteFolder($controllerMetadata['class']);

                $pages[] = [
                    'page_path' => $routeName,
                    'route_path' => $routePath,
                    'page_name' => $defaultTitle,
                    'default_title' => $defaultTitle,
                    'group' => $this->getRouteGroup($routeName, $routePath, $folder),
                    'folder' => $folder,
                    'controller_class' => $controllerMetadata['class'],
                    'controller_action' => $controllerMetadata['method'],
                    'controller_file' => $controllerMetadata['file'],
                ];
            }

            $pages = array_merge($pages, $this->getDynamicPageDescriptors());

            usort($pages, static function (array $left, array $right): int {
                $groupCompare = strcmp((string) ($left['group'] ?? ''), (string) ($right['group'] ?? ''));
                if ($groupCompare !== 0) {
                    return $groupCompare;
                }

                $titleCompare = strcmp((string) ($left['page_name'] ?? ''), (string) ($right['page_name'] ?? ''));
                if ($titleCompare !== 0) {
                    return $titleCompare;
                }

                return strcmp((string) ($left['page_path'] ?? ''), (string) ($right['page_path'] ?? ''));
            });

            return $pages;
        });
    }

    public function invalidateConfigurablePagesCache(): void
    {
        $this->configurablePagesCache = null;
        $this->allowedPageMapCache = null;
        $this->cache->delete('page_display_configurable_pages_v1');
        $this->cache->delete('page_display_configurable_pages_v2');
        $this->cache->delete('page_display_configurable_pages_' . $this->buildConfigurablePagesFingerprint());
    }

    public function getConfiguredTitleOverrides(): array
    {
        if (is_array($this->configuredTitleOverridesCache)) {
            return $this->configuredTitleOverridesCache;
        }

        try {
            return $this->configuredTitleOverridesCache = $this->cache->get('page_display_title_overrides_v1', function (ItemInterface $item): array {
                $item->expiresAfter(3600);

                $titles = [];

                foreach ($this->pageTitleRepository->findAllRows() as $pageTitle) {
                    $pagePath = trim((string) ($pageTitle['pagePath'] ?? ''));
                    $displayName = $this->normalizeTitle((string) ($pageTitle['displayName'] ?? ''));
                    if ($pagePath === '' || $displayName === '') {
                        continue;
                    }

                    $titles[$pagePath] = $displayName;
                }

                return $titles;
            });
        } catch (\Throwable) {
            return $this->configuredTitleOverridesCache = [];
        }
    }

    public function saveTitleOverrides(array $titles): void
    {
        $allowedPages = $this->getAllowedPageMap();
        $existingEntries = $this->pageTitleRepository->findAllIndexedByPagePath();

        foreach ($titles as $pagePath => $title) {
            $pagePath = trim((string) $pagePath);
            if ($pagePath === '' || !isset($allowedPages[$pagePath])) {
                continue;
            }

            $normalizedTitle = $this->normalizeTitle((string) $title);
            $existingEntry = $existingEntries[$pagePath] ?? null;
            $defaultTitle = $this->getDefaultPageTitle($pagePath);

            if ($normalizedTitle === '' || $normalizedTitle === $defaultTitle) {
                if ($existingEntry instanceof PageTitle) {
                    $this->em->remove($existingEntry);
                }
                continue;
            }

            if (!$existingEntry instanceof PageTitle) {
                $existingEntry = new PageTitle();
                $existingEntry->setPagePath($pagePath);
                $this->em->persist($existingEntry);
            }

            $existingEntry->setDisplayName($normalizedTitle);
        }

        $this->em->flush();
        $this->configuredTitleOverridesCache = null;
        $this->cache->delete('page_display_title_overrides_v1');
    }

    public function getConfiguredIcons(): array
    {
        if (is_array($this->configuredIconsCache)) {
            return $this->configuredIconsCache;
        }

        try {
            return $this->configuredIconsCache = $this->cache->get('page_display_icons_v1', function (ItemInterface $item): array {
                $item->expiresAfter(3600);

                $icons = [];

                foreach ($this->pageIconRepository->findAllRows() as $pageIcon) {
                    $pagePath = trim((string) ($pageIcon['pagePath'] ?? ''));
                    $iconValue = trim((string) ($pageIcon['icon'] ?? ''));
                    if ($pagePath === '' || $iconValue === '') {
                        continue;
                    }

                    $icons[$pagePath] = [
                        'value' => $iconValue,
                        'color' => $this->sanitizeIconColor($pageIcon['color'] ?? ''),
                        'library' => $this->normalizeIconLibrary((string) ($pageIcon['iconLibrary'] ?? '')),
                    ];
                }

                return $icons;
            });
        } catch (\Throwable) {
            return $this->configuredIconsCache = [];
        }
    }

    public function saveIconConfiguration(string $library, array $icons): void
    {
        $library = $this->normalizeIconLibrary($library);
        $allowedPages = $this->getAllowedPageMap();
        $allowedValues = $this->getAllowedIconValuesForLibrary($library);
        $existingEntries = $this->pageIconRepository->findAllIndexedByPagePath();

        foreach ($allowedPages as $pagePath => $_) {
            $submittedConfig = $icons[$pagePath] ?? null;
            $submittedValue = is_array($submittedConfig)
                ? trim((string) ($submittedConfig['value'] ?? ''))
                : trim((string) $submittedConfig);
            $submittedColor = is_array($submittedConfig)
                ? $this->sanitizeIconColor($submittedConfig['color'] ?? '')
                : '';

            $existingEntry = $existingEntries[$pagePath] ?? null;
            $defaultValue = $this->getDefaultPageIconValue($pagePath, $library);

            if ($submittedValue === '' || !isset($allowedValues[$submittedValue])) {
                if ($existingEntry instanceof PageIcon) {
                    $this->em->remove($existingEntry);
                }
                continue;
            }

            if ($submittedValue === $defaultValue && $submittedColor === '') {
                if ($existingEntry instanceof PageIcon) {
                    $this->em->remove($existingEntry);
                }
                continue;
            }

            if (!$existingEntry instanceof PageIcon) {
                $existingEntry = new PageIcon();
                $existingEntry->setPagePath($pagePath);
                $this->em->persist($existingEntry);
            }

            $existingEntry->setIcon($submittedValue);
            $existingEntry->setIconLibrary($library);
            $existingEntry->setColor($submittedColor !== '' ? $submittedColor : null);
        }

        $this->saveSetting(self::PAGE_ICON_LIBRARY_SETTING_KEY, $library);
        $this->em->flush();
        $this->configuredIconsCache = null;
        $this->activeIconLibraryCache = null;
        $this->iconLibraryStylesheetsCache = [];
        $this->cache->delete('page_display_icons_v1');
        $this->cache->delete('page_display_active_icon_library_v1');
    }

    public function getActiveIconLibrary(): string
    {
        if (is_string($this->activeIconLibraryCache) && $this->activeIconLibraryCache !== '') {
            return $this->activeIconLibraryCache;
        }

        try {
            return $this->activeIconLibraryCache = $this->cache->get('page_display_active_icon_library_v1', function (ItemInterface $item): string {
                $item->expiresAfter(3600);

                $setting = $this->themeSettingRepository->findByKey(self::PAGE_ICON_LIBRARY_SETTING_KEY);
                $settingValue = $this->normalizeIconLibrary((string) ($setting?->getSettingValue() ?? ''));

                if (isset($this->getAvailableIconLibraries()[$settingValue])) {
                    return $settingValue;
                }

                foreach ($this->getConfiguredIcons() as $pageIcon) {
                    $library = $this->normalizeIconLibrary((string) ($pageIcon['library'] ?? ''));
                    if (isset($this->getAvailableIconLibraries()[$library])) {
                        return $library;
                    }
                }

                return 'bootstrap';
            });
        } catch (\Throwable) {
            return $this->activeIconLibraryCache = 'bootstrap';
        }
    }

    public function getIconLibraryStylesheets(bool $includeAll = false): array
    {
        $cacheKey = $includeAll ? 'all' : 'active';
        if (isset($this->iconLibraryStylesheetsCache[$cacheKey])) {
            return $this->iconLibraryStylesheetsCache[$cacheKey];
        }

        $libraries = $this->getAvailableIconLibraries();
        $stylesheets = [];
        $keys = [];

        if ($includeAll) {
            $keys = array_keys($libraries);
        } else {
            $keys[] = $this->getActiveIconLibrary();
            foreach ($this->getConfiguredIcons() as $iconConfig) {
                $keys[] = $this->normalizeIconLibrary((string) ($iconConfig['library'] ?? ''));
            }
        }

        foreach (array_unique($keys) as $key) {
            foreach ((array) ($libraries[$key]['stylesheets'] ?? []) as $stylesheet) {
                $stylesheet = trim((string) $stylesheet);
                if ($stylesheet !== '' && !in_array($stylesheet, $stylesheets, true)) {
                    $stylesheets[] = $stylesheet;
                }
            }
        }

        return $this->iconLibraryStylesheetsCache[$cacheKey] = $stylesheets;
    }

    public function resolveNavigationLabel(string $pagePath = '', string $fallbackTitle = ''): string
    {
        $pagePath = trim($pagePath);
        $normalizedFallback = $this->normalizeTitle($fallbackTitle);

        if ($this->isPageTitlesFeatureEnabled()) {
            $override = trim((string) ($this->getConfiguredTitleOverrides()[$pagePath] ?? ''));
            if ($override !== '') {
                return $override;
            }
        }

        if ($normalizedFallback !== '') {
            return $normalizedFallback;
        }

        return $this->getDefaultPageTitle($pagePath);
    }

    public function resolveRuntimeTitle(string $pagePath = '', string $fallbackTitle = ''): string
    {
        return $this->resolveNavigationLabel($pagePath, $fallbackTitle);
    }

    public function renderNavigationIcon(string $pagePath = '', string $fallbackIcon = '', string $fallbackLibrary = 'emoji', bool $importantColor = false): string
    {
        $resolvedIcon = $this->resolveConfiguredPageIcon($pagePath, $fallbackIcon, $fallbackLibrary);
        $iconValue = trim((string) ($resolvedIcon['value'] ?? ''));
        if ($iconValue === '') {
            return '';
        }

        $library = $this->normalizeIconLibrary((string) ($resolvedIcon['library'] ?? ''));
        $iconColor = $this->sanitizeIconColor($resolvedIcon['color'] ?? '');
        $style = $iconColor !== ''
            ? ' style="color: ' . htmlspecialchars($iconColor, ENT_QUOTES, 'UTF-8') . ($importantColor ? ' !important' : '') . ';"'
            : '';

        return '<span class="configured-page-icon configured-page-icon-' . htmlspecialchars($library, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"' . $style . '>' . $this->renderIconInnerHtml($library, $iconValue) . '</span>';
    }

    public function getDefaultIconValue(string $pagePath, string $library = 'emoji', string $fallbackIcon = '', string $fallbackLibrary = 'emoji'): string
    {
        return $this->getDefaultPageIconValue($pagePath, $library, $fallbackIcon, $fallbackLibrary);
    }

    public function getAvailableIconLibraries(): array
    {
        if (is_array($this->availableIconLibrariesCache)) {
            return $this->availableIconLibrariesCache;
        }

        return $this->availableIconLibrariesCache = [
            'emoji' => [
                'key' => 'emoji',
                'label' => 'Emojis',
                'render_mode' => 'emoji',
                'stylesheets' => [],
                'options' => [
                    ['value' => '📄', 'label' => 'Document'],
                    ['value' => '🎫', 'label' => 'Ticket'],
                    ['value' => '📋', 'label' => 'Liste'],
                    ['value' => '📊', 'label' => 'Statistiques'],
                    ['value' => '👤', 'label' => 'Profil'],
                    ['value' => '⚙️', 'label' => 'Parametrage'],
                    ['value' => '👥', 'label' => 'Utilisateurs'],
                    ['value' => '🧩', 'label' => 'Interface'],
                    ['value' => '📇', 'label' => 'Annuaire'],
                    ['value' => '🛠️', 'label' => 'Administration'],
                    ['value' => '🗄️', 'label' => 'Sauvegarde'],
                    ['value' => '📝', 'label' => 'Edition'],
                    ['value' => '🎨', 'label' => 'Theme'],
                    ['value' => '🖼️', 'label' => 'Icones'],
                    ['value' => '🗂️', 'label' => 'Menu'],
                    ['value' => '🧪', 'label' => 'Test'],
                    ['value' => '⚠️', 'label' => 'Alerte'],
                    ['value' => '🏠', 'label' => 'Accueil'],
                    ['value' => '🔐', 'label' => 'Securite'],
                ],
            ],
            'bootstrap' => [
                'key' => 'bootstrap',
                'label' => 'Bootstrap Icons',
                'render_mode' => 'class',
                'stylesheets' => [self::BOOTSTRAP_ICON_STYLESHEET],
                'options' => [
                    ['value' => 'bi-file-earmark-text', 'label' => 'Document'],
                    ['value' => 'bi-ticket-perforated', 'label' => 'Ticket'],
                    ['value' => 'bi-card-list', 'label' => 'Liste'],
                    ['value' => 'bi-bar-chart', 'label' => 'Statistiques'],
                    ['value' => 'bi-person', 'label' => 'Profil'],
                    ['value' => 'bi-gear', 'label' => 'Parametrage'],
                    ['value' => 'bi-people', 'label' => 'Utilisateurs'],
                    ['value' => 'bi-puzzle', 'label' => 'Interface'],
                    ['value' => 'bi-person-badge', 'label' => 'Annuaire'],
                    ['value' => 'bi-tools', 'label' => 'Administration'],
                    ['value' => 'bi-archive', 'label' => 'Sauvegarde'],
                    ['value' => 'bi-pencil-square', 'label' => 'Edition'],
                    ['value' => 'bi-palette', 'label' => 'Theme'],
                    ['value' => 'bi-images', 'label' => 'Icones'],
                    ['value' => 'bi-folder', 'label' => 'Menu'],
                    ['value' => 'bi-beaker', 'label' => 'Test'],
                    ['value' => 'bi-exclamation-triangle', 'label' => 'Alerte'],
                    ['value' => 'bi-house', 'label' => 'Accueil'],
                    ['value' => 'bi-shield-lock', 'label' => 'Securite'],
                    ['value' => 'bi-speedometer2', 'label' => 'Dashboard'],
                    ['value' => 'bi-grid-1x2', 'label' => 'Grille'],
                    ['value' => 'bi-window-stack', 'label' => 'Pages'],
                    ['value' => 'bi-search', 'label' => 'Recherche'],
                    ['value' => 'bi-funnel', 'label' => 'Filtre'],
                    ['value' => 'bi-calendar-event', 'label' => 'Calendrier'],
                    ['value' => 'bi-clock-history', 'label' => 'Historique'],
                    ['value' => 'bi-bell', 'label' => 'Notification'],
                    ['value' => 'bi-chat-dots', 'label' => 'Commentaire'],
                    ['value' => 'bi-envelope', 'label' => 'Message'],
                    ['value' => 'bi-upload', 'label' => 'Import'],
                    ['value' => 'bi-download', 'label' => 'Export'],
                    ['value' => 'bi-save', 'label' => 'Sauvegarde action'],
                    ['value' => 'bi-printer', 'label' => 'Impression'],
                    ['value' => 'bi-link-45deg', 'label' => 'Lien'],
                    ['value' => 'bi-globe2', 'label' => 'Web'],
                    ['value' => 'bi-shield-check', 'label' => 'Securite validee'],
                    ['value' => 'bi-lock', 'label' => 'Verrou'],
                    ['value' => 'bi-person-plus', 'label' => 'Ajout utilisateur'],
                    ['value' => 'bi-diagram-3', 'label' => 'Organisation'],
                    ['value' => 'bi-table', 'label' => 'Tableau'],
                    ['value' => 'bi-clipboard-data', 'label' => 'Rapport'],
                    ['value' => 'bi-graph-up-arrow', 'label' => 'Performance'],
                    ['value' => 'bi-database', 'label' => 'Base de donnees'],
                    ['value' => 'bi-phone', 'label' => 'Telephone'],
                    ['value' => 'bi-geo-alt', 'label' => 'Localisation'],
                    ['value' => 'bi-flag', 'label' => 'Statut'],
                    ['value' => 'bi-check2-circle', 'label' => 'Validation'],
                    ['value' => 'bi-question-circle', 'label' => 'Aide'],
                ],
            ],
            'fontawesome' => [
                'key' => 'fontawesome',
                'label' => 'Font Awesome Free',
                'render_mode' => 'class',
                'stylesheets' => ['https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css'],
                'options' => [
                    ['value' => 'fa-solid fa-file-lines', 'label' => 'Document'],
                    ['value' => 'fa-solid fa-ticket', 'label' => 'Ticket'],
                    ['value' => 'fa-solid fa-table-list', 'label' => 'Liste'],
                    ['value' => 'fa-solid fa-chart-column', 'label' => 'Statistiques'],
                    ['value' => 'fa-solid fa-user', 'label' => 'Profil'],
                    ['value' => 'fa-solid fa-gear', 'label' => 'Parametrage'],
                    ['value' => 'fa-solid fa-users', 'label' => 'Utilisateurs'],
                    ['value' => 'fa-solid fa-puzzle-piece', 'label' => 'Interface'],
                    ['value' => 'fa-solid fa-address-card', 'label' => 'Annuaire'],
                    ['value' => 'fa-solid fa-screwdriver-wrench', 'label' => 'Administration'],
                    ['value' => 'fa-solid fa-box-archive', 'label' => 'Sauvegarde'],
                    ['value' => 'fa-solid fa-pen-to-square', 'label' => 'Edition'],
                    ['value' => 'fa-solid fa-palette', 'label' => 'Theme'],
                    ['value' => 'fa-regular fa-images', 'label' => 'Icones'],
                    ['value' => 'fa-solid fa-folder-open', 'label' => 'Menu'],
                    ['value' => 'fa-solid fa-flask', 'label' => 'Test'],
                    ['value' => 'fa-solid fa-triangle-exclamation', 'label' => 'Alerte'],
                    ['value' => 'fa-solid fa-house', 'label' => 'Accueil'],
                    ['value' => 'fa-solid fa-shield-halved', 'label' => 'Securite'],
                ],
            ],
            'remixicon' => [
                'key' => 'remixicon',
                'label' => 'Remix Icon',
                'render_mode' => 'class',
                'stylesheets' => ['https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css'],
                'options' => [
                    ['value' => 'ri-file-text-line', 'label' => 'Document'],
                    ['value' => 'ri-ticket-2-line', 'label' => 'Ticket'],
                    ['value' => 'ri-file-list-3-line', 'label' => 'Liste'],
                    ['value' => 'ri-bar-chart-box-line', 'label' => 'Statistiques'],
                    ['value' => 'ri-user-line', 'label' => 'Profil'],
                    ['value' => 'ri-settings-3-line', 'label' => 'Parametrage'],
                    ['value' => 'ri-team-line', 'label' => 'Utilisateurs'],
                    ['value' => 'ri-puzzle-line', 'label' => 'Interface'],
                    ['value' => 'ri-contacts-book-line', 'label' => 'Annuaire'],
                    ['value' => 'ri-tools-line', 'label' => 'Administration'],
                    ['value' => 'ri-archive-line', 'label' => 'Sauvegarde'],
                    ['value' => 'ri-edit-line', 'label' => 'Edition'],
                    ['value' => 'ri-palette-line', 'label' => 'Theme'],
                    ['value' => 'ri-image-line', 'label' => 'Icones'],
                    ['value' => 'ri-folders-line', 'label' => 'Menu'],
                    ['value' => 'ri-flask-line', 'label' => 'Test'],
                    ['value' => 'ri-alert-line', 'label' => 'Alerte'],
                    ['value' => 'ri-home-line', 'label' => 'Accueil'],
                    ['value' => 'ri-shield-keyhole-line', 'label' => 'Securite'],
                ],
            ],
            'boxicons' => [
                'key' => 'boxicons',
                'label' => 'Boxicons',
                'render_mode' => 'class',
                'stylesheets' => ['https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css'],
                'options' => [
                    ['value' => 'bx bx-file', 'label' => 'Document'],
                    ['value' => 'bx bx-purchase-tag-alt', 'label' => 'Ticket'],
                    ['value' => 'bx bx-list-ul', 'label' => 'Liste'],
                    ['value' => 'bx bx-bar-chart-alt-2', 'label' => 'Statistiques'],
                    ['value' => 'bx bx-user', 'label' => 'Profil'],
                    ['value' => 'bx bx-cog', 'label' => 'Parametrage'],
                    ['value' => 'bx bx-group', 'label' => 'Utilisateurs'],
                    ['value' => 'bx bx-extension', 'label' => 'Interface'],
                    ['value' => 'bx bx-id-card', 'label' => 'Annuaire'],
                    ['value' => 'bx bx-wrench', 'label' => 'Administration'],
                    ['value' => 'bx bx-archive', 'label' => 'Sauvegarde'],
                    ['value' => 'bx bx-edit-alt', 'label' => 'Edition'],
                    ['value' => 'bx bx-palette', 'label' => 'Theme'],
                    ['value' => 'bx bx-images', 'label' => 'Icones'],
                    ['value' => 'bx bx-folder', 'label' => 'Menu'],
                    ['value' => 'bx bx-test-tube', 'label' => 'Test'],
                    ['value' => 'bx bx-error', 'label' => 'Alerte'],
                    ['value' => 'bx bx-home', 'label' => 'Accueil'],
                    ['value' => 'bx bx-shield-quarter', 'label' => 'Securite'],
                ],
            ],
            'tabler' => [
                'key' => 'tabler',
                'label' => 'Tabler Icons',
                'render_mode' => 'class',
                'stylesheets' => ['https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.34.1/dist/tabler-icons.min.css'],
                'options' => [
                    ['value' => 'ti ti-file-text', 'label' => 'Document'],
                    ['value' => 'ti ti-ticket', 'label' => 'Ticket'],
                    ['value' => 'ti ti-list-details', 'label' => 'Liste'],
                    ['value' => 'ti ti-chart-bar', 'label' => 'Statistiques'],
                    ['value' => 'ti ti-user', 'label' => 'Profil'],
                    ['value' => 'ti ti-settings', 'label' => 'Parametrage'],
                    ['value' => 'ti ti-users', 'label' => 'Utilisateurs'],
                    ['value' => 'ti ti-puzzle', 'label' => 'Interface'],
                    ['value' => 'ti ti-address-book', 'label' => 'Annuaire'],
                    ['value' => 'ti ti-tool', 'label' => 'Administration'],
                    ['value' => 'ti ti-archive', 'label' => 'Sauvegarde'],
                    ['value' => 'ti ti-edit', 'label' => 'Edition'],
                    ['value' => 'ti ti-palette', 'label' => 'Theme'],
                    ['value' => 'ti ti-photo', 'label' => 'Icones'],
                    ['value' => 'ti ti-folder', 'label' => 'Menu'],
                    ['value' => 'ti ti-flask', 'label' => 'Test'],
                    ['value' => 'ti ti-alert-triangle', 'label' => 'Alerte'],
                    ['value' => 'ti ti-home', 'label' => 'Accueil'],
                    ['value' => 'ti ti-shield-lock', 'label' => 'Securite'],
                ],
            ],
            'material' => [
                'key' => 'material',
                'label' => 'Material Symbols',
                'render_mode' => 'ligature',
                'stylesheets' => ['https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0'],
                'options' => [
                    ['value' => 'description', 'label' => 'Document'],
                    ['value' => 'confirmation_number', 'label' => 'Ticket'],
                    ['value' => 'list_alt', 'label' => 'Liste'],
                    ['value' => 'bar_chart', 'label' => 'Statistiques'],
                    ['value' => 'person', 'label' => 'Profil'],
                    ['value' => 'settings', 'label' => 'Parametrage'],
                    ['value' => 'groups', 'label' => 'Utilisateurs'],
                    ['value' => 'extension', 'label' => 'Interface'],
                    ['value' => 'contacts', 'label' => 'Annuaire'],
                    ['value' => 'construction', 'label' => 'Administration'],
                    ['value' => 'inventory_2', 'label' => 'Sauvegarde'],
                    ['value' => 'edit_square', 'label' => 'Edition'],
                    ['value' => 'palette', 'label' => 'Theme'],
                    ['value' => 'imagesmode', 'label' => 'Icones'],
                    ['value' => 'folder', 'label' => 'Menu'],
                    ['value' => 'science', 'label' => 'Test'],
                    ['value' => 'warning', 'label' => 'Alerte'],
                    ['value' => 'home', 'label' => 'Accueil'],
                    ['value' => 'shield_lock', 'label' => 'Securite'],
                ],
            ],
        ];
    }

    public function isPageIconsFeatureEnabled(): bool
    {
        return $this->moduleService->isActive(self::PAGE_ICONS_MODULE);
    }

    public function isPageTitlesFeatureEnabled(): bool
    {
        return $this->moduleService->isActive(self::PAGE_TITLES_MODULE);
    }

    private function resolveConfiguredPageIcon(string $pagePath, string $fallbackIcon, string $fallbackLibrary): array
    {
        $fallbackLibrary = $this->normalizeIconLibrary($fallbackLibrary);
        if (!$this->isPageIconsFeatureEnabled()) {
            return [
                'library' => $fallbackLibrary,
                'value' => trim($fallbackIcon),
                'color' => '',
            ];
        }

        $icons = $this->getConfiguredIcons();
        if (isset($icons[$pagePath]['value']) && trim((string) $icons[$pagePath]['value']) !== '') {
            return [
                'library' => $this->normalizeIconLibrary((string) ($icons[$pagePath]['library'] ?? $this->getActiveIconLibrary())),
                'value' => trim((string) $icons[$pagePath]['value']),
                'color' => $this->sanitizeIconColor((string) ($icons[$pagePath]['color'] ?? '')),
            ];
        }

        return [
            'library' => $this->getActiveIconLibrary(),
            'value' => $this->getDefaultPageIconValue($pagePath, $this->getActiveIconLibrary(), $fallbackIcon, $fallbackLibrary),
            'color' => '',
        ];
    }

    private function getAllowedPageMap(): array
    {
        if (is_array($this->allowedPageMapCache)) {
            return $this->allowedPageMapCache;
        }

        $pages = [];
        foreach ($this->getConfigurablePages() as $page) {
            $pagePath = trim((string) ($page['page_path'] ?? ''));
            if ($pagePath !== '') {
                $pages[$pagePath] = true;
            }
        }

        return $this->allowedPageMapCache = $pages;
    }

    private function getDynamicPageDescriptors(): array
    {
        if (!$this->moduleService->isActive(DynamicPageService::PAGES_MODULE)) {
            return [];
        }

        $descriptors = [];
        $controllerFile = $this->kernel->getProjectDir() . '/src/Controller/DynamicPageController.php';

        foreach ($this->pageRepository->findAllActiveSorted() as $page) {
            $pageId = (int) ($page->getId() ?? 0);
            $slug = trim((string) $page->getSlug());
            if ($pageId <= 0 || $slug === '') {
                continue;
            }

            $pagePath = DynamicPageService::buildManagedPagePathFromId($pageId);
            $title = trim((string) $page->getTitle());
            $routePath = $this->router->generate(DynamicPageService::PUBLIC_ROUTE, ['slug' => $slug]);

            $descriptors[] = [
                'page_path' => $pagePath,
                'route_name' => DynamicPageService::PUBLIC_ROUTE,
                'route_parameters' => ['slug' => $slug],
                'route_path' => $routePath,
                'page_name' => $title !== '' ? $title : $this->getDefaultPageTitle($pagePath, $routePath),
                'default_title' => $title !== '' ? $title : $this->getDefaultPageTitle($pagePath, $routePath),
                'group' => 'Pages',
                'folder' => 'pages',
                'controller_class' => 'App\\Controller\\DynamicPageController',
                'controller_action' => 'show',
                'controller_file' => $controllerFile,
            ];
        }

        return $descriptors;
    }

    private function getAllowedIconValuesForLibrary(string $library): array
    {
        $values = [];

        foreach ((array) ($this->getAvailableIconLibraries()[$library]['options'] ?? []) as $option) {
            $value = trim((string) ($option['value'] ?? ''));
            if ($value !== '') {
                $values[$value] = true;
            }
        }

        return $values;
    }

    private function saveSetting(string $settingKey, string $settingValue): void
    {
        $setting = $this->themeSettingRepository->findByKey($settingKey);
        if (!$setting instanceof ThemeSetting) {
            $setting = new ThemeSetting();
            $setting->setSettingKey($settingKey);
            $this->em->persist($setting);
        }

        $setting->setSettingValue($settingValue);
    }

    private function isConfigurableRoute(string $routeName, Route $route, array $controllerMetadata): bool
    {
        if ($routeName === '' || in_array($routeName, self::EXCLUDED_ROUTE_NAMES, true)) {
            return false;
        }

        if (!$this->isConfigurableControllerAction($controllerMetadata['class'], $controllerMetadata['method'])) {
            return false;
        }

        if ((bool) ($route->getDefault('routeCreatedByEasyAdmin') ?? false)) {
            return false;
        }

        if (str_contains((string) $route->getPath(), '{')) {
            return false;
        }

        $methods = $route->getMethods();

        return $methods === [] || in_array('GET', $methods, true);
    }

    private function getRouteGroup(string $routeName, string $routePath, string $folder = 'pages'): string
    {
        if ($folder === 'admin') {
            return 'Administration';
        }

        if ($routePath === '/') {
            return 'Racine';
        }

        return 'Pages';
    }

    private function getDefaultPageTitle(string $pagePath, string $routePath = ''): string
    {
        $pagePath = trim($pagePath);
        if ($pagePath === '') {
            return 'Page';
        }

        $defaultTitles = $this->getDefaultPageTitleMap();
        if (isset($defaultTitles[$pagePath])) {
            return $defaultTitles[$pagePath];
        }

        $module = $this->getModulesByRoute()[$pagePath] ?? null;
        if ($module !== null) {
            return $this->normalizeTitle((string) $module->getLabel());
        }

        $seed = $routePath !== '' && $routePath !== '/' ? trim($routePath, '/') : preg_replace('/^(app_|admin_)/', '', $pagePath);
        $seed = str_replace(['-', '_', '/'], ' ', (string) $seed);
        $seed = preg_replace('/(?<=\\p{Ll})(\\p{Lu})/u', ' $1', (string) $seed);
        $seed = preg_replace('/\\s+/', ' ', (string) $seed);
        $seed = trim((string) $seed);

        return $seed !== '' ? ucfirst($seed) : 'Page';
    }

    private function normalizeTitle(string $title): string
    {
        $title = trim(strip_tags($title));
        $title = preg_replace('/\s*[-|]\s*Dashboard ADEP$/i', '', (string) $title);
        $title = preg_replace('/\\s+/', ' ', (string) $title);

        return trim((string) $title);
    }

    private function normalizeIconLibrary(string $library): string
    {
        $library = trim($library);

        return isset($this->getAvailableIconLibraries()[$library]) ? $library : 'bootstrap';
    }

    private function sanitizeIconColor(mixed $value): string
    {
        $color = strtoupper(trim((string) $value));

        return preg_match('/^#[0-9A-F]{6}$/', $color) ? $color : '';
    }

    private function getDefaultPageIconValue(string $pagePath, string $library = 'emoji', string $fallbackIcon = '', string $fallbackLibrary = 'emoji'): string
    {
        $emojiDefaults = $this->getDefaultPageEmojiIconMap();
        $semanticIcon = $emojiDefaults[$pagePath] ?? ($fallbackLibrary === 'emoji' ? trim($fallbackIcon) : '');

        if ($library === 'emoji') {
            return $semanticIcon !== '' ? $semanticIcon : ($fallbackLibrary === 'emoji' ? trim($fallbackIcon) : '📄');
        }

        $fallbackMaps = $this->getIconFallbackMaps();
        if ($semanticIcon !== '' && isset($fallbackMaps[$library][$semanticIcon])) {
            return $fallbackMaps[$library][$semanticIcon];
        }

        if ($fallbackLibrary === $library && trim($fallbackIcon) !== '') {
            return trim($fallbackIcon);
        }

        return isset($fallbackMaps[$library]) ? (string) reset($fallbackMaps[$library]) : '';
    }

    private function getDefaultPageTitleMap(): array
    {
        if (is_array($this->defaultPageTitleMapCache)) {
            return $this->defaultPageTitleMapCache;
        }

        return $this->defaultPageTitleMapCache = [
            'admin' => 'Interface d administration',
            'app_dashboard' => 'Accueil',
            'app_profile' => 'Mon profil',
            'app_profile_create' => 'Creer un utilisateur',
            'app_annuaire' => 'Annuaire des contacts',
            'app_stats' => 'Statistiques',
            'app_bi' => 'Business Intelligence',
            'app_gantt_projects' => 'Planning projets',
            'app_livre_de_caisse' => 'Livre de caisse',
            'app_livre_de_caisse_listing' => 'Listing livre de caisse agence',
            'app_livre_de_caisse_management' => 'Gestion - Livres de caisse',
            'app_quittancement_generator' => 'Generateur de quittancements',
            'admin_parametrage' => 'Parametrage',
            'admin_modules' => 'Modules',
            'admin_pages' => 'Pages',
            'admin_theme' => 'Theme du site',
            'admin_services' => 'Services',
            'admin_page_icons' => 'Icones des pages',
            'admin_page_titles' => 'Libelles des pages',
            'admin_menus' => 'Gestion du menu',
            'admin_permissions' => 'Acces utilisateur',
        ];
    }

    private function getDefaultPageEmojiIconMap(): array
    {
        if (is_array($this->defaultPageEmojiIconMapCache)) {
            return $this->defaultPageEmojiIconMapCache;
        }

        return $this->defaultPageEmojiIconMapCache = [
            'admin' => '🛠️',
            'app_dashboard' => '🏠',
            'app_profile' => '👤',
            'app_profile_create' => '👥',
            'app_annuaire' => '📇',
            'app_rh' => '👥',
            'app_compta' => '📊',
            'app_production' => '🛠️',
            'app_prestation' => '📋',
            'app_sinistre' => '⚠️',
            'app_serviceentreprise' => '🧩',
            'app_controleinterne' => '🔐',
            'app_communication' => '📝',
            'app_relationclient' => '📇',
            'app_marketing' => '🎨',
            'app_vente' => '📄',
            'admin_parametrage' => '⚙️',
            'admin_modules' => '🧩',
            'admin_theme' => '🎨',
            'admin_page_icons' => '🖼️',
            'admin_page_titles' => '📝',
            'admin_menus' => '🗂️',
            'admin_permissions' => '🔐',
        ];
    }

    private function getIconFallbackMaps(): array
    {
        if (is_array($this->iconFallbackMapsCache)) {
            return $this->iconFallbackMapsCache;
        }

        return $this->iconFallbackMapsCache = [
            'bootstrap' => [
                '📄' => 'bi-file-earmark-text',
                '🎫' => 'bi-ticket-perforated',
                '📋' => 'bi-card-list',
                '📊' => 'bi-bar-chart',
                '👤' => 'bi-person',
                '⚙️' => 'bi-gear',
                '👥' => 'bi-people',
                '🧩' => 'bi-puzzle',
                '📇' => 'bi-person-badge',
                '🛠️' => 'bi-tools',
                '🗄️' => 'bi-archive',
                '📝' => 'bi-pencil-square',
                '🎨' => 'bi-palette',
                '🖼️' => 'bi-images',
                '🗂️' => 'bi-folder',
                '🧪' => 'bi-beaker',
                '⚠️' => 'bi-exclamation-triangle',
                '🏠' => 'bi-house',
                '🔐' => 'bi-shield-lock',
            ],
            'fontawesome' => [
                '📄' => 'fa-solid fa-file-lines',
                '🎫' => 'fa-solid fa-ticket',
                '📋' => 'fa-solid fa-table-list',
                '📊' => 'fa-solid fa-chart-column',
                '👤' => 'fa-solid fa-user',
                '⚙️' => 'fa-solid fa-gear',
                '👥' => 'fa-solid fa-users',
                '🧩' => 'fa-solid fa-puzzle-piece',
                '📇' => 'fa-solid fa-address-card',
                '🛠️' => 'fa-solid fa-screwdriver-wrench',
                '🗄️' => 'fa-solid fa-box-archive',
                '📝' => 'fa-solid fa-pen-to-square',
                '🎨' => 'fa-solid fa-palette',
                '🖼️' => 'fa-regular fa-images',
                '🗂️' => 'fa-solid fa-folder-open',
                '🧪' => 'fa-solid fa-flask',
                '⚠️' => 'fa-solid fa-triangle-exclamation',
                '🏠' => 'fa-solid fa-house',
                '🔐' => 'fa-solid fa-shield-halved',
            ],
            'remixicon' => [
                '📄' => 'ri-file-text-line',
                '🎫' => 'ri-ticket-2-line',
                '📋' => 'ri-file-list-3-line',
                '📊' => 'ri-bar-chart-box-line',
                '👤' => 'ri-user-line',
                '⚙️' => 'ri-settings-3-line',
                '👥' => 'ri-team-line',
                '🧩' => 'ri-puzzle-line',
                '📇' => 'ri-contacts-book-line',
                '🛠️' => 'ri-tools-line',
                '🗄️' => 'ri-archive-line',
                '📝' => 'ri-edit-line',
                '🎨' => 'ri-palette-line',
                '🖼️' => 'ri-image-line',
                '🗂️' => 'ri-folders-line',
                '🧪' => 'ri-flask-line',
                '⚠️' => 'ri-alert-line',
                '🏠' => 'ri-home-line',
                '🔐' => 'ri-shield-keyhole-line',
            ],
            'boxicons' => [
                '📄' => 'bx bx-file',
                '🎫' => 'bx bx-purchase-tag-alt',
                '📋' => 'bx bx-list-ul',
                '📊' => 'bx bx-bar-chart-alt-2',
                '👤' => 'bx bx-user',
                '⚙️' => 'bx bx-cog',
                '👥' => 'bx bx-group',
                '🧩' => 'bx bx-extension',
                '📇' => 'bx bx-id-card',
                '🛠️' => 'bx bx-wrench',
                '🗄️' => 'bx bx-archive',
                '📝' => 'bx bx-edit-alt',
                '🎨' => 'bx bx-palette',
                '🖼️' => 'bx bx-images',
                '🗂️' => 'bx bx-folder',
                '🧪' => 'bx bx-test-tube',
                '⚠️' => 'bx bx-error',
                '🏠' => 'bx bx-home',
                '🔐' => 'bx bx-shield-quarter',
            ],
            'tabler' => [
                '📄' => 'ti ti-file-text',
                '🎫' => 'ti ti-ticket',
                '📋' => 'ti ti-list-details',
                '📊' => 'ti ti-chart-bar',
                '👤' => 'ti ti-user',
                '⚙️' => 'ti ti-settings',
                '👥' => 'ti ti-users',
                '🧩' => 'ti ti-puzzle',
                '📇' => 'ti ti-address-book',
                '🛠️' => 'ti ti-tool',
                '🗄️' => 'ti ti-archive',
                '📝' => 'ti ti-edit',
                '🎨' => 'ti ti-palette',
                '🖼️' => 'ti ti-photo',
                '🗂️' => 'ti ti-folder',
                '🧪' => 'ti ti-flask',
                '⚠️' => 'ti ti-alert-triangle',
                '🏠' => 'ti ti-home',
                '🔐' => 'ti ti-shield-lock',
            ],
            'material' => [
                '📄' => 'description',
                '🎫' => 'confirmation_number',
                '📋' => 'list_alt',
                '📊' => 'bar_chart',
                '👤' => 'person',
                '⚙️' => 'settings',
                '👥' => 'groups',
                '🧩' => 'extension',
                '📇' => 'contacts',
                '🛠️' => 'construction',
                '🗄️' => 'inventory_2',
                '📝' => 'edit_square',
                '🎨' => 'palette',
                '🖼️' => 'imagesmode',
                '🗂️' => 'folder',
                '🧪' => 'science',
                '⚠️' => 'warning',
                '🏠' => 'home',
                '🔐' => 'shield_lock',
            ],
        ];
    }

    private function renderIconInnerHtml(string $library, string $iconValue): string
    {
        $libraryConfig = $this->getAvailableIconLibraries()[$library] ?? $this->getAvailableIconLibraries()['emoji'];
        $renderMode = (string) ($libraryConfig['render_mode'] ?? 'emoji');

        if ($renderMode === 'class') {
            $className = $this->normalizeClassIconValue($library, $iconValue);
            if ($className === '') {
                return '';
            }

            return '<i class="' . htmlspecialchars($className, ENT_QUOTES, 'UTF-8') . '"></i>';
        }

        if ($renderMode === 'ligature') {
            return '<span class="material-symbols-outlined">' . htmlspecialchars($iconValue, ENT_QUOTES, 'UTF-8') . '</span>';
        }

        return htmlspecialchars($iconValue, ENT_QUOTES, 'UTF-8');
    }

    private function normalizeClassIconValue(string $library, string $iconValue): string
    {
        $iconValue = preg_replace('/\s+/', ' ', trim($iconValue));
        if ($iconValue === null || $iconValue === '') {
            return '';
        }

        return match ($library) {
            'bootstrap' => preg_match('/^bi-[a-z0-9-]+$/i', $iconValue) ? 'bi ' . $iconValue : $iconValue,
            'boxicons' => preg_match('/^bx-[a-z0-9-]+$/i', $iconValue) ? 'bx ' . $iconValue : $iconValue,
            'tabler' => preg_match('/^ti-[a-z0-9-]+$/i', $iconValue) ? 'ti ' . $iconValue : $iconValue,
            default => $iconValue,
        };
    }

    private function discoverConfigurableControllerClasses(): array
    {
        if (is_array($this->configurableControllerClassesCache)) {
            return $this->configurableControllerClassesCache;
        }

        $controllerDirectory = $this->kernel->getProjectDir() . '/src/Controller';
        if (!is_dir($controllerDirectory)) {
            return $this->configurableControllerClassesCache = [];
        }

        $controllers = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($controllerDirectory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
                continue;
            }

            $filePath = $fileInfo->getPathname();
            $relativePath = substr($filePath, strlen($controllerDirectory) + 1);
            if (!is_string($relativePath) || $relativePath === '') {
                continue;
            }

            $className = 'App\\Controller\\' . str_replace(
                ['/', '\\', '.php'],
                ['\\', '\\', ''],
                $relativePath,
            );

            if ($this->isExcludedControllerClass($className) || !class_exists($className)) {
                continue;
            }

            $controllers[$className] = $filePath;
        }

        ksort($controllers);

        return $this->configurableControllerClassesCache = $controllers;
    }

    private function isExcludedControllerClass(string $className): bool
    {
        return in_array(trim($className), self::EXCLUDED_CONTROLLER_CLASSES, true);
    }

    private function resolveControllerMetadataForRoute(Route $route, array $controllerClasses): ?array
    {
        $controllerReference = (string) ($route->getDefault('_controller') ?? '');
        if ($controllerReference === '') {
            return null;
        }

        $parsedController = $this->parseControllerReference($controllerReference);
        if ($parsedController === null) {
            return null;
        }

        $controllerClass = $parsedController['class'];
        if (!isset($controllerClasses[$controllerClass])) {
            return null;
        }

        return [
            'class' => $controllerClass,
            'method' => $parsedController['method'],
            'file' => $controllerClasses[$controllerClass],
        ];
    }

    private function parseControllerReference(string $controllerReference): ?array
    {
        $controllerReference = trim($controllerReference);
        if ($controllerReference === '' || !str_contains($controllerReference, '::')) {
            return null;
        }

        [$className, $methodName] = explode('::', $controllerReference, 2);
        $className = ltrim(trim($className), '\\');
        $methodName = preg_replace('/\(\)$/', '', trim($methodName));

        if ($className === '' || !is_string($methodName) || $methodName === '') {
            return null;
        }

        return [
            'class' => $className,
            'method' => $methodName,
        ];
    }

    private function isConfigurableControllerAction(string $controllerClass, string $methodName): bool
    {
        if (!class_exists($controllerClass) || !method_exists($controllerClass, $methodName)) {
            return false;
        }

        $method = new ReflectionMethod($controllerClass, $methodName);
        if (!$method->isPublic()) {
            return false;
        }

        $returnType = $method->getReturnType();
        if ($returnType instanceof ReflectionNamedType) {
            return !$this->isJsonResponseTypeName($returnType->getName());
        }

        if ($returnType instanceof ReflectionUnionType) {
            foreach ($returnType->getTypes() as $type) {
                if ($type instanceof ReflectionNamedType && $this->isJsonResponseTypeName($type->getName())) {
                    return false;
                }
            }
        }

        return true;
    }

    private function isJsonResponseTypeName(string $typeName): bool
    {
        $typeName = ltrim(trim($typeName), '\\');

        return $typeName !== '' && is_a($typeName, JsonResponse::class, true);
    }

    private function getRouteFolder(string $controllerClass): string
    {
        return str_starts_with($controllerClass, 'App\\Controller\\Admin\\') ? 'admin' : 'pages';
    }

    /**
     * @return array<string, object>
     */
    private function getModulesByRoute(): array
    {
        if (is_array($this->modulesByRouteCache)) {
            return $this->modulesByRouteCache;
        }

        $modulesByRoute = [];
        foreach ($this->moduleService->getAllModules() as $module) {
            $routeName = trim((string) $module->getRouteName());
            if ($routeName !== '') {
                $modulesByRoute[$routeName] = $module;
            }
        }

        return $this->modulesByRouteCache = $modulesByRoute;
    }

    private function buildConfigurablePagesFingerprint(): string
    {
        $routeSignatures = [];

        foreach ($this->router->getRouteCollection()->all() as $routeName => $route) {
            $routeSignatures[] = implode('|', [
                (string) $routeName,
                (string) $route->getPath(),
                implode(',', $route->getMethods()),
                (string) $route->getDefault('_controller'),
                (string) ($route->getDefault('_managed_page_path') ?? ''),
            ]);
        }

        sort($routeSignatures);

        $moduleSignatures = [];
        foreach ($this->moduleService->getAllModules() as $module) {
            $moduleSignatures[] = implode('|', [
                (string) $module->getName(),
                (string) $module->getRouteName(),
                (string) $module->getLabel(),
                (string) $module->getIcon(),
            ]);
        }

        sort($moduleSignatures);

        return sha1(json_encode([
            'routes' => $routeSignatures,
            'modules' => $moduleSignatures,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }
}
