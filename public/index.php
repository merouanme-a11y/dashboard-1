<?php

use App\HttpCache\AppCache;
use App\Kernel;
use Symfony\Component\HttpKernel\HttpCache\Store;

require_once dirname(__DIR__).'/config/runtime_environment.php';
require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    $projectDir = dirname(__DIR__);
    $environment = (string) ($context['APP_ENV'] ?? 'prod');
    $projectVarDir = $projectDir . '/var';
    $runtimeBaseDir = $projectVarDir;
    $canUseProjectVarDir = (is_dir($projectVarDir) || @mkdir($projectVarDir, 0775, true)) && is_writable($projectVarDir);

    if (!$canUseProjectVarDir) {
        $homeDir = trim((string) ($_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? ''));
        $tempDir = trim((string) ($_SERVER['TMPDIR'] ?? sys_get_temp_dir()));
        $projectHash = substr(sha1($projectDir), 0, 12);
        $fallbackCandidates = [];

        if ($homeDir !== '') {
            $fallbackCandidates[] = rtrim(str_replace('\\', '/', $homeDir), '/') . '/tmp/dashboard-runtime-' . $projectHash;
        }

        if ($tempDir !== '') {
            $fallbackCandidates[] = rtrim(str_replace('\\', '/', $tempDir), '/') . '/dashboard-runtime-' . $projectHash;
        }

        foreach ($fallbackCandidates as $candidate) {
            if (is_dir($candidate) || @mkdir($candidate, 0775, true)) {
                $runtimeBaseDir = $candidate;
                break;
            }
        }
    }

    $directories = [
        $runtimeBaseDir,
        $runtimeBaseDir . '/cache',
        $runtimeBaseDir . '/cache/' . $environment,
        $runtimeBaseDir . '/log',
        $runtimeBaseDir . '/share',
        $runtimeBaseDir . '/share/' . $environment,
        $runtimeBaseDir . '/sessions',
        $runtimeBaseDir . '/sessions/' . $environment,
    ];

    foreach ($directories as $directory) {
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }
    }

    $shareDir = $runtimeBaseDir . '/share';
    $sessionDir = $runtimeBaseDir . '/cache/' . $environment . '/sessions';

    if (is_dir($shareDir) && is_writable($shareDir)) {
        $_SERVER['APP_SHARE_DIR'] = $shareDir;
        $_ENV['APP_SHARE_DIR'] = $shareDir;
    }

    if (is_dir($sessionDir) && is_writable($sessionDir)) {
        @ini_set('session.save_path', $sessionDir);
    }

    $kernel = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
    $httpCacheEnabled = filter_var($_SERVER['APP_HTTP_CACHE'] ?? $_ENV['APP_HTTP_CACHE'] ?? true, FILTER_VALIDATE_BOOL);

    if (!$httpCacheEnabled) {
        return $kernel;
    }

    $storeDir = $projectDir . '/var/http_cache/' . $context['APP_ENV'];
    $parentDir = dirname($storeDir);

    if (
        (!is_dir($parentDir) && !@mkdir($parentDir, 0775, true) && !is_dir($parentDir))
        || (!is_dir($storeDir) && !@mkdir($storeDir, 0775, true) && !is_dir($storeDir))
        || !is_writable($storeDir)
    ) {
        return $kernel;
    }

    try {
        return new AppCache($kernel, new Store($storeDir));
    } catch (\Throwable) {
        return $kernel;
    }
};
