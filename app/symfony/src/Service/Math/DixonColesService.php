<?php

declare(strict_types=1);

namespace App\Service\Math;

/**
 * Implémentation du modèle Dixon-Coles pour les prédictions de football.
 *
 * Ce modèle améliore le modèle de Poisson standard en:
 * 1. Ajoutant une correction pour les scores faibles (0-0, 1-0, 0-1, 1-1)
 * 2. Prenant en compte la corrélation entre les buts marqués par chaque équipe
 * 3. Utilisant des paramètres d'attaque/défense par équipe
 *
 * Référence: Dixon & Coles (1997) - "Modelling Association Football Scores and Inefficiencies in the Football Betting Market"
 */
class DixonColesService
{
    private const RHO_DEFAULT = -0.13; // Paramètre de corrélation typique

    public function __construct(
        private readonly PoissonService $poissonService,
    ) {
    }

    /**
     * Calcule la fonction de correction tau de Dixon-Coles.
     * Cette fonction ajuste les probabilités pour les scores faibles.
     *
     * @param int   $homeGoals Buts à domicile
     * @param int   $awayGoals Buts à l'extérieur
     * @param float $lambda    Expected goals domicile
     * @param float $mu        Expected goals extérieur
     * @param float $rho       Paramètre de corrélation (-1 à 1)
     */
    public function tau(int $homeGoals, int $awayGoals, float $lambda, float $mu, float $rho): float
    {
        if (0 === $homeGoals && 0 === $awayGoals) {
            return 1 - $lambda * $mu * $rho;
        }

        if (0 === $homeGoals && 1 === $awayGoals) {
            return 1 + $lambda * $rho;
        }

        if (1 === $homeGoals && 0 === $awayGoals) {
            return 1 + $mu * $rho;
        }

        if (1 === $homeGoals && 1 === $awayGoals) {
            return 1 - $rho;
        }

        return 1.0;
    }

    /**
     * Calcule la probabilité d'un score exact avec le modèle Dixon-Coles.
     */
    public function matchScoreProbability(
        float $homeExpectedGoals,
        float $awayExpectedGoals,
        int $homeGoals,
        int $awayGoals,
        float $rho = self::RHO_DEFAULT,
    ): float {
        $poissonProb = $this->poissonService->matchScoreProbability(
            $homeExpectedGoals,
            $awayExpectedGoals,
            $homeGoals,
            $awayGoals
        );

        $tauCorrection = $this->tau(
            $homeGoals,
            $awayGoals,
            $homeExpectedGoals,
            $awayExpectedGoals,
            $rho
        );

        return $poissonProb * $tauCorrection;
    }

    /**
     * Calcule les probabilités de résultat avec le modèle Dixon-Coles.
     */
    public function calculateResultProbabilities(
        float $homeExpectedGoals,
        float $awayExpectedGoals,
        float $rho = self::RHO_DEFAULT,
    ): array {
        $homeWin = 0.0;
        $draw = 0.0;
        $awayWin = 0.0;
        $totalProb = 0.0;

        for ($homeScore = 0; $homeScore <= 10; ++$homeScore) {
            for ($awayScore = 0; $awayScore <= 10; ++$awayScore) {
                $probability = $this->matchScoreProbability(
                    $homeExpectedGoals,
                    $awayExpectedGoals,
                    $homeScore,
                    $awayScore,
                    $rho
                );

                $totalProb += $probability;

                if ($homeScore > $awayScore) {
                    $homeWin += $probability;
                } elseif ($homeScore === $awayScore) {
                    $draw += $probability;
                } else {
                    $awayWin += $probability;
                }
            }
        }

        // Normaliser
        if ($totalProb > 0) {
            $homeWin /= $totalProb;
            $draw /= $totalProb;
            $awayWin /= $totalProb;
        }

        return [
            '1' => round($homeWin * 100, 2),
            'X' => round($draw * 100, 2),
            '2' => round($awayWin * 100, 2),
        ];
    }

    /**
     * Calcule la distribution complète des scores avec Dixon-Coles.
     */
    public function calculateScoreDistribution(
        float $homeExpectedGoals,
        float $awayExpectedGoals,
        float $rho = self::RHO_DEFAULT,
        int $maxGoals = 8,
    ): array {
        $distribution = [];
        $totalProb = 0.0;

        for ($homeScore = 0; $homeScore <= $maxGoals; ++$homeScore) {
            for ($awayScore = 0; $awayScore <= $maxGoals; ++$awayScore) {
                $probability = $this->matchScoreProbability(
                    $homeExpectedGoals,
                    $awayExpectedGoals,
                    $homeScore,
                    $awayScore,
                    $rho
                );

                $totalProb += $probability;

                if ($probability > 0.001) {
                    $distribution["{$homeScore}-{$awayScore}"] = $probability;
                }
            }
        }

        // Normaliser et convertir en pourcentage
        foreach ($distribution as $score => $prob) {
            $distribution[$score] = round(($prob / $totalProb) * 100, 2);
        }

        arsort($distribution);

        return $distribution;
    }

