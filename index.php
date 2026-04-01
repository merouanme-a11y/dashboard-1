<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $scriptBase = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
    $relativePath = is_string($requestPath) ? $requestPath : '/';

    if ($scriptBase !== '' && $scriptBase !== '/') {
        if (str_starts_with($relativePath, $scriptBase . '/')) {
            $relativePath = substr($relativePath, strlen($scriptBase) + 1);
        } elseif ($relativePath === $scriptBase) {
            $relativePath = '';
        }
    } else {
        $relativePath = ltrim($relativePath, '/');
    }

    $relativePath = ltrim((string) $relativePath, '/');

    if ($relativePath !== '' && !str_starts_with($relativePath, 'index.php')) {
        $publicDir = realpath(__DIR__ . '/public');
        $candidatePath = $publicDir === false ? false : realpath($publicDir . DIRECTORY_SEPARATOR . $relativePath);

        if (
            $publicDir !== false
            && $candidatePath !== false
            && str_starts_with($candidatePath, $publicDir . DIRECTORY_SEPARATOR)
            && is_file($candidatePath)
        ) {
            $contentType = 'application/octet-stream';
            $extension = strtolower(pathinfo($candidatePath, PATHINFO_EXTENSION));
            $knownContentTypes = [
                'avif' => 'image/avif',
                'css' => 'text/css; charset=UTF-8',
                'eot' => 'application/vnd.ms-fontobject',
                'gif' => 'image/gif',
                'html' => 'text/html; charset=UTF-8',
                'ico' => 'image/x-icon',
                'jpeg' => 'image/jpeg',
                'jpg' => 'image/jpeg',
                'js' => 'application/javascript; charset=UTF-8',
                'json' => 'application/json; charset=UTF-8',
                'map' => 'application/json; charset=UTF-8',
                'mjs' => 'application/javascript; charset=UTF-8',
                'pdf' => 'application/pdf',
                'png' => 'image/png',
                'svg' => 'image/svg+xml',
                'ttf' => 'font/ttf',
                'txt' => 'text/plain; charset=UTF-8',
                'webp' => 'image/webp',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2',
                'xml' => 'application/xml; charset=UTF-8',
            ];

            if (isset($knownContentTypes[$extension])) {
                $contentType = $knownContentTypes[$extension];
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);

                if ($finfo !== false) {
                    $detectedContentType = finfo_file($finfo, $candidatePath);
                    finfo_close($finfo);

                    if (is_string($detectedContentType) && $detectedContentType !== '') {
                        $contentType = $detectedContentType;
                    }
                }
            }

            header('Content-Type: ' . $contentType);
            header('Content-Length: ' . (string) filesize($candidatePath));
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', (int) filemtime($candidatePath)) . ' GMT');

            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
                readfile($candidatePath);
            }

            return true;
        }
    }
}

return require __DIR__ . '/public/index.php';
