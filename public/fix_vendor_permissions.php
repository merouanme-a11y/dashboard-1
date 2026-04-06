<?php

declare(strict_types=1);

$token = 'fix-20260404-2140';

header('Content-Type: text/plain; charset=UTF-8');

if (!isset($_GET['token']) || !hash_equals($token, (string) $_GET['token'])) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

$projectRoot = dirname(__DIR__);
$targets = [
    $projectRoot . '/vendor',
    $projectRoot . '/var',
    $projectRoot . '/public/uploads',
    $projectRoot . '/public/modules/gantt-projects/export',
];

$dirMode = 0755;
$fileMode = 0644;
$writableDirMode = 0775;

$changed = 0;
$errors = [];

$applyTreePermissions = static function (string $path, int $directoryMode, int $regularFileMode) use (&$changed, &$errors): void {
    if (!file_exists($path)) {
        return;
    }

    if (is_dir($path)) {
        if (!@chmod($path, $directoryMode)) {
            $errors[] = 'chmod failed on dir: ' . $path;
        } else {
            $changed++;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $itemPath = $item->getPathname();
            $mode = $item->isDir() ? $directoryMode : $regularFileMode;

            if (!@chmod($itemPath, $mode)) {
                $errors[] = 'chmod failed on ' . ($item->isDir() ? 'dir' : 'file') . ': ' . $itemPath;
                continue;
            }

            $changed++;
        }

        return;
    }

    if (!@chmod($path, $regularFileMode)) {
        $errors[] = 'chmod failed on file: ' . $path;
        return;
    }

    $changed++;
};

foreach ($targets as $target) {
    $mode = str_contains($target, '/var') || str_contains($target, '/uploads') || str_contains($target, '/export')
        ? $writableDirMode
        : $dirMode;

    $applyTreePermissions($target, $mode, $fileMode);
}

foreach ([$projectRoot . '/.htaccess', $projectRoot . '/public/.htaccess'] as $file) {
    if (file_exists($file)) {
        if (!@chmod($file, $fileMode)) {
            $errors[] = 'chmod failed on file: ' . $file;
        } else {
            $changed++;
        }
    }
}

echo "Changed: {$changed}\n";

if ($errors !== []) {
    echo "Errors:\n";
    foreach ($errors as $error) {
        echo '- ' . $error . "\n";
    }
    exit(1);
}

echo "OK\n";
