<?php

declare(strict_types=1);

namespace App\Service\Data\Adapter;

use App\Service\Data\DTO\MatchData;
use App\Service\Data\Interface\CacheInterface;
use App\Service\Data\Interface\MatchDataProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Adapter pour récupérer les matchs et cotes depuis Unibet France.
 * Utilise l'API interne Unibet (Kindred Group/Kambi).
 */
final class UnibetAdapter implements MatchDataProviderInterface
{
    // API Unibet via Kambi
    private const API_BASE_URL = 'https://eu-offering-api.kambicdn.com/offering/v2018/ub';

    // Catégories de sport (IDs Kambi)
    private const SPORT_FOOTBALL = 1000093190;
    private const SPORT_BASKETBALL = 1000093204;

    // Sports disponibles
    private const SPORTS = [
        'football' => 'football',
        'basketball' => 'basketball',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
    ) {
    }

    public function fetchTodayMatches(): array
    {
        $today = new \DateTimeImmutable('today');

        return $this->fetchMatchesByDate($today);
    }

    public function fetchMatchesByDate(\DateTimeInterface $date): array
    {
        $cacheKey = sprintf('unibet_matches_%s', $date->format('Y-m-d'));

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $allMatches = [];

        // Récupérer les matchs de football
        $allMatches = array_merge($allMatches, $this->fetchAllSportMatches('football', $date));

        // Récupérer les matchs de basketball
        $allMatches = array_merge($allMatches, $this->fetchAllSportMatches('basketball', $date));

        // Cache pour 15 minutes
        $this->cache->set($cacheKey, $allMatches, 900);

        return $allMatches;
    }

