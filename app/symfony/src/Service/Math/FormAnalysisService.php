<?php

declare(strict_types=1);

namespace App\Service\Math;

/**
 * Service d'analyse de forme avec:
 * - Pondération exponentielle des matchs récents
 * - Analyse de tendance (momentum)
 * - Distinction domicile/extérieur
 * - Analyse des performances offensives/défensives
 */
class FormAnalysisService
{
    private const DECAY_FACTOR = 0.85; // Facteur de décroissance par match
    private const MAX_MATCHES = 20;

    /**
     * Analyse complète de la forme d'une équipe.
     */
    public function analyzeForm(array $matches, bool $homeOnly = false, bool $awayOnly = false): array
    {
        if (empty($matches)) {
            return $this->getEmptyFormAnalysis();
        }

        // Filtrer selon domicile/extérieur si nécessaire
        if ($homeOnly) {
            $matches = array_filter($matches, fn ($m) => $m['is_home'] ?? false);
        } elseif ($awayOnly) {
            $matches = array_filter($matches, fn ($m) => !($m['is_home'] ?? true));
        }

        $matches = array_slice(array_values($matches), 0, self::MAX_MATCHES);

        if (empty($matches)) {
            return $this->getEmptyFormAnalysis();
        }

        // Calculer les métriques pondérées
        $weightedStats = $this->calculateWeightedStats($matches);
        $streaks = $this->calculateStreaks($matches);
        $trend = $this->calculateTrend($matches);
        $consistency = $this->calculateConsistency($matches);
        $periodAnalysis = $this->analyzePeriods($matches);

        return [
            'weighted_stats' => $weightedStats,
            'streaks' => $streaks,
            'trend' => $trend,
            'consistency' => $consistency,
            'period_analysis' => $periodAnalysis,
            'form_rating' => $this->calculateFormRating($weightedStats, $trend, $consistency),
            'matches_analyzed' => count($matches),
        ];
    }

    /**
     * Compare la forme de deux équipes.
     */
    public function compareForm(array $homeTeamMatches, array $awayTeamMatches): array
    {
        $homeForm = $this->analyzeForm($homeTeamMatches, true);
        $awayForm = $this->analyzeForm($awayTeamMatches, false, true);

        $homeRating = $homeForm['form_rating'];
        $awayRating = $awayForm['form_rating'];

        // Avantage de forme (0-100, 50 = égal)
        $formAdvantage = 50 + (($homeRating - $awayRating) / 2);
        $formAdvantage = max(0, min(100, $formAdvantage));

        return [
            'home_form' => $homeForm,
            'away_form' => $awayForm,
            'form_advantage' => round($formAdvantage, 2),
            'advantage_team' => $formAdvantage > 55 ? 'home' : ($formAdvantage < 45 ? 'away' : 'neutral'),
            'form_diff' => round($homeRating - $awayRating, 2),
            'prediction_adjustment' => $this->calculatePredictionAdjustment($homeForm, $awayForm),
        ];
    }

    /**
     * Calcule les statistiques pondérées.
     */
    private function calculateWeightedStats(array $matches): array
    {
        $totalWeight = 0;
        $weightedWins = 0;
        $weightedDraws = 0;
        $weightedLosses = 0;
        $weightedGoalsScored = 0;
        $weightedGoalsConceded = 0;
        $weightedPoints = 0;
        $weightedCleanSheets = 0;
        $weightedBTTS = 0;

        foreach ($matches as $i => $match) {
            $weight = pow(self::DECAY_FACTOR, $i);
            $totalWeight += $weight;

            $goalsScored = $match['goals_scored'] ?? 0;
            $goalsConceded = $match['goals_conceded'] ?? 0;

            $weightedGoalsScored += $goalsScored * $weight;
            $weightedGoalsConceded += $goalsConceded * $weight;

            if (0 === $goalsConceded) {
                $weightedCleanSheets += $weight;
            }

            if ($goalsScored > 0 && $goalsConceded > 0) {
                $weightedBTTS += $weight;
            }

            $result = $match['result'] ?? 'D';
            if ('W' === $result || 1 === $result) {
                $weightedWins += $weight;
                $weightedPoints += 3 * $weight;
            } elseif ('D' === $result || 0.5 === $result) {
                $weightedDraws += $weight;
                $weightedPoints += 1 * $weight;
            } else {
                $weightedLosses += $weight;
            }
        }

        return [
            'weighted_ppg' => round($weightedPoints / $totalWeight, 3),
            'weighted_win_rate' => round(($weightedWins / $totalWeight) * 100, 2),
            'weighted_draw_rate' => round(($weightedDraws / $totalWeight) * 100, 2),
            'weighted_loss_rate' => round(($weightedLosses / $totalWeight) * 100, 2),
            'weighted_goals_scored' => round($weightedGoalsScored / $totalWeight, 3),
            'weighted_goals_conceded' => round($weightedGoalsConceded / $totalWeight, 3),
            'weighted_goal_diff' => round(($weightedGoalsScored - $weightedGoalsConceded) / $totalWeight, 3),
            'weighted_clean_sheet_rate' => round(($weightedCleanSheets / $totalWeight) * 100, 2),
            'weighted_btts_rate' => round(($weightedBTTS / $totalWeight) * 100, 2),
            'total_weight' => round($totalWeight, 3),
        ];
    }

