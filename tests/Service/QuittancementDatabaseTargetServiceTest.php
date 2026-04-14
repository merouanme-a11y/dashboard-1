<?php

namespace App\Tests\Service;

use App\Entity\ThemeSetting;
use App\Repository\ThemeSettingRepository;
use App\Service\QuittancementDatabaseTargetService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class QuittancementDatabaseTargetServiceTest extends TestCase
{
    public function testAddTargetMasksPasswordInReturnedPayloadAndStoredJson(): void
    {
        $setting = null;
        $repository = $this->createMock(ThemeSettingRepository::class);
        $repository->method('findByKey')->willReturnCallback(static function () use (&$setting): ?ThemeSetting {
            return $setting;
        });

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$setting): void {
            if ($entity instanceof ThemeSetting) {
                $setting = $entity;
            }
        });
        $entityManager->expects($this->atLeastOnce())->method('flush');

        $service = new QuittancementDatabaseTargetService(
            $repository,
            $entityManager,
            '',
            5432,
            '',
            '',
            '',
            'test-secret'
        );

        $targets = $service->addTarget('Recette OA', 'shv-oa-test-bdd', 5432, 'opas_integration', 'openassur', 'bwk18ghh');

        self::assertCount(1, $targets);
        self::assertSame('Recette OA', $targets[0]['label']);
        self::assertTrue($targets[0]['passwordConfigured']);
        self::assertStringContainsString('*', (string) $targets[0]['passwordPreview']);
        self::assertInstanceOf(ThemeSetting::class, $setting);

        $storedJson = (string) $setting->getSettingValue();
        if (function_exists('openssl_encrypt')) {
            self::assertStringNotContainsString('bwk18ghh', $storedJson);
            self::assertStringContainsString('enc:v1:', $storedJson);
        }

        $savedTarget = $service->getTargetById((string) $targets[0]['id'], true);
        self::assertSame('bwk18ghh', (string) ($savedTarget['password'] ?? ''));
    }

    public function testRemoveTargetKeepsBuiltInTargetAndRejectsItsDeletion(): void
    {
        $setting = null;
        $repository = $this->createMock(ThemeSettingRepository::class);
        $repository->method('findByKey')->willReturnCallback(static function () use (&$setting): ?ThemeSetting {
            return $setting;
        });

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$setting): void {
            if ($entity instanceof ThemeSetting) {
                $setting = $entity;
            }
        });
        $entityManager->expects($this->atLeastOnce())->method('flush');

        $service = new QuittancementDatabaseTargetService(
            $repository,
            $entityManager,
            'env-host',
            5432,
            'env-db',
            'env-user',
            'env-pass',
            'test-secret'
        );

        $targets = $service->addTarget('Recette OA', 'custom-host', 5432, 'custom-db', 'custom-user', 'custom-pass');
        $customTargetId = (string) ($targets[array_key_last($targets)]['id'] ?? '');

        $remainingTargets = $service->removeTarget($customTargetId);
        self::assertCount(1, $remainingTargets);
        self::assertSame(QuittancementDatabaseTargetService::ENV_TARGET_ID, $remainingTargets[0]['id']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ne peut pas etre supprimee');
        $service->removeTarget(QuittancementDatabaseTargetService::ENV_TARGET_ID);
    }
}
