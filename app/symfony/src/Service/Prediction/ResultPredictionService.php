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

        // Utiliser les cotes du bookmaker si disponibles pour ajuster les expected goals
        $oddsPrediction = $this->calculateProbabilitiesFromOdds($match);
        $hasOdds = null !== $oddsPrediction;

        // Ajuster les expected goals en fonction des cotes si disponibles
        $homeExpectedGoals = $homeStats['avg_goals_scored'];
        $awayExpectedGoals = $awayStats['avg_goals_scored'];

        if ($hasOdds && !$homeStats['has_historical_data']) {
            // Utiliser les cotes pour estimer les expected goals
            $homeWinProb = $oddsPrediction['1'] / 100;
            $awayWinProb = $oddsPrediction['2'] / 100;

            // Estimation: une équipe favorite marque plus
            $homeExpectedGoals = 1.0 + ($homeWinProb - $awayWinProb) * 1.5;
            $awayExpectedGoals = 1.0 + ($awayWinProb - $homeWinProb) * 1.5;

            $homeExpectedGoals = max(0.5, min(3.0, $homeExpectedGoals));
            $awayExpectedGoals = max(0.5, min(3.0, $awayExpectedGoals));
        }

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

        // Construire les prédictions à agréger
        $predictionsToAggregate = [
            'poisson' => $poissonPrediction,
            'elo' => $eloPrediction,
            'xg' => $xgPrediction,
            'monte_carlo' => $monteCarloPrediction,
        ];

        // Ajouter les cotes du bookmaker avec un poids important si disponibles
        if ($hasOdds) {
            $predictionsToAggregate['bookmaker'] = $oddsPrediction;
        }

        // Agréger les résultats
        $aggregatedPrediction = $this->aggregatePredictions($predictionsToAggregate);

        // Calculer la confiance
        $algorithmScores = [
            'poisson' => max($poissonPrediction['1'], $poissonPrediction['X'], $poissonPrediction['2']),
            'elo' => max($eloPrediction['1'], $eloPrediction['X'], $eloPrediction['2']),
            'xg' => max($xgPrediction['1'], $xgPrediction['X'], $xgPrediction['2']),
            'monte_carlo' => max($monteCarloPrediction['1'], $monteCarloPrediction['X'], $monteCarloPrediction['2']),
        ];

        if ($hasOdds) {
            $algorithmScores['bookmaker'] = max($oddsPrediction['1'], $oddsPrediction['X'], $oddsPrediction['2']);
        }

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
                'bookmaker' => $oddsPrediction,
            ],
            'expected_goals' => [
                'home' => round($homeExpectedGoals, 2),
                'away' => round($awayExpectedGoals, 2),
            ],
            'has_historical_data' => $homeStats['has_historical_data'] && $awayStats['has_historical_data'],
        ];
    }

    /**
     * Calcule les probabilités à partir des cotes du bookmaker.
     */
    private function calculateProbabilitiesFromOdds(FootballMatch $match): ?array
    {
        $odds = $match->getOdds();

        if ($odds->isEmpty()) {
            return null;
        }

        $homeOdds = null;
        $drawOdds = null;
        $awayOdds = null;

        foreach ($odds as $odd) {
            if ('1X2' === $odd->getBetType() || 'ML' === $odd->getBetType()) {
                $market = $odd->getMarket();
                if ('home' === $market || '1' === $market) {
                    $homeOdds = $odd->getOdds();
                } elseif ('draw' === $market || 'X' === $market) {
                    $drawOdds = $odd->getOdds();
                } elseif ('away' === $market || '2' === $market) {
                    $awayOdds = $odd->getOdds();
                }
            }
        }

        // Si on n'a pas toutes les cotes, retourner null
        if (null === $homeOdds || null === $awayOdds) {
            return null;
        }

        // Convertir les cotes en probabilités implicites
        $homeProb = 1 / $homeOdds;
        $drawProb = null !== $drawOdds ? (1 / $drawOdds) : 0.25; // 25% par défaut pour le nul
        $awayProb = 1 / $awayOdds;

        // Normaliser (enlever la marge du bookmaker)
        $total = $homeProb + $drawProb + $awayProb;

        return [
            '1' => round(($homeProb / $total) * 100, 2),
            'X' => round(($drawProb / $total) * 100, 2),
            '2' => round(($awayProb / $total) * 100, 2),
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
        // Poids par défaut sans cotes bookmaker
        $weights = [
            'poisson' => 0.25,
            'elo' => 0.20,
            'xg' => 0.30,
            'monte_carlo' => 0.25,
        ];

        // Si on a les cotes du bookmaker, les utiliser avec un poids important
        // car elles représentent l'analyse du marché
        if (isset($predictions['bookmaker'])) {
            $weights = [
                'poisson' => 0.15,
                'elo' => 0.10,
                'xg' => 0.15,
                'monte_carlo' => 0.15,
                'bookmaker' => 0.45, // Les cotes sont très fiables
            ];
        }

        $aggregated = [
            '1' => 0.0,
            'X' => 0.0,
            '2' => 0.0,
        ];

        foreach ($predictions as $algorithm => $prediction) {
            if (null === $prediction) {
                continue;
            }

            $weight = $weights[$algorithm] ?? 0.0;

            $aggregated['1'] += $prediction['1'] * $weight;
            $aggregated['X'] += $prediction['X'] * $weight;
            $aggregated['2'] += $prediction['2'] * $weight;
        }

        // Normaliser pour que la somme soit 100
        $total = $aggregated['1'] + $aggregated['X'] + $aggregated['2'];

        if (0.0 === $total) {
            // Fallback si aucune donnée
            return ['1' => 33.33, 'X' => 33.34, '2' => 33.33];
        }

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
