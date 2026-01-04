<?php

declare(strict_types=1);

namespace App\Service\Prediction;

use App\Entity\FootballMatch;
use App\Repository\FootballMatchRepository;
use App\Service\Math\AdvancedEloService;
use App\Service\Math\AdvancedKellyService;
use App\Service\Math\AdvancedMonteCarloService;
use App\Service\Math\DixonColesService;
use App\Service\Math\FormAnalysisService;
use App\Service\Math\HeadToHeadService;
use App\Service\Math\PoissonService;

/**
 * Service de prédictions avancées combinant plusieurs modèles mathématiques:
 * - Dixon-Coles (amélioration du Poisson)
 * - Elo avec décroissance temporelle et marge de victoire
 * - Analyse de forme pondérée
 * - Confrontations directes (H2H)
 * - Monte Carlo avec corrélation
 * - Kelly Criterion pour le value betting
 */
final class AdvancedFootballPredictionService
{
    // Poids des différents modèles dans la prédiction finale
    private const MODEL_WEIGHTS = [
        'dixon_coles' => 0.30,
        'elo' => 0.20,
        'form' => 0.20,
        'h2h' => 0.15,
        'monte_carlo' => 0.15,
    ];

    public function __construct(
        private readonly FootballMatchRepository $matchRepository,
        private readonly DixonColesService $dixonColes,
        private readonly AdvancedEloService $eloService,
        private readonly FormAnalysisService $formService,
        private readonly HeadToHeadService $h2hService,
        private readonly AdvancedMonteCarloService $monteCarloService,
        private readonly AdvancedKellyService $kellyService,
        private readonly PoissonService $poissonService,
    ) {
    }

    /**
     * Génère une prédiction complète pour un match.
     */
    public function predictMatch(FootballMatch $match): array
    {
        // Récupérer les données historiques
        $homeTeam = $match->getHomeTeam();
        $awayTeam = $match->getAwayTeam();

        $homeMatches = $this->getTeamRecentMatches($homeTeam->getName(), 20);
        $awayMatches = $this->getTeamRecentMatches($awayTeam->getName(), 20);
        $h2hMatches = $this->getH2HMatches($homeTeam->getName(), $awayTeam->getName());

        // Calculer les Expected Goals
        $xgData = $this->calculateExpectedGoals($homeMatches, $awayMatches, $h2hMatches);

        // Exécuter chaque modèle
        $dixonColesResult = $this->runDixonColesModel($xgData);
        $eloResult = $this->runEloModel($homeMatches, $awayMatches);
        $formResult = $this->runFormModel($homeMatches, $awayMatches);
        $h2hResult = $this->runH2HModel($h2hMatches, $homeTeam->getName(), $awayTeam->getName());
        $monteCarloResult = $this->runMonteCarloModel($xgData, $formResult, $h2hResult);

        // Combiner les prédictions
        $combinedPrediction = $this->combineModels([
            'dixon_coles' => $dixonColesResult,
            'elo' => $eloResult,
            'form' => $formResult,
            'h2h' => $h2hResult,
            'monte_carlo' => $monteCarloResult,
        ]);

        // Calculs supplémentaires
        $overUnder = $this->calculateOverUnder($xgData, $monteCarloResult);
        $btts = $this->calculateBTTS($xgData, $monteCarloResult);
        $exactScores = $this->calculateExactScores($xgData);
        $confidence = $this->calculateConfidence($combinedPrediction, $formResult, $h2hResult);

        return [
            'match' => [
                'id' => $match->getId(),
                'home_team' => $homeTeam->getName(),
                'away_team' => $awayTeam->getName(),
                'date' => $match->getMatchDate()->format('Y-m-d H:i'),
                'league' => $match->getLeague(),
            ],
            'expected_goals' => $xgData,
            'result_prediction' => [
                'probabilities' => $combinedPrediction['probabilities'],
                'prediction' => $combinedPrediction['prediction'],
                'confidence' => $confidence['overall'],
                'model_agreement' => $combinedPrediction['agreement'],
            ],
            'over_under' => $overUnder,
            'btts' => $btts,
            'exact_scores' => $exactScores,
            'double_chance' => $this->calculateDoubleChance($combinedPrediction['probabilities']),
            'handicaps' => $this->calculateHandicaps($xgData, $monteCarloResult),
            'model_outputs' => [
                'dixon_coles' => $dixonColesResult,
                'elo' => $eloResult,
                'form' => $formResult,
                'h2h' => $h2hResult,
                'monte_carlo' => $monteCarloResult,
            ],
            'confidence_details' => $confidence,
            'data_quality' => $this->assessDataQuality($homeMatches, $awayMatches, $h2hMatches),
        ];
    }

