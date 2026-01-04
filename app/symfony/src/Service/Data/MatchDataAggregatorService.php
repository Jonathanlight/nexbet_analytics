<?php

declare(strict_types=1);

namespace App\Service\Data;

use App\Service\Data\Adapter\ApiFootballAdapter;
use App\Service\Data\Adapter\FootballDataApiAdapter;
use App\Service\Data\Adapter\OpenWeatherApiAdapter;
use App\Service\Data\Adapter\TheOddsApiAdapter;
use App\Service\Data\Adapter\UnibetAdapter;
use App\Service\Data\DTO\MatchData;
use App\Service\Data\Interface\MatchDataProviderInterface;

/**
 * Service d'agrégation de données de matchs depuis plusieurs sources.
 * Utilise le pattern Strategy pour sélectionner le meilleur provider disponible.
 */
final class MatchDataAggregatorService
{
    /**
     * @var MatchDataProviderInterface[]
     */
    private array $providers = [];

    public function __construct(
        private readonly TheOddsApiAdapter $oddsApiAdapter,
        private readonly FootballDataApiAdapter $footballDataAdapter,
        private readonly ApiFootballAdapter $apiFootballAdapter,
        private readonly OpenWeatherApiAdapter $weatherAdapter,
        private readonly UnibetAdapter $unibetAdapter,
    ) {
        // Ordre de priorité des providers
        // Unibet en premier car il a le plus de matchs (bookmaker direct)
        $this->providers = [
            $this->unibetAdapter,           // Bookmaker direct - tous les matchs disponibles
            $this->apiFootballAdapter,      // Le plus complet pour les données de match
            $this->footballDataAdapter,     // Bon pour les stats détaillées
            $this->oddsApiAdapter,          // Spécialisé dans les cotes
        ];
    }

    /**
     * Récupère les matchs du jour depuis tous les providers disponibles.
     * Agrège et déduplique les résultats.
     *
     * @return MatchData[]
     */
    public function fetchTodayMatches(): array
    {
        $today = new \DateTimeImmutable('today');

        return $this->fetchMatchesByDate($today);
    }

    /**
     * Récupère les matchs d'une date donnée depuis tous les providers.
     *
     * @return MatchData[]
     */
    public function fetchMatchesByDate(\DateTimeInterface $date): array
    {
        $allMatches = [];

        foreach ($this->providers as $provider) {
            if (!$provider->isAvailable()) {
                continue;
            }

            $matches = $provider->fetchMatchesByDate($date);
            $allMatches = array_merge($allMatches, $matches);
        }

        // Dédupliquer les matchs par équipes et date
        return $this->deduplicateMatches($allMatches);
    }

    /**
     * Récupère les détails enrichis d'un match.
     * Combine les données de plusieurs sources pour un match complet.
     */
    public function fetchEnrichedMatchDetails(string $matchId): ?MatchData
    {
        $matchData = null;

        // Essayer chaque provider jusqu'à trouver le match
        foreach ($this->providers as $provider) {
            if (!$provider->isAvailable()) {
                continue;
            }

            $matchData = $provider->fetchMatchDetails($matchId);
            if (null !== $matchData) {
                break;
            }
        }

        if (null === $matchData) {
            return null;
        }

        // Enrichir avec les cotes si disponibles
        $matchData = $this->enrichWithOdds($matchData);

        // Enrichir avec la météo si disponibles
        $matchData = $this->enrichWithWeather($matchData);

        return $matchData;
    }

    /**
     * Enrichit un match avec les données météo.
     */
    public function enrichWithWeather(MatchData $match): MatchData
    {
        if (null === $match->venue) {
            return $match;
        }

        // Extraire la ville du nom du stade si possible
        $city = $this->extractCityFromVenue($match->venue);
        if (null === $city) {
            return $match;
        }

        $weather = $this->weatherAdapter->fetchWeatherForVenue($city);
        if (null === $weather) {
            return $match;
        }

        // Créer un nouveau MatchData avec la météo
        return new MatchData(
            externalId: $match->externalId,
            homeTeam: $match->homeTeam,
            awayTeam: $match->awayTeam,
            matchDate: $match->matchDate,
            league: $match->league,
            sport: $match->sport,
            odds: $match->odds,
            statistics: $match->statistics,
            weather: $weather,
            venue: $match->venue,
            status: $match->status,
            homeScore: $match->homeScore,
            awayScore: $match->awayScore,
            events: $match->events,
        );
    }

