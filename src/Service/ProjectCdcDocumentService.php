<?php

namespace App\Service;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use ZipArchive;

final class ProjectCdcDocumentService
{
    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const PKG_REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';
    private const DRAWING_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const PIC_NS = 'http://schemas.openxmlformats.org/drawingml/2006/picture';
    private const WP_NS = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';

    private const SECTION_DEFINITIONS = [
        'cdcPresentation' => [
            'title' => 'Présentation et contexte du projet',
            'tag' => 'cdc_presentation',
        ],
        'cdcObjectives' => [
            'title' => 'Objectifs',
            'tag' => 'cdc_objectives',
        ],
        'cdcFeatures' => [
            'title' => 'Fonctionnalités attendues',
            'tag' => 'cdc_features',
        ],
        'cdcConstraints' => [
            'title' => 'Contraintes et points de vigilance',
            'tag' => 'cdc_constraints',
        ],
        'cdcAdditionalInfo' => [
            'title' => 'Informations complémentaires',
            'tag' => 'cdc_additional_info',
        ],
    ];

    public function __construct(
        private KernelInterface $kernel,
        private FileUploadService $fileUploadService,
        private ?string $libreOfficeBinary = null,
    ) {}

    public function getSectionDefinitions(): array
    {
        return self::SECTION_DEFINITIONS;
    }

    public function hasCdcContent(array $project): bool
    {
        if (trim((string) ($project['cdcTitle'] ?? '')) !== '') {
            return true;
        }

        if ($this->hasCdcSummaryContent($project)) {
            return true;
        }

        foreach (array_keys(self::SECTION_DEFINITIONS) as $fieldName) {
            if ($this->hasMeaningfulHtmlContent((string) ($project[$fieldName] ?? ''))) {
                return true;
            }
        }

        return false;
    }

    public function ensureProjectDocx(array $project): array
    {
        if (!$this->hasCdcContent($project)) {
            throw new \RuntimeException('Aucun contenu de cahier des charges n est disponible pour ce projet.');
        }

        $docxPath = $this->getProjectDocxPath($project);
        $updatedAt = $this->parseProjectUpdatedAtTimestamp($project);

        if (!is_file($docxPath) || ($updatedAt !== null && filemtime($docxPath) < $updatedAt)) {
            $this->generateProjectDocx($project);
        }

        return [
            'path' => $docxPath,
            'fileName' => $this->buildDownloadFileName($project, 'docx'),
        ];
    }

    public function generateProjectDocx(array $project): array
    {
        if (!$this->hasCdcContent($project)) {
            throw new \RuntimeException('Aucun contenu de cahier des charges n est disponible pour ce projet.');
        }

        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive est requis pour générer le document Word.');
        }

        $projectDirectory = $this->ensureProjectDirectory($project);
        $docxPath = $this->getProjectDocxPath($project);
        $tempPath = $projectDirectory . '/cdc-' . bin2hex(random_bytes(6)) . '.docx';
        $package = $this->buildDocxPackage($project);

        $zip = new ZipArchive();
        $result = $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($result !== true) {
            throw new \RuntimeException('Impossible de créer le fichier DOCX du cahier des charges.');
        }

        try {
            $zip->addFromString('[Content_Types].xml', $package['contentTypesXml']);
            $zip->addFromString('_rels/.rels', $this->buildRootRelationshipsXml());
            $zip->addFromString('docProps/app.xml', $this->buildAppPropertiesXml());
            $zip->addFromString('docProps/core.xml', $this->buildCorePropertiesXml($project));
            $zip->addFromString('word/document.xml', $package['documentXml']);
            $zip->addFromString('word/styles.xml', $this->buildStylesXml());
            $zip->addFromString('word/settings.xml', $this->buildSettingsXml());
            $zip->addFromString('word/numbering.xml', $this->buildNumberingXml());
            $zip->addFromString('word/_rels/document.xml.rels', $package['documentRelationshipsXml']);
            foreach ($package['mediaFiles'] as $mediaFile) {
                $zip->addFromString('word/' . $mediaFile['target'], $mediaFile['content']);
            }
        } finally {
            $zip->close();
        }

        if (is_file($docxPath) && !@unlink($docxPath)) {
            @unlink($tempPath);
            throw new \RuntimeException('Impossible de remplacer la version Word du cahier des charges.');
        }

        if (!@rename($tempPath, $docxPath)) {
            @unlink($tempPath);
            throw new \RuntimeException('Impossible d enregistrer le fichier Word du cahier des charges.');
        }

