<?php

declare(strict_types=1);

namespace App\Service\Prediction;

/**
 * Service de calcul du niveau de confiance des prédictions.
 * Agrège les résultats de plusieurs algorithmes pour déterminer la fiabilité.
 *
 * IMPORTANT: La confiance mesure la FIABILITÉ de notre prédiction, pas la probabilité.
 * Une confiance élevée = on est sûr de notre analyse (bonnes données, algos d'accord)
 * Une probabilité élevée = l'événement est probable
 */
class ConfidenceCalculator
{
    /**
     * Calcule le niveau de confiance global basé sur plusieurs facteurs.
     *
     * @param array $algorithmScores     Les scores max de chaque algorithme
     * @param bool  $hasHistoricalData   Données historiques disponibles ?
     * @param int   $matchesAnalyzed     Nombre de matchs analysés
     * @param array $algorithmPredictions Les prédictions complètes (1/X/2) de chaque algo
     */
    public function calculateOverallConfidence(
        array $algorithmScores,
        bool $hasHistoricalData = true,
        int $matchesAnalyzed = 10,
        array $algorithmPredictions = []
    ): float {
        if (empty($algorithmScores)) {
            return 0.0;
        }

        // 1. SCORE DE BASE: Accord entre les algorithmes sur le MÊME résultat
        $agreementScore = $this->calculateAlgorithmAgreement($algorithmPredictions);

        // 2. SCORE DE DONNÉES: Qualité et quantité des données
        $dataQualityScore = $this->calculateDataQualityScore($hasHistoricalData, $matchesAnalyzed);

        // 3. SCORE DE CERTITUDE: Écart entre le 1er et 2e choix
        $certaintyScore = $this->calculateCertaintyScore($algorithmPredictions);

        // 4. VARIANCE: Cohérence des probabilités entre algorithmes
        $variance = $this->calculateVariance($algorithmScores);
        $consistencyScore = $this->calculateConsistencyScore($variance);

        // Combiner les scores avec des poids appropriés
        $confidence = (
            ($agreementScore * 0.35) +      // 35% - Les algos prédisent le même résultat
            ($dataQualityScore * 0.30) +    // 30% - Qualité des données
            ($certaintyScore * 0.20) +      // 20% - Clarté de la prédiction
            ($consistencyScore * 0.15)      // 15% - Cohérence des probabilités
        );

        // Cap à 95% max - on ne peut jamais être sûr à 100% au football
        $finalConfidence = min(95.0, max(10.0, $confidence));

        return round($finalConfidence, 2);
    }

    /**
     * Vérifie si tous les algorithmes prédisent le même résultat.
     */
    private function calculateAlgorithmAgreement(array $algorithmPredictions): float
    {
        if (empty($algorithmPredictions)) {
            return 50.0; // Pas de données = confiance moyenne
        }

        $predictedOutcomes = [];
        foreach ($algorithmPredictions as $algo => $probs) {
            if (null === $probs) {
                continue;
            }
            $maxProb = max($probs);
            $outcome = array_search($maxProb, $probs);
            $predictedOutcomes[$algo] = $outcome;
        }

        if (empty($predictedOutcomes)) {
            return 50.0;
        }

        // Compter combien d'algorithmes sont d'accord
        $counts = array_count_values($predictedOutcomes);
        $maxAgreement = max($counts);
        $totalAlgos = count($predictedOutcomes);

        // Score: 100 si tous d'accord, moins si désaccord
        $agreementRatio = $maxAgreement / $totalAlgos;

        if ($agreementRatio >= 1.0) {
            return 95.0; // Tous d'accord
        } elseif ($agreementRatio >= 0.75) {
            return 80.0; // 3/4 d'accord
        } elseif ($agreementRatio >= 0.5) {
            return 60.0; // Moitié d'accord
        }

        return 40.0; // Désaccord total
    }

    /**
     * Calcule un score basé sur la qualité des données.
     */
    private function calculateDataQualityScore(bool $hasHistoricalData, int $matchesAnalyzed): float
    {
        if (!$hasHistoricalData) {
            return 25.0; // Pénalité sévère: pas de données = faible confiance
        }

        // Plus on a de matchs analysés, mieux c'est
        if ($matchesAnalyzed >= 10) {
            return 90.0;
        } elseif ($matchesAnalyzed >= 7) {
            return 75.0;
        } elseif ($matchesAnalyzed >= 5) {
            return 60.0;
        } elseif ($matchesAnalyzed >= 3) {
            return 45.0;
        }

        return 30.0;
    }

    /**
     * Calcule la clarté de la prédiction (écart entre 1er et 2e choix).
     */
    private function calculateCertaintyScore(array $algorithmPredictions): float
    {
        if (empty($algorithmPredictions)) {
            return 50.0;
        }

        // Calculer la moyenne des écarts entre 1er et 2e choix
        $totalGap = 0.0;
        $count = 0;

        foreach ($algorithmPredictions as $probs) {
            if (null === $probs) {
                continue;
            }
            $sorted = $probs;
            arsort($sorted);
            $values = array_values($sorted);

            if (count($values) >= 2) {
                $gap = $values[0] - $values[1];
                $totalGap += $gap;
                ++$count;
            }
        }

        if (0 === $count) {
            return 50.0;
        }

        $avgGap = $totalGap / $count;

        // Gap > 30% = très clair, Gap < 5% = très incertain
        if ($avgGap >= 30) {
            return 95.0;
        } elseif ($avgGap >= 20) {
            return 80.0;
        } elseif ($avgGap >= 10) {
            return 65.0;
        } elseif ($avgGap >= 5) {
            return 50.0;
        }

        return 35.0; // Très serré = incertain
    }

    /**
     * Score de cohérence basé sur la variance.
     */
    private function calculateConsistencyScore(float $variance): float
    {
        if ($variance < 5) {
            return 95.0; // Très cohérent
        } elseif ($variance < 15) {
            return 80.0;
        } elseif ($variance < 30) {
            return 60.0;
        } elseif ($variance < 50) {
            return 45.0;
        }

        return 30.0; // Très incohérent
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
        array $specificFactors = []
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