    /**
     * Calcule les séries en cours.
     */
    private function calculateStreaks(array $matches): array
    {
        $currentStreak = ['type' => null, 'count' => 0];
        $longestWinStreak = 0;
        $longestUnbeatenStreak = 0;
        $longestLossStreak = 0;
        $longestNoWinStreak = 0;
        $currentWin = 0;
        $currentUnbeaten = 0;
        $currentLoss = 0;
        $currentNoWin = 0;
        $scoringStreak = 0;
        $cleanSheetStreak = 0;

        foreach ($matches as $i => $match) {
            $result = $match['result'] ?? 'D';
            $goalsScored = $match['goals_scored'] ?? 0;
            $goalsConceded = $match['goals_conceded'] ?? 0;

            // Série actuelle (pour le match le plus récent)
            if (0 === $i) {
                if ('W' === $result || 1 === $result) {
                    $currentStreak['type'] = 'win';
                } elseif ('D' === $result || 0.5 === $result) {
                    $currentStreak['type'] = 'draw';
                } else {
                    $currentStreak['type'] = 'loss';
                }
            }

            // Calculer les différentes séries
            if ('W' === $result || 1 === $result) {
                ++$currentWin;
                ++$currentUnbeaten;
                $currentLoss = 0;
                $currentNoWin = 0;
            } elseif ('D' === $result || 0.5 === $result) {
                $currentWin = 0;
                ++$currentUnbeaten;
                $currentLoss = 0;
                ++$currentNoWin;
            } else {
                $currentWin = 0;
                $currentUnbeaten = 0;
                ++$currentLoss;
                ++$currentNoWin;
            }

            // Série de buts marqués
            if ($goalsScored > 0) {
                ++$scoringStreak;
            } else {
                $scoringStreak = 0;
            }

            // Série de clean sheets
            if (0 === $goalsConceded) {
                ++$cleanSheetStreak;
            } else {
                $cleanSheetStreak = 0;
            }

            // Mettre à jour les maximums
            $longestWinStreak = max($longestWinStreak, $currentWin);
            $longestUnbeatenStreak = max($longestUnbeatenStreak, $currentUnbeaten);
            $longestLossStreak = max($longestLossStreak, $currentLoss);
            $longestNoWinStreak = max($longestNoWinStreak, $currentNoWin);

            // Compter la série actuelle
            if (null !== $currentStreak['type']) {
                $isMatchingSeries = match ($currentStreak['type']) {
                    'win' => 'W' === $result || 1 === $result,
                    'draw' => 'D' === $result || 0.5 === $result,
                    'loss' => 'L' === $result || 0 === $result,
                    default => false,
                };

                if ($isMatchingSeries || ('unbeaten' === $currentStreak['type'] && ('L' !== $result && 0 !== $result))) {
                    ++$currentStreak['count'];
                } else {
                    break;
                }
            }
        }

        return [
            'current' => $currentStreak,
            'longest_win' => $longestWinStreak,
            'longest_unbeaten' => $longestUnbeatenStreak,
            'longest_loss' => $longestLossStreak,
            'longest_no_win' => $longestNoWinStreak,
            'scoring_streak' => $scoringStreak,
            'clean_sheet_streak' => $cleanSheetStreak,
        ];
    }

