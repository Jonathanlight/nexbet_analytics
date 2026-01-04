<?php

declare(strict_types=1);

namespace App\Service\Math;

/**
 * Service d'analyse des confrontations directes (Head-to-Head).
 * Analyse l'historique des matchs entre deux équipes pour affiner les prédictions.
 */
class HeadToHeadService
{
    private const MIN_MATCHES_FOR_SIGNIFICANCE = 3;
    private const RECENCY_DECAY = 0.9; // Décroissance par année

    /**
     * Analyse complète des confrontations directes.
     */
    public function analyzeHeadToHead(array $matches, string $team1, string $team2): array
    {
        if (empty($matches)) {
            return $this->getEmptyAnalysis();
        }

        $stats = $this->calculateBasicStats($matches, $team1, $team2);
        $trends = $this->analyzeTrends($matches, $team1, $team2);
        $venueAnalysis = $this->analyzeByVenue($matches, $team1, $team2);
        $scoringPatterns = $this->analyzeScoringPatterns($matches);
        $recentForm = $this->analyzeRecentH2H($matches, $team1, $team2);
        $psychologicalEdge = $this->calculatePsychologicalEdge($stats, $trends);

        return [
            'total_matches' => count($matches),
            'basic_stats' => $stats,
            'trends' => $trends,
            'venue_analysis' => $venueAnalysis,
            'scoring_patterns' => $scoringPatterns,
            'recent_form' => $recentForm,
            'psychological_edge' => $psychologicalEdge,
            'prediction_adjustments' => $this->calculateAdjustments($stats, $trends, $recentForm),
            'significance' => $this->calculateSignificance($matches),
            'confidence' => $this->calculateConfidence($matches, $stats),
        ];
    }

    /**
     * Calcule les statistiques de base.
     */
    private function calculateBasicStats(array $matches, string $team1, string $team2): array
    {
        $team1Wins = 0;
        $team2Wins = 0;
        $draws = 0;
        $team1Goals = 0;
        $team2Goals = 0;

        foreach ($matches as $match) {
            $homeTeam = $match['home_team'] ?? '';
            $homeGoals = $match['home_score'] ?? 0;
            $awayGoals = $match['away_score'] ?? 0;

            $isTeam1Home = $this->isTeamMatch($homeTeam, $team1);

            if ($isTeam1Home) {
                $team1Goals += $homeGoals;
                $team2Goals += $awayGoals;

                if ($homeGoals > $awayGoals) {
                    ++$team1Wins;
                } elseif ($homeGoals < $awayGoals) {
                    ++$team2Wins;
                } else {
                    ++$draws;
                }
            } else {
                $team1Goals += $awayGoals;
                $team2Goals += $homeGoals;

                if ($awayGoals > $homeGoals) {
                    ++$team1Wins;
                } elseif ($awayGoals < $homeGoals) {
                    ++$team2Wins;
                } else {
                    ++$draws;
                }
            }
        }

        $totalMatches = count($matches);

        return [
            'team1_wins' => $team1Wins,
            'team2_wins' => $team2Wins,
            'draws' => $draws,
            'team1_win_rate' => round(($team1Wins / $totalMatches) * 100, 2),
            'team2_win_rate' => round(($team2Wins / $totalMatches) * 100, 2),
            'draw_rate' => round(($draws / $totalMatches) * 100, 2),
            'team1_goals' => $team1Goals,
            'team2_goals' => $team2Goals,
            'team1_avg_goals' => round($team1Goals / $totalMatches, 2),
            'team2_avg_goals' => round($team2Goals / $totalMatches, 2),
            'total_avg_goals' => round(($team1Goals + $team2Goals) / $totalMatches, 2),
            'dominant_team' => $team1Wins > $team2Wins ? $team1 : ($team2Wins > $team1Wins ? $team2 : 'balanced'),
        ];
    }

