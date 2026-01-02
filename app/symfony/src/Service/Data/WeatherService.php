<?php

declare(strict_types=1);

namespace App\Service\Data;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service de récupération des conditions météorologiques.
 * Peut utiliser une API comme OpenWeatherMap.
 */
class WeatherService
{
    private const API_URL = 'https://api.openweathermap.org/data/2.5/weather';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $apiKey = null, // À configurer via .env
    ) {
    }

    /**
     * Récupère la météo pour un lieu et une date.
     */
    public function getWeather(string $city, \DateTimeInterface $date): array
    {
        if (!$this->apiKey) {
            return $this->getDefaultWeather();
        }

        try {
            $response = $this->httpClient->request('GET', self::API_URL, [
                'query' => [
                    'q' => $city,
                    'appid' => $this->apiKey,
                    'units' => 'metric',
                ],
            ]);

            $data = $response->toArray();

            return $this->formatWeatherData($data);
        } catch (\Exception $e) {
            return $this->getDefaultWeather();
        }
    }

    /**
     * Analyse l'impact de la météo sur le match.
     */
    public function analyzeWeatherImpact(array $weather): array
    {
        $impact = 'neutral';
        $severity = 0;

        // Pluie
        if (isset($weather['rain']) || 'rain' === $weather['description']) {
            $impact = 'negative';
            $severity = 2;
        }

        // Vent fort
        if (isset($weather['wind_speed']) && $weather['wind_speed'] > 10) {
            $impact = 'negative';
            $severity = max($severity, 1);
        }

        // Température extrême
        if (isset($weather['temperature'])) {
            if ($weather['temperature'] < 5 || $weather['temperature'] > 35) {
                $impact = 'negative';
                $severity = max($severity, 1);
            }
        }

        return [
            'impact' => $impact,
            'severity' => $severity,
            'adjustment_factor' => $this->calculateAdjustmentFactor($severity),
            'recommendations' => $this->getRecommendations($weather),
        ];
    }

    /**
     * Formate les données météo.
     */
    private function formatWeatherData(array $data): array
    {
        return [
            'temperature' => $data['main']['temp'] ?? null,
            'humidity' => $data['main']['humidity'] ?? null,
            'wind_speed' => $data['wind']['speed'] ?? null,
            'description' => $data['weather'][0]['main'] ?? 'Unknown',
            'precipitation' => $data['rain']['1h'] ?? 0,
        ];
    }

    /**
     * Retourne des conditions météo par défaut.
     */
    private function getDefaultWeather(): array
    {
        return [
            'temperature' => 20,
            'humidity' => 60,
            'wind_speed' => 5,
            'description' => 'Clear',
            'precipitation' => 0,
        ];
    }

    /**
     * Calcule un facteur d'ajustement selon la sévérité.
     */
    private function calculateAdjustmentFactor(int $severity): float
    {
        return match ($severity) {
            0 => 1.0,
            1 => 0.95,
            2 => 0.90,
            3 => 0.85,
            default => 1.0,
        };
    }

    /**
     * Génère des recommandations selon la météo.
     */
    private function getRecommendations(array $weather): array
    {
        $recommendations = [];

        if ('rain' === $weather['description'] || $weather['precipitation'] > 0) {
            $recommendations[] = 'Moins de buts attendus avec la pluie';
            $recommendations[] = 'Favoriser les Under plutôt que Over';
        }

        if (isset($weather['wind_speed']) && $weather['wind_speed'] > 10) {
            $recommendations[] = 'Vent fort - peut affecter le jeu aérien';
        }

        return $recommendations;
    }
}
