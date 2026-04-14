<?php

namespace App\Tests\Service;

use App\Service\ApiResultCacheService;
use App\Service\BIModuleSettingsService;
use App\Service\MicrosoftGraphAuthService;
use App\Service\SharePointDataService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpKernel\KernelInterface;

#[AllowMockObjectsWithoutExpectations]
final class SharePointDataServiceTest extends TestCase
{
    public function testApiWebserviceSourceUsesBearerTokenAndParsesJsonPayload(): void
    {
        $service = $this->createService(
            [
                new MockResponse('[{"state":"Open","assignee":"Merouan"},{"state":"Closed","assignee":"Sebastien"}]', [
                    'response_headers' => [
                        'content-type: application/json; charset=utf-8',
                    ],
                ]),
            ],
            [
                'uploadedSources' => [],
                'remoteSources' => [],
                'apiSources' => [[
                    'id' => 'api-youtrack',
                    'label' => 'YouTrack',
                    'url' => 'https://maintenance.adep.com/api/issues',
                    'token' => 'perm-token-123',
                    'extension' => 'json',
                    'createdAt' => '2026-04-09T00:00:00+00:00',
                ]],
            ],
        );

        $payload = $service->getDatasetPayload('api-youtrack', 'dataset.json');

        self::assertSame(2, $payload['rowCount'] ?? null);
        self::assertSame('Open', $payload['rows'][0]['state'] ?? null);
        self::assertSame('Sebastien', $payload['rows'][1]['assignee'] ?? null);
        self::assertSame('api-webservice', $payload['connection']['type'] ?? null);
    }

