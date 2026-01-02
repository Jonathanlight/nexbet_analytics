<?php

declare(strict_types=1);

namespace App\Service\Prediction;

use App\Entity\FootballMatch;
use App\Repository\FootballMatchRepository;
use App\Service\Math\MonteCarloSimulator;
use App\Service\Math\PoissonService;

/**
 * Service de prédiction du nombre de buts (Over/Under, BTTS, Exact Score).
 */
class GoalsPredictionService
{
    public function __construct(
        private readonly PoissonService $poissonService,
        private readonly MonteCarloSimulator $monteCarloSimulator,
        private readonly ConfidenceCalculator $confidenceCalculator,
        private readonly FootballMatchRepository $matchRepository,
    ) {
    }

    /**
     * Prédit Over/Under pour différentes lignes.
     */
    public function predictOverUnder(FootballMatch $match): array
    {
        $homeTeam = $match->getHomeTeam();
        $awayTeam = $match->getAwayTeam();

        $homeStats = $this->matchRepository->getTeamAverageStats($homeTeam, 10);
        $awayStats = $this->matchRepository->getTeamAverageStats($awayTeam, 10);

        $homeExpectedGoals = $homeStats['avg_goals_scored'];
        $awayExpectedGoals = $awayStats['avg_goals_scored'];

        $lines = [0.5, 1.5, 2.5, 3.5, 4.5, 5.5];
        $predictions = [];

        foreach ($lines as $line) {
            $poissonResult = $this->poissonService->calculateOverUnder(
                $homeExpectedGoals,
                $awayExpectedGoals,
                $line
            );

            $predictions["OU{$line}"] = [
                'over' => $poissonResult['over'],
                'under' => $poissonResult['under'],
                'confidence' => $this->calculateLineConfidence($line, $homeExpectedGoals + $awayExpectedGoals),
            ];
        }

        return $predictions;
    }

    /**
     * Prédit BTTS (Both Teams To Score).
     */
    public function predictBTTS(FootballMatch $match): array
    {
        $homeTeam = $match->getHomeTeam();
        $awayTeam = $match->getAwayTeam();

        $homeStats = $this->matchRepository->getTeamAverageStats($homeTeam, 10);
        $awayStats = $this->matchRepository->getTeamAverageStats($awayTeam, 10);

        $homeExpectedGoals = $homeStats['avg_goals_scored'];
        $awayExpectedGoals = $awayStats['avg_goals_scored'];

        $poissonBTTS = $this->poissonService->calculateBTTS($homeExpectedGoals, $awayExpectedGoals);

        // Facteurs additionnels
        $homeCleanSheetRate = ($homeStats['clean_sheets'] / max($homeStats['matches_played'] ?? 10, 1)) * 100;
        $awayCleanSheetRate = ($awayStats['clean_sheets'] / max($awayStats['matches_played'] ?? 10, 1)) * 100;

        $bttsHistorical = min($homeStats['btts_percentage'] ?? 50, $awayStats['btts_percentage'] ?? 50);

        // Moyenne pondérée
        $bttsYes = ($poissonBTTS['yes'] * 0.6) + ($bttsHistorical * 0.4);
        $bttsNo = 100 - $bttsYes;

        $confidence = $this->confidenceCalculator->calculateBetTypeConfidence('BTTS', 75.0);

        return [
            'yes' => round($bttsYes, 2),
            'no' => round($bttsNo, 2),
            'confidence' => $confidence,
            'factors' => [
                'poisson' => $poissonBTTS,
                'historical' => $bttsHistorical,
                'home_clean_sheet_rate' => round($homeCleanSheetRate, 2),
                'away_clean_sheet_rate' => round($awayCleanSheetRate, 2),
            ],
        ];
    }

    /**
     * Prédit le score exact le plus probable.
     */
    public function predictExactScore(FootballMatch $match, int $topN = 10): array
    {
        $homeTeam = $match->getHomeTeam();
        $awayTeam = $match->getAwayTeam();

        $homeStats = $this->matchRepository->getTeamAverageStats($homeTeam, 10);
        $awayStats = $this->matchRepository->getTeamAverageStats($awayTeam, 10);

        $homeExpectedGoals = $homeStats['avg_goals_scored'];
        $awayExpectedGoals = $awayStats['avg_goals_scored'];

        $scoreDistribution = $this->poissonService->calculateScoreDistribution(
            $homeExpectedGoals,
            $awayExpectedGoals
        );

        // Prendre les N scores les plus probables
        $topScores = array_slice($scoreDistribution, 0, $topN, true);

        return [
            'most_likely' => array_key_first($topScores),
            'probability' => reset($topScores),
            'top_scores' => $topScores,
            'confidence' => $this->confidenceCalculator->calculateBetTypeConfidence('exact_score', 60.0),
        ];
    }

    /**
     * Prédit le nombre de buts par équipe.
     */
    public function predictTeamGoals(FootballMatch $match): array
    {
        $homeTeam = $match->getHomeTeam();
        $awayTeam = $match->getAwayTeam();

        $homeStats = $this->matchRepository->getTeamAverageStats($homeTeam, 10);
        $awayStats = $this->matchRepository->getTeamAverageStats($awayTeam, 10);

        return [
            'home' => [
                'expected' => round($homeStats['avg_goals_scored'], 2),
                'over_05' => $this->calculateTeamOverProbability($homeStats['avg_goals_scored'], 0.5),
                'over_15' => $this->calculateTeamOverProbability($homeStats['avg_goals_scored'], 1.5),
                'over_25' => $this->calculateTeamOverProbability($homeStats['avg_goals_scored'], 2.5),
            ],
            'away' => [
                'expected' => round($awayStats['avg_goals_scored'], 2),
                'over_05' => $this->calculateTeamOverProbability($awayStats['avg_goals_scored'], 0.5),
                'over_15' => $this->calculateTeamOverProbability($awayStats['avg_goals_scored'], 1.5),
                'over_25' => $this->calculateTeamOverProbability($awayStats['avg_goals_scored'], 2.5),
            ],
        ];
    }

    /**
     * Calcule la probabilité qu'une équipe dépasse une ligne.
     */
    private function calculateTeamOverProbability(float $expectedGoals, float $line): float
    {
        $probability = 0.0;

        for ($goals = ceil($line + 0.1); $goals <= 10; ++$goals) {
            $probability += $this->poissonService->probability($expectedGoals, (int) $goals);
        }

        return round($probability * 100, 2);
    }

    /**
     * Calcule la confiance selon la ligne.
     */
    private function calculateLineConfidence(float $line, float $totalExpected): float
    {
        $diff = abs($totalExpected - $line);

        if ($diff < 0.3) {
            return 90.0;
        }
        if ($diff < 0.5) {
            return 85.0;
        }
        if ($diff < 0.8) {
            return 80.0;
        }
        if ($diff < 1.2) {
            return 75.0;
        }

        return 70.0;
    }
}
