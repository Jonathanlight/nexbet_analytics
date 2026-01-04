<?php

declare(strict_types=1);

namespace App\Service\Cache;

use App\Repository\FootballMatchRepository;
use App\Service\Prediction\ResultPredictionService;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Service de cache pour les donnees du dashboard.
 * Optimise les performances en mettant en cache les calculs lourds.
 */
final class DashboardCacheService
{
    private const CACHE_TTL_SHORT = 300;    // 5 minutes
    private const CACHE_TTL_MEDIUM = 900;   // 15 minutes
    private const CACHE_TTL_LONG = 3600;    // 1 heure

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly FootballMatchRepository $matchRepository,
        private readonly ResultPredictionService $resultPredictionService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Recupere les statistiques du dashboard (cachees).
     */
    public function getDashboardStats(): array
    {
        return $this->cache->get('dashboard_stats', function (ItemInterface $item) {
            $item->expiresAfter(self::CACHE_TTL_SHORT);

            $this->logger->debug('Computing dashboard stats (cache miss)');

            $todayMatches = $this->matchRepository->findTodayMatches();
            $safeBetsCount = count($this->matchRepository->findSafeBetsToday());
            $highConfidenceCount = count($this->matchRepository->findMatchesWithHighConfidencePredictions(85.0));

            // Calculer value bets en batch (optimise)
            $valueBetsCount = $this->calculateValueBetsCount($todayMatches);

            return [
                'total_matches_today' => count($todayMatches),
                'safe_bets' => $safeBetsCount,
                'high_confidence' => $highConfidenceCount,
                'value_bets' => $valueBetsCount,
                'cached_at' => new \DateTimeImmutable(),
            ];
        });
    }

