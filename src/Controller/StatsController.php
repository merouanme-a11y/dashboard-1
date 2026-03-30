<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Service\ModuleService;
use App\Service\StatsPreferenceService;
use App\Service\YouTrackApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/stats')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class StatsController extends AbstractController
{
    public function __construct(
        private YouTrackApiService $youTrackApiService,
        private ModuleService $moduleService,
        private StatsPreferenceService $statsPreferenceService,
    ) {}

    #[Route('', name: 'app_stats', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->ensureModuleIsActive();
        $user = $this->getRequiredUser();
        $selectedProject = trim((string) $request->query->get('project', ''));
        $preloadedProjectsPayload = $this->youTrackApiService->peekAvailableProjectsPayload();
        $preloadedProjectId = $selectedProject;

        if ($preloadedProjectId === '' && is_array($preloadedProjectsPayload['projects'] ?? null)) {
            $firstProject = $preloadedProjectsPayload['projects'][0] ?? null;
            $preloadedProjectId = is_array($firstProject) ? trim((string) ($firstProject['id'] ?? '')) : '';
        }

        $response = $this->render('stats/index.html.twig', [
            'statsProjectsUrl' => $this->generateUrl('app_stats_projects'),
            'statsTicketsUrl' => $this->generateUrl('app_stats_tickets'),
            'statsPreferencesUrl' => $this->generateUrl('app_stats_preferences'),
            'ticketsPageUrl' => $this->generateUrl('app_tickets'),
            'statsBrowserCacheKey' => $this->buildBrowserCacheKey($user),
            'preloadedProjectsPayload' => $preloadedProjectsPayload,
            'preloadedProjectId' => $preloadedProjectId,
            'preloadedProjectTicketsPayload' => $preloadedProjectId !== ''
                ? $this->youTrackApiService->peekProjectTicketsPayload($preloadedProjectId)
                : null,
            'statsPreferencesPayload' => $this->statsPreferenceService->getForUser($user),
        ]);

        return $this->applyPrivateCacheHeaders($response, 300);
    }

    #[Route('/projects', name: 'app_stats_projects', methods: ['GET'], defaults: ['_managed_page_path' => 'app_stats'])]
    public function projects(Request $request): JsonResponse
    {
        $this->ensureModuleIsActive();

        $payload = $this->youTrackApiService->getAvailableProjectsPayload(
            $request->query->getBoolean('refresh'),
        );

        $response = new JsonResponse($payload, ($payload['_error'] ?? '') !== '' ? Response::HTTP_BAD_GATEWAY : Response::HTTP_OK);

        return $this->applyPrivateCacheHeaders($response, 1800);
    }

    #[Route('/tickets', name: 'app_stats_tickets', methods: ['GET'], defaults: ['_managed_page_path' => 'app_stats'])]
    public function tickets(Request $request): JsonResponse
    {
        $this->ensureModuleIsActive();

        $payload = $this->youTrackApiService->getProjectTicketsPayload(
            (string) $request->query->get('project', ''),
            $request->query->getBoolean('refresh'),
        );

        $response = new JsonResponse($payload, ($payload['_error'] ?? '') !== '' ? Response::HTTP_BAD_GATEWAY : Response::HTTP_OK);

        return $this->applyPrivateCacheHeaders($response, 300);
    }

    #[Route('/preferences', name: 'app_stats_preferences', methods: ['POST'], defaults: ['_managed_page_path' => 'app_stats'])]
    public function savePreferences(Request $request): JsonResponse
    {
        $this->ensureModuleIsActive();
        $user = $this->getRequiredUser();

        if (!$this->isCsrfTokenValid('stats_preferences', (string) $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse([
                '_error' => 'Jeton CSRF invalide.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $payload = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse([
                '_error' => 'Payload JSON invalide.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $preferences = $this->statsPreferenceService->saveForUser(
            $user,
            is_array($payload['preferences'] ?? null) ? $payload['preferences'] : [],
        );

        return new JsonResponse([
            'saved' => true,
            'preferences' => $preferences,
        ]);
    }

    private function ensureModuleIsActive(): void
    {
        if (!$this->moduleService->isActive('stats')) {
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

    private function buildBrowserCacheKey(Utilisateur $user): string
    {
        $userId = (string) ($user->getId() ?? '0');
        $scope = in_array('ROLE_ADMIN', $user->getRoles(), true)
            ? 'admin'
            : md5(mb_strtolower(trim((string) ($user->getService() ?? ''))));

        return 'stats_browser_cache_' . $userId . '_' . $scope . '_v3';
    }

    private function applyPrivateCacheHeaders(Response $response, int $maxAge): Response
    {
        $response->setPrivate();
        $response->setMaxAge($maxAge);
        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->setVary(['Cookie', 'X-Requested-With'], false);

        return $response;
    }
}
