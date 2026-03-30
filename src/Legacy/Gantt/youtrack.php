<?php

declare(strict_types=1);

require_once __DIR__ . '/runtime.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/database.php';

function app_youtrack_request(string $method, string $url, ?array $body = null): array
{
    $handle = curl_init($url);
    if ($handle === false) {
        throw new RuntimeException('Impossible d\'initialiser la connexion vers YouTrack.');
    }

    $headers = [
        'Authorization: Bearer ' . YT_TOKEN,
        'Accept: application/json',
        'Content-Type: application/json',
    ];

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ];

    if ($body !== null) {
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new RuntimeException('Impossible de sÃ©rialiser la requÃªte YouTrack.');
        }

        $options[CURLOPT_POSTFIELDS] = $payload;
    }

    curl_setopt_array($handle, $options);

    $responseBody = curl_exec($handle);
    $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $curlError = curl_error($handle);

    curl_close($handle);

    if ($curlError !== '') {
        throw new RuntimeException('Erreur de connexion YouTrack : ' . $curlError);
    }

    if ($responseBody === false || $responseBody === null) {
        throw new RuntimeException('RÃ©ponse vide renvoyÃ©e par YouTrack.');
    }

    $decoded = json_decode((string) $responseBody, true);
    $parsedResponse = is_array($decoded) ? $decoded : [];

    if ($statusCode < 200 || $statusCode >= 300) {
        $errorDetail = '';

        if (!empty($parsedResponse['error_description'])) {
            $errorDetail = (string) $parsedResponse['error_description'];
        } elseif (!empty($parsedResponse['error'])) {
            $errorDetail = (string) $parsedResponse['error'];
        } elseif (!empty($parsedResponse['message'])) {
            $errorDetail = (string) $parsedResponse['message'];
        } else {
            $errorDetail = trim(strip_tags((string) $responseBody));
        }

        throw new RuntimeException(
            $errorDetail !== ''
                ? 'YouTrack a refusÃ© la requÃªte : ' . $errorDetail
                : 'YouTrack a refusÃ© la requÃªte.'
        );
    }

    if ($parsedResponse === [] && trim((string) $responseBody) !== '' && json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('RÃ©ponse JSON invalide reÃ§ue depuis YouTrack.');
    }

    return $parsedResponse;
}

function app_youtrack_base_url(): string
{
    return rtrim((string) YT_BASE_URL, '/');
}

function app_youtrack_hub_base_url(): string
{
    return app_youtrack_base_url() . '/hub/api/rest';
}

function app_array_is_list_compatible(array $value): bool
{
    if (function_exists('array_is_list')) {
        return array_is_list($value);
    }

    $expectedKey = 0;
    foreach ($value as $key => $_) {
        if ($key !== $expectedKey) {
            return false;
        }

        $expectedKey += 1;
    }

    return true;
}

function app_normalize_youtrack_cache_key(string $value): string
{
    $normalized = trim($value);
    if ($normalized === '') {
        return '';
    }

    $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    return mb_strtolower($normalized, 'UTF-8');
}

function app_youtrack_project_cache_key(string $projectKey): string
{
    return app_normalize_youtrack_cache_key($projectKey);
}

function app_reset_youtrack_project_record_cache(string $projectKey): void
{
    $cacheKey = app_youtrack_project_cache_key($projectKey);
    if ($cacheKey === '') {
        return;
    }

    app_cache_forget('youtrack.project-record', $cacheKey);
    app_cache_forget('youtrack.hub-project', $cacheKey);
}

function app_reset_youtrack_project_tasks_cache(string $projectKey): void
{
    $cacheKey = app_youtrack_project_cache_key($projectKey);
    if ($cacheKey === '') {
        return;
    }

    app_cache_forget('youtrack.project-tasks', $cacheKey);
}

function app_reset_youtrack_project_team_cache(string $projectKey): void
{
    $cacheKey = app_youtrack_project_cache_key($projectKey);
    if ($cacheKey === '') {
        return;
    }

    app_cache_forget('youtrack.project-team', $cacheKey);
}

function app_normalize_youtrack_project_record(array $project, string $fallbackProjectKey = ''): ?array
{
    $projectId = trim((string) ($project['id'] ?? ''));
    if ($projectId === '') {
        return null;
    }

    $projectRingId = trim((string) ($project['ringId'] ?? ''));
    $shortName = trim((string) ($project['shortName'] ?? $fallbackProjectKey));
    $name = trim((string) ($project['name'] ?? ''));
    $leaderId = trim((string) ($project['leader']['id'] ?? ''));
    $leaderLogin = trim((string) ($project['leader']['login'] ?? ''));
    $leaderName = trim((string) ($project['leader']['name'] ?? $leaderLogin));

    return [
        'id' => $projectId,
        'ringId' => $projectRingId,
        'shortName' => $shortName !== '' ? $shortName : $fallbackProjectKey,
        'name' => $name,
        'leader' => [
            'id' => $leaderId,
            'login' => $leaderLogin,
            'name' => $leaderName,
        ],
        'archived' => !empty($project['archived']),
    ];
}

function app_find_youtrack_project_or_null(string $projectKey): ?array
{
    $normalizedProjectKey = trim($projectKey);
    if ($normalizedProjectKey === '') {
        throw new InvalidArgumentException('Le projet YouTrack cible est manquant.');
    }

    return app_cache_remember(
        'youtrack.project-record',
        app_youtrack_project_cache_key($normalizedProjectKey),
        120,
        static function () use ($normalizedProjectKey): ?array {
            try {
                $directProject = app_youtrack_request(
                    'GET',
                    app_youtrack_base_url()
                        . '/api/admin/projects/'
                        . rawurlencode($normalizedProjectKey)
                        . '?fields='
                        . rawurlencode('id,ringId,name,shortName,leader(id,login,name),archived')
                );

                $normalizedDirectProject = is_array($directProject)
                    ? app_normalize_youtrack_project_record($directProject, $normalizedProjectKey)
                    : null;
                if ($normalizedDirectProject !== null) {
                    return $normalizedDirectProject;
                }
            } catch (Throwable $throwable) {
            }

            $projects = app_youtrack_request(
                'GET',
                app_youtrack_base_url()
                    . '/api/admin/projects?fields=id,ringId,name,shortName,leader(id,login,name),archived&query='
                    . rawurlencode($normalizedProjectKey)
                    . '&$top=20'
            );

            if (!is_array($projects) || $projects === []) {
                return null;
            }

            foreach ($projects as $project) {
                $shortName = trim((string) ($project['shortName'] ?? ''));
                $name = trim((string) ($project['name'] ?? ''));

                if (
                    strcasecmp($shortName, $normalizedProjectKey) === 0 ||
                    strcasecmp($name, $normalizedProjectKey) === 0
                ) {
                    return app_normalize_youtrack_project_record($project, $normalizedProjectKey);
                }
            }

            $fallback = is_array($projects[0] ?? null) ? $projects[0] : null;
            return $fallback ? app_normalize_youtrack_project_record($fallback, $normalizedProjectKey) : null;
        },
        [
            'allowStaleOnError' => true,
            'staleTtl' => 900,
        ]
    );
}

function app_find_youtrack_project(string $projectKey): array
{
    $normalizedProjectKey = trim($projectKey);
    $project = app_find_youtrack_project_or_null($projectKey);
    if ($project !== null) {
        return $project;
    }

    throw new RuntimeException('Projet YouTrack introuvable : ' . $normalizedProjectKey);
}

function app_find_youtrack_user_id(string $loginOrEmail): string
{
    $loginOrEmail = trim($loginOrEmail);
    if ($loginOrEmail === '') {
        return '';
    }

    $candidates = [$loginOrEmail];
    $atPos = strpos($loginOrEmail, '@');
    if ($atPos !== false) {
        $login = substr($loginOrEmail, 0, $atPos);
        if ($login !== '') {
            $candidates[] = $login;
        }
    }

    foreach (app_list_youtrack_standard_users() as $user) {
        $userId = trim((string) ($user['youtrackId'] ?? ''));
        if ($userId === '') {
            continue;
        }

        $userLogin = trim((string) ($user['login'] ?? ''));
        $userEmail = trim((string) ($user['email'] ?? ''));
        $displayName = trim((string) ($user['displayName'] ?? ''));

        foreach ($candidates as $candidate) {
            if (
                ($userLogin !== '' && strcasecmp($userLogin, $candidate) === 0)
                || ($userEmail !== '' && strcasecmp($userEmail, $candidate) === 0)
                || ($displayName !== '' && strcasecmp($displayName, $candidate) === 0)
            ) {
                return $userId;
            }
        }
    }

    return (string) app_cache_remember(
        'youtrack.user-id-lookup',
        app_normalize_youtrack_cache_key($loginOrEmail),
        3600,
        static function () use ($candidates, $loginOrEmail): string {
            foreach ($candidates as $candidate) {
                $url = app_youtrack_base_url()
                    . '/api/users?fields=id,login,name&query='
                    . rawurlencode($candidate)
                    . '&$top=10';

                $users = app_youtrack_request('GET', $url);
                if (!is_array($users) || $users === []) {
                    continue;
                }

                foreach ($users as $user) {
                    $userId = trim((string) ($user['id'] ?? ''));
                    $login = trim((string) ($user['login'] ?? ''));
                    $name = trim((string) ($user['name'] ?? ''));

                    if ($userId === '') {
                        continue;
                    }

                    if (
                        strcasecmp($login, $candidate) === 0 ||
                        strcasecmp($name, $candidate) === 0 ||
                        strcasecmp($login, $loginOrEmail) === 0
                    ) {
                        return $userId;
                    }
                }

                $firstUser = is_array($users[0] ?? null) ? $users[0] : null;
                if ($firstUser && trim((string) ($firstUser['id'] ?? '')) !== '') {
                    return trim((string) ($firstUser['id'] ?? ''));
                }
            }

            return '';
        },
        [
            'allowStaleOnError' => true,
            'staleTtl' => 86400,
        ]
    );
}

