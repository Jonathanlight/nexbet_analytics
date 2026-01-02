<?php

declare(strict_types=1);

namespace App\Service\Math;

/**
 * Service de calcul de la distribution de Poisson pour prédire les scores.
 * Utilise la formule: P(X=k) = (λ^k * e^(-λ)) / k!
 */
class PoissonService
{
    /**
     * Calcule la probabilité qu'une équipe marque exactement k buts.
     *
     * @param float $lambda Moyenne de buts attendus
     * @param int   $k      Nombre de buts
     */
    public function probability(float $lambda, int $k): float
    {
        if ($k < 0) {
            return 0.0;
        }

        return (pow($lambda, $k) * exp(-$lambda)) / $this->factorial($k);
    }

    /**
     * Calcule la probabilité d'un score exact pour un match.
     *
     * @param float $homeExpectedGoals Buts attendus pour l'équipe à domicile
     * @param float $awayExpectedGoals Buts attendus pour l'équipe à l'extérieur
     * @param int   $homeGoals         Buts de l'équipe à domicile
     * @param int   $awayGoals         Buts de l'équipe à l'extérieur
     */
    public function matchScoreProbability(
        float $homeExpectedGoals,
        float $awayExpectedGoals,
        int $homeGoals,
        int $awayGoals,
    ): float {
        $homeProbability = $this->probability($homeExpectedGoals, $homeGoals);
        $awayProbability = $this->probability($awayExpectedGoals, $awayGoals);

        return $homeProbability * $awayProbability;
    }

    /**
     * Calcule les probabilités de résultat (1, X, 2).
     */
    public function calculateResultProbabilities(float $homeExpectedGoals, float $awayExpectedGoals): array
    {
        $homeWin = 0.0;
        $draw = 0.0;
        $awayWin = 0.0;

        // Calculer pour des scores jusqu'à 10 buts
        for ($homeScore = 0; $homeScore <= 10; ++$homeScore) {
            for ($awayScore = 0; $awayScore <= 10; ++$awayScore) {
                $probability = $this->matchScoreProbability(
                    $homeExpectedGoals,
                    $awayExpectedGoals,
                    $homeScore,
                    $awayScore
                );

                if ($homeScore > $awayScore) {
                    $homeWin += $probability;
                } elseif ($homeScore === $awayScore) {
                    $draw += $probability;
                } else {
                    $awayWin += $probability;
                }
            }
        }

        return [
            '1' => round($homeWin * 100, 2),
            'X' => round($draw * 100, 2),
            '2' => round($awayWin * 100, 2),
        ];
    }

    /**
     * Calcule la probabilité Over/Under pour un seuil donné.
     */
    public function calculateOverUnder(float $homeExpectedGoals, float $awayExpectedGoals, float $line): array
    {
        $over = 0.0;
        $under = 0.0;

        for ($homeScore = 0; $homeScore <= 10; ++$homeScore) {
            for ($awayScore = 0; $awayScore <= 10; ++$awayScore) {
                $totalGoals = $homeScore + $awayScore;
                $probability = $this->matchScoreProbability(
                    $homeExpectedGoals,
                    $awayExpectedGoals,
                    $homeScore,
                    $awayScore
                );

                if ($totalGoals > $line) {
                    $over += $probability;
                } else {
                    $under += $probability;
                }
            }
        }

        return [
            'over' => round($over * 100, 2),
            'under' => round($under * 100, 2),
        ];
    }

    /**
     * Calcule la probabilité BTTS (Both Teams To Score).
     */
    public function calculateBTTS(float $homeExpectedGoals, float $awayExpectedGoals): array
    {
        $bttsYes = 0.0;
        $bttsNo = 0.0;

        for ($homeScore = 0; $homeScore <= 10; ++$homeScore) {
            for ($awayScore = 0; $awayScore <= 10; ++$awayScore) {
                $probability = $this->matchScoreProbability(
                    $homeExpectedGoals,
                    $awayExpectedGoals,
                    $homeScore,
                    $awayScore
                );

                if ($homeScore > 0 && $awayScore > 0) {
                    $bttsYes += $probability;
                } else {
                    $bttsNo += $probability;
                }
            }
        }

        return [
            'yes' => round($bttsYes * 100, 2),
            'no' => round($bttsNo * 100, 2),
        ];
    }

    /**
     * Calcule la distribution de probabilité de tous les scores possibles.
     *
     * @return array<string, float> Tableau associatif avec clés "homeGoals-awayGoals" => probabilité
     */
    public function calculateScoreDistribution(float $homeExpectedGoals, float $awayExpectedGoals): array
    {
        $distribution = [];

        for ($homeScore = 0; $homeScore <= 10; ++$homeScore) {
            for ($awayScore = 0; $awayScore <= 10; ++$awayScore) {
                $probability = $this->matchScoreProbability(
                    $homeExpectedGoals,
                    $awayExpectedGoals,
                    $homeScore,
                    $awayScore
                );

                if ($probability > 0.001) { // Filtre les probabilités très faibles
                    $distribution["{$homeScore}-{$awayScore}"] = round($probability * 100, 2);
                }
            }
        }

        // Trie par probabilité décroissante
        arsort($distribution);

        return $distribution;
    }

    /**
     * Calcule le nombre moyen de buts attendus basé sur les statistiques.
     */
    public function calculateExpectedGoals(
        float $teamAttackStrength,
        float $teamDefenseStrength,
        float $opponentAttackStrength,
        float $opponentDefenseStrength,
        float $leagueAverage,
    ): float {
        // Formule: (Force d'attaque de l'équipe / Moyenne de la ligue)
        // × (Force de défense de l'adversaire / Moyenne de la ligue)
        // × Moyenne de la ligue

        return ($teamAttackStrength / $leagueAverage)
            * ($opponentDefenseStrength / $leagueAverage)
            * $leagueAverage;
    }

    /**
     * Calcule la factorielle de n.
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
}
