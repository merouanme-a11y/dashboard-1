<?php

declare(strict_types=1);

require_once __DIR__ . '/runtime.php';
require_once __DIR__ . '/database.php';

function app_fetch_projects(): array
{
    return app_fetch_projects_from_database(true);
}

function app_fetch_project_by_id(string $projectId): ?array
{
    app_ensure_projects_schema();

    $normalizedId = trim($projectId);
    if ($normalizedId === '') {
        return null;
    }

    $statement = app_db()->prepare(
        'SELECT id, ref, title, service, parentProjectId, projectType, description, color, customColor, start, duration, lane, startExact, endExact, riskGain, budgetEstimate, prioritization, status, progression, youtrackId, youtrackUrl, ownerId, ownerDisplayName, ownerEmail, teamMembers, taskColumns
         FROM projets
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $normalizedId]);

    $row = $statement->fetch();
    if (!is_array($row)) {
        return null;
    }

    return app_normalize_project_record($row);
}

function app_fetch_project_by_ref(string $projectRef): ?array
{
    app_ensure_projects_schema();

    $normalizedRef = trim($projectRef);
    if ($normalizedRef === '') {
        return null;
    }

    $statement = app_db()->prepare(
        'SELECT id, ref, title, service, parentProjectId, projectType, description, color, customColor, start, duration, lane, startExact, endExact, riskGain, budgetEstimate, prioritization, status, progression, youtrackId, youtrackUrl, ownerId, ownerDisplayName, ownerEmail, teamMembers, taskColumns
         FROM projets
         WHERE ref = :ref
         LIMIT 1'
    );
    $statement->execute(['ref' => $normalizedRef]);

    $row = $statement->fetch();
    if (!is_array($row)) {
        return null;
    }

    return app_normalize_project_record($row);
}

function app_fetch_project_by_youtrack_id(string $youtrackId): ?array
{
    app_ensure_projects_schema();

    $normalizedYoutrackId = trim($youtrackId);
    if ($normalizedYoutrackId === '') {
        return null;
    }

    $statement = app_db()->prepare(
        'SELECT id, ref, title, service, parentProjectId, projectType, description, color, customColor, start, duration, lane, startExact, endExact, riskGain, budgetEstimate, prioritization, status, progression, youtrackId, youtrackUrl, ownerId, ownerDisplayName, ownerEmail, teamMembers, taskColumns
         FROM projets
         WHERE youtrackId = :youtrackId
         LIMIT 1'
    );
    $statement->execute(['youtrackId' => $normalizedYoutrackId]);

    $row = $statement->fetch();
    if (!is_array($row)) {
        return null;
    }

    return app_normalize_project_record($row);
}

function app_fetch_projects_from_database(bool $seedIfEmpty): array
{
    app_ensure_projects_schema();

    $pdo = app_db();
    $projectCount = (int) $pdo->query('SELECT COUNT(*) FROM projets')->fetchColumn();

    if ($seedIfEmpty && $projectCount === 0) {
        $seedProjects = app_read_json_file(app_projects_file());
        if (!empty($seedProjects)) {
            app_store_projects($seedProjects);
        }
    }

    $statement = $pdo->query(
        'SELECT id, ref, title, service, parentProjectId, projectType, description, color, customColor, start, duration, lane, startExact, endExact, riskGain, budgetEstimate, prioritization, status, progression, youtrackId, youtrackUrl, ownerId, ownerDisplayName, ownerEmail, teamMembers, taskColumns
         FROM projets
         ORDER BY ref ASC, id ASC'
    );

    $projects = [];
    foreach ($statement->fetchAll() as $row) {
        $projects[] = app_normalize_project_record($row);
    }

    app_sync_project_service_links($projects);

    return $projects;
}

