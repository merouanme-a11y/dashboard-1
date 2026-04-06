<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Service\GanttLegacyRuntime;
use App\Service\GanttViewStateService;
use App\Service\ModuleService;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/projets/gantt')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class GanttProjectsController extends AbstractController
{
    public const MODULE_NAME = 'gantt_projects';

    public function __construct(
        private ModuleService $moduleService,
        private GanttLegacyRuntime $ganttLegacyRuntime,
        private GanttViewStateService $ganttViewStateService,
        private UtilisateurRepository $utilisateurRepository,
        private KernelInterface $kernel,
    ) {}

    #[Route('', name: 'app_gantt_projects', methods: ['GET'])]
    public function index(): Response
    {
        $this->ensureModuleIsActive();

        $user = $this->getRequiredUser();
        $this->ganttLegacyRuntime->bootForUser($user);

        return $this->render('gantt_projects/index.html.twig', [
            'ganttAuthUser' => $this->ganttLegacyRuntime->createFrontendUserPayload($user),
            'ganttConfig' => [
                'baseUrl' => $this->generateUrl('app_gantt_projects'),
                'loginUrl' => $this->generateUrl('app_login'),
                'logoutUrl' => $this->generateUrl('app_logout'),
                'ticketDetailUrlPattern' => str_replace(
                    'MTN-1',
                    '__ID__',
                    $this->generateUrl('app_ticket_detail', ['id' => 'MTN-1'])
                ),
                'availableProfiles' => $this->utilisateurRepository->findDistinctProfileTypes(),
                'sharedSettings' => $this->readSharedSettings(),
                'sharedViewState' => $this->ganttViewStateService->getState(),
                'routes' => [
                    'session' => $this->generateUrl('app_gantt_projects_session'),
                    'settings' => $this->generateUrl('app_gantt_projects_api_settings'),
                    'viewState' => $this->generateUrl('app_gantt_projects_api_view_state'),
                    'projects' => $this->generateUrl('app_gantt_projects_api_projects'),
                    'createProject' => $this->generateUrl('app_gantt_projects_api_create_project'),
                    'projectUsers' => $this->generateUrl('app_gantt_projects_api_project_users'),
                    'projectTeam' => $this->generateUrl('app_gantt_projects_api_project_team'),
                    'youtrackProjectTasks' => $this->generateUrl('app_gantt_projects_api_youtrack_project_tasks'),
                    'createYouTrackProjectTask' => $this->generateUrl('app_gantt_projects_api_create_youtrack_project_task'),
                    'updateYouTrackProjectTask' => $this->generateUrl('app_gantt_projects_api_update_youtrack_project_task'),
                    'deleteYouTrackProjectTask' => $this->generateUrl('app_gantt_projects_api_delete_youtrack_project_task'),
                    'services' => $this->generateUrl('app_gantt_projects_api_services'),
                    'importProjects' => $this->generateUrl('app_gantt_projects_api_import_projects'),
                    'exportProjects' => $this->generateUrl('app_gantt_projects_api_export_projects'),
                ],
            ],
        ]);
    }

    private function ensureModuleIsActive(): void
    {
        if (!$this->moduleService->isActive(self::MODULE_NAME)) {
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

    private function readSharedSettings(): array
    {
        $path = $this->kernel->getProjectDir() . '/var/gantt/shared-settings.json';
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }
}