    public function testApiWebserviceSourceFlattensYouTrackCustomFieldsIntoColumns(): void
    {
        $service = $this->createService(
            [
                new MockResponse((string) json_encode([
                    [
                        'summary' => 'Ticket 1',
                        'customFields' => [
                            [
                                'name' => 'Priority',
                                'value' => ['name' => 'URGENT'],
                                '$type' => 'SingleEnumIssueCustomField',
                            ],
                            [
                                'name' => 'State',
                                'value' => ['name' => 'EN COURS'],
                                '$type' => 'StateIssueCustomField',
                            ],
                            [
                                'name' => 'Date échéance',
                                'value' => 1712664000000,
                                '$type' => 'DateIssueCustomField',
                            ],
                        ],
                    ],
                    [
                        'summary' => 'Ticket 2',
                        'customFields' => [
                            [
                                'name' => 'Assignee',
                                'value' => ['fullName' => 'Merouan HAMZAOUI', 'login' => 'Merouan'],
                                '$type' => 'SingleUserIssueCustomField',
                            ],
                            [
                                'name' => 'Type Action',
                                'value' => ['name' => 'Pret pour MEP'],
                                '$type' => 'SingleEnumIssueCustomField',
                            ],
                            [
                                'name' => 'Lien RM',
                                'value' => 'https://redmine.example/123',
                                '$type' => 'SimpleIssueCustomField',
                            ],
                        ],
                    ],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), [
                    'response_headers' => [
                        'content-type: application/json; charset=utf-8',
                    ],
                ]),
            ],
            [
                'uploadedSources' => [],
                'remoteSources' => [],
                'apiSources' => [[
                    'id' => 'api-youtrack',
                    'label' => 'YouTrack',
                    'url' => 'https://maintenance.adep.com/api/issues',
                    'token' => 'perm-token-123',
                    'extension' => 'json',
                    'createdAt' => '2026-04-09T00:00:00+00:00',
                ]],
            ],
        );

        $payload = $service->getDatasetPayload('api-youtrack', 'dataset.json');
        $columnKeys = array_map(static fn (array $column): string => (string) ($column['key'] ?? ''), $payload['columns'] ?? []);

        self::assertSame('URGENT', $payload['rows'][0]['Priority'] ?? null);
        self::assertSame('EN COURS', $payload['rows'][0]['State'] ?? null);
        self::assertSame('2024-04-09', $payload['rows'][0]['Date_echeance'] ?? null);
        self::assertSame('Merouan HAMZAOUI', $payload['rows'][1]['Assignee'] ?? null);
        self::assertSame('Pret pour MEP', $payload['rows'][1]['Type_Action'] ?? null);
        self::assertSame('https://redmine.example/123', $payload['rows'][1]['Lien_RM'] ?? null);
        self::assertContains('Priority', $columnKeys);
        self::assertContains('State', $columnKeys);
        self::assertContains('Assignee', $columnKeys);
        self::assertContains('Type_Action', $columnKeys);
        self::assertNotContains('customFields', $columnKeys);
    }

    public function testRemoteSharePointSourceCanBeReadThroughMicrosoftConnection(): void
    {
        $microsoftGraphAuthService = $this->createMock(MicrosoftGraphAuthService::class);
        $microsoftGraphAuthService
            ->method('isConfigured')
            ->willReturn(true);
        $microsoftGraphAuthService
            ->method('hasConnectedAccount')
            ->willReturn(true);
        $microsoftGraphAuthService
            ->expects($this->once())
            ->method('downloadSharedFile')
            ->with('https://adep0.sharepoint.com/sites/ServiceIT/Documents%20partages/Tickets/issues.csv')
            ->willReturn([
                'content' => "nom;quantite\nAlice;12\nBob;18\n",
                'contentType' => 'text/csv; charset=utf-8',
            ]);

        $service = $this->createService(
            [],
            [
                'uploadedSources' => [],
                'remoteSources' => [[
                    'id' => 'remote-tickets',
                    'label' => 'Tickets',
                    'url' => 'https://adep0.sharepoint.com/sites/ServiceIT/Documents%20partages/Tickets/issues.csv',
                    'extension' => 'csv',
                    'createdAt' => '2026-04-07T00:00:00+00:00',
                ]],
            ],
            $microsoftGraphAuthService,
        );

        $payload = $service->getDatasetPayload('remote-tickets', 'issues.csv');

        self::assertSame(2, $payload['rowCount'] ?? null);
        self::assertSame('Alice', $payload['rows'][0]['nom'] ?? null);
        self::assertSame('18', $payload['rows'][1]['quantite'] ?? null);
    }

    public function testRemoteSharePointSourceReturnsClearErrorWhenAuthenticationIsRequired(): void
    {
        $service = $this->createService(
            [
                new MockResponse('Access denied.', [
                    'http_code' => 403,
                    'response_headers' => [
                        'content-type: text/plain; charset=utf-8',
                        'x-forms_based_auth_required: https://adep0.sharepoint.com/_forms/default.aspx',
                    ],
                ]),
            ],
            [
                'uploadedSources' => [],
                'remoteSources' => [[
                    'id' => 'remote-tickets',
                    'label' => 'Tickets',
                    'url' => 'https://adep0.sharepoint.com/sites/ServiceIT/Documents%20partages/Tickets/issues.csv',
                    'extension' => 'csv',
                    'createdAt' => '2026-04-07T00:00:00+00:00',
                ]],
            ],
        );

        $payload = $service->getDatasetPayload('remote-tickets', 'issues.csv');

        self::assertSame(
            'La source SharePoint distante refuse l acces au serveur du dashboard (HTTP 403). Utilisez un lien de telechargement direct/public ou importez le fichier sur le site.',
            $payload['_error'] ?? null,
        );
    }

    public function testRemoteCsvPayloadAutoDetectsCommaDelimiter(): void
    {
        $service = $this->createService(
            [
                new MockResponse("nom,quantite\nAlice,12\nBob,18\n", [
                    'response_headers' => [
                        'content-type: text/csv; charset=utf-8',
                    ],
                ]),
            ],
            [
                'uploadedSources' => [],
                'remoteSources' => [[
                    'id' => 'remote-sales',
                    'label' => 'Ventes',
                    'url' => 'https://example.test/files/ventes.csv',
                    'extension' => 'csv',
                    'createdAt' => '2026-04-07T00:00:00+00:00',
                ]],
            ],
        );

        $payload = $service->getDatasetPayload('remote-sales', 'ventes.csv');

        self::assertSame(2, $payload['rowCount'] ?? null);
        self::assertSame(['nom', 'quantite'], array_map(static fn (array $column): string => (string) ($column['key'] ?? ''), $payload['columns'] ?? []));
        self::assertSame('Alice', $payload['rows'][0]['nom'] ?? null);
        self::assertSame('18', $payload['rows'][1]['quantite'] ?? null);
    }

    public function testRemoteCsvPayloadEncodedInWindows1252IsNormalizedToUtf8(): void
    {
        $csvContent = mb_convert_encoding("Prénom;Statut\nAndré;Réglé\n", 'Windows-1252', 'UTF-8');

        $service = $this->createService(
            [
                new MockResponse($csvContent, [
                    'response_headers' => [
                        'content-type: text/csv; charset=windows-1252',
                    ],
                ]),
            ],
            [
                'uploadedSources' => [],
                'remoteSources' => [[
                    'id' => 'remote-legacy-csv',
                    'label' => 'CSV legacy',
                    'url' => 'https://example.test/files/legacy.csv',
                    'extension' => 'csv',
                    'createdAt' => '2026-04-07T00:00:00+00:00',
                ]],
            ],
        );

        $payload = $service->getDatasetPayload('remote-legacy-csv', 'legacy.csv');

        self::assertSame(1, $payload['rowCount'] ?? null);
        self::assertSame('André', $payload['rows'][0]['Prenom'] ?? null);
        self::assertSame('Réglé', $payload['rows'][0]['Statut'] ?? null);
        self::assertSame('Prenom', $payload['columns'][0]['key'] ?? null);
        self::assertSame('Prenom', $payload['columns'][0]['label'] ?? null);

        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        self::assertIsString($json);
    }

    public function testRemoteXlsxPayloadIsParsed(): void
    {
        $service = $this->createService(
            [
                new MockResponse($this->createXlsxBinary([
                    ['Nom', 'Quantite'],
                    ['Alice', '12'],
                    ['Bob', '18'],
                ]), [
                    'response_headers' => [
                        'content-type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ],
                ]),
            ],
            [
                'uploadedSources' => [],
                'remoteSources' => [[
                    'id' => 'remote-excel',
                    'label' => 'Excel',
                    'url' => 'https://example.test/files/tickets.xlsx',
                    'extension' => 'xlsx',
                    'createdAt' => '2026-04-07T00:00:00+00:00',
                ]],
            ],
        );

        $payload = $service->getDatasetPayload('remote-excel', 'tickets.xlsx');

        self::assertSame(2, $payload['rowCount'] ?? null);
        self::assertSame('Alice', $payload['rows'][0]['Nom'] ?? null);
        self::assertSame('18', $payload['rows'][1]['Quantite'] ?? null);
    }

    public function testRemotePublicSharePointShareLinkUsesDownloadModeAndParsesXlsx(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->createXlsxBinary([
                ['Nom', 'Quantite'],
                ['Alice', '12'],
                ['Bob', '18'],
            ]), [
                'response_headers' => [
                    'content-type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ],
            ]),
        ]);

        $service = $this->createService(
            $httpClient,
            [
                'uploadedSources' => [],
                'remoteSources' => [[
                    'id' => 'remote-share-link',
                    'label' => 'Partage public',
                    'url' => 'https://nonexistent-dashboard-test.sharepoint.com/:x:/g/personal/demo_user/example-token?e=test',
                    'extension' => 'xlsx',
                    'createdAt' => '2026-04-07T00:00:00+00:00',
                ]],
            ],
        );

        $payload = $service->getDatasetPayload('remote-share-link', 'partage-public.xlsx');

        self::assertSame('sharepoint-url', $payload['connection']['type'] ?? null);
        self::assertSame(2, $payload['rowCount'] ?? null);
        self::assertSame('Alice', $payload['rows'][0]['Nom'] ?? null);
    }

