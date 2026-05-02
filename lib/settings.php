<?php

if (!function_exists('chimSettingsDb')) {
    function chimSettingsDb()
    {
        return $GLOBALS["db"] ?? null;
    }
}

if (!function_exists('chimSettingsStringifyValue')) {
    function chimSettingsStringifyValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if ($value === null) {
            return '';
        }

        return strval($value);
    }
}

if (!function_exists('chimSettingsNormalizeScalar')) {
    function chimSettingsNormalizeScalar(string $rawValue, array $definition = [])
    {
        $type = strtolower(trim(strval($definition['type'] ?? 'string')));

        if ($type === 'boolean') {
            $normalized = strtolower(trim($rawValue));
            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        if ($type === 'integer' || $type === 'int') {
            return intval($rawValue);
        }

        if ($type === 'number' || $type === 'float' || $type === 'double') {
            return floatval($rawValue);
        }

        if ($type === 'selectmultiple') {
            $decoded = json_decode($rawValue, true);
            return is_array($decoded) ? $decoded : [];
        }

        return $rawValue;
    }
}

if (!function_exists('chimLoadRawConfSchema')) {
    function chimLoadRawConfSchema(): array
    {
        static $schema = null;
        if (is_array($schema)) {
            return $schema;
        }

        $schemaPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . "conf" . DIRECTORY_SEPARATOR . "conf_schema.json";
        $decoded = @json_decode(@file_get_contents($schemaPath), true);
        $schema = is_array($decoded) ? $decoded : [];
        return $schema;
    }
}

if (!function_exists('chimFlattenConfSchema')) {
    function chimFlattenConfSchema(array $node = null, string $prefix = ''): array
    {
        if ($node === null) {
            $node = chimLoadRawConfSchema();
        }

        $flat = [];
        foreach ($node as $key => $value) {
            if (!is_array($value) || strpos(strval($key), '_') === 0) {
                continue;
            }

            $flatKey = ($prefix === '') ? strval($key) : ($prefix . '@' . strval($key));
            if (array_key_exists('type', $value)) {
                $flat[$flatKey] = $value;
            }

            foreach ($value as $childKey => $childValue) {
                if (strpos(strval($childKey), '_') === 0) {
                    continue;
                }

                if (is_array($childValue) && array_key_exists('type', $childValue)) {
                    $childFlatKey = $flatKey . '@' . strval($childKey);
                    $flat[$childFlatKey] = $childValue;
                } elseif (is_array($childValue)) {
                    $flat = array_merge($flat, chimFlattenConfSchema([$childKey => $childValue], $flatKey));
                }
            }
        }

        return $flat;
    }
}

if (!function_exists('chimGetSchemaDefinition')) {
    function chimGetSchemaDefinition(string $id): array
    {
        static $definitions = null;
        if (!is_array($definitions)) {
            $definitions = chimFlattenConfSchema();
        }

        return $definitions[$id] ?? [];
    }
}

if (!function_exists('chimGetSchemaDescription')) {
    function chimGetSchemaDescription(string $id): string
    {
        $definition = chimGetSchemaDefinition($id);
        return strval($definition['description'] ?? '');
    }
}

if (!function_exists('chimReadLegacyGlobalValue')) {
    function chimReadLegacyGlobalValue(string $flatId, $default = null)
    {
        if (strpos($flatId, '@') === false) {
            return array_key_exists($flatId, $GLOBALS) ? $GLOBALS[$flatId] : $default;
        }

        $parts = explode('@', $flatId);
        $cursor = $GLOBALS;
        foreach ($parts as $part) {
            if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
                return $default;
            }
            $cursor = $cursor[$part];
        }

        return $cursor;
    }
}

