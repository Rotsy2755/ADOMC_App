<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\McdmResultRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: McdmResultRepository::class)]
#[ORM\Table(name: 'mcdm_results')]
#[ApiResource(
    shortName: 'McdmResult',
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER') and object.getProblem().getProject().getUser() == user"),
    ],
    normalizationContext: ['groups' => ['mcdm:read']],
)]
class McdmResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['mcdm:read'])]
    private ?int $id = null;

    /** 'topsis' | 'vikor' | 'promethee2' | 'electre1' */
    #[ORM\Column(length: 32)]
    #[Groups(['mcdm:read'])]
    private string $method = 'topsis';

    /** 'vector' | 'minmax' | 'zscore' */
    #[ORM\Column(length: 16)]
    #[Groups(['mcdm:read'])]
    private string $normalization = 'vector';

    /** Rankings: [{solution_id, score, rank}] */
    #[ORM\Column(type: 'json')]
    #[Groups(['mcdm:read'])]
    private array $rankings = [];

    /** Method-specific parameters */
    #[ORM\Column(type: 'json')]
    #[Groups(['mcdm:read'])]
    private array $params = [];

    /** Weights used (copy of AHP weights at compute time) */
    #[ORM\Column(type: 'json')]
    #[Groups(['mcdm:read'])]
    private array $weights = [];

    /** Recommended solution id (top-1) */
    #[ORM\Column(nullable: true)]
    #[Groups(['mcdm:read'])]
    private ?int $recommendedSolutionId = null;

    /** Whether the user validated ("choisie") this recommendation */
    #[ORM\Column]
    #[Groups(['mcdm:read'])]
    private bool $chosen = false;

    #[ORM\Column]
    #[Groups(['mcdm:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(inversedBy: 'mcdmResults')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Problem $problem = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?AhpMatrix $ahpMatrix = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getMethod(): string { return $this->method; }
    public function setMethod(string $v): self { $this->method = $v; return $this; }
    public function getNormalization(): string { return $this->normalization; }
    public function setNormalization(string $v): self { $this->normalization = $v; return $this; }
    public function getRankings(): array { return $this->rankings; }
    public function setRankings(array $v): self { $this->rankings = $v; return $this; }
    public function getParams(): array { return $this->params; }
    public function setParams(array $v): self { $this->params = $v; return $this; }
    public function getWeights(): array { return $this->weights; }
    public function setWeights(array $v): self { $this->weights = $v; return $this; }
    public function getRecommendedSolutionId(): ?int { return $this->recommendedSolutionId; }
    public function setRecommendedSolutionId(?int $v): self { $this->recommendedSolutionId = $v; return $this; }
    public function isChosen(): bool { return $this->chosen; }
    public function setChosen(bool $v): self { $this->chosen = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getProblem(): ?Problem { return $this->problem; }
    public function setProblem(?Problem $p): self { $this->problem = $p; return $this; }
    public function getAhpMatrix(): ?AhpMatrix { return $this->ahpMatrix; }
    public function setAhpMatrix(?AhpMatrix $a): self { $this->ahpMatrix = $a; return $this; }
}
