<?php

namespace App\Entity;

use App\Repository\PageIconRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PageIconRepository::class)]
class PageIcon
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $pagePath = null;

    #[ORM\Column(length: 255)]
    private ?string $icon = null;

    #[ORM\Column(length: 100)]
    private ?string $iconLibrary = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $color = null;

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

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    public function getIconLibrary(): ?string
    {
        return $this->iconLibrary;
    }

    public function setIconLibrary(string $iconLibrary): static
    {
        $this->iconLibrary = $iconLibrary;
        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;
        return $this;
    }
}
