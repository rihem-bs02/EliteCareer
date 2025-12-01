<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\JobRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobRepository::class)]
#[ORM\Table(name: 'jobs')]
#[ORM\HasLifecycleCallbacks]
class Job
{
    public const WORK_ONSITE = 'ONSITE';
    public const WORK_HYBRID = 'HYBRID';
    public const WORK_REMOTE = 'REMOTE';

    public const TYPE_FULL_TIME = 'FULL_TIME';
    public const TYPE_PART_TIME = 'PART_TIME';
    public const TYPE_CONTRACT = 'CONTRACT';
    public const TYPE_INTERN = 'INTERN';
    public const TYPE_TEMP = 'TEMP';

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_PUBLISHED = 'PUBLISHED';
    public const STATUS_CLOSED = 'CLOSED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'jobs')]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Company $company;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'postedJobs')]
    #[ORM\JoinColumn(name: 'posted_by_user_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private User $postedBy;

    #[ORM\Column(length: 200)]
    private string $title;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(length: 20, options: ['default' => self::WORK_ONSITE])]
    private string $workMode = self::WORK_ONSITE;

    #[ORM\Column(length: 20, options: ['default' => self::TYPE_FULL_TIME])]
    private string $employmentType = self::TYPE_FULL_TIME;

    // Doctrine type is TEXT, MySQL column forced to LONGTEXT
    #[ORM\Column(type: Types::TEXT, columnDefinition: 'LONGTEXT')]
    private string $description;

    #[ORM\Column(type: Types::TEXT, nullable: true, columnDefinition: 'LONGTEXT')]
    private ?string $requirements = null;

    #[ORM\Column(length: 20, options: ['default' => self::STATUS_DRAFT])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $publishedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Application> */
    #[ORM\OneToMany(mappedBy: 'job', targetEntity: Application::class, cascade: ['remove'])]
    private Collection $applications;

    public function __construct()
    {
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

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function setCompany(Company $c): self
    {
        $this->company = $c;
        return $this;
    }

    public function getPostedBy(): User
    {
        return $this->postedBy;
    }

    public function setPostedBy(User $u): self
    {
        $this->postedBy = $u;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $v): self
    {
        $this->title = trim($v);
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $v): self
    {
        $this->location = $v ? trim($v) : null;
        return $this;
    }

    public function getWorkMode(): string
    {
        return $this->workMode;
    }

    public function setWorkMode(string $v): self
    {
        $this->workMode = $v;
        return $this;
    }

    public function getEmploymentType(): string
    {
        return $this->employmentType;
    }

    public function setEmploymentType(string $v): self
    {
        $this->employmentType = $v;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $v): self
    {
        $this->description = $v;
        return $this;
    }

    public function getRequirements(): ?string
    {
        return $this->requirements;
    }

    public function setRequirements(?string $v): self
    {
        $this->requirements = $v;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $v): self
    {
        $this->status = $v;
        return $this;
    }

    public function getPublishedAt(): ?\DateTimeInterface
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeInterface $d): self
    {
        $this->publishedAt = $d;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, Application> */
    public function getApplications(): Collection
    {
        return $this->applications;
    }
}
