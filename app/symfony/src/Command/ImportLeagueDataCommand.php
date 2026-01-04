<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\FootballMatch;
use App\Entity\Team;
use App\Enum\MatchStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Import historical data by league/season - more efficient than day by day.
 * Uses 1 API call per league instead of 1 per day.
 */
#[AsCommand(
    name: 'app:import-league-data',
    description: 'Import historical match data by league (more efficient)',
)]
final class ImportLeagueDataCommand extends Command
{
    private const API_BASE_URL = 'https://v3.football.api-sports.io';

    // Popular leagues with their API-Football IDs
    private const LEAGUES = [
        39 => 'Premier League (England)',
        140 => 'La Liga (Spain)',
        135 => 'Serie A (Italy)',
        78 => 'Bundesliga (Germany)',
        61 => 'Ligue 1 (France)',
        2 => 'UEFA Champions League',
        3 => 'UEFA Europa League',
        88 => 'Eredivisie (Netherlands)',
        94 => 'Primeira Liga (Portugal)',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $apiFootballKey,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('league', 'l', InputOption::VALUE_OPTIONAL, 'League ID (see --list-leagues)')
            ->addOption('season', 's', InputOption::VALUE_OPTIONAL, 'Season year (e.g., 2025)', date('Y'))
            ->addOption('list-leagues', null, InputOption::VALUE_NONE, 'List available leagues')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Import all major leagues')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('list-leagues')) {
            $io->title('Available Leagues');
            $rows = [];
            foreach (self::LEAGUES as $id => $name) {
                $rows[] = [$id, $name];
            }
            $io->table(['ID', 'League'], $rows);
            return Command::SUCCESS;
        }

        $season = (int) $input->getOption('season');
        $importAll = $input->getOption('all');
        $leagueId = $input->getOption('league');

        if (!$importAll && !$leagueId) {
            $io->error('Please specify --league ID or use --all to import all leagues');
            $io->info('Use --list-leagues to see available leagues');
            return Command::FAILURE;
        }

        $leagues = $importAll ? self::LEAGUES : [$leagueId => self::LEAGUES[$leagueId] ?? "League $leagueId"];

        $io->title('Importing League Data');
        $io->info([
            sprintf('Season: %d/%d', $season, $season + 1),
            sprintf('Leagues to import: %d', count($leagues)),
        ]);

        $totalStats = [
            'matches' => 0,
            'created' => 0,
            'updated' => 0,
            'teams' => 0,
            'errors' => 0,
        ];

        foreach ($leagues as $id => $name) {
            $io->section("Importing: $name (ID: $id)");

            try {
                $stats = $this->importLeague((int) $id, $season, $io);
                $totalStats['matches'] += $stats['matches'];
                $totalStats['created'] += $stats['created'];
                $totalStats['updated'] += $stats['updated'];
                $totalStats['teams'] += $stats['teams'];

                $io->success(sprintf(
                    '%s: %d matches (%d new, %d updated), %d teams',
                    $name,
                    $stats['matches'],
                    $stats['created'],
                    $stats['updated'],
                    $stats['teams']
                ));

                // Respect rate limits
                sleep(1);

            } catch (\Exception $e) {
                $io->error("Error importing $name: " . $e->getMessage());
                $totalStats['errors']++;
            }
        }

        $io->title('Import Summary');
        $io->table(
            ['Metric', 'Value'],
            [
                ['Total matches', $totalStats['matches']],
                ['New matches', $totalStats['created']],
                ['Updated matches', $totalStats['updated']],
                ['Teams synced', $totalStats['teams']],
                ['Errors', $totalStats['errors']],
            ]
        );

        if ($totalStats['matches'] > 0) {
            $io->note('Run "app:calculate-elo-ratings" to update team ratings with new data');
        }

