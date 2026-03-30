<?php
declare(strict_types=1);

/**
 * Layout global pour toutes les pages authentifiees.
 * A inclure au debut de chaque page avec :
 * require_once __DIR__ . '/../Modules/layout.php';
 * layout_header("Titre de la page", "icon");
 * ... contenu ...
 * layout_footer();
 */

function layout_header(string $pageTitle = '', string $icon = 'bi-file-earmark', array $options = []): void
{
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }

    $user = currentUser();
    $darkmode = isDarkModeEnabled();
    $userId = currentUserId();
    $pageTitle = resolveConfiguredPageRuntimeTitle('', $pageTitle);
    $themeSettings = loadThemeSettings();
    $siteTitle = trim((string) ($themeSettings['site_title'] ?? ''));
    $siteTagline = (string) ($themeSettings['site_tagline'] ?? '');
    $activeStylesheet = getActiveSiteTemplateStylesheet();
    $googleFontUrls = getThemeGoogleFontsStylesheets($themeSettings);
    $pageTitleIconStylesheets = getPageTitleIconLibraryStylesheets([], !empty($options['load_all_page_icon_libraries']));
    $themeCssOverrides = buildThemeCssOverrides($themeSettings);
    $logoPath = trim((string) ($themeSettings['logo_path'] ?? ''));
    $logoUrl = $logoPath !== '' ? appUrl(ltrim($logoPath, '/')) : '';
    $logoSize = max(24, min(96, (int) ($themeSettings['logo_size'] ?? 40)));
    $stickyHeaderEnabled = !empty($themeSettings['sticky_header_enabled']);
    $pageTitleIconHtml = $pageTitle !== '' ? renderConfiguredPageTitleIconHtml('', $icon) : '';
    $headerRightMenuItems = getMenuConfigForCurrentUser('header_right');
    $showUserInfo = isHeaderDisplayElementEnabled('user-info');
    $showHeaderRightMenu = isHeaderDisplayElementEnabled('header-right-menu');
    $showDarkModeToggle = isHeaderDisplayElementEnabled('dark-mode-toggle');
    $brandAriaLabel = $siteTitle !== '' ? $siteTitle : 'Accueil';
    $hasBrandCopy = $siteTitle !== '' || $siteTagline !== '';
    $userPhotoUrl = buildUserProfilePhotoUrl((string) ($user['photo'] ?? ''));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?><?= $siteTitle !== '' ? ' - ' . htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') : '' ?></title>

    <?php if ($googleFontUrls !== []): ?>
    <?php foreach ($googleFontUrls as $googleFontUrl): ?>
    <?php if ($googleFontUrl === $googleFontUrls[0]): ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php endif; ?>
    <link href="<?= htmlspecialchars($googleFontUrl, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endforeach; ?>
    <?php endif; ?>
    <link href="<?= htmlspecialchars(appUrl($activeStylesheet), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <?php foreach ($pageTitleIconStylesheets as $iconStylesheet): ?>
    <?php if ($iconStylesheet !== 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css'): ?>
    <link href="<?= htmlspecialchars($iconStylesheet, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php endforeach; ?>
    <style id="site-theme-overrides">
<?= $themeCssOverrides ?>
    </style>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        (function () {
            const isDark = localStorage.getItem('darkmode') === 'true';
            if (isDark) {
                document.documentElement.classList.add('dark');
            }
        })();

        function toggleDarkMode() {
            const html = document.documentElement;
            const isDark = html.classList.toggle('dark');
            localStorage.setItem('darkmode', isDark ? 'true' : 'false');
        }
    </script>
</head>
<body>
    <div class="app-container<?= $stickyHeaderEnabled ? ' has-sticky-header' : '' ?>">
        <style>
            .nav-parent-row {
                display: flex;
                align-items: stretch;
            }

            .site-brand-logo {
                width: calc(var(--theme-logo-size, <?= $logoSize ?>px) + 4px);
                height: calc(var(--theme-logo-size, <?= $logoSize ?>px) + 4px);
            }

            .site-brand-logo img {
                width: 100%;
                height: 100%;
                object-fit: contain;
                display: block;
            }

            .page-title-heading {
                display: flex;
                align-items: center;
                gap: 0.65rem;
                flex-wrap: wrap;
            }

            .page-title-icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 1.3em;
                line-height: 1;
            }

            .configured-page-icon,
            .configured-page-icon > i,
            .configured-page-icon .material-symbols-outlined,
            .page-title-icon > i,
            .page-title-icon .material-symbols-outlined {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                line-height: 1;
            }

            .page-title-icon-bootstrap,
            .page-title-icon-fontawesome,
            .page-title-icon-remixicon,
            .page-title-icon-boxicons,
            .page-title-icon-tabler,
            .page-title-icon-material {
                font-size: 0.92em;
            }

            .page-title-icon-material .material-symbols-outlined,
            .configured-page-icon-material .material-symbols-outlined {
                font-size: 1.08em;
                font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            }

            .nav-link-parent {
                flex: 1;
            }

            .nav-link.active-parent {
                color: var(--primary);
                background: rgba(59, 130, 246, 0.08);
                border-color: rgba(59, 130, 246, 0.18);
                font-weight: 600;
            }

            .nav-submenu-toggle {
                width: 48px;
                border: 0;
                border-left: 1px solid var(--border);
                background: transparent;
                color: var(--text-muted);
                cursor: pointer;
                transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
            }

            .nav-submenu-toggle:hover {
                background: var(--bg-tertiary);
                color: var(--primary);
            }

            .nav-item-parent.is-open .nav-submenu-toggle {
                color: var(--primary);
            }

            .nav-submenu-chevron {
                display: inline-block;
                transition: transform 0.2s ease;
            }

            .nav-item-parent.is-open .nav-submenu-chevron {
                transform: rotate(180deg);
            }

            .nav-submenu {
                list-style: none;
                margin: 0;
                padding: 0;
                display: none;
                background: rgba(148, 163, 184, 0.06);
            }

            .nav-item-parent.is-open > .nav-submenu {
                display: block;
            }

            .nav-submenu .nav-link {
                padding-left: 3.5rem;
                font-size: 0.94rem;
            }

            .nav-submenu .nav-icon {
                font-size: 0.95rem;
                width: 16px;
            }

            @media (hover: hover) and (min-width: 769px) {
                .app-container:not(.is-sidebar-collapsed) .nav-item-parent > .nav-submenu {
                    position: absolute;
                    top: calc(100% + 0.35rem);
                    left: 0.75rem;
                    right: 0;
                    margin-top: 0;
                    padding: 0.35rem;
                    background: var(--bg-secondary);
                    border: 1px solid var(--border);
                    border-radius: 14px;
                    box-shadow: 0 16px 40px var(--shadow-lg);
                    z-index: 60;
                    overflow: hidden;
                }

                .app-container:not(.is-sidebar-collapsed) .nav-item-parent > .nav-submenu .nav-link {
                    margin-left: 0;
                    padding-left: 1rem;
                    justify-content: flex-start;
                    gap: 0.875rem;
                }
            }
        </style>

        <header class="app-header">
            <div class="header-left-spacer" aria-hidden="true"></div>
            <div class="header-right">
                <?php if ($showUserInfo): ?>
                <a class="user-info user-info-link" href="<?= htmlspecialchars(appUrl('profile.php'), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="user-avatar<?= $userPhotoUrl !== '' ? ' has-photo' : '' ?>">
                        <?php if ($userPhotoUrl !== ''): ?>
                            <img src="<?= htmlspecialchars($userPhotoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Photo de profil de <?= htmlspecialchars((string) ($user['prenom'] ?? $userId), ENT_QUOTES, 'UTF-8') ?>">
                        <?php else: ?>
                            <?= strtoupper(substr($userId, 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-identity">
                        <div class="user-name">
                            <?= htmlspecialchars($user['prenom'] ?? $userId, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>
                </a>
                <?php endif; ?>

                <?php if ($showHeaderRightMenu): ?>
                <?= renderHeaderRightMenu($headerRightMenuItems, (string) ($_SERVER['PHP_SELF'] ?? '')) ?>
                <?php endif; ?>

                <?php if ($showDarkModeToggle): ?>
                <button class="dark-mode-toggle" onclick="toggleDarkMode()" title="Basculer le dark mode" aria-label="Basculer le dark mode">
                    <i id="theme-icon" class="bi <?= $darkmode ? 'bi-sun-fill' : 'bi-moon-stars-fill' ?>" aria-hidden="true"></i>
                </button>
                <?php endif; ?>
            </div>
        </header>

        <aside class="app-sidebar">
            <div class="sidebar-shell">
                <a class="sidebar-brand<?= $hasBrandCopy ? '' : ' is-logo-only' ?>" href="<?= htmlspecialchars(appUrl('index.php'), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($brandAriaLabel, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="site-brand-logo<?= $logoUrl !== '' ? ' has-image-logo' : '' ?>">
                        <?php if ($logoUrl !== ''): ?>
                            <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Logo du site">
                        <?php else: ?>
                            <i class="bi bi-grid-1x2-fill" aria-hidden="true"></i>
                        <?php endif; ?>
                    </div>
                    <?php if ($hasBrandCopy): ?>
                    <div class="sidebar-brand-copy">
                        <?php if ($siteTitle !== ''): ?>
                            <span class="sidebar-brand-title"><?= htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <?php if ($siteTagline !== ''): ?>
                            <small class="sidebar-brand-tagline"><?= htmlspecialchars($siteTagline, ENT_QUOTES, 'UTF-8') ?></small>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </a>

                <nav class="sidebar-nav">
                    <ul class="nav-menu">
                        <?php
                        $menuItems = getMenuConfigForCurrentUser();
                        echo renderSidebarMenuTree($menuItems, (string) ($_SERVER['PHP_SELF'] ?? ''));
                        ?>
                    </ul>
                </nav>

                <div class="sidebar-footer">
                    <a href="<?= htmlspecialchars(appUrl('logout.php'), ENT_QUOTES, 'UTF-8') ?>" class="nav-link sidebar-footer-link sidebar-logout-link" title="Déconnexion">
                        <span class="nav-icon"><i class="bi bi-box-arrow-right" aria-hidden="true"></i></span>
                        <span class="nav-label">Déconnexion</span>
                    </a>
                    <button type="button" class="sidebar-collapse-toggle" id="sidebarCollapseToggle" aria-pressed="false" aria-label="Réduire le menu" title="Réduire le menu">
                        <span class="sidebar-collapse-toggle-icon"><i class="bi bi-layout-sidebar-inset" aria-hidden="true"></i></span>
                        <span class="sidebar-collapse-toggle-label">Réduire le menu</span>
                    </button>
                </div>
            </div>
        </aside>

        <main class="app-main">
            <div class="app-content">
                <?php if ($pageTitle): ?>
                    <div style="margin-bottom: 2rem;">
                        <h1 class="page-title-heading"><?= $pageTitleIconHtml ?><span><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></span></h1>
                    </div>
                <?php endif; ?>
<?php
}

function layout_footer(): void
{
?>
            </div>
        </main>
    </div>

    <div id="image-lightbox" class="image-lightbox" aria-hidden="true">
        <button type="button" class="image-lightbox-close" id="image-lightbox-close" aria-label="Fermer">&times;</button>
        <img id="image-lightbox-img" src="" alt="Aperçu image">
    </div>

    <script>
        function showAlert(message, type = 'info') {
            const alertClass = `alert alert-${type}`;
            const alertHTML = `
                <div class="${alertClass}">
                    <span style="font-weight: 600;">${escapeHtml(message)}</span>
                </div>
            `;

            const alertContainer = document.querySelector('.app-content');
            if (alertContainer) {
                const alert = document.createElement('div');
                alert.innerHTML = alertHTML;
                alertContainer.insertBefore(alert.firstElementChild, alertContainer.firstChild);

                setTimeout(() => {
                    alert.firstElementChild.remove();
                }, 5000);
            }
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };

            return text.replace(/[&<>"']/g, (character) => map[character]);
        }

        (function initImageLightbox() {
            const overlay = document.getElementById('image-lightbox');
            const overlayImg = document.getElementById('image-lightbox-img');
            const closeBtn = document.getElementById('image-lightbox-close');
            const content = document.querySelector('.app-content');

            if (!overlay || !overlayImg || !closeBtn || !content) {
                return;
            }

            function closeLightbox() {
                overlay.classList.remove('is-open');
                overlay.setAttribute('aria-hidden', 'true');
                overlayImg.removeAttribute('src');
                document.body.classList.remove('lightbox-open');
            }

            content.addEventListener('click', function (event) {
                const target = event.target;
                if (!(target instanceof HTMLImageElement)) {
                    return;
                }

                if (target.closest('.user-avatar')) {
                    return;
                }

                const src = target.getAttribute('src');
                if (!src) {
                    return;
                }

                overlayImg.src = src;
                overlay.classList.add('is-open');
                overlay.setAttribute('aria-hidden', 'false');
                document.body.classList.add('lightbox-open');
            });

            closeBtn.addEventListener('click', closeLightbox);

            overlay.addEventListener('click', function (event) {
                if (event.target === overlay) {
                    closeLightbox();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && overlay.classList.contains('is-open')) {
                    closeLightbox();
                }
            });
        })();

        (function initDashboardHeader() {
            const toggleBtn = document.querySelector('.dark-mode-toggle');
            const icon = document.getElementById('theme-icon');
            const headerMenuItems = Array.from(document.querySelectorAll('.header-admin-item-parent'));
            const headerMenuToggles = Array.from(document.querySelectorAll('.header-admin-toggle'));
            const canHover = window.matchMedia('(hover: hover)').matches;

            function updateIcon() {
                if (!icon) {
                    return;
                }

                const isDark = document.documentElement.classList.contains('dark');
                icon.className = `bi ${isDark ? 'bi-sun-fill' : 'bi-moon-stars-fill'}`;
            }

            function closeHeaderMenus(exceptItem = null) {
                headerMenuItems.forEach(function (item) {
                    const shouldStayOpen = !!exceptItem && item === exceptItem;
                    item.classList.toggle('is-open', shouldStayOpen);
                    const button = item.querySelector('.header-admin-toggle');
                    if (button) {
                        button.setAttribute('aria-expanded', shouldStayOpen ? 'true' : 'false');
                    }
                });
            }

            if (toggleBtn) {
                toggleBtn.addEventListener('click', function () {
                    setTimeout(updateIcon, 0);
                });
            }

            headerMenuToggles.forEach(function (button) {
                button.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    const parentItem = button.closest('.header-admin-item-parent');
                    const willOpen = parentItem && !parentItem.classList.contains('is-open');
                    closeHeaderMenus(willOpen ? parentItem : null);
                });
            });

            if (canHover) {
                headerMenuItems.forEach(function (item) {
                    item.addEventListener('mouseenter', function () {
                        closeHeaderMenus(item);
                    });

                    item.addEventListener('mouseleave', function () {
                        closeHeaderMenus();
                    });
                });
            }

            document.addEventListener('click', function (event) {
                if (!(event.target instanceof Element)) {
                    return;
                }

                if (!event.target.closest('.header-admin-item-parent')) {
                    closeHeaderMenus();
                }
            });

            updateIcon();
        })();

        (function initSidebarLayout() {
            const storageKey = 'dashboard-sidebar-collapsed-v1';
            const container = document.querySelector('.app-container');
            const sidebar = document.querySelector('.app-sidebar');
            const collapseButton = document.getElementById('sidebarCollapseToggle');
            const navParents = Array.from(document.querySelectorAll('.nav-item-parent'));
            const submenuToggles = Array.from(document.querySelectorAll('.nav-submenu-toggle'));
            const canHover = window.matchMedia('(hover: hover)').matches;
            const mobileMedia = window.matchMedia('(max-width: 768px)');

            if (!container) {
                return;
            }

            function syncCollapseButton(collapsed) {
                if (!collapseButton) {
                    return;
                }

                const label = collapsed ? 'Agrandir le menu' : 'Réduire le menu';
                const labelNode = collapseButton.querySelector('.sidebar-collapse-toggle-label');
                const iconNode = collapseButton.querySelector('.bi');

                collapseButton.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
                collapseButton.setAttribute('aria-label', label);
                collapseButton.title = label;

                if (labelNode) {
                    labelNode.textContent = label;
                }

                if (iconNode) {
                    iconNode.className = `bi ${collapsed ? 'bi-layout-sidebar' : 'bi-layout-sidebar-inset'}`;
                }
            }

            function closeSidebarSubmenus(exceptItem = null) {
                navParents.forEach(function (item) {
                    const shouldStayOpen = !!exceptItem && item === exceptItem;
                    item.classList.toggle('is-open', shouldStayOpen);
                    const button = item.querySelector('.nav-submenu-toggle');
                    if (button) {
                        button.setAttribute('aria-expanded', shouldStayOpen ? 'true' : 'false');
                    }
                });
            }

            function setCollapsed(collapsed, persist) {
                container.classList.toggle('is-sidebar-collapsed', collapsed);
                container.setAttribute('data-sidebar-collapsed', collapsed ? 'true' : 'false');
                syncCollapseButton(collapsed);
                closeSidebarSubmenus();

                if (persist) {
                    localStorage.setItem(storageKey, collapsed ? 'true' : 'false');
                }
            }

            const savedState = localStorage.getItem(storageKey);
            const initialCollapsed = savedState === null ? mobileMedia.matches : savedState === 'true';
            setCollapsed(initialCollapsed, false);

            if (collapseButton) {
                collapseButton.addEventListener('click', function () {
                    const nextState = !container.classList.contains('is-sidebar-collapsed');
                    setCollapsed(nextState, true);
                });
            }

            if (canHover) {
                navParents.forEach(function (item) {
                    item.addEventListener('mouseenter', function () {
                        closeSidebarSubmenus(item);
                    });

                    item.addEventListener('mouseleave', function () {
                        closeSidebarSubmenus();
                    });
                });
            }

            submenuToggles.forEach(function (button) {
                button.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();

                    const navItem = button.closest('.nav-item-parent');
                    if (!navItem) {
                        return;
                    }

                    if (canHover) {
                        closeSidebarSubmenus(navItem);
                        return;
                    }

                    const willOpen = !navItem.classList.contains('is-open');
                    closeSidebarSubmenus(willOpen ? navItem : null);
                });
            });

            document.addEventListener('click', function (event) {
                if (!(event.target instanceof Element)) {
                    return;
                }

                if (sidebar && !event.target.closest('.app-sidebar')) {
                    closeSidebarSubmenus();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeSidebarSubmenus();
                }
            });

            if (typeof mobileMedia.addEventListener === 'function') {
                mobileMedia.addEventListener('change', function (event) {
                    if (localStorage.getItem(storageKey) === null) {
                        setCollapsed(event.matches, false);
                    }
                });
            }
        })();
    </script>
</body>
</html>
<?php
}
