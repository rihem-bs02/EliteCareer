<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuthAccessTokenBlacklist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class AuthAccessTokenBlacklistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthAccessTokenBlacklist::class);
    }

    public function isBlacklisted(string $jti): bool
    {
        $now = new \DateTimeImmutable('now');

        $qb = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.jti = :jti')
            ->andWhere('b.expiresAt > :now')
            ->setParameter('jti', $jti)
            ->setParameter('now', $now);

        return (int)$qb->getQuery()->getSingleScalarResult() > 0;
    }
}
