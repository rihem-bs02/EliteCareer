<?php
declare(strict_types=1);

namespace App\Security;

use App\Repository\AuthAccessTokenBlacklistRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class JwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly JwtService $jwt,
        private readonly AuthAccessTokenBlacklistRepository $blacklistRepo,
    ) {}

public function supports(Request $request): ?bool
{
    $path = $request->getPathInfo();

    // public routes
    if (str_starts_with($path, '/api/auth/') || $path === '/login' || $path === '/register') {
        return false;
    }

    // protect these routes ALWAYS (even if token missing)
    return str_starts_with($path, '/api/') || str_starts_with($path, '/dashboard');
}


    public function authenticate(Request $request): SelfValidatingPassport
    {
       $auth = $request->headers->get('Authorization', '');
    $token = null;

    if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
        $token = trim($m[1]);
    } else {
        $token = (string) $request->cookies->get('access_token', '');
    }

    if ($token === '') {
        throw new AuthenticationException('Missing token.');
    }

    $payload = $this->jwt->decodeAndVerify($token);
        if (($payload['typ'] ?? null) !== 'access') {
            throw new AuthenticationException('Not an access token.');
        }

        $jti = (string)($payload['jti'] ?? '');
        if ($jti === '') {
            throw new AuthenticationException('Missing token jti.');
        }

        if ($this->blacklistRepo->isBlacklisted($jti)) {
            throw new AuthenticationException('Token revoked.');
        }

        $email = (string)($payload['email'] ?? '');
        if ($email === '') {
            throw new AuthenticationException('Missing token email.');
        }

        // Put payload in request for controllers if needed
        $request->attributes->set('jwt_payload', $payload);

        return new SelfValidatingPassport(new UserBadge($email));
    }

    public function onAuthenticationSuccess(Request $request, $token, string $firewallName): ?JsonResponse
    {
        return null; // continue request
    }

public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?\Symfony\Component\HttpFoundation\Response
{
    if (str_starts_with($request->getPathInfo(), '/dashboard')) {
        return new \Symfony\Component\HttpFoundation\RedirectResponse('/login');
    }

    return new \Symfony\Component\HttpFoundation\JsonResponse([
        'error' => 'unauthorized',
        'message' => $exception->getMessage(),
    ], 401);
}
}