    /**
     * Analyse les tendances des confrontations.
     */
    private function analyzeTrends(array $matches, string $team1, string $team2): array
    {
        if (count($matches) < 4) {
            return ['available' => false];
        }

        // Séparer les matchs récents et anciens
        $recentCount = (int) ceil(count($matches) / 2);
        $recentMatches = array_slice($matches, 0, $recentCount);
        $olderMatches = array_slice($matches, $recentCount);

        $recentStats = $this->calculateBasicStats($recentMatches, $team1, $team2);
        $olderStats = $this->calculateBasicStats($olderMatches, $team1, $team2);

        $team1TrendChange = $recentStats['team1_win_rate'] - $olderStats['team1_win_rate'];
        $goalsTrendChange = $recentStats['total_avg_goals'] - $olderStats['total_avg_goals'];

        return [
            'available' => true,
            'team1_trend' => $team1TrendChange > 5 ? 'improving' : ($team1TrendChange < -5 ? 'declining' : 'stable'),
            'team1_trend_value' => round($team1TrendChange, 2),
            'goals_trend' => $goalsTrendChange > 0.3 ? 'increasing' : ($goalsTrendChange < -0.3 ? 'decreasing' : 'stable'),
            'goals_trend_value' => round($goalsTrendChange, 2),
            'recent_period_stats' => $recentStats,
            'older_period_stats' => $olderStats,
            'momentum_shift' => abs($team1TrendChange) > 15 ? 'significant' : 'minor',
        ];
    }

    /**
     * Analyse par lieu (domicile/extérieur).
     */
    private function analyzeByVenue(array $matches, string $team1, string $team2): array
    {
        $team1Home = ['wins' => 0, 'draws' => 0, 'losses' => 0, 'goals_for' => 0, 'goals_against' => 0, 'matches' => 0];
        $team1Away = ['wins' => 0, 'draws' => 0, 'losses' => 0, 'goals_for' => 0, 'goals_against' => 0, 'matches' => 0];

        foreach ($matches as $match) {
            $homeTeam = $match['home_team'] ?? '';
            $homeGoals = $match['home_score'] ?? 0;
            $awayGoals = $match['away_score'] ?? 0;

            if ($this->isTeamMatch($homeTeam, $team1)) {
                ++$team1Home['matches'];
                $team1Home['goals_for'] += $homeGoals;
                $team1Home['goals_against'] += $awayGoals;

                if ($homeGoals > $awayGoals) {
                    ++$team1Home['wins'];
                } elseif ($homeGoals < $awayGoals) {
                    ++$team1Home['losses'];
                } else {
                    ++$team1Home['draws'];
                }
            } else {
                ++$team1Away['matches'];
                $team1Away['goals_for'] += $awayGoals;
                $team1Away['goals_against'] += $homeGoals;

                if ($awayGoals > $homeGoals) {
                    ++$team1Away['wins'];
                } elseif ($awayGoals < $homeGoals) {
                    ++$team1Away['losses'];
                } else {
                    ++$team1Away['draws'];
                }
            }
        }

        $team1HomeWinRate = $team1Home['matches'] > 0 ? ($team1Home['wins'] / $team1Home['matches']) * 100 : 0;
        $team1AwayWinRate = $team1Away['matches'] > 0 ? ($team1Away['wins'] / $team1Away['matches']) * 100 : 0;

        return [
            'team1_home' => [
                'win_rate' => round($team1HomeWinRate, 2),
                'avg_goals_scored' => $team1Home['matches'] > 0 ? round($team1Home['goals_for'] / $team1Home['matches'], 2) : 0,
                'avg_goals_conceded' => $team1Home['matches'] > 0 ? round($team1Home['goals_against'] / $team1Home['matches'], 2) : 0,
                'matches' => $team1Home['matches'],
            ],
            'team1_away' => [
                'win_rate' => round($team1AwayWinRate, 2),
                'avg_goals_scored' => $team1Away['matches'] > 0 ? round($team1Away['goals_for'] / $team1Away['matches'], 2) : 0,
                'avg_goals_conceded' => $team1Away['matches'] > 0 ? round($team1Away['goals_against'] / $team1Away['matches'], 2) : 0,
                'matches' => $team1Away['matches'],
            ],
            'home_advantage_factor' => $team1HomeWinRate > $team1AwayWinRate ? round($team1HomeWinRate - $team1AwayWinRate, 2) : 0,
        ];
    }

