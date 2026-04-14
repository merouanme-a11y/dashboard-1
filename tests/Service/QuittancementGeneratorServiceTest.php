<?php

namespace App\Tests\Service;

use App\Entity\Module;
use App\Repository\ModuleRepository;
use App\Service\QuittancementGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class QuittancementGeneratorServiceTest extends TestCase
{
    public function testEnsureModuleExistsCreatesModuleWhenMissing(): void
    {
        $repository = $this->createMock(ModuleRepository::class);
        $repository
            ->expects($this->once())
            ->method('findByName')
            ->with(QuittancementGeneratorService::MODULE_NAME)
            ->willReturn(null);
        $repository
            ->expects($this->once())
            ->method('findAllSorted')
            ->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Module::class));
        $entityManager
            ->expects($this->once())
            ->method('flush');

        $service = new QuittancementGeneratorService($repository, $entityManager);
        $module = $service->ensureModuleExists();

        self::assertSame(QuittancementGeneratorService::MODULE_NAME, $module->getName());
        self::assertSame('Generateur de quittancements', $module->getLabel());
        self::assertSame(QuittancementGeneratorService::ROUTE_NAME, $module->getRouteName());
        self::assertTrue($module->isActive());
    }

    public function testBuildPageDataAppliesLinkedDateOverrides(): void
    {
        $repository = $this->createMock(ModuleRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $service = new QuittancementGeneratorService($repository, $entityManager);
        $payload = $service->buildPageData([
            'year' => '2026',
            'month' => '4',
            'd3_date' => '2026-04-08',
            'd25_date' => '2026-04-28',
        ]);

        self::assertSame('2026-04-08', $payload['dates']['d3']['date_only']);
        self::assertSame('2026-04-15', $payload['dates']['d15']['date_only']);
        self::assertSame('2026-04-28', $payload['dates']['d25']['date_only']);
        self::assertSame('2026-04-26', $payload['dates']['d27']['date_only']);
        self::assertStringContainsString('avril 2026', $payload['emailData']['subject']);
    }

    public function testBuildPageDataKeepsCustomizedEmailAndBuildsSqlBlocks(): void
    {
        $repository = $this->createMock(ModuleRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $service = new QuittancementGeneratorService($repository, $entityManager);
        $payload = $service->buildPageData([
            'year' => '2026',
            'month' => '5',
            'email_customized' => '1',
            'email_to' => 'test@example.com',
            'email_cc' => 'copy@example.com',
            'email_subject' => 'Sujet libre',
            'email_body' => 'Contenu libre',
        ]);

        self::assertTrue($payload['emailCustomized']);
        self::assertSame('test@example.com', $payload['emailData']['to']);
        self::assertSame('Sujet libre', $payload['emailData']['subject']);
        self::assertStringContainsString('insert into oa_requittancement_planification', $payload['sqlSections']['rattrapage']);
        self::assertStringContainsString('insert into oa_requittancement_planification', $payload['sqlSections']['sante_collective']);
    }

    public function testApril2026SqlMatchesExpectedBusinessRules(): void
    {
        $repository = $this->createMock(ModuleRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $service = new QuittancementGeneratorService($repository, $entityManager);
        $payload = $service->buildPageData([
            'year' => '2026',
            'month' => '4',
        ]);

        self::assertSame('2026-04-17', $payload['dates']['d3']['date_only']);
        self::assertSame('2026-04-24', $payload['dates']['d15']['date_only']);
        self::assertSame('2026-04-29', $payload['dates']['d25']['date_only']);
        self::assertSame('2026-04-27', $payload['dates']['d27']['date_only']);

        $block1 = $payload['sqlSections']['rattrapage'];
        self::assertStringContainsString('with quittancement_1 as (', $block1);
        self::assertStringContainsString("'2026-04-17 23:00:00'", $block1);
        self::assertStringContainsString("Risque 7/8 - [Santé Collective] - 2026/02 au 2026/04", $block1);
        self::assertStringContainsString("Risque 8/8 - [Vie/Obsèques] - 2026/02 au 2026/04", $block1);

        $block2 = $payload['sqlSections']['quittancement'];
        self::assertStringContainsString('with quittancement_1 as (', $block2);
        self::assertStringContainsString("'2026-04-24 23:00:00'", $block2);
        self::assertStringContainsString("Risque 6/7 - [Santé Collectif (gestion individuelle)] - 2026/04 au 2026/05", $block2);
        self::assertStringContainsString("Risque 7/7 - [Vie/Obsèques] - 2026/04 au 2026/05", $block2);
        self::assertStringNotContainsString('Santé Collective', $block2);

        $block3 = $payload['sqlSections']['sante_collective'];
        self::assertStringContainsString("Risque [Santé Collective] - 2026/04 au 2026/05", $block3);
        self::assertStringContainsString("'2026-04-29 23:00:00'", $block3);
        self::assertSame(1, substr_count($block3, 'insert into oa_requittancement_planification'));
        self::assertStringNotContainsString("'2026-04-27 23:00:00'", $block3);
    }

    public function testApril2026EmailMatchesExpectedTemplate(): void
    {
        $repository = $this->createMock(ModuleRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $service = new QuittancementGeneratorService($repository, $entityManager);
        $payload = $service->buildPageData([
            'year' => '2026',
            'month' => '4',
        ]);

        $expectedBody = <<<TEXT
Bonjour,

Le rattrapage des quittancements des mois de février 2026 au mois de avril 2026 aura lieu le 17/04/2026 à partir de 23:00 pour l'ensemble des risques.
Le quittancement du mois de mai 2026 se fera le 24/04/2026 à partir de 23:00 pour tous les risques sauf santé collective avec un rattrapage depuis le mois de avril 2026.
Pour le risque Santé Collective spécifiquement, les ajustements seront lancés le 27/04/2026 à 23:00
Le quittancement de ce même risque pour le mois de mai 2026 sera lancé le 29/04/2026 à 23:00 avec un rattrapage depuis le mois de avril 2026.
Pour rappel, le traitement d'ajustement est aussi exécuté tous les 14 du mois.

Bonne réception
Le service IT
TEXT;

        self::assertSame($expectedBody, $payload['emailData']['body']);
    }
}
