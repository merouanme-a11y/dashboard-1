<?php

namespace App\Repository;

use App\Entity\DirectoryService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DirectoryServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DirectoryService::class);
    }

    public function countAllServices(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findAllForAdmin(): array
    {
        return $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT s.id, s.name, s.color, COUNT(u.id) AS usage_count
             FROM services s
             LEFT JOIN utilisateur u ON u.service COLLATE utf8mb4_unicode_ci = s.name
             GROUP BY s.id, s.name, s.color
             ORDER BY s.name ASC'
        );
    }

    public function findNameList(): array
    {
        $names = [];

        foreach ($this->getEntityManager()->getConnection()->fetchFirstColumn('SELECT name FROM services ORDER BY name ASC') as $name) {
            $normalized = trim((string) $name);
            if ($normalized !== '') {
                $names[] = $normalized;
            }
        }

        return $names;
    }

    public function findColorMap(): array
    {
        $map = [];

        foreach ($this->getEntityManager()->getConnection()->fetchAllAssociative('SELECT name, color FROM services ORDER BY name ASC') as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $color = strtoupper(trim((string) ($row['color'] ?? '')));
            if ($name === '' || !preg_match('/^#[0-9A-F]{6}$/', $color)) {
                continue;
            }

            $map[$name] = $color;
        }

        return $map;
    }

    public function findOneByNormalizedName(string $name, ?int $excludedId = null): ?DirectoryService
    {
        $queryBuilder = $this->createQueryBuilder('s')
            ->where('LOWER(s.name) = LOWER(:name)')
            ->setParameter('name', trim($name))
            ->setMaxResults(1);

        if ($excludedId !== null) {
            $queryBuilder
                ->andWhere('s.id <> :excludedId')
                ->setParameter('excludedId', $excludedId);
        }

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    public function findAllIndexedByNormalizedName(): array
    {
        $indexed = [];

        foreach ($this->getEntityManager()->getConnection()->fetchAllAssociative('SELECT id, name, color FROM services ORDER BY name ASC') as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $indexed[mb_strtolower($name)] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => $name,
                'color' => strtoupper(trim((string) ($row['color'] ?? ''))),
            ];
        }

        return $indexed;
    }
}