    /**
     * Calcule les value bets pour un match.
     */
    public function calculateValueBets(FootballMatch $match, array $bookmakerOdds, float $bankroll = 1000): array
    {
        $prediction = $this->predictMatch($match);
        $valueBets = [];

        // Analyser chaque marché
        $markets = [
            '1' => $prediction['result_prediction']['probabilities']['1'],
            'X' => $prediction['result_prediction']['probabilities']['X'],
            '2' => $prediction['result_prediction']['probabilities']['2'],
            'over_2.5' => $prediction['over_under']['over_25'],
            'under_2.5' => $prediction['over_under']['under_25'],
            'btts_yes' => $prediction['btts']['yes'],
            'btts_no' => $prediction['btts']['no'],
        ];

        foreach ($markets as $market => $probability) {
            if (!isset($bookmakerOdds[$market])) {
                continue;
            }

            $odds = $bookmakerOdds[$market];
            $kellyAnalysis = $this->kellyService->analyzeKelly($probability, $odds, $bankroll);

            if ($kellyAnalysis['is_value_bet']) {
                $valueBets[] = [
                    'market' => $market,
                    'probability' => $probability,
                    'odds' => $odds,
                    'fair_odds' => $kellyAnalysis['fair_odds'],
                    'value_percentage' => $kellyAnalysis['value_percentage'],
                    'edge' => $kellyAnalysis['edge'],
                    'kelly_fraction' => $kellyAnalysis['kelly_fraction'],
                    'recommended_stake' => $kellyAnalysis['stakes']['half_kelly'],
                    'expected_profit' => round($kellyAnalysis['stakes']['half_kelly'] * ($odds - 1) * ($probability / 100), 2),
                    'risk_assessment' => $kellyAnalysis['risk_assessment'],
                    'recommendation' => $kellyAnalysis['recommendation'],
                ];
            }
        }

        // Trier par value
        usort($valueBets, fn ($a, $b) => $b['value_percentage'] <=> $a['value_percentage']);

        return [
            'match' => $prediction['match'],
            'value_bets' => $valueBets,
            'best_value' => $valueBets[0] ?? null,
            'total_value_bets' => count($valueBets),
            'portfolio_optimization' => count($valueBets) > 1
                ? $this->kellyService->optimizePortfolio(
                    array_map(fn ($vb) => ['probability' => $vb['probability'], 'odds' => $vb['odds']], $valueBets),
                    $bankroll
                )
                : null,
        ];
    }

    /**
     * Génère les prédictions pour plusieurs matchs avec classement.
     */
    public function predictMultipleMatches(array $matches, string $sortBy = 'confidence'): array
    {
        $predictions = [];

        foreach ($matches as $match) {
            try {
                $prediction = $this->predictMatch($match);
                $prediction['safe_score'] = $this->calculateSafeScore($prediction);
                $predictions[] = $prediction;
            } catch (\Exception $e) {
                continue;
            }
        }

        // Trier selon le critère demandé
        usort($predictions, function ($a, $b) use ($sortBy) {
            return match ($sortBy) {
                'confidence' => $b['result_prediction']['confidence'] <=> $a['result_prediction']['confidence'],
                'safe_score' => $b['safe_score'] <=> $a['safe_score'],
                'value' => $b['result_prediction']['probabilities'][array_key_first($b['result_prediction']['probabilities'])]
                    <=> $a['result_prediction']['probabilities'][array_key_first($a['result_prediction']['probabilities'])],
                default => 0,
            };
        });

        return [
            'predictions' => $predictions,
            'summary' => $this->generateSummary($predictions),
            'top_picks' => array_slice($predictions, 0, 5),
        ];
    }

