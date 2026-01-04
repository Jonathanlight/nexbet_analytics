<?php

declare(strict_types=1);

namespace App\Service\Math;

/**
 * Service Monte Carlo avancé avec:
 * - Corrélation entre les scores des équipes
 * - Analyse de variance et intervalles de confiance
 * - Simulation de scénarios multiples
 * - Support pour différentes distributions
 */
class AdvancedMonteCarloService
{
    private const DEFAULT_SIMULATIONS = 10000;
    private const CORRELATION_FACTOR = -0.1; // Corrélation négative légère entre les buts

    /**
     * Simule un match de football avec corrélation.
     */
    public function simulateFootballMatchAdvanced(
        float $homeExpectedGoals,
        float $awayExpectedGoals,
        float $homeVariance = 0.0,
        float $awayVariance = 0.0,
        float $correlation = self::CORRELATION_FACTOR,
        int $simulations = self::DEFAULT_SIMULATIONS,
    ): array {
        $results = $this->initializeResults();
        $scores = [];
        $margins = [];
        $totalGoals = [];

        for ($i = 0; $i < $simulations; ++$i) {
            // Générer des buts corrélés
            [$homeGoals, $awayGoals] = $this->generateCorrelatedGoals(
                $homeExpectedGoals,
                $awayExpectedGoals,
                $homeVariance,
                $awayVariance,
                $correlation
            );

            $total = $homeGoals + $awayGoals;
            $margin = $homeGoals - $awayGoals;
            $scoreKey = "{$homeGoals}-{$awayGoals}";

            $scores[] = $scoreKey;
            $margins[] = $margin;
            $totalGoals[] = $total;

            $this->updateResults($results, $homeGoals, $awayGoals, $total);
        }

        return $this->calculateAdvancedProbabilities($results, $simulations, $margins, $totalGoals, $scores);
    }

    /**
     * Simule avec des facteurs dynamiques.
     */
    public function simulateWithDynamicFactors(
        float $homeExpectedGoals,
        float $awayExpectedGoals,
        array $factors,
        int $simulations = self::DEFAULT_SIMULATIONS,
    ): array {
        // Appliquer les ajustements des facteurs
        $adjustments = $this->calculateFactorAdjustments($factors);

        $adjustedHomeXg = $homeExpectedGoals * $adjustments['home_multiplier'];
        $adjustedAwayXg = $awayExpectedGoals * $adjustments['away_multiplier'];

        // Variance ajustée selon l'incertitude des facteurs
        $homeVariance = $adjustments['uncertainty'] * $homeExpectedGoals * 0.3;
        $awayVariance = $adjustments['uncertainty'] * $awayExpectedGoals * 0.3;

        $baseResult = $this->simulateFootballMatchAdvanced(
            $adjustedHomeXg,
            $adjustedAwayXg,
            $homeVariance,
            $awayVariance,
            $adjustments['correlation'],
            $simulations
        );

        $baseResult['factor_adjustments'] = $adjustments;
        $baseResult['original_xg'] = [
            'home' => $homeExpectedGoals,
            'away' => $awayExpectedGoals,
        ];
        $baseResult['adjusted_xg'] = [
            'home' => round($adjustedHomeXg, 3),
            'away' => round($adjustedAwayXg, 3),
        ];

        return $baseResult;
    }