    /**
     * Analyse les patterns de score.
     */
    private function analyzeScoringPatterns(array $matches): array
    {
        $overUnder = ['over_1.5' => 0, 'over_2.5' => 0, 'over_3.5' => 0];
        $btts = ['yes' => 0, 'no' => 0];
        $cleanSheets = ['home' => 0, 'away' => 0];
        $scoreDistribution = [];

        foreach ($matches as $match) {
            $homeGoals = $match['home_score'] ?? 0;
            $awayGoals = $match['away_score'] ?? 0;
            $totalGoals = $homeGoals + $awayGoals;

            if ($totalGoals > 1.5) {
                ++$overUnder['over_1.5'];
            }
            if ($totalGoals > 2.5) {
                ++$overUnder['over_2.5'];
            }
            if ($totalGoals > 3.5) {
                ++$overUnder['over_3.5'];
            }

            if ($homeGoals > 0 && $awayGoals > 0) {
                ++$btts['yes'];
            } else {
                ++$btts['no'];
            }

            if (0 === $awayGoals) {
                ++$cleanSheets['home'];
            }
            if (0 === $homeGoals) {
                ++$cleanSheets['away'];
            }

            $scoreKey = "{$homeGoals}-{$awayGoals}";
            $scoreDistribution[$scoreKey] = ($scoreDistribution[$scoreKey] ?? 0) + 1;
        }

        $totalMatches = count($matches);
        arsort($scoreDistribution);

        return [
            'over_under' => [
                'over_1.5' => round(($overUnder['over_1.5'] / $totalMatches) * 100, 2),
                'over_2.5' => round(($overUnder['over_2.5'] / $totalMatches) * 100, 2),
                'over_3.5' => round(($overUnder['over_3.5'] / $totalMatches) * 100, 2),
            ],
            'btts' => [
                'yes' => round(($btts['yes'] / $totalMatches) * 100, 2),
                'no' => round(($btts['no'] / $totalMatches) * 100, 2),
            ],
            'clean_sheets' => [
                'home' => round(($cleanSheets['home'] / $totalMatches) * 100, 2),
                'away' => round(($cleanSheets['away'] / $totalMatches) * 100, 2),
            ],
            'most_common_scores' => array_slice($scoreDistribution, 0, 5, true),
        ];
    }

    /**
     * Analyse les confrontations récentes avec pondération.
     */
    private function analyzeRecentH2H(array $matches, string $team1, string $team2, int $recentCount = 5): array
    {
        $recentMatches = array_slice($matches, 0, $recentCount);

        if (empty($recentMatches)) {
            return ['available' => false];
        }

        $weightedTeam1Score = 0;
        $totalWeight = 0;

        foreach ($recentMatches as $i => $match) {
            $weight = pow(self::RECENCY_DECAY, $i);
            $totalWeight += $weight;

            $homeTeam = $match['home_team'] ?? '';
            $homeGoals = $match['home_score'] ?? 0;
            $awayGoals = $match['away_score'] ?? 0;

            $isTeam1Home = $this->isTeamMatch($homeTeam, $team1);

            if ($isTeam1Home) {
                if ($homeGoals > $awayGoals) {
                    $weightedTeam1Score += $weight;
                } elseif ($homeGoals === $awayGoals) {
                    $weightedTeam1Score += 0.5 * $weight;
                }
            } else {
                if ($awayGoals > $homeGoals) {
                    $weightedTeam1Score += $weight;
                } elseif ($awayGoals === $homeGoals) {
                    $weightedTeam1Score += 0.5 * $weight;
                }
            }
        }

        $normalizedScore = $weightedTeam1Score / $totalWeight;

        return [
            'available' => true,
            'matches_analyzed' => count($recentMatches),
            'weighted_score' => round($normalizedScore, 3),
            'recent_dominance' => $normalizedScore > 0.6 ? $team1 : ($normalizedScore < 0.4 ? $team2 : 'balanced'),
            'last_match' => $recentMatches[0] ?? null,
        ];
    }

    /**
     * Calcule l'avantage psychologique.
     */
    private function calculatePsychologicalEdge(array $stats, array $trends): array
    {
        $edge = 0;
        $factors = [];

        // Dominance historique
        $winDiff = $stats['team1_wins'] - $stats['team2_wins'];
        if ($winDiff > 2) {
            $edge += 10;
            $factors[] = 'historical_dominance';
        } elseif ($winDiff < -2) {
            $edge -= 10;
            $factors[] = 'historical_inferiority';
        }

        // Tendance récente
        if (isset($trends['team1_trend']) && 'improving' === $trends['team1_trend']) {
            $edge += 5;
            $factors[] = 'improving_trend';
        } elseif (isset($trends['team1_trend']) && 'declining' === $trends['team1_trend']) {
            $edge -= 5;
            $factors[] = 'declining_trend';
        }

        // Goal difference
        $goalDiff = $stats['team1_goals'] - $stats['team2_goals'];
        $edge += min(10, max(-10, $goalDiff));

        return [
            'edge_score' => round($edge, 2),
            'advantage_team' => $edge > 5 ? 'team1' : ($edge < -5 ? 'team2' : 'neutral'),
            'factors' => $factors,
            'confidence' => min(100, abs($edge) * 5),
        ];
    }

