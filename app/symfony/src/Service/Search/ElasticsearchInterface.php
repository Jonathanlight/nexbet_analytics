<?php

declare(strict_types=1);

namespace App\Service\Search;

/**
 * Interface pour le service Elasticsearch.
 * Permet le mocking dans les tests unitaires.
 */
interface ElasticsearchInterface
{
    /**
     * Vérifie si Elasticsearch est disponible.
     */
    public function isAvailable(): bool;

    /**
     * Crée un index avec son mapping.
     */
    public function createIndex(string $index, array $mapping = []): bool;

    /**
     * Supprime un index.
     */
    public function deleteIndex(string $index): bool;

    /**
     * Vérifie si un index existe.
     */
    public function indexExists(string $index): bool;

    /**
     * Indexe un document.
     */
    public function index(string $index, string $id, array $document): bool;

    /**
     * Indexe plusieurs documents en bulk.
     *
     * @param array<array{id: string, document: array}> $documents
     */
    public function bulkIndex(string $index, array $documents): array;

    /**
     * Récupère un document par ID.
     */
    public function get(string $index, string $id): ?array;

    /**
     * Supprime un document.
     */
    public function delete(string $index, string $id): bool;

    /**
     * Recherche des documents.
     */
    public function search(string $index, array $query, int $from = 0, int $size = 10): array;

    /**
     * Recherche full-text simple.
     */
    public function searchText(string $index, string $text, array $fields, int $size = 10): array;

    /**
     * Met à jour un document partiellement.
     */
    public function update(string $index, string $id, array $fields): bool;

    /**
     * Compte les documents correspondant à une requête.
     */
    public function count(string $index, array $query = []): int;

    /**
     * Récupère les statistiques d'un index.
     */
    public function getIndexStats(string $index): array;

    /**
     * Rafraîchit un index (force l'indexation immédiate).
     */
    public function refresh(string $index): bool;
}
