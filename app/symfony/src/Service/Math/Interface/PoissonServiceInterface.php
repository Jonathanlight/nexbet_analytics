<?php

declare(strict_types=1);

namespace App\Service\Math\Interface;

interface PoissonServiceInterface
{
    /**
     * Calcule la probabilité qu'une équipe marque exactement k buts.
     */
    public function probability(float $lambda, int $k): float;

    /**
     * Calcule la probabilité d'un score exact pour un match.
     */
    public function matchScoreProbability(
        float $homeExpectedGoals,
        float $awayExpectedGoals,
        int $homeGoals,
        int $awayGoals,
    ): float;

    /**
     * Calcule les probabilités de résultat (1, X, 2).
     *
     * @return array{1: float, X: float, 2: float}
     */
    public function calculateResultProbabilities(float $homeExpectedGoals, float $awayExpectedGoals): array;

    /**
     * Calcule la probabilité Over/Under pour un seuil donné.
     *
     * @return array{over: float, under: float}
     */
    public function calculateOverUnder(float $homeExpectedGoals, float $awayExpectedGoals, float $line): array;

    /**
     * Calcule la probabilité BTTS (Both Teams To Score).
     *
     * @return array{yes: float, no: float}
     */
    public function calculateBTTS(float $homeExpectedGoals, float $awayExpectedGoals): array;

    /**
     * Calcule la distribution de probabilité de tous les scores possibles.
     *
     * @return array<string, float>
     */
    public function calculateScoreDistribution(float $homeExpectedGoals, float $awayExpectedGoals): array;

    /**
     * Calcule le nombre moyen de buts attendus basé sur les statistiques.
     */
    public function calculateExpectedGoals(
        float $teamAttackStrength,
        float $teamDefenseStrength,
        float $opponentAttackStrength,
        float $opponentDefenseStrength,
        float $leagueAverage,
    ): float;
}
