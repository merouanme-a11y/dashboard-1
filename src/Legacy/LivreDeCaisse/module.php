<?php
declare(strict_types=1);

const LDC_AGENCE_NOM = 'TestIT';
const LDC_AGENCE_DEPT = '75';
const LDC_AGENCE_ID = '75_TestIT';
const LDC_VERSION_FICHIER = 'V2.23';
const LDC_BDX_NUM = 113;
const LDC_CHRONO_INIT = 135;
const LDC_FOND_CAISSE_DEBUT = 550.04;
const LDC_ANTICIPATION_MONTHS_WINDOW = 24;
const LDC_ATTACHMENT_MAX_FILE_SIZE = 10485760;

function ldcGetTypesAffaire(): array
{
    return [
        'AN',
        'AN DEMATERIALISEE',
        'IMPAYE',
        'COMPLEMENT',
        'RECOUVREMENT',
    ];
}

function ldcGetRisques(): array
{
    return [
        'AGGEMA',
        'ASSISTANCE',
        'AUTO OA',
        'CCMO',
        'COLLECTIVES',
        'GROUPAMA',
        'MRH OA',
        'SANTE',
        'SPI',
        'VIE LOGICA',
        'VIE OA',
    ];
}

function ldcGetBaseEncaissements(): array
{
    return [
        'Espèces',
        'Chèque',
        'CB',
    ];
}

function ldcGetExtendedEncaissements(): array
{
    return [
        'Comptant Offert',
        'Comptant à Prélever',
        'Appel de Cotisation',
    ];
}

function ldcGetTypeAffaireRules(): array
{
    return [
        'AN' => [
            'helper' => "Affaire nouvelle : tous les champs visibles sont obligatoires sauf DSU.",
            'visible_fields' => [
                'num_adhesion',
                'date_effet',
                'formule_produit',
                'mandataire',
                'dsu',
            ],
            'required_fields' => [
                'num_adhesion',
                'date_effet',
                'formule_produit',
                'mandataire',
            ],
        ],
        'AN DEMATERIALISEE' => [
            'helper' => "Affaire nouvelle dématérialisée : tous les champs visibles sont obligatoires sauf DSU.",
            'visible_fields' => [
                'num_adhesion',
                'date_effet',
                'formule_produit',
                'mandataire',
                'dsu',
            ],
            'required_fields' => [
                'num_adhesion',
                'date_effet',
                'formule_produit',
                'mandataire',
            ],
        ],
        'IMPAYE' => [
            'helper' => "Impayé : contrat obligatoire, options de régularisation visibles, mois d'anticipation facultatif.",
            'visible_fields' => [
                'num_contrat',
                'mois_anticipation',
                'saisie_oa',
                'reglement_avis',
                'regul_impaye',
                'regul_mise_demeure',
            ],
            'required_fields' => [
                'num_contrat',
            ],
        ],
        'COMPLEMENT' => [
            'helper' => "Complément : tous les champs visibles sont obligatoires sauf DSU.",
            'visible_fields' => [
                'num_contrat',
                'formule_produit',
                'mandataire',
                'dsu',
            ],
            'required_fields' => [
                'num_contrat',
                'formule_produit',
                'mandataire',
            ],
        ],
        'RECOUVREMENT' => [
            'helper' => "Recouvrement : contrat obligatoire, options de régularisation visibles, mois d'anticipation facultatif.",
            'visible_fields' => [
                'num_contrat',
                'mois_anticipation',
                'saisie_oa',
                'reglement_avis',
                'regul_impaye',
                'regul_mise_demeure',
            ],
            'required_fields' => [
                'num_contrat',
            ],
        ],
    ];
}

function ldcGetConditionalScalarFields(): array
{
    return [
        'num_contrat',
        'mois_anticipation',
        'num_adhesion',
        'date_effet',
        'formule_produit',
        'mandataire',
        'dsu',
    ];
}

function ldcGetConditionalBooleanFields(): array
{
    return [
        'saisie_oa',
        'reglement_avis',
        'avenant',
        'regul_impaye',
        'regul_mise_demeure',
    ];
}

function ldcGetTypeAffaireWithExtendedEncaissements(): array
{
    return [
        'AN',
        'AN DEMATERIALISEE',
        'COMPLEMENT',
    ];
}

function ldcGetVisibleFieldsForTypeAffaire(?string $typeAffaire): array
{
    $rules = ldcGetTypeAffaireRules();

    return $typeAffaire !== null && isset($rules[$typeAffaire]['visible_fields'])
        ? (array) $rules[$typeAffaire]['visible_fields']
        : [];
}

function ldcGetRequiredFieldsForTypeAffaire(?string $typeAffaire): array
{
    $rules = ldcGetTypeAffaireRules();

    return $typeAffaire !== null && isset($rules[$typeAffaire]['required_fields'])
        ? (array) $rules[$typeAffaire]['required_fields']
        : [];
}

function ldcTypeAffaireShowsField(?string $typeAffaire, string $field): bool
{
    return in_array($field, ldcGetVisibleFieldsForTypeAffaire($typeAffaire), true);
}

function ldcTypeAffaireRequiresField(?string $typeAffaire, string $field): bool
{
    return in_array($field, ldcGetRequiredFieldsForTypeAffaire($typeAffaire), true);
}

function ldcTypeAffaireShowsAnyField(?string $typeAffaire, array $fields): bool
{
    foreach ($fields as $field) {
        if (ldcTypeAffaireShowsField($typeAffaire, (string) $field)) {
            return true;
        }
    }

    return false;
}

function ldcGetTypeAffaireHelper(?string $typeAffaire): string
{
    $rules = ldcGetTypeAffaireRules();

    return $typeAffaire !== null ? (string) ($rules[$typeAffaire]['helper'] ?? '') : '';
}

function ldcTypeAffaireHasExtendedEncaissements(?string $typeAffaire): bool
{
    return in_array($typeAffaire, ldcGetTypeAffaireWithExtendedEncaissements(), true);
}

function ldcGetTypeEncaissementOptionsForTypeAffaire(?string $typeAffaire): array
{
    $options = ldcGetBaseEncaissements();

    if (ldcTypeAffaireHasExtendedEncaissements($typeAffaire)) {
        $options = array_merge($options, ldcGetExtendedEncaissements());
    }

    return $options;
}

function ldcGetTypeEncaissementOptionsByAffaire(): array
{
    $options = ['' => ldcGetTypeEncaissementOptionsForTypeAffaire(null)];

    foreach (ldcGetTypesAffaire() as $typeAffaire) {
        $options[$typeAffaire] = ldcGetTypeEncaissementOptionsForTypeAffaire($typeAffaire);
    }

    return $options;
}

function ldcNormalizeMoneyInput($value): float
{
    $normalized = str_replace([' ', ','], ['', '.'], trim((string) $value));

    return round((float) $normalized, 2);
}

function ldcFormatEuro($value): string
{
    return number_format((float) $value, 2, ',', ' ') . ' €';
}

