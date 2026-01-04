<?php

declare(strict_types=1);

namespace App\Service\Cache;

use Psr\Log\LoggerInterface;

/**
 * Service de cache Redis haute performance.
 *
 * Optimisations:
 * - Sérialisation JSON pour les données complexes
 * - Pattern cache-aside avec remember()
 * - Gestion des erreurs avec fallback gracieux
 * - Statistiques de cache intégrées
 */
final class RedisCacheService implements RedisCacheInterface
{
    private ?\Redis $redis = null;
    private bool $connected = false;

    // Préfixes pour organiser les clés par catégorie
    public const PREFIX_MATCHES = 'matches:';
    public const PREFIX_PREDICTIONS = 'predictions:';
    public const PREFIX_STATS = 'stats:';
    public const PREFIX_TEAMS = 'teams:';
    public const PREFIX_API = 'api:';
    public const PREFIX_SESSION = 'session:';

    // TTL par défaut selon le type de données
    public const TTL_MATCHES = 300;       // 5 minutes
    public const TTL_PREDICTIONS = 600;   // 10 minutes
    public const TTL_STATS = 1800;        // 30 minutes
    public const TTL_TEAMS = 3600;        // 1 heure
    public const TTL_API = 900;           // 15 minutes
    public const TTL_SESSION = 86400;     // 24 heures

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly LoggerInterface $logger,
        private readonly string $prefix = 'nexbet:',
    ) {
        $this->connect();
    }

    private function connect(): void
    {
        if ($this->connected) {
            return;
        }

        try {
            $this->redis = new \Redis();
            $this->connected = $this->redis->connect($this->host, $this->port, 2.0);

            if ($this->connected) {
                $this->redis->setOption(\Redis::OPT_PREFIX, $this->prefix);
                $this->redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_JSON);
                $this->logger->info('Redis connected', ['host' => $this->host, 'port' => $this->port]);
            }
        } catch (\RedisException $e) {
            $this->connected = false;
            $this->logger->warning('Redis connection failed', [
                'host' => $this->host,
                'port' => $this->port,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function get(string $key): mixed
    {
        if (!$this->isAvailable()) {
            return null;
        }

        try {
            $value = $this->redis->get($key);

            return false !== $value ? $value : null;
        } catch (\RedisException $e) {
            $this->logger->error('Redis get error', ['key' => $key, 'error' => $e->getMessage()]);

            return null;
        }
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        try {
            if ($ttl > 0) {
                return $this->redis->setex($key, $ttl, $value);
            }

            return $this->redis->set($key, $value);
        } catch (\RedisException $e) {
            $this->logger->error('Redis set error', ['key' => $key, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function has(string $key): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        try {
            return (bool) $this->redis->exists($key);
        } catch (\RedisException $e) {
            $this->logger->error('Redis exists error', ['key' => $key, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function delete(string $key): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        try {
            return $this->redis->del($key) > 0;
        } catch (\RedisException $e) {
            $this->logger->error('Redis delete error', ['key' => $key, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function deleteByPattern(string $pattern): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }

        try {
            $keys = $this->redis->keys($pattern);
            if (empty($keys)) {
                return 0;
            }

            // Retirer le préfixe car Redis l'ajoute automatiquement
            $keysWithoutPrefix = array_map(
                fn ($key) => str_replace($this->prefix, '', $key),
                $keys
            );

            return $this->redis->del(...$keysWithoutPrefix);
        } catch (\RedisException $e) {
            $this->logger->error('Redis deleteByPattern error', ['pattern' => $pattern, 'error' => $e->getMessage()]);

            return 0;
        }
    }

    public function clear(): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        try {
            // Supprimer uniquement les clés avec notre préfixe
            return $this->deleteByPattern('*') >= 0;
        } catch (\RedisException $e) {
            $this->logger->error('Redis clear error', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function getMultiple(array $keys): array
    {
        if (!$this->isAvailable() || empty($keys)) {
            return [];
        }

        try {
            $values = $this->redis->mget($keys);
            $result = [];

            foreach ($keys as $i => $key) {
                $result[$key] = false !== $values[$i] ? $values[$i] : null;
            }

            return $result;
        } catch (\RedisException $e) {
            $this->logger->error('Redis mget error', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function setMultiple(array $values, int $ttl = 3600): bool
    {
        if (!$this->isAvailable() || empty($values)) {
            return false;
        }

        try {
            $pipeline = $this->redis->multi(\Redis::PIPELINE);

            foreach ($values as $key => $value) {
                if ($ttl > 0) {
                    $pipeline->setex($key, $ttl, $value);
                } else {
                    $pipeline->set($key, $value);
                }
            }

            $results = $pipeline->exec();

            return !in_array(false, $results, true);
        } catch (\RedisException $e) {
            $this->logger->error('Redis mset error', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function increment(string $key, int $value = 1): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }

        try {
            return $this->redis->incrBy($key, $value);
        } catch (\RedisException $e) {
            $this->logger->error('Redis increment error', ['key' => $key, 'error' => $e->getMessage()]);

            return 0;
        }
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        // Essayer de récupérer du cache
        $cached = $this->get($key);
        if (null !== $cached) {
            return $cached;
        }

        // Calculer la valeur
        $value = $callback();

        // Stocker en cache
        $this->set($key, $value, $ttl);

        return $value;
    }

    public function getStats(): array
    {
        if (!$this->isAvailable()) {
            return ['available' => false];
        }

        try {
            $info = $this->redis->info();

            return [
                'available' => true,
                'connected_clients' => $info['connected_clients'] ?? 0,
                'used_memory_human' => $info['used_memory_human'] ?? 'N/A',
                'total_commands' => $info['total_commands_processed'] ?? 0,
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                'hit_rate' => $this->calculateHitRate($info),
                'uptime_days' => round(($info['uptime_in_seconds'] ?? 0) / 86400, 2),
                'keys_count' => $this->countKeys(),
            ];
        } catch (\RedisException $e) {
            $this->logger->error('Redis stats error', ['error' => $e->getMessage()]);

            return ['available' => false, 'error' => $e->getMessage()];
        }
    }

    public function isAvailable(): bool
    {
        if (!$this->connected || null === $this->redis) {
            $this->connect();
        }

        if (!$this->connected) {
            return false;
        }

        try {
            return false !== $this->redis->ping();
        } catch (\RedisException $e) {
            $this->connected = false;

            return false;
        }
    }

    // === Méthodes spécialisées pour l'application ===

    /**
     * Cache les données d'un match.
     */
    public function cacheMatch(int $matchId, array $data, string $sport = 'football'): bool
    {
        $key = self::PREFIX_MATCHES.$sport.':'.$matchId;

        return $this->set($key, $data, self::TTL_MATCHES);
    }

    /**
     * Récupère les données d'un match depuis le cache.
     */
    public function getMatch(int $matchId, string $sport = 'football'): ?array
    {
        $key = self::PREFIX_MATCHES.$sport.':'.$matchId;

        return $this->get($key);
    }

    /**
     * Cache une prédiction.
     */
    public function cachePrediction(int $matchId, array $prediction, string $sport = 'football'): bool
    {
        $key = self::PREFIX_PREDICTIONS.$sport.':'.$matchId;

        return $this->set($key, $prediction, self::TTL_PREDICTIONS);
    }

    /**
     * Récupère une prédiction depuis le cache.
     */
    public function getPrediction(int $matchId, string $sport = 'football'): ?array
    {
        $key = self::PREFIX_PREDICTIONS.$sport.':'.$matchId;

        return $this->get($key);
    }

    /**
     * Cache les statistiques d'une équipe.
     */
    public function cacheTeamStats(int $teamId, array $stats): bool
    {
        $key = self::PREFIX_TEAMS.$teamId;

        return $this->set($key, $stats, self::TTL_TEAMS);
    }

    /**
     * Récupère les statistiques d'une équipe.
     */
    public function getTeamStats(int $teamId): ?array
    {
        $key = self::PREFIX_TEAMS.$teamId;

        return $this->get($key);
    }

    /**
     * Cache une réponse API externe.
     */
    public function cacheApiResponse(string $endpoint, array $response, ?int $ttl = null): bool
    {
        $key = self::PREFIX_API.md5($endpoint);

        return $this->set($key, $response, $ttl ?? self::TTL_API);
    }

    /**
     * Récupère une réponse API en cache.
     */
    public function getApiResponse(string $endpoint): ?array
    {
        $key = self::PREFIX_API.md5($endpoint);

        return $this->get($key);
    }

    /**
     * Invalide tout le cache pour un sport.
     */
    public function invalidateSportCache(string $sport): int
    {
        $count = 0;
        $count += $this->deleteByPattern(self::PREFIX_MATCHES.$sport.':*');
        $count += $this->deleteByPattern(self::PREFIX_PREDICTIONS.$sport.':*');

        return $count;
    }

    private function calculateHitRate(array $info): float
    {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;

        return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
    }

    private function countKeys(): int
    {
        try {
            $keys = $this->redis->keys('*');

            return count($keys);
        } catch (\RedisException $e) {
            return 0;
        }
    }
}
