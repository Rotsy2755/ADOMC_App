<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Problem;
use App\Entity\ShareLink;
use App\Repository\ProblemRepository;
use App\Repository\ShareLinkRepository;
use App\Security\Voter\ProblemVoter;
use App\Service\Audit\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/share')]
final class ShareController extends AbstractController
{
    #[Route('', name: 'app_share_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(ShareLinkRepository $links): Response
    {
        $user = $this->getUser();
        $mine = $links->findBy(['owner' => $user], ['createdAt' => 'DESC']);
        return $this->render('share/index.html.twig', [
            'links' => $mine,
        ]);
    }

    #[Route('/new/{id}', name: 'app_share_new', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function create(
        Request $request,
        Problem $problem,
        EntityManagerInterface $em,
        AuditLogger $audit,
    ): Response {
        $this->denyAccessUnlessGranted(ProblemVoter::VIEW, $problem);
        if (!$this->isCsrfTokenValid('share_new_'.$problem->getId(), (string)$request->request->get('_csrf_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $expires = (int)$request->request->get('expires_days', 30);
        $expires = max(1, min(365, $expires));

        $token = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $token);
        $link = (new ShareLink())
            ->setTokenHash($hash)
            ->setExpiresAt(new \DateTimeImmutable('+'.$expires.' days'))
            ->setProblem($problem)
            ->setOwner($this->getUser());
        $em->persist($link);
        $em->flush();

        $audit->log('share.create', 'Problem', $problem->getId(), ['expires_days' => $expires]);
        $url = $this->generateUrl('app_share_public', ['token' => $token], 0);
        $this->addFlash('success', 'Lien de partage créé : '.$url);
        return $this->redirectToRoute('app_problem_show', ['id' => $problem->getId()]);
    }

    #[Route('/revoke/{id}', name: 'app_share_revoke', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function revoke(
        Request $request,
        ShareLink $link,
        EntityManagerInterface $em,
        AuditLogger $audit,
    ): Response {
        if ($link->getOwner()?->getUserIdentifier() !== $this->getUser()?->getUserIdentifier()
            && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('share_revoke_'.$link->getId(), (string)$request->request->get('_csrf_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $link->setRevoked(true);
        $em->flush();
        $audit->log('share.revoke', 'ShareLink', $link->getId());
        $this->addFlash('success', 'Lien révoqué.');
        return $this->redirectToRoute('app_share_index');
    }

    #[Route('/public/{token}', name: 'app_share_public', methods: ['GET'], requirements: ['token' => '[a-f0-9]{64}'])]
    public function public(
        string $token,
        ShareLinkRepository $links,
        EntityManagerInterface $em,
    ): Response {
        $hash = hash('sha256', $token);
        $link = $links->findByTokenHash($hash);
        if ($link === null || !$link->isActive()) {
            throw $this->createNotFoundException('Lien invalide, expiré ou révoqué.');
        }
        $link->incrementViews();
        $em->flush();

        $problem = $link->getProblem();
        if ($problem === null) {
            throw $this->createNotFoundException();
        }

        $paretoSolutions = [];
        foreach ($problem->getSolutions() as $s) {
            if ($s->isPareto()) {
                $paretoSolutions[] = $s;
            }
        }
        return $this->render('share/public.html.twig', [
            'problem'  => $problem,
            'solutions' => $paretoSolutions,
            'link'     => $link,
        ]);
    }
}
