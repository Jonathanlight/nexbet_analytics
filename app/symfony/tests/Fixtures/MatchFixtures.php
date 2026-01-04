<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

use App\Entity\FootballMatch;
use App\Entity\BasketballMatch;
use App\Entity\HockeyMatch;
use App\Entity\Odds;
use DateTimeImmutable;

/**
 * Factory de fixtures pour les matchs de tous les sports.
 * Utilisé dans les tests unitaires et d'intégration.
 */
final class MatchFixtures
{
    /**
     * Crée un match de football avec des données par défaut.
     */
    public static function createFootballMatch(array $overrides = []): FootballMatch
    {
        $match = new FootballMatch();

        $defaults = [
            'externalId' => 'ext_' . uniqid(),
            'homeTeam' => 'Paris Saint-Germain',
            'awayTeam' => 'Olympique de Marseille',
            'league' => 'Ligue 1',
            'country' => 'France',
            'matchDate' => new DateTimeImmutable('+1 day'),
            'status' => 'scheduled',
            'homeScore' => null,
            'awayScore' => null,
            'homeTeamRanking' => 1,
            'awayTeamRanking' => 3,
            'homeTeamForm' => 'WWDWW',
            'awayTeamForm' => 'WDLWW',
            'venue' => 'Parc des Princes',
        ];

        $data = array_merge($defaults, $overrides);

        $match->setExternalId($data['externalId']);
        $match->setHomeTeam($data['homeTeam']);
        $match->setAwayTeam($data['awayTeam']);
        $match->setLeague($data['league']);
        $match->setCountry($data['country']);
        $match->setMatchDate($data['matchDate']);
        $match->setStatus($data['status']);

        if ($data['homeScore'] !== null) {
            $match->setHomeScore($data['homeScore']);
        }
        if ($data['awayScore'] !== null) {
            $match->setAwayScore($data['awayScore']);
        }
        if (method_exists($match, 'setHomeTeamRanking')) {
            $match->setHomeTeamRanking($data['homeTeamRanking']);
        }
        if (method_exists($match, 'setAwayTeamRanking')) {
            $match->setAwayTeamRanking($data['awayTeamRanking']);
        }
        if (method_exists($match, 'setHomeTeamForm')) {
            $match->setHomeTeamForm($data['homeTeamForm']);
        }
        if (method_exists($match, 'setAwayTeamForm')) {
            $match->setAwayTeamForm($data['awayTeamForm']);
        }
        if (method_exists($match, 'setVenue')) {
            $match->setVenue($data['venue']);
        }

        return $match;
    }

    /**
     * Crée un match de football terminé avec score.
     */
    public static function createFinishedFootballMatch(
        int $homeScore = 2,
        int $awayScore = 1,
        array $overrides = []
    ): FootballMatch {
        return self::createFootballMatch(array_merge([
            'status' => 'finished',
            'homeScore' => $homeScore,
            'awayScore' => $awayScore,
            'matchDate' => new DateTimeImmutable('-1 day'),
        ], $overrides));
    }

    /**
     * Crée un match de basketball avec des données par défaut.
     */
    public static function createBasketballMatch(array $overrides = []): BasketballMatch
    {
        $match = new BasketballMatch();

        $defaults = [
            'externalId' => 'bsk_' . uniqid(),
            'homeTeam' => 'Los Angeles Lakers',
            'awayTeam' => 'Boston Celtics',
            'league' => 'NBA',
            'country' => 'USA',
            'matchDate' => new DateTimeImmutable('+1 day'),
            'status' => 'scheduled',
            'homeScore' => null,
            'awayScore' => null,
        ];

        $data = array_merge($defaults, $overrides);

        $match->setExternalId($data['externalId']);
        $match->setHomeTeam($data['homeTeam']);
        $match->setAwayTeam($data['awayTeam']);
        $match->setLeague($data['league']);
        $match->setCountry($data['country']);
        $match->setMatchDate($data['matchDate']);
        $match->setStatus($data['status']);

        if ($data['homeScore'] !== null) {
            $match->setHomeScore($data['homeScore']);
        }
        if ($data['awayScore'] !== null) {
            $match->setAwayScore($data['awayScore']);
        }

        return $match;
    }

