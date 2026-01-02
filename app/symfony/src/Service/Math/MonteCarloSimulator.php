<?php

declare(strict_types=1);

namespace App\Service\Math;

/**
 * Service de simulation Monte Carlo pour les prédictions sportives.
 * Effectue des milliers de simulations pour estimer les probabilités.
 */
class MonteCarloSimulator
{
    private const DEFAULT_SIMULATIONS = 10000;

    /**
     * Simule un match de football.
     */
    public function simulateFootballMatch(
        float $homeExpectedGoals,
        float $awayExpectedGoals,
        int $simulations = self::DEFAULT_SIMULATIONS,
    ): array {
        $results = [
            'home_wins' => 0,
            'draws' => 0,
            'away_wins' => 0,
            'over_05' => 0,
            'over_15' => 0,
            'over_25' => 0,
            'over_35' => 0,
            'btts' => 0,
            'scores' => [],
        ];

        for ($i = 0; $i < $simulations; ++$i) {
            $homeGoals = $this->poissonRandom($homeExpectedGoals);
            $awayGoals = $this->poissonRandom($awayExpectedGoals);

            $totalGoals = $homeGoals + $awayGoals;
            $scoreKey = "{$homeGoals}-{$awayGoals}";

            // Résultat du match
            if ($homeGoals > $awayGoals) {
                ++$results['home_wins'];
            } elseif ($homeGoals === $awayGoals) {
                ++$results['draws'];
            } else {
                ++$results['away_wins'];
            }

            // Over/Under
            if ($totalGoals > 0.5) {
                ++$results['over_05'];
            }
            if ($totalGoals > 1.5) {
                ++$results['over_15'];
            }
            if ($totalGoals > 2.5) {
                ++$results['over_25'];
            }
            if ($totalGoals > 3.5) {
                ++$results['over_35'];
            }

            // BTTS
            if ($homeGoals > 0 && $awayGoals > 0) {
                ++$results['btts'];
            }

            // Distribution des scores
            if (!isset($results['scores'][$scoreKey])) {
                $results['scores'][$scoreKey] = 0;
            }
            ++$results['scores'][$scoreKey];
        }

        return $this->calculateProbabilities($results, $simulations);
    }

    /**
     * Simule un match de basketball.
     */
    public function simulateBasketballMatch(
        float $homeAvgPoints,
        float $awayAvgPoints,
        float $homeStdDev = 10.0,
        float $awayStdDev = 10.0,
        int $simulations = self::DEFAULT_SIMULATIONS,
    ): array {
        $results = [
            'home_wins' => 0,
            'away_wins' => 0,
            'overtime' => 0,
            'point_differences' => [],
            'total_points' => [],
        ];

        for ($i = 0; $i < $simulations; ++$i) {
            $homePoints = max(0, round($this->normalRandom($homeAvgPoints, $homeStdDev)));
            $awayPoints = max(0, round($this->normalRandom($awayAvgPoints, $awayStdDev)));

            $pointDiff = abs($homePoints - $awayPoints);
            $totalPoints = $homePoints + $awayPoints;

            // Résultat
            if ($homePoints > $awayPoints) {
                ++$results['home_wins'];
            } elseif ($homePoints < $awayPoints) {
                ++$results['away_wins'];
            } else {
                ++$results['overtime'];
                // En overtime, assigner aléatoirement
                if (0 === rand(0, 1)) {
                    ++$results['home_wins'];
                    $pointDiff = rand(1, 5);
                } else {
                    ++$results['away_wins'];
                    $pointDiff = rand(1, 5);
                }
            }

            // Stocker les différences de points
            $diffKey = $this->getPointDifferenceRange($pointDiff);
            if (!isset($results['point_differences'][$diffKey])) {
                $results['point_differences'][$diffKey] = 0;
            }
            ++$results['point_differences'][$diffKey];

            // Stocker les totaux de points
            $totalKey = $this->getTotalPointsRange($totalPoints);
            if (!isset($results['total_points'][$totalKey])) {
                $results['total_points'][$totalKey] = 0;
            }
            ++$results['total_points'][$totalKey];
        }

        return $this->calculateBasketballProbabilities($results, $simulations);
    }

