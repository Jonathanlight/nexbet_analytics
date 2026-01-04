<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Data\MatchSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande pour importer les données historiques des matchs.
 * Permet de récupérer les matchs des X derniers jours pour alimenter les statistiques.
 */
#[AsCommand(
    name: 'app:import-historical-data',
    description: 'Import historical match data for better predictions',
)]
final class ImportHistoricalDataCommand extends Command
{
    public function __construct(
        private readonly MatchSyncService $matchSyncService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Number of days to import (default: 30)', 30)
            ->addOption('start-date', null, InputOption::VALUE_OPTIONAL, 'Start date (Y-m-d format)')
            ->addOption('end-date', null, InputOption::VALUE_OPTIONAL, 'End date (Y-m-d format, default: yesterday)')
            ->addOption('update-elo', null, InputOption::VALUE_NONE, 'Update Elo ratings after import')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Importing Historical Match Data');

        // Determine date range
        $days = (int) $input->getOption('days');
        $startDateStr = $input->getOption('start-date');
        $endDateStr = $input->getOption('end-date');

        if ($startDateStr) {
            $startDate = new \DateTimeImmutable($startDateStr);
        } else {
            $startDate = new \DateTimeImmutable("-{$days} days");
        }

        if ($endDateStr) {
            $endDate = new \DateTimeImmutable($endDateStr);
        } else {
            $endDate = new \DateTimeImmutable('yesterday');
        }

        // Validate dates
        if ($startDate > $endDate) {
            $io->error('Start date must be before end date!');
            return Command::FAILURE;
        }

        $totalDays = $startDate->diff($endDate)->days + 1;

        $io->info([
            sprintf('Start date: %s', $startDate->format('Y-m-d')),
            sprintf('End date: %s', $endDate->format('Y-m-d')),
            sprintf('Total days to import: %d', $totalDays),
        ]);

        $io->warning('This may take a while and use API quota. Continue?');

        if (!$io->confirm('Proceed with import?', true)) {
            $io->info('Import cancelled.');
            return Command::SUCCESS;
        }

        // Import matches day by day
        $io->section('Importing matches...');

        $totalStats = [
            'synced' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
            'days_processed' => 0,
        ];

        $io->progressStart($totalDays);

        $currentDate = $startDate;
        while ($currentDate <= $endDate) {
            try {
                $stats = $this->matchSyncService->syncMatchesByDate($currentDate);

                $totalStats['synced'] += $stats['synced'];
                $totalStats['created'] += $stats['created'];
                $totalStats['updated'] += $stats['updated'];
                $totalStats['errors'] += $stats['errors'];
                $totalStats['days_processed']++;

                // Small delay to respect API rate limits
                usleep(500000); // 0.5 second

            } catch (\Exception $e) {
                $io->warning(sprintf('Error on %s: %s', $currentDate->format('Y-m-d'), $e->getMessage()));
                $totalStats['errors']++;
            }

            $currentDate = $currentDate->modify('+1 day');
            $io->progressAdvance();
        }

        $io->progressFinish();

        // Display results
        $io->success('Historical data import completed!');

        $io->table(
            ['Metric', 'Value'],
            [
                ['Days processed', $totalStats['days_processed']],
                ['Total matches synced', $totalStats['synced']],
                ['New matches created', $totalStats['created']],
                ['Matches updated', $totalStats['updated']],
                ['Errors', $totalStats['errors']],
            ]
        );

        // Update Elo ratings if requested
        if ($input->getOption('update-elo')) {
            $io->section('Updating Elo ratings...');

            $result = $this->runEloUpdate($output);

            if ($result === Command::SUCCESS) {
                $io->success('Elo ratings updated successfully!');
            } else {
                $io->warning('Elo rating update completed with warnings.');
            }
        } else {
            $io->note('Run "app:calculate-elo-ratings" to update team ratings based on imported data.');
        }

        return $totalStats['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Run Elo rating update command programmatically.
     */
    private function runEloUpdate(OutputInterface $output): int
    {
        $application = $this->getApplication();

        if (null === $application) {
            return Command::FAILURE;
        }

        $command = $application->find('app:calculate-elo-ratings');

        $arguments = [
            'command' => 'app:calculate-elo-ratings',
        ];

        $input = new \Symfony\Component\Console\Input\ArrayInput($arguments);

        return $command->run($input, $output);
    }
}