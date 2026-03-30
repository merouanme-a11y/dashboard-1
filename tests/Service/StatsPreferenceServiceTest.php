<?php

namespace App\Tests\Service;

use App\Entity\UserPagePreference;
use App\Entity\Utilisateur;
use App\Repository\UserPagePreferenceRepository;
use App\Service\StatsPreferenceService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class StatsPreferenceServiceTest extends TestCase
{
    public function testGetForUserReturnsNormalizedPayload(): void
    {
        $preference = (new UserPagePreference())
            ->setPageKey('stats')
            ->setPreferencePayload([
                'defaultProject' => ' MTN ',
                'projects' => [
                    'MTN' => [
                        'layout' => [
                            ['id' => 'card-total', 'fraction' => '4/8'],
                            ['id' => 'card-total', 'fraction' => '8/8'],
                        ],
                        'visibility' => [
                            ['id' => 'card-table', 'hidden' => 'true'],
                        ],
                        'colors' => [
                            ['id' => 'card-services', 'bgColor' => '#abc', 'textColor' => '#123456'],
                            ['id' => 'card-ignored', 'bgColor' => 'not-a-color'],
                        ],
                    ],
                ],
            ]);

        $repository = $this->createMock(UserPagePreferenceRepository::class);
        $repository
            ->expects($this->once())
            ->method('findOneForUserAndPage')
            ->willReturn($preference);

        $service = new StatsPreferenceService($repository, $this->createMock(EntityManagerInterface::class));

        self::assertSame([
            'defaultProject' => 'MTN',
            'projects' => [
                'MTN' => [
                    'layout' => [
                        ['id' => 'card-total', 'fraction' => '4/8'],
                    ],
                    'visibility' => [
                        ['id' => 'card-table', 'hidden' => true],
                    ],
                    'colors' => [
                        ['id' => 'card-services', 'bgColor' => '#aabbcc', 'textColor' => '#123456'],
                    ],
                ],
            ],
        ], $service->getForUser(new Utilisateur()));
    }

    public function testSaveForUserCreatesOrUpdatesPreferenceRow(): void
    {
        $user = new Utilisateur();
        $createdPreference = null;

        $repository = $this->createMock(UserPagePreferenceRepository::class);
        $repository
            ->expects($this->once())
            ->method('findOneForUserAndPage')
            ->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($entity) use (&$createdPreference, $user): bool {
                $createdPreference = $entity;

                return $entity instanceof UserPagePreference
                    && $entity->getUtilisateur() === $user
                    && $entity->getPageKey() === 'stats';
            }));
        $em->expects($this->once())->method('flush');

        $service = new StatsPreferenceService($repository, $em);
        $saved = $service->saveForUser($user, [
            'defaultProject' => 'MTN',
            'projects' => [
                'MTN' => [
                    'layout' => [
                        ['id' => 'card-total', 'fraction' => '8/8'],
                    ],
                ],
            ],
        ]);

        self::assertInstanceOf(UserPagePreference::class, $createdPreference);
        self::assertSame($saved, $createdPreference->getPreferencePayload());
        self::assertSame('MTN', $saved['defaultProject']);
    }
}
