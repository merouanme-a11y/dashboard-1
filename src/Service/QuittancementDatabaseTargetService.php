<?php

namespace App\Service;

use App\Entity\ThemeSetting;
use App\Repository\ThemeSettingRepository;
use Doctrine\ORM\EntityManagerInterface;

class QuittancementDatabaseTargetService
{
    public const SETTING_KEY = 'quittancement_generator_database_targets';
    public const ENV_TARGET_ID = 'quittancement-env-default';

    public function __construct(
        private ThemeSettingRepository $themeSettingRepository,
        private EntityManagerInterface $em,
        private string $defaultHost,
        private int $defaultPort,
        private string $defaultDatabase,
        private string $defaultUsername,
        private string $defaultPassword,
        private string $appSecret,
    ) {}

    /**
     * @return list<array{
     *     id:string,
     *     label:string,
     *     host:string,
     *     port:int,
     *     database:string,
     *     username:string,
     *     passwordConfigured:bool,
     *     passwordPreview:string,
     *     isBuiltIn:bool,
     *     createdAt:string
     * }>
     */
    public function getTargets(bool $includeSecrets = false): array
    {
        $targets = [];
        $envTarget = $this->buildEnvTarget($includeSecrets);
        if ($envTarget !== null) {
            $targets[] = $envTarget;
        }

        $setting = $this->themeSettingRepository->findByKey(self::SETTING_KEY);
        if (!$setting instanceof ThemeSetting || trim((string) $setting->getSettingValue()) === '') {
            return $targets;
        }

        try {
            $payload = json_decode((string) $setting->getSettingValue(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $targets;
        }

        foreach ((array) ($payload['targets'] ?? []) as $target) {
            $normalized = $this->normalizeStoredTarget(is_array($target) ? $target : [], $includeSecrets);
            if ($normalized !== null) {
                $targets[] = $normalized;
            }
        }

        return $targets;
    }

    public function resolveRequestedTargetId(string $requestedId = ''): string
    {
        $requestedId = trim($requestedId);
        $targets = $this->getTargets();
        if ($targets === []) {
            return '';
        }

        if ($requestedId !== '') {
            foreach ($targets as $target) {
                if ((string) ($target['id'] ?? '') === $requestedId) {
                    return $requestedId;
                }
            }
        }

        return (string) ($targets[0]['id'] ?? '');
    }

    /**
     * @return array{
     *     id:string,
     *     label:string,
     *     host:string,
     *     port:int,
     *     database:string,
     *     username:string,
     *     passwordConfigured:bool,
     *     passwordPreview:string,
     *     isBuiltIn:bool,
     *     createdAt:string,
     *     password?:string
     * }|null
     */
    public function getTargetById(string $targetId, bool $includeSecrets = false): ?array
    {
        $targetId = trim($targetId);
        if ($targetId === '') {
            return null;
        }

        foreach ($this->getTargets($includeSecrets) as $target) {
            if ((string) ($target['id'] ?? '') === $targetId) {
                return $target;
            }
        }

        return null;
    }

    /**
     * @return list<array{
     *     id:string,
     *     label:string,
     *     host:string,
     *     port:int,
     *     database:string,
     *     username:string,
     *     passwordConfigured:bool,
     *     passwordPreview:string,
     *     isBuiltIn:bool,
     *     createdAt:string
     * }>
     */
    public function addTarget(
        string $label,
        string $host,
        int|string $port,
        string $database,
        string $username,
        string $password,
    ): array {
        $settings = $this->getRawSettings();
        $settings['targets'][] = [
            'id' => $this->generateTargetId(),
            'label' => $this->resolveLabel($label, $host, $database),
            'host' => $this->normalizeHost($host),
            'port' => $this->normalizePort($port),
            'database' => $this->normalizeDatabase($database),
            'username' => $this->normalizeUsername($username),
            'password' => $this->encryptPassword($this->normalizePassword($password)),
            'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        $this->saveSettings($settings);

        return $this->getTargets();
    }

    /**
     * @return list<array{
     *     id:string,
     *     label:string,
     *     host:string,
     *     port:int,
     *     database:string,
     *     username:string,
     *     passwordConfigured:bool,
     *     passwordPreview:string,
     *     isBuiltIn:bool,
     *     createdAt:string
     * }>
     */
    public function removeTarget(string $targetId): array
    {
        $targetId = trim($targetId);
        if ($targetId === '') {
            throw new \InvalidArgumentException('Cible BDD introuvable.');
        }

        if ($targetId === self::ENV_TARGET_ID) {
            throw new \InvalidArgumentException('La configuration serveur par defaut ne peut pas etre supprimee depuis l interface.');
        }

        $settings = $this->getRawSettings();
        $remainingTargets = [];
        $removed = false;

        foreach ((array) ($settings['targets'] ?? []) as $target) {
            if (!is_array($target)) {
                continue;
            }

            if ((string) ($target['id'] ?? '') === $targetId) {
                $removed = true;
                continue;
            }

            $remainingTargets[] = $target;
        }

        if (!$removed) {
            throw new \InvalidArgumentException('Cible BDD introuvable.');
        }

        $settings['targets'] = $remainingTargets;
        $this->saveSettings($settings);

        return $this->getTargets();
    }

    private function buildEnvTarget(bool $includeSecrets): ?array
    {
        $host = trim($this->defaultHost);
        $database = trim($this->defaultDatabase);
        $username = trim($this->defaultUsername);

        if ($host === '' || $database === '' || $username === '' || $this->defaultPort <= 0) {
            return null;
        }

        $password = trim($this->defaultPassword);
        $target = [
            'id' => self::ENV_TARGET_ID,
            'label' => 'Serveur par defaut',
            'host' => $host,
            'port' => $this->defaultPort,
            'database' => $database,
            'username' => $username,
            'passwordConfigured' => $password !== '',
            'passwordPreview' => $this->maskSecret($password),
            'isBuiltIn' => true,
            'createdAt' => '',
        ];

        if ($includeSecrets) {
            $target['password'] = $password;
        }

        return $target;
    }

    /**
     * @return array{id:string,label:string,host:string,port:int,database:string,username:string,passwordConfigured:bool,passwordPreview:string,isBuiltIn:bool,createdAt:string,password?:string}|null
     */
    private function normalizeStoredTarget(array $target, bool $includeSecrets): ?array
    {
        $id = $this->normalizeScalar($target['id'] ?? '', 80);
        $host = $this->normalizeScalar($target['host'] ?? '', 255);
        $database = $this->normalizeScalar($target['database'] ?? '', 120);
        $username = $this->normalizeScalar($target['username'] ?? '', 120);
        $port = $this->normalizePort($target['port'] ?? 5432, false);

        if ($id === '' || $host === '' || $database === '' || $username === '' || $port === null) {
            return null;
        }

        $password = $this->decryptPassword((string) ($target['password'] ?? ''));
        $normalizedTarget = [
            'id' => $id,
            'label' => $this->resolveLabel((string) ($target['label'] ?? ''), $host, $database),
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'passwordConfigured' => $password !== '',
            'passwordPreview' => $this->maskSecret($password),
            'isBuiltIn' => false,
            'createdAt' => $this->normalizeScalar($target['createdAt'] ?? '', 80),
        ];

        if ($includeSecrets) {
            $normalizedTarget['password'] = $password;
        }

        return $normalizedTarget;
    }

    private function getRawSettings(): array
    {
        $setting = $this->themeSettingRepository->findByKey(self::SETTING_KEY);
        if (!$setting instanceof ThemeSetting || trim((string) $setting->getSettingValue()) === '') {
            return ['targets' => []];
        }

        try {
            $payload = json_decode((string) $setting->getSettingValue(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['targets' => []];
        }

        return [
            'targets' => is_array($payload['targets'] ?? null) ? array_values($payload['targets']) : [],
        ];
    }

    private function saveSettings(array $settings): void
    {
        $normalizedTargets = [];
        foreach ((array) ($settings['targets'] ?? []) as $target) {
            if (!is_array($target)) {
                continue;
            }

            $id = $this->normalizeScalar($target['id'] ?? '', 80);
            $host = $this->normalizeHost((string) ($target['host'] ?? ''));
            $port = $this->normalizePort($target['port'] ?? 5432, false);
            $database = $this->normalizeDatabase((string) ($target['database'] ?? ''));
            $username = $this->normalizeUsername((string) ($target['username'] ?? ''));
            if ($id === '' || $host === '' || $port === null || $database === '' || $username === '') {
                continue;
            }

            $normalizedTargets[] = [
                'id' => $id,
                'label' => $this->resolveLabel((string) ($target['label'] ?? ''), $host, $database),
                'host' => $host,
                'port' => $port,
                'database' => $database,
                'username' => $username,
                'password' => $this->normalizeScalar($target['password'] ?? '', 4096),
                'createdAt' => $this->normalizeScalar($target['createdAt'] ?? '', 80),
            ];
        }

        $setting = $this->themeSettingRepository->findByKey(self::SETTING_KEY);
        if (!$setting instanceof ThemeSetting) {
            $setting = (new ThemeSetting())->setSettingKey(self::SETTING_KEY);
            $this->em->persist($setting);
        }

        $setting->setSettingValue((string) json_encode([
            'targets' => $normalizedTargets,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        $this->em->flush();
    }

    private function resolveLabel(string $label, string $host, string $database): string
    {
        $normalizedLabel = $this->normalizeScalar($label, 120);
        if ($normalizedLabel !== '') {
            return $normalizedLabel;
        }

        return trim($host . ' / ' . $database);
    }

    private function normalizeHost(string $host): string
    {
        $host = $this->normalizeScalar($host, 255);
        if ($host === '') {
            throw new \InvalidArgumentException('Le serveur BDD est obligatoire.');
        }

        return $host;
    }

    private function normalizeDatabase(string $database): string
    {
        $database = $this->normalizeScalar($database, 120);
        if ($database === '') {
            throw new \InvalidArgumentException('Le nom de la base est obligatoire.');
        }

        return $database;
    }

    private function normalizeUsername(string $username): string
    {
        $username = $this->normalizeScalar($username, 120);
        if ($username === '') {
            throw new \InvalidArgumentException('L utilisateur BDD est obligatoire.');
        }

        return $username;
    }

    private function normalizePassword(string $password): string
    {
        return $this->normalizeScalar($password, 1000);
    }

    private function normalizePort(int|string $port, bool $throwOnError = true): ?int
    {
        $normalizedPort = (int) $port;
        if ($normalizedPort < 1 || $normalizedPort > 65535) {
            if ($throwOnError) {
                throw new \InvalidArgumentException('Le port PostgreSQL doit etre compris entre 1 et 65535.');
            }

            return null;
        }

        return $normalizedPort;
    }

    private function generateTargetId(): string
    {
        return 'qg-target-' . substr((string) bin2hex(random_bytes(6)), 0, 12);
    }

    private function normalizeScalar(mixed $value, int $maxLength): string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '';
        }

        return mb_substr($normalized, 0, $maxLength);
    }

    private function maskSecret(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (mb_strlen($trimmed) <= 8) {
            return str_repeat('*', mb_strlen($trimmed));
        }

        return mb_substr($trimmed, 0, 2) . str_repeat('*', max(4, mb_strlen($trimmed) - 4)) . mb_substr($trimmed, -2);
    }

    private function encryptPassword(string $password): string
    {
        if ($password === '') {
            return '';
        }

        if (!function_exists('openssl_encrypt')) {
            return $password;
        }

        $iv = random_bytes(16);
        $encrypted = openssl_encrypt(
            $password,
            'AES-256-CBC',
            $this->getEncryptionKey(),
            OPENSSL_RAW_DATA,
            $iv
        );

        if (!is_string($encrypted) || $encrypted === '') {
            return $password;
        }

        return 'enc:v1:' . base64_encode($iv . $encrypted);
    }

    private function decryptPassword(string $password): string
    {
        $password = trim($password);
        if ($password === '') {
            return '';
        }

        if (!str_starts_with($password, 'enc:v1:')) {
            return $password;
        }

        if (!function_exists('openssl_decrypt')) {
            return '';
        }

        $decoded = base64_decode(substr($password, 7), true);
        if ($decoded === false || strlen($decoded) <= 16) {
            return '';
        }

        $iv = substr($decoded, 0, 16);
        $ciphertext = substr($decoded, 16);
        $decrypted = openssl_decrypt(
            $ciphertext,
            'AES-256-CBC',
            $this->getEncryptionKey(),
            OPENSSL_RAW_DATA,
            $iv
        );

        return is_string($decrypted) ? $decrypted : '';
    }

    private function getEncryptionKey(): string
    {
        $secret = trim($this->appSecret);

        return hash('sha256', $secret !== '' ? $secret : self::SETTING_KEY, true);
    }
}
