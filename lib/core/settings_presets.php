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
        'COMPACT_CHAT_ENABLED' => true,
    ] + chimSettingsPresetConnectorAvailability(true);
}

/** Return the feature switches for tasks that use Global Connectors. */
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
        'RECHAT_ALLOW_ACTIONS' => true,
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
        'RECHAT_P' => 50,
        'RECHAT_ALLOW_ACTIONS' => false,
        'BORED_EVENT' => 30,
        'RPG_COMMENTS_CHANCE' => 0,
        'COMBAT_BARK_COOLDOWN' => 100,
        'QUEST_COMMENT' => false,
    ];
}

/**
 * Built-in presets for one NPC profile. Values are server-owned so Quickstart,
 * the PHP Profiles page and Prisma cannot drift apart.
 */
function chimProfileSettingsPresetBuiltIns(): array
{
    $defaultValues = chimSettingsPresetProfileDefaults();
    $defaultValues['CONTEXT_HISTORY'] = 75;

    $defaultOverrides = chimSettingsPresetDefaultProfileOverrides();
    $localOverrides = [
        'DYNAMIC_PROFILE_ENABLED' => false,
        'MIDDLE_TERM_MEMORY_ENABLED' => false,
        'AUTO_DIARY_ENABLED' => false,
        'AUTO_DIARY_WAIT_ENABLED' => false,
        'MATERIALIZE_DIARY_ENABLED' => false,
        'LATEST_DIARY_CONTEXT_ENABLED' => false,
        'LLM_RANDOMIZER_ENABLED' => false,
    ] + chimSettingsPresetLocalProfileRuntimeValues();

    return [
        'builtin:default' => [
            'id' => 'builtin:default',
            'name' => 'Default',
            'description' => 'The Recommended CHIM experience.',
            'profile_values' => $defaultValues,
            'profile_overrides' => $defaultOverrides,
            'profile_runtime_defaults' => chimSettingsPresetDefaultProfileRuntimeValues(),
        ],
        'builtin:local_llm' => [
            'id' => 'builtin:local_llm',
            'name' => 'Local LLM',
            'description' => 'Shorter context and replies with optional background AI features turned off.',
            'profile_values' => [
                'CONTEXT_HISTORY' => 20,
                'CONTEXT_HISTORY_DIARY' => 20,
                'CONTEXT_HISTORY_DYNAMIC_PROFILE' => 20,
                'MAX_WORDS_LIMIT' => 60,
            ],
            'profile_overrides' => $localOverrides,
            'profile_runtime_defaults' => chimSettingsPresetLocalProfileRuntimeValues(),
        ],
        'builtin:follower' => [
            'id' => 'builtin:follower',
            'name' => 'Follower',
            'description' => 'More roleplay, memory and conversation features for a regular companion.',
            'profile_values' => [
                'CONTEXT_HISTORY' => 100,
                'CONTEXT_HISTORY_DIARY' => 150,
                'CONTEXT_HISTORY_DYNAMIC_PROFILE' => 100,
                'MAX_WORDS_LIMIT' => 0,
            ],
            'profile_overrides' => [
                'DYNAMIC_PROFILE_ENABLED' => true,
                'MIDDLE_TERM_MEMORY_ENABLED' => true,
                'AUTO_DIARY_ENABLED' => true,
                'AUTO_DIARY_WAIT_ENABLED' => true,
                'MATERIALIZE_DIARY_ENABLED' => true,
                'LATEST_DIARY_CONTEXT_ENABLED' => true,
                'LLM_RANDOMIZER_ENABLED' => false,
                'RECHAT_H' => 4,
                'RECHAT_P' => 60,
                'RECHAT_ALLOW_ACTIONS' => true,
                'BORED_EVENT' => 50,
                'RPG_COMMENTS_CHANCE' => 75,
                'COMBAT_BARK_COOLDOWN' => 20,
                'QUEST_COMMENT' => true,
            ],
            'profile_runtime_defaults' => [],
        ],
        'builtin:passive' => [
            'id' => 'builtin:passive',
            'name' => 'Passive',
            'description' => 'Full-quality responses with fewer unsolicited conversations and comments.',
            'profile_values' => $defaultValues,
            'profile_overrides' => [
                'DYNAMIC_PROFILE_ENABLED' => false,
                'MIDDLE_TERM_MEMORY_ENABLED' => false,
                'AUTO_DIARY_ENABLED' => false,
                'AUTO_DIARY_WAIT_ENABLED' => false,
                'MATERIALIZE_DIARY_ENABLED' => false,
                'LATEST_DIARY_CONTEXT_ENABLED' => false,
                'LLM_RANDOMIZER_ENABLED' => false,
                'RECHAT_H' => 1,
                'RECHAT_P' => 10,
                'RECHAT_ALLOW_ACTIONS' => false,
                'BORED_EVENT' => 5,
                'RPG_COMMENTS_CHANCE' => 20,
                'COMBAT_BARK_COOLDOWN' => 120,
                'QUEST_COMMENT' => false,
            ],
            'profile_runtime_defaults' => [],
        ],
    ];
}

