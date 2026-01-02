<?php

declare(strict_types=1);

namespace App\Service\Betting;

use App\Entity\User;

/**
 * Service de stratégies de paris adaptées au profil de l'utilisateur.
 */
class BettingStrategyService
{
    public function __construct(
        private readonly KellyCriterionService $kellyService,
    ) {
    }

    /**
     * Recommande un montant de mise selon le profil de l'utilisateur.
     */
    public function recommendStake(
        User $user,
        float $probability,
        float $odds,
        float $confidence,
    ): array {
        $bankroll = $user->getBankroll();
        $profile = $user->getBettingProfile();
        $maxStakePercentage = $user->getMaxStakePercentage() ?? 5.0;

        // Calculer selon différentes stratégies
        $strategies = [
            'kelly' => $this->kellyService->calculateStakeAmount($probability, $odds, $bankroll, 0.5),
            'flat' => $this->calculateFlatStake($bankroll, $profile),
            'confidence_based' => $this->calculateConfidenceBasedStake($bankroll, $confidence, $profile),
        ];

        // Choisir selon le profil
        $recommended = match ($profile) {
            'conservative' => min($strategies['kelly'], $strategies['flat']),
            'balanced' => $strategies['confidence_based'],
            'aggressive' => max($strategies['kelly'], $strategies['confidence_based']),
            default => $strategies['flat'],
        };

        // Appliquer le maximum
        $maxStake = $bankroll * ($maxStakePercentage / 100);
        $recommended = min($recommended, $maxStake);

        return [
            'recommended' => round($recommended, 2),
            'min' => round($recommended * 0.5, 2),
            'max' => round(min($recommended * 1.5, $maxStake), 2),
            'strategies' => array_map(fn ($v) => round($v, 2), $strategies),
            'bankroll_percentage' => round(($recommended / $bankroll) * 100, 2),
        ];
    }

    /**
     * Génère des stratégies de paris pour différents profils.
     */
    public function generateStrategies(float $bankroll, array $bets): array
    {
        return [
            'conservative' => $this->generateConservativeStrategy($bankroll, $bets),
            'balanced' => $this->generateBalancedStrategy($bankroll, $bets),
            'aggressive' => $this->generateAggressiveStrategy($bankroll, $bets),
        ];
    }

    /**
     * Stratégie conservatrice: mise plate, paris sûrs uniquement.
     */
    private function generateConservativeStrategy(float $bankroll, array $bets): array
    {
        $safeBets = array_filter($bets, fn ($bet) => $bet['confidence'] >= 85 && $bet['probability'] >= 70);
        $unitStake = $bankroll * 0.01; // 1% de la bankroll

        $strategy = [];
        foreach ($safeBets as $bet) {
            $strategy[] = [
                'bet' => $bet,
                'stake' => round($unitStake, 2),
                'potential_return' => round($unitStake * $bet['odds'], 2),
            ];
        }

        return [
            'bets' => $strategy,
            'total_stake' => round(count($strategy) * $unitStake, 2),
            'max_potential_return' => round(array_sum(array_column($strategy, 'potential_return')), 2),
        ];
    }

    /**
     * Stratégie équilibrée: mix de paris sûrs et value bets.
     */
    private function generateBalancedStrategy(float $bankroll, array $bets): array
    {
        $selectedBets = array_filter($bets, fn ($bet) => $bet['confidence'] >= 70);

        $strategy = [];
        foreach ($selectedBets as $bet) {
            $stakePercentage = $this->calculateDynamicStakePercentage($bet['confidence'], 'balanced');
            $stake = $bankroll * ($stakePercentage / 100);

            $strategy[] = [
                'bet' => $bet,
                'stake' => round($stake, 2),
                'potential_return' => round($stake * $bet['odds'], 2),
            ];
        }

        return [
            'bets' => $strategy,
            'total_stake' => round(array_sum(array_column($strategy, 'stake')), 2),
            'max_potential_return' => round(array_sum(array_column($strategy, 'potential_return')), 2),
        ];
    }

    /**
     * Stratégie agressive: value bets avec mises plus importantes.
     */
    private function generateAggressiveStrategy(float $bankroll, array $bets): array
    {
        $valueBets = array_filter($bets, fn ($bet) => ($bet['is_value_bet'] ?? false) && $bet['confidence'] >= 65);

        $strategy = [];
        foreach ($valueBets as $bet) {
            $stakePercentage = $this->calculateDynamicStakePercentage($bet['confidence'], 'aggressive');
            $stake = $bankroll * ($stakePercentage / 100);

            $strategy[] = [
                'bet' => $bet,
                'stake' => round($stake, 2),
                'potential_return' => round($stake * $bet['odds'], 2),
            ];
        }

        return [
            'bets' => $strategy,
            'total_stake' => round(array_sum(array_column($strategy, 'stake')), 2),
            'max_potential_return' => round(array_sum(array_column($strategy, 'potential_return')), 2),
        ];
    }

    /**
     * Calcule une mise plate selon le profil.
     */
    private function calculateFlatStake(float $bankroll, string $profile): float
    {
        $percentages = [
            'conservative' => 1.0,
            'balanced' => 2.0,
            'aggressive' => 3.0,
        ];

        $percentage = $percentages[$profile] ?? 2.0;

        return $bankroll * ($percentage / 100);
    }

    /**
     * Calcule une mise basée sur la confiance.
     */
    private function calculateConfidenceBasedStake(float $bankroll, float $confidence, string $profile): float
    {
        $basePercentage = match ($profile) {
            'conservative' => 0.5,
            'balanced' => 1.0,
            'aggressive' => 2.0,
            default => 1.0,
        };

        // Augmenter selon la confiance
        $confidenceMultiplier = 1 + (($confidence - 70) / 100);

        $percentage = $basePercentage * $confidenceMultiplier;

        return $bankroll * ($percentage / 100);
    }

    /**
     * Calcule un pourcentage dynamique selon la confiance.
     */
    private function calculateDynamicStakePercentage(float $confidence, string $profile): float
    {
        $basePercentages = [
            'conservative' => ['min' => 0.5, 'max' => 2.0],
            'balanced' => ['min' => 1.0, 'max' => 4.0],
            'aggressive' => ['min' => 2.0, 'max' => 7.0],
        ];

        $range = $basePercentages[$profile] ?? $basePercentages['balanced'];

        // Interpoler selon la confiance (70-95%)
        $normalized = ($confidence - 70) / 25;
        $percentage = $range['min'] + ($normalized * ($range['max'] - $range['min']));

        return min($range['max'], max($range['min'], $percentage));
    }
}
