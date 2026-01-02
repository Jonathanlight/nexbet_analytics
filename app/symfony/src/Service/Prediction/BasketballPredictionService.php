<?php

declare(strict_types=1);

namespace App\Service\Prediction;

use App\Service\Math\MonteCarloSimulator;

/**
 * Service de prédiction pour le basketball.
 */
class BasketballPredictionService
{
    public function __construct(
        private readonly MonteCarloSimulator $monteCarloSimulator,
        private readonly ConfidenceCalculator $confidenceCalculator,
    ) {
    }

    /**
     * Prédit le résultat d'un match de basketball.
     */
    public function predictResult(
        float $homeAvgPoints,
        float $awayAvgPoints,
        float $homeStdDev = 10.0,
        float $awayStdDev = 10.0,
    ): array {
        $simulation = $this->monteCarloSimulator->simulateBasketballMatch(
            $homeAvgPoints,
            $awayAvgPoints,
            $homeStdDev,
            $awayStdDev,
            10000
        );

        $confidence = $this->confidenceCalculator->calculateOverallConfidence([
            'monte_carlo' => max($simulation['result']['home'], $simulation['result']['away']),
        ]);

        return [
            'probabilities' => $simulation['result'],
            'confidence' => $confidence,
            'overtime_probability' => $simulation['overtime_probability'],
            'point_differences' => $simulation['point_differences'],
            'total_points' => $simulation['total_points'],
        ];
    }

    /**
     * Prédit le handicap.
     */
    public function predictHandicap(
        float $homeAvgPoints,
        float $awayAvgPoints,
        array $handicaps = [-3.5, -4.5, -5.5, -6.5, -7.5, -8.5, -9.5, -10.5],
    ): array {
        $predictions = [];

        $pointDiff = $homeAvgPoints - $awayAvgPoints;

        foreach ($handicaps as $handicap) {
            $homeWithHandicap = $homeAvgPoints + $handicap;

            $homeProbability = $this->calculateHandicapProbability($homeWithHandicap, $awayAvgPoints);

            $predictions[(string) $handicap] = [
                'home' => round($homeProbability, 2),
                'away' => round(100 - $homeProbability, 2),
                'confidence' => $this->calculateHandicapConfidence($handicap, $pointDiff),
            ];
        }

        return $predictions;
    }

    /**
     * Prédit le total de points.
     */
    public function predictTotalPoints(
        float $homeAvgPoints,
        float $awayAvgPoints,
        array $lines = [160.5, 170.5, 180.5, 190.5, 200.5, 210.5, 220.5],
    ): array {
        $predictions = [];
        $expectedTotal = $homeAvgPoints + $awayAvgPoints;

        foreach ($lines as $line) {
            $overProb = $this->calculateOverProbability($expectedTotal, $line);

            $predictions[(string) $line] = [
                'over' => round($overProb, 2),
                'under' => round(100 - $overProb, 2),
                'confidence' => $this->calculateTotalConfidence($line, $expectedTotal),
            ];
        }

        return $predictions;
    }

    /**
     * Prédit le vainqueur de chaque quart-temps.
     */
    public function predictQuarters(float $homeAvgPoints, float $awayAvgPoints): array
    {
        $homePerQuarter = $homeAvgPoints / 4;
        $awayPerQuarter = $awayAvgPoints / 4;

        $quarters = [];

        for ($i = 1; $i <= 4; ++$i) {
            // Ajouter de la variance par quart
            $variance = rand(95, 105) / 100;

            $homeQPoints = $homePerQuarter * $variance;
            $awayQPoints = $awayPerQuarter * $variance;

            $homeProbability = $this->calculateWinProbability($homeQPoints, $awayQPoints);

            $quarters["Q{$i}"] = [
                'home' => round($homeProbability, 2),
                'away' => round(100 - $homeProbability, 2),
                'expected_points' => [
                    'home' => round($homeQPoints, 1),
                    'away' => round($awayQPoints, 1),
                ],
            ];
        }

        return $quarters;
    }

    /**
     * Calcule la probabilité de handicap.
     */
    private function calculateHandicapProbability(float $homePoints, float $awayPoints): float
    {
        $diff = $homePoints - $awayPoints;

        // Fonction logistique
        $probability = 1 / (1 + exp(-$diff / 5)) * 100;

        return max(1, min(99, $probability));
    }

    /**
     * Calcule la probabilité Over.
     */
    private function calculateOverProbability(float $expected, float $line): float
    {
        $diff = $expected - $line;

        // Plus le score attendu est au-dessus de la ligne, plus la probabilité est élevée
        $probability = 50 + ($diff * 3);

        return max(10, min(90, $probability));
    }

    /**
     * Calcule la probabilité de victoire.
     */
    private function calculateWinProbability(float $homePoints, float $awayPoints): float
    {
        $diff = $homePoints - $awayPoints;

        $probability = 50 + ($diff * 5);

        return max(10, min(90, $probability));
    }

    /**
     * Calcule la confiance pour un handicap.
     */
    private function calculateHandicapConfidence(float $handicap, float $actualDiff): float
    {
        $diff = abs($handicap - $actualDiff);

        if ($diff < 2) {
            return 85.0;
        }
        if ($diff < 4) {
            return 80.0;
        }
        if ($diff < 6) {
            return 75.0;
        }
        if ($diff < 8) {
            return 70.0;
        }

        return 65.0;
    }

    /**
     * Calcule la confiance pour un total.
     */
    private function calculateTotalConfidence(float $line, float $expectedTotal): float
    {
        $diff = abs($line - $expectedTotal);

        if ($diff < 5) {
            return 85.0;
        }
        if ($diff < 10) {
            return 80.0;
        }
        if ($diff < 15) {
            return 75.0;
        }
        if ($diff < 20) {
            return 70.0;
        }

        return 65.0;
    }
}
