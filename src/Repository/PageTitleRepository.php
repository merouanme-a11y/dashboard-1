<?php

namespace App\Repository;

use App\Entity\PageTitle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PageTitleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageTitle::class);
    }

    public function findByPagePath(string $pagePath): ?PageTitle
    {
        return $this->findOneBy(['pagePath' => $pagePath]);
    }

    public function findAllRows(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.pagePath AS pagePath', 'p.displayName AS displayName')
            ->getQuery()
            ->getArrayResult();
    }

    public function findAllIndexedByPagePath(): array
    {
        $items = [];

        foreach ($this->findAll() as $pageTitle) {
            $pagePath = $pageTitle->getPagePath();
            if ($pagePath !== null && $pagePath !== '') {
                $items[$pagePath] = $pageTitle;
            }
        }

        return $items;
    }
}
