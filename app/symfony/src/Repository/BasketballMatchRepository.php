<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BasketballMatch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BasketballMatch>
 */
class BasketballMatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BasketballMatch::class);
    }

    /**
     * Trouve les matchs d'aujourd'hui.
     */
    public function findTodayMatches(): array
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = new \DateTimeImmutable('tomorrow');

        return $this->createQueryBuilder('m')
            ->where('m.matchDate >= :today')
            ->andWhere('m.matchDate < :tomorrow')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->orderBy('m.matchDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les matchs par date.
     */
    public function findByDate(\DateTimeInterface $date): array
    {
        $start = (clone $date)->setTime(0, 0, 0);
        $end = (clone $date)->setTime(23, 59, 59);

        return $this->createQueryBuilder('m')
            ->where('m.matchDate >= :start')
            ->andWhere('m.matchDate <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('m.matchDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les matchs à venir pour une équipe.
     */
    public function findUpcomingMatchesByTeam(object $team, int $limit = 5): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('m')
            ->where('m.homeTeam = :team OR m.awayTeam = :team')
            ->andWhere('m.matchDate > :now')
            ->setParameter('team', $team)
            ->setParameter('now', $now)
            ->orderBy('m.matchDate', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les matchs récents d'une équipe.
     */
    public function findRecentMatchesByTeam(object $team, int $limit = 10): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('m')
            ->where('m.homeTeam = :team OR m.awayTeam = :team')
            ->andWhere('m.matchDate < :now')
            ->andWhere('m.status = :status')
            ->setParameter('team', $team)
            ->setParameter('now', $now)
            ->setParameter('status', 'finished')
            ->orderBy('m.matchDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
