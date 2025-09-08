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
	'deepgram' => 'deepgram',
];

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

// Player Re-speech controls
$playerRespeech = tts_current_value('PLAYER_RESPEECH', $currentConf);
$playerSpeechStyle = tts_current_value('PLAYER_SPEECH_STYLE', $currentConf);

// Save handler: write TTSFUNCTION + shown provider fields to conf.php
$saveSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_all'])) {
	$confSchemaFlat = conf_loader_load_schema();
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

	// Overwrite Player Re-speech fields
	$allPairs['PLAYER_RESPEECH'] = (isset($_POST['PLAYER_RESPEECH']) && $_POST['PLAYER_RESPEECH'] === 'true') ? 'true' : 'false';
	if (isset($_POST['PLAYER_SPEECH_STYLE'])) {
		$allPairs['PLAYER_SPEECH_STYLE'] = (string)$_POST['PLAYER_SPEECH_STYLE'];
	}

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
	$result = @file_put_contents($target, $buffer);
	$saveSuccess = $result !== false;
	if ($saveSuccess) {
		Logger::info("TTS settings saved to conf.php by UI");
		// Hard redirect to ensure UI reflects new saved values and avoid resubmission
		while (@ob_end_clean());
		header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
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
								<option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ((string)$selectedFunction===(string)$opt?'selected':''); ?>><?php echo htmlspecialchars($opt); ?></option>
							<?php endforeach; ?>
						</select>
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
									echo '<div>'.($hasKey?'<span style="color:#6dd19c">Configured</span>':'<span style="color:#ffb862">Missing</span>').' — <a href="'.htmlspecialchars($webRoot).'/ui/core/api_badge.php" target="_blank" rel="noopener">Manage Keys</a></div>';
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
								<option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ((string)$playerFunctionSaved===(string)$opt?'selected':''); ?>><?php echo htmlspecialchars($opt); ?></option>
							<?php endforeach; ?>
						</select>

						<label for="TTSFUNCTION_PLAYER_VOICE">Player Voice</label>
						<input type="text" id="TTSFUNCTION_PLAYER_VOICE" name="TTSFUNCTION_PLAYER_VOICE" value="<?php echo htmlspecialchars((string)$playerVoice); ?>">

						<label for="TTSFUNCTION_PLAYER_VOICE_ID">Player Voice ID</label>
						<input type="number" step="1" id="TTSFUNCTION_PLAYER_VOICE_ID" name="TTSFUNCTION_PLAYER_VOICE_ID" value="<?php echo htmlspecialchars((string)$playerVoiceId); ?>">

						<label for="TTSFUNCTION_PLAYER_LANGUAGE">Player Language Override</label>
						<input type="text" id="TTSFUNCTION_PLAYER_LANGUAGE" name="TTSFUNCTION_PLAYER_LANGUAGE" value="<?php echo htmlspecialchars((string)$playerLanguage); ?>">

						<label for="PLAYER_RESPEECH">Player Respeech Enabled</label>
						<input type="hidden" name="PLAYER_RESPEECH" value="false">
						<input type="checkbox" id="PLAYER_RESPEECH" name="PLAYER_RESPEECH" value="true" <?php echo ($playerRespeech ? 'checked' : ''); ?> style="width:auto;">

						<label for="PLAYER_SPEECH_STYLE">Player Respeech Style</label>
						<textarea id="PLAYER_SPEECH_STYLE" name="PLAYER_SPEECH_STYLE" rows="3"><?php echo htmlspecialchars((string)$playerSpeechStyle); ?></textarea>
					</div>
				</div>
			</div>
			<div class="actions">
				<button type="submit" class="btn-primary" name="save_all" value="1">Save</button>
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


