<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuthRefreshToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuthRefreshToken>
 *
 * @method AuthRefreshToken|null find($id, $lockMode = null, $lockVersion = null)
 * @method AuthRefreshToken|null findOneBy(array $criteria, array $orderBy = null)
 * @method AuthRefreshToken[]    findAll()
 * @method AuthRefreshToken[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class AuthRefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthRefreshToken::class);
    }
}
