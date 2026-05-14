<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\AuditLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/audit')]
#[IsGranted('ROLE_USER')]
final class AuditController extends AbstractController
{
    #[Route('', name: 'app_audit_index', methods: ['GET'])]
    public function index(Request $request, AuditLogRepository $repo): Response
    {
        $action = $request->query->get('action') ?: null;
        $email  = $request->query->get('user_email') ?: null;
        $from   = $request->query->get('from') ? new \DateTimeImmutable($request->query->get('from')) : null;
        $to     = $request->query->get('to') ? new \DateTimeImmutable($request->query->get('to')) : null;

        if (!$this->isGranted('ROLE_ADMIN')) {
            $email = $this->getUser()?->getUserIdentifier();
        }

        $logs = $repo->search($action, $email, $from, $to, 200);

        return $this->render('audit/index.html.twig', [
            'logs'   => $logs,
            'action' => $action,
            'email'  => $email,
            'from'   => $from,
            'to'     => $to,
        ]);
    }
}
