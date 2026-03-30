<?php

namespace App\Service;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

class MenuConfigService
{
    public const MENUS_MODULE = 'menus';
    private const DEFAULT_PROFILE_TYPES = ['Admin', 'Responsable', 'Superviseur', 'Employe'];
    private const ADMIN_ONLY_ROUTES = [
        'admin',
        'app_profile_create',
        'app_profile_view',
    ];

    private ?array $profilesListCache = null;
    private ?array $usersDataCache = null;
    private ?array $availablePagesMapCache = null;
    private ?array $menuConfigStorageCache = null;
    private ?array $modulesByRouteCache = null;
    private ?array $activeModuleRoutesCache = null;
    private array $resolvedMenuConfigCache = [];
    private array $menuTreeCache = [];
    private array $firstAccessibleRouteCache = [];

    public function __construct(
        private KernelInterface $kernel,
        private RouterInterface $router,
        private UtilisateurRepository $utilisateurRepository,
        private ModuleService $moduleService,
        private PageDisplayService $pageDisplayService,
        private PagePermissionService $pagePermissionService,
    ) {}

    public function getAvailableMenuDefinitions(): array
    {
        return [
            'sidebar' => [
                'key' => 'sidebar',
                'label' => 'Menu Vertical',
            ],
            'header_right' => [
                'key' => 'header_right',
                'label' => 'Menu Header - Droit',
            ],
        ];
    }

    public function getProfilesList(): array
    {
        if (is_array($this->profilesListCache)) {
            return $this->profilesListCache;
        }

        $profiles = [];

        foreach (self::DEFAULT_PROFILE_TYPES as $profileType) {
            $profiles[$profileType] = $profileType;
        }

        foreach ($this->utilisateurRepository->findDistinctProfileTypes() as $profileType) {
            $normalizedProfileType = trim((string) $profileType);
            if ($normalizedProfileType !== '') {
                $profiles[$normalizedProfileType] = $normalizedProfileType;
            }
        }

        return $this->profilesListCache = array_values($profiles);
    }

    public function getUsersData(): array
    {
        if (is_array($this->usersDataCache)) {
            return $this->usersDataCache;
        }

        $users = [];
        $storage = $this->loadMenuConfigStorage();
        $menuDefinitions = $this->getAvailableMenuDefinitions();

        foreach ($this->utilisateurRepository->findAllSorted() as $user) {
            if (!($user instanceof Utilisateur)) {
                continue;
            }

            $userId = trim((string) ($user->getId() ?? ''));
            $menuOverrides = [];
            foreach ($menuDefinitions as $menuType => $_definition) {
                $menuOverrides[$menuType] = $userId !== '' && array_key_exists($userId, $storage['menus'][$menuType]['users'] ?? []);
            }

            $users[] = [
                'id' => $userId,
                'prenom' => (string) ($user->getPrenom() ?? ''),
                'nom' => (string) ($user->getNom() ?? ''),
                'profile_type' => (string) $user->getProfileType(),
                'effective_profile_type' => $user->getEffectiveProfileType(),
                'menu_overrides' => $menuOverrides,
            ];
        }

        return $this->usersDataCache = $users;
    }

    public function getAvailablePages(): array
    {
        $pages = array_values($this->getAvailablePagesMap());

        usort($pages, static function (array $left, array $right): int {
            $folderCompare = strcmp((string) ($left['folder'] ?? ''), (string) ($right['folder'] ?? ''));
            if ($folderCompare !== 0) {
                return $folderCompare;
            }

            return strcmp((string) ($left['label'] ?? $left['page'] ?? ''), (string) ($right['label'] ?? $right['page'] ?? ''));
        });

        return $pages;
    }

    public function isManagedPageRoute(string $routeName): bool
    {
        return isset($this->getAvailablePagesMap()[trim($routeName)]);
    }

