<?php

namespace App\Repository;

use App\Entity\Permission;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Permission::class);
    }

    public function findByPagePathAndUser(string $pagePath, Utilisateur $user): ?Permission
    {
        return $this->findOneBy(['pagePath' => $pagePath, 'utilisateur' => $user]);
    }

    public function findByPagePathAndRole(string $pagePath, string $role): ?Permission
    {
        return $this->findOneBy(['pagePath' => $pagePath, 'role' => $role]);
    }

    public function findAllIndexedByUser(Utilisateur $user): array
    {
        $permissions = $this->createQueryBuilder('p')
            ->where('p.utilisateur = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($permissions as $permission) {
            if ($permission instanceof Permission && $permission->getPagePath()) {
                $indexed[(string) $permission->getPagePath()] = $permission;
            }
        }

        return $indexed;
    }

    public function findAllIndexedByRole(string $role): array
    {
        $permissions = $this->createQueryBuilder('p')
            ->where('p.role = :role')
            ->andWhere('p.utilisateur IS NULL')
            ->setParameter('role', $role)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($permissions as $permission) {
            if ($permission instanceof Permission && $permission->getPagePath()) {
                $indexed[(string) $permission->getPagePath()] = $permission;
            }
        }

        return $indexed;
    }
}
