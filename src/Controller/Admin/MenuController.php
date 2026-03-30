<?php

namespace App\Controller\Admin;

use App\Service\MenuConfigService;
use App\Service\ModuleService;
use App\Service\PagePermissionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/menus')]
#[IsGranted('ROLE_ADMIN')]
final class MenuController extends AbstractController
{
    public function __construct(
        private MenuConfigService $menuConfigService,
        private PagePermissionService $pagePermissionService,
        private ModuleService $moduleService,
    ) {}

    #[Route('', name: 'admin_menus', methods: ['GET'])]
    public function index(): Response
    {
        if (!$this->moduleService->isActive(MenuConfigService::MENUS_MODULE)) {
            $this->addFlash('danger', 'Le module des menus est desactive.');

            return $this->redirectToRoute('admin_parametrage');
        }

        return $this->render('admin/menus/index.html.twig', [
            'usersData' => $this->menuConfigService->getUsersData(),
            'profilesList' => $this->menuConfigService->getProfilesList(),
            'menuDefinitions' => $this->menuConfigService->getAvailableMenuDefinitions(),
        ]);
    }

    #[Route('/available-pages', name: 'menus_api_available_pages', methods: ['GET'])]
    public function availablePages(): JsonResponse
    {
        if (!$this->moduleService->isActive(MenuConfigService::MENUS_MODULE)) {
            return new JsonResponse(['_error' => 'Acces refuse'], 403);
        }

        return new JsonResponse($this->menuConfigService->getAvailablePages());
    }

    #[Route('/config', name: 'menus_api_get_config', methods: ['GET'])]
    public function getConfig(Request $request): JsonResponse
    {
        if (!$this->moduleService->isActive(MenuConfigService::MENUS_MODULE)) {
            return new JsonResponse(['_error' => 'Acces refuse'], 403);
        }

        $selectorType = (string) $request->query->get('type', '');
        $selectorValue = (string) $request->query->get('value', '');
        $menuType = (string) $request->query->get('menu_type', 'sidebar');

        return new JsonResponse($this->menuConfigService->resolveMenuConfigForSelector($selectorType, $selectorValue, $menuType));
    }

    #[Route('/config', name: 'menus_api_save_config', methods: ['POST'])]
    public function saveConfig(Request $request): JsonResponse
    {
        if (!$this->moduleService->isActive(MenuConfigService::MENUS_MODULE)) {
            return new JsonResponse(['_error' => 'Acces refuse'], 403);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || !is_array($payload['menu'] ?? null)) {
            return new JsonResponse(['_error' => 'Donnees invalides'], 400);
        }

        $error = '';
        $success = $this->menuConfigService->saveMenuConfigForSelector(
            (string) ($payload['type'] ?? ''),
            (string) ($payload['value'] ?? ''),
            $payload['menu'],
            $error,
            (string) ($payload['menu_type'] ?? 'sidebar'),
        );

        if (!$success) {
            return new JsonResponse(['_error' => $error !== '' ? $error : 'Impossible d enregistrer la configuration'], 400);
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route('/config/reset', name: 'menus_api_reset_config', methods: ['POST'])]
    public function resetConfig(Request $request): JsonResponse
    {
        if (!$this->moduleService->isActive(MenuConfigService::MENUS_MODULE)) {
            return new JsonResponse(['_error' => 'Acces refuse'], 403);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['_error' => 'Donnees invalides'], 400);
        }

        $error = '';
        $success = $this->menuConfigService->resetMenuConfigForSelector(
            (string) ($payload['type'] ?? ''),
            (string) ($payload['value'] ?? ''),
            $error,
            (string) ($payload['menu_type'] ?? 'sidebar'),
        );

        if (!$success) {
            return new JsonResponse(['_error' => $error !== '' ? $error : 'Impossible de reinitialiser la configuration'], 400);
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route('/permissions', name: 'menus_api_permissions', methods: ['GET'])]
    public function permissions(Request $request): JsonResponse
    {
        if (!$this->moduleService->isActive(MenuConfigService::MENUS_MODULE)) {
            return new JsonResponse(['_error' => 'Acces refuse'], 403);
        }

        $selectorType = (string) $request->query->get('type', '');
        $selectorValue = (string) $request->query->get('value', '');
        if (trim($selectorValue) === '') {
            return new JsonResponse(['_error' => 'Selection manquante'], 400);
        }

        return new JsonResponse([
            'permissions' => $this->pagePermissionService->getPermissionsForSelector($selectorType, $selectorValue),
        ]);
    }

    #[Route('/permissions', name: 'menus_api_update_permission', methods: ['POST'])]
    public function updatePermission(Request $request): JsonResponse
    {
        if (!$this->moduleService->isActive(MenuConfigService::MENUS_MODULE)) {
            return new JsonResponse(['_error' => 'Acces refuse'], 403);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['_error' => 'Donnees invalides'], 400);
        }

        $success = $this->pagePermissionService->updatePermissionForSelector(
            (string) ($payload['type'] ?? ''),
            (string) ($payload['value'] ?? ''),
            (string) ($payload['page_path'] ?? ''),
            (bool) ($payload['is_allowed'] ?? false),
        );

        if (!$success) {
            return new JsonResponse(['_error' => 'Erreur lors de la mise a jour'], 400);
        }

        return new JsonResponse(['success' => true]);
    }
}
