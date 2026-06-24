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
        'SELECT id, ref, title, service, parentProjectId, projectType, description, color, customColor, start, duration, lane, startExact, endExact, riskGain, budgetEstimate, prioritization, projectManager, status, progression, youtrackId, youtrackUrl, youtrackTicketUrl, redmineUrl, cdcTitle, cdcRequester, cdcRequestDate, cdcDueDate, cdcPriority, cdcService, cdcProjectManager, cdcPresentation, cdcObjectives, cdcFeatures, cdcConstraints, cdcAdditionalInfo, cdcUpdatedAt, ownerId, ownerDisplayName, ownerEmail, teamMembers, taskColumns, created_at, updated_at
         FROM projets
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $normalizedId]);

    $row = $statement->fetch();
    if (!is_array($row)) {
        return null;
    }

    return app_attach_follow_up_tasks_to_project(app_normalize_project_record($row));
}

function app_fetch_project_by_ref(string $projectRef): ?array
{
    app_ensure_projects_schema();

    $normalizedRef = trim($projectRef);
    if ($normalizedRef === '') {
        return null;
    }

    $statement = app_db()->prepare(
        'SELECT id, ref, title, service, parentProjectId, projectType, description, color, customColor, start, duration, lane, startExact, endExact, riskGain, budgetEstimate, prioritization, projectManager, status, progression, youtrackId, youtrackUrl, youtrackTicketUrl, redmineUrl, cdcTitle, cdcRequester, cdcRequestDate, cdcDueDate, cdcPriority, cdcService, cdcProjectManager, cdcPresentation, cdcObjectives, cdcFeatures, cdcConstraints, cdcAdditionalInfo, cdcUpdatedAt, ownerId, ownerDisplayName, ownerEmail, teamMembers, taskColumns, created_at, updated_at
         FROM projets
         WHERE ref = :ref
         LIMIT 1'
    );
    $statement->execute(['ref' => $normalizedRef]);

    $row = $statement->fetch();
    if (!is_array($row)) {
        return null;
    }

    return app_attach_follow_up_tasks_to_project(app_normalize_project_record($row));
}

