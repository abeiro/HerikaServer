<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "prisma_settings_catalog.php");

chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_stt_connector' => false,
    'load_itt_connector' => false,
]);

$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
if ($uiPos !== false) {
    $webRoot = substr($scriptPath, 0, $uiPos);
} else {
    $webRoot = '';
}
if ($webRoot === '/') {
    $webRoot = '';
}
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__ . DIRECTORY_SEPARATOR . "profile_loader.php");
$TITLE = "⚙️ CHIM - Global Settings";
ob_start();
include(__DIR__ . DIRECTORY_SEPARATOR . "tmpl" . DIRECTORY_SEPARATOR . "head.html");

$saveSuccess = isset($_GET['_saved']) && $_GET['_saved'] === '1';
$clearReanimationResult = null;
$promptContextSectionTitle = 'Context Selections';
$gsSections = chimPrismaGlobalSettingsSections();
$settingsTabs = chimPrismaGlobalSettingsTabs();
$sectionTabs = chimPrismaGlobalSettingsSectionTabs();

$tabControlPanels = [
    'prompt-rechat' => 'settings-panel-prompt-rechat-prompt-rechat',
    'ai-memory' => 'settings-panel-ai-memory-memory',
    'context-knowledge' => 'settings-panel-context-knowledge-oghma',
    'global-connectors' => 'settings-panel-global-connectors-global-connectors',
];

$connectorAvailabilityToggles = chimGlobalLlmConnectorAvailabilityMap();

// Paired toggles stay beside their connector instead of appearing twice. OGHMA_CUSTOM picks
// the Oghma extraction backend rather than gating a slot, so it keeps its plain checkbox.
$pairedConnectorToggles = array_merge(array_values($connectorAvailabilityToggles), ['OGHMA_CUSTOM']);
foreach ($gsSections as $sectionName => $fields) {
    $gsSections[$sectionName] = array_values(array_filter($fields, static function (array $field) use ($pairedConnectorToggles): bool {
        return !in_array($field['name'] ?? '', $pairedConnectorToggles, true);
    }));
}

function pretty_label(string $flatName): string
{
    if (strpos($flatName, 'FEATURES@MEMORY_EMBEDDING@') === 0) {
        $parts = explode('@', $flatName);
        $last = end($parts) ?: $flatName;
        if (strtoupper(trim($last)) === 'TXTAI_URL') {
            return 'MiniMe / TXT2VEC URL';
        }
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
        'CORE_CONNECTOR_BGL' => 'Background Life',
        'CORE_CONNECTOR_OGHMA_CUSTOM' => 'Custom Oghma LLM',
        'RELLLM_CONNECTOR' => 'Relationship Management',
        'RELATIONSHIP_UPDATE_CHANCE' => 'Relationship Update Chance',
        'NEVER_CLEAR_RELATIONSHIP_DATA' => 'Never Clear Relationship Data',
        'COMPACT_CHAT_ENABLED' => 'Compact Chat',
        'PROMPT_HEAD_MARKDOWN_ENABLED' => 'Compact Prompt Info',
        'PLAYER_WORST_MEMORY_GAME_DAYS' => 'Worst Memory Lifespan',
        'EMOTEMOODS' => 'Emote Moods',
        'OGHMA_INFINIUM' => 'Oghma Infinium',
        'OGHMA_AMOUNT' => 'Oghma Articles Amount',
        'RACIAL_OGHMA' => 'Force Racial Oghma',
        'LOCATION_OGHMA' => 'Force Location Oghma',
        'ENFORCE_STRICT_RECHAT_RESPONSE' => 'Strict Rechat Targeting',
        'BGL_TRIGGER_HOURS' => 'Background Life Trigger Time',
        'CHIM_AI_QUEST_PROGRESSION' => 'CHIM AI Quest Progression (Beta)',
        'CHIM_PLAYER_ONLY_QUEST_ADVANCEMENT' => 'Player Only Quest Advancement',
        'CHIM_ITEM_PICKUP_EVENTLOG_MIN_VALUE' => 'Item Pickup Detection Value',
    ];
    if (isset($customLabels[$flatName])) {
        return $customLabels[$flatName];
    }

    $parts = explode('@', $flatName);
    $prettyParts = [];
    foreach ($parts as $part) {
        $prettyParts[] = ucwords(str_replace('_', ' ', strtolower(trim($part))));
    }
    return implode(' → ', $prettyParts);
}

function icon_for_field(string $flatName): string
{
    $u = strtoupper($flatName);
    $icons = [
        'PLAYER_NAME' => '🏷️',
        'PROMPT_HEAD' => '🔝',
        'PROMPT_HEAD_MARKDOWN_ENABLED' => '📝',
        'EMOTEMOODS' => '🎭',
        'RECHAT_MODE' => '🔁',
        'ENFORCE_STRICT_RECHAT_RESPONSE' => '🎯',
        'OGHMA_INFINIUM' => '📚',
        'OGHMA_AMOUNT' => '🔢',
        'RACIAL_OGHMA' => '🧬',
        'LOCATION_OGHMA' => '📍',
        'FEATURES@MEMORY_EMBEDDING@ENABLED' => '🧠',
        'FEATURES@MEMORY_EMBEDDING@TXTAI_URL' => '🔗',
        'FEATURES@MEMORY_EMBEDDING@USE_TEXT2VEC' => '🔤',
        'FEATURES@MEMORY_EMBEDDING@AUTO_CREATE_SUMMARY_INTERVAL' => '⏱️',
        'PLAYER_WORST_MEMORY_GAME_DAYS' => '💔',
        'AUTO_LOCK_PROFILE' => '🔒',
        'AUTOFILL_CUSTOM_PROFILES' => '✨',
        'AUTOFILL_CUSTOM_PROFILES_TRIGGER' => '🎯',
        'BGL_TRIGGER_HOURS' => '🌍',
        'END_CONVERSATION_COOLDOWN' => '⏳',
        'CHIM_AI_QUEST_PROGRESSION' => '🗺️',
        'CHIM_PLAYER_ONLY_QUEST_ADVANCEMENT' => '🧍',
        'SCENE_CLASSIFIER_ENABLED' => '🎭',
        'RELATIONSHIP_SYSTEM_ENABLED' => '💞',
        'RELLLM_CONNECTOR' => '🔗',
        'RELATIONSHIP_UPDATE_CHANCE' => '🎲',
        'NEVER_CLEAR_RELATIONSHIP_DATA' => '🕰️',
        'COMPACT_CHAT_ENABLED' => '💬',
        'DETECT_MAGIC_EVENT' => '✨',
        'GROUND_ITEMS_DESCRIPTIONS_ONLY' => '🪨',
        'INVENTORY_ITEMS_DESCRIPTIONS_ONLY' => '🎒',
        'HIDE_AMBIENT_COMBAT' => '🕊️',
        'DISABLE_REANIMATION_TRACKING' => '🧟',
        'TRANSFORMATION_DETECTION' => '🐺',
        'POWER_AWARENESS_ENABLED' => '⚔️',
        'CHIM_ITEM_PICKUP_EVENTLOG_MIN_VALUE' => '💰',
        'PROMPT_TIMESTAMP' => '🕐',
        'MAGIC_EVENT_BLACKLIST' => '🪄',
        'LOCATION_BLACKLIST' => '📍',
        'ITEM_BLACKLIST' => '📦',
        'EVENT_TYPE_FILTER' => '🔍',
        'TRANSLATION_FUNCTION' => '🌐',
        'TRANSLATION@SETTINGS@TRANSLATE_AUDIO' => '🎧',
        'TRANSLATION@SETTINGS@TRANSLATE_TEXT' => '📝',
        'TRANSLATION@SETTINGS@SAVE_TRANSLATED_TEXT' => '💾',
        'TRANSLATION@SETTINGS@TRANSLATE_PLAYER_AUDIO' => '🎙️',
        'TRANSLATION@SETTINGS@SAVE_TRANSLATED_PLAYER_TEXT' => '💾',
        'TRANSLATION@DEEPL@SOURCE_LANGUAGE' => '🗣️',
        'TRANSLATION@DEEPL@TARGET_LANGUAGE' => '🌍',
        'TRANSLATION@DEEPL@URL' => '🔗',
        'TRANSLATION@DEEPL@PLAYER_SOURCE_LANGUAGE' => '🎤',
        'TRANSLATION@DEEPL@PLAYER_TARGET_LANGUAGE' => '🌎',
    ];
    if (isset($icons[$u])) return $icons[$u];
    if (strpos($u, 'CORE_CONNECTOR_') === 0) {
        if ($u === 'CORE_CONNECTOR_PLAYER') return '🎮';
        if ($u === 'CORE_CONNECTOR_SUMMARY') return '📝';
        if ($u === 'CORE_CONNECTOR_MEDIUMTERM') return '🧠';
        if ($u === 'CORE_CONNECTOR_SCENECLASSIFIER') return '🎭';
        if ($u === 'CORE_CONNECTOR_PROFILES') return '👥';
        if ($u === 'CORE_CONNECTOR_DIRECTOR') return '🎬';
        if ($u === 'CORE_CONNECTOR_BGL') return '⏱️';
        if ($u === 'CORE_CONNECTOR_OGHMA_CUSTOM') return '📖';
        return '🔌';
    }
    if (strpos($u, 'RESPEECH') !== false) return '🦜';
    if (strpos($u, 'SPEECH_STYLE') !== false) return '🦜';
    if (strpos($u, 'SUMMARY_PROMPT') === 0) return '📝';
    if (strpos($u, 'DYNAMIC_PROMPT_') === 0) return '👥';
    if (strpos($u, 'DIARY') !== false) return '📙';
    if (strpos($u, 'NARRATOR') !== false) return '🗣️';
    if (strpos($u, 'MEMORY') !== false) return '🧠';
    if (strpos($u, 'COOLDOWN') !== false || strpos($u, 'INTERVAL') !== false) return '⏱️';
    return '🧩';
}

function select_option_label(string $fieldName, string $optionValue): string
{
    if ($fieldName === 'RECHAT_MODE') {
        $labels = [
            'tight' => 'Tight',
            'conversational' => 'Conversational',
            'group' => 'Group',
            'random' => 'Random (Recommended)',
        ];

        return $labels[$optionValue] ?? ucwords(str_replace('_', ' ', strtolower($optionValue)));
    }

    return $optionValue;
}

function filter_browse_field_configs(): array
{
    return [
        'MAGIC_EVENT_BLACKLIST' => [
            'button_label' => 'Select',
            'modal_title' => 'Recent Magic Events',
            'modal_hint' => 'Shows recent npcspellcast names from the last 5000 eventlog rows. Check a value to keep it in the filter, uncheck it to remove it.',
        ],
        'LOCATION_BLACKLIST' => [
            'button_label' => 'Select',
            'modal_title' => 'Recent Locations',
            'modal_hint' => 'Shows recent Points of Interest candidates parsed from the last 5000 eventlog rows. Check a value to keep it in the filter, uncheck it to remove it.',
        ],
        'ITEM_BLACKLIST' => [
            'button_label' => 'Select',
            'modal_title' => 'Recent Items',
            'modal_hint' => 'Shows recent item names parsed from itemfound and nearby-item eventlog entries. Check a value to keep it in the filter, uncheck it to remove it.',
        ],
        'EVENT_TYPE_FILTER' => [
            'button_label' => 'Select',
            'modal_title' => 'Recent Event Types',
            'modal_hint' => 'Shows recent prompt-relevant event types from the last 5000 eventlog rows. Check a value to keep it in the filter, uncheck it to remove it.',
        ],
    ];
}

