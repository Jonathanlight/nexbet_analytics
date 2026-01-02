<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OddsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OddsRepository::class)]
#[ORM\Table(name: 'odds')]
#[ORM\Index(columns: ['match_id'], name: 'idx_odds_match')]
#[ORM\Index(columns: ['bookmaker'], name: 'idx_odds_bookmaker')]
#[ORM\Index(columns: ['bet_type'], name: 'idx_odds_bet_type')]
class Odds
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FootballMatch::class, inversedBy: 'odds')]
    #[ORM\JoinColumn(nullable: false)]
    private FootballMatch $match;

    #[ORM\Column(length: 100)]
    private string $bookmaker; // 'Betclic', 'Unibet', 'PMU', etc.

    #[ORM\Column(length: 100)]
    private string $betType; // '1X2', 'OU25', 'BTTS', 'handicap', etc.

    #[ORM\Column(length: 100)]
    private string $market; // 'home', 'draw', 'away', 'over', 'under', etc.

    #[ORM\Column(type: 'float')]
    private float $odds;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $impliedProbability = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $margin = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isBestOdds = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $fetchedAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->fetchedAt = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->calculateImpliedProbability();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMatch(): FootballMatch
    {
        return $this->match;
    }

    public function setMatch(FootballMatch $match): self
    {
        $this->match = $match;

        return $this;
    }

    public function getBookmaker(): string
    {
        return $this->bookmaker;
    }

    public function setBookmaker(string $bookmaker): self
    {
        $this->bookmaker = $bookmaker;

        return $this;
    }

    public function getBetType(): string
    {
        return $this->betType;
    }

    public function setBetType(string $betType): self
    {
        $this->betType = $betType;

        return $this;
    }

    public function getMarket(): string
    {
        return $this->market;
    }

    public function setMarket(string $market): self
    {
        $this->market = $market;

        return $this;
    }

    public function getOdds(): float
    {
        return $this->odds;
    }

    public function setOdds(float $odds): self
    {
        $this->odds = $odds;
        $this->calculateImpliedProbability();

        return $this;
    }

    public function getImpliedProbability(): ?float
    {
        return $this->impliedProbability;
    }

    public function getMargin(): ?float
    {
        return $this->margin;
    }

    public function setMargin(?float $margin): self
    {
        $this->margin = $margin;

        return $this;
    }

    public function isBestOdds(): bool
    {
        return $this->isBestOdds;
    }

    public function setIsBestOdds(bool $isBestOdds): self
    {
        $this->isBestOdds = $isBestOdds;

        return $this;
    }

    public function getFetchedAt(): \DateTimeImmutable
    {
        return $this->fetchedAt;
    }

    public function setFetchedAt(\DateTimeImmutable $fetchedAt): self
    {
        $this->fetchedAt = $fetchedAt;

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

    private function calculateImpliedProbability(): void
    {
        if ($this->odds > 0) {
            $this->impliedProbability = 1 / $this->odds;
        }
    }

    public function getImpliedProbabilityPercentage(): string
    {
        if (null === $this->impliedProbability) {
            return 'N/A';
        }

        return round($this->impliedProbability * 100, 2).'%';
    }
}
