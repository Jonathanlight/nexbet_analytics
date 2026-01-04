<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\BasketballMatchRepository;
use App\Service\AI\PredictionLearningService;
use App\Service\Analysis\BasketballDetailedAnalysisService;
use App\Service\Analysis\CrossValidationService;
use App\Service\Analysis\PredictionAccuracyService;
use App\Service\Data\TeamStatisticsCalculator;
use App\Service\Prediction\BasketballPredictionService;
use App\Service\Prediction\UltraPredictionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/basketball')]
class BasketballController extends AbstractController
{
    public function __construct(
        private readonly BasketballMatchRepository $basketballMatchRepository,
        private readonly BasketballPredictionService $basketballPredictionService,
        private readonly BasketballDetailedAnalysisService $detailedAnalysisService,
        private readonly TeamStatisticsCalculator $statsCalculator,
        private readonly PredictionAccuracyService $predictionAccuracyService,
        private readonly UltraPredictionService $ultraPredictionService,
        private readonly PredictionLearningService $learningService,
        private readonly CrossValidationService $validationService,
    ) {
    }

    #[Route('/predictions/today', name: 'app_basketball_predictions_today')]
    public function predictionsToday(): Response
    {
        // Appliquer l'apprentissage des erreurs passées
        $this->learningService->analyzeAndLearn('basketball');

        // Requête optimisée avec eager loading
        $matches = $this->basketballMatchRepository->findTodayMatches();

        // Si pas de matchs aujourd'hui, chercher les prochains matchs
        if (empty($matches)) {
            $matches = $this->basketballMatchRepository->findUpcomingMatches(20);
        }

        // Construire les prédictions ultra-optimisées pour chaque match
        $matchesWithPredictions = [];
        foreach ($matches as $match) {
            try {
                // Utiliser le service de prédiction ultra-performant
                $ultraPrediction = $this->ultraPredictionService->predictBasketballMatch($match);

                // Adapter le format pour la compatibilité avec le template existant
                $matchesWithPredictions[] = [
                    'match' => $match,
                    'prediction' => [
                        'probabilities' => [
                            'home' => $ultraPrediction['prediction']['probabilities']['home'],
                            'away' => $ultraPrediction['prediction']['probabilities']['away'],
                        ],
                        'confidence' => $ultraPrediction['prediction']['confidence'],
                        'confidence_level' => $ultraPrediction['prediction']['confidence_level'],
                    ],
                    'total_points' => $ultraPrediction['total_points']['predictions'],
                    'home_avg' => $ultraPrediction['team_stats']['home']['avg_points'],
                    'away_avg' => $ultraPrediction['team_stats']['away']['avg_points'],
                    'home_stats' => $ultraPrediction['team_stats']['home'],
                    'away_stats' => $ultraPrediction['team_stats']['away'],
                    'data_quality' => $ultraPrediction['data_quality']['data_quality'] ?? 'estimated',
                    'analysis' => $ultraPrediction['analysis'],
                    'ultra_prediction' => $ultraPrediction,
                ];
            } catch (\Exception $e) {
                // Fallback vers l'ancienne méthode si erreur
                $homeStats = $this->statsCalculator->calculateBasketballStats($match->getHomeTeam());
                $awayStats = $this->statsCalculator->calculateBasketballStats($match->getAwayTeam());

                $homeAvgPoints = $homeStats['avg_points'];
                $awayAvgPoints = $awayStats['avg_points'];
                $homeStdDev = $homeStats['std_dev'] ?? 10.0;
                $awayStdDev = $awayStats['std_dev'] ?? 10.0;

                $resultPrediction = $this->basketballPredictionService->predictResult(
                    $homeAvgPoints,
                    $awayAvgPoints,
                    $homeStdDev,
                    $awayStdDev
                );
                $totalPointsPrediction = $this->basketballPredictionService->predictTotalPoints($homeAvgPoints, $awayAvgPoints);

                $matchesWithPredictions[] = [
                    'match' => $match,
                    'prediction' => $resultPrediction,
                    'total_points' => $totalPointsPrediction,
                    'home_avg' => $homeAvgPoints,
                    'away_avg' => $awayAvgPoints,
                    'home_stats' => $homeStats,
                    'away_stats' => $awayStats,
                    'data_quality' => $homeStats['data_quality'] ?? 'estimated',
                ];
            }
        }

        // Récupérer les matchs terminés récents avec vérification des prédictions
        $recentFinishedData = $this->predictionAccuracyService->getBasketballDailyMatchesWithAccuracy(
            new \DateTimeImmutable('yesterday')
        );
        $todayFinishedData = $this->predictionAccuracyService->getBasketballDailyMatchesWithAccuracy(
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
            'correct_results' => count(array_filter($recentFinishedMatches, fn ($m) => $m['result_prediction_correct'])),
            'correct_totals' => count(array_filter($recentFinishedMatches, fn ($m) => $m['total_prediction_correct'])),
        ];
        $accuracyStats['result_accuracy'] = $accuracyStats['total_finished'] > 0
            ? round(($accuracyStats['correct_results'] / $accuracyStats['total_finished']) * 100, 1)
            : 0;
        $accuracyStats['total_accuracy'] = $accuracyStats['total_finished'] > 0
            ? round(($accuracyStats['correct_totals'] / $accuracyStats['total_finished']) * 100, 1)
            : 0;

        return $this->render('basketball/predictions_today.html.twig', [
            'matches' => $matches,
            'matches_with_predictions' => $matchesWithPredictions,
            'total_count' => $this->basketballMatchRepository->countUpcomingMatches(),
            'recent_finished_matches' => $recentFinishedMatches,
            'accuracy_stats' => $accuracyStats,
        ]);
    }

