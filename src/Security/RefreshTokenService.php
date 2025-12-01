<?php
declare(strict_types=1);

namespace App\Security;

use App\Entity\AuthRefreshToken;
use App\Entity\User;
use App\Repository\AuthRefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

final class RefreshTokenService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuthRefreshTokenRepository $refreshRepo,
        private readonly int $refreshTtlDays,
    ) {}

    /**
     * @return array{refreshToken:string,expiresAt:\DateTimeImmutable}
     */
    public function issue(User $user, ?string $userAgent = null, ?string $ip = null, ?string $deviceLabel = null): array
    {
        $plain = $this->generateToken();
        $hash = hash('sha256', $plain);
        $expiresAt = (new \DateTimeImmutable('now'))->modify('+' . $this->refreshTtlDays . ' days');

        $rt = new AuthRefreshToken();
        $rt->setUser($user)
            ->setTokenHash($hash)
            ->setIssuedAt(new \DateTimeImmutable('now'))
            ->setExpiresAt($expiresAt)
            ->setUserAgent($userAgent)
            ->setIpAddress($ip)
            ->setDeviceLabel($deviceLabel);

        $this->em->persist($rt);
        $this->em->flush();

        return ['refreshToken' => $plain, 'expiresAt' => $expiresAt];
    }

    public function rotate(string $plainRefreshToken): AuthRefreshToken
    {
        $hash = hash('sha256', $plainRefreshToken);
        $rt = $this->refreshRepo->findOneBy(['tokenHash' => $hash]);

        if (!$rt) {
            throw new \RuntimeException('Invalid refresh token.');
        }
        if ($rt->getRevokedAt() !== null) {
            throw new \RuntimeException('Refresh token revoked.');
        }
        if ($rt->getExpiresAt() <= new \DateTimeImmutable('now')) {
            throw new \RuntimeException('Refresh token expired.');
        }

        // rotate: revoke old token
        $rt->setRevokedAt(new \DateTimeImmutable('now'));
        $this->em->flush();

        return $rt;
    }

    private function generateToken(): string
    {
        $bytes = random_bytes(64);
        $b64 = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        return $b64; // safe for JSON/headers
    }
}
