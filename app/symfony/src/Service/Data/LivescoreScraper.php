<?php

declare(strict_types=1);

namespace App\Service\Data;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service de scraping de Livescore (TEMPLATE - nécessite adaptation).
 * Note: Le scraping réel nécessite l'analyse de la structure HTML actuelle du site.
 */
class LivescoreScraper
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Récupère les matchs du jour depuis Livescore.
     * TEMPLATE - À adapter selon la structure réelle du site.
     */
    public function fetchTodayMatches(): array
    {
        // TODO: Implémenter le scraping réel
        // Considérations:
        // - Vérifier les Terms of Service
        // - Respecter le robots.txt
        // - Implémenter un rate limiting
        // - Gérer les erreurs et timeouts

        // Structure attendue du retour:
        return [
            // Exemple de structure
            [
                'home_team' => 'PSG',
                'away_team' => 'OM',
                'league' => 'Ligue 1',
                'date' => new \DateTimeImmutable('2026-01-02 21:00:00'),
                'status' => 'scheduled',
            ],
        ];
    }

    /**
     * Récupère les détails d'un match.
     */
    public function fetchMatchDetails(string $matchId): array
    {
        // TODO: Implémenter

        return [
            'match_id' => $matchId,
            'statistics' => [],
            'events' => [],
            'lineups' => [],
        ];
    }

    /**
     * Récupère les classements d'un championnat.
     */
    public function fetchLeagueStandings(string $league): array
    {
        // TODO: Implémenter

        return [];
    }
}
