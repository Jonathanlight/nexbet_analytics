<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\BasketballMatchRepository;
use App\Repository\FootballMatchRepository;
use App\Repository\HockeyMatchRepository;
use App\Service\Betting\BettingStrategyService;
use App\Service\Betting\CombinationBuilder;
use App\Service\Data\TeamStatisticsCalculator;
use App\Service\Prediction\BasketballPredictionService;
use App\Service\Prediction\HockeyPredictionService;
use App\Service\Prediction\ResultPredictionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/betting')]
class BettingController extends AbstractController
{
    public function __construct(
        private readonly FootballMatchRepository $matchRepository,
        private readonly BasketballMatchRepository $basketballMatchRepository,
        private readonly HockeyMatchRepository $hockeyMatchRepository,
        private readonly ResultPredictionService $resultPredictionService,
        private readonly BasketballPredictionService $basketballPredictionService,
        private readonly HockeyPredictionService $hockeyPredictionService,
        private readonly TeamStatisticsCalculator $statsCalculator,
        private readonly CombinationBuilder $combinationBuilder,
        private readonly BettingStrategyService $bettingStrategyService,
    ) {
    }

    #[Route('/combinations', name: 'app_betting_combinations')]
    public function combinations(Request $request): Response
    {
        $sportFilter = $request->query->get('sport', 'all');

        $predictions = $this->getAllPredictions($sportFilter);

        $safeCombinations = $this->combinationBuilder->generateSafeCombinations($predictions, 5);
        $valueCombinations = $this->combinationBuilder->generateValueCombinations($predictions);
        $systems = $this->combinationBuilder->generateSystems($predictions);

        return $this->render('betting/combinations.html.twig', [
            'safe_combinations' => $safeCombinations,
            'value_combinations' => $valueCombinations,
            'systems' => $systems,
            'predictions' => $predictions,
            'current_sport' => $sportFilter,
            'last_update' => new \DateTimeImmutable(),
        ]);
    }

    /**
     * API endpoint pour rafraichir les combinaisons.
     */
    #[Route('/api/combinations/refresh', name: 'api_betting_combinations_refresh', methods: ['GET'])]
    public function refreshCombinations(Request $request): JsonResponse
    {
        $sportFilter = $request->query->get('sport', 'all');
        $predictions = $this->getAllPredictions($sportFilter);

        $safeCombinations = $this->combinationBuilder->generateSafeCombinations($predictions, 5);
        $valueCombinations = $this->combinationBuilder->generateValueCombinations($predictions);
        $systems = $this->combinationBuilder->generateSystems($predictions);

        return $this->json([
            'success' => true,
            'data' => [
                'predictions' => $predictions,
                'safe_combinations' => $safeCombinations,
                'value_combinations' => $valueCombinations,
                'systems' => $systems,
                'last_update' => (new \DateTimeImmutable())->format('H:i:s'),
            ],
        ]);
    }

