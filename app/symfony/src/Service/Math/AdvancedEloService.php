<?php

declare(strict_types=1);

namespace App\Service\Math;

/**
 * Service Elo avancé avec:
 * - Facteur K dynamique selon l'importance du match
 * - Prise en compte de la marge de victoire
 * - Décroissance temporelle
 * - Ajustement selon la force de la ligue
 */
class AdvancedEloService
{
    private const DEFAULT_RATING = 1500;
    private const HOME_ADVANTAGE = 65; // Points Elo pour l'avantage domicile

    // Facteurs K selon l'importance
    private const K_FACTOR_LEAGUE = 20;
    private const K_FACTOR_CUP = 30;
    private const K_FACTOR_INTERNATIONAL = 40;
    private const K_FACTOR_FRIENDLY = 10;

    // Multiplicateurs de ligue
    private const LEAGUE_MULTIPLIERS = [
        'Premier League' => 1.00,
        'La Liga' => 0.98,
        'Bundesliga' => 0.96,
        'Serie A' => 0.95,
        'Ligue 1' => 0.92,
        'Eredivisie' => 0.85,
        'Liga Portugal' => 0.82,
        'default' => 0.80,
    ];

    /**
     * Calcule le nouveau rating avec marge de victoire (Goal Difference).
     */
    public function updateRatingWithMargin(
        float $rating,
        float $opponentRating,
        int $goalsScored,
        int $goalsConceded,
        bool $isHome = true,
        string $matchType = 'league',
        string $league = 'default',
    ): array {
        $kFactor = $this->getKFactor($matchType);
        $leagueMultiplier = self::LEAGUE_MULTIPLIERS[$league] ?? self::LEAGUE_MULTIPLIERS['default'];

        // Ajuster pour l'avantage domicile
        $adjustedRating = $isHome ? $rating + self::HOME_ADVANTAGE : $rating;
        $adjustedOpponent = $isHome ? $opponentRating : $opponentRating + self::HOME_ADVANTAGE;

        // Résultat attendu
        $expectedScore = $this->calculateExpectedScore($adjustedRating, $adjustedOpponent);

        // Résultat réel avec multiplicateur de marge
        $goalDiff = $goalsScored - $goalsConceded;
        $actualScore = $this->getActualScore($goalDiff);
        $marginMultiplier = $this->getMarginMultiplier($goalDiff);

        // Changement de rating
        $change = $kFactor * $marginMultiplier * $leagueMultiplier * ($actualScore - $expectedScore);

        return [
            'new_rating' => round($rating + $change, 2),
            'change' => round($change, 2),
            'expected_score' => round($expectedScore, 4),
            'actual_score' => $actualScore,
            'margin_multiplier' => round($marginMultiplier, 2),
        ];
    }

    /**
     * Calcule la probabilité de victoire avec décroissance temporelle.
     */
    public function calculateWinProbabilityWithDecay(
        array $teamMatches,
        array $opponentMatches,
        float $decayFactor = 0.95,
    ): array {
        $teamRating = $this->calculateRatingWithDecay($teamMatches, $decayFactor);
        $opponentRating = $this->calculateRatingWithDecay($opponentMatches, $decayFactor);

        $homeWinProb = $this->calculateExpectedScore(
            $teamRating + self::HOME_ADVANTAGE,
            $opponentRating
        );

        // Estimation du match nul
        $ratingDiff = abs($teamRating - $opponentRating);
        $drawProb = $this->estimateDrawProbability($ratingDiff);

        // Ajuster les probabilités
        $homeWinProb = $homeWinProb * (1 - $drawProb);
        $awayWinProb = (1 - $homeWinProb - $drawProb);

        return [
            '1' => round($homeWinProb * 100, 2),
            'X' => round($drawProb * 100, 2),
            '2' => round(max(0, $awayWinProb) * 100, 2),
            'home_rating' => round($teamRating, 2),
            'away_rating' => round($opponentRating, 2),
        ];
    }

    /**
     * Calcule le rating avec décroissance temporelle.
     * Les matchs récents ont plus d'importance.
     */
    public function calculateRatingWithDecay(array $matches, float $decayFactor = 0.95): float
    {
        if (empty($matches)) {
            return self::DEFAULT_RATING;
        }

        $rating = self::DEFAULT_RATING;
        $weight = 1.0;
        $totalWeight = 0.0;

        // Trier par date décroissante (plus récent en premier)
        usort($matches, fn ($a, $b) => $b['date'] <=> $a['date']);

        foreach ($matches as $match) {
            $matchRatingChange = $match['rating_change'] ?? 0;
            $rating += $matchRatingChange * $weight;
            $totalWeight += $weight;
            $weight *= $decayFactor;
        }

        return $rating;
    }

