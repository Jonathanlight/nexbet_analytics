<?php

declare(strict_types=1);

namespace App\Service\Analysis;

use App\Entity\BasketballMatch;
use App\Entity\FootballMatch;
use App\Entity\HockeyMatch;
use App\Repository\BasketballMatchRepository;
use App\Repository\FootballMatchRepository;
use App\Repository\HockeyMatchRepository;
use App\Service\Data\TeamStatisticsCalculator;
use App\Service\Prediction\BasketballPredictionService;
use App\Service\Prediction\HockeyPredictionService;
use App\Service\Prediction\ResultPredictionService;

/**
 * Service pour suivre et analyser la précision des prédictions.
 */
class PredictionAccuracyService
{
    public function __construct(
        private readonly FootballMatchRepository $footballMatchRepository,
        private readonly BasketballMatchRepository $basketballMatchRepository,
        private readonly HockeyMatchRepository $hockeyMatchRepository,
        private readonly ResultPredictionService $footballPredictionService,
        private readonly BasketballPredictionService $basketballPredictionService,
        private readonly HockeyPredictionService $hockeyPredictionService,
        private readonly TeamStatisticsCalculator $statsCalculator,
    ) {
    }

    /**
     * Récupère tous les matchs de football du jour avec leur statut de prédiction.
     */
    public function getFootballDailyMatchesWithAccuracy(?\DateTimeInterface $date = null): array
    {
        $date = $date ?? new \DateTimeImmutable('today');
        $matches = $this->footballMatchRepository->findByDate($date);

        $result = [
            'date' => $date,
            'matches' => [],
            'stats' => [
                'total' => 0,
                'finished' => 0,
                'upcoming' => 0,
                'live' => 0,
                'correct_predictions' => 0,
                'accuracy_rate' => 0,
            ],
        ];

        foreach ($matches as $match) {
            $matchData = $this->analyzeFootballMatch($match);
            $result['matches'][] = $matchData;

            ++$result['stats']['total'];

            if ($matchData['is_finished']) {
                ++$result['stats']['finished'];
                if ($matchData['prediction_correct']) {
                    ++$result['stats']['correct_predictions'];
                }
            } elseif ($matchData['is_live']) {
                ++$result['stats']['live'];
            } else {
                ++$result['stats']['upcoming'];
            }
        }

        // Calculer le taux de précision
        if ($result['stats']['finished'] > 0) {
            $result['stats']['accuracy_rate'] = round(
                ($result['stats']['correct_predictions'] / $result['stats']['finished']) * 100,
                1
            );
        }

        return $result;
    }

    /**
     * Analyse un match de football avec sa prédiction.
     */
    private function analyzeFootballMatch(FootballMatch $match): array
    {
        $prediction = $this->footballPredictionService->predictResult($match);

        // Utiliser les méthodes de l'enum MatchStatus si disponible
        $status = $match->getStatus();
        if ($status instanceof \App\Enum\MatchStatus) {
            $isFinished = $status->isFinished();
            $isLive = $status->isLive();
        } else {
            // Fallback pour les chaînes de caractères
            $isFinished = in_array($status, ['Match Finished', 'finished', 'FT'], true);
            $isLive = in_array($status, ['live', 'First Half', 'Second Half', 'Halftime', 'HT'], true);
        }

        $actualResult = null;
        $predictionCorrect = null;

        if ($isFinished) {
            $homeScore = $match->getHomeScore();
            $awayScore = $match->getAwayScore();

            if (null !== $homeScore && null !== $awayScore) {
                if ($homeScore > $awayScore) {
                    $actualResult = '1';
                } elseif ($homeScore < $awayScore) {
                    $actualResult = '2';
                } else {
                    $actualResult = 'X';
                }

                $predictionCorrect = ($prediction['prediction'] === $actualResult);
            }
        }

        return [
            'match' => $match,
            'prediction' => $prediction,
            'predicted_result' => $prediction['prediction'],
            'predicted_probability' => $prediction['probabilities'][$prediction['prediction']] ?? 0,
            'confidence' => $prediction['confidence'],
            'is_finished' => $isFinished,
            'is_live' => $isLive,
            'is_upcoming' => !$isFinished && !$isLive,
            'actual_result' => $actualResult,
            'home_score' => $match->getHomeScore(),
            'away_score' => $match->getAwayScore(),
            'prediction_correct' => $predictionCorrect,
            'status' => $match->getStatus(),
        ];
    }

