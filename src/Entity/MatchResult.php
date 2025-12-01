<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\MatchResultRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MatchResultRepository::class)]
#[ORM\Table(name: 'match_results')]
#[ORM\UniqueConstraint(name: 'uq_match_app_algo', columns: ['application_id', 'algorithm_version'])]
#[ORM\HasLifecycleCallbacks]
class MatchResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Application::class, inversedBy: 'matchResults')]
    #[ORM\JoinColumn(name: 'application_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Application $application;

    #[ORM\Column(length: 50, options: ['default' => 'v1'])]
    private string $algorithmVersion = 'v1';

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $matchScore = '0.00';

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $matchedKeywords = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $computedAt;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->computedAt = new \DateTimeImmutable('now');
    }

    public function getId(): ?string { return $this->id; }

    public function getApplication(): Application { return $this->application; }
    public function setApplication(Application $a): self { $this->application = $a; return $this; }

    public function getAlgorithmVersion(): string { return $this->algorithmVersion; }
    public function setAlgorithmVersion(string $v): self { $this->algorithmVersion = trim($v); return $this; }

    public function getMatchScore(): float { return (float)$this->matchScore; }
    public function setMatchScore(float $v): self { $this->matchScore = number_format($v, 2, '.', ''); return $this; }

    public function getMatchedKeywords(): ?array { return $this->matchedKeywords; }
    public function setMatchedKeywords(?array $v): self { $this->matchedKeywords = $v; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): self { $this->notes = $v; return $this; }

    public function getComputedAt(): \DateTimeImmutable { return $this->computedAt; }
}
