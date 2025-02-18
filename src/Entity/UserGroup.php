<?php

namespace App\Entity;

use App\Repository\UserGroupRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: UserGroupRepository::class)]
class UserGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api:group'])]
    private ?int $id = null;

    #[ORM\Column(length: 25)]
    #[Groups(['api:group'])]
    private ?string $username = null;

    #[ORM\Column]
    #[Groups(['api:group'])]
    private ?bool $responsable = null;

    #[ORM\ManyToOne(inversedBy: 'userGroups')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Group $groupe = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    #[Groups(['api:group'])]
    public function isUser(): ?bool
    {
        return !$this->responsable;
    }

    public function isResponsable(): ?bool
    {
        return $this->responsable;
    }

    public function setResponsable(bool $responsable): static
    {
        $this->responsable = $responsable;

        return $this;
    }

    public function getGroupe(): ?Group
    {
        return $this->groupe;
    }

    public function setGroupe(?Group $groupe): static
    {
        $this->groupe = $groupe;

        return $this;
    }
}
