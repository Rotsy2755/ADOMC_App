<?php

namespace App\Repository;

use App\Entity\McdmResult;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<McdmResult> */
class McdmResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, McdmResult::class);
    }

    /** @return McdmResult[] */
    public function findChosenByUser(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->join('m.problem', 'pb')
            ->join('pb.project', 'pr')
            ->where('pr.user = :u')->setParameter('u', $user)
            ->andWhere('m.chosen = :t')->setParameter('t', true)
            ->getQuery()->getResult();
    }

    /** @return McdmResult[] */
    public function findRecentForUser(User $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('m')
            ->join('m.problem', 'pb')
            ->join('pb.project', 'pr')
            ->where('pr.user = :u')->setParameter('u', $user)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    public function countForUser(User $user): int
    {
        return (int)$this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->join('m.problem', 'pb')
            ->join('pb.project', 'pr')
            ->where('pr.user = :u')->setParameter('u', $user)
            ->getQuery()->getSingleScalarResult();
    }
}
