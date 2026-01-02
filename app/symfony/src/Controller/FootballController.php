<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\FootballMatchRepository;
use App\Repository\TeamRepository;
use App\Service\Betting\ValueBetDetector;
use App\Service\Prediction\GoalsPredictionService;
use App\Service\Prediction\HalfTimeFullTimeService;
use App\Service\Prediction\ResultPredictionService;
use App\Service\Prediction\ScorerPredictionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    ) {
    }

    #[Route('/predictions/today', name: 'app_football_predictions_today')]
    public function predictionsToday(): Response
    {
        $matches = $this->matchRepository->findTodayMatches();

        $predictions = [];
        foreach ($matches as $match) {
            $predictions[] = [
                'match' => $match,
                'result' => $this->resultPredictionService->predictResult($match),
            ];
        }

        return $this->render('football/predictions_today.html.twig', [
            'predictions' => $predictions,
        ]);
    }

    #[Route('/match/{id}/analysis', name: 'app_football_match_analysis')]
    public function matchAnalysis(int $id): Response
    {
        $match = $this->matchRepository->find($id);

        if (!$match) {
            throw $this->createNotFoundException('Match non trouvé');
        }

        // Prédictions complètes
        $resultPrediction = $this->resultPredictionService->predictResult($match);
        $goalsPrediction = $this->goalsPredictionService->predictOverUnder($match);
        $bttsPrediction = $this->goalsPredictionService->predictBTTS($match);
        $exactScorePrediction = $this->goalsPredictionService->predictExactScore($match);
        $scorersPrediction = $this->scorerPredictionService->predictScorers($match);
        $htftPrediction = $this->halfTimeFullTimeService->predictHalfTimeFullTime($match);
        $h2hAnalysis = $this->resultPredictionService->analyzeHeadToHead($match);

        return $this->render('football/match_analysis.html.twig', [
            'match' => $match,
            'result_prediction' => $resultPrediction,
            'goals_prediction' => $goalsPrediction,
            'btts_prediction' => $bttsPrediction,
            'exact_score' => $exactScorePrediction,
            'scorers' => $scorersPrediction,
            'htft' => $htftPrediction,
            'h2h' => $h2hAnalysis,
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
            // TODO: Récupérer les cotes réelles et comparer
            // Pour l'instant, utiliser des cotes fictives

            $predictions = $this->resultPredictionService->predictResult($match);

            // Exemple avec cotes fictives
            $fakeOdds = [
                '1X2_1' => 2.0,
                '1X2_X' => 3.5,
                '1X2_2' => 4.0,
            ];

            $bets = $this->valueBetDetector->findValueBets(
                [
                    '1X2_1' => ['probability' => $predictions['probabilities']['1']],
                    '1X2_X' => ['probability' => $predictions['probabilities']['X']],
                    '1X2_2' => ['probability' => $predictions['probabilities']['2']],
                ],
                $fakeOdds
            );

            if (!empty($bets)) {
                $valueBets[] = [
                    'match' => $match,
                    'value_bets' => $bets,
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
}
