<?php

declare(strict_types=1);

namespace App\Service\Betting;

use App\Service\Prediction\ConfidenceCalculator;

/**
 * Service de construction de combinaisons de paris optimales.
 */
class CombinationBuilder
{
    public function __construct(
        private readonly ConfidenceCalculator $confidenceCalculator,
    ) {
    }

    /**
     * Génère des combinaisons sûres (confiance >= 90%).
     */
    public function generateSafeCombinations(array $predictions, int $maxBets = 5): array
    {
        $safeBets = array_filter($predictions, fn ($p) => $p['is_safe_bet'] ?? false);

        usort($safeBets, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);

        $combinations = [];

        // Simples (1 pari)
        foreach (array_slice($safeBets, 0, $maxBets) as $bet) {
            $combinations[] = [
                'type' => 'simple',
                'bets' => [$bet],
                'odds' => $bet['odds'],
                'confidence' => $bet['confidence'],
                'success_probability' => $bet['probability'],
            ];
        }

        // Doubles (2 paris)
        for ($i = 0; $i < min(3, count($safeBets) - 1); ++$i) {
            for ($j = $i + 1; $j < min(4, count($safeBets)); ++$j) {
                $combination = $this->buildCombination([$safeBets[$i], $safeBets[$j]]);
                if ($combination['confidence'] >= 80) {
                    $combinations[] = $combination;
                }
            }
        }

        // Triplés (3 paris) - Seulement les meilleurs
        if (count($safeBets) >= 3) {
            $topThree = array_slice($safeBets, 0, 3);
            $combination = $this->buildCombination($topThree);
            if ($combination['confidence'] >= 75) {
                $combinations[] = $combination;
            }
        }

        return $combinations;
    }

    /**
     * Génère des combinaisons value (bon ratio risk/reward).
     */
    public function generateValueCombinations(array $predictions): array
    {
        $valueBets = array_filter($predictions, fn ($p) => ($p['is_value_bet'] ?? false) && $p['confidence'] >= 70
        );

        usort($valueBets, fn ($a, $b) => $b['expected_value'] <=> $a['expected_value']);

        $combinations = [];

        // Doubles value
        for ($i = 0; $i < min(5, count($valueBets) - 1); ++$i) {
            for ($j = $i + 1; $j < min(6, count($valueBets)); ++$j) {
                $combination = $this->buildCombination([$valueBets[$i], $valueBets[$j]]);
                $combinations[] = $combination;
            }
        }

        // Trier par EV
        usort($combinations, fn ($a, $b) => $b['expected_value'] <=> $a['expected_value']);

        return array_slice($combinations, 0, 10);
    }

    /**
     * Génère des systèmes (Trixie, Patent, Yankee, etc.).
     */
    public function generateSystems(array $predictions): array
    {
        $selectedBets = array_filter($predictions, fn ($p) => $p['confidence'] >= 75);
        $selectedBets = array_slice($selectedBets, 0, 8);

        $systems = [];

        // Trixie (4 paris: 3 doubles + 1 triplé)
        if (count($selectedBets) >= 3) {
            $systems['trixie'] = $this->buildTrixie(array_slice($selectedBets, 0, 3));
        }

        // Patent (7 paris: 3 simples + 3 doubles + 1 triplé)
        if (count($selectedBets) >= 3) {
            $systems['patent'] = $this->buildPatent(array_slice($selectedBets, 0, 3));
        }

        // Yankee (11 paris: 6 doubles + 4 triplés + 1 quadruplé)
        if (count($selectedBets) >= 4) {
            $systems['yankee'] = $this->buildYankee(array_slice($selectedBets, 0, 4));
        }

        return $systems;
    }

    /**
     * Construit une combinaison de paris.
     */
    private function buildCombination(array $bets): array
    {
        $odds = 1.0;
        $betConfidences = [];

        foreach ($bets as $bet) {
            $odds *= $bet['odds'];
            $betConfidences[] = [
                'confidence' => $bet['confidence'],
                'probability' => $bet['probability'],
            ];
        }

        $combinedStats = $this->confidenceCalculator->calculateCombinationConfidence($betConfidences);

        return [
            'type' => 'combination_'.count($bets),
            'bets' => $bets,
            'odds' => round($odds, 2),
            'confidence' => $combinedStats['confidence'],
            'success_probability' => $combinedStats['combined_probability'],
            'expected_value' => round($odds * ($combinedStats['combined_probability'] / 100), 2),
            'risk_level' => $combinedStats['risk_level'],
        ];
    }

    /**
     * Construit un système Trixie.
     */
    private function buildTrixie(array $bets): array
    {
        if (3 !== count($bets)) {
            return [];
        }

        $combinations = [];

        // 3 doubles
        $combinations[] = $this->buildCombination([$bets[0], $bets[1]]);
        $combinations[] = $this->buildCombination([$bets[0], $bets[2]]);
        $combinations[] = $this->buildCombination([$bets[1], $bets[2]]);

        // 1 triplé
        $combinations[] = $this->buildCombination($bets);

        return [
            'name' => 'Trixie',
            'total_bets' => 4,
            'combinations' => $combinations,
            'required_wins' => 2,
        ];
    }

    /**
     * Construit un système Patent.
     */
    private function buildPatent(array $bets): array
    {
        if (3 !== count($bets)) {
            return [];
        }

        $combinations = [];

        // 3 simples
        foreach ($bets as $bet) {
            $combinations[] = $this->buildCombination([$bet]);
        }

        // 3 doubles + 1 triplé (Trixie)
        $trixie = $this->buildTrixie($bets);
        $combinations = array_merge($combinations, $trixie['combinations']);

        return [
            'name' => 'Patent',
            'total_bets' => 7,
            'combinations' => $combinations,
            'required_wins' => 1,
        ];
    }

    /**
     * Construit un système Yankee.
     */
    private function buildYankee(array $bets): array
    {
        if (4 !== count($bets)) {
            return [];
        }

        $combinations = [];

        // 6 doubles
        for ($i = 0; $i < 3; ++$i) {
            for ($j = $i + 1; $j < 4; ++$j) {
                $combinations[] = $this->buildCombination([$bets[$i], $bets[$j]]);
            }
        }

        // 4 triplés
        $combinations[] = $this->buildCombination([$bets[0], $bets[1], $bets[2]]);
        $combinations[] = $this->buildCombination([$bets[0], $bets[1], $bets[3]]);
        $combinations[] = $this->buildCombination([$bets[0], $bets[2], $bets[3]]);
        $combinations[] = $this->buildCombination([$bets[1], $bets[2], $bets[3]]);

        // 1 quadruplé
        $combinations[] = $this->buildCombination($bets);

        return [
            'name' => 'Yankee',
            'total_bets' => 11,
            'combinations' => $combinations,
            'required_wins' => 2,
        ];
    }
}
