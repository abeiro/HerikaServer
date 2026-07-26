<?php

const CHIM_MIDDLETERM_WORKER_LOCK = 'chim_middleterm_worker';

function chimPostgresLockValue($value): bool
{
    return $value === true
        || $value === 1
        || in_array(strtolower((string) $value), ['1', 't', 'true'], true);
}

function chimTryAcquireMiddletermWorkerLock($db): bool
{
    $row = $db->fetchOne(
        "SELECT pg_try_advisory_lock(hashtext('" . CHIM_MIDDLETERM_WORKER_LOCK . "')) AS acquired"
    );

    return chimPostgresLockValue($row['acquired'] ?? false);
}

function chimReleaseMiddletermWorkerLock($db): void
{
    $db->fetchOne(
        "SELECT pg_advisory_unlock(hashtext('" . CHIM_MIDDLETERM_WORKER_LOCK . "')) AS released"
    );
}
