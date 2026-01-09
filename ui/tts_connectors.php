<?php

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf_loader.php");
// Load defaults first, then override with actual configuration (prevents fallback to sample values)
@include_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.sample.php");
// Capture sample defaults for Player TTS before overriding with conf.php
$samplePlayerDefaults = [
    'TTSFUNCTION_PLAYER' => $GLOBALS['TTSFUNCTION_PLAYER'] ?? null,
    'TTSFUNCTION_PLAYER_VOICE' => $GLOBALS['TTSFUNCTION_PLAYER_VOICE'] ?? null,
    'TTSFUNCTION_PLAYER_VOICE_ID' => $GLOBALS['TTSFUNCTION_PLAYER_VOICE_ID'] ?? null,
    'TTSFUNCTION_PLAYER_LANGUAGE' => $GLOBALS['TTSFUNCTION_PLAYER_LANGUAGE'] ?? null,
];
@include_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "online_translation.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php");

// Determine web root (match other pages)
$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
if ($uiPos !== false) { $webRoot = substr($scriptPath, 0, $uiPos); } else { $webRoot = ''; }
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

// Load raw schema for rendering provider fields
$schemaPath = $enginePath . "conf" . DIRECTORY_SEPARATOR . "conf_schema.json";
$rawSchema = @json_decode(@file_get_contents($schemaPath), true);
if (!is_array($rawSchema)) $rawSchema = [];
$providersAll = is_array($rawSchema['TTS'] ?? null) ? $rawSchema['TTS'] : [];
$ttsOptions = $rawSchema['TTSFUNCTION']['values'] ?? [ 'mimic3','melotts','xtts-fastapi','xvasynth','azure','11labs','openai','koboldcpp','zonos_gradio','piper-tts','kokoro','deepgram' ];
// Player TTS options
$playerTtsOptions = $rawSchema['TTSFUNCTION_PLAYER']['values'] ?? [ 'none','melotts','xtts-fastapi','xvasynth','mimic3','piper-tts','azure','11labs','openai','kokoro','zonos_gradio' ];

// Current configuration (flattened values and titles from loader)
$currentConf = conf_loader_load();

// TTS Quick Test handler
$ttsTestOutputUrl = '';
$ttsTestTextDefault = "In Skyrim's land of snow and ice, Where dragons soar and souls entwine, Heroes rise, their fate unveiled, As ancient tales, the land does bind.";
$ttsTestText = $ttsTestTextDefault;
$ttsTestVoice = '';

// Default voice per provider for the quick test (only applied if the field is blank)
$__selLower = strtolower($selectedFunction);
$__defaultVoice = ($__selLower === 'xtts-fastapi') ? 'TheNarrator' : (in_array($__selLower, ['melotts','piper-tts','xvasynth'], true) ? 'malenord' : '');
if ($ttsTestVoice === '') { $ttsTestVoice = $__defaultVoice; }

