<?php

declare(strict_types=1);

namespace App\Service\Math;

use App\Entity\Team;

/**
 * Service de calcul des Expected Goals (xG).
 * Les xG représentent la qualité des occasions de but.
 */
class XGService
{
    /**
     * Calcule les xG attendus pour une équipe basés sur ses statistiques.
     */
    public function calculateExpectedGoals(
        float $shots,
        float $shotsOnTarget,
        float $bigChances,
        float $possession,
        float $passAccuracy,
    ): float {
        // Modèle simplifié de xG
        $shotQuality = $shotsOnTarget / max($shots, 1);
        $chanceQuality = $bigChances / max($shots, 1);

        $xG = (
            ($shotsOnTarget * 0.3) +
            ($bigChances * 0.8) +
            ($possession * 0.01) +
            ($passAccuracy * 0.02)
        ) * $shotQuality * (1 + $chanceQuality);

        return round($xG, 2);
    }

    /**
     * Calcule les xG basés sur les performances récentes.
     */
    public function calculateFromRecentForm(Team $team, array $recentMatches): float
    {
        if (empty($recentMatches)) {
            return 1.0; // Valeur par défaut
        }

        $totalXG = 0;
        $count = 0;

        foreach ($recentMatches as $match) {
            $isHome = $match['is_home'] ?? true;
            $xg = $isHome ? ($match['home_xg'] ?? 0) : ($match['away_xg'] ?? 0);

            $totalXG += $xg;
            ++$count;
        }

        return $count > 0 ? round($totalXG / $count, 2) : 1.0;
    }

    /**
     * Calcule la probabilité de marquer basée sur xG.
     */
    public function calculateScoringProbability(float $xG): float
    {
        // Utilise une fonction logistique pour convertir xG en probabilité
        $probability = 1 - exp(-$xG);

        return round($probability * 100, 2);
    }

    /**
     * Calcule les xG ajustés en fonction de l'adversaire.
     */
    public function calculateAdjustedXG(
        float $teamXG,
        float $opponentDefensiveStrength,
        float $leagueAverage = 1.5,
    ): float {
        // Ajustement basé sur la force défensive de l'adversaire
        $adjustment = $leagueAverage / max($opponentDefensiveStrength, 0.5);

        return round($teamXG * $adjustment, 2);
    }

    /**
     * Prédit le score basé sur les xG.
     */
    public function predictScoreFromXG(float $homeXG, float $awayXG): array
    {
        // Convertir xG en scores probables
        $homeScore = round($homeXG);
        $awayScore = round($awayXG);

        $scoreProbability = $this->calculateScoreProbability($homeXG, $awayXG, $homeScore, $awayScore);

        return [
            'home_score' => max(0, $homeScore),
            'away_score' => max(0, $awayScore),
            'score' => max(0, $homeScore).'-'.max(0, $awayScore),
            'probability' => $scoreProbability,
        ];
    }

    /**
     * Calcule la probabilité d'un score spécifique basé sur xG.
     */
    private function calculateScoreProbability(float $homeXG, float $awayXG, int $homeScore, int $awayScore): float
    {
        // Utilise une distribution de Poisson simplifiée
        $homeProbability = $this->poissonProbability($homeXG, $homeScore);
        $awayProbability = $this->poissonProbability($awayXG, $awayScore);

        return round($homeProbability * $awayProbability * 100, 2);
    }

    /**
     * Calcule la probabilité de Poisson.
     */
    private function poissonProbability(float $lambda, int $k): float
    {
        if ($k < 0) {
            return 0.0;
        }

        return (pow($lambda, $k) * exp(-$lambda)) / $this->factorial($k);
    }

    /**
     * Calcule la factorielle.
     */
    private function factorial(int $n): int
    {
        if ($n <= 1) {
            return 1;
        }

        $result = 1;
        for ($i = 2; $i <= $n; ++$i) {
            $result *= $i;
        }

        return $result;
    }

    /**
     * Calcule les xG pour un joueur.
     */
    public function calculatePlayerXG(
        int $shotsPerGame,
        float $shotAccuracy,
        float $bigChancesPerGame,
        string $position,
    ): float {
        // Facteur de position
        $positionFactors = [
            'FW' => 1.0,
            'MF' => 0.7,
            'DF' => 0.3,
            'GK' => 0.05,
        ];

        $positionFactor = $positionFactors[$position] ?? 0.5;

        $xG = (
            ($shotsPerGame * $shotAccuracy * 0.1) +
            ($bigChancesPerGame * 0.4)
        ) * $positionFactor;

        return round($xG, 2);
    }

    /**
     * Analyse la qualité des occasions.
     */
    public function analyzeChanceQuality(array $matchStats): array
    {
        $shots = $matchStats['shots'] ?? 0;
        $shotsOnTarget = $matchStats['shots_on_target'] ?? 0;
        $bigChances = $matchStats['big_chances'] ?? 0;
        $xG = $matchStats['xg'] ?? 0;

        $shotAccuracy = $shots > 0 ? ($shotsOnTarget / $shots) * 100 : 0;
        $conversionRate = $shotsOnTarget > 0 ? ($matchStats['goals'] ?? 0) / $shotsOnTarget * 100 : 0;
        $xGPerShot = $shots > 0 ? $xG / $shots : 0;

        return [
            'shot_accuracy' => round($shotAccuracy, 2),
            'conversion_rate' => round($conversionRate, 2),
            'xg_per_shot' => round($xGPerShot, 3),
            'big_chances' => $bigChances,
            'quality_rating' => $this->calculateQualityRating($shotAccuracy, $xGPerShot, $bigChances),
        ];
    }

    /**
     * Calcule une note de qualité générale.
     */
    private function calculateQualityRating(float $shotAccuracy, float $xGPerShot, int $bigChances): string
    {
        $score = ($shotAccuracy / 10) + ($xGPerShot * 50) + ($bigChances * 2);

        if ($score >= 15) {
            return 'Excellent';
        } elseif ($score >= 10) {
            return 'Très bon';
        } elseif ($score >= 7) {
            return 'Bon';
        } elseif ($score >= 4) {
            return 'Moyen';
        }

        return 'Faible';
    }
}