function app_store_projects(array $projects): array
{
    app_ensure_projects_schema();

    $pdo = app_db();
    $normalizedProjects = [];

    foreach (array_values($projects) as $project) {
        if (!is_array($project)) {
            continue;
        }

        $normalizedProjects[] = app_normalize_project_record($project);
    }

    app_validate_project_relationships($normalizedProjects);

    $statement = $pdo->prepare(
        'INSERT INTO projets (
            id, ref, title, service, parentProjectId, projectType, description, color, customColor, start, duration, lane, startExact, endExact, riskGain, budgetEstimate, prioritization, status, progression, youtrackId, youtrackUrl, ownerId, ownerDisplayName, ownerEmail, teamMembers, taskColumns
        ) VALUES (
            :id, :ref, :title, :service, :parentProjectId, :projectType, :description, :color, :customColor, :start, :duration, :lane, :startExact, :endExact, :riskGain, :budgetEstimate, :prioritization, :status, :progression, :youtrackId, :youtrackUrl, :ownerId, :ownerDisplayName, :ownerEmail, :teamMembers, :taskColumns
        )
        ON DUPLICATE KEY UPDATE
            ref = VALUES(ref),
            title = VALUES(title),
            service = VALUES(service),
            parentProjectId = VALUES(parentProjectId),
            projectType = VALUES(projectType),
            description = VALUES(description),
            color = VALUES(color),
            customColor = VALUES(customColor),
            start = VALUES(start),
            duration = VALUES(duration),
            lane = VALUES(lane),
            startExact = VALUES(startExact),
            endExact = VALUES(endExact),
            riskGain = VALUES(riskGain),
            budgetEstimate = VALUES(budgetEstimate),
            prioritization = VALUES(prioritization),
            status = VALUES(status),
            progression = VALUES(progression),
            youtrackId = VALUES(youtrackId),
            youtrackUrl = VALUES(youtrackUrl),
            ownerId = VALUES(ownerId),
            ownerDisplayName = VALUES(ownerDisplayName),
            ownerEmail = VALUES(ownerEmail),
            teamMembers = VALUES(teamMembers),
            taskColumns = VALUES(taskColumns),
            updated_at = CURRENT_TIMESTAMP'
    );

    $pdo->beginTransaction();
    try {
        foreach ($normalizedProjects as $project) {
            $statement->execute([
                'id' => $project['id'],
                'ref' => $project['ref'],
                'title' => $project['title'],
                'service' => $project['service'],
                'parentProjectId' => $project['parentProjectId'],
                'projectType' => $project['projectType'],
                'description' => $project['description'],
                'color' => $project['color'] !== '' ? $project['color'] : null,
                'customColor' => $project['customColor'] !== '' ? $project['customColor'] : null,
                'start' => $project['start'],
                'duration' => $project['duration'],
                'lane' => $project['lane'],
                'startExact' => $project['startExact'],
                'endExact' => $project['endExact'],
                'riskGain' => $project['riskGain'],
                'budgetEstimate' => $project['budgetEstimate'],
                'prioritization' => $project['prioritization'],
                'status' => $project['status'],
                'progression' => $project['progression'],
                'youtrackId' => $project['youtrackId'],
                'youtrackUrl' => $project['youtrackUrl'],
                'ownerId' => $project['ownerId'],
                'ownerDisplayName' => $project['ownerDisplayName'],
                'ownerEmail' => $project['ownerEmail'],
                'teamMembers' => json_encode($project['teamMembers'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'taskColumns' => json_encode($project['taskColumns'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }

        $pdo->commit();
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $throwable;
    }

    $storedProjects = app_fetch_projects_from_database(false);
    app_sync_services_from_projects($storedProjects);
    app_sync_project_service_links($storedProjects);
    app_write_projects_json_mirror($storedProjects);

    return $normalizedProjects;
}

function app_create_project(array $project): array
{
    app_ensure_projects_schema();

    $title = trim((string) ($project['title'] ?? ''));
    $ref = trim((string) ($project['ref'] ?? ''));
    $service = trim((string) ($project['service'] ?? ''));

    if ($title === '') {
        throw new InvalidArgumentException('Le titre du projet est obligatoire.');
    }

    if ($ref === '') {
        throw new InvalidArgumentException('L\'identifiant du projet est obligatoire.');
    }

    if ($service === '') {
        throw new InvalidArgumentException('Le service du projet est obligatoire.');
    }

    if (app_project_ref_exists($ref)) {
        throw new DomainException('Un projet avec cet identifiant existe déjà.');
    }

    $projectId = trim((string) ($project['id'] ?? ''));
    if ($projectId === '') {
        $project['id'] = app_generate_project_id();
    }

    $storedProjects = app_store_projects([$project]);
    if (!empty($storedProjects[0]) && is_array($storedProjects[0])) {
        return $storedProjects[0];
    }

    throw new RuntimeException('Impossible de créer le projet.');
}

function app_delete_project(string $projectId): void
{
    app_ensure_projects_schema();

    $normalizedId = trim($projectId);
    if ($normalizedId === '') {
        throw new RuntimeException('Identifiant projet manquant.');
    }

    $detachChildrenStatement = app_db()->prepare(
        'UPDATE projets
         SET parentProjectId = NULL
         WHERE parentProjectId = :id'
    );
    $detachChildrenStatement->execute(['id' => $normalizedId]);

    $statement = app_db()->prepare('DELETE FROM projets WHERE id = :id');
    $statement->execute(['id' => $normalizedId]);

    if ($statement->rowCount() < 1) {
        throw new RuntimeException('Projet introuvable.');
    }

    $remainingProjects = app_fetch_projects_from_database(false);
    app_write_projects_json_mirror($remainingProjects);
    app_sync_services_from_projects($remainingProjects);
    app_sync_project_service_links($remainingProjects);
}

function app_ensure_projects_schema(): void
{
    static $isReady = false;

    if ($isReady) {
        return;
    }

    $pdo = app_db();
    app_merge_legacy_gantt_services_into_dashboard();

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS projets (
            id VARCHAR(32) NOT NULL,
            ref VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            service VARCHAR(255) NOT NULL,
            parentProjectId VARCHAR(32) DEFAULT NULL,
            projectType VARCHAR(64) DEFAULT NULL,
            description TEXT NOT NULL,
            color VARCHAR(7) DEFAULT NULL,
            customColor VARCHAR(7) DEFAULT NULL,
            start DATE DEFAULT NULL,
            duration INT DEFAULT NULL,
            lane INT DEFAULT NULL,
            startExact DATE DEFAULT NULL,
            endExact DATE DEFAULT NULL,
            riskGain TEXT DEFAULT NULL,
            budgetEstimate TEXT DEFAULT NULL,
            prioritization TEXT DEFAULT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'A planifier',
            progression TINYINT UNSIGNED NOT NULL DEFAULT 0,
            youtrackId VARCHAR(64) DEFAULT NULL,
            youtrackUrl VARCHAR(255) DEFAULT NULL,
            ownerId VARCHAR(64) DEFAULT NULL,
            ownerDisplayName VARCHAR(255) DEFAULT NULL,
            ownerEmail VARCHAR(255) DEFAULT NULL,
            teamMembers LONGTEXT DEFAULT NULL,
            taskColumns LONGTEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_projets_ref (ref),
            KEY idx_projets_title (title),
            KEY idx_projets_service (service),
            KEY idx_projets_parent_project (parentProjectId),
            KEY idx_projets_project_type (projectType),
            KEY idx_projets_start (start),
            KEY idx_projets_start_exact (startExact),
            KEY idx_projets_end_exact (endExact)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS projet_services (
            project_id VARCHAR(32) NOT NULL,
            service_id INT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (project_id, service_id),
            KEY idx_projet_services_service (service_id),
            CONSTRAINT fk_projet_services_project
                FOREIGN KEY (project_id) REFERENCES projets(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_projet_services_service
                FOREIGN KEY (service_id) REFERENCES services(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    if (!app_projects_column_exists('status')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'A planifier'
             AFTER prioritization"
        );
    }

    if (!app_projects_column_exists('parentProjectId')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN parentProjectId VARCHAR(32) DEFAULT NULL
             AFTER service"
        );
    }

    if (!app_projects_column_exists('projectType')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN projectType VARCHAR(64) DEFAULT NULL
             AFTER parentProjectId"
        );
    }

    if (!app_projects_column_exists('progression')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN progression TINYINT UNSIGNED NOT NULL DEFAULT 0
             AFTER status"
        );
    }

    if (!app_projects_column_exists('youtrackId')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN youtrackId VARCHAR(64) DEFAULT NULL
             AFTER progression"
        );
    }

    if (!app_projects_column_exists('youtrackUrl')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN youtrackUrl VARCHAR(255) DEFAULT NULL
             AFTER youtrackId"
        );
    }

    if (!app_projects_column_exists('teamMembers')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN teamMembers LONGTEXT DEFAULT NULL
             AFTER youtrackUrl"
        );
    }

    if (!app_projects_column_exists('ownerId')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN ownerId VARCHAR(64) DEFAULT NULL
             AFTER youtrackUrl"
        );
    }

    if (!app_projects_column_exists('ownerDisplayName')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN ownerDisplayName VARCHAR(255) DEFAULT NULL
             AFTER ownerId"
        );
    }

    if (!app_projects_column_exists('ownerEmail')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN ownerEmail VARCHAR(255) DEFAULT NULL
             AFTER ownerDisplayName"
        );
    }

    if (!app_projects_column_exists('taskColumns')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN taskColumns LONGTEXT DEFAULT NULL
             AFTER teamMembers"
        );
    }

    app_merge_duplicate_services();

    $isReady = true;
}

