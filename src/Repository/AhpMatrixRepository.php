<?php

namespace App\Repository;

use App\Entity\AhpMatrix;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AhpMatrix> */
class AhpMatrixRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AhpMatrix::class);
    }
}