function ldcNormalizeAnticipationMonthValues($value): array
{
    $rawValues = is_array($value)
        ? $value
        : preg_split('/\s*,\s*/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);

    $normalized = [];

    foreach ($rawValues as $rawValue) {
        $rawValue = trim((string) $rawValue);
        if (!preg_match('/^\d{4}-\d{2}$/', $rawValue)) {
            continue;
        }

        [$year, $month] = explode('-', $rawValue);
        $monthNumber = (int) $month;
        if ($monthNumber < 1 || $monthNumber > 12) {
            continue;
        }

        $normalized[] = sprintf('%04d-%02d', (int) $year, $monthNumber);
    }

    $normalized = array_values(array_unique($normalized));
    sort($normalized, SORT_STRING);

    return $normalized;
}

function ldcFormatAnticipationMonthLabel(string $monthValue): string
{
    static $monthLabels = [
        1 => 'Janvier',
        2 => 'Février',
        3 => 'Mars',
        4 => 'Avril',
        5 => 'Mai',
        6 => 'Juin',
        7 => 'Juillet',
        8 => 'Août',
        9 => 'Septembre',
        10 => 'Octobre',
        11 => 'Novembre',
        12 => 'Décembre',
    ];

    if (!preg_match('/^(\d{4})-(\d{2})$/', $monthValue, $matches)) {
        return $monthValue;
    }

    $year = (int) $matches[1];
    $month = (int) $matches[2];

    return ($monthLabels[$month] ?? $monthValue) . '-' . substr((string) $year, -2);
}

function ldcFormatAnticipationSummary($value): string
{
    $months = ldcNormalizeAnticipationMonthValues($value);
    if ($months === []) {
        return '-';
    }

    $labels = array_map('ldcFormatAnticipationMonthLabel', $months);
    $count = count($labels);

    return sprintf('%d mois : %s', $count, implode(', ', $labels));
}

function ldcGetAnticipationMonthOptions(?string $baseDate = null, int $months = LDC_ANTICIPATION_MONTHS_WINDOW, array $selectedValues = []): array
{
    $selectedValues = ldcNormalizeAnticipationMonthValues($selectedValues);

    try {
        $startDate = $baseDate ? new DateTimeImmutable($baseDate) : new DateTimeImmutable('today');
    } catch (Throwable $exception) {
        $startDate = new DateTimeImmutable('today');
    }

    $cursor = $startDate->modify('first day of this month');
    $options = [];

    for ($index = 0; $index < $months; $index++) {
        $current = $cursor->modify(sprintf('+%d month', $index));
        $value = $current->format('Y-m');
        $options[$value] = ldcFormatAnticipationMonthLabel($value);
    }

    foreach ($selectedValues as $selectedValue) {
        $options[$selectedValue] = ldcFormatAnticipationMonthLabel($selectedValue);
    }

    ksort($options, SORT_STRING);

    $formatted = [];
    foreach ($options as $value => $label) {
        $formatted[] = [
            'value' => $value,
            'label' => $label,
        ];
    }

    return $formatted;
}

function ldcNormalizeCheckboxValue(bool $checked): string
{
    return $checked ? 'Oui' : 'Non';
}

function ldcNormalizeEntryForTypeAffaire(array $entry): array
{
    $visibleFields = ldcGetVisibleFieldsForTypeAffaire($entry['type_affaire'] ?? '');

    foreach (ldcGetConditionalScalarFields() as $field) {
        if (!in_array($field, $visibleFields, true)) {
            $entry[$field] = '';
        }
    }

    foreach (ldcGetConditionalBooleanFields() as $field) {
        if (!in_array($field, $visibleFields, true)) {
            $entry[$field] = 'Non';
        }
    }

    $entry['mois_anticipation'] = implode(',', ldcNormalizeAnticipationMonthValues($entry['mois_anticipation'] ?? ''));

    $allowedEncaissements = ldcGetTypeEncaissementOptionsForTypeAffaire($entry['type_affaire'] ?? null);
    if (!in_array($entry['type_encaissement'] ?? '', $allowedEncaissements, true)) {
        $entry['type_encaissement'] = '';
    }

    if (!in_array($entry['type_encaissement'] ?? '', ['Chèque', 'CB'], true)) {
        $entry['num_cheque'] = '';
    }

    return $entry;
}

function ldcApplyEncaissementVentilation(array $entry): array
{
    $amount = (float) ($entry['montant'] ?? 0);

    $entry['especes'] = 0.0;
    $entry['cheque'] = 0.0;
    $entry['cb'] = 0.0;
    $entry['comptant_prelever'] = 0.0;
    $entry['comptant_offert'] = 0.0;
    $entry['appel_cotisation'] = 0.0;

    switch ($entry['type_encaissement'] ?? '') {
        case 'Espèces':
            $entry['especes'] = $amount;
            break;
        case 'Chèque':
            $entry['cheque'] = $amount;
            break;
        case 'CB':
            $entry['cb'] = $amount;
            break;
        case 'Comptant à Prélever':
            $entry['comptant_prelever'] = $amount;
            break;
        case 'Comptant Offert':
            $entry['comptant_offert'] = $amount;
            break;
        case 'Appel de Cotisation':
            $entry['appel_cotisation'] = $amount;
            break;
    }

    return $entry;
}

function ldcToday(): string
{
    return date('Y-m-d');
}

function ldcResolveBusinessDate(?string $value = null): string
{
    $normalizedValue = ldcFormatInputDate($value);

    return $normalizedValue !== '' ? $normalizedValue : ldcToday();
}

function ldcCurrentDateLabel(?string $value = null): string
{
    $referenceDate = ldcResolveBusinessDate($value);

    if (class_exists('IntlDateFormatter')) {
        $formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE);
        return (string) $formatter->format(new DateTimeImmutable($referenceDate));
    }

    return (new DateTimeImmutable($referenceDate))->format('d/m/Y');
}

function ldcGetAgenceContext(?array $user = null): array
{
    $departement = trim((string) ($user['departement'] ?? ''));
    $agence = trim((string) ($user['agence'] ?? ''));

    if ($departement === '') {
        $departement = LDC_AGENCE_DEPT;
    }

    if ($agence === '') {
        $agence = LDC_AGENCE_NOM;
    }

    $agenceId = trim($departement . '_' . $agence, '_');
    if ($agenceId === '') {
        $agenceId = LDC_AGENCE_ID;
    }

    return [
        'departement' => $departement,
        'agence' => $agence,
        'id' => $agenceId,
        'label' => trim($agence . ($departement !== '' ? ' (' . $departement . ')' : '')),
    ];
}

function ldcCurrentAgenceSnapshot(): array
{
    return ldcGetAgenceContext(app_current_livre_de_caisse_user());
}

function ldcFormatInputDate(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($value))->format('Y-m-d');
    } catch (Throwable $exception) {
        return '';
    }
}

function ldcFormatDisplayDate(?string $value, string $format = 'd/m/Y'): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '-';
    }

    try {
        return (new DateTimeImmutable($value))->format($format);
    } catch (Throwable $exception) {
        return '-';
    }
}

function ldcFormatDisplayDateTime(?string $value, string $format = 'd/m/Y H:i'): string
{
    return ldcFormatDisplayDate($value, $format);
}

function ldcGetAllowedAttachmentMimeMap(): array
{
    return [
        'image/jpeg' => 'JPG',
        'image/png' => 'PNG',
        'application/pdf' => 'PDF',
    ];
}

function ldcNormalizeUploadedFilesArray(?array $files): array
{
    if ($files === null || $files === [] || !isset($files['name'])) {
        return [];
    }

    if (!is_array($files['name'])) {
        return [$files];
    }

    $normalized = [];
    $count = count($files['name']);

    for ($index = 0; $index < $count; $index++) {
        $normalized[] = [
            'name' => $files['name'][$index] ?? '',
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0,
        ];
    }

    return $normalized;
}

function ldcFormatAttachmentSize(int $size): string
{
    $units = ['o', 'Ko', 'Mo', 'Go'];
    $value = max(0, $size);
    $unitIndex = 0;

    while ($value >= 1024 && $unitIndex < count($units) - 1) {
        $value /= 1024;
        $unitIndex++;
    }

    return number_format($value, $unitIndex === 0 ? 0 : 1, ',', ' ') . ' ' . $units[$unitIndex];
}