function app_projects_column_exists(string $columnName): bool
{
    $statement = app_db()->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'projets'
           AND COLUMN_NAME = :columnName"
    );
    $statement->execute(['columnName' => $columnName]);

    return (int) $statement->fetchColumn() > 0;
}

function app_project_ref_exists(string $ref): bool
{
    app_ensure_projects_schema();

    $normalizedRef = trim($ref);
    if ($normalizedRef === '') {
        return false;
    }

    $statement = app_db()->prepare('SELECT COUNT(*) FROM projets WHERE ref = :ref');
    $statement->execute(['ref' => $normalizedRef]);

    return (int) $statement->fetchColumn() > 0;
}

function app_project_id_exists(string $projectId): bool
{
    app_ensure_projects_schema();

    $normalizedId = trim($projectId);
    if ($normalizedId === '') {
        return false;
    }

    $statement = app_db()->prepare('SELECT COUNT(*) FROM projets WHERE id = :id');
    $statement->execute(['id' => $normalizedId]);

    return (int) $statement->fetchColumn() > 0;
}

function app_generate_project_id(): string
{
    do {
        $projectId = 'prj' . strtolower(bin2hex(random_bytes(4)));
    } while (app_project_id_exists($projectId));

    return $projectId;
}

function app_normalize_project_record(array $project): array
{
    $normalizedService = app_normalize_project_services_string(trim((string) ($project['service'] ?? '')));
    $normalizedProjectId = trim((string) ($project['id'] ?? ''));
    $ownerId = app_normalize_project_nullable_string($project['ownerId'] ?? null);
    $ownerDisplayName = app_normalize_project_nullable_string($project['ownerDisplayName'] ?? null);
    $ownerEmail = app_normalize_project_nullable_string($project['ownerEmail'] ?? null);

    $normalized = [
        'id' => $normalizedProjectId,
        'ref' => trim((string) ($project['ref'] ?? '')),
        'title' => trim((string) ($project['title'] ?? '')),
        'service' => $normalizedService,
        'parentProjectId' => app_normalize_project_parent_id($project['parentProjectId'] ?? null, $normalizedProjectId),
        'projectType' => app_normalize_project_type_value($project['projectType'] ?? null),
        'description' => (string) ($project['description'] ?? ''),
        'color' => '',
        'customColor' => '',
        'start' => app_normalize_project_date_value($project['start'] ?? null),
        'duration' => app_normalize_project_integer_value($project['duration'] ?? null),
        'lane' => app_normalize_project_integer_value($project['lane'] ?? null),
        'startExact' => app_normalize_project_date_value($project['startExact'] ?? null),
        'endExact' => app_normalize_project_date_value($project['endExact'] ?? null),
        'riskGain' => app_normalize_project_nullable_string($project['riskGain'] ?? null),
        'budgetEstimate' => app_normalize_project_nullable_string($project['budgetEstimate'] ?? null),
        'prioritization' => app_normalize_project_nullable_string($project['prioritization'] ?? null),
        'status' => app_normalize_project_status_value($project['status'] ?? null, $project),
        'progression' => app_normalize_project_progression_value($project['progression'] ?? 0),
        'youtrackId' => app_normalize_project_nullable_string($project['youtrackId'] ?? null),
        'youtrackUrl' => app_normalize_project_nullable_string($project['youtrackUrl'] ?? null),
        'ownerId' => $ownerId,
        'ownerDisplayName' => $ownerDisplayName,
        'ownerEmail' => $ownerEmail,
        'teamMembers' => app_normalize_project_team_members($project['teamMembers'] ?? []),
        'taskColumns' => app_normalize_project_task_columns($project['taskColumns'] ?? []),
    ];

    if ($normalized['id'] === '') {
        throw new RuntimeException('Chaque projet doit contenir un identifiant.');
    }

    if ($normalized['ref'] === '') {
        $normalized['ref'] = strtoupper($normalized['id']);
    }

    if ($normalized['title'] === '') {
        $normalized['title'] = $normalized['ref'];
    }

    if ($normalized['service'] === '') {
        $normalized['service'] = 'Non renseigné';
    }

    $customColor = app_normalize_project_hex_color($project['customColor'] ?? null);
    $explicitColor = app_normalize_project_hex_color($project['color'] ?? null);
    if ($customColor === '' && $explicitColor !== '') {
        $customColor = $explicitColor;
    }

    $normalized['customColor'] = $customColor;
    $normalized['color'] = $customColor;

    if ($normalized['duration'] !== null && $normalized['duration'] < 1) {
        $normalized['duration'] = null;
    }

    return $normalized;
}

