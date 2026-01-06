<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\ApplicationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ApplicationRepository::class)]
#[ORM\Table(name: 'applications')]
#[ORM\UniqueConstraint(name: 'uq_job_candidate', columns: ['job_id', 'candidate_user_id'])]
#[ORM\HasLifecycleCallbacks]
class Application
{
    public const STATUS_SUBMITTED            = 'SUBMITTED';
    public const STATUS_IN_REVIEW            = 'IN_REVIEW';
    public const STATUS_SHORTLISTED          = 'SHORTLISTED';
    public const STATUS_REJECTED             = 'REJECTED';
    public const STATUS_HIRED                = 'HIRED';
    public const STATUS_WITHDRAWN            = 'WITHDRAWN';

    // New ones used by your controller / UI
    public const STATUS_ACCEPTED             = 'ACCEPTED';
    public const STATUS_INTERVIEW_SCHEDULED  = 'INTERVIEW_SCHEDULED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Job::class, inversedBy: 'applications')]
    #[ORM\JoinColumn(name: 'job_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Job $job;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'applications')]
    #[ORM\JoinColumn(name: 'candidate_user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $candidate;

    #[ORM\ManyToOne(targetEntity: Resume::class)]
    #[ORM\JoinColumn(name: 'resume_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Resume $resume;

    #[ORM\Column(length: 30, options: ['default' => self::STATUS_SUBMITTED])]
    private string $status = self::STATUS_SUBMITTED;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $coverLetter = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $appliedAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    // ========= NEW INTERVIEW FIELDS =========

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $interviewAt = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $interviewMode = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $interviewLocation = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $interviewNotes = null;

    /** @var Collection<int, MatchResult> */
    #[ORM\OneToMany(mappedBy: 'application', targetEntity: MatchResult::class, cascade: ['persist', 'remove'])]
    private Collection $matchResults;

    public function __construct()
    {
        $this->matchResults = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable('now');
        $this->appliedAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now');
    }

    // ========= BASIC FIELDS =========

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getJob(): Job
    {
        return $this->job;
    }

    public function setJob(Job $job): self
    {
        $this->job = $job;

        return $this;
    }

    public function getCandidate(): User
    {
        return $this->candidate;
    }

    public function setCandidate(User $candidate): self
    {
        $this->candidate = $candidate;

        return $this;
    }

    public function getResume(): Resume
    {
        return $this->resume;
    }

    public function setResume(Resume $resume): self
    {
        $this->resume = $resume;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCoverLetter(): ?string
    {
        return $this->coverLetter;
    }

    public function setCoverLetter(?string $coverLetter): self
    {
        $this->coverLetter = $coverLetter;

        return $this;
    }

    public function getAppliedAt(): \DateTimeImmutable
    {
        return $this->appliedAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // ========= MATCH RESULTS =========

    /**
     * @return Collection<int, MatchResult>
     */
    public function getMatchResults(): Collection
    {
        return $this->matchResults;
    }

    public function addMatchResult(MatchResult $matchResult): self
    {
        if (!$this->matchResults->contains($matchResult)) {
            $this->matchResults[] = $matchResult;
            $matchResult->setApplication($this);
        }

        return $this;
    }

    public function removeMatchResult(MatchResult $matchResult): self
    {
        if ($this->matchResults->removeElement($matchResult)) {
            if ($matchResult->getApplication() === $this) {
                $matchResult->setApplication(null);
            }
        }

        return $this;
    }

    // ========= INTERVIEW GETTERS / SETTERS =========

    public function getInterviewAt(): ?\DateTimeImmutable
    {
        return $this->interviewAt;
    }

    public function setInterviewAt(?\DateTimeImmutable $interviewAt): self
    {
        $this->interviewAt = $interviewAt;

        return $this;
    }

    public function getInterviewMode(): ?string
    {
        return $this->interviewMode;
    }

    public function setInterviewMode(?string $interviewMode): self
    {
        $this->interviewMode = $interviewMode;

        return $this;
    }

    public function getInterviewLocation(): ?string
    {
        return $this->interviewLocation;
    }

    public function setInterviewLocation(?string $interviewLocation): self
    {
        $this->interviewLocation = $interviewLocation;

        return $this;
    }

    public function getInterviewNotes(): ?string
    {
        return $this->interviewNotes;
    }

    public function setInterviewNotes(?string $interviewNotes): self
    {
        $this->interviewNotes = $interviewNotes;

        return $this;
    }
}
