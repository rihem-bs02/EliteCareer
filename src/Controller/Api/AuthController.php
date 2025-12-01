<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\AuthAccessTokenBlacklist;
use App\Entity\User;
use App\Repository\AuthRefreshTokenRepository;
use App\Repository\UserRepository;
use App\Security\JwtService;
use App\Security\RefreshTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly JwtService $jwt,
        private readonly RefreshTokenService $refresh,
        private readonly AuthRefreshTokenRepository $refreshRepo,
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    #[Route('/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(Request $req): JsonResponse
    {
        $data = json_decode($req->getContent() ?: '{}', true) ?: [];
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $password = (string)($data['password'] ?? '');
        $role = strtoupper((string)($data['role'] ?? 'CANDIDATE')); // CANDIDATE | HR

        if ($email === '' || $password === '') {
            return $this->json(['error' => 'validation', 'message' => 'Email and password are required.'], 422);
        }
        if ($this->users->findOneBy(['email' => $email])) {
            return $this->json(['error' => 'conflict', 'message' => 'Email already exists.'], 409);
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

        return $this->json(['message' => 'registered'], 201);
    }

    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(Request $req): JsonResponse
    {
        $data = json_decode($req->getContent() ?: '{}', true) ?: [];
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $password = (string)($data['password'] ?? '');

        $user = $email ? $this->users->findOneBy(['email' => $email]) : null;
        if (!$user || !$this->hasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'invalid_credentials'], 401);
        }

        $access = $this->jwt->createAccessToken((string)$user->getId(), $user->getEmail(), $user->getRoles());
        $rt = $this->refresh->issue(
            $user,
            $req->headers->get('User-Agent'),
            $req->getClientIp(),
            $data['deviceLabel'] ?? null
        );

        return $this->json([
            'accessToken' => $access['token'],
            'accessTokenExpiresAt' => $access['expiresAt'],
            'refreshToken' => $rt['refreshToken'],
            'refreshTokenExpiresAt' => $rt['expiresAt']->format(DATE_ATOM),
        ]);
    }

    #[Route('/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    public function refresh(Request $req): JsonResponse
    {
        $data = json_decode($req->getContent() ?: '{}', true) ?: [];
        $refreshToken = (string)($data['refreshToken'] ?? '');

        if ($refreshToken === '') {
            return $this->json(['error' => 'validation', 'message' => 'refreshToken is required'], 422);
        }

        // verify + revoke old refresh token
        $old = $this->refresh->rotate($refreshToken);
        $user = $old->getUser();

        // issue new access + new refresh
        $access = $this->jwt->createAccessToken((string)$user->getId(), $user->getEmail(), $user->getRoles());
        $rt = $this->refresh->issue($user, $req->headers->get('User-Agent'), $req->getClientIp());

        return $this->json([
            'accessToken' => $access['token'],
            'accessTokenExpiresAt' => $access['expiresAt'],
            'refreshToken' => $rt['refreshToken'],
            'refreshTokenExpiresAt' => $rt['expiresAt']->format(DATE_ATOM),
        ]);
    }

    #[Route('/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(Request $req): JsonResponse
    {
        // revoke refresh token (optional but recommended)
        $data = json_decode($req->getContent() ?: '{}', true) ?: [];
        $refreshToken = (string)($data['refreshToken'] ?? '');

        if ($refreshToken !== '') {
            $hash = hash('sha256', $refreshToken);
            $rt = $this->refreshRepo->findOneBy(['tokenHash' => $hash]);
            if ($rt && $rt->getRevokedAt() === null) {
                $rt->setRevokedAt(new \DateTimeImmutable('now'));
            }
        }

        // blacklist current access token (if present)
        $auth = $req->headers->get('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            try {
                $payload = $this->jwt->decodeAndVerify(trim($m[1]));
                $jti = (string)($payload['jti'] ?? '');
                $exp = (int)($payload['exp'] ?? 0);
                $userEmail = (string)($payload['email'] ?? '');

                if ($jti !== '' && $exp > time() && $userEmail !== '') {
                    $user = $this->users->findOneBy(['email' => $userEmail]);
                    if ($user) {
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
                // ignore token decode error on logout
            }
        }

        $this->em->flush();
        return $this->json(['message' => 'logged_out']);
    }
}
