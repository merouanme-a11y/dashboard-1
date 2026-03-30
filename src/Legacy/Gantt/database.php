<?php

declare(strict_types=1);

require_once __DIR__ . '/runtime.php';

function app_db(): PDO
{
    return Database::connect();
}

function app_fetch_standard_project_users(): array
{
    $statement = app_db()->query(
        "SELECT id, prenom, nom, email, service, profile_type
         FROM utilisateur
         WHERE TRIM(COALESCE(id, '')) <> ''
           AND LOWER(TRIM(COALESCE(profile_type, 'Responsable'))) <> 'admin'
         ORDER BY prenom ASC, nom ASC, id ASC"
    );

    $users = [];
    foreach ($statement->fetchAll() as $row) {
        $id = trim((string) ($row['id'] ?? ''));
        if ($id === '') {
            continue;
        }

        $firstName = trim((string) ($row['prenom'] ?? ''));
        $lastName = trim((string) ($row['nom'] ?? ''));
        $displayName = trim($firstName . ' ' . $lastName);

        $users[] = [
            'id' => $id,
            'displayName' => $displayName !== '' ? $displayName : $id,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => trim((string) ($row['email'] ?? '')),
            'service' => trim((string) ($row['service'] ?? '')),
            'profileType' => trim((string) ($row['profile_type'] ?? 'Responsable')) ?: 'Responsable',
        ];
    }

    return $users;
}

function app_fetch_standard_project_user_by_id(string $userId): ?array
{
    $normalizedId = trim($userId);
    if ($normalizedId === '') {
        return null;
    }

    $statement = app_db()->prepare(
        "SELECT id, prenom, nom, email, service, profile_type
         FROM utilisateur
         WHERE id = :id
           AND LOWER(TRIM(COALESCE(profile_type, 'Responsable'))) <> 'admin'
         LIMIT 1"
    );
    $statement->execute(['id' => $normalizedId]);

    $row = $statement->fetch();
    if (!is_array($row)) {
        return null;
    }

    $firstName = trim((string) ($row['prenom'] ?? ''));
    $lastName = trim((string) ($row['nom'] ?? ''));
    $displayName = trim($firstName . ' ' . $lastName);

    return [
        'id' => $normalizedId,
        'displayName' => $displayName !== '' ? $displayName : $normalizedId,
        'firstName' => $firstName,
        'lastName' => $lastName,
        'email' => trim((string) ($row['email'] ?? '')),
        'service' => trim((string) ($row['service'] ?? '')),
        'profileType' => trim((string) ($row['profile_type'] ?? 'Responsable')) ?: 'Responsable',
    ];
}

function app_default_service_colors(): array
{
    return [
        'Agence' => '#B88463',
        'COM' => '#6c757d',
        'Communication' => '#3d3d38',
        'Comptabilité' => '#ab3df5',
        'Conformité' => '#d415c4',
        'Contrôle Interne' => '#da1414',
        'IT' => '#5380e0',
        'Marketing' => '#4da5f7',
        'PATCH' => '#B0007A',
        'Prestations' => '#56bad3',
        'Production' => '#ffbb00',
        'Relation Client' => '#b95ad3',
        'RH' => '#2F4E8C',
        'Service Entreprise' => '#E89A61',
        'Sinistre' => '#a10f67',
    ];
}

function app_normalize_service_key(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($transliterated !== false) {
        $value = $transliterated;
    }

    $value = mb_strtolower($value, 'UTF-8');
    $value = preg_replace('/[^a-z0-9]+/', '', $value);
    return $value ?? '';
}

function app_service_aliases(): array
{
    return [
        'agence' => 'Agence',
        'com' => 'COM',
        'communication' => 'Communication',
        'compta' => 'Comptabilité',
        'comptabilite' => 'Comptabilité',
        'conformite' => 'Conformité',
        'controleinterne' => 'Contrôle Interne',
        'it' => 'IT',
        'market' => 'Marketing',
        'marketing' => 'Marketing',
        'patch' => 'PATCH',
        'prestation' => 'Prestations',
        'prestations' => 'Prestations',
        'prc' => 'Relation Client',
        'relationclient' => 'Relation Client',
        'production' => 'Production',
        'rh' => 'RH',
        'serviceentreprise' => 'Service Entreprise',
        'sinistre' => 'Sinistre',
        'test' => '',
    ];
}

function app_fetch_existing_service_rows(): array
{
    $statement = app_db()->query('SELECT id, name, color FROM services ORDER BY name ASC');
    $rows = [];

    foreach ($statement->fetchAll() as $row) {
        $name = trim((string) ($row['name'] ?? ''));
        $key = app_normalize_service_key($name);
        if ($name === '' || $key === '') {
            continue;
        }

        $rows[$key] = [
            'id' => (int) ($row['id'] ?? 0),
            'name' => $name,
            'color' => trim((string) ($row['color'] ?? '')),
        ];
    }

    return $rows;
}

