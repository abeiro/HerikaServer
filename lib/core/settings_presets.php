<?php

require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'settings.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'prisma_settings_catalog.php');

function chimSettingsPresetProfileDefaults(): array
{
    return [
        'CONTEXT_HISTORY' => 50,
        'CONTEXT_HISTORY_DIARY' => 100,
        'CONTEXT_HISTORY_DYNAMIC_PROFILE' => 50,
        'MAX_WORDS_LIMIT' => 0,
        'chim_context_mode' => 0,
    ];
}

function chimSettingsPresetDefaultGlobalSettings(): array
{
    return [
        'RELATIONSHIP_UPDATE_CHANCE' => 50,
        'FEATURES@MEMORY_EMBEDDING@ENABLED' => true,
        'AUTOFILL_CUSTOM_PROFILES' => true,
        'BGL_TRIGGER_HOURS' => 24,
        'CHIM_AI_QUEST_PROGRESSION' => false,
        'DETECT_MAGIC_EVENT' => true,
        'GROUND_ITEMS_DESCRIPTIONS_ONLY' => false,
        'INVENTORY_ITEMS_DESCRIPTIONS_ONLY' => false,
        'HIDE_AMBIENT_COMBAT' => false,
        'DISABLE_REANIMATION_TRACKING' => true,
        'TRANSFORMATION_DETECTION' => true,
        'POWER_AWARENESS_ENABLED' => false,
        'CHIM_ITEM_PICKUP_EVENTLOG_MIN_VALUE' => 500,
        'PROMPT_TIMESTAMP' => false,
    ] + chimSettingsPresetConnectorAvailability(true);
}

/**
 * Availability switches for the eight Global Connector slots. Presets only flip these
 * booleans, so connector assignments, credentials, models and URLs survive untouched.
 */
function chimSettingsPresetConnectorAvailability(bool $available): array
{
    return [
        'PLAYER_RESPEECH' => $available,
        'CORE_CONNECTOR_SUMMARY_ENABLED' => $available,
        'CORE_CONNECTOR_MEDIUMTERM_ENABLED' => $available,
        'SCENE_CLASSIFIER_ENABLED' => $available,
        'CORE_CONNECTOR_PROFILES_ENABLED' => $available,
        'CORE_CONNECTOR_DIRECTOR_ENABLED' => $available,
        'CORE_CONNECTOR_BGL_ENABLED' => $available,
        'RELATIONSHIP_SYSTEM_ENABLED' => $available,
    ];
}

function chimSettingsPresetDefaultProfileRuntimeValues(): array
{
    return [
        'RECHAT_H' => 2,
        'RECHAT_P' => 50,
        'RECHAT_ALLOW_ACTIONS' => false,
        'BORED_EVENT' => 30,
        'RPG_COMMENTS_CHANCE' => 50,
        'COMBAT_BARK_COOLDOWN' => 30,
        'QUEST_COMMENT' => false,
    ];
}

function chimSettingsPresetDefaultProfileOverrides(): array
{
    return [
        'DYNAMIC_PROFILE_ENABLED' => false,
        'MIDDLE_TERM_MEMORY_ENABLED' => false,
        'AUTO_DIARY_ENABLED' => false,
        'AUTO_DIARY_WAIT_ENABLED' => false,
        'MATERIALIZE_DIARY_ENABLED' => false,
        'LATEST_DIARY_CONTEXT_ENABLED' => false,
        'LLM_RANDOMIZER_ENABLED' => false,
    ] + chimSettingsPresetDefaultProfileRuntimeValues();
}

function chimSettingsPresetLocalProfileRuntimeValues(): array
{
    return [
        'RECHAT_H' => 1,
        'RECHAT_P' => 0,
        'RECHAT_ALLOW_ACTIONS' => false,
        'BORED_EVENT' => 0,
        'RPG_COMMENTS_CHANCE' => 0,
        'COMBAT_BARK_COOLDOWN' => 600,
        'QUEST_COMMENT' => false,
    ];
}

