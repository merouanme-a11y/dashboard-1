<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Service\ModuleService;
use App\Service\QuittancementGeneratorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/quittancement-generator')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class QuittancementGeneratorController extends AbstractController
{
    public function __construct(
        private QuittancementGeneratorService $quittancementGeneratorService,
        private ModuleService $moduleService,
    ) {}

    #[Route('', name: 'app_quittancement_generator', methods: ['GET', 'POST'], defaults: ['_managed_page_path' => 'app_quittancement_generator'])]
    public function index(Request $request): Response
    {
        $this->getRequiredUser();

        $module = $this->quittancementGeneratorService->ensureModuleExists();
        $this->moduleService->invalidateCache();

        if (!$module->isActive()) {
            throw $this->createNotFoundException('Module indisponible.');
        }

        $pageData = $this->quittancementGeneratorService->buildPageData(
            $request->isMethod('POST') ? $request->request->all() : $request->query->all()
        );

        return $this->render('quittancement_generator/index.html.twig', $pageData);
    }

    private function getRequiredUser(): Utilisateur
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
