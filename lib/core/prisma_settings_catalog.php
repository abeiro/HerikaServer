<?php

/**
 * Shared field catalog for the web and Prisma Global Settings/Profile editors.
 * Keep presentation metadata here so both interfaces expose the same settings.
 */
function chimPrismaGlobalSettingsSections(): array
{
    return [
        'Prompt & Rechat' => [
            ['name' => 'PROMPT_HEAD', 'type' => 'longstring'],
            ['name' => 'EMOTEMOODS', 'type' => 'longstring'],
            ['name' => 'RECHAT_MODE', 'type' => 'select', 'values' => ['tight', 'conversational', 'group', 'random']],
            ['name' => 'ENFORCE_STRICT_RECHAT_RESPONSE', 'type' => 'boolean'],
            [
                'name' => 'COMPACT_CHAT_ENABLED',
                'type' => 'boolean',
                'default' => true,
                'help' => 'Use compact text instead of separate messages for conversation history. Does not affect the Narrator.',
            ],
            [
                'name' => 'PROMPT_HEAD_MARKDOWN_ENABLED', // Compact Prompt Info; keep the stored key for compatibility.
                'type' => 'boolean',
                'default' => false,
                'help' => 'Use Markdown headings instead of XML tags for all prompt sections.',
            ],
            ['name' => 'RELATIONSHIP_UPDATE_CHANCE', 'type' => 'integer', 'min' => 0, 'max' => 100, 'default' => 50],
        ],
        'Oghma' => [
            ['name' => 'OGHMA_INFINIUM', 'type' => 'boolean'],
            ['name' => 'OGHMA_AMOUNT', 'type' => 'select', 'values' => ['1', '2', '3']],
            ['name' => 'RACIAL_OGHMA', 'type' => 'boolean'],
            ['name' => 'LOCATION_OGHMA', 'type' => 'boolean'],
            ['name' => 'CORE_CONNECTOR_OGHMA_CUSTOM', 'type' => 'foreign:core_llm_connector:id:label'],
            ['name' => 'OGHMA_CUSTOM', 'type' => 'boolean'],
        ],
        'Memory' => [
            ['name' => 'FEATURES@MEMORY_EMBEDDING@ENABLED', 'type' => 'boolean'],
            ['name' => 'FEATURES@MEMORY_EMBEDDING@TXTAI_URL', 'type' => 'url'],
            ['name' => 'FEATURES@MEMORY_EMBEDDING@USE_TEXT2VEC', 'type' => 'boolean'],
            ['name' => 'FEATURES@MEMORY_EMBEDDING@AUTO_CREATE_SUMMARY_INTERVAL', 'type' => 'integer'],
            [
                'name' => 'PLAYER_WORST_MEMORY_GAME_DAYS',
                'type' => 'integer',
                'min' => 0,
                'max' => 365,
                'default' => 7,
                'help' => 'How long the player\'s worst memory of an NPC lingers before it fades, in in-game days (0 = never forget). Default 7 (one game-week). NPC-to-NPC worst memories are always permanent.',
            ],
            [
                'name' => 'SHORT_TERM_MEMORY_IN_COMPACT_CHAT',
                'type' => 'boolean',
                'default' => true,
                'help' => 'Keep injecting short-term memory summaries while Compact Chat is active. Turn this off if you use Compact Chat to keep the prompt small. Has no effect on profiles that do not have Short Term Memory enabled.',
            ],
            ['name' => 'NEVER_CLEAR_RELATIONSHIP_DATA', 'type' => 'boolean', 'default' => false],
        ],
        'Misc' => [
            ['name' => 'AUTO_LOCK_PROFILE', 'type' => 'boolean'],
            ['name' => 'AUTOFILL_CUSTOM_PROFILES', 'type' => 'boolean'],
            ['name' => 'AUTOFILL_CUSTOM_PROFILES_TRIGGER', 'type' => 'integer', 'min' => 10, 'max' => 100],
            ['name' => 'BGL_TRIGGER_HOURS', 'type' => 'number', 'min' => 1, 'max' => 720, 'step' => 0.1, 'default' => 24],
            ['name' => 'END_CONVERSATION_COOLDOWN', 'type' => 'integer', 'min' => 0, 'max' => 300],
        ],
        'Quests' => [
            ['name' => 'CHIM_AI_QUEST_PROGRESSION', 'type' => 'boolean'],
            ['name' => 'CHIM_PLAYER_ONLY_QUEST_ADVANCEMENT', 'type' => 'boolean'],
        ],
        // Each connector is followed by the boolean that makes its tasks available. Scene
        // Classifier and Relationship Management reuse their existing settings.
        'Global Connectors' => [
            ['name' => 'CORE_CONNECTOR_PLAYER', 'type' => 'foreign:core_llm_connector:id:label'],
            ['name' => 'PLAYER_RESPEECH', 'type' => 'boolean', 'default' => true],
            ['name' => 'CORE_CONNECTOR_SUMMARY', 'type' => 'foreign:core_llm_connector:id:label'],
            ['name' => 'CORE_CONNECTOR_SUMMARY_ENABLED', 'type' => 'boolean', 'default' => true],
            ['name' => 'CORE_CONNECTOR_MEDIUMTERM', 'type' => 'foreign:core_llm_connector:id:label'],
            ['name' => 'CORE_CONNECTOR_MEDIUMTERM_ENABLED', 'type' => 'boolean', 'default' => true],
            ['name' => 'CORE_CONNECTOR_SCENECLASSIFIER', 'type' => 'foreign:core_llm_connector:id:label'],
            ['name' => 'SCENE_CLASSIFIER_ENABLED', 'type' => 'boolean', 'default' => true],
            ['name' => 'CORE_CONNECTOR_PROFILES', 'type' => 'foreign:core_llm_connector:id:label'],
            ['name' => 'CORE_CONNECTOR_PROFILES_ENABLED', 'type' => 'boolean', 'default' => true],
            ['name' => 'CORE_CONNECTOR_DIRECTOR', 'type' => 'foreign:core_llm_connector:id:label'],
            ['name' => 'CORE_CONNECTOR_DIRECTOR_ENABLED', 'type' => 'boolean', 'default' => true],
            ['name' => 'CORE_CONNECTOR_BGL', 'type' => 'foreign:core_llm_connector:id:label'],
            ['name' => 'CORE_CONNECTOR_BGL_ENABLED', 'type' => 'boolean', 'default' => true],
            ['name' => 'RELLLM_CONNECTOR', 'type' => 'foreign:core_llm_connector:id:label'],
            ['name' => 'RELATIONSHIP_SYSTEM_ENABLED', 'type' => 'boolean', 'default' => true],
        ],
        'Context' => [
            ['name' => 'DETECT_MAGIC_EVENT', 'type' => 'boolean'],
            ['name' => 'GROUND_ITEMS_DESCRIPTIONS_ONLY', 'type' => 'boolean'],
            ['name' => 'INVENTORY_ITEMS_DESCRIPTIONS_ONLY', 'type' => 'boolean'],
            ['name' => 'HIDE_AMBIENT_COMBAT', 'type' => 'boolean'],
            ['name' => 'DISABLE_REANIMATION_TRACKING', 'type' => 'boolean', 'action' => 'clear_reanimation'],
            ['name' => 'TRANSFORMATION_DETECTION', 'type' => 'boolean'],
            ['name' => 'POWER_AWARENESS_ENABLED', 'type' => 'boolean'],
            ['name' => 'CHIM_ITEM_PICKUP_EVENTLOG_MIN_VALUE', 'type' => 'integer', 'min' => 0],
            ['name' => 'PROMPT_TIMESTAMP', 'type' => 'boolean'],
        ],
        'Context Selections' => [
            ['name' => 'MAGIC_EVENT_BLACKLIST', 'type' => 'longstring'],
            ['name' => 'LOCATION_BLACKLIST', 'type' => 'longstring'],
            ['name' => 'ITEM_BLACKLIST', 'type' => 'longstring'],
            ['name' => 'EVENT_TYPE_FILTER', 'type' => 'longstring'],
        ],
        'Translation' => [
            ['name' => 'TRANSLATION_FUNCTION', 'type' => 'select', 'values' => ['none', 'DeepL']],
            ['name' => 'TRANSLATION@settings@translate_audio', 'type' => 'boolean'],
            ['name' => 'TRANSLATION@settings@translate_text', 'type' => 'boolean'],
            ['name' => 'TRANSLATION@settings@save_translated_text', 'type' => 'boolean'],
            ['name' => 'TRANSLATION@settings@translate_player_audio', 'type' => 'boolean'],
            ['name' => 'TRANSLATION@settings@save_translated_player_text', 'type' => 'boolean'],
            ['name' => 'TRANSLATION@DeepL@source_language', 'type' => 'string'],
            ['name' => 'TRANSLATION@DeepL@target_language', 'type' => 'string'],
            ['name' => 'TRANSLATION@DeepL@url', 'type' => 'url'],
            ['name' => 'TRANSLATION@DeepL@player_source_language', 'type' => 'string'],
            ['name' => 'TRANSLATION@DeepL@player_target_language', 'type' => 'string'],
        ],
    ];
}

