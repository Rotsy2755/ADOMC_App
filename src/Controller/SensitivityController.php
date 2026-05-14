<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Problem;
use App\Message\ComputeSensitivityMessage;
use App\Repository\McdmResultRepository;
use App\Repository\ProblemRepository;
use App\Repository\SensitivityAnalysisRepository;
use App\Security\Voter\ProblemVoter;
use App\Service\Audit\AuditLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/sensitivity')]
#[IsGranted('ROLE_USER')]
final class SensitivityController extends AbstractController
{
    #[Route('', name: 'app_sensitivity_index', methods: ['GET'])]
    public function index(ProblemRepository $problems): Response
    {
        return $this->render('sensitivity/index.html.twig', [
            'problems' => $problems->findByUser($this->getUser()),
        ]);
    }

    #[Route('/{id}', name: 'app_sensitivity_show', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function show(
        Request $request,
        Problem $problem,
        McdmResultRepository $mcdmResults,
        SensitivityAnalysisRepository $analyses,
        MessageBusInterface $bus,
        AuditLogger $audit,
    ): Response {
        $this->denyAccessUnlessGranted(ProblemVoter::VIEW, $problem);

        $candidates = $mcdmResults->findBy(['problem' => $problem], ['createdAt' => 'DESC']);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('sensitivity_'.$problem->getId(), (string)$request->request->get('_csrf_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }
            $mcdmId = (int)$request->request->get('mcdm_result_id');
            $crit   = max(0, (int)$request->request->get('criterion', 0));
            $min    = max(0.0, (float)$request->request->get('min', 0.1));
            $max    = min(1.0, (float)$request->request->get('max', 0.9));
            $step   = max(0.01, (float)$request->request->get('step', 0.05));

            $mcdm = $mcdmResults->find($mcdmId);
            if ($mcdm === null || $mcdm->getProblem()?->getId() !== $problem->getId()) {
                $this->addFlash('danger', 'Résultat MCDM introuvable.');
                return $this->redirectToRoute('app_sensitivity_show', ['id' => $problem->getId()]);
            }
            if ($min >= $max) {
                $this->addFlash('danger', 'Intervalle invalide (min doit être < max).');
                return $this->redirectToRoute('app_sensitivity_show', ['id' => $problem->getId()]);
            }

            $bus->dispatch(new ComputeSensitivityMessage(
                (int)$problem->getId(),
                (int)$mcdm->getId(),
                ['criterion' => $crit, 'min' => $min, 'max' => $max, 'step' => $step],
            ));
            $audit->log('sensitivity.dispatch', 'Problem', $problem->getId(), ['criterion' => $crit, 'min' => $min, 'max' => $max, 'step' => $step]);
            $this->addFlash('info', 'Analyse de sensibilité déclenchée en arrière-plan.');
            return $this->redirectToRoute('app_sensitivity_show', ['id' => $problem->getId()]);
        }

        $history = [];
        foreach ($candidates as $c) {
            foreach ($analyses->findBy(['mcdmResult' => $c], ['createdAt' => 'DESC']) as $a) {
                $history[] = $a;
            }
        }

        return $this->render('sensitivity/show.html.twig', [
            'problem'    => $problem,
            'candidates' => $candidates,
            'history'    => $history,
        ]);
    }

    #[Route('/{id}/analysis/{analysis}', name: 'app_sensitivity_view', methods: ['GET'], requirements: ['id' => '\d+', 'analysis' => '\d+'])]
    public function view(
        Problem $problem,
        int $analysis,
        SensitivityAnalysisRepository $analyses,
    ): Response {
        $this->denyAccessUnlessGranted(ProblemVoter::VIEW, $problem);
        $entity = $analyses->find($analysis);
        if ($entity === null || $entity->getMcdmResult()?->getProblem()?->getId() !== $problem->getId()) {
            throw $this->createNotFoundException();
        }
        return $this->render('sensitivity/view.html.twig', [
            'problem'  => $problem,
            'analysis' => $entity,
        ]);
    }
}