    /**
     * Simule avec des facteurs avancés.
     */
    public function simulateWithFactors(
        float $homeExpectedGoals,
        float $awayExpectedGoals,
        array $factors = [],
        int $simulations = self::DEFAULT_SIMULATIONS,
    ): array {
        // Facteurs possibles: home_advantage, weather, injuries, form, referee
        $homeAdjustment = 1.0;
        $awayAdjustment = 1.0;

        if (isset($factors['home_advantage'])) {
            $homeAdjustment *= (1 + $factors['home_advantage']);
        }

        if (isset($factors['weather']) && 'bad' === $factors['weather']) {
            $homeAdjustment *= 0.85;
            $awayAdjustment *= 0.85;
        }

        if (isset($factors['home_injuries'])) {
            $homeAdjustment *= (1 - ($factors['home_injuries'] * 0.1));
        }

        if (isset($factors['away_injuries'])) {
            $awayAdjustment *= (1 - ($factors['away_injuries'] * 0.1));
        }

        if (isset($factors['home_form'])) {
            $homeAdjustment *= (1 + ($factors['home_form'] * 0.05));
        }

        if (isset($factors['away_form'])) {
            $awayAdjustment *= (1 + ($factors['away_form'] * 0.05));
        }

        $adjustedHomeGoals = $homeExpectedGoals * $homeAdjustment;
        $adjustedAwayGoals = $awayExpectedGoals * $awayAdjustment;

        return $this->simulateFootballMatch($adjustedHomeGoals, $adjustedAwayGoals, $simulations);
    }

    /**
     * Génère un nombre aléatoire suivant une distribution de Poisson.
     */
    private function poissonRandom(float $lambda): int
    {
        $L = exp(-$lambda);
        $k = 0;
        $p = 1.0;

        do {
            ++$k;
            $p *= (mt_rand() / mt_getrandmax());
        } while ($p > $L);

        return $k - 1;
    }

    /**
     * Génère un nombre aléatoire suivant une distribution normale.
     */
    private function normalRandom(float $mean, float $stdDev): float
    {
        // Box-Muller transform
        $u1 = mt_rand() / mt_getrandmax();
        $u2 = mt_rand() / mt_getrandmax();

        $z0 = sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);

        return $mean + $stdDev * $z0;
    }

    /**
     * Calcule les probabilités en pourcentage.
     */
    private function calculateProbabilities(array $results, int $simulations): array
    {
        $probabilities = [
            'result' => [
                '1' => round(($results['home_wins'] / $simulations) * 100, 2),
                'X' => round(($results['draws'] / $simulations) * 100, 2),
                '2' => round(($results['away_wins'] / $simulations) * 100, 2),
            ],
            'over_under' => [
                'over_05' => round(($results['over_05'] / $simulations) * 100, 2),
                'over_15' => round(($results['over_15'] / $simulations) * 100, 2),
                'over_25' => round(($results['over_25'] / $simulations) * 100, 2),
                'over_35' => round(($results['over_35'] / $simulations) * 100, 2),
            ],
            'btts' => [
                'yes' => round(($results['btts'] / $simulations) * 100, 2),
                'no' => round((($simulations - $results['btts']) / $simulations) * 100, 2),
            ],
            'most_likely_scores' => [],
        ];

        // Trier les scores par fréquence
        arsort($results['scores']);
        $topScores = array_slice($results['scores'], 0, 5, true);

        foreach ($topScores as $score => $count) {
            $probabilities['most_likely_scores'][$score] = round(($count / $simulations) * 100, 2);
        }

        return $probabilities;
    }

    /**
     * Calcule les probabilités pour le basketball.
     */
    private function calculateBasketballProbabilities(array $results, int $simulations): array
    {
        $probabilities = [
            'result' => [
                'home' => round(($results['home_wins'] / $simulations) * 100, 2),
                'away' => round(($results['away_wins'] / $simulations) * 100, 2),
            ],
            'overtime_probability' => round(($results['overtime'] / $simulations) * 100, 2),
            'point_differences' => [],
            'total_points' => [],
        ];

        foreach ($results['point_differences'] as $range => $count) {
            $probabilities['point_differences'][$range] = round(($count / $simulations) * 100, 2);
        }

        foreach ($results['total_points'] as $range => $count) {
            $probabilities['total_points'][$range] = round(($count / $simulations) * 100, 2);
        }

        return $probabilities;
    }

    /**
     * Détermine la plage de différence de points.
     */
    private function getPointDifferenceRange(int $diff): string
    {
        if ($diff <= 5) {
            return '1-5';
        }
        if ($diff <= 10) {
            return '6-10';
        }
        if ($diff <= 15) {
            return '11-15';
        }
        if ($diff <= 20) {
            return '16-20';
        }

        return '21+';
    }

    /**
     * Détermine la plage de total de points.
     */
    private function getTotalPointsRange(int $total): string
    {
        if ($total < 180) {
            return 'under_180';
        }
        if ($total < 190) {
            return '180-189';
        }
        if ($total < 200) {
            return '190-199';
        }
        if ($total < 210) {
            return '200-209';
        }
        if ($total < 220) {
            return '210-219';
        }

        return '220+';
    }
}
