<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Search;

use App\Tests\Unit\AbstractUnitTestCase;
use App\Tests\Mock\MockElasticsearchService;

final class ElasticsearchServiceTest extends AbstractUnitTestCase
{
    private MockElasticsearchService $es;

    protected function setUp(): void
    {
        parent::setUp();
        $this->es = new MockElasticsearchService();
    }

    protected function tearDown(): void
    {
        $this->es->reset();
        parent::tearDown();
    }

    public function testCreateIndex(): void
    {
        $result = $this->es->createIndex('test_index', [
            'properties' => [
                'name' => ['type' => 'text'],
                'date' => ['type' => 'date'],
            ],
        ]);

        $this->assertTrue($result);
        $this->assertTrue($this->es->indexExists('test_index'));
    }

    public function testDeleteIndex(): void
    {
        $this->es->createIndex('index_to_delete');
        $this->assertTrue($this->es->indexExists('index_to_delete'));

        $result = $this->es->deleteIndex('index_to_delete');

        $this->assertTrue($result);
        $this->assertFalse($this->es->indexExists('index_to_delete'));
    }

    public function testDeleteNonExistentIndex(): void
    {
        $result = $this->es->deleteIndex('non_existent');

        $this->assertFalse($result);
    }

    public function testIndexDocument(): void
    {
        $this->es->createIndex('matches');
        $document = [
            'home_team' => 'PSG',
            'away_team' => 'OM',
            'league' => 'Ligue 1',
            'date' => '2024-01-15',
        ];

        $result = $this->es->index('matches', 'match_1', $document);

        $this->assertTrue($result);
        $this->assertEquals($document, $this->es->get('matches', 'match_1'));
    }

    public function testBulkIndex(): void
    {
        $this->es->createIndex('matches');
        $documents = [
            ['id' => 'match_1', 'document' => ['home' => 'PSG', 'away' => 'OM']],
            ['id' => 'match_2', 'document' => ['home' => 'Lyon', 'away' => 'Monaco']],
            ['id' => 'match_3', 'document' => ['home' => 'Lille', 'away' => 'Nice']],
        ];

        $result = $this->es->bulkIndex('matches', $documents);

        $this->assertFalse($result['errors']);
        $this->assertCount(3, $result['items']);
        $this->assertEquals(3, $this->es->count('matches'));
    }

    public function testGetDocument(): void
    {
        $this->es->createIndex('teams');
        $team = ['name' => 'Paris Saint-Germain', 'country' => 'France'];
        $this->es->index('teams', 'team_1', $team);

        $result = $this->es->get('teams', 'team_1');

        $this->assertEquals($team, $result);
    }

    public function testGetNonExistentDocument(): void
    {
        $this->es->createIndex('teams');

        $result = $this->es->get('teams', 'non_existent');

        $this->assertNull($result);
    }

    public function testDeleteDocument(): void
    {
        $this->es->createIndex('matches');
        $this->es->index('matches', 'match_1', ['home' => 'PSG']);

        $result = $this->es->delete('matches', 'match_1');

        $this->assertTrue($result);
        $this->assertNull($this->es->get('matches', 'match_1'));
    }

    public function testSearchWithMatchAll(): void
    {
        $this->es->createIndex('matches');
        $this->es->index('matches', '1', ['home' => 'PSG', 'league' => 'Ligue 1']);
        $this->es->index('matches', '2', ['home' => 'Lyon', 'league' => 'Ligue 1']);
        $this->es->index('matches', '3', ['home' => 'Barcelona', 'league' => 'La Liga']);

        $result = $this->es->search('matches', ['match_all' => new \stdClass()]);

        $this->assertEquals(3, $result['hits']['total']['value']);
        $this->assertCount(3, $result['hits']['hits']);
    }

    public function testSearchWithMatch(): void
    {
        $this->es->createIndex('matches');
        $this->es->index('matches', '1', ['home' => 'Paris Saint-Germain', 'league' => 'Ligue 1']);
        $this->es->index('matches', '2', ['home' => 'Lyon', 'league' => 'Ligue 1']);
        $this->es->index('matches', '3', ['home' => 'Barcelona', 'league' => 'La Liga']);

        $result = $this->es->search('matches', [
            'match' => ['home' => 'Paris'],
        ]);

        $this->assertEquals(1, $result['hits']['total']['value']);
        $this->assertEquals('Paris Saint-Germain', $result['hits']['hits'][0]['_source']['home']);
    }

