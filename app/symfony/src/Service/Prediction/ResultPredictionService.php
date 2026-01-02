<?php

declare(strict_types=1);

namespace App\Service\Prediction;

use App\Entity\FootballMatch;
use App\Repository\FootballMatchRepository;
use App\Service\Math\EloService;
use App\Service\Math\MonteCarloSimulator;
use App\Service\Math\PoissonService;
use App\Service\Math\XGService;

/**
 * Service de prédiction du résultat d'un match (1X2).
 */
class ResultPredictionService
{
    public function __construct(
        private readonly PoissonService $poissonService,
        private readonly EloService $eloService,
        private readonly XGService $xgService,
        private readonly MonteCarloSimulator $monteCarloSimulator,
        private readonly ConfidenceCalculator $confidenceCalculator,
        private readonly FootballMatchRepository $matchRepository,
    ) {
    }

    /**
     * Prédit le résultat d'un match avec tous les algorithmes.
     */
    public function predictResult(FootballMatch $match): array
    {
        $homeTeam = $match->getHomeTeam();
        $awayTeam = $match->getAwayTeam();

        // Récupérer les statistiques récentes
        $homeStats = $this->matchRepository->getTeamAverageStats($homeTeam, 10);
        $awayStats = $this->matchRepository->getTeamAverageStats($awayTeam, 10);

        $homeExpectedGoals = $homeStats['avg_goals_scored'];
        $awayExpectedGoals = $awayStats['avg_goals_scored'];

        // 1. Prédiction Poisson
        $poissonPrediction = $this->poissonService->calculateResultProbabilities(
            $homeExpectedGoals,
            $awayExpectedGoals
        );

        // 2. Prédiction Elo
        $eloPrediction = $this->eloService->calculateResultProbabilities($homeTeam, $awayTeam);

        // 3. Prédiction xG
        $homeXG = $homeStats['avg_xg'] ?? $homeExpectedGoals;
        $awayXG = $awayStats['avg_xg'] ?? $awayExpectedGoals;

        $xgPrediction = $this->poissonService->calculateResultProbabilities($homeXG, $awayXG);

        // 4. Simulation Monte Carlo
        $monteCarloResults = $this->monteCarloSimulator->simulateFootballMatch(
            $homeExpectedGoals,
            $awayExpectedGoals,
            10000
        );
        $monteCarloPrediction = $monteCarloResults['result'];

        // Agréger les résultats
        $aggregatedPrediction = $this->aggregatePredictions([
            'poisson' => $poissonPrediction,
            'elo' => $eloPrediction,
            'xg' => $xgPrediction,
            'monte_carlo' => $monteCarloPrediction,
        ]);

        // Calculer la confiance
        $algorithmScores = [
            'poisson' => max($poissonPrediction['1'], $poissonPrediction['X'], $poissonPrediction['2']),
            'elo' => max($eloPrediction['1'], $eloPrediction['X'], $eloPrediction['2']),
            'xg' => max($xgPrediction['1'], $xgPrediction['X'], $xgPrediction['2']),
            'monte_carlo' => max($monteCarloPrediction['1'], $monteCarloPrediction['X'], $monteCarloPrediction['2']),
        ];

        $confidence = $this->confidenceCalculator->calculateOverallConfidence($algorithmScores);

        // Déterminer le résultat le plus probable
        $mostProbable = $this->determineMostProbableOutcome($aggregatedPrediction);

        return [
            'prediction' => $mostProbable['outcome'],
            'probabilities' => $aggregatedPrediction,
            'confidence' => $confidence,
            'algorithm_scores' => $algorithmScores,
            'details' => [
                'poisson' => $poissonPrediction,
                'elo' => $eloPrediction,
                'xg' => $xgPrediction,
                'monte_carlo' => $monteCarloPrediction,
            ],
            'expected_goals' => [
                'home' => round($homeExpectedGoals, 2),
                'away' => round($awayExpectedGoals, 2),
            ],
        ];
    }

