<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\ResumeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ResumeRepository::class)]
#[ORM\Table(name: 'resumes')]
#[ORM\UniqueConstraint(name: 'uq_resume_sha', columns: ['candidate_user_id', 'sha256'])]
#[ORM\HasLifecycleCallbacks]
class Resume
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'resumes')]
    #[ORM\JoinColumn(name: 'candidate_user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $candidate;

    #[ORM\Column(length: 255)]
    private string $originalFilename;

    #[ORM\Column(length: 500)]
    private string $storagePath;

    #[ORM\Column(length: 100, options: ['default' => 'application/pdf'])]
    private string $mimeType = 'application/pdf';

    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private string $fileSizeBytes;

    #[ORM\Column(length: 64)]
    private string $sha256;

    // Doctrine doesn't know "longtext" as a type; use TEXT + force MySQL LONGTEXT
    #[ORM\Column(type: Types::TEXT, nullable: true, columnDefinition: 'LONGTEXT')]
    private ?string $extractedText = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $parsedAt = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => 0])]
    private bool $isDefault = false;

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

    public function getCandidate(): User
    {
        return $this->candidate;
    }

    public function setCandidate(User $u): self
    {
        $this->candidate = $u;
        return $this;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $v): self
    {
        $this->originalFilename = $v;
        return $this;
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function setStoragePath(string $v): self
    {
        $this->storagePath = $v;
        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $v): self
    {
        $this->mimeType = $v;
        return $this;
    }

    public function getFileSizeBytes(): int
    {
        return (int) $this->fileSizeBytes;
    }

    public function setFileSizeBytes(int $v): self
    {
        $this->fileSizeBytes = (string) $v;
        return $this;
    }

    public function getSha256(): string
    {
        return $this->sha256;
    }

    public function setSha256(string $v): self
    {
        $this->sha256 = strtolower($v);
        return $this;
    }

    public function getExtractedText(): ?string
    {
        return $this->extractedText;
    }

    public function setExtractedText(?string $v): self
    {
        $this->extractedText = $v;
        return $this;
    }

    public function getParsedAt(): ?\DateTimeInterface
    {
        return $this->parsedAt;
    }

    public function setParsedAt(?\DateTimeInterface $d): self
    {
        $this->parsedAt = $d;
        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $v): self
    {
        $this->isDefault = $v;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
