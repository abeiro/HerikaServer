<?php

/**
 * Normalize a stored actor reference ID for a BackgroundCmd command.
 */
function chimNormalizeBackgroundTrackRefId($refId): ?string
{
    $refId = trim((string) $refId);
    if (!preg_match('/^(?:0x)?([0-9a-f]{1,8})$/i', $refId, $matches)) {
        return null;
    }

    return '0x' . strtoupper(str_pad($matches[1], 8, '0', STR_PAD_LEFT));
}

/**
 * Check the exact pipe-delimited presence list before requesting coordinates.
 *
 * Nearby NPC coordinates are already refreshed by normal game presence data,
 * so sending an additional BackgroundCmd Track request only creates redundant
 * server and plugin work.
 */
function chimIsBackgroundNpcInRange(string $npcName): bool
{
    $npcName = trim($npcName);
    if ($npcName === '' || !function_exists('DataBeingsInRange')) {
        return false;
    }

    return str_contains((string) DataBeingsInRange(), '|' . $npcName . '|');
}

/**
 * Atomically claim and queue a coordinate update for one Background Life NPC.
 *
 * The pending flag and response command are written in one statement. Competing
 * workers therefore cannot enqueue duplicate Track commands for the same NPC.
 */
function chimQueueBackgroundTrack(array $npcData, $db, string $tag = ''): bool
{
    $npcId = (int) ($npcData['id'] ?? 0);
    $refId = chimNormalizeBackgroundTrackRefId($npcData['refid'] ?? '');
    if ($npcId <= 0 || $refId === null) {
        return false;
    }

    $action = $db->escape("rolecommand|BackgroundCmd@{$refId}@Track/");
    $tag = $db->escape($tag);
    $localTs = time();

    $queued = $db->fetchOne(
        "WITH claimed AS (
            UPDATE core_npc_master
            SET metadata = jsonb_set(
                COALESCE(metadata, '{}'::jsonb),
                '{last_coords}',
                COALESCE(metadata->'last_coords', '{}'::jsonb) || '{\"pending\":true}'::jsonb,
                true
            )
            WHERE id={$npcId}
              AND COALESCE(metadata->'last_coords'->>'pending', 'false') <> 'true'
            RETURNING id
        )
        INSERT INTO responselog (localts, sent, actor, text, action, tag)
        SELECT {$localTs}, 0, 'rolemaster', '', '{$action}', '{$tag}'
        FROM claimed
        RETURNING rowid"
    );

    return is_array($queued) && !empty($queued['rowid']);
}
