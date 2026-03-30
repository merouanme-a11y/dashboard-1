<?php

declare(strict_types=1);

require_once __DIR__ . '/runtime.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/projects_repository.php';

function app_source_workbook_file(): string
{
    return rtrim((string) APP_GANTT_STORAGE_DIR, "\\/") . '/source.xlsx';
}

function app_exports_dir(): string
{
    return rtrim((string) APP_GANTT_EXPORT_DIR, "\\/");
}

function app_write_json_file(string $filePath, array $payload): void
{
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($filePath, $json . PHP_EOL) === false) {
        throw new RuntimeException('Impossible d\'écrire le fichier JSON.');
    }
}

function app_import_projects_from_workbook(string $workbookPath): array
{
    $rows = app_excel_read_rows($workbookPath);
    if (count($rows) < 2) {
        throw new RuntimeException('Le fichier Excel ne contient pas assez de lignes.');
    }

    $headerRow = app_find_projects_header_row($rows);
    if ($headerRow === null) {
        throw new RuntimeException('Impossible de détecter les en-têtes de la feuille projets.');
    }

    $headersByColumn = [];
    foreach ($headerRow['cells'] as $column => $value) {
        $headersByColumn[$column] = app_normalize_lookup_key((string) $value);
    }

    $startColumn = app_resolve_start_column($headersByColumn);
    $endColumn = app_find_column_by_headers($headersByColumn, [
        'datefin',
        'datefincible',
        'datefinduprojet',
        'datefindeprojet',
        'fin',
        'finprojet',
    ]);

    if ($startColumn === null) {
        throw new RuntimeException('La colonne de date de début est introuvable dans le fichier Excel.');
    }

    if ($endColumn === null) {
        throw new RuntimeException('La colonne "Date fin" est introuvable dans le fichier Excel.');
    }

    $projects = app_fetch_projects();
    $projectItems = array_values($projects);
    $projectIndexesByRef = [];
    $projectIndexesByTitle = [];

    foreach ($projectItems as $index => $project) {
        $refKey = app_normalize_lookup_key((string) ($project['ref'] ?? ''));
        $titleKey = app_normalize_lookup_key((string) ($project['title'] ?? ''));

        if ($refKey !== '') {
            $projectIndexesByRef[$refKey] = $index;
        }

        if ($titleKey !== '' && !isset($projectIndexesByTitle[$titleKey])) {
            $projectIndexesByTitle[$titleKey] = $index;
        }
    }

    $updatedCount = 0;
    $clearedCount = 0;
    $createdCount = 0;
    $unmatchedRows = [];
    $scheduledLane = 0;
    $nextProjectNumber = app_resolve_next_project_number($projectItems);

    foreach ($rows as $row) {
        if ($row['rowNumber'] <= $headerRow['rowNumber']) {
            continue;
        }

        if (empty($row['cells'])) {
            continue;
        }

        $projectIndex = app_match_project_index_from_row(
            $row['cells'],
            $headersByColumn,
            $projectIndexesByRef,
            $projectIndexesByTitle
        );

        if ($projectIndex === null) {
            $newProject = app_build_project_from_row($row['cells'], $headersByColumn, $nextProjectNumber);
            if ($newProject === null) {
                $unmatchedRows[] = $row['rowNumber'];
                continue;
            }

            $projectItems[] = $newProject;
            $projectIndex = count($projectItems) - 1;
            $createdCount++;
            $nextProjectNumber++;

            $newRefKey = app_normalize_lookup_key((string) ($newProject['ref'] ?? ''));
            $newTitleKey = app_normalize_lookup_key((string) ($newProject['title'] ?? ''));
            if ($newRefKey !== '') {
                $projectIndexesByRef[$newRefKey] = $projectIndex;
            }
            if ($newTitleKey !== '' && !isset($projectIndexesByTitle[$newTitleKey])) {
                $projectIndexesByTitle[$newTitleKey] = $projectIndex;
            }
        }

        $projectMetadata = app_read_project_metadata_from_row($row['cells']);
        $projectItems[$projectIndex]['riskGain'] = $projectMetadata['riskGain'];
        $projectItems[$projectIndex]['budgetEstimate'] = $projectMetadata['budgetEstimate'];
        $projectItems[$projectIndex]['prioritization'] = $projectMetadata['prioritization'];

        $startDate = app_parse_excel_date_value($row['cells'][$startColumn] ?? null);
        $endDate = app_parse_excel_date_value($row['cells'][$endColumn] ?? null);

        if ($startDate === null && $endDate === null) {
            $projectItems[$projectIndex]['start'] = null;
            $projectItems[$projectIndex]['duration'] = null;
            $projectItems[$projectIndex]['lane'] = null;
            $projectItems[$projectIndex]['startExact'] = null;
            $projectItems[$projectIndex]['endExact'] = null;
            $clearedCount++;
            continue;
        }

        if ($startDate === null || $endDate === null) {
            $unmatchedRows[] = $row['rowNumber'];
            continue;
        }

        if ($endDate < $startDate) {
            $unmatchedRows[] = $row['rowNumber'];
            continue;
        }

        $startSlot = app_snap_date_to_half_month_start($startDate);
        $endSlot = app_snap_date_to_half_month_start($endDate);
        $duration = app_get_half_month_slot_number($endSlot) - app_get_half_month_slot_number($startSlot) + 1;

        $projectItems[$projectIndex]['start'] = $startSlot->format('Y-m-d');
        $projectItems[$projectIndex]['duration'] = $duration;
        $projectItems[$projectIndex]['lane'] = $scheduledLane++;
        $projectItems[$projectIndex]['startExact'] = $startDate->format('Y-m-d');
        $projectItems[$projectIndex]['endExact'] = $endDate->format('Y-m-d');
        $updatedCount++;
    }

    $projects = app_store_projects(array_values($projectItems));

    return [
        'projects' => $projects,
        'summary' => [
            'updatedCount' => $updatedCount,
            'clearedCount' => $clearedCount,
            'createdCount' => $createdCount,
            'unmatchedCount' => count($unmatchedRows),
            'unmatchedRows' => $unmatchedRows,
        ],
    ];
}

