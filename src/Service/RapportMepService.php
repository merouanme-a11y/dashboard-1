<?php

namespace App\Service;

use App\Entity\Module;
use App\Entity\Utilisateur;
use App\Repository\ModuleRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;
use ZipArchive;

final class RapportMepService
{
    public const MODULE_NAME = 'rapport_mep';
    public const ROUTE_NAME = 'app_rapport_mep';
    public const APP_VERSION = '0.1.0';

    private const EXPECTED_HEADERS = [
        'iddeticket' => 'ticketId',
        'type' => 'type',
        'service' => 'service',
        'etat' => 'state',
        'typeaction' => 'actionType',
        'resolu' => 'resolvedAt',
        'resume' => 'summary',
        'responsable' => 'owner',
        'rapporteur' => 'reporter',
        'lienrm' => 'redmineLink',
    ];

    private const COLOR_MAP = [
        'Comptabilite' => ['fill' => '#F2CEEF', 'font' => '#000000'],
        'Conformite' => ['fill' => '#782170', 'font' => '#FFFFFF'],
        'Service Entreprise' => ['fill' => '#E97132', 'font' => '#FFFFFF'],
        'Controle Interne' => ['fill' => '#FFAFAF', 'font' => '#000000'],
        'Prestation' => ['fill' => '#36B890', 'font' => '#FFFFFF'],
        'Production' => ['fill' => '#FFC000', 'font' => '#000000'],
        'Relation Client' => ['fill' => '#E49EDD', 'font' => '#000000'],
        'COM' => ['fill' => '#B686DA', 'font' => '#000000'],
        'IT' => ['fill' => '#A6C9EC', 'font' => '#000000'],
        'Market' => ['fill' => '#595959', 'font' => '#FFFFFF'],
        'Sinistre' => ['fill' => '#BE5014', 'font' => '#FFFFFF'],
        'FAIT' => ['fill' => '#DAF2D0', 'font' => '#000000'],
        'PROJET' => ['fill' => '#DAE9F8', 'font' => '#000000'],
        'Tache' => ['fill' => '#606060', 'font' => '#FFFFFF'],
        'En Production' => ['fill' => '#DAF2D0', 'font' => '#000000'],
        'Giovanni' => ['fill' => '#B5E6A2', 'font' => '#000000'],
        'Merouan' => ['fill' => '#83CCEB', 'font' => '#000000'],
        'Arnaud' => ['fill' => '#808080', 'font' => '#FFFFFF'],
        'John' => ['fill' => '#51154A', 'font' => '#FFFFFF'],
        'Sebastien' => ['fill' => '#F1A317', 'font' => '#000000'],
    ];

    public function __construct(
        private ModuleRepository $moduleRepository,
        private EntityManagerInterface $em,
        private KernelInterface $kernel,
    ) {}

    public function ensureModuleExists(): Module
    {
        $module = $this->moduleRepository->findByName(self::MODULE_NAME);
        if ($module instanceof Module) {
            return $module;
        }

        $maxSortOrder = 0;
        foreach ($this->moduleRepository->findAllSorted() as $existingModule) {
            $maxSortOrder = max($maxSortOrder, (int) $existingModule->getSortOrder());
        }

        $module = (new Module())
            ->setName(self::MODULE_NAME)
            ->setLabel('Rapport MEP')
            ->setIcon('bi-journal-richtext')
            ->setRouteName(self::ROUTE_NAME)
            ->setIsActive(true)
            ->setSortOrder($maxSortOrder + 10);

        $this->em->persist($module);
        $this->em->flush();

        return $module;
    }

    public function listReports(): array
    {
        $reports = $this->loadReports();

        usort($reports, static function (array $left, array $right): int {
            return strcmp((string) ($right['updatedAt'] ?? ''), (string) ($left['updatedAt'] ?? ''));
        });

        return $reports;
    }

    public function findReport(string $reportId): ?array
    {
        $reportId = trim($reportId);
        if ($reportId === '') {
            return null;
        }

        foreach ($this->loadReports() as $report) {
            if ((string) ($report['id'] ?? '') === $reportId) {
                return $report;
            }
        }

        return null;
    }

    public function deleteReport(string $reportId): bool
    {
        $reportId = trim($reportId);
        if ($reportId === '') {
            return false;
        }

        $reports = $this->loadReports();
        $remaining = [];
        $removedReport = null;

        foreach ($reports as $report) {
            if ((string) ($report['id'] ?? '') === $reportId) {
                $removedReport = $report;
                continue;
            }

            $remaining[] = $report;
        }

        if (!is_array($removedReport)) {
            return false;
        }

        $this->saveReports($remaining);
        $this->removeReportArtifacts($removedReport);

        return true;
    }

