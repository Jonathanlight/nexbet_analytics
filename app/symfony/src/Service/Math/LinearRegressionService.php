<?php

declare(strict_types=1);

namespace App\Service\Math;

use App\Repository\BasketballMatchRepository;
use App\Repository\FootballMatchRepository;
use App\Repository\HockeyMatchRepository;

/**
 * Service de régression linéaire pour l'analyse et l'amélioration des prédictions sportives.
 *
 * Ce service utilise une régression linéaire multiple pour:
 * - Identifier les facteurs les plus importants dans les résultats
 * - Améliorer la calibration des probabilités
 * - Prédire les scores et totaux de points/buts
 */
class LinearRegressionService
{
    private array $footballCoefficients = [];
    private array $basketballCoefficients = [];
    private array $hockeyCoefficients = [];
    private bool $isTrained = false;

    public function __construct(
        private readonly FootballMatchRepository $footballRepository,
        private readonly BasketballMatchRepository $basketballRepository,
        private readonly HockeyMatchRepository $hockeyRepository,
    ) {
    }

    /**
     * Entraîne le modèle de régression sur les données historiques.
     */
    public function train(string $sport, int $days = 90): array
    {
        $trainingData = $this->getTrainingData($sport, $days);

        if (count($trainingData['features']) < 10) {
            return [
                'success' => false,
                'error' => 'Pas assez de données pour l\'entraînement',
                'samples' => count($trainingData['features']),
            ];
        }

        // Calculer les coefficients de régression
        $coefficients = $this->calculateCoefficients(
            $trainingData['features'],
            $trainingData['targets']
        );

        // Stocker les coefficients par sport
        match ($sport) {
            'football' => $this->footballCoefficients = $coefficients,
            'basketball' => $this->basketballCoefficients = $coefficients,
            'hockey' => $this->hockeyCoefficients = $coefficients,
            default => throw new \InvalidArgumentException("Sport non supporté: $sport"),
        };

        $this->isTrained = true;

        // Calculer les métriques de qualité du modèle
        $metrics = $this->evaluateModel($trainingData, $coefficients);

        return [
            'success' => true,
            'sport' => $sport,
            'samples' => count($trainingData['features']),
            'coefficients' => $coefficients,
            'metrics' => $metrics,
        ];
    }

    /**
     * Prédit le résultat d'un match en utilisant la régression.
     */
    public function predict(string $sport, array $features): array
    {
        $coefficients = match ($sport) {
            'football' => $this->footballCoefficients,
            'basketball' => $this->basketballCoefficients,
            'hockey' => $this->hockeyCoefficients,
            default => throw new \InvalidArgumentException("Sport non supporté: $sport"),
        };

        if (empty($coefficients)) {
            // Auto-entrainement si pas de coefficients
            $this->train($sport);
            $coefficients = match ($sport) {
                'football' => $this->footballCoefficients,
                'basketball' => $this->basketballCoefficients,
                'hockey' => $this->hockeyCoefficients,
            };
        }

        // Calculer la prédiction linéaire
        $prediction = $this->linearPredict($features, $coefficients);

        // Convertir en probabilités
        $probabilities = $this->toProbabilities($prediction, $sport);

        return [
            'raw_prediction' => $prediction,
            'probabilities' => $probabilities,
            'confidence' => $this->calculateConfidence($features, $coefficients),
            'feature_importance' => $this->getFeatureImportance($coefficients),
        ];
    }