try { if (!isset($GLOBALS['db']) || !$GLOBALS['db']) $GLOBALS['db'] = new sql(); } catch (Throwable $_) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tts_quick_test'])) {
    $ttsTestText = isset($_POST['tts_text']) ? (string)$_POST['tts_text'] : '';
    if (trim($ttsTestText) === '') {
        $ttsTestText = $ttsTestTextDefault;
    }
    $ttsTestVoice = isset($_POST['tts_voiceid']) ? trim((string)$_POST['tts_voiceid']) : '';
    // Allow previewing current dropdown provider without saving
    if (isset($_POST['TTSFUNCTION']) && is_string($_POST['TTSFUNCTION'])) {
        $selectedFunction = (string)$_POST['TTSFUNCTION'];
    }
    // Prepare globals for TTS (minimal runtime context expected by returnLines and TTS backends)
    $GLOBALS["TTSFUNCTION"] = $selectedFunction;
    $GLOBALS["HERIKA_NAME"] = "The Narrator";
    $GLOBALS["AVOID_TTS_CACHE"] = true;
    $GLOBALS["TTS_FFMPEG_FILTERS"] = [];
    $GLOBALS["HERIKA_ANIMATIONS"] = false;
    $GLOBALS["SCRIPTLINE_LISTENER"] = '';
    $GLOBALS["SCRIPTLINE_EXPRESSION"] = '';
    $GLOBALS["DEBUG_DATA"] = [];
    $GLOBALS["FEATURES"] = $GLOBALS["FEATURES"] ?? [];
    if (!isset($GLOBALS["FEATURES"]["MISC"])) $GLOBALS["FEATURES"]["MISC"] = [];
    if (!isset($GLOBALS["FEATURES"]["MISC"]["TTS_RANDOM_PITCH"])) $GLOBALS["FEATURES"]["MISC"]["TTS_RANDOM_PITCH"] = false;
    if (!isset($GLOBALS["PLAYER_NAME"])) $GLOBALS["PLAYER_NAME"] = 'Player';
    // Fake gameRequest indices used by logging in returnLines
    $gameRequest = [ 'tts_quick_test', time(), time(), '' ];
    $startTime = microtime(true);
    // Voice override mapping (custom input wins)
    $selLower = strtolower($selectedFunction);
    if ($ttsTestVoice !== '') {
        $GLOBALS["PATCH_OVERRIDE_VOICE"] = $ttsTestVoice;
    } else {
        if ($selLower === 'xtts-fastapi') {
            $GLOBALS["PATCH_OVERRIDE_VOICE"] = 'TheNarrator';
        } else {
            $GLOBALS["PATCH_OVERRIDE_VOICE"] = 'malenord';
        }
    }
    try {
        // Optional translation flow (kept simple)
        // Translation::translate($ttsTestText);
        // $speakText = Translation::$response ?: $ttsTestText;
        $speakText = $ttsTestText;
        // Generate TTS without streaming
        returnLines([$speakText], false);
        $file = isset($GLOBALS["TRACK"]["FILES_GENERATED"][0]) ? basename((string)$GLOBALS["TRACK"]["FILES_GENERATED"][0]) : '';
        if ($file !== '') {
            $ttsTestOutputUrl = $webRoot . '/soundcache/' . $file . '?ts=' . time();
        }
    } catch (Throwable $e) {
        Logger::error('TTS quick test failed: '.$e->getMessage());
        $ttsTestOutputUrl = '';
    }
    unset($GLOBALS["PATCH_OVERRIDE_VOICE"]);
}

// Helpers
function tts_current_value(string $flatName, array $currentConf) {
	$plain = strtr($flatName, ["@" => " "]);
	$parms = $currentConf[$plain] ?? null;
	if (!$parms) return '';
	return $parms['currentValue'] ?? '';
}

// Mapping from dropdown value -> provider key in schema
$ttsMap = [
	'melotts' => 'MELOTTS',
	'xtts-fastapi' => 'XTTSFASTAPI',
	'mimic3' => 'MIMIC3',
	'xvasynth' => 'XVASYNTH',
	'azure' => 'AZURE',
	'11labs' => 'ELEVEN_LABS',
	'openai' => 'openai',
	'kokoro' => 'KOKORO',
	'koboldcpp' => 'koboldcpp',
	'zonos_gradio' => 'ZONOS_GRADIO',
	'piper-tts' => 'PIPERTTS',
	'cartesia' => 'CARTESIA',
	'inworld' => 'INWORLD',
	'deepgram' => 'deepgram',
];
// Display name mappings for UI labels
$ttsDisplayNames = [ 'xtts-fastapi' => 'xtts/chatterbox' ];

// Selected provider: prefer saved value; only use POST for preview unless saving
$selectedFunction = tts_current_value('TTSFUNCTION', $currentConf);
if (isset($_POST['TTSFUNCTION']) && is_string($_POST['TTSFUNCTION']) && !isset($_POST['save_all'])) {
	$selectedFunction = (string)$_POST['TTSFUNCTION'];
}
if ($selectedFunction === '' && !empty($ttsOptions)) $selectedFunction = $ttsOptions[0];
$providerKey = $ttsMap[$selectedFunction] ?? '';
$providerSchema = ($providerKey && isset($providersAll[$providerKey]) && is_array($providersAll[$providerKey])) ? $providersAll[$providerKey] : [];