        return [
            'path' => $docxPath,
            'fileName' => $this->buildDownloadFileName($project, 'docx'),
        ];
    }

    public function ensureProjectPdf(array $project): array
    {
        $docx = $this->ensureProjectDocx($project);
        $pdfPath = $this->getProjectPdfPath($project);

        if (!is_file($pdfPath) || filemtime($pdfPath) < filemtime($docx['path'])) {
            $this->convertDocxToPdf($docx['path'], $pdfPath);
        }

        return [
            'path' => $pdfPath,
            'fileName' => $this->buildDownloadFileName($project, 'pdf'),
        ];
    }

    public function importProjectDocx(array $project, UploadedFile $file): array
    {
        if (!$file->isValid()) {
            throw new \RuntimeException('Le fichier Word importé est invalide.');
        }

        $originalName = strtolower((string) $file->getClientOriginalName());
        if (!str_ends_with($originalName, '.docx')) {
            throw new \RuntimeException('Seuls les fichiers Word au format .docx sont supportés.');
        }

        $this->ensureProjectDirectory($project);
        $docxPath = $this->getProjectDocxPath($project);

        if (!@copy($file->getPathname(), $docxPath)) {
            throw new \RuntimeException('Impossible de remplacer la version Word du cahier des charges.');
        }

        $sections = $this->extractSectionsFromDocx($docxPath);
        $warning = null;

        try {
            $this->convertDocxToPdf($docxPath, $this->getProjectPdfPath($project));
        } catch (\RuntimeException $exception) {
            $warning = 'Le document Word a bien ete remplace, mais la mise a jour du PDF a echoue : ' . $exception->getMessage();
        }

        return [
            'sections' => $sections,
            'path' => $docxPath,
            'fileName' => $this->buildDownloadFileName($project, 'docx'),
            'warning' => $warning ?? ($this->shouldWarnAboutMissingImportedSections($sections)
                ? 'Le document Word a bien ete remplace, mais certaines sections n ont pas pu etre relues automatiquement.'
                : null),
        ];

        return [
            'sections' => $sections,
            'path' => $docxPath,
            'fileName' => $this->buildDownloadFileName($project, 'docx'),
            'warning' => $this->shouldWarnAboutMissingImportedSections($sections)
                ? 'Le document Word a bien été remplacé, mais certaines sections n ont pas pu être relues automatiquement.'
                : null,
        ];
    }

    public function deleteProjectDocuments(array $project): void
    {
        $this->deleteProjectEditorAssets($project);

        $projectId = trim((string) ($project['id'] ?? ''));
        if ($projectId === '') {
            return;
        }

        $directory = $this->kernel->getProjectDir() . '/var/gantt/cdc/' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', $projectId);
        if (!is_dir($directory)) {
            return;
        }

        foreach (['current.docx', 'current.pdf'] as $fileName) {
            $filePath = $directory . '/' . $fileName;
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }

        $entries = @scandir($directory);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                return;
            }
        }

        @rmdir($directory);
    }

    private function deleteProjectEditorAssets(array $project): void
    {
        $assetPaths = [];

        foreach (array_keys(self::SECTION_DEFINITIONS) as $fieldName) {
            foreach ($this->extractProjectEditorAssetPaths((string) ($project[$fieldName] ?? '')) as $assetPath) {
                $assetPaths[$assetPath] = true;
            }
        }

        foreach (array_keys($assetPaths) as $assetPath) {
            $this->fileUploadService->deleteFile($assetPath);
        }
    }

    private function extractProjectEditorAssetPaths(string $html): array
    {
        $html = trim($html);
        if ($html === '') {
            return [];
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = false;
        $document->formatOutput = false;

        $wrappedHtml = '<!DOCTYPE html><html><body>' . $html . '</body></html>';
        if (!@$document->loadHTML('<?xml encoding="utf-8" ?>' . $wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $assetPaths = [];

        foreach (['src', 'href'] as $attributeName) {
            foreach ($xpath->query(sprintf('//*[@%s]', $attributeName)) as $node) {
                if (!$node instanceof DOMElement) {
                    continue;
                }

                $assetPath = $this->normalizeProjectEditorAssetPath($node->getAttribute($attributeName));
                if ($assetPath !== null) {
                    $assetPaths[$assetPath] = true;
                }
            }
        }

        return array_keys($assetPaths);
    }

    private function normalizeProjectEditorAssetPath(string $value): ?string
    {
        $normalizedValue = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($normalizedValue === '' || str_starts_with(strtolower($normalizedValue), 'data:')) {
            return null;
        }

        $path = parse_url($normalizedValue, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $normalizedValue;
        }

        $path = rawurldecode(str_replace('\\', '/', $path));
        if (!preg_match('#(?:^|/)(uploads/(?:images|files)/editor/[^?#]+)$#i', $path, $matches)) {
            return null;
        }

        return ltrim($matches[1], '/');
    }

    public function extractSectionsFromDocx(string $docxPath): array
    {
        if (!is_file($docxPath)) {
            throw new \RuntimeException('Document Word introuvable.');
        }

        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            throw new \RuntimeException('Impossible d ouvrir le document Word.');
        }

        try {
            $documentXml = $zip->getFromName('word/document.xml');
            if (!is_string($documentXml) || trim($documentXml) === '') {
                throw new \RuntimeException('Le contenu du document Word est introuvable.');
            }

            $documentRelsXml = $zip->getFromName('word/_rels/document.xml.rels');
            $mediaFiles = $this->extractDocumentMediaFiles($zip);
        } finally {
            $zip->close();
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = false;
        $document->formatOutput = false;
        if (!@$document->loadXML($documentXml)) {
            throw new \RuntimeException('Le document Word ne peut pas être lu.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::WORD_NS);
        $xpath->registerNamespace('r', self::REL_NS);
        $xpath->registerNamespace('a', self::DRAWING_NS);
        $xpath->registerNamespace('pic', self::PIC_NS);
        $xpath->registerNamespace('wp', self::WP_NS);

        $relationships = $this->extractDocumentRelationships($documentRelsXml);
        $sections = $this->extractSectionsFromContentControls($xpath, $relationships, $mediaFiles);

        if ($this->areAllSectionsEmpty($sections)) {
            $sections = $this->extractSectionsFromHeadingFallback($xpath, $relationships, $mediaFiles);
        }

        return $sections;
    }

    public function normalizeImportedSections(array $sections): array
    {
        $normalized = [];
        foreach (self::SECTION_DEFINITIONS as $fieldName => $definition) {
            $value = trim((string) ($sections[$fieldName] ?? ''));
            $normalized[$fieldName] = $value !== '' ? $value : null;
        }

        return $normalized;
    }

    private function buildContentTypesXml(array $mediaRegistry): string
    {
        $defaults = [
            '  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>',
            '  <Default Extension="xml" ContentType="application/xml"/>',
        ];

        $seenExtensions = [];
        foreach ($mediaRegistry as $mediaFile) {
            $extension = strtolower((string) ($mediaFile['extension'] ?? ''));
            $contentType = (string) ($mediaFile['contentType'] ?? '');
            if ($extension === '' || $contentType === '' || isset($seenExtensions[$extension])) {
                continue;
            }

            $seenExtensions[$extension] = true;
            $defaults[] = sprintf(
                '  <Default Extension="%s" ContentType="%s"/>',
                $this->xmlEscape($extension),
                $this->xmlEscape($contentType)
            );
        }

        return "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n"
            . "<Types xmlns=\"http://schemas.openxmlformats.org/package/2006/content-types\">\n"
            . implode("\n", $defaults) . "\n"
            . "  <Override PartName=\"/docProps/app.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.extended-properties+xml\"/>\n"
            . "  <Override PartName=\"/docProps/core.xml\" ContentType=\"application/vnd.openxmlformats-package.core-properties+xml\"/>\n"
            . "  <Override PartName=\"/word/document.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml\"/>\n"
            . "  <Override PartName=\"/word/styles.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml\"/>\n"
            . "  <Override PartName=\"/word/settings.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml\"/>\n"
            . "  <Override PartName=\"/word/numbering.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml\"/>\n"
            . "</Types>\n";
    }

    private function buildRootRelationshipsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
XML;
    }

    private function buildAppPropertiesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <Application>Dashboard ADEP</Application>
</Properties>
XML;
    }

    private function buildDocxPackage(array $project): array
    {
        $mediaRegistry = [];
        $documentXml = $this->buildDocumentXml($project, $mediaRegistry);

        return [
            'contentTypesXml' => $this->buildContentTypesXml($mediaRegistry),
            'documentXml' => $documentXml,
            'documentRelationshipsXml' => $this->buildDocumentRelationshipsXml($mediaRegistry),
            'mediaFiles' => array_values($mediaRegistry),
        ];
    }

    private function buildCorePropertiesXml(array $project): string
    {
        $title = $this->xmlEscape('CDC - ' . $this->projectReference($project));
        $description = $this->xmlEscape('Cahier des charges du projet ' . $this->projectDocumentTitle($project));
        $createdAt = gmdate('Y-m-d\TH:i:s\Z');

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <dc:title>{$title}</dc:title>
  <dc:creator>Dashboard ADEP</dc:creator>
  <dc:description>{$description}</dc:description>
  <cp:lastModifiedBy>Dashboard ADEP</cp:lastModifiedBy>
  <dcterms:created xsi:type="dcterms:W3CDTF">{$createdAt}</dcterms:created>
  <dcterms:modified xsi:type="dcterms:W3CDTF">{$createdAt}</dcterms:modified>
</cp:coreProperties>
XML;
    }

    private function buildDocumentRelationshipsXml(array $mediaRegistry): string
    {
        $relationships = [
            '  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>',
            '  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>',
            '  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>',
        ];

        foreach ($mediaRegistry as $mediaFile) {
            $relationships[] = sprintf(
                '  <Relationship Id="%s" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="%s"/>',
                $this->xmlEscape((string) $mediaFile['relationshipId']),
                $this->xmlEscape((string) $mediaFile['target'])
            );
        }

        return "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n"
            . "<Relationships xmlns=\"http://schemas.openxmlformats.org/package/2006/relationships\">\n"
            . implode("\n", $relationships)
            . "\n</Relationships>\n";
    }

    private function buildStylesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:docDefaults>
    <w:rPrDefault>
      <w:rPr>
        <w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/>
        <w:sz w:val="22"/>
        <w:szCs w:val="22"/>
        <w:lang w:val="fr-FR"/>
      </w:rPr>
    </w:rPrDefault>
    <w:pPrDefault>
      <w:pPr>
        <w:spacing w:after="140" w:line="276" w:lineRule="auto"/>
      </w:pPr>
    </w:pPrDefault>
  </w:docDefaults>
  <w:style w:type="paragraph" w:default="1" w:styleId="Normal">
    <w:name w:val="Normal"/>
    <w:qFormat/>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Title">
    <w:name w:val="Title"/>
    <w:basedOn w:val="Normal"/>
    <w:qFormat/>
    <w:pPr>
      <w:spacing w:after="120" w:line="276" w:lineRule="auto"/>
    </w:pPr>
    <w:rPr>
      <w:b/>
      <w:sz w:val="34"/>
      <w:szCs w:val="34"/>
      <w:color w:val="1F2937"/>
    </w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Subtitle">
    <w:name w:val="Subtitle"/>
    <w:basedOn w:val="Normal"/>
    <w:qFormat/>
    <w:rPr>
      <w:color w:val="4B5563"/>
      <w:sz w:val="20"/>
      <w:szCs w:val="20"/>
    </w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading1">
    <w:name w:val="heading 1"/>
    <w:basedOn w:val="Normal"/>
    <w:next w:val="Normal"/>
    <w:qFormat/>
    <w:pPr>
      <w:spacing w:before="260" w:after="120" w:line="276" w:lineRule="auto"/>
    </w:pPr>
    <w:rPr>
      <w:b/>
      <w:sz w:val="28"/>
      <w:szCs w:val="28"/>
      <w:color w:val="1F2937"/>
    </w:rPr>
  </w:style>
</w:styles>
XML;
    }

    private function buildSettingsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:zoom w:percent="100"/>
  <w:defaultTabStop w:val="720"/>
  <w:characterSpacingControl w:val="doNotCompress"/>
</w:settings>
XML;
    }

    private function buildNumberingXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="0">
    <w:multiLevelType w:val="hybridMultilevel"/>
    <w:lvl w:ilvl="0">
      <w:start w:val="1"/>
      <w:numFmt w:val="bullet"/>
      <w:lvlText w:val="•"/>
      <w:lvlJc w:val="left"/>
      <w:pPr>
        <w:ind w:left="720" w:hanging="360"/>
      </w:pPr>
    </w:lvl>
  </w:abstractNum>
  <w:abstractNum w:abstractNumId="1">
    <w:multiLevelType w:val="hybridMultilevel"/>
    <w:lvl w:ilvl="0">
      <w:start w:val="1"/>
      <w:numFmt w:val="decimal"/>
      <w:lvlText w:val="%1."/>
      <w:lvlJc w:val="left"/>
      <w:pPr>
        <w:ind w:left="720" w:hanging="360"/>
      </w:pPr>
    </w:lvl>
  </w:abstractNum>
  <w:num w:numId="1">
    <w:abstractNumId w:val="0"/>
  </w:num>
  <w:num w:numId="2">
    <w:abstractNumId w:val="1"/>
  </w:num>
</w:numbering>
XML;
    }

    private function buildDocumentXml(array $project, array &$mediaRegistry): string
    {
        $bodyBlocks = [
            $this->buildParagraphXml(
                [
                    ['text' => 'CDC - Projet : ', 'bold' => true, 'color' => '2C5B89', 'size' => 46],
                    ['text' => $this->projectDocumentTitle($project), 'bold' => true, 'color' => '7A9B34', 'size' => 46],
                ],
                null,
                null,
                ['align' => 'center', 'spacingAfter' => 460]
            ),
            $this->buildProjectSummaryTableXml($project),
            $this->buildParagraphXml([], null, null, ['spacingAfter' => 240]),
        ];

        $sectionIndex = 1;
        foreach (self::SECTION_DEFINITIONS as $fieldName => $definition) {
            $bodyBlocks[] = $this->buildParagraphXml(
                [[
                    'text' => $sectionIndex . '. ' . $definition['title'],
                    'bold' => true,
                    'color' => '2C5B89',
                    'size' => 30,
                ]],
                null,
                null,
                ['spacingBefore' => $sectionIndex === 1 ? 240 : 300, 'spacingAfter' => 150]
            );
            $bodyBlocks[] = $this->buildSectionContentControlXml(
                $definition['tag'],
                $definition['title'],
                (string) ($project[$fieldName] ?? ''),
                $mediaRegistry
            );
            $sectionIndex++;
        }

        $bodyBlocks[] = <<<'XML'
<w:sectPr>
  <w:pgSz w:w="11906" w:h="16838"/>
  <w:pgMar w:top="1080" w:right="1080" w:bottom="1080" w:left="1080" w:header="708" w:footer="708" w:gutter="0"/>
</w:sectPr>
XML;

        $body = implode('', $bodyBlocks);

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document
    xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
    xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
    xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
    xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
    xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
  <w:body>{$body}</w:body>
</w:document>
XML;

        $title = $this->buildParagraphXml(
            [['text' => 'Cahier des charges - ' . $this->projectTitle($project)]],
            'Title'
        );

        $subtitleLines = array_filter([
            'Projet : ' . $this->projectReference($project),
            trim((string) ($project['service'] ?? '')) !== '' ? 'Service : ' . trim((string) $project['service']) : null,
            trim((string) ($project['projectManager'] ?? '')) !== '' ? 'Chef de projet : ' . trim((string) $project['projectManager']) : null,
            'Date de génération : ' . date('d/m/Y H:i'),
        ]);

        $subtitle = $this->buildParagraphXml(
            [['text' => implode(' | ', $subtitleLines)]],
            'Subtitle'
        );

        $bodyBlocks = [$title, $subtitle];

        foreach (self::SECTION_DEFINITIONS as $fieldName => $definition) {
            $bodyBlocks[] = $this->buildParagraphXml(
                [['text' => $definition['title']]],
                'Heading1'
            );
            $bodyBlocks[] = $this->buildSectionContentControlXml(
                $definition['tag'],
                $definition['title'],
                (string) ($project[$fieldName] ?? '')
            );
        }

        $bodyBlocks[] = <<<'XML'
<w:sectPr>
  <w:pgSz w:w="11906" w:h="16838"/>
  <w:pgMar w:top="1440" w:right="1134" w:bottom="1440" w:left="1134" w:header="708" w:footer="708" w:gutter="0"/>
</w:sectPr>
XML;

        $body = implode('', $bodyBlocks);

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:body>{$body}</w:body>
</w:document>
XML;
    }

    private function buildSectionContentControlXml(string $tag, string $title, string $html, array &$mediaRegistry): string
    {
        $blocks = $this->htmlToWordBlocks($html, $mediaRegistry);
        if ($blocks === []) {
            $blocks[] = $this->buildParagraphXml([]);
        }

        $content = implode('', $blocks);
        $alias = $this->xmlEscape($title);
        $tagValue = $this->xmlEscape($tag);
        $id = (string) (abs(crc32($tag)) % 1000000 + 1000);

        return <<<XML
<w:sdt>
  <w:sdtPr>
    <w:alias w:val="{$alias}"/>
    <w:tag w:val="{$tagValue}"/>
    <w:id w:val="{$id}"/>
  </w:sdtPr>
  <w:sdtContent>{$content}</w:sdtContent>
</w:sdt>
XML;
    }

    private function buildProjectSummaryTableXml(array $project): string
    {
        return $this->buildContainedProjectSummaryTableXml($project);

        $columns = [
            ['label' => 'Demandeur', 'value' => $this->projectRequester($project), 'width' => 2200],
            ['label' => 'Date de la demande', 'value' => $this->projectRequestDateLabel($project), 'width' => 2400],
            ['label' => 'Échéance souhaitée', 'value' => $this->projectRequestedDueDateLabel($project), 'width' => 2600],
            ['label' => 'Priorité', 'value' => $this->projectSummaryText($project, 'cdcPriority'), 'width' => 1950],
            ['label' => 'Service', 'value' => $this->projectSummaryText($project, 'cdcService'), 'width' => 2200],
        ];

        $grid = [];
        $headerCells = [];
        $valueCells = [];

        foreach ($columns as $index => $column) {
            $width = (int) $column['width'];
            $grid[] = '<w:gridCol w:w="' . $width . '"/>';
            $headerCells[] = $this->buildProjectSummaryCellXml(
                $column['label'],
                $width,
                true,
                $index === 0,
                $index === count($columns) - 1
            );
            $valueCells[] = $this->buildProjectSummaryCellXml(
                $column['value'],
                $width,
                false,
                $index === 0,
                $index === count($columns) - 1
            );
        }

        return '<w:tbl>'
            . '<w:tblPr>'
            . '<w:tblW w:w="0" w:type="auto"/>'
            . '<w:tblLayout w:type="fixed"/>'
            . '<w:tblBorders>'
            . '<w:top w:val="single" w:sz="8" w:space="0" w:color="000000"/>'
            . '<w:left w:val="single" w:sz="8" w:space="0" w:color="000000"/>'
            . '<w:bottom w:val="single" w:sz="8" w:space="0" w:color="000000"/>'
            . '<w:right w:val="single" w:sz="8" w:space="0" w:color="000000"/>'
            . '<w:insideH w:val="single" w:sz="8" w:space="0" w:color="000000"/>'
            . '<w:insideV w:val="single" w:sz="8" w:space="0" w:color="000000"/>'
            . '</w:tblBorders>'
            . '</w:tblPr>'
            . '<w:tblGrid>' . implode('', $grid) . '</w:tblGrid>'
            . '<w:tr>' . implode('', $headerCells) . '</w:tr>'
            . '<w:tr>' . implode('', $valueCells) . '</w:tr>'
            . '</w:tbl>';
    }

    private function buildProjectSummaryCellXml(string $text, int $width, bool $header, bool $firstColumn, bool $lastColumn): string
    {
        $cellFill = $header ? '2C5B89' : 'FFFFFF';
        $textColor = $header ? 'FFFFFF' : '000000';
        $paragraph = $this->buildParagraphXml(
            [[
                'text' => $text,
                'bold' => $header,
                'color' => $textColor,
                'size' => $header ? 22 : 20,
            ]],
            null,
            null,
            ['align' => 'center', 'spacingAfter' => 0]
        );

        return '<w:tc>'
            . '<w:tcPr>'
            . '<w:tcW w:w="' . $width . '" w:type="dxa"/>'
            . '<w:shd w:val="clear" w:fill="' . $cellFill . '"/>'
            . '<w:vAlign w:val="center"/>'
            . ($firstColumn ? '<w:tcBorders><w:left w:val="single" w:sz="8" w:space="0" w:color="000000"/></w:tcBorders>' : '')
            . ($lastColumn ? '<w:tcBorders><w:right w:val="single" w:sz="8" w:space="0" w:color="000000"/></w:tcBorders>' : '')
            . '</w:tcPr>'
            . $paragraph
            . '</w:tc>';
    }

    private function buildContainedProjectSummaryTableXml(array $project): string
    {
        $tableWidth = 9746;
        $columns = [
            ['label' => 'Demandeur', 'value' => $this->projectRequester($project), 'width' => 1624],
            ['label' => 'Date de la demande', 'value' => $this->projectRequestDateLabel($project), 'width' => 1624],
            ['label' => 'Echeance souhaitee', 'value' => $this->projectRequestedDueDateLabel($project), 'width' => 1624],
            ['label' => 'Priorite', 'value' => $this->projectSummaryText($project, 'cdcPriority'), 'width' => 1624],
            ['label' => 'Service', 'value' => $this->projectSummaryText($project, 'cdcService'), 'width' => 1625],
            ['label' => 'Chef de projet', 'value' => $this->projectSummaryText($project, 'cdcProjectManager'), 'width' => 1625],
        ];

        $grid = [];
        $headerCells = [];
        $valueCells = [];

        foreach ($columns as $column) {
            $width = (int) $column['width'];
            $grid[] = '<w:gridCol w:w="' . $width . '"/>';
            $headerCells[] = $this->buildContainedProjectSummaryCellXml((string) $column['label'], $width, true);
            $valueCells[] = $this->buildContainedProjectSummaryCellXml((string) $column['value'], $width, false);
        }

        return '<w:tbl>'
            . '<w:tblPr>'
            . '<w:tblW w:w="' . $tableWidth . '" w:type="dxa"/>'
            . '<w:tblLayout w:type="fixed"/>'
            . '<w:jc w:val="center"/>'
            . '<w:tblCellMar>'
            . '<w:top w:w="90" w:type="dxa"/>'
            . '<w:left w:w="90" w:type="dxa"/>'
            . '<w:bottom w:w="90" w:type="dxa"/>'
            . '<w:right w:w="90" w:type="dxa"/>'
            . '</w:tblCellMar>'
            . '<w:tblBorders>'
            . '<w:top w:val="single" w:sz="8" w:space="0" w:color="000000"/>'
            . '<w:left w:val="single" w:sz="8" w:space="0" w:color="000000"/>'
            . '<w:bottom w:val="single" w:sz="8" w:space="0" w:color="000000"/>'
            . '<w:right w:val="single" w:sz="8" w:space="0" w:color="000000"/>'
            . '<w:insideH w:val="single" w:sz="8" w:space="0" w:color="000000"/>'
            . '<w:insideV w:val="single" w:sz="8" w:space="0" w:color="000000"/>'
            . '</w:tblBorders>'
            . '</w:tblPr>'
            . '<w:tblGrid>' . implode('', $grid) . '</w:tblGrid>'
            . '<w:tr>' . implode('', $headerCells) . '</w:tr>'
            . '<w:tr>' . implode('', $valueCells) . '</w:tr>'
            . '</w:tbl>';
    }

    private function buildContainedProjectSummaryCellXml(string $text, int $width, bool $header): string
    {
        $cellFill = $header ? '2C5B89' : 'FFFFFF';
        $textColor = $header ? 'FFFFFF' : '000000';
        $paragraph = $this->buildParagraphXml(
            [[
                'text' => $text,
                'bold' => $header,
                'color' => $textColor,
                'size' => $header ? 22 : 20,
            ]],
            null,
            null,
            ['align' => 'center', 'spacingAfter' => 0]
        );

        return '<w:tc>'
            . '<w:tcPr>'
            . '<w:tcW w:w="' . $width . '" w:type="dxa"/>'
            . '<w:shd w:val="clear" w:fill="' . $cellFill . '"/>'
            . '<w:vAlign w:val="center"/>'
            . '</w:tcPr>'
            . $paragraph
            . '</w:tc>';
    }

    private function buildInlineImageDrawingXml(string $relationshipId, int $widthEmu, int $heightEmu, string $name, string $alt): string
    {
        $safeName = $this->xmlEscape($name !== '' ? $name : 'Image');
        $safeAlt = $this->xmlEscape($alt);
        $widthEmu = max(1, $widthEmu);
        $heightEmu = max(1, $heightEmu);
        $documentPropertyId = abs(crc32($relationshipId . $name)) % 100000 + 1;

        return <<<XML
<w:drawing>
  <wp:inline distT="0" distB="0" distL="0" distR="0">
    <wp:extent cx="{$widthEmu}" cy="{$heightEmu}"/>
    <wp:effectExtent l="0" t="0" r="0" b="0"/>
    <wp:docPr id="{$documentPropertyId}" name="{$safeName}" descr="{$safeAlt}"/>
    <wp:cNvGraphicFramePr>
      <a:graphicFrameLocks noChangeAspect="1"/>
    </wp:cNvGraphicFramePr>
    <a:graphic>
      <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">
        <pic:pic>
          <pic:nvPicPr>
            <pic:cNvPr id="0" name="{$safeName}" descr="{$safeAlt}"/>
            <pic:cNvPicPr/>
          </pic:nvPicPr>
          <pic:blipFill>
            <a:blip r:embed="{$relationshipId}"/>
            <a:stretch><a:fillRect/></a:stretch>
          </pic:blipFill>
          <pic:spPr>
            <a:xfrm>
              <a:off x="0" y="0"/>
              <a:ext cx="{$widthEmu}" cy="{$heightEmu}"/>
            </a:xfrm>
            <a:prstGeom prst="rect"><a:avLst/></a:prstGeom>
          </pic:spPr>
        </pic:pic>
      </a:graphicData>
    </a:graphic>
  </wp:inline>
</w:drawing>
XML;
    }

    private function htmlToWordBlocks(string $html, array &$mediaRegistry): array
    {
        $html = trim($html);
        if ($html === '') {
            return [];
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = false;
        $document->formatOutput = false;

        $wrappedHtml = '<!DOCTYPE html><html><body>' . $html . '</body></html>';
        @$document->loadHTML('<?xml encoding="utf-8" ?>' . $wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $body = $document->getElementsByTagName('body')->item(0);
        if (!$body instanceof DOMElement) {
            return [];
        }

        $blocks = [];
        foreach ($body->childNodes as $childNode) {
            foreach ($this->convertHtmlNodeToWordBlocks($childNode, $mediaRegistry) as $blockXml) {
                $blocks[] = $blockXml;
            }
        }

        return $blocks;
    }

    private function convertHtmlNodeToWordBlocks(DOMNode $node, array &$mediaRegistry): array
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = trim($node->textContent ?? '');
            if ($text === '') {
                return [];
            }

            return [$this->buildParagraphXml([['text' => $text]])];
        }

        if (!$node instanceof DOMElement) {
            return [];
        }

        $tagName = strtolower($node->tagName);

        if (in_array($tagName, ['ul', 'ol'], true)) {
            $ordered = $tagName === 'ol';
            $blocks = [];
            foreach ($node->childNodes as $itemNode) {
                if (!$itemNode instanceof DOMElement || strtolower($itemNode->tagName) !== 'li') {
                    continue;
                }

                $runs = $this->extractInlineRunsFromHtmlNode($itemNode, [], $mediaRegistry);
                $blocks[] = $this->buildParagraphXml($runs, null, [
                    'numId' => $ordered ? 2 : 1,
                    'level' => 0,
                ]);
            }

            return $blocks;
        }

        if (in_array($tagName, ['p', 'div', 'blockquote', 'section', 'article', 'aside'], true)) {
            if ($this->containsBlockLevelChildren($node)) {
                $blocks = [];
                foreach ($node->childNodes as $childNode) {
                    foreach ($this->convertHtmlNodeToWordBlocks($childNode, $mediaRegistry) as $blockXml) {
                        $blocks[] = $blockXml;
                    }
                }

                return $blocks;
            }

            return [$this->buildParagraphXml($this->extractInlineRunsFromHtmlNode($node, [], $mediaRegistry))];
        }

        if (preg_match('/^h[1-6]$/', $tagName) === 1) {
            return [$this->buildParagraphXml($this->extractInlineRunsFromHtmlNode($node, [], $mediaRegistry), 'Heading1')];
        }

        if ($tagName === 'br') {
            return [$this->buildParagraphXml([])];
        }

        return [$this->buildParagraphXml($this->extractInlineRunsFromHtmlNode($node, [], $mediaRegistry))];
    }

    private function containsBlockLevelChildren(DOMElement $node): bool
    {
        foreach ($node->childNodes as $childNode) {
            if (!$childNode instanceof DOMElement) {
                continue;
            }

            if (in_array(strtolower($childNode->tagName), ['p', 'div', 'ul', 'ol', 'li', 'table', 'section', 'article', 'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
                return true;
            }
        }

        return false;
    }

    private function extractInlineRunsFromHtmlNode(DOMNode $node, array $formatting = [], array &$mediaRegistry = []): array
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = preg_replace('/\s+/u', ' ', $node->textContent ?? '') ?? '';
            if ($text === '') {
                return [];
            }

            return [[
                'text' => $text,
                'bold' => !empty($formatting['bold']),
                'italic' => !empty($formatting['italic']),
                'underline' => !empty($formatting['underline']),
            ]];
        }

        if (!$node instanceof DOMElement) {
            return [];
        }

        $tagName = strtolower($node->tagName);

        if ($tagName === 'br') {
            return [['break' => true]];
        }

        if ($tagName === 'img') {
            $src = trim((string) $node->getAttribute('src'));
            if ($src === '') {
                return [];
            }

            $media = $this->registerImageMedia($src, $node, $mediaRegistry);
            if ($media === null) {
                return [];
            }

            return [[
                'image' => true,
                'relationshipId' => $media['relationshipId'],
                'widthEmu' => $media['widthEmu'],
                'heightEmu' => $media['heightEmu'],
                'alt' => trim((string) $node->getAttribute('alt')) ?: $media['fileName'],
                'name' => $media['fileName'],
            ]];
        }

        $nextFormatting = $formatting;
        if (in_array($tagName, ['strong', 'b'], true)) {
            $nextFormatting['bold'] = true;
        }
        if (in_array($tagName, ['em', 'i'], true)) {
            $nextFormatting['italic'] = true;
        }
        if ($tagName === 'u') {
            $nextFormatting['underline'] = true;
        }

        $runs = [];
        foreach ($node->childNodes as $childNode) {
            foreach ($this->extractInlineRunsFromHtmlNode($childNode, $nextFormatting, $mediaRegistry) as $run) {
                $runs[] = $run;
            }
        }

        if ($tagName === 'a') {
            $href = trim((string) $node->getAttribute('href'));
            if ($href !== '') {
                foreach ($runs as &$run) {
                    if (!isset($run['text'])) {
                        continue;
                    }

                    $run['underline'] = true;
                    $run['color'] = '1155CC';
                }
                unset($run);

                $linkLabel = trim($node->textContent ?? '');
                if ($linkLabel === '' || trim($linkLabel) !== $href) {
                    $runs[] = [
                        'text' => ' (' . $href . ')',
                        'underline' => false,
                        'italic' => false,
                        'bold' => false,
                    ];
                }
            }
        }

        return $runs;
    }

    private function buildParagraphXml(array $runs, ?string $styleId = null, ?array $numbering = null, array $options = []): string
    {
        $paragraphProperties = '';
        if ($styleId !== null && $styleId !== '') {
            $paragraphProperties .= '<w:pStyle w:val="' . $this->xmlEscape($styleId) . '"/>';
        }
        if (is_array($numbering)) {
            $level = (string) max(0, (int) ($numbering['level'] ?? 0));
            $numId = (string) max(1, (int) ($numbering['numId'] ?? 1));
            $paragraphProperties .= '<w:numPr><w:ilvl w:val="' . $level . '"/><w:numId w:val="' . $numId . '"/></w:numPr>';
        }
        if (!empty($options['align'])) {
            $paragraphProperties .= '<w:jc w:val="' . $this->xmlEscape((string) $options['align']) . '"/>';
        }
        if (!empty($options['spacingBefore']) || !empty($options['spacingAfter'])) {
            $paragraphProperties .= '<w:spacing'
                . (!empty($options['spacingBefore']) ? ' w:before="' . (int) $options['spacingBefore'] . '"' : '')
                . (!empty($options['spacingAfter']) ? ' w:after="' . (int) $options['spacingAfter'] . '"' : '')
                . '/>';
        }

        $paragraphRuns = '';
        foreach ($runs as $run) {
            if (!empty($run['break'])) {
                $paragraphRuns .= '<w:r><w:br/></w:r>';
                continue;
            }

            if (!empty($run['image'])) {
                $paragraphRuns .= '<w:r>' . $this->buildInlineImageDrawingXml(
                    (string) ($run['relationshipId'] ?? ''),
                    (int) ($run['widthEmu'] ?? 0),
                    (int) ($run['heightEmu'] ?? 0),
                    (string) ($run['name'] ?? 'Image'),
                    (string) ($run['alt'] ?? '')
                ) . '</w:r>';
                continue;
            }

            $text = (string) ($run['text'] ?? '');
            if ($text === '') {
                continue;
            }

            $runProperties = '';
            if (!empty($run['bold'])) {
                $runProperties .= '<w:b/>';
            }
            if (!empty($run['italic'])) {
                $runProperties .= '<w:i/>';
            }
            if (!empty($run['underline'])) {
                $runProperties .= '<w:u w:val="single"/>';
            }
            if (!empty($run['color'])) {
                $runProperties .= '<w:color w:val="' . $this->xmlEscape((string) $run['color']) . '"/>';
            } elseif ($styleId === null || $styleId === '') {
                $runProperties .= '<w:color w:val="000000"/>';
            }
            if (!empty($run['size'])) {
                $fontSize = (int) $run['size'];
                $runProperties .= '<w:sz w:val="' . $fontSize . '"/><w:szCs w:val="' . $fontSize . '"/>';
            }

            $textAttributes = preg_match('/^\s|\s$/u', $text) === 1 ? ' xml:space="preserve"' : '';
            $paragraphRuns .= '<w:r>';
            if ($runProperties !== '') {
                $paragraphRuns .= '<w:rPr>' . $runProperties . '</w:rPr>';
            }
            $paragraphRuns .= '<w:t' . $textAttributes . '>' . $this->xmlEscape($text) . '</w:t></w:r>';
        }

        if ($paragraphRuns === '') {
            $paragraphRuns = '<w:r><w:t></w:t></w:r>';
        }

        if ($paragraphProperties !== '') {
            $paragraphProperties = '<w:pPr>' . $paragraphProperties . '</w:pPr>';
        }

        return '<w:p>' . $paragraphProperties . $paragraphRuns . '</w:p>';
    }

    private function registerImageMedia(string $source, DOMElement $imageNode, array &$mediaRegistry): ?array
    {
        $resolvedImage = $this->resolveHtmlImageSource($source);
        if ($resolvedImage === null) {
            return null;
        }

        $hash = sha1($resolvedImage['content']);
        if (isset($mediaRegistry[$hash])) {
            return $mediaRegistry[$hash];
        }

        $dimensions = @getimagesizefromstring($resolvedImage['content']);
        if (!is_array($dimensions) || empty($dimensions[0]) || empty($dimensions[1])) {
            return null;
        }

        $targetWidthPx = (int) $dimensions[0];
        $targetHeightPx = (int) $dimensions[1];
        $widthHint = $this->extractHtmlImageDimensionHint($imageNode, 'width');
        $heightHint = $this->extractHtmlImageDimensionHint($imageNode, 'height');

        if ($widthHint !== null && $widthHint > 0) {
            $ratio = $targetWidthPx > 0 ? ($targetHeightPx / $targetWidthPx) : 1;
            $targetWidthPx = $widthHint;
            $targetHeightPx = $heightHint !== null && $heightHint > 0
                ? $heightHint
                : (int) max(1, round($targetWidthPx * $ratio));
        } elseif ($heightHint !== null && $heightHint > 0) {
            $ratio = $targetHeightPx > 0 ? ($targetWidthPx / $targetHeightPx) : 1;
            $targetHeightPx = $heightHint;
            $targetWidthPx = (int) max(1, round($targetHeightPx * $ratio));
        }

        $maxWidthPx = 680;
        if ($targetWidthPx > $maxWidthPx) {
            $ratio = $targetHeightPx > 0 ? ($targetHeightPx / $targetWidthPx) : 1;
            $targetWidthPx = $maxWidthPx;
            $targetHeightPx = (int) max(1, round($targetWidthPx * $ratio));
        }

        $relationshipId = 'rIdImg' . (count($mediaRegistry) + 10);
        $mediaFile = [
            'relationshipId' => $relationshipId,
            'target' => 'media/' . $hash . '.' . $resolvedImage['extension'],
            'extension' => $resolvedImage['extension'],
            'contentType' => $resolvedImage['contentType'],
            'content' => $resolvedImage['content'],
            'widthEmu' => (int) max(1, round($targetWidthPx * 9525)),
            'heightEmu' => (int) max(1, round($targetHeightPx * 9525)),
            'fileName' => $resolvedImage['fileName'],
        ];

        $mediaRegistry[$hash] = $mediaFile;

        return $mediaFile;
    }

    private function resolveHtmlImageSource(string $source): ?array
    {
        $normalizedSource = trim($source);
        if ($normalizedSource === '') {
            return null;
        }

        if (preg_match('#^data:(image/[^;]+);base64,(.+)$#', $normalizedSource, $matches) === 1) {
            $content = base64_decode($matches[2], true);
            if ($content === false) {
                return null;
            }

            $extension = $this->mapImageMimeToExtension(strtolower($matches[1]));

            return [
                'content' => $content,
                'contentType' => strtolower($matches[1]),
                'extension' => $extension,
                'fileName' => 'image.' . $extension,
            ];
        }

        $publicPathCandidates = [];
        if (preg_match('#^https?://#i', $normalizedSource) === 1) {
            $path = (string) parse_url($normalizedSource, PHP_URL_PATH);
            if ($path !== '') {
                $publicPathCandidates[] = $path;
            }
        } else {
            $publicPathCandidates[] = $normalizedSource;
        }

        foreach ($publicPathCandidates as $candidate) {
            $resolvedPath = $this->resolveImagePublicPathToFile($candidate);
            if ($resolvedPath !== null && is_file($resolvedPath)) {
                $content = @file_get_contents($resolvedPath);
                if (!is_string($content) || $content === '') {
                    continue;
                }

                $extension = strtolower((string) pathinfo($resolvedPath, PATHINFO_EXTENSION));
                $contentType = $this->mapImageExtensionToMime($extension);

                return [
                    'content' => $content,
                    'contentType' => $contentType,
                    'extension' => $extension !== '' ? $extension : $this->mapImageMimeToExtension($contentType),
                    'fileName' => basename($resolvedPath),
                ];
            }
        }

        if (is_file($normalizedSource)) {
            $content = @file_get_contents($normalizedSource);
            if (!is_string($content) || $content === '') {
                return null;
            }

            $extension = strtolower((string) pathinfo($normalizedSource, PATHINFO_EXTENSION));
            $contentType = $this->mapImageExtensionToMime($extension);

            return [
                'content' => $content,
                'contentType' => $contentType,
                'extension' => $extension !== '' ? $extension : $this->mapImageMimeToExtension($contentType),
                'fileName' => basename($normalizedSource),
            ];
        }

        return null;
    }

    private function resolveImagePublicPathToFile(string $publicPath): ?string
    {
        $normalizedPath = trim($publicPath);
        if ($normalizedPath === '') {
            return null;
        }

        $normalizedPath = str_replace('\\', '/', $normalizedPath);
        $normalizedPath = preg_replace('#^https?://[^/]+#i', '', $normalizedPath) ?? $normalizedPath;
        $normalizedPath = preg_replace('#^/dashboard/#', '/', $normalizedPath) ?? $normalizedPath;
        $normalizedPath = ltrim($normalizedPath, '/');

        if ($normalizedPath === '') {
            return null;
        }

        $candidatePath = $this->kernel->getProjectDir() . '/public/' . $normalizedPath;

        return is_file($candidatePath) ? $candidatePath : null;
    }

    private function extractHtmlImageDimensionHint(DOMElement $imageNode, string $dimension): ?int
    {
        $attributeValue = trim((string) $imageNode->getAttribute($dimension));
        if ($attributeValue !== '' && ctype_digit(preg_replace('/\D+/', '', $attributeValue) ?? '')) {
            return (int) preg_replace('/\D+/', '', $attributeValue);
        }

        $style = strtolower(trim((string) $imageNode->getAttribute('style')));
        if ($style !== '' && preg_match('/' . preg_quote($dimension, '/') . '\s*:\s*(\d+)px/', $style, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private function mapImageExtensionToMime(string $extension): string
    {
        return match (strtolower($extension)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }

    private function mapImageMimeToExtension(string $contentType): string
    {
        return match (strtolower($contentType)) {
            'image/jpeg', 'image/jpg' => 'jpeg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'png',
        };
    }

    private function extractDocumentRelationships(string|false $relationshipsXml): array
    {
        if (!is_string($relationshipsXml) || trim($relationshipsXml) === '') {
            return [];
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        if (!@$document->loadXML($relationshipsXml)) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('pr', self::PKG_REL_NS);

        $relationships = [];
        foreach ($xpath->query('/pr:Relationships/pr:Relationship') as $relationshipNode) {
            if (!$relationshipNode instanceof DOMElement) {
                continue;
            }

            $id = trim($relationshipNode->getAttribute('Id'));
            $target = trim($relationshipNode->getAttribute('Target'));
            $mode = trim($relationshipNode->getAttribute('TargetMode'));

            if ($id === '' || $target === '') {
                continue;
            }

            $relationships[$id] = [
                'target' => $target,
                'external' => strcasecmp($mode, 'External') === 0,
            ];
        }

        return $relationships;
    }

    private function extractDocumentMediaFiles(ZipArchive $zip): array
    {
        $mediaFiles = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            if (!is_array($stat)) {
                continue;
            }

            $entryName = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
            if (!str_starts_with($entryName, 'word/media/') || str_ends_with($entryName, '/')) {
                continue;
            }

            $content = $zip->getFromIndex($index);
            if (!is_string($content) || $content === '') {
                continue;
            }

            $relativeTarget = substr($entryName, strlen('word/'));
            $extension = strtolower((string) pathinfo($entryName, PATHINFO_EXTENSION));
            $contentType = $this->mapImageExtensionToMime($extension);

            $mediaFiles[$relativeTarget] = [
                'content' => $content,
                'contentType' => $contentType,
                'extension' => $extension !== '' ? $extension : $this->mapImageMimeToExtension($contentType),
                'fileName' => basename($entryName),
            ];
        }

        return $mediaFiles;
    }

    private function extractSectionsFromContentControls(DOMXPath $xpath, array $relationships, array $mediaFiles): array
    {
        $sections = [];

        foreach (self::SECTION_DEFINITIONS as $fieldName => $definition) {
            $query = sprintf('//w:sdt[w:sdtPr/w:tag[@w:val="%s"]]/w:sdtContent', $definition['tag']);
            $sectionNode = $xpath->query($query)->item(0);
            $sections[$fieldName] = $sectionNode instanceof DOMElement
                ? $this->convertWordContainerToHtml($sectionNode, $xpath, $relationships, $mediaFiles)
                : null;
        }

        return $sections;
    }

    private function extractSectionsFromHeadingFallback(DOMXPath $xpath, array $relationships, array $mediaFiles): array
    {
        $bodyNode = $xpath->query('/w:document/w:body')->item(0);
        if (!$bodyNode instanceof DOMElement) {
            return $this->normalizeImportedSections([]);
        }

        $titleMap = [];
        $sectionIndex = 1;
        foreach (self::SECTION_DEFINITIONS as $fieldName => $definition) {
            $titleMap[$definition['title']] = $fieldName;
            $titleMap[$sectionIndex . '. ' . $definition['title']] = $fieldName;
            $sectionIndex++;
        }

        $buffers = [];
        $currentField = null;

        foreach ($bodyNode->childNodes as $childNode) {
            if (!$childNode instanceof DOMElement) {
                continue;
            }

            if ($childNode->localName !== 'p') {
                continue;
            }

            $paragraphText = trim($this->extractParagraphPlainText($childNode, $xpath, $relationships));
            if (isset($titleMap[$paragraphText])) {
                $currentField = $titleMap[$paragraphText];
                $buffers[$currentField] ??= [];
                continue;
            }

            if ($currentField !== null) {
                $buffers[$currentField][] = $childNode;
            }
        }

        $sections = [];
        foreach (self::SECTION_DEFINITIONS as $fieldName => $definition) {
            $sections[$fieldName] = $this->convertWordParagraphsToHtml($buffers[$fieldName] ?? [], $xpath, $relationships, $mediaFiles);
        }

        return $sections;
    }

    private function convertWordContainerToHtml(DOMElement $containerNode, DOMXPath $xpath, array $relationships, array $mediaFiles): ?string
    {
        $paragraphs = [];
        foreach ($containerNode->childNodes as $childNode) {
            if ($childNode instanceof DOMElement && $childNode->localName === 'p') {
                $paragraphs[] = $childNode;
            }
        }

        return $this->convertWordParagraphsToHtml($paragraphs, $xpath, $relationships, $mediaFiles);
    }

    private function convertWordParagraphsToHtml(array $paragraphNodes, DOMXPath $xpath, array $relationships, array $mediaFiles): ?string
    {
        $htmlParts = [];
        $openListType = null;
        $listItems = [];

        $flushList = function () use (&$htmlParts, &$openListType, &$listItems): void {
            if ($openListType === null || $listItems === []) {
                $openListType = null;
                $listItems = [];
                return;
            }

            $tagName = $openListType === 'ol' ? 'ol' : 'ul';
            $htmlParts[] = '<' . $tagName . '><li>' . implode('</li><li>', $listItems) . '</li></' . $tagName . '>';
            $openListType = null;
            $listItems = [];
        };

        foreach ($paragraphNodes as $paragraphNode) {
            if (!$paragraphNode instanceof DOMElement) {
                continue;
            }

            $numberingType = $this->extractParagraphNumberingType($paragraphNode, $xpath);
            $paragraphHtml = $this->extractParagraphInlineHtml($paragraphNode, $xpath, $relationships, $mediaFiles);

            if ($numberingType !== null) {
                if ($openListType !== null && $openListType !== $numberingType) {
                    $flushList();
                }

                $openListType = $openListType ?? $numberingType;
                $listItems[] = $paragraphHtml !== '' ? $paragraphHtml : '&nbsp;';
                continue;
            }

            $flushList();

            if ($paragraphHtml === '') {
                continue;
            }

            $htmlParts[] = '<p>' . $paragraphHtml . '</p>';
        }

        $flushList();

        $html = trim(implode('', $htmlParts));

        return $html !== '' ? $html : null;
    }

    private function extractParagraphNumberingType(DOMElement $paragraphNode, DOMXPath $xpath): ?string
    {
        $numIdNode = $xpath->query('./w:pPr/w:numPr/w:numId', $paragraphNode)->item(0);
        if (!$numIdNode instanceof DOMElement) {
            return null;
        }

        $numId = trim($numIdNode->getAttributeNS(self::WORD_NS, 'val'));
        if ($numId === '') {
            $numId = trim($numIdNode->getAttribute('w:val'));
        }

        if ($numId === '') {
            return null;
        }

        return $numId === '2' ? 'ol' : 'ul';
    }

    private function extractParagraphInlineHtml(DOMElement $paragraphNode, DOMXPath $xpath, array $relationships, array $mediaFiles): string
    {
        $parts = [];

        foreach ($paragraphNode->childNodes as $childNode) {
            if (!$childNode instanceof DOMElement) {
                continue;
            }

            if ($childNode->localName === 'r') {
                $parts[] = $this->renderWordRunToHtml($childNode, $xpath, $relationships, $mediaFiles);
                continue;
            }

            if ($childNode->localName === 'hyperlink') {
                $parts[] = $this->renderWordHyperlinkToHtml($childNode, $xpath, $relationships, $mediaFiles);
            }
        }

        return trim(implode('', array_filter($parts, static fn (string $part): bool => $part !== '')));
    }

    private function renderWordHyperlinkToHtml(DOMElement $hyperlinkNode, DOMXPath $xpath, array $relationships, array $mediaFiles): string
    {
        $relationId = trim($hyperlinkNode->getAttributeNS(self::REL_NS, 'id'));
        if ($relationId === '') {
            $relationId = trim($hyperlinkNode->getAttribute('r:id'));
        }

        $href = '';
        if ($relationId !== '' && isset($relationships[$relationId])) {
            $href = (string) ($relationships[$relationId]['target'] ?? '');
        }

        $content = '';
        foreach ($hyperlinkNode->childNodes as $childNode) {
            if ($childNode instanceof DOMElement && $childNode->localName === 'r') {
                $content .= $this->renderWordRunToHtml($childNode, $xpath, $relationships, $mediaFiles);
            }
        }

        if ($content === '') {
            return '';
        }

        if ($href === '') {
            return $content;
        }

        return '<a href="' . htmlspecialchars($href, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '">' . $content . '</a>';
    }

    private function renderWordRunToHtml(DOMElement $runNode, DOMXPath $xpath, array $relationships, array $mediaFiles): string
    {
        $bold = $xpath->query('./w:rPr/w:b', $runNode)->length > 0;
        $italic = $xpath->query('./w:rPr/w:i', $runNode)->length > 0;
        $underline = $xpath->query('./w:rPr/w:u[@w:val!="none"] | ./w:rPr/w:u[not(@w:val)]', $runNode)->length > 0;

        $fragments = [];
        foreach ($runNode->childNodes as $childNode) {
            if (!$childNode instanceof DOMElement) {
                continue;
            }

            if ($childNode->localName === 't') {
                $fragments[] = htmlspecialchars($childNode->textContent ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                continue;
            }

            if ($childNode->localName === 'br') {
                $fragments[] = '<br>';
                continue;
            }

            if ($childNode->localName === 'drawing') {
                $fragments[] = $this->renderWordDrawingToHtml($childNode, $xpath, $relationships, $mediaFiles);
            }
        }

        $content = implode('', $fragments);
        if ($content === '') {
            return '';
        }

        if ($underline) {
            $content = '<u>' . $content . '</u>';
        }
        if ($italic) {
            $content = '<em>' . $content . '</em>';
        }
        if ($bold) {
            $content = '<strong>' . $content . '</strong>';
        }

        return $content;
    }

    private function renderWordDrawingToHtml(DOMElement $drawingNode, DOMXPath $xpath, array $relationships, array $mediaFiles): string
    {
        $blipNode = $xpath->query('.//a:blip', $drawingNode)->item(0);
        if (!$blipNode instanceof DOMElement) {
            return '';
        }

        $relationId = trim($blipNode->getAttributeNS(self::REL_NS, 'embed'));
        if ($relationId === '') {
            $relationId = trim($blipNode->getAttribute('r:embed'));
        }
        if ($relationId === '') {
            $relationId = trim($blipNode->getAttribute('embed'));
        }
        if ($relationId === '' || !isset($relationships[$relationId])) {
            return '';
        }

        $relationship = $relationships[$relationId];
        $src = '';
        $fileName = '';

        if (!empty($relationship['external'])) {
            $src = (string) ($relationship['target'] ?? '');
            $fileName = basename((string) parse_url($src, PHP_URL_PATH));
        } else {
            $target = $this->normalizeWordMediaTarget((string) ($relationship['target'] ?? ''));
            if ($target === null || !isset($mediaFiles[$target])) {
                return '';
            }

            $media = $mediaFiles[$target];
            $src = 'data:' . $media['contentType'] . ';base64,' . base64_encode((string) $media['content']);
            $fileName = (string) $media['fileName'];
        }

        if ($src === '') {
            return '';
        }

        $docPropertiesNode = $xpath->query('.//wp:docPr', $drawingNode)->item(0);
        $alt = '';
        if ($docPropertiesNode instanceof DOMElement) {
            $alt = trim((string) ($docPropertiesNode->getAttribute('descr') ?: $docPropertiesNode->getAttribute('name')));
        }
        if ($alt === '') {
            $alt = $fileName !== '' ? $fileName : 'Image';
        }

        $size = $this->extractWordDrawingDimensions($drawingNode, $xpath);
        $style = 'max-width:100%;height:auto;';
        $dimensionAttributes = '';
        if ($size !== null) {
            $style = sprintf('max-width:100%%;width:%dpx;height:%dpx;', $size['widthPx'], $size['heightPx']);
            $dimensionAttributes = sprintf(' width="%d" height="%d"', $size['widthPx'], $size['heightPx']);
        }

        return '<img src="'
            . htmlspecialchars($src, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            . '" alt="'
            . htmlspecialchars($alt, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            . '"'
            . $dimensionAttributes
            . ' style="'
            . htmlspecialchars($style, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            . '">';
    }

    private function extractWordDrawingDimensions(DOMElement $drawingNode, DOMXPath $xpath): ?array
    {
        $extentNode = $xpath->query('.//wp:extent', $drawingNode)->item(0);
        if (!$extentNode instanceof DOMElement) {
            return null;
        }

        $widthEmu = (int) trim((string) $extentNode->getAttribute('cx'));
        $heightEmu = (int) trim((string) $extentNode->getAttribute('cy'));
        if ($widthEmu <= 0 || $heightEmu <= 0) {
            return null;
        }

        return [
            'widthPx' => max(1, (int) round($widthEmu / 9525)),
            'heightPx' => max(1, (int) round($heightEmu / 9525)),
        ];
    }

    private function normalizeWordMediaTarget(string $target): ?string
    {
        $normalizedTarget = trim(str_replace('\\', '/', $target));
        if ($normalizedTarget === '') {
            return null;
        }

        while (str_starts_with($normalizedTarget, '../')) {
            $normalizedTarget = substr($normalizedTarget, 3);
        }

        $normalizedTarget = preg_replace('#^\./#', '', $normalizedTarget) ?? $normalizedTarget;
        if (str_starts_with($normalizedTarget, 'word/')) {
            $normalizedTarget = substr($normalizedTarget, 5);
        }

        return $normalizedTarget !== '' ? $normalizedTarget : null;
    }

    private function extractParagraphPlainText(DOMElement $paragraphNode, DOMXPath $xpath, array $relationships): string
    {
        $html = $this->extractParagraphInlineHtml($paragraphNode, $xpath, $relationships, []);

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function areAllSectionsEmpty(array $sections): bool
    {
        foreach ($sections as $value) {
            if ($this->hasMeaningfulHtmlContent((string) $value)) {
                return false;
            }
        }

        return true;
    }

    private function shouldWarnAboutMissingImportedSections(array $sections): bool
    {
        foreach (self::SECTION_DEFINITIONS as $fieldName => $definition) {
            if (!$this->hasMeaningfulHtmlContent((string) ($sections[$fieldName] ?? ''))) {
                return true;
            }
        }

        return false;
    }

    private function hasMeaningfulHtmlContent(string $html): bool
    {
        $normalizedHtml = trim($html);
        if ($normalizedHtml === '') {
            return false;
        }

        if (preg_match('/<img\b/i', $normalizedHtml) === 1) {
            return true;
        }

        $text = trim(html_entity_decode(strip_tags($normalizedHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $text !== '';
    }

    private function convertDocxToPdf(string $docxPath, string $pdfPath): void
    {
        $binary = $this->findLibreOfficeBinary();
        if ($binary === null) {
            throw new \RuntimeException('LibreOffice est requis pour générer le PDF du cahier des charges.');
        }

        $outputDirectory = dirname($pdfPath);
        if (!is_dir($outputDirectory) && !@mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
            throw new \RuntimeException('Impossible de préparer le dossier PDF du cahier des charges.');
        }

        $expectedOutputPath = $outputDirectory . '/' . pathinfo($docxPath, PATHINFO_FILENAME) . '.pdf';
        if (is_file($expectedOutputPath)) {
            @unlink($expectedOutputPath);
        }
        if (is_file($pdfPath)) {
            @unlink($pdfPath);
        }

        $process = new Process([
            $binary,
            '--headless',
            '--convert-to',
            'pdf:writer_pdf_Export',
            '--outdir',
            $outputDirectory,
            $docxPath,
        ]);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('La conversion PDF a échoué : ' . trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        if (!is_file($expectedOutputPath)) {
            throw new \RuntimeException('Le fichier PDF n a pas été généré par LibreOffice.');
        }

        if ($expectedOutputPath !== $pdfPath && !@rename($expectedOutputPath, $pdfPath)) {
            throw new \RuntimeException('Impossible d enregistrer le fichier PDF du cahier des charges.');
        }
    }

    private function findLibreOfficeBinary(): ?string
    {
        $candidates = array_filter([
            $this->libreOfficeBinary,
            $_ENV['LIBREOFFICE_BINARY'] ?? null,
            $_SERVER['LIBREOFFICE_BINARY'] ?? null,
        ], static fn ($value): bool => is_string($value) && trim($value) !== '');

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $finder = new ExecutableFinder();
        foreach (['soffice', 'libreoffice'] as $command) {
            $found = $finder->find($command);
            if (is_string($found) && $found !== '') {
                return $found;
            }
        }

        foreach ([
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
            '/usr/bin/soffice',
            '/usr/bin/libreoffice',
            '/snap/bin/libreoffice',
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function ensureProjectDirectory(array $project): string
    {
        $projectId = trim((string) ($project['id'] ?? ''));
        if ($projectId === '') {
            throw new \RuntimeException('Le projet doit être enregistré avant de générer un cahier des charges.');
        }

        $directory = $this->kernel->getProjectDir() . '/var/gantt/cdc/' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', $projectId);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de préparer le dossier du cahier des charges.');
        }

        return $directory;
    }

    private function getProjectDocxPath(array $project): string
    {
        return $this->ensureProjectDirectory($project) . '/current.docx';
    }

    private function getProjectPdfPath(array $project): string
    {
        return $this->ensureProjectDirectory($project) . '/current.pdf';
    }

    private function buildDownloadFileName(array $project, string $extension): string
    {
        $reference = $this->sanitizeFileFragment($this->projectReference($project));
        $title = $this->sanitizeFileFragment($this->projectTitle($project));
        $baseName = 'CDC';

        if ($reference !== '') {
            $baseName .= '-' . $reference;
        }
        if ($title !== '' && strcasecmp($title, $reference) !== 0) {
            $baseName .= '-' . $title;
        }

        return $baseName . '.' . strtolower($extension);
    }

    private function sanitizeFileFragment(string $value): string
    {
        $value = preg_replace('/[^\pL\pN]+/u', '-', trim($value)) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : '';
    }

    private function hasCdcSummaryContent(array $project): bool
    {
        foreach (['cdcRequester', 'cdcRequestDate', 'cdcDueDate', 'cdcPriority', 'cdcService', 'cdcProjectManager'] as $fieldName) {
            if (trim((string) ($project[$fieldName] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function projectSummaryText(array $project, string $fieldName): string
    {
        return trim((string) ($project[$fieldName] ?? ''));
    }

    private function projectSummaryDateLabel(array $project, string $fieldName): string
    {
        $value = trim((string) ($project[$fieldName] ?? ''));
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? date('d/m/Y', $timestamp) : '';
    }

    private function projectRequester(array $project): string
    {
        return $this->projectSummaryText($project, 'cdcRequester');
    }

    private function projectRequestDateLabel(array $project): string
    {
        return $this->projectSummaryDateLabel($project, 'cdcRequestDate');
    }

    private function projectRequestedDueDateLabel(array $project): string
    {
        return $this->projectSummaryDateLabel($project, 'cdcDueDate');
    }

    private function parseProjectUpdatedAtTimestamp(array $project): ?int
    {
        $value = trim((string) ($project['cdcUpdatedAt'] ?? ''));
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? $timestamp : null;
    }

    private function projectReference(array $project): string
    {
        $reference = trim((string) ($project['ref'] ?? ''));

        return $reference !== '' ? $reference : trim((string) ($project['id'] ?? 'Projet'));
    }

    private function projectTitle(array $project): string
    {
        $title = trim((string) ($project['title'] ?? ''));

        return $title !== '' ? $title : $this->projectReference($project);
    }

    private function projectDocumentTitle(array $project): string
    {
        $title = trim((string) ($project['cdcTitle'] ?? ''));

        return $title !== '' ? $title : $this->projectTitle($project);
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
