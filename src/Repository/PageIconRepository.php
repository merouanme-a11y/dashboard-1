<?php

namespace App\Repository;

use App\Entity\PageIcon;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PageIconRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageIcon::class);
    }

    public function findByPagePath(string $pagePath): ?PageIcon
    {
        return $this->findOneBy(['pagePath' => $pagePath]);
    }

    public function findAllRows(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.pagePath AS pagePath', 'p.icon AS icon', 'p.color AS color', 'p.iconLibrary AS iconLibrary')
            ->getQuery()
            ->getArrayResult();
    }

    public function findAllIndexedByPagePath(): array
    {
        $items = [];

        foreach ($this->findAll() as $pageIcon) {
            $pagePath = $pageIcon->getPagePath();
            if ($pagePath !== null && $pagePath !== '') {
                $items[$pagePath] = $pageIcon;
            }
        }

        return $items;
    }
}