    private function calculateExpectedGoals(array $homeMatches, array $awayMatches, array $h2hMatches): array
    {
        // Moyennes de buts pondérées
        $homeAvgScored = $this->calculateWeightedAverage($homeMatches, 'goals_scored');
        $homeAvgConceded = $this->calculateWeightedAverage($homeMatches, 'goals_conceded');
        $awayAvgScored = $this->calculateWeightedAverage($awayMatches, 'goals_scored');
        $awayAvgConceded = $this->calculateWeightedAverage($awayMatches, 'goals_conceded');

        // Ajuster pour domicile/extérieur
        $homeAttack = $homeAvgScored * 1.1; // Bonus domicile
        $homeDefense = $homeAvgConceded * 0.95;
        $awayAttack = $awayAvgScored * 0.9;
        $awayDefense = $awayAvgConceded * 1.05;

        // xG finaux
        $homeXg = $this->dixonColes->calculateAdjustedExpectedGoals(
            $homeAttack,
            $homeDefense,
            $awayAttack,
            $awayDefense
        );

        // Ajustement H2H si disponible
        if (!empty($h2hMatches)) {
            $h2hAvgGoals = array_sum(array_map(
                fn ($m) => ($m['home_score'] ?? 0) + ($m['away_score'] ?? 0),
                $h2hMatches
            )) / count($h2hMatches);

            $h2hFactor = $h2hAvgGoals / 2.5;
            $homeXg['home'] *= (0.8 + 0.2 * $h2hFactor);
            $homeXg['away'] *= (0.8 + 0.2 * $h2hFactor);
            $homeXg['total'] = $homeXg['home'] + $homeXg['away'];
        }

        return [
            'home' => round($homeXg['home'], 3),
            'away' => round($homeXg['away'], 3),
            'total' => round($homeXg['total'], 3),
            'components' => [
                'home_attack' => round($homeAttack, 3),
                'home_defense' => round($homeDefense, 3),
                'away_attack' => round($awayAttack, 3),
                'away_defense' => round($awayDefense, 3),
            ],
        ];
    }

    private function runDixonColesModel(array $xgData): array
    {
        $probs = $this->dixonColes->calculateResultProbabilities($xgData['home'], $xgData['away']);
        $intervals = $this->dixonColes->calculateConfidenceIntervals($xgData['home'], $xgData['away']);

        return [
            'probabilities' => $probs,
            'confidence_intervals' => $intervals,
            'grouped_scores' => $this->dixonColes->calculateGroupedScores($xgData['home'], $xgData['away']),
        ];
    }

    private function runEloModel(array $homeMatches, array $awayMatches): array
    {
        $homeMomentum = $this->eloService->calculateMomentum($homeMatches);
        $awayMomentum = $this->eloService->calculateMomentum($awayMatches);

        $homeRating = $this->eloService->calculateRatingWithDecay($homeMatches);
        $awayRating = $this->eloService->calculateRatingWithDecay($awayMatches);

        $strength = $this->eloService->calculateRelativeStrength(
            $homeRating,
            $awayRating,
            $homeMomentum,
            $awayMomentum
        );

        $marginPrediction = $this->eloService->predictMargin($homeRating, $awayRating);

        // Convertir en probabilités
        $probs = $this->eloService->calculateWinProbabilityWithDecay($homeMatches, $awayMatches);

        return [
            'probabilities' => [
                '1' => $probs['1'],
                'X' => $probs['X'],
                '2' => $probs['2'],
            ],
            'ratings' => [
                'home' => round($homeRating, 2),
                'away' => round($awayRating, 2),
            ],
            'momentum' => [
                'home' => $homeMomentum,
                'away' => $awayMomentum,
            ],
            'relative_strength' => $strength,
            'margin_prediction' => $marginPrediction,
        ];
    }

