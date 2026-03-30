<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class LegacyDirectoryOptionsService
{
    private ?Connection $legacyConnection = null;
    private ?array $serviceOptions = null;
    private ?array $agenceOptions = null;
    private ?array $departementOptions = null;
    private ?array $agenceDepartementMap = null;

    public function __construct(
        private Connection $connection,
        private string $legacyDatabaseName,
        private CacheInterface $cache,
    ) {}

    public function getServiceOptions(): array
    {
        if (is_array($this->serviceOptions)) {
            return $this->serviceOptions;
        }

        return $this->serviceOptions = $this->cache->get('legacy_directory_service_options_v1', function (ItemInterface $item): array {
            $item->expiresAfter(43200);

            try {
                $rows = $this->getLegacyConnection()->fetchAllAssociative('SELECT name FROM services ORDER BY name ASC');
                $services = [];

                foreach ($rows as $row) {
                    $name = trim((string) ($row['name'] ?? ''));
                    if ($name !== '') {
                        $services[] = $name;
                    }
                }

                $services = array_values(array_unique($services));
                sort($services, SORT_NATURAL | SORT_FLAG_CASE);

                return $services;
            } catch (\Throwable $exception) {
                error_log('Erreur chargement services mydash83: ' . $exception->getMessage());

                return [];
            }
        });
    }

    public function getAgenceOptions(): array
    {
        if (is_array($this->agenceOptions)) {
            return $this->agenceOptions;
        }

        $data = $this->cache->get('legacy_directory_agence_data_v1', function (ItemInterface $item): array {
            $item->expiresAfter(43200);

            try {
                $rows = $this->getLegacyConnection()->fetchAllAssociative('SELECT agence, departement FROM agence ORDER BY departement ASC, agence ASC');
                $options = [];
                $map = [];

                foreach ($rows as $row) {
                    $agence = trim((string) ($row['agence'] ?? ''));
                    $departement = trim((string) ($row['departement'] ?? ''));
                    if ($agence === '') {
                        continue;
                    }

                    $options[] = [
                        'agence' => $agence,
                        'departement' => $departement,
                    ];
                    $map[$agence] = $departement;
                }

                return [
                    'options' => $options,
                    'map' => $map,
                ];
            } catch (\Throwable $exception) {
                error_log('Erreur chargement agences mydash83: ' . $exception->getMessage());

                return [
                    'options' => [],
                    'map' => [],
                ];
            }
        });

        $this->agenceOptions = is_array($data['options'] ?? null) ? $data['options'] : [];
        $this->agenceDepartementMap = is_array($data['map'] ?? null) ? $data['map'] : [];

        return $this->agenceOptions;
    }

    public function getDepartementOptions(): array
    {
        if (is_array($this->departementOptions)) {
            return $this->departementOptions;
        }

        $departements = [];
        foreach ($this->getAgenceOptions() as $option) {
            $departement = trim((string) ($option['departement'] ?? ''));
            if ($departement !== '' && !in_array($departement, $departements, true)) {
                $departements[] = $departement;
            }
        }

        sort($departements, SORT_NATURAL | SORT_FLAG_CASE);
        $this->departementOptions = $departements;

        return $this->departementOptions;
    }

    public function serviceExists(string $service): bool
    {
        $service = trim($service);
        if ($service === '') {
            return false;
        }

        return in_array($service, $this->getServiceOptions(), true);
    }

    public function departementExists(string $departement): bool
    {
        $departement = trim($departement);
        if ($departement === '') {
            return false;
        }

        return in_array($departement, $this->getDepartementOptions(), true);
    }

    public function resolveDepartementForAgence(string $agence): ?string
    {
        $agence = trim($agence);
        if ($agence === '') {
            return null;
        }

        $this->getAgenceOptions();

        if (!is_array($this->agenceDepartementMap) || !array_key_exists($agence, $this->agenceDepartementMap)) {
            return null;
        }

        return (string) $this->agenceDepartementMap[$agence];
    }

    private function getLegacyConnection(): Connection
    {
        if ($this->legacyConnection instanceof Connection) {
            return $this->legacyConnection;
        }

        $params = $this->connection->getParams();
        if (($params['dbname'] ?? '') === $this->legacyDatabaseName) {
            $this->legacyConnection = $this->connection;

            return $this->legacyConnection;
        }

        unset($params['url']);
        $params['dbname'] = $this->legacyDatabaseName;

        $this->legacyConnection = DriverManager::getConnection($params, $this->connection->getConfiguration());

        return $this->legacyConnection;
    }
}
