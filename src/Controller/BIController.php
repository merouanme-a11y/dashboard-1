<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Service\BIChartBuilderService;
use App\Service\BIConfigurationService;
use App\Service\BIModuleSettingsService;
use App\Service\ModuleService;
use App\Service\SharePointDataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/bi')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class BIController extends AbstractController
{
    public function __construct(
        private SharePointDataService $sharePointDataService,
        private BIChartBuilderService $biChartBuilderService,
        private BIConfigurationService $biConfigurationService,
        private BIModuleSettingsService $biModuleSettingsService,
        private ModuleService $moduleService,
    ) {}

    #[Route('', name: 'app_bi', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getRequiredUser();
        $module = $this->biConfigurationService->ensureModuleExists();
        $this->moduleService->invalidateCache();

        if (!$module->isActive()) {
            throw $this->createNotFoundException('Module indisponible.');
        }

        $preferences = $this->biConfigurationService->getForUser($user);
        $canEdit = $this->canEditBuilder($user);
        $connections = $this->sharePointDataService->getAvailableConnections();

        $selectedConnection = trim((string) $request->query->get(
            'connection',
            $preferences['defaultConnection'] ?? ($connections[0]['id'] ?? '')
        ));
        $files = $selectedConnection !== ''
            ? $this->sharePointDataService->getAvailableFiles($selectedConnection)
            : [];

        $selectedFile = trim((string) $request->query->get(
            'file',
            $preferences['defaultFile'] ?? ($files[0]['id'] ?? '')
        ));

        $datasetPayload = $selectedConnection !== '' && $selectedFile !== ''
            ? $this->sharePointDataService->getDatasetPayload($selectedConnection, $selectedFile)
            : null;

        $builderOptions = is_array($datasetPayload) && !isset($datasetPayload['_error'])
            ? $this->biChartBuilderService->getBuilderOptions($datasetPayload)
            : $this->biChartBuilderService->getBuilderOptions([]);

        $suggestedWidgets = is_array($datasetPayload) && !isset($datasetPayload['_error'])
            ? $this->biChartBuilderService->buildSuggestedWidgets(
                $datasetPayload,
                $this->sharePointDataService->getSuggestedColumns($datasetPayload)
            )
            : [];

        return $this->render('bi/index.html.twig', [
            'biConnectionsUrl' => $this->generateUrl('app_bi_connections'),
            'biFilesUrl' => $this->generateUrl('app_bi_files'),
            'biDatasetUrl' => $this->generateUrl('app_bi_dataset'),
            'biPreferencesUrl' => $this->generateUrl('app_bi_preferences'),
            'biSettingsUrl' => $this->generateUrl('app_bi_settings'),
            'biUploadSourceUrl' => $this->generateUrl('app_bi_upload_source'),
            'biPreloadedConnections' => $connections,
            'biPreloadedConnectionId' => $selectedConnection,
            'biPreloadedFiles' => $files,
            'biPreloadedFileId' => $selectedFile,
            'biPreloadedDataset' => $datasetPayload,
            'biBuilderOptions' => $builderOptions,
            'biSuggestedWidgets' => $suggestedWidgets,
            'biPreferencesPayload' => $preferences,
            'biModuleSettings' => $this->biModuleSettingsService->getSettings(),
            'biCanEdit' => $canEdit,
        ]);
    }

    #[Route('/connections', name: 'app_bi_connections', methods: ['GET'], defaults: ['_managed_page_path' => 'app_bi'])]
    public function connections(): JsonResponse
    {
        $this->ensureModuleIsActive();

        return new JsonResponse([
            'connections' => $this->sharePointDataService->getAvailableConnections(),
        ]);
    }

    #[Route('/files', name: 'app_bi_files', methods: ['GET'], defaults: ['_managed_page_path' => 'app_bi'])]
    public function files(Request $request): JsonResponse
    {
        $this->ensureModuleIsActive();

        return new JsonResponse([
            'files' => $this->sharePointDataService->getAvailableFiles((string) $request->query->get('connection', '')),
        ]);
    }

    #[Route('/dataset', name: 'app_bi_dataset', methods: ['GET'], defaults: ['_managed_page_path' => 'app_bi'])]
    public function dataset(Request $request): JsonResponse
    {
        $this->ensureModuleIsActive();

        $payload = $this->sharePointDataService->getDatasetPayload(
            (string) $request->query->get('connection', ''),
            (string) $request->query->get('file', ''),
        );

        if (($payload['_error'] ?? '') !== '') {
            return new JsonResponse($payload, Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($payload);
    }

    #[Route('/preferences', name: 'app_bi_preferences', methods: ['POST'], defaults: ['_managed_page_path' => 'app_bi'])]
    public function savePreferences(Request $request): JsonResponse
    {
        $this->ensureModuleIsActive();
        $user = $this->getRequiredUser();
        if (!$this->canEditBuilder($user)) {
            return new JsonResponse(['_error' => 'Vous n avez pas les droits pour modifier cette page BI.'], Response::HTTP_FORBIDDEN);
        }

        if (!$this->isCsrfTokenValid('bi_preferences', (string) $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['_error' => 'Jeton CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $payload = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['_error' => 'Payload JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'saved' => true,
            'preferences' => $this->biConfigurationService->saveForUser(
                $user,
                is_array($payload['preferences'] ?? null) ? $payload['preferences'] : [],
            ),
        ]);
    }

    #[Route('/settings', name: 'app_bi_settings', methods: ['GET', 'POST'], defaults: ['_managed_page_path' => 'app_bi'])]
    public function settings(Request $request): JsonResponse
    {
        $this->ensureModuleIsActive();
        $user = $this->getRequiredUser();

        if ($request->isMethod('GET')) {
            return new JsonResponse([
                'settings' => $this->biModuleSettingsService->getSettings(),
            ]);
        }

        if (!$this->canEditBuilder($user)) {
            return new JsonResponse(['_error' => 'Vous n avez pas les droits pour modifier les parametres BI.'], Response::HTTP_FORBIDDEN);
        }

        if (!$this->isCsrfTokenValid('bi_settings', (string) $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['_error' => 'Jeton CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $payload = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['_error' => 'Payload JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $action = trim((string) ($payload['action'] ?? ''));

        try {
            $settings = match ($action) {
                'add_remote_source' => $this->biModuleSettingsService->addRemoteSource(
                    (string) ($payload['label'] ?? ''),
                    (string) ($payload['url'] ?? ''),
                ),
                'delete_source' => $this->biModuleSettingsService->removeSource((string) ($payload['sourceId'] ?? '')),
                default => throw new \InvalidArgumentException('Action de parametrage BI inconnue.'),
            };
        } catch (\Throwable $exception) {
            return new JsonResponse(['_error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'saved' => true,
            'settings' => $settings,
        ]);
    }

    #[Route('/upload-source', name: 'app_bi_upload_source', methods: ['POST'], defaults: ['_managed_page_path' => 'app_bi'])]
    public function uploadSource(Request $request): JsonResponse
    {
        $this->ensureModuleIsActive();
        $user = $this->getRequiredUser();

        if (!$this->canEditBuilder($user)) {
            return new JsonResponse(['_error' => 'Vous n avez pas les droits pour importer des sources BI.'], Response::HTTP_FORBIDDEN);
        }

        if (!$this->isCsrfTokenValid('bi_settings', (string) $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['_error' => 'Jeton CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $file = $request->files->get('sourceFile');
        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['_error' => 'Aucun fichier BI n a ete recu.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $settings = $this->biModuleSettingsService->addUploadedSource(
                $file,
                (string) $request->request->get('label', ''),
            );
        } catch (\Throwable $exception) {
            return new JsonResponse(['_error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'saved' => true,
            'settings' => $settings,
        ]);
    }

    private function ensureModuleIsActive(): void
    {
        $module = $this->biConfigurationService->ensureModuleExists();
        $this->moduleService->invalidateCache();

        if (!$module->isActive()) {
            throw $this->createNotFoundException('Module indisponible.');
        }
    }

    private function getRequiredUser(): Utilisateur
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function canEditBuilder(Utilisateur $user): bool
    {
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return strcasecmp($user->getEffectiveProfileType(), 'Admin') === 0;
    }
}
