<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");

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

$gsSections = [
    'General' => [
        [ 'name' => 'AUTO_LOCK_PROFILE', 'type' => 'boolean' ],
        [ 'name' => 'AUTOFILL_CUSTOM_PROFILES', 'type' => 'boolean' ],
        [ 'name' => 'AUTOFILL_CUSTOM_PROFILES_TRIGGER', 'type' => 'integer', 'min' => 10, 'max' => 100 ],
        [ 'name' => 'BGL_TRIGGER_DAYS', 'type' => 'integer', 'min' => 1, 'max' => 30 ],
        [ 'name' => 'END_CONVERSATION_COOLDOWN', 'type' => 'integer', 'min' => 0, 'max' => 300 ],
        [ 'name' => 'CLEAN_CONTEXT_FOCUS_CHAT_HISTORY', 'type' => 'integer' ],
    ],
    'Memory' => [
        [ 'name' => 'FEATURES@MEMORY_EMBEDDING@ENABLED', 'type' => 'boolean' ],
        [ 'name' => 'FEATURES@MEMORY_EMBEDDING@USE_TEXT2VEC', 'type' => 'boolean' ],
        [ 'name' => 'FEATURES@MEMORY_EMBEDDING@AUTO_CREATE_SUMMARY_INTERVAL', 'type' => 'integer' ],
    ],
    'Rechat' => [
        [ 'name' => 'RECHAT_MODE', 'type' => 'select', 'values' => ['tight', 'conversational', 'group', 'random'] ],
        [ 'name' => 'ENFORCE_STRICT_RECHAT_RESPONSE', 'type' => 'boolean' ],
    ],
    'Prompt' => [
        [ 'name' => 'PROMPT_HEAD', 'type' => 'longstring' ],
        [ 'name' => 'EMOTEMOODS', 'type' => 'longstring' ],
        [ 'name' => 'DETECT_MAGIC_EVENT', 'type' => 'boolean' ],
        [ 'name' => 'MAGIC_EVENT_BLACKLIST', 'type' => 'longstring' ],
        [ 'name' => 'LOCATION_BLACKLIST', 'type' => 'longstring' ],
        [ 'name' => 'ITEM_BLACKLIST', 'type' => 'longstring' ],
        [ 'name' => 'EVENT_TYPE_FILTER', 'type' => 'longstring' ],
    ],
    'Context' => [
        [ 'name' => 'GROUND_ITEMS_DESCRIPTIONS_ONLY', 'type' => 'boolean' ],
        [ 'name' => 'INVENTORY_ITEMS_DESCRIPTIONS_ONLY', 'type' => 'boolean' ],
        [ 'name' => 'HIDE_AMBIENT_COMBAT', 'type' => 'boolean' ],
        [ 'name' => 'DISABLE_REANIMATION_TRACKING', 'type' => 'boolean', 'action' => 'clear_reanimation' ],
        [ 'name' => 'TRANSFORMATION_DETECTION', 'type' => 'boolean' ],
        [ 'name' => 'PROMPT_TIMESTAMP', 'type' => 'boolean' ],
    ],
    'Prompt Context Options' => [],
    'Global Connectors' => [
        [ 'name' => 'CORE_CONNECTOR_PLAYER', 'type' => 'foreign:core_llm_connector:id:label' ],
        [ 'name' => 'CORE_CONNECTOR_SUMMARY', 'type' => 'foreign:core_llm_connector:id:label' ],
        [ 'name' => 'CORE_CONNECTOR_MEDIUMTERM', 'type' => 'foreign:core_llm_connector:id:label' ],
        [ 'name' => 'CORE_CONNECTOR_SCENECLASSIFIER', 'type' => 'foreign:core_llm_connector:id:label' ],
        [ 'name' => 'CORE_CONNECTOR_PROFILES', 'type' => 'foreign:core_llm_connector:id:label' ],
        [ 'name' => 'CORE_CONNECTOR_DIRECTOR', 'type' => 'foreign:core_llm_connector:id:label' ],
        [ 'name' => 'RELLLM_CONNECTOR', 'type' => 'foreign:core_llm_connector:id:label' ],
        [ 'name' => 'CORE_CONNECTOR_OGHMA_CUSTOM', 'type' => 'foreign:core_llm_connector:id:label' ],
    ],
    'Translation' => [
        [ 'name' => 'TRANSLATION_FUNCTION', 'type' => 'select', 'values' => ['none', 'DeepL'] ],
        [ 'name' => 'TRANSLATION@settings@translate_audio', 'type' => 'boolean' ],
        [ 'name' => 'TRANSLATION@settings@translate_text', 'type' => 'boolean' ],
        [ 'name' => 'TRANSLATION@settings@save_translated_text', 'type' => 'boolean' ],
        [ 'name' => 'TRANSLATION@settings@translate_player_audio', 'type' => 'boolean' ],
        [ 'name' => 'TRANSLATION@settings@save_translated_player_text', 'type' => 'boolean' ],
        [ 'name' => 'TRANSLATION@DeepL@source_language', 'type' => 'string' ],
        [ 'name' => 'TRANSLATION@DeepL@target_language', 'type' => 'string' ],
        [ 'name' => 'TRANSLATION@DeepL@url', 'type' => 'url' ],
        [ 'name' => 'TRANSLATION@DeepL@player_source_language', 'type' => 'string' ],
        [ 'name' => 'TRANSLATION@DeepL@player_target_language', 'type' => 'string' ],
    ],
];

