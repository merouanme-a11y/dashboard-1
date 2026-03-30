<?php

use App\HttpCache\AppCache;
use App\Kernel;
use Symfony\Component\HttpKernel\HttpCache\Store;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    $kernel = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
    $httpCacheEnabled = filter_var($_SERVER['APP_HTTP_CACHE'] ?? $_ENV['APP_HTTP_CACHE'] ?? true, FILTER_VALIDATE_BOOL);

    if (!$httpCacheEnabled) {
        return $kernel;
    }

    return new AppCache(
        $kernel,
        new Store(dirname(__DIR__).'/var/http_cache/'.$context['APP_ENV'])
    );
};
