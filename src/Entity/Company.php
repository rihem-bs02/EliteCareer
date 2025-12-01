<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[ORM\Table(name: 'companies')]
#[ORM\UniqueConstraint(name: 'uq_company_name', columns: ['name'])]
#[ORM\HasLifecycleCallbacks]
class Company
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\Column(length: 200)]
    private string $name;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $industry = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, CompanyMember> */
    #[ORM\OneToMany(mappedBy: 'company', targetEntity: CompanyMember::class, cascade: ['persist', 'remove'])]
    private Collection $members;

    /** @var Collection<int, Job> */
    #[ORM\OneToMany(mappedBy: 'company', targetEntity: Job::class, cascade: ['remove'])]
    private Collection $jobs;

    public function __construct()
    {
        $this->members = new ArrayCollection();
        $this->jobs = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable('now');
    }

    public function getId(): ?string { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = trim($name); return $this; }

    public function getWebsite(): ?string { return $this->website; }
    public function setWebsite(?string $website): self { $this->website = $website ? trim($website) : null; return $this; }

    public function getIndustry(): ?string { return $this->industry; }
    public function setIndustry(?string $industry): self { $this->industry = $industry ? trim($industry) : null; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @return Collection<int, CompanyMember> */
    public function getMembers(): Collection { return $this->members; }

    /** @return Collection<int, Job> */
    public function getJobs(): Collection { return $this->jobs; }
}