function ldcHydrateAttachmentRow(array $row): array
{
    $defaults = [
        'id' => 0,
        'record_type' => 'attachment',
        'reference_key' => null,
        'livredecaisse_entry_id' => null,
        'attachment_entry_id' => null,
        'business_date' => ldcToday(),
        'attachment_file_name' => '',
        'attachment_mime' => '',
        'attachment_size' => 0,
        'attachment_blob' => null,
        'created_by' => '',
        'updated_by' => '',
        'created_at' => '',
        'updated_at' => '',
    ];

    $attachment = array_merge($defaults, $row);
    $parentEntryId = $attachment['livredecaisse_entry_id'] ?? $attachment['attachment_entry_id'];

    $attachment['id'] = (int) $attachment['id'];
    $attachment['livredecaisse_entry_id'] = $parentEntryId === null ? null : (int) $parentEntryId;
    $attachment['attachment_entry_id'] = $parentEntryId === null ? null : (int) $parentEntryId;
    $attachment['attachment_size'] = (int) ($attachment['attachment_size'] ?? 0);

    return $attachment;
}

function ldcFetchAttachments(PDO $pdo, int $entryId): array
{
    if ($entryId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT * FROM livredecaisse_attachments
         WHERE livredecaisse_entry_id = ?
         ORDER BY id DESC"
    );
    $stmt->execute([$entryId]);
    $rows = $stmt->fetchAll() ?: [];

    return array_map('ldcHydrateAttachmentRow', $rows);
}

function ldcFetchAttachmentById(PDO $pdo, int $attachmentId): ?array
{
    if ($attachmentId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT * FROM livredecaisse_attachments
         WHERE id = ?
         LIMIT 1"
    );
    $stmt->execute([$attachmentId]);
    $row = $stmt->fetch();

    return $row ? ldcHydrateAttachmentRow($row) : null;
}

function ldcFetchAttachmentsByBusinessDate(PDO $pdo, string $businessDate): array
{
    $resolvedDate = ldcResolveBusinessDate($businessDate);
    $stmt = $pdo->prepare(
        "SELECT
            a.*,
            e.chrono AS entry_chrono,
            e.nom_adherent AS entry_nom_adherent,
            e.prenom_adherent AS entry_prenom_adherent
         FROM livredecaisse_attachments a
         LEFT JOIN livredecaisse e
            ON e.id = a.livredecaisse_entry_id
           AND e.record_type = 'entry'
         WHERE a.business_date = ?
         ORDER BY COALESCE(e.chrono, 0) ASC, a.id ASC"
    );
    $stmt->execute([$resolvedDate]);
    $rows = $stmt->fetchAll() ?: [];

    return array_map(
        static function (array $row): array {
            $attachment = ldcHydrateAttachmentRow($row);
            $attachment['entry_chrono'] = $row['entry_chrono'] !== null ? (int) $row['entry_chrono'] : null;
            $attachment['entry_nom_adherent'] = trim((string) ($row['entry_nom_adherent'] ?? ''));
            $attachment['entry_prenom_adherent'] = trim((string) ($row['entry_prenom_adherent'] ?? ''));

            return $attachment;
        },
        $rows
    );
}

function ldcSanitizeArchivePathSegment(string $value, string $fallback = 'fichier'): string
{
    $value = trim($value);
    if ($value === '') {
        return $fallback;
    }

    $value = str_replace(['\\', '/'], '-', $value);
    $value = preg_replace('/[^A-Za-z0-9._-]+/', '-', $value) ?? '';
    $value = trim($value, '-._ ');

    return $value !== '' ? $value : $fallback;
}

function ldcBuildAttachmentArchiveEntryName(array $attachment, array &$usedNames): string
{
    $folderParts = [];

    if (($attachment['entry_chrono'] ?? null) !== null) {
        $folderParts[] = 'chrono-' . sprintf('%05d', (int) $attachment['entry_chrono']);
    }

    $adherentLabel = trim(
        trim((string) ($attachment['entry_nom_adherent'] ?? '')) . '_' .
        trim((string) ($attachment['entry_prenom_adherent'] ?? '')),
        '_'
    );
    if ($adherentLabel !== '') {
        $folderParts[] = ldcSanitizeArchivePathSegment($adherentLabel, 'adherent');
    }

    $folderName = $folderParts !== [] ? implode('_', $folderParts) : 'autres-fichiers';
    $originalFileName = ldcSanitizeArchivePathSegment((string) ($attachment['attachment_file_name'] ?? ''), 'piece-jointe');
    $pathInfo = pathinfo($originalFileName);
    $baseName = ldcSanitizeArchivePathSegment((string) ($pathInfo['filename'] ?? 'piece-jointe'), 'piece-jointe');
    $extension = isset($pathInfo['extension']) && $pathInfo['extension'] !== ''
        ? '.' . ldcSanitizeArchivePathSegment((string) $pathInfo['extension'], 'dat')
        : '';

    $candidate = $folderName . '/' . $baseName . $extension;
    $suffix = 2;
    while (isset($usedNames[strtolower($candidate)])) {
        $candidate = $folderName . '/' . $baseName . '-' . $suffix . $extension;
        $suffix++;
    }

    $usedNames[strtolower($candidate)] = true;

    return $candidate;
}

function ldcStreamAttachmentsArchive(array $attachments, string $archiveFileName): never
{
    if ($attachments === []) {
        http_response_code(404);
        exit('Aucune piece jointe a telecharger pour cette journee.');
    }

    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        exit('L extension ZIP n est pas disponible sur le serveur.');
    }

    $tempPath = tempnam(sys_get_temp_dir(), 'ldczip_');
    if ($tempPath === false) {
        throw new RuntimeException('Impossible de preparer l archive ZIP.');
    }

    $zip = new ZipArchive();
    $openResult = $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($openResult !== true) {
        @unlink($tempPath);
        throw new RuntimeException('Impossible de creer l archive ZIP.');
    }

    $usedNames = [];
    foreach ($attachments as $attachment) {
        $entryName = ldcBuildAttachmentArchiveEntryName($attachment, $usedNames);
        $content = (string) ($attachment['attachment_blob'] ?? '');

        if (!$zip->addFromString($entryName, $content)) {
            $zip->close();
            @unlink($tempPath);
            throw new RuntimeException('Impossible d ajouter une piece jointe a l archive ZIP.');
        }
    }

    $zip->close();

    $archiveFileName = ldcSanitizeArchivePathSegment($archiveFileName, 'pieces-jointes') . '.zip';

    header('Content-Type: application/zip');
    header('Content-Length: ' . (string) filesize($tempPath));
    header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($archiveFileName));
    header('X-Content-Type-Options: nosniff');

    readfile($tempPath);
    @unlink($tempPath);
    exit;
}

