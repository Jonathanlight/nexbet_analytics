<?php

declare(strict_types=1);

namespace App\Service\Data\Adapter;

use App\Service\Data\DTO\WeatherData;
use App\Service\Data\Interface\CacheInterface;
use App\Service\Data\Interface\RateLimiterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Adapter pour OpenWeatherMap API.
 * Limite: 1000 requêtes/jour gratuit.
 */
final class OpenWeatherApiAdapter
{
    private const API_BASE_URL = 'https://api.openweathermap.org/data/2.5';
    private const RATE_LIMIT_KEY = 'openweather_api';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly RateLimiterInterface $rateLimiter,
        private readonly string $apiKey,
    ) {
    }

    public function fetchWeatherForVenue(string $city): ?WeatherData
    {
        if (!$this->rateLimiter->allow(self::RATE_LIMIT_KEY)) {
            return null;
        }

        $cacheKey = sprintf('weather_%s', md5($city));

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        try {
            $this->rateLimiter->hit(self::RATE_LIMIT_KEY);

            $response = $this->httpClient->request('GET', self::API_BASE_URL.'/weather', [
                'query' => [
                    'q' => $city,
                    'appid' => $this->apiKey,
                    'units' => 'metric',
                ],
            ]);

            $data = $response->toArray();
            $weather = $this->transformToWeatherData($data);

            $this->cache->set($cacheKey, $weather, 1800); // 30 minutes

            return $weather;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getName(): string
    {
        return 'OpenWeatherMap';
    }

    private function transformToWeatherData(array $data): WeatherData
    {
        return new WeatherData(
            temperature: $data['main']['temp'] ?? 20.0,
            description: $data['weather'][0]['description'] ?? 'clear',
            windSpeed: $data['wind']['speed'] ?? 0.0,
            humidity: $data['main']['humidity'] ?? 50,
            precipitation: $data['rain']['1h'] ?? 0.0,
        );
    }
}
