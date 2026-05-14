<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Problem;
use App\Repository\AhpMatrixRepository;
use App\Security\Voter\ProblemVoter;
use App\Service\Scenario\ScenarioComparisonService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/scenario')]
#[IsGranted('ROLE_USER')]
final class ScenarioController extends AbstractController
{
    #[Route('/{id}', name: 'app_scenario_compare', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function compare(
        Request $request,
        Problem $problem,
        AhpMatrixRepository $matrices,
        ScenarioComparisonService $service,
    ): Response {
        $this->denyAccessUnlessGranted(ProblemVoter::VIEW, $problem);

        $availableMatrices = $matrices->findBy(['problem' => $problem], ['createdAt' => 'DESC']);

        $result = null;
        $selected = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('scenario_'.$problem->getId(), (string)$request->request->get('_csrf_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }
            $ids = (array)$request->request->all('scenario_ids');
            $ids = array_slice(array_values(array_unique(array_map('intval', $ids))), 0, ScenarioComparisonService::MAX_SCENARIOS);

            $scenarios = [];
            foreach ($ids as $mid) {
                $m = $matrices->find($mid);
                if ($m === null || $m->getProblem()?->getId() !== $problem->getId()) {
                    continue;
                }
                $scenarios[] = [
                    'name'    => sprintf('AHP #%d', $m->getId()),
                    'weights' => $m->getWeights(),
                ];
                $selected[$mid] = true;
            }

            $solutions = [];
            foreach ($problem->getSolutions() as $s) {
                if ($s->isPareto()) {
                    $solutions[] = ['id' => (int)$s->getId(), 'values' => $s->getObjectiveValues()];
                }
            }

            if ($scenarios === [] || $solutions === []) {
                $this->addFlash('danger', 'Veuillez sélectionner au moins un scénario et vérifier que le front de Pareto est calculé.');
            } else {
                $result = $service->compare($solutions, $scenarios);
            }
        }

        return $this->render('scenario/compare.html.twig', [
            'problem'  => $problem,
            'matrices' => $availableMatrices,
            'selected' => $selected,
            'result'   => $result,
            'maxScenarios' => ScenarioComparisonService::MAX_SCENARIOS,
        ]);
    }
}
