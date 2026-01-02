<?php

declare(strict_types=1);

namespace App\Service\Data\Interface;

use App\Service\Data\DTO\MatchData;

/**
 * Interface pour les providers de données de matchs.
 */
interface MatchDataProviderInterface
{
    /**
     * Récupère les matchs du jour.
     *
     * @return MatchData[]
     */
    public function fetchTodayMatches(): array;

    /**
     * Récupère les matchs par date.
     *
     * @return MatchData[]
     */
    public function fetchMatchesByDate(\DateTimeInterface $date): array;

    /**
     * Récupère les détails d'un match.
     */
    public function fetchMatchDetails(string $matchId): ?MatchData;

    /**
     * Retourne le nom du provider.
     */
    public function getName(): string;

    /**
     * Vérifie si le provider est disponible (rate limit, quota).
     */
    public function isAvailable(): bool;
}