function app_find_projects_header_row(array $rows): ?array
{
    foreach (array_slice($rows, 0, 10) as $row) {
        $headers = [];
        foreach (($row['cells'] ?? []) as $column => $value) {
            $headers[$column] = app_normalize_lookup_key((string) $value);
        }

        $hasRef = app_find_column_by_headers($headers, ['ref', 'reference']) !== null;
        $hasProject = app_find_column_by_headers($headers, ['projet', 'titre', 'intitule', 'title']) !== null;
        $hasStart = app_resolve_start_column($headers) !== null;
        $hasEnd = app_find_column_by_headers($headers, ['datefin', 'fin', 'finprojet', 'datefindeprojet']) !== null;

        if ($hasRef && $hasProject && ($hasStart || $hasEnd)) {
            return $row;
        }
    }

    return null;
}

function app_resolve_start_column(array $headersByColumn): ?string
{
    if (isset($headersByColumn['J']) && strpos($headersByColumn['J'], 'date') !== false) {
        return 'J';
    }

    return app_find_column_by_headers($headersByColumn, [
        'datedebut',
        'datededebut',
        'debut',
        'debutprojet',
        'start',
    ]);
}

function app_match_project_index_from_row(
    array $rowCells,
    array $headersByColumn,
    array $projectIndexesByRef,
    array $projectIndexesByTitle
): ?int {
    $refColumn = app_find_column_by_headers($headersByColumn, [
        'ref',
        'reference',
        'id',
        'code',
        'codeprojet',
        'referenceprojet',
    ]);

    if ($refColumn !== null) {
        $refKey = app_normalize_lookup_key((string) ($rowCells[$refColumn] ?? ''));
        if ($refKey !== '' && isset($projectIndexesByRef[$refKey])) {
            return $projectIndexesByRef[$refKey];
        }
    }

    $titleColumn = app_find_column_by_headers($headersByColumn, [
        'projet',
        'nomprojet',
        'intitule',
        'intituleprojet',
        'libelle',
        'titre',
        'title',
    ]);

    if ($titleColumn !== null) {
        $titleKey = app_normalize_lookup_key((string) ($rowCells[$titleColumn] ?? ''));
        if ($titleKey !== '' && isset($projectIndexesByTitle[$titleKey])) {
            return $projectIndexesByTitle[$titleKey];
        }
    }

    foreach ($rowCells as $value) {
        $candidate = app_normalize_lookup_key((string) $value);
        if ($candidate === '') {
            continue;
        }

        if (isset($projectIndexesByRef[$candidate])) {
            return $projectIndexesByRef[$candidate];
        }

        if (isset($projectIndexesByTitle[$candidate])) {
            return $projectIndexesByTitle[$candidate];
        }
    }

    return null;
}