    /**
     * Simule les résultats avec analyse de sensibilité.
     */
    public function sensitivityAnalysis(
        float $homeExpectedGoals,
        float $awayExpectedGoals,
        float $xgVariation = 0.3,
        int $scenarios = 5,
        int $simulationsPerScenario = 5000,
    ): array {
        $results = [];
        $variations = [];

        // Générer les variations
        for ($i = 0; $i < $scenarios; ++$i) {
            $factor = 1 + ($xgVariation * (2 * $i / ($scenarios - 1) - 1));
            $variations[] = $factor;
        }

        foreach ($variations as $homeVar) {
            foreach ($variations as $awayVar) {
                $adjustedHome = $homeExpectedGoals * $homeVar;
                $adjustedAway = $awayExpectedGoals * $awayVar;

                $sim = $this->simulateFootballMatchAdvanced(
                    $adjustedHome,
                    $adjustedAway,
                    0,
                    0,
                    self::CORRELATION_FACTOR,
                    $simulationsPerScenario
                );

                $results[] = [
                    'home_factor' => round($homeVar, 2),
                    'away_factor' => round($awayVar, 2),
                    'home_xg' => round($adjustedHome, 2),
                    'away_xg' => round($adjustedAway, 2),
                    'result_probs' => $sim['result'],
                    'over_2_5' => $sim['over_under']['over_25'],
                ];
            }
        }

        // Calculer les statistiques de sensibilité
        $homeWinProbs = array_column(array_column($results, 'result_probs'), '1');
        $drawProbs = array_column(array_column($results, 'result_probs'), 'X');
        $awayWinProbs = array_column(array_column($results, 'result_probs'), '2');

        return [
            'scenarios' => $results,
            'sensitivity_summary' => [
                'home_win' => [
                    'min' => round(min($homeWinProbs), 2),
                    'max' => round(max($homeWinProbs), 2),
                    'range' => round(max($homeWinProbs) - min($homeWinProbs), 2),
                ],
                'draw' => [
                    'min' => round(min($drawProbs), 2),
                    'max' => round(max($drawProbs), 2),
                    'range' => round(max($drawProbs) - min($drawProbs), 2),
                ],
                'away_win' => [
                    'min' => round(min($awayWinProbs), 2),
                    'max' => round(max($awayWinProbs), 2),
                    'range' => round(max($awayWinProbs) - min($awayWinProbs), 2),
                ],
            ],
            'robustness_score' => $this->calculateRobustnessScore($homeWinProbs, $drawProbs, $awayWinProbs),
        ];
    }

    /**
     * Simule les mi-temps séparément.
     */
    public function simulateHalfTimes(
        float $homeExpectedGoals,
        float $awayExpectedGoals,
        float $firstHalfRatio = 0.45,
        int $simulations = self::DEFAULT_SIMULATIONS,
    ): array {
        $firstHalfResults = $this->initializeResults();
        $secondHalfResults = $this->initializeResults();
        $htftCombinations = [];

        $homeFirstHalfXg = $homeExpectedGoals * $firstHalfRatio;
        $homeSecondHalfXg = $homeExpectedGoals * (1 - $firstHalfRatio);
        $awayFirstHalfXg = $awayExpectedGoals * $firstHalfRatio;
        $awaySecondHalfXg = $awayExpectedGoals * (1 - $firstHalfRatio);

        for ($i = 0; $i < $simulations; ++$i) {
            // Première mi-temps
            $homeHT = $this->poissonRandom($homeFirstHalfXg);
            $awayHT = $this->poissonRandom($awayFirstHalfXg);

            // Deuxième mi-temps (légèrement corrélée avec la première)
            $momentumFactor = ($homeHT > $awayHT) ? 0.05 : (($homeHT < $awayHT) ? -0.05 : 0);
            $homeFT = $this->poissonRandom($homeSecondHalfXg * (1 + $momentumFactor));
            $awayFT = $this->poissonRandom($awaySecondHalfXg * (1 - $momentumFactor));

            // Mise à jour des résultats
            $this->updateResults($firstHalfResults, $homeHT, $awayHT, $homeHT + $awayHT);
            $this->updateResults($secondHalfResults, $homeFT, $awayFT, $homeFT + $awayFT);

            // Combinaison HT/FT
            $htResult = $homeHT > $awayHT ? 'H' : ($homeHT < $awayHT ? 'A' : 'D');
            $ftResult = ($homeHT + $homeFT) > ($awayHT + $awayFT) ? 'H' : (($homeHT + $homeFT) < ($awayHT + $awayFT) ? 'A' : 'D');
            $htftKey = "{$htResult}/{$ftResult}";
            $htftCombinations[$htftKey] = ($htftCombinations[$htftKey] ?? 0) + 1;
        }

        // Calculer les probabilités
        $htProbs = $this->calculateResultProbs($firstHalfResults, $simulations);
        $ftProbs = $this->calculateResultProbs($secondHalfResults, $simulations);

        // Normaliser les combinaisons HT/FT
        foreach ($htftCombinations as $key => $count) {
            $htftCombinations[$key] = round(($count / $simulations) * 100, 2);
        }
        arsort($htftCombinations);

        return [
            'first_half' => $htProbs,
            'second_half' => $ftProbs,
            'htft_combinations' => $htftCombinations,
            'draw_at_ht_probability' => round(($firstHalfResults['draws'] / $simulations) * 100, 2),
            'comeback_probability' => $this->calculateComebackProbability($htftCombinations),
        ];
    }

