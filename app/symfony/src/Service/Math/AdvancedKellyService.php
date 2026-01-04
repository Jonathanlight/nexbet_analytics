<?php

declare(strict_types=1);

namespace App\Service\Math;

/**
 * Service Kelly avancé avec:
 * - Fractional Kelly pour différents niveaux de risque
 * - Détection de l'edge et value betting
 * - Optimisation de portefeuille multi-paris
 * - Calcul du drawdown maximum
 * - Simulation de croissance de bankroll
 */
class AdvancedKellyService
{
    // Fractions de Kelly recommandées
    private const FULL_KELLY = 1.0;
    private const HALF_KELLY = 0.5;
    private const QUARTER_KELLY = 0.25;
    private const EIGHTH_KELLY = 0.125;

    /**
     * Calcule le Kelly avec analyse complète.
     */
    public function analyzeKelly(float $probability, float $odds, float $bankroll): array
    {
        $p = $probability / 100;
        $q = 1 - $p;
        $b = $odds - 1;

        // Fraction Kelly optimale
        $kellyFraction = $this->calculateOptimalKelly($p, $b);

        // Edge (avantage)
        $edge = $this->calculateEdge($p, $odds);

        // Expected Value
        $ev = $this->calculateEV($p, $odds);

        // Cote juste (fair odds)
        $fairOdds = $this->calculateFairOdds($p);

        // Coefficient de variation pour mesurer le risque
        $variance = $p * pow(1 + $b, 2) + $q * 1 - pow(1 + $p * $b, 2);
        $cv = sqrt(abs($variance)) / (1 + $p * $b);

        // Calculer les différentes mises
        $stakes = $this->calculateStakes($kellyFraction, $bankroll);

        // Risk of Ruin
        $ror = $this->calculateRiskOfRuin($p, $b, $kellyFraction);

        return [
            'kelly_fraction' => round($kellyFraction * 100, 2),
            'edge' => round($edge, 2),
            'ev_percentage' => round($ev * 100, 2),
            'fair_odds' => round($fairOdds, 2),
            'is_value_bet' => $odds > $fairOdds,
            'value_percentage' => round((($odds / $fairOdds) - 1) * 100, 2),
            'stakes' => $stakes,
            'risk_assessment' => $this->assessRisk($kellyFraction, $edge, $cv),
            'risk_of_ruin' => round($ror * 100, 4),
            'coefficient_of_variation' => round($cv, 4),
            'recommendation' => $this->getRecommendation($kellyFraction, $edge),
        ];
    }

    /**
     * Optimise un portefeuille de paris simultanés.
     */
    public function optimizePortfolio(array $bets, float $bankroll): array
    {
        if (empty($bets)) {
            return ['bets' => [], 'total_stake' => 0, 'expected_profit' => 0];
        }

        $optimizedBets = [];
        $totalKelly = 0;

        // Calculer le Kelly pour chaque pari
        foreach ($bets as $bet) {
            $p = ($bet['probability'] ?? 50) / 100;
            $b = ($bet['odds'] ?? 1.5) - 1;

            $kelly = $this->calculateOptimalKelly($p, $b);
            if ($kelly > 0) {
                $totalKelly += $kelly;
                $optimizedBets[] = array_merge($bet, [
                    'kelly_fraction' => $kelly,
                    'edge' => round($this->calculateEdge($p, $bet['odds'] ?? 1.5), 2),
                ]);
            }
        }

        // Normaliser si la somme des Kelly dépasse 100%
        $scaleFactor = 1.0;
        if ($totalKelly > 1.0) {
            $scaleFactor = 0.8 / $totalKelly; // Limiter à 80% max du bankroll
        }

        // Appliquer Half Kelly par sécurité pour les paris multiples
        $safetyFactor = 0.5;

        $totalStake = 0;
        $expectedProfit = 0;

        foreach ($optimizedBets as &$bet) {
            $adjustedKelly = $bet['kelly_fraction'] * $scaleFactor * $safetyFactor;
            $stake = round($bankroll * $adjustedKelly, 2);

            $bet['recommended_stake'] = $stake;
            $bet['stake_percentage'] = round($adjustedKelly * 100, 2);

            $p = ($bet['probability'] ?? 50) / 100;
            $bet['expected_profit'] = round($stake * (($bet['odds'] ?? 1.5) * $p - 1), 2);

            $totalStake += $stake;
            $expectedProfit += $bet['expected_profit'];
        }

        return [
            'bets' => $optimizedBets,
            'total_stake' => round($totalStake, 2),
            'stake_percentage' => round(($totalStake / $bankroll) * 100, 2),
            'expected_profit' => round($expectedProfit, 2),
            'expected_roi' => $totalStake > 0 ? round(($expectedProfit / $totalStake) * 100, 2) : 0,
            'diversification_score' => $this->calculateDiversificationScore($optimizedBets),
        ];
    }

