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

function app_chronological_export_layout_file(): string
{
    return rtrim((string) APP_GANTT_STORAGE_DIR, "\\/") . '/chronological-export-layout.json';
}

function app_normalize_chronological_export_column_width(float $width): float
{
    return max(4.0, min(120.0, $width));
}

function app_read_chronological_export_column_widths(): array
{
    $payload = app_read_json_file(app_chronological_export_layout_file());
    $rawWidths = is_array($payload['columnWidths'] ?? null) ? $payload['columnWidths'] : [];
    $widths = [];

    foreach ($rawWidths as $columnIndex => $width) {
        $normalizedColumnIndex = (int) $columnIndex;
        $normalizedWidth = (float) $width;
        if ($normalizedColumnIndex <= 0 || $normalizedWidth <= 0) {
            continue;
        }

        $widths[$normalizedColumnIndex] = app_normalize_chronological_export_column_width($normalizedWidth);
    }

    return $widths;
}

function app_store_chronological_export_column_widths(array $columnWidths): void
{
    $normalizedWidths = [];

    foreach ($columnWidths as $columnIndex => $width) {
        $normalizedColumnIndex = (int) $columnIndex;
        $normalizedWidth = (float) $width;
        if ($normalizedColumnIndex <= 0 || $normalizedWidth <= 0) {
            continue;
        }

        $normalizedWidths[(string) $normalizedColumnIndex] = app_normalize_chronological_export_column_width($normalizedWidth);
    }

    if ($normalizedWidths === []) {
        return;
    }

    app_write_json_file(app_chronological_export_layout_file(), [
        'updatedAt' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
        'columnWidths' => $normalizedWidths,
    ]);
}

function app_capture_chronological_export_layout_from_workbook(string $workbookPath): void
{
    try {
        $columnWidths = app_extract_chronological_export_column_widths_from_workbook($workbookPath);
    } catch (Throwable) {
        return;
    }

    if ($columnWidths !== []) {
        app_store_chronological_export_column_widths($columnWidths);
    }
}

function app_extract_chronological_export_column_widths_from_workbook(string $workbookPath): array
{
    if (!app_is_xlsx_workbook_file($workbookPath)) {
        return [];
    }

    $zip = new ZipArchive();
    if ($zip->open($workbookPath) !== true) {
        return [];
    }

    try {
        $sharedStrings = app_excel_read_shared_strings($zip);
        $workbookSheets = app_excel_get_workbook_sheets($zip);
        $bestSheetPath = '';
        $bestScore = PHP_INT_MIN;

        foreach ($workbookSheets as $sheet) {
            $rows = app_excel_read_rows_from_sheet($zip, $sheet['path'], $sharedStrings);
            $score = app_excel_score_sheet((string) ($sheet['name'] ?? ''), $rows);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestSheetPath = (string) ($sheet['path'] ?? '');
            }
        }

        if ($bestSheetPath === '') {
            return [];
        }

        return app_excel_read_sheet_column_widths($zip, $bestSheetPath);
    } finally {
        $zip->close();
    }
}

function app_excel_read_sheet_column_widths(ZipArchive $zip, string $sheetPath): array
{
    $sheetXml = app_excel_load_xml($zip, $sheetPath);
    $sheetXml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $columnNodes = $sheetXml->xpath('/main:worksheet/main:cols/main:col');
    if (!$columnNodes) {
        return [];
    }

    $columnWidths = [];

    foreach ($columnNodes as $columnNode) {
        $min = (int) ($columnNode['min'] ?? 0);
        $max = (int) ($columnNode['max'] ?? $min);
        $width = (float) ($columnNode['width'] ?? 0);
        if ($min <= 0 || $max < $min || $width <= 0) {
            continue;
        }

        for ($columnIndex = $min; $columnIndex <= $max; $columnIndex++) {
            if ($columnIndex > 11) {
                break;
            }

            $columnWidths[$columnIndex] = app_normalize_chronological_export_column_width($width);
        }
    }

    return $columnWidths;
}

