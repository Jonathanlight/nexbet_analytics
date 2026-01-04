<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\FootballMatchRepository;
use App\Service\Data\MatchSyncService;
use App\Service\Prediction\ResultPredictionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class ApiController extends AbstractController
{
    public function __construct(
        private readonly FootballMatchRepository $matchRepository,
        private readonly ResultPredictionService $predictionService,
        private readonly MatchSyncService $matchSyncService,
    ) {
    }

    #[Route('/matches/today', name: 'api_matches_today', methods: ['GET'])]
    public function getTodayMatches(): JsonResponse
    {
        $matches = $this->matchRepository->findTodayMatches();

        $data = array_map(function ($match) {
            return [
                'id' => $match->getId(),
                'league' => $match->getLeague(),
                'home_team' => $match->getHomeTeam()->getName(),
                'away_team' => $match->getAwayTeam()->getName(),
                'match_date' => $match->getMatchDate()->format('Y-m-d H:i:s'),
                'status' => $match->getStatus()->value,
                'home_score' => $match->getHomeScore(),
                'away_score' => $match->getAwayScore(),
            ];
        }, $matches);

        return $this->json([
            'success' => true,
            'count' => count($data),
            'matches' => $data,
        ]);
    }

    #[Route('/matches/{id}', name: 'api_match_details', methods: ['GET'])]
    public function getMatchDetails(int $id): JsonResponse
    {
        $match = $this->matchRepository->find($id);

        if (!$match) {
            return $this->json(['error' => 'Match not found'], 404);
        }

        $prediction = $this->predictionService->predictResult($match);

        return $this->json([
            'success' => true,
            'match' => [
                'id' => $match->getId(),
                'league' => $match->getLeague(),
                'home_team' => $match->getHomeTeam()->getName(),
                'away_team' => $match->getAwayTeam()->getName(),
                'match_date' => $match->getMatchDate()->format('Y-m-d H:i:s'),
                'status' => $match->getStatus()->value,
                'home_score' => $match->getHomeScore(),
                'away_score' => $match->getAwayScore(),
                'venue' => $match->getVenue(),
            ],
            'prediction' => $prediction,
        ]);
    }

    #[Route('/matches/sync', name: 'api_matches_sync', methods: ['POST'])]
    public function syncMatches(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $date = $data['date'] ?? null;

        try {
            if ($date) {
                $dateTime = new \DateTimeImmutable($date);
                $stats = $this->matchSyncService->syncMatchesByDate($dateTime);
            } else {
                $stats = $this->matchSyncService->syncTodayMatches();
            }

            return $this->json([
                'success' => true,
                'message' => 'Matches synchronized successfully',
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/stats/dashboard', name: 'api_stats_dashboard', methods: ['GET'])]
    public function getDashboardStats(): JsonResponse
    {
        $todayMatches = $this->matchRepository->findTodayMatches();
        $safeBets = $this->matchRepository->findSafeBetsToday();

        // Calculer les value bets
        $valueBetsCount = 0;
        foreach ($todayMatches as $match) {
            $odds = $match->getOddsArray();
            if (!empty($odds)) {
                $prediction = $this->predictionService->predictResult($match);
                if ($prediction['confidence'] > 70) {
                    ++$valueBetsCount;
                }
            }
        }

        return $this->json([
            'success' => true,
            'stats' => [
                'total_matches_today' => count($todayMatches),
                'safe_bets' => count($safeBets),
                'high_confidence' => count($this->matchRepository->findMatchesWithHighConfidencePredictions(85.0)),
                'value_bets' => $valueBetsCount,
            ],
        ]);
    }

    #[Route('/matches/live', name: 'api_matches_live', methods: ['GET'])]
    public function getLiveMatches(): JsonResponse
    {
        // Récupérer les matchs en cours
        $liveMatches = $this->matchRepository->findBy([
            'status' => ['live', 'First Half', 'Second Half', 'Halftime'],
        ], ['matchDate' => 'ASC']);

        $data = array_map(function ($match) {
            return [
                'id' => $match->getId(),
                'league' => $match->getLeague(),
                'home_team' => $match->getHomeTeam()->getName(),
                'away_team' => $match->getAwayTeam()->getName(),
                'status' => $match->getStatus()->value,
                'home_score' => $match->getHomeScore(),
                'away_score' => $match->getAwayScore(),
                'minute' => $this->estimateMatchMinute($match),
            ];
        }, $liveMatches);

        return $this->json([
            'success' => true,
            'count' => count($data),
            'matches' => $data,
        ]);
    }

    private function estimateMatchMinute($match): ?int
    {
        $status = $match->getStatus()->value;
        $matchDate = $match->getMatchDate();
        $now = new \DateTimeImmutable();

        if (!in_array($status, ['live', 'First Half', 'Second Half', 'Halftime'])) {
            return null;
        }

        $diff = $now->getTimestamp() - $matchDate->getTimestamp();
        $minutes = (int) ($diff / 60);

        return min($minutes, 90);
    }
}
