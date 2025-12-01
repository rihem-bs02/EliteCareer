<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\CandidateProfileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CandidateProfileRepository::class)]
#[ORM\Table(name: 'candidate_profiles')]
#[ORM\HasLifecycleCallbacks]
class CandidateProfile
{
    #[ORM\Id]
    #[ORM\OneToOne(inversedBy: 'candidateProfile', targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $headline = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now');
    }

    public function getUser(): User { return $this->user; }

    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(?string $v): self { $this->firstName = $v ? trim($v) : null; return $this; }

    public function getLastName(): ?string { return $this->lastName; }
    public function setLastName(?string $v): self { $this->lastName = $v ? trim($v) : null; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $v): self { $this->phone = $v ? trim($v) : null; return $this; }

    public function getLocation(): ?string { return $this->location; }
    public function setLocation(?string $v): self { $this->location = $v ? trim($v) : null; return $this; }

    public function getHeadline(): ?string { return $this->headline; }
    public function setHeadline(?string $v): self { $this->headline = $v ? trim($v) : null; return $this; }

    public function getSummary(): ?string { return $this->summary; }
    public function setSummary(?string $v): self { $this->summary = $v; return $this; }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