function app_resolve_service_name(string $value, ?array $existingRows = null): string
{
    $name = trim($value);
    if ($name === '') {
        return '';
    }

    $key = app_normalize_service_key($name);
    $aliases = app_service_aliases();
    if (array_key_exists($key, $aliases)) {
        return $aliases[$key];
    }

    $existingRows = $existingRows ?? app_fetch_existing_service_rows();
    if (isset($existingRows[$key])) {
        return $existingRows[$key]['name'];
    }

    return preg_replace('/\s+/', ' ', $name) ?? $name;
}

function app_normalize_service_name(string $value): string
{
    return app_resolve_service_name($value);
}

function app_normalize_project_services_string(string $value): string
{
    $existingRows = app_fetch_existing_service_rows();
    $normalizedServices = [];

    foreach (preg_split('/\s*\/\s*/', trim($value)) ?: [] as $token) {
        $service = app_resolve_service_name($token, $existingRows);
        if ($service === '') {
            continue;
        }

        $normalizedServices[$service] = $service;
    }

    return implode(' / ', array_values($normalizedServices));
}

function app_extract_project_services(array $projects): array
{
    $existingRows = app_fetch_existing_service_rows();
    $services = [];

    foreach ($projects as $project) {
        $rawService = (string) ($project['service'] ?? '');
        foreach (preg_split('/\s*\/\s*/', $rawService) ?: [] as $token) {
            $service = app_resolve_service_name($token, $existingRows);
            if ($service === '') {
                continue;
            }

            $services[$service] = $service;
        }
    }

    uasort($services, static function (string $left, string $right): int {
        return strcasecmp($left, $right);
    });

    return array_values($services);
}

function app_normalize_hex_color(string $value): string
{
    $normalized = strtolower(trim($value));
    if ($normalized === '') {
        return '';
    }

    if (preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{6})$/', $normalized, $matches) !== 1) {
        return '';
    }

    $hex = $matches[1];
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    return '#' . $hex;
}

function app_choose_service_color(string $serviceName, string $currentColor = '', string $candidateColor = ''): string
{
    $normalizedCurrent = app_normalize_hex_color($currentColor);
    $normalizedCandidate = app_normalize_hex_color($candidateColor);
    $defaults = app_default_service_colors();
    $defaultColor = app_normalize_hex_color($defaults[$serviceName] ?? '#1d6f74');

    if ($normalizedCurrent !== '' && $normalizedCurrent !== '#6c757d') {
        return $normalizedCurrent;
    }

    if ($normalizedCandidate !== '') {
        return $normalizedCandidate;
    }

    if ($normalizedCurrent !== '') {
        return $normalizedCurrent;
    }

    return $defaultColor !== '' ? $defaultColor : '#1d6f74';
}

function app_sync_services_from_projects(array $projects): array
{
    $pdo = app_db();
    $serviceNames = app_extract_project_services($projects);
    $existingRows = app_fetch_existing_service_rows();
    $defaultColors = app_default_service_colors();
    $statement = $pdo->prepare(
        'INSERT INTO services (name, color) VALUES (:name, :color)
         ON DUPLICATE KEY UPDATE color = VALUES(color)'
    );

    foreach ($serviceNames as $serviceName) {
        $existingColor = $existingRows[app_normalize_service_key($serviceName)]['color'] ?? '';
        $targetColor = app_choose_service_color($serviceName, $existingColor, $defaultColors[$serviceName] ?? '');

        $statement->execute([
            'name' => $serviceName,
            'color' => $targetColor,
        ]);
    }

    return app_fetch_service_colors();
}

function app_fetch_service_colors(): array
{
    $statement = app_db()->query('SELECT name, color FROM services ORDER BY name ASC');
    $services = [];

    foreach ($statement->fetchAll() as $row) {
        $serviceName = trim((string) ($row['name'] ?? ''));
        $color = strtolower(trim((string) ($row['color'] ?? '')));
        if ($serviceName === '' || $color === '') {
            continue;
        }

        $services[$serviceName] = $color;
    }

    return $services;
}

function app_update_service_color(string $serviceName, string $color): array
{
    $existingRows = app_fetch_existing_service_rows();
    $normalizedService = app_resolve_service_name($serviceName, $existingRows);
    $normalizedColor = app_normalize_hex_color($color);

    if ($normalizedService === '') {
        throw new RuntimeException('Le nom du service est obligatoire.');
    }

    if ($normalizedColor === '') {
        throw new RuntimeException('La couleur du service est invalide.');
    }

    $statement = app_db()->prepare(
        'INSERT INTO services (name, color) VALUES (:name, :color)
         ON DUPLICATE KEY UPDATE color = VALUES(color)'
    );

    $statement->execute([
        'name' => $normalizedService,
        'color' => $normalizedColor,
    ]);

    return app_fetch_service_colors();
}

