<?php

declare(strict_types=1);

namespace App\Service\Data\Adapter;

use App\Service\Data\DTO\MatchData;
use App\Service\Data\Interface\CacheInterface;
use App\Service\Data\Interface\MatchDataProviderInterface;
use App\Service\Data\Interface\RateLimiterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Adapter pour Football-Data.org API.
 * Limite: 10 requêtes/minute gratuit.
 */
final class FootballDataApiAdapter implements MatchDataProviderInterface
{
    private const API_BASE_URL = 'https://api.football-data.org/v4';
    private const RATE_LIMIT_KEY = 'football_data_api';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly RateLimiterInterface $rateLimiter,
        private readonly string $apiKey,
    ) {
    }

    public function fetchTodayMatches(): array
    {
        $today = new \DateTimeImmutable('today');

        return $this->fetchMatchesByDate($today);
    }

    public function fetchMatchesByDate(\DateTimeInterface $date): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $cacheKey = sprintf('football_data_matches_%s', $date->format('Y-m-d'));

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        try {
            $this->rateLimiter->hit(self::RATE_LIMIT_KEY);

            $response = $this->httpClient->request('GET', self::API_BASE_URL.'/matches', [
                'headers' => [
                    'X-Auth-Token' => $this->apiKey,
                ],
                'query' => [
                    'dateFrom' => $date->format('Y-m-d'),
                    'dateTo' => $date->format('Y-m-d'),
                ],
            ]);

            $data = $response->toArray();
            $matches = $this->transformToMatchData($data['matches'] ?? []);

            $this->cache->set($cacheKey, $matches, 3600);

            return $matches;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function fetchMatchDetails(string $matchId): ?MatchData
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $cacheKey = sprintf('football_data_match_%s', $matchId);

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        try {
            $this->rateLimiter->hit(self::RATE_LIMIT_KEY);

            $response = $this->httpClient->request('GET', self::API_BASE_URL."/matches/{$matchId}", [
                'headers' => [
                    'X-Auth-Token' => $this->apiKey,
                ],
            ]);

            $data = $response->toArray();
            $match = $this->transformSingleMatch($data);

            $this->cache->set($cacheKey, $match, 3600);

            return $match;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getName(): string
    {
        return 'Football-Data.org';
    }

    public function isAvailable(): bool
    {
        return $this->rateLimiter->allow(self::RATE_LIMIT_KEY);
    }

    /**
     * @return MatchData[]
     */
    private function transformToMatchData(array $matches): array
    {
        return array_map(
            fn (array $match) => $this->transformSingleMatch($match),
            $matches
        );
    }

    private function transformSingleMatch(array $match): MatchData
    {
        $statistics = [
            'head_to_head' => $match['head2head'] ?? null,
            'season' => $match['season'] ?? null,
        ];

        return new MatchData(
            externalId: (string) ($match['id'] ?? uniqid('fd_')),
            homeTeam: $match['homeTeam']['name'] ?? 'Unknown',
            awayTeam: $match['awayTeam']['name'] ?? 'Unknown',
            matchDate: new \DateTimeImmutable($match['utcDate'] ?? 'now'),
            league: $match['competition']['name'] ?? 'Unknown',
            sport: 'football',
            statistics: $statistics,
            venue: $match['venue'] ?? null,
            status: $this->mapStatus($match['status'] ?? 'SCHEDULED'),
            homeScore: $match['score']['fullTime']['home'] ?? null,
            awayScore: $match['score']['fullTime']['away'] ?? null,
        );
    }

    /**
     * Map le statut Football-Data vers un format compatible avec l'enum MatchStatus.
     */
    private function mapStatus(string $status): string
    {
        return match ($status) {
            'SCHEDULED', 'TIMED' => 'scheduled',
            'IN_PLAY', 'LIVE' => 'live',
            'PAUSED' => 'Halftime',
            'FINISHED' => 'finished',
            'POSTPONED' => 'postponed',
            'SUSPENDED' => 'postponed',
            'CANCELLED' => 'cancelled',
            default => 'scheduled',
        };
    }
}