function app_build_project_from_row(array $rowCells, array $headersByColumn, int $nextProjectNumber): ?array
{
    $ref = app_read_project_row_value($rowCells, $headersByColumn, [
        'ref',
        'reference',
        'id',
        'code',
        'codeprojet',
        'referenceprojet',
    ]);
    $title = app_read_project_row_value($rowCells, $headersByColumn, [
        'projet',
        'nomprojet',
        'intitule',
        'intituleprojet',
        'libelle',
        'titre',
        'title',
    ]);

    if ($ref === null && $title === null) {
        return null;
    }

    $service = app_read_project_row_value($rowCells, $headersByColumn, [
        'service',
        'serviceprescripteur',
        'serviceporteur',
        'responsable',
    ]);
    $description = app_read_project_row_value($rowCells, $headersByColumn, [
        'details',
        'detail',
        'description',
        'commentaire',
        'commentaires',
    ]);

    return [
        'id' => sprintf('prj%03d', $nextProjectNumber),
        'ref' => $ref ?? sprintf('PRJ%03d', $nextProjectNumber),
        'title' => $title ?? ($ref ?? sprintf('Projet %03d', $nextProjectNumber)),
        'service' => $service ?? 'Non renseigné',
        'description' => $description ?? '',
        'color' => '',
        'start' => null,
        'duration' => null,
        'lane' => null,
        'startExact' => null,
        'endExact' => null,
        'riskGain' => null,
        'budgetEstimate' => null,
        'prioritization' => null,
    ];
}

function app_read_project_row_value(array $rowCells, array $headersByColumn, array $candidates): ?string
{
    $column = app_find_column_by_headers($headersByColumn, $candidates);
    if ($column === null) {
        return null;
    }

    return app_normalize_project_metadata_value($rowCells[$column] ?? null);
}

function app_resolve_next_project_number(array $projects): int
{
    $maxProjectNumber = 0;

    foreach ($projects as $project) {
        $projectId = (string) ($project['id'] ?? '');
        if (preg_match('/prj(\d+)/i', $projectId, $matches) === 1) {
            $maxProjectNumber = max($maxProjectNumber, (int) $matches[1]);
        }
    }

    return $maxProjectNumber + 1;
}

function app_find_column_by_headers(array $headersByColumn, array $candidates): ?string
{
    foreach ($headersByColumn as $column => $header) {
        if (in_array($header, $candidates, true)) {
            return $column;
        }
    }

    return null;
}

function app_read_project_metadata_from_row(array $rowCells): array
{
    return [
        'riskGain' => app_normalize_project_metadata_value($rowCells['G'] ?? null),
        'budgetEstimate' => app_normalize_project_metadata_value($rowCells['H'] ?? null),
        'prioritization' => app_normalize_project_metadata_value($rowCells['I'] ?? null),
    ];
}

function app_normalize_project_metadata_value($value): ?string
{
    $normalized = trim((string) $value);
    return $normalized !== '' ? $normalized : null;
}

