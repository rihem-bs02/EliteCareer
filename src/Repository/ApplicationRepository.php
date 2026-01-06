<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Application;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Application>
 */
class ApplicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Application::class);
    }

    // Tu pourras ajouter des méthodes custom ici plus tard, par ex :
    // public function findOneByJobAndCandidate(Job $job, User $candidate): ?Application
    // {
    //     return $this->createQueryBuilder('a')
    //         ->andWhere('a.job = :job')
    //         ->andWhere('a.candidate = :candidate')
    //         ->setParameter('job', $job)
    //         ->setParameter('candidate', $candidate)
    //         ->getQuery()
    //         ->getOneOrNullResult();
    // }
}
