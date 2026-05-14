<?php

namespace App\Entity;

use App\Repository\SolutionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SolutionRepository::class)]
#[ORM\Table(name: 'solutions')]
#[ORM\Index(columns: ['problem_id'], name: 'idx_solution_problem')]
class Solution
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Selected item ids: [5, 8, 12, ...] */
    #[ORM\Column(type: 'json')]
    private array $selectedItemIds = [];

    /** Total weight sum w_i */
    #[ORM\Column(type: 'float')]
    private float $totalWeight = 0.0;

    /** Objective values [f1, f2, ..., fp] */
    #[ORM\Column(type: 'json')]
    private array $objectiveValues = [];

    #[ORM\Column]
    private bool $isPareto = true;

    #[ORM\Column(nullable: true)]
    private ?int $dominatedById = null;

    /** 'epsilon' or 'nsga2' */
    #[ORM\Column(length: 32)]
    private string $algorithm = 'epsilon';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(inversedBy: 'solutions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Problem $problem = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getSelectedItemIds(): array { return $this->selectedItemIds; }
    public function setSelectedItemIds(array $v): self { $this->selectedItemIds = $v; return $this; }
    public function getTotalWeight(): float { return $this->totalWeight; }
    public function setTotalWeight(float $v): self { $this->totalWeight = $v; return $this; }
    public function getObjectiveValues(): array { return $this->objectiveValues; }
    public function setObjectiveValues(array $v): self { $this->objectiveValues = $v; return $this; }
    public function isPareto(): bool { return $this->isPareto; }
    public function setIsPareto(bool $v): self { $this->isPareto = $v; return $this; }
    public function getDominatedById(): ?int { return $this->dominatedById; }
    public function setDominatedById(?int $v): self { $this->dominatedById = $v; return $this; }
    public function getAlgorithm(): string { return $this->algorithm; }
    public function setAlgorithm(string $v): self { $this->algorithm = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getProblem(): ?Problem { return $this->problem; }
    public function setProblem(?Problem $p): self { $this->problem = $p; return $this; }
}