function chimSettingsPresetBuiltIns(): array
{
    // Fresh installs seed the first profile at 75 events while later profiles inherit 50.
    $defaultProfileValues = chimSettingsPresetProfileDefaults();
    $defaultProfileValues['CONTEXT_HISTORY'] = 75;

    return [
        'builtin:default' => [
            'id' => 'builtin:default',
            'name' => 'Default',
            'built_in' => true,
            'description' => '',
            'affects_profiles' => true,
            'snapshot' => [
                'version' => 1,
                'global_settings' => chimSettingsPresetDefaultGlobalSettings(),
                'prompt_context_options' => chimGetDefaultPromptContextOptions(),
                'profile_defaults' => chimSettingsPresetProfileDefaults(),
                'profiles' => [],
                'built_in_profile_values' => $defaultProfileValues,
                'built_in_profile_overrides' => chimSettingsPresetDefaultProfileOverrides(),
                'profile_runtime_defaults' => chimSettingsPresetDefaultProfileRuntimeValues(),
            ],
        ],
        'builtin:local_llm' => [
            'id' => 'builtin:local_llm',
            'name' => 'Local LLM',
            'built_in' => true,
            'description' => '',
            'affects_profiles' => true,
            'snapshot' => [
                'version' => 1,
                'global_settings' => [
                    'RELATIONSHIP_UPDATE_CHANCE' => 0,
                    'FEATURES@MEMORY_EMBEDDING@ENABLED' => false,
                    'AUTOFILL_CUSTOM_PROFILES' => false,
                    'BGL_TRIGGER_HOURS' => 720,
                    'CHIM_AI_QUEST_PROGRESSION' => false,
                    'DETECT_MAGIC_EVENT' => false,
                    'GROUND_ITEMS_DESCRIPTIONS_ONLY' => true,
                    'INVENTORY_ITEMS_DESCRIPTIONS_ONLY' => true,
                    'HIDE_AMBIENT_COMBAT' => true,
                    'DISABLE_REANIMATION_TRACKING' => true,
                    'TRANSFORMATION_DETECTION' => false,
                    'POWER_AWARENESS_ENABLED' => false,
                    'CHIM_ITEM_PICKUP_EVENTLOG_MIN_VALUE' => 1000,
                    'PROMPT_TIMESTAMP' => false,
                    'PLAYER_RESPEECH' => true,
                    'CORE_CONNECTOR_DIRECTOR_ENABLED' => true,
                ] + chimSettingsPresetConnectorAvailability(false),
                'prompt_context_options' => chimNormalizePromptContextOptions([
                    'enabled_sections' => [
                        'roleplay_instructions', 'world', 'knowledge', 'available_actions_list',
                        'nearby_actors', 'nearby_items', 'adventuring_party', 'scene_notes',
                        'paralinguistic_tags',
                    ],
                    'enabled_character_subsections' => [
                        'basic_summary', 'personality', 'relationships', 'occupation',
                        'speech_style', 'goals', 'quest_topics',
                    ],
                    'enabled_appearance_subsections' => [
                        'appearance', 'equipment', 'current_activity', 'current_condition',
                    ],
                    'enabled_general_subsections' => ['current_plans'],
                    'enabled_nearby_actor_subsections' => ['current_activity'],
                    'enabled_nearby_item_subsections' => ['group_duplicates'],
                ]),
                'profile_defaults' => [
                    'CONTEXT_HISTORY' => 20,
                    'CONTEXT_HISTORY_DIARY' => 20,
                    'CONTEXT_HISTORY_DYNAMIC_PROFILE' => 20,
                    'MAX_WORDS_LIMIT' => 60,
                    'chim_context_mode' => 1,
                ],
                'profiles' => [],
                'built_in_profile_values' => [
                    'CONTEXT_HISTORY' => 20,
                    'CONTEXT_HISTORY_DIARY' => 20,
                    'CONTEXT_HISTORY_DYNAMIC_PROFILE' => 20,
                    'MAX_WORDS_LIMIT' => 60,
                    'chim_context_mode' => 1,
                ],
                'built_in_profile_overrides' => [
                    'DYNAMIC_PROFILE_ENABLED' => false,
                    'MIDDLE_TERM_MEMORY_ENABLED' => false,
                    'AUTO_DIARY_ENABLED' => false,
                    'AUTO_DIARY_WAIT_ENABLED' => false,
                    'MATERIALIZE_DIARY_ENABLED' => false,
                    'LATEST_DIARY_CONTEXT_ENABLED' => false,
                    'LLM_RANDOMIZER_ENABLED' => false,
                ] + chimSettingsPresetLocalProfileRuntimeValues(),
                'profile_runtime_defaults' => chimSettingsPresetLocalProfileRuntimeValues(),
            ],
        ],
    ];
}

function chimSettingsPresetFieldMap(): array
{
    $map = [];
    foreach (chimPrismaGlobalSettingsSections() as $fields) {
        foreach ($fields as $field) {
            $map[(string)$field['name']] = $field;
        }
    }
    return $map;
}

