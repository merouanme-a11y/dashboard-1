<?php

namespace App\Service;

use App\Entity\DirectoryService;
use App\Repository\DirectoryServiceRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class DirectoryServiceManager
{
    private const DEFAULT_COLOR = '#6C757D';
    private const OPTIONS_CACHE_KEY = 'directory_service_options_v1';
    private const COLOR_MAP_CACHE_KEY = 'directory_service_color_map_v1';
    private const ADMIN_ROWS_CACHE_KEY = 'directory_service_admin_rows_v1';
    private const LEGACY_SYNC_CACHE_KEY = 'directory_service_legacy_sync_v1';

    private ?Connection $legacyConnection = null;
    private bool $catalogBootstrapped = false;
    private ?array $serviceOptionsCache = null;
    private ?array $serviceColorMapCache = null;
    private ?array $adminRowsCache = null;

    public function __construct(
        private DirectoryServiceRepository $directoryServiceRepository,
        private EntityManagerInterface $em,
        private Connection $connection,
        private string $legacyDatabaseName,
        private CacheInterface $cache,
    ) {}

    public function getServiceOptions(): array
    {
        $this->bootstrapCatalog();

        if (is_array($this->serviceOptionsCache)) {
            return $this->serviceOptionsCache;
        }

        return $this->serviceOptionsCache = $this->cache->get(self::OPTIONS_CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(43200);

            return $this->directoryServiceRepository->findNameList();
        });
    }

    public function getServiceColorMap(): array
    {
        $this->bootstrapCatalog();

        if (is_array($this->serviceColorMapCache)) {
            return $this->serviceColorMapCache;
        }

        return $this->serviceColorMapCache = $this->cache->get(self::COLOR_MAP_CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(43200);

            return $this->directoryServiceRepository->findColorMap();
        });
    }

    public function getAdminRows(): array
    {
        $this->bootstrapCatalog();

        if (is_array($this->adminRowsCache)) {
            return $this->adminRowsCache;
        }

        return $this->adminRowsCache = $this->cache->get(self::ADMIN_ROWS_CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(3600);

            $rows = [];
            foreach ($this->directoryServiceRepository->findAllForAdmin() as $row) {
                $name = $this->normalizeServiceName((string) ($row['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $rows[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => $name,
                    'color' => $this->normalizeHexColor((string) ($row['color'] ?? self::DEFAULT_COLOR)),
                    'usage_count' => (int) ($row['usage_count'] ?? 0),
                ];
            }

            return $rows;
        });
    }

    public function serviceExists(string $serviceName): bool
    {
        $serviceName = $this->normalizeServiceName($serviceName);
        if ($serviceName === '') {
            return false;
        }

        return in_array($serviceName, $this->getServiceOptions(), true);
    }

    public function createService(string $name, string $color): array
    {
        $this->bootstrapCatalog();

        $name = $this->normalizeServiceName($name);
        $color = $this->normalizeHexColor($color);

        if ($name === '') {
            return [
                'success' => false,
                'message' => 'Le nom du service est obligatoire.',
            ];
        }

        if ($this->directoryServiceRepository->findOneByNormalizedName($name) instanceof DirectoryService) {
            return [
                'success' => false,
                'message' => 'Ce service existe deja.',
            ];
        }

        try {
            $service = new DirectoryService();
            $service->setName($name);
            $service->setColor($color);

            $this->em->persist($service);
            $this->em->flush();
            $this->clearCaches();

            return [
                'success' => true,
                'message' => 'Service ajoute.',
                'name' => $name,
            ];
        } catch (\Throwable $exception) {
            error_log('Erreur creation service: ' . $exception->getMessage());

            return [
                'success' => false,
                'message' => 'Impossible d\'ajouter le service.',
            ];
        }
    }

    public function updateService(int $id, string $name, string $color): array
    {
        $this->bootstrapCatalog();

        $service = $this->directoryServiceRepository->find($id);
        if (!$service instanceof DirectoryService) {
            return [
                'success' => false,
                'message' => 'Service introuvable.',
            ];
        }

        $name = $this->normalizeServiceName($name);
        $color = $this->normalizeHexColor($color);

        if ($name === '') {
            return [
                'success' => false,
                'message' => 'Le nom du service est obligatoire.',
            ];
        }

        if ($this->directoryServiceRepository->findOneByNormalizedName($name, $id) instanceof DirectoryService) {
            return [
                'success' => false,
                'message' => 'Un autre service utilise deja ce nom.',
            ];
        }

        $oldName = (string) ($service->getName() ?? '');
        $renamedUsers = 0;

        $this->connection->beginTransaction();

        try {
            $service->setName($name);
            $service->setColor($color);
            $this->em->flush();

            if ($oldName !== '' && $oldName !== $name) {
                $renamedUsers = $this->connection->executeStatement(
                    'UPDATE utilisateur SET service = :newName WHERE service = :oldName',
                    [
                        'newName' => $name,
                        'oldName' => $oldName,
                    ]
                );
            }

            $this->connection->commit();
            $this->clearCaches();

            return [
                'success' => true,
                'message' => 'Service mis a jour.',
                'name' => $name,
                'renamed_users' => $renamedUsers,
            ];
        } catch (\Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            error_log('Erreur mise a jour service: ' . $exception->getMessage());

            return [
                'success' => false,
                'message' => 'Impossible de mettre a jour le service.',
            ];
        }
    }

    public function deleteService(int $id): array
    {
        $this->bootstrapCatalog();

        $service = $this->directoryServiceRepository->find($id);
        if (!$service instanceof DirectoryService) {
            return [
                'success' => false,
                'message' => 'Service introuvable.',
            ];
        }

        $name = (string) ($service->getName() ?? '');
        $detachedUsers = 0;

        $this->connection->beginTransaction();

        try {
            if ($name !== '') {
                $detachedUsers = (int) $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM utilisateur WHERE service = :serviceName',
                    ['serviceName' => $name]
                );

                if ($detachedUsers > 0) {
                    $this->connection->executeStatement(
                        'UPDATE utilisateur SET service = NULL WHERE service = :serviceName',
                        ['serviceName' => $name]
                    );
                }
            }

            $this->em->remove($service);
            $this->em->flush();

            $this->connection->commit();
            $this->clearCaches();

            return [
                'success' => true,
                'message' => 'Service supprime.',
                'name' => $name,
                'detached_users' => $detachedUsers,
            ];
        } catch (\Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            error_log('Erreur suppression service: ' . $exception->getMessage());

            return [
                'success' => false,
                'message' => 'Impossible de supprimer le service.',
            ];
        }
    }

    private function bootstrapCatalog(): void
    {
        if ($this->catalogBootstrapped) {
            return;
        }

        $this->seedFromLocalTables();
        $this->syncFromLegacyOnce();
        $this->catalogBootstrapped = true;
    }

    private function seedFromLocalTables(): void
    {
        if (!$this->tableExists('services')) {
            return;
        }

        try {
            if ($this->tableExists('service_color')) {
                $this->connection->executeStatement(
                    "INSERT IGNORE INTO services (name, color)
                     SELECT TRIM(name), CASE
                         WHEN UPPER(TRIM(color)) REGEXP '^#[0-9A-F]{6}$' THEN UPPER(TRIM(color))
                         ELSE :defaultColor
                     END
                     FROM service_color
                     WHERE name IS NOT NULL AND TRIM(name) <> ''",
                    ['defaultColor' => self::DEFAULT_COLOR]
                );
            }

            if ($this->tableExists('utilisateur')) {
                $this->connection->executeStatement(
                    "INSERT IGNORE INTO services (name, color)
                     SELECT DISTINCT TRIM(service), :defaultColor
                     FROM utilisateur
                     WHERE service IS NOT NULL AND TRIM(service) <> ''",
                    ['defaultColor' => self::DEFAULT_COLOR]
                );
            }
        } catch (\Throwable $exception) {
            error_log('Erreur synchronisation services locaux: ' . $exception->getMessage());
        }
    }

    private function syncFromLegacyOnce(): void
    {
        if (!$this->tableExists('services')) {
            return;
        }

        $this->cache->get(self::LEGACY_SYNC_CACHE_KEY, function (ItemInterface $item): bool {
            $item->expiresAfter(86400);

            try {
                $rows = $this->getLegacyConnection()->fetchAllAssociative('SELECT name, color FROM services ORDER BY name ASC');
                if ($rows === []) {
                    return true;
                }

                $existingRows = $this->directoryServiceRepository->findAllIndexedByNormalizedName();
                $hasChanges = false;

                foreach ($rows as $row) {
                    $name = $this->normalizeServiceName((string) ($row['name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }

                    $color = $this->normalizeHexColor((string) ($row['color'] ?? self::DEFAULT_COLOR));
                    $key = $this->normalizeServiceKey($name);
                    $existing = $existingRows[$key] ?? null;

                    if (!is_array($existing)) {
                        $this->connection->insert('services', [
                            'name' => $name,
                            'color' => $color,
                        ]);
                        $existingRows[$key] = [
                            'id' => (int) $this->connection->lastInsertId(),
                            'name' => $name,
                            'color' => $color,
                        ];
                        $hasChanges = true;
                        continue;
                    }

                    $currentColor = $this->normalizeHexColor((string) ($existing['color'] ?? self::DEFAULT_COLOR));
                    if ($currentColor === self::DEFAULT_COLOR && $color !== self::DEFAULT_COLOR) {
                        $this->connection->update('services', ['color' => $color], ['id' => (int) ($existing['id'] ?? 0)]);
                        $existingRows[$key]['color'] = $color;
                        $hasChanges = true;
                    }
                }

                if ($hasChanges) {
                    $this->clearCaches();
                }
            } catch (\Throwable $exception) {
                $item->expiresAfter(600);
                error_log('Erreur synchronisation services legacy: ' . $exception->getMessage());
            }

            return true;
        });
    }

    private function clearCaches(): void
    {
        $this->serviceOptionsCache = null;
        $this->serviceColorMapCache = null;
        $this->adminRowsCache = null;
        $this->cache->delete(self::OPTIONS_CACHE_KEY);
        $this->cache->delete(self::COLOR_MAP_CACHE_KEY);
        $this->cache->delete(self::ADMIN_ROWS_CACHE_KEY);
    }

    private function normalizeServiceName(string $value): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/\s+/', ' ', $value);

        return trim((string) $value);
    }

    private function normalizeServiceKey(string $value): string
    {
        return mb_strtolower($this->normalizeServiceName($value));
    }

    private function normalizeHexColor(string $value): string
    {
        $color = strtoupper(trim($value));
        if ($color !== '' && !str_starts_with($color, '#')) {
            $color = '#' . $color;
        }

        return preg_match('/^#[0-9A-F]{6}$/', $color) ? $color : self::DEFAULT_COLOR;
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

    private function tableExists(string $tableName): bool
    {
        return $this->connection->createSchemaManager()->tablesExist([$tableName]);
    }
}