    /**
     * Calcule la tendance (amélioration ou détérioration).
     */
    private function calculateTrend(array $matches): array
    {
        if (count($matches) < 4) {
            return ['direction' => 'neutral', 'strength' => 0, 'confidence' => 0];
        }

        $halfPoint = (int) ceil(count($matches) / 2);
        $recentMatches = array_slice($matches, 0, $halfPoint);
        $olderMatches = array_slice($matches, $halfPoint);

        $recentPPG = $this->calculatePPG($recentMatches);
        $olderPPG = $this->calculatePPG($olderMatches);

        $recentGoals = $this->calculateAvgGoals($recentMatches);
        $olderGoals = $this->calculateAvgGoals($olderMatches);

        $ppgDiff = $recentPPG - $olderPPG;
        $goalsDiff = $recentGoals['scored'] - $olderGoals['scored'];

        $direction = 'neutral';
        if ($ppgDiff > 0.3) {
            $direction = 'improving';
        } elseif ($ppgDiff < -0.3) {
            $direction = 'declining';
        }

        return [
            'direction' => $direction,
            'ppg_change' => round($ppgDiff, 3),
            'goals_change' => round($goalsDiff, 3),
            'recent_ppg' => round($recentPPG, 3),
            'older_ppg' => round($olderPPG, 3),
            'strength' => round(abs($ppgDiff) * 33.33, 2), // 0-100
            'confidence' => min(100, count($matches) * 5),
        ];
    }

    /**
     * Calcule la consistance des performances.
     */
    private function calculateConsistency(array $matches): array
    {
        if (count($matches) < 3) {
            return ['score' => 50, 'variance' => 0, 'std_dev' => 0];
        }

        $points = [];
        $goalDiffs = [];

        foreach ($matches as $match) {
            $result = $match['result'] ?? 'D';
            $goalsScored = $match['goals_scored'] ?? 0;
            $goalsConceded = $match['goals_conceded'] ?? 0;

            if ('W' === $result || 1 === $result) {
                $points[] = 3;
            } elseif ('D' === $result || 0.5 === $result) {
                $points[] = 1;
            } else {
                $points[] = 0;
            }

            $goalDiffs[] = $goalsScored - $goalsConceded;
        }

        $pointsVariance = $this->calculateVariance($points);
        $goalDiffVariance = $this->calculateVariance($goalDiffs);

        // Score de consistance: moins de variance = plus consistant
        $consistencyScore = 100 - min(100, ($pointsVariance * 20) + ($goalDiffVariance * 10));

        return [
            'score' => round($consistencyScore, 2),
            'points_variance' => round($pointsVariance, 3),
            'points_std_dev' => round(sqrt($pointsVariance), 3),
            'goal_diff_variance' => round($goalDiffVariance, 3),
            'assessment' => $consistencyScore > 70 ? 'consistent' : ($consistencyScore > 40 ? 'average' : 'inconsistent'),
        ];
    }

    /**
     * Analyse les performances par période (mi-temps).
     */
    private function analyzePeriods(array $matches): array
    {
        $firstHalfGoals = 0;
        $secondHalfGoals = 0;
        $firstHalfConceded = 0;
        $secondHalfConceded = 0;
        $matchesWithData = 0;

        foreach ($matches as $match) {
            if (isset($match['first_half_goals']) && isset($match['second_half_goals'])) {
                $firstHalfGoals += $match['first_half_goals'];
                $secondHalfGoals += $match['second_half_goals'];
                $firstHalfConceded += $match['first_half_conceded'] ?? 0;
                $secondHalfConceded += $match['second_half_conceded'] ?? 0;
                ++$matchesWithData;
            }
        }

        if (0 === $matchesWithData) {
            return ['available' => false];
        }

        return [
            'available' => true,
            'first_half_avg_scored' => round($firstHalfGoals / $matchesWithData, 2),
            'second_half_avg_scored' => round($secondHalfGoals / $matchesWithData, 2),
            'first_half_avg_conceded' => round($firstHalfConceded / $matchesWithData, 2),
            'second_half_avg_conceded' => round($secondHalfConceded / $matchesWithData, 2),
            'stronger_half' => $firstHalfGoals > $secondHalfGoals ? 'first' : 'second',
            'weaker_half_defense' => $firstHalfConceded > $secondHalfConceded ? 'first' : 'second',
        ];
    }