    private function runFormModel(array $homeMatches, array $awayMatches): array
    {
        $comparison = $this->formService->compareForm($homeMatches, $awayMatches);

        // Convertir l'avantage de forme en probabilités
        $formAdvantage = $comparison['form_advantage'];
        $homeBoost = ($formAdvantage - 50) * 0.3;

        // Probabilités de base ajustées
        $baseProbs = ['1' => 45, 'X' => 25, '2' => 30];
        $probs = [
            '1' => max(5, min(85, $baseProbs['1'] + $homeBoost)),
            'X' => max(5, min(40, $baseProbs['X'] - abs($homeBoost) * 0.2)),
            '2' => max(5, min(85, $baseProbs['2'] - $homeBoost)),
        ];

        // Normaliser
        $total = array_sum($probs);
        $probs = array_map(fn ($p) => round(($p / $total) * 100, 2), $probs);

        return [
            'probabilities' => $probs,
            'comparison' => $comparison,
            'home_form_rating' => $comparison['home_form']['form_rating'],
            'away_form_rating' => $comparison['away_form']['form_rating'],
            'trend_advantage' => $comparison['advantage_team'],
        ];
    }

    private function runH2HModel(array $h2hMatches, string $homeTeam, string $awayTeam): array
    {
        $analysis = $this->h2hService->analyzeHeadToHead($h2hMatches, $homeTeam, $awayTeam);

        if (!$analysis['significance']['is_significant']) {
            return [
                'probabilities' => ['1' => 40, 'X' => 28, '2' => 32],
                'available' => false,
                'analysis' => $analysis,
            ];
        }

        $stats = $analysis['basic_stats'];

        return [
            'probabilities' => [
                '1' => $stats['team1_win_rate'],
                'X' => $stats['draw_rate'],
                '2' => $stats['team2_win_rate'],
            ],
            'available' => true,
            'analysis' => $analysis,
            'adjustments' => $analysis['prediction_adjustments'],
        ];
    }

    private function runMonteCarloModel(array $xgData, array $formResult, array $h2hResult): array
    {
        $factors = [
            'home_form' => ($formResult['home_form_rating'] - 50) / 50,
            'away_form' => ($formResult['away_form_rating'] - 50) / 50,
        ];

        if ($h2hResult['available'] ?? false) {
            $factors['h2h_home_advantage'] = ($h2hResult['analysis']['psychological_edge']['edge_score'] ?? 0) / 10;
        }

        return $this->monteCarloService->simulateWithDynamicFactors(
            $xgData['home'],
            $xgData['away'],
            $factors,
            15000
        );
    }

    private function combineModels(array $modelResults): array
    {
        $combinedProbs = ['1' => 0, 'X' => 0, '2' => 0];

        foreach (self::MODEL_WEIGHTS as $model => $weight) {
            if (!isset($modelResults[$model]['probabilities'])) {
                continue;
            }

            $probs = $modelResults[$model]['probabilities'];
            // Gérer les différentes clés possibles
            $homeProb = $probs['1'] ?? $probs['home'] ?? 0;
            $drawProb = $probs['X'] ?? $probs['draw'] ?? 0;
            $awayProb = $probs['2'] ?? $probs['away'] ?? 0;

            $combinedProbs['1'] += $homeProb * $weight;
            $combinedProbs['X'] += $drawProb * $weight;
            $combinedProbs['2'] += $awayProb * $weight;
        }

        // Normaliser
        $total = array_sum($combinedProbs);
        if ($total > 0) {
            $combinedProbs = array_map(fn ($p) => round(($p / $total) * 100, 2), $combinedProbs);
        }

        // Déterminer la prédiction
        $maxProb = max($combinedProbs);
        $prediction = array_search($maxProb, $combinedProbs);

        // Calculer l'accord entre les modèles
        $predictions = [];
        foreach ($modelResults as $model => $result) {
            if (isset($result['probabilities'])) {
                $probs = $result['probabilities'];
                $homeProb = $probs['1'] ?? $probs['home'] ?? 0;
                $drawProb = $probs['X'] ?? $probs['draw'] ?? 0;
                $awayProb = $probs['2'] ?? $probs['away'] ?? 0;
                $maxP = max($homeProb, $drawProb, $awayProb);
                if ($maxP == $homeProb) {
                    $predictions[] = '1';
                } elseif ($maxP == $drawProb) {
                    $predictions[] = 'X';
                } else {
                    $predictions[] = '2';
                }
            }
        }

        $agreementCount = count(array_filter($predictions, fn ($p) => $p === $prediction));
        $agreement = round(($agreementCount / max(1, count($predictions))) * 100, 2);

        return [
            'probabilities' => $combinedProbs,
            'prediction' => $prediction,
            'agreement' => $agreement,
            'model_predictions' => $predictions,
        ];
    }

