<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

/**
 * Factory de fixtures pour les prédictions.
 */
final class PredictionFixtures
{
    /**
     * Crée des statistiques d'équipe simulées.
     */
    public static function createTeamStats(array $overrides = []): array
    {
        return array_merge([
            'matches_played' => 20,
            'wins' => 12,
            'draws' => 5,
            'losses' => 3,
            'goals_for' => 35,
            'goals_against' => 18,
            'clean_sheets' => 8,
            'failed_to_score' => 2,
            'avg_goals_scored' => 1.75,
            'avg_goals_conceded' => 0.90,
            'form' => ['W', 'W', 'D', 'W', 'W'],
            'form_points' => 13,
            'home_wins' => 7,
            'home_draws' => 2,
            'home_losses' => 1,
            'away_wins' => 5,
            'away_draws' => 3,
            'away_losses' => 2,
        ], $overrides);
    }

    /**
     * Crée des données head-to-head simulées.
     */
    public static function createH2HData(array $overrides = []): array
    {
        return array_merge([
            'total_matches' => 10,
            'home_wins' => 4,
            'draws' => 3,
            'away_wins' => 3,
            'home_goals' => 12,
            'away_goals' => 10,
            'avg_total_goals' => 2.2,
            'btts_percentage' => 60.0,
            'over_25_percentage' => 50.0,
            'recent_matches' => [
                ['home' => 2, 'away' => 1, 'result' => 'H'],
                ['home' => 1, 'away' => 1, 'result' => 'D'],
                ['home' => 0, 'away' => 2, 'result' => 'A'],
                ['home' => 3, 'away' => 1, 'result' => 'H'],
                ['home' => 2, 'away' => 2, 'result' => 'D'],
            ],
        ], $overrides);
    }

    /**
     * Crée un résultat de prédiction simulé.
     */
    public static function createPredictionResult(array $overrides = []): array
    {
        return array_merge([
            'match_id' => 1,
            'home_team' => 'Paris Saint-Germain',
            'away_team' => 'Olympique de Marseille',
            'predictions' => [
                'home_win' => 55.5,
                'draw' => 25.0,
                'away_win' => 19.5,
            ],
            'over_under' => [
                'over_25' => 62.0,
                'under_25' => 38.0,
                'over_35' => 35.0,
                'under_35' => 65.0,
            ],
            'btts' => [
                'yes' => 58.0,
                'no' => 42.0,
            ],
            'exact_score' => [
                '2-1' => 12.5,
                '1-0' => 10.2,
                '2-0' => 9.8,
                '1-1' => 8.5,
                '0-0' => 5.2,
            ],
            'confidence' => 72.5,
            'model_weights' => [
                'poisson' => 0.25,
                'monte_carlo' => 0.20,
                'elo' => 0.15,
                'dixon_coles' => 0.15,
                'regression' => 0.15,
                'form' => 0.10,
            ],
            'created_at' => date('Y-m-d H:i:s'),
        ], $overrides);
    }

    /**
     * Crée des coefficients de régression simulés.
     */
    public static function createRegressionCoefficients(): array
    {
        return [
            'intercept' => 0.35,
            'avg_goals_diff' => 0.25,
            'ranking_diff' => 0.15,
            'form_diff' => 0.12,
            'home_win_rate' => 0.20,
            'away_win_rate' => -0.15,
            'h2h_diff' => 0.08,
            'defense_diff' => -0.18,
            'clean_sheets_rate' => 0.10,
            'btts_rate' => 0.05,
        ];
    }

    /**
     * Crée des statistiques de validation simulées.
     */
    public static function createValidationStats(array $overrides = []): array
    {
        return array_merge([
            'total_predictions' => 100,
            'correct_predictions' => 68,
            'accuracy' => 68.0,
            'precision' => [
                'home_win' => 72.5,
                'draw' => 45.0,
                'away_win' => 65.0,
            ],
            'recall' => [
                'home_win' => 78.0,
                'draw' => 38.0,
                'away_win' => 62.0,
            ],
            'f1_score' => [
                'home_win' => 75.1,
                'draw' => 41.2,
                'away_win' => 63.5,
            ],
            'roi' => [
                'home_win' => 8.5,
                'draw' => -12.0,
                'away_win' => 5.2,
            ],
            'calibration_error' => 0.045,
            'log_loss' => 0.62,
        ], $overrides);
    }

    /**
     * Crée des données Monte Carlo simulées.
     */
    public static function createMonteCarloResult(array $overrides = []): array
    {
        return array_merge([
            'simulations' => 10000,
            'home_wins' => 5520,
            'draws' => 2480,
            'away_wins' => 2000,
            'home_win_prob' => 55.2,
            'draw_prob' => 24.8,
            'away_win_prob' => 20.0,
            'avg_home_goals' => 1.82,
            'avg_away_goals' => 1.15,
            'over_25_prob' => 58.5,
            'btts_prob' => 52.0,
            'confidence_interval' => [
                'home_win' => [52.1, 58.3],
                'draw' => [22.0, 27.6],
                'away_win' => [17.5, 22.5],
            ],
        ], $overrides);
    }

    /**
     * Crée des ratings Elo simulés.
     */
    public static function createEloRatings(): array
    {
        return [
            'Paris Saint-Germain' => 1820,
            'Olympique de Marseille' => 1680,
            'Real Madrid' => 1850,
            'Barcelona' => 1810,
            'Manchester City' => 1870,
            'Liverpool' => 1800,
            'Bayern Munich' => 1840,
            'Borussia Dortmund' => 1720,
            'Juventus' => 1750,
            'Inter Milan' => 1760,
        ];
    }

    /**
     * Crée des cotes value bet simulées.
     */
    public static function createValueBet(array $overrides = []): array
    {
        return array_merge([
            'match_id' => 1,
            'bet_type' => 'home_win',
            'bookmaker_odds' => 2.10,
            'calculated_odds' => 1.85,
            'probability' => 54.0,
            'implied_probability' => 47.6,
            'edge' => 6.4,
            'kelly_fraction' => 0.118,
            'recommended_stake' => 2.36,
            'expected_value' => 0.134,
            'confidence' => 'high',
        ], $overrides);
    }
}