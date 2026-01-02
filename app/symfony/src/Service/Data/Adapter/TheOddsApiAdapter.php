<?php

declare(strict_types=1);

namespace App\Service\Data\Adapter;

use App\Service\Data\DTO\MatchData;
use App\Service\Data\Interface\CacheInterface;
use App\Service\Data\Interface\MatchDataProviderInterface;
use App\Service\Data\Interface\RateLimiterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Adapter pour The Odds API.
 * Limite: 500 requêtes/mois gratuit.
 */
final class TheOddsApiAdapter implements MatchDataProviderInterface
{
    private const API_BASE_URL = 'https://api.the-odds-api.com/v4';
    private const RATE_LIMIT_KEY = 'the_odds_api';

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

        $cacheKey = sprintf('odds_api_matches_%s', $date->format('Y-m-d'));

        // Vérifier le cache
        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        try {
            $this->rateLimiter->hit(self::RATE_LIMIT_KEY);

            $response = $this->httpClient->request('GET', self::API_BASE_URL.'/sports/soccer_epl/odds', [
                'query' => [
                    'apiKey' => $this->apiKey,
                    'regions' => 'eu,uk',
                    'markets' => 'h2h,spreads,totals',
                    'oddsFormat' => 'decimal',
                ],
            ]);

            $data = $response->toArray();
            $matches = $this->transformToMatchData($data);

            // Mettre en cache pour 1 heure
            $this->cache->set($cacheKey, $matches, 3600);

            return $matches;
        } catch (\Exception $e) {
            // Log l'erreur
            return [];
        }
    }

    public function fetchMatchDetails(string $matchId): ?MatchData
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $cacheKey = sprintf('odds_api_match_%s', $matchId);

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        try {
            $this->rateLimiter->hit(self::RATE_LIMIT_KEY);

            $response = $this->httpClient->request('GET', self::API_BASE_URL."/sports/soccer_epl/events/{$matchId}/odds", [
                'query' => [
                    'apiKey' => $this->apiKey,
                    'regions' => 'eu,uk',
                    'markets' => 'h2h,spreads,totals',
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
        return 'The Odds API';
    }

    public function isAvailable(): bool
    {
        return $this->rateLimiter->allow(self::RATE_LIMIT_KEY);
    }

    /**
     * @return MatchData[]
     */
    private function transformToMatchData(array $data): array
    {
        $matches = [];

        foreach ($data as $event) {
            $matches[] = $this->transformSingleMatch($event);
        }

        return $matches;
    }

    private function transformSingleMatch(array $event): MatchData
    {
        $odds = $this->extractOdds($event['bookmakers'] ?? []);

        return new MatchData(
            externalId: $event['id'] ?? uniqid('odds_'),
            homeTeam: $event['home_team'] ?? 'Unknown',
            awayTeam: $event['away_team'] ?? 'Unknown',
            matchDate: new \DateTimeImmutable($event['commence_time'] ?? 'now'),
            league: $event['sport_title'] ?? 'Unknown',
            sport: 'football',
            odds: $odds,
        );
    }

    private function extractOdds(array $bookmakers): array
    {
        $odds = [];

        foreach ($bookmakers as $bookmaker) {
            $bookmakerName = $bookmaker['key'] ?? 'unknown';

            foreach ($bookmaker['markets'] ?? [] as $market) {
                $marketKey = $market['key'] ?? 'unknown';

                foreach ($market['outcomes'] ?? [] as $outcome) {
                    $odds[$bookmakerName][$marketKey][$outcome['name']] = $outcome['price'] ?? 1.0;
                }
            }
        }

        return $odds;
    }
}
