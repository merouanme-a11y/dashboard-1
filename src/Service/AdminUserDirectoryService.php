<?php

namespace App\Service;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AdminUserDirectoryService
{
    public const USERS_MODULE = 'utilisateurs';
    public const IMPORT_MAX_FILE_SIZE = 5242880;

    /**
     * @var array<int, string>
     */
    private const DEFAULT_PROFILE_TYPES = [
        'Admin',
        'Responsable',
        'Superviseur',
        'Employe',
    ];

    /**
     * @var array<string, string>
     */
    private const PROFILE_ROLE_MAP = [
        'Admin' => 'ROLE_ADMIN',
        'Responsable' => 'ROLE_RESPONSABLE',
        'Superviseur' => 'ROLE_SUPERVISEUR',
        'Employe' => 'ROLE_EMPLOYE',
    ];

    public function __construct(
        private UtilisateurRepository $utilisateurRepository,
        private EntityManagerInterface $em,
        private Connection $connection,
        private DirectoryServiceManager $directoryServiceManager,
        private LegacyDirectoryOptionsService $legacyDirectoryOptionsService,
        private FileUploadService $fileUploadService,
        private UserPasswordHasherInterface $passwordHasher,
        private TransportInterface $mailerTransport,
        private MenuConfigService $menuConfigService,
        private string $mailerFrom,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function getPageContext(): array
    {
        $serviceColors = $this->directoryServiceManager->getServiceColorMap();
        $serviceOptions = $this->directoryServiceManager->getServiceOptions();
        $departementOptions = $this->legacyDirectoryOptionsService->getDepartementOptions();
        $agenceOptions = $this->legacyDirectoryOptionsService->getAgenceOptions();
        $profileOptions = $this->getAvailableProfileTypes();
        $rows = $this->connection->fetchAllAssociative(
            'SELECT u.id,
                    u.prenom,
                    u.nom,
                    u.email,
                    u.service,
                    u.departement,
                    u.agence,
                    u.telephone,
                    u.numero_court AS numeroCourt,
                    u.profile_type AS profileType,
                    u.photo,
                    s.color AS serviceColor
             FROM utilisateur u
             LEFT JOIN services s ON u.service COLLATE utf8mb4_unicode_ci = s.name
             ORDER BY u.nom ASC, u.prenom ASC, u.id ASC'
        );

        $users = [];
        foreach ($rows as $row) {
            $prenom = trim((string) ($row['prenom'] ?? ''));
            $nom = trim((string) ($row['nom'] ?? ''));
            $service = trim((string) ($row['service'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            $departement = trim((string) ($row['departement'] ?? ''));
            $agence = trim((string) ($row['agence'] ?? ''));
            $telephone = trim((string) ($row['telephone'] ?? ''));
            $numeroCourt = trim((string) ($row['numeroCourt'] ?? ''));
            $profileType = trim((string) ($row['profileType'] ?? ''));
            $displayName = trim($prenom . ' ' . $nom);
            $photoUrl = $this->fileUploadService->resolvePublicPath((string) ($row['photo'] ?? ''));
            $initials = mb_strtoupper(mb_substr($prenom !== '' ? $prenom : $displayName, 0, 1) . mb_substr($nom, 0, 1));
            $serviceColor = $this->normalizeHexColor((string) ($row['serviceColor'] ?? ($serviceColors[$service] ?? '')));

            $users[] = [
                'id' => (int) ($row['id'] ?? 0),
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'service' => $service,
                'departement' => $departement,
                'agence' => $agence,
                'telephone' => $telephone,
                'numeroCourt' => $numeroCourt,
                'profileType' => $profileType !== '' ? $profileType : 'Responsable',
                'photoUrl' => $photoUrl,
                'hasPhoto' => $photoUrl !== '',
                'initials' => $initials !== '' ? $initials : '--',
                'displayName' => $displayName !== '' ? $displayName : ('Utilisateur #' . (int) ($row['id'] ?? 0)),
                'searchBlob' => mb_strtolower(trim(implode(' ', [
                    (string) ($row['id'] ?? ''),
                    $nom,
                    $prenom,
                    $email,
                    $service,
                    $departement,
                    $agence,
                    $telephone,
                    $numeroCourt,
                    $profileType,
                ]))),
                'serviceColor' => $serviceColor,
                'serviceStyle' => $this->buildServiceBadgeStyle($serviceColor),
            ];
        }

        return [
            'users' => $users,
            'serviceOptions' => $serviceOptions,
            'serviceColorMap' => $serviceColors,
            'departementOptions' => $departementOptions,
            'agenceOptions' => $agenceOptions,
            'profileOptions' => $profileOptions,
        ];
    }

    public function inlineUpdate(Utilisateur $actor, int $userId, string $field, string $value): array
    {
        $field = trim($field);
        $value = trim($value);
        $allowedFields = ['nom', 'prenom', 'email', 'service', 'departement', 'agence', 'telephone', 'numero_court', 'profile_type'];

        if ($userId < 1 || !in_array($field, $allowedFields, true)) {
            return ['ok' => false, 'message' => 'Requete invalide.'];
        }

        $user = $this->utilisateurRepository->find($userId);
        if (!$user instanceof Utilisateur) {
            return ['ok' => false, 'message' => 'Utilisateur introuvable.'];
        }

        if ($field === 'email') {
            if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                return ['ok' => false, 'message' => 'Email invalide.'];
            }

            if ($value !== '') {
                $duplicateCount = (int) $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM utilisateur WHERE email = :email AND id <> :id',
                    ['email' => $value, 'id' => $userId]
                );

                if ($duplicateCount > 0) {
                    return ['ok' => false, 'message' => 'Email deja utilise.'];
                }
            }
        }

        if ($field === 'service' && $value !== '' && !$this->directoryServiceManager->serviceExists($value)) {
            return ['ok' => false, 'message' => 'Service invalide.'];
        }

        if ($field === 'departement' && $value !== '' && !$this->legacyDirectoryOptionsService->departementExists($value)) {
            return ['ok' => false, 'message' => 'Departement invalide.'];
        }

        if ($field === 'profile_type') {
            if (!in_array('ROLE_ADMIN', $actor->getRoles(), true)) {
                return ['ok' => false, 'message' => 'Seul un administrateur peut modifier le type de profil.'];
            }

            if (!in_array($value, $this->getAvailableProfileTypes(), true)) {
                return ['ok' => false, 'message' => 'Type de profil invalide.'];
            }
        }

        if ($field === 'agence' && $value !== '') {
            $agenceDepartement = $this->legacyDirectoryOptionsService->resolveDepartementForAgence($value);
            if ($agenceDepartement === null) {
                return ['ok' => false, 'message' => 'Agence invalide.'];
            }

            $currentDepartement = trim((string) ($user->getDepartement() ?? ''));
            if ($currentDepartement !== '' && $agenceDepartement !== '' && $agenceDepartement !== $currentDepartement) {
                return ['ok' => false, 'message' => 'Cette agence ne correspond pas au departement choisi.'];
            }
        }

        if ($field === 'departement') {
            $currentAgence = trim((string) ($user->getAgence() ?? ''));
            if ($currentAgence !== '') {
                $agenceDepartement = $this->legacyDirectoryOptionsService->resolveDepartementForAgence($currentAgence);
                if ($value === '' || ($agenceDepartement !== null && $agenceDepartement !== $value)) {
                    $user->setDepartement($value !== '' ? $value : null);
                    $user->setAgence(null);
                    $this->em->flush();

                    return [
                        'ok' => true,
                        'value' => $value,
                        'updatedFields' => [
                            'departement' => $value,
                            'agence' => '',
                        ],
                    ];
                }
            }
        }

        $updatedFields = [];

        switch ($field) {
            case 'nom':
                $user->setNom($value);
                $updatedFields['nom'] = $value;
                break;
            case 'prenom':
                $user->setPrenom($value);
                $updatedFields['prenom'] = $value;
                break;
            case 'email':
                $user->setEmail($value);
                $updatedFields['email'] = $value;
                break;
            case 'service':
                $user->setService($value !== '' ? $value : null);
                $updatedFields['service'] = $value;
                break;
            case 'departement':
                $user->setDepartement($value !== '' ? $value : null);
                $updatedFields['departement'] = $value;
                break;
            case 'agence':
                $user->setAgence($value !== '' ? $value : null);
                $updatedFields['agence'] = $value;
                break;
            case 'telephone':
                $user->setTelephone($value !== '' ? $value : null);
                $updatedFields['telephone'] = $value;
                break;
            case 'numero_court':
                $user->setNumeroCourt($value !== '' ? $value : null);
                $updatedFields['numero_court'] = $value;
                break;
            case 'profile_type':
                $user->setProfileType($value);
                $user->setRoles([$this->resolveRoleForProfileType($value)]);
                $updatedFields['profile_type'] = $value;
                break;
        }

        $this->em->flush();

        return [
            'ok' => true,
            'value' => $value,
            'updatedFields' => $updatedFields,
        ];
    }

    public function resetPassword(int $userId): array
    {
        $user = $this->utilisateurRepository->find($userId);
        if (!$user instanceof Utilisateur) {
            return [
                'success' => false,
                'flashType' => 'danger',
                'message' => 'Utilisateur introuvable.',
            ];
        }

        $temporaryPassword = $this->generateTemporaryPassword();
        $user
            ->setMotDePasse($this->passwordHasher->hashPassword($user, $temporaryPassword))
            ->setForcePasswordChange(true);

        $this->em->flush();

        $email = trim((string) ($user->getEmail() ?? ''));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return [
                'success' => true,
                'flashType' => 'danger',
                'message' => 'Mot de passe reinitialise pour ' . $this->formatUserDisplayName($user) . ', mais email impossible.',
            ];
        }

        $loginUrl = $this->urlGenerator->generate('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $body = implode("\n\n", [
            'Bonjour ' . ($user->getPrenom() ?: $user->getEmail()) . ',',
            'Un administrateur a reinitialise votre mot de passe.',
            'Lien de connexion : ' . $loginUrl,
            'Identifiant : ' . $email,
            'Mot de passe temporaire : ' . $temporaryPassword,
            'Vous devrez modifier ce mot de passe a votre prochaine connexion.',
        ]);

        $message = (new Email())
            ->from($this->mailerFrom)
            ->to($email)
            ->subject('Dashboard - Reinitialisation de votre mot de passe')
            ->text($body);

        try {
            $this->mailerTransport->send($message);

            return [
                'success' => true,
                'flashType' => 'success',
                'message' => 'Mot de passe reinitialise et email envoye a ' . $email . '.',
            ];
        } catch (TransportExceptionInterface) {
            return [
                'success' => true,
                'flashType' => 'danger',
                'message' => 'Mot de passe reinitialise pour ' . $email . ', mais envoi email impossible.',
            ];
        }
    }

