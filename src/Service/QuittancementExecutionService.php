<?php

namespace App\Service;

use PDO;
use RuntimeException;
use Throwable;

class QuittancementExecutionService
{
    /**
     * @var array{host:string,port:int,database:string,username:string,password:string}
     */
    private array $defaultTarget;

    public function __construct(
        private string $host,
        private int $port,
        private string $database,
        private string $username,
        private string $password,
    ) {
        $this->defaultTarget = [
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->password,
        ];
    }

    public function isConfigured(?array $targetConfig = null): bool
    {
        $target = $this->normalizeTargetConfig($targetConfig);

        return trim((string) ($target['host'] ?? '')) !== ''
            && (int) ($target['port'] ?? 0) > 0
            && trim((string) ($target['database'] ?? '')) !== ''
            && trim((string) ($target['username'] ?? '')) !== '';
    }

    /**
     * @return array{host:string,port:int,database:string}
     */
    public function getTargetSummary(?array $targetConfig = null): array
    {
        $target = $this->normalizeTargetConfig($targetConfig);

        return [
            'host' => (string) ($target['host'] ?? ''),
            'port' => (int) ($target['port'] ?? 0),
            'database' => (string) ($target['database'] ?? ''),
        ];
    }

    /**
     * @param array{rattrapage:string,quittancement:string,sante_collective:string} $sqlSections
     *
     * @return array{executedBlocks:list<string>,target:array{host:string,port:int,database:string},committed:bool}
     */
    public function executeSqlSections(array $sqlSections, bool $commit = true): array
    {
        if (!$this->isConfigured($this->defaultTarget)) {
            throw new RuntimeException('La connexion PostgreSQL du module quittancement n est pas configuree.');
        }

        $connection = $this->createConnection();
        return $this->runSqlSections($connection, $sqlSections, $this->getTargetSummary($this->defaultTarget), $commit);
    }

    /**
     * @param array{rattrapage:string,quittancement:string,sante_collective:string} $sqlSections
     * @param array{host:string,port:int,database:string,username:string,password:string} $targetConfig
     *
     * @return array{executedBlocks:list<string>,target:array{host:string,port:int,database:string},committed:bool}
     */
    public function executeSqlSectionsOnTarget(array $sqlSections, array $targetConfig, bool $commit = true): array
    {
        if (!$this->isConfigured($targetConfig)) {
            throw new RuntimeException('La connexion PostgreSQL selectionnee est incomplete.');
        }

        $normalizedTarget = $this->normalizeTargetConfig($targetConfig);
        $connection = $this->createConnectionForTarget($normalizedTarget);

        return $this->runSqlSections($connection, $sqlSections, $this->getTargetSummary($normalizedTarget), $commit);
    }

    /**
     * @param array{rattrapage:string,quittancement:string,sante_collective:string} $sqlSections
     * @param array{host:string,port:int,database:string} $targetSummary
     *
     * @return array{executedBlocks:list<string>,target:array{host:string,port:int,database:string},committed:bool}
     */
    private function runSqlSections(PDO $connection, array $sqlSections, array $targetSummary, bool $commit): array
    {
        $blocks = [
            'rattrapage' => 'Bloc 1 - Rattrapage',
            'quittancement' => 'Bloc 2 - Quittancement',
            'sante_collective' => 'Bloc 3 - Sante collective',
        ];
        $executedBlocks = [];
        $currentBlockLabel = 'Execution SQL';

        try {
            $connection->beginTransaction();

            foreach ($blocks as $key => $label) {
                $sql = trim((string) ($sqlSections[$key] ?? ''));
                if ($sql === '') {
                    throw new RuntimeException(sprintf('%s est vide et ne peut pas etre execute.', $label));
                }

                $currentBlockLabel = $label;
                $connection->exec($sql);
                $executedBlocks[] = $label;
            }

            if ($commit) {
                $connection->commit();
            } elseif ($connection->inTransaction()) {
                $connection->rollBack();
            }
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw new RuntimeException(
                sprintf('%s a echoue: %s', $currentBlockLabel, $exception->getMessage()),
                0,
                $exception
            );
        }

        return [
            'executedBlocks' => $executedBlocks,
            'target' => $targetSummary,
            'committed' => $commit,
        ];
    }

    protected function createConnection(): PDO
    {
        $this->assertPgsqlDriverIsAvailable($this->defaultTarget);

        return $this->createPdoConnection($this->defaultTarget);
    }

    /**
     * @param array{host:string,port:int,database:string,username:string,password:string} $targetConfig
     */
    protected function createConnectionForTarget(array $targetConfig): PDO
    {
        $this->assertPgsqlDriverIsAvailable($targetConfig);

        return $this->createPdoConnection($targetConfig);
    }

    /**
     * @param array{host:string,port:int,database:string,username:string,password:string} $targetConfig
     */
    private function createPdoConnection(array $targetConfig): PDO
    {
        return new PDO(
            sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                (string) ($targetConfig['host'] ?? ''),
                (int) ($targetConfig['port'] ?? 0),
                (string) ($targetConfig['database'] ?? '')
            ),
            (string) ($targetConfig['username'] ?? ''),
            (string) ($targetConfig['password'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    /**
     * @param array{host:string,port:int,database:string,username:string,password:string}|null $targetConfig
     */
    private function normalizeTargetConfig(?array $targetConfig): array
    {
        $target = $targetConfig ?? $this->defaultTarget;

        return [
            'host' => trim((string) ($target['host'] ?? '')),
            'port' => (int) ($target['port'] ?? 0),
            'database' => trim((string) ($target['database'] ?? '')),
            'username' => trim((string) ($target['username'] ?? '')),
            'password' => (string) ($target['password'] ?? ''),
        ];
    }

    /**
     * @param array{host:string,port:int,database:string,username:string,password:string}|null $targetConfig
     */
    private function assertPgsqlDriverIsAvailable(?array $targetConfig = null): void
    {
        if (!class_exists(PDO::class)) {
            throw new RuntimeException('PDO est indisponible dans ce runtime PHP.');
        }

        $availableDrivers = PDO::getAvailableDrivers();
        if (in_array('pgsql', $availableDrivers, true) && extension_loaded('pdo_pgsql')) {
            return;
        }

        $loadedIni = php_ini_loaded_file();
        $driverList = $availableDrivers !== [] ? implode(', ', $availableDrivers) : 'aucun';
        $targetSummary = $this->getTargetSummary($targetConfig);

        throw new RuntimeException(sprintf(
            'Le driver PostgreSQL PDO n est pas charge dans le runtime web. Cible: %s:%d/%s. SAPI: %s. INI charge: %s. Drivers PDO detectes: %s. Sous Wamp, verifiez surtout phpForApache.ini puis redemarrez Apache.',
            (string) ($targetSummary['host'] ?? ''),
            (int) ($targetSummary['port'] ?? 0),
            (string) ($targetSummary['database'] ?? ''),
            PHP_SAPI,
            $loadedIni !== false ? $loadedIni : 'aucun fichier php.ini detecte',
            $driverList
        ));
    }
}
