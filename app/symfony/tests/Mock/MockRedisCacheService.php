<?php

declare(strict_types=1);

namespace App\Tests\Mock;

use App\Service\Cache\RedisCacheInterface;

/**
 * Mock du service Redis pour les tests unitaires.
 * Utilise un tableau en mémoire comme stockage.
 */
final class MockRedisCacheService implements RedisCacheInterface
{
    /** @var array<string, array{value: mixed, ttl: int, created: int}> */
    private array $storage = [];

    private bool $available = true;

    public function setAvailable(bool $available): void
    {
        $this->available = $available;
    }

    public function get(string $key): mixed
    {
        if (!$this->available) {
            return null;
        }

        if (!isset($this->storage[$key])) {
            return null;
        }

        $entry = $this->storage[$key];

        // Vérifier le TTL
        if ($entry['ttl'] > 0 && (time() - $entry['created']) > $entry['ttl']) {
            unset($this->storage[$key]);
            return null;
        }

        return $entry['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        if (!$this->available) {
            return false;
        }

        $this->storage[$key] = [
            'value' => $value,
            'ttl' => $ttl,
            'created' => time(),
        ];

        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function delete(string $key): bool
    {
        if (!$this->available) {
            return false;
        }

        if (isset($this->storage[$key])) {
            unset($this->storage[$key]);
            return true;
        }

        return false;
    }

    public function deleteByPattern(string $pattern): int
    {
        if (!$this->available) {
            return 0;
        }

        // Convert glob pattern to regex: escape special chars first, then replace wildcards
        $regex = preg_quote($pattern, '/');
        $regex = str_replace(['\*', '\?'], ['.*', '.'], $regex);
        $regex = '/^' . $regex . '$/';
        $count = 0;

        foreach (array_keys($this->storage) as $key) {
            if (preg_match($regex, $key)) {
                unset($this->storage[$key]);
                $count++;
            }
        }

        return $count;
    }

    public function clear(): bool
    {
        if (!$this->available) {
            return false;
        }

        $this->storage = [];
        return true;
    }

    public function getMultiple(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }
        return $result;
    }

    public function setMultiple(array $values, int $ttl = 3600): bool
    {
        if (!$this->available) {
            return false;
        }

        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    public function increment(string $key, int $value = 1): int
    {
        if (!$this->available) {
            return 0;
        }

        $current = (int) ($this->get($key) ?? 0);
        $new = $current + $value;
        $this->set($key, $new, 0);

        return $new;
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    public function getStats(): array
    {
        return [
            'available' => $this->available,
            'keys_count' => count($this->storage),
            'memory_usage' => 'N/A (mock)',
        ];
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    /**
     * Méthode utilitaire pour les tests : vide le cache.
     */
    public function reset(): void
    {
        $this->storage = [];
        $this->available = true;
    }

    /**
     * Méthode utilitaire pour les tests : retourne tout le stockage.
     */
    public function getAll(): array
    {
        return $this->storage;
    }

    /**
     * Méthode utilitaire pour les tests : compte les clés.
     */
    public function count(): int
    {
        return count($this->storage);
    }
}