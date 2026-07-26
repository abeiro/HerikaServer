<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/background_life_tracking.php';
require_once __DIR__ . '/../../lib/middleterm_worker_lock.php';

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