function app_normalize_lookup_key(string $value): string
{
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($transliterated !== false) {
        $value = $transliterated;
    }

    $value = preg_replace('/[^a-z0-9]+/', '', $value);
    return $value ?? '';
}

function app_excel_read_rows(string $workbookPath): array
{
    $zip = new ZipArchive();
    if ($zip->open($workbookPath) !== true) {
        throw new RuntimeException('Impossible d\'ouvrir le fichier Excel.');
    }

    try {
        $sharedStrings = app_excel_read_shared_strings($zip);
        $workbookSheets = app_excel_get_workbook_sheets($zip);
        $bestSheetRows = [];
        $bestScore = PHP_INT_MIN;

        foreach ($workbookSheets as $sheet) {
            $rows = app_excel_read_rows_from_sheet($zip, $sheet['path'], $sharedStrings);
            $score = app_excel_score_sheet($sheet['name'], $rows);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestSheetRows = $rows;
            }
        }

        return $bestSheetRows;
    } finally {
        $zip->close();
    }
}

function app_excel_get_workbook_sheets(ZipArchive $zip): array
{
    $workbookXml = app_excel_load_xml($zip, 'xl/workbook.xml');
    $workbookXml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $sheetNodes = $workbookXml->xpath('/main:workbook/main:sheets/main:sheet');
    if (!$sheetNodes || empty($sheetNodes[0])) {
        throw new RuntimeException('Aucune feuille de calcul trouvée dans le classeur.');
    }

    $relsXml = app_excel_load_xml($zip, 'xl/_rels/workbook.xml.rels');
    $relsXml->registerXPathNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');
    $targetsByRelationshipId = [];

    foreach ($relsXml->xpath('/rel:Relationships/rel:Relationship') ?: [] as $relationshipNode) {
        $target = (string) $relationshipNode['Target'];
        $targetsByRelationshipId[(string) $relationshipNode['Id']] = strpos($target, 'xl/') === 0
            ? $target
            : 'xl/' . ltrim($target, '/');
    }

    $sheets = [];
    foreach ($sheetNodes as $sheetNode) {
        $relationshipId = (string) $sheetNode->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
        if (!isset($targetsByRelationshipId[$relationshipId])) {
            continue;
        }

        $sheets[] = [
            'name' => (string) $sheetNode['name'],
            'path' => $targetsByRelationshipId[$relationshipId],
        ];
    }

    if (empty($sheets)) {
        throw new RuntimeException('Impossible de résoudre les feuilles du classeur.');
    }

    return $sheets;
}

function app_excel_read_rows_from_sheet(ZipArchive $zip, string $sheetPath, array $sharedStrings): array
{
    $sheetXml = app_excel_load_xml($zip, $sheetPath);
    $sheetXml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $rowNodes = $sheetXml->xpath('/main:worksheet/main:sheetData/main:row');
    $rows = [];

    foreach ($rowNodes ?: [] as $rowNode) {
        $rowNode->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $cells = [];
        foreach ($rowNode->xpath('main:c') ?: [] as $cellNode) {
            $reference = (string) $cellNode['r'];
            $column = preg_replace('/\d+/', '', $reference);
            if ($column === '') {
                continue;
            }

            $cells[$column] = app_excel_resolve_cell_value($cellNode, $sharedStrings);
        }

        $rows[] = [
            'rowNumber' => (int) $rowNode['r'],
            'cells' => $cells,
        ];
    }

    return $rows;
}

