<?php

namespace App\Controller;

use App\Service\PageDisplayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    public function __construct(private PageDisplayService $pageDisplayService) {}

    #[Route('/', name: 'app_dashboard')]
    public function index(): Response
    {
        return $this->render('dashboard/index.html.twig', [
            'pageTitle' => $this->pageDisplayService->resolveNavigationLabel('app_dashboard', 'Accueil'),
            'pageIconHtml' => $this->pageDisplayService->renderNavigationIcon('app_dashboard', 'bi-house', 'bootstrap'),
        ]);
    }
}
