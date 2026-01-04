<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FootballMatch;
use App\Entity\Team;
use App\Enum\MatchStatus;
use App\Service\Search\ElasticsearchInterface;
use App\Service\Search\ElasticsearchService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FootballMatch>
 */
class FootballMatchRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ?ElasticsearchInterface $elasticsearch = null,
    ) {
        parent::__construct($registry, FootballMatch::class);
    }

    /**
     * Trouve les matchs d'aujourd'hui qui n'ont pas encore commencé.
     * Optimisé avec eager loading des relations.
     *
     * @return FootballMatch[]
     */
    public function findTodayMatches(): array
    {
        $now = new \DateTimeImmutable();
        $tomorrow = new \DateTimeImmutable('tomorrow');

        return $this->createQueryBuilder('m')
            ->select('m', 'ht', 'at', 'o')
            ->leftJoin('m.homeTeam', 'ht')
            ->leftJoin('m.awayTeam', 'at')
            ->leftJoin('m.odds', 'o')
            ->where('m.matchDate > :now')
            ->andWhere('m.matchDate < :tomorrow')
            ->andWhere('m.status = :status')
            ->orderBy('m.matchDate', 'ASC')
            ->setParameter('now', $now)
            ->setParameter('tomorrow', $tomorrow)
            ->setParameter('status', MatchStatus::SCHEDULED)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les matchs à venir (pas encore commencés).
     * Optimisé avec eager loading.
     *
     * @return FootballMatch[]
     */
    public function findUpcomingMatches(int $limit = 50): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('m')
            ->select('m', 'ht', 'at', 'o')
            ->leftJoin('m.homeTeam', 'ht')
            ->leftJoin('m.awayTeam', 'at')
            ->leftJoin('m.odds', 'o')
            ->where('m.matchDate > :now')
            ->andWhere('m.status = :status')
            ->orderBy('m.matchDate', 'ASC')
            ->setParameter('now', $now)
            ->setParameter('status', MatchStatus::SCHEDULED)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les matchs avec leurs cotes (optimisé pour l'affichage).
     *
     * @return FootballMatch[]
     */
    public function findMatchesWithOdds(int $limit = 20): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('m')
            ->select('m', 'ht', 'at', 'o')
            ->leftJoin('m.homeTeam', 'ht')
            ->leftJoin('m.awayTeam', 'at')
            ->innerJoin('m.odds', 'o')
            ->where('m.matchDate > :now')
            ->andWhere('m.status = :status')
            ->orderBy('m.matchDate', 'ASC')
            ->setParameter('now', $now)
            ->setParameter('status', MatchStatus::SCHEDULED)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les matchs par date avec eager loading.
     *
     * @return FootballMatch[]
     */
    public function findByDate(\DateTimeInterface $date): array
    {
        // Convertir en DateTimeImmutable si nécessaire
        if (!$date instanceof \DateTimeImmutable) {
            $date = \DateTimeImmutable::createFromInterface($date);
        }

        $startOfDay = $date->setTime(0, 0, 0);
        $endOfDay = $date->setTime(23, 59, 59);

        return $this->createQueryBuilder('m')
            ->select('m', 'ht', 'at')
            ->leftJoin('m.homeTeam', 'ht')
            ->leftJoin('m.awayTeam', 'at')
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
            ->setParameter('status', MatchStatus::SCHEDULED)
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
            ->setParameter('status', MatchStatus::FINISHED)
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
            ->setParameter('status', MatchStatus::FINISHED)
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
            ->setParameter('status', MatchStatus::SCHEDULED)
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
            ->setParameter('status', MatchStatus::SCHEDULED)
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

        // Valeurs par défaut réalistes basées sur les moyennes globales du football
        // (environ 1.3-1.5 buts par équipe par match en moyenne)
        $stats = [
            'avg_goals_scored' => 1.35,   // Moyenne réaliste
            'avg_goals_conceded' => 1.35,
            'avg_corners' => 5.0,
            'avg_yellow_cards' => 1.8,
            'avg_xg' => 1.25,
            'clean_sheets' => 0,
            'btts_count' => 0,
            'matches_played' => count($matches),
            'has_historical_data' => count($matches) > 0,
        ];

        $count = count($matches);
        if (0 === $count) {
            // Essayer d'utiliser le rating Elo pour ajuster les valeurs par défaut
            $eloRating = $team->getEloRating() ?? 1500;
            $eloFactor = ($eloRating - 1500) / 400; // -1 à +1 selon la force

            // Ajuster les buts attendus selon le rating Elo
            $stats['avg_goals_scored'] = round(1.35 + ($eloFactor * 0.5), 2);
            $stats['avg_goals_conceded'] = round(1.35 - ($eloFactor * 0.3), 2);

            // S'assurer que les valeurs restent positives
            $stats['avg_goals_scored'] = max(0.5, $stats['avg_goals_scored']);
            $stats['avg_goals_conceded'] = max(0.5, $stats['avg_goals_conceded']);

            return $stats;
        }

        // Réinitialiser les compteurs pour les calculs
        $totalGoalsScored = 0;
        $totalGoalsConceded = 0;
        $totalCorners = 0;
        $totalYellowCards = 0;
        $totalXg = 0;

        foreach ($matches as $match) {
            $isHome = $match->getHomeTeam()->getId() === $team->getId();

            if ($isHome) {
                $totalGoalsScored += $match->getHomeScore() ?? 0;
                $totalGoalsConceded += $match->getAwayScore() ?? 0;
                $totalCorners += $match->getHomeCorners() ?? 0;
                $totalYellowCards += $match->getHomeYellowCards() ?? 0;
                $totalXg += $match->getHomeXg() ?? 0;

                if (0 === $match->getAwayScore()) {
                    ++$stats['clean_sheets'];
                }
            } else {
                $totalGoalsScored += $match->getAwayScore() ?? 0;
                $totalGoalsConceded += $match->getHomeScore() ?? 0;
                $totalCorners += $match->getAwayCorners() ?? 0;
                $totalYellowCards += $match->getAwayYellowCards() ?? 0;
                $totalXg += $match->getAwayXg() ?? 0;

                if (0 === $match->getHomeScore()) {
                    ++$stats['clean_sheets'];
                }
            }

            if ($match->getHomeScore() > 0 && $match->getAwayScore() > 0) {
                ++$stats['btts_count'];
            }
        }

        $stats['avg_goals_scored'] = round($totalGoalsScored / $count, 2);
        $stats['avg_goals_conceded'] = round($totalGoalsConceded / $count, 2);
        $stats['avg_corners'] = round($totalCorners / $count, 2);
        $stats['avg_yellow_cards'] = round($totalYellowCards / $count, 2);
        $stats['avg_xg'] = $totalXg > 0 ? round($totalXg / $count, 2) : $stats['avg_goals_scored'];
        $stats['btts_percentage'] = round(($stats['btts_count'] / $count) * 100, 2);

        return $stats;
    }

    /**
     * Trouve un match par équipes et date.
     */
    public function findMatchByTeamsAndDate(string $homeTeam, string $awayTeam, \DateTimeInterface $date): ?FootballMatch
    {
        // Convertir en DateTimeImmutable si nécessaire
        if (!$date instanceof \DateTimeImmutable) {
            $date = \DateTimeImmutable::createFromInterface($date);
        }

        $startOfDay = $date->setTime(0, 0, 0);
        $endOfDay = $date->setTime(23, 59, 59);

        return $this->createQueryBuilder('m')
            ->join('m.homeTeam', 'ht')
            ->join('m.awayTeam', 'at')
            ->where('ht.name = :homeTeam')
            ->andWhere('at.name = :awayTeam')
            ->andWhere('m.matchDate >= :startOfDay')
            ->andWhere('m.matchDate <= :endOfDay')
            ->setParameter('homeTeam', $homeTeam)
            ->setParameter('awayTeam', $awayTeam)
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les matchs récents terminés.
     *
     * @return FootballMatch[]
     */
    public function findRecentFinishedMatches(int $limit = 100): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.status = :status')
            ->andWhere('m.homeScore IS NOT NULL')
            ->andWhere('m.awayScore IS NOT NULL')
            ->orderBy('m.matchDate', 'DESC')
            ->setParameter('status', MatchStatus::FINISHED)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtient les statistiques par championnat.
     */
    public function getStatsByLeague(): array
    {
        $qb = $this->createQueryBuilder('m');
        $qb->select('m.league, COUNT(m.id) as total_matches')
            ->groupBy('m.league')
            ->orderBy('total_matches', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte le nombre de matchs à venir.
     */
    public function countUpcomingMatches(): int
    {
        $now = new \DateTimeImmutable();

        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.matchDate > :now')
            ->andWhere('m.status = :status')
            ->setParameter('now', $now)
            ->setParameter('status', MatchStatus::SCHEDULED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les matchs terminés depuis une date donnée (pour l'entraînement ML).
     *
     * @return FootballMatch[]
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
            ->setParameter('status', MatchStatus::FINISHED)
            ->orderBy('m.matchDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // === Méthodes Elasticsearch ===

    /**
     * Recherche full-text des matchs via Elasticsearch.
     * Utilise Doctrine comme fallback si Elasticsearch n'est pas disponible.
     *
     * @return FootballMatch[]
     */
    public function searchMatches(
        ?string $teamQuery = null,
        ?string $league = null,
        ?\DateTimeInterface $dateFrom = null,
        ?\DateTimeInterface $dateTo = null,
        int $limit = 20,
    ): array {
        // Essayer Elasticsearch si disponible
        if ($this->elasticsearch?->isAvailable()) {
            $esResults = $this->searchViaElasticsearch($teamQuery, $league, $dateFrom, $dateTo, $limit);
            if (!empty($esResults)) {
                return $this->hydrateFromElasticsearch($esResults);
            }
        }

        // Fallback vers Doctrine
        return $this->searchViaDoctrine($teamQuery, $league, $dateFrom, $dateTo, $limit);
    }

    /**
     * Recherche via Elasticsearch.
     */
    private function searchViaElasticsearch(
        ?string $teamQuery,
        ?string $league,
        ?\DateTimeInterface $dateFrom,
        ?\DateTimeInterface $dateTo,
        int $limit,
    ): array {
        $must = [];

        if ($teamQuery) {
            $must[] = [
                'multi_match' => [
                    'query' => $teamQuery,
                    'fields' => ['home_team^2', 'away_team^2', 'league'],
                    'type' => 'best_fields',
                    'fuzziness' => 'AUTO',
                ],
            ];
        }

        if ($league) {
            $must[] = ['term' => ['league' => $league]];
        }

        if ($dateFrom || $dateTo) {
            $range = ['match_date' => []];
            if ($dateFrom) {
                $range['match_date']['gte'] = $dateFrom->format('Y-m-d');
            }
            if ($dateTo) {
                $range['match_date']['lte'] = $dateTo->format('Y-m-d');
            }
            $must[] = ['range' => $range];
        }

        $query = empty($must) ? ['match_all' => new \stdClass()] : ['bool' => ['must' => $must]];

        return $this->elasticsearch->search(
            ElasticsearchService::INDEX_FOOTBALL_MATCHES,
            $query,
            0,
            $limit
        );
    }

    /**
     * Recherche via Doctrine (fallback).
     *
     * @return FootballMatch[]
     */
    private function searchViaDoctrine(
        ?string $teamQuery,
        ?string $league,
        ?\DateTimeInterface $dateFrom,
        ?\DateTimeInterface $dateTo,
        int $limit,
    ): array {
        $qb = $this->createQueryBuilder('m')
            ->select('m', 'ht', 'at')
            ->leftJoin('m.homeTeam', 'ht')
            ->leftJoin('m.awayTeam', 'at');

        if ($teamQuery) {
            $qb->andWhere('ht.name LIKE :team OR at.name LIKE :team OR m.league LIKE :team')
                ->setParameter('team', '%'.$teamQuery.'%');
        }

        if ($league) {
            $qb->andWhere('m.league = :league')
                ->setParameter('league', $league);
        }

        if ($dateFrom) {
            $qb->andWhere('m.matchDate >= :dateFrom')
                ->setParameter('dateFrom', $dateFrom);
        }

        if ($dateTo) {
            $qb->andWhere('m.matchDate <= :dateTo')
                ->setParameter('dateTo', $dateTo);
        }

        return $qb->orderBy('m.matchDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Hydrate les résultats Elasticsearch vers des entités FootballMatch.
     *
     * @return FootballMatch[]
     */
    private function hydrateFromElasticsearch(array $esResults): array
    {
        $ids = array_map(fn ($hit) => (int) $hit['id'], $esResults['hits'] ?? []);

        if (empty($ids)) {
            return [];
        }

        return $this->createQueryBuilder('m')
            ->select('m', 'ht', 'at')
            ->leftJoin('m.homeTeam', 'ht')
            ->leftJoin('m.awayTeam', 'at')
            ->where('m.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les matchs via Elasticsearch.
     */
    public function countMatchesElasticsearch(?string $league = null): int
    {
        if (!$this->elasticsearch?->isAvailable()) {
            return $this->count([]);
        }

        $query = $league ? ['term' => ['league' => $league]] : [];

        return $this->elasticsearch->count(ElasticsearchService::INDEX_FOOTBALL_MATCHES, $query);
    }

    /**
     * Obtient les agrégations par ligue via Elasticsearch.
     */
    public function getLeagueAggregations(): array
    {
        if (!$this->elasticsearch?->isAvailable()) {
            return $this->getStatsByLeague();
        }

        try {
            // Utiliser la méthode du service Elasticsearch
            if ($this->elasticsearch instanceof ElasticsearchService) {
                return $this->elasticsearch->getLeagueAggregation('football');
            }
        } catch (\Exception $e) {
            // Fallback
        }

        return $this->getStatsByLeague();
    }
}
