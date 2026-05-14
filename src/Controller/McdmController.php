<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Problem;
use App\Message\ComputeMcdmMessage;
use App\Repository\AhpMatrixRepository;
use App\Repository\McdmResultRepository;
use App\Repository\ProblemRepository;
use App\Security\Voter\ProblemVoter;
use App\Service\Audit\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mcdm')]
#[IsGranted('ROLE_USER')]
final class McdmController extends AbstractController
{
    private const METHODS = ['topsis', 'vikor', 'promethee2', 'electre1'];
    private const NORMALIZATIONS = ['vector', 'minmax', 'zscore'];

    #[Route('', name: 'app_mcdm_index', methods: ['GET'])]
    public function index(ProblemRepository $problems): Response
    {
        $user = $this->getUser();
        return $this->render('mcdm/index.html.twig', [
            'problems' => $problems->findByUser($user),
        ]);
    }

    #[Route('/{id}', name: 'app_mcdm_show', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function show(
        Request $request,
        Problem $problem,
        AhpMatrixRepository $matrices,
        McdmResultRepository $results,
        MessageBusInterface $bus,
        AuditLogger $audit,
    ): Response {
        $this->denyAccessUnlessGranted(ProblemVoter::VIEW, $problem);

        $existingMatrices = $matrices->findBy(['problem' => $problem], ['createdAt' => 'DESC']);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('mcdm_'.$problem->getId(), (string)$request->request->get('_csrf_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }
            $ahpId  = (int)$request->request->get('ahp_matrix_id');
            $method = (string)$request->request->get('method', 'topsis');
            $norm   = (string)$request->request->get('normalization', 'vector');

            if (!in_array($method, self::METHODS, true)) {
                $this->addFlash('danger', 'Méthode inconnue.');
                return $this->redirectToRoute('app_mcdm_show', ['id' => $problem->getId()]);
            }
            if (!in_array($norm, self::NORMALIZATIONS, true)) {
                $norm = 'vector';
            }
            $matrix = $matrices->find($ahpId);
            if ($matrix === null || $matrix->getProblem()?->getId() !== $problem->getId()) {
                $this->addFlash('danger', 'Pondération AHP introuvable.');
                return $this->redirectToRoute('app_mcdm_show', ['id' => $problem->getId()]);
            }

            $params = [];
            if ($method === 'vikor') {
                $params['nu'] = max(0.0, min(1.0, (float)$request->request->get('vikor_nu', 0.5)));
            }
            if ($method === 'promethee2') {
                $params['preference_type'] = (string)$request->request->get('promethee_type', 'usual');
                $params['p'] = (float)$request->request->get('promethee_p', 0.5);
                $params['q'] = (float)$request->request->get('promethee_q', 0.1);
            }
            if ($method === 'electre1') {
                $params['c_threshold'] = (float)$request->request->get('electre_c', 0.65);
                $params['d_threshold'] = (float)$request->request->get('electre_d', 0.35);
            }

            $bus->dispatch(new ComputeMcdmMessage(
                (int)$problem->getId(),
                (int)$matrix->getId(),
                $method,
                $norm,
                $params,
            ));
            $audit->log('mcdm.dispatch', 'Problem', $problem->getId(), ['method' => $method, 'normalization' => $norm]);
            $this->addFlash('info', sprintf('Calcul %s déclenché en arrière-plan.', strtoupper($method)));
            return $this->redirectToRoute('app_mcdm_show', ['id' => $problem->getId()]);
        }

        $history = $results->findBy(['problem' => $problem], ['createdAt' => 'DESC'], 20);
        return $this->render('mcdm/show.html.twig', [
            'problem'  => $problem,
            'matrices' => $existingMatrices,
            'history'  => $history,
            'methods'  => self::METHODS,
            'normalizations' => self::NORMALIZATIONS,
        ]);
    }

    #[Route('/{id}/choose/{result}', name: 'app_mcdm_choose', methods: ['POST'], requirements: ['id' => '\d+', 'result' => '\d+'])]
    public function choose(
        Request $request,
        Problem $problem,
        int $result,
        McdmResultRepository $results,
        EntityManagerInterface $em,
        AuditLogger $audit,
    ): Response {
        $this->denyAccessUnlessGranted(ProblemVoter::VIEW, $problem);
        if (!$this->isCsrfTokenValid('mcdm_choose_'.$result, (string)$request->request->get('_csrf_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $res = $results->find($result);
        if ($res === null || $res->getProblem()?->getId() !== $problem->getId()) {
            $this->addFlash('danger', 'Résultat introuvable.');
        } else {
            // Reset chosen flag among results of the same problem, keep one choice.
            foreach ($results->findBy(['problem' => $problem]) as $r) {
                if ($r->isChosen() && $r->getId() !== $res->getId()) {
                    $r->setChosen(false);
                }
            }
            $res->setChosen(true);
            $em->flush();
            $audit->log('mcdm.choose', 'McdmResult', $res->getId());
            $this->addFlash('success', 'Solution validée comme choix du décideur.');
        }
        return $this->redirectToRoute('app_mcdm_show', ['id' => $problem->getId()]);
    }
}