    /**
     * Simule le score exact avec distribution.
     */
    public function simulateExactScoreDistribution(
        float $homeExpectedGoals,
        float $awayExpectedGoals,
        int $simulations = self::DEFAULT_SIMULATIONS,
    ): array {
        $scoreCount = [];

        for ($i = 0; $i < $simulations; ++$i) {
            $homeGoals = $this->poissonRandom($homeExpectedGoals);
            $awayGoals = $this->poissonRandom($awayExpectedGoals);
            $key = "{$homeGoals}-{$awayGoals}";
            $scoreCount[$key] = ($scoreCount[$key] ?? 0) + 1;
        }

        // Convertir en probabilités et trier
        $distribution = [];
        foreach ($scoreCount as $score => $count) {
            $distribution[$score] = [
                'probability' => round(($count / $simulations) * 100, 2),
                'fair_odds' => round($simulations / $count, 2),
            ];
        }

        uasort($distribution, fn ($a, $b) => $b['probability'] <=> $a['probability']);

        return [
            'distribution' => array_slice($distribution, 0, 20, true),
            'total_unique_scores' => count($scoreCount),
            'most_likely' => array_key_first($distribution),
            'confidence_top_5' => round(array_sum(array_column(array_slice($distribution, 0, 5, true), 'probability')), 2),
        ];
    }

    /**
     * Génère des buts corrélés en utilisant une copule gaussienne.
     */
    private function generateCorrelatedGoals(
        float $homeXg,
        float $awayXg,
        float $homeVar,
        float $awayVar,
        float $correlation,
    ): array {
        // Générer des variables normales corrélées
        $u1 = $this->boxMullerRandom();
        $u2 = $this->boxMullerRandom();

        // Appliquer la corrélation
        $z1 = $u1;
        $z2 = $correlation * $u1 + sqrt(1 - $correlation * $correlation) * $u2;

        // Convertir en probabilités uniformes
        $p1 = $this->normalCDF($z1);
        $p2 = $this->normalCDF($z2);

        // Ajouter de la variance si spécifiée
        $adjustedHomeXg = $homeXg + $homeVar * $z1;
        $adjustedAwayXg = $awayXg + $awayVar * $z2;

        // Générer les buts via Poisson
        $homeGoals = $this->poissonRandom(max(0.1, $adjustedHomeXg));
        $awayGoals = $this->poissonRandom(max(0.1, $adjustedAwayXg));

        return [$homeGoals, $awayGoals];
    }

    private function poissonRandom(float $lambda): int
    {
        if ($lambda <= 0) {
            return 0;
        }

        $L = exp(-$lambda);
        $k = 0;
        $p = 1.0;

        do {
            ++$k;
            $p *= (mt_rand() / mt_getrandmax());
        } while ($p > $L);

        return $k - 1;
    }