function prompt_context_bucket_title(string $bucket): string
{
    $labels = [
        'enabled_sections' => 'Top-Level Sections',
        'enabled_character_subsections' => 'Character Subsections',
        'enabled_appearance_subsections' => 'Appearance / State Subsections',
        'enabled_general_subsections' => 'General Instruction Subsections',
        'enabled_nearby_actor_subsections' => 'Nearby Actor Details',
        'enabled_nearby_item_subsections' => 'Nearby Item Details',
    ];

    return $labels[$bucket] ?? $bucket;
}

function current_value(string $flatName)
{
    $definition = chimGetSchemaDefinition($flatName);
    $default = $definition['default'] ?? '';
    return chimReadLegacyGlobalValue($flatName, $default);
}

function current_description(string $flatName, array $rowMap): string
{
    // Use the revised Compact Chat help even when an older description was stored.
    if ($flatName === 'COMPACT_CHAT_ENABLED') {
        return chimGetSchemaDescription($flatName);
    }
    $row = $rowMap[$flatName] ?? null;
    $description = is_array($row) ? trim(strval($row['description'] ?? '')) : '';
    if ($description !== '') {
        return $description;
    }
    return chimGetSchemaDescription($flatName);
}

function render_connector_availability_switch(string $connectorField, string $toggleField): string
{
    $isOn = (bool) current_value($toggleField);
    $inputId = 'availability-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $toggleField));
    $connectorLabel = pretty_label($connectorField);
    $escapedName = htmlspecialchars($toggleField, ENT_QUOTES, 'UTF-8');
    $escapedId = htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8');
    $hint = 'Turn off to make ' . $connectorLabel . ' unavailable. The connector selection is kept.';

    return '<div class="connector-availability' . ($isOn ? '' : ' is-off') . '" title="' . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') . '">'
        . '<input type="hidden" name="' . $escapedName . '" value="false">'
        . '<label class="connector-availability-label" for="' . $escapedId . '">'
        . '<span class="connector-availability-sr">' . htmlspecialchars($connectorLabel, ENT_QUOTES, 'UTF-8') . ' available</span>'
        . '<span class="connector-availability-state" aria-hidden="true">' . ($isOn ? 'On' : 'Off') . '</span>'
        . '</label>'
        . '<input type="checkbox" class="connector-availability-input" id="' . $escapedId . '"'
        . ' name="' . $escapedName . '" value="true"' . ($isOn ? ' checked' : '') . '>'
        . '</div>';
}

function render_provider_help(string $flatName, string $help, string $webRoot): string
{
    if ($flatName === 'CHIM_AI_QUEST_PROGRESSION') {
        $prefix = 'Enable CHIM AI quest progression. Allows you to progress regular Skyrim quests with AI dialogue. Most vanilla non radiant quests are supported.';
        $url = rtrim($webRoot, '/') . '/ui/events-memories.php?tab=questgen';
        return htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8')
            . ' <a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">Open AI Quest Manager</a> for more info.';
    }

    return htmlspecialchars($help, ENT_QUOTES, 'UTF-8');
}

function normalize_posted_value(string $type, $posted)
{
    if ($type === 'boolean') {
        return ($posted === 'true' || $posted === true || $posted === 1 || $posted === '1');
    }
    if ($type === 'integer') {
        return ($posted === '' || $posted === null) ? '' : intval($posted);
    }
    if ($type === 'number') {
        return ($posted === '' || $posted === null) ? '' : floatval($posted);
    }
    if (is_array($posted)) {
        return $posted;
    }
    return trim(strval($posted));
}

$generalSettingRows = chimGetAllGeneralSettings();
$generalSettingRowMap = [];
foreach ($generalSettingRows as $row) {
    if (is_array($row) && isset($row['id'])) {
        $generalSettingRowMap[strval($row['id'])] = $row;
    }
}

$promptContextCatalog = chimGetPromptContextOptionCatalog();
$currentPromptContextOptions = chimGetPromptContextOptions();
$filterBrowseFieldConfigs = filter_browse_field_configs();

$foreignOptions = [];
try {
    $foreignOptions['core_llm_connector:id:label'] = $GLOBALS["db"]->fetchAll("SELECT id, label FROM core_llm_connector ORDER BY LOWER(label) ASC, id ASC");
} catch (\Throwable $e) {
    $foreignOptions['core_llm_connector:id:label'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_reanimation_status'])) {
    try {
        $db = $GLOBALS["db"];
        $db->execQuery("UPDATE core_npc_master SET extended_data = extended_data - 'reanimated' WHERE extended_data::text LIKE '%reanimated%'");

        $zombiePhrases = [
            ' You have been reanimated from death as a zombie.',
            'You have been reanimated from death as a zombie. ',
            'You have been reanimated from death as a zombie.',
        ];
        foreach ($zombiePhrases as $phrase) {
            $escaped = $db->escape($phrase);
            $db->execQuery("UPDATE core_npc_master SET core = REPLACE(core, '{$escaped}', '') WHERE core LIKE '%{$escaped}%'");
        }

        $clearReanimationResult = ['success' => true, 'message' => 'Successfully cleared reanimation status from all NPCs.'];
        Logger::info("[GLOBAL_SETTINGS] Cleared reanimation status from all NPCs");
    } catch (\Throwable $e) {
        $clearReanimationResult = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        Logger::error("[GLOBAL_SETTINGS] Failed to clear reanimation status: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_all'])) {
    $didSave = true;

    foreach ($gsSections as $fields) {
        foreach ($fields as $field) {
            $name = $field['name'];
            $type = $field['type'];

            if ($type === 'boolean') {
                $value = normalize_posted_value('boolean', $_POST[$name] ?? 'false');
            } else {
                $value = normalize_posted_value($type, $_POST[$name] ?? '');
            }

            $description = current_description($name, $generalSettingRowMap);
            if (!chimSetGeneralSetting($name, $value, $description)) {
                $didSave = false;
            }
        }
    }

    foreach ($pairedConnectorToggles as $name) {
        $value = normalize_posted_value('boolean', $_POST[$name] ?? 'false');
        $description = current_description($name, $generalSettingRowMap);
        if (!chimSetGeneralSetting($name, $value, $description)) {
            $didSave = false;
        }
    }

    $postedPromptContextRaw = [];
    foreach ($promptContextCatalog as $bucket => $_options) {
        $postedPromptContextRaw[$bucket] = array_values(array_map('strval', $_POST['prompt_context_' . $bucket] ?? []));
    }
    $postedPromptContextOptions = chimNormalizePromptContextOptions($postedPromptContextRaw);
    $promptContextDescription = current_description('PROMPT_CONTEXT_OPTIONS', $generalSettingRowMap);
    if (!chimSetGeneralSetting('PROMPT_CONTEXT_OPTIONS', $postedPromptContextOptions, $promptContextDescription)) {
        $didSave = false;
    }

    $memoryCompatDescription = current_description('FEATURES@MEMORY_EMBEDDING@AUTO_CREATE_SUMMARYS', $generalSettingRowMap);
    if (!chimSetGeneralSetting('FEATURES@MEMORY_EMBEDDING@AUTO_CREATE_SUMMARYS', true, $memoryCompatDescription)) {
        $didSave = false;
    }

    if ($didSave) {
        chimLoadGeneralSettingsIntoGlobals();
        Logger::info("Global settings saved to general_settings by UI");
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        $redirectUrl = strtok($_SERVER['REQUEST_URI'], '?') . '?_saved=1&_ts=' . time();
        header("Location: " . $redirectUrl);
        exit;
    }

    Logger::error("Failed writing general_settings from Global Settings UI");
}

?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css?v=gs2">
<style>
main {
    padding: 10px clamp(10px, 2.5vw, 34px) 24px;
    width: 100%;
    margin: 0;
}

footer {
    position: fixed;
    bottom: 0;
    width: 100%;
    height: 20px;
    background: #031633;
    z-index: 100;
}

@font-face {
    font-family: 'MagicCards';
    src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype');
    font-weight: normal;
    font-style: normal;
}

.page-header {
    margin: 0 0 10px 0;
    padding: 10px 14px;
    background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(28, 28, 28, 0.98));
    border-radius: 10px;
    border: 1px solid #3a3a3a;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    text-align: left;
}

.page-header-row {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

h1.gs-title {
    margin: 0;
    font-family: 'MagicCards', serif;
    word-spacing: 8px;
    font-size: 1.75em;
    color: rgb(242, 124, 17);
    text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
}

.page-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-left: auto;
}

.header-note {
    color: #b9c7d9;
    font-size: 13px;
}

.settings-tabs {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 8px;
    margin-bottom: 10px;
    padding: 8px;
    border: 1px solid #3a3a3a;
    border-radius: 10px;
    background: rgba(30, 30, 30, 0.92);
}

.settings-tab {
    position: relative;
    min-height: 40px;
    padding: 8px 12px;
    border: 1px solid #444;
    border-radius: 7px;
    background: #303030;
    color: #ddd;
    font-weight: 700;
    cursor: pointer;
    transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
}

.settings-tab:hover {
    border-color: rgba(242, 124, 17, 0.55);
    background: #383838;
}

body .settings-tabs .settings-tab.is-active {
    border-color: rgb(242,124,17) !important;
    color: #fff !important;
    background: rgba(92, 53, 25, 0.95) !important;
    box-shadow: inset 0 0 0 1px rgba(242, 124, 17, 0.28), 0 0 12px rgba(242, 124, 17, 0.24) !important;
    transform: translateY(-1px) !important;
}

.settings-tab:focus-visible {
    outline: 2px solid rgb(242,124,17);
    outline-offset: 2px;
}

.content-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
    margin-bottom: 24px;
}

.content-section {
    background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
    padding: 14px;
    border-radius: 10px;
    border: 1px solid #3a3a3a;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
}

.content-section h2 {
    margin-bottom: 12px;
    padding-bottom: 8px;
    font-family: 'MagicCards', serif;
    word-spacing: 7px;
    font-size: 1.18em;
    color: rgb(242,124,17);
    text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
    border-bottom: 1px solid rgba(242, 124, 17, 0.2);
}

.provider-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
}

.provider-subsection-title {
    grid-column: 1 / -1;
    margin: 8px 0 2px;
    padding: 4px 2px 8px;
    font-family: 'MagicCards', serif;
    color: rgb(242,124,17);
    text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
    border-bottom: 1px solid rgba(242, 124, 17, 0.2);
    font-size: 1.05em;
}

.provider-card {
    background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.95));
    border: 1px solid #3a3a3a;
    border-radius: 8px;
    padding: 12px 14px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.02);
    display: grid;
    grid-template-columns: minmax(220px, 280px) minmax(420px, 720px) minmax(200px, 1fr);
    gap: 12px 16px;
    align-items: center;
}

.connector-section .provider-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.connector-section .provider-card {
    grid-template-columns: 1fr;
    align-content: start;
    align-items: start;
}

.connector-section .provider-title {
    flex-wrap: wrap;
    row-gap: 6px;
}

.connector-section .provider-body {
    width: 100%;
}

.connector-section .provider-help {
    margin-top: 0;
}

.provider-head {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 0;
    min-width: 0;
}

.provider-title {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #e0e0e0;
    min-width: 0;
    width: 100%;
    flex-wrap: nowrap;
}

