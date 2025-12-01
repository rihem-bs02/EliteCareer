<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uq_users_email', columns: ['email'])]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_CANDIDATE = 'ROLE_CANDIDATE';
    public const ROLE_HR = 'ROLE_HR';
    public const ROLE_ADMIN = 'ROLE_ADMIN';

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_SUSPENDED = 'SUSPENDED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $email;

    #[ORM\Column(length: 255)]
    private string $passwordHash;

    #[ORM\Column(type: 'json')]
    private array $roles = [self::ROLE_CANDIDATE];

    #[ORM\Column(length: 20, options: ['default' => self::STATUS_ACTIVE])]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastLoginAt = null;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: CandidateProfile::class, cascade: ['persist', 'remove'])]
    private ?CandidateProfile $candidateProfile = null;

    /** @var Collection<int, Resume> */
    #[ORM\OneToMany(mappedBy: 'candidate', targetEntity: Resume::class, cascade: ['persist', 'remove'])]
    private Collection $resumes;

    /** @var Collection<int, CompanyMember> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: CompanyMember::class, cascade: ['persist', 'remove'])]
    private Collection $companyMemberships;

    /** @var Collection<int, Job> */
    #[ORM\OneToMany(mappedBy: 'postedBy', targetEntity: Job::class)]
    private Collection $postedJobs;

    /** @var Collection<int, Application> */
    #[ORM\OneToMany(mappedBy: 'candidate', targetEntity: Application::class)]
    private Collection $applications;

    public function __construct()
    {
        $this->resumes = new ArrayCollection();
        $this->companyMemberships = new ArrayCollection();
        $this->postedJobs = new ArrayCollection();
        $this->applications = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable('now');
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now');
    }

    public function getId(): ?string { return $this->id; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = mb_strtolower(trim($email)); return $this; }

    public function getPassword(): string { return $this->passwordHash; }
    public function setPasswordHash(string $hash): self { $this->passwordHash = $hash; return $this; }

    public function getUserIdentifier(): string { return $this->email; }
    public function eraseCredentials(): void {}

    public function getRoles(): array
    {
        $roles = $this->roles;
        if (!in_array(self::ROLE_CANDIDATE, $roles, true) && !in_array(self::ROLE_HR, $roles, true) && !in_array(self::ROLE_ADMIN, $roles, true)) {
            $roles[] = self::ROLE_CANDIDATE;
        }
        return array_values(array_unique($roles));
    }

    public function setRoles(array $roles): self
    {
        $this->roles = array_values(array_unique($roles));
        return $this;
    }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function getLastLoginAt(): ?\DateTimeInterface { return $this->lastLoginAt; }
    public function setLastLoginAt(?\DateTimeInterface $d): self { $this->lastLoginAt = $d; return $this; }

    public function getCandidateProfile(): ?CandidateProfile { return $this->candidateProfile; }
    public function setCandidateProfile(?CandidateProfile $profile): self
    {
        $this->candidateProfile = $profile;
        return $this;
    }

    /** @return Collection<int, Resume> */
    public function getResumes(): Collection { return $this->resumes; }

    /** @return Collection<int, CompanyMember> */
    public function getCompanyMemberships(): Collection { return $this->companyMemberships; }

    /** @return Collection<int, Job> */
    public function getPostedJobs(): Collection { return $this->postedJobs; }

    /** @return Collection<int, Application> */
    public function getApplications(): Collection { return $this->applications; }
}
