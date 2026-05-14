<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AhpMatrix;
use App\Entity\Problem;
use App\Entity\User;
use App\Repository\AhpMatrixRepository;
use App\Repository\ProblemRepository;
use App\Security\Voter\ProblemVoter;
use App\Service\Ahp\AhpService;
use App\Service\Audit\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/ahp')]
#[IsGranted('ROLE_USER')]
final class AhpController extends AbstractController
{
    #[Route('', name: 'app_ahp_index', methods: ['GET'])]
    public function index(ProblemRepository $problems): Response
    {
        $user = $this->getUser();
        return $this->render('ahp/index.html.twig', [
            'problems' => $problems->findByUser($user),
        ]);
    }

    #[Route('/{id}', name: 'app_ahp_show', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function show(
        Request $request,
        Problem $problem,
        AhpService $ahp,
        AhpMatrixRepository $matrices,
        EntityManagerInterface $em,
        AuditLogger $audit,
    ): Response {
        $this->denyAccessUnlessGranted(ProblemVoter::VIEW, $problem);
        $n = $problem->getObjectiveCount();

        $computed = null;
        $savedMatrix = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('ahp_'.$problem->getId(), (string)$request->request->get('_csrf_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $raw = $request->request->all('matrix'); // 2D array of floats
            $matrix = [];
            for ($i = 0; $i < $n; $i++) {
                for ($j = 0; $j < $n; $j++) {
                    if ($i === $j) { $matrix[$i][$j] = 1.0; continue; }
                    $v = (float)($raw[$i][$j] ?? 1.0);
                    if ($v <= 0) { $v = 1.0; }
                    $matrix[$i][$j] = $v;
                    // Enforce reciprocity if user only filled upper triangle.
                    if ($j > $i && empty($raw[$j][$i])) {
                        $matrix[$j][$i] = 1.0 / $v;
                    }
                }
            }
            // Symmetrize missing cells deterministically.
            for ($i = 0; $i < $n; $i++) {
                for ($j = 0; $j < $n; $j++) {
                    if (!isset($matrix[$i][$j])) { $matrix[$i][$j] = 1.0; }
                }
            }

            $computed = $ahp->compute($matrix);

            if ($request->request->getBoolean('save') && $computed['consistent']) {
                /** @var User $user */
                $user = $this->getUser();
                $m = new AhpMatrix();
                $m->setLabel(trim((string)$request->request->get('label', 'scénario')));
                $m->setMatrix($matrix);
                $m->setWeights($computed['weights']);
                $m->setLambdaMax($computed['lambdaMax']);
                $m->setCi($computed['ci']);
                $m->setCr($computed['cr']);
                $m->setConsistent(true);
                $m->setProblem($problem);
                $m->setAuthor($user);
                $em->persist($m);
                $em->flush();
                $audit->log('ahp.save', 'AhpMatrix', $m->getId(), ['cr' => $computed['cr']]);
                $savedMatrix = $m;
                $this->addFlash('success', sprintf('Pondération enregistrée (CR = %.3f).', $computed['cr']));
            } elseif ($request->request->getBoolean('save') && !$computed['consistent']) {
                $this->addFlash('warning', sprintf('CR = %.3f ≥ 0,10 — la matrice est inconsistante. Corrigez les jugements indiqués.', $computed['cr']));
            }
        }

        $existing = $matrices->findBy(['problem' => $problem], ['createdAt' => 'DESC']);

        return $this->render('ahp/show.html.twig', [
            'problem'    => $problem,
            'n'          => $n,
            'computed'   => $computed,
            'existing'   => $existing,
            'savedId'    => $savedMatrix?->getId(),
        ]);
    }
}
