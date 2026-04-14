<?php

declare(strict_types=1);

if (!function_exists('app_runtime_env_value')) {
    function app_runtime_env_value(string $name): ?string
    {
        $candidates = [
            $_SERVER[$name] ?? null,
            $_ENV[$name] ?? null,
        ];

        $processValue = getenv($name);
        if ($processValue !== false) {
            $candidates[] = $processValue;
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

app_configure_runtime_environment(PHP_SAPI !== 'cli');