    public function resolveMenuConfigForSelector(string $selectorType = '', string $selectorValue = '', string $menuType = 'sidebar'): array
    {
        $selectorType = trim($selectorType);
        $selectorValue = trim($selectorValue);
        $menuType = $this->normalizeMenuType($menuType);
        $cacheKey = $selectorType . ':' . $selectorValue . ':' . $menuType;

        if (isset($this->resolvedMenuConfigCache[$cacheKey])) {
            return $this->resolvedMenuConfigCache[$cacheKey];
        }

        $storage = $this->loadMenuConfigStorage();
        $menuStorage = $storage['menus'][$menuType] ?? $this->createEmptyMenuStorageBucket($menuType);
        $defaultMenu = $menuStorage['default'] !== [] ? $menuStorage['default'] : $this->getDefaultMenuConfigByType($menuType);

        if ($selectorType === 'user' && $selectorValue !== '') {
            if (array_key_exists($selectorValue, $menuStorage['users'])) {
                return $this->resolvedMenuConfigCache[$cacheKey] = [
                    'menu_type' => $menuType,
                    'menu' => $menuStorage['users'][$selectorValue],
                    'scope' => 'user',
                    'source' => 'user',
                    'source_label' => 'Configuration specifique utilisateur',
                ];
            }

            $user = $this->utilisateurRepository->find((int) $selectorValue);
            $profileType = $user instanceof Utilisateur ? $user->getEffectiveProfileType() : '';
            if ($profileType !== '' && array_key_exists($profileType, $menuStorage['profiles'])) {
                return $this->resolvedMenuConfigCache[$cacheKey] = [
                    'menu_type' => $menuType,
                    'menu' => $menuStorage['profiles'][$profileType],
                    'scope' => 'user',
                    'source' => 'profile',
                    'source_label' => 'Herite du profil ' . $profileType,
                    'inherited_profile' => $profileType,
                ];
            }

            return $this->resolvedMenuConfigCache[$cacheKey] = [
                'menu_type' => $menuType,
                'menu' => $defaultMenu,
                'scope' => 'user',
                'source' => 'default',
                'source_label' => 'Configuration par defaut',
            ];
        }

        if ($selectorType === 'profile' && $selectorValue !== '') {
            if (array_key_exists($selectorValue, $menuStorage['profiles'])) {
                return $this->resolvedMenuConfigCache[$cacheKey] = [
                    'menu_type' => $menuType,
                    'menu' => $menuStorage['profiles'][$selectorValue],
                    'scope' => 'profile',
                    'source' => 'profile',
                    'source_label' => 'Configuration specifique profil',
                ];
            }

            return $this->resolvedMenuConfigCache[$cacheKey] = [
                'menu_type' => $menuType,
                'menu' => $defaultMenu,
                'scope' => 'profile',
                'source' => 'default',
                'source_label' => 'Configuration par defaut',
            ];
        }

        return $this->resolvedMenuConfigCache[$cacheKey] = [
            'menu_type' => $menuType,
            'menu' => $defaultMenu,
            'scope' => 'default',
            'source' => 'default',
            'source_label' => 'Configuration par defaut',
        ];
    }

    public function saveMenuConfigForSelector(string $selectorType, string $selectorValue, array $menu, string &$error = '', string $menuType = 'sidebar'): bool
    {
        $selectorType = trim($selectorType);
        $selectorValue = trim($selectorValue);
        $menuType = $this->normalizeMenuType($menuType);

        if (!in_array($selectorType, ['profile', 'user'], true)) {
            $error = 'Type de selection invalide.';

            return false;
        }

        if ($selectorValue === '') {
            $error = 'Selection manquante.';

            return false;
        }

        if ($selectorType === 'profile' && !in_array($selectorValue, $this->getProfilesList(), true)) {
            $error = 'Profil invalide.';

            return false;
        }

        if ($selectorType === 'user' && !($this->utilisateurRepository->find((int) $selectorValue) instanceof Utilisateur)) {
            $error = 'Utilisateur invalide.';

            return false;
        }

        $storage = $this->loadMenuConfigStorage();
        $bucket = $selectorType === 'profile' ? 'profiles' : 'users';
        $storage['menus'][$menuType][$bucket][$selectorValue] = $this->sanitizeMenuConfigItems($menu);

        if (!$this->saveMenuConfigStorage($storage)) {
            $error = 'Impossible d ecrire le fichier de configuration.';

            return false;
        }

        $this->clearMenuRuntimeCaches();
        return true;
    }

