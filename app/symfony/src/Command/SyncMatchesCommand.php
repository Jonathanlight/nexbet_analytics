<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Cache\DashboardCacheService;
use App\Service\Data\MatchSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande pour synchroniser les matchs en background.
 * A executer via CRON toutes les 15-30 minutes.
 *
 * Exemple CRON:
 * * /15 * * * * php /var/www/symfony/bin/console app:sync-matches
 */
#[AsCommand(
    name: 'app:sync-matches',
    description: 'Synchronise les matchs depuis les APIs externes',
)]
final class SyncMatchesCommand extends Command
{
    public function __construct(
        private readonly MatchSyncService $matchSyncService,
        private readonly DashboardCacheService $dashboardCache,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('date', 'd', InputOption::VALUE_OPTIONAL, 'Date au format Y-m-d (par défaut: aujourd\'hui)')
            ->addOption('clean', 'c', InputOption::VALUE_NONE, 'Nettoyer les anciens matchs (30+ jours)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Synchronisation des matchs depuis les APIs');

        // Nettoyer les anciens matchs si demandé
        if ($input->getOption('clean')) {
            $io->section('Nettoyage des anciens matchs');
            $deleted = $this->matchSyncService->cleanOldMatches(30);
            $io->success(sprintf('%d matchs anciens supprimés', $deleted));
        }

        // Récupérer la date
        $dateString = $input->getOption('date');
        $date = $dateString ? new \DateTimeImmutable($dateString) : new \DateTimeImmutable('today');

        $io->section(sprintf('Synchronisation des matchs du %s', $date->format('Y-m-d')));

        // Synchroniser
        $io->progressStart();
        $stats = $this->matchSyncService->syncMatchesByDate($date);
        $io->progressFinish();

        // Afficher les résultats
        $io->success('Synchronisation terminée !');

        $io->table(
            ['Métrique', 'Valeur'],
            [
                ['Matchs synchronisés', $stats['synced']],
                ['Nouveaux matchs', $stats['created']],
                ['Matchs mis à jour', $stats['updated']],
                ['Erreurs', $stats['errors']],
            ]
        );

        // Invalider le cache si des matchs ont été synchronisés
        if ($stats['synced'] > 0) {
            $this->dashboardCache->invalidateDashboardCache();
            $io->info('Cache du dashboard invalidé');
        }

        return $stats['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