function chimProfileSettingsPresetCatalog(): array
{
    $presets = array_values(array_map(static function(array $preset): array {
        return [
            'id' => (string)$preset['id'],
            'name' => (string)$preset['name'],
            'description' => (string)($preset['description'] ?? ''),
            'built_in' => true,
        ];
    }, chimProfileSettingsPresetBuiltIns()));

    $db = $GLOBALS['db'] ?? null;
    if ($db) {
        try {
            $rows = $db->fetchAll('SELECT id, name, updated_at FROM public.profile_settings_presets ORDER BY lower(name), id');
            foreach ((array)$rows as $row) {
                $presets[] = [
                    'id' => 'custom:' . (int)$row['id'],
                    'name' => (string)$row['name'],
                    'description' => 'Saved profile settings.',
                    'built_in' => false,
                    'updated_at' => (string)($row['updated_at'] ?? ''),
                ];
            }
        } catch (Throwable $e) {
            // Built-ins remain available while an older installation awaits its schema update.
        }
    }

    return $presets;
}

function chimSettingsPresetBuiltIns(): array
{
    $profilePresets = chimProfileSettingsPresetBuiltIns();
    $defaultProfilePreset = $profilePresets['builtin:default'];
    $localProfilePreset = $profilePresets['builtin:local_llm'];

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
                'built_in_profile_values' => $defaultProfilePreset['profile_values'],
                'built_in_profile_overrides' => $defaultProfilePreset['profile_overrides'],
                'profile_runtime_defaults' => $defaultProfilePreset['profile_runtime_defaults'],
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
                    'COMPACT_CHAT_ENABLED' => true,
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
                ],
                'profiles' => [],
                'built_in_profile_values' => $localProfilePreset['profile_values'],
                'built_in_profile_overrides' => $localProfilePreset['profile_overrides'],
                'profile_runtime_defaults' => $localProfilePreset['profile_runtime_defaults'],
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

function chimProfileSettingsPresetManagedValueKeys(): array
{
    return ['CONTEXT_HISTORY', 'CONTEXT_HISTORY_DIARY', 'CONTEXT_HISTORY_DYNAMIC_PROFILE', 'MAX_WORDS_LIMIT'];
}

function chimProfileSettingsPresetManagedOverrideKeys(): array
{
    $keys = [];
    foreach (chimProfileSettingsPresetBuiltIns() as $preset) {
        foreach (array_keys((array)($preset['profile_overrides'] ?? [])) as $name) {
            $keys[(string)$name] = true;
        }
    }
    return array_keys($keys);
}

function chimProfileSettingsPresetEnsureStorage(): void
{
    $db = $GLOBALS['db'] ?? null;
    if (!$db) {
        throw new RuntimeException('Database connection is unavailable.');
    }
    $schemaFile = __DIR__ . DIRECTORY_SEPARATOR . 'database_schema' . DIRECTORY_SEPARATOR . 'profile_settings_presets.sql';
    $schema = @file_get_contents($schemaFile);
    if ($schema === false || $db->execQuery($schema) === false) {
        throw new RuntimeException('Could not prepare profile preset storage.');
    }
}