    public function testSearchWithTerm(): void
    {
        $this->es->createIndex('matches');
        $this->es->index('matches', '1', ['home' => 'PSG', 'league' => 'Ligue 1']);
        $this->es->index('matches', '2', ['home' => 'Lyon', 'league' => 'Ligue 1']);
        $this->es->index('matches', '3', ['home' => 'Barcelona', 'league' => 'La Liga']);

        $result = $this->es->search('matches', [
            'term' => ['league' => 'La Liga'],
        ]);

        $this->assertEquals(1, $result['hits']['total']['value']);
        $this->assertEquals('Barcelona', $result['hits']['hits'][0]['_source']['home']);
    }

    public function testSearchText(): void
    {
        $this->es->createIndex('matches');
        $this->es->index('matches', '1', [
            'home' => 'Paris Saint-Germain',
            'away' => 'Olympique de Marseille',
        ]);
        $this->es->index('matches', '2', [
            'home' => 'Lyon',
            'away' => 'Monaco',
        ]);

        $result = $this->es->searchText('matches', 'Paris', ['home', 'away']);

        $this->assertEquals(1, $result['hits']['total']['value']);
    }

    public function testUpdateDocument(): void
    {
        $this->es->createIndex('teams');
        $this->es->index('teams', 'team_1', [
            'name' => 'PSG',
            'ranking' => 1,
            'points' => 50,
        ]);

        $result = $this->es->update('teams', 'team_1', [
            'points' => 53,
            'wins' => 17,
        ]);

        $this->assertTrue($result);
        $updated = $this->es->get('teams', 'team_1');
        $this->assertEquals(53, $updated['points']);
        $this->assertEquals(17, $updated['wins']);
        $this->assertEquals('PSG', $updated['name']); // Original field preserved
    }

    public function testCount(): void
    {
        $this->es->createIndex('matches');
        $this->es->index('matches', '1', ['league' => 'Ligue 1']);
        $this->es->index('matches', '2', ['league' => 'Ligue 1']);
        $this->es->index('matches', '3', ['league' => 'La Liga']);

        $total = $this->es->count('matches');
        $ligue1Count = $this->es->count('matches', [
            'term' => ['league' => 'Ligue 1'],
        ]);

        $this->assertEquals(3, $total);
        $this->assertEquals(2, $ligue1Count);
    }

    public function testGetIndexStats(): void
    {
        $this->es->createIndex('matches');
        $this->es->index('matches', '1', ['home' => 'PSG']);
        $this->es->index('matches', '2', ['home' => 'Lyon']);

        $stats = $this->es->getIndexStats('matches');

        $this->assertEquals(2, $stats['docs_count']);
    }

    public function testRefresh(): void
    {
        $this->es->createIndex('matches');

        $result = $this->es->refresh('matches');

        $this->assertTrue($result);
    }

    public function testIsAvailable(): void
    {
        $this->assertTrue($this->es->isAvailable());

        $this->es->setAvailable(false);

        $this->assertFalse($this->es->isAvailable());
    }

    public function testOperationsWhenUnavailable(): void
    {
        $this->es->setAvailable(false);

        $this->assertFalse($this->es->createIndex('test'));
        $this->assertFalse($this->es->index('test', '1', []));
        $this->assertNull($this->es->get('test', '1'));
        $this->assertEquals(0, $this->es->count('test'));
    }

    public function testSearchWithBoolQuery(): void
    {
        $this->es->createIndex('matches');
        $this->es->index('matches', '1', ['home' => 'PSG', 'league' => 'Ligue 1', 'status' => 'finished']);
        $this->es->index('matches', '2', ['home' => 'Lyon', 'league' => 'Ligue 1', 'status' => 'scheduled']);
        $this->es->index('matches', '3', ['home' => 'Barcelona', 'league' => 'La Liga', 'status' => 'finished']);

        $result = $this->es->search('matches', [
            'bool' => [
                'must' => [
                    ['term' => ['status' => 'finished']],
                ],
            ],
        ]);

        $this->assertEquals(2, $result['hits']['total']['value']);
    }

    public function testSearchWithPagination(): void
    {
        $this->es->createIndex('matches');
        for ($i = 1; $i <= 20; $i++) {
            $this->es->index('matches', (string) $i, ['number' => $i]);
        }

        $page1 = $this->es->search('matches', [], 0, 5);
        $page2 = $this->es->search('matches', [], 5, 5);

        $this->assertCount(5, $page1['hits']['hits']);
        $this->assertCount(5, $page2['hits']['hits']);
    }
}