function ldcPersistUploadedAttachments(PDO $pdo, int $entryId, string $businessDate, ?array $files, string $userId = ''): array
{
    $uploadedFiles = ldcNormalizeUploadedFilesArray($files);
    if ($entryId <= 0 || $uploadedFiles === []) {
        return [];
    }

    $allowedMimes = ldcGetAllowedAttachmentMimeMap();
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $stmt = $pdo->prepare(
        "INSERT INTO livredecaisse_attachments (
            reference_key,
            livredecaisse_entry_id,
            business_date,
            attachment_file_name,
            attachment_mime,
            attachment_size,
            attachment_blob,
            created_by,
            updated_by
        ) VALUES (
            :reference_key,
            :livredecaisse_entry_id,
            :business_date,
            :attachment_file_name,
            :attachment_mime,
            :attachment_size,
            :attachment_blob,
            :created_by,
            :updated_by
        )"
    );

    foreach ($uploadedFiles as $uploadedFile) {
        $errorCode = (int) ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Une pièce jointe n’a pas pu être téléversée.');
        }

        $tmpName = (string) ($uploadedFile['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Le fichier reçu est invalide.');
        }

        $fileSize = (int) ($uploadedFile['size'] ?? 0);
        if ($fileSize <= 0 || $fileSize > LDC_ATTACHMENT_MAX_FILE_SIZE) {
            throw new RuntimeException('Chaque pièce jointe doit faire au maximum 10 Mo.');
        }

        $mimeType = (string) $finfo->file($tmpName);
        if (!isset($allowedMimes[$mimeType])) {
            throw new RuntimeException('Formats acceptés : JPG, PNG ou PDF.');
        }

        $originalName = trim((string) ($uploadedFile['name'] ?? ''));
        $safeName = $originalName !== '' ? basename($originalName) : ('piece-jointe-' . date('Ymd-His'));
        $blob = file_get_contents($tmpName);
        if ($blob === false) {
            throw new RuntimeException('Impossible de lire la pièce jointe téléversée.');
        }

        $stmt->execute([
            'reference_key' => 'att_' . bin2hex(random_bytes(16)),
            'livredecaisse_entry_id' => $entryId,
            'business_date' => $businessDate,
            'attachment_file_name' => $safeName,
            'attachment_mime' => $mimeType,
            'attachment_size' => $fileSize,
            'attachment_blob' => $blob,
            'created_by' => $userId !== '' ? $userId : null,
            'updated_by' => $userId !== '' ? $userId : null,
        ]);
    }

    return ldcFetchAttachments($pdo, $entryId);
}

function ldcDeleteAttachment(PDO $pdo, int $attachmentId): ?array
{
    $attachment = ldcFetchAttachmentById($pdo, $attachmentId);
    if ($attachment === null) {
        return null;
    }

    $stmt = $pdo->prepare("DELETE FROM livredecaisse_attachments WHERE id = ?");
    $stmt->execute([$attachmentId]);

    return $attachment;
}

function ldcDeleteAttachmentsByEntryId(PDO $pdo, int $entryId): void
{
    if ($entryId <= 0) {
        return;
    }

    $stmt = $pdo->prepare(
        "DELETE FROM livredecaisse_attachments
         WHERE livredecaisse_entry_id = ?"
    );
    $stmt->execute([$entryId]);
}

function ldcStreamAttachment(array $attachment, bool $forceDownload = false): never
{
    $mimeType = (string) ($attachment['attachment_mime'] ?? 'application/octet-stream');
    $fileName = (string) ($attachment['attachment_file_name'] ?? 'piece-jointe');
    $content = (string) ($attachment['attachment_blob'] ?? '');
    $disposition = $forceDownload ? 'attachment' : 'inline';

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . strlen($content));
    header("Content-Disposition: {$disposition}; filename*=UTF-8''" . rawurlencode($fileName));
    header('X-Content-Type-Options: nosniff');

    echo $content;
    exit;
}

function ldcIsDailyClosed(?array $dailyState): bool
{
    if ($dailyState === null) {
        return false;
    }

    return (int) ($dailyState['journee_cloturee'] ?? 0) === 1
        || trim((string) ($dailyState['journee_cloturee_at'] ?? '')) !== '';
}

function ldcEnsureDayIsEditable(?array $dailyState): void
{
    if (ldcIsDailyClosed($dailyState)) {
        throw new RuntimeException('Cette journée a déjà été clôturée et n’est plus modifiable.');
    }
}

function ldcHydrateEntryRow(array $row): array
{
    $defaults = [
        'id' => 0,
        'record_type' => 'entry',
        'reference_key' => null,
        'attachment_entry_id' => null,
        'business_date' => ldcToday(),
        'date_saisie' => '',
        'chrono' => null,
        'type_affaire' => '',
        'risque' => '',
        'nom_adherent' => '',
        'prenom_adherent' => '',
        'saisie_oa' => 'Non',
        'type_encaissement' => '',
        'montant' => 0.0,
        'num_cheque' => '',
        'num_contrat' => '',
        'date_reglement' => '',
        'mois_anticipation' => '',
        'reglement_avis' => 'Non',
        'avenant' => 'Non',
        'regul_impaye' => 'Non',
        'regul_mise_demeure' => 'Non',
        'num_adhesion' => '',
        'date_effet' => '',
        'formule_produit' => '',
        'mandataire' => '',
        'dsu' => '',
        'especes' => 0.0,
        'cheque' => 0.0,
        'cb' => 0.0,
        'comptant_prelever' => 0.0,
        'comptant_offert' => 0.0,
        'appel_cotisation' => 0.0,
        'num_remise_especes' => '',
        'num_remise_cheque' => '',
        'attachment_file_name' => '',
        'attachment_mime' => '',
        'attachment_size' => 0,
        'attachment_blob' => null,
        'fond_caisse_debut' => null,
        'fond_caisse_confirme_at' => '',
        'fond_caisse_fin' => null,
        'bordereau_num' => null,
        'bordereau_suivant' => null,
        'depot_on' => 0,
        'depot_espece' => 0,
        'depot_cheque' => 0,
        'montant_remise_especes' => null,
        'montant_remise_cheque' => null,
        'journee_cloturee' => 0,
        'journee_cloturee_at' => '',
        'journee_cloturee_by' => '',
        'created_by' => '',
        'updated_by' => '',
        'created_at' => '',
        'updated_at' => '',
    ];

    $entry = array_merge($defaults, $row);

    foreach ([
        'montant',
        'especes',
        'cheque',
        'cb',
        'comptant_prelever',
        'comptant_offert',
        'appel_cotisation',
        'fond_caisse_debut',
        'fond_caisse_fin',
        'montant_remise_especes',
        'montant_remise_cheque',
    ] as $numericField) {
        $entry[$numericField] = $entry[$numericField] === null ? null : (float) $entry[$numericField];
    }

    $entry['id'] = (int) $entry['id'];
    $entry['attachment_entry_id'] = $entry['attachment_entry_id'] === null ? null : (int) $entry['attachment_entry_id'];
    $entry['attachment_size'] = (int) ($entry['attachment_size'] ?? 0);
    $entry['chrono'] = $entry['chrono'] === null ? null : (int) $entry['chrono'];
    $entry['bordereau_num'] = $entry['bordereau_num'] === null ? null : (int) $entry['bordereau_num'];
    $entry['bordereau_suivant'] = $entry['bordereau_suivant'] === null ? null : (int) $entry['bordereau_suivant'];
    $entry['depot_on'] = (int) ($entry['depot_on'] ?? 0);
    $entry['depot_espece'] = (int) ($entry['depot_espece'] ?? 0);
    $entry['depot_cheque'] = (int) ($entry['depot_cheque'] ?? 0);
    $entry['journee_cloturee'] = (int) ($entry['journee_cloturee'] ?? 0);
    $entry['date_reglement'] = ldcFormatInputDate((string) $entry['date_reglement']);
    $entry['date_effet'] = ldcFormatInputDate((string) $entry['date_effet']);
    $entry['date_saisie_display'] = ldcFormatDisplayDateTime((string) ($entry['date_saisie'] ?: $entry['created_at']));

    return $entry;
}

