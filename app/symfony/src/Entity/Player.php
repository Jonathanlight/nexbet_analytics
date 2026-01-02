<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PlayerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlayerRepository::class)]
#[ORM\Table(name: 'players')]
#[ORM\Index(columns: ['team_id'], name: 'idx_player_team')]
#[ORM\Index(columns: ['position'], name: 'idx_player_position')]
class Player
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: Team::class, inversedBy: 'players')]
    #[ORM\JoinColumn(nullable: false)]
    private Team $team;

    #[ORM\Column(length: 50)]
    private string $position; // FW, MF, DF, GK pour football / PG, SG, SF, PF, C pour basket

    #[ORM\Column(nullable: true)]
    private ?int $jerseyNumber = null;

    #[ORM\Column(type: 'json')]
    private array $statistics = [];

    #[ORM\Column(nullable: true)]
    private ?int $goalsScored = null;

    #[ORM\Column(nullable: true)]
    private ?int $assists = null;

    #[ORM\Column(nullable: true)]
    private ?int $yellowCards = null;

    #[ORM\Column(nullable: true)]
    private ?int $redCards = null;

    #[ORM\Column(nullable: true)]
    private ?int $matchesPlayed = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $avgRating = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $xG = null; // Expected Goals

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $xA = null; // Expected Assists

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $scoringProbability = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isInjured = false;

    #[ORM\Column(type: 'boolean')]
    private bool $isSuspended = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photoUrl = null;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getTeam(): Team
    {
        return $this->team;
    }

    public function setTeam(Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    public function getPosition(): string
    {
        return $this->position;
    }

    public function setPosition(string $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getJerseyNumber(): ?int
    {
        return $this->jerseyNumber;
    }

    public function setJerseyNumber(?int $jerseyNumber): self
    {
        $this->jerseyNumber = $jerseyNumber;

        return $this;
    }

    public function getStatistics(): array
    {
        return $this->statistics;
    }

    public function setStatistics(array $statistics): self
    {
        $this->statistics = $statistics;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getGoalsScored(): ?int
    {
        return $this->goalsScored;
    }

    public function setGoalsScored(?int $goalsScored): self
    {
        $this->goalsScored = $goalsScored;

        return $this;
    }

    public function getAssists(): ?int
    {
        return $this->assists;
    }

    public function setAssists(?int $assists): self
    {
        $this->assists = $assists;

        return $this;
    }

    public function getYellowCards(): ?int
    {
        return $this->yellowCards;
    }

    public function setYellowCards(?int $yellowCards): self
    {
        $this->yellowCards = $yellowCards;

        return $this;
    }

    public function getRedCards(): ?int
    {
        return $this->redCards;
    }

    public function setRedCards(?int $redCards): self
    {
        $this->redCards = $redCards;

        return $this;
    }

    public function getMatchesPlayed(): ?int
    {
        return $this->matchesPlayed;
    }

    public function setMatchesPlayed(?int $matchesPlayed): self
    {
        $this->matchesPlayed = $matchesPlayed;

        return $this;
    }

    public function getAvgRating(): ?float
    {
        return $this->avgRating;
    }

    public function setAvgRating(?float $avgRating): self
    {
        $this->avgRating = $avgRating;

        return $this;
    }

    public function getXG(): ?float
    {
        return $this->xG;
    }

    public function setXG(?float $xG): self
    {
        $this->xG = $xG;

        return $this;
    }

    public function getXA(): ?float
    {
        return $this->xA;
    }

    public function setXA(?float $xA): self
    {
        $this->xA = $xA;

        return $this;
    }

    public function getScoringProbability(): ?float
    {
        return $this->scoringProbability;
    }

    public function setScoringProbability(?float $scoringProbability): self
    {
        $this->scoringProbability = $scoringProbability;

        return $this;
    }

    public function isInjured(): bool
    {
        return $this->isInjured;
    }

    public function setIsInjured(bool $isInjured): self
    {
        $this->isInjured = $isInjured;

        return $this;
    }

    public function isSuspended(): bool
    {
        return $this->isSuspended;
    }

    public function setIsSuspended(bool $isSuspended): self
    {
        $this->isSuspended = $isSuspended;

        return $this;
    }

    public function getPhotoUrl(): ?string
    {
        return $this->photoUrl;
    }

    public function setPhotoUrl(?string $photoUrl): self
    {
        $this->photoUrl = $photoUrl;

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

    public function isAvailable(): bool
    {
        return !$this->isInjured && !$this->isSuspended;
    }
}