// Track posted selection for save operations
$postedFunction = (isset($_POST['TTSFUNCTION']) && is_string($_POST['TTSFUNCTION'])) ? (string)$_POST['TTSFUNCTION'] : null;

// Current Player TTS selections
$playerFunctionSaved = tts_current_value('TTSFUNCTION_PLAYER', $currentConf);
if ($playerFunctionSaved === '') {
    $playerFunctionSaved = (string)($samplePlayerDefaults['TTSFUNCTION_PLAYER'] ?? ($playerTtsOptions[0] ?? 'none'));
}
$playerVoice = tts_current_value('TTSFUNCTION_PLAYER_VOICE', $currentConf);
if ($playerVoice === '') { $playerVoice = (string)($samplePlayerDefaults['TTSFUNCTION_PLAYER_VOICE'] ?? ''); }
$playerVoiceId = tts_current_value('TTSFUNCTION_PLAYER_VOICE_ID', $currentConf);
if ($playerVoiceId === '') { $playerVoiceId = (string)($samplePlayerDefaults['TTSFUNCTION_PLAYER_VOICE_ID'] ?? ''); }
$playerLanguage = tts_current_value('TTSFUNCTION_PLAYER_LANGUAGE', $currentConf);
if ($playerLanguage === '') { $playerLanguage = (string)($samplePlayerDefaults['TTSFUNCTION_PLAYER_LANGUAGE'] ?? ''); }

// Descriptions for Player TTS fields (from conf schema)
$descTtsPlayer = (string)($rawSchema['TTSFUNCTION_PLAYER']['description'] ?? '');
$descPlayerVoice = (string)($rawSchema['TTSFUNCTION_PLAYER_VOICE']['description'] ?? '');
$descPlayerVoiceId = (string)($rawSchema['TTSFUNCTION_PLAYER_VOICE_ID']['description'] ?? '');
$descPlayerLang = (string)($rawSchema['TTSFUNCTION_PLAYER_LANGUAGE']['description'] ?? '');

