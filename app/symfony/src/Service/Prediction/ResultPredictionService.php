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

        // Détecter le type de compétition pour ajuster les prédictions
        $competitionType = $this->detectCompetitionType($match);
        $competitionMultiplier = $this->getCompetitionConfidenceMultiplier($competitionType);

        // Utiliser les cotes du bookmaker si disponibles pour ajuster les expected goals
        $oddsPrediction = $this->calculateProbabilitiesFromOdds($match);
        $hasOdds = null !== $oddsPrediction;

        // Ajuster les expected goals en fonction des cotes si disponibles
        $homeExpectedGoals = $homeStats['avg_goals_scored'];
        $awayExpectedGoals = $awayStats['avg_goals_scored'];

        // Déterminer si on a des données fiables
        $hasHistoricalData = $homeStats['has_historical_data'] && $awayStats['has_historical_data'];
        $matchesAnalyzed = min($homeStats['matches_played'], $awayStats['matches_played']);

        if ($hasOdds && !$hasHistoricalData) {
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

        // 2. Prédiction Elo (avec ajustement pour matchs amicaux)
        $eloPrediction = $this->eloService->calculateResultProbabilities($homeTeam, $awayTeam);
        if ('friendly' === $competitionType) {
            // Réduire l'avantage domicile pour les matchs amicaux
            $eloPrediction = $this->reduceHomeAdvantage($eloPrediction);
        }

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

        // Agréger les résultats avec ajustement pour matchs serrés
        $aggregatedPrediction = $this->aggregatePredictions($predictionsToAggregate, $hasHistoricalData);

        // Ajuster pour les matchs très serrés (augmenter probabilité de nul)
        $aggregatedPrediction = $this->adjustForCloseMatches($aggregatedPrediction, $hasHistoricalData);

        // Calculer la confiance RÉELLE (pas la probabilité!)
        $algorithmScores = [
            'poisson' => max($poissonPrediction['1'], $poissonPrediction['X'], $poissonPrediction['2']),
            'elo' => max($eloPrediction['1'], $eloPrediction['X'], $eloPrediction['2']),
            'xg' => max($xgPrediction['1'], $xgPrediction['X'], $xgPrediction['2']),
            'monte_carlo' => max($monteCarloPrediction['1'], $monteCarloPrediction['X'], $monteCarloPrediction['2']),
        ];

        if ($hasOdds) {
            $algorithmScores['bookmaker'] = max($oddsPrediction['1'], $oddsPrediction['X'], $oddsPrediction['2']);
        }

        // Nouveau calcul de confiance avec tous les facteurs
        $confidence = $this->confidenceCalculator->calculateOverallConfidence(
            $algorithmScores,
            $hasHistoricalData,
            $matchesAnalyzed,
            $predictionsToAggregate
        );

        // Appliquer le multiplicateur de compétition
        $confidence = round($confidence * $competitionMultiplier, 2);
        $confidence = min(95.0, max(10.0, $confidence));

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
            'has_historical_data' => $hasHistoricalData,
            'matches_analyzed' => $matchesAnalyzed,
            'competition_type' => $competitionType,
            'data_quality' => $this->calculateDataQuality($hasHistoricalData, $matchesAnalyzed, $hasOdds),
        ];
    }

    /**
     * Détecte le type de compétition à partir du match.
     */
    private function detectCompetitionType(FootballMatch $match): string
    {
        $league = strtolower($match->getLeague() ?? '');

        // Matchs amicaux
        if (str_contains($league, 'friendly') || str_contains($league, 'friendlies') || str_contains($league, 'amical')) {
            return 'friendly';
        }

        // Matchs de jeunes
        if (str_contains($league, 'u20') || str_contains($league, 'u21') || str_contains($league, 'u19')
            || str_contains($league, 'youth') || str_contains($league, 'junior')) {
            return 'youth';
        }

        // Coupes (plus imprévisibles)
        if (str_contains($league, 'cup') || str_contains($league, 'copa') || str_contains($league, 'coupe')) {
            return 'cup';
        }

        return 'league';
    }

    /**
     * Retourne un multiplicateur de confiance selon le type de compétition.
     */
    private function getCompetitionConfidenceMultiplier(string $competitionType): float
    {
        return match ($competitionType) {
            'friendly' => 0.70,  // -30% confiance pour les amicaux
            'youth' => 0.75,     // -25% confiance pour les matchs jeunes
            'cup' => 0.90,       // -10% confiance pour les coupes
            default => 1.0,      // Ligues normales
        };
    }

    /**
     * Réduit l'avantage domicile pour les matchs amicaux.
     */
    private function reduceHomeAdvantage(array $prediction): array
    {
        // Transférer 5% du domicile vers le nul et l'extérieur
        $homeReduction = min(5.0, $prediction['1'] * 0.15);

        return [
            '1' => round($prediction['1'] - $homeReduction, 2),
            'X' => round($prediction['X'] + ($homeReduction * 0.6), 2),
            '2' => round($prediction['2'] + ($homeReduction * 0.4), 2),
        ];
    }

    /**
     * Ajuste les probabilités pour les matchs très serrés.
     */
    private function adjustForCloseMatches(array $probabilities, bool $hasHistoricalData): array
    {
        $values = array_values($probabilities);
        sort($values);
        $gap = $values[2] - $values[1]; // Écart entre le 1er et 2e

        // Si l'écart est faible (< 10%), le match est serré
        if ($gap < 10) {
            // Augmenter la probabilité du nul
            $drawBoost = (10 - $gap) * 0.3; // Max +3%

            // Sans données historiques, on est encore plus incertain
            if (!$hasHistoricalData) {
                $drawBoost += 2.0;
            }

            $probabilities['X'] += $drawBoost;

            // Réduire proportionnellement les autres
            $reduction = $drawBoost / 2;
            $probabilities['1'] -= $reduction;
            $probabilities['2'] -= $reduction;

            // Normaliser à 100%
            $total = $probabilities['1'] + $probabilities['X'] + $probabilities['2'];

            return [
                '1' => round(($probabilities['1'] / $total) * 100, 2),
                'X' => round(($probabilities['X'] / $total) * 100, 2),
                '2' => round(($probabilities['2'] / $total) * 100, 2),
            ];
        }

        return $probabilities;
    }

    /**
     * Calcule un indicateur de qualité des données.
     */
    private function calculateDataQuality(bool $hasHistoricalData, int $matchesAnalyzed, bool $hasOdds): string
    {
        if ($hasHistoricalData && $matchesAnalyzed >= 10 && $hasOdds) {
            return 'excellent';
        }
        if ($hasHistoricalData && $matchesAnalyzed >= 5) {
            return 'good';
        }
        if ($hasHistoricalData || $hasOdds) {
            return 'fair';
        }

        return 'poor';
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
    private function aggregatePredictions(array $predictions, bool $hasHistoricalData = true): array
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
            // Sans données historiques, les cotes du bookmaker sont encore plus importantes
            $bookmakerWeight = $hasHistoricalData ? 0.45 : 0.60;
            $othersWeight = (1 - $bookmakerWeight) / 4;

            $weights = [
                'poisson' => $othersWeight,
                'elo' => $othersWeight,
                'xg' => $othersWeight,
                'monte_carlo' => $othersWeight,
                'bookmaker' => $bookmakerWeight,
            ];
        } elseif (!$hasHistoricalData) {
            // Sans données historiques ni cotes, on est très incertain
            // Donner plus de poids au nul car on ne sait pas
            // (sera ajusté dans adjustForCloseMatches)
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
            // Fallback si aucune donnée: distribution plus équilibrée
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