    public function testRemoteLegacyXlsHtmlPayloadIsParsed(): void
    {
        $service = $this->createService(
            [
                new MockResponse('<html><body><table><tr><th>Nom</th><th>Statut</th></tr><tr><td>Alice</td><td>Ouvert</td></tr><tr><td>Bob</td><td>Clos</td></tr></table></body></html>', [
                    'response_headers' => [
                        'content-type: text/html; charset=utf-8',
                    ],
                ]),
            ],
            [
                'uploadedSources' => [],
                'remoteSources' => [[
                    'id' => 'remote-legacy-xls',
                    'label' => 'Legacy Excel',
                    'url' => 'https://example.test/files/tickets.xls',
                    'extension' => 'xls',
                    'createdAt' => '2026-04-07T00:00:00+00:00',
                ]],
            ],
        );

        $payload = $service->getDatasetPayload('remote-legacy-xls', 'tickets.xls');

        self::assertSame(2, $payload['rowCount'] ?? null);
        self::assertSame('Ouvert', $payload['rows'][0]['Statut'] ?? null);
        self::assertSame('Bob', $payload['rows'][1]['Nom'] ?? null);
    }

    public function testRemoteDatasetPayloadUsesServerCacheUntilRefreshIsForced(): void
    {
        $requestCount = 0;
        $httpClient = new MockHttpClient(function () use (&$requestCount) {
            ++$requestCount;

            return new MockResponse("nom;quantite\nAlice;12\nBob;18\n", [
                'response_headers' => [
                    'content-type: text/csv; charset=utf-8',
                ],
            ]);
        });

        $service = $this->createService(
            $httpClient,
            [
                'uploadedSources' => [],
                'remoteSources' => [[
                    'id' => 'remote-cache',
                    'label' => 'Cache test',
                    'url' => 'https://example.test/files/cache.csv',
                    'extension' => 'csv',
                    'createdAt' => '2026-04-07T00:00:00+00:00',
                ]],
            ],
        );

        $firstPayload = $service->getDatasetPayload('remote-cache', 'cache.csv');
        $secondPayload = $service->getDatasetPayload('remote-cache', 'cache.csv');

        self::assertSame(1, $requestCount, 'Le second chargement doit reutiliser le cache serveur.');
        self::assertSame(2, $firstPayload['rowCount'] ?? null);
        self::assertSame(2, $secondPayload['rowCount'] ?? null);
        self::assertSame('server', $secondPayload['_cache']['source'] ?? null);

        $refreshedPayload = $service->getDatasetPayload('remote-cache', 'cache.csv', true);

        self::assertSame(2, $refreshedPayload['rowCount'] ?? null);
        self::assertSame(2, $requestCount, 'Un refresh force doit invalider le cache serveur.');
    }

