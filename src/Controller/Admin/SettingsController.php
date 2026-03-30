<?php

namespace App\Controller\Admin;

use App\Service\AdminUserDirectoryService;
use App\Service\DynamicPageService;
use App\Service\PageDisplayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/parametrage')]
#[IsGranted('ROLE_ADMIN')]
final class SettingsController extends AbstractController
{
    #[Route('', name: 'admin_parametrage')]
    public function index(): Response
    {
        $settingsLinks = [
            ['page' => 'admin_modules', 'label' => 'Modules', 'desc' => 'Activez ou desactivez les modules disponibles', 'fallback_icon' => 'bi-grid-1x2', 'fallback_icon_library' => 'bootstrap'],
            ['page' => 'admin_pages', 'label' => 'Pages', 'desc' => 'Creez, activez et administrez les pages dynamiques avec TinyMCE', 'fallback_icon' => 'bi-window-stack', 'fallback_icon_library' => 'bootstrap', 'module' => DynamicPageService::PAGES_MODULE],
            ['page' => 'admin_theme', 'label' => 'Theme du site', 'desc' => 'Personnalisez les couleurs, polices et logo', 'fallback_icon' => 'bi-palette', 'fallback_icon_library' => 'bootstrap'],
            ['page' => 'admin_services', 'label' => 'Services', 'desc' => 'Ajoutez, renommez et recolorez les services relies aux profils', 'fallback_icon' => 'bi-diagram-3', 'fallback_icon_library' => 'bootstrap'],
            ['page' => 'admin_utilisateurs', 'label' => 'Utilisateurs', 'desc' => 'Gerez les comptes utilisateurs avec edition rapide, import et export', 'fallback_icon' => 'bi-people', 'fallback_icon_library' => 'bootstrap', 'module' => AdminUserDirectoryService::USERS_MODULE],
            ['page' => 'admin_page_icons', 'label' => 'Icones des pages', 'desc' => 'Assignez une icone a chaque page', 'fallback_icon' => 'bi-images', 'fallback_icon_library' => 'bootstrap', 'module' => PageDisplayService::PAGE_ICONS_MODULE],
            ['page' => 'admin_page_titles', 'label' => 'Libelles des pages', 'desc' => 'Renommez les titres des pages', 'fallback_icon' => 'bi-pencil-square', 'fallback_icon_library' => 'bootstrap', 'module' => PageDisplayService::PAGE_TITLES_MODULE],
            ['page' => 'admin_menus', 'label' => 'Menus', 'desc' => 'Configurez la navigation et les permissions associees', 'fallback_icon' => 'bi-folder', 'fallback_icon_library' => 'bootstrap', 'module' => \App\Service\MenuConfigService::MENUS_MODULE],
        ];

        return $this->render('admin/settings/index.html.twig', [
            'settingsLinks' => $settingsLinks,
        ]);
    }
}
