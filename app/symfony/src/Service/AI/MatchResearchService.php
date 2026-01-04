<?php

declare(strict_types=1);

namespace App\Service\AI;

use App\Entity\FootballMatch;
use App\Service\Data\Interface\CacheInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service de recherche IA pour enrichir les prédictions avec des données externes.
 * Utilise Claude API avec capacités de recherche web pour analyser les matchs.
 */
final class MatchResearchService
{
    private const CACHE_TTL = 3600; // 1 heure

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly string $anthropicApiKey,
    ) {
    }

    /**
     * Recherche des informations sur un match pour améliorer les prédictions.
     */
    public function researchMatch(FootballMatch $match): array
    {
        $cacheKey = sprintf('ai_research_%d', $match->getId());

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $homeTeam = $match->getHomeTeam()->getName();
        $awayTeam = $match->getAwayTeam()->getName();
        $league = $match->getLeague();
        $matchDate = $match->getMatchDate()->format('Y-m-d');

        try {
            $research = $this->performAIResearch($homeTeam, $awayTeam, $league, $matchDate);
            $this->cache->set($cacheKey, $research, self::CACHE_TTL);

            return $research;
        } catch (\Exception $e) {
            $this->logger->warning('AI research failed', [
                'match_id' => $match->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->getDefaultResearch();
        }
    }

    /**
     * Recherche en batch pour plusieurs matchs.
     *
     * @param FootballMatch[] $matches
     *
     * @return array<int, array>
     */
    public function researchMatches(array $matches): array
    {
        $results = [];

        foreach ($matches as $match) {
            $results[$match->getId()] = $this->researchMatch($match);
        }

        return $results;
    }

    /**
     * Effectue la recherche IA via Claude API.
     */
    private function performAIResearch(string $homeTeam, string $awayTeam, string $league, string $matchDate): array
    {
        if (empty($this->anthropicApiKey)) {
            return $this->getDefaultResearch();
        }

        $prompt = $this->buildResearchPrompt($homeTeam, $awayTeam, $league, $matchDate);

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

            return $this->parseAIResponse($content);
        } catch (\Exception $e) {
            $this->logger->error('Claude API request failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->getDefaultResearch();
        }
    }

    /**
     * Construit le prompt pour la recherche IA.
     */
    private function buildResearchPrompt(string $homeTeam, string $awayTeam, string $league, string $matchDate): string
    {
        return <<<PROMPT
Analyse le match de football suivant et fournis des informations pour améliorer les prédictions :

Match: {$homeTeam} vs {$awayTeam}
Championnat: {$league}
Date: {$matchDate}

Réponds UNIQUEMENT en JSON avec cette structure exacte :
{
    "form_analysis": {
        "home_form": "description courte de la forme récente",
        "away_form": "description courte de la forme récente",
        "home_form_score": 0-100,
        "away_form_score": 0-100
    },
    "key_factors": [
        "facteur 1",
        "facteur 2",
        "facteur 3"
    ],
    "injuries_suspensions": {
        "home_missing": ["joueur1", "joueur2"],
        "away_missing": ["joueur1"]
    },
    "prediction_adjustment": {
        "home_boost": -10 à +10,
        "away_boost": -10 à +10,
        "draw_boost": -5 à +5
    },
    "confidence_modifier": 0.8 à 1.2,
    "recommended_bet": "1" ou "X" ou "2" ou "1X" ou "X2" ou "12",
    "risk_level": "low" ou "medium" ou "high",
    "analysis_summary": "Résumé en 1-2 phrases"
}
PROMPT;
    }

    /**
     * Parse la réponse de l'IA.
     */
    private function parseAIResponse(string $content): array
    {
        // Extraire le JSON de la réponse
        if (preg_match('/\{[\s\S]*\}/m', $content, $matches)) {
            $jsonStr = $matches[0];

            try {
                $parsed = json_decode($jsonStr, true, 512, JSON_THROW_ON_ERROR);

                // Valider et normaliser la structure
                return $this->normalizeResearchData($parsed);
            } catch (\JsonException $e) {
                $this->logger->warning('Failed to parse AI JSON response', [
                    'content' => substr($content, 0, 500),
                ]);
            }
        }

        return $this->getDefaultResearch();
    }

    /**
     * Normalise les données de recherche.
     */
    private function normalizeResearchData(array $data): array
    {
        return [
            'form_analysis' => [
                'home_form' => $data['form_analysis']['home_form'] ?? 'Unknown',
                'away_form' => $data['form_analysis']['away_form'] ?? 'Unknown',
                'home_form_score' => $this->clamp((int) ($data['form_analysis']['home_form_score'] ?? 50), 0, 100),
                'away_form_score' => $this->clamp((int) ($data['form_analysis']['away_form_score'] ?? 50), 0, 100),
            ],
            'key_factors' => array_slice($data['key_factors'] ?? [], 0, 5),
            'injuries_suspensions' => [
                'home_missing' => $data['injuries_suspensions']['home_missing'] ?? [],
                'away_missing' => $data['injuries_suspensions']['away_missing'] ?? [],
            ],
            'prediction_adjustment' => [
                'home_boost' => $this->clamp((float) ($data['prediction_adjustment']['home_boost'] ?? 0), -10, 10),
                'away_boost' => $this->clamp((float) ($data['prediction_adjustment']['away_boost'] ?? 0), -10, 10),
                'draw_boost' => $this->clamp((float) ($data['prediction_adjustment']['draw_boost'] ?? 0), -5, 5),
            ],
            'confidence_modifier' => $this->clamp((float) ($data['confidence_modifier'] ?? 1.0), 0.8, 1.2),
            'recommended_bet' => $data['recommended_bet'] ?? null,
            'risk_level' => in_array($data['risk_level'] ?? '', ['low', 'medium', 'high']) ? $data['risk_level'] : 'medium',
            'analysis_summary' => $data['analysis_summary'] ?? '',
            'has_ai_analysis' => true,
        ];
    }

    /**
     * Retourne des données de recherche par défaut.
     */
    private function getDefaultResearch(): array
    {
        return [
            'form_analysis' => [
                'home_form' => 'No data available',
                'away_form' => 'No data available',
                'home_form_score' => 50,
                'away_form_score' => 50,
            ],
            'key_factors' => [],
            'injuries_suspensions' => [
                'home_missing' => [],
                'away_missing' => [],
            ],
            'prediction_adjustment' => [
                'home_boost' => 0,
                'away_boost' => 0,
                'draw_boost' => 0,
            ],
            'confidence_modifier' => 1.0,
            'recommended_bet' => null,
            'risk_level' => 'medium',
            'analysis_summary' => '',
            'has_ai_analysis' => false,
        ];
    }

    /**
     * Limite une valeur entre min et max.
     */
    private function clamp(float|int $value, float|int $min, float|int $max): float|int
    {
        return max($min, min($max, $value));
    }
}
