<?php

session_start();

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
$ttsOptions = $rawSchema['TTSFUNCTION']['values'] ?? [ 'mimic3','melotts','xtts-fastapi','xvasynth','azure','11labs','openai','koboldcpp','zonos_gradio','piper-tts','kokoro','deepgram' ];
$sttOptions = $rawSchema['STTFUNCTION']['values'] ?? [ 'none','whisper','localwhisper','azure','deepgram' ];
$ittOptionsRaw = $rawSchema['ITTFUNCTION']['values'] ?? [ 'openai','google_openai','openrouter','llamacpp' ];
// Exclude llamacpp per existing ITT page behavior
$ittOptions = array_values(array_filter($ittOptionsRaw, function($v){ return strtolower($v) !== 'llamacpp'; }));

// Mappings
$ttsMap = [ 'melotts' => 'MELOTTS','xtts-fastapi' => 'XTTSFASTAPI','mimic3' => 'MIMIC3','xvasynth' => 'XVASYNTH','azure' => 'AZURE','11labs' => 'ELEVEN_LABS','openai' => 'openai','kokoro' => 'KOKORO','koboldcpp' => 'koboldcpp','zonos_gradio' => 'ZONOS_GRADIO','piper-tts' => 'PIPERTTS','deepgram' => 'deepgram' ];
$sttMap = [ 'whisper' => 'WHISPER','localwhisper' => 'LOCALWHISPER','azure' => 'AZURE','deepgram' => 'DEEPGRAM' ];
$ittMap = [ 'openai' => 'openai','google_openai' => 'google_openai','openrouter' => 'openrouter' ];

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
        $GLOBALS["PATCH_OVERRIDE_VOICE"] = $ttsTestVoice;
    } else {
        if ($selLower === 'xtts-fastapi') $GLOBALS["PATCH_OVERRIDE_VOICE"] = 'TheNarrator'; else $GLOBALS["PATCH_OVERRIDE_VOICE"] = 'malenord';
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
    while (@ob_end_clean());
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => ($ttsTestOutputUrl !== ''),
        'url' => $ttsTestOutputUrl,
    ]);
    exit;
}

