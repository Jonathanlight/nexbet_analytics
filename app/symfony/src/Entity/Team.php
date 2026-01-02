<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TeamRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamRepository::class)]
#[ORM\Table(name: 'teams')]
#[ORM\Index(columns: ['sport'], name: 'idx_team_sport')]
#[ORM\Index(columns: ['league'], name: 'idx_team_league')]
class Team
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 10)]
    private string $sport; // 'football' ou 'basketball'

    #[ORM\Column(length: 255)]
    private string $league;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $eloRating = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private array $statistics = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private array $formLast5 = []; // ['W', 'D', 'L', 'W', 'W']

    #[ORM\Column(nullable: true)]
    private ?int $position = null;

    #[ORM\Column(nullable: true)]
    private ?int $matchesPlayed = null;

    #[ORM\Column(nullable: true)]
    private ?int $wins = null;

    #[ORM\Column(nullable: true)]
    private ?int $draws = null;

    #[ORM\Column(nullable: true)]
    private ?int $losses = null;

    #[ORM\Column(nullable: true)]
    private ?int $goalsFor = null;

    #[ORM\Column(nullable: true)]
    private ?int $goalsAgainst = null;

    #[ORM\Column(nullable: true)]
    private ?int $points = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoUrl = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, Player>
     */
    #[ORM\OneToMany(targetEntity: Player::class, mappedBy: 'team')]
    private Collection $players;

    /**
     * @var Collection<int, FootballMatch>
     */
    #[ORM\OneToMany(targetEntity: FootballMatch::class, mappedBy: 'homeTeam')]
    private Collection $homeMatches;

    /**
     * @var Collection<int, FootballMatch>
     */
    #[ORM\OneToMany(targetEntity: FootballMatch::class, mappedBy: 'awayTeam')]
    private Collection $awayMatches;

    public function __construct()
    {
        $this->players = new ArrayCollection();
        $this->homeMatches = new ArrayCollection();
        $this->awayMatches = new ArrayCollection();
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

    public function getSport(): string
    {
        return $this->sport;
    }

    public function setSport(string $sport): self
    {
        $this->sport = $sport;

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

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function getEloRating(): ?float
    {
        return $this->eloRating;
    }

    public function setEloRating(?float $eloRating): self
    {
        $this->eloRating = $eloRating;

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

    public function getFormLast5(): array
    {
        return $this->formLast5;
    }

    public function setFormLast5(array $formLast5): self
    {
        $this->formLast5 = $formLast5;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position;

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

    public function getWins(): ?int
    {
        return $this->wins;
    }

    public function setWins(?int $wins): self
    {
        $this->wins = $wins;

        return $this;
    }

    public function getDraws(): ?int
    {
        return $this->draws;
    }

    public function setDraws(?int $draws): self
    {
        $this->draws = $draws;

        return $this;
    }

    public function getLosses(): ?int
    {
        return $this->losses;
    }

    public function setLosses(?int $losses): self
    {
        $this->losses = $losses;

        return $this;
    }

    public function getGoalsFor(): ?int
    {
        return $this->goalsFor;
    }

    public function setGoalsFor(?int $goalsFor): self
    {
        $this->goalsFor = $goalsFor;

        return $this;
    }

    public function getGoalsAgainst(): ?int
    {
        return $this->goalsAgainst;
    }

    public function setGoalsAgainst(?int $goalsAgainst): self
    {
        $this->goalsAgainst = $goalsAgainst;

        return $this;
    }

    public function getPoints(): ?int
    {
        return $this->points;
    }

    public function setPoints(?int $points): self
    {
        $this->points = $points;

        return $this;
    }

    public function getLogoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function setLogoUrl(?string $logoUrl): self
    {
        $this->logoUrl = $logoUrl;

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

    /**
     * @return Collection<int, Player>
     */
    public function getPlayers(): Collection
    {
        return $this->players;
    }

    /**
     * @return Collection<int, FootballMatch>
     */
    public function getHomeMatches(): Collection
    {
        return $this->homeMatches;
    }

    /**
     * @return Collection<int, FootballMatch>
     */
    public function getAwayMatches(): Collection
    {
        return $this->awayMatches;
    }

    public function getGoalDifference(): int
    {
        return ($this->goalsFor ?? 0) - ($this->goalsAgainst ?? 0);
    }

    public function getWinPercentage(): float
    {
        if (null === $this->matchesPlayed || 0 === $this->matchesPlayed) {
            return 0.0;
        }

        return round(($this->wins ?? 0) / $this->matchesPlayed * 100, 2);
    }
}
