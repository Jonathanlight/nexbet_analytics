<?php

declare(strict_types=1);

namespace App\Service\Data;

use App\Entity\FootballMatch;
use App\Entity\Odds;
use App\Entity\Team;
use App\Repository\FootballMatchRepository;
use App\Repository\TeamRepository;
use App\Service\Data\DTO\MatchData;
use App\Service\Data\Interface\LiveDataProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de synchronisation des matchs depuis les APIs vers la base de données.
 */
final class MatchSyncService
{
    public function __construct(
        private readonly LiveDataProviderInterface $liveDataProvider,
        private readonly EntityManagerInterface $entityManager,
        private readonly FootballMatchRepository $matchRepository,
        private readonly TeamRepository $teamRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Synchronise les matchs du jour depuis les APIs.
     *
     * @return array{synced: int, created: int, updated: int, errors: int}
     */
    public function syncTodayMatches(): array
    {
        $stats = [
            'synced' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
        ];

        try {
            $apiMatches = $this->liveDataProvider->fetchTodayMatches();

            foreach ($apiMatches as $apiMatch) {
                try {
                    $this->syncMatch($apiMatch, $stats);
                } catch (\Exception $e) {
                    $this->logger->error('Error syncing match', [
                        'match_id' => $apiMatch->externalId,
                        'error' => $e->getMessage(),
                    ]);
                    ++$stats['errors'];
                }
            }

            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->logger->error('Error fetching matches from API', [
                'error' => $e->getMessage(),
            ]);
            ++$stats['errors'];
        }

        return $stats;
    }

    /**
     * Synchronise les matchs d'une date spécifique.
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
            $apiMatches = $this->liveDataProvider->fetchMatchesByDate($date);

            foreach ($apiMatches as $apiMatch) {
                try {
                    $this->syncMatch($apiMatch, $stats);
                } catch (\Exception $e) {
                    $this->logger->error('Error syncing match', [
                        'match_id' => $apiMatch->externalId,
                        'error' => $e->getMessage(),
                    ]);
                    ++$stats['errors'];
                }
            }

            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->logger->error('Error fetching matches from API', [
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
        // Chercher si le match existe déjà par équipes et date
        $match = $this->matchRepository->findMatchByTeamsAndDate(
            $apiMatch->homeTeam,
            $apiMatch->awayTeam,
            $apiMatch->matchDate
        );

        if (null === $match) {
            // Créer un nouveau match
            $match = new FootballMatch();
            ++$stats['created'];
        } else {
            ++$stats['updated'];
        }

        // Récupérer ou créer les équipes (avec le bon sport)
        $homeTeam = $this->getOrCreateTeam($apiMatch->homeTeam, $apiMatch->sport, $apiMatch->league);
        $awayTeam = $this->getOrCreateTeam($apiMatch->awayTeam, $apiMatch->sport, $apiMatch->league);

        // Mettre à jour les données du match
        $match->setHomeTeam($homeTeam);
        $match->setAwayTeam($awayTeam);
        $match->setMatchDate($apiMatch->matchDate);
        $match->setLeague($apiMatch->league);
        $match->setStatus($apiMatch->status);

        // Stade/Venue
        if (null !== $apiMatch->venue) {
            $match->setStadium($apiMatch->venue);
        }

        // Scores
        if (null !== $apiMatch->homeScore) {
            $match->setHomeScore($apiMatch->homeScore);
        }
        if (null !== $apiMatch->awayScore) {
            $match->setAwayScore($apiMatch->awayScore);
        }

        // Statistiques (extraire les données pertinentes)
        if (!empty($apiMatch->statistics)) {
            $this->updateMatchStatistics($match, $apiMatch->statistics);
        }

        // Météo
        if (null !== $apiMatch->weather) {
            $match->setWeatherConditions([
                'temperature' => $apiMatch->weather->temperature,
                'description' => $apiMatch->weather->description,
                'wind_speed' => $apiMatch->weather->windSpeed,
                'humidity' => $apiMatch->weather->humidity,
                'precipitation' => $apiMatch->weather->precipitation,
            ]);
        }

        // Cotes
        if (!empty($apiMatch->odds)) {
            $this->syncOdds($match, $apiMatch->odds);
        }

        $this->entityManager->persist($match);
        ++$stats['synced'];
    }

    /**
     * Met à jour les statistiques du match depuis les données API.
     */
    private function updateMatchStatistics(FootballMatch $match, array $statistics): void
    {
        // Extraire les statistiques pertinentes selon le format
        if (isset($statistics['referee'])) {
            $match->setReferee($statistics['referee']);
        }

        // xG si disponible
        if (isset($statistics['home_xg'])) {
            $match->setHomeXg((float) $statistics['home_xg']);
        }
        if (isset($statistics['away_xg'])) {
            $match->setAwayXg((float) $statistics['away_xg']);
        }

        // Stocker les stats complètes en H2H
        $match->setHeadToHeadStats($statistics);
    }

    /**
     * Synchronise les cotes pour un match.
     */
    private function syncOdds(FootballMatch $match, array $oddsData): void
    {
        // Supprimer les anciennes cotes si le match existe déjà
        if (null !== $match->getId()) {
            foreach ($match->getOdds() as $existingOdds) {
                $this->entityManager->remove($existingOdds);
            }
        }

        // Créer les nouvelles cotes depuis les données API
        foreach ($oddsData as $betType => $markets) {
            if (!is_array($markets)) {
                continue;
            }

            foreach ($markets as $market => $oddsValue) {
                if (!is_numeric($oddsValue)) {
                    continue;
                }

                $odds = new Odds();
                $odds->setMatch($match);
                $odds->setBookmaker('Aggregated'); // Cotes agrégées de plusieurs sources
                $odds->setBetType((string) $betType);
                $odds->setMarket((string) $market);
                $odds->setOdds((float) $oddsValue);

                $this->entityManager->persist($odds);
            }
        }
    }

    /**
     * Récupère ou crée une équipe.
     */
    private function getOrCreateTeam(string $teamName, string $sport = 'football', string $league = 'Unknown'): Team
    {
        $team = $this->teamRepository->findOneBy(['name' => $teamName]);

        if (null === $team) {
            $team = new Team();
            $team->setName($teamName);
            $team->setSport($sport);
            $team->setCountry('Unknown'); // À améliorer avec une détection du pays
            $team->setLeague($league);

            $this->entityManager->persist($team);
        } else {
            // Mettre à jour le sport si différent (migration)
            if ($team->getSport() !== $sport) {
                $team->setSport($sport);
            }
            if ('Unknown' === $team->getLeague() && 'Unknown' !== $league) {
                $team->setLeague($league);
            }
        }

        return $team;
    }

    /**
     * Nettoie les anciens matchs (optionnel).
     */
    public function cleanOldMatches(int $daysToKeep = 30): int
    {
        $cutoffDate = new \DateTimeImmutable("-{$daysToKeep} days");

        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete(FootballMatch::class, 'm')
            ->where('m.matchDate < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate);

        return $qb->getQuery()->execute();
    }
}
