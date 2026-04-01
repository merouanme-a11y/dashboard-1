<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Service\ModuleService;
use App\Service\YouTrackApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tickets')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class TicketsController extends AbstractController
{
    public function __construct(
        private YouTrackApiService $youTrackApiService,
        private ModuleService $moduleService,
    ) {}

    #[Route('', name: 'app_tickets', methods: ['GET'])]
    public function index(): Response
    {
        $this->ensureModuleIsActive();
        $user = $this->getRequiredUser();

        $response = $this->render('tickets/index.html.twig', [
            'ticketsDataUrl' => $this->generateUrl('app_tickets_data'),
            'ticketDetailUrlPattern' => str_replace(
                'MTN-1',
                '__ID__',
                $this->generateUrl('app_ticket_detail', ['id' => 'MTN-1'])
            ),
            'ticketsCacheKey' => $this->buildBrowserCacheKey($user),
            'preloadedTicketsPayload' => $this->youTrackApiService->peekTicketsPayloadForUser($user),
        ]);

        return $this->applyPrivateCacheHeaders($response, 300);
    }

    #[Route('/data', name: 'app_tickets_data', methods: ['GET'], defaults: ['_managed_page_path' => 'app_tickets'])]
    public function data(Request $request): JsonResponse
    {
        $this->ensureModuleIsActive();

        $payload = $this->youTrackApiService->getTicketsPayloadForUser(
            $this->getRequiredUser(),
            $request->query->getBoolean('refresh'),
        );

        $response = new JsonResponse($payload, ($payload['_error'] ?? '') !== '' ? Response::HTTP_BAD_GATEWAY : Response::HTTP_OK);

        return $this->applyPrivateCacheHeaders($response, 300);
    }

    #[Route('/{id}', name: 'app_ticket_detail', methods: ['GET'], requirements: ['id' => '(?!data$)[A-Za-z0-9\-]+'], defaults: ['_managed_page_path' => 'app_tickets'])]
    public function show(string $id): Response
    {
        $this->ensureModuleIsActive();

        $payload = $this->youTrackApiService->getTicketDetailPayloadForUser($id, $this->getRequiredUser());
        $statusCode = ($payload['_error'] ?? '') !== '' ? Response::HTTP_NOT_FOUND : Response::HTTP_OK;

        return $this->render('tickets/show.html.twig', [
            'ticketId' => $id,
            'detailPayload' => $payload,
        ], new Response('', $statusCode));
    }

    private function ensureModuleIsActive(): void
    {
        if (!$this->moduleService->isActive(YouTrackApiService::TICKETS_MODULE)) {
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

        return 'tickets_browser_cache_' . $userId . '_' . $scope . '_v1';
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
