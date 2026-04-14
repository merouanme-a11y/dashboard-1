<?php

namespace App\Tests\Service;

use App\Entity\Utilisateur;
use App\Service\ApiResultCacheService;
use App\Service\DirectoryServiceManager;
use App\Service\YouTrackApiService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[AllowMockObjectsWithoutExpectations]
final class YouTrackApiServiceTest extends TestCase
{
    public function testTicketsPayloadFiltersByUserServiceAndPreselectsConnectedResponsable(): void
    {
        $issues = [
            $this->createIssue('MTN-10', 'Ticket IT', 'A FAIRE', 'Haute', 'IT', 'Merouan Hamzaoui', '28/03/2026 09:00'),
            $this->createIssue('MTN-30', 'Ticket IT 2', 'En cours', 'Medium', 'IT', 'Autre Personne', '28/03/2026 10:00'),
            $this->createIssue('MTN-99', 'Ticket RH', 'A FAIRE', 'Basse', 'RH', 'Merouan Hamzaoui', '28/03/2026 11:00'),
        ];

        $httpClient = new MockHttpClient([
            new MockResponse((string) json_encode($issues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ]);

        $directoryServiceManager = $this->createMock(DirectoryServiceManager::class);
        $directoryServiceManager
            ->expects($this->once())
            ->method('getServiceColorMap')
            ->willReturn([
                'IT' => '#4A6FC0',
                'RH' => '#2F4E8C',
            ]);

        $service = new YouTrackApiService(
            $httpClient,
            $directoryServiceManager,
            $this->createApiResultCacheService(),
            'https://maintenance.adep.com',
            'token',
            'MTN',
        );

        $user = (new Utilisateur())
            ->setPrenom('Merouan')
            ->setNom('Hamzaoui')
            ->setEmail('m.hamzaoui@adep.com')
            ->setService('IT')
            ->setRoles(['ROLE_EMPLOYE'])
            ->setProfileType('Employe');

        $payload = $service->getTicketsPayloadForUser($user);

        self::assertCount(2, $payload['tickets']);
        self::assertSame('MTN-30', $payload['tickets'][0]['idReadable']);
        self::assertSame('MTN-10', $payload['tickets'][1]['idReadable']);
        self::assertSame('IT', $payload['defaultFilters']['service']);
        self::assertSame('Merouan Hamzaoui', $payload['defaultFilters']['responsable']);
        self::assertSame('A FAIRE', $payload['defaultFilters']['state']);
        self::assertSame('#4A6FC0', $payload['serviceColors']['IT']);
    }

    public function testTicketDetailPayloadNormalizesEmailHtmlAndAttachmentUrls(): void
    {
        $timestamp = (\DateTimeImmutable::createFromFormat('d/m/Y H:i', '28/03/2026 09:00')?->getTimestamp() ?? 0) * 1000;
        $issue = [
            'idReadable' => 'MTN-927',
            'summary' => 'Ticket HTML',
            'description' => '<html><body><div>Bonjour</div><div><img src="![](image.png)"></div></body></html>',
            'created' => $timestamp,
            'updated' => $timestamp,
            'reporter' => [
                'name' => 'Reporter',
                'login' => 'reporter',
            ],
            'customFields' => [
                ['name' => 'State', 'value' => ['name' => 'A FAIRE']],
                ['name' => 'Priority', 'value' => ['name' => 'Haute']],
                ['name' => 'Service', 'value' => ['name' => 'IT']],
                ['name' => 'Assignee', 'value' => ['fullName' => 'Merouan Hamzaoui']],
                ['name' => 'Type Action', 'value' => ['name' => 'Correctif']],
            ],
            'attachments' => [
                ['id' => '9-2559', 'name' => 'image.png', 'url' => '/api/files/9-2559?sign=abc'],
            ],
            'comments' => [
                [
                    'id' => '4-1449',
                    'text' => '![Capture](capture.png)',
                    'created' => $timestamp,
                    'updated' => $timestamp,
                    'author' => ['fullName' => 'Reporter'],
                    'attachments' => [
                        ['id' => '9-2560', 'name' => 'capture.png', 'url' => '/api/files/9-2560?sign=def'],
                    ],
                ],
            ],
        ];

        $httpClient = new MockHttpClient([
            new MockResponse((string) json_encode($issue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ]);

        $service = new YouTrackApiService(
            $httpClient,
            $this->createMock(DirectoryServiceManager::class),
            $this->createApiResultCacheService(),
            'https://maintenance.adep.com',
            'token',
            'MTN',
        );

        $user = (new Utilisateur())
            ->setPrenom('Merouan')
            ->setNom('Hamzaoui')
            ->setEmail('m.hamzaoui@adep.com')
            ->setService('IT')
            ->setRoles(['ROLE_EMPLOYE'])
            ->setProfileType('Employe');

        $payload = $service->getTicketDetailPayloadForUser('MTN-927', $user);

        self::assertStringContainsString(
            'src="https://maintenance.adep.com/api/files/9-2559?sign=abc"',
            $payload['ticket']['description']
        );
        self::assertSame('https://maintenance.adep.com/api/files/9-2559?sign=abc', $payload['attachments'][0]['url']);
        self::assertStringContainsString(
            'https://maintenance.adep.com/api/files/9-2560?sign=def',
            $payload['comments'][0]['text']
        );
    }

    public function testCreatePatchTicketBuildsExpectedSummaryAndCustomFields(): void
    {
        $customFieldsResponse = [
            [
                'field' => ['name' => 'Type'],
                'bundle' => [
                    'values' => [
                        ['name' => 'Tâche'],
                        ['name' => 'Projet'],
                    ],
                ],
            ],
            [
                'field' => ['name' => 'State'],
                'bundle' => [
                    'values' => [
                        ['name' => 'A FAIRE'],
                        ['name' => 'EN COURS'],
                    ],
                ],
            ],
            [
                'field' => ['name' => 'Priority'],
                'bundle' => [
                    'values' => [
                        ['name' => 'MODÉRÉE'],
                        ['name' => 'URGENT'],
                    ],
                ],
            ],
            [
                'field' => ['name' => 'Service'],
                'bundle' => [
                    'values' => [
                        ['name' => 'IT'],
                        ['name' => 'PATCH'],
                    ],
                ],
            ],
            [
                'field' => ['name' => 'Date échéance'],
            ],
            [
                'field' => ['name' => 'Lien RM'],
            ],
        ];

        $recordedRequests = [];

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$recordedRequests, $customFieldsResponse): MockResponse {
            $recordedRequests[] = [
                'method' => $method,
                'url' => $url,
                'options' => $options,
            ];

            if (str_contains($url, '/api/admin/projects?')) {
                return new MockResponse((string) json_encode([
                    [
                        'id' => '0-0',
                        'name' => 'Maintenance',
                        'shortName' => 'MTN',
                    ],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            if (str_contains($url, '/api/admin/projects/MTN/customFields')) {
                return new MockResponse((string) json_encode($customFieldsResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            if (str_contains($url, '/api/issues')) {
                return new MockResponse((string) json_encode([
                    'idReadable' => 'MTN-999',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            return new MockResponse('{"error":"unexpected"}', ['http_code' => 500]);
        });

        $service = new YouTrackApiService(
            $httpClient,
            $this->createMock(DirectoryServiceManager::class),
            $this->createApiResultCacheService(),
            'https://maintenance.adep.com',
            'token',
            'MTN',
        );

        $user = (new Utilisateur())
            ->setPrenom('Merouan')
            ->setNom('Hamzaoui')
            ->setEmail('m.hamzaoui@adep.com')
            ->setService('IT')
            ->setRoles(['ROLE_EMPLOYE'])
            ->setProfileType('Employe');

        $result = $service->createPatchTicket($user, [
            'redmineUrl' => 'https://redmine.snlogica.com/issues/8775',
            'ticketNumber' => '',
            'followUpNumber' => '12',
            'service' => 'IT',
            'dueDate' => '2026-04-20',
            'relatedYouTrackUrl' => 'https://maintenance.adep.com/issue/MTN-321',
            'details' => 'Livrer la correction en production.',
        ]);

        self::assertSame('MTN-999', $result['idReadable'] ?? null);
        self::assertSame('https://maintenance.adep.com/issue/MTN-999', $result['url'] ?? null);
        self::assertSame('Patch à mettre en production [RM#8775] [S12]', $result['summary'] ?? null);
        self::assertCount(3, $recordedRequests);

        $postRequest = $recordedRequests[2] ?? null;
        self::assertNotNull($postRequest);
        self::assertSame('POST', $postRequest['method'] ?? null);

        $payload = json_decode((string) ($postRequest['options']['body'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Patch à mettre en production [RM#8775] [S12]', $payload['summary'] ?? null);
        self::assertSame('0-0', $payload['project']['id'] ?? null);
        self::assertStringContainsString('Lien ticket Redmine : https://redmine.snlogica.com/issues/8775', (string) ($payload['description'] ?? ''));
        self::assertStringContainsString('Numero de suivi patch : [S12]', (string) ($payload['description'] ?? ''));
        self::assertStringContainsString('Lien ticket Youtrack lie : https://maintenance.adep.com/issue/MTN-321', (string) ($payload['description'] ?? ''));
        self::assertStringContainsString('Demandeur : Merouan Hamzaoui - m.hamzaoui@adep.com', (string) ($payload['description'] ?? ''));
        self::assertStringContainsString('Livrer la correction en production.', (string) ($payload['description'] ?? ''));

        $customFields = $payload['customFields'] ?? [];
        self::assertSame('Tâche', $this->findCustomFieldValue($customFields, 'Type'));
        self::assertSame('A FAIRE', $this->findCustomFieldValue($customFields, 'State'));
        self::assertSame('MODÉRÉE', $this->findCustomFieldValue($customFields, 'Priority'));
        self::assertSame('IT', $this->findCustomFieldValue($customFields, 'Service'));
        self::assertSame('https://redmine.snlogica.com/issues/8775', $this->findCustomFieldValue($customFields, 'Lien RM'));
        self::assertSame(
            (\DateTimeImmutable::createFromFormat('Y-m-d', '2026-04-20')?->setTime(0, 0)->getTimestamp() ?? 0) * 1000,
            $this->findCustomFieldValue($customFields, 'Date échéance')
        );
    }

    private function createIssue(
        string $idReadable,
        string $summary,
        string $state,
        string $priority,
        string $service,
        string $assignee,
        string $createdAt
    ): array {
        $timestamp = \DateTimeImmutable::createFromFormat('d/m/Y H:i', $createdAt)?->getTimestamp() ?? 0;

        return [
            'idReadable' => $idReadable,
            'summary' => $summary,
            'created' => $timestamp * 1000,
            'updated' => $timestamp * 1000,
            'reporter' => [
                'name' => 'Reporter',
                'login' => 'reporter',
            ],
            'customFields' => [
                ['name' => 'State', 'value' => ['name' => $state]],
                ['name' => 'Priority', 'value' => ['name' => $priority]],
                ['name' => 'Service', 'value' => ['name' => $service]],
                ['name' => 'Assignee', 'value' => ['fullName' => $assignee]],
                ['name' => 'Type Action', 'value' => ['name' => 'Correctif']],
            ],
        ];
    }

    private function createApiResultCacheService(): ApiResultCacheService
    {
        $adapter = new ArrayAdapter();

        return new ApiResultCacheService($adapter, $adapter);
    }

    private function findCustomFieldValue(array $customFields, string $fieldName): mixed
    {
        foreach ($customFields as $customField) {
            if (($customField['name'] ?? null) !== $fieldName) {
                continue;
            }

            $value = $customField['value'] ?? null;
            if (is_array($value) && array_key_exists('name', $value)) {
                return $value['name'];
            }

            return $value;
        }

        return null;
    }
}
