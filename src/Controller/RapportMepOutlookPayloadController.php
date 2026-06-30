<?php

namespace App\Controller;

use App\Service\RapportMepDocumentService;
use App\Service\RapportMepService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/rapport-mep')]
final class RapportMepOutlookPayloadController extends AbstractController
{
    public function __construct(
        private RapportMepService $rapportMepService,
        private RapportMepDocumentService $rapportMepDocumentService,
    ) {}

    #[Route('/{reportId}/outlook-payload', name: 'app_rapport_mep_outlook_payload', methods: ['GET'])]
    public function __invoke(Request $request, string $reportId): JsonResponse
    {
        $reportId = trim($reportId);
        $expiresAt = (int) $request->query->get('expires', 0);
        $token = trim((string) $request->query->get('token', ''));

        if (!$this->rapportMepDocumentService->isValidOutlookPayloadToken($reportId, $expiresAt, $token)) {
            return $this->json([
                'error' => 'Lien Outlook invalide ou expire.',
            ], 403);
        }

        $report = $this->rapportMepService->findReport($reportId);
        if (!is_array($report)) {
            return $this->json([
                'error' => 'Rapport MEP introuvable.',
            ], 404);
        }

        return $this->json($this->rapportMepDocumentService->buildOutlookRemotePayload($report));
    }
}
