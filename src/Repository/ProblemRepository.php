<?php

namespace App\Repository;

use App\Entity\Problem;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Problem>
 */
class ProblemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Problem::class);
    }

    /** @return Problem[] */
    public function findByUser(User $user, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('pb')
            ->join('pb.project', 'p')
            ->where('p.user = :u')->setParameter('u', $user)
            ->orderBy('pb.updatedAt', 'DESC');
        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }
        return $qb->getQuery()->getResult();
    }

    public function countReadyForUser(User $user): int
    {
        return (int)$this->createQueryBuilder('pb')
            ->select('COUNT(pb.id)')
            ->join('pb.project', 'p')
            ->where('p.user = :u')->setParameter('u', $user)
            ->andWhere('pb.status = :s')->setParameter('s', Problem::STATUS_DONE)
            ->getQuery()->getSingleScalarResult();
    }
}
