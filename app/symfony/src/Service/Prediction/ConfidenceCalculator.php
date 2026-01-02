<?php

declare(strict_types=1);

namespace App\Service\Prediction;

/**
 * Service de calcul du niveau de confiance des prédictions.
 * Agrège les résultats de plusieurs algorithmes pour déterminer la fiabilité.
 */
class ConfidenceCalculator
{
    /**
     * Calcule le niveau de confiance global basé sur plusieurs algorithmes.
     */
    public function calculateOverallConfidence(array $algorithmScores): float
    {
        if (empty($algorithmScores)) {
            return 0.0;
        }

        // Moyennes pondérées des différents algorithmes
        $weights = [
            'poisson' => 0.25,
            'elo' => 0.20,
            'xg' => 0.30,
            'monte_carlo' => 0.25,
        ];

        $weightedSum = 0.0;
        $totalWeight = 0.0;

        foreach ($algorithmScores as $algorithm => $score) {
            $weight = $weights[$algorithm] ?? 0.0;
            $weightedSum += $score * $weight;
            $totalWeight += $weight;
        }

        if (0 === $totalWeight) {
            return 0.0;
        }

        $confidence = ($weightedSum / $totalWeight);

        // Ajuster selon la variance entre les algorithmes
        $variance = $this->calculateVariance($algorithmScores);
        $consistencyBonus = $this->calculateConsistencyBonus($variance);

        $finalConfidence = min(99.9, $confidence + $consistencyBonus);

        return round($finalConfidence, 2);
    }

    /**
     * Calcule la variance entre les scores des algorithmes.
     */
    private function calculateVariance(array $scores): float
    {
        if (count($scores) < 2) {
            return 0.0;
        }

        $mean = array_sum($scores) / count($scores);
        $variance = 0.0;

        foreach ($scores as $score) {
            $variance += pow($score - $mean, 2);
        }

        return $variance / count($scores);
    }

    /**
     * Calcule un bonus de consistance basé sur la variance.
     * Si tous les algorithmes sont d'accord, augmente la confiance.
     */
    private function calculateConsistencyBonus(float $variance): float
    {
        if ($variance < 10) {
            return 10.0; // Très forte cohérence
        } elseif ($variance < 25) {
            return 5.0; // Bonne cohérence
        } elseif ($variance < 50) {
            return 2.0; // Cohérence moyenne
        }

        return 0.0; // Faible cohérence
    }

    /**
     * Détermine si un pari est considéré comme "safe".
     */
    public function isSafeBet(float $confidence, float $probability, ?float $odds = null): bool
    {
        // Critères pour un pari sûr:
        // 1. Confiance >= 85%
        // 2. Probabilité >= 70%
        // 3. Si cotes disponibles, value bet ratio > 1

        if ($confidence < 85.0 || $probability < 70.0) {
            return false;
        }

        if (null !== $odds) {
            $impliedProbability = 1 / $odds;
            $valueBetRatio = $probability / 100 / $impliedProbability;

            return $valueBetRatio >= 1.0;
        }

        return true;
    }

    /**
     * Détermine si c'est un value bet.
     */
    public function isValueBet(float $predictedProbability, float $odds, float $minEdge = 5.0): bool
    {
        if ($odds <= 1.0) {
            return false;
        }

        $impliedProbability = (1 / $odds) * 100;
        $edge = $predictedProbability - $impliedProbability;

        return $edge >= $minEdge;
    }

    /**
     * Calcule le score de confiance pour un type de pari spécifique.
     */
    public function calculateBetTypeConfidence(
        string $betType,
        float $generalConfidence,
        array $specificFactors = [],
    ): float {
        // Ajustements selon le type de pari
        $adjustments = [
            '1X2' => 0,
            'OU25' => -5, // Un peu moins fiable
            'BTTS' => -3,
            'scorer' => -10, // Beaucoup moins prévisible
            'HT_FT' => -8,
            'exact_score' => -15,
            'handicap' => -5,
        ];

        $adjustment = $adjustments[$betType] ?? 0;
        $confidence = $generalConfidence + $adjustment;

        // Facteurs spécifiques
        if (isset($specificFactors['historical_accuracy'])) {
            $confidence += ($specificFactors['historical_accuracy'] - 70) * 0.2;
        }

        if (isset($specificFactors['sample_size']) && $specificFactors['sample_size'] < 5) {
            $confidence -= 10; // Pénalité pour manque de données
        }

        return max(0, min(99.9, round($confidence, 2)));
    }

    /**
     * Calcule la confiance pour les combinaisons.
     */
    public function calculateCombinationConfidence(array $betConfidences): array
    {
        if (empty($betConfidences)) {
            return [
                'confidence' => 0.0,
                'combined_probability' => 0.0,
                'risk_level' => 'very_high',
            ];
        }

        // La probabilité combinée = produit des probabilités individuelles
        $combinedProbability = 1.0;
        foreach ($betConfidences as $bet) {
            $combinedProbability *= ($bet['probability'] / 100);
        }
        $combinedProbability *= 100;

        // La confiance combinée diminue avec le nombre de paris
        $avgConfidence = array_sum(array_column($betConfidences, 'confidence')) / count($betConfidences);
        $penaltyFactor = pow(0.9, count($betConfidences) - 1); // Pénalité exponentielle
        $combinedConfidence = $avgConfidence * $penaltyFactor;

        $riskLevel = $this->determineRiskLevel($combinedConfidence, $combinedProbability);

        return [
            'confidence' => round($combinedConfidence, 2),
            'combined_probability' => round($combinedProbability, 2),
            'risk_level' => $riskLevel,
            'bet_count' => count($betConfidences),
        ];
    }

    /**
     * Détermine le niveau de risque.
     */
    private function determineRiskLevel(float $confidence, float $probability): string
    {
        if ($confidence >= 85 && $probability >= 70) {
            return 'very_low';
        } elseif ($confidence >= 70 && $probability >= 60) {
            return 'low';
        } elseif ($confidence >= 60 && $probability >= 50) {
            return 'medium';
        } elseif ($confidence >= 50 && $probability >= 40) {
            return 'high';
        }

        return 'very_high';
    }

    /**
     * Calcule un score de qualité de prédiction (0-100).
     */
    public function calculatePredictionQuality(
        float $confidence,
        float $probability,
        int $dataPoints,
        float $variance,
    ): array {
        // Critères de qualité
        $scores = [
            'confidence_score' => min(100, $confidence),
            'probability_score' => min(100, $probability),
            'data_quality_score' => min(100, ($dataPoints / 20) * 100),
            'consistency_score' => max(0, 100 - ($variance * 2)),
        ];

        $overallScore = array_sum($scores) / count($scores);

        $grade = $this->getQualityGrade($overallScore);

        return [
            'overall_score' => round($overallScore, 2),
            'grade' => $grade,
            'breakdown' => array_map(fn ($score) => round($score, 2), $scores),
        ];
    }

    /**
     * Attribue une note de qualité.
     */
    private function getQualityGrade(float $score): string
    {
        if ($score >= 90) {
            return 'A+';
        }
        if ($score >= 85) {
            return 'A';
        }
        if ($score >= 80) {
            return 'A-';
        }
        if ($score >= 75) {
            return 'B+';
        }
        if ($score >= 70) {
            return 'B';
        }
        if ($score >= 65) {
            return 'B-';
        }
        if ($score >= 60) {
            return 'C+';
        }
        if ($score >= 55) {
            return 'C';
        }
        if ($score >= 50) {
            return 'C-';
        }
        if ($score >= 45) {
            return 'D';
        }

        return 'F';
    }
}
