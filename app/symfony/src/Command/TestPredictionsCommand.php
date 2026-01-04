<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\MatchStatus;
use App\Repository\FootballMatchRepository;
use App\Service\Prediction\ResultPredictionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-predictions',
    description: 'Test predictions for scheduled matches',
)]
class TestPredictionsCommand extends Command
{
    public function __construct(
        private readonly FootballMatchRepository $matchRepository,
        private readonly ResultPredictionService $predictionService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Number of matches to test', 10);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int) $input->getOption('limit');

        $io->title('Testing Predictions');

        // Get scheduled matches
        $matches = $this->matchRepository->createQueryBuilder('m')
            ->where('m.status = :status')
            ->setParameter('status', MatchStatus::SCHEDULED)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $io->info(sprintf('Testing predictions for %d matches', count($matches)));

        $results = [];
        $predictions_seen = [];

        foreach ($matches as $match) {
            $homeTeam = $match->getHomeTeam()->getName();
            $awayTeam = $match->getAwayTeam()->getName();

            try {
                $prediction = $this->predictionService->predictResult($match);
                $probs = $prediction['probabilities'];

                $probKey = sprintf('%.2f-%.2f-%.2f', $probs['1'], $probs['X'], $probs['2']);
                $predictions_seen[$probKey] = ($predictions_seen[$probKey] ?? 0) + 1;

                $results[] = [
                    'Match' => substr($homeTeam, 0, 20).' vs '.substr($awayTeam, 0, 20),
                    'Pred' => $prediction['prediction'],
                    '1' => $probs['1'].'%',
                    'X' => $probs['X'].'%',
                    '2' => $probs['2'].'%',
                    'Conf' => $prediction['confidence'].'%',
                    'Type' => $prediction['competition_type'] ?? 'league',
                    'Data' => $prediction['data_quality'] ?? 'N/A',
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'Match' => substr($homeTeam, 0, 20).' vs '.substr($awayTeam, 0, 20),
                    'Pred' => 'Err',
                    '1' => 'Error',
                    'X' => 'Error',
                    '2' => 'Error',
                    'Conf' => 'N/A',
                    'Type' => 'N/A',
                    'Data' => 'N/A',
                ];
                $io->warning(sprintf('Error for %s vs %s: %s', $homeTeam, $awayTeam, $e->getMessage()));
            }
        }

        $io->table(['Match', 'Pred', '1', 'X', '2', 'Conf', 'Type', 'Data'], $results);

        // Check for duplicates
        $io->section('Duplicate analysis');
        $duplicates = array_filter($predictions_seen, fn ($count) => $count > 1);

        if (empty($duplicates)) {
            $io->success('All predictions have unique probabilities!');
        } else {
            $io->warning(sprintf('Found %d duplicate probability sets:', count($duplicates)));
            foreach ($duplicates as $probs => $count) {
                $io->text(sprintf('  - %s appears %d times', $probs, $count));
            }
        }

        $io->success(sprintf('Tested %d matches with %d unique prediction sets', count($matches), count($predictions_seen)));

        return Command::SUCCESS;
    }
}