function chimProfileSettingsPresetValidateName(string $name): string
{
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    if ($name === '') {
        throw new InvalidArgumentException('Preset name is required.');
    }
    $length = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
    if ($length > 60) {
        throw new InvalidArgumentException('Preset name must be 60 characters or fewer.');
    }
    $normalized = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
    foreach (chimProfileSettingsPresetBuiltIns() as $preset) {
        $builtInName = function_exists('mb_strtolower')
            ? mb_strtolower((string)$preset['name'])
            : strtolower((string)$preset['name']);
        if ($normalized === $builtInName) {
            throw new InvalidArgumentException('That name is reserved for a built-in preset.');
        }
    }
    return $name;
}

function chimProfileSettingsPresetCustomId(string $presetId): int
{
    if (!preg_match('/^custom:([1-9][0-9]*)$/', $presetId, $matches)) {
        throw new InvalidArgumentException('Invalid custom profile preset.');
    }
    return (int)$matches[1];
}

/** Normalize one portable custom snapshot to the server-owned profile preset allowlist. */
function chimProfileSettingsPresetNormalizeSnapshot(array $snapshot): array
{
    if (($snapshot['version'] ?? null) !== 1) {
        throw new InvalidArgumentException('Unsupported profile preset version.');
    }

    $rawValues = (array)($snapshot['profile_values'] ?? []);
    $unknownValues = array_diff(array_keys($rawValues), chimProfileSettingsPresetManagedValueKeys());
    if ($unknownValues) {
        throw new InvalidArgumentException('Unknown profile preset setting: ' . (string)reset($unknownValues));
    }
    foreach ($rawValues as $name => $value) {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException('Invalid ' . (string)$name . ': Expected an integer.');
        }
    }
    $values = chimSettingsPresetNormalizeProfileValues($rawValues);
    $values = array_intersect_key($values, array_flip(chimProfileSettingsPresetManagedValueKeys()));

    $rawOverrides = (array)($snapshot['profile_overrides'] ?? []);
    $unknownOverrides = array_diff(array_keys($rawOverrides), chimProfileSettingsPresetManagedOverrideKeys());
    if ($unknownOverrides) {
        throw new InvalidArgumentException('Unknown profile preset setting: ' . (string)reset($unknownOverrides));
    }

    return [
        'version' => 1,
        'profile_values' => $values,
        'profile_overrides' => chimSettingsPresetNormalizeProfileOverrides($rawOverrides),
    ];
}

function chimProfileSettingsPresetCapture(int $profileId, ?array $submittedMetadata = null): array
{
    $db = $GLOBALS['db'] ?? null;
    if (!$db || $profileId <= 0) {
        throw new InvalidArgumentException('Invalid profile.');
    }
    $profile = $db->fetchOne('SELECT id, metadata FROM public.core_profiles WHERE id = ' . $profileId . ' LIMIT 1');
    if (!isset($profile['id'])) {
        throw new InvalidArgumentException('Profile not found.');
    }

    if ($submittedMetadata === null) {
        $metadata = json_decode((string)($profile['metadata'] ?? '{}'), true);
        if (!is_array($metadata)) $metadata = [];
    } else {
        $metadata = $submittedMetadata;
    }

    $defaults = chimSettingsPresetProfileDefaults();
    $values = [];
    foreach (chimProfileSettingsPresetManagedValueKeys() as $name) {
        $values[$name] = array_key_exists($name, $metadata) ? $metadata[$name] : $defaults[$name];
    }
    $overrides = [];
    foreach (chimProfileSettingsPresetManagedOverrideKeys() as $name) {
        if (array_key_exists($name, $metadata)) $overrides[$name] = $metadata[$name];
    }

    return chimProfileSettingsPresetNormalizeSnapshot([
        'version' => 1,
        'profile_values' => $values,
        'profile_overrides' => $overrides,
    ]);
}

