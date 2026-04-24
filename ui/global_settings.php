<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
// Load schema/helpers without requiring a potentially broken conf.php
require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf_loader.php");
// Include helpers used by TTS quick test
@include_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php");
@include_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "online_translation.php");
@include_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php");
// Seed defaults from sample so UI has baseline values even if conf.php is broken
@include_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.sample.php");

// Determine web root (match other core pages)
$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
if ($uiPos !== false) {
    $webRoot = substr($scriptPath, 0, $uiPos);
} else {
    $webRoot = '';
}
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

// Site chrome (also loads conf_loader.php once)
require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");
$TITLE = "⚙️ CHIM - Global Settings";
ob_start();
include(__DIR__.DIRECTORY_SEPARATOR."tmpl".DIRECTORY_SEPARATOR."head.html");

// Load schema and current configuration
$confSchema = conf_loader_load_schema();
$currentConf = conf_loader_load();

// Raw schema for provider groups (TTS/STT/ITT)
$schemaPath = $enginePath . "conf" . DIRECTORY_SEPARATOR . "conf_schema.json";
$rawSchema = @json_decode(@file_get_contents($schemaPath), true);
if (!is_array($rawSchema)) $rawSchema = [];
$providersTts = is_array($rawSchema['TTS'] ?? null) ? $rawSchema['TTS'] : [];
$providersStt = is_array($rawSchema['STT'] ?? null) ? $rawSchema['STT'] : [];
$ittProviders = is_array($rawSchema['ITT'] ?? null) ? $rawSchema['ITT'] : [];
$ttsOptions = $rawSchema['TTSFUNCTION']['values'] ?? [ 'mimic3','melotts','xtts-fastapi','xvasynth','azure','11labs','openai','koboldcpp','zonos_gradio','piper-tts','kokoro','deepgram','cartesia','inworld' ];
$sttOptions = $rawSchema['STTFUNCTION']['values'] ?? [ 'none','whisper','localwhisper','azure','deepgram','gemini','parakeet','inworld' ];
$ittOptionsRaw = $rawSchema['ITTFUNCTION']['values'] ?? [ 'openai','google_openai','openrouter','llamacpp' ];
// Exclude llamacpp per existing ITT page behavior
$ittOptions = array_values(array_filter($ittOptionsRaw, function($v){ return strtolower($v) !== 'llamacpp'; }));

// Mappings
$ttsMap = [ 'melotts' => 'MELOTTS','xtts-fastapi' => 'XTTSFASTAPI','chatterbox' => 'CHATTERBOX','pockettts' => 'POCKETTTS','mimic3' => 'MIMIC3','xvasynth' => 'XVASYNTH','azure' => 'AZURE','11labs' => 'ELEVEN_LABS','openai' => 'openai','kokoro' => 'KOKORO','koboldcpp' => 'koboldcpp','zonos_gradio' => 'ZONOS_GRADIO','piper-tts' => 'PIPERTTS','deepgram' => 'deepgram','cartesia' => 'CARTESIA','inworld' => 'INWORLD' ];
$sttMap = [ 'whisper' => 'WHISPER','localwhisper' => 'LOCALWHISPER','azure' => 'AZURE','deepgram' => 'DEEPGRAM','parakeet' => 'PARAKEET','gemini' => 'GEMINI','inworld' => 'INWORLD' ];
$ittMap = [ 'openai' => 'openai','google_openai' => 'google_openai','openrouter' => 'openrouter' ];
// Display name mappings for UI labels
$ttsDisplayNames = [ 
    'none' => 'None',
    'melotts' => 'MeloTTS', 
    'xtts-fastapi' => 'XTTS', 
    'chatterbox' => 'Chatterbox', 
    'pockettts' => 'PocketTTS',
    'xvasynth' => 'xVASynth',
    'mimic3' => 'Mimic3',
    'azure' => 'Azure TTS',
    '11labs' => 'ElevenLabs',
    'openai' => 'OpenAI TTS',
    'kokoro' => 'Kokoro',
    'koboldcpp' => 'KoboldCPP',
    'zonos_gradio' => 'Zonos TTS',
    'piper-tts' => 'Piper TTS',
    'deepgram' => 'Deepgram',
    'cartesia' => 'Cartesia',
    'inworld' => 'Inworld'
];

// Active tab tracking for postback previews
$activeTab = (isset($_POST['gs_tab']) && is_string($_POST['gs_tab'])) ? (string)$_POST['gs_tab'] : 'tab-global';

// Selected providers for render; preview POST (no save) should reflect immediate selection
$ttsSelRender = isset($_POST['TTSFUNCTION']) && !isset($_POST['save_all']) ? (string)$_POST['TTSFUNCTION'] : (string)current_value('TTSFUNCTION', $currentConf);
if ($ttsSelRender === '' && !empty($ttsOptions)) { $ttsSelRender = (string)$ttsOptions[0]; }
$sttSelRender = isset($_POST['STTFUNCTION']) && !isset($_POST['save_all']) ? (string)$_POST['STTFUNCTION'] : (string)current_value('STTFUNCTION', $currentConf);
if ($sttSelRender === '' && !empty($sttOptions)) { $sttSelRender = (string)$sttOptions[0]; }
$ittSelRender = isset($_POST['ITTFUNCTION']) && !isset($_POST['save_all']) ? (string)$_POST['ITTFUNCTION'] : (string)current_value('ITTFUNCTION', $currentConf);
if ($ittSelRender === '' && !empty($ittOptions)) { $ittSelRender = (string)$ittOptions[0]; }

// TTS Quick Test (inline) handler
$ttsTestOutputUrl = '';
$ttsTestTextDefault = "In Skyrim's land of snow and ice, Where dragons soar and souls entwine, Heroes rise, their fate unveiled, As ancient tales, the land does bind.";
$ttsTestText = $ttsTestTextDefault;
$ttsTestVoice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tts_quick_test'])) {
    // Hard timeouts to prevent server lock if TTS backend stalls
    try { @set_time_limit(30); } catch (Throwable $_) {}
    try { @ini_set('default_socket_timeout', '20'); } catch (Throwable $_) {}
    try { if (!isset($GLOBALS['db']) || !$GLOBALS['db']) { @include_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php"); if (isset($GLOBALS["DBDRIVER"])) @require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . $GLOBALS["DBDRIVER"] . ".class.php"); $GLOBALS['db'] = new sql(); } } catch (Throwable $_) {}
    $ttsTestText = isset($_POST['tts_text']) ? (string)$_POST['tts_text'] : '';
    if (trim($ttsTestText) === '') { $ttsTestText = $ttsTestTextDefault; }
    $selectedFunction = isset($_POST['TTSFUNCTION']) ? (string)$_POST['TTSFUNCTION'] : $ttsSelRender;
    $ttsTestVoice = isset($_POST['tts_voiceid']) ? trim((string)$_POST['tts_voiceid']) : '';
    $GLOBALS["TTSFUNCTION"] = $selectedFunction;
    $GLOBALS["HERIKA_NAME"] = "The Narrator";
    $GLOBALS["AVOID_TTS_CACHE"] = true;
    $GLOBALS["TTS_FFMPEG_FILTERS"] = [];
    $GLOBALS["HERIKA_ANIMATIONS"] = false;
    $GLOBALS["SCRIPTLINE_LISTENER"] = '';
    $GLOBALS["SCRIPTLINE_EXPRESSION"] = '';
    $GLOBALS["DEBUG_DATA"] = [];
    // Respect a shorter HTTP timeout when testing
    if (!isset($GLOBALS["HTTP_TIMEOUT"]) || (int)$GLOBALS["HTTP_TIMEOUT"] <= 0) { $GLOBALS["HTTP_TIMEOUT"] = 20; }
    $GLOBALS["FEATURES"] = $GLOBALS["FEATURES"] ?? [];
    if (!isset($GLOBALS["FEATURES"]["MISC"])) $GLOBALS["FEATURES"]["MISC"] = [];
    if (!isset($GLOBALS["FEATURES"]["MISC"]["TTS_RANDOM_PITCH"])) $GLOBALS["FEATURES"]["MISC"]["TTS_RANDOM_PITCH"] = false;
    if (!isset($GLOBALS["PLAYER_NAME"])) $GLOBALS["PLAYER_NAME"] = 'Player';
    $GLOBALS["PATCH_DONT_STORE_SPEECH_ON_DB"] = true;
    $gameRequest = [ 'tts_quick_test', time(), time(), '' ];
    $selLower = strtolower($selectedFunction);
    if ($ttsTestVoice !== '') {
        // User specified a test voice - override configured voice
        $GLOBALS["PATCH_OVERRIDE_VOICE"] = $ttsTestVoice;
    } else {
        // Only set default voices for providers that need them; let 11labs/openai/azure/deepgram use configured voice
        if ($selLower === 'xtts-fastapi') $GLOBALS["PATCH_OVERRIDE_VOICE"] = 'TheNarrator';
        else if ($selLower === 'chatterbox') $GLOBALS["PATCH_OVERRIDE_VOICE"] = 'TheNarrator';
        else if ($selLower === 'pockettts') $GLOBALS["PATCH_OVERRIDE_VOICE"] = 'TheNarrator';
        else if ($selLower === 'cartesia') $GLOBALS["PATCH_OVERRIDE_VOICE"] = 'TheNarrator';
        else if ($selLower === 'inworld') $GLOBALS["PATCH_OVERRIDE_VOICE"] = 'TheNarrator';
        else if (in_array($selLower, ['melotts','piper-tts','xvasynth'], true)) $GLOBALS["PATCH_OVERRIDE_VOICE"] = 'malenord';
    }
    try {
        $GLOBALS["PATCH_DONT_STORE_SPEECH_ON_DB"]=true;
        $speakText = $ttsTestText;
        returnLines([$speakText], false);
        $file = isset($GLOBALS["TRACK"]["FILES_GENERATED"][0]) ? basename((string)$GLOBALS["TRACK"]["FILES_GENERATED"][0]) : '';
        if ($file !== '') { $ttsTestOutputUrl = $webRoot . '/soundcache/' . $file . '?ts=' . time(); }
    } catch (Throwable $e) {
        Logger::error('TTS quick test failed: '.$e->getMessage());
        $ttsTestOutputUrl = '';
    }
    unset($GLOBALS["PATCH_OVERRIDE_VOICE"]);
}

// If TTS quick test was requested via AJAX, return JSON and exit early
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tts_quick_test']) && isset($_POST['ajax'])) {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => ($ttsTestOutputUrl !== ''),
        'url' => $ttsTestOutputUrl,
    ]);
    exit;
}

// Handle Clear Reanimation Status action
$clearReanimationResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_reanimation_status'])) {
    try {
        // Initialize database if needed
        if (!isset($GLOBALS['db']) || !$GLOBALS['db']) {
            @include_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
            if (isset($GLOBALS["DBDRIVER"])) {
                @require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . $GLOBALS["DBDRIVER"] . ".class.php");
            }
            $GLOBALS['db'] = new sql();
        }
        
        $db = $GLOBALS['db'];
        $affectedCount = 0;
        
        // 1. Remove "reanimated" flag from extended_data for all NPCs
        $updateExtended = "UPDATE core_npc_master 
            SET extended_data = extended_data - 'reanimated'
            WHERE extended_data::text LIKE '%reanimated%'";
        $db->execQuery($updateExtended);
        
        // 2. Remove zombie text from core field (multiple variations)
        $zombiePhrases = [
            ' You have been reanimated from death as a zombie.',
            'You have been reanimated from death as a zombie. ',
            'You have been reanimated from death as a zombie.',
        ];
        
        foreach ($zombiePhrases as $phrase) {
            $escaped = $db->escape($phrase);
            $updateCore = "UPDATE core_npc_master 
                SET core = REPLACE(core, '{$escaped}', '')
                WHERE core LIKE '%{$escaped}%'";
            $db->execQuery($updateCore);
        }
        
        // Count affected NPCs for feedback
        $countQuery = "SELECT COUNT(*) as cnt FROM core_npc_master WHERE 1=0"; // Placeholder
        
        $clearReanimationResult = ['success' => true, 'message' => 'Successfully cleared reanimation status from all NPCs.'];
        Logger::info("[GLOBAL_SETTINGS] Cleared reanimation status from all NPCs");
        
    } catch (Exception $e) {
        $clearReanimationResult = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        Logger::error("[GLOBAL_SETTINGS] Failed to clear reanimation status: " . $e->getMessage());
    }
}

// Helper: flatten currentConf into name=>value pairs like conf_wizard/conf_writer
function flatten_current_conf(array $currentConf, array $confSchema): array {
    $flat = [];
    foreach ($currentConf as $pname => $parms) {
        $fieldName = strtr($pname, [" " => "@"]); // HERIKA NAME -> HERIKA@NAME
        $type = $parms["type"] ?? ($confSchema[$pname]["type"] ?? 'string');
        $val = $parms["currentValue"] ?? '';
        if ($type !== 'selectmultiple' && is_array($val)) {
            $firstScalar = '';
            foreach ($val as $candidate) {
                if (is_scalar($candidate)) {
                    $firstScalar = (string)$candidate;
                    break;
                }
            }
            $val = $firstScalar;
        }
        if ($type === 'boolean') {
            $flat[$fieldName] = $val ? 'true' : 'false';
        } else if ($type === 'selectmultiple') {
            $flat[$fieldName] = is_array($val) ? $val : [];
        } else if ($type === 'number' || $type === 'integer') {
            $flat[$fieldName] = (string)($val === '' ? '' : $val);
        } else {
            // strings, longstring, url, apikey, foreign, etc.
            $flat[$fieldName] = (string)$val;
        }
    }
    return $flat;
}

