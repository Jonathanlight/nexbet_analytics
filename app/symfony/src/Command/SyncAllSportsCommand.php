<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Cache\DashboardCacheService;
use App\Service\Data\BasketballSyncService;
use App\Service\Data\HockeySyncService;
use App\Service\Data\MatchSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande pour synchroniser tous les sports depuis les APIs externes.
 */
#[AsCommand(
    name: 'app:sync:all-sports',
    description: 'Synchronise les matchs de football, basketball et hockey depuis les APIs',
)]
final class SyncAllSportsCommand extends Command
{
    public function __construct(
        private readonly MatchSyncService $footballSyncService,
        private readonly BasketballSyncService $basketballSyncService,
        private readonly HockeySyncService $hockeySyncService,
        private readonly DashboardCacheService $dashboardCache,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('date', 'd', InputOption::VALUE_OPTIONAL, 'Date au format Y-m-d (par defaut: aujourd\'hui)')
            ->addOption('sport', 's', InputOption::VALUE_OPTIONAL, 'Sport specifique: football, basketball, hockey (par defaut: tous)')
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Nombre de jours a synchroniser (1-7)', 1)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Synchronisation des matchs depuis les APIs');

        $sport = $input->getOption('sport');
        $days = min(7, max(1, (int) $input->getOption('days')));

        $dateString = $input->getOption('date');
        $startDate = $dateString ? new \DateTimeImmutable($dateString) : new \DateTimeImmutable('today');

        $totalStats = [
            'football' => ['synced' => 0, 'created' => 0, 'updated' => 0, 'errors' => 0],
            'basketball' => ['synced' => 0, 'created' => 0, 'updated' => 0, 'errors' => 0],
            'hockey' => ['synced' => 0, 'created' => 0, 'updated' => 0, 'errors' => 0],
        ];

        for ($i = 0; $i < $days; ++$i) {
            $date = $startDate->modify("+{$i} days");
            $io->section(sprintf('Synchronisation du %s', $date->format('Y-m-d')));

            // Football
            if (null === $sport || 'football' === $sport) {
                $io->text('Football...');
                try {
                    $stats = $this->footballSyncService->syncMatchesByDate($date);
                    $this->mergeStats($totalStats['football'], $stats);
                    $io->text(sprintf('  -> %d matchs synchronises', $stats['synced']));
                } catch (\Exception $e) {
                    $io->warning('Erreur football: '.$e->getMessage());
                    ++$totalStats['football']['errors'];
                }
            }

            // Basketball
            if (null === $sport || 'basketball' === $sport) {
                $io->text('Basketball...');
                try {
                    $stats = $this->basketballSyncService->syncMatchesByDate($date);
                    $this->mergeStats($totalStats['basketball'], $stats);
                    $io->text(sprintf('  -> %d matchs synchronises', $stats['synced']));
                } catch (\Exception $e) {
                    $io->warning('Erreur basketball: '.$e->getMessage());
                    ++$totalStats['basketball']['errors'];
                }
            }

            // Hockey
            if (null === $sport || 'hockey' === $sport) {
                $io->text('Hockey...');
                try {
                    $stats = $this->hockeySyncService->syncMatchesByDate($date);
                    $this->mergeStats($totalStats['hockey'], $stats);
                    $io->text(sprintf('  -> %d matchs synchronises', $stats['synced']));
                } catch (\Exception $e) {
                    $io->warning('Erreur hockey: '.$e->getMessage());
                    ++$totalStats['hockey']['errors'];
                }
            }
        }

        // Afficher le resume
        $io->section('Resume');

        $rows = [];
        $totalSynced = 0;
        $totalErrors = 0;

        foreach ($totalStats as $sportName => $stats) {
            $rows[] = [
                ucfirst($sportName),
                $stats['synced'],
                $stats['created'],
                $stats['updated'],
                $stats['errors'],
            ];
            $totalSynced += $stats['synced'];
            $totalErrors += $stats['errors'];
        }

        $io->table(
            ['Sport', 'Synchronises', 'Crees', 'Mis a jour', 'Erreurs'],
            $rows
        );

        // Invalider le cache
        if ($totalSynced > 0) {
            $this->dashboardCache->invalidateDashboardCache();
            $io->info('Cache du dashboard invalide');
        }

        if ($totalSynced > 0) {
            $io->success(sprintf('%d matchs synchronises au total !', $totalSynced));
        } else {
            $io->warning('Aucun match synchronise. Verifiez les cles API.');
        }

        return $totalErrors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function mergeStats(array &$total, array $new): void
    {
        $total['synced'] += $new['synced'];
        $total['created'] += $new['created'];
        $total['updated'] += $new['updated'];
        $total['errors'] += $new['errors'];
    }
}