function chimProfileSettingsPresetSaveNew(string $name, array $snapshot): array
{
    chimProfileSettingsPresetEnsureStorage();
    $db = $GLOBALS['db'];
    $name = chimProfileSettingsPresetValidateName($name);
    $snapshot = chimProfileSettingsPresetNormalizeSnapshot($snapshot);
    $existing = $db->fetchOne('SELECT id FROM public.profile_settings_presets WHERE lower(name) = lower(' . $db->escapeLiteral($name) . ') LIMIT 1');
    if (isset($existing['id'])) {
        throw new InvalidArgumentException('A preset with that name already exists. Select it and use Overwrite.');
    }
    $id = $db->insertReturningId('profile_settings_presets', [
        'name' => $name,
        'snapshot' => json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
    if ($id <= 0) {
        throw new RuntimeException('Could not save the profile preset.');
    }
    return ['id' => 'custom:' . $id, 'name' => $name, 'description' => 'Saved profile settings.', 'built_in' => false];
}

function chimProfileSettingsPresetOverwrite(string $presetId, array $snapshot): array
{
    chimProfileSettingsPresetEnsureStorage();
    $db = $GLOBALS['db'];
    $id = chimProfileSettingsPresetCustomId($presetId);
    $row = $db->fetchOne('SELECT name FROM public.profile_settings_presets WHERE id = ' . $id . ' LIMIT 1');
    if (!isset($row['name'])) {
        throw new InvalidArgumentException('Profile preset not found.');
    }
    $snapshot = chimProfileSettingsPresetNormalizeSnapshot($snapshot);
    $updated = $db->updateRow('profile_settings_presets', [
        'snapshot' => json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'updated_at' => date('Y-m-d H:i:s'),
    ], 'id = ' . $id);
    if ($updated === false) {
        throw new RuntimeException('Could not overwrite the profile preset.');
    }
    return ['id' => 'custom:' . $id, 'name' => (string)$row['name'], 'description' => 'Saved profile settings.', 'built_in' => false];
}

function chimProfileSettingsPresetLoad(string $presetId): array
{
    $builtIns = chimProfileSettingsPresetBuiltIns();
    if (isset($builtIns[$presetId])) {
        return $builtIns[$presetId] + ['built_in' => true];
    }

    chimProfileSettingsPresetEnsureStorage();
    $db = $GLOBALS['db'];
    $id = chimProfileSettingsPresetCustomId($presetId);
    $row = $db->fetchOne('SELECT id, name, snapshot FROM public.profile_settings_presets WHERE id = ' . $id . ' LIMIT 1');
    if (!isset($row['id'])) {
        throw new InvalidArgumentException('Profile preset not found.');
    }
    $snapshot = json_decode((string)$row['snapshot'], true);
    if (!is_array($snapshot)) {
        throw new RuntimeException('The saved profile preset is invalid.');
    }
    $snapshot = chimProfileSettingsPresetNormalizeSnapshot($snapshot);
    return [
        'id' => 'custom:' . (int)$row['id'],
        'name' => (string)$row['name'],
        'description' => 'Saved profile settings.',
        'built_in' => false,
        'profile_values' => $snapshot['profile_values'],
        'profile_overrides' => $snapshot['profile_overrides'],
    ];
}

function chimProfileSettingsPresetExport(string $presetId): array
{
    chimProfileSettingsPresetCustomId($presetId);
    $preset = chimProfileSettingsPresetLoad($presetId);
    $snapshot = chimProfileSettingsPresetNormalizeSnapshot([
        'version' => 1,
        'profile_values' => $preset['profile_values'],
        'profile_overrides' => $preset['profile_overrides'],
    ]);
    $slug = trim(preg_replace('/[^a-z0-9_-]+/i', '_', strtolower((string)$preset['name'])) ?? '', '_');
    if ($slug === '') $slug = 'profile_preset';
    return [
        'filename' => $slug . '_profile_preset.json',
        'document' => [
            'format' => 'chim-profile-settings-preset',
            'version' => 1,
            'name' => (string)$preset['name'],
            'snapshot' => $snapshot,
        ],
    ];
}

function chimProfileSettingsPresetUniqueImportedName(string $name): string
{
    $db = $GLOBALS['db'];
    $name = chimProfileSettingsPresetValidateName($name);
    $base = preg_replace('/(?:\s*\(Imported(?: \d+)?\))+$/i', '', $name) ?: $name;
    for ($index = 1; $index <= 999; $index++) {
        $suffix = $index === 1 ? ' (Imported)' : ' (Imported ' . $index . ')';
        $maxBaseLength = 60 - strlen($suffix);
        $candidateBase = function_exists('mb_substr') ? mb_substr($base, 0, $maxBaseLength) : substr($base, 0, $maxBaseLength);
        $candidate = rtrim($candidateBase) . $suffix;
        $row = $db->fetchOne('SELECT id FROM public.profile_settings_presets WHERE lower(name) = lower(' . $db->escapeLiteral($candidate) . ') LIMIT 1');
        if (!isset($row['id'])) return $candidate;
    }
    throw new RuntimeException('Could not choose a unique imported preset name.');
}

function chimProfileSettingsPresetImport(array $document): array
{
    if (($document['format'] ?? null) !== 'chim-profile-settings-preset' || ($document['version'] ?? null) !== 1) {
        throw new InvalidArgumentException('Unsupported profile preset file.');
    }
    if (!isset($document['snapshot']) || !is_array($document['snapshot'])) {
        throw new InvalidArgumentException('Profile preset settings are required.');
    }
    chimProfileSettingsPresetEnsureStorage();
    $name = chimProfileSettingsPresetUniqueImportedName((string)($document['name'] ?? ''));
    return chimProfileSettingsPresetSaveNew($name, $document['snapshot']);
}

/** Apply one profile preset without changing profile identity or connector columns. */
function chimProfileSettingsPresetApply(int $profileId, string $presetId): array
{
    $db = $GLOBALS['db'] ?? null;
    if (!$db) {
        throw new RuntimeException('Database connection is unavailable.');
    }
    if ($profileId <= 0) {
        throw new InvalidArgumentException('Invalid profile.');
    }

    $preset = chimProfileSettingsPresetLoad($presetId);
    $profile = $db->fetchOne('SELECT id, label, metadata FROM public.core_profiles WHERE id = ' . $profileId . ' LIMIT 1');
    if (!isset($profile['id'])) {
        throw new InvalidArgumentException('Profile not found.');
    }

    $metadata = json_decode((string)($profile['metadata'] ?? '{}'), true);
    if (!is_array($metadata)) {
        $metadata = [];
    }
    foreach (array_merge(chimProfileSettingsPresetManagedValueKeys(), chimProfileSettingsPresetManagedOverrideKeys()) as $name) {
        unset($metadata[$name]);
    }
    $values = chimSettingsPresetNormalizeProfileValues((array)$preset['profile_values']);
    foreach (chimProfileSettingsPresetManagedValueKeys() as $name) {
        $metadata[$name] = $values[$name];
    }
    $overrides = chimSettingsPresetNormalizeProfileOverrides((array)$preset['profile_overrides']);
    foreach ($overrides as $name => $value) {
        $metadata[$name] = $value;
    }

    if ($db->execQuery('BEGIN') === false) {
        throw new RuntimeException('Could not start the profile preset update.');
    }
    try {
        if ($db->updateRow('core_profiles', [
            'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ], 'id = ' . $profileId) === false) {
            throw new RuntimeException('Could not update the profile.');
        }
        if ($db->execQuery('COMMIT') === false) {
            throw new RuntimeException('Could not finish the profile preset update.');
        }
    } catch (Throwable $e) {
        $db->execQuery('ROLLBACK');
        throw $e;
    }

    return [
        'preset_id' => (string)$preset['id'],
        'preset_name' => (string)$preset['name'],
        'profile_id' => (int)$profile['id'],
        'profile_name' => (string)($profile['label'] ?? ('Profile ' . $profileId)),
        'settings_updated' => count(chimProfileSettingsPresetManagedValueKeys()) + count(chimProfileSettingsPresetManagedOverrideKeys()),
    ];
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