    /**
     * Prédit le score d'un match (home_score, away_score).
     */
    public function predictScore(string $sport, array $features): array
    {
        $coefficients = $this->getCoefficients($sport);

        if (empty($coefficients)) {
            $this->train($sport);
            $coefficients = $this->getCoefficients($sport);
        }

        // Prédiction du score à domicile
        $homeScore = $this->predictValue($features, $coefficients['home_score'] ?? $coefficients);

        // Prédiction du score à l'extérieur
        $awayScore = $this->predictValue($features, $coefficients['away_score'] ?? $coefficients);

        // Ajuster selon le sport
        $adjustedScores = $this->adjustScoresForSport($homeScore, $awayScore, $sport);

        return [
            'home_score' => $adjustedScores['home'],
            'away_score' => $adjustedScores['away'],
            'total' => $adjustedScores['home'] + $adjustedScores['away'],
            'margin' => abs($adjustedScores['home'] - $adjustedScores['away']),
            'predicted_winner' => $adjustedScores['home'] > $adjustedScores['away'] ? 'home' :
                                 ($adjustedScores['home'] < $adjustedScores['away'] ? 'away' : 'draw'),
        ];
    }

    /**
     * Analyse l'importance des features pour les prédictions.
     */
    public function analyzeFeatureImportance(string $sport): array
    {
        $coefficients = $this->getCoefficients($sport);

        if (empty($coefficients)) {
            $this->train($sport);
            $coefficients = $this->getCoefficients($sport);
        }

        $importance = [];
        $featureNames = $this->getFeatureNames($sport);

        foreach ($coefficients as $i => $coef) {
            if (0 === $i) {
                continue;
            } // Skip intercept

            $featureName = $featureNames[$i - 1] ?? "feature_$i";
            $importance[$featureName] = [
                'coefficient' => $coef,
                'absolute_importance' => abs($coef),
                'direction' => $coef > 0 ? 'positive' : 'negative',
            ];
        }

        // Trier par importance absolue
        uasort($importance, fn ($a, $b) => $b['absolute_importance'] <=> $a['absolute_importance']);

        return [
            'sport' => $sport,
            'feature_importance' => $importance,
            'most_important' => array_key_first($importance),
            'interpretation' => $this->interpretCoefficients($importance, $sport),
        ];
    }

    /**
     * Combine la régression avec d'autres modèles pour une prédiction ensemble.
     */
    public function ensemblePrediction(
        string $sport,
        array $features,
        array $otherPredictions,
        array $weights = [],
    ): array {
        // Prédiction de régression
        $regressionPred = $this->predict($sport, $features);

        // Poids par défaut: régression = 30%, autres = 70%
        $defaultWeights = [
            'regression' => 0.3,
            'monte_carlo' => 0.25,
            'poisson' => 0.25,
            'elo' => 0.2,
        ];

        $weights = array_merge($defaultWeights, $weights);

        // Normaliser les poids
        $totalWeight = array_sum($weights);
        foreach ($weights as $key => $weight) {
            $weights[$key] = $weight / $totalWeight;
        }

        // Combiner les probabilités
        $ensembleProbs = [
            'home' => $regressionPred['probabilities']['home'] * $weights['regression'],
            'away' => $regressionPred['probabilities']['away'] * $weights['regression'],
        ];

        if (isset($regressionPred['probabilities']['draw'])) {
            $ensembleProbs['draw'] = $regressionPred['probabilities']['draw'] * $weights['regression'];
        }

        // Ajouter les autres prédictions
        foreach ($otherPredictions as $model => $prediction) {
            $modelWeight = $weights[$model] ?? 0;
            if ($modelWeight > 0 && isset($prediction['probabilities'])) {
                $ensembleProbs['home'] += ($prediction['probabilities']['home'] ?? 0) * $modelWeight;
                $ensembleProbs['away'] += ($prediction['probabilities']['away'] ?? 0) * $modelWeight;
                if (isset($prediction['probabilities']['draw'])) {
                    $ensembleProbs['draw'] = ($ensembleProbs['draw'] ?? 0) +
                                             $prediction['probabilities']['draw'] * $modelWeight;
                }
            }
        }

        // Normaliser les probabilités
        $total = array_sum($ensembleProbs);
        if ($total > 0) {
            foreach ($ensembleProbs as $key => $prob) {
                $ensembleProbs[$key] = $prob / $total;
            }
        }

        return [
            'probabilities' => $ensembleProbs,
            'regression_contribution' => $regressionPred,
            'weights_used' => $weights,
            'confidence' => $this->calculateEnsembleConfidence($regressionPred, $otherPredictions),
        ];
    }

