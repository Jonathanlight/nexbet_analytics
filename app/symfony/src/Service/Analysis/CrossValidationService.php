<?php

declare(strict_types=1);

namespace App\Service\Analysis;

use App\Repository\BasketballMatchRepository;
use App\Repository\FootballMatchRepository;
use App\Repository\HockeyMatchRepository;
use App\Service\Prediction\UltraPredictionService;
use Psr\Log\LoggerInterface;

/**
 * Service de validation croisée des prédictions.
 * Évalue la qualité des modèles en comparant les prédictions aux résultats réels.
 *
 * Métriques calculées :
 * - Brier Score (précision probabiliste)
 * - Log Loss (pénalise la surconfiance)
 * - Calibration (cohérence prédiction/réalité)
 * - ROC-AUC par outcome
 * - Précision par niveau de confiance
 */
final class CrossValidationService
{
    private const MIN_SAMPLE_SIZE = 30;

    public function __construct(
        private readonly FootballMatchRepository $footballRepo,
        private readonly BasketballMatchRepository $basketballRepo,
        private readonly HockeyMatchRepository $hockeyRepo,
        private readonly UltraPredictionService $predictionService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Effectue une validation croisée complète pour un sport.
     */
    public function validatePredictions(string $sport, int $daysBack = 30): array
    {
        $matches = $this->getFinishedMatches($sport, $daysBack);

        if (count($matches) < self::MIN_SAMPLE_SIZE) {
            return [
                'valid' => false,
                'error' => 'Insufficient sample size',
                'sample_size' => count($matches),
                'minimum_required' => self::MIN_SAMPLE_SIZE,
            ];
        }

        $predictions = [];
        $actuals = [];
        $confidences = [];

        foreach ($matches as $match) {
            try {
                $prediction = $this->getPredictionForMatch($match, $sport);
                $actual = $this->getActualResult($match, $sport);

                if ($prediction && $actual) {
                    $predictions[] = $prediction;
                    $actuals[] = $actual;
                    $confidences[] = $prediction['confidence'];
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        if (empty($predictions)) {
            return ['valid' => false, 'error' => 'No valid predictions to validate'];
        }

        // Calculer toutes les métriques
        $metrics = [
            'sample_size' => count($predictions),
            'accuracy' => $this->calculateAccuracy($predictions, $actuals),
            'brier_score' => $this->calculateBrierScore($predictions, $actuals, $sport),
            'log_loss' => $this->calculateLogLoss($predictions, $actuals, $sport),
            'calibration' => $this->calculateCalibration($predictions, $actuals, $confidences),
            'by_confidence' => $this->analyzeByConfidence($predictions, $actuals),
            'by_prediction_type' => $this->analyzeByPredictionType($predictions, $actuals, $sport),
            'confusion_matrix' => $this->calculateConfusionMatrix($predictions, $actuals, $sport),
            'recommendation' => null,
        ];

        // Générer les recommandations d'amélioration
        $metrics['recommendation'] = $this->generateRecommendations($metrics, $sport);

        return [
            'valid' => true,
            'sport' => $sport,
            'period' => [
                'days' => $daysBack,
                'start' => (new \DateTimeImmutable("-{$daysBack} days"))->format('Y-m-d'),
                'end' => (new \DateTimeImmutable())->format('Y-m-d'),
            ],
            'metrics' => $metrics,
        ];
    }

    /**
     * Calcule le Brier Score (0 = parfait, 1 = pire).
     */
    public function calculateBrierScore(array $predictions, array $actuals, string $sport): float
    {
        $n = count($predictions);
        if (0 === $n) {
            return 1.0;
        }

        $totalScore = 0;
        $outcomes = 'basketball' === $sport ? ['home', 'away'] : ['1', 'X', '2'];

        foreach ($predictions as $i => $pred) {
            $probs = $pred['probabilities'];
            $actual = $actuals[$i];

            foreach ($outcomes as $outcome) {
                $predicted = ($probs[$outcome] ?? 0) / 100;
                $occurred = ($actual === $outcome) ? 1 : 0;
                $totalScore += pow($predicted - $occurred, 2);
            }
        }

        return round($totalScore / ($n * count($outcomes)), 4);
    }

    /**
     * Calcule le Log Loss (pénalise les prédictions confiantes mais incorrectes).
     */
    public function calculateLogLoss(array $predictions, array $actuals, string $sport): float
    {
        $n = count($predictions);
        if (0 === $n) {
            return 999;
        }

        $totalLoss = 0;
        $epsilon = 1e-15; // Pour éviter log(0)

        foreach ($predictions as $i => $pred) {
            $probs = $pred['probabilities'];
            $actual = $actuals[$i];

            // Probabilité assignée au résultat réel
            $predictedProb = ($probs[$actual] ?? 0) / 100;
            $predictedProb = max($epsilon, min(1 - $epsilon, $predictedProb));

            $totalLoss -= log($predictedProb);
        }

        return round($totalLoss / $n, 4);
    }

    /**
     * Analyse la calibration (les prédictions à X% de confiance sont-elles correctes X% du temps?).
     */
    public function calculateCalibration(array $predictions, array $actuals, array $confidences): array
    {
        $bins = [
            '0-50' => ['predicted' => 0, 'correct' => 0, 'count' => 0],
            '50-60' => ['predicted' => 0, 'correct' => 0, 'count' => 0],
            '60-70' => ['predicted' => 0, 'correct' => 0, 'count' => 0],
            '70-80' => ['predicted' => 0, 'correct' => 0, 'count' => 0],
            '80-90' => ['predicted' => 0, 'correct' => 0, 'count' => 0],
            '90-100' => ['predicted' => 0, 'correct' => 0, 'count' => 0],
        ];

        foreach ($predictions as $i => $pred) {
            $conf = $confidences[$i];
            $maxProb = max($pred['probabilities']);
            $predictedOutcome = array_search($maxProb, $pred['probabilities']);
            $isCorrect = $predictedOutcome === $actuals[$i];

            $bin = match (true) {
                $conf < 50 => '0-50',
                $conf < 60 => '50-60',
                $conf < 70 => '60-70',
                $conf < 80 => '70-80',
                $conf < 90 => '80-90',
                default => '90-100',
            };

            $bins[$bin]['predicted'] += $conf;
            $bins[$bin]['correct'] += $isCorrect ? 1 : 0;
            ++$bins[$bin]['count'];
        }

        // Calculer les taux réels vs prédits
        $calibrationData = [];
        $totalCalibrationError = 0;

        foreach ($bins as $range => $data) {
            if (0 === $data['count']) {
                continue;
            }

            $avgPredicted = $data['predicted'] / $data['count'];
            $actualRate = ($data['correct'] / $data['count']) * 100;
            $error = abs($avgPredicted - $actualRate);
            $totalCalibrationError += $error * $data['count'];

            $calibrationData[$range] = [
                'count' => $data['count'],
                'avg_predicted' => round($avgPredicted, 1),
                'actual_rate' => round($actualRate, 1),
                'calibration_error' => round($error, 1),
                'status' => $error < 10 ? 'good' : ($error < 20 ? 'acceptable' : 'poor'),
            ];
        }

        $totalSamples = array_sum(array_column($bins, 'count'));

        return [
            'by_confidence_level' => $calibrationData,
            'overall_calibration_error' => $totalSamples > 0
                ? round($totalCalibrationError / $totalSamples, 2)
                : 0,
            'is_well_calibrated' => ($totalCalibrationError / max(1, $totalSamples)) < 15,
        ];
    }

    /**
     * Analyse la précision par niveau de confiance.
     */
    private function analyzeByConfidence(array $predictions, array $actuals): array
    {
        $levels = [
            'very_low' => ['min' => 0, 'max' => 45, 'correct' => 0, 'total' => 0],
            'low' => ['min' => 45, 'max' => 55, 'correct' => 0, 'total' => 0],
            'medium' => ['min' => 55, 'max' => 65, 'correct' => 0, 'total' => 0],
            'high' => ['min' => 65, 'max' => 75, 'correct' => 0, 'total' => 0],
            'very_high' => ['min' => 75, 'max' => 100, 'correct' => 0, 'total' => 0],
        ];

        foreach ($predictions as $i => $pred) {
            $conf = $pred['confidence'];
            $maxProb = max($pred['probabilities']);
            $predictedOutcome = array_search($maxProb, $pred['probabilities']);
            $isCorrect = $predictedOutcome === $actuals[$i];

            foreach ($levels as $name => &$level) {
                if ($conf >= $level['min'] && $conf < $level['max']) {
                    ++$level['total'];
                    if ($isCorrect) {
                        ++$level['correct'];
                    }
                    break;
                }
            }
        }

        $result = [];
        foreach ($levels as $name => $level) {
            if (0 === $level['total']) {
                continue;
            }

            $accuracy = ($level['correct'] / $level['total']) * 100;
            $result[$name] = [
                'total' => $level['total'],
                'correct' => $level['correct'],
                'accuracy' => round($accuracy, 1),
                'expected_min' => $level['min'],
                'is_overconfident' => $accuracy < $level['min'],
                'is_underconfident' => $accuracy > $level['max'],
            ];
        }

        return $result;
    }

    /**
     * Analyse par type de prédiction (1, X, 2 ou home/away).
     */
    private function analyzeByPredictionType(array $predictions, array $actuals, string $sport): array
    {
        $outcomes = 'basketball' === $sport ? ['home', 'away'] : ['1', 'X', '2'];
        $analysis = [];

        foreach ($outcomes as $outcome) {
            $analysis[$outcome] = [
                'predicted_count' => 0,
                'correct_when_predicted' => 0,
                'actual_count' => 0,
                'detected_when_occurred' => 0,
            ];
        }

        foreach ($predictions as $i => $pred) {
            $maxProb = max($pred['probabilities']);
            $predictedOutcome = array_search($maxProb, $pred['probabilities']);
            $actualOutcome = $actuals[$i];

            // Comptage des prédictions
            if (isset($analysis[$predictedOutcome])) {
                ++$analysis[$predictedOutcome]['predicted_count'];
                if ($predictedOutcome === $actualOutcome) {
                    ++$analysis[$predictedOutcome]['correct_when_predicted'];
                }
            }

            // Comptage des résultats réels
            if (isset($analysis[$actualOutcome])) {
                ++$analysis[$actualOutcome]['actual_count'];
                if ($predictedOutcome === $actualOutcome) {
                    ++$analysis[$actualOutcome]['detected_when_occurred'];
                }
            }
        }

        // Calculer les métriques
        foreach ($analysis as $outcome => &$data) {
            $data['precision'] = $data['predicted_count'] > 0
                ? round(($data['correct_when_predicted'] / $data['predicted_count']) * 100, 1)
                : 0;
            $data['recall'] = $data['actual_count'] > 0
                ? round(($data['detected_when_occurred'] / $data['actual_count']) * 100, 1)
                : 0;
            $data['f1_score'] = ($data['precision'] + $data['recall']) > 0
                ? round(2 * ($data['precision'] * $data['recall']) / ($data['precision'] + $data['recall']), 1)
                : 0;
        }

        return $analysis;
    }

    /**
     * Calcule la matrice de confusion.
     */
    private function calculateConfusionMatrix(array $predictions, array $actuals, string $sport): array
    {
        $outcomes = 'basketball' === $sport ? ['home', 'away'] : ['1', 'X', '2'];
        $matrix = [];

        foreach ($outcomes as $predicted) {
            $matrix[$predicted] = [];
            foreach ($outcomes as $actual) {
                $matrix[$predicted][$actual] = 0;
            }
        }

        foreach ($predictions as $i => $pred) {
            $maxProb = max($pred['probabilities']);
            $predictedOutcome = array_search($maxProb, $pred['probabilities']);
            $actualOutcome = $actuals[$i];

            if (isset($matrix[$predictedOutcome][$actualOutcome])) {
                ++$matrix[$predictedOutcome][$actualOutcome];
            }
        }

        return $matrix;
    }

    /**
     * Calcule la précision globale.
     */
    private function calculateAccuracy(array $predictions, array $actuals): float
    {
        $correct = 0;
        foreach ($predictions as $i => $pred) {
            $maxProb = max($pred['probabilities']);
            $predictedOutcome = array_search($maxProb, $pred['probabilities']);
            if ($predictedOutcome === $actuals[$i]) {
                ++$correct;
            }
        }

        return round(($correct / count($predictions)) * 100, 2);
    }

    /**
     * Génère des recommandations basées sur l'analyse.
     */
    private function generateRecommendations(array $metrics, string $sport): array
    {
        $recommendations = [];

        // Analyse de la calibration
        if (!$metrics['calibration']['is_well_calibrated']) {
            $recommendations[] = [
                'priority' => 'high',
                'area' => 'calibration',
                'issue' => 'Le modèle est mal calibré',
                'suggestion' => 'Appliquer une courbe de calibration (Platt scaling ou isotonic regression)',
            ];
        }

        // Analyse par niveau de confiance
        foreach ($metrics['by_confidence'] as $level => $data) {
            if ($data['is_overconfident']) {
                $recommendations[] = [
                    'priority' => 'medium',
                    'area' => 'confidence',
                    'issue' => "Surconfiance au niveau '{$level}'",
                    'suggestion' => 'Réduire les scores de confiance de '.round($data['expected_min'] - $data['accuracy'], 1).'%',
                ];
            }
        }

        // Analyse par type de prédiction
        foreach ($metrics['by_prediction_type'] as $outcome => $data) {
            if ($data['precision'] < 40) {
                $recommendations[] = [
                    'priority' => 'high',
                    'area' => 'prediction_type',
                    'issue' => "Faible précision pour la prédiction '{$outcome}'",
                    'suggestion' => "Réviser les seuils de décision pour '{$outcome}' ou réduire sa fréquence de prédiction",
                ];
            }
            if ($data['recall'] < 30) {
                $recommendations[] = [
                    'priority' => 'medium',
                    'area' => 'prediction_type',
                    'issue' => "Faible rappel pour '{$outcome}'",
                    'suggestion' => "Le modèle rate trop souvent les '{$outcome}' - ajuster les biais",
                ];
            }
        }

        // Analyse du Brier Score
        $brierThreshold = 'basketball' === $sport ? 0.20 : 0.22;
        if ($metrics['brier_score'] > $brierThreshold) {
            $recommendations[] = [
                'priority' => 'high',
                'area' => 'model_quality',
                'issue' => 'Brier Score élevé',
                'suggestion' => 'Améliorer la qualité des probabilités via ensemble learning ou feature engineering',
            ];
        }

        // Analyse de la précision globale
        $accuracyThreshold = 'basketball' === $sport ? 55 : 45;
        if ($metrics['accuracy'] < $accuracyThreshold) {
            $recommendations[] = [
                'priority' => 'critical',
                'area' => 'overall',
                'issue' => 'Précision globale insuffisante',
                'suggestion' => 'Réviser l\'architecture du modèle, augmenter les données d\'entraînement, ou ajouter de nouvelles features',
            ];
        }

        // Recommandations positives
        if ($metrics['accuracy'] >= 60) {
            $recommendations[] = [
                'priority' => 'info',
                'area' => 'overall',
                'issue' => 'Bonne performance globale',
                'suggestion' => 'Continuer à collecter des données pour maintenir cette performance',
            ];
        }

        return $recommendations;
    }

    /**
     * Effectue une validation croisée K-fold.
     */
    public function kFoldValidation(string $sport, int $k = 5, int $daysBack = 60): array
    {
        $matches = $this->getFinishedMatches($sport, $daysBack);
        shuffle($matches);

        if (count($matches) < $k * 10) {
            return ['valid' => false, 'error' => 'Insufficient data for k-fold validation'];
        }

        $foldSize = intdiv(count($matches), $k);
        $foldResults = [];

        for ($fold = 0; $fold < $k; ++$fold) {
            $testStart = $fold * $foldSize;
            $testEnd = ($fold === $k - 1) ? count($matches) : ($fold + 1) * $foldSize;

            $testMatches = array_slice($matches, $testStart, $testEnd - $testStart);

            $predictions = [];
            $actuals = [];

            foreach ($testMatches as $match) {
                try {
                    $prediction = $this->getPredictionForMatch($match, $sport);
                    $actual = $this->getActualResult($match, $sport);

                    if ($prediction && $actual) {
                        $predictions[] = $prediction;
                        $actuals[] = $actual;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            if (!empty($predictions)) {
                $foldResults[] = [
                    'fold' => $fold + 1,
                    'test_size' => count($predictions),
                    'accuracy' => $this->calculateAccuracy($predictions, $actuals),
                    'brier_score' => $this->calculateBrierScore($predictions, $actuals, $sport),
                ];
            }
        }

        // Moyennes et écarts-types
        $accuracies = array_column($foldResults, 'accuracy');
        $brierScores = array_column($foldResults, 'brier_score');

        return [
            'valid' => true,
            'k' => $k,
            'total_samples' => count($matches),
            'fold_results' => $foldResults,
            'summary' => [
                'mean_accuracy' => round(array_sum($accuracies) / count($accuracies), 2),
                'std_accuracy' => round($this->standardDeviation($accuracies), 2),
                'mean_brier_score' => round(array_sum($brierScores) / count($brierScores), 4),
                'std_brier_score' => round($this->standardDeviation($brierScores), 4),
                'stability' => $this->standardDeviation($accuracies) < 5 ? 'stable' : 'unstable',
            ],
        ];
    }

    /**
     * Compare deux périodes pour détecter une dégradation/amélioration.
     */
    public function comparePerformance(string $sport, int $recentDays = 7, int $historicalDays = 30): array
    {
        $recent = $this->validatePredictions($sport, $recentDays);
        $historical = $this->validatePredictions($sport, $historicalDays);

        if (!$recent['valid'] || !$historical['valid']) {
            return ['valid' => false, 'error' => 'Insufficient data for comparison'];
        }

        $recentMetrics = $recent['metrics'];
        $historicalMetrics = $historical['metrics'];

        $comparison = [
            'accuracy_change' => round($recentMetrics['accuracy'] - $historicalMetrics['accuracy'], 2),
            'brier_change' => round($recentMetrics['brier_score'] - $historicalMetrics['brier_score'], 4),
            'calibration_change' => round(
                $recentMetrics['calibration']['overall_calibration_error'] -
                $historicalMetrics['calibration']['overall_calibration_error'],
                2
            ),
        ];

        $comparison['trend'] = match (true) {
            $comparison['accuracy_change'] > 3 => 'improving',
            $comparison['accuracy_change'] < -3 => 'degrading',
            default => 'stable',
        };

        $comparison['alert'] = 'degrading' === $comparison['trend'] ? [
            'level' => 'warning',
            'message' => 'Performance en baisse détectée',
            'action' => 'Vérifier les données d\'entrée et recalibrer le modèle',
        ] : null;

        return [
            'valid' => true,
            'recent_period' => $recent,
            'historical_period' => $historical,
            'comparison' => $comparison,
        ];
    }

    // === Méthodes utilitaires ===

    private function getFinishedMatches(string $sport, int $daysBack): array
    {
        $startDate = new \DateTimeImmutable("-{$daysBack} days");
        $matches = [];

        switch ($sport) {
            case 'football':
                $allMatches = $this->footballRepo->findRecentFinishedMatches(500);
                break;
            case 'basketball':
                return $this->getBasketballFinishedMatches($daysBack);
            case 'hockey':
                return $this->getHockeyFinishedMatches($daysBack);
            default:
                return [];
        }

        foreach ($allMatches as $match) {
            if ($match->getMatchDate() >= $startDate) {
                $matches[] = $match;
            }
        }

        return $matches;
    }

    private function getBasketballFinishedMatches(int $daysBack): array
    {
        $matches = [];
        $endDate = new \DateTimeImmutable();
        $startDate = $endDate->modify("-{$daysBack} days");

        for ($d = 0; $d < $daysBack; ++$d) {
            $date = $startDate->modify("+{$d} days");
            $dayMatches = $this->basketballRepo->findByDate($date);
            foreach ($dayMatches as $match) {
                if (null !== $match->getHomeFinalScore()) {
                    $matches[] = $match;
                }
            }
        }

        return $matches;
    }

    private function getHockeyFinishedMatches(int $daysBack): array
    {
        $matches = [];
        $endDate = new \DateTimeImmutable();
        $startDate = $endDate->modify("-{$daysBack} days");

        for ($d = 0; $d < $daysBack; ++$d) {
            $date = $startDate->modify("+{$d} days");
            $dayMatches = $this->hockeyRepo->findByDate($date);
            foreach ($dayMatches as $match) {
                if (null !== $match->getHomeFinalScore()) {
                    $matches[] = $match;
                }
            }
        }

        return $matches;
    }

    private function getPredictionForMatch(object $match, string $sport): ?array
    {
        try {
            $prediction = match ($sport) {
                'football' => $this->predictionService->predictFootballMatch($match),
                'basketball' => $this->predictionService->predictBasketballMatch($match),
                'hockey' => $this->predictionService->predictHockeyMatch($match),
                default => null,
            };

            if (!$prediction) {
                return null;
            }

            return [
                'probabilities' => $prediction['prediction']['probabilities'],
                'confidence' => $prediction['prediction']['confidence'],
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getActualResult(object $match, string $sport): ?string
    {
        if ('football' === $sport) {
            $homeScore = $match->getHomeScore();
            $awayScore = $match->getAwayScore();
        } else {
            $homeScore = $match->getHomeFinalScore();
            $awayScore = $match->getAwayFinalScore();
        }

        if (null === $homeScore || null === $awayScore) {
            return null;
        }

        if ('basketball' === $sport) {
            return $homeScore > $awayScore ? 'home' : 'away';
        }

        if ($homeScore > $awayScore) {
            return '1';
        }
        if ($awayScore > $homeScore) {
            return '2';
        }

        return 'X';
    }

    private function standardDeviation(array $values): float
    {
        if (0 === count($values)) {
            return 0;
        }

        $mean = array_sum($values) / count($values);
        $squaredDiffs = array_map(fn ($v) => pow($v - $mean, 2), $values);

        return sqrt(array_sum($squaredDiffs) / count($values));
    }
}
