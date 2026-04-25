<?php

function chimGetRumorHoldOptions() {
    return [
        'Skyrim',
        'Whiterun',
        'Eastmarch',
        'Haafingar',
        'The Reach',
        'Hjaalmarch',
        'The Pale',
        'Falkreath',
        'The Rift',
        'Winterhold',
        'Solstheim',
    ];
}

function chimNormalizeRumorLengthDays($lengthDaysInput) {
    $lengthDaysInput = trim((string) $lengthDaysInput);
    if ($lengthDaysInput === '') {
        return 7;
    }

    if (!preg_match('/^\d+$/', $lengthDaysInput)) {
        return null;
    }

    $lengthDays = (int) $lengthDaysInput;
    if ($lengthDays < 1) {
        return null;
    }

    return $lengthDays;
}

function chimPrepareRumorEntry($hold, $type, $content, $lengthDaysInput = null) {
    $hold = trim((string) $hold);
    $type = trim((string) $type);
    $content = trim((string) $content);
    $lengthDays = chimNormalizeRumorLengthDays($lengthDaysInput);

    if (!in_array($hold, chimGetRumorHoldOptions(), true)) {
        return [
            'ok' => false,
            'error_type' => 'validation',
            'message' => 'Please select a valid hold.',
        ];
    }

    if ($content === '') {
        return [
            'ok' => false,
            'error_type' => 'validation',
            'message' => 'Rumor content is required.',
        ];
    }

    if ($lengthDays === null) {
        return [
            'ok' => false,
            'error_type' => 'validation',
            'message' => 'Rumor length must be a whole number of days.',
        ];
    }

    if ($type === '') {
        $type = 'General';
    }

    return [
        'ok' => true,
        'hold' => $hold,
        'type' => $type,
        'content' => $content,
        'rumor_length_days' => $lengthDays,
    ];
}

function chimCreateRumorEntry($pgConn, $hold, $type, $content, $lengthDaysInput = null) {
    $prepared = chimPrepareRumorEntry($hold, $type, $content, $lengthDaysInput);
    if (!($prepared['ok'] ?? false)) {
        return $prepared;
    }

    $lastGametsResult = pg_query($pgConn, "SELECT max(gamets) as last_gamets FROM eventlog");
    if (!$lastGametsResult || pg_num_rows($lastGametsResult) === 0) {
        error_log('[RUMORS] Failed to resolve latest game timestamp for rumor creation');
        return [
            'ok' => false,
            'error_type' => 'server',
            'message' => 'Could not determine the current game time.',
        ];
    }

    $hold = $prepared['hold'];
    $type = $prepared['type'];
    $content = $prepared['content'];
    $lengthDays = $prepared['rumor_length_days'];

    $lastGametsRow = pg_fetch_assoc($lastGametsResult);
    $gamets = isset($lastGametsRow['last_gamets']) ? (int) $lastGametsRow['last_gamets'] : 0;
    if ($gamets <= 0) {
        return [
            'ok' => false,
            'error_type' => 'server',
            'message' => 'Could not determine the current game time.',
        ];
    }

    $ts = (int) round(microtime(true) * 1000);
    $insertQuery = "INSERT INTO rumors (gamets, ts, hold, content, type, rumor_length_days) VALUES ($1, $2, $3, $4, $5, $6)";
    $insertResult = pg_query_params($pgConn, $insertQuery, [$gamets, $ts, $hold, $content, $type, $lengthDays]);

    if (!$insertResult) {
        error_log('[RUMORS] Failed to create rumor: ' . pg_last_error($pgConn));
        return [
            'ok' => false,
            'error_type' => 'server',
            'message' => 'Failed to create rumor.',
        ];
    }

    return [
        'ok' => true,
        'message' => 'Rumor created successfully.',
        'gamets' => $gamets,
        'ts' => $ts,
        'hold' => $hold,
        'type' => $type,
        'content' => $content,
        'rumor_length_days' => $lengthDays,
    ];
}

function chimUpdateRumorEntry($pgConn, $rumorId, $hold, $type, $content, $lengthDaysInput = null) {
    $rumorId = (int) $rumorId;
    if ($rumorId <= 0) {
        return [
            'ok' => false,
            'error_type' => 'validation',
            'message' => 'Invalid rumor selected for editing.',
        ];
    }

    $prepared = chimPrepareRumorEntry($hold, $type, $content, $lengthDaysInput);
    if (!($prepared['ok'] ?? false)) {
        return $prepared;
    }

    $updateQuery = "UPDATE rumors SET hold = $1, content = $2, type = $3, rumor_length_days = $4 WHERE id = $5";
    $updateResult = pg_query_params(
        $pgConn,
        $updateQuery,
        [
            $prepared['hold'],
            $prepared['content'],
            $prepared['type'],
            $prepared['rumor_length_days'],
            $rumorId,
        ]
    );

    if (!$updateResult) {
        error_log('[RUMORS] Failed to update rumor: ' . pg_last_error($pgConn));
        return [
            'ok' => false,
            'error_type' => 'server',
            'message' => 'Failed to update rumor.',
        ];
    }

    if (pg_affected_rows($updateResult) < 1) {
        return [
            'ok' => false,
            'error_type' => 'validation',
            'message' => 'Rumor not found.',
        ];
    }

    return [
        'ok' => true,
        'message' => 'Rumor updated successfully.',
        'id' => $rumorId,
        'hold' => $prepared['hold'],
        'type' => $prepared['type'],
        'content' => $prepared['content'],
        'rumor_length_days' => $prepared['rumor_length_days'],
    ];
}

function chimDeleteRumorEntry($pgConn, $rumorId) {
    $rumorId = (int) $rumorId;
    if ($rumorId <= 0) {
        return [
            'ok' => false,
            'error_type' => 'validation',
            'message' => 'Invalid rumor selected for deletion.',
        ];
    }

    $deleteResult = pg_query_params(
        $pgConn,
        "DELETE FROM rumors WHERE id = $1",
        [$rumorId]
    );

    if (!$deleteResult) {
        error_log('[RUMORS] Failed to delete rumor: ' . pg_last_error($pgConn));
        return [
            'ok' => false,
            'error_type' => 'server',
            'message' => 'Failed to delete rumor.',
        ];
    }

    if (pg_affected_rows($deleteResult) < 1) {
        return [
            'ok' => false,
            'error_type' => 'validation',
            'message' => 'Rumor not found.',
        ];
    }

    return [
        'ok' => true,
        'message' => 'Rumor deleted successfully.',
        'id' => $rumorId,
    ];
}
