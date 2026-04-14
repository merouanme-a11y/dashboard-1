<?php

namespace App\Service;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SharePointDataService
{
    private const SUPPORTED_EXTENSIONS = ['csv', 'json', 'xls', 'xlsx'];
    private const SAMPLE_ROW_LIMIT = 120;
    private const DATASET_CACHE_FRESH_TTL = 300;
    private const DATASET_CACHE_STALE_TTL = 900;

    public function __construct(
        private KernelInterface $kernel,
        private HttpClientInterface $httpClient,
        private BIModuleSettingsService $biModuleSettingsService,
        private MicrosoftGraphAuthService $microsoftGraphAuthService,
        private ApiResultCacheService $apiResultCache,
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

        foreach ((array) ($settings['apiSources'] ?? []) as $source) {
            $connections[] = [
                'id' => (string) ($source['id'] ?? ''),
                'label' => (string) (($source['label'] ?? '') !== '' ? $source['label'] : 'Webservice JSON'),
                'type' => 'api-webservice',
                'url' => (string) ($source['url'] ?? ''),
                'extension' => 'json',
                'description' => 'Webservice JSON authentifie par token',
                'createdAt' => (string) ($source['createdAt'] ?? ''),
            ];
        }

        usort($connections, static fn (array $left, array $right): int => strcasecmp((string) $left['label'], (string) $right['label']));

        return $connections;
    }

    public function getAvailableFiles(string $connectionId): array
    {
        $connection = $this->findConnection($connectionId, true);
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
            $fileName = $this->resolveRemoteSourceFileName($connection);

            return [[
                'id' => $fileName !== '' ? $fileName : 'source.' . (string) ($connection['extension'] ?? 'csv'),
                'name' => $this->humanize((string) pathinfo($fileName !== '' ? $fileName : ((string) ($connection['label'] ?? 'Source distante')), PATHINFO_FILENAME)),
                'extension' => strtolower((string) ($connection['extension'] ?? pathinfo($fileName, PATHINFO_EXTENSION))),
                'size' => 0,
                'updatedAt' => (string) ($connection['createdAt'] ?? null),
            ]];
        }

