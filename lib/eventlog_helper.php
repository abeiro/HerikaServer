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
            'traditional_quest_import',
            'backgroundaction',
            'innerchat',
            'npc_reanimated',
            'npcvoice_refresh',
            'status_msg',
            'region',
            'ext_nsfw_physics_raw',
        ];
    }
}

if (!function_exists('chimBuildVisibleEventLogWhereClause')) {
    function chimBuildVisibleEventLogWhereClause($db, $selectedType = '', $additionalExcludedTypes = [])
    {
        $excludedTypes = array_values(array_unique(array_merge(
            chimGetVisibleEventLogExcludedTypes(),
            is_array($additionalExcludedTypes) ? $additionalExcludedTypes : []
        )));
        $escapedTypes = array_map(function ($type) use ($db) {
            return "'" . $db->escape($type) . "'";
        }, $excludedTypes);

        $clauses = [
            "type NOT IN (" . implode(',', $escapedTypes) . ")",
        ];

        $selectedType = trim((string)$selectedType);
        if ($selectedType !== '') {
            $clauses[] = "type = '" . $db->escape($selectedType) . "'";
        }

        return implode(' AND ', $clauses);
    }
}

if (!function_exists('chimGetVisibleEventLogTypes')) {
    function chimGetVisibleEventLogTypes($db, $additionalExcludedTypes = [])
    {
        $visibleWhereClause = chimBuildVisibleEventLogWhereClause($db, '', $additionalExcludedTypes);

        return $db->fetchAll("
            SELECT type, COUNT(*) AS total
            FROM eventlog
            WHERE {$visibleWhereClause}
            GROUP BY type
            ORDER BY type ASC
        ");
    }
}

if (!function_exists('chimNormalizeEventLogTypeList')) {
    function chimNormalizeEventLogTypeList($types)
    {
        if (!is_array($types)) {
            return [];
        }

        $normalized = [];
        foreach ($types as $type) {
            $type = trim((string)$type);
            if ($type === '') {
                continue;
            }
            $normalized[$type] = $type;
        }

        return array_values($normalized);
    }
}

if (!function_exists('chimGetPersistedEventLogHiddenTypes')) {
    function chimGetPersistedEventLogHiddenTypes($db)
    {
        $confKey = 'chim_eventlog_hidden_types';
        $row = $db->fetchOne("SELECT value FROM conf_opts WHERE id='" . $db->escape($confKey) . "' LIMIT 1");
        $rawValue = trim((string)($row['value'] ?? ''));
        if ($rawValue === '') {
            return [];
        }

        $decoded = json_decode($rawValue, true);
        if (is_array($decoded)) {
            return chimNormalizeEventLogTypeList($decoded);
        }

        return chimNormalizeEventLogTypeList(explode(',', $rawValue));
    }
}

if (!function_exists('chimSavePersistedEventLogHiddenTypes')) {
    function chimSavePersistedEventLogHiddenTypes($db, $types)
    {
        $confKey = 'chim_eventlog_hidden_types';
        $normalizedTypes = chimNormalizeEventLogTypeList($types);

        if (empty($normalizedTypes)) {
            $db->delete('conf_opts', "id='" . $db->escape($confKey) . "'");
            return true;
        }

        return $db->upsertRowOnConflict('conf_opts', [
            'id' => $confKey,
            'value' => json_encode(array_values($normalizedTypes), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ], 'id');
    }
}

if (!function_exists('chimDeleteLatestVisibleEventLogRows')) {
    function chimDeleteLatestVisibleEventLogRows($db, $deleteCount, $selectedType = '', $additionalExcludedTypes = [])
    {
        $deleteCount = intval($deleteCount);
        if (!in_array($deleteCount, [20, 50, 100], true)) {
            return [
                'ok' => false,
                'deleted_count' => 0,
                'message' => 'Unsupported delete count.',
            ];
        }

        $visibleWhereClause = chimBuildVisibleEventLogWhereClause($db, $selectedType, $additionalExcludedTypes);
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