.provider-icon {
    width: 24px;
    height: 24px;
    border-radius: 6px;
    background: linear-gradient(135deg, rgba(58, 58, 58, 0.9), rgba(48, 48, 48, 0.9));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.provider-body {
    display: flex;
    gap: 8px;
    align-items: center;
    min-width: 0;
}

.filter-browse-wrap {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    width: 100%;
    min-width: 0;
}

.filter-setting-card {
    grid-template-columns: minmax(180px, 0.65fr) minmax(420px, 1.7fr) minmax(180px, 0.65fr);
}

.filter-setting-card .provider-body {
    width: 100%;
}

.btn-filter-browse {
    flex: 0 0 auto;
    align-self: flex-start;
    min-width: 64px;
    border: 1px solid rgba(77, 144, 254, 0.42);
    border-radius: 6px;
    background: linear-gradient(135deg, rgba(34, 84, 173, 0.95), rgba(24, 62, 130, 0.98));
    color: #e8f1ff;
    font-weight: 700;
    font-size: 11px;
    line-height: 1.15;
    padding: 6px 9px;
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease;
}

.btn-filter-browse:hover {
    background: linear-gradient(135deg, rgba(46, 101, 198, 0.98), rgba(34, 84, 173, 0.98));
    border-color: rgba(106, 169, 255, 0.62);
}

.btn-filter-browse:disabled {
    cursor: default;
    opacity: 0.6;
}

.provider-body input[type="text"],
.provider-body input[type="url"],
.provider-body input[type="number"],
.provider-body input[type="password"],
.provider-body select,
.provider-body textarea {
    flex: 1;
    width: 100%;
    background-color: rgba(26, 26, 26, 0.8);
    color: #e9efff;
    border: 1px solid #3a3a3a;
    border-radius: 6px;
    padding: 8px 10px;
}

.provider-body textarea {
    min-height: 145px;
    resize: vertical;
}

.provider-body input:focus,
.provider-body select:focus,
.provider-body textarea:focus {
    border-color: rgba(242, 124, 17, 0.5);
    outline: none;
    box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
}

.provider-toggle {
    margin-left: auto;
    display: flex;
    align-items: center;
    flex: 0 0 auto;
}

.provider-toggle input[type="checkbox"] {
    accent-color: #176529;
    transform: scale(1.6);
    transform-origin: center;
    cursor: pointer;
}

/* On/Off switch for a global connector. Its select stays editable while switched off. */
.connector-availability {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 0 0 auto;
}

.connector-availability-label {
    display: inline-flex;
    align-items: center;
    margin: 0;
    cursor: pointer;
}

.connector-availability-state {
    min-width: 34px;
    padding: 2px 9px;
    border: 1px solid rgba(23, 101, 41, 0.8);
    border-radius: 999px;
    background: rgba(23, 101, 41, 0.28);
    color: #a5e2b3;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.05em;
    line-height: 1.55;
    text-align: center;
    text-transform: uppercase;
}

.connector-availability.is-off .connector-availability-state {
    border-color: #4d4d4d;
    background: rgba(70, 70, 70, 0.35);
    color: #b8b8b8;
}

.connector-availability-input {
    accent-color: #176529;
    transform: scale(1.6);
    transform-origin: center;
    margin: 0 4px;
    flex: 0 0 auto;
    cursor: pointer;
}

.connector-availability-input:focus-visible {
    outline: 2px solid rgba(242, 124, 17, 0.85);
    outline-offset: 4px;
}

.connector-availability-sr {
    position: absolute;
    width: 1px;
    height: 1px;
    margin: -1px;
    padding: 0;
    border: 0;
    overflow: hidden;
    white-space: nowrap;
    clip-path: inset(50%);
}

.provider-help {
    margin-top: 0;
    color: #bbb;
    font-size: 12px;
    line-height: 1.45;
    min-width: 0;
}

.provider-help a {
    color: #8fb8ff;
    font-weight: 700;
    text-decoration: none;
}

.provider-help a:hover {
    color: #bdd4ff;
    text-decoration: underline;
}

.prompt-context-wrap {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

.prompt-context-group {
    border: 1px solid #3a3a3a;
    border-radius: 8px;
    background: linear-gradient(135deg, rgba(28, 28, 28, 0.92), rgba(22, 22, 22, 0.95));
    padding: 12px;
}

.prompt-context-group h3 {
    margin: 0 0 10px 0;
    color: #e6e6e6;
    font-size: 15px;
}

.prompt-context-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));
    gap: 10px;
}

.prompt-context-card {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 10px 12px;
    align-items: start;
    background: rgba(36, 36, 36, 0.9);
    border: 1px solid #3a3a3a;
    border-radius: 8px;
    padding: 10px 12px;
}

.prompt-context-card input[type="checkbox"] {
    margin-top: 2px;
    accent-color: #176529;
    transform: scale(1.15);
}

.prompt-context-label {
    color: #e8edf7;
    font-weight: 700;
    margin-bottom: 4px;
    word-break: break-word;
}

.prompt-context-desc {
    color: #b7c2d2;
    font-size: 12px;
    line-height: 1.45;
}

.btn-save-green {
    background: linear-gradient(135deg, rgba(32, 122, 74, 0.9), rgba(23, 101, 57, 0.9));
    color: #fff;
    border: 1px solid rgba(72, 187, 120, 0.3);
    border-radius: 8px;
    padding: 10px 20px;
    cursor: pointer;
    font-weight: 700;
    font-size: 14px;
}

.btn-save-green:hover {
    background: linear-gradient(135deg, rgba(42, 142, 94, 0.95), rgba(32, 122, 74, 0.95));
}

.btn-action-blue {
    background: linear-gradient(135deg, rgba(34, 84, 173, 0.95), rgba(24, 62, 130, 0.98));
    color: #e8f1ff;
    border: 1px solid rgba(77, 144, 254, 0.42);
    border-radius: 8px;
    padding: 10px 20px;
    cursor: pointer;
    font-weight: 700;
    font-size: 14px;
}

.btn-action-blue:hover {
    background: linear-gradient(135deg, rgba(46, 101, 198, 0.98), rgba(34, 84, 173, 0.98));
    border-color: rgba(106, 169, 255, 0.62);
}

.btn-settings-transfer {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    height: 36px;
    background: #333;
    color: #fff;
    border: 1px solid #4a4a4a;
    border-radius: 8px;
    padding: 7px 12px;
    cursor: pointer;
    font-weight: 700;
    font-size: 13px;
    text-decoration: none;
}

.btn-settings-transfer:hover:not(:disabled) {
    background: #414141;
    border-color: rgba(242, 124, 17, 0.65);
    color: #fff;
    text-decoration: none;
}

.btn-settings-transfer:disabled {
    opacity: 0.6;
    cursor: wait;
}

.btn-action {
    background: #8b0000;
    border: 1px solid #a52a2a;
    color: #fff;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
}

.btn-action:hover {
    background: #a00000;
}

.result-ok {
    margin-top: 8px;
    padding: 8px 12px;
    border-radius: 6px;
    background: #1a3d1a;
    color: #90EE90;
}

.result-error {
    margin-top: 8px;
    padding: 8px 12px;
    border-radius: 6px;
    background: #3d1a1a;
    color: #ff6b6b;
}

.filter-modal-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 1200;
    background: rgba(0, 0, 0, 0.72);
    padding: 24px;
    overflow-y: auto;
}

.filter-modal-backdrop.is-open {
    display: block;
}

.filter-modal-panel {
    max-width: 900px;
    margin: 0 auto;
    border: 1px solid #3a3a3a;
    border-radius: 10px;
    background: linear-gradient(135deg, rgba(34, 34, 34, 0.98), rgba(24, 24, 24, 0.99));
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.45);
    overflow: hidden;
}

.filter-modal-head {
    padding: 18px 22px 12px;
    border-bottom: 1px solid rgba(242, 124, 17, 0.18);
}

.filter-modal-head h2 {
    margin: 0;
    font-size: 1.15em;
    color: rgb(242,124,17);
    font-family: 'MagicCards', serif;
}

.filter-modal-hint {
    margin-top: 8px;
    color: #c4c4c4;
    font-size: 13px;
    line-height: 1.45;
}

.filter-modal-body {
    padding: 18px 22px;
}

.filter-modal-toolbar {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 12px;
    align-items: center;
    margin-bottom: 14px;
}

.filter-modal-toolbar input[type="search"] {
    width: 100%;
    background-color: rgba(26, 26, 26, 0.88);
    color: #e9efff;
    border: 1px solid #3a3a3a;
    border-radius: 6px;
    padding: 9px 11px;
}

.filter-modal-toolbar input[type="search"]:focus {
    border-color: rgba(242, 124, 17, 0.5);
    outline: none;
    box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
}

.filter-modal-status {
    color: #afafaf;
    font-size: 12px;
    text-align: right;
    white-space: nowrap;
}

.filter-modal-list {
    display: grid;
    gap: 10px;
    max-height: 420px;
    overflow-y: auto;
    padding-right: 4px;
}

.filter-candidate-item {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 10px;
    align-items: start;
    border: 1px solid #353535;
    border-radius: 8px;
    background: rgba(29, 29, 29, 0.95);
    padding: 10px 12px;
}

.filter-candidate-item input[type="checkbox"] {
    margin-top: 3px;
    transform: scale(1.1);
    accent-color: #176529;
}

.filter-candidate-main {
    min-width: 0;
}

.filter-candidate-top {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.filter-candidate-value {
    color: #f1f4ff;
    font-weight: 700;
    word-break: break-word;
}

.filter-candidate-count,
.filter-candidate-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: 700;
}

.filter-candidate-count {
    background: rgba(24, 89, 50, 0.85);
    color: #d8ffe9;
}

.filter-candidate-badge {
    background: rgba(113, 83, 26, 0.8);
    color: #f7dfae;
}

.filter-candidate-sample {
    margin-top: 6px;
    color: #b7b7b7;
    font-size: 12px;
    line-height: 1.4;
    word-break: break-word;
}

.filter-modal-empty,
.filter-modal-loading,
.filter-modal-error {
    padding: 18px 14px;
    text-align: center;
    border: 1px dashed #444;
    border-radius: 8px;
    color: #b9b9b9;
    background: rgba(26, 26, 26, 0.7);
}

.filter-modal-error {
    color: #ff9a9a;
    border-color: rgba(160, 52, 52, 0.5);
}

.filter-modal-foot {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 14px 22px 18px;
    border-top: 1px solid rgba(242, 124, 17, 0.12);
}

.filter-modal-note {
    color: #b2b2b2;
    font-size: 12px;
    line-height: 1.4;
}

.filter-modal-actions {
    display: flex;
    gap: 10px;
}

.filter-modal-close {
    border: 1px solid #444;
    border-radius: 6px;
    background: rgba(42, 42, 42, 0.95);
    color: #ececec;
    font-weight: 700;
    padding: 9px 14px;
    cursor: pointer;
}

.filter-modal-close:hover {
    background: rgba(56, 56, 56, 0.98);
}

.global-test-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 1300;
    background: rgba(0, 0, 0, 0.74);
    align-items: center;
    justify-content: center;
    padding: 24px;
}

.global-test-shell {
    width: min(960px, 96vw);
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    background: #1e1e1e;
    border: 1px solid #4a4a4a;
    border-radius: 14px;
    color: #e9efff;
    box-shadow: 0 18px 48px rgba(0, 0, 0, 0.45);
}

.global-test-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 18px 20px;
    border-bottom: 1px solid #343434;
}

.global-test-title {
    font-size: 18px;
    font-weight: 800;
    color: rgb(242, 124, 17);
}

.global-test-subtitle {
    color: #9fb1c9;
    font-size: 13px;
    margin-top: 4px;
}

.global-test-body {
    overflow: auto;
    padding: 16px 20px 20px;
}

.global-test-summary {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 8px;
    margin-bottom: 12px;
}

.global-test-card {
    background: #262626;
    border: 1px solid #393939;
    border-radius: 10px;
    padding: 10px;
}

.global-test-card .num {
    font-size: 20px;
    font-weight: 800;
    color: #fff;
}

.global-test-card .lbl {
    color: #9fb1c9;
    font-size: 12px;
    margin-top: 2px;
}

.global-test-progress {
    height: 8px;
    background: #2e2e2e;
    border-radius: 99px;
    overflow: hidden;
    border: 1px solid #3c3c3c;
    margin-bottom: 14px;
}

