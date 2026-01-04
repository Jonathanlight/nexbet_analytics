<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\HockeyMatchRepository;
use App\Service\AI\PredictionLearningService;
use App\Service\Analysis\CrossValidationService;
use App\Service\Analysis\PredictionAccuracyService;
use App\Service\Data\TeamStatisticsCalculator;
use App\Service\Prediction\HockeyPredictionService;
use App\Service\Prediction\UltraPredictionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/hockey')]
class HockeyController extends AbstractController
{
    public function __construct(
        private readonly HockeyMatchRepository $hockeyMatchRepository,
        private readonly HockeyPredictionService $hockeyPredictionService,
        private readonly TeamStatisticsCalculator $statsCalculator,
        private readonly PredictionAccuracyService $predictionAccuracyService,
        private readonly UltraPredictionService $ultraPredictionService,
        private readonly PredictionLearningService $learningService,
        private readonly CrossValidationService $validationService,
    ) {
    }

    #[Route('/predictions/today', name: 'app_hockey_predictions_today')]
    public function predictionsToday(): Response
    {
        // Requete optimisee avec eager loading
        $matches = $this->hockeyMatchRepository->findTodayMatches();

        // Si pas de matchs aujourd'hui, chercher les prochains matchs
        if (empty($matches)) {
            $matches = $this->hockeyMatchRepository->findUpcomingMatches(20);
        }

        // Construire les predictions pour chaque match
        $matchesWithPredictions = [];
        foreach ($matches as $match) {
            // Calculer les vraies statistiques des équipes
            $homeStats = $this->statsCalculator->calculateHockeyStats($match->getHomeTeam());
            $awayStats = $this->statsCalculator->calculateHockeyStats($match->getAwayTeam());

            $homeAvgGoals = $homeStats['avg_goals'];
            $awayAvgGoals = $awayStats['avg_goals'];

            try {
                $resultPrediction = $this->hockeyPredictionService->predictResult($homeAvgGoals, $awayAvgGoals);
                $totalGoalsPrediction = $this->hockeyPredictionService->predictTotalGoals($homeAvgGoals, $awayAvgGoals);

                $matchesWithPredictions[] = [
                    'match' => $match,
                    'prediction' => $resultPrediction,
                    'total_goals' => $totalGoalsPrediction,
                    'home_avg' => $homeAvgGoals,
                    'away_avg' => $awayAvgGoals,
                    'home_stats' => $homeStats,
                    'away_stats' => $awayStats,
                    'data_quality' => $homeStats['data_quality'] ?? 'estimated',
                ];
            } catch (\Exception $e) {
                continue;
            }
        }

        // Récupérer les matchs terminés récents avec vérification des prédictions
        $recentFinishedData = $this->predictionAccuracyService->getHockeyDailyMatchesWithAccuracy(
            new \DateTimeImmutable('yesterday')
        );
        $todayFinishedData = $this->predictionAccuracyService->getHockeyDailyMatchesWithAccuracy(
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

        return $this->render('hockey/predictions_today.html.twig', [
            'matches' => $matches,
            'matches_with_predictions' => $matchesWithPredictions,
            'total_count' => $this->hockeyMatchRepository->countUpcomingMatches(),
            'recent_finished_matches' => $recentFinishedMatches,
            'accuracy_stats' => $accuracyStats,
        ]);
    }

    #[Route('/match/{id}/analysis', name: 'app_hockey_match_analysis')]
    public function matchAnalysis(int $id): Response
    {
        $match = $this->hockeyMatchRepository->find($id);

        if (!$match) {
            throw $this->createNotFoundException('Match de hockey non trouve');
        }

        // Calculer les vraies statistiques des équipes
        $homeStats = $this->statsCalculator->calculateHockeyStats($match->getHomeTeam());
        $awayStats = $this->statsCalculator->calculateHockeyStats($match->getAwayTeam());

        $homeAvgGoals = $homeStats['avg_goals'];
        $awayAvgGoals = $awayStats['avg_goals'];

        // Predictions
        $resultPrediction = $this->hockeyPredictionService->predictResult($homeAvgGoals, $awayAvgGoals);
        $handicapPrediction = $this->hockeyPredictionService->predictHandicap($homeAvgGoals, $awayAvgGoals);
        $totalGoalsPrediction = $this->hockeyPredictionService->predictTotalGoals($homeAvgGoals, $awayAvgGoals);
        $periodsPrediction = $this->hockeyPredictionService->predictPeriods($homeAvgGoals, $awayAvgGoals);
        $overtimePrediction = $this->hockeyPredictionService->predictOvertime($homeAvgGoals, $awayAvgGoals);

        return $this->render('hockey/match_analysis.html.twig', [
            'match' => $match,
            'result_prediction' => $resultPrediction,
            'handicap_prediction' => $handicapPrediction,
            'total_goals' => $totalGoalsPrediction,
            'periods' => $periodsPrediction,
            'overtime' => $overtimePrediction,
            'home_stats' => $homeStats,
            'away_stats' => $awayStats,
        ]);
    }

    /**
     * API endpoint pour l'analyse detaillee d'un match de hockey.
     */
    #[Route('/api/match/{id}/detailed-analysis', name: 'api_hockey_match_detailed', methods: ['GET'])]
    public function apiMatchDetailedAnalysis(int $id): JsonResponse
    {
        $match = $this->hockeyMatchRepository->find($id);

        if (!$match) {
            return $this->json(['error' => 'Match non trouve'], 404);
        }

        // Calculer les vraies statistiques des équipes
        $homeStats = $this->statsCalculator->calculateHockeyStats($match->getHomeTeam());
        $awayStats = $this->statsCalculator->calculateHockeyStats($match->getAwayTeam());

        $homeAvgGoals = $homeStats['avg_goals'];
        $awayAvgGoals = $awayStats['avg_goals'];

        $analysis = [
            'match_info' => [
                'id' => $match->getId(),
                'home_team' => $match->getHomeTeam()->getName(),
                'away_team' => $match->getAwayTeam()->getName(),
                'league' => $match->getLeague(),
                'match_date' => $match->getMatchDate()->format('Y-m-d H:i'),
                'venue' => $match->getVenue(),
            ],
            'team_stats' => [
                'home' => $homeStats,
                'away' => $awayStats,
            ],
            'result_prediction' => $this->hockeyPredictionService->predictResult($homeAvgGoals, $awayAvgGoals),
            'total_goals' => $this->hockeyPredictionService->predictTotalGoals($homeAvgGoals, $awayAvgGoals),
            'handicap' => $this->hockeyPredictionService->predictHandicap($homeAvgGoals, $awayAvgGoals),
            'periods' => $this->hockeyPredictionService->predictPeriods($homeAvgGoals, $awayAvgGoals),
            'overtime' => $this->hockeyPredictionService->predictOvertime($homeAvgGoals, $awayAvgGoals),
        ];

        return $this->json([
            'success' => true,
            'data' => $analysis,
        ]);
    }

    /**
     * Affiche tous les matchs du jour avec verification des predictions.
     */
    #[Route('/daily', name: 'app_hockey_daily')]
    public function dailyMatches(Request $request): Response
    {
        $dateStr = $request->query->get('date');
        $date = $dateStr ? new \DateTimeImmutable($dateStr) : new \DateTimeImmutable('today');

        $dailyData = $this->predictionAccuracyService->getHockeyDailyMatchesWithAccuracy($date);
        $overallStats = $this->predictionAccuracyService->getOverallAccuracyStats('hockey', 7);

        usort($dailyData['matches'], function ($a, $b) {
            $priorityA = $a['is_finished'] ? 0 : ($a['is_live'] ? 1 : 2);
            $priorityB = $b['is_finished'] ? 0 : ($b['is_live'] ? 1 : 2);

            if ($priorityA !== $priorityB) {
                return $priorityA - $priorityB;
            }

            return $a['match']->getMatchDate() <=> $b['match']->getMatchDate();
        });

        return $this->render('hockey/daily.html.twig', [
            'daily_data' => $dailyData,
            'overall_stats' => $overallStats,
            'current_date' => $date,
            'prev_date' => $date->modify('-1 day'),
            'next_date' => $date->modify('+1 day'),
        ]);
    }

    /**
     * API endpoint pour la validation croisée des prédictions hockey.
     */
    #[Route('/api/validation', name: 'api_hockey_validation', methods: ['GET'])]
    public function apiValidation(Request $request): JsonResponse
    {
        $days = $request->query->getInt('days', 30);

        // Appliquer l'apprentissage avant validation
        $this->learningService->analyzeAndLearn('hockey');

        $validation = $this->validationService->validatePredictions('hockey', $days);
        $kFold = $this->validationService->kFoldValidation('hockey', 5, $days);
        $comparison = $this->validationService->comparePerformance('hockey', 7, 30);

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
    #[Route('/api/learning-stats', name: 'api_hockey_learning', methods: ['GET'])]
    public function apiLearningStats(): JsonResponse
    {
        $learningAnalysis = $this->learningService->analyzeAndLearn('hockey');
        $adjustmentFactors = $this->learningService->getAdjustmentFactors('hockey');
        $errorHistory = $this->learningService->getErrorHistory('hockey', 7);

        return $this->json([
            'success' => true,
            'learning_analysis' => $learningAnalysis,
            'adjustment_factors' => $adjustmentFactors,
            'recent_errors' => $errorHistory,
        ]);
    }

    /**
     * API endpoint pour la prédiction ultra-optimisée d'un match hockey.
     */
    #[Route('/api/match/{id}/ultra-prediction', name: 'api_hockey_ultra_prediction', methods: ['GET'])]
    public function apiUltraPrediction(int $id): JsonResponse
    {
        $match = $this->hockeyMatchRepository->find($id);

        if (!$match) {
            return $this->json(['error' => 'Match non trouve'], 404);
        }

        try {
            $prediction = $this->ultraPredictionService->predictHockeyMatch($match);

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
}