function chimPrismaGlobalSettingsTabs(): array
{
    return [
        'prompt-rechat' => '💬 Prompt & Rechat',
        'ai-memory' => '🧠 Memory & Others',
        'context-knowledge' => '📚 Context & Knowledge',
        'global-connectors' => '🔌 Global Connectors',
    ];
}

function chimPrismaGlobalSettingsSectionTabs(): array
{
    return [
        'Prompt & Rechat' => 'prompt-rechat',
        'Memory' => 'ai-memory', 'Misc' => 'ai-memory', 'Quests' => 'ai-memory', 'Translation' => 'ai-memory',
        'Oghma' => 'context-knowledge', 'Context' => 'context-knowledge', 'Context Selections' => 'context-knowledge',
        'Global Connectors' => 'global-connectors',
    ];
}

function chimPrismaProfileMetadataCatalog(): array
{
    return [
        'Profiles & Memories' => [
            ['name' => 'DYNAMIC_PROFILE_ENABLED', 'type' => 'boolean'],
            ['name' => 'DYNAMIC_PROFILE_FIELDS', 'type' => 'multiselect', 'schema' => 'DYNAMIC_PROFILE_FIELDS'],
            ['name' => 'MIDDLE_TERM_MEMORY_ENABLED', 'type' => 'boolean'],
            ['name' => 'SHORT_TERM_MEMORY_ENABLED', 'type' => 'boolean'],
            ['name' => 'SHORT_TERM_MEMORY_MAX', 'type' => 'integer', 'min' => 1, 'max' => 50],
            ['name' => 'CONTEXT_HISTORY_DYNAMIC_PROFILE', 'type' => 'integer', 'min' => 0, 'max' => 400],
            ['name' => 'RPG_COMMENTS', 'type' => 'multiselect', 'schema' => 'RPG_COMMENTS'],
            ['name' => 'RPG_COMMENTS_CHANCE', 'type' => 'integer', 'min' => 0, 'max' => 100],
        ],
        'Diary' => [
            ['name' => 'AUTO_DIARY_ENABLED', 'type' => 'boolean'],
            ['name' => 'AUTO_DIARY_WAIT_ENABLED', 'type' => 'boolean'],
            ['name' => 'MATERIALIZE_DIARY_ENABLED', 'type' => 'boolean'],
            ['name' => 'LATEST_DIARY_CONTEXT_ENABLED', 'type' => 'boolean'],
            ['name' => 'DIARY_PROMPT', 'type' => 'longstring', 'copyable' => true],
            ['name' => 'DIARY_COOLDOWN', 'type' => 'integer', 'min' => 10, 'max' => 1200, 'copyable' => true],
            ['name' => 'CONTEXT_HISTORY_DIARY', 'type' => 'integer', 'min' => 0, 'max' => 400, 'copyable' => true],
        ],
        'LLM' => [
            ['name' => 'LLM_RANDOMIZER_ENABLED', 'type' => 'boolean'],
            ['name' => 'LLM_FALLBACK_ENABLED', 'type' => 'boolean'],
            ['name' => 'CORE_LANG', 'type' => 'select', 'values' => ['en', 'de', 'es', 'fr', 'jp'], 'copyable' => true],
            ['name' => 'LANG_LLM_XTTS', 'type' => 'boolean', 'copyable' => true],
            ['name' => 'MAX_WORDS_LIMIT', 'type' => 'integer', 'copyable' => true],
        ],
        'Rechat' => [
            ['name' => 'RECHAT_H', 'type' => 'integer', 'min' => 1, 'max' => 10, 'copyable' => true],
            ['name' => 'RECHAT_P', 'type' => 'integer', 'min' => 0, 'max' => 100, 'copyable' => true],
            ['name' => 'RECHAT_ALLOW_ACTIONS', 'type' => 'boolean', 'copyable' => true],
        ],
        'Bored Event' => [
            ['name' => 'BORED_EVENT', 'type' => 'integer', 'min' => 0, 'max' => 100, 'copyable' => true],
        ],
        'Context' => [
            ['name' => 'CONTEXT_HISTORY', 'type' => 'integer', 'min' => 0, 'max' => 200, 'copyable' => true],
        ],
        'Combat' => [
            ['name' => 'COMBAT_BARK_COOLDOWN', 'type' => 'integer', 'min' => 10, 'max' => 600, 'copyable' => true],
        ],
        'Quest' => [
            ['name' => 'QUEST_COMMENT', 'type' => 'boolean', 'copyable' => true],
            ['name' => 'QUEST_COMMENT_CHANCE', 'type' => 'select', 'values' => ['10%', '25%', '50%', '75%', '100%'], 'copyable' => true],
        ],
    ];
}

