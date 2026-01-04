<?php

declare(strict_types=1);

namespace App\Service\Math\Interface;

use App\Entity\Team;

interface EloServiceInterface
{
    /**
     * Calcule la probabilité de victoire selon le rating Elo.
     */
    public function calculateWinProbability(float $ratingA, float $ratingB): float;

    /**
     * Calcule les probabilités de résultat (1, X, 2) basées sur Elo.
     *
     * @return array{1: float, X: float, 2: float}
     */
    public function calculateResultProbabilities(Team $homeTeam, Team $awayTeam): array;

    /**
     * Met à jour les ratings Elo après un match.
     *
     * @return array{home: float, away: float, home_change: float, away_change: float}
     */
    public function updateRatings(Team $homeTeam, Team $awayTeam, int $homeScore, int $awayScore): array;

    /**
     * Calcule l'écart de force entre deux équipes.
     */
    public function calculateStrengthDifference(Team $team1, Team $team2, bool $team1Home = false): float;

    /**
     * Calcule le rating Elo initial basé sur les performances récentes.
     */
    public function calculateInitialRating(int $wins, int $draws, int $losses, float $leagueStrength = 1.0): float;

    /**
     * Prédit le score le plus probable basé sur Elo et historique.
     *
     * @return array{home_goals: int, away_goals: int, score: string, confidence: float}
     */
    public function predictMostLikelyScore(
        Team $homeTeam,
        Team $awayTeam,
        float $homeAvgGoals,
        float $awayAvgGoals,
    ): array;
}
