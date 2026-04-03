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
                throw new RuntimeException('DATABASE_URL est manquant pour le runtime Livre de caisse.');
            }

            $parts = parse_url($databaseUrl);
            if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'mysql') {
                throw new RuntimeException('DATABASE_URL doit pointer vers une base MySQL pour le runtime Livre de caisse.');
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

if (!function_exists('app_set_current_livre_de_caisse_user')) {
    function app_set_current_livre_de_caisse_user(?array $user): void
    {
        $GLOBALS['app_livre_de_caisse_current_user'] = is_array($user) ? $user : null;
    }
}

if (!function_exists('app_current_livre_de_caisse_user')) {
    function app_current_livre_de_caisse_user(): ?array
    {
        $user = $GLOBALS['app_livre_de_caisse_current_user'] ?? null;

        return is_array($user) ? $user : null;
    }
}
