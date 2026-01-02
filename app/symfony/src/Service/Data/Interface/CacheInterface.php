<?php

declare(strict_types=1);

namespace App\Service\Data\Interface;

interface CacheInterface
{
    /**
     * Récupère une valeur du cache.
     */
    public function get(string $key): mixed;

    /**
     * Stocke une valeur dans le cache.
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
     * Vide tout le cache.
     */
    public function clear(): bool;
}
