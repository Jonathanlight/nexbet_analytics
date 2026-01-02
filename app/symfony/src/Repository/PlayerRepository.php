<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Player;
use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Player>
 */
class PlayerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Player::class);
    }

    /**
     * Trouve les meilleurs buteurs d'une équipe.
     *
     * @return Player[]
     */
    public function findTopScorers(Team $team, int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.team = :team')
            ->andWhere('p.goalsScored IS NOT NULL')
            ->orderBy('p.goalsScored', 'DESC')
            ->setParameter('team', $team)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les joueurs disponibles d'une équipe.
     *
     * @return Player[]
     */
    public function findAvailablePlayersByTeam(Team $team): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.team = :team')
            ->andWhere('p.isInjured = :false')
            ->andWhere('p.isSuspended = :false')
            ->orderBy('p.position', 'ASC')
            ->addOrderBy('p.avgRating', 'DESC')
            ->setParameter('team', $team)
            ->setParameter('false', false)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les joueurs avec la plus haute probabilité de marquer.
     *
     * @return Player[]
     */
    public function findTopScoringProbability(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.scoringProbability IS NOT NULL')
            ->andWhere('p.isInjured = :false')
            ->andWhere('p.isSuspended = :false')
            ->orderBy('p.scoringProbability', 'DESC')
            ->setParameter('false', false)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les joueurs par position.
     *
     * @return Player[]
     */
    public function findByPosition(Team $team, string $position): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.team = :team')
            ->andWhere('p.position = :position')
            ->orderBy('p.avgRating', 'DESC')
            ->setParameter('team', $team)
            ->setParameter('position', $position)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les joueurs blessés ou suspendus.
     *
     * @return Player[]
     */
    public function findUnavailablePlayers(Team $team): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.team = :team')
            ->andWhere('p.isInjured = :true OR p.isSuspended = :true')
            ->setParameter('team', $team)
            ->setParameter('true', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Met à jour les statistiques d'un joueur.
     */
    public function updatePlayerStatistics(int $playerId, array $stats): void
    {
        $player = $this->find($playerId);

        if ($player) {
            if (isset($stats['goals'])) {
                $player->setGoalsScored($stats['goals']);
            }
            if (isset($stats['assists'])) {
                $player->setAssists($stats['assists']);
            }
            if (isset($stats['yellow_cards'])) {
                $player->setYellowCards($stats['yellow_cards']);
            }
            if (isset($stats['red_cards'])) {
                $player->setRedCards($stats['red_cards']);
            }
            if (isset($stats['xG'])) {
                $player->setXG($stats['xG']);
            }
            if (isset($stats['xA'])) {
                $player->setXA($stats['xA']);
            }
            if (isset($stats['avg_rating'])) {
                $player->setAvgRating($stats['avg_rating']);
            }
            if (isset($stats['scoring_probability'])) {
                $player->setScoringProbability($stats['scoring_probability']);
            }

            $this->getEntityManager()->flush();
        }
    }
}
