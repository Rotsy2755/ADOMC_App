<?php

namespace App\Entity;

use App\Repository\SensitivityAnalysisRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SensitivityAnalysisRepository::class)]
#[ORM\Table(name: 'sensitivity_analyses')]
class SensitivityAnalysis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Index k of criterion being varied (0-based) */
    #[ORM\Column]
    private int $criterion = 0;

    #[ORM\Column(type: 'float')]
    private float $minValue = 0.0;

    #[ORM\Column(type: 'float')]
    private float $maxValue = 1.0;

    #[ORM\Column(type: 'float')]
    private float $step = 0.05;

    #[ORM\Column]
    private int $topK = 3;

    #[ORM\Column(type: 'float')]
    private float $robustnessThreshold = 0.8;

    /** Results: [{lambda_k, rankings: [{solution_id, rank, score}]}] */
    #[ORM\Column(type: 'json')]
    private array $configurations = [];

    /** Robust solutions: [{solution_id, robustness, dominant_interval: [min,max]}] */
    #[ORM\Column(type: 'json')]
    private array $robustSolutions = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $summary = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?McdmResult $mcdmResult = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getCriterion(): int { return $this->criterion; }
    public function setCriterion(int $v): self { $this->criterion = $v; return $this; }
    public function getMinValue(): float { return $this->minValue; }
    public function setMinValue(float $v): self { $this->minValue = $v; return $this; }
    public function getMaxValue(): float { return $this->maxValue; }
    public function setMaxValue(float $v): self { $this->maxValue = $v; return $this; }
    public function getStep(): float { return $this->step; }
    public function setStep(float $v): self { $this->step = $v; return $this; }
    public function getTopK(): int { return $this->topK; }
    public function setTopK(int $v): self { $this->topK = $v; return $this; }
    public function getRobustnessThreshold(): float { return $this->robustnessThreshold; }
    public function setRobustnessThreshold(float $v): self { $this->robustnessThreshold = $v; return $this; }
    public function getConfigurations(): array { return $this->configurations; }
    public function setConfigurations(array $v): self { $this->configurations = $v; return $this; }
    public function getRobustSolutions(): array { return $this->robustSolutions; }
    public function setRobustSolutions(array $v): self { $this->robustSolutions = $v; return $this; }
    public function getSummary(): ?string { return $this->summary; }
    public function setSummary(?string $v): self { $this->summary = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getMcdmResult(): ?McdmResult { return $this->mcdmResult; }
    public function setMcdmResult(?McdmResult $r): self { $this->mcdmResult = $r; return $this; }
}
