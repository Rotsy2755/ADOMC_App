<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Problem;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** A problem is reachable only through a project owned by the current user. */
final class ProblemVoter extends Voter
{
    public const VIEW = 'PROBLEM_VIEW';
    public const EDIT = 'PROBLEM_EDIT';
    public const DELETE = 'PROBLEM_DELETE';
    public const COMPUTE = 'PROBLEM_COMPUTE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::COMPUTE], true)
            && $subject instanceof Problem;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$subject instanceof Problem) {
            return false;
        }
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }
        return $subject->getProject()?->getUser()?->getId() === $user->getId();
    }
}
