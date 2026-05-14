<?php

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_logs')]
#[ORM\Index(columns: ['action'], name: 'idx_audit_action')]
#[ORM\Index(columns: ['created_at'], name: 'idx_audit_created_at')]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $action = '';

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $entityType = null;

    #[ORM\Column(nullable: true)]
    private ?int $entityId = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $userEmail = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $context = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getAction(): string { return $this->action; }
    public function setAction(string $v): self { $this->action = $v; return $this; }
    public function getEntityType(): ?string { return $this->entityType; }
    public function setEntityType(?string $v): self { $this->entityType = $v; return $this; }
    public function getEntityId(): ?int { return $this->entityId; }
    public function setEntityId(?int $v): self { $this->entityId = $v; return $this; }
    public function getUserEmail(): ?string { return $this->userEmail; }
    public function setUserEmail(?string $v): self { $this->userEmail = $v; return $this; }
    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function setIpAddress(?string $v): self { $this->ipAddress = $v; return $this; }
    public function getContext(): ?array { return $this->context; }
    public function setContext(?array $v): self { $this->context = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
