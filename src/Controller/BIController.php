<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Service\AdminUserDirectoryService;
use App\Service\BIChartBuilderService;
use App\Service\BIConfigurationService;
use App\Service\BIModuleSettingsService;
use App\Service\MicrosoftGraphAuthService;
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
        private MicrosoftGraphAuthService $microsoftGraphAuthService,
        private ModuleService $moduleService,
        private UtilisateurRepository $utilisateurRepository,
        private AdminUserDirectoryService $adminUserDirectoryService,
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
        $connections = $this->sharePointDataService->getAvailableConnections();
        $selectedPage = null;
        $hasEditablePage = false;
        $canManagePagePermissions = false;
        foreach ((array) ($preferences['pages'] ?? []) as $candidatePage) {
            if (($candidatePage['canEdit'] ?? false) === true) {
                $hasEditablePage = true;
            }
            if (($candidatePage['canManagePermissions'] ?? false) === true) {
                $canManagePagePermissions = true;
            }
        }
        foreach ((array) ($preferences['pages'] ?? []) as $page) {
            if ((string) ($page['id'] ?? '') === (string) ($preferences['selectedPageId'] ?? '')) {
                $selectedPage = $page;
                break;
            }
        }
        if (!is_array($selectedPage)) {
            $selectedPage = $preferences['pages'][0] ?? [];
        }

        $selectedConnection = trim((string) $request->query->get(
            'connection',
            $selectedPage['connectionId'] ?? ($connections[0]['id'] ?? '')
        ));
        if (
            $selectedConnection !== ''
            && !array_filter($connections, static fn (array $connection): bool => (string) ($connection['id'] ?? '') === $selectedConnection)
        ) {
            $selectedConnection = (string) ($connections[0]['id'] ?? '');
        }
        $files = $selectedConnection !== ''
            ? $this->sharePointDataService->getAvailableFiles($selectedConnection)
            : [];

        $selectedFile = $this->resolveSelectedFileId($files, trim((string) $request->query->get(
            'file',
            $selectedPage['fileId'] ?? ($files[0]['id'] ?? '')
        )));

        $preloadedDataset = $selectedConnection !== '' && $selectedFile !== ''
            ? $this->sharePointDataService->peekDatasetPayload($selectedConnection, $selectedFile)
            : null;

        $builderOptions = is_array($preloadedDataset) && !isset($preloadedDataset['_error'])
            ? $this->biChartBuilderService->getBuilderOptions($preloadedDataset)
            : $this->biChartBuilderService->getBuilderOptions([]);

        $suggestedWidgets = is_array($preloadedDataset) && !isset($preloadedDataset['_error'])
            ? $this->biChartBuilderService->buildSuggestedWidgets(
                $preloadedDataset,
                $this->sharePointDataService->getSuggestedColumns($preloadedDataset)
            )
            : [];
        $settingsFeedback = $this->consumeSettingsFeedback($request);
        $microsoftAuth = $this->microsoftGraphAuthService->getConnectionStatus();

        $response = $this->render('bi/index.html.twig', [
            'biConnectionsUrl' => $this->generateUrl('app_bi_connections'),
            'biFilesUrl' => $this->generateUrl('app_bi_files'),
            'biDatasetUrl' => $this->generateUrl('app_bi_dataset'),
            'biPreferencesUrl' => $this->generateUrl('app_bi_preferences'),
            'biBrowserCacheKey' => $this->buildBrowserCacheKey($user),
            'biSettingsUrl' => $this->generateUrl('app_bi_settings'),
            'biUploadSourceUrl' => $this->generateUrl('app_bi_upload_source'),
            'biPreloadedConnections' => $connections,
            'biPreloadedConnectionId' => $selectedConnection,
            'biPreloadedFiles' => $files,
            'biPreloadedFileId' => $selectedFile,
            'biPreloadedDataset' => $preloadedDataset,
            'biBuilderOptions' => $builderOptions,
            'biSuggestedWidgets' => $suggestedWidgets,
            'biPreferencesPayload' => $preferences,
            'biModuleSettings' => $this->biModuleSettingsService->getSettings(),
            'biRightsDirectory' => $this->buildRightsDirectory(),
            'biMicrosoftAuth' => $microsoftAuth,
            'biMicrosoftConnectUrl' => $this->generateUrl('app_bi_microsoft_connect'),
            'biMicrosoftDisconnectUrl' => $this->generateUrl('app_bi_microsoft_disconnect'),
            'biOpenSettingsOnLoad' => $request->query->getBoolean('openSettings') || $settingsFeedback !== null,
            'biPreloadedSettingsFeedback' => $settingsFeedback,
            'biCanManageSettings' => $this->canManageBISettings($user),
            'biHasEditablePage' => $hasEditablePage,
            'biCanManagePagePermissions' => $canManagePagePermissions,
        ]);

        return $this->applyPrivateCacheHeaders($response, 300);
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
            $request->query->getBoolean('refresh'),
        );

        if (($payload['_error'] ?? '') !== '') {
            return $this->applyPrivateCacheHeaders(new JsonResponse($payload, Response::HTTP_BAD_REQUEST), 0);
        }

        return $this->applyPrivateCacheHeaders(new JsonResponse($payload), 300);
    }

    #[Route('/preferences', name: 'app_bi_preferences', methods: ['POST'], defaults: ['_managed_page_path' => 'app_bi'])]
    public function savePreferences(Request $request): JsonResponse
    {
        $this->ensureModuleIsActive();
        $user = $this->getRequiredUser();

        if (!$this->isCsrfTokenValid('bi_preferences', (string) $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['_error' => 'Jeton CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $payload = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['_error' => 'Payload JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $preferences = $this->biConfigurationService->saveForUser(
                $user,
                is_array($payload['preferences'] ?? null) ? $payload['preferences'] : [],
            );
        } catch (\DomainException $exception) {
            return new JsonResponse(['_error' => $exception->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['_error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'saved' => true,
            'preferences' => $preferences,
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
                'update_page_creation_permissions' => $this->requireSettingsManager($user, fn () => $this->biModuleSettingsService->updatePageCreationPermissions(
                    is_array($payload['userIds'] ?? null) ? $payload['userIds'] : [],
                    is_array($payload['profileTypes'] ?? null) ? $payload['profileTypes'] : [],
                )),
                'update_page_visibility' => $this->biConfigurationService->updatePageVisibility(
                    $user,
                    (string) ($payload['pageId'] ?? ''),
                    is_array($payload['userIds'] ?? null) ? $payload['userIds'] : [],
                    is_array($payload['profileTypes'] ?? null) ? $payload['profileTypes'] : [],
                ),
                'add_remote_source' => $this->requireSettingsManager($user, fn () => $this->biModuleSettingsService->addRemoteSource(
                    (string) ($payload['label'] ?? ''),
                    (string) ($payload['url'] ?? ''),
                )),
                'add_api_source' => $this->requireSettingsManager($user, fn () => $this->biModuleSettingsService->addApiSource(
                    (string) ($payload['label'] ?? ''),
                    (string) ($payload['url'] ?? ''),
                    (string) ($payload['token'] ?? ''),
                )),
                'update_source' => $this->requireSettingsManager($user, fn () => $this->biModuleSettingsService->updateSource(
                    (string) ($payload['sourceId'] ?? ''),
                    (string) ($payload['label'] ?? ''),
                    array_key_exists('url', $payload) ? (string) ($payload['url'] ?? '') : null,
                    array_key_exists('token', $payload) ? (string) ($payload['token'] ?? '') : null,
                )),
                'delete_source' => $this->requireSettingsManager($user, fn () => $this->biModuleSettingsService->removeSource((string) ($payload['sourceId'] ?? ''))),
                default => throw new \InvalidArgumentException('Action de parametrage BI inconnue.'),
            };
        } catch (\Throwable $exception) {
            $statusCode = $exception instanceof \DomainException ? Response::HTTP_FORBIDDEN : Response::HTTP_BAD_REQUEST;

            return new JsonResponse(['_error' => $exception->getMessage()], $statusCode);
        }

        if ($action === 'update_page_creation_permissions') {
            return new JsonResponse([
                'saved' => true,
                'settings' => $settings,
            ]);
        }

        if ($action === 'update_page_visibility') {
            return new JsonResponse([
                'saved' => true,
                'preferences' => $settings,
            ]);
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

        if (!$this->canManageBISettings($user)) {
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

    private function canManageBISettings(Utilisateur $user): bool
    {
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return strcasecmp($user->getEffectiveProfileType(), 'Admin') === 0;
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    private function requireSettingsManager(Utilisateur $user, callable $callback): mixed
    {
        if (!$this->canManageBISettings($user)) {
            throw new \DomainException('Vous n avez pas les droits pour modifier les parametres BI.');
        }

        return $callback();
    }

    private function buildRightsDirectory(): array
    {
        $users = [];
        foreach ($this->utilisateurRepository->findAllSorted() as $candidate) {
            if (!$candidate instanceof Utilisateur || (int) ($candidate->getId() ?? 0) < 1) {
                continue;
            }

            $users[] = [
                'id' => (int) $candidate->getId(),
                'label' => trim((string) $candidate->getPrenom() . ' ' . (string) $candidate->getNom()) ?: (string) $candidate->getEmail(),
                'email' => (string) $candidate->getEmail(),
            ];
        }

        return [
            'users' => $users,
            'profiles' => $this->adminUserDirectoryService->getAvailableProfileTypes(),
        ];
    }

    private function buildBrowserCacheKey(Utilisateur $user): string
    {
        return 'bi_browser_cache_' . (string) ($user->getId() ?? '0') . '_v1';
    }

    private function applyPrivateCacheHeaders(Response $response, int $maxAge): Response
    {
        $response->setPrivate();
        $response->setMaxAge(max(0, $maxAge));
        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->setVary(['Cookie', 'X-Requested-With'], false);

        return $response;
    }

    private function resolveSelectedFileId(array $files, string $selectedFile): string
    {
        $selectedFile = trim($selectedFile);
        if ($selectedFile !== '') {
            foreach ($files as $file) {
                if ((string) ($file['id'] ?? '') === $selectedFile) {
                    return $selectedFile;
                }
            }
        }

        return (string) ($files[0]['id'] ?? '');
    }

    private function consumeSettingsFeedback(Request $request): ?array
    {
        if (!$request->hasSession()) {
            return null;
        }

        $messages = $request->getSession()->getFlashBag()->get('bi_settings_feedback', []);
        $message = $messages[0] ?? null;

        return is_array($message) ? $message : null;
    }
}
