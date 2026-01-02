<?php

declare(strict_types=1);

namespace App\Service\Data\DTO;

final readonly class WeatherData
{
    public function __construct(
        public float $temperature,
        public string $description,
        public float $windSpeed,
        public int $humidity,
        public float $precipitation = 0.0,
    ) {
    }

    public function toArray(): array
    {
        return [
            'temperature' => $this->temperature,
            'description' => $this->description,
            'wind_speed' => $this->windSpeed,
            'humidity' => $this->humidity,
            'precipitation' => $this->precipitation,
        ];
    }
}
