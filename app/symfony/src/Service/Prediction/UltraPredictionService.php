<?php

declare(strict_types=1);

namespace App\Service\Prediction;

use App\Entity\BasketballMatch;
use App\Entity\FootballMatch;
use App\Entity\HockeyMatch;
use App\Repository\BasketballMatchRepository;
use App\Repository\FootballMatchRepository;
use App\Repository\HockeyMatchRepository;
use App\Service\AI\MatchResearchService;
use App\Service\AI\PredictionLearningService;
use App\Service\AI\SportsNewsService;
use App\Service\Data\TeamStatisticsCalculator;
use App\Service\Math\LinearRegressionService;
use App\Service\Math\MonteCarloSimulator;
use Psr\Log\LoggerInterface;

/**
 * Service de prédiction ultra-performant combinant :
 * - Apprentissage automatique des erreurs passées
 * - Actualités et contexte en temps réel
 * - Modèles mathématiques avancés (Monte Carlo, Poisson, Dixon-Coles)
 * - Régression linéaire multiple pour analyse statistique
 * - Analyse multi-facteurs
 *
 * Objectif : Précision > 70% (le 99% est mathématiquement impossible en sport)
 */
final class UltraPredictionService
{
    // Poids optimisés par sport basés sur l'analyse des erreurs
    private const MODEL_WEIGHTS = [
        'football' => [
            'statistical' => 0.22,
            'regression' => 0.15,  // Nouveau poids pour régression
            'form' => 0.18,
            'h2h' => 0.12,
            'elo' => 0.12,
            'news_context' => 0.12,
            'learning_adjustment' => 0.09,
        ],
        'basketball' => [
            'statistical' => 0.25,
            'regression' => 0.18,  // Régression importante pour basket
            'form' => 0.22,
            'momentum' => 0.12,
            'home_away' => 0.08,
            'news_context' => 0.08,
            'learning_adjustment' => 0.07,
        ],
        'hockey' => [
            'statistical' => 0.22,
            'regression' => 0.15,
            'form' => 0.18,
            'goaltending' => 0.18,
            'home_ice' => 0.08,
            'news_context' => 0.12,
            'learning_adjustment' => 0.07,
        ],
    ];

