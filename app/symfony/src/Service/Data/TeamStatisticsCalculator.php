<?php

declare(strict_types=1);

namespace App\Service\Data;

use App\Entity\Team;
use App\Repository\BasketballMatchRepository;
use App\Repository\FootballMatchRepository;
use App\Repository\HockeyMatchRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service pour calculer les statistiques des équipes basées sur leurs matchs historiques.
 */
final class TeamStatisticsCalculator
{
    // Moyennes de ligue par défaut (points par match)
    private const LEAGUE_AVERAGES = [
        // Basketball
        'NBA' => 114.5,
        'Euroleague' => 82.0,
        'Eurocup' => 80.0,
        'Pro A' => 84.0,
        'Liga ACB' => 85.0,
        'Serie A Basketball' => 83.0,
        'BBL' => 86.0,
        'KBSL' => 78.0,
        'default_basketball' => 85.0,

        // Hockey (buts par match)
        'NHL' => 3.1,
        'KHL' => 2.8,
        'SHL' => 3.0,
        'Liiga' => 2.9,
        'DEL' => 3.2,
        'default_hockey' => 2.9,

        // Football (buts par match)
        'Premier League' => 1.4,
        'La Liga' => 1.3,
        'Serie A' => 1.35,
        'Bundesliga' => 1.5,
        'Ligue 1' => 1.3,
        'default_football' => 1.35,
    ];

