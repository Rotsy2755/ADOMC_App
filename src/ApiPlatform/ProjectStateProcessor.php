<?php

declare(strict_types=1);

namespace App\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Automatically assigns the authenticated user as the owner of a new Project
 * created through the REST API, and persists the entity via Doctrine.
 */
final class ProjectStateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $inner,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Project && $data->getUser() === null) {
            $user = $this->security->getUser();
            if ($user instanceof User) {
                $data->setUser($user);
            }
        }
        return $this->inner->process($data, $operation, $uriVariables, $context);
    }
}
