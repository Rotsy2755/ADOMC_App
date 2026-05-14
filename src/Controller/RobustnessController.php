<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Problem;
use App\Repository\AhpMatrixRepository;
use App\Security\Voter\ProblemVoter;
use App\Service\Robustness\RobustnessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/robustness')]
#[IsGranted('ROLE_USER')]
final class RobustnessController extends AbstractController
{
    #[Route('/{id}', name: 'app_robustness_show', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function show(
        Request $request,
        Problem $problem,
        AhpMatrixRepository $matrices,
        RobustnessService $service,
    ): Response {
        $this->denyAccessUnlessGranted(ProblemVoter::VIEW, $problem);

        $availableMatrices = $matrices->findBy(['problem' => $problem], ['createdAt' => 'DESC']);
        $result = null;
        $alpha  = (float)$request->request->get('alpha', 0.5);
        $topK   = max(1, (int)$request->request->get('top_k', 3));
        $samples = max(10, (int)$request->request->get('samples', 100));
        $neighbours = max(5, (int)$request->request->get('neighbours', 20));
        $selectedMatrixId = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('robustness_'.$problem->getId(), (string)$request->request->get('_csrf_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }
            $selectedMatrixId = (int)$request->request->get('ahp_matrix_id');
            $matrix = $matrices->find($selectedMatrixId);
            if ($matrix === null || $matrix->getProblem()?->getId() !== $problem->getId()) {
                $this->addFlash('danger', 'Pondération AHP introuvable.');
                return $this->redirectToRoute('app_robustness_show', ['id' => $problem->getId()]);
            }

            $solutions = [];
            foreach ($problem->getSolutions() as $s) {
                if ($s->isPareto()) {
                    $solutions[] = ['id' => (int)$s->getId(), 'values' => $s->getObjectiveValues()];
                }
            }
            if ($solutions === []) {
                $this->addFlash('danger', 'Aucune solution Pareto n\'est disponible pour ce problème.');
            } else {
                $result = $service->compute($solutions, $matrix->getWeights(), [
                    'alpha' => $alpha,
                    'topK' => $topK,
                    'samples' => $samples,
                    'neighbours' => $neighbours,
                ]);
            }
        }

        return $this->render('robustness/show.html.twig', [
            'problem'  => $problem,
            'matrices' => $availableMatrices,
            'result'   => $result,
            'alpha'    => $alpha,
            'topK'     => $topK,
            'samples'  => $samples,
            'neighbours' => $neighbours,
            'selectedMatrixId' => $selectedMatrixId,
        ]);
    }
}