// Save handler: write TTSFUNCTION + shown provider fields to conf.php
$saveSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_all'])) {
	$confSchemaFlat = conf_loader_load_schema();
	// Reload latest configuration to avoid overwriting changes from other pages
	$confFile = $enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php";
	@clearstatcache(true, $confFile);
	$currentConf = conf_loader_load();
	$allPairs = [];
	// Preserve all existing pairs from currentConf
	foreach ($currentConf as $pname => $parms) {
		$fieldName = strtr($pname, [" " => "@"]); // flatten
		$type = $parms['type'] ?? ($confSchemaFlat[$pname]['type'] ?? 'string');
		$val = $parms['currentValue'] ?? '';
		if ($type === 'boolean') $allPairs[$fieldName] = $val ? 'true' : 'false';
		else if ($type === 'selectmultiple') $allPairs[$fieldName] = is_array($val) ? $val : [];
		else $allPairs[$fieldName] = (string)$val;
	}
	// Use currently selected in the form when saving
	if ($postedFunction !== null) {
		$selectedFunction = $postedFunction;
	}
	$allPairs['TTSFUNCTION'] = (string)$selectedFunction;

	// Recompute provider schema for the function we are saving
	$saveProviderKey = $ttsMap[$selectedFunction] ?? '';
	$saveProviderSchema = ($saveProviderKey && isset($providersAll[$saveProviderKey]) && is_array($providersAll[$saveProviderKey])) ? $providersAll[$saveProviderKey] : [];

	// Overwrite selected provider fields
	if ($saveProviderKey && is_array($saveProviderSchema)) {
		foreach ($saveProviderSchema as $fname => $def) {
			if (!is_array($def)) continue;
			$type = $def['type'] ?? 'string';
			$key = 'TTS@' . $saveProviderKey . '@' . $fname;
			if ($type === 'boolean') {
				$allPairs[$key] = (isset($_POST[$fname]) && $_POST[$fname] === 'true') ? 'true' : 'false';
			} else if ($type === 'selectmultiple') {
				$allPairs[$key] = isset($_POST[$fname]) && is_array($_POST[$fname]) ? array_values($_POST[$fname]) : [];
			} else {
				if (isset($_POST[$fname])) $allPairs[$key] = (string)$_POST[$fname];
			}
		}
	}

	// Overwrite Player TTS fields if present
	if (isset($_POST['TTSFUNCTION_PLAYER'])) {
		$allPairs['TTSFUNCTION_PLAYER'] = (string)$_POST['TTSFUNCTION_PLAYER'];
	}
	if (isset($_POST['TTSFUNCTION_PLAYER_VOICE'])) {
		$allPairs['TTSFUNCTION_PLAYER_VOICE'] = (string)$_POST['TTSFUNCTION_PLAYER_VOICE'];
	}
	if (isset($_POST['TTSFUNCTION_PLAYER_VOICE_ID'])) {
		$allPairs['TTSFUNCTION_PLAYER_VOICE_ID'] = (string)$_POST['TTSFUNCTION_PLAYER_VOICE_ID'];
	}
	if (isset($_POST['TTSFUNCTION_PLAYER_LANGUAGE'])) {
		$allPairs['TTSFUNCTION_PLAYER_LANGUAGE'] = (string)$_POST['TTSFUNCTION_PLAYER_LANGUAGE'];
	}

	// Player Re-speech fields handled in Global Settings

	// Build conf.php content (match writer style)
	$buffer = "<?php" . PHP_EOL;
	$oldGroup = '';
	$oldSubGroup = '';
	$process_slashes = function(string $s_input): string { $sx = str_replace("\\'", "'", $s_input); return addcslashes($sx, "'"); };
	foreach ($allPairs as $k => $v) {
		$full = explode('@', $k);
		$plain = strtr($k, ['@' => ' ']);
		$type = $confSchemaFlat[$plain]['type'] ?? 'string';
		if (is_array($v)) $value = json_encode($v, true);
		else if ($type === 'number') { if ($v === '') continue; else $value = "" . addcslashes($v, "'") . ""; }
		else if ($type === 'boolean') $value = ($v === 'true') ? 'true' : 'false';
		else $value = "'" . $process_slashes((string)$v) . "'";
		if ($oldGroup !== $full[0]) { $buffer .= PHP_EOL . PHP_EOL; $oldGroup = $full[0]; }
		if (isset($full[1]) && $oldSubGroup !== $full[1]) { $buffer .= PHP_EOL; $oldSubGroup = $full[1]; }
		if (count($full) === 1) { if (isset($confSchemaFlat[$plain]['description'])) $buffer .= "//" . $confSchemaFlat[$plain]['description'] . PHP_EOL; $buffer .= '$' . $full[0] . '=' . $value . ';' . PHP_EOL; }
		else if (count($full) === 2) { $inline = isset($confSchemaFlat[$plain]['description']) ? ("//" . $confSchemaFlat[$plain]['description']) : ''; $buffer .= '$' . $full[0] . '["' . $full[1] . '"]=' . $value . ';' . "\t" . $inline . PHP_EOL; }
		else if (count($full) === 3) { $inline = isset($confSchemaFlat[$plain]['description']) ? ("//" . $confSchemaFlat[$plain]['description']) : ''; $buffer .= '$' . $full[0] . '["' . $full[1] . '"]["' . $full[2] . '"]=' . $value . ';' . "\t" . $inline . PHP_EOL; }
	}
	$buffer .= "?>" . PHP_EOL;

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
		// Ensure the updated configuration file is visible immediately on next request
		@clearstatcache(true, $target);
		if (function_exists('opcache_invalidate')) { @opcache_invalidate($target, true); }
		Logger::info("TTS settings saved to conf.php by UI");
		// Hard redirect to ensure UI reflects new saved values and avoid resubmission
		while (@ob_end_clean());
		$redirectUrl = strtok($_SERVER['REQUEST_URI'], '?') . '?_ts=' . time();
		header("Location: " . $redirectUrl);
		exit;
	} else {
		Logger::error("Failed writing conf.php from TTS Connectors UI");
	}
}

