<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Service\ModuleService;
use App\Service\QuittancementDatabaseTargetService;
use App\Service\QuittancementExecutionService;
use App\Service\QuittancementGeneratorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        private QuittancementExecutionService $quittancementExecutionService,
        private QuittancementDatabaseTargetService $quittancementDatabaseTargetService,
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

        $requestData = $request->isMethod('POST') ? $request->request->all() : $request->query->all();
        $pageData = $this->quittancementGeneratorService->buildPageData($requestData);
        $selectedTargetId = $this->quittancementDatabaseTargetService->resolveRequestedTargetId(
            (string) ($requestData['target_id'] ?? $requestData['target'] ?? '')
        );
        $databaseTargets = $this->quittancementDatabaseTargetService->getTargets();
        $selectedTarget = $this->quittancementDatabaseTargetService->getTargetById($selectedTargetId);
        $executionConfigured = $selectedTarget !== null && $this->quittancementExecutionService->isConfigured($selectedTarget);
        $executionTarget = $selectedTarget !== null
            ? array_merge(
                ['label' => (string) ($selectedTarget['label'] ?? '')],
                $this->quittancementExecutionService->getTargetSummary($selectedTarget),
            )
            : ['label' => '', 'host' => '', 'port' => 0, 'database' => ''];

        $response = $this->render('quittancement_generator/index.html.twig', array_merge($pageData, [
            'assetVersion' => $this->getModuleAssetVersion(),
            'databaseTargets' => $databaseTargets,
            'selectedTargetId' => $selectedTargetId,
            'canManageDatabaseTargets' => true,
            'executionTarget' => $executionTarget,
            'executionConfigured' => $executionConfigured,
        ]));

        return $this->applyPrivateCacheHeaders($response, 60);
    }

    #[Route('/execute', name: 'app_quittancement_generator_execute', methods: ['POST'], defaults: ['_managed_page_path' => 'app_quittancement_generator'])]
    public function execute(Request $request): JsonResponse
    {
        $this->getRequiredUser();
        $this->ensureModuleIsActive();

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->applyPrivateCacheHeaders(new JsonResponse(['message' => 'Requete invalide.'], 400), 0);
        }

        if (!$this->isCsrfTokenValid('quittancement_execute', (string) ($payload['_token'] ?? ''))) {
            return $this->applyPrivateCacheHeaders(new JsonResponse(['message' => 'Jeton de securite invalide. Rechargez la page puis reessayez.'], 403), 0);
        }

        $requestData = is_array($payload['requestData'] ?? null) ? $payload['requestData'] : [];
        $pageData = $this->quittancementGeneratorService->buildPageData($requestData);
        $selectedTargetId = $this->quittancementDatabaseTargetService->resolveRequestedTargetId(
            (string) ($payload['targetId'] ?? ($requestData['target_id'] ?? ''))
        );
        $selectedTarget = $this->quittancementDatabaseTargetService->getTargetById($selectedTargetId, true);

        if ($selectedTarget === null) {
            return $this->applyPrivateCacheHeaders(new JsonResponse([
                'message' => 'Aucune cible BDD n est configuree pour executer les requetes.',
            ], 400), 0);
        }

        try {
            $result = $this->quittancementExecutionService->executeSqlSectionsOnTarget($pageData['sqlSections'], $selectedTarget);
        } catch (\Throwable $exception) {
            return $this->applyPrivateCacheHeaders(new JsonResponse([
                'message' => $exception->getMessage(),
            ], 500), 0);
        }

        return $this->applyPrivateCacheHeaders(new JsonResponse([
            'message' => sprintf(
                'Les 3 blocs ont ete executes sur %s/%s.',
                (string) ($result['target']['host'] ?? ''),
                (string) ($result['target']['database'] ?? '')
            ),
            'executedBlocks' => $result['executedBlocks'],
            'targetId' => $selectedTargetId,
        ]), 0);
    }

    #[Route('/settings', name: 'app_quittancement_generator_settings', methods: ['GET', 'POST'], defaults: ['_managed_page_path' => 'app_quittancement_generator'])]
    public function settings(Request $request): JsonResponse
    {
        $this->getRequiredUser();
        $this->ensureModuleIsActive();

        if ($request->isMethod('GET')) {
            return $this->applyPrivateCacheHeaders(new JsonResponse([
                'targets' => $this->quittancementDatabaseTargetService->getTargets(),
                'selectedTargetId' => $this->quittancementDatabaseTargetService->resolveRequestedTargetId(
                    (string) $request->query->get('selectedTargetId', '')
                ),
            ]), 0);
        }

        if (!$this->isCsrfTokenValid('quittancement_database_settings', (string) $request->headers->get('X-CSRF-Token'))) {
            return $this->applyPrivateCacheHeaders(new JsonResponse([
                'message' => 'Jeton de securite invalide. Rechargez la page puis reessayez.',
            ], 403), 0);
        }

        try {
            $payload = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->applyPrivateCacheHeaders(new JsonResponse([
                'message' => 'Payload JSON invalide.',
            ], 400), 0);
        }

        if (!is_array($payload)) {
            return $this->applyPrivateCacheHeaders(new JsonResponse([
                'message' => 'Payload JSON invalide.',
            ], 400), 0);
        }

        $action = trim((string) ($payload['action'] ?? ''));
        $selectedTargetId = trim((string) ($payload['selectedTargetId'] ?? ''));

        try {
            switch ($action) {
                case 'add_target':
                    $targets = $this->quittancementDatabaseTargetService->addTarget(
                        (string) ($payload['label'] ?? ''),
                        (string) ($payload['host'] ?? ''),
                        (string) ($payload['port'] ?? ''),
                        (string) ($payload['database'] ?? ''),
                        (string) ($payload['username'] ?? ''),
                        (string) ($payload['password'] ?? ''),
                    );
                    $lastTarget = $targets[array_key_last($targets)] ?? null;
                    $selectedTargetId = is_array($lastTarget) ? (string) ($lastTarget['id'] ?? '') : '';
                    $message = 'Serveur BDD ajoute. Le mot de passe reste masque apres enregistrement.';
                    break;

                case 'delete_target':
                    $deletedTargetId = trim((string) ($payload['targetId'] ?? ''));
                    $targets = $this->quittancementDatabaseTargetService->removeTarget($deletedTargetId);
                    $selectedTargetId = $this->quittancementDatabaseTargetService->resolveRequestedTargetId(
                        $selectedTargetId === $deletedTargetId ? '' : $selectedTargetId
                    );
                    $message = 'Serveur BDD supprime.';
                    break;

                default:
                    throw new \InvalidArgumentException('Action de parametrage BDD inconnue.');
            }
        } catch (\Throwable $exception) {
            return $this->applyPrivateCacheHeaders(new JsonResponse([
                'message' => $exception->getMessage(),
            ], 400), 0);
        }

        return $this->applyPrivateCacheHeaders(new JsonResponse([
            'saved' => true,
            'message' => $message,
            'targets' => $targets,
            'selectedTargetId' => $selectedTargetId,
        ]), 0);
    }

    private function getRequiredUser(): Utilisateur
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function ensureModuleIsActive(): void
    {
        if (!$this->moduleService->isActive(QuittancementGeneratorService::MODULE_NAME)) {
            throw $this->createNotFoundException('Module indisponible.');
        }
    }

    private function applyPrivateCacheHeaders(Response $response, int $maxAge): Response
    {
        $response->setPrivate();
        $response->setVary(['Cookie', 'X-Requested-With'], false);
        $response->headers->addCacheControlDirective('must-revalidate', true);

        if ($maxAge > 0) {
            $response->setMaxAge($maxAge);

            return $response;
        }

        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-cache', true);
        $response->headers->addCacheControlDirective('no-store', true);

        return $response;
    }

    private function getModuleAssetVersion(): string
    {
        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $assetFiles = [
            $projectDir . '/public/modules/quittancement-generator/app.js',
            $projectDir . '/public/modules/quittancement-generator/style.css',
            $projectDir . '/templates/quittancement_generator/index.html.twig',
        ];

        $latestVersion = 0;
        foreach ($assetFiles as $assetFile) {
            if (is_file($assetFile)) {
                $latestVersion = max($latestVersion, (int) @filemtime($assetFile));
            }
        }

        return $latestVersion > 0
            ? (string) $latestVersion
            : QuittancementGeneratorService::APP_VERSION;
    }
}
