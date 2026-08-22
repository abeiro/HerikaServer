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
            'addbgnpc',
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

if (!function_exists('chimBuildNpcEventLogPeopleWhereClause')) {
    // Match one NPC token without allowing partial-name matches or far-away audience markers.
    function chimBuildNpcEventLogPeopleWhereClause($db, $npcName, $peopleColumn = 'people')
    {
        $peopleColumn = trim((string)$peopleColumn);
        if (!preg_match('/^(?:[A-Za-z_][A-Za-z0-9_]*\.)?[A-Za-z_][A-Za-z0-9_]*$/', $peopleColumn)) {
            $peopleColumn = 'people';
        }

        $escapedNpcName = $db->escape(trim((string)$npcName));
        return "EXISTS (
            SELECT 1
            FROM unnest(string_to_array(trim(BOTH '|' FROM COALESCE({$peopleColumn}, '')), '|')) AS chim_person(person_name)
            WHERE lower(regexp_replace(btrim(chim_person.person_name), ' \\((busy|hostile|in combat|restrained)\\)$', '', 'i')) = lower('{$escapedNpcName}')
        )";
    }
}

if (!function_exists('chimGetVisibleEventLogTypes')) {
    function chimGetVisibleEventLogTypes($db, $additionalExcludedTypes = [])
    {
        $visibleWhereClause = chimBuildVisibleEventLogWhereClause($db, '', $additionalExcludedTypes);

        $types = $db->fetchAll("
            SELECT type, COUNT(*) AS total
            FROM eventlog
            WHERE {$visibleWhereClause}
            GROUP BY type
            ORDER BY type ASC
        ");

        if (!in_array('relationship', $additionalExcludedTypes, true)) {
            $relationshipRow = $db->fetchOne(
                "SELECT 1 AS available
                 FROM core_npc_master_history
                 WHERE extended_data ->> '_chim_history_source' = 'relationship'
                 LIMIT 1"
            );
            if ($relationshipRow) {
                $types[] = [
                    'type' => 'relationship',
                    'total' => 1,
                ];
                usort($types, static function (array $left, array $right): int {
                    return strcmp((string)($left['type'] ?? ''), (string)($right['type'] ?? ''));
                });
            }
        }

        return $types;
    }
}

if (!function_exists('chimRelationshipHistoryTimelineCte')) {
    /**
     * Pair each NPC history snapshot with its predecessor without copying relationship data into eventlog.
     */
    function chimRelationshipHistoryTimelineCte()
    {
        return "WITH ordered_relationship_history AS (
            SELECT
                history_id,
                npc_id,
                npc_name,
                extended_data,
                gamets_last_updated,
                created,
                LAG(extended_data) OVER (
                    PARTITION BY npc_id
                    ORDER BY gamets_last_updated ASC NULLS FIRST, created ASC, history_id ASC
                ) AS previous_extended_data
            FROM core_npc_master_history
        ), visible_relationship_history AS (
            SELECT *
            FROM ordered_relationship_history
            WHERE extended_data ->> '_chim_history_source' = 'relationship'
              AND COALESCE(extended_data -> 'relationships', '{}'::jsonb)
                  IS DISTINCT FROM COALESCE(previous_extended_data -> 'relationships', '{}'::jsonb)
        )";
    }
}

if (!function_exists('chimCountRelationshipHistoryTimelineRows')) {
    function chimCountRelationshipHistoryTimelineRows($db)
    {
        $row = $db->fetchOne(
            chimRelationshipHistoryTimelineCte()
            . " SELECT COUNT(*) AS total FROM visible_relationship_history"
        );
        return intval($row['total'] ?? 0);
    }
}

if (!function_exists('chimGetLatestRelationshipHistoryId')) {
    function chimGetLatestRelationshipHistoryId($db)
    {
        $row = $db->fetchOne(
            "SELECT COALESCE(MAX(history_id), 0) AS latest_id
             FROM core_npc_master_history
             WHERE extended_data ->> '_chim_history_source' = 'relationship'"
        );
        return intval($row['latest_id'] ?? 0);
    }
}

