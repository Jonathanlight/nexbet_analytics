<?php

declare(strict_types=1);

namespace App\Service\Math;

use App\Entity\Team;
use App\Service\Math\Interface\EloServiceInterface;

/**
 * Service de calcul du rating Elo pour évaluer la force des équipes.
 * Le système Elo est adapté du système d'échecs pour le football.
 */
class EloService implements EloServiceInterface
{
    private const DEFAULT_RATING = 1500;
    private const K_FACTOR = 40; // Facteur K - détermine la vitesse de changement
    private const HOME_ADVANTAGE = 65; // Avantage du terrain RÉDUIT (était 100)

    /**
     * Calcule la probabilité de victoire selon le rating Elo.
     */
    public function calculateWinProbability(float $ratingA, float $ratingB): float
    {
        return 1 / (1 + pow(10, ($ratingB - $ratingA) / 400));
    }

    /**
     * Calcule les probabilités de résultat (1, X, 2) basées sur Elo.
     */
    public function calculateResultProbabilities(Team $homeTeam, Team $awayTeam): array
    {
        $homeRating = $homeTeam->getEloRating();
        $awayRating = $awayTeam->getEloRating();

        // Détecter si on a des ratings réels ou par défaut
        $hasHomeRating = null !== $homeRating;
        $hasAwayRating = null !== $awayRating;
        $hasRealData = $hasHomeRating && $hasAwayRating;

        $homeRating = $homeRating ?? self::DEFAULT_RATING;
        $awayRating = $awayRating ?? self::DEFAULT_RATING;

        // Réduire l'avantage domicile si on n'a pas de données réelles
        // Car on ne peut pas être sûr de la force relative des équipes
        $homeAdvantage = $hasRealData ? self::HOME_ADVANTAGE : (self::HOME_ADVANTAGE * 0.5);

        // Ajouter l'avantage du terrain
        $adjustedHomeRating = $homeRating + $homeAdvantage;

        // Calculer la probabilité de victoire à domicile
        $homeWinProb = $this->calculateWinProbability($adjustedHomeRating, $awayRating);
        $awayWinProb = 1 - $homeWinProb;

        // Estimation du match nul (formule empirique améliorée)
        $ratingDiff = abs($adjustedHomeRating - $awayRating);

        // Base de nul plus élevée, surtout sans données
        $baseDrawProb = $hasRealData ? 0.26 : 0.30;
        $drawProb = $baseDrawProb - ($ratingDiff / 2500);

        // Si les deux équipes ont le rating par défaut, le nul est plus probable
        if (!$hasHomeRating && !$hasAwayRating) {
            $drawProb = 0.32; // ~32% de nul quand on ne sait rien
        }

        $drawProb = max(0.08, min(0.38, $drawProb)); // Entre 8% et 38%

        // Ajuster les probabilités de victoire
        $homeWinProb = $homeWinProb * (1 - $drawProb);
        $awayWinProb = $awayWinProb * (1 - $drawProb);

        // Normaliser pour que la somme soit 100%
        $total = $homeWinProb + $drawProb + $awayWinProb;

        return [
            '1' => round(($homeWinProb / $total) * 100, 2),
            'X' => round(($drawProb / $total) * 100, 2),
            '2' => round(($awayWinProb / $total) * 100, 2),
        ];
    }

