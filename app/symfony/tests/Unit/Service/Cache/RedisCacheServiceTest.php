<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cache;

use App\Tests\Unit\AbstractUnitTestCase;
use App\Tests\Mock\MockRedisCacheService;
use PHPUnit\Framework\Attributes\DataProvider;

final class RedisCacheServiceTest extends AbstractUnitTestCase
{
    private MockRedisCacheService $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = new MockRedisCacheService();
    }

    protected function tearDown(): void
    {
        $this->cache->reset();
        parent::tearDown();
    }

    public function testSetAndGet(): void
    {
        $key = 'test_key';
        $value = ['data' => 'test value', 'number' => 42];

        $result = $this->cache->set($key, $value);

        $this->assertTrue($result);
        $this->assertEquals($value, $this->cache->get($key));
    }

    public function testGetNonExistentKey(): void
    {
        $this->assertNull($this->cache->get('non_existent_key'));
    }

    public function testHas(): void
    {
        $this->cache->set('existing_key', 'value');

        $this->assertTrue($this->cache->has('existing_key'));
        $this->assertFalse($this->cache->has('missing_key'));
    }

    public function testDelete(): void
    {
        $this->cache->set('key_to_delete', 'value');
        $this->assertTrue($this->cache->has('key_to_delete'));

        $result = $this->cache->delete('key_to_delete');

        $this->assertTrue($result);
        $this->assertFalse($this->cache->has('key_to_delete'));
    }

    public function testDeleteNonExistent(): void
    {
        $result = $this->cache->delete('non_existent');

        $this->assertFalse($result);
    }

    public function testDeleteByPattern(): void
    {
        $this->cache->set('matches:football:1', 'match1');
        $this->cache->set('matches:football:2', 'match2');
        $this->cache->set('matches:basketball:1', 'basket1');
        $this->cache->set('predictions:1', 'pred1');

        $count = $this->cache->deleteByPattern('matches:football:*');

        $this->assertEquals(2, $count);
        $this->assertFalse($this->cache->has('matches:football:1'));
        $this->assertFalse($this->cache->has('matches:football:2'));
        $this->assertTrue($this->cache->has('matches:basketball:1'));
        $this->assertTrue($this->cache->has('predictions:1'));
    }

    public function testClear(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');

        $result = $this->cache->clear();

        $this->assertTrue($result);
        $this->assertEquals(0, $this->cache->count());
    }

    public function testGetMultiple(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $result = $this->cache->getMultiple(['key1', 'key2', 'key3']);

        $this->assertEquals('value1', $result['key1']);
        $this->assertEquals('value2', $result['key2']);
        $this->assertNull($result['key3']);
    }

    public function testSetMultiple(): void
    {
        $values = [
            'multi1' => 'value1',
            'multi2' => 'value2',
            'multi3' => 'value3',
        ];

        $result = $this->cache->setMultiple($values);

        $this->assertTrue($result);
        $this->assertEquals('value1', $this->cache->get('multi1'));
        $this->assertEquals('value2', $this->cache->get('multi2'));
        $this->assertEquals('value3', $this->cache->get('multi3'));
    }

    public function testIncrement(): void
    {
        $this->cache->set('counter', 5);

        $result = $this->cache->increment('counter', 3);

        $this->assertEquals(8, $result);
        $this->assertEquals(8, $this->cache->get('counter'));
    }

    public function testIncrementNewKey(): void
    {
        $result = $this->cache->increment('new_counter', 1);

        $this->assertEquals(1, $result);
    }

    public function testRememberWithCacheMiss(): void
    {
        $callCount = 0;
        $callback = function () use (&$callCount) {
            $callCount++;
            return ['computed' => 'value'];
        };

        $result = $this->cache->remember('computed_key', 3600, $callback);

        $this->assertEquals(['computed' => 'value'], $result);
        $this->assertEquals(1, $callCount);
    }

    public function testRememberWithCacheHit(): void
    {
        $this->cache->set('cached_key', ['cached' => 'data']);

        $callCount = 0;
        $callback = function () use (&$callCount) {
            $callCount++;
            return ['new' => 'value'];
        };

        $result = $this->cache->remember('cached_key', 3600, $callback);

        $this->assertEquals(['cached' => 'data'], $result);
        $this->assertEquals(0, $callCount); // Callback should not be called
    }

    public function testIsAvailable(): void
    {
        $this->assertTrue($this->cache->isAvailable());

        $this->cache->setAvailable(false);

        $this->assertFalse($this->cache->isAvailable());
    }

    public function testOperationsWhenUnavailable(): void
    {
        $this->cache->setAvailable(false);

        $this->assertFalse($this->cache->set('key', 'value'));
        $this->assertNull($this->cache->get('key'));
        $this->assertFalse($this->cache->has('key'));
        $this->assertFalse($this->cache->delete('key'));
        $this->assertEquals(0, $this->cache->deleteByPattern('*'));
        $this->assertEquals(0, $this->cache->increment('counter'));
    }

    public function testGetStats(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $stats = $this->cache->getStats();

        $this->assertTrue($stats['available']);
        $this->assertEquals(2, $stats['keys_count']);
    }

    #[DataProvider('cacheKeyProvider')]
    public function testVariousCacheKeys(string $key, mixed $value): void
    {
        $this->cache->set($key, $value);

        $this->assertEquals($value, $this->cache->get($key));
    }

    public static function cacheKeyProvider(): array
    {
        return [
            'simple string' => ['simple_key', 'simple value'],
            'with colons' => ['prefix:category:id', 'namespaced value'],
            'with numbers' => ['match_12345', ['id' => 12345]],
            'numeric value' => ['number_key', 42],
            'float value' => ['float_key', 3.14159],
            'boolean value' => ['bool_key', true],
            'array value' => ['array_key', ['a' => 1, 'b' => 2, 'c' => [1, 2, 3]]],
            'empty array' => ['empty_array', []],
        ];
    }
}