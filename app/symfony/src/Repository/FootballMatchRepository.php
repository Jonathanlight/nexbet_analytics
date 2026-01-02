<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FootballMatch;
use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FootballMatch>
 */
class FootballMatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FootballMatch::class);
    }

    /**
     * Trouve les matchs d'aujourd'hui.
     *
     * @return FootballMatch[]
     */
    public function findTodayMatches(): array
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = new \DateTimeImmutable('tomorrow');

        return $this->createQueryBuilder('m')
            ->where('m.matchDate >= :today')
            ->andWhere('m.matchDate < :tomorrow')
            ->andWhere('m.status = :status')
            ->orderBy('m.matchDate', 'ASC')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->setParameter('status', 'scheduled')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les matchs par date.
     *
     * @return FootballMatch[]
     */
    public function findByDate(\DateTimeImmutable $date): array
    {
        $startOfDay = $date->setTime(0, 0, 0);
        $endOfDay = $date->setTime(23, 59, 59);

        return $this->createQueryBuilder('m')
            ->where('m.matchDate >= :start')
            ->andWhere('m.matchDate <= :end')
            ->orderBy('m.matchDate', 'ASC')
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les matchs à venir d'une équipe.
     *
     * @return FootballMatch[]
     */
    public function findUpcomingMatchesByTeam(Team $team, int $limit = 5): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('m')
            ->where('m.homeTeam = :team OR m.awayTeam = :team')
            ->andWhere('m.matchDate > :now')
            ->andWhere('m.status = :status')
            ->orderBy('m.matchDate', 'ASC')
            ->setParameter('team', $team)
            ->setParameter('now', $now)
            ->setParameter('status', 'scheduled')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les derniers matchs d'une équipe.
     *
     * @return FootballMatch[]
     */
    public function findRecentMatchesByTeam(Team $team, int $limit = 5): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('m')
            ->where('m.homeTeam = :team OR m.awayTeam = :team')
            ->andWhere('m.matchDate < :now')
            ->andWhere('m.status = :status')
            ->orderBy('m.matchDate', 'DESC')
            ->setParameter('team', $team)
            ->setParameter('now', $now)
            ->setParameter('status', 'finished')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les confrontations directes entre deux équipes.
     *
     * @return FootballMatch[]
     */
    public function findHeadToHead(Team $team1, Team $team2, int $limit = 10): array
    {
        return $this->createQueryBuilder('m')
            ->where('(m.homeTeam = :team1 AND m.awayTeam = :team2) OR (m.homeTeam = :team2 AND m.awayTeam = :team1)')
            ->andWhere('m.status = :status')
            ->orderBy('m.matchDate', 'DESC')
            ->setParameter('team1', $team1)
            ->setParameter('team2', $team2)
            ->setParameter('status', 'finished')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les matchs par championnat et date.
     *
     * @return FootballMatch[]
     */
    public function findByLeagueAndDate(string $league, \DateTimeImmutable $date): array
    {
        $startOfDay = $date->setTime(0, 0, 0);
        $endOfDay = $date->setTime(23, 59, 59);

        return $this->createQueryBuilder('m')
            ->where('m.league = :league')
            ->andWhere('m.matchDate >= :start')
            ->andWhere('m.matchDate <= :end')
            ->orderBy('m.matchDate', 'ASC')
            ->setParameter('league', $league)
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les matchs avec prédictions à haute confiance.
     *
     * @return FootballMatch[]
     */
    public function findMatchesWithHighConfidencePredictions(float $minConfidence = 85.0): array
    {
        $today = new \DateTimeImmutable('today');

        return $this->createQueryBuilder('m')
            ->innerJoin('m.predictions', 'p')
            ->where('m.matchDate >= :today')
            ->andWhere('m.status = :status')
            ->andWhere('p.confidence >= :minConfidence')
            ->orderBy('p.confidence', 'DESC')
            ->addOrderBy('m.matchDate', 'ASC')
            ->setParameter('today', $today)
            ->setParameter('status', 'scheduled')
            ->setParameter('minConfidence', $minConfidence)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les paris sûrs du jour.
     *
     * @return FootballMatch[]
     */
    public function findSafeBetsToday(): array
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = new \DateTimeImmutable('tomorrow');

        return $this->createQueryBuilder('m')
            ->innerJoin('m.predictions', 'p')
            ->where('m.matchDate >= :today')
            ->andWhere('m.matchDate < :tomorrow')
            ->andWhere('m.status = :status')
            ->andWhere('p.isSafeBet = :isSafe')
            ->orderBy('p.confidence', 'DESC')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->setParameter('status', 'scheduled')
            ->setParameter('isSafe', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule les statistiques moyennes d'une équipe.
     */
    public function getTeamAverageStats(Team $team, int $lastMatches = 10): array
    {
        $matches = $this->findRecentMatchesByTeam($team, $lastMatches);

        $stats = [
            'avg_goals_scored' => 0,
            'avg_goals_conceded' => 0,
            'avg_corners' => 0,
            'avg_yellow_cards' => 0,
            'avg_xg' => 0,
            'clean_sheets' => 0,
            'btts_count' => 0,
            'matches_played' => count($matches),
        ];

        $count = count($matches);
        if (0 === $count) {
            return $stats;
        }

        foreach ($matches as $match) {
            $isHome = $match->getHomeTeam()->getId() === $team->getId();

            if ($isHome) {
                $stats['avg_goals_scored'] += $match->getHomeScore() ?? 0;
                $stats['avg_goals_conceded'] += $match->getAwayScore() ?? 0;
                $stats['avg_corners'] += $match->getHomeCorners() ?? 0;
                $stats['avg_yellow_cards'] += $match->getHomeYellowCards() ?? 0;
                $stats['avg_xg'] += $match->getHomeXg() ?? 0;

                if (0 === $match->getAwayScore()) {
                    ++$stats['clean_sheets'];
                }
            } else {
                $stats['avg_goals_scored'] += $match->getAwayScore() ?? 0;
                $stats['avg_goals_conceded'] += $match->getHomeScore() ?? 0;
                $stats['avg_corners'] += $match->getAwayCorners() ?? 0;
                $stats['avg_yellow_cards'] += $match->getAwayYellowCards() ?? 0;
                $stats['avg_xg'] += $match->getAwayXg() ?? 0;

                if (0 === $match->getHomeScore()) {
                    ++$stats['clean_sheets'];
                }
            }

            if ($match->getHomeScore() > 0 && $match->getAwayScore() > 0) {
                ++$stats['btts_count'];
            }
        }

        $stats['avg_goals_scored'] = round($stats['avg_goals_scored'] / $count, 2);
        $stats['avg_goals_conceded'] = round($stats['avg_goals_conceded'] / $count, 2);
        $stats['avg_corners'] = round($stats['avg_corners'] / $count, 2);
        $stats['avg_yellow_cards'] = round($stats['avg_yellow_cards'] / $count, 2);
        $stats['avg_xg'] = round($stats['avg_xg'] / $count, 2);
        $stats['btts_percentage'] = round(($stats['btts_count'] / $count) * 100, 2);

        return $stats;
    }
}