    /**
     * Met à jour les ratings Elo après un match.
     *
     * @param Team $homeTeam  Équipe à domicile
     * @param Team $awayTeam  Équipe à l'extérieur
     * @param int  $homeScore Score de l'équipe à domicile
     * @param int  $awayScore Score de l'équipe à l'extérieur
     */
    public function updateRatings(Team $homeTeam, Team $awayTeam, int $homeScore, int $awayScore): array
    {
        $homeRating = $homeTeam->getEloRating() ?? self::DEFAULT_RATING;
        $awayRating = $awayTeam->getEloRating() ?? self::DEFAULT_RATING;

        // Calculer les résultats attendus
        $homeExpected = $this->calculateWinProbability($homeRating + self::HOME_ADVANTAGE, $awayRating);
        $awayExpected = 1 - $homeExpected;

        // Déterminer les résultats réels
        if ($homeScore > $awayScore) {
            $homeActual = 1.0;
            $awayActual = 0.0;
        } elseif ($homeScore < $awayScore) {
            $homeActual = 0.0;
            $awayActual = 1.0;
        } else {
            $homeActual = 0.5;
            $awayActual = 0.5;
        }

        // Calculer les nouveaux ratings
        $newHomeRating = $homeRating + self::K_FACTOR * ($homeActual - $homeExpected);
        $newAwayRating = $awayRating + self::K_FACTOR * ($awayActual - $awayExpected);

        return [
            'home' => round($newHomeRating, 2),
            'away' => round($newAwayRating, 2),
            'home_change' => round($newHomeRating - $homeRating, 2),
            'away_change' => round($newAwayRating - $awayRating, 2),
        ];
    }

    /**
     * Calcule l'écart de force entre deux équipes.
     */
    public function calculateStrengthDifference(Team $team1, Team $team2, bool $team1Home = false): float
    {
        $rating1 = $team1->getEloRating() ?? self::DEFAULT_RATING;
        $rating2 = $team2->getEloRating() ?? self::DEFAULT_RATING;

        if ($team1Home) {
            $rating1 += self::HOME_ADVANTAGE;
        }

        return $rating1 - $rating2;
    }

    /**
     * Calcule le rating Elo initial basé sur les performances récentes.
     */
    public function calculateInitialRating(int $wins, int $draws, int $losses, float $leagueStrength = 1.0): float
    {
        $totalMatches = $wins + $draws + $losses;

        if (0 === $totalMatches) {
            return self::DEFAULT_RATING;
        }

        $winPercentage = ($wins + ($draws * 0.5)) / $totalMatches;

        // Ajuster selon la performance
        $adjustment = ($winPercentage - 0.5) * 400;
        $baseRating = self::DEFAULT_RATING + $adjustment;

        // Ajuster selon la force de la ligue
        return round($baseRating * $leagueStrength, 2);
    }

    /**
     * Prédit le score le plus probable basé sur Elo et historique.
     */
    public function predictMostLikelyScore(
        Team $homeTeam,
        Team $awayTeam,
        float $homeAvgGoals,
        float $awayAvgGoals,
    ): array {
        $homeRating = $homeTeam->getEloRating() ?? self::DEFAULT_RATING;
        $awayRating = $awayTeam->getEloRating() ?? self::DEFAULT_RATING;

        $ratingDiff = ($homeRating + self::HOME_ADVANTAGE) - $awayRating;

        // Ajuster les moyennes de buts selon la différence Elo
        $adjustmentFactor = $ratingDiff / 400;

        $predictedHomeGoals = $homeAvgGoals * (1 + ($adjustmentFactor * 0.2));
        $predictedAwayGoals = $awayAvgGoals * (1 - ($adjustmentFactor * 0.2));

        // Arrondir au nombre entier le plus proche
        $homeGoals = round($predictedHomeGoals);
        $awayGoals = round($predictedAwayGoals);

        return [
            'home_goals' => max(0, $homeGoals),
            'away_goals' => max(0, $awayGoals),
            'score' => max(0, $homeGoals).'-'.max(0, $awayGoals),
            'confidence' => $this->calculateConfidence($ratingDiff),
        ];
    }

    /**
     * Calcule le niveau de confiance basé sur la différence de rating.
     */
    private function calculateConfidence(float $ratingDiff): float
    {
        $absDiff = abs($ratingDiff);

        // Plus la différence est grande, plus la confiance est élevée
        if ($absDiff > 300) {
            return 90.0;
        } elseif ($absDiff > 200) {
            return 80.0;
        } elseif ($absDiff > 100) {
            return 70.0;
        } elseif ($absDiff > 50) {
            return 60.0;
        }

        return 50.0;
    }
}
