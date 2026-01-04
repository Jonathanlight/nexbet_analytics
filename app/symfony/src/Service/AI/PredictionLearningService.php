<?php

declare(strict_types=1);

namespace App\Service\AI;

use App\Repository\BasketballMatchRepository;
use App\Repository\FootballMatchRepository;
use App\Repository\HockeyMatchRepository;
use Psr\Log\LoggerInterface;

/**
 * Service d'apprentissage automatique des prédictions.
 * Analyse les erreurs passées pour ajuster dynamiquement les algorithmes.
 */
final class PredictionLearningService
{
    private const LEARNING_WINDOW_DAYS = 30;
    private const MIN_MATCHES_FOR_LEARNING = 50;

    // Facteurs d'ajustement par défaut
    private array $adjustmentFactors = [
        'football' => [
            'home_advantage' => 1.08,
            'form_weight' => 0.25,
            'h2h_weight' => 0.15,
            'league_factors' => [],
            'time_decay' => 0.95,
        ],
        'basketball' => [
            'home_advantage' => 1.03,
            'form_weight' => 0.30,
            'momentum_weight' => 0.20,
            'fatigue_factor' => 0.02,
        ],
        'hockey' => [
            'home_advantage' => 1.05,
            'goalie_impact' => 0.25,
            'back_to_back_penalty' => 0.08,
            'overtime_tendency' => 0.12,
        ],
    ];

