<?php

declare(strict_types=1);

namespace App\Service\Data\Interface;

/**
 * Interface pour les services fournissant des statistiques détaillées de matchs.
 */
interface MatchStatisticsProviderInterface
{
    /**
     * Récupère les statistiques détaillées d'un match (xG, tirs, possession, etc.).
     *
     * @return array{
     *     home_xg: float,
     *     away_xg: float,
     *     home_shots: int,
     *     away_shots: int,
     *     home_shots_on_target: int,
     *     away_shots_on_target: int,
     *     home_possession: int,
     *     away_possession: int,
     *     home_passes: int,
     *     away_passes: int,
     *     home_pass_accuracy: float,
     *     away_pass_accuracy: float
     * }
     */
    public function fetchMatchStatistics(string $matchId): array;

    /**
     * Récupère les statistiques d'un joueur.
     *
     * @return array{
     *     goals: int,
     *     assists: int,
     *     xg: float,
     *     xa: float,
     *     rating: float,
     *     minutes_played: int,
     *     shots: int,
     *     passes: int,
     *     pass_accuracy: float
     * }
     */
    public function fetchPlayerStatistics(string $playerId): array;

    /**
     * Vérifie si le service est disponible.
     */
    public function isAvailable(): bool;

    /**
     * Retourne le nom du service.
     */
    public function getName(): string;
}
