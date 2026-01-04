<?php

declare(strict_types=1);

namespace App\Service\Prediction;

use App\Entity\FootballMatch;
use App\Repository\FootballMatchRepository;
use App\Service\AI\MatchResearchService;

/**
 * Service de prédictions améliorées avec recherche IA et tri par confiance.
 */
final class EnhancedPredictionService
{
    public function __construct(
        private readonly FootballMatchRepository $matchRepository,
        private readonly ResultPredictionService $resultPredictionService,
        private readonly GoalsPredictionService $goalsPredictionService,
        private readonly MatchResearchService $matchResearchService,
    ) {
    }

    /**
     * Récupère les prédictions pour les matchs à venir, triées par confiance.
     *
     * @return array<int, array{
     *     match: FootballMatch,
     *     prediction: array,
     *     ai_research: array,
     *     enhanced_confidence: float,
     *     safe_score: float
     * }>
     */
    public function getUpcomingPredictionsSortedByConfidence(int $limit = 50): array
    {
        $matches = $this->matchRepository->findUpcomingMatches($limit * 2);

        $predictions = [];

        foreach ($matches as $match) {
            try {
                $basePrediction = $this->resultPredictionService->predictResult($match);
                $goalsPrediction = $this->goalsPredictionService->predictOverUnder($match);
                $aiResearch = $this->matchResearchService->researchMatch($match);

                // Appliquer les ajustements IA aux probabilités
                $enhancedPrediction = $this->applyAIAdjustments($basePrediction, $aiResearch);

                // Calculer le score de sécurité du pari
                $safeScore = $this->calculateSafeScore($enhancedPrediction, $aiResearch);

                $predictions[] = [
                    'match' => $match,
                    'prediction' => $enhancedPrediction,
                    'goals_prediction' => $goalsPrediction,
                    'ai_research' => $aiResearch,
                    'enhanced_confidence' => $enhancedPrediction['confidence'],
                    'safe_score' => $safeScore,
                ];
            } catch (\Exception $e) {
                continue;
            }
        }

        // Trier par score de sécurité décroissant
        usort($predictions, fn ($a, $b) => $b['safe_score'] <=> $a['safe_score']);

        return array_slice($predictions, 0, $limit);
    }

    /**
     * Récupère les paris les plus sûrs du jour.
     *
     * @return array<int, array>
     */
    public function getTodaySafestBets(int $limit = 10): array
    {
        $allPredictions = $this->getUpcomingPredictionsSortedByConfidence(100);

        // Filtrer pour ne garder que les matchs d'aujourd'hui
        $today = new \DateTimeImmutable('today');
        $tomorrow = new \DateTimeImmutable('tomorrow');

        $todayPredictions = array_filter($allPredictions, function ($p) use ($today, $tomorrow) {
            $matchDate = $p['match']->getMatchDate();

            return $matchDate >= $today && $matchDate < $tomorrow;
        });

        // Ne garder que les paris avec un bon score de sécurité
        $safeBets = array_filter($todayPredictions, fn ($p) => $p['safe_score'] >= 70);

        return array_slice(array_values($safeBets), 0, $limit);
    }

    /**
     * Applique les ajustements IA aux prédictions de base.
     */
    private function applyAIAdjustments(array $prediction, array $aiResearch): array
    {
        if (!$aiResearch['has_ai_analysis']) {
            return $prediction;
        }

        $adjustments = $aiResearch['prediction_adjustment'];
        $probabilities = $prediction['probabilities'];

        // Appliquer les boosts
        $newProbs = [
            '1' => $probabilities['1'] + $adjustments['home_boost'],
            'X' => $probabilities['X'] + $adjustments['draw_boost'],
            '2' => $probabilities['2'] + $adjustments['away_boost'],
        ];

        // S'assurer que les probabilités restent positives
        $newProbs = array_map(fn ($p) => max(1, $p), $newProbs);

        // Normaliser à 100%
        $total = array_sum($newProbs);
        $newProbs = array_map(fn ($p) => round(($p / $total) * 100, 2), $newProbs);

        // Ajuster la confiance
        $newConfidence = $prediction['confidence'] * $aiResearch['confidence_modifier'];
        $newConfidence = min(100, max(0, $newConfidence));

        $prediction['probabilities'] = $newProbs;
        $prediction['confidence'] = round($newConfidence, 2);
        $prediction['ai_enhanced'] = true;

        // Recalculer le résultat prédit
        $maxProb = max($newProbs);
        $prediction['prediction'] = array_search($maxProb, $newProbs);

        return $prediction;
    }

    /**
     * Calcule un score de sécurité pour un pari (0-100).
     */
    private function calculateSafeScore(array $prediction, array $aiResearch): float
    {
        $score = 0;

        // 1. Probabilité du résultat prédit (max 40 points)
        $maxProb = max($prediction['probabilities']);
        $score += min(40, $maxProb * 0.6);

        // 2. Confiance globale (max 25 points)
        $score += ($prediction['confidence'] / 100) * 25;

        // 3. Écart avec le deuxième choix (max 15 points)
        $probs = $prediction['probabilities'];
        arsort($probs);
        $probValues = array_values($probs);
        $gap = $probValues[0] - ($probValues[1] ?? 0);
        $score += min(15, $gap * 0.5);

        // 4. Niveau de risque IA (max 10 points)
        $riskBonus = match ($aiResearch['risk_level']) {
            'low' => 10,
            'medium' => 5,
            'high' => 0,
            default => 5,
        };
        $score += $riskBonus;

        // 5. Présence de données historiques (5 points)
        if ($prediction['has_historical_data'] ?? false) {
            $score += 5;
        }

        // 6. Présence d'analyse IA (5 points)
        if ($aiResearch['has_ai_analysis']) {
            $score += 5;
        }

        return round(min(100, $score), 2);
    }

    /**
     * Génère un résumé des meilleures prédictions.
     */
    public function generatePredictionsSummary(array $predictions): array
    {
        $totalMatches = count($predictions);
        $withAI = count(array_filter($predictions, fn ($p) => $p['ai_research']['has_ai_analysis']));
        $highConfidence = count(array_filter($predictions, fn ($p) => $p['safe_score'] >= 80));
        $mediumConfidence = count(array_filter($predictions, fn ($p) => $p['safe_score'] >= 60 && $p['safe_score'] < 80));

        $byOutcome = ['1' => 0, 'X' => 0, '2' => 0];
        foreach ($predictions as $p) {
            $outcome = $p['prediction']['prediction'];
            $byOutcome[$outcome] = ($byOutcome[$outcome] ?? 0) + 1;
        }

        return [
            'total_matches' => $totalMatches,
            'with_ai_analysis' => $withAI,
            'high_confidence' => $highConfidence,
            'medium_confidence' => $mediumConfidence,
            'by_outcome' => $byOutcome,
            'avg_safe_score' => $totalMatches > 0
                ? round(array_sum(array_column($predictions, 'safe_score')) / $totalMatches, 2)
                : 0,
        ];
    }
}
