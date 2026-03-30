<?php

namespace App\Controller\Admin;

use App\Repository\ModuleRepository;
use App\Service\ModuleService;
use App\Service\PageDisplayService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/modules')]
#[IsGranted('ROLE_ADMIN')]
final class ModuleController extends AbstractController
{
    public function __construct(
        private ModuleRepository $moduleRepository,
        private ModuleService $moduleService,
        private PageDisplayService $pageDisplayService,
        private EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'admin_modules')]
    public function index(): Response
    {
        $modules = $this->moduleService->getAllModules();

        return $this->render('admin/modules/index.html.twig', [
            'modules' => $modules,
        ]);
    }

    #[Route('/{id}/toggle', name: 'admin_modules_toggle', methods: ['POST'])]
    public function toggle(int $id): JsonResponse
    {
        $module = $this->moduleRepository->find($id);
        if (!$module) {
            return new JsonResponse(['error' => 'Module not found'], 404);
        }

        $module->setIsActive(!$module->isActive());
        $this->em->flush();

        $this->moduleService->invalidateCache();
        $this->pageDisplayService->invalidateConfigurablePagesCache();

        return new JsonResponse([
            'success' => true,
            'isActive' => $module->isActive(),
        ]);
    }
}