function app_excel_score_sheet(string $sheetName, array $rows): int
{
    if (empty($rows)) {
        return PHP_INT_MIN;
    }

    $sheetKey = app_normalize_lookup_key($sheetName);
    $score = 0;
    if (strpos($sheetKey, 'gantt') !== false) {
        $score += 10;
    }
    if (strpos($sheetKey, 'projet') !== false || strpos($sheetKey, 'planning') !== false) {
        $score += 4;
    }

    $headers = [];
    foreach (($rows[0]['cells'] ?? []) as $value) {
        $headers[] = app_normalize_lookup_key((string) $value);
    }

    if (in_array('datefin', $headers, true)) {
        $score += 8;
    }
    if (in_array('ref', $headers, true) || in_array('reference', $headers, true)) {
        $score += 4;
    }
    if (in_array('projet', $headers, true) || in_array('titre', $headers, true) || in_array('intitule', $headers, true)) {
        $score += 4;
    }
    if (isset(($rows[0]['cells'] ?? [])['J']) && strpos(app_normalize_lookup_key((string) $rows[0]['cells']['J']), 'date') !== false) {
        $score += 4;
    }

    foreach (array_slice($rows, 1, 5) as $row) {
        foreach (($row['cells'] ?? []) as $value) {
            if (preg_match('/^prj\d+$/i', trim((string) $value)) === 1) {
                $score += 6;
                break 2;
            }
        }
    }

    return $score;
}

function app_excel_read_shared_strings(ZipArchive $zip): array
{
    $index = $zip->locateName('xl/sharedStrings.xml');
    if ($index === false) {
        return [];
    }

    $xml = app_excel_load_xml($zip, 'xl/sharedStrings.xml');
    $xml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $items = [];

    foreach ($xml->xpath('/main:sst/main:si') ?: [] as $itemNode) {
        $itemNode->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $directText = $itemNode->xpath('main:t');
        if ($directText && isset($directText[0])) {
            $items[] = (string) $directText[0];
            continue;
        }

        $parts = [];
        foreach ($itemNode->xpath('main:r/main:t') ?: [] as $textNode) {
            $parts[] = (string) $textNode;
        }
        $items[] = implode('', $parts);
    }

    return $items;
}

function app_excel_resolve_cell_value(SimpleXMLElement $cellNode, array $sharedStrings): string
{
    $type = (string) $cellNode['t'];

    if ($type === 's') {
        $stringIndex = (int) ($cellNode->v ?? 0);
        return (string) ($sharedStrings[$stringIndex] ?? '');
    }

    if ($type === 'inlineStr') {
        $cellNode->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $textParts = [];
        foreach ($cellNode->xpath('main:is/main:t|main:is/main:r/main:t') ?: [] as $textNode) {
            $textParts[] = (string) $textNode;
        }
        return implode('', $textParts);
    }

    if (!isset($cellNode->v)) {
        return '';
    }

    return (string) $cellNode->v;
}

function app_excel_load_xml(ZipArchive $zip, string $path): SimpleXMLElement
{
    $content = $zip->getFromName($path);
    if ($content === false) {
        throw new RuntimeException(sprintf('Entrée Excel introuvable: %s', $path));
    }

    $xml = simplexml_load_string($content);
    if (!$xml instanceof SimpleXMLElement) {
        throw new RuntimeException(sprintf('Impossible de lire le XML Excel: %s', $path));
    }

    return $xml;
}

function app_parse_excel_date_value($value): ?DateTimeImmutable
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    if (preg_match('/^\d+(?:\.\d+)?$/', $value) === 1) {
        $serial = (float) $value;
        $days = (int) floor($serial);
        return (new DateTimeImmutable('1899-12-30'))->modify(sprintf('+%d days', $days));
    }

    foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'j/n/Y', 'j-n-Y'] as $format) {
        $parsed = DateTimeImmutable::createFromFormat($format, $value);
        if ($parsed instanceof DateTimeImmutable) {
            return $parsed;
        }
    }

    try {
        return new DateTimeImmutable($value);
    } catch (Exception $exception) {
        return null;
    }
}

function app_snap_date_to_half_month_start(DateTimeImmutable $date): DateTimeImmutable
{
    $day = (int) $date->format('d');
    return $date->setDate(
        (int) $date->format('Y'),
        (int) $date->format('m'),
        $day >= 15 ? 15 : 1
    );
}

