<?php

declare(strict_types=1);

namespace App\Service\Data;

use App\Service\Data\Adapter\ApiFootballAdapter;
use App\Service\Data\Interface\CacheInterface;
use App\Service\Data\Interface\MatchStatisticsProviderInterface;

/**
 * Service fournissant des statistiques détaillées de matchs via API-Football.
 * Utilise les APIs au lieu du scraping pour respecter les ToS et éviter les protections anti-bot.
 */
final class WhoscoredScraper implements MatchStatisticsProviderInterface
{
    public function __construct(
        private readonly ApiFootballAdapter $apiFootballAdapter,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Récupère les statistiques détaillées d'un match (xG, tirs, possession, etc.).
     */
    public function fetchMatchStatistics(string $matchId): array
    {
        $cacheKey = sprintf('match_stats_%s', $matchId);

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        if (!$this->apiFootballAdapter->isAvailable()) {
            return $this->getEmptyStatistics();
        }

        $match = $this->apiFootballAdapter->fetchMatchDetails($matchId);

        if (null === $match) {
            return $this->getEmptyStatistics();
        }

        $statistics = $this->extractStatisticsFromMatch($match->statistics);

        // Cache pour 30 minutes
        $this->cache->set($cacheKey, $statistics, 1800);

        return $statistics;
    }

    /**
     * Récupère les statistiques d'un joueur.
     */
    public function fetchPlayerStatistics(string $playerId): array
    {
        $cacheKey = sprintf('player_stats_%s', $playerId);

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        // Pour l'instant, retourner une structure vide
        // Une implémentation complète nécessiterait un endpoint spécifique API-Football
        $stats = [
            'goals' => 0,
            'assists' => 0,
            'xg' => 0.0,
            'xa' => 0.0,
            'rating' => 0.0,
            'minutes_played' => 0,
            'shots' => 0,
            'passes' => 0,
            'pass_accuracy' => 0.0,
        ];

        // Cache pour 1 heure
        $this->cache->set($cacheKey, $stats, 3600);

        return $stats;
    }

    public function isAvailable(): bool
    {
        return $this->apiFootballAdapter->isAvailable();
    }

    public function getName(): string
    {
        return 'Match Statistics Provider (via API-Football)';
    }

    /**
     * Récupère les statistiques avancées d'un match (xG, expected points, etc.).
     */
    public function fetchAdvancedStatistics(string $matchId): array
    {
        $cacheKey = sprintf('advanced_stats_%s', $matchId);

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $basicStats = $this->fetchMatchStatistics($matchId);

        // Calculer des statistiques avancées basées sur les données de base
        $advancedStats = [
            ...$basicStats,
            'home_xpts' => $this->calculateExpectedPoints($basicStats, 'home'),
            'away_xpts' => $this->calculateExpectedPoints($basicStats, 'away'),
            'home_shot_quality' => $this->calculateShotQuality($basicStats, 'home'),
            'away_shot_quality' => $this->calculateShotQuality($basicStats, 'away'),
            'home_efficiency' => $this->calculateEfficiency($basicStats, 'home'),
            'away_efficiency' => $this->calculateEfficiency($basicStats, 'away'),
        ];

        // Cache pour 30 minutes
        $this->cache->set($cacheKey, $advancedStats, 1800);

        return $advancedStats;
    }

    /**
     * Extrait les statistiques depuis les données du match.
     */
    private function extractStatisticsFromMatch(array $matchStatistics): array
    {
        $stats = $this->getEmptyStatistics();

        if (empty($matchStatistics)) {
            return $stats;
        }

        // API-Football retourne les stats dans un format spécifique
        // On doit extraire et normaliser les données
        foreach ($matchStatistics as $teamStats) {
            $isHome = ($teamStats['team'] ?? null) === 'home';
            $prefix = $isHome ? 'home' : 'away';

            $stats[$prefix.'_shots'] = (int) ($teamStats['shots']['total'] ?? 0);
            $stats[$prefix.'_shots_on_target'] = (int) ($teamStats['shots']['on'] ?? 0);
            $stats[$prefix.'_possession'] = (int) ($teamStats['possession'] ?? 0);
            $stats[$prefix.'_passes'] = (int) ($teamStats['passes']['total'] ?? 0);
            $stats[$prefix.'_pass_accuracy'] = (float) ($teamStats['passes']['accuracy'] ?? 0.0);

            // xG peut ne pas être disponible dans toutes les APIs
            // On utilise une estimation basée sur les tirs si non disponible
            if (isset($teamStats['xg'])) {
                $stats[$prefix.'_xg'] = (float) $teamStats['xg'];
            } else {
                // Estimation simple: xG ≈ (tirs cadrés * 0.3) + (tirs non cadrés * 0.05)
                $shotsOnTarget = $stats[$prefix.'_shots_on_target'];
                $shotsOffTarget = $stats[$prefix.'_shots'] - $shotsOnTarget;
                $stats[$prefix.'_xg'] = ($shotsOnTarget * 0.3) + ($shotsOffTarget * 0.05);
            }
        }

        return $stats;
    }

    /**
     * Retourne une structure de statistiques vide.
     */
    private function getEmptyStatistics(): array
    {
        return [
            'home_xg' => 0.0,
            'away_xg' => 0.0,
            'home_shots' => 0,
            'away_shots' => 0,
            'home_shots_on_target' => 0,
            'away_shots_on_target' => 0,
            'home_possession' => 0,
            'away_possession' => 0,
            'home_passes' => 0,
            'away_passes' => 0,
            'home_pass_accuracy' => 0.0,
            'away_pass_accuracy' => 0.0,
        ];
    }

    /**
     * Calcule les points attendus (expected points) basés sur le xG.
     */
    private function calculateExpectedPoints(array $stats, string $team): float
    {
        $prefix = $team;
        $opposingPrefix = 'home' === $team ? 'away' : 'home';

        $teamXg = $stats[$prefix.'_xg'] ?? 0.0;
        $opponentXg = $stats[$opposingPrefix.'_xg'] ?? 0.0;

        // Formule simplifiée pour les expected points
        // Win probability ≈ xG / (xG + opponent xG)
        $totalXg = $teamXg + $opponentXg;
        if (0.0 === $totalXg) {
            return 1.0; // Draw
        }

        $winProb = $teamXg / $totalXg;
        $drawProb = 0.25; // Probabilité de match nul estimée
        $loseProb = 1 - $winProb - $drawProb;

        return ($winProb * 3) + ($drawProb * 1);
    }

    /**
     * Calcule la qualité des tirs (shot quality).
     */
    private function calculateShotQuality(array $stats, string $team): float
    {
        $shots = $stats[$team.'_shots'] ?? 0;
        $xg = $stats[$team.'_xg'] ?? 0.0;

        if (0 === $shots) {
            return 0.0;
        }

        // Qualité moyenne par tir
        return $xg / $shots;
    }

    /**
     * Calcule l'efficacité (ratio buts/xG).
     */
    private function calculateEfficiency(array $stats, string $team): float
    {
        $xg = $stats[$team.'_xg'] ?? 0.0;

        if (0.0 === $xg) {
            return 0.0;
        }

        // Pour calculer l'efficacité réelle, il faudrait les vrais buts
        // Pour l'instant, retourner 1.0 (efficacité neutre)
        return 1.0;
    }
}
