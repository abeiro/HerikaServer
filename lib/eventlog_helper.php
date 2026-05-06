<?php

if (!function_exists('chimGetVisibleEventLogExcludedTypes')) {
    function chimGetVisibleEventLogExcludedTypes()
    {
        return [
            'prechat',
            'rechat',
            'infonpc',
            'request',
            'infonpc_close',
            'addnpc',
            'user_input',
            'infosave',
            'init',
            'playerinfo',
            'oghma_import',
            'biography_import',
            'dynamic_oghma_import',
            'infoitems',
            'description_import',
            'backgroundaction',
            'innerchat',
            'npc_reanimated',
            'npcvoice_refresh',
            'status_msg',
            'region',
        ];
    }
}

if (!function_exists('chimBuildVisibleEventLogWhereClause')) {
    function chimBuildVisibleEventLogWhereClause($db)
    {
        $excludedTypes = chimGetVisibleEventLogExcludedTypes();
        $escapedTypes = array_map(function ($type) use ($db) {
            return "'" . $db->escape($type) . "'";
        }, $excludedTypes);

        return "type NOT IN (" . implode(',', $escapedTypes) . ")";
    }
}

if (!function_exists('chimDeleteLatestVisibleEventLogRows')) {
    function chimDeleteLatestVisibleEventLogRows($db, $deleteCount)
    {
        $deleteCount = intval($deleteCount);
        if (!in_array($deleteCount, [20, 50, 100], true)) {
            return [
                'ok' => false,
                'deleted_count' => 0,
                'message' => 'Unsupported delete count.',
            ];
        }

        $visibleWhereClause = chimBuildVisibleEventLogWhereClause($db);
        $targetRows = $db->fetchAll("
            SELECT rowid
            FROM eventlog
            WHERE {$visibleWhereClause}
            ORDER BY gamets DESC, ts DESC, localts DESC, rowid DESC
            LIMIT {$deleteCount}
        ");

        $targetRowids = [];
        foreach ($targetRows as $targetRow) {
            $targetRowid = intval($targetRow['rowid'] ?? 0);
            if ($targetRowid > 0) {
                $targetRowids[] = $targetRowid;
            }
        }

        if (!empty($targetRowids)) {
            $targetRowidsStr = implode(',', $targetRowids);
            $db->query("DELETE FROM eventlog WHERE rowid IN ({$targetRowidsStr})");
        }

        return [
            'ok' => true,
            'deleted_count' => count($targetRowids),
            'requested_count' => $deleteCount,
            'message' => 'Deleted latest visible events.',
        ];
    }
}