    /**
     * Récupère tous les matchs de basketball du jour avec leur statut de prédiction.
     */
    public function getBasketballDailyMatchesWithAccuracy(?\DateTimeInterface $date = null): array
    {
        $date = $date ?? new \DateTimeImmutable('today');
        $matches = $this->basketballMatchRepository->findByDate($date);

        $result = [
            'date' => $date,
            'matches' => [],
            'stats' => [
                'total' => 0,
                'finished' => 0,
                'upcoming' => 0,
                'live' => 0,
                'correct_result_predictions' => 0,
                'correct_total_predictions' => 0,
                'result_accuracy_rate' => 0,
                'total_accuracy_rate' => 0,
            ],
        ];

        foreach ($matches as $match) {
            $matchData = $this->analyzeBasketballMatch($match);
            $result['matches'][] = $matchData;

            ++$result['stats']['total'];

            if ($matchData['is_finished']) {
                ++$result['stats']['finished'];
                if ($matchData['result_prediction_correct']) {
                    ++$result['stats']['correct_result_predictions'];
                }
                if ($matchData['total_prediction_correct']) {
                    ++$result['stats']['correct_total_predictions'];
                }
            } elseif ($matchData['is_live']) {
                ++$result['stats']['live'];
            } else {
                ++$result['stats']['upcoming'];
            }
        }

        // Calculer les taux de précision
        if ($result['stats']['finished'] > 0) {
            $result['stats']['result_accuracy_rate'] = round(
                ($result['stats']['correct_result_predictions'] / $result['stats']['finished']) * 100,
                1
            );
            $result['stats']['total_accuracy_rate'] = round(
                ($result['stats']['correct_total_predictions'] / $result['stats']['finished']) * 100,
                1
            );
        }

        return $result;
    }

    /**
     * Analyse un match de basketball avec sa prédiction.
     */
    private function analyzeBasketballMatch(BasketballMatch $match): array
    {
        // Calculer les statistiques des équipes
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
        $totalPrediction = $this->basketballPredictionService->predictTotalPoints($homeAvgPoints, $awayAvgPoints);

        $isFinished = in_array($match->getStatus(), ['Match Finished', 'finished', 'FT'], true);
        $isLive = in_array($match->getStatus(), ['live', 'Q1', 'Q2', 'Q3', 'Q4', 'HT', 'OT'], true);

        $actualWinner = null;
        $actualTotal = null;
        $resultCorrect = null;
        $totalCorrect = null;

        // Basketball utilise probabilities.home et probabilities.away
        $homeProb = $resultPrediction['probabilities']['home'] ?? 50;
        $awayProb = $resultPrediction['probabilities']['away'] ?? 50;

        if ($isFinished) {
            $homeScore = $match->getHomeFinalScore();
            $awayScore = $match->getAwayFinalScore();

            if (null !== $homeScore && null !== $awayScore) {
                $actualWinner = $homeScore > $awayScore ? 'home' : 'away';
                $actualTotal = $homeScore + $awayScore;

                // Vérifier la prédiction du gagnant
                $predictedWinner = $homeProb > $awayProb ? 'home' : 'away';
                $resultCorrect = ($predictedWinner === $actualWinner);

                // Vérifier la prédiction du total (over/under)
                $predictedTotal = $totalPrediction['expected_total'];
                $totalLine = $totalPrediction['over_under_line'] ?? 210.5;
                $predictedOver = $predictedTotal > $totalLine;
                $actualOver = $actualTotal > $totalLine;
                $totalCorrect = ($predictedOver === $actualOver);
            }
        }

        return [
            'match' => $match,
            'result_prediction' => $resultPrediction,
            'total_prediction' => $totalPrediction,
            'home_stats' => $homeStats,
            'away_stats' => $awayStats,
            'predicted_winner' => $homeProb > $awayProb ? 'home' : 'away',
            'predicted_total' => $totalPrediction['expected_total'],
            'is_finished' => $isFinished,
            'is_live' => $isLive,
            'is_upcoming' => !$isFinished && !$isLive,
            'actual_winner' => $actualWinner,
            'actual_total' => $actualTotal,
            'home_score' => $match->getHomeFinalScore(),
            'away_score' => $match->getAwayFinalScore(),
            'result_prediction_correct' => $resultCorrect,
            'total_prediction_correct' => $totalCorrect,
            'status' => $match->getStatus(),
            'data_quality' => $homeStats['data_quality'] ?? 'estimated',
        ];
    }

