<?php

namespace App\Repository;

use App\Entity\Module;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ModuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Module::class);
    }

    public function findAllSorted(): array
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.sortOrder', 'ASC')
            ->addOrderBy('m.label', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveSorted(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.isActive = true')
            ->orderBy('m.sortOrder', 'ASC')
            ->addOrderBy('m.label', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByName(string $name): ?Module
    {
        return $this->findOneBy(['name' => $name]);
    }
}
