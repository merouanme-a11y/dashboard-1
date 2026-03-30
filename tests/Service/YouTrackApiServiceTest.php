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
}