function chimSettingsPresetSafeFields(): array
{
    $safe = [];
    foreach (chimSettingsPresetFieldMap() as $name => $field) {
        $type = strtolower((string)($field['type'] ?? 'string'));
        if (strpos($type, 'foreign:') === 0 || $type === 'url') {
            continue;
        }
        $safe[$name] = $field;
    }
    return $safe;
}

function chimSettingsPresetNormalizeSetting($value, array $field)
{
    $type = strtolower((string)($field['type'] ?? 'string'));
    if ($type === 'boolean') {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
    }
    if ($type === 'integer') {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException('Expected an integer.');
        }
        $value = (int)$value;
    } elseif ($type === 'number') {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException('Expected a number.');
        }
        $value = (float)$value;
    } else {
        $value = (string)$value;
    }
    if (isset($field['values']) && !in_array((string)$value, array_map('strval', $field['values']), true)) {
        throw new InvalidArgumentException('Value is not allowed.');
    }
    if (isset($field['min']) && $value < $field['min']) {
        $value = $field['min'];
    }
    if (isset($field['max']) && $value > $field['max']) {
        $value = $field['max'];
    }
    return $value;
}

function chimSettingsPresetNormalizeSettings(array $settings): array
{
    $known = chimSettingsPresetFieldMap();
    $safe = chimSettingsPresetSafeFields();
    $normalized = [];
    foreach ($settings as $name => $value) {
        $name = (string)$name;
        if (!isset($known[$name])) {
            throw new InvalidArgumentException("Unknown setting: {$name}");
        }
        if (!isset($safe[$name])) {
            continue;
        }
        try {
            $normalized[$name] = chimSettingsPresetNormalizeSetting($value, $safe[$name]);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException("Invalid {$name}: " . $e->getMessage());
        }
    }
    return $normalized;
}

function chimSettingsPresetNormalizeProfileValues(array $values): array
{
    $limits = [
        'CONTEXT_HISTORY' => [0, 200],
        'CONTEXT_HISTORY_DIARY' => [0, 400],
        'CONTEXT_HISTORY_DYNAMIC_PROFILE' => [0, 400],
        'MAX_WORDS_LIMIT' => [0, 10000],
        'chim_context_mode' => [0, 1],
    ];
    $defaults = chimSettingsPresetProfileDefaults();
    $normalized = [];
    foreach ($limits as $name => [$min, $max]) {
        $value = $values[$name] ?? $defaults[$name];
        if (!is_numeric($value)) {
            $value = $defaults[$name];
        }
        $normalized[$name] = max($min, min($max, (int)$value));
    }
    return $normalized;
}

function chimSettingsPresetProfileFieldMap(): array
{
    $map = [];
    foreach (chimPrismaProfileMetadataCatalog() as $fields) {
        foreach ($fields as $field) {
            $map[(string)$field['name']] = $field;
        }
    }
    return $map;
}

function chimSettingsPresetNormalizeProfileOverrides(array $values): array
{
    $fields = chimSettingsPresetProfileFieldMap();
    $normalized = [];
    foreach ($values as $name => $value) {
        $name = (string)$name;
        if (!isset($fields[$name])) {
            throw new InvalidArgumentException("Unknown profile setting: {$name}");
        }
        $field = $fields[$name];
        $type = strtolower((string)($field['type'] ?? 'string'));
        if ($type === 'boolean') {
            $normalized[$name] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            continue;
        }
        if ($type === 'integer') {
            if (!is_numeric($value)) {
                throw new InvalidArgumentException("Invalid {$name}: Expected an integer.");
            }
            $value = (int)$value;
            if (isset($field['min'])) $value = max((int)$field['min'], $value);
            if (isset($field['max'])) $value = min((int)$field['max'], $value);
            $normalized[$name] = $value;
            continue;
        }
        if ($type === 'multiselect') {
            if (!is_array($value)) {
                throw new InvalidArgumentException("Invalid {$name}: Expected a list.");
            }
            $normalized[$name] = array_values(array_filter(array_map('strval', $value), static fn(string $item): bool => $item !== ''));
            continue;
        }
        $value = (string)$value;
        if (isset($field['values']) && !in_array($value, array_map('strval', $field['values']), true)) {
            throw new InvalidArgumentException("Invalid {$name}: Value is not allowed.");
        }
        $normalized[$name] = $value;
    }
    return $normalized;
}

