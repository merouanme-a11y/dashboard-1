<?php

namespace App\Controller\Admin;

use App\Service\ModuleService;
use App\Service\PageDisplayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/page-icons')]
#[IsGranted('ROLE_ADMIN')]
final class PageIconController extends AbstractController
{
    public function __construct(
        private PageDisplayService $pageDisplayService,
        private ModuleService $moduleService,
    ) {}

    #[Route('', name: 'admin_page_icons', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        if (!$this->moduleService->isActive(PageDisplayService::PAGE_ICONS_MODULE)) {
            $this->addFlash('danger', 'Le module des icones de pages est desactive.');

            return $this->redirectToRoute('admin_parametrage');
        }

        if ($request->isMethod('POST')) {
            $payload = $request->request->all();
            $this->pageDisplayService->saveIconConfiguration(
                (string) ($payload['library'] ?? 'bootstrap'),
                is_array($payload['icons'] ?? null) ? $payload['icons'] : [],
            );

            $this->addFlash('success', 'Parametrage des icones enregistre.');

            return $this->redirectToRoute('admin_page_icons');
        }

        $configurablePages = $this->pageDisplayService->getConfigurablePages();
        $iconLibraries = $this->pageDisplayService->getAvailableIconLibraries();
        $currentLibrary = $this->pageDisplayService->getActiveIconLibrary();
        $savedIcons = $this->pageDisplayService->getConfiguredIcons();
        $defaultsByPage = [];
        $availableGroups = [];

        foreach ($configurablePages as &$page) {
            $pagePath = (string) ($page['page_path'] ?? '');
            $page['page_name'] = $this->pageDisplayService->resolveNavigationLabel(
                $pagePath,
                (string) ($page['default_title'] ?? ''),
            );

            $group = trim((string) ($page['group'] ?? ''));
            if ($group !== '') {
                $availableGroups[$group] = true;
            }

            foreach (array_keys($iconLibraries) as $libraryKey) {
                $defaultsByPage[$pagePath][$libraryKey] = $this->pageDisplayService->getDefaultIconValue($pagePath, $libraryKey);
            }
        }
        unset($page);

        $availableGroups = array_keys($availableGroups);
        sort($availableGroups);

        return $this->render('admin/page_icons/index.html.twig', [
            'configurablePages' => $configurablePages,
            'iconLibraries' => $iconLibraries,
            'currentLibrary' => $currentLibrary,
            'savedIcons' => $savedIcons,
            'defaultsByPage' => $defaultsByPage,
            'availableGroups' => $availableGroups,
        ]);
    }
}