// Helper: format field name for display with proper title casing
function format_field_label($fieldName) {
    // Special cases for common acronyms and abbreviations
    $specialCases = [
        'api_key' => 'API Key',
        'url' => 'URL',
        'tts' => 'TTS',
        'stt' => 'STT',
        'itt' => 'ITT',
        'llm' => 'LLM',
        'id' => 'ID',
        'voiceid' => 'Voice ID',
        'model_id' => 'Model ID',
        'voice_id' => 'Voice ID',
        'speaker_id' => 'Speaker ID',
        'cfg_scale' => 'CFG Scale',
    ];
    
    $lower = strtolower($fieldName);
    if (isset($specialCases[$lower])) {
        return $specialCases[$lower];
    }
    
    // Convert snake_case or underscore-separated to Title Case
    $words = preg_split('/[_\s]+/', $fieldName);
    $formatted = array_map(function($word) {
        // Capitalize first letter of each word
        return ucfirst(strtolower($word));
    }, $words);
    
    return implode(' ', $formatted);
}


// Helper: build conf.php content using logic aligned with tools/conf_writer.php
function build_conf_php_from_pairs(array $pairs, array $confSchema): string {
    $buffer = "<?php" . PHP_EOL;
    $oldGroup = '';
    $oldSubGroup = '';

    $process_slashes = function(string $s_input): string {
        $sx = str_replace("\\'", "'", $s_input);
        return addcslashes($sx, "'");
    };

    foreach ($pairs as $k => $v) {
        $fullNameHierch = explode("@", $k);
        $plainNameHierch = strtr($k, ["@" => " "]);
        $type = $confSchema[$plainNameHierch]["type"] ?? 'string';

        if ($type !== 'selectmultiple' && is_array($v)) {
            $firstScalar = '';
            foreach ($v as $candidate) {
                if (is_scalar($candidate)) {
                    $firstScalar = (string)$candidate;
                    break;
                }
            }
            $v = $firstScalar;
        }

        if (is_array($v)) {
            $value = json_encode($v, true);
        } else if ($type === 'number') {
            if ($v === '') continue; else $value = "" . addcslashes($v, "'") . "";
        } else if ($type === 'boolean') {
            $value = ($v === 'true') ? 'true' : 'false';
        } else {
            $value = "'" . $process_slashes((string)$v) . "'";
        }

        if ($oldGroup != $fullNameHierch[0]) {
            $buffer .= PHP_EOL . PHP_EOL;
            $oldGroup = $fullNameHierch[0];
        }
        if (isset($fullNameHierch[1])) {
            if ($oldSubGroup != $fullNameHierch[1]) {
                $buffer .= PHP_EOL;
                $oldSubGroup = $fullNameHierch[1];
            }
        }

        if (sizeof($fullNameHierch) == 1) {
            if (isset($confSchema[$plainNameHierch]["description"]))
                $buffer .= "//" . $confSchema[$plainNameHierch]["description"] . PHP_EOL;
            $buffer .= "\${$fullNameHierch[0]}=$value;" . PHP_EOL;
        } else if (sizeof($fullNameHierch) == 2) {
            $inlineComment = '';
            if (isset($confSchema[$plainNameHierch]["description"]))
                $inlineComment = "//" . $confSchema[$plainNameHierch]["description"];
            $buffer .= "\${$fullNameHierch[0]}[\"$fullNameHierch[1]\"]=$value;\t$inlineComment" . PHP_EOL;
        } else if (sizeof($fullNameHierch) == 3) {
            $inlineComment = '';
            if (isset($confSchema[$plainNameHierch]["description"]))
                $inlineComment = "//" . $confSchema[$plainNameHierch]["description"];
            $buffer .= "\${$fullNameHierch[0]}[\"$fullNameHierch[1]\"][\"$fullNameHierch[2]\"]=$value;\t$inlineComment" . PHP_EOL;
        }
    }

    $buffer .= "?>" . PHP_EOL;
    return $buffer;
}

// Helper: humanize a flat name like PLAYER_NAME or FEATURES@MEMORY_EMBEDDING@ENABLED
function pretty_label(string $flatName): string {
    // For Memory Embedding keys, show only the final part
    if (strpos($flatName, 'FEATURES@MEMORY_EMBEDDING@') === 0) {
        $parts = explode('@', $flatName);
        $last = end($parts) ?: $flatName;
        $last2 = str_replace('_', ' ', strtolower(trim($last)));
        return ucwords($last2);
    }
    // Translation: simplify labels for settings and DeepL
    if (strpos($flatName, 'TRANSLATION@settings@') === 0) {
        $parts = explode('@', $flatName);
        $last = end($parts) ?: $flatName;
        $last2 = str_replace('_', ' ', strtolower(trim($last)));
        return ucwords($last2);
    }
    if (strpos($flatName, 'TRANSLATION@DeepL@') === 0) {
        $parts = explode('@', $flatName);
        $last = end($parts) ?: $flatName;
        $lastLower = strtolower(trim($last));
        if ($lastLower === 'url') return 'Endpoint URL';
        if ($lastLower === 'api_key') return 'API Key';
        $last2 = str_replace('_', ' ', $lastLower);
        return ucwords($last2);
    }
    if ($flatName === 'TRANSLATION_FUNCTION') {
        return 'Provider';
    }
    // Custom display names (UI-only)
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
    ];
    if (isset($customLabels[$flatName])) {
        return $customLabels[$flatName];
    }
    $parts = explode('@', $flatName);
    $prettyParts = [];
    foreach ($parts as $p) {
        $p2 = str_replace('_', ' ', strtolower(trim($p)));
        $prettyParts[] = ucwords($p2);
    }
    return implode(' → ', $prettyParts);
}

// Helper: choose icon per setting name
function icon_for_field(string $flatName): string {
    $u = strtoupper($flatName);
    // Memory-related
    if (strpos($u, 'FEATURES@MEMORY_EMBEDDING@') === 0 || strpos($u, 'MEMORY_') !== false) return '💭';
    // Specific keys
    if ($u === 'PLAYER_NAME') return '🏷️';
    if ($u === 'PROMPT_HEAD') return '🔝';
    if ($u === 'EMOTEMOODS') return '🎭';
    if ($u === 'PROMPT_TIMESTAMP') return '🕐';
    // Connectors
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
    // Respeech related
    if (strpos($u, 'RESPEECH') !== false) return '🦜';
    if (strpos($u, 'SPEECH_STYLE') !== false) return '🦜';
    // Prompts (summary / dynamic prompts)
    if (strpos($u, 'SUMMARY_PROMPT') === 0) return '🎭';
    if (strpos($u, 'DYNAMIC_PROMPT_') === 0) return '🎭';
    // Diary
    if (strpos($u, 'DIARY') !== false) return '📙';
    // Narrator
    if (strpos($u, 'NARRATOR') !== false) return '🗣️';
    return '⚙️';
}

// Curated, manually-defined global settings (exclude TTS, STT, ITT)
$gsSections = [
    'General' => [
        [ 'name' => 'AUTO_LOCK_PROFILE', 'type' => 'boolean' ],
        [ 'name' => 'AUTOFILL_CUSTOM_PROFILES', 'type' => 'boolean' ],
        [ 'name' => 'AUTOFILL_CUSTOM_PROFILES_TRIGGER', 'type' => 'integer', 'min' => 10, 'max' => 100 ],
        [ 'name' => 'BGL_TRIGGER_DAYS', 'type' => 'integer', 'min' => 1, 'max' => 30 ],
        [ 'name' => 'END_CONVERSATION_COOLDOWN', 'type' => 'integer', 'min' => 0, 'max' => 300 ],
        [ 'name' => 'CARRIAGE_DRIVERS', 'type' => 'longstring' ],
        [ 'name' => 'FERRY_DRIVERS', 'type' => 'longstring' ],
        [ 'name' => 'CLEAN_CONTEXT_FOCUS_CHAT_HISTORY', 'type' => 'integer' ],
        [ 'name' => 'FEATURES@MEMORY_EMBEDDING@ENABLED', 'type' => 'boolean', 'subsection' => 'Memory' ],
        [ 'name' => 'FEATURES@MEMORY_EMBEDDING@USE_TEXT2VEC', 'type' => 'boolean', 'subsection' => 'Memory' ],
        [ 'name' => 'FEATURES@MEMORY_EMBEDDING@AUTO_CREATE_SUMMARY_INTERVAL', 'type' => 'integer', 'subsection' => 'Memory' ],
        // Hidden from Global Settings UI:
        // - MEMORY_TIME_DELAY
        // - MEMORY_CONTEXT_SIZE
        // - MEMORY_BIAS_A / MEMORY_BIAS_B
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
        [ 'name' => 'PROMPT_TIMESTAMP', 'type' => 'boolean' ],
    ],
    // NOTE: Diary section removed - AUTO_DIARY is now configured per-profile in Profile Settings
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
    // 'Dynamic Prompts' => [
    //     // All dynamic prompts have been migrated to Prompts Manager (⚙️Prompts Manager in Config Hub)
    //     // [ 'name' => 'DYNAMIC_PROMPT_PERSONALITY', 'type' => 'longstring' ],
    //     // [ 'name' => 'DYNAMIC_PROMPT_RELATIONSHIPS', 'type' => 'longstring' ],
    //     // [ 'name' => 'DYNAMIC_PROMPT_OCCUPATION', 'type' => 'longstring' ],
    //     // [ 'name' => 'DYNAMIC_PROMPT_SKILLS', 'type' => 'longstring' ],
    //     // [ 'name' => 'DYNAMIC_PROMPT_SPEECHSTYLE', 'type' => 'longstring' ],
    //     // [ 'name' => 'DYNAMIC_PROMPT_GOALS', 'type' => 'longstring' ],
    // ],
    // 'Narrator' section removed - now managed via Narrator Management page (Config Hub > Narrator)
    'Translation' => [
        [ 'name' => 'TRANSLATION_FUNCTION', 'type' => 'select', 'values' => ['none','DeepL'] ],
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
        
    ]
];

// Build lookup for descriptions from schema
$gsDesc = function(string $flatName) use ($confSchema): string {
    $plain = strtr($flatName, ["@" => " "]);
    return $confSchema[$plain]["description"] ?? '';
};

// Fetch DB data only if any field requires foreign options
$foreignOptions = [];
$hasForeign = false;
foreach ($gsSections as $sec => $fields) {
    foreach ($fields as $f) {
        if (strpos($f['type'], 'foreign:') === 0) { $hasForeign = true; break; }
    }
    if ($hasForeign) break;
}
if ($hasForeign) {
    // Load DB driver safely from sample or current conf
    @include_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
    if (!isset($GLOBALS["DBDRIVER"])) {
        @include_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.sample.php");
    }
    $dbDriverFile = $enginePath . "lib" . DIRECTORY_SEPARATOR . ($GLOBALS["DBDRIVER"] ?? '') . ".class.php";
    if (isset($GLOBALS["DBDRIVER"]) && file_exists($dbDriverFile)) {
        @require_once($dbDriverFile);
        if (class_exists('sql')) {
            $db = new sql();
            foreach ($gsSections as $sec => $fields) {
                foreach ($fields as $f) {
                    if (strpos($f['type'], 'foreign:') === 0) {
                        $parts = explode(':', $f['type']); // foreign:table:id:label
                        if (count($parts) === 4) {
                            $table = $parts[1];
                            $idCol = $parts[2];
                            $labelCol = $parts[3];
                            $rows = $db->fetchAll("select {$idCol},{$labelCol} from {$table}");
                            $foreignOptions[$f['name']] = $rows;
                        }
                    }
                }
            }
        }
    }
}

$sceneClassifierLabels = [
    'Gemma 3N E4B',
    'Scene Classifier (Gemma 3N E4B)',
    'Scene Classifier (Gemini 2.5 Flash Lite)'
];
$runtimeConfPath = $enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php";
$runtimeConfRaw = @file_get_contents($runtimeConfPath);
$sceneClassifierExplicitlyConfigured = is_string($runtimeConfRaw)
    && preg_match('/\\$CORE_CONNECTOR_SCENECLASSIFIER\\s*=/', $runtimeConfRaw);
$sceneClassifierEnabledExplicitlyConfigured = is_string($runtimeConfRaw)
    && preg_match('/\\$SCENE_CLASSIFIER_ENABLED\\s*=/', $runtimeConfRaw);