    #[Route('/match/{id}/analysis', name: 'app_basketball_match_analysis')]
    public function matchAnalysis(int $id): Response
    {
        $match = $this->basketballMatchRepository->find($id);

        if (!$match) {
            throw $this->createNotFoundException('Match de basketball non trouvé');
        }

        // Analyse detaillee complete
        $detailedAnalysis = $this->detailedAnalysisService->analyzeMatch($match);

        // Calculer les vraies statistiques des équipes
        $homeStats = $this->statsCalculator->calculateBasketballStats($match->getHomeTeam());
        $awayStats = $this->statsCalculator->calculateBasketballStats($match->getAwayTeam());

        $homeAvgPoints = $homeStats['avg_points'];
        $awayAvgPoints = $awayStats['avg_points'];
        $homeStdDev = $homeStats['std_dev'] ?? 10.0;
        $awayStdDev = $awayStats['std_dev'] ?? 10.0;

        $resultPrediction = $this->basketballPredictionService->predictResult($homeAvgPoints, $awayAvgPoints, $homeStdDev, $awayStdDev);
        $handicapPrediction = $this->basketballPredictionService->predictHandicap($homeAvgPoints, $awayAvgPoints);
        $totalPointsPrediction = $this->basketballPredictionService->predictTotalPoints($homeAvgPoints, $awayAvgPoints);
        $quartersPrediction = $this->basketballPredictionService->predictQuarters($homeAvgPoints, $awayAvgPoints);

        return $this->render('basketball/match_analysis.html.twig', [
            'match' => $match,
            'detailed_analysis' => $detailedAnalysis,
            'result_prediction' => $resultPrediction,
            'handicap_prediction' => $handicapPrediction,
            'total_points' => $totalPointsPrediction,
            'quarters' => $quartersPrediction,
            'home_stats' => $homeStats,
            'away_stats' => $awayStats,
        ]);
    }

    /**
     * API endpoint pour l'analyse detaillee d'un match de basketball.
     */
    #[Route('/api/match/{id}/detailed-analysis', name: 'api_basketball_match_detailed', methods: ['GET'])]
    public function apiMatchDetailedAnalysis(int $id): JsonResponse
    {
        $match = $this->basketballMatchRepository->find($id);

        if (!$match) {
            return $this->json(['error' => 'Match non trouve'], 404);
        }

        $analysis = $this->detailedAnalysisService->analyzeMatch($match);

        return $this->json([
            'success' => true,
            'data' => $analysis,
        ]);
    }

    /**
     * Affiche tous les matchs du jour avec verification des predictions.
     */
    #[Route('/daily', name: 'app_basketball_daily')]
    public function dailyMatches(Request $request): Response
    {
        $dateStr = $request->query->get('date');
        $date = $dateStr ? new \DateTimeImmutable($dateStr) : new \DateTimeImmutable('today');

        $dailyData = $this->predictionAccuracyService->getBasketballDailyMatchesWithAccuracy($date);
        $overallStats = $this->predictionAccuracyService->getOverallAccuracyStats('basketball', 7);

        usort($dailyData['matches'], function ($a, $b) {
            $priorityA = $a['is_finished'] ? 0 : ($a['is_live'] ? 1 : 2);
            $priorityB = $b['is_finished'] ? 0 : ($b['is_live'] ? 1 : 2);

            if ($priorityA !== $priorityB) {
                return $priorityA - $priorityB;
            }

            return $a['match']->getMatchDate() <=> $b['match']->getMatchDate();
        });

        return $this->render('basketball/daily.html.twig', [
            'daily_data' => $dailyData,
            'overall_stats' => $overallStats,
            'current_date' => $date,
            'prev_date' => $date->modify('-1 day'),
            'next_date' => $date->modify('+1 day'),
        ]);
    }

    /**
     * API endpoint pour la validation croisée des prédictions basketball.
     */
    #[Route('/api/validation', name: 'api_basketball_validation', methods: ['GET'])]
    public function apiValidation(Request $request): JsonResponse
    {
        $days = $request->query->getInt('days', 30);

        $validation = $this->validationService->validatePredictions('basketball', $days);
        $kFold = $this->validationService->kFoldValidation('basketball', 5, $days);
        $comparison = $this->validationService->comparePerformance('basketball', 7, 30);

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
    #[Route('/api/learning-stats', name: 'api_basketball_learning', methods: ['GET'])]
    public function apiLearningStats(): JsonResponse
    {
        $learningAnalysis = $this->learningService->analyzeAndLearn('basketball');
        $adjustmentFactors = $this->learningService->getAdjustmentFactors('basketball');
        $errorHistory = $this->learningService->getErrorHistory('basketball', 7);

        return $this->json([
            'success' => true,
            'learning_analysis' => $learningAnalysis,
            'adjustment_factors' => $adjustmentFactors,
            'recent_errors' => $errorHistory,
        ]);
    }
}
