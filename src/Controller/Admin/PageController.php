<?php

namespace App\Controller\Admin;

use App\Entity\Page;
use App\Entity\Utilisateur;
use App\Service\DynamicPageService;
use App\Service\ModuleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/pages')]
#[IsGranted('ROLE_ADMIN')]
final class PageController extends AbstractController
{
    public function __construct(
        private DynamicPageService $dynamicPageService,
        private ModuleService $moduleService,
    ) {}

    #[Route('', name: 'admin_pages', methods: ['GET'])]
    public function index(): Response
    {
        if ($redirect = $this->redirectIfModuleDisabled()) {
            return $redirect;
        }

        $pages = $this->dynamicPageService->listAll();
        $activePagesCount = 0;
        foreach ($pages as $page) {
            if ($page->isActive()) {
                ++$activePagesCount;
            }
        }

        return $this->render('admin/pages/index.html.twig', [
            'pages' => $pages,
            'activePagesCount' => $activePagesCount,
            'publicRouteName' => DynamicPageService::PUBLIC_ROUTE,
        ]);
    }

    #[Route('/builder', name: 'admin_pages_builder', methods: ['GET', 'POST'])]
    public function builder(Request $request): Response
    {
        if ($redirect = $this->redirectIfModuleDisabled()) {
            return $redirect;
        }

        return $this->handleBuilder($request);
    }

    #[Route('/{id}/edit', name: 'admin_pages_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Page $page, Request $request): Response
    {
        if ($redirect = $this->redirectIfModuleDisabled()) {
            return $redirect;
        }

        return $this->handleBuilder($request, $page);
    }

