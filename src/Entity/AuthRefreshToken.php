<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\AuthRefreshTokenRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuthRefreshTokenRepository::class)]
#[ORM\Table(name: 'auth_refresh_tokens')]
#[ORM\UniqueConstraint(name: 'uq_refresh_token_hash', columns: ['token_hash'])]
class AuthRefreshToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'token_hash', length: 64)]
    private string $tokenHash;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $issuedAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $revokedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $deviceLabel = null;

    public function __construct()
    {
        $this->issuedAt = new \DateTimeImmutable('now');
    }

    public function getId(): ?string { return $this->id; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $u): self { $this->user = $u; return $this; }

    public function getTokenHash(): string { return $this->tokenHash; }
    public function setTokenHash(string $v): self { $this->tokenHash = strtolower($v); return $this; }

    public function getIssuedAt(): \DateTimeImmutable { return $this->issuedAt; }
    public function setIssuedAt(\DateTimeImmutable $d): self { $this->issuedAt = $d; return $this; }

    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function setExpiresAt(\DateTimeImmutable $d): self { $this->expiresAt = $d; return $this; }

    public function getRevokedAt(): ?\DateTimeInterface { return $this->revokedAt; }
    public function setRevokedAt(?\DateTimeInterface $d): self { $this->revokedAt = $d; return $this; }

    public function getUserAgent(): ?string { return $this->userAgent; }
    public function setUserAgent(?string $v): self { $this->userAgent = $v; return $this; }

    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function setIpAddress(?string $v): self { $this->ipAddress = $v; return $this; }

    public function getDeviceLabel(): ?string { return $this->deviceLabel; }
    public function setDeviceLabel(?string $v): self { $this->deviceLabel = $v; return $this; }
}