function app_get_half_month_slot_number(DateTimeImmutable $date): int
{
    $year = (int) $date->format('Y');
    $month = (int) $date->format('m');
    $day = (int) $date->format('d');

    return (($year * 12) + ($month - 1)) * 2 + ($day >= 15 ? 1 : 0);
}

function app_export_projects_to_workbook(array $projects): array
{
    $templateFile = app_source_workbook_file();
    if (!is_file($templateFile)) {
        throw new RuntimeException('Aucun fichier source à exporter n\'a été trouvé à la racine.');
    }

    $exportDir = app_exports_dir();
    if (!is_dir($exportDir) && !mkdir($exportDir, 0777, true) && !is_dir($exportDir)) {
        throw new RuntimeException('Impossible de créer le dossier export.');
    }

    $baseName = pathinfo($templateFile, PATHINFO_FILENAME);
    $extension = pathinfo($templateFile, PATHINFO_EXTENSION) ?: 'xlsx';
    $timestamp = (new DateTimeImmutable())->format('Ymd-His');
    $fileName = sprintf('%s-%s.%s', $baseName, $timestamp, $extension);
    $targetFile = $exportDir . '/' . $fileName;

    if (!copy($templateFile, $targetFile)) {
        throw new RuntimeException('Impossible de créer la copie Excel exportée.');
    }

    app_write_projects_to_workbook($targetFile, $projects);

    return [
        'fileName' => $fileName,
        'filePath' => $targetFile,
        'downloadUrl' => app_gantt_export_download_url($fileName),
    ];
}

