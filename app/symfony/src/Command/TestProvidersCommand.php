<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Data\Adapter\ApiFootballAdapter;
use App\Service\Data\Adapter\FootballDataApiAdapter;
use App\Service\Data\Adapter\TheOddsApiAdapter;
use App\Service\Data\Adapter\UnibetAdapter;
use App\Service\Data\MatchDataAggregatorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test:providers',
    description: 'Teste les providers de données et affiche le statut',
)]
final class TestProvidersCommand extends Command
{
    public function __construct(
        private readonly MatchDataAggregatorService $aggregator,
        private readonly UnibetAdapter $unibetAdapter,
        private readonly ApiFootballAdapter $apiFootballAdapter,
        private readonly FootballDataApiAdapter $footballDataAdapter,
        private readonly TheOddsApiAdapter $oddsApiAdapter,
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
                'Date à tester (format: Y-m-d). Par défaut: aujourd\'hui'
            )
            ->addOption(
                'provider',
                'p',
                InputOption::VALUE_OPTIONAL,
                'Provider spécifique à tester (unibet, api-football, football-data, odds-api)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Test des providers de données');

        $dateString = $input->getOption('date');
        $providerFilter = $input->getOption('provider');

        try {
            $date = $dateString
                ? new \DateTimeImmutable($dateString)
                : new \DateTimeImmutable('today');
        } catch (\Exception $e) {
            $io->error('Format de date invalide.');

            return Command::FAILURE;
        }

        $io->info(sprintf('Date de test: %s', $date->format('Y-m-d')));

        // Statut des providers
        $io->section('Statut des providers');
        $status = $this->aggregator->getProvidersStatus();
        $statusTable = [];
        foreach ($status as $key => $provider) {
            $statusTable[] = [
                $key,
                $provider['name'],
                $provider['available'] ? '✅ Disponible' : '❌ Non disponible',
            ];
        }
        $io->table(['ID', 'Nom', 'Statut'], $statusTable);

        // Test des providers individuels
        $providers = [
            'unibet' => $this->unibetAdapter,
            'api-football' => $this->apiFootballAdapter,
            'football-data' => $this->footballDataAdapter,
            'odds-api' => $this->oddsApiAdapter,
        ];

        if (null !== $providerFilter) {
            if (!isset($providers[$providerFilter])) {
                $io->error(sprintf('Provider inconnu: %s', $providerFilter));

                return Command::FAILURE;
            }
            $providers = [$providerFilter => $providers[$providerFilter]];
        }

        $io->section('Matchs par provider');

        $matchCounts = [];
        foreach ($providers as $key => $provider) {
            $io->text(sprintf('Test de %s...', $provider->getName()));

            try {
                $matches = $provider->fetchMatchesByDate($date);
                $count = count($matches);
                $matchCounts[] = [$key, $provider->getName(), $count];

                // Afficher quelques matchs en exemple
                if ($count > 0 && $io->isVerbose()) {
                    $io->text('  Exemples:');
                    $examples = array_slice($matches, 0, 5);
                    foreach ($examples as $match) {
                        $io->text(sprintf(
                            '    - %s vs %s (%s)',
                            $match->homeTeam,
                            $match->awayTeam,
                            $match->league
                        ));
                    }
                }
            } catch (\Exception $e) {
                $matchCounts[] = [$key, $provider->getName(), 'Erreur: '.$e->getMessage()];
            }
        }

        $io->table(['ID', 'Nom', 'Matchs trouvés'], $matchCounts);

        // Test agrégateur
        $io->section('Test de l\'agrégateur');
        $allMatches = $this->aggregator->fetchMatchesByDate($date);
        $io->info(sprintf('Total matchs agrégés (dédupliqués): %d', count($allMatches)));

        // Grouper par ligue
        $byLeague = [];
        foreach ($allMatches as $match) {
            $league = $match->league;
            if (!isset($byLeague[$league])) {
                $byLeague[$league] = 0;
            }
            ++$byLeague[$league];
        }

        arsort($byLeague);
        $leagueTable = [];
        foreach (array_slice($byLeague, 0, 20, true) as $league => $count) {
            $leagueTable[] = [$league, $count];
        }
        $io->table(['Ligue', 'Matchs'], $leagueTable);

        $io->success('Test terminé !');

        return Command::SUCCESS;
    }
}