    /**
     * Crée un match de basketball terminé.
     */
    public static function createFinishedBasketballMatch(
        int $homeScore = 112,
        int $awayScore = 105,
        array $overrides = []
    ): BasketballMatch {
        return self::createBasketballMatch(array_merge([
            'status' => 'finished',
            'homeScore' => $homeScore,
            'awayScore' => $awayScore,
            'matchDate' => new DateTimeImmutable('-1 day'),
        ], $overrides));
    }

    /**
     * Crée un match de hockey avec des données par défaut.
     */
    public static function createHockeyMatch(array $overrides = []): HockeyMatch
    {
        $match = new HockeyMatch();

        $defaults = [
            'externalId' => 'hky_' . uniqid(),
            'homeTeam' => 'Montreal Canadiens',
            'awayTeam' => 'Toronto Maple Leafs',
            'league' => 'NHL',
            'country' => 'Canada',
            'matchDate' => new DateTimeImmutable('+1 day'),
            'status' => 'scheduled',
            'homeScore' => null,
            'awayScore' => null,
        ];

        $data = array_merge($defaults, $overrides);

        $match->setExternalId($data['externalId']);
        $match->setHomeTeam($data['homeTeam']);
        $match->setAwayTeam($data['awayTeam']);
        $match->setLeague($data['league']);
        $match->setCountry($data['country']);
        $match->setMatchDate($data['matchDate']);
        $match->setStatus($data['status']);

        if ($data['homeScore'] !== null) {
            $match->setHomeScore($data['homeScore']);
        }
        if ($data['awayScore'] !== null) {
            $match->setAwayScore($data['awayScore']);
        }

        return $match;
    }

    /**
     * Crée un match de hockey terminé.
     */
    public static function createFinishedHockeyMatch(
        int $homeScore = 4,
        int $awayScore = 2,
        array $overrides = []
    ): HockeyMatch {
        return self::createHockeyMatch(array_merge([
            'status' => 'finished',
            'homeScore' => $homeScore,
            'awayScore' => $awayScore,
            'matchDate' => new DateTimeImmutable('-1 day'),
        ], $overrides));
    }

    /**
     * Crée une collection de matchs de football.
     *
     * @return FootballMatch[]
     */
    public static function createFootballMatchCollection(int $count = 5): array
    {
        $matches = [];
        $teams = [
            ['Paris Saint-Germain', 'Olympique de Marseille'],
            ['Real Madrid', 'Barcelona'],
            ['Manchester United', 'Manchester City'],
            ['Bayern Munich', 'Borussia Dortmund'],
            ['Juventus', 'AC Milan'],
            ['Liverpool', 'Chelsea'],
            ['Inter Milan', 'AS Roma'],
            ['Arsenal', 'Tottenham'],
        ];

        for ($i = 0; $i < $count; $i++) {
            $teamPair = $teams[$i % count($teams)];
            $matches[] = self::createFootballMatch([
                'homeTeam' => $teamPair[0],
                'awayTeam' => $teamPair[1],
                'matchDate' => new DateTimeImmutable("+{$i} days"),
            ]);
        }

        return $matches;
    }

    /**
     * Crée des cotes pour un match.
     */
    public static function createOdds(array $overrides = []): Odds
    {
        $odds = new Odds();

        $defaults = [
            'bookmaker' => 'Betway',
            'homeWin' => 1.85,
            'draw' => 3.50,
            'awayWin' => 4.20,
            'over25' => 1.75,
            'under25' => 2.10,
            'btts' => 1.90,
            'bttsNo' => 1.85,
            'updatedAt' => new DateTimeImmutable(),
        ];

        $data = array_merge($defaults, $overrides);

        if (method_exists($odds, 'setBookmaker')) {
            $odds->setBookmaker($data['bookmaker']);
        }
        if (method_exists($odds, 'setHomeWin')) {
            $odds->setHomeWin($data['homeWin']);
        }
        if (method_exists($odds, 'setDraw')) {
            $odds->setDraw($data['draw']);
        }
        if (method_exists($odds, 'setAwayWin')) {
            $odds->setAwayWin($data['awayWin']);
        }

        return $odds;
    }
}