    public function createReport(Utilisateur $user): array
    {
        $now = new DateTimeImmutable('now');
        $releaseDate = $now->format('Y-m-d');
        $displayName = $this->buildUserDisplayName($user);

        $report = [
            'id' => $this->generateReportId($now),
            'title' => $this->buildWeekLabel($releaseDate),
            'releaseDate' => $releaseDate,
            'releaseType' => 'Mise en production ADEP',
            'preparedBy' => $displayName,
            'emailTo' => '',
            'emailSubject' => $this->buildDefaultEmailSubject($releaseDate),
            'emailBody' => $this->buildDefaultEmailBody($releaseDate),
            'youtrackUrl' => '',
            'infoBlocks' => $this->buildDefaultInfoBlocks(),
            'rows' => [],
            'sourceFileName' => '',
            'sourceSheetName' => '',
            'createdBy' => $displayName,
            'createdByEmail' => (string) $user->getEmail(),
            'createdAt' => $now->format(DATE_ATOM),
            'updatedAt' => $now->format(DATE_ATOM),
        ];

        $this->upsertReport($report);

        return $report;
    }

    public function updateReportFromForm(array $report, array $formData): array
    {
        $releaseDate = $this->normalizeDateInput((string) ($formData['releaseDate'] ?? ''), (string) ($report['releaseDate'] ?? ''));
        $report['releaseDate'] = $releaseDate;
        $report['title'] = $this->buildWeekLabel($releaseDate);
        $report['releaseType'] = $this->normalizeText((string) ($formData['releaseType'] ?? ''), 120, 'Mise en production ADEP');
        $report['preparedBy'] = $this->normalizeText((string) ($formData['preparedBy'] ?? ''), 120);
        $report['emailTo'] = $this->normalizeTextarea((string) ($formData['emailTo'] ?? ''));
        $report['emailSubject'] = $this->normalizeText(
            (string) ($formData['emailSubject'] ?? ''),
            255,
            $this->buildDefaultEmailSubject($releaseDate)
        );
        $report['emailBody'] = $this->normalizeTextarea(
            (string) ($formData['emailBody'] ?? ''),
            $this->buildDefaultEmailBody($releaseDate)
        );
        $report['youtrackUrl'] = $this->normalizeUrl((string) ($formData['youtrackUrl'] ?? ''));
        $report['infoBlocks'] = $this->normalizeInfoBlocks($formData['infoBlocks'] ?? null, $report['infoBlocks'] ?? []);
        $report['updatedAt'] = (new DateTimeImmutable('now'))->format(DATE_ATOM);

        $this->upsertReport($report);

        return $report;
    }

    public function importSourceWorkbook(array $report, UploadedFile $file): array
    {
        if (!$file->isValid()) {
            throw new \RuntimeException('Le fichier source Excel est invalide.');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, ['xlsx', 'xlsm'], true)) {
            throw new \RuntimeException('Format non supporte. Utilisez un fichier .xlsx ou .xlsm.');
        }

        $imported = $this->parseOpenXmlWorkbook($file->getPathname());
        if (($imported['rows'] ?? []) === []) {
            throw new \RuntimeException('Aucune ligne exploitable n a ete trouvee dans le fichier source.');
        }

        $sourcePath = $this->storeImportedSourceFile((string) ($report['id'] ?? ''), $file);
        $report['rows'] = $imported['rows'];
        $report['sourceFileName'] = (string) $file->getClientOriginalName();
        $report['sourceSheetName'] = (string) ($imported['sheetName'] ?? '');
        $report['sourceFilePath'] = $sourcePath;
        $report['updatedAt'] = (new DateTimeImmutable('now'))->format(DATE_ATOM);

        $this->upsertReport($report);

