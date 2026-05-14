<?php

declare(strict_types=1);

namespace App\ApiPlatform;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\McdmResult;
use App\Entity\Problem;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Restricts API Platform Doctrine queries so a user only ever sees
 * rows belonging to projects they own. Admins bypass the filter.
 */
final class CurrentUserExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function applyToCollection(
        QueryBuilder $qb,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->addWhere($qb, $resourceClass);
    }

    public function applyToItem(
        QueryBuilder $qb,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->addWhere($qb, $resourceClass);
    }

    private function addWhere(QueryBuilder $qb, string $resourceClass): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }
        $rootAlias = $qb->getRootAliases()[0] ?? null;
        if (!$rootAlias) {
            return;
        }

        $param = 'current_user_' . uniqid();
        if ($resourceClass === Project::class) {
            $qb->andWhere(sprintf('%s.user = :%s', $rootAlias, $param))
               ->setParameter($param, $user);
            return;
        }
        if ($resourceClass === Problem::class) {
            $qb->join($rootAlias . '.project', 'cue_project')
               ->andWhere(sprintf('cue_project.user = :%s', $param))
               ->setParameter($param, $user);
            return;
        }
        if ($resourceClass === McdmResult::class) {
            $qb->join($rootAlias . '.problem', 'cue_problem')
               ->join('cue_problem.project', 'cue_project')
               ->andWhere(sprintf('cue_project.user = :%s', $param))
               ->setParameter($param, $user);
        }
    }
}