if (!function_exists('chimBuildRelationshipHistoryTimelineRows')) {
    /**
     * Turn persisted relationship snapshots into virtual timeline rows for user-facing views.
     */
    function chimBuildRelationshipHistoryTimelineRows(array $snapshots)
    {
        if (!class_exists('RelationshipManager')) {
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'relationship_manager.php';
        }

        $rows = [];
        foreach ($snapshots as $snapshot) {
            $historyId = intval($snapshot['history_id'] ?? 0);
            if ($historyId <= 0) {
                continue;
            }

            $currentExtended = json_decode((string)($snapshot['extended_data'] ?? ''), true);
            $previousExtended = json_decode((string)($snapshot['previous_extended_data'] ?? ''), true);
            $currentExtended = is_array($currentExtended) ? $currentExtended : [];
            $previousExtended = is_array($previousExtended) ? $previousExtended : [];
            $changes = RelationshipManager::buildRelationshipChangeSummaries(
                (string)($snapshot['npc_name'] ?? ''),
                $previousExtended['relationships'] ?? [],
                $currentExtended['relationships'] ?? []
            );
            if (empty($changes)) {
                continue;
            }

            $people = [];
            $descriptions = [];
            foreach ($changes as $change) {
                $description = trim((string)($change['data'] ?? ''));
                if ($description !== '') {
                    $descriptions[] = $description;
                }
                foreach (explode('|', trim((string)($change['people'] ?? ''), '|')) as $person) {
                    $person = trim($person);
                    if ($person !== '' && !in_array($person, $people, true)) {
                        $people[] = $person;
                    }
                }
            }
            if (empty($descriptions)) {
                continue;
            }

            $localTimestamp = intval($snapshot['localts'] ?? 0);
            $rows[] = [
                'type' => 'relationship',
                'data' => implode(' ', $descriptions),
                'people' => '|' . implode('|', $people) . '|',
                'gamets' => (int)($snapshot['gamets_last_updated'] ?? 0),
                'localts' => $localTimestamp,
                'ts' => $localTimestamp,
                'rowid' => 'relationship:' . $historyId,
                'relationship_history_id' => $historyId,
                'source' => 'relationship_history',
            ];
        }

        return $rows;
    }
}

if (!function_exists('chimFetchRelationshipHistoryTimelineRows')) {
    function chimFetchRelationshipHistoryTimelineRows(
        $db,
        $limit,
        $offset = 0,
        $sinceHistoryId = 0,
        $sinceGamets = 0
    ) {
        $limit = max(1, min(5000, intval($limit)));
        $offset = max(0, intval($offset));
        $sinceHistoryId = max(0, intval($sinceHistoryId));
        $sinceGamets = max(0, intval($sinceGamets));

        $where = [];
        if ($sinceHistoryId > 0) {
            $where[] = "history_id > {$sinceHistoryId}";
        }
        if ($sinceGamets > 0) {
            $where[] = "gamets_last_updated >= {$sinceGamets}";
        }
        $whereSql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);
        $incremental = $sinceHistoryId > 0;
        $orderSql = $incremental
            ? 'history_id ASC'
            : 'gamets_last_updated DESC NULLS LAST, created DESC, history_id DESC';

        $snapshots = $db->fetchAll(
            chimRelationshipHistoryTimelineCte()
            . " SELECT
                    history_id,
                    npc_name,
                    extended_data,
                    previous_extended_data,
                    gamets_last_updated,
                    EXTRACT(EPOCH FROM created)::bigint AS localts
                FROM visible_relationship_history
                {$whereSql}
                ORDER BY {$orderSql}
                LIMIT {$limit} OFFSET {$offset}"
        );

        return chimBuildRelationshipHistoryTimelineRows($snapshots);
    }
}