    public function resetMenuConfigForSelector(string $selectorType, string $selectorValue, string &$error = '', string $menuType = 'sidebar'): bool
    {
        $selectorType = trim($selectorType);
        $selectorValue = trim($selectorValue);
        $menuType = $this->normalizeMenuType($menuType);

        if (!in_array($selectorType, ['profile', 'user'], true)) {
            $error = 'Type de selection invalide.';

            return false;
        }

        if ($selectorValue === '') {
            $error = 'Selection manquante.';

            return false;
        }

        $storage = $this->loadMenuConfigStorage();
        $bucket = $selectorType === 'profile' ? 'profiles' : 'users';
        unset($storage['menus'][$menuType][$bucket][$selectorValue]);

        if (!$this->saveMenuConfigStorage($storage)) {
            $error = 'Impossible d ecrire le fichier de configuration.';

            return false;
        }

        $this->clearMenuRuntimeCaches();
        return true;
    }

    public function getSidebarMenuTreeForUser(?Utilisateur $user, string $currentRoute = ''): array
    {
        return $this->buildMenuTreeForUser($user, 'sidebar', $currentRoute);
    }

    public function getHeaderRightMenuTreeForUser(?Utilisateur $user, string $currentRoute = ''): array
    {
        return $this->buildMenuTreeForUser($user, 'header_right', $currentRoute);
    }

    public function getFirstAccessibleRouteName(?Utilisateur $user): string
    {
        if (!($user instanceof Utilisateur)) {
            return 'app_login';
        }

        $userId = $user->getId();
        if ($userId !== null && isset($this->firstAccessibleRouteCache[$userId])) {
            return $this->firstAccessibleRouteCache[$userId];
        }

        $seen = [];
        $candidateMenus = [];
        $resolvedMenu = $this->resolveMenuConfigForSelector('user', (string) $user->getId(), 'sidebar');

        if (is_array($resolvedMenu['menu'] ?? null)) {
            $candidateMenus[] = $resolvedMenu['menu'];
        }

        $candidateMenus[] = $this->getDefaultMenuConfigByType('sidebar');

        foreach ($candidateMenus as $menuItems) {
            foreach ($menuItems as $item) {
                $routeName = trim((string) ($item['page'] ?? ''));
                if ($routeName === '' || isset($seen[$routeName])) {
                    continue;
                }

                $seen[$routeName] = true;

                if ($this->isRouteVisibleForUser($routeName, $user)) {
                    return $userId !== null ? ($this->firstAccessibleRouteCache[$userId] = $routeName) : $routeName;
                }
            }
        }

        foreach ($this->getAvailablePages() as $page) {
            $routeName = trim((string) ($page['page'] ?? ''));
            if ($routeName === '' || isset($seen[$routeName])) {
                continue;
            }

            if ($this->isRouteVisibleForUser($routeName, $user)) {
                return $userId !== null ? ($this->firstAccessibleRouteCache[$userId] = $routeName) : $routeName;
            }
        }

        return $userId !== null ? ($this->firstAccessibleRouteCache[$userId] = 'app_dashboard') : 'app_dashboard';
    }

    public function getFirstAccessibleUrl(?Utilisateur $user): string
    {
        $pagePath = $this->getFirstAccessibleRouteName($user);
        if ($pagePath !== '' && isset($this->getAvailablePagesMap()[$pagePath]['url'])) {
            return (string) $this->getAvailablePagesMap()[$pagePath]['url'];
        }

        return $this->router->generate('app_dashboard');
    }

    public function canUserAccessRoute(?Utilisateur $user, string $routeName): bool
    {
        if (!($user instanceof Utilisateur)) {
            return false;
        }

        return $this->isRouteVisibleForUser(trim($routeName), $user);
    }

    public function removeUserOverrides(int $userId): void
    {
        $key = trim((string) $userId);
        if ($key === '') {
            return;
        }

        $storage = $this->loadMenuConfigStorage();
        $changed = false;

        foreach ($this->getAvailableMenuDefinitions() as $menuType => $_definition) {
            if (isset($storage['menus'][$menuType]['users'][$key])) {
                unset($storage['menus'][$menuType]['users'][$key]);
                $changed = true;
            }
        }

        if ($changed && $this->saveMenuConfigStorage($storage)) {
            $this->clearMenuRuntimeCaches();
        }
    }

