<?php

namespace App\Tests\Service;

use App\Entity\ThemeSetting;
use App\Repository\ThemeSettingRepository;
use App\Service\BIModuleSettingsService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class BIModuleSettingsServiceTest extends TestCase
{
    public function testAddRemoteSourceRejectsSharePointFolderLink(): void
    {
        $repository = $this->createMock(ThemeSettingRepository::class);
        $repository
            ->expects($this->never())
            ->method('findByKey');

        $service = new BIModuleSettingsService(
            $repository,
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(KernelInterface::class),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ce lien SharePoint pointe vers un dossier.');

        $service->addRemoteSource(
            'Tickets',
            'https://adep0.sharepoint.com/:f:/r/sites/ServiceIT/Documents%20partages/Tickets?csf=1&web=1&e=0FXX7j',
        );
    }

    public function testAddRemoteSourceAcceptsExcelFileLink(): void
    {
        $repository = $this->createMock(ThemeSettingRepository::class);
        $repository
            ->expects($this->exactly(2))
            ->method('findByKey')
            ->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->once())
            ->method('persist');
        $entityManager
            ->expects($this->once())
            ->method('flush');

        $service = new BIModuleSettingsService(
            $repository,
            $entityManager,
            $this->createMock(KernelInterface::class),
        );

        $settings = $service->addRemoteSource(
            'Suivi Tickets',
            'https://adep0.sharepoint.com/sites/ServiceIT/Documents%20partages/Tickets/issues.xlsx',
        );

        self::assertSame('xlsx', $settings['remoteSources'][0]['extension'] ?? null);
        self::assertSame('Suivi Tickets', $settings['remoteSources'][0]['label'] ?? null);
    }

    public function testAddApiSourceKeepsTokenServerSideWithoutExposingIt(): void
    {
        $repository = $this->createMock(ThemeSettingRepository::class);
        $repository
            ->expects($this->exactly(2))
            ->method('findByKey')
            ->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->once())
            ->method('persist');
        $entityManager
            ->expects($this->once())
            ->method('flush');

        $service = new BIModuleSettingsService(
            $repository,
            $entityManager,
            $this->createMock(KernelInterface::class),
        );

        $settings = $service->addApiSource(
            'YouTrack tickets',
            'https://maintenance.adep.com/api/issues',
            'perm-super-secret-token',
        );

        self::assertSame('https://maintenance.adep.com/api/issues', $settings['apiSources'][0]['url'] ?? null);
        self::assertTrue($settings['apiSources'][0]['tokenConfigured'] ?? false);
        self::assertNotSame('', $settings['apiSources'][0]['tokenPreview'] ?? '');
        self::assertArrayNotHasKey('token', $settings['apiSources'][0] ?? []);
    }

    public function testUpdateUploadedSourceRenamesLabel(): void
    {
        $setting = (new ThemeSetting())
            ->setSettingKey(BIModuleSettingsService::SETTING_KEY)
            ->setSettingValue((string) json_encode([
                'uploadedSources' => [[
                    'id' => 'upload-1',
                    'label' => 'Ancien libelle',
                    'fileName' => 'issues.csv',
                    'path' => 'site-imports/issues.csv',
                    'extension' => 'csv',
                    'uploadedAt' => '2026-04-09T10:00:00+00:00',
                ]],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $repository = $this->createMock(ThemeSettingRepository::class);
        $repository
            ->expects($this->exactly(2))
            ->method('findByKey')
            ->with(BIModuleSettingsService::SETTING_KEY)
            ->willReturn($setting);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->never())
            ->method('persist');
        $entityManager
            ->expects($this->once())
            ->method('flush');

        $service = new BIModuleSettingsService(
            $repository,
            $entityManager,
            $this->createMock(KernelInterface::class),
        );

        $settings = $service->updateSource('upload-1', 'Tickets renommes');

        self::assertSame('Tickets renommes', $settings['uploadedSources'][0]['label'] ?? null);
        self::assertSame('issues.csv', $settings['uploadedSources'][0]['fileName'] ?? null);
    }

    public function testUpdateRemoteSourceChangesUrlAndExtension(): void
    {
        $setting = (new ThemeSetting())
            ->setSettingKey(BIModuleSettingsService::SETTING_KEY)
            ->setSettingValue((string) json_encode([
                'remoteSources' => [[
                    'id' => 'remote-1',
                    'label' => 'Tickets distants',
                    'url' => 'https://adep0.sharepoint.com/sites/ServiceIT/Documents%20partages/Tickets/issues.csv',
                    'extension' => 'csv',
                    'createdAt' => '2026-04-09T10:00:00+00:00',
                ]],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $repository = $this->createMock(ThemeSettingRepository::class);
        $repository
            ->expects($this->exactly(2))
            ->method('findByKey')
            ->with(BIModuleSettingsService::SETTING_KEY)
            ->willReturn($setting);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->never())
            ->method('persist');
        $entityManager
            ->expects($this->once())
            ->method('flush');

        $service = new BIModuleSettingsService(
            $repository,
            $entityManager,
            $this->createMock(KernelInterface::class),
        );

        $settings = $service->updateSource(
            'remote-1',
            'Tickets Excel',
            'https://adep0.sharepoint.com/sites/ServiceIT/Documents%20partages/Tickets/issues.xlsx',
        );

        self::assertSame('Tickets Excel', $settings['remoteSources'][0]['label'] ?? null);
        self::assertSame('https://adep0.sharepoint.com/sites/ServiceIT/Documents%20partages/Tickets/issues.xlsx', $settings['remoteSources'][0]['url'] ?? null);
        self::assertSame('xlsx', $settings['remoteSources'][0]['extension'] ?? null);
    }

    public function testUpdateApiSourceKeepsTokenWhenBlank(): void
    {
        $setting = (new ThemeSetting())
            ->setSettingKey(BIModuleSettingsService::SETTING_KEY)
            ->setSettingValue((string) json_encode([
                'apiSources' => [[
                    'id' => 'api-1',
                    'label' => 'YouTrack tickets',
                    'url' => 'https://maintenance.adep.com/api/issues',
                    'token' => 'perm-super-secret-token',
                    'extension' => 'json',
                    'createdAt' => '2026-04-09T10:00:00+00:00',
                ]],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $repository = $this->createMock(ThemeSettingRepository::class);
        $repository
            ->expects($this->exactly(2))
            ->method('findByKey')
            ->with(BIModuleSettingsService::SETTING_KEY)
            ->willReturn($setting);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->never())
            ->method('persist');
        $entityManager
            ->expects($this->once())
            ->method('flush');

        $service = new BIModuleSettingsService(
            $repository,
            $entityManager,
            $this->createMock(KernelInterface::class),
        );

        $settings = $service->updateSource(
            'api-1',
            'YouTrack incidents',
            'https://maintenance.adep.com/api/issues?query=project:%20MTN',
            '',
        );

        self::assertSame('YouTrack incidents', $settings['apiSources'][0]['label'] ?? null);
        self::assertSame('https://maintenance.adep.com/api/issues?query=project:%20MTN', $settings['apiSources'][0]['url'] ?? null);
        self::assertTrue($settings['apiSources'][0]['tokenConfigured'] ?? false);
        self::assertArrayNotHasKey('token', $settings['apiSources'][0] ?? []);

        $stored = json_decode((string) $setting->getSettingValue(), true);
        self::assertSame('perm-super-secret-token', $stored['apiSources'][0]['token'] ?? null);
    }
}
