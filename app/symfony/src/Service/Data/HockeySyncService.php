<?php

declare(strict_types=1);

namespace App\Service\Data;

use App\Entity\HockeyMatch;
use App\Entity\Team;
use App\Repository\HockeyMatchRepository;
use App\Repository\TeamRepository;
use App\Service\Data\Adapter\ApiHockeyAdapter;
use App\Service\Data\DTO\MatchData;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de synchronisation des matchs de hockey depuis les APIs.
 */
final class HockeySyncService
{
    public function __construct(
        private readonly ApiHockeyAdapter $apiAdapter,
        private readonly EntityManagerInterface $entityManager,
        private readonly HockeyMatchRepository $matchRepository,
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
                    $this->logger->error('Error syncing hockey match', [
                        'match_id' => $apiMatch->externalId,
                        'error' => $e->getMessage(),
                    ]);
                    ++$stats['errors'];
                }
            }

            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->logger->error('Error fetching hockey matches from API', [
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
        $existing = $this->matchRepository->findMatchByTeamsAndDate(
            $apiMatch->homeTeam,
            $apiMatch->awayTeam,
            $apiMatch->matchDate
        );

        if (null === $existing) {
            $match = new HockeyMatch();
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

        if (null !== $apiMatch->venue) {
            $match->setVenue($apiMatch->venue);
        }

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

        // Scores par periode
        if (!empty($stats_data['periods'])) {
            $this->setPeriodScores($match, $stats_data['periods']);
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
            'avg_goals' => $rawStats['total'] ?? 2.8,
            'avg_shots' => 32.0,
            'avg_power_play_pct' => 20.0,
            'avg_penalty_kill_pct' => 80.0,
            'avg_faceoff_pct' => 50.0,
        ];
    }

    /**
     * Definit les scores par periode.
     */
    private function setPeriodScores(HockeyMatch $match, array $periods): void
    {
        if (isset($periods['p1'])) {
            $match->setHomeScoreP1($periods['p1']['home'] ?? null);
            $match->setAwayScoreP1($periods['p1']['away'] ?? null);
        }
        if (isset($periods['p2'])) {
            $match->setHomeScoreP2($periods['p2']['home'] ?? null);
            $match->setAwayScoreP2($periods['p2']['away'] ?? null);
        }
        if (isset($periods['p3'])) {
            $match->setHomeScoreP3($periods['p3']['home'] ?? null);
            $match->setAwayScoreP3($periods['p3']['away'] ?? null);
        }
        if (isset($periods['overtime'])) {
            $match->setHomeScoreOt($periods['overtime']['home'] ?? null);
            $match->setAwayScoreOt($periods['overtime']['away'] ?? null);
        }
        if (isset($periods['shootout'])) {
            $match->setHomeShootout($periods['shootout']['home'] ?? null);
            $match->setAwayShootout($periods['shootout']['away'] ?? null);
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
            $team->setSport('hockey');
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
            'NHL' => 'USA/Canada',
            'KHL' => 'Russia',
            'SHL' => 'Sweden',
            'Liiga' => 'Finland',
            'Swiss' => 'Switzerland',
            'DEL' => 'Germany',
            'Magnus' => 'France',
        ];

        foreach ($mapping as $key => $country) {
            if (false !== stripos($league, $key)) {
                return $country;
            }
        }

        return 'Unknown';
    }
}
