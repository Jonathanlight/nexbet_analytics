<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\FootballMatchRepository;
use App\Service\Prediction\ResultPredictionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly FootballMatchRepository $matchRepository,
        private readonly ResultPredictionService $resultPredictionService,
    ) {
    }

    #[Route('/', name: 'app_dashboard')]
    public function index(): Response
    {
        $todayMatches = $this->matchRepository->findTodayMatches();
        $safeBets = $this->matchRepository->findSafeBetsToday();

        $stats = [
            'total_matches_today' => count($todayMatches),
            'safe_bets' => count($safeBets),
            'high_confidence' => count($this->matchRepository->findMatchesWithHighConfidencePredictions(85.0)),
        ];

        return $this->render('dashboard/index.html.twig', [
            'matches' => array_slice($todayMatches, 0, 10),
            'safe_bets' => $safeBets,
            'stats' => $stats,
        ]);
    }

    #[Route('/matches/today', name: 'app_matches_today')]
    public function todayMatches(): Response
    {
        $matches = $this->matchRepository->findTodayMatches();

        return $this->render('dashboard/today_matches.html.twig', [
            'matches' => $matches,
        ]);
    }

    #[Route('/stats', name: 'app_stats')]
    public function stats(): Response
    {
        // TODO: Implémenter les statistiques globales

        return $this->render('dashboard/stats.html.twig', [
            'stats' => [],
        ]);
    }
}