    private function calculateOverUnder(array $xgData, array $monteCarloResult): array
    {
        $ou = $monteCarloResult['over_under'] ?? [];

        return [
            'expected_total' => round($xgData['total'], 2),
            'over_15' => $ou['over_15'] ?? $this->poissonService->calculateOverUnder($xgData['home'], $xgData['away'], 1.5)['over'],
            'over_25' => $ou['over_25'] ?? $this->poissonService->calculateOverUnder($xgData['home'], $xgData['away'], 2.5)['over'],
            'over_35' => $ou['over_35'] ?? $this->poissonService->calculateOverUnder($xgData['home'], $xgData['away'], 3.5)['over'],
            'under_15' => 100 - ($ou['over_15'] ?? 0),
            'under_25' => 100 - ($ou['over_25'] ?? 0),
            'under_35' => 100 - ($ou['over_35'] ?? 0),
        ];
    }

    private function calculateBTTS(array $xgData, array $monteCarloResult): array
    {
        $btts = $monteCarloResult['btts'] ?? $this->poissonService->calculateBTTS($xgData['home'], $xgData['away']);

        return [
            'yes' => $btts['yes'],
            'no' => $btts['no'],
            'home_to_score' => round((1 - exp(-$xgData['home'])) * 100, 2),
            'away_to_score' => round((1 - exp(-$xgData['away'])) * 100, 2),
        ];
    }

    private function calculateExactScores(array $xgData): array
    {
        $distribution = $this->dixonColes->calculateScoreDistribution($xgData['home'], $xgData['away']);

        return [
            'distribution' => array_slice($distribution, 0, 10, true),
            'most_likely' => array_key_first($distribution),
            'top_5_confidence' => round(array_sum(array_slice($distribution, 0, 5)), 2),
        ];
    }

    private function calculateDoubleChance(array $probs): array
    {
        return [
            '1X' => round($probs['1'] + $probs['X'], 2),
            '12' => round($probs['1'] + $probs['2'], 2),
            'X2' => round($probs['X'] + $probs['2'], 2),
        ];
    }

    private function calculateHandicaps(array $xgData, array $monteCarloResult): array
    {
        $expectedMargin = $xgData['home'] - $xgData['away'];
        $stats = $monteCarloResult['statistics'] ?? [];

        return [
            'expected_margin' => round($expectedMargin, 2),
            'handicap_-1' => $expectedMargin > 1 ? 'home_covers' : 'away_covers',
            'handicap_+1' => $expectedMargin > -1 ? 'home_covers' : 'away_covers',
            'asian_handicap_analysis' => [
                'home_0' => round(50 + ($expectedMargin * 15), 2),
                'home_-0.5' => round(45 + ($expectedMargin * 12), 2),
                'home_-1' => round(35 + ($expectedMargin * 10), 2),
            ],
        ];
    }