function pretty_label(string $flatName): string
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
        'CORE_CONNECTOR_MEDIUMTERM' => 'Middle Term Memory/Background Life',
        'CORE_CONNECTOR_SCENECLASSIFIER' => 'Scene Classifier',
        'SCENE_CLASSIFIER_ENABLED' => 'Scene Classifier',
        'CORE_CONNECTOR_PROFILES' => 'Dynamic Profile',
        'CORE_CONNECTOR_DIRECTOR' => 'Director Mode',
        'CORE_CONNECTOR_OGHMA_CUSTOM' => 'Custom Oghma LLM',
        'RELLLM_CONNECTOR' => 'Relationship Management',
        'EMOTEMOODS' => 'Emote Moods',
        'ENFORCE_STRICT_RECHAT_RESPONSE' => 'Strict Rechat Targeting',
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
    if (strpos($u, 'FEATURES@MEMORY_EMBEDDING@') === 0 || strpos($u, 'MEMORY_') !== false) return '💭';
    if ($u === 'PLAYER_NAME') return '🏷️';
    if ($u === 'PROMPT_HEAD') return '🔝';
    if ($u === 'EMOTEMOODS') return '🎭';
    if ($u === 'PROMPT_TIMESTAMP') return '🕐';
    if (strpos($u, 'CORE_CONNECTOR_') === 0) {
        if ($u === 'CORE_CONNECTOR_PLAYER') return '🎮';
        if ($u === 'CORE_CONNECTOR_SUMMARY') return '📝';
        if ($u === 'CORE_CONNECTOR_MEDIUMTERM') return '🧠';
        if ($u === 'CORE_CONNECTOR_SCENECLASSIFIER') return '🎭';
        if ($u === 'CORE_CONNECTOR_PROFILES') return '👥';
        if ($u === 'CORE_CONNECTOR_DIRECTOR') return '🎬';
        if ($u === 'CORE_CONNECTOR_OGHMA_CUSTOM') return '🐙';
        return '🔌';
    }
    if ($u === 'SCENE_CLASSIFIER_ENABLED') return '🎭';
    if ($u === 'RELATIONSHIP_SYSTEM_ENABLED') return '💞';
    if ($u === 'RELLLM_CONNECTOR') return '🔗';
    if ($u === 'POWER_AWARENESS_ENABLED') return '⚔️';
    if (strpos($u, 'RESPEECH') !== false) return '🦜';
    if (strpos($u, 'SPEECH_STYLE') !== false) return '🦜';
    if (strpos($u, 'SUMMARY_PROMPT') === 0) return '🎭';
    if (strpos($u, 'DYNAMIC_PROMPT_') === 0) return '🎭';
    if (strpos($u, 'DIARY') !== false) return '📙';
    if (strpos($u, 'NARRATOR') !== false) return '🗣️';
    return '⚙️';
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

