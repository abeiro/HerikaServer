<?php

session_start();

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
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

// Site chrome (also loads conf_loader.php once)
require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");
$TITLE = "⚙️ CHIM - Global Settings";
ob_start();
include(__DIR__.DIRECTORY_SEPARATOR."tmpl".DIRECTORY_SEPARATOR."head.html");

// Load schema and current configuration
$confSchema = conf_loader_load_schema();
$currentConf = conf_loader_load();

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

// Curated, manually-defined global settings (exclude TTS, STT, ITT)
$gsSections = [
    'General' => [
    ],
    'Narrator' => [
        [ 'name' => 'NARRATOR_TALKS', 'type' => 'boolean' ],
        [ 'name' => 'NARRATOR_WELCOME', 'type' => 'boolean' ],
        [ 'name' => 'BOOK_EVENT_ALWAYS_NARRATOR', 'type' => 'boolean' ]
    ],
    'Diary' => [
        [ 'name' => 'AUTO_DIARY', 'type' => 'boolean' ],
        [ 'name' => 'AUTO_DIARY_WAIT', 'type' => 'boolean' ],
        [ 'name' => 'DIARY_COOLDOWN', 'type' => 'integer', 'min' => 10, 'max' => 1200 ]
    ],
    'Events' => [
        [ 'name' => 'CLEAN_CONTEXT_FOCUS_CHAT_HISTORY', 'type' => 'integer' ]
    ],
    'Memory' => [
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
    // Flatten existing conf to full map
    $allPairs = flatten_current_conf($currentConf, $confSchema);

    // Apply posted overrides for our curated settings
    foreach ($sections as $sec => $fields) {
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

    // Build and write buffer to default conf.php (always default profile)
    $buffer = build_conf_php_from_pairs($allPairs, $confSchema);
    $target = $enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php";
    $result = @file_put_contents($target, $buffer);
    $saveSuccess = $result !== false;
    if ($saveSuccess) {
        Logger::info("Global settings saved to conf.php by UI");
    } else {
        Logger::error("Failed writing conf.php from Global Settings UI");
    }
    // Reload current conf after save
    $currentConf = conf_loader_load();
}

// Helper: get current value by field name in our curated list
function current_value(string $flatName, array $currentConf) {
    $plain = strtr($flatName, ["@" => " "]);
    $parms = $currentConf[$plain] ?? null;
    if (!$parms) return '';
    return $parms['currentValue'] ?? '';
}
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
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
        grid-template-columns: 1fr 1fr;
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
    .provider-grid { display:grid; grid-template-columns: 1fr; gap:12px; }
    .provider-card { background:#2a2a2a; border:1px solid #4a4a4a; border-radius:8px; padding:12px; }
    .provider-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px; }
    .provider-title { display:flex; align-items:center; gap:10px; color:#e0e0e0; }
    .provider-icon { width:28px; height:28px; border-radius:6px; background:#3a3a3a; display:flex; align-items:center; justify-content:center; font-size:16px; }
    .provider-body { display:flex; gap:8px; align-items:center; }
    .provider-body input[type="text"], .provider-body input[type="url"], .provider-body input[type="number"], .provider-body input[type="password"], .provider-body select, .provider-body textarea { flex:1; background-color:#333; color:#fff; border:1px solid #444; border-radius:4px; padding:8px; }
    .actions { display:flex; justify-content:flex-end; margin-top:10px; }
    .btn-primary { background:#204e7a; color:#fff; border:1px solid rgba(138,155,182,0.4); border-radius:8px; padding:8px 14px; cursor:pointer; }
    .btn-primary:hover { background:#285c8f; }

    @media (max-width: 900px) {
        main { padding-left: 5%; padding-right: 5%; }
        .content-grid { grid-template-columns: 1fr; }
    }
</style>

<main>
    <h1 class="gs-title">Global Settings</h1>
    <div id="toast" class="toast-notification" style="display:none;"><span class="message"></span></div>

    <?php if ($saveSuccess): ?>
        <script>setTimeout(function(){ try{ const t=document.getElementById('toast'); if(t){ t.style.display='block'; t.textContent='Settings saved to conf.php'; setTimeout(()=>{ t.style.display='none'; }, 2500); } }catch(_e){} }, 50);</script>
    <?php endif; ?>

    <form method="post" action="">
        <div class="content-grid">
            <?php foreach ($gsSections as $sectionTitle => $fields): ?>
                <div class="content-section">
                    <h2><?php echo htmlspecialchars($sectionTitle); ?></h2>
                    <div class="provider-grid">
                        <?php foreach ($fields as $f): ?>
                            <?php
                                $fname = $f['name'];
                                $ftype = $f['type'];
                                $current = current_value($fname, $currentConf);
                                $label = str_replace(['@'], [' → '], $fname);
                                $help = $gsDesc($fname);
                            ?>
                            <div class="provider-card">
                                <div class="provider-head">
                                    <div class="provider-title">
                                        <div class="provider-icon">⚙️</div>
                                        <div><?php echo htmlspecialchars($label); ?></div>
                                    </div>
                                </div>
                                <div class="provider-body">
                                    <?php if ($ftype === 'boolean'): ?>
                                        <input type="hidden" name="<?php echo htmlspecialchars($fname); ?>" value="false">
                                        <label style="min-width:180px;">Enable</label>
                                        <input type="checkbox" value="true" name="<?php echo htmlspecialchars($fname); ?>" <?php echo ($current ? 'checked' : ''); ?> style="width:auto;">
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
        <div class="actions">
            <button type="submit" class="btn-primary" name="save_all" value="1">Save All</button>
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