    /**
     * Enrichit un match avec les cotes depuis The Odds API.
     */
    public function enrichWithOdds(MatchData $match): MatchData
    {
        if (!$this->oddsApiAdapter->isAvailable()) {
            return $match;
        }

        // Chercher le même match dans l'API des cotes
        $oddsMatches = $this->oddsApiAdapter->fetchMatchesByDate($match->matchDate);

        foreach ($oddsMatches as $oddsMatch) {
            if ($this->isSameMatch($match, $oddsMatch)) {
                // Fusionner les cotes
                $mergedOdds = array_merge($match->odds ?? [], $oddsMatch->odds ?? []);

                return new MatchData(
                    externalId: $match->externalId,
                    homeTeam: $match->homeTeam,
                    awayTeam: $match->awayTeam,
                    matchDate: $match->matchDate,
                    league: $match->league,
                    sport: $match->sport,
                    odds: $mergedOdds,
                    statistics: $match->statistics,
                    weather: $match->weather,
                    venue: $match->venue,
                    status: $match->status,
                    homeScore: $match->homeScore,
                    awayScore: $match->awayScore,
                    events: $match->events,
                );
            }
        }

        return $match;
    }

    /**
     * Récupère uniquement les matchs avec cotes disponibles.
     *
     * @return MatchData[]
     */
    public function fetchMatchesWithOdds(\DateTimeInterface $date): array
    {
        if (!$this->oddsApiAdapter->isAvailable()) {
            return [];
        }

        return $this->oddsApiAdapter->fetchMatchesByDate($date);
    }

    /**
     * Obtient les statistiques de disponibilité des providers.
     */
    public function getProvidersStatus(): array
    {
        return [
            'unibet' => [
                'name' => $this->unibetAdapter->getName(),
                'available' => $this->unibetAdapter->isAvailable(),
            ],
            'api_football' => [
                'name' => $this->apiFootballAdapter->getName(),
                'available' => $this->apiFootballAdapter->isAvailable(),
            ],
            'football_data' => [
                'name' => $this->footballDataAdapter->getName(),
                'available' => $this->footballDataAdapter->isAvailable(),
            ],
            'odds_api' => [
                'name' => $this->oddsApiAdapter->getName(),
                'available' => $this->oddsApiAdapter->isAvailable(),
            ],
            'weather' => [
                'name' => $this->weatherAdapter->getName(),
                'available' => true, // Weather n'a pas de rate limit strict
            ],
        ];
    }

    /**
     * Déduplique les matchs en fonction des équipes et de la date.
     *
     * @param MatchData[] $matches
     *
     * @return MatchData[]
     */
    private function deduplicateMatches(array $matches): array
    {
        $unique = [];
        $keys = [];

        foreach ($matches as $match) {
            // Créer une clé unique basée sur les équipes et la date
            $key = sprintf(
                '%s_%s_%s',
                mb_strtolower($match->homeTeam),
                mb_strtolower($match->awayTeam),
                $match->matchDate->format('Y-m-d')
            );

            if (!isset($keys[$key])) {
                $keys[$key] = true;
                $unique[] = $match;
            }
        }

        return $unique;
    }

    /**
     * Vérifie si deux MatchData représentent le même match.
     */
    private function isSameMatch(MatchData $match1, MatchData $match2): bool
    {
        $team1Home = mb_strtolower(trim($match1->homeTeam));
        $team1Away = mb_strtolower(trim($match1->awayTeam));
        $team2Home = mb_strtolower(trim($match2->homeTeam));
        $team2Away = mb_strtolower(trim($match2->awayTeam));

        // Vérifier si les équipes correspondent
        $teamsMatch = ($team1Home === $team2Home && $team1Away === $team2Away);

        // Vérifier si c'est le même jour
        $dateMatch = $match1->matchDate->format('Y-m-d') === $match2->matchDate->format('Y-m-d');

        return $teamsMatch && $dateMatch;
    }

    /**
     * Extrait le nom de la ville du nom du stade.
     * Logique simplifiée - peut être améliorée avec une mapping table.
     */
    private function extractCityFromVenue(string $venue): ?string
    {
        // Mapping des stades connus vers leurs villes
        $venueToCity = [
            'Parc des Princes' => 'Paris',
            'Orange Vélodrome' => 'Marseille',
            'Stade de France' => 'Paris',
            'Allianz Arena' => 'Munich',
            'Camp Nou' => 'Barcelona',
            'Old Trafford' => 'Manchester',
            'Anfield' => 'Liverpool',
            'Santiago Bernabéu' => 'Madrid',
            'Stamford Bridge' => 'London',
            'Emirates Stadium' => 'London',
        ];

        foreach ($venueToCity as $stadiumName => $city) {
            if (false !== stripos($venue, $stadiumName)) {
                return $city;
            }
        }

        // Fallback: essayer d'extraire le premier mot (souvent la ville)
        $parts = explode(' ', $venue);
        if (count($parts) > 0) {
            return $parts[0];
        }

        return null;
    }
}
