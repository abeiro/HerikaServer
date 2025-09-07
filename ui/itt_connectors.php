<?php

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf_loader.php");
@include_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
@include_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.sample.php");

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
$providersAll = is_array($rawSchema['ITT'] ?? null) ? $rawSchema['ITT'] : [];
$ittOptionsRaw = $rawSchema['ITTFUNCTION']['values'] ?? [ 'openai','google_openai','llamacpp' ];
// Exclude llamacpp per request
$ittOptions = array_values(array_filter($ittOptionsRaw, function($v){ return strtolower($v) !== 'llamacpp'; }));

// Current configuration
$currentConf = conf_loader_load();

// Helpers
function itt_current_value(string $flatName, array $currentConf) {
	$plain = strtr($flatName, ["@" => " "]);
	$parms = $currentConf[$plain] ?? null;
	if (!$parms) return '';
	return $parms['currentValue'] ?? '';
}

// Mapping from dropdown value -> provider key in schema
$ittMap = [
	'openai' => 'openai',
	'google_openai' => 'google_openai',
];

// Selected provider
$selectedFunction = itt_current_value('ITTFUNCTION', $currentConf);
if (isset($_POST['ITTFUNCTION']) && is_string($_POST['ITTFUNCTION'])) {
	$selectedFunction = (string)$_POST['ITTFUNCTION'];
}
if ($selectedFunction === '' && !empty($ittOptions)) $selectedFunction = $ittOptions[0];
$providerKey = $ittMap[$selectedFunction] ?? '';
$providerSchema = ($providerKey && isset($providersAll[$providerKey]) && is_array($providersAll[$providerKey])) ? $providersAll[$providerKey] : [];

// Save: write ITTFUNCTION + selected provider fields into conf.php
$saveSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_all'])) {
	$confSchemaFlat = conf_loader_load_schema();
	$allPairs = [];
	// Preserve existing pairs
	foreach ($currentConf as $pname => $parms) {
		$fieldName = strtr($pname, [" " => "@"]);
		$type = $parms['type'] ?? ($confSchemaFlat[$pname]['type'] ?? 'string');
		$val = $parms['currentValue'] ?? '';
		if ($type === 'boolean') $allPairs[$fieldName] = $val ? 'true' : 'false';
		else if ($type === 'selectmultiple') $allPairs[$fieldName] = is_array($val) ? $val : [];
		else $allPairs[$fieldName] = (string)$val;
	}
	// Overwrite ITTFUNCTION
	$allPairs['ITTFUNCTION'] = (string)$selectedFunction;
	// Overwrite selected provider fields
	if ($providerKey && is_array($providerSchema)) {
		foreach ($providerSchema as $fname => $def) {
			if (!is_array($def)) continue;
			$type = $def['type'] ?? 'string';
			$key = 'ITT@' . $providerKey . '@' . $fname;
			if ($type === 'boolean') {
				$allPairs[$key] = (isset($_POST[$fname]) && $_POST[$fname] === 'true') ? 'true' : 'false';
			} else if ($type === 'selectmultiple') {
				$allPairs[$key] = isset($_POST[$fname]) && is_array($_POST[$fname]) ? array_values($_POST[$fname]) : [];
			} else {
				if (isset($_POST[$fname])) $allPairs[$key] = (string)$_POST[$fname];
			}
		}
	}

	// Build conf.php content
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
		Logger::info("ITT settings saved to conf.php by UI");
		@include_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
		$currentConf = conf_loader_load();
	} else {
		Logger::error("Failed writing conf.php from ITT Connectors UI");
	}
}

$TITLE = "🖼️ CHIM - ITT Connectors";
ob_start();
include(__DIR__.DIRECTORY_SEPARATOR."tmpl".DIRECTORY_SEPARATOR."head.html");
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
main { padding-top: 40px; padding-bottom: 40px; padding-left: 10%; padding-right: 10%; width: 100%; margin: 0; }
footer { position: fixed; bottom: 0; width: 100%; height: 20px; background: #031633; z-index: 100; }
@font-face { font-family: 'MagicCards'; src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype'); }
h1.itt-title { margin:0 0 20px 0; font-family:'MagicCards', serif; word-spacing:8px; font-size:2.2em; color:rgb(242,124,17); text-shadow:2px 2px 4px rgba(0,0,0,0.5); text-align:center; }
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
	<h1 class="itt-title">ITT Connectors</h1>
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
							<div class="provider-icon">🖼️</div>
							<div>ITT Provider</div>
						</div>
					</div>
					<div class="provider-body">
						<label for="ITTFUNCTION">ITT Selection</label>
						<select name="ITTFUNCTION" id="ITTFUNCTION" onchange="this.form.submit()">
							<?php foreach ($ittOptions as $opt): ?>
								<option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ((string)$selectedFunction===(string)$opt?'selected':''); ?>><?php echo htmlspecialchars($opt); ?></option>
							<?php endforeach; ?>
						</select>
						<div class="help">Saved as <code>ITTFUNCTION</code> in <code>conf.php</code>.</div>
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
							<?php foreach ($providerSchema as $fname => $def): if (!is_array($def)) continue; $ftype = $def['type'] ?? 'string'; $plainName = 'ITT ' . $providerKey . ' ' . $fname; $current = $currentConf[$plainName]['currentValue'] ?? ''; $help = $def['description'] ?? ''; ?>
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
			</div>
			<div class="actions">
				<button type="submit" class="btn-primary" name="save_all" value="1">Save</button>
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


