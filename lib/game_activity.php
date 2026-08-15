<?php

const CHIM_GAME_ACTIVITY_TTL = 180;
const CHIM_GAME_ACTIVITY_WRITE_INTERVAL = 15;

function chimGameActivityGetOption(string $id, string $default = ''): string
{
    $db = $GLOBALS['db'] ?? null;
    if (!$db) {
        return $default;
    }

    $escapedId = $db->escape($id);
    $row = $db->fetchOne("SELECT value FROM conf_opts WHERE id='{$escapedId}' LIMIT 1");
    return is_array($row) && array_key_exists('value', $row) ? strval($row['value']) : $default;
}

function chimGameActivitySetOption(string $id, string $value): bool
{
    $db = $GLOBALS['db'] ?? null;
    if (!$db) {
        return false;
    }

    return (bool)$db->upsertRowOnConflict('conf_opts', [
        'id' => $id,
        'value' => $value,
    ], 'id');
}

function chimGameActivityReadTimestamp(string $id, string $legacyId): int
{
    $timestamp = intval(chimGameActivityGetOption($id, '0'));
    if ($timestamp <= 0) {
        $timestamp = intval(chimGameActivityGetOption($legacyId, '0'));
    }

    return $timestamp;
}

function chimGameActivityWriteTimestamp(string $id, string $legacyId, int $timestamp): void
{
    $value = strval($timestamp);
    chimGameActivitySetOption($id, $value);
    chimGameActivitySetOption($legacyId, $value);
}

function chimGetLastGameActivityTimestamp(): int
{
    return chimGameActivityReadTimestamp('CHIM_GAME_LAST_ACTIVITY_TS', 'PLAYER2_GAME_LAST_ACTIVITY_TS');
}

function chimGetGameActivitySessionStartedTimestamp(): int
{
    return chimGameActivityReadTimestamp('CHIM_GAME_SESSION_STARTED_TS', 'PLAYER2_GAME_SESSION_STARTED_TS');
}

/**
 * Mark normal Skyrim traffic and report whether it starts a new activity session.
 */
function chimMarkGameActivity(?int $now = null): bool
{
    $now = $now ?? time();
    $lastActivity = chimGetLastGameActivityTimestamp();
    $newSession = $lastActivity <= 0 || ($now - $lastActivity) > CHIM_GAME_ACTIVITY_TTL;

    if ($newSession) {
        chimGameActivityWriteTimestamp('CHIM_GAME_SESSION_STARTED_TS', 'PLAYER2_GAME_SESSION_STARTED_TS', $now);
    }
    if ($newSession || ($now - $lastActivity) >= CHIM_GAME_ACTIVITY_WRITE_INTERVAL) {
        chimGameActivityWriteTimestamp('CHIM_GAME_LAST_ACTIVITY_TS', 'PLAYER2_GAME_LAST_ACTIVITY_TS', $now);
    }

    $GLOBALS['CHIM_GAME_REQUEST_ACTIVE'] = true;
    $GLOBALS['PLAYER2_GAME_REQUEST_ACTIVE'] = true;

    return $newSession;
}

function chimHasRecentGameActivity(?int $now = null, int $ttl = CHIM_GAME_ACTIVITY_TTL): bool
{
    $now = $now ?? time();
    $ttl = max(1, $ttl);
    $lastActivity = chimGetLastGameActivityTimestamp();

    return $lastActivity > 0 && ($now - $lastActivity) <= $ttl;
}

function chimIsGameRequestActive(): bool
{
    return !empty($GLOBALS['CHIM_GAME_REQUEST_ACTIVE']) || !empty($GLOBALS['PLAYER2_GAME_REQUEST_ACTIVE']);
}