    private function calculateConfidence(array $combinedPrediction, array $formResult, array $h2hResult): array
    {
        $factors = [];

        // Probabilité maximale
        $maxProb = max($combinedPrediction['probabilities']);
        $probConfidence = min(30, ($maxProb - 33) * 1.5);
        $factors['probability_margin'] = round($probConfidence, 2);

        // Accord des modèles
        $agreementConfidence = $combinedPrediction['agreement'] * 0.25;
        $factors['model_agreement'] = round($agreementConfidence, 2);

        // Qualité de la forme
        $formConfidence = min(20, ($formResult['comparison']['confidence'] ?? 50) * 0.2);
        $factors['form_quality'] = round($formConfidence, 2);

        // Données H2H
        $h2hConfidence = ($h2hResult['available'] ?? false)
            ? min(15, ($h2hResult['analysis']['significance']['match_count'] ?? 0) * 2)
            : 5;
        $factors['h2h_data'] = round($h2hConfidence, 2);

        $overall = round(array_sum($factors), 2);

        return [
            'overall' => min(95, max(20, $overall)),
            'factors' => $factors,
            'level' => $overall > 70 ? 'high' : ($overall > 50 ? 'medium' : 'low'),
        ];
    }

    private function assessDataQuality(array $homeMatches, array $awayMatches, array $h2hMatches): array
    {
        return [
            'home_matches_count' => count($homeMatches),
            'away_matches_count' => count($awayMatches),
            'h2h_matches_count' => count($h2hMatches),
            'data_sufficient' => count($homeMatches) >= 5 && count($awayMatches) >= 5,
            'h2h_available' => count($h2hMatches) >= 3,
            'quality_score' => round(min(100, (count($homeMatches) + count($awayMatches)) * 2.5 + count($h2hMatches) * 5), 2),
        ];
    }

    private function calculateSafeScore(array $prediction): float
    {
        $score = 0;

        // Probabilité maximale
        $maxProb = max($prediction['result_prediction']['probabilities']);
        $score += min(40, $maxProb * 0.5);

        // Confiance
        $score += $prediction['result_prediction']['confidence'] * 0.3;

        // Accord des modèles
        $score += $prediction['result_prediction']['model_agreement'] * 0.15;

        // Qualité des données
        $score += $prediction['data_quality']['quality_score'] * 0.1;

        return round(min(100, $score), 2);
    }

    private function calculateWeightedAverage(array $matches, string $field): float
    {
        if (empty($matches)) {
            return 1.3; // Valeur par défaut
        }

        $totalWeight = 0;
        $weightedSum = 0;

        foreach ($matches as $i => $match) {
            $weight = pow(0.9, $i);
            $totalWeight += $weight;
            $weightedSum += ($match[$field] ?? 0) * $weight;
        }

        return $totalWeight > 0 ? $weightedSum / $totalWeight : 1.3;
    }

    private function getTeamRecentMatches(string $teamName, int $limit = 20): array
    {
        // Cette méthode devrait récupérer les matchs de la base de données
        // Pour l'instant, retourne un tableau vide
        return [];
    }

    private function getH2HMatches(string $team1, string $team2): array
    {
        // Cette méthode devrait récupérer les confrontations directes
        return [];
    }

    private function generateSummary(array $predictions): array
    {
        if (empty($predictions)) {
            return ['total' => 0];
        }

        $highConfidence = count(array_filter($predictions, fn ($p) => ($p['result_prediction']['confidence'] ?? 0) > 70));
        $avgConfidence = array_sum(array_column(array_column($predictions, 'result_prediction'), 'confidence')) / count($predictions);

        $byPrediction = ['1' => 0, 'X' => 0, '2' => 0];
        foreach ($predictions as $p) {
            $pred = $p['result_prediction']['prediction'] ?? '1';
            ++$byPrediction[$pred];
        }

        return [
            'total' => count($predictions),
            'high_confidence' => $highConfidence,
            'average_confidence' => round($avgConfidence, 2),
            'by_prediction' => $byPrediction,
            'avg_expected_goals' => round(array_sum(array_column(array_column($predictions, 'expected_goals'), 'total')) / count($predictions), 2),
        ];
    }
}