    /**
     * Recupere toutes les predictions de tous les sports.
     */
    private function getAllPredictions(string $sportFilter = 'all'): array
    {
        $predictions = [];

        // Football
        if ('all' === $sportFilter || 'football' === $sportFilter) {
            $footballMatches = $this->matchRepository->findTodayMatches();
            foreach (array_slice($footballMatches, 0, 10) as $match) {
                $result = $this->resultPredictionService->predictResult($match);

                $odds = $match->getOddsArray();
                $matchOdds = 2.0;
                $allOdds = ['1' => 2.0, 'X' => 3.5, '2' => 4.0];

                if ($odds && isset($odds['1X2'])) {
                    $predictionKey = $result['prediction'];
                    $matchOdds = $odds['1X2'][$predictionKey] ?? 2.0;
                    $allOdds = [
                        '1' => $odds['1X2']['1'] ?? 2.0,
                        'X' => $odds['1X2']['X'] ?? 3.5,
                        '2' => $odds['1X2']['2'] ?? 4.0,
                    ];
                }

                $probability = $result['probabilities'][$result['prediction']];
                $impliedProbability = 1 / $matchOdds;

                $predictions[] = [
                    'match_id' => $match->getId(),
                    'sport' => 'football',
                    'sport_icon' => 'bi-circle-fill',
                    'sport_color' => 'success',
                    'home_team' => $match->getHomeTeam()->getName(),
                    'away_team' => $match->getAwayTeam()->getName(),
                    'league' => $match->getLeague(),
                    'match_date' => $match->getMatchDate(),
                    'prediction' => (string) $result['prediction'],
                    'prediction_label' => $this->getFootballPredictionLabel((string) $result['prediction'], $match->getHomeTeam()->getName(), $match->getAwayTeam()->getName()),
                    'probability' => $probability,
                    'confidence' => $result['confidence'],
                    'odds' => $matchOdds,
                    'all_odds' => $allOdds,
                    'is_safe_bet' => $result['confidence'] >= 75,
                    'is_value_bet' => ($probability / 100) > $impliedProbability,
                ];
            }
        }

        // Basketball
        if ('all' === $sportFilter || 'basketball' === $sportFilter) {
            $basketballMatches = $this->basketballMatchRepository->findTodayMatches();
            if (empty($basketballMatches)) {
                $basketballMatches = $this->basketballMatchRepository->findUpcomingMatches(10);
            }

            foreach (array_slice($basketballMatches, 0, 10) as $match) {
                $homeStats = $this->statsCalculator->calculateBasketballStats($match->getHomeTeam());
                $awayStats = $this->statsCalculator->calculateBasketballStats($match->getAwayTeam());

                $resultPrediction = $this->basketballPredictionService->predictResult(
                    $homeStats['avg_points'],
                    $awayStats['avg_points'],
                    $homeStats['std_dev'] ?? 10.0,
                    $awayStats['std_dev'] ?? 10.0
                );

                // Basketball utilise probabilities.home et probabilities.away
                $homeProb = $resultPrediction['probabilities']['home'] ?? 50;
                $awayProb = $resultPrediction['probabilities']['away'] ?? 50;

                $predictedWinner = $homeProb > $awayProb ? 'home' : 'away';
                $winnerProb = max($homeProb, $awayProb);
                $confidence = $resultPrediction['confidence'] ?? ($winnerProb > 60 ? 70 : 50);

                // Estimer les cotes basees sur les probabilites
                $homeOdds = round(100 / max(1, $homeProb), 2);
                $awayOdds = round(100 / max(1, $awayProb), 2);

                $predictions[] = [
                    'match_id' => $match->getId(),
                    'sport' => 'basketball',
                    'sport_icon' => 'bi-dribbble',
                    'sport_color' => 'warning',
                    'home_team' => $match->getHomeTeam()->getName(),
                    'away_team' => $match->getAwayTeam()->getName(),
                    'league' => $match->getLeague(),
                    'match_date' => $match->getMatchDate(),
                    'prediction' => $predictedWinner,
                    'prediction_label' => 'home' === $predictedWinner ? $match->getHomeTeam()->getName() : $match->getAwayTeam()->getName(),
                    'probability' => $winnerProb,
                    'confidence' => $confidence,
                    'odds' => 'home' === $predictedWinner ? $homeOdds : $awayOdds,
                    'all_odds' => [
                        'home' => $homeOdds,
                        'away' => $awayOdds,
                    ],
                    'is_safe_bet' => $confidence >= 70,
                    'is_value_bet' => $winnerProb > 55,
                ];
            }
        }

        // Hockey
        if ('all' === $sportFilter || 'hockey' === $sportFilter) {
            $hockeyMatches = $this->hockeyMatchRepository->findTodayMatches();
            if (empty($hockeyMatches)) {
                $hockeyMatches = $this->hockeyMatchRepository->findUpcomingMatches(10);
            }

            foreach (array_slice($hockeyMatches, 0, 10) as $match) {
                $homeStats = $this->statsCalculator->calculateHockeyStats($match->getHomeTeam());
                $awayStats = $this->statsCalculator->calculateHockeyStats($match->getAwayTeam());

                $resultPrediction = $this->hockeyPredictionService->predictResult(
                    $homeStats['avg_goals'],
                    $awayStats['avg_goals']
                );

                // Hockey utilise '1', 'X', '2' pour les probabilites
                $homeProb = $resultPrediction['probabilities']['1'];
                $awayProb = $resultPrediction['probabilities']['2'];
                $predictedWinner = $homeProb > $awayProb ? 'home' : 'away';
                $winnerProb = max($homeProb, $awayProb);
                $confidence = $resultPrediction['confidence'];

                $homeOdds = round(100 / max(1, $homeProb), 2);
                $awayOdds = round(100 / max(1, $awayProb), 2);

                $predictions[] = [
                    'match_id' => $match->getId(),
                    'sport' => 'hockey',
                    'sport_icon' => 'bi-disc',
                    'sport_color' => 'info',
                    'home_team' => $match->getHomeTeam()->getName(),
                    'away_team' => $match->getAwayTeam()->getName(),
                    'league' => $match->getLeague(),
                    'match_date' => $match->getMatchDate(),
                    'prediction' => $predictedWinner,
                    'prediction_label' => 'home' === $predictedWinner ? $match->getHomeTeam()->getName() : $match->getAwayTeam()->getName(),
                    'probability' => $winnerProb,
                    'confidence' => $confidence,
                    'odds' => 'home' === $predictedWinner ? $homeOdds : $awayOdds,
                    'all_odds' => [
                        'home' => $homeOdds,
                        'away' => $awayOdds,
                    ],
                    'is_safe_bet' => $confidence >= 65,
                    'is_value_bet' => $winnerProb > 55,
                ];
            }
        }

        // Trier par confiance decroissante
        usort($predictions, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);

        return $predictions;
    }

    private function getFootballPredictionLabel(string $prediction, string $homeTeam, string $awayTeam): string
    {
        return match ($prediction) {
            '1' => $homeTeam,
            '2' => $awayTeam,
            'X' => 'Match Nul',
            default => $prediction,
        };
    }

