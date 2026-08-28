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

if (!function_exists('chimGlobalLlmConnectorAvailabilityMap')) {
    /** Keep every Global Connectors assignment paired with its authoritative availability switch. */
    function chimGlobalLlmConnectorAvailabilityMap(): array
    {
        return [
            'CORE_CONNECTOR_PLAYER' => 'PLAYER_RESPEECH',
            'CORE_CONNECTOR_SUMMARY' => 'CORE_CONNECTOR_SUMMARY_ENABLED',
            'CORE_CONNECTOR_MEDIUMTERM' => 'CORE_CONNECTOR_MEDIUMTERM_ENABLED',
            'CORE_CONNECTOR_SCENECLASSIFIER' => 'SCENE_CLASSIFIER_ENABLED',
            'CORE_CONNECTOR_PROFILES' => 'CORE_CONNECTOR_PROFILES_ENABLED',
            'CORE_CONNECTOR_DIRECTOR' => 'CORE_CONNECTOR_DIRECTOR_ENABLED',
            'CORE_CONNECTOR_BGL' => 'CORE_CONNECTOR_BGL_ENABLED',
            'RELLLM_CONNECTOR' => 'RELATIONSHIP_SYSTEM_ENABLED',
        ];
    }
}

if (!function_exists('chimIsGlobalLlmConnectorEnabled')) {
    /** Read a connector's switch without clearing or changing its saved assignment. */
    function chimIsGlobalLlmConnectorEnabled(string $connectorField): bool
    {
        $toggleField = chimGlobalLlmConnectorAvailabilityMap()[$connectorField] ?? '';
        if ($toggleField === '') {
            return true;
        }

        $value = function_exists('chimReadLegacyGlobalValue')
            ? chimReadLegacyGlobalValue($toggleField, true)
            : ($GLOBALS[$toggleField] ?? true);

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool)$value;
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
    function chimFlattenConfSchema(?array $node = null, string $prefix = ''): array
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

if (!function_exists('chimNormalizeBackgroundLifeTriggerHours')) {
    function chimNormalizeBackgroundLifeTriggerHours($hours, float $default = 24.0): float
    {
        $value = is_numeric($hours) ? floatval($hours) : $default;
        return max(1.0, min(720.0, $value));
    }
}

if (!function_exists('chimConvertBackgroundLifeDaysToHours')) {
    function chimConvertBackgroundLifeDaysToHours($days, float $default = 24.0): float
    {
        if (!is_numeric($days)) {
            return chimNormalizeBackgroundLifeTriggerHours($default, $default);
        }

        return chimNormalizeBackgroundLifeTriggerHours(floatval($days) * 24.0, $default);
    }
}

if (!function_exists('chimGetBackgroundLifeTriggerHours')) {
    function chimGetBackgroundLifeTriggerHours(float $default = 24.0): float
    {
        if (isset($GLOBALS['BGL_TRIGGER_HOURS']) && is_numeric($GLOBALS['BGL_TRIGGER_HOURS'])) {
            return chimNormalizeBackgroundLifeTriggerHours($GLOBALS['BGL_TRIGGER_HOURS'], $default);
        }

        // Compatibility for pre-hours configuration files and databases.
        if (isset($GLOBALS['BGL_TRIGGER_DAYS']) && is_numeric($GLOBALS['BGL_TRIGGER_DAYS'])) {
            return chimConvertBackgroundLifeDaysToHours($GLOBALS['BGL_TRIGGER_DAYS'], $default);
        }

        return chimNormalizeBackgroundLifeTriggerHours($default, $default);
    }
}

if (!function_exists('chimGetManagedGeneralSettingIds')) {
    function chimGetManagedGeneralSettingIds(): array
    {
        return [
            'AUTO_LOCK_PROFILE',
            'AUTOFILL_CUSTOM_PROFILES',
            'AUTOFILL_CUSTOM_PROFILES_TRIGGER',
            'BGL_TRIGGER_HOURS',
            'VISUAL_CONTEXT_SCENE_TTL_MINUTES',
            'VISUAL_CONTEXT_PROMPT_MAX_CHARS',
            'END_CONVERSATION_COOLDOWN',
            'FEATURES@MEMORY_EMBEDDING@ENABLED',
            'FEATURES@MEMORY_EMBEDDING@USE_TEXT2VEC',
            'FEATURES@MEMORY_EMBEDDING@AUTO_CREATE_SUMMARY_INTERVAL',
            'FEATURES@MEMORY_EMBEDDING@AUTO_CREATE_SUMMARYS',
            'PROMPT_HEAD',
            'EMOTEMOODS',
            'OGHMA_INFINIUM',
            'OGHMA_AMOUNT',
            'OGHMA_RESULT_LIMIT',
            'OGHMA_EXTRACTOR_FALLBACK',
            'OGHMA_EXTRACTOR_TIMEOUT_MS',
            'RACIAL_OGHMA',
            'LOCATION_OGHMA',
            'DETECT_MAGIC_EVENT',
            'MAGIC_EVENT_BLACKLIST',
            'LOCATION_BLACKLIST',
            'ITEM_BLACKLIST',
            'EVENT_TYPE_FILTER',
            'CHIM_ITEM_PICKUP_EVENTLOG_MIN_VALUE',
            'GROUND_ITEMS_DESCRIPTIONS_ONLY',
            'INVENTORY_ITEMS_DESCRIPTIONS_ONLY',
            'HIDE_AMBIENT_COMBAT',
            'DISABLE_REANIMATION_TRACKING',
            'TRANSFORMATION_DETECTION',
            'CHIM_AI_QUEST_PROGRESSION',
            'CHIM_PLAYER_ONLY_QUEST_ADVANCEMENT',
            'PROMPT_TIMESTAMP',
            'COMPACT_CHAT_ENABLED',
            'PROMPT_CONTEXT_OPTIONS',
            'RECHAT_MODE',
            'ENFORCE_STRICT_RECHAT_RESPONSE',
            'OPEN_RECHAT',
            'CORE_CONNECTOR_PLAYER',
            'CORE_CONNECTOR_SUMMARY',
            'CORE_CONNECTOR_MEDIUMTERM',
            'CORE_CONNECTOR_SCENECLASSIFIER',
            'CORE_CONNECTOR_PROFILES',
            'CORE_CONNECTOR_DIRECTOR',
            'CORE_CONNECTOR_BGL',
            'RELLLM_CONNECTOR',
            'PLAYER_RESPEECH',
            'CORE_CONNECTOR_SUMMARY_ENABLED',
            'CORE_CONNECTOR_MEDIUMTERM_ENABLED',
            'CORE_CONNECTOR_PROFILES_ENABLED',
            'CORE_CONNECTOR_DIRECTOR_ENABLED',
            'CORE_CONNECTOR_BGL_ENABLED',
            'RELATIONSHIP_UPDATE_CHANCE',
            'CORE_CONNECTOR_OGHMA_CUSTOM',
            'RELATIONSHIP_SYSTEM_ENABLED',
            'NEVER_CLEAR_RELATIONSHIP_DATA',
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
        $descriptions['PROMPT_CONTEXT_OPTIONS'] = 'Controls which XML-like prompt context sections are included in the final system prompt sent to the LLM. Managed from Global Settings.';
        $descriptions['GLOBAL_STT_CONNECTOR_ID'] = 'Active global STT connector. Only one STT connector is used globally for player speech-to-text.';
        $descriptions['GLOBAL_ITT_CONNECTOR_ID'] = 'Active global ITT connector. Only one ITT connector is used globally for image-to-text and Soulgaze.';

        return $descriptions;
    }
}

if (!function_exists('chimPrettySettingLabel')) {
    function chimPrettySettingLabel(string $flatName): string
    {
        if (strpos($flatName, 'FEATURES@MEMORY_EMBEDDING@') === 0) {
            $parts = explode('@', $flatName);
            $last = end($parts) ?: $flatName;
            return ucwords(str_replace('_', ' ', strtolower(trim($last))));
        }

        if (strpos($flatName, 'TRANSLATION@settings@') === 0) {
            $parts = explode('@', $flatName);
            $last = end($parts) ?: $flatName;
            return ucwords(str_replace('_', ' ', strtolower(trim($last))));
        }

        if (strpos($flatName, 'TRANSLATION@DeepL@') === 0) {
            $parts = explode('@', $flatName);
            $last = end($parts) ?: $flatName;
            $lastLower = strtolower(trim($last));
            if ($lastLower === 'url') {
                return 'Endpoint URL';
            }
            if ($lastLower === 'api_key') {
                return 'API Key';
            }
            return ucwords(str_replace('_', ' ', $lastLower));
        }

        if ($flatName === 'TRANSLATION_FUNCTION') {
            return 'Provider';
        }

        $customLabels = [
            'CORE_CONNECTOR_PLAYER' => 'Player Respeech',
            'CORE_CONNECTOR_SUMMARY' => 'Summaries',
            'CORE_CONNECTOR_MEDIUMTERM' => 'Background & Memory Tasks',
            'CORE_CONNECTOR_SCENECLASSIFIER' => 'Scene Classifier',
            'SCENE_CLASSIFIER_ENABLED' => 'Scene Classifier',
            'CORE_CONNECTOR_PROFILES' => 'Profile Tasks',
            'CORE_CONNECTOR_DIRECTOR' => 'Director Mode',
            'CORE_CONNECTOR_OGHMA_CUSTOM' => 'Oghma Extractor Fallback',
            'PLAYER_RESPEECH' => 'Player Respeech Available',
            'CORE_CONNECTOR_SUMMARY_ENABLED' => 'Summaries Available',
            'CORE_CONNECTOR_MEDIUMTERM_ENABLED' => 'Background & Memory Tasks Available',
            'CORE_CONNECTOR_PROFILES_ENABLED' => 'Profile Tasks Available',
            'CORE_CONNECTOR_DIRECTOR_ENABLED' => 'Director Mode Available',
            'CORE_CONNECTOR_BGL_ENABLED' => 'Background Life Available',
            'RELLLM_CONNECTOR' => 'Relationship Management',
            'RELATIONSHIP_UPDATE_CHANCE' => 'Relationship Update Chance',
            'NEVER_CLEAR_RELATIONSHIP_DATA' => 'Never Clear Relationship Data',
            'EMOTEMOODS' => 'Emote Moods',
            'OGHMA_INFINIUM' => 'Enable Oghma',
            'OGHMA_AMOUNT' => 'Oghma Topic Count',
            'OGHMA_RESULT_LIMIT' => 'Oghma Result Limit',
            'OGHMA_EXTRACTOR_FALLBACK' => 'Oghma Extractor Fallback',
            'OGHMA_EXTRACTOR_TIMEOUT_MS' => 'Oghma Extractor Timeout',
            'RACIAL_OGHMA' => 'Force Racial Oghma',
            'LOCATION_OGHMA' => 'Force Location Oghma',
            'ENFORCE_STRICT_RECHAT_RESPONSE' => 'Strict Rechat Targeting',
            'SHORTER_NEARBY_ITEM_LIST' => 'Shorter Nearby Item List',
            'BGL_TRIGGER_HOURS' => 'Background Life Trigger Time',
            'GLOBAL_STT_CONNECTOR_ID' => 'Speech To Text Connector',
            'GLOBAL_ITT_CONNECTOR_ID' => 'Image To Text Connector',
            'CHIM_AI_QUEST_PROGRESSION' => 'AI Quest Progression (Beta)',
            'CHIM_PLAYER_ONLY_QUEST_ADVANCEMENT' => 'Player Only Quest Advancement',
            'CHIM_ITEM_PICKUP_EVENTLOG_MIN_VALUE' => 'Item Pickup Detection Value',
            'VISUAL_CONTEXT_SCENE_TTL_MINUTES' => 'Visual Scene Lifetime',
            'VISUAL_CONTEXT_PROMPT_MAX_CHARS' => 'Visual Prompt Limit',
        ];
        if (isset($customLabels[$flatName])) {
            return $customLabels[$flatName];
        }

        $parts = explode('@', $flatName);
        $prettyParts = [];
        foreach ($parts as $part) {
            $prettyParts[] = ucwords(str_replace('_', ' ', strtolower(trim($part))));
        }
        return implode(' -> ', $prettyParts);
    }
}

if (!function_exists('chimGetOverrideableGeneralSettingCategory')) {
    function chimGetOverrideableGeneralSettingCategory(string $flatId): string
    {
        if (in_array($flatId, [
            'OGHMA_INFINIUM', 'OGHMA_AMOUNT', 'OGHMA_RESULT_LIMIT',
            'OGHMA_EXTRACTOR_FALLBACK', 'OGHMA_EXTRACTOR_TIMEOUT_MS',
            'RACIAL_OGHMA', 'LOCATION_OGHMA', 'OGHMA_CUSTOM', 'CORE_CONNECTOR_OGHMA_CUSTOM',
        ], true)) {
            return 'Oghma';
        }

        if (
            strpos($flatId, 'PROMPT_') === 0
            || in_array($flatId, ['EMOTEMOODS', 'DETECT_MAGIC_EVENT', 'MAGIC_EVENT_BLACKLIST', 'LOCATION_BLACKLIST', 'ITEM_BLACKLIST', 'EVENT_TYPE_FILTER', 'RELATIONSHIP_UPDATE_CHANCE'], true)
        ) {
            return 'Prompt';
        }

        if (strpos($flatId, 'RECHAT') === 0) {
            return 'Rechat';
        }

        if (strpos($flatId, 'VISUAL_CONTEXT_') === 0) {
            return 'Visual Context';
        }

        if (in_array($flatId, ['CHIM_AI_QUEST_PROGRESSION', 'CHIM_PLAYER_ONLY_QUEST_ADVANCEMENT'], true)) {
            return 'Quests';
        }

        if (strpos($flatId, 'FEATURES@MEMORY_EMBEDDING@') === 0) {
            return 'Memory';
        }

        if (
            strpos($flatId, 'CORE_CONNECTOR_') === 0
            || in_array($flatId, ['RELLLM_CONNECTOR', 'PLAYER_RESPEECH', 'GLOBAL_STT_CONNECTOR_ID', 'GLOBAL_ITT_CONNECTOR_ID'], true)
        ) {
            return 'Global Connectors';
        }

        if (
            in_array($flatId, [
                'GROUND_ITEMS_DESCRIPTIONS_ONLY',
                'INVENTORY_ITEMS_DESCRIPTIONS_ONLY',
                'HIDE_AMBIENT_COMBAT',
                'DISABLE_REANIMATION_TRACKING',
                'TRANSFORMATION_DETECTION',
                'CHIM_ITEM_PICKUP_EVENTLOG_MIN_VALUE',
                'CHIM_AI_QUEST_PROGRESSION',
                'POWER_AWARENESS_ENABLED',
                'SCENE_CLASSIFIER_ENABLED',
                'RELATIONSHIP_SYSTEM_ENABLED',
                'NEVER_CLEAR_RELATIONSHIP_DATA',
            ], true)
        ) {
            return 'Context';
        }

        if (strpos($flatId, 'TRANSLATION') === 0) {
            return 'Translation';
        }

        return 'Misc';
    }
}

if (!function_exists('chimGetSelectOptionsForOverrideSetting')) {
    function chimGetSelectOptionsForOverrideSetting(string $flatId): array
    {
        $db = chimSettingsDb();
        if (!$db) {
            return [];
        }

        $definitions = [
            'GLOBAL_STT_CONNECTOR_ID' => [
                'query' => "SELECT id, COALESCE(NULLIF(label, ''), NULLIF(driver, ''), CAST(id AS text)) AS option_label FROM public.core_stt_connector ORDER BY id ASC",
            ],
            'GLOBAL_ITT_CONNECTOR_ID' => [
                'query' => "SELECT id, COALESCE(NULLIF(label, ''), NULLIF(driver, ''), CAST(id AS text)) AS option_label FROM public.core_itt_connector ORDER BY id ASC",
            ],
        ];

        $definition = chimGetSchemaDefinition($flatId);
        if (strpos(strval($definition['type'] ?? ''), 'foreign:core_llm_connector') === 0) {
            $definitions[$flatId] = [
                'query' => "SELECT id, COALESCE(NULLIF(label, ''), NULLIF(model, ''), CAST(id AS text)) AS option_label FROM public.core_llm_connector ORDER BY LOWER(COALESCE(NULLIF(label, ''), model)) ASC",
            ];
        }

        if (!isset($definitions[$flatId])) {
            return [];
        }

        try {
            $rows = $db->fetchAll($definitions[$flatId]['query']);
        } catch (\Throwable $e) {
            return [];
        }

        $values = [];
        $valueLabels = [];
        foreach ((array)$rows as $row) {
            $id = strval($row['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $values[] = $id;
            $valueLabels[$id] = strval($row['option_label'] ?? $id);
        }

        return [
            'values' => $values,
            'value_labels' => $valueLabels,
        ];
    }
}

if (!function_exists('chimGetOverrideableGeneralSettingsCatalog')) {
    function chimGetOverrideableGeneralSettingsCatalog(): array
    {
        $descriptions = chimGetManagedGeneralSettingDescriptions();
        $rowMap = [];
        foreach (chimGetAllGeneralSettings() as $row) {
            $id = strval($row['id'] ?? '');
            if ($id !== '') {
                $rowMap[$id] = $row;
            }
        }

        $candidateIds = array_values(array_unique(array_merge(
            chimGetManagedGeneralSettingIds(),
            ['GLOBAL_STT_CONNECTOR_ID', 'GLOBAL_ITT_CONNECTOR_ID'],
            array_keys($rowMap)
        )));

        $catalog = [];
        foreach ($candidateIds as $id) {
            if ($id === 'OGHMA_CUSTOM') {
                continue;
            }
            $definition = chimGetSchemaDefinition($id);
            if (array_key_exists('profile_overrideable', $definition) && $definition['profile_overrideable'] === false) {
                continue;
            }
            $type = strtolower(trim(strval($definition['type'] ?? '')));
            if ($type === '') {
                $currentValue = strval($rowMap[$id]['value'] ?? '');
                if (in_array(strtolower($currentValue), ['true', 'false'], true)) {
                    $type = 'boolean';
                } elseif ($currentValue !== '' && preg_match('/^-?\d+$/', $currentValue)) {
                    $type = 'integer';
                } else {
                    $type = 'string';
                }
            }

            if ($type === 'selectmultiple') {
                continue;
            }

            if (in_array($type, ['int'], true)) {
                $type = 'integer';
            } elseif (in_array($type, ['float', 'double'], true)) {
                $type = 'number';
            } elseif ($type === 'url') {
                $type = 'string';
            }

            $entry = [
                'type' => $type,
                'description' => trim(strval($rowMap[$id]['description'] ?? ($descriptions[$id] ?? chimGetSchemaDescription($id)))),
                'category' => chimGetOverrideableGeneralSettingCategory($id),
                'ui_label' => chimPrettySettingLabel($id),
                'global_value' => $rowMap[$id]['value'] ?? ($definition['default'] ?? ''),
            ];

            if (!empty($definition['values']) && is_array($definition['values'])) {
                $entry['values'] = array_map('strval', $definition['values']);
            }

            $selectOptions = chimGetSelectOptionsForOverrideSetting($id);
            if (!empty($selectOptions['values'])) {
                $entry['type'] = 'select';
                $entry['values'] = $selectOptions['values'];
                $entry['value_labels'] = $selectOptions['value_labels'];
            }

            $catalog[$id] = $entry;
        }

        ksort($catalog);
        return $catalog;
    }
}

if (!function_exists('chimGetPromptContextOptionCatalog')) {
    function chimGetPromptContextOptionCatalog(): array
    {
        return [
            'enabled_sections' => [
                'roleplay_instructions' => [
                    'label' => '<roleplay_instructions>',
                    'description' => 'Core roleplay rules, system preamble, and scene-director framing.',
                ],
                'world' => [
                    'label' => '<world>',
                    'description' => 'Current location, hold, weather, date, and time context.',
                ],
                'knowledge' => [
                    'label' => '<knowledge>',
                    'description' => 'Injected Oghma or lore knowledge for the active subject.',
                ],
                'available_actions_list' => [
                    'label' => '<available_actions_list>',
                    'description' => 'Available in-game actions the actor may choose from.',
                ],
                'nearby_actors' => [
                    'label' => '<nearby_actors>',
                    'description' => 'Nearby NPCs, creatures, and party members in the current scene.',
                ],
                'group_descriptions' => [
                    'label' => '<group_descriptions>',
                    'description' => 'Faction and group descriptions for nearby actors.',
                ],
                'nearby_items' => [
                    'label' => '<nearby_items>',
                    'description' => 'Ground items and item descriptions near the actor.',
                ],
                'adventuring_party' => [
                    'label' => '<adventuring_party>',
                    'description' => 'Companion-party framing and who counts as part of the active group.',
                ],
                'points_of_interest' => [
                    'label' => '<points_of_interest>',
                    'description' => 'Nearby doors, passages, and notable destinations.',
                ],
                'scene_notes' => [
                    'label' => '<scene_notes>',
                    'description' => 'Temporary director or rolemaster scene notes.',
                ],
                'paralinguistic_tags' => [
                    'label' => '<paralinguistic_tags>',
                    'description' => 'TTS-specific paralinguistic tag guidance when enabled.',
                ],
            ],
            'enabled_character_subsections' => [
                'basic_summary' => [
                    'label' => '<basic_summary>',
                    'description' => 'Core background summary or short biography.',
                ],
                'groups' => [
                    'label' => '<groups>',
                    'description' => 'Faction membership summary inside the character sheet.',
                ],
                'personality' => [
                    'label' => '<personality>',
                    'description' => 'Behavioral traits, psychology, and temperament.',
                ],
                'relationships' => [
                    'label' => '<relationships>',
                    'description' => 'Named relationships and relevant social ties.',
                ],
                'occupation' => [
                    'label' => '<occupation>',
                    'description' => 'Job, societal role, or current profession.',
                ],
                'skills' => [
                    'label' => '<skills>',
                    'description' => 'Narrative skills, talents, and expertise.',
                ],
                'rpg_skills' => [
                    'label' => '<rpg_skills>',
                    'description' => 'RPG-style skill proficiencies and levels.',
                ],
                'speech_style' => [
                    'label' => '<speech_style>',
                    'description' => 'Speaking style and communication habits.',
                ],
                'goals' => [
                    'label' => '<goals>',
                    'description' => 'Current ambitions, motivations, and long-term aims.',
                ],
                'middle_term_memory' => [
                    'label' => '<middle_term_memory>',
                    'description' => 'Longer-range memory summary or background-life recap.',
                ],
                'group' => [
                    'label' => '<group>',
                    'description' => 'Profile-level group membership prompt fragment.',
                ],
                'storyline_starring' => [
                    'label' => '<storyline_starring>',
                    'description' => 'Quest or storyline currently starring this actor.',
                ],
                'quest_topics' => [
                    'label' => '<quest_topics>',
                    'description' => 'Quest topics this actor specifically knows about.',
                ],
            ],
            'enabled_appearance_subsections' => [
                'appearance' => [
                    'label' => '<appearance>',
                    'description' => 'Physical appearance and identifying features.',
                ],
                'equipment' => [
                    'label' => '<equipment>',
                    'description' => 'Currently equipped gear and worn items.',
                ],
                'target_equipment' => [
                    'label' => '<target_equipment>',
                    'description' => 'Equipment summary for the current dialogue target when available.',
                ],
                'inventory' => [
                    'label' => '<inventory>',
                    'description' => 'Inventory listing.',
                ],
                'current_activity' => [
                    'label' => '<activity>',
                    'description' => 'What the actor is doing.',
                ],
                'current_condition' => [
                    'label' => '<condition>',
                    'description' => 'Health, magicka, stamina, and visible condition state.',
                ],
                'spells' => [
                    'label' => '<spells>',
                    'description' => 'Known spell list when available.',
                ],
                'reanimation_status' => [
                    'label' => '<reanimation_status>',
                    'description' => 'Zombie/reanimated state notice when applicable.',
                ],
            ],
            'enabled_general_subsections' => [
                'active_quests' => [
                    'label' => '<active_quests>',
                    'description' => 'Current active quest list.',
                ],
                'current_plans' => [
                    'label' => '<current_plans>',
                    'description' => 'Current and recent plan/task summary.',
                ],
            ],
            'enabled_nearby_actor_subsections' => [
                'basic_summary' => [
                    'label' => 'Basic summary',
                    'description' => 'Nearby actor profile summary or short biography.',
                ],
                'appearance' => [
                    'label' => 'Appearance',
                    'description' => 'Nearby actor physical appearance and visible traits.',
                ],
                'equipment' => [
                    'label' => 'Equipment',
                    'description' => 'Nearby actor currently equipped gear and worn items.',
                ],
                'equipment_descriptions' => [
                    'label' => 'Equipment descriptions',
                    'description' => 'Adds item descriptions to nearby actor equipment when available.',
                ],
                'current_activity' => [
                    'label' => 'Current activity',
                    'description' => 'What nearby actors are currently doing.',
                ],
                'power_awareness' => [
                    'label' => 'Power awareness',
                    'description' => 'Relative strength assessment for nearby actors when power awareness is enabled.',
                ],
                'factions' => [
                    'label' => 'Factions',
                    'description' => 'Faction names and group descriptions for nearby actors.',
                ],
                'custom_state' => [
                    'label' => 'Custom state',
                    'description' => 'Custom plugin state attached to nearby actor profile lines.',
                ],
            ],
            'enabled_nearby_item_subsections' => [
                'group_duplicates' => [
                    'label' => 'Group duplicates',
                    'description' => 'Groups duplicate nearby ground items into counted entries.',
                    'default_enabled' => false,
                ],
                'item_descriptions' => [
                    'label' => 'Item descriptions',
                    'description' => 'Adds item descriptions for nearby ground items when available.',
                ],
            ],
        ];
    }
}

if (!function_exists('chimGetDefaultPromptContextOptions')) {
    function chimGetDefaultPromptContextOptions(): array
    {
        $catalog = chimGetPromptContextOptionCatalog();
        $defaults = [];
        foreach ($catalog as $bucket => $options) {
            $defaults[$bucket] = [];
            foreach ($options as $id => $meta) {
                if (!isset($meta['default_enabled']) || $meta['default_enabled'] !== false) {
                    $defaults[$bucket][] = $id;
                }
            }
        }

        return $defaults;
    }
}

if (!function_exists('chimNormalizePromptContextOptions')) {
    function chimNormalizePromptContextOptions($rawOptions): array
    {
        $catalog = chimGetPromptContextOptionCatalog();
        $defaults = chimGetDefaultPromptContextOptions();

        if (is_string($rawOptions) && trim($rawOptions) !== '') {
            $decoded = json_decode($rawOptions, true);
            if (is_array($decoded)) {
                $rawOptions = $decoded;
            }
        }

        if (!is_array($rawOptions)) {
            return $defaults;
        }

        $legacyAppearanceSubsectionIds = [
            'appearance',
            'equipment',
            'inventory',
            'current_activity',
            'current_condition',
            'reanimation_status',
        ];
        $normalized = [];
        foreach ($defaults as $bucket => $defaultIds) {
            $hasBucket = array_key_exists($bucket, $rawOptions);
            $rawIds = $hasBucket ? $rawOptions[$bucket] : $defaultIds;
            if (
                !$hasBucket
                && $bucket === 'enabled_appearance_subsections'
                && isset($rawOptions['enabled_character_subsections'])
                && is_array($rawOptions['enabled_character_subsections'])
            ) {
                $legacyCharacterIds = array_values(array_map('strval', $rawOptions['enabled_character_subsections']));
                $rawIds = $defaultIds;
                foreach ($legacyAppearanceSubsectionIds as $legacyId) {
                    if (!in_array($legacyId, $legacyCharacterIds, true)) {
                        $rawIds = array_values(array_diff($rawIds, [$legacyId]));
                    }
                }
            }
            if ($hasBucket && !is_array($rawIds)) {
                $rawIds = [];
            } elseif (!$hasBucket && !is_array($rawIds)) {
                $rawIds = $defaultIds;
            }

            $allowedIds = array_keys($catalog[$bucket] ?? []);
            $enabled = [];
            foreach ($rawIds as $id) {
                $id = strval($id);
                if ($id !== '' && in_array($id, $allowedIds, true) && !in_array($id, $enabled, true)) {
                    $enabled[] = $id;
                }
            }

            $normalized[$bucket] = $hasBucket
                ? $enabled
                : (!empty($enabled) ? $enabled : $defaultIds);
        }

        return $normalized;
    }
}

if (!function_exists('chimGetPromptContextOptions')) {
    function chimGetPromptContextOptions(): array
    {
        $rawValue = chimGetGeneralSetting('PROMPT_CONTEXT_OPTIONS', '');
        return chimNormalizePromptContextOptions($rawValue);
    }
}

if (!function_exists('chimPromptContextOptionEnabled')) {
    function chimPromptContextOptionEnabled(string $bucket, string $id): bool
    {
        $options = chimGetPromptContextOptions();
        $enabled = $options[$bucket] ?? [];
        return in_array($id, $enabled, true);
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

if (!function_exists('chimApplyOverrideValueToGlobals')) {
    function chimApplyOverrideValueToGlobals(string $rawKey, $value): void
    {
        $rawKey = trim($rawKey);
        if ($rawKey === '') {
            return;
        }

        $schemaKey = strpos($rawKey, '@') !== false
            ? $rawKey
            : str_replace(' ', '@', $rawKey);
        $definition = chimGetSchemaDefinition($schemaKey);
        if (!empty($definition)) {
            $value = chimSettingsNormalizeScalar(chimSettingsStringifyValue($value), $definition);
        } elseif ($value === 'true') {
            $value = true;
        } elseif ($value === 'false') {
            $value = false;
        }

        if (strpos($rawKey, '@') !== false) {
            chimAssignNestedGlobalValueToGlobals(explode('@', $rawKey), $value);
            return;
        }

        if (strpos($rawKey, ' ') !== false) {
            chimAssignNestedGlobalValueToGlobals(explode(' ', $rawKey), $value);
            return;
        }

        $GLOBALS[$rawKey] = $value;
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

if (!function_exists('chimResolvePreferredTtsConnectorRow')) {
    function chimResolvePreferredTtsConnectorRow(string $driver = ''): ?array
    {
        if (!class_exists('TTSConnector')) {
            require_once(__DIR__ . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "tts_connector.class.php");
        }

        $connector = new TTSConnector();
        $normalizedDriver = $driver !== '' ? $connector->normalizeDriverValue($driver) : '';
        $rows = $connector->readAll();
        if (!is_array($rows) || empty($rows)) {
            return null;
        }

        if ($normalizedDriver !== '') {
            $rows = array_values(array_filter($rows, static function ($row) use ($connector, $normalizedDriver) {
                return $connector->normalizeDriverValue($row['driver'] ?? '') === $normalizedDriver;
            }));
            if (empty($rows)) {
                return null;
            }
        }

        $profileUsageMap = [];
        try {
            $usageRows = $GLOBALS["db"]->fetchAll(
                "SELECT tts_connector_id, COUNT(*) AS c
                 FROM core_profiles
                 WHERE tts_connector_id IS NOT NULL
                 GROUP BY tts_connector_id"
            );
            foreach ($usageRows as $usageRow) {
                $profileUsageMap[intval($usageRow['tts_connector_id'] ?? 0)] = intval($usageRow['c'] ?? 0);
            }
        } catch (\Throwable $e) {
        }

        $playerConnectorId = 0;
        try {
            $playerRow = $GLOBALS["db"]->fetchOne("SELECT value FROM core_player WHERE id = 'tts_connector_id' LIMIT 1");
            if (is_array($playerRow)) {
                $playerConnectorId = intval($playerRow['value'] ?? 0);
            }
        } catch (\Throwable $e) {
        }

        usort($rows, static function ($a, $b) use ($profileUsageMap, $playerConnectorId) {
            $aId = intval($a['id'] ?? 0);
            $bId = intval($b['id'] ?? 0);

            $aIsPlayer = ($aId > 0 && $aId === $playerConnectorId) ? 1 : 0;
            $bIsPlayer = ($bId > 0 && $bId === $playerConnectorId) ? 1 : 0;
            if ($aIsPlayer !== $bIsPlayer) {
                return $bIsPlayer <=> $aIsPlayer;
            }

            $aUsage = $profileUsageMap[$aId] ?? 0;
            $bUsage = $profileUsageMap[$bId] ?? 0;
            if ($aUsage !== $bUsage) {
                return $bUsage <=> $aUsage;
            }

            $aLabel = strtolower(trim(strval($a['label'] ?? '')));
            $bLabel = strtolower(trim(strval($b['label'] ?? '')));
            if ($aLabel !== $bLabel) {
                return $aLabel <=> $bLabel;
            }

            return $aId <=> $bId;
        });

        return $connector->getById(intval($rows[0]['id'] ?? 0)) ?: null;
    }
}

if (!function_exists('chimLoadPreferredTtsConnectorIntoGlobals')) {
    function chimLoadPreferredTtsConnectorIntoGlobals(string $driver = ''): void
    {
        if (!class_exists('TTSConnector')) {
            require_once(__DIR__ . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "tts_connector.class.php");
        }

        $row = chimResolvePreferredTtsConnectorRow($driver);
        if (!$row) {
            return;
        }

        $connector = new TTSConnector();
        $connector->setOldGlobals($row);
    }
}

if (!function_exists('chimEnsureTtsConnectorGlobals')) {
    function chimEnsureTtsConnectorGlobals(string $driver): void
    {
        if (!class_exists('TTSConnector')) {
            require_once(__DIR__ . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "tts_connector.class.php");
        }

        $connector = new TTSConnector();
        $normalizedDriver = $connector->normalizeDriverValue($driver);
        if ($normalizedDriver === '') {
            return;
        }

        $currentDriver = $connector->normalizeDriverValue($GLOBALS["TTSFUNCTION"] ?? '');
        $providerKey = $connector->getProviderKeyFromDriver($normalizedDriver);
        $hasProviderGlobals = ($providerKey !== '' && !empty($GLOBALS["TTS"][$providerKey]) && is_array($GLOBALS["TTS"][$providerKey]));
        if ($currentDriver === $normalizedDriver && $hasProviderGlobals) {
            return;
        }

        chimLoadPreferredTtsConnectorIntoGlobals($normalizedDriver);
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

if (!function_exists('chimMaybeSyncPlayerName')) {
    // Self-heal core_player.player_name when game's player differs from configured name.
    // Trusted only when candidate is non-empty, not narrator/Player, and not in core_npc_master.
    function chimMaybeSyncPlayerName($candidateName): bool
    {
        if (!is_string($candidateName)) return false;
        $candidate = trim($candidateName);
        if ($candidate === '') return false;
        if (strcasecmp($candidate, 'The Narrator') === 0) return false;
        if (strcasecmp($candidate, 'Player') === 0) return false;

        $current = trim((string)($GLOBALS["PLAYER_NAME"] ?? ''));
        if ($current !== '' && strcasecmp($current, $candidate) === 0) return false;

        static $cache = [];
        $key = strtolower($candidate);
        if (array_key_exists($key, $cache)) return $cache[$key];

        if (!isset($GLOBALS["db"]) || !is_object($GLOBALS["db"])) {
            $cache[$key] = false;
            return false;
        }

        try {
            $escaped = $GLOBALS["db"]->escape($candidate);
            $row = $GLOBALS["db"]->fetchOne(
                "SELECT 1 FROM core_npc_master WHERE LOWER(npc_name) = LOWER('{$escaped}') LIMIT 1"
            );
            if ($row) {
                $cache[$key] = false;
                return false;
            }
        } catch (\Throwable $e) {
            $cache[$key] = false;
            return false;
        }

        if (!class_exists('Player')) {
            require_once(__DIR__ . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "player.class.php");
        }

        try {
            $player = new Player();
            $player->set('player_name', $candidate);
            $GLOBALS["PLAYER_NAME"] = $candidate;
            Logger::info("[CHIM] Auto-synced player_name: '{$current}' -> '{$candidate}'");
            $cache[$key] = true;
            return true;
        } catch (\Throwable $e) {
            Logger::warn("[CHIM] chimMaybeSyncPlayerName failed: " . $e->getMessage());
            $cache[$key] = false;
            return false;
        }
    }
}

?>
