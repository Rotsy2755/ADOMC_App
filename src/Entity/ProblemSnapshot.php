<?php

namespace App\Entity;

use App\Repository\ProblemSnapshotRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProblemSnapshotRepository::class)]
#[ORM\Table(name: 'problem_snapshots')]
class ProblemSnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Full JSON dump of the problem and its items */
    #[ORM\Column(type: 'json')]
    private array $payload = [];

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(inversedBy: 'snapshots')]
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
    public function getPayload(): array { return $this->payload; }
    public function setPayload(array $v): self { $this->payload = $v; return $this; }
    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $v): self { $this->reason = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getProblem(): ?Problem { return $this->problem; }
    public function setProblem(?Problem $p): self { $this->problem = $p; return $this; }
    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(?User $u): self { $this->author = $u; return $this; }
}
