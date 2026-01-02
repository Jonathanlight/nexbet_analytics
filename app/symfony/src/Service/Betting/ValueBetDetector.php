<?php

declare(strict_types=1);

namespace App\Service\Betting;

/**
 * Service de détection des value bets (paris avec valeur).
 */
class ValueBetDetector
{
    private const MIN_EDGE_PERCENTAGE = 5.0; // Edge minimum pour considérer un value bet

    /**
     * Détecte si un pari est un value bet.
     */
    public function isValueBet(float $predictedProbability, float $odds, float $minEdge = self::MIN_EDGE_PERCENTAGE): bool
    {
        if ($odds <= 1.0) {
            return false;
        }

        $edge = $this->calculateEdge($predictedProbability, $odds);

        return $edge >= $minEdge;
    }

    /**
     * Calcule l'edge (avantage) d'un pari.
     * Edge = Probabilité prédite - Probabilité implicite des cotes.
     */
    public function calculateEdge(float $predictedProbability, float $odds): float
    {
        $impliedProbability = (1 / $odds) * 100;
        $edge = $predictedProbability - $impliedProbability;

        return round($edge, 2);
    }

    /**
     * Calcule l'espérance de valeur (EV).
     */
    public function calculateExpectedValue(float $predictedProbability, float $odds, float $stake = 100): float
    {
        $p = $predictedProbability / 100;
        $q = 1 - $p;

        $winAmount = $stake * ($odds - 1);
        $loseAmount = $stake;

        $ev = ($p * $winAmount) - ($q * $loseAmount);

        return round($ev, 2);
    }

    /**
     * Détecte tous les value bets parmi un ensemble de prédictions.
     */
    public function findValueBets(array $predictions, array $odds, float $minEdge = self::MIN_EDGE_PERCENTAGE): array
    {
        $valueBets = [];

        foreach ($predictions as $betType => $prediction) {
            if (!isset($odds[$betType])) {
                continue;
            }

            $betOdds = $odds[$betType];
            $probability = $prediction['probability'] ?? 0;

            if ($this->isValueBet($probability, $betOdds, $minEdge)) {
                $edge = $this->calculateEdge($probability, $betOdds);
                $ev = $this->calculateExpectedValue($probability, $betOdds);

                $valueBets[] = [
                    'bet_type' => $betType,
                    'prediction' => $prediction,
                    'odds' => $betOdds,
                    'probability' => $probability,
                    'edge' => $edge,
                    'expected_value' => $ev,
                    'value_rating' => $this->calculateValueRating($edge, $probability),
                ];
            }
        }

        // Trier par edge décroissant
        usort($valueBets, fn ($a, $b) => $b['edge'] <=> $a['edge']);

        return $valueBets;
    }

    /**
     * Compare les cotes de plusieurs bookmakers pour trouver la meilleure valeur.
     */
    public function findBestValueBetweenBookmakers(float $predictedProbability, array $bookmakerOdds): ?array
    {
        $bestValue = null;
        $maxEdge = 0;

        foreach ($bookmakerOdds as $bookmaker => $odds) {
            $edge = $this->calculateEdge($predictedProbability, $odds);

            if ($edge > $maxEdge) {
                $maxEdge = $edge;
                $bestValue = [
                    'bookmaker' => $bookmaker,
                    'odds' => $odds,
                    'edge' => $edge,
                    'is_value_bet' => $edge >= self::MIN_EDGE_PERCENTAGE,
                ];
            }
        }

        return $bestValue;
    }

    /**
     * Calcule une note de qualité du value bet (0-100).
     */
    public function calculateValueRating(float $edge, float $probability): float
    {
        // Facteurs:
        // 1. Edge important = meilleur
        // 2. Probabilité élevée = meilleur (moins de risque)

        $edgeScore = min(50, $edge * 5); // Max 50 points
        $probabilityScore = $probability / 2; // Max 50 points

        $rating = $edgeScore + $probabilityScore;

        return round(min(100, $rating), 2);
    }

    /**
     * Analyse la qualité globale d'un value bet.
     */
    public function analyzeValueBetQuality(array $valueBet): array
    {
        $edge = $valueBet['edge'];
        $probability = $valueBet['probability'];
        $odds = $valueBet['odds'];

        $quality = 'Medium';
        $riskLevel = 'Medium';

        // Déterminer la qualité
        if ($edge >= 15 && $probability >= 60) {
            $quality = 'Excellent';
        } elseif ($edge >= 10 && $probability >= 50) {
            $quality = 'Very Good';
        } elseif ($edge >= 7 && $probability >= 40) {
            $quality = 'Good';
        }

        // Déterminer le risque
        if ($probability >= 70) {
            $riskLevel = 'Low';
        } elseif ($probability >= 50) {
            $riskLevel = 'Medium';
        } elseif ($probability >= 35) {
            $riskLevel = 'High';
        } else {
            $riskLevel = 'Very High';
        }

        return [
            'quality' => $quality,
            'risk_level' => $riskLevel,
            'recommended' => 'Excellent' === $quality || 'Very Good' === $quality,
            'expected_roi' => round((($probability / 100) * $odds - 1) * 100, 2),
        ];
    }
}
