<?php

namespace App\Tests\Service;

use App\Service\ProjectCdcDocumentService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ProjectCdcDocumentServiceTest extends KernelTestCase
{
    private const SAMPLE_PNG_DATA_URI = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7Z0ioAAAAASUVORK5CYII=';

    private ?string $projectDirectory = null;

    protected function tearDown(): void
    {
        if ($this->projectDirectory !== null) {
            $this->removeDirectory($this->projectDirectory);
            $this->projectDirectory = null;
        }

        parent::tearDown();
    }

    public function testGenerateAndImportRoundTripPreservesCdcSections(): void
    {
        self::bootKernel();

        /** @var ProjectCdcDocumentService $service */
        $service = static::getContainer()->get(ProjectCdcDocumentService::class);
        $project = [
            'id' => 'test-project-cdc-service',
            'ref' => 'PRJ-CDC-001',
            'title' => 'Projet CDC de test',
            'cdcTitle' => 'Titre CDC personnalise',
            'service' => 'IT',
            'projectManager' => 'Merouan Hamzaoui',
            'cdcPresentation' => '<p>Contexte initial du projet.</p><p>Besoin metier a cadrer.</p>',
            'cdcObjectives' => '<ul><li>Objectif 1</li><li>Objectif 2</li></ul>',
            'cdcFeatures' => '<p>Fonction A</p><p>Fonction B</p>',
            'cdcConstraints' => '<p>Contrainte forte de securite.</p>',
            'cdcAdditionalInfo' => '<p>Informations supplementaires a conserver.</p><p><img src="' . self::SAMPLE_PNG_DATA_URI . '" alt="Schema CDC" width="24" height="24"></p>',
            'cdcUpdatedAt' => '2026-06-19 10:30:00',
        ];
        $this->projectDirectory = static::getContainer()->getParameter('kernel.project_dir') . '/var/gantt/cdc/test-project-cdc-service';

        $generatedDocument = $service->generateProjectDocx($project);

        self::assertFileExists($generatedDocument['path']);
        self::assertSame('CDC-PRJ-CDC-001-Projet-CDC-de-test.docx', $generatedDocument['fileName']);
        $archive = new \ZipArchive();
        self::assertTrue($archive->open($generatedDocument['path']) === true);
        $documentXml = (string) $archive->getFromName('word/document.xml');
        self::assertStringContainsString('Titre CDC personnalise', $documentXml);
        self::assertStringContainsString('Chef de projet', $documentXml);
        self::assertStringContainsString('<w:jc w:val="center"/>', $documentXml);
        $archive->close();

        $extractedSections = $service->extractSectionsFromDocx($generatedDocument['path']);

        self::assertStringContainsString('Contexte initial du projet.', strip_tags((string) ($extractedSections['cdcPresentation'] ?? '')));
        self::assertStringContainsString('Objectif 1', strip_tags((string) ($extractedSections['cdcObjectives'] ?? '')));
        self::assertStringContainsString('Fonction B', strip_tags((string) ($extractedSections['cdcFeatures'] ?? '')));
        self::assertStringContainsString('Contrainte forte de securite.', strip_tags((string) ($extractedSections['cdcConstraints'] ?? '')));
        self::assertStringContainsString('Informations supplementaires a conserver.', strip_tags((string) ($extractedSections['cdcAdditionalInfo'] ?? '')));
        self::assertStringContainsString('<img', (string) ($extractedSections['cdcAdditionalInfo'] ?? ''));
        self::assertStringContainsString('data:image/png;base64,', (string) ($extractedSections['cdcAdditionalInfo'] ?? ''));

        $temporaryImportPath = sys_get_temp_dir() . '/project-cdc-import-' . uniqid('', true) . '.docx';
        self::assertTrue(copy($generatedDocument['path'], $temporaryImportPath));

        try {
            $uploadedFile = new UploadedFile(
                $temporaryImportPath,
                'cdc.docx',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                null,
                true
            );

            $importedDocument = $service->importProjectDocx($project, $uploadedFile);
            $normalizedSections = $service->normalizeImportedSections($importedDocument['sections']);

            self::assertSame($generatedDocument['path'], $importedDocument['path']);
            self::assertStringContainsString('Besoin metier a cadrer.', strip_tags((string) ($normalizedSections['cdcPresentation'] ?? '')));
            self::assertStringContainsString('Objectif 2', strip_tags((string) ($normalizedSections['cdcObjectives'] ?? '')));
            self::assertStringContainsString('Fonction A', strip_tags((string) ($normalizedSections['cdcFeatures'] ?? '')));
            self::assertStringContainsString('Contrainte forte de securite.', strip_tags((string) ($normalizedSections['cdcConstraints'] ?? '')));
            self::assertStringContainsString('Informations supplementaires a conserver.', strip_tags((string) ($normalizedSections['cdcAdditionalInfo'] ?? '')));
            self::assertStringContainsString('<img', (string) ($normalizedSections['cdcAdditionalInfo'] ?? ''));
            self::assertStringContainsString('data:image/png;base64,', (string) ($normalizedSections['cdcAdditionalInfo'] ?? ''));
        } finally {
            if (is_file($temporaryImportPath)) {
                @unlink($temporaryImportPath);
            }
        }
    }

    public function testHasCdcContentTreatsImageOnlyHtmlAsContent(): void
    {
        self::bootKernel();

        /** @var ProjectCdcDocumentService $service */
        $service = static::getContainer()->get(ProjectCdcDocumentService::class);

        self::assertTrue($service->hasCdcContent([
            'cdcPresentation' => '<p><img src="' . self::SAMPLE_PNG_DATA_URI . '" alt="Schema seul"></p>',
        ]));
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
