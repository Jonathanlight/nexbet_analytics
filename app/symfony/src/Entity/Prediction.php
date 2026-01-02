<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'predictions')]
#[ORM\Index(columns: ['match_id'], name: 'idx_prediction_match')]
#[ORM\Index(columns: ['bet_type'], name: 'idx_prediction_bet_type')]
#[ORM\Index(columns: ['confidence_level'], name: 'idx_prediction_confidence')]
class Prediction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FootballMatch::class, inversedBy: 'predictions')]
    #[ORM\JoinColumn(nullable: false)]
    private FootballMatch $match;

    #[ORM\Column(length: 100)]
    private string $betType; // '1X2', 'BTTS', 'OU25', 'HT_FT', 'scorer', etc.

    #[ORM\Column(length: 255)]
    private string $prediction; // La prédiction réelle

    #[ORM\Column(type: 'float')]
    private float $probability; // Probabilité calculée (0-1)

    #[ORM\Column(type: 'float')]
    private float $confidence; // Niveau de confiance (0-100)

    #[ORM\Column(length: 50)]
    private string $confidenceLevel; // 'low', 'medium', 'high', 'very_high', 'absolute'

    #[ORM\Column(type: 'json')]
    private array $calculationDetails = []; // Détails des calculs mathématiques

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $expectedValue = null; // Valeur attendue

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $kellyPercentage = null; // Kelly Criterion

    #[ORM\Column(type: 'boolean')]
    private bool $isValueBet = false;

    #[ORM\Column(type: 'boolean')]
    private bool $isSafeBet = false;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $result = null; // 'won', 'lost', 'void', 'pending'

    #[ORM\Column(type: 'json', nullable: true)]
    private array $algorithmScores = []; // Scores par algorithme (Poisson, Elo, xG, Monte Carlo)

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

    public function getMatch(): FootballMatch
    {
        return $this->match;
    }

    public function setMatch(FootballMatch $match): self
    {
        $this->match = $match;

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

    public function getPrediction(): string
    {
        return $this->prediction;
    }

    public function setPrediction(string $prediction): self
    {
        $this->prediction = $prediction;

        return $this;
    }

    public function getProbability(): float
    {
        return $this->probability;
    }

    public function setProbability(float $probability): self
    {
        $this->probability = $probability;

        return $this;
    }

    public function getConfidence(): float
    {
        return $this->confidence;
    }

    public function setConfidence(float $confidence): self
    {
        $this->confidence = $confidence;
        $this->confidenceLevel = $this->calculateConfidenceLevel($confidence);

        return $this;
    }

    public function getConfidenceLevel(): string
    {
        return $this->confidenceLevel;
    }

    public function getCalculationDetails(): array
    {
        return $this->calculationDetails;
    }

    public function setCalculationDetails(array $calculationDetails): self
    {
        $this->calculationDetails = $calculationDetails;

        return $this;
    }

    public function getExpectedValue(): ?float
    {
        return $this->expectedValue;
    }

    public function setExpectedValue(?float $expectedValue): self
    {
        $this->expectedValue = $expectedValue;

        return $this;
    }

    public function getKellyPercentage(): ?float
    {
        return $this->kellyPercentage;
    }

    public function setKellyPercentage(?float $kellyPercentage): self
    {
        $this->kellyPercentage = $kellyPercentage;

        return $this;
    }

    public function isValueBet(): bool
    {
        return $this->isValueBet;
    }

    public function setIsValueBet(bool $isValueBet): self
    {
        $this->isValueBet = $isValueBet;

        return $this;
    }

    public function isSafeBet(): bool
    {
        return $this->isSafeBet;
    }

    public function setIsSafeBet(bool $isSafeBet): self
    {
        $this->isSafeBet = $isSafeBet;

        return $this;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function setResult(?string $result): self
    {
        $this->result = $result;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getAlgorithmScores(): array
    {
        return $this->algorithmScores;
    }

    public function setAlgorithmScores(array $algorithmScores): self
    {
        $this->algorithmScores = $algorithmScores;

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

    private function calculateConfidenceLevel(float $confidence): string
    {
        if ($confidence >= 95) {
            return 'absolute';
        } elseif ($confidence >= 85) {
            return 'very_high';
        } elseif ($confidence >= 70) {
            return 'high';
        } elseif ($confidence >= 50) {
            return 'medium';
        }

        return 'low';
    }

    public function getConfidencePercentage(): string
    {
        return round($this->confidence, 2).'%';
    }
}