if (!$sceneClassifierExplicitlyConfigured && !empty($foreignOptions['CORE_CONNECTOR_SCENECLASSIFIER'])) {
    foreach ($foreignOptions['CORE_CONNECTOR_SCENECLASSIFIER'] as $row) {
        $rowLabel = trim((string)($row['label'] ?? ''));
        foreach ($sceneClassifierLabels as $sceneClassifierLabel) {
            if (strcasecmp($rowLabel, $sceneClassifierLabel) !== 0) {
                continue;
            }
            if (!isset($currentConf['CORE_CONNECTOR_SCENECLASSIFIER']) || !is_array($currentConf['CORE_CONNECTOR_SCENECLASSIFIER'])) {
                $currentConf['CORE_CONNECTOR_SCENECLASSIFIER'] = $confSchema['CORE_CONNECTOR_SCENECLASSIFIER'] ?? ['type' => 'foreign:core_llm_connector:id:label'];
            }
            $currentConf['CORE_CONNECTOR_SCENECLASSIFIER']['currentValue'] = (string)($row['id'] ?? '');
            break;
        }
        if (!empty($currentConf['CORE_CONNECTOR_SCENECLASSIFIER']['currentValue'] ?? '')) {
            break;
        }
    }
}

if (!$sceneClassifierEnabledExplicitlyConfigured) {
    if (!isset($currentConf['SCENE_CLASSIFIER_ENABLED']) || !is_array($currentConf['SCENE_CLASSIFIER_ENABLED'])) {
        $currentConf['SCENE_CLASSIFIER_ENABLED'] = $confSchema['SCENE_CLASSIFIER_ENABLED'] ?? ['type' => 'boolean'];
    }
    $currentConf['SCENE_CLASSIFIER_ENABLED']['currentValue'] = true;
}

// Handle Save
$saveSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_all'])) {
	// Reload latest configuration to avoid overwriting changes from other pages
	$confFile = $enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php";
	@clearstatcache(true, $confFile);
	$currentConf = conf_loader_load();
    // Flatten existing conf to full map
    $allPairs = flatten_current_conf($currentConf, $confSchema);

    // Apply posted overrides for our curated settings
    foreach ($gsSections as $sec => $fields) {
        // Skip Narrator section - it's now managed separately
        if ($sec === 'Narrator') {
            continue;
        }
        foreach ($fields as $f) {
            $key = $f['name'];
            $postKey = $key;
            // Ensure posted name uses '@' separators as key already contains that if nested
            if (isset($_POST[$postKey])) {
                $val = $_POST[$postKey];
                if (($f['type'] ?? '') === 'boolean') {
                    $allPairs[$postKey] = ($val === 'true') ? 'true' : 'false';
                } else {
                    $allPairs[$postKey] = $val;
                }
            } else if (($f['type'] ?? '') === 'boolean') {
                // Unchecked checkbox fallback (shouldn't happen due to hidden input)
                $allPairs[$postKey] = 'false';
            }
        }
    }
    // Always keep memory auto-summary enabled even though it is hidden in Global Settings UI.
    $allPairs['FEATURES@MEMORY_EMBEDDING@AUTO_CREATE_SUMMARYS'] = 'true';

    // Apply RELATIONSHIP_SYSTEM_ENABLED (rendered inline with RELLLM_CONNECTOR, not in $gsSections)
    if (isset($_POST['RELATIONSHIP_SYSTEM_ENABLED'])) {
        $allPairs['RELATIONSHIP_SYSTEM_ENABLED'] = ($_POST['RELATIONSHIP_SYSTEM_ENABLED'] === 'true') ? 'true' : 'false';
    } else {
        // Checkbox unchecked - no POST value means false
        $allPairs['RELATIONSHIP_SYSTEM_ENABLED'] = 'false';
    }

    // Apply SCENE_CLASSIFIER_ENABLED (rendered inline with CORE_CONNECTOR_SCENECLASSIFIER, not in $gsSections)
    if (isset($_POST['SCENE_CLASSIFIER_ENABLED'])) {
        $allPairs['SCENE_CLASSIFIER_ENABLED'] = ($_POST['SCENE_CLASSIFIER_ENABLED'] === 'true') ? 'true' : 'false';
    } else {
        $allPairs['SCENE_CLASSIFIER_ENABLED'] = 'false';
    }

    // Apply POWER_AWARENESS_ENABLED
    if (isset($_POST['POWER_AWARENESS_ENABLED'])) {
        $allPairs['POWER_AWARENESS_ENABLED'] = ($_POST['POWER_AWARENESS_ENABLED'] === 'true') ? 'true' : 'false';
    } else {
        // Checkbox unchecked - no POST value means false
        $allPairs['POWER_AWARENESS_ENABLED'] = 'false';
    }

    // Apply OGHMA_CUSTOM (rendered inline with CORE_CONNECTOR_OGHMA_CUSTOM, not in $gsSections)
    if (isset($_POST['OGHMA_CUSTOM'])) {
        $allPairs['OGHMA_CUSTOM'] = ($_POST['OGHMA_CUSTOM'] === 'true') ? 'true' : 'false';
    } else {
        $allPairs['OGHMA_CUSTOM'] = 'false';
    }

	// Apply TTS overrides (selection + provider fields + Player TTS)
	if (isset($_POST['TTSFUNCTION'])) {
		$ttsSel = (string)$_POST['TTSFUNCTION'];
		$allPairs['TTSFUNCTION'] = $ttsSel;
		$ttsKey = $ttsMap[$ttsSel] ?? '';
		$ttsSchema = ($ttsKey && isset($providersTts[$ttsKey]) && is_array($providersTts[$ttsKey])) ? $providersTts[$ttsKey] : [];
		if ($ttsKey && $ttsSchema) {
			foreach ($ttsSchema as $fname => $def) {
				if (!is_array($def)) continue;
				$type = $def['type'] ?? 'string';
				$key = 'TTS@' . $ttsKey . '@' . $fname;
				$postName = 'tts__' . $fname;
				if ($type === 'boolean') {
					$allPairs[$key] = (isset($_POST[$postName]) && $_POST[$postName] === 'true') ? 'true' : 'false';
				} else if ($type === 'selectmultiple') {
					$allPairs[$key] = isset($_POST[$postName]) && is_array($_POST[$postName]) ? array_values($_POST[$postName]) : [];
				} else if (isset($_POST[$postName])) {
					$allPairs[$key] = (string)$_POST[$postName];
				}
			}
		}
	}
	// Apply STT overrides
	if (isset($_POST['STTFUNCTION'])) {
		$sttSel = (string)$_POST['STTFUNCTION'];
		$allPairs['STTFUNCTION'] = $sttSel;
		$sttKey = $sttMap[$sttSel] ?? '';
		$sttSchema = ($sttKey && isset($providersStt[$sttKey]) && is_array($providersStt[$sttKey])) ? $providersStt[$sttKey] : [];
		if ($sttKey && $sttSchema) {
			foreach ($sttSchema as $fname => $def) {
				if (!is_array($def)) continue;
				$type = $def['type'] ?? 'string';
				$key = 'STT@' . $sttKey . '@' . $fname;
				$postName = 'stt__' . $fname;
				if ($type === 'boolean') {
					$allPairs[$key] = (isset($_POST[$postName]) && $_POST[$postName] === 'true') ? 'true' : 'false';
				} else if ($type === 'selectmultiple') {
					$allPairs[$key] = isset($_POST[$postName]) && is_array($_POST[$postName]) ? array_values($_POST[$postName]) : [];
				} else if (isset($_POST[$postName])) {
					$allPairs[$key] = (string)$_POST[$postName];
				}
			}
		}
	}

	// Apply ITT overrides
	if (isset($_POST['ITTFUNCTION'])) {
		$ittSel = (string)$_POST['ITTFUNCTION'];
		$allPairs['ITTFUNCTION'] = $ittSel;
		$ittKey = $ittMap[$ittSel] ?? '';
		$ittSchema = ($ittKey && isset($ittProviders[$ittKey]) && is_array($ittProviders[$ittKey])) ? $ittProviders[$ittKey] : [];
		if ($ittKey && $ittSchema) {
			foreach ($ittSchema as $fname => $def) {
				if (!is_array($def)) continue;
				$type = $def['type'] ?? 'string';
				$key = 'ITT@' . $ittKey . '@' . $fname;
				$postName = 'itt__' . $fname;
				if ($type === 'boolean') {
					$allPairs[$key] = (isset($_POST[$postName]) && $_POST[$postName] === 'true') ? 'true' : 'false';
				} else if ($type === 'selectmultiple') {
					$allPairs[$key] = isset($_POST[$postName]) && is_array($_POST[$postName]) ? array_values($_POST[$postName]) : [];
				} else if (isset($_POST[$postName])) {
					$allPairs[$key] = (string)$_POST[$postName];
				}
            }
        }
    }

    // Build and write buffer to default conf.php (always default profile)
    $buffer = build_conf_php_from_pairs($allPairs, $confSchema);
    $target = $enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php";
	$tmpTarget = $target . '.tmp.' . getmypid() . '.' . str_replace('.', '_', (string)microtime(true));
	$result = @file_put_contents($tmpTarget, $buffer, LOCK_EX);
    $saveSuccess = $result !== false;
    if ($saveSuccess) {
		$moved = @rename($tmpTarget, $target);
		if (!$moved) { $moved = (@copy($tmpTarget, $target) && @unlink($tmpTarget)); }
		$saveSuccess = $moved;
	}
	if ($saveSuccess) {
		@clearstatcache(true, $target);
		if (function_exists('opcache_invalidate')) { @opcache_invalidate($target, true); }
        Logger::info("Global settings saved to conf.php by UI");
		while (ob_get_level() > 0) { @ob_end_clean(); }
		$redirectUrl = strtok($_SERVER['REQUEST_URI'], '?') . '?_ts=' . time();
		header("Location: " . $redirectUrl);
		exit;
    } else {
        Logger::error("Failed writing conf.php from Global Settings UI");
		// Reload current conf after failed save to keep UI consistent
    $currentConf = conf_loader_load();
    }
}

// Helper: get current value by field name in our curated list
function current_value(string $flatName, array $currentConf) {
    $plain = strtr($flatName, ["@" => " "]);
    $parms = $currentConf[$plain] ?? null;
    if (!$parms) return '';
    return $parms['currentValue'] ?? '';
}