    public function removePageFromAllMenus(string $pagePath): void
    {
        $pagePath = trim($pagePath);
        if ($pagePath === '') {
            return;
        }

        $storage = $this->loadMenuConfigStorage();
        $changed = false;

        foreach ($this->getAvailableMenuDefinitions() as $menuType => $_definition) {
            $defaultItems = (array) ($storage['menus'][$menuType]['default'] ?? []);
            $filteredDefaultItems = $this->removePageFromMenuItems($defaultItems, $pagePath);
            if ($filteredDefaultItems !== $defaultItems) {
                $storage['menus'][$menuType]['default'] = $filteredDefaultItems;
                $changed = true;
            }

            foreach (['profiles', 'users'] as $bucket) {
                foreach ((array) ($storage['menus'][$menuType][$bucket] ?? []) as $key => $items) {
                    $filteredItems = $this->removePageFromMenuItems((array) $items, $pagePath);
                    if ($filteredItems !== $items) {
                        $storage['menus'][$menuType][$bucket][$key] = $filteredItems;
                        $changed = true;
                    }
                }
            }
        }

        if ($changed && $this->saveMenuConfigStorage($storage)) {
            $this->clearMenuRuntimeCaches();
        }
    }

    private function buildMenuTreeForUser(?Utilisateur $user, string $menuType, string $currentRoute): array
    {
        if (!($user instanceof Utilisateur)) {
            return [];
        }

        $userId = $user->getId() ?? 0;
        $cacheKey = $userId . ':' . $menuType . ':' . $currentRoute;
        if (isset($this->menuTreeCache[$cacheKey])) {
            return $this->menuTreeCache[$cacheKey];
        }

        $resolved = $this->resolveMenuConfigForSelector('user', (string) $user->getId(), $menuType);
        $availablePages = $this->getAvailablePagesMap();
        $items = [];

        foreach ((array) ($resolved['menu'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $routeName = trim((string) ($item['page'] ?? ''));
            if ($routeName === '' || !isset($availablePages[$routeName])) {
                continue;
            }

            if (!$this->isRouteVisibleForUser($routeName, $user)) {
                continue;
            }

            $pageMeta = $availablePages[$routeName];
            $fallbackIcon = trim((string) ($item['icon'] ?? $pageMeta['icon'] ?? 'bi-file-earmark-text'));

            $items[] = [
                'page' => $routeName,
                'url' => (string) ($pageMeta['url'] ?? ''),
                'label' => $this->pageDisplayService->resolveNavigationLabel(
                    $routeName,
                    (string) ($item['label'] ?? $pageMeta['label'] ?? $routeName),
                ),
                'icon_html' => $this->pageDisplayService->renderNavigationIcon($routeName, $fallbackIcon, 'bootstrap', true),
                'folder' => (string) ($pageMeta['folder'] ?? 'pages'),
                'parent_page' => trim((string) ($item['parent_page'] ?? '')) ?: null,
                'icon_only' => $menuType === 'header_right' && !empty($item['icon_only']),
            ];
        }

        $tree = $this->buildTree($items);
        $decorated = [];

        foreach ($tree as $node) {
            $decorated[] = $this->decorateNode($node, $currentRoute, $menuType);
        }

        return $this->menuTreeCache[$cacheKey] = $decorated;
    }

    private function decorateNode(array $node, string $currentRoute, string $menuType): array
    {
        $item = (array) ($node['item'] ?? []);
        $children = [];
        $hasActiveChild = false;

        foreach ((array) ($node['children'] ?? []) as $childNode) {
            $child = $this->decorateNode($childNode, $currentRoute, $menuType);
            $children[] = $child;
            if (!empty($child['is_current']) || !empty($child['is_open'])) {
                $hasActiveChild = true;
            }
        }

        $page = (string) ($item['page'] ?? '');
        $isCurrent = $currentRoute !== '' && $currentRoute === $page;

        $item['children'] = $children;
        $item['is_current'] = $isCurrent;
        $item['is_open'] = $isCurrent || $hasActiveChild;
        $item['submenu_id'] = ($menuType === 'header_right' ? 'header-menu-' : 'submenu-') . preg_replace('/[^a-zA-Z0-9_\-]/', '-', $page);

        return $item;
    }

    private function buildTree(array $items): array
    {
        $nodes = [];
        $roots = [];

        foreach ($items as $item) {
            $page = trim((string) ($item['page'] ?? ''));
            if ($page === '') {
                continue;
            }

            $nodes[$page] = [
                'item' => $item,
                'children' => [],
            ];
        }

        foreach ($items as $item) {
            $page = trim((string) ($item['page'] ?? ''));
            if ($page === '' || !isset($nodes[$page])) {
                continue;
            }

            $parentPage = trim((string) ($item['parent_page'] ?? ''));
            if ($parentPage !== '' && isset($nodes[$parentPage])) {
                $nodes[$parentPage]['children'][] = &$nodes[$page];
                continue;
            }

            $roots[] = &$nodes[$page];
        }

        return $roots;
    }

    private function isRouteVisibleForUser(string $routeName, Utilisateur $user): bool
    {
        if (!$this->isManagedPageRoute($routeName)) {
            return false;
        }

        if (isset($this->getModulesByRoute()[$routeName]) && !isset($this->getActiveModuleRoutes()[$routeName])) {
            return false;
        }

        if ($this->requiresAdminRole($routeName) && !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return false;
        }

        return $this->pagePermissionService->canAccessRoute($routeName, $user);
    }

    private function requiresAdminRole(string $routeName): bool
    {
        return str_starts_with($routeName, 'admin_') || in_array($routeName, self::ADMIN_ONLY_ROUTES, true);
    }

    private function getAvailablePagesMap(): array
    {
        if (is_array($this->availablePagesMapCache)) {
            return $this->availablePagesMapCache;
        }

        $pages = [];
        $modulesByRoute = $this->getModulesByRoute();

        foreach ($this->pageDisplayService->getConfigurablePages() as $page) {
            $routeName = trim((string) ($page['page_path'] ?? ''));
            if ($routeName === '') {
                continue;
            }

            $actualRouteName = trim((string) ($page['route_name'] ?? $routeName));
            $routeParameters = is_array($page['route_parameters'] ?? null) ? $page['route_parameters'] : [];
            try {
                $url = $actualRouteName !== '' ? $this->router->generate($actualRouteName, $routeParameters) : (string) ($page['route_path'] ?? '');
            } catch (\Throwable) {
                $url = (string) ($page['route_path'] ?? '');
            }

            $fallbackIcon = isset($modulesByRoute[$routeName])
                ? trim((string) $modulesByRoute[$routeName]->getIcon())
                : $this->pageDisplayService->getDefaultIconValue($routeName, 'bootstrap', 'bi-file-earmark-text', 'bootstrap');

            $pages[$routeName] = [
                'page' => $routeName,
                'url' => $url,
                'label' => $this->pageDisplayService->resolveNavigationLabel(
                    $routeName,
                    (string) ($page['default_title'] ?? $routeName),
                ),
                'icon' => $fallbackIcon !== '' ? $fallbackIcon : 'bi-file-earmark-text',
                'icon_html' => $this->pageDisplayService->renderNavigationIcon($routeName, $fallbackIcon, 'bootstrap'),
                'folder' => in_array((string) ($page['folder'] ?? 'pages'), ['admin', 'pages'], true)
                    ? (string) $page['folder']
                    : ((str_starts_with($routeName, 'admin_') || $routeName === 'admin') ? 'admin' : 'pages'),
                'route_path' => (string) ($page['route_path'] ?? ''),
                'route_name' => $actualRouteName,
                'route_parameters' => $routeParameters,
                'group' => (string) ($page['group'] ?? ''),
            ];
        }

        return $this->availablePagesMapCache = $pages;
    }

    private function getDefaultMenuConfigByType(string $menuType = 'sidebar'): array
    {
        $menuType = $this->normalizeMenuType($menuType);

        if ($menuType === 'header_right') {
            return $this->buildDefaultHeaderRightMenuConfig();
        }

        return $this->buildDefaultSidebarMenuConfig();
    }

    private function buildDefaultSidebarMenuConfig(): array
    {
        $availablePages = $this->getAvailablePagesMap();
        $routes = [];

        if (isset($availablePages['app_dashboard'])) {
            $routes[] = 'app_dashboard';
        }

        foreach ($this->moduleService->getActiveModules() as $module) {
            $routeName = trim((string) $module->getRouteName());
            if ($routeName !== '' && str_starts_with($routeName, 'app_')) {
                $routes[] = $routeName;
            }
        }

        $routes = array_values(array_unique($routes));
        $config = [];

        foreach ($routes as $routeName) {
            if (!isset($availablePages[$routeName])) {
                continue;
            }

            $pageMeta = $availablePages[$routeName];
            $config[] = [
                'page' => $routeName,
                'url' => (string) $pageMeta['url'],
                'label' => (string) $pageMeta['label'],
                'icon' => (string) $pageMeta['icon'],
                'folder' => (string) $pageMeta['folder'],
                'parent_page' => null,
                'icon_only' => false,
            ];
        }

        return $config;
    }

    private function buildDefaultHeaderRightMenuConfig(): array
    {
        $availablePages = $this->getAvailablePagesMap();
        if (!isset($availablePages['admin_parametrage'])) {
            return [];
        }

        return [[
            'page' => 'admin_parametrage',
            'url' => (string) $availablePages['admin_parametrage']['url'],
            'label' => (string) $availablePages['admin_parametrage']['label'],
            'icon' => (string) $availablePages['admin_parametrage']['icon'],
            'folder' => (string) $availablePages['admin_parametrage']['folder'],
            'parent_page' => null,
            'icon_only' => false,
        ]];
    }

    private function createEmptyMenuStorageBucket(string $menuType = 'sidebar'): array
    {
        return [
            'default' => $this->getDefaultMenuConfigByType($menuType),
            'profiles' => [],
            'users' => [],
        ];
    }

    private function normalizeMenuType(string $menuType = ''): string
    {
        $menuType = trim($menuType);
        if ($menuType === '' || $menuType === 'vertical') {
            return 'sidebar';
        }

        return array_key_exists($menuType, $this->getAvailableMenuDefinitions()) ? $menuType : 'sidebar';
    }

    private function loadMenuConfigStorage(): array
    {
        if (is_array($this->menuConfigStorageCache)) {
            return $this->menuConfigStorageCache;
        }

        $filePath = $this->getStoragePath();
        if (!is_file($filePath)) {
            return $this->menuConfigStorageCache = $this->normalizeMenuConfigStorage(null);
        }

        $raw = @file_get_contents($filePath);
        if ($raw === false || trim($raw) === '') {
            return $this->menuConfigStorageCache = $this->normalizeMenuConfigStorage(null);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $this->menuConfigStorageCache = $this->normalizeMenuConfigStorage(null);
        }

        return $this->menuConfigStorageCache = $this->normalizeMenuConfigStorage($decoded);
    }

    private function saveMenuConfigStorage(array $storage): bool
    {
        $normalized = $this->normalizeMenuConfigStorage($storage);
        $encoded = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            return false;
        }

        $written = @file_put_contents($this->getStoragePath(), $encoded . PHP_EOL) !== false;
        if ($written) {
            $this->menuConfigStorageCache = $normalized;
        }

        return $written;
    }

    private function normalizeMenuConfigStorage(mixed $decoded): array
    {
        $definitions = $this->getAvailableMenuDefinitions();
        $storage = ['menus' => []];

        foreach ($definitions as $menuType => $_definition) {
            $storage['menus'][$menuType] = $this->createEmptyMenuStorageBucket($menuType);
        }

        if (!is_array($decoded)) {
            return $storage;
        }

        if ($this->isMenuConfigList($decoded) || isset($decoded['default']) || isset($decoded['profiles']) || isset($decoded['users'])) {
            $storage['menus']['sidebar'] = $this->normalizeSingleMenuStorage($decoded, 'sidebar');

            return $storage;
        }

        foreach ((array) ($decoded['menus'] ?? []) as $menuType => $menuStorage) {
            $normalizedType = $this->normalizeMenuType((string) $menuType);
            $storage['menus'][$normalizedType] = $this->normalizeSingleMenuStorage($menuStorage, $normalizedType);
        }

        return $storage;
    }

    private function normalizeSingleMenuStorage(mixed $decoded, string $menuType = 'sidebar'): array
    {
        $menuType = $this->normalizeMenuType($menuType);
        $storage = $this->createEmptyMenuStorageBucket($menuType);

        if ($this->isMenuConfigList($decoded)) {
            $storage['default'] = $this->sanitizeMenuConfigItems((array) $decoded);

            return $storage;
        }

        if (!is_array($decoded)) {
            return $storage;
        }

        if (isset($decoded['default']) && is_array($decoded['default'])) {
            $normalizedDefault = $this->sanitizeMenuConfigItems($decoded['default']);
            if ($normalizedDefault !== []) {
                $storage['default'] = $normalizedDefault;
            }
        }

        foreach ((array) ($decoded['profiles'] ?? []) as $profileName => $items) {
            $profileKey = trim((string) $profileName);
            if ($profileKey === '' || !is_array($items)) {
                continue;
            }

            $storage['profiles'][$profileKey] = $this->sanitizeMenuConfigItems($items);
        }

        foreach ((array) ($decoded['users'] ?? []) as $userId => $items) {
            $userKey = trim((string) $userId);
            if ($userKey === '' || !is_array($items)) {
                continue;
            }

            $storage['users'][$userKey] = $this->sanitizeMenuConfigItems($items);
        }

        return $storage;
    }

    private function sanitizeMenuConfigItems(array $menu): array
    {
        $clean = [];
        $availablePages = $this->getAvailablePagesMap();

        foreach ($menu as $item) {
            if (!is_array($item)) {
                continue;
            }

            $page = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($item['page'] ?? ''));
            if ($page === '' || !isset($availablePages[$page])) {
                continue;
            }

            $pageMeta = $availablePages[$page];
            $parentPage = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($item['parent_page'] ?? ''));
            $label = trim((string) ($item['label'] ?? $pageMeta['label'] ?? $page));
            if ($label === '') {
                $label = $page;
            }

            $icon = trim((string) ($item['icon'] ?? $pageMeta['icon'] ?? 'bi-file-earmark-text'));
            if ($icon === '') {
                $icon = 'bi-file-earmark-text';
            }

            $clean[] = [
                'page' => $page,
                'url' => (string) ($pageMeta['url'] ?? ''),
                'label' => mb_substr(strip_tags($label), 0, 60, 'UTF-8'),
                'icon' => mb_substr($icon, 0, 60, 'UTF-8'),
                'folder' => (string) ($pageMeta['folder'] ?? 'pages'),
                'parent_page' => $parentPage !== '' ? $parentPage : null,
                'icon_only' => !empty($item['icon_only']),
            ];
        }

