<?php

declare(strict_types=1);

namespace App\Service\Data\Interface;

use App\Service\Data\DTO\MatchData;

/**
 * Interface pour les services fournissant des données live de matchs.
 */
interface LiveDataProviderInterface
{
    /**
     * Récupère les matchs du jour.
     *
     * @return MatchData[]
     */
    public function fetchTodayMatches(): array;

    /**
     * Récupère les détails d'un match.
     */
    public function fetchMatchDetails(string $matchId): ?MatchData;

    /**
     * Récupère les classements d'un championnat.
     *
     * @return array<string, mixed>
     */
    public function fetchLeagueStandings(string $league): array;

    /**
     * Vérifie si le service est disponible.
     */
    public function isAvailable(): bool;
}