function app_normalize_project_nullable_string($value): ?string
{
    if ($value === null) {
        return null;
    }

    $normalized = trim((string) $value);
    return $normalized !== '' ? $normalized : null;
}

function app_normalize_project_parent_id($value, string $projectId): ?string
{
    $normalizedParentId = app_normalize_project_nullable_string($value);
    if ($normalizedParentId === null || $normalizedParentId === $projectId) {
        return null;
    }

    return $normalizedParentId;
}

function app_normalize_project_type_value($value): ?string
{
    $normalized = app_normalize_project_nullable_string($value);
    if ($normalized === null) {
        return null;
    }

    $allowedTypes = [
        'Maintenance',
        'Evolution',
        'Projet transverse',
        'Projet non transverse',
    ];

    return in_array($normalized, $allowedTypes, true) ? $normalized : null;
}

function app_normalize_project_json_array($value): array
{
    if (is_array($value)) {
        return $value;
    }

    if (!is_string($value)) {
        return [];
    }

    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function app_normalize_project_team_members($value): array
{
    $members = [];
    foreach (app_normalize_project_json_array($value) as $member) {
        if (!is_array($member)) {
            continue;
        }

        $id = trim((string) ($member['id'] ?? ''));
        if ($id === '') {
            continue;
        }

        $displayName = trim((string) ($member['displayName'] ?? $member['name'] ?? ''));
        $members[$id] = [
            'id' => $id,
            'ringId' => app_normalize_project_nullable_string($member['ringId'] ?? $id),
            'youtrackId' => app_normalize_project_nullable_string($member['youtrackId'] ?? null),
            'displayName' => $displayName !== '' ? $displayName : $id,
            'login' => app_normalize_project_nullable_string($member['login'] ?? null),
            'email' => app_normalize_project_nullable_string($member['email'] ?? null),
            'service' => app_normalize_project_nullable_string($member['service'] ?? null),
        ];
    }

    return array_values($members);
}

function app_normalize_project_task_columns($value): array
{
    $allowedColumns = ['idReadable', 'summary', 'assignee', 'dueDate', 'state'];
    $columns = [];

    foreach (app_normalize_project_json_array($value) as $column) {
        $normalizedColumn = trim((string) $column);
        $isDynamicCustomFieldColumn = (bool) preg_match('/^cf__[A-Za-z0-9_-]+$/', $normalizedColumn);
        if ($normalizedColumn === '' || (!in_array($normalizedColumn, $allowedColumns, true) && !$isDynamicCustomFieldColumn)) {
            continue;
        }

        $columns[$normalizedColumn] = $normalizedColumn;
    }

    if ($columns === []) {
        return $allowedColumns;
    }

    return array_values($columns);
}

function app_normalize_project_date_value($value): ?string
{
    $normalized = app_normalize_project_nullable_string($value);
    if ($normalized === null) {
        return null;
    }

    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $normalized);
    if ($parsed instanceof DateTimeImmutable) {
        return $parsed->format('Y-m-d');
    }

    try {
        return (new DateTimeImmutable($normalized))->format('Y-m-d');
    } catch (Exception $exception) {
        return null;
    }
}

