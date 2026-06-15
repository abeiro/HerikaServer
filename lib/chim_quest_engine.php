<?php

if (!function_exists('chimQuestEngineHasDb')) {
    function chimQuestEngineHasDb()
    {
        return isset($GLOBALS["db"]) && is_object($GLOBALS["db"]);
    }
}

if (!function_exists('chimQuestEngineLog')) {
    function chimQuestEngineLog($level, $message)
    {
        $levelCn = strtolower(trim((string)$level));
        if (class_exists('Logger')) {
            if ($levelCn === 'warn' && method_exists('Logger', 'warn')) {
                Logger::warn($message);
                return;
            }
            if ($levelCn === 'error' && method_exists('Logger', 'error')) {
                Logger::error($message);
                return;
            }
            if ($levelCn === 'debug' && method_exists('Logger', 'debug')) {
                Logger::debug($message);
                return;
            }
            if ($levelCn === 'trace' && method_exists('Logger', 'trace')) {
                Logger::trace($message);
                return;
            }
            if (method_exists('Logger', 'info')) {
                Logger::info($message);
                return;
            }
        }

        error_log("[chim_quest_engine][$levelCn] $message");
    }
}

if (!function_exists('chimQuestEngineJsonEncode')) {
    function chimQuestEngineJsonEncode($value)
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return '{}';
        }

        return $json;
    }
}

if (!function_exists('chimQuestEngineJsonDecode')) {
    function chimQuestEngineJsonDecode($value, $default = array())
    {
        if (!is_string($value) || trim($value) === '') {
            return $default;
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return $default;
        }

        return $decoded;
    }
}