    /**
     * Calcule les ajustements de prédiction basés sur l'H2H.
     */
    private function calculateAdjustments(array $stats, array $trends, array $recentForm): array
    {
        $homeBoost = 0;
        $awayBoost = 0;
        $drawAdjust = 0;

        // Ajustement basé sur le taux de victoire historique
        $winRateDiff = $stats['team1_win_rate'] - $stats['team2_win_rate'];
        $homeBoost += $winRateDiff * 0.1;
        $awayBoost -= $winRateDiff * 0.1;

        // Ajustement basé sur le taux de nuls
        if ($stats['draw_rate'] > 30) {
            $drawAdjust += 5;
            $homeBoost -= 2.5;
            $awayBoost -= 2.5;
        } elseif ($stats['draw_rate'] < 15) {
            $drawAdjust -= 5;
        }

        // Ajustement basé sur la forme récente H2H
        if (isset($recentForm['weighted_score'])) {
            $recentWeight = ($recentForm['weighted_score'] - 0.5) * 10;
            $homeBoost += $recentWeight;
            $awayBoost -= $recentWeight;
        }

        // Ajustement xG basé sur les moyennes de buts H2H
        $avgTotalGoals = $stats['total_avg_goals'];
        $xgMultiplier = $avgTotalGoals / 2.5; // 2.5 étant la moyenne typique

        return [
            'home_boost' => round($homeBoost, 2),
            'draw_adjust' => round($drawAdjust, 2),
            'away_boost' => round($awayBoost, 2),
            'xg_multiplier' => round($xgMultiplier, 3),
            'btts_adjustment' => $stats['team1_avg_goals'] > 1 && $stats['team2_avg_goals'] > 1 ? 5 : -5,
        ];
    }

    /**
     * Calcule la significativité statistique.
     */
    private function calculateSignificance(array $matches): array
    {
        $matchCount = count($matches);
        $isSignificant = $matchCount >= self::MIN_MATCHES_FOR_SIGNIFICANCE;

        $level = 'none';
        if ($matchCount >= 10) {
            $level = 'high';
        } elseif ($matchCount >= 5) {
            $level = 'medium';
        } elseif ($matchCount >= 3) {
            $level = 'low';
        }

        return [
            'is_significant' => $isSignificant,
            'level' => $level,
            'match_count' => $matchCount,
            'recommendation' => $isSignificant
                ? 'Les données H2H peuvent être utilisées avec confiance'
                : 'Données H2H insuffisantes, se fier aux autres facteurs',
        ];
    }

    /**
     * Calcule le niveau de confiance global.
     */
    private function calculateConfidence(array $matches, array $stats): float
    {
        $matchCount = count($matches);

        // Base confidence selon le nombre de matchs
        $baseConfidence = min(50, $matchCount * 5);

        // Bonus si résultats cohérents
        $winRates = [$stats['team1_win_rate'], $stats['draw_rate'], $stats['team2_win_rate']];
        $maxWinRate = max($winRates);
        $consistencyBonus = ($maxWinRate - 33.33) * 0.5;

        return round(min(100, $baseConfidence + $consistencyBonus), 2);
    }

    private function isTeamMatch(string $teamName, string $searchTeam): bool
    {
        return false !== stripos($teamName, $searchTeam) || false !== stripos($searchTeam, $teamName);
    }

    private function getEmptyAnalysis(): array
    {
        return [
            'total_matches' => 0,
            'basic_stats' => null,
            'trends' => ['available' => false],
            'venue_analysis' => null,
            'scoring_patterns' => null,
            'recent_form' => ['available' => false],
            'psychological_edge' => ['edge_score' => 0, 'advantage_team' => 'neutral'],
            'prediction_adjustments' => [
                'home_boost' => 0,
                'draw_adjust' => 0,
                'away_boost' => 0,
                'xg_multiplier' => 1.0,
            ],
            'significance' => ['is_significant' => false, 'level' => 'none'],
            'confidence' => 0,
        ];
    }
}