function ldcBuildEntryFromPost(array $post, ?array $existingEntry, int $chrono, string $businessDate): array
{
    $entry = $existingEntry ?? [];

    $lastName = trim((string) ($post['nom_adherent'] ?? ''));
    $firstName = trim((string) ($post['prenom_adherent'] ?? ''));

    $entry['business_date'] = $businessDate;
    $entry['date_saisie'] = $existingEntry['date_saisie'] ?? date('Y-m-d H:i:s');
    $entry['chrono'] = $existingEntry['chrono'] ?? $chrono;
    $entry['type_affaire'] = trim((string) ($post['type_affaire'] ?? ''));
    $entry['risque'] = trim((string) ($post['risque'] ?? ''));
    $entry['nom_adherent'] = $lastName !== '' ? mb_strtoupper($lastName, 'UTF-8') : '';
    $entry['prenom_adherent'] = $firstName !== '' ? mb_convert_case(mb_strtolower($firstName, 'UTF-8'), MB_CASE_TITLE, 'UTF-8') : '';
    $entry['saisie_oa'] = ldcNormalizeCheckboxValue(isset($post['saisie_oa']));
    $entry['type_encaissement'] = trim((string) ($post['type_encaissement'] ?? ''));
    $entry['montant'] = ldcNormalizeMoneyInput($post['montant'] ?? '0');
    $entry['num_cheque'] = trim((string) ($post['num_cheque'] ?? ''));
    $entry['num_contrat'] = trim((string) ($post['num_contrat'] ?? ''));
    $entry['date_reglement'] = ldcFormatInputDate((string) ($post['date_reglement'] ?? ldcToday()));
    $entry['mois_anticipation'] = implode(',', ldcNormalizeAnticipationMonthValues($post['mois_anticipation'] ?? ''));
    $entry['reglement_avis'] = ldcNormalizeCheckboxValue(isset($post['reglement_avis']));
    $entry['avenant'] = ldcNormalizeCheckboxValue(isset($post['avenant']));
    $entry['regul_impaye'] = ldcNormalizeCheckboxValue(isset($post['regul_impaye']));
    $entry['regul_mise_demeure'] = ldcNormalizeCheckboxValue(isset($post['regul_mise_demeure']));
    $entry['num_adhesion'] = trim((string) ($post['num_adhesion'] ?? ''));
    $entry['date_effet'] = ldcFormatInputDate((string) ($post['date_effet'] ?? ''));
    $entry['formule_produit'] = trim((string) ($post['formule_produit'] ?? ''));
    $entry['mandataire'] = trim((string) ($post['mandataire'] ?? ''));
    $entry['dsu'] = trim((string) ($post['dsu'] ?? ''));

    $entry = ldcNormalizeEntryForTypeAffaire($entry);

    return ldcApplyEncaissementVentilation($entry);
}

function ldcFetchDailyState(PDO $pdo, ?string $businessDate = null): ?array
{
    $businessDate = $businessDate ?: ldcToday();
    $stmt = $pdo->prepare(
        "SELECT * FROM livredecaisse
         WHERE record_type = 'daily_state' AND business_date = ?
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute([$businessDate]);
    $row = $stmt->fetch();

    return $row ? ldcHydrateEntryRow($row) : null;
}

function ldcFetchPreviousBusinessDate(PDO $pdo, string $businessDate): ?string
{
    $resolvedDate = ldcResolveBusinessDate($businessDate);
    $stmt = $pdo->prepare(
        "SELECT business_date
         FROM livredecaisse
         WHERE record_type IN ('entry', 'daily_state')
           AND business_date < ?
         GROUP BY business_date
         ORDER BY business_date DESC
         LIMIT 1"
    );
    $stmt->execute([$resolvedDate]);
    $value = $stmt->fetchColumn();

    if ($value === false || $value === null) {
        return null;
    }

    $previousDate = ldcResolveBusinessDate((string) $value);

    return $previousDate !== '' ? $previousDate : null;
}

function ldcNormalizeBooleanFlag($value): int
{
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'oui', 'on'], true) ? 1 : 0;
}

function ldcGetBordereauNumber(PDO $pdo, ?string $businessDate = null): int
{
    return ldcGetBordereauNumberRecursive($pdo, ldcResolveBusinessDate($businessDate), []);
}

function ldcGetBordereauNumberRecursive(PDO $pdo, string $businessDate, array $visitedDates): int
{
    static $cache = [];

    if ($visitedDates === [] && array_key_exists($businessDate, $cache)) {
        return $cache[$businessDate];
    }

    if (in_array($businessDate, $visitedDates, true)) {
        return LDC_BDX_NUM;
    }

    $state = ldcFetchDailyState($pdo, $businessDate);
    if ($state !== null && $state['bordereau_num'] !== null) {
        $bordereauNum = (int) $state['bordereau_num'];
        if ($visitedDates === []) {
            $cache[$businessDate] = $bordereauNum;
        }

        return $bordereauNum;
    }

    $previousBusinessDate = ldcFetchPreviousBusinessDate($pdo, $businessDate);
    if ($previousBusinessDate === null) {
        if ($visitedDates === []) {
            $cache[$businessDate] = LDC_BDX_NUM;
        }

        return LDC_BDX_NUM;
    }

    $previousState = ldcFetchDailyState($pdo, $previousBusinessDate);
    if ($previousState !== null && $previousState['bordereau_suivant'] !== null) {
        $bordereauNum = (int) $previousState['bordereau_suivant'];
        if ($visitedDates === []) {
            $cache[$businessDate] = $bordereauNum;
        }

        return $bordereauNum;
    }

    $previousBordereau = ldcGetBordereauNumberRecursive(
        $pdo,
        $previousBusinessDate,
        array_merge($visitedDates, [$businessDate])
    );

    $bordereauNum = $previousBordereau;
    if ($previousState !== null && (int) ($previousState['depot_on'] ?? 0) === 1) {
        $bordereauNum++;
    }

    if ($visitedDates === []) {
        $cache[$businessDate] = $bordereauNum;
    }

    return $bordereauNum;
}

function ldcUpsertDailyState(PDO $pdo, float $fondCaisseDebut, string $businessDate, string $userId = ''): array
{
    $agenceSnapshot = ldcCurrentAgenceSnapshot();
    $stmt = $pdo->prepare(
        "INSERT INTO livredecaisse (
            record_type,
            reference_key,
            business_date,
            departement,
            agence,
            fond_caisse_debut,
            fond_caisse_confirme_at,
            num_remise_especes,
            num_remise_cheque,
            created_by,
            updated_by
        ) VALUES (
            'daily_state',
            'daily_state',
            :business_date,
            :departement,
            :agence,
            :fond_caisse_debut,
            NOW(),
            :num_remise_especes,
            :num_remise_cheque,
            :created_by,
            :updated_by
        )
        ON DUPLICATE KEY UPDATE
            departement = VALUES(departement),
            agence = VALUES(agence),
            fond_caisse_debut = VALUES(fond_caisse_debut),
            fond_caisse_confirme_at = VALUES(fond_caisse_confirme_at),
            num_remise_especes = VALUES(num_remise_especes),
            num_remise_cheque = VALUES(num_remise_cheque),
            updated_by = VALUES(updated_by),
            updated_at = CURRENT_TIMESTAMP"
    );

    $currentState = ldcFetchDailyState($pdo, $businessDate);

    $stmt->execute([
        'business_date' => $businessDate,
        'departement' => (string) ($agenceSnapshot['departement'] ?? ''),
        'agence' => (string) ($agenceSnapshot['agence'] ?? ''),
        'fond_caisse_debut' => $fondCaisseDebut,
        'num_remise_especes' => $currentState['num_remise_especes'] ?? '',
        'num_remise_cheque' => $currentState['num_remise_cheque'] ?? '',
        'created_by' => $userId !== '' ? $userId : null,
        'updated_by' => $userId !== '' ? $userId : null,
    ]);

    return ldcFetchDailyState($pdo, $businessDate) ?? [];
}