function app_export_template_workbook_file(): string
{
    $sourceFile = app_source_workbook_file();
    if (is_file($sourceFile)) {
        return $sourceFile;
    }

    $exportDir = app_exports_dir();
    if (!is_dir($exportDir)) {
        return $sourceFile;
    }

    $candidates = glob($exportDir . '/*.xlsx') ?: [];
    if ($candidates === []) {
        return $sourceFile;
    }

    usort($candidates, static function (string $left, string $right): int {
        return (int) @filemtime($right) <=> (int) @filemtime($left);
    });

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return $sourceFile;
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

function app_import_projects_from_chronological_workbook(string $workbookPath): array
{
    $rows = app_parse_chronological_spreadsheet_rows($workbookPath);
    if (count($rows) < 2) {
        throw new RuntimeException('Le fichier liste chrono ne contient pas assez de lignes.');
    }

    $headerRow = app_find_chronological_export_header_row($rows);
    if ($headerRow === null) {
        throw new RuntimeException('Impossible de detecter les en-tetes de la liste chrono.');
    }

    app_capture_chronological_export_layout_from_workbook($workbookPath);

    $headersByIndex = [];
    foreach ($headerRow['cells'] as $columnIndex => $value) {
        $headersByIndex[(int) $columnIndex] = app_normalize_lookup_key((string) $value);
    }

    $refColumn = app_find_chronological_export_column_by_headers($headersByIndex, [
        'n0projet',
        'nprojet',
        'numprojet',
        'numeroprojet',
        'ref',
        'reference',
    ]);
    $titleColumn = app_find_chronological_export_column_by_headers($headersByIndex, [
        'titre',
        'title',
        'projet',
        'intitule',
    ]);
    $serviceColumn = app_find_chronological_export_column_by_headers($headersByIndex, ['service']);
    $startColumn = app_find_chronological_export_column_by_headers($headersByIndex, ['datedebut', 'debut', 'start']);
    $endColumn = app_find_chronological_export_column_by_headers($headersByIndex, ['datefin', 'fin', 'end']);
    $youtrackColumn = app_find_chronological_export_youtrack_column($headersByIndex);
    $redmineColumn = app_find_chronological_export_redmine_column($headersByIndex);
    $projectManagerColumn = app_find_chronological_export_column_by_headers($headersByIndex, [
        'chefdeprojet',
        'projectmanager',
        'responsableprojet',
    ]);
    $statusColumn = app_find_chronological_export_column_by_headers($headersByIndex, ['statut', 'status']);

    if ($refColumn === null || $titleColumn === null || $statusColumn === null) {
        throw new RuntimeException('Les colonnes Ref / Titre / Statut sont introuvables dans la liste chrono.');
    }

    $projectItems = array_values(app_fetch_projects());
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
    $currentParentProjectId = null;
    $nextProjectNumber = app_resolve_next_project_number($projectItems);

    foreach ($rows as $row) {
        if (($row['rowNumber'] ?? 0) <= ($headerRow['rowNumber'] ?? 0)) {
            continue;
        }

        $rowCells = is_array($row['cells'] ?? null) ? $row['cells'] : [];
        $rowLinks = is_array($row['links'] ?? null) ? $row['links'] : [];
        if ($rowCells === [] || app_is_chronological_export_footer_row($rowCells)) {
            continue;
        }

        $projectIndex = app_match_project_index_from_chronological_export_row(
            $rowCells,
            $headersByIndex,
            $projectIndexesByRef,
            $projectIndexesByTitle
        );
        $matchedProject = $projectIndex !== null ? ($projectItems[$projectIndex] ?? null) : null;
        $isParentRow = app_is_chronological_export_parent_row($rowCells, $headersByIndex, $matchedProject);

        if ($isParentRow) {
            if (!is_array($matchedProject)) {
                $unmatchedRows[] = (int) ($row['rowNumber'] ?? 0);
                $currentParentProjectId = null;
                continue;
            }

            $currentParentProjectId = app_normalize_project_nullable_string($matchedProject['id'] ?? null);
            $beforeProject = $matchedProject;
            $updatedProject = $matchedProject;
            $title = app_read_chronological_export_row_value($rowCells, $titleColumn);
            $youtrackValue = $youtrackColumn !== null ? app_read_chronological_export_row_value($rowCells, $youtrackColumn) : null;
            $redmineValue = $redmineColumn !== null ? app_read_chronological_export_row_value($rowCells, $redmineColumn) : null;
            $youtrackLink = $youtrackColumn !== null ? app_read_chronological_export_row_link($rowLinks, $youtrackColumn) : null;
            $redmineLink = $redmineColumn !== null ? app_read_chronological_export_row_link($rowLinks, $redmineColumn) : null;

            if ($title !== null) {
                $updatedProject['title'] = $title;
            }

            if ($youtrackColumn !== null) {
                $updatedProject['youtrackTicketUrl'] = app_resolve_project_youtrack_url_from_export_cell($youtrackValue, $youtrackLink);
            }

            if ($redmineColumn !== null) {
                $updatedProject['redmineUrl'] = app_resolve_project_redmine_url_from_export_cell($redmineValue, $redmineLink);
            }

            if (app_have_project_values_changed($beforeProject, $updatedProject)) {
                $projectItems[$projectIndex] = $updatedProject;
                $updatedCount++;
            }

            continue;
        }

        if ($projectIndex === null) {
            $pendingStartValue = $startColumn !== null ? app_read_chronological_export_row_value($rowCells, $startColumn) : null;
            $pendingEndValue = $endColumn !== null ? app_read_chronological_export_row_value($rowCells, $endColumn) : null;

            if (($pendingStartValue === null) xor ($pendingEndValue === null)) {
                $unmatchedRows[] = (int) ($row['rowNumber'] ?? 0);
                continue;
            }

            if ($pendingStartValue !== null && $pendingEndValue !== null) {
                $pendingStartDate = app_parse_excel_date_value($pendingStartValue);
                $pendingEndDate = app_parse_excel_date_value($pendingEndValue);

                if (
                    !$pendingStartDate instanceof DateTimeImmutable
                    || !$pendingEndDate instanceof DateTimeImmutable
                    || $pendingEndDate < $pendingStartDate
                ) {
                    $unmatchedRows[] = (int) ($row['rowNumber'] ?? 0);
                    continue;
                }
            }

            $newProject = app_build_project_from_chronological_export_row(
                $rowCells,
                $rowLinks,
                $headersByIndex,
                $nextProjectNumber,
                $currentParentProjectId
            );

            if ($newProject === null) {
                $unmatchedRows[] = (int) ($row['rowNumber'] ?? 0);
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

        $beforeProject = $projectItems[$projectIndex];
        $updatedProject = $beforeProject;
        $title = app_read_chronological_export_row_value($rowCells, $titleColumn);
        $service = $serviceColumn !== null ? app_read_chronological_export_row_value($rowCells, $serviceColumn) : null;
        $startValue = $startColumn !== null ? app_read_chronological_export_row_value($rowCells, $startColumn) : null;
        $endValue = $endColumn !== null ? app_read_chronological_export_row_value($rowCells, $endColumn) : null;
        $youtrackValue = $youtrackColumn !== null ? app_read_chronological_export_row_value($rowCells, $youtrackColumn) : null;
        $redmineValue = $redmineColumn !== null ? app_read_chronological_export_row_value($rowCells, $redmineColumn) : null;
        $youtrackLink = $youtrackColumn !== null ? app_read_chronological_export_row_link($rowLinks, $youtrackColumn) : null;
        $redmineLink = $redmineColumn !== null ? app_read_chronological_export_row_link($rowLinks, $redmineColumn) : null;
        $projectManager = $projectManagerColumn !== null ? app_read_chronological_export_row_value($rowCells, $projectManagerColumn) : null;
        $statusValue = app_read_chronological_export_row_value($rowCells, $statusColumn);
        $effectiveStatusKey = app_normalize_lookup_key($statusValue ?? (string) ($updatedProject['status'] ?? ''));
        $storedStatusKey = app_normalize_lookup_key((string) ($beforeProject['status'] ?? ''));
        $datesWereMaskedByExport = $storedStatusKey === 'aplanifier' && app_project_has_chronological_schedule($beforeProject);
        $scheduleWasCleared = false;

        if ($title !== null) {
            $updatedProject['title'] = $title;
        }

        if ($service !== null) {
            $updatedProject['service'] = $service;
        }

        if ($currentParentProjectId !== null) {
            $updatedProject['parentProjectId'] = $currentParentProjectId;
        }

        if ($projectManagerColumn !== null) {
            $updatedProject['projectManager'] = app_normalize_project_nullable_string($projectManager);
        }

        if ($youtrackColumn !== null) {
            $updatedProject['youtrackTicketUrl'] = app_resolve_project_youtrack_url_from_export_cell($youtrackValue, $youtrackLink);
        }

        if ($redmineColumn !== null) {
            $updatedProject['redmineUrl'] = app_resolve_project_redmine_url_from_export_cell($redmineValue, $redmineLink);
        }

        if ($startValue === null && $endValue === null) {
            if ($effectiveStatusKey === 'aplanifier' || $datesWereMaskedByExport) {
                // Les dates sont volontairement masquees dans l'export chrono pour ce statut.
                // Si le projet avait deja un planning, on le conserve tel quel.
            } else {
            $scheduleWasCleared =
                $updatedProject['start'] !== null
                || $updatedProject['duration'] !== null
                || $updatedProject['startExact'] !== null
                || $updatedProject['endExact'] !== null;

                $updatedProject['start'] = null;
                $updatedProject['duration'] = null;
                $updatedProject['lane'] = null;
                $updatedProject['startExact'] = null;
                $updatedProject['endExact'] = null;
            }
        } elseif ($startValue === null || $endValue === null) {
            $unmatchedRows[] = (int) ($row['rowNumber'] ?? 0);
            continue;
        } else {
            $startDate = app_parse_excel_date_value($startValue);
            $endDate = app_parse_excel_date_value($endValue);

            if (!$startDate instanceof DateTimeImmutable || !$endDate instanceof DateTimeImmutable || $endDate < $startDate) {
                $unmatchedRows[] = (int) ($row['rowNumber'] ?? 0);
                continue;
            }

            $startSlot = app_snap_date_to_half_month_start($startDate);
            $endSlot = app_snap_date_to_half_month_start($endDate);
            $duration = app_get_half_month_slot_number($endSlot) - app_get_half_month_slot_number($startSlot) + 1;

            $updatedProject['start'] = $startSlot->format('Y-m-d');
            $updatedProject['duration'] = $duration;
            $updatedProject['startExact'] = $startDate->format('Y-m-d');
            $updatedProject['endExact'] = $endDate->format('Y-m-d');
        }

        if ($statusValue !== null) {
            $updatedProject['status'] = app_normalize_chronological_import_status($statusValue, $updatedProject);
        }

        if (app_have_project_values_changed($beforeProject, $updatedProject)) {
            $projectItems[$projectIndex] = $updatedProject;
            if ($scheduleWasCleared) {
                $clearedCount++;
            } else {
                $updatedCount++;
            }
        }
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

function app_find_chronological_export_header_row(array $rows): ?array
{
    foreach (array_slice($rows, 0, 12) as $row) {
        $headers = [];
        foreach (($row['cells'] ?? []) as $columnIndex => $value) {
            $headers[(int) $columnIndex] = app_normalize_lookup_key((string) $value);
        }

        $hasRef = app_find_chronological_export_column_by_headers($headers, ['n0projet', 'nprojet', 'numprojet', 'numeroprojet', 'ref']) !== null;
        $hasTitle = app_find_chronological_export_column_by_headers($headers, ['titre', 'title', 'projet']) !== null;
        $hasStatus = app_find_chronological_export_column_by_headers($headers, ['statut', 'status']) !== null;

        if ($hasRef && $hasTitle && $hasStatus) {
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

function app_find_chronological_export_column_by_headers(array $headersByIndex, array $candidates): ?int
{
    $normalizedCandidates = array_fill_keys($candidates, true);

    foreach ($headersByIndex as $columnIndex => $headerValue) {
        if (isset($normalizedCandidates[(string) $headerValue])) {
            return (int) $columnIndex;
        }
    }

    return null;
}

function app_find_chronological_export_youtrack_column(array $headersByIndex): ?int
{
    return app_find_chronological_export_column_by_headers($headersByIndex, [
        'youtrack',
        'lienyoutrack',
        'ticketyoutrack',
        'idyoutrack',
    ]);
}

function app_find_chronological_export_redmine_column(array $headersByIndex): ?int
{
    return app_find_chronological_export_column_by_headers($headersByIndex, [
        'rm',
        'lienrm',
        'redmine',
        'lienredmine',
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

function app_build_project_from_chronological_export_row(
    array $rowCells,
    array $rowLinks,
    array $headersByIndex,
    int $nextProjectNumber,
    ?string $parentProjectId
): ?array {
    $refColumn = app_find_chronological_export_column_by_headers($headersByIndex, [
        'n0projet',
        'nprojet',
        'numprojet',
        'numeroprojet',
        'ref',
        'reference',
    ]);
    $titleColumn = app_find_chronological_export_column_by_headers($headersByIndex, [
        'titre',
        'title',
        'projet',
        'intitule',
    ]);
    $serviceColumn = app_find_chronological_export_column_by_headers($headersByIndex, ['service']);
    $startColumn = app_find_chronological_export_column_by_headers($headersByIndex, ['datedebut', 'debut', 'start']);
    $endColumn = app_find_chronological_export_column_by_headers($headersByIndex, ['datefin', 'fin', 'end']);
    $youtrackColumn = app_find_chronological_export_youtrack_column($headersByIndex);
    $redmineColumn = app_find_chronological_export_redmine_column($headersByIndex);
    $projectManagerColumn = app_find_chronological_export_column_by_headers($headersByIndex, [
        'chefdeprojet',
        'projectmanager',
        'responsableprojet',
    ]);
    $statusColumn = app_find_chronological_export_column_by_headers($headersByIndex, ['statut', 'status']);

    $ref = $refColumn !== null ? app_read_chronological_export_row_value($rowCells, $refColumn) : null;
    $title = $titleColumn !== null ? app_read_chronological_export_row_value($rowCells, $titleColumn) : null;
    $service = $serviceColumn !== null ? app_read_chronological_export_row_value($rowCells, $serviceColumn) : null;
    $startValue = $startColumn !== null ? app_read_chronological_export_row_value($rowCells, $startColumn) : null;
    $endValue = $endColumn !== null ? app_read_chronological_export_row_value($rowCells, $endColumn) : null;
    $youtrackValue = $youtrackColumn !== null ? app_read_chronological_export_row_value($rowCells, $youtrackColumn) : null;
    $redmineValue = $redmineColumn !== null ? app_read_chronological_export_row_value($rowCells, $redmineColumn) : null;
    $youtrackLink = $youtrackColumn !== null ? app_read_chronological_export_row_link($rowLinks, $youtrackColumn) : null;
    $redmineLink = $redmineColumn !== null ? app_read_chronological_export_row_link($rowLinks, $redmineColumn) : null;
    $projectManager = $projectManagerColumn !== null ? app_read_chronological_export_row_value($rowCells, $projectManagerColumn) : null;
    $statusValue = $statusColumn !== null ? app_read_chronological_export_row_value($rowCells, $statusColumn) : null;

    if ($ref === null && $title === null) {
        return null;
    }

    $project = [
        'id' => app_generate_project_id(),
        'ref' => $ref ?? sprintf('PRJ%03d', $nextProjectNumber),
        'title' => $title ?? ($ref ?? sprintf('PRJ%03d', $nextProjectNumber)),
        'service' => $service ?? '',
        'parentProjectId' => $parentProjectId,
        'projectType' => null,
        'description' => '',
        'color' => '',
        'customColor' => '',
        'start' => null,
        'duration' => null,
        'lane' => null,
        'startExact' => null,
        'endExact' => null,
        'riskGain' => null,
        'budgetEstimate' => null,
        'prioritization' => null,
        'projectManager' => app_normalize_project_nullable_string($projectManager),
        'status' => 'A planifier',
        'progression' => 0,
        'youtrackId' => null,
        'youtrackUrl' => null,
        'youtrackTicketUrl' => app_resolve_project_youtrack_url_from_export_cell($youtrackValue, $youtrackLink),
        'redmineUrl' => app_resolve_project_redmine_url_from_export_cell($redmineValue, $redmineLink),
        'ownerId' => null,
        'ownerDisplayName' => null,
        'ownerEmail' => null,
        'teamMembers' => [],
        'taskColumns' => [],
    ];

    if ($statusValue !== null) {
        $project['status'] = app_normalize_chronological_import_status($statusValue, $project);
    }

    if ($startValue !== null && $endValue !== null) {
        $startDate = app_parse_excel_date_value($startValue);
        $endDate = app_parse_excel_date_value($endValue);

        if ($startDate instanceof DateTimeImmutable && $endDate instanceof DateTimeImmutable && $endDate >= $startDate) {
            $startSlot = app_snap_date_to_half_month_start($startDate);
            $endSlot = app_snap_date_to_half_month_start($endDate);
            $project['start'] = $startSlot->format('Y-m-d');
            $project['duration'] = app_get_half_month_slot_number($endSlot) - app_get_half_month_slot_number($startSlot) + 1;
            $project['startExact'] = $startDate->format('Y-m-d');
            $project['endExact'] = $endDate->format('Y-m-d');
        }
    }

    return $project;
}

function app_read_project_row_value(array $rowCells, array $headersByColumn, array $candidates): ?string
{
    $column = app_find_column_by_headers($headersByColumn, $candidates);
    if ($column === null) {
        return null;
    }

    return app_normalize_project_metadata_value($rowCells[$column] ?? null);
}

function app_read_chronological_export_row_value(array $rowCells, int $columnIndex): ?string
{
    $value = trim((string) ($rowCells[$columnIndex] ?? ''));

    return $value !== '' ? $value : null;
}

function app_read_chronological_export_row_link(array $rowLinks, int $columnIndex): ?string
{
    $value = trim((string) ($rowLinks[$columnIndex] ?? ''));

    return $value !== '' ? $value : null;
}

function app_project_has_chronological_schedule(array $project): bool
{
    return (
        !empty($project['start'])
        && is_numeric($project['duration'] ?? null)
        && (int) ($project['duration'] ?? 0) > 0
    ) || (
        !empty($project['startExact'])
        && !empty($project['endExact'])
    );
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

function app_match_project_index_from_chronological_export_row(
    array $rowCells,
    array $headersByIndex,
    array $projectIndexesByRef,
    array $projectIndexesByTitle
): ?int {
    $refColumn = app_find_chronological_export_column_by_headers($headersByIndex, [
        'n0projet',
        'nprojet',
        'numprojet',
        'numeroprojet',
        'ref',
        'reference',
        'id',
    ]);

    if ($refColumn !== null) {
        $refKey = app_normalize_lookup_key((string) ($rowCells[$refColumn] ?? ''));
        if ($refKey !== '' && isset($projectIndexesByRef[$refKey])) {
            return $projectIndexesByRef[$refKey];
        }
    }

    $titleColumn = app_find_chronological_export_column_by_headers($headersByIndex, [
        'titre',
        'title',
        'projet',
        'intitule',
    ]);

    if ($titleColumn !== null) {
        $titleKey = app_normalize_lookup_key((string) ($rowCells[$titleColumn] ?? ''));
        if ($titleKey !== '' && isset($projectIndexesByTitle[$titleKey])) {
            return $projectIndexesByTitle[$titleKey];
        }
    }

    return null;
}

function app_is_chronological_export_parent_row(array $rowCells, array $headersByIndex, ?array $matchedProject): bool
{
    if (!is_array($matchedProject) || !app_is_chronological_export_parent_project($matchedProject)) {
        return false;
    }

    $serviceColumn = app_find_chronological_export_column_by_headers($headersByIndex, ['service']);
    $startColumn = app_find_chronological_export_column_by_headers($headersByIndex, ['datedebut', 'debut', 'start']);
    $endColumn = app_find_chronological_export_column_by_headers($headersByIndex, ['datefin', 'fin', 'end']);
    $projectManagerColumn = app_find_chronological_export_column_by_headers($headersByIndex, [
        'chefdeprojet',
        'projectmanager',
        'responsableprojet',
    ]);
    $statusColumn = app_find_chronological_export_column_by_headers($headersByIndex, ['statut', 'status']);

    return ($serviceColumn === null || app_read_chronological_export_row_value($rowCells, $serviceColumn) === null)
        && ($startColumn === null || app_read_chronological_export_row_value($rowCells, $startColumn) === null)
        && ($endColumn === null || app_read_chronological_export_row_value($rowCells, $endColumn) === null)
        && ($projectManagerColumn === null || app_read_chronological_export_row_value($rowCells, $projectManagerColumn) === null)
        && ($statusColumn === null || app_read_chronological_export_row_value($rowCells, $statusColumn) === null);
}

function app_is_chronological_export_footer_row(array $rowCells): bool
{
    if ($rowCells === []) {
        return true;
    }

    return str_contains(
        app_normalize_lookup_key((string) ($rowCells[0] ?? '')),
        'lesprojetssansdate'
    );
}

function app_have_project_values_changed(array $beforeProject, array $afterProject): bool
{
    foreach ([
        'ref',
        'title',
        'service',
        'parentProjectId',
        'start',
        'duration',
        'lane',
        'startExact',
        'endExact',
        'projectManager',
        'youtrackTicketUrl',
        'redmineUrl',
        'status',
    ] as $trackedKey) {
        if (($beforeProject[$trackedKey] ?? null) !== ($afterProject[$trackedKey] ?? null)) {
            return true;
        }
    }

    return false;
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

function app_resolve_project_youtrack_url_from_export_cell(?string $cellValue, ?string $hyperlinkUrl): ?string
{
    $resolvedUrl = app_resolve_project_external_link_url_from_export_cell($cellValue, $hyperlinkUrl);
    if ($resolvedUrl !== null) {
        return $resolvedUrl;
    }

    $ticketKey = app_extract_project_youtrack_ticket_key($cellValue);
    if ($ticketKey === null) {
        return null;
    }

    return sprintf('https://maintenance.adep.com/tickets/%s/', $ticketKey);
}

function app_resolve_project_redmine_url_from_export_cell(?string $cellValue, ?string $hyperlinkUrl): ?string
{
    $resolvedUrl = app_resolve_project_external_link_url_from_export_cell($cellValue, $hyperlinkUrl);
    if ($resolvedUrl !== null) {
        return $resolvedUrl;
    }

    $issueId = app_extract_project_redmine_issue_id($cellValue);
    if ($issueId === null) {
        return null;
    }

    return sprintf('https://redmine.snlogica.com/issues/%s', $issueId);
}

function app_resolve_project_external_link_url_from_export_cell(?string $cellValue, ?string $hyperlinkUrl): ?string
{
    $normalizedHyperlink = app_normalize_project_external_link_url($hyperlinkUrl);
    if ($normalizedHyperlink !== null) {
        return $normalizedHyperlink;
    }

    $normalizedValue = app_normalize_project_nullable_string($cellValue);
    if ($normalizedValue === null || !app_is_probable_project_external_url($normalizedValue)) {
        return null;
    }

    return app_normalize_project_external_link_url($normalizedValue);
}

function app_normalize_project_external_link_url(?string $value): ?string
{
    $normalized = app_normalize_project_nullable_string($value);
    if ($normalized === null) {
        return null;
    }

    if (preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $normalized) !== 1) {
        $normalized = 'https://' . ltrim($normalized, '/');
    }

    return $normalized;
}

function app_is_probable_project_external_url(string $value): bool
{
    if (preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $value) === 1) {
        return true;
    }

    return preg_match('/^[^\s]+\.[^\s]+(?:\/[^\s]*)?$/', $value) === 1;
}

function app_extract_project_youtrack_ticket_key(?string $value): ?string
{
    $normalized = app_normalize_project_nullable_string($value);
    if ($normalized === null) {
        return null;
    }

    if (preg_match('/([A-Z][A-Z0-9]+-\d+)/i', $normalized, $matches) === 1) {
        return strtoupper((string) $matches[1]);
    }

    return null;
}

function app_extract_project_redmine_issue_id(?string $value): ?string
{
    $normalized = app_normalize_project_nullable_string($value);
    if ($normalized === null) {
        return null;
    }

    if (preg_match('/RM#?\s*(\d+)/i', $normalized, $matches) === 1) {
        return (string) $matches[1];
    }

    if (preg_match('/\/issues\/(\d+)/i', $normalized, $matches) === 1) {
        return (string) $matches[1];
    }

    if (preg_match('/(^|[^\d])(\d{2,})(?:[^\d]|$)/', $normalized, $matches) === 1) {
        return (string) $matches[2];
    }

    return null;
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

function app_normalize_chronological_import_status(string $value, array $project): string
{
    $plannedStatus = html_entity_decode('Planifi&eacute;', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $completedStatus = html_entity_decode('Termin&eacute;', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $normalizedKey = app_normalize_lookup_key($value);
    $statusByKey = [
        'aplanifier' => 'A planifier',
        'planifie' => $plannedStatus,
        'encours' => 'En cours',
        'termine' => $completedStatus,
        'standby' => 'Standby',
    ];

    return app_normalize_project_status_value($statusByKey[$normalizedKey] ?? $value, $project);
}

function app_parse_chronological_spreadsheet_rows(string $filePath): array
{
    if (app_is_xlsx_workbook_file($filePath)) {
        return app_parse_chronological_xlsx_rows($filePath);
    }

    return app_parse_legacy_spreadsheet_rows($filePath);
}

function app_is_xlsx_workbook_file(string $filePath): bool
{
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    if ($extension === 'xlsx') {
        return true;
    }

    if (!is_file($filePath)) {
        return false;
    }

    $signature = @file_get_contents($filePath, false, null, 0, 4);
    if (!is_string($signature) || $signature !== "PK\x03\x04") {
        return false;
    }

    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return false;
    }

    try {
        return $zip->locateName('xl/workbook.xml') !== false;
    } finally {
        $zip->close();
    }
}

function app_parse_chronological_xlsx_rows(string $filePath): array
{
    $spreadsheetRows = app_excel_read_rows($filePath);
    $rows = [];

    foreach ($spreadsheetRows as $row) {
        $rowCells = app_convert_excel_row_cells_to_chronological_cells((array) ($row['cells'] ?? []));
        if ($rowCells === []) {
            continue;
        }

        $rowLinks = app_convert_excel_row_links_to_chronological_links((array) ($row['links'] ?? []));

        $rows[] = [
            'rowNumber' => (int) ($row['rowNumber'] ?? 0),
            'cells' => $rowCells,
            'links' => $rowLinks,
        ];
    }

    return $rows;
}

function app_convert_excel_row_cells_to_chronological_cells(array $cellsByColumn): array
{
    if ($cellsByColumn === []) {
        return [];
    }

    uksort($cellsByColumn, static function ($left, $right): int {
        return app_excel_column_reference_to_index((string) $left) <=> app_excel_column_reference_to_index((string) $right);
    });

    $cells = [];
    foreach ($cellsByColumn as $columnReference => $value) {
        $columnIndex = app_excel_column_reference_to_index((string) $columnReference);
        if ($columnIndex < 0) {
            continue;
        }

        while (count($cells) < $columnIndex) {
            $cells[] = '';
        }

        $cells[$columnIndex] = trim((string) $value);
    }

    if ($cells === []) {
        return [];
    }

    ksort($cells);
    return array_values($cells);
}

function app_convert_excel_row_links_to_chronological_links(array $linksByColumn): array
{
    if ($linksByColumn === []) {
        return [];
    }

    uksort($linksByColumn, static function ($left, $right): int {
        return app_excel_column_reference_to_index((string) $left) <=> app_excel_column_reference_to_index((string) $right);
    });

    $links = [];
    foreach ($linksByColumn as $columnReference => $value) {
        $columnIndex = app_excel_column_reference_to_index((string) $columnReference);
        if ($columnIndex < 0) {
            continue;
        }

        $normalizedValue = trim((string) $value);
        if ($normalizedValue === '') {
            continue;
        }

        $links[$columnIndex] = $normalizedValue;
    }

    ksort($links);
    return $links;
}

function app_excel_column_reference_to_index(string $columnReference): int
{
    $columnReference = strtoupper(trim($columnReference));
    if ($columnReference === '' || preg_match('/^[A-Z]+$/', $columnReference) !== 1) {
        return -1;
    }

    $index = 0;
    $length = strlen($columnReference);

    for ($position = 0; $position < $length; $position++) {
        $index = ($index * 26) + (ord($columnReference[$position]) - 64);
    }

    return $index - 1;
}

function app_parse_legacy_spreadsheet_rows(string $filePath): array
{
    $content = @file_get_contents($filePath);
    if ($content === false || $content === '') {
        return [];
    }

    $content = app_normalize_legacy_spreadsheet_content($content);
    $rows = [];
    $document = new DOMDocument();
    $previousLibxmlState = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $content);
    libxml_clear_errors();
    libxml_use_internal_errors($previousLibxmlState);

    if ($loaded) {
        $xpath = new DOMXPath($document);
        $trNodes = $xpath->query('//tr');
        if ($trNodes !== false) {
            $rowNumber = 0;
            foreach ($trNodes as $trNode) {
                $line = [];

                foreach ($trNode->childNodes as $cell) {
                    if (!($cell instanceof DOMElement)) {
                        continue;
                    }

                    $tagName = strtolower($cell->tagName);
                    if ($tagName !== 'td' && $tagName !== 'th') {
                        continue;
                    }

                    $line[] = trim(html_entity_decode($cell->textContent ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                }

                if ($line !== []) {
                    $rowNumber++;
                    $rows[] = [
                        'rowNumber' => $rowNumber,
                        'cells' => $line,
                    ];
                }
            }
        }
    }

    if ($rows !== []) {
        return $rows;
    }

    $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
    $rowNumber = 0;

    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }

        $rowNumber++;
        $rows[] = [
            'rowNumber' => $rowNumber,
            'cells' => array_map(
                static fn ($value): string => trim((string) $value),
                explode("\t", $line)
            ),
        ];
    }

    return $rows;
}

function app_normalize_legacy_spreadsheet_content(string $content): string
{
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

    if (preg_match('//u', $content) === 1) {
        return $content;
    }

    foreach (['Windows-1252', 'ISO-8859-1'] as $encoding) {
        $converted = @iconv($encoding, 'UTF-8//IGNORE', $content);
        if (is_string($converted) && $converted !== '') {
            return $converted;
        }
    }

    return $content;
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
    $hyperlinksByReference = app_excel_read_sheet_hyperlinks($zip, $sheetXml, $sheetPath);
    $rows = [];

    foreach ($rowNodes ?: [] as $rowNode) {
        $rowNode->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $cells = [];
        $links = [];
        foreach ($rowNode->xpath('main:c') ?: [] as $cellNode) {
            $reference = (string) $cellNode['r'];
            $column = preg_replace('/\d+/', '', $reference);
            if ($column === '') {
                continue;
            }

            $cells[$column] = app_excel_resolve_cell_value($cellNode, $sharedStrings);
            $formulaHyperlink = app_excel_extract_hyperlink_target_from_formula($cellNode);
            if ($formulaHyperlink !== null) {
                $links[$column] = $formulaHyperlink;
            } elseif (isset($hyperlinksByReference[$reference])) {
                $links[$column] = $hyperlinksByReference[$reference];
            }
        }

        $rows[] = [
            'rowNumber' => (int) $rowNode['r'],
            'cells' => $cells,
            'links' => $links,
        ];
    }

    return $rows;
}

function app_excel_read_sheet_hyperlinks(ZipArchive $zip, SimpleXMLElement $sheetXml, string $sheetPath): array
{
    $sheetXml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $hyperlinkNodes = $sheetXml->xpath('/main:worksheet/main:hyperlinks/main:hyperlink');
    if (!$hyperlinkNodes) {
        return [];
    }

    $targetsByRelationshipId = app_excel_read_sheet_hyperlink_relationship_targets(
        $zip,
        app_excel_resolve_sheet_relationships_path($sheetPath)
    );
    $hyperlinksByReference = [];

    foreach ($hyperlinkNodes as $hyperlinkNode) {
        $reference = trim((string) ($hyperlinkNode['ref'] ?? ''));
        if ($reference === '') {
            continue;
        }

        $relationshipId = (string) $hyperlinkNode->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
        if ($relationshipId !== '' && isset($targetsByRelationshipId[$relationshipId])) {
            $hyperlinksByReference[$reference] = $targetsByRelationshipId[$relationshipId];
            continue;
        }

        $location = trim((string) ($hyperlinkNode['location'] ?? ''));
        if ($location !== '') {
            $hyperlinksByReference[$reference] = '#' . ltrim($location, '#');
        }
    }

    return $hyperlinksByReference;
}

function app_excel_resolve_sheet_relationships_path(string $sheetPath): string
{
    $directory = trim((string) dirname($sheetPath), '.\\/');
    $fileName = basename($sheetPath);

    if ($directory === '') {
        return '_rels/' . $fileName . '.rels';
    }

    return $directory . '/_rels/' . $fileName . '.rels';
}

function app_excel_read_sheet_hyperlink_relationship_targets(ZipArchive $zip, string $relationshipsPath): array
{
    if ($zip->locateName($relationshipsPath) === false) {
        return [];
    }

    $relsXml = app_excel_load_xml($zip, $relationshipsPath);
    $relsXml->registerXPathNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');
    $targetsByRelationshipId = [];

    foreach ($relsXml->xpath('/rel:Relationships/rel:Relationship') ?: [] as $relationshipNode) {
        $type = (string) ($relationshipNode['Type'] ?? '');
        if ($type !== 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink') {
            continue;
        }

        $relationshipId = (string) ($relationshipNode['Id'] ?? '');
        $target = trim((string) ($relationshipNode['Target'] ?? ''));
        if ($relationshipId === '' || $target === '') {
            continue;
        }

        $targetsByRelationshipId[$relationshipId] = $target;
    }

    return $targetsByRelationshipId;
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
        $formulaLabel = app_excel_extract_hyperlink_label_from_formula($cellNode);
        if ($formulaLabel !== null) {
            return $formulaLabel;
        }

        return '';
    }

    return (string) $cellNode->v;
}

function app_excel_extract_hyperlink_target_from_formula(SimpleXMLElement $cellNode): ?string
{
    $parts = app_excel_extract_hyperlink_parts_from_formula($cellNode);

    return $parts['url'] ?? null;
}

function app_excel_extract_hyperlink_label_from_formula(SimpleXMLElement $cellNode): ?string
{
    $parts = app_excel_extract_hyperlink_parts_from_formula($cellNode);

    return $parts['label'] ?? null;
}

function app_excel_extract_hyperlink_parts_from_formula(SimpleXMLElement $cellNode): array
{
    $cellNode->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $formulaNodes = $cellNode->xpath('main:f');
    if (!$formulaNodes || !isset($formulaNodes[0])) {
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
    $templateFile = app_export_template_workbook_file();
    if (!is_file($templateFile)) {
        throw new RuntimeException('Aucun fichier source Excel n a ete trouve. Importez d abord un fichier source Excel.');
    }

    $exportDir = app_exports_dir();
    if (!is_dir($exportDir) && !mkdir($exportDir, 0777, true) && !is_dir($exportDir)) {
        throw new RuntimeException('Impossible de créer le dossier export.');
    }

    $extension = pathinfo($templateFile, PATHINFO_EXTENSION) ?: 'xlsx';
    $fileName = sprintf('Planning Projets - Gantt.%s', $extension);
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

function app_export_projects_to_chronological_workbook(array $projects, ?string $cdcPageUrl = null): array
{
    $exportDir = app_exports_dir();
    if (!is_dir($exportDir) && !mkdir($exportDir, 0777, true) && !is_dir($exportDir)) {
        throw new RuntimeException('Impossible de créer le dossier export.');
    }

    $fileName = 'Planning Projets - Gantt.xlsx';
    $targetFile = $exportDir . '/' . $fileName;
    $orderedProjects = app_sort_projects_for_chronological_export($projects);
    app_write_chronological_projects_to_workbook($targetFile, $orderedProjects, $cdcPageUrl);

    return [
        'fileName' => $fileName,
        'filePath' => $targetFile,
        'downloadUrl' => app_gantt_export_download_url($fileName),
    ];
}

function app_write_chronological_projects_to_workbook(string $workbookPath, array $orderedProjects, ?string $cdcPageUrl = null): void
{
    $sheetPayload = app_build_chronological_export_sheet_payload($orderedProjects, $cdcPageUrl);
    $styleRegistry = $sheetPayload['styleRegistry'];
    $createdAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $files = [
        '[Content_Types].xml' => app_render_chronological_export_content_types_xml(),
        '_rels/.rels' => app_render_chronological_export_root_relationships_xml(),
        'docProps/app.xml' => app_render_chronological_export_app_properties_xml(),
        'docProps/core.xml' => app_render_chronological_export_core_properties_xml($createdAt),
        'xl/workbook.xml' => app_render_chronological_export_workbook_xml(),
        'xl/_rels/workbook.xml.rels' => app_render_chronological_export_workbook_relationships_xml(),
        'xl/styles.xml' => app_render_chronological_export_styles_xml($styleRegistry),
        'xl/worksheets/sheet1.xml' => app_render_chronological_export_sheet_xml($sheetPayload),
        'xl/worksheets/_rels/sheet1.xml.rels' => app_render_chronological_export_sheet_relationships_xml($sheetPayload['hyperlinks'] ?? []),
    ];

    $zip = new ZipArchive();
    if ($zip->open($workbookPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Impossible de créer le fichier Excel chronologique.');
    }

    try {
        foreach ($files as $path => $content) {
            if ($zip->addFromString($path, $content) === false) {
                throw new RuntimeException('Impossible d\'écrire le contenu Excel chronologique.');
            }
        }
    } finally {
        $zip->close();
    }
}

function app_build_chronological_export_sheet_payload(array $orderedProjects, ?string $cdcPageUrl = null): array
{
    $generatedAt = (new DateTimeImmutable())->format('d/m/Y H:i');
    $rowCount = count($orderedProjects);
    $styleRegistry = app_create_chronological_export_style_registry();
    $serviceColors = app_build_chronological_export_service_color_map();
    $existingServiceRows = app_fetch_existing_service_rows();
    $rows = [];
    $mergeCells = [];
    $gridBorderColor = '#000000';
    $progressBars = [
        'zero' => [],
        'running' => [],
        'done' => [],
    ];

    $titleStyleId = app_register_chronological_export_style($styleRegistry, [
        'fillColor' => '#253246',
        'fontColor' => '#ffffff',
        'fontSize' => 16,
        'bold' => true,
        'horizontal' => 'left',
        'borderColor' => $gridBorderColor,
    ]);
    $rows[] = [
        'rowNumber' => 1,
        'height' => 28,
        'cells' => app_build_chronological_export_merged_row_cells(
            11,
            'Planning projets',
            $titleStyleId
        ),
    ];
    $mergeCells[] = 'A1:K1';

    $metaStyleId = app_register_chronological_export_style($styleRegistry, [
        'fillColor' => '#eaf1fb',
        'fontColor' => '#52637a',
        'fontSize' => 11,
        'horizontal' => 'left',
        'borderColor' => $gridBorderColor,
    ]);
    $rows[] = [
        'rowNumber' => 2,
        'height' => 22,
        'cells' => app_build_chronological_export_merged_row_cells(
            11,
            sprintf('Généré le %s | %d projet(s)', $generatedAt, $rowCount),
            $metaStyleId
        ),
    ];
    $mergeCells[] = 'A2:K2';

    $headerStyleId = app_register_chronological_export_style($styleRegistry, [
        'fillColor' => '#3d88c8',
        'fontColor' => '#ffffff',
        'fontSize' => 11,
        'bold' => true,
        'horizontal' => 'center',
        'borderColor' => $gridBorderColor,
    ]);
    $rows[] = [
        'rowNumber' => 3,
        'height' => 22,
        'cells' => [
            app_build_chronological_export_cell(1, 'N° projet', $headerStyleId),
            app_build_chronological_export_cell(2, 'YouTrack', $headerStyleId),
            app_build_chronological_export_cell(3, 'RM', $headerStyleId),
            app_build_chronological_export_cell(4, 'CDC', $headerStyleId),
            app_build_chronological_export_cell(5, 'Titre', $headerStyleId),
            app_build_chronological_export_cell(6, 'Service', $headerStyleId),
            app_build_chronological_export_cell(7, 'Date début', $headerStyleId),
            app_build_chronological_export_cell(8, 'Date fin', $headerStyleId),
            app_build_chronological_export_cell(9, 'Chef de projet', $headerStyleId),
            app_build_chronological_export_cell(10, 'Avancement', $headerStyleId),
            app_build_chronological_export_cell(11, 'Statut', $headerStyleId),
        ],
    ];

    $defaultCenterStyleId = app_register_chronological_export_style($styleRegistry, [
        'fillColor' => '#ffffff',
        'fontColor' => '#1f2a3d',
        'fontSize' => 11,
        'horizontal' => 'center',
        'borderColor' => $gridBorderColor,
    ]);
    $defaultLeftStyleId = app_register_chronological_export_style($styleRegistry, [
        'fillColor' => '#ffffff',
        'fontColor' => '#1f2a3d',
        'fontSize' => 11,
        'horizontal' => 'left',
        'borderColor' => $gridBorderColor,
    ]);
    $defaultRightStyleId = app_register_chronological_export_style($styleRegistry, [
        'fillColor' => '#ffffff',
        'fontColor' => '#1f2a3d',
        'fontSize' => 11,
        'horizontal' => 'right',
        'borderColor' => $gridBorderColor,
    ]);
    $defaultDateStyleId = app_register_chronological_export_style($styleRegistry, [
        'fillColor' => '#ffffff',
        'fontColor' => '#1f2a3d',
        'fontSize' => 11,
        'horizontal' => 'center',
        'borderColor' => $gridBorderColor,
        'numFmtCode' => 'dd/mm/yyyy',
    ]);
    $defaultProgressStyleId = app_register_chronological_export_style($styleRegistry, [
        'fillColor' => '#ffffff',
        'fontColor' => '#1f2a3d',
        'fontSize' => 11,
        'bold' => true,
        'horizontal' => 'center',
        'borderColor' => $gridBorderColor,
        'numFmtCode' => '0"%"',
    ]);
    $completedCenterStyleId = app_register_chronological_export_style($styleRegistry, [
        'fillColor' => '#d7dbe2',
        'fontColor' => '#566173',
        'fontSize' => 11,
        'horizontal' => 'center',
        'borderColor' => $gridBorderColor,
    ]);
    $completedLeftStyleId = app_register_chronological_export_style($styleRegistry, [
        'fillColor' => '#d7dbe2',
        'fontColor' => '#566173',
        'fontSize' => 11,
        'horizontal' => 'left',
        'borderColor' => $gridBorderColor,
    ]);
    $completedRightStyleId = app_register_chronological_export_style($styleRegistry, [
        'fillColor' => '#d7dbe2',
        'fontColor' => '#566173',
        'fontSize' => 11,
        'horizontal' => 'right',
        'borderColor' => $gridBorderColor,
    ]);
    $completedDateStyleId = app_register_chronological_export_style($styleRegistry, [
        'fillColor' => '#d7dbe2',
        'fontColor' => '#566173',
        'fontSize' => 11,
        'horizontal' => 'center',
        'borderColor' => $gridBorderColor,
        'numFmtCode' => 'dd/mm/yyyy',
    ]);
    $completedProgressStyleId = app_register_chronological_export_style($styleRegistry, [
        'fillColor' => '#d7dbe2',
        'fontColor' => '#566173',
        'fontSize' => 11,
        'bold' => true,
        'horizontal' => 'center',
        'borderColor' => $gridBorderColor,
        'numFmtCode' => '0"%"',
    ]);

    $dataRowNumber = 4;
    foreach ($orderedProjects as $entry) {
        $project = $entry['project'];
        $isParentProject = app_is_chronological_export_parent_project($project);
        $resolvedStatus = !$isParentProject
            ? app_resolve_chronological_export_status($project, $entry['start'], $entry['end'])
            : '';
        $statusKey = app_normalize_lookup_key($resolvedStatus);
        $hideDates = $isParentProject || $statusKey === 'aplanifier';
        $startDate = !$hideDates && $entry['start'] instanceof DateTimeImmutable ? $entry['start'] : null;
        $endDate = !$hideDates && $entry['end'] instanceof DateTimeImmutable ? $entry['end'] : null;
        $youtrackUrl = !$isParentProject ? app_resolve_project_youtrack_url_from_export_cell((string) ($project['youtrackTicketUrl'] ?? ''), null) : null;
        $redmineUrl = !$isParentProject ? app_resolve_project_redmine_url_from_export_cell((string) ($project['redmineUrl'] ?? ''), null) : null;
        $cdcUrl = !$isParentProject ? app_build_chronological_export_cdc_link_url($project, $cdcPageUrl) : null;
        $youtrackValue = !$isParentProject ? (string) ($youtrackUrl ?? '') : '';
        $redmineValue = !$isParentProject ? (string) ($redmineUrl ?? '') : '';
        $cdcValue = !$isParentProject && $cdcUrl !== null ? 'CDC' : '';
        $progressValue = !$isParentProject ? app_normalize_chronological_export_progress_value($project['progression'] ?? null) : null;

        $rowCenterStyleId = $defaultCenterStyleId;
        $rowLeftStyleId = $defaultLeftStyleId;
        $rowRightStyleId = $defaultRightStyleId;
        $rowDateStyleId = $defaultDateStyleId;
        $projectManagerStyleId = $defaultCenterStyleId;
        $progressStyleId = $defaultProgressStyleId;
        $serviceStyleId = $defaultCenterStyleId;
        $statusStyleId = $defaultCenterStyleId;

        if ($isParentProject) {
            $parentBackground = app_resolve_chronological_export_project_color($project, $serviceColors, $existingServiceRows);
            $parentTextColor = $parentBackground !== ''
                ? app_resolve_chronological_export_text_color($parentBackground)
                : '#1f2a3d';
            $parentBorderColor = $gridBorderColor;

            $rowCenterStyleId = app_register_chronological_export_style($styleRegistry, [
                'fillColor' => $parentBackground !== '' ? $parentBackground : '#ffffff',
                'fontColor' => $parentTextColor,
                'fontSize' => 11,
                'bold' => true,
                'horizontal' => 'center',
                'borderColor' => $parentBorderColor,
            ]);
            $rowLeftStyleId = app_register_chronological_export_style($styleRegistry, [
                'fillColor' => $parentBackground !== '' ? $parentBackground : '#ffffff',
                'fontColor' => $parentTextColor,
                'fontSize' => 11,
                'bold' => true,
                'horizontal' => 'left',
                'borderColor' => $parentBorderColor,
            ]);
            $rowRightStyleId = app_register_chronological_export_style($styleRegistry, [
                'fillColor' => $parentBackground !== '' ? $parentBackground : '#ffffff',
                'fontColor' => $parentTextColor,
                'fontSize' => 11,
                'bold' => true,
                'horizontal' => 'right',
                'borderColor' => $parentBorderColor,
            ]);
            $rowDateStyleId = app_register_chronological_export_style($styleRegistry, [
                'fillColor' => $parentBackground !== '' ? $parentBackground : '#ffffff',
                'fontColor' => $parentTextColor,
                'fontSize' => 11,
                'bold' => true,
                'horizontal' => 'center',
                'borderColor' => $parentBorderColor,
                'numFmtCode' => 'dd/mm/yyyy',
            ]);
            $projectManagerStyleId = $rowCenterStyleId;
            $progressStyleId = app_register_chronological_export_style($styleRegistry, [
                'fillColor' => $parentBackground !== '' ? $parentBackground : '#ffffff',
                'fontColor' => $parentTextColor,
                'fontSize' => 11,
                'bold' => true,
                'horizontal' => 'center',
                'borderColor' => $parentBorderColor,
                'numFmtCode' => '0"%"',
            ]);
            $serviceStyleId = $rowCenterStyleId;
            $statusStyleId = $rowCenterStyleId;
        } else {
            $serviceBackground = app_resolve_chronological_export_service_color(
                (string) ($project['service'] ?? ''),
                $serviceColors,
                $existingServiceRows
            );
            if ($serviceBackground !== '') {
                $serviceStyleId = app_register_chronological_export_style($styleRegistry, [
                    'fillColor' => $serviceBackground,
                    'fontColor' => app_resolve_chronological_export_text_color($serviceBackground),
                    'fontSize' => 11,
                    'bold' => true,
                    'horizontal' => 'center',
                    'borderColor' => $gridBorderColor,
                ]);
            }

            if ($statusKey === 'termine') {
                $rowCenterStyleId = $completedCenterStyleId;
                $rowLeftStyleId = $completedLeftStyleId;
                $rowRightStyleId = $completedRightStyleId;
                $rowDateStyleId = $completedDateStyleId;
                $progressStyleId = $completedProgressStyleId;
            }

            $statusBackground = app_resolve_chronological_export_status_background_color($resolvedStatus);
            if ($statusBackground !== '') {
                $statusStyleId = app_register_chronological_export_style($styleRegistry, [
                    'fillColor' => $statusBackground,
                    'fontColor' => app_resolve_chronological_export_text_color($statusBackground),
                    'fontSize' => 11,
                    'bold' => true,
                    'horizontal' => 'center',
                    'borderColor' => $gridBorderColor,
                ]);
            }

            if (in_array($statusKey, ['encours', 'planifie', 'standby', 'termine'], true) && $statusBackground !== '') {
                $statusTextColor = app_resolve_chronological_export_text_color($statusBackground);
                $rowCenterStyleId = app_register_chronological_export_style($styleRegistry, [
                    'fillColor' => $statusBackground,
                    'fontColor' => $statusTextColor,
                    'fontSize' => 11,
                    'horizontal' => 'center',
                    'borderColor' => $gridBorderColor,
                ]);
                $rowLeftStyleId = app_register_chronological_export_style($styleRegistry, [
                    'fillColor' => $statusBackground,
                    'fontColor' => $statusTextColor,
                    'fontSize' => 11,
                    'horizontal' => 'left',
                    'borderColor' => $gridBorderColor,
                ]);
                $rowRightStyleId = app_register_chronological_export_style($styleRegistry, [
                    'fillColor' => $statusBackground,
                    'fontColor' => $statusTextColor,
                    'fontSize' => 11,
                    'horizontal' => 'right',
                    'borderColor' => $gridBorderColor,
                ]);
                $rowDateStyleId = app_register_chronological_export_style($styleRegistry, [
                    'fillColor' => $statusBackground,
                    'fontColor' => $statusTextColor,
                    'fontSize' => 11,
                    'horizontal' => 'center',
                    'borderColor' => $gridBorderColor,
                    'numFmtCode' => 'dd/mm/yyyy',
                ]);
                $projectManagerStyleId = $rowCenterStyleId;
                $progressStyleId = app_register_chronological_export_style($styleRegistry, [
                    'fillColor' => $statusBackground,
                    'fontColor' => $statusTextColor,
                    'fontSize' => 11,
                    'bold' => true,
                    'horizontal' => 'center',
                    'borderColor' => $gridBorderColor,
                    'numFmtCode' => '0"%"',
                ]);
            }

            $progressBackground = app_resolve_chronological_export_progress_background_color($progressValue);
            if (
                $progressBackground !== ''
                && !in_array($statusKey, ['encours', 'planifie', 'standby', 'termine'], true)
            ) {
                $progressStyleId = app_register_chronological_export_style($styleRegistry, [
                    'fillColor' => $progressBackground,
                    'fontColor' => app_resolve_chronological_export_text_color($progressBackground),
                    'fontSize' => 11,
                    'bold' => true,
                    'horizontal' => 'center',
                    'borderColor' => $gridBorderColor,
                    'numFmtCode' => '0"%"',
                ]);
            }
        }

        if (!$isParentProject && $progressValue !== null) {
            $progressBucket = app_resolve_chronological_export_progress_bucket($progressValue);
            if ($progressBucket !== '' && isset($progressBars[$progressBucket])) {
                $progressBars[$progressBucket][] = 'J' . $dataRowNumber;
            }
        }

        $rows[] = [
            'rowNumber' => $dataRowNumber,
            'height' => 22,
            'cells' => [
                app_build_chronological_export_cell(1, (string) ($project['ref'] ?? ''), $rowCenterStyleId),
                app_build_chronological_export_cell(2, $youtrackValue, $rowRightStyleId, $youtrackUrl),
                app_build_chronological_export_cell(3, $redmineValue, $rowRightStyleId, $redmineUrl),
                app_build_chronological_export_cell(4, $cdcValue, $rowCenterStyleId, $cdcUrl),
                app_build_chronological_export_cell(5, (string) ($project['title'] ?? ''), $rowLeftStyleId),
                app_build_chronological_export_cell(6, $isParentProject ? '' : (string) ($project['service'] ?? ''), $serviceStyleId),
                app_build_chronological_export_date_cell(7, $startDate, $rowDateStyleId),
                app_build_chronological_export_date_cell(8, $endDate, $rowDateStyleId),
                app_build_chronological_export_cell(9, $isParentProject ? '' : (string) ($project['projectManager'] ?? ''), $projectManagerStyleId),
                app_build_chronological_export_progress_cell(10, $progressValue, $progressStyleId),
                app_build_chronological_export_cell(11, $resolvedStatus, $statusStyleId),
            ],
        ];

        $dataRowNumber++;
    }

    $footerStyleId = app_register_chronological_export_style($styleRegistry, [
        'fillColor' => '#eef4fb',
        'fontColor' => '#52637a',
        'fontSize' => 11,
        'horizontal' => 'left',
        'borderColor' => $gridBorderColor,
    ]);
    $rows[] = [
        'rowNumber' => $dataRowNumber,
        'height' => 20,
        'cells' => app_build_chronological_export_merged_row_cells(
            11,
            'Les projets sans date planifiée apparaissent à la fin du tableau.',
            $footerStyleId
        ),
    ];
    $mergeCells[] = sprintf('A%d:K%d', $dataRowNumber, $dataRowNumber);

    array_pop($rows);
    array_pop($mergeCells);

    return [
        'styleRegistry' => $styleRegistry,
        'rows' => $rows,
        'mergeCells' => $mergeCells,
        'lastRowNumber' => max(3, $dataRowNumber - 1),
        'columnWidths' => app_resolve_chronological_export_column_widths($orderedProjects),
        'titleColumnWidth' => app_resolve_chronological_export_title_column_excel_width($orderedProjects),
        'hyperlinks' => app_collect_chronological_export_sheet_hyperlinks($rows),
        'progressBars' => [
            [
                'refs' => $progressBars['zero'],
                'color' => 'FF3D88C8',
            ],
            [
                'refs' => $progressBars['running'],
                'color' => 'FFF3C14F',
            ],
            [
                'refs' => $progressBars['done'],
                'color' => 'FF7BC67E',
            ],
        ],
    ];
}

function app_build_chronological_export_cell(int $columnIndex, string $value, int $styleId, ?string $hyperlink = null): array
{
    return [
        'columnIndex' => $columnIndex,
        'styleId' => $styleId,
        'type' => 'string',
        'value' => $value,
        'hyperlink' => $hyperlink,
    ];
}

function app_build_chronological_export_date_cell(int $columnIndex, ?DateTimeImmutable $date, int $styleId): array
{
    if (!$date instanceof DateTimeImmutable) {
        return app_build_chronological_export_cell($columnIndex, '', $styleId);
    }

    return [
        'columnIndex' => $columnIndex,
        'styleId' => $styleId,
        'type' => 'number',
        'value' => app_convert_date_to_excel_serial($date),
    ];
}

function app_build_chronological_export_progress_cell(int $columnIndex, mixed $progression, int $styleId): array
{
    if ($progression === null || $progression === '') {
        return app_build_chronological_export_cell($columnIndex, '', $styleId);
    }

    $progressValue = app_normalize_chronological_export_progress_value($progression);
    if ($progressValue === null) {
        return app_build_chronological_export_cell($columnIndex, '', $styleId);
    }

    return [
        'columnIndex' => $columnIndex,
        'styleId' => $styleId,
        'type' => 'number',
        'value' => $progressValue,
    ];
}

function app_normalize_chronological_export_progress_value(mixed $progression): ?float
{
    if ($progression === null || $progression === '') {
        return null;
    }

    return max(0.0, min(100.0, (float) $progression));
}

function app_resolve_chronological_export_progress_bucket(?float $progressValue): string
{
    if ($progressValue === null) {
        return '';
    }

    if ($progressValue <= 0.0) {
        return 'zero';
    }

    if ($progressValue >= 100.0) {
        return 'done';
    }

    return 'running';
}

function app_resolve_chronological_export_progress_background_color(?float $progressValue): string
{
    return match (app_resolve_chronological_export_progress_bucket($progressValue)) {
        'zero' => '#b9ddff',
        'running' => '#f3c14f',
        'done' => '#b7e5b4',
        default => '',
    };
}

function app_build_chronological_export_merged_row_cells(int $columnCount, string $value, int $styleId): array
{
    $cells = [];

    for ($columnIndex = 1; $columnIndex <= $columnCount; $columnIndex++) {
        $cells[] = app_build_chronological_export_cell(
            $columnIndex,
            $columnIndex === 1 ? $value : '',
            $styleId
        );
    }

    return $cells;
}

function app_collect_chronological_export_sheet_hyperlinks(array $rows): array
{
    $hyperlinks = [];
    $nextRelationshipId = 1;

    foreach ($rows as $row) {
        $rowNumber = (int) ($row['rowNumber'] ?? 0);
        if ($rowNumber <= 0) {
            continue;
        }

        foreach (($row['cells'] ?? []) as $cell) {
            $hyperlink = app_normalize_project_external_link_url($cell['hyperlink'] ?? null);
            if ($hyperlink === null) {
                continue;
            }

            $columnIndex = (int) ($cell['columnIndex'] ?? 0);
            if ($columnIndex <= 0) {
                continue;
            }

            $hyperlinks[] = [
                'ref' => app_convert_index_to_excel_column_reference($columnIndex) . $rowNumber,
                'relationshipId' => 'rId' . $nextRelationshipId,
                'target' => $hyperlink,
            ];
            $nextRelationshipId++;
        }
    }

    return $hyperlinks;
}

function app_build_chronological_export_cdc_link_url(array $project, ?string $cdcPageUrl): ?string
{
    $normalizedBaseUrl = trim((string) $cdcPageUrl);
    $projectId = trim((string) ($project['id'] ?? ''));
    $hasCdcContent = !empty($project['hasCdcContent']);
    if ($normalizedBaseUrl === '' || $projectId === '' || !$hasCdcContent) {
        return null;
    }

    $query = http_build_query([
        'cdc' => '1',
        'projectId' => $projectId,
        'projectRef' => trim((string) ($project['ref'] ?? '')),
    ], '', '&', PHP_QUERY_RFC3986);

    return $normalizedBaseUrl . (str_contains($normalizedBaseUrl, '?') ? '&' : '?') . $query;
}

function app_resolve_chronological_export_column_widths(array $orderedProjects): array
{
    $columnWidths = [
        1 => 18.0,
        2 => 12.0,
        3 => 12.0,
        4 => 12.0,
        5 => app_resolve_chronological_export_title_column_excel_width($orderedProjects),
        6 => 22.0,
        7 => 14.0,
        8 => 14.0,
        9 => 24.0,
        10 => 18.0,
        11 => 16.0,
    ];

    foreach (app_read_chronological_export_column_widths() as $columnIndex => $width) {
        if (!isset($columnWidths[$columnIndex])) {
            continue;
        }

        $columnWidths[$columnIndex] = app_normalize_chronological_export_column_width((float) $width);
    }

    return $columnWidths;
}

function app_format_chronological_export_youtrack_label(?string $value): string
{
    $tailLabel = app_extract_chronological_export_url_tail_label($value);
    if ($tailLabel !== null) {
        return $tailLabel;
    }

    $ticketKey = app_extract_project_youtrack_ticket_key($value);
    if ($ticketKey !== null) {
        return $ticketKey;
    }

    $normalized = app_normalize_project_nullable_string($value);
    if ($normalized === null) {
        return '';
    }

    return preg_replace('~/+$~', '', $normalized) ?? $normalized;
}

function app_format_chronological_export_redmine_label(?string $value): string
{
    $tailLabel = app_extract_chronological_export_url_tail_label($value);
    if ($tailLabel !== null) {
        return $tailLabel;
    }

    $issueId = app_extract_project_redmine_issue_id($value);
    if ($issueId !== null) {
        return $issueId;
    }

    $normalized = app_normalize_project_nullable_string($value);
    if ($normalized === null) {
        return '';
    }

    return preg_replace('~/+$~', '', $normalized) ?? $normalized;
}

function app_extract_chronological_export_url_tail_label(?string $value): ?string
{
    $normalized = app_normalize_project_nullable_string($value);
    if ($normalized === null || strpos($normalized, '/') === false) {
        return null;
    }

    $path = (string) parse_url($normalized, PHP_URL_PATH);
    if ($path === '') {
        return null;
    }

    $segments = array_values(array_filter(explode('/', trim($path, '/')), static function ($segment): bool {
        return trim((string) $segment) !== '';
    }));
    if ($segments === []) {
        return null;
    }

    return urldecode((string) $segments[count($segments) - 1]);
}

function app_build_chronological_export_hyperlink_formula(?string $url, string $label): ?string
{
    $normalizedUrl = app_normalize_project_external_link_url($url);
    if ($normalizedUrl === null) {
        return null;
    }

    $normalizedLabel = $label !== '' ? $label : $normalizedUrl;

    return sprintf(
        'HYPERLINK("%s","%s")',
        str_replace('"', '""', $normalizedUrl),
        str_replace('"', '""', $normalizedLabel)
    );
}

function app_create_chronological_export_style_registry(): array
{
    $baseStyle = app_normalize_chronological_export_style_definition([]);
    $styleKey = json_encode($baseStyle, JSON_UNESCAPED_SLASHES);

    return [
        'styles' => [$baseStyle],
        'indexByKey' => [$styleKey => 0],
    ];
}

function app_register_chronological_export_style(array &$registry, array $styleDefinition): int
{
    $normalizedStyle = app_normalize_chronological_export_style_definition($styleDefinition);
    $styleKey = json_encode($normalizedStyle, JSON_UNESCAPED_SLASHES);

    if (isset($registry['indexByKey'][$styleKey])) {
        return (int) $registry['indexByKey'][$styleKey];
    }

    $styleId = count($registry['styles']);
    $registry['styles'][] = $normalizedStyle;
    $registry['indexByKey'][$styleKey] = $styleId;

    return $styleId;
}

function app_normalize_chronological_export_style_definition(array $styleDefinition): array
{
    $fillColor = app_normalize_hex_color((string) ($styleDefinition['fillColor'] ?? '#ffffff'));
    $fontColor = app_normalize_hex_color((string) ($styleDefinition['fontColor'] ?? '#1f2a3d'));
    $borderColor = app_normalize_hex_color((string) ($styleDefinition['borderColor'] ?? '#c9d4e5'));
    $horizontal = strtolower(trim((string) ($styleDefinition['horizontal'] ?? 'left')));
    $vertical = strtolower(trim((string) ($styleDefinition['vertical'] ?? 'center')));
    $numFmtCode = trim((string) ($styleDefinition['numFmtCode'] ?? ''));

    if (!in_array($horizontal, ['left', 'center', 'right'], true)) {
        $horizontal = 'left';
    }

    if (!in_array($vertical, ['top', 'center', 'bottom'], true)) {
        $vertical = 'center';
    }

    return [
        'fontName' => trim((string) ($styleDefinition['fontName'] ?? 'Arial')),
        'fontSize' => (float) ($styleDefinition['fontSize'] ?? 11),
        'bold' => !empty($styleDefinition['bold']),
        'fontColor' => $fontColor !== '' ? $fontColor : '#1f2a3d',
        'fillColor' => $fillColor !== '' ? $fillColor : '#ffffff',
        'borderColor' => $borderColor !== '' ? $borderColor : '#c9d4e5',
        'horizontal' => $horizontal,
        'vertical' => $vertical,
        'wrapText' => !empty($styleDefinition['wrapText']),
        'numFmtCode' => $numFmtCode,
    ];
}

function app_render_chronological_export_sheet_xml(array $sheetPayload): string
{
    $rowsXml = [];

    foreach ($sheetPayload['rows'] as $row) {
        $cellsXml = [];

        foreach (($row['cells'] ?? []) as $cell) {
            $cellReference = app_convert_index_to_excel_column_reference((int) $cell['columnIndex']) . (int) $row['rowNumber'];
            $styleId = (int) ($cell['styleId'] ?? 0);
            $type = (string) ($cell['type'] ?? 'string');

            if ($type === 'number' && $cell['value'] !== '') {
                $cellsXml[] = sprintf(
                    '<c r="%s" s="%d"><v>%s</v></c>',
                    $cellReference,
                    $styleId,
                    app_format_chronological_export_excel_number((float) $cell['value'])
                );
                continue;
            }

            $cellsXml[] = sprintf(
                '<c r="%s" s="%d" t="inlineStr"><is><t xml:space="preserve">%s</t></is></c>',
                $cellReference,
                $styleId,
                app_escape_excel_xml((string) ($cell['value'] ?? ''))
            );
        }

        $rowsXml[] = sprintf(
            '<row r="%d" ht="%s" customHeight="1">%s</row>',
            (int) $row['rowNumber'],
            app_format_chronological_export_excel_number((float) ($row['height'] ?? 20)),
            implode('', $cellsXml)
        );
    }

    $hyperlinksXml = '';
    if (!empty($sheetPayload['hyperlinks'])) {
        $hyperlinkParts = [];
        foreach ((array) $sheetPayload['hyperlinks'] as $hyperlink) {
            $reference = trim((string) ($hyperlink['ref'] ?? ''));
            $relationshipId = trim((string) ($hyperlink['relationshipId'] ?? ''));
            if ($reference === '' || $relationshipId === '') {
                continue;
            }

            $hyperlinkParts[] = sprintf(
                '<hyperlink ref="%s" r:id="%s"/>',
                app_escape_excel_xml($reference),
                app_escape_excel_xml($relationshipId)
            );
        }

        if ($hyperlinkParts !== []) {
            $hyperlinksXml = '<hyperlinks>' . implode('', $hyperlinkParts) . '</hyperlinks>';
        }
    }

    $mergeCellsXml = '';
    if (!empty($sheetPayload['mergeCells'])) {
        $mergeParts = [];
        foreach ($sheetPayload['mergeCells'] as $reference) {
            $mergeParts[] = sprintf('<mergeCell ref="%s"/>', app_escape_excel_xml((string) $reference));
        }

        $mergeCellsXml = sprintf(
            '<mergeCells count="%d">%s</mergeCells>',
            count($sheetPayload['mergeCells']),
            implode('', $mergeParts)
        );
    }

    $progressConditionalFormattingXml = '';
    $progressConditionalFormattingParts = [];
    $progressPriority = 1;
    foreach ((array) ($sheetPayload['progressBars'] ?? []) as $progressBarRule) {
        $refs = array_values(array_filter((array) ($progressBarRule['refs'] ?? []), static function ($value): bool {
            return trim((string) $value) !== '';
        }));
        $color = trim((string) ($progressBarRule['color'] ?? ''));
        if ($refs === [] || $color === '') {
            continue;
        }

        $progressConditionalFormattingParts[] = sprintf(
            '<conditionalFormatting sqref="%s"><cfRule type="dataBar" priority="%d"><dataBar showValue="1"><cfvo type="num" val="0"/><cfvo type="num" val="100"/><color rgb="%s"/></dataBar></cfRule></conditionalFormatting>',
            app_escape_excel_xml(implode(' ', $refs)),
            $progressPriority,
            app_escape_excel_xml($color)
        );
        $progressPriority++;
    }
    if ($progressConditionalFormattingParts !== []) {
        $progressConditionalFormattingXml = implode('', $progressConditionalFormattingParts);
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<dimension ref="A1:K' . (int) ($sheetPayload['lastRowNumber'] ?? 1) . '"/>'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/>'
        . '<selection pane="bottomLeft" activeCell="A4" sqref="A4"/></sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="20"/>'
        . app_render_chronological_export_columns_xml((array) ($sheetPayload['columnWidths'] ?? []))
        . '<sheetData>' . implode('', $rowsXml) . '</sheetData>'
        . '<autoFilter ref="A3:K3"/>'
        . $mergeCellsXml
        . $progressConditionalFormattingXml
        . $hyperlinksXml
        . '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
        . '</worksheet>';
}

function app_render_chronological_export_columns_xml(array $columnWidths): string
{
    $parts = [];

    for ($columnIndex = 1; $columnIndex <= 11; $columnIndex++) {
        $width = isset($columnWidths[$columnIndex])
            ? app_normalize_chronological_export_column_width((float) $columnWidths[$columnIndex])
            : 12.0;

        $parts[] = sprintf(
            '<col min="%d" max="%d" width="%s" customWidth="1"/>',
            $columnIndex,
            $columnIndex,
            app_format_chronological_export_excel_number($width)
        );
    }

    return '<cols>' . implode('', $parts) . '</cols>';
}

function app_render_chronological_export_styles_xml(array $styleRegistry): string
{
    $styles = array_values((array) ($styleRegistry['styles'] ?? []));
    if ($styles === []) {
        $styles[] = app_normalize_chronological_export_style_definition([]);
    }

    $fonts = [];
    $fontIndexByKey = [];
    $fills = [
        ['patternType' => 'none'],
        ['patternType' => 'gray125'],
    ];
    $fillIndexByKey = [];
    $borders = [
        ['color' => ''],
    ];
    $borderIndexByKey = [];
    $numFmts = [];
    $numFmtIdByCode = [];
    $nextNumFmtId = 164;
    $xfRecords = [];

    foreach ($styles as $style) {
        $fontKey = json_encode([
            'name' => $style['fontName'],
            'size' => $style['fontSize'],
            'bold' => $style['bold'],
            'color' => $style['fontColor'],
        ], JSON_UNESCAPED_SLASHES);
        if (!isset($fontIndexByKey[$fontKey])) {
            $fontIndexByKey[$fontKey] = count($fonts);
            $fonts[] = [
                'name' => (string) $style['fontName'],
                'size' => (float) $style['fontSize'],
                'bold' => (bool) $style['bold'],
                'color' => app_convert_hex_color_to_excel_argb((string) $style['fontColor']),
            ];
        }

        $fillColor = app_normalize_hex_color((string) ($style['fillColor'] ?? ''));
        if ($fillColor === '' || $fillColor === '#ffffff') {
            $fillId = 0;
        } else {
            $fillKey = mb_strtolower($fillColor, 'UTF-8');
            if (!isset($fillIndexByKey[$fillKey])) {
                $fillIndexByKey[$fillKey] = count($fills);
                $fills[] = [
                    'patternType' => 'solid',
                    'color' => app_convert_hex_color_to_excel_argb($fillColor),
                ];
            }
            $fillId = (int) $fillIndexByKey[$fillKey];
        }

        $borderColor = app_normalize_hex_color((string) ($style['borderColor'] ?? ''));
        if ($borderColor === '') {
            $borderId = 0;
        } else {
            $borderKey = mb_strtolower($borderColor, 'UTF-8');
            if (!isset($borderIndexByKey[$borderKey])) {
                $borderIndexByKey[$borderKey] = count($borders);
                $borders[] = [
                    'color' => app_convert_hex_color_to_excel_argb($borderColor),
                ];
            }
            $borderId = (int) $borderIndexByKey[$borderKey];
        }

        $numFmtCode = (string) ($style['numFmtCode'] ?? '');
        if ($numFmtCode !== '') {
            if (!isset($numFmtIdByCode[$numFmtCode])) {
                $numFmtIdByCode[$numFmtCode] = $nextNumFmtId;
                $numFmts[] = [
                    'id' => $nextNumFmtId,
                    'code' => $numFmtCode,
                ];
                $nextNumFmtId++;
            }
            $numFmtId = (int) $numFmtIdByCode[$numFmtCode];
        } else {
            $numFmtId = 0;
        }

        $xfRecords[] = [
            'fontId' => (int) $fontIndexByKey[$fontKey],
            'fillId' => $fillId,
            'borderId' => $borderId,
            'numFmtId' => $numFmtId,
            'horizontal' => (string) ($style['horizontal'] ?? 'left'),
            'vertical' => (string) ($style['vertical'] ?? 'center'),
            'wrapText' => !empty($style['wrapText']),
        ];
    }

    $numFmtXml = '';
    if ($numFmts !== []) {
        $numFmtParts = [];
        foreach ($numFmts as $numFmt) {
            $numFmtParts[] = sprintf(
                '<numFmt numFmtId="%d" formatCode="%s"/>',
                (int) $numFmt['id'],
                app_escape_excel_xml((string) $numFmt['code'])
            );
        }
        $numFmtXml = sprintf('<numFmts count="%d">%s</numFmts>', count($numFmts), implode('', $numFmtParts));
    }

    $fontXmlParts = [];
    foreach ($fonts as $font) {
        $fontXmlParts[] = '<font>'
            . ($font['bold'] ? '<b/>' : '')
            . '<sz val="' . app_format_chronological_export_excel_number((float) $font['size']) . '"/>'
            . '<color rgb="' . app_escape_excel_xml((string) $font['color']) . '"/>'
            . '<name val="' . app_escape_excel_xml((string) $font['name']) . '"/>'
            . '<family val="2"/>'
            . '</font>';
    }

    $fillXmlParts = [];
    foreach ($fills as $fill) {
        if (($fill['patternType'] ?? '') === 'solid') {
            $fillXmlParts[] = '<fill><patternFill patternType="solid">'
                . '<fgColor rgb="' . app_escape_excel_xml((string) ($fill['color'] ?? 'FFFFFFFF')) . '"/>'
                . '<bgColor indexed="64"/>'
                . '</patternFill></fill>';
        } else {
            $fillXmlParts[] = '<fill><patternFill patternType="' . app_escape_excel_xml((string) ($fill['patternType'] ?? 'none')) . '"/></fill>';
        }
    }

    $borderXmlParts = ['<border><left/><right/><top/><bottom/><diagonal/></border>'];
    foreach (array_slice($borders, 1) as $border) {
        $color = app_escape_excel_xml((string) ($border['color'] ?? 'FF000000'));
        $borderXmlParts[] = '<border>'
            . '<left style="thin"><color rgb="' . $color . '"/></left>'
            . '<right style="thin"><color rgb="' . $color . '"/></right>'
            . '<top style="thin"><color rgb="' . $color . '"/></top>'
            . '<bottom style="thin"><color rgb="' . $color . '"/></bottom>'
            . '<diagonal/>'
            . '</border>';
    }

    $xfXmlParts = [];
    foreach ($xfRecords as $xfRecord) {
        $xfXmlParts[] = sprintf(
            '<xf numFmtId="%d" fontId="%d" fillId="%d" borderId="%d" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"%s>'
            . '<alignment horizontal="%s" vertical="%s" wrapText="%d"/>'
            . '</xf>',
            (int) $xfRecord['numFmtId'],
            (int) $xfRecord['fontId'],
            (int) $xfRecord['fillId'],
            (int) $xfRecord['borderId'],
            (int) $xfRecord['numFmtId'] > 0 ? ' applyNumberFormat="1"' : '',
            app_escape_excel_xml((string) $xfRecord['horizontal']),
            app_escape_excel_xml((string) $xfRecord['vertical']),
            !empty($xfRecord['wrapText']) ? 1 : 0
        );
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . $numFmtXml
        . '<fonts count="' . count($fonts) . '">' . implode('', $fontXmlParts) . '</fonts>'
        . '<fills count="' . count($fills) . '">' . implode('', $fillXmlParts) . '</fills>'
        . '<borders count="' . count($borderXmlParts) . '">' . implode('', $borderXmlParts) . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="' . count($xfRecords) . '">' . implode('', $xfXmlParts) . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '<dxfs count="0"/>'
        . '<tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/>'
        . '</styleSheet>';
}

function app_render_chronological_export_content_types_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
        . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '</Types>';
}

function app_render_chronological_export_root_relationships_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
        . '</Relationships>';
}

function app_render_chronological_export_app_properties_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"'
        . ' xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
        . '<Application>Dashboard</Application>'
        . '</Properties>';
}

function app_render_chronological_export_core_properties_xml(DateTimeImmutable $createdAt): string
{
    $timestamp = $createdAt->format('Y-m-d\TH:i:s\Z');

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:dcterms="http://purl.org/dc/terms/"'
        . ' xmlns:dcmitype="http://purl.org/dc/dcmitype/"'
        . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
        . '<dc:creator>Dashboard</dc:creator>'
        . '<cp:lastModifiedBy>Dashboard</cp:lastModifiedBy>'
        . '<dcterms:created xsi:type="dcterms:W3CDTF">' . app_escape_excel_xml($timestamp) . '</dcterms:created>'
        . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . app_escape_excel_xml($timestamp) . '</dcterms:modified>'
        . '</cp:coreProperties>';
}

function app_render_chronological_export_workbook_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<fileVersion appName="xl"/>'
        . '<workbookPr date1904="false"/>'
        . '<bookViews><workbookView xWindow="0" yWindow="0" windowWidth="28800" windowHeight="15840"/></bookViews>'
        . '<sheets><sheet name="Liste chrono" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';
}

function app_render_chronological_export_workbook_relationships_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';
}

function app_render_chronological_export_sheet_relationships_xml(array $hyperlinks): string
{
    $relationships = [];

    foreach ($hyperlinks as $hyperlink) {
        $relationshipId = trim((string) ($hyperlink['relationshipId'] ?? ''));
        $target = trim((string) ($hyperlink['target'] ?? ''));
        if ($relationshipId === '' || $target === '') {
            continue;
        }

        $relationships[] = sprintf(
            '<Relationship Id="%s" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="%s" TargetMode="External"/>',
            app_escape_excel_xml($relationshipId),
            app_escape_excel_xml($target)
        );
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . implode('', $relationships)
        . '</Relationships>';
}

function app_convert_index_to_excel_column_reference(int $columnIndex): string
{
    if ($columnIndex <= 0) {
        return 'A';
    }

    $reference = '';
    while ($columnIndex > 0) {
        $columnIndex--;
        $reference = chr(65 + ($columnIndex % 26)) . $reference;
        $columnIndex = intdiv($columnIndex, 26);
    }

    return $reference;
}

function app_convert_hex_color_to_excel_argb(string $color): string
{
    $normalizedColor = app_normalize_hex_color($color);
    if ($normalizedColor === '') {
        return 'FF000000';
    }

    return 'FF' . strtoupper(substr($normalizedColor, 1));
}

function app_escape_excel_xml(string $value): string
{
    $sanitizedValue = preg_replace('/[^\P{C}\t\n\r]/u', '', $value);
    if (!is_string($sanitizedValue)) {
        $sanitizedValue = $value;
    }

    return htmlspecialchars($sanitizedValue, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
}

function app_format_chronological_export_excel_number(float $value): string
{
    if ((float) (int) $value === $value) {
        return (string) (int) $value;
    }

    return rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
}

function app_convert_date_to_excel_serial(DateTimeImmutable $date): int
{
    $baseDate = new DateTimeImmutable('1899-12-30');
    return (int) $baseDate->diff($date->setTime(0, 0))->format('%r%a');
}

function app_resolve_chronological_export_title_column_excel_width(array $orderedProjects): float
{
    $maxTitleLength = 0;

    foreach ($orderedProjects as $entry) {
        $title = trim((string) (($entry['project']['title'] ?? '')));
        $maxTitleLength = max($maxTitleLength, mb_strlen($title, 'UTF-8'));
    }

    if ($maxTitleLength <= 0) {
        return 60.0;
    }

    return max(60.0, min(140.0, (float) ($maxTitleLength + 6)));
}

function app_resolve_chronological_export_status_background_color(string $status): string
{
    $statusKey = app_normalize_lookup_key($status);

    return match ($statusKey) {
        'encours' => '#f3c14f',
        'planifie' => '#b9ddff',
        'standby' => '#c7a4f7',
        'termine' => '#b7e5b4',
        'aplanifier' => '#e5e7eb',
        default => '',
    };
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

function app_sort_projects_for_chronological_export(array $projects): array
{
    $prepared = [];

    foreach ($projects as $index => $project) {
        if (!is_array($project)) {
            continue;
        }

        $dates = app_get_project_export_dates($project);
        $prepared[] = [
            'index' => $index,
            'project' => $project,
            'start' => $dates['start'],
            'end' => $dates['end'],
        ];
    }

    $scheduledEntries = [];
    $unscheduledEntries = [];
    $scheduledEntriesById = [];

    foreach ($prepared as $entry) {
        if (app_is_chronological_export_scheduled_entry($entry)) {
            $scheduledEntries[] = $entry;

            $projectId = trim((string) ($entry['project']['id'] ?? ''));
            if ($projectId !== '') {
                $scheduledEntriesById[$projectId] = $entry;
            }

            continue;
        }

        $unscheduledEntries[] = $entry;
    }

    $childrenByParentId = [];
    $rootEntries = [];

    foreach ($scheduledEntries as $entry) {
        $project = $entry['project'];
        $projectId = trim((string) ($project['id'] ?? ''));
        $parentProjectId = $projectId !== ''
            ? app_normalize_project_parent_id($project['parentProjectId'] ?? null, $projectId)
            : null;

        if ($parentProjectId !== null && isset($scheduledEntriesById[$parentProjectId])) {
            if (!isset($childrenByParentId[$parentProjectId])) {
                $childrenByParentId[$parentProjectId] = [];
            }

            $childrenByParentId[$parentProjectId][] = $entry;
            continue;
        }

        $rootEntries[] = $entry;
    }

    $orderedEntries = [];
    $visitedEntryKeys = [];

    foreach (app_sort_chronological_export_entries_by_lane_then_ref($rootEntries) as $rootEntry) {
        app_append_chronological_export_entry_tree($rootEntry, $childrenByParentId, $orderedEntries, $visitedEntryKeys, 0);
    }

    $remainingScheduledEntries = [];
    foreach ($scheduledEntries as $entry) {
        if (!isset($visitedEntryKeys[app_build_chronological_export_entry_key($entry)])) {
            $remainingScheduledEntries[] = $entry;
        }
    }

    foreach (app_sort_chronological_export_entries_by_lane_then_ref($remainingScheduledEntries) as $entry) {
        app_append_chronological_export_entry_tree($entry, $childrenByParentId, $orderedEntries, $visitedEntryKeys, 0);
    }

    foreach (app_sort_chronological_export_entries_by_lane_then_ref($unscheduledEntries) as $entry) {
        $entry['depth'] = 0;
        $orderedEntries[] = $entry;
    }

    return $orderedEntries;
}

function app_is_chronological_export_scheduled_entry(array $entry): bool
{
    return ($entry['start'] ?? null) instanceof DateTimeImmutable;
}

function app_build_chronological_export_entry_key(array $entry): string
{
    $projectId = trim((string) ($entry['project']['id'] ?? ''));
    if ($projectId !== '') {
        return $projectId;
    }

    return 'index:' . (string) ($entry['index'] ?? '');
}

function app_sort_chronological_export_entries_by_lane_then_ref(array $entries): array
{
    usort($entries, static function (array $left, array $right): int {
        $leftIsParentProject = app_is_chronological_export_parent_project($left['project'] ?? []);
        $rightIsParentProject = app_is_chronological_export_parent_project($right['project'] ?? []);
        if ($leftIsParentProject !== $rightIsParentProject) {
            return $leftIsParentProject ? -1 : 1;
        }

        if (!$leftIsParentProject && !$rightIsParentProject) {
            $leftStatusKey = app_resolve_chronological_export_entry_status_key($left);
            $rightStatusKey = app_resolve_chronological_export_entry_status_key($right);
            $statusComparison = app_resolve_chronological_export_status_rank($leftStatusKey)
                <=> app_resolve_chronological_export_status_rank($rightStatusKey);
            if ($statusComparison !== 0) {
                return $statusComparison;
            }

            if (
                app_is_chronological_export_service_grouped_status_key($leftStatusKey)
                && app_is_chronological_export_service_grouped_status_key($rightStatusKey)
            ) {
                $leftService = mb_strtolower(trim((string) ($left['project']['service'] ?? '')), 'UTF-8');
                $rightService = mb_strtolower(trim((string) ($right['project']['service'] ?? '')), 'UTF-8');
                $serviceComparison = $leftService <=> $rightService;
                if ($serviceComparison !== 0) {
                    return $serviceComparison;
                }
            }
        }

        $leftLane = isset($left['project']['lane']) && is_numeric($left['project']['lane'])
            ? (int) $left['project']['lane']
            : PHP_INT_MAX;
        $rightLane = isset($right['project']['lane']) && is_numeric($right['project']['lane'])
            ? (int) $right['project']['lane']
            : PHP_INT_MAX;
        $laneComparison = $leftLane <=> $rightLane;
        if ($laneComparison !== 0) {
            return $laneComparison;
        }

        $leftRef = mb_strtolower(trim((string) ($left['project']['ref'] ?? '')), 'UTF-8');
        $rightRef = mb_strtolower(trim((string) ($right['project']['ref'] ?? '')), 'UTF-8');
        $refComparison = $leftRef <=> $rightRef;
        if ($refComparison !== 0) {
            return $refComparison;
        }

        $leftTitle = mb_strtolower(trim((string) ($left['project']['title'] ?? '')), 'UTF-8');
        $rightTitle = mb_strtolower(trim((string) ($right['project']['title'] ?? '')), 'UTF-8');
        $titleComparison = $leftTitle <=> $rightTitle;
        if ($titleComparison !== 0) {
            return $titleComparison;
        }

        return (int) ($left['index'] ?? 0) <=> (int) ($right['index'] ?? 0);
    });

    return $entries;
}

function app_resolve_chronological_export_entry_status_key(array $entry): string
{
    return app_normalize_lookup_key(
        app_resolve_chronological_export_status(
            $entry['project'] ?? [],
            $entry['start'] ?? null,
            $entry['end'] ?? null
        )
    );
}

function app_is_chronological_export_service_grouped_status_key(string $statusKey): bool
{
    return in_array($statusKey, ['planifie', 'aplanifier'], true);
}

function app_resolve_chronological_export_status_rank(string $statusKey): int
{
    return match ($statusKey) {
        'termine' => 0,
        'encours' => 1,
        'planifie' => 2,
        'aplanifier' => 3,
        'standby' => 4,
        default => 5,
    };
}

function app_append_chronological_export_entry_tree(
    array $entry,
    array $childrenByParentId,
    array &$orderedEntries,
    array &$visitedEntryKeys,
    int $depth
): void {
    $entryKey = app_build_chronological_export_entry_key($entry);
    if (isset($visitedEntryKeys[$entryKey])) {
        return;
    }

    $visitedEntryKeys[$entryKey] = true;
    $entry['depth'] = $depth;
    $orderedEntries[] = $entry;

    $projectId = trim((string) ($entry['project']['id'] ?? ''));
    if ($projectId === '' || !isset($childrenByParentId[$projectId])) {
        return;
    }

    foreach (app_sort_chronological_export_entries_by_lane_then_ref($childrenByParentId[$projectId]) as $childEntry) {
        app_append_chronological_export_entry_tree(
            $childEntry,
            $childrenByParentId,
            $orderedEntries,
            $visitedEntryKeys,
            $depth + 1
        );
    }
}

function app_render_chronological_projects_excel_html_legacy(array $orderedProjects): string
{
    $generatedAt = (new DateTimeImmutable())->format('d/m/Y H:i');
    $rowCount = count($orderedProjects);
    $serviceColors = app_build_chronological_export_service_color_map();
    $existingServiceRows = app_fetch_existing_service_rows();

    $html = <<<HTML
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f7fb; color: #1f2a3d; }
        table { border-collapse: collapse; width: auto; }
        .report { min-width: 1020px; width: auto; }
        .report td, .report th { border: 1px solid #c9d4e5; padding: 10px 12px; vertical-align: middle; line-height: 1.15; }
        .report .title-row td {
            background: #253246;
            color: #ffffff;
            font-size: 18px;
            font-weight: 700;
            padding: 16px 18px;
            border-color: #253246;
        }
        .report .meta-row td {
            background: #eaf1fb;
            color: #52637a;
            font-size: 11px;
            padding: 8px 12px;
        }
        .report .header-row th {
            background: #3d88c8;
            color: #ffffff;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .report .data-row { height: 24pt; }
        .report .project-number { font-weight: 700; color: #1c4f7a; width: 140px; text-align: center; }
        .report .project-title { font-weight: 600; white-space: nowrap; }
        .report .project-service { width: 180px; text-align: center; }
        .report .project-date { width: 120px; text-align: center; mso-number-format:"dd\\/mm\\/yyyy"; }
        .report .project-date-empty { color: #8091a7; }
        .report .project-status { width: 140px; font-weight: 600; text-align: center; }
        .report .footer-row td {
            background: #eef4fb;
            color: #52637a;
            font-size: 11px;
            padding: 10px 12px;
        }
    </style>
</head>
<body>
<table class="report">
    <tr class="title-row">
        <td colspan="6"{$titleCellAttributes}>Planning projets</td>
    </tr>
    <tr class="meta-row">
        <td colspan="5">Généré le {$generatedAt} | {$rowCount} projet(s)</td>
    </tr>
    <tr class="header-row">
        <th>N° projet</th>
        <th>Titre</th>
        <th>Service</th>
        <th>Date début</th>
        <th>Date fin</th>
    </tr>
HTML;

    foreach ($orderedProjects as $entry) {
        $project = $entry['project'];
        $ref = app_escape_excel_html((string) ($project['ref'] ?? ''));
        $title = app_escape_excel_html((string) ($project['title'] ?? ''));
        $service = app_escape_excel_html((string) ($project['service'] ?? ''));
        $start = $entry['start'] instanceof DateTimeImmutable ? $entry['start']->format('d/m/Y') : '';
        $end = $entry['end'] instanceof DateTimeImmutable ? $entry['end']->format('d/m/Y') : '';
        $startClass = $start !== '' ? 'project-date' : 'project-date project-date-empty';
        $endClass = $end !== '' ? 'project-date' : 'project-date project-date-empty';
        $parentRowCellStyle = app_build_chronological_export_parent_row_style(
            $project,
            $serviceColors,
            $existingServiceRows
        );
        $serviceCellStyle = app_build_chronological_export_service_cell_style(
            (string) ($project['service'] ?? ''),
            $serviceColors,
            $existingServiceRows
        );
        $rowNumberStyle = $parentRowCellStyle !== '' ? ' style="' . app_escape_excel_html($parentRowCellStyle) . '"' : '';
        $rowTitleStyle = $parentRowCellStyle !== '' ? ' style="' . app_escape_excel_html($parentRowCellStyle) . '"' : '';
        $rowStartStyle = $parentRowCellStyle !== '' ? ' style="' . app_escape_excel_html($parentRowCellStyle) . '"' : '';
        $rowEndStyle = $parentRowCellStyle !== '' ? ' style="' . app_escape_excel_html($parentRowCellStyle) . '"' : '';
        $serviceStyle = $serviceCellStyle !== ''
            ? ' style="' . app_escape_excel_html($serviceCellStyle) . '"'
            : ($parentRowCellStyle !== '' ? ' style="' . app_escape_excel_html($parentRowCellStyle) . '"' : '');

        $html .= <<<HTML

    <tr class="data-row">
        <td class="project-number"{$rowNumberStyle}>{$ref}</td>
        <td class="project-title"{$rowTitleStyle}>{$title}</td>
        <td class="project-service"{$serviceStyle}>{$service}</td>
        <td class="{$startClass}"{$rowStartStyle}>{$start}</td>
        <td class="{$endClass}"{$rowEndStyle}>{$end}</td>
    </tr>
HTML;
    }

    $html .= <<<HTML

    <tr class="footer-row">
        <td colspan="5">Les projets sans date planifiée apparaissent à la fin du tableau.</td>
    </tr>
</table>
</body>
</html>
HTML;

    $html = str_replace('Planning projets - export chronologique', 'Planning projets', $html);
    $html = preg_replace('/\s*<tr class="footer-row">.*?<\/tr>/s', '', $html) ?? $html;

    return $html;
}

function app_render_chronological_projects_excel_html(array $orderedProjects): string
{
    $generatedAt = (new DateTimeImmutable())->format('d/m/Y H:i');
    $rowCount = count($orderedProjects);
    $titleColumnWidth = app_resolve_chronological_export_title_column_width($orderedProjects);
    $serviceColors = app_build_chronological_export_service_color_map();
    $existingServiceRows = app_fetch_existing_service_rows();
    $titleCellAttributes = app_build_chronological_export_inline_attributes(
        'background-color:#253246;background:#253246;color:#ffffff;border-color:#253246;font-size:18px;font-weight:700;padding:16px 18px;'
    );
    $metaCellAttributes = app_build_chronological_export_inline_attributes(
        'background-color:#eaf1fb;background:#eaf1fb;color:#52637a;font-size:11px;padding:8px 12px;'
    );
    $headerCellAttributes = app_build_chronological_export_inline_attributes(
        'background-color:#3d88c8;background:#3d88c8;color:#ffffff;border-color:#3d88c8;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.02em;'
    );
    $footerCellAttributes = app_build_chronological_export_inline_attributes(
        'background-color:#eef4fb;background:#eef4fb;color:#52637a;font-size:11px;padding:10px 12px;'
    );

    $html = <<<HTML
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f7fb; color: #1f2a3d; }
        table { border-collapse: collapse; width: auto; }
        .report { min-width: 1220px; width: auto; }
        .report td, .report th { border: 1px solid #c9d4e5; padding: 10px 12px; vertical-align: middle; line-height: 1.15; }
        .report .data-row { height: 24pt; }
        .report .project-number { font-weight: 700; color: #1c4f7a; width: 140px; text-align: center; }
        .report .project-title { width: {$titleColumnWidth}px; min-width: {$titleColumnWidth}px; font-weight: 600; white-space: nowrap; }
        .report .project-service { width: 180px; text-align: center; }
        .report .project-date { width: 120px; text-align: center; mso-number-format:"dd\\/mm\\/yyyy"; }
        .report .project-date-empty { color: #8091a7; }
        .report .project-manager { width: 180px; text-align: center; }
        .report .project-status { width: 140px; font-weight: 600; text-align: center; }
    </style>
</head>
<body>
<table class="report">
    <tr class="title-row">
        <td colspan="7"{$titleCellAttributes}>Planning projets</td>
    </tr>
    <tr class="meta-row">
        <td colspan="7"{$metaCellAttributes}>G&eacute;n&eacute;r&eacute; le {$generatedAt} | {$rowCount} projet(s)</td>
    </tr>
    <tr class="header-row">
        <th{$headerCellAttributes}>N&deg; projet</th>
        <th{$headerCellAttributes}>Titre</th>
        <th{$headerCellAttributes}>Service</th>
        <th{$headerCellAttributes}>Date d&eacute;but</th>
        <th{$headerCellAttributes}>Date fin</th>
        <th{$headerCellAttributes}>Chef de projet</th>
        <th{$headerCellAttributes}>Statut</th>
    </tr>
HTML;

    foreach ($orderedProjects as $rowIndex => $entry) {
        $project = $entry['project'];
        $isParentProject = app_is_chronological_export_parent_project($project);
        $resolvedStatus = !$isParentProject
            ? app_resolve_chronological_export_status($project, $entry['start'], $entry['end'])
            : '';
        $statusKey = app_normalize_lookup_key($resolvedStatus);
        $hideDates = $isParentProject || $statusKey === 'aplanifier';
        $ref = app_escape_excel_html((string) ($project['ref'] ?? ''));
        $title = app_escape_excel_html((string) ($project['title'] ?? ''));
        $service = $isParentProject ? '' : app_escape_excel_html((string) ($project['service'] ?? ''));
        $start = !$hideDates && $entry['start'] instanceof DateTimeImmutable ? $entry['start']->format('d/m/Y') : '';
        $end = !$hideDates && $entry['end'] instanceof DateTimeImmutable ? $entry['end']->format('d/m/Y') : '';
        $projectManager = !$isParentProject
            ? app_escape_excel_html((string) ($project['projectManager'] ?? ''))
            : '';
        $status = app_escape_excel_html($resolvedStatus);
        $startClass = $start !== '' ? 'project-date' : 'project-date project-date-empty';
        $endClass = $end !== '' ? 'project-date' : 'project-date project-date-empty';
        $baseRowStyle = app_build_chronological_export_default_row_style($rowIndex);
        $parentRowCellStyle = app_build_chronological_export_parent_row_style(
            $project,
            $serviceColors,
            $existingServiceRows
        );
        $serviceCellStyle = app_build_chronological_export_service_cell_style(
            (string) ($project['service'] ?? ''),
            $serviceColors,
            $existingServiceRows
        );
        $rowCellStyle = $parentRowCellStyle !== '' ? $parentRowCellStyle : $baseRowStyle;
        $completedCellStyle = (!$isParentProject && $statusKey === 'termine')
            ? app_build_chronological_export_completed_row_cell_style()
            : '';
        $rowNumberAttributes = app_build_chronological_export_inline_attributes(
            $completedCellStyle !== '' ? $completedCellStyle : $rowCellStyle
        );
        $rowTitleAttributes = app_build_chronological_export_inline_attributes(
            $completedCellStyle !== '' ? $completedCellStyle : $rowCellStyle
        );
        $rowStartAttributes = app_build_chronological_export_inline_attributes(
            $completedCellStyle !== '' ? $completedCellStyle : $rowCellStyle
        );
        $rowEndAttributes = app_build_chronological_export_inline_attributes(
            $completedCellStyle !== '' ? $completedCellStyle : $rowCellStyle
        );
        $rowProjectManagerAttributes = app_build_chronological_export_inline_attributes(
            $rowCellStyle
        );
        $statusCellStyle = $resolvedStatus !== ''
            ? app_build_chronological_export_status_cell_style($resolvedStatus)
            : '';
        $rowStatusAttributes = app_build_chronological_export_inline_attributes(
            $statusCellStyle !== '' ? $statusCellStyle : $rowCellStyle
        );
        $serviceAttributes = app_build_chronological_export_inline_attributes($isParentProject
            ? $rowCellStyle
            : ($serviceCellStyle !== '' ? $serviceCellStyle : $rowCellStyle)
        );

        $html .= <<<HTML

    <tr class="data-row">
        <td class="project-number"{$rowNumberAttributes}>{$ref}</td>
        <td class="project-title"{$rowTitleAttributes}>{$title}</td>
        <td class="project-service"{$serviceAttributes}>{$service}</td>
        <td class="{$startClass}"{$rowStartAttributes}>{$start}</td>
        <td class="{$endClass}"{$rowEndAttributes}>{$end}</td>
        <td class="project-manager"{$rowProjectManagerAttributes}>{$projectManager}</td>
        <td class="project-status"{$rowStatusAttributes}>{$status}</td>
    </tr>
HTML;
    }

    $html .= <<<HTML

    <tr class="footer-row">
        <td colspan="7"{$footerCellAttributes}>Les projets sans date planifi&eacute;e apparaissent &agrave; la fin du tableau.</td>
    </tr>
</table>
</body>
</html>
HTML;

    $html = str_replace('Planning projets - export chronologique', 'Planning projets', $html);
    $html = preg_replace('/\s*<tr class="footer-row">.*?<\/tr>/s', '', $html) ?? $html;

    return $html;
}

function app_escape_excel_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_resolve_chronological_export_title_column_width(array $orderedProjects): int
{
    $maxTitleLength = 0;

    foreach ($orderedProjects as $entry) {
        $title = trim((string) (($entry['project']['title'] ?? '')));
        $maxTitleLength = max($maxTitleLength, mb_strlen($title, 'UTF-8'));
    }

    if ($maxTitleLength <= 0) {
        return 420;
    }

    return max(420, min(1200, (int) ceil(($maxTitleLength * 7.4) + 72)));
}

function app_build_chronological_export_service_color_map(): array
{
    $normalizedColors = [];

    foreach (app_fetch_service_colors() as $serviceName => $color) {
        $normalizedService = app_normalize_service_key((string) $serviceName);
        $normalizedColor = app_normalize_hex_color((string) $color);
        if ($normalizedService === '' || $normalizedColor === '') {
            continue;
        }

        $normalizedColors[$normalizedService] = $normalizedColor;
    }

    return $normalizedColors;
}

function app_build_chronological_export_inline_attributes(string $style): string
{
    $normalizedStyle = trim($style);
    if ($normalizedStyle === '') {
        return '';
    }

    $backgroundColor = app_extract_chronological_export_background_color($normalizedStyle);
    $backgroundAttribute = $backgroundColor !== ''
        ? ' bgcolor="' . app_escape_excel_html($backgroundColor) . '"'
        : '';

    return $backgroundAttribute . ' style="' . app_escape_excel_html($normalizedStyle) . '"';
}

function app_extract_chronological_export_background_color(string $style): string
{
    if (preg_match('/background-color\s*:\s*(#[0-9a-f]{6})/i', $style, $matches) === 1) {
        return app_normalize_hex_color($matches[1]);
    }

    if (preg_match('/background\s*:\s*(#[0-9a-f]{6})/i', $style, $matches) === 1) {
        return app_normalize_hex_color($matches[1]);
    }

    return '';
}

function app_build_chronological_export_default_row_style(int $rowIndex): string
{
    $backgroundColor = '#ffffff';

    return sprintf(
        'background-color:%1$s;background:%1$s;mso-pattern:auto none;color:#1f2a3d;border-color:#c9d4e5;',
        $backgroundColor
    );
}

function app_build_chronological_export_completed_row_cell_style(): string
{
    return 'background-color:#d7dbe2;background:#d7dbe2;mso-pattern:auto none;color:#566173;border-color:#d7dbe2;';
}

function app_build_chronological_export_parent_row_style(array $project, array $serviceColors, array $existingServiceRows): string
{
    if (!app_is_chronological_export_parent_project($project)) {
        return '';
    }

    $backgroundColor = app_resolve_chronological_export_project_color($project, $serviceColors, $existingServiceRows);
    if ($backgroundColor === '') {
        return '';
    }

    $textColor = app_resolve_chronological_export_text_color($backgroundColor);

    return sprintf(
        'background-color:%1$s;background:%1$s;mso-pattern:auto none;color:%2$s;border-color:%1$s;font-weight:700;',
        $backgroundColor,
        $textColor
    );
}

function app_resolve_chronological_export_project_color(array $project, array $serviceColors, array $existingServiceRows): string
{
    $explicitColor = app_normalize_hex_color((string) ($project['color'] ?? ''));
    if ($explicitColor !== '') {
        return $explicitColor;
    }

    return app_resolve_chronological_export_service_color(
        (string) ($project['service'] ?? ''),
        $serviceColors,
        $existingServiceRows
    );
}

function app_build_chronological_export_service_cell_style(string $service, array $serviceColors, array $existingServiceRows): string
{
    $backgroundColor = app_resolve_chronological_export_service_color($service, $serviceColors, $existingServiceRows);
    if ($backgroundColor === '') {
        return '';
    }

    $textColor = app_resolve_chronological_export_text_color($backgroundColor);

    return sprintf(
        'background-color:%1$s;background:%1$s;mso-pattern:auto none;color:%2$s;border-color:%1$s;font-weight:700;',
        $backgroundColor,
        $textColor
    );
}

function app_build_chronological_export_status_cell_style(string $status): string
{
    $statusKey = app_normalize_lookup_key($status);
    $backgroundColor = match ($statusKey) {
        'encours' => '#f3c14f',
        'planifie' => '#b9ddff',
        'standby' => '#c7a4f7',
        'termine' => '#b7e5b4',
        'aplanifier' => '#e5e7eb',
        default => '',
    };

    if ($backgroundColor === '') {
        return '';
    }

    $textColor = app_resolve_chronological_export_text_color($backgroundColor);

    return sprintf(
        'background-color:%1$s;background:%1$s;mso-pattern:auto none;color:%2$s;border-color:%1$s;font-weight:700;',
        $backgroundColor,
        $textColor
    );
}

function app_resolve_chronological_export_service_color(string $service, array $serviceColors, array $existingServiceRows): string
{
    foreach (preg_split('/\s*\/\s*/', trim($service)) ?: [] as $token) {
        $resolvedService = app_resolve_service_name($token, $existingServiceRows);
        $serviceKey = app_normalize_service_key($resolvedService);
        if ($serviceKey !== '' && isset($serviceColors[$serviceKey])) {
            return $serviceColors[$serviceKey];
        }
    }

    return '';
}

function app_is_chronological_export_parent_project(array $project): bool
{
    static $parentProjectRefs = [
        'prjtrv',
        'prjnontrv',
        'mtnevo',
        'prjreccrnt',
    ];

    return in_array(
        app_normalize_lookup_key((string) ($project['ref'] ?? '')),
        $parentProjectRefs,
        true
    );
}

function app_resolve_chronological_export_text_color(string $backgroundColor): string
{
    $normalizedColor = app_normalize_hex_color($backgroundColor);
    if ($normalizedColor === '') {
        return '#1f2a3d';
    }

    $red = hexdec(substr($normalizedColor, 1, 2));
    $green = hexdec(substr($normalizedColor, 3, 2));
    $blue = hexdec(substr($normalizedColor, 5, 2));
    $luminance = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;

    return $luminance < 145 ? '#ffffff' : '#1f2a3d';
}

function app_resolve_chronological_export_status(array $project, ?DateTimeImmutable $start, ?DateTimeImmutable $end): string
{
    return app_normalize_project_status_value($project['status'] ?? null, $project);
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
