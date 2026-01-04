<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\FootballMatchRepository;
use App\Service\Cache\DashboardCacheService;
use App\Service\Data\MatchSyncService;
use App\Service\Prediction\GoalsPredictionService;
use App\Service\Prediction\ResultPredictionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly FootballMatchRepository $matchRepository,
        private readonly ResultPredictionService $resultPredictionService,
        private readonly GoalsPredictionService $goalsPredictionService,
        private readonly MatchSyncService $matchSyncService,
        private readonly DashboardCacheService $dashboardCache,
    ) {
    }

    #[Route('/dashboard', name: 'app_dashboard_authenticated')]
    public function index(): Response
    {
        // NE PAS synchroniser a chaque chargement - utiliser le cache
        // La sync est faite via cron ou manuellement

        // Recuperer les donnees du cache (rapide)
        $stats = $this->dashboardCache->getDashboardStats();

        // Recuperer seulement les matchs serialises (pas d'entites lourdes)
        $todayMatches = $this->matchRepository->findTodayMatches();

        // Reponse avec cache HTTP (navigateur)
        $response = $this->render('dashboard/index.html.twig', [
            'matches' => array_slice($todayMatches, 0, 10),
            'stats' => $stats,
            'load_async' => true, // Flag pour charger le reste en AJAX
        ]);

        // Cache HTTP de 2 minutes
        $response->setSharedMaxAge(120);
        $response->headers->addCacheControlDirective('must-revalidate', true);

        return $response;
    }

    /**
     * API endpoint pour charger les predictions en AJAX (leger).
     */
    #[Route('/api/dashboard/predictions', name: 'api_dashboard_predictions', methods: ['GET'])]
    public function apiPredictions(Request $request): JsonResponse
    {
        $limit = $request->query->getInt('limit', 5);
        $limit = min($limit, 20); // Max 20

        $predictions = $this->dashboardCache->getQuickPredictions($limit);

        return $this->json([
            'success' => true,
            'data' => $predictions,
            'cached' => true,
        ]);
    }

    /**
     * API endpoint pour les statistiques (cache long).
     */
    #[Route('/api/dashboard/stats', name: 'api_dashboard_stats', methods: ['GET'])]
    public function apiStats(): JsonResponse
    {
        $stats = $this->dashboardCache->getDashboardStats();

        return $this->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * API endpoint pour synchroniser les matchs (manuel).
     */
    #[Route('/api/dashboard/sync', name: 'api_dashboard_sync', methods: ['POST'])]
    public function apiSync(): JsonResponse
    {
        try {
            $syncStats = $this->matchSyncService->syncTodayMatches();

            // Invalider le cache apres sync
            $this->dashboardCache->invalidateDashboardCache();

            return $this->json([
                'success' => true,
                'data' => $syncStats,
                'message' => 'Synchronisation terminee',
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/matches/today', name: 'app_matches_today')]
    public function todayMatches(): Response
    {
        // Utiliser le cache au lieu de recalculer
        $matchesWithPredictions = $this->dashboardCache->getTodayMatchesWithPredictions(50);

        $response = $this->render('dashboard/matches_today.html.twig', [
            'matches' => $matchesWithPredictions,
        ]);

        $response->setSharedMaxAge(180); // 3 minutes

        return $response;
    }

    #[Route('/stats', name: 'app_stats')]
    public function stats(): Response
    {
        // Utiliser le cache pour les stats globales
        $globalStats = $this->dashboardCache->getGlobalStats();

        // ROI simule (a remplacer par de vraies donnees)
        $roiData = [
            'current_bankroll' => 1250,
            'initial_bankroll' => 1000,
            'roi_percentage' => 25.0,
            'total_bets' => 45,
            'winning_bets' => 28,
            'losing_bets' => 17,
        ];

        $statistics = array_merge($globalStats, ['roi' => $roiData]);

        $response = $this->render('dashboard/stats.html.twig', [
            'stats' => $statistics,
        ]);

        $response->setSharedMaxAge(300); // 5 minutes

        return $response;
    }

    /**
     * API pour charger les matchs par page avec analyses completes (pagination AJAX).
     */
    #[Route('/api/matches', name: 'api_matches', methods: ['GET'])]
    public function apiMatches(Request $request): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $limit = min($limit, 50);

        $offset = ($page - 1) * $limit;

        $totalMatches = $this->matchRepository->countUpcomingMatches();
        $matches = $this->matchRepository->findUpcomingMatches($limit + $offset);
        $matches = array_slice($matches, $offset, $limit);

        $result = [];
        foreach ($matches as $match) {
            try {
                $prediction = $this->resultPredictionService->predictResult($match);
                $goalsPrediction = $this->goalsPredictionService->predictOverUnder($match);
                $btts = $this->goalsPredictionService->predictBTTS($match);

                // Recuperer les cotes
                $oddsArray = $match->getOddsArray();
                $odds1X2 = $oddsArray['1X2'] ?? [];
                $oddsOU = $oddsArray['OU2.5'] ?? $oddsArray['over_under'] ?? [];

                // Calculer le safe score
                $maxProb = max($prediction['probabilities']);
                $probs = $prediction['probabilities'];
                arsort($probs);
                $probValues = array_values($probs);
                $gap = $probValues[0] - ($probValues[1] ?? 0);
                $safeScore = min(100, ($maxProb * 0.5) + ($prediction['confidence'] * 0.3) + ($gap * 0.4));

                $result[] = [
                    'id' => $match->getId(),
                    'home_team' => $match->getHomeTeam()->getName(),
                    'away_team' => $match->getAwayTeam()->getName(),
                    'league' => $match->getLeague(),
                    'match_date' => $match->getMatchDate()->format('Y-m-d H:i'),
                    'match_time' => $match->getMatchDate()->format('H:i'),
                    'prediction' => $prediction['prediction'],
                    'confidence' => round($prediction['confidence'], 1),
                    'probabilities' => $prediction['probabilities'],
                    'safe_score' => round($safeScore, 1),
                    'odds' => [
                        'home' => $odds1X2['home'] ?? $odds1X2['1'] ?? null,
                        'draw' => $odds1X2['draw'] ?? $odds1X2['X'] ?? null,
                        'away' => $odds1X2['away'] ?? $odds1X2['2'] ?? null,
                    ],
                    'has_odds' => !empty($odds1X2),
                    'goals' => [
                        'over_25' => $goalsPrediction['OU2.5']['over'] ?? null,
                        'under_25' => $goalsPrediction['OU2.5']['under'] ?? null,
                        'over_15' => $goalsPrediction['OU1.5']['over'] ?? null,
                        'under_15' => $goalsPrediction['OU1.5']['under'] ?? null,
                    ],
                    'btts' => [
                        'yes' => $btts['yes'] ?? null,
                        'no' => $btts['no'] ?? null,
                    ],
                    'odds_ou' => [
                        'over_25' => $oddsOU['over'] ?? null,
                        'under_25' => $oddsOU['under'] ?? null,
                    ],
                ];
            } catch (\Exception $e) {
                continue;
            }
        }

        return $this->json([
            'success' => true,
            'data' => $result,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalMatches,
                'pages' => ceil($totalMatches / $limit),
                'has_more' => ($page * $limit) < $totalMatches,
            ],
        ]);
    }

    /**
     * Endpoint pour invalider le cache (admin).
     */
    #[Route('/api/cache/clear', name: 'api_cache_clear', methods: ['POST'])]
    public function clearCache(): JsonResponse
    {
        $this->dashboardCache->invalidateAllCache();

        return $this->json([
            'success' => true,
            'message' => 'Cache invalide',
        ]);
    }
}
