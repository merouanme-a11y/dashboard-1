<?php

declare(strict_types=1);

if (!class_exists('Database')) {
    final class Database
    {
        private static ?PDO $pdo = null;

        public static function connect(): PDO
        {
            if (self::$pdo instanceof PDO) {
                return self::$pdo;
            }

            $databaseUrl = (string) (
                $_ENV['DATABASE_URL']
                ?? $_SERVER['DATABASE_URL']
                ?? getenv('DATABASE_URL')
                ?? ''
            );

            if ($databaseUrl === '') {
                throw new RuntimeException('DATABASE_URL est manquant pour le runtime Gantt.');
            }

            $parts = parse_url($databaseUrl);
            if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'mysql') {
                throw new RuntimeException('DATABASE_URL doit pointer vers une base MySQL pour le runtime Gantt.');
            }

            parse_str((string) ($parts['query'] ?? ''), $query);

            $host = (string) ($parts['host'] ?? '127.0.0.1');
            $port = (int) ($parts['port'] ?? 3306);
            $database = ltrim((string) ($parts['path'] ?? ''), '/');
            $charset = trim((string) ($query['charset'] ?? 'utf8mb4'));
            $user = rawurldecode((string) ($parts['user'] ?? ''));
            $password = rawurldecode((string) ($parts['pass'] ?? ''));

            if ($database === '') {
                throw new RuntimeException('Le nom de base est manquant dans DATABASE_URL.');
            }

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $host,
                $port,
                $database,
                $charset !== '' ? $charset : 'utf8mb4'
            );

            self::$pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            return self::$pdo;
        }
    }
}

if (!function_exists('app_start_session')) {
    function app_start_session(): void
    {
        // La session est geree par Symfony.
    }
}

if (!function_exists('app_set_current_user')) {
    function app_set_current_user(?array $user): void
    {
        $GLOBALS['app_gantt_current_user'] = is_array($user) ? $user : null;
    }
}

if (!function_exists('app_current_user')) {
    function app_current_user(): ?array
    {
        $user = $GLOBALS['app_gantt_current_user'] ?? null;

        return is_array($user) ? $user : null;
    }
}

if (!function_exists('app_require_user')) {
    function app_require_user(): array
    {
        $user = app_current_user();
        if (!is_array($user)) {
            throw new RuntimeException('Utilisateur Symfony non initialise pour le module Gantt.');
        }

        return $user;
    }
}

if (!function_exists('app_read_json_file')) {
    function app_read_json_file(string $filePath): array
    {
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return [];
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('app_projects_file')) {
    function app_projects_file(): string
    {
        return rtrim((string) (defined('APP_GANTT_STORAGE_DIR') ? APP_GANTT_STORAGE_DIR : sys_get_temp_dir()), "\\/") . '/projects.json';
    }
}

if (!function_exists('app_gantt_export_download_url')) {
    function app_gantt_export_download_url(string $fileName): string
    {
        $template = (string) ($GLOBALS['app_gantt_export_download_url_template'] ?? '');
        if ($template === '') {
            return '/projets/gantt/export/' . rawurlencode($fileName);
        }

        return str_replace('__FILE__', rawurlencode($fileName), $template);
    }
}

if (!function_exists('app_set_gantt_export_download_url_template')) {
    function app_set_gantt_export_download_url_template(string $template): void
    {
        $GLOBALS['app_gantt_export_download_url_template'] = $template;
    }
}
