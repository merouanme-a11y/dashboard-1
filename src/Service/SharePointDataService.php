<?php

namespace App\Service;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SharePointDataService
{
    private const SUPPORTED_EXTENSIONS = ['csv', 'json'];
    private const SAMPLE_ROW_LIMIT = 120;

    public function __construct(
        private KernelInterface $kernel,
        private HttpClientInterface $httpClient,
        private BIModuleSettingsService $biModuleSettingsService,
        private string $dataDirectory,
    ) {}

    public function getAvailableConnections(): array
    {
        $connections = [];
        $rootDirectory = $this->resolveDataDirectory();

        if (is_dir($rootDirectory)) {
            $directories = array_filter((array) scandir($rootDirectory), function ($entry) use ($rootDirectory): bool {
                if (!is_string($entry) || $entry === '.' || $entry === '..' || $entry === 'site-imports') {
                    return false;
                }

                return is_dir($rootDirectory . DIRECTORY_SEPARATOR . $entry);
            });

            foreach ($directories as $directory) {
                $connections[] = [
                    'id' => $this->slugify((string) $directory),
                    'label' => $this->humanize((string) $directory),
                    'type' => 'sharepoint-folder',
                    'path' => (string) $directory,
                    'description' => 'Source SharePoint synchronisee localement',
                ];
            }
        }

        $settings = $this->biModuleSettingsService->getSettings();
        foreach ((array) ($settings['uploadedSources'] ?? []) as $source) {
            $connections[] = [
                'id' => (string) ($source['id'] ?? ''),
                'label' => (string) (($source['label'] ?? '') !== '' ? $source['label'] : $source['fileName']),
                'type' => 'site-upload',
                'path' => (string) ($source['path'] ?? ''),
                'fileName' => (string) ($source['fileName'] ?? ''),
                'extension' => (string) ($source['extension'] ?? ''),
                'description' => 'Fichier importe dans le repertoire du site',
                'uploadedAt' => (string) ($source['uploadedAt'] ?? ''),
            ];
        }

        foreach ((array) ($settings['remoteSources'] ?? []) as $source) {
            $connections[] = [
                'id' => (string) ($source['id'] ?? ''),
                'label' => (string) (($source['label'] ?? '') !== '' ? $source['label'] : 'URL SharePoint'),
                'type' => 'sharepoint-url',
                'url' => (string) ($source['url'] ?? ''),
                'extension' => (string) ($source['extension'] ?? ''),
                'description' => 'Fichier SharePoint distant',
                'createdAt' => (string) ($source['createdAt'] ?? ''),
            ];
        }

        usort($connections, static fn (array $left, array $right): int => strcasecmp((string) $left['label'], (string) $right['label']));

        return $connections;
    }