function ldcUpsertDailyRemises(PDO $pdo, string $businessDate, string $numRemiseEspeces, string $numRemiseCheque, string $userId = ''): array
{
    $agenceSnapshot = ldcCurrentAgenceSnapshot();
    $stmt = $pdo->prepare(
        "INSERT INTO livredecaisse (
            record_type,
            reference_key,
            business_date,
            departement,
            agence,
            num_remise_especes,
            num_remise_cheque,
            created_by,
            updated_by
        ) VALUES (
            'daily_state',
            'daily_state',
            :business_date,
            :departement,
            :agence,
            :num_remise_especes,
            :num_remise_cheque,
            :created_by,
            :updated_by
        )
        ON DUPLICATE KEY UPDATE
            departement = VALUES(departement),
            agence = VALUES(agence),
            num_remise_especes = VALUES(num_remise_especes),
            num_remise_cheque = VALUES(num_remise_cheque),
            updated_by = VALUES(updated_by),
            updated_at = CURRENT_TIMESTAMP"
    );

    $stmt->execute([
        'business_date' => $businessDate,
        'departement' => (string) ($agenceSnapshot['departement'] ?? ''),
        'agence' => (string) ($agenceSnapshot['agence'] ?? ''),
        'num_remise_especes' => trim($numRemiseEspeces),
        'num_remise_cheque' => trim($numRemiseCheque),
        'created_by' => $userId !== '' ? $userId : null,
        'updated_by' => $userId !== '' ? $userId : null,
    ]);

    return ldcFetchDailyState($pdo, $businessDate) ?? [];
}

function ldcCloseDailyState(PDO $pdo, string $businessDate, float $fondCaisseDebut, string $userId = '', array $options = []): array
{
    $resolvedDate = ldcResolveBusinessDate($businessDate);
    $currentState = ldcFetchDailyState($pdo, $resolvedDate);
    $agenceSnapshot = ldcCurrentAgenceSnapshot();
    $depotOn = ldcNormalizeBooleanFlag($options['depot_on'] ?? 0);
    $depotEspece = $depotOn === 1 ? ldcNormalizeBooleanFlag($options['depot_espece'] ?? 0) : 0;
    $depotCheque = $depotOn === 1 ? ldcNormalizeBooleanFlag($options['depot_cheque'] ?? 0) : 0;
    $bordereauNum = isset($options['bordereau_num']) ? (int) $options['bordereau_num'] : ldcGetBordereauNumber($pdo, $resolvedDate);
    $bordereauSuivant = $bordereauNum + ($depotOn === 1 ? 1 : 0);
    $fondCaisseFin = array_key_exists('fond_caisse_fin', $options) ? (float) $options['fond_caisse_fin'] : null;
    $montantRemiseEspeces = $depotEspece === 1 && array_key_exists('montant_remise_especes', $options)
        ? (float) $options['montant_remise_especes']
        : null;
    $montantRemiseCheque = $depotCheque === 1 && array_key_exists('montant_remise_cheque', $options)
        ? (float) $options['montant_remise_cheque']
        : null;
    $numRemiseEspeces = trim((string) ($options['num_remise_especes'] ?? ($currentState['num_remise_especes'] ?? '')));
    $numRemiseCheque = trim((string) ($options['num_remise_cheque'] ?? ($currentState['num_remise_cheque'] ?? '')));
    $stmt = $pdo->prepare(
        "INSERT INTO livredecaisse (
            record_type,
            reference_key,
            business_date,
            departement,
            agence,
            fond_caisse_debut,
            fond_caisse_confirme_at,
            fond_caisse_fin,
            bordereau_num,
            bordereau_suivant,
            num_remise_especes,
            num_remise_cheque,
            depot_on,
            depot_espece,
            depot_cheque,
            montant_remise_especes,
            montant_remise_cheque,
            journee_cloturee,
            journee_cloturee_at,
            journee_cloturee_by,
            created_by,
            updated_by
        ) VALUES (
            'daily_state',
            'daily_state',
            :business_date,
            :departement,
            :agence,
            :fond_caisse_debut,
            NOW(),
            :fond_caisse_fin,
            :bordereau_num,
            :bordereau_suivant,
            :num_remise_especes,
            :num_remise_cheque,
            :depot_on,
            :depot_espece,
            :depot_cheque,
            :montant_remise_especes,
            :montant_remise_cheque,
            1,
            NOW(),
            :journee_cloturee_by,
            :created_by,
            :updated_by
        )
        ON DUPLICATE KEY UPDATE
            departement = VALUES(departement),
            agence = VALUES(agence),
            fond_caisse_debut = VALUES(fond_caisse_debut),
            fond_caisse_confirme_at = VALUES(fond_caisse_confirme_at),
            fond_caisse_fin = VALUES(fond_caisse_fin),
            bordereau_num = VALUES(bordereau_num),
            bordereau_suivant = VALUES(bordereau_suivant),
            num_remise_especes = VALUES(num_remise_especes),
            num_remise_cheque = VALUES(num_remise_cheque),
            depot_on = VALUES(depot_on),
            depot_espece = VALUES(depot_espece),
            depot_cheque = VALUES(depot_cheque),
            montant_remise_especes = VALUES(montant_remise_especes),
            montant_remise_cheque = VALUES(montant_remise_cheque),
            journee_cloturee = 1,
            journee_cloturee_at = COALESCE(journee_cloturee_at, VALUES(journee_cloturee_at)),
            journee_cloturee_by = COALESCE(journee_cloturee_by, VALUES(journee_cloturee_by)),
            updated_by = VALUES(updated_by),
            updated_at = CURRENT_TIMESTAMP"
    );

    $stmt->execute([
        'business_date' => $resolvedDate,
        'departement' => (string) ($agenceSnapshot['departement'] ?? ''),
        'agence' => (string) ($agenceSnapshot['agence'] ?? ''),
        'fond_caisse_debut' => $fondCaisseDebut,
        'fond_caisse_fin' => $fondCaisseFin,
        'bordereau_num' => $bordereauNum,
        'bordereau_suivant' => $bordereauSuivant,
        'num_remise_especes' => $numRemiseEspeces,
        'num_remise_cheque' => $numRemiseCheque,
        'depot_on' => $depotOn,
        'depot_espece' => $depotEspece,
        'depot_cheque' => $depotCheque,
        'montant_remise_especes' => $montantRemiseEspeces,
        'montant_remise_cheque' => $montantRemiseCheque,
        'journee_cloturee_by' => $userId !== '' ? $userId : null,
        'created_by' => $currentState === null && $userId !== '' ? $userId : null,
        'updated_by' => $userId !== '' ? $userId : null,
    ]);

    return ldcFetchDailyState($pdo, $resolvedDate) ?? [];
}

function ldcGetFondCaisseDebutJournee(PDO $pdo, ?string $businessDate = null): float
{
    return ldcGetFondCaisseDebutJourneeRecursive($pdo, ldcResolveBusinessDate($businessDate), []);
}

function ldcGetFondCaisseDebutJourneeRecursive(PDO $pdo, string $businessDate, array $visitedDates): float
{
    static $cache = [];

    if ($visitedDates === [] && array_key_exists($businessDate, $cache)) {
        return $cache[$businessDate];
    }

    if (in_array($businessDate, $visitedDates, true)) {
        return LDC_FOND_CAISSE_DEBUT;
    }

    $state = ldcFetchDailyState($pdo, $businessDate);
    if ($state !== null && $state['fond_caisse_debut'] !== null) {
        $fondDebut = (float) $state['fond_caisse_debut'];
        if ($visitedDates === []) {
            $cache[$businessDate] = $fondDebut;
        }

        return $fondDebut;
    }

    $previousBusinessDate = ldcFetchPreviousBusinessDate($pdo, $businessDate);
    if ($previousBusinessDate === null) {
        if ($visitedDates === []) {
            $cache[$businessDate] = LDC_FOND_CAISSE_DEBUT;
        }

        return LDC_FOND_CAISSE_DEBUT;
    }

    $fondDebut = ldcGetFondCaisseFinJourneeRecursive(
        $pdo,
        $previousBusinessDate,
        array_merge($visitedDates, [$businessDate])
    );

    if ($visitedDates === []) {
        $cache[$businessDate] = $fondDebut;
    }

    return $fondDebut;
}