function app_write_projects_to_workbook(string $workbookPath, array $projects): void
{
    $zip = new ZipArchive();
    if ($zip->open($workbookPath) !== true) {
        throw new RuntimeException('Impossible d\'ouvrir le fichier export Excel.');
    }

    try {
        $sharedStrings = app_excel_read_shared_strings($zip);
        [$sheet, $rows, $headerRow] = app_select_projects_sheet($zip, $sharedStrings);
        $resolvedRowsByNumber = [];
        foreach ($rows as $resolvedRow) {
            $resolvedRowsByNumber[(int) $resolvedRow['rowNumber']] = $resolvedRow['cells'] ?? [];
        }

        $headersByColumn = [];
        foreach ($headerRow['cells'] as $column => $value) {
            $headersByColumn[$column] = app_normalize_lookup_key((string) $value);
        }

        $startColumn = app_resolve_start_column($headersByColumn);
        $endColumn = app_find_column_by_headers($headersByColumn, [
            'datefin',
            'datefincible',
            'datefinduprojet',
            'datefindeprojet',
            'fin',
            'finprojet',
        ]);

        if ($startColumn === null || $endColumn === null) {
            throw new RuntimeException('Impossible de détecter les colonnes Début / Fin du modèle Excel.');
        }

        $projectsByRef = [];
        $projectsByTitle = [];
        foreach ($projects as $project) {
            $refKey = app_normalize_lookup_key((string) ($project['ref'] ?? ''));
            $titleKey = app_normalize_lookup_key((string) ($project['title'] ?? ''));
            if ($refKey !== '') {
                $projectsByRef[$refKey] = $project;
            }
            if ($titleKey !== '' && !isset($projectsByTitle[$titleKey])) {
                $projectsByTitle[$titleKey] = $project;
            }
        }

        $sheetXml = $zip->getFromName($sheet['path']);
        if ($sheetXml === false) {
            throw new RuntimeException('Impossible de lire la feuille à exporter.');
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        $dom->loadXML($sheetXml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rowNodes = $xpath->query('/main:worksheet/main:sheetData/main:row');
        if (!$rowNodes instanceof DOMNodeList) {
            throw new RuntimeException('Impossible de parcourir la feuille Excel.');
        }

        $startColumnStyle = app_excel_find_column_style($rowNodes, (int) $headerRow['rowNumber'], $startColumn);
        $endColumnStyle = app_excel_find_column_style($rowNodes, (int) $headerRow['rowNumber'], $endColumn);

        foreach ($rowNodes as $rowNode) {
            if (!$rowNode instanceof DOMElement) {
                continue;
            }

            $rowNumber = (int) $rowNode->getAttribute('r');
            if ($rowNumber <= (int) $headerRow['rowNumber']) {
                continue;
            }

            $rowCells = $resolvedRowsByNumber[$rowNumber] ?? [];
            if (empty($rowCells)) {
                continue;
            }

            $project = app_match_project_from_row_cells($rowCells, $headersByColumn, $projectsByRef, $projectsByTitle);
            if ($project === null) {
                continue;
            }

            $exportDates = app_get_project_export_dates($project);
            app_excel_write_dom_date_cell($dom, $rowNode, $startColumn, $exportDates['start'], $startColumnStyle);
            app_excel_write_dom_date_cell($dom, $rowNode, $endColumn, $exportDates['end'], $endColumnStyle);
        }

        $zip->deleteName($sheet['path']);
        $zip->addFromString($sheet['path'], $dom->saveXML());
    } finally {
        $zip->close();
    }
}

function app_select_projects_sheet(ZipArchive $zip, array $sharedStrings): array
{
    $workbookSheets = app_excel_get_workbook_sheets($zip);
    $bestSheet = null;
    $bestRows = [];
    $bestHeaderRow = null;
    $bestScore = PHP_INT_MIN;

    foreach ($workbookSheets as $sheet) {
        $rows = app_excel_read_rows_from_sheet($zip, $sheet['path'], $sharedStrings);
        $headerRow = app_find_projects_header_row($rows);
        $score = app_excel_score_sheet($sheet['name'], $rows);

        if ($headerRow !== null) {
            $score += 20;
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestSheet = $sheet;
            $bestRows = $rows;
            $bestHeaderRow = $headerRow;
        }
    }

    if ($bestSheet === null || $bestHeaderRow === null) {
        throw new RuntimeException('Impossible de trouver une feuille projets exploitable dans le fichier source.');
    }

    return [$bestSheet, $bestRows, $bestHeaderRow];
}

function app_match_project_from_row_cells(
    array $rowCells,
    array $headersByColumn,
    array $projectsByRef,
    array $projectsByTitle
): ?array {
    $refColumn = app_find_column_by_headers($headersByColumn, [
        'ref',
        'reference',
        'id',
        'code',
        'codeprojet',
        'referenceprojet',
    ]);

    if ($refColumn !== null) {
        $refKey = app_normalize_lookup_key((string) ($rowCells[$refColumn] ?? ''));
        if ($refKey !== '' && isset($projectsByRef[$refKey])) {
            return $projectsByRef[$refKey];
        }
    }

    $titleColumn = app_find_column_by_headers($headersByColumn, [
        'projet',
        'nomprojet',
        'intitule',
        'intituleprojet',
        'libelle',
        'titre',
        'title',
    ]);

    if ($titleColumn !== null) {
        $titleKey = app_normalize_lookup_key((string) ($rowCells[$titleColumn] ?? ''));
        if ($titleKey !== '' && isset($projectsByTitle[$titleKey])) {
            return $projectsByTitle[$titleKey];
        }
    }

    foreach ($rowCells as $value) {
        $candidate = app_normalize_lookup_key((string) $value);
        if ($candidate === '') {
            continue;
        }

        if (isset($projectsByRef[$candidate])) {
            return $projectsByRef[$candidate];
        }

        if (isset($projectsByTitle[$candidate])) {
            return $projectsByTitle[$candidate];
        }
    }

    return null;
}

function app_get_project_export_dates(array $project): array
{
    $startExact = app_parse_excel_date_value($project['startExact'] ?? null);
    $endExact = app_parse_excel_date_value($project['endExact'] ?? null);

    if ($startExact instanceof DateTimeImmutable && $endExact instanceof DateTimeImmutable) {
        return [
            'start' => $startExact,
            'end' => $endExact,
        ];
    }

    $start = app_parse_excel_date_value($project['start'] ?? null);
    $duration = isset($project['duration']) ? (int) $project['duration'] : 0;
    if (!$start instanceof DateTimeImmutable || $duration < 1) {
        return [
            'start' => null,
            'end' => null,
        ];
    }

    $endSlot = app_add_half_months($start, $duration - 1);
    return [
        'start' => $start,
        'end' => app_get_half_month_end_date($endSlot),
    ];
}

function app_add_half_months(DateTimeImmutable $date, int $delta): DateTimeImmutable
{
    $monthIndex = (((int) $date->format('Y')) * 12) + ((int) $date->format('m')) - 1;
    $slot = ((int) $date->format('d')) >= 15 ? 1 : 0;
    $totalSlots = ($monthIndex * 2) + $slot + $delta;
    $targetMonthIndex = (int) floor($totalSlots / 2);
    $targetSlot = $totalSlots % 2;
    $targetYear = (int) floor($targetMonthIndex / 12);
    $targetMonth = ($targetMonthIndex % 12) + 1;
    $targetDay = $targetSlot === 1 ? 15 : 1;

    return (new DateTimeImmutable())->setDate($targetYear, $targetMonth, $targetDay)->setTime(0, 0);
}

function app_get_half_month_end_date(DateTimeImmutable $date): DateTimeImmutable
{
    if ((int) $date->format('d') >= 15) {
        return $date->modify('last day of this month');
    }

    return $date->setDate((int) $date->format('Y'), (int) $date->format('m'), 14);
}

function app_excel_write_dom_date_cell(
    DOMDocument $dom,
    DOMElement $rowNode,
    string $column,
    ?DateTimeImmutable $date,
    ?string $styleId
): void
{
    $rowNumber = $rowNode->getAttribute('r');
    $cellRef = $column . $rowNumber;
    $cellNode = app_excel_find_dom_cell($rowNode, $cellRef);

    if ($cellNode === null && $date === null) {
        return;
    }

    if ($cellNode === null) {
        $cellNode = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'c');
        $cellNode->setAttribute('r', $cellRef);
        $rowNode->appendChild($cellNode);
    }

    if ($styleId !== null && $styleId !== '' && $cellNode->getAttribute('s') === '') {
        $cellNode->setAttribute('s', $styleId);
    }

    while ($cellNode->firstChild) {
        $cellNode->removeChild($cellNode->firstChild);
    }
    $cellNode->removeAttribute('t');

    if ($date === null) {
        return;
    }

    $valueNode = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'v', (string) app_excel_date_to_serial($date));
    $cellNode->appendChild($valueNode);
}