    public function getAvailableFiles(string $connectionId): array
    {
        $connection = $this->findConnection($connectionId);
        if ($connection === null) {
            return [];
        }

        if (($connection['type'] ?? '') === 'site-upload') {
            return [[
                'id' => (string) ($connection['fileName'] ?? ''),
                'name' => $this->humanize((string) pathinfo((string) ($connection['fileName'] ?? ''), PATHINFO_FILENAME)),
                'extension' => strtolower((string) ($connection['extension'] ?? pathinfo((string) ($connection['fileName'] ?? ''), PATHINFO_EXTENSION))),
                'size' => $this->resolveLocalFileSize((string) ($connection['path'] ?? '')),
                'updatedAt' => (string) ($connection['uploadedAt'] ?? ''),
            ]];
        }

        if (($connection['type'] ?? '') === 'sharepoint-url') {
            $urlPath = (string) parse_url((string) ($connection['url'] ?? ''), PHP_URL_PATH);
            $fileName = basename($urlPath);

            return [[
                'id' => $fileName !== '' ? $fileName : 'source.' . (string) ($connection['extension'] ?? 'csv'),
                'name' => $this->humanize((string) pathinfo($fileName !== '' ? $fileName : ((string) ($connection['label'] ?? 'Source distante')), PATHINFO_FILENAME)),
                'extension' => strtolower((string) ($connection['extension'] ?? pathinfo($fileName, PATHINFO_EXTENSION))),
                'size' => 0,
                'updatedAt' => (string) ($connection['createdAt'] ?? null),
            ]];
        }

        $connectionPath = $this->resolveDataDirectory() . DIRECTORY_SEPARATOR . (string) $connection['path'];
        if (!is_dir($connectionPath)) {
            return [];
        }
        $files = [];

        foreach ((array) scandir($connectionPath) as $entry) {
            if (!is_string($entry) || $entry === '.' || $entry === '..') {
                continue;
            }

            $filePath = $connectionPath . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($filePath)) {
                continue;
            }

            $extension = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION));
            if (!in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
                continue;
            }

            $files[] = [
                'id' => $entry,
                'name' => $this->humanize((string) pathinfo($entry, PATHINFO_FILENAME)),
                'extension' => $extension,
                'size' => (int) (is_file($filePath) ? filesize($filePath) : 0),
                'updatedAt' => is_file($filePath) ? date(DATE_ATOM, (int) filemtime($filePath)) : null,
            ];
        }

        usort($files, static fn (array $left, array $right): int => strcasecmp((string) $left['name'], (string) $right['name']));

        return $files;
    }

    public function getDatasetPayload(string $connectionId, string $fileId): array
    {
        $connection = $this->findConnection($connectionId);
        if ($connection === null) {
            return ['_error' => 'Connexion SharePoint introuvable.'];
        }

        if (($connection['type'] ?? '') === 'site-upload') {
            return $this->getLocalUploadedDatasetPayload($connection);
        }

        if (($connection['type'] ?? '') === 'sharepoint-url') {
            return $this->getRemoteDatasetPayload($connection);
        }

        $filePath = $this->resolveDataDirectory()
            . DIRECTORY_SEPARATOR
            . (string) $connection['path']
            . DIRECTORY_SEPARATOR
            . basename($fileId);

        if (!is_file($filePath)) {
            return ['_error' => 'Fichier introuvable.'];
        }

        $extension = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION));
        if ($extension === 'csv') {
            $rows = $this->parseCsvFile($filePath);
        } elseif ($extension === 'json') {
            $rows = $this->parseJsonFile($filePath);
        } else {
            return ['_error' => 'Format de fichier non supporte.'];
        }

        $columns = $this->analyzeColumns($rows);

        return [
            'connection' => $connection,
            'file' => [
                'id' => basename($filePath),
                'name' => $this->humanize((string) pathinfo($filePath, PATHINFO_FILENAME)),
                'extension' => $extension,
                'updatedAt' => date(DATE_ATOM, (int) filemtime($filePath)),
                'size' => (int) filesize($filePath),
            ],
            'columns' => $columns,
            'rows' => $rows,
            'sampleRows' => array_slice($rows, 0, self::SAMPLE_ROW_LIMIT),
            'rowCount' => count($rows),
        ];
    }

    public function getSuggestedColumns(array $datasetPayload): array
    {
        $columns = is_array($datasetPayload['columns'] ?? null) ? $datasetPayload['columns'] : [];
        $firstNumeric = '';
        $firstCategory = '';
        $firstDate = '';

        foreach ($columns as $column) {
            $key = trim((string) ($column['key'] ?? ''));
            $type = trim((string) ($column['type'] ?? 'string'));
            if ($key === '') {
                continue;
            }

            if ($firstNumeric === '' && $type === 'number') {
                $firstNumeric = $key;
            }

            if ($firstDate === '' && $type === 'date') {
                $firstDate = $key;
            }

            if ($firstCategory === '' && in_array($type, ['string', 'boolean'], true)) {
                $firstCategory = $key;
            }
        }

        return [
            'numeric' => $firstNumeric,
            'category' => $firstCategory,
            'date' => $firstDate,
        ];
    }

    private function getLocalUploadedDatasetPayload(array $connection): array
    {
        $filePath = $this->biModuleSettingsService->resolveStoragePath((string) ($connection['path'] ?? ''));
        if ($filePath === '' || !is_file($filePath)) {
            return ['_error' => 'Fichier local introuvable.'];
        }

        return $this->buildDatasetPayloadFromFile(
            $filePath,
            (string) (($connection['fileName'] ?? '') !== '' ? $connection['fileName'] : basename($filePath)),
            [
                'id' => (string) ($connection['id'] ?? ''),
                'label' => (string) ($connection['label'] ?? ''),
                'type' => 'site-upload',
                'description' => (string) ($connection['description'] ?? ''),
            ],
        );
    }

    private function getRemoteDatasetPayload(array $connection): array
    {
        $url = trim((string) ($connection['url'] ?? ''));
        if ($url === '') {
            return ['_error' => 'URL SharePoint manquante.'];
        }

        try {
            $response = $this->httpClient->request('GET', $url);
            $content = $response->getContent();
        } catch (\Throwable) {
            return ['_error' => 'Impossible de lire la source SharePoint distante.'];
        }

        $extension = strtolower((string) ($connection['extension'] ?? pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)));
        if ($extension === 'csv') {
            $rows = $this->parseCsvContent($content);
        } elseif ($extension === 'json') {
            $rows = $this->parseJsonContent($content);
        } else {
            return ['_error' => 'Format de fichier non supporte.'];
        }

        $columns = $this->analyzeColumns($rows);
        $fileName = basename((string) parse_url($url, PHP_URL_PATH));

        return [
            'connection' => [
                'id' => (string) ($connection['id'] ?? ''),
                'label' => (string) ($connection['label'] ?? ''),
                'type' => 'sharepoint-url',
                'description' => (string) ($connection['description'] ?? ''),
            ],
            'file' => [
                'id' => $fileName !== '' ? $fileName : 'source.' . $extension,
                'name' => $this->humanize((string) pathinfo($fileName !== '' ? $fileName : ((string) ($connection['label'] ?? 'source')), PATHINFO_FILENAME)),
                'extension' => $extension,
                'updatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'size' => strlen($content),
            ],
            'columns' => $columns,
            'rows' => $rows,
            'sampleRows' => array_slice($rows, 0, self::SAMPLE_ROW_LIMIT),
            'rowCount' => count($rows),
        ];
    }

    private function parseCsvFile(string $filePath): array
    {
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return [];
        }

        return $this->parseCsvContent($content);
    }

    private function parseCsvContent(string $content): array
    {
        $handle = fopen('php://temp', 'r+b');
        if ($handle === false) {
            return [];
        }

        fwrite($handle, $content);
        rewind($handle);

        $headers = [];
        $rows = [];
        $rowIndex = 0;

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            if ($rowIndex === 0) {
                $headers = $this->normalizeHeaders($data);
                ++$rowIndex;
                continue;
            }

            if ($headers === []) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = isset($data[$index]) ? trim((string) $data[$index]) : '';
            }

            if (implode('', $row) !== '') {
                $rows[] = $row;
            }

            ++$rowIndex;
        }

        fclose($handle);

        return $rows;
    }

    private function parseJsonFile(string $filePath): array
    {
        try {
            $payload = json_decode((string) file_get_contents($filePath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        return $this->normalizeJsonPayload($payload);
    }

    private function parseJsonContent(string $content): array
    {
        try {
            $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        return $this->normalizeJsonPayload($payload);
    }

    private function normalizeJsonPayload(mixed $payload): array
    {
        $records = [];
        if (is_array($payload) && array_is_list($payload)) {
            $records = $payload;
        } elseif (is_array($payload['items'] ?? null)) {
            $records = $payload['items'];
        } elseif (is_array($payload['data'] ?? null)) {
            $records = $payload['data'];
        }

        $rows = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $row = [];
            foreach ($record as $key => $value) {
                $normalizedKey = $this->sanitizeHeader((string) $key);
                $row[$normalizedKey] = is_scalar($value) || $value === null ? trim((string) $value) : json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            if ($row !== []) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function analyzeColumns(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $columns = [];
        $keys = array_keys((array) ($rows[0] ?? []));

        foreach ($keys as $key) {
            $samples = [];
            $numericCount = 0;
            $dateCount = 0;
            $booleanCount = 0;
            $nonEmptyCount = 0;

            foreach ($rows as $row) {
                $value = trim((string) ($row[$key] ?? ''));
                if ($value === '') {
                    continue;
                }

                ++$nonEmptyCount;
                if (count($samples) < 5 && !in_array($value, $samples, true)) {
                    $samples[] = $value;
                }

                if (is_numeric(str_replace(',', '.', $value))) {
                    ++$numericCount;
                } elseif ($this->looksLikeDate($value)) {
                    ++$dateCount;
                } elseif (in_array(mb_strtolower($value), ['oui', 'non', 'true', 'false', '0', '1'], true)) {
                    ++$booleanCount;
                }
            }

            $type = 'string';
            if ($nonEmptyCount > 0 && $numericCount === $nonEmptyCount) {
                $type = 'number';
            } elseif ($nonEmptyCount > 0 && $dateCount >= (int) ceil($nonEmptyCount * 0.7)) {
                $type = 'date';
            } elseif ($nonEmptyCount > 0 && $booleanCount === $nonEmptyCount) {
                $type = 'boolean';
            }

            $columns[] = [
                'key' => (string) $key,
                'label' => $this->humanize((string) $key),
                'type' => $type,
                'samples' => $samples,
            ];
        }

        return $columns;
    }

    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        $seen = [];

        foreach ($headers as $header) {
            $key = $this->sanitizeHeader((string) $header);
            if ($key === '') {
                $key = 'colonne';
            }

            $baseKey = $key;
            $suffix = 2;
            while (isset($seen[$key])) {
                $key = $baseKey . '_' . $suffix;
                ++$suffix;
            }

            $normalized[] = $key;
            $seen[$key] = true;
        }

        return $normalized;
    }

    private function sanitizeHeader(string $header): string
    {
        $normalized = trim($header);
        $normalized = preg_replace('/\s+/', '_', $normalized);
        $normalized = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $normalized);

        return trim((string) $normalized, '_');
    }

    private function findConnection(string $connectionId): ?array
    {
        $normalizedId = trim($connectionId);
        if ($normalizedId === '') {
            return null;
        }

        foreach ($this->getAvailableConnections() as $connection) {
            if ((string) ($connection['id'] ?? '') === $normalizedId) {
                return $connection;
            }
        }

        return null;
    }

    private function resolveDataDirectory(): string
    {
        $configuredDirectory = trim($this->dataDirectory);
        if ($configuredDirectory !== '') {
            return $configuredDirectory;
        }

        return $this->kernel->getProjectDir() . '/data/sharepoint';
    }

    private function humanize(string $value): string
    {
        $value = str_replace(['_', '-'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', (string) $value);

        return trim((string) $value);
    }

    private function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', (string) $value);

        return trim((string) $value, '-') ?: 'source';
    }

    private function looksLikeDate(string $value): bool
    {
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($date instanceof \DateTimeImmutable) {
                return true;
            }
        }

        return false;
    }

    private function buildDatasetPayloadFromFile(string $filePath, string $fileName, array $connection): array
    {
        $extension = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION));
        if ($extension === 'csv') {
            $rows = $this->parseCsvFile($filePath);
        } elseif ($extension === 'json') {
            $rows = $this->parseJsonFile($filePath);
        } else {
            return ['_error' => 'Format de fichier non supporte.'];
        }

        $columns = $this->analyzeColumns($rows);

        return [
            'connection' => $connection,
            'file' => [
                'id' => basename($fileName),
                'name' => $this->humanize((string) pathinfo($fileName, PATHINFO_FILENAME)),
                'extension' => $extension,
                'updatedAt' => date(DATE_ATOM, (int) filemtime($filePath)),
                'size' => (int) filesize($filePath),
            ],
            'columns' => $columns,
            'rows' => $rows,
            'sampleRows' => array_slice($rows, 0, self::SAMPLE_ROW_LIMIT),
            'rowCount' => count($rows),
        ];
    }

    private function resolveLocalFileSize(string $relativePath): int
    {
        $path = $this->biModuleSettingsService->resolveStoragePath($relativePath);

        return ($path !== '' && is_file($path)) ? (int) filesize($path) : 0;
    }
}