    /**
     * Récupère les données d'entraînement depuis la base de données.
     */
    private function getTrainingData(string $sport, int $days): array
    {
        $startDate = new \DateTimeImmutable("-{$days} days");

        $features = [];
        $targets = [];

        $matches = match ($sport) {
            'football' => $this->footballRepository->findFinishedMatchesSince($startDate),
            'basketball' => $this->basketballRepository->findFinishedMatchesSince($startDate),
            'hockey' => $this->hockeyRepository->findFinishedMatchesSince($startDate),
            default => [],
        };

        foreach ($matches as $match) {
            $matchFeatures = $this->extractFeatures($match, $sport);

            if (!empty($matchFeatures)) {
                $features[] = $matchFeatures;
                $targets[] = $this->extractTarget($match, $sport);
            }
        }

        return [
            'features' => $features,
            'targets' => $targets,
        ];
    }

    /**
     * Extrait les features d'un match pour l'entraînement/prédiction.
     */
    private function extractFeatures($match, string $sport): array
    {
        $homeTeam = $match->getHomeTeam();
        $awayTeam = $match->getAwayTeam();

        // Features communes
        $features = [
            1.0, // Intercept (biais)
        ];

        // Récupérer les statistiques des équipes si disponibles
        $homeStats = $homeTeam->getStatistics() ?? [];
        $awayStats = $awayTeam->getStatistics() ?? [];

        // Feature 1: Moyenne de buts/points à domicile
        $features[] = $homeStats['avg_goals_home'] ?? $homeStats['avg_points_home'] ?? 1.5;

        // Feature 2: Moyenne de buts/points à l'extérieur
        $features[] = $awayStats['avg_goals_away'] ?? $awayStats['avg_points_away'] ?? 1.0;

        // Feature 3: Différence de classement
        $features[] = ($homeStats['ranking'] ?? 10) - ($awayStats['ranking'] ?? 10);

        // Feature 4: Forme récente (victoires sur 5 derniers matchs)
        $features[] = ($homeStats['recent_wins'] ?? 2.5) - ($awayStats['recent_wins'] ?? 2.5);

        // Feature 5: Avantage à domicile historique
        $features[] = $homeStats['home_win_rate'] ?? 0.5;

        // Feature 6: Performance à l'extérieur
        $features[] = $awayStats['away_win_rate'] ?? 0.3;

        // Feature 7: Head-to-head (si disponible)
        $h2h = $this->getHeadToHead($homeTeam, $awayTeam, $sport);
        $features[] = $h2h['home_wins'] - $h2h['away_wins'];

        // Feature 8: Buts/points encaissés (défense)
        $features[] = ($awayStats['avg_goals_conceded'] ?? $awayStats['avg_points_conceded'] ?? 1.5) -
                     ($homeStats['avg_goals_conceded'] ?? $homeStats['avg_points_conceded'] ?? 1.0);

        // Features spécifiques au sport
        if ('football' === $sport) {
            // Feature 9: Clean sheets
            $features[] = $homeStats['clean_sheets_rate'] ?? 0.3;
            // Feature 10: BTTS rate
            $features[] = ($homeStats['btts_rate'] ?? 0.5) * ($awayStats['btts_rate'] ?? 0.5);
        } elseif ('basketball' === $sport) {
            // Feature 9: Pace (rythme de jeu)
            $features[] = ($homeStats['pace'] ?? 100) / 100;
            // Feature 10: Efficacité offensive
            $features[] = ($homeStats['offensive_rating'] ?? 110) - ($awayStats['defensive_rating'] ?? 105);
        } elseif ('hockey' === $sport) {
            // Feature 9: Power play %
            $features[] = $homeStats['power_play_pct'] ?? 0.2;
            // Feature 10: Penalty kill %
            $features[] = $homeStats['penalty_kill_pct'] ?? 0.8;
        }

        return $features;
    }