    public function __construct(
        private readonly BasketballMatchRepository $basketballMatchRepository,
        private readonly HockeyMatchRepository $hockeyMatchRepository,
        private readonly FootballMatchRepository $footballMatchRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Calcule les statistiques basketball pour une équipe.
     */
    public function calculateBasketballStats(Team $team, int $matchesLimit = 10): array
    {
        $matches = $this->basketballMatchRepository->findRecentMatchesByTeam($team, $matchesLimit);

        if (empty($matches) || count($matches) < 3) {
            return $this->getDefaultBasketballStats($team->getLeague() ?? 'default_basketball', $team->getId());
        }

        $pointsScored = [];
        $pointsConceded = [];
        $pointsDiff = [];
        $q1Points = [];
        $q2Points = [];
        $q3Points = [];
        $q4Points = [];
        $wins = 0;
        $homeWins = 0;
        $awayWins = 0;
        $homeGames = 0;
        $awayGames = 0;
        $overtimeGames = 0;

        foreach ($matches as $match) {
            $isHome = $match->getHomeTeam()->getId() === $team->getId();

            if ($isHome) {
                $scored = $match->getHomeFinalScore();
                $conceded = $match->getAwayFinalScore();
                ++$homeGames;
                $q1 = $match->getHomeScoreQ1();
                $q2 = $match->getHomeScoreQ2();
                $q3 = $match->getHomeScoreQ3();
                $q4 = $match->getHomeScoreQ4();
            } else {
                $scored = $match->getAwayFinalScore();
                $conceded = $match->getHomeFinalScore();
                ++$awayGames;
                $q1 = $match->getAwayScoreQ1();
                $q2 = $match->getAwayScoreQ2();
                $q3 = $match->getAwayScoreQ3();
                $q4 = $match->getAwayScoreQ4();
            }

            // Ne compter que les matchs terminés avec scores
            if (null !== $scored && null !== $conceded) {
                $pointsScored[] = $scored;
                $pointsConceded[] = $conceded;
                $pointsDiff[] = $scored - $conceded;

                if ($scored > $conceded) {
                    ++$wins;
                    if ($isHome) {
                        ++$homeWins;
                    } else {
                        ++$awayWins;
                    }
                }

                if ($match->hadOvertime()) {
                    ++$overtimeGames;
                }

                if (null !== $q1) {
                    $q1Points[] = $q1;
                }
                if (null !== $q2) {
                    $q2Points[] = $q2;
                }
                if (null !== $q3) {
                    $q3Points[] = $q3;
                }
                if (null !== $q4) {
                    $q4Points[] = $q4;
                }
            }
        }

        // Si pas assez de matchs terminés, utiliser les défauts
        if (count($pointsScored) < 3) {
            return $this->getDefaultBasketballStats($team->getLeague() ?? 'default_basketball');
        }

        $avgScored = array_sum($pointsScored) / count($pointsScored);
        $avgConceded = array_sum($pointsConceded) / count($pointsConceded);
        $totalGames = count($pointsScored);

        return [
            'avg_points' => round($avgScored, 1),
            'avg_conceded' => round($avgConceded, 1),
            'avg_total' => round($avgScored + $avgConceded, 1),
            'avg_diff' => round(array_sum($pointsDiff) / count($pointsDiff), 1),
            'win_rate' => round(($wins / $totalGames) * 100, 1),
            'home_win_rate' => $homeGames > 0 ? round(($homeWins / $homeGames) * 100, 1) : 50,
            'away_win_rate' => $awayGames > 0 ? round(($awayWins / $awayGames) * 100, 1) : 50,
            'overtime_rate' => round(($overtimeGames / $totalGames) * 100, 1),
            'form' => $this->calculateRecentForm(array_slice($pointsDiff, 0, 5)),
            'quarters' => [
                'q1_avg' => !empty($q1Points) ? round(array_sum($q1Points) / count($q1Points), 1) : round($avgScored / 4, 1),
                'q2_avg' => !empty($q2Points) ? round(array_sum($q2Points) / count($q2Points), 1) : round($avgScored / 4, 1),
                'q3_avg' => !empty($q3Points) ? round(array_sum($q3Points) / count($q3Points), 1) : round($avgScored / 4, 1),
                'q4_avg' => !empty($q4Points) ? round(array_sum($q4Points) / count($q4Points), 1) : round($avgScored / 4, 1),
            ],
            'std_dev' => $this->calculateStdDev($pointsScored),
            'matches_analyzed' => $totalGames,
            'data_quality' => $totalGames >= 10 ? 'high' : ($totalGames >= 5 ? 'medium' : 'low'),
        ];
    }

    /**
     * Calcule les statistiques hockey pour une équipe.
     */
    public function calculateHockeyStats(Team $team, int $matchesLimit = 10): array
    {
        $matches = $this->hockeyMatchRepository->findRecentMatchesByTeam($team, $matchesLimit);

        if (empty($matches) || count($matches) < 3) {
            return $this->getDefaultHockeyStats($team->getLeague() ?? 'default_hockey', $team->getId());
        }

        $goalsScored = [];
        $goalsConceded = [];
        $wins = 0;
        $draws = 0;
        $overtimeGames = 0;
        $totalGames = 0;

        foreach ($matches as $match) {
            $isHome = $match->getHomeTeam()->getId() === $team->getId();

            $homeScore = $match->getHomeFinalScore();
            $awayScore = $match->getAwayFinalScore();

            if (null === $homeScore || null === $awayScore) {
                continue;
            }

            ++$totalGames;

            if ($isHome) {
                $goalsScored[] = $homeScore;
                $goalsConceded[] = $awayScore;
                if ($homeScore > $awayScore) {
                    ++$wins;
                } elseif ($homeScore === $awayScore) {
                    ++$draws;
                }
            } else {
                $goalsScored[] = $awayScore;
                $goalsConceded[] = $homeScore;
                if ($awayScore > $homeScore) {
                    ++$wins;
                } elseif ($awayScore === $homeScore) {
                    ++$draws;
                }
            }

            if (null !== $match->getHomeScoreOt() || null !== $match->getAwayScoreOt()) {
                ++$overtimeGames;
            }
        }

        if ($totalGames < 3) {
            return $this->getDefaultHockeyStats($team->getLeague() ?? 'default_hockey', $team->getId());
        }

        return [
            'avg_goals' => round(array_sum($goalsScored) / $totalGames, 2),
            'avg_conceded' => round(array_sum($goalsConceded) / $totalGames, 2),
            'avg_total' => round((array_sum($goalsScored) + array_sum($goalsConceded)) / $totalGames, 2),
            'win_rate' => round(($wins / $totalGames) * 100, 1),
            'draw_rate' => round(($draws / $totalGames) * 100, 1),
            'overtime_rate' => round(($overtimeGames / $totalGames) * 100, 1),
            'form' => $this->calculateRecentForm(array_map(
                fn ($s, $c) => $s - $c,
                array_slice($goalsScored, 0, 5),
                array_slice($goalsConceded, 0, 5)
            )),
            'std_dev' => $this->calculateStdDev($goalsScored),
            'matches_analyzed' => $totalGames,
            'data_quality' => $totalGames >= 10 ? 'high' : ($totalGames >= 5 ? 'medium' : 'low'),
        ];
    }

    /**
     * Calcule les statistiques football pour une équipe.
     */
    public function calculateFootballStats(Team $team, int $matchesLimit = 10): array
    {
        $matches = $this->footballMatchRepository->findRecentMatchesByTeam($team, $matchesLimit);

        if (empty($matches)) {
            return $this->getDefaultFootballStats($team->getLeague() ?? 'default_football');
        }

        $goalsScored = [];
        $goalsConceded = [];
        $wins = 0;
        $draws = 0;
        $cleanSheets = 0;
        $btts = 0;
        $totalGames = 0;

        foreach ($matches as $match) {
            $isHome = $match->getHomeTeam()->getId() === $team->getId();

            $homeScore = $match->getHomeScore();
            $awayScore = $match->getAwayScore();

            if (null === $homeScore || null === $awayScore) {
                continue;
            }

            ++$totalGames;

            if ($isHome) {
                $goalsScored[] = $homeScore;
                $goalsConceded[] = $awayScore;
                if ($homeScore > $awayScore) {
                    ++$wins;
                } elseif ($homeScore === $awayScore) {
                    ++$draws;
                }
                if (0 === $awayScore) {
                    ++$cleanSheets;
                }
            } else {
                $goalsScored[] = $awayScore;
                $goalsConceded[] = $homeScore;
                if ($awayScore > $homeScore) {
                    ++$wins;
                } elseif ($awayScore === $homeScore) {
                    ++$draws;
                }
                if (0 === $homeScore) {
                    ++$cleanSheets;
                }
            }

            if ($homeScore > 0 && $awayScore > 0) {
                ++$btts;
            }
        }

        if ($totalGames < 3) {
            return $this->getDefaultFootballStats($team->getLeague() ?? 'default_football');
        }

        return [
            'avg_goals' => round(array_sum($goalsScored) / $totalGames, 2),
            'avg_conceded' => round(array_sum($goalsConceded) / $totalGames, 2),
            'avg_total' => round((array_sum($goalsScored) + array_sum($goalsConceded)) / $totalGames, 2),
            'win_rate' => round(($wins / $totalGames) * 100, 1),
            'draw_rate' => round(($draws / $totalGames) * 100, 1),
            'clean_sheet_rate' => round(($cleanSheets / $totalGames) * 100, 1),
            'btts_rate' => round(($btts / $totalGames) * 100, 1),
            'form' => $this->calculateRecentForm(array_map(
                fn ($s, $c) => $s - $c,
                array_slice($goalsScored, 0, 5),
                array_slice($goalsConceded, 0, 5)
            )),
            'std_dev' => $this->calculateStdDev($goalsScored),
            'matches_analyzed' => $totalGames,
        ];
    }

    /**
     * Met à jour les statistiques d'une équipe dans la base de données.
     */
    public function updateTeamStatistics(Team $team): void
    {
        $sport = $team->getSport() ?? 'football';

        $stats = match ($sport) {
            'basketball' => $this->calculateBasketballStats($team),
            'hockey' => $this->calculateHockeyStats($team),
            default => $this->calculateFootballStats($team),
        };

        $team->setStatistics($stats);
        $this->entityManager->persist($team);
    }

    /**
     * Calcule la forme récente (-2 = très mauvais, +2 = très bon).
     */
    private function calculateRecentForm(array $diffs): float
    {
        if (empty($diffs)) {
            return 0;
        }

        $score = 0;
        foreach ($diffs as $diff) {
            if ($diff > 0) {
                ++$score;
            } elseif (0 === $diff) {
                $score += 0;
            } else {
                --$score;
            }
        }

        return round($score / count($diffs), 2);
    }

    /**
     * Calcule l'écart-type.
     */
    private function calculateStdDev(array $values): float
    {
        if (count($values) < 2) {
            return 10.0;
        }

        $mean = array_sum($values) / count($values);
        $squaredDiffs = array_map(fn ($v) => pow($v - $mean, 2), $values);
        $variance = array_sum($squaredDiffs) / count($values);

        return round(sqrt($variance), 2);
    }

    /**
     * Statistiques basketball par défaut selon la ligue.
     * Utilise l'ID de l'équipe comme graine pour générer des stats cohérentes.
     */
    private function getDefaultBasketballStats(string $league, ?int $teamId = null): array
    {
        $baseAvg = self::LEAGUE_AVERAGES[$league] ?? self::LEAGUE_AVERAGES['default_basketball'];

        // Ajouter de la variance selon la ligue
        $variance = match (true) {
            str_contains(strtolower($league), 'nba') => 12.0,
            str_contains(strtolower($league), 'euro') => 8.0,
            default => 10.0,
        };

        // Utiliser l'ID de l'équipe comme graine pour avoir des stats cohérentes
        $seed = $teamId ?? random_int(1, 10000);

        // Générer des variations déterministes basées sur l'ID
        $attackFactor = 1 + (($seed % 200) - 100) / 1000; // -10% à +10%
        $defenseFactor = 1 + ((($seed * 7) % 200) - 100) / 1000;

        $avgPoints = round($baseAvg * $attackFactor, 1);
        $avgConceded = round($baseAvg * $defenseFactor, 1);

        // Calculer un taux de victoire approximatif basé sur la différence
        $diff = $avgPoints - $avgConceded;
        $winRate = round(50 + ($diff * 2.5), 1);
        $winRate = max(25, min(75, $winRate));

        // Form basée sur l'ID (-1 à +1)
        $form = round((($seed * 13) % 200 - 100) / 100, 2);

        return [
            'avg_points' => $avgPoints,
            'avg_conceded' => $avgConceded,
            'avg_total' => round($avgPoints + $avgConceded, 1),
            'avg_diff' => round($diff, 1),
            'win_rate' => $winRate,
            'home_win_rate' => round(min(85, $winRate + 5), 1),
            'away_win_rate' => round(max(15, $winRate - 5), 1),
            'overtime_rate' => 5.0 + ($seed % 5),
            'form' => $form,
            'quarters' => [
                'q1_avg' => round($avgPoints / 4 * (1 + (($seed * 2) % 100 - 50) / 1000), 1),
                'q2_avg' => round($avgPoints / 4 * (1 + (($seed * 3) % 100 - 50) / 1000), 1),
                'q3_avg' => round($avgPoints / 4 * (1 + (($seed * 5) % 100 - 50) / 1000), 1),
                'q4_avg' => round($avgPoints / 4 * (1 + (($seed * 7) % 100 - 50) / 1000), 1),
            ],
            'std_dev' => $variance,
            'matches_analyzed' => 0,
            'data_quality' => 'estimated',
        ];
    }

    /**
     * Statistiques hockey par défaut selon la ligue.
     * Utilise l'ID de l'équipe comme graine pour générer des stats cohérentes.
     */
    private function getDefaultHockeyStats(string $league, ?int $teamId = null): array
    {
        $baseAvg = self::LEAGUE_AVERAGES[$league] ?? self::LEAGUE_AVERAGES['default_hockey'];

        // Utiliser l'ID de l'équipe comme graine pour avoir des stats cohérentes
        $seed = $teamId ?? random_int(1, 10000);

        // Générer des variations déterministes basées sur l'ID
        $attackFactor = 1 + (($seed % 200) - 100) / 500; // -20% à +20%
        $defenseFactor = 1 + ((($seed * 7) % 200) - 100) / 500;

        $avgGoals = round($baseAvg * $attackFactor, 2);
        $avgConceded = round($baseAvg * $defenseFactor, 2);

        // Calculer un taux de victoire approximatif basé sur la différence
        $diff = $avgGoals - $avgConceded;
        $winRate = round(45 + ($diff * 8), 1);
        $winRate = max(25, min(65, $winRate));

        // Form basée sur l'ID (-1 à +1)
        $form = round((($seed * 13) % 200 - 100) / 100, 2);

        return [
            'avg_goals' => $avgGoals,
            'avg_conceded' => $avgConceded,
            'avg_total' => round($avgGoals + $avgConceded, 2),
            'win_rate' => $winRate,
            'draw_rate' => 10.0 + ($seed % 8) - 4,
            'overtime_rate' => 10.0 + ($seed % 10),
            'form' => $form,
            'std_dev' => 1.2,
            'matches_analyzed' => 0,
            'data_quality' => 'estimated',
        ];
    }

    /**
     * Statistiques football par défaut selon la ligue.
     */
    private function getDefaultFootballStats(string $league): array
    {
        $avgGoals = self::LEAGUE_AVERAGES[$league] ?? self::LEAGUE_AVERAGES['default_football'];

        return [
            'avg_goals' => $avgGoals,
            'avg_conceded' => $avgGoals,
            'avg_total' => $avgGoals * 2,
            'win_rate' => 40.0,
            'draw_rate' => 25.0,
            'clean_sheet_rate' => 30.0,
            'btts_rate' => 50.0,
            'form' => 0,
            'std_dev' => 1.1,
            'matches_analyzed' => 0,
        ];
    }
}