        if (($connection['type'] ?? '') === 'api-webservice') {
            $urlPath = (string) parse_url((string) ($connection['url'] ?? ''), PHP_URL_PATH);
            $fileName = basename($urlPath);
            $baseName = $fileName !== '' ? (string) pathinfo($fileName, PATHINFO_FILENAME) : ((string) ($connection['label'] ?? 'webservice'));

            return [[
                'id' => 'dataset.json',
                'name' => $this->humanize($baseName),
                'extension' => 'json',
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

    public function peekDatasetPayload(string $connectionId, string $fileId): ?array
    {
        $connection = $this->findConnection($connectionId, true);
        if ($connection === null) {
            return null;
        }

        return $this->apiResultCache->peek($this->buildDatasetCacheKey($connection, $fileId));
    }

    public function getDatasetPayload(string $connectionId, string $fileId, bool $forceRefresh = false): array
    {
        $connection = $this->findConnection($connectionId, true);
        if ($connection === null) {
            return ['_error' => 'Source BI introuvable.'];
        }

        $cacheKey = $this->buildDatasetCacheKey($connection, $fileId);
        if (!$forceRefresh) {
            $cachedPayload = $this->apiResultCache->peek($cacheKey);
            if (is_array($cachedPayload)) {
                return $cachedPayload;
            }
        } else {
            $this->apiResultCache->delete($cacheKey);
        }

        $payload = $this->buildDatasetPayload($connection, $fileId);
        if (($payload['_error'] ?? '') !== '') {
            return $payload;
        }

        return $this->apiResultCache->store(
            $cacheKey,
            $payload,
            self::DATASET_CACHE_FRESH_TTL,
            self::DATASET_CACHE_STALE_TTL,
        );
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

    private function buildDatasetPayload(array $connection, string $fileId): array
    {
        if (($connection['type'] ?? '') === 'site-upload') {
            return $this->getLocalUploadedDatasetPayload($connection);
        }

        if (($connection['type'] ?? '') === 'sharepoint-url') {
            return $this->getRemoteDatasetPayload($connection);
        }

        if (($connection['type'] ?? '') === 'api-webservice') {
            return $this->getApiDatasetPayload($connection);
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
        $rows = $this->parseRowsFromFileByExtension($filePath, $extension);
        if ($rows === null) {
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

        $extension = strtolower((string) ($connection['extension'] ?? pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)));
        if (!in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
            return ['_error' => 'Format de fichier non supporte.'];
        }

        $content = null;
        $contentType = '';
        $authenticatedDownloadError = '';
        $downloadUrl = $this->resolveRemoteDownloadUrl($url);

        if ($this->looksLikeSharePointSharedFileUrl($url)) {
            $curlDownload = $this->tryCurlRemoteDownload($downloadUrl, true);
            if ((!is_array($curlDownload) || (int) ($curlDownload['status'] ?? 0) < 200 || (int) ($curlDownload['status'] ?? 0) >= 400) && !$this->microsoftGraphAuthService->hasConnectedAccount()) {
                $curlDownload = $this->tryCurlRemoteDownload($downloadUrl, false);
            }
            if (is_array($curlDownload) && (int) ($curlDownload['status'] ?? 0) >= 200 && (int) ($curlDownload['status'] ?? 0) < 400) {
                $content = (string) ($curlDownload['content'] ?? '');
                $contentType = strtolower((string) ($curlDownload['contentType'] ?? ''));
            }
        }

        if ($this->isMicrosoftSharePointUrl($url) && $this->microsoftGraphAuthService->hasConnectedAccount()) {
            try {
                $download = $this->microsoftGraphAuthService->downloadSharedFile($url);
                $content = (string) ($download['content'] ?? '');
                $contentType = strtolower((string) ($download['contentType'] ?? ''));
            } catch (\Throwable $exception) {
                $authenticatedDownloadError = $exception->getMessage();
            }
        }

        if ($content === null) {
            try {
                $response = $this->requestRemoteSource($downloadUrl, true);
                $statusCode = $response->getStatusCode();
                $headers = $response->getHeaders(false);
                $contentType = strtolower((string) ($headers['content-type'][0] ?? ''));
                $content = $response->getContent(false);
            } catch (\Throwable $exception) {
                $allowInsecureRetry = $this->isSslCertificateError($exception);
                $curlDownload = $this->tryCurlRemoteDownload($downloadUrl, !$allowInsecureRetry);
                if ((!is_array($curlDownload) || (int) ($curlDownload['status'] ?? 0) < 200 || (int) ($curlDownload['status'] ?? 0) >= 400) && $allowInsecureRetry) {
                    $curlDownload = $this->tryCurlRemoteDownload($downloadUrl, false);
                }
                if (is_array($curlDownload) && (int) ($curlDownload['status'] ?? 0) > 0 && (int) ($curlDownload['status'] ?? 0) < 400) {
                    $content = (string) ($curlDownload['content'] ?? '');
                    $contentType = strtolower((string) ($curlDownload['contentType'] ?? ''));
                } else {
                    $transportError = trim($exception->getMessage());
                    if ($authenticatedDownloadError !== '') {
                        return ['_error' => $authenticatedDownloadError];
                    }

                    return ['_error' => $transportError !== ''
                        ? 'Impossible de lire la source SharePoint distante. Detail: ' . $transportError
                        : 'Impossible de lire la source SharePoint distante.'];
                }
            }

            if ($content !== null && !isset($statusCode)) {
                $statusCode = 200;
                $headers = ['content-type' => [$contentType]];
            }

            if (($statusCode ?? 0) >= 400) {
                $curlDownload = $this->tryCurlRemoteDownload($downloadUrl, true);
                if (is_array($curlDownload) && (int) ($curlDownload['status'] ?? 0) > 0 && (int) ($curlDownload['status'] ?? 0) < 400) {
                    $content = (string) ($curlDownload['content'] ?? '');
                    $contentType = strtolower((string) ($curlDownload['contentType'] ?? ''));
                    $statusCode = 200;
                }
            }

            if (($statusCode ?? 0) >= 400) {
                if ($authenticatedDownloadError !== '') {
                    return ['_error' => $authenticatedDownloadError];
                }

                return ['_error' => $this->buildRemoteSourceHttpError((int) $statusCode, $headers ?? [], $url)];
            }
        }

        if ($this->shouldRejectHtmlPayload($content, $contentType, $extension)) {
            $curlDownload = $this->tryCurlRemoteDownload($downloadUrl, true);
            if (is_array($curlDownload)) {
                $curlContent = (string) ($curlDownload['content'] ?? '');
                $curlContentType = strtolower((string) ($curlDownload['contentType'] ?? ''));

                if ($curlContent !== '' && !$this->shouldRejectHtmlPayload($curlContent, $curlContentType, $extension)) {
                    $content = $curlContent;
                    $contentType = $curlContentType;
                }
            }
        }

        if ($this->shouldRejectHtmlPayload($content, $contentType, $extension)) {
            if ($authenticatedDownloadError !== '') {
                return ['_error' => $authenticatedDownloadError];
            }

            return ['_error' => 'La source SharePoint distante renvoie une page HTML Microsoft au lieu du fichier CSV, Excel ou JSON. Utilisez un lien de telechargement direct/public ou connectez votre compte Microsoft dans les parametres BI.'];
        }

        $rows = $this->parseRowsFromContentByExtension($content, $extension);
        if ($rows === null) {
            return ['_error' => 'Format de fichier non supporte.'];
        }

        $columns = $this->analyzeColumns($rows);
        $fileName = $this->resolveRemoteSourceFileName($connection);

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

    private function getApiDatasetPayload(array $connection): array
    {
        $url = trim((string) ($connection['url'] ?? ''));
        if ($url === '') {
            return ['_error' => 'URL API manquante.'];
        }

        $token = trim((string) ($connection['token'] ?? ''));
        if ($token === '') {
            return ['_error' => 'Le token du webservice est manquant.'];
        }

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ],
                'timeout' => 30,
                'verify_peer' => false,
                'verify_host' => false,
            ]);
            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (\Throwable) {
            return ['_error' => 'Impossible de joindre le webservice BI.'];
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            return ['_error' => $this->buildApiSourceHttpError($statusCode, $content)];
        }

        try {
            $payload = json_decode($this->normalizeTextEncoding($content), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return ['_error' => 'Le webservice BI doit renvoyer du JSON valide.'];
        }

        if (!is_array($payload)) {
            return ['_error' => 'Le webservice BI doit renvoyer un objet ou une liste JSON.'];
        }

        $rows = $this->normalizeJsonPayload($payload);
        $columns = $this->analyzeColumns($rows);
        $urlPath = (string) parse_url($url, PHP_URL_PATH);
        $fileName = basename($urlPath);
        $baseName = $fileName !== '' ? (string) pathinfo($fileName, PATHINFO_FILENAME) : ((string) ($connection['label'] ?? 'webservice'));

        return [
            'connection' => [
                'id' => (string) ($connection['id'] ?? ''),
                'label' => (string) ($connection['label'] ?? ''),
                'type' => 'api-webservice',
                'description' => (string) ($connection['description'] ?? ''),
            ],
            'file' => [
                'id' => 'dataset.json',
                'name' => $this->humanize($baseName),
                'extension' => 'json',
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
        $content = $this->normalizeTextEncoding($content);
        $content = $this->stripUtf8Bom($content);
        $delimiter = $this->detectCsvDelimiter($content);
        $handle = fopen('php://temp', 'r+b');
        if ($handle === false) {
            return [];
        }

        fwrite($handle, $content);
        rewind($handle);

        $headers = [];
        $rows = [];

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $data = array_map(fn ($value): string => $this->normalizeTextEncoding(trim((string) $value)), $data);
            if ($this->isCsvRowEmpty($data)) {
                continue;
            }

            if ($headers === []) {
                $headers = $this->normalizeHeaders($data);
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = isset($data[$index]) ? $this->normalizeTextEncoding(trim((string) $data[$index])) : '';
            }

            if (implode('', $row) !== '') {
                $rows[] = $row;
            }
        }

        fclose($handle);

        return $rows;
    }

    private function parseJsonFile(string $filePath): array
    {
        try {
            $content = file_get_contents($filePath);
            if (!is_string($content)) {
                return [];
            }

            $payload = json_decode($this->normalizeTextEncoding($content), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        return $this->normalizeJsonPayload($payload);
    }

    private function parseJsonContent(string $content): array
    {
        try {
            $payload = json_decode($this->normalizeTextEncoding($content), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        return $this->normalizeJsonPayload($payload);
    }

    private function parseRowsFromFileByExtension(string $filePath, string $extension): ?array
    {
        return match ($extension) {
            'csv' => $this->parseCsvFile($filePath),
            'json' => $this->parseJsonFile($filePath),
            'xls' => $this->tabularRowsToRecords($this->parseXlsRows($filePath)),
            'xlsx' => $this->tabularRowsToRecords($this->parseXlsxRows($filePath)),
            default => null,
        };
    }

    private function parseRowsFromContentByExtension(string $content, string $extension): ?array
    {
        return match ($extension) {
            'csv' => $this->parseCsvContent($content),
            'json' => $this->parseJsonContent($content),
            'xls', 'xlsx' => $this->parseSpreadsheetContent($content, $extension),
            default => null,
        };
    }

    private function parseSpreadsheetContent(string $content, string $extension): array
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'bi-remote-');
        if ($temporaryPath === false) {
            return [];
        }

        try {
            if (@file_put_contents($temporaryPath, $content) === false) {
                return [];
            }

            return (array) ($this->parseRowsFromFileByExtension($temporaryPath, $extension) ?? []);
        } finally {
            @unlink($temporaryPath);
        }
    }

    /**
     * @param array<int, array<int, string>> $rows
     * @return array<int, array<string, string>>
     */
    private function tabularRowsToRecords(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $headers = $this->normalizeHeaders(array_shift($rows) ?: []);
        if ($headers === []) {
            return [];
        }

        $records = [];
        foreach ($rows as $line) {
            $record = [];
            foreach ($headers as $index => $header) {
                $record[$header] = $this->normalizeTextEncoding(trim((string) ($line[$index] ?? '')));
            }

            if (implode('', $record) !== '') {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function parseXlsRows(string $path): array
    {
        $content = @file_get_contents($path);
        if ($content === false || $content === '') {
            return [];
        }

        $content = $this->normalizeTextEncoding($content);

        $rows = [];
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($content);
        libxml_clear_errors();

        if ($loaded) {
            $xpath = new \DOMXPath($document);
            $trNodes = $xpath->query('//tr');
            if ($trNodes !== false) {
                foreach ($trNodes as $trNode) {
                    $line = [];
                    foreach ($trNode->childNodes as $cell) {
                        if (!($cell instanceof \DOMElement)) {
                            continue;
                        }

                        $tagName = strtolower($cell->tagName);
                        if ($tagName !== 'td' && $tagName !== 'th') {
                            continue;
                        }

                        $line[] = $this->normalizeTextEncoding(trim(html_entity_decode($cell->textContent ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                    }

                    if ($line !== []) {
                        $rows[] = $line;
                    }
                }
            }
        }

        if ($rows !== []) {
            return $rows;
        }

        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $rows[] = array_map(fn ($value): string => $this->normalizeTextEncoding(trim((string) $value)), explode("\t", $line));
        }

        return $rows;
    }

    private function excelColumnIndexFromReference(string $cellReference): int
    {
        if (!preg_match('/^[A-Z]+/i', $cellReference, $matches)) {
            return 0;
        }

        $letters = strtoupper((string) $matches[0]);
        $index = 0;
        $length = strlen($letters);

        for ($i = 0; $i < $length; ++$i) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return max(0, $index - 1);
    }

    /**
     * @return array<int, string>
     */
    private function parseXlsxSharedStrings(\ZipArchive $zipArchive): array
    {
        $xml = $zipArchive->getFromName('xl/sharedStrings.xml');
        if (!is_string($xml) || $xml === '') {
            return [];
        }

        $document = @simplexml_load_string($xml);
        if ($document === false) {
            return [];
        }

        $document->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $nodes = $document->xpath('//main:si');
        if (!is_array($nodes)) {
            return [];
        }

        $sharedStrings = [];
        foreach ($nodes as $node) {
            $node->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $texts = $node->xpath('.//main:t');
            if (!is_array($texts) || $texts === []) {
                $sharedStrings[] = '';
                continue;
            }

            $value = '';
            foreach ($texts as $textNode) {
                $value .= (string) $textNode;
            }

            $sharedStrings[] = $this->normalizeTextEncoding(trim($value));
        }

        return $sharedStrings;
    }

    private function resolveFirstWorksheetPath(\ZipArchive $zipArchive): ?string
    {
        $workbookXml = $zipArchive->getFromName('xl/workbook.xml');
        $relsXml = $zipArchive->getFromName('xl/_rels/workbook.xml.rels');
        if (!is_string($workbookXml) || !is_string($relsXml) || $workbookXml === '' || $relsXml === '') {
            return $zipArchive->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
        }

        $workbook = @simplexml_load_string($workbookXml);
        $rels = @simplexml_load_string($relsXml);
        if ($workbook === false || $rels === false) {
            return $zipArchive->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
        }

        $workbook->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $rels->registerXPathNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');

        $sheetNodes = $workbook->xpath('//main:sheets/main:sheet');
        $relationshipNodes = $rels->xpath('//rel:Relationship');
        if (!is_array($sheetNodes) || $sheetNodes === [] || !is_array($relationshipNodes)) {
            return $zipArchive->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
        }

        $relationshipMap = [];
        foreach ($relationshipNodes as $relationshipNode) {
            $attributes = $relationshipNode->attributes();
            $id = (string) ($attributes['Id'] ?? '');
            $target = (string) ($attributes['Target'] ?? '');
            if ($id !== '' && $target !== '') {
                $relationshipMap[$id] = 'xl/' . ltrim($target, '/');
            }
        }

        $sheetAttributes = $sheetNodes[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $relationshipId = (string) ($sheetAttributes['id'] ?? '');
        if ($relationshipId !== '' && isset($relationshipMap[$relationshipId])) {
            return $relationshipMap[$relationshipId];
        }

        return $zipArchive->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function parseXlsxRows(string $path): array
    {
        $zipArchive = new \ZipArchive();
        if ($zipArchive->open($path) !== true) {
            return [];
        }

        $worksheetPath = $this->resolveFirstWorksheetPath($zipArchive);
        if ($worksheetPath === null) {
            $zipArchive->close();

            return [];
        }

        $worksheetXml = $zipArchive->getFromName($worksheetPath);
        $sharedStrings = $this->parseXlsxSharedStrings($zipArchive);
        $zipArchive->close();

        if (!is_string($worksheetXml) || $worksheetXml === '') {
            return [];
        }

        $worksheet = @simplexml_load_string($worksheetXml);
        if ($worksheet === false) {
            return [];
        }

        $worksheet->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rowNodes = $worksheet->xpath('//main:sheetData/main:row');
        if (!is_array($rowNodes)) {
            return [];
        }

        $rows = [];
        foreach ($rowNodes as $rowNode) {
            $line = [];
            $rowNode->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $cellNodes = $rowNode->xpath('./main:c');
            if (!is_array($cellNodes)) {
                continue;
            }

            foreach ($cellNodes as $cellNode) {
                $attributes = $cellNode->attributes();
                $reference = (string) ($attributes['r'] ?? '');
                $type = (string) ($attributes['t'] ?? '');
                $index = $this->excelColumnIndexFromReference($reference);

                while (count($line) < $index) {
                    $line[] = '';
                }

                $value = '';
                if ($type === 'inlineStr') {
                    $cellNode->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                    $textNodes = $cellNode->xpath('./main:is/main:t');
                    if (is_array($textNodes)) {
                        foreach ($textNodes as $textNode) {
                            $value .= (string) $textNode;
                        }
                    }
                } else {
                    $rawValue = (string) ($cellNode->v ?? '');
                    if ($type === 's') {
                        $sharedIndex = (int) $rawValue;
                        $value = (string) ($sharedStrings[$sharedIndex] ?? '');
                    } else {
                        $value = $rawValue;
                    }
                }

                $line[$index] = $this->normalizeTextEncoding(trim($value));
            }

            if ($line !== []) {
                $rows[] = $line;
            }
        }

        return $rows;
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
        } elseif (is_array($payload['results'] ?? null)) {
            $records = $payload['results'];
        } elseif (is_array($payload['issues'] ?? null)) {
            $records = $payload['issues'];
        } elseif (is_array($payload['projects'] ?? null)) {
            $records = $payload['projects'];
        } elseif (is_array($payload['value'] ?? null)) {
            $records = $payload['value'];
        }

        $rows = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $row = [];
            foreach ($record as $key => $value) {
                if (str_starts_with((string) $key, '$')) {
                    continue;
                }

                if ((string) $key === 'customFields' && is_array($value)) {
                    foreach ($this->extractYouTrackCustomFields($value) as $customFieldKey => $customFieldValue) {
                        $row[$customFieldKey] = $customFieldValue;
                    }

                    continue;
                }

                $normalizedKey = $this->sanitizeHeader((string) $key);
                if ($normalizedKey === '') {
                    continue;
                }

                $row[$normalizedKey] = $this->normalizeJsonCellValue($value);
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
        $keys = [];
        foreach ($rows as $row) {
            foreach (array_keys((array) $row) as $key) {
                if (!in_array($key, $keys, true)) {
                    $keys[] = (string) $key;
                }
            }
        }

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

    /**
     * @param array<int, mixed> $customFields
     * @return array<string, string>
     */
    private function extractYouTrackCustomFields(array $customFields): array
    {
        $row = [];

        foreach ($customFields as $customField) {
            if (!is_array($customField)) {
                continue;
            }

            $fieldName = trim((string) ($customField['name'] ?? ''));
            if ($fieldName === '') {
                continue;
            }

            $normalizedKey = $this->sanitizeHeader($fieldName);
            if ($normalizedKey === '') {
                continue;
            }

            $row[$normalizedKey] = $this->extractYouTrackCustomFieldValue(
                $customField['value'] ?? null,
                (string) ($customField['$type'] ?? ''),
            );
        }

        return $row;
    }

    private function extractYouTrackCustomFieldValue(mixed $value, string $fieldType = ''): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            if (
                $fieldType !== ''
                && str_contains($fieldType, 'Date')
                && is_numeric((string) $value)
            ) {
                return $this->formatYouTrackTimestamp((string) $value);
            }

            return $this->normalizeTextEncoding(trim((string) $value));
        }

        if (is_array($value) && array_is_list($value)) {
            $parts = [];
            foreach ($value as $entry) {
                $displayValue = $this->extractYouTrackCustomFieldValue($entry, $fieldType);
                if ($displayValue !== '' && !in_array($displayValue, $parts, true)) {
                    $parts[] = $displayValue;
                }
            }

            return implode(' | ', $parts);
        }

        if (is_array($value)) {
            foreach (['presentation', 'localizedName', 'fullName', 'name', 'text', 'login', 'idReadable', 'shortName'] as $candidateKey) {
                $candidateValue = trim((string) ($value[$candidateKey] ?? ''));
                if ($candidateValue !== '') {
                    return $this->normalizeTextEncoding($candidateValue);
                }
            }
        }

        return $this->normalizeJsonCellValue($value);
    }

    private function normalizeJsonCellValue(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return $this->normalizeTextEncoding(trim((string) $value));
        }

        return $this->normalizeTextEncoding((string) json_encode($value, JSON_UNESCAPED_UNICODE));
    }

    private function formatYouTrackTimestamp(string $value): string
    {
        $timestamp = (int) trim($value);
        if ($timestamp <= 0) {
            return $this->normalizeTextEncoding(trim($value));
        }

        if ($timestamp > 9999999999) {
            $timestamp = (int) floor($timestamp / 1000);
        }

        try {
            return (new \DateTimeImmutable('@' . $timestamp))
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->format('Y-m-d');
        } catch (\Throwable) {
            return $this->normalizeTextEncoding(trim($value));
        }
    }

    private function sanitizeHeader(string $header): string
    {
        $normalized = $this->normalizeTextEncoding(trim(preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header));
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if (is_string($transliterated) && $transliterated !== '') {
            $normalized = $transliterated;
        }
        $normalized = preg_replace('/\s+/', '_', $normalized);
        $normalized = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $normalized);

        return trim((string) $normalized, '_');
    }

    private function findConnection(string $connectionId, bool $includeSecrets = false): ?array
    {
        $normalizedId = trim($connectionId);
        if ($normalizedId === '') {
            return null;
        }

        $rootDirectory = $this->resolveDataDirectory();
        if (is_dir($rootDirectory)) {
            foreach ((array) scandir($rootDirectory) as $entry) {
                if (!is_string($entry) || $entry === '.' || $entry === '..' || $entry === 'site-imports') {
                    continue;
                }

                if (!is_dir($rootDirectory . DIRECTORY_SEPARATOR . $entry)) {
                    continue;
                }

                if ($this->slugify($entry) === $normalizedId) {
                    return [
                        'id' => $this->slugify($entry),
                        'label' => $this->humanize($entry),
                        'type' => 'sharepoint-folder',
                        'path' => $entry,
                        'description' => 'Source SharePoint synchronisee localement',
                    ];
                }
            }
        }

        $settings = $this->biModuleSettingsService->getSettings($includeSecrets);

        foreach ((array) ($settings['uploadedSources'] ?? []) as $source) {
            if ((string) ($source['id'] ?? '') !== $normalizedId) {
                continue;
            }

            return [
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
            if ((string) ($source['id'] ?? '') !== $normalizedId) {
                continue;
            }

            return [
                'id' => (string) ($source['id'] ?? ''),
                'label' => (string) (($source['label'] ?? '') !== '' ? $source['label'] : 'URL SharePoint'),
                'type' => 'sharepoint-url',
                'url' => (string) ($source['url'] ?? ''),
                'extension' => (string) ($source['extension'] ?? ''),
                'description' => 'Fichier SharePoint distant',
                'createdAt' => (string) ($source['createdAt'] ?? ''),
            ];
        }

        foreach ((array) ($settings['apiSources'] ?? []) as $source) {
            if ((string) ($source['id'] ?? '') !== $normalizedId) {
                continue;
            }

            $connection = [
                'id' => (string) ($source['id'] ?? ''),
                'label' => (string) (($source['label'] ?? '') !== '' ? $source['label'] : 'Webservice JSON'),
                'type' => 'api-webservice',
                'url' => (string) ($source['url'] ?? ''),
                'extension' => 'json',
                'description' => 'Webservice JSON authentifie par token',
                'createdAt' => (string) ($source['createdAt'] ?? ''),
            ];

            if ($includeSecrets) {
                $connection['token'] = (string) ($source['token'] ?? '');
            }

            return $connection;
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
        $value = $this->normalizeTextEncoding($value);
        $value = str_replace(['_', '-'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', (string) $value);

        return trim((string) $value);
    }

    private function normalizeTextEncoding(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $detectedEncoding = mb_detect_encoding($value, ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ISO-8859-15'], true);
        if (is_string($detectedEncoding) && $detectedEncoding !== '') {
            $converted = @mb_convert_encoding($value, 'UTF-8', $detectedEncoding);
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        foreach (['Windows-1252', 'ISO-8859-1', 'ISO-8859-15'] as $encoding) {
            $converted = @iconv($encoding, 'UTF-8//IGNORE', $value);
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        return @mb_convert_encoding($value, 'UTF-8', 'UTF-8') ?: $value;
    }

    private function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', (string) $value);

        return trim((string) $value, '-') ?: 'source';
    }

    private function stripUtf8Bom(string $content): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            return substr($content, 3);
        }

        return $content;
    }

    private function detectCsvDelimiter(string $content): string
    {
        $lines = array_values(array_filter(
            preg_split('/\R/u', $content) ?: [],
            static fn ($line): bool => trim((string) $line) !== ''
        ));
        if ($lines === []) {
            return ';';
        }

        $sampleLines = array_slice($lines, 0, 5);
        $bestDelimiter = ';';
        $bestScore = 0;

        foreach ([';', ',', "\t", '|'] as $candidate) {
            $score = 0;
            foreach ($sampleLines as $line) {
                $columnCount = count(str_getcsv((string) $line, $candidate));
                if ($columnCount > 1) {
                    $score += $columnCount;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestDelimiter = $candidate;
            }
        }

        return $bestScore > 0 ? $bestDelimiter : ';';
    }

    private function isCsvRowEmpty(array $data): bool
    {
        foreach ($data as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function shouldRejectHtmlPayload(string $content, string $contentType, string $extension): bool
    {
        if (!$this->looksLikeHtmlPayload($content, $contentType)) {
            return false;
        }

        if ($this->looksLikeSharePointLandingHtml($content)) {
            return true;
        }

        if ($extension === 'xls' && $this->looksLikeLegacySpreadsheetHtml($content)) {
            return false;
        }

        return $extension !== 'xls';
    }

    private function looksLikeHtmlPayload(string $content, string $contentType = ''): bool
    {
        $normalizedContentType = strtolower(trim($contentType));
        if (
            $normalizedContentType !== ''
            && (str_contains($normalizedContentType, 'text/html') || str_contains($normalizedContentType, 'application/xhtml'))
        ) {
            return true;
        }

        $prefix = strtolower(ltrim($this->stripUtf8Bom(substr($content, 0, 512))));
        if ($prefix === '') {
            return false;
        }

        return str_starts_with($prefix, '<!doctype html')
            || str_starts_with($prefix, '<html')
            || str_starts_with($prefix, '<!-- copyright (c) microsoft corporation');
    }

    private function looksLikeSharePointLandingHtml(string $content): bool
    {
        $prefix = strtolower(ltrim($this->stripUtf8Bom(substr($content, 0, 4096))));

        return str_contains($prefix, 'microsoft corporation')
            || str_contains($prefix, 'sharepoint')
            || str_contains($prefix, '_forms/default.aspx')
            || str_contains($prefix, 'login automatically')
            || str_contains($prefix, 'onedrive')
            || str_contains($prefix, 'download your files');
    }

    private function looksLikeLegacySpreadsheetHtml(string $content): bool
    {
        $prefix = strtolower(ltrim($this->stripUtf8Bom(substr($content, 0, 4096))));

        return str_contains($prefix, '<table')
            || str_contains($prefix, 'urn:schemas-microsoft-com:office:excel')
            || str_contains($prefix, 'xmlns:x="urn:schemas-microsoft-com:office:excel"');
    }

    private function isMicrosoftSharePointUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host !== '' && (str_ends_with($host, '.sharepoint.com') || $host === 'sharepoint.com');
    }

    private function resolveRemoteDownloadUrl(string $url): string
    {
        if (!$this->looksLikeSharePointSharedFileUrl($url)) {
            return $url;
        }

        $parts = parse_url($url);
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        $query['download'] = '1';

        $rebuilt = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
        if (($parts['path'] ?? '') !== '') {
            $rebuilt .= (string) $parts['path'];
        }
        $rebuilt .= '?' . http_build_query($query);

        return $rebuilt;
    }

    private function resolveRemoteSourceFileName(array $connection): string
    {
        $url = trim((string) ($connection['url'] ?? ''));
        $urlPath = (string) parse_url($url, PHP_URL_PATH);
        $fileName = basename($urlPath);
        if ($fileName !== '' && str_contains($fileName, '.')) {
            return $fileName;
        }

        $extension = strtolower((string) ($connection['extension'] ?? 'csv'));
        $label = trim((string) ($connection['label'] ?? 'source distante'));
        $baseName = $this->slugify($label);

        return $baseName . '.' . $extension;
    }

    private function looksLikeSharePointSharedFileUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);

        return $host !== ''
            && str_ends_with($host, '.sharepoint.com')
            && preg_match('#/\:([a-z])\:/#i', $path) === 1;
    }

    private function buildRemoteSourceHttpError(int $statusCode, array $headers, string $url): string
    {
        $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);
        if (
            in_array($statusCode, [401, 403], true)
            && (
                isset($normalizedHeaders['x-forms_based_auth_required'])
                || isset($normalizedHeaders['x-idcrl_auth_params_v1'])
                || isset($normalizedHeaders['www-authenticate'])
            )
        ) {
            if ($this->isMicrosoftSharePointUrl($url) && $this->microsoftGraphAuthService->isConfigured()) {
                if ($this->microsoftGraphAuthService->hasConnectedAccount()) {
                    return sprintf(
                        'La source SharePoint distante refuse encore l acces (HTTP %d). Reconnectez votre compte Microsoft dans les parametres BI ou verifiez que ce compte a bien acces au fichier.',
                        $statusCode
                    );
                }

                return sprintf(
                    'La source SharePoint distante refuse l acces au serveur du dashboard (HTTP %d). Connectez votre compte Microsoft dans les parametres BI pour lire ce fichier prive.',
                    $statusCode
                );
            }

            return sprintf(
                'La source SharePoint distante refuse l acces au serveur du dashboard (HTTP %d). Utilisez un lien de telechargement direct/public ou importez le fichier sur le site.',
                $statusCode
            );
        }

        if ($statusCode === 404) {
            return 'Le fichier SharePoint distant est introuvable (HTTP 404).';
        }

        return sprintf('Impossible de lire la source SharePoint distante (HTTP %d).', $statusCode);
    }

    private function requestRemoteSource(string $url, bool $verifyPeer): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return $this->httpClient->request('GET', $url, [
            'headers' => [
                'Accept' => 'text/csv,application/json,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain;q=0.9,*/*;q=0.8',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36',
            ],
            'max_redirects' => 10,
            'verify_peer' => $verifyPeer,
            'verify_host' => $verifyPeer,
        ]);
    }

    private function tryCurlRemoteDownload(string $url, bool $verifyPeer = true): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $handle = curl_init($url);
        if ($handle === false) {
            return null;
        }

        $cookieFile = tempnam(sys_get_temp_dir(), 'bi-sharepoint-cookie-');
        if ($cookieFile === false) {
            $cookieFile = null;
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => $verifyPeer,
            CURLOPT_SSL_VERIFYHOST => $verifyPeer ? 2 : 0,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: text/csv,application/json,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain;q=0.9,*/*;q=0.8',
            ],
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEFILE => $cookieFile ?: '',
            CURLOPT_COOKIEJAR => $cookieFile ?: '',
        ]);

        $rawResponse = curl_exec($handle);
        if (!is_string($rawResponse) || $rawResponse === '') {
            curl_close($handle);
            if (is_string($cookieFile) && $cookieFile !== '' && is_file($cookieFile)) {
                @unlink($cookieFile);
            }

            return null;
        }

        $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        $contentType = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);
        if (is_string($cookieFile) && $cookieFile !== '' && is_file($cookieFile)) {
            @unlink($cookieFile);
        }

        if ($headerSize <= 0) {
            return null;
        }

        return [
            'status' => $statusCode,
            'contentType' => $contentType,
            'content' => substr($rawResponse, $headerSize),
        ];
    }

    private function isSslCertificateError(\Throwable $exception): bool
    {
        $message = strtolower(trim($exception->getMessage()));

        return $message !== ''
            && (
                str_contains($message, 'certificate verify failed')
                || str_contains($message, 'ssl operation failed')
                || str_contains($message, 'unable to get local issuer certificate')
                || str_contains($message, 'peer certificate')
            );
    }

    private function buildApiSourceHttpError(int $statusCode, string $content): string
    {
        $detail = '';
        try {
            $payload = json_decode($this->normalizeTextEncoding($content), true, 512, JSON_THROW_ON_ERROR);
            if (is_array($payload)) {
                $detail = trim((string) ($payload['error_description'] ?? $payload['error'] ?? $payload['message'] ?? ''));
            }
        } catch (\Throwable) {
            $detail = trim($this->normalizeTextEncoding($content));
        }

        if (in_array($statusCode, [401, 403], true)) {
            return $detail !== ''
                ? sprintf('Le webservice BI refuse l acces (HTTP %d). Verifiez l URL API et le token. Detail: %s', $statusCode, $detail)
                : sprintf('Le webservice BI refuse l acces (HTTP %d). Verifiez l URL API et le token.', $statusCode);
        }

        if ($statusCode === 404) {
            return 'Le webservice BI est introuvable (HTTP 404). Verifiez l URL API.';
        }

        return $detail !== ''
            ? sprintf('Le webservice BI a renvoye HTTP %d. Detail: %s', $statusCode, $detail)
            : sprintf('Le webservice BI a renvoye HTTP %d.', $statusCode);
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
        $rows = $this->parseRowsFromFileByExtension($filePath, $extension);
        if ($rows === null) {
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

    private function buildDatasetCacheKey(array $connection, string $fileId): string
    {
        $type = (string) ($connection['type'] ?? '');
        $signature = [
            'type' => $type,
            'id' => (string) ($connection['id'] ?? ''),
            'file' => basename($fileId),
        ];

        if ($type === 'sharepoint-folder') {
            $filePath = $this->resolveDataDirectory()
                . DIRECTORY_SEPARATOR
                . (string) ($connection['path'] ?? '')
                . DIRECTORY_SEPARATOR
                . basename($fileId);

            $signature['path'] = $filePath;
            $signature['mtime'] = is_file($filePath) ? (int) filemtime($filePath) : 0;
            $signature['size'] = is_file($filePath) ? (int) filesize($filePath) : 0;
        } elseif ($type === 'site-upload') {
            $filePath = $this->biModuleSettingsService->resolveStoragePath((string) ($connection['path'] ?? ''));

            $signature['path'] = $filePath;
            $signature['mtime'] = ($filePath !== '' && is_file($filePath)) ? (int) filemtime($filePath) : 0;
            $signature['size'] = ($filePath !== '' && is_file($filePath)) ? (int) filesize($filePath) : 0;
        } elseif ($type === 'sharepoint-url') {
            $signature['url'] = (string) ($connection['url'] ?? '');
            $signature['extension'] = (string) ($connection['extension'] ?? '');
            $signature['createdAt'] = (string) ($connection['createdAt'] ?? '');
        } elseif ($type === 'api-webservice') {
            $signature['url'] = (string) ($connection['url'] ?? '');
            $signature['token'] = sha1((string) ($connection['token'] ?? ''));
            $signature['createdAt'] = (string) ($connection['createdAt'] ?? '');
        }

        $encodedSignature = json_encode($signature, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return 'bi_dataset_' . md5(is_string($encodedSignature) ? $encodedSignature : serialize($signature));
    }

    private function resolveLocalFileSize(string $relativePath): int
    {
        $path = $this->biModuleSettingsService->resolveStoragePath($relativePath);

        return ($path !== '' && is_file($path)) ? (int) filesize($path) : 0;
    }
}
