<?php

declare(strict_types=1);

namespace App\Service\Betting;

/**
 * Service d'implémentation du Critère de Kelly pour la gestion optimale de bankroll.
 * Formule: f* = (bp - q) / b
 * où f* = fraction de bankroll à miser
 *     b = cote - 1
 *     p = probabilité de victoire
 *     q = probabilité de perte (1 - p).
 */
class KellyCriterionService
{
    /**
     * Calcule la fraction optimale de bankroll selon Kelly.
     */
    public function calculateKellyFraction(float $probability, float $odds): float
    {
        if ($odds <= 1.0 || $probability <= 0 || $probability >= 1) {
            return 0.0;
        }

        $p = $probability / 100; // Convertir en décimal
        $q = 1 - $p;
        $b = $odds - 1;

        $kellyFraction = (($b * $p) - $q) / $b;

        return max(0, $kellyFraction); // Ne jamais retourner de valeur négative
    }

    /**
     * Calcule le montant à miser selon Kelly avec une bankroll donnée.
     */
    public function calculateStakeAmount(float $probability, float $odds, float $bankroll, float $fraction = 1.0): float
    {
        $kellyFraction = $this->calculateKellyFraction($probability, $odds);

        // Utiliser une fraction du Kelly (ex: Half Kelly = 0.5)
        $adjustedFraction = $kellyFraction * $fraction;

        $stakeAmount = $bankroll * $adjustedFraction;

        return round($stakeAmount, 2);
    }

    /**
     * Détermine si c'est un bon pari selon Kelly.
     */
    public function isGoodBet(float $probability, float $odds): bool
    {
        $kellyFraction = $this->calculateKellyFraction($probability, $odds);

        return $kellyFraction > 0;
    }

    /**
     * Calcule le Kelly conservateur (fraction réduite pour moins de risque).
     */
    public function calculateConservativeKelly(float $probability, float $odds, float $bankroll): array
    {
        $fullKelly = $this->calculateStakeAmount($probability, $odds, $bankroll, 1.0);
        $halfKelly = $this->calculateStakeAmount($probability, $odds, $bankroll, 0.5);
        $quarterKelly = $this->calculateStakeAmount($probability, $odds, $bankroll, 0.25);

        return [
            'full_kelly' => $fullKelly,
            'half_kelly' => $halfKelly,
            'quarter_kelly' => $quarterKelly,
            'recommended' => $halfKelly, // Half Kelly recommandé pour la plupart
        ];
    }

    /**
     * Calcule l'espérance de gain.
     */
    public function calculateExpectedValue(float $probability, float $odds, float $stake): float
    {
        $p = $probability / 100;
        $q = 1 - $p;

        $expectedWin = $p * ($stake * ($odds - 1));
        $expectedLoss = $q * $stake;

        return round($expectedWin - $expectedLoss, 2);
    }

    /**
     * Calcule le ROI attendu.
     */
    public function calculateExpectedROI(float $probability, float $odds): float
    {
        $p = $probability / 100;

        $ev = ($p * $odds) - 1;
        $roi = $ev * 100;

        return round($roi, 2);
    }
}