function app_open_legacy_gantt_db(): ?PDO
{
    try {
        $pdo = new PDO('mysql:host=localhost;port=3306;dbname=ganttprojets;charset=utf8mb4', 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (Throwable $throwable) {
        return null;
    }
}

function app_merge_legacy_gantt_services_into_dashboard(): void
{
    static $merged = false;

    if ($merged) {
        return;
    }

    $merged = true;
    $legacyPdo = app_open_legacy_gantt_db();
    if (!$legacyPdo) {
        return;
    }

    $legacyRows = $legacyPdo->query('SELECT service, couleur FROM service ORDER BY service ASC')->fetchAll();
    if (!$legacyRows) {
        return;
    }

    $pdo = app_db();
    $existingRows = app_fetch_existing_service_rows();
    $upsert = $pdo->prepare(
        'INSERT INTO services (name, color) VALUES (:name, :color)
         ON DUPLICATE KEY UPDATE color = VALUES(color)'
    );

    foreach ($legacyRows as $row) {
        $canonicalName = app_resolve_service_name((string) ($row['service'] ?? ''), $existingRows);
        if ($canonicalName === '') {
            continue;
        }

        $key = app_normalize_service_key($canonicalName);
        $currentColor = $existingRows[$key]['color'] ?? '';
        $targetColor = app_choose_service_color($canonicalName, $currentColor, (string) ($row['couleur'] ?? ''));

        $upsert->execute([
            'name' => $canonicalName,
            'color' => $targetColor,
        ]);

        $existingRows[$key] = [
            'id' => (int) ($existingRows[$key]['id'] ?? 0),
            'name' => $canonicalName,
            'color' => $targetColor,
        ];
    }
}

function app_merge_duplicate_services(): void
{
    $pdo = app_db();
    $pdo->beginTransaction();

    try {
        $rows = $pdo->query('SELECT id, name, color FROM services ORDER BY id ASC')->fetchAll();
        $canonicalRows = [];
        $replaceById = [];
        $upsert = $pdo->prepare(
            'INSERT INTO services (name, color) VALUES (:name, :color)
             ON DUPLICATE KEY UPDATE color = VALUES(color)'
        );

        foreach ($rows as $row) {
            $canonicalName = app_resolve_service_name((string) ($row['name'] ?? ''));
            if ($canonicalName === '') {
                $replaceById[(int) $row['id']] = null;
                continue;
            }

            $color = app_choose_service_color(
                $canonicalName,
                $canonicalRows[app_normalize_service_key($canonicalName)]['color'] ?? '',
                (string) ($row['color'] ?? '')
            );

            $upsert->execute([
                'name' => $canonicalName,
                'color' => $color,
            ]);
        }

        $canonicalRows = app_fetch_existing_service_rows();
        foreach ($rows as $row) {
            $originalId = (int) ($row['id'] ?? 0);
            $canonicalName = app_resolve_service_name((string) ($row['name'] ?? ''), $canonicalRows);
            $replaceById[$originalId] = $canonicalName !== ''
                ? (int) ($canonicalRows[app_normalize_service_key($canonicalName)]['id'] ?? 0)
                : null;
        }

        $projectLinkRows = $pdo->query('SELECT project_id, service_id FROM projet_services')->fetchAll();
        $deleteLink = $pdo->prepare('DELETE FROM projet_services WHERE project_id = :project_id AND service_id = :service_id');
        $upsertLink = $pdo->prepare(
            'INSERT IGNORE INTO projet_services (project_id, service_id) VALUES (:project_id, :service_id)'
        );

        foreach ($projectLinkRows as $linkRow) {
            $projectId = (string) ($linkRow['project_id'] ?? '');
            $serviceId = (int) ($linkRow['service_id'] ?? 0);
            $targetId = $replaceById[$serviceId] ?? null;

            if ($projectId === '' || $serviceId < 1) {
                continue;
            }

            if ($targetId === null) {
                $deleteLink->execute([
                    'project_id' => $projectId,
                    'service_id' => $serviceId,
                ]);
                continue;
            }

            if ($targetId !== $serviceId) {
                $upsertLink->execute([
                    'project_id' => $projectId,
                    'service_id' => $targetId,
                ]);
                $deleteLink->execute([
                    'project_id' => $projectId,
                    'service_id' => $serviceId,
                ]);
            }
        }

        $users = $pdo->query("SELECT id, service FROM utilisateur WHERE service IS NOT NULL AND TRIM(service) <> ''")->fetchAll();
        $updateUser = $pdo->prepare('UPDATE utilisateur SET service = :service WHERE id = :id');
        foreach ($users as $user) {
            $canonicalName = app_resolve_service_name((string) ($user['service'] ?? ''), $canonicalRows);
            if ($canonicalName !== '' && $canonicalName !== (string) $user['service']) {
                $updateUser->execute([
                    'service' => $canonicalName,
                    'id' => (string) $user['id'],
                ]);
            }
        }

        $deleteService = $pdo->prepare('DELETE FROM services WHERE id = :id');
        foreach ($rows as $row) {
            $originalId = (int) ($row['id'] ?? 0);
            $targetId = $replaceById[$originalId] ?? null;
            if ($targetId === null || $targetId !== $originalId) {
                $deleteService->execute(['id' => $originalId]);
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
