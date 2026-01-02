<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'basketball_matches')]
#[ORM\Index(columns: ['match_date'], name: 'idx_bb_match_date')]
#[ORM\Index(columns: ['league'], name: 'idx_bb_match_league')]
class BasketballMatch
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

    #[ORM\Column(nullable: true)]
    private ?int $homeScoreQ1 = null;

    #[ORM\Column(nullable: true)]
    private ?int $awayScoreQ1 = null;

    #[ORM\Column(nullable: true)]
    private ?int $homeScoreQ2 = null;

    #[ORM\Column(nullable: true)]
    private ?int $awayScoreQ2 = null;

    #[ORM\Column(nullable: true)]
    private ?int $homeScoreQ3 = null;

    #[ORM\Column(nullable: true)]
    private ?int $awayScoreQ3 = null;

    #[ORM\Column(nullable: true)]
    private ?int $homeScoreQ4 = null;

    #[ORM\Column(nullable: true)]
    private ?int $awayScoreQ4 = null;

    #[ORM\Column(nullable: true)]
    private ?int $homeScoreOt = null; // Overtime

    #[ORM\Column(nullable: true)]
    private ?int $awayScoreOt = null;

    #[ORM\Column(nullable: true)]
    private ?int $homeFinalScore = null;

    #[ORM\Column(nullable: true)]
    private ?int $awayFinalScore = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private array $homeStats = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private array $awayStats = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private array $headToHeadStats = [];

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

    public function getHomeScoreQ1(): ?int
    {
        return $this->homeScoreQ1;
    }

    public function setHomeScoreQ1(?int $homeScoreQ1): self
    {
        $this->homeScoreQ1 = $homeScoreQ1;

        return $this;
    }

    public function getAwayScoreQ1(): ?int
    {
        return $this->awayScoreQ1;
    }

    public function setAwayScoreQ1(?int $awayScoreQ1): self
    {
        $this->awayScoreQ1 = $awayScoreQ1;

        return $this;
    }

    public function getHomeScoreQ2(): ?int
    {
        return $this->homeScoreQ2;
    }

    public function setHomeScoreQ2(?int $homeScoreQ2): self
    {
        $this->homeScoreQ2 = $homeScoreQ2;

        return $this;
    }

    public function getAwayScoreQ2(): ?int
    {
        return $this->awayScoreQ2;
    }

    public function setAwayScoreQ2(?int $awayScoreQ2): self
    {
        $this->awayScoreQ2 = $awayScoreQ2;

        return $this;
    }

    public function getHomeScoreQ3(): ?int
    {
        return $this->homeScoreQ3;
    }

    public function setHomeScoreQ3(?int $homeScoreQ3): self
    {
        $this->homeScoreQ3 = $homeScoreQ3;

        return $this;
    }

    public function getAwayScoreQ3(): ?int
    {
        return $this->awayScoreQ3;
    }

    public function setAwayScoreQ3(?int $awayScoreQ3): self
    {
        $this->awayScoreQ3 = $awayScoreQ3;

        return $this;
    }

    public function getHomeScoreQ4(): ?int
    {
        return $this->homeScoreQ4;
    }

    public function setHomeScoreQ4(?int $homeScoreQ4): self
    {
        $this->homeScoreQ4 = $homeScoreQ4;

        return $this;
    }

    public function getAwayScoreQ4(): ?int
    {
        return $this->awayScoreQ4;
    }

    public function setAwayScoreQ4(?int $awayScoreQ4): self
    {
        $this->awayScoreQ4 = $awayScoreQ4;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getHomeScoreHalfTime(): ?int
    {
        if (null === $this->homeScoreQ1 || null === $this->homeScoreQ2) {
            return null;
        }

        return $this->homeScoreQ1 + $this->homeScoreQ2;
    }

    public function getAwayScoreHalfTime(): ?int
    {
        if (null === $this->awayScoreQ1 || null === $this->awayScoreQ2) {
            return null;
        }

        return $this->awayScoreQ1 + $this->awayScoreQ2;
    }

    public function getTotalPoints(): ?int
    {
        if (null === $this->homeFinalScore || null === $this->awayFinalScore) {
            return null;
        }

        return $this->homeFinalScore + $this->awayFinalScore;
    }

    public function getPointDifference(): ?int
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
}
