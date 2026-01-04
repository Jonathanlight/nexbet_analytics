<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Data\Adapter\ApiFootballAdapter;
use App\Service\Data\MatchSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync:matches',
    description: 'Synchronise les matchs (et optionnellement les cotes) depuis les APIs',
)]
final class SyncOddsCommand extends Command
{
    public function __construct(
        private readonly MatchSyncService $matchSyncService,
        private readonly ApiFootballAdapter $apiFootballAdapter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'date',
                'd',
                InputOption::VALUE_OPTIONAL,
                'Date spécifique (format: Y-m-d). Par défaut: aujourd\'hui'
            )
            ->addOption(
                'days',
                null,
                InputOption::VALUE_OPTIONAL,
                'Nombre de jours à synchroniser (à partir d\'aujourd\'hui)',
                1
            )
            ->addOption(
                'with-odds',
                'o',
                InputOption::VALUE_NONE,
                'Récupérer les cotes (double le nombre de requêtes API)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Synchronisation des cotes des matchs');

        $dateString = $input->getOption('date');
        $days = (int) $input->getOption('days');
        $withOdds = $input->getOption('with-odds');

        try {
            $startDate = $dateString
                ? new \DateTimeImmutable($dateString)
                : new \DateTimeImmutable('today');
        } catch (\Exception $e) {
            $io->error('Format de date invalide. Utilisez le format Y-m-d (ex: 2024-01-15)');

            return Command::FAILURE;
        }

        // Activer la récupération des cotes si demandé
        if ($withOdds) {
            $this->apiFootballAdapter->setFetchOdds(true);
            $io->warning('⚠️  Récupération des cotes activée : double le nombre de requêtes API !');
        }

        $io->info(sprintf(
            'Synchronisation des matchs%s pour %d jour(s) à partir du %s',
            $withOdds ? ' avec cotes' : '',
            $days,
            $startDate->format('Y-m-d')
        ));

        $totalStats = [
            'synced' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
        ];

        $io->progressStart($days);

        for ($i = 0; $i < $days; ++$i) {
            $currentDate = $startDate->modify("+{$i} days");

            $stats = $this->matchSyncService->syncMatchesByDate($currentDate);

            $totalStats['synced'] += $stats['synced'];
            $totalStats['created'] += $stats['created'];
            $totalStats['updated'] += $stats['updated'];
            $totalStats['errors'] += $stats['errors'];

            $io->progressAdvance();
        }

        $io->progressFinish();

        $io->success('Synchronisation terminée !');

        $io->table(
            ['Statistique', 'Valeur'],
            [
                ['Matchs synchronisés', $totalStats['synced']],
                ['Matchs créés', $totalStats['created']],
                ['Matchs mis à jour', $totalStats['updated']],
                ['Erreurs', $totalStats['errors']],
            ]
        );

        return Command::SUCCESS;
    }
}
