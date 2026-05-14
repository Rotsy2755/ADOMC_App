<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\McdmResultRepository;
use App\Repository\ProblemRepository;
use App\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function index(
        ProjectRepository $projects,
        ProblemRepository $problems,
        McdmResultRepository $mcdm,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $userProjects = $projects->findBy(['user' => $user], ['updatedAt' => 'DESC']);
        $userProblems = $problems->findByUser($user, 10);
        $recentMcdm   = $mcdm->findRecentForUser($user, 5);

        $counts = [
            'projects'       => count($userProjects),
            'problems'       => count($userProblems),
            'paretoReady'    => $problems->countReadyForUser($user),
            'mcdmRuns'       => $mcdm->countForUser($user),
        ];

        return $this->render('dashboard/index.html.twig', [
            'projects' => array_slice($userProjects, 0, 5),
            'problems' => $userProblems,
            'mcdm'     => $recentMcdm,
            'counts'   => $counts,
        ]);
    }
}