function prompt_context_bucket_title(string $bucket): string
{
    $labels = [
        'enabled_sections' => 'Top-Level Sections',
        'enabled_character_subsections' => 'Character Subsections',
        'enabled_general_subsections' => 'General Instruction Subsections',
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
    $row = $rowMap[$flatName] ?? null;
    $description = is_array($row) ? trim(strval($row['description'] ?? '')) : '';
    if ($description !== '') {
        return $description;
    }
    return chimGetSchemaDescription($flatName);
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

    $specialBooleans = [
        'RELATIONSHIP_SYSTEM_ENABLED',
        'SCENE_CLASSIFIER_ENABLED',
        'OGHMA_CUSTOM',
    ];
    foreach ($specialBooleans as $name) {
        $value = normalize_posted_value('boolean', $_POST[$name] ?? 'false');
        $description = current_description($name, $generalSettingRowMap);
        if (!chimSetGeneralSetting($name, $value, $description)) {
            $didSave = false;
        }
    }

    $postedPromptContextOptions = chimNormalizePromptContextOptions([
        'enabled_sections' => array_values(array_map('strval', $_POST['prompt_context_enabled_sections'] ?? [])),
        'enabled_character_subsections' => array_values(array_map('strval', $_POST['prompt_context_enabled_character_subsections'] ?? [])),
        'enabled_general_subsections' => array_values(array_map('strval', $_POST['prompt_context_enabled_general_subsections'] ?? [])),
    ]);
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
    padding-top: 40px;
    padding-bottom: 40px;
    padding-left: 10%;
    padding-right: 10%;
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
    margin: 0 0 16px 0;
    padding: 14px 18px;
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
    flex-wrap: wrap;
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
    margin-left: 10px;
    display: flex;
    align-items: center;
}

.provider-toggle input[type="checkbox"] {
    accent-color: #176529;
    transform: scale(1.6);
    transform-origin: center;
    cursor: pointer;
}

.provider-help {
    margin-top: 0;
    color: #bbb;
    font-size: 12px;
    line-height: 1.45;
    min-width: 0;
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

@media (max-width: 1000px) {
    .provider-card {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 900px) {
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
}
</style>

<main>
    <div class="page-header">
        <div class="page-header-row">
            <h1 class="gs-title">Global Settings</h1>
            <div class="page-header-actions">
                <button type="submit" class="btn-save-green" name="save_all" value="1" form="gs_form">Save All</button>
            </div>
        </div>
    </div>

    <?php if ($saveSuccess): ?>
        <div class="result-ok" style="margin-bottom: 16px;">Global settings saved to the database.</div>
    <?php endif; ?>

    <form method="post" action="" id="gs_form">
        <div class="content-grid">
            <?php foreach ($gsSections as $sectionTitle => $fields): ?>
                <div class="content-section">
                    <h2><?php echo htmlspecialchars($sectionTitle); ?></h2>
                    <div class="provider-grid">
                        <?php if ($sectionTitle === 'Prompt Context Options'): ?>
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
                            $label = pretty_label($fieldName);
                            $help = current_description($fieldName, $generalSettingRowMap);
                            $schemaDefinition = chimGetSchemaDefinition($fieldName);
                            $isReadonly = isset($schemaDefinition['readonly']) && $schemaDefinition['readonly'] === true;
                            $readonlyAttr = $isReadonly ? 'readonly' : '';
                            ?>
                            <div class="provider-card">
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
                                        <?php if ($fieldName === 'RELLLM_CONNECTOR'): ?>
                                            <div class="provider-toggle">
                                                <input type="hidden" name="RELATIONSHIP_SYSTEM_ENABLED" value="false">
                                                <input type="checkbox" name="RELATIONSHIP_SYSTEM_ENABLED" value="true" <?php echo (current_value('RELATIONSHIP_SYSTEM_ENABLED') ? 'checked' : ''); ?> title="Enable/Disable Relationship System">
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($fieldName === 'CORE_CONNECTOR_SCENECLASSIFIER'): ?>
                                            <div class="provider-toggle">
                                                <input type="hidden" name="SCENE_CLASSIFIER_ENABLED" value="false">
                                                <input type="checkbox" name="SCENE_CLASSIFIER_ENABLED" value="true" <?php echo (current_value('SCENE_CLASSIFIER_ENABLED') ? 'checked' : ''); ?> title="Enable/Disable Scene Classifier">
                                            </div>
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
                                        <input type="number" step="0.01" name="<?php echo htmlspecialchars($fieldName); ?>" value="<?php echo htmlspecialchars(strval($current)); ?>" <?php echo $readonlyAttr; ?>>
                                    <?php elseif ($fieldType === 'longstring'): ?>
                                        <textarea name="<?php echo htmlspecialchars($fieldName); ?>" rows="4" <?php echo $readonlyAttr; ?>><?php echo htmlspecialchars(strval($current)); ?></textarea>
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
                                    <div class="provider-help"><?php echo htmlspecialchars($help); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </form>
</main>

<?php
include(__DIR__ . DIRECTORY_SEPARATOR . "tmpl" . DIRECTORY_SEPARATOR . "footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $TITLE . '$3', $buffer);
echo $buffer;
?>