    /**
     * Récupère tous les matchs de hockey du jour avec leur statut de prédiction.
     */
    public function getHockeyDailyMatchesWithAccuracy(?\DateTimeInterface $date = null): array
    {
        $date = $date ?? new \DateTimeImmutable('today');
        $matches = $this->hockeyMatchRepository->findByDate($date);

        $result = [
            'date' => $date,
            'matches' => [],
            'stats' => [
                'total' => 0,
                'finished' => 0,
                'upcoming' => 0,
                'live' => 0,
                'correct_predictions' => 0,
                'accuracy_rate' => 0,
            ],
        ];

        foreach ($matches as $match) {
            $matchData = $this->analyzeHockeyMatch($match);
            $result['matches'][] = $matchData;

            ++$result['stats']['total'];

            if ($matchData['is_finished']) {
                ++$result['stats']['finished'];
                if ($matchData['prediction_correct']) {
                    ++$result['stats']['correct_predictions'];
                }
            } elseif ($matchData['is_live']) {
                ++$result['stats']['live'];
            } else {
                ++$result['stats']['upcoming'];
            }
        }

        if ($result['stats']['finished'] > 0) {
            $result['stats']['accuracy_rate'] = round(
                ($result['stats']['correct_predictions'] / $result['stats']['finished']) * 100,
                1
            );
        }

        return $result;
    }

    /**
     * Analyse un match de hockey avec sa prédiction.
     */
    private function analyzeHockeyMatch(HockeyMatch $match): array
    {
        $homeStats = $this->statsCalculator->calculateHockeyStats($match->getHomeTeam());
        $awayStats = $this->statsCalculator->calculateHockeyStats($match->getAwayTeam());

        $homeAvgGoals = $homeStats['avg_goals'];
        $awayAvgGoals = $awayStats['avg_goals'];

        $resultPrediction = $this->hockeyPredictionService->predictResult($homeAvgGoals, $awayAvgGoals);
        $totalPrediction = $this->hockeyPredictionService->predictTotalGoals($homeAvgGoals, $awayAvgGoals);

        $isFinished = in_array($match->getStatus(), ['Match Finished', 'finished', 'FT'], true);
        $isLive = in_array($match->getStatus(), ['live', 'P1', 'P2', 'P3', 'OT'], true);

        // Hockey utilise probabilities.1 et probabilities.2
        $homeProb = $resultPrediction['probabilities']['1'] ?? 50;
        $awayProb = $resultPrediction['probabilities']['2'] ?? 50;

        $actualWinner = null;
        $predictionCorrect = null;

        if ($isFinished) {
            $homeScore = $match->getHomeFinalScore();
            $awayScore = $match->getAwayFinalScore();

            if (null !== $homeScore && null !== $awayScore) {
                $actualWinner = $homeScore > $awayScore ? 'home' : ($homeScore < $awayScore ? 'away' : 'draw');
                $predictedWinner = $homeProb > $awayProb ? 'home' : 'away';
                $predictionCorrect = ($predictedWinner === $actualWinner);
            }
        }

        return [
            'match' => $match,
            'result_prediction' => $resultPrediction,
            'total_prediction' => $totalPrediction,
            'home_stats' => $homeStats,
            'away_stats' => $awayStats,
            'predicted_winner' => $homeProb > $awayProb ? 'home' : 'away',
            'is_finished' => $isFinished,
            'is_live' => $isLive,
            'is_upcoming' => !$isFinished && !$isLive,
            'actual_winner' => $actualWinner,
            'home_score' => $match->getHomeFinalScore(),
            'away_score' => $match->getAwayFinalScore(),
            'prediction_correct' => $predictionCorrect,
            'status' => $match->getStatus(),
            'data_quality' => $homeStats['data_quality'] ?? 'estimated',
        ];
    }

