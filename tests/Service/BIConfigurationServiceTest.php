<?php

namespace App\Tests\Service;

use App\Entity\Module;
use App\Entity\UserPagePreference;
use App\Entity\Utilisateur;
use App\Repository\ModuleRepository;
use App\Repository\UserPagePreferenceRepository;
use App\Service\BIConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class BIConfigurationServiceTest extends TestCase
{
    public function testSaveForUserKeepsWidgetMeasuresAndFilters(): void
    {
        $user = new Utilisateur();
        $savedPreference = null;

        $preferenceRepository = $this->createMock(UserPagePreferenceRepository::class);
        $preferenceRepository
            ->expects($this->once())
            ->method('findOneForUserAndPage')
            ->with($user, BIConfigurationService::PAGE_KEY)
            ->willReturn(null);

        $moduleRepository = $this->createMock(ModuleRepository::class);
        $moduleRepository
            ->expects($this->once())
            ->method('findByName')
            ->with(BIConfigurationService::MODULE_NAME)
            ->willReturn(new Module());

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

        $service = new BIConfigurationService($preferenceRepository, $moduleRepository, $entityManager);
        $saved = $service->saveForUser($user, [
            'defaultConnection' => 'api-youtrack',
            'defaultFile' => 'issues',
            'selectedPageId' => 'page-1',
            'pages' => [[
                'id' => 'page-1',
                'name' => 'Tickets',
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
                    'measures' => [[
                        'id' => 'measure-main',
                        'column' => 'Assigne a',
                        'aggregation' => 'count',
                        'matchValue' => 'Merouan',
                    ]],
                    'widgetFilters' => [
                        ['id' => 'filter-status', 'column' => 'Statut', 'value' => 'En cours'],
                        ['id' => 'filter-priority', 'column' => 'Priorite', 'value' => 'Urgent'],
                        ['id' => 'filter-team', 'column' => 'Equipe', 'value' => 'Support'],
                        ['id' => 'filter-project', 'column' => 'Projet', 'value' => 'Maintenance'],
                        ['id' => 'filter-sprint', 'column' => 'Sprint', 'value' => 'Sprint 12'],
                        ['id' => 'filter-extra', 'column' => 'Ignore', 'value' => 'Trop'],
                    ],
                ]],
            ]],
        ]);

        self::assertInstanceOf(UserPagePreference::class, $savedPreference);
        self::assertSame($saved, $savedPreference->getPreferencePayload());

        $widget = $saved['pages'][0]['widgets'][0] ?? [];
        self::assertSame(['Statut'], $widget['chartDimensions'] ?? []);
        self::assertSame('Assigne a', $widget['measures'][0]['column'] ?? null);
        self::assertSame('count', $widget['measures'][0]['aggregation'] ?? null);
        self::assertSame('Merouan', $widget['measures'][0]['matchValue'] ?? null);
        self::assertCount(5, $widget['widgetFilters'] ?? []);
        self::assertSame('Statut', $widget['widgetFilters'][0]['column'] ?? null);
        self::assertSame('En cours', $widget['widgetFilters'][0]['value'] ?? null);
        self::assertSame('Sprint', $widget['widgetFilters'][4]['column'] ?? null);
    }

    public function testSaveForUserKeepsDistributionTableWidgets(): void
    {
        $user = new Utilisateur();
        $savedPreference = null;

        $preferenceRepository = $this->createMock(UserPagePreferenceRepository::class);
        $preferenceRepository
            ->expects($this->once())
            ->method('findOneForUserAndPage')
            ->with($user, BIConfigurationService::PAGE_KEY)
            ->willReturn(null);

        $moduleRepository = $this->createMock(ModuleRepository::class);
        $moduleRepository
            ->expects($this->once())
            ->method('findByName')
            ->with(BIConfigurationService::MODULE_NAME)
            ->willReturn(new Module());

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

        $service = new BIConfigurationService($preferenceRepository, $moduleRepository, $entityManager);
        $saved = $service->saveForUser($user, [
            'pages' => [[
                'id' => 'page-1',
                'name' => 'Page detail',
                'connectionId' => 'csv-local',
                'fileId' => 'issues',
                'widgets' => [[
                    'id' => 'widget-distribution',
                    'type' => 'distribution-table',
                    'title' => 'Services',
                    'layout' => '8/8',
                    'dimensionColumn' => 'Service',
                    'chartDimensions' => ['Service'],
                    'maxItems' => 12,
                    'widgetFilters' => [
                        ['column' => 'State', 'value' => 'A FAIRE'],
                    ],
                ]],
            ]],
        ]);

        self::assertInstanceOf(UserPagePreference::class, $savedPreference);
        self::assertSame($saved, $savedPreference->getPreferencePayload());

        $widget = $saved['pages'][0]['widgets'][0] ?? [];
        self::assertSame('distribution-table', $widget['type'] ?? null);
        self::assertSame(['Service'], $widget['chartDimensions'] ?? []);
        self::assertSame('8/8', $widget['layout'] ?? null);
        self::assertSame(12, $widget['maxItems'] ?? null);
        self::assertSame('State', $widget['widgetFilters'][0]['column'] ?? null);
        self::assertSame('A FAIRE', $widget['widgetFilters'][0]['value'] ?? null);
    }
}
