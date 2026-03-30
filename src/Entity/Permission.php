<?php

namespace App\Entity;

use App\Repository\PermissionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PermissionRepository::class)]
class Permission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $pagePath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $role = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column]
    private bool $isAllowed = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPagePath(): ?string
    {
        return $this->pagePath;
    }

    public function setPagePath(string $pagePath): static
    {
        $this->pagePath = $pagePath;
        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;
        return $this;
    }

    public function isAllowed(): bool
    {
        return $this->isAllowed;
    }

    public function setIsAllowed(bool $isAllowed): static
    {
        $this->isAllowed = $isAllowed;
        return $this;
    }
}
