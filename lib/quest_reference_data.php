<?php

if (!function_exists('chimParseStableFormReference')) {
    require_once(__DIR__ . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "game_plugins.php");
}
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'quest_asset_library.php');

if (!function_exists('quest_reference_dataset_config')) {
    function quest_reference_dataset_config()
    {
        return [
            'item_types' => [
                'table' => 'quest_item_types',
                'key_column' => 'type_key',
            ],
            'npc_templates' => [
                'table' => 'quest_npc_templates',
                'key_column' => 'template_key',
            ],
            'npc_own_templates' => [
                'table' => 'quest_npc_own_templates',
                'key_column' => 'template_key',
            ],
            'outfit' => [
                'table' => 'quest_outfits',
                'key_column' => 'class_key',
            ],
        ];
    }
}

if (!function_exists('quest_reference_has_db')) {
    function quest_reference_has_db()
    {
        return isset($GLOBALS["db"]) && is_object($GLOBALS["db"]);
    }
}

if (!function_exists('quest_reference_table_exists')) {
    function quest_reference_table_exists($tableName)
    {
        static $cache = [];
        if (isset($cache[$tableName])) {
            return $cache[$tableName];
        }

        if (!quest_reference_has_db()) {
            $cache[$tableName] = false;
            return false;
        }

        try {
            $tableCn = $GLOBALS["db"]->escape($tableName);
            $row = $GLOBALS["db"]->fetchOne("
                SELECT 1 as n
                FROM information_schema.tables
                WHERE table_schema = 'public' AND table_name = '{$tableCn}'
                LIMIT 1
            ");

            $cache[$tableName] = isset($row["n"]);
            return $cache[$tableName];
        } catch (Exception $e) {
            $cache[$tableName] = false;
            return false;
        }
    }
}

if (!function_exists('quest_reference_column_exists')) {
    function quest_reference_column_exists($tableName, $columnName)
    {
        static $cache = [];
        $cacheKey = strtolower($tableName . "|" . $columnName);
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        if (!quest_reference_table_exists($tableName)) {
            $cache[$cacheKey] = false;
            return false;
        }

        try {
            $tableCn = $GLOBALS["db"]->escape($tableName);
            $columnCn = $GLOBALS["db"]->escape($columnName);
            $row = $GLOBALS["db"]->fetchOne("
                SELECT 1 as n
                FROM information_schema.columns
                WHERE table_schema = 'public'
                  AND table_name = '{$tableCn}'
                  AND column_name = '{$columnCn}'
                LIMIT 1
            ");

            $cache[$cacheKey] = isset($row["n"]);
            return $cache[$cacheKey];
        } catch (Exception $e) {
            $cache[$cacheKey] = false;
            return false;
        }
    }
}

if (!function_exists('quest_reference_array_row_sentinel')) {
    function quest_reference_array_row_sentinel()
    {
        return "__array__";
    }
}

if (!function_exists('quest_reference_formid_column_is_text')) {
    function quest_reference_formid_column_is_text($tableName)
    {
        static $cache = [];
        $cacheKey = strtolower((string) $tableName);
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        if (!quest_reference_column_exists($tableName, "formid")) {
            $cache[$cacheKey] = false;
            return false;
        }

        try {
            $tableCn = $GLOBALS["db"]->escape($tableName);
            $row = $GLOBALS["db"]->fetchOne("
                SELECT data_type, udt_name
                FROM information_schema.columns
                WHERE table_schema = 'public'
                  AND table_name = '{$tableCn}'
                  AND column_name = 'formid'
                LIMIT 1
            ");

            $dataType = strtolower(trim((string) ($row["data_type"] ?? "")));
            $udtName = strtolower(trim((string) ($row["udt_name"] ?? "")));
            $cache[$cacheKey] = ($dataType === "text" || $udtName === "text");
            return $cache[$cacheKey];
        } catch (Exception $e) {
            $cache[$cacheKey] = false;
            return false;
        }
    }
}

if (!function_exists('quest_reference_normalize_formid')) {
    function quest_reference_resolve_runtime_formid_string($value)
    {
        if (is_int($value)) {
            if ($value < 0) {
                return null;
            }

            return sprintf('%08X', $value & 0xFFFFFFFF);
        }

        if (is_float($value)) {
            $intValue = intval($value);
            if ($intValue < 0) {
                return null;
            }

            return sprintf('%08X', $intValue & 0xFFFFFFFF);
        }

        $cn = trim((string) $value);
        if ($cn === '') {
            return null;
        }

        $stableReference = chimParseStableFormReference($cn);
        if ($stableReference) {
            return chimResolveStableFormReferenceToRuntimeFormId($stableReference['stable_key']);
        }

        if (stripos($cn, "0x") === 0) {
            $hex = chimNormalizeRuntimeFormId($cn);
            return $hex !== '' ? $hex : null;
        }

        if (preg_match('/^[0-9A-Fa-f]{8}$/', $cn)) {
            $hex = chimNormalizeRuntimeFormId($cn);
            return $hex !== '' ? $hex : null;
        }

        if (preg_match('/^[0-9A-Fa-f]{1,8}$/', $cn) && preg_match('/[A-Fa-f]/', $cn)) {
            $hex = chimNormalizeRuntimeFormId($cn);
            return $hex !== '' ? $hex : null;
        }

        if (preg_match('/^-?\d+$/', $cn)) {
            $intValue = intval($cn, 10);
            if ($intValue < 0) {
                return null;
            }

            return sprintf('%08X', $intValue & 0xFFFFFFFF);
        }

        return null;
    }

    function quest_reference_classify_formid_for_text_storage($value, ?array $pluginsByPrefix = null)
    {
        $stableReference = chimParseStableFormReference($value);
        if ($stableReference) {
            return [
                'value' => $stableReference['stable_key'],
                'status' => 'stable',
                'runtime_formid' => null,
            ];
        }

        $runtimeFormId = quest_reference_resolve_runtime_formid_string($value);
        if ($runtimeFormId === null) {
            return [
                'value' => null,
                'status' => 'invalid',
                'runtime_formid' => null,
            ];
        }

        if (strpos($runtimeFormId, 'FF') === 0) {
            return [
                'value' => null,
                'status' => 'dynamic',
                'runtime_formid' => $runtimeFormId,
            ];
        }

        $stableReference = chimConvertRuntimeFormIdToStableReference($runtimeFormId, $pluginsByPrefix);
        if ($stableReference !== null) {
            return [
                'value' => $stableReference,
                'status' => 'converted',
                'runtime_formid' => $runtimeFormId,
            ];
        }

        return [
            'value' => strtolower('0x' . $runtimeFormId),
            'status' => 'unresolved',
            'runtime_formid' => $runtimeFormId,
        ];
    }

    function quest_reference_canonicalize_formid_for_text_storage($value, ?array $pluginsByPrefix = null)
    {
        $classified = quest_reference_classify_formid_for_text_storage($value, $pluginsByPrefix);
        return $classified['value'];
    }

    function quest_reference_legacy_local_plugin_name($datasetName, $keyName, $runtimeFormId)
    {
        $datasetName = strtolower(trim((string) $datasetName));
        $keyName = strtolower(trim((string) $keyName));
        $runtimeFormId = chimNormalizeRuntimeFormId($runtimeFormId);
        $runtimeValue = $runtimeFormId !== '' ? hexdec($runtimeFormId) : -1;

        $isLegacyNpcTemplateKey = preg_match(
            '/^(female|male)_(breton|nord|imperial|redguard|orc|argonian|altmer|bosmer|dunmer|khajiit)_(noble|merchant|warrior|assassin|mage|beggar|farmer|bard|soldier)$/',
            $keyName
        ) === 1 || in_array($keyName, ['female_breton_forsworn', 'male_breton_forsworn'], true);
        if (
            $datasetName === 'npc_own_templates'
            && $isLegacyNpcTemplateKey
            && (
                ($runtimeValue >= 0x00025844 && $runtimeValue <= 0x0002584D)
                || ($runtimeValue >= 0x00025DAF && $runtimeValue <= 0x00025DED)
                || ($runtimeValue >= 0x00045CE7 && $runtimeValue <= 0x00045CEE)
            )
        ) {
            return 'AIAgent.esp';
        }

        $legacyItemFormIds = [
            'potion' => 0x0002481F,
            'necklace' => 0x0002481D,
            'amulet' => 0x0002481E,
            'ring' => 0x000242B9,
        ];
        if ($datasetName === 'item_types' && ($legacyItemFormIds[$keyName] ?? -1) === $runtimeValue) {
            return 'AIAgent.esp';
        }

        return null;
    }

    function quest_reference_classify_dataset_formid_for_text_storage(
        $datasetName,
        $keyName,
        $value,
        ?array $pluginsByPrefix = null,
        ?array $pluginsByName = null
    ) {
        $stableReference = chimParseStableFormReference($value);
        if ($stableReference) {
            return [
                'value' => $stableReference['stable_key'],
                'status' => 'stable',
                'runtime_formid' => null,
            ];
        }

        $runtimeFormId = quest_reference_resolve_runtime_formid_string($value);
        $legacyPluginName = quest_reference_legacy_local_plugin_name($datasetName, $keyName, $runtimeFormId);
        if (
            $runtimeFormId !== null
            && $runtimeFormId !== '00000000'
            && substr($runtimeFormId, 0, 2) === '00'
            && $legacyPluginName !== null
        ) {
            $pluginRow = $pluginsByName === null
                ? chimGetLoadedGamePluginByName($legacyPluginName)
                : ($pluginsByName[strtolower($legacyPluginName)] ?? null);

            if (is_array($pluginRow) && !empty($pluginRow['plugin_name'])) {
                return [
                    'value' => chimBuildStableFormReference(
                        $pluginRow['plugin_name'],
                        chimExtractLocalFormIdFromRuntimeFormId($runtimeFormId)
                    ),
                    'status' => 'converted',
                    'runtime_formid' => $runtimeFormId,
                ];
            }

            return [
                'value' => strtolower('0x' . $runtimeFormId),
                'status' => 'unresolved',
                'runtime_formid' => $runtimeFormId,
            ];
        }

        if ($runtimeFormId === '00000000') {
            return [
                'value' => '0x00000000',
                'status' => 'unresolved',
                'runtime_formid' => $runtimeFormId,
            ];
        }

        return quest_reference_classify_formid_for_text_storage($value, $pluginsByPrefix);
    }

    function quest_reference_canonicalize_dataset_formid_for_text_storage(
        $datasetName,
        $keyName,
        $value,
        ?array $pluginsByPrefix = null,
        ?array $pluginsByName = null
    ) {
        $classified = quest_reference_classify_dataset_formid_for_text_storage(
            $datasetName,
            $keyName,
            $value,
            $pluginsByPrefix,
            $pluginsByName
        );
        return $classified['value'];
    }

    function quest_reference_normalize_formid($value)
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return intval($value);
        }

        if (is_string($value)) {
            $cn = trim($value);
            if ($cn === "") {
                return null;
            }

            $stableReference = chimParseStableFormReference($cn);
            if ($stableReference) {
                $runtimeFormId = chimResolveStableFormReferenceToRuntimeFormId($stableReference['stable_key']);
                if ($runtimeFormId !== null) {
                    return hexdec($runtimeFormId);
                }

                return null;
            }

            if (stripos($cn, "0x") === 0) {
                $runtimeFormId = chimNormalizeRuntimeFormId($cn);
                return $runtimeFormId !== '' ? hexdec($runtimeFormId) : null;
            }

            if (preg_match('/^[0-9A-Fa-f]{8}$/', $cn)) {
                $runtimeFormId = chimNormalizeRuntimeFormId($cn);
                return $runtimeFormId !== '' ? hexdec($runtimeFormId) : null;
            }

            if (preg_match('/^[0-9A-Fa-f]{1,8}$/', $cn) && preg_match('/[A-Fa-f]/', $cn)) {
                $runtimeFormId = chimNormalizeRuntimeFormId($cn);
                return $runtimeFormId !== '' ? hexdec($runtimeFormId) : null;
            }

            if (preg_match('/^-?\d+$/', $cn)) {
                return intval($cn, 10);
            }
        }

        return null;
    }
}

if (!function_exists('quest_reference_formid_for_papyrus')) {
    function quest_reference_formid_for_papyrus($value): int
    {
        $formId = quest_reference_normalize_formid($value);
        if ($formId === null) {
            return 0;
        }

        $unsigned = $formId & 0xFFFFFFFF;
        return $unsigned > 0x7FFFFFFF ? $unsigned - 0x100000000 : $unsigned;
    }
}

if (!function_exists('quest_reference_formid_for_full_plugin_file')) {
    function quest_reference_formid_for_full_plugin_file($value): int
    {
        $formId = quest_reference_normalize_formid($value);
        return $formId === null ? 0 : $formId & 0x00FFFFFF;
    }
}

if (!function_exists('quest_reference_repair_formid_values')) {
    function quest_reference_repair_formid_values(
        $datasetName,
        $keyName,
        $values,
        array $pluginsByPrefix,
        array $pluginsByName
    )
    {
        if (!is_array($values)) {
            return [
                'values' => [],
                'changed' => false,
                'converted' => 0,
                'unresolved' => 0,
                'dynamic' => 0,
                'invalid' => 1,
            ];
        }

        $repaired = [];
        $seen = [];
        $summary = [
            'values' => [],
            'changed' => false,
            'converted' => 0,
            'unresolved' => 0,
            'dynamic' => 0,
            'invalid' => 0,
        ];

        foreach ($values as $value) {
            $original = trim((string) $value);
            if ($original === '') {
                $summary['invalid']++;
                continue;
            }

            $classified = quest_reference_classify_dataset_formid_for_text_storage(
                $datasetName,
                $keyName,
                $original,
                $pluginsByPrefix,
                $pluginsByName
            );
            $status = $classified['status'];
            $canonical = $classified['value'];

            if ($status === 'dynamic' || $status === 'invalid') {
                $canonical = $original;
                $summary[$status]++;
            } elseif ($status === 'converted') {
                $summary['converted']++;
            } elseif ($status === 'unresolved') {
                $summary['unresolved']++;
            }

            $dedupeKey = strtolower($canonical);
            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;
            $repaired[] = $canonical;
        }

        $summary['values'] = $repaired;
        $summary['changed'] = array_values($values) !== $repaired;
        return $summary;
    }
}

if (!function_exists('quest_reference_repair_runtime_formids_to_stable')) {
    function quest_reference_repair_runtime_formids_to_stable(array $plugins)
    {
        $summary = [
            'rows_scanned' => 0,
            'rows_updated' => 0,
            'converted' => 0,
            'unresolved' => 0,
            'dynamic' => 0,
            'invalid' => 0,
            'error' => null,
        ];

        if (!quest_reference_has_db()) {
            $summary['error'] = 'database_unavailable';
            return $summary;
        }

        $pluginsByPrefix = chimIndexLoadedGamePluginsByPrefix($plugins);
        $pluginsByName = chimIndexLoadedGamePluginsByName($plugins);
        if (empty($pluginsByPrefix)) {
            $summary['error'] = 'plugin_manifest_empty';
            return $summary;
        }

        $transactionStarted = false;
        try {
            $beginResult = $GLOBALS["db"]->execQuery("BEGIN");
            if ($beginResult === false) {
                throw new RuntimeException("Could not start quest reference repair transaction.");
            }
            $transactionStarted = true;

            foreach (quest_reference_dataset_config() as $datasetName => $cfg) {
                $table = $cfg['table'];
                $keyColumn = $cfg['key_column'];
                if (!quest_reference_table_exists($table) || !quest_reference_column_exists($table, 'formids_json')) {
                    continue;
                }

                $rows = $GLOBALS["db"]->fetchAll("
                    SELECT {$keyColumn} AS key_name, formids_json
                    FROM public.{$table}
                ");

                foreach ((array) $rows as $row) {
                    $summary['rows_scanned']++;
                    $encodedValues = $row['formids_json'] ?? '[]';
                    $values = is_array($encodedValues) ? $encodedValues : json_decode((string) $encodedValues, true);
                    if (!is_array($values)) {
                        $summary['invalid']++;
                        continue;
                    }

                    $keyName = trim((string) ($row['key_name'] ?? ''));
                    if ($keyName === '') {
                        $summary['invalid']++;
                        continue;
                    }

                    $repair = quest_reference_repair_formid_values(
                        $datasetName,
                        $keyName,
                        $values,
                        $pluginsByPrefix,
                        $pluginsByName
                    );
                    foreach (['converted', 'unresolved', 'dynamic', 'invalid'] as $counter) {
                        $summary[$counter] += $repair[$counter];
                    }

                    if (!$repair['changed']) {
                        continue;
                    }

                    $keyCn = $GLOBALS["db"]->escape($keyName);
                    $jsonCn = $GLOBALS["db"]->escape(json_encode($repair['values']));
                    $updateResult = $GLOBALS["db"]->execQuery("
                        UPDATE public.{$table}
                        SET formids_json = '{$jsonCn}'::jsonb,
                            updated_at = now()
                        WHERE {$keyColumn} = '{$keyCn}'
                    ");
                    if ($updateResult === false) {
                        throw new RuntimeException("Could not update quest reference row '{$keyName}'.");
                    }

                    $summary['rows_updated']++;
                }
            }

            $commitResult = $GLOBALS["db"]->execQuery("COMMIT");
            if ($commitResult === false) {
                throw new RuntimeException("Could not commit quest reference repair transaction.");
            }
            $transactionStarted = false;
        } catch (Throwable $e) {
            if ($transactionStarted) {
                $GLOBALS["db"]->execQuery("ROLLBACK");
            }
            $summary['error'] = $e->getMessage();
        }

        return $summary;
    }
}

if (!function_exists('quest_reference_extract_formids')) {
    function quest_reference_extract_formids($value)
    {
        $result = [];

        if ($value === null || $value === "") {
            return $result;
        }

        if (is_array($value)) {
            foreach ($value as $v) {
                $normalized = quest_reference_normalize_formid($v);
                if ($normalized !== null) {
                    $result[] = $normalized;
                }
            }

            return $result;
        }

        $cn = is_string($value) ? trim($value) : $value;

        if (is_string($cn) && strlen($cn) >= 2 && $cn[0] === '[' && substr($cn, -1) === ']') {
            $decoded = json_decode($cn, true);
            if (is_array($decoded)) {
                return quest_reference_extract_formids($decoded);
            }
        }

        // PostgreSQL array text format, e.g. "{123,456}".
        if (is_string($cn) && strlen($cn) >= 2 && $cn[0] === '{' && substr($cn, -1) === '}') {
            $inner = trim($cn, "{} \t\n\r\0\x0B");
            if ($inner === "") {
                return [];
            }

            foreach (explode(",", $inner) as $part) {
                $part = trim($part, "\" \t\n\r\0\x0B");
                $normalized = quest_reference_normalize_formid($part);
                if ($normalized !== null) {
                    $result[] = $normalized;
                }
            }

            return $result;
        }

        if (is_string($cn) && strpos($cn, ",") !== false) {
            foreach (explode(",", $cn) as $part) {
                $normalized = quest_reference_normalize_formid($part);
                if ($normalized !== null) {
                    $result[] = $normalized;
                }
            }

            return $result;
        }

        $normalized = quest_reference_normalize_formid($cn);
        if ($normalized !== null) {
            $result[] = $normalized;
        }

        return $result;
    }
}

if (!function_exists('quest_reference_normalize_dataset_values')) {
    function quest_reference_normalize_dataset_values($values)
    {
        $normalized = [];
        if (!is_array($values)) {
            return $normalized;
        }

        foreach ($values as $key => $formIds) {
            $keyCn = strtolower(trim((string) $key));
            if ($keyCn === "") {
                continue;
            }

            $list = is_array($formIds) ? $formIds : [$formIds];
            if (empty($list)) {
                continue;
            }

            $final = [];
            $dedupe = [];
            foreach ($list as $formId) {
                $stableReference = chimParseStableFormReference($formId);
                if ($stableReference) {
                    $dedupeKey = strtolower($stableReference['stable_key']);
                    if (!isset($dedupe[$dedupeKey])) {
                        $dedupe[$dedupeKey] = true;
                        $final[] = $stableReference['stable_key'];
                    }
                    continue;
                }

                $value = quest_reference_normalize_formid($formId);
                if ($value === null || $value < 0) {
                    continue;
                }

                $dedupeKey = 'runtime:' . $value;
                if (!isset($dedupe[$dedupeKey])) {
                    $dedupe[$dedupeKey] = true;
                    $final[] = intval($value);
                }
            }

            if (!empty($final)) {
                $normalized[$keyCn] = $final;
            }
        }

        return $normalized;
    }
}

if (!function_exists('quest_reference_prepare_formids_for_storage')) {
    function quest_reference_prepare_formids_for_storage($datasetName, $keyName, $formIds, $storeAsText)
    {
        $prepared = [];
        foreach ($formIds as $formId) {
            if ($storeAsText) {
                $canonical = quest_reference_canonicalize_dataset_formid_for_text_storage(
                    $datasetName,
                    $keyName,
                    $formId
                );
                if ($canonical === null || $canonical === '') {
                    continue;
                }

                $prepared[] = $canonical;
            } else {
                $normalized = quest_reference_normalize_formid($formId);
                if ($normalized === null || $normalized < 0) {
                    continue;
                }

                $prepared[] = intval($normalized);
            }
        }

        return array_values($prepared);
    }
}

if (!function_exists('quest_reference_sql_formid_literal')) {
    function quest_reference_sql_formid_literal($datasetName, $keyName, $formId, $storeAsText)
    {
        if ($storeAsText) {
            $canonical = quest_reference_canonicalize_dataset_formid_for_text_storage(
                $datasetName,
                $keyName,
                $formId
            );
            if ($canonical === null || $canonical === '') {
                return null;
            }

            $canonicalCn = $GLOBALS["db"]->escape($canonical);
            return "'{$canonicalCn}'";
        }

        $normalized = quest_reference_normalize_formid($formId);
        if ($normalized === null) {
            return null;
        }

        return (string) intval($normalized);
    }
}

if (!function_exists('quest_reference_sql_array_sentinel_literal')) {
    function quest_reference_sql_array_sentinel_literal($storeAsText)
    {
        if ($storeAsText) {
            $sentinelCn = $GLOBALS["db"]->escape(quest_reference_array_row_sentinel());
            return "'{$sentinelCn}'";
        }

        return "-1";
    }
}

if (!function_exists('quest_reference_tables_ready')) {
    function quest_reference_tables_ready()
    {
        $cfg = quest_reference_dataset_config();
        foreach ($cfg as $entry) {
            if (!quest_reference_table_exists($entry["table"])) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('quest_reference_table_count')) {
    function quest_reference_table_count($tableName)
    {
        if (!quest_reference_table_exists($tableName)) {
            return 0;
        }

        try {
            $row = $GLOBALS["db"]->fetchOne("SELECT count(*) as n FROM public.{$tableName}");
            return intval($row["n"] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }
}

if (!function_exists('quest_reference_seed_dataset_if_empty')) {
    function quest_reference_seed_dataset_if_empty($datasetName, $values)
    {
        if (!quest_reference_has_db() || !is_array($values)) {
            return;
        }

        $cfg = quest_reference_dataset_config();
        if (!isset($cfg[$datasetName])) {
            return;
        }

        $table = $cfg[$datasetName]["table"];
        $keyColumn = $cfg[$datasetName]["key_column"];

        if (!quest_reference_table_exists($table)) {
            return;
        }

        if (quest_reference_table_count($table) > 0) {
            return;
        }

        $normalized = quest_reference_normalize_dataset_values($values);
        if (empty($normalized)) {
            return;
        }

        $hasArrayColumn = quest_reference_column_exists($table, "formids_json");
        $hasFormIdColumn = quest_reference_column_exists($table, "formid");
        $storeAsText = (!$hasFormIdColumn) || quest_reference_formid_column_is_text($table);

        foreach ($normalized as $key => $formIds) {
            $keyCn = $GLOBALS["db"]->escape($key);

            if ($hasArrayColumn) {
                $preparedFormIds = quest_reference_prepare_formids_for_storage(
                    $datasetName,
                    $key,
                    $formIds,
                    $storeAsText
                );
                $jsonPayload = $GLOBALS["db"]->escape(json_encode($preparedFormIds));
                if ($hasFormIdColumn) {
                    $arraySentinelSql = quest_reference_sql_array_sentinel_literal($storeAsText);
                    $GLOBALS["db"]->execQuery("
                        INSERT INTO public.{$table} ({$keyColumn}, formid, formids_json, active, note)
                        VALUES ('{$keyCn}', {$arraySentinelSql}, '{$jsonPayload}'::jsonb, true, 'seeded from hardcoded quest reference')
                        ON CONFLICT ({$keyColumn}, formid)
                        DO UPDATE SET
                            formids_json = EXCLUDED.formids_json,
                            active = EXCLUDED.active,
                            updated_at = now()
                    ");
                } else {
                    $GLOBALS["db"]->execQuery("
                        INSERT INTO public.{$table} ({$keyColumn}, formids_json, active, note)
                        VALUES ('{$keyCn}', '{$jsonPayload}'::jsonb, true, 'seeded from hardcoded quest reference')
                        ON CONFLICT ({$keyColumn})
                        DO UPDATE SET
                            formids_json = EXCLUDED.formids_json,
                            active = EXCLUDED.active,
                            updated_at = now()
                    ");
                }
                continue;
            }

            if (!$hasFormIdColumn) {
                continue;
            }

            foreach ($formIds as $formId) {
                $formIdSql = quest_reference_sql_formid_literal(
                    $datasetName,
                    $key,
                    $formId,
                    $storeAsText
                );
                if ($formIdSql === null) {
                    continue;
                }

                $GLOBALS["db"]->execQuery("
                    INSERT INTO public.{$table} ({$keyColumn}, formid, active)
                    VALUES ('{$keyCn}', {$formIdSql}, true)
                    ON CONFLICT ({$keyColumn}, formid) DO NOTHING
                ");
            }
        }
    }
}

if (!function_exists('quest_reference_missing_dataset_values')) {
    function quest_reference_missing_dataset_values($values, $existingKeys)
    {
        $normalized = quest_reference_normalize_dataset_values($values);
        $existing = [];
        foreach (is_array($existingKeys) ? $existingKeys : [] as $key) {
            $keyCn = strtolower(trim((string) $key));
            if ($keyCn !== '') {
                $existing[$keyCn] = true;
            }
        }

        return array_diff_key($normalized, $existing);
    }
}

if (!function_exists('quest_reference_add_missing_dataset_entries')) {
    function quest_reference_add_missing_dataset_entries(
        $datasetName,
        $values,
        $defaultActive = true,
        $note = 'added from built-in quest reference defaults'
    ) {
        if (!quest_reference_has_db() || !is_array($values)) {
            return false;
        }

        $datasetName = strtolower(trim((string) $datasetName));
        $cfg = quest_reference_dataset_config();
        if (!isset($cfg[$datasetName])) {
            return false;
        }

        $table = $cfg[$datasetName]['table'];
        $keyColumn = $cfg[$datasetName]['key_column'];
        if (!quest_reference_table_exists($table)) {
            return false;
        }

        try {
            $rows = $GLOBALS['db']->fetchAll(
                "SELECT DISTINCT {$keyColumn} AS key_name FROM public.{$table}"
            );
        } catch (Throwable $e) {
            return false;
        }

        $existingKeys = [];
        foreach ($rows as $row) {
            $existingKeys[] = $row['key_name'] ?? '';
        }
        $missing = quest_reference_missing_dataset_values($values, $existingKeys);
        if (empty($missing)) {
            return 0;
        }

        $hasArrayColumn = quest_reference_column_exists($table, 'formids_json');
        $hasFormIdColumn = quest_reference_column_exists($table, 'formid');
        if (!$hasArrayColumn && !$hasFormIdColumn) {
            return false;
        }

        $storeAsText = (!$hasFormIdColumn) || quest_reference_formid_column_is_text($table);
        $activeSql = $defaultActive ? 'true' : 'false';
        $noteCn = $GLOBALS['db']->escape($note);
        $insertedKeys = 0;

        try {
            if ($GLOBALS['db']->execQuery('BEGIN') === false) {
                throw new RuntimeException('Unable to start quest reference synchronization transaction.');
            }

            foreach ($missing as $key => $formIds) {
                $keyCn = $GLOBALS['db']->escape($key);
                if ($hasArrayColumn) {
                    $preparedFormIds = quest_reference_prepare_formids_for_storage(
                        $datasetName,
                        $key,
                        $formIds,
                        $storeAsText
                    );
                    $jsonPayload = $GLOBALS['db']->escape(json_encode($preparedFormIds));
                    if ($hasFormIdColumn) {
                        $arraySentinelSql = quest_reference_sql_array_sentinel_literal($storeAsText);
                        $sql = "
                            INSERT INTO public.{$table} ({$keyColumn}, formid, formids_json, active, note)
                            VALUES ('{$keyCn}', {$arraySentinelSql}, '{$jsonPayload}'::jsonb, {$activeSql}, '{$noteCn}')
                            ON CONFLICT ({$keyColumn}, formid) DO NOTHING
                        ";
                    } else {
                        $sql = "
                            INSERT INTO public.{$table} ({$keyColumn}, formids_json, active, note)
                            VALUES ('{$keyCn}', '{$jsonPayload}'::jsonb, {$activeSql}, '{$noteCn}')
                            ON CONFLICT ({$keyColumn}) DO NOTHING
                        ";
                    }
                    if ($GLOBALS['db']->execQuery($sql) === false) {
                        throw new RuntimeException("Unable to add missing quest reference key {$key}.");
                    }
                    $insertedKeys++;
                    continue;
                }

                foreach ($formIds as $formId) {
                    $formIdSql = quest_reference_sql_formid_literal(
                        $datasetName,
                        $key,
                        $formId,
                        $storeAsText
                    );
                    if ($formIdSql === null) {
                        continue;
                    }
                    $sql = "
                        INSERT INTO public.{$table} ({$keyColumn}, formid, active, note)
                        VALUES ('{$keyCn}', {$formIdSql}, {$activeSql}, '{$noteCn}')
                        ON CONFLICT ({$keyColumn}, formid) DO NOTHING
                    ";
                    if ($GLOBALS['db']->execQuery($sql) === false) {
                        throw new RuntimeException("Unable to add missing quest reference key {$key}.");
                    }
                }
                $insertedKeys++;
            }

            if ($GLOBALS['db']->execQuery('COMMIT') === false) {
                throw new RuntimeException('Unable to commit quest reference synchronization.');
            }
            return $insertedKeys;
        } catch (Throwable $e) {
            try {
                $GLOBALS['db']->execQuery('ROLLBACK');
            } catch (Throwable $_rollbackError) {
            }
            return false;
        }
    }
}

if (!function_exists('quest_reference_replace_dataset_with_arrays')) {
    function quest_reference_replace_dataset_with_arrays($datasetName, $values, $defaultActive = true, $note = 'synced from hardcoded quest reference')
    {
        if (!quest_reference_has_db() || !is_array($values)) {
            return false;
        }

        $cfg = quest_reference_dataset_config();
        if (!isset($cfg[$datasetName])) {
            return false;
        }

        $table = $cfg[$datasetName]["table"];
        $keyColumn = $cfg[$datasetName]["key_column"];

        if (!quest_reference_table_exists($table)) {
            return false;
        }

        if (!quest_reference_column_exists($table, "formids_json")) {
            return false;
        }

        $normalized = quest_reference_normalize_dataset_values($values);
        $hasFormIdColumn = quest_reference_column_exists($table, "formid");
        $storeAsText = (!$hasFormIdColumn) || quest_reference_formid_column_is_text($table);

        try {
            $GLOBALS["db"]->execQuery("BEGIN");
            $GLOBALS["db"]->execQuery("DELETE FROM public.{$table}");

            $activeSql = $defaultActive ? "true" : "false";
            $noteCn = $GLOBALS["db"]->escape($note);

            foreach ($normalized as $key => $formIds) {
                $keyCn = $GLOBALS["db"]->escape($key);
                $preparedFormIds = quest_reference_prepare_formids_for_storage(
                    $datasetName,
                    $key,
                    $formIds,
                    $storeAsText
                );
                $jsonPayload = $GLOBALS["db"]->escape(json_encode($preparedFormIds));
                if ($hasFormIdColumn) {
                    $arraySentinelSql = quest_reference_sql_array_sentinel_literal($storeAsText);
                    $GLOBALS["db"]->execQuery("
                        INSERT INTO public.{$table} ({$keyColumn}, formid, formids_json, active, note)
                        VALUES ('{$keyCn}', {$arraySentinelSql}, '{$jsonPayload}'::jsonb, {$activeSql}, '{$noteCn}')
                    ");
                } else {
                    $GLOBALS["db"]->execQuery("
                        INSERT INTO public.{$table} ({$keyColumn}, formids_json, active, note)
                        VALUES ('{$keyCn}', '{$jsonPayload}'::jsonb, {$activeSql}, '{$noteCn}')
                    ");
                }
            }

            $GLOBALS["db"]->execQuery("COMMIT");
            return true;
        } catch (Exception $e) {
            try {
                $GLOBALS["db"]->execQuery("ROLLBACK");
            } catch (Exception $_rollbackError) {
            }

            return false;
        }
    }
}

if (!function_exists('quest_reference_load_dataset')) {
    function quest_reference_load_dataset($datasetName, $activeOnly = true)
    {
        $datasetName = strtolower(trim((string) $datasetName));
        $cfg = quest_reference_dataset_config();
        $libraryDatasets = function_exists('quest_asset_dataset_signatures')
            ? quest_asset_dataset_signatures()
            : [];
        if (!isset($cfg[$datasetName]) && !isset($libraryDatasets[$datasetName])) {
            return [];
        }

        $result = [];
        $seen = [];
        $rows = [];
        $hasArrayColumn = false;
        $hasFormIdColumn = false;
        $table = null;
        $keyColumn = null;
        if (isset($cfg[$datasetName])) {
            $table = $cfg[$datasetName]["table"];
            $keyColumn = $cfg[$datasetName]["key_column"];
        }
        if ($table !== null && $keyColumn !== null && quest_reference_table_exists($table)) {
            $where = $activeOnly ? "WHERE active = true" : "";
            $hasArrayColumn = quest_reference_column_exists($table, "formids_json");
            $hasFormIdColumn = quest_reference_column_exists($table, "formid");

            $selectColumns = ["{$keyColumn} as key_name"];
            if ($hasFormIdColumn) {
                $selectColumns[] = "formid";
            }
            if ($hasArrayColumn) {
                $selectColumns[] = "formids_json";
            }

            $orderColumns = [$keyColumn];
            if ($hasFormIdColumn) {
                $orderColumns[] = "formid";
            }

            $selectSql = implode(", ", $selectColumns);
            $orderSql = implode(", ", $orderColumns);

            try {
                $rows = $GLOBALS["db"]->fetchAll("
                    SELECT {$selectSql}
                    FROM public.{$table}
                    {$where}
                    ORDER BY {$orderSql}
                ");
            } catch (Throwable $e) {
                $rows = [];
            }
        }

        foreach ($rows as $row) {
            $key = strtolower(trim((string) ($row["key_name"] ?? "")));
            if ($key === "") {
                continue;
            }

            if (!isset($result[$key])) {
                $result[$key] = [];
            }

            $formIds = [];
            if ($hasArrayColumn && array_key_exists("formids_json", $row) && $row["formids_json"] !== null && $row["formids_json"] !== "") {
                $formIds = array_merge($formIds, quest_reference_extract_formids($row["formids_json"]));
            }

            if ($hasFormIdColumn && array_key_exists("formid", $row)) {
                $scalarFormId = quest_reference_normalize_formid($row["formid"] ?? null);
                if ($scalarFormId !== null && $scalarFormId >= 0) {
                    $formIds[] = $scalarFormId;
                }
            }

            foreach ($formIds as $formId) {
                if (!isset($seen[$key])) {
                    $seen[$key] = [];
                }

                if (isset($seen[$key][$formId])) {
                    continue;
                }

                $seen[$key][$formId] = true;
                $result[$key][] = intval($formId);
            }
        }

        $libraryValues = function_exists('quest_asset_load_dataset')
            ? quest_asset_load_dataset($datasetName)
            : [];
        $result = function_exists('quest_asset_merge_dataset')
            ? quest_asset_merge_dataset($result, $libraryValues)
            : $result;
        return function_exists('quest_asset_apply_group_fallbacks')
            ? quest_asset_apply_group_fallbacks($datasetName, $result)
            : $result;
    }
}

if (!function_exists('quest_reference_load_all_active')) {
    function quest_reference_load_all_active()
    {
        $datasetNames = array_keys(quest_reference_dataset_config());
        if (function_exists('quest_asset_dataset_signatures')) {
            $datasetNames = array_unique(array_merge(
                $datasetNames,
                array_keys(quest_asset_dataset_signatures())
            ));
        }

        $result = [];
        foreach ($datasetNames as $datasetName) {
            $result[$datasetName] = quest_reference_load_dataset($datasetName, true);
        }

        return $result;
    }
}

if (!function_exists('quest_reference_active_keys')) {
    function quest_reference_active_keys($datasetName)
    {
        $dataset = quest_reference_load_dataset($datasetName, true);
        return array_keys($dataset);
    }
}

if (!function_exists('quest_reference_pick_safe_spawn_base')) {
    function quest_reference_pick_safe_spawn_base($dataset, $gender, $race, $class, $defaultClasses = null)
    {
        if (!is_array($dataset)) {
            return 0;
        }

        $gender = strtolower(trim((string) $gender));
        $race = strtolower(trim((string) $race));
        $class = strtolower(trim((string) $class));
        $classes = [$class];
        foreach (is_array($defaultClasses) ? $defaultClasses : ['warrior', 'soldier', 'farmer'] as $fallbackClass) {
            $fallbackClass = strtolower(trim((string) $fallbackClass));
            if ($fallbackClass !== '' && !in_array($fallbackClass, $classes, true)) {
                $classes[] = $fallbackClass;
            }
        }

        foreach ($classes as $candidateClass) {
            $value = quest_reference_pick_random($dataset, "{$gender}_{$race}_{$candidateClass}", 0);
            if ($value !== 0) {
                return $value;
            }
        }
        return 0;
    }
}

if (!function_exists('quest_reference_normalize_allowed_values')) {
    function quest_reference_normalize_allowed_values($values)
    {
        $normalized = [];
        foreach (is_array($values) ? $values : [] as $value) {
            $key = strtolower(trim((string) $value));
            if ($key !== '' && preg_match('/^[a-z0-9_]+$/', $key)) {
                $normalized[$key] = true;
            }
        }
        $result = array_keys($normalized);
        sort($result, SORT_NATURAL | SORT_FLAG_CASE);
        return $result;
    }
}

if (!function_exists('quest_reference_playable_races')) {
    function quest_reference_playable_races()
    {
        return [
            'nord',
            'imperial',
            'redguard',
            'breton',
            'altmer',
            'bosmer',
            'dunmer',
            'orc',
            'argonian',
            'khajiit',
        ];
    }
}

if (!function_exists('quest_reference_spawnable_playable_races')) {
    function quest_reference_spawnable_playable_races($donorKeys = null, $spawnKeys = null, $classes = null)
    {
        $donorKeys = is_array($donorKeys) ? $donorKeys : quest_reference_active_keys('npc_templates');
        $spawnKeys = is_array($spawnKeys) ? $spawnKeys : quest_reference_active_keys('npc_own_templates');

        $donorSet = array_fill_keys(quest_reference_normalize_allowed_values($donorKeys), true);
        $spawnSet = array_fill_keys(quest_reference_normalize_allowed_values($spawnKeys), true);
        $spawnable = [];

        foreach (quest_reference_playable_races() as $race) {
            $supported = true;
            foreach (['male', 'female'] as $gender) {
                if (!isset($donorSet["{$gender}_{$race}"])) {
                    $supported = false;
                    break;
                }

                // Outfit groups are global and may include classes unavailable to some races.
                // The spawn resolver safely falls back within the same race and gender.
                $prefix = "{$gender}_{$race}_";
                $hasSpawnBase = false;
                foreach ($spawnSet as $spawnKey => $_enabled) {
                    if (str_starts_with($spawnKey, $prefix)) {
                        $hasSpawnBase = true;
                        break;
                    }
                }
                if (!$hasSpawnBase) {
                    $supported = false;
                    break;
                }
            }

            if ($supported) {
                $spawnable[] = $race;
            }
        }

        return $spawnable;
    }
}

if (!function_exists('quest_reference_prompt_constraints')) {
    function quest_reference_prompt_constraints($fallbackRaces, $fallbackClasses, $fallbackItemTypes)
    {
        $templateRaces = quest_reference_derive_races_from_template_keys(
            quest_reference_active_keys('npc_templates')
        );
        $creatureRaces = array_values(array_diff($templateRaces, quest_reference_playable_races()));
        $races = array_merge(quest_reference_spawnable_playable_races(), $creatureRaces);
        if (empty($races)) {
            $races = $fallbackRaces;
        }

        $classes = quest_reference_active_keys('outfit');
        if (empty($classes)) {
            $classes = $fallbackClasses;
        }
        $classes[] = 'creature';

        $itemTypes = quest_reference_active_keys('item_types');
        if (empty($itemTypes)) {
            $itemTypes = $fallbackItemTypes;
        }

        return [
            'races' => quest_reference_normalize_allowed_values($races),
            'classes' => quest_reference_normalize_allowed_values($classes),
            'item_types' => quest_reference_normalize_allowed_values($itemTypes),
        ];
    }
}

if (!function_exists('quest_reference_apply_prompt_constraints')) {
    function quest_reference_apply_prompt_constraints($template, $constraints)
    {
        $constraints = is_array($constraints) ? $constraints : [];
        return strtr((string) $template, [
            '{{ALLOWED_RACES}}' => implode(', ', $constraints['races'] ?? []),
            '{{ALLOWED_CLASSES}}' => implode(', ', $constraints['classes'] ?? []),
            '{{ALLOWED_ITEM_TYPES}}' => implode(', ', $constraints['item_types'] ?? []),
        ]);
    }
}

if (!function_exists('quest_reference_pick_random')) {
    function quest_reference_pick_random($dataset, $key, $default = 0)
    {
        $cnKey = strtolower((string) $key);
        if (!isset($dataset[$cnKey]) || !is_array($dataset[$cnKey]) || empty($dataset[$cnKey])) {
            return $default;
        }

        return $dataset[$cnKey][array_rand($dataset[$cnKey])];
    }
}

if (!function_exists('quest_reference_pick_any_random')) {
    function quest_reference_pick_any_random($dataset, $default = 0)
    {
        if (!is_array($dataset) || empty($dataset)) {
            return $default;
        }

        foreach ($dataset as $list) {
            if (is_array($list) && !empty($list)) {
                return $list[array_rand($list)];
            }
        }

        return $default;
    }
}

if (!function_exists('quest_reference_derive_races_from_template_keys')) {
    function quest_reference_derive_races_from_template_keys($templateKeys)
    {
        $races = [];
        foreach ($templateKeys as $key) {
            if (preg_match('/^(male|female)_(.+)$/', $key, $m)) {
                $races[] = strtolower(trim($m[2]));
            }
        }

        return array_values(array_unique(array_filter($races)));
    }
}

if (!function_exists('quest_reference_formid_to_hex')) {
    function quest_reference_formid_to_hex($formId)
    {
        $value = quest_reference_normalize_formid($formId);
        if ($value === null) {
            return "";
        }

        if ($value < 0) {
            return (string) $value;
        }

        return sprintf("0x%08x", $value);
    }
}

if (!function_exists('quest_reference_format_dataset_for_prompt')) {
    function quest_reference_format_dataset_for_prompt($datasetName, $activeOnly = true)
    {
        $dataset = quest_reference_load_dataset($datasetName, $activeOnly);
        if (empty($dataset) || !is_array($dataset)) {
            return "";
        }

        ksort($dataset, SORT_NATURAL | SORT_FLAG_CASE);

        $lines = [];
        foreach ($dataset as $key => $formIds) {
            if (!is_array($formIds) || empty($formIds)) {
                continue;
            }

            $parts = [];
            foreach ($formIds as $formId) {
                $parts[] = quest_reference_formid_to_hex($formId);
            }

            $lines[] = $key . " => [" . implode(", ", $parts) . "]";
        }

        return implode("\n", $lines);
    }
}
