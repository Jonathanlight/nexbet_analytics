<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Math;

use App\Service\Math\PoissonService;
use App\Tests\Unit\AbstractUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PoissonServiceTest extends AbstractUnitTestCase
{
    private PoissonService $poissonService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->poissonService = new PoissonService();
    }

    public function testProbabilityWithZeroGoals(): void
    {
        // P(X=0) = e^(-λ) pour λ=1.5
        $probability = $this->poissonService->probability(1.5, 0);

        $expected = exp(-1.5);
        $this->assertApproximatelyEquals($expected, $probability, 0.0001);
    }

    public function testProbabilityWithOneGoal(): void
    {
        // P(X=1) = λ * e^(-λ) pour λ=2.0
        $probability = $this->poissonService->probability(2.0, 1);

        $expected = 2.0 * exp(-2.0);
        $this->assertApproximatelyEquals($expected, $probability, 0.0001);
    }

    public function testProbabilityWithNegativeGoals(): void
    {
        $probability = $this->poissonService->probability(1.5, -1);

        $this->assertEquals(0.0, $probability);
    }

    public function testMatchScoreProbability(): void
    {
        // Probabilité d'un 1-0 avec homeExpected=1.5, awayExpected=1.0
        $probability = $this->poissonService->matchScoreProbability(1.5, 1.0, 1, 0);

        // P(1,0) = P(home=1) * P(away=0)
        $expectedHome = 1.5 * exp(-1.5);
        $expectedAway = exp(-1.0);
        $expected = $expectedHome * $expectedAway;

        $this->assertApproximatelyEquals($expected, $probability, 0.0001);
    }

    public function testCalculateResultProbabilities(): void
    {
        // Test avec un favori clair (home expected > away expected)
        $result = $this->poissonService->calculateResultProbabilities(2.0, 1.0);

        $this->assertArrayHasKeys(['1', 'X', '2'], $result);

        // La victoire à domicile devrait être la plus probable
        $this->assertGreaterThan($result['X'], $result['1']);
        $this->assertGreaterThan($result['2'], $result['1']);

        // Les probabilités devraient approximativement sommer à 100%
        $this->assertProbabilitiesSumToOne($result, 2.0);
    }

    public function testCalculateResultProbabilitiesEqualTeams(): void
    {
        // Deux équipes équivalentes
        $result = $this->poissonService->calculateResultProbabilities(1.3, 1.3);

        // Les probabilités 1 et 2 devraient être très proches
        $this->assertApproximatelyEquals($result['1'], $result['2'], 1.0);
    }

    #[DataProvider('overUnderProvider')]
    public function testCalculateOverUnder(float $homeGoals, float $awayGoals, float $line): void
    {
        $result = $this->poissonService->calculateOverUnder($homeGoals, $awayGoals, $line);

        $this->assertArrayHasKeys(['over', 'under'], $result);
        $this->assertValidProbability($result['over']);
        $this->assertValidProbability($result['under']);

        // Over + Under devrait sommer à ~100%
        $this->assertApproximatelyEquals(100.0, $result['over'] + $result['under'], 2.0);
    }

    public static function overUnderProvider(): array
    {
        return [
            'high scoring match, line 2.5' => [2.0, 1.8, 2.5],
            'low scoring match, line 2.5' => [1.0, 0.8, 2.5],
            'high scoring match, line 3.5' => [2.5, 2.0, 3.5],
            'very low scoring, line 1.5' => [0.8, 0.6, 1.5],
        ];
    }

    public function testCalculateOverUnderHighExpectedGoals(): void
    {
        // Match à buts attendus élevés
        $result = $this->poissonService->calculateOverUnder(2.5, 2.0, 2.5);

        // Over 2.5 devrait être plus probable
        $this->assertGreaterThan($result['under'], $result['over']);
    }

    public function testCalculateOverUnderLowExpectedGoals(): void
    {
        // Match à buts attendus faibles
        $result = $this->poissonService->calculateOverUnder(0.8, 0.7, 2.5);

        // Under 2.5 devrait être plus probable
        $this->assertGreaterThan($result['over'], $result['under']);
    }

    public function testCalculateBTTS(): void
    {
        $result = $this->poissonService->calculateBTTS(1.5, 1.2);

        $this->assertArrayHasKeys(['yes', 'no'], $result);
        $this->assertValidProbability($result['yes']);
        $this->assertValidProbability($result['no']);

        // Yes + No devrait sommer à ~100%
        $this->assertApproximatelyEquals(100.0, $result['yes'] + $result['no'], 2.0);
    }

    public function testCalculateBTTSHighScoring(): void
    {
        // Match à buts attendus élevés
        $result = $this->poissonService->calculateBTTS(2.5, 2.0);

        // BTTS Yes devrait être très probable
        $this->assertGreaterThan(50, $result['yes']);
    }

    public function testCalculateBTTSLowScoring(): void
    {
        // Match à buts attendus faibles
        $result = $this->poissonService->calculateBTTS(0.5, 0.4);

        // BTTS No devrait être plus probable
        $this->assertGreaterThan($result['yes'], $result['no']);
    }

    public function testCalculateScoreDistribution(): void
    {
        $distribution = $this->poissonService->calculateScoreDistribution(1.5, 1.0);

        $this->assertNotEmpty($distribution);

        // Les scores devraient être triés par probabilité décroissante
        $probs = array_values($distribution);
        for ($i = 0; $i < count($probs) - 1; $i++) {
            $this->assertGreaterThanOrEqual($probs[$i + 1], $probs[$i]);
        }

        // 1-0 ou 1-1 devraient être parmi les plus probables
        $topScores = array_slice(array_keys($distribution), 0, 5);
        $this->assertTrue(
            in_array('1-0', $topScores) || in_array('1-1', $topScores) || in_array('0-0', $topScores),
            'Expected common score to be in top 5'
        );
    }

    public function testCalculateScoreDistributionFiltersProbabilities(): void
    {
        $distribution = $this->poissonService->calculateScoreDistribution(1.5, 1.0);

        // Tous les scores devraient avoir une probabilité > 0.1%
        foreach ($distribution as $score => $probability) {
            $this->assertGreaterThan(0.09, $probability, "Score $score should have probability > 0.1%");
        }
    }

    public function testCalculateExpectedGoals(): void
    {
        // Test avec des valeurs de référence
        $expected = $this->poissonService->calculateExpectedGoals(
            teamAttackStrength: 1.8,
            teamDefenseStrength: 1.0,
            opponentAttackStrength: 1.2,
            opponentDefenseStrength: 1.3,
            leagueAverage: 1.4
        );

        // Le résultat devrait être raisonnable (entre 0.5 et 4)
        $this->assertGreaterThan(0.5, $expected);
        $this->assertLessThan(4.0, $expected);
    }

    public function testCalculateExpectedGoalsSymmetry(): void
    {
        $leagueAvg = 1.4;

        // Une équipe moyenne contre elle-même devrait donner ~moyenne
        $expected = $this->poissonService->calculateExpectedGoals(
            $leagueAvg, $leagueAvg, $leagueAvg, $leagueAvg, $leagueAvg
        );

        $this->assertApproximatelyEquals($leagueAvg, $expected, 0.01);
    }

    public function testProbabilitySumToOne(): void
    {
        $lambda = 1.5;
        $totalProbability = 0.0;

        // Somme des probabilités pour k = 0 à 20 (suffisamment élevé)
        for ($k = 0; $k <= 20; $k++) {
            $totalProbability += $this->poissonService->probability($lambda, $k);
        }

        // La somme devrait être très proche de 1
        $this->assertApproximatelyEquals(1.0, $totalProbability, 0.001);
    }

    #[DataProvider('realWorldScenarioProvider')]
    public function testRealWorldScenarios(
        string $scenario,
        float $homeXG,
        float $awayXG,
        string $expectedFavorite
    ): void {
        $result = $this->poissonService->calculateResultProbabilities($homeXG, $awayXG);

        $winner = match (true) {
            $result['1'] > $result['X'] && $result['1'] > $result['2'] => 'home',
            $result['2'] > $result['X'] && $result['2'] > $result['1'] => 'away',
            default => 'draw',
        };

        $this->assertEquals(
            $expectedFavorite,
            $winner,
            "Scenario '$scenario': Expected $expectedFavorite to be favorite"
        );
    }

    public static function realWorldScenarioProvider(): array
    {
        return [
            'Strong home team' => ['PSG vs small team', 2.5, 0.8, 'home'],
            'Strong away team' => ['Small team vs Real Madrid', 0.9, 2.2, 'away'],
            'Equal teams' => ['Derby match', 1.4, 1.4, 'draw'], // Equal xG = draw most likely
            'Slightly better home' => ['Mid-table clash', 1.6, 1.3, 'home'],
        ];
    }
}