<?php

namespace App\Entity;

use App\Repository\AhpMatrixRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AhpMatrixRepository::class)]
#[ORM\Table(name: 'ahp_matrices')]
class AhpMatrix
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $label = 'default';

    /** Matrix A (n×n), list of lists */
    #[ORM\Column(type: 'json')]
    private array $matrix = [];

    /** Computed priority vector λ = [λ1..λn] */
    #[ORM\Column(type: 'json')]
    private array $weights = [];

    #[ORM\Column(type: 'float')]
    private float $lambdaMax = 0.0;

    #[ORM\Column(type: 'float')]
    private float $ci = 0.0;

    #[ORM\Column(type: 'float')]
    private float $cr = 0.0;

    #[ORM\Column]
    private bool $consistent = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(inversedBy: 'ahpMatrices')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Problem $problem = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getLabel(): string { return $this->label; }
    public function setLabel(string $v): self { $this->label = $v; return $this; }
    public function getMatrix(): array { return $this->matrix; }
    public function setMatrix(array $v): self { $this->matrix = $v; return $this; }
    public function getWeights(): array { return $this->weights; }
    public function setWeights(array $v): self { $this->weights = $v; return $this; }
    public function getLambdaMax(): float { return $this->lambdaMax; }
    public function setLambdaMax(float $v): self { $this->lambdaMax = $v; return $this; }
    public function getCi(): float { return $this->ci; }
    public function setCi(float $v): self { $this->ci = $v; return $this; }
    public function getCr(): float { return $this->cr; }
    public function setCr(float $v): self { $this->cr = $v; return $this; }
    public function isConsistent(): bool { return $this->consistent; }
    public function setConsistent(bool $v): self { $this->consistent = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getProblem(): ?Problem { return $this->problem; }
    public function setProblem(?Problem $p): self { $this->problem = $p; return $this; }
    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(?User $u): self { $this->author = $u; return $this; }
}