    #[Route('/strategies', name: 'app_betting_strategies')]
    public function strategies(): Response
    {
        $matches = $this->matchRepository->findTodayMatches();

        $bets = [];
        foreach (array_slice($matches, 0, 10) as $match) {
            $result = $this->resultPredictionService->predictResult($match);

            // Récupérer les vraies cotes
            $odds = $match->getOddsArray();
            $matchOdds = 2.0;
            if ($odds && isset($odds['1X2'])) {
                $predictionKey = $result['prediction'];
                $matchOdds = $odds['1X2'][$predictionKey] ?? 2.0;
            }

            // Calculer value bet
            $probability = $result['probabilities'][$result['prediction']];
            $impliedProbability = 1 / $matchOdds;
            $isValueBet = $probability > $impliedProbability;

            $bets[] = [
                'match' => $match,
                'prediction' => $result['prediction'],
                'probability' => $probability,
                'confidence' => $result['confidence'],
                'odds' => $matchOdds,
                'is_safe_bet' => $result['confidence'] >= 85,
                'is_value_bet' => $isValueBet,
                'expected_value' => ($probability * $matchOdds) - 1,
            ];
        }

        $bankroll = 1000; // Exemple - à récupérer depuis l'utilisateur ou session

        $strategies = $this->bettingStrategyService->generateStrategies($bankroll, $bets);

        return $this->render('betting/strategies.html.twig', [
            'strategies' => $strategies,
            'bankroll' => $bankroll,
            'bets' => $bets,
        ]);
    }

    #[Route('/bankroll', name: 'app_betting_bankroll')]
    public function bankrollManager(): Response
    {
        // Simuler des données de bankroll (à remplacer par une vraie gestion utilisateur)
        $initialBankroll = 1000;
        $currentBankroll = 1250;

        // Historique simulé des paris
        $betsHistory = [
            [
                'date' => new \DateTimeImmutable('-7 days'),
                'match' => 'PSG vs OM',
                'prediction' => '1',
                'stake' => 50,
                'odds' => 2.1,
                'result' => 'won',
                'profit' => 55,
            ],
            [
                'date' => new \DateTimeImmutable('-6 days'),
                'match' => 'Real Madrid vs Barcelona',
                'prediction' => 'X',
                'stake' => 30,
                'odds' => 3.5,
                'result' => 'lost',
                'profit' => -30,
            ],
            [
                'date' => new \DateTimeImmutable('-5 days'),
                'match' => 'Man City vs Liverpool',
                'prediction' => '1',
                'stake' => 40,
                'odds' => 1.8,
                'result' => 'won',
                'profit' => 32,
            ],
        ];

        // Calculer les statistiques
        $totalBets = count($betsHistory);
        $wonBets = count(array_filter($betsHistory, fn ($bet) => 'won' === $bet['result']));
        $lostBets = $totalBets - $wonBets;
        $totalStaked = array_sum(array_column($betsHistory, 'stake'));
        $totalProfit = array_sum(array_column($betsHistory, 'profit'));
        $roi = $totalStaked > 0 ? ($totalProfit / $totalStaked) * 100 : 0;

        // Évolution de la bankroll
        $bankrollEvolution = [
            ['date' => '-30d', 'amount' => 1000],
            ['date' => '-25d', 'amount' => 1050],
            ['date' => '-20d', 'amount' => 980],
            ['date' => '-15d', 'amount' => 1100],
            ['date' => '-10d', 'amount' => 1150],
            ['date' => '-5d', 'amount' => 1200],
            ['date' => 'today', 'amount' => 1250],
        ];

        // Stratégies de gestion recommandées
        $recommendedStrategies = [
            [
                'name' => 'Kelly Criterion',
                'description' => 'Mise optimale basée sur l\'avantage et les cotes',
                'risk_level' => 'Modéré',
            ],
            [
                'name' => 'Flat Betting',
                'description' => 'Mise fixe de 2-5% de la bankroll',
                'risk_level' => 'Faible',
            ],
            [
                'name' => 'Percentage Betting',
                'description' => 'Mise proportionnelle à la confiance',
                'risk_level' => 'Modéré',
            ],
        ];

        return $this->render('betting/bankroll_manager.html.twig', [
            'bankroll' => $currentBankroll,
            'initial_bankroll' => $initialBankroll,
            'growth' => $currentBankroll - $initialBankroll,
            'growth_percentage' => (($currentBankroll - $initialBankroll) / $initialBankroll) * 100,
            'total_bets' => $totalBets,
            'won_bets' => $wonBets,
            'lost_bets' => $lostBets,
            'win_rate' => $totalBets > 0 ? ($wonBets / $totalBets) * 100 : 0,
            'total_staked' => $totalStaked,
            'total_profit' => $totalProfit,
            'roi' => $roi,
            'bets_history' => $betsHistory,
            'bankroll_evolution' => $bankrollEvolution,
            'recommended_strategies' => $recommendedStrategies,
        ]);
    }
}
