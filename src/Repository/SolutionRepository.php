<?php

namespace App\Repository;

use App\Entity\Problem;
use App\Entity\Solution;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Solution> */
class SolutionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Solution::class);
    }

    /** @return Solution[] */
    public function findParetoByProblem(Problem $problem): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.problem = :p')->setParameter('p', $problem)
            ->andWhere('s.isPareto = :t')->setParameter('t', true)
            ->orderBy('s.id', 'ASC')
            ->getQuery()->getResult();
    }

    public function deleteAllForProblem(Problem $problem): void
    {
        $this->createQueryBuilder('s')
            ->delete()
            ->where('s.problem = :p')->setParameter('p', $problem)
            ->getQuery()->execute();
    }
}