.global-test-progress > div {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, rgb(242, 124, 17), #ffd089);
    transition: width 0.2s ease;
}

.global-test-group {
    border: 1px solid #383838;
    border-radius: 10px;
    background: #242424;
    overflow: hidden;
}

.global-test-group-title {
    padding: 10px 12px;
    background: #2a2a2a;
    border-bottom: 1px solid #383838;
    font-weight: 700;
}

.global-test-slots {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    padding: 10px;
}

.global-test-slot {
    display: grid;
    grid-template-columns: 150px 78px 1fr;
    gap: 8px;
    align-items: start;
    border: 1px solid #363636;
    background: #202020;
    border-radius: 8px;
    padding: 8px;
    font-size: 12px;
}

.global-test-slot .slot-name {
    color: #f0f4ff;
    font-weight: 700;
}

.global-test-badge {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    border-radius: 999px;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    border: 1px solid transparent;
}

.global-test-badge.pending { color: #d7dfef; border-color: #626262; background: #333; }
.global-test-badge.pass { color: #bdf4cb; border-color: #2f8050; background: #16351f; }
.global-test-badge.warn { color: #ffe2a3; border-color: #9c6a18; background: #3f2c0d; }
.global-test-badge.fail { color: #ffb6b6; border-color: #923232; background: #421616; }
.global-test-badge.skipped { color: #9fb1c9; border-color: #465164; background: #252b35; }

.global-test-message {
    color: #cfd9ea;
    overflow-wrap: anywhere;
}

.global-test-detail {
    color: #8390a6;
    margin-top: 3px;
    overflow-wrap: anywhere;
}

.global-test-close {
    background: #303030;
    color: #e9efff;
    border: 1px solid #555;
    border-radius: 8px;
    padding: 8px 12px;
    cursor: pointer;
}

/* Settings Presets: preset- prefixed so the header row stays merge-friendly. */
.preset-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid #3a3a3a;
}

.preset-label {
    color: #cfd9ea;
    font-size: 13px;
    font-weight: 700;
}

.preset-select {
    flex: 0 1 auto;
    min-width: 220px;
    max-width: 340px;
    height: 36px;
    padding: 6px 10px;
    background-color: rgba(26, 26, 26, 0.8);
    color: #e9efff;
    border: 1px solid #3a3a3a;
    border-radius: 8px;
    font-size: 13px;
}

.preset-select:focus {
    border-color: rgba(242, 124, 17, 0.5);
    outline: none;
    box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
}

.preset-select:disabled,
.preset-row button:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.preset-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.preset-btn-compact {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    height: 36px;
    padding: 7px 12px;
    border-radius: 8px;
    font-size: 13px;
}

.preset-status {
    flex: 1 1 180px;
    min-width: 0;
    color: #9fb1c9;
    font-size: 12px;
    text-align: right;
    overflow-wrap: anywhere;
}

.preset-status.is-error {
    color: #ff9ca4;
}

.preset-desc {
    flex: 1 1 100%;
    color: #8d9cb2;
    font-size: 12px;
    overflow-wrap: anywhere;
}

.preset-dialog-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 1300;
    background: rgba(0, 0, 0, 0.72);
    padding: 24px;
    overflow-y: auto;
}

.preset-dialog-backdrop.is-open {
    display: flex;
    align-items: flex-start;
    justify-content: center;
}

.preset-dialog {
    width: 100%;
    max-width: 480px;
    border: 1px solid #3a3a3a;
    border-radius: 10px;
    background: #222;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.45);
    padding: 16px;
}

.preset-dialog h2 {
    margin: 0 0 8px;
    font-family: 'MagicCards', serif;
    word-spacing: 7px;
    font-size: 1.1em;
    color: rgb(242, 124, 17);
}

.preset-dialog p {
    margin: 0;
    color: #cfd9ea;
    font-size: 13px;
    line-height: 1.45;
}

.preset-dialog-field {
    display: grid;
    gap: 6px;
    margin-top: 12px;
}

.preset-dialog-field label {
    color: #cfd9ea;
    font-size: 12px;
    font-weight: 700;
}

.preset-dialog-field input {
    width: 100%;
    padding: 8px 10px;
    background-color: rgba(26, 26, 26, 0.8);
    color: #e9efff;
    border: 1px solid #3a3a3a;
    border-radius: 6px;
}

.preset-dialog-field input:focus {
    border-color: rgba(242, 124, 17, 0.5);
    outline: none;
    box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
}

.preset-dialog .result-error {
    font-size: 13px;
}

.preset-dialog-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 16px;
}

@media (max-width: 1000px) {
    .provider-card {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 900px) {
    .settings-tabs,
    .connector-section .provider-grid {
        grid-template-columns: 1fr;
    }

    main {
        padding-left: 5%;
        padding-right: 5%;
    }

    .page-header-row {
        align-items: flex-start;
    }

    .page-header-actions {
        margin-left: 0;
        width: 100%;
    }

    .preset-row {
        flex-direction: column;
        align-items: stretch;
    }

    .preset-select {
        width: 100%;
        max-width: none;
    }

    .preset-actions {
        width: 100%;
    }

    .preset-actions > button {
        flex: 1 1 auto;
    }

    .preset-status {
        flex: 0 0 auto;
        width: 100%;
        text-align: left;
    }

    .preset-desc {
        flex: 0 0 auto;
        width: 100%;
    }

    .preset-dialog-actions {
        flex-direction: column-reverse;
    }

    .preset-dialog-actions > button {
        width: 100%;
    }

    .filter-browse-wrap {
        flex-direction: column;
    }

    .btn-filter-browse {
        width: 100%;
    }

    .filter-modal-toolbar,
    .filter-modal-foot {
        grid-template-columns: 1fr;
        flex-direction: column;
        align-items: stretch;
    }

    .filter-modal-actions {
        width: 100%;
    }

    .filter-modal-actions button {
        flex: 1 1 auto;
    }

    .global-test-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .global-test-slots {
        grid-template-columns: 1fr;
    }

    .global-test-slot {
        grid-template-columns: 1fr;
    }
}
</style>

<main>
    <div class="page-header">
        <div class="page-header-row">
            <h1 class="gs-title">Global Settings</h1>
            <div class="page-header-actions">
                <a id="export_global_settings_link" class="btn-settings-transfer" href="<?php echo $webRoot; ?>/ui/cmd/settings_portability.php?scope=global&amp;action=export">&#128228; Export Settings</a>
                <button type="button" id="import_global_settings_btn" class="btn-settings-transfer">&#128229; Import Settings</button>
                <input type="file" id="import_global_settings_file" accept="application/json,.json" hidden>
                <button type="button" id="global_connector_test_btn" class="btn-action-blue">Test Global Connectors</button>
                <button type="submit" class="btn-save-green" name="save_all" value="1" form="gs_form">Save All</button>
            </div>
        </div>
        <div class="preset-row">
            <label class="preset-label" for="preset-select">Settings Preset</label>
            <select id="preset-select" class="preset-select" aria-describedby="preset-desc preset-status" disabled>
                <option value="">Loading presets&hellip;</option>
            </select>
            <div class="preset-actions">
                <button type="button" id="preset-apply-btn" class="btn-action-blue preset-btn-compact" disabled>Apply</button>
                <button type="button" id="preset-save-new-btn" class="btn-settings-transfer" disabled>Save as new&hellip;</button>
                <button type="button" id="preset-overwrite-btn" class="btn-settings-transfer" disabled>Overwrite&hellip;</button>
            </div>
            <div id="preset-status" class="preset-status" role="status" aria-live="polite">Loading presets&hellip;</div>
            <div id="preset-desc" class="preset-desc"></div>
        </div>
    </div>

    <?php if ($saveSuccess): ?>
        <div class="result-ok" style="margin-bottom: 16px;">Global settings saved to the database.</div>
    <?php endif; ?>

    <div class="settings-tabs" role="tablist" aria-label="Global settings categories">
        <?php foreach ($settingsTabs as $tabId => $tabLabel): ?>
            <button
                type="button"
                class="settings-tab<?php echo $tabId === 'prompt-rechat' ? ' is-active' : ''; ?>"
                id="settings-tab-<?php echo htmlspecialchars($tabId); ?>"
                role="tab"
                aria-selected="<?php echo $tabId === 'prompt-rechat' ? 'true' : 'false'; ?>"
                aria-controls="<?php echo htmlspecialchars($tabControlPanels[$tabId]); ?>"
                data-settings-tab="<?php echo htmlspecialchars($tabId); ?>"
            ><?php echo htmlspecialchars($tabLabel); ?></button>
        <?php endforeach; ?>
    </div>

    <form method="post" action="" id="gs_form">
        <div class="content-grid">
            <?php foreach ($gsSections as $sectionTitle => $fields): ?>
                <?php
                $sectionTab = $sectionTabs[$sectionTitle] ?? 'general';
                $isInitialTab = $sectionTab === 'prompt-rechat';
                $sectionClasses = 'content-section' . ($sectionTitle === 'Global Connectors' ? ' connector-section' : '');
                ?>
                <div
                    class="<?php echo htmlspecialchars($sectionClasses); ?>"
                    id="settings-panel-<?php echo htmlspecialchars($sectionTab); ?>-<?php echo htmlspecialchars(preg_replace('/[^a-z0-9]+/i', '-', strtolower($sectionTitle))); ?>"
                    role="tabpanel"
                    aria-labelledby="settings-tab-<?php echo htmlspecialchars($sectionTab); ?>"
                    data-settings-panel="<?php echo htmlspecialchars($sectionTab); ?>"
                    <?php echo $isInitialTab ? '' : 'hidden'; ?>
                >
                    <h2><?php echo htmlspecialchars($sectionTitle); ?></h2>
                    <div class="provider-grid">
                        <?php if ($sectionTitle === $promptContextSectionTitle): ?>
                            <div class="prompt-context-wrap">
                                <?php foreach ($promptContextCatalog as $bucket => $options): ?>
                                    <div class="prompt-context-group">
                                        <h3><?php echo htmlspecialchars(prompt_context_bucket_title($bucket)); ?></h3>
                                        <div class="prompt-context-grid">
                                            <?php foreach ($options as $optionId => $meta): ?>
                                                <?php
                                                $checked = in_array($optionId, $currentPromptContextOptions[$bucket] ?? [], true);
                                                $inputName = 'prompt_context_' . $bucket . '[]';
                                                ?>
                                                <label class="prompt-context-card">
                                                    <input type="checkbox" name="<?php echo htmlspecialchars($inputName); ?>" value="<?php echo htmlspecialchars($optionId); ?>" <?php echo $checked ? 'checked' : ''; ?>>
                                                    <span>
                                                        <div class="prompt-context-label"><?php echo htmlspecialchars($meta['label'] ?? $optionId); ?></div>
                                                        <div class="prompt-context-desc"><?php echo htmlspecialchars($meta['description'] ?? ''); ?></div>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="prompt-context-group">
                                    <h3>Context Options</h3>
                                    <div class="provider-grid">
                                        <?php foreach ($fields as $field): ?>
                                            <?php
                                            $fieldName = $field['name'];
                                            $fieldType = strval($field['type']);
                                            $current = current_value($fieldName);
                                            $label = pretty_label($fieldName);
                                            $help = current_description($fieldName, $generalSettingRowMap);
                                            $schemaDefinition = chimGetSchemaDefinition($fieldName);
                                            $isReadonly = isset($schemaDefinition['readonly']) && $schemaDefinition['readonly'] === true;
                                            $readonlyAttr = $isReadonly ? 'readonly' : '';
                                            ?>
                                            <div class="provider-card<?php echo isset($filterBrowseFieldConfigs[$fieldName]) ? ' filter-setting-card' : ''; ?>">
                                                <div class="provider-head">
                                                    <div class="provider-title">
                                                        <div class="provider-icon"><?php echo icon_for_field($fieldName); ?></div>
                                                        <div><?php echo htmlspecialchars($label); ?></div>
                                                        <?php if ($fieldType === 'boolean'): ?>
                                                            <div class="provider-toggle">
                                                                <input type="hidden" name="<?php echo htmlspecialchars($fieldName); ?>" value="false">
                                                                <input type="checkbox" name="<?php echo htmlspecialchars($fieldName); ?>" value="true" <?php echo ($current ? 'checked' : ''); ?> <?php echo $isReadonly ? 'disabled' : ''; ?>>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="provider-body">
                                                    <?php if ($fieldType === 'longstring'): ?>
                                                        <?php if (isset($filterBrowseFieldConfigs[$fieldName])): ?>
                                                            <?php $browseConfig = $filterBrowseFieldConfigs[$fieldName]; ?>
                                                            <div class="filter-browse-wrap">
                                                                <textarea name="<?php echo htmlspecialchars($fieldName); ?>" rows="4" <?php echo $readonlyAttr; ?>><?php echo htmlspecialchars(strval($current)); ?></textarea>
                                                                <?php if (!$isReadonly): ?>
                                                                    <button
                                                                        type="button"
                                                                        class="btn-filter-browse js-filter-browse"
                                                                        data-field="<?php echo htmlspecialchars($fieldName); ?>"
                                                                    >
                                                                        <?php echo htmlspecialchars(strval($browseConfig['button_label'] ?? 'Recent...')); ?>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <textarea name="<?php echo htmlspecialchars($fieldName); ?>" rows="4" <?php echo $readonlyAttr; ?>><?php echo htmlspecialchars(strval($current)); ?></textarea>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($help)): ?>
                                                    <p class="provider-help"><?php echo render_provider_help($fieldName, $help, $webRoot); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            </div>
                            </div>
                            <?php continue; ?>
                        <?php endif; ?>
                        <?php $lastSubsection = null; ?>
                        <?php foreach ($fields as $field): ?>
                            <?php
                            $fieldName = $field['name'];
                            if ($fieldName === 'PLAYER_NAME') {
                                continue;
                            }

                            $subsection = isset($field['subsection']) ? strval($field['subsection']) : null;
                            if ($subsection !== $lastSubsection) {
                                if (!empty($subsection)) {
                                    echo '<div class="provider-subsection-title">' . htmlspecialchars($subsection) . '</div>';
                                }
                                $lastSubsection = $subsection;
                            }

                            $fieldType = strval($field['type']);
                            $current = current_value($fieldName);
                            if (($current === '' || $current === null) && isset($field['default'])) {
                                $current = $field['default'];
                            }
                            $label = pretty_label($fieldName);
                            $help = current_description($fieldName, $generalSettingRowMap);
                            if ($help === '' && isset($field['help'])) {
                                $help = strval($field['help']);
                            }
                            $schemaDefinition = chimGetSchemaDefinition($fieldName);
                            $isReadonly = isset($schemaDefinition['readonly']) && $schemaDefinition['readonly'] === true;
                            $readonlyAttr = $isReadonly ? 'readonly' : '';
                            ?>
                            <div class="provider-card<?php echo isset($filterBrowseFieldConfigs[$fieldName]) ? ' filter-setting-card' : ''; ?>">
                                <div class="provider-head">
                                    <div class="provider-title">
                                        <div class="provider-icon"><?php echo icon_for_field($fieldName); ?></div>
                                        <div><?php echo htmlspecialchars($label); ?></div>
                                        <?php if ($fieldType === 'boolean'): ?>
                                            <div class="provider-toggle">
                                                <input type="hidden" name="<?php echo htmlspecialchars($fieldName); ?>" value="false">
                                                <input type="checkbox" name="<?php echo htmlspecialchars($fieldName); ?>" value="true" <?php echo ($current ? 'checked' : ''); ?> <?php echo $isReadonly ? 'disabled' : ''; ?>>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($connectorAvailabilityToggles[$fieldName])): ?>
                                            <?php echo render_connector_availability_switch($fieldName, $connectorAvailabilityToggles[$fieldName]); ?>
                                        <?php endif; ?>
                                        <?php if ($fieldName === 'CORE_CONNECTOR_OGHMA_CUSTOM'): ?>
                                            <div class="provider-toggle">
                                                <input type="hidden" name="OGHMA_CUSTOM" value="false">
                                                <input type="checkbox" name="OGHMA_CUSTOM" value="true" <?php echo (current_value('OGHMA_CUSTOM') ? 'checked' : ''); ?> title="Enable/Disable Custom Oghma LLM">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="provider-body">
                                    <?php if ($fieldType === 'boolean'): ?>
                                        <?php if (($field['action'] ?? '') === 'clear_reanimation'): ?>
                                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                                <button type="submit" name="clear_reanimation_status" value="1" class="btn-action" onclick="return confirm('This will remove the reanimated/zombie status from ALL NPCs in the database. Continue?');">🧟 Clear Reanimation Status</button>
                                                <span style="color:#888; font-size:12px;">Removes zombie flags from all NPCs</span>
                                            </div>
                                            <?php if ($clearReanimationResult !== null): ?>
                                                <div class="<?php echo $clearReanimationResult['success'] ? 'result-ok' : 'result-error'; ?>">
                                                    <?php echo htmlspecialchars($clearReanimationResult['message']); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php elseif ($fieldType === 'integer'): ?>
                                        <?php $min = isset($field['min']) ? intval($field['min']) : null; $max = isset($field['max']) ? intval($field['max']) : null; ?>
                                        <input type="number" name="<?php echo htmlspecialchars($fieldName); ?>" value="<?php echo htmlspecialchars(strval($current)); ?>" <?php echo ($min !== null ? 'min="' . $min . '"' : ''); ?> <?php echo ($max !== null ? 'max="' . $max . '"' : ''); ?> step="1" <?php echo $readonlyAttr; ?>>
                                    <?php elseif ($fieldType === 'number'): ?>
                                        <?php
                                        $min = isset($field['min']) ? floatval($field['min']) : null;
                                        $max = isset($field['max']) ? floatval($field['max']) : null;
                                        $step = isset($field['step']) ? floatval($field['step']) : 0.01;
                                        ?>
                                        <input type="number" step="<?php echo htmlspecialchars(strval($step)); ?>" name="<?php echo htmlspecialchars($fieldName); ?>" value="<?php echo htmlspecialchars(strval($current)); ?>" <?php echo ($min !== null ? 'min="' . $min . '"' : ''); ?> <?php echo ($max !== null ? 'max="' . $max . '"' : ''); ?> <?php echo $readonlyAttr; ?>>
                                    <?php elseif ($fieldType === 'longstring'): ?>
                                        <?php if (isset($filterBrowseFieldConfigs[$fieldName])): ?>
                                            <?php $browseConfig = $filterBrowseFieldConfigs[$fieldName]; ?>
                                            <div class="filter-browse-wrap">
                                                <textarea name="<?php echo htmlspecialchars($fieldName); ?>" rows="4" <?php echo $readonlyAttr; ?>><?php echo htmlspecialchars(strval($current)); ?></textarea>
                                                <?php if (!$isReadonly): ?>
                                                    <button
                                                        type="button"
                                                        class="btn-filter-browse js-filter-browse"
                                                        data-field="<?php echo htmlspecialchars($fieldName); ?>"
                                                    >
                                                        <?php echo htmlspecialchars(strval($browseConfig['button_label'] ?? 'Recent...')); ?>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <textarea name="<?php echo htmlspecialchars($fieldName); ?>" rows="4" <?php echo $readonlyAttr; ?>><?php echo htmlspecialchars(strval($current)); ?></textarea>
                                        <?php endif; ?>
                                    <?php elseif ($fieldType === 'url'): ?>
                                        <input type="url" name="<?php echo htmlspecialchars($fieldName); ?>" value="<?php echo htmlspecialchars(strval($current)); ?>" <?php echo $readonlyAttr; ?>>
                                    <?php elseif ($fieldType === 'apikey'): ?>
                                        <input type="password" name="<?php echo htmlspecialchars($fieldName); ?>" value="<?php echo htmlspecialchars(strval($current)); ?>" placeholder="Paste API key" <?php echo $readonlyAttr; ?>>
                                    <?php elseif ($fieldType === 'select'): ?>
                                        <select name="<?php echo htmlspecialchars($fieldName); ?>" <?php echo $isReadonly ? 'disabled' : ''; ?>>
                                            <?php foreach (($field['values'] ?? []) as $option): ?>
                                                <option value="<?php echo htmlspecialchars(strval($option)); ?>" <?php echo (strval($current) === strval($option) ? 'selected' : ''); ?>><?php echo htmlspecialchars(select_option_label($fieldName, strval($option))); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif (strpos($fieldType, 'foreign:') === 0): ?>
                                        <?php $parts = explode(':', $fieldType); $fkKey = implode(':', array_slice($parts, 1)); $rows = $foreignOptions[$fkKey] ?? []; ?>
                                        <select name="<?php echo htmlspecialchars($fieldName); ?>" <?php echo $isReadonly ? 'disabled' : ''; ?>>
                                            <option value="" <?php echo (empty($current) ? 'selected' : ''); ?>>None</option>
                                            <?php foreach ($rows as $row): ?>
                                                <option value="<?php echo htmlspecialchars(strval($row[$parts[2]] ?? '')); ?>" <?php echo (strval($current) === strval($row[$parts[2]] ?? '') ? 'selected' : ''); ?>>
                                                    <?php echo htmlspecialchars(strval($row[$parts[3]] ?? '')); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <input type="text" name="<?php echo htmlspecialchars($fieldName); ?>" value="<?php echo htmlspecialchars(strval($current)); ?>" <?php echo $readonlyAttr; ?>>
                                    <?php endif; ?>
                                </div>
                                <?php if ($help !== ''): ?>
                                    <div class="provider-help"><?php echo render_provider_help($fieldName, $help, $webRoot); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </form>

    <script>
    (() => {
        const storageKey = 'herika-global-settings-tab';
        const tabs = Array.from(document.querySelectorAll('[data-settings-tab]'));
        const panels = Array.from(document.querySelectorAll('[data-settings-panel]'));
        const form = document.getElementById('gs_form');
        const validTabs = new Set(tabs.map((tab) => tab.dataset.settingsTab));

        function activateTab(tabId, focusTab = false) {
            if (!validTabs.has(tabId)) {
                tabId = 'prompt-rechat';
            }

            tabs.forEach((tab) => {
                const active = tab.dataset.settingsTab === tabId;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
                tab.tabIndex = active ? 0 : -1;
                if (active && focusTab) {
                    tab.focus();
                }
            });

            panels.forEach((panel) => {
                panel.hidden = panel.dataset.settingsPanel !== tabId;
            });

            try {
                sessionStorage.setItem(storageKey, tabId);
            } catch (error) {
                // Storage can be unavailable in privacy-restricted browsers.
            }
        }

        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => activateTab(tab.dataset.settingsTab));
            tab.addEventListener('keydown', (event) => {
                let nextIndex = null;
                if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                    nextIndex = (index + 1) % tabs.length;
                } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                    nextIndex = (index - 1 + tabs.length) % tabs.length;
                } else if (event.key === 'Home') {
                    nextIndex = 0;
                } else if (event.key === 'End') {
                    nextIndex = tabs.length - 1;
                }

                if (nextIndex !== null) {
                    event.preventDefault();
                    activateTab(tabs[nextIndex].dataset.settingsTab, true);
                }
            });
        });

        form?.addEventListener('invalid', (event) => {
            const panel = event.target.closest('[data-settings-panel]');
            if (panel) {
                activateTab(panel.dataset.settingsPanel);
            }
        }, true);

        let initialTab = 'prompt-rechat';
        try {
            initialTab = sessionStorage.getItem(storageKey) || initialTab;
        } catch (error) {
            // Keep the default tab when storage is unavailable.
        }
        activateTab(initialTab);
    })();
    </script>

    <script>
    (() => {
        // Keep the visible On/Off text in step with each connector availability checkbox.
        document.querySelectorAll('.connector-availability').forEach((wrap) => {
            const input = wrap.querySelector('.connector-availability-input');
            const state = wrap.querySelector('.connector-availability-state');
            if (!input || !state) {
                return;
            }

            const sync = () => {
                wrap.classList.toggle('is-off', !input.checked);
                state.textContent = input.checked ? 'On' : 'Off';
            };

            input.addEventListener('change', sync);
            sync();
        });
    })();
    </script>

    <div id="global-connector-test-modal" class="global-test-modal" aria-hidden="true">
        <div class="global-test-shell" role="dialog" aria-modal="true" aria-labelledby="global-connector-test-title">
            <div class="global-test-head">
                <div>
                    <div id="global-connector-test-title" class="global-test-title">Test Global Connectors</div>
                    <div class="global-test-subtitle">Testing enabled global connector slots once, then applying shared connector results to every matching slot.</div>
                </div>
                <button type="button" id="global-connector-test-close" class="global-test-close">Close</button>
            </div>
            <div class="global-test-body">
                <div id="global-connector-test-summary" class="global-test-summary"></div>
                <div class="global-test-progress"><div id="global-connector-test-progress-fill"></div></div>
                <div id="global-connector-test-results"></div>
            </div>
        </div>
    </div>

    <div id="preset-dialog-backdrop" class="preset-dialog-backdrop" aria-hidden="true">
        <div class="preset-dialog" role="dialog" aria-modal="true" aria-labelledby="preset-dialog-title" aria-describedby="preset-dialog-body">
            <h2 id="preset-dialog-title">Settings Preset</h2>
            <p id="preset-dialog-body"></p>
            <div class="preset-dialog-field" id="preset-dialog-name-wrap" hidden>
                <label for="preset-dialog-name">Preset name</label>
                <input type="text" id="preset-dialog-name" maxlength="60" autocomplete="off" spellcheck="false">
            </div>
            <div class="result-error" id="preset-dialog-error" hidden></div>
            <div class="preset-dialog-actions">
                <button type="button" id="preset-dialog-cancel" class="filter-modal-close">Cancel</button>
                <button type="button" id="preset-dialog-confirm" class="btn-save-green preset-btn-compact">Confirm</button>
            </div>
        </div>
    </div>

    <div id="filterBrowseModal" class="filter-modal-backdrop" aria-hidden="true">
        <div class="filter-modal-panel" role="dialog" aria-modal="true" aria-labelledby="filterBrowseModalTitle">
            <div class="filter-modal-head">
                <h2 id="filterBrowseModalTitle">Recent Values</h2>
                <div id="filterBrowseModalHint" class="filter-modal-hint"></div>
            </div>
            <div class="filter-modal-body">
                <div class="filter-modal-toolbar">
                    <input type="search" id="filterBrowseSearch" placeholder="Search recent values">
                    <div id="filterBrowseStatus" class="filter-modal-status"></div>
                </div>
                <div id="filterBrowseFeedback" class="filter-modal-loading">Loading recent values...</div>
                <div id="filterBrowseList" class="filter-modal-list" hidden></div>
            </div>
            <div class="filter-modal-foot">
                <div class="filter-modal-note">Checked values stay in the textbox. Uncheck a value here, then save, to remove it from the filter.</div>
                <div class="filter-modal-actions">
                    <button type="button" class="filter-modal-close" id="filterBrowseCancel">Cancel</button>
                    <button type="button" class="btn-save-green" id="filterBrowseSave">Save Selection</button>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="<?php echo $webRoot; ?>/ui/js/settings-portability.js?v=<?php echo (int) @filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'settings-portability.js'); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.chimInitSettingsImport === 'function') {
        window.chimInitSettingsImport({
            scope: 'global',
            endpoint: <?php echo json_encode($webRoot . '/ui/cmd/settings_portability.php'); ?>,
            importButtonId: 'import_global_settings_btn',
            fileInputId: 'import_global_settings_file'
        });
    }
});
</script>