        return $totalStats['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function importLeague(int $leagueId, int $season, SymfonyStyle $io): array
    {
        $stats = ['matches' => 0, 'created' => 0, 'updated' => 0, 'teams' => 0];

        // Fetch fixtures for the entire season
        $response = $this->httpClient->request('GET', self::API_BASE_URL . '/fixtures', [
            'headers' => [
                'x-apisports-key' => $this->apiFootballKey,
            ],
            'query' => [
                'league' => $leagueId,
                'season' => $season,
            ],
        ]);

        $data = $response->toArray();
        $fixtures = $data['response'] ?? [];

        $io->progressStart(count($fixtures));

        $teamRepository = $this->entityManager->getRepository(Team::class);
        $matchRepository = $this->entityManager->getRepository(FootballMatch::class);
        $teamsCreated = [];

        foreach ($fixtures as $fixture) {
            try {
                $fixtureData = $fixture['fixture'] ?? [];
                $teams = $fixture['teams'] ?? [];
                $league = $fixture['league'] ?? [];
                $goals = $fixture['goals'] ?? [];

                // Get or create teams
                $homeTeam = $this->getOrCreateTeam(
                    $teams['home']['name'] ?? 'Unknown',
                    $teams['home']['id'] ?? null,
                    $league['name'] ?? 'Unknown',
                    $teamRepository,
                    $teamsCreated
                );

                $awayTeam = $this->getOrCreateTeam(
                    $teams['away']['name'] ?? 'Unknown',
                    $teams['away']['id'] ?? null,
                    $league['name'] ?? 'Unknown',
                    $teamRepository,
                    $teamsCreated
                );

                // Find or create match by teams + date
                $matchDate = new \DateTimeImmutable($fixtureData['date'] ?? 'now');
                $match = $matchRepository->findOneBy([
                    'homeTeam' => $homeTeam,
                    'awayTeam' => $awayTeam,
                    'matchDate' => $matchDate,
                ]);

                if (!$match) {
                    $match = new FootballMatch();
                    $stats['created']++;
                } else {
                    $stats['updated']++;
                }

                $stats['matches']++;

                // Update match data
                $match->setHomeTeam($homeTeam);
                $match->setAwayTeam($awayTeam);
                $match->setLeague($league['name'] ?? 'Unknown');
                $match->setMatchDate($matchDate);

                // Set scores if available
                if (isset($goals['home'])) {
                    $match->setHomeScore((int) $goals['home']);
                }
                if (isset($goals['away'])) {
                    $match->setAwayScore((int) $goals['away']);
                }

                // Set status
                $status = $this->mapStatus($fixtureData['status']['long'] ?? 'Not Started');
                $match->setStatus(MatchStatus::from($status));

                // Set stadium if available
                if (isset($fixtureData['venue']['name'])) {
                    $match->setStadium($fixtureData['venue']['name']);
                }

                $this->entityManager->persist($match);

            } catch (\Exception $e) {
                // Skip problematic fixtures
                continue;
            }

            $io->progressAdvance();
        }

        $this->entityManager->flush();
        $io->progressFinish();

        $stats['teams'] = count($teamsCreated);

        return $stats;
    }

    private function getOrCreateTeam(
        string $name,
        ?int $externalId,
        string $league,
        $repository,
        array &$teamsCreated
    ): Team {
        // Check cache first
        $cacheKey = $name . '_' . $league;
        if (isset($teamsCreated[$cacheKey])) {
            return $teamsCreated[$cacheKey];
        }

        // Try to find existing team
        $team = $repository->findOneBy(['name' => $name, 'league' => $league]);

        if (!$team) {
            // Try by name only
            $team = $repository->findOneBy(['name' => $name]);
        }

        if (!$team) {
            $team = new Team();
            $team->setName($name);
            $team->setLeague($league);
            $team->setSport('football');
            $team->setEloRating(1500.0);

            $this->entityManager->persist($team);
        }

        $teamsCreated[$cacheKey] = $team;

        return $team;
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'Not Started', 'Time to be defined', 'TBD' => 'Not Started',
            'First Half', '1H' => 'First Half',
            'Halftime', 'HT' => 'Halftime',
            'Second Half', '2H' => 'Second Half',
            'Extra Time', 'ET', 'Break Time', 'BT' => 'live',
            'Penalty In Progress', 'P' => 'live',
            'Match Finished', 'FT', 'AET', 'PEN' => 'Match Finished',
            'Match Suspended', 'SUSP' => 'postponed',
            'Match Interrupted', 'INT' => 'postponed',
            'Match Postponed', 'PST' => 'Match Postponed',
            'Match Cancelled', 'CANC' => 'cancelled',
            'Match Abandoned', 'ABD' => 'cancelled',
            'Technical Loss', 'AWD', 'WO' => 'Match Finished',
            'Live', 'LIVE' => 'live',
            'In Progress' => 'In Progress',
            default => $status,
        };
    }
}
