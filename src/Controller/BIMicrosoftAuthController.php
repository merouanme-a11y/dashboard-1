<?php

namespace App\Controller;

use App\Service\BIConfigurationService;
use App\Service\MicrosoftGraphAuthService;
use App\Service\ModuleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/bi/microsoft')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class BIMicrosoftAuthController extends AbstractController
{
    public function __construct(
        private BIConfigurationService $biConfigurationService,
        private ModuleService $moduleService,
        private MicrosoftGraphAuthService $microsoftGraphAuthService,
    ) {}

    #[Route('/connect', name: 'app_bi_microsoft_connect', methods: ['GET'])]
    public function connect(): RedirectResponse
    {
        $this->ensureModuleIsActive();

        try {
            return new RedirectResponse($this->microsoftGraphAuthService->getAuthorizationUrl());
        } catch (\Throwable $exception) {
            $this->addFlash('bi_settings_feedback', [
                'type' => 'is-error',
                'message' => $exception->getMessage(),
            ]);

            return $this->redirectToRoute('app_bi', ['openSettings' => 1]);
        }
    }

    #[Route('/callback', name: 'app_bi_microsoft_callback', methods: ['GET'])]
    public function callback(Request $request): RedirectResponse
    {
        $this->ensureModuleIsActive();

        $error = trim((string) $request->query->get('error', ''));
        if ($error !== '') {
            $description = trim((string) $request->query->get('error_description', ''));
            $this->addFlash('bi_settings_feedback', [
                'type' => 'is-error',
                'message' => $description !== ''
                    ? 'Connexion Microsoft refusee: ' . preg_replace('/\s+/', ' ', $description)
                    : 'Connexion Microsoft refusee.',
            ]);

            return $this->redirectToRoute('app_bi', ['openSettings' => 1]);
        }

        try {
            $this->microsoftGraphAuthService->handleAuthorizationCallback(
                (string) $request->query->get('code', ''),
                (string) $request->query->get('state', ''),
            );

            $this->addFlash('bi_settings_feedback', [
                'type' => 'is-success',
                'message' => 'Compte Microsoft connecte avec succes. Les sources SharePoint privees peuvent maintenant etre lues depuis cette session.',
            ]);
        } catch (\Throwable $exception) {
            $this->addFlash('bi_settings_feedback', [
                'type' => 'is-error',
                'message' => $exception->getMessage(),
            ]);
        }

        return $this->redirectToRoute('app_bi', ['openSettings' => 1]);
    }

    #[Route('/disconnect', name: 'app_bi_microsoft_disconnect', methods: ['POST'])]
    public function disconnect(Request $request): RedirectResponse
    {
        $this->ensureModuleIsActive();

        if (!$this->isCsrfTokenValid('bi_microsoft_disconnect', (string) $request->request->get('_token'))) {
            $this->addFlash('bi_settings_feedback', [
                'type' => 'is-error',
                'message' => 'Jeton CSRF invalide pour la deconnexion Microsoft.',
            ]);

            return $this->redirectToRoute('app_bi', ['openSettings' => 1]);
        }

        $this->microsoftGraphAuthService->disconnect();
        $this->addFlash('bi_settings_feedback', [
            'type' => 'is-success',
            'message' => 'Compte Microsoft deconnecte pour cette session.',
        ]);

        return $this->redirectToRoute('app_bi', ['openSettings' => 1]);
    }

    private function ensureModuleIsActive(): void
    {
        $module = $this->biConfigurationService->ensureModuleExists();
        $this->moduleService->invalidateCache();

        if (!$module->isActive()) {
            throw $this->createNotFoundException('Module indisponible.');
        }
    }
}
