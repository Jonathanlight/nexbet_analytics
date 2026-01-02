<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 50)]
    private string $bettingProfile = 'conservative'; // conservative, balanced, aggressive

    #[ORM\Column(type: 'float')]
    private float $bankroll = 1000.0;

    #[ORM\Column(type: 'float')]
    private float $initialBankroll = 1000.0;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $maxStakePercentage = 5.0; // Pourcentage max de bankroll par pari

    #[ORM\Column(type: 'json', nullable: true)]
    private array $bettingPreferences = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private array $statistics = [];

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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
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

    public function getBettingProfile(): string
    {
        return $this->bettingProfile;
    }

    public function setBettingProfile(string $bettingProfile): self
    {
        $this->bettingProfile = $bettingProfile;

        return $this;
    }

    public function getBankroll(): float
    {
        return $this->bankroll;
    }

    public function setBankroll(float $bankroll): self
    {
        $this->bankroll = $bankroll;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getInitialBankroll(): float
    {
        return $this->initialBankroll;
    }

    public function setInitialBankroll(float $initialBankroll): self
    {
        $this->initialBankroll = $initialBankroll;

        return $this;
    }

    public function getMaxStakePercentage(): ?float
    {
        return $this->maxStakePercentage;
    }

    public function setMaxStakePercentage(?float $maxStakePercentage): self
    {
        $this->maxStakePercentage = $maxStakePercentage;

        return $this;
    }

    public function getBettingPreferences(): array
    {
        return $this->bettingPreferences;
    }

    public function setBettingPreferences(array $bettingPreferences): self
    {
        $this->bettingPreferences = $bettingPreferences;

        return $this;
    }

    public function getStatistics(): array
    {
        return $this->statistics;
    }

    public function setStatistics(array $statistics): self
    {
        $this->statistics = $statistics;

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

    public function eraseCredentials(): void
    {
    }

    public function getBankrollGrowth(): float
    {
        if ($this->initialBankroll <= 0) {
            return 0.0;
        }

        return round((($this->bankroll - $this->initialBankroll) / $this->initialBankroll) * 100, 2);
    }

    public function getMaxStakeAmount(): float
    {
        if (null === $this->maxStakePercentage) {
            return 0.0;
        }

        return round($this->bankroll * ($this->maxStakePercentage / 100), 2);
    }
}
