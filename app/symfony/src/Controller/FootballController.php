<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\FootballMatchRepository;
use App\Repository\TeamRepository;
use App\Service\AI\MatchResearchService;
use App\Service\AI\PredictionLearningService;
use App\Service\Analysis\CrossValidationService;
use App\Service\Analysis\MatchDetailedAnalysisService;
use App\Service\Analysis\PredictionAccuracyService;
use App\Service\Betting\ValueBetDetector;
use App\Service\Prediction\EnhancedPredictionService;
use App\Service\Prediction\GoalsPredictionService;
use App\Service\Prediction\HalfTimeFullTimeService;
use App\Service\Prediction\ResultPredictionService;
use App\Service\Prediction\ScorerPredictionService;
use App\Service\Prediction\UltraPredictionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/football')]
class FootballController extends AbstractController
{
    public function __construct(
        private readonly FootballMatchRepository $matchRepository,
        private readonly TeamRepository $teamRepository,
        private readonly ResultPredictionService $resultPredictionService,
        private readonly GoalsPredictionService $goalsPredictionService,
        private readonly ScorerPredictionService $scorerPredictionService,
        private readonly HalfTimeFullTimeService $halfTimeFullTimeService,
        private readonly ValueBetDetector $valueBetDetector,
        private readonly EnhancedPredictionService $enhancedPredictionService,
        private readonly MatchResearchService $matchResearchService,
        private readonly MatchDetailedAnalysisService $detailedAnalysisService,
        private readonly PredictionAccuracyService $predictionAccuracyService,
        private readonly UltraPredictionService $ultraPredictionService,
        private readonly PredictionLearningService $learningService,
        private readonly CrossValidationService $validationService,
    ) {
    }

    #[Route('/predictions/today', name: 'app_football_predictions_today')]
    public function predictionsToday(Request $request): Response
    {
        $sortBy = $request->query->get('sort', 'confidence'); // @TODO set as enum later confidence, time, league
        $limit = $request->query->getInt('limit', 150);

        // Utiliser le service amélioré qui trie par confiance
        $predictions = $this->enhancedPredictionService->getUpcomingPredictionsSortedByConfidence($limit);

        // Filtrer pour aujourd'hui seulement
        $today = new \DateTimeImmutable('today');
        $tomorrow = new \DateTimeImmutable('tomorrow');

        $predictions = array_filter($predictions, function ($p) use ($today, $tomorrow) {
            $matchDate = $p['match']->getMatchDate();

            return $matchDate >= $today && $matchDate < $tomorrow;
        });

        // Tri alternatif si demandé
        if ('time' === $sortBy) {
            usort($predictions, fn ($a, $b) => $a['match']->getMatchDate() <=> $b['match']->getMatchDate());
        } elseif ('league' === $sortBy) {
            usort($predictions, fn ($a, $b) => $a['match']->getLeague() <=> $b['match']->getLeague());
        }

        $summary = $this->enhancedPredictionService->generatePredictionsSummary($predictions);

        // Récupérer les matchs terminés récents avec vérification des prédictions
        $recentFinishedData = $this->predictionAccuracyService->getFootballDailyMatchesWithAccuracy(
            new \DateTimeImmutable('yesterday')
        );
        $todayFinishedData = $this->predictionAccuracyService->getFootballDailyMatchesWithAccuracy(
            new \DateTimeImmutable('today')
        );

        // Fusionner les matchs terminés (aujourd'hui + hier)
        $recentFinishedMatches = array_merge(
            array_filter($todayFinishedData['matches'], fn ($m) => $m['is_finished']),
            array_filter($recentFinishedData['matches'], fn ($m) => $m['is_finished'])
        );

        // Limiter à 10 matchs récents
        $recentFinishedMatches = array_slice($recentFinishedMatches, 0, 10);

        // Calculer les statistiques de précision
        $accuracyStats = [
            'total_finished' => count($recentFinishedMatches),
            'correct_predictions' => count(array_filter($recentFinishedMatches, fn ($m) => $m['prediction_correct'])),
        ];
        $accuracyStats['accuracy_rate'] = $accuracyStats['total_finished'] > 0
            ? round(($accuracyStats['correct_predictions'] / $accuracyStats['total_finished']) * 100, 1)
            : 0;

        return $this->render('football/predictions_today.html.twig', [
            'predictions' => $predictions,
            'summary' => $summary,
            'current_sort' => $sortBy,
            'recent_finished_matches' => $recentFinishedMatches,
            'accuracy_stats' => $accuracyStats,
        ]);
    }