function ldcGetFondCaisseFinJournee(PDO $pdo, ?string $businessDate = null): float
{
    return ldcGetFondCaisseFinJourneeRecursive($pdo, ldcResolveBusinessDate($businessDate), []);
}

function ldcGetFondCaisseFinJourneeRecursive(PDO $pdo, string $businessDate, array $visitedDates): float
{
    static $cache = [];

    if ($visitedDates === [] && array_key_exists($businessDate, $cache)) {
        return $cache[$businessDate];
    }

    if (in_array($businessDate, $visitedDates, true)) {
        return LDC_FOND_CAISSE_DEBUT;
    }

    $fondDebut = ldcGetFondCaisseDebutJourneeRecursive(
        $pdo,
        $businessDate,
        $visitedDates
    );
    $entries = ldcFetchEntries($pdo, $businessDate);
    $totaux = ldcGetTotaux($entries);
    $fondFin = $fondDebut + (float) ($totaux['total'] ?? 0.0);

    if ($visitedDates === []) {
        $cache[$businessDate] = $fondFin;
    }

    return $fondFin;
}

function ldcFetchEntries(PDO $pdo, ?string $businessDate = null): array
{
    $businessDate = $businessDate ?: ldcToday();
    $stmt = $pdo->prepare(
        "SELECT * FROM livredecaisse
         WHERE record_type = 'entry' AND business_date = ?
         ORDER BY chrono DESC, id DESC"
    );
    $stmt->execute([$businessDate]);

    $rows = $stmt->fetchAll() ?: [];

    return array_map('ldcHydrateEntryRow', $rows);
}

function ldcFetchEntryById(PDO $pdo, int $entryId, ?string $businessDate = null): ?array
{
    $businessDate = $businessDate ?: ldcToday();
    $stmt = $pdo->prepare(
        "SELECT * FROM livredecaisse
         WHERE id = ? AND record_type = 'entry' AND business_date = ?
         LIMIT 1"
    );
    $stmt->execute([$entryId, $businessDate]);
    $row = $stmt->fetch();

    return $row ? ldcHydrateEntryRow($row) : null;
}

function ldcFetchDailyBooks(PDO $pdo): array
{
    $booksByDate = [];
    $attachmentCountRows = $pdo->query(
        "SELECT business_date, COUNT(*) AS attachment_count
         FROM livredecaisse_attachments
         GROUP BY business_date"
    )->fetchAll() ?: [];
    $attachmentCountsByDate = [];

    foreach ($attachmentCountRows as $row) {
        $attachmentCountsByDate[ldcResolveBusinessDate((string) ($row['business_date'] ?? ''))] = (int) ($row['attachment_count'] ?? 0);
    }

    $stateRows = $pdo->query(
        "SELECT * FROM livredecaisse
         WHERE record_type = 'daily_state'
         ORDER BY business_date DESC, id DESC"
    )->fetchAll() ?: [];

    foreach ($stateRows as $row) {
        $state = ldcHydrateEntryRow($row);
        $businessDate = ldcResolveBusinessDate((string) ($state['business_date'] ?? ''));

        if (!isset($booksByDate[$businessDate])) {
            $booksByDate[$businessDate] = [
                'business_date' => $businessDate,
                'daily_state' => $state,
                'entries' => [],
            ];
            continue;
        }

        if ($booksByDate[$businessDate]['daily_state'] === null) {
            $booksByDate[$businessDate]['daily_state'] = $state;
        }
    }

    $entryRows = $pdo->query(
        "SELECT * FROM livredecaisse
         WHERE record_type = 'entry'
         ORDER BY business_date DESC, chrono DESC, id DESC"
    )->fetchAll() ?: [];

    foreach ($entryRows as $row) {
        $entry = ldcHydrateEntryRow($row);
        $businessDate = ldcResolveBusinessDate((string) ($entry['business_date'] ?? ''));

        if (!isset($booksByDate[$businessDate])) {
            $booksByDate[$businessDate] = [
                'business_date' => $businessDate,
                'daily_state' => null,
                'entries' => [],
            ];
        }

        $booksByDate[$businessDate]['entries'][] = $entry;
    }

    krsort($booksByDate, SORT_STRING);

    $books = [];
    foreach ($booksByDate as $businessDate => $book) {
        $dailyState = $book['daily_state'];
        $entries = $book['entries'];
        $totaux = ldcGetTotaux($entries);
        $fondDebut = ldcGetFondCaisseDebutJournee($pdo, $businessDate);

        $books[] = [
            'business_date' => $businessDate,
            'daily_state' => $dailyState,
            'entries' => $entries,
            'totaux' => $totaux,
            'fond_debut' => $fondDebut,
            'fond_fin' => ldcGetFondCaisseFinJournee($pdo, $businessDate),
            'is_closed' => ldcIsDailyClosed($dailyState),
            'closed_at' => (string) ($dailyState['journee_cloturee_at'] ?? ''),
            'closed_by' => (string) ($dailyState['journee_cloturee_by'] ?? ''),
            'bordereau_num' => $dailyState !== null && $dailyState['bordereau_num'] !== null
                ? (int) $dailyState['bordereau_num']
                : ldcGetBordereauNumber($pdo, $businessDate),
            'bordereau_suivant' => $dailyState !== null && $dailyState['bordereau_suivant'] !== null
                ? (int) $dailyState['bordereau_suivant']
                : ldcGetBordereauNumber($pdo, $businessDate),
            'depot_on' => (int) ($dailyState['depot_on'] ?? 0),
            'depot_espece' => (int) ($dailyState['depot_espece'] ?? 0),
            'depot_cheque' => (int) ($dailyState['depot_cheque'] ?? 0),
            'montant_remise_especes' => $dailyState['montant_remise_especes'] ?? null,
            'montant_remise_cheque' => $dailyState['montant_remise_cheque'] ?? null,
            'entry_count' => count($entries),
            'attachment_count' => (int) ($attachmentCountsByDate[$businessDate] ?? 0),
            'num_remise_especes' => trim((string) ($dailyState['num_remise_especes'] ?? '')),
            'num_remise_cheque' => trim((string) ($dailyState['num_remise_cheque'] ?? '')),
        ];
    }

    return $books;
}

function ldcGetNextChrono(PDO $pdo): int
{
    $value = $pdo->query("SELECT MAX(chrono) FROM livredecaisse WHERE record_type = 'entry'")->fetchColumn();
    $maxChrono = $value !== false && $value !== null ? (int) $value : null;

    if ($maxChrono === null || $maxChrono < LDC_CHRONO_INIT) {
        return LDC_CHRONO_INIT;
    }

    return $maxChrono + 1;
}

