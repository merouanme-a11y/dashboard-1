<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Service\GanttLegacyRuntime;
use App\Service\ModuleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/projets/gantt')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class GanttProjectsApiController extends AbstractController
{
    public function __construct(
        private ModuleService $moduleService,
        private GanttLegacyRuntime $ganttLegacyRuntime,
        private KernelInterface $kernel,
    ) {}

    #[Route('/api/session', name: 'app_gantt_projects_session', methods: ['GET'], defaults: ['_managed_page_path' => 'app_gantt_projects'])]
    public function session(): JsonResponse
    {
        $user = $this->bootAndGetUser();

        return new JsonResponse([
            'user' => $this->ganttLegacyRuntime->createFrontendUserPayload($user),
            'settings' => $this->readSharedSettings(),
        ]);
    }

    #[Route('/api/settings', name: 'app_gantt_projects_api_settings', methods: ['GET', 'POST'], defaults: ['_managed_page_path' => 'app_gantt_projects'])]
    public function settings(Request $request): JsonResponse
    {
        $user = $this->bootAndGetUser();

        if ($request->isMethod('GET')) {
            return new JsonResponse([
                'settings' => $this->readSharedSettings(),
            ]);
        }

        if (!in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->jsonError('Acces refuse.', 403);
        }

        $payload = $this->readJsonRequest($request);
        $incomingSettings = is_array($payload['settings'] ?? null) ? $payload['settings'] : [];
        $settings = [
            'authorizedProfile' => trim((string) ($incomingSettings['authorizedProfile'] ?? '')),
            'showSettingsAccessButton' => !empty($incomingSettings['showSettingsAccessButton']),
            'showTimelineProgressButton' => !empty($incomingSettings['showTimelineProgressButton']),
            'showTodayMarkerButton' => !empty($incomingSettings['showTodayMarkerButton']),
        ];

        $this->writeSharedSettings($settings);

        return new JsonResponse([
            'settings' => $settings,
        ]);
    }

    #[Route('/api/projects', name: 'app_gantt_projects_api_projects', methods: ['GET', 'POST', 'DELETE'], defaults: ['_managed_page_path' => 'app_gantt_projects'])]
    public function projects(Request $request): Response
    {
        $this->bootAndGetUser();

        try {
            if ($request->isMethod('GET')) {
                return new JsonResponse(app_fetch_projects());
            }

            $payload = $this->readJsonRequest($request);

            if ($request->isMethod('POST')) {
                $projects = $payload['projects'] ?? null;
                if (!is_array($projects)) {
                    return $this->jsonError('Aucune liste de projets a enregistrer', 400);
                }

                app_store_projects(array_values($projects));

                return new Response('', Response::HTTP_NO_CONTENT);
            }

            $projectId = trim((string) ($payload['id'] ?? $request->query->get('id', '')));
            if ($projectId === '') {
                return $this->jsonError('Identifiant projet manquant', 400);
            }

            $project = app_fetch_project_by_id($projectId);
            if ($project === null) {
                return $this->jsonError('Projet introuvable.', 404);
            }

            $youTrackProjectKey = trim((string) ($project['youtrackId'] ?? ''));
            if ($youTrackProjectKey !== '') {
                try {
                    app_delete_youtrack_project($youTrackProjectKey);
                } catch (\Throwable $exception) {
                    return $this->jsonError(
                        'Le projet n a pas ete supprime localement, car l archivage dans YouTrack a echoue : '
                        . $exception->getMessage(),
                        502
                    );
                }
            }

            app_delete_project($projectId);

            return new Response('', Response::HTTP_NO_CONTENT);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonError($exception->getMessage(), 400);
        } catch (\RuntimeException $exception) {
            return $this->jsonError($exception->getMessage(), 404);
        }
    }

    #[Route('/api/create-project', name: 'app_gantt_projects_api_create_project', methods: ['POST'], defaults: ['_managed_page_path' => 'app_gantt_projects'])]
    public function createProject(Request $request): JsonResponse
    {
        $user = $this->bootAndGetUser();
        $payload = $this->readJsonRequest($request);
        $project = $payload['project'] ?? null;
        $createInYouTrack = !empty($payload['createInYouTrack']);
        $removeFromYouTrack = !empty($payload['removeFromYouTrack']);

        if (!is_array($project)) {
            return $this->jsonError('Aucun projet a creer', 400);
        }

        $currentUser = $this->ganttLegacyRuntime->createFrontendUserPayload($user);
        $projectId = trim((string) ($project['id'] ?? ''));
        $projectRef = trim((string) ($project['ref'] ?? ''));
        $existingProject = $projectId !== '' ? app_fetch_project_by_id($projectId) : null;
        $projectWithSameRef = $projectRef !== '' ? app_fetch_project_by_ref($projectRef) : null;

        $resolvedOwnerId = trim((string) (($existingProject['ownerId'] ?? null) ?: ($project['ownerId'] ?? null) ?: ($currentUser['id'] ?? '')));
        $resolvedOwnerDisplayName = trim((string) (($existingProject['ownerDisplayName'] ?? null) ?: ($project['ownerDisplayName'] ?? null) ?: ($currentUser['displayName'] ?? $currentUser['username'] ?? '')));
        $resolvedOwnerEmail = trim((string) (($existingProject['ownerEmail'] ?? null) ?: ($project['ownerEmail'] ?? null) ?: ($currentUser['email'] ?? '')));

        $project['ownerId'] = $resolvedOwnerId !== '' ? $resolvedOwnerId : null;
        $project['ownerDisplayName'] = $resolvedOwnerDisplayName !== '' ? $resolvedOwnerDisplayName : null;
        $project['ownerEmail'] = $resolvedOwnerEmail !== '' ? $resolvedOwnerEmail : null;

        if (
            $projectWithSameRef !== null
            && ($existingProject === null || (string) ($projectWithSameRef['id'] ?? '') !== (string) ($existingProject['id'] ?? ''))
        ) {
            return $this->jsonError('Un projet avec cet identifiant existe deja.', 409);
        }

        try {
            if ($removeFromYouTrack) {
                $existingYouTrackKey = trim((string) ($existingProject['youtrackId'] ?? $project['youtrackId'] ?? ''));

                if ($existingYouTrackKey !== '') {
                    app_delete_youtrack_project($existingYouTrackKey);
                }

                $project['youtrackId'] = null;
                $project['youtrackUrl'] = null;
            }

            if ($createInYouTrack && trim((string) ($project['youtrackId'] ?? '')) === '') {
                $youtrackProject = app_create_gantt_youtrack_project($project, $currentUser);
                $project['youtrackId'] = $youtrackProject['shortName'];
                $project['youtrackUrl'] = $youtrackProject['url'];

                if (!empty($project['teamMembers']) && is_array($project['teamMembers'])) {
                    $project['teamMembers'] = app_sync_youtrack_project_team($project['youtrackId'], $project['teamMembers']);
                }
            }

            if ($existingProject !== null) {
                $storedProjects = app_store_projects([$project]);
                $savedProject = is_array($storedProjects[0] ?? null) ? $storedProjects[0] : null;
                if ($savedProject === null) {
                    throw new \RuntimeException('Impossible de mettre a jour le projet.');
                }

                return new JsonResponse(['project' => $savedProject], 200);
            }

            $createdProject = app_create_project($project);

            return new JsonResponse(['project' => $createdProject], 201);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonError($exception->getMessage(), 400);
        } catch (\DomainException $exception) {
            return $this->jsonError($exception->getMessage(), 409);
        } catch (\RuntimeException $exception) {
            return $this->jsonError($exception->getMessage(), 502);
        } catch (\Throwable) {
            return $this->jsonError('Impossible de creer le projet.', 500);
        }
    }

    #[Route('/api/project-users', name: 'app_gantt_projects_api_project_users', methods: ['GET'], defaults: ['_managed_page_path' => 'app_gantt_projects'])]
    public function projectUsers(): JsonResponse
    {
        $this->bootAndGetUser();

        return new JsonResponse([
            'users' => app_list_youtrack_standard_users(),
        ]);
    }

    #[Route('/api/project-team', name: 'app_gantt_projects_api_project_team', methods: ['GET', 'POST', 'DELETE'], defaults: ['_managed_page_path' => 'app_gantt_projects'])]
    public function projectTeam(Request $request): JsonResponse
    {
        $user = $this->bootAndGetUser();
        $currentUser = $this->ganttLegacyRuntime->createFrontendUserPayload($user);

        try {
            if ($request->isMethod('GET')) {
                $projectKey = trim((string) $request->query->get('project', ''));
                if ($projectKey === '') {
                    return $this->jsonError('Projet YouTrack manquant.', 400);
                }

                return new JsonResponse([
                    'team' => app_list_youtrack_project_team($projectKey),
                    'canManage' => $this->canManageProjectTeam($projectKey, $currentUser),
                ]);
            }

            $payload = $this->readJsonRequest($request);
            $projectKey = trim((string) ($payload['project'] ?? ''));
            $userRingId = trim((string) ($payload['userId'] ?? ''));

            if ($projectKey === '' || $userRingId === '') {
                return $this->jsonError('Projet ou utilisateur YouTrack manquant.', 400);
            }

            if (!$this->canManageProjectTeam($projectKey, $currentUser)) {
                return $this->jsonError('Lecture seule : seul le responsable du projet peut modifier l equipe.', 403);
            }

            if ($request->isMethod('POST')) {
                return new JsonResponse([
                    'team' => app_add_youtrack_user_to_project_team($projectKey, $userRingId),
                    'canManage' => true,
                ]);
            }

            return new JsonResponse([
                'team' => app_remove_youtrack_user_from_project_team($projectKey, $userRingId),
                'canManage' => true,
            ]);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonError($exception->getMessage(), 400);
        } catch (\Throwable $exception) {
            return $this->jsonError($exception->getMessage(), 502);
        }
    }

    #[Route('/api/youtrack-project-tasks', name: 'app_gantt_projects_api_youtrack_project_tasks', methods: ['GET'], defaults: ['_managed_page_path' => 'app_gantt_projects'])]
    public function youtrackProjectTasks(Request $request): JsonResponse
    {
        $this->bootAndGetUser();
        $projectKey = trim((string) $request->query->get('project', ''));
        if ($projectKey === '') {
            return $this->jsonError('Projet YouTrack manquant.', 400);
        }

        try {
            return new JsonResponse(app_list_youtrack_project_tasks($projectKey));
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonError($exception->getMessage(), 400);
        } catch (\Throwable $exception) {
            return $this->jsonError($exception->getMessage(), 502);
        }
    }

    #[Route('/api/create-youtrack-project-task', name: 'app_gantt_projects_api_create_youtrack_project_task', methods: ['POST'], defaults: ['_managed_page_path' => 'app_gantt_projects'])]
    public function createYouTrackProjectTask(Request $request): JsonResponse
    {
        $user = $this->bootAndGetUser();
        $currentUser = $this->ganttLegacyRuntime->createFrontendUserPayload($user);
        $payload = $this->readJsonRequest($request);
        $projectKey = trim((string) ($payload['project'] ?? ''));
        $task = $payload['task'] ?? null;

        if ($projectKey === '') {
            return $this->jsonError('Projet YouTrack manquant.', 400);
        }

        if (!is_array($task)) {
            return $this->jsonError('Tache manquante.', 400);
        }

        if (!app_user_can_manage_youtrack_project_tasks($projectKey, $currentUser)) {
            return $this->jsonError('Lecture seule : vous devez appartenir a l equipe du projet pour modifier les taches.', 403);
        }

        try {
            return new JsonResponse([
                'task' => app_create_youtrack_project_task($projectKey, $task),
            ], 201);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonError($exception->getMessage(), 400);
        } catch (\Throwable $exception) {
            return $this->jsonError($exception->getMessage(), 502);
        }
    }

    #[Route('/api/update-youtrack-project-task', name: 'app_gantt_projects_api_update_youtrack_project_task', methods: ['POST'], defaults: ['_managed_page_path' => 'app_gantt_projects'])]
    public function updateYouTrackProjectTask(Request $request): JsonResponse
    {
        $user = $this->bootAndGetUser();
        $currentUser = $this->ganttLegacyRuntime->createFrontendUserPayload($user);
        $payload = $this->readJsonRequest($request);
        $projectKey = trim((string) ($payload['project'] ?? ''));
        $taskId = trim((string) ($payload['taskId'] ?? ''));
        $updates = $payload['updates'] ?? null;

        if ($projectKey === '' || $taskId === '') {
            return $this->jsonError('Projet ou tache YouTrack manquant.', 400);
        }

        if (!is_array($updates)) {
            return $this->jsonError('Aucune modification a appliquer.', 400);
        }

        if (!app_user_can_manage_youtrack_project_tasks($projectKey, $currentUser)) {
            return $this->jsonError('Lecture seule : vous devez appartenir a l equipe du projet pour modifier les taches.', 403);
        }

        try {
            return new JsonResponse([
                'task' => app_update_youtrack_project_task($projectKey, $taskId, $updates),
            ]);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonError($exception->getMessage(), 400);
        } catch (\Throwable $exception) {
            return $this->jsonError($exception->getMessage(), 502);
        }
    }

    #[Route('/api/delete-youtrack-project-task', name: 'app_gantt_projects_api_delete_youtrack_project_task', methods: ['DELETE'], defaults: ['_managed_page_path' => 'app_gantt_projects'])]
    public function deleteYouTrackProjectTask(Request $request): Response
    {
        $user = $this->bootAndGetUser();
        $currentUser = $this->ganttLegacyRuntime->createFrontendUserPayload($user);
        $payload = $this->readJsonRequest($request);
        $projectKey = trim((string) ($payload['project'] ?? ''));
        $issueId = trim((string) ($payload['id'] ?? ''));

        if ($projectKey === '' || $issueId === '') {
            return $this->jsonError('Projet ou tache YouTrack manquant.', 400);
        }

        if (!app_user_can_manage_youtrack_project_tasks($projectKey, $currentUser)) {
            return $this->jsonError('Lecture seule : vous devez appartenir a l equipe du projet pour modifier les taches.', 403);
        }

        try {
            app_delete_youtrack_project_task($issueId, $projectKey);

            return new Response('', Response::HTTP_NO_CONTENT);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonError($exception->getMessage(), 400);
        } catch (\Throwable $exception) {
            return $this->jsonError($exception->getMessage(), 502);
        }
    }

    #[Route('/api/services', name: 'app_gantt_projects_api_services', methods: ['GET', 'POST'], defaults: ['_managed_page_path' => 'app_gantt_projects'])]
    public function services(Request $request): JsonResponse
    {
        $this->bootAndGetUser();

        try {
            if ($request->isMethod('POST')) {
                $payload = $this->readJsonRequest($request);
                $service = (string) ($payload['service'] ?? '');
                $color = (string) ($payload['color'] ?? '');

                return new JsonResponse([
                    'services' => app_update_service_color($service, $color),
                ]);
            }

            return new JsonResponse([
                'services' => app_fetch_service_colors(),
            ]);
        } catch (\RuntimeException $exception) {
            return $this->jsonError($exception->getMessage(), 500);
        }
    }

    #[Route('/api/import-projects', name: 'app_gantt_projects_api_import_projects', methods: ['POST'], defaults: ['_managed_page_path' => 'app_gantt_projects'])]
    public function importProjects(Request $request): JsonResponse
    {
        $this->bootAndGetUser();

        $uploadedFile = $request->files->get('sourceFile');
        if ($uploadedFile === null) {
            return $this->jsonError('Aucun fichier source recu', 400);
        }

        $extension = strtolower((string) $uploadedFile->getClientOriginalExtension());
        if ($extension !== 'xlsx') {
            return $this->jsonError('Format non supporte. Utilisez un fichier .xlsx', 400);
        }

        $targetFile = app_source_workbook_file();

        try {
            $uploadedFile->move(\dirname($targetFile), \basename($targetFile));
        } catch (FileException $exception) {
            return $this->jsonError('Impossible d enregistrer le fichier source : ' . $exception->getMessage(), 500);
        }

        try {
            return new JsonResponse(app_import_projects_from_workbook($targetFile));
        } catch (\RuntimeException $exception) {
            return $this->jsonError($exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            return $this->jsonError('Import impossible : ' . $exception->getMessage(), 500);
        }
    }

    #[Route('/api/export-projects', name: 'app_gantt_projects_api_export_projects', methods: ['POST'], defaults: ['_managed_page_path' => 'app_gantt_projects'])]
    public function exportProjects(Request $request): JsonResponse
    {
        $this->bootAndGetUser();
        $payload = $this->readJsonRequest($request);
        $projects = $payload['projects'] ?? null;

        if (!is_array($projects)) {
            return $this->jsonError('Aucune liste de projets a exporter', 400);
        }

        try {
            return new JsonResponse(app_export_projects_to_workbook(array_values($projects)));
        } catch (\RuntimeException $exception) {
            return $this->jsonError($exception->getMessage(), 422);
        }
    }

    #[Route('/export/{fileName}', name: 'app_gantt_projects_export_download', methods: ['GET'], defaults: ['_managed_page_path' => 'app_gantt_projects'])]
    public function exportDownload(string $fileName): BinaryFileResponse
    {
        $this->bootAndGetUser();

        $safeFileName = basename($fileName);
        $filePath = $this->getParameter('kernel.project_dir') . '/public/modules/gantt-projects/export/' . $safeFileName;

        if (!is_file($filePath)) {
            throw $this->createNotFoundException('Fichier export introuvable.');
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $safeFileName);

        return $response;
    }

    private function bootAndGetUser(): Utilisateur
    {
        $this->ensureModuleIsActive();

        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }

        $this->ganttLegacyRuntime->bootForUser($user);

        return $user;
    }

    private function ensureModuleIsActive(): void
    {
        if (!$this->moduleService->isActive(GanttProjectsController::MODULE_NAME)) {
            throw $this->createNotFoundException('Module indisponible.');
        }
    }

    private function readJsonRequest(Request $request): array
    {
        $content = trim($request->getContent());
        if ($content === '') {
            return [];
        }

        try {
            $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('Charge utile JSON invalide.', 0, $exception);
        }

        if (!is_array($payload)) {
            throw new \InvalidArgumentException('Charge utile JSON invalide.');
        }

        return $payload;
    }

    private function jsonError(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status);
    }

    private function readSharedSettings(): array
    {
        $path = $this->getSharedSettingsPath();
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function writeSharedSettings(array $settings): void
    {
        $path = $this->getSharedSettingsPath();
        $directory = \dirname($path);

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de creer le dossier de configuration Gantt.');
        }

        $written = @file_put_contents(
            $path,
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        if ($written === false) {
            throw new \RuntimeException('Impossible d enregistrer la configuration Gantt.');
        }
    }

    private function getSharedSettingsPath(): string
    {
        return $this->kernel->getProjectDir() . '/var/gantt/shared-settings.json';
    }

    private function canManageProjectTeam(string $projectKey, array $dashboardUser): bool
    {
        $localProject = app_fetch_project_by_youtrack_id($projectKey);
        if (is_array($localProject) && $this->projectOwnerMatchesDashboardUser($localProject, $dashboardUser)) {
            return true;
        }

        return $this->projectLeaderMatchesDashboardUser($projectKey, $dashboardUser);
    }

    private function projectOwnerMatchesDashboardUser(array $project, array $dashboardUser): bool
    {
        $ownerEmail = app_normalize_youtrack_user_match_value($project['ownerEmail'] ?? '');
        $ownerId = app_normalize_youtrack_user_match_value($project['ownerId'] ?? '');
        $ownerDisplayName = app_normalize_youtrack_user_match_value($project['ownerDisplayName'] ?? '');

        $userEmail = app_normalize_youtrack_user_match_value($dashboardUser['email'] ?? '');
        $userId = app_normalize_youtrack_user_match_value($dashboardUser['id'] ?? $dashboardUser['username'] ?? '');
        $userDisplayName = app_normalize_youtrack_user_match_value($dashboardUser['displayName'] ?? '');

        return (
            ($ownerEmail !== '' && $userEmail !== '' && $ownerEmail === $userEmail)
            || ($ownerId !== '' && $userId !== '' && $ownerId === $userId)
            || ($ownerDisplayName !== '' && $userDisplayName !== '' && $ownerDisplayName === $userDisplayName)
        );
    }

    private function projectLeaderMatchesDashboardUser(string $projectKey, array $dashboardUser): bool
    {
        $youtrackProject = app_find_youtrack_project_or_null($projectKey);
        if (!is_array($youtrackProject)) {
            return false;
        }

        $leader = is_array($youtrackProject['leader'] ?? null) ? $youtrackProject['leader'] : [];
        $leaderId = trim((string) ($leader['id'] ?? ''));
        $matchedLeader = $leaderId !== '' ? app_find_youtrack_standard_user_by_youtrack_id($leaderId) : null;

        $leaderCandidate = [
            'email' => trim((string) ($matchedLeader['email'] ?? '')),
            'login' => trim((string) ($matchedLeader['login'] ?? $leader['login'] ?? '')),
            'displayName' => trim((string) ($matchedLeader['displayName'] ?? $leader['name'] ?? $leader['login'] ?? '')),
        ];

        return app_is_youtrack_team_member_matching_dashboard_user($leaderCandidate, $dashboardUser);
    }
}