    /**
     * Calcule le momentum d'une équipe (forme récente pondérée).
     */
    public function calculateMomentum(array $recentMatches, int $maxMatches = 10): array
    {
        if (empty($recentMatches)) {
            return [
                'momentum' => 0,
                'trend' => 'stable',
                'confidence' => 0,
            ];
        }

        $matches = array_slice($recentMatches, 0, $maxMatches);
        $weights = [];
        $momentum = 0;
        $totalWeight = 0;

        // Poids exponentiellement décroissants
        for ($i = 0; $i < count($matches); ++$i) {
            $weight = pow(0.85, $i);
            $weights[] = $weight;
            $totalWeight += $weight;
        }

        foreach ($matches as $i => $match) {
            $result = $match['result'] ?? 0; // 1 = win, 0.5 = draw, 0 = loss
            $goalDiff = ($match['goals_scored'] ?? 0) - ($match['goals_conceded'] ?? 0);

            // Score de performance: résultat + bonus/malus pour la marge
            $performance = $result + ($goalDiff * 0.1);
            $momentum += $performance * $weights[$i];
        }

        $momentum = $momentum / $totalWeight;

        // Calculer la tendance
        $recentMomentum = 0;
        $olderMomentum = 0;
        $half = (int) ceil(count($matches) / 2);

        for ($i = 0; $i < count($matches); ++$i) {
            $result = $matches[$i]['result'] ?? 0;
            if ($i < $half) {
                $recentMomentum += $result;
            } else {
                $olderMomentum += $result;
            }
        }

        $recentMomentum /= max(1, $half);
        $olderMomentum /= max(1, count($matches) - $half);

        $trend = 'stable';
        if ($recentMomentum > $olderMomentum + 0.15) {
            $trend = 'ascending';
        } elseif ($recentMomentum < $olderMomentum - 0.15) {
            $trend = 'descending';
        }

        return [
            'momentum' => round($momentum, 3),
            'normalized_momentum' => round(($momentum + 1) / 2 * 100, 2), // 0-100
            'trend' => $trend,
            'recent_form' => round($recentMomentum, 3),
            'older_form' => round($olderMomentum, 3),
            'confidence' => min(100, count($matches) * 10),
        ];
    }

    /**
     * Calcule la force relative entre deux équipes.
     */
    public function calculateRelativeStrength(
        float $teamRating,
        float $opponentRating,
        array $teamMomentum,
        array $opponentMomentum,
    ): array {
        $ratingDiff = $teamRating - $opponentRating;
        $momentumDiff = ($teamMomentum['normalized_momentum'] ?? 50) - ($opponentMomentum['normalized_momentum'] ?? 50);

        // Combiner rating et momentum (70% rating, 30% momentum)
        $combinedStrength = ($ratingDiff * 0.7) + ($momentumDiff * 0.3);

        // Convertir en probabilité
        $strengthAdvantage = 1 / (1 + pow(10, -$combinedStrength / 400));

        return [
            'rating_diff' => round($ratingDiff, 2),
            'momentum_diff' => round($momentumDiff, 2),
            'combined_strength' => round($combinedStrength, 2),
            'advantage_probability' => round($strengthAdvantage * 100, 2),
            'assessment' => $this->assessStrengthDiff($combinedStrength),
        ];
    }

    /**
     * Prédit la marge de victoire probable.
     */
    public function predictMargin(float $teamRating, float $opponentRating): array
    {
        $ratingDiff = $teamRating - $opponentRating;

        // Convertir la différence de rating en marge de buts attendue
        // Environ 1 but de marge pour 100 points de différence
        $expectedMargin = $ratingDiff / 100;

        // Calculer les probabilités pour différentes marges
        $stdDev = 1.2; // Écart-type typique pour les marges de buts
        $margins = [];

        for ($margin = -5; $margin <= 5; ++$margin) {
            $z = ($margin - $expectedMargin) / $stdDev;
            $probability = exp(-0.5 * $z * $z) / (sqrt(2 * M_PI) * $stdDev);
            if ($probability > 0.01) {
                $margins[$margin] = round($probability * 100, 2);
            }
        }

        return [
            'expected_margin' => round($expectedMargin, 2),
            'margin_distribution' => $margins,
            'most_likely_margin' => (int) round($expectedMargin),
        ];
    }

    private function getKFactor(string $matchType): float
    {
        return match ($matchType) {
            'league' => self::K_FACTOR_LEAGUE,
            'cup' => self::K_FACTOR_CUP,
            'international' => self::K_FACTOR_INTERNATIONAL,
            'friendly' => self::K_FACTOR_FRIENDLY,
            default => self::K_FACTOR_LEAGUE,
        };
    }

    private function calculateExpectedScore(float $ratingA, float $ratingB): float
    {
        return 1 / (1 + pow(10, ($ratingB - $ratingA) / 400));
    }

    private function getActualScore(int $goalDiff): float
    {
        if ($goalDiff > 0) {
            return 1.0;
        } elseif ($goalDiff < 0) {
            return 0.0;
        }

        return 0.5;
    }

    private function getMarginMultiplier(int $goalDiff): float
    {
        $absMargin = abs($goalDiff);

        if (0 === $absMargin) {
            return 1.0;
        } elseif (1 === $absMargin) {
            return 1.0;
        } elseif (2 === $absMargin) {
            return 1.5;
        } elseif (3 === $absMargin) {
            return 1.75;
        }

        // Pour les grandes marges
        return 1.75 + (($absMargin - 3) * 0.25);
    }

    private function estimateDrawProbability(float $ratingDiff): float
    {
        // Plus la différence est grande, moins le nul est probable
        $baseDraw = 0.26; // Probabilité de base d'un nul
        $decay = exp(-abs($ratingDiff) / 400);

        return $baseDraw * $decay;
    }

    private function assessStrengthDiff(float $diff): string
    {
        if ($diff > 200) {
            return 'dominant_advantage';
        } elseif ($diff > 100) {
            return 'clear_advantage';
        } elseif ($diff > 50) {
            return 'slight_advantage';
        } elseif ($diff > -50) {
            return 'even_match';
        } elseif ($diff > -100) {
            return 'slight_disadvantage';
        } elseif ($diff > -200) {
            return 'clear_disadvantage';
        }

        return 'dominant_disadvantage';
    }
}
