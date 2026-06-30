<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Service\ModuleService;
use App\Service\RapportMepDocumentService;
use App\Service\RapportMepService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

#[Route('/rapport-mep')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class RapportMepController extends AbstractController
{
    public function __construct(
        private RapportMepService $rapportMepService,
        private RapportMepDocumentService $rapportMepDocumentService,
        private ModuleService $moduleService,
    ) {}

    #[Route('', name: 'app_rapport_mep', methods: ['GET', 'POST'], defaults: ['_managed_page_path' => 'app_rapport_mep'])]
    public function index(Request $request): Response
    {
        $user = $this->getRequiredUser();
        $module = $this->rapportMepService->ensureModuleExists();
        $this->moduleService->invalidateCache();

        if (!$module->isActive()) {
            throw $this->createNotFoundException('Module indisponible.');
        }

        if ($request->isMethod('POST')) {
            return $this->handlePost($request, $user);
        }

        $reports = $this->rapportMepService->listReports();
        $selectedReportId = trim((string) $request->query->get('report', ''));
        $selectedReport = $selectedReportId !== ''
            ? $this->rapportMepService->findReport($selectedReportId)
            : ($reports[0] ?? null);

        return $this->renderPage($reports, $selectedReport, $request->query->getBoolean('openOutlook', false));
    }

    #[Route('/{reportId}/pdf', name: 'app_rapport_mep_pdf_download', methods: ['GET'], defaults: ['_managed_page_path' => 'app_rapport_mep'])]
    public function pdfDownload(string $reportId): BinaryFileResponse
    {
        $this->getRequiredUser();
        $report = $this->requireExistingReport($reportId);
        $document = $this->rapportMepDocumentService->ensureReportPdf($report);

        $response = new BinaryFileResponse((string) $document['path'], 200, [
            'Content-Type' => 'application/pdf',
        ], true, null, false, false);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, (string) $document['fileName']);

        return $response;
    }

    #[Route('/{reportId}/mail-draft', name: 'app_rapport_mep_mail_draft_download', methods: ['GET'], defaults: ['_managed_page_path' => 'app_rapport_mep'])]
    public function mailDraftDownload(string $reportId): Response
    {
        $this->getRequiredUser();
        $report = $this->requireExistingReport($reportId);
        $draft = $this->rapportMepDocumentService->buildEmailDraft($report);

        $response = new Response((string) $draft['content']);
        $response->headers->set('Content-Type', 'message/rfc822; charset=UTF-8');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            (string) $draft['fileName']
        ));

        return $response;
    }

    #[Route('/{reportId}/open-mail-draft', name: 'app_rapport_mep_mail_draft_open', methods: ['GET'], defaults: ['_managed_page_path' => 'app_rapport_mep'])]
    public function openMailDraft(Request $request, string $reportId): RedirectResponse
    {
        $this->getRequiredUser();

        $host = strtolower(trim((string) $request->getHost()));
        if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            $this->addFlash('danger', 'L ouverture directe dans Outlook est disponible uniquement en localhost.');

            return $this->redirectToRoute('app_rapport_mep', ['report' => $reportId]);
        }

        try {
            $report = $this->requireExistingReport($reportId);
            $this->rapportMepDocumentService->openEmailDraftInOutlook($report);
            $this->addFlash('success', 'Le brouillon mail a ete ouvert dans Outlook.');
        } catch (\Throwable $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        return $this->redirectToRoute('app_rapport_mep', ['report' => $reportId]);
    }

    private function handlePost(Request $request, Utilisateur $user): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('rapport_mep_form', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de securite invalide. Rechargez la page puis recommencez.');

            return $this->redirectToRoute('app_rapport_mep');
        }

        $action = trim((string) $request->request->get('action', ''));
        $reportId = trim((string) $request->request->get('reportId', ''));

        try {
            switch ($action) {
                case 'new':
                    $report = $this->rapportMepService->createReport($user);
                    $this->addFlash('success', 'Nouveau rapport MEP cree.');

                    return $this->redirectToRoute('app_rapport_mep', ['report' => $report['id']]);

                case 'save':
                    $report = $this->requireExistingReport($reportId);
                    $report = $this->rapportMepService->updateReportFromForm($report, $request->request->all());
                    $this->addFlash('success', 'Rapport MEP enregistre.');

                    return $this->redirectToRoute('app_rapport_mep', ['report' => $report['id']]);

                case 'open_outlook':
                    $report = $reportId !== ''
                        ? $this->requireExistingReport($reportId)
                        : $this->rapportMepService->createReport($user);

                    $report = $this->rapportMepService->updateReportFromForm($report, $request->request->all());
                    $this->addFlash('success', 'Rapport MEP enregistre. Ouverture Outlook en cours.');

                    return $this->redirectToRoute('app_rapport_mep', [
                        'report' => $report['id'],
                        'openOutlook' => 1,
                    ]);

                case 'import':
                    $report = $reportId !== ''
                        ? $this->requireExistingReport($reportId)
                        : $this->rapportMepService->createReport($user);

                    $report = $this->rapportMepService->updateReportFromForm($report, $request->request->all());

                    $sourceFile = $request->files->get('sourceFile');
                    if (!$sourceFile) {
                        throw new \RuntimeException('Aucun fichier Excel source n a ete selectionne.');
                    }

                    $report = $this->rapportMepService->importSourceWorkbook($report, $sourceFile);
                    $this->addFlash('success', sprintf('Import termine : %d ticket(s) charges.', count($report['rows'] ?? [])));

                    return $this->redirectToRoute('app_rapport_mep', ['report' => $report['id']]);

                case 'delete':
                    $selectedReportId = trim((string) $request->request->get('selectedReportId', ''));
                    $this->requireExistingReport($reportId);
                    $this->rapportMepService->deleteReport($reportId);
                    $this->addFlash('success', 'Rapport MEP supprime.');

                    $redirectParameters = [];
                    if ($selectedReportId !== '' && $selectedReportId !== $reportId && $this->rapportMepService->findReport($selectedReportId)) {
                        $redirectParameters['report'] = $selectedReportId;
                    } else {
                        $remainingReports = $this->rapportMepService->listReports();
                        if ($remainingReports !== []) {
                            $redirectParameters['report'] = (string) ($remainingReports[0]['id'] ?? '');
                        }
                    }

                    return $this->redirectToRoute('app_rapport_mep', array_filter($redirectParameters, static fn ($value) => $value !== ''));

                default:
                    $this->addFlash('danger', 'Action Rapport MEP inconnue.');

                    return $this->redirectToRoute('app_rapport_mep', $reportId !== '' ? ['report' => $reportId] : []);
            }
        } catch (\Throwable $exception) {
            $this->addFlash('danger', $exception->getMessage());

            return $this->redirectToRoute('app_rapport_mep', $reportId !== '' ? ['report' => $reportId] : []);
        }
    }

    private function renderPage(array $reports, ?array $selectedReport, bool $shouldAutoOpenOutlook = false): Response
    {
        $outlookProtocolUrl = '';
        if (is_array($selectedReport)) {
            try {
                $outlookPayloadUrl = $this->buildOutlookPayloadUrl($selectedReport);
                $outlookLaunch = $this->rapportMepDocumentService->prepareOutlookLaunch($selectedReport, $outlookPayloadUrl);
                $outlookProtocolUrl = (string) ($outlookLaunch['protocolUrl'] ?? '');
            } catch (\Throwable $exception) {
                $this->addFlash('danger', $exception->getMessage());
            }
        }

        return $this->render('rapport_mep/index.html.twig', [
            'assetVersion' => $this->getAssetVersion(),
            'reports' => $reports,
            'selectedReport' => $selectedReport,
            'colorMap' => $this->rapportMepService->getColorMap(),
            'tableRows' => is_array($selectedReport) ? $this->rapportMepService->buildTableRows($selectedReport) : [],
            'previewStats' => is_array($selectedReport) ? $this->rapportMepService->buildPreviewStats($selectedReport) : null,
            'outlookProtocolUrl' => $outlookProtocolUrl,
            'autoOpenOutlook' => $shouldAutoOpenOutlook,
        ]);
    }

    private function requireExistingReport(string $reportId): array
    {
        $report = $this->rapportMepService->findReport($reportId);
        if (!is_array($report)) {
            throw new \RuntimeException('Rapport MEP introuvable.');
        }

        return $report;
    }

    private function buildOutlookPayloadUrl(array $report): string
    {
        $reportId = trim((string) ($report['id'] ?? ''));
        if ($reportId === '') {
            throw new \RuntimeException('Identifiant de rapport MEP manquant.');
        }

        $expiresAt = time() + 900;
        $token = $this->rapportMepDocumentService->createOutlookPayloadToken($reportId, $expiresAt);

        return $this->generateUrl('app_rapport_mep_outlook_payload', [
            'reportId' => $reportId,
            'expires' => $expiresAt,
            'token' => $token,
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function getRequiredUser(): Utilisateur
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function getAssetVersion(): string
    {
        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $assetFiles = [
            $projectDir . '/src/Controller/RapportMepController.php',
            $projectDir . '/src/Service/RapportMepService.php',
            $projectDir . '/templates/rapport_mep/index.html.twig',
            $projectDir . '/public/modules/rapport-mep/style.css',
        ];

        $version = 0;
        foreach ($assetFiles as $assetFile) {
            if (is_file($assetFile)) {
                $version = max($version, (int) @filemtime($assetFile));
            }
        }

        return $version > 0 ? (string) $version : RapportMepService::APP_VERSION;
    }
}
