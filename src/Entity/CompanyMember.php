<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\CompanyMemberRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompanyMemberRepository::class)]
#[ORM\Table(name: 'company_members')]
#[ORM\UniqueConstraint(name: 'uq_company_user', columns: ['company_id', 'user_id'])]
#[ORM\HasLifecycleCallbacks]
class CompanyMember
{
    public const ROLE_HR = 'HR';
    public const ROLE_COMPANY_ADMIN = 'COMPANY_ADMIN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'members')]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Company $company;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'companyMemberships')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 20, options: ['default' => self::ROLE_HR])]
    private string $roleInCompany = self::ROLE_HR;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable('now');
    }

    public function getId(): ?string { return $this->id; }

    public function getCompany(): Company { return $this->company; }
    public function setCompany(Company $company): self { $this->company = $company; return $this; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }

    public function getRoleInCompany(): string { return $this->roleInCompany; }
    public function setRoleInCompany(string $role): self { $this->roleInCompany = $role; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
