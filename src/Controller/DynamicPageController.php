<?php

namespace App\Controller;

use App\Entity\Page;
use App\Service\DynamicPageService;
use App\Service\ModuleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DynamicPageController extends AbstractController
{
    public function __construct(
        private DynamicPageService $dynamicPageService,
        private ModuleService $moduleService,
    ) {}

    #[Route('/pages/{slug}', name: 'app_dynamic_page_show', methods: ['GET'])]
    public function show(string $slug, Request $request): Response
    {
        if (!$this->moduleService->isActive(DynamicPageService::PAGES_MODULE)) {
            throw $this->createNotFoundException();
        }

        $page = $request->attributes->get('_dynamic_page');
        if (!$page instanceof Page) {
            $page = $this->dynamicPageService->findActiveBySlug($slug);
        }

        if (!$page instanceof Page || !$page->isActive()) {
            throw $this->createNotFoundException('Page introuvable.');
        }

        $this->dynamicPageService->repairStoredContent($page);

        return $this->render('pages/show.html.twig', [
            'pageEntity' => $page,
            'managedPagePath' => $this->dynamicPageService->buildManagedPagePath($page),
        ]);
    }
}