    /**
     * Estime le paramètre rho optimal basé sur les données historiques.
     */
    public function estimateRho(array $historicalMatches): float
    {
        if (empty($historicalMatches)) {
            return self::RHO_DEFAULT;
        }

        $lowScoreMatches = 0;
        $totalMatches = count($historicalMatches);

        foreach ($historicalMatches as $match) {
            $homeGoals = $match['home_score'] ?? 0;
            $awayGoals = $match['away_score'] ?? 0;

            // Compter les scores faibles
            if ($homeGoals <= 1 && $awayGoals <= 1) {
                ++$lowScoreMatches;
            }
        }

        $lowScoreRatio = $lowScoreMatches / $totalMatches;

        // Ajuster rho selon le ratio de scores faibles observé
        // Plus de scores faibles = rho plus négatif
        return -0.05 - ($lowScoreRatio * 0.15);
    }

    /**
     * Calcule les expected goals ajustés avec les facteurs d'équipe.
     */
    public function calculateAdjustedExpectedGoals(
        float $homeAttack,
        float $homeDefense,
        float $awayAttack,
        float $awayDefense,
        float $leagueAvgGoals = 2.7,
        float $homeAdvantage = 1.25,
    ): array {
        // xG domicile = Attaque dom × Défense ext × Moyenne ligue × Avantage dom
        $homeXg = ($homeAttack / $leagueAvgGoals)
                  * ($awayDefense / $leagueAvgGoals)
                  * $leagueAvgGoals
                  * $homeAdvantage;

        // xG extérieur = Attaque ext × Défense dom × Moyenne ligue
        $awayXg = ($awayAttack / $leagueAvgGoals)
                  * ($homeDefense / $leagueAvgGoals)
                  * $leagueAvgGoals;

        return [
            'home' => round($homeXg, 3),
            'away' => round($awayXg, 3),
            'total' => round($homeXg + $awayXg, 3),
        ];
    }

    /**
     * Calcule les intervalles de confiance pour les probabilités.
     */
    public function calculateConfidenceIntervals(
        float $homeExpectedGoals,
        float $awayExpectedGoals,
        float $sampleSize = 100.0,
    ): array {
        $probs = $this->calculateResultProbabilities($homeExpectedGoals, $awayExpectedGoals);

        $intervals = [];
        foreach ($probs as $outcome => $probability) {
            $p = $probability / 100;
            // Intervalle de confiance à 95% avec formule de Wilson
            $z = 1.96;
            $n = $sampleSize;

            $denominator = 1 + ($z * $z / $n);
            $center = ($p + ($z * $z / (2 * $n))) / $denominator;
            $margin = ($z * sqrt(($p * (1 - $p) + $z * $z / (4 * $n)) / $n)) / $denominator;

            $intervals[$outcome] = [
                'probability' => $probability,
                'lower' => round(max(0, $center - $margin) * 100, 2),
                'upper' => round(min(1, $center + $margin) * 100, 2),
            ];
        }

        return $intervals;
    }

    /**
     * Calcule les probabilités double chance.
     */
    public function calculateDoubleChance(
        float $homeExpectedGoals,
        float $awayExpectedGoals,
    ): array {
        $probs = $this->calculateResultProbabilities($homeExpectedGoals, $awayExpectedGoals);

        return [
            '1X' => round($probs['1'] + $probs['X'], 2),
            '12' => round($probs['1'] + $probs['2'], 2),
            'X2' => round($probs['X'] + $probs['2'], 2),
        ];
    }

    /**
     * Calcule les probabilités de score exact regroupées.
     */
    public function calculateGroupedScores(
        float $homeExpectedGoals,
        float $awayExpectedGoals,
    ): array {
        $homeWin = 0.0;
        $draw = 0.0;
        $awayWin = 0.0;

        $homeByOne = 0.0;
        $homeByTwo = 0.0;
        $homeByThreePlus = 0.0;
        $awayByOne = 0.0;
        $awayByTwo = 0.0;
        $awayByThreePlus = 0.0;

        for ($h = 0; $h <= 10; ++$h) {
            for ($a = 0; $a <= 10; ++$a) {
                $prob = $this->matchScoreProbability($homeExpectedGoals, $awayExpectedGoals, $h, $a);
                $diff = $h - $a;

                if ($diff > 0) {
                    $homeWin += $prob;
                    if (1 === $diff) {
                        $homeByOne += $prob;
                    } elseif (2 === $diff) {
                        $homeByTwo += $prob;
                    } else {
                        $homeByThreePlus += $prob;
                    }
                } elseif ($diff < 0) {
                    $awayWin += $prob;
                    $absDiff = abs($diff);
                    if (1 === $absDiff) {
                        $awayByOne += $prob;
                    } elseif (2 === $absDiff) {
                        $awayByTwo += $prob;
                    } else {
                        $awayByThreePlus += $prob;
                    }
                } else {
                    $draw += $prob;
                }
            }
        }

        return [
            'home_win' => round($homeWin * 100, 2),
            'draw' => round($draw * 100, 2),
            'away_win' => round($awayWin * 100, 2),
            'home_by_1' => round($homeByOne * 100, 2),
            'home_by_2' => round($homeByTwo * 100, 2),
            'home_by_3+' => round($homeByThreePlus * 100, 2),
            'away_by_1' => round($awayByOne * 100, 2),
            'away_by_2' => round($awayByTwo * 100, 2),
            'away_by_3+' => round($awayByThreePlus * 100, 2),
        ];
    }
}