    /**
     * Prédit la double chance.
     */
    public function predictDoubleChance(FootballMatch $match): array
    {
        $resultPrediction = $this->predictResult($match);
        $probabilities = $resultPrediction['probabilities'];

        return [
            '1X' => round($probabilities['1'] + $probabilities['X'], 2),
            'X2' => round($probabilities['X'] + $probabilities['2'], 2),
            '12' => round($probabilities['1'] + $probabilities['2'], 2),
        ];
    }

    /**
     * Agrège les prédictions de plusieurs algorithmes.
     */
    private function aggregatePredictions(array $predictions): array
    {
        $weights = [
            'poisson' => 0.25,
            'elo' => 0.20,
            'xg' => 0.30,
            'monte_carlo' => 0.25,
        ];

        $aggregated = [
            '1' => 0.0,
            'X' => 0.0,
            '2' => 0.0,
        ];

        foreach ($predictions as $algorithm => $prediction) {
            $weight = $weights[$algorithm] ?? 0.0;

            $aggregated['1'] += $prediction['1'] * $weight;
            $aggregated['X'] += $prediction['X'] * $weight;
            $aggregated['2'] += $prediction['2'] * $weight;
        }

        // Normaliser pour que la somme soit 100
        $total = $aggregated['1'] + $aggregated['X'] + $aggregated['2'];

        return [
            '1' => round(($aggregated['1'] / $total) * 100, 2),
            'X' => round(($aggregated['X'] / $total) * 100, 2),
            '2' => round(($aggregated['2'] / $total) * 100, 2),
        ];
    }

    /**
     * Détermine le résultat le plus probable.
     */
    private function determineMostProbableOutcome(array $probabilities): array
    {
        $max = max($probabilities);
        $outcome = array_search($max, $probabilities);

        return [
            'outcome' => $outcome,
            'probability' => $max,
        ];
    }

    /**
     * Analyse les confrontations directes.
     */
    public function analyzeHeadToHead(FootballMatch $match): array
    {
        $homeTeam = $match->getHomeTeam();
        $awayTeam = $match->getAwayTeam();

        $h2hMatches = $this->matchRepository->findHeadToHead($homeTeam, $awayTeam, 10);

        $stats = [
            'home_wins' => 0,
            'draws' => 0,
            'away_wins' => 0,
            'total_matches' => count($h2hMatches),
            'avg_home_goals' => 0,
            'avg_away_goals' => 0,
        ];

        if (empty($h2hMatches)) {
            return $stats;
        }

        $totalHomeGoals = 0;
        $totalAwayGoals = 0;

        foreach ($h2hMatches as $h2hMatch) {
            $result = $h2hMatch->getResult();

            if ('1' === $result) {
                if ($h2hMatch->getHomeTeam()->getId() === $homeTeam->getId()) {
                    ++$stats['home_wins'];
                } else {
                    ++$stats['away_wins'];
                }
            } elseif ('X' === $result) {
                ++$stats['draws'];
            } else {
                if ($h2hMatch->getAwayTeam()->getId() === $awayTeam->getId()) {
                    ++$stats['away_wins'];
                } else {
                    ++$stats['home_wins'];
                }
            }

            // Calculer la moyenne de buts
            if ($h2hMatch->getHomeTeam()->getId() === $homeTeam->getId()) {
                $totalHomeGoals += $h2hMatch->getHomeScore() ?? 0;
                $totalAwayGoals += $h2hMatch->getAwayScore() ?? 0;
            } else {
                $totalHomeGoals += $h2hMatch->getAwayScore() ?? 0;
                $totalAwayGoals += $h2hMatch->getHomeScore() ?? 0;
            }
        }

        $stats['avg_home_goals'] = round($totalHomeGoals / count($h2hMatches), 2);
        $stats['avg_away_goals'] = round($totalAwayGoals / count($h2hMatches), 2);

        return $stats;
    }
}
