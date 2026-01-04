<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Math;

use App\Service\Math\MonteCarloSimulator;
use App\Tests\Unit\AbstractUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class MonteCarloSimulatorTest extends AbstractUnitTestCase
{
    private MonteCarloSimulator $simulator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simulator = new MonteCarloSimulator();
    }

    public function testSimulateFootballMatchReturnsExpectedStructure(): void
    {
        $result = $this->simulator->simulateFootballMatch(1.5, 1.2, 1000);

        $this->assertArrayHasKeys(['result', 'over_under', 'btts', 'most_likely_scores'], $result);
        $this->assertArrayHasKeys(['1', 'X', '2'], $result['result']);
        $this->assertArrayHasKeys(['over_05', 'over_15', 'over_25', 'over_35'], $result['over_under']);
        $this->assertArrayHasKeys(['yes', 'no'], $result['btts']);
    }

    public function testSimulateFootballMatchResultsSumToHundred(): void
    {
        $result = $this->simulator->simulateFootballMatch(1.5, 1.2, 5000);

        $resultSum = $result['result']['1'] + $result['result']['X'] + $result['result']['2'];
        $this->assertApproximatelyEquals(100.0, $resultSum, 1.0);

        $bttsSum = $result['btts']['yes'] + $result['btts']['no'];
        $this->assertApproximatelyEquals(100.0, $bttsSum, 1.0);
    }

    public function testSimulateFootballMatchFavorHome(): void
    {
        // Home team clearly stronger
        $result = $this->simulator->simulateFootballMatch(2.5, 0.8, 5000);

        // Home win should be most probable
        $this->assertGreaterThan($result['result']['X'], $result['result']['1']);
        $this->assertGreaterThan($result['result']['2'], $result['result']['1']);

        // Home win should be > 50% for such a big difference
        $this->assertGreaterThan(45, $result['result']['1']);
    }

    public function testSimulateFootballMatchFavorAway(): void
    {
        // Away team clearly stronger
        $result = $this->simulator->simulateFootballMatch(0.7, 2.3, 5000);

        // Away win should be most probable
        $this->assertGreaterThan($result['result']['X'], $result['result']['2']);
        $this->assertGreaterThan($result['result']['1'], $result['result']['2']);
    }

    public function testSimulateFootballMatchEqualTeams(): void
    {
        // Equal expected goals
        $result = $this->simulator->simulateFootballMatch(1.3, 1.3, 5000);

        // Home and away win should be similar (within 5%)
        $diff = abs($result['result']['1'] - $result['result']['2']);
        $this->assertLessThan(10, $diff);
    }

    public function testOverUnderProbabilities(): void
    {
        // High scoring match expected
        $result = $this->simulator->simulateFootballMatch(2.2, 1.8, 5000);

        // Over 2.5 should be fairly likely
        $this->assertGreaterThan(40, $result['over_under']['over_25']);

        // Over values should decrease as threshold increases
        $this->assertGreaterThan($result['over_under']['over_15'], $result['over_under']['over_05']);
        $this->assertGreaterThan($result['over_under']['over_25'], $result['over_under']['over_15']);
        $this->assertGreaterThan($result['over_under']['over_35'], $result['over_under']['over_25']);
    }

    public function testBTTSProbabilities(): void
    {
        // High scoring match
        $highScoringResult = $this->simulator->simulateFootballMatch(2.0, 1.8, 5000);

        // Low scoring match
        $lowScoringResult = $this->simulator->simulateFootballMatch(0.8, 0.6, 5000);

        // BTTS should be more likely in high scoring match
        $this->assertGreaterThan($lowScoringResult['btts']['yes'], $highScoringResult['btts']['yes']);
    }

    public function testMostLikelyScores(): void
    {
        $result = $this->simulator->simulateFootballMatch(1.5, 1.0, 5000);

        $this->assertNotEmpty($result['most_likely_scores']);
        $this->assertLessThanOrEqual(5, count($result['most_likely_scores']));

        // All probabilities should be valid
        foreach ($result['most_likely_scores'] as $score => $probability) {
            $this->assertValidProbability($probability);
            $this->assertMatchesRegularExpression('/^\d+-\d+$/', $score);
        }
    }

    public function testSimulateBasketballMatchReturnsExpectedStructure(): void
    {
        $result = $this->simulator->simulateBasketballMatch(110.0, 105.0, 10.0, 10.0, 1000);

        $this->assertArrayHasKeys(['result', 'overtime_probability', 'point_differences', 'total_points'], $result);
        $this->assertArrayHasKeys(['home', 'away'], $result['result']);
    }

    public function testSimulateBasketballMatchResultsSumToHundred(): void
    {
        $result = $this->simulator->simulateBasketballMatch(110.0, 105.0, 10.0, 10.0, 5000);

        $resultSum = $result['result']['home'] + $result['result']['away'];
        $this->assertApproximatelyEquals(100.0, $resultSum, 1.0);
    }

    public function testSimulateBasketballMatchFavorHome(): void
    {
        // Home team clearly stronger
        $result = $this->simulator->simulateBasketballMatch(115.0, 100.0, 10.0, 10.0, 5000);

        // Home win should be more probable
        $this->assertGreaterThan($result['result']['away'], $result['result']['home']);
        $this->assertGreaterThan(60, $result['result']['home']);
    }

    public function testSimulateBasketballMatchOvertimeProbability(): void
    {
        $result = $this->simulator->simulateBasketballMatch(105.0, 105.0, 8.0, 8.0, 5000);

        // Overtime should be possible but rare
        $this->assertGreaterThanOrEqual(0, $result['overtime_probability']);
        $this->assertLessThan(10, $result['overtime_probability']);
    }

    public function testSimulateWithFactorsHomeAdvantage(): void
    {
        $baseResult = $this->simulator->simulateFootballMatch(1.5, 1.5, 5000);

        $withHomeAdvantage = $this->simulator->simulateWithFactors(
            1.5, 1.5,
            ['home_advantage' => 0.15],
            5000
        );

        // Home win should be more likely with home advantage
        $this->assertGreaterThan($baseResult['result']['1'], $withHomeAdvantage['result']['1']);
    }

    public function testSimulateWithFactorsBadWeather(): void
    {
        $normalResult = $this->simulator->simulateFootballMatch(2.0, 1.8, 5000);

        $badWeatherResult = $this->simulator->simulateWithFactors(
            2.0, 1.8,
            ['weather' => 'bad'],
            5000
        );

        // Over 2.5 should be less likely in bad weather
        $this->assertLessThan(
            $normalResult['over_under']['over_25'],
            $badWeatherResult['over_under']['over_25']
        );
    }

    public function testSimulateWithFactorsInjuries(): void
    {
        $baseResult = $this->simulator->simulateFootballMatch(2.0, 1.5, 5000);

        $withHomeInjuries = $this->simulator->simulateWithFactors(
            2.0, 1.5,
            ['home_injuries' => 3], // 3 key players injured
            5000
        );

        // Home win should be less likely with injuries
        $this->assertLessThan($baseResult['result']['1'], $withHomeInjuries['result']['1']);
    }

    public function testSimulateWithFactorsForm(): void
    {
        $withGoodHomeForm = $this->simulator->simulateWithFactors(
            1.5, 1.5,
            ['home_form' => 2], // Good form = +2
            5000
        );

        $withBadHomeForm = $this->simulator->simulateWithFactors(
            1.5, 1.5,
            ['home_form' => -2], // Bad form = -2
            5000
        );

        // Good form should increase home win probability
        $this->assertGreaterThan(
            $withBadHomeForm['result']['1'],
            $withGoodHomeForm['result']['1']
        );
    }

    public function testSimulationConsistency(): void
    {
        // Run multiple simulations and check they're reasonably consistent
        $results = [];
        for ($i = 0; $i < 5; $i++) {
            $results[] = $this->simulator->simulateFootballMatch(1.5, 1.2, 5000);
        }

        // Get home win probabilities
        $homeWins = array_map(fn($r) => $r['result']['1'], $results);

        // Standard deviation should be reasonable (< 5%)
        $mean = array_sum($homeWins) / count($homeWins);
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $homeWins)) / count($homeWins);
        $stdDev = sqrt($variance);

        $this->assertLessThan(5, $stdDev, 'Simulation results should be reasonably consistent');
    }

    #[DataProvider('footballScenarioProvider')]
    public function testFootballScenarios(
        float $homeXG,
        float $awayXG,
        string $expectedWinner,
        float $minProbability
    ): void {
        $result = $this->simulator->simulateFootballMatch($homeXG, $awayXG, 5000);

        $winnerProb = match ($expectedWinner) {
            'home' => $result['result']['1'],
            'away' => $result['result']['2'],
            'draw' => $result['result']['X'],
        };

        $this->assertGreaterThan(
            $minProbability,
            $winnerProb,
            "Expected $expectedWinner to have probability > $minProbability"
        );
    }

    public static function footballScenarioProvider(): array
    {
        return [
            'Strong home team' => [2.5, 0.8, 'home', 50],
            'Strong away team' => [0.7, 2.3, 'away', 45],
            'Equal teams favor home slightly' => [1.3, 1.3, 'home', 25],
            'Very defensive match' => [0.8, 0.7, 'draw', 25],
        ];
    }
}