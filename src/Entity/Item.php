<?php

namespace App\Entity;

use App\Repository\ItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ItemRepository::class)]
#[ORM\Table(name: 'items')]
class Item
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private string $name = '';

    #[ORM\Column(type: 'float')]
    #[Assert\Positive(message: 'w_i must be strictly greater than 0.')]
    private float $weight = 0.0;

    /** Objective values [v1, v2, ...] (each > 0) */
    #[ORM\Column(name: '`values`', type: 'json')]
    private array $values = [];

    /** Optional fuzzy triangular values [[l,m,u], ...] per objective */
    #[ORM\Column(name: 'fuzzy_values', type: 'json', nullable: true)]
    private ?array $fuzzyValues = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Problem $problem = null;

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): self { $this->name = $v; return $this; }
    public function getWeight(): float { return $this->weight; }
    public function setWeight(float $v): self { $this->weight = $v; return $this; }
    public function getValues(): array { return $this->values; }
    public function setValues(array $v): self { $this->values = $v; return $this; }
    public function getFuzzyValues(): ?array { return $this->fuzzyValues; }
    public function setFuzzyValues(?array $v): self { $this->fuzzyValues = $v; return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $v): self { $this->position = $v; return $this; }
    public function getProblem(): ?Problem { return $this->problem; }
    public function setProblem(?Problem $p): self { $this->problem = $p; return $this; }
}