function app_list_youtrack_standard_users(): array
{
    static $cachedUsers = null;

    if (is_array($cachedUsers)) {
        return $cachedUsers;
    }

    $cachedUsers = app_cache_remember(
        'youtrack.standard-users',
        'all',
        300,
        static function (): array {
            $users = [];
            $skip = 0;
            $top = 50;

            do {
                $page = app_youtrack_request(
                    'GET',
                    app_youtrack_base_url()
                        . '/api/users?fields=id,ringId,login,name,fullName,email,banned,guest'
                        . '&$skip=' . $skip
                        . '&$top=' . $top
                );

                if (!is_array($page)) {
                    break;
                }

                foreach ($page as $user) {
                    if (!is_array($user)) {
                        continue;
                    }

                    if (!empty($user['guest']) || !empty($user['banned'])) {
                        continue;
                    }

                    $ringId = trim((string) ($user['ringId'] ?? ''));
                    $youtrackId = trim((string) ($user['id'] ?? ''));
                    if ($ringId === '' || $youtrackId === '') {
                        continue;
                    }

                    $displayName = trim((string) ($user['fullName'] ?? $user['name'] ?? $user['login'] ?? ''));
                    $login = trim((string) ($user['login'] ?? ''));

                    $users[$ringId] = [
                        'id' => $ringId,
                        'ringId' => $ringId,
                        'youtrackId' => $youtrackId,
                        'displayName' => $displayName !== '' ? $displayName : ($login !== '' ? $login : $ringId),
                        'login' => $login,
                        'email' => trim((string) ($user['email'] ?? '')),
                    ];
                }

                $skip += $top;
            } while (count($page) === $top);

            $users = array_values($users);
            usort($users, static function (array $left, array $right): int {
                return strcasecmp((string) ($left['displayName'] ?? ''), (string) ($right['displayName'] ?? ''));
            });

            return $users;
        },
        [
            'allowStaleOnError' => true,
            'staleTtl' => 3600,
        ]
    );

    return is_array($cachedUsers) ? $cachedUsers : [];
}

function app_get_youtrack_standard_users_by_ring_id(): array
{
    $usersByRingId = [];
    foreach (app_list_youtrack_standard_users() as $user) {
        $ringId = trim((string) ($user['ringId'] ?? $user['id'] ?? ''));
        if ($ringId === '') {
            continue;
        }

        $usersByRingId[$ringId] = $user;
    }

    return $usersByRingId;
}

function app_find_youtrack_standard_user_by_youtrack_id(string $youtrackId): ?array
{
    $normalizedYoutrackId = trim($youtrackId);
    if ($normalizedYoutrackId === '') {
        return null;
    }

    foreach (app_list_youtrack_standard_users() as $user) {
        if (trim((string) ($user['youtrackId'] ?? '')) === $normalizedYoutrackId) {
            return $user;
        }
    }

    return null;
}

function app_find_youtrack_custom_field_definition(string $fieldName): ?array
{
    $normalizedFieldName = trim($fieldName);
    if ($normalizedFieldName === '') {
        return null;
    }

    return app_cache_remember(
        'youtrack.custom-field-definition',
        app_normalize_youtrack_cache_key($normalizedFieldName),
        3600,
        static function () use ($normalizedFieldName): ?array {
            $definitions = app_youtrack_request(
                'GET',
                app_youtrack_base_url()
                    . '/api/admin/customFieldSettings/customFields?fields=id,name,localizedName&query='
                    . rawurlencode($normalizedFieldName)
                    . '&$top=20'
            );

            if (!is_array($definitions)) {
                return null;
            }

            foreach ($definitions as $definition) {
                $name = trim((string) ($definition['name'] ?? ''));
                $localizedName = trim((string) ($definition['localizedName'] ?? ''));
                if (
                    strcasecmp($name, $normalizedFieldName) === 0
                    || ($localizedName !== '' && strcasecmp($localizedName, $normalizedFieldName) === 0)
                ) {
                    return $definition;
                }
            }

            return null;
        },
        [
            'allowStaleOnError' => true,
            'staleTtl' => 86400,
        ]
    );
}