function ldcPersistEntry(PDO $pdo, array $entry, string $userId = '', ?int $entryId = null): int
{
    $agenceSnapshot = ldcCurrentAgenceSnapshot();
    $basePayload = [
        'business_date' => $entry['business_date'],
        'departement' => (string) ($agenceSnapshot['departement'] ?? ''),
        'agence' => (string) ($agenceSnapshot['agence'] ?? ''),
        'date_saisie' => $entry['date_saisie'],
        'chrono' => $entry['chrono'],
        'type_affaire' => $entry['type_affaire'],
        'risque' => $entry['risque'],
        'nom_adherent' => $entry['nom_adherent'],
        'prenom_adherent' => $entry['prenom_adherent'],
        'saisie_oa' => $entry['saisie_oa'],
        'type_encaissement' => $entry['type_encaissement'],
        'montant' => $entry['montant'],
        'num_cheque' => $entry['num_cheque'],
        'num_contrat' => $entry['num_contrat'],
        'date_reglement' => $entry['date_reglement'] !== '' ? $entry['date_reglement'] : null,
        'mois_anticipation' => $entry['mois_anticipation'],
        'reglement_avis' => $entry['reglement_avis'],
        'avenant' => $entry['avenant'],
        'regul_impaye' => $entry['regul_impaye'],
        'regul_mise_demeure' => $entry['regul_mise_demeure'],
        'num_adhesion' => $entry['num_adhesion'],
        'date_effet' => $entry['date_effet'] !== '' ? $entry['date_effet'] : null,
        'formule_produit' => $entry['formule_produit'],
        'mandataire' => $entry['mandataire'],
        'dsu' => $entry['dsu'],
        'especes' => $entry['especes'],
        'cheque' => $entry['cheque'],
        'cb' => $entry['cb'],
        'comptant_prelever' => $entry['comptant_prelever'],
        'comptant_offert' => $entry['comptant_offert'],
        'appel_cotisation' => $entry['appel_cotisation'],
        'updated_by' => $userId !== '' ? $userId : null,
    ];

    if ($entryId === null) {
        $payload = $basePayload + [
            'record_type' => 'entry',
            'reference_key' => null,
        ];
        $stmt = $pdo->prepare(
            "INSERT INTO livredecaisse (
                record_type,
                reference_key,
                business_date,
                departement,
                agence,
                date_saisie,
                chrono,
                type_affaire,
                risque,
                nom_adherent,
                prenom_adherent,
                saisie_oa,
                type_encaissement,
                montant,
                num_cheque,
                num_contrat,
                date_reglement,
                mois_anticipation,
                reglement_avis,
                avenant,
                regul_impaye,
                regul_mise_demeure,
                num_adhesion,
                date_effet,
                formule_produit,
                mandataire,
                dsu,
                especes,
                cheque,
                cb,
                comptant_prelever,
                comptant_offert,
                appel_cotisation,
                created_by,
                updated_by
            ) VALUES (
                :record_type,
                :reference_key,
                :business_date,
                :departement,
                :agence,
                :date_saisie,
                :chrono,
                :type_affaire,
                :risque,
                :nom_adherent,
                :prenom_adherent,
                :saisie_oa,
                :type_encaissement,
                :montant,
                :num_cheque,
                :num_contrat,
                :date_reglement,
                :mois_anticipation,
                :reglement_avis,
                :avenant,
                :regul_impaye,
                :regul_mise_demeure,
                :num_adhesion,
                :date_effet,
                :formule_produit,
                :mandataire,
                :dsu,
                :especes,
                :cheque,
                :cb,
                :comptant_prelever,
                :comptant_offert,
                :appel_cotisation,
                :created_by,
                :updated_by
            )"
        );
        $payload['created_by'] = $userId !== '' ? $userId : null;
        $stmt->execute($payload);

        return (int) $pdo->lastInsertId();
    }

    $payload = $basePayload;
    $payload['id'] = $entryId;
    $stmt = $pdo->prepare(
        "UPDATE livredecaisse SET
            business_date = :business_date,
            departement = :departement,
            agence = :agence,
            date_saisie = :date_saisie,
            chrono = :chrono,
            type_affaire = :type_affaire,
            risque = :risque,
            nom_adherent = :nom_adherent,
            prenom_adherent = :prenom_adherent,
            saisie_oa = :saisie_oa,
            type_encaissement = :type_encaissement,
            montant = :montant,
            num_cheque = :num_cheque,
            num_contrat = :num_contrat,
            date_reglement = :date_reglement,
            mois_anticipation = :mois_anticipation,
            reglement_avis = :reglement_avis,
            avenant = :avenant,
            regul_impaye = :regul_impaye,
            regul_mise_demeure = :regul_mise_demeure,
            num_adhesion = :num_adhesion,
            date_effet = :date_effet,
            formule_produit = :formule_produit,
            mandataire = :mandataire,
            dsu = :dsu,
            especes = :especes,
            cheque = :cheque,
            cb = :cb,
            comptant_prelever = :comptant_prelever,
            comptant_offert = :comptant_offert,
            appel_cotisation = :appel_cotisation,
            updated_by = :updated_by,
            updated_at = CURRENT_TIMESTAMP
         WHERE id = :id AND record_type = 'entry'"
    );
    $stmt->execute($payload);

    return $entryId;
}

function ldcDeleteEntry(PDO $pdo, int $entryId, ?string $businessDate = null): ?array
{
    $entry = ldcFetchEntryById($pdo, $entryId, $businessDate);
    if ($entry === null) {
        return null;
    }

    ldcDeleteAttachmentsByEntryId($pdo, $entryId);

    $stmt = $pdo->prepare("DELETE FROM livredecaisse WHERE id = ? AND record_type = 'entry'");
    $stmt->execute([$entryId]);

    return $entry;
}

function ldcGetTotaux(array $entries): array
{
    $totaux = [
        'especes' => 0.0,
        'cheques' => 0.0,
        'cb' => 0.0,
        'comptant_prelever' => 0.0,
        'comptant_offert' => 0.0,
        'appel_cotisation' => 0.0,
        'nb_especes' => 0,
        'nb_cheques' => 0,
        'nb_cb' => 0,
        'nb_comptant_prelever' => 0,
        'nb_comptant_offert' => 0,
        'nb_appel_cotisation' => 0,
    ];

    foreach ($entries as $entry) {
        switch ($entry['type_encaissement'] ?? '') {
            case 'Espèces':
                $totaux['especes'] += (float) ($entry['especes'] ?? 0);
                $totaux['nb_especes']++;
                break;
            case 'Chèque':
                $totaux['cheques'] += (float) ($entry['cheque'] ?? 0);
                $totaux['nb_cheques']++;
                break;
            case 'CB':
                $totaux['cb'] += (float) ($entry['cb'] ?? 0);
                $totaux['nb_cb']++;
                break;
            case 'Comptant à Prélever':
                $totaux['comptant_prelever'] += (float) ($entry['comptant_prelever'] ?? 0);
                $totaux['nb_comptant_prelever']++;
                break;
            case 'Comptant Offert':
                $totaux['comptant_offert'] += (float) ($entry['comptant_offert'] ?? 0);
                $totaux['nb_comptant_offert']++;
                break;
            case 'Appel de Cotisation':
                $totaux['appel_cotisation'] += (float) ($entry['appel_cotisation'] ?? 0);
                $totaux['nb_appel_cotisation']++;
                break;
        }
    }

    $totaux['total'] =
        $totaux['especes'] +
        $totaux['cheques'] +
        $totaux['cb'] +
        $totaux['comptant_prelever'] +
        $totaux['comptant_offert'] +
        $totaux['appel_cotisation'];

    $totaux['nb_total'] =
        $totaux['nb_especes'] +
        $totaux['nb_cheques'] +
        $totaux['nb_cb'] +
        $totaux['nb_comptant_prelever'] +
        $totaux['nb_comptant_offert'] +
        $totaux['nb_appel_cotisation'];

    return $totaux;
}

function ldcFormatEntryValueForTable(array $entry, string $field): string
{
    $value = $entry[$field] ?? '';
    $booleanFields = ['saisie_oa', 'reglement_avis', 'avenant', 'regul_impaye', 'regul_mise_demeure'];
    $currencyFields = ['montant', 'comptant_prelever', 'comptant_offert', 'appel_cotisation'];

    if ($field === 'date_reglement' || $field === 'date_effet') {
        return ldcFormatDisplayDate((string) $value);
    }

    if ($field === 'mois_anticipation') {
        return ldcFormatAnticipationSummary($value);
    }

    if (in_array($field, $booleanFields, true)) {
        return $value === 'Oui' ? 'Oui' : '-';
    }

    if (in_array($field, $currencyFields, true)) {
        if ($value === '' || $value === null || abs((float) $value) < 0.00001) {
            return '-';
        }

        return ldcFormatEuro((float) $value);
    }

    $stringValue = trim((string) $value);

    return $stringValue !== '' ? $stringValue : '-';
}
