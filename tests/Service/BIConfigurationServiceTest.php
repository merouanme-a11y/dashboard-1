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

    public function testSaveForUserPreservesPageFilters(): void
    {
        $user = (new Utilisateur())
            ->setPrenom('Merouan')
            ->setNom('Hamzaoui')
            ->setEmail('m.hamzaoui@adep.com')
            ->setProfileType('Admin');
        $this->setUserId($user, 1);

        $sharedPages = [[
            'id' => 'page-bi-1',
            'name' => 'Tableaux',
            'connectionId' => 'api-youtrack',
            'fileId' => 'issues',
            'filters' => [],
            'widgets' => [],
            'ownerUserId' => 1,
            'ownerEmail' => 'm.hamzaoui@adep.com',
            'ownerDisplayName' => 'Merouan Hamzaoui',
            'allowedUserIds' => [],
            'allowedProfileTypes' => [],
        ]];
        $savedPreference = (new UserPagePreference())
            ->setUtilisateur($user)
            ->setPageKey(BIConfigurationService::PAGE_KEY)
            ->setPreferencePayload(['selectedPageId' => 'page-bi-1']);

        $preferenceRepository = $this->createMock(UserPagePreferenceRepository::class);
        $preferenceRepository
            ->expects($this->exactly(2))
            ->method('findOneForUserAndPage')
            ->with($user, BIConfigurationService::PAGE_KEY)
            ->willReturn($savedPreference);

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
            ->willReturnCallback(function () use (&$sharedPages): array {
                return $sharedPages;
            });
        $moduleSettingsService
            ->expects($this->once())
            ->method('saveSharedPages')
            ->willReturnCallback(function (array $pages) use (&$sharedPages): array {
                $sharedPages = $pages;

                return $pages;
            });

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->once())
            ->method('flush');

        $service = new BIConfigurationService(
            $preferenceRepository,
            $moduleRepository,
            $entityManager,
            $moduleSettingsService,
        );

        $saved = $service->saveForUser($user, [
            'selectedPageId' => 'page-bi-1',
            'pages' => [[
                'id' => 'page-bi-1',
                'name' => 'Tableaux',
                'connectionId' => 'api-youtrack',
                'fileId' => 'issues',
                'filters' => [[
                    'column' => 'assignee',
                    'value' => 'Merouan Hamzaoui',
                ]],
                'widgets' => [[
                    'id' => 'widget-1',
                    'type' => 'counter',
                    'title' => 'Compteur',
                    'layout' => '1/8',
                ]],
            ]],
        ]);

        self::assertSame('assignee', $sharedPages[0]['filters'][0]['column'] ?? null);
        self::assertSame('Merouan Hamzaoui', $sharedPages[0]['filters'][0]['value'] ?? null);
        self::assertSame('assignee', $saved['pages'][0]['filters'][0]['column'] ?? null);
        self::assertSame('Merouan Hamzaoui', $saved['pages'][0]['filters'][0]['value'] ?? null);
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

    public function testSaveForUserPreservesDatatableWidgets(): void
    {
        $user = (new Utilisateur())
            ->setPrenom('Merouan')
            ->setNom('Hamzaoui')
            ->setEmail('m.hamzaoui@adep.com')
            ->setProfileType('Admin');
        $this->setUserId($user, 1);

        $sharedPages = [[
            'id' => 'page-bi-1',
            'name' => 'Tableaux',
            'connectionId' => 'api-youtrack',
            'fileId' => 'issues',
            'filters' => [],
            'widgets' => [],
            'ownerUserId' => 1,
            'ownerEmail' => 'm.hamzaoui@adep.com',
            'ownerDisplayName' => 'Merouan Hamzaoui',
            'allowedUserIds' => [],
            'allowedProfileTypes' => [],
        ]];
        $savedSharedPages = null;
        $savedPreference = (new UserPagePreference())
            ->setUtilisateur($user)
            ->setPageKey(BIConfigurationService::PAGE_KEY)
            ->setPreferencePayload(['selectedPageId' => 'page-bi-1']);

        $preferenceRepository = $this->createMock(UserPagePreferenceRepository::class);
        $preferenceRepository
            ->expects($this->exactly(2))
            ->method('findOneForUserAndPage')
            ->with($user, BIConfigurationService::PAGE_KEY)
            ->willReturn($savedPreference);

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
            ->willReturnCallback(function () use (&$sharedPages): array {
                return $sharedPages;
            });
        $moduleSettingsService
            ->expects($this->once())
            ->method('saveSharedPages')
            ->with($this->callback(function (array $pages) use (&$savedSharedPages): bool {
                $savedSharedPages = $pages;

                return isset($pages[0]['widgets'][0]['type']) && $pages[0]['widgets'][0]['type'] === 'datatable';
            }))
            ->willReturnCallback(function (array $pages) use (&$sharedPages, &$savedSharedPages): array {
                $sharedPages = $pages;
                $savedSharedPages = $pages;

                return $pages;
            });

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->once())
            ->method('flush');

        $service = new BIConfigurationService(
            $preferenceRepository,
            $moduleRepository,
            $entityManager,
            $moduleSettingsService,
        );

        $saved = $service->saveForUser($user, [
            'selectedPageId' => 'page-bi-1',
            'pages' => [[
                'id' => 'page-bi-1',
                'name' => 'Tableaux',
                'connectionId' => 'api-youtrack',
                'fileId' => 'issues',
                'widgets' => [[
                    'id' => 'widget-datatable-1',
                    'type' => 'datatable',
                    'title' => 'Tableau detaille',
                    'layout' => '8/8',
                    'maxItems' => 20,
                    'tableColumns' => ['service', 'responsable', 'statut', 'service'],
                    'tableColumnStyles' => [
                        ['key' => 'service', 'bgColor' => '#223344', 'textColor' => '#ffffff'],
                        ['key' => 'statut', 'bgColor' => '#445566', 'textColor' => '#f8fafc'],
                    ],
                    'tableStyles' => [
                        'headerBgColor' => '#112233',
                        'headerTextColor' => '#ffffff',
                        'rowBgColor' => '#f8fafc',
                        'rowAltBgColor' => '#e2e8f0',
                        'cellBgColor' => '#ffffff',
                        'cellTextColor' => '#0f172a',
                    ],
                    'sortColumn' => 'service',
                    'sortDir' => 'desc',
                    'widgetFilters' => [[
                        'id' => 'filter-1',
                        'column' => 'assignee',
                        'operator' => 'contains',
                        'inputMode' => 'input',
                        'value' => 'Merouan Hamzaoui',
                        'styleTarget' => 'row',
                        'bgColor' => '#112233',
                        'textColor' => '#ffffff',
                    ], [
                        'id' => 'filter-2',
                        'column' => 'statut',
                        'operator' => 'equals',
                        'inputMode' => 'select',
                        'values' => ['En cours', 'Bloque', 'En cours'],
                        'valueStyles' => [
                            ['value' => 'En cours', 'styleTarget' => 'cell', 'bgColor' => '#334455', 'textColor' => '#f8fafc'],
                            ['value' => 'Bloque', 'styleTarget' => 'row', 'bgColor' => '#552233', 'textColor' => '#ffffff'],
                        ],
                    ]],
                ]],
            ]],
        ]);

        self::assertNotNull($savedSharedPages);
        self::assertSame('datatable', $savedSharedPages[0]['widgets'][0]['type'] ?? null);
        self::assertSame(['service', 'responsable', 'statut'], $savedSharedPages[0]['widgets'][0]['tableColumns'] ?? null);
        self::assertSame('#223344', $savedSharedPages[0]['widgets'][0]['tableColumnStyles'][0]['bgColor'] ?? null);
        self::assertSame('#ffffff', $savedSharedPages[0]['widgets'][0]['tableColumnStyles'][0]['textColor'] ?? null);
        self::assertSame('service', $savedSharedPages[0]['widgets'][0]['sortColumn'] ?? null);
        self::assertSame('desc', $savedSharedPages[0]['widgets'][0]['sortDir'] ?? null);
        self::assertSame('#112233', $savedSharedPages[0]['widgets'][0]['tableStyles']['headerBgColor'] ?? null);
        self::assertSame('contains', $savedSharedPages[0]['widgets'][0]['widgetFilters'][0]['operator'] ?? null);
        self::assertSame('input', $savedSharedPages[0]['widgets'][0]['widgetFilters'][0]['inputMode'] ?? null);
        self::assertSame('row', $savedSharedPages[0]['widgets'][0]['widgetFilters'][0]['styleTarget'] ?? null);
        self::assertSame('#112233', $savedSharedPages[0]['widgets'][0]['widgetFilters'][0]['bgColor'] ?? null);
        self::assertSame('#ffffff', $savedSharedPages[0]['widgets'][0]['widgetFilters'][0]['textColor'] ?? null);
        self::assertSame(['En cours', 'Bloque'], $savedSharedPages[0]['widgets'][0]['widgetFilters'][1]['values'] ?? null);
        self::assertSame('cell', $savedSharedPages[0]['widgets'][0]['widgetFilters'][1]['valueStyles'][0]['styleTarget'] ?? null);
        self::assertSame('#552233', $savedSharedPages[0]['widgets'][0]['widgetFilters'][1]['valueStyles'][1]['bgColor'] ?? null);
        self::assertSame('datatable', $saved['pages'][0]['widgets'][0]['type'] ?? null);
        self::assertSame(['service', 'responsable', 'statut'], $saved['pages'][0]['widgets'][0]['tableColumns'] ?? null);
        self::assertSame('#223344', $saved['pages'][0]['widgets'][0]['tableColumnStyles'][0]['bgColor'] ?? null);
        self::assertSame('service', $saved['pages'][0]['widgets'][0]['sortColumn'] ?? null);
        self::assertSame('desc', $saved['pages'][0]['widgets'][0]['sortDir'] ?? null);
        self::assertSame('row', $saved['pages'][0]['widgets'][0]['widgetFilters'][0]['styleTarget'] ?? null);
        self::assertSame(['En cours', 'Bloque'], $saved['pages'][0]['widgets'][0]['widgetFilters'][1]['values'] ?? null);
        self::assertSame('row', $saved['pages'][0]['widgets'][0]['widgetFilters'][1]['valueStyles'][1]['styleTarget'] ?? null);
    }

    private function setUserId(Utilisateur $user, int $id): void
    {
        $reflection = new \ReflectionProperty($user, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($user, $id);
    }
}