    #[Route('/match/{id}/analysis', name: 'app_football_match_analysis')]
    public function matchAnalysis(int $id): Response
    {
        $match = $this->matchRepository->find($id);

        if (!$match) {
            throw $this->createNotFoundException('Match non trouvé');
        }

        // Analyse detaillee complete
        $detailedAnalysis = $this->detailedAnalysisService->analyzeMatch($match);

        // Predictions de base (garde pour compatibilite)
        $resultPrediction = $this->resultPredictionService->predictResult($match);
        $goalsPrediction = $this->goalsPredictionService->predictOverUnder($match);
        $bttsPrediction = $this->goalsPredictionService->predictBTTS($match);
        $exactScorePrediction = $this->goalsPredictionService->predictExactScore($match);
        $scorersPrediction = $this->scorerPredictionService->predictScorers($match);
        $htftPrediction = $this->halfTimeFullTimeService->predictHalfTimeFullTime($match);
        $h2hAnalysis = $this->resultPredictionService->analyzeHeadToHead($match);

        // Recherche IA
        $aiResearch = $this->matchResearchService->researchMatch($match);

        return $this->render('football/match_analysis.html.twig', [
            'match' => $match,
            'detailed_analysis' => $detailedAnalysis,
            'result_prediction' => $resultPrediction,
            'goals_prediction' => $goalsPrediction,
            'btts_prediction' => $bttsPrediction,
            'exact_score' => $exactScorePrediction,
            'scorers' => $scorersPrediction,
            'htft' => $htftPrediction,
            'h2h' => $h2hAnalysis,
            'ai_research' => $aiResearch,
        ]);
    }

    /**
     * API endpoint pour l'analyse detaillee d'un match.
     */
    #[Route('/api/match/{id}/detailed-analysis', name: 'api_football_match_detailed', methods: ['GET'])]
    public function apiMatchDetailedAnalysis(int $id): JsonResponse
    {
        $match = $this->matchRepository->find($id);

        if (!$match) {
            return $this->json(['error' => 'Match non trouve'], 404);
        }

        $analysis = $this->detailedAnalysisService->analyzeMatch($match);

        return $this->json([
            'success' => true,
            'data' => $analysis,
        ]);
    }

    #[Route('/safest-bets', name: 'app_football_safest_bets')]
    public function safestBets(): Response
    {
        // Récupérer les paris les plus sûrs triés par score de sécurité
        $safestBets = $this->enhancedPredictionService->getTodaySafestBets(20);

        $summary = $this->enhancedPredictionService->generatePredictionsSummary($safestBets);

        return $this->render('football/safest_bets.html.twig', [
            'safest_bets' => $safestBets,
            'summary' => $summary,
        ]);
    }

    #[Route('/upcoming', name: 'app_football_upcoming')]
    public function upcomingMatches(Request $request): Response
    {
        $limit = $request->query->getInt('limit', 50);
        $sortBy = $request->query->get('sort', 'confidence');

        $predictions = $this->enhancedPredictionService->getUpcomingPredictionsSortedByConfidence($limit);

        if ('time' === $sortBy) {
            usort($predictions, fn ($a, $b) => $a['match']->getMatchDate() <=> $b['match']->getMatchDate());
        } elseif ('league' === $sortBy) {
            usort($predictions, fn ($a, $b) => $a['match']->getLeague() <=> $b['match']->getLeague());
        }

        $summary = $this->enhancedPredictionService->generatePredictionsSummary($predictions);

        return $this->render('football/upcoming.html.twig', [
            'predictions' => $predictions,
            'summary' => $summary,
            'current_sort' => $sortBy,
        ]);
    }