    private function createService(mixed $responses, array $settings, ?MicrosoftGraphAuthService $microsoftGraphAuthService = null): SharePointDataService
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel
            ->method('getProjectDir')
            ->willReturn(sys_get_temp_dir());

        $biModuleSettingsService = $this->createMock(BIModuleSettingsService::class);
        $biModuleSettingsService
            ->method('getSettings')
            ->willReturn($settings);
        $biModuleSettingsService
            ->method('resolveStoragePath')
            ->willReturn('');

        if (!($microsoftGraphAuthService instanceof MicrosoftGraphAuthService)) {
            $microsoftGraphAuthService = $this->createMock(MicrosoftGraphAuthService::class);
            $microsoftGraphAuthService
                ->method('isConfigured')
                ->willReturn(false);
            $microsoftGraphAuthService
                ->method('hasConnectedAccount')
                ->willReturn(false);
        }

        $cacheAdapter = new ArrayAdapter();

        return new SharePointDataService(
            $kernel,
            $responses instanceof MockHttpClient ? $responses : new MockHttpClient($responses),
            $biModuleSettingsService,
            $microsoftGraphAuthService,
            new ApiResultCacheService($cacheAdapter, $cacheAdapter),
            $this->createTempDataDirectory(),
        );
    }

    private function createTempDataDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/dashboard-bi-sharepoint-' . uniqid('', true);
        @mkdir($directory, 0777, true);

        return $directory;
    }

    /**
     * @param array<int, array<int, string>> $rows
     */
    private function createXlsxBinary(array $rows): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'bi-xlsx-test-');
        if ($temporaryPath === false) {
            self::fail('Impossible de creer un fichier XLSX temporaire.');
        }

        $zipArchive = new \ZipArchive();
        if ($zipArchive->open($temporaryPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            self::fail('Impossible de creer l archive XLSX de test.');
        }

        $sharedStrings = [];
        $sharedStringIndexes = [];
        $sheetRowsXml = '';

        foreach ($rows as $rowIndex => $row) {
            $sheetRowsXml .= '<row r="' . ($rowIndex + 1) . '">';
            foreach ($row as $columnIndex => $value) {
                $cellReference = $this->columnIndexToLetters($columnIndex) . ($rowIndex + 1);
                if (is_numeric($value) && $rowIndex > 0) {
                    $sheetRowsXml .= '<c r="' . $cellReference . '"><v>' . htmlspecialchars($value, ENT_XML1) . '</v></c>';
                    continue;
                }

                if (!array_key_exists($value, $sharedStringIndexes)) {
                    $sharedStringIndexes[$value] = count($sharedStrings);
                    $sharedStrings[] = $value;
                }

                $sheetRowsXml .= '<c r="' . $cellReference . '" t="s"><v>' . $sharedStringIndexes[$value] . '</v></c>';
            }
            $sheetRowsXml .= '</row>';
        }

        $sharedStringsXml = '';
        foreach ($sharedStrings as $sharedString) {
            $sharedStringsXml .= '<si><t>' . htmlspecialchars($sharedString, ENT_XML1) . '</t></si>';
        }

        $zipArchive->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '</Types>');
        $zipArchive->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zipArchive->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>');
        $zipArchive->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            . '</Relationships>');
        $zipArchive->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            . $sheetRowsXml
            . '</sheetData></worksheet>');
        $zipArchive->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($sharedStrings) . '" uniqueCount="' . count($sharedStrings) . '">'
            . $sharedStringsXml
            . '</sst>');
        $zipArchive->close();

        $binary = (string) file_get_contents($temporaryPath);
        @unlink($temporaryPath);

        return $binary;
    }

    private function columnIndexToLetters(int $index): string
    {
        $letters = '';
        ++$index;

        while ($index > 0) {
            $remainder = ($index - 1) % 26;
            $letters = chr(65 + $remainder) . $letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }
}
