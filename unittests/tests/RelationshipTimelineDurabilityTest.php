<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

$GLOBALS['ENGINE_PATH'] = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
require_once $GLOBALS['ENGINE_PATH'] . 'lib' . DIRECTORY_SEPARATOR . 'logger.php';
require_once $GLOBALS['ENGINE_PATH'] . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'npc_master.class.php';

final class RelationshipTimelineDurabilityTest extends TestCase
{
    protected function setUp(): void
    {
        Logger::setCustomLog(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'chim-relationship-timeline-test.log');
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['db'], $GLOBALS['gameRequest']);
        Logger::unsetCustomLog();
    }

    public function testTimelineStateIgnoresVolatileEvaluationTimestamps(): void
    {
        $first = chimRelationshipTimelineState([
            'relationships' => ['Player' => ['aff' => 99, 'type' => 'platonic']],
            'relationships_analyzed' => 'yes',
            'relationships_model' => 'model-a',
            'relationships_last_eval' => '2026-07-27 10:00:00',
            'relationships_updated' => '2026-07-27 10:00:00',
            '_chim_history_source' => 'infosave',
        ]);
        $second = chimRelationshipTimelineState([
            'relationships' => ['Player' => ['type' => 'platonic', 'aff' => 99]],
            'relationships_analyzed' => 'yes',
            'relationships_model' => 'model-a',
            'relationships_last_eval' => '2026-07-27 10:05:00',
            'relationships_updated' => '2026-07-27 10:05:00',
            '_chim_history_source' => 'relationship',
        ]);

        $this->assertEquals($first, $second);
    }

    public function testChangedRelationshipSnapshotsImmediatelyAndIdenticalStateIsDeduplicated(): void
    {
        $GLOBALS['gameRequest'] = ['init', 'Player', 100.0];
        $GLOBALS['db'] = new RelationshipTimelineFakeDb(
            $this->npcRow(99, 100.0),
            [$this->historyRow(0, 100.0, 'infosave', 1)]
        );

        $this->assertTrue(chimRelationshipTimelineStamp(42));
        $this->assertSame(1, $GLOBALS['db']->insertCount);
        $this->assertSame('relationship', $GLOBALS['db']->latestSource(100.0));
        $this->assertSame(99, $GLOBALS['db']->latestAffinity(100.0));

        $this->assertTrue(chimRelationshipTimelineStamp(42));
        $this->assertSame(1, $GLOBALS['db']->insertCount);

        $live = json_decode($GLOBALS['db']->live['extended_data'], true, 512, JSON_THROW_ON_ERROR);
        $live['relationships_last_eval'] = '2026-07-27 10:05:00';
        $GLOBALS['db']->live['extended_data'] = json_encode($live, JSON_THROW_ON_ERROR);

        $this->assertTrue(chimRelationshipTimelineStamp(42));
        $this->assertSame(1, $GLOBALS['db']->insertCount);
    }

    public function testOlderTimelineIgnoresFutureHistoryAndCreatesEligibleSnapshot(): void
    {
        $GLOBALS['gameRequest'] = ['init', 'Player', 50.0];
        $GLOBALS['db'] = new RelationshipTimelineFakeDb(
            $this->npcRow(5, 50.0),
            [
                $this->historyRow(0, 50.0, 'infosave', 1),
                $this->historyRow(99, 100.0, 'relationship', 2),
            ]
        );

        $this->assertTrue(chimRelationshipTimelineStamp(42));
        $this->assertSame(1, $GLOBALS['db']->insertCount);
        $this->assertSame(5, $GLOBALS['db']->latestAffinity(50.0));
        $this->assertSame(99, $GLOBALS['db']->latestAffinity(100.0));
    }

    public function testInvalidLiveRelationshipJsonFailsClosedWithoutSnapshot(): void
    {
        $GLOBALS['gameRequest'] = ['init', 'Player', 100.0];
        $live = $this->npcRow(99, 100.0);
        $live['extended_data'] = '{invalid json';
        $GLOBALS['db'] = new RelationshipTimelineFakeDb($live, []);

        $this->assertFalse(chimRelationshipTimelineStamp(42));
        $this->assertSame(0, $GLOBALS['db']->insertCount);
    }

    public function testRestoreMergesRelationshipTimelineForLockedAndUnlockedProfiles(): void
    {
        $GLOBALS['db'] = new RelationshipRestoreRecordingDb();
        $npcMaster = new NpcMaster();

        $this->assertTrue($npcMaster->restoreNPC(100.0));

        $query = $GLOBALS['db']->relationshipRestoreQuery;
        $this->assertStringContainsString('UPDATE public.core_npc_master c', $query);
        $this->assertStringNotContainsString('COALESCE(c.lock_profile, 0) = 1', $query);
        $this->assertStringContainsString("= 'relationship' THEN 2", $query);
        $this->assertMatchesRegularExpression('/h\\.created DESC,\\s+CASE/s', $query);
        $this->assertStringContainsString('COUNT(*)::int AS affected', $query);
        $this->assertStringContainsString('RETURNING npc_name', $GLOBALS['db']->futureClearQuery);
    }

    public function testRestoreSkipsFutureClearWhenRelationshipMergeFails(): void
    {
        $GLOBALS['db'] = new RelationshipRestoreRecordingDb(true);
        $npcMaster = new NpcMaster();

        $this->assertTrue($npcMaster->restoreNPC(100.0));
        $this->assertSame('', $GLOBALS['db']->futureClearQuery);
    }

    public function testPostgresRestoreAndFutureClearQueries(): void
    {
        if (getenv('CHIM_RELATIONSHIP_DB_INTEGRATION') !== '1') {
            $this->markTestSkipped('Set CHIM_RELATIONSHIP_DB_INTEGRATION=1 to run the PostgreSQL regression.');
        }

        $connection = pg_connect('host=localhost dbname=dwemer user=dwemer password=dwemer');
        $this->assertNotFalse($connection);
        $schema = 'chim_rel_timeline_test_' . getmypid();

        pg_query($connection, 'BEGIN');
        try {
            $this->assertNotFalse(pg_query($connection, "CREATE SCHEMA {$schema}"));
            $this->assertNotFalse(pg_query($connection, "CREATE TABLE {$schema}.core_npc_master (
                id integer PRIMARY KEY,
                npc_name text NOT NULL,
                lock_profile integer DEFAULT 0,
                extended_data jsonb,
                gamets_last_updated double precision
            )"));
            $this->assertNotFalse(pg_query($connection, "CREATE TABLE {$schema}.core_npc_master_history (
                npc_id integer NOT NULL,
                extended_data jsonb,
                gamets_last_updated double precision,
                created timestamp without time zone NOT NULL
            )"));

            $current = $this->relationshipJson(0, 'current');
            $future = $this->relationshipJson(50, 'future');
            $this->assertNotFalse(pg_query_params(
                $connection,
                "INSERT INTO {$schema}.core_npc_master
                    (id, npc_name, lock_profile, extended_data, gamets_last_updated)
                 VALUES
                    (1, 'Unlocked', 0, $1::jsonb, 100),
                    (2, 'Locked', 1, $1::jsonb, 100),
                    (3, 'Future', 0, $2::jsonb, 101),
                    (4, 'Never stamped', 0, $2::jsonb, NULL)",
                [$current, $future]
            ));

            foreach ([[1, 99], [2, 88]] as [$npcId, $affinity]) {
                $infosave = $this->relationshipJson(0, 'infosave', 'infosave');
                $relationship = $this->relationshipJson($affinity, 'relationship', 'relationship');
                $this->assertNotFalse(pg_query_params(
                    $connection,
                    "INSERT INTO {$schema}.core_npc_master_history
                        (npc_id, extended_data, gamets_last_updated, created)
                     VALUES
                        ($1, $2::jsonb, 100, '2026-07-27 10:00:00'),
                        ($1, $3::jsonb, 100, '2026-07-27 10:00:00')",
                    [$npcId, $infosave, $relationship]
                ));
            }

            $restoreResult = pg_query($connection, chimRelationshipRestoreQuery(100, $schema));
            $this->assertNotFalse($restoreResult);
            $this->assertSame('2', pg_fetch_assoc($restoreResult)['affected']);

            $restoredResult = pg_query(
                $connection,
                "SELECT id,
                        extended_data ->> 'profile_marker' AS profile_marker,
                        extended_data #>> '{relationships,Player,aff}' AS affinity
                 FROM {$schema}.core_npc_master
                 WHERE id IN (1, 2)
                 ORDER BY id"
            );
            $restored = pg_fetch_all($restoredResult);
            $this->assertSame('current', $restored[0]['profile_marker']);
            $this->assertSame('99', $restored[0]['affinity']);
            $this->assertSame('current', $restored[1]['profile_marker']);
            $this->assertSame('88', $restored[1]['affinity']);

            $clearResult = pg_query($connection, chimRelationshipFutureClearQuery(100, $schema));
            $this->assertNotFalse($clearResult);
            $clearSummary = pg_fetch_assoc($clearResult);
            $this->assertSame('2', $clearSummary['affected']);
            $this->assertStringContainsString('Future', $clearSummary['sample_names']);
            $this->assertStringContainsString('Never stamped', $clearSummary['sample_names']);

            $remainingFuture = pg_query(
                $connection,
                "SELECT COUNT(*) AS remaining
                 FROM {$schema}.core_npc_master
                 WHERE id IN (3, 4) AND extended_data ? 'relationships'"
            );
            $this->assertSame('0', pg_fetch_assoc($remainingFuture)['remaining']);
        } finally {
            pg_query($connection, 'ROLLBACK');
            pg_close($connection);
        }
    }

    private function npcRow(int $affinity, float $gameTimestamp): array
    {
        return [
            'id' => 42,
            'npc_name' => 'Sa\'chil',
            'lock_profile' => 0,
            'extended_data' => json_encode([
                'relationships' => [
                    'Player' => ['aff' => $affinity, 'type' => 'platonic'],
                ],
                'relationships_analyzed' => '2026-07-27 10:00:00',
                'relationships_model' => 'test-model',
                'relationships_last_eval' => '2026-07-27 10:00:00',
            ], JSON_THROW_ON_ERROR),
            'gamets_last_updated' => $gameTimestamp,
        ];
    }

    private function historyRow(
        int $affinity,
        float $gameTimestamp,
        string $source,
        int $createdSequence
    ): array {
        $row = $this->npcRow($affinity, $gameTimestamp);
        $extended = json_decode($row['extended_data'], true, 512, JSON_THROW_ON_ERROR);
        $extended['_chim_history_source'] = $source;
        return [
            'npc_id' => 42,
            'extended_data' => json_encode($extended, JSON_THROW_ON_ERROR),
            'gamets_last_updated' => $gameTimestamp,
            'created_sequence' => $createdSequence,
        ];
    }

    private function relationshipJson(
        int $affinity,
        string $profileMarker,
        ?string $historySource = null
    ): string {
        $extended = [
            'profile_marker' => $profileMarker,
            'relationships' => [
                'Player' => ['aff' => $affinity, 'type' => 'platonic'],
            ],
            'relationships_analyzed' => '2026-07-27 10:00:00',
            'relationships_model' => 'test-model',
        ];
        if ($historySource !== null) {
            $extended['_chim_history_source'] = $historySource;
        }
        return json_encode($extended, JSON_THROW_ON_ERROR);
    }
}

final class RelationshipTimelineFakeDb
{
    public int $insertCount = 0;
    private int $createdSequence;

    public function __construct(
        public array $live,
        public array $history
    ) {
        $this->createdSequence = empty($history)
            ? 0
            : max(array_column($history, 'created_sequence'));
    }

    public function execQuery(string $query): bool
    {
        if (preg_match('/SET gamets_last_updated = ([0-9.]+)/', $query, $matches)) {
            $this->live['gamets_last_updated'] = (float) $matches[1];
        }
        return true;
    }

    public function fetchOne(string $query): ?array
    {
        if (str_contains($query, 'SELECT * FROM core_npc_master')) {
            return $this->live;
        }
        if (str_contains($query, 'FROM core_npc_master_history')) {
            $eligibleTimestamp = null;
            if (preg_match('/gamets_last_updated <= ([0-9.]+)/', $query, $matches)) {
                $eligibleTimestamp = (float) $matches[1];
            }
            return $this->latestEligible($eligibleTimestamp);
        }
        if (str_contains($query, 'FROM core_npc_master WHERE id')) {
            return [
                'extended_data' => $this->live['extended_data'],
                'gamets_last_updated' => $this->live['gamets_last_updated'],
            ];
        }
        return null;
    }

    public function insert(string $table, array $data): void
    {
        if ($table !== 'core_npc_master_history') {
            return;
        }
        $data['created_sequence'] = ++$this->createdSequence;
        $this->history[] = $data;
        $this->insertCount++;
    }

    public function latestAffinity(float $timestamp): int
    {
        $row = $this->latestEligible($timestamp);
        $extended = json_decode($row['extended_data'], true, 512, JSON_THROW_ON_ERROR);
        return (int) $extended['relationships']['Player']['aff'];
    }

    public function latestSource(float $timestamp): string
    {
        $row = $this->latestEligible($timestamp);
        $extended = json_decode($row['extended_data'], true, 512, JSON_THROW_ON_ERROR);
        return (string) $extended['_chim_history_source'];
    }

    private function latestEligible(?float $timestamp): ?array
    {
        $eligible = array_values(array_filter(
            $this->history,
            static fn(array $row): bool => $timestamp === null
                || $row['gamets_last_updated'] === null
                || (float) $row['gamets_last_updated'] <= $timestamp
        ));
        usort($eligible, static function (array $left, array $right): int {
            $gameCompare = ((float) ($right['gamets_last_updated'] ?? -INF))
                <=> ((float) ($left['gamets_last_updated'] ?? -INF));
            if ($gameCompare !== 0) {
                return $gameCompare;
            }
            $createdCompare = ((int) $right['created_sequence']) <=> ((int) $left['created_sequence']);
            if ($createdCompare !== 0) {
                return $createdCompare;
            }
            return self::sourcePriority($right) <=> self::sourcePriority($left);
        });
        return $eligible[0] ?? null;
    }

    private static function sourcePriority(array $row): int
    {
        $extended = json_decode($row['extended_data'], true);
        return match ($extended['_chim_history_source'] ?? '') {
            'relationship' => 2,
            'infosave' => 1,
            default => 0,
        };
    }
}

final class RelationshipRestoreRecordingDb
{
    public string $relationshipRestoreQuery = '';
    public string $futureClearQuery = '';

    public function __construct(private bool $failRelationshipRestore = false)
    {
    }

    public function query(string $query): bool
    {
        return true;
    }

    public function execQuery(string $query): bool
    {
        return true;
    }

    public function fetchOne(string $query): array
    {
        if (str_contains($query, 'WITH restore AS')) {
            $this->relationshipRestoreQuery = $query;
            return $this->failRelationshipRestore ? [] : ['affected' => 2];
        }
        if (str_contains($query, 'WITH cleared AS')) {
            $this->futureClearQuery = $query;
            return ['affected' => 0, 'sample_names' => ''];
        }
        return [];
    }
}