    /**
     * Extrait la cible (target) d'un match terminé.
     */
    private function extractTarget($match, string $sport): float
    {
        $homeScore = $match->getHomeScore() ?? 0;
        $awayScore = $match->getAwayScore() ?? 0;

        // Target: différence de score normalisée
        $diff = $homeScore - $awayScore;

        // Normalisation selon le sport
        return match ($sport) {
            'football' => $diff / 3, // Max environ ±3 buts normalement
            'basketball' => $diff / 20, // Max environ ±20 points normalement
            'hockey' => $diff / 4, // Max environ ±4 buts normalement
            default => $diff,
        };
    }

    /**
     * Calcule les coefficients de régression (méthode des moindres carrés).
     */
    private function calculateCoefficients(array $X, array $y): array
    {
        $n = count($X);
        $m = count($X[0]); // Nombre de features

        // Convertir en matrices pour le calcul
        // X^T * X
        $XtX = array_fill(0, $m, array_fill(0, $m, 0.0));
        for ($i = 0; $i < $m; ++$i) {
            for ($j = 0; $j < $m; ++$j) {
                for ($k = 0; $k < $n; ++$k) {
                    $XtX[$i][$j] += $X[$k][$i] * $X[$k][$j];
                }
            }
        }

        // Ajouter régularisation Ridge (L2) pour stabilité
        $lambda = 0.01;
        for ($i = 0; $i < $m; ++$i) {
            $XtX[$i][$i] += $lambda;
        }

        // X^T * y
        $Xty = array_fill(0, $m, 0.0);
        for ($i = 0; $i < $m; ++$i) {
            for ($k = 0; $k < $n; ++$k) {
                $Xty[$i] += $X[$k][$i] * $y[$k];
            }
        }

        // Résoudre le système linéaire par Gauss-Jordan
        $coefficients = $this->solveLinearSystem($XtX, $Xty);

        return $coefficients;
    }

    /**
     * Résout un système linéaire Ax = b par élimination de Gauss-Jordan.
     */
    private function solveLinearSystem(array $A, array $b): array
    {
        $n = count($A);
        $augmented = [];

        // Créer la matrice augmentée [A|b]
        for ($i = 0; $i < $n; ++$i) {
            $augmented[$i] = array_merge($A[$i], [$b[$i]]);
        }

        // Élimination de Gauss avec pivot partiel
        for ($col = 0; $col < $n; ++$col) {
            // Trouver le pivot maximum
            $maxRow = $col;
            for ($row = $col + 1; $row < $n; ++$row) {
                if (abs($augmented[$row][$col]) > abs($augmented[$maxRow][$col])) {
                    $maxRow = $row;
                }
            }

            // Échanger les lignes
            $temp = $augmented[$col];
            $augmented[$col] = $augmented[$maxRow];
            $augmented[$maxRow] = $temp;

            // Éviter division par zéro
            if (abs($augmented[$col][$col]) < 1e-10) {
                continue;
            }

            // Normaliser la ligne pivot
            $pivot = $augmented[$col][$col];
            for ($j = $col; $j <= $n; ++$j) {
                $augmented[$col][$j] /= $pivot;
            }

            // Éliminer les autres lignes
            for ($row = 0; $row < $n; ++$row) {
                if ($row !== $col) {
                    $factor = $augmented[$row][$col];
                    for ($j = $col; $j <= $n; ++$j) {
                        $augmented[$row][$j] -= $factor * $augmented[$col][$j];
                    }
                }
            }
        }

        // Extraire la solution
        $x = [];
        for ($i = 0; $i < $n; ++$i) {
            $x[$i] = $augmented[$i][$n];
        }

        return $x;
    }

