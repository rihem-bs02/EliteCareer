<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Resume;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ResumeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Resume::class);
    }

    // Méthodes custom éventuelles
}
