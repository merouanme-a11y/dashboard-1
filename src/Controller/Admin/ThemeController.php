<?php

namespace App\Controller\Admin;

use App\Service\FileUploadService;
use App\Service\ThemeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/theme')]
#[IsGranted('ROLE_ADMIN')]
final class ThemeController extends AbstractController
{
    public function __construct(
        private ThemeService $themeService,
        private FileUploadService $fileUploadService,
    ) {}

    #[Route('', name: 'admin_theme', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $settings = $this->themeService->getAll();

        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('action');

            if ($action === 'reset_defaults') {
                $this->themeService->deleteLogoFile((string) ($settings['logo_path'] ?? ''));
                $this->themeService->resetDefaults();
                $this->addFlash('success', 'Parametrage du theme reinitialise.');

                return $this->redirectToRoute('admin_theme');
            }

            $payload = $request->request->all();
            $payload['sticky_header_enabled'] = $request->request->getBoolean('sticky_header_enabled');
            $payload['user_info'] = $request->request->getBoolean('user_info');
            $payload['header_right_menu'] = $request->request->getBoolean('header_right_menu');
            $payload['dark_mode_toggle'] = $request->request->getBoolean('dark_mode_toggle');
            $logoPath = (string) ($settings['logo_path'] ?? '');

            if ($request->request->getBoolean('clear_logo') && $logoPath !== '') {
                $this->themeService->deleteLogoFile($logoPath);
                $logoPath = '';
            }

            $logoFile = $request->files->get('logo_file');
            if ($logoFile) {
                if (!$this->fileUploadService->validateSvgSafety($logoFile)) {
                    $this->addFlash('danger', 'Le fichier SVG contient des elements non autorises.');

                    return $this->redirectToRoute('admin_theme');
                }

                try {
                    $uploadedLogo = $this->fileUploadService->uploadThemeLogo($logoFile);
                } catch (\Throwable $exception) {
                    $this->addFlash('danger', 'Impossible d\'enregistrer le logo du theme.');

                    return $this->redirectToRoute('admin_theme');
                }

                if ($logoPath !== '' && $logoPath !== $uploadedLogo) {
                    $this->themeService->deleteLogoFile($logoPath);
                }
                $logoPath = $uploadedLogo;
            }

            $payload['logo_path'] = $logoPath;
            unset($payload['action'], $payload['clear_logo']);

            $this->themeService->saveMultiple($payload);
            $this->addFlash('success', 'Parametrage du theme enregistre.');

            return $this->redirectToRoute('admin_theme');
        }

        $settings = $this->themeService->getAll();
        $fontOptions = $this->themeService->getFontFamilyOptions();
        $templates = $this->themeService->getAvailableTemplates();
        $menuHoverColor = $this->themeService->adjustHexColor((string) ($settings['menu_background_color'] ?? '#F8FAFC'), 12);

        return $this->render('admin/theme/index.html.twig', [
            'settings' => $settings,
            'templates' => $templates,
            'fontOptions' => $fontOptions,
            'menuHoverColor' => $menuHoverColor,
        ]);
    }
}