        return $report;
    }

    public function getColorMap(): array
    {
        return self::COLOR_MAP;
    }

    public function resolveColorStyle(string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach ([$value, $this->repairDisplayText($value)] as $candidate) {
            $candidate = trim($candidate);
            if ($candidate !== '' && isset(self::COLOR_MAP[$candidate]) && is_array(self::COLOR_MAP[$candidate])) {
                return self::COLOR_MAP[$candidate];
            }
        }

        $normalizedValue = $this->normalizeLookupKey($value);
        if ($normalizedValue === '') {
            return null;
        }

        foreach (self::COLOR_MAP as $label => $style) {
            if ($this->normalizeLookupKey((string) $label) === $normalizedValue) {
                return is_array($style) ? $style : null;
            }
        }

        return null;
    }

    public function buildTableRows(array $report): array
    {
        $rows = is_array($report['rows'] ?? null) ? $report['rows'] : [];
        $displayRows = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $row['serviceStyle'] = $this->resolveColorStyle((string) ($row['service'] ?? ''));
            $row['ownerStyle'] = $this->resolveColorStyle((string) ($row['owner'] ?? ''));
            $displayRows[] = $row;
        }

        return $displayRows;
    }

    public function getWorkbookAnalysis(): array
    {
        return [
            'sheets' => [
                ['name' => 'Rapport MEP', 'usage' => 'Feuille principale du rapport, impression B8:L29.'],
                ['name' => 'Infos MEP', 'usage' => 'Blocs d informations annexes injectes dans le mail.'],
                ['name' => 'Archive', 'usage' => 'Historique des anciens rapports importes.'],
                ['name' => 'Donnees', 'usage' => 'Referentiel des services, etats, collaborateurs et couleurs.'],
            ],
            'buttons' => [
                ['label' => 'Import Tickets MEP', 'macro' => 'Module3.Import_Rapport_mise_en_prod'],
                ['label' => 'Generer l email du CR', 'macro' => 'Export_to_Mail'],
                ['label' => 'Sauvegarde', 'macro' => 'Sauvegarde_Rapport_MEP_SansEcraser'],
            ],
            'sourceHeaders' => [
                'ID de ticket',
                'Type',
                'Service',
                'Etat',
                'Type Action',
                'Resolu',
                'Resume',
                'Responsable',
                'Rapporteur',
                'Lien RM',
            ],
            'limitations' => [
                'Le module web peut reproduire l import, l affichage, l archivage et les exports.',
                'Le brouillon Outlook desktop via macro COM ne peut pas etre reproduit a l identique dans le navigateur.',
                'Le PDF devra etre genere cote serveur ou via un document intermediaire, pas via Excel.',
            ],
        ];
    }

    public function buildPreviewStats(array $report): array
    {
        $rows = is_array($report['rows'] ?? null) ? $report['rows'] : [];
        $services = [];
        $reporters = [];

        foreach ($rows as $row) {
            $service = trim((string) ($row['service'] ?? ''));
            $reporter = trim((string) ($row['reporter'] ?? ''));
            if ($service !== '') {
                $services[$service] = true;
            }
            if ($reporter !== '') {
                $reporters[$reporter] = true;
            }
        }

        return [
            'tickets' => count($rows),
            'services' => count($services),
            'reporters' => count($reporters),
            'sheetName' => trim((string) ($report['sourceSheetName'] ?? '')),
        ];
    }

    public function buildMailtoLink(array $report): string
    {
        $recipients = $this->getEmailRecipients($report);
        if ($recipients === []) {
            return '';
        }

        $query = [];
        $subject = trim((string) ($report['emailSubject'] ?? ''));
        $body = $this->buildEmailTextBody($report);

        if ($subject !== '') {
            $query['subject'] = $subject;
        }

        if ($body !== '') {
            $query['body'] = $body;
        }

        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return 'mailto:' . implode(',', $recipients) . ($queryString !== '' ? '?' . $queryString : '');
    }

    public function getEmailRecipients(array $report): array
    {
        return $this->normalizeMailtoRecipients((string) ($report['emailTo'] ?? ''));
    }

    public function buildEmailTextBody(array $report): string
    {
        $sections = [];

        $primaryBody = trim((string) ($report['emailBody'] ?? ''));
        if ($primaryBody !== '') {
            $sections[] = $primaryBody;
        }

        foreach ($this->normalizeInfoBlocks($report['infoBlocks'] ?? null, []) as $block) {
            $title = trim((string) ($block['title'] ?? ''));
            $body = trim((string) ($block['body'] ?? ''));
            if ($body === '') {
                continue;
            }

            $blockParts = [];
            if ($title !== '') {
                $blockParts[] = $title;
            }
            if ($body !== '') {
                $blockParts[] = $body;
            }

            $sections[] = implode("\n", $blockParts);
        }

        return trim(implode("\n\n", $sections));
    }

    public function buildEmailHtmlBody(array $report): string
    {
        $contentBlocks = [];

        $primaryBody = trim((string) ($report['emailBody'] ?? ''));
        if ($primaryBody !== '') {
            $contentBlocks[] = sprintf(
                '<div style="margin-bottom:16px;">%s</div>',
                $this->convertPlainTextToHtmlParagraphs($primaryBody)
            );
        }

        foreach ($this->normalizeInfoBlocks($report['infoBlocks'] ?? null, []) as $block) {
            $title = trim((string) ($block['title'] ?? ''));
            $body = trim((string) ($block['body'] ?? ''));
            if ($body === '') {
                continue;
            }

            $fragment = '<div style="margin-top:18px; padding:14px; border:1px solid #d6deef; border-radius:8px; background:#f8fbff;">';
            if ($title !== '') {
                $fragment .= '<div style="font-weight:700; color:#c62828; margin-bottom:8px;">'
                    . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '</div>';
            }
            if ($body !== '') {
                $fragment .= '<div>' . $this->convertPlainTextToHtmlParagraphs($body) . '</div>';
            }
            $fragment .= '</div>';

            $contentBlocks[] = $fragment;
        }

        $safeTitle = htmlspecialchars((string) ($report['title'] ?? 'Rapport MEP'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return sprintf(<<<HTML
<div style="font-family: Arial, sans-serif; font-size: 14px; line-height: 1.5; color: #1f2937;">
    <div style="font-size: 20px; font-weight: 700; color: #173a63; margin-bottom: 18px;">{$safeTitle}</div>
    %s
</div>
HTML
        , implode('', $contentBlocks));
    }

    private function loadReports(): array
    {
        $path = $this->getReportsStoragePath();
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }

    private function saveReports(array $reports): void
    {
        $directory = dirname($this->getReportsStoragePath());
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de preparer le stockage local du module Rapport MEP.');
        }

        file_put_contents(
            $this->getReportsStoragePath(),
            json_encode(array_values($reports), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function upsertReport(array $report): void
    {
        $reportId = (string) ($report['id'] ?? '');
        if ($reportId === '') {
            throw new \RuntimeException('Identifiant de rapport MEP manquant.');
        }

        $reports = $this->loadReports();
        $saved = false;

        foreach ($reports as $index => $existingReport) {
            if ((string) ($existingReport['id'] ?? '') === $reportId) {
                $reports[$index] = $report;
                $saved = true;
                break;
            }
        }

        if (!$saved) {
            $reports[] = $report;
        }

        $this->saveReports($reports);
    }

    private function removeReportArtifacts(array $report): void
    {
        $sourceFilePath = trim((string) ($report['sourceFilePath'] ?? ''));
        if ($sourceFilePath !== '') {
            $this->deletePath($sourceFilePath);
        }

        $reportId = trim((string) ($report['id'] ?? ''));
        if ($reportId !== '') {
            $this->deletePath($this->kernel->getProjectDir() . '/var/rapport-mep/exports/' . $reportId);
        }
    }

    private function deletePath(string $path): void
    {
        if ($path === '' || !file_exists($path)) {
            return;
        }

        if (is_dir($path)) {
            $items = scandir($path);
            if (is_array($items)) {
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }

                    $this->deletePath($path . DIRECTORY_SEPARATOR . $item);
                }
            }

            @rmdir($path);

            return;
        }

        @unlink($path);
    }

    private function parseOpenXmlWorkbook(string $filePath): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive est requis pour importer le fichier Excel.');
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException('Impossible d ouvrir le fichier Excel source.');
        }

        try {
            $sharedStrings = $this->readSharedStrings($zip);
            $sheets = $this->readWorkbookSheets($zip);
            $selected = null;

            foreach ($sheets as $sheet) {
                $rows = $this->readRowsFromSheet($zip, (string) $sheet['path'], $sharedStrings);
                $headerMatch = $this->detectHeaderRow($rows);
                if ($headerMatch === null) {
                    continue;
                }

                $score = count($headerMatch['map']);
                if ($selected === null || $score > $selected['score']) {
                    $selected = [
                        'sheetName' => (string) $sheet['name'],
                        'rows' => $rows,
                        'score' => $score,
                        'headerRowIndex' => $headerMatch['rowIndex'],
                        'headerMap' => $headerMatch['map'],
                    ];
                }
            }

            if ($selected === null) {
                throw new \RuntimeException('Impossible de detecter les en-tetes attendus dans le fichier source.');
            }

            $reportRows = $this->mapImportedRows($selected['rows'], $selected['headerRowIndex'], $selected['headerMap']);
            usort($reportRows, static function (array $left, array $right): int {
                $serviceCompare = strcasecmp((string) ($left['service'] ?? ''), (string) ($right['service'] ?? ''));
                if ($serviceCompare !== 0) {
                    return $serviceCompare;
                }

                return strcasecmp((string) ($left['ticketId'] ?? ''), (string) ($right['ticketId'] ?? ''));
            });

            return [
                'sheetName' => $selected['sheetName'],
                'rows' => $reportRows,
            ];
        } finally {
            $zip->close();
        }
    }

    private function readWorkbookSheets(ZipArchive $zip): array
    {
        $workbookXml = $this->loadXmlFromZip($zip, 'xl/workbook.xml');
        $workbookXml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $sheetNodes = $workbookXml->xpath('/main:workbook/main:sheets/main:sheet');
        if (!is_array($sheetNodes) || $sheetNodes === []) {
            throw new \RuntimeException('Aucune feuille de calcul n a ete trouvee dans le classeur source.');
        }

        $relsXml = $this->loadXmlFromZip($zip, 'xl/_rels/workbook.xml.rels');
        $relsXml->registerXPathNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');
        $targetsByRelationshipId = [];

        foreach ($relsXml->xpath('/rel:Relationships/rel:Relationship') ?: [] as $relationshipNode) {
            $target = (string) ($relationshipNode['Target'] ?? '');
            $targetsByRelationshipId[(string) ($relationshipNode['Id'] ?? '')] = strpos($target, 'xl/') === 0
                ? $target
                : 'xl/' . ltrim($target, '/');
        }

        $sheets = [];
        foreach ($sheetNodes as $sheetNode) {
            $relationshipId = (string) $sheetNode->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
            if ($relationshipId === '' || !isset($targetsByRelationshipId[$relationshipId])) {
                continue;
            }

            $sheets[] = [
                'name' => (string) ($sheetNode['name'] ?? ''),
                'path' => $targetsByRelationshipId[$relationshipId],
            ];
        }

        if ($sheets === []) {
            throw new \RuntimeException('Impossible de resoudre les feuilles du classeur source.');
        }

        return $sheets;
    }

    private function readRowsFromSheet(ZipArchive $zip, string $sheetPath, array $sharedStrings): array
    {
        $sheetXml = $this->loadXmlFromZip($zip, $sheetPath);
        $sheetXml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rowNodes = $sheetXml->xpath('/main:worksheet/main:sheetData/main:row');
        $hyperlinksByReference = $this->readSheetHyperlinks($zip, $sheetXml, $sheetPath);
        $rows = [];

        foreach ($rowNodes ?: [] as $rowNode) {
            $rowNode->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $cells = [];
            $links = [];

            foreach ($rowNode->xpath('main:c') ?: [] as $cellNode) {
                $reference = (string) ($cellNode['r'] ?? '');
                $column = preg_replace('/\d+/', '', $reference);
                if ($column === '') {
                    continue;
                }

                $cells[$column] = $this->resolveCellValue($cellNode, $sharedStrings);
                $formulaLink = $this->extractHyperlinkTargetFromFormula($cellNode);
                if ($formulaLink !== null) {
                    $links[$column] = $formulaLink;
                } elseif (isset($hyperlinksByReference[$reference])) {
                    $links[$column] = $hyperlinksByReference[$reference];
                }
            }

            $rows[] = [
                'rowNumber' => (int) ($rowNode['r'] ?? 0),
                'cells' => $cells,
                'links' => $links,
            ];
        }

        return $rows;
    }

    private function readSheetHyperlinks(ZipArchive $zip, \SimpleXMLElement $sheetXml, string $sheetPath): array
    {
        $sheetXml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $hyperlinkNodes = $sheetXml->xpath('/main:worksheet/main:hyperlinks/main:hyperlink');
        if (!is_array($hyperlinkNodes) || $hyperlinkNodes === []) {
            return [];
        }

        $relationshipsPath = $this->resolveSheetRelationshipsPath($sheetPath);
        $targetsByRelationshipId = $this->readSheetHyperlinkRelationshipTargets($zip, $relationshipsPath);
        $result = [];

        foreach ($hyperlinkNodes as $hyperlinkNode) {
            $reference = trim((string) ($hyperlinkNode['ref'] ?? ''));
            if ($reference === '') {
                continue;
            }

            $relationshipId = (string) $hyperlinkNode->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
            if ($relationshipId !== '' && isset($targetsByRelationshipId[$relationshipId])) {
                $result[$reference] = $targetsByRelationshipId[$relationshipId];
            }
        }

        return $result;
    }

    private function resolveSheetRelationshipsPath(string $sheetPath): string
    {
        $directory = trim((string) dirname($sheetPath), '.\\/');
        $fileName = basename($sheetPath);

        return $directory === ''
            ? '_rels/' . $fileName . '.rels'
            : $directory . '/_rels/' . $fileName . '.rels';
    }

    private function readSheetHyperlinkRelationshipTargets(ZipArchive $zip, string $relationshipsPath): array
    {
        if ($zip->locateName($relationshipsPath) === false) {
            return [];
        }

        $relsXml = $this->loadXmlFromZip($zip, $relationshipsPath);
        $relsXml->registerXPathNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');
        $targets = [];

        foreach ($relsXml->xpath('/rel:Relationships/rel:Relationship') ?: [] as $relationshipNode) {
            $type = (string) ($relationshipNode['Type'] ?? '');
            if ($type !== 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink') {
                continue;
            }

            $relationshipId = (string) ($relationshipNode['Id'] ?? '');
            $target = trim((string) ($relationshipNode['Target'] ?? ''));
            if ($relationshipId !== '' && $target !== '') {
                $targets[$relationshipId] = $target;
            }
        }

        return $targets;
    }

    private function detectHeaderRow(array $rows): ?array
    {
        foreach ($rows as $rowIndex => $row) {
            $map = [];

            foreach (($row['cells'] ?? []) as $column => $value) {
                $normalized = $this->normalizeLookupKey((string) $value);
                if ($normalized === '' || !isset(self::EXPECTED_HEADERS[$normalized])) {
                    continue;
                }

                $map[(string) $column] = self::EXPECTED_HEADERS[$normalized];
            }

            if (isset($map['A']) && count($map) >= 6) {
                return [
                    'rowIndex' => $rowIndex,
                    'map' => $map,
                ];
            }
        }

        return null;
    }

    private function mapImportedRows(array $rows, int $headerRowIndex, array $headerMap): array
    {
        $result = [];

        foreach (array_slice($rows, $headerRowIndex + 1) as $row) {
            $entry = [
                'ticketId' => '',
                'ticketUrl' => '',
                'type' => '',
                'service' => '',
                'state' => '',
                'actionType' => '',
                'resolvedAt' => '',
                'summary' => '',
                'owner' => '',
                'reporter' => '',
                'redmineLabel' => '',
                'redmineUrl' => '',
            ];

            foreach ($headerMap as $column => $targetKey) {
                $rawValue = trim((string) (($row['cells'][$column] ?? '')));
                $linkValue = trim((string) (($row['links'][$column] ?? '')));

                if ($targetKey === 'redmineLink') {
                    $url = $this->normalizeUrl($linkValue !== '' ? $linkValue : $rawValue);
                    $entry['redmineUrl'] = $url;
                    $entry['redmineLabel'] = $this->buildRedmineLabel($url);
                    continue;
                }

                if ($targetKey === 'resolvedAt') {
                    $entry['resolvedAt'] = $this->formatExcelDate($rawValue);
                    continue;
                }

                $entry[$targetKey] = $rawValue;
            }

            $ticketId = trim((string) $entry['ticketId']);
            if ($ticketId === '') {
                continue;
            }

            $entry['ticketUrl'] = 'https://maintenance.adep.com/tickets/' . rawurlencode($ticketId);
            $result[] = $entry;
        }

        return $result;
    }

    private function readSharedStrings(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $xml = $this->loadXmlFromZip($zip, 'xl/sharedStrings.xml');
        $xml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $items = [];

        foreach ($xml->xpath('/main:sst/main:si') ?: [] as $itemNode) {
            $itemNode->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $textNodes = $itemNode->xpath('main:t|main:r/main:t');
            $value = '';

            foreach ($textNodes ?: [] as $textNode) {
                $value .= (string) $textNode;
            }

            $items[] = $value;
        }

        return $items;
    }

    private function resolveCellValue(\SimpleXMLElement $cellNode, array $sharedStrings): string
    {
        $type = (string) ($cellNode['t'] ?? '');

        if ($type === 's') {
            $index = (int) ($cellNode->v ?? 0);
            return (string) ($sharedStrings[$index] ?? '');
        }

        if ($type === 'inlineStr') {
            $cellNode->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $parts = [];
            foreach ($cellNode->xpath('main:is/main:t|main:is/main:r/main:t') ?: [] as $textNode) {
                $parts[] = (string) $textNode;
            }

            return implode('', $parts);
        }

        if (!isset($cellNode->v)) {
            $label = $this->extractHyperlinkLabelFromFormula($cellNode);
            return $label ?? '';
        }

        return (string) $cellNode->v;
    }

    private function extractHyperlinkTargetFromFormula(\SimpleXMLElement $cellNode): ?string
    {
        $parts = $this->extractHyperlinkFormulaParts($cellNode);

        return $parts['url'] ?? null;
    }

    private function extractHyperlinkLabelFromFormula(\SimpleXMLElement $cellNode): ?string
    {
        $parts = $this->extractHyperlinkFormulaParts($cellNode);

        return $parts['label'] ?? null;
    }

    private function extractHyperlinkFormulaParts(\SimpleXMLElement $cellNode): array
    {
        $cellNode->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $formulaNodes = $cellNode->xpath('main:f');
        if (!is_array($formulaNodes) || !isset($formulaNodes[0])) {
            return [];
        }

        $formula = trim((string) $formulaNodes[0]);
        if ($formula === '') {
            return [];
        }

        $formula = ltrim($formula, '=');
        if (preg_match('/^(?:_xlfn\.)?(?:HYPERLINK|LIEN_HYPERTEXTE)\(\s*"((?:[^"]|"")*)"\s*[,;]\s*"((?:[^"]|"")*)"\s*\)$/iu', $formula, $matches) !== 1) {
            return [];
        }

        return [
            'url' => str_replace('""', '"', (string) $matches[1]),
            'label' => str_replace('""', '"', (string) $matches[2]),
        ];
    }

    private function loadXmlFromZip(ZipArchive $zip, string $path): \SimpleXMLElement
    {
        $content = $zip->getFromName($path);
        if ($content === false) {
            throw new \RuntimeException(sprintf('Entree Excel introuvable : %s', $path));
        }

        $xml = @simplexml_load_string($content);
        if (!$xml instanceof \SimpleXMLElement) {
            throw new \RuntimeException(sprintf('Impossible de lire le XML Excel : %s', $path));
        }

        return $xml;
    }

    private function getReportsStoragePath(): string
    {
        return $this->kernel->getProjectDir() . '/var/rapport-mep/reports.json';
    }

    private function getSourcesDirectory(): string
    {
        return $this->kernel->getProjectDir() . '/var/rapport-mep/sources';
    }

    private function storeImportedSourceFile(string $reportId, UploadedFile $file): string
    {
        $directory = $this->getSourcesDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de preparer le dossier des sources Rapport MEP.');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $targetPath = $directory . '/' . $reportId . '-source.' . $extension;

        if (!@copy($file->getPathname(), $targetPath)) {
            throw new \RuntimeException('Impossible de conserver une copie du fichier source importe.');
        }

        return $targetPath;
    }

    private function generateReportId(DateTimeImmutable $now): string
    {
        return 'mep-' . $now->format('Ymd-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
    }

    private function buildWeekLabel(string $releaseDate): string
    {
        $date = $this->parseDate($releaseDate) ?? new DateTimeImmutable('today');

        return sprintf('Mise en production S%s', $date->format('W'));
    }

    private function buildDefaultEmailSubject(string $releaseDate): string
    {
        $date = $this->parseDate($releaseDate) ?? new DateTimeImmutable('today');

        return sprintf('Compte rendu | Mise en production Openassur | %s', $date->format('d-m-Y'));
    }

    private function buildDefaultEmailBody(string $releaseDate): string
    {
        $date = $this->parseDate($releaseDate) ?? new DateTimeImmutable('today');

        return sprintf(
            "Bonjour,\n\nVous trouverez en PJ le rapport de mise en production du : %s.\n\nCordialement,",
            $this->formatLongFrenchDate($date)
        );
    }

    private function buildDefaultInfoBlocks(): array
    {
        return [
            ['title' => '! Date des prochaines Mise en production SNL !', 'body' => ''],
            ['title' => '', 'body' => ''],
            ['title' => '', 'body' => ''],
        ];
    }

    private function normalizeInfoBlocks(mixed $value, array $fallback): array
    {
        $blocks = [];
        $incoming = is_array($value) ? $value : [];

        for ($index = 0; $index < 3; $index++) {
            $source = is_array($incoming[$index] ?? null) ? $incoming[$index] : [];
            $blocks[] = [
                'title' => mb_substr(trim((string) ($source['title'] ?? '')), 0, 160),
                'body' => trim(str_replace(["\r\n", "\r"], "\n", (string) ($source['body'] ?? ''))),
            ];
        }

        return $blocks;
    }

    private function normalizeDateInput(string $value, string $fallback): string
    {
        $date = $this->parseDate($value) ?? $this->parseDate($fallback) ?? new DateTimeImmutable('today');

        return $date->format('Y-m-d');
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof DateTimeImmutable) {
                return $date;
            }
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatExcelDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d+(?:\.\d+)?$/', $value) === 1) {
            $serial = (float) $value;
            $date = (new DateTimeImmutable('1899-12-30'))->modify(sprintf('+%d days', (int) floor($serial)));
            return $date->format('d/m/Y');
        }

        $date = $this->parseDate($value);

        return $date instanceof DateTimeImmutable ? $date->format('d/m/Y') : $value;
    }

    private function buildRedmineLabel(string $url): string
    {
        if ($url === '') {
            return '';
        }

        if (preg_match('/(\d+)(?:\/)?$/', $url, $matches) === 1) {
            return 'RM#' . $matches[1];
        }

        return 'RM';
    }

    private function normalizeLookupKey(string $value): string
    {
        $value = $this->transliterateToAscii($value);
        $value = strtolower(trim($value));

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }

    private function repairDisplayText(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return strtr($value, [
            'Ã©' => 'é',
            'Ã¨' => 'è',
            'Ãª' => 'ê',
            'Ã«' => 'ë',
            'Ã‰' => 'É',
            'Ã€' => 'À',
            'Ã ' => 'à',
            'Ã¢' => 'â',
            'Ã¤' => 'ä',
            'Ã¹' => 'ù',
            'Ã»' => 'û',
            'Ã¼' => 'ü',
            'Ã´' => 'ô',
            'Ã¶' => 'ö',
            'Ã®' => 'î',
            'Ã¯' => 'ï',
            'Ã§' => 'ç',
            'â€™' => "'",
            'â€“' => '-',
            'â€”' => '-',
            'â€œ' => '"',
            'â€' => '"',
            'Â°' => '°',
            'Â' => '',
        ]);
    }

    private function transliterateToAscii(string $value): string
    {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return is_string($converted) && $converted !== '' ? $converted : $value;
    }

    private function normalizeText(string $value, int $maxLength, string $fallback = ''): string
    {
        $value = trim($value);
        if ($value === '') {
            return $fallback;
        }

        return mb_substr($value, 0, $maxLength);
    }

    private function normalizeTextarea(string $value, string $fallback = ''): string
    {
        $value = trim(str_replace(["\r\n", "\r"], "\n", $value));

        return $value !== '' ? $value : $fallback;
    }

    private function normalizeUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
    }

    private function formatLongFrenchDate(DateTimeInterface $date): string
    {
        $formatter = new \IntlDateFormatter(
            'fr_FR',
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::NONE,
            'Europe/Paris',
            \IntlDateFormatter::GREGORIAN,
            'EEEE d MMMM yyyy'
        );

        $formatted = $formatter->format($date);

        return is_string($formatted) && $formatted !== '' ? $formatted : $date->format('d/m/Y');
    }

    private function buildUserDisplayName(Utilisateur $user): string
    {
        $parts = array_filter([
            trim((string) $user->getPrenom()),
            trim((string) $user->getNom()),
        ]);

        if ($parts !== []) {
            return implode(' ', $parts);
        }

        return (string) $user->getEmail();
    }

    private function convertPlainTextToHtmlParagraphs(string $value): string
    {
        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $value));
        if ($normalized === '') {
            return '';
        }

        $paragraphs = preg_split("/\n{2,}/", $normalized) ?: [];
        $htmlParagraphs = [];

        foreach ($paragraphs as $paragraph) {
            $text = trim($paragraph);
            if ($text === '') {
                continue;
            }

            $htmlParagraphs[] = '<p style="margin:0 0 12px;">'
                . nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false)
                . '</p>';
        }

        return implode('', $htmlParagraphs);
    }

    private function normalizeMailtoRecipients(string $value): array
    {
        $chunks = preg_split('/[\s,;]+/', trim(str_replace(["\r\n", "\r"], "\n", $value))) ?: [];
        $recipients = [];

        foreach ($chunks as $chunk) {
            $email = trim($chunk);
            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            $recipients[strtolower($email)] = $email;
        }

        return array_values($recipients);
    }
}
