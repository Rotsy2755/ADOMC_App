<?php

namespace App\Repository;

use App\Entity\SensitivityAnalysis;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SensitivityAnalysis> */
class SensitivityAnalysisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SensitivityAnalysis::class);
    }
}
