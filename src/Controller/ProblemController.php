<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Item;
use App\Entity\Problem;
use App\Entity\Project;
use App\Entity\ProblemSnapshot;
use App\Entity\User;
use App\Message\ComputeParetoMessage;
use App\Repository\ProblemRepository;
use App\Repository\ProjectRepository;
use App\Security\Voter\ProblemVoter;
use App\Security\Voter\ProjectVoter;
use App\Service\Audit\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/problems')]
#[IsGranted('ROLE_USER')]
final class ProblemController extends AbstractController
{
    #[Route('', name: 'app_problem_index', methods: ['GET'])]
    public function index(ProblemRepository $problems): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->render('problem/index.html.twig', [
            'problems' => $problems->findByUser($user),
        ]);
    }

    #[Route('/new', name: 'app_problem_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        ProjectRepository $projects,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        AuditLogger $audit,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $userProjects = $projects->findBy(['user' => $user], ['updatedAt' => 'DESC']);

        $problem = new Problem();
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('problem', (string)$request->request->get('_csrf_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }
            $projectId = (int)$request->request->get('project_id');
            $project   = $projects->find($projectId);
            if ($project === null) {
                $this->addFlash('danger', 'Projet introuvable.');
                return $this->redirectToRoute('app_problem_new');
            }
            $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

            $this->populateProblemFromRequest($problem, $request, $project);

            $errors = $validator->validate($problem);
            foreach ($problem->getItems() as $item) {
                foreach ($validator->validate($item) as $e) { $errors[] = $e; }
            }

            if (count($errors) === 0) {
                $em->persist($problem);
                $em->flush();
                $this->snapshot($em, $problem, $user, 'initial');
                $audit->log('problem.create', 'Problem', $problem->getId(), ['name' => $problem->getName()]);
                $this->addFlash('success', 'Problème créé.');
                return $this->redirectToRoute('app_problem_show', ['id' => $problem->getId()]);
            }
            foreach ($errors as $err) { $this->addFlash('danger', (string)$err->getMessage()); }
        }

        return $this->render('problem/new.html.twig', [
            'problem'  => $problem,
            'projects' => $userProjects,
        ]);
    }

    #[Route('/{id}', name: 'app_problem_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Problem $problem): Response
    {
        $this->denyAccessUnlessGranted(ProblemVoter::VIEW, $problem);
        return $this->render('problem/show.html.twig', ['problem' => $problem]);
    }

    #[Route('/{id}/edit', name: 'app_problem_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        Problem $problem,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        AuditLogger $audit,
    ): Response {
        $this->denyAccessUnlessGranted(ProblemVoter::EDIT, $problem);
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('problem', (string)$request->request->get('_csrf_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }
            // Archive previous state before modification.
            $this->snapshot($em, $problem, $user, 'before_edit');

            // Replace items (cascade remove).
            foreach ($problem->getItems()->toArray() as $it) { $em->remove($it); }
            $em->flush();

            $this->populateProblemFromRequest($problem, $request, $problem->getProject());
            $problem->touch();

            $errors = $validator->validate($problem);
            foreach ($problem->getItems() as $item) {
                foreach ($validator->validate($item) as $e) { $errors[] = $e; }
            }
            if (count($errors) === 0) {
                $em->flush();
                $audit->log('problem.update', 'Problem', $problem->getId());
                $this->addFlash('success', 'Problème mis à jour (snapshot créé).');
                return $this->redirectToRoute('app_problem_show', ['id' => $problem->getId()]);
            }
            foreach ($errors as $err) { $this->addFlash('danger', (string)$err->getMessage()); }
        }

        /** @var User $user */
        $user = $this->getUser();
        return $this->render('problem/edit.html.twig', [
            'problem'  => $problem,
            'projects' => [$problem->getProject()],
        ]);
    }

    #[Route('/{id}/delete', name: 'app_problem_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Problem $problem, EntityManagerInterface $em, AuditLogger $audit): Response
    {
        $this->denyAccessUnlessGranted(ProblemVoter::DELETE, $problem);
        if (!$this->isCsrfTokenValid('problem_delete_'.$problem->getId(), (string)$request->request->get('_csrf_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $id = $problem->getId();
        $em->remove($problem);
        $em->flush();
        $audit->log('problem.delete', 'Problem', $id);
        $this->addFlash('success', 'Problème supprimé.');
        return $this->redirectToRoute('app_problem_index');
    }

    /** Trigger the async Pareto computation. */
    #[Route('/{id}/compute', name: 'app_problem_compute', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function compute(Request $request, Problem $problem, MessageBusInterface $bus, AuditLogger $audit, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(ProblemVoter::COMPUTE, $problem);
        if (!$this->isCsrfTokenValid('problem_compute_'.$problem->getId(), (string)$request->request->get('_csrf_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $algo = (string)$request->request->get('algorithm', 'epsilon');
        if (!in_array($algo, ['epsilon', 'nsga2'], true)) { $algo = 'epsilon'; }

        $problem->setStatus(Problem::STATUS_COMPUTING);
        $problem->setProgress(0);
        $em->flush();

        $bus->dispatch(new ComputeParetoMessage((int)$problem->getId(), $algo));
        $audit->log('problem.compute', 'Problem', $problem->getId(), ['algorithm' => $algo]);
        $this->addFlash('info', 'Calcul lancé en arrière-plan.');
        return $this->redirectToRoute('app_pareto_show', ['id' => $problem->getId()]);
    }

    #[Route('/{id}/progress', name: 'app_problem_progress', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function progress(Problem $problem): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProblemVoter::VIEW, $problem);
        return new JsonResponse([
            'status'   => $problem->getStatus(),
            'progress' => $problem->getProgress(),
        ]);
    }

    private function populateProblemFromRequest(Problem $problem, Request $request, ?Project $project): void
    {
        $problem->setName(trim((string)$request->request->get('name')));
        $problem->setDescription($request->request->get('description') !== null ? trim((string)$request->request->get('description')) : null);
        $problem->setCapacity((float)$request->request->get('capacity', 0));
        $problem->setObjectiveCount((int)$request->request->get('objective_count', 2));

        $objectiveNames = $request->request->all('objective_name');
        $labels = [];
        for ($i = 0; $i < $problem->getObjectiveCount(); $i++) {
            $labels[] = trim((string)($objectiveNames[$i] ?? 'f'.($i+1)));
        }
        $problem->setObjectives($labels);
        $problem->setFuzzyEnabled($request->request->getBoolean('fuzzy_enabled'));
        if ($project !== null) {
            $problem->setProject($project);
        }

        $itemNames    = $request->request->all('item_name');
        $itemWeights  = $request->request->all('item_weight');
        $itemValues   = $request->request->all('item_values'); // list of lists
        $itemFuzzy    = $request->request->all('item_fuzzy');  // optional list of 3-tuples lists

        $count = count($itemNames);
        for ($idx = 0; $idx < $count; $idx++) {
            $name = trim((string)($itemNames[$idx] ?? ''));
            if ($name === '') { continue; }
            $item = new Item();
            $item->setName($name);
            $item->setWeight((float)($itemWeights[$idx] ?? 0));
            $values = array_values(array_map('floatval', (array)($itemValues[$idx] ?? [])));
            if (count($values) > $problem->getObjectiveCount()) {
                $values = array_slice($values, 0, $problem->getObjectiveCount());
            }
            while (count($values) < $problem->getObjectiveCount()) { $values[] = 0.0; }
            $item->setValues($values);

            if ($problem->isFuzzyEnabled() && !empty($itemFuzzy[$idx])) {
                $fuzzy = [];
                foreach ((array)$itemFuzzy[$idx] as $triplet) {
                    $t = array_map('floatval', array_values((array)$triplet));
                    while (count($t) < 3) { $t[] = 0.0; }
                    $fuzzy[] = array_slice($t, 0, 3);
                }
                $item->setFuzzyValues($fuzzy);
            }
            $item->setPosition($idx);
            $problem->addItem($item);
        }
    }

    private function snapshot(EntityManagerInterface $em, Problem $problem, User $user, string $reason): void
    {
        $items = [];
        foreach ($problem->getItems() as $it) {
            $items[] = [
                'name'   => $it->getName(),
                'weight' => $it->getWeight(),
                'values' => $it->getValues(),
                'fuzzy'  => $it->getFuzzyValues(),
                'pos'    => $it->getPosition(),
            ];
        }
        $snap = new ProblemSnapshot();
        $snap->setProblem($problem);
        $snap->setAuthor($user);
        $snap->setReason($reason);
        $snap->setPayload([
            'name'          => $problem->getName(),
            'description'   => $problem->getDescription(),
            'capacity'      => $problem->getCapacity(),
            'objective_count' => $problem->getObjectiveCount(),
            'objectives'    => $problem->getObjectives(),
            'fuzzy_enabled' => $problem->isFuzzyEnabled(),
            'items'         => $items,
        ]);
        $em->persist($snap);
        $em->flush();
    }
}
