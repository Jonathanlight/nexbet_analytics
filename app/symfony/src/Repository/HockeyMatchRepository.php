<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\HockeyMatch;
use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HockeyMatch>
 */
class HockeyMatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HockeyMatch::class);
    }

    /**
     * Trouve les matchs d'aujourd'hui avec eager loading.
     *
     * @return HockeyMatch[]
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
     * Trouve les matchs a venir avec eager loading.
     *
     * @return HockeyMatch[]
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
     * Compte les matchs a venir.
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
     * Trouve les matchs par date.
     *
     * @return HockeyMatch[]
     */
    public function findByDate(\DateTimeInterface $date): array
    {
        $start = \DateTimeImmutable::createFromInterface($date)->setTime(0, 0, 0);
        $end = \DateTimeImmutable::createFromInterface($date)->setTime(23, 59, 59);

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
     * Trouve un match par equipes et date.
     */
    public function findMatchByTeamsAndDate(string $homeTeam, string $awayTeam, \DateTimeInterface $date): ?HockeyMatch
    {
        $start = \DateTimeImmutable::createFromInterface($date)->modify('-1 hour');
        $end = \DateTimeImmutable::createFromInterface($date)->modify('+1 hour');

        return $this->createQueryBuilder('m')
            ->leftJoin('m.homeTeam', 'ht')
            ->leftJoin('m.awayTeam', 'at')
            ->where('ht.name = :homeTeam')
            ->andWhere('at.name = :awayTeam')
            ->andWhere('m.matchDate >= :start')
            ->andWhere('m.matchDate <= :end')
            ->setParameter('homeTeam', $homeTeam)
            ->setParameter('awayTeam', $awayTeam)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les matchs a venir pour une equipe.
     *
     * @return HockeyMatch[]
     */
    public function findUpcomingMatchesByTeam(Team $team, int $limit = 5): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('m')
            ->select('m', 'ht', 'at')
            ->leftJoin('m.homeTeam', 'ht')
            ->leftJoin('m.awayTeam', 'at')
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
     * Trouve les matchs recents d'une equipe.
     *
     * @return HockeyMatch[]
     */
    public function findRecentMatchesByTeam(Team $team, int $limit = 10): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('m')
            ->select('m', 'ht', 'at')
            ->leftJoin('m.homeTeam', 'ht')
            ->leftJoin('m.awayTeam', 'at')
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
     * Trouve les confrontations directes.
     *
     * @return HockeyMatch[]
     */
    public function findHeadToHead(Team $team1, Team $team2, int $limit = 10): array
    {
        return $this->createQueryBuilder('m')
            ->select('m', 'ht', 'at')
            ->leftJoin('m.homeTeam', 'ht')
            ->leftJoin('m.awayTeam', 'at')
            ->where('(m.homeTeam = :team1 AND m.awayTeam = :team2) OR (m.homeTeam = :team2 AND m.awayTeam = :team1)')
            ->andWhere('m.status = :status')
            ->setParameter('team1', $team1)
            ->setParameter('team2', $team2)
            ->setParameter('status', 'finished')
            ->orderBy('m.matchDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques moyennes d'une equipe.
     */
    public function getTeamAverageStats(Team $team, int $limit = 10): array
    {
        $matches = $this->findRecentMatchesByTeam($team, $limit);

        if (empty($matches)) {
            return [
                'matches_played' => 0,
                'avg_goals_scored' => 2.5,
                'avg_goals_conceded' => 2.5,
                'wins' => 0,
                'losses' => 0,
                'overtime_wins' => 0,
                'overtime_losses' => 0,
            ];
        }

        $goalsScored = 0;
        $goalsConceded = 0;
        $wins = 0;
        $losses = 0;
        $otWins = 0;
        $otLosses = 0;

        foreach ($matches as $match) {
            $isHome = $match->getHomeTeam()->getId() === $team->getId();
            $homeScore = $match->getHomeFinalScore() ?? 0;
            $awayScore = $match->getAwayFinalScore() ?? 0;

            if ($isHome) {
                $goalsScored += $homeScore;
                $goalsConceded += $awayScore;
                if ($homeScore > $awayScore) {
                    if ($match->hadOvertime() || $match->hadShootout()) {
                        ++$otWins;
                    } else {
                        ++$wins;
                    }
                } else {
                    if ($match->hadOvertime() || $match->hadShootout()) {
                        ++$otLosses;
                    } else {
                        ++$losses;
                    }
                }
            } else {
                $goalsScored += $awayScore;
                $goalsConceded += $homeScore;
                if ($awayScore > $homeScore) {
                    if ($match->hadOvertime() || $match->hadShootout()) {
                        ++$otWins;
                    } else {
                        ++$wins;
                    }
                } else {
                    if ($match->hadOvertime() || $match->hadShootout()) {
                        ++$otLosses;
                    } else {
                        ++$losses;
                    }
                }
            }
        }

        $count = count($matches);

        return [
            'matches_played' => $count,
            'avg_goals_scored' => round($goalsScored / $count, 2),
            'avg_goals_conceded' => round($goalsConceded / $count, 2),
            'wins' => $wins,
            'losses' => $losses,
            'overtime_wins' => $otWins,
            'overtime_losses' => $otLosses,
        ];
    }

    /**
     * Trouve les matchs terminés depuis une date donnée (pour l'entraînement ML).
     *
     * @return HockeyMatch[]
     */
    public function findFinishedMatchesSince(\DateTimeInterface $startDate): array
    {
        return $this->createQueryBuilder('m')
            ->select('m', 'ht', 'at')
            ->leftJoin('m.homeTeam', 'ht')
            ->leftJoin('m.awayTeam', 'at')
            ->where('m.matchDate >= :startDate')
            ->andWhere('m.status = :status')
            ->andWhere('m.homeScore IS NOT NULL OR m.homeFinalScore IS NOT NULL')
            ->setParameter('startDate', $startDate)
            ->setParameter('status', 'finished')
            ->orderBy('m.matchDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
