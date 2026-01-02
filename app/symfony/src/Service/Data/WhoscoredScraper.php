<?php

declare(strict_types=1);

namespace App\Service\Data;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service de scraping de Whoscored pour les statistiques détaillées (TEMPLATE).
 * Note: Whoscored a des protections anti-scraping - privilégier une API si disponible.
 */
class WhoscoredScraper
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Récupère les statistiques d'un match (xG, tirs, etc.).
     * TEMPLATE - À adapter.
     */
    public function fetchMatchStatistics(string $matchId): array
    {
        // TODO: Implémenter le scraping
        // Whoscored utilise JavaScript - peut nécessiter un headless browser

        return [
            'home_xg' => 0.0,
            'away_xg' => 0.0,
            'home_shots' => 0,
            'away_shots' => 0,
            'home_shots_on_target' => 0,
            'away_shots_on_target' => 0,
            'home_possession' => 0,
            'away_possession' => 0,
        ];
    }

    /**
     * Récupère les statistiques des joueurs.
     */
    public function fetchPlayerStatistics(string $playerId): array
    {
        // TODO: Implémenter

        return [
            'goals' => 0,
            'assists' => 0,
            'xg' => 0.0,
            'xa' => 0.0,
            'rating' => 0.0,
        ];
    }
}
