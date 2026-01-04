<?php

declare(strict_types=1);

namespace App\Service\Prediction;

/**
 * Service de predictions pour les matchs de hockey sur glace.
 */
final class HockeyPredictionService
{
    private const HOME_ADVANTAGE = 0.03; // 3% avantage domicile
    private const AVG_GOALS_PER_MATCH = 5.5; // Moyenne NHL

    /**
     * Predit le resultat d'un match.
     */
    public function predictResult(float $homeAvgGoals, float $awayAvgGoals): array
    {
        // Ajuster pour l'avantage a domicile
        $homeStrength = $homeAvgGoals * (1 + self::HOME_ADVANTAGE);
        $awayStrength = $awayAvgGoals * (1 - self::HOME_ADVANTAGE);

        $total = $homeStrength + $awayStrength;
        $homeWinProb = ($homeStrength / $total) * 100;
        $awayWinProb = ($awayStrength / $total) * 100;

        // Le hockey a moins de matchs nuls (overtime/shootout)
        $drawProb = 10; // Environ 10% des matchs vont en prolongation
        $homeWinProb = $homeWinProb * 0.90;
        $awayWinProb = $awayWinProb * 0.90;

        $prediction = '1';
        if ($awayWinProb > $homeWinProb && $awayWinProb > $drawProb) {
            $prediction = '2';
        } elseif ($drawProb > $homeWinProb && $drawProb > $awayWinProb) {
            $prediction = 'X';
        }

        $confidence = max($homeWinProb, $awayWinProb, $drawProb);

        return [
            'prediction' => $prediction,
            'probabilities' => [
                '1' => round($homeWinProb, 1),
                'X' => round($drawProb, 1),
                '2' => round($awayWinProb, 1),
            ],
            'confidence' => round($confidence, 1),
            'home_expected_goals' => round($homeStrength, 2),
            'away_expected_goals' => round($awayStrength, 2),
        ];
    }

    /**
     * Predit le total de buts.
     */
    public function predictTotalGoals(float $homeAvgGoals, float $awayAvgGoals): array
    {
        $expectedTotal = $homeAvgGoals + $awayAvgGoals;

        // Probabilites over/under pour differentes lignes
        $lines = [4.5, 5.5, 6.5, 7.5];
        $predictions = [];

        foreach ($lines as $line) {
            $overProb = $this->calculateOverProbability($expectedTotal, $line);
            $predictions["over_{$line}"] = $overProb;
            $predictions["under_{$line}"] = 100 - $overProb;
        }

        // Meilleure prediction
        $bestLine = 5.5;
        $bestProb = $predictions['over_5.5'];
        if ($expectedTotal > 6) {
            $bestLine = 6.5;
            $bestProb = $predictions['over_6.5'];
        } elseif ($expectedTotal < 5) {
            $bestLine = 4.5;
            $bestProb = $predictions['under_4.5'];
        }

        return [
            'expected_total' => round($expectedTotal, 2),
            'predictions' => $predictions,
            'best_bet' => [
                'line' => $bestLine,
                'type' => $expectedTotal > $bestLine ? 'over' : 'under',
                'probability' => round($bestProb, 1),
            ],
            'confidence' => round(abs($expectedTotal - 5.5) * 10 + 50, 1),
        ];
    }

    /**
     * Predit les buts par periode.
     */
    public function predictPeriods(float $homeAvgGoals, float $awayAvgGoals): array
    {
        // Distribution typique des buts par periode en NHL
        // P1: 30%, P2: 35%, P3: 35%
        $homeP1 = $homeAvgGoals * 0.30;
        $homeP2 = $homeAvgGoals * 0.35;
        $homeP3 = $homeAvgGoals * 0.35;

        $awayP1 = $awayAvgGoals * 0.30;
        $awayP2 = $awayAvgGoals * 0.35;
        $awayP3 = $awayAvgGoals * 0.35;

        return [
            'p1' => [
                'home' => round($homeP1, 2),
                'away' => round($awayP1, 2),
                'total' => round($homeP1 + $awayP1, 2),
                'over_1_5' => $this->calculateOverProbability($homeP1 + $awayP1, 1.5),
            ],
            'p2' => [
                'home' => round($homeP2, 2),
                'away' => round($awayP2, 2),
                'total' => round($homeP2 + $awayP2, 2),
                'over_1_5' => $this->calculateOverProbability($homeP2 + $awayP2, 1.5),
            ],
            'p3' => [
                'home' => round($homeP3, 2),
                'away' => round($awayP3, 2),
                'total' => round($homeP3 + $awayP3, 2),
                'over_1_5' => $this->calculateOverProbability($homeP3 + $awayP3, 1.5),
            ],
            'highest_scoring_period' => 'P2/P3',
        ];
    }

    /**
     * Predit le handicap.
     */
    public function predictHandicap(float $homeAvgGoals, float $awayAvgGoals): array
    {
        $expectedDiff = ($homeAvgGoals * 1.03) - ($awayAvgGoals * 0.97);

        $lines = [-2.5, -1.5, -0.5, 0.5, 1.5, 2.5];
        $predictions = [];

        foreach ($lines as $line) {
            // Probabilite que home couvre le handicap
            $coverProb = $this->calculateHandicapProbability($expectedDiff, $line);
            $predictions["home_{$line}"] = round($coverProb, 1);
            $predictions['away_'.(-$line)] = round(100 - $coverProb, 1);
        }

        // Meilleur handicap
        $bestLine = $expectedDiff > 0 ? -1.5 : 1.5;
        $bestProb = $predictions["home_{$bestLine}"];

        return [
            'expected_margin' => round($expectedDiff, 2),
            'predictions' => $predictions,
            'best_bet' => [
                'team' => $expectedDiff > 0 ? 'home' : 'away',
                'line' => $bestLine,
                'probability' => $bestProb,
            ],
            'confidence' => round(min(90, abs($expectedDiff) * 15 + 50), 1),
        ];
    }

    /**
     * Predit la probabilite d'overtime.
     */
    public function predictOvertime(float $homeAvgGoals, float $awayAvgGoals): array
    {
        // Plus les equipes sont proches, plus l'OT est probable
        $diff = abs($homeAvgGoals - $awayAvgGoals);
        $otProb = max(5, 20 - ($diff * 8));

        return [
            'probability' => round($otProb, 1),
            'description' => $otProb > 15 ? 'Match serre, OT possible' : 'OT peu probable',
        ];
    }

    /**
     * Calcule la probabilite over.
     */
    private function calculateOverProbability(float $expected, float $line): float
    {
        $diff = $expected - $line;
        $prob = 50 + ($diff * 12);

        return max(5, min(95, $prob));
    }

    /**
     * Calcule la probabilite de couvrir un handicap.
     */
    private function calculateHandicapProbability(float $expectedMargin, float $line): float
    {
        // line negative = home doit gagner par plus de |line|
        $diff = $expectedMargin - $line;
        $prob = 50 + ($diff * 15);

        return max(5, min(95, $prob));
    }
}
