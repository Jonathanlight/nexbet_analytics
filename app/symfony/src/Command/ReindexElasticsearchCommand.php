<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\FootballMatchRepository;
use App\Repository\TeamRepository;
use App\Service\Search\ElasticsearchService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reindex-elasticsearch',
    description: 'Reindex all data into Elasticsearch',
)]
final class ReindexElasticsearchCommand extends Command
{
    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly ElasticsearchService $elasticsearch,
        private readonly FootballMatchRepository $matchRepository,
        private readonly TeamRepository $teamRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Delete and recreate indexes')
            ->addOption('matches', 'm', InputOption::VALUE_NONE, 'Only reindex matches')
            ->addOption('teams', 't', InputOption::VALUE_NONE, 'Only reindex teams')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Elasticsearch Reindexing');

        // Check Elasticsearch availability
        if (!$this->elasticsearch->isAvailable()) {
            $io->error('Elasticsearch is not available. Please check the connection.');
            return Command::FAILURE;
        }

        $io->success('Elasticsearch is available');

        $force = $input->getOption('force');
        $onlyMatches = $input->getOption('matches');
        $onlyTeams = $input->getOption('teams');

        // If no specific option, reindex all
        $reindexAll = !$onlyMatches && !$onlyTeams;

        // Initialize or recreate indexes
        if ($force) {
            $io->section('Recreating indexes...');
            $this->deleteIndexes($io);
        }

        $io->section('Initializing indexes...');
        $results = $this->elasticsearch->initializeIndexes();
        foreach ($results as $index => $success) {
            if ($success) {
                $io->text("  ✓ Index '$index' created");
            } else {
                $io->text("  - Index '$index' already exists");
            }
        }

        $stats = ['matches' => 0, 'teams' => 0];

        // Reindex teams
        if ($reindexAll || $onlyTeams) {
            $io->section('Indexing teams...');
            $stats['teams'] = $this->indexTeams($io);
        }

        // Reindex matches
        if ($reindexAll || $onlyMatches) {
            $io->section('Indexing football matches...');
            $stats['matches'] = $this->indexMatches($io);
        }

        // Display summary
        $io->title('Indexing Summary');
        $io->table(
            ['Index', 'Documents'],
            [
                ['Teams', $stats['teams']],
                ['Matches', $stats['matches']],
            ]
        );

        // Refresh indexes
        $io->section('Refreshing indexes...');
        $this->elasticsearch->refresh(ElasticsearchService::INDEX_TEAMS);
        $this->elasticsearch->refresh(ElasticsearchService::INDEX_FOOTBALL_MATCHES);

        $io->success('Elasticsearch reindexing completed!');

        return Command::SUCCESS;
    }

    private function deleteIndexes(SymfonyStyle $io): void
    {
        $indexes = [
            ElasticsearchService::INDEX_FOOTBALL_MATCHES,
            ElasticsearchService::INDEX_BASKETBALL_MATCHES,
            ElasticsearchService::INDEX_HOCKEY_MATCHES,
            ElasticsearchService::INDEX_TEAMS,
            ElasticsearchService::INDEX_PREDICTIONS,
        ];

        foreach ($indexes as $index) {
            if ($this->elasticsearch->indexExists($index)) {
                $this->elasticsearch->deleteIndex($index);
                $io->text("  ✗ Deleted index '$index'");
            }
        }
    }

    private function indexTeams(SymfonyStyle $io): int
    {
        $teams = $this->teamRepository->findAll();
        $total = count($teams);

        if ($total === 0) {
            $io->warning('No teams to index');
            return 0;
        }

        $io->progressStart($total);
        $documents = [];
        $indexed = 0;

        foreach ($teams as $team) {
            $documents[] = [
                'id' => (string) $team->getId(),
                'document' => [
                    'name' => $team->getName(),
                    'sport' => $team->getSport(),
                    'league' => $team->getLeague(),
                    'elo_rating' => $team->getEloRating(),
                    'updated_at' => (new \DateTimeImmutable())->format('c'),
                ],
            ];

            if (count($documents) >= self::BATCH_SIZE) {
                $result = $this->elasticsearch->bulkIndex(ElasticsearchService::INDEX_TEAMS, $documents);
                $indexed += $result['success'];
                $documents = [];
            }

            $io->progressAdvance();
        }

        // Index remaining documents
        if (!empty($documents)) {
            $result = $this->elasticsearch->bulkIndex(ElasticsearchService::INDEX_TEAMS, $documents);
            $indexed += $result['success'];
        }

        $io->progressFinish();
        $io->text("  Indexed $indexed teams");

        return $indexed;
    }

    private function indexMatches(SymfonyStyle $io): int
    {
        $matches = $this->matchRepository->findAll();
        $total = count($matches);

        if ($total === 0) {
            $io->warning('No matches to index');
            return 0;
        }

        $io->progressStart($total);
        $documents = [];
        $indexed = 0;

        foreach ($matches as $match) {
            $documents[] = [
                'id' => (string) $match->getId(),
                'document' => [
                    'home_team' => $match->getHomeTeam()?->getName(),
                    'away_team' => $match->getAwayTeam()?->getName(),
                    'league' => $match->getLeague(),
                    'match_date' => $match->getMatchDate()?->format('c'),
                    'status' => $match->getStatus()?->value,
                    'home_score' => $match->getHomeScore(),
                    'away_score' => $match->getAwayScore(),
                    'created_at' => (new \DateTimeImmutable())->format('c'),
                ],
            ];

            if (count($documents) >= self::BATCH_SIZE) {
                $result = $this->elasticsearch->bulkIndex(ElasticsearchService::INDEX_FOOTBALL_MATCHES, $documents);
                $indexed += $result['success'];
                $documents = [];
            }

            $io->progressAdvance();
        }

        // Index remaining documents
        if (!empty($documents)) {
            $result = $this->elasticsearch->bulkIndex(ElasticsearchService::INDEX_FOOTBALL_MATCHES, $documents);
            $indexed += $result['success'];
        }

        $io->progressFinish();
        $io->text("  Indexed $indexed matches");

        return $indexed;
    }
}
