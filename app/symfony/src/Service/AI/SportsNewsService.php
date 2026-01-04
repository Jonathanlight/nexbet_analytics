<?php

declare(strict_types=1);

namespace App\Service\AI;

use App\Service\Data\Interface\CacheInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service de recherche d'actualités sportives pour enrichir les prédictions.
 * Utilise plusieurs sources : API Anthropic avec recherche web, RSS feeds, etc.
 */
final class SportsNewsService
{
    private const CACHE_TTL = 1800; // 30 minutes

    // Sources d'actualités sportives par sport
    private const NEWS_SOURCES = [
        'football' => [
            'https://www.espn.com/soccer/',
            'https://www.skysports.com/football',
            'https://www.goal.com',
        ],
        'basketball' => [
            'https://www.espn.com/nba/',
            'https://www.nba.com/news',
            'https://www.basketball-reference.com',
        ],
        'hockey' => [
            'https://www.espn.com/nhl/',
            'https://www.nhl.com/news',
            'https://www.tsn.ca/nhl',
        ],
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly string $anthropicApiKey,
    ) {
    }

    /**
     * Recherche les actualités importantes pour un match.
     */
    public function getMatchNews(string $sport, string $homeTeam, string $awayTeam, string $league): array
    {
        $cacheKey = sprintf('news_%s_%s_%s', $sport, md5($homeTeam), md5($awayTeam));

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $news = $this->fetchNewsWithAI($sport, $homeTeam, $awayTeam, $league);
        $this->cache->set($cacheKey, $news, self::CACHE_TTL);

        return $news;
    }

    /**
     * Obtient les facteurs d'impact basés sur les actualités.
     */
    public function getNewsImpactFactors(string $sport, string $homeTeam, string $awayTeam, string $league): array
    {
        $news = $this->getMatchNews($sport, $homeTeam, $awayTeam, $league);

        return [
            'home_impact' => $news['impact_analysis']['home_team_impact'] ?? 0,
            'away_impact' => $news['impact_analysis']['away_team_impact'] ?? 0,
            'match_importance' => $news['impact_analysis']['match_importance'] ?? 'normal',
            'weather_impact' => $news['impact_analysis']['weather_impact'] ?? 0,
            'confidence_modifier' => $news['impact_analysis']['confidence_modifier'] ?? 1.0,
            'key_factors' => $news['key_factors'] ?? [],
            'risk_factors' => $news['risk_factors'] ?? [],
        ];
    }

    /**
     * Recherche les blessures et suspensions récentes.
     */
    public function getInjuriesAndSuspensions(string $sport, string $teamName): array
    {
        $cacheKey = sprintf('injuries_%s_%s', $sport, md5($teamName));

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $injuries = $this->fetchInjuriesWithAI($sport, $teamName);
        $this->cache->set($cacheKey, $injuries, self::CACHE_TTL);

        return $injuries;
    }

    /**
     * Analyse complète pour un match avec toutes les actualités.
     */
    public function getCompleteMatchAnalysis(
        string $sport,
        string $homeTeam,
        string $awayTeam,
        string $league,
        \DateTimeInterface $matchDate,
    ): array {
        $news = $this->getMatchNews($sport, $homeTeam, $awayTeam, $league);
        $homeInjuries = $this->getInjuriesAndSuspensions($sport, $homeTeam);
        $awayInjuries = $this->getInjuriesAndSuspensions($sport, $awayTeam);

        // Calculer l'impact global
        $totalImpact = $this->calculateTotalImpact($news, $homeInjuries, $awayInjuries);

        return [
            'news' => $news,
            'injuries' => [
                'home' => $homeInjuries,
                'away' => $awayInjuries,
            ],
            'impact_summary' => $totalImpact,
            'prediction_adjustments' => $this->generatePredictionAdjustments($totalImpact, $sport),
            'analysis_timestamp' => new \DateTimeImmutable(),
        ];
    }

