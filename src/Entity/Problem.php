<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\ProblemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProblemRepository::class)]
#[ORM\Table(name: 'problems')]
#[ApiResource(
    shortName: 'Problem',
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('PROBLEM_VIEW', object)"),
        new Post(security: "is_granted('ROLE_USER')"),
        new Patch(security: "is_granted('PROBLEM_EDIT', object)"),
        new Delete(security: "is_granted('PROBLEM_DELETE', object)"),
    ],
    normalizationContext: ['groups' => ['problem:read']],
    denormalizationContext: ['groups' => ['problem:write']],
)]
class Problem
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_COMPUTING = 'computing';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    #[Groups(['problem:read', 'problem:write'])]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['problem:read', 'problem:write'])]
    private ?string $description = null;

    /** Capacity W of the knapsack */
    #[ORM\Column(type: 'float')]
    #[Assert\Positive(message: 'W must be strictly greater than 0.')]
    #[Groups(['problem:read', 'problem:write'])]
    private float $capacity = 0.0;

    /** Number of objectives p (2 or 3 typically) */
    #[ORM\Column]
    #[Assert\Range(min: 2, max: 5)]
    #[Groups(['problem:read', 'problem:write'])]
    private int $objectiveCount = 2;

    /** List of objective names: ["f1"=>"Beneficiaries", "f2"=>"Urgency", ...] */
    #[ORM\Column(type: 'json')]
    #[Groups(['problem:read', 'problem:write'])]
    private array $objectives = [];

    #[ORM\Column(length: 32)]
    #[Groups(['problem:read'])]
    private string $status = self::STATUS_DRAFT;

    /** Algorithm used: 'epsilon', 'nsga2' */
    #[ORM\Column(length: 32, nullable: true)]
    #[Groups(['problem:read', 'problem:write'])]
    private ?string $algorithm = null;

    #[ORM\Column]
    #[Groups(['problem:read', 'problem:write'])]
    private bool $fuzzyEnabled = false;

    /** Integer progress 0..100 for async computation */
    #[ORM\Column]
    #[Groups(['problem:read'])]
    private int $progress = 0;

    #[ORM\Column]
    #[Groups(['problem:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['problem:read'])]
    private \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne(inversedBy: 'problems')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    /** @var Collection<int, Item> */
    #[ORM\OneToMany(mappedBy: 'problem', targetEntity: Item::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    /** @var Collection<int, Solution> */
    #[ORM\OneToMany(mappedBy: 'problem', targetEntity: Solution::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $solutions;

    /** @var Collection<int, AhpMatrix> */
    #[ORM\OneToMany(mappedBy: 'problem', targetEntity: AhpMatrix::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $ahpMatrices;

    /** @var Collection<int, McdmResult> */
    #[ORM\OneToMany(mappedBy: 'problem', targetEntity: McdmResult::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $mcdmResults;

    /** @var Collection<int, ProblemSnapshot> */
    #[ORM\OneToMany(mappedBy: 'problem', targetEntity: ProblemSnapshot::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $snapshots;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
        $this->solutions = new ArrayCollection();
        $this->ahpMatrices = new ArrayCollection();
        $this->mcdmResults = new ArrayCollection();
        $this->snapshots = new ArrayCollection();
    }

    #[Groups(['problem:read'])]
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): self { $this->name = $v; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): self { $this->description = $v; return $this; }
    public function getCapacity(): float { return $this->capacity; }
    public function setCapacity(float $v): self { $this->capacity = $v; return $this; }
    public function getObjectiveCount(): int { return $this->objectiveCount; }
    public function setObjectiveCount(int $v): self { $this->objectiveCount = $v; return $this; }
    public function getObjectives(): array { return $this->objectives; }
    public function setObjectives(array $v): self { $this->objectives = $v; return $this; }

    /** Objective labels as a 0-indexed list (safe for numeric Twig access). */
    public function getObjectiveLabels(): array { return array_values($this->objectives); }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): self { $this->status = $v; return $this; }
    public function getAlgorithm(): ?string { return $this->algorithm; }
    public function setAlgorithm(?string $v): self { $this->algorithm = $v; return $this; }
    public function isFuzzyEnabled(): bool { return $this->fuzzyEnabled; }
    public function setFuzzyEnabled(bool $v): self { $this->fuzzyEnabled = $v; return $this; }
    public function getProgress(): int { return $this->progress; }
    public function setProgress(int $v): self { $this->progress = max(0, min(100, $v)); return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): self { $this->updatedAt = new \DateTimeImmutable(); return $this; }
    public function getProject(): ?Project { return $this->project; }
    public function setProject(?Project $p): self { $this->project = $p; return $this; }

    /** @return Collection<int, Item> */
    public function getItems(): Collection { return $this->items; }
    public function addItem(Item $i): self { if (!$this->items->contains($i)) { $this->items->add($i); $i->setProblem($this); } return $this; }
    public function removeItem(Item $i): self { if ($this->items->removeElement($i) && $i->getProblem() === $this) { $i->setProblem(null); } return $this; }

    /** @return Collection<int, Solution> */
    public function getSolutions(): Collection { return $this->solutions; }

    /** Number of Pareto-optimal (non-dominated) solutions currently persisted. */
    public function getParetoCount(): int
    {
        $n = 0;
        foreach ($this->solutions as $s) {
            if ($s->isPareto()) { $n++; }
        }
        return $n;
    }

    /** @return Collection<int, AhpMatrix> */
    public function getAhpMatrices(): Collection { return $this->ahpMatrices; }

    /** @return Collection<int, McdmResult> */
    public function getMcdmResults(): Collection { return $this->mcdmResults; }

    /** @return Collection<int, ProblemSnapshot> */
    public function getSnapshots(): Collection { return $this->snapshots; }
}
