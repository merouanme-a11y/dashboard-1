<?php

declare(strict_types=1);

if (!function_exists('app_runtime_env_value')) {
    function app_runtime_env_value(string $name, bool $includeProcessValue = true): ?string
    {
        $candidates = [
            $_SERVER[$name] ?? null,
            $_ENV[$name] ?? null,
        ];

        if ($includeProcessValue) {
            $processValue = getenv($name);
            if ($processValue !== false) {
                $candidates[] = $processValue;
            }
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    function app_set_runtime_env_value(string $name, string $value): void
    {
        $_SERVER[$name] = $value;
        $_ENV[$name] = $value;

        if (function_exists('putenv')) {
            @putenv($name . '=' . $value);
        }
    }

    function app_parse_runtime_env_file(string $filePath): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return [];
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return [];
        }

        $values = [];

        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            if ($trimmedLine === '' || str_starts_with($trimmedLine, '#')) {
                continue;
            }

            if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/', $line, $matches) !== 1) {
                continue;
            }

            $name = $matches[1];
            $rawValue = trim($matches[2]);

            if ($rawValue !== '' && $rawValue[0] !== '"' && $rawValue[0] !== "'") {
                $commentOffset = strpos($rawValue, ' #');
                if ($commentOffset !== false) {
                    $rawValue = rtrim(substr($rawValue, 0, $commentOffset));
                }
            }

            $quote = $rawValue[0] ?? '';
            $lastChar = $rawValue !== '' ? substr($rawValue, -1) : '';

            if (($quote === '"' || $quote === "'") && $lastChar === $quote) {
                $rawValue = substr($rawValue, 1, -1);

                if ($quote === '"') {
                    $rawValue = stripcslashes($rawValue);
                }
            }

            $values[$name] = $rawValue;
        }

        return $values;
    }

    function app_load_runtime_env_file(string $filePath): void
    {
        foreach (app_parse_runtime_env_file($filePath) as $name => $value) {
            app_set_runtime_env_value($name, $value);
        }
    }

    function app_load_runtime_dotenv(): void
    {
        $isHttpRequest = PHP_SAPI !== 'cli';
        $hasCurrentRequestAppEnv = app_runtime_env_value('APP_ENV', false) !== null;
        $hasCurrentRequestDatabaseUrl = app_runtime_env_value('DATABASE_URL', false) !== null;
        $hasCurrentRequestDefaultUri = app_runtime_env_value('DEFAULT_URI', false) !== null;

        if (
            $hasCurrentRequestAppEnv
            && $hasCurrentRequestDatabaseUrl
            && (!$isHttpRequest || $hasCurrentRequestDefaultUri)
        ) {
            return;
        }

        $projectDir = dirname(__DIR__);
        $envFile = $projectDir . '/.env';
        if (!is_file($envFile)) {
            return;
        }

        $defaultEnv = DIRECTORY_SEPARATOR === '\\' ? 'dev' : 'prod';
        $host = strtolower(trim((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''))));
        $isRemoteHttpHost = $host !== '' && !in_array($host, ['localhost', '127.0.0.1', '::1'], true);

        app_load_runtime_env_file($envFile);
        app_load_runtime_env_file($projectDir . '/.env.local');

        $resolvedEnv = app_runtime_env_value('APP_ENV') ?? $defaultEnv;

        if (DIRECTORY_SEPARATOR !== '\\' && $isRemoteHttpHost) {
            $resolvedEnv = 'prod';
        }

        app_set_runtime_env_value('APP_ENV', $resolvedEnv);

        if ($resolvedEnv !== 'local') {
            app_load_runtime_env_file($projectDir . '/.env.' . $resolvedEnv);
            app_load_runtime_env_file($projectDir . '/.env.' . $resolvedEnv . '.local');
        }

        if (DIRECTORY_SEPARATOR !== '\\' && $isRemoteHttpHost) {
            app_set_runtime_env_value('APP_ENV', 'prod');
            app_set_runtime_env_value('APP_DEBUG', '0');
        } elseif (app_runtime_env_value('APP_DEBUG') === null) {
            app_set_runtime_env_value('APP_DEBUG', strtolower($resolvedEnv) === 'prod' ? '0' : '1');
        }
    }

    function app_configure_runtime_environment(bool $isHttpRequest): void
    {
        $appEnv = app_runtime_env_value('APP_ENV');
        if ($appEnv === null) {
            $appEnv = DIRECTORY_SEPARATOR === '\\' ? 'dev' : 'prod';
            app_set_runtime_env_value('APP_ENV', $appEnv);
        }

        $normalizedEnv = strtolower($appEnv);

        if (app_runtime_env_value('APP_DEBUG') === null) {
            app_set_runtime_env_value('APP_DEBUG', $normalizedEnv === 'prod' ? '0' : '1');
        }

        if ($normalizedEnv === 'prod' && app_runtime_env_value('APP_HTTP_CACHE') === null) {
            app_set_runtime_env_value('APP_HTTP_CACHE', '0');
        }

        if (!$isHttpRequest || app_runtime_env_value('DEFAULT_URI') !== null) {
            return;
        }

        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return;
        }

        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        $requestScheme = strtolower((string) ($_SERVER['REQUEST_SCHEME'] ?? ''));
        $scheme = ($https !== '' && $https !== 'off') || $requestScheme === 'https' ? 'https' : 'http';

        app_set_runtime_env_value('DEFAULT_URI', $scheme . '://' . $host);
    }
}

app_load_runtime_dotenv();
app_configure_runtime_environment(PHP_SAPI !== 'cli');
