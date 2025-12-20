<?php

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . "../../";

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
// Load schema/helpers without requiring a potentially broken conf.php
require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf_loader.php");
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

// Do NOT include profile_loader or conf.php here to avoid fatal parse errors when conf.php is broken

// Load schema and current configuration
$confSchema = conf_loader_load_schema();
$currentConf = conf_loader_load();

// Helpers copied from global_settings.php to write back to conf.php consistently
function tts_flatten_current_conf(array $currentConf, array $confSchema): array {
	$flat = [];
	foreach ($currentConf as $pname => $parms) {
		$fieldName = strtr($pname, [" " => "@"]); // e.g., TTS AZURE voice -> TTS@AZURE@voice
		$type = $parms["type"] ?? ($confSchema[$pname]["type"] ?? 'string');
		$val = $parms["currentValue"] ?? '';
		if ($type === 'boolean') {
			$flat[$fieldName] = $val ? 'true' : 'false';
		} else if ($type === 'selectmultiple') {
			$flat[$fieldName] = is_array($val) ? $val : [];
		} else if ($type === 'number' || $type === 'integer') {
			$flat[$fieldName] = (string)($val === '' ? '' : $val);
		} else {
			if (is_array($val)) {
				$flat[$fieldName] = [];
			} else {
				$flat[$fieldName] = (string)$val;
			}
		}
	}
	return $flat;
}

function tts_build_conf_php_from_pairs(array $pairs, array $confSchema): string {
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
			$buffer .= '$' . $fullNameHierch[0] . '=' . $value . ';' . PHP_EOL;
		} else if (sizeof($fullNameHierch) == 2) {
			$inlineComment = '';
			if (isset($confSchema[$plainNameHierch]["description"]))
				$inlineComment = "//" . $confSchema[$plainNameHierch]["description"];
			$buffer .= '$' . $fullNameHierch[0] . '["' . $fullNameHierch[1] . '"]=' . $value . ';' . "\t" . $inlineComment . PHP_EOL;
		} else if (sizeof($fullNameHierch) == 3) {
			$inlineComment = '';
			if (isset($confSchema[$plainNameHierch]["description"]))
				$inlineComment = "//" . $confSchema[$plainNameHierch]["description"];
			$buffer .= '$' . $fullNameHierch[0] . '["' . $fullNameHierch[1] . '"]["' . $fullNameHierch[2] . '"]=' . $value . ';' . "\t" . $inlineComment . PHP_EOL;
		}
	}

	$buffer .= "?>" . PHP_EOL;
	return $buffer;
}

// Map TTSFUNCTION values to TTS schema provider keys
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
	'cartesia' => 'CARTESIA',
	'inworld' => 'INWORLD',
];

// Values for TTSFUNCTION select from schema
$ttsFunctionValues = $confSchema['TTSFUNCTION']['values'] ?? array_keys($ttsMap);

// Current selected TTSFUNCTION value
function tts_current_value(string $flatName, array $currentConf) {
	$plain = strtr($flatName, ["@" => " "]);
	$parms = $currentConf[$plain] ?? null;
	if (!$parms) return '';
	return $parms['currentValue'] ?? '';
}

$selectedFunction = tts_current_value('TTSFUNCTION', $currentConf);
// Honor immediate dropdown change without requiring Save
if (isset($_POST['TTSFUNCTION']) && is_string($_POST['TTSFUNCTION'])) {
	$selectedFunction = (string)$_POST['TTSFUNCTION'];
}
if ($selectedFunction === '' && !empty($ttsFunctionValues)) {
	$selectedFunction = $ttsFunctionValues[0];
}

