<?php

declare(strict_types=1);

namespace App\Service\Data\DTO;

/**
 * Data Transfer Object pour les données de match.
 */
final readonly class MatchData
{
    public function __construct(
        public string $externalId,
        public string $homeTeam,
        public string $awayTeam,
        public \DateTimeImmutable $matchDate,
        public string $league,
        public string $sport = 'football',
        public ?array $odds = null,
        public ?array $statistics = null,
        public ?WeatherData $weather = null,
        public ?string $venue = null,
        public ?string $status = 'scheduled',
        public ?int $homeScore = null,
        public ?int $awayScore = null,
        public ?array $lineup = null,
        public ?array $events = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'home_team' => $this->homeTeam,
            'away_team' => $this->awayTeam,
            'match_date' => $this->matchDate,
            'league' => $this->league,
            'sport' => $this->sport,
            'odds' => $this->odds,
            'statistics' => $this->statistics,
            'weather' => $this->weather?->toArray(),
            'venue' => $this->venue,
            'status' => $this->status,
            'home_score' => $this->homeScore,
            'away_score' => $this->awayScore,
            'lineup' => $this->lineup,
            'events' => $this->events,
        ];
    }
}
