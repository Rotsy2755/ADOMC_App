<?php

namespace App\Repository;

use App\Entity\ShareLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ShareLink> */
class ShareLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShareLink::class);
    }

    public function findByTokenHash(string $tokenHash): ?ShareLink
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }
}
