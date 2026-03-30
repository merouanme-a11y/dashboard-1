<?php

use App\HttpCache\AppCache;
use App\Kernel;
use Symfony\Component\HttpKernel\HttpCache\Store;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    $projectDir = dirname(__DIR__);
    $environment = (string) ($context['APP_ENV'] ?? 'prod');

    $directories = [
        $projectDir . '/var',
        $projectDir . '/var/cache',
        $projectDir . '/var/cache/' . $environment,
        $projectDir . '/var/log',
        $projectDir . '/var/share',
        $projectDir . '/var/share/' . $environment,
        $projectDir . '/var/sessions',
        $projectDir . '/var/sessions/' . $environment,
    ];

    foreach ($directories as $directory) {
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }
    }

    $sessionDir = $projectDir . '/var/sessions/' . $environment;
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