    public function deleteUser(Utilisateur $actor, int $targetUserId, string $currentPassword): array
    {
        if ($targetUserId < 1) {
            return [
                'success' => false,
                'message' => 'Utilisateur manquant.',
            ];
        }

        if ($actor->getId() === $targetUserId) {
            return [
                'success' => false,
                'message' => 'Vous ne pouvez pas supprimer votre propre compte depuis cette session.',
            ];
        }

        if (!$this->passwordHasher->isPasswordValid($actor, $currentPassword)) {
            return [
                'success' => false,
                'message' => 'Mot de passe de confirmation invalide.',
            ];
        }

        $user = $this->utilisateurRepository->find($targetUserId);
        if (!$user instanceof Utilisateur) {
            return [
                'success' => false,
                'message' => 'Utilisateur introuvable.',
            ];
        }

        $photoPath = (string) ($user->getPhoto() ?? '');

        $this->connection->beginTransaction();

        try {
            $this->connection->executeStatement(
                'DELETE FROM permission WHERE utilisateur_id = :userId',
                ['userId' => $targetUserId]
            );

            $this->em->remove($user);
            $this->em->flush();
            $this->connection->commit();
        } catch (\Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression de l utilisateur.',
            ];
        }

        $this->menuConfigService->removeUserOverrides($targetUserId);
        if ($photoPath !== '') {
            $this->fileUploadService->deleteFile($photoPath);
        }

