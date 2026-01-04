<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\BasketballMatch;
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
    name: 'app:generate-basketball-matches',
    description: 'Genere des matchs de basketball de test',
)]
final class GenerateBasketballMatchesCommand extends Command
{
    private const NBA_TEAMS = [
        'Los Angeles Lakers',
        'Golden State Warriors',
        'Boston Celtics',
        'Miami Heat',
        'Milwaukee Bucks',
        'Phoenix Suns',
        'Denver Nuggets',
        'Philadelphia 76ers',
        'Brooklyn Nets',
        'Dallas Mavericks',
        'Memphis Grizzlies',
        'Cleveland Cavaliers',
        'New York Knicks',
        'Sacramento Kings',
        'LA Clippers',
        'Atlanta Hawks',
    ];

    private const EUROLEAGUE_TEAMS = [
        'Real Madrid',
        'FC Barcelona',
        'Olympiacos',
        'Fenerbahce',
        'CSKA Moscow',
        'Anadolu Efes',
        'Maccabi Tel Aviv',
        'Panathinaikos',
        'Bayern Munich',
        'AS Monaco',
        'Virtus Bologna',
        'Partizan Belgrade',
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

        $io->title('Generation de matchs de basketball');

        $created = 0;

        // Generer des matchs NBA
        $io->section('Matchs NBA');
        $nbaCount = (int) ceil($count * 0.7);
        for ($i = 0; $i < $nbaCount; ++$i) {
            $match = $this->createMatch(self::NBA_TEAMS, 'NBA', $days);
            if ($match) {
                $this->entityManager->persist($match);
                ++$created;
            }
        }

        // Generer des matchs Euroleague
        $io->section('Matchs Euroleague');
        $euroCount = $count - $nbaCount;
        for ($i = 0; $i < $euroCount; ++$i) {
            $match = $this->createMatch(self::EUROLEAGUE_TEAMS, 'Euroleague', $days);
            if ($match) {
                $this->entityManager->persist($match);
                ++$created;
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d matchs de basketball generes !', $created));

        return Command::SUCCESS;
    }

    private function createMatch(array $teams, string $league, int $daysRange): ?BasketballMatch
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
        $hour = random_int(18, 23);
        $minute = [0, 30][random_int(0, 1)];
        $matchDate = new \DateTimeImmutable("+{$daysOffset} days {$hour}:{$minute}:00");

        // Creer le match
        $match = new BasketballMatch();
        $match->setHomeTeam($homeTeam);
        $match->setAwayTeam($awayTeam);
        $match->setLeague($league);
        $match->setMatchDate($matchDate);
        $match->setStatus('scheduled');

        // Stats simulees
        $match->setHomeStats([
            'avg_points' => random_int(100, 120) + random_int(0, 99) / 100,
            'avg_rebounds' => random_int(40, 50) + random_int(0, 99) / 100,
            'avg_assists' => random_int(20, 28) + random_int(0, 99) / 100,
            'avg_steals' => random_int(6, 10) + random_int(0, 99) / 100,
            'avg_blocks' => random_int(4, 7) + random_int(0, 99) / 100,
            'fg_pct' => random_int(44, 50) + random_int(0, 99) / 100,
            'three_pt_pct' => random_int(33, 40) + random_int(0, 99) / 100,
            'ft_pct' => random_int(75, 85) + random_int(0, 99) / 100,
            'pace' => random_int(95, 105) + random_int(0, 99) / 100,
            'def_rating' => random_int(105, 115) + random_int(0, 99) / 100,
            'key_players' => [
                ['name' => 'Star Player', 'avg_points' => random_int(20, 30), 'avg_rebounds' => random_int(5, 10), 'avg_assists' => random_int(4, 8)],
            ],
        ]);

        $match->setAwayStats([
            'avg_points' => random_int(100, 120) + random_int(0, 99) / 100,
            'avg_rebounds' => random_int(40, 50) + random_int(0, 99) / 100,
            'avg_assists' => random_int(20, 28) + random_int(0, 99) / 100,
            'avg_steals' => random_int(6, 10) + random_int(0, 99) / 100,
            'avg_blocks' => random_int(4, 7) + random_int(0, 99) / 100,
            'fg_pct' => random_int(44, 50) + random_int(0, 99) / 100,
            'three_pt_pct' => random_int(33, 40) + random_int(0, 99) / 100,
            'ft_pct' => random_int(75, 85) + random_int(0, 99) / 100,
            'pace' => random_int(95, 105) + random_int(0, 99) / 100,
            'def_rating' => random_int(105, 115) + random_int(0, 99) / 100,
            'key_players' => [
                ['name' => 'Star Player', 'avg_points' => random_int(18, 28), 'avg_rebounds' => random_int(5, 10), 'avg_assists' => random_int(4, 8)],
            ],
        ]);

        $match->setHeadToHeadStats([
            'total' => random_int(3, 10),
            'home_wins' => random_int(1, 5),
            'away_wins' => random_int(1, 5),
            'avg_total' => random_int(200, 230),
            'avg_margin' => random_int(5, 15),
        ]);

        return $match;
    }

    private function getOrCreateTeam(string $name, string $league): Team
    {
        $team = $this->teamRepository->findOneBy(['name' => $name]);

        if (null === $team) {
            $team = new Team();
            $team->setName($name);
            $team->setSport('basketball');
            $team->setLeague($league);
            $team->setCountry('NBA' === $league ? 'USA' : 'Europe');

            $this->entityManager->persist($team);
        }

        return $team;
    }
}