function app_get_youtrack_project_custom_fields(string $projectKey): array
{
    $normalizedProjectKey = trim($projectKey);
    if ($normalizedProjectKey === '') {
        return [];
    }

    if (!isset($GLOBALS['app_youtrack_project_custom_fields_cache']) || !is_array($GLOBALS['app_youtrack_project_custom_fields_cache'])) {
        $GLOBALS['app_youtrack_project_custom_fields_cache'] = [];
    }

    if (isset($GLOBALS['app_youtrack_project_custom_fields_cache'][$normalizedProjectKey])) {
        return $GLOBALS['app_youtrack_project_custom_fields_cache'][$normalizedProjectKey];
    }

    $indexedFields = app_cache_remember(
        'youtrack.project-custom-fields',
        app_youtrack_project_cache_key($normalizedProjectKey),
        180,
        static function () use ($normalizedProjectKey): array {
            $project = app_find_youtrack_project($normalizedProjectKey);
            $fields = app_youtrack_request(
                'GET',
                app_youtrack_base_url()
                    . '/api/admin/projects/'
                    . rawurlencode((string) $project['id'])
                    . '/customFields?fields=id,$type,canBeEmpty,emptyFieldText,ordinal,field(id,name,localizedName),bundle(id,name,values(id,name,presentation,isResolved,color(id)),aggregatedUsers(id,login,name,fullName))'
                    . '&$top=100'
            );

            $indexedFields = [];
            if (is_array($fields)) {
                foreach ($fields as $field) {
                    if (!is_array($field)) {
                        continue;
                    }

                    $name = trim((string) ($field['field']['name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }

                    $indexedFields[$name] = $field;
                }
            }

            return $indexedFields;
        },
        [
            'allowStaleOnError' => true,
            'staleTtl' => 900,
        ]
    );

    $GLOBALS['app_youtrack_project_custom_fields_cache'][$normalizedProjectKey] = is_array($indexedFields) ? $indexedFields : [];
    return $GLOBALS['app_youtrack_project_custom_fields_cache'][$normalizedProjectKey];
}

function app_reset_youtrack_project_custom_fields_cache(string $projectKey): void
{
    $normalizedProjectKey = trim($projectKey);
    if ($normalizedProjectKey === '') {
        return;
    }

    if (isset($GLOBALS['app_youtrack_project_custom_fields_cache'][$normalizedProjectKey])) {
        unset($GLOBALS['app_youtrack_project_custom_fields_cache'][$normalizedProjectKey]);
    }

    app_cache_forget('youtrack.project-custom-fields', app_youtrack_project_cache_key($normalizedProjectKey));
}

function app_get_youtrack_project_template_key(): string
{
    $templateKey = trim((string) (defined('YT_PROJECT_TEMPLATE_SHORT_NAME') ? YT_PROJECT_TEMPLATE_SHORT_NAME : 'MLDP'));
    return $templateKey !== '' ? $templateKey : 'MLDP';
}

function app_get_youtrack_template_project_custom_fields(): array
{
    return app_get_youtrack_project_custom_fields(app_get_youtrack_project_template_key());
}

function app_build_youtrack_project_custom_field_create_payload(array $templateField): array
{
    $payload = [];
    $fieldId = trim((string) ($templateField['field']['id'] ?? ''));
    $type = trim((string) ($templateField['$type'] ?? ''));
    if ($fieldId !== '') {
        $payload['field'] = [
            'id' => $fieldId,
        ];
    }

    if ($type !== '') {
        $payload['$type'] = $type;
    }

    return $payload;
}

function app_build_youtrack_project_custom_field_update_payload(array $templateField): array
{
    $payload = [];

    if (array_key_exists('canBeEmpty', $templateField)) {
        $payload['canBeEmpty'] = !empty($templateField['canBeEmpty']);
    }

    if (array_key_exists('emptyFieldText', $templateField)) {
        $payload['emptyFieldText'] = (string) ($templateField['emptyFieldText'] ?? '');
    }

    if (array_key_exists('ordinal', $templateField) && is_numeric($templateField['ordinal'])) {
        $payload['ordinal'] = (int) $templateField['ordinal'];
    }

    return $payload;
}

function app_get_youtrack_bundle_element_type_for_project_field(string $projectFieldType): string
{
    $mapping = [
        'EnumProjectCustomField' => 'EnumBundleElement',
        'StateProjectCustomField' => 'StateBundleElement',
        'VersionProjectCustomField' => 'VersionBundleElement',
        'BuildProjectCustomField' => 'BuildBundleElement',
        'OwnedProjectCustomField' => 'OwnedBundleElement',
    ];

    return $mapping[$projectFieldType] ?? '';
}

function app_build_youtrack_bundle_value_sync_payload(array $templateValue, string $projectFieldType): array
{
    $name = trim((string) ($templateValue['name'] ?? ''));
    $bundleElementType = app_get_youtrack_bundle_element_type_for_project_field($projectFieldType);
    if ($name === '' || $bundleElementType === '') {
        return [];
    }

    $payload = [
        'name' => $name,
        '$type' => $bundleElementType,
    ];

    if ($bundleElementType === 'StateBundleElement') {
        $payload['isResolved'] = !empty($templateValue['isResolved']);
    }

    return $payload;
}

function app_sync_youtrack_project_custom_field_bundle_values(
    string $projectId,
    string $projectFieldId,
    array $templateField,
    array $projectField
): void {
    $projectFieldType = trim((string) ($projectField['$type'] ?? $templateField['$type'] ?? ''));
    $bundleElementType = app_get_youtrack_bundle_element_type_for_project_field($projectFieldType);
    if ($bundleElementType === '') {
        return;
    }

    $templateValues = is_array($templateField['bundle']['values'] ?? null) ? $templateField['bundle']['values'] : [];
    if ($templateValues === []) {
        return;
    }

    $projectValues = is_array($projectField['bundle']['values'] ?? null) ? $projectField['bundle']['values'] : [];
    $existingValueNames = [];
    foreach ($projectValues as $projectValue) {
        $existingName = trim((string) ($projectValue['name'] ?? ''));
        if ($existingName !== '') {
            $existingValueNames[mb_strtolower($existingName, 'UTF-8')] = true;
        }
    }

    foreach ($templateValues as $templateValue) {
        $templateName = trim((string) ($templateValue['name'] ?? ''));
        if ($templateName === '') {
            continue;
        }

        $normalizedTemplateName = mb_strtolower($templateName, 'UTF-8');
        if (isset($existingValueNames[$normalizedTemplateName])) {
            continue;
        }

        $payload = app_build_youtrack_bundle_value_sync_payload($templateValue, $projectFieldType);
        if ($payload === []) {
            continue;
        }

        app_youtrack_request(
            'POST',
            app_youtrack_base_url()
                . '/api/admin/projects/'
                . rawurlencode($projectId)
                . '/customFields/'
                . rawurlencode($projectFieldId)
                . '/bundle/values?fields='
                . rawurlencode('id,name'),
            $payload
        );

        $existingValueNames[$normalizedTemplateName] = true;
    }
}

function app_sync_youtrack_project_custom_fields_from_template(string $projectKey): void
{
    $normalizedProjectKey = trim($projectKey);
    if ($normalizedProjectKey === '') {
        throw new InvalidArgumentException('Projet YouTrack manquant pour l\'application du modÃ¨le de projet.');
    }

    $project = app_find_youtrack_project($normalizedProjectKey);
    $projectId = trim((string) ($project['id'] ?? ''));
    if ($projectId === '') {
        throw new RuntimeException('Projet YouTrack introuvable pour l\'application du modÃ¨le de projet.');
    }

    $templateFields = app_get_youtrack_template_project_custom_fields();
    $projectFields = app_get_youtrack_project_custom_fields($normalizedProjectKey);

    foreach ($templateFields as $fieldName => $templateField) {
        $normalizedFieldName = trim((string) $fieldName);
        if ($normalizedFieldName === '') {
            continue;
        }

        $templateFieldId = trim((string) ($templateField['field']['id'] ?? ''));
        $templateProjectFieldType = trim((string) ($templateField['$type'] ?? ''));
        if ($templateFieldId === '' || $templateProjectFieldType === '') {
            continue;
        }

        if (!isset($projectFields[$normalizedFieldName])) {
            try {
                app_youtrack_request(
                    'POST',
                    app_youtrack_base_url()
                        . '/api/admin/projects/'
                        . rawurlencode($projectId)
                        . '/customFields?fields='
                        . rawurlencode('id,$type,field(id,name,localizedName),bundle(id,name),canBeEmpty,emptyFieldText,ordinal'),
                    app_build_youtrack_project_custom_field_create_payload($templateField)
                );
            } catch (Throwable $throwable) {
                throw new RuntimeException(
                    'Impossible d\'ajouter le champ "' . $normalizedFieldName . '" depuis le modÃ¨le de projet : '
                    . $throwable->getMessage(),
                    0,
                    $throwable
                );
            }

            app_reset_youtrack_project_custom_fields_cache($normalizedProjectKey);
            $projectFields = app_get_youtrack_project_custom_fields($normalizedProjectKey);
        }

        $projectField = $projectFields[$normalizedFieldName] ?? null;
        $projectFieldId = trim((string) ($projectField['id'] ?? ''));
        if ($projectFieldId === '') {
            continue;
        }

        $payload = app_build_youtrack_project_custom_field_update_payload($templateField);
        if ($payload !== []) {
            try {
                app_youtrack_request(
                    'POST',
                    app_youtrack_base_url()
                        . '/api/admin/projects/'
                        . rawurlencode($projectId)
                        . '/customFields/'
                        . rawurlencode($projectFieldId)
                        . '?fields='
                        . rawurlencode('id,$type,field(id,name,localizedName),bundle(id,name),canBeEmpty,emptyFieldText,ordinal'),
                    $payload
                );
            } catch (Throwable $throwable) {
                throw new RuntimeException(
                    'Impossible d\'appliquer le modèle au champ "' . $normalizedFieldName . '" : '
                    . $throwable->getMessage(),
                    0,
                    $throwable
                );
            }
        }

        try {
            app_sync_youtrack_project_custom_field_bundle_values($projectId, $projectFieldId, $templateField, $projectField);
        } catch (Throwable $throwable) {
            throw new RuntimeException(
                'Impossible de synchroniser les valeurs du champ "' . $normalizedFieldName . '" depuis le modèle de projet : '
                . $throwable->getMessage(),
                0,
                $throwable
            );
        }
    }
    app_reset_youtrack_project_custom_fields_cache($normalizedProjectKey);
    app_reset_youtrack_project_tasks_cache($normalizedProjectKey);
}

function app_ensure_youtrack_project_assignee_field(string $projectKey): void
{
    $customFields = app_get_youtrack_project_custom_fields($projectKey);
    if (isset($customFields[YT_FIELD_ASSIGNEE])) {
        return;
    }

    $definition = app_find_youtrack_custom_field_definition(YT_FIELD_ASSIGNEE);
    if (!is_array($definition) || trim((string) ($definition['id'] ?? '')) === '') {
        throw new RuntimeException('Le champ YouTrack Assignee est introuvable.');
    }

    $project = app_find_youtrack_project($projectKey);
    app_youtrack_request(
        'POST',
        app_youtrack_base_url()
            . '/api/admin/projects/'
            . rawurlencode((string) $project['id'])
            . '/customFields?fields=id,field(name),$type',
        [
            'field' => [
                'id' => trim((string) $definition['id']),
            ],
            '$type' => 'UserProjectCustomField',
        ]
    );

    app_reset_youtrack_project_custom_fields_cache($projectKey);
    app_reset_youtrack_project_tasks_cache($projectKey);
}

function app_list_youtrack_project_state_options(string $projectKey): array
{
    $customFields = app_get_youtrack_project_custom_fields($projectKey);
    $stateField = $customFields['State'] ?? null;
    $options = [];

    foreach (($stateField['bundle']['values'] ?? []) as $value) {
        if (!is_array($value)) {
            continue;
        }

        $name = trim((string) ($value['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $options[] = [
            'id' => trim((string) ($value['id'] ?? '')),
            'name' => $name,
            'isResolved' => !empty($value['isResolved']),
            'colorId' => trim((string) ($value['color']['id'] ?? '')),
        ];
    }

    return $options;
}

function app_is_displayable_youtrack_task_custom_field(array $field): bool
{
    $fieldName = trim((string) ($field['field']['name'] ?? ''));
    if ($fieldName === '') {
        return false;
    }

    $excludedFieldNames = [
        'State',
        YT_FIELD_ASSIGNEE,
        defined('YT_FIELD_DUE_DATE') ? YT_FIELD_DUE_DATE : 'Date Ã©chÃ©ance',
    ];

    foreach ($excludedFieldNames as $excludedFieldName) {
        if (strcasecmp($fieldName, (string) $excludedFieldName) === 0) {
            return false;
        }
    }

    return true;
}

function app_build_youtrack_task_custom_field_column_key(array $field): string
{
    $fieldId = trim((string) ($field['field']['id'] ?? ''));
    if ($fieldId !== '') {
        return 'cf__' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $fieldId);
    }

    $fieldName = trim((string) ($field['field']['name'] ?? ''));
    if ($fieldName === '') {
        return '';
    }

    $slug = preg_replace('/[^A-Za-z0-9_-]+/', '_', $fieldName);
    $slug = trim((string) $slug, '_');
    return $slug !== '' ? 'cf__' . $slug : '';
}

function app_map_youtrack_project_field_to_issue_field_type(string $projectFieldType): string
{
    $mapping = [
        'EnumProjectCustomField' => 'SingleEnumIssueCustomField',
        'OwnedProjectCustomField' => 'SingleOwnedIssueCustomField',
        'UserProjectCustomField' => 'SingleUserIssueCustomField',
        'StateProjectCustomField' => 'StateIssueCustomField',
        'PeriodProjectCustomField' => 'PeriodIssueCustomField',
        'SimpleProjectCustomField' => 'SimpleIssueCustomField',
    ];

    return $mapping[$projectFieldType] ?? '';
}

function app_get_youtrack_task_custom_field_input_kind(array $field): string
{
    $projectFieldType = trim((string) ($field['$type'] ?? ''));

    if (in_array($projectFieldType, ['EnumProjectCustomField', 'OwnedProjectCustomField', 'UserProjectCustomField', 'StateProjectCustomField'], true)) {
        return 'select';
    }

    if (in_array($projectFieldType, ['PeriodProjectCustomField', 'SimpleProjectCustomField'], true)) {
        return 'text';
    }

    return '';
}

function app_build_youtrack_task_custom_field_options(array $field): array
{
    $options = [];

    foreach (($field['bundle']['values'] ?? []) as $value) {
        if (!is_array($value)) {
            continue;
        }

        $name = trim((string) ($value['name'] ?? $value['presentation'] ?? ''));
        if ($name === '') {
            continue;
        }

        $options[] = [
            'id' => trim((string) ($value['id'] ?? '')),
            'name' => $name,
            'presentation' => trim((string) ($value['presentation'] ?? $name)),
            'colorId' => trim((string) ($value['color']['id'] ?? '')),
        ];
    }

    foreach (($field['bundle']['aggregatedUsers'] ?? []) as $user) {
        if (!is_array($user)) {
            continue;
        }

        $name = trim((string) ($user['name'] ?? $user['fullName'] ?? $user['login'] ?? ''));
        if ($name === '') {
            continue;
        }

        $options[] = [
            'id' => trim((string) ($user['id'] ?? '')),
            'name' => $name,
            'presentation' => $name,
            'login' => trim((string) ($user['login'] ?? '')),
        ];
    }

    return $options;
}

function app_get_youtrack_task_custom_field_columns(string $projectKey): array
{
    $templateFields = app_get_youtrack_template_project_custom_fields();
    $projectFields = app_get_youtrack_project_custom_fields($projectKey);
    $columns = [];

    foreach ($templateFields as $fieldName => $templateField) {
        $sourceField = is_array($projectFields[$fieldName] ?? null) ? $projectFields[$fieldName] : $templateField;
        if (!app_is_displayable_youtrack_task_custom_field($sourceField)) {
            continue;
        }

        $key = app_build_youtrack_task_custom_field_column_key($sourceField);
        if ($key === '') {
            continue;
        }

        $label = trim((string) ($sourceField['field']['localizedName'] ?? $templateField['field']['localizedName'] ?? $fieldName));
        if ($label === '') {
            $label = trim((string) ($sourceField['field']['name'] ?? $fieldName));
        }

        $sourceOptionsField = is_array($sourceField['bundle'] ?? null) ? $sourceField : $templateField;
        $projectFieldType = trim((string) ($sourceField['$type'] ?? $templateField['$type'] ?? ''));

        $columns[$key] = [
            'key' => $key,
            'fieldId' => trim((string) ($sourceField['field']['id'] ?? $templateField['field']['id'] ?? '')),
            'fieldName' => trim((string) ($sourceField['field']['name'] ?? $fieldName)),
            'label' => $label !== '' ? $label : trim((string) $fieldName),
            'type' => $projectFieldType,
            'issueType' => app_map_youtrack_project_field_to_issue_field_type($projectFieldType),
            'inputKind' => app_get_youtrack_task_custom_field_input_kind($sourceOptionsField),
            'emptyFieldText' => trim((string) ($sourceField['emptyFieldText'] ?? $templateField['emptyFieldText'] ?? '')),
            'options' => app_build_youtrack_task_custom_field_options($sourceOptionsField),
            'ordinal' => is_numeric($templateField['ordinal'] ?? null) ? (int) $templateField['ordinal'] : 0,
        ];
    }

    $columns = array_values($columns);
    usort($columns, static function (array $left, array $right): int {
        $leftOrdinal = (int) ($left['ordinal'] ?? 0);
        $rightOrdinal = (int) ($right['ordinal'] ?? 0);
        if ($leftOrdinal !== $rightOrdinal) {
            return $leftOrdinal <=> $rightOrdinal;
        }

        return strcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
    });

    return $columns;
}

function app_list_youtrack_project_team(string $projectKey): array
{
    $normalizedProjectKey = trim($projectKey);
    if ($normalizedProjectKey === '') {
        throw new InvalidArgumentException('Projet YouTrack manquant.');
    }

    return app_cache_remember(
        'youtrack.project-team',
        app_youtrack_project_cache_key($normalizedProjectKey),
        45,
        static function () use ($normalizedProjectKey): array {
            $project = app_find_youtrack_project($normalizedProjectKey);
            $projectRingId = trim((string) ($project['ringId'] ?? ''));
            if ($projectRingId === '') {
                throw new RuntimeException('Identifiant Hub du projet introuvable : ' . $normalizedProjectKey);
            }

            $response = app_youtrack_request(
                'GET',
                app_youtrack_hub_base_url()
                    . '/projects/'
                    . rawurlencode($projectRingId)
                    . '/team/users?fields=id,login,name&$top=200'
            );

            $usersByRingId = app_get_youtrack_standard_users_by_ring_id();
            $teamItems = is_array($response['users'] ?? null) ? $response['users'] : [];
            $teamMembers = [];

            foreach ($teamItems as $teamItem) {
                if (!is_array($teamItem)) {
                    continue;
                }

                $ringId = trim((string) ($teamItem['id'] ?? ''));
                if ($ringId === '') {
                    continue;
                }

                $matchedUser = $usersByRingId[$ringId] ?? null;
                $displayName = trim((string) ($matchedUser['displayName'] ?? $teamItem['name'] ?? $teamItem['login'] ?? $ringId));

                $teamMembers[$ringId] = [
                    'id' => $ringId,
                    'ringId' => $ringId,
                    'youtrackId' => trim((string) ($matchedUser['youtrackId'] ?? '')),
                    'displayName' => $displayName !== '' ? $displayName : $ringId,
                    'login' => trim((string) ($matchedUser['login'] ?? $teamItem['login'] ?? '')),
                    'email' => trim((string) ($matchedUser['email'] ?? '')),
                ];
            }

            return array_values($teamMembers);
        },
        [
            'allowStaleOnError' => true,
            'staleTtl' => 300,
        ]
    );
}

function app_normalize_youtrack_user_match_value($value): string
{
    $normalized = trim((string) $value);
    if ($normalized === '') {
        return '';
    }

    $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    return mb_strtolower($normalized, 'UTF-8');
}

function app_is_youtrack_team_member_matching_dashboard_user(array $teamMember, array $dashboardUser): bool
{
    $memberEmail = app_normalize_youtrack_user_match_value($teamMember['email'] ?? '');
    $memberLogin = app_normalize_youtrack_user_match_value($teamMember['login'] ?? '');
    $memberDisplayName = app_normalize_youtrack_user_match_value($teamMember['displayName'] ?? '');

    $userEmail = app_normalize_youtrack_user_match_value($dashboardUser['email'] ?? '');
    $userLogin = app_normalize_youtrack_user_match_value($dashboardUser['username'] ?? $dashboardUser['id'] ?? '');
    $userDisplayName = app_normalize_youtrack_user_match_value($dashboardUser['displayName'] ?? '');

    if ($userEmail !== '' && $memberEmail !== '' && $userEmail === $memberEmail) {
        return true;
    }

    if ($userLogin !== '' && $memberLogin !== '' && $userLogin === $memberLogin) {
        return true;
    }

    return $userDisplayName !== '' && $memberDisplayName !== '' && $userDisplayName === $memberDisplayName;
}

function app_user_can_manage_youtrack_project_tasks(string $projectKey, ?array $dashboardUser = null): bool
{
    if (!is_array($dashboardUser)) {
        return false;
    }

    $teamMembers = app_list_youtrack_project_team($projectKey);
    if ($teamMembers === []) {
        return false;
    }

    foreach ($teamMembers as $teamMember) {
        if (app_is_youtrack_team_member_matching_dashboard_user($teamMember, $dashboardUser)) {
            return true;
        }
    }

    return false;
}

function app_add_youtrack_user_to_project_team(string $projectKey, string $userRingId): array
{
    $project = app_find_youtrack_project($projectKey);
    $projectRingId = trim((string) ($project['ringId'] ?? ''));
    $normalizedUserRingId = trim($userRingId);

    if ($projectRingId === '' || $normalizedUserRingId === '') {
        throw new InvalidArgumentException('Projet ou utilisateur YouTrack manquant pour la mise Ã  jour de l\'Ã©quipe.');
    }

    app_ensure_youtrack_project_assignee_field($projectKey);

    app_youtrack_request(
        'POST',
        app_youtrack_hub_base_url()
            . '/projects/'
            . rawurlencode($projectRingId)
            . '/team/users?fields=id,name,login',
        [
            'id' => $normalizedUserRingId,
        ]
    );

    app_reset_youtrack_project_team_cache($projectKey);
    app_reset_youtrack_project_custom_fields_cache($projectKey);
    app_reset_youtrack_project_tasks_cache($projectKey);

    return app_list_youtrack_project_team($projectKey);
}

function app_remove_youtrack_user_from_project_team(string $projectKey, string $userRingId): array
{
    $project = app_find_youtrack_project($projectKey);
    $projectRingId = trim((string) ($project['ringId'] ?? ''));
    $normalizedUserRingId = trim($userRingId);

    if ($projectRingId === '' || $normalizedUserRingId === '') {
        throw new InvalidArgumentException('Projet ou utilisateur YouTrack manquant pour la mise Ã  jour de l\'Ã©quipe.');
    }

    app_youtrack_request(
        'DELETE',
        app_youtrack_hub_base_url()
            . '/projects/'
            . rawurlencode($projectRingId)
            . '/team/users/'
            . rawurlencode($normalizedUserRingId)
    );

    app_reset_youtrack_project_team_cache($projectKey);
    app_reset_youtrack_project_custom_fields_cache($projectKey);
    app_reset_youtrack_project_tasks_cache($projectKey);

    return app_list_youtrack_project_team($projectKey);
}

function app_sync_youtrack_project_team(string $projectKey, array $members): array
{
    $targetRingIds = [];
    foreach ($members as $member) {
        if (!is_array($member)) {
            continue;
        }

        $ringId = trim((string) ($member['ringId'] ?? $member['id'] ?? ''));
        if ($ringId === '') {
            continue;
        }

        $targetRingIds[$ringId] = $ringId;
    }

    $currentTeam = app_list_youtrack_project_team($projectKey);
    $currentRingIds = [];
    foreach ($currentTeam as $member) {
        $ringId = trim((string) ($member['ringId'] ?? $member['id'] ?? ''));
        if ($ringId === '') {
            continue;
        }

        $currentRingIds[$ringId] = $ringId;
    }

    foreach (array_diff(array_values($targetRingIds), array_values($currentRingIds)) as $ringId) {
        app_add_youtrack_user_to_project_team($projectKey, $ringId);
    }

    foreach (array_diff(array_values($currentRingIds), array_values($targetRingIds)) as $ringId) {
        app_remove_youtrack_user_from_project_team($projectKey, $ringId);
    }

    return app_list_youtrack_project_team($projectKey);
}

function app_resolve_youtrack_assignee_id(array $task): string
{
    $assigneeId = trim((string) ($task['assigneeId'] ?? ''));
    if ($assigneeId !== '') {
        return $assigneeId;
    }

    $assigneeRingId = trim((string) ($task['assigneeRingId'] ?? ''));
    if ($assigneeRingId !== '') {
        $usersByRingId = app_get_youtrack_standard_users_by_ring_id();
        if (!empty($usersByRingId[$assigneeRingId]['youtrackId'])) {
            return trim((string) $usersByRingId[$assigneeRingId]['youtrackId']);
        }
    }

    $dashboardUserId = trim((string) ($task['assigneeUserId'] ?? ''));
    if ($dashboardUserId === '') {
        return '';
    }

    $dashboardUser = app_fetch_standard_project_user_by_id($dashboardUserId);
    if ($dashboardUser === null) {
        return '';
    }

    foreach ([
        (string) ($dashboardUser['email'] ?? ''),
        (string) ($dashboardUser['id'] ?? ''),
        (string) ($dashboardUser['displayName'] ?? ''),
    ] as $candidate) {
        $youTrackUserId = app_find_youtrack_user_id($candidate);
        if ($youTrackUserId !== '') {
            return $youTrackUserId;
        }
    }

    return '';
}

function app_normalize_youtrack_due_date_value($value): ?int
{
    $normalized = trim((string) $value);
    if ($normalized === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $normalized);
    if (!$date instanceof DateTimeImmutable) {
        return null;
    }

    $date = $date->setTime(12, 0, 0);
    return ((int) $date->format('U')) * 1000;
}

function app_resolve_youtrack_leader_id(?array $dashboardUser = null): string
{
    $configuredLeaderId = defined('YT_DEFAULT_PROJECT_LEADER_ID')
        ? trim((string) YT_DEFAULT_PROJECT_LEADER_ID)
        : '';
    if ($configuredLeaderId !== '') {
        return $configuredLeaderId;
    }

    $candidates = [];

    $configuredLeaderLogin = defined('YT_DEFAULT_PROJECT_LEADER_LOGIN')
        ? trim((string) YT_DEFAULT_PROJECT_LEADER_LOGIN)
        : '';
    if ($configuredLeaderLogin !== '') {
        $candidates[] = $configuredLeaderLogin;
    }

    $configuredLeaderEmail = defined('YT_DEFAULT_PROJECT_LEADER_EMAIL')
        ? trim((string) YT_DEFAULT_PROJECT_LEADER_EMAIL)
        : '';
    if ($configuredLeaderEmail !== '') {
        $candidates[] = $configuredLeaderEmail;
    }

    if (is_array($dashboardUser)) {
        $email = trim((string) ($dashboardUser['email'] ?? ''));
        $userId = trim((string) ($dashboardUser['id'] ?? ''));
        $firstName = trim((string) ($dashboardUser['prenom'] ?? ''));
        $lastName = trim((string) ($dashboardUser['nom'] ?? ''));
        $fullName = trim($firstName . ' ' . $lastName);

        if ($email !== '') {
            $candidates[] = $email;
        }
        if ($userId !== '') {
            $candidates[] = $userId;
        }
        if ($fullName !== '') {
            $candidates[] = $fullName;
        }
    }

    foreach (array_values(array_unique($candidates)) as $candidate) {
        $leaderId = app_find_youtrack_user_id($candidate);
        if ($leaderId !== '') {
            return $leaderId;
        }
    }

    try {
        $currentYouTrackUser = app_youtrack_request(
            'GET',
            app_youtrack_base_url() . '/api/users/me?fields=id,login,name'
        );
        $currentUserId = trim((string) ($currentYouTrackUser['id'] ?? ''));
        if ($currentUserId !== '') {
            return $currentUserId;
        }
    } catch (Throwable $throwable) {
        // Ignore this fallback and keep the original resolution flow silent.
    }

    return '';
}

function app_find_youtrack_organization(string $organizationName): array
{
    $normalizedName = trim($organizationName);
    if ($normalizedName === '') {
        throw new InvalidArgumentException('Le nom de l\'organisation YouTrack est manquant.');
    }

    return app_cache_remember(
        'youtrack.organization',
        app_normalize_youtrack_cache_key($normalizedName),
        1800,
        static function () use ($normalizedName): array {
            $organizations = app_youtrack_request(
                'GET',
                app_youtrack_hub_base_url() . '/organizations?fields=id,name,aliases&$top=100'
            );

            $organizationItems = [];
            if (is_array($organizations)) {
                if (isset($organizations['organizations']) && is_array($organizations['organizations'])) {
                    $organizationItems = $organizations['organizations'];
                } else {
                    $organizationItems = $organizations;
                }
            }

            if ($organizationItems === []) {
                throw new RuntimeException('Organisation YouTrack introuvable : ' . $normalizedName);
            }

            foreach ($organizationItems as $organization) {
                $organizationId = trim((string) ($organization['id'] ?? ''));
                $name = trim((string) ($organization['name'] ?? ''));

                if ($organizationId === '') {
                    continue;
                }

                if (strcasecmp($name, $normalizedName) === 0) {
                    return [
                        'id' => $organizationId,
                        'name' => $name,
                    ];
                }

                foreach (($organization['aliases'] ?? []) as $alias) {
                    $aliasName = trim((string) ($alias['value'] ?? $alias['name'] ?? ''));
                    if ($aliasName !== '' && strcasecmp($aliasName, $normalizedName) === 0) {
                        return [
                            'id' => $organizationId,
                            'name' => $name !== '' ? $name : $normalizedName,
                        ];
                    }
                }
            }

            throw new RuntimeException('Organisation YouTrack introuvable : ' . $normalizedName);
        },
        [
            'allowStaleOnError' => true,
            'staleTtl' => 86400,
        ]
    );

    $organizations = app_youtrack_request(
        'GET',
        app_youtrack_hub_base_url() . '/organizations?fields=id,name,aliases&$top=100'
    );

    $organizationItems = [];
    if (is_array($organizations)) {
        if (isset($organizations['organizations']) && is_array($organizations['organizations'])) {
            $organizationItems = $organizations['organizations'];
        } else {
            $organizationItems = $organizations;
        }
    }

    if ($organizationItems === []) {
        throw new RuntimeException('Organisation YouTrack introuvable : ' . $normalizedName);
    }

    foreach ($organizationItems as $organization) {
        $organizationId = trim((string) ($organization['id'] ?? ''));
        $name = trim((string) ($organization['name'] ?? ''));

        if ($organizationId === '') {
            continue;
        }

        if (strcasecmp($name, $normalizedName) === 0) {
            return [
                'id' => $organizationId,
                'name' => $name,
            ];
        }

        foreach (($organization['aliases'] ?? []) as $alias) {
            $aliasName = trim((string) ($alias['value'] ?? $alias['name'] ?? ''));
            if ($aliasName !== '' && strcasecmp($aliasName, $normalizedName) === 0) {
                return [
                    'id' => $organizationId,
                    'name' => $name !== '' ? $name : $normalizedName,
                ];
            }
        }
    }

    throw new RuntimeException('Organisation YouTrack introuvable : ' . $normalizedName);
}

function app_find_youtrack_hub_project(string $projectKey): array
{
    $normalizedProjectKey = trim($projectKey);
    if ($normalizedProjectKey === '') {
        throw new InvalidArgumentException('La clÃ© du projet Hub est manquante.');
    }

    return app_cache_remember(
        'youtrack.hub-project',
        app_youtrack_project_cache_key($normalizedProjectKey),
        300,
        static function () use ($normalizedProjectKey): array {
            $queries = [
                'key: ' . $normalizedProjectKey,
                $normalizedProjectKey,
            ];

            foreach ($queries as $query) {
                $projects = app_youtrack_request(
                    'GET',
                    app_youtrack_hub_base_url()
                        . '/projects?fields=id,key,name,organization(id,name)&query='
                        . rawurlencode($query)
                        . '&$top=20'
                );

                $projectItems = [];
                if (is_array($projects)) {
                    if (isset($projects['projects']) && is_array($projects['projects'])) {
                        $projectItems = $projects['projects'];
                    } else {
                        $projectItems = $projects;
                    }
                }

                foreach ($projectItems as $project) {
                    $projectId = trim((string) ($project['id'] ?? ''));
                    $projectHubKey = trim((string) ($project['key'] ?? ''));
                    $projectName = trim((string) ($project['name'] ?? ''));

                    if ($projectId === '') {
                        continue;
                    }

                    if (
                        strcasecmp($projectHubKey, $normalizedProjectKey) === 0 ||
                        strcasecmp($projectName, $normalizedProjectKey) === 0
                    ) {
                        return [
                            'id' => $projectId,
                            'key' => $projectHubKey,
                            'name' => $projectName,
                            'organization' => is_array($project['organization'] ?? null) ? $project['organization'] : null,
                        ];
                    }
                }
            }

            throw new RuntimeException('Projet Hub introuvable : ' . $normalizedProjectKey);
        },
        [
            'allowStaleOnError' => true,
            'staleTtl' => 1800,
        ]
    );

    $queries = [
        'key: ' . $normalizedProjectKey,
        $normalizedProjectKey,
    ];

    foreach ($queries as $query) {
        $projects = app_youtrack_request(
            'GET',
            app_youtrack_hub_base_url()
                . '/projects?fields=id,key,name,organization(id,name)&query='
                . rawurlencode($query)
                . '&$top=20'
        );

        $projectItems = [];
        if (is_array($projects)) {
            if (isset($projects['projects']) && is_array($projects['projects'])) {
                $projectItems = $projects['projects'];
            } else {
                $projectItems = $projects;
            }
        }

        foreach ($projectItems as $project) {
            $projectId = trim((string) ($project['id'] ?? ''));
            $projectHubKey = trim((string) ($project['key'] ?? ''));
            $projectName = trim((string) ($project['name'] ?? ''));

            if ($projectId === '') {
                continue;
            }

            if (
                strcasecmp($projectHubKey, $normalizedProjectKey) === 0 ||
                strcasecmp($projectName, $normalizedProjectKey) === 0
            ) {
                return [
                    'id' => $projectId,
                    'key' => $projectHubKey,
                    'name' => $projectName,
                    'organization' => is_array($project['organization'] ?? null) ? $project['organization'] : null,
                ];
            }
        }
    }

    throw new RuntimeException('Projet Hub introuvable : ' . $normalizedProjectKey);
}

function app_assign_youtrack_project_to_organization(string $projectKey, string $organizationName): void
{
    $normalizedProjectKey = trim($projectKey);
    if ($normalizedProjectKey === '') {
        throw new InvalidArgumentException('Identifiant projet YouTrack manquant pour le rattachement Ã  l\'organisation.');
    }

    $organization = app_find_youtrack_organization($organizationName);
    $hubProject = app_find_youtrack_hub_project($normalizedProjectKey);
    $currentOrganizationName = trim((string) (($hubProject['organization']['name'] ?? '')));
    if ($currentOrganizationName !== '' && strcasecmp($currentOrganizationName, $organization['name']) === 0) {
        return;
    }

    app_youtrack_request(
        'POST',
        app_youtrack_hub_base_url()
            . '/organizations/'
            . rawurlencode($organization['id'])
            . '/projects?fields=id,name',
        [
            'id' => $hubProject['id'],
        ]
    );

    app_reset_youtrack_project_record_cache($normalizedProjectKey);
}

function app_finalize_gantt_youtrack_project(string $projectKey): void
{
    app_assign_youtrack_project_to_organization($projectKey, 'PROJETS');
    app_sync_youtrack_project_custom_fields_from_template($projectKey);
    app_ensure_youtrack_project_assignee_field($projectKey);
}

function app_restore_or_update_gantt_youtrack_project(array $existingProject, array $project, string $leaderId): array
{
    $title = trim((string) ($project['title'] ?? ''));
    $ref = trim((string) ($project['ref'] ?? ''));
    $description = trim((string) ($project['description'] ?? ''));
    $projectId = trim((string) ($existingProject['id'] ?? ''));
    $projectKey = trim((string) ($existingProject['shortName'] ?? $ref));

    if ($projectId === '' || $projectKey === '') {
        throw new RuntimeException('Projet YouTrack existant introuvable pour la restauration.');
    }

    $payload = [
        'name' => $title,
        'description' => $description,
        'leader' => [
            'id' => $leaderId,
        ],
    ];

    if (!empty($existingProject['archived'])) {
        $payload['archived'] = false;
    }

    $updatedProject = app_youtrack_request(
        'POST',
        app_youtrack_base_url()
            . '/api/admin/projects/'
            . rawurlencode($projectId)
            . '?fields='
            . rawurlencode('id,shortName,name,leader(id,login,name),description,archived'),
        $payload
    );

    app_reset_youtrack_project_record_cache($projectKey);
    app_reset_youtrack_project_team_cache($projectKey);
    app_reset_youtrack_project_custom_fields_cache($projectKey);
    app_reset_youtrack_project_tasks_cache($projectKey);

    try {
        app_finalize_gantt_youtrack_project((string) ($updatedProject['shortName'] ?? $projectKey));
    } catch (Throwable $throwable) {
        $restoredShortName = trim((string) ($updatedProject['shortName'] ?? $projectKey));
        throw new RuntimeException(
            'Le projet YouTrack existant (' . $restoredShortName . ') a bien Ã©tÃ© retrouvÃ©, mais n\'a pas pu Ãªtre rattachÃ© Ã  l\'organisation PROJETS : '
            . $throwable->getMessage()
            . '. VÃ©rifiez dans YouTrack avant de relancer la crÃ©ation.'
        );
    }

    return [
        'id' => trim((string) ($updatedProject['id'] ?? $projectId)),
        'shortName' => trim((string) ($updatedProject['shortName'] ?? $projectKey)),
        'name' => trim((string) ($updatedProject['name'] ?? $title)),
        'response' => $updatedProject,
        'url' => null,
    ];
}

function app_create_gantt_youtrack_project(array $project, ?array $dashboardUser = null): array
{
    $title = trim((string) ($project['title'] ?? ''));
    $ref = trim((string) ($project['ref'] ?? ''));
    $description = trim((string) ($project['description'] ?? ''));

    if ($title === '') {
        throw new InvalidArgumentException('Le titre est obligatoire pour crÃ©er le projet dans YouTrack.');
    }

    if ($ref === '') {
        throw new InvalidArgumentException('L\'identifiant est obligatoire pour crÃ©er le projet dans YouTrack.');
    }

    if ($description === '') {
        throw new InvalidArgumentException('La description est obligatoire pour crÃ©er le projet dans YouTrack.');
    }

    $leaderId = app_resolve_youtrack_leader_id($dashboardUser);
    if ($leaderId === '') {
        throw new RuntimeException('Impossible de dÃ©terminer le responsable YouTrack du projet. VÃ©rifiez le mapping de l\'utilisateur courant avec un compte YouTrack.');
    }

    $existingProject = app_find_youtrack_project_or_null($ref);
    if ($existingProject !== null) {
        return app_restore_or_update_gantt_youtrack_project($existingProject, $project, $leaderId);
    }

    $payload = [
        'name' => $title,
        'shortName' => $ref,
        'description' => $description,
        'leader' => [
            'id' => $leaderId,
        ],
    ];

    try {
        $createdProject = app_youtrack_request(
            'POST',
            app_youtrack_base_url() . '/api/admin/projects?fields=id,shortName,name,leader(id,login,name),description',
            $payload
        );
    } catch (RuntimeException $exception) {
        $message = $exception->getMessage();
        if (stripos($message, 'not unique') !== false || stripos($message, 'n\'est pas unique') !== false) {
            $existingProject = app_find_youtrack_project_or_null($ref);
            if ($existingProject !== null) {
                return app_restore_or_update_gantt_youtrack_project($existingProject, $project, $leaderId);
            }

            throw new RuntimeException(
                'Un projet YouTrack avec l\'identifiant ' . $ref . ' existe dÃ©jÃ , mais il n\'a pas pu Ãªtre restaurÃ© automatiquement. VÃ©rifiez son Ã©tat dans YouTrack avant de relancer.'
            );
        }

        throw $exception;
    }

    $createdProjectId = trim((string) ($createdProject['id'] ?? ''));
    app_reset_youtrack_project_record_cache((string) ($createdProject['shortName'] ?? $ref));
    if ($createdProjectId === '') {
        throw new RuntimeException('Projet YouTrack crÃ©Ã© mais identifiant non retournÃ© par l\'API.');
    }

    try {
        app_finalize_gantt_youtrack_project((string) ($createdProject['shortName'] ?? $ref));
    } catch (Throwable $throwable) {
        $createdShortName = trim((string) ($createdProject['shortName'] ?? $ref));
        throw new RuntimeException(
            'Le projet YouTrack a Ã©tÃ© crÃ©Ã© (' . $createdShortName . ') mais n\'a pas pu Ãªtre rattachÃ© Ã  l\'organisation PROJETS : '
            . $throwable->getMessage()
            . '. VÃ©rifiez dans YouTrack avant de relancer la crÃ©ation.'
        );
    }

    return [
        'id' => $createdProjectId,
        'shortName' => trim((string) ($createdProject['shortName'] ?? $ref)),
        'name' => trim((string) ($createdProject['name'] ?? $title)),
        'response' => $createdProject,
        'url' => null,
    ];
}

function app_list_youtrack_project_assignees(string $projectKey): array
{
    $normalizedProjectKey = trim($projectKey);
    if ($normalizedProjectKey === '') {
        return [];
    }

    $customFields = app_get_youtrack_project_custom_fields($normalizedProjectKey);
    $assignees = [];
    foreach ($customFields as $customField) {
        if (($customField['field']['name'] ?? '') !== YT_FIELD_ASSIGNEE) {
            continue;
        }

        foreach (($customField['bundle']['aggregatedUsers'] ?? []) as $user) {
            $userId = trim((string) ($user['id'] ?? ''));
            $login = trim((string) ($user['login'] ?? ''));
            $name = trim((string) ($user['fullName'] ?? $user['name'] ?? $login));

            if ($userId === '') {
                continue;
            }

            $assignees[$userId] = [
                'id' => $userId,
                'login' => $login,
                'name' => $name !== '' ? $name : $login,
            ];
        }

        break;
    }

    $assignees = array_values($assignees);
    usort($assignees, static function (array $left, array $right): int {
        return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
    });

    return $assignees;
}

function app_create_youtrack_project_task(string $projectKey, array $task): array
{
    $normalizedProjectKey = trim($projectKey);
    $summary = trim((string) ($task['summary'] ?? ''));
    $description = trim((string) ($task['description'] ?? ''));
    $assigneeId = app_resolve_youtrack_assignee_id($task);
    $dueDate = app_normalize_youtrack_due_date_value($task['dueDate'] ?? null);
    $state = trim((string) ($task['state'] ?? ''));
    $customFieldValues = is_array($task['customFieldValues'] ?? null) ? $task['customFieldValues'] : [];

    if ($normalizedProjectKey === '') {
        throw new InvalidArgumentException('Projet YouTrack manquant.');
    }

    if ($summary === '') {
        throw new InvalidArgumentException('Le rÃ©sumÃ© de la tÃ¢che est obligatoire.');
    }

    $project = app_find_youtrack_project($normalizedProjectKey);
    $payload = [
        'project' => [
            'id' => $project['id'],
            'shortName' => $project['shortName'],
        ],
        'summary' => $summary,
    ];

    if ($description !== '') {
        $payload['description'] = $description;
    }

    $customFields = [];

    if ($assigneeId !== '') {
        app_ensure_youtrack_project_assignee_field($normalizedProjectKey);
        $customFields[] = [
            'name' => YT_FIELD_ASSIGNEE,
            '$type' => 'SingleUserIssueCustomField',
            'value' => [
                'id' => $assigneeId,
                '$type' => 'User',
            ],
        ];
    }

    if ($dueDate !== null) {
        $customFields[] = [
            'name' => defined('YT_FIELD_DUE_DATE') ? YT_FIELD_DUE_DATE : 'Date Ã©chÃ©ance',
            '$type' => 'DateIssueCustomField',
            'value' => $dueDate,
        ];
    }

    if ($state !== '') {
        $customFields[] = [
            'name' => 'State',
            '$type' => 'StateIssueCustomField',
            'value' => [
                'name' => $state,
            ],
        ];
    }

    foreach ($customFieldValues as $customFieldKey => $customFieldValue) {
        $normalizedCustomFieldKey = trim((string) $customFieldKey);
        if ($normalizedCustomFieldKey === '') {
            continue;
        }

        $customFields[] = app_build_youtrack_task_custom_field_update_entry(
            $normalizedProjectKey,
            $normalizedCustomFieldKey,
            $customFieldValue
        );
    }

    if ($customFields !== []) {
        $payload['customFields'] = $customFields;
    }

    $createdIssue = app_youtrack_request(
        'POST',
        app_youtrack_base_url() . '/api/issues?fields=id,idReadable,summary,project(shortName)',
        $payload
    );

    app_reset_youtrack_project_tasks_cache($normalizedProjectKey);

    return [
        'id' => trim((string) ($createdIssue['id'] ?? '')),
        'idReadable' => trim((string) ($createdIssue['idReadable'] ?? '')),
        'summary' => trim((string) ($createdIssue['summary'] ?? $summary)),
        'project' => trim((string) ($createdIssue['project']['shortName'] ?? $project['shortName'])),
        'url' => !empty($createdIssue['idReadable']) ? app_build_youtrack_issue_url((string) $createdIssue['idReadable']) : null,
    ];
}

function app_find_youtrack_task_custom_field_column(string $projectKey, string $columnKey): ?array
{
    $normalizedColumnKey = trim($columnKey);
    if ($normalizedColumnKey === '') {
        return null;
    }

    foreach (app_get_youtrack_task_custom_field_columns($projectKey) as $column) {
        if (trim((string) ($column['key'] ?? '')) === $normalizedColumnKey) {
            return $column;
        }
    }

    return null;
}

function app_build_youtrack_task_custom_field_update_entry(string $projectKey, string $columnKey, $value): array
{
    $column = app_find_youtrack_task_custom_field_column($projectKey, $columnKey);
    if (!is_array($column)) {
        throw new InvalidArgumentException('Colonne de tâche YouTrack inconnue : ' . $columnKey);
    }

    $fieldName = trim((string) ($column['fieldName'] ?? ''));
    $issueFieldType = trim((string) ($column['issueType'] ?? ''));
    if ($fieldName === '' || $issueFieldType === '') {
        throw new InvalidArgumentException('Le champ "' . ($column['label'] ?? $columnKey) . '" ne peut pas être modifié depuis le tableau.');
    }

    $normalizedValue = trim((string) $value);
    $payloadValue = null;

    if ($issueFieldType === 'PeriodIssueCustomField') {
        if ($normalizedValue !== '') {
            $payloadValue = [
                '$type' => 'PeriodValue',
                'presentation' => $normalizedValue,
            ];
        }
    } elseif (
        $issueFieldType === 'SingleEnumIssueCustomField'
        || $issueFieldType === 'SingleOwnedIssueCustomField'
        || $issueFieldType === 'SingleUserIssueCustomField'
        || $issueFieldType === 'StateIssueCustomField'
    ) {
        if ($normalizedValue !== '') {
            $payloadValue = [
                'name' => $normalizedValue,
            ];
        }
    } else {
        $payloadValue = $normalizedValue !== '' ? $normalizedValue : null;
    }

    return [
        'name' => $fieldName,
        '$type' => $issueFieldType,
        'value' => $payloadValue,
    ];
}

function app_update_youtrack_project_task(string $projectKey, string $issueId, array $updates): array
{
    $normalizedProjectKey = trim($projectKey);
    $normalizedIssueId = trim($issueId);

    if ($normalizedProjectKey === '') {
        throw new InvalidArgumentException('Projet YouTrack manquant.');
    }

    if ($normalizedIssueId === '') {
        throw new InvalidArgumentException('TÃ¢che YouTrack manquante.');
    }

    $payload = [];
    $summary = array_key_exists('summary', $updates) ? trim((string) ($updates['summary'] ?? '')) : null;
    if ($summary !== null) {
        if ($summary === '') {
            throw new InvalidArgumentException('Le rÃ©sumÃ© de la tÃ¢che est obligatoire.');
        }

        $payload['summary'] = $summary;
    }

    $customFields = [];

    if (array_key_exists('assigneeId', $updates) || array_key_exists('assigneeRingId', $updates) || array_key_exists('assigneeUserId', $updates)) {
        $assigneeId = app_resolve_youtrack_assignee_id($updates);
        app_ensure_youtrack_project_assignee_field($normalizedProjectKey);
        $customFields[] = [
            'name' => YT_FIELD_ASSIGNEE,
            '$type' => 'SingleUserIssueCustomField',
            'value' => $assigneeId !== ''
                ? [
                    'id' => $assigneeId,
                    '$type' => 'User',
                ]
                : null,
        ];
    }

    if (array_key_exists('state', $updates)) {
        $state = trim((string) ($updates['state'] ?? ''));
        $customFields[] = [
            'name' => 'State',
            '$type' => 'StateIssueCustomField',
            'value' => $state !== ''
                ? [
                    'name' => $state,
                ]
                : null,
        ];
    }

    if (array_key_exists('dueDate', $updates)) {
        $dueDate = app_normalize_youtrack_due_date_value($updates['dueDate'] ?? null);
        $customFields[] = [
            'name' => defined('YT_FIELD_DUE_DATE') ? YT_FIELD_DUE_DATE : 'Date Ã©chÃ©ance',
            '$type' => 'DateIssueCustomField',
            'value' => $dueDate,
        ];
    }

    if (is_array($updates['customField'] ?? null)) {
        $customFieldKey = trim((string) ($updates['customField']['key'] ?? ''));
        if ($customFieldKey !== '') {
            $customFields[] = app_build_youtrack_task_custom_field_update_entry(
                $normalizedProjectKey,
                $customFieldKey,
                $updates['customField']['value'] ?? null
            );
        }
    }

    if ($customFields !== []) {
        $payload['customFields'] = $customFields;
    }

    if ($payload === []) {
        throw new InvalidArgumentException('Aucune modification Ã  appliquer Ã  la tÃ¢che YouTrack.');
    }

    app_youtrack_request(
        'POST',
        app_youtrack_base_url()
            . '/api/issues/'
            . rawurlencode($normalizedIssueId)
            . '?fields=id,idReadable,summary',
        $payload
    );

    app_reset_youtrack_project_tasks_cache($normalizedProjectKey);

    $projectTasks = app_list_youtrack_project_tasks($normalizedProjectKey);
    foreach (($projectTasks['tasks'] ?? []) as $task) {
        if (trim((string) ($task['idReadable'] ?? '')) === $normalizedIssueId) {
            return $task;
        }
    }

    throw new RuntimeException('Impossible de relire la tÃ¢che YouTrack aprÃ¨s mise Ã  jour.');
}

function app_delete_youtrack_project_task(string $issueId, string $projectKey = ''): void
{
    $normalizedIssueId = trim($issueId);
    if ($normalizedIssueId === '') {
        throw new InvalidArgumentException('TÃ¢che YouTrack manquante.');
    }

    app_youtrack_request(
        'DELETE',
        app_youtrack_base_url() . '/api/issues/' . rawurlencode($normalizedIssueId)
    );

    if (trim($projectKey) !== '') {
        app_reset_youtrack_project_tasks_cache($projectKey);
    }
}

function app_delete_youtrack_project(string $projectKey): void
{
    $normalizedProjectKey = trim($projectKey);
    if ($normalizedProjectKey === '') {
        throw new InvalidArgumentException('Projet YouTrack manquant.');
    }

    $project = app_find_youtrack_project($normalizedProjectKey);
    $projectId = trim((string) ($project['id'] ?? ''));
    if ($projectId === '') {
        throw new RuntimeException('Projet YouTrack introuvable : ' . $normalizedProjectKey);
    }

    app_youtrack_request(
        'POST',
        app_youtrack_base_url()
            . '/api/admin/projects/'
            . rawurlencode($projectId)
            . '?fields='
            . rawurlencode('id,shortName,archived'),
        [
            'archived' => true,
        ]
    );

    app_reset_youtrack_project_record_cache($normalizedProjectKey);
    app_reset_youtrack_project_team_cache($normalizedProjectKey);
    app_reset_youtrack_project_custom_fields_cache($normalizedProjectKey);
    app_reset_youtrack_project_tasks_cache($normalizedProjectKey);
}

function app_get_youtrack_issue_custom_field_value(array $issue, string $fieldName)
{
    foreach (($issue['customFields'] ?? []) as $customField) {
        if (($customField['name'] ?? '') === $fieldName) {
            return $customField['value'] ?? null;
        }
    }

    return null;
}

function app_format_youtrack_field_value_text($value): string
{
    if ($value === null) {
        return '';
    }

    if (is_string($value) || is_numeric($value)) {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '';
        }

        if (is_numeric($value)) {
            $formattedDate = app_format_youtrack_date_only($value);
            if ($formattedDate !== '') {
                return $formattedDate;
            }
        }

        return $normalized;
    }

    if (is_array($value) && app_array_is_list_compatible($value)) {
        $parts = [];

        foreach ($value as $item) {
            $text = app_format_youtrack_field_value_text($item);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode(', ', array_values(array_unique($parts)));
    }

    if (is_array($value)) {
        foreach (['presentation', 'name', 'login', 'text', 'fullName'] as $attribute) {
            if (!empty($value[$attribute])) {
                return trim((string) $value[$attribute]);
            }
        }
    }

    return '';
}

function app_get_youtrack_issue_custom_field_text(array $issue, string $fieldName): string
{
    $value = app_get_youtrack_issue_custom_field_value($issue, $fieldName);
    return app_format_youtrack_field_value_text($value);
}

function app_get_youtrack_issue_custom_field_input_text(array $issue, string $fieldName): string
{
    $value = app_get_youtrack_issue_custom_field_value($issue, $fieldName);

    if ($value === null) {
        return '';
    }

    if (is_string($value) || is_numeric($value)) {
        return trim((string) $value);
    }

    if (is_array($value) && app_array_is_list_compatible($value)) {
        return app_format_youtrack_field_value_text($value);
    }

    if (is_array($value)) {
        foreach (['name', 'presentation', 'login', 'text', 'fullName'] as $attribute) {
            if (!empty($value[$attribute])) {
                return trim((string) $value[$attribute]);
            }
        }
    }

    return '';
}

function app_format_youtrack_date($value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    if (is_numeric($value)) {
        $timestampMs = (int) $value;
        if ($timestampMs > 0) {
            return date('d/m/Y H:i', (int) floor($timestampMs / 1000));
        }
    }

    return '';
}

function app_format_youtrack_date_only($value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    if (is_numeric($value)) {
        $timestampMs = (int) $value;
        if ($timestampMs > 0) {
            return date('d/m/Y', (int) floor($timestampMs / 1000));
        }
    }

    $normalized = trim((string) $value);
    if ($normalized === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($normalized))->format('d/m/Y');
    } catch (Exception $exception) {
        return $normalized;
    }
}

function app_format_youtrack_date_input($value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    if (is_numeric($value)) {
        $timestampMs = (int) $value;
        if ($timestampMs > 0) {
            return date('Y-m-d', (int) floor($timestampMs / 1000));
        }
    }

    $normalized = trim((string) $value);
    if ($normalized === '') {
        return '';
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $normalized);
    if ($date instanceof DateTimeImmutable) {
        return $date->format('Y-m-d');
    }

    try {
        return (new DateTimeImmutable($normalized))->format('Y-m-d');
    } catch (Exception $exception) {
        return '';
    }
}

function app_build_youtrack_issue_url(string $idReadable): string
{
    return app_youtrack_base_url() . '/issue/' . rawurlencode($idReadable);
}

function app_list_youtrack_project_tasks(string $projectKey): array
{
    $normalizedProjectKey = trim($projectKey);
    if ($normalizedProjectKey === '') {
        throw new InvalidArgumentException('Projet YouTrack manquant.');
    }

    return app_cache_remember(
        'youtrack.project-tasks',
        app_youtrack_project_cache_key($normalizedProjectKey),
        45,
        static function () use ($normalizedProjectKey): array {
            $fields = 'id,idReadable,summary,description,created,reporter(id,login,name),customFields(name,value(id,login,name,presentation,text,fullName))';
            $tasks = [];
            $customFieldColumns = app_get_youtrack_task_custom_field_columns($normalizedProjectKey);
            $skip = 0;
            $top = 100;

            do {
                $page = app_youtrack_request(
                    'GET',
                    app_youtrack_base_url()
                        . '/api/issues?query=' . rawurlencode('project: ' . $normalizedProjectKey)
                        . '&$skip=' . $skip
                        . '&$top=' . $top
                        . '&fields=' . rawurlencode($fields)
                );

                if (!is_array($page)) {
                    break;
                }

                foreach ($page as $issue) {
                    $reporterLogin = trim((string) ($issue['reporter']['login'] ?? ''));
                    $reporterName = trim((string) ($issue['reporter']['name'] ?? $reporterLogin));
                    $assigneeValue = app_get_youtrack_issue_custom_field_value($issue, YT_FIELD_ASSIGNEE);
                    $assignee = app_get_youtrack_issue_custom_field_text($issue, YT_FIELD_ASSIGNEE);
                    $assigneeId = is_array($assigneeValue) ? trim((string) ($assigneeValue['id'] ?? '')) : '';
                    $stateValue = app_get_youtrack_issue_custom_field_value($issue, 'State');
                    $state = app_get_youtrack_issue_custom_field_text($issue, 'State');
                    $stateId = is_array($stateValue) ? trim((string) ($stateValue['id'] ?? '')) : '';
                    $dueDateFieldName = defined('YT_FIELD_DUE_DATE') ? YT_FIELD_DUE_DATE : 'Date ÃƒÂ©chÃƒÂ©ance';
                    $dueDateValue = app_get_youtrack_issue_custom_field_value($issue, $dueDateFieldName);
                    $dueDate = app_format_youtrack_date_only($dueDateValue);
                    if ($dueDate === '') {
                        $dueDate = app_format_youtrack_date_only(app_get_youtrack_issue_custom_field_text($issue, $dueDateFieldName));
                    }
                    $dueDateInput = app_format_youtrack_date_input($dueDateValue);
                    if ($dueDateInput === '') {
                        $dueDateInput = app_format_youtrack_date_input(app_get_youtrack_issue_custom_field_text($issue, $dueDateFieldName));
                    }

                    $customFieldValues = [];
                    $customFieldInputValues = [];
                    foreach ($customFieldColumns as $column) {
                        $columnKey = trim((string) ($column['key'] ?? ''));
                        $fieldName = trim((string) ($column['fieldName'] ?? ''));
                        if ($columnKey === '' || $fieldName === '') {
                            continue;
                        }

                        $customFieldText = app_get_youtrack_issue_custom_field_text($issue, $fieldName);
                        $customFieldInputText = app_get_youtrack_issue_custom_field_input_text($issue, $fieldName);
                        $customFieldValues[$columnKey] = $customFieldText !== '' ? $customFieldText : '-';
                        $customFieldInputValues[$columnKey] = $customFieldInputText;
                    }

                    $tasks[] = [
                        'idReadable' => trim((string) ($issue['idReadable'] ?? '')),
                        'summary' => trim((string) ($issue['summary'] ?? '')),
                        'description' => trim(strip_tags((string) ($issue['description'] ?? ''))),
                        'reporter' => $reporterName !== '' ? $reporterName : $reporterLogin,
                        'assignee' => $assignee,
                        'assigneeId' => $assigneeId,
                        'created' => app_format_youtrack_date($issue['created'] ?? null),
                        'dueDate' => $dueDate,
                        'dueDateInput' => $dueDateInput,
                        'state' => $state !== '' ? $state : 'Non renseignÃƒÂ©',
                        'stateId' => $stateId,
                        'customFieldValues' => $customFieldValues,
                        'customFieldInputValues' => $customFieldInputValues,
                        'url' => !empty($issue['idReadable']) ? app_build_youtrack_issue_url((string) $issue['idReadable']) : null,
                    ];
                }

                $skip += $top;
            } while (count($page) === $top);

            usort($tasks, static function (array $left, array $right): int {
                return strcasecmp($left['idReadable'] ?? '', $right['idReadable'] ?? '');
            });

            return [
                'project' => $normalizedProjectKey,
                'tasks' => $tasks,
                'assignees' => app_list_youtrack_project_assignees($normalizedProjectKey),
                'stateOptions' => app_list_youtrack_project_state_options($normalizedProjectKey),
                'customFieldColumns' => $customFieldColumns,
            ];
        },
        [
            'allowStaleOnError' => true,
            'staleTtl' => 300,
        ]
    );

    $fields = 'id,idReadable,summary,description,created,reporter(id,login,name),customFields(name,value(id,login,name,presentation,text,fullName))';
    $tasks = [];
    $customFieldColumns = app_get_youtrack_task_custom_field_columns($normalizedProjectKey);
    $skip = 0;
    $top = 100;

    do {
        $page = app_youtrack_request(
            'GET',
            app_youtrack_base_url()
                . '/api/issues?query=' . rawurlencode('project: ' . $normalizedProjectKey)
                . '&$skip=' . $skip
                . '&$top=' . $top
                . '&fields=' . rawurlencode($fields)
        );

        if (!is_array($page)) {
            break;
        }

        foreach ($page as $issue) {
            $reporterLogin = trim((string) ($issue['reporter']['login'] ?? ''));
            $reporterName = trim((string) ($issue['reporter']['name'] ?? $reporterLogin));
            $assigneeValue = app_get_youtrack_issue_custom_field_value($issue, YT_FIELD_ASSIGNEE);
            $assignee = app_get_youtrack_issue_custom_field_text($issue, YT_FIELD_ASSIGNEE);
            $assigneeId = is_array($assigneeValue) ? trim((string) ($assigneeValue['id'] ?? '')) : '';
            $stateValue = app_get_youtrack_issue_custom_field_value($issue, 'State');
            $state = app_get_youtrack_issue_custom_field_text($issue, 'State');
            $stateId = is_array($stateValue) ? trim((string) ($stateValue['id'] ?? '')) : '';
            $dueDateValue = app_get_youtrack_issue_custom_field_value($issue, defined('YT_FIELD_DUE_DATE') ? YT_FIELD_DUE_DATE : 'Date Ã©chÃ©ance');
            $dueDate = app_format_youtrack_date_only($dueDateValue);
            if ($dueDate === '') {
                $dueDate = app_format_youtrack_date_only(app_get_youtrack_issue_custom_field_text($issue, defined('YT_FIELD_DUE_DATE') ? YT_FIELD_DUE_DATE : 'Date Ã©chÃ©ance'));
            }
            $dueDateInput = app_format_youtrack_date_input($dueDateValue);
            if ($dueDateInput === '') {
                $dueDateInput = app_format_youtrack_date_input(app_get_youtrack_issue_custom_field_text($issue, defined('YT_FIELD_DUE_DATE') ? YT_FIELD_DUE_DATE : 'Date Ã©chÃ©ance'));
            }

            $customFieldValues = [];
            $customFieldInputValues = [];
            foreach ($customFieldColumns as $column) {
                $columnKey = trim((string) ($column['key'] ?? ''));
                $fieldName = trim((string) ($column['fieldName'] ?? ''));
                if ($columnKey === '' || $fieldName === '') {
                    continue;
                }

                $customFieldText = app_get_youtrack_issue_custom_field_text($issue, $fieldName);
                $customFieldInputText = app_get_youtrack_issue_custom_field_input_text($issue, $fieldName);
                $customFieldValues[$columnKey] = $customFieldText !== '' ? $customFieldText : '-';
                $customFieldInputValues[$columnKey] = $customFieldInputText;
            }

            $tasks[] = [
                'idReadable' => trim((string) ($issue['idReadable'] ?? '')),
                'summary' => trim((string) ($issue['summary'] ?? '')),
                'description' => trim(strip_tags((string) ($issue['description'] ?? ''))),
                'reporter' => $reporterName !== '' ? $reporterName : $reporterLogin,
                'assignee' => $assignee,
                'assigneeId' => $assigneeId,
                'created' => app_format_youtrack_date($issue['created'] ?? null),
                'dueDate' => $dueDate,
                'dueDateInput' => $dueDateInput,
                'state' => $state !== '' ? $state : 'Non renseignÃ©',
                'stateId' => $stateId,
                'customFieldValues' => $customFieldValues,
                'customFieldInputValues' => $customFieldInputValues,
                'url' => !empty($issue['idReadable']) ? app_build_youtrack_issue_url((string) $issue['idReadable']) : null,
            ];
        }

        $skip += $top;
    } while (count($page) === $top);

    usort($tasks, static function (array $left, array $right): int {
        return strcasecmp($left['idReadable'] ?? '', $right['idReadable'] ?? '');
    });

    return [
        'project' => $normalizedProjectKey,
        'tasks' => $tasks,
        'assignees' => app_list_youtrack_project_assignees($normalizedProjectKey),
        'stateOptions' => app_list_youtrack_project_state_options($normalizedProjectKey),
        'customFieldColumns' => $customFieldColumns,
    ];
}
