<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\HockeyMatch;
use App\Entity\Team;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-hockey-matches',
    description: 'Genere des matchs de hockey de test',
)]
final class GenerateHockeyMatchesCommand extends Command
{
    private const NHL_TEAMS = [
        'Boston Bruins',
        'Toronto Maple Leafs',
        'Tampa Bay Lightning',
        'Florida Panthers',
        'New York Rangers',
        'Carolina Hurricanes',
        'New Jersey Devils',
        'Pittsburgh Penguins',
        'Colorado Avalanche',
        'Dallas Stars',
        'Vegas Golden Knights',
        'Edmonton Oilers',
        'Los Angeles Kings',
        'Seattle Kraken',
        'Winnipeg Jets',
        'Minnesota Wild',
    ];

    private const KHL_TEAMS = [
        'SKA Saint Petersburg',
        'CSKA Moscow',
        'Ak Bars Kazan',
        'Metallurg Magnitogorsk',
        'Dynamo Moscow',
        'Lokomotiv Yaroslavl',
        'Avangard Omsk',
        'Salavat Yulaev',
        'Jokerit Helsinki',
        'Barys Nur-Sultan',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TeamRepository $teamRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('count', 'c', InputOption::VALUE_OPTIONAL, 'Nombre de matchs a generer', 20)
            ->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Nombre de jours a couvrir', 7)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = (int) $input->getOption('count');
        $days = (int) $input->getOption('days');

        $io->title('Generation de matchs de hockey');

        $created = 0;

        // Generer des matchs NHL
        $io->section('Matchs NHL');
        $nhlCount = (int) ceil($count * 0.7);
        for ($i = 0; $i < $nhlCount; ++$i) {
            $match = $this->createMatch(self::NHL_TEAMS, 'NHL', $days);
            if ($match) {
                $this->entityManager->persist($match);
                ++$created;
            }
        }

        // Generer des matchs KHL
        $io->section('Matchs KHL');
        $khlCount = $count - $nhlCount;
        for ($i = 0; $i < $khlCount; ++$i) {
            $match = $this->createMatch(self::KHL_TEAMS, 'KHL', $days);
            if ($match) {
                $this->entityManager->persist($match);
                ++$created;
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d matchs de hockey generes !', $created));

        return Command::SUCCESS;
    }

    private function createMatch(array $teams, string $league, int $daysRange): ?HockeyMatch
    {
        // Selectionner 2 equipes aleatoires
        $shuffled = $teams;
        shuffle($shuffled);
        $homeTeamName = $shuffled[0];
        $awayTeamName = $shuffled[1];

        // Recuperer ou creer les equipes
        $homeTeam = $this->getOrCreateTeam($homeTeamName, $league);
        $awayTeam = $this->getOrCreateTeam($awayTeamName, $league);

        // Date aleatoire dans les prochains jours
        $daysOffset = random_int(0, $daysRange);
        $hour = random_int(18, 22);
        $minute = [0, 30][random_int(0, 1)];
        $matchDate = new \DateTimeImmutable("+{$daysOffset} days {$hour}:{$minute}:00");

        // Creer le match
        $match = new HockeyMatch();
        $match->setHomeTeam($homeTeam);
        $match->setAwayTeam($awayTeam);
        $match->setLeague($league);
        $match->setMatchDate($matchDate);
        $match->setStatus('scheduled');

        // Stats simulees
        $match->setHomeStats([
            'avg_goals' => 2.5 + random_int(0, 10) / 10,
            'avg_shots' => random_int(28, 36) + random_int(0, 99) / 100,
            'power_play_pct' => random_int(15, 28) + random_int(0, 99) / 100,
            'penalty_kill_pct' => random_int(75, 88) + random_int(0, 99) / 100,
            'faceoff_pct' => random_int(45, 55) + random_int(0, 99) / 100,
            'save_pct' => 0.90 + random_int(0, 5) / 100,
        ]);

        $match->setAwayStats([
            'avg_goals' => 2.5 + random_int(0, 10) / 10,
            'avg_shots' => random_int(28, 36) + random_int(0, 99) / 100,
            'power_play_pct' => random_int(15, 28) + random_int(0, 99) / 100,
            'penalty_kill_pct' => random_int(75, 88) + random_int(0, 99) / 100,
            'faceoff_pct' => random_int(45, 55) + random_int(0, 99) / 100,
            'save_pct' => 0.90 + random_int(0, 5) / 100,
        ]);

        $match->setHeadToHeadStats([
            'total' => random_int(3, 10),
            'home_wins' => random_int(1, 5),
            'away_wins' => random_int(1, 5),
            'avg_total' => random_int(5, 7),
            'avg_margin' => random_int(1, 3),
        ]);

        return $match;
    }

    private function getOrCreateTeam(string $name, string $league): Team
    {
        $team = $this->teamRepository->findOneBy(['name' => $name]);

        if (null === $team) {
            $team = new Team();
            $team->setName($name);
            $team->setSport('hockey');
            $team->setLeague($league);
            $team->setCountry('NHL' === $league ? 'USA/Canada' : 'Russia');

            $this->entityManager->persist($team);
        }

        return $team;
    }
}
