<?php

declare(strict_types=1);

namespace App\Service\Search;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ElasticsearchService implements ElasticsearchInterface
{
    private const TIMEOUT = 5.0;

    // Index disponibles
    public const INDEX_FOOTBALL_MATCHES = 'football_matches';
    public const INDEX_BASKETBALL_MATCHES = 'basketball_matches';
    public const INDEX_HOCKEY_MATCHES = 'hockey_matches';
    public const INDEX_TEAMS = 'teams';
    public const INDEX_PREDICTIONS = 'predictions';
    public const INDEX_ANALYTICS = 'analytics';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $host = 'elasticsearch',
        private readonly int $port = 9200,
    ) {
    }

    private function getBaseUrl(): string
    {
        return "http://{$this->host}:{$this->port}";
    }

    public function isAvailable(): bool
    {
        try {
            $response = $this->httpClient->request('GET', $this->getBaseUrl().'/_cluster/health', [
                'timeout' => self::TIMEOUT,
            ]);

            $data = $response->toArray(false);

            return in_array($data['status'] ?? '', ['green', 'yellow'], true);
        } catch (\Exception $e) {
            $this->logger->warning('Elasticsearch not available', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function createIndex(string $index, array $mapping = []): bool
    {
        try {
            $body = [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                    'analysis' => [
                        'analyzer' => [
                            'custom_analyzer' => [
                                'type' => 'custom',
                                'tokenizer' => 'standard',
                                'filter' => ['lowercase', 'asciifolding'],
                            ],
                        ],
                    ],
                ],
            ];

            if (!empty($mapping)) {
                $body['mappings'] = $mapping;
            }

            $response = $this->httpClient->request('PUT', $this->getBaseUrl().'/'.$index, [
                'json' => $body,
                'timeout' => self::TIMEOUT,
            ]);

            $data = $response->toArray(false);
            $success = $data['acknowledged'] ?? false;

            if ($success) {
                $this->logger->info('Index created', ['index' => $index]);
            }

            return $success;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create index', ['index' => $index, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function deleteIndex(string $index): bool
    {
        try {
            $response = $this->httpClient->request('DELETE', $this->getBaseUrl().'/'.$index, [
                'timeout' => self::TIMEOUT,
            ]);

            $data = $response->toArray(false);

            return $data['acknowledged'] ?? false;
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete index', ['index' => $index, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function indexExists(string $index): bool
    {
        try {
            $response = $this->httpClient->request('HEAD', $this->getBaseUrl().'/'.$index, [
                'timeout' => self::TIMEOUT,
            ]);

            return 200 === $response->getStatusCode();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function index(string $index, string $id, array $document): bool
    {
        try {
            $response = $this->httpClient->request('PUT', $this->getBaseUrl().'/'.$index.'/_doc/'.$id, [
                'json' => $document,
                'timeout' => self::TIMEOUT,
            ]);

            $data = $response->toArray(false);

            return in_array($data['result'] ?? '', ['created', 'updated'], true);
        } catch (\Exception $e) {
            $this->logger->error('Failed to index document', [
                'index' => $index,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function bulkIndex(string $index, array $documents): array
    {
        if (empty($documents)) {
            return ['success' => 0, 'failed' => 0];
        }

        try {
            $body = '';
            foreach ($documents as $doc) {
                $body .= json_encode(['index' => ['_index' => $index, '_id' => $doc['id']]])."\n";
                $body .= json_encode($doc['document'])."\n";
            }

            $response = $this->httpClient->request('POST', $this->getBaseUrl().'/_bulk', [
                'headers' => ['Content-Type' => 'application/x-ndjson'],
                'body' => $body,
                'timeout' => 30.0,
            ]);

            $data = $response->toArray(false);

            $success = 0;
            $failed = 0;

            foreach ($data['items'] ?? [] as $item) {
                $result = $item['index']['result'] ?? 'failed';
                if (in_array($result, ['created', 'updated'], true)) {
                    ++$success;
                } else {
                    ++$failed;
                }
            }

            $this->logger->info('Bulk indexing completed', [
                'index' => $index,
                'success' => $success,
                'failed' => $failed,
            ]);

            return ['success' => $success, 'failed' => $failed];
        } catch (\Exception $e) {
            $this->logger->error('Bulk indexing failed', ['index' => $index, 'error' => $e->getMessage()]);

            return ['success' => 0, 'failed' => count($documents), 'error' => $e->getMessage()];
        }
    }

    public function get(string $index, string $id): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $this->getBaseUrl().'/'.$index.'/_doc/'.$id, [
                'timeout' => self::TIMEOUT,
            ]);

            $data = $response->toArray(false);

            if ($data['found'] ?? false) {
                return $data['_source'];
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function delete(string $index, string $id): bool
    {
        try {
            $response = $this->httpClient->request('DELETE', $this->getBaseUrl().'/'.$index.'/_doc/'.$id, [
                'timeout' => self::TIMEOUT,
            ]);

            $data = $response->toArray(false);

            return ($data['result'] ?? '') === 'deleted';
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete document', [
                'index' => $index,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function search(string $index, array $query, int $from = 0, int $size = 10): array
    {
        try {
            $body = [
                'query' => $query,
                'from' => $from,
                'size' => $size,
            ];

            $response = $this->httpClient->request('POST', $this->getBaseUrl().'/'.$index.'/_search', [
                'json' => $body,
                'timeout' => self::TIMEOUT,
            ]);

            $data = $response->toArray(false);

            $hits = [];
            foreach ($data['hits']['hits'] ?? [] as $hit) {
                $hits[] = [
                    'id' => $hit['_id'],
                    'score' => $hit['_score'],
                    'source' => $hit['_source'],
                ];
            }

            return [
                'total' => $data['hits']['total']['value'] ?? 0,
                'hits' => $hits,
                'took' => $data['took'] ?? 0,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Search failed', ['index' => $index, 'error' => $e->getMessage()]);

            return ['total' => 0, 'hits' => [], 'error' => $e->getMessage()];
        }
    }

    public function searchText(string $index, string $text, array $fields, int $size = 10): array
    {
        $query = [
            'multi_match' => [
                'query' => $text,
                'fields' => $fields,
                'type' => 'best_fields',
                'fuzziness' => 'AUTO',
            ],
        ];

        return $this->search($index, $query, 0, $size);
    }

    public function update(string $index, string $id, array $fields): bool
    {
        try {
            $response = $this->httpClient->request('POST', $this->getBaseUrl().'/'.$index.'/_update/'.$id, [
                'json' => ['doc' => $fields],
                'timeout' => self::TIMEOUT,
            ]);

            $data = $response->toArray(false);

            return in_array($data['result'] ?? '', ['updated', 'noop'], true);
        } catch (\Exception $e) {
            $this->logger->error('Update failed', [
                'index' => $index,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function count(string $index, array $query = []): int
    {
        try {
            $body = empty($query) ? [] : ['query' => $query];

            $response = $this->httpClient->request('POST', $this->getBaseUrl().'/'.$index.'/_count', [
                'json' => $body,
                'timeout' => self::TIMEOUT,
            ]);

            $data = $response->toArray(false);

            return $data['count'] ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getIndexStats(string $index): array
    {
        try {
            $response = $this->httpClient->request('GET', $this->getBaseUrl().'/'.$index.'/_stats', [
                'timeout' => self::TIMEOUT,
            ]);

            $data = $response->toArray(false);
            $stats = $data['indices'][$index] ?? [];

            return [
                'docs_count' => $stats['primaries']['docs']['count'] ?? 0,
                'docs_deleted' => $stats['primaries']['docs']['deleted'] ?? 0,
                'store_size' => $stats['primaries']['store']['size_in_bytes'] ?? 0,
                'store_size_human' => $this->formatBytes($stats['primaries']['store']['size_in_bytes'] ?? 0),
                'indexing_total' => $stats['primaries']['indexing']['index_total'] ?? 0,
                'search_total' => $stats['primaries']['search']['query_total'] ?? 0,
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function refresh(string $index): bool
    {
        try {
            $response = $this->httpClient->request('POST', $this->getBaseUrl().'/'.$index.'/_refresh', [
                'timeout' => self::TIMEOUT,
            ]);

            return 200 === $response->getStatusCode();
        } catch (\Exception $e) {
            return false;
        }
    }

    // === Méthodes spécialisées pour l'application ===

    /**
     * Initialise tous les index nécessaires avec leurs mappings optimisés.
     */
    public function initializeIndexes(): array
    {
        $results = [];

        // Index des matchs de football
        $results[self::INDEX_FOOTBALL_MATCHES] = $this->createIndex(self::INDEX_FOOTBALL_MATCHES, [
            'properties' => [
                'home_team' => ['type' => 'text', 'analyzer' => 'custom_analyzer', 'fields' => ['keyword' => ['type' => 'keyword']]],
                'away_team' => ['type' => 'text', 'analyzer' => 'custom_analyzer', 'fields' => ['keyword' => ['type' => 'keyword']]],
                'league' => ['type' => 'keyword'],
                'match_date' => ['type' => 'date'],
                'status' => ['type' => 'keyword'],
                'home_score' => ['type' => 'integer'],
                'away_score' => ['type' => 'integer'],
                'prediction' => ['type' => 'keyword'],
                'confidence' => ['type' => 'float'],
                'probabilities' => ['type' => 'object'],
                'created_at' => ['type' => 'date'],
            ],
        ]);

        // Index des matchs de basketball
        $results[self::INDEX_BASKETBALL_MATCHES] = $this->createIndex(self::INDEX_BASKETBALL_MATCHES, [
            'properties' => [
                'home_team' => ['type' => 'text', 'analyzer' => 'custom_analyzer', 'fields' => ['keyword' => ['type' => 'keyword']]],
                'away_team' => ['type' => 'text', 'analyzer' => 'custom_analyzer', 'fields' => ['keyword' => ['type' => 'keyword']]],
                'league' => ['type' => 'keyword'],
                'match_date' => ['type' => 'date'],
                'status' => ['type' => 'keyword'],
                'home_score' => ['type' => 'integer'],
                'away_score' => ['type' => 'integer'],
                'total_points' => ['type' => 'integer'],
                'prediction' => ['type' => 'keyword'],
                'confidence' => ['type' => 'float'],
                'created_at' => ['type' => 'date'],
            ],
        ]);

        // Index des matchs de hockey
        $results[self::INDEX_HOCKEY_MATCHES] = $this->createIndex(self::INDEX_HOCKEY_MATCHES, [
            'properties' => [
                'home_team' => ['type' => 'text', 'analyzer' => 'custom_analyzer', 'fields' => ['keyword' => ['type' => 'keyword']]],
                'away_team' => ['type' => 'text', 'analyzer' => 'custom_analyzer', 'fields' => ['keyword' => ['type' => 'keyword']]],
                'league' => ['type' => 'keyword'],
                'match_date' => ['type' => 'date'],
                'status' => ['type' => 'keyword'],
                'home_score' => ['type' => 'integer'],
                'away_score' => ['type' => 'integer'],
                'overtime' => ['type' => 'boolean'],
                'prediction' => ['type' => 'keyword'],
                'confidence' => ['type' => 'float'],
                'created_at' => ['type' => 'date'],
            ],
        ]);

        // Index des équipes
        $results[self::INDEX_TEAMS] = $this->createIndex(self::INDEX_TEAMS, [
            'properties' => [
                'name' => ['type' => 'text', 'analyzer' => 'custom_analyzer', 'fields' => ['keyword' => ['type' => 'keyword']]],
                'sport' => ['type' => 'keyword'],
                'league' => ['type' => 'keyword'],
                'country' => ['type' => 'keyword'],
                'elo_rating' => ['type' => 'float'],
                'stats' => ['type' => 'object'],
                'updated_at' => ['type' => 'date'],
            ],
        ]);

        // Index des prédictions
        $results[self::INDEX_PREDICTIONS] = $this->createIndex(self::INDEX_PREDICTIONS, [
            'properties' => [
                'match_id' => ['type' => 'keyword'],
                'sport' => ['type' => 'keyword'],
                'prediction' => ['type' => 'keyword'],
                'confidence' => ['type' => 'float'],
                'was_correct' => ['type' => 'boolean'],
                'probabilities' => ['type' => 'object'],
                'factors' => ['type' => 'object'],
                'created_at' => ['type' => 'date'],
            ],
        ]);

        return $results;
    }

    /**
     * Recherche de matchs avec filtres multiples.
     */
    public function searchMatches(
        string $sport,
        ?string $team = null,
        ?string $league = null,
        ?\DateTimeInterface $dateFrom = null,
        ?\DateTimeInterface $dateTo = null,
        int $size = 20,
    ): array {
        $index = match ($sport) {
            'football' => self::INDEX_FOOTBALL_MATCHES,
            'basketball' => self::INDEX_BASKETBALL_MATCHES,
            'hockey' => self::INDEX_HOCKEY_MATCHES,
            default => throw new \InvalidArgumentException("Sport non supporté: $sport"),
        };

        $must = [];

        if ($team) {
            $must[] = [
                'multi_match' => [
                    'query' => $team,
                    'fields' => ['home_team', 'away_team'],
                    'fuzziness' => 'AUTO',
                ],
            ];
        }

        if ($league) {
            $must[] = ['term' => ['league' => $league]];
        }

        if ($dateFrom || $dateTo) {
            $range = ['match_date' => []];
            if ($dateFrom) {
                $range['match_date']['gte'] = $dateFrom->format('Y-m-d');
            }
            if ($dateTo) {
                $range['match_date']['lte'] = $dateTo->format('Y-m-d');
            }
            $must[] = ['range' => $range];
        }

        $query = empty($must) ? ['match_all' => new \stdClass()] : ['bool' => ['must' => $must]];

        return $this->search($index, $query, 0, $size);
    }

    /**
     * Recherche full-text sur toutes les équipes.
     */
    public function searchTeams(string $query, int $size = 10): array
    {
        return $this->searchText(self::INDEX_TEAMS, $query, ['name^3', 'league', 'country'], $size);
    }

    /**
     * Agrégation des statistiques par ligue.
     */
    public function getLeagueAggregation(string $sport): array
    {
        $index = match ($sport) {
            'football' => self::INDEX_FOOTBALL_MATCHES,
            'basketball' => self::INDEX_BASKETBALL_MATCHES,
            'hockey' => self::INDEX_HOCKEY_MATCHES,
            default => throw new \InvalidArgumentException("Sport non supporté: $sport"),
        };

        try {
            $response = $this->httpClient->request('POST', $this->getBaseUrl().'/'.$index.'/_search', [
                'json' => [
                    'size' => 0,
                    'aggs' => [
                        'leagues' => [
                            'terms' => [
                                'field' => 'league',
                                'size' => 50,
                            ],
                            'aggs' => [
                                'avg_confidence' => ['avg' => ['field' => 'confidence']],
                            ],
                        ],
                    ],
                ],
                'timeout' => self::TIMEOUT,
            ]);

            $data = $response->toArray(false);
            $buckets = $data['aggregations']['leagues']['buckets'] ?? [];

            return array_map(fn ($bucket) => [
                'league' => $bucket['key'],
                'count' => $bucket['doc_count'],
                'avg_confidence' => round($bucket['avg_confidence']['value'] ?? 0, 2),
            ], $buckets);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            ++$i;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