    public function __construct(
        private readonly FootballMatchRepository $footballRepo,
        private readonly BasketballMatchRepository $basketballRepo,
        private readonly HockeyMatchRepository $hockeyRepo,
        private readonly PredictionLearningService $learningService,
        private readonly SportsNewsService $newsService,
        private readonly MatchResearchService $researchService,
        private readonly TeamStatisticsCalculator $statsCalculator,
        private readonly MonteCarloSimulator $monteCarloSimulator,
        private readonly LinearRegressionService $regressionService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Prédiction ultra-optimisée pour un match de football.
     */
    public function predictFootballMatch(FootballMatch $match): array
    {
        $sport = 'football';
        $homeTeam = $match->getHomeTeam();
        $awayTeam = $match->getAwayTeam();
        $league = $match->getLeague();

        // 1. Collecter toutes les données
        $homeStats = $this->footballRepo->getTeamAverageStats($homeTeam);
        $awayStats = $this->footballRepo->getTeamAverageStats($awayTeam);
        $h2hMatches = $this->footballRepo->findHeadToHead($homeTeam, $awayTeam, 10);

        // 2. Obtenir le contexte actualités
        $newsAnalysis = $this->newsService->getCompleteMatchAnalysis(
            $sport,
            $homeTeam->getName(),
            $awayTeam->getName(),
            $league,
            $match->getMatchDate()
        );

        // 3. Recherche IA approfondie
        $aiResearch = $this->researchService->researchMatch($match);

        // 4. Obtenir les ajustements d'apprentissage
        $learningBoosts = $this->learningService->getLearnedPredictionBoosts($sport, [
            'home_form' => $this->calculateFormScore($homeStats),
            'away_form' => $this->calculateFormScore($awayStats),
            'h2h_home_wins' => $this->countH2HWins($h2hMatches, $homeTeam->getName(), true),
            'h2h_away_wins' => $this->countH2HWins($h2hMatches, $awayTeam->getName(), false),
            'league' => $league,
        ]);

        // 5. Calculer les probabilités de base avec Monte Carlo
        $baseProbs = $this->calculateFootballBaseProbabilities($homeStats, $awayStats);

        // 5b. Obtenir les prédictions de régression linéaire
        $regressionFeatures = $this->buildRegressionFeatures($homeStats, $awayStats, $h2hMatches, $homeTeam->getName());
        $regressionPrediction = $this->regressionService->predict($sport, $regressionFeatures);

        // 5c. Combiner les prédictions (ensemble learning)
        $ensembleProbs = $this->combineWithRegression($baseProbs, $regressionPrediction, $sport);

        // 6. Appliquer les ajustements multi-facteurs
        $adjustedProbs = $this->applyFootballAdjustments(
            $ensembleProbs,
            $newsAnalysis,
            $aiResearch,
            $learningBoosts,
            $h2hMatches,
            $homeTeam->getName()
        );

        // 7. Calculer la confiance finale
        $confidence = $this->calculateOptimizedConfidence(
            $adjustedProbs,
            $newsAnalysis,
            $homeStats,
            $awayStats,
            $h2hMatches,
            $sport,
            $league
        );

        // 8. Prédictions supplémentaires
        $overUnder = $this->predictOverUnder($homeStats, $awayStats, $sport);
        $btts = $this->predictBTTS($homeStats, $awayStats);

        return [
            'sport' => $sport,
            'match' => [
                'id' => $match->getId(),
                'home_team' => $homeTeam->getName(),
                'away_team' => $awayTeam->getName(),
                'league' => $league,
                'date' => $match->getMatchDate()->format('Y-m-d H:i'),
            ],
            'prediction' => [
                'result' => $this->getBestPrediction($adjustedProbs),
                'probabilities' => [
                    '1' => round($adjustedProbs['home'], 1),
                    'X' => round($adjustedProbs['draw'], 1),
                    '2' => round($adjustedProbs['away'], 1),
                ],
                'confidence' => $confidence['overall'],
                'confidence_level' => $confidence['level'],
            ],
            'over_under' => $overUnder,
            'btts' => $btts,
            'double_chance' => [
                '1X' => round($adjustedProbs['home'] + $adjustedProbs['draw'], 1),
                'X2' => round($adjustedProbs['draw'] + $adjustedProbs['away'], 1),
                '12' => round($adjustedProbs['home'] + $adjustedProbs['away'], 1),
            ],
            'analysis' => [
                'news_impact' => $newsAnalysis['impact_summary'] ?? [],
                'ai_insight' => $aiResearch['analysis_summary'] ?? '',
                'key_factors' => array_merge(
                    $newsAnalysis['news']['key_factors'] ?? [],
                    $aiResearch['key_factors'] ?? []
                ),
                'risk_level' => $newsAnalysis['impact_summary']['risk_level'] ?? 'medium',
            ],
            'model_details' => [
                'base_probabilities' => $baseProbs,
                'regression_prediction' => $regressionPrediction,
                'ensemble_probabilities' => $ensembleProbs,
                'learning_boosts' => $learningBoosts,
                'news_adjustments' => $newsAnalysis['prediction_adjustments'] ?? [],
                'feature_importance' => $regressionPrediction['feature_importance'] ?? [],
            ],
            'data_quality' => [
                'home_matches_analyzed' => $homeStats['matches_played'] ?? 0,
                'away_matches_analyzed' => $awayStats['matches_played'] ?? 0,
                'h2h_matches' => count($h2hMatches),
                'has_ai_analysis' => $aiResearch['has_ai_analysis'] ?? false,
                'has_news_analysis' => $newsAnalysis['news']['has_analysis'] ?? false,
            ],
        ];
    }

    /**
     * Prédiction ultra-optimisée pour un match de basketball.
     */
    public function predictBasketballMatch(BasketballMatch $match): array
    {
        $sport = 'basketball';
        $homeTeam = $match->getHomeTeam();
        $awayTeam = $match->getAwayTeam();
        $league = $match->getLeague();

        // Statistiques des équipes
        $homeStats = $this->statsCalculator->calculateBasketballStats($homeTeam);
        $awayStats = $this->statsCalculator->calculateBasketballStats($awayTeam);

        // Contexte actualités
        $newsAnalysis = $this->newsService->getCompleteMatchAnalysis(
            $sport,
            $homeTeam->getName(),
            $awayTeam->getName(),
            $league,
            $match->getMatchDate()
        );

        // Ajustements d'apprentissage
        $learningBoosts = $this->learningService->getLearnedPredictionBoosts($sport, [
            'home_form' => $homeStats['form_rating'] ?? 50,
            'away_form' => $awayStats['form_rating'] ?? 50,
            'home_momentum' => $homeStats['momentum'] ?? 0,
            'away_momentum' => $awayStats['momentum'] ?? 0,
            'home_back_to_back' => $homeStats['back_to_back'] ?? false,
            'away_back_to_back' => $awayStats['back_to_back'] ?? false,
        ]);

        // Probabilités de base via simulation Monte Carlo
        $homeAvg = $homeStats['avg_points'] ?? 105;
        $awayAvg = $awayStats['avg_points'] ?? 105;
        $homeStd = $homeStats['std_dev'] ?? 12;
        $awayStd = $awayStats['std_dev'] ?? 12;

        $simulation = $this->monteCarloSimulator->simulateBasketballMatch(
            $homeAvg,
            $awayAvg,
            $homeStd,
            $awayStd,
            10000
        );

        // Appliquer les ajustements
        $baseProbs = [
            'home' => $simulation['result']['home'] ?? 50,
            'away' => $simulation['result']['away'] ?? 50,
        ];

        $adjustedProbs = $this->applyBasketballAdjustments(
            $baseProbs,
            $newsAnalysis,
            $learningBoosts,
            $homeStats,
            $awayStats
        );

        // Confiance
        $confidence = $this->calculateBasketballConfidence(
            $adjustedProbs,
            $homeStats,
            $awayStats,
            $newsAnalysis,
            $league
        );

        // Total points
        $expectedTotal = $homeAvg + $awayAvg;
        $totalPrediction = $this->predictBasketballTotal($expectedTotal, $simulation);

        return [
            'sport' => $sport,
            'match' => [
                'id' => $match->getId(),
                'home_team' => $homeTeam->getName(),
                'away_team' => $awayTeam->getName(),
                'league' => $league,
                'date' => $match->getMatchDate()->format('Y-m-d H:i'),
            ],
            'prediction' => [
                'winner' => $adjustedProbs['home'] > $adjustedProbs['away'] ? 'home' : 'away',
                'probabilities' => [
                    'home' => round($adjustedProbs['home'], 1),
                    'away' => round($adjustedProbs['away'], 1),
                ],
                'confidence' => $confidence['overall'],
                'confidence_level' => $confidence['level'],
            ],
            'total_points' => [
                'expected_total' => round($expectedTotal, 1),
                'predictions' => $totalPrediction,
            ],
            'spread' => [
                'expected_margin' => round($homeAvg - $awayAvg, 1),
                'home_spread' => $simulation['statistics']['spread'] ?? 0,
            ],
            'analysis' => [
                'news_impact' => $newsAnalysis['impact_summary'] ?? [],
                'key_factors' => $newsAnalysis['news']['key_factors'] ?? [],
                'risk_level' => $newsAnalysis['impact_summary']['risk_level'] ?? 'medium',
            ],
            'team_stats' => [
                'home' => [
                    'avg_points' => round($homeAvg, 1),
                    'form_rating' => $homeStats['form_rating'] ?? 50,
                ],
                'away' => [
                    'avg_points' => round($awayAvg, 1),
                    'form_rating' => $awayStats['form_rating'] ?? 50,
                ],
            ],
            'data_quality' => [
                'home_matches_analyzed' => $homeStats['matches_analyzed'] ?? 0,
                'away_matches_analyzed' => $awayStats['matches_analyzed'] ?? 0,
                'data_quality' => $homeStats['data_quality'] ?? 'estimated',
            ],
        ];
    }

    /**
     * Prédiction ultra-optimisée pour un match de hockey.
     */
    public function predictHockeyMatch(HockeyMatch $match): array
    {
        $sport = 'hockey';
        $homeTeam = $match->getHomeTeam();
        $awayTeam = $match->getAwayTeam();
        $league = $match->getLeague();

        // Statistiques
        $homeStats = $this->statsCalculator->calculateHockeyStats($homeTeam);
        $awayStats = $this->statsCalculator->calculateHockeyStats($awayTeam);

        // H2H
        $h2hMatches = $this->hockeyRepo->findHeadToHead($homeTeam, $awayTeam, 10);

        // Contexte actualités
        $newsAnalysis = $this->newsService->getCompleteMatchAnalysis(
            $sport,
            $homeTeam->getName(),
            $awayTeam->getName(),
            $league,
            $match->getMatchDate()
        );

        // Ajustements d'apprentissage
        $learningBoosts = $this->learningService->getLearnedPredictionBoosts($sport, [
            'home_form' => $homeStats['form_rating'] ?? 50,
            'away_form' => $awayStats['form_rating'] ?? 50,
            'home_goalie_rating' => $homeStats['goalie_rating'] ?? 50,
            'away_goalie_rating' => $awayStats['goalie_rating'] ?? 50,
            'home_back_to_back' => $homeStats['back_to_back'] ?? false,
            'away_back_to_back' => $awayStats['back_to_back'] ?? false,
            'home_ot_rate' => $homeStats['overtime_rate'] ?? 0.12,
            'away_ot_rate' => $awayStats['overtime_rate'] ?? 0.12,
        ]);

        // Simulation
        $homeAvg = $homeStats['avg_goals'] ?? 2.8;
        $awayAvg = $awayStats['avg_goals'] ?? 2.8;

        $baseProbs = $this->calculateHockeyBaseProbabilities($homeAvg, $awayAvg, $homeStats, $awayStats);

        // Ajustements
        $adjustedProbs = $this->applyHockeyAdjustments(
            $baseProbs,
            $newsAnalysis,
            $learningBoosts,
            $h2hMatches,
            $homeTeam->getName()
        );

        // Confiance
        $confidence = $this->calculateHockeyConfidence(
            $adjustedProbs,
            $homeStats,
            $awayStats,
            $h2hMatches,
            $newsAnalysis
        );

        // Total goals
        $expectedTotal = $homeAvg + $awayAvg;
        $totalPrediction = $this->predictHockeyTotal($expectedTotal);

        return [
            'sport' => $sport,
            'match' => [
                'id' => $match->getId(),
                'home_team' => $homeTeam->getName(),
                'away_team' => $awayTeam->getName(),
                'league' => $league,
                'date' => $match->getMatchDate()->format('Y-m-d H:i'),
            ],
            'prediction' => [
                'result' => $this->getHockeyBestPrediction($adjustedProbs),
                'probabilities' => [
                    '1' => round($adjustedProbs['home'], 1),
                    'X' => round($adjustedProbs['draw'], 1),
                    '2' => round($adjustedProbs['away'], 1),
                ],
                'regulation_winner' => $adjustedProbs['home'] > $adjustedProbs['away'] ? 'home' : 'away',
                'overtime_probability' => round($adjustedProbs['draw'], 1),
                'confidence' => $confidence['overall'],
                'confidence_level' => $confidence['level'],
            ],
            'total_goals' => [
                'expected_total' => round($expectedTotal, 2),
                'predictions' => $totalPrediction,
            ],
            'analysis' => [
                'news_impact' => $newsAnalysis['impact_summary'] ?? [],
                'key_factors' => $newsAnalysis['news']['key_factors'] ?? [],
                'risk_level' => $newsAnalysis['impact_summary']['risk_level'] ?? 'medium',
                'h2h_summary' => $this->summarizeH2H($h2hMatches, $homeTeam->getName()),
            ],
            'team_stats' => [
                'home' => [
                    'avg_goals' => round($homeAvg, 2),
                    'form_rating' => $homeStats['form_rating'] ?? 50,
                    'goalie_rating' => $homeStats['goalie_rating'] ?? 50,
                ],
                'away' => [
                    'avg_goals' => round($awayAvg, 2),
                    'form_rating' => $awayStats['form_rating'] ?? 50,
                    'goalie_rating' => $awayStats['goalie_rating'] ?? 50,
                ],
            ],
            'data_quality' => [
                'home_matches_analyzed' => $homeStats['matches_analyzed'] ?? 0,
                'away_matches_analyzed' => $awayStats['matches_analyzed'] ?? 0,
                'h2h_matches' => count($h2hMatches),
            ],
        ];
    }

    // === Méthodes privées ===

    private function calculateFootballBaseProbabilities(array $homeStats, array $awayStats): array
    {
        // Calculer les expected goals
        $homeXg = ($homeStats['avg_goals_scored'] ?? 1.3) * 1.08; // Bonus domicile
        $awayXg = ($awayStats['avg_goals_scored'] ?? 1.3) * 0.92;

        // Ajuster selon la défense adverse
        $homeXg *= (2.6 / max(0.5, ($awayStats['avg_goals_conceded'] ?? 1.3) + 1.3));
        $awayXg *= (2.6 / max(0.5, ($homeStats['avg_goals_conceded'] ?? 1.3) + 1.3));

        // Simulation Poisson
        $probs = ['home' => 0, 'draw' => 0, 'away' => 0];

        for ($h = 0; $h <= 6; ++$h) {
            for ($a = 0; $a <= 6; ++$a) {
                $prob = $this->poissonProbability($homeXg, $h) * $this->poissonProbability($awayXg, $a);

                if ($h > $a) {
                    $probs['home'] += $prob;
                } elseif ($h < $a) {
                    $probs['away'] += $prob;
                } else {
                    $probs['draw'] += $prob;
                }
            }
        }

        // Normaliser
        $total = array_sum($probs);

        return [
            'home' => ($probs['home'] / $total) * 100,
            'draw' => ($probs['draw'] / $total) * 100,
            'away' => ($probs['away'] / $total) * 100,
        ];
    }

    private function applyFootballAdjustments(
        array $baseProbs,
        array $newsAnalysis,
        array $aiResearch,
        array $learningBoosts,
        array $h2hMatches,
        string $homeTeamName,
    ): array {
        $probs = $baseProbs;

        // 1. Ajustements des actualités
        $newsAdj = $newsAnalysis['prediction_adjustments'] ?? [];
        $probs['home'] += $newsAdj['home_probability_boost'] ?? 0;
        $probs['away'] += $newsAdj['away_probability_boost'] ?? 0;
        $probs['draw'] += $newsAdj['draw_probability_boost'] ?? 0;

        // 2. Ajustements IA
        $aiAdj = $aiResearch['prediction_adjustment'] ?? [];
        $probs['home'] += $aiAdj['home_boost'] ?? 0;
        $probs['away'] += $aiAdj['away_boost'] ?? 0;
        $probs['draw'] += $aiAdj['draw_boost'] ?? 0;

        // 3. Ajustements apprentissage
        $probs['home'] += $learningBoosts['home_boost'] ?? 0;
        $probs['away'] += $learningBoosts['away_boost'] ?? 0;
        $probs['draw'] += $learningBoosts['draw_boost'] ?? 0;

        // 4. Ajustements H2H
        if (count($h2hMatches) >= 3) {
            $h2hAdj = $this->calculateH2HAdjustment($h2hMatches, $homeTeamName);
            $probs['home'] += $h2hAdj['home'] * 0.5;
            $probs['away'] += $h2hAdj['away'] * 0.5;
            $probs['draw'] += $h2hAdj['draw'] * 0.5;
        }

        // Normaliser pour que le total = 100
        return $this->normalizeProbs($probs);
    }

    private function calculateOptimizedConfidence(
        array $probs,
        array $newsAnalysis,
        array $homeStats,
        array $awayStats,
        array $h2hMatches,
        string $sport,
        string $league,
    ): array {
        $factors = [];

        // 1. Écart de probabilité (max 35 points)
        $maxProb = max($probs);
        $factors['probability_margin'] = min(35, ($maxProb - 33) * 1.2);

        // 2. Qualité des données (max 20 points)
        $dataQuality = min(20, (
            ($homeStats['matches_played'] ?? 0) +
            ($awayStats['matches_played'] ?? 0)
        ) * 0.5 + count($h2hMatches) * 1.5);
        $factors['data_quality'] = $dataQuality;

        // 3. Cohérence des indicateurs (max 20 points)
        $newsImpact = $newsAnalysis['impact_summary']['net_advantage'] ?? 0;
        $probAdvantage = $probs['home'] - $probs['away'];
        $coherence = abs($newsImpact) < 3 || ($newsImpact > 0) === ($probAdvantage > 0) ? 20 : 10;
        $factors['indicator_coherence'] = $coherence;

        // 4. Stabilité de forme (max 15 points)
        $homeForm = $newsAnalysis['news']['recent_form']['home']['trend'] ?? 'unknown';
        $awayForm = $newsAnalysis['news']['recent_form']['away']['trend'] ?? 'unknown';
        $formStability = ('unstable' !== $homeForm ? 7.5 : 3) + ('unstable' !== $awayForm ? 7.5 : 3);
        $factors['form_stability'] = $formStability;

        // 5. Niveau de risque (max 10 points)
        $riskLevel = $newsAnalysis['impact_summary']['risk_level'] ?? 'medium';
        $factors['risk_assessment'] = match ($riskLevel) {
            'low' => 10,
            'medium' => 6,
            'high' => 2,
            default => 5,
        };

        $rawConfidence = array_sum($factors);

        // Ajuster selon l'apprentissage de la ligue
        $adjustedConfidence = $this->learningService->getAdjustedConfidence($sport, $rawConfidence, $league);

        // Niveau de confiance
        $level = match (true) {
            $adjustedConfidence >= 75 => 'very_high',
            $adjustedConfidence >= 65 => 'high',
            $adjustedConfidence >= 55 => 'medium',
            $adjustedConfidence >= 45 => 'low',
            default => 'very_low',
        };

        return [
            'overall' => round($adjustedConfidence, 1),
            'level' => $level,
            'factors' => $factors,
        ];
    }

    private function applyBasketballAdjustments(
        array $baseProbs,
        array $newsAnalysis,
        array $learningBoosts,
        array $homeStats,
        array $awayStats,
    ): array {
        $probs = $baseProbs;

        // Ajustements actualités
        $newsAdj = $newsAnalysis['prediction_adjustments'] ?? [];
        $probs['home'] += ($newsAdj['home_probability_boost'] ?? 0) * 0.5;
        $probs['away'] += ($newsAdj['away_probability_boost'] ?? 0) * 0.5;

        // Ajustements apprentissage
        $probs['home'] += $learningBoosts['home_boost'] ?? 0;
        $probs['away'] += $learningBoosts['away_boost'] ?? 0;

        // Ajustements forme
        $homeForm = $homeStats['form_rating'] ?? 50;
        $awayForm = $awayStats['form_rating'] ?? 50;
        $formDiff = ($homeForm - $awayForm) * 0.15;
        $probs['home'] += $formDiff;
        $probs['away'] -= $formDiff;

        // Normaliser (pas de nul en basketball)
        $total = $probs['home'] + $probs['away'];

        return [
            'home' => ($probs['home'] / $total) * 100,
            'away' => ($probs['away'] / $total) * 100,
        ];
    }

    private function calculateBasketballConfidence(
        array $probs,
        array $homeStats,
        array $awayStats,
        array $newsAnalysis,
        string $league,
    ): array {
        $factors = [];

        // Écart de probabilité
        $maxProb = max($probs);
        $factors['probability_margin'] = min(40, ($maxProb - 50) * 1.5);

        // Qualité des données
        $factors['data_quality'] = min(25, (
            ($homeStats['matches_analyzed'] ?? 0) +
            ($awayStats['matches_analyzed'] ?? 0)
        ) * 1.5);

        // Forme
        $homeForm = $homeStats['form_rating'] ?? 50;
        $awayForm = $awayStats['form_rating'] ?? 50;
        $factors['form_confidence'] = min(20, abs($homeForm - $awayForm) * 0.4);

        // Risque
        $riskLevel = $newsAnalysis['impact_summary']['risk_level'] ?? 'medium';
        $factors['risk'] = match ($riskLevel) {
            'low' => 15,
            'medium' => 10,
            'high' => 5,
            default => 10,
        };

        $rawConfidence = array_sum($factors);
        $adjustedConfidence = $this->learningService->getAdjustedConfidence('basketball', $rawConfidence, $league);

        return [
            'overall' => round(min(95, max(35, $adjustedConfidence)), 1),
            'level' => $adjustedConfidence >= 70 ? 'high' : ($adjustedConfidence >= 55 ? 'medium' : 'low'),
            'factors' => $factors,
        ];
    }

    private function calculateHockeyBaseProbabilities(
        float $homeAvg,
        float $awayAvg,
        array $homeStats,
        array $awayStats,
    ): array {
        // Ajuster pour le bonus domicile en NHL (~54% de victoires à domicile)
        $homeXg = $homeAvg * 1.05;
        $awayXg = $awayAvg * 0.95;

        // Impact du gardien
        $homeGoalieRating = ($homeStats['goalie_rating'] ?? 50) / 50;
        $awayGoalieRating = ($awayStats['goalie_rating'] ?? 50) / 50;

        $homeXg *= (2 - $awayGoalieRating);
        $awayXg *= (2 - $homeGoalieRating);

        // Simulation Poisson pour hockey (incluant OT)
        $probs = ['home' => 0, 'draw' => 0, 'away' => 0];

        for ($h = 0; $h <= 8; ++$h) {
            for ($a = 0; $a <= 8; ++$a) {
                $prob = $this->poissonProbability($homeXg, $h) * $this->poissonProbability($awayXg, $a);

                if ($h > $a) {
                    $probs['home'] += $prob;
                } elseif ($h < $a) {
                    $probs['away'] += $prob;
                } else {
                    $probs['draw'] += $prob;
                }
            }
        }

        // En NHL, les nuls vont en OT - environ 50/50
        $otProb = $probs['draw'];
        $probs['home'] += $otProb * 0.52; // Léger avantage domicile en OT
        $probs['away'] += $otProb * 0.48;

        // Garder une petite probabilité de "nul" pour représenter le OT
        $probs['draw'] = $otProb * 100; // Probabilité d'aller en prolongation

        $total = $probs['home'] + $probs['away'];

        return [
            'home' => ($probs['home'] / $total) * 100,
            'draw' => min(25, $probs['draw']), // Cap à 25% pour les OT
            'away' => ($probs['away'] / $total) * 100,
        ];
    }

    private function applyHockeyAdjustments(
        array $baseProbs,
        array $newsAnalysis,
        array $learningBoosts,
        array $h2hMatches,
        string $homeTeamName,
    ): array {
        $probs = $baseProbs;

        // Ajustements actualités
        $newsAdj = $newsAnalysis['prediction_adjustments'] ?? [];
        $probs['home'] += $newsAdj['home_probability_boost'] ?? 0;
        $probs['away'] += $newsAdj['away_probability_boost'] ?? 0;

        // Ajustements apprentissage
        $probs['home'] += $learningBoosts['home_boost'] ?? 0;
        $probs['away'] += $learningBoosts['away_boost'] ?? 0;
        $probs['draw'] += $learningBoosts['draw_boost'] ?? 0;

        // H2H
        if (count($h2hMatches) >= 3) {
            $h2hAdj = $this->calculateH2HAdjustment($h2hMatches, $homeTeamName);
            $probs['home'] += $h2hAdj['home'] * 0.3;
            $probs['away'] += $h2hAdj['away'] * 0.3;
        }

        return $this->normalizeProbs($probs);
    }

    private function calculateHockeyConfidence(
        array $probs,
        array $homeStats,
        array $awayStats,
        array $h2hMatches,
        array $newsAnalysis,
    ): array {
        $factors = [];

        $maxProb = max($probs['home'], $probs['away']);
        $factors['probability_margin'] = min(35, ($maxProb - 50) * 1.0);

        $factors['data_quality'] = min(20, (
            ($homeStats['matches_analyzed'] ?? 0) +
            ($awayStats['matches_analyzed'] ?? 0)
        ) + count($h2hMatches) * 2);

        $factors['goalie_factor'] = min(15, abs(
            ($homeStats['goalie_rating'] ?? 50) - ($awayStats['goalie_rating'] ?? 50)
        ) * 0.3);

        $riskLevel = $newsAnalysis['impact_summary']['risk_level'] ?? 'medium';
        $factors['risk'] = match ($riskLevel) {
            'low' => 15,
            'medium' => 10,
            'high' => 5,
            default => 10,
        };

        $rawConfidence = array_sum($factors);

        return [
            'overall' => round(min(90, max(35, $rawConfidence)), 1),
            'level' => $rawConfidence >= 65 ? 'high' : ($rawConfidence >= 50 ? 'medium' : 'low'),
            'factors' => $factors,
        ];
    }

    // === Méthodes utilitaires ===

    private function poissonProbability(float $lambda, int $k): float
    {
        return (exp(-$lambda) * pow($lambda, $k)) / $this->factorial($k);
    }

    private function factorial(int $n): int
    {
        if ($n <= 1) {
            return 1;
        }
        $result = 1;
        for ($i = 2; $i <= $n; ++$i) {
            $result *= $i;
        }

        return $result;
    }

    private function normalizeProbs(array $probs): array
    {
        $total = array_sum($probs);
        if ($total <= 0) {
            return ['home' => 40, 'draw' => 25, 'away' => 35];
        }
        foreach ($probs as &$p) {
            $p = max(1, ($p / $total) * 100);
        }

        return $probs;
    }

    private function getBestPrediction(array $probs): string
    {
        $max = max($probs);
        if ($probs['home'] === $max) {
            return '1';
        }
        if ($probs['away'] === $max) {
            return '2';
        }

        return 'X';
    }

    private function getHockeyBestPrediction(array $probs): string
    {
        return $probs['home'] > $probs['away'] ? '1' : '2';
    }

    private function calculateFormScore(array $stats): float
    {
        $goalsScored = $stats['avg_goals_scored'] ?? 1.3;
        $goalsConceded = $stats['avg_goals_conceded'] ?? 1.3;
        $matchesPlayed = $stats['matches_played'] ?? 0;

        if (0 === $matchesPlayed) {
            return 50;
        }

        $goalDiff = $goalsScored - $goalsConceded;

        return min(100, max(0, 50 + $goalDiff * 15));
    }

    private function countH2HWins(array $matches, string $teamName, bool $isHome): int
    {
        $wins = 0;
        foreach ($matches as $match) {
            $homeScore = $match->getHomeScore() ?? 0;
            $awayScore = $match->getAwayScore() ?? 0;
            $matchHomeTeam = $match->getHomeTeam()->getName();

            if ($isHome && $matchHomeTeam === $teamName && $homeScore > $awayScore) {
                ++$wins;
            } elseif (!$isHome && $matchHomeTeam !== $teamName && $awayScore > $homeScore) {
                ++$wins;
            }
        }

        return $wins;
    }

    private function calculateH2HAdjustment(array $h2hMatches, string $homeTeamName): array
    {
        $homeWins = 0;
        $awayWins = 0;
        $draws = 0;

        foreach ($h2hMatches as $match) {
            $homeScore = $match->getHomeScore() ?? $match->getHomeFinalScore() ?? 0;
            $awayScore = $match->getAwayScore() ?? $match->getAwayFinalScore() ?? 0;

            if ($homeScore > $awayScore) {
                if ($match->getHomeTeam()->getName() === $homeTeamName) {
                    ++$homeWins;
                } else {
                    ++$awayWins;
                }
            } elseif ($awayScore > $homeScore) {
                if ($match->getAwayTeam()->getName() === $homeTeamName) {
                    ++$homeWins;
                } else {
                    ++$awayWins;
                }
            } else {
                ++$draws;
            }
        }

        $total = count($h2hMatches);
        if (0 === $total) {
            return ['home' => 0, 'draw' => 0, 'away' => 0];
        }

        $homeRate = ($homeWins / $total) * 100;
        $awayRate = ($awayWins / $total) * 100;
        $drawRate = ($draws / $total) * 100;

        return [
            'home' => ($homeRate - 40) * 0.2,
            'draw' => ($drawRate - 25) * 0.1,
            'away' => ($awayRate - 35) * 0.2,
        ];
    }

    private function summarizeH2H(array $matches, string $homeTeamName): array
    {
        if (empty($matches)) {
            return ['available' => false];
        }

        $homeWins = 0;
        $awayWins = 0;
        $draws = 0;
        $totalGoals = 0;

        foreach ($matches as $match) {
            $homeScore = $match->getHomeFinalScore() ?? 0;
            $awayScore = $match->getAwayFinalScore() ?? 0;
            $totalGoals += $homeScore + $awayScore;

            if ($homeScore > $awayScore) {
                if ($match->getHomeTeam()->getName() === $homeTeamName) {
                    ++$homeWins;
                } else {
                    ++$awayWins;
                }
            } elseif ($awayScore > $homeScore) {
                if ($match->getAwayTeam()->getName() === $homeTeamName) {
                    ++$homeWins;
                } else {
                    ++$awayWins;
                }
            } else {
                ++$draws;
            }
        }

        return [
            'available' => true,
            'matches_count' => count($matches),
            'home_team_wins' => $homeWins,
            'away_team_wins' => $awayWins,
            'draws' => $draws,
            'avg_goals' => round($totalGoals / count($matches), 1),
        ];
    }

    private function predictOverUnder(array $homeStats, array $awayStats, string $sport): array
    {
        $homeXg = $homeStats['avg_goals_scored'] ?? 1.3;
        $awayXg = $awayStats['avg_goals_scored'] ?? 1.3;
        $totalXg = $homeXg + $awayXg;

        $lines = [1.5, 2.5, 3.5];
        $predictions = [];

        foreach ($lines as $line) {
            $over = 0;
            for ($total = (int) ceil($line); $total <= 10; ++$total) {
                for ($h = 0; $h <= $total; ++$h) {
                    $a = $total - $h;
                    $over += $this->poissonProbability($homeXg, $h) * $this->poissonProbability($awayXg, $a);
                }
            }
            $predictions["over_{$line}"] = round($over * 100, 1);
            $predictions["under_{$line}"] = round((1 - $over) * 100, 1);
        }

        $predictions['expected_total'] = round($totalXg, 2);

        return $predictions;
    }

    private function predictBTTS(array $homeStats, array $awayStats): array
    {
        $homeXg = $homeStats['avg_goals_scored'] ?? 1.3;
        $awayXg = $awayStats['avg_goals_scored'] ?? 1.3;

        // P(BTTS) = P(home >= 1) * P(away >= 1)
        $homeScores = 1 - exp(-$homeXg);
        $awayScores = 1 - exp(-$awayXg);
        $bttsYes = $homeScores * $awayScores * 100;

        return [
            'yes' => round($bttsYes, 1),
            'no' => round(100 - $bttsYes, 1),
        ];
    }

    private function predictBasketballTotal(float $expectedTotal, array $simulation): array
    {
        $lines = [190.5, 200.5, 210.5, 220.5, 230.5];
        $predictions = [];

        foreach ($lines as $line) {
            // Utiliser une distribution normale
            $stdDev = $simulation['statistics']['std_dev'] ?? 15;
            $zScore = ($line - $expectedTotal) / $stdDev;
            $under = $this->normalCDF($zScore) * 100;

            $predictions["over_{$line}"] = round(100 - $under, 1);
            $predictions["under_{$line}"] = round($under, 1);
        }

        return $predictions;
    }

    private function predictHockeyTotal(float $expectedTotal): array
    {
        $lines = [4.5, 5.5, 6.5];
        $predictions = [];

        foreach ($lines as $line) {
            $over = 0;
            for ($total = (int) ceil($line); $total <= 12; ++$total) {
                $over += $this->poissonProbability($expectedTotal, $total);
            }
            $predictions["over_{$line}"] = round($over * 100, 1);
            $predictions["under_{$line}"] = round((1 - $over) * 100, 1);
        }

        return $predictions;
    }

    private function normalCDF(float $z): float
    {
        // Approximation de la CDF normale standard
        $a1 = 0.254829592;
        $a2 = -0.284496736;
        $a3 = 1.421413741;
        $a4 = -1.453152027;
        $a5 = 1.061405429;
        $p = 0.3275911;

        $sign = $z < 0 ? -1 : 1;
        $z = abs($z) / sqrt(2);

        $t = 1.0 / (1.0 + $p * $z);
        $y = 1.0 - ((((($a5 * $t + $a4) * $t) + $a3) * $t + $a2) * $t + $a1) * $t * exp(-$z * $z);

        return 0.5 * (1.0 + $sign * $y);
    }

    // === Méthodes de régression linéaire ===

    /**
     * Construit les features pour le modèle de régression.
     */
    private function buildRegressionFeatures(array $homeStats, array $awayStats, array $h2hMatches, string $homeTeamName): array
    {
        // Intercept
        $features = [1.0];

        // Feature 1: Moyenne de buts/points à domicile
        $features[] = $homeStats['avg_goals_scored'] ?? $homeStats['avg_points_home'] ?? 1.5;

        // Feature 2: Moyenne de buts/points à l'extérieur
        $features[] = $awayStats['avg_goals_scored'] ?? $awayStats['avg_points_away'] ?? 1.0;

        // Feature 3: Différence de classement (approximé par les buts)
        $homeDiff = ($homeStats['avg_goals_scored'] ?? 1.3) - ($homeStats['avg_goals_conceded'] ?? 1.3);
        $awayDiff = ($awayStats['avg_goals_scored'] ?? 1.3) - ($awayStats['avg_goals_conceded'] ?? 1.3);
        $features[] = $homeDiff - $awayDiff;

        // Feature 4: Forme récente
        $homeForm = $this->calculateFormScore($homeStats);
        $awayForm = $this->calculateFormScore($awayStats);
        $features[] = ($homeForm - $awayForm) / 100;

        // Feature 5: Taux de victoire à domicile
        $features[] = $homeStats['home_win_rate'] ?? 0.5;

        // Feature 6: Taux de victoire à l'extérieur
        $features[] = $awayStats['away_win_rate'] ?? 0.3;

        // Feature 7: Head-to-head
        $h2hStats = $this->calculateH2HAdjustment($h2hMatches, $homeTeamName);
        $features[] = $h2hStats['home'] - $h2hStats['away'];

        // Feature 8: Défense
        $features[] = ($awayStats['avg_goals_conceded'] ?? 1.3) - ($homeStats['avg_goals_conceded'] ?? 1.3);

        // Feature 9: Clean sheets rate (pour football)
        $features[] = $homeStats['clean_sheets_rate'] ?? ($homeStats['clean_sheets'] ?? 0) / max(1, $homeStats['matches_played'] ?? 1);

        // Feature 10: BTTS rate
        $bttsHome = $homeStats['btts_percentage'] ?? 50;
        $bttsAway = $awayStats['btts_percentage'] ?? 50;
        $features[] = ($bttsHome * $bttsAway) / 10000;

        return $features;
    }

    /**
     * Combine les probabilités de base avec les prédictions de régression.
     */
    private function combineWithRegression(array $baseProbs, array $regressionPrediction, string $sport): array
    {
        $weights = self::MODEL_WEIGHTS[$sport] ?? self::MODEL_WEIGHTS['football'];
        $regressionWeight = $weights['regression'] ?? 0.15;
        $baseWeight = 1 - $regressionWeight;

        $regressionProbs = $regressionPrediction['probabilities'] ?? [];

        // Convertir les clés si nécessaire
        $regHome = ($regressionProbs['home'] ?? $regressionProbs['1'] ?? $baseProbs['home']) * 100;
        $regAway = ($regressionProbs['away'] ?? $regressionProbs['2'] ?? $baseProbs['away']) * 100;
        $regDraw = ($regressionProbs['draw'] ?? $regressionProbs['X'] ?? $baseProbs['draw'] ?? 0) * 100;

        // S'assurer que les valeurs sont dans [0, 100]
        $regHome = min(100, max(0, $regHome));
        $regAway = min(100, max(0, $regAway));
        $regDraw = min(100, max(0, $regDraw));

        $combined = [
            'home' => $baseProbs['home'] * $baseWeight + $regHome * $regressionWeight,
            'away' => $baseProbs['away'] * $baseWeight + $regAway * $regressionWeight,
        ];

        if (isset($baseProbs['draw'])) {
            $combined['draw'] = $baseProbs['draw'] * $baseWeight + $regDraw * $regressionWeight;
        }

        // Normaliser
        return $this->normalizeProbs($combined);
    }

    /**
     * Entraîne le modèle de régression (peut être appelé manuellement ou automatiquement).
     */
    public function trainRegressionModels(int $days = 90): array
    {
        $results = [];

        foreach (['football', 'basketball', 'hockey'] as $sport) {
            try {
                $result = $this->regressionService->train($sport, $days);
                $results[$sport] = $result;

                $this->logger->info("Modèle de régression entraîné pour $sport", [
                    'success' => $result['success'],
                    'samples' => $result['samples'] ?? 0,
                    'r_squared' => $result['metrics']['r_squared'] ?? 'N/A',
                ]);
            } catch (\Exception $e) {
                $results[$sport] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
                $this->logger->warning("Échec de l'entraînement de régression pour $sport: ".$e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Analyse l'importance des features pour un sport donné.
     */
    public function getFeatureImportance(string $sport): array
    {
        return $this->regressionService->analyzeFeatureImportance($sport);
    }

    /**
     * Obtient les statistiques du modèle de régression.
     */
    public function getRegressionStats(string $sport): array
    {
        $trainingResult = $this->regressionService->train($sport);

        return [
            'sport' => $sport,
            'is_trained' => $trainingResult['success'] ?? false,
            'samples' => $trainingResult['samples'] ?? 0,
            'metrics' => $trainingResult['metrics'] ?? [],
            'feature_importance' => $this->getFeatureImportance($sport),
        ];
    }
}
