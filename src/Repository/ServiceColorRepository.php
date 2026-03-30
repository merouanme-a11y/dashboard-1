<?php

namespace App\Repository;

use App\Entity\ServiceColor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ServiceColorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceColor::class);
    }

    public function findByName(string $name): ?ServiceColor
    {
        return $this->findOneBy(['name' => $name]);
    }

    public function findAllAsArray(): array
    {
        $array = [];

        $results = $this->createQueryBuilder('s')
            ->select('s.name AS name', 's.color AS color')
            ->getQuery()
            ->getArrayResult();

        foreach ($results as $service) {
            $name = trim((string) ($service['name'] ?? ''));
            $color = trim((string) ($service['color'] ?? ''));
            if ($name === '' || $color === '') {
                continue;
            }

            $array[$name] = $color;
        }

        return $array;
    }
}