$TITLE = "🔊 CHIM - TTS Connectors";
ob_start();
include(__DIR__.DIRECTORY_SEPARATOR."tmpl".DIRECTORY_SEPARATOR."head.html");
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
main { padding-top: 40px; padding-bottom: 40px; padding-left: 10%; padding-right: 10%; width: 100%; margin: 0; }
footer { position: fixed; bottom: 0; width: 100%; height: 20px; background: #031633; z-index: 100; }
@font-face { font-family: 'MagicCards'; src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype'); }
h1.tts-title { margin:0 0 20px 0; font-family:'MagicCards', serif; word-spacing:8px; font-size:2.2em; color:rgb(242,124,17); text-shadow:2px 2px 4px rgba(0,0,0,0.5); text-align:center; }
.content-section { background:#2a2a2a; padding:25px; border-radius:8px; border:1px solid #4a4a4a; }
.provider-grid { display:grid; grid-template-columns: 1fr; gap:12px; }
.provider-card { background:#2a2a2a; border:1px solid #4a4a4a; border-radius:8px; padding:12px; }
.provider-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px; }
.provider-title { display:flex; align-items:center; gap:10px; color:#e0e0e0; }
.provider-icon { width:28px; height:28px; border-radius:6px; background:#3a3a3a; display:flex; align-items:center; justify-content:center; font-size:16px; }
.provider-body { display:grid; grid-template-columns: 220px 1fr; gap:8px 12px; align-items:center; }
.provider-body input, .provider-body select, .provider-body textarea { background-color:#333; color:#fff; border:1px solid #444; border-radius:4px; padding:8px; }
.actions { display:flex; justify-content:flex-end; margin-top:10px; }
.btn-primary { background:#204e7a; color:#fff; border:1px solid rgba(138,155,182,0.4); border-radius:8px; padding:8px 14px; cursor:pointer; }
.btn-primary:hover { background:#285c8f; }
.help { margin-top:6px; color:#bbb; font-size:12px; grid-column: 1 / -1; }
@media (max-width: 900px) { main { padding-left: 5%; padding-right: 5%; } .provider-body { grid-template-columns: 1fr; } }
</style>

<main>
	<h1 class="tts-title">TTS Connectors</h1>
	<div id="toast" class="toast-notification" style="display:none;"><span class="message"></span></div>

	<?php if ($saveSuccess): ?>
		<script>setTimeout(function(){ try{ const t=document.getElementById('toast'); if(t){ t.style.display='block'; t.textContent='Settings saved to conf.php'; setTimeout(()=>{ t.style.display='none'; }, 2500); } }catch(_e){} }, 50);</script>
	<?php endif; ?>

	<form method="post" action="">
		<div class="content-section">
			<div class="provider-grid">
				<div class="provider-card">
					<div class="provider-head">
						<div class="provider-title">
							<div class="provider-icon">🔊</div>
							<div>TTS Provider</div>
						</div>
					</div>
					<div class="provider-body">
						<label for="TTSFUNCTION">TTS Selection</label>
                        <select name="TTSFUNCTION" id="TTSFUNCTION" onchange="this.form.submit()">
							<?php foreach ($ttsOptions as $opt): ?>
								<option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ((string)$selectedFunction===(string)$opt?'selected':''); ?>><?php echo htmlspecialchars($ttsDisplayNames[$opt] ?? $opt); ?></option>
							<?php endforeach; ?>
						</select>
						<div></div>
						<div class="help">Quick test will synthesize a short sample using: <b><?php echo htmlspecialchars(strtolower($selectedFunction)==='xtts-fastapi'?'TheNarrator':'malenord'); ?></b></div>
					</div>
				</div>

				<?php if (!empty($providerSchema)): ?>
					<div class="provider-card">
						<div class="provider-head">
							<div class="provider-title">
								<div class="provider-icon">⚙️</div>
								<div><?php echo htmlspecialchars($providerKey); ?> Settings</div>
							</div>
						</div>
						<div class="provider-body">
							<?php
							// Load API badges for status (once)
							$apiBadges = [];
							try { if (!isset($GLOBALS['db']) || !$GLOBALS['db']) $GLOBALS['db'] = new sql(); $apiBadges = $GLOBALS['db']->fetchAll("SELECT id,label,api_key FROM core_api_badge ORDER BY label ASC"); } catch (Throwable $_e) {}
							foreach ($providerSchema as $fname => $def): if (!is_array($def)) continue; $lname = strtolower($fname); $lnameNorm = str_replace(['_','-'],'',$lname); if ($lnameNorm === 'voiceid' || $lnameNorm === 'voicelogic') continue; $ftype = $def['type'] ?? 'string'; $plainName = 'TTS ' . $providerKey . ' ' . $fname; $current = $currentConf[$plainName]['currentValue'] ?? ''; $help = $def['description'] ?? '';
								// API badge status for known providers
								$provLower = strtolower($providerKey);
								if ($fname === 'API_KEY' && in_array($provLower, ['azure','eleven_labs','openai','deepgram'])) {
									$badgeName = ($provLower==='eleven_labs') ? 'ElevenLabs' : ucfirst($provLower);
									$hasKey = false;
									foreach ($apiBadges as $r){ if (strtolower((string)($r['label']??''))===strtolower($badgeName) && trim((string)($r['api_key']??''))!==''){ $hasKey=true; break; } }
									echo '<div>API Badge ('.htmlspecialchars($badgeName).')</div>';
									echo '<div>'.($hasKey?'<span style="color:#6dd19c">Configured</span>':'<span style="color:#ffb862">Missing</span>').' — <a href="#" onclick="try{ if(window.top){ window.top.location.href=\''.htmlspecialchars($webRoot).'/ui/core/config_hub.php?tab=keys\'; } else { window.location.href=\''.htmlspecialchars($webRoot).'/ui/core/api_badge.php?embed=1\'; } }catch(e){ window.location.href=\''.htmlspecialchars($webRoot).'/ui/core/api_badge.php?embed=1\'; } return false;">Manage Keys</a></div>';
									if (!empty($help)) echo '<div class="help">'.$help.'</div>';
									continue;
								}
							?>
								<label for="f_<?php echo htmlspecialchars($fname); ?>"><?php echo htmlspecialchars($fname); ?></label>
								<?php if ($ftype === 'boolean'): ?>
									<input type="hidden" name="<?php echo htmlspecialchars($fname); ?>" value="false">
									<input type="checkbox" id="f_<?php echo htmlspecialchars($fname); ?>" name="<?php echo htmlspecialchars($fname); ?>" value="true" <?php echo ($current ? 'checked' : ''); ?> style="width:auto;">
								<?php elseif ($ftype === 'integer'): ?>
									<input type="number" step="1" id="f_<?php echo htmlspecialchars($fname); ?>" name="<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>">
								<?php elseif ($ftype === 'number'): ?>
									<input type="number" step="0.01" id="f_<?php echo htmlspecialchars($fname); ?>" name="<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>">
								<?php elseif ($ftype === 'longstring'): ?>
									<textarea id="f_<?php echo htmlspecialchars($fname); ?>" name="<?php echo htmlspecialchars($fname); ?>" rows="3"><?php echo htmlspecialchars((string)$current); ?></textarea>
								<?php elseif ($ftype === 'url'): ?>
									<input type="url" id="f_<?php echo htmlspecialchars($fname); ?>" name="<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>">
								<?php elseif ($ftype === 'select'): $values = $def['values'] ?? []; ?>
									<select id="f_<?php echo htmlspecialchars($fname); ?>" name="<?php echo htmlspecialchars($fname); ?>">
										<?php foreach ($values as $opt): ?>
											<option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ((string)$current===(string)$opt?'selected':''); ?>><?php echo htmlspecialchars($opt); ?></option>
										<?php endforeach; ?>
									</select>
								<?php elseif ($ftype === 'apikey'): ?>
									<input type="password" id="f_<?php echo htmlspecialchars($fname); ?>" name="<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>" placeholder="Paste API key">
								<?php else: ?>
									<input type="text" id="f_<?php echo htmlspecialchars($fname); ?>" name="<?php echo htmlspecialchars($fname); ?>" value="<?php echo htmlspecialchars((string)$current); ?>">
								<?php endif; ?>
								<?php if (!empty($help)): ?><div class="help"><?php echo $help; ?></div><?php endif; ?>
							<?php endforeach; ?>
						</div>
					</div>
				<?php else: ?>
					<div class="provider-card"><div class="provider-body"><div></div><div>No settings available for this provider.</div></div></div>
				<?php endif; ?>

				<!-- Player TTS Settings (below regular TTS settings) -->
				<div class="provider-card">
					<div class="provider-head">
						<div class="provider-title">
							<div class="provider-icon">🧑‍🎤</div>
							<div>Player TTS</div>
						</div>
					</div>
					<div class="provider-body">
						<label for="TTSFUNCTION_PLAYER">Player TTS Selection</label>
						<select name="TTSFUNCTION_PLAYER" id="TTSFUNCTION_PLAYER">
							<?php foreach ($playerTtsOptions as $opt): ?>
								<option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ((string)$playerFunctionSaved===(string)$opt?'selected':''); ?>><?php echo htmlspecialchars($ttsDisplayNames[$opt] ?? $opt); ?></option>
							<?php endforeach; ?>
						</select>
						<?php if (!empty($descTtsPlayer)): ?><div class="help"><?php echo $descTtsPlayer; ?></div><?php endif; ?>

						<label for="TTSFUNCTION_PLAYER_VOICE">Player Voice</label>
						<input type="text" id="TTSFUNCTION_PLAYER_VOICE" name="TTSFUNCTION_PLAYER_VOICE" value="<?php echo htmlspecialchars((string)$playerVoice); ?>">
						<?php if (!empty($descPlayerVoice)): ?><div class="help"><?php echo $descPlayerVoice; ?></div><?php endif; ?>

						<label for="TTSFUNCTION_PLAYER_VOICE_ID">Player Voice ID</label>
						<input type="number" step="1" id="TTSFUNCTION_PLAYER_VOICE_ID" name="TTSFUNCTION_PLAYER_VOICE_ID" value="<?php echo htmlspecialchars((string)$playerVoiceId); ?>">
						<?php if (!empty($descPlayerVoiceId)): ?><div class="help"><?php echo $descPlayerVoiceId; ?></div><?php endif; ?>

						<label for="TTSFUNCTION_PLAYER_LANGUAGE">Player Language Override</label>
						<input type="text" id="TTSFUNCTION_PLAYER_LANGUAGE" name="TTSFUNCTION_PLAYER_LANGUAGE" value="<?php echo htmlspecialchars((string)$playerLanguage); ?>">
						<?php if (!empty($descPlayerLang)): ?><div class="help"><?php echo $descPlayerLang; ?></div><?php endif; ?>


					</div>
				</div>
				<!-- TTS Test -->
				<div class="provider-card">
					<div class="provider-head">
						<div class="provider-title">
							<div class="provider-icon">🧪</div>
							<div>TTS Test</div>
						</div>
					</div>
					<div class="provider-body">
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
                                    var v = sel.value.toLowerCase();
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
							<button type="submit" class="btn-primary" name="tts_quick_test" value="1">Test</button>
						</div>
						<div></div>
						<div>
							<?php if (!empty($ttsTestOutputUrl)): ?>
								<audio controls style="width:100%; max-width:500px"><source src="<?php echo htmlspecialchars($ttsTestOutputUrl); ?>" type="audio/wav"></audio>
							<?php elseif (isset($_POST['tts_quick_test'])): ?>
								<div style="color:#ffb862">No audio produced. Check connector settings and logs.</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
			<div class="actions">
				<button type="submit" class="btn-save" name="save_all" value="1">Save</button>
				<?php if ($saveSuccess): ?>
				<script>
				setTimeout(function(){ window.location.replace(window.location.pathname + window.location.search); }, 100);
				</script>
				<?php endif; ?>
			</div>
		</div>
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


