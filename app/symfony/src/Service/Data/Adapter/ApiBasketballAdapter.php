<?php

declare(strict_types=1);

namespace App\Service\Data\Adapter;

use App\Service\Data\DTO\MatchData;
use App\Service\Data\Interface\CacheInterface;
use App\Service\Data\Interface\MatchDataProviderInterface;
use App\Service\Data\Interface\RateLimiterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Adapter pour API-Basketball (API-Sports).
 * Fournit les matchs NBA, Euroleague, et autres ligues de basketball.
 */
final class ApiBasketballAdapter implements MatchDataProviderInterface
{
    private const API_BASE_URL = 'https://v1.basketball.api-sports.io';
    private const RATE_LIMIT_KEY = 'api_basketball';

    // Ligues principales
    private const LEAGUES = [
        12 => 'NBA',
        120 => 'Euroleague',
        117 => 'Eurocup',
        194 => 'Pro A (France)',
        116 => 'Liga ACB (Spain)',
        79 => 'Serie A (Italy)',
    ];

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

        $cacheKey = sprintf('api_basketball_matches_%s', $date->format('Y-m-d'));

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        try {
            $this->rateLimiter->hit(self::RATE_LIMIT_KEY);

            // Fetch all games for the date (not filtered by league)
            $response = $this->httpClient->request('GET', self::API_BASE_URL.'/games', [
                'headers' => [
                    'x-apisports-key' => $this->apiKey,
                ],
                'query' => [
                    'date' => $date->format('Y-m-d'),
                ],
            ]);

            $data = $response->toArray();
            $allMatches = $this->transformToMatchData($data['response'] ?? [], 0);

            // Filter to keep only major leagues
            $majorLeagues = ['NBA', 'Euroleague', 'Eurocup', 'Liga ACB', 'Pro A', 'Serie A', 'BBL', 'BSL'];
            $filteredMatches = array_filter($allMatches, function ($match) use ($majorLeagues) {
                foreach ($majorLeagues as $league) {
                    if (false !== stripos($match->league, $league)) {
                        return true;
                    }
                }

                return false;
            });

            $this->cache->set($cacheKey, array_values($filteredMatches), 1800); // 30 minutes

            return array_values($filteredMatches);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function fetchMatchesForLeague(int $leagueId, \DateTimeInterface $date): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        try {
            $this->rateLimiter->hit(self::RATE_LIMIT_KEY);

            $response = $this->httpClient->request('GET', self::API_BASE_URL.'/games', [
                'headers' => [
                    'x-apisports-key' => $this->apiKey,
                ],
                'query' => [
                    'league' => $leagueId,
                    'date' => $date->format('Y-m-d'),
                    'season' => $this->getCurrentSeason(),
                ],
            ]);

            $data = $response->toArray();

            return $this->transformToMatchData($data['response'] ?? [], $leagueId);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function fetchMatchDetails(string $matchId): ?MatchData
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $cacheKey = sprintf('api_basketball_match_%s', $matchId);

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        try {
            $this->rateLimiter->hit(self::RATE_LIMIT_KEY);

            $response = $this->httpClient->request('GET', self::API_BASE_URL.'/games', [
                'headers' => [
                    'x-apisports-key' => $this->apiKey,
                ],
                'query' => [
                    'id' => $matchId,
                ],
            ]);

            $data = $response->toArray();
            $games = $data['response'] ?? [];

            if (empty($games)) {
                return null;
            }

            $match = $this->transformSingleMatch($games[0], 12);

            $this->cache->set($cacheKey, $match, 1800);

            return $match;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Recupere les statistiques d'un match.
     */
    public function fetchMatchStatistics(string $matchId): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $cacheKey = sprintf('api_basketball_stats_%s', $matchId);

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        try {
            $this->rateLimiter->hit(self::RATE_LIMIT_KEY);

            $response = $this->httpClient->request('GET', self::API_BASE_URL.'/games/statistics', [
                'headers' => [
                    'x-apisports-key' => $this->apiKey,
                ],
                'query' => [
                    'id' => $matchId,
                ],
            ]);

            $data = $response->toArray();
            $stats = $data['response'] ?? [];

            $this->cache->set($cacheKey, $stats, 3600);

            return $stats;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getName(): string
    {
        return 'API-Basketball';
    }

    public function isAvailable(): bool
    {
        return $this->rateLimiter->allow(self::RATE_LIMIT_KEY);
    }

    /**
     * @return MatchData[]
     */
    private function transformToMatchData(array $games, int $leagueId): array
    {
        return array_map(
            fn (array $game) => $this->transformSingleMatch($game, $leagueId),
            $games
        );
    }

    private function transformSingleMatch(array $game, int $leagueId): MatchData
    {
        $teams = $game['teams'] ?? [];
        $scores = $game['scores'] ?? [];
        $league = $game['league'] ?? [];

        // Calculer les stats des equipes
        $homeStats = $this->buildTeamStats($scores['home'] ?? []);
        $awayStats = $this->buildTeamStats($scores['away'] ?? []);

        return new MatchData(
            externalId: (string) ($game['id'] ?? uniqid('bb_')),
            homeTeam: $teams['home']['name'] ?? 'Unknown',
            awayTeam: $teams['away']['name'] ?? 'Unknown',
            matchDate: new \DateTimeImmutable($game['date'] ?? 'now'),
            league: $league['name'] ?? self::LEAGUES[$leagueId] ?? 'Unknown',
            sport: 'basketball',
            odds: null,
            statistics: [
                'home_stats' => $homeStats,
                'away_stats' => $awayStats,
                'quarters' => $this->extractQuarterScores($scores),
            ],
            venue: $game['venue'] ?? null,
            status: $this->mapStatus($game['status']['long'] ?? 'Not Started'),
            homeScore: $scores['home']['total'] ?? null,
            awayScore: $scores['away']['total'] ?? null,
        );
    }

    private function buildTeamStats(array $scoreData): array
    {
        return [
            'total' => $scoreData['total'] ?? null,
            'q1' => $scoreData['quarter_1'] ?? null,
            'q2' => $scoreData['quarter_2'] ?? null,
            'q3' => $scoreData['quarter_3'] ?? null,
            'q4' => $scoreData['quarter_4'] ?? null,
            'overtime' => $scoreData['over_time'] ?? null,
        ];
    }

    private function extractQuarterScores(array $scores): array
    {
        return [
            'q1' => [
                'home' => $scores['home']['quarter_1'] ?? null,
                'away' => $scores['away']['quarter_1'] ?? null,
            ],
            'q2' => [
                'home' => $scores['home']['quarter_2'] ?? null,
                'away' => $scores['away']['quarter_2'] ?? null,
            ],
            'q3' => [
                'home' => $scores['home']['quarter_3'] ?? null,
                'away' => $scores['away']['quarter_3'] ?? null,
            ],
            'q4' => [
                'home' => $scores['home']['quarter_4'] ?? null,
                'away' => $scores['away']['quarter_4'] ?? null,
            ],
        ];
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'Not Started', 'NS' => 'scheduled',
            'Quarter 1', 'Q1', 'Quarter 2', 'Q2', 'Quarter 3', 'Q3', 'Quarter 4', 'Q4' => 'live',
            'Halftime', 'HT', 'Break' => 'live',
            'Overtime', 'OT' => 'live',
            'After Over Time', 'AOT' => 'finished',
            'Finished', 'FT', 'Game Finished' => 'finished',
            'Postponed', 'POST' => 'postponed',
            'Cancelled', 'CANC' => 'cancelled',
            'Suspended', 'SUSP' => 'postponed',
            default => 'scheduled',
        };
    }

    private function getCurrentSeason(): string
    {
        $now = new \DateTimeImmutable();
        $month = (int) $now->format('n');
        $year = (int) $now->format('Y');

        // La saison NBA commence en octobre
        if ($month < 7) {
            return (string) ($year - 1).'-'.$year;
        }

        return $year.'-'.($year + 1);
    }
}
