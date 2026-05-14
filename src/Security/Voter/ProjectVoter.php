<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Project;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * A user can only access their own projects.
 * Admins bypass via role check in the security layer (access_control).
 */
final class ProjectVoter extends Voter
{
    public const VIEW = 'PROJECT_VIEW';
    public const EDIT = 'PROJECT_EDIT';
    public const DELETE = 'PROJECT_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)
            && $subject instanceof Project;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$subject instanceof Project) {
            return false;
        }
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }
        return $subject->getUser()?->getId() === $user->getId();
    }
}
