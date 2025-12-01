<?php
declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\AuthAccessTokenBlacklist;
use App\Repository\AuthRefreshTokenRepository;
use App\Repository\UserRepository;
use App\Security\JwtService;
use App\Security\RefreshTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class AuthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly JwtService $jwt,
        private readonly RefreshTokenService $refresh,
        private readonly AuthRefreshTokenRepository $refreshRepo,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly CsrfTokenManagerInterface $csrf,
    ) {}

    #[Route('/login', name: 'web_login', methods: ['GET', 'POST'])]
    public function login(Request $req): Response
    {
        if ($req->isMethod('GET')) {
            return $this->render('auth/login.html.twig', [
                'csrf_token' => $this->csrf->getToken('authenticate')->getValue(),
            ]);
        }

        $csrf = new CsrfToken('authenticate', (string) $req->request->get('_csrf_token', ''));
        if (!$this->csrf->isTokenValid($csrf)) {
            return $this->render('auth/login.html.twig', [
                'csrf_token' => $this->csrf->getToken('authenticate')->getValue(),
                'error' => 'Invalid CSRF token.',
            ], new Response('', 422));
        }

        $email = strtolower(trim((string) $req->request->get('email', '')));
        $password = (string) $req->request->get('password', '');

        $user = $email !== '' ? $this->users->findOneBy(['email' => $email]) : null;
        if (!$user || !$this->hasher->isPasswordValid($user, $password)) {
            return $this->render('auth/login.html.twig', [
                'csrf_token' => $this->csrf->getToken('authenticate')->getValue(),
                'error' => 'Invalid email or password.',
            ], new Response('', 401));
        }

        // Create tokens
        $access = $this->jwt->createAccessToken((string) $user->getId(), $user->getEmail(), $user->getRoles());
        $rt = $this->refresh->issue($user, $req->headers->get('User-Agent'), $req->getClientIp());

        // Redirect to dashboard
        $resp = $this->redirectToRoute('web_dashboard');

        // Set cookies
        $resp->headers->setCookie($this->makeCookie($req, 'access_token', $access['token'], $access['expiresAt']));
        $resp->headers->setCookie($this->makeCookie($req, 'refresh_token', $rt['refreshToken'], $rt['expiresAt']->getTimestamp()));

        return $resp;
    }

    #[Route('/logout', name: 'web_logout', methods: ['POST'])]
    public function logout(Request $req): Response
    {
        // Revoke refresh token if present
        $refreshToken = (string) $req->cookies->get('refresh_token', '');
        if ($refreshToken !== '') {
            $hash = hash('sha256', $refreshToken);
            $rt = $this->refreshRepo->findOneBy(['tokenHash' => $hash]);
            if ($rt && $rt->getRevokedAt() === null) {
               $rt->setRevokedAt(new \DateTime('now'));

            }
        }

        // Blacklist access token jti if present
        $accessToken = (string) $req->cookies->get('access_token', '');
        if ($accessToken !== '') {
            try {
                $payload = $this->jwt->decodeAndVerify($accessToken);
                $jti = (string) ($payload['jti'] ?? '');
                $exp = (int) ($payload['exp'] ?? 0);

                if ($jti !== '' && $exp > time()) {
                    $user = $this->getUser();
                    if ($user instanceof UserInterface) {
                        $b = new AuthAccessTokenBlacklist();
                        $b->setUser($user)
                            ->setJti($jti)
                            ->setExpiresAt((new \DateTimeImmutable())->setTimestamp($exp))
                            ->setRevokedAt(new \DateTimeImmutable('now'))
                            ->setReason('logout');
                        $this->em->persist($b);
                    }
                }
            } catch (\Throwable $e) {
                // ignore invalid token on logout
            }
        }

        $this->em->flush();

        // Redirect to login and clear cookies
        $resp = new RedirectResponse('/login');

        $resp->headers->setCookie($this->clearCookie($req, 'access_token'));
        $resp->headers->setCookie($this->clearCookie($req, 'refresh_token'));

        return $resp;
    }
private function makeCookie(Request $req, string $name, string $value, int $expiresAtTs): Cookie
{
    $secure = false; // force false in dev to ensure browser stores cookies

    return Cookie::create($name)
        ->withValue($value)
        ->withExpires($expiresAtTs)
        ->withPath('/')
        ->withSecure($secure)
        ->withHttpOnly(true)
        ->withSameSite('lax');
}


    private function clearCookie(Request $req, string $name): Cookie
    {
        $secure = $req->isSecure();

        return Cookie::create($name)
            ->withValue('')
            ->withExpires(1)
            ->withPath('/')
            ->withSecure($secure)
            ->withHttpOnly(true)
            ->withSameSite('lax');
    }
}
