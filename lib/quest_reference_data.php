<?php

if (!function_exists('chimParseStableFormReference')) {
    require_once(__DIR__ . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "game_plugins.php");
}

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

    function quest_reference_canonicalize_formid_for_text_storage($value)
    {
        $stableReference = chimParseStableFormReference($value);
        if ($stableReference) {
            return $stableReference['stable_key'];
        }

        $runtimeFormId = quest_reference_resolve_runtime_formid_string($value);
        if ($runtimeFormId === null) {
            return null;
        }

        return strtolower('0x' . $runtimeFormId);
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

            $list = quest_reference_extract_formids($formIds);
            if (empty($list)) {
                continue;
            }

            $dedupe = [];
            foreach ($list as $formId) {
                $value = quest_reference_normalize_formid($formId);
                if ($value === null || $value < 0) {
                    continue;
                }
                $dedupe[$value] = true;
            }

            if (empty($dedupe)) {
                continue;
            }

            $final = array_map('intval', array_keys($dedupe));
            sort($final, SORT_NUMERIC);
            $normalized[$keyCn] = $final;
        }

        return $normalized;
    }
}

if (!function_exists('quest_reference_prepare_formids_for_storage')) {
    function quest_reference_prepare_formids_for_storage($formIds, $storeAsText)
    {
        $prepared = [];
        foreach ($formIds as $formId) {
            if ($storeAsText) {
                $canonical = quest_reference_canonicalize_formid_for_text_storage($formId);
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
    function quest_reference_sql_formid_literal($formId, $storeAsText)
    {
        if ($storeAsText) {
            $canonical = quest_reference_canonicalize_formid_for_text_storage($formId);
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
                $preparedFormIds = quest_reference_prepare_formids_for_storage($formIds, $storeAsText);
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
                $formIdSql = quest_reference_sql_formid_literal($formId, $storeAsText);
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
                $preparedFormIds = quest_reference_prepare_formids_for_storage($formIds, $storeAsText);
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
        $cfg = quest_reference_dataset_config();
        if (!isset($cfg[$datasetName])) {
            return [];
        }

        $table = $cfg[$datasetName]["table"];
        $keyColumn = $cfg[$datasetName]["key_column"];

        if (!quest_reference_table_exists($table)) {
            return [];
        }

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
        } catch (Exception $e) {
            return [];
        }

        $result = [];
        $seen = [];
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

        return $result;
    }
}

if (!function_exists('quest_reference_load_all_active')) {
    function quest_reference_load_all_active()
    {
        $cfg = quest_reference_dataset_config();
        $result = [];
        foreach ($cfg as $datasetName => $_cfg) {
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