    /**
     * Calcule le rating de forme global.
     */
    private function calculateFormRating(array $stats, array $trend, array $consistency): float
    {
        // PPG contribue 40%
        $ppgScore = ($stats['weighted_ppg'] / 3) * 40;

        // Différence de buts contribue 20%
        $gdScore = max(0, min(20, 10 + $stats['weighted_goal_diff'] * 5));

        // Tendance contribue 20%
        $trendScore = 10;
        if ('improving' === $trend['direction']) {
            $trendScore += min(10, $trend['strength'] / 10);
        } elseif ('declining' === $trend['direction']) {
            $trendScore -= min(10, $trend['strength'] / 10);
        }

        // Consistance contribue 20%
        $consistencyScore = $consistency['score'] * 0.2;

        return round($ppgScore + $gdScore + $trendScore + $consistencyScore, 2);
    }

    /**
     * Calcule l'ajustement de prédiction basé sur la forme.
     */
    private function calculatePredictionAdjustment(array $homeForm, array $awayForm): array
    {
        $homeRating = $homeForm['form_rating'];
        $awayRating = $awayForm['form_rating'];

        $diff = $homeRating - $awayRating;

        // Ajustements en pourcentage
        $homeBoost = round($diff * 0.3, 2);
        $awayBoost = round(-$diff * 0.3, 2);
        $drawAdjust = round(-abs($diff) * 0.1, 2);

        return [
            'home_boost' => $homeBoost,
            'draw_adjust' => $drawAdjust,
            'away_boost' => $awayBoost,
            'xg_home_multiplier' => round(1 + ($diff * 0.005), 3),
            'xg_away_multiplier' => round(1 - ($diff * 0.005), 3),
        ];
    }

    private function calculatePPG(array $matches): float
    {
        if (empty($matches)) {
            return 0;
        }

        $points = 0;
        foreach ($matches as $match) {
            $result = $match['result'] ?? 'D';
            if ('W' === $result || 1 === $result) {
                $points += 3;
            } elseif ('D' === $result || 0.5 === $result) {
                ++$points;
            }
        }

        return $points / count($matches);
    }

    private function calculateAvgGoals(array $matches): array
    {
        if (empty($matches)) {
            return ['scored' => 0, 'conceded' => 0];
        }

        $scored = 0;
        $conceded = 0;

        foreach ($matches as $match) {
            $scored += $match['goals_scored'] ?? 0;
            $conceded += $match['goals_conceded'] ?? 0;
        }

        return [
            'scored' => $scored / count($matches),
            'conceded' => $conceded / count($matches),
        ];
    }

    private function calculateVariance(array $values): float
    {
        if (count($values) < 2) {
            return 0;
        }

        $mean = array_sum($values) / count($values);
        $squaredDiffs = array_map(fn ($v) => pow($v - $mean, 2), $values);

        return array_sum($squaredDiffs) / count($values);
    }

    private function getEmptyFormAnalysis(): array
    {
        return [
            'weighted_stats' => [
                'weighted_ppg' => 0,
                'weighted_win_rate' => 0,
                'weighted_draw_rate' => 0,
                'weighted_loss_rate' => 0,
                'weighted_goals_scored' => 0,
                'weighted_goals_conceded' => 0,
                'weighted_goal_diff' => 0,
                'weighted_clean_sheet_rate' => 0,
                'weighted_btts_rate' => 0,
            ],
            'streaks' => ['current' => ['type' => null, 'count' => 0]],
            'trend' => ['direction' => 'neutral', 'strength' => 0],
            'consistency' => ['score' => 50],
            'period_analysis' => ['available' => false],
            'form_rating' => 50,
            'matches_analyzed' => 0,
        ];
    }
}
