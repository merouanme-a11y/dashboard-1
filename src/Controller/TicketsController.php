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

    #[Route('/formulaire-patch', name: 'app_tickets_patch', methods: ['GET'], defaults: ['_managed_page_path' => 'app_tickets_patch'])]
    public function patchForm(): Response
    {
        $this->ensureModuleIsActive();
        $user = $this->getRequiredUser();
        $patchFormPayload = $this->youTrackApiService->getPatchFormOptionsPayloadForUser($user);
        $statusCode = ($patchFormPayload['_error'] ?? '') !== '' ? Response::HTTP_BAD_GATEWAY : Response::HTTP_OK;

        $response = $this->render('tickets/patch_form.html.twig', [
            'patchFormSubmitUrl' => $this->generateUrl('app_tickets_patch_create'),
            'patchFormPayload' => $patchFormPayload,
            'patchFormCsrfToken' => $this->container->get('security.csrf.token_manager')->getToken('tickets_patch_create')->getValue(),
        ], new Response('', $statusCode));

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

    #[Route('/formulaire-patch/create', name: 'app_tickets_patch_create', methods: ['POST'], defaults: ['_managed_page_path' => 'app_tickets_patch'])]
    public function createPatch(Request $request): JsonResponse
    {
        $this->ensureModuleIsActive();

        if (!$this->isCsrfTokenValid('tickets_patch_create', (string) $request->headers->get('X-CSRF-Token'))) {
            return $this->applyNoStoreHeaders(new JsonResponse([
                '_error' => 'Jeton CSRF invalide.',
            ], Response::HTTP_FORBIDDEN));
        }

        try {
            $payload = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->applyNoStoreHeaders(new JsonResponse([
                '_error' => 'Payload JSON invalide.',
            ], Response::HTTP_BAD_REQUEST));
        }

        $result = $this->youTrackApiService->createPatchTicket(
            $this->getRequiredUser(),
            is_array($payload) ? $payload : [],
        );

        $statusCode = $this->resolvePatchCreateStatusCode($result);

        return $this->applyNoStoreHeaders(new JsonResponse($result, $statusCode));
    }

    #[Route('/{id}', name: 'app_ticket_detail', methods: ['GET'], requirements: ['id' => '(?!data$|formulaire-patch$)[A-Za-z0-9\-]+'], defaults: ['_managed_page_path' => 'app_tickets'])]
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

    private function applyNoStoreHeaders(JsonResponse $response): JsonResponse
    {
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store', true);
        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->setVary(['Cookie', 'X-Requested-With'], false);

        return $response;
    }

    private function resolvePatchCreateStatusCode(array $payload): int
    {
        if (($payload['_error'] ?? '') === '') {
            return Response::HTTP_OK;
        }

        $error = trim((string) ($payload['_error'] ?? ''));

        if (
            str_starts_with($error, 'HTTP ')
            || str_contains($error, 'YouTrack')
            || str_contains($error, 'connexion')
            || str_contains($error, 'JSON')
        ) {
            return Response::HTTP_BAD_GATEWAY;
        }

        return Response::HTTP_BAD_REQUEST;
    }
}