function chimSettingsPresetCaptureProfiles(): array
{
    $db = $GLOBALS['db'] ?? null;
    if (!$db) {
        throw new RuntimeException('Database connection is unavailable.');
    }
    $defaults = chimSettingsPresetProfileDefaults();
    foreach (array_keys($defaults) as $name) {
        $row = $db->fetchOne('SELECT value FROM public.conf_opts WHERE id = ' . $db->escapeLiteral($name) . ' LIMIT 1');
        if (isset($row['value'])) {
            $defaults[$name] = $row['value'];
        }
    }
    $defaults = chimSettingsPresetNormalizeProfileValues($defaults);

    $runtimeDefaults = chimSettingsPresetDefaultProfileRuntimeValues();
    foreach (array_keys($runtimeDefaults) as $name) {
        $row = $db->fetchOne('SELECT value FROM public.conf_opts WHERE id = ' . $db->escapeLiteral($name) . ' LIMIT 1');
        if (isset($row['value'])) {
            $runtimeDefaults[$name] = $row['value'];
        }
    }
    $runtimeDefaults = chimSettingsPresetNormalizeProfileOverrides($runtimeDefaults);

    $profiles = [];
    $profileOverrides = [];
    $overrideDefaults = chimSettingsPresetDefaultProfileOverrides();
    foreach ($db->fetchAll('SELECT id, metadata FROM public.core_profiles ORDER BY id ASC') as $row) {
        $id = (string)(int)$row['id'];
        $metadata = json_decode((string)($row['metadata'] ?? '{}'), true);
        if (!is_array($metadata)) {
            $metadata = [];
        }
        $values = $defaults;
        foreach (['CONTEXT_HISTORY', 'CONTEXT_HISTORY_DIARY', 'CONTEXT_HISTORY_DYNAMIC_PROFILE', 'MAX_WORDS_LIMIT'] as $name) {
            if (array_key_exists($name, $metadata)) {
                $values[$name] = $metadata[$name];
            }
        }
        $profiles[$id] = chimSettingsPresetNormalizeProfileValues($values);

        $overrides = $overrideDefaults;
        foreach (array_keys($overrides) as $name) {
            if (array_key_exists($name, $metadata)) {
                $overrides[$name] = $metadata[$name];
            } elseif (array_key_exists($name, $runtimeDefaults)) {
                $overrides[$name] = $runtimeDefaults[$name];
            }
        }
        $profileOverrides[$id] = chimSettingsPresetNormalizeProfileOverrides($overrides);
    }
    return [
        'profile_defaults' => $defaults,
        'profile_runtime_defaults' => $runtimeDefaults,
        'profiles' => $profiles,
        'profile_overrides' => $profileOverrides,
    ];
}

function chimSettingsPresetCaptureSnapshot(array $settings, $promptContextOptions): array
{
    $profileSnapshot = chimSettingsPresetCaptureProfiles();
    return [
        'version' => 1,
        'global_settings' => chimSettingsPresetNormalizeSettings($settings),
        'prompt_context_options' => chimNormalizePromptContextOptions($promptContextOptions),
        'profile_defaults' => $profileSnapshot['profile_defaults'],
        'profile_runtime_defaults' => $profileSnapshot['profile_runtime_defaults'],
        'profiles' => $profileSnapshot['profiles'],
        'profile_overrides' => $profileSnapshot['profile_overrides'],
    ];
}

function chimSettingsPresetEnsureStorage(): void
{
    $db = $GLOBALS['db'] ?? null;
    if (!$db) {
        throw new RuntimeException('Database connection is unavailable.');
    }
    $schemaFile = __DIR__ . DIRECTORY_SEPARATOR . 'database_schema' . DIRECTORY_SEPARATOR . 'global_settings_presets.sql';
    $schema = @file_get_contents($schemaFile);
    if ($schema === false || $db->execQuery($schema) === false) {
        throw new RuntimeException('Could not prepare settings preset storage.');
    }
}