function chimPrismaProfileSyncableMetadataKeys(): array
{
    $keys = [];
    foreach (chimPrismaProfileMetadataCatalog() as $fields) {
        foreach ($fields as $field) {
            if (!empty($field['copyable'])) {
                $keys[] = $field['name'];
            }
        }
    }
    return $keys;
}

function chimPrismaProfileConnectorCatalog(): array
{
    return [
        'Response Connectors' => [
            ['name' => 'llm_primary_id', 'label' => '🧠 Standard LLM', 'source' => 'llm'],
            ['name' => 'llm_secondary_id', 'label' => '⚡ Fast LLM', 'source' => 'llm'],
            ['name' => 'llm_tertiary_id', 'label' => '💪 Powerful LLM', 'source' => 'llm'],
            ['name' => 'llm_quaternary_id', 'label' => '🧪 Experimental LLM', 'source' => 'llm'],
        ],
        'Other Connectors' => [
            ['name' => 'tts_connector_id', 'label' => '🔊 TTS Connector', 'source' => 'tts'],
            ['name' => 'diary_connector_id', 'label' => '📙 Diary Connector', 'source' => 'llm'],
            ['name' => 'llm_formatter_id', 'label' => '🧹 Formatter Connector', 'source' => 'llm'],
            ['name' => 'llm_fallback_id', 'label' => '🛟 Fallback Connector', 'source' => 'llm'],
        ],
    ];
}
