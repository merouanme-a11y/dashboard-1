<?php

declare(strict_types=1);

$token = 'fix-20260404-2215';

header('Content-Type: text/plain; charset=UTF-8');

if (!isset($_GET['token']) || !hash_equals($token, (string) $_GET['token'])) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

$projectRoot = __DIR__;
$changed = 0;
$errors = [];

$removeTree = static function (string $path) use (&$errors, &$removeTree): void {
    if (!file_exists($path)) {
        return;
    }

    if (is_file($path) || is_link($path)) {
        if (!@unlink($path)) {
            $errors[] = 'unlink failed: ' . $path;
        }
        return;
    }

    $items = @scandir($path);
    if (!is_array($items)) {
        $errors[] = 'scandir failed before delete: ' . $path;
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $removeTree($path . DIRECTORY_SEPARATOR . $item);
    }

    if (!@rmdir($path)) {
        $errors[] = 'rmdir failed: ' . $path;
    }
};

$chmodTree = static function (string $path, int $directoryMode, int $fileMode) use (&$changed, &$errors, &$chmodTree): void {
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
                @chmod($entryPath, 0755);
            } elseif (is_file($entryPath)) {
                @chmod($entryPath, 0644);
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

$removeTree($projectRoot . '/var/cache/prod');

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
    exit(1);
}

echo "OK\n";
