<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Problem;
use App\Repository\ProblemRepository;
use App\Security\Voter\ProblemVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pareto')]
#[IsGranted('ROLE_USER')]
final class ParetoController extends AbstractController
{
    #[Route('', name: 'app_pareto_index', methods: ['GET'])]
    public function index(ProblemRepository $problems): Response
    {
        $user = $this->getUser();
        return $this->render('pareto/index.html.twig', [
            'problems' => $problems->findByUser($user),
        ]);
    }

    #[Route('/{id}', name: 'app_pareto_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Problem $problem): Response
    {
        $this->denyAccessUnlessGranted(ProblemVoter::VIEW, $problem);

        $pareto = [];
        foreach ($problem->getSolutions() as $s) {
            if ($s->isPareto()) {
                $pareto[] = [
                    'id'       => $s->getId(),
                    'items'    => $s->getSelectedItemIds(),
                    'weight'   => $s->getTotalWeight(),
                    'values'   => $s->getObjectiveValues(),
                    'algo'     => $s->getAlgorithm(),
                ];
            }
        }

        return $this->render('pareto/show.html.twig', [
            'problem' => $problem,
            'pareto'  => $pareto,
        ]);
    }

    /** JSON feed used by Chart.js / Plotly front-end. */
    #[Route('/{id}/data', name: 'app_pareto_data', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function data(Problem $problem): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProblemVoter::VIEW, $problem);
        $rows = [];
        foreach ($problem->getSolutions() as $s) {
            if (!$s->isPareto()) { continue; }
            $rows[] = [
                'id'     => $s->getId(),
                'weight' => $s->getTotalWeight(),
                'values' => $s->getObjectiveValues(),
                'items'  => $s->getSelectedItemIds(),
            ];
        }
        return new JsonResponse([
            'problem'    => $problem->getId(),
            'objectives' => $problem->getObjectives(),
            'pareto'     => $rows,
            'status'     => $problem->getStatus(),
            'progress'   => $problem->getProgress(),
        ]);
    }
}
