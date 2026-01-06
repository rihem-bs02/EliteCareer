<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\MatchResultRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MatchResultRepository::class)]
#[ORM\Table(name: 'match_results')]
#[ORM\HasLifecycleCallbacks]
class MatchResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Application::class, inversedBy: 'matchResults')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Application $application;

    #[ORM\Column(length: 100)]
    private string $engineName = 'gemini_rag_v1';

    #[ORM\Column(length: 50)]
    private string $decision;

    #[ORM\Column(type: Types::INTEGER)]
    private int $overallScore;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $scores = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $rawPayload = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable('now');
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getApplication(): Application
    {
        return $this->application;
    }

    public function setApplication(Application $application): self
    {
        $this->application = $application;
        return $this;
    }

    public function getEngineName(): string
    {
        return $this->engineName;
    }

    public function setEngineName(string $engineName): self
    {
        $this->engineName = $engineName;
        return $this;
    }

    public function getDecision(): string
    {
        return $this->decision;
    }

    public function setDecision(string $decision): self
    {
        $this->decision = $decision;
        return $this;
    }

    public function getOverallScore(): int
    {
        return $this->overallScore;
    }

    public function setOverallScore(int $overallScore): self
    {
        $this->overallScore = $overallScore;
        return $this;
    }

    public function getScores(): ?array
    {
        return $this->scores;
    }

    public function setScores(?array $scores): self
    {
        $this->scores = $scores;
        return $this;
    }

    public function getRawPayload(): ?array
    {
        return $this->rawPayload;
    }

    public function setRawPayload(?array $rawPayload): self
    {
        $this->rawPayload = $rawPayload;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
