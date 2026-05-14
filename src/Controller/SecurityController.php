<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request, AuthenticationUtils $utils, RateLimiterFactory $loginLimiter): Response
    {
        // Rate-limit brute force attempts based on client IP.
        $limit = $loginLimiter->create($request->getClientIp() ?? 'anon')->consume();
        if (!$limit->isAccepted()) {
            return $this->render('security/login.html.twig', [
                'last_username' => $utils->getLastUsername(),
                'error' => new \Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException(
                    'Trop de tentatives. Veuillez réessayer dans 15 minutes.'
                ),
            ], new Response('', Response::HTTP_TOO_MANY_REQUESTS));
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $utils->getLastUsername(),
            'error'         => $utils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Intercepted by the firewall logout path.');
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        UserRepository $users,
        EntityManagerInterface $em,
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('register', (string)$request->request->get('_csrf_token'))) {
                $this->addFlash('danger', 'Jeton CSRF invalide.');
                return $this->redirectToRoute('app_register');
            }
            $email = trim((string)$request->request->get('email'));
            $name  = trim((string)$request->request->get('fullName'));
            $pwd   = (string)$request->request->get('password');

            if ($email === '' || $name === '' || strlen($pwd) < 8) {
                $this->addFlash('danger', 'Champs invalides (mot de passe ≥ 8 caractères).');
                return $this->redirectToRoute('app_register');
            }
            if ($users->findOneBy(['email' => $email]) !== null) {
                $this->addFlash('danger', 'Adresse e-mail déjà utilisée.');
                return $this->redirectToRoute('app_register');
            }

            $user = new User();
            $user->setEmail($email);
            $user->setFullName($name);
            $user->setRoles(['ROLE_USER']);
            $user->setPassword($hasher->hashPassword($user, $pwd));
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Compte créé avec succès. Vous pouvez vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/register.html.twig');
    }
}