function app_normalize_project_integer_value($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        return null;
    }

    return (int) $value;
}

function app_normalize_project_progression_value($value): int
{
    if ($value === null || $value === '') {
        return 0;
    }

    if (!is_numeric($value)) {
        return 0;
    }

    $numericValue = (int) round(((float) $value) / 10) * 10;
    return max(0, min(100, $numericValue));
}

function app_normalize_project_status_value($value, array $project): string
{
    $normalized = trim((string) $value);
    $allowedStatuses = [
        'A planifier',
        'Planifié',
        'En cours',
        'Terminé',
        'Standby',
    ];
    $hasSchedule = !empty($project['start']) && is_numeric($project['duration'] ?? null) && (int) $project['duration'] > 0;

    if (in_array($normalized, $allowedStatuses, true)) {
        if (!$hasSchedule && in_array($normalized, ['Planifié', 'En cours'], true)) {
            return 'A planifier';
        }

        return $normalized;
    }

    return $hasSchedule ? 'Planifié' : 'A planifier';
}

function app_normalize_project_hex_color($value): string
{
    $normalized = strtolower(trim((string) $value));
    if ($normalized === '') {
        return '';
    }

    if (preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{6})$/', $normalized, $matches) !== 1) {
        return '';
    }

    $hex = strtolower($matches[1]);
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    return '#' . $hex;
}

