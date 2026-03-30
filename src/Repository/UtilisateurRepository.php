<?php

namespace App\Repository;

use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Utilisateur>
 *
 * @method Utilisateur|null find($id, $lockMode = null, $lockVersion = null)
 * @method Utilisateur|null findOneBy(array $criteria, array $orderBy = null)
 * @method Utilisateur[]    findAll()
 * @method Utilisateur[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UtilisateurRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Utilisateur) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setMotDePasse($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findAllSorted(): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.prenom', 'ASC')
            ->addOrderBy('u.nom', 'ASC')
            ->addOrderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAllSortedIdentityRows(): array
    {
        return $this->createQueryBuilder('u')
            ->select('u.id AS id', 'u.prenom AS prenom', 'u.nom AS nom', 'u.profileType AS profile_type')
            ->orderBy('u.prenom', 'ASC')
            ->addOrderBy('u.nom', 'ASC')
            ->addOrderBy('u.email', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function findDirectoryRows(): array
    {
        return $this->createQueryBuilder('u')
            ->select(
                'u.prenom AS prenom',
                'u.nom AS nom',
                'u.email AS email',
                'u.service AS service',
                'u.departement AS departement',
                'u.agence AS agence',
                'u.telephone AS telephone',
                'u.numeroCourt AS numeroCourt',
                'u.photo AS photo'
            )
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenom', 'ASC')
            ->addOrderBy('u.email', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function findDistinctProfileTypes(): array
    {
        $rows = $this->createQueryBuilder('u')
            ->select('DISTINCT u.profileType AS profileType')
            ->where('u.profileType IS NOT NULL')
            ->andWhere('u.profileType <> :empty')
            ->setParameter('empty', '')
            ->orderBy('u.profileType', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $profiles = [];
        foreach ($rows as $row) {
            $profileType = trim((string) ($row['profileType'] ?? ''));
            if ($profileType !== '') {
                $profiles[$profileType] = $profileType;
            }
        }

        return array_values($profiles);
    }
}
