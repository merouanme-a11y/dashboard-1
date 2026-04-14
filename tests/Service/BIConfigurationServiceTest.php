<?php

namespace App\Tests\Service;

use App\Entity\Module;
use App\Entity\UserPagePreference;
use App\Entity\Utilisateur;
use App\Repository\ModuleRepository;
use App\Repository\UserPagePreferenceRepository;
use App\Service\BIConfigurationService;
use App\Service\BIModuleSettingsService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class BIConfigurationServiceTest extends TestCase
{
    public function testSaveForUserCreatesSharedOwnedPageAndPersistsSelectedPage(): void
    {
        $user = (new Utilisateur())
            ->setPrenom('Sonia')
            ->setNom('Test')
            ->setEmail('s.at@adep.com')
            ->setProfileType('Employe');
        $this->setUserId($user, 14);

        $savedPreference = null;
        $savedSharedPages = null;

        $preferenceRepository = $this->createMock(UserPagePreferenceRepository::class);
        $preferenceRepository
            ->expects($this->exactly(2))
            ->method('findOneForUserAndPage')
            ->with($user, BIConfigurationService::PAGE_KEY)
            ->willReturnCallback(function () use (&$savedPreference) {
                return $savedPreference;
            });

        $moduleRepository = $this->createMock(ModuleRepository::class);
        $moduleRepository
            ->expects($this->exactly(2))
            ->method('findByName')
            ->with(BIConfigurationService::MODULE_NAME)
            ->willReturn(new Module());

        $moduleSettingsService = $this->createMock(BIModuleSettingsService::class);
        $moduleSettingsService
            ->expects($this->exactly(2))
            ->method('getSharedPages')
            ->willReturnCallback(function () use (&$savedSharedPages) {
                return $savedSharedPages ?? [];
            });
        $moduleSettingsService
            ->expects($this->exactly(2))
            ->method('getPageCreationPermissions')
            ->willReturn([
                'userIds' => [14],
                'profileTypes' => [],
            ]);
        $moduleSettingsService
            ->expects($this->once())
            ->method('saveSharedPages')
            ->with($this->callback(function (array $pages) use (&$savedSharedPages): bool {
                $savedSharedPages = $pages;

                return count($pages) === 1
                    && ($pages[0]['name'] ?? '') === 'Page BI Support'
                    && ($pages[0]['ownerUserId'] ?? 0) === 14
                    && ($pages[0]['ownerEmail'] ?? '') === 's.at@adep.com';
            }))
            ->willReturnCallback(function (array $pages) use (&$savedSharedPages): array {
                $savedSharedPages = $pages;

                return $pages;
            });

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($entity) use (&$savedPreference, $user): bool {
                $savedPreference = $entity;

                return $entity instanceof UserPagePreference
                    && $entity->getUtilisateur() === $user
                    && $entity->getPageKey() === BIConfigurationService::PAGE_KEY;
            }));
        $entityManager
            ->expects($this->once())
            ->method('flush');

        $service = new BIConfigurationService($preferenceRepository, $moduleRepository, $entityManager, $moduleSettingsService);
        $saved = $service->saveForUser($user, [
            'selectedPageId' => 'page-1',
            'pages' => [[
                'id' => 'page-1',
                'name' => 'Page BI Support',
                'connectionId' => 'api-youtrack',
                'fileId' => 'issues',
                'widgets' => [[
                    'id' => 'widget-1',
                    'type' => 'bar-horizontal',
                    'title' => 'Tickets par statut',
                    'layout' => '4/8',
                    'dimensionColumn' => 'Statut',
                    'chartDimensions' => ['Statut'],
                    'valueColumn' => 'Assigne a',
                    'aggregation' => 'count',
                ]],
            ]],
        ]);

        self::assertInstanceOf(UserPagePreference::class, $savedPreference);
        self::assertSame(['selectedPageId' => 'page-1'], $savedPreference->getPreferencePayload());
        self::assertCount(1, $savedSharedPages ?? []);
        self::assertSame('Page BI Support', $saved['pages'][0]['name'] ?? null);
        self::assertTrue($saved['pages'][0]['canEdit'] ?? false);
    }

    public function testGetForUserReturnsVisibleSharedPageForAuthorizedProfile(): void
    {
        $user = (new Utilisateur())
            ->setPrenom('Sarah')
            ->setNom('At')
            ->setEmail('s.at@adep.com')
            ->setProfileType('Responsable');
        $this->setUserId($user, 21);

        $preference = (new UserPagePreference())
            ->setUtilisateur($user)
            ->setPageKey(BIConfigurationService::PAGE_KEY)
            ->setPreferencePayload(['selectedPageId' => 'page-support']);

        $preferenceRepository = $this->createMock(UserPagePreferenceRepository::class);
        $preferenceRepository
            ->expects($this->once())
            ->method('findOneForUserAndPage')
            ->with($user, BIConfigurationService::PAGE_KEY)
            ->willReturn($preference);

        $moduleRepository = $this->createMock(ModuleRepository::class);
        $moduleRepository
            ->expects($this->once())
            ->method('findByName')
            ->with(BIConfigurationService::MODULE_NAME)
            ->willReturn(new Module());

        $moduleSettingsService = $this->createMock(BIModuleSettingsService::class);
        $moduleSettingsService
            ->expects($this->once())
            ->method('getSharedPages')
            ->willReturn([[
                'id' => 'page-support',
                'name' => 'Support',
                'connectionId' => 'api-youtrack',
                'fileId' => 'issues',
                'filters' => [],
                'widgets' => [[
                    'id' => 'widget-1',
                    'type' => 'kpi',
                    'title' => 'Total',
                    'layout' => '2/8',
                ]],
                'ownerUserId' => 4,
                'ownerEmail' => 'owner@adep.com',
                'ownerDisplayName' => 'Owner BI',
                'allowedUserIds' => [],
                'allowedProfileTypes' => ['Responsable'],
            ]]);
        $moduleSettingsService
            ->expects($this->once())
            ->method('getPageCreationPermissions')
            ->willReturn([
                'userIds' => [],
                'profileTypes' => ['Responsable'],
            ]);

        $service = new BIConfigurationService(
            $preferenceRepository,
            $moduleRepository,
            $this->createMock(EntityManagerInterface::class),
            $moduleSettingsService,
        );

        $preferences = $service->getForUser($user);

        self::assertSame('page-support', $preferences['selectedPageId'] ?? null);
        self::assertSame('Support', $preferences['pages'][0]['name'] ?? null);
        self::assertFalse($preferences['pages'][0]['canEdit'] ?? true);
        self::assertTrue($preferences['canCreatePages'] ?? false);
    }

    private function setUserId(Utilisateur $user, int $id): void
    {
        $reflection = new \ReflectionProperty($user, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($user, $id);
    }
}
