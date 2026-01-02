<?php

declare(strict_types=1);

namespace App\Service\Prediction;

use App\Entity\FootballMatch;
use App\Entity\Player;
use App\Repository\PlayerRepository;

/**
 * Service de prédiction des buteurs.
 */
class ScorerPredictionService
{
    public function __construct(
        private readonly PlayerRepository $playerRepository,
        private readonly ConfidenceCalculator $confidenceCalculator,
    ) {
    }

    /**
     * Prédit les buteurs potentiels pour un match.
     */
    public function predictScorers(FootballMatch $match, int $limit = 10): array
    {
        $homeTeam = $match->getHomeTeam();
        $awayTeam = $match->getAwayTeam();

        $homeScorers = $this->getTeamTopScorers($homeTeam, $limit);
        $awayScorers = $this->getTeamTopScorers($awayTeam, $limit);

        $allScorers = array_merge($homeScorers, $awayScorers);

        // Trier par probabilité décroissante
        usort($allScorers, fn ($a, $b) => $b['probability'] <=> $a['probability']);

        return array_slice($allScorers, 0, $limit);
    }

    /**
     * Prédit le premier buteur.
     */
    public function predictFirstScorer(FootballMatch $match): array
    {
        $scorers = $this->predictScorers($match, 5);

        if (empty($scorers)) {
            return [
                'player' => null,
                'probability' => 0,
                'confidence' => 0,
            ];
        }

        $topScorer = $scorers[0];

        return [
            'player' => $topScorer['player'],
            'probability' => $topScorer['probability'],
            'confidence' => $this->confidenceCalculator->calculateBetTypeConfidence('scorer', 65.0),
            'alternatives' => array_slice($scorers, 1, 4),
        ];
    }

    /**
     * Prédit les joueurs qui marqueront à tout moment.
     */
    public function predictAnytimeScorers(FootballMatch $match, float $minProbability = 20.0): array
    {
        $scorers = $this->predictScorers($match, 15);

        return array_filter($scorers, fn ($scorer) => $scorer['probability'] >= $minProbability);
    }

    /**
     * Prédit les joueurs susceptibles de marquer 2+ buts.
     */
    public function predictMultipleScorers(FootballMatch $match): array
    {
        $scorers = $this->predictScorers($match, 10);

        $multipleScorers = [];

        foreach ($scorers as $scorer) {
            // Probabilité de marquer 2+ buts basée sur la probabilité initiale
            $p = $scorer['probability'] / 100;
            $multipleGoalsProbability = $p * $p * 100; // Simplification

            if ($multipleGoalsProbability >= 5.0) {
                $multipleScorers[] = [
                    'player' => $scorer['player'],
                    'probability' => round($multipleGoalsProbability, 2),
                    'confidence' => $this->confidenceCalculator->calculateBetTypeConfidence('scorer', 55.0),
                ];
            }
        }

        return $multipleScorers;
    }

    /**
     * Récupère les meilleurs buteurs d'une équipe avec probabilités.
     */
    private function getTeamTopScorers(object $team, int $limit): array
    {
        $players = $this->playerRepository->findTopScorers($team, $limit);
        $scorers = [];

        foreach ($players as $player) {
            if (!$player->isAvailable()) {
                continue; // Skip blessés/suspendus
            }

            $probability = $this->calculateScoringProbability($player);

            $scorers[] = [
                'player' => $player->getName(),
                'team' => $team->getName(),
                'position' => $player->getPosition(),
                'goals_season' => $player->getGoalsScored(),
                'probability' => $probability,
            ];
        }

        return $scorers;
    }

    /**
     * Calcule la probabilité qu'un joueur marque.
     */
    private function calculateScoringProbability(Player $player): float
    {
        $goalsScored = $player->getGoalsScored() ?? 0;
        $matchesPlayed = $player->getMatchesPlayed() ?? 1;

        // Taux de buts par match
        $goalRate = $goalsScored / max($matchesPlayed, 1);

        // Utiliser xG si disponible
        $xG = $player->getXG() ?? $goalRate;

        // Facteur de position
        $positionFactors = [
            'FW' => 1.2,
            'MF' => 0.8,
            'DF' => 0.3,
            'GK' => 0.01,
        ];

        $positionFactor = $positionFactors[$player->getPosition()] ?? 0.5;

        // Probabilité basée sur Poisson
        $lambda = $xG * $positionFactor;
        $probability = (1 - exp(-$lambda)) * 100;

        return min(95, round($probability, 2));
    }
}
