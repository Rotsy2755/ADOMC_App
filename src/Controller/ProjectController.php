<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Security\Voter\ProjectVoter;
use App\Service\Audit\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/projects')]
#[IsGranted('ROLE_USER')]
final class ProjectController extends AbstractController
{
    #[Route('', name: 'app_project_index', methods: ['GET'])]
    public function index(ProjectRepository $projects): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->render('project/index.html.twig', [
            'projects' => $projects->findBy(['user' => $user], ['updatedAt' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'app_project_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, ValidatorInterface $validator, AuditLogger $audit): Response
    {
        $project = new Project();
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('project', (string)$request->request->get('_csrf_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }
            $project->setName(trim((string)$request->request->get('name')));
            $project->setDescription($request->request->get('description') !== null ? trim((string)$request->request->get('description')) : null);
            /** @var User $user */
            $user = $this->getUser();
            $project->setUser($user);

            $errors = $validator->validate($project);
            if (count($errors) === 0) {
                $em->persist($project);
                $em->flush();
                $audit->log('project.create', 'Project', $project->getId(), ['name' => $project->getName()]);
                $this->addFlash('success', 'Projet créé.');
                return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
            }
            foreach ($errors as $err) { $this->addFlash('danger', (string)$err->getMessage()); }
        }

        return $this->render('project/new.html.twig', ['project' => $project]);
    }

    #[Route('/{id}', name: 'app_project_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Project $project): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);
        return $this->render('project/show.html.twig', ['project' => $project]);
    }

    #[Route('/{id}/edit', name: 'app_project_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Project $project, EntityManagerInterface $em, ValidatorInterface $validator, AuditLogger $audit): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('project', (string)$request->request->get('_csrf_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }
            $project->setName(trim((string)$request->request->get('name')));
            $project->setDescription($request->request->get('description') !== null ? trim((string)$request->request->get('description')) : null);
            $project->touch();
            $errors = $validator->validate($project);
            if (count($errors) === 0) {
                $em->flush();
                $audit->log('project.update', 'Project', $project->getId());
                $this->addFlash('success', 'Projet mis à jour.');
                return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
            }
            foreach ($errors as $err) { $this->addFlash('danger', (string)$err->getMessage()); }
        }
        return $this->render('project/edit.html.twig', ['project' => $project]);
    }

    #[Route('/{id}/delete', name: 'app_project_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Project $project, EntityManagerInterface $em, AuditLogger $audit): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::DELETE, $project);
        if (!$this->isCsrfTokenValid('project_delete_'.$project->getId(), (string)$request->request->get('_csrf_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $id = $project->getId();
        $em->remove($project);
        $em->flush();
        $audit->log('project.delete', 'Project', $id);
        $this->addFlash('success', 'Projet supprimé.');
        return $this->redirectToRoute('app_project_index');
    }
}