// Handle Save
$saveSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_all'])) {
	$allPairs = tts_flatten_current_conf($currentConf, $confSchema);

	// Save TTSFUNCTION
	if (isset($_POST['TTSFUNCTION'])) {
		$allPairs['TTSFUNCTION'] = (string)$_POST['TTSFUNCTION'];
		$selectedFunction = (string)$_POST['TTSFUNCTION'];
	}

	// Determine provider key
	$providerKey = $ttsMap[$selectedFunction] ?? '';
	$providerSchema = [];
	if ($providerKey !== '' && isset($confSchema['TTS'][$providerKey]) && is_array($confSchema['TTS'][$providerKey])) {
		$providerSchema = $confSchema['TTS'][$providerKey];
	}

	// Collect boolean defaults for unchecked boxes
	$booleanFields = [];
	foreach ($providerSchema as $fieldName => $def) {
		if (!is_array($def)) continue;
		$type = $def['type'] ?? '';
		if ($type === 'boolean') $booleanFields[] = $fieldName;
	}

	// Apply posted provider fields
	foreach ($providerSchema as $fieldName => $def) {
		if (!is_array($def)) continue;
		$type = $def['type'] ?? 'string';
		$key = 'TTS@' . $providerKey . '@' . $fieldName;
		if ($type === 'boolean') {
			$val = isset($_POST[$fieldName]) && $_POST[$fieldName] === 'true' ? 'true' : 'false';
			$allPairs[$key] = $val;
		} else if ($type === 'selectmultiple') {
			$vals = isset($_POST[$fieldName]) && is_array($_POST[$fieldName]) ? array_values($_POST[$fieldName]) : [];
			$allPairs[$key] = $vals;
		} else {
			if (isset($_POST[$fieldName])) {
				$allPairs[$key] = (string)$_POST[$fieldName];
			}
		}
	}

	$buffer = tts_build_conf_php_from_pairs($allPairs, $confSchema);
	$target = $enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php";
	$result = @file_put_contents($target, $buffer);
	$saveSuccess = $result !== false;
	if ($saveSuccess) {
		Logger::info("TTS settings saved to conf.php by UI");
		$currentConf = conf_loader_load();
	} else {
		Logger::error("Failed writing conf.php from TTS Connectors UI");
	}
}

// Resolve current provider schema for rendering
// If a provider is selected, show just that provider’s settings; otherwise show all providers
$providerKey = $ttsMap[$selectedFunction] ?? '';
$allProviderSchemas = is_array($confSchema['TTS'] ?? null) ? $confSchema['TTS'] : [];
$providerSchema = [];
if ($providerKey !== '' && isset($allProviderSchemas[$providerKey]) && is_array($allProviderSchemas[$providerKey])) {
	$providerSchema = $allProviderSchemas[$providerKey];
}

// Helper: description
$desc = function(string $plainName) use ($confSchema): string {
	return $confSchema[$plainName]['description'] ?? '';
};

// Helper: get current provider field value
function tts_field_value(string $providerKey, string $fieldName, array $currentConf) {
	$plain = 'TTS ' . $providerKey . ' ' . $fieldName;
	$parms = $currentConf[$plain] ?? null;
	if (!$parms) return '';
	return $parms['currentValue'] ?? '';
}

$TITLE = "🔊 CHIM - TTS Connectors";
ob_start();
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/head.html");
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
main {
	padding-top: 40px;
	padding-bottom: 40px;
	padding-left: 10%;
	padding-right: 10%;
	width: 100%;
	margin: 0;
}
footer { position: fixed; bottom: 0; width: 100%; height: 20px; background: #031633; z-index: 100; }
@font-face { font-family: 'MagicCards'; src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype'); font-weight: normal; font-style: normal; }
h1.tts-title { margin:0 0 20px 0; font-family:'MagicCards', serif; word-spacing:8px; font-size:2.2em; color:rgb(242,124,17); text-shadow:2px 2px 4px rgba(0,0,0,0.5); text-align:center; }
.content-section { background:#2a2a2a; padding:25px; border-radius:8px; border:1px solid #4a4a4a; }
.provider-grid { display:grid; grid-template-columns: 1fr; gap:12px; }
.provider-card { background:#2a2a2a; border:1px solid #4a4a4a; border-radius:8px; padding:12px; }
.provider-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px; }
.provider-title { display:flex; align-items:center; gap:10px; color:#e0e0e0; }
.provider-icon { width:28px; height:28px; border-radius:6px; background:#3a3a3a; display:flex; align-items:center; justify-content:center; font-size:16px; }
.provider-body { display:grid; grid-template-columns: 220px 1fr; gap:8px 12px; align-items:center; }
.provider-body input[type="text"], .provider-body input[type="url"], .provider-body input[type="number"], .provider-body input[type="password"], .provider-body select, .provider-body textarea { background-color:#333; color:#fff; border:1px solid #444; border-radius:4px; padding:8px; }
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
							<?php foreach ($ttsFunctionValues as $opt): ?>
								<option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ((string)$selectedFunction===(string)$opt?'selected':''); ?>><?php echo htmlspecialchars($opt); ?></option>
							<?php endforeach; ?>
						</select>
						<div class="help">Will be saved as <code>TTSFUNCTION</code> in <code>conf.php</code>.</div>
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
							<?php foreach ($providerSchema as $fname => $def): if (!is_array($def)) continue; $ftype = $def['type'] ?? 'string'; $current = tts_field_value($providerKey, $fname, $currentConf); $help = $def['description'] ?? ''; ?>
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
					<div class="provider-card">
						<div class="provider-body"><div></div><div>No settings available for this provider.</div></div>
					</div>
				<?php endif; ?>
			</div>
			<div class="actions">
				<button type="submit" class="btn-primary" name="save_all" value="1">Save</button>
			</div>
		</div>
	</form>
</main>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>


