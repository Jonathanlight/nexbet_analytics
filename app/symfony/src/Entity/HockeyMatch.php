<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\HockeyMatchRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HockeyMatchRepository::class)]
#[ORM\Table(name: 'hockey_matches')]
#[ORM\Index(columns: ['match_date'], name: 'idx_hk_match_date')]
#[ORM\Index(columns: ['league'], name: 'idx_hk_match_league')]
#[ORM\Index(columns: ['status'], name: 'idx_hk_match_status')]
class HockeyMatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Team $homeTeam;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Team $awayTeam;

    #[ORM\Column(length: 255)]
    private string $league;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $matchDate;

    #[ORM\Column(length: 50)]
    private string $status = 'scheduled';

    // Scores par periode
    #[ORM\Column(nullable: true)]
    private ?int $homeScoreP1 = null;

    #[ORM\Column(nullable: true)]
    private ?int $awayScoreP1 = null;

    #[ORM\Column(nullable: true)]
    private ?int $homeScoreP2 = null;

    #[ORM\Column(nullable: true)]
    private ?int $awayScoreP2 = null;

    #[ORM\Column(nullable: true)]
    private ?int $homeScoreP3 = null;

    #[ORM\Column(nullable: true)]
    private ?int $awayScoreP3 = null;

    #[ORM\Column(nullable: true)]
    private ?int $homeScoreOt = null; // Overtime

    #[ORM\Column(nullable: true)]
    private ?int $awayScoreOt = null;

    #[ORM\Column(nullable: true)]
    private ?int $homeShootout = null; // Tirs au but

    #[ORM\Column(nullable: true)]
    private ?int $awayShootout = null;

    #[ORM\Column(nullable: true)]
    private ?int $homeFinalScore = null;

    #[ORM\Column(nullable: true)]
    private ?int $awayFinalScore = null;

    // Statistiques detaillees
    #[ORM\Column(type: 'json', nullable: true)]
    private array $homeStats = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private array $awayStats = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private array $headToHeadStats = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $venue = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHomeTeam(): Team
    {
        return $this->homeTeam;
    }

    public function setHomeTeam(Team $homeTeam): self
    {
        $this->homeTeam = $homeTeam;

        return $this;
    }

    public function getAwayTeam(): Team
    {
        return $this->awayTeam;
    }

    public function setAwayTeam(Team $awayTeam): self
    {
        $this->awayTeam = $awayTeam;

        return $this;
    }

    public function getLeague(): string
    {
        return $this->league;
    }

    public function setLeague(string $league): self
    {
        $this->league = $league;

        return $this;
    }

    public function getMatchDate(): \DateTimeImmutable
    {
        return $this->matchDate;
    }

    public function setMatchDate(\DateTimeImmutable $matchDate): self
    {
        $this->matchDate = $matchDate;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getHomeScoreP1(): ?int
    {
        return $this->homeScoreP1;
    }

    public function setHomeScoreP1(?int $homeScoreP1): self
    {
        $this->homeScoreP1 = $homeScoreP1;

        return $this;
    }

    public function getAwayScoreP1(): ?int
    {
        return $this->awayScoreP1;
    }

    public function setAwayScoreP1(?int $awayScoreP1): self
    {
        $this->awayScoreP1 = $awayScoreP1;

        return $this;
    }

    public function getHomeScoreP2(): ?int
    {
        return $this->homeScoreP2;
    }

    public function setHomeScoreP2(?int $homeScoreP2): self
    {
        $this->homeScoreP2 = $homeScoreP2;

        return $this;
    }

    public function getAwayScoreP2(): ?int
    {
        return $this->awayScoreP2;
    }

    public function setAwayScoreP2(?int $awayScoreP2): self
    {
        $this->awayScoreP2 = $awayScoreP2;

        return $this;
    }

    public function getHomeScoreP3(): ?int
    {
        return $this->homeScoreP3;
    }

    public function setHomeScoreP3(?int $homeScoreP3): self
    {
        $this->homeScoreP3 = $homeScoreP3;

        return $this;
    }

    public function getAwayScoreP3(): ?int
    {
        return $this->awayScoreP3;
    }

    public function setAwayScoreP3(?int $awayScoreP3): self
    {
        $this->awayScoreP3 = $awayScoreP3;

        return $this;
    }

    public function getHomeScoreOt(): ?int
    {
        return $this->homeScoreOt;
    }

    public function setHomeScoreOt(?int $homeScoreOt): self
    {
        $this->homeScoreOt = $homeScoreOt;

        return $this;
    }

    public function getAwayScoreOt(): ?int
    {
        return $this->awayScoreOt;
    }

    public function setAwayScoreOt(?int $awayScoreOt): self
    {
        $this->awayScoreOt = $awayScoreOt;

        return $this;
    }

    public function getHomeShootout(): ?int
    {
        return $this->homeShootout;
    }

    public function setHomeShootout(?int $homeShootout): self
    {
        $this->homeShootout = $homeShootout;

        return $this;
    }

    public function getAwayShootout(): ?int
    {
        return $this->awayShootout;
    }

    public function setAwayShootout(?int $awayShootout): self
    {
        $this->awayShootout = $awayShootout;

        return $this;
    }

    public function getHomeFinalScore(): ?int
    {
        return $this->homeFinalScore;
    }

    public function setHomeFinalScore(?int $homeFinalScore): self
    {
        $this->homeFinalScore = $homeFinalScore;

        return $this;
    }

    public function getAwayFinalScore(): ?int
    {
        return $this->awayFinalScore;
    }

    public function setAwayFinalScore(?int $awayFinalScore): self
    {
        $this->awayFinalScore = $awayFinalScore;

        return $this;
    }

    public function getHomeStats(): array
    {
        return $this->homeStats;
    }

    public function setHomeStats(array $homeStats): self
    {
        $this->homeStats = $homeStats;

        return $this;
    }

    public function getAwayStats(): array
    {
        return $this->awayStats;
    }

    public function setAwayStats(array $awayStats): self
    {
        $this->awayStats = $awayStats;

        return $this;
    }

    public function getHeadToHeadStats(): array
    {
        return $this->headToHeadStats;
    }

    public function setHeadToHeadStats(array $headToHeadStats): self
    {
        $this->headToHeadStats = $headToHeadStats;

        return $this;
    }

    public function getVenue(): ?string
    {
        return $this->venue;
    }

    public function setVenue(?string $venue): self
    {
        $this->venue = $venue;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // Methodes utilitaires

    public function getTotalGoals(): ?int
    {
        if (null === $this->homeFinalScore || null === $this->awayFinalScore) {
            return null;
        }

        return $this->homeFinalScore + $this->awayFinalScore;
    }

    public function getGoalDifference(): ?int
    {
        if (null === $this->homeFinalScore || null === $this->awayFinalScore) {
            return null;
        }

        return abs($this->homeFinalScore - $this->awayFinalScore);
    }

    public function hadOvertime(): bool
    {
        return null !== $this->homeScoreOt || null !== $this->awayScoreOt;
    }

    public function hadShootout(): bool
    {
        return null !== $this->homeShootout || null !== $this->awayShootout;
    }

    public function getRegulationScore(): array
    {
        $homeReg = ($this->homeScoreP1 ?? 0) + ($this->homeScoreP2 ?? 0) + ($this->homeScoreP3 ?? 0);
        $awayReg = ($this->awayScoreP1 ?? 0) + ($this->awayScoreP2 ?? 0) + ($this->awayScoreP3 ?? 0);

        return ['home' => $homeReg, 'away' => $awayReg];
    }
}