    private function fetchNewsWithAI(string $sport, string $homeTeam, string $awayTeam, string $league): array
    {
        if (empty($this->anthropicApiKey)) {
            return $this->getDefaultNews();
        }

        $prompt = $this->buildNewsPrompt($sport, $homeTeam, $awayTeam, $league);

        try {
            $response = $this->httpClient->request('POST', 'https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-api-key' => $this->anthropicApiKey,
                    'anthropic-version' => '2023-06-01',
                ],
                'json' => [
                    'model' => 'claude-sonnet-4-20250514',
                    'max_tokens' => 2048,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ],
            ]);

            $data = $response->toArray();
            $content = $data['content'][0]['text'] ?? '';

            return $this->parseNewsResponse($content);
        } catch (\Exception $e) {
            $this->logger->warning('News fetch failed', ['error' => $e->getMessage()]);

            return $this->getDefaultNews();
        }
    }

    private function buildNewsPrompt(string $sport, string $homeTeam, string $awayTeam, string $league): string
    {
        $sportName = match ($sport) {
            'football' => 'football/soccer',
            'basketball' => 'basketball (NBA/Euroleague)',
            'hockey' => 'ice hockey (NHL)',
            default => $sport,
        };

        return <<<PROMPT
En tant qu'analyste sportif expert, analyse le match de {$sportName} suivant et fournis des informations récentes qui pourraient impacter le résultat :

Match: {$homeTeam} vs {$awayTeam}
Championnat: {$league}

Recherche et analyse les informations suivantes :
1. Forme récente des deux équipes (5 derniers matchs)
2. Blessures et suspensions confirmées
3. Changements tactiques ou d'entraîneur récents
4. Conditions météo prévues (si pertinent)
5. Enjeux du match (qualification, relégation, derby, etc.)
6. Historique récent des confrontations
7. Joueurs clés en forme ou en méforme
8. Tout facteur externe pouvant influencer le match

Réponds UNIQUEMENT en JSON avec cette structure :
{
    "recent_form": {
        "home": {"last_5": "WDLWW", "goals_scored": 8, "goals_conceded": 4, "trend": "ascending"},
        "away": {"last_5": "LWDWL", "goals_scored": 5, "goals_conceded": 7, "trend": "unstable"}
    },
    "injuries_suspensions": {
        "home": [{"player": "nom", "status": "injured/suspended", "importance": "key/regular/bench"}],
        "away": [{"player": "nom", "status": "injured/suspended", "importance": "key/regular/bench"}]
    },
    "tactical_news": {
        "home": "description des changements tactiques ou null",
        "away": "description des changements tactiques ou null"
    },
    "match_context": {
        "importance": "critical/high/normal/low",
        "type": "derby/relegation_battle/title_decider/normal",
        "stakes_home": "description de l'enjeu",
        "stakes_away": "description de l'enjeu"
    },
    "key_factors": [
        "facteur 1 important",
        "facteur 2 important"
    ],
    "risk_factors": [
        "facteur de risque pour la prédiction"
    ],
    "impact_analysis": {
        "home_team_impact": -10 à +10,
        "away_team_impact": -10 à +10,
        "match_importance": "critical/high/normal/low",
        "weather_impact": -5 à +5,
        "confidence_modifier": 0.7 à 1.3
    },
    "expert_insight": "Résumé en 2-3 phrases de l'analyse"
}
PROMPT;
    }

    private function parseNewsResponse(string $content): array
    {
        if (preg_match('/\{[\s\S]*\}/m', $content, $matches)) {
            try {
                $parsed = json_decode($matches[0], true, 512, JSON_THROW_ON_ERROR);

                return $this->normalizeNewsData($parsed);
            } catch (\JsonException $e) {
                $this->logger->warning('Failed to parse news JSON', ['content' => substr($content, 0, 500)]);
            }
        }

        return $this->getDefaultNews();
    }

    private function normalizeNewsData(array $data): array
    {
        return [
            'recent_form' => [
                'home' => [
                    'last_5' => $data['recent_form']['home']['last_5'] ?? 'XXXXX',
                    'goals_scored' => $data['recent_form']['home']['goals_scored'] ?? 0,
                    'goals_conceded' => $data['recent_form']['home']['goals_conceded'] ?? 0,
                    'trend' => $data['recent_form']['home']['trend'] ?? 'unknown',
                ],
                'away' => [
                    'last_5' => $data['recent_form']['away']['last_5'] ?? 'XXXXX',
                    'goals_scored' => $data['recent_form']['away']['goals_scored'] ?? 0,
                    'goals_conceded' => $data['recent_form']['away']['goals_conceded'] ?? 0,
                    'trend' => $data['recent_form']['away']['trend'] ?? 'unknown',
                ],
            ],
            'injuries_suspensions' => [
                'home' => $data['injuries_suspensions']['home'] ?? [],
                'away' => $data['injuries_suspensions']['away'] ?? [],
            ],
            'tactical_news' => $data['tactical_news'] ?? ['home' => null, 'away' => null],
            'match_context' => [
                'importance' => $data['match_context']['importance'] ?? 'normal',
                'type' => $data['match_context']['type'] ?? 'normal',
                'stakes_home' => $data['match_context']['stakes_home'] ?? '',
                'stakes_away' => $data['match_context']['stakes_away'] ?? '',
            ],
            'key_factors' => array_slice($data['key_factors'] ?? [], 0, 5),
            'risk_factors' => array_slice($data['risk_factors'] ?? [], 0, 3),
            'impact_analysis' => [
                'home_team_impact' => $this->clamp($data['impact_analysis']['home_team_impact'] ?? 0, -10, 10),
                'away_team_impact' => $this->clamp($data['impact_analysis']['away_team_impact'] ?? 0, -10, 10),
                'match_importance' => $data['impact_analysis']['match_importance'] ?? 'normal',
                'weather_impact' => $this->clamp($data['impact_analysis']['weather_impact'] ?? 0, -5, 5),
                'confidence_modifier' => $this->clamp($data['impact_analysis']['confidence_modifier'] ?? 1.0, 0.7, 1.3),
            ],
            'expert_insight' => $data['expert_insight'] ?? '',
            'has_analysis' => true,
        ];
    }

    private function fetchInjuriesWithAI(string $sport, string $teamName): array
    {
        if (empty($this->anthropicApiKey)) {
            return $this->getDefaultInjuries();
        }

        $prompt = <<<PROMPT
Liste les blessures et suspensions actuelles connues pour l'équipe de {$sport} : {$teamName}

Réponds UNIQUEMENT en JSON :
{
    "players": [
        {"name": "nom du joueur", "status": "injured/suspended/doubtful", "reason": "raison", "expected_return": "date ou unknown", "impact_rating": 1-10}
    ],
    "total_impact": 0-100,
    "key_absences": ["joueur clé 1", "joueur clé 2"]
}
PROMPT;

        try {
            $response = $this->httpClient->request('POST', 'https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-api-key' => $this->anthropicApiKey,
                    'anthropic-version' => '2023-06-01',
                ],
                'json' => [
                    'model' => 'claude-sonnet-4-20250514',
                    'max_tokens' => 1024,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ],
            ]);

            $data = $response->toArray();
            $content = $data['content'][0]['text'] ?? '';

            if (preg_match('/\{[\s\S]*\}/m', $content, $matches)) {
                return json_decode($matches[0], true) ?? $this->getDefaultInjuries();
            }
        } catch (\Exception $e) {
            $this->logger->warning('Injuries fetch failed', ['error' => $e->getMessage()]);
        }

        return $this->getDefaultInjuries();
    }

    private function calculateTotalImpact(array $news, array $homeInjuries, array $awayInjuries): array
    {
        $homeImpact = $news['impact_analysis']['home_team_impact'] ?? 0;
        $awayImpact = $news['impact_analysis']['away_team_impact'] ?? 0;

        // Ajouter l'impact des blessures
        $homeInjuryImpact = -($homeInjuries['total_impact'] ?? 0) / 20; // Converti en échelle -5 à 0
        $awayInjuryImpact = -($awayInjuries['total_impact'] ?? 0) / 20;

        $homeImpact += $homeInjuryImpact;
        $awayImpact += $awayInjuryImpact;

        // Impact de l'importance du match
        $importanceMultiplier = match ($news['match_context']['importance'] ?? 'normal') {
            'critical' => 1.3,
            'high' => 1.15,
            'normal' => 1.0,
            'low' => 0.9,
            default => 1.0,
        };

        return [
            'home_total_impact' => round($homeImpact, 2),
            'away_total_impact' => round($awayImpact, 2),
            'net_advantage' => round($homeImpact - $awayImpact, 2),
            'importance_multiplier' => $importanceMultiplier,
            'risk_level' => $this->assessRiskLevel($news, $homeInjuries, $awayInjuries),
            'confidence_adjustment' => $news['impact_analysis']['confidence_modifier'] ?? 1.0,
        ];
    }

    private function assessRiskLevel(array $news, array $homeInjuries, array $awayInjuries): string
    {
        $riskFactors = 0;

        // Blessures de joueurs clés
        if (count($homeInjuries['key_absences'] ?? []) > 0) {
            ++$riskFactors;
        }
        if (count($awayInjuries['key_absences'] ?? []) > 0) {
            ++$riskFactors;
        }

        // Type de match
        if (in_array($news['match_context']['type'] ?? '', ['derby', 'title_decider'])) {
            ++$riskFactors;
        }

        // Forme instable
        if (($news['recent_form']['home']['trend'] ?? '') === 'unstable') {
            ++$riskFactors;
        }
        if (($news['recent_form']['away']['trend'] ?? '') === 'unstable') {
            ++$riskFactors;
        }

        // Facteurs de risque identifiés
        $riskFactors += count($news['risk_factors'] ?? []);

        if ($riskFactors >= 5) {
            return 'high';
        }
        if ($riskFactors >= 3) {
            return 'medium';
        }

        return 'low';
    }

    private function generatePredictionAdjustments(array $impact, string $sport): array
    {
        $adjustments = [
            'home_probability_boost' => 0,
            'away_probability_boost' => 0,
            'draw_probability_boost' => 0,
            'confidence_multiplier' => 1.0,
            'recommended_caution' => false,
        ];

        $netAdvantage = $impact['net_advantage'] ?? 0;

        // Ajuster les probabilités basées sur l'impact net
        if ($netAdvantage > 3) {
            $adjustments['home_probability_boost'] = min(15, $netAdvantage * 2);
            $adjustments['away_probability_boost'] = max(-10, -$netAdvantage);
        } elseif ($netAdvantage < -3) {
            $adjustments['away_probability_boost'] = min(15, abs($netAdvantage) * 2);
            $adjustments['home_probability_boost'] = max(-10, $netAdvantage);
        }

        // Ajuster la confiance selon le risque
        $adjustments['confidence_multiplier'] = match ($impact['risk_level'] ?? 'low') {
            'high' => 0.8,
            'medium' => 0.9,
            'low' => 1.0,
            default => 1.0,
        };

        $adjustments['confidence_multiplier'] *= $impact['confidence_adjustment'] ?? 1.0;
        $adjustments['recommended_caution'] = ($impact['risk_level'] ?? 'low') === 'high';

        return $adjustments;
    }

    private function getDefaultNews(): array
    {
        return [
            'recent_form' => [
                'home' => ['last_5' => 'XXXXX', 'goals_scored' => 0, 'goals_conceded' => 0, 'trend' => 'unknown'],
                'away' => ['last_5' => 'XXXXX', 'goals_scored' => 0, 'goals_conceded' => 0, 'trend' => 'unknown'],
            ],
            'injuries_suspensions' => ['home' => [], 'away' => []],
            'tactical_news' => ['home' => null, 'away' => null],
            'match_context' => ['importance' => 'normal', 'type' => 'normal', 'stakes_home' => '', 'stakes_away' => ''],
            'key_factors' => [],
            'risk_factors' => [],
            'impact_analysis' => [
                'home_team_impact' => 0,
                'away_team_impact' => 0,
                'match_importance' => 'normal',
                'weather_impact' => 0,
                'confidence_modifier' => 1.0,
            ],
            'expert_insight' => '',
            'has_analysis' => false,
        ];
    }

    private function getDefaultInjuries(): array
    {
        return [
            'players' => [],
            'total_impact' => 0,
            'key_absences' => [],
        ];
    }

    private function clamp(float|int $value, float|int $min, float|int $max): float|int
    {
        return max($min, min($max, $value));
    }
}