    /**
     * Recupere les matchs du jour avec predictions (caches).
     */
    public function getTodayMatchesWithPredictions(int $limit = 10): array
    {
        $cacheKey = sprintf('today_matches_predictions_%d', $limit);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($limit) {
            $item->expiresAfter(self::CACHE_TTL_SHORT);

            $this->logger->debug('Computing today matches predictions (cache miss)');

            $matches = $this->matchRepository->findTodayMatches();
            $result = [];

            // Limiter le nombre de predictions calculees
            $matchesToProcess = array_slice($matches, 0, $limit);

            foreach ($matchesToProcess as $match) {
                try {
                    $prediction = $this->resultPredictionService->predictResult($match);
                    $result[] = [
                        'match' => $this->serializeMatch($match),
                        'prediction' => $prediction,
                    ];
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to predict match', [
                        'match_id' => $match->getId(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Trier par confiance decroissante
            usort($result, fn ($a, $b) => ($b['prediction']['confidence'] ?? 0) <=> ($a['prediction']['confidence'] ?? 0));

            return $result;
        });
    }

    /**
     * Recupere les statistiques globales (cache long).
     */
    public function getGlobalStats(): array
    {
        return $this->cache->get('global_stats', function (ItemInterface $item) {
            $item->expiresAfter(self::CACHE_TTL_LONG);

            $this->logger->debug('Computing global stats (cache miss)');

            $totalMatches = $this->matchRepository->count([]);
            $todayMatches = count($this->matchRepository->findTodayMatches());
            $leagueStats = $this->matchRepository->getStatsByLeague();

            // Calculer le taux de reussite (operation lourde)
            $successStats = $this->calculateSuccessRate();

            return [
                'total_matches' => $totalMatches,
                'today_matches' => $todayMatches,
                'league_stats' => $leagueStats,
                'success_rate' => $successStats['rate'],
                'total_predictions' => $successStats['total'],
                'correct_predictions' => $successStats['correct'],
                'cached_at' => new \DateTimeImmutable(),
            ];
        });
    }

    /**
     * Recupere les predictions pour l'API AJAX (leger).
     */
    public function getQuickPredictions(int $limit = 5): array
    {
        $cacheKey = sprintf('quick_predictions_%d', $limit);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($limit) {
            $item->expiresAfter(self::CACHE_TTL_SHORT);

            $matches = $this->matchRepository->findUpcomingMatches($limit);
            $result = [];

            foreach ($matches as $match) {
                try {
                    $prediction = $this->resultPredictionService->predictResult($match);
                    $oddsArray = $match->getOddsArray();
                    $odds1X2 = $oddsArray['1X2'] ?? [];

                    $result[] = [
                        'id' => $match->getId(),
                        'home' => $match->getHomeTeam()->getName(),
                        'away' => $match->getAwayTeam()->getName(),
                        'league' => $match->getLeague(),
                        'date' => $match->getMatchDate()->format('H:i'),
                        'full_date' => $match->getMatchDate()->format('d/m H:i'),
                        'prediction' => $prediction['prediction'],
                        'confidence' => $prediction['confidence'],
                        'probabilities' => $prediction['probabilities'],
                        'odds' => [
                            'home' => $odds1X2['home'] ?? $odds1X2['1'] ?? null,
                            'draw' => $odds1X2['draw'] ?? $odds1X2['X'] ?? null,
                            'away' => $odds1X2['away'] ?? $odds1X2['2'] ?? null,
                        ],
                        'has_odds' => !empty($odds1X2),
                    ];
                } catch (\Exception $e) {
                    continue;
                }
            }

            return $result;
        });
    }

    /**
     * Invalide le cache du dashboard.
     */
    public function invalidateDashboardCache(): void
    {
        $this->cache->delete('dashboard_stats');
        $this->cache->delete('today_matches_predictions_10');
        $this->cache->delete('quick_predictions_5');
    }

    /**
     * Invalide tout le cache.
     */
    public function invalidateAllCache(): void
    {
        $this->invalidateDashboardCache();
        $this->cache->delete('global_stats');
    }

    /**
     * Calcule le nombre de value bets (optimise).
     */
    private function calculateValueBetsCount(array $matches): int
    {
        $count = 0;
        $maxToCheck = min(20, count($matches)); // Limiter pour la perf

        for ($i = 0; $i < $maxToCheck; ++$i) {
            $match = $matches[$i];
            $odds = $match->getOddsArray();

            if (!empty($odds)) {
                try {
                    $prediction = $this->resultPredictionService->predictResult($match);
                    if ($prediction['confidence'] > 70) {
                        ++$count;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return $count;
    }

    /**
     * Calcule le taux de reussite des predictions.
     */
    private function calculateSuccessRate(): array
    {
        $recentMatches = $this->matchRepository->findRecentFinishedMatches(50); // Reduit de 100 a 50
        $correct = 0;
        $total = 0;

        foreach ($recentMatches as $match) {
            if (null === $match->getHomeScore() || null === $match->getAwayScore()) {
                continue;
            }

            try {
                $prediction = $this->resultPredictionService->predictResult($match);
                $actualResult = $this->getActualResult($match);

                if ($prediction['prediction'] === $actualResult) {
                    ++$correct;
                }
                ++$total;
            } catch (\Exception $e) {
                continue;
            }
        }

        return [
            'rate' => $total > 0 ? round(($correct / $total) * 100, 2) : 0,
            'total' => $total,
            'correct' => $correct,
        ];
    }

    /**
     * Serialise un match pour le cache (avec cotes).
     */
    private function serializeMatch(object $match): array
    {
        $oddsArray = $match->getOddsArray();

        // Extraire les cotes 1X2
        $odds1X2 = $oddsArray['1X2'] ?? [];

        return [
            'id' => $match->getId(),
            'home_team' => $match->getHomeTeam()->getName(),
            'away_team' => $match->getAwayTeam()->getName(),
            'league' => $match->getLeague(),
            'match_date' => $match->getMatchDate()->format('Y-m-d H:i'),
            'match_time' => $match->getMatchDate()->format('H:i'),
            'status' => $match->getStatus()->value,
            'odds' => [
                'home' => $odds1X2['home'] ?? $odds1X2['1'] ?? null,
                'draw' => $odds1X2['draw'] ?? $odds1X2['X'] ?? null,
                'away' => $odds1X2['away'] ?? $odds1X2['2'] ?? null,
            ],
            'has_odds' => !empty($odds1X2),
        ];
    }

    private function getActualResult(object $match): string
    {
        $homeScore = $match->getHomeScore();
        $awayScore = $match->getAwayScore();

        if ($homeScore > $awayScore) {
            return '1';
        }
        if ($homeScore < $awayScore) {
            return '2';
        }

        return 'X';
    }
}
