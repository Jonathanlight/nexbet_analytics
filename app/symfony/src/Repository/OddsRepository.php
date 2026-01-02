<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FootballMatch;
use App\Entity\Odds;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Odds>
 */
class OddsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Odds::class);
    }

    /**
     * Trouve les meilleures cotes pour un match et type de pari.
     *
     * @return Odds[]
     */
    public function findBestOddsForMatch(FootballMatch $match, string $betType): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.match = :match')
            ->andWhere('o.betType = :betType')
            ->orderBy('o.odds', 'DESC')
            ->setParameter('match', $match)
            ->setParameter('betType', $betType)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les cotes d'un match.
     *
     * @return Odds[]
     */
    public function findByMatch(FootballMatch $match): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.match = :match')
            ->orderBy('o.betType', 'ASC')
            ->addOrderBy('o.bookmaker', 'ASC')
            ->setParameter('match', $match)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les meilleures cotes par bookmaker.
     *
     * @return Odds[]
     */
    public function findBestOddsByBookmaker(FootballMatch $match, string $bookmaker): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.match = :match')
            ->andWhere('o.bookmaker = :bookmaker')
            ->orderBy('o.betType', 'ASC')
            ->setParameter('match', $match)
            ->setParameter('bookmaker', $bookmaker)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compare les cotes entre bookmakers.
     */
    public function compareBookmakers(FootballMatch $match, string $betType, string $market): array
    {
        $odds = $this->createQueryBuilder('o')
            ->where('o.match = :match')
            ->andWhere('o.betType = :betType')
            ->andWhere('o.market = :market')
            ->orderBy('o.odds', 'DESC')
            ->setParameter('match', $match)
            ->setParameter('betType', $betType)
            ->setParameter('market', $market)
            ->getQuery()
            ->getResult();

        $comparison = [];
        foreach ($odds as $odd) {
            $comparison[] = [
                'bookmaker' => $odd->getBookmaker(),
                'odds' => $odd->getOdds(),
                'implied_probability' => $odd->getImpliedProbabilityPercentage(),
            ];
        }

        return $comparison;
    }

    /**
     * Trouve les value bets (cotes avec valeur).
     */
    public function findValueBets(float $minExpectedValue = 1.1): array
    {
        // Cette méthode sera implémentée avec la logique de value bet
        // après avoir calculé les probabilités prédites

        return [];
    }

    /**
     * Met à jour les meilleures cotes.
     */
    public function updateBestOdds(FootballMatch $match): void
    {
        $betTypes = $this->createQueryBuilder('o')
            ->select('DISTINCT o.betType')
            ->where('o.match = :match')
            ->setParameter('match', $match)
            ->getQuery()
            ->getResult();

        foreach ($betTypes as $betTypeData) {
            $betType = $betTypeData['betType'];

            $markets = $this->createQueryBuilder('o')
                ->select('DISTINCT o.market')
                ->where('o.match = :match')
                ->andWhere('o.betType = :betType')
                ->setParameter('match', $match)
                ->setParameter('betType', $betType)
                ->getQuery()
                ->getResult();

            foreach ($markets as $marketData) {
                $market = $marketData['market'];

                // Reset all odds for this combination
                $this->createQueryBuilder('o')
                    ->update()
                    ->set('o.isBestOdds', ':false')
                    ->where('o.match = :match')
                    ->andWhere('o.betType = :betType')
                    ->andWhere('o.market = :market')
                    ->setParameter('false', false)
                    ->setParameter('match', $match)
                    ->setParameter('betType', $betType)
                    ->setParameter('market', $market)
                    ->getQuery()
                    ->execute();

                // Find and mark best odds
                $bestOdd = $this->createQueryBuilder('o')
                    ->where('o.match = :match')
                    ->andWhere('o.betType = :betType')
                    ->andWhere('o.market = :market')
                    ->orderBy('o.odds', 'DESC')
                    ->setParameter('match', $match)
                    ->setParameter('betType', $betType)
                    ->setParameter('market', $market)
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($bestOdd) {
                    $bestOdd->setIsBestOdds(true);
                    $this->getEntityManager()->flush();
                }
            }
        }
    }
}
