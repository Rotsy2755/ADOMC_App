<?php

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AuditLog> */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /** @return AuditLog[] */
    public function search(?string $action, ?string $userEmail, ?\DateTimeInterface $from, ?\DateTimeInterface $to, int $limit = 200): array
    {
        $qb = $this->createQueryBuilder('a')->orderBy('a.createdAt', 'DESC')->setMaxResults($limit);
        if ($action) { $qb->andWhere('a.action LIKE :act')->setParameter('act', $action.'%'); }
        if ($userEmail) { $qb->andWhere('a.userEmail = :u')->setParameter('u', $userEmail); }
        if ($from) { $qb->andWhere('a.createdAt >= :f')->setParameter('f', $from); }
        if ($to) { $qb->andWhere('a.createdAt <= :t')->setParameter('t', $to); }
        return $qb->getQuery()->getResult();
    }
}
