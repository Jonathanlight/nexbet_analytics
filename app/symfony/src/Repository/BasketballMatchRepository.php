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
     * Trouve les matchs d'aujourd'hui avec eager loading.
     *
     * @return BasketballMatch[]
     */
    public function findTodayMatches(): array
    {
        $now = new \DateTimeImmutable();
        $tomorrow = new \DateTimeImmutable('tomorrow');

        return $this->createQueryBuilder('m')
            ->select('m', 'ht', 'at')
            ->leftJoin('m.homeTeam', 'ht')
            ->leftJoin('m.awayTeam', 'at')
            ->where('m.matchDate > :now')
            ->andWhere('m.matchDate < :tomorrow')
            ->andWhere('m.status = :status')
            ->setParameter('now', $now)
            ->setParameter('tomorrow', $tomorrow)
            ->setParameter('status', 'scheduled')
            ->orderBy('m.matchDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les matchs à venir avec eager loading.
     *
     * @return BasketballMatch[]
     */
    public function findUpcomingMatches(int $limit = 50): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('m')
            ->select('m', 'ht', 'at')
            ->leftJoin('m.homeTeam', 'ht')
            ->leftJoin('m.awayTeam', 'at')
            ->where('m.matchDate > :now')
            ->andWhere('m.status = :status')
            ->setParameter('now', $now)
            ->setParameter('status', 'scheduled')
            ->orderBy('m.matchDate', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les matchs à venir.
     */
    public function countUpcomingMatches(): int
    {
        $now = new \DateTimeImmutable();

        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.matchDate > :now')
            ->andWhere('m.status = :status')
            ->setParameter('now', $now)
            ->setParameter('status', 'scheduled')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les matchs par date avec eager loading.
     */
    public function findByDate(\DateTimeInterface $date): array
    {
        $start = (clone $date)->setTime(0, 0, 0);
        $end = (clone $date)->setTime(23, 59, 59);

        return $this->createQueryBuilder('m')
            ->select('m', 'ht', 'at')
            ->leftJoin('m.homeTeam', 'ht')
            ->leftJoin('m.awayTeam', 'at')
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

    /**
     * Trouve les matchs terminés depuis une date donnée (pour l'entraînement ML).
     *
     * @return BasketballMatch[]
     */
    public function findFinishedMatchesSince(\DateTimeInterface $startDate): array
    {
        return $this->createQueryBuilder('m')
            ->select('m', 'ht', 'at')
            ->leftJoin('m.homeTeam', 'ht')
            ->leftJoin('m.awayTeam', 'at')
            ->where('m.matchDate >= :startDate')
            ->andWhere('m.status = :status')
            ->andWhere('m.homeScore IS NOT NULL')
            ->andWhere('m.awayScore IS NOT NULL')
            ->setParameter('startDate', $startDate)
            ->setParameter('status', 'finished')
            ->orderBy('m.matchDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
