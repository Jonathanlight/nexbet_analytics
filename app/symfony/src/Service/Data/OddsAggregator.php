<?php

declare(strict_types=1);

namespace App\Service\Data;

use App\Entity\FootballMatch;
use App\Entity\Odds;
use App\Repository\OddsRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service d'agrégation et de comparaison des cotes.
 */
class OddsAggregator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OddsRepository $oddsRepository,
    ) {
    }

    /**
     * Agrège les cotes de plusieurs bookmakers.
     */
    public function aggregateOdds(FootballMatch $match): array
    {
        $allOdds = $this->oddsRepository->findByMatch($match);

        $aggregated = [];

        foreach ($allOdds as $odd) {
            $betType = $odd->getBetType();
            $market = $odd->getMarket();
            $key = "{$betType}_{$market}";

            if (!isset($aggregated[$key])) {
                $aggregated[$key] = [
                    'bet_type' => $betType,
                    'market' => $market,
                    'bookmakers' => [],
                    'best_odds' => 0,
                    'worst_odds' => PHP_FLOAT_MAX,
                    'avg_odds' => 0,
                ];
            }

            $aggregated[$key]['bookmakers'][$odd->getBookmaker()] = $odd->getOdds();

            if ($odd->getOdds() > $aggregated[$key]['best_odds']) {
                $aggregated[$key]['best_odds'] = $odd->getOdds();
                $aggregated[$key]['best_bookmaker'] = $odd->getBookmaker();
            }

            if ($odd->getOdds() < $aggregated[$key]['worst_odds']) {
                $aggregated[$key]['worst_odds'] = $odd->getOdds();
            }
        }

        // Calculer les moyennes
        foreach ($aggregated as &$data) {
            if (!empty($data['bookmakers'])) {
                $data['avg_odds'] = round(array_sum($data['bookmakers']) / count($data['bookmakers']), 2);
            }
        }

        return $aggregated;
    }

    /**
     * Sauvegarde les cotes dans la base de données.
     */
    public function saveOdds(FootballMatch $match, string $bookmaker, array $oddsData): void
    {
        foreach ($oddsData as $data) {
            $odds = new Odds();
            $odds->setMatch($match);
            $odds->setBookmaker($bookmaker);
            $odds->setBetType($data['bet_type']);
            $odds->setMarket($data['market']);
            $odds->setOdds($data['odds']);

            if (isset($data['margin'])) {
                $odds->setMargin($data['margin']);
            }

            $this->entityManager->persist($odds);
        }

        $this->entityManager->flush();

        // Mettre à jour les meilleures cotes
        $this->oddsRepository->updateBestOdds($match);
    }

    /**
     * Compare les bookmakers pour un type de pari.
     */
    public function compareBookmakersForBet(FootballMatch $match, string $betType, string $market): array
    {
        return $this->oddsRepository->compareBookmakers($match, $betType, $market);
    }

    /**
     * Calcule la marge des bookmakers.
     */
    public function calculateBookmakerMargin(array $odds1X2): float
    {
        if (!isset($odds1X2['1'], $odds1X2['X'], $odds1X2['2'])) {
            return 0.0;
        }

        $impliedProb1 = 1 / $odds1X2['1'];
        $impliedProbX = 1 / $odds1X2['X'];
        $impliedProb2 = 1 / $odds1X2['2'];

        $totalImpliedProb = $impliedProb1 + $impliedProbX + $impliedProb2;
        $margin = ($totalImpliedProb - 1) * 100;

        return round($margin, 2);
    }
}
