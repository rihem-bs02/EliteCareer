<?php
declare(strict_types=1);

namespace App\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\Uid\Uuid;

final class JwtService
{
    public function __construct(
        private readonly string $secret,
        private readonly string $issuer,
        private readonly string $audience,
        private readonly int $accessTtlSeconds,
    ) {}

    /**
     * @return array{token:string,jti:string,expiresAt:int}
     */
    public function createAccessToken(string $userId, string $email, array $roles): array
    {
        $now = time();
        $jti = Uuid::v4()->toRfc4122();
        $exp = $now + $this->accessTtlSeconds;

        $payload = [
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $exp,
            'jti' => $jti,

            // identity
            'sub' => $userId,
            'email' => $email,
            'roles' => $roles,
            'typ' => 'access',
        ];

        $jwt = JWT::encode($payload, $this->secret, 'HS256');

        return ['token' => $jwt, 'jti' => $jti, 'expiresAt' => $exp];
    }

    /**
     * @return array<string,mixed>
     */
    public function decodeAndVerify(string $jwt): array
    {
        $decoded = (array) JWT::decode($jwt, new Key($this->secret, 'HS256'));

        // extra checks
        if (($decoded['iss'] ?? null) !== $this->issuer) {
            throw new \RuntimeException('Invalid token issuer.');
        }
        if (($decoded['aud'] ?? null) !== $this->audience) {
            throw new \RuntimeException('Invalid token audience.');
        }

        return $decoded;
    }
}
