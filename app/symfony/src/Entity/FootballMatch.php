<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MatchStatus;
use App\Repository\FootballMatchRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FootballMatchRepository::class)]
#[ORM\Table(name: 'matches')]
#[ORM\Index(columns: ['match_date'], name: 'idx_match_date')]
#[ORM\Index(columns: ['league'], name: 'idx_match_league')]
#[ORM\Index(columns: ['status'], name: 'idx_match_status')]
class FootballMatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Team::class, inversedBy: 'homeMatches')]
    #[ORM\JoinColumn(nullable: false)]
    private Team $homeTeam;

    #[ORM\ManyToOne(targetEntity: Team::class, inversedBy: 'awayMatches')]
    #[ORM\JoinColumn(nullable: false)]
    private Team $awayTeam;

    #[ORM\Column(length: 255)]
    private string $league;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $matchDate;

    #[ORM\Column(type: 'string', length: 50, enumType: MatchStatus::class)]
    private MatchStatus $status;

    #[ORM\Column(nullable: true)]
    private ?int $homeScore = null;

    #[ORM\Column(nullable: true)]
    private ?int $awayScore = null;

    #[ORM\Column(nullable: true)]
    private ?int $homeScoreHt = null; // Half-time

    #[ORM\Column(nullable: true)]
    private ?int $awayScoreHt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $referee = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stadium = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private array $weatherConditions = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private array $headToHeadStats = [];

    #[ORM\Column(nullable: true)]
    private ?int $homeCorners = null;

    #[ORM\Column(nullable: true)]
    private ?int $awayCorners = null;

    #[ORM\Column(nullable: true)]
    private ?int $homeYellowCards = null;

    #[ORM\Column(nullable: true)]
    private ?int $awayYellowCards = null;

    #[ORM\Column(nullable: true)]
    private ?int $homeRedCards = null;

    #[ORM\Column(nullable: true)]
    private ?int $awayRedCards = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $homeXg = null; // Expected Goals

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $awayXg = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private array $homeLineup = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private array $awayLineup = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private array $events = []; // Goals, cards, substitutions

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, Prediction>
     */
    #[ORM\OneToMany(targetEntity: Prediction::class, mappedBy: 'match', cascade: ['persist', 'remove'])]
    private Collection $predictions;

    /**
     * @var Collection<int, Odds>
     */
    #[ORM\OneToMany(targetEntity: Odds::class, mappedBy: 'match', cascade: ['persist', 'remove'])]
    private Collection $odds;

    public function __construct()
    {
        $this->predictions = new ArrayCollection();
        $this->odds = new ArrayCollection();
        $this->status = MatchStatus::SCHEDULED;
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

    public function getStatus(): MatchStatus
    {
        return $this->status;
    }

    public function setStatus(MatchStatus|string $status): self
    {
        $this->status = is_string($status) ? MatchStatus::from($status) : $status;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getHomeScore(): ?int
    {
        return $this->homeScore;
    }

    public function setHomeScore(?int $homeScore): self
    {
        $this->homeScore = $homeScore;

        return $this;
    }

    public function getAwayScore(): ?int
    {
        return $this->awayScore;
    }

    public function setAwayScore(?int $awayScore): self
    {
        $this->awayScore = $awayScore;

        return $this;
    }

    public function getHomeScoreHt(): ?int
    {
        return $this->homeScoreHt;
    }

    public function setHomeScoreHt(?int $homeScoreHt): self
    {
        $this->homeScoreHt = $homeScoreHt;

        return $this;
    }

    public function getAwayScoreHt(): ?int
    {
        return $this->awayScoreHt;
    }

    public function setAwayScoreHt(?int $awayScoreHt): self
    {
        $this->awayScoreHt = $awayScoreHt;

        return $this;
    }

    public function getReferee(): ?string
    {
        return $this->referee;
    }

    public function setReferee(?string $referee): self
    {
        $this->referee = $referee;

        return $this;
    }

    public function getStadium(): ?string
    {
        return $this->stadium;
    }

    public function setStadium(?string $stadium): self
    {
        $this->stadium = $stadium;

        return $this;
    }

    public function getWeatherConditions(): array
    {
        return $this->weatherConditions;
    }

    public function setWeatherConditions(array $weatherConditions): self
    {
        $this->weatherConditions = $weatherConditions;

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

    public function getHomeCorners(): ?int
    {
        return $this->homeCorners;
    }

    public function setHomeCorners(?int $homeCorners): self
    {
        $this->homeCorners = $homeCorners;

        return $this;
    }

    public function getAwayCorners(): ?int
    {
        return $this->awayCorners;
    }

    public function setAwayCorners(?int $awayCorners): self
    {
        $this->awayCorners = $awayCorners;

        return $this;
    }

    public function getHomeYellowCards(): ?int
    {
        return $this->homeYellowCards;
    }

    public function setHomeYellowCards(?int $homeYellowCards): self
    {
        $this->homeYellowCards = $homeYellowCards;

        return $this;
    }

    public function getAwayYellowCards(): ?int
    {
        return $this->awayYellowCards;
    }

    public function setAwayYellowCards(?int $awayYellowCards): self
    {
        $this->awayYellowCards = $awayYellowCards;

        return $this;
    }

    public function getHomeRedCards(): ?int
    {
        return $this->homeRedCards;
    }

    public function setHomeRedCards(?int $homeRedCards): self
    {
        $this->homeRedCards = $homeRedCards;

        return $this;
    }

    public function getAwayRedCards(): ?int
    {
        return $this->awayRedCards;
    }

    public function setAwayRedCards(?int $awayRedCards): self
    {
        $this->awayRedCards = $awayRedCards;

        return $this;
    }

    public function getHomeXg(): ?float
    {
        return $this->homeXg;
    }

    public function setHomeXg(?float $homeXg): self
    {
        $this->homeXg = $homeXg;

        return $this;
    }

    public function getAwayXg(): ?float
    {
        return $this->awayXg;
    }

    public function setAwayXg(?float $awayXg): self
    {
        $this->awayXg = $awayXg;

        return $this;
    }

    public function getHomeLineup(): array
    {
        return $this->homeLineup;
    }

    public function setHomeLineup(array $homeLineup): self
    {
        $this->homeLineup = $homeLineup;

        return $this;
    }

    public function getAwayLineup(): array
    {
        return $this->awayLineup;
    }

    public function setAwayLineup(array $awayLineup): self
    {
        $this->awayLineup = $awayLineup;

        return $this;
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function setEvents(array $events): self
    {
        $this->events = $events;

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
     * @return Collection<int, Prediction>
     */
    public function getPredictions(): Collection
    {
        return $this->predictions;
    }

    /**
     * @return Collection<int, Odds>
     */
    public function getOdds(): Collection
    {
        return $this->odds;
    }

    /**
     * Retourne les cotes sous forme de tableau structuré.
     *
     * @return array<string, array<string, float>>
     */
    public function getOddsArray(): array
    {
        $result = [];

        foreach ($this->odds as $odd) {
            $betType = $odd->getBetType();
            $market = $odd->getMarket();
            $value = $odd->getOdds();

            if (!isset($result[$betType])) {
                $result[$betType] = [];
            }

            $result[$betType][$market] = $value;
        }

        return $result;
    }

    public function isFinished(): bool
    {
        return $this->status->isFinished();
    }

    public function isScheduled(): bool
    {
        return $this->status->isScheduled();
    }

    public function isLive(): bool
    {
        return $this->status->isLive();
    }

    public function getTotalGoals(): ?int
    {
        if (null === $this->homeScore || null === $this->awayScore) {
            return null;
        }

        return $this->homeScore + $this->awayScore;
    }

    public function getResult(): ?string
    {
        if (null === $this->homeScore || null === $this->awayScore) {
            return null;
        }

        if ($this->homeScore > $this->awayScore) {
            return '1';
        } elseif ($this->homeScore < $this->awayScore) {
            return '2';
        }

        return 'X';
    }

    public function getHalfTimeResult(): ?string
    {
        if (null === $this->homeScoreHt || null === $this->awayScoreHt) {
            return null;
        }

        if ($this->homeScoreHt > $this->awayScoreHt) {
            return '1';
        } elseif ($this->homeScoreHt < $this->awayScoreHt) {
            return '2';
        }

        return 'X';
    }
}
