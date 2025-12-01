<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\AuthAccessTokenBlacklistRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuthAccessTokenBlacklistRepository::class)]
#[ORM\Table(name: 'auth_access_token_blacklist')]
#[ORM\UniqueConstraint(name: 'uq_blacklist_jti', columns: ['jti'])]
class AuthAccessTokenBlacklist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 36)]
    private string $jti;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $revokedAt;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reason = null;

    public function __construct()
    {
        $this->revokedAt = new \DateTimeImmutable('now');
    }

    public function getId(): ?string { return $this->id; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $u): self { $this->user = $u; return $this; }

    public function getJti(): string { return $this->jti; }
    public function setJti(string $v): self { $this->jti = $v; return $this; }

    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function setExpiresAt(\DateTimeImmutable $d): self { $this->expiresAt = $d; return $this; }

    public function getRevokedAt(): \DateTimeImmutable { return $this->revokedAt; }
    public function setRevokedAt(\DateTimeImmutable $d): self { $this->revokedAt = $d; return $this; }

    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $v): self { $this->reason = $v; return $this; }
}