if (!function_exists('chimGetManagedGeneralSettingIds')) {
    function chimGetManagedGeneralSettingIds(): array
    {
        return [
            'AUTO_LOCK_PROFILE',
            'AUTOFILL_CUSTOM_PROFILES',
            'AUTOFILL_CUSTOM_PROFILES_TRIGGER',
            'BGL_TRIGGER_DAYS',
            'END_CONVERSATION_COOLDOWN',
            'CLEAN_CONTEXT_FOCUS_CHAT_HISTORY',
            'FEATURES@MEMORY_EMBEDDING@ENABLED',
            'FEATURES@MEMORY_EMBEDDING@USE_TEXT2VEC',
            'FEATURES@MEMORY_EMBEDDING@AUTO_CREATE_SUMMARY_INTERVAL',
            'FEATURES@MEMORY_EMBEDDING@AUTO_CREATE_SUMMARYS',
            'PROMPT_HEAD',
            'EMOTEMOODS',
            'DETECT_MAGIC_EVENT',
            'MAGIC_EVENT_BLACKLIST',
            'LOCATION_BLACKLIST',
            'ITEM_BLACKLIST',
            'EVENT_TYPE_FILTER',
            'GROUND_ITEMS_DESCRIPTIONS_ONLY',
            'INVENTORY_ITEMS_DESCRIPTIONS_ONLY',
            'HIDE_AMBIENT_COMBAT',
            'DISABLE_REANIMATION_TRACKING',
            'PROMPT_TIMESTAMP',
            'RECHAT_MODE',
            'OPEN_RECHAT',
            'CORE_CONNECTOR_PLAYER',
            'CORE_CONNECTOR_SUMMARY',
            'CORE_CONNECTOR_MEDIUMTERM',
            'CORE_CONNECTOR_SCENECLASSIFIER',
            'CORE_CONNECTOR_PROFILES',
            'CORE_CONNECTOR_DIRECTOR',
            'RELLLM_CONNECTOR',
            'CORE_CONNECTOR_OGHMA_CUSTOM',
            'RELATIONSHIP_SYSTEM_ENABLED',
            'SCENE_CLASSIFIER_ENABLED',
            'POWER_AWARENESS_ENABLED',
            'OGHMA_CUSTOM',
            'TRANSLATION_FUNCTION',
            'TRANSLATION@settings@translate_audio',
            'TRANSLATION@settings@translate_text',
            'TRANSLATION@settings@save_translated_text',
            'TRANSLATION@settings@translate_player_audio',
            'TRANSLATION@settings@save_translated_player_text',
            'TRANSLATION@DeepL@source_language',
            'TRANSLATION@DeepL@target_language',
            'TRANSLATION@DeepL@url',
            'TRANSLATION@DeepL@player_source_language',
            'TRANSLATION@DeepL@player_target_language',
        ];
    }
}

if (!function_exists('chimGetManagedGeneralSettingDescriptions')) {
    function chimGetManagedGeneralSettingDescriptions(): array
    {
        $descriptions = [];
        foreach (chimGetManagedGeneralSettingIds() as $id) {
            $description = chimGetSchemaDescription($id);
            if ($description !== '') {
                $descriptions[$id] = $description;
            }
        }

        $descriptions['FEATURES@MEMORY_EMBEDDING@AUTO_CREATE_SUMMARYS'] = 'Compatibility flag preserved during settings migration so automatic memory summary creation continues to behave like the legacy Global Settings save path.';
        $descriptions['GLOBAL_STT_CONNECTOR_ID'] = 'Active global STT connector. Only one STT connector is used globally for player speech-to-text.';
        $descriptions['GLOBAL_ITT_CONNECTOR_ID'] = 'Active global ITT connector. Only one ITT connector is used globally for image-to-text and Soulgaze.';

        return $descriptions;
    }
}

if (!function_exists('chimGetGeneralSettingRow')) {
    function chimGetGeneralSettingRow(string $id): array
    {
        $db = chimSettingsDb();
        if (!$db) {
            return [];
        }

        $safeId = trim($id);
        if ($safeId === '') {
            return [];
        }

        $query = "SELECT id, value, description, updated_at FROM public.general_settings WHERE id = " . $db->escapeLiteral($safeId) . " LIMIT 1";
        $row = $db->fetchOne($query);
        return is_array($row) ? $row : [];
    }
}

if (!function_exists('chimGetGeneralSetting')) {
    function chimGetGeneralSetting(string $id, string $default = ''): string
    {
        $row = chimGetGeneralSettingRow($id);
        if (!$row) {
            return $default;
        }

        return strval($row['value'] ?? $default);
    }
}

if (!function_exists('chimGetGeneralSettingBool')) {
    function chimGetGeneralSettingBool(string $id, bool $default = false): bool
    {
        $value = chimGetGeneralSetting($id, $default ? 'true' : 'false');
        return (bool)chimSettingsNormalizeScalar($value, ['type' => 'boolean']);
    }
}

