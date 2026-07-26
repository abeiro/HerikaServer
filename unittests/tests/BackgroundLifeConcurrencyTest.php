<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/background_life_tracking.php';
require_once __DIR__ . '/../../lib/middleterm_worker_lock.php';

$GLOBALS['BACKGROUND_LIFE_TEST_BEINGS_IN_RANGE'] = '';
if (!function_exists('DataBeingsInRange')) {
    function DataBeingsInRange(): string
    {
        return (string) ($GLOBALS['BACKGROUND_LIFE_TEST_BEINGS_IN_RANGE'] ?? '');
    }
}

final class BackgroundLifeConcurrencyDb
{
    public array $queries = [];
    public $nextResult = null;

    public function escape($value): string
    {
        return str_replace("'", "''", (string) $value);
    }

    public function fetchOne(string $query)
    {
        $this->queries[] = $query;
        return $this->nextResult;
    }
}

final class BackgroundLifeConcurrencyTest extends TestCase
{
    public function testNewerBackgroundEventLocationOverridesStaleCoordinates(): void
    {
        self::assertSame(
            'Riverwood',
            chimSelectCurrentBackgroundLocation(
                'Sleeping Giant Inn (Interior)',
                77000000,
                'Riverwood',
                77088888
            )
        );
    }

    public function testNewerCoordinatesRemainAuthoritative(): void
    {
        self::assertSame(
            'Sleeping Giant Inn (Interior)',
            chimSelectCurrentBackgroundLocation(
                'Sleeping Giant Inn (Interior)',
                77100000,
                'Riverwood',
                77088888
            )
        );
    }

    public function testLocationSelectionFallsBackWhenEitherSourceIsMissing(): void
    {
        self::assertSame(
            'Riverwood',
            chimSelectCurrentBackgroundLocation('', 0, 'Riverwood', 77088888)
        );
        self::assertSame(
            'Sleeping Giant Inn',
            chimSelectCurrentBackgroundLocation('Sleeping Giant Inn', 77000000, '', 0)
        );
    }

    public function testTrackClaimAndQueueUseOneAtomicStatement(): void
    {
        $db = new BackgroundLifeConcurrencyDb();
        $db->nextResult = ['rowid' => 1234];

        self::assertTrue(chimQueueBackgroundTrack([
            'id' => 2190,
            'refid' => '1347A',
        ], $db, 'scheduled'));

        self::assertCount(1, $db->queries);
        $query = preg_replace('/\s+/', ' ', $db->queries[0]);
        self::assertStringContainsString('WITH claimed AS ( UPDATE core_npc_master', $query);
        self::assertStringContainsString("\"pending\":true", $query);
        self::assertStringContainsString("metadata->'last_coords'->>'pending'", $query);
        self::assertStringContainsString('INSERT INTO responselog', $query);
        self::assertStringContainsString('BackgroundCmd@0x0001347A@Track/', $query);
        self::assertStringContainsString("'scheduled'", $query);
    }

    public function testTrackQueueReturnsFalseWhenAnotherWorkerAlreadyClaimedNpc(): void
    {
        $db = new BackgroundLifeConcurrencyDb();
        $db->nextResult = [];

        self::assertFalse(chimQueueBackgroundTrack([
            'id' => 2190,
            'refid' => '0001347A',
        ], $db));
    }

    public function testTrackQueueRejectsInvalidNpcIdentity(): void
    {
        $db = new BackgroundLifeConcurrencyDb();

        self::assertFalse(chimQueueBackgroundTrack(['id' => 0, 'refid' => '1347A'], $db));
        self::assertFalse(chimQueueBackgroundTrack(['id' => 1, 'refid' => 'not-a-ref'], $db));
        self::assertSame([], $db->queries);
    }

    public function testNearbyCheckMatchesWholeActorNamesOnly(): void
    {
        $GLOBALS['BACKGROUND_LIFE_TEST_BEINGS_IN_RANGE'] = '|RANGROO|Sven|Sigrid|';

        self::assertTrue(chimIsBackgroundNpcInRange('Sven'));
        self::assertTrue(chimIsBackgroundNpcInRange('Sigrid'));
        self::assertFalse(chimIsBackgroundNpcInRange('Sven the Bard'));
        self::assertFalse(chimIsBackgroundNpcInRange(''));
    }

    public function testSchedulerSkipsNearbyActorsBeforeLaunchingTrackCommands(): void
    {
        $source = file_get_contents(__DIR__ . '/../../service/processors/middleterm/entrypoint.php');

        self::assertIsString($source);
        self::assertGreaterThanOrEqual(
            2,
            substr_count($source, 'if (chimIsBackgroundNpcInRange((string) $npc["npc_name"]))')
        );
    }

    public function testMiddletermLockUsesDedicatedAdvisoryLock(): void
    {
        $db = new BackgroundLifeConcurrencyDb();
        $db->nextResult = ['acquired' => 't'];

        self::assertTrue(chimTryAcquireMiddletermWorkerLock($db));
        self::assertStringContainsString('pg_try_advisory_lock', $db->queries[0]);
        self::assertStringContainsString(CHIM_MIDDLETERM_WORKER_LOCK, $db->queries[0]);

        $db->nextResult = ['released' => 't'];
        chimReleaseMiddletermWorkerLock($db);
        self::assertStringContainsString('pg_advisory_unlock', $db->queries[1]);
    }

    public function testMiddletermLockFailsClosedWhenAlreadyOwned(): void
    {
        $db = new BackgroundLifeConcurrencyDb();
        $db->nextResult = ['acquired' => 'f'];

        self::assertFalse(chimTryAcquireMiddletermWorkerLock($db));
    }
}