    /**
     * Récupère les statistiques de précision globales pour une période.
     */
    public function getOverallAccuracyStats(string $sport, int $days = 7): array
    {
        $stats = [
            'sport' => $sport,
            'period_days' => $days,
            'total_matches' => 0,
            'finished_matches' => 0,
            'correct_predictions' => 0,
            'accuracy_rate' => 0,
            'by_confidence' => [
                'high' => ['total' => 0, 'correct' => 0, 'rate' => 0],
                'medium' => ['total' => 0, 'correct' => 0, 'rate' => 0],
                'low' => ['total' => 0, 'correct' => 0, 'rate' => 0],
            ],
        ];

        $startDate = new \DateTimeImmutable("-{$days} days");
        $endDate = new \DateTimeImmutable('today');

        $currentDate = $startDate;
        while ($currentDate <= $endDate) {
            $dailyData = match ($sport) {
                'basketball' => $this->getBasketballDailyMatchesWithAccuracy($currentDate),
                'hockey' => $this->getHockeyDailyMatchesWithAccuracy($currentDate),
                default => $this->getFootballDailyMatchesWithAccuracy($currentDate),
            };

            $stats['total_matches'] += $dailyData['stats']['total'];
            $stats['finished_matches'] += $dailyData['stats']['finished'];

            if ('basketball' === $sport) {
                $stats['correct_predictions'] += $dailyData['stats']['correct_result_predictions'];
            } else {
                $stats['correct_predictions'] += $dailyData['stats']['correct_predictions'];
            }

            // Analyser par niveau de confiance
            foreach ($dailyData['matches'] as $matchData) {
                if (!$matchData['is_finished']) {
                    continue;
                }

                $confidence = $matchData['confidence'] ?? 50;
                $confidenceLevel = $confidence >= 70 ? 'high' : ($confidence >= 50 ? 'medium' : 'low');

                ++$stats['by_confidence'][$confidenceLevel]['total'];

                $isCorrect = $matchData['prediction_correct'] ?? $matchData['result_prediction_correct'] ?? false;
                if ($isCorrect) {
                    ++$stats['by_confidence'][$confidenceLevel]['correct'];
                }
            }

            $currentDate = $currentDate->modify('+1 day');
        }

        // Calculer les taux
        if ($stats['finished_matches'] > 0) {
            $stats['accuracy_rate'] = round(
                ($stats['correct_predictions'] / $stats['finished_matches']) * 100,
                1
            );
        }

        foreach (['high', 'medium', 'low'] as $level) {
            if ($stats['by_confidence'][$level]['total'] > 0) {
                $stats['by_confidence'][$level]['rate'] = round(
                    ($stats['by_confidence'][$level]['correct'] / $stats['by_confidence'][$level]['total']) * 100,
                    1
                );
            }
        }

        return $stats;
    }
}