function app_fetch_project_parent_map(): array
{
    app_ensure_projects_schema();

    $statement = app_db()->query('SELECT id, parentProjectId FROM projets');
    $parentMap = [];

    foreach ($statement->fetchAll() as $row) {
        $projectId = trim((string) ($row['id'] ?? ''));
        if ($projectId === '') {
            continue;
        }

        $parentMap[$projectId] = app_normalize_project_parent_id($row['parentProjectId'] ?? null, $projectId);
    }

    return $parentMap;
}

function app_validate_project_relationships(array $projects): void
{
    if ($projects === []) {
        return;
    }

    $parentMap = app_fetch_project_parent_map();
    $knownProjectIds = [];

    foreach (array_keys($parentMap) as $projectId) {
        $knownProjectIds[$projectId] = true;
    }

    foreach ($projects as $project) {
        $projectId = trim((string) ($project['id'] ?? ''));
        if ($projectId === '') {
            continue;
        }

        $knownProjectIds[$projectId] = true;
    }

    foreach ($projects as $project) {
        $projectId = trim((string) ($project['id'] ?? ''));
        if ($projectId === '') {
            continue;
        }

        $parentProjectId = app_normalize_project_parent_id($project['parentProjectId'] ?? null, $projectId);
        if ($parentProjectId === null) {
            $parentMap[$projectId] = null;
            continue;
        }

        if (!isset($knownProjectIds[$parentProjectId])) {
            throw new InvalidArgumentException('Le projet parent sélectionné est introuvable.');
        }

        $parentMap[$projectId] = $parentProjectId;
    }

    foreach ($parentMap as $projectId => $parentProjectId) {
        $visitedIds = [$projectId => true];
        $cursorId = $parentProjectId;

        while ($cursorId !== null && $cursorId !== '') {
            if (isset($visitedIds[$cursorId])) {
                throw new InvalidArgumentException('Un projet ne peut pas être rattaché à l\'un de ses sous-projets.');
            }

            $visitedIds[$cursorId] = true;
            $cursorId = $parentMap[$cursorId] ?? null;
        }
    }
}

function app_write_projects_json_mirror(array $projects): void
{
    $json = json_encode($projects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents(app_projects_file(), $json . PHP_EOL) === false) {
        throw new RuntimeException('Impossible d\'écrire le miroir JSON des projets.');
    }
}

function app_sync_project_service_links(array $projects): void
{
    $pdo = app_db();
    $serviceRows = app_fetch_existing_service_rows();
    $pdo->beginTransaction();

    try {
        $deleteStatement = $pdo->prepare('DELETE FROM projet_services WHERE project_id = :project_id');
        $insertStatement = $pdo->prepare(
            'INSERT IGNORE INTO projet_services (project_id, service_id) VALUES (:project_id, :service_id)'
        );

        foreach ($projects as $project) {
            $projectId = trim((string) ($project['id'] ?? ''));
            if ($projectId === '') {
                continue;
            }

            $deleteStatement->execute(['project_id' => $projectId]);

            $serviceNames = [];
            foreach (preg_split('/\s*\/\s*/', (string) ($project['service'] ?? '')) ?: [] as $token) {
                $serviceName = app_resolve_service_name($token, $serviceRows);
                if ($serviceName === '') {
                    continue;
                }

                $serviceNames[$serviceName] = $serviceName;
            }

            foreach ($serviceNames as $serviceName) {
                $serviceKey = app_normalize_service_key($serviceName);
                $serviceId = (int) ($serviceRows[$serviceKey]['id'] ?? 0);
                if ($serviceId < 1) {
                    continue;
                }

                $insertStatement->execute([
                    'project_id' => $projectId,
                    'service_id' => $serviceId,
                ]);
            }
        }

        $pdo->commit();
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $throwable;
    }
}