    /**
     * Récupère tous les matchs d'un sport.
     *
     * @return MatchData[]
     */
    public function fetchAllSportMatches(string $sport, \DateTimeInterface $date): array
    {
        $cacheKey = sprintf('unibet_%s_matches_%s', $sport, $date->format('Y-m-d'));

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        try {
            $response = $this->httpClient->request('GET', self::API_BASE_URL."/listView/{$sport}.json", [
                'query' => [
                    'lang' => 'fr_FR',
                    'market' => 'FR',
                    'client_id' => '2',
                    'channel_id' => '1',
                    'useCombined' => 'true',
                    'ncid' => time(),
                ],
                'headers' => $this->getHeaders(),
            ]);

            $data = $response->toArray();
            $matches = $this->processEventsResponse($data, $date, $sport);

            $this->cache->set($cacheKey, $matches, 900);

            return $matches;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function fetchMatchDetails(string $matchId): ?MatchData
    {
        $cacheKey = sprintf('unibet_match_%s', $matchId);

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        try {
            $response = $this->httpClient->request('GET', self::API_BASE_URL.'/betoffer/event/'.$matchId.'.json', [
                'query' => [
                    'lang' => 'fr_FR',
                    'market' => 'FR',
                    'client_id' => '2',
                    'channel_id' => '1',
                    'ncid' => time(),
                ],
                'headers' => $this->getHeaders(),
            ]);

            $data = $response->toArray();
            $event = $data['events'][0] ?? null;

            if (null === $event) {
                return null;
            }

            $match = $this->transformEvent($event, $data['betOffers'] ?? []);
            $this->cache->set($cacheKey, $match, 600);

            return $match;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getName(): string
    {
        return 'Unibet';
    }

    public function isAvailable(): bool
    {
        return true; // Pas de rate limit strict
    }

    /**
     * Récupère la liste des compétitions de football disponibles.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function fetchCompetitions(): array
    {
        $cacheKey = 'unibet_competitions';

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        try {
            $response = $this->httpClient->request('GET', self::API_BASE_URL.'/group/'.self::SPORT_FOOTBALL.'.json', [
                'query' => [
                    'lang' => 'fr_FR',
                    'market' => 'FR',
                    'client_id' => '2',
                    'channel_id' => '1',
                    'ncid' => time(),
                ],
                'headers' => $this->getHeaders(),
            ]);

            $data = $response->toArray();
            $competitions = [];

            foreach ($data['group']['groups'] ?? [] as $region) {
                foreach ($region['groups'] ?? [] as $competition) {
                    $competitions[] = [
                        'id' => $competition['id'],
                        'name' => $competition['name'] ?? 'Unknown',
                        'country' => $region['name'] ?? 'Unknown',
                    ];
                }
            }

            // Cache pour 6 heures
            $this->cache->set($cacheKey, $competitions, 21600);

            return $competitions;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Récupère les matchs d'une compétition spécifique.
     *
     * @return MatchData[]
     */
    private function fetchMatchesForCompetition(int $competitionId, \DateTimeInterface $date): array
    {
        try {
            $response = $this->httpClient->request('GET', self::API_BASE_URL.'/listView/football.json', [
                'query' => [
                    'lang' => 'fr_FR',
                    'market' => 'FR',
                    'client_id' => '2',
                    'channel_id' => '1',
                    'useCombined' => 'true',
                    'ncid' => time(),
                    'categoryGroup' => $competitionId,
                ],
                'headers' => $this->getHeaders(),
            ]);

            $data = $response->toArray();

            return $this->processEventsResponse($data, $date);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Récupère tous les matchs de football (fallback).
     *
     * @return MatchData[]
     */
    private function fetchAllFootballMatches(\DateTimeInterface $date): array
    {
        $cacheKey = sprintf('unibet_all_matches_%s', $date->format('Y-m-d'));

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        try {
            $response = $this->httpClient->request('GET', self::API_BASE_URL.'/listView/football.json', [
                'query' => [
                    'lang' => 'fr_FR',
                    'market' => 'FR',
                    'client_id' => '2',
                    'channel_id' => '1',
                    'useCombined' => 'true',
                    'ncid' => time(),
                ],
                'headers' => $this->getHeaders(),
            ]);

            $data = $response->toArray();
            $matches = $this->processEventsResponse($data, $date);

            $this->cache->set($cacheKey, $matches, 900);

            return $matches;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Traite la réponse des événements.
     *
     * @return MatchData[]
     */
    private function processEventsResponse(array $data, \DateTimeInterface $targetDate, string $sport = 'football'): array
    {
        $matches = [];
        $events = $data['events'] ?? [];
        $targetDateStr = $targetDate->format('Y-m-d');

        foreach ($events as $eventWrapper) {
            $event = $eventWrapper['event'] ?? $eventWrapper;
            $betOffers = $eventWrapper['betOffers'] ?? [];
            $liveData = $eventWrapper['liveData'] ?? null;

            // Filtrer les esports
            $path = $event['path'] ?? [];
            $isEsports = false;
            foreach ($path as $p) {
                if (false !== stripos($p['name'] ?? '', 'esports')) {
                    $isEsports = true;
                    break;
                }
            }
            if ($isEsports) {
                continue;
            }

            // Filtrer par date
            $eventDate = new \DateTimeImmutable($event['start'] ?? 'now');
            if ($eventDate->format('Y-m-d') !== $targetDateStr) {
                continue;
            }

            $matches[] = $this->transformEvent($event, $betOffers, $liveData, $sport);
        }

        return $matches;
    }

    /**
     * Transforme un événement Unibet en MatchData.
     */
    private function transformEvent(array $event, array $betOffers, ?array $liveData = null, string $sport = 'football'): MatchData
    {
        $homeTeam = $event['homeName'] ?? 'Unknown';
        $awayTeam = $event['awayName'] ?? 'Unknown';

        // Extraire la ligue depuis le path (dernier niveau avant le match)
        $path = $event['path'] ?? [];
        $league = $event['group'] ?? 'Unknown';
        if (count($path) >= 2) {
            // Format: Sport > Région > Ligue
            $league = $path[count($path) - 1]['name'] ?? $league;
        }

        // Extraire les cotes
        $odds = $this->extractOdds($betOffers);

        // Score depuis liveData si disponible
        $homeScore = null;
        $awayScore = null;
        if (null !== $liveData && isset($liveData['score'])) {
            $homeScore = (int) ($liveData['score']['home'] ?? 0);
            $awayScore = (int) ($liveData['score']['away'] ?? 0);
        }

        return new MatchData(
            externalId: 'unibet_'.($event['id'] ?? uniqid()),
            homeTeam: $homeTeam,
            awayTeam: $awayTeam,
            matchDate: new \DateTimeImmutable($event['start'] ?? 'now'),
            league: $league,
            sport: $sport,
            odds: $odds,
            venue: null,
            status: $this->mapStatus($event['state'] ?? 'NOT_STARTED'),
            homeScore: $homeScore,
            awayScore: $awayScore,
        );
    }

    /**
     * Extrait les cotes des betOffers.
     */
    private function extractOdds(array $betOffers): array
    {
        $odds = [];

        foreach ($betOffers as $offer) {
            $criterionLabel = $offer['criterion']['label'] ?? ($offer['criterion']['englishLabel'] ?? 'unknown');
            $betType = $this->mapBetType($criterionLabel);

            if (null === $betType) {
                continue;
            }

            foreach ($offer['outcomes'] ?? [] as $outcome) {
                // Label: "1" pour home, "X" pour draw, "2" pour away
                $label = $outcome['label'] ?? ($outcome['englishLabel'] ?? 'unknown');
                $rawOdds = $outcome['odds'] ?? null;

                // Skip si pas de cotes ou outcome suspendu
                if (null === $rawOdds || ($outcome['status'] ?? '') === 'SUSPENDED') {
                    continue;
                }

                // Unibet envoie les cotes * 1000 (ex: 1530 = 1.53)
                $oddValue = $rawOdds / 1000;

                if (!isset($odds[$betType])) {
                    $odds[$betType] = [];
                }

                // Mapper les labels vers des noms standards
                $mappedLabel = match ($label) {
                    '1' => 'home',
                    'X' => 'draw',
                    '2' => 'away',
                    default => $label,
                };

                $odds[$betType][$mappedLabel] = $oddValue;
            }
        }

        return $odds;
    }

    /**
     * Map le type de pari Unibet vers un format standard.
     */
    private function mapBetType(string $criterionName): ?string
    {
        $mapping = [
            // === FOOTBALL ===
            // Match result / 1X2
            'Résultat du match' => '1X2',
            'Match Winner' => '1X2',
            'Match Result' => '1X2',
            'Match' => '1X2',
            'Temps réglementaire' => '1X2',
            'Full Time' => '1X2',

            // Double chance
            'Double chance' => 'DC',
            'Double Chance' => 'DC',

            // Both teams to score
            'But pour les 2 équipes' => 'BTTS',
            'Both Teams to Score' => 'BTTS',
            'Les 2 équipes marquent' => 'BTTS',

            // Over/Under
            'Total de buts' => 'OU',
            'Over/Under' => 'OU',
            'Total Goals' => 'OU',

            // Correct score
            'Score Exact' => 'CS',
            'Correct Score' => 'CS',

            // Half time
            '1ère Mi-temps - Résultat' => 'HT1X2',
            'Half Time Result' => 'HT1X2',
            '1ère mi-temps' => 'HT1X2',
            'First Half' => 'HT1X2',

            // === BASKETBALL ===
            // Match winner (pas de draw en basket)
            'Vainqueur (Prolongations incluses)' => 'ML',
            'Vainqueur du match' => 'ML',
            'Vainqueur' => 'ML',
            'Winner' => 'ML',
            'Money Line' => 'ML',
            'Moneyline' => 'ML',

            // Total points
            'Total de points' => 'OU',
            'Total de points (Prolongations incluses)' => 'OU',
            'Total Points' => 'OU',

            // Handicap
            'Handicap' => 'AH',
            'Spread' => 'AH',
            'Point Spread' => 'AH',
            'Marge du vainqueur' => 'AH',
            'Marge du vainqueur (Prolongations incluses)' => 'AH',

            // Quarter/Half
            '1er Quart-temps' => 'Q1',
            '1st Quarter' => 'Q1',
            'Mi-temps / Fin de match' => 'HT_FT',

            // Overtime
            'Prolongations Oui/Non' => 'OT',
        ];

        return $mapping[$criterionName] ?? null;
    }

    /**
     * Map le statut Unibet vers un format compatible avec l'enum MatchStatus.
     */
    private function mapStatus(string $state): string
    {
        return match ($state) {
            'NOT_STARTED' => 'scheduled',
            'STARTED', 'LIVE' => 'live',
            'FINISHED', 'ENDED' => 'finished',
            'POSTPONED' => 'postponed',
            'CANCELLED' => 'cancelled',
            default => 'scheduled',
        };
    }

    private function getHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
            'Accept-Language' => 'fr-FR,fr;q=0.9',
            'Origin' => 'https://www.unibet.fr',
            'Referer' => 'https://www.unibet.fr/',
        ];
    }
}
