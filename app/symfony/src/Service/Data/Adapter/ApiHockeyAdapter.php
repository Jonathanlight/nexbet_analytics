<?php

declare(strict_types=1);

namespace App\Service\Data\Adapter;

use App\Service\Data\DTO\MatchData;
use App\Service\Data\Interface\CacheInterface;
use App\Service\Data\Interface\MatchDataProviderInterface;
use App\Service\Data\Interface\RateLimiterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Adapter pour API-Hockey (API-Sports).
 * Fournit les matchs NHL, KHL, et autres ligues de hockey sur glace.
 */
final class ApiHockeyAdapter implements MatchDataProviderInterface
{
    private const API_BASE_URL = 'https://v1.hockey.api-sports.io';
    private const RATE_LIMIT_KEY = 'api_hockey';

    // Ligues principales
    private const LEAGUES = [
        57 => 'NHL',
        51 => 'KHL',
        70 => 'SHL (Sweden)',
        71 => 'Liiga (Finland)',
        72 => 'Swiss League',
        73 => 'DEL (Germany)',
        134 => 'Ligue Magnus (France)',
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

        $cacheKey = sprintf('api_hockey_matches_%s', $date->format('Y-m-d'));

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
            $majorLeagues = ['NHL', 'KHL', 'SHL', 'Liiga', 'Swiss', 'DEL', 'Magnus', 'AHL', 'Czech'];
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

        $cacheKey = sprintf('api_hockey_match_%s', $matchId);

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

            $match = $this->transformSingleMatch($games[0], 57);

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

        $cacheKey = sprintf('api_hockey_stats_%s', $matchId);

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
        return 'API-Hockey';
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
        $periods = $game['periods'] ?? [];
        $league = $game['league'] ?? [];

        // Calculer les stats des equipes
        $homeStats = $this->buildTeamStats($scores['home'] ?? null, $periods, 'home');
        $awayStats = $this->buildTeamStats($scores['away'] ?? null, $periods, 'away');

        return new MatchData(
            externalId: (string) ($game['id'] ?? uniqid('hk_')),
            homeTeam: $teams['home']['name'] ?? 'Unknown',
            awayTeam: $teams['away']['name'] ?? 'Unknown',
            matchDate: new \DateTimeImmutable($game['date'] ?? 'now'),
            league: $league['name'] ?? self::LEAGUES[$leagueId] ?? 'Unknown',
            sport: 'hockey',
            odds: null,
            statistics: [
                'home_stats' => $homeStats,
                'away_stats' => $awayStats,
                'periods' => $this->extractPeriodScores($periods),
            ],
            venue: null,
            status: $this->mapStatus($game['status']['long'] ?? 'Not Started'),
            homeScore: $scores['home'] ?? null,
            awayScore: $scores['away'] ?? null,
        );
    }

    private function buildTeamStats(?int $totalScore, array $periods, string $side): array
    {
        return [
            'total' => $totalScore,
            'p1' => $periods['first'][$side] ?? null,
            'p2' => $periods['second'][$side] ?? null,
            'p3' => $periods['third'][$side] ?? null,
            'overtime' => $periods['overtime'][$side] ?? null,
            'shootout' => $periods['shootout'][$side] ?? null,
        ];
    }

    private function extractPeriodScores(array $periods): array
    {
        return [
            'p1' => [
                'home' => $periods['first']['home'] ?? null,
                'away' => $periods['first']['away'] ?? null,
            ],
            'p2' => [
                'home' => $periods['second']['home'] ?? null,
                'away' => $periods['second']['away'] ?? null,
            ],
            'p3' => [
                'home' => $periods['third']['home'] ?? null,
                'away' => $periods['third']['away'] ?? null,
            ],
            'overtime' => [
                'home' => $periods['overtime']['home'] ?? null,
                'away' => $periods['overtime']['away'] ?? null,
            ],
            'shootout' => [
                'home' => $periods['shootout']['home'] ?? null,
                'away' => $periods['shootout']['away'] ?? null,
            ],
        ];
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'Not Started', 'NS' => 'scheduled',
            'Period 1', 'P1', 'Period 2', 'P2', 'Period 3', 'P3' => 'live',
            'Break Time', 'BT' => 'live',
            'Overtime', 'OT' => 'live',
            'Shootout', 'SO' => 'live',
            'After Overtime', 'AOT', 'After Shootout' => 'finished',
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

        // La saison NHL commence en octobre
        if ($month < 7) {
            return (string) ($year - 1);
        }

        return (string) $year;
    }
}
