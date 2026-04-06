<?php

namespace App\Service;

use App\Entity\ThemeSetting;
use App\Repository\ThemeSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;

class BIModuleSettingsService
{
    public const SETTING_KEY = 'bi_module_settings';

    private const ALLOWED_EXTENSIONS = ['csv', 'json'];

    public function __construct(
        private ThemeSettingRepository $themeSettingRepository,
        private EntityManagerInterface $em,
        private KernelInterface $kernel,
    ) {}

    public function getSettings(): array
    {
        $setting = $this->themeSettingRepository->findByKey(self::SETTING_KEY);
        if (!$setting instanceof ThemeSetting || trim((string) $setting->getSettingValue()) === '') {
            return $this->normalizeSettings([]);
        }

        try {
            $payload = json_decode((string) $setting->getSettingValue(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->normalizeSettings([]);
        }

        return $this->normalizeSettings(is_array($payload) ? $payload : []);
    }

    public function addRemoteSource(string $label, string $url): array
    {
        $normalizedUrl = trim($url);
        if ($normalizedUrl === '' || filter_var($normalizedUrl, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('URL SharePoint invalide.');
        }

        $extension = strtolower((string) pathinfo((string) parse_url($normalizedUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('Seuls les fichiers CSV et JSON sont supportes.');
        }

        $settings = $this->getSettings();
        $settings['remoteSources'][] = [
            'id' => $this->generateSourceId('remote'),
            'label' => trim($label) !== '' ? trim($label) : $this->humanize((string) pathinfo((string) parse_url($normalizedUrl, PHP_URL_PATH), PATHINFO_FILENAME)),
            'url' => $normalizedUrl,
            'extension' => $extension,
            'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        return $this->saveSettings($settings);
    }

    public function addUploadedSource(UploadedFile $file, string $label = ''): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('Seuls les fichiers CSV et JSON sont supportes.');
        }

        $directory = $this->getUploadedSourcesDirectory();
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de preparer le repertoire des sources BI.');
        }

        $baseName = $this->slugify((string) pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME));
        $targetName = $baseName . '-' . substr((string) bin2hex(random_bytes(4)), 0, 8) . '.' . $extension;
        $file->move($directory, $targetName);

        $relativePath = 'site-imports/' . $targetName;
        $settings = $this->getSettings();
        $settings['uploadedSources'][] = [
            'id' => $this->generateSourceId('upload'),
            'label' => trim($label) !== '' ? trim($label) : $this->humanize($baseName),
            'fileName' => $targetName,
            'path' => $relativePath,
            'extension' => $extension,
            'uploadedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        return $this->saveSettings($settings);
    }

    public function removeSource(string $sourceId): array
    {
        $sourceId = trim($sourceId);
        $settings = $this->getSettings();

        foreach ($settings['uploadedSources'] as $index => $source) {
            if ((string) ($source['id'] ?? '') !== $sourceId) {
                continue;
            }

            $path = $this->resolveStoragePath((string) ($source['path'] ?? ''));
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }

            unset($settings['uploadedSources'][$index]);

            return $this->saveSettings($settings);
        }

        foreach ($settings['remoteSources'] as $index => $source) {
            if ((string) ($source['id'] ?? '') !== $sourceId) {
                continue;
            }

            unset($settings['remoteSources'][$index]);

            return $this->saveSettings($settings);
        }

        return $settings;
    }

    public function resolveStoragePath(string $relativePath): string
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath));
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return '';
        }

        return $this->kernel->getProjectDir() . '/data/sharepoint/' . ltrim($relativePath, '/');
    }

    private function saveSettings(array $settings): array
    {
        $normalized = $this->normalizeSettings($settings);
        $setting = $this->themeSettingRepository->findByKey(self::SETTING_KEY);
        if (!$setting instanceof ThemeSetting) {
            $setting = (new ThemeSetting())->setSettingKey(self::SETTING_KEY);
            $this->em->persist($setting);
        }

        $setting->setSettingValue((string) json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        $this->em->flush();

        return $normalized;
    }

    private function normalizeSettings(array $settings): array
    {
        $normalized = [
            'uploadedSources' => [],
            'remoteSources' => [],
        ];

        foreach ((array) ($settings['uploadedSources'] ?? []) as $source) {
            if (!is_array($source)) {
                continue;
            }

            $id = $this->normalizeScalar($source['id'] ?? '', 80);
            $path = $this->normalizeScalar($source['path'] ?? '', 255);
            $extension = strtolower($this->normalizeScalar($source['extension'] ?? '', 10));
            if ($id === '' || $path === '' || !in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                continue;
            }

            $normalized['uploadedSources'][] = [
                'id' => $id,
                'label' => $this->normalizeScalar($source['label'] ?? '', 120),
                'fileName' => basename($this->normalizeScalar($source['fileName'] ?? '', 160)),
                'path' => ltrim(str_replace('\\', '/', $path), '/'),
                'extension' => $extension,
                'uploadedAt' => $this->normalizeScalar($source['uploadedAt'] ?? '', 80),
            ];
        }

        foreach ((array) ($settings['remoteSources'] ?? []) as $source) {
            if (!is_array($source)) {
                continue;
            }

            $id = $this->normalizeScalar($source['id'] ?? '', 80);
            $url = trim((string) ($source['url'] ?? ''));
            $extension = strtolower($this->normalizeScalar($source['extension'] ?? '', 10));
            if (
                $id === ''
                || $url === ''
                || filter_var($url, FILTER_VALIDATE_URL) === false
                || !in_array($extension, self::ALLOWED_EXTENSIONS, true)
            ) {
                continue;
            }

            $normalized['remoteSources'][] = [
                'id' => $id,
                'label' => $this->normalizeScalar($source['label'] ?? '', 120),
                'url' => $url,
                'extension' => $extension,
                'createdAt' => $this->normalizeScalar($source['createdAt'] ?? '', 80),
            ];
        }

        return $normalized;
    }

    private function getUploadedSourcesDirectory(): string
    {
        return $this->kernel->getProjectDir() . '/data/sharepoint/site-imports';
    }

    private function normalizeScalar(mixed $value, int $maxLength): string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '';
        }

        return mb_substr($normalized, 0, $maxLength);
    }

    private function generateSourceId(string $prefix): string
    {
        return $prefix . '-' . substr((string) bin2hex(random_bytes(6)), 0, 12);
    }

    private function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', (string) $value);

        return trim((string) $value, '-') ?: 'source';
    }

    private function humanize(string $value): string
    {
        $value = str_replace(['_', '-'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', (string) $value);

        return trim((string) $value);
    }
}