function app_excel_find_column_style(DOMNodeList $rowNodes, int $headerRowNumber, string $column): ?string
{
    foreach ($rowNodes as $rowNode) {
        if (!$rowNode instanceof DOMElement) {
            continue;
        }

        $rowNumber = (int) $rowNode->getAttribute('r');
        if ($rowNumber <= $headerRowNumber) {
            continue;
        }

        $cellNode = app_excel_find_dom_cell($rowNode, $column . $rowNumber);
        if ($cellNode === null) {
            continue;
        }

        $styleId = $cellNode->getAttribute('s');
        if ($styleId !== '') {
            return $styleId;
        }
    }

    return null;
}

function app_excel_find_dom_cell(DOMElement $rowNode, string $cellRef): ?DOMElement
{
    foreach ($rowNode->childNodes as $childNode) {
        if (!$childNode instanceof DOMElement || $childNode->localName !== 'c') {
            continue;
        }

        if ($childNode->getAttribute('r') === $cellRef) {
            return $childNode;
        }
    }

    return null;
}

function app_excel_date_to_serial(DateTimeImmutable $date): int
{
    $baseDate = new DateTimeImmutable('1899-12-30');
    $diff = $baseDate->diff($date->setTime(0, 0));
    return (int) $diff->format('%a');
}
