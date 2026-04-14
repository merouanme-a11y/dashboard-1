<?php

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Service\QuittancementDatabaseTargetService;
use App\Service\QuittancementExecutionService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class QuittancementGeneratorControllerTest extends WebTestCase
{
    public function testIndexRendersExecuteButtonWithVersionedAssets(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->loginAsExistingUser($client);
        $this->mockDatabaseTargetService();

        $client->request('GET', '/quittancement-generator');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-action="execute-sql"]');
        self::assertSelectorExists('#qgTargetSelect');

        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('modules/quittancement-generator/style.css?v=', $content);
        self::assertStringContainsString('modules/quittancement-generator/app.js?v=', $content);
        self::assertStringContainsString('private', (string) $client->getResponse()->headers->get('Cache-Control'));
    }

    public function testExecuteReturnsJsonSuccessPayload(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->loginAsExistingUser($client);
        $this->mockDatabaseTargetService();

        static::getContainer()->set(QuittancementExecutionService::class, new class extends QuittancementExecutionService {
            public function __construct()
            {
            }

            public function isConfigured(?array $targetConfig = null): bool
            {
                return true;
            }

            public function getTargetSummary(?array $targetConfig = null): array
            {
                return [
                    'host' => 'test-host',
                    'port' => 5432,
                    'database' => 'test-db',
                ];
            }

            public function executeSqlSections(array $sqlSections, bool $commit = true): array
            {
                return [
                    'executedBlocks' => [
                        'Bloc 1 - Rattrapage',
                        'Bloc 2 - Quittancement',
                        'Bloc 3 - Sante collective',
                    ],
                    'target' => $this->getTargetSummary(),
                    'committed' => $commit,
                ];
            }

            public function executeSqlSectionsOnTarget(array $sqlSections, array $targetConfig, bool $commit = true): array
            {
                return [
                    'executedBlocks' => [
                        'Bloc 1 - Rattrapage',
                        'Bloc 2 - Quittancement',
                        'Bloc 3 - Sante collective',
                    ],
                    'target' => $this->getTargetSummary(),
                    'committed' => $commit,
                ];
            }
        });

        $client->request('GET', '/quittancement-generator');
        self::assertResponseIsSuccessful();

        $content = (string) $client->getResponse()->getContent();
        self::assertMatchesRegularExpression('/"executeToken":"([^"]+)"/', $content);
        preg_match('/"executeToken":"([^"]+)"/', $content, $matches);
        $token = stripcslashes((string) ($matches[1] ?? ''));

        $client->xmlHttpRequest(
            'POST',
            '/quittancement-generator/execute',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                '_token' => $token,
                'targetId' => 'test-target',
                'requestData' => [
                    'year' => '2026',
                    'month' => '4',
                    'd3_date' => '',
                    'd15_date' => '',
                    'd25_date' => '',
                    'd27_date' => '',
                    'target_id' => 'test-target',
                ],
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertStringContainsString('private', (string) $client->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([
            'Bloc 1 - Rattrapage',
            'Bloc 2 - Quittancement',
            'Bloc 3 - Sante collective',
        ], $payload['executedBlocks'] ?? []);
        self::assertStringContainsString('test-host/test-db', (string) ($payload['message'] ?? ''));
    }

    private function mockDatabaseTargetService(): void
    {
        static::getContainer()->set(QuittancementDatabaseTargetService::class, new class extends QuittancementDatabaseTargetService {
            public function __construct()
            {
            }

            public function getTargets(bool $includeSecrets = false): array
            {
                $target = [
                    'id' => 'test-target',
                    'label' => 'Serveur de test',
                    'host' => 'test-host',
                    'port' => 5432,
                    'database' => 'test-db',
                    'username' => 'tester',
                    'passwordConfigured' => true,
                    'passwordPreview' => 'te****et',
                    'isBuiltIn' => false,
                    'createdAt' => '',
                ];

                if ($includeSecrets) {
                    $target['password'] = 'password';
                }

                return [$target];
            }

            public function resolveRequestedTargetId(string $requestedId = ''): string
            {
                return trim($requestedId) !== '' ? trim($requestedId) : 'test-target';
            }

            public function getTargetById(string $targetId, bool $includeSecrets = false): ?array
            {
                foreach ($this->getTargets($includeSecrets) as $target) {
                    if ((string) ($target['id'] ?? '') === trim($targetId)) {
                        return $target;
                    }
                }

                return null;
            }
        });
    }

    private function loginAsExistingUser(KernelBrowser $client): void
    {
        try {
            /** @var UtilisateurRepository $repository */
            $repository = static::getContainer()->get(UtilisateurRepository::class);
            $user = $repository->findOneBy([], ['id' => 'ASC']);
        } catch (\Throwable $exception) {
            self::markTestSkipped('Base de test indisponible pour le test fonctionnel du module quittancement: ' . $exception->getMessage());
        }

        self::assertInstanceOf(Utilisateur::class, $user);

        $client->loginUser($user);
    }
}
