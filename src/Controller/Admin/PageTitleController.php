<?php

namespace App\Controller\Admin;

use App\Service\ModuleService;
use App\Service\PageDisplayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/page-titles')]
#[IsGranted('ROLE_ADMIN')]
final class PageTitleController extends AbstractController
{
    public function __construct(
        private PageDisplayService $pageDisplayService,
        private ModuleService $moduleService,
    ) {}

    #[Route('', name: 'admin_page_titles', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        if (!$this->moduleService->isActive(PageDisplayService::PAGE_TITLES_MODULE)) {
            $this->addFlash('danger', 'Le module des libelles de pages est desactive.');

            return $this->redirectToRoute('admin_parametrage');
        }

        if ($request->isMethod('POST')) {
            $payload = $request->request->all();
            $submittedPagePath = trim((string) ($payload['save_page_path'] ?? ''));
            $titles = is_array($payload['titles'] ?? null) ? $payload['titles'] : [];

            $this->pageDisplayService->saveTitleOverrides($titles);

            if ($submittedPagePath !== '') {
                $label = $this->pageDisplayService->resolveNavigationLabel($submittedPagePath, $submittedPagePath);
                $this->addFlash('success', 'Libelle enregistre pour ' . $label . '.');
            } else {
                $this->addFlash('success', 'Libelles des pages enregistres.');
            }

            return $this->redirectToRoute('admin_page_titles');
        }

        $configurablePages = $this->pageDisplayService->getConfigurablePages();
        $savedTitles = $this->pageDisplayService->getConfiguredTitleOverrides();
        $availableGroups = [];

        foreach ($configurablePages as &$page) {
            $group = trim((string) ($page['group'] ?? ''));
            if ($group !== '') {
                $availableGroups[$group] = true;
            }

            $pagePath = (string) ($page['page_path'] ?? '');
            $page['current_title'] = $this->pageDisplayService->resolveNavigationLabel(
                $pagePath,
                (string) ($page['default_title'] ?? ''),
            );
        }
        unset($page);

        $availableGroups = array_keys($availableGroups);
        sort($availableGroups);

        return $this->render('admin/page_titles/index.html.twig', [
            'configurablePages' => $configurablePages,
            'savedTitles' => $savedTitles,
            'availableGroups' => $availableGroups,
        ]);
    }
}
