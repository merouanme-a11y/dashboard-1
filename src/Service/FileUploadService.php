<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileUploadService
{
    private const PROFILE_PHOTO_ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];
    private const EDITOR_IMAGE_ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    private const EDITOR_FILE_ALLOWED_MIME_TYPES = [
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/zip' => 'zip',
        'application/x-zip-compressed' => 'zip',
        'application/vnd.rar' => 'rar',
        'application/x-rar-compressed' => 'rar',
        'application/x-7z-compressed' => '7z',
    ];
    private const EDITOR_MAX_UPLOAD_SIZE = 10485760;

    public function __construct(
        private string $uploadDir,
        private SluggerInterface $slugger,
        private Filesystem $filesystem,
    ) {}

    public function uploadProfilePhoto(UploadedFile $file, int|string $userId): string
    {
        $extension = $this->validateProfilePhoto($file);

        return $this->uploadFile($file, 'images/profiles', 'profile_' . $userId, 'uploads/images/profiles', $extension);
    }

    public function uploadThemeLogo(UploadedFile $file): string
    {
        if (!$this->validateSvgSafety($file)) {
            throw new \RuntimeException('Le fichier SVG contient des elements non autorises.');
        }

        return $this->uploadFile($file, 'images/theme', 'site_logo', 'uploads/images/theme');
    }

    public function uploadEditorAsset(UploadedFile $file, string $kind = 'file'): array
    {
        $kind = $kind === 'image' ? 'image' : 'file';
        if (!$file->isValid()) {
            throw new \RuntimeException('Le fichier envoye est invalide.');
        }

        $size = (int) ($file->getSize() ?? 0);
        if ($size <= 0) {
            throw new \RuntimeException('Le fichier est vide ou invalide.');
        }

        if ($size > self::EDITOR_MAX_UPLOAD_SIZE) {
            throw new \RuntimeException('Fichier trop volumineux (max 10 Mo).');
        }

        $originalName = $this->sanitizeClientFilename($file->getClientOriginalName());
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mime = $this->detectMime($file->getPathname());

        $allowedMimeTypes = $kind === 'image'
            ? self::EDITOR_IMAGE_ALLOWED_MIME_TYPES
            : self::EDITOR_FILE_ALLOWED_MIME_TYPES + self::EDITOR_IMAGE_ALLOWED_MIME_TYPES;
        $allowedExtensions = array_values($allowedMimeTypes);

        if ($extension === '') {
            $extension = $allowedMimeTypes[$mime] ?? '';
        }

        if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
            throw new \RuntimeException('Extension non autorisee.');
        }

        if (!isset($allowedMimeTypes[$mime])) {
            throw new \RuntimeException('Type MIME non autorise.');
        }

        $storedPath = $this->uploadFile(
            $file,
            $kind === 'image' ? 'images/editor' : 'files/editor',
            $kind === 'image' ? 'editor_image' : 'editor_file',
            $kind === 'image' ? 'uploads/images/editor' : 'uploads/files/editor',
            $extension,
        );

        return [
            'path' => $storedPath,
            'name' => $originalName !== '' ? $originalName : basename($storedPath),
            'mime' => $mime,
            'size' => $size,
        ];
    }

    public function deleteFile(string $relativePath): void
    {
        if (empty($relativePath)) {
            return;
        }

        $normalizedPath = $this->resolvePublicPath($relativePath);
        if ($normalizedPath === '' || preg_match('#^https?://#i', $normalizedPath)) {
            return;
        }

        $pathInsideUploadDir = preg_replace('#^uploads/#', '', $normalizedPath, 1) ?? ltrim($normalizedPath, '/');
        $fullPath = $this->uploadDir . '/' . ltrim($pathInsideUploadDir, '/');

        // Security check
        $realPath = realpath($fullPath);
        $realUploadDir = realpath($this->uploadDir);

        if ($realPath && $realUploadDir && strpos($realPath, $realUploadDir) === 0) {
            $this->filesystem->remove($realPath);
        }
    }

    public function validateSvgSafety(UploadedFile $file): bool
    {
        if ($file->getMimeType() !== 'image/svg+xml') {
            return true;
        }

        $content = file_get_contents($file->getPathname());
        if (!$content) {
            return false;
        }

        // Block dangerous SVG patterns
        $unsafePatterns = [
            '/<script\b/i',
            '/<foreignObject\b/i',
            '/\bon[a-z]+\s*=\s*/i',
            '/javascript\s*:/i',
        ];

        foreach ($unsafePatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return false;
            }
        }

        return true;
    }

    public function detectMime(string $filePath): string
    {
        if (class_exists('finfo')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($filePath);
            if ($mime && $mime !== '') {
                return $mime;
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($filePath);
            if ($mime && $mime !== '') {
                return $mime;
            }
        }

        $imageInfo = @getimagesize($filePath);
        if (is_array($imageInfo) && !empty($imageInfo['mime'])) {
            return $imageInfo['mime'];
        }

        return 'application/octet-stream';
    }

    public function resolvePublicPath(?string $path): string
    {
        $path = trim(str_replace('\\', '/', (string) $path));
        if ($path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'uploads/')) {
            return $path;
        }

        if (str_starts_with($path, 'images/')) {
            return 'uploads/' . $path;
        }

        return 'uploads/images/profiles/' . ltrim(basename($path), '/');
    }

    private function validateProfilePhoto(UploadedFile $file): string
    {
        if (!$file->isValid()) {
            throw new \RuntimeException('Le fichier de photo est invalide.');
        }

        $mime = $this->detectMime($file->getPathname());
        if (!isset(self::PROFILE_PHOTO_ALLOWED_MIME_TYPES[$mime])) {
            throw new \RuntimeException('Format image non supporte. Utilisez JPG ou PNG.');
        }

        return self::PROFILE_PHOTO_ALLOWED_MIME_TYPES[$mime];
    }

    private function sanitizeClientFilename(string $filename): string
    {
        $filename = preg_replace('/[^\w.\-]+/u', '_', trim($filename)) ?? '';
        $filename = trim($filename, '._');

        return $filename !== '' ? $filename : 'fichier';
    }

    private function uploadFile(
        UploadedFile $file,
        string $subdir,
        string $namePrefix,
        ?string $storedSubdir = null,
        ?string $forcedExtension = null,
    ): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $forcedExtension ?: $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';
        $safeFilename = $this->slugger->slug($originalFilename)->toString();
        $timestamp = date('YmdHis');
        $suffix = $safeFilename !== '' ? '_' . $safeFilename : '';
        $newFilename = $namePrefix . '_' . $timestamp . $suffix . '.' . strtolower($extension);
        $storedSubdir = trim((string) ($storedSubdir ?: $subdir), '/');

        $targetDir = $this->uploadDir . '/' . $subdir;
        $this->filesystem->mkdir($targetDir);

        $file->move($targetDir, $newFilename);

        $storedPath = $storedSubdir . '/' . $newFilename;
        $absolutePath = $targetDir . '/' . $newFilename;
        if (!is_file($absolutePath)) {
            throw new \RuntimeException('La photo n\'a pas pu etre verifiee apres l\'upload.');
        }

        return $storedPath;
    }
}