<script>
(() => {
    const apiUrl = <?php echo json_encode($webRoot . '/ui/api/profile_connector_tests.php', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const modal = document.getElementById('global-connector-test-modal');
    const openBtn = document.getElementById('global_connector_test_btn');
    const closeBtn = document.getElementById('global-connector-test-close');
    const summaryEl = document.getElementById('global-connector-test-summary');
    const resultsEl = document.getElementById('global-connector-test-results');
    const progressFill = document.getElementById('global-connector-test-progress-fill');
    let cancelled = false;

    if (!modal || !openBtn || !closeBtn || !summaryEl || !resultsEl || !progressFill) {
        return;
    }

    function esc(value) {
        return (value == null ? '' : String(value)).replace(/[&<>"']/g, c => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[c]));
    }

    function attr(value) {
        return esc(value).replace(/`/g, '&#096;');
    }

    function statusLabel(status) {
        const labels = { pass: 'Pass', warn: 'Warn', fail: 'Fail', skipped: 'Skipped', pending: 'Pending' };
        return labels[String(status || '').toLowerCase()] || 'Pending';
    }

    function renderSummary() {
        const counts = { pass: 0, warn: 0, fail: 0, skipped: 0, pending: 0 };
        document.querySelectorAll('#global-connector-test-results .global-test-slot').forEach(slot => {
            const status = String(slot.getAttribute('data-status') || 'pending').toLowerCase();
            if (Object.prototype.hasOwnProperty.call(counts, status)) counts[status]++;
            else counts.pending++;
        });

        summaryEl.innerHTML = [
            ['pass', 'Passed'],
            ['warn', 'Warnings'],
            ['fail', 'Failed'],
            ['skipped', 'Skipped'],
            ['pending', 'Pending']
        ].map(([key, label]) => `
            <div class="global-test-card">
                <div class="num">${counts[key]}</div>
                <div class="lbl">${label}</div>
            </div>
        `).join('');
    }

    function setProgress(done, total) {
        const safeTotal = Math.max(1, Number(total || 0));
        const pct = Math.max(0, Math.min(100, Math.round((Number(done || 0) / safeTotal) * 100)));
        progressFill.style.width = pct + '%';
    }

    function renderPlan(plan) {
        const profiles = Array.isArray(plan.profiles) ? plan.profiles : [];
        let html = '';

        profiles.forEach(profile => {
            html += `
                <div class="global-test-group">
                    <div class="global-test-group-title">${esc(profile.label || 'Global Connectors')}</div>
                    <div class="global-test-slots">
            `;
            (profile.slots || []).forEach(slot => {
                const status = slot.status || 'pending';
                html += `
                    <div class="global-test-slot" data-status="${attr(status)}" data-job-key="${attr(slot.job_key || '')}">
                        <div class="slot-name">${esc(slot.label || slot.field || 'Connector')}</div>
                        <div><span class="global-test-badge ${attr(status)}">${statusLabel(status)}</span></div>
                        <div>
                            <div class="global-test-message">${esc(slot.message || '')}</div>
                            <div class="global-test-detail"></div>
                        </div>
                    </div>
                `;
            });
            html += '</div></div>';
        });

        resultsEl.innerHTML = html || '<div class="global-test-card">No global connectors found.</div>';
        renderSummary();
    }

    function detailFromResult(result) {
        const details = result && result.details ? result.details : {};
        const chunks = [];
        if (details.label) chunks.push(details.label);
        if (details.driver) chunks.push('driver: ' + details.driver);
        if (details.model) chunks.push('model: ' + details.model);
        if (details.url) chunks.push('url: ' + details.url);
        if (Number(result.elapsed_ms || 0) > 0) chunks.push(String(result.elapsed_ms) + 'ms');
        if (details.response_preview) chunks.push('response: ' + details.response_preview);
        return chunks.join(' | ');
    }

    function applyJobResult(result) {
        const jobKey = result && result.job_key ? String(result.job_key) : '';
        if (!jobKey) return;

        document.querySelectorAll('#global-connector-test-results .global-test-slot').forEach(slot => {
            if (String(slot.getAttribute('data-job-key') || '') !== jobKey) return;
            const status = String(result.status || 'fail').toLowerCase();
            slot.setAttribute('data-status', status);

            const badge = slot.querySelector('.global-test-badge');
            if (badge) {
                badge.className = 'global-test-badge ' + status;
                badge.textContent = statusLabel(status);
            }

            const message = slot.querySelector('.global-test-message');
            if (message) message.textContent = result.message || '';

            const detail = slot.querySelector('.global-test-detail');
            if (detail) detail.textContent = detailFromResult(result);
        });

        renderSummary();
    }

    async function fetchJson(url) {
        const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
        const text = await response.text();
        let json = null;
        try {
            json = JSON.parse(text);
        } catch (_error) {
            throw new Error('Invalid JSON response: ' + text.slice(0, 160));
        }

        if (!response.ok || !json || json.ok !== true) {
            throw new Error((json && json.error) ? json.error : ('HTTP ' + response.status));
        }

        return json;
    }

    async function testJob(job) {
        const url = apiUrl + '?action=test&type=' + encodeURIComponent(job.type) + '&id=' + encodeURIComponent(job.id) + '&_=' + Date.now();
        try {
            const json = await fetchJson(url);
            return json.result;
        } catch (error) {
            return {
                job_key: String(job.type) + ':' + String(job.id),
                type: job.type,
                id: job.id,
                status: 'fail',
                message: error.message || 'Connector test failed',
                details: {},
                elapsed_ms: 0
            };
        }
    }

    async function runJobs(jobs) {
        let completed = 0;
        const total = jobs.length;
        setProgress(0, total);
        const queue = jobs.slice();
        const workers = Array.from({ length: Math.min(2, Math.max(1, total)) }, async () => {
            while (!cancelled && queue.length > 0) {
                const job = queue.shift();
                const result = await testJob(job);
                applyJobResult(result);
                completed++;
                setProgress(completed, total);
            }
        });
        await Promise.all(workers);
    }

    async function openGlobalConnectorTestModal() {
        cancelled = false;
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        summaryEl.innerHTML = '';
        resultsEl.innerHTML = '<div class="global-test-card">Building global connector test plan...</div>';
        setProgress(0, 1);

        try {
            const planJson = await fetchJson(apiUrl + '?action=plan&scope=global&_=' + Date.now());
            const plan = planJson.plan || {};
            const jobs = Array.isArray(plan.jobs) ? plan.jobs : [];
            renderPlan(plan);
            if (jobs.length === 0) {
                setProgress(1, 1);
                return;
            }
            await runJobs(jobs);
        } catch (error) {
            resultsEl.innerHTML = '<div class="global-test-card"><span style="color:#ff9898;">' + esc(error.message || 'Failed to run global connector tests') + '</span></div>';
            renderSummary();
            setProgress(1, 1);
        }
    }

    function closeGlobalConnectorTestModal() {
        cancelled = true;
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
    }

    openBtn.addEventListener('click', openGlobalConnectorTestModal);
    closeBtn.addEventListener('click', closeGlobalConnectorTestModal);
    modal.addEventListener('click', event => {
        if (event.target === modal) closeGlobalConnectorTestModal();
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && modal.style.display === 'flex') closeGlobalConnectorTestModal();
    });
})();

const filterBrowseConfigs = <?php echo json_encode($filterBrowseFieldConfigs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
const filterBrowseEndpoint = <?php echo json_encode($webRoot . '/ui/api/filter_candidates.php', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

(function () {
    const modal = document.getElementById('filterBrowseModal');
    const modalTitle = document.getElementById('filterBrowseModalTitle');
    const modalHint = document.getElementById('filterBrowseModalHint');
    const feedback = document.getElementById('filterBrowseFeedback');
    const list = document.getElementById('filterBrowseList');
    const searchInput = document.getElementById('filterBrowseSearch');
    const status = document.getElementById('filterBrowseStatus');
    const saveButton = document.getElementById('filterBrowseSave');
    const cancelButton = document.getElementById('filterBrowseCancel');
    const browseButtons = document.querySelectorAll('.js-filter-browse');

    const state = {
        activeField: '',
        activeTextarea: null,
        candidates: [],
        filteredCandidates: [],
        selectedKeys: new Set()
    };

    function normalizeKey(value) {
        return String(value || '').trim().toLowerCase();
    }

    function parseCsvValues(rawValue) {
        const values = [];
        const seen = new Set();
        String(rawValue || '')
            .split(',')
            .map((part) => part.trim())
            .filter((part) => part.length > 0)
            .forEach((part) => {
                const key = normalizeKey(part);
                if (!seen.has(key)) {
                    seen.add(key);
                    values.push(part);
                }
            });
        return values;
    }

    function mergeCsvValues(currentValue, additions) {
        const merged = [];
        const seen = new Set();

        parseCsvValues(currentValue).forEach((value) => {
            const key = normalizeKey(value);
            if (!seen.has(key)) {
                seen.add(key);
                merged.push(value);
            }
        });

        additions.forEach((value) => {
            const trimmed = String(value || '').trim();
            const key = normalizeKey(trimmed);
            if (trimmed && !seen.has(key)) {
                seen.add(key);
                merged.push(trimmed);
            }
        });

        return merged.join(', ');
    }

    function setFeedback(message, cssClass) {
        feedback.hidden = false;
        feedback.className = cssClass;
        feedback.textContent = message;
        list.hidden = true;
        list.innerHTML = '';
        status.textContent = '';
    }

    function openModal() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        searchInput.value = '';
        state.activeField = '';
        state.activeTextarea = null;
        state.candidates = [];
        state.filteredCandidates = [];
        state.selectedKeys = new Set();
    }

    function applySearchFilter() {
        const query = searchInput.value.trim().toLowerCase();
        if (!query) {
            state.filteredCandidates = state.candidates.slice();
            return;
        }

        state.filteredCandidates = state.candidates.filter((candidate) => {
            const haystack = [candidate.value, candidate.sample || '']
                .join(' ')
                .toLowerCase();
            return haystack.includes(query);
        });
    }

    function renderCandidates() {
        applySearchFilter();
        list.innerHTML = '';

        if (state.filteredCandidates.length === 0) {
            list.hidden = true;
            feedback.hidden = false;
            feedback.className = 'filter-modal-empty';
            feedback.textContent = 'No matching recent values found.';
            status.textContent = '';
            return;
        }

        const fragment = document.createDocumentFragment();
        let selectedCount = 0;

        state.filteredCandidates.forEach((candidate) => {
            const item = document.createElement('label');
            item.className = 'filter-candidate-item';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.value = candidate.value;

            const key = normalizeKey(candidate.value);
            const isSelected = state.selectedKeys.has(key);
            if (isSelected) {
                checkbox.checked = true;
                selectedCount += 1;
            }

            checkbox.addEventListener('change', function () {
                if (checkbox.checked) {
                    state.selectedKeys.add(key);
                } else {
                    state.selectedKeys.delete(key);
                }
                renderCandidates();
            });

            const main = document.createElement('div');
            main.className = 'filter-candidate-main';

            const top = document.createElement('div');
            top.className = 'filter-candidate-top';

            const value = document.createElement('span');
            value.className = 'filter-candidate-value';
            value.textContent = candidate.value;
            top.appendChild(value);

            const count = document.createElement('span');
            count.className = 'filter-candidate-count';
            count.textContent = candidate.count + ' hits';
            top.appendChild(count);

            if (isSelected) {
                const badge = document.createElement('span');
                badge.className = 'filter-candidate-badge';
                badge.textContent = 'Selected';
                top.appendChild(badge);
            }

            main.appendChild(top);

            if (candidate.sample) {
                const sample = document.createElement('div');
                sample.className = 'filter-candidate-sample';
                sample.textContent = candidate.sample;
                main.appendChild(sample);
            }

            item.appendChild(checkbox);
            item.appendChild(main);
            fragment.appendChild(item);
        });

        list.appendChild(fragment);
        feedback.hidden = true;
        list.hidden = false;
        status.textContent = selectedCount + ' selected of ' + state.filteredCandidates.length + ' shown';
    }

    async function loadCandidates(fieldName) {
        setFeedback('Loading recent values...', 'filter-modal-loading');
        saveButton.disabled = true;

        const config = filterBrowseConfigs[fieldName] || {};
        modalTitle.textContent = config.modal_title || 'Recent Values';
        modalHint.textContent = config.modal_hint || '';

        try {
            const response = await fetch(filterBrowseEndpoint + '?field=' + encodeURIComponent(fieldName), {
                cache: 'no-store',
                credentials: 'same-origin'
            });

            const payload = await response.json();
            if (!response.ok || !payload.success) {
                throw new Error(payload.error || 'Failed to load recent values.');
            }

            state.candidates = Array.isArray(payload.data) ? payload.data : [];
            state.selectedKeys = new Set(
                parseCsvValues(state.activeTextarea ? state.activeTextarea.value : '').map((value) => normalizeKey(value))
            );
            renderCandidates();
            saveButton.disabled = false;
        } catch (error) {
            setFeedback(error.message || 'Failed to load recent values.', 'filter-modal-error');
        }
    }

    browseButtons.forEach((button) => {
        button.addEventListener('click', function () {
            const fieldName = button.getAttribute('data-field') || '';
            const card = button.closest('.provider-card');
            const textarea = card ? card.querySelector('textarea[name="' + fieldName + '"]') : null;
            if (!fieldName || !textarea) {
                return;
            }

            state.activeField = fieldName;
            state.activeTextarea = textarea;
            openModal();
            loadCandidates(fieldName);
            setTimeout(() => searchInput.focus(), 0);
        });
    });

    searchInput.addEventListener('input', function () {
        renderCandidates();
    });

    saveButton.addEventListener('click', function () {
        if (!state.activeTextarea) {
            closeModal();
            return;
        }

        const currentValues = parseCsvValues(state.activeTextarea.value);
        const candidateMap = new Map();
        const candidateKeys = new Set();
        const nextValues = [];
        const seen = new Set();

        state.candidates.forEach((candidate) => {
            const key = normalizeKey(candidate.value);
            candidateKeys.add(key);
            candidateMap.set(key, candidate.value);
        });

        currentValues.forEach((value) => {
            const key = normalizeKey(value);
            if (seen.has(key)) {
                return;
            }

            if (candidateKeys.has(key)) {
                if (state.selectedKeys.has(key)) {
                    nextValues.push(value);
                    seen.add(key);
                }
                return;
            }

            nextValues.push(value);
            seen.add(key);
        });

        state.candidates.forEach((candidate) => {
            const key = normalizeKey(candidate.value);
            if (state.selectedKeys.has(key) && !seen.has(key)) {
                nextValues.push(candidateMap.get(key) || candidate.value);
                seen.add(key);
            }
        });

        state.activeTextarea.value = nextValues.join(', ');

        closeModal();
    });

    cancelButton.addEventListener('click', closeModal);

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });
})();
</script>

<script>
(() => {
    'use strict';

    const presetEndpoint = <?php echo json_encode($webRoot . '/ui/api/chim_settings_presets.php', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const presetFlashKey = 'herika-settings-preset-flash';
    const presetAppliedKey = 'herika-settings-preset-applied';

    const presetSelect = document.getElementById('preset-select');
    const presetApplyBtn = document.getElementById('preset-apply-btn');
    const presetSaveNewBtn = document.getElementById('preset-save-new-btn');
    const presetOverwriteBtn = document.getElementById('preset-overwrite-btn');
    const presetStatus = document.getElementById('preset-status');
    const presetDesc = document.getElementById('preset-desc');
    const presetForm = document.getElementById('gs_form');
    const exportLink = document.getElementById('export_global_settings_link');
    const exportBaseHref = exportLink ? exportLink.getAttribute('href') : '';

    const presetDialogBackdrop = document.getElementById('preset-dialog-backdrop');
    const presetDialogTitle = document.getElementById('preset-dialog-title');
    const presetDialogBody = document.getElementById('preset-dialog-body');
    const presetDialogNameWrap = document.getElementById('preset-dialog-name-wrap');
    const presetDialogName = document.getElementById('preset-dialog-name');
    const presetDialogError = document.getElementById('preset-dialog-error');
    const presetDialogCancel = document.getElementById('preset-dialog-cancel');
    const presetDialogConfirm = document.getElementById('preset-dialog-confirm');

    if (!presetSelect || !presetApplyBtn || !presetDialogBackdrop) {
        return;
    }

    let presets = [];
    let barBusy = false;
    let dialogBusy = false;
    let dialogConfig = null;
    let dialogInvoker = null;

    function setStatus(message, isError) {
        presetStatus.textContent = message || '';
        presetStatus.classList.toggle('is-error', !!isError);
    }

    function errorText(error) {
        const message = error && error.message ? String(error.message) : String(error || '');
        return message === '' ? 'Unknown error.' : message;
    }

    function findPreset(id) {
        return presets.find((preset) => preset && String(preset.id) === String(id)) || null;
    }

    function selectedPreset() {
        return findPreset(presetSelect.value);
    }

    function syncBar() {
        const preset = selectedPreset();
        presetSelect.disabled = barBusy || presets.length === 0;
        presetApplyBtn.disabled = barBusy || !preset;
        presetSaveNewBtn.disabled = barBusy;
        presetOverwriteBtn.disabled = barBusy || !preset || preset.built_in === true;
        const description = preset && preset.description ? String(preset.description) : '';
        presetDesc.textContent = description;
        presetDesc.hidden = description === '';
        syncExportLink();
    }

    // Names the export download after the selected preset; the server sanitizes the raw name.
    function syncExportLink() {
        if (!exportLink || exportBaseHref === '') {
            return;
        }
        const preset = selectedPreset();
        const name = preset ? String(preset.name || preset.id || '') : '';
        exportLink.setAttribute(
            'href',
            name === '' ? exportBaseHref : exportBaseHref + '&preset=' + encodeURIComponent(name)
        );
    }

    function setBarBusy(busy) {
        barBusy = !!busy;
        syncBar();
    }

    function renderOptions(preferredId) {
        const previous = preferredId || presetSelect.value;
        presetSelect.replaceChildren();

        if (presets.length === 0) {
            const placeholder = new Option('No presets available', '');
            placeholder.disabled = true;
            presetSelect.appendChild(placeholder);
            syncBar();
            return;
        }

        const builtIns = presets.filter((preset) => preset.built_in === true);
        const customs = presets.filter((preset) => preset.built_in !== true);

        const addGroup = (label, items, emptyLabel) => {
            if (items.length === 0 && !emptyLabel) {
                return;
            }
            const group = document.createElement('optgroup');
            group.label = label;
            if (items.length === 0) {
                const empty = new Option(emptyLabel, '');
                empty.disabled = true;
                group.appendChild(empty);
            } else {
                items.forEach((preset) => {
                    group.appendChild(new Option(String(preset.name || preset.id), String(preset.id)));
                });
            }
            presetSelect.appendChild(group);
        };

        addGroup('Built-in', builtIns, null);
        addGroup('Custom', customs, null);

        if (previous && findPreset(previous)) {
            presetSelect.value = previous;
        } else {
            presetSelect.value = String(presets[0].id);
        }
        syncBar();
    }

    async function presetRequest(body) {
        const options = { cache: 'no-store', credentials: 'same-origin' };
        if (body) {
            options.method = 'POST';
            options.headers = { 'Content-Type': 'application/json' };
            options.body = JSON.stringify(body);
        }
        const response = await fetch(presetEndpoint, options);
        let payload = null;
        try {
            payload = await response.json();
        } catch (_error) {
            throw new Error('The preset service returned an unreadable response (HTTP ' + response.status + ').');
        }
        if (!response.ok || !payload || payload.success !== true) {
            throw new Error((payload && payload.error) || ('HTTP ' + response.status));
        }
        return payload.data || payload.result || {};
    }

    // Mirrors submitting #gs_form, so unsaved on-screen edits are captured.
    function serializeGlobalSettingsForm() {
        const settings = {};
        const promptContextOptions = {};
        if (!presetForm) {
            return { settings: settings, prompt_context_options: promptContextOptions };
        }
        for (const entry of new FormData(presetForm).entries()) {
            const rawName = String(entry[0]);
            const rawValue = entry[1];
            if (rawName === 'save_all') {
                continue;
            }
            if (rawName.endsWith('[]')) {
                const listName = rawName.slice(0, -2);
                if (listName.indexOf('prompt_context_') === 0) {
                    const bucket = listName.slice('prompt_context_'.length);
                    if (!promptContextOptions[bucket]) promptContextOptions[bucket] = [];
                    promptContextOptions[bucket].push(String(rawValue));
                }
                continue;
            }
            settings[rawName] = typeof rawValue === 'string' ? rawValue : String(rawValue);
        }
        // FormData omits unchecked checkboxes; presets must preserve an explicit Off value.
        presetForm.querySelectorAll('input[type="checkbox"][name]:not([name$="[]"])').forEach((input) => {
            settings[input.name] = input.checked ? String(input.value || 'true') : 'false';
        });
        // Buckets with every checkbox cleared must still post an empty list.
        presetForm.querySelectorAll('input[name^="prompt_context_"][name$="[]"]').forEach((input) => {
            const bucket = input.name.slice('prompt_context_'.length, -2);
            if (!promptContextOptions[bucket]) promptContextOptions[bucket] = [];
        });
        return { settings: settings, prompt_context_options: promptContextOptions };
    }

    function dialogFocusable() {
        // offsetParent filters out the name field while it is hidden for confirm-only dialogs.
        return Array.from(presetDialogBackdrop.querySelectorAll('button:not([disabled]), input:not([disabled])'))
            .filter((element) => element.offsetParent !== null);
    }

    function setDialogBusy(busy, label) {
        dialogBusy = !!busy;
        presetDialogConfirm.disabled = dialogBusy;
        presetDialogCancel.disabled = dialogBusy;
        presetDialogName.disabled = dialogBusy;
        presetDialogConfirm.textContent = label || 'Confirm';
    }

    function setDialogError(message) {
        presetDialogError.textContent = message || '';
        presetDialogError.hidden = !message;
    }

    function openDialog(config) {
        dialogConfig = config;
        dialogInvoker = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        presetDialogTitle.textContent = config.title;
        presetDialogBody.textContent = config.body;
        presetDialogNameWrap.hidden = !config.needsName;
        presetDialogName.value = config.needsName ? (config.nameValue || '') : '';
        setDialogError('');
        setDialogBusy(false, config.confirmLabel);
        presetDialogBackdrop.classList.add('is-open');
        presetDialogBackdrop.setAttribute('aria-hidden', 'false');
        if (config.needsName && config.focusName) {
            presetDialogName.focus();
            presetDialogName.select();
        } else {
            // Destructive actions start on Cancel so Enter never fires them by accident.
            presetDialogCancel.focus();
        }
    }

    function closeDialog() {
        if (!presetDialogBackdrop.classList.contains('is-open')) {
            return;
        }
        presetDialogBackdrop.classList.remove('is-open');
        presetDialogBackdrop.setAttribute('aria-hidden', 'true');
        dialogConfig = null;
        setDialogBusy(false, 'Confirm');
        const invoker = dialogInvoker;
        dialogInvoker = null;
        if (invoker && document.contains(invoker) && !invoker.disabled) {
            invoker.focus();
        }
    }

    presetDialogBackdrop.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            if (dialogBusy) return;
            event.preventDefault();
            event.stopPropagation();
            closeDialog();
            return;
        }
        if (event.key === 'Enter' && dialogConfig && dialogConfig.needsName && event.target === presetDialogName) {
            event.preventDefault();
            presetDialogConfirm.click();
            return;
        }
        if (event.key !== 'Tab') {
            return;
        }
        const focusable = dialogFocusable();
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    presetDialogBackdrop.addEventListener('mousedown', (event) => {
        if (event.target === presetDialogBackdrop && !dialogBusy) closeDialog();
    });

    presetDialogCancel.addEventListener('click', () => {
        if (!dialogBusy) closeDialog();
    });

    presetDialogConfirm.addEventListener('click', () => {
        if (dialogBusy || !dialogConfig) return;
        const config = dialogConfig;
        let name = '';
        if (config.needsName) {
            name = presetDialogName.value.trim();
            if (name === '') {
                setDialogError('Enter a name for this preset.');
                presetDialogName.focus();
                return;
            }
        }
        setDialogError('');
        setDialogBusy(true, config.busyLabel);
        Promise.resolve()
            .then(() => config.onConfirm(name))
            .then(() => {
                setDialogBusy(false, config.confirmLabel);
                closeDialog();
            })
            .catch((error) => {
                setDialogBusy(false, config.confirmLabel);
                setDialogError(errorText(error));
                presetDialogConfirm.focus();
            });
    });

    // Keep focus inside the dialog when something outside steals it.
    document.addEventListener('focusin', (event) => {
        if (!presetDialogBackdrop.classList.contains('is-open')) return;
        if (presetDialogBackdrop.contains(event.target)) return;
        presetDialogCancel.focus();
    });

    async function loadPresets(preferredId) {
        setStatus('Loading presets…', false);
        setBarBusy(true);
        try {
            const data = await presetRequest(null);
            presets = Array.isArray(data.presets) ? data.presets.filter((preset) => preset && preset.id) : [];
            renderOptions(preferredId);
            // Idle state carries no message; the row keeps its width without showing text.
            setStatus('', false);
        } catch (error) {
            presets = [];
            renderOptions();
            setStatus('Could not load presets: ' + errorText(error), true);
        } finally {
            setBarBusy(false);
        }
    }

    function applyConfirmBody(preset) {
        const name = String(preset.name || preset.id);
        let body = 'Apply “' + name + '”? This saves the settings included in this preset immediately.';
        if (preset.affects_profiles) {
            body += ' It also updates context and response limits for all NPC profiles.';
        }
        return body + ' Unsaved edits on this page will be lost.';
    }

    // Selecting alone changes nothing; only button availability and the description update.
    presetSelect.addEventListener('change', () => {
        syncBar();
        setStatus('', false);
    });

    presetApplyBtn.addEventListener('click', () => {
        const preset = selectedPreset();
        if (!preset) return;
        const name = String(preset.name || preset.id);
        openDialog({
            title: 'Apply settings preset',
            body: applyConfirmBody(preset),
            confirmLabel: 'Apply preset',
            busyLabel: 'Applying…',
            needsName: false,
            onConfirm: async () => {
                setBarBusy(true);
                setStatus('Applying “' + name + '”…', false);
                try {
                    const result = await presetRequest({ operation: 'apply', preset_id: String(preset.id) });
                    const settingsUpdated = Number(result.settings_updated || 0);
                    const profilesUpdated = Number(result.profiles_updated || 0);
                    let message = 'Applied “' + String(result.name || name) + '”. '
                        + settingsUpdated + ' setting' + (settingsUpdated === 1 ? '' : 's') + ' updated';
                    message += profilesUpdated > 0
                        ? ', ' + profilesUpdated + ' NPC profile' + (profilesUpdated === 1 ? '' : 's') + ' updated.'
                        : '.';
                    try {
                        sessionStorage.setItem(presetAppliedKey, String(preset.id));
                        sessionStorage.setItem(presetFlashKey, message);
                    } catch (_error) {
                        // Storage can be unavailable in privacy-restricted browsers.
                    }
                    setStatus(message, false);
                    window.location.reload();
                } catch (error) {
                    setBarBusy(false);
                    setStatus('Apply failed: ' + errorText(error), true);
                    throw error;
                }
            }
        });
    });

    presetSaveNewBtn.addEventListener('click', () => {
        openDialog({
            title: 'Save current setup as a preset',
            body: 'Name this preset. It stores safe Global Settings currently on screen, including context selections and unsaved edits. Connector choices and service URLs stay unchanged.',
            confirmLabel: 'Save preset',
            busyLabel: 'Saving…',
            needsName: true,
            focusName: true,
            onConfirm: async (name) => {
                const payload = serializeGlobalSettingsForm();
                setBarBusy(true);
                setStatus('Saving “' + name + '”…', false);
                try {
                    const result = await presetRequest({
                        operation: 'save_new',
                        name: name,
                        settings: payload.settings,
                        prompt_context_options: payload.prompt_context_options
                    });
                    const saved = result.preset || {};
                    setBarBusy(false);
                    await loadPresets(saved.id ? String(saved.id) : undefined);
                    setStatus('Saved preset “' + String(saved.name || name) + '”.', false);
                } catch (error) {
                    setBarBusy(false);
                    setStatus('Save failed: ' + errorText(error), true);
                    throw error;
                }
            }
        });
    });

    presetOverwriteBtn.addEventListener('click', () => {
        const preset = selectedPreset();
        if (!preset || preset.built_in === true) return;
        const name = String(preset.name || preset.id);
        openDialog({
            title: 'Overwrite settings preset',
            body: 'Overwrite “' + name + '” with the safe Global Settings currently on screen, including unsaved edits? Connector choices and service URLs stay unchanged. The stored preset values are replaced and cannot be recovered.',
            confirmLabel: 'Overwrite preset',
            busyLabel: 'Saving…',
            needsName: false,
            onConfirm: async () => {
                const payload = serializeGlobalSettingsForm();
                setBarBusy(true);
                setStatus('Overwriting “' + name + '”…', false);
                try {
                    const result = await presetRequest({
                        operation: 'overwrite',
                        preset_id: String(preset.id),
                        settings: payload.settings,
                        prompt_context_options: payload.prompt_context_options
                    });
                    const saved = result.preset || {};
                    setBarBusy(false);
                    await loadPresets(String(saved.id || preset.id));
                    setStatus('Overwrote preset “' + String(saved.name || name) + '”.', false);
                } catch (error) {
                    setBarBusy(false);
                    setStatus('Overwrite failed: ' + errorText(error), true);
                    throw error;
                }
            }
        });
    });

    let presetFlash = '';
    let presetApplied = '';
    try {
        presetApplied = sessionStorage.getItem(presetAppliedKey) || '';
        presetFlash = sessionStorage.getItem(presetFlashKey) || '';
        if (presetFlash) sessionStorage.removeItem(presetFlashKey);
    } catch (_error) {
        presetApplied = '';
        presetFlash = '';
    }

    // A missing or stale id falls through to renderOptions' built-in default.
    loadPresets(presetApplied || undefined).then(() => {
        if (presetFlash) setStatus(presetFlash, false);
    });
})();
</script>

<?php
include(__DIR__ . DIRECTORY_SEPARATOR . "tmpl" . DIRECTORY_SEPARATOR . "footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $TITLE . '$3', $buffer);
echo $buffer;
?>
