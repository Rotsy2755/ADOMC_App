<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private string $email = '';

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $fullName = '';

    #[ORM\Column]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    /** @var Collection<int, Project> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Project::class, orphanRemoval: true)]
    private Collection $projects;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->projects = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    public function getFullName(): string { return $this->fullName; }
    public function setFullName(string $v): self { $this->fullName = $v; return $this; }

    public function getUserIdentifier(): string { return $this->email; }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_values(array_unique($roles));
    }
    public function setRoles(array $roles): self { $this->roles = $roles; return $this; }

    public function getPassword(): string { return $this->password; }
    public function setPassword(string $p): self { $this->password = $p; return $this; }

    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $v): self { $this->enabled = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getLastLoginAt(): ?\DateTimeImmutable { return $this->lastLoginAt; }
    public function setLastLoginAt(?\DateTimeImmutable $d): self { $this->lastLoginAt = $d; return $this; }

    public function eraseCredentials(): void {}

    /** @return Collection<int, Project> */
    public function getProjects(): Collection { return $this->projects; }
}
