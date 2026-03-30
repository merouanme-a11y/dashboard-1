<?php

namespace App\Repository;

use App\Entity\ThemeSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ThemeSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ThemeSetting::class);
    }

    public function findByKey(string $key): ?ThemeSetting
    {
        return $this->findOneBy(['settingKey' => $key]);
    }

    public function findAllAsArray(): array
    {
        $array = [];

        foreach ($this->createQueryBuilder('t')
            ->select('t.settingKey AS settingKey', 't.settingValue AS settingValue')
            ->getQuery()
            ->getArrayResult() as $setting
        ) {
            $settingKey = trim((string) ($setting['settingKey'] ?? ''));
            if ($settingKey === '') {
                continue;
            }

            $array[$settingKey] = (string) ($setting['settingValue'] ?? '');
        }

        return $array;
    }
}