        return [
            'success' => true,
            'message' => 'Utilisateur supprime avec succes : ' . $this->formatUserDisplayName($user) . '.',
        ];
    }

    public function importUsersFromFile(UploadedFile $file): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, ['csv', 'xls', 'xlsx'], true)) {
            return [
                'success' => false,
                'message' => 'Format non supporte. Utilisez CSV, XLS ou XLSX.',
            ];
        }

        if (!$file->isValid()) {
            return [
                'success' => false,
                'message' => 'Fichier d import invalide.',
            ];
        }

        if ((int) $file->getSize() <= 0 || (int) $file->getSize() > self::IMPORT_MAX_FILE_SIZE) {
            return [
                'success' => false,
                'message' => 'Taille de fichier invalide (max 5 Mo).',
            ];
        }

        $rawRows = match ($extension) {
            'csv' => $this->parseCsvRows($file->getPathname()),
            'xlsx' => $this->parseXlsxRows($file->getPathname()),
            default => $this->parseXlsRows($file->getPathname()),
        };

        $users = $this->rowsToUsers($rawRows);
        if ($users === []) {
            return [
                'success' => false,
                'message' => 'Aucune ligne utilisateur exploitable dans le fichier.',
            ];
        }

        $allowedProfiles = $this->getAvailableProfileTypes();
        $batchEmailOwners = [];
        $emailWarnings = [];
        $updated = 0;
        $inserted = 0;
        $nowSql = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->connection->beginTransaction();

        try {
            foreach ($users as $row) {
                $id = trim((string) ($row['id'] ?? ''));
                if ($id === '' || !ctype_digit($id)) {
                    throw new \RuntimeException('Identifiant invalide dans le fichier import.');
                }

                $userId = (int) $id;
                $email = trim((string) ($row['email'] ?? ''));
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    throw new \RuntimeException('Email invalide pour l utilisateur : ' . $id);
                }

                if ($email !== '') {
                    $emailKey = mb_strtolower($email);
                    if (isset($batchEmailOwners[$emailKey]) && $batchEmailOwners[$emailKey] !== $id) {
                        $emailWarnings[] = 'Email duplique ignore pour ' . $id . ' : ' . $email;
                        $email = '';
                    } else {
                        $existingEmailOwner = (string) $this->connection->fetchOne(
                            'SELECT id FROM utilisateur WHERE email = :email LIMIT 1',
                            ['email' => $email]
                        );

                        if ($existingEmailOwner !== '' && $existingEmailOwner !== $id) {
                            $emailWarnings[] = 'Email deja utilise ignore pour ' . $id . ' : ' . $email;
                            $email = '';
                        } else {
                            $batchEmailOwners[$emailKey] = $id;
                        }
                    }
                }

                $profileType = trim((string) ($row['profile_type'] ?? 'Responsable'));
                if ($profileType === '' || !in_array($profileType, $allowedProfiles, true)) {
                    $profileType = 'Responsable';
                }

                $normalizedService = trim((string) ($row['service'] ?? ''));
                if ($normalizedService !== '' && !$this->directoryServiceManager->serviceExists($normalizedService)) {
                    $normalizedService = '';
                }

                $normalizedDepartement = trim((string) ($row['departement'] ?? ''));
                if ($normalizedDepartement !== '' && !$this->legacyDirectoryOptionsService->departementExists($normalizedDepartement)) {
                    $normalizedDepartement = '';
                }

                $normalizedAgence = trim((string) ($row['agence'] ?? ''));
                if ($normalizedAgence !== '') {
                    $agenceDepartement = $this->legacyDirectoryOptionsService->resolveDepartementForAgence($normalizedAgence);
                    if ($agenceDepartement === null) {
                        $normalizedAgence = '';
                    } elseif ($normalizedDepartement !== '' && $agenceDepartement !== $normalizedDepartement) {
                        $normalizedAgence = '';
                    }
                }

                $existingUser = $this->utilisateurRepository->find($userId);
                if ($existingUser instanceof Utilisateur) {
                    $existingUser->setNom(trim((string) ($row['nom'] ?? '')));
                    $existingUser->setPrenom(trim((string) ($row['prenom'] ?? '')));
                    $existingUser->setEmail($email !== '' ? $email : ('import-' . $userId . '@local.invalid'));
                    $existingUser->setService($normalizedService !== '' ? $normalizedService : null);
                    $existingUser->setDepartement($normalizedDepartement !== '' ? $normalizedDepartement : null);
                    $existingUser->setAgence($normalizedAgence !== '' ? $normalizedAgence : null);
                    $existingUser->setTelephone($this->nullify(trim((string) ($row['telephone'] ?? ''))));
                    $existingUser->setNumeroCourt($this->nullify(trim((string) ($row['numero_court'] ?? ''))));
                    $existingUser->setProfileType($profileType);
                    $existingUser->setRoles([$this->resolveRoleForProfileType($profileType)]);
                    $updated++;
                    continue;
                }

                $passwordOwner = new Utilisateur();
                $passwordOwner->setEmail($email !== '' ? $email : ('import-' . $userId . '@local.invalid'));
                $hashedPassword = $this->passwordHasher->hashPassword($passwordOwner, $this->generateTemporaryPassword());

                $this->connection->insert('utilisateur', [
                    'id' => $userId,
                    'nom' => trim((string) ($row['nom'] ?? '')),
                    'prenom' => trim((string) ($row['prenom'] ?? '')),
                    'email' => $email !== '' ? $email : ('import-' . $userId . '@local.invalid'),
                    'adresse' => null,
                    'code_postal' => null,
                    'service' => $normalizedService !== '' ? $normalizedService : null,
                    'telephone' => $this->nullify(trim((string) ($row['telephone'] ?? ''))),
                    'numero_court' => $this->nullify(trim((string) ($row['numero_court'] ?? ''))),
                    'mot_de_passe' => $hashedPassword,
                    'photo' => null,
                    'roles' => json_encode([$this->resolveRoleForProfileType($profileType)], JSON_UNESCAPED_UNICODE),
                    'created_at' => $nowSql,
                    'updated_at' => null,
                    'reset_password_token' => null,
                    'reset_password_expires_at' => null,
                    'agence' => $normalizedAgence !== '' ? $normalizedAgence : null,
                    'departement' => $normalizedDepartement !== '' ? $normalizedDepartement : null,
                    'darkmode' => 0,
                    'force_password_change' => 1,
                    'profile_type' => $profileType,
                ]);
                $inserted++;
            }

            $this->em->flush();
            $this->connection->commit();
        } catch (\Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            return [
                'success' => false,
                'message' => 'Import impossible : ' . $exception->getMessage(),
            ];
        }

        $message = 'Import termine : ' . $inserted . ' ajoute(s), ' . $updated . ' mis a jour.';
        if ($emailWarnings !== []) {
            $message .= ' ' . count($emailWarnings) . ' email(s) en doublon ignore(s).';
            $message .= ' Details : ' . implode(' | ', array_slice($emailWarnings, 0, 3));
            if (count($emailWarnings) > 3) {
                $message .= ' | ...';
            }
        }

        return [
            'success' => true,
            'message' => $message,
        ];
    }

    public function getExportRows(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT u.id,
                    u.nom,
                    u.prenom,
                    u.email,
                    u.service,
                    u.departement,
                    u.agence,
                    u.telephone,
                    u.numero_court,
                    u.profile_type
             FROM utilisateur u
             ORDER BY u.nom ASC, u.prenom ASC, u.id ASC'
        );
    }

    /**
     * @return array<int, string>
     */
    public function getAvailableProfileTypes(): array
    {
        $profiles = [];
        foreach (self::DEFAULT_PROFILE_TYPES as $profileType) {
            $profiles[$profileType] = $profileType;
        }

        foreach ($this->utilisateurRepository->findDistinctProfileTypes() as $profileType) {
            $normalized = trim((string) $profileType);
            if ($normalized !== '') {
                $profiles[$normalized] = $normalized;
            }
        }

        return array_values($profiles);
    }

    private function resolveRoleForProfileType(string $profileType): string
    {
        $profileType = trim($profileType);

        return self::PROFILE_ROLE_MAP[$profileType] ?? 'ROLE_EMPLOYE';
    }

    private function nullify(string $value): ?string
    {
        return $value !== '' ? $value : null;
    }

    private function formatUserDisplayName(Utilisateur $user): string
    {
        $displayName = trim((string) $user->getPrenom() . ' ' . (string) $user->getNom());

        return $displayName !== '' ? $displayName : ('Utilisateur #' . (int) ($user->getId() ?? 0));
    }

    private function generateTemporaryPassword(int $length = 12): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
        $max = strlen($alphabet) - 1;
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= $alphabet[random_int(0, $max)];
        }

        return $result;
    }

    private function buildServiceBadgeStyle(string $backgroundColor): string
    {
        $color = $this->normalizeHexColor($backgroundColor);
        if ($color === '') {
            return 'background: var(--bg-tertiary); color: var(--text-secondary); border: 1px solid var(--border);';
        }

        return 'background: ' . $color . '; color: ' . $this->resolveServiceTextColor($color) . '; border: 1px solid ' . $color . ';';
    }

    private function resolveServiceTextColor(string $backgroundColor): string
    {
        if (!preg_match('/^#[0-9A-F]{6}$/', $backgroundColor)) {
            return 'var(--text-secondary)';
        }

        $red = hexdec(substr($backgroundColor, 1, 2));
        $green = hexdec(substr($backgroundColor, 3, 2));
        $blue = hexdec(substr($backgroundColor, 5, 2));
        $luminance = ((0.299 * $red) + (0.587 * $green) + (0.114 * $blue)) / 255;

        return $luminance > 0.62 ? '#1F2937' : '#FFFFFF';
    }

    private function normalizeHexColor(string $value): string
    {
        $color = strtoupper(trim($value));

        return preg_match('/^#[0-9A-F]{6}$/', $color) ? $color : '';
    }

    /**
     * @return array<string, string>
     */
    private function importHeadersMap(): array
    {
        return [
            'id' => 'id',
            'identifiant' => 'id',
            'nom' => 'nom',
            'prenom' => 'prenom',
            'email' => 'email',
            'service' => 'service',
            'departement' => 'departement',
            'agence' => 'agence',
            'n_telephone' => 'telephone',
            'telephone' => 'telephone',
            'numero_telephone' => 'telephone',
            'numero_court' => 'numero_court',
            'n_court' => 'numero_court',
            'type_de_profil' => 'profile_type',
            'profil' => 'profile_type',
            'profile_type' => 'profile_type',
        ];
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $header = trim(mb_strtolower($header));
        $header = preg_replace('/[^a-z0-9]+/u', '_', $header) ?? '';

        return trim($header, '_');
    }

    private function detectCsvDelimiter(string $line): string
    {
        $candidates = [';' => 0, ',' => 0, "\t" => 0];
        foreach (array_keys($candidates) as $delimiter) {
            $candidates[$delimiter] = substr_count($line, $delimiter);
        }

        arsort($candidates);
        $delimiter = (string) array_key_first($candidates);

        return ($candidates[$delimiter] ?? 0) > 0 ? $delimiter : ';';
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function parseCsvRows(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false || $content === '') {
            return [];
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $rows = [];
        $delimiter = ';';

        foreach ($lines as $index => $line) {
            if ($line === '') {
                continue;
            }

            if ($index === 0) {
                $delimiter = $this->detectCsvDelimiter($line);
            }

            $data = str_getcsv($line, $delimiter);
            if (count($data) <= 1 && $delimiter !== ';') {
                $fallback = str_getcsv($line, ';');
                if (count($fallback) > count($data)) {
                    $data = $fallback;
                }
            }

            if (count($data) <= 1 && $delimiter !== ',') {
                $fallback = str_getcsv($line, ',');
                if (count($fallback) > count($data)) {
                    $data = $fallback;
                }
            }

            if (count($data) <= 1 && $delimiter !== "\t") {
                $fallback = str_getcsv($line, "\t");
                if (count($fallback) > count($data)) {
                    $data = $fallback;
                }
            }

            $rows[] = array_map(static fn ($value): string => trim((string) $value), $data);
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function parseXlsRows(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false || $content === '') {
            return [];
        }

        $rows = [];
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($content);
        libxml_clear_errors();

        if ($loaded) {
            $xpath = new \DOMXPath($document);
            $trNodes = $xpath->query('//tr');
            if ($trNodes !== false) {
                foreach ($trNodes as $trNode) {
                    $line = [];
                    foreach ($trNode->childNodes as $cell) {
                        if (!($cell instanceof \DOMElement)) {
                            continue;
                        }

                        $tagName = strtolower($cell->tagName);
                        if ($tagName !== 'td' && $tagName !== 'th') {
                            continue;
                        }

                        $line[] = trim(html_entity_decode($cell->textContent ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    }

                    if ($line !== []) {
                        $rows[] = $line;
                    }
                }
            }
        }

        if ($rows !== []) {
            return $rows;
        }

        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $rows[] = array_map('trim', explode("\t", $line));
        }

        return $rows;
    }

    private function excelColumnIndexFromReference(string $cellReference): int
    {
        if (!preg_match('/^[A-Z]+/i', $cellReference, $matches)) {
            return 0;
        }

        $letters = strtoupper((string) $matches[0]);
        $index = 0;
        $length = strlen($letters);

        for ($i = 0; $i < $length; $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return max(0, $index - 1);
    }

    /**
     * @return array<int, string>
     */
    private function parseXlsxSharedStrings(\ZipArchive $zipArchive): array
    {
        $xml = $zipArchive->getFromName('xl/sharedStrings.xml');
        if (!is_string($xml) || $xml === '') {
            return [];
        }

        $document = @simplexml_load_string($xml);
        if ($document === false) {
            return [];
        }

        $document->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $nodes = $document->xpath('//main:si');
        if (!is_array($nodes)) {
            return [];
        }

        $sharedStrings = [];
        foreach ($nodes as $node) {
            $texts = $node->xpath('.//main:t');
            if (!is_array($texts) || $texts === []) {
                $sharedStrings[] = '';
                continue;
            }

            $value = '';
            foreach ($texts as $textNode) {
                $value .= (string) $textNode;
            }

            $sharedStrings[] = trim($value);
        }

        return $sharedStrings;
    }

    private function resolveFirstWorksheetPath(\ZipArchive $zipArchive): ?string
    {
        $workbookXml = $zipArchive->getFromName('xl/workbook.xml');
        $relsXml = $zipArchive->getFromName('xl/_rels/workbook.xml.rels');
        if (!is_string($workbookXml) || !is_string($relsXml) || $workbookXml === '' || $relsXml === '') {
            return $zipArchive->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
        }

        $workbook = @simplexml_load_string($workbookXml);
        $rels = @simplexml_load_string($relsXml);
        if ($workbook === false || $rels === false) {
            return $zipArchive->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
        }

        $workbook->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $rels->registerXPathNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');

        $sheetNodes = $workbook->xpath('//main:sheets/main:sheet');
        $relationshipNodes = $rels->xpath('//rel:Relationship');
        if (!is_array($sheetNodes) || $sheetNodes === [] || !is_array($relationshipNodes)) {
            return $zipArchive->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
        }

        $relationshipMap = [];
        foreach ($relationshipNodes as $relationshipNode) {
            $attributes = $relationshipNode->attributes();
            $id = (string) ($attributes['Id'] ?? '');
            $target = (string) ($attributes['Target'] ?? '');
            if ($id !== '' && $target !== '') {
                $relationshipMap[$id] = 'xl/' . ltrim($target, '/');
            }
        }

        $sheetAttributes = $sheetNodes[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $relationshipId = (string) ($sheetAttributes['id'] ?? '');
        if ($relationshipId !== '' && isset($relationshipMap[$relationshipId])) {
            return $relationshipMap[$relationshipId];
        }

        return $zipArchive->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function parseXlsxRows(string $path): array
    {
        $zipArchive = new \ZipArchive();
        if ($zipArchive->open($path) !== true) {
            return [];
        }

        $worksheetPath = $this->resolveFirstWorksheetPath($zipArchive);
        if ($worksheetPath === null) {
            $zipArchive->close();

            return [];
        }

        $worksheetXml = $zipArchive->getFromName($worksheetPath);
        $sharedStrings = $this->parseXlsxSharedStrings($zipArchive);
        $zipArchive->close();

        if (!is_string($worksheetXml) || $worksheetXml === '') {
            return [];
        }

        $worksheet = @simplexml_load_string($worksheetXml);
        if ($worksheet === false) {
            return [];
        }

        $worksheet->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rowNodes = $worksheet->xpath('//main:sheetData/main:row');
        if (!is_array($rowNodes)) {
            return [];
        }

        $rows = [];
        foreach ($rowNodes as $rowNode) {
            $line = [];
            $cellNodes = $rowNode->xpath('./main:c');
            if (!is_array($cellNodes)) {
                continue;
            }

            foreach ($cellNodes as $cellNode) {
                $attributes = $cellNode->attributes();
                $reference = (string) ($attributes['r'] ?? '');
                $type = (string) ($attributes['t'] ?? '');
                $index = $this->excelColumnIndexFromReference($reference);

                while (count($line) < $index) {
                    $line[] = '';
                }

                $value = '';
                if ($type === 'inlineStr') {
                    $textNodes = $cellNode->xpath('./main:is/main:t');
                    if (is_array($textNodes)) {
                        foreach ($textNodes as $textNode) {
                            $value .= (string) $textNode;
                        }
                    }
                } else {
                    $rawValue = (string) ($cellNode->v ?? '');
                    if ($type === 's') {
                        $sharedIndex = (int) $rawValue;
                        $value = (string) ($sharedStrings[$sharedIndex] ?? '');
                    } else {
                        $value = $rawValue;
                    }
                }

                $line[$index] = trim($value);
            }

            if ($line !== []) {
                $rows[] = $line;
            }
        }

        return $rows;
    }

    /**
     * @param array<int, array<int, string>> $rows
     * @return array<int, array<string, string>>
     */
    private function rowsToUsers(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $headerLine = array_shift($rows);
        $map = $this->importHeadersMap();
        $normalizedHeaders = [];

        foreach ($headerLine as $headerName) {
            $key = $this->normalizeHeader((string) $headerName);
            $normalizedHeaders[] = $map[$key] ?? null;
        }

        $users = [];
        foreach ($rows as $line) {
            $entry = [];
            foreach ($normalizedHeaders as $index => $targetKey) {
                if ($targetKey === null) {
                    continue;
                }

                $entry[$targetKey] = trim((string) ($line[$index] ?? ''));
            }

            if (trim((string) ($entry['id'] ?? '')) === '') {
                continue;
            }

            $users[] = $entry;
        }

        return $users;
    }
}