    #[Route('/safe-bets', name: 'app_football_safe_bets')]
    public function safeBets(): Response
    {
        $safeBets = $this->matchRepository->findSafeBetsToday();

        $betsWithPredictions = [];
        foreach ($safeBets as $match) {
            $predictions = $this->resultPredictionService->predictResult($match);

            $betsWithPredictions[] = [
                'match' => $match,
                'predictions' => $predictions,
            ];
        }

        return $this->render('football/safe_bets.html.twig', [
            'safe_bets' => $betsWithPredictions,
        ]);
    }

    #[Route('/value-bets', name: 'app_football_value_bets')]
    public function valueBets(): Response
    {
        $matches = $this->matchRepository->findTodayMatches();

        $valueBets = [];
        foreach ($matches as $match) {
            $predictions = $this->resultPredictionService->predictResult($match);

            // Récupérer les vraies cotes depuis la base de données
            $oddsArray = $match->getOddsArray();

            // Si aucune cote réelle n'est disponible, utiliser des cotes par défaut
            if (empty($oddsArray) || !isset($oddsArray['1X2'])) {
                $oddsArray = [
                    '1X2' => [
                        '1' => 2.0,
                        'X' => 3.5,
                        '2' => 4.0,
                    ],
                ];
            }

            // Convertir au format attendu par le ValueBetDetector
            $formattedOdds = [
                '1X2_1' => $oddsArray['1X2']['1'] ?? 2.0,
                '1X2_X' => $oddsArray['1X2']['X'] ?? 3.5,
                '1X2_2' => $oddsArray['1X2']['2'] ?? 4.0,
            ];

            $bets = $this->valueBetDetector->findValueBets(
                [
                    '1X2_1' => ['probability' => $predictions['probabilities']['1']],
                    '1X2_X' => ['probability' => $predictions['probabilities']['X']],
                    '1X2_2' => ['probability' => $predictions['probabilities']['2']],
                ],
                $formattedOdds
            );

            if (!empty($bets)) {
                $valueBets[] = [
                    'match' => $match,
                    'value_bets' => $bets,
                    'has_real_odds' => !empty($match->getOddsArray()),
                ];
            }
        }

        return $this->render('football/value_bets.html.twig', [
            'value_bets' => $valueBets,
        ]);
    }

    #[Route('/team/{id}', name: 'app_football_team')]
    public function teamDetail(int $id): Response
    {
        $team = $this->teamRepository->find($id);

        if (!$team) {
            throw $this->createNotFoundException('Équipe non trouvée');
        }

        $stats = $this->teamRepository->getTeamStatistics($id);
        $upcomingMatches = $this->matchRepository->findUpcomingMatchesByTeam($team, 5);
        $recentMatches = $this->matchRepository->findRecentMatchesByTeam($team, 5);

        return $this->render('football/team_detail.html.twig', [
            'team' => $team,
            'stats' => $stats,
            'upcoming_matches' => $upcomingMatches,
            'recent_matches' => $recentMatches,
        ]);
    }

    /**
     * Affiche tous les matchs du jour avec vérification des prédictions.
     */
    #[Route('/daily', name: 'app_football_daily')]
    public function dailyMatches(Request $request): Response
    {
        $dateStr = $request->query->get('date');
        $date = $dateStr ? new \DateTimeImmutable($dateStr) : new \DateTimeImmutable('today');

        // Récupérer tous les matchs du jour avec leurs prédictions et résultats
        $dailyData = $this->predictionAccuracyService->getFootballDailyMatchesWithAccuracy($date);

        // Récupérer les stats globales des 7 derniers jours
        $overallStats = $this->predictionAccuracyService->getOverallAccuracyStats('football', 7);

        // Trier les matchs: terminés en premier, puis en cours, puis à venir
        usort($dailyData['matches'], function ($a, $b) {
            // Priorité: terminés (0), en cours (1), à venir (2)
            $priorityA = $a['is_finished'] ? 0 : ($a['is_live'] ? 1 : 2);
            $priorityB = $b['is_finished'] ? 0 : ($b['is_live'] ? 1 : 2);

            if ($priorityA !== $priorityB) {
                return $priorityA - $priorityB;
            }

            // À l'intérieur de chaque groupe, trier par heure
            return $a['match']->getMatchDate() <=> $b['match']->getMatchDate();
        });

        return $this->render('football/daily.html.twig', [
            'daily_data' => $dailyData,
            'overall_stats' => $overallStats,
            'current_date' => $date,
            'prev_date' => $date->modify('-1 day'),
            'next_date' => $date->modify('+1 day'),
        ]);
    }

