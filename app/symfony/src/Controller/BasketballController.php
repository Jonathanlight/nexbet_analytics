<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\BasketballMatchRepository;
use App\Service\Prediction\BasketballPredictionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/basketball')]
class BasketballController extends AbstractController
{
    public function __construct(
        private readonly BasketballMatchRepository $basketballMatchRepository,
        private readonly BasketballPredictionService $basketballPredictionService,
    ) {
    }

    #[Route('/predictions/today', name: 'app_basketball_predictions_today')]
    public function predictionsToday(): Response
    {
        $matches = $this->basketballMatchRepository->findBy(
            ['matchDate' => new \DateTimeImmutable('today')],
            ['matchDate' => 'ASC']
        );

        return $this->render('basketball/predictions_today.html.twig', [
            'matches' => $matches,
        ]);
    }

    #[Route('/match/{id}/analysis', name: 'app_basketball_match_analysis')]
    public function matchAnalysis(int $id): Response
    {
        $match = $this->basketballMatchRepository->find($id);

        if (!$match) {
            throw $this->createNotFoundException('Match de basketball non trouvé');
        }

        // Récupérer les moyennes de points depuis les stats des équipes
        $homeStats = $match->getHomeStats();
        $awayStats = $match->getAwayStats();

        $homeAvgPoints = $homeStats['avg_points'] ?? 105.0;
        $awayAvgPoints = $awayStats['avg_points'] ?? 105.0;

        $resultPrediction = $this->basketballPredictionService->predictResult($homeAvgPoints, $awayAvgPoints);
        $handicapPrediction = $this->basketballPredictionService->predictHandicap($homeAvgPoints, $awayAvgPoints);
        $totalPointsPrediction = $this->basketballPredictionService->predictTotalPoints($homeAvgPoints, $awayAvgPoints);
        $quartersPrediction = $this->basketballPredictionService->predictQuarters($homeAvgPoints, $awayAvgPoints);

        return $this->render('basketball/match_analysis.html.twig', [
            'match' => $match,
            'result_prediction' => $resultPrediction,
            'handicap_prediction' => $handicapPrediction,
            'total_points' => $totalPointsPrediction,
            'quarters' => $quartersPrediction,
        ]);
    }
}