    #[Route('/{id}/toggle-status', name: 'admin_pages_toggle_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleStatus(Page $page, Request $request): Response
    {
        if ($redirect = $this->redirectIfModuleDisabled()) {
            return $redirect;
        }

        if (!$this->isCsrfTokenValid('toggle_page_' . $page->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de securite invalide.');

            return $this->redirectToRoute('admin_pages');
        }

        $isActive = filter_var($request->request->get('is_active', '0'), FILTER_VALIDATE_BOOL);
        $this->dynamicPageService->setPageActive($page, $isActive);

        $this->addFlash('success', sprintf(
            'La page "%s" est maintenant %s.',
            (string) $page->getTitle(),
            $isActive ? 'active' : 'inactive',
        ));

        return $this->redirectToRoute('admin_pages');
    }

    #[Route('/{id}/duplicate', name: 'admin_pages_duplicate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function duplicate(Page $page, Request $request): Response
    {
        if ($redirect = $this->redirectIfModuleDisabled()) {
            return $redirect;
        }

        if (!$this->isCsrfTokenValid('duplicate_page_' . $page->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de securite invalide.');

            return $this->redirectToRoute('admin_pages');
        }

        $author = $this->getUser();
        $duplicate = $this->dynamicPageService->duplicatePage(
            $page,
            $author instanceof Utilisateur ? $author : null,
        );

        $this->addFlash('success', sprintf('La copie "%s" a ete creee.', (string) $duplicate->getTitle()));

        return $this->redirectToRoute('admin_pages_edit', ['id' => $duplicate->getId()]);
    }

    #[Route('/{id}/delete', name: 'admin_pages_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Page $page, Request $request): Response
    {
        if ($redirect = $this->redirectIfModuleDisabled()) {
            return $redirect;
        }

        if (!$this->isCsrfTokenValid('delete_page_' . $page->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de securite invalide.');

            return $this->redirectToRoute('admin_pages');
        }

        $pageTitle = (string) $page->getTitle();
        $this->dynamicPageService->deletePage($page);

        $this->addFlash('success', sprintf('La page "%s" a ete supprimee.', $pageTitle));

        return $this->redirectToRoute('admin_pages');
    }

    #[Route('/upload', name: 'admin_pages_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        if (!$this->moduleService->isActive(DynamicPageService::PAGES_MODULE)) {
            return new JsonResponse(['_error' => 'Le module Pages est desactive.'], Response::HTTP_FORBIDDEN);
        }

        if (!$this->isCsrfTokenValid('admin_pages_upload', (string) $request->request->get('_token'))) {
            return new JsonResponse(['_error' => 'Jeton de securite invalide.'], Response::HTTP_FORBIDDEN);
        }

        $file = $request->files->get('file');
        if (!$file) {
            return new JsonResponse(['_error' => 'Aucun fichier recu.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            return new JsonResponse($this->dynamicPageService->uploadEditorAsset(
                $file,
                (string) $request->request->get('kind', 'file'),
            ));
        } catch (\Throwable $exception) {
            return new JsonResponse(['_error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    private function handleBuilder(Request $request, ?Page $page = null): Response
    {
        $isEdit = $page instanceof Page;
        if ($isEdit) {
            $this->dynamicPageService->repairStoredContent($page);
        }

        $formData = [
            'title' => $isEdit ? (string) $page->getTitle() : '',
            'keywords' => $isEdit ? (string) ($page->getKeywords() ?? '') : '',
            'content' => $isEdit ? (string) $page->getContent() : '',
            'is_active' => $isEdit ? $page->isActive() : true,
            'show_title' => $isEdit ? $page->isShowTitle() : true,
            'show_breadcrumb' => $isEdit ? $page->isShowBreadcrumb() : true,
        ];

        if ($request->isMethod('POST')) {
            $formData = [
                'title' => trim((string) $request->request->get('title', '')),
                'keywords' => trim((string) $request->request->get('keywords', '')),
                'content' => (string) $request->request->get('content', ''),
                'is_active' => filter_var($request->request->get('is_active', '0'), FILTER_VALIDATE_BOOL),
                'show_title' => filter_var($request->request->get('show_title', '0'), FILTER_VALIDATE_BOOL),
                'show_breadcrumb' => filter_var($request->request->get('show_breadcrumb', '0'), FILTER_VALIDATE_BOOL),
            ];

            if (!$this->isCsrfTokenValid('save_page', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Jeton de securite invalide.');
            } else {
                try {
                    if ($isEdit) {
                        $page = $this->dynamicPageService->updatePage(
                            $page,
                            $formData['title'],
                            $formData['content'],
                            $formData['keywords'],
                            $formData['is_active'],
                            $formData['show_title'],
                            $formData['show_breadcrumb'],
                        );
                        $this->addFlash('success', 'La page a ete mise a jour.');
                    } else {
                        $author = $this->getUser();
                        $page = $this->dynamicPageService->createPage(
                            $formData['title'],
                            $formData['content'],
                            $formData['keywords'],
                            $author instanceof Utilisateur ? $author : null,
                            $formData['is_active'],
                            $formData['show_title'],
                            $formData['show_breadcrumb'],
                        );
                        $this->addFlash('success', 'La page a ete creee.');
                    }

                    return $this->redirectToRoute('admin_pages_edit', ['id' => $page->getId()]);
                } catch (\Throwable $exception) {
                    $this->addFlash('danger', $exception->getMessage());
                }
            }
        }

        return $this->render('admin/pages/builder.html.twig', [
            'pageEntity' => $page,
            'formData' => $formData,
            'isEdit' => $page instanceof Page,
            'publicUrl' => $page instanceof Page && $page->isActive() ? $this->dynamicPageService->getPublicUrl($page) : '',
            'managedPagePath' => $page instanceof Page ? $this->dynamicPageService->buildManagedPagePath($page) : '',
        ]);
    }

    private function redirectIfModuleDisabled(): ?Response
    {
        if ($this->moduleService->isActive(DynamicPageService::PAGES_MODULE)) {
            return null;
        }

        $this->addFlash('danger', 'Le module Pages est desactive.');

        return $this->redirectToRoute('admin_parametrage');
    }
}
