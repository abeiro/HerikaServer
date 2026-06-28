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

        if (!function_exists('chimGetGeneralSettingBool')) {
            $settingsPath = __DIR__ . DIRECTORY_SEPARATOR . 'settings.php';
            if (file_exists($settingsPath)) {
                require_once($settingsPath);
            }
        }

        if (function_exists('chimGetGeneralSettingRow') && function_exists('chimGetGeneralSettingBool')) {
            try {
                if (chimGetGeneralSettingRow('CHIM_AI_QUEST_PROGRESSION')) {
                    $cached = chimGetGeneralSettingBool('CHIM_AI_QUEST_PROGRESSION', false);
                    return $cached;
                }
            } catch (Throwable $e) {
                chimQuestEngineLog('warn', 'Could not read CHIM_AI_QUEST_PROGRESSION general setting: ' . $e->getMessage());
            }
        }

        if (array_key_exists('CHIM_AI_QUEST_PROGRESSION', $GLOBALS)) {
            $valueCn = strtolower(trim((string)$GLOBALS['CHIM_AI_QUEST_PROGRESSION']));
            $cached = in_array($valueCn, array('1', 'true', 'on', 'yes', 'enabled'), true);
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

if (!function_exists('chimQuestEnginePlayerOnlyAdvancementEnabled')) {
    function chimQuestEnginePlayerOnlyAdvancementEnabled()
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        if (!function_exists('chimGetGeneralSettingBool')) {
            $settingsPath = __DIR__ . DIRECTORY_SEPARATOR . 'settings.php';
            if (file_exists($settingsPath)) {
                require_once($settingsPath);
            }
        }

        if (function_exists('chimGetGeneralSettingRow') && function_exists('chimGetGeneralSettingBool')) {
            try {
                if (chimGetGeneralSettingRow('CHIM_PLAYER_ONLY_QUEST_ADVANCEMENT')) {
                    $cached = chimGetGeneralSettingBool('CHIM_PLAYER_ONLY_QUEST_ADVANCEMENT', true);
                    return $cached;
                }
            } catch (Throwable $e) {
                chimQuestEngineLog('warn', 'Could not read CHIM_PLAYER_ONLY_QUEST_ADVANCEMENT general setting: ' . $e->getMessage());
            }
        }

        if (array_key_exists('CHIM_PLAYER_ONLY_QUEST_ADVANCEMENT', $GLOBALS)) {
            $valueCn = strtolower(trim((string)$GLOBALS['CHIM_PLAYER_ONLY_QUEST_ADVANCEMENT']));
            $cached = in_array($valueCn, array('1', 'true', 'on', 'yes', 'enabled'), true);
            return $cached;
        }

        if (!chimQuestEngineHasDb() || !chimQuestEngineTableExists('conf_opts')) {
            $cached = true;
            return true;
        }

        try {
            $row = $GLOBALS["db"]->fetchOne("
                SELECT value
                FROM public.conf_opts
                WHERE lower(id) = 'chim_player_only_quest_advancement'
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
            chimQuestEngineLog('warn', 'Could not read chim_player_only_quest_advancement: ' . $e->getMessage());
        }

        $cached = true;
        return true;
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
            'radiant_aliases' => array(),
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
        if (!is_array($normalized['radiant_aliases'])) {
            $normalized['radiant_aliases'] = array();
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
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'skyrim_quest_definitions.json',
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

if (!function_exists('chimQuestEngineIsListArray')) {
    function chimQuestEngineIsListArray(array $value)
    {
        if (count($value) === 0) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
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

if (!function_exists('chimQuestEngineImportDefinitionData')) {
    function chimQuestEngineImportDefinitionData(array $definition, $sourcePath)
    {
        if (!chimQuestEngineReady()) {
            return array('success' => false, 'error' => 'quest engine tables not ready');
        }

        $questKey = chimQuestEngineNormalizeQuestKey($definition['quest_key'] ?? $definition['quest_editor_id'] ?? '');
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
        $sourcePath = $GLOBALS["db"]->escape((string)$sourcePath);
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
            'source_path' => $sourcePath,
        );
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

        if (chimQuestEngineIsListArray($definition)) {
            $results = array();
            foreach ($definition as $index => $bundledDefinition) {
                if (!is_array($bundledDefinition)) {
                    $results[] = array('success' => false, 'error' => 'bundled definition is invalid', 'source_path' => $filePath . '#' . $index);
                    continue;
                }
                $questKey = chimQuestEngineNormalizeQuestKey($bundledDefinition['quest_key'] ?? $bundledDefinition['quest_editor_id'] ?? '');
                $results[] = chimQuestEngineImportDefinitionData($bundledDefinition, $filePath . ($questKey !== '' ? '#' . $questKey : '#' . $index));
            }
            return $results;
        }

        if (empty($definition['quest_key']) && empty($definition['quest_editor_id'])) {
            $definition['quest_key'] = pathinfo($filePath, PATHINFO_FILENAME);
        }

        return chimQuestEngineImportDefinitionData($definition, $filePath);
    }
}

if (!function_exists('chimQuestEngineImportBundledDefinitions')) {
    function chimQuestEngineImportBundledDefinitions()
    {
        $results = array();
        foreach (chimQuestEngineBundledDefinitionFiles() as $filePath) {
            $importResult = chimQuestEngineImportDefinitionFile($filePath);
            if (isset($importResult[0]) && is_array($importResult[0])) {
                foreach ($importResult as $result) {
                    $results[] = $result;
                }
            } else {
                $results[] = $importResult;
            }
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
            $definition['source_path'] = $row['source_path'];
            $definitions[] = $definition;
        }

        return $definitions;
    }
}

if (!function_exists('chimQuestEngineIsRadiantTemplate')) {
    function chimQuestEngineIsRadiantTemplate(array $definition)
    {
        $type = strtolower(trim((string)($definition['skeleton_type'] ?? '')));
        if ($type === 'radiant_template') {
            return true;
        }

        return !empty($definition['radiant_template']) && is_array($definition['radiant_template']) && !empty($definition['radiant_template']['enabled']);
    }
}

if (!function_exists('chimQuestEngineNormalizeAliasName')) {
    function chimQuestEngineNormalizeAliasName($value)
    {
        $cn = strtolower(trim((string)$value));
        $cn = preg_replace('/[^a-z0-9]+/', '', $cn);
        return (string)$cn;
    }
}

if (!function_exists('chimQuestEngineAliasDisplayName')) {
    function chimQuestEngineAliasDisplayName(array $alias)
    {
        foreach (array('display_name', 'base_name', 'instance_text', 'name') as $key) {
            $value = trim((string)($alias[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}

if (!function_exists('chimQuestEngineAliasFieldValue')) {
    function chimQuestEngineAliasFieldValue(array $alias, $field)
    {
        $fieldCn = strtolower(trim((string)$field));
        if ($fieldCn === '' || $fieldCn === 'name' || $fieldCn === 'display_name') {
            return chimQuestEngineAliasDisplayName($alias);
        }
        if ($fieldCn === 'plugin') {
            return trim((string)($alias['base_plugin'] ?? ($alias['plugin'] ?? '')));
        }
        if ($fieldCn === 'form_id') {
            return trim((string)($alias['base_form_id'] ?? ($alias['form_id'] ?? '')));
        }

        return trim((string)($alias[$fieldCn] ?? ''));
    }
}

if (!function_exists('chimQuestEnginePayloadAliasMap')) {
    function chimQuestEnginePayloadAliasMap(array $definition, array $payload)
    {
        $aliases = $payload['aliases'] ?? array();
        if (!is_array($aliases) || empty($aliases)) {
            return array();
        }

        $map = array();
        foreach ($aliases as $alias) {
            if (!is_array($alias)) {
                continue;
            }

            $aliasName = trim((string)($alias['name'] ?? ''));
            if ($aliasName === '') {
                continue;
            }

            $normalized = chimQuestEngineNormalizeAliasName($aliasName);
            if ($normalized !== '') {
                $map[$normalized] = $alias;
            }
        }

        $template = $definition['radiant_template'] ?? array();
        $synonyms = is_array($template) ? ($template['alias_synonyms'] ?? array()) : array();
        if (is_array($synonyms)) {
            foreach ($synonyms as $canonical => $names) {
                $canonicalCn = chimQuestEngineNormalizeAliasName($canonical);
                if ($canonicalCn === '') {
                    continue;
                }
                if (isset($map[$canonicalCn])) {
                    continue;
                }
                foreach ((array)$names as $name) {
                    $nameCn = chimQuestEngineNormalizeAliasName($name);
                    if ($nameCn !== '' && isset($map[$nameCn])) {
                        $map[$canonicalCn] = $map[$nameCn];
                        break;
                    }
                }
            }
        }

        if (!isset($map['questgiver'])) {
            foreach ($aliases as $alias) {
                if (!is_array($alias)) {
                    continue;
                }
                $nameCn = chimQuestEngineNormalizeAliasName($alias['name'] ?? '');
                if (strpos($nameCn, 'giver') !== false || !empty($alias['is_actor'])) {
                    $map['questgiver'] = $alias;
                    break;
                }
            }
        }

        if (!isset($map['questitem'])) {
            foreach ($aliases as $alias) {
                if (!is_array($alias)) {
                    continue;
                }
                $nameCn = chimQuestEngineNormalizeAliasName($alias['name'] ?? '');
                $hasItemIdentity = trim((string)($alias['base_form_id'] ?? ($alias['form_id'] ?? ''))) !== '';
                if (strpos($nameCn, 'item') !== false || ($hasItemIdentity && empty($alias['is_actor']) && strtolower((string)($alias['type'] ?? '')) === 'reference')) {
                    $map['questitem'] = $alias;
                    break;
                }
            }
        }

        return $map;
    }
}

if (!function_exists('chimQuestEngineRadiantAliasesSatisfyTemplate')) {
    function chimQuestEngineRadiantAliasesSatisfyTemplate(array $definition, array $aliasMap)
    {
        $template = $definition['radiant_template'] ?? array();
        $requiredAliases = is_array($template) ? ($template['required_aliases'] ?? array()) : array();
        foreach ((array)$requiredAliases as $aliasName) {
            $aliasNameCn = chimQuestEngineNormalizeAliasName($aliasName);
            if ($aliasNameCn === '' || !isset($aliasMap[$aliasNameCn])) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('chimQuestEngineRadiantMinimumQuestStage')) {
    function chimQuestEngineRadiantMinimumQuestStage(array $definition)
    {
        $minimumStage = null;
        foreach ($definition['beats'] ?? array() as $beat) {
            if (!is_array($beat)) {
                continue;
            }
            foreach ($beat['triggers'] ?? array() as $trigger) {
                if (!is_array($trigger)) {
                    continue;
                }
                if (strtolower(trim((string)($trigger['type'] ?? ''))) !== 'quest_stage') {
                    continue;
                }
                $stage = intval($trigger['min_stage'] ?? $trigger['stage'] ?? -1);
                if ($stage < 0) {
                    continue;
                }
                $minimumStage = ($minimumStage === null) ? $stage : min($minimumStage, $stage);
            }
        }

        return $minimumStage;
    }
}

if (!function_exists('chimQuestEngineReplaceRadiantPlaceholders')) {
    function chimQuestEngineReplaceRadiantPlaceholders($value, array $aliasMap)
    {
        if (is_array($value)) {
            $resolved = array();
            foreach ($value as $key => $childValue) {
                $resolvedKey = is_string($key) ? chimQuestEngineReplaceRadiantPlaceholders($key, $aliasMap) : $key;
                $resolved[$resolvedKey] = chimQuestEngineReplaceRadiantPlaceholders($childValue, $aliasMap);
            }
            return $resolved;
        }

        if (!is_string($value) || strpos($value, '{') === false) {
            return $value;
        }

        return preg_replace_callback('/\{([A-Za-z0-9_ -]+)(?:\.([A-Za-z0-9_]+))?\}/', function ($matches) use ($aliasMap) {
            $aliasName = chimQuestEngineNormalizeAliasName($matches[1] ?? '');
            $field = $matches[2] ?? '';
            if ($aliasName === '' || !isset($aliasMap[$aliasName])) {
                return $matches[0];
            }

            $replacement = chimQuestEngineAliasFieldValue($aliasMap[$aliasName], $field);
            return ($replacement !== '') ? $replacement : $matches[0];
        }, $value);
    }
}

if (!function_exists('chimQuestEngineSlugText')) {
    function chimQuestEngineSlugText($value)
    {
        $cn = strtolower(trim((string)$value));
        $cn = preg_replace('/[^a-z0-9]+/', '_', $cn);
        $cn = trim((string)$cn, '_');
        return ($cn !== '') ? $cn : 'unknown';
    }
}

if (!function_exists('chimQuestEngineRadiantInstanceKey')) {
    function chimQuestEngineRadiantInstanceKey(array $definition, array $aliasMap)
    {
        $templateKey = chimQuestEngineNormalizeQuestKey($definition['quest_key'] ?? $definition['quest_editor_id'] ?? 'radiant');
        $template = $definition['radiant_template'] ?? array();
        $keyAliases = is_array($template) ? ($template['instance_key_aliases'] ?? array()) : array();
        if (empty($keyAliases)) {
            $keyAliases = array_keys($aliasMap);
        }

        $parts = array($templateKey);
        foreach ((array)$keyAliases as $aliasName) {
            $aliasNameCn = chimQuestEngineNormalizeAliasName($aliasName);
            if ($aliasNameCn === '' || !isset($aliasMap[$aliasNameCn])) {
                continue;
            }
            $alias = $aliasMap[$aliasNameCn];
            $identity = chimQuestEngineAliasFieldValue($alias, 'form_id');
            if ($identity === '') {
                $identity = chimQuestEngineAliasDisplayName($alias);
            }
            $parts[] = chimQuestEngineSlugText($identity);
        }

        return chimQuestEngineNormalizeQuestKey(implode('_', $parts));
    }
}

if (!function_exists('chimQuestEngineUpsertDefinition')) {
    function chimQuestEngineUpsertDefinition(array $definition, $sourcePath = '')
    {
        if (!chimQuestEngineReady()) {
            return false;
        }

        $questKey = chimQuestEngineNormalizeQuestKey($definition['quest_key'] ?? $definition['quest_editor_id'] ?? '');
        if ($questKey === '') {
            return false;
        }

        $definition['quest_key'] = $questKey;
        $questEditorId = $GLOBALS["db"]->escape((string)($definition['quest_editor_id'] ?? $questKey));
        $title = $GLOBALS["db"]->escape((string)($definition['title'] ?? ($definition['quest_editor_id'] ?? $questKey)));
        $sourcePlugin = $GLOBALS["db"]->escape((string)($definition['quest_plugin'] ?? ''));
        $sourceFormId = $GLOBALS["db"]->escape((string)($definition['quest_form_id'] ?? ''));
        $sourcePathEscaped = $GLOBALS["db"]->escape((string)$sourcePath);
        $skeletonJson = $GLOBALS["db"]->escape(chimQuestEngineJsonEncode($definition));
        $questKeyEscaped = $GLOBALS["db"]->escape($questKey);

        $GLOBALS["db"]->execQuery("
            INSERT INTO public.skyrim_quest_definitions
                (quest_key, quest_editor_id, title, source_plugin, source_form_id, source_path, skeleton, active)
            VALUES
                ('{$questKeyEscaped}', '{$questEditorId}', '{$title}', '{$sourcePlugin}', '{$sourceFormId}', '{$sourcePathEscaped}', '{$skeletonJson}'::jsonb, true)
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

        return true;
    }
}

if (!function_exists('chimQuestEngineInstantiateRadiantDefinition')) {
    function chimQuestEngineInstantiateRadiantDefinition(array $definition, $eventType, array $payload)
    {
        if (!chimQuestEngineIsRadiantTemplate($definition)) {
            return null;
        }

        $eventTypeCn = strtolower(trim((string)$eventType));
        if ($eventTypeCn !== 'quest_stage') {
            return null;
        }
        if (!chimQuestEngineQuestMatchesPayload($definition, $payload)) {
            return null;
        }

        $minimumStage = chimQuestEngineRadiantMinimumQuestStage($definition);
        if ($minimumStage !== null) {
            $payloadStage = intval($payload['stage'] ?? -1);
            if ($payloadStage < $minimumStage) {
                return null;
            }
        }

        $aliasMap = chimQuestEnginePayloadAliasMap($definition, $payload);
        if (!chimQuestEngineRadiantAliasesSatisfyTemplate($definition, $aliasMap)) {
            return null;
        }

        $concrete = chimQuestEngineReplaceRadiantPlaceholders($definition, $aliasMap);
        $concrete['template_quest_key'] = $definition['quest_key'] ?? '';
        $concrete['quest_key'] = chimQuestEngineRadiantInstanceKey($definition, $aliasMap);
        $concrete['skeleton_type'] = 'quest';
        $concrete['radiant_aliases'] = $aliasMap;
        $concrete['radiant_instance'] = array(
            'template_quest_key' => $definition['quest_key'] ?? '',
            'created_from_event' => 'quest_stage',
            'aliases' => $aliasMap,
        );
        unset($concrete['radiant_template']);

        if (!chimQuestEngineUpsertDefinition($concrete, (string)($definition['source_path'] ?? ''))) {
            return null;
        }

        chimQuestEngineEnsureInstanceRow($concrete);
        $instance = chimQuestEngineGetInstance($concrete['quest_key']);
        if ($instance) {
            $instance['state_json'] = chimQuestEngineNormalizeState($instance['state_json'] ?? array());
            $instance['state_json']['radiant_aliases'] = $aliasMap;
            chimQuestEnginePersistInstance($concrete, $instance);
        }

        return $concrete;
    }
}

if (!function_exists('chimQuestEngineExpandRadiantDefinitionsForEvent')) {
    function chimQuestEngineExpandRadiantDefinitionsForEvent(array $definitions, $eventType, array $payload)
    {
        $expanded = array();
        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            if (chimQuestEngineIsRadiantTemplate($definition)) {
                $concrete = chimQuestEngineInstantiateRadiantDefinition($definition, $eventType, $payload);
                if (is_array($concrete)) {
                    $expanded[] = $concrete;
                }
                continue;
            }

            $expanded[] = $definition;
        }

        return $expanded;
    }
}

if (!function_exists('chimQuestEngineRadiantConcreteMatchesPayload')) {
    function chimQuestEngineRadiantConcreteMatchesPayload(array $definition, array $payload)
    {
        $definitionAliases = $definition['radiant_aliases'] ?? array();
        if (!is_array($definitionAliases) || empty($definitionAliases) || empty($payload['aliases'])) {
            return true;
        }

        $payloadAliases = chimQuestEnginePayloadAliasMap($definition, $payload);
        if (empty($payloadAliases)) {
            return true;
        }

        foreach ($definitionAliases as $aliasName => $definitionAlias) {
            if (!is_array($definitionAlias)) {
                continue;
            }
            $aliasNameCn = chimQuestEngineNormalizeAliasName($aliasName);
            if ($aliasNameCn === '' || !isset($payloadAliases[$aliasNameCn])) {
                continue;
            }

            $definitionKey = chimQuestEngineFormKey(
                chimQuestEngineAliasFieldValue($definitionAlias, 'plugin'),
                chimQuestEngineAliasFieldValue($definitionAlias, 'form_id')
            );
            $payloadKey = chimQuestEngineFormKey(
                chimQuestEngineAliasFieldValue($payloadAliases[$aliasNameCn], 'plugin'),
                chimQuestEngineAliasFieldValue($payloadAliases[$aliasNameCn], 'form_id')
            );
            if ($definitionKey !== '' && $payloadKey !== '' && $definitionKey !== $payloadKey) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('chimQuestEngineDefinitionReferencesDialogueNpc')) {
    function chimQuestEngineDefinitionReferencesDialogueNpc(array $definition, $npcName)
    {
        $npcNameCn = trim((string)$npcName);
        if ($npcNameCn === '') {
            return false;
        }

        $npcFactsMap = $definition['npc_facts'] ?? array();
        if (is_array($npcFactsMap) && chimQuestEngineFindCaseInsensitiveKey($npcFactsMap, $npcNameCn) !== null) {
            return true;
        }

        foreach ($definition['beats'] ?? array() as $beat) {
            if (!is_array($beat)) {
                continue;
            }
            if (chimQuestEngineBeatFocusNpcMatches($beat, $npcNameCn, false)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('chimQuestEngineBeatFocusNpcs')) {
    function chimQuestEngineBeatFocusNpcs(array $beat)
    {
        $focusNpcs = array();

        if (isset($beat['focus_npcs']) && is_array($beat['focus_npcs'])) {
            foreach ($beat['focus_npcs'] as $focusNpc) {
                $focusNpcCn = trim((string)$focusNpc);
                if ($focusNpcCn !== '') {
                    $focusNpcs[$focusNpcCn] = true;
                }
            }
        }

        $focusNpc = trim((string)($beat['focus_npc'] ?? ''));
        if ($focusNpc !== '') {
            $focusNpcs[$focusNpc] = true;
        }

        return array_keys($focusNpcs);
    }
}

if (!function_exists('chimQuestEngineBeatFocusNpcMatches')) {
    function chimQuestEngineBeatFocusNpcMatches(array $beat, $npcName, $emptyFocusMatches = true)
    {
        $npcNameCn = trim((string)$npcName);
        $focusNpcs = chimQuestEngineBeatFocusNpcs($beat);
        if (empty($focusNpcs)) {
            return $emptyFocusMatches;
        }
        if ($npcNameCn === '') {
            return false;
        }

        foreach ($focusNpcs as $focusNpc) {
            if (strcasecmp($focusNpc, $npcNameCn) === 0) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('chimQuestEngineFilterDefinitionsForEvent')) {
    function chimQuestEngineFilterDefinitionsForEvent(array $definitions, $eventType, array $payload)
    {
        if (strtolower(trim((string)$eventType)) !== 'dialogue_turn') {
            return $definitions;
        }

        $npcName = trim((string)($payload['npc_name'] ?? ''));
        if ($npcName === '') {
            return array();
        }

        $filtered = array();
        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                continue;
            }
            if (chimQuestEngineIsRadiantTemplate($definition)) {
                continue;
            }
            if (chimQuestEngineDefinitionReferencesDialogueNpc($definition, $npcName)) {
                $filtered[] = $definition;
            }
        }

        return $filtered;
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
            if (!empty($condition['quest_key']) || !empty($condition['quest_editor_id'])) {
                $otherInstance = chimQuestEngineGetReferencedInstance($condition);
                $stage = intval($condition['min_stage'] ?? $condition['stage'] ?? -1);
                return ($stage >= 0 && $otherInstance && $otherInstance['current_stage'] !== null && intval($otherInstance['current_stage']) >= $stage);
            }

            $stage = intval($condition['min_stage'] ?? $condition['stage'] ?? -1);
            return ($stage >= 0 && $state['current_stage'] !== null && intval($state['current_stage']) >= $stage);
        }

        if ($type === 'quest_started') {
            $otherInstance = chimQuestEngineGetReferencedInstance($condition);
            return ($otherInstance && (strtolower((string)$otherInstance['run_state']) !== 'inactive' || $otherInstance['current_stage'] !== null));
        }

        if ($type === 'quest_not_started') {
            $otherInstance = chimQuestEngineGetReferencedInstance($condition);
            return (!$otherInstance || (strtolower((string)$otherInstance['run_state']) === 'inactive' && $otherInstance['current_stage'] === null));
        }

        if ($type === 'quest_completed') {
            $otherInstance = chimQuestEngineGetReferencedInstance($condition);
            if (!$otherInstance) {
                return false;
            }
            if (strtolower((string)$otherInstance['run_state']) === 'completed') {
                return true;
            }

            $stage = intval($condition['min_stage'] ?? $condition['stage'] ?? -1);
            return ($stage >= 0 && $otherInstance['current_stage'] !== null && intval($otherInstance['current_stage']) >= $stage);
        }

        if ($type === 'quest_beat_fired') {
            return chimQuestEngineReferencedBeatFired($condition);
        }

        if ($type === 'quest_beat_not_fired') {
            return !chimQuestEngineReferencedBeatFired($condition);
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

if (!function_exists('chimQuestEngineGetReferencedInstance')) {
    function chimQuestEngineGetReferencedInstance(array $reference)
    {
        if (!chimQuestEngineReady()) {
            return null;
        }

        $questKey = chimQuestEngineNormalizeQuestKey($reference['quest_key'] ?? '');
        if ($questKey !== '') {
            return chimQuestEngineGetInstance($questKey);
        }

        $questEditorId = trim((string)($reference['quest_editor_id'] ?? ''));
        if ($questEditorId === '') {
            return null;
        }

        $questEditorIdEscaped = $GLOBALS["db"]->escape($questEditorId);
        $row = $GLOBALS["db"]->fetchOne("
            SELECT quest_key, quest_editor_id, run_state, current_stage, last_gamets, state_json
            FROM public.skyrim_quest_instances
            WHERE lower(quest_editor_id) = lower('{$questEditorIdEscaped}')
            ORDER BY updated_at DESC
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

if (!function_exists('chimQuestEngineReferencedBeatFired')) {
    function chimQuestEngineReferencedBeatFired(array $reference)
    {
        if (!chimQuestEngineReady() || !chimQuestEngineTableExists('skyrim_quest_beat_states')) {
            return false;
        }

        $questKey = chimQuestEngineNormalizeQuestKey($reference['quest_key'] ?? '');
        $beatId = trim((string)($reference['beat_id'] ?? $reference['id'] ?? ''));
        if ($questKey === '' || $beatId === '') {
            return false;
        }

        $questKeyEscaped = $GLOBALS["db"]->escape($questKey);
        $beatIdEscaped = $GLOBALS["db"]->escape($beatId);
        $row = $GLOBALS["db"]->fetchOne("
            SELECT fired
            FROM public.skyrim_quest_beat_states
            WHERE quest_key = '{$questKeyEscaped}'
              AND beat_id = '{$beatIdEscaped}'
            LIMIT 1
        ");

        return ($row && !empty($row['fired']));
    }
}

if (!function_exists('chimQuestEngineFirstBeatId')) {
    function chimQuestEngineFirstBeatId(array $definition)
    {
        foreach ($definition['beats'] ?? array() as $beat) {
            if (!is_array($beat)) {
                continue;
            }
            $beatId = trim((string)($beat['id'] ?? ''));
            if ($beatId !== '') {
                return $beatId;
            }
        }

        return '';
    }
}

if (!function_exists('chimQuestEngineBeatIsNaturalStartCandidate')) {
    function chimQuestEngineBeatIsNaturalStartCandidate(array $definition, array $beat)
    {
        if (!chimQuestEngineBeatHasDialogueTrigger($beat)) {
            return false;
        }

        if (!empty($beat['allow_natural_start']) || !empty($beat['natural_start']) || !empty($beat['activation'])) {
            return true;
        }

        $beatId = trim((string)($beat['id'] ?? ''));
        $naturalStart = $definition['natural_start'] ?? null;
        if (is_array($naturalStart)) {
            if (array_key_exists('enabled', $naturalStart) && empty($naturalStart['enabled'])) {
                return false;
            }

            $allowedBeats = $naturalStart['beats'] ?? array();
            if (is_array($allowedBeats) && !empty($allowedBeats)) {
                return in_array($beatId, array_map('strval', $allowedBeats), true);
            }

            if (!empty($naturalStart['enabled'])) {
                return ($beatId !== '' && $beatId === chimQuestEngineFirstBeatId($definition));
            }
        }

        $prerequisites = $beat['prerequisites'] ?? array();
        return ($beatId !== '' && $beatId === chimQuestEngineFirstBeatId($definition) && (empty($prerequisites) || !is_array($prerequisites)));
    }
}

if (!function_exists('chimQuestEngineBeatIsNaturalStartCandidateForEvent')) {
    function chimQuestEngineBeatIsNaturalStartCandidateForEvent(array $definition, array $beat, $eventType)
    {
        $eventTypeCn = strtolower(trim((string)$eventType));
        if ($eventTypeCn === 'dialogue_turn' || $eventTypeCn === 'dialogue_turn_intent') {
            return chimQuestEngineBeatIsNaturalStartCandidate($definition, $beat);
        }

        if (!empty($beat['allow_natural_start']) || !empty($beat['natural_start']) || !empty($beat['activation'])) {
            return true;
        }

        $beatId = trim((string)($beat['id'] ?? ''));
        $naturalStart = $definition['natural_start'] ?? null;
        if (is_array($naturalStart)) {
            if (array_key_exists('enabled', $naturalStart) && empty($naturalStart['enabled'])) {
                return false;
            }

            $allowedBeats = $naturalStart['beats'] ?? array();
            if (is_array($allowedBeats) && !empty($allowedBeats)) {
                return in_array($beatId, array_map('strval', $allowedBeats), true);
            }

            if (!empty($naturalStart['enabled'])) {
                return ($beatId !== '' && $beatId === chimQuestEngineFirstBeatId($definition));
            }
        }

        $prerequisites = $beat['prerequisites'] ?? array();
        return ($beatId !== '' && $beatId === chimQuestEngineFirstBeatId($definition) && (empty($prerequisites) || !is_array($prerequisites)));
    }
}

if (!function_exists('chimQuestEngineNaturalStartConditionsMet')) {
    function chimQuestEngineNaturalStartConditionsMet(array $definition, array $beat, array $state)
    {
        $conditions = array();
        $naturalStart = $definition['natural_start'] ?? null;
        if (is_array($naturalStart) && isset($naturalStart['requires']) && is_array($naturalStart['requires'])) {
            $conditions = array_merge($conditions, $naturalStart['requires']);
        }
        if (isset($beat['start_conditions']) && is_array($beat['start_conditions'])) {
            $conditions = array_merge($conditions, $beat['start_conditions']);
        }

        return chimQuestEngineConditionsMet($conditions, $state, $definition);
    }
}

if (!function_exists('chimQuestEngineBeatAllowedForRuntime')) {
    function chimQuestEngineBeatAllowedForRuntime(array $definition, array $beat, $eventType, array $payload, array $instance)
    {
        $eventTypeCn = strtolower(trim((string)$eventType));
        if ($eventTypeCn === 'quest_stage') {
            return true;
        }

        $runState = strtolower(trim((string)($instance['run_state'] ?? 'inactive')));
        $currentStage = $instance['current_stage'] ?? null;
        if ($runState !== 'inactive' || $currentStage !== null) {
            return true;
        }

        if (chimQuestEnginePlayerOnlyAdvancementEnabled() && $eventTypeCn !== 'dialogue_turn' && $eventTypeCn !== 'dialogue_turn_intent') {
            return false;
        }

        if (!chimQuestEngineBeatIsNaturalStartCandidateForEvent($definition, $beat, $eventTypeCn)) {
            return false;
        }

        return chimQuestEngineNaturalStartConditionsMet($definition, $beat, $instance['state_json'] ?? array());
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

            if (!chimQuestEngineBeatFocusNpcMatches($beat, $npcNameCn, true)) {
                continue;
            }

            if (!chimQuestEngineBeatAllowedForRuntime($definition, $beat, 'dialogue_turn', $payload, $instance)) {
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
        static $intentFallbackCounts = array();

        $playerTextCn = trim((string)($payload['player_text'] ?? ''));
        if ($playerTextCn === '') {
            return null;
        }

        $candidates = chimQuestEngineBuildDialogueIntentCandidates($definition, $instance, $beatStateMap, $payload);
        if (empty($candidates)) {
            return null;
        }

        $intentBudgetKey = md5(implode('|', array(
            strtolower(trim((string)($payload['npc_name'] ?? ''))),
            strtolower($playerTextCn),
            strval(intval($payload['gamets'] ?? 0)),
            strval(intval($payload['ts'] ?? 0)),
        )));
        $intentFallbackLimit = max(1, intval($GLOBALS['CHIM_QUEST_DIALOGUE_INTENT_MAX_CALLS'] ?? 3));
        $intentFallbackCounts[$intentBudgetKey] = intval($intentFallbackCounts[$intentBudgetKey] ?? 0);
        if ($intentFallbackCounts[$intentBudgetKey] >= $intentFallbackLimit) {
            chimQuestEngineLog('debug', 'Skipping quest intent fallback: per-turn fallback limit reached');
            return null;
        }
        $intentFallbackCounts[$intentBudgetKey]++;

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
            return chimQuestEngineRadiantConcreteMatchesPayload($definition, $payload);
        }

        $payloadFormKey = chimQuestEngineFormKey(
            $payload['quest_plugin'] ?? ($payload['plugin'] ?? ''),
            $payload['quest_form_id'] ?? ($payload['form_id'] ?? '')
        );
        $definitionFormKey = chimQuestEngineFormKey($definition['quest_plugin'] ?? '', $definition['quest_form_id'] ?? '');

        if ($payloadFormKey !== '' && $definitionFormKey !== '' && $payloadFormKey === $definitionFormKey) {
            return chimQuestEngineRadiantConcreteMatchesPayload($definition, $payload);
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
            $npcName = trim((string)($payload['npc_name'] ?? ''));
            if (!chimQuestEngineBeatFocusNpcMatches($beat, $npcName, true)) {
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
        } elseif ($type === 'start_quest_stage_objective' || $type === 'actor_dialogue_start_quest_stage_objective' || $type === 'change_location_start_quest_stage_objective') {
            $stage = intval($action['stage'] ?? -1);
            if ($stage >= 0) {
                $state['current_stage'] = $stage;
            }
        } elseif ($type === 'stop_quest') {
            $state['run_state'] = 'completed';
        } elseif ($type === 'fail_all_objectives') {
            $state['run_state'] = 'failed';
        }
    }
}

if (!function_exists('chimQuestEngineActionRequiresAppliedAckForState')) {
    function chimQuestEngineActionRequiresAppliedAckForState($actionType)
    {
        $type = strtolower(trim((string)$actionType));
        return in_array($type, array(
            'start_quest_stage_objective',
            'actor_dialogue_start_quest_stage_objective',
            'change_location_start_quest_stage_objective',
        ), true);
    }
}

if (!function_exists('chimQuestEngineQueueResolvedAction')) {
    function chimQuestEngineQueueResolvedAction(array $definition, $questKey, $beatId, array $action, array &$state, $sourceActionType = '', $gamets = null)
    {
        $actionType = strtolower(trim((string)($action['type'] ?? '')));
        if ($actionType === '') {
            return;
        }

        if (!chimQuestEngineActionRequiresAppliedAckForState($actionType)) {
            chimQuestEngineMutateStateForAction($state, $action);
        }
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
        if (!isset($payload['index']) && isset($payload['objective_index'])) {
            $payload['index'] = $payload['objective_index'];
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
            if (!empty($definition['radiant_aliases']) && is_array($definition['radiant_aliases'])) {
                $state['radiant_aliases'] = $definition['radiant_aliases'];
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

if (!function_exists('chimQuestEngineEventCanFireBeats')) {
    function chimQuestEngineEventCanFireBeats($eventType, array $payload)
    {
        if (!chimQuestEnginePlayerOnlyAdvancementEnabled()) {
            return true;
        }

        $eventTypeCn = strtolower(trim((string)$eventType));
        if ($eventTypeCn !== 'dialogue_turn' && $eventTypeCn !== 'dialogue_turn_intent') {
            return false;
        }

        return !empty($payload['player_driven']);
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
              AND status = 'applied'
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
            if (chimQuestEngineIsRadiantTemplate($definition)) {
                continue;
            }
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

        if (!chimQuestEngineEventCanFireBeats($eventType, $payload)) {
            if ($instance['run_state'] === 'inactive' && $instance['current_stage'] !== null) {
                $instance['run_state'] = 'running';
            }
            chimQuestEnginePersistInstance($definition, $instance);
            return array(
                'quest_key' => $definition['quest_key'],
                'quest_editor_id' => $definition['quest_editor_id'] ?? $definition['quest_key'],
                'current_stage' => $instance['current_stage'],
                'run_state' => $instance['run_state'],
                'beats' => array(),
                'advancement_blocked' => 'player_only',
            );
        }

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
                if (!chimQuestEngineBeatAllowedForRuntime($definition, $beat, $eventType, $payload, $instance)) {
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
        $definitions = chimQuestEngineExpandRadiantDefinitionsForEvent(chimQuestEngineFetchDefinitions(true), $eventType, $payload);
        $definitions = chimQuestEngineFilterDefinitionsForEvent($definitions, $eventType, $payload);
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

        $playerRequestTypes = array('inputtext', 'inputtext_s', 'ginputtext', 'ginputtext_s');
        $isPlayerDriven = in_array($requestType, $playerRequestTypes, true);
        if (chimQuestEnginePlayerOnlyAdvancementEnabled()) {
            if (!$isPlayerDriven) {
                return array('ok' => false, 'error' => 'request type not eligible');
            }
        } else {
            $npcResponseRequestTypes = array(
                'instruction',
                'suggestion',
                'rechat',
                'continue',
                'continue_group',
                'combatbark',
            );
            if (!$isPlayerDriven && !in_array($requestType, $npcResponseRequestTypes, true)) {
                return array('ok' => false, 'error' => 'request type not eligible');
            }
        }

        if (!$isPlayerDriven && empty($gameRequest[3])) {
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
            'player_driven' => $isPlayerDriven,
            'request_type' => $requestType,
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

if (!function_exists('chimQuestEngineApplyAcknowledgedActionState')) {
    function chimQuestEngineApplyAcknowledgedActionState(array $row, array $result)
    {
        $actionPayload = chimQuestEngineJsonDecode($row['payload_json'] ?? '{}', array());
        if (!is_array($actionPayload)) {
            $actionPayload = array();
        }
        $actionType = strtolower(trim((string)($actionPayload['type'] ?? $row['action_type'] ?? '')));
        if (!chimQuestEngineActionRequiresAppliedAckForState($actionType)) {
            return;
        }
        if (empty($result['verified'])) {
            return;
        }

        $stage = intval($result['stage'] ?? ($actionPayload['stage'] ?? -1));
        if ($stage < 0) {
            return;
        }

        $questKey = chimQuestEngineNormalizeQuestKey($row['quest_key'] ?? '');
        if ($questKey === '') {
            return;
        }

        $ackGamets = chimQuestEngineNormalizeGamets($result['gamets'] ?? ($actionPayload['gamets'] ?? ($row['action_gamets'] ?? null)));
        $lastGametsSql = ($ackGamets === null)
            ? 'last_gamets'
            : 'GREATEST(COALESCE(last_gamets, 0), ' . intval($ackGamets) . ')';
        $questKeyEscaped = $GLOBALS["db"]->escape($questKey);

        $GLOBALS["db"]->execQuery("
            UPDATE public.skyrim_quest_instances
            SET run_state = 'running',
                current_stage = {$stage},
                last_gamets = {$lastGametsSql},
                state_json = jsonb_set(COALESCE(state_json, '{}'::jsonb), '{current_stage}', to_jsonb({$stage}), true),
                updated_at = now()
            WHERE quest_key = '{$questKeyEscaped}'
        ");
    }
}

if (!function_exists('chimQuestEngineResetFailedAcknowledgedActionBeat')) {
    function chimQuestEngineResetFailedAcknowledgedActionBeat(array $row)
    {
        $actionPayload = chimQuestEngineJsonDecode($row['payload_json'] ?? '{}', array());
        if (!is_array($actionPayload)) {
            $actionPayload = array();
        }
        $actionType = strtolower(trim((string)($actionPayload['type'] ?? $row['action_type'] ?? '')));
        if (!chimQuestEngineActionRequiresAppliedAckForState($actionType)) {
            return;
        }

        $questKey = chimQuestEngineNormalizeQuestKey($row['quest_key'] ?? '');
        $beatId = trim((string)($row['beat_id'] ?? ($actionPayload['beat_id'] ?? '')));
        if ($questKey === '' || $beatId === '') {
            return;
        }

        $questKeyEscaped = $GLOBALS["db"]->escape($questKey);
        $beatIdEscaped = $GLOBALS["db"]->escape($beatId);
        $GLOBALS["db"]->execQuery("
            UPDATE public.skyrim_quest_beat_state
               SET fired = false,
                   fired_order = NULL,
                   fired_gamets = NULL,
                   evidence_json = '{}'::jsonb,
                   updated_at = now()
             WHERE quest_key = '{$questKeyEscaped}'
               AND beat_id = '{$beatIdEscaped}'
        ");

        $activeBeatRow = $GLOBALS["db"]->fetchOne("
            SELECT COUNT(*) AS n
            FROM public.skyrim_quest_beat_state
            WHERE quest_key = '{$questKeyEscaped}'
              AND fired = true
        ");
        if (intval($activeBeatRow['n'] ?? 0) > 0) {
            return;
        }

        $GLOBALS["db"]->execQuery("
            UPDATE public.skyrim_quest_instances
               SET run_state = 'inactive',
                   current_stage = NULL,
                   state_json = jsonb_set(COALESCE(state_json, '{}'::jsonb) - 'run_state', '{current_stage}', 'null'::jsonb, true),
                   updated_at = now()
             WHERE quest_key = '{$questKeyEscaped}'
               AND current_stage IS NULL
        ");
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
            SELECT id, quest_key, beat_id, action_type, action_gamets, payload_json
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

        if ($statusCn === 'applied') {
            chimQuestEngineApplyAcknowledgedActionState($row, $result);
        } else {
            chimQuestEngineResetFailedAcknowledgedActionBeat($row);
        }

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
            if (!chimQuestEngineBeatFocusNpcMatches($beat, $npcNameCn, true)) {
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

if (!function_exists('chimQuestEngineSuppressedActionsForTurn')) {
    function chimQuestEngineSuppressedActionsForTurn($npcName, $locationName = '')
    {
        if (!chimQuestEngineFeatureEnabled() || !chimQuestEngineReady()) {
            return array();
        }

        $npcNameCn = trim((string)$npcName);
        if ($npcNameCn === '' || strcasecmp($npcNameCn, 'The Narrator') === 0 || strcasecmp($npcNameCn, 'Player') === 0) {
            return array();
        }

        chimQuestEngineMaybeBootstrapBundledDefinitions();
        $suppressed = array();
        $payload = array(
            'npc_name' => $npcNameCn,
            'location_name' => trim((string)$locationName),
        );

        foreach (chimQuestEngineFetchDefinitions(true) as $definition) {
            if (chimQuestEngineIsRadiantTemplate($definition)) {
                continue;
            }

            $instance = chimQuestEngineGetInstance($definition['quest_key']);
            if (!$instance) {
                continue;
            }
            $instance['state_json'] = chimQuestEngineNormalizeState($instance['state_json'] ?? array());
            if (!empty($definition['radiant_instance'])) {
                $minimumStage = chimQuestEngineRadiantMinimumQuestStage($definition);
                if ($minimumStage !== null && ($instance['current_stage'] === null || intval($instance['current_stage']) < $minimumStage)) {
                    continue;
                }
            }

            $beatStateMap = chimQuestEngineLoadBeatStateMap($definition['quest_key']);
            foreach ($definition['beats'] ?? array() as $beat) {
                if (!is_array($beat)) {
                    continue;
                }

                $beatId = (string)($beat['id'] ?? '');
                if ($beatId === '' || !empty($beatStateMap[$beatId]['fired'])) {
                    continue;
                }
                if (empty($beat['suppress_actions']) || !is_array($beat['suppress_actions'])) {
                    continue;
                }
                if (!chimQuestEngineBeatFocusNpcMatches($beat, $npcNameCn, false)) {
                    continue;
                }
                if (!chimQuestEngineBeatHasDialogueTrigger($beat)) {
                    continue;
                }
                if (!chimQuestEngineBeatAllowedForRuntime($definition, $beat, 'dialogue_turn', $payload, $instance)) {
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

                $questKey = (string)($definition['quest_key'] ?? '');
                foreach ($beat['suppress_actions'] as $actionCode) {
                    $actionCodeCn = trim((string)$actionCode);
                    if ($actionCodeCn === '') {
                        continue;
                    }
                    if (!isset($suppressed[$actionCodeCn])) {
                        $suppressed[$actionCodeCn] = array();
                    }
                    $suppressed[$actionCodeCn][] = $questKey . '/' . $beatId;
                }
            }
        }

        return $suppressed;
    }
}

if (!function_exists('chimQuestEngineIsActionSuppressedForTurn')) {
    function chimQuestEngineIsActionSuppressedForTurn($actionCodeName)
    {
        $actionCodeNameCn = trim((string)$actionCodeName);
        if ($actionCodeNameCn === '') {
            return false;
        }

        $suppressed = $GLOBALS['CHIM_QUEST_SUPPRESSED_ACTIONS'] ?? array();
        return is_array($suppressed) && in_array($actionCodeNameCn, $suppressed, true);
    }
}

if (!function_exists('chimQuestEngineApplyActionSuppressionsForTurn')) {
    function chimQuestEngineApplyActionSuppressionsForTurn($npcName, $locationName = '')
    {
        $suppressed = chimQuestEngineSuppressedActionsForTurn($npcName, $locationName);
        $GLOBALS['CHIM_QUEST_SUPPRESSED_ACTIONS'] = array_keys($suppressed);
        $GLOBALS['CHIM_QUEST_SUPPRESSED_ACTION_REASONS'] = $suppressed;

        if (empty($suppressed)) {
            return array();
        }

        foreach ($suppressed as $actionCode => $reasons) {
            if (function_exists('unsetFunction')) {
                unsetFunction($actionCode);
            } elseif (isset($GLOBALS["ENABLED_FUNCTIONS"]) && is_array($GLOBALS["ENABLED_FUNCTIONS"])) {
                $GLOBALS["ENABLED_FUNCTIONS"] = array_values(array_filter(
                    $GLOBALS["ENABLED_FUNCTIONS"],
                    function ($enabledAction) use ($actionCode) {
                        return trim((string)$enabledAction) !== $actionCode;
                    }
                ));
            }
            error_log("[AI Quest] Suppressed action {$actionCode} for {$npcName}: " . implode(', ', $reasons));
        }

        return $suppressed;
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
            if (chimQuestEngineIsRadiantTemplate($definition)) {
                continue;
            }
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
            if (!empty($definition['radiant_instance'])) {
                $minimumStage = chimQuestEngineRadiantMinimumQuestStage($definition);
                if ($minimumStage !== null && ($instance['current_stage'] === null || intval($instance['current_stage']) < $minimumStage)) {
                    continue;
                }
            }
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
