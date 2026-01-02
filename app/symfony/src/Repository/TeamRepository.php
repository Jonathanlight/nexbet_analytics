<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Team>
 */
class TeamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Team::class);
    }

    /**
     * Trouve une équipe par nom et sport.
     */
    public function findByNameAndSport(string $name, string $sport): ?Team
    {
        return $this->createQueryBuilder('t')
            ->where('t.name = :name')
            ->andWhere('t.sport = :sport')
            ->setParameter('name', $name)
            ->setParameter('sport', $sport)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve toutes les équipes d'un championnat.
     *
     * @return Team[]
     */
    public function findByLeague(string $league, string $sport = 'football'): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.league = :league')
            ->andWhere('t.sport = :sport')
            ->orderBy('t.position', 'ASC')
            ->setParameter('league', $league)
            ->setParameter('sport', $sport)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les meilleures équipes par rating Elo.
     *
     * @return Team[]
     */
    public function findTopByEloRating(string $sport, int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.sport = :sport')
            ->andWhere('t.eloRating IS NOT NULL')
            ->orderBy('t.eloRating', 'DESC')
            ->setParameter('sport', $sport)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les équipes avec la meilleure forme récente.
     *
     * @return Team[]
     */
    public function findByBestForm(string $league, int $limit = 5): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.league = :league')
            ->orderBy('t.points', 'DESC')
            ->addOrderBy('t.goalsFor', 'DESC')
            ->setParameter('league', $league)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les statistiques d'une équipe.
     */
    public function getTeamStatistics(int $teamId): array
    {
        $team = $this->find($teamId);

        if (!$team) {
            return [];
        }

        return [
            'name' => $team->getName(),
            'position' => $team->getPosition(),
            'matches_played' => $team->getMatchesPlayed(),
            'wins' => $team->getWins(),
            'draws' => $team->getDraws(),
            'losses' => $team->getLosses(),
            'goals_for' => $team->getGoalsFor(),
            'goals_against' => $team->getGoalsAgainst(),
            'goal_difference' => $team->getGoalDifference(),
            'points' => $team->getPoints(),
            'win_percentage' => $team->getWinPercentage(),
            'form' => $team->getFormLast5(),
            'elo_rating' => $team->getEloRating(),
        ];
    }

    /**
     * Met à jour le rating Elo d'une équipe.
     */
    public function updateEloRating(int $teamId, float $newRating): void
    {
        $team = $this->find($teamId);

        if ($team) {
            $team->setEloRating($newRating);
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Met à jour les statistiques d'une équipe.
     */
    public function updateStatistics(int $teamId, array $statistics): void
    {
        $team = $this->find($teamId);

        if ($team) {
            if (isset($statistics['wins'])) {
                $team->setWins($statistics['wins']);
            }
            if (isset($statistics['draws'])) {
                $team->setDraws($statistics['draws']);
            }
            if (isset($statistics['losses'])) {
                $team->setLosses($statistics['losses']);
            }
            if (isset($statistics['goals_for'])) {
                $team->setGoalsFor($statistics['goals_for']);
            }
            if (isset($statistics['goals_against'])) {
                $team->setGoalsAgainst($statistics['goals_against']);
            }
            if (isset($statistics['points'])) {
                $team->setPoints($statistics['points']);
            }
            if (isset($statistics['position'])) {
                $team->setPosition($statistics['position']);
            }

            $this->getEntityManager()->flush();
        }
    }
}