    /**
     * API endpoint pour les statistiques de précision.
     */
    #[Route('/api/accuracy-stats', name: 'api_football_accuracy_stats', methods: ['GET'])]
    public function apiAccuracyStats(Request $request): JsonResponse
    {
        $days = $request->query->getInt('days', 7);
        $stats = $this->predictionAccuracyService->getOverallAccuracyStats('football', $days);

        return $this->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * API endpoint pour la validation croisée des prédictions football.
     */
    #[Route('/api/validation', name: 'api_football_validation', methods: ['GET'])]
    public function apiValidation(Request $request): JsonResponse
    {
        $days = $request->query->getInt('days', 30);

        // Appliquer l'apprentissage avant validation
        $this->learningService->analyzeAndLearn('football');

        $validation = $this->validationService->validatePredictions('football', $days);
        $kFold = $this->validationService->kFoldValidation('football', 5, $days);
        $comparison = $this->validationService->comparePerformance('football', 7, 30);

        return $this->json([
            'success' => true,
            'validation' => $validation,
            'k_fold' => $kFold,
            'performance_comparison' => $comparison,
        ]);
    }

    /**
     * API endpoint pour les statistiques d'apprentissage.
     */
    #[Route('/api/learning-stats', name: 'api_football_learning', methods: ['GET'])]
    public function apiLearningStats(): JsonResponse
    {
        $learningAnalysis = $this->learningService->analyzeAndLearn('football');
        $adjustmentFactors = $this->learningService->getAdjustmentFactors('football');
        $errorHistory = $this->learningService->getErrorHistory('football', 7);

        return $this->json([
            'success' => true,
            'learning_analysis' => $learningAnalysis,
            'adjustment_factors' => $adjustmentFactors,
            'recent_errors' => $errorHistory,
        ]);
    }

    /**
     * API endpoint pour la prédiction ultra-optimisée d'un match.
     */
    #[Route('/api/match/{id}/ultra-prediction', name: 'api_football_ultra_prediction', methods: ['GET'])]
    public function apiUltraPrediction(int $id): JsonResponse
    {
        $match = $this->matchRepository->find($id);

        if (!$match) {
            return $this->json(['error' => 'Match non trouve'], 404);
        }

        try {
            $prediction = $this->ultraPredictionService->predictFootballMatch($match);

            return $this->json([
                'success' => true,
                'prediction' => $prediction,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API endpoint pour les statistiques du modèle de régression.
     */
    #[Route('/api/regression-stats', name: 'api_football_regression', methods: ['GET'])]
    public function apiRegressionStats(): JsonResponse
    {
        try {
            $stats = $this->ultraPredictionService->getRegressionStats('football');

            return $this->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API endpoint pour entraîner les modèles de régression.
     */
    #[Route('/api/regression-train', name: 'api_football_regression_train', methods: ['POST'])]
    public function apiTrainRegression(Request $request): JsonResponse
    {
        $days = $request->query->getInt('days', 90);

        try {
            $results = $this->ultraPredictionService->trainRegressionModels($days);

            return $this->json([
                'success' => true,
                'training_results' => $results,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API endpoint pour l'importance des features.
     */
    #[Route('/api/feature-importance', name: 'api_football_features', methods: ['GET'])]
    public function apiFeatureImportance(): JsonResponse
    {
        try {
            $importance = $this->ultraPredictionService->getFeatureImportance('football');

            return $this->json([
                'success' => true,
                'data' => $importance,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
