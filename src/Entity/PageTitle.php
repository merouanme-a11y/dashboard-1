<?php

namespace App\Entity;

use App\Repository\PageTitleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PageTitleRepository::class)]
class PageTitle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $pagePath = null;

    #[ORM\Column(length: 255)]
    private ?string $displayName = null;

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

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): static
    {
        $this->displayName = $displayName;
        return $this;
    }
}