    /**
     * Simule la croissance du bankroll sur N paris.
     */
    public function simulateBankrollGrowth(
        float $probability,
        float $odds,
        float $initialBankroll,
        int $numberOfBets,
        float $kellyFraction = self::HALF_KELLY,
    ): array {
        $p = $probability / 100;
        $b = $odds - 1;
        $optimalKelly = $this->calculateOptimalKelly($p, $b);
        $usedKelly = $optimalKelly * $kellyFraction;

        // Calcul théorique de la croissance
        $growthRate = $p * log(1 + $usedKelly * $b) + (1 - $p) * log(1 - $usedKelly);
        $expectedFinalBankroll = $initialBankroll * exp($growthRate * $numberOfBets);

        // Simulation Monte Carlo
        $simulations = 1000;
        $finalBankrolls = [];

        for ($sim = 0; $sim < $simulations; ++$sim) {
            $bankroll = $initialBankroll;
            $maxBankroll = $initialBankroll;
            $minBankroll = $initialBankroll;

            for ($bet = 0; $bet < $numberOfBets; ++$bet) {
                $stake = $bankroll * $usedKelly;

                // Simuler le résultat
                if (mt_rand() / mt_getrandmax() < $p) {
                    $bankroll += $stake * $b;
                } else {
                    $bankroll -= $stake;
                }

                $maxBankroll = max($maxBankroll, $bankroll);
                $minBankroll = min($minBankroll, $bankroll);
            }

            $finalBankrolls[] = [
                'final' => $bankroll,
                'max' => $maxBankroll,
                'min' => $minBankroll,
                'max_drawdown' => ($maxBankroll - $minBankroll) / $maxBankroll,
            ];
        }

        // Analyser les résultats
        $finals = array_column($finalBankrolls, 'final');
        $drawdowns = array_column($finalBankrolls, 'max_drawdown');

        sort($finals);

        return [
            'initial_bankroll' => $initialBankroll,
            'number_of_bets' => $numberOfBets,
            'kelly_used' => round($usedKelly * 100, 2),
            'theoretical_growth_rate' => round($growthRate * 100, 4),
            'expected_final_bankroll' => round($expectedFinalBankroll, 2),
            'median_final_bankroll' => round($finals[(int) ($simulations * 0.5)], 2),
            'percentile_5' => round($finals[(int) ($simulations * 0.05)], 2),
            'percentile_95' => round($finals[(int) ($simulations * 0.95)], 2),
            'probability_of_profit' => round(count(array_filter($finals, fn ($f) => $f > $initialBankroll)) / $simulations * 100, 2),
            'probability_of_doubling' => round(count(array_filter($finals, fn ($f) => $f > $initialBankroll * 2)) / $simulations * 100, 2),
            'probability_of_ruin' => round(count(array_filter($finals, fn ($f) => $f < $initialBankroll * 0.1)) / $simulations * 100, 2),
            'average_max_drawdown' => round(array_sum($drawdowns) / $simulations * 100, 2),
        ];
    }

    /**
     * Calcule le Kelly pour un pari combiné (accumulator).
     */
    public function calculateAccumulatorKelly(array $selections, float $bankroll): array
    {
        if (empty($selections)) {
            return ['kelly' => 0, 'stake' => 0];
        }

        // Calculer la cote combinée et la probabilité combinée
        $combinedOdds = 1.0;
        $combinedProb = 1.0;

        foreach ($selections as $selection) {
            $combinedOdds *= $selection['odds'] ?? 1.5;
            $combinedProb *= ($selection['probability'] ?? 50) / 100;
        }

        $p = $combinedProb;
        $b = $combinedOdds - 1;

        $kelly = $this->calculateOptimalKelly($p, $b);

        // Pour les combinés, utiliser un Kelly encore plus conservateur
        $safeKelly = $kelly * 0.25;

        return [
            'combined_odds' => round($combinedOdds, 2),
            'combined_probability' => round($combinedProb * 100, 4),
            'full_kelly' => round($kelly * 100, 4),
            'safe_kelly' => round($safeKelly * 100, 4),
            'recommended_stake' => round($bankroll * $safeKelly, 2),
            'potential_return' => round($bankroll * $safeKelly * $combinedOdds, 2),
            'ev_percentage' => round(($combinedOdds * $combinedProb - 1) * 100, 2),
            'is_value' => $combinedOdds * $combinedProb > 1,
            'selections_count' => count($selections),
        ];
    }

    /**
     * Calcule l'ajustement de Kelly selon la confiance.
     */
    public function adjustKellyForConfidence(
        float $probability,
        float $odds,
        float $confidence,
        float $bankroll,
    ): array {
        $baseKelly = $this->analyzeKelly($probability, $odds, $bankroll);

        // Ajuster le Kelly selon la confiance
        // Confiance 100% = full recommended stake
        // Confiance 50% = moitié du recommended stake
        $confidenceFactor = $confidence / 100;

        // Ajuster aussi la probabilité perçue selon la confiance
        // Si confiance basse, régression vers 50%
        $adjustedProb = $probability * $confidenceFactor + 50 * (1 - $confidenceFactor);

        $adjustedKelly = $this->analyzeKelly($adjustedProb, $odds, $bankroll);

        return [
            'original_kelly' => $baseKelly,
            'adjusted_probability' => round($adjustedProb, 2),
            'adjusted_kelly' => $adjustedKelly,
            'confidence_factor' => round($confidenceFactor, 2),
            'final_recommended_stake' => round($adjustedKelly['stakes']['half_kelly'] * $confidenceFactor, 2),
        ];
    }