function render_tts_grouped_options(array $options, string $selectedValue, array $displayNames, bool $includeDisabledGroup = false): void {
    $recommended = ['pockettts', 'chatterbox', 'xtts-fastapi', 'inworld'];
    $deprecated = ['mimic3', 'azure', 'deepgram', 'koboldcpp', 'kokoro'];
    $disabled = $includeDisabledGroup ? array_values(array_filter($options, function($opt) {
        return strtolower((string)$opt) === 'none';
    })) : [];
    $others = array_values(array_filter($options, function($opt) use ($recommended, $deprecated, $includeDisabledGroup) {
        $value = strtolower((string)$opt);
        if ($includeDisabledGroup && $value === 'none') {
            return false;
        }
        return !in_array($opt, $recommended, true) && !in_array($opt, $deprecated, true);
    }));
    $renderOption = function($opt) use ($selectedValue, $displayNames) {
        $selected = ((string)$selectedValue === (string)$opt) ? ' selected' : '';
        echo '<option value="' . htmlspecialchars((string)$opt) . '"' . $selected . '>' . htmlspecialchars($displayNames[$opt] ?? $opt) . '</option>';
    };

    if (!empty($disabled)) {
        echo '<optgroup label="— Disabled —">';
        foreach ($disabled as $opt) {
            $renderOption($opt);
        }
        echo '</optgroup>';
    }

    $recommendedAvailable = array_values(array_filter($recommended, function($opt) use ($options) {
        return in_array($opt, $options, true);
    }));
    if (!empty($recommendedAvailable)) {
        echo '<optgroup label="— Recommended —">';
        foreach ($recommendedAvailable as $opt) {
            $renderOption($opt);
        }
        echo '</optgroup>';
    }

    if (!empty($others)) {
        echo '<optgroup label="— Others —">';
        foreach ($others as $opt) {
            $renderOption($opt);
        }
        echo '</optgroup>';
    }

    $deprecatedAvailable = array_values(array_filter($deprecated, function($opt) use ($options) {
        return in_array($opt, $options, true);
    }));
    if (!empty($deprecatedAvailable)) {
        echo '<optgroup label="— Deprecated —">';
        foreach ($deprecatedAvailable as $opt) {
            $renderOption($opt);
        }
        echo '</optgroup>';
    }
}
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css?v=gs1">
<style>
    /* Match api_badge (oghma) layout and colors */
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
    .page-header-actions .tab-buttons {
        margin-top: 0;
        justify-content: flex-start;
    }

    .content-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 30px;
        margin-bottom: 30px;
    }
    /* Stobe-like horizontal section layout for General tab sections */
    .global-sections-horizontal {
        grid-template-columns: repeat(4, minmax(260px, 1fr));
        gap: 14px;
        margin-bottom: 24px;
    }
    .global-sections-horizontal .content-section {
        padding: 14px;
    }
    .global-sections-horizontal .content-section h2 {
        margin-bottom: 12px;
        padding-bottom: 8px;
        font-size: 1.18em;
    }
    .global-sections-horizontal .provider-grid {
        gap: 8px;
    }
    .global-sections-horizontal .provider-card {
        padding: 10px;
    }
    .global-sections-horizontal .provider-head {
        margin-bottom: 5px;
    }
    .global-sections-horizontal .provider-icon {
        width: 24px;
        height: 24px;
        font-size: 14px;
    }
    .global-sections-horizontal .provider-body input[type="text"],
    .global-sections-horizontal .provider-body input[type="url"],
    .global-sections-horizontal .provider-body input[type="number"],
    .global-sections-horizontal .provider-body input[type="password"],
    .global-sections-horizontal .provider-body select,
    .global-sections-horizontal .provider-body textarea {
        padding: 8px 10px;
    }
    @media (max-width: 1700px) {
        .global-sections-horizontal {
            grid-template-columns: repeat(2, minmax(260px, 1fr));
        }
    }
    @media (max-width: 1000px) {
        .global-sections-horizontal {
            grid-template-columns: 1fr;
        }
    }
    .content-section {
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        padding: 22px;
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15),
                    inset 0 1px rgba(255, 255, 255, 0.03);
        transition: border-color 0.2s ease;
    }
    .content-section:hover {
        border-color: #4a4a4a;
    }
    .content-section h2 { 
        font-family: 'MagicCards', serif; 
        color: rgb(242,124,17); 
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5); 
        word-spacing: 6px; 
        margin-bottom: 18px; 
        font-size: 1.35em; 
        padding-bottom: 12px;
        border-bottom: 1px solid rgba(242, 124, 17, 0.2);
    }
    .provider-grid { display:grid; grid-template-columns: 1fr; gap:12px; align-items:start; }
    .provider-subsection-title {
        grid-column: 1 / -1;
        margin: 8px 0 2px;
        padding: 4px 2px 8px;
        font-family: 'MagicCards', serif;
        color: rgb(242,124,17);
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        letter-spacing: 0.3px;
        border-bottom: 1px solid rgba(242, 124, 17, 0.2);
        font-size: 1.05em;
    }
    .provider-card { 
        background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.95)); 
        border: 1px solid #3a3a3a; 
        border-radius: 8px; 
        padding: 14px; 
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15),
                    inset 0 1px rgba(255, 255, 255, 0.02);
        transition: all 0.2s ease;
    }
    .provider-card:hover {
        border-color: #4a4a4a;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2),
                    inset 0 1px rgba(255, 255, 255, 0.03);
    }
    .provider-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px; }
    .provider-title { display:flex; align-items:center; gap:10px; color:#e0e0e0; }
    .provider-icon { 
        width: 30px; 
        height: 30px; 
        border-radius: 6px; 
        background: linear-gradient(135deg, rgba(58, 58, 58, 0.9), rgba(48, 48, 48, 0.9)); 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        font-size: 17px; 
        box-shadow: inset 0 1px rgba(255, 255, 255, 0.05);
    }
    .provider-body { display:flex; gap:8px; align-items:center; }
    .provider-body.grid { display:grid; grid-template-columns: 1fr; gap:8px; align-items:start; }
    .provider-body.grid .help { margin-top:6px; color:#bbb; font-size:12px; }
    .provider-body input[type="text"], .provider-body input[type="url"], .provider-body input[type="number"], .provider-body input[type="password"], .provider-body select, .provider-body textarea { 
        flex: 1; 
        background-color: rgba(26, 26, 26, 0.8); 
        color: #e9efff; 
        border: 1px solid #3a3a3a; 
        border-radius: 6px; 
        padding: 10px 12px; 
        transition: all 0.2s ease;
    }
    .provider-body .provider-field-wrap {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
        min-width: 0;
        width: 100%;
    }
    .provider-body input:focus, .provider-body select:focus, .provider-body textarea:focus {
        border-color: rgba(242, 124, 17, 0.5);
        outline: none;
        box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
    }
    .actions { display:flex; justify-content:flex-end; margin-top:10px; }
    .btn-primary { 
        background: linear-gradient(135deg, #204e7a, #1a3d5f); 
        color: #fff; 
        border: 1px solid rgba(138,155,182,0.4); 
        border-radius: 8px; 
        padding: 10px 16px; 
        cursor: pointer; 
        transition: all 0.2s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }
    .btn-primary:hover { 
        background: linear-gradient(135deg, #285c8f, #204e7a); 
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    }

    @media (max-width: 900px) {
        main { padding-left: 5%; padding-right: 5%; }
        .content-grid { grid-template-columns: 1fr; }
        .provider-grid { grid-template-columns: 1fr; }
        .page-header-row { align-items: flex-start; }
        .page-header-actions { margin-left: 0; width: 100%; }
    }

    /* Global Settings: enhance native boolean checkboxes (e.g., PLAYER_RESPEECH) */
    .provider-card .provider-body input[type="checkbox"] {
        accent-color: #176529 !important;
        transform: scale(1.4) !important;
        transform-origin: center;
        margin: 0 !important;
        flex: 0 0 auto;
        cursor: pointer;
        vertical-align: middle;
    }
    .provider-card .provider-body input[type="checkbox"]:focus-visible {
        outline: 2px solid rgba(23, 101, 41, 0.6);
        outline-offset: 2px;
    }
    .provider-card .provider-body { justify-content: flex-start; }
    .provider-card .provider-body > label {
        min-width: unset;
        width: auto;
        display: inline-block;
        margin-right: 8px;
    }

    /* Ensure PLAYER_RESPEECH specifically is styled, even if other rules miss */
    .provider-card .provider-body input[type="checkbox"][name="PLAYER_RESPEECH"] {
        accent-color: #176529 !important;
        transform: scale(1.6) !important;
        transform-origin: center;
        zoom: 1.2; /* Chromium fallback for some environments */
    }

    /* Header toggle placement and spacing */
    .provider-title .provider-toggle { margin-left: 10px; display:flex; align-items:center; }
    .provider-title .provider-toggle input[type="checkbox"] {
        accent-color:#176529; transform: scale(1.8); transform-origin:center; cursor:pointer;
    }

\\
    .tab-buttons { 
        display: flex; 
        gap: 10px; 
        flex-wrap: wrap; 
        margin-top: 8px; 
        justify-content: center; 
        align-items: center; 
    }
    .tab-button { 
        background: rgba(26, 41, 64, 0.8); 
        color: #cfd8e3; 
        border: 1px solid rgba(138,155,182,0.35); 
        padding: 8px 16px; 
        border-radius: 8px; 
        cursor: pointer; 
        transition: all 0.2s ease;
        font-weight: 600;
    }
    .tab-button:hover { 
        background: rgba(32, 53, 83, 0.9); 
        transform: translateY(-1px);
    }
    .tab-button.active { 
        background: linear-gradient(135deg, rgba(242, 124, 17, 0.2), rgba(242, 124, 17, 0.1)); 
        color: rgb(242, 124, 17); 
        border-color: rgba(242, 124, 17, 0.5); 
        box-shadow: inset 0 -2px 0 rgb(242, 124, 17);
        font-weight: 700;
    }
    .btn-save-green { 
        background: linear-gradient(135deg, rgba(32, 122, 74, 0.9), rgba(23, 101, 57, 0.9));
        color: #fff;
        border: 1px solid rgba(72, 187, 120, 0.3);
        border-radius: 8px;
        padding: 10px 20px;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        font-weight: 700;
        font-size: 14px;
    }
    .btn-save-green:hover { 
        background: linear-gradient(135deg, rgba(42, 142, 94, 0.95), rgba(32, 122, 74, 0.95)); 
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(32, 122, 74, 0.3);
        border-color: rgba(72, 187, 120, 0.5);
    }
</style>

<main>
    <div class="page-header">
        <div class="page-header-row">
            <h1 class="gs-title">Global Settings</h1>
            <div class="page-header-actions">
                <button type="submit" class="btn-save-green" name="save_all" value="1" form="gs_form">Save All</button>
                <div class="tab-buttons">
                <button type="button" class="tab-button active" data-gs-tab="tab-global">🌐General</button>
                <button type="button" class="tab-button" data-gs-tab="tab-tts">🔊TTS</button>
                <button type="button" class="tab-button" data-gs-tab="tab-stt">🎤STT</button>
                <button type="button" class="tab-button" data-gs-tab="tab-itt">🖼️ITT</button>
                </div>
            </div>
        </div>
    </div>
    
    <div id="toast" class="toast-notification" style="display:none;"><span class="message"></span></div>

    <?php if ($saveSuccess): ?>
        <script>setTimeout(function(){ try{ const t=document.getElementById('toast'); if(t){ t.style.display='block'; t.textContent='Settings saved to conf.php'; setTimeout(()=>{ t.style.display='none'; }, 2500); } }catch(_e){} }, 50);</script>
    <?php endif; ?>

    <form method="post" action="" id="gs_form">
        <input type="hidden" name="gs_tab" id="gs_tab" value="<?php echo htmlspecialchars($activeTab); ?>">
        <div class="content-grid global-sections-horizontal" id="tab-global">
            <?php foreach ($gsSections as $sectionTitle => $fields): ?>
                <div class="content-section">
                    <h2><?php echo htmlspecialchars($sectionTitle); ?></h2>
                    <div class="provider-grid">
                        <?php $lastSubsection = null; ?>
                        <?php foreach ($fields as $f): ?>
                            <?php
                                $subsection = isset($f['subsection']) ? (string)$f['subsection'] : null;
                                if ($subsection !== $lastSubsection) {
                                    if (!empty($subsection)) {
                                        echo '<div class="provider-subsection-title">' . htmlspecialchars($subsection) . '</div>';
                                    }
                                    $lastSubsection = $subsection;
                                }
                                $fname = $f['name'];
                                $ftype = $f['type'];
                                $current = current_value($fname, $currentConf);
                                $label = pretty_label($fname);
                                $help = $gsDesc($fname);
                                $isReadonly = isset($confSchema[$fname]['readonly']) && $confSchema[$fname]['readonly'] === true;
                                $readonlyAttr = $isReadonly ? 'readonly' : '';
                                if ($fname === 'PLAYER_NAME') { continue; }
                            ?>
                            <div class="provider-card">
                                <div class="provider-head">
                                    <div class="provider-title">
                                        <div class="provider-icon"><?php echo icon_for_field($fname); ?></div>
                                        <div><?php echo htmlspecialchars($label); ?></div>
                                        <?php if ($ftype === 'boolean'): ?>
                                            <div class="provider-toggle">
                                                <input type="hidden" name="<?php echo htmlspecialchars($fname); ?>" value="false">
                                                <input type="checkbox" value="true" name="<?php echo htmlspecialchars($fname); ?>" <?php echo ($current ? 'checked' : ''); ?> <?php echo $isReadonly ? 'disabled' : ''; ?> style="width:auto;">
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($fname === 'RELLLM_CONNECTOR'): ?>
                                            <div class="provider-toggle">
                                                <input type="hidden" name="RELATIONSHIP_SYSTEM_ENABLED" value="false">
                                                <input type="checkbox" name="RELATIONSHIP_SYSTEM_ENABLED" value="true" <?php echo (current_value('RELATIONSHIP_SYSTEM_ENABLED', $currentConf) ? 'checked' : ''); ?> style="width:auto;" title="Enable/Disable Relationship System">
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($fname === 'CORE_CONNECTOR_SCENECLASSIFIER'): ?>
                                            <div class="provider-toggle">
                                                <input type="hidden" name="SCENE_CLASSIFIER_ENABLED" value="false">
                                                <input type="checkbox" name="SCENE_CLASSIFIER_ENABLED" value="true" <?php echo (current_value('SCENE_CLASSIFIER_ENABLED', $currentConf) ? 'checked' : ''); ?> style="width:auto;" title="Enable/Disable Scene Classifier">
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($fname === 'CORE_CONNECTOR_OGHMA_CUSTOM'): ?>
                                            <div class="provider-toggle">
                                                <input type="hidden" name="OGHMA_CUSTOM" value="false">
                                                <input type="checkbox" name="OGHMA_CUSTOM" value="true" <?php echo (current_value('OGHMA_CUSTOM', $currentConf) ? 'checked' : ''); ?> style="width:auto;" title="Enable/Disable Custom Oghma LLM">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="provider-body">
                                    <?php if ($ftype === 'boolean'): ?>
                                        <?php if (isset($f['action']) && $f['action'] === 'clear_reanimation'): ?>
                                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                                <button type="submit" name="clear_reanimation_status" value="1" class="btn-action" style="background:#8b0000; border:1px solid #a52a2a; color:#fff; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:13px;" onclick="return confirm('This will remove the reanimated/zombie status from ALL NPCs in the database. Continue?');">
                                                    🧟 Clear Reanimation Status
                                                </button>
                                                <span style="color:#888; font-size:12px;">Removes zombie flags from all NPCs</span>
                                            </div>
                                            <?php if ($clearReanimationResult !== null): ?>
                                                <div style="margin-top:8px; padding:8px 12px; border-radius:6px; <?php echo $clearReanimationResult['success'] ? 'background:#1a3d1a; color:#90EE90;' : 'background:#3d1a1a; color:#ff6b6b;'; ?>">
                                                    <?php echo htmlspecialchars($clearReanimationResult['message']); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <!-- Boolean rendered in header next to title -->
                                        <?php endif; ?>
                                    <?php elseif ($ftype === 'integer'): ?>
                                        <?php $min = isset($f['min']) ? (int)$f['min'] : null; $max = isset($f['max']) ? (int)$f['max'] : null; ?>
                                        <input type="number" name="<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>" <?php echo ($min!==null?('min="'.$min.'"'):''); ?> <?php echo ($max!==null?('max="'.$max.'"'):''); ?> step="1" <?php echo $readonlyAttr; ?>>
                                    <?php elseif ($ftype === 'number'): ?>
                                        <input type="number" step="0.01" name="<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>" <?php echo $readonlyAttr; ?>>
                                    <?php elseif ($ftype === 'longstring'): ?>
                                        <textarea name="<?php echo htmlspecialchars($fname); ?>" rows="4" <?php echo $readonlyAttr; ?>><?php echo htmlspecialchars((string)$current); ?></textarea>
                                    <?php elseif ($ftype === 'url'): ?>
                                        <input type="url" name="<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>" <?php echo $readonlyAttr; ?>>
                                    <?php elseif ($ftype === 'apikey'): ?>
                                        <input type="password" name="<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>" placeholder="Paste API key" <?php echo $readonlyAttr; ?>>
                                    <?php elseif ($ftype === 'select'): ?>
                                        <?php $values = $f['values'] ?? []; ?>
                                        <select name="<?php echo htmlspecialchars($fname); ?>" <?php echo $isReadonly ? 'disabled' : ''; ?>>
                                            <?php foreach ($values as $opt): ?>
                                                <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ((string)$current===(string)$opt?'selected':''); ?>><?php echo htmlspecialchars($opt); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif (strpos($ftype, 'foreign:') === 0): ?>
                                        <?php $rows = $foreignOptions[$fname] ?? []; ?>
                                        <div class="provider-field-wrap">
                                            <select name="<?php echo htmlspecialchars($fname); ?>" <?php echo $isReadonly ? 'disabled' : ''; ?>>
                                                <option value="" <?php echo (empty($current) ? 'selected' : ''); ?>>None</option>
                                                <?php foreach ($rows as $row): ?>
                                                    <?php $idCol = explode(':', $ftype)[2]; $labelCol = explode(':', $ftype)[3]; ?>
                                                    <option value="<?php echo htmlspecialchars($row[$idCol]); ?>" <?php echo ((string)$current===(string)$row[$idCol]?'selected':''); ?>><?php echo htmlspecialchars($row[$labelCol]); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php else: ?>
                                        <input type="text" name="<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>" <?php echo $readonlyAttr; ?>>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($help)): ?>
                                    <div style="margin-top:6px; color:#bbb; font-size:12px;"><?php echo $help; ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="content-section" id="tab-tts" style="display:none;">
            <h2>Text-to-Speech</h2>
            <div class="provider-grid">
                <div class="provider-card">
                    <div class="provider-head">
                        <div class="provider-title">
                            <div class="provider-icon">🔊</div>
                            <div>TTS Provider</div>
                        </div>
                    </div>
                    <div class="provider-body grid">
                        <label for="TTSFUNCTION">TTS Selection</label>
                        <select name="TTSFUNCTION" id="TTSFUNCTION" onchange="document.getElementById('gs_tab').value='tab-tts'; this.form.submit()">
                            <?php render_tts_grouped_options($ttsOptions, $ttsSelRender, $ttsDisplayNames); ?>
                        </select>
                        
                        <div></div>
                        <div class="help">
                            <?php
                            $ttsDescMap = [
                                'melotts' => "[Skyrim Voices] MeloTTS runs locally installed via DwemerDistro. It's fast and free, but low quality voices. Under 1GB of VRAM.",
                                'xtts-fastapi' => "[Skyrim Voices] XTTS runs locally and generates cloned voices from samples. Great for immersive, consistent character voices. Uses roughly 4GB of VRAM. Best for NVIDIA GPUs.",
                                'chatterbox' => "[Skyrim Voices] Chatterbox is an optimized fork of XTTS with faster inference. Generates cloned voices from samples. Uses roughly 4GB of VRAM. Best for NVIDIA GPUs.",
                                'pockettts' => "[Skyrim Voices] PocketTTS is a CPU-based TTS engine that generates cloned voices from samples. Perfect for AMD systems or CPU-only setups. No GPU required.",
                                'mimic3' => "Mimic3 is a very basic LLM installed in DwemerDistro. It's fast and free, but low quality custom voices. Under 1GB of VRAM.",
                                'xvasynth' => "[Skyrim Voices] xVASynth uses pre-trained game voices. Good fit for Skyrim-style character voices and mod voicepacks.",
                                'azure' => "Azure TTS offers decent voices with emotion control. Requires Azure subscription and API key.",
                                '11labs' => "ElevenLabs provides realistic, emotive voices. Requires manual generation of voices. Requires API key and credits.",
                                'openai' => "OpenAI TTS supports a limited amount of decent quality voices. Requires API key.",
                                'kokoro' => "KOKORO is a lightweight TTS. Useful when you need a simple, fast voice without complex configs.",
                                'koboldcpp' => "KoboldCPP TTS routes to a local service. Use if you maintain a custom local TTS pipeline.",
                                'zonos_gradio' => "Zonos TTS provides expressive voices with emotion controls. Recommended to use with cloud GPU hosting (Vast.ai). Uses roughly 6GB of VRAM.",
                                'piper-tts' => "[Skyrim Voices]Piper-TTS is a middle quality and fast TTS. Requires manual installation of voices though. Under 1GB of VRAM. https://dwemerdynamics.hostwiki.io/en/TTS-Options",
                                'deepgram' => "Deepgram TTS is a cloud option aimed at simple, quick voice generation. Requires API key.",
                                'cartesia' => "[Skyrim Voices] Cartesia TTS provides high-quality automatic voice generation. Supports emotions and multiple languages. Requires API key.",
                                'inworld' => "[Skyrim Voices] Inworld TTS provides high-quality automatic voice generation. Requires API credential (Base64) and workspace ID."
                            ];
                            $ttsLower = strtolower((string)$ttsSelRender);
                            echo htmlspecialchars($ttsDescMap[$ttsLower] ?? '');
                            ?>
                        </div>
                    </div>
                </div>
                <?php $ttsKeyCur = $ttsMap[$ttsSelRender] ?? ''; $ttsSchemaCur = ($ttsKeyCur && isset($providersTts[$ttsKeyCur]) && is_array($providersTts[$ttsKeyCur])) ? $providersTts[$ttsKeyCur] : []; $HOST_IP=''; $WSL_IP=''; if ($ttsKeyCur==='XVASYNTH' || $ttsKeyCur==='XTTSFASTAPI' || $ttsKeyCur==='CHATTERBOX' || $ttsKeyCur==='POCKETTTS'){ try { if (!isset($GLOBALS['db']) || !$GLOBALS['db']) { @include_once($enginePath.'conf'.DIRECTORY_SEPARATOR.'conf.php'); if (isset($GLOBALS['DBDRIVER'])) { @require_once($enginePath.'lib'.DIRECTORY_SEPARATOR.$GLOBALS['DBDRIVER'].'.class.php'); } $GLOBALS['db'] = new sql(); } $row = $GLOBALS['db']->fetchOne("SELECT value FROM conf_opts WHERE id='Network/HOST_IP' LIMIT 1"); if (is_array($row) && isset($row['value'])) { $HOST_IP = (string)$row['value']; } $row2 = $GLOBALS['db']->fetchOne("SELECT value FROM conf_opts WHERE id='Network/WSL_IP' LIMIT 1"); if (is_array($row2) && isset($row2['value'])) { $WSL_IP = (string)$row2['value']; } } catch (Throwable $_e) { $HOST_IP=''; $WSL_IP=''; } } ?>
                <?php if (!empty($ttsSchemaCur)): ?>
                <div class="provider-card">
                    <div class="provider-head">
                        <div class="provider-title">
                            <div class="provider-icon">⚙️</div>
                            <div><?php echo htmlspecialchars($ttsKeyCur); ?> Settings</div>
                        </div>
                    </div>
                    <div class="provider-body grid">
                        <?php
                        // API badge status (once)
                        $apiBadges = [];
                        try {
                            if (!isset($GLOBALS['db']) || !$GLOBALS['db']) {
                                @include_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
                                if (isset($GLOBALS["DBDRIVER"])) { @require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . $GLOBALS["DBDRIVER"] . ".class.php"); }
                                $GLOBALS['db'] = new sql();
                            }
                            $apiBadges = $GLOBALS['db']->fetchAll("SELECT id,label,api_key FROM core_api_badge ORDER BY label ASC");
                        } catch (Throwable $_e) {}
                        foreach ($ttsSchemaCur as $fname => $def): if (!is_array($def)) continue; $ftype=$def['type']??'string'; $plain='TTS '.$ttsKeyCur.' '.$fname; $current=$currentConf[$plain]['currentValue']??''; $help=$def['description']??''; $lname=strtolower($fname); $lnameNorm=str_replace(['_','-'],'',$lname); if ($lnameNorm==='voiceid' || $lnameNorm==='voicelogic') continue; if ($ttsKeyCur==='XVASYNTH' && $lname==='model') continue; if (strtolower($ttsKeyCur)==='openai' && $lname==='voice') continue; if (strpos($fname, 'PARALINGUISTIC_TAGS') === 0) continue; 
                            // API KEY badge handling for known providers
                            $provLower = strtolower($ttsKeyCur);
                            if ($fname === 'API_KEY' && in_array($provLower, ['azure','eleven_labs','openai','deepgram','cartesia','inworld'])) {
                                $badgeName = ($provLower==='eleven_labs') ? 'ElevenLabs' : ucfirst($provLower);
                                $hasKey=false; foreach ($apiBadges as $r){ if (strtolower((string)($r['label']??''))===strtolower($badgeName) && trim((string)($r['api_key']??''))!==''){ $hasKey=true; break; } }
                                echo '<div>API Badge ('.htmlspecialchars($badgeName).')</div>';
                                echo '<div>'.($hasKey?'<span style="color:#6dd19c">Configured</span>':'<span style="color:#ffb862">Missing</span>').' — <a href="#" onclick="try{ if(window.top){ window.top.location.href=\''.htmlspecialchars($webRoot).'/ui/core/config_hub.php?tab=keys\'; } else { window.location.href=\''.htmlspecialchars($webRoot).'/ui/core/api_badge.php?embed=1\'; } }catch(e){ window.location.href=\''.htmlspecialchars($webRoot).'/ui/core/api_badge.php?embed=1\'; } return false;">Manage Keys</a></div>';
                                if (!empty($help)) echo '<div class="help">'.$help.'</div>';
                                continue;
                            }
                        ?>
                            <label for="tts_<?php echo htmlspecialchars($fname); ?>"><?php echo htmlspecialchars(format_field_label($fname)); ?></label>
                            <?php if ($ftype==='boolean'): ?>
                                <input type="hidden" name="tts__<?php echo htmlspecialchars($fname); ?>" value="false">
                                <input type="checkbox" id="tts_<?php echo htmlspecialchars($fname); ?>" name="tts__<?php echo htmlspecialchars($fname); ?>" value="true" <?php echo ($current?'checked':''); ?> style="width:auto;">
                            <?php elseif ($ftype==='integer'): ?>
                                <input type="number" step="1" id="tts_<?php echo htmlspecialchars($fname); ?>" name="tts__<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>">
                            <?php elseif ($ftype==='number'): ?>
                                <input type="number" step="0.01" id="tts_<?php echo htmlspecialchars($fname); ?>" name="tts__<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>">
                            <?php elseif ($ftype==='longstring'): ?>
                                <textarea id="tts_<?php echo htmlspecialchars($fname); ?>" name="tts__<?php echo htmlspecialchars($fname); ?>" rows="3"><?php echo htmlspecialchars((string)$current); ?></textarea>
                            <?php elseif ($ftype==='url'): ?>
                                <input type="url" id="tts_<?php echo htmlspecialchars($fname); ?>" name="tts__<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>">
                                <div style="margin-top:6px;">
                                    <button type="button" class="btn-primary" style="padding: 6px 12px; background-color: rgba(37, 99, 235, 0.8); color: #ffffff; border: 1px solid rgba(138, 155, 182, 0.3); border-radius: 8px; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s ease-in-out; font-weight: 500; letter-spacing: 0.3px; backdrop-filter: blur(5px); box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2), inset 0 1px rgba(255, 255, 255, 0.1); margin-right: 5px;" onclick="checkUrlFromServer('tts__<?php echo htmlspecialchars($fname); ?>')" onmouseover="this.style.backgroundColor='rgba(47, 109, 245, 0.9)'; this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(0, 0, 0, 0.3), inset 0 1px rgba(255, 255, 255, 0.15)';" onmouseout="this.style.backgroundColor='rgba(37, 99, 235, 0.8)'; this.style.transform='none'; this.style.boxShadow='0 2px 6px rgba(0, 0, 0, 0.2), inset 0 1px rgba(255, 255, 255, 0.1)';">Check Connection</button>
                                    <?php if ($ttsKeyCur==='XVASYNTH'): ?>
                                        <button type="button" id="btn_host_ip_xvasynth" class="btn-primary" data-ip="<?php echo htmlspecialchars($HOST_IP); ?>">Host PC IP</button>
                                        <script>(function(){ try{ var b=document.getElementById('btn_host_ip_xvasynth'); var inp=document.getElementById('tts_url'); if(b && inp){ b.addEventListener('click', function(){ var ip=(b.getAttribute('data-ip')||'').trim(); if(!ip){ try{ alert('Host IP not set. Configure Network/HOST_IP in Settings.'); }catch(_){} return; } var v='http://'+ip+':8008'; inp.value=v; try{ inp.dispatchEvent(new Event('input', { bubbles:true })); }catch(_){} try{ inp.dispatchEvent(new Event('change', { bubbles:true })); }catch(_){} }); } }catch(_e){} })();</script>
                                    <?php elseif ($ttsKeyCur==='XTTSFASTAPI' && strtolower($fname)==='endpoint'): ?>
                                        <button type="button" id="btn_host_ip_xtts" class="btn-primary" data-ip="<?php echo htmlspecialchars($HOST_IP); ?>">Host PC IP</button>
                                        <button type="button" id="btn_wsl_ip_xtts" class="btn-primary" data-ip="<?php echo htmlspecialchars($WSL_IP); ?>">WSL IP</button>
                                        <script>(function(){ try{ var bh=document.getElementById('btn_host_ip_xtts'); var bw=document.getElementById('btn_wsl_ip_xtts'); var inp=document.getElementById('tts_endpoint'); function setHost(ip){ if(!ip){ try{ alert('Host IP not set. Configure Network/HOST_IP in Settings.'); }catch(_){} return; } try{ var u = new URL(inp.value||('http://'+ip+':8020')); u.protocol = 'http:'; u.hostname = ip; u.port = '8020'; inp.value = u.toString(); } catch(e){ inp.value = 'http://'+ip+':8020'; } try{ inp.dispatchEvent(new Event('input', { bubbles:true })); }catch(_){} try{ inp.dispatchEvent(new Event('change', { bubbles:true })); }catch(_){} }
                                        function setWsl(ip){ if(!ip){ try{ alert('WSL IP not set. Configure Network/WSL_IP in Settings.'); }catch(_){} return; } try{ var u = new URL(inp.value||('http://'+ip+':8020')); u.protocol='http:'; u.hostname=ip; u.port='8020'; inp.value = u.toString(); } catch(e){ inp.value = 'http://'+ip+':8020'; } try{ inp.dispatchEvent(new Event('input', { bubbles:true })); }catch(_){} try{ inp.dispatchEvent(new Event('change', { bubbles:true })); }catch(_){} }
                                        if(bh && inp){ bh.addEventListener('click', function(){ setHost((bh.getAttribute('data-ip')||'').trim()); }); }
                                        if(bw && inp){ bw.addEventListener('click', function(){ setWsl((bw.getAttribute('data-ip')||'').trim()); }); }
                                        }catch(_e){} })();</script>
                                    <?php elseif ($ttsKeyCur==='CHATTERBOX' && strtolower($fname)==='endpoint'): ?>
                                        <button type="button" id="btn_host_ip_chatterbox" class="btn-primary" data-ip="<?php echo htmlspecialchars($HOST_IP); ?>">Host PC IP</button>
                                        <button type="button" id="btn_wsl_ip_chatterbox" class="btn-primary" data-ip="<?php echo htmlspecialchars($WSL_IP); ?>">WSL IP</button>
                                        <script>(function(){ try{ var bh=document.getElementById('btn_host_ip_chatterbox'); var bw=document.getElementById('btn_wsl_ip_chatterbox'); var inp=document.getElementById('tts_endpoint'); function setHost(ip){ if(!ip){ try{ alert('Host IP not set. Configure Network/HOST_IP in Settings.'); }catch(_){} return; } try{ var u = new URL(inp.value||('http://'+ip+':8020')); u.protocol = 'http:'; u.hostname = ip; u.port = '8020'; inp.value = u.toString(); } catch(e){ inp.value = 'http://'+ip+':8020'; } try{ inp.dispatchEvent(new Event('input', { bubbles:true })); }catch(_){} try{ inp.dispatchEvent(new Event('change', { bubbles:true })); }catch(_){} }
                                        function setWsl(ip){ if(!ip){ try{ alert('WSL IP not set. Configure Network/WSL_IP in Settings.'); }catch(_){} return; } try{ var u = new URL(inp.value||('http://'+ip+':8020')); u.protocol='http:'; u.hostname=ip; u.port='8020'; inp.value = u.toString(); } catch(e){ inp.value = 'http://'+ip+':8020'; } try{ inp.dispatchEvent(new Event('input', { bubbles:true })); }catch(_){} try{ inp.dispatchEvent(new Event('change', { bubbles:true })); }catch(_){} }
                                        if(bh && inp){ bh.addEventListener('click', function(){ setHost((bh.getAttribute('data-ip')||'').trim()); }); }
                                        if(bw && inp){ bw.addEventListener('click', function(){ setWsl((bw.getAttribute('data-ip')||'').trim()); }); }
                                        }catch(_e){} })();</script>
                                    <?php elseif ($ttsKeyCur==='POCKETTTS' && strtolower($fname)==='endpoint'): ?>
                                        <button type="button" id="btn_host_ip_pockettts" class="btn-primary" data-ip="<?php echo htmlspecialchars($HOST_IP); ?>">Host PC IP</button>
                                        <button type="button" id="btn_wsl_ip_pockettts" class="btn-primary" data-ip="<?php echo htmlspecialchars($WSL_IP); ?>">WSL IP</button>
                                        <script>(function(){ try{ var bh=document.getElementById('btn_host_ip_pockettts'); var bw=document.getElementById('btn_wsl_ip_pockettts'); var inp=document.getElementById('tts_endpoint'); function setHost(ip){ if(!ip){ try{ alert('Host IP not set. Configure Network/HOST_IP in Settings.'); }catch(_){} return; } try{ var u = new URL(inp.value||('http://'+ip+':8020')); u.protocol = 'http:'; u.hostname = ip; u.port = '8020'; inp.value = u.toString(); } catch(e){ inp.value = 'http://'+ip+':8020'; } try{ inp.dispatchEvent(new Event('input', { bubbles:true })); }catch(_){} try{ inp.dispatchEvent(new Event('change', { bubbles:true })); }catch(_){} }
                                        function setWsl(ip){ if(!ip){ try{ alert('WSL IP not set. Configure Network/WSL_IP in Settings.'); }catch(_){} return; } try{ var u = new URL(inp.value||('http://'+ip+':8020')); u.protocol='http:'; u.hostname=ip; u.port='8020'; inp.value = u.toString(); } catch(e){ inp.value = 'http://'+ip+':8020'; } try{ inp.dispatchEvent(new Event('input', { bubbles:true })); }catch(_){} try{ inp.dispatchEvent(new Event('change', { bubbles:true })); }catch(_){} }
                                        if(bh && inp){ bh.addEventListener('click', function(){ setHost((bh.getAttribute('data-ip')||'').trim()); }); }
                                        if(bw && inp){ bw.addEventListener('click', function(){ setWsl((bw.getAttribute('data-ip')||'').trim()); }); }
                                        }catch(_e){} })();</script>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($ftype==='select'): $values=$def['values']??[]; ?>
                                <select id="tts_<?php echo htmlspecialchars($fname); ?>" name="tts__<?php echo htmlspecialchars($fname); ?>">
                                    <?php foreach ($values as $opt): ?>
                                        <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ((string)$current===(string)$opt?'selected':''); ?>><?php echo htmlspecialchars($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($ftype==='apikey'): ?>
                                <input type="password" id="tts_<?php echo htmlspecialchars($fname); ?>" name="tts__<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>" placeholder="Paste API key">
                            <?php else: ?>
                                <input type="text" id="tts_<?php echo htmlspecialchars($fname); ?>" name="tts__<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>">
                            <?php endif; ?>
                            <?php if (!empty($help)): ?><div class="help"><?php echo $help; ?></div><?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                    <div class="provider-card"><div class="provider-body"><div></div><div>No settings available for this provider.</div></div></div>
                <?php endif; ?>

                <?php 
                // Check if current TTS provider supports paralinguistic tags
                $hasParalinguisticTags = isset($ttsSchemaCur['PARALINGUISTIC_TAGS_ENABLED']);
                if ($hasParalinguisticTags && $ttsKeyCur === 'CHATTERBOX'): 
                    $paraEnabled = current_value('TTS '.$ttsKeyCur.' PARALINGUISTIC_TAGS_ENABLED', $currentConf);
                    $paraPrompt = (string)current_value('TTS '.$ttsKeyCur.' PARALINGUISTIC_TAGS_PROMPT', $currentConf);
                    $paraTagsList = (string)current_value('TTS '.$ttsKeyCur.' PARALINGUISTIC_TAGS_LIST', $currentConf);
                ?>
                <div class="provider-card">
                    <div class="provider-head">
                        <div class="provider-title">
                            <div class="provider-icon">🎭</div>
                            <div>Paralinguistic Tags</div>
                        </div>
                    </div>
                    <div class="provider-body grid">
                        <label for="tts_PARALINGUISTIC_TAGS_ENABLED">Enable Tags</label>
                        <div>
                            <input type="hidden" name="tts__PARALINGUISTIC_TAGS_ENABLED" value="false">
                            <input type="checkbox" id="tts_PARALINGUISTIC_TAGS_ENABLED" name="tts__PARALINGUISTIC_TAGS_ENABLED" value="true" <?php echo ($paraEnabled?'checked':''); ?> style="width:auto;">
                        </div>
                        <div class="help">Enable paralinguistic tags like [laugh], [sigh], [gasp] for expressive TTS output. When enabled, these tags will be preserved in the TTS output.</div>
                        
                        <label for="tts_PARALINGUISTIC_TAGS_LIST">Tag List</label>
                        <input type="text" id="tts_PARALINGUISTIC_TAGS_LIST" name="tts__PARALINGUISTIC_TAGS_LIST" value="<?php echo htmlspecialchars($paraTagsList); ?>" placeholder="[laugh],[sigh],[gasp],[cough],[chuckle]">
                        <div class="help">Comma-separated list of paralinguistic tags to preserve. Tags are case-insensitive. Example: [laugh],[sigh],[gasp],[cough],[groan],[sniff],[chuckle],[clear throat],[shush]</div>
                        
                        <label for="tts_PARALINGUISTIC_TAGS_PROMPT">Prompt Snippet</label>
                        <textarea id="tts_PARALINGUISTIC_TAGS_PROMPT" name="tts__PARALINGUISTIC_TAGS_PROMPT" rows="4" placeholder="You may use paralinguistic tags in your dialogue: [laugh], [sigh], [gasp], [cough], [chuckle]. Place them within your spoken text for audible effects. Use sparingly for natural immersion."><?php echo htmlspecialchars($paraPrompt); ?></textarea>
                        <div class="help">Prompt snippet instructing the LLM to use paralinguistic tags. This will be added to the system prompt when paralinguistic tags are enabled. Leave empty to disable prompt injection.</div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="provider-card">
                    <div class="provider-head">
                        <div class="provider-title">
                            <div class="provider-icon">INFO</div>
                            <div>TTS Connector Management</div>
                        </div>
                    </div>
                    <div class="provider-body grid">
                        <div style="grid-column: 1 / -1; color: #cfd8e3;">
                            TTS connectors, Player TTS overrides, and the TTS test now live in the dedicated
                            <a href="<?php echo htmlspecialchars($webRoot . '/ui/core/config_hub.php?tab=ttscfg'); ?>" target="_blank" style="color:#ffcc00;">TTS Connectors</a>
                            and
                            <a href="<?php echo htmlspecialchars($webRoot . '/ui/core/config_hub.php?tab=player'); ?>" target="_blank" style="color:#ffcc00;">Player</a>
                            pages.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-section" id="tab-stt" style="display:none;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                <h2 style="margin: 0;">Speech-to-Text</h2>
                <button type="button" id="btn_google_free_stt" class="btn-primary" style="padding: 8px 16px;">Google Free STT</button>
            </div>
            <div class="provider-grid">
                <div class="provider-card">
                    <div class="provider-head">
                        <div class="provider-title">
                            <div class="provider-icon">🎤</div>
                            <div>STT Provider</div>
                        </div>
                    </div>
                    <div class="provider-body grid">
                        <label for="STTFUNCTION">STT Selection</label>
                        <select name="STTFUNCTION" id="STTFUNCTION" onchange="document.getElementById('gs_tab').value='tab-stt'; this.form.submit()">
                            <?php
                            $sttDisplayNames = [
                                'none'        => 'None',
                                'parakeet'    => 'Parakeet',
                                'deepgram'    => 'Deepgram',
                                'inworld'     => 'Inworld',
                                'whisper'     => 'OpenAI Whisper',
                                'localwhisper'=> 'Local Whisper',
                                'azure'       => 'Azure STT',
                                'gemini'      => 'Gemini STT',
                            ];
                            $sttRecommended = ['parakeet', 'deepgram', 'inworld', 'whisper', 'localwhisper'];
                            $sttOthers = array_values(array_filter($sttOptions, function($o) use ($sttRecommended) {
                                return !in_array($o, $sttRecommended, true);
                            }));
                            $renderSttOpt = function($opt) use ($sttSelRender, $sttDisplayNames) {
                                $sel = ((string)$sttSelRender === (string)$opt) ? ' selected' : '';
                                echo '<option value="'.htmlspecialchars($opt).'"'.$sel.'>'.htmlspecialchars($sttDisplayNames[$opt] ?? $opt).'</option>';
                            };
                            echo '<optgroup label="— Recommended —">';
                            foreach ($sttRecommended as $opt) { if (in_array($opt, $sttOptions, true)) $renderSttOpt($opt); }
                            echo '</optgroup>';
                            if (!empty($sttOthers)) {
                                echo '<optgroup label="— Others —">';
                                foreach ($sttOthers as $opt) { $renderSttOpt($opt); }
                                echo '</optgroup>';
                            }
                            ?>
                        </select>
                        
                    </div>
                </div>
                <?php $sttKeyCur = $sttMap[$sttSelRender] ?? ''; $sttSchemaCur = ($sttKeyCur && isset($providersStt[$sttKeyCur]) && is_array($providersStt[$sttKeyCur])) ? $providersStt[$sttKeyCur] : []; ?>
                <?php if (!empty($sttSchemaCur)): ?>
                <div class="provider-card">
                    <div class="provider-head">
                        <div class="provider-title">
                            <div class="provider-icon">⚙️</div>
                            <div><?php echo htmlspecialchars($sttKeyCur); ?> Settings</div>
                        </div>
                    </div>
                    <div class="provider-body grid">
                        <?php
                        $apiBadges = [];
                        try { if (!isset($GLOBALS['db']) || !$GLOBALS['db']) { $GLOBALS['db'] = new sql(); } $apiBadges = $GLOBALS['db']->fetchAll("SELECT id,label,api_key FROM core_api_badge ORDER BY label ASC"); } catch (Throwable $_e) {}
                        foreach ($sttSchemaCur as $fname => $def): if (!is_array($def)) continue; $ftype=$def['type']??'string'; $plain='STT '.$sttKeyCur.' '.$fname; $current=$currentConf[$plain]['currentValue']??''; $help=$def['description']??'';
                            $lnameProv = strtolower($sttKeyCur);
                            $providerBadgeMap = [
                                'whisper' => 'OpenAI',
                                'azure' => 'Azure',
                                'deepgram' => 'Deepgram',
                                'gemini' => 'Google',
                                'inworld' => 'Inworld',
                            ];
                            if ($fname === 'API_KEY' && isset($providerBadgeMap[$lnameProv])) {
                                $badgeName = $providerBadgeMap[$lnameProv];
                                $hasKey=false; foreach ($apiBadges as $r){ if (strtolower((string)($r['label']??''))===strtolower($badgeName) && trim((string)($r['api_key']??''))!==''){ $hasKey=true; break; } }
                                echo '<div>API Badge ('.htmlspecialchars($badgeName).')</div>';
                                echo '<div>'.($hasKey?'<span style="color:#6dd19c">Configured</span>':'<span style="color:#ffb862">Missing</span>').' — <a href="#" onclick="try{ if(window.top){ window.top.location.href=\''.htmlspecialchars($webRoot).'/ui/core/config_hub.php?tab=keys\'; } else { window.location.href=\''.htmlspecialchars($webRoot).'/ui/core/api_badge.php?embed=1\'; } }catch(e){ window.location.href=\''.htmlspecialchars($webRoot).'/ui/core/api_badge.php?embed=1\'; } return false;">Manage Keys</a></div>';
                                if (!empty($help)) echo '<div class="help">'.$help.'</div>';
                                continue;
                            }
                        ?>
                            <label for="stt_<?php echo htmlspecialchars($fname); ?>"><?php echo htmlspecialchars(format_field_label($fname)); ?></label>
                            <?php if ($ftype==='boolean'): ?>
                                <input type="hidden" name="stt__<?php echo htmlspecialchars($fname); ?>" value="false">
                                <input type="checkbox" id="stt_<?php echo htmlspecialchars($fname); ?>" name="stt__<?php echo htmlspecialchars($fname); ?>" value="true" <?php echo ($current?'checked':''); ?> style="width:auto;">
                            <?php elseif ($ftype==='integer'): ?>
                                <input type="number" step="1" id="stt_<?php echo htmlspecialchars($fname); ?>" name="stt__<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>">
                            <?php elseif ($ftype==='number'): ?>
                                <input type="number" step="0.01" id="stt_<?php echo htmlspecialchars($fname); ?>" name="stt__<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>">
                            <?php elseif ($ftype==='longstring'): ?>
                                <textarea id="stt_<?php echo htmlspecialchars($fname); ?>" name="stt__<?php echo htmlspecialchars($fname); ?>" rows="3"><?php echo htmlspecialchars((string)$current); ?></textarea>
                            <?php elseif ($ftype==='url'): ?>
                                <input type="url" id="stt_<?php echo htmlspecialchars($fname); ?>" name="stt__<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>">
                                <div style="margin-top:6px;">
                                    <button type="button" class="btn-primary" style="padding: 6px 12px; background-color: rgba(37, 99, 235, 0.8); color: #ffffff; border: 1px solid rgba(138, 155, 182, 0.3); border-radius: 8px; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s ease-in-out; font-weight: 500; letter-spacing: 0.3px; backdrop-filter: blur(5px); box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2), inset 0 1px rgba(255, 255, 255, 0.1);" onclick="checkUrlFromServer('stt__<?php echo htmlspecialchars($fname); ?>')" onmouseover="this.style.backgroundColor='rgba(47, 109, 245, 0.9)'; this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(0, 0, 0, 0.3), inset 0 1px rgba(255, 255, 255, 0.15)';" onmouseout="this.style.backgroundColor='rgba(37, 99, 235, 0.8)'; this.style.transform='none'; this.style.boxShadow='0 2px 6px rgba(0, 0, 0, 0.2), inset 0 1px rgba(255, 255, 255, 0.1)';">Check Connection</button>
                                </div>
                            <?php elseif ($ftype==='select'): $values=$def['values']??[]; ?>
                                <select id="stt_<?php echo htmlspecialchars($fname); ?>" name="stt__<?php echo htmlspecialchars($fname); ?>">
                                    <?php foreach ($values as $opt): ?>
                                        <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ((string)$current===(string)$opt?'selected':''); ?>><?php echo htmlspecialchars($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($ftype==='apikey'): ?>
                                <input type="password" id="stt_<?php echo htmlspecialchars($fname); ?>" name="stt__<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>" placeholder="Paste API key">
                            <?php else: ?>
                                <input type="text" id="stt_<?php echo htmlspecialchars($fname); ?>" name="stt__<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>">
                            <?php endif; ?>
                            <?php if (!empty($help)): ?><div class="help"><?php echo $help; ?></div><?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                    <div class="provider-card"><div class="provider-body"><div></div><div>No settings available for this provider.</div></div></div>
                <?php endif; ?>

                <div class="provider-card">
                    <div class="provider-head">
                        <div class="provider-title">
                            <div class="provider-icon">🧪</div>
                            <div>STT Test</div>
                        </div>
                    </div>
                    <div class="provider-body">
                        <button type="button" id="btn_test_stt_gs" class="btn-primary">Test</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-section" id="tab-itt" style="display:none;">
            <h2>Image-to-Text</h2>
            <div class="provider-grid">
                <div class="provider-card">
                    <div class="provider-head">
                        <div class="provider-title">
                            <div class="provider-icon">🖼️</div>
                            <div>ITT Provider</div>
                        </div>
                    </div>
                    <div class="provider-body grid">
                        <label for="ITTFUNCTION">ITT Selection</label>
                        <select name="ITTFUNCTION" id="ITTFUNCTION" onchange="document.getElementById('gs_tab').value='tab-itt'; this.form.submit()">
                            <?php foreach ($ittOptions as $opt): ?>
                                <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ((string)$ittSelRender===(string)$opt?'selected':''); ?>><?php echo htmlspecialchars($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                    </div>
                </div>
                <?php $ittKeyCur = $ittMap[$ittSelRender] ?? ''; $ittSchemaCur = ($ittKeyCur && isset($ittProviders[$ittKeyCur]) && is_array($ittProviders[$ittKeyCur])) ? $ittProviders[$ittKeyCur] : []; ?>
                <?php if (!empty($ittSchemaCur)): ?>
                <div class="provider-card">
                    <div class="provider-head">
                        <div class="provider-title">
                            <div class="provider-icon">⚙️</div>
                            <div><?php echo htmlspecialchars($ittKeyCur); ?> Settings</div>
                        </div>
                    </div>
                    <div class="provider-body grid">
                        <?php
                        $apiBadges = [];
                        try { if (!isset($GLOBALS['db']) || !$GLOBALS['db']) { $GLOBALS['db'] = new sql(); } $apiBadges = $GLOBALS['db']->fetchAll("SELECT id,label,api_key FROM core_api_badge ORDER BY label ASC"); } catch (Throwable $_e) {}
                        foreach ($ittSchemaCur as $fname => $def): if (!is_array($def)) continue; $ftype=$def['type']??'string'; $plain='ITT '.$ittKeyCur.' '.$fname; $current=$currentConf[$plain]['currentValue']??''; $help=$def['description']??'';
                            $lnameProv = strtolower($ittKeyCur);
                            if ($fname === 'API_KEY' && in_array($lnameProv, ['openai','google_openai','openrouter'])) {
                                $badgeName = ($lnameProv==='google_openai') ? 'Google' : ($lnameProv==='openrouter' ? 'OpenRouter' : 'OpenAI');
                                $hasKey=false; foreach ($apiBadges as $r){ if (strtolower((string)($r['label']??''))===strtolower($badgeName) && trim((string)($r['api_key']??''))!==''){ $hasKey=true; break; } }
                                echo '<div>API Badge ('.htmlspecialchars($badgeName).')</div>';
                                echo '<div>'.($hasKey?'<span style="color:#6dd19c">Configured</span>':'<span style="color:#ffb862">Missing</span>').' — <a href="#" onclick="try{ if(window.top){ window.top.location.href=\''.htmlspecialchars($webRoot).'/ui/core/config_hub.php?tab=keys\'; } else { window.location.href=\''.htmlspecialchars($webRoot).'/ui/core/api_badge.php?embed=1\'; } }catch(e){ window.location.href=\''.htmlspecialchars($webRoot).'/ui/core/api_badge.php?embed=1\'; } return false;">Manage Keys</a></div>';
                                if (!empty($help)) echo '<div class="help">'.$help.'</div>';
                                continue;
                            }
                        ?>
                            <label for="itt_<?php echo htmlspecialchars($fname); ?>"><?php echo htmlspecialchars(format_field_label($fname)); ?></label>
                            <?php if ($ftype==='boolean'): ?>
                                <input type="hidden" name="itt__<?php echo htmlspecialchars($fname); ?>" value="false">
                                <input type="checkbox" id="itt_<?php echo htmlspecialchars($fname); ?>" name="itt__<?php echo htmlspecialchars($fname); ?>" value="true" <?php echo ($current?'checked':''); ?> style="width:auto;">
                            <?php elseif ($ftype==='integer'): ?>
                                <input type="number" step="1" id="itt_<?php echo htmlspecialchars($fname); ?>" name="itt__<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>">
                            <?php elseif ($ftype==='number'): ?>
                                <input type="number" step="0.01" id="itt_<?php echo htmlspecialchars($fname); ?>" name="itt__<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>">
                            <?php elseif ($ftype==='longstring'): ?>
                                <textarea id="itt_<?php echo htmlspecialchars($fname); ?>" name="itt__<?php echo htmlspecialchars($fname); ?>" rows="3"><?php echo htmlspecialchars((string)$current); ?></textarea>
                            <?php elseif ($ftype==='url'): ?>
                                <input type="url" id="itt_<?php echo htmlspecialchars($fname); ?>" name="itt__<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>">
                                <div style="margin-top:6px;">
                                    <button type="button" class="btn-primary" style="padding: 6px 12px; background-color: rgba(37, 99, 235, 0.8); color: #ffffff; border: 1px solid rgba(138, 155, 182, 0.3); border-radius: 8px; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s ease-in-out; font-weight: 500; letter-spacing: 0.3px; backdrop-filter: blur(5px); box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2), inset 0 1px rgba(255, 255, 255, 0.1);" onclick="checkUrlFromServer('itt__<?php echo htmlspecialchars($fname); ?>')" onmouseover="this.style.backgroundColor='rgba(47, 109, 245, 0.9)'; this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(0, 0, 0, 0.3), inset 0 1px rgba(255, 255, 255, 0.15)';" onmouseout="this.style.backgroundColor='rgba(37, 99, 235, 0.8)'; this.style.transform='none'; this.style.boxShadow='0 2px 6px rgba(0, 0, 0, 0.2), inset 0 1px rgba(255, 255, 255, 0.1)';">Check Connection</button>
                                </div>
                            <?php elseif ($ftype==='select'): $values=$def['values']??[]; ?>
                                <select id="itt_<?php echo htmlspecialchars($fname); ?>" name="itt__<?php echo htmlspecialchars($fname); ?>">
                                    <?php foreach ($values as $opt): ?>
                                        <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ((string)$current===(string)$opt?'selected':''); ?>><?php echo htmlspecialchars($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($ftype==='apikey'): ?>
                                <input type="password" id="itt_<?php echo htmlspecialchars($fname); ?>" name="itt__<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>" placeholder="Paste API key">
                            <?php else: ?>
                                <input type="text" id="itt_<?php echo htmlspecialchars($fname); ?>" name="itt__<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>">
                            <?php endif; ?>
                            <?php if (!empty($help)): ?><div class="help"><?php echo $help; ?></div><?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                    <div class="provider-card"><div class="provider-body"><div></div><div>No settings available for this provider.</div></div></div>
                <?php endif; ?>

                <div class="provider-card">
                    <div class="provider-head">
                        <div class="provider-title">
                            <div class="provider-icon">🧪</div>
                            <div>ITT Test</div>
                        </div>
                    </div>
                    <div class="provider-body">
                        <button type="button" id="btn_test_itt_gs" class="btn-primary">Test</button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="actions"></div>
    </form>
</main>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl".DIRECTORY_SEPARATOR."footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>

<script>
(function(){
  try{
    function showTab(id){
      var ids=['tab-global','tab-tts','tab-stt','tab-itt'];
      ids.forEach(function(x){
        var el=document.getElementById(x);
        if(!el) return;
        if (x === id) {
          // Keep General tab as CSS grid; other tabs are block sections.
          el.style.display = (x === 'tab-global') ? 'grid' : 'block';
        } else {
          el.style.display = 'none';
        }
      });
    }
    var btns=document.querySelectorAll('[data-gs-tab]');
    for (var i=0;i<btns.length;i++){
      btns[i].addEventListener('click', function(){
        var id=this.getAttribute('data-gs-tab');
        showTab(id);
        for (var j=0;j<btns.length;j++){ btns[j].classList.remove('active'); }
        this.classList.add('active');
      });
    }
    // Persist active tab on postback
    var active = '<?php echo htmlspecialchars($activeTab); ?>';
    if (active && active !== 'tab-global'){
      showTab(active);
      for (var j=0;j<btns.length;j++){ if (btns[j].getAttribute('data-gs-tab')===active){ btns[j].classList.add('active'); } else { btns[j].classList.remove('active'); } }
    }
  }catch(_e){}
})();
</script>

<script>
(function(){
  try{
    // Toggle Player Voice ID for Piper-TTS only
    function togglePlayerVoiceId(){
      var sel = document.getElementById('TTSFUNCTION_PLAYER');
      var show = !!sel && String(sel.value||'').toLowerCase()==='piper-tts';
      var nodes = document.querySelectorAll('.player-voice-id-only');
      for (var i=0;i<nodes.length;i++){
        nodes[i].style.display = show ? '' : 'none';
      }
    }
    var sel = document.getElementById('TTSFUNCTION_PLAYER');
    if (sel){ sel.addEventListener('change', togglePlayerVoiceId); }
    // Initialize on load
    togglePlayerVoiceId();
    // STT/ITT test modals opener (reuse existing test pages)
    function openModal(url){
      var modal = document.createElement('div');
      modal.style.cssText = 'position:fixed; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.65); z-index:10000;';
      modal.innerHTML = '\n        <div style="width:90%; max-width:1100px; height:80vh; background:#111; border:1px solid rgba(138,155,182,0.4); border-radius:10px; box-shadow:0 10px 30px rgba(0,0,0,0.6); position:relative; overflow:hidden;">\n            <button id="hubtest_close" style="position:absolute; top:8px; right:10px; background:#300; color:#fff; border:1px solid rgba(255,255,255,0.2); border-radius:6px; padding:4px 10px; cursor:pointer; z-index:3;">Close<\/button>\n            <div id="hubtest_loading" style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.4); z-index:2;">\n                <div style="width:48px; height:48px; border:4px solid rgba(255,255,255,0.25); border-top-color:#ffb862; border-radius:50%; animation: spin 1s linear infinite;"><\/div>\n            <\/div>\n            <iframe id="hubtest_iframe" src="about:blank" style="width:100%; height:100%; border:0; background:#0e1624; position:relative; z-index:1;"><\/iframe>\n        <\/div>\n        <style>@keyframes spin{to{transform:rotate(360deg)}}<\/style>';
      document.body.appendChild(modal);
      var iframe = modal.querySelector('#hubtest_iframe');
      var loader = modal.querySelector('#hubtest_loading');
      if (loader) loader.style.display='flex';
      iframe.onload = function(){ if (loader) loader.style.display='none'; };
      iframe.src = url;
      function close(){ try{ document.body.removeChild(modal); }catch(e){} }
      modal.addEventListener('click', function(e){ if (e.target===modal) close(); });
      document.addEventListener('click', function(e){ if (e.target && e.target.id==='hubtest_close') close(); });
      document.addEventListener('keydown', function(e){ if (e.key==='Escape') close(); });
    }
    function openAudioModal(src){
      var modal = document.createElement('div');
      modal.style.cssText = 'position:fixed; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.65); z-index:10000;';
      modal.innerHTML = '\n        <div style="width:90%; max-width:700px; background:#111; border:1px solid rgba(138,155,182,0.4); border-radius:10px; box-shadow:0 10px 30px rgba(0,0,0,0.6); position:relative; padding:16px;">\n            <button id="hubtest_close" style="position:absolute; top:8px; right:10px; background:#300; color:#fff; border:1px solid rgba(255,255,255,0.2); border-radius:6px; padding:4px 10px; cursor:pointer; z-index:3;">Close<\/button>\n            <h3 style="margin:0 0 10px 0; color:#cfd8e3;">TTS Preview<\/h3>\n            <audio controls style="width:100%"><source src="'+src+'" type="audio/wav"><\/audio>\n        <\/div>';
      document.body.appendChild(modal);
      function close(){ try{ document.body.removeChild(modal); }catch(e){} }
      modal.addEventListener('click', function(e){ if (e.target===modal) close(); });
      document.addEventListener('click', function(e){ if (e.target && e.target.id==='hubtest_close') close(); });
      document.addEventListener('keydown', function(e){ if (e.key==='Escape') close(); });
    }
    function openTtsGeneratingModal(){
      var modal = document.createElement('div');
      modal.style.cssText = 'position:fixed; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.65); z-index:10000;';
      modal.innerHTML = '\n        <div style="width:90%; max-width:700px; background:#111; border:1px solid rgba(138,155,182,0.4); border-radius:10px; box-shadow:0 10px 30px rgba(0,0,0,0.6); position:relative; padding:16px;">\n            <button id="hubtest_close" style="position:absolute; top:8px; right:10px; background:#300; color:#fff; border:1px solid rgba(255,255,255,0.2); border-radius:6px; padding:4px 10px; cursor:pointer; z-index:3;">Close<\/button>\n            <h3 style="margin:0 0 10px 0; color:#cfd8e3;">Generating TTS...<\/h3>\n            <div id="tts_gen_body" style="min-height:80px; display:flex; align-items:center; justify-content:center; color:#cfd8e3;">\n              <div style="width:36px; height:36px; border:4px solid rgba(255,255,255,0.25); border-top-color:#ffb862; border-radius:50%; animation: spin 1s linear infinite;"><\/div>\n            <\/div>\n        <\/div>\n        <style>@keyframes spin{to{transform:rotate(360deg)}}<\/style>';
      document.body.appendChild(modal);
      function close(){ try{ document.body.removeChild(modal); }catch(e){} }
      modal.addEventListener('click', function(e){ if (e.target===modal) close(); });
      document.addEventListener('click', function(e){ if (e.target && e.target.id==='hubtest_close'){ try{ if (window.__tts_abortController){ window.__tts_abortController.abort(); window.__tts_abortController = null; } }catch(_e){} close(); } });
      document.addEventListener('keydown', function(e){ if (e.key==='Escape'){ try{ if (window.__tts_abortController){ window.__tts_abortController.abort(); window.__tts_abortController = null; } }catch(_e){} close(); } });
      modal.addEventListener('click', function(e){ if (e.target===modal){ try{ if (window.__tts_abortController){ window.__tts_abortController.abort(); window.__tts_abortController = null; } }catch(_e){} close(); } });
      return {
        update: function(url){
          try{
            var b = modal.querySelector('#tts_gen_body');
            if (!b) return;
            if (!url){ b.innerHTML = '<div style="color:#ffb862">No audio produced. Check connector settings and logs.<\/div>'; return; }
            var ts = Date.now();
            b.innerHTML = '<audio autoplay controls style="width:100%"><source src="'+url+'" type="audio/wav"><\/audio>';
          }catch(e){}
        }
      };
    }
    async function saveFormSilently(){
      try {
        var form=document.getElementById('gs_form');
        if (!form) return;
        var fd=new FormData(form);
        if (!fd.has('save_all')) fd.append('save_all','1');
        await fetch(window.location.pathname, { method:'POST', body: fd });
      } catch(_e){}
    }
    // Toggle Player Language Override for supported providers
    function togglePlayerLanguage(){
      var sel = document.getElementById('TTSFUNCTION_PLAYER');
      var v = (sel && sel.value) ? String(sel.value).toLowerCase() : '';
      var supported = ['melotts','xtts-fastapi','chatterbox','pockettts','xvasynth','piper-tts','zonos_gradio','cartesia','inworld'];
      var show = supported.indexOf(v) >= 0;
      var nodes = document.querySelectorAll('.player-language-only');
      for (var i=0;i<nodes.length;i++){
        nodes[i].style.display = show ? '' : 'none';
      }
    }
    var selPlayer = document.getElementById('TTSFUNCTION_PLAYER');
    if (selPlayer){ selPlayer.addEventListener('change', function(){ togglePlayerVoiceId(); togglePlayerLanguage(); }); }
    togglePlayerLanguage();
    var sttBtn = document.getElementById('btn_test_stt_gs');
    if (sttBtn){
      sttBtn.addEventListener('click', async function(){ await saveFormSilently(); var cb=Date.now(); openModal('<?php echo $webRoot; ?>/ui/tests/stt-test.php?cb='+cb); });
    }
    var ittBtn = document.getElementById('btn_test_itt_gs');
    if (ittBtn){
      ittBtn.addEventListener('click', async function(){ await saveFormSilently(); var cb=Date.now(); openModal('<?php echo $webRoot; ?>/ui/tests/itt-test.php?cb='+cb); });
    }
    var ttsBtn = document.getElementById('btn_test_tts_gs');
    if (ttsBtn){
      ttsBtn.addEventListener('click', async function(){
        try {
          var modalCtl = openTtsGeneratingModal();
          var form=document.getElementById('gs_form');
          if (!form) return;
          // Save current provider settings so test uses latest config
          await saveFormSilently();
          var fd=new FormData(form);
          fd.append('tts_quick_test','1');
          fd.append('ajax','1');
          fd.set('gs_tab','tab-tts');
          var controller = new AbortController();
          try { window.__tts_abortController = controller; } catch(_){ }
          var timedOut = false;
          var timeoutId = setTimeout(function(){ timedOut=true; try{ controller.abort(); }catch(e){} }, 30000);
          var resp = await fetch(window.location.pathname, { method:'POST', body: fd, signal: controller.signal }).catch(function(e){ if (timedOut) throw new Error('TTS test timed out'); throw e; });
          clearTimeout(timeoutId);
          try { if (window.__tts_abortController === controller) window.__tts_abortController = null; } catch(_){ }
          var data = null;
          try { data = await resp.json(); } catch(e) { data = null; }
          var url = (data && data.url) ? data.url : '';
          if (url) {
            // Update modal with playable audio (autoplay allowed due to user gesture that opened modal)
            modalCtl.update(url);
          } else {
            modalCtl.update('');
          }
        } catch(e){
          try { console.warn('TTS quick test failed or timed out', e); } catch(_){ }
          try {
            var modalCtl2 = openTtsGeneratingModal();
            modalCtl2.update('');
          } catch(_){ }
        }
      });
    }
  }catch(_e){}
})();
</script>

<script>
// Google Free STT Button Handler
(function(){
  const googleFreeBtn = document.getElementById('btn_google_free_stt');
  if (googleFreeBtn){
    googleFreeBtn.addEventListener('click', function(){
      window.open('<?php echo $webRoot; ?>/ui/addons/pmstt/index.html', '_blank', 'width=1100,height=800');
    });
  }
})();
</script>


