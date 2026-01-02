<?php

declare(strict_types=1);

namespace App\Service\Data\RateLimiter;

use App\Service\Data\Interface\RateLimiterInterface;

/**
 * Rate Limiter simple basé sur fichiers.
 */
final class SimpleRateLimiter implements RateLimiterInterface
{
    private const STORAGE_DIR = '/var/cache/nexbet/ratelimit';

    public function __construct(
        private readonly int $maxRequests = 100,
        private readonly int $windowSeconds = 60,
        private readonly string $storageDir = self::STORAGE_DIR,
    ) {
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    public function allow(string $key): bool
    {
        $attempts = $this->getAttempts($key);

        return count($attempts) < $this->maxRequests;
    }

    public function hit(string $key): void
    {
        $attempts = $this->getAttempts($key);
        $attempts[] = time();

        $this->saveAttempts($key, $attempts);
    }

    public function remaining(string $key): int
    {
        $attempts = $this->getAttempts($key);

        return max(0, $this->maxRequests - count($attempts));
    }

    public function reset(string $key): void
    {
        $file = $this->getStorageFile($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }

    private function getAttempts(string $key): array
    {
        $file = $this->getStorageFile($key);

        if (!file_exists($file)) {
            return [];
        }

        $content = file_get_contents($file);
        $attempts = $content ? unserialize($content) : [];

        // Filtrer les tentatives expirées
        $cutoff = time() - $this->windowSeconds;

        return array_filter($attempts, fn ($timestamp) => $timestamp > $cutoff);
    }

    private function saveAttempts(string $key, array $attempts): void
    {
        $file = $this->getStorageFile($key);
        file_put_contents($file, serialize($attempts), LOCK_EX);
    }

    private function getStorageFile(string $key): string
    {
        return $this->storageDir.'/'.md5($key).'.limit';
    }
}