if (!function_exists('chimFetchRecentRelationshipHistoryChanges')) {
    /**
     * Read-only feed for compact relationship panels (dashboard widget, NPC editor).
     *
     * Reuses the derived timeline rows so relationship history stays in
     * core_npc_master_history; nothing is copied into eventlog. Pass $npcId to
     * scope the feed to a single NPC (the snapshot owner), 0 for every NPC.
     */
    function chimFetchRecentRelationshipHistoryChanges($db, $limit = 5, $npcId = 0)
    {
        $limit = max(1, min(50, intval($limit)));
        $npcId = max(0, intval($npcId));
        // Snapshots whose summaries resolve to nothing are dropped by the row builder,
        // so read a slightly wider window than the caller asked for.
        $sourceWindow = min(200, $limit * 4);
        $whereSql = $npcId > 0 ? "WHERE npc_id = {$npcId}" : '';

        $snapshots = $db->fetchAll(
            chimRelationshipHistoryTimelineCte()
            . " SELECT
                    history_id,
                    npc_id,
                    npc_name,
                    extended_data,
                    previous_extended_data,
                    gamets_last_updated,
                    EXTRACT(EPOCH FROM created)::bigint AS localts
                FROM visible_relationship_history
                {$whereSql}
                ORDER BY gamets_last_updated DESC NULLS LAST, created DESC, history_id DESC
                LIMIT {$sourceWindow}"
        );
        if (!is_array($snapshots) || empty($snapshots)) {
            return [];
        }

        $npcNames = [];
        foreach ($snapshots as $snapshot) {
            $npcNames[intval($snapshot['history_id'] ?? 0)] = trim((string)($snapshot['npc_name'] ?? ''));
        }

        $rows = chimBuildRelationshipHistoryTimelineRows($snapshots);
        foreach ($rows as $index => $row) {
            $rows[$index]['npc_name'] = $npcNames[intval($row['relationship_history_id'] ?? 0)] ?? '';
        }

        return array_slice($rows, 0, $limit);
    }
}

if (!function_exists('chimMergeTimelineRows')) {
    function chimMergeTimelineRows(array $eventRows, array $relationshipRows, $limit = 0, $offset = 0)
    {
        $rows = array_merge($eventRows, $relationshipRows);
        usort($rows, static function (array $left, array $right): int {
            foreach (['gamets', 'ts', 'localts'] as $key) {
                $comparison = ((float)($right[$key] ?? 0)) <=> ((float)($left[$key] ?? 0));
                if ($comparison !== 0) {
                    return $comparison;
                }
            }
            return intval($right['relationship_history_id'] ?? $right['rowid'] ?? 0)
                <=> intval($left['relationship_history_id'] ?? $left['rowid'] ?? 0);
        });

        $offset = max(0, intval($offset));
        $limit = max(0, intval($limit));
        return $limit > 0 ? array_slice($rows, $offset, $limit) : array_slice($rows, $offset);
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

if (!function_exists('chimDeleteEventLogRow')) {
    function chimDeleteEventLogRow($db, $rowId)
    {
        $rowId = intval($rowId);
        if ($rowId <= 0) {
            return [
                'ok' => false,
                'deleted_count' => 0,
                'message' => 'Invalid event row.',
            ];
        }

        $visibleWhereClause = chimBuildVisibleEventLogWhereClause($db);
        $existing = $db->fetchOne("SELECT rowid FROM eventlog WHERE rowid={$rowId} AND {$visibleWhereClause} LIMIT 1");
        if (!$existing) {
            return [
                'ok' => true,
                'rowid' => $rowId,
                'deleted_count' => 0,
                'message' => 'Event is no longer available.',
            ];
        }

        if (!$db->delete('eventlog', "rowid={$rowId}")) {
            return [
                'ok' => false,
                'rowid' => $rowId,
                'deleted_count' => 0,
                'message' => 'Failed to delete event.',
            ];
        }

        return [
            'ok' => true,
            'rowid' => $rowId,
            'deleted_count' => 1,
            'message' => 'Event deleted.',
        ];
    }
}
