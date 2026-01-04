<?php

declare(strict_types=1);

namespace App\Service\Data;

use App\Entity\BasketballMatch;
use App\Entity\Team;
use App\Repository\BasketballMatchRepository;
use App\Repository\TeamRepository;
use App\Service\Data\Adapter\ApiBasketballAdapter;
use App\Service\Data\DTO\MatchData;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de synchronisation des matchs de basketball depuis les APIs.
 */
final class BasketballSyncService
{
    public function __construct(
        private readonly ApiBasketballAdapter $apiAdapter,
        private readonly EntityManagerInterface $entityManager,
        private readonly BasketballMatchRepository $matchRepository,
        private readonly TeamRepository $teamRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Synchronise les matchs du jour.
     *
     * @return array{synced: int, created: int, updated: int, errors: int}
     */
    public function syncTodayMatches(): array
    {
        $today = new \DateTimeImmutable('today');

        return $this->syncMatchesByDate($today);
    }

    /**
     * Synchronise les matchs d'une date specifique.
     *
     * @return array{synced: int, created: int, updated: int, errors: int}
     */
    public function syncMatchesByDate(\DateTimeInterface $date): array
    {
        $stats = [
            'synced' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
        ];

        try {
            $apiMatches = $this->apiAdapter->fetchMatchesByDate($date);

            foreach ($apiMatches as $apiMatch) {
                try {
                    $this->syncMatch($apiMatch, $stats);
                } catch (\Exception $e) {
                    $this->logger->error('Error syncing basketball match', [
                        'match_id' => $apiMatch->externalId,
                        'error' => $e->getMessage(),
                    ]);
                    ++$stats['errors'];
                }
            }

            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->logger->error('Error fetching basketball matches from API', [
                'error' => $e->getMessage(),
            ]);
            ++$stats['errors'];
        }

        return $stats;
    }

    /**
     * Synchronise un match unique.
     */
    private function syncMatch(MatchData $apiMatch, array &$stats): void
    {
        // Chercher si le match existe deja
        $existing = $this->matchRepository->createQueryBuilder('m')
            ->leftJoin('m.homeTeam', 'ht')
            ->leftJoin('m.awayTeam', 'at')
            ->where('ht.name = :homeTeam')
            ->andWhere('at.name = :awayTeam')
            ->andWhere('m.matchDate >= :start')
            ->andWhere('m.matchDate <= :end')
            ->setParameter('homeTeam', $apiMatch->homeTeam)
            ->setParameter('awayTeam', $apiMatch->awayTeam)
            ->setParameter('start', $apiMatch->matchDate->modify('-2 hours'))
            ->setParameter('end', $apiMatch->matchDate->modify('+2 hours'))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (null === $existing) {
            $match = new BasketballMatch();
            ++$stats['created'];
        } else {
            $match = $existing;
            ++$stats['updated'];
        }

        // Recuperer ou creer les equipes
        $homeTeam = $this->getOrCreateTeam($apiMatch->homeTeam, $apiMatch->league);
        $awayTeam = $this->getOrCreateTeam($apiMatch->awayTeam, $apiMatch->league);

        // Mettre a jour les donnees
        $match->setHomeTeam($homeTeam);
        $match->setAwayTeam($awayTeam);
        $match->setMatchDate($apiMatch->matchDate);
        $match->setLeague($apiMatch->league);
        $match->setStatus($apiMatch->status ?? 'scheduled');

        // Scores
        if (null !== $apiMatch->homeScore) {
            $match->setHomeFinalScore($apiMatch->homeScore);
        }
        if (null !== $apiMatch->awayScore) {
            $match->setAwayFinalScore($apiMatch->awayScore);
        }

        // Statistiques des equipes
        $stats_data = $apiMatch->statistics ?? [];
        if (!empty($stats_data['home_stats'])) {
            $match->setHomeStats($this->buildTeamStats($stats_data['home_stats']));
        }
        if (!empty($stats_data['away_stats'])) {
            $match->setAwayStats($this->buildTeamStats($stats_data['away_stats']));
        }

        // Scores par quart
        if (!empty($stats_data['quarters'])) {
            $this->setQuarterScores($match, $stats_data['quarters']);
        }

        $this->entityManager->persist($match);
        ++$stats['synced'];
    }

    /**
     * Construit les statistiques d'equipe.
     */
    private function buildTeamStats(array $rawStats): array
    {
        return [
            'avg_points' => $rawStats['total'] ?? 105.0,
            'avg_rebounds' => 44.0,
            'avg_assists' => 24.0,
            'fg_pct' => 46.0,
            'three_pt_pct' => 36.0,
            'ft_pct' => 78.0,
            'pace' => 100.0,
        ];
    }

    /**
     * Definit les scores par quart.
     */
    private function setQuarterScores(BasketballMatch $match, array $quarters): void
    {
        if (isset($quarters['q1'])) {
            $match->setHomeScoreQ1($quarters['q1']['home'] ?? null);
            $match->setAwayScoreQ1($quarters['q1']['away'] ?? null);
        }
        if (isset($quarters['q2'])) {
            $match->setHomeScoreQ2($quarters['q2']['home'] ?? null);
            $match->setAwayScoreQ2($quarters['q2']['away'] ?? null);
        }
        if (isset($quarters['q3'])) {
            $match->setHomeScoreQ3($quarters['q3']['home'] ?? null);
            $match->setAwayScoreQ3($quarters['q3']['away'] ?? null);
        }
        if (isset($quarters['q4'])) {
            $match->setHomeScoreQ4($quarters['q4']['home'] ?? null);
            $match->setAwayScoreQ4($quarters['q4']['away'] ?? null);
        }
    }

    /**
     * Recupere ou cree une equipe.
     */
    private function getOrCreateTeam(string $teamName, string $league): Team
    {
        $team = $this->teamRepository->findOneBy(['name' => $teamName]);

        if (null === $team) {
            $team = new Team();
            $team->setName($teamName);
            $team->setSport('basketball');
            $team->setLeague($league);
            $team->setCountry($this->detectCountry($league));

            $this->entityManager->persist($team);
        }

        return $team;
    }

    /**
     * Detecte le pays depuis la ligue.
     */
    private function detectCountry(string $league): string
    {
        $mapping = [
            'NBA' => 'USA',
            'Euroleague' => 'Europe',
            'Eurocup' => 'Europe',
            'Pro A' => 'France',
            'Liga ACB' => 'Spain',
            'Serie A' => 'Italy',
        ];

        foreach ($mapping as $key => $country) {
            if (false !== stripos($league, $key)) {
                return $country;
            }
        }

        return 'Unknown';
    }
}
