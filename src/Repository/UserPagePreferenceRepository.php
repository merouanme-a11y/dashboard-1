<?php

namespace App\Repository;

use App\Entity\UserPagePreference;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserPagePreference>
 */
class UserPagePreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserPagePreference::class);
    }

    public function findOneForUserAndPage(Utilisateur $user, string $pageKey): ?UserPagePreference
    {
        return $this->findOneBy([
            'utilisateur' => $user,
            'pageKey' => trim($pageKey),
        ]);
    }
}
