<?php

declare(strict_types=1);

namespace App\Service\Data\Adapter;

use App\Service\Data\DTO\MatchData;
use App\Service\Data\Interface\CacheInterface;
use App\Service\Data\Interface\MatchDataProviderInterface;
use App\Service\Data\Interface\RateLimiterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Adapter pour API-Football.
 * Limite: 100 requêtes/jour gratuit.
 */
final class ApiFootballAdapter implements MatchDataProviderInterface
{
    private const API_BASE_URL = 'https://v3.football.api-sports.io';
    private const RATE_LIMIT_KEY = 'api_football';

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

        $cacheKey = sprintf('api_football_matches_%s', $date->format('Y-m-d'));

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        try {
            $this->rateLimiter->hit(self::RATE_LIMIT_KEY);

            $response = $this->httpClient->request('GET', self::API_BASE_URL.'/fixtures', [
                'headers' => [
                    'x-apisports-key' => $this->apiKey,
                ],
                'query' => [
                    'date' => $date->format('Y-m-d'),
                ],
            ]);

            $data = $response->toArray();
            $matches = $this->transformToMatchData($data['response'] ?? []);

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

        $cacheKey = sprintf('api_football_match_%s', $matchId);

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        try {
            $this->rateLimiter->hit(self::RATE_LIMIT_KEY);

            $response = $this->httpClient->request('GET', self::API_BASE_URL.'/fixtures', [
                'headers' => [
                    'x-apisports-key' => $this->apiKey,
                ],
                'query' => [
                    'id' => $matchId,
                ],
            ]);

            $data = $response->toArray();
            $fixtures = $data['response'] ?? [];

            if (empty($fixtures)) {
                return null;
            }

            $match = $this->transformSingleMatch($fixtures[0]);

            $this->cache->set($cacheKey, $match, 3600);

            return $match;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getName(): string
    {
        return 'API-Football';
    }

    public function isAvailable(): bool
    {
        return $this->rateLimiter->allow(self::RATE_LIMIT_KEY);
    }

    /**
     * @return MatchData[]
     */
    private function transformToMatchData(array $fixtures): array
    {
        return array_map(
            fn (array $fixture) => $this->transformSingleMatch($fixture),
            $fixtures
        );
    }

    private function transformSingleMatch(array $fixture): MatchData
    {
        $fixtureData = $fixture['fixture'] ?? [];
        $teams = $fixture['teams'] ?? [];
        $league = $fixture['league'] ?? [];
        $goals = $fixture['goals'] ?? [];
        $statistics = $fixture['statistics'] ?? [];
        $events = $fixture['events'] ?? [];

        $stats = [
            'referee' => $fixtureData['referee'] ?? null,
            'statistics' => $statistics,
            'venue_capacity' => $fixtureData['venue']['capacity'] ?? null,
        ];

        return new MatchData(
            externalId: (string) ($fixtureData['id'] ?? uniqid('api_')),
            homeTeam: $teams['home']['name'] ?? 'Unknown',
            awayTeam: $teams['away']['name'] ?? 'Unknown',
            matchDate: new \DateTimeImmutable($fixtureData['date'] ?? 'now'),
            league: $league['name'] ?? 'Unknown',
            sport: 'football',
            statistics: $stats,
            venue: $fixtureData['venue']['name'] ?? null,
            status: $fixtureData['status']['long'] ?? 'Scheduled',
            homeScore: $goals['home'] ?? null,
            awayScore: $goals['away'] ?? null,
            events: $events,
        );
    }
}
