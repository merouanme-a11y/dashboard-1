<?php

namespace App\Controller;

use App\Service\ThemeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ThemeTemplateAssetController extends AbstractController
{
    public function __construct(
        private ThemeService $themeService,
    ) {}

    #[Route('/css/theme-template/{template}/styles.css', name: 'theme_template_stylesheet', methods: ['GET'], requirements: ['template' => '[A-Za-z0-9_-]+'])]
    public function stylesheet(string $template): Response
    {
        $templates = $this->themeService->getAvailableTemplates();
        if (!isset($templates[$template])) {
            throw $this->createNotFoundException('Template introuvable.');
        }

        $filePath = $this->themeService->getTemplateStylesheetFilePath($template);
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw $this->createNotFoundException('Feuille de style introuvable.');
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw $this->createNotFoundException('Impossible de lire la feuille de style.');
        }

        $lastModified = filemtime($filePath) ?: time();

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified) . ' GMT',
        ]);
    }
}
