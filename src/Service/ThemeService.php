<?php

namespace App\Service;

use App\Entity\ThemeSetting;
use App\Repository\ThemeSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ThemeService
{
    public const DEFAULT_TEMPLATE = 'template2';

    private ?array $fontFamilyOptionsCache = null;
    private ?array $availableTemplatesCache = null;
    private array $templateStylesheetVersionCache = [];
    private array $publishedTemplateStylesheetAssetPathCache = [];

    public function __construct(
        private ThemeSettingRepository $settingRepository,
        private CacheInterface $cache,
        private EntityManagerInterface $em,
        private KernelInterface $kernel,
    ) {}

    public function getAll(): array
    {
        return $this->cache->get('theme_settings_all', function (ItemInterface $item) {
            $item->expiresAfter(3600);

            return $this->normalizeSettings($this->settingRepository->findAllAsArray());
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->getAll();

        return $settings[$key] ?? $default;
    }

    public function getDefaultSettings(): array
    {
        $templates = $this->getAvailableTemplates();
        $defaultTemplate = isset($templates[self::DEFAULT_TEMPLATE]) ? self::DEFAULT_TEMPLATE : ((string) array_key_first($templates) ?: self::DEFAULT_TEMPLATE);

        return [
            'active_template' => $defaultTemplate,
            'site_title' => 'Dashboard ADEP',
            'site_tagline' => '',
            'logo_path' => '',
            'logo_size' => 40,
            'sticky_header_enabled' => true,
            'user_info' => true,
            'header_right_menu' => true,
            'dark_mode_toggle' => true,
            'app_background_color' => '#FFFFFF',
            'header_background_color' => '#F8FAFC',
            'menu_background_color' => '#F8FAFC',
            'menu_text_color' => '#475569',
            'heading_color' => '#0F172A',
            'primary_button_color' => '#3B82F6',
            'primary_button_text_color' => '#FFFFFF',
            'dark_app_background_color' => '#0F172A',
            'dark_header_background_color' => '#1E293B',
            'dark_menu_background_color' => '#1E293B',
            'dark_menu_text_color' => '#CBD5E1',
            'dark_heading_color' => '#F8FAFC',
            'dark_primary_button_color' => '#3B82F6',
            'dark_primary_button_text_color' => '#FFFFFF',
            'body_font' => 'system-ui',
            'body_font_size' => 16,
            'menu_font' => 'system-ui',
            'menu_font_size' => 15,
            'heading_font' => 'system-ui',
            'heading_font_size' => 32,
            'button_font' => 'system-ui',
            'button_font_size' => 15,
            'button_radius' => 10,
        ];
    }

    public function getFontFamilyOptions(): array
    {
        if (is_array($this->fontFamilyOptionsCache)) {
            return $this->fontFamilyOptionsCache;
        }

        return $this->fontFamilyOptionsCache = [
            'system-ui' => ['label' => 'System UI', 'css' => 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif'],
            'inter' => ['label' => 'Inter (Google Fonts)', 'css' => '"Inter", system-ui, sans-serif', 'google_url' => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap'],
            'poppins' => ['label' => 'Poppins (Google Fonts)', 'css' => '"Poppins", system-ui, sans-serif', 'google_url' => 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap'],
            'montserrat' => ['label' => 'Montserrat (Google Fonts)', 'css' => '"Montserrat", system-ui, sans-serif', 'google_url' => 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap'],
            'roboto' => ['label' => 'Roboto (Google Fonts)', 'css' => '"Roboto", Arial, sans-serif', 'google_url' => 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap'],
            'open-sans' => ['label' => 'Open Sans (Google Fonts)', 'css' => '"Open Sans", Arial, sans-serif', 'google_url' => 'https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700;800&display=swap'],
            'nunito-sans' => ['label' => 'Nunito Sans (Google Fonts)', 'css' => '"Nunito Sans", system-ui, sans-serif', 'google_url' => 'https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&display=swap'],
            'segoe-ui' => ['label' => 'Segoe UI', 'css' => '"Segoe UI", Tahoma, Geneva, Verdana, sans-serif'],
            'arial' => ['label' => 'Arial', 'css' => 'Arial, Helvetica, sans-serif'],
            'verdana' => ['label' => 'Verdana', 'css' => 'Verdana, Geneva, sans-serif'],
            'trebuchet' => ['label' => 'Trebuchet MS', 'css' => '"Trebuchet MS", Helvetica, sans-serif'],
            'georgia' => ['label' => 'Georgia', 'css' => 'Georgia, "Times New Roman", serif'],
            'lora' => ['label' => 'Lora (Google Fonts)', 'css' => '"Lora", Georgia, serif', 'google_url' => 'https://fonts.googleapis.com/css2?family=Lora:wght@400;500;600;700&display=swap'],
            'merriweather' => ['label' => 'Merriweather (Google Fonts)', 'css' => '"Merriweather", Georgia, serif', 'google_url' => 'https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&display=swap'],
            'garamond' => ['label' => 'Garamond', 'css' => 'Garamond, "Times New Roman", serif'],
            'source-serif-4' => ['label' => 'Source Serif 4 (Google Fonts)', 'css' => '"Source Serif 4", Georgia, serif', 'google_url' => 'https://fonts.googleapis.com/css2?family=Source+Serif+4:wght@400;600;700&display=swap'],
            'courier' => ['label' => 'Courier New', 'css' => '"Courier New", monospace'],
        ];
    }

    public function getGoogleFontStylesheets(array $settings = []): array
    {
        $settings = $settings !== [] ? $this->normalizeSettings($settings) : $this->getAll();
        $fontOptions = $this->getFontFamilyOptions();
        $urls = [];

        foreach (['body_font', 'menu_font', 'heading_font', 'button_font'] as $field) {
            $fontKey = (string) ($settings[$field] ?? '');
            $googleUrl = (string) ($fontOptions[$fontKey]['google_url'] ?? '');
            if ($googleUrl !== '') {
                $urls[$googleUrl] = $googleUrl;
            }
        }

        return array_values($urls);
    }

    public function getAvailableTemplates(): array
    {
        if (is_array($this->availableTemplatesCache)) {
            return $this->availableTemplatesCache;
        }

        $templatesDir = $this->kernel->getProjectDir() . '/templates';
        $templates = [];

        if (!is_dir($templatesDir)) {
            return [
                self::DEFAULT_TEMPLATE => [
                    'key' => self::DEFAULT_TEMPLATE,
                    'label' => 'Template2',
                    'stylesheet' => '/css/theme-template/' . self::DEFAULT_TEMPLATE . '/styles.css',
                    'has_layout' => true,
                ],
            ];
        }

        foreach (scandir($templatesDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $templateDir = $templatesDir . '/' . $entry;
            if (!is_dir($templateDir) || !is_file($templateDir . '/CSS/styles.css')) {
                continue;
            }

            $templates[$entry] = [
                'key' => $entry,
                'label' => ucwords(str_replace(['-', '_'], ' ', $entry)),
                'stylesheet' => '/css/theme-template/' . $entry . '/styles.css',
                'has_layout' => is_file($templateDir . '/layout.php'),
            ];
        }

        if ($templates === []) {
            $templates[self::DEFAULT_TEMPLATE] = [
                'key' => self::DEFAULT_TEMPLATE,
                'label' => 'Template2',
                'stylesheet' => '/css/theme-template/' . self::DEFAULT_TEMPLATE . '/styles.css',
                'has_layout' => true,
            ];
        }

        ksort($templates, SORT_NATURAL | SORT_FLAG_CASE);

        return $this->availableTemplatesCache = $templates;
    }

    public function getActiveTemplateStylesheet(array $settings = []): string
    {
        $settings = $settings !== [] ? $this->normalizeSettings($settings) : $this->getAll();
        $templates = $this->getAvailableTemplates();
        $activeTemplate = (string) ($settings['active_template'] ?? self::DEFAULT_TEMPLATE);

        if (isset($templates[$activeTemplate]['stylesheet'])) {
            return (string) $templates[$activeTemplate]['stylesheet'];
        }

        return '/css/theme-template/' . self::DEFAULT_TEMPLATE . '/styles.css';
    }

    public function getTemplateStylesheetFilePath(string $template): string
    {
        $template = trim($template);
        if ($template === '') {
            $template = self::DEFAULT_TEMPLATE;
        }

        return $this->kernel->getProjectDir() . '/templates/' . $template . '/CSS/styles.css';
    }

    public function getTemplateStylesheetVersion(string $template): int
    {
        $template = trim($template);
        if ($template === '') {
            $template = self::DEFAULT_TEMPLATE;
        }

        if (isset($this->templateStylesheetVersionCache[$template])) {
            return $this->templateStylesheetVersionCache[$template];
        }

        $filePath = $this->getTemplateStylesheetFilePath($template);
        if (!is_file($filePath)) {
            return $this->templateStylesheetVersionCache[$template] = 1;
        }

        return $this->templateStylesheetVersionCache[$template] = (int) (filemtime($filePath) ?: 1);
    }

    public function getPublishedTemplateStylesheetAssetPath(string $template): string
    {
        $template = trim($template);
        if ($template === '') {
            $template = self::DEFAULT_TEMPLATE;
        }

        if (isset($this->publishedTemplateStylesheetAssetPathCache[$template])) {
            return $this->publishedTemplateStylesheetAssetPathCache[$template];
        }

        $relativeAssetPath = 'templates/' . $template . '/CSS/styles.css';
        $sourcePath = $this->getTemplateStylesheetFilePath($template);
        $targetPath = $this->kernel->getProjectDir() . '/public/' . str_replace('/', DIRECTORY_SEPARATOR, $relativeAssetPath);

        if (is_file($sourcePath)) {
            $targetDirectory = dirname($targetPath);
            if (!is_dir($targetDirectory)) {
                @mkdir($targetDirectory, 0777, true);
            }

            $sourceMTime = (int) (filemtime($sourcePath) ?: 0);
            $targetMTime = is_file($targetPath) ? (int) (filemtime($targetPath) ?: 0) : 0;

            if (!is_file($targetPath) || $targetMTime < $sourceMTime) {
                @copy($sourcePath, $targetPath);
                if ($sourceMTime > 0 && is_file($targetPath)) {
                    @touch($targetPath, $sourceMTime);
                }
            }
        }

        return $this->publishedTemplateStylesheetAssetPathCache[$template] = $relativeAssetPath;
    }

    public function saveMultiple(array $data): void
    {
        $normalized = $this->normalizeSettings(array_merge($this->getAll(), $data));

        foreach ($normalized as $key => $value) {
            $setting = $this->settingRepository->findByKey($key);

            if (!$setting) {
                $setting = new ThemeSetting();
                $setting->setSettingKey($key);
                $this->em->persist($setting);
            }

            $setting->setSettingValue($this->stringifySettingValue($value));
        }

        $this->em->flush();
        $this->cache->delete('theme_settings_all');
        $this->availableTemplatesCache = null;
        $this->templateStylesheetVersionCache = [];
        $this->publishedTemplateStylesheetAssetPathCache = [];
    }

    public function resetDefaults(): void
    {
        foreach ($this->settingRepository->findAll() as $setting) {
            $this->em->remove($setting);
        }

        $this->em->flush();
        $this->cache->delete('theme_settings_all');
        $this->availableTemplatesCache = null;
        $this->templateStylesheetVersionCache = [];
        $this->publishedTemplateStylesheetAssetPathCache = [];
    }

    public function normalizeLogoPath(string $value): string
    {
        $path = trim(str_replace('\\', '/', $value));
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'uploads/images/theme/')) {
            return $path;
        }

        return 'uploads/images/theme/' . ltrim(basename($path), '/');
    }

    public function resolveLogoFilePath(string $logoPath): string
    {
        $normalized = $this->normalizeLogoPath($logoPath);
        if ($normalized === '') {
            return '';
        }

        return $this->kernel->getProjectDir() . '/public/' . str_replace('/', DIRECTORY_SEPARATOR, ltrim($normalized, '/'));
    }

    public function deleteLogoFile(string $logoPath): void
    {
        $fullPath = $this->resolveLogoFilePath($logoPath);
        $baseDir = realpath($this->kernel->getProjectDir() . '/public/uploads/images/theme');
        if ($fullPath === '' || $baseDir === false) {
            return;
        }

        $realDir = realpath(dirname($fullPath));
        if ($realDir === false || !str_starts_with($realDir, $baseDir)) {
            return;
        }

        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    public function buildCssOverrides(array $settings = []): string
    {
        $settings = $settings !== [] ? $this->normalizeSettings($settings) : $this->getAll();
        $cacheKey = 'theme_css_overrides_' . md5((string) json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($settings): string {
            $item->expiresAfter(86400);

            return $this->doBuildCssOverrides($settings);
        });
    }

    private function doBuildCssOverrides(array $settings): string
    {
        $fontOptions = $this->getFontFamilyOptions();

        $bodyFont = $fontOptions[$settings['body_font']]['css'] ?? $fontOptions['system-ui']['css'];
        $menuFont = $fontOptions[$settings['menu_font']]['css'] ?? $fontOptions['system-ui']['css'];
        $headingFont = $fontOptions[$settings['heading_font']]['css'] ?? $fontOptions['system-ui']['css'];
        $buttonFont = $fontOptions[$settings['button_font']]['css'] ?? $fontOptions['system-ui']['css'];

        $menuBg = (string) $settings['menu_background_color'];
        $menuHover = $this->adjustHexColor($menuBg, 12);
        $menuActive = $this->adjustHexColor($menuBg, 22);
        $buttonBg = (string) $settings['primary_button_color'];
        $buttonHover = $this->adjustHexColor($buttonBg, -18);
        $darkMenuBg = (string) $settings['dark_menu_background_color'];
        $darkMenuHover = $this->adjustHexColor($darkMenuBg, 12);
        $darkMenuActive = $this->adjustHexColor($darkMenuBg, 22);
        $darkButtonBg = (string) $settings['dark_primary_button_color'];
        $darkButtonHover = $this->adjustHexColor($darkButtonBg, -18);

        $css = [];
        $css[] = ':root {';
        $css[] = '  --theme-app-background: ' . $settings['app_background_color'] . ';';
        $css[] = '  --theme-header-background: ' . $settings['header_background_color'] . ';';
        $css[] = '  --theme-menu-background: ' . $menuBg . ';';
        $css[] = '  --theme-menu-text: ' . $settings['menu_text_color'] . ';';
        $css[] = '  --theme-menu-hover-background: ' . $menuHover . ';';
        $css[] = '  --theme-menu-active-background: ' . $menuActive . ';';
        $css[] = '  --theme-heading-color: ' . $settings['heading_color'] . ';';
        $css[] = '  --theme-button-background: ' . $buttonBg . ';';
        $css[] = '  --theme-button-background-hover: ' . $buttonHover . ';';
        $css[] = '  --theme-button-text: ' . $settings['primary_button_text_color'] . ';';
        $css[] = '  --theme-dark-app-background: ' . $settings['dark_app_background_color'] . ';';
        $css[] = '  --theme-dark-header-background: ' . $settings['dark_header_background_color'] . ';';
        $css[] = '  --theme-dark-menu-background: ' . $darkMenuBg . ';';
        $css[] = '  --theme-dark-menu-text: ' . $settings['dark_menu_text_color'] . ';';
        $css[] = '  --theme-dark-menu-hover-background: ' . $darkMenuHover . ';';
        $css[] = '  --theme-dark-menu-active-background: ' . $darkMenuActive . ';';
        $css[] = '  --theme-dark-heading-color: ' . $settings['dark_heading_color'] . ';';
        $css[] = '  --theme-dark-button-background: ' . $darkButtonBg . ';';
        $css[] = '  --theme-dark-button-background-hover: ' . $darkButtonHover . ';';
        $css[] = '  --theme-dark-button-text: ' . $settings['dark_primary_button_text_color'] . ';';
        $css[] = '  --theme-body-font: ' . $bodyFont . ';';
        $css[] = '  --theme-menu-font: ' . $menuFont . ';';
        $css[] = '  --theme-heading-font: ' . $headingFont . ';';
        $css[] = '  --theme-button-font: ' . $buttonFont . ';';
        $css[] = '  --theme-body-font-size: ' . (int) $settings['body_font_size'] . 'px;';
        $css[] = '  --theme-menu-font-size: ' . (int) $settings['menu_font_size'] . 'px;';
        $css[] = '  --theme-heading-font-size: ' . (int) $settings['heading_font_size'] . 'px;';
        $css[] = '  --theme-button-font-size: ' . (int) $settings['button_font_size'] . 'px;';
        $css[] = '  --theme-button-radius: ' . (int) $settings['button_radius'] . 'px;';
        $css[] = '  --theme-logo-size: ' . (int) $settings['logo_size'] . 'px;';
        $css[] = '  --theme-surface-app: var(--theme-app-background);';
        $css[] = '  --theme-surface-header: var(--theme-header-background);';
        $css[] = '  --theme-surface-menu: var(--theme-menu-background);';
        $css[] = '  --theme-menu-foreground: var(--theme-menu-text);';
        $css[] = '  --theme-menu-hover-surface: var(--theme-menu-hover-background);';
        $css[] = '  --theme-menu-active-surface: var(--theme-menu-active-background);';
        $css[] = '  --theme-heading-foreground: var(--theme-heading-color);';
        $css[] = '}';
        $css[] = 'html.dark {';
        $css[] = '  --theme-surface-app: var(--theme-dark-app-background);';
        $css[] = '  --theme-surface-header: var(--theme-dark-header-background);';
        $css[] = '  --theme-surface-menu: var(--theme-dark-menu-background);';
        $css[] = '  --theme-menu-foreground: var(--theme-dark-menu-text);';
        $css[] = '  --theme-menu-hover-surface: var(--theme-dark-menu-hover-background);';
        $css[] = '  --theme-menu-active-surface: var(--theme-dark-menu-active-background);';
        $css[] = '  --theme-heading-foreground: var(--theme-dark-heading-color);';
        $css[] = '  --theme-button-background: var(--theme-dark-button-background);';
        $css[] = '  --theme-button-background-hover: var(--theme-dark-button-background-hover);';
        $css[] = '  --theme-button-text: var(--theme-dark-button-text);';
        $css[] = '}';
        $css[] = 'body { background: var(--theme-surface-app); font-family: var(--theme-body-font); font-size: var(--theme-body-font-size); }';
        $css[] = '.app-main, .app-content { background: var(--theme-surface-app); }';
        $css[] = '.app-header { background: var(--theme-surface-header) !important; }';
        $css[] = '.app-sidebar { background: var(--theme-surface-menu) !important; }';
        $css[] = '.nav-link, .nav-link span, .nav-icon, .nav-submenu-toggle, .nav-submenu .nav-link { font-family: var(--theme-menu-font); font-size: var(--theme-menu-font-size); }';
        $css[] = '.app-sidebar .nav-link, .app-sidebar .nav-link span, .app-sidebar .nav-icon, .app-sidebar .nav-submenu-toggle, .app-sidebar .nav-item > div { color: var(--theme-menu-foreground) !important; }';
        $css[] = '.app-sidebar .nav-link:hover, .app-sidebar .nav-submenu-toggle:hover { background: var(--theme-menu-hover-surface) !important; text-decoration: none; }';
        $css[] = '.app-sidebar .nav-link.active, .app-sidebar .nav-link.active-parent { background: var(--theme-menu-active-surface) !important; border-left-color: var(--theme-button-background) !important; }';
        $css[] = 'h1, h2, h3, h4, h5, h6 { font-family: var(--theme-heading-font); color: var(--theme-heading-foreground); }';
        $css[] = 'h1 { font-size: var(--theme-heading-font-size); }';
        $css[] = 'h2 { font-size: calc(var(--theme-heading-font-size) * 0.76); }';
        $css[] = 'h3 { font-size: calc(var(--theme-heading-font-size) * 0.62); }';
        $css[] = '.btn, button.btn, input[type="submit"].btn { font-family: var(--theme-button-font); font-size: var(--theme-button-font-size); border-radius: var(--theme-button-radius); }';
        $css[] = '.btn-primary { background: var(--theme-button-background); border-color: var(--theme-button-background); color: var(--theme-button-text); }';
        $css[] = '.btn-primary:hover:not(:disabled), .btn-primary:focus:not(:disabled) { background: var(--theme-button-background-hover); border-color: var(--theme-button-background-hover); color: var(--theme-button-text); }';

        return implode("\n", $css);
    }

    public function adjustHexColor(string $color, int $delta): string
    {
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            return $color;
        }

        $red = max(0, min(255, hexdec(substr($color, 1, 2)) + $delta));
        $green = max(0, min(255, hexdec(substr($color, 3, 2)) + $delta));
        $blue = max(0, min(255, hexdec(substr($color, 5, 2)) + $delta));

        return sprintf('#%02X%02X%02X', $red, $green, $blue);
    }

    private function normalizeSettings(array $decoded): array
    {
        $defaults = $this->getDefaultSettings();
        $templates = $this->getAvailableTemplates();
        $activeTemplate = trim((string) ($decoded['active_template'] ?? ''));
        if ($activeTemplate === '' || !isset($templates[$activeTemplate])) {
            $activeTemplate = (string) $defaults['active_template'];
        }

        return [
            'active_template' => $activeTemplate,
            'site_title' => $this->sanitizeText($decoded['site_title'] ?? '', (string) $defaults['site_title'], 60),
            'site_tagline' => $this->sanitizeText($decoded['site_tagline'] ?? '', (string) $defaults['site_tagline'], 120),
            'logo_path' => $this->normalizeLogoPath((string) ($decoded['logo_path'] ?? '')),
            'logo_size' => $this->sanitizePixelValue($decoded['logo_size'] ?? null, (int) $defaults['logo_size'], 24, 96),
            'sticky_header_enabled' => $this->sanitizeBoolean($decoded['sticky_header_enabled'] ?? null, (bool) $defaults['sticky_header_enabled']),
            'user_info' => $this->sanitizeBoolean($decoded['user_info'] ?? null, (bool) $defaults['user_info']),
            'header_right_menu' => $this->sanitizeBoolean($decoded['header_right_menu'] ?? null, (bool) $defaults['header_right_menu']),
            'dark_mode_toggle' => $this->sanitizeBoolean($decoded['dark_mode_toggle'] ?? null, (bool) $defaults['dark_mode_toggle']),
            'app_background_color' => $this->sanitizeHexColor($decoded['app_background_color'] ?? '', (string) $defaults['app_background_color']),
            'header_background_color' => $this->sanitizeHexColor($decoded['header_background_color'] ?? '', (string) $defaults['header_background_color']),
            'menu_background_color' => $this->sanitizeHexColor($decoded['menu_background_color'] ?? '', (string) $defaults['menu_background_color']),
            'menu_text_color' => $this->sanitizeHexColor($decoded['menu_text_color'] ?? '', (string) $defaults['menu_text_color']),
            'heading_color' => $this->sanitizeHexColor($decoded['heading_color'] ?? '', (string) $defaults['heading_color']),
            'primary_button_color' => $this->sanitizeHexColor($decoded['primary_button_color'] ?? '', (string) $defaults['primary_button_color']),
            'primary_button_text_color' => $this->sanitizeHexColor($decoded['primary_button_text_color'] ?? '', (string) $defaults['primary_button_text_color']),
            'dark_app_background_color' => $this->sanitizeHexColor($decoded['dark_app_background_color'] ?? '', (string) $defaults['dark_app_background_color']),
            'dark_header_background_color' => $this->sanitizeHexColor($decoded['dark_header_background_color'] ?? '', (string) $defaults['dark_header_background_color']),
            'dark_menu_background_color' => $this->sanitizeHexColor($decoded['dark_menu_background_color'] ?? '', (string) $defaults['dark_menu_background_color']),
            'dark_menu_text_color' => $this->sanitizeHexColor($decoded['dark_menu_text_color'] ?? '', (string) $defaults['dark_menu_text_color']),
            'dark_heading_color' => $this->sanitizeHexColor($decoded['dark_heading_color'] ?? '', (string) $defaults['dark_heading_color']),
            'dark_primary_button_color' => $this->sanitizeHexColor($decoded['dark_primary_button_color'] ?? '', (string) $defaults['dark_primary_button_color']),
            'dark_primary_button_text_color' => $this->sanitizeHexColor($decoded['dark_primary_button_text_color'] ?? '', (string) $defaults['dark_primary_button_text_color']),
            'body_font' => $this->sanitizeFontKey($decoded['body_font'] ?? '', (string) $defaults['body_font']),
            'body_font_size' => $this->sanitizePixelValue($decoded['body_font_size'] ?? null, (int) $defaults['body_font_size'], 13, 22),
            'menu_font' => $this->sanitizeFontKey($decoded['menu_font'] ?? '', (string) $defaults['menu_font']),
            'menu_font_size' => $this->sanitizePixelValue($decoded['menu_font_size'] ?? null, (int) $defaults['menu_font_size'], 12, 22),
            'heading_font' => $this->sanitizeFontKey($decoded['heading_font'] ?? '', (string) $defaults['heading_font']),
            'heading_font_size' => $this->sanitizePixelValue($decoded['heading_font_size'] ?? null, (int) $defaults['heading_font_size'], 22, 52),
            'button_font' => $this->sanitizeFontKey($decoded['button_font'] ?? '', (string) $defaults['button_font']),
            'button_font_size' => $this->sanitizePixelValue($decoded['button_font_size'] ?? null, (int) $defaults['button_font_size'], 12, 22),
            'button_radius' => $this->sanitizePixelValue($decoded['button_radius'] ?? null, (int) $defaults['button_radius'], 0, 32),
        ];
    }

    private function sanitizeHexColor(mixed $value, string $default): string
    {
        $color = strtoupper(trim((string) $value));

        return preg_match('/^#[0-9A-F]{6}$/', $color) ? $color : $default;
    }

    private function sanitizePixelValue(mixed $value, int $default, int $min, int $max): int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT);
        if ($number === false) {
            return $default;
        }

        return max($min, min($max, (int) $number));
    }

    private function sanitizeFontKey(mixed $value, string $default): string
    {
        $key = trim((string) $value);
        $options = $this->getFontFamilyOptions();

        return array_key_exists($key, $options) ? $key : $default;
    }

    private function sanitizeBoolean(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'off', 'no'], true)) {
            return false;
        }

        return $default;
    }

    private function sanitizeText(mixed $value, string $default, int $maxLength): string
    {
        $text = trim(strip_tags((string) $value));
        if ($text === '') {
            return '';
        }

        return mb_substr($text, 0, $maxLength, 'UTF-8');
    }

    private function stringifySettingValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
