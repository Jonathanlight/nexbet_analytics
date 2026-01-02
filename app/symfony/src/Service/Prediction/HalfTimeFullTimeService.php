<?php

declare(strict_types=1);

namespace App\Service\Prediction;

use App\Entity\FootballMatch;
use App\Repository\FootballMatchRepository;

/**
 * Service de prédiction Mi-Temps / Fin de Match.
 */
class HalfTimeFullTimeService
{
    private const HT_FT_COMBINATIONS = [
        '1/1', '1/X', '1/2',
        'X/1', 'X/X', 'X/2',
        '2/1', '2/X', '2/2',
    ];

    public function __construct(
        private readonly FootballMatchRepository $matchRepository,
        private readonly ConfidenceCalculator $confidenceCalculator,
        private readonly ResultPredictionService $resultPredictionService,
    ) {
    }

    /**
     * Prédit toutes les combinaisons HT/FT.
     */
    public function predictHalfTimeFullTime(FootballMatch $match): array
    {
        $homeTeam = $match->getHomeTeam();
        $awayTeam = $match->getAwayTeam();

        // Analyse historique
        $homeMatches = $this->matchRepository->findRecentMatchesByTeam($homeTeam, 10);
        $awayMatches = $this->matchRepository->findRecentMatchesByTeam($awayTeam, 10);

        $homeHTFTStats = $this->analyzeHTFTPattern($homeMatches, true);
        $awayHTFTStats = $this->analyzeHTFTPattern($awayMatches, false);

        // Prédiction du résultat final
        $fullTimePrediction = $this->resultPredictionService->predictResult($match);
        $ftProbabilities = $fullTimePrediction['probabilities'];

        // Calculer les probabilités HT/FT
        $htftPredictions = [];

        foreach (self::HT_FT_COMBINATIONS as $combination) {
            [$ht, $ft] = explode('/', $combination);

            $probability = $this->calculateHTFTProbability(
                $ht,
                $ft,
                $ftProbabilities,
                $homeHTFTStats,
                $awayHTFTStats
            );

            $htftPredictions[$combination] = [
                'probability' => $probability,
                'confidence' => $this->confidenceCalculator->calculateBetTypeConfidence('HT_FT', 70.0),
            ];
        }

        // Trier par probabilité décroissante
        uasort($htftPredictions, fn ($a, $b) => $b['probability'] <=> $a['probability']);

        return [
            'predictions' => $htftPredictions,
            'most_likely' => array_key_first($htftPredictions),
            'statistics' => [
                'home' => $homeHTFTStats,
                'away' => $awayHTFTStats,
            ],
        ];
    }

    /**
     * Prédit le résultat à la mi-temps.
     */
    public function predictHalfTime(FootballMatch $match): array
    {
        $homeTeam = $match->getHomeTeam();
        $awayTeam = $match->getAwayTeam();

        $homeMatches = $this->matchRepository->findRecentMatchesByTeam($homeTeam, 10);
        $awayMatches = $this->matchRepository->findRecentMatchesByTeam($awayTeam, 10);

        $homeHTStats = $this->analyzeHalfTimeResults($homeMatches, true);
        $awayHTStats = $this->analyzeHalfTimeResults($awayMatches, false);

        // Moyenne pondérée
        $htPrediction = [
            '1' => ($homeHTStats['wins'] * 0.6) + ($awayHTStats['losses'] * 0.4),
            'X' => ($homeHTStats['draws'] * 0.6) + ($awayHTStats['draws'] * 0.4),
            '2' => ($homeHTStats['losses'] * 0.6) + ($awayHTStats['wins'] * 0.4),
        ];

        // Normaliser
        $total = array_sum($htPrediction);
        foreach ($htPrediction as &$prob) {
            $prob = round(($prob / $total) * 100, 2);
        }

        return [
            'probabilities' => $htPrediction,
            'confidence' => $this->confidenceCalculator->calculateBetTypeConfidence('HT', 75.0),
        ];
    }

    /**
     * Analyse les patterns HT/FT d'une équipe.
     */
    private function analyzeHTFTPattern(array $matches, bool $isHomeTeam): array
    {
        $patterns = array_fill_keys(self::HT_FT_COMBINATIONS, 0);
        $total = 0;

        foreach ($matches as $match) {
            $htResult = $match->getHalfTimeResult();
            $ftResult = $match->getResult();

            if ($htResult && $ftResult) {
                // Ajuster selon la perspective de l'équipe
                if (!$isHomeTeam) {
                    $htResult = $this->invertResult($htResult);
                    $ftResult = $this->invertResult($ftResult);
                }

                $pattern = "{$htResult}/{$ftResult}";
                if (isset($patterns[$pattern])) {
                    ++$patterns[$pattern];
                    ++$total;
                }
            }
        }

        // Convertir en pourcentages
        if ($total > 0) {
            foreach ($patterns as &$count) {
                $count = round(($count / $total) * 100, 2);
            }
        }

        return $patterns;
    }

    /**
     * Analyse les résultats à la mi-temps.
     */
    private function analyzeHalfTimeResults(array $matches, bool $isHomeTeam): array
    {
        $stats = ['wins' => 0, 'draws' => 0, 'losses' => 0];
        $total = 0;

        foreach ($matches as $match) {
            $htResult = $match->getHalfTimeResult();

            if ($htResult) {
                if (!$isHomeTeam) {
                    $htResult = $this->invertResult($htResult);
                }

                if ('1' === $htResult) {
                    ++$stats['wins'];
                } elseif ('X' === $htResult) {
                    ++$stats['draws'];
                } else {
                    ++$stats['losses'];
                }
                ++$total;
            }
        }

        // Convertir en pourcentages
        if ($total > 0) {
            $stats['wins'] = round(($stats['wins'] / $total) * 100, 2);
            $stats['draws'] = round(($stats['draws'] / $total) * 100, 2);
            $stats['losses'] = round(($stats['losses'] / $total) * 100, 2);
        }

        return $stats;
    }

    /**
     * Calcule la probabilité d'une combinaison HT/FT.
     */
    private function calculateHTFTProbability(
        string $ht,
        string $ft,
        array $ftProbabilities,
        array $homeStats,
        array $awayStats,
    ): float {
        $combination = "{$ht}/{$ft}";

        // Basé sur les statistiques historiques
        $historical = ($homeStats[$combination] ?? 0) * 0.5 + ($awayStats[$combination] ?? 0) * 0.5;

        // Basé sur la probabilité FT
        $ftProb = $ftProbabilities[$ft] ?? 0;

        // Probabilités conditionnelles typiques HT -> FT
        $conditionalProbs = $this->getConditionalProbabilities();
        $conditional = $conditionalProbs[$combination] ?? 0;

        // Moyenne pondérée
        $probability = ($historical * 0.4) + ($ftProb * $conditional * 0.6);

        return round($probability, 2);
    }

    /**
     * Retourne les probabilités conditionnelles typiques.
     */
    private function getConditionalProbabilities(): array
    {
        return [
            '1/1' => 0.75,  // Si 1 à la MT, forte chance de 1 au FT
            '1/X' => 0.15,
            '1/2' => 0.10,
            'X/1' => 0.35,
            'X/X' => 0.40,
            'X/2' => 0.25,
            '2/1' => 0.10,
            '2/X' => 0.15,
            '2/2' => 0.75,
        ];
    }

    /**
     * Inverse un résultat (1 devient 2, 2 devient 1).
     */
    private function invertResult(string $result): string
    {
        return match ($result) {
            '1' => '2',
            '2' => '1',
            default => $result,
        };
    }
}