    private function boxMullerRandom(): float
    {
        $u1 = max(0.0001, mt_rand() / mt_getrandmax());
        $u2 = mt_rand() / mt_getrandmax();

        return sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);
    }

    private function normalCDF(float $x): float
    {
        return 0.5 * (1 + $this->erf($x / sqrt(2)));
    }

    private function erf(float $x): float
    {
        $a1 = 0.254829592;
        $a2 = -0.284496736;
        $a3 = 1.421413741;
        $a4 = -1.453152027;
        $a5 = 1.061405429;
        $p = 0.3275911;

        $sign = $x < 0 ? -1 : 1;
        $x = abs($x);

        $t = 1.0 / (1.0 + $p * $x);
        $y = 1.0 - (((($a5 * $t + $a4) * $t + $a3) * $t + $a2) * $t + $a1) * $t * exp(-$x * $x);

        return $sign * $y;
    }

    private function initializeResults(): array
    {
        return [
            'home_wins' => 0,
            'draws' => 0,
            'away_wins' => 0,
            'over_05' => 0,
            'over_15' => 0,
            'over_25' => 0,
            'over_35' => 0,
            'over_45' => 0,
            'btts' => 0,
            'home_clean_sheet' => 0,
            'away_clean_sheet' => 0,
        ];
    }

    private function updateResults(array &$results, int $homeGoals, int $awayGoals, int $total): void
    {
        if ($homeGoals > $awayGoals) {
            ++$results['home_wins'];
        } elseif ($homeGoals === $awayGoals) {
            ++$results['draws'];
        } else {
            ++$results['away_wins'];
        }

        if ($total > 0.5) {
            ++$results['over_05'];
        }
        if ($total > 1.5) {
            ++$results['over_15'];
        }
        if ($total > 2.5) {
            ++$results['over_25'];
        }
        if ($total > 3.5) {
            ++$results['over_35'];
        }
        if ($total > 4.5) {
            ++$results['over_45'];
        }

        if ($homeGoals > 0 && $awayGoals > 0) {
            ++$results['btts'];
        }
        if (0 === $awayGoals) {
            ++$results['home_clean_sheet'];
        }
        if (0 === $homeGoals) {
            ++$results['away_clean_sheet'];
        }
    }

    private function calculateAdvancedProbabilities(
        array $results,
        int $simulations,
        array $margins,
        array $totalGoals,
        array $scores,
    ): array {
        // Statistiques de base
        $avgMargin = array_sum($margins) / count($margins);
        $avgTotal = array_sum($totalGoals) / count($totalGoals);

        // Variance et écart-type
        $marginVariance = $this->calculateVariance($margins, $avgMargin);
        $totalVariance = $this->calculateVariance($totalGoals, $avgTotal);

        // Intervalles de confiance à 95%
        $marginStdDev = sqrt($marginVariance);
        $totalStdDev = sqrt($totalVariance);
        $marginError = 1.96 * $marginStdDev / sqrt($simulations);
        $totalError = 1.96 * $totalStdDev / sqrt($simulations);

        // Distribution des scores
        $scoreCounts = array_count_values($scores);
        arsort($scoreCounts);

        return [
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
                'over_45' => round(($results['over_45'] / $simulations) * 100, 2),
            ],
            'btts' => [
                'yes' => round(($results['btts'] / $simulations) * 100, 2),
                'no' => round((($simulations - $results['btts']) / $simulations) * 100, 2),
            ],
            'clean_sheets' => [
                'home' => round(($results['home_clean_sheet'] / $simulations) * 100, 2),
                'away' => round(($results['away_clean_sheet'] / $simulations) * 100, 2),
            ],
            'statistics' => [
                'avg_margin' => round($avgMargin, 3),
                'avg_total_goals' => round($avgTotal, 3),
                'margin_std_dev' => round($marginStdDev, 3),
                'total_goals_std_dev' => round($totalStdDev, 3),
            ],
            'confidence_intervals' => [
                'margin_95' => [
                    'lower' => round($avgMargin - $marginError, 3),
                    'upper' => round($avgMargin + $marginError, 3),
                ],
                'total_goals_95' => [
                    'lower' => round($avgTotal - $totalError, 3),
                    'upper' => round($avgTotal + $totalError, 3),
                ],
            ],
            'most_likely_scores' => array_map(
                fn ($count) => round(($count / $simulations) * 100, 2),
                array_slice($scoreCounts, 0, 5, true)
            ),
        ];
    }

    private function calculateVariance(array $values, float $mean): float
    {
        $squaredDiffs = array_map(fn ($v) => pow($v - $mean, 2), $values);

        return array_sum($squaredDiffs) / count($values);
    }

    private function calculateFactorAdjustments(array $factors): array
    {
        $homeMultiplier = 1.0;
        $awayMultiplier = 1.0;
        $uncertainty = 0.5;
        $correlation = self::CORRELATION_FACTOR;

        // Forme récente
        if (isset($factors['home_form'])) {
            $homeMultiplier *= 1 + ($factors['home_form'] * 0.05);
        }
        if (isset($factors['away_form'])) {
            $awayMultiplier *= 1 + ($factors['away_form'] * 0.05);
        }

        // Avantage domicile
        if (isset($factors['home_advantage'])) {
            $homeMultiplier *= 1 + $factors['home_advantage'];
        }

        // Blessures
        if (isset($factors['home_injuries'])) {
            $homeMultiplier *= 1 - ($factors['home_injuries'] * 0.08);
            $uncertainty += 0.1 * $factors['home_injuries'];
        }
        if (isset($factors['away_injuries'])) {
            $awayMultiplier *= 1 - ($factors['away_injuries'] * 0.08);
            $uncertainty += 0.1 * $factors['away_injuries'];
        }

        // Météo
        if (isset($factors['weather']) && 'bad' === $factors['weather']) {
            $homeMultiplier *= 0.9;
            $awayMultiplier *= 0.9;
            $uncertainty += 0.15;
        }

        // Importance du match
        if (isset($factors['importance']) && 'high' === $factors['importance']) {
            $correlation -= 0.05; // Matchs importants = moins de buts
        }

        // Head to head
        if (isset($factors['h2h_home_advantage'])) {
            $homeMultiplier *= 1 + ($factors['h2h_home_advantage'] * 0.02);
        }

        return [
            'home_multiplier' => round($homeMultiplier, 4),
            'away_multiplier' => round($awayMultiplier, 4),
            'uncertainty' => round(min(1.5, $uncertainty), 2),
            'correlation' => round($correlation, 3),
        ];
    }

    private function calculateResultProbs(array $results, int $simulations): array
    {
        return [
            '1' => round(($results['home_wins'] / $simulations) * 100, 2),
            'X' => round(($results['draws'] / $simulations) * 100, 2),
            '2' => round(($results['away_wins'] / $simulations) * 100, 2),
        ];
    }

    private function calculateComebackProbability(array $htft): float
    {
        $comebackHome = ($htft['A/H'] ?? 0) + ($htft['A/D'] ?? 0);
        $comebackAway = ($htft['H/A'] ?? 0) + ($htft['H/D'] ?? 0);

        return round($comebackHome + $comebackAway, 2);
    }

    private function calculateRobustnessScore(array $homeWins, array $draws, array $awayWins): float
    {
        // Score basé sur la stabilité des prédictions
        $homeRange = max($homeWins) - min($homeWins);
        $drawRange = max($draws) - min($draws);
        $awayRange = max($awayWins) - min($awayWins);

        $totalRange = $homeRange + $drawRange + $awayRange;

        // Plus la range est petite, plus c'est robuste (100 = très robuste)
        return round(max(0, 100 - $totalRange), 2);
    }
}
