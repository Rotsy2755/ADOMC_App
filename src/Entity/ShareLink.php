<?php

namespace App\Entity;

use App\Repository\ShareLinkRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShareLinkRepository::class)]
#[ORM\Table(name: 'share_links')]
#[ORM\UniqueConstraint(name: 'uniq_share_token', columns: ['token_hash'])]
class ShareLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** SHA-256 hash of the 32-byte random token (hex, 64 chars) */
    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column]
    private bool $revoked = false;

    #[ORM\Column]
    private int $views = 0;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Problem $problem = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $owner = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getTokenHash(): string { return $this->tokenHash; }
    public function setTokenHash(string $v): self { $this->tokenHash = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getExpiresAt(): ?\DateTimeImmutable { return $this->expiresAt; }
    public function setExpiresAt(?\DateTimeImmutable $v): self { $this->expiresAt = $v; return $this; }
    public function isRevoked(): bool { return $this->revoked; }
    public function setRevoked(bool $v): self { $this->revoked = $v; return $this; }
    public function getViews(): int { return $this->views; }
    public function incrementViews(): self { $this->views++; return $this; }
    public function getProblem(): ?Problem { return $this->problem; }
    public function setProblem(?Problem $p): self { $this->problem = $p; return $this; }
    public function getOwner(): ?User { return $this->owner; }
    public function setOwner(?User $u): self { $this->owner = $u; return $this; }

    public function isActive(): bool
    {
        if ($this->revoked) {
            return false;
        }
        if ($this->expiresAt !== null && $this->expiresAt < new \DateTimeImmutable()) {
            return false;
        }
        return true;
    }
}