function app_fetch_project_by_youtrack_id(string $youtrackId): ?array
{
    app_ensure_projects_schema();

    $normalizedYoutrackId = trim($youtrackId);
    if ($normalizedYoutrackId === '') {
        return null;
    }

    $statement = app_db()->prepare(
        'SELECT id, ref, title, service, parentProjectId, projectType, description, color, customColor, start, duration, lane, startExact, endExact, riskGain, budgetEstimate, prioritization, projectManager, status, progression, youtrackId, youtrackUrl, youtrackTicketUrl, redmineUrl, cdcTitle, cdcRequester, cdcRequestDate, cdcDueDate, cdcPriority, cdcService, cdcProjectManager, cdcPresentation, cdcObjectives, cdcFeatures, cdcConstraints, cdcAdditionalInfo, cdcUpdatedAt, ownerId, ownerDisplayName, ownerEmail, teamMembers, taskColumns, created_at, updated_at
         FROM projets
         WHERE youtrackId = :youtrackId
         LIMIT 1'
    );
    $statement->execute(['youtrackId' => $normalizedYoutrackId]);

    $row = $statement->fetch();
    if (!is_array($row)) {
        return null;
    }

    return app_attach_follow_up_tasks_to_project(app_normalize_project_record($row));
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
        'SELECT id, ref, title, service, parentProjectId, projectType, description, color, customColor, start, duration, lane, startExact, endExact, riskGain, budgetEstimate, prioritization, projectManager, status, progression, youtrackId, youtrackUrl, youtrackTicketUrl, redmineUrl, cdcTitle, cdcRequester, cdcRequestDate, cdcDueDate, cdcPriority, cdcService, cdcProjectManager, cdcPresentation, cdcObjectives, cdcFeatures, cdcConstraints, cdcAdditionalInfo, cdcUpdatedAt, ownerId, ownerDisplayName, ownerEmail, teamMembers, taskColumns, created_at, updated_at
         FROM projets
         ORDER BY ref ASC, id ASC'
    );

    $projects = [];
    foreach ($statement->fetchAll() as $row) {
        $projects[] = app_normalize_project_record($row);
    }

    app_sync_project_service_links($projects);

    return app_attach_follow_up_tasks_to_projects($projects);
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
            id, ref, title, service, parentProjectId, projectType, description, color, customColor, start, duration, lane, startExact, endExact, riskGain, budgetEstimate, prioritization, projectManager, status, progression, youtrackId, youtrackUrl, youtrackTicketUrl, redmineUrl, cdcTitle, cdcRequester, cdcRequestDate, cdcDueDate, cdcPriority, cdcService, cdcProjectManager, cdcPresentation, cdcObjectives, cdcFeatures, cdcConstraints, cdcAdditionalInfo, cdcUpdatedAt, ownerId, ownerDisplayName, ownerEmail, teamMembers, taskColumns, created_at
        ) VALUES (
            :id, :ref, :title, :service, :parentProjectId, :projectType, :description, :color, :customColor, :start, :duration, :lane, :startExact, :endExact, :riskGain, :budgetEstimate, :prioritization, :projectManager, :status, :progression, :youtrackId, :youtrackUrl, :youtrackTicketUrl, :redmineUrl, :cdcTitle, :cdcRequester, :cdcRequestDate, :cdcDueDate, :cdcPriority, :cdcService, :cdcProjectManager, :cdcPresentation, :cdcObjectives, :cdcFeatures, :cdcConstraints, :cdcAdditionalInfo, :cdcUpdatedAt, :ownerId, :ownerDisplayName, :ownerEmail, :teamMembers, :taskColumns, :createdAt
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
            projectManager = VALUES(projectManager),
            status = VALUES(status),
            progression = VALUES(progression),
            youtrackId = VALUES(youtrackId),
            youtrackUrl = VALUES(youtrackUrl),
            youtrackTicketUrl = VALUES(youtrackTicketUrl),
            redmineUrl = VALUES(redmineUrl),
            cdcTitle = VALUES(cdcTitle),
            cdcRequester = VALUES(cdcRequester),
            cdcRequestDate = VALUES(cdcRequestDate),
            cdcDueDate = VALUES(cdcDueDate),
            cdcPriority = VALUES(cdcPriority),
            cdcService = VALUES(cdcService),
            cdcProjectManager = VALUES(cdcProjectManager),
            cdcPresentation = VALUES(cdcPresentation),
            cdcObjectives = VALUES(cdcObjectives),
            cdcFeatures = VALUES(cdcFeatures),
            cdcConstraints = VALUES(cdcConstraints),
            cdcAdditionalInfo = VALUES(cdcAdditionalInfo),
            cdcUpdatedAt = VALUES(cdcUpdatedAt),
            ownerId = VALUES(ownerId),
            ownerDisplayName = VALUES(ownerDisplayName),
            ownerEmail = VALUES(ownerEmail),
            teamMembers = VALUES(teamMembers),
            taskColumns = VALUES(taskColumns),
            created_at = COALESCE(VALUES(created_at), created_at),
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
                'projectManager' => $project['projectManager'],
                'status' => $project['status'],
                'progression' => $project['progression'],
                'youtrackId' => $project['youtrackId'],
                'youtrackUrl' => $project['youtrackUrl'],
                'youtrackTicketUrl' => $project['youtrackTicketUrl'],
                'redmineUrl' => $project['redmineUrl'],
                'cdcTitle' => $project['cdcTitle'],
                'cdcRequester' => $project['cdcRequester'],
                'cdcRequestDate' => $project['cdcRequestDate'],
                'cdcDueDate' => $project['cdcDueDate'],
                'cdcPriority' => $project['cdcPriority'],
                'cdcService' => $project['cdcService'],
                'cdcProjectManager' => $project['cdcProjectManager'],
                'cdcPresentation' => $project['cdcPresentation'],
                'cdcObjectives' => $project['cdcObjectives'],
                'cdcFeatures' => $project['cdcFeatures'],
                'cdcConstraints' => $project['cdcConstraints'],
                'cdcAdditionalInfo' => $project['cdcAdditionalInfo'],
                'cdcUpdatedAt' => $project['cdcUpdatedAt'],
                'ownerId' => $project['ownerId'],
                'ownerDisplayName' => $project['ownerDisplayName'],
                'ownerEmail' => $project['ownerEmail'],
                'teamMembers' => json_encode($project['teamMembers'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'taskColumns' => json_encode($project['taskColumns'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'createdAt' => $project['createdAt'] ?? date('Y-m-d H:i:s'),
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

    $storedProjectsById = [];
    foreach ($storedProjects as $storedProject) {
        if (!is_array($storedProject)) {
            continue;
        }

        $storedProjectsById[(string) ($storedProject['id'] ?? '')] = $storedProject;
    }

    $result = [];
    foreach ($normalizedProjects as $normalizedProject) {
        $normalizedProjectId = (string) ($normalizedProject['id'] ?? '');
        $result[] = $storedProjectsById[$normalizedProjectId] ?? $normalizedProject;
    }

    return $result;
}

function app_attach_follow_up_tasks_to_project(array $project): array
{
    $projectId = trim((string) ($project['id'] ?? ''));
    if ($projectId === '') {
        $project['followUpTasks'] = [];
        return $project;
    }

    $project['followUpTasks'] = app_fetch_project_follow_up_tasks($projectId);
    return $project;
}

function app_attach_follow_up_tasks_to_projects(array $projects): array
{
    if ($projects === []) {
        return [];
    }

    $projectIds = [];
    foreach ($projects as $project) {
        if (!is_array($project)) {
            continue;
        }

        $projectId = trim((string) ($project['id'] ?? ''));
        if ($projectId !== '') {
            $projectIds[] = $projectId;
        }
    }

    $tasksByProjectId = app_fetch_project_follow_up_tasks_map($projectIds);
    $result = [];

    foreach ($projects as $project) {
        if (!is_array($project)) {
            continue;
        }

        $projectId = trim((string) ($project['id'] ?? ''));
        $project['followUpTasks'] = $projectId !== ''
            ? ($tasksByProjectId[$projectId] ?? [])
            : [];
        $result[] = $project;
    }

    return $result;
}

function app_fetch_project_follow_up_tasks(string $projectId): array
{
    $tasksByProjectId = app_fetch_project_follow_up_tasks_map([$projectId]);
    return $tasksByProjectId[trim($projectId)] ?? [];
}

function app_fetch_project_follow_up_task_by_id(string $projectId, string $taskId): ?array
{
    app_ensure_projects_schema();

    $normalizedProjectId = trim($projectId);
    $normalizedTaskId = trim($taskId);
    if ($normalizedProjectId === '' || $normalizedTaskId === '') {
        return null;
    }

    $statement = app_db()->prepare(
        'SELECT id, project_id, task_date, title, details, youtrack_url, created_by_id, created_by_display_name, created_by_email, created_at, updated_at
         FROM project_follow_up_tasks
         WHERE project_id = :projectId AND id = :taskId
         LIMIT 1'
    );
    $statement->execute([
        'projectId' => $normalizedProjectId,
        'taskId' => $normalizedTaskId,
    ]);

    $row = $statement->fetch();
    if (!is_array($row)) {
        return null;
    }

    return app_normalize_project_follow_up_task_record($row);
}

function app_fetch_project_follow_up_tasks_map(array $projectIds): array
{
    app_ensure_projects_schema();

    $normalizedProjectIds = array_values(array_filter(array_map(
        static fn ($projectId): string => trim((string) $projectId),
        $projectIds
    )));
    if ($normalizedProjectIds === []) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($normalizedProjectIds), '?'));
    $statement = app_db()->prepare(
        'SELECT id, project_id, task_date, title, details, youtrack_url, created_by_id, created_by_display_name, created_by_email, created_at, updated_at
         FROM project_follow_up_tasks
         WHERE project_id IN (' . $placeholders . ')
         ORDER BY task_date DESC, updated_at DESC, created_at DESC, id DESC'
    );
    $statement->execute($normalizedProjectIds);

    $tasksByProjectId = [];
    foreach ($statement->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }

        $task = app_normalize_project_follow_up_task_record($row);
        if ($task === null) {
            continue;
        }

        $projectId = (string) ($task['projectId'] ?? '');
        if ($projectId === '') {
            continue;
        }

        if (!array_key_exists($projectId, $tasksByProjectId)) {
            $tasksByProjectId[$projectId] = [];
        }

        $tasksByProjectId[$projectId][] = $task;
    }

    return $tasksByProjectId;
}

function app_store_project_follow_up_task(string $projectId, array $task): array
{
    app_ensure_projects_schema();

    $normalizedProjectId = trim($projectId);
    if ($normalizedProjectId === '') {
        throw new InvalidArgumentException('Projet de suivi introuvable.');
    }

    $normalizedTask = app_normalize_project_follow_up_task_record([
        ...$task,
        'projectId' => $normalizedProjectId,
    ]);
    if ($normalizedTask === null) {
        throw new InvalidArgumentException('Tache de suivi invalide.');
    }

    $statement = app_db()->prepare(
        'INSERT INTO project_follow_up_tasks (
            id, project_id, task_date, title, details, youtrack_url, created_by_id, created_by_display_name, created_by_email, created_at
        ) VALUES (
            :id, :projectId, :taskDate, :title, :details, :youtrackUrl, :createdById, :createdByDisplayName, :createdByEmail, :createdAt
        )
        ON DUPLICATE KEY UPDATE
            task_date = VALUES(task_date),
            title = VALUES(title),
            details = VALUES(details),
            youtrack_url = VALUES(youtrack_url),
            updated_at = CURRENT_TIMESTAMP'
    );
    $statement->execute([
        'id' => $normalizedTask['id'],
        'projectId' => $normalizedTask['projectId'],
        'taskDate' => $normalizedTask['date'],
        'title' => $normalizedTask['title'],
        'details' => $normalizedTask['details'],
        'youtrackUrl' => $normalizedTask['youtrackUrl'],
        'createdById' => $normalizedTask['createdById'],
        'createdByDisplayName' => $normalizedTask['createdByDisplayName'],
        'createdByEmail' => $normalizedTask['createdByEmail'],
        'createdAt' => $normalizedTask['createdAt'] ?? date('Y-m-d H:i:s'),
    ]);

    $savedTask = app_fetch_project_follow_up_task_by_id($normalizedProjectId, (string) $normalizedTask['id']);
    if ($savedTask === null) {
        throw new RuntimeException('Impossible d enregistrer la tache de suivi.');
    }

    return $savedTask;
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
    $shouldBackfillCdcSummary = false;
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
            projectManager VARCHAR(255) DEFAULT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'A planifier',
            progression TINYINT UNSIGNED NOT NULL DEFAULT 0,
            youtrackId VARCHAR(64) DEFAULT NULL,
            youtrackUrl VARCHAR(255) DEFAULT NULL,
            youtrackTicketUrl VARCHAR(255) DEFAULT NULL,
            redmineUrl VARCHAR(255) DEFAULT NULL,
            cdcTitle VARCHAR(255) DEFAULT NULL,
            cdcRequester VARCHAR(255) DEFAULT NULL,
            cdcRequestDate DATETIME DEFAULT NULL,
            cdcDueDate DATETIME DEFAULT NULL,
            cdcPriority TEXT DEFAULT NULL,
            cdcService VARCHAR(255) DEFAULT NULL,
            cdcProjectManager VARCHAR(255) DEFAULT NULL,
            cdcPresentation LONGTEXT DEFAULT NULL,
            cdcObjectives LONGTEXT DEFAULT NULL,
            cdcFeatures LONGTEXT DEFAULT NULL,
            cdcConstraints LONGTEXT DEFAULT NULL,
            cdcAdditionalInfo LONGTEXT DEFAULT NULL,
            cdcUpdatedAt DATETIME DEFAULT NULL,
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

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS project_follow_up_tasks (
            id VARCHAR(64) NOT NULL,
            project_id VARCHAR(32) NOT NULL,
            task_date DATE NOT NULL,
            title VARCHAR(255) NOT NULL,
            details LONGTEXT DEFAULT NULL,
            youtrack_url VARCHAR(255) DEFAULT NULL,
            created_by_id VARCHAR(64) DEFAULT NULL,
            created_by_display_name VARCHAR(255) DEFAULT NULL,
            created_by_email VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_project_follow_up_tasks_project (project_id),
            KEY idx_project_follow_up_tasks_date (task_date),
            CONSTRAINT fk_project_follow_up_tasks_project
                FOREIGN KEY (project_id) REFERENCES projets(id)
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

    if (!app_projects_column_exists('projectManager')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN projectManager VARCHAR(255) DEFAULT NULL
             AFTER prioritization"
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

    if (!app_projects_column_exists('youtrackTicketUrl')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN youtrackTicketUrl VARCHAR(255) DEFAULT NULL
             AFTER youtrackUrl"
        );
    }

    if (!app_projects_column_exists('redmineUrl')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN redmineUrl VARCHAR(255) DEFAULT NULL
             AFTER youtrackTicketUrl"
        );
    }

    if (!app_projects_column_exists('cdcTitle')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN cdcTitle VARCHAR(255) DEFAULT NULL
             AFTER redmineUrl"
        );
    }

    if (!app_projects_column_exists('cdcPresentation')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN cdcPresentation LONGTEXT DEFAULT NULL
             AFTER cdcTitle"
        );
    }

    if (!app_projects_column_exists('cdcRequester')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN cdcRequester VARCHAR(255) DEFAULT NULL
             AFTER cdcTitle"
        );
        $shouldBackfillCdcSummary = true;
    }

    if (!app_projects_column_exists('cdcRequestDate')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN cdcRequestDate DATETIME DEFAULT NULL
             AFTER cdcRequester"
        );
        $shouldBackfillCdcSummary = true;
    }

    if (!app_projects_column_exists('cdcDueDate')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN cdcDueDate DATETIME DEFAULT NULL
             AFTER cdcRequestDate"
        );
        $shouldBackfillCdcSummary = true;
    }

    if (!app_projects_column_exists('cdcPriority')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN cdcPriority TEXT DEFAULT NULL
             AFTER cdcDueDate"
        );
        $shouldBackfillCdcSummary = true;
    }

    if (!app_projects_column_exists('cdcService')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN cdcService VARCHAR(255) DEFAULT NULL
             AFTER cdcPriority"
        );
        $shouldBackfillCdcSummary = true;
    }

    if (!app_projects_column_exists('cdcProjectManager')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN cdcProjectManager VARCHAR(255) DEFAULT NULL
             AFTER cdcService"
        );
        $shouldBackfillCdcSummary = true;
    }

    if (!app_projects_column_exists('cdcObjectives')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN cdcObjectives LONGTEXT DEFAULT NULL
             AFTER cdcPresentation"
        );
    }

    if (!app_projects_column_exists('cdcFeatures')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN cdcFeatures LONGTEXT DEFAULT NULL
             AFTER cdcObjectives"
        );
    }

    if (!app_projects_column_exists('cdcConstraints')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN cdcConstraints LONGTEXT DEFAULT NULL
             AFTER cdcFeatures"
        );
    }

    if (!app_projects_column_exists('cdcAdditionalInfo')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN cdcAdditionalInfo LONGTEXT DEFAULT NULL
             AFTER cdcConstraints"
        );
    }

    if (!app_projects_column_exists('cdcUpdatedAt')) {
        $pdo->exec(
            "ALTER TABLE projets
             ADD COLUMN cdcUpdatedAt DATETIME DEFAULT NULL
             AFTER cdcAdditionalInfo"
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

    if ($shouldBackfillCdcSummary) {
        app_backfill_project_cdc_summary_metadata();
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

function app_backfill_project_cdc_summary_metadata(): void
{
    app_db()->exec(
        "UPDATE projets
         SET
            cdcRequester = CASE
                WHEN cdcRequester IS NULL OR TRIM(cdcRequester) = '' THEN ownerDisplayName
                ELSE cdcRequester
            END,
            cdcRequestDate = COALESCE(cdcRequestDate, created_at),
            cdcDueDate = COALESCE(cdcDueDate, endExact),
            cdcPriority = CASE
                WHEN cdcPriority IS NULL OR TRIM(cdcPriority) = '' THEN prioritization
                ELSE cdcPriority
            END,
            cdcService = CASE
                WHEN cdcService IS NULL OR TRIM(cdcService) = '' THEN service
                ELSE cdcService
            END,
            cdcProjectManager = CASE
                WHEN cdcProjectManager IS NULL OR TRIM(cdcProjectManager) = '' THEN projectManager
                ELSE cdcProjectManager
            END
         WHERE cdcUpdatedAt IS NOT NULL
            OR TRIM(COALESCE(cdcTitle, '')) <> ''
            OR TRIM(COALESCE(cdcPresentation, '')) <> ''
            OR TRIM(COALESCE(cdcObjectives, '')) <> ''
            OR TRIM(COALESCE(cdcFeatures, '')) <> ''
            OR TRIM(COALESCE(cdcConstraints, '')) <> ''
            OR TRIM(COALESCE(cdcAdditionalInfo, '')) <> ''"
    );
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
    $projectManager = app_normalize_project_nullable_string($project['projectManager'] ?? null);
    $cdcRequester = app_normalize_project_nullable_string($project['cdcRequester'] ?? null);
    $cdcPriority = app_normalize_project_nullable_string($project['cdcPriority'] ?? null);
    $cdcService = app_normalize_project_nullable_string($project['cdcService'] ?? null);
    $cdcProjectManager = app_normalize_project_nullable_string($project['cdcProjectManager'] ?? null);
    $documentFlags = app_project_cdc_document_flags($normalizedProjectId);

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
        'projectManager' => $projectManager,
        'status' => app_normalize_project_status_value($project['status'] ?? null, $project),
        'progression' => app_normalize_project_progression_value($project['progression'] ?? 0),
        'youtrackId' => app_normalize_project_nullable_string($project['youtrackId'] ?? null),
        'youtrackUrl' => app_normalize_project_nullable_string($project['youtrackUrl'] ?? null),
        'youtrackTicketUrl' => app_normalize_project_nullable_string($project['youtrackTicketUrl'] ?? null),
        'redmineUrl' => app_normalize_project_nullable_string($project['redmineUrl'] ?? null),
        'cdcTitle' => app_normalize_project_nullable_string($project['cdcTitle'] ?? null),
        'cdcRequester' => $cdcRequester,
        'cdcRequestDate' => app_normalize_project_datetime_value($project['cdcRequestDate'] ?? null),
        'cdcDueDate' => app_normalize_project_datetime_value($project['cdcDueDate'] ?? null),
        'cdcPriority' => $cdcPriority,
        'cdcService' => $cdcService,
        'cdcProjectManager' => $cdcProjectManager,
        'cdcPresentation' => app_normalize_project_html_value($project['cdcPresentation'] ?? null),
        'cdcObjectives' => app_normalize_project_html_value($project['cdcObjectives'] ?? null),
        'cdcFeatures' => app_normalize_project_html_value($project['cdcFeatures'] ?? null),
        'cdcConstraints' => app_normalize_project_html_value($project['cdcConstraints'] ?? null),
        'cdcAdditionalInfo' => app_normalize_project_html_value($project['cdcAdditionalInfo'] ?? null),
        'cdcUpdatedAt' => app_normalize_project_datetime_value($project['cdcUpdatedAt'] ?? null),
        'cdcDocxAvailable' => $documentFlags['docx'],
        'cdcPdfAvailable' => $documentFlags['pdf'],
        'ownerId' => $ownerId,
        'ownerDisplayName' => $ownerDisplayName,
        'ownerEmail' => $ownerEmail,
        'teamMembers' => app_normalize_project_team_members($project['teamMembers'] ?? []),
        'taskColumns' => app_normalize_project_task_columns($project['taskColumns'] ?? []),
        'followUpTasks' => app_normalize_project_follow_up_tasks($project['followUpTasks'] ?? []),
        'createdAt' => app_normalize_project_datetime_value($project['created_at'] ?? $project['createdAt'] ?? null),
        'updatedAt' => app_normalize_project_datetime_value($project['updated_at'] ?? $project['updatedAt'] ?? null),
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

function app_normalize_project_html_value($value): ?string
{
    if ($value === null) {
        return null;
    }

    $normalized = trim((string) $value);
    return $normalized !== '' ? $normalized : null;
}

function app_normalize_project_datetime_value($value): ?string
{
    $normalized = app_normalize_project_nullable_string($value);
    if ($normalized === null) {
        return null;
    }

    $timestamp = strtotime($normalized);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function app_project_cdc_document_flags(string $projectId): array
{
    $normalizedProjectId = trim($projectId);
    if ($normalizedProjectId === '') {
        return [
            'docx' => false,
            'pdf' => false,
        ];
    }

    $baseDirectory = dirname(__DIR__, 3) . '/var/gantt/cdc/' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', $normalizedProjectId);

    return [
        'docx' => is_file($baseDirectory . '/current.docx'),
        'pdf' => is_file($baseDirectory . '/current.pdf'),
    ];
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

function app_normalize_project_follow_up_tasks($value): array
{
    $tasks = [];

    foreach (app_normalize_project_json_array($value) as $task) {
        if (!is_array($task)) {
            continue;
        }

        $normalizedTask = app_normalize_project_follow_up_task_record($task);
        if ($normalizedTask === null) {
            continue;
        }

        $tasks[(string) $normalizedTask['id']] = $normalizedTask;
    }

    return array_values($tasks);
}

function app_normalize_project_follow_up_task_record(array $task): ?array
{
    $taskId = trim((string) ($task['id'] ?? ''));
    if ($taskId === '') {
        $taskId = app_generate_project_follow_up_task_id();
    }

    $projectId = trim((string) ($task['projectId'] ?? $task['project_id'] ?? ''));
    $title = trim((string) ($task['title'] ?? ''));
    $taskDate = app_normalize_project_date_value($task['date'] ?? $task['task_date'] ?? null);

    if ($taskId === '' || $title === '' || $taskDate === null) {
        return null;
    }

    return [
        'id' => $taskId,
        'projectId' => $projectId,
        'date' => $taskDate,
        'title' => $title,
        'details' => app_normalize_project_nullable_string($task['details'] ?? null),
        'youtrackUrl' => app_normalize_project_nullable_string($task['youtrackUrl'] ?? $task['youtrack_url'] ?? null),
        'createdById' => app_normalize_project_nullable_string($task['createdById'] ?? $task['created_by_id'] ?? null),
        'createdByDisplayName' => app_normalize_project_nullable_string($task['createdByDisplayName'] ?? $task['created_by_display_name'] ?? null),
        'createdByEmail' => app_normalize_project_nullable_string($task['createdByEmail'] ?? $task['created_by_email'] ?? null),
        'createdAt' => app_normalize_project_datetime_value($task['createdAt'] ?? $task['created_at'] ?? null),
        'updatedAt' => app_normalize_project_datetime_value($task['updatedAt'] ?? $task['updated_at'] ?? null),
    ];
}

function app_generate_project_follow_up_task_id(): string
{
    return 'pfu_' . bin2hex(random_bytes(8));
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
