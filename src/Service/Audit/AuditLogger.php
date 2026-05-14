<?php

declare(strict_types=1);

namespace App\Service\Audit;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/** Append-only audit logger (one line per mutating operation). */
final class AuditLogger
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string,mixed> $context
     */
    public function log(string $action, ?string $entityType = null, ?int $entityId = null, array $context = []): void
    {
        $entry = new AuditLog();
        $entry->setAction($action);
        $entry->setEntityType($entityType);
        $entry->setEntityId($entityId);
        $entry->setContext($context);

        $user = $this->security->getUser();
        if ($user instanceof User) {
            $entry->setUserEmail($user->getEmail());
        }

        $req = $this->requestStack->getMainRequest();
        if ($req !== null) {
            $entry->setIpAddress($req->getClientIp());
        }

        $this->em->persist($entry);
        $this->em->flush();
    }
}
