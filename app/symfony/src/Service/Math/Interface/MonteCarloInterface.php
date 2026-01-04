<?php

declare(strict_types=1);

namespace App\Service\Math\Interface;

interface MonteCarloInterface
{
    /**
     * @return array{
     *     result: array{1: float, X: float, 2: float},
     *     over_under: array{over_05: float, over_15: float, over_25: float, over_35: float},
     *     btts: array{yes: float, no: float},
     *     most_likely_scores: array<string, float>
     * }
     */
    public function simulateFootballMatch(
        float $homeExpectedGoals,
        float $awayExpectedGoals,
        int $simulations = 10000,
    ): array;

    /**
     * Simule un match de basketball.
     *
     * @return array{
     *     result: array{home: float, away: float},
     *     overtime_probability: float,
     *     point_differences: array<string, float>,
     *     total_points: array<string, float>
     * }
     */
    public function simulateBasketballMatch(
        float $homeAvgPoints,
        float $awayAvgPoints,
        float $homeStdDev = 10.0,
        float $awayStdDev = 10.0,
        int $simulations = 10000,
    ): array;

    /**
     * Simule avec des facteurs avancés (avantage domicile, météo, blessures, forme).
     *
     * @param array{
     *     home_advantage?: float,
     *     weather?: string,
     *     home_injuries?: int,
     *     away_injuries?: int,
     *     home_form?: float,
     *     away_form?: float
     * } $factors
     */
    public function simulateWithFactors(
        float $homeExpectedGoals,
        float $awayExpectedGoals,
        array $factors = [],
        int $simulations = 10000,
    ): array;
}
