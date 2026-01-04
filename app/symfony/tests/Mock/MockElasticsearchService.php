<?php

declare(strict_types=1);

namespace App\Tests\Mock;

use App\Service\Search\ElasticsearchInterface;

/**
 * Mock du service Elasticsearch pour les tests unitaires.
 * Utilise un tableau en mémoire comme stockage.
 */
final class MockElasticsearchService implements ElasticsearchInterface
{
    /** @var array<string, array<string, array>> */
    private array $indexes = [];

    private bool $available = true;

    public function setAvailable(bool $available): void
    {
        $this->available = $available;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function createIndex(string $index, array $mapping = []): bool
    {
        if (!$this->available) {
            return false;
        }

        $this->indexes[$index] = [
            'documents' => [],
            'mapping' => $mapping,
        ];

        return true;
    }

    public function deleteIndex(string $index): bool
    {
        if (!$this->available) {
            return false;
        }

        if (isset($this->indexes[$index])) {
            unset($this->indexes[$index]);
            return true;
        }

        return false;
    }

    public function indexExists(string $index): bool
    {
        return isset($this->indexes[$index]);
    }

    public function index(string $index, string $id, array $document): bool
    {
        if (!$this->available) {
            return false;
        }

        if (!isset($this->indexes[$index])) {
            $this->createIndex($index);
        }

        $this->indexes[$index]['documents'][$id] = $document;
        return true;
    }

    public function bulkIndex(string $index, array $documents): array
    {
        if (!$this->available) {
            return ['errors' => true, 'items' => []];
        }

        $items = [];
        foreach ($documents as $doc) {
            $success = $this->index($index, $doc['id'], $doc['document']);
            $items[] = [
                'index' => [
                    '_id' => $doc['id'],
                    'status' => $success ? 201 : 500,
                ],
            ];
        }

        return [
            'errors' => false,
            'items' => $items,
            'took' => 1,
        ];
    }

    public function get(string $index, string $id): ?array
    {
        if (!$this->available) {
            return null;
        }

        return $this->indexes[$index]['documents'][$id] ?? null;
    }

    public function delete(string $index, string $id): bool
    {
        if (!$this->available) {
            return false;
        }

        if (isset($this->indexes[$index]['documents'][$id])) {
            unset($this->indexes[$index]['documents'][$id]);
            return true;
        }

        return false;
    }

    public function search(string $index, array $query, int $from = 0, int $size = 10): array
    {
        if (!$this->available || !isset($this->indexes[$index])) {
            return [
                'hits' => [
                    'total' => ['value' => 0],
                    'hits' => [],
                ],
            ];
        }

        $documents = $this->indexes[$index]['documents'];
        $hits = [];

        // Simulation basique de recherche
        foreach ($documents as $id => $doc) {
            if ($this->matchesQuery($doc, $query)) {
                $hits[] = [
                    '_id' => $id,
                    '_source' => $doc,
                    '_score' => 1.0,
                ];
            }
        }

        // Pagination
        $hits = array_slice($hits, $from, $size);

        return [
            'hits' => [
                'total' => ['value' => count($hits)],
                'hits' => $hits,
            ],
            'took' => 1,
        ];
    }

    public function searchText(string $index, string $text, array $fields, int $size = 10): array
    {
        if (!$this->available || !isset($this->indexes[$index])) {
            return [
                'hits' => [
                    'total' => ['value' => 0],
                    'hits' => [],
                ],
            ];
        }

        $documents = $this->indexes[$index]['documents'];
        $hits = [];
        $textLower = strtolower($text);

        foreach ($documents as $id => $doc) {
            foreach ($fields as $field) {
                if (isset($doc[$field]) && str_contains(strtolower((string) $doc[$field]), $textLower)) {
                    $hits[] = [
                        '_id' => $id,
                        '_source' => $doc,
                        '_score' => 1.0,
                    ];
                    break;
                }
            }
        }

        return [
            'hits' => [
                'total' => ['value' => count($hits)],
                'hits' => array_slice($hits, 0, $size),
            ],
            'took' => 1,
        ];
    }

    public function update(string $index, string $id, array $fields): bool
    {
        if (!$this->available) {
            return false;
        }

        if (!isset($this->indexes[$index]['documents'][$id])) {
            return false;
        }

        $this->indexes[$index]['documents'][$id] = array_merge(
            $this->indexes[$index]['documents'][$id],
            $fields
        );

        return true;
    }

    public function count(string $index, array $query = []): int
    {
        if (!$this->available || !isset($this->indexes[$index])) {
            return 0;
        }

        if (empty($query)) {
            return count($this->indexes[$index]['documents']);
        }

        $count = 0;
        foreach ($this->indexes[$index]['documents'] as $doc) {
            if ($this->matchesQuery($doc, $query)) {
                $count++;
            }
        }

        return $count;
    }

    public function getIndexStats(string $index): array
    {
        if (!isset($this->indexes[$index])) {
            return [];
        }

        return [
            'docs_count' => count($this->indexes[$index]['documents']),
            'store_size' => 'N/A (mock)',
        ];
    }

    public function refresh(string $index): bool
    {
        return $this->available && isset($this->indexes[$index]);
    }

    /**
     * Méthode utilitaire pour les tests : réinitialise tout.
     */
    public function reset(): void
    {
        $this->indexes = [];
        $this->available = true;
    }

    /**
     * Méthode utilitaire pour les tests : retourne tous les index.
     */
    public function getAllIndexes(): array
    {
        return array_keys($this->indexes);
    }

    /**
     * Méthode utilitaire pour les tests : retourne tous les documents d'un index.
     */
    public function getAllDocuments(string $index): array
    {
        return $this->indexes[$index]['documents'] ?? [];
    }

    /**
     * Simulation basique de matching de query Elasticsearch.
     */
    private function matchesQuery(array $document, array $query): bool
    {
        // Match all
        if (isset($query['match_all']) || empty($query)) {
            return true;
        }

        // Match simple
        if (isset($query['match'])) {
            foreach ($query['match'] as $field => $value) {
                if (!isset($document[$field])) {
                    return false;
                }
                if (is_array($value) && isset($value['query'])) {
                    $value = $value['query'];
                }
                if (!str_contains(strtolower((string) $document[$field]), strtolower((string) $value))) {
                    return false;
                }
            }
            return true;
        }

        // Term query
        if (isset($query['term'])) {
            foreach ($query['term'] as $field => $value) {
                if (!isset($document[$field]) || $document[$field] !== $value) {
                    return false;
                }
            }
            return true;
        }

        // Bool query
        if (isset($query['bool'])) {
            $mustMatch = true;
            $shouldMatch = empty($query['bool']['should']);

            // Must conditions
            if (isset($query['bool']['must'])) {
                foreach ($query['bool']['must'] as $subQuery) {
                    if (!$this->matchesQuery($document, $subQuery)) {
                        $mustMatch = false;
                        break;
                    }
                }
            }

            // Should conditions
            if (isset($query['bool']['should'])) {
                foreach ($query['bool']['should'] as $subQuery) {
                    if ($this->matchesQuery($document, $subQuery)) {
                        $shouldMatch = true;
                        break;
                    }
                }
            }

            // Must not conditions
            if (isset($query['bool']['must_not'])) {
                foreach ($query['bool']['must_not'] as $subQuery) {
                    if ($this->matchesQuery($document, $subQuery)) {
                        return false;
                    }
                }
            }

            return $mustMatch && $shouldMatch;
        }

        // Range query
        if (isset($query['range'])) {
            foreach ($query['range'] as $field => $conditions) {
                if (!isset($document[$field])) {
                    return false;
                }
                $value = $document[$field];
                if (isset($conditions['gte']) && $value < $conditions['gte']) {
                    return false;
                }
                if (isset($conditions['gt']) && $value <= $conditions['gt']) {
                    return false;
                }
                if (isset($conditions['lte']) && $value > $conditions['lte']) {
                    return false;
                }
                if (isset($conditions['lt']) && $value >= $conditions['lt']) {
                    return false;
                }
            }
            return true;
        }

        return true;
    }
}