if (!function_exists('chimGetGeneralSettingInt')) {
    function chimGetGeneralSettingInt(string $id, int $default = 0): int
    {
        $value = chimGetGeneralSetting($id, strval($default));
        return intval(chimSettingsNormalizeScalar($value, ['type' => 'integer']));
    }
}

if (!function_exists('chimGetGeneralSettingFloat')) {
    function chimGetGeneralSettingFloat(string $id, float $default = 0.0): float
    {
        $value = chimGetGeneralSetting($id, strval($default));
        return floatval(chimSettingsNormalizeScalar($value, ['type' => 'number']));
    }
}

if (!function_exists('chimGetAllGeneralSettings')) {
    function chimGetAllGeneralSettings(): array
    {
        $db = chimSettingsDb();
        if (!$db) {
            return [];
        }

        try {
            $rows = $db->fetchAll("SELECT id, value, description, updated_at FROM public.general_settings ORDER BY id ASC");
        } catch (\Throwable $e) {
            return [];
        }

        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('chimSetGeneralSetting')) {
    function chimSetGeneralSetting(string $id, $value, ?string $description = null): bool
    {
        $db = chimSettingsDb();
        if (!$db) {
            return false;
        }

        $safeId = trim($id);
        if ($safeId === '') {
            return false;
        }

        $valueLiteral = $db->escapeLiteral(chimSettingsStringifyValue($value));
        $descriptionSql = ($description === null)
            ? "description"
            : $db->escapeLiteral($description);

        $query = "
            INSERT INTO public.general_settings (id, value, description, updated_at)
            VALUES (" . $db->escapeLiteral($safeId) . ", {$valueLiteral}, " . (($description === null) ? "''" : $descriptionSql) . ", CURRENT_TIMESTAMP)
            ON CONFLICT (id) DO UPDATE SET
                value = EXCLUDED.value,
                description = " . (($description === null) ? "public.general_settings.description" : "EXCLUDED.description") . ",
                updated_at = CURRENT_TIMESTAMP
        ";

        return $db->execQuery($query) !== false;
    }
}

if (!function_exists('chimSetGeneralSettingDescription')) {
    function chimSetGeneralSettingDescription(string $id, string $description): bool
    {
        $db = chimSettingsDb();
        if (!$db) {
            return false;
        }

        $safeId = trim($id);
        if ($safeId === '') {
            return false;
        }

        $query = "
            INSERT INTO public.general_settings (id, value, description, updated_at)
            VALUES (" . $db->escapeLiteral($safeId) . ", '', " . $db->escapeLiteral($description) . ", CURRENT_TIMESTAMP)
            ON CONFLICT (id) DO UPDATE SET
                description = EXCLUDED.description,
                updated_at = CURRENT_TIMESTAMP
        ";

        return $db->execQuery($query) !== false;
    }
}

if (!function_exists('chimGeneralSettingsToLegacyGlobals')) {
    function chimGeneralSettingsToLegacyGlobals(array $rows): void
    {
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['id'])) {
                continue;
            }

            $flatId = strval($row['id']);
            $rawValue = strval($row['value'] ?? '');
            $definition = chimGetSchemaDefinition($flatId);
            $normalizedValue = chimSettingsNormalizeScalar($rawValue, $definition);

            if (strpos($flatId, '@') === false) {
                $GLOBALS[$flatId] = $normalizedValue;
                continue;
            }

            $parts = explode('@', $flatId);
            chimAssignNestedGlobalValueToGlobals($parts, $normalizedValue);
        }
    }
}

if (!function_exists('chimAssignNestedGlobalValueToGlobals')) {
    function chimAssignNestedGlobalValueToGlobals(array $parts, $value): void
    {
        if (empty($parts)) {
            return;
        }

        $rootKey = strval(array_shift($parts));
        if ($rootKey === '') {
            return;
        }

        if (empty($parts)) {
            $GLOBALS[$rootKey] = $value;
            return;
        }

        if (!isset($GLOBALS[$rootKey]) || !is_array($GLOBALS[$rootKey])) {
            $GLOBALS[$rootKey] = [];
        }

        $cursor =& $GLOBALS[$rootKey];
        $lastIndex = count($parts) - 1;
        foreach ($parts as $index => $part) {
            $part = strval($part);
            if ($part === '') {
                return;
            }

            if ($index === $lastIndex) {
                $cursor[$part] = $value;
                return;
            }

            if (!isset($cursor[$part]) || !is_array($cursor[$part])) {
                $cursor[$part] = [];
            }
            $cursor =& $cursor[$part];
        }
    }
}

