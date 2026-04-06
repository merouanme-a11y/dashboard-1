<?php

declare(strict_types=1);

$maintenanceToken = 'fix-20260404-2205';

if (
    PHP_SAPI !== 'cli'
    && isset($_GET['maintenance_token'])
    && hash_equals($maintenanceToken, (string) $_GET['maintenance_token'])
) {
    header('Content-Type: text/plain; charset=UTF-8');

    $projectRoot = __DIR__ . '/..';
    $changed = 0;
    $errors = [];

    $chmodTree = static function (string $path, int $directoryMode, int $fileMode) use (&$changed, &$errors): void {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path)) {
            if (!@chmod($path, $fileMode)) {
                $errors[] = 'chmod failed on file: ' . $path;
            } else {
                $changed++;
            }

            return;
        }

        if (!@chmod($path, $directoryMode)) {
            $errors[] = 'chmod failed on dir: ' . $path;
        } else {
            $changed++;
        }

        $items = @scandir($path);
        if (!is_array($items)) {
            $errors[] = 'scandir failed: ' . $path;
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $child = $path . DIRECTORY_SEPARATOR . $item;

            if (is_dir($child)) {
                $chmodTree($child, $directoryMode, $fileMode);
                continue;
            }

            if (!@chmod($child, $fileMode)) {
                $errors[] = 'chmod failed on file: ' . $child;
                continue;
            }

            $changed++;
        }
    };

    $vendorRoot = $projectRoot . '/vendor';
    if (is_dir($vendorRoot)) {
        $vendorEntries = @scandir($vendorRoot);
        if (is_array($vendorEntries)) {
            foreach ($vendorEntries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $entryPath = $vendorRoot . '/' . $entry;
                if (is_dir($entryPath)) {
                    if (!@chmod($entryPath, 0755)) {
                        $errors[] = 'chmod failed on vendor dir: ' . $entryPath;
                    } else {
                        $changed++;
                    }
                } elseif (is_file($entryPath)) {
                    if (!@chmod($entryPath, 0644)) {
                        $errors[] = 'chmod failed on vendor file: ' . $entryPath;
                    } else {
                        $changed++;
                    }
                }
            }
        }
    }

    $zipPath = $projectRoot . '/vendor-prod.zip';
    if (is_file($zipPath) && class_exists(ZipArchive::class)) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) === true) {
            if (!$zip->extractTo($projectRoot)) {
                $errors[] = 'extract failed: ' . $zipPath;
            }
            $zip->close();
        } else {
            $errors[] = 'zip open failed: ' . $zipPath;
        }
    }

    $chmodTree($projectRoot . '/vendor', 0755, 0644);
    $chmodTree($projectRoot . '/var', 0775, 0644);
    $chmodTree($projectRoot . '/uploads', 0775, 0644);
    $chmodTree($projectRoot . '/public/uploads', 0775, 0644);
    $chmodTree($projectRoot . '/public/modules/gantt-projects/export', 0775, 0644);

    foreach ([$projectRoot . '/.htaccess', $projectRoot . '/public/.htaccess'] as $file) {
        if (is_file($file)) {
            if (!@chmod($file, 0644)) {
                $errors[] = 'chmod failed on file: ' . $file;
            } else {
                $changed++;
            }
        }
    }

    echo "Changed: {$changed}\n";
    echo 'autoload_runtime readable: ' . (is_readable($projectRoot . '/vendor/autoload_runtime.php') ? 'yes' : 'no') . "\n";
    echo 'symfony function readable: ' . (is_readable($projectRoot . '/vendor/symfony/deprecation-contracts/function.php') ? 'yes' : 'no') . "\n";

    if ($errors !== []) {
        echo "Errors:\n";
        foreach ($errors as $error) {
            echo '- ' . $error . "\n";
        }
    } else {
        echo "OK\n";
    }

    exit;
}

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
        $publicDir = realpath(__DIR__ . '/../public');
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

return require __DIR__ . '/../public/index.php';
