<?php

declare(strict_types=1);

namespace App\Service\Data\Cache;

use App\Service\Data\Interface\CacheInterface;

/**
 * Implémentation simple du cache basé sur des fichiers.
 */
final class FileCacheService implements CacheInterface
{
    private const CACHE_DIR = '/var/cache/nexbet';

    public function __construct(
        private readonly string $cacheDir = self::CACHE_DIR,
    ) {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public function get(string $key): mixed
    {
        $file = $this->getCacheFile($key);

        if (!file_exists($file)) {
            return null;
        }

        $content = file_get_contents($file);
        if (false === $content) {
            return null;
        }

        $data = unserialize($content);

        // Vérifier l'expiration
        if ($data['expires_at'] < time()) {
            $this->delete($key);

            return null;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $file = $this->getCacheFile($key);
        $data = [
            'value' => $value,
            'expires_at' => time() + $ttl,
        ];

        $result = file_put_contents($file, serialize($data), LOCK_EX);

        return false !== $result;
    }

    public function has(string $key): bool
    {
        return null !== $this->get($key);
    }

    public function delete(string $key): bool
    {
        $file = $this->getCacheFile($key);

        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }

    public function clear(): bool
    {
        $files = glob($this->cacheDir.'/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    private function getCacheFile(string $key): string
    {
        return $this->cacheDir.'/'.md5($key).'.cache';
    }
}