        return $this->normalizeMenuHierarchy($clean);
    }

    private function normalizeMenuHierarchy(array $items): array
    {
        $normalized = [];
        $seen = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $page = trim((string) ($item['page'] ?? ''));
            if ($page === '') {
                continue;
            }

            $parentPage = trim((string) ($item['parent_page'] ?? ''));
            if ($parentPage === '' || $parentPage === $page || !isset($seen[$parentPage])) {
                $item['parent_page'] = null;
            } else {
                $item['parent_page'] = $parentPage;
            }

            $normalized[] = $item;
            $seen[$page] = true;
        }

        return $normalized;
    }

    private function isMenuConfigList(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    private function getStoragePath(): string
    {
        return $this->kernel->getProjectDir() . '/menu_config.json';
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

    /**
     * @return array<string, bool>
     */
    private function getActiveModuleRoutes(): array
    {
        if (is_array($this->activeModuleRoutesCache)) {
            return $this->activeModuleRoutesCache;
        }

        $activeRoutes = [];
        foreach ($this->moduleService->getActiveModules() as $module) {
            $routeName = trim((string) $module->getRouteName());
            if ($routeName !== '') {
                $activeRoutes[$routeName] = true;
            }
        }

        return $this->activeModuleRoutesCache = $activeRoutes;
    }

    private function clearMenuRuntimeCaches(): void
    {
        $this->availablePagesMapCache = null;
        $this->menuConfigStorageCache = null;
        $this->resolvedMenuConfigCache = [];
        $this->menuTreeCache = [];
        $this->firstAccessibleRouteCache = [];
    }

    private function removePageFromMenuItems(array $items, string $pagePath): array
    {
        $filtered = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (trim((string) ($item['page'] ?? '')) === $pagePath) {
                continue;
            }

            if (trim((string) ($item['parent_page'] ?? '')) === $pagePath) {
                $item['parent_page'] = null;
            }

            $filtered[] = $item;
        }

        return $this->normalizeMenuHierarchy($filtered);
    }
}
