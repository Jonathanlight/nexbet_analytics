<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\FootballMatchRepository;
use App\Service\Betting\BettingStrategyService;
use App\Service\Betting\CombinationBuilder;
use App\Service\Prediction\ResultPredictionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/betting')]
class BettingController extends AbstractController
{
    public function __construct(
        private readonly FootballMatchRepository $matchRepository,
        private readonly ResultPredictionService $resultPredictionService,
        private readonly CombinationBuilder $combinationBuilder,
        private readonly BettingStrategyService $bettingStrategyService,
    ) {
    }

    #[Route('/combinations', name: 'app_betting_combinations')]
    public function combinations(): Response
    {
        $matches = $this->matchRepository->findTodayMatches();

        $predictions = [];
        foreach (array_slice($matches, 0, 10) as $match) {
            $result = $this->resultPredictionService->predictResult($match);

            $predictions[] = [
                'match' => $match->getId(),
                'home_team' => $match->getHomeTeam()->getName(),
                'away_team' => $match->getAwayTeam()->getName(),
                'prediction' => $result['prediction'],
                'probability' => $result['probabilities'][$result['prediction']],
                'confidence' => $result['confidence'],
                'odds' => 2.0, // Exemple - à remplacer par les vraies cotes
                'is_safe_bet' => $result['confidence'] >= 85,
                'is_value_bet' => false, // À calculer avec les vraies cotes
            ];
        }

        $safeCombinations = $this->combinationBuilder->generateSafeCombinations($predictions, 5);
        $valueCombinations = $this->combinationBuilder->generateValueCombinations($predictions);
        $systems = $this->combinationBuilder->generateSystems($predictions);

        return $this->render('betting/combinations.html.twig', [
            'safe_combinations' => $safeCombinations,
            'value_combinations' => $valueCombinations,
            'systems' => $systems,
        ]);
    }

    #[Route('/strategies', name: 'app_betting_strategies')]
    public function strategies(): Response
    {
        $matches = $this->matchRepository->findTodayMatches();

        $bets = [];
        foreach (array_slice($matches, 0, 10) as $match) {
            $result = $this->resultPredictionService->predictResult($match);

            $bets[] = [
                'match' => $match,
                'prediction' => $result['prediction'],
                'probability' => $result['probabilities'][$result['prediction']],
                'confidence' => $result['confidence'],
                'odds' => 2.0, // Exemple
                'is_safe_bet' => $result['confidence'] >= 85,
                'is_value_bet' => false,
            ];
        }

        $bankroll = 1000; // Exemple - à récupérer depuis l'utilisateur

        $strategies = $this->bettingStrategyService->generateStrategies($bankroll, $bets);

        return $this->render('betting/strategies.html.twig', [
            'strategies' => $strategies,
            'bankroll' => $bankroll,
        ]);
    }

    #[Route('/bankroll', name: 'app_betting_bankroll')]
    public function bankrollManager(): Response
    {
        // TODO: Implémenter la gestion complète de la bankroll

        return $this->render('betting/bankroll_manager.html.twig', [
            'bankroll' => 1000,
            'initial_bankroll' => 1000,
            'growth' => 0,
        ]);
    }
}
