<?php

declare(strict_types=1);

function app_cache_root_dir(): ?string
{
    static $resolvedDir = false;

    if (is_string($resolvedDir) || $resolvedDir === null) {
        return $resolvedDir;
    }

    $candidates = [
        __DIR__ . DIRECTORY_SEPARATOR . 'runtime-cache',
    ];

    $tempDirectory = rtrim((string) sys_get_temp_dir(), "\\/");
    if ($tempDirectory !== '') {
        $candidates[] = $tempDirectory . DIRECTORY_SEPARATOR . 'dashboard-adep-gantt-cache';
    }

    foreach ($candidates as $candidate) {
        if (!is_dir($candidate) && !@mkdir($candidate, 0775, true) && !is_dir($candidate)) {
            continue;
        }

        if (is_writable($candidate)) {
            $resolvedDir = $candidate;
            return $resolvedDir;
        }
    }

    $resolvedDir = null;
    return null;
}

function app_cache_memory_key(string $namespace, string $key): string
{
    return $namespace . "\n" . $key;
}

function app_cache_file_path(string $namespace, string $key): ?string
{
    $rootDir = app_cache_root_dir();
    if ($rootDir === null) {
        return null;
    }

    $safeNamespace = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($namespace)) ?? '';
    if ($safeNamespace === '') {
        $safeNamespace = 'default';
    }

    return $rootDir
        . DIRECTORY_SEPARATOR
        . $safeNamespace
        . '--'
        . hash('sha256', $key)
        . '.json';
}

function app_cache_read_entry(string $namespace, string $key): ?array
{
    if (!isset($GLOBALS['app_runtime_cache']) || !is_array($GLOBALS['app_runtime_cache'])) {
        $GLOBALS['app_runtime_cache'] = [];
    }

    $memoryKey = app_cache_memory_key($namespace, $key);
    if (array_key_exists($memoryKey, $GLOBALS['app_runtime_cache'])) {
        $cachedEntry = $GLOBALS['app_runtime_cache'][$memoryKey];
        return is_array($cachedEntry) ? $cachedEntry : null;
    }

    $path = app_cache_file_path($namespace, $key);
    if ($path === null || !is_file($path)) {
        $GLOBALS['app_runtime_cache'][$memoryKey] = null;
        return null;
    }

    $contents = @file_get_contents($path);
    if (!is_string($contents) || trim($contents) === '') {
        $GLOBALS['app_runtime_cache'][$memoryKey] = null;
        return null;
    }

    $decoded = json_decode($contents, true);
    if (
        !is_array($decoded)
        || !array_key_exists('storedAt', $decoded)
        || !array_key_exists('value', $decoded)
    ) {
        $GLOBALS['app_runtime_cache'][$memoryKey] = null;
        return null;
    }

    $entry = [
        'storedAt' => (int) $decoded['storedAt'],
        'value' => $decoded['value'],
    ];

    $GLOBALS['app_runtime_cache'][$memoryKey] = $entry;
    return $entry;
}

function app_cache_write_entry(string $namespace, string $key, $value): void
{
    if (!isset($GLOBALS['app_runtime_cache']) || !is_array($GLOBALS['app_runtime_cache'])) {
        $GLOBALS['app_runtime_cache'] = [];
    }

    $entry = [
        'storedAt' => time(),
        'value' => $value,
    ];

    $memoryKey = app_cache_memory_key($namespace, $key);
    $GLOBALS['app_runtime_cache'][$memoryKey] = $entry;

    $path = app_cache_file_path($namespace, $key);
    if ($path === null) {
        return;
    }

    $payload = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return;
    }

    $temporaryPath = $path . '.' . str_replace('.', '', uniqid('cache', true)) . '.tmp';
    if (@file_put_contents($temporaryPath, $payload, LOCK_EX) === false) {
        @unlink($temporaryPath);
        return;
    }

    if (!@rename($temporaryPath, $path)) {
        if (@copy($temporaryPath, $path)) {
            @unlink($temporaryPath);
            return;
        }

        @unlink($temporaryPath);
    }
}

function app_cache_forget(string $namespace, string $key): void
{
    if (!isset($GLOBALS['app_runtime_cache']) || !is_array($GLOBALS['app_runtime_cache'])) {
        $GLOBALS['app_runtime_cache'] = [];
    }

    $memoryKey = app_cache_memory_key($namespace, $key);
    unset($GLOBALS['app_runtime_cache'][$memoryKey]);

    $path = app_cache_file_path($namespace, $key);
    if ($path !== null && is_file($path)) {
        @unlink($path);
    }
}

function app_cache_remember(string $namespace, string $key, int $ttl, callable $resolver, array $options = [])
{
    $normalizedTtl = max(1, $ttl);
    $staleTtl = max(
        $normalizedTtl,
        (int) ($options['staleTtl'] ?? ($normalizedTtl * 10))
    );
    $allowStaleOnError = array_key_exists('allowStaleOnError', $options)
        ? (bool) $options['allowStaleOnError']
        : true;

    $entry = app_cache_read_entry($namespace, $key);
    $now = time();

    if (is_array($entry) && ($now - (int) ($entry['storedAt'] ?? 0)) <= $normalizedTtl) {
        return $entry['value'];
    }

    try {
        $value = $resolver();
        app_cache_write_entry($namespace, $key, $value);
        return $value;
    } catch (Throwable $throwable) {
        if (
            $allowStaleOnError
            && is_array($entry)
            && ($now - (int) ($entry['storedAt'] ?? 0)) <= $staleTtl
        ) {
            return $entry['value'];
        }

        throw $throwable;
    }
}