if (!function_exists('chimQuestEngineTableExists')) {
    function chimQuestEngineTableExists($tableName)
    {
        static $cache = array();
        $tableCn = strtolower(trim((string)$tableName));
        if ($tableCn === '') {
            return false;
        }
        if (isset($cache[$tableCn])) {
            return $cache[$tableCn];
        }
        if (!chimQuestEngineHasDb()) {
            $cache[$tableCn] = false;
            return false;
        }

        try {
            $tableEscaped = $GLOBALS["db"]->escape($tableCn);
            $row = $GLOBALS["db"]->fetchOne("
                SELECT 1 as n
                FROM information_schema.tables
                WHERE table_schema = 'public'
                  AND table_name = '{$tableEscaped}'
                LIMIT 1
            ");
            $cache[$tableCn] = isset($row["n"]);
        } catch (Exception $e) {
            $cache[$tableCn] = false;
        }

        return $cache[$tableCn];
    }
}

if (!function_exists('chimQuestEngineReady')) {
    function chimQuestEngineReady()
    {
        return chimQuestEngineHasDb() && chimQuestEngineTableExists('skyrim_quest_definitions');
    }
}

if (!function_exists('chimQuestEngineFeatureEnabled')) {
    function chimQuestEngineFeatureEnabled()
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        if (!chimQuestEngineHasDb() || !chimQuestEngineTableExists('conf_opts')) {
            $cached = false;
            return false;
        }

        try {
            $row = $GLOBALS["db"]->fetchOne("
                SELECT value
                FROM public.conf_opts
                WHERE lower(id) = 'chim_ai_quest_progression'
                LIMIT 1
            ");
            $valueCn = strtolower(trim((string)($row['value'] ?? '')));
            if (in_array($valueCn, array('1', 'true', 'on', 'yes', 'enabled'), true)) {
                $cached = true;
                return true;
            }
            if (in_array($valueCn, array('0', 'false', 'off', 'no', 'disabled'), true)) {
                $cached = false;
                return false;
            }
        } catch (Exception $e) {
            chimQuestEngineLog('warn', 'Could not read chim_ai_quest_progression: ' . $e->getMessage());
        }

        $cached = false;
        return false;
    }
}

if (!function_exists('chimQuestEngineNormalizeQuestKey')) {
    function chimQuestEngineNormalizeQuestKey($value)
    {
        $cn = strtolower(trim((string)$value));
        $cn = preg_replace('/[^a-z0-9_]+/', '_', $cn);
        $cn = trim((string)$cn, '_');
        return $cn;
    }
}

if (!function_exists('chimQuestEngineNormalizeText')) {
    function chimQuestEngineNormalizeText($text)
    {
        $cn = strtolower(trim((string)$text));
        $cn = str_replace(array("’", "`"), "'", $cn);

        $replacements = array(
            '/\b(?:i\s*\'\s*ll|i\'ll)\b/u' => 'i will',
            '/\b(?:ill|il)\b(?=\s+(?:get|go|help|find|retrieve|recover|reclaim|handle|do|take|bring|return|accept|slay|kill|crush|destroy|leave|count|be|have)\b)/u' => 'i will',
            '/\b(?:i\s*\'\s*m|i\'m|im)\b/u' => 'i am',
            '/\b(?:you\s*\'\s*ll|you\'ll|youll)\b/u' => 'you will',
            '/\b(?:we\s*\'\s*ll|we\'ll)\b/u' => 'we will',
            '/\b(?:can\s*\'\s*t|can\'t|cant)\b/u' => 'cannot',
            '/\b(?:won\s*\'\s*t|won\'t|wont)\b/u' => 'will not',
            '/\b(?:don\s*\'\s*t|don\'t|dont)\b/u' => 'do not',
            '/\b(?:doesn\s*\'\s*t|doesn\'t|doesnt)\b/u' => 'does not',
            '/\b(?:didn\s*\'\s*t|didn\'t|didnt)\b/u' => 'did not',
        );
        foreach ($replacements as $pattern => $replacement) {
            $cn = preg_replace($pattern, $replacement, $cn);
        }

        $cn = preg_replace('/[^a-z0-9\s]+/i', ' ', $cn);
        $cn = preg_replace('/\s+/', ' ', $cn);
        return trim((string)$cn);
    }
}

if (!function_exists('chimQuestEngineNormalizeHexFormId')) {
    function chimQuestEngineNormalizeHexFormId($value)
    {
        if (is_int($value) || is_float($value)) {
            $intval = intval($value);
            if ($intval < 0) {
                return '';
            }
            return sprintf("0x%08x", $intval);
        }

        $cn = trim((string)$value);
        if ($cn === '') {
            return '';
        }

        if (stripos($cn, '0x') === 0) {
            $intval = intval($cn, 16);
            if ($intval < 0) {
                return '';
            }
            return sprintf("0x%08x", $intval);
        }

        if (preg_match('/^[0-9a-f]+$/i', $cn) && strlen($cn) >= 6) {
            $intval = intval($cn, 16);
            if ($intval < 0) {
                return '';
            }
            return sprintf("0x%08x", $intval);
        }

        if (preg_match('/^\d+$/', $cn)) {
            $intval = intval($cn, 10);
            if ($intval < 0) {
                return '';
            }
            return sprintf("0x%08x", $intval);
        }

        return strtolower($cn);
    }
}

if (!function_exists('chimQuestEngineFormKey')) {
    function chimQuestEngineFormKey($plugin, $formId)
    {
        $pluginCn = strtolower(trim((string)$plugin));
        $formCn = chimQuestEngineNormalizeHexFormId($formId);
        if ($pluginCn === '' || $formCn === '') {
            return '';
        }

        return "{$pluginCn}:{$formCn}";
    }
}

if (!function_exists('chimQuestEngineNormalizeGamets')) {
    function chimQuestEngineNormalizeGamets($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $gamets = intval($value);
        return ($gamets > 0) ? $gamets : null;
    }
}

if (!function_exists('chimQuestEngineFindCaseInsensitiveKey')) {
    function chimQuestEngineFindCaseInsensitiveKey($array, $needle)
    {
        if (!is_array($array)) {
            return null;
        }

        $needleCn = strtolower(trim((string)$needle));
        foreach ($array as $key => $_value) {
            if (strtolower(trim((string)$key)) === $needleCn) {
                return $key;
            }
        }

        return null;
    }
}

if (!function_exists('chimQuestEngineDefaultState')) {
    function chimQuestEngineDefaultState()
    {
        return array(
            'has_items' => array(),
            'dead_actors' => array(),
            'entered_locations' => array(),
            'current_stage' => null,
            'last_dialogue' => array(),
        );
    }
}

if (!function_exists('chimQuestEngineNormalizeState')) {
    function chimQuestEngineNormalizeState($state)
    {
        $normalized = chimQuestEngineDefaultState();
        if (!is_array($state)) {
            return $normalized;
        }

        foreach ($normalized as $key => $defaultValue) {
            if (array_key_exists($key, $state)) {
                $normalized[$key] = $state[$key];
            }
        }

        if (!is_array($normalized['has_items'])) {
            $normalized['has_items'] = array();
        }
        if (!is_array($normalized['dead_actors'])) {
            $normalized['dead_actors'] = array();
        }
        if (!is_array($normalized['entered_locations'])) {
            $normalized['entered_locations'] = array();
        }
        if (!is_array($normalized['last_dialogue'])) {
            $normalized['last_dialogue'] = array();
        }

        if ($normalized['current_stage'] !== null && $normalized['current_stage'] !== '') {
            $normalized['current_stage'] = intval($normalized['current_stage']);
        } else {
            $normalized['current_stage'] = null;
        }

        return $normalized;
    }
}

if (!function_exists('chimQuestEngineBundledDefinitionFiles')) {
    function chimQuestEngineBundledDefinitionFiles()
    {
        $patterns = array(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'chim_quest_engine' . DIRECTORY_SEPARATOR . 'definitions' . DIRECTORY_SEPARATOR . '*.json',
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'CHIM QUEST TRIGGERS' . DIRECTORY_SEPARATOR . '02_GCH_SkyrimMod_POC' . DIRECTORY_SEPARATOR . 'GCH - POC TEST' . DIRECTORY_SEPARATOR . 'SKSE' . DIRECTORY_SEPARATOR . 'Plugins' . DIRECTORY_SEPARATOR . 'GCH_Skeletons' . DIRECTORY_SEPARATOR . 'quests' . DIRECTORY_SEPARATOR . '*.json',
        );

        $files = array();
        foreach ($patterns as $pattern) {
            foreach (glob($pattern) ?: array() as $filePath) {
                if (is_file($filePath)) {
                    $files[$filePath] = true;
                }
            }
        }

        return array_keys($files);
    }
}

if (!function_exists('chimQuestEngineEnsureInstanceRow')) {
    function chimQuestEngineEnsureInstanceRow(array $definition)
    {
        if (!chimQuestEngineReady()) {
            return false;
        }

        $questKey = chimQuestEngineNormalizeQuestKey($definition['quest_key'] ?? $definition['quest_editor_id'] ?? '');
        if ($questKey === '') {
            return false;
        }

        $questEditorId = $GLOBALS["db"]->escape((string)($definition['quest_editor_id'] ?? $questKey));
        $stateJson = $GLOBALS["db"]->escape(chimQuestEngineJsonEncode(chimQuestEngineDefaultState()));
        $questKeyEscaped = $GLOBALS["db"]->escape($questKey);
        $GLOBALS["db"]->execQuery("
            INSERT INTO public.skyrim_quest_instances (quest_key, quest_editor_id, run_state, current_stage, last_gamets, state_json)
            VALUES ('{$questKeyEscaped}', '{$questEditorId}', 'inactive', NULL, NULL, '{$stateJson}'::jsonb)
            ON CONFLICT (quest_key) DO NOTHING
        ");

        return true;
    }
}

if (!function_exists('chimQuestEngineImportDefinitionFile')) {
    function chimQuestEngineImportDefinitionFile($filePath)
    {
        if (!chimQuestEngineReady()) {
            return array('success' => false, 'error' => 'quest engine tables not ready');
        }
        if (!is_string($filePath) || !is_file($filePath)) {
            return array('success' => false, 'error' => 'definition file not found');
        }

        $raw = file_get_contents($filePath);
        if ($raw === false) {
            return array('success' => false, 'error' => 'could not read definition file');
        }

        $definition = json_decode($raw, true);
        if (!is_array($definition)) {
            return array('success' => false, 'error' => 'definition json is invalid');
        }

        $questKey = chimQuestEngineNormalizeQuestKey($definition['quest_key'] ?? $definition['quest_editor_id'] ?? pathinfo($filePath, PATHINFO_FILENAME));
        if ($questKey === '') {
            return array('success' => false, 'error' => 'definition missing quest key');
        }

        $definition['quest_key'] = $questKey;
        if (empty($definition['quest_editor_id'])) {
            $definition['quest_editor_id'] = strtoupper($questKey);
        }

        $questEditorId = $GLOBALS["db"]->escape((string)$definition['quest_editor_id']);
        $title = $GLOBALS["db"]->escape((string)($definition['title'] ?? $definition['quest_editor_id']));
        $sourcePlugin = $GLOBALS["db"]->escape((string)($definition['quest_plugin'] ?? ''));
        $sourceFormId = $GLOBALS["db"]->escape((string)($definition['quest_form_id'] ?? ''));
        $sourcePath = $GLOBALS["db"]->escape((string)$filePath);
        $skeletonJson = $GLOBALS["db"]->escape(chimQuestEngineJsonEncode($definition));
        $questKeyEscaped = $GLOBALS["db"]->escape($questKey);

        $GLOBALS["db"]->execQuery("
            INSERT INTO public.skyrim_quest_definitions
                (quest_key, quest_editor_id, title, source_plugin, source_form_id, source_path, skeleton, active)
            VALUES
                ('{$questKeyEscaped}', '{$questEditorId}', '{$title}', '{$sourcePlugin}', '{$sourceFormId}', '{$sourcePath}', '{$skeletonJson}'::jsonb, true)
            ON CONFLICT (quest_key) DO UPDATE SET
                quest_editor_id = EXCLUDED.quest_editor_id,
                title = EXCLUDED.title,
                source_plugin = EXCLUDED.source_plugin,
                source_form_id = EXCLUDED.source_form_id,
                source_path = EXCLUDED.source_path,
                skeleton = EXCLUDED.skeleton,
                active = true,
                updated_at = now()
        ");

        chimQuestEngineEnsureInstanceRow($definition);

        return array(
            'success' => true,
            'quest_key' => $questKey,
            'quest_editor_id' => $definition['quest_editor_id'],
            'title' => $definition['title'] ?? $definition['quest_editor_id'],
            'source_path' => $filePath,
        );
    }
}

if (!function_exists('chimQuestEngineImportBundledDefinitions')) {
    function chimQuestEngineImportBundledDefinitions()
    {
        $results = array();
        foreach (chimQuestEngineBundledDefinitionFiles() as $filePath) {
            $results[] = chimQuestEngineImportDefinitionFile($filePath);
        }

        return $results;
    }
}

if (!function_exists('chimQuestEngineMaybeBootstrapBundledDefinitions')) {
    function chimQuestEngineMaybeBootstrapBundledDefinitions()
    {
        static $bootstrapped = false;
        if ($bootstrapped || !chimQuestEngineReady()) {
            return;
        }
        $bootstrapped = true;

        try {
            $row = $GLOBALS["db"]->fetchOne("SELECT COUNT(*) as n FROM public.skyrim_quest_definitions");
            if (intval($row["n"] ?? 0) === 0) {
                $results = chimQuestEngineImportBundledDefinitions();
                chimQuestEngineLog('info', 'Bootstrapped chim quest definitions: ' . count($results));
            }
        } catch (Exception $e) {
            chimQuestEngineLog('warn', 'Could not bootstrap chim quest definitions: ' . $e->getMessage());
        }
    }
}

if (!function_exists('chimQuestEngineFetchDefinitions')) {
    function chimQuestEngineFetchDefinitions($activeOnly = true)
    {
        if (!chimQuestEngineReady()) {
            return array();
        }

        chimQuestEngineMaybeBootstrapBundledDefinitions();
        $where = $activeOnly ? "WHERE active = true" : "";
        $rows = $GLOBALS["db"]->fetchAll("
            SELECT quest_key, quest_editor_id, title, source_plugin, source_form_id, source_path, skeleton, active
            FROM public.skyrim_quest_definitions
            {$where}
            ORDER BY quest_key
        ");

        $definitions = array();
        foreach ($rows as $row) {
            $definition = chimQuestEngineJsonDecode($row["skeleton"] ?? '{}', array());
            if (!is_array($definition)) {
                $definition = array();
            }
            $definition['quest_key'] = $row['quest_key'];
            $definition['quest_editor_id'] = $row['quest_editor_id'];
            $definition['title'] = $row['title'];
            $definition['quest_plugin'] = $definition['quest_plugin'] ?? $row['source_plugin'];
            $definition['quest_form_id'] = $definition['quest_form_id'] ?? $row['source_form_id'];
            $definitions[] = $definition;
        }

        return $definitions;
    }
}

if (!function_exists('chimQuestEngineGetInstance')) {
    function chimQuestEngineGetInstance($questKey)
    {
        if (!chimQuestEngineReady()) {
            return null;
        }

        $questKeyCn = chimQuestEngineNormalizeQuestKey($questKey);
        if ($questKeyCn === '') {
            return null;
        }

        $questKeyEscaped = $GLOBALS["db"]->escape($questKeyCn);
        $row = $GLOBALS["db"]->fetchOne("
            SELECT quest_key, quest_editor_id, run_state, current_stage, last_gamets, state_json
            FROM public.skyrim_quest_instances
            WHERE quest_key = '{$questKeyEscaped}'
            LIMIT 1
        ");
        if (!$row) {
            return null;
        }

        $row['state_json'] = chimQuestEngineNormalizeState(chimQuestEngineJsonDecode($row['state_json'] ?? '{}', array()));
        $row['current_stage'] = ($row['current_stage'] === null || $row['current_stage'] === '') ? null : intval($row['current_stage']);
        $row['last_gamets'] = ($row['last_gamets'] === null || $row['last_gamets'] === '') ? null : intval($row['last_gamets']);
        return $row;
    }
}

if (!function_exists('chimQuestEnginePersistInstance')) {
    function chimQuestEnginePersistInstance(array $definition, array $instance)
    {
        if (!chimQuestEngineReady()) {
            return false;
        }

        $questKey = chimQuestEngineNormalizeQuestKey($instance['quest_key'] ?? $definition['quest_key'] ?? '');
        if ($questKey === '') {
            return false;
        }

        $questEditorId = $GLOBALS["db"]->escape((string)($instance['quest_editor_id'] ?? $definition['quest_editor_id'] ?? $questKey));
        $runState = $GLOBALS["db"]->escape((string)($instance['run_state'] ?? 'inactive'));
        $currentStageSql = ($instance['current_stage'] === null || $instance['current_stage'] === '') ? 'NULL' : intval($instance['current_stage']);
        $lastGamets = chimQuestEngineNormalizeGamets($instance['last_gamets'] ?? null);
        $lastGametsSql = ($lastGamets === null) ? 'NULL' : intval($lastGamets);
        $stateJson = $GLOBALS["db"]->escape(chimQuestEngineJsonEncode(chimQuestEngineNormalizeState($instance['state_json'] ?? array())));
        $questKeyEscaped = $GLOBALS["db"]->escape($questKey);

        $GLOBALS["db"]->execQuery("
            INSERT INTO public.skyrim_quest_instances (quest_key, quest_editor_id, run_state, current_stage, last_gamets, state_json)
            VALUES ('{$questKeyEscaped}', '{$questEditorId}', '{$runState}', {$currentStageSql}, {$lastGametsSql}, '{$stateJson}'::jsonb)
            ON CONFLICT (quest_key) DO UPDATE SET
                quest_editor_id = EXCLUDED.quest_editor_id,
                run_state = EXCLUDED.run_state,
                current_stage = EXCLUDED.current_stage,
                last_gamets = EXCLUDED.last_gamets,
                state_json = EXCLUDED.state_json,
                updated_at = now()
        ");

        return true;
    }
}

if (!function_exists('chimQuestEngineLoadBeatStateMap')) {
    function chimQuestEngineLoadBeatStateMap($questKey)
    {
        if (!chimQuestEngineReady()) {
            return array();
        }

        $questKeyCn = chimQuestEngineNormalizeQuestKey($questKey);
        if ($questKeyCn === '') {
            return array();
        }

        $questKeyEscaped = $GLOBALS["db"]->escape($questKeyCn);
        $rows = $GLOBALS["db"]->fetchAll("
            SELECT beat_id, fired, fired_order, fired_gamets, evidence_json
            FROM public.skyrim_quest_beat_state
            WHERE quest_key = '{$questKeyEscaped}'
            ORDER BY fired_order NULLS LAST, beat_id
        ");

        $map = array();
        foreach ($rows as $row) {
            $map[$row['beat_id']] = array(
                'fired' => (($row['fired'] ?? false) === true || ($row['fired'] ?? '') === 't' || intval($row['fired'] ?? 0) === 1),
                'fired_order' => ($row['fired_order'] === null || $row['fired_order'] === '') ? null : intval($row['fired_order']),
                'fired_gamets' => ($row['fired_gamets'] === null || $row['fired_gamets'] === '') ? null : intval($row['fired_gamets']),
                'evidence_json' => chimQuestEngineJsonDecode($row['evidence_json'] ?? '{}', array()),
            );
        }

        return $map;
    }
}

if (!function_exists('chimQuestEngineNextBeatOrder')) {
    function chimQuestEngineNextBeatOrder($questKey)
    {
        $questKeyCn = chimQuestEngineNormalizeQuestKey($questKey);
        if ($questKeyCn === '' || !chimQuestEngineReady()) {
            return 1;
        }

        $questKeyEscaped = $GLOBALS["db"]->escape($questKeyCn);
        $row = $GLOBALS["db"]->fetchOne("
            SELECT COALESCE(MAX(fired_order), 0) + 1 as next_order
            FROM public.skyrim_quest_beat_state
            WHERE quest_key = '{$questKeyEscaped}'
        ");

        return max(1, intval($row['next_order'] ?? 1));
    }
}

if (!function_exists('chimQuestEngineMarkBeatFired')) {
    function chimQuestEngineMarkBeatFired($questKey, $beatId, $gamets, array $evidence)
    {
        if (!chimQuestEngineReady()) {
            return false;
        }

        $questKeyEscaped = $GLOBALS["db"]->escape(chimQuestEngineNormalizeQuestKey($questKey));
        $beatIdEscaped = $GLOBALS["db"]->escape((string)$beatId);
        $evidenceJson = $GLOBALS["db"]->escape(chimQuestEngineJsonEncode($evidence));
        $order = chimQuestEngineNextBeatOrder($questKey);
        $gametsSql = ($gamets === null || $gamets === '') ? 'NULL' : intval($gamets);

        $GLOBALS["db"]->execQuery("
            INSERT INTO public.skyrim_quest_beat_state
                (quest_key, beat_id, fired, fired_order, fired_gamets, evidence_json)
            VALUES
                ('{$questKeyEscaped}', '{$beatIdEscaped}', true, {$order}, {$gametsSql}, '{$evidenceJson}'::jsonb)
            ON CONFLICT (quest_key, beat_id) DO UPDATE SET
                fired = true,
                fired_order = COALESCE(public.skyrim_quest_beat_state.fired_order, EXCLUDED.fired_order),
                fired_gamets = COALESCE(public.skyrim_quest_beat_state.fired_gamets, EXCLUDED.fired_gamets),
                evidence_json = EXCLUDED.evidence_json,
                updated_at = now()
        ");

        return array(
            'fired' => true,
            'fired_order' => $order,
            'fired_gamets' => ($gametsSql === 'NULL') ? null : intval($gametsSql),
            'evidence_json' => $evidence,
        );
    }
}

if (!function_exists('chimQuestEngineBeatInferenceStage')) {
    function chimQuestEngineBeatInferenceStage(array $beat)
    {
        $action = $beat['action'] ?? array();
        $actionType = strtolower(trim((string)($action['type'] ?? '')));
        if (!in_array($actionType, array('set_stage', 'set_stage_cascade'), true)) {
            return null;
        }

        $stage = intval($action['stage'] ?? -1);
        return ($stage >= 0) ? $stage : null;
    }
}

if (!function_exists('chimQuestEngineIndexBeatsById')) {
    function chimQuestEngineIndexBeatsById(array $definition)
    {
        $index = array();
        foreach ($definition['beats'] ?? array() as $beat) {
            if (!is_array($beat)) {
                continue;
            }

            $beatId = trim((string)($beat['id'] ?? ''));
            if ($beatId !== '') {
                $index[$beatId] = $beat;
            }
        }

        return $index;
    }
}

if (!function_exists('chimQuestEngineBackfillBeatWithPrerequisites')) {
    function chimQuestEngineBackfillBeatWithPrerequisites($questKey, array $beat, array $beatIndex, array &$beatStateMap, $gamets, $currentStage, $sourceBeatId, array &$visiting)
    {
        $beatId = trim((string)($beat['id'] ?? ''));
        if ($beatId === '' || !empty($beatStateMap[$beatId]['fired']) || !empty($visiting[$beatId])) {
            return;
        }

        $visiting[$beatId] = true;

        foreach ($beat['prerequisites'] ?? array() as $prereqBeatId) {
            $prereqIdCn = trim((string)$prereqBeatId);
            if ($prereqIdCn === '' || !isset($beatIndex[$prereqIdCn])) {
                continue;
            }

            chimQuestEngineBackfillBeatWithPrerequisites(
                $questKey,
                $beatIndex[$prereqIdCn],
                $beatIndex,
                $beatStateMap,
                $gamets,
                $currentStage,
                $sourceBeatId,
                $visiting
            );
        }

        $beatStateMap[$beatId] = chimQuestEngineMarkBeatFired($questKey, $beatId, $gamets, array(
            'event_type' => 'state_backfill',
            'inferred_from_stage' => $currentStage,
            'source_beat_id' => $sourceBeatId,
        ));
    }
}

if (!function_exists('chimQuestEngineRehydrateBeatStateFromStage')) {
    function chimQuestEngineRehydrateBeatStateFromStage(array $definition, array &$instance, array &$beatStateMap, $eventType, array $payload)
    {
        $currentStage = $instance['state_json']['current_stage'] ?? null;
        if ($currentStage === null || $currentStage === '') {
            return;
        }

        $currentStage = intval($currentStage);
        $beatIndex = chimQuestEngineIndexBeatsById($definition);
        $gamets = isset($payload['gamets']) ? intval($payload['gamets']) : null;

        foreach ($definition['beats'] ?? array() as $beat) {
            if (!is_array($beat)) {
                continue;
            }

            $beatId = trim((string)($beat['id'] ?? ''));
            if ($beatId === '' || !empty($beatStateMap[$beatId]['fired'])) {
                continue;
            }

            $inferenceStage = chimQuestEngineBeatInferenceStage($beat);
            if ($inferenceStage === null || $currentStage < $inferenceStage) {
                continue;
            }

            if (!chimQuestEngineConditionsMet($beat['conditions'] ?? array(), $instance['state_json'], $definition)) {
                continue;
            }

            $visiting = array();
            chimQuestEngineBackfillBeatWithPrerequisites(
                $definition['quest_key'],
                $beat,
                $beatIndex,
                $beatStateMap,
                $gamets,
                $currentStage,
                $beatId,
                $visiting
            );
        }
    }
}

if (!function_exists('chimQuestEngineInsertEvent')) {
    function chimQuestEngineInsertEvent($questKey, $eventType, array $payload)
    {
        if (!chimQuestEngineReady()) {
            return false;
        }

        $questKeyCn = chimQuestEngineNormalizeQuestKey($questKey);
        $questKeySql = ($questKeyCn === '') ? 'NULL' : "'" . $GLOBALS["db"]->escape($questKeyCn) . "'";
        $eventTypeEscaped = $GLOBALS["db"]->escape((string)$eventType);
        $eventSourceEscaped = $GLOBALS["db"]->escape((string)($payload['event_source'] ?? 'chim'));
        $npcNameEscaped = $GLOBALS["db"]->escape((string)($payload['npc_name'] ?? ''));
        $locationEscaped = $GLOBALS["db"]->escape((string)($payload['location_name'] ?? ($payload['location'] ?? '')));
        $gametsSql = isset($payload['gamets']) && $payload['gamets'] !== '' ? intval($payload['gamets']) : 'NULL';
        $payloadJson = $GLOBALS["db"]->escape(chimQuestEngineJsonEncode($payload));

        $GLOBALS["db"]->execQuery("
            INSERT INTO public.skyrim_quest_events
                (quest_key, event_type, event_source, npc_name, location_name, gamets, payload_json)
            VALUES
                ({$questKeySql}, '{$eventTypeEscaped}', '{$eventSourceEscaped}', '{$npcNameEscaped}', '{$locationEscaped}', {$gametsSql}, '{$payloadJson}'::jsonb)
        ");

        return true;
    }
}

if (!function_exists('chimQuestEngineQueueAction')) {
    function chimQuestEngineQueueAction($questKey, $beatId, $actionType, array $payload, $gamets = null)
    {
        if (!chimQuestEngineReady()) {
            return false;
        }

        $questKeyEscaped = $GLOBALS["db"]->escape(chimQuestEngineNormalizeQuestKey($questKey));
        $beatIdSql = ($beatId === null || $beatId === '') ? 'NULL' : "'" . $GLOBALS["db"]->escape((string)$beatId) . "'";
        $actionTypeEscaped = $GLOBALS["db"]->escape((string)$actionType);
        $actionGamets = chimQuestEngineNormalizeGamets($gamets ?? ($payload['gamets'] ?? null));
        $actionGametsSql = ($actionGamets === null) ? 'NULL' : intval($actionGamets);
        $payloadJson = $GLOBALS["db"]->escape(chimQuestEngineJsonEncode($payload));

        $GLOBALS["db"]->execQuery("
            INSERT INTO public.skyrim_quest_action_outbox
                (quest_key, beat_id, action_type, action_gamets, payload_json, status, result_json)
            VALUES
                ('{$questKeyEscaped}', {$beatIdSql}, '{$actionTypeEscaped}', {$actionGametsSql}, '{$payloadJson}'::jsonb, 'pending', '{}'::jsonb)
        ");

        return true;
    }
}

if (!function_exists('chimQuestEngineBeatPrerequisitesMet')) {
    function chimQuestEngineBeatPrerequisitesMet(array $beat, array $beatStateMap)
    {
        $prerequisites = $beat['prerequisites'] ?? array();
        if (!is_array($prerequisites) || empty($prerequisites)) {
            return true;
        }

        foreach ($prerequisites as $requiredBeatId) {
            if (!isset($beatStateMap[$requiredBeatId]) || empty($beatStateMap[$requiredBeatId]['fired'])) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('chimQuestEngineConditionSatisfied')) {
    function chimQuestEngineConditionSatisfied(array $condition, array $state, array $definition)
    {
        $type = strtolower(trim((string)($condition['type'] ?? '')));
        if ($type === '') {
            return true;
        }

        if ($type === 'actor_alive') {
            $formKey = chimQuestEngineFormKey($condition['plugin'] ?? '', $condition['form_id'] ?? '');
            return ($formKey !== '' && empty($state['dead_actors'][$formKey]));
        }

        if ($type === 'actor_dead') {
            $formKey = chimQuestEngineFormKey($condition['plugin'] ?? '', $condition['form_id'] ?? '');
            return ($formKey !== '' && !empty($state['dead_actors'][$formKey]));
        }

        if ($type === 'stage_done') {
            $stage = intval($condition['stage'] ?? -1);
            return ($stage >= 0 && $state['current_stage'] !== null && intval($state['current_stage']) >= $stage);
        }

        if ($type === 'stage_not_done') {
            $stage = intval($condition['stage'] ?? -1);
            return ($stage >= 0 && ($state['current_stage'] === null || intval($state['current_stage']) < $stage));
        }

        if ($type === 'quest_stage') {
            $stage = intval($condition['min_stage'] ?? $condition['stage'] ?? -1);
            return ($stage >= 0 && $state['current_stage'] !== null && intval($state['current_stage']) >= $stage);
        }

        return true;
    }
}

if (!function_exists('chimQuestEngineConditionsMet')) {
    function chimQuestEngineConditionsMet($conditions, array $state, array $definition)
    {
        if (!is_array($conditions) || empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (is_array($condition) && !chimQuestEngineConditionSatisfied($condition, $state, $definition)) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('chimQuestEngineRequiredItemMet')) {
    function chimQuestEngineRequiredItemMet(array $beat, array $state)
    {
        if (!isset($beat['required_item']) || !is_array($beat['required_item'])) {
            return true;
        }

        $formKey = chimQuestEngineFormKey($beat['required_item']['plugin'] ?? '', $beat['required_item']['form_id'] ?? '');
        if ($formKey === '') {
            return true;
        }

        return intval($state['has_items'][$formKey] ?? 0) > 0;
    }
}

if (!function_exists('chimQuestEngineIsDialogueTriggerType')) {
    function chimQuestEngineIsDialogueTriggerType($triggerType)
    {
        $typeCn = strtolower(trim((string)$triggerType));
        return in_array($typeCn, array('dialogue', 'dialogue_intent'), true);
    }
}

if (!function_exists('chimQuestEngineBeatHasDialogueTrigger')) {
    function chimQuestEngineBeatHasDialogueTrigger(array $beat)
    {
        foreach ($beat['triggers'] ?? array() as $trigger) {
            if (is_array($trigger) && chimQuestEngineIsDialogueTriggerType($trigger['type'] ?? '')) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('chimQuestEngineLimitText')) {
    function chimQuestEngineLimitText($text, $maxLength = 220)
    {
        $textCn = trim(preg_replace('/\s+/', ' ', (string)$text));
        $maxLengthCn = max(16, intval($maxLength));
        if ($textCn === '' || strlen($textCn) <= $maxLengthCn) {
            return $textCn;
        }

        return rtrim(substr($textCn, 0, $maxLengthCn - 3)) . '...';
    }
}

if (!function_exists('chimQuestEngineIntentRuleLabel')) {
    function chimQuestEngineIntentRuleLabel($ruleKey)
    {
        $ruleCn = strtolower(trim((string)$ruleKey));
        $known = array(
            'requires_explicit_commitment' => 'Requires an explicit commitment to help or do the task.',
            'requires_explicit_acceptance' => 'Requires an explicit acceptance of the quest or request.',
            'requires_explicit_delivery' => 'Requires clearly returning, handing over, or presenting the quest item.',
            'requires_explicit_report' => 'Requires clearly reporting success, completion, or the outcome.',
        );
        if (isset($known[$ruleCn])) {
            return $known[$ruleCn];
        }
        if (strpos($ruleCn, 'requires_explicit_') === 0) {
            $label = str_replace('_', ' ', substr($ruleCn, strlen('requires_explicit_')));
            return 'Requires explicit ' . trim($label) . '.';
        }

        return '';
    }
}

if (!function_exists('chimQuestEngineCollectDialogueIntentMetadata')) {
    function chimQuestEngineCollectDialogueIntentMetadata(array $beat)
    {
        $examplesYes = array();
        $examplesNo = array();
        $rules = array();
        $intentLabels = array();

        foreach ($beat['triggers'] ?? array() as $trigger) {
            if (!is_array($trigger)) {
                continue;
            }

            $typeCn = strtolower(trim((string)($trigger['type'] ?? '')));
            if ($typeCn === 'dialogue') {
                foreach ($trigger['keywords'] ?? array() as $keyword) {
                    $keywordCn = chimQuestEngineLimitText($keyword, 80);
                    if ($keywordCn !== '') {
                        $examplesYes[$keywordCn] = true;
                    }
                }
                continue;
            }

            if ($typeCn !== 'dialogue_intent') {
                continue;
            }

            $intentCn = chimQuestEngineLimitText($trigger['intent'] ?? '', 80);
            if ($intentCn !== '') {
                $intentLabels[$intentCn] = true;
            }

            foreach ($trigger['examples_yes'] ?? array() as $example) {
                $exampleCn = chimQuestEngineLimitText($example, 80);
                if ($exampleCn !== '') {
                    $examplesYes[$exampleCn] = true;
                }
            }

            foreach ($trigger['examples_no'] ?? array() as $example) {
                $exampleCn = chimQuestEngineLimitText($example, 80);
                if ($exampleCn !== '') {
                    $examplesNo[$exampleCn] = true;
                }
            }

            foreach ($trigger as $key => $value) {
                if (!is_bool($value) || !$value) {
                    continue;
                }
                $ruleLabel = chimQuestEngineIntentRuleLabel($key);
                if ($ruleLabel !== '') {
                    $rules[$ruleLabel] = true;
                }
            }
        }

        return array(
            'examples_yes' => array_slice(array_keys($examplesYes), 0, 10),
            'examples_no' => array_slice(array_keys($examplesNo), 0, 6),
            'rules' => array_values(array_keys($rules)),
            'intent_labels' => array_values(array_keys($intentLabels)),
        );
    }
}

if (!function_exists('chimQuestEngineBeatIntentSummary')) {
    function chimQuestEngineBeatIntentSummary(array $beat)
    {
        $summaryCn = chimQuestEngineLimitText($beat['intent_summary'] ?? '', 220);
        if ($summaryCn !== '') {
            return $summaryCn;
        }

        $summaryCn = chimQuestEngineLimitText($beat['comment'] ?? '', 220);
        if ($summaryCn !== '') {
            return $summaryCn;
        }

        $beatId = trim((string)($beat['id'] ?? ''));
        if ($beatId !== '') {
            return "Quest beat {$beatId}.";
        }

        return 'Quest dialogue beat.';
    }
}

if (!function_exists('chimQuestEngineBeatIntentThreshold')) {
    function chimQuestEngineBeatIntentThreshold(array $beat)
    {
        if (isset($beat['intent_min_confidence'])) {
            return max(0.0, min(1.0, floatval($beat['intent_min_confidence'])));
        }

        foreach ($beat['triggers'] ?? array() as $trigger) {
            if (!is_array($trigger)) {
                continue;
            }
            if (strtolower(trim((string)($trigger['type'] ?? ''))) !== 'dialogue_intent') {
                continue;
            }
            if (isset($trigger['min_confidence'])) {
                return max(0.0, min(1.0, floatval($trigger['min_confidence'])));
            }
        }

        return 0.80;
    }
}

if (!function_exists('chimQuestEngineBuildDialogueIntentCandidates')) {
    function chimQuestEngineBuildDialogueIntentCandidates(array $definition, array $instance, array $beatStateMap, array $payload)
    {
        $npcNameCn = trim((string)($payload['npc_name'] ?? ''));
        $candidates = array();

        foreach ($definition['beats'] ?? array() as $beat) {
            if (!is_array($beat)) {
                continue;
            }

            $beatId = trim((string)($beat['id'] ?? ''));
            if ($beatId === '' || !empty($beatStateMap[$beatId]['fired'])) {
                continue;
            }

            $focusNpc = trim((string)($beat['focus_npc'] ?? ''));
            if ($focusNpc !== '' && $npcNameCn !== '' && strcasecmp($focusNpc, $npcNameCn) !== 0) {
                continue;
            }

            if (!chimQuestEngineBeatPrerequisitesMet($beat, $beatStateMap)) {
                continue;
            }
            if (!chimQuestEngineConditionsMet($beat['conditions'] ?? array(), $instance['state_json'], $definition)) {
                continue;
            }
            if (!chimQuestEngineRequiredItemMet($beat, $instance['state_json'])) {
                continue;
            }

            $triggerMode = strtolower(trim((string)($beat['trigger_mode'] ?? 'any')));
            if ($triggerMode === '') {
                $triggerMode = 'any';
            }

            $dialogueTriggerCount = 0;
            $nonDialogueSatisfied = true;
            foreach ($beat['triggers'] ?? array() as $trigger) {
                if (!is_array($trigger)) {
                    continue;
                }

                $triggerType = $trigger['type'] ?? '';
                if (chimQuestEngineIsDialogueTriggerType($triggerType)) {
                    $dialogueTriggerCount++;
                    continue;
                }

                if ($triggerMode === 'all' && !chimQuestEngineTriggerStateSatisfied($trigger, $instance['state_json'], $definition)) {
                    $nonDialogueSatisfied = false;
                    break;
                }
            }

            if ($dialogueTriggerCount <= 0) {
                continue;
            }
            if ($triggerMode === 'all' && (!$nonDialogueSatisfied || $dialogueTriggerCount > 1)) {
                continue;
            }

            $metadata = chimQuestEngineCollectDialogueIntentMetadata($beat);
            $candidate = array(
                'beat' => $beat,
                'summary' => chimQuestEngineBeatIntentSummary($beat),
                'threshold' => chimQuestEngineBeatIntentThreshold($beat),
                'examples_yes' => $metadata['examples_yes'],
                'examples_no' => $metadata['examples_no'],
                'rules' => $metadata['rules'],
                'intent_labels' => $metadata['intent_labels'],
            );

            if (isset($beat['required_item']) && is_array($beat['required_item'])) {
                $candidate['required_item'] = array(
                    'name' => chimQuestEngineLimitText($beat['required_item']['name'] ?? '', 80),
                    'plugin' => $beat['required_item']['plugin'] ?? '',
                    'form_id' => $beat['required_item']['form_id'] ?? '',
                );
            }

            $candidates[$beatId] = $candidate;
        }

        return $candidates;
    }
}

if (!function_exists('chimQuestEnginePushConnectorOverrides')) {
    function chimQuestEnginePushConnectorOverrides($driverName, array $overrides)
    {
        $driverCn = trim((string)$driverName);
        if ($driverCn === '') {
            return array();
        }
        if (!isset($GLOBALS["CONNECTOR"][$driverCn]) || !is_array($GLOBALS["CONNECTOR"][$driverCn])) {
            $GLOBALS["CONNECTOR"][$driverCn] = array();
        }

        $snapshot = array();
        foreach ($overrides as $key => $value) {
            $hasKey = array_key_exists($key, $GLOBALS["CONNECTOR"][$driverCn]);
            $snapshot[$key] = array(
                'had_key' => $hasKey,
                'value' => $hasKey ? $GLOBALS["CONNECTOR"][$driverCn][$key] : null,
            );
            $GLOBALS["CONNECTOR"][$driverCn][$key] = $value;
        }

        return $snapshot;
    }
}

if (!function_exists('chimQuestEnginePopConnectorOverrides')) {
    function chimQuestEnginePopConnectorOverrides($driverName, array $snapshot)
    {
        $driverCn = trim((string)$driverName);
        if ($driverCn === '' || !isset($GLOBALS["CONNECTOR"][$driverCn]) || !is_array($GLOBALS["CONNECTOR"][$driverCn])) {
            return;
        }

        foreach ($snapshot as $key => $state) {
            if (!is_array($state) || empty($state['had_key'])) {
                unset($GLOBALS["CONNECTOR"][$driverCn][$key]);
                continue;
            }

            $GLOBALS["CONNECTOR"][$driverCn][$key] = $state['value'];
        }
    }
}

if (!function_exists('chimQuestEngineBuildDialogueIntentMessages')) {
    function chimQuestEngineBuildDialogueIntentMessages(array $definition, array $instance, array $payload, array $candidates)
    {
        $questTitle = trim((string)($definition['title'] ?? $definition['quest_editor_id'] ?? $definition['quest_key'] ?? 'Quest'));
        $questEditorId = trim((string)($definition['quest_editor_id'] ?? $definition['quest_key'] ?? ''));
        $playerText = chimQuestEngineLimitText(trim((string)($payload['player_text'] ?? '')), 280);
        $playerTextNormalized = chimQuestEngineLimitText(chimQuestEngineNormalizeText($playerText), 280);
        $npcText = chimQuestEngineLimitText(trim((string)($payload['npc_text'] ?? '')), 360);
        $npcName = chimQuestEngineLimitText(trim((string)($payload['npc_name'] ?? '')), 80);
        $locationName = chimQuestEngineLimitText(trim((string)($payload['location_name'] ?? ($payload['location'] ?? ''))), 80);
        $currentStage = ($instance['current_stage'] === null || $instance['current_stage'] === '') ? 'unknown' : strval(intval($instance['current_stage']));

        $systemPrompt = <<<'PROMPT'
You are a strict quest dialogue intent evaluator for Skyrim.
Choose at most one legal beat ID that the player's words clearly advance on this turn.
Use the player's words as the primary signal. The NPC reply is supporting context only.
Never invent beat IDs. If the intent is unclear, weak, or purely conversational, return null.

Be conservative:
- Asking for information is not the same as accepting a quest.
- Sympathy, speculation, or generic discussion is not acceptance or completion.
- Mentioning an item is not a valid hand-in unless the player is clearly returning, presenting, or reporting it.
- Prefer null over a weak guess.

Reply with JSON only in this format:
{"selected_beat_id": null, "confidence": 0.0, "reason": "brief reason"}
PROMPT;

        $userPrompt = "Quest: {$questTitle}";
        if ($questEditorId !== '') {
            $userPrompt .= " ({$questEditorId})";
        }
        $userPrompt .= "\nCurrent stage: {$currentStage}";
        if ($locationName !== '') {
            $userPrompt .= "\nLocation: {$locationName}";
        }
        if ($npcName !== '') {
            $userPrompt .= "\nNPC: {$npcName}";
        }
        $userPrompt .= "\n\nPlayer line (raw): {$playerText}";
        if ($playerTextNormalized !== '' && $playerTextNormalized !== strtolower($playerText)) {
            $userPrompt .= "\nPlayer line (normalized): {$playerTextNormalized}";
        }
        $userPrompt .= "\nNPC reply: " . ($npcText !== '' ? $npcText : '[empty]');
        $userPrompt .= "\n\nLegal candidate beats:\n";

        foreach ($candidates as $beatId => $candidate) {
            $userPrompt .= "\n- beat_id: {$beatId}\n";
            $userPrompt .= "  summary: " . ($candidate['summary'] ?? 'Quest dialogue beat.') . "\n";
            if (!empty($candidate['intent_labels'])) {
                $userPrompt .= "  intent labels: " . implode('; ', $candidate['intent_labels']) . "\n";
            }
            if (!empty($candidate['rules'])) {
                $userPrompt .= "  rules: " . implode(' ', $candidate['rules']) . "\n";
            }
            if (!empty($candidate['required_item'])) {
                $requiredName = trim((string)($candidate['required_item']['name'] ?? 'required quest item'));
                if ($requiredName === '') {
                    $requiredName = 'required quest item';
                }
                $userPrompt .= "  item gate: already satisfied for {$requiredName}\n";
            }
            if (!empty($candidate['examples_yes'])) {
                $userPrompt .= "  clear positive examples: " . implode(' | ', $candidate['examples_yes']) . "\n";
            }
            if (!empty($candidate['examples_no'])) {
                $userPrompt .= "  clear negative examples: " . implode(' | ', $candidate['examples_no']) . "\n";
            }
        }

        return array(
            array('role' => 'system', 'content' => $systemPrompt),
            array('role' => 'user', 'content' => $userPrompt),
        );
    }
}

if (!function_exists('chimQuestEngineParseDialogueIntentResponse')) {
    function chimQuestEngineParseDialogueIntentResponse($response, array $candidates)
    {
        if (!is_string($response) || trim($response) === '') {
            return null;
        }

        $jsonResponse = trim($response);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $jsonResponse, $matches)) {
            $jsonResponse = trim($matches[1]);
        }

        $jsonResponse = str_replace(
            array("\xe2\x80\x9c", "\xe2\x80\x9d", "\xe2\x80\x98", "\xe2\x80\x99", '*'),
            array('"', '"', "'", "'", ''),
            $jsonResponse
        );
        $jsonResponse = preg_replace('/[\x00-\x1F\x7F]/u', '', $jsonResponse);

        $parsed = json_decode($jsonResponse, true);
        if (!is_array($parsed) && preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $cleanJson = str_replace(
                array("\xe2\x80\x9c", "\xe2\x80\x9d", "\xe2\x80\x98", "\xe2\x80\x99", '*'),
                array('"', '"', "'", "'", ''),
                $matches[0]
            );
            $cleanJson = preg_replace('/[\x00-\x1F\x7F]/u', '', $cleanJson);
            $parsed = json_decode($cleanJson, true);
        }

        if (!is_array($parsed)) {
            return null;
        }

        $selectedBeatId = trim((string)($parsed['selected_beat_id'] ?? ($parsed['beat_id'] ?? '')));
        if (in_array(strtolower($selectedBeatId), array('', 'null', 'none', 'no_match', 'nomatch'), true)) {
            $selectedBeatId = '';
        }

        $mappedBeatId = $selectedBeatId === '' ? '' : (chimQuestEngineFindCaseInsensitiveKey($candidates, $selectedBeatId) ?? '');

        $confidence = $parsed['confidence'] ?? 0.0;
        if (is_numeric($confidence)) {
            $confidence = floatval($confidence);
            if ($confidence > 1.0 && $confidence <= 100.0) {
                $confidence = $confidence / 100.0;
            }
        } else {
            $confidence = 0.0;
        }
        $confidence = max(0.0, min(1.0, floatval($confidence)));

        return array(
            'selected_beat_id' => $mappedBeatId,
            'confidence' => $confidence,
            'reason' => chimQuestEngineLimitText($parsed['reason'] ?? ($parsed['rationale'] ?? ''), 180),
            'raw' => $parsed,
        );
    }
}

if (!function_exists('chimQuestEngineSelectDialogueBeatByIntent')) {
    function chimQuestEngineSelectDialogueBeatByIntent(array $definition, array $instance, array $beatStateMap, array $payload)
    {
        $playerTextCn = trim((string)($payload['player_text'] ?? ''));
        if ($playerTextCn === '') {
            return null;
        }

        $candidates = chimQuestEngineBuildDialogueIntentCandidates($definition, $instance, $beatStateMap, $payload);
        if (empty($candidates)) {
            return null;
        }

        $driverName = trim((string)($GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]["driver"] ?? ($GLOBALS["CURRENT_CONNECTOR"] ?? '')));
        if ($driverName === '') {
            chimQuestEngineLog('debug', 'Skipping quest intent fallback: no active connector driver');
            return null;
        }

        if ((!isset($GLOBALS["CONNECTOR"][$driverName]) || !is_array($GLOBALS["CONNECTOR"][$driverName])) &&
            isset($GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]) &&
            is_array($GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]) &&
            class_exists('LLMConnector')) {
            try {
                $connectorManager = new LLMConnector();
                $connectorManager->setOldGlobals($GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]);
            } catch (Throwable $e) {
                chimQuestEngineLog('warn', 'Could not hydrate current connector globals for quest intent fallback: ' . $e->getMessage());
                return null;
            }
        }

        $driverFile = ($GLOBALS["ENGINE_PATH"] ?? (__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR)) . "connector" . DIRECTORY_SEPARATOR . $driverName . ".php";
        if (!is_file($driverFile)) {
            chimQuestEngineLog('warn', 'Skipping quest intent fallback: connector driver file not found for ' . $driverName);
            return null;
        }

        require_once $driverFile;
        if (!class_exists($driverName)) {
            chimQuestEngineLog('warn', 'Skipping quest intent fallback: connector driver class not found for ' . $driverName);
            return null;
        }

        $driver = new $driverName();
        if (!method_exists($driver, 'fast_request')) {
            chimQuestEngineLog('debug', 'Skipping quest intent fallback: connector does not support fast_request for ' . $driverName);
            return null;
        }

        $messages = chimQuestEngineBuildDialogueIntentMessages($definition, $instance, $payload, $candidates);
        $snapshot = chimQuestEnginePushConnectorOverrides($driverName, array(
            'temperature' => 0.1,
            'presence_penalty' => 0.0,
            'frequency_penalty' => 0.0,
            'repetition_penalty' => 1.0,
            'reasoning_model' => false,
        ));

        try {
            $response = $driver->fast_request($messages, array('MAX_TOKENS' => 220), 'quest_intent');
        } catch (Throwable $e) {
            chimQuestEnginePopConnectorOverrides($driverName, $snapshot);
            chimQuestEngineLog('warn', 'Quest intent fallback request failed: ' . $e->getMessage());
            return null;
        }

        chimQuestEnginePopConnectorOverrides($driverName, $snapshot);
        $selection = chimQuestEngineParseDialogueIntentResponse($response, $candidates);
        if (!is_array($selection) || empty($selection['selected_beat_id'])) {
            chimQuestEngineLog('debug', 'Quest intent fallback returned no beat for ' . ($definition['quest_key'] ?? 'unknown_quest'));
            return null;
        }

        $beatId = $selection['selected_beat_id'];
        $candidate = $candidates[$beatId] ?? null;
        if (!is_array($candidate)) {
            return null;
        }

        $threshold = floatval($candidate['threshold'] ?? 0.80);
        if (floatval($selection['confidence'] ?? 0.0) + 0.0001 < $threshold) {
            chimQuestEngineLog(
                'debug',
                'Quest intent fallback rejected ' . $beatId . ' for ' . ($definition['quest_key'] ?? 'unknown_quest') .
                ' at confidence ' . number_format(floatval($selection['confidence'] ?? 0.0), 2) .
                ' below threshold ' . number_format($threshold, 2)
            );
            return null;
        }

        chimQuestEngineLog(
            'info',
            'Quest intent fallback selected ' . $beatId . ' for ' . ($definition['quest_key'] ?? 'unknown_quest') .
            ' at confidence ' . number_format(floatval($selection['confidence'] ?? 0.0), 2) .
            ($selection['reason'] !== '' ? ' (' . $selection['reason'] . ')' : '')
        );

        $selection['beat'] = $candidate['beat'];
        $selection['threshold'] = $threshold;
        return $selection;
    }
}

if (!function_exists('chimQuestEngineQuestMatchesPayload')) {
    function chimQuestEngineQuestMatchesPayload(array $definition, array $payload)
    {
        $payloadEditorId = strtolower(trim((string)($payload['quest_editor_id'] ?? '')));
        $definitionEditorId = strtolower(trim((string)($definition['quest_editor_id'] ?? '')));
        if ($payloadEditorId !== '' && $payloadEditorId === $definitionEditorId) {
            return true;
        }

        $payloadFormKey = chimQuestEngineFormKey(
            $payload['quest_plugin'] ?? ($payload['plugin'] ?? ''),
            $payload['quest_form_id'] ?? ($payload['form_id'] ?? '')
        );
        $definitionFormKey = chimQuestEngineFormKey($definition['quest_plugin'] ?? '', $definition['quest_form_id'] ?? '');

        if ($payloadFormKey !== '' && $definitionFormKey !== '' && $payloadFormKey === $definitionFormKey) {
            return true;
        }

        return false;
    }
}

if (!function_exists('chimQuestEngineKeywordMatchCount')) {
    function chimQuestEngineKeywordMatchCount($text, $keywords)
    {
        if (!is_array($keywords) || empty($keywords)) {
            return 0;
        }

        $textCn = chimQuestEngineNormalizeText($text);
        if ($textCn === '') {
            return 0;
        }

        $count = 0;
        foreach ($keywords as $keyword) {
            $keywordCn = chimQuestEngineNormalizeText($keyword);
            if ($keywordCn === '') {
                continue;
            }
            if (strpos($textCn, $keywordCn) !== false) {
                $count++;
            }
        }

        return $count;
    }
}

if (!function_exists('chimQuestEngineTriggerStateSatisfied')) {
    function chimQuestEngineTriggerStateSatisfied(array $trigger, array $state, array $definition)
    {
        $type = strtolower(trim((string)($trigger['type'] ?? '')));
        if ($type === 'quest_stage') {
            if (!chimQuestEngineQuestMatchesPayload($definition, $trigger + array(
                'quest_plugin' => $trigger['quest_plugin'] ?? '',
                'quest_form_id' => $trigger['quest_form_id'] ?? '',
            ))) {
                return false;
            }
            $minStage = intval($trigger['min_stage'] ?? $trigger['stage'] ?? -1);
            return ($minStage >= 0 && $state['current_stage'] !== null && intval($state['current_stage']) >= $minStage);
        }

        if ($type === 'actor_dead') {
            $formKey = chimQuestEngineFormKey($trigger['plugin'] ?? '', $trigger['form_id'] ?? '');
            return ($formKey !== '' && !empty($state['dead_actors'][$formKey]));
        }

        if ($type === 'location_entered') {
            $formKey = chimQuestEngineFormKey($trigger['plugin'] ?? '', $trigger['form_id'] ?? '');
            return ($formKey !== '' && !empty($state['entered_locations'][$formKey]));
        }

        if ($type === 'item_acquired') {
            $formKey = chimQuestEngineFormKey($trigger['plugin'] ?? '', $trigger['form_id'] ?? '');
            return ($formKey !== '' && intval($state['has_items'][$formKey] ?? 0) > 0);
        }

        return false;
    }
}

if (!function_exists('chimQuestEngineTriggerMatchesEvent')) {
    function chimQuestEngineTriggerMatchesEvent(array $trigger, $eventType, array $payload, array $state, array $definition, array $beat)
    {
        $type = strtolower(trim((string)($trigger['type'] ?? '')));
        $eventTypeCn = strtolower(trim((string)$eventType));

        if ($type === 'dialogue') {
            if ($eventTypeCn !== 'dialogue_turn') {
                return false;
            }
            $focusNpc = trim((string)($beat['focus_npc'] ?? ''));
            $npcName = trim((string)($payload['npc_name'] ?? ''));
            if ($focusNpc !== '' && strcasecmp($focusNpc, $npcName) !== 0) {
                return false;
            }
            $matchCount = chimQuestEngineKeywordMatchCount($payload['player_text'] ?? '', $trigger['keywords'] ?? array());
            $minMatches = max(1, intval($trigger['min_matches'] ?? 1));
            return $matchCount >= $minMatches;
        }

        if ($type === 'quest_stage') {
            if ($eventTypeCn !== 'quest_stage') {
                return false;
            }
            if (!chimQuestEngineQuestMatchesPayload($definition, $payload)) {
                return false;
            }
            $minStage = intval($trigger['min_stage'] ?? $trigger['stage'] ?? -1);
            $currentStage = intval($payload['stage'] ?? -1);
            return ($minStage >= 0 && $currentStage >= $minStage);
        }

        if ($type === 'location_entered') {
            if ($eventTypeCn !== 'location_entered') {
                return false;
            }
            $expected = chimQuestEngineFormKey($trigger['plugin'] ?? '', $trigger['form_id'] ?? '');
            $actual = chimQuestEngineFormKey($payload['plugin'] ?? '', $payload['form_id'] ?? '');
            return ($expected !== '' && $expected === $actual);
        }

        if ($type === 'actor_dead') {
            if ($eventTypeCn !== 'actor_dead') {
                return false;
            }
            $expected = chimQuestEngineFormKey($trigger['plugin'] ?? '', $trigger['form_id'] ?? '');
            $actual = chimQuestEngineFormKey($payload['plugin'] ?? '', $payload['form_id'] ?? '');
            return ($expected !== '' && $expected === $actual);
        }

        if ($type === 'item_acquired') {
            if ($eventTypeCn !== 'item_acquired' && $eventTypeCn !== 'player_inventory_sync') {
                return false;
            }
            $expected = chimQuestEngineFormKey($trigger['plugin'] ?? '', $trigger['form_id'] ?? '');
            if ($eventTypeCn === 'player_inventory_sync') {
                return ($expected !== '' && intval($state['has_items'][$expected] ?? 0) > 0);
            }
            $actual = chimQuestEngineFormKey($payload['plugin'] ?? '', $payload['form_id'] ?? '');
            return ($expected !== '' && $expected === $actual);
        }

        return false;
    }
}

if (!function_exists('chimQuestEngineBeatShouldFire')) {
    function chimQuestEngineBeatShouldFire(array $beat, $eventType, array $payload, array $state, array $definition)
    {
        $triggers = $beat['triggers'] ?? array();
        if (!is_array($triggers) || empty($triggers)) {
            return false;
        }

        $triggerMode = strtolower(trim((string)($beat['trigger_mode'] ?? 'any')));
        if ($triggerMode === '') {
            $triggerMode = 'any';
        }

        $satisfiedCount = 0;
        $matchedEventCount = 0;
        foreach ($triggers as $trigger) {
            if (!is_array($trigger)) {
                continue;
            }
            $satisfied = chimQuestEngineTriggerStateSatisfied($trigger, $state, $definition);
            $matchedEvent = chimQuestEngineTriggerMatchesEvent($trigger, $eventType, $payload, $state, $definition, $beat);
            if ($satisfied || $matchedEvent) {
                $satisfiedCount++;
            }
            if ($matchedEvent) {
                $matchedEventCount++;
            }
        }

        if ($triggerMode === 'all') {
            return ($matchedEventCount > 0 && $satisfiedCount >= count($triggers));
        }

        return $matchedEventCount > 0;
    }
}

if (!function_exists('chimQuestEngineAdjustItemCount')) {
    function chimQuestEngineAdjustItemCount(array &$state, $plugin, $formId, $delta)
    {
        $formKey = chimQuestEngineFormKey($plugin, $formId);
        if ($formKey === '') {
            return;
        }

        $current = intval($state['has_items'][$formKey] ?? 0);
        $updated = $current + intval($delta);
        if ($updated <= 0) {
            unset($state['has_items'][$formKey]);
            return;
        }

        $state['has_items'][$formKey] = $updated;
    }
}

if (!function_exists('chimQuestEngineMutateStateForAction')) {
    function chimQuestEngineMutateStateForAction(array &$state, array $action)
    {
        $type = strtolower(trim((string)($action['type'] ?? '')));
        if ($type === 'remove_item') {
            chimQuestEngineAdjustItemCount($state, $action['plugin'] ?? '', $action['item_form_id'] ?? '', -intval($action['count'] ?? 1));
        } elseif ($type === 'add_item') {
            chimQuestEngineAdjustItemCount($state, $action['plugin'] ?? '', $action['item_form_id'] ?? '', intval($action['count'] ?? 1));
        } elseif ($type === 'stop_quest') {
            $state['run_state'] = 'completed';
        } elseif ($type === 'fail_all_objectives') {
            $state['run_state'] = 'failed';
        }
    }
}

if (!function_exists('chimQuestEngineQueueResolvedAction')) {
    function chimQuestEngineQueueResolvedAction(array $definition, $questKey, $beatId, array $action, array &$state, $sourceActionType = '', $gamets = null)
    {
        $actionType = strtolower(trim((string)($action['type'] ?? '')));
        if ($actionType === '') {
            return;
        }

        chimQuestEngineMutateStateForAction($state, $action);
        $payload = $action;
        $payload['quest_key'] = $questKey;
        $payload['quest_editor_id'] = $definition['quest_editor_id'] ?? $questKey;
        $payload['quest_plugin'] = $payload['quest_plugin'] ?? ($definition['quest_plugin'] ?? '');
        $payload['quest_form_id'] = $payload['quest_form_id'] ?? ($definition['quest_form_id'] ?? '');
        $payload['beat_id'] = $beatId;
        $actionGamets = chimQuestEngineNormalizeGamets($gamets);
        if ($actionGamets !== null) {
            $payload['gamets'] = $actionGamets;
        }
        if ($sourceActionType !== '') {
            $payload['source_action_type'] = $sourceActionType;
        }

        chimQuestEngineQueueAction($questKey, $beatId, $actionType, $payload, $actionGamets);
    }
}

if (!function_exists('chimQuestEngineFireBeat')) {
    function chimQuestEngineFireBeat(array $definition, array $beat, $eventType, array $payload, array &$instance, array &$beatStateMap, array &$firedBeats)
    {
        $questKey = $definition['quest_key'];
        $beatId = (string)($beat['id'] ?? '');
        if ($beatId === '') {
            return;
        }

        $gamets = isset($payload['gamets']) ? intval($payload['gamets']) : null;
        $evidence = array(
            'event_type' => $eventType,
            'npc_name' => $payload['npc_name'] ?? '',
            'player_text' => $payload['player_text'] ?? '',
            'npc_text' => $payload['npc_text'] ?? '',
            'location_name' => $payload['location_name'] ?? ($payload['location'] ?? ''),
        );
        if (!empty($payload['evaluation_mode'])) {
            $evidence['evaluation_mode'] = trim((string)$payload['evaluation_mode']);
        }
        if (isset($payload['intent_confidence'])) {
            $evidence['intent_confidence'] = max(0.0, min(1.0, floatval($payload['intent_confidence'])));
        }
        if (isset($payload['intent_threshold'])) {
            $evidence['intent_threshold'] = max(0.0, min(1.0, floatval($payload['intent_threshold'])));
        }
        if (!empty($payload['intent_reason'])) {
            $evidence['intent_reason'] = chimQuestEngineLimitText($payload['intent_reason'], 180);
        }
        if (!empty($payload['intent_selected_beat_id'])) {
            $evidence['intent_selected_beat_id'] = trim((string)$payload['intent_selected_beat_id']);
        }

        $beatStateMap[$beatId] = chimQuestEngineMarkBeatFired($questKey, $beatId, $gamets, $evidence);
        $firedBeats[] = $beatId;
        $instance['run_state'] = 'running';
        $instance['state_json']['run_state'] = 'running';

        $action = $beat['action'] ?? array();
        $actionType = strtolower(trim((string)($action['type'] ?? '')));
        if ($actionType === 'set_stage') {
            $stage = intval($action['stage'] ?? -1);
            if ($stage >= 0) {
                $instance['current_stage'] = $stage;
                $instance['state_json']['current_stage'] = $stage;
                chimQuestEngineQueueResolvedAction($definition, $questKey, $beatId, $action, $instance['state_json'], '', $gamets);
            }
        } elseif ($actionType === 'set_stage_cascade') {
            $targetStage = intval($action['stage'] ?? -1);
            $cascadeBeats = $definition['beats'] ?? array();
            $intermediate = array();
            foreach ($cascadeBeats as $candidateBeat) {
                if (!is_array($candidateBeat)) {
                    continue;
                }
                $candidateId = (string)($candidateBeat['id'] ?? '');
                if ($candidateId === '' || $candidateId === $beatId) {
                    continue;
                }
                if (!empty($beatStateMap[$candidateId]['fired'])) {
                    continue;
                }
                $candidateAction = $candidateBeat['action'] ?? array();
                if (strtolower(trim((string)($candidateAction['type'] ?? ''))) !== 'set_stage') {
                    continue;
                }
                $candidateStage = intval($candidateAction['stage'] ?? -1);
                if ($candidateStage >= 0 && $candidateStage < $targetStage) {
                    $intermediate[] = array(
                        'beat' => $candidateBeat,
                        'stage' => $candidateStage,
                    );
                }
            }

            usort($intermediate, function ($left, $right) {
                return intval($left['stage']) <=> intval($right['stage']);
            });

            foreach ($intermediate as $entry) {
                $candidateBeat = $entry['beat'];
                $candidateId = (string)($candidateBeat['id'] ?? '');
                if (!chimQuestEngineConditionsMet($candidateBeat['conditions'] ?? array(), $instance['state_json'], $definition)) {
                    continue;
                }
                if (!chimQuestEngineRequiredItemMet($candidateBeat, $instance['state_json'])) {
                    continue;
                }
                $beatStateMap[$candidateId] = chimQuestEngineMarkBeatFired($questKey, $candidateId, $gamets, array(
                    'event_type' => 'cascade',
                    'triggered_by' => $beatId,
                ));
                $firedBeats[] = $candidateId;
                $candidateAction = $candidateBeat['action'] ?? array();
                $candidateStage = intval($candidateAction['stage'] ?? -1);
                if ($candidateStage >= 0) {
                    $instance['current_stage'] = $candidateStage;
                    $instance['state_json']['current_stage'] = $candidateStage;
                }
                chimQuestEngineQueueResolvedAction($definition, $questKey, $candidateId, $candidateAction, $instance['state_json'], 'set_stage_cascade_intermediate', $gamets);
                foreach ($candidateBeat['downstream'] ?? array() as $downstreamAction) {
                    if (!is_array($downstreamAction)) {
                        continue;
                    }
                    if (!chimQuestEngineConditionsMet($downstreamAction['conditions'] ?? array(), $instance['state_json'], $definition)) {
                        continue;
                    }
                    chimQuestEngineQueueResolvedAction($definition, $questKey, $candidateId, $downstreamAction, $instance['state_json'], '', $gamets);
                }
            }

            if ($targetStage >= 0) {
                $instance['current_stage'] = $targetStage;
                $instance['state_json']['current_stage'] = $targetStage;
                $resolvedAction = $action;
                $resolvedAction['type'] = 'set_stage';
                chimQuestEngineQueueResolvedAction($definition, $questKey, $beatId, $resolvedAction, $instance['state_json'], 'set_stage_cascade', $gamets);
            }
        } elseif ($actionType !== '' && $actionType !== 'gate') {
            chimQuestEngineQueueResolvedAction($definition, $questKey, $beatId, $action, $instance['state_json'], '', $gamets);
        }

        foreach ($beat['downstream'] ?? array() as $downstreamAction) {
            if (!is_array($downstreamAction)) {
                continue;
            }
            if (!chimQuestEngineConditionsMet($downstreamAction['conditions'] ?? array(), $instance['state_json'], $definition)) {
                continue;
            }
            chimQuestEngineQueueResolvedAction($definition, $questKey, $beatId, $downstreamAction, $instance['state_json'], '', $gamets);
        }
    }
}

if (!function_exists('chimQuestEngineApplyEventToState')) {
    function chimQuestEngineApplyEventToState(array $definition, $eventType, array $payload, array &$state)
    {
        $eventTypeCn = strtolower(trim((string)$eventType));

        if ($eventTypeCn === 'player_inventory_sync') {
            $state['has_items'] = array();
            foreach ($payload['items'] ?? array() as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $plugin = $item['plugin'] ?? ($item['source_plugin'] ?? 'Skyrim.esm');
                $formKey = chimQuestEngineFormKey($plugin, $item['baseid'] ?? ($item['form_id'] ?? ''));
                if ($formKey === '') {
                    continue;
                }
                $count = intval($item['count'] ?? 0);
                if ($count > 0) {
                    $state['has_items'][$formKey] = $count;
                }
            }
            return;
        }

        if ($eventTypeCn === 'item_acquired') {
            chimQuestEngineAdjustItemCount($state, $payload['plugin'] ?? 'Skyrim.esm', $payload['form_id'] ?? '', intval($payload['count'] ?? 1));
            return;
        }

        if ($eventTypeCn === 'item_removed') {
            chimQuestEngineAdjustItemCount($state, $payload['plugin'] ?? 'Skyrim.esm', $payload['form_id'] ?? '', -intval($payload['count'] ?? 1));
            return;
        }

        if ($eventTypeCn === 'actor_dead') {
            $formKey = chimQuestEngineFormKey($payload['plugin'] ?? '', $payload['form_id'] ?? '');
            if ($formKey !== '') {
                $state['dead_actors'][$formKey] = true;
            }
            return;
        }

        if ($eventTypeCn === 'location_entered') {
            $formKey = chimQuestEngineFormKey($payload['plugin'] ?? '', $payload['form_id'] ?? '');
            if ($formKey !== '') {
                $state['entered_locations'][$formKey] = intval($payload['gamets'] ?? 0);
            }
            return;
        }

        if ($eventTypeCn === 'quest_stage' && chimQuestEngineQuestMatchesPayload($definition, $payload)) {
            $stage = intval($payload['stage'] ?? -1);
            if ($stage >= 0) {
                $state['current_stage'] = $stage;
            }
            return;
        }

        if ($eventTypeCn === 'dialogue_turn') {
            $npcName = trim((string)($payload['npc_name'] ?? ''));
            if ($npcName !== '') {
                $state['last_dialogue'][$npcName] = array(
                    'player_text' => trim((string)($payload['player_text'] ?? '')),
                    'npc_text' => trim((string)($payload['npc_text'] ?? '')),
                    'gamets' => intval($payload['gamets'] ?? 0),
                );
            }
        }
    }
}

if (!function_exists('chimQuestEngineApplyBeatToStateOnly')) {
    function chimQuestEngineApplyBeatToStateOnly(array $definition, array $beat, array &$state)
    {
        $action = $beat['action'] ?? array();
        if (is_array($action)) {
            $actionType = strtolower(trim((string)($action['type'] ?? '')));
            if ($actionType === 'set_stage' || $actionType === 'set_stage_cascade') {
                $stage = intval($action['stage'] ?? -1);
                if ($stage >= 0) {
                    $state['current_stage'] = $stage;
                }
            }
        }
    }
}

if (!function_exists('chimQuestEngineFetchMaxRuntimeGamets')) {
    function chimQuestEngineFetchMaxRuntimeGamets()
    {
        if (!chimQuestEngineReady()) {
            return null;
        }

        $row = $GLOBALS["db"]->fetchOne("
            SELECT GREATEST(
                COALESCE((SELECT MAX(last_gamets) FROM public.skyrim_quest_instances), 0),
                COALESCE((SELECT MAX(fired_gamets) FROM public.skyrim_quest_beat_state), 0),
                COALESCE((SELECT MAX(gamets) FROM public.skyrim_quest_events), 0),
                COALESCE((SELECT MAX(action_gamets) FROM public.skyrim_quest_action_outbox), 0)
            ) AS max_gamets
        ");

        $maxGamets = intval($row['max_gamets'] ?? 0);
        return ($maxGamets > 0) ? $maxGamets : null;
    }
}

if (!function_exists('chimQuestEngineRebuildInstanceStateAtGamets')) {
    function chimQuestEngineRebuildInstanceStateAtGamets(array $definition, $targetGamets)
    {
        if (!chimQuestEngineReady()) {
            return false;
        }

        chimQuestEngineEnsureInstanceRow($definition);

        $questKey = chimQuestEngineNormalizeQuestKey($definition['quest_key'] ?? '');
        if ($questKey === '') {
            return false;
        }

        $questKeyEscaped = $GLOBALS["db"]->escape($questKey);
        $state = chimQuestEngineDefaultState();
        $lastGamets = null;

        $events = $GLOBALS["db"]->fetchAll("
            SELECT event_type, gamets, payload_json
            FROM public.skyrim_quest_events
            WHERE quest_key = '{$questKeyEscaped}'
              AND gamets IS NOT NULL
              AND gamets <= " . intval($targetGamets) . "
            ORDER BY gamets ASC, id ASC
        ");

        foreach ($events as $event) {
            $payload = chimQuestEngineJsonDecode($event['payload_json'] ?? '{}', array());
            if (!isset($payload['gamets']) && $event['gamets'] !== null && $event['gamets'] !== '') {
                $payload['gamets'] = intval($event['gamets']);
            }
            chimQuestEngineApplyEventToState($definition, $event['event_type'] ?? '', $payload, $state);
            $eventGamets = chimQuestEngineNormalizeGamets($event['gamets'] ?? null);
            if ($eventGamets !== null) {
                $lastGamets = max($lastGamets ?? 0, $eventGamets);
            }
        }

        $beatIndex = chimQuestEngineIndexBeatsById($definition);
        $firedBeats = $GLOBALS["db"]->fetchAll("
            SELECT beat_id, fired_gamets
            FROM public.skyrim_quest_beat_state
            WHERE quest_key = '{$questKeyEscaped}'
              AND fired = true
              AND fired_gamets IS NOT NULL
              AND fired_gamets <= " . intval($targetGamets) . "
            ORDER BY fired_order NULLS LAST, fired_gamets ASC, beat_id ASC
        ");

        foreach ($firedBeats as $firedBeat) {
            $beatId = (string)($firedBeat['beat_id'] ?? '');
            if ($beatId !== '' && isset($beatIndex[$beatId])) {
                chimQuestEngineApplyBeatToStateOnly($definition, $beatIndex[$beatId], $state);
            }
            $beatGamets = chimQuestEngineNormalizeGamets($firedBeat['fired_gamets'] ?? null);
            if ($beatGamets !== null) {
                $lastGamets = max($lastGamets ?? 0, $beatGamets);
            }
        }

        $actions = $GLOBALS["db"]->fetchAll("
            SELECT action_type, action_gamets, payload_json
            FROM public.skyrim_quest_action_outbox
            WHERE quest_key = '{$questKeyEscaped}'
              AND action_gamets IS NOT NULL
              AND action_gamets <= " . intval($targetGamets) . "
            ORDER BY action_gamets ASC, id ASC
        ");

        foreach ($actions as $actionRow) {
            $actionPayload = chimQuestEngineJsonDecode($actionRow['payload_json'] ?? '{}', array());
            if (!is_array($actionPayload)) {
                $actionPayload = array();
            }
            if (empty($actionPayload['type'])) {
                $actionPayload['type'] = $actionRow['action_type'] ?? '';
            }

            $actionType = strtolower(trim((string)($actionPayload['type'] ?? '')));
            if ($actionType === 'set_stage' || $actionType === 'set_stage_cascade') {
                $stage = intval($actionPayload['stage'] ?? -1);
                if ($stage >= 0) {
                    $state['current_stage'] = $stage;
                }
            }
            chimQuestEngineMutateStateForAction($state, $actionPayload);

            $actionGamets = chimQuestEngineNormalizeGamets($actionRow['action_gamets'] ?? null);
            if ($actionGamets !== null) {
                $lastGamets = max($lastGamets ?? 0, $actionGamets);
            }
        }

        $runState = $state['run_state'] ?? null;
        if (!is_string($runState) || trim($runState) === '') {
            $runState = (!empty($firedBeats) || $state['current_stage'] !== null) ? 'running' : 'inactive';
        }
        unset($state['run_state']);

        return chimQuestEnginePersistInstance($definition, array(
            'quest_key' => $questKey,
            'quest_editor_id' => $definition['quest_editor_id'] ?? $questKey,
            'run_state' => $runState,
            'current_stage' => $state['current_stage'],
            'last_gamets' => $lastGamets,
            'state_json' => $state,
        ));
    }
}

if (!function_exists('chimQuestEngineRollbackRuntimeToGamets')) {
    function chimQuestEngineRollbackRuntimeToGamets($targetGamets)
    {
        if (!chimQuestEngineReady()) {
            return array('rolled_back' => false, 'reason' => 'not_ready');
        }

        $targetGamets = chimQuestEngineNormalizeGamets($targetGamets);
        if ($targetGamets === null) {
            return array('rolled_back' => false, 'reason' => 'missing_gamets');
        }

        $currentMaxGamets = chimQuestEngineFetchMaxRuntimeGamets();
        if ($currentMaxGamets === null || $targetGamets >= $currentMaxGamets) {
            return array(
                'rolled_back' => false,
                'current_max_gamets' => $currentMaxGamets,
                'target_gamets' => $targetGamets,
            );
        }

        $GLOBALS["db"]->execQuery("DELETE FROM public.skyrim_quest_action_outbox WHERE action_gamets IS NULL OR action_gamets > {$targetGamets}");
        $GLOBALS["db"]->execQuery("DELETE FROM public.skyrim_quest_beat_state WHERE fired_gamets IS NULL OR fired_gamets > {$targetGamets}");
        $GLOBALS["db"]->execQuery("DELETE FROM public.skyrim_quest_events WHERE gamets IS NULL OR gamets > {$targetGamets}");

        $rebuilt = 0;
        foreach (chimQuestEngineFetchDefinitions(true) as $definition) {
            if (chimQuestEngineRebuildInstanceStateAtGamets($definition, $targetGamets)) {
                $rebuilt++;
            }
        }

        chimQuestEngineLog('info', "Rolled back Skyrim quest runtime from gamets {$currentMaxGamets} to {$targetGamets}; rebuilt {$rebuilt} quest instance(s).");

        return array(
            'rolled_back' => true,
            'from_gamets' => $currentMaxGamets,
            'target_gamets' => $targetGamets,
            'rebuilt_instances' => $rebuilt,
        );
    }
}

if (!function_exists('chimQuestEngineHandleEventForDefinition')) {
    function chimQuestEngineHandleEventForDefinition(array $definition, $eventType, array $payload)
    {
        chimQuestEngineEnsureInstanceRow($definition);

        $instance = chimQuestEngineGetInstance($definition['quest_key']);
        if (!$instance) {
            return array('quest_key' => $definition['quest_key'], 'beats' => array());
        }

        $instance['state_json'] = chimQuestEngineNormalizeState($instance['state_json'] ?? array());
        $beatStateMap = chimQuestEngineLoadBeatStateMap($definition['quest_key']);

        chimQuestEngineApplyEventToState($definition, $eventType, $payload, $instance['state_json']);
        $eventGamets = chimQuestEngineNormalizeGamets($payload['gamets'] ?? null);
        if ($eventGamets !== null) {
            $instance['last_gamets'] = max(intval($instance['last_gamets'] ?? 0), $eventGamets);
        }
        if ($instance['state_json']['current_stage'] !== null) {
            $instance['current_stage'] = intval($instance['state_json']['current_stage']);
        }
        chimQuestEngineRehydrateBeatStateFromStage($definition, $instance, $beatStateMap, $eventType, $payload);

        $firedBeats = array();
        $passes = 0;
        do {
            $firedThisPass = false;
            foreach ($definition['beats'] ?? array() as $beat) {
                if (!is_array($beat)) {
                    continue;
                }
                $beatId = (string)($beat['id'] ?? '');
                if ($beatId === '' || !empty($beatStateMap[$beatId]['fired'])) {
                    continue;
                }
                if (!chimQuestEngineBeatPrerequisitesMet($beat, $beatStateMap)) {
                    continue;
                }
                if (!chimQuestEngineConditionsMet($beat['conditions'] ?? array(), $instance['state_json'], $definition)) {
                    continue;
                }
                if (!chimQuestEngineRequiredItemMet($beat, $instance['state_json'])) {
                    continue;
                }
                if (!chimQuestEngineBeatShouldFire($beat, $eventType, $payload, $instance['state_json'], $definition)) {
                    continue;
                }

                chimQuestEngineFireBeat($definition, $beat, $eventType, $payload, $instance, $beatStateMap, $firedBeats);
                $firedThisPass = true;
            }
            $passes++;
        } while ($firedThisPass && $passes < 10);

        $eventTypeCn = strtolower(trim((string)$eventType));
        if ($eventTypeCn === 'dialogue_turn' && empty($firedBeats)) {
            $intentSelection = chimQuestEngineSelectDialogueBeatByIntent($definition, $instance, $beatStateMap, $payload);
            if (is_array($intentSelection) && !empty($intentSelection['selected_beat_id']) && !empty($intentSelection['beat']) && is_array($intentSelection['beat'])) {
                $intentPayload = $payload;
                $intentPayload['evaluation_mode'] = 'llm_fallback';
                $intentPayload['intent_selected_beat_id'] = $intentSelection['selected_beat_id'];
                $intentPayload['intent_confidence'] = floatval($intentSelection['confidence'] ?? 0.0);
                $intentPayload['intent_threshold'] = floatval($intentSelection['threshold'] ?? 0.80);
                $intentPayload['intent_reason'] = $intentSelection['reason'] ?? '';

                chimQuestEngineFireBeat(
                    $definition,
                    $intentSelection['beat'],
                    'dialogue_turn_intent',
                    $intentPayload,
                    $instance,
                    $beatStateMap,
                    $firedBeats
                );
            }
        }

        if ($instance['run_state'] === 'inactive' && (!empty($firedBeats) || $instance['current_stage'] !== null)) {
            $instance['run_state'] = 'running';
        }

        chimQuestEnginePersistInstance($definition, $instance);

        return array(
            'quest_key' => $definition['quest_key'],
            'quest_editor_id' => $definition['quest_editor_id'] ?? $definition['quest_key'],
            'current_stage' => $instance['current_stage'],
            'run_state' => $instance['run_state'],
            'beats' => $firedBeats,
        );
    }
}

if (!function_exists('chimQuestEngineHandleEvent')) {
    function chimQuestEngineHandleEvent($eventType, array $payload = array())
    {
        if (!chimQuestEngineFeatureEnabled()) {
            return array(
                'ok' => true,
                'event_type' => $eventType,
                'disabled' => true,
                'results' => array(),
            );
        }
        if (!chimQuestEngineReady()) {
            return array('ok' => false, 'error' => 'quest engine tables not ready');
        }

        chimQuestEngineMaybeBootstrapBundledDefinitions();
        $rollback = chimQuestEngineRollbackRuntimeToGamets($payload['gamets'] ?? null);
        $definitions = chimQuestEngineFetchDefinitions(true);
        $results = array();

        foreach ($definitions as $definition) {
            $questKey = $definition['quest_key'];
            chimQuestEngineInsertEvent($questKey, $eventType, $payload);
            $result = chimQuestEngineHandleEventForDefinition($definition, $eventType, $payload);
            if (!empty($result['beats'])) {
                $results[] = $result;
            }
        }

        return array(
            'ok' => true,
            'event_type' => $eventType,
            'rollback' => $rollback,
            'results' => $results,
        );
    }
}

if (!function_exists('chimQuestEngineExtractPlayerUtterance')) {
    function chimQuestEngineExtractPlayerUtterance(array $gameRequest)
    {
        $text = trim((string)($gameRequest[3] ?? ''));
        if ($text === '') {
            return '';
        }

        $parts = explode(':', $text, 2);
        if (count($parts) === 2 && strlen(trim($parts[0])) <= 48) {
            $text = trim($parts[1]);
        }

        $patterns = array(
            '/\(\s*context [^)]+\)/i',
            '/\(\s*(?:talking|whispering|speaking loudly)\s+to\s+[^)]+\)/i',
        );
        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, ' ', $text);
        }

        $text = preg_replace('/\s+/', ' ', $text);
        return trim((string)$text);
    }
}

if (!function_exists('chimQuestEngineHandleLiveDialogueTurn')) {
    function chimQuestEngineHandleLiveDialogueTurn($npcName, $npcResponseText, array $gameRequest)
    {
        if (!chimQuestEngineFeatureEnabled()) {
            return array('ok' => true, 'disabled' => true);
        }
        $npcNameCn = trim((string)$npcName);
        $npcResponseCn = trim((string)$npcResponseText);
        $requestType = strtolower(trim((string)($gameRequest[0] ?? '')));
        if ($npcNameCn === '' || $npcResponseCn === '') {
            return array('ok' => false, 'error' => 'missing npc or response text');
        }
        if (strcasecmp($npcNameCn, 'The Narrator') === 0 || strcasecmp($npcNameCn, 'Player') === 0) {
            return array('ok' => false, 'error' => 'ignored actor');
        }
        if (!in_array($requestType, array('inputtext', 'inputtext_s', 'ginputtext', 'ginputtext_s'), true)) {
            return array('ok' => false, 'error' => 'request type not eligible');
        }

        $playerText = chimQuestEngineExtractPlayerUtterance($gameRequest);
        if ($playerText === '') {
            return array('ok' => false, 'error' => 'missing player text');
        }

        $payload = array(
            'event_source' => 'live_dialogue',
            'npc_name' => $npcNameCn,
            'player_text' => $playerText,
            'npc_text' => $npcResponseCn,
            'gamets' => intval($gameRequest[2] ?? 0),
            'ts' => intval($gameRequest[1] ?? 0),
            'location_name' => $GLOBALS["CACHE_LOCATION"] ?? '',
            'listener' => $GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"] ?? '',
        );

        return chimQuestEngineHandleEvent('dialogue_turn', $payload);
    }
}

if (!function_exists('chimQuestEngineSyncPlayerInventory')) {
    function chimQuestEngineSyncPlayerInventory(array $items, $gamets = null)
    {
        if (!chimQuestEngineFeatureEnabled()) {
            return array('ok' => true, 'disabled' => true);
        }
        $payload = array(
            'event_source' => 'gamedata',
            'items' => $items,
        );
        $eventGamets = chimQuestEngineNormalizeGamets($gamets);
        if ($eventGamets !== null) {
            $payload['gamets'] = $eventGamets;
        }
        return chimQuestEngineHandleEvent('player_inventory_sync', $payload);
    }
}

if (!function_exists('chimQuestEngineFetchPendingActions')) {
    function chimQuestEngineFetchPendingActions($limit = 25)
    {
        if (!chimQuestEngineFeatureEnabled()) {
            return array();
        }
        if (!chimQuestEngineReady()) {
            return array();
        }

        $limitCn = max(1, min(100, intval($limit)));
        $rows = $GLOBALS["db"]->fetchAll("
            SELECT id, quest_key, beat_id, action_type, action_gamets, payload_json, status, created_at, applied_at
            FROM public.skyrim_quest_action_outbox
            WHERE status = 'pending'
            ORDER BY id ASC
            LIMIT {$limitCn}
        ");

        $actions = array();
        foreach ($rows as $row) {
            $actions[] = array(
                'id' => intval($row['id']),
                'quest_key' => $row['quest_key'],
                'beat_id' => $row['beat_id'],
                'action_type' => $row['action_type'],
                'action_gamets' => ($row['action_gamets'] === null || $row['action_gamets'] === '') ? null : intval($row['action_gamets']),
                'payload' => chimQuestEngineJsonDecode($row['payload_json'] ?? '{}', array()),
                'status' => $row['status'],
                'created_at' => $row['created_at'],
                'applied_at' => $row['applied_at'],
            );
        }

        return $actions;
    }
}

if (!function_exists('chimQuestEngineAcknowledgeAction')) {
    function chimQuestEngineAcknowledgeAction($actionId, $status = 'applied', array $result = array())
    {
        if (!chimQuestEngineReady()) {
            return array('success' => false, 'error' => 'quest engine tables not ready');
        }

        $actionIdCn = intval($actionId);
        if ($actionIdCn <= 0) {
            return array('success' => false, 'error' => 'invalid action id');
        }

        $row = $GLOBALS["db"]->fetchOne("
            SELECT id, quest_key, action_type, payload_json
            FROM public.skyrim_quest_action_outbox
            WHERE id = {$actionIdCn}
            LIMIT 1
        ");
        if (!$row) {
            return array('success' => false, 'error' => 'action not found');
        }

        $statusCn = strtolower(trim((string)$status));
        if ($statusCn === '') {
            $statusCn = 'applied';
        }
        $statusEscaped = $GLOBALS["db"]->escape($statusCn);
        $resultJson = $GLOBALS["db"]->escape(chimQuestEngineJsonEncode($result));
        $appliedAtSql = ($statusCn === 'applied') ? 'now()' : 'NULL';

        $GLOBALS["db"]->execQuery("
            UPDATE public.skyrim_quest_action_outbox
            SET status = '{$statusEscaped}',
                result_json = '{$resultJson}'::jsonb,
                applied_at = {$appliedAtSql},
                updated_at = now()
            WHERE id = {$actionIdCn}
        ");

        return array('success' => true, 'id' => $actionIdCn, 'status' => $statusCn);
    }
}

if (!function_exists('chimQuestEngineResetRuntime')) {
    function chimQuestEngineResetRuntime($preserveDefinitions = true)
    {
        if (!chimQuestEngineReady()) {
            return false;
        }

        $GLOBALS["db"]->execQuery("DELETE FROM public.skyrim_quest_action_outbox");
        $GLOBALS["db"]->execQuery("DELETE FROM public.skyrim_quest_events");
        $GLOBALS["db"]->execQuery("DELETE FROM public.skyrim_quest_beat_state");
        $defaultState = $GLOBALS["db"]->escape(chimQuestEngineJsonEncode(chimQuestEngineDefaultState()));
        $GLOBALS["db"]->execQuery("
            UPDATE public.skyrim_quest_instances
            SET run_state = 'inactive',
                current_stage = NULL,
                last_gamets = NULL,
                state_json = '{$defaultState}'::jsonb,
                updated_at = now()
        ");

        if (!$preserveDefinitions) {
            $GLOBALS["db"]->execQuery("DELETE FROM public.skyrim_quest_instances");
            $GLOBALS["db"]->execQuery("DELETE FROM public.skyrim_quest_definitions");
        }

        chimQuestEngineMaybeBootstrapBundledDefinitions();
        return true;
    }
}

if (!function_exists('chimQuestEngineStatus')) {
    function chimQuestEngineStatus()
    {
        $enabled = chimQuestEngineFeatureEnabled();
        if (!chimQuestEngineReady()) {
            return array('ready' => false, 'enabled' => $enabled);
        }

        chimQuestEngineMaybeBootstrapBundledDefinitions();
        $definitions = $GLOBALS["db"]->fetchOne("SELECT COUNT(*) as n FROM public.skyrim_quest_definitions");
        $instances = $GLOBALS["db"]->fetchOne("SELECT COUNT(*) as n FROM public.skyrim_quest_instances");
        $pendingActions = $GLOBALS["db"]->fetchOne("SELECT COUNT(*) as n FROM public.skyrim_quest_action_outbox WHERE status = 'pending'");
        $events = $GLOBALS["db"]->fetchOne("SELECT COUNT(*) as n FROM public.skyrim_quest_events");
        $maxGamets = chimQuestEngineFetchMaxRuntimeGamets();

        return array(
            'ready' => true,
            'enabled' => $enabled,
            'definition_count' => intval($definitions['n'] ?? 0),
            'instance_count' => intval($instances['n'] ?? 0),
            'pending_action_count' => intval($pendingActions['n'] ?? 0),
            'event_count' => intval($events['n'] ?? 0),
            'max_gamets' => $maxGamets,
        );
    }
}

if (!function_exists('chimQuestEnginePendingDialogueHint')) {
    function chimQuestEnginePendingDialogueHint(array $definition, array $instance, array $beatStateMap, $npcName)
    {
        $npcNameCn = trim((string)$npcName);
        foreach ($definition['beats'] ?? array() as $beat) {
            if (!is_array($beat)) {
                continue;
            }
            $beatId = (string)($beat['id'] ?? '');
            if ($beatId === '' || !empty($beatStateMap[$beatId]['fired'])) {
                continue;
            }
            $focusNpc = trim((string)($beat['focus_npc'] ?? ''));
            if ($focusNpc !== '' && strcasecmp($focusNpc, $npcNameCn) !== 0) {
                continue;
            }
            if (!chimQuestEngineBeatHasDialogueTrigger($beat)) {
                continue;
            }
            if (!chimQuestEngineBeatPrerequisitesMet($beat, $beatStateMap)) {
                continue;
            }
            if (!chimQuestEngineConditionsMet($beat['conditions'] ?? array(), $instance['state_json'], $definition)) {
                continue;
            }
            if (!chimQuestEngineRequiredItemMet($beat, $instance['state_json'])) {
                continue;
            }

            return true;
        }

        return false;
    }
}

if (!function_exists('chimQuestEngineBuildPromptContext')) {
    function chimQuestEngineBuildPromptContext($npcName, $locationName = '')
    {
        if (!chimQuestEngineFeatureEnabled()) {
            return '';
        }
        if (!chimQuestEngineReady()) {
            return '';
        }

        $npcNameCn = trim((string)$npcName);
        if ($npcNameCn === '' || strcasecmp($npcNameCn, 'The Narrator') === 0 || strcasecmp($npcNameCn, 'Player') === 0) {
            return '';
        }

        chimQuestEngineMaybeBootstrapBundledDefinitions();
        $blocks = array();
        foreach (chimQuestEngineFetchDefinitions(true) as $definition) {
            $npcFactsMap = $definition['npc_facts'] ?? array();
            $npcFactsKey = chimQuestEngineFindCaseInsensitiveKey($npcFactsMap, $npcNameCn);
            if ($npcFactsKey === null) {
                continue;
            }

            $instance = chimQuestEngineGetInstance($definition['quest_key']);
            if (!$instance) {
                continue;
            }
            $instance['state_json'] = chimQuestEngineNormalizeState($instance['state_json'] ?? array());
            $beatStateMap = chimQuestEngineLoadBeatStateMap($definition['quest_key']);

            $facts = array();
            $npcFacts = $npcFactsMap[$npcFactsKey];
            foreach ($npcFacts['base_facts'] ?? array() as $fact) {
                $factCn = trim((string)$fact);
                if ($factCn !== '') {
                    $facts[$factCn] = true;
                }
            }

            foreach ($npcFacts['beat_facts'] ?? array() as $beatId => $beatFacts) {
                if (empty($beatStateMap[$beatId]['fired'])) {
                    continue;
                }
                foreach ($beatFacts as $fact) {
                    $factCn = trim((string)$fact);
                    if ($factCn !== '') {
                        $facts[$factCn] = true;
                    }
                }
            }

            if (chimQuestEnginePendingDialogueHint($definition, $instance, $beatStateMap, $npcNameCn)) {
                $facts["The player's current words or possessions may be relevant to this quest. Respond naturally if they discuss it."] = true;
            }

            if (empty($facts)) {
                continue;
            }

            $questTitle = trim((string)($definition['title'] ?? $definition['quest_editor_id'] ?? $definition['quest_key']));
            if ($questTitle === '') {
                $questTitle = $definition['quest_key'];
            }

            $block = "Quest: {$questTitle}\n";
            foreach (array_keys($facts) as $fact) {
                $block .= "- {$fact}\n";
            }
            $blocks[] = trim($block);
        }

        if (empty($blocks)) {
            return '';
        }

        return "\n<quest_context>\n#Quest-sensitive facts\n" . implode("\n\n", $blocks) . "\n</quest_context>";
    }
}
