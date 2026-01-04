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
 * Supporte toutes les ligues soccer disponibles.
 */
final class TheOddsApiAdapter implements MatchDataProviderInterface
{
    private const API_BASE_URL = 'https://api.the-odds-api.com/v4';
    private const RATE_LIMIT_KEY = 'the_odds_api';

    /**
     * Liste des sports soccer disponibles sur The Odds API.
     * Voir: https://the-odds-api.com/sports-odds-data/.
     */
    private const SOCCER_SPORTS = [
        // Europe - Top Leagues
        'soccer_epl' => 'Premier League',
        'soccer_spain_la_liga' => 'La Liga',
        'soccer_italy_serie_a' => 'Serie A',
        'soccer_germany_bundesliga' => 'Bundesliga',
        'soccer_france_ligue_one' => 'Ligue 1',
        'soccer_uefa_champs_league' => 'Champions League',
        'soccer_uefa_europa_league' => 'Europa League',
        'soccer_uefa_europa_conference_league' => 'Conference League',

        // Europe - Secondary
        'soccer_netherlands_eredivisie' => 'Eredivisie',
        'soccer_portugal_primeira_liga' => 'Liga Portugal',
        'soccer_belgium_first_div' => 'Jupiler Pro League',
        'soccer_turkey_super_league' => 'Super Lig',
        'soccer_scotland_premiership' => 'Premiership',
        'soccer_greece_super_league' => 'Super League Greece',
        'soccer_switzerland_superleague' => 'Swiss Super League',
        'soccer_austria_bundesliga' => 'Austrian Bundesliga',
        'soccer_denmark_superliga' => 'Superliga',
        'soccer_sweden_allsvenskan' => 'Allsvenskan',
        'soccer_norway_eliteserien' => 'Eliteserien',
        'soccer_poland_ekstraklasa' => 'Ekstraklasa',
        'soccer_russia_premier_league' => 'Russian Premier League',
        'soccer_czech_football_league' => 'Czech First League',

        // Spain Lower Divisions
        'soccer_spain_segunda_division' => 'LaLiga2',

        // England Lower Divisions
        'soccer_efl_champ' => 'Championship',
        'soccer_england_league1' => 'League One',
        'soccer_england_league2' => 'League Two',

        // France Lower
        'soccer_france_ligue_two' => 'Ligue 2',

        // Italy Lower
        'soccer_italy_serie_b' => 'Serie B',

        // Germany Lower
        'soccer_germany_bundesliga2' => 'Bundesliga 2',

        // South America
        'soccer_brazil_campeonato' => 'Brasileirão',
        'soccer_brazil_serie_b' => 'Brasileirão Serie B',
        'soccer_argentina_primera_division' => 'Liga Profesional',
        'soccer_mexico_ligamx' => 'Liga MX',
        'soccer_conmebol_copa_libertadores' => 'Copa Libertadores',

        // North America
        'soccer_usa_mls' => 'MLS',

        // Asia & Middle East
        'soccer_japan_j_league' => 'J1 League',
        'soccer_korea_kleague1' => 'K League 1',
        'soccer_china_superleague' => 'Chinese Super League',
        'soccer_saudi_professional_league' => 'Saudi Pro League',

        // Australia
        'soccer_australia_aleague' => 'A-League',

        // Africa
        'soccer_africa_cup_of_nations' => 'CAN',

        // International
        'soccer_fifa_world_cup' => 'World Cup',
        'soccer_uefa_european_championship' => 'Euro',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly RateLimiterInterface $rateLimiter,
        private readonly string $apiKey,
    ) {
    }

    /**
     * Retourne la liste des ligues supportées.
     *
     * @return array<string, string>
     */
    public function getSupportedLeagues(): array
    {
        return self::SOCCER_SPORTS;
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

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $allMatches = [];
        $dateStr = $date->format('Y-m-d');

        // Récupérer les matchs de toutes les ligues soccer disponibles
        foreach (self::SOCCER_SPORTS as $sportKey => $leagueName) {
            if (!$this->isAvailable()) {
                break; // Arrêter si on atteint la limite
            }

            $matches = $this->fetchMatchesForSport($sportKey, $leagueName, $dateStr);
            $allMatches = array_merge($allMatches, $matches);
        }

        // Mettre en cache pour 1 heure
        $this->cache->set($cacheKey, $allMatches, 3600);

        return $allMatches;
    }

    /**
     * Récupère les matchs d'une ligue spécifique.
     *
     * @return MatchData[]
     */
    private function fetchMatchesForSport(string $sportKey, string $leagueName, string $dateStr): array
    {
        $sportCacheKey = sprintf('odds_api_%s_%s', $sportKey, $dateStr);

        if ($this->cache->has($sportCacheKey)) {
            return $this->cache->get($sportCacheKey);
        }

        try {
            $this->rateLimiter->hit(self::RATE_LIMIT_KEY);

            $response = $this->httpClient->request('GET', self::API_BASE_URL."/sports/{$sportKey}/odds", [
                'query' => [
                    'apiKey' => $this->apiKey,
                    'regions' => 'eu,uk',
                    'markets' => 'h2h,spreads,totals',
                    'oddsFormat' => 'decimal',
                ],
            ]);

            $data = $response->toArray();
            $matches = $this->transformToMatchData($data, $leagueName);

            // Filtrer par date
            $matches = array_filter($matches, function (MatchData $match) use ($dateStr) {
                return $match->matchDate->format('Y-m-d') === $dateStr;
            });

            $this->cache->set($sportCacheKey, array_values($matches), 3600);

            return array_values($matches);
        } catch (\Exception $e) {
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
    private function transformToMatchData(array $data, ?string $leagueName = null): array
    {
        $matches = [];

        foreach ($data as $event) {
            $matches[] = $this->transformSingleMatch($event, $leagueName);
        }

        return $matches;
    }

    private function transformSingleMatch(array $event, ?string $leagueName = null): MatchData
    {
        $odds = $this->extractOdds($event['bookmakers'] ?? []);

        return new MatchData(
            externalId: $event['id'] ?? uniqid('odds_'),
            homeTeam: $event['home_team'] ?? 'Unknown',
            awayTeam: $event['away_team'] ?? 'Unknown',
            matchDate: new \DateTimeImmutable($event['commence_time'] ?? 'now'),
            league: $leagueName ?? $event['sport_title'] ?? 'Unknown',
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