    /**
     * Évalue la qualité du modèle sur les données d'entraînement.
     */
    private function evaluateModel(array $data, array $coefficients): array
    {
        $predictions = [];
        $errors = [];

        foreach ($data['features'] as $i => $features) {
            $pred = $this->linearPredict($features, $coefficients);
            $predictions[] = $pred;
            $errors[] = $data['targets'][$i] - $pred;
        }

        $n = count($errors);

        // MSE (Mean Squared Error)
        $mse = array_sum(array_map(fn ($e) => $e * $e, $errors)) / $n;

        // RMSE (Root Mean Squared Error)
        $rmse = sqrt($mse);

        // MAE (Mean Absolute Error)
        $mae = array_sum(array_map('abs', $errors)) / $n;

        // R² (coefficient de détermination)
        $yMean = array_sum($data['targets']) / $n;
        $ssTot = array_sum(array_map(fn ($y) => ($y - $yMean) ** 2, $data['targets']));
        $ssRes = array_sum(array_map(fn ($e) => $e * $e, $errors));
        $r2 = $ssTot > 0 ? 1 - ($ssRes / $ssTot) : 0;

        return [
            'mse' => round($mse, 6),
            'rmse' => round($rmse, 6),
            'mae' => round($mae, 6),
            'r_squared' => round($r2, 4),
            'samples' => $n,
            'interpretation' => $this->interpretR2($r2),
        ];
    }

    /**
     * Effectue une prédiction linéaire.
     */
    private function linearPredict(array $features, array $coefficients): float
    {
        $prediction = 0.0;

        for ($i = 0; $i < count($coefficients) && $i < count($features); ++$i) {
            $prediction += $coefficients[$i] * $features[$i];
        }

        return $prediction;
    }

    /**
     * Prédit une valeur avec les coefficients donnés.
     */
    private function predictValue(array $features, array $coefficients): float
    {
        return $this->linearPredict($features, $coefficients);
    }

    /**
     * Convertit la prédiction linéaire en probabilités.
     */
    private function toProbabilities(float $prediction, string $sport): array
    {
        // La prédiction représente la différence de score normalisée
        // On utilise une fonction sigmoïde pour convertir en probabilités

        $homeWinProb = 1 / (1 + exp(-3 * $prediction)); // Sigmoïde

        if ('basketball' === $sport) {
            // Pas de match nul au basket
            return [
                'home' => round($homeWinProb, 4),
                'away' => round(1 - $homeWinProb, 4),
            ];
        }

        // Pour football et hockey, calculer probabilité de nul
        // Le nul est plus probable quand la prédiction est proche de 0
        $drawProb = exp(-($prediction ** 2) * 5) * 0.3; // Max ~30% pour nul

        // Ajuster les autres probabilités
        $remainingProb = 1 - $drawProb;

        return [
            'home' => round($homeWinProb * $remainingProb, 4),
            'draw' => round($drawProb, 4),
            'away' => round((1 - $homeWinProb) * $remainingProb, 4),
        ];
    }

    /**
     * Calcule la confiance de la prédiction.
     */
    private function calculateConfidence(array $features, array $coefficients): float
    {
        // La confiance est basée sur:
        // 1. L'écart par rapport à la moyenne (prédiction plus extrême = plus confiant)
        // 2. La qualité des features

        $prediction = abs($this->linearPredict($features, $coefficients));

        // Plus la prédiction est extrême, plus on est confiant
        $extremityFactor = min(1.0, $prediction * 2);

        // Facteur de qualité des données (features non-nulles)
        $nonZeroFeatures = count(array_filter($features, fn ($f) => abs($f) > 0.01));
        $qualityFactor = min(1.0, $nonZeroFeatures / count($features));

        $confidence = ($extremityFactor * 0.6 + $qualityFactor * 0.4);

        return round(min(0.95, max(0.1, $confidence)), 2);
    }

