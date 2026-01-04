<?php

declare(strict_types=1);

namespace App\Service\Cache;

/**
 * Interface pour le service de cache Redis.
 * Permet le mocking dans les tests unitaires.
 */
interface RedisCacheInterface
{
    /**
     * Récupère une valeur du cache.
     */
    public function get(string $key): mixed;

    /**
     * Stocke une valeur dans le cache.
     *
     * @param int $ttl Time-to-live en secondes (0 = permanent)
     */
    public function set(string $key, mixed $value, int $ttl = 3600): bool;

    /**
     * Vérifie si une clé existe dans le cache.
     */
    public function has(string $key): bool;

    /**
     * Supprime une clé du cache.
     */
    public function delete(string $key): bool;

    /**
     * Supprime toutes les clés correspondant à un pattern.
     */
    public function deleteByPattern(string $pattern): int;

    /**
     * Vide complètement le cache.
     */
    public function clear(): bool;

    /**
     * Récupère plusieurs valeurs à la fois.
     *
     * @param array<string> $keys
     *
     * @return array<string, mixed>
     */
    public function getMultiple(array $keys): array;

    /**
     * Stocke plusieurs valeurs à la fois.
     *
     * @param array<string, mixed> $values
     */
    public function setMultiple(array $values, int $ttl = 3600): bool;

    /**
     * Incrémente une valeur numérique.
     */
    public function increment(string $key, int $value = 1): int;

    /**
     * Récupère ou calcule une valeur (cache-aside pattern).
     */
    public function remember(string $key, int $ttl, callable $callback): mixed;

    /**
     * Récupère les statistiques du cache.
     */
    public function getStats(): array;

    /**
     * Vérifie si le cache est disponible.
     */
    public function isAvailable(): bool;
}
