<?php

namespace App\Entity;

use App\Repository\UserPagePreferenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserPagePreferenceRepository::class)]
#[ORM\Table(name: 'user_page_preference')]
#[ORM\UniqueConstraint(name: 'uniq_user_page_pref_user_page', columns: ['utilisateur_id', 'page_key'])]
#[ORM\HasLifecycleCallbacks]
class UserPagePreference
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(length: 100)]
    private string $pageKey = '';

    #[ORM\Column(type: Types::JSON)]
    private array $preferencePayload = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $now = new \DateTime();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getPageKey(): string
    {
        return $this->pageKey;
    }

    public function setPageKey(string $pageKey): static
    {
        $this->pageKey = trim($pageKey);

        return $this;
    }

    public function getPreferencePayload(): array
    {
        return $this->preferencePayload;
    }

    public function setPreferencePayload(array $preferencePayload): static
    {
        $this->preferencePayload = $preferencePayload;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }
}