if (!function_exists('chimAssignNestedGlobalValue')) {
    function chimAssignNestedGlobalValue(array &$target, array $parts, $value, int $index = 0): void
    {
        $part = strval($parts[$index] ?? '');
        if ($part === '') {
            return;
        }

        if ($index >= (count($parts) - 1)) {
            $target[$part] = $value;
            return;
        }

        if (!isset($target[$part]) || !is_array($target[$part])) {
            $target[$part] = [];
        }

        chimAssignNestedGlobalValue($target[$part], $parts, $value, $index + 1);
    }
}

if (!function_exists('chimLoadGeneralSettingsIntoGlobals')) {
    function chimLoadGeneralSettingsIntoGlobals(): void
    {
        try {
            $rows = chimGetAllGeneralSettings();
        } catch (\Throwable $e) {
            $rows = [];
        }

        if (!empty($rows)) {
            chimGeneralSettingsToLegacyGlobals($rows);
        }
    }
}

if (!function_exists('chimLoadActiveSttConnectorIntoGlobals')) {
    function chimLoadActiveSttConnectorIntoGlobals(): void
    {
        $connectorId = chimGetGeneralSettingInt('GLOBAL_STT_CONNECTOR_ID', 0);
        if ($connectorId <= 0) {
            return;
        }

        if (!class_exists('STTConnector')) {
            require_once(__DIR__ . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "stt_connector.class.php");
        }

        $connector = new STTConnector();
        try {
            $row = $connector->getById($connectorId);
        } catch (\Throwable $e) {
            $row = [];
        }
        if ($row) {
            $connector->setOldGlobals($row);
        }
    }
}

if (!function_exists('chimLoadActiveIttConnectorIntoGlobals')) {
    function chimLoadActiveIttConnectorIntoGlobals(): void
    {
        $connectorId = chimGetGeneralSettingInt('GLOBAL_ITT_CONNECTOR_ID', 0);
        if ($connectorId <= 0) {
            return;
        }

        if (!class_exists('ITTConnector')) {
            require_once(__DIR__ . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "itt_connector.class.php");
        }

        $connector = new ITTConnector();
        try {
            $row = $connector->getById($connectorId);
        } catch (\Throwable $e) {
            $row = [];
        }
        if ($row) {
            $connector->setOldGlobals($row);
        }
    }
}

if (!function_exists('chimHydrateLegacyGlobalsFromDb')) {
    function chimHydrateLegacyGlobalsFromDb(): void
    {
        chimLoadGeneralSettingsIntoGlobals();
        chimLoadActiveSttConnectorIntoGlobals();
        chimLoadActiveIttConnectorIntoGlobals();
    }
}

if (!function_exists('chimLoadPlayerNameIntoGlobals')) {
    function chimLoadPlayerNameIntoGlobals(): void
    {
        if (!class_exists('Player')) {
            require_once(__DIR__ . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "player.class.php");
        }

        try {
            $player = new Player();
            $playerNameFromTable = $player->get('player_name');
            if ($playerNameFromTable !== null && $playerNameFromTable !== '') {
                $GLOBALS["PLAYER_NAME"] = $playerNameFromTable;
                return;
            }
        } catch (\Throwable $e) {
        }

        $db = chimSettingsDb();
        if (!$db) {
            return;
        }

        try {
            $playerNameFromDb = $db->fetchOne("SELECT value FROM conf_opts WHERE id='PLAYER_NAME'");
        } catch (\Throwable $e) {
            $playerNameFromDb = [];
        }

        if ($playerNameFromDb && !empty($playerNameFromDb['value'])) {
            $GLOBALS["PLAYER_NAME"] = $playerNameFromDb['value'];
        }
    }
}

if (!function_exists('chimLoadNarratorSettingsIntoGlobals')) {
    function chimLoadNarratorSettingsIntoGlobals(): void
    {
        if (!class_exists('Narrator')) {
            require_once(__DIR__ . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "narrator.class.php");
        }

        try {
            $narrator = new Narrator();
            $narrator->loadIntoGlobals();
        } catch (\Throwable $e) {
        }
    }
}

?>
