<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Classe de base pour tous les tests unitaires.
 * Fournit des méthodes utilitaires et des mocks communs.
 */
abstract class AbstractUnitTestCase extends TestCase
{
    /**
     * Crée un mock du LoggerInterface (NullLogger par défaut).
     */
    protected function createLoggerMock(): LoggerInterface
    {
        return new NullLogger();
    }

    /**
     * Crée un mock avec méthodes configurables.
     *
     * @template T of object
     * @param class-string<T> $class
     * @return MockObject&T
     */
    protected function createServiceMock(string $class): MockObject
    {
        return $this->createMock($class);
    }

    /**
     * Asserts that two floating-point numbers are approximately equal.
     */
    protected function assertApproximatelyEquals(
        float $expected,
        float $actual,
        float $delta = 0.01,
        string $message = ''
    ): void {
        $this->assertEqualsWithDelta($expected, $actual, $delta, $message);
    }

    /**
     * Asserts that a probability is valid (between 0 and 100).
     */
    protected function assertValidProbability(float $probability, string $message = ''): void
    {
        $this->assertGreaterThanOrEqual(0, $probability, $message ?: 'Probability should be >= 0');
        $this->assertLessThanOrEqual(100, $probability, $message ?: 'Probability should be <= 100');
    }

    /**
     * Asserts that probabilities sum to approximately 100%.
     *
     * @param array<string, float> $probabilities
     */
    protected function assertProbabilitiesSumToOne(array $probabilities, float $delta = 1.0): void
    {
        $sum = array_sum($probabilities);
        $this->assertEqualsWithDelta(
            100.0,
            $sum,
            $delta,
            sprintf('Probabilities should sum to 100%%, got %.2f%%', $sum)
        );
    }

    /**
     * Asserts that an array has all required keys.
     *
     * @param array<string> $keys
     */
    protected function assertArrayHasKeys(array $keys, array $array, string $message = ''): void
    {
        foreach ($keys as $key) {
            $this->assertArrayHasKey(
                $key,
                $array,
                $message ?: sprintf('Array should have key "%s"', $key)
            );
        }
    }

    /**
     * Asserts that a value is a valid odds (> 1.0).
     */
    protected function assertValidOdds(float $odds, string $message = ''): void
    {
        $this->assertGreaterThan(
            1.0,
            $odds,
            $message ?: 'Odds should be greater than 1.0'
        );
    }

    /**
     * Crée un tableau de données pour data providers.
     *
     * @return array<string, array<mixed>>
     */
    protected static function dataSet(string $name, array $data): array
    {
        return [$name => $data];
    }

    /**
     * Invoke a private/protected method for testing.
     *
     * @param object $object The object instance
     * @param string $methodName The method name to invoke
     * @param array<mixed> $parameters The parameters to pass
     * @return mixed The method result
     */
    protected function invokeMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Get a private/protected property value.
     *
     * @param object $object The object instance
     * @param string $propertyName The property name
     * @return mixed The property value
     */
    protected function getPropertyValue(object $object, string $propertyName): mixed
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);

        return $property->getValue($object);
    }

    /**
     * Set a private/protected property value.
     *
     * @param object $object The object instance
     * @param string $propertyName The property name
     * @param mixed $value The value to set
     */
    protected function setPropertyValue(object $object, string $propertyName, mixed $value): void
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }
}