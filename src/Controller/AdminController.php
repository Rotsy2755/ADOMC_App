<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\AuditLogRepository;
use App\Repository\McdmResultRepository;
use App\Repository\ProblemRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Service\Audit\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin_dashboard', methods: ['GET'])]
    public function dashboard(
        UserRepository $users,
        ProjectRepository $projects,
        ProblemRepository $problems,
        McdmResultRepository $results,
        AuditLogRepository $audit,
    ): Response {
        $recentLogs = $audit->search(null, null, null, null, 20);
        return $this->render('admin/dashboard.html.twig', [
            'stats' => [
                'users'    => count($users->findAll()),
                'projects' => count($projects->findAll()),
                'problems' => count($problems->findAll()),
                'results'  => count($results->findAll()),
            ],
            'recentLogs' => $recentLogs,
        ]);
    }

    #[Route('/users', name: 'app_admin_users', methods: ['GET'])]
    public function users(UserRepository $users): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $users->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/users/{id}/toggle', name: 'app_admin_user_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggle(
        Request $request,
        User $user,
        EntityManagerInterface $em,
        AuditLogger $audit,
    ): Response {
        if (!$this->isCsrfTokenValid('admin_toggle_'.$user->getId(), (string)$request->request->get('_csrf_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $user->setEnabled(!$user->isEnabled());
        $em->flush();
        $audit->log('admin.user.toggle', 'User', $user->getId(), ['enabled' => $user->isEnabled()]);
        $this->addFlash('success', sprintf('Utilisateur %s.', $user->isEnabled() ? 'activé' : 'désactivé'));
        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/users/{id}/promote', name: 'app_admin_user_promote', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function promote(
        Request $request,
        User $user,
        EntityManagerInterface $em,
        AuditLogger $audit,
    ): Response {
        if (!$this->isCsrfTokenValid('admin_promote_'.$user->getId(), (string)$request->request->get('_csrf_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $roles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $roles, true)) {
            $roles = array_values(array_diff($roles, ['ROLE_ADMIN']));
            $msg = 'Rôle administrateur retiré.';
        } else {
            $roles[] = 'ROLE_ADMIN';
            $msg = 'Rôle administrateur attribué.';
        }
        $user->setRoles(array_values(array_unique($roles)));
        $em->flush();
        $audit->log('admin.user.roles', 'User', $user->getId(), ['roles' => $user->getRoles()]);
        $this->addFlash('success', $msg);
        return $this->redirectToRoute('app_admin_users');
    }
}