    public function __construct(
        private readonly FootballMatchRepository $footballRepo,
        private readonly BasketballMatchRepository $basketballRepo,
        private readonly HockeyMatchRepository $hockeyRepo,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Analyse les erreurs de prédiction et génère des facteurs de correction.
     */
    public function analyzeAndLearn(string $sport): array
    {
        $finishedMatches = $this->getFinishedMatchesWithPredictions($sport);

        if (count($finishedMatches) < self::MIN_MATCHES_FOR_LEARNING) {
            return $this->adjustmentFactors[$sport] ?? [];
        }

        $analysis = [
            'total_matches' => count($finishedMatches),
            'correct_predictions' => 0,
            'accuracy_by_confidence' => [],
            'accuracy_by_league' => [],
            'error_patterns' => [],
            'suggested_adjustments' => [],
        ];

        // Analyser chaque match
        foreach ($finishedMatches as $match) {
            $wasCorrect = $this->checkPredictionCorrectness($match, $sport);
            if ($wasCorrect) {
                ++$analysis['correct_predictions'];
            }

            // Analyser par niveau de confiance
            $confidence = $match['predicted_confidence'] ?? 50;
            $confLevel = $this->getConfidenceLevel($confidence);
            if (!isset($analysis['accuracy_by_confidence'][$confLevel])) {
                $analysis['accuracy_by_confidence'][$confLevel] = ['correct' => 0, 'total' => 0];
            }
            ++$analysis['accuracy_by_confidence'][$confLevel]['total'];
            if ($wasCorrect) {
                ++$analysis['accuracy_by_confidence'][$confLevel]['correct'];
            }

            // Analyser par ligue
            $league = $match['league'] ?? 'Unknown';
            if (!isset($analysis['accuracy_by_league'][$league])) {
                $analysis['accuracy_by_league'][$league] = ['correct' => 0, 'total' => 0];
            }
            ++$analysis['accuracy_by_league'][$league]['total'];
            if ($wasCorrect) {
                ++$analysis['accuracy_by_league'][$league]['correct'];
            }

            // Détecter les patterns d'erreur
            if (!$wasCorrect) {
                $this->recordErrorPattern($analysis['error_patterns'], $match, $sport);
            }
        }

        // Calculer les taux de précision
        $analysis['overall_accuracy'] = round(
            ($analysis['correct_predictions'] / $analysis['total_matches']) * 100,
            2
        );

        foreach ($analysis['accuracy_by_confidence'] as $level => &$data) {
            $data['accuracy'] = $data['total'] > 0
                ? round(($data['correct'] / $data['total']) * 100, 2)
                : 0;
        }

        foreach ($analysis['accuracy_by_league'] as $league => &$data) {
            $data['accuracy'] = $data['total'] > 0
                ? round(($data['correct'] / $data['total']) * 100, 2)
                : 0;
        }

        // Générer les ajustements suggérés
        $analysis['suggested_adjustments'] = $this->generateAdjustments($analysis, $sport);

        // Appliquer les ajustements
        $this->applyAdjustments($analysis['suggested_adjustments'], $sport);

        return $analysis;
    }

    /**
     * Obtient les facteurs d'ajustement actuels pour un sport.
     */
    public function getAdjustmentFactors(string $sport): array
    {
        return $this->adjustmentFactors[$sport] ?? [];
    }

    /**
     * Calcule un score de confiance ajusté basé sur l'apprentissage.
     */
    public function getAdjustedConfidence(string $sport, float $rawConfidence, string $league): float
    {
        $factors = $this->adjustmentFactors[$sport] ?? [];
        $leagueFactor = $factors['league_factors'][$league] ?? 1.0;

        // Ajuster la confiance selon les performances passées
        $adjustedConfidence = $rawConfidence * $leagueFactor;

        // Appliquer une courbe de calibration
        $adjustedConfidence = $this->calibrateConfidence($adjustedConfidence);

        return round(min(99, max(1, $adjustedConfidence)), 2);
    }

    /**
     * Prédit avec les facteurs d'apprentissage appliqués.
     */
    public function getLearnedPredictionBoosts(string $sport, array $matchContext): array
    {
        $factors = $this->adjustmentFactors[$sport] ?? [];
        $boosts = [
            'home_boost' => 0,
            'away_boost' => 0,
            'draw_boost' => 0,
            'confidence_modifier' => 1.0,
        ];

        // Appliquer l'avantage domicile appris
        $homeAdvantage = $factors['home_advantage'] ?? 1.0;
        $boosts['home_boost'] = ($homeAdvantage - 1.0) * 100;

        // Ajustements spécifiques au sport
        switch ($sport) {
            case 'football':
                $boosts = $this->applyFootballLearning($boosts, $matchContext, $factors);
                break;
            case 'basketball':
                $boosts = $this->applyBasketballLearning($boosts, $matchContext, $factors);
                break;
            case 'hockey':
                $boosts = $this->applyHockeyLearning($boosts, $matchContext, $factors);
                break;
        }

        return $boosts;
    }

    /**
     * Récupère l'historique des erreurs pour analyse.
     */
    public function getErrorHistory(string $sport, int $days = 7): array
    {
        $finishedMatches = $this->getFinishedMatchesWithPredictions($sport, $days);
        $errors = [];

        foreach ($finishedMatches as $match) {
            if (!$this->checkPredictionCorrectness($match, $sport)) {
                $errors[] = [
                    'match' => $match,
                    'predicted' => $match['predicted_result'] ?? 'unknown',
                    'actual' => $match['actual_result'] ?? 'unknown',
                    'confidence' => $match['predicted_confidence'] ?? 0,
                    'error_type' => $this->classifyError($match, $sport),
                ];
            }
        }

        return $errors;
    }

    private function getFinishedMatchesWithPredictions(string $sport, ?int $days = null): array
    {
        $days = $days ?? self::LEARNING_WINDOW_DAYS;
        $startDate = new \DateTimeImmutable("-{$days} days");
        $matches = [];

        switch ($sport) {
            case 'football':
                $rawMatches = $this->footballRepo->findRecentFinishedMatches(500);
                break;
            case 'basketball':
                $rawMatches = $this->getRecentBasketballMatches($days);
                break;
            case 'hockey':
                $rawMatches = $this->getRecentHockeyMatches($days);
                break;
            default:
                return [];
        }

        foreach ($rawMatches as $match) {
            $matchDate = $match->getMatchDate();
            if ($matchDate < $startDate) {
                continue;
            }

            $matches[] = $this->formatMatchForAnalysis($match, $sport);
        }

        return $matches;
    }

    private function formatMatchForAnalysis(object $match, string $sport): array
    {
        $homeScore = 'football' === $sport
            ? $match->getHomeScore()
            : $match->getHomeFinalScore();
        $awayScore = 'football' === $sport
            ? $match->getAwayScore()
            : $match->getAwayFinalScore();

        // Déterminer le résultat réel
        $actualResult = 'X';
        if ($homeScore > $awayScore) {
            $actualResult = '1';
        } elseif ($awayScore > $homeScore) {
            $actualResult = '2';
        }

        return [
            'id' => $match->getId(),
            'home_team' => $match->getHomeTeam()->getName(),
            'away_team' => $match->getAwayTeam()->getName(),
            'league' => $match->getLeague(),
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'total_score' => ($homeScore ?? 0) + ($awayScore ?? 0),
            'actual_result' => $actualResult,
            'match_date' => $match->getMatchDate(),
            // Ces valeurs devraient venir d'une table de prédictions stockées
            'predicted_result' => $actualResult, // Placeholder
            'predicted_confidence' => 50, // Placeholder
        ];
    }

    private function checkPredictionCorrectness(array $match, string $sport): bool
    {
        return ($match['predicted_result'] ?? '') === ($match['actual_result'] ?? '');
    }

    private function getConfidenceLevel(float $confidence): string
    {
        if ($confidence >= 75) {
            return 'very_high';
        }
        if ($confidence >= 65) {
            return 'high';
        }
        if ($confidence >= 55) {
            return 'medium';
        }
        if ($confidence >= 45) {
            return 'low';
        }

        return 'very_low';
    }

    private function recordErrorPattern(array &$patterns, array $match, string $sport): void
    {
        $predicted = $match['predicted_result'] ?? 'unknown';
        $actual = $match['actual_result'] ?? 'unknown';
        $key = "{$predicted}_to_{$actual}";

        if (!isset($patterns[$key])) {
            $patterns[$key] = 0;
        }
        ++$patterns[$key];
    }

    private function classifyError(array $match, string $sport): string
    {
        $predicted = $match['predicted_result'] ?? '';
        $actual = $match['actual_result'] ?? '';

        if ('1' === $predicted && '2' === $actual) {
            return 'home_upset';
        }
        if ('2' === $predicted && '1' === $actual) {
            return 'away_upset';
        }
        if ('1' === $predicted && 'X' === $actual) {
            return 'draw_missed_home';
        }
        if ('2' === $predicted && 'X' === $actual) {
            return 'draw_missed_away';
        }
        if ('X' === $predicted && '1' === $actual) {
            return 'home_underestimated';
        }
        if ('X' === $predicted && '2' === $actual) {
            return 'away_underestimated';
        }

        return 'other';
    }

    private function generateAdjustments(array $analysis, string $sport): array
    {
        $adjustments = [];

        // Ajuster si trop d'erreurs sur les matchs à haute confiance
        $highConf = $analysis['accuracy_by_confidence']['very_high'] ?? ['accuracy' => 80];
        if ($highConf['accuracy'] < 70) {
            $adjustments['reduce_confidence_scaling'] = 0.9;
        }

        // Ajuster les ligues problématiques
        foreach ($analysis['accuracy_by_league'] as $league => $data) {
            if ($data['total'] >= 10 && $data['accuracy'] < 40) {
                $adjustments['league_penalties'][$league] = 0.85;
            } elseif ($data['total'] >= 10 && $data['accuracy'] > 65) {
                $adjustments['league_boosts'][$league] = 1.1;
            }
        }

        // Analyser les patterns d'erreur
        $patterns = $analysis['error_patterns'] ?? [];
        $totalErrors = array_sum($patterns);

        if ($totalErrors > 0) {
            // Si on sous-estime les nuls
            $drawMissed = ($patterns['draw_missed_home'] ?? 0) + ($patterns['draw_missed_away'] ?? 0);
            if ($drawMissed / $totalErrors > 0.3) {
                $adjustments['increase_draw_probability'] = 1.15;
            }

            // Si on surestime le favori
            $upsets = ($patterns['home_upset'] ?? 0) + ($patterns['away_upset'] ?? 0);
            if ($upsets / $totalErrors > 0.4) {
                $adjustments['reduce_favorite_confidence'] = 0.92;
            }
        }

        return $adjustments;
    }

    private function applyAdjustments(array $adjustments, string $sport): void
    {
        if (empty($adjustments)) {
            return;
        }

        $factors = &$this->adjustmentFactors[$sport];

        if (isset($adjustments['reduce_confidence_scaling'])) {
            $factors['confidence_scaling'] = ($factors['confidence_scaling'] ?? 1.0) * $adjustments['reduce_confidence_scaling'];
        }

        if (isset($adjustments['league_penalties'])) {
            foreach ($adjustments['league_penalties'] as $league => $penalty) {
                $factors['league_factors'][$league] = ($factors['league_factors'][$league] ?? 1.0) * $penalty;
            }
        }

        if (isset($adjustments['league_boosts'])) {
            foreach ($adjustments['league_boosts'] as $league => $boost) {
                $factors['league_factors'][$league] = ($factors['league_factors'][$league] ?? 1.0) * $boost;
            }
        }

        if (isset($adjustments['increase_draw_probability'])) {
            $factors['draw_boost'] = ($factors['draw_boost'] ?? 1.0) * $adjustments['increase_draw_probability'];
        }

        if (isset($adjustments['reduce_favorite_confidence'])) {
            $factors['favorite_penalty'] = $adjustments['reduce_favorite_confidence'];
        }

        $this->logger->info("Applied learning adjustments for {$sport}", $adjustments);
    }

    private function calibrateConfidence(float $confidence): float
    {
        // Courbe de calibration pour éviter la surconfiance
        // Basée sur l'analyse des erreurs passées
        if ($confidence > 80) {
            return 75 + ($confidence - 80) * 0.5;
        }
        if ($confidence > 60) {
            return 55 + ($confidence - 60) * 0.75;
        }

        return $confidence * 0.9;
    }

    private function applyFootballLearning(array $boosts, array $context, array $factors): array
    {
        // Ajuster selon la forme récente
        $formWeight = $factors['form_weight'] ?? 0.25;
        $homeForm = $context['home_form'] ?? 50;
        $awayForm = $context['away_form'] ?? 50;

        $formDiff = ($homeForm - $awayForm) * $formWeight;
        $boosts['home_boost'] += $formDiff;
        $boosts['away_boost'] -= $formDiff;

        // Ajuster selon les H2H
        $h2hWeight = $factors['h2h_weight'] ?? 0.15;
        if (isset($context['h2h_home_wins'], $context['h2h_away_wins'])) {
            $h2hAdvantage = ($context['h2h_home_wins'] - $context['h2h_away_wins']) * 3 * $h2hWeight;
            $boosts['home_boost'] += $h2hAdvantage;
            $boosts['away_boost'] -= $h2hAdvantage;
        }

        // Boost pour les nuls si nécessaire
        if (isset($factors['draw_boost'])) {
            $boosts['draw_boost'] += ($factors['draw_boost'] - 1.0) * 10;
        }

        return $boosts;
    }

    private function applyBasketballLearning(array $boosts, array $context, array $factors): array
    {
        // Facteur de fatigue (back-to-back games)
        if ($context['home_back_to_back'] ?? false) {
            $boosts['home_boost'] -= ($factors['fatigue_factor'] ?? 0.02) * 100;
        }
        if ($context['away_back_to_back'] ?? false) {
            $boosts['away_boost'] -= ($factors['fatigue_factor'] ?? 0.02) * 100;
        }

        // Momentum
        $momentumWeight = $factors['momentum_weight'] ?? 0.20;
        $homeMomentum = $context['home_momentum'] ?? 0;
        $awayMomentum = $context['away_momentum'] ?? 0;

        $boosts['home_boost'] += $homeMomentum * $momentumWeight * 10;
        $boosts['away_boost'] += $awayMomentum * $momentumWeight * 10;

        return $boosts;
    }

    private function applyHockeyLearning(array $boosts, array $context, array $factors): array
    {
        // Impact du gardien
        $goalieImpact = $factors['goalie_impact'] ?? 0.25;
        if (isset($context['home_goalie_rating'])) {
            $boosts['home_boost'] += ($context['home_goalie_rating'] - 50) * $goalieImpact;
        }

        // Pénalité back-to-back
        if ($context['home_back_to_back'] ?? false) {
            $boosts['home_boost'] -= ($factors['back_to_back_penalty'] ?? 0.08) * 100;
        }
        if ($context['away_back_to_back'] ?? false) {
            $boosts['away_boost'] -= ($factors['back_to_back_penalty'] ?? 0.08) * 100;
        }

        // Tendance aux prolongations
        $otTendency = $factors['overtime_tendency'] ?? 0.12;
        if (($context['home_ot_rate'] ?? 0) > 0.15 && ($context['away_ot_rate'] ?? 0) > 0.15) {
            $boosts['draw_boost'] += $otTendency * 50;
        }

        return $boosts;
    }

    private function getRecentBasketballMatches(int $days): array
    {
        // Récupérer les matchs terminés des X derniers jours
        $endDate = new \DateTimeImmutable();
        $startDate = $endDate->modify("-{$days} days");

        $allMatches = [];
        for ($d = 0; $d < $days; ++$d) {
            $date = $startDate->modify("+{$d} days");
            $matches = $this->basketballRepo->findByDate($date);
            foreach ($matches as $match) {
                if ('finished' === $match->getStatus() || null !== $match->getHomeFinalScore()) {
                    $allMatches[] = $match;
                }
            }
        }

        return $allMatches;
    }

    private function getRecentHockeyMatches(int $days): array
    {
        $endDate = new \DateTimeImmutable();
        $startDate = $endDate->modify("-{$days} days");

        $allMatches = [];
        for ($d = 0; $d < $days; ++$d) {
            $date = $startDate->modify("+{$d} days");
            $matches = $this->hockeyRepo->findByDate($date);
            foreach ($matches as $match) {
                if ('finished' === $match->getStatus() || null !== $match->getHomeFinalScore()) {
                    $allMatches[] = $match;
                }
            }
        }

        return $allMatches;
    }
}
