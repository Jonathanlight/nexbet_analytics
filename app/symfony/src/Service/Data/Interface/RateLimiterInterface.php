<?php

declare(strict_types=1);

namespace App\Service\Data\Interface;

interface RateLimiterInterface
{
    /**
     * Vérifie si une requête peut être effectuée.
     */
    public function allow(string $key): bool;

    /**
     * Enregistre une tentative de requête.
     */
    public function hit(string $key): void;

    /**
     * Récupère le nombre de requêtes restantes.
     */
    public function remaining(string $key): int;

    /**
     * Réinitialise le compteur pour une clé.
     */
    public function reset(string $key): void;
}
