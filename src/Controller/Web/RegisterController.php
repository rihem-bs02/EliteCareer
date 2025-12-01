<?php
declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class RegisterController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly CsrfTokenManagerInterface $csrf,
    ) {}

    #[Route('/register', name: 'web_register', methods: ['GET', 'POST'])]
    public function register(Request $req): Response
    {
        if ($req->isMethod('GET')) {
            return $this->render('auth/register.html.twig', [
                'csrf_token' => $this->csrf->getToken('register')->getValue(),
            ]);
        }

        $token = new CsrfToken('register', (string) $req->request->get('_csrf_token', ''));
        if (!$this->csrf->isTokenValid($token)) {
            return $this->render('auth/register.html.twig', [
                'csrf_token' => $this->csrf->getToken('register')->getValue(),
                'error' => 'Invalid CSRF token.',
            ], new Response('', 422));
        }

        $email = strtolower(trim((string) $req->request->get('email', '')));
        $password = (string) $req->request->get('password', '');
        $role = strtoupper((string) $req->request->get('role', 'CANDIDATE')); // CANDIDATE | HR

        if ($email === '' || $password === '') {
            return $this->render('auth/register.html.twig', [
                'csrf_token' => $this->csrf->getToken('register')->getValue(),
                'error' => 'Email and password are required.',
            ], new Response('', 422));
        }

        if ($this->users->findOneBy(['email' => $email])) {
            return $this->render('auth/register.html.twig', [
                'csrf_token' => $this->csrf->getToken('register')->getValue(),
                'error' => 'Email already exists.',
            ], new Response('', 409));
        }

        $user = new User();
        $user->setEmail($email);

        $symRole = match ($role) {
            'HR' => User::ROLE_HR,
            default => User::ROLE_CANDIDATE,
        };
        $user->setRoles([$symRole]);

        $user->setPasswordHash($this->hasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        // flash success + redirect to login
        $this->addFlash('success', 'Account created. Please login.');
        return $this->redirectToRoute('web_login');
    }
}