// Helper: flatten currentConf into name=>value pairs like conf_wizard/conf_writer
function flatten_current_conf(array $currentConf, array $confSchema): array {
    $flat = [];
    foreach ($currentConf as $pname => $parms) {
        $fieldName = strtr($pname, [" " => "@"]); // HERIKA NAME -> HERIKA@NAME
        $type = $parms["type"] ?? ($confSchema[$pname]["type"] ?? 'string');
        $val = $parms["currentValue"] ?? '';
        if ($type === 'boolean') {
            $flat[$fieldName] = $val ? 'true' : 'false';
        } else if ($type === 'selectmultiple') {
            $flat[$fieldName] = is_array($val) ? $val : [];
        } else if ($type === 'number' || $type === 'integer') {
            $flat[$fieldName] = (string)($val === '' ? '' : $val);
        } else {
            // strings, longstring, url, apikey, foreign, etc.
            if (is_array($val)) {
                // Defensive: unexpected arrays default to empty
                $flat[$fieldName] = [];
            } else {
                $flat[$fieldName] = (string)$val;
            }
        }
    }
    return $flat;
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
        [ 'name' => 'PLAYER_NAME', 'type' => 'text' ],
        [ 'name' => 'PROMPT_HEAD', 'type' => 'longstring' ],
        [ 'name' => 'CORE_CONNECTOR_PLAYER', 'type' => 'foreign:core_llm_connector:id:label' ],
        [ 'name' => 'PLAYER_RESPEECH', 'type' => 'boolean' ],
        [ 'name' => 'PLAYER_SPEECH_STYLE', 'type' => 'longstring' ],
        [ 'name' => 'CORE_CONNECTOR_SUMMARY', 'type' => 'foreign:core_llm_connector:id:label' ],
        [ 'name' => 'CORE_CONNECTOR_MEDIUMTERM', 'type' => 'foreign:core_llm_connector:id:label' ],
        [ 'name' => 'CORE_CONNECTOR_PROFILES', 'type' => 'foreign:core_llm_connector:id:label' ],
        [ 'name' => 'CLEAN_CONTEXT_FOCUS_CHAT_HISTORY', 'type' => 'integer' ],
    ],
    'Dynamic Prompts' => [
        [ 'name' => 'DYNAMIC_PROMPT_PERSONALITY', 'type' => 'longstring' ],
        [ 'name' => 'DYNAMIC_PROMPT_RELATIONSHIPS', 'type' => 'longstring' ],
        [ 'name' => 'DYNAMIC_PROMPT_OCCUPATION', 'type' => 'longstring' ],
        [ 'name' => 'DYNAMIC_PROMPT_SKILLS', 'type' => 'longstring' ],
        [ 'name' => 'DYNAMIC_PROMPT_SPEECHSTYLE', 'type' => 'longstring' ],
        [ 'name' => 'DYNAMIC_PROMPT_GOALS', 'type' => 'longstring' ],
    ],
    'Narrator' => [
        [ 'name' => 'NARRATOR_TALKS', 'type' => 'boolean' ],
        [ 'name' => 'NARRATOR_WELCOME', 'type' => 'boolean' ],
        [ 'name' => 'BOOK_EVENT_ALWAYS_NARRATOR', 'type' => 'boolean' ]
    ],
    'Memory' => [
        [ 'name' => 'SUMMARY_PROMPT', 'type' => 'longstring' ],
        [ 'name' => 'FEATURES@MEMORY_EMBEDDING@ENABLED', 'type' => 'boolean' ],
        [ 'name' => 'FEATURES@MEMORY_EMBEDDING@TXTAI_URL', 'type' => 'url' ],
        [ 'name' => 'FEATURES@MEMORY_EMBEDDING@USE_TEXT2VEC', 'type' => 'boolean' ],
        [ 'name' => 'FEATURES@MEMORY_EMBEDDING@MEMORY_TIME_DELAY', 'type' => 'integer' ],
        [ 'name' => 'FEATURES@MEMORY_EMBEDDING@MEMORY_CONTEXT_SIZE', 'type' => 'integer' ],
        [ 'name' => 'FEATURES@MEMORY_EMBEDDING@AUTO_CREATE_SUMMARYS', 'type' => 'boolean' ],
        [ 'name' => 'FEATURES@MEMORY_EMBEDDING@AUTO_CREATE_SUMMARY_INTERVAL', 'type' => 'integer' ],
        [ 'name' => 'FEATURES@MEMORY_EMBEDDING@MEMORY_BIAS_A', 'type' => 'number' ],
        [ 'name' => 'FEATURES@MEMORY_EMBEDDING@MEMORY_BIAS_B', 'type' => 'number' ]
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
	if (isset($_POST['TTSFUNCTION_PLAYER'])) { $allPairs['TTSFUNCTION_PLAYER'] = (string)$_POST['TTSFUNCTION_PLAYER']; }
	if (isset($_POST['TTSFUNCTION_PLAYER_VOICE'])) { $allPairs['TTSFUNCTION_PLAYER_VOICE'] = (string)$_POST['TTSFUNCTION_PLAYER_VOICE']; }
	if (isset($_POST['TTSFUNCTION_PLAYER_VOICE_ID'])) { $allPairs['TTSFUNCTION_PLAYER_VOICE_ID'] = (string)$_POST['TTSFUNCTION_PLAYER_VOICE_ID']; }
	if (isset($_POST['TTSFUNCTION_PLAYER_LANGUAGE'])) { $allPairs['TTSFUNCTION_PLAYER_LANGUAGE'] = (string)$_POST['TTSFUNCTION_PLAYER_LANGUAGE']; }

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
		while (@ob_end_clean());
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

    h1.gs-title {
        margin: 0 0 20px 0;
        font-family: 'MagicCards', serif;
        word-spacing: 8px;
        font-size: 2.2em;
        color: rgb(242, 124, 17);
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        text-align: center;
    }

    .content-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }
    .content-section {
        background: #2a2a2a;
        padding: 25px;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
    }
    .content-section h2 { font-family: 'MagicCards', serif; color: rgb(242,124,17); text-shadow: 1px 1px 2px rgba(0,0,0,0.5); word-spacing: 6px; margin-bottom: 15px; font-size: 1.4em; }
    .provider-grid { display:grid; grid-template-columns: 1fr; gap:12px; align-items:start; }
    .provider-card { background:#2a2a2a; border:1px solid #4a4a4a; border-radius:8px; padding:12px; }
    .provider-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px; }
    .provider-title { display:flex; align-items:center; gap:10px; color:#e0e0e0; }
    .provider-icon { width:28px; height:28px; border-radius:6px; background:#3a3a3a; display:flex; align-items:center; justify-content:center; font-size:16px; }
    .provider-body { display:flex; gap:8px; align-items:center; }
    .provider-body.grid { display:grid; grid-template-columns: 220px 1fr; gap:8px 12px; align-items:center; }
    .provider-body.grid .help { grid-column: 1 / -1; margin-top:6px; color:#bbb; font-size:12px; }
    .provider-body input[type="text"], .provider-body input[type="url"], .provider-body input[type="number"], .provider-body input[type="password"], .provider-body select, .provider-body textarea { flex:1; background-color:#333; color:#fff; border:1px solid #444; border-radius:4px; padding:8px; }
    .actions { display:flex; justify-content:flex-end; margin-top:10px; }
    .btn-primary { background:#204e7a; color:#fff; border:1px solid rgba(138,155,182,0.4); border-radius:8px; padding:8px 14px; cursor:pointer; }
    .btn-primary:hover { background:#285c8f; }

    @media (max-width: 900px) {
        main { padding-left: 5%; padding-right: 5%; }
        .content-grid { grid-template-columns: 1fr; }
        .provider-grid { grid-template-columns: 1fr; }
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
    .tab-buttons { display:flex; gap:8px; flex-wrap:wrap; margin-top:8px; justify-content:center; align-items:center; }
    .tab-button { background:#1a2940; color:#cfd8e3; border:1px solid rgba(138,155,182,0.35); padding:6px 12px; border-radius:8px; cursor:pointer; }
    .tab-button:hover { background:#203553; }
    .tab-button.active { background:#204e7a; color:#fff; border-color: rgba(138,155,182,0.6); }
    .btn-save-green { 
        background-color: rgba(32, 122, 74, 0.8);
        color: #fff;
        border: 1px solid rgba(138, 155, 182, 0.3);
        border-radius: 8px;
        padding: 8px 16px;
        cursor: pointer;
    }
    .btn-save-green:hover { background-color: rgba(42, 142, 94, 0.9); }
</style>

<main>
    <h1 class="gs-title">Global Settings</h1>
    <div class="provider-card" style="margin-bottom:16px;">
    <div style="display:flex; justify-content:center; margin-top:8px; margin-bottom:12px;">
            <button type="submit" class="btn-save-green" name="save_all" value="1" form="gs_form">Save All</button>
        </div>
        <div class="provider-head" style="justify-content:center;">
            <div class="tab-buttons">
                <button type="button" class="tab-button active" data-gs-tab="tab-global">🌐General</button>
                <button type="button" class="tab-button" data-gs-tab="tab-tts">🔊TTS</button>
                <button type="button" class="tab-button" data-gs-tab="tab-stt">🎤STT</button>
                <button type="button" class="tab-button" data-gs-tab="tab-itt">🖼️ITT</button>
            </div>
        </div>

    </div>
    <div id="toast" class="toast-notification" style="display:none;"><span class="message"></span></div>

    <?php if ($saveSuccess): ?>
        <script>setTimeout(function(){ try{ const t=document.getElementById('toast'); if(t){ t.style.display='block'; t.textContent='Settings saved to conf.php'; setTimeout(()=>{ t.style.display='none'; }, 2500); } }catch(_e){} }, 50);</script>
    <?php endif; ?>

    <form method="post" action="" id="gs_form">
        <input type="hidden" name="gs_tab" id="gs_tab" value="<?php echo htmlspecialchars($activeTab); ?>">
        <div class="content-grid" id="tab-global">
            <?php foreach ($gsSections as $sectionTitle => $fields): ?>
                <div class="content-section">
                    <h2><?php echo htmlspecialchars($sectionTitle); ?></h2>
                    <div class="provider-grid">
                        <?php foreach ($fields as $f): ?>
                            <?php
                                $fname = $f['name'];
                                $ftype = $f['type'];
                                $current = current_value($fname, $currentConf);
                                $label = pretty_label($fname);
                                $help = $gsDesc($fname);
                            ?>
                            <div class="provider-card">
                                <div class="provider-head">
                                    <div class="provider-title">
                                        <div class="provider-icon"><?php echo icon_for_field($fname); ?></div>
                                        <div><?php echo htmlspecialchars($label); ?></div>
                                        <?php if ($ftype === 'boolean'): ?>
                                            <div class="provider-toggle">
                                                <input type="hidden" name="<?php echo htmlspecialchars($fname); ?>" value="false">
                                                <input type="checkbox" value="true" name="<?php echo htmlspecialchars($fname); ?>" <?php echo ($current ? 'checked' : ''); ?> style="width:auto;">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="provider-body">
                                    <?php if ($ftype === 'boolean'): ?>
                                        <!-- Boolean rendered in header next to title -->
                                    <?php elseif ($ftype === 'integer'): ?>
                                        <?php $min = isset($f['min']) ? (int)$f['min'] : null; $max = isset($f['max']) ? (int)$f['max'] : null; ?>
                                        <input type="number" name="<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>" <?php echo ($min!==null?('min="'.$min.'"'):''); ?> <?php echo ($max!==null?('max="'.$max.'"'):''); ?> step="1">
                                    <?php elseif ($ftype === 'number'): ?>
                                        <input type="number" step="0.01" name="<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>">
                                    <?php elseif ($ftype === 'longstring'): ?>
                                        <textarea name="<?php echo htmlspecialchars($fname); ?>" rows="4"><?php echo htmlspecialchars((string)$current); ?></textarea>
                                    <?php elseif ($ftype === 'url'): ?>
                                        <input type="url" name="<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>">
                                    <?php elseif ($ftype === 'apikey'): ?>
                                        <input type="password" name="<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>" placeholder="Paste API key">
                                    <?php elseif ($ftype === 'select'): ?>
                                        <?php $values = $f['values'] ?? []; ?>
                                        <select name="<?php echo htmlspecialchars($fname); ?>">
                                            <?php foreach ($values as $opt): ?>
                                                <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ((string)$current===(string)$opt?'selected':''); ?>><?php echo htmlspecialchars($opt); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif (strpos($ftype, 'foreign:') === 0): ?>
                                        <?php $rows = $foreignOptions[$fname] ?? []; ?>
                                        <select name="<?php echo htmlspecialchars($fname); ?>">
                                            <?php foreach ($rows as $row): ?>
                                                <?php $idCol = explode(':', $ftype)[2]; $labelCol = explode(':', $ftype)[3]; ?>
                                                <option value="<?php echo htmlspecialchars($row[$idCol]); ?>" <?php echo ((string)$current===(string)$row[$idCol]?'selected':''); ?>><?php echo htmlspecialchars($row[$labelCol]); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <input type="text" name="<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>">
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
                            <?php foreach ($ttsOptions as $opt): ?>
                                <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ((string)$ttsSelRender===(string)$opt?'selected':''); ?>><?php echo htmlspecialchars($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <div></div>
                        <div class="help">
                            <?php
                            $ttsDescMap = [
                                'melotts' => "[Skyrim Voices] MeloTTS runs locally installed via DwemerDistro. It's fast and free, but low quality voices. Under 1GB of VRAM.",
                                'xtts-fastapi' => "[Skyrim Voices] CHIM XTTS runs locally and generates cloned voices from samples. Great for immersive, consistent character voices. Uses roughly 4GB of VRAM.",
                                'mimic3' => "Mimic3 is a very basic LLM installed in DwemerDistro. It's fast and free, but low quality custom voices. Under 1GB of VRAM.",
                                'xvasynth' => "[Skyrim Voices] xVASynth uses pre-trained game voices. Good fit for Skyrim-style character voices and mod voicepacks.",
                                'azure' => "Azure TTS offers decent voices with emotion control. Requires Azure subscription and API key.",
                                '11labs' => "ElevenLabs provides realistic, emotive voices. Requires manual generation of voices. Requires API key and credits.",
                                'openai' => "OpenAI TTS supports a limited amount of decent quality voices. Requires API key.",
                                'kokoro' => "KOKORO is a lightweight TTS. Useful when you need a simple, fast voice without complex configs.",
                                'koboldcpp' => "KoboldCPP TTS routes to a local service. Use if you maintain a custom local TTS pipeline.",
                                'zonos_gradio' => "Zonos TTS provides expressive voices with emotion controls. Recommended to use with cloud GPU hosting (Vast.ai). Uses roughly 6GB of VRAM.",
                                'piper-tts' => "[Skyrim Voices]Piper-TTS is a middle quality and fast TTS. Requires manual installation of voices though. Under 1GB of VRAM. https://dwemerdynamics.hostwiki.io/en/TTS-Options",
                                'deepgram' => "Deepgram TTS is a cloud option aimed at simple, quick voice generation. Requires API key."
                            ];
                            $ttsLower = strtolower((string)$ttsSelRender);
                            echo htmlspecialchars($ttsDescMap[$ttsLower] ?? '');
                            ?>
                        </div>
                    </div>
                </div>
                <?php $ttsKeyCur = $ttsMap[$ttsSelRender] ?? ''; $ttsSchemaCur = ($ttsKeyCur && isset($providersTts[$ttsKeyCur]) && is_array($providersTts[$ttsKeyCur])) ? $providersTts[$ttsKeyCur] : []; $HOST_IP=''; $WSL_IP=''; if ($ttsKeyCur==='XVASYNTH' || $ttsKeyCur==='XTTSFASTAPI'){ try { if (!isset($GLOBALS['db']) || !$GLOBALS['db']) { @include_once($enginePath.'conf'.DIRECTORY_SEPARATOR.'conf.php'); if (isset($GLOBALS['DBDRIVER'])) { @require_once($enginePath.'lib'.DIRECTORY_SEPARATOR.$GLOBALS['DBDRIVER'].'.class.php'); } $GLOBALS['db'] = new sql(); } $row = $GLOBALS['db']->fetchOne("SELECT value FROM conf_opts WHERE id='Network/HOST_IP' LIMIT 1"); if (is_array($row) && isset($row['value'])) { $HOST_IP = (string)$row['value']; } $row2 = $GLOBALS['db']->fetchOne("SELECT value FROM conf_opts WHERE id='Network/WSL_IP' LIMIT 1"); if (is_array($row2) && isset($row2['value'])) { $WSL_IP = (string)$row2['value']; } } catch (Throwable $_e) { $HOST_IP=''; $WSL_IP=''; } } ?>
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
                        foreach ($ttsSchemaCur as $fname => $def): if (!is_array($def)) continue; $ftype=$def['type']??'string'; $plain='TTS '.$ttsKeyCur.' '.$fname; $current=$currentConf[$plain]['currentValue']??''; $help=$def['description']??''; $lname=strtolower($fname); $lnameNorm=str_replace(['_','-'],'',$lname); if ($lnameNorm==='voiceid' || $lnameNorm==='voicelogic') continue; if ($ttsKeyCur==='XVASYNTH' && $lname==='model') continue; 
                            // API KEY badge handling for known providers
                            $provLower = strtolower($ttsKeyCur);
                            if ($fname === 'API_KEY' && in_array($provLower, ['azure','eleven_labs','openai','deepgram'])) {
                                $badgeName = ($provLower==='eleven_labs') ? 'ElevenLabs' : ucfirst($provLower);
                                $hasKey=false; foreach ($apiBadges as $r){ if (strtolower((string)($r['label']??''))===strtolower($badgeName) && trim((string)($r['api_key']??''))!==''){ $hasKey=true; break; } }
                                echo '<div>API Badge ('.htmlspecialchars($badgeName).')</div>';
                                echo '<div>'.($hasKey?'<span style="color:#6dd19c">Configured</span>':'<span style="color:#ffb862">Missing</span>').' — <a href="#" onclick="try{ if(window.top){ window.top.location.href=\''.htmlspecialchars($webRoot).'/ui/core/config_hub.php?tab=keys\'; } else { window.location.href=\''.htmlspecialchars($webRoot).'/ui/core/api_badge.php?embed=1\'; } }catch(e){ window.location.href=\''.htmlspecialchars($webRoot).'/ui/core/api_badge.php?embed=1\'; } return false;">Manage Keys</a></div>';
                                if (!empty($help)) echo '<div class="help">'.$help.'</div>';
                                continue;
                            }
                        ?>
                            <label for="tts_<?php echo htmlspecialchars($fname); ?>"><?php echo htmlspecialchars($fname); ?></label>
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
                                <?php if ($ttsKeyCur==='XVASYNTH'): ?>
                                    <div style="margin-top:6px;">
                                        <button type="button" id="btn_host_ip_xvasynth" class="btn-primary" data-ip="<?php echo htmlspecialchars($HOST_IP); ?>">Host PC IP</button>
                                        <script>(function(){ try{ var b=document.getElementById('btn_host_ip_xvasynth'); var inp=document.getElementById('tts_url'); if(b && inp){ b.addEventListener('click', function(){ var ip=(b.getAttribute('data-ip')||'').trim(); if(!ip){ try{ alert('Host IP not set. Configure Network/HOST_IP in Settings.'); }catch(_){} return; } var v='http://'+ip+':8008'; inp.value=v; try{ inp.dispatchEvent(new Event('input', { bubbles:true })); }catch(_){} try{ inp.dispatchEvent(new Event('change', { bubbles:true })); }catch(_){} }); } }catch(_e){} })();</script>
                                    </div>
                                <?php elseif ($ttsKeyCur==='XTTSFASTAPI' && strtolower($fname)==='endpoint'): ?>
                                    <div style="margin-top:6px;">
                                        <button type="button" id="btn_host_ip_xtts" class="btn-primary" data-ip="<?php echo htmlspecialchars($HOST_IP); ?>">Host PC IP</button>
                                        <button type="button" id="btn_wsl_ip_xtts" class="btn-primary" data-ip="<?php echo htmlspecialchars($WSL_IP); ?>">WSL IP</button>
                                        <script>(function(){ try{ var bh=document.getElementById('btn_host_ip_xtts'); var bw=document.getElementById('btn_wsl_ip_xtts'); var inp=document.getElementById('tts_endpoint'); function setHost(ip){ if(!ip){ try{ alert('Host IP not set. Configure Network/HOST_IP in Settings.'); }catch(_){} return; } try{ var u = new URL(inp.value||('http://'+ip+':8020')); u.protocol = 'http:'; u.hostname = ip; u.port = '8020'; inp.value = u.toString(); } catch(e){ inp.value = 'http://'+ip+':8020'; } try{ inp.dispatchEvent(new Event('input', { bubbles:true })); }catch(_){} try{ inp.dispatchEvent(new Event('change', { bubbles:true })); }catch(_){} }
                                        function setWsl(ip){ if(!ip){ try{ alert('WSL IP not set. Configure Network/WSL_IP in Settings.'); }catch(_){} return; } try{ var u = new URL(inp.value||('http://'+ip+':8020')); u.protocol='http:'; u.hostname=ip; u.port='8020'; inp.value = u.toString(); } catch(e){ inp.value = 'http://'+ip+':8020'; } try{ inp.dispatchEvent(new Event('input', { bubbles:true })); }catch(_){} try{ inp.dispatchEvent(new Event('change', { bubbles:true })); }catch(_){} }
                                        if(bh && inp){ bh.addEventListener('click', function(){ setHost((bh.getAttribute('data-ip')||'').trim()); }); }
                                        if(bw && inp){ bw.addEventListener('click', function(){ setWsl((bw.getAttribute('data-ip')||'').trim()); }); }
                                        }catch(_e){} })();</script>
                                    </div>
                                <?php endif; ?>
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

                <div class="provider-card">
                    <div class="provider-head">
                        <div class="provider-title">
                            <div class="provider-icon">🧑‍🎤</div>
                            <div>Player TTS</div>
                        </div>
                    </div>
                    <?php $descTtsPlayer = (string)($rawSchema['TTSFUNCTION_PLAYER']['description'] ?? ''); $descPlayerVoice = (string)($rawSchema['TTSFUNCTION_PLAYER_VOICE']['description'] ?? ''); $descPlayerVoiceId = (string)($rawSchema['TTSFUNCTION_PLAYER_VOICE_ID']['description'] ?? ''); $descPlayerLang = (string)($rawSchema['TTSFUNCTION_PLAYER_LANGUAGE']['description'] ?? ''); ?>
                    <div class="provider-body grid">
                        <label for="TTSFUNCTION_PLAYER">Player TTS Selection</label>
                        <?php $playerTtsOptions = $rawSchema['TTSFUNCTION_PLAYER']['values'] ?? [ 'none','melotts','xtts-fastapi','xvasynth','mimic3','piper-tts','azure','11labs','openai','kokoro','zonos_gradio' ]; $playerFunctionSaved = current_value('TTSFUNCTION_PLAYER',$currentConf); ?>
                        <select name="TTSFUNCTION_PLAYER" id="TTSFUNCTION_PLAYER">
                            <?php foreach ($playerTtsOptions as $opt): ?>
                                <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ((string)$playerFunctionSaved===(string)$opt?'selected':''); ?>><?php echo htmlspecialchars($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($descTtsPlayer)): ?><div class="help"><?php echo $descTtsPlayer; ?></div><?php endif; ?>
                        <label for="TTSFUNCTION_PLAYER_VOICE">Player Voice</label>
                        <input type="text" id="TTSFUNCTION_PLAYER_VOICE" name="TTSFUNCTION_PLAYER_VOICE" value="<?php echo htmlspecialchars((string)current_value('TTSFUNCTION_PLAYER_VOICE',$currentConf)); ?>">
                        <?php if (!empty($descPlayerVoice)): ?><div class="help"><?php echo $descPlayerVoice; ?></div><?php endif; ?>
                        <label for="TTSFUNCTION_PLAYER_VOICE_ID">Player Voice ID</label>
                        <input type="number" step="1" id="TTSFUNCTION_PLAYER_VOICE_ID" name="TTSFUNCTION_PLAYER_VOICE_ID" value="<?php echo htmlspecialchars((string)current_value('TTSFUNCTION_PLAYER_VOICE_ID',$currentConf)); ?>">
                        <?php if (!empty($descPlayerVoiceId)): ?><div class="help"><?php echo $descPlayerVoiceId; ?></div><?php endif; ?>
                        <label for="TTSFUNCTION_PLAYER_LANGUAGE">Player Language Override</label>
                        <input type="text" id="TTSFUNCTION_PLAYER_LANGUAGE" name="TTSFUNCTION_PLAYER_LANGUAGE" value="<?php echo htmlspecialchars((string)current_value('TTSFUNCTION_PLAYER_LANGUAGE',$currentConf)); ?>">
                        <?php if (!empty($descPlayerLang)): ?><div class="help"><?php echo $descPlayerLang; ?></div><?php endif; ?>
                    </div>
                </div>

                <div class="provider-card">
                    <div class="provider-head">
                        <div class="provider-title">
                            <div class="provider-icon">🧪</div>
                            <div>TTS Test</div>
                        </div>
                    </div>
                    <div class="provider-body grid">
                        <label for="tts_text">Text to synthesize</label>
                        <textarea id="tts_text" name="tts_text" rows="3" placeholder="Write a sample line to synthesize..."><?php echo htmlspecialchars($ttsTestText); ?></textarea>
                        <div></div>
                        <div>
                            <label for="tts_voiceid">Voice ID (optional)</label>
                            <input type="text" id="tts_voiceid" name="tts_voiceid" value="<?php echo htmlspecialchars($ttsTestVoice); ?>" placeholder="e.g. TheNarrator or malenord" style="width:100%">
                            <script>
                            (function(){
                                try {
                                    var sel = document.getElementById('TTSFUNCTION');
                                    var voice = document.getElementById('tts_voiceid');
                                    if (sel && voice && !voice.value) {
                                        var v = (sel.value||'').toLowerCase();
                                        if (v==='xtts-fastapi') voice.placeholder = 'TheNarrator';
                                        else if (v==='melotts' || v==='piper-tts' || v==='xvasynth') voice.placeholder = 'malenord';
                                    }
                                    if (sel && voice){
                                        sel.addEventListener('change', function(){
                                            if (voice && !voice.value){
                                                var vv = String(sel.value||'').toLowerCase();
                                                voice.placeholder = (vv==='xtts-fastapi') ? 'TheNarrator' : (['melotts','piper-tts','xvasynth'].indexOf(vv)>=0 ? 'malenord' : '');
                                            }
                                        });
                                    }
                                } catch(e){}
                            })();
                            </script>
                        </div>
                        <div>
                            <button type="button" id="btn_test_tts_gs" class="btn-primary">Test</button>
                        </div>
                        <div></div>
                        <div>
                            <?php if (!empty($ttsTestOutputUrl)): ?>
                                <audio controls style="width:100%; max-width:500px"><source src="<?php echo htmlspecialchars($ttsTestOutputUrl); ?>" type="audio/wav"></audio>
                                <input type="hidden" id="tts_test_audio_url_hidden" value="<?php echo htmlspecialchars($ttsTestOutputUrl); ?>">
                            <?php elseif (isset($_POST['tts_quick_test'])): ?>
                                <div style="color:#ffb862">No audio produced. Check connector settings and logs.</div>
                                <input type="hidden" id="tts_test_audio_url_hidden" value="">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-section" id="tab-stt" style="display:none;">
            <h2>Speech-to-Text</h2>
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
                            <?php foreach ($sttOptions as $opt): ?>
                                <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ((string)$sttSelRender===(string)$opt?'selected':''); ?>><?php echo htmlspecialchars($opt); ?></option>
                            <?php endforeach; ?>
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
                            if ($fname === 'API_KEY' && in_array($lnameProv, ['whisper','azure','deepgram'])) {
                                $badgeName = ($lnameProv==='whisper') ? 'OpenAI' : ucfirst($lnameProv);
                                $hasKey=false; foreach ($apiBadges as $r){ if (strtolower((string)($r['label']??''))===strtolower($badgeName) && trim((string)($r['api_key']??''))!==''){ $hasKey=true; break; } }
                                echo '<div>API Badge ('.htmlspecialchars($badgeName).')</div>';
                                echo '<div>'.($hasKey?'<span style="color:#6dd19c">Configured</span>':'<span style="color:#ffb862">Missing</span>').' — <a href="#" onclick="try{ if(window.top){ window.top.location.href=\''.htmlspecialchars($webRoot).'/ui/core/config_hub.php?tab=keys\'; } else { window.location.href=\''.htmlspecialchars($webRoot).'/ui/core/api_badge.php?embed=1\'; } }catch(e){ window.location.href=\''.htmlspecialchars($webRoot).'/ui/core/api_badge.php?embed=1\'; } return false;">Manage Keys</a></div>';
                                if (!empty($help)) echo '<div class="help">'.$help.'</div>';
                                continue;
                            }
                        ?>
                            <label for="stt_<?php echo htmlspecialchars($fname); ?>"><?php echo htmlspecialchars($fname); ?></label>
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
                            <label for="itt_<?php echo htmlspecialchars($fname); ?>"><?php echo htmlspecialchars($fname); ?></label>
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
      ids.forEach(function(x){ var el=document.getElementById(x); if(el){ el.style.display=(x===id?'block':'none'); }});
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