    private function calculateOptimalKelly(float $p, float $b): float
    {
        if ($b <= 0 || $p <= 0 || $p >= 1) {
            return 0;
        }

        $kelly = ($p * $b - (1 - $p)) / $b;

        return max(0, $kelly);
    }

    private function calculateEdge(float $p, float $odds): float
    {
        return ($p * $odds) - 1;
    }

    private function calculateEV(float $p, float $odds): float
    {
        return ($p * $odds) - 1;
    }

    private function calculateFairOdds(float $p): float
    {
        if ($p <= 0) {
            return PHP_FLOAT_MAX;
        }

        return 1 / $p;
    }

    private function calculateRiskOfRuin(float $p, float $b, float $kelly): float
    {
        if ($kelly <= 0 || $kelly >= 1) {
            return $kelly >= 1 ? 1 : 0;
        }

        // Formule simplifiée de Risk of Ruin
        $edge = $p * $b - (1 - $p);
        if ($edge <= 0) {
            return 1;
        }

        $variance = $p * $b * $b + (1 - $p);

        return exp(-2 * $edge / $variance);
    }

    private function calculateStakes(float $kellyFraction, float $bankroll): array
    {
        return [
            'full_kelly' => round($bankroll * $kellyFraction, 2),
            'half_kelly' => round($bankroll * $kellyFraction * 0.5, 2),
            'quarter_kelly' => round($bankroll * $kellyFraction * 0.25, 2),
            'eighth_kelly' => round($bankroll * $kellyFraction * 0.125, 2),
            'fixed_1_percent' => round($bankroll * 0.01, 2),
            'fixed_2_percent' => round($bankroll * 0.02, 2),
        ];
    }

    private function assessRisk(float $kellyFraction, float $edge, float $cv): array
    {
        $riskLevel = 'low';
        $riskScore = 0;

        if ($kellyFraction > 0.25) {
            $riskScore += 30;
        } elseif ($kellyFraction > 0.1) {
            $riskScore += 15;
        }

        if ($edge < 0.05) {
            $riskScore += 25;
        } elseif ($edge < 0.1) {
            $riskScore += 10;
        }

        if ($cv > 1) {
            $riskScore += 25;
        } elseif ($cv > 0.5) {
            $riskScore += 10;
        }

        if ($riskScore > 50) {
            $riskLevel = 'high';
        } elseif ($riskScore > 25) {
            $riskLevel = 'medium';
        }

        return [
            'level' => $riskLevel,
            'score' => $riskScore,
            'factors' => [
                'kelly_size' => $kellyFraction > 0.1 ? 'high' : 'normal',
                'edge_quality' => $edge > 0.1 ? 'good' : ($edge > 0.05 ? 'moderate' : 'low'),
                'volatility' => $cv > 1 ? 'high' : ($cv > 0.5 ? 'moderate' : 'low'),
            ],
        ];
    }

    private function getRecommendation(float $kellyFraction, float $edge): array
    {
        if ($edge <= 0) {
            return [
                'action' => 'skip',
                'reason' => 'Pas de value (edge négatif)',
                'confidence' => 'high',
            ];
        }

        if ($kellyFraction <= 0.01) {
            return [
                'action' => 'skip',
                'reason' => 'Edge trop faible pour justifier un pari',
                'confidence' => 'medium',
            ];
        }

        if ($kellyFraction > 0.25) {
            return [
                'action' => 'bet_quarter_kelly',
                'reason' => 'Excellent edge mais utiliser Quarter Kelly pour la sécurité',
                'confidence' => 'high',
            ];
        }

        if ($kellyFraction > 0.1) {
            return [
                'action' => 'bet_half_kelly',
                'reason' => 'Bon edge, Half Kelly recommandé',
                'confidence' => 'high',
            ];
        }

        return [
            'action' => 'bet_small',
            'reason' => 'Edge modéré, mise conservatrice recommandée',
            'confidence' => 'medium',
        ];
    }

    private function calculateDiversificationScore(array $bets): float
    {
        if (count($bets) <= 1) {
            return 0;
        }

        // Score basé sur la répartition des mises
        $stakes = array_column($bets, 'recommended_stake');
        $total = array_sum($stakes);

        if ($total <= 0) {
            return 0;
        }

        // Indice de Herfindahl-Hirschman (HHI) inversé
        $hhi = 0;
        foreach ($stakes as $stake) {
            $share = $stake / $total;
            $hhi += $share * $share;
        }

        // Normaliser: 1/n est parfaitement diversifié, 1 est concentré
        $n = count($bets);
        $maxDiversification = 1 / $n;
        $diversificationScore = (1 - $hhi) / (1 - $maxDiversification);

        return round(min(100, $diversificationScore * 100), 2);
    }
}