function chimSettingsPresetCatalog(): array
{
    $presets = [];
    foreach (chimSettingsPresetBuiltIns() as $preset) {
        unset($preset['snapshot']);
        $presets[] = $preset;
    }
    $customCount = 0;
    $db = $GLOBALS['db'] ?? null;
    if ($db) {
        try {
            $rows = $db->fetchAll('SELECT id, name, updated_at FROM public.global_settings_presets ORDER BY lower(name), id');
            foreach ($rows as $row) {
                $presets[] = [
                    'id' => 'custom:' . (int)$row['id'],
                    'name' => (string)$row['name'],
                    'built_in' => false,
                    'description' => 'Saved from this installation. Connector choices and service URLs stay unchanged.',
                    'affects_profiles' => true,
                    'updated_at' => (string)($row['updated_at'] ?? ''),
                ];
                $customCount++;
            }
        } catch (Throwable $e) {
            // Built-ins remain usable while an older installation awaits its schema update.
        }
    }
    return ['presets' => $presets, 'custom_count' => $customCount];
}

function chimSettingsPresetValidateName(string $name): string
{
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    if ($name === '') {
        throw new InvalidArgumentException('Preset name is required.');
    }
    $nameLength = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
    if ($nameLength > 60) {
        throw new InvalidArgumentException('Preset name must be 60 characters or fewer.');
    }
    $reserved = ['default', 'local llm', 'local-llm', 'local_llm'];
    $normalizedName = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
    if (in_array($normalizedName, $reserved, true)) {
        throw new InvalidArgumentException('That name is reserved for a built-in preset.');
    }
    return $name;
}

function chimSettingsPresetCustomId(string $presetId): int
{
    if (!preg_match('/^custom:([1-9][0-9]*)$/', $presetId, $matches)) {
        throw new InvalidArgumentException('Invalid custom preset.');
    }
    return (int)$matches[1];
}

