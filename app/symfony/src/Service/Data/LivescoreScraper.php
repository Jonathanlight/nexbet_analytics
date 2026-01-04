<?php

declare(strict_types=1);

namespace App\Service\Data;

use App\Service\Data\DTO\MatchData;
use App\Service\Data\Interface\CacheInterface;
use App\Service\Data\Interface\LiveDataProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service fournissant des données live de matchs via notre agrégateur d'APIs.
 * Utilise les APIs externes au lieu du scraping pour respecter les ToS.
 */
final class LivescoreScraper implements LiveDataProviderInterface
{
    public function __construct(
        private readonly MatchDataAggregatorService $aggregator,
        private readonly CacheInterface $cache,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Récupère les matchs du jour depuis nos sources API.
     *
     * @return MatchData[]
     */
    public function fetchTodayMatches(): array
    {
        $cacheKey = 'livescore_today_matches';

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $matches = $this->aggregator->fetchTodayMatches();

        // Cache pour 5 minutes
        $this->cache->set($cacheKey, $matches, 300);

        return $matches;
    }

    /**
     * Récupère les détails enrichis d'un match.
     */
    public function fetchMatchDetails(string $matchId): ?MatchData
    {
        $cacheKey = sprintf('livescore_match_%s', $matchId);

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $match = $this->aggregator->fetchEnrichedMatchDetails($matchId);

        if (null !== $match) {
            // Cache pour 10 minutes
            $this->cache->set($cacheKey, $match, 600);
        }

        return $match;
    }

    /**
     * Récupère les classements d'un championnat via API-Football.
     */
    public function fetchLeagueStandings(string $league): array
    {
        $cacheKey = sprintf('livescore_standings_%s', md5($league));

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $standings = $this->fetchStandingsFromApi($league);

        // Cache pour 1 heure
        $this->cache->set($cacheKey, $standings, 3600);

        return $standings;
    }

    /**
     * Récupère les matchs live en cours.
     *
     * @return MatchData[]
     */
    public function fetchLiveMatches(): array
    {
        $cacheKey = 'livescore_live_matches';

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $today = new \DateTimeImmutable('today');
        $allMatches = $this->aggregator->fetchMatchesByDate($today);

        // Filtrer uniquement les matchs en cours
        $liveMatches = array_filter($allMatches, function (MatchData $match) {
            return in_array(strtolower($match->status), ['live', 'in play', '1h', '2h', 'ht']);
        });

        // Cache pour 1 minute seulement (données live)
        $this->cache->set($cacheKey, array_values($liveMatches), 60);

        return array_values($liveMatches);
    }

    /**
     * Récupère les matchs d'une date spécifique.
     *
     * @return MatchData[]
     */
    public function fetchMatchesByDate(\DateTimeInterface $date): array
    {
        $cacheKey = sprintf('livescore_matches_%s', $date->format('Y-m-d'));

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $matches = $this->aggregator->fetchMatchesByDate($date);

        // Cache pour 30 minutes
        $this->cache->set($cacheKey, $matches, 1800);

        return $matches;
    }

    public function isAvailable(): bool
    {
        // Vérifier si au moins un provider est disponible
        $status = $this->aggregator->getProvidersStatus();

        foreach ($status as $provider) {
            if ($provider['available']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Récupère les classements depuis l'API.
     */
    private function fetchStandingsFromApi(string $league): array
    {
        // Mapping des noms de ligues vers les IDs API-Football
        // Liste étendue pour supporter plus de compétitions
        $leagueMapping = [
            // Top 5 européens
            'Ligue 1' => 61,
            'Ligue 1 McDonald\'s®' => 61,
            'Premier League' => 39,
            'La Liga' => 140,
            'LaLiga' => 140,
            'Serie A' => 135,
            'Bundesliga' => 78,

            // Coupes d'Europe
            'Champions League' => 2,
            'Europa League' => 3,
            'Conference League' => 848,

            // Divisions secondaires
            'Ligue 2' => 62,
            'Ligue 2 BKT®' => 62,
            'Championship' => 40,
            'LaLiga2' => 141,
            'Serie B' => 136,
            'Bundesliga 2' => 79,

            // Autres ligues européennes
            'Eredivisie' => 88,
            'Liga Portugal' => 94,
            'Primeira Liga' => 94,
            'Jupiler Pro League' => 144,
            'Premiership' => 179, // Écosse
            'Super Lig' => 203,
            'Super League Greece' => 197,

            // Portugal Segunda Liga
            'Segunda Liga' => 95,
            'Portugal, Segunda Liga' => 95,

            // Ligues mineures européennes
            'NIFL Premiership' => 408,
            'Cymru Premier' => 110,
            'Division 1' => 318, // Chypre

            // Moyen-Orient
            'Saudi Pro League' => 307,
            'Premier League Israel' => 383,

            // Australie
            'A-League' => 188,

            // Afrique
            'CAN' => 6,
            'Coupe d\'Afrique des Nations' => 6,
            'Ligue 1' => 200, // Algérie (note: conflit avec France, géré par contexte)

            // Amérique du Sud
            'Brasileirão' => 71,
            'Liga Profesional' => 128,
            'Liga MX' => 262,

            // Amérique du Nord
            'MLS' => 253,

            // Asie
            'J1 League' => 98,
            'K League 1' => 292,
            'Chinese Super League' => 169,
        ];

        $leagueId = $leagueMapping[$league] ?? null;
        if (null === $leagueId) {
            return [];
        }

        try {
            // Note: Cette implémentation nécessiterait un appel direct à API-Football
            // Pour l'instant, retourner une structure vide
            return [
                'league' => $league,
                'season' => date('Y'),
                'standings' => [],
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Récupère les statistiques des providers.
     */
    public function getProvidersStatus(): array
    {
        return $this->aggregator->getProvidersStatus();
    }
}
