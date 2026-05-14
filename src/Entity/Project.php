<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\ApiPlatform\ProjectStateProcessor;
use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'projects')]
#[ApiResource(
    shortName: 'Project',
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('PROJECT_VIEW', object)"),
        new Post(security: "is_granted('ROLE_USER')", processor: ProjectStateProcessor::class),
        new Patch(security: "is_granted('PROJECT_EDIT', object)"),
        new Delete(security: "is_granted('PROJECT_EDIT', object)"),
    ],
    normalizationContext: ['groups' => ['project:read']],
    denormalizationContext: ['groups' => ['project:write']],
)]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    #[Groups(['project:read', 'project:write'])]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 4000)]
    #[Groups(['project:read', 'project:write'])]
    private ?string $description = null;

    #[ORM\Column]
    #[Groups(['project:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['project:read'])]
    private \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne(inversedBy: 'projects')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /** @var Collection<int, Problem> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Problem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $problems;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->problems = new ArrayCollection();
    }

    #[Groups(['project:read'])]
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): self { $this->name = $v; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): self { $this->description = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): self { $this->updatedAt = new \DateTimeImmutable(); return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $u): self { $this->user = $u; return $this; }

    /** @return Collection<int, Problem> */
    public function getProblems(): Collection { return $this->problems; }
}