    /**
     * Calcule la confiance de la prédiction ensemble.
     */
    private function calculateEnsembleConfidence(array $regressionPred, array $otherPredictions): float
    {
        $confidences = [$regressionPred['confidence'] ?? 0.5];

        foreach ($otherPredictions as $pred) {
            if (isset($pred['confidence'])) {
                $confidences[] = $pred['confidence'];
            }
        }

        // Moyenne pondérée des confiances
        return round(array_sum($confidences) / count($confidences), 2);
    }

    /**
     * Retourne les coefficients pour un sport.
     */
    private function getCoefficients(string $sport): array
    {
        return match ($sport) {
            'football' => $this->footballCoefficients,
            'basketball' => $this->basketballCoefficients,
            'hockey' => $this->hockeyCoefficients,
            default => [],
        };
    }

    /**
     * Retourne les noms des features.
     */
    private function getFeatureNames(string $sport): array
    {
        $common = [
            'home_avg_score',
            'away_avg_score',
            'ranking_diff',
            'form_diff',
            'home_win_rate',
            'away_win_rate',
            'h2h_diff',
            'defense_diff',
        ];

        return match ($sport) {
            'football' => array_merge($common, ['clean_sheets', 'btts_rate']),
            'basketball' => array_merge($common, ['pace', 'offensive_efficiency']),
            'hockey' => array_merge($common, ['power_play', 'penalty_kill']),
            default => $common,
        };
    }

    /**
     * Retourne l'importance des features.
     */
    private function getFeatureImportance(array $coefficients): array
    {
        $importance = [];
        $total = array_sum(array_map('abs', $coefficients));

        foreach ($coefficients as $i => $coef) {
            $importance["feature_$i"] = $total > 0 ? abs($coef) / $total : 0;
        }

        return $importance;
    }

    /**
     * Récupère les statistiques head-to-head.
     */
    private function getHeadToHead($homeTeam, $awayTeam, string $sport): array
    {
        // Par défaut, retourner des valeurs neutres
        // TODO: Implémenter la récupération réelle depuis la DB
        return [
            'home_wins' => 0,
            'away_wins' => 0,
            'draws' => 0,
        ];
    }

    /**
     * Ajuste les scores selon le sport.
     */
    private function adjustScoresForSport(float $homeScore, float $awayScore, string $sport): array
    {
        // Dénormaliser et arrondir
        $home = match ($sport) {
            'football' => max(0, round($homeScore * 3, 0)),
            'basketball' => max(70, round($homeScore * 20 + 100, 0)),
            'hockey' => max(0, round($homeScore * 4 + 2.5, 0)),
            default => round($homeScore, 0),
        };

        $away = match ($sport) {
            'football' => max(0, round($awayScore * 3, 0)),
            'basketball' => max(70, round($awayScore * 20 + 95, 0)),
            'hockey' => max(0, round($awayScore * 4 + 2, 0)),
            default => round($awayScore, 0),
        };

        return [
            'home' => (int) $home,
            'away' => (int) $away,
        ];
    }

    /**
     * Interprète le R².
     */
    private function interpretR2(float $r2): string
    {
        return match (true) {
            $r2 >= 0.9 => 'Excellent - Le modèle explique très bien les données',
            $r2 >= 0.7 => 'Bon - Le modèle capture la majorité de la variance',
            $r2 >= 0.5 => 'Modéré - Le modèle a une capacité prédictive correcte',
            $r2 >= 0.3 => 'Faible - Le modèle capture quelques tendances',
            default => 'Très faible - Le modèle nécessite des améliorations',
        };
    }

    /**
     * Interprète les coefficients.
     */
    private function interpretCoefficients(array $importance, string $sport): array
    {
        $interpretations = [];

        foreach (array_slice($importance, 0, 5, true) as $feature => $data) {
            $effect = 'positive' === $data['direction']
                ? 'favorise l\'équipe à domicile'
                : 'favorise l\'équipe à l\'extérieur';

            $interpretations[] = "$feature: {$effect} (importance: ".round($data['absolute_importance'] * 100, 1).'%)';
        }

        return $interpretations;
    }
}
