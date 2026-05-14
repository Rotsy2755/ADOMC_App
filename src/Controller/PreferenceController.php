<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Problem;
use App\Repository\McdmResultRepository;
use App\Security\Voter\ProblemVoter;
use App\Service\Preference\InversePreferenceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/preference')]
#[IsGranted('ROLE_USER')]
final class PreferenceController extends AbstractController
{
    public const MIN_CHOSEN = 3;

    #[Route('/{id}', name: 'app_preference_suggest', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function suggest(
        Problem $problem,
        McdmResultRepository $mcdmResults,
        InversePreferenceService $service,
    ): Response {
        $this->denyAccessUnlessGranted(ProblemVoter::VIEW, $problem);

        $chosenResults = $mcdmResults->findBy(['problem' => $problem, 'chosen' => true]);
        $chosenIds = [];
        foreach ($chosenResults as $r) {
            if ($r->getRecommendedSolutionId() !== null) {
                $chosenIds[] = $r->getRecommendedSolutionId();
            }
        }
        $chosenIds = array_values(array_unique($chosenIds));

        $suggestion = null;
        $warning    = null;
        if (count($chosenIds) < self::MIN_CHOSEN) {
            $warning = sprintf(
                'Au moins %d solutions validées sont nécessaires (actuellement %d).',
                self::MIN_CHOSEN,
                count($chosenIds)
            );
        } else {
            $matrix = [];
            foreach ($problem->getSolutions() as $s) {
                if ($s->isPareto()) {
                    $matrix[(int)$s->getId()] = $s->getObjectiveValues();
                }
            }
            if ($matrix === []) {
                $warning = 'Aucune solution Pareto disponible pour ce problème.';
            } else {
                $suggestion = $service->suggestWeights($matrix, $chosenIds);
            }
        }

        return $this->render('preference/suggest.html.twig', [
            'problem'    => $problem,
            'chosen'     => $chosenIds,
            'suggestion' => $suggestion,
            'warning'    => $warning,
            'objectives' => $problem->getObjectives(),
        ]);
    }
}
