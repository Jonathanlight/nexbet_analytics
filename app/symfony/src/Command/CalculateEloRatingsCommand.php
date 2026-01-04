<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Math\EloService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:calculate-elo-ratings',
    description: 'Calculate Elo ratings for all teams based on historical match results',
)]
class CalculateEloRatingsCommand extends Command
{
    private const DEFAULT_RATING = 1500;
    private const K_FACTOR_BASE = 40;
    private const HOME_ADVANTAGE = 100;

    // Multiplicateurs de force de ligue
    private const LEAGUE_STRENGTH = [
        'Premier League' => 1.15,
        'La Liga' => 1.12,
        'Bundesliga' => 1.10,
        'Serie A' => 1.08,
        'Ligue 1' => 1.05,
        'Championship' => 1.0,
        'Eredivisie' => 1.0,
        'Primeira Liga' => 1.0,
        'default' => 1.0,
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EloService $eloService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('reset', 'r', InputOption::VALUE_NONE, 'Reset all Elo ratings before calculating')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit number of matches to process', 0)
            ->addOption('sport', 's', InputOption::VALUE_OPTIONAL, 'Sport to calculate (football, basketball, hockey)', 'football');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sport = $input->getOption('sport');
        $reset = $input->getOption('reset');
        $limit = (int) $input->getOption('limit');

        $io->title("Calculating Elo Ratings for $sport teams");

        $conn = $this->entityManager->getConnection();

        // Reset ratings if requested
        if ($reset) {
            $io->section('Resetting all Elo ratings to default...');
            $conn->executeStatement(
                'UPDATE teams SET elo_rating = ? WHERE sport = ?',
                [self::DEFAULT_RATING, $sport]
            );
            $io->success('All ratings reset to '.self::DEFAULT_RATING);
        }

        // Initialize teams without Elo rating
        $io->section('Initializing teams without Elo rating...');
        $conn->executeStatement(
            'UPDATE teams SET elo_rating = ? WHERE elo_rating IS NULL AND sport = ?',
            [self::DEFAULT_RATING, $sport]
        );

        // Get finished matches sorted by date
        $io->section('Fetching finished matches...');

        $matchTable = match ($sport) {
            'basketball' => 'basketball_matches',
            'hockey' => 'hockey_matches',
            default => 'matches',
        };

        $scoreHomeCol = match ($sport) {
            'basketball' => 'home_final_score',
            'hockey' => 'home_final_score',
            default => 'home_score',
        };

        $scoreAwayCol = match ($sport) {
            'basketball' => 'away_final_score',
            'hockey' => 'away_final_score',
            default => 'away_score',
        };

        $sql = "SELECT m.id, m.home_team_id, m.away_team_id, m.$scoreHomeCol as home_score,
                       m.$scoreAwayCol as away_score, m.league, m.match_date,
                       ht.name as home_name, at.name as away_name
                FROM $matchTable m
                JOIN teams ht ON m.home_team_id = ht.id
                JOIN teams at ON m.away_team_id = at.id
                WHERE m.status IN ('Match Finished', 'finished', 'FT')
                AND m.$scoreHomeCol IS NOT NULL
                AND m.$scoreAwayCol IS NOT NULL
                ORDER BY m.match_date ASC";

        if ($limit > 0) {
            $sql .= " LIMIT $limit";
        }

        $matches = $conn->fetchAllAssociative($sql);
        $totalMatches = count($matches);

        $io->info("Found $totalMatches finished matches to process");

        if (0 === $totalMatches) {
            $io->warning('No finished matches found!');

            return Command::SUCCESS;
        }

        // Cache for Elo ratings (to avoid multiple DB queries)
        $eloRatings = [];

        // Get current ratings
        $teams = $conn->fetchAllAssociative(
            'SELECT id, elo_rating FROM teams WHERE sport = ?',
            [$sport]
        );
        foreach ($teams as $team) {
            $eloRatings[$team['id']] = (float) ($team['elo_rating'] ?? self::DEFAULT_RATING);
        }

        $io->progressStart($totalMatches);
        $updatedTeams = [];

        foreach ($matches as $match) {
            $homeId = (int) $match['home_team_id'];
            $awayId = (int) $match['away_team_id'];
            $homeScore = (int) $match['home_score'];
            $awayScore = (int) $match['away_score'];
            $league = $match['league'] ?? 'default';

            // Get current ratings
            $homeRating = $eloRatings[$homeId] ?? self::DEFAULT_RATING;
            $awayRating = $eloRatings[$awayId] ?? self::DEFAULT_RATING;

            // Calculate K factor based on league importance
            $leagueStrength = self::LEAGUE_STRENGTH[$league] ?? self::LEAGUE_STRENGTH['default'];
            $kFactor = self::K_FACTOR_BASE * $leagueStrength;

            // Add home advantage
            $adjustedHomeRating = $homeRating + self::HOME_ADVANTAGE;

            // Calculate expected scores
            $homeExpected = 1 / (1 + pow(10, ($awayRating - $adjustedHomeRating) / 400));
            $awayExpected = 1 - $homeExpected;

            // Determine actual result
            if ($homeScore > $awayScore) {
                $homeActual = 1.0;
                $awayActual = 0.0;
            } elseif ($homeScore < $awayScore) {
                $homeActual = 0.0;
                $awayActual = 1.0;
            } else {
                $homeActual = 0.5;
                $awayActual = 0.5;
            }

            // Goal difference bonus (max 0.5 extra change for large wins)
            $goalDiff = abs($homeScore - $awayScore);
            $goalBonus = min(0.5, $goalDiff * 0.1);

            if ($homeScore > $awayScore) {
                $homeActual += $goalBonus;
            } elseif ($awayScore > $homeScore) {
                $awayActual += $goalBonus;
            }

            // Calculate new ratings
            $newHomeRating = $homeRating + $kFactor * ($homeActual - $homeExpected);
            $newAwayRating = $awayRating + $kFactor * ($awayActual - $awayExpected);

            // Ensure ratings stay within reasonable bounds
            $newHomeRating = max(1000, min(2200, $newHomeRating));
            $newAwayRating = max(1000, min(2200, $newAwayRating));

            // Update cache
            $eloRatings[$homeId] = round($newHomeRating, 2);
            $eloRatings[$awayId] = round($newAwayRating, 2);

            $updatedTeams[$homeId] = true;
            $updatedTeams[$awayId] = true;

            $io->progressAdvance();
        }

        $io->progressFinish();

        // Bulk update all team ratings
        $io->section('Updating team ratings in database...');

        $updateCount = 0;
        foreach ($eloRatings as $teamId => $rating) {
            if (isset($updatedTeams[$teamId])) {
                $conn->executeStatement(
                    'UPDATE teams SET elo_rating = ? WHERE id = ?',
                    [$rating, $teamId]
                );
                ++$updateCount;
            }
        }

        // Show top teams by rating
        $io->section("Top 15 teams by Elo rating ($sport):");

        $topTeams = $conn->fetchAllAssociative(
            'SELECT name, elo_rating, league FROM teams WHERE sport = ? AND elo_rating IS NOT NULL ORDER BY elo_rating DESC LIMIT 15',
            [$sport]
        );

        $rows = [];
        foreach ($topTeams as $team) {
            $rows[] = [$team['name'], round($team['elo_rating'], 0), $team['league'] ?? 'N/A'];
        }
        $io->table(['Team', 'Elo Rating', 'League'], $rows);

        // Show bottom teams
        $io->section("Bottom 10 teams by Elo rating ($sport):");

        $bottomTeams = $conn->fetchAllAssociative(
            'SELECT name, elo_rating, league FROM teams WHERE sport = ? AND elo_rating IS NOT NULL ORDER BY elo_rating ASC LIMIT 10',
            [$sport]
        );

        $rows = [];
        foreach ($bottomTeams as $team) {
            $rows[] = [$team['name'], round($team['elo_rating'], 0), $team['league'] ?? 'N/A'];
        }
        $io->table(['Team', 'Elo Rating', 'League'], $rows);

        $io->success([
            "Processed $totalMatches matches",
            "Updated $updateCount team ratings",
        ]);

        return Command::SUCCESS;
    }
}