function chimSettingsPresetSaveNew(string $name, array $snapshot): array
{
    chimSettingsPresetEnsureStorage();
    $db = $GLOBALS['db'];
    $name = chimSettingsPresetValidateName($name);
    $existing = $db->fetchOne('SELECT id FROM public.global_settings_presets WHERE lower(name) = lower(' . $db->escapeLiteral($name) . ') LIMIT 1');
    if (isset($existing['id'])) {
        throw new InvalidArgumentException('A preset with that name already exists. Select it and use Overwrite.');
    }
    $id = $db->insertReturningId('global_settings_presets', [
        'name' => $name,
        'snapshot' => json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
    if ($id <= 0) {
        throw new RuntimeException('Could not save the settings preset.');
    }
    return ['id' => 'custom:' . $id, 'name' => $name, 'built_in' => false];
}

function chimSettingsPresetOverwrite(string $presetId, array $snapshot): array
{
    chimSettingsPresetEnsureStorage();
    $db = $GLOBALS['db'];
    $id = chimSettingsPresetCustomId($presetId);
    $row = $db->fetchOne('SELECT name FROM public.global_settings_presets WHERE id = ' . $id . ' LIMIT 1');
    if (!isset($row['name'])) {
        throw new InvalidArgumentException('Preset not found.');
    }
    $updated = $db->updateRow('global_settings_presets', [
        'snapshot' => json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'updated_at' => date('Y-m-d H:i:s'),
    ], 'id = ' . $id);
    if ($updated === false) {
        throw new RuntimeException('Could not overwrite the settings preset.');
    }
    return ['id' => 'custom:' . $id, 'name' => (string)$row['name'], 'built_in' => false];
}

function chimSettingsPresetLoad(string $presetId): array
{
    $builtIns = chimSettingsPresetBuiltIns();
    if (isset($builtIns[$presetId])) {
        return $builtIns[$presetId];
    }
    chimSettingsPresetEnsureStorage();
    $db = $GLOBALS['db'];
    $id = chimSettingsPresetCustomId($presetId);
    $row = $db->fetchOne('SELECT id, name, snapshot FROM public.global_settings_presets WHERE id = ' . $id . ' LIMIT 1');
    if (!isset($row['id'])) {
        throw new InvalidArgumentException('Preset not found.');
    }
    $snapshot = json_decode((string)$row['snapshot'], true);
    if (!is_array($snapshot)) {
        throw new RuntimeException('The saved preset is invalid.');
    }
    return [
        'id' => 'custom:' . (int)$row['id'],
        'name' => (string)$row['name'],
        'built_in' => false,
        'affects_profiles' => true,
        'snapshot' => $snapshot,
    ];
}

function chimSettingsPresetApplyProfiles(array $snapshot): int
{
    $db = $GLOBALS['db'];
    $defaults = chimSettingsPresetNormalizeProfileValues((array)($snapshot['profile_defaults'] ?? []));
    $profiles = (array)($snapshot['profiles'] ?? []);
    $builtInValues = isset($snapshot['built_in_profile_values'])
        ? chimSettingsPresetNormalizeProfileValues((array)$snapshot['built_in_profile_values'])
        : null;
    $builtInOverrides = chimSettingsPresetNormalizeProfileOverrides((array)($snapshot['built_in_profile_overrides'] ?? []));
    $runtimeDefaults = chimSettingsPresetNormalizeProfileOverrides((array)($snapshot['profile_runtime_defaults'] ?? []));
    $profileOverrides = (array)($snapshot['profile_overrides'] ?? []);
    $count = 0;
    foreach ($db->fetchAll('SELECT id, metadata FROM public.core_profiles ORDER BY id ASC') as $row) {
        $id = (string)(int)$row['id'];
        $values = $builtInValues ?? chimSettingsPresetNormalizeProfileValues((array)($profiles[$id] ?? $defaults));
        $metadata = json_decode((string)($row['metadata'] ?? '{}'), true);
        if (!is_array($metadata)) {
            $metadata = [];
        }
        foreach (['CONTEXT_HISTORY', 'CONTEXT_HISTORY_DIARY', 'CONTEXT_HISTORY_DYNAMIC_PROFILE', 'MAX_WORDS_LIMIT'] as $name) {
            $metadata[$name] = (string)$values[$name];
        }
        $overrides = $builtInOverrides ?: chimSettingsPresetNormalizeProfileOverrides((array)($profileOverrides[$id] ?? []));
        foreach ($overrides as $name => $value) {
            $metadata[$name] = $value;
        }
        if ($db->updateRow('core_profiles', [
            'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ], 'id = ' . (int)$row['id']) === false) {
            throw new RuntimeException('Could not update NPC profile limits.');
        }
        $count++;
    }
    foreach ($defaults as $name => $value) {
        $query = 'INSERT INTO public.conf_opts (id, value) VALUES ('
            . $db->escapeLiteral($name) . ', ' . $db->escapeLiteral((string)$value) . ')
            ON CONFLICT (id) DO UPDATE SET value = EXCLUDED.value';
        if ($db->execQuery($query) === false) {
            throw new RuntimeException("Could not update {$name}.");
        }
    }
    foreach ($runtimeDefaults as $name => $value) {
        $storedValue = is_bool($value) ? ($value ? 'true' : 'false') : (is_array($value) ? json_encode($value) : (string)$value);
        $query = 'INSERT INTO public.conf_opts (id, value) VALUES ('
            . $db->escapeLiteral($name) . ', ' . $db->escapeLiteral($storedValue) . ')
            ON CONFLICT (id) DO UPDATE SET value = EXCLUDED.value';
        if ($db->execQuery($query) === false) {
            throw new RuntimeException("Could not update {$name}.");
        }
    }
    return $count;
}

function chimSettingsPresetApply(string $presetId, bool $manageTransaction = true): array
{
    $preset = chimSettingsPresetLoad($presetId);
    $snapshot = (array)($preset['snapshot'] ?? []);
    $db = $GLOBALS['db'] ?? null;
    if (!$db) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    if ($manageTransaction && $db->execQuery('BEGIN') === false) {
        throw new RuntimeException('Could not start the preset update.');
    }
    try {
        $settings = chimSettingsPresetNormalizeSettings((array)($snapshot['global_settings'] ?? []));
        $settingsUpdated = 0;
        foreach ($settings as $name => $value) {
            if (!chimSetGeneralSetting($name, $value)) {
                throw new RuntimeException("Could not save {$name}.");
            }
            $settingsUpdated++;
        }
        $contextOptions = chimNormalizePromptContextOptions($snapshot['prompt_context_options'] ?? null);
        if (!chimSetGeneralSetting('PROMPT_CONTEXT_OPTIONS', json_encode($contextOptions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))) {
            throw new RuntimeException('Could not save prompt context selections.');
        }
        $settingsUpdated++;
        $profilesUpdated = chimSettingsPresetApplyProfiles($snapshot);
        if ($manageTransaction && $db->execQuery('COMMIT') === false) {
            throw new RuntimeException('Could not commit the preset update.');
        }
    } catch (Throwable $e) {
        if ($manageTransaction) {
            $db->execQuery('ROLLBACK');
        }
        throw $e;
    }

    if ($manageTransaction) {
        chimLoadGeneralSettingsIntoGlobals();
    }
    return [
        'preset_id' => (string)$preset['id'],
        'name' => (string)$preset['name'],
        'settings_updated' => $settingsUpdated,
        'profiles_updated' => $profilesUpdated,
    ];
}
