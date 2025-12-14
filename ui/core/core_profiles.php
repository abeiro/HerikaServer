<?php

$enginePath = __DIR__.DIRECTORY_SEPARATOR."../../";

require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
if (!isset($GLOBALS["db"])) { $GLOBALS["db"] = new sql(); }
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."logger.php");

require_once "{$enginePath}/lib/core/core_profiles.class.php";
require_once "{$enginePath}/lib/core/llm_connector.class.php";
require_once "{$enginePath}/lib/core/import_rules.class.php";

//function renderSelect($obj, $fieldName, $labelText, $selectedValue = "") 
//function include from below file
include(__DIR__."/tmpl/ui_utils.php");

// Determine web root similar to oghma_upload
$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
if ($uiPos !== false) {
    $webRoot = substr($scriptPath, 0, $uiPos);
} else {
    $webRoot = '';
}
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

// Site chrome
require_once(__DIR__.DIRECTORY_SEPARATOR."../profile_loader.php");
$TITLE = "👤 CHIM - Profiles";
ob_start();
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/head.html");
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
/* Match Oghma/Connectors spacing and title styling */
@font-face {
    font-family: 'MagicCards';
    src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype');
    font-weight: normal;
    font-style: normal;
}
main { padding-top: 40px; padding-bottom: 40px; }
h1.api-title {
    margin: 0 0 20px 0;
    font-family: 'MagicCards', serif;
    letter-spacing: 0.7px;
    word-spacing: 12px;
    font-size: 2.2em;
    color: rgb(242, 124, 17);
    text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
    text-align: center;
}
.wide-centered { max-width: 1300px; margin: 0 auto; }
.two-col-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
.connector-card { background: #2a2a2a; border: 1px solid #4a4a4a; border-radius: 8px; padding: 12px; }
.connector-title { font-family: 'MagicCards', serif; color: rgb(242, 124, 17); margin-bottom: 8px; font-size: 1.1em; letter-spacing: 0.6px; word-spacing: 10px; }
@media (max-width: 1000px) { .two-col-grid { grid-template-columns: 1fr; } }
/* Split layout like LLM Connectors */
.llm-layout { display:grid; grid-template-columns: minmax(240px, 340px) 1fr; gap:16px; align-items:stretch; }
@media (max-width: 1100px) { .llm-layout { grid-template-columns: minmax(220px, 300px) 1fr; } }
@media (max-width: 860px) { .llm-layout { grid-template-columns: minmax(200px, 260px) 1fr; } }
.llm-left { display:flex; flex-direction:column; height:800px; overflow:hidden; padding:8px; padding-right:8px; border:1px solid #4a4a4a; border-radius:8px; background:#2a2a2a; }
.llm-right { min-width: 0; }
.list-filters { display:flex; gap:8px; align-items:center; margin:6px 0 10px; flex-wrap:wrap; }
.list-filters input[type="text"]{ width: 100%; max-width: 260px; }
.list-filters select { max-width: 200px; }
.conn-list { display:flex; flex-direction:column; gap:8px; flex:1 1 auto; overflow:auto; }
.llm-left .llm-title { font-family: 'MagicCards', serif; letter-spacing: 0.6px; word-spacing: 10px; }
.conn-li { border:1px solid #4a4a4a; background:#2a2a2a; border-radius:10px; padding:10px; cursor:pointer; transition:transform .08s ease, background .12s ease; }
.conn-li:hover { background:#3a3a3a; transform: translateY(-1px); }
.conn-li.active { outline:2px solid rgb(242,124,17); }
.conn-li .head { display:flex; justify-content:space-between; gap:8px; align-items:center; }
.conn-li .title { font-weight:600; color:#e9efff; }
.conn-li .badge { font-size:11px; padding:2px 6px; border:1px solid #4a4a4a; border-radius:999px; color:#9fb1c9; }
.conn-li .sub { font-size:12px; color:#9fb1c9; margin-top:3px; overflow-wrap:anywhere; }
.conn-li .actions { display:flex; gap:6px; margin-top:6px; justify-content:flex-end; }
.pf-badges { display:flex; flex-direction:column; gap:4px; align-items:flex-end; }
.pf-badges-row { display:flex; gap:10px; align-items:center; flex-wrap:nowrap; }
.pf-flag { display:inline-flex; align-items:center; font-size:12px; padding:2px 8px; border:1px solid #4a4a4a; border-radius:0; color:#cfd9ea; background:rgba(255,255,255,0.04); line-height:1.4; }
/* Active Slots block rows */
.slot-row { display:flex; align-items:center; justify-content:flex-start; gap:10px; padding:2px 0; }
.slot-key { color: rgb(242,124,17); font-weight:700; min-width:70px; white-space:nowrap; }
.slot-val { color:#cfd9ea; overflow-wrap:anywhere; }
.pf-tabs { display:flex; gap:6px; flex-wrap:wrap; margin: 8px 0 10px; border-bottom: 2px solid #3a3a3a; }
.pf-tab { background:#2a2a2a; border:none; padding:8px 12px; color:#f8f9fa; cursor:pointer; border-top-left-radius:8px; border-top-right-radius:8px; transition: all .2s ease; font-size:0.95em; }
.pf-tab:hover { background:#3a3a3a; }
.pf-tab.active { background:#1a1a1a; border-bottom: 2px solid rgb(242,124,17); margin-bottom:-2px; }
.pf-pane { display:none; }
.pf-pane.active { display:block; }
.pf-lines { display:flex; flex-direction:column; gap:4px; margin-top:6px; }
.pf-line { display:grid; grid-template-columns: 18px 150px 1fr; align-items:center; gap:6px; font-size:12px; color:#cfd9ea; }
.pf-icon { width:18px; text-align:center; opacity:0.9; }
.pf-key { color:#9fb1c9; font-weight:600; white-space:nowrap; text-align:left; }
.pf-val { overflow-wrap:anywhere; }
/* Ensure inputs fit within card borders */
.connector-card input[type="text"],
.connector-card input[type="number"],
.connector-card input[type="password"],
.connector-card select,
.connector-card textarea { width: 100%; max-width: 100%; box-sizing: border-box; }
/* Connector help text */
.connector-help { color:#cfd9ea; font-size:12px; margin-top:6px; }
.connector-help ul { margin:6px 0 0 16px; padding:0; }
.connector-help li { margin:2px 0; }
/* Profile Settings: provider-style cards (match Global Settings look) */
.provider-grid { display:grid; grid-template-columns: 1fr; gap:12px; align-items:start; }
.provider-card { background:#2a2a2a; border:1px solid #4a4a4a; border-radius:8px; padding:12px; }
.provider-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px; }
.provider-title { display:flex; align-items:center; gap:10px; color:#e0e0e0; }
.provider-icon { width:28px; height:28px; border-radius:6px; background:#3a3a3a; display:flex; align-items:center; justify-content:center; font-size:16px; }
.provider-body { display:flex; gap:8px; align-items:center; }
.provider-body.grid { display:grid; grid-template-columns: 220px 1fr; gap:8px 12px; align-items:center; }
.provider-body.grid .help { grid-column: 1 / -1; margin-top:6px; color:#bbb; font-size:12px; }
.provider-title .provider-toggle { margin-left: 10px; display:flex; align-items:center; }
.provider-title .provider-toggle input[type="checkbox"] { accent-color:#176529; transform: scale(1.8); transform-origin:center; cursor:pointer; }
/* Toast notification */
.toast-notification { position: fixed; top: 20px; right: 20px; padding: 12px 20px; border-radius: 8px; color: white; font-weight: 500; z-index: 10000; opacity: 0; transform: translateX(400px); transition: all 0.3s ease; max-width: 400px; }
.toast-notification.show { opacity: 1; transform: translateX(0); }
.toast-notification:not(.error) { background: linear-gradient(135deg, #6dd19c, #5bb377); border: 1px solid rgba(109, 209, 156, 0.3); }
.toast-notification.error { background: linear-gradient(135deg, #ff6b6b, #e55a5a); border: 1px solid rgba(255, 107, 107, 0.3); }
/* Compact select row with Set button */
.select-row { display:flex; gap:8px; align-items:center; }
.select-row select { max-width: 480px; }
.btn-apply { white-space: nowrap; padding: 6px 10px; }
/* Collapsible block for Metadata */
.collapsible { margin-top: 8px; border:1px solid #4a4a4a; border-radius:10px; background:#2a2a2a; }
.collapsible-header { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:10px; cursor:pointer; user-select:none; color:#e9efff; font-weight:600; }
.collapsible-header::after { content:'\25BE'; font-size:12px; color:#9fb1c9; transition: transform .12s ease; }
.collapsible[open] .collapsible-header { border-bottom:1px solid #4a4a4a; }
.collapsible[open] .collapsible-header::after { transform: rotate(180deg); }
.collapsible-content { padding:10px; }
.section-title { font-weight:800; color:#e9efff; border-bottom:1px solid #4a4a4a; padding-bottom:4px; margin:10px 0 6px; }
/* Inline title + toggle styling */
.label-with-toggle { display:flex; align-items:center; gap:10px; }
.label-with-toggle input[type="checkbox"] { accent-color:#176529; transform: scale(1.8); transform-origin:center; cursor:pointer; }
/* Profile Settings (metadata editor) checkbox enhancement */
.profile-settings-card input[type="checkbox"] { accent-color:#176529; transform: scale(1.6); transform-origin:center; cursor:pointer; }
</style>

<main>
    <div id="toast" class="toast-notification">
        <span class="message"></span>
    </div>

    <h1 class="api-title">Profiles</h1>

<?php
$GLOBALS["db"]=new sql();

$profiles = new CoreProfile();

// Populate arrays for connector labels at the beginning of the file
$ttsOptions = getSelectOptions($profiles, "tts_connector_id");
$ittOptions = getSelectOptions($profiles, "itt_connector_id");
$diaryOptions = getSelectOptions($profiles, "diary_connector_id");
$llmPrimaryOptions = getSelectOptions($profiles, "llm_primary_id");
$llmSecondaryOptions = getSelectOptions($profiles, "llm_secondary_id");
$llmTertiaryOptions = getSelectOptions($profiles, "llm_tertiary_id");
$llmQuaternaryOptions = getSelectOptions($profiles, "llm_quaternary_id");
$llmFormatterOptions = getSelectOptions($profiles, "llm_formatter_id");

// Load RPG Comments schema options for per-profile control
require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf_loader.php");
$__confSchema = conf_loader_load_schema();
$__rpgOptionsRaw = is_array($__confSchema['RPG_COMMENTS']['values'] ?? null) ? $__confSchema['RPG_COMMENTS']['values'] : [];
$__rpgHelp = (string)($__confSchema['RPG_COMMENTS']['description'] ?? '');
$__rpgOptions = array_values(array_filter($__rpgOptionsRaw, function($v){ return strtolower((string)$v) !== 'keepmechecked'; }));
// Load Dynamic Profile Fields options for per-profile control
$__dynOptions = is_array($__confSchema['DYNAMIC_PROFILE_FIELDS']['values'] ?? null) ? $__confSchema['DYNAMIC_PROFILE_FIELDS']['values'] : [];
$__dynHelp = (string)($__confSchema['DYNAMIC_PROFILE_FIELDS']['description'] ?? '');

// Handle Create
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create"])) {
    if ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
        header('Content-Type: application/json');
    }
    // Server-side merge of visual metadata with JSON editor content
    if (isset($_POST['meta_vis'])) {
        $base = [];
        if (!empty($_POST['metadata'])) {
            $tmp = json_decode($_POST['metadata'], true);
            if (is_array($tmp)) $base = $tmp;
        }
        foreach ((array)$_POST['meta_vis'] as $k=>$v) unset($base[$k]);
        foreach ((array)$_POST['meta_vis'] as $k=>$v) {
            if (is_array($v)) {
                $v = array_values(array_filter($v, function($x){ return $x !== '' && $x !== null && strtolower((string)$x) !== 'keepmechecked'; }));
            }
            // Skip scalar values that are empty (indicates deletion)
            if (!is_array($v) && ($v === '' || $v === null)) {
                continue;
            }
            $base[$k] = $v;
        }
        $_POST['metadata'] = json_encode($base, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    }
    $newId = $profiles->create($_POST);
    if ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        echo json_encode(['ok'=>true,'id'=>$newId]);
        exit;
    } else {
        header("Location: core_profiles.php");
        exit;
    }
}

// Handle Update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update"])) {
    if ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
        header('Content-Type: application/json');
    }
    // Server-side merge of visual metadata with JSON editor content
    if (isset($_POST['meta_vis'])) {
        $base = [];
        if (!empty($_POST['metadata'])) {
            $tmp = json_decode($_POST['metadata'], true);
            if (is_array($tmp)) $base = $tmp;
        }
        foreach ((array)$_POST['meta_vis'] as $k=>$v) unset($base[$k]);
        foreach ((array)$_POST['meta_vis'] as $k=>$v) {
            if (is_array($v)) {
                $v = array_values(array_filter($v, function($x){ return $x !== '' && $x !== null && strtolower((string)$x) !== 'keepmechecked'; }));
            }
            // Skip scalar values that are empty (indicates deletion)
            if (!is_array($v) && ($v === '' || $v === null)) {
                continue;
            }
            $base[$k] = $v;
        }
        $_POST['metadata'] = json_encode($base, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    }
    $profiles->update($_POST["id"], $_POST);
    if ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        echo json_encode(['ok'=>true,'id'=>$_POST['id'] ?? null]);
        exit;
    } else {
        header("Location: core_profiles.php");
        exit;
    }
}

// Handle Delete
if (isset($_GET["delete"])) {
    $profiles->delete($_GET["delete"]);
    header("Location: core_profiles.php");
    exit;
}

// Inline update handler for LLM connectors (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["inline_update_connector"])) {
    // Ensure no buffered HTML leaks into JSON response
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    try {
        $llm = new LLMConnector();
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(["ok"=>false, "error"=>"Invalid id"]); exit; }

        $allowed = [
            'label','service','url','model','provider','driver','max_tokens','temperature','presence_penalty','frequency_penalty','repetition_penalty','top_p','top_k','min_p','top_a','enforce_json','prefill_json','reasoning_model','json_schema','api_badge_id'
        ];
        $data = [];
        foreach ($allowed as $k) {
            if (!array_key_exists($k, $_POST)) continue;
            $v = $_POST[$k];
            if (in_array($k, ['enforce_json','prefill_json','reasoning_model','json_schema'], true)) {
                $data[$k] = ($v==='1' || $v==='true' || $v===1) ? 1 : 0;
            } else if (in_array($k, ['max_tokens','top_k'], true)) {
                $data[$k] = ($v === '' ? null : intval($v));
            } else if (in_array($k, ['temperature','presence_penalty','frequency_penalty','repetition_penalty','top_p','min_p','top_a'], true)) {
                $data[$k] = ($v === '' ? null : floatval($v));
            } else if ($k === 'api_badge_id') {
                $data[$k] = ($v === '' ? null : intval($v));
            } else {
                $data[$k] = ($v === '' ? null : $v);
            }
        }
        $ok = $llm->update($id, $data);
        if ($ok === false) {
            echo json_encode(["ok"=>false, "error"=>($llm->getLastError() ?: 'Update failed')]);
        } else {
            echo json_encode(["ok"=>true]);
        }
    } catch (Throwable $e) {
        echo json_encode(["ok"=>false, "error"=>$e->getMessage()]);
    }
    exit;
}

// Inline update handler for Core Profile fields (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["inline_update_profile"])) {
    // Ensure no buffered HTML leaks into JSON response
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    try {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(["ok"=>false, "error"=>"Invalid id"]); exit; }
        $field = (string)($_POST['field'] ?? '');
        $allowed = [
            'llm_primary_id','llm_secondary_id','llm_tertiary_id','llm_quaternary_id','llm_formatter_id','llm_fallback_id',
            'diary_connector_id','tts_connector_id','itt_connector_id'
        ];
        if (!in_array($field, $allowed, true)) { echo json_encode(["ok"=>false, "error"=>"Invalid field"]); exit; }
        $raw = $_POST['value'] ?? '';
        $val = ($raw === '' ? null : intval($raw));
        $data = [ $field => $val ];
        // Fallback direct update for llm_formatter_id to ensure persistence
        if ($field === 'llm_formatter_id') {
            $ok = $GLOBALS["db"]->updateRow('core_profiles', ['llm_formatter_id' => $val], 'id = '.$id);
        } else {
            $ok = $profiles->update($id, $data);
        }
        if ($ok === false) {
            echo json_encode(["ok"=>false, "error"=>($profiles->getLastError() ?: 'Update failed')]);
        } else {
            echo json_encode(["ok"=>true]);
        }
    } catch (Throwable $e) {
        echo json_encode(["ok"=>false, "error"=>$e->getMessage()]);
    }
    exit;
}

// Add a new action for cloning a connector
if (isset($_GET["clone"])) {
    $newId = $profiles->clone($_GET["clone"]);
    if ($newId) {
        header("Location: core_profiles.php?edit=".urlencode((string)$newId));
    } else {
        header("Location: core_profiles.php");
    }
    exit;
}

// ============= Profile Rules AJAX Handlers =============
$importRules = new ImportRules();

// Fetch all import rules (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET["get_import_rules"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    $rules = $importRules->getAll();
    echo json_encode(['ok' => true, 'data' => $rules]);
    exit;
}

// Create import rule (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create_import_rule"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    
    // Normalize inputs
    $rawAction = isset($_POST['action']) ? (string)$_POST['action'] : '';
    $decodedAction = null;
    if ($rawAction !== '') {
        $tmp = json_decode($rawAction, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['ok'=>false,'error'=>'Invalid JSON in action']);
            exit;
        }
        $decodedAction = $tmp;
    }
    $modsStr = isset($_POST['match_mods']) ? (string)$_POST['match_mods'] : '';
    $modsArr = $modsStr !== '' ? array_values(array_filter(array_map('trim', explode(',', $modsStr)), function($v){ return $v!==''; })) : null;

    $data = [
        'description' => trim($_POST['description'] ?? ''),
        'match_name' => !empty($_POST['match_name']) ? trim($_POST['match_name']) : null,
        'match_race' => !empty($_POST['match_race']) ? trim($_POST['match_race']) : null,
        'match_gender' => !empty($_POST['match_gender']) ? trim($_POST['match_gender']) : null,
        'match_base' => !empty($_POST['match_base']) ? trim($_POST['match_base']) : null,
        'match_mods' => $modsArr,
        'action' => $decodedAction,
        'profile' => !empty($_POST['profile']) ? (int)$_POST['profile'] : null,
        'priority' => isset($_POST['priority']) ? (int)$_POST['priority'] : 0,
        'enabled' => isset($_POST['enabled']) && $_POST['enabled'] === '1'
    ];
    
    $importRules->create($data);
    $last = $GLOBALS['db']->fetchOne("SELECT id FROM import_rules ORDER BY id DESC LIMIT 1");
    $newId = $last['id'] ?? '';
    echo json_encode(['ok' => true, 'id' => $newId]);
    exit;
}

// Update import rule (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_import_rule"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    
    $id = (int)$_POST['id'];
    // Normalize inputs
    $rawAction = isset($_POST['action']) ? (string)$_POST['action'] : '';
    $decodedAction = null;
    if ($rawAction !== '') {
        $tmp = json_decode($rawAction, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['ok'=>false,'error'=>'Invalid JSON in action']);
            exit;
        }
        $decodedAction = $tmp;
    }
    $modsStr = isset($_POST['match_mods']) ? (string)$_POST['match_mods'] : '';
    $modsArr = $modsStr !== '' ? array_values(array_filter(array_map('trim', explode(',', $modsStr)), function($v){ return $v!==''; })) : null;

    $data = [
        'description' => trim($_POST['description'] ?? ''),
        'match_name' => !empty($_POST['match_name']) ? trim($_POST['match_name']) : null,
        'match_race' => !empty($_POST['match_race']) ? trim($_POST['match_race']) : null,
        'match_gender' => !empty($_POST['match_gender']) ? trim($_POST['match_gender']) : null,
        'match_base' => !empty($_POST['match_base']) ? trim($_POST['match_base']) : null,
        'match_mods' => $modsArr,
        'action' => $decodedAction,
        'profile' => !empty($_POST['profile']) ? (int)$_POST['profile'] : null,
        'priority' => isset($_POST['priority']) ? (int)$_POST['priority'] : 0,
        'enabled' => isset($_POST['enabled']) && $_POST['enabled'] === '1'
    ];
    
    $importRules->update($id, $data);
    echo json_encode(['ok' => true]);
    exit;
}

// Delete import rule (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_import_rule"])) {
    try { while (ob_get_level() > 0) { ob_end_clean(); } } catch (Throwable $e) {}
    header('Content-Type: application/json');
    
    $id = (int)$_POST['id'];
    $importRules->delete($id);
    echo json_encode(['ok' => true]);
    exit;
}

// Create a blank profile and open it for editing
if (isset($_GET["create_blank"])) {
    try {
        $defaultMeta = json_encode([
            'RPG_COMMENTS'=>['levelup','sleep','lockpick'],
            'DYNAMIC_PROFILE_FIELDS'=>['relationships','goals']
        ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $row = $GLOBALS["db"]->fetchOne("INSERT INTO core_profiles (label, metadata) VALUES ('New Profile', '".pg_escape_string($defaultMeta)."') RETURNING id");
        $newId = is_array($row) ? ($row['id'] ?? '') : '';
        $redir = 'core_profiles.php' . ($newId !== '' ? ('?edit=' . urlencode($newId)) : '');
        header("Location: $redir");
        exit;
    } catch (Throwable $e) {
        header("Location: core_profiles.php");
        exit;
    }
}

// Fetch Data
$data = $profiles->readAll();
$npcCountRows = $GLOBALS["db"]->fetchAll("SELECT profile_id, COUNT(*) AS c FROM core_npc_master GROUP BY profile_id");
$profileIdToNpcCount = [];
foreach ($npcCountRows as $r) {
    $pid = (string)($r['profile_id'] ?? '');
    $cnt = (int)($r['c'] ?? 0);
    if ($pid !== '') $profileIdToNpcCount[$pid] = $cnt;
}
$editItem = null;

if (isset($_GET["edit"])) {
    $editItem = $profiles->getById($_GET["edit"]);
}
// Preload connector details for left list and editors
$llmRows = $GLOBALS["db"]->fetchAll("SELECT c.*, b.label AS api_badge_label FROM core_llm_connector c LEFT JOIN core_api_badge b ON b.id=c.api_badge_id ORDER BY c.id ASC");
$ttsRows = $GLOBALS["db"]->fetchAll("SELECT t.*, b.label AS api_badge_label FROM core_tts_connector t LEFT JOIN core_api_badge b ON b.id=t.api_badge_id ORDER BY t.id ASC");
$ittRows = $GLOBALS["db"]->fetchAll("SELECT * FROM core_itt_connector ORDER BY id ASC");
$apiBadgeRows = $GLOBALS["db"]->fetchAll("SELECT id, label FROM core_api_badge ORDER BY id ASC");

$byId = function($rows){
    $out = [];
    foreach ($rows as $r) { $out[(string)($r['id']??'')] = $r; }
    return $out;
};
$llmById = $byId($llmRows);
$ttsById = $byId($ttsRows);
$ittById = $byId($ittRows);
?>

<div class="llm-layout">
    <div class="llm-left">
        <div class="llm-title" style="margin: 4px 0 6px 2px; font-weight: 600; color: rgb(242,124,17);">Profiles</div>
        <div style="margin: 6px 0 10px 4px; display:flex; gap:8px; flex-wrap:wrap;">
            <form method="get" action="core_profiles.php" style="display:inline">
                <input type="hidden" name="create_blank" value="1">
                <button type="submit" class="btn-save">New Profile</button>
            </form>
            <button type="button" id="open_import_rules_btn" class="btn-primary">Profile Rules</button>
        </div>
        <div id="profiles_list" class="conn-list"></div>
        <script>
        (function(){
            const RAW = <?= json_encode($data ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
            const ACTIVE_ID = <?= json_encode($_GET['edit'] ?? '') ?>;
            const list = document.getElementById('profiles_list');
            const NPC_COUNT = <?= json_encode($profileIdToNpcCount ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
            const LLM = <?= json_encode($llmById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
            const TTS = <?= json_encode($ttsById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
            const ITT = <?= json_encode($ittById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
            function escapeHtml(s){ return (s==null?'':String(s)).replace(/[&<>]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])); }
            function labelOf(map, id){ if (!id) return ''; const k=String(id); const row=map[k]; return row && (row.label||row.model||row.driver) ? (row.label||'') : ''; }
            function pass(_row){ return true; }
            function render(){
                const rows=(RAW||[]).filter(pass);
                let html='';
                // Active Slots summary (always render slots 1-4, even if empty)
                {
                    const slotToProfile = { 1: null, 2: null, 3: null, 4: null };
                    (rows||[]).forEach(r=>{
                        const s = Number(r && r.slot);
                        if (s>=1 && s<=4 && slotToProfile[s]===null) slotToProfile[s] = r;
                    });
                    html += '<div class="connector-card" style="padding:8px;">';
                    html += '<div class="connector-title" title="Can be assigned to NPCs ingame with the Settings Wheel hotkey">Profile Slots <span style="margin-left:6px; color:#9fb1c9; cursor:help;" title="Can be assigned to NPCs ingame with the Settings Wheel hotkey">ⓘ</span></div>';
                    [1,2,3,4].forEach(s=>{
                        const r = slotToProfile[s];
                        if (r){
                            const title = escapeHtml(r.label||('Profile #'+r.id));
                            html += `<div class=\"slot-row\" style=\"cursor:pointer;\" data-jump-id=\"${String(r.id)}\" title=\"Can be assigned to NPCs ingame with the Settings Wheel hotkey\"><span class=\"slot-key\">Slot ${String(s)}</span><span class=\"slot-val\">${title}</span></div>`;
                        } else {
                            html += `<div class=\"slot-row\" style=\"opacity:.75;\" title=\"Can be assigned to NPCs ingame with the Settings Wheel hotkey\"><span class=\"slot-key\">Slot ${String(s)}</span><span class=\"slot-val\">— Empty —</span></div>`;
                        }
                    });
                    html += '</div>';
                }
                rows.forEach(r=>{
                    const active = String(r.id)===String(ACTIVE_ID) ? ' active' : '';
                    const llm1 = escapeHtml(labelOf(LLM, r.llm_primary_id));
                    const llm2 = escapeHtml(labelOf(LLM, r.llm_secondary_id));
                    const llm3 = escapeHtml(labelOf(LLM, r.llm_tertiary_id));
                    const llm4 = escapeHtml(labelOf(LLM, r.llm_quaternary_id));
                    const tts = escapeHtml(labelOf(TTS, r.tts_connector_id));
                    const itt = escapeHtml(labelOf(ITT, r.itt_connector_id));
                    const diary = escapeHtml(labelOf(LLM, r.diary_connector_id));
                    const formatter = escapeHtml(labelOf(LLM, r.llm_formatter_id));
                    const npcCount = Number((NPC_COUNT||{})[String(r.id)]||0);
                    const row1 = [];
                    if (String(r.default_npc)==='1') row1.push('<span class="pf-flag">👤 NPC</span>');
                    if (String(r.default_narrator)==='1') row1.push('<span class="pf-flag">🗣️Narrator</span>');
                    const row2 = [];
                    // Slot badge removed from list items
                    if (npcCount > 0) row2.push('<span class="pf-flag">'+npcCount+' NPCs</span>');
                    html += `
                        <div class="conn-li${active}" data-id="${String(r.id)}">
                            <div class="head">
                                <div class="title">${escapeHtml(r.label||('Profile #'+r.id))}</div>
                                <div class="pf-badges">`+
                                    (row1.length?('<div class=\"pf-badges-row\">'+row1.join(' ')+'</div>'):'')+
                                    (row2.length?('<div class=\"pf-badges-row\">'+row2.join(' ')+'</div>'):'')+
                                `</div>
                            </div>
                            <div class="pf-lines">
                                <div class="pf-line"><span class="pf-icon">🕹️</span><span class="pf-key">Standard LLM</span><span class="pf-val">${llm1||'—'}</span></div>
                                <div class="pf-line"><span class="pf-icon">🏃‍♂️‍➡️</span><span class="pf-key">Fast LLM</span><span class="pf-val">${llm2||'—'}</span></div>
                                <div class="pf-line"><span class="pf-icon">💪</span><span class="pf-key">Powerful LLM</span><span class="pf-val">${llm3||'—'}</span></div>
                                <div class="pf-line"><span class="pf-icon">🧪</span><span class="pf-key">Experimental LLM</span><span class="pf-val">${llm4||'—'}</span></div>
                                <div class="pf-line"><span class="pf-icon">📓</span><span class="pf-key">Diary LLM</span><span class="pf-val">${diary||'—'}</span></div>
                                <div class="pf-line"><span class="pf-icon">🧾</span><span class="pf-key">Formatter LLM</span><span class="pf-val">${formatter||'—'}</span></div>
                            </div>
                            <div class="actions">
                                <form method="get" action="core_profiles.php" onsubmit="return confirm('Delete this profile?');" style="display:inline">
                                    <input type="hidden" name="delete" value="${r.id}">
                                    <button type="submit" class="btn-danger">Delete</button>
                                </form>
                                <form method="get" action="core_profiles.php" style="display:inline">
                                    <input type="hidden" name="clone" value="${r.id}">
                                    <button type="submit" class="btn-primary">Clone</button>
                                </form>
                            </div>
                        </div>`;
                });
                list.innerHTML = html || '<div class="conn-li"><em>No profiles match filters.</em></div>';
                // wire slot jump links
                list.querySelectorAll('[data-jump-id]').forEach(el=>{
                    el.addEventListener('click', ()=>{
                        const id = el.getAttribute('data-jump-id');
                        if (id) window.location.href = `?edit=${id}`;
                    });
                });
                list.querySelectorAll('.conn-li').forEach(li => {
                    li.addEventListener('click', (ev) => {
                        if (ev.target.closest('a') || ev.target.closest('button') || ev.target.closest('form')) return;
                        const id = li.getAttribute('data-id');
                        if (id) window.location.href = `?edit=${id}`;
                    });
                });
            }
            render();
        })();
        </script>
    </div>
    <div class="llm-right">
        <div class="form-container wide-centered">
        <?php if (!$editItem): ?>
            <div class="connector-placeholder" style="border:1px dashed #4a4a4a; background:#2a2a2a; color:#9fb1c9; border-radius:10px; padding:18px; margin-bottom:10px;">
                <div style="font-weight:600; color:#e9efff; margin-bottom:6px;">No profile selected</div>
                <div>Select a profile from the list on the left to view and edit its settings.</div>
            </div>
        <?php endif; ?>
        <form id="core_profile_form" method="post" onsubmit='return saveProfileAjax(event, "core_profile_form")' style='<?= $editItem!=null?"":"display:none"?>'>
    <?php if ($editItem): ?>
        <input type="hidden" name="id" value="<?= $editItem["id"] ?>">
    <?php endif; ?>

    

    <div class="connector-card" style="margin-bottom:12px;">
        <div class="connector-title">Profile Core</div>
        <label for='label'>Name</label><br>
        <input type="text" name="label" placeholder="Name" value="<?= htmlspecialchars($editItem["label"] ?? "") ?>">
        <small class="hint">Name for the profile.</small>
        
        <div style="height:8px;"></div>
        <label for='slot' title="Can be assigned to NPCs ingame with the Settings Wheel hotkey">Slot <span style="margin-left:6px; color:#9fb1c9; cursor:help;" title="Can be assigned to NPCs ingame with the Settings Wheel hotkey">ⓘ</span></label><br>
        <?php
            $usedSlotsRows = $GLOBALS["db"]->fetchAll("SELECT id, slot FROM core_profiles WHERE slot IS NOT NULL ORDER BY slot ASC");
            $usedSlots = [];
            foreach ($usedSlotsRows as $r){ $s=intval($r['slot']??0); if ($s>=1 && $s<=4) $usedSlots[$s]=(int)$r['id']; }
            $currentId = (int)($editItem['id'] ?? 0);
            $currentSlot = isset($editItem['slot']) ? (int)$editItem['slot'] : 0;
        ?>
        <select name="slot" id="slot" title="Can be assigned to NPCs ingame with the Settings Wheel hotkey">
            <option value="">—</option>
            <?php for($s=1;$s<=4;$s++):
                $takenBy = $usedSlots[$s] ?? null;
                $disabled = ($takenBy !== null && $takenBy !== $currentId) ? ' disabled' : '';
                $sel = ($currentSlot === $s) ? ' selected' : '';
            ?>
            <option value="<?= $s ?>"<?= $sel.$disabled ?>><?= $s ?></option>
            <?php endfor; ?>
        </select>
        <small class="hint">Optional quick-access slot (1–4). Can be quickchanged ingame.</small>

        <div style="height:8px;"></div>
        <label class="label-with-toggle">👤Default NPC
            <input type="hidden" name="default_npc" value="0">
            <input type="checkbox" name="default_npc" value="1" <?= isset($editItem["default_npc"]) && $editItem["default_npc"] == 1 ? "checked" : "" ?>>
            <span class="toggle-text">On</span>
        </label>
        <small class="hint">When enabled, new NPCs will default to using this profile. Only 1 profile can be default.</small>

        <div style="height:6px;"></div>
        <label class="label-with-toggle">🗣️Default Narrator
            <input type="hidden" name="default_narrator" value="0">
            <input type="checkbox" name="default_narrator" value="1" <?= isset($editItem["default_narrator"]) && $editItem["default_narrator"] == 1 ? "checked" : "" ?>>
            <span class="toggle-text">On</span>
        </label>
        <small class="hint">When enabled, this profile is used for the narrator. Only 1 profile can be default narrator.</small>

        <div style="height:8px;"></div>
        <?php
            $dynamicProfileEnabled = false;
            try {
                if (!empty($editItem["metadata"])) {
                    $metaData = json_decode($editItem["metadata"], true);
                    if (is_array($metaData)) {
                        $dynamicProfileEnabled = !empty($metaData['DYNAMIC_PROFILE_ENABLED']);
                    }
                }
            } catch (Throwable $e) {}
        ?>
        <label class="label-with-toggle">♻️ Dynamic Profile
            <input type="hidden" name="meta_vis[DYNAMIC_PROFILE_ENABLED]" value="">
            <input type="checkbox" name="meta_vis[DYNAMIC_PROFILE_ENABLED]" value="1" <?= $dynamicProfileEnabled ? "checked" : "" ?>>
            <span class="toggle-text">Off</span>
        </label>
        <small class="hint">Allow systems to evolve NPC profiles based on gameplay events. NPCs using this profile will have dynamic profile enabled by default.</small>

        <div style="height:6px;"></div>
        <?php
            $mtmEnabled = false;
            try {
                if (!empty($editItem["metadata"])) {
                    $metaData = json_decode($editItem["metadata"], true);
                    if (is_array($metaData)) {
                        $mtmEnabled = !empty($metaData['MIDDLE_TERM_MEMORY_ENABLED']);
                    }
                }
            } catch (Throwable $e) {}
        ?>
        <label class="label-with-toggle">📃 Middle Term Memory
            <input type="hidden" name="meta_vis[MIDDLE_TERM_MEMORY_ENABLED]" value="">
            <input type="checkbox" name="meta_vis[MIDDLE_TERM_MEMORY_ENABLED]" value="1" <?= $mtmEnabled ? "checked" : "" ?>>
            <span class="toggle-text">Off</span>
        </label>
        <small class="hint">Saves a list of recent events after every 10 memory summaries. NPCs using this profile will have MTM enabled by default.</small>

        <div style="height:6px;"></div>
        <?php
            $autoDiaryEnabled = false;
            try {
                if (!empty($editItem["metadata"])) {
                    $metaData = json_decode($editItem["metadata"], true);
                    if (is_array($metaData)) {
                        $autoDiaryEnabled = !empty($metaData['AUTO_DIARY_ENABLED']);
                    }
                }
            } catch (Throwable $e) {}
        ?>
        <label class="label-with-toggle">📙 Auto Diary
            <input type="hidden" name="meta_vis[AUTO_DIARY_ENABLED]" value="">
            <input type="checkbox" name="meta_vis[AUTO_DIARY_ENABLED]" value="1" <?= $autoDiaryEnabled ? "checked" : "" ?>>
            <span class="toggle-text">Off</span>
        </label>
        <small class="hint">Automatically generate diary entries when NPCs are nearby during sleep/wait events. NPCs using this profile will have auto diary enabled by default.</small>

        <div style="height:8px;"></div>
        <label for="prompt">Profile Prompt</label>
        <textarea name="prompt" placeholder="<?= htmlspecialchars('') ?>"><?= htmlspecialchars($editItem["prompt"] ?? "") ?></textarea>
        <small class="hint">Optional: profile-specific system instructions appended to requests. Example is using this to hold specific instructions for followers and assigning the profile only to followers.</small>

        <div style="height:8px;"></div>
        <?php
            $randomizerEnabled = false;
            try {
                if (!empty($editItem["metadata"])) {
                    $metaData = json_decode($editItem["metadata"], true);
                    if (is_array($metaData)) {
                        $randomizerEnabled = !empty($metaData['LLM_RANDOMIZER_ENABLED']);
                    }
                }
            } catch (Throwable $e) {}
        ?>
        <label class="label-with-toggle">LLM Randomizer
            <input type="hidden" name="meta_vis[LLM_RANDOMIZER_ENABLED]" value="">
            <input type="checkbox" name="meta_vis[LLM_RANDOMIZER_ENABLED]" value="1" <?= $randomizerEnabled ? "checked" : "" ?>>
            <span class="toggle-text">Off</span>
        </label>
        <small class="hint">Randomly switches between the 4 LLM connectors for NPCs using this profile. Will roughly switch ever 2-3 responses per NPC. Is useful to add more variety to NPC responses and make them more dynamic.</small>

        <div style="height:6px;"></div>
        <?php
            $fallbackEnabled = false;
            try {
                if (!empty($editItem["metadata"])) {
                    $metaData = json_decode($editItem["metadata"], true);
                    if (is_array($metaData)) {
                        $fallbackEnabled = !empty($metaData['LLM_FALLBACK_ENABLED']);
                    }
                }
            } catch (Throwable $e) {}
        ?>
        <label class="label-with-toggle">🔄 LLM Fallback
            <input type="hidden" name="meta_vis[LLM_FALLBACK_ENABLED]" value="">
            <input type="checkbox" name="meta_vis[LLM_FALLBACK_ENABLED]" value="1" <?= $fallbackEnabled ? "checked" : "" ?>>
            <span class="toggle-text">Off</span>
        </label>
        <small class="hint">Automatically retry with fallback connector when primary connector fails. Please use a reliable, ideally cheaper connector. Response time will be longer when fallback is used.</small>

        <div style="margin-top:8px; display:flex; gap:8px;">
            <button type="button" id="btn_save_profile_settings" class="btn-save">Save Profile Settings</button>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function(){
        const names = ['default_npc','default_narrator','meta_vis[LLM_RANDOMIZER_ENABLED]','meta_vis[LLM_FALLBACK_ENABLED]','meta_vis[DYNAMIC_PROFILE_ENABLED]','meta_vis[MIDDLE_TERM_MEMORY_ENABLED]','meta_vis[AUTO_DIARY_ENABLED]'];
        names.forEach(n=>{
            const cb = document.querySelector(`input[type="checkbox"][name="${n}"]`);
            if (!cb) return;
            const label = cb.closest('label');
            const span = label ? label.querySelector('.toggle-text') : null;
            function sync(){ if (span) span.textContent = cb.checked ? 'On' : 'Off'; }
            sync();
            cb.addEventListener('change', sync);
        });
        const basicBtn = document.getElementById('btn_save_profile_settings');
        if (basicBtn){ basicBtn.addEventListener('click', function(ev){ try{ if (typeof showToast==='function') showToast('Saving...'); saveProfileAjax(ev, 'core_profile_form'); }catch(_e){} }); }
        const metaBtn = document.getElementById('btn_save_meta_settings');
        if (metaBtn){ metaBtn.addEventListener('click', function(ev){ try{ if (typeof showToast==='function') showToast('Saving...'); saveProfileAjax(ev, 'core_profile_form'); }catch(_e){} }); }
        const saveAllBtn = document.getElementById('btn_save_all');
        if (saveAllBtn){ saveAllBtn.addEventListener('click', function(ev){ try{ if (typeof showToast==='function') showToast('Saving all settings...'); saveProfileAjax(ev, 'core_profile_form'); }catch(_e){} }); }
        const backTopBtn = document.getElementById('btn_back_to_top');
        if (backTopBtn){ backTopBtn.addEventListener('click', function(){
            try { window.scrollTo({ top: 0, behavior: 'smooth' }); }
            catch(_) { window.scrollTo(0, 0); }
        }); }

        // Responsive iframe heights for embedded editors
        function sizeIframes(){
            try {
                const panes = ['frame_llm_primary_id','frame_llm_secondary_id','frame_llm_tertiary_id','frame_llm_quaternary_id','frame_diary_connector_id','frame_llm_formatter_id','frame_llm_fallback_id'];
                const vh = Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0);
                const available = Math.max(400, vh - 260);
                panes.forEach(id=>{ const f=document.getElementById(id); if (f) f.style.minHeight = available + 'px'; });
            } catch(_){ }
        }
        sizeIframes();
        window.addEventListener('resize', sizeIframes);
    });
    </script>

    <?php /* connector details preloaded above for both panes */ ?>

    <div class="connector-card">
        <div class="connector-title" title="Can swap the models all NPC use ingame with the Settings Wheel hotkey.">Connector Selection <span style="margin-left:6px; color:#9fb1c9; cursor:help;" title="Can swap the models all NPC use ingame with the Settings Wheel hotkey.">ⓘ</span></div>
        <div class="pf-tabs" id="pf_tabs">
            <button type="button" class="pf-tab active" data-pane="pane_llm1">🕹️ Standard LLM</button>
            <button type="button" class="pf-tab" data-pane="pane_llm2">🏃‍♂️‍➡️ Fast LLM</button>
            <button type="button" class="pf-tab" data-pane="pane_llm3">💪 Powerful LLM</button>
            <button type="button" class="pf-tab" data-pane="pane_llm4">🧪 Experimental LLM</button>
            <button type="button" class="pf-tab" data-pane="pane_diary">📓 Diary LLM</button>
            <button type="button" class="pf-tab" data-pane="pane_llm_formatter">🧾 Formatter LLM</button>
            <button type="button" class="pf-tab" data-pane="pane_llm_fallback">🔄 Fallback LLM</button>
            
        </div>
        <div class="pf-pane active" id="pane_llm1">
            <div class="select-row">
                <?= renderSelect($profiles, "llm_primary_id", "🕹️ Standard LLM", $editItem["llm_primary_id"] ?? "") ?>
                <button type="button" class="btn-apply btn-primary" data-apply-select="llm_primary_id">Set</button>
            </div>
            <div class="connector-help">
                General purpose LLM for general roleplay.
                <ul>
                    <li>meta-llama/llama-3.3-70b-instruct</li>
                    <li>deepseek/deepseek-chat-v3.1</li>
                    <li>qwen/qwen3-235b-a22b</li>
                </ul>
            </div>
            <div style="margin-top:8px;">
                <iframe id="frame_llm_primary_id" src="about:blank" style="width:100%; min-height:900px; border:1px solid #4a4a4a; border-radius:10px; background:transparent;"></iframe>
            </div>
        </div>
        <div class="pf-pane" id="pane_llm2">
            <div class="select-row">
                <?= renderSelect($profiles, "llm_secondary_id", "🏃‍♂️‍➡️ Fast LLM", $editItem["llm_secondary_id"] ?? "") ?>
                <button type="button" class="btn-apply btn-primary" data-apply-select="llm_secondary_id">Set</button>
            </div>
            <div class="connector-help">
                Fast and lesspowerful LLM for quick responses. Good for combat.
                <ul>
                    <li>google/gemini-2.5-flash-lite</li>
                    <li>Google Gemini 1.5 Flash</li>
                    <li>OpenRouter Llama-3.1-70B-Instruct</li>
                </ul>
            </div>
            <div style="margin-top:8px;">
                <iframe id="frame_llm_secondary_id" src="about:blank" style="width:100%; min-height:900px; border:1px solid #4a4a4a; border-radius:10px; background:transparent;"></iframe>
            </div>
        </div>
        <div class="pf-pane" id="pane_llm3">
            <div class="select-row">
                <?= renderSelect($profiles, "llm_tertiary_id", "💪 Powerful LLM", $editItem["llm_tertiary_id"] ?? "") ?>
                <button type="button" class="btn-apply btn-primary" data-apply-select="llm_tertiary_id">Set</button>
            </div>
            <div class="connector-help">
                Smarter and more expensive LLM for indepth conversations.
                <ul>
                    <li>anthropic/claude-3.7-sonnet</li>
                    <li>deepseek/deepseek-r1-0528</li>
                    <li>openai/gpt-5</li>
                </ul>
            </div>
            <div style="margin-top:8px;">
                <iframe id="frame_llm_tertiary_id" src="about:blank" style="width:100%; min-height:900px; border:1px solid #4a4a4a; border-radius:10px; background:transparent;"></iframe>
            </div>
        </div>
        <div class="pf-pane" id="pane_llm4">
            <div class="select-row">
                <?= renderSelect($profiles, "llm_quaternary_id", "🧪 Experimental LLM", $editItem["llm_quaternary_id"] ?? "") ?>
                <button type="button" class="btn-apply btn-primary" data-apply-select="llm_quaternary_id">Set</button>
            </div>
            <div class="connector-help">
                Wildcard and uncensored LLM's.
                <ul>
                    <li>qwen/qwen3-235b-a22b</li>
                    <li>deepseek/deepseek-chat-v3.1</li>
                    <li>anthropic/claude-3.7-sonnet</li>
                </ul>
            </div>
            <div style="margin-top:8px;">
                <iframe id="frame_llm_quaternary_id" src="about:blank" style="width:100%; min-height:900px; border:1px solid #4a4a4a; border-radius:10px; background:transparent;"></iframe>
            </div>
        </div>
        <div class="pf-pane" id="pane_diary">
            <div class="select-row">
                <?= renderSelect($profiles, "diary_connector_id", "📓 Diary LLM", $editItem["diary_connector_id"] ?? "") ?>
                <button type="button" class="btn-apply btn-primary" data-apply-select="diary_connector_id">Set</button>
            </div>
            <div class="connector-help">
                LLM good for writing character based diary entries.
                <ul>
                    <li>meta-llama/llama-3.3-70b-instruct</li>
                    <li>google/gemini-2.5-pro</li>
                    <li>Anthropic Claude 3.5 Sonnet</li>
                </ul>
            </div>
            <div style="margin-top:8px;">
                <iframe id="frame_diary_connector_id" src="about:blank" style="width:100%; min-height:900px; border:1px solid #4a4a4a; border-radius:10px; background:transparent;"></iframe>
            </div>
        </div>
        <div class="pf-pane" id="pane_llm_formatter">
            <div class="select-row">
                <?= renderSelect($profiles, "llm_formatter_id", "🧾 Formatter LLM", $editItem["llm_formatter_id"] ?? "") ?>
                <button type="button" class="btn-apply btn-primary" data-apply-select="llm_formatter_id">Set</button>
            </div>
            <div class="connector-help">
                Used to help format JSON responses for background tasks. Can be a very small model.
                <ul>
                    <li>OpenAI o3-mini / GPT-4o-mini</li>
                    <li>Anthropic Claude 3.5 Sonnet</li>
                    <li>OpenRouter Mistral-Nemo / Llama-3.1-70B</li>
                </ul>
            </div>
            <div style="margin-top:8px;">
                <iframe id="frame_llm_formatter_id" src="about:blank" style="width:100%; min-height:900px; border:1px solid #4a4a4a; border-radius:10px; background:transparent;"></iframe>
            </div>
        </div>
        <div class="pf-pane" id="pane_llm_fallback">
            <div class="select-row">
                <?= renderSelect($profiles, "llm_fallback_id", "🔄 Fallback LLM", $editItem["llm_fallback_id"] ?? "") ?>
                <button type="button" class="btn-apply btn-primary" data-apply-select="llm_fallback_id">Set</button>
            </div>
            <div class="connector-help">
                Backup connector used automatically when primary connectors fail due to network errors (connection failures, timeouts, HTTP errors). Must enable "🔄 LLM Fallback" toggle in Profile Core settings above.
                <ul>
                    <li>Choose a reliable, ideally cheaper connector</li>
                </ul>
            </div>
            <div style="margin-top:8px;">
                <iframe id="frame_llm_fallback_id" src="about:blank" style="width:100%; min-height:900px; border:1px solid #4a4a4a; border-radius:10px; background:transparent;"></iframe>
            </div>
        </div>
        
    </div>

    <!-- Visual Profile Settings (first chunk) -->
    <div class="connector-card profile-settings-card" style="margin-bottom:10px;">
        <div class="connector-title">Profile  Settings</div>
        <?php
            // Resolve current selected RPG comments from metadata
            $rpgSelected = [];
            try {
                $metaObj = [];
                if (!empty($editItem["metadata"])) {
                    $tmp = json_decode($editItem["metadata"], true);
                    if (is_array($tmp)) $metaObj = $tmp;
                }
                $arr = $metaObj['RPG_COMMENTS'] ?? [];
                if (is_array($arr)) { $rpgSelected = array_values(array_map('strval', $arr)); }
            } catch (Throwable $_e) { $rpgSelected = []; }
            // Resolve current selected Dynamic Profile Fields from metadata
            $dynSelected = [];
            try {
                $metaObj2 = [];
                if (!empty($editItem["metadata"])) {
                    $tmp2 = json_decode($editItem["metadata"], true);
                    if (is_array($tmp2)) $metaObj2 = $tmp2;
                }
                $arr2 = $metaObj2['DYNAMIC_PROFILE_FIELDS'] ?? [];
                if (is_array($arr2)) { $dynSelected = array_values(array_map('strval', $arr2)); }
            } catch (Throwable $_e) { $dynSelected = []; }
        ?>
        <?php if (!empty($__dynOptions)): ?>
        <div class="provider-card" style="margin-bottom:8px;">
            <div class="provider-head">
                <div class="provider-title">
                    <div class="provider-icon">🛠️</div>
                    <div>Dynamic Profile Fields</div>
                </div>
            </div>
            <div class="provider-body grid">
                <div style="grid-column: 1 / -1; display:flex; flex-wrap:wrap; gap:10px;">
                    <input type="hidden" name="meta_vis[DYNAMIC_PROFILE_FIELDS][]" value="">
                    <?php foreach ($__dynOptions as $opt): $val=(string)$opt; $checked = in_array($val, $dynSelected, true) ? ' checked' : ''; ?>
                        <label style="display:inline-flex; align-items:center; gap:6px; background:#1f2a36; border:1px solid #33485f; padding:6px 10px; border-radius:8px;">
                            <input type="checkbox" name="meta_vis[DYNAMIC_PROFILE_FIELDS][]" value="<?= htmlspecialchars($val) ?>"<?= $checked ?>>
                            <span><?= htmlspecialchars($val) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if (!empty($__dynHelp)): ?><div class="help" style="grid-column:1/-1;"><?= htmlspecialchars($__dynHelp) ?></div><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($__rpgOptions)): ?>
        <div class="provider-card" style="margin-bottom:8px;">
            <div class="provider-head">
                <div class="provider-title">
                    <div class="provider-icon">🎲</div>
                    <div>RPG Comments</div>
                </div>
            </div>
            <div class="provider-body grid">
                <div style="grid-column: 1 / -1; display:flex; flex-wrap:wrap; gap:10px;">
                    <input type="hidden" name="meta_vis[RPG_COMMENTS][]" value="">
                    <?php foreach ($__rpgOptions as $opt): $val=(string)$opt; $checked = in_array($val, $rpgSelected, true) ? ' checked' : ''; ?>
                        <label style="display:inline-flex; align-items:center; gap:6px; background:#1f2a36; border:1px solid #33485f; padding:6px 10px; border-radius:8px;">
                            <input type="checkbox" name="meta_vis[RPG_COMMENTS][]" value="<?= htmlspecialchars($val) ?>"<?= $checked ?>>
                            <span><?= htmlspecialchars($val) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if (!empty($__rpgHelp)): ?><div class="help" style="grid-column:1/-1;"><?= htmlspecialchars($__rpgHelp) ?></div><?php endif; ?>
                <?php
                    // Get current RPG_Comments_Chance value from metadata
                    $rpgChance = 100; // Default to 100%
                    try {
                        if (!empty($editItem["metadata"])) {
                            $tmpMeta = json_decode($editItem["metadata"], true);
                            if (is_array($tmpMeta) && isset($tmpMeta['RPG_COMMENTS_CHANCE'])) {
                                $rpgChance = intval($tmpMeta['RPG_COMMENTS_CHANCE']);
                            }
                        }
                    } catch (Throwable $_e) { $rpgChance = 100; }
                ?>
                <div style="grid-column: 1 / -1; margin-top: 12px; padding-top: 12px; border-top: 1px solid #33485f;">
                    <div style="color: #e9efff;">
                        <div style="font-weight: 600; margin-bottom: 6px;">🔁 Trigger Chance</div>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <input type="range" id="rpg_chance_range" min="0" max="100" step="1" value="<?= htmlspecialchars($rpgChance) ?>" oninput="document.getElementById('rpg_chance_num').value=this.value" style="flex: 1;">
                            <input type="number" id="rpg_chance_num" name="meta_vis[RPG_COMMENTS_CHANCE]" min="0" max="100" step="1" value="<?= htmlspecialchars($rpgChance) ?>" style="width:80px;" oninput="metaClamp('rpg_chance_range','rpg_chance_num',0,100)">
                        </div>
                        <div style="color: #9fb1c9; font-size: 12px; margin-top: 6px;">Probability that enabled RPG comments will trigger when their conditions are met. 0 = Never | 50 = 50% | 100 = Always</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php include(__DIR__."/tmpl/metadata_json_editor.php");?>
        <div style="margin-top:8px; display:flex; gap:8px;">
            <button type="button" id="btn_save_meta_settings" class="btn-save">Save Profile Settings</button>
            <button type="button" id="btn_save_all" class="btn-save">💾 Save All</button>
            <button type="button" id="btn_back_to_top" class="btn-primary" title="Scroll to top">↑ Back to top</button>
        </div>
    </div>
    
    <!-- Global Settings Overrides -->
    <div class="provider-card" style="margin-bottom:8px;">
        <div class="provider-head">
            <div class="provider-title">
                <div class="provider-icon">🌐</div>
                <div>Global Settings Overrides</div>
            </div>
        </div>
        <div class="provider-body" style="display:block;">
            <small style="color:#9fb1c9; display:block; margin-bottom:8px;">Override global settings for this profile. Changes here take precedence over global configurations.</small>
            <?php
            // Configure override editor for Profile mode
            $currentProfileOverrides = [];
            try {
                if (!empty($editItem["metadata"])) {
                    $metaData = json_decode($editItem["metadata"], true);
                    if (is_array($metaData)) {
                        $globalSettingKeys = ['TTSFUNCTION'];
                        foreach ($globalSettingKeys as $key) {
                            if (isset($metaData[$key])) {
                                $currentProfileOverrides[$key] = $metaData[$key];
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                $currentProfileOverrides = [];
            }
            $overrideEditorConfig = [
                'mode' => 'profile',
                'fieldName' => 'metadata',
                'allowedSettings' => ['TTSFUNCTION'],
                'reservedKeys' => ['DYNAMIC_PROFILE_ENABLED', 'MIDDLE_TERM_MEMORY_ENABLED', 'AUTO_DIARY_ENABLED', 'LLM_RANDOMIZER_ENABLED', 'RPG_COMMENTS', 'RPG_COMMENTS_CHANCE', 'DYNAMIC_PROFILE_FIELDS'],
                'currentData' => $currentProfileOverrides,
                'systemFields' => [],
            ];
            include(__DIR__."/tmpl/override_editor.php");
            ?>
        </div>
    </div>
    
    <!-- JSON Editor (second chunk) in collapsible -->
    <details id="metadata_section" class="collapsible">
        <summary class="collapsible-header">Metadata (Advanced JSON)</summary>
        <div class="collapsible-content">
            <textarea name="metadata" style="display:none" placeholder="Metadata"><?= htmlspecialchars($editItem["metadata"] ?? "") ?></textarea>
            <div id="metadata"></div>
        </div>
    </details>

    
    <script>
    // Sticky save bar styles
    (function(){
        const css = `
            .save-bar{position:sticky; bottom:0; z-index:999;}
            .save-bar-inner{display:flex; justify-content:flex-end; gap:8px; padding:8px; background:rgba(13,17,23,0.9); border-top:1px solid rgba(138,155,182,0.35); backdrop-filter: blur(3px);}
        `;
        const styleTag = document.createElement('style');
        styleTag.textContent = css;
        document.head.appendChild(styleTag);
    })();

    // Save handler that keeps the user on the same profile and shows a toast
    async function saveProfileAjax(ev, formId){
        try {
            if (typeof consolidation === 'function') {
                const ok = consolidation(ev, formId);
                if (ok === false) return false;
            }
        } catch(_e){}
        if (ev && typeof ev.preventDefault==='function') ev.preventDefault();
        const form = document.getElementById(formId) || ev.target;
        if (!form) return false;

        // First, attempt to persist any inline connector editors and embedded LLM editors
        try {
            await saveAllConnectorEditors();
        } catch (e) {
            // Surface connector save failure but continue to save profile data as well
            try { if (typeof showToast === 'function') showToast('Connector save failed: ' + e.message, true); } catch(_){ }
        }

        // Sync profile global overrides to metadata
        try {
            if (typeof window.syncProfileGlobalOverrides === 'function') {
                window.syncProfileGlobalOverrides();
            }
        } catch(_e) { console.error('Failed to sync profile global overrides:', _e); }

        const fd = new FormData(form);
        const hasId = !!(form.querySelector('input[name="id"]') && form.querySelector('input[name="id"]').value);
        if (hasId) {
            if (!fd.has('update')) fd.append('update','1');
        } else {
            if (!fd.has('create')) fd.append('create','1');
        }
        try {
            const res = await fetch('core_profiles.php', { method:'POST', headers:{ 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
            let json = {};
            try { json = await res.json(); } catch(_){ json = { ok:false, error:'Invalid response' }; }
            if (json && json.ok){
                if (json.id) {
                    // In case of create, set id and update URL
                    let idEl = form.querySelector('input[name="id"]');
                    if (!idEl){ idEl = document.createElement('input'); idEl.type='hidden'; idEl.name='id'; form.appendChild(idEl); }
                    idEl.value = String(json.id);
                    try { history.replaceState({}, '', 'core_profiles.php?edit='+encodeURIComponent(String(json.id))); } catch(_){ }
                }
                if (typeof showToast === 'function') showToast('Profile saved');
            } else {
                if (typeof showToast === 'function') showToast('Save failed: ' + (json && json.error ? json.error : 'Unknown error'), true);
            }
        } catch (e) {
            if (typeof showToast === 'function') showToast('Save failed: ' + e.message, true);
        }
        return false;
    }

    // Save only profile basics (label, default flags, prompt)
    async function saveProfileBasics(){
        try {
            const form = document.getElementById('core_profile_form');
            if (!form) return;
            const idEl = form.querySelector('input[name="id"]');
            const pid = idEl ? (idEl.value||'') : '';
            if (!pid){ if (typeof showToast==='function') showToast('Save failed: create the profile first', true); return; }
            const label = (form.querySelector('input[name="label"]').value||'');
            const defNpc = !!(form.querySelector('input[type=\"checkbox\"][name=\"default_npc\"]').checked) ? '1' : '0';
            const defNarr = !!(form.querySelector('input[type="checkbox"][name="default_narrator"]').checked) ? '1' : '0';
            const prompt = (form.querySelector('textarea[name="prompt"]').value||'');
            const slotSel = form.querySelector('select[name="slot"]');
            const slotVal = slotSel ? (slotSel.value||'') : '';
            const fmtSel = form.querySelector('select[name="llm_formatter_id"]');
            const fmtVal = fmtSel ? (fmtSel.value||'') : '';
            const fd = new FormData();
            fd.append('update','1');
            fd.append('id', pid);
            fd.append('label', label);
            fd.append('default_npc', defNpc);
            fd.append('default_narrator', defNarr);
            fd.append('prompt', prompt);
            fd.append('slot', slotVal);
            if (fmtSel) fd.append('llm_formatter_id', fmtVal);
            const res = await fetch('core_profiles.php', { method:'POST', headers:{ 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
            let json={}; try { json = await res.json(); } catch(_){ json = { ok:false, error:'Invalid response' }; }
            if (json && json.ok){
                if (typeof showToast==='function') showToast('Profile settings saved');
                try { updateLeftListBasics(label, defNpc==='1', defNarr==='1'); } catch(_e){}
            } else {
                if (typeof showToast==='function') showToast('Save failed: ' + (json && json.error ? json.error : 'Unknown error'), true);
            }
        } catch(e){ if (typeof showToast==='function') showToast('Save failed: ' + e.message, true); }
    }

    function updateLeftListBasics(newLabel, isDefaultNpc, isDefaultNarrator){
        const li = document.querySelector('.llm-left .conn-li[data-id="'+String(CURRENT_PROFILE_ID)+'"]');
        if (!li) return;
        const title = li.querySelector('.title');
        if (title) title.textContent = newLabel || title.textContent;
        const badges = li.querySelector('.pf-badges');
        if (badges){
            // Preserve existing NPC count badge if present
            const countBadge = Array.from(badges.children).find(el => /NPCs$/.test(el.textContent||''));
            badges.innerHTML='';
            if (countBadge) badges.appendChild(countBadge);
            if (isDefaultNpc){ const b=document.createElement('span'); b.className='pf-flag'; b.textContent='NPC'; badges.appendChild(b); }
            if (isDefaultNarrator){ const b=document.createElement('span'); b.className='pf-flag'; b.textContent='Narrator'; badges.appendChild(b); }
        }
    }

    // Persist inline TTS/ITT editors and embedded LLM editors before saving profile
    async function saveAllConnectorEditors(){
        const tasks = [];
        function enqueueInline(containerId, selectId){
            const container = document.getElementById(containerId);
            const sel = document.getElementById(selectId);
            if (!container || !sel) return;
            const id = sel.value || '';
            if (!id) return;
            const fd = new FormData();
            fd.append('inline_update_connector','1');
            fd.append('id', id);
            container.querySelectorAll('input,select,textarea').forEach(inp=>{
                const n = inp.name; if (!n) return;
                if (inp.type === 'checkbox') fd.append(n, inp.checked ? '1':'0'); else fd.append(n, inp.value);
            });
            tasks.push(
                fetch('core_profiles.php', { method:'POST', body: fd })
                    .then(r=>r.json())
                    .then(j=>{ if (!j || j.ok!==true) throw new Error((j && j.error) || 'Inline save failed'); })
            );
        }
        // (Inline editors for TTS/ITT removed; now embedded full pages are used)
        // Attempt to trigger embedded LLM editor saves (if available)
        const frameIds = ['frame_llm_primary_id','frame_llm_secondary_id','frame_llm_tertiary_id','frame_llm_quaternary_id','frame_diary_connector_id','frame_llm_formatter_id','frame_llm_fallback_id'];
        frameIds.forEach(fid => {
            const f = document.getElementById(fid);
            try {
                if (f && f.contentWindow && typeof f.contentWindow.handleEmbeddedSave === 'function'){
                    const p = f.contentWindow.handleEmbeddedSave();
                    // If the child returned a promise, await it
                    if (p && typeof p.then === 'function') tasks.push(p);
                }
            } catch(_){ }
        });
        if (tasks.length>0){ await Promise.allSettled(tasks); }
    }
    // Connector details passed from PHP
    const LLM_DETAILS = <?= json_encode($llmById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
    const TTS_DETAILS = <?= json_encode($ttsById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
    const ITT_DETAILS = <?= json_encode($ittById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
    const CURRENT_PROFILE_ID = <?= json_encode($editItem["id"] ?? '') ?>;

    function renderKVList(obj, keys, labels){
        if (!obj) return '<em style="color:#888">No connector selected.</em>';
        let html = '<div style="display:grid; grid-template-columns: 220px 1fr; gap:6px;">';
        for (let i=0;i<keys.length;i++){
            const k = keys[i], lab = labels[i];
            const val = (obj[k]===null||obj[k]===undefined||obj[k]==='') ? '<span style="color:#888">—</span>' : String(obj[k]);
            html += `<div style="color:rgb(242,124,17); font-weight:bold;">${lab}</div><div style="overflow-wrap:anywhere;">${val}</div>`;
        }
        html += '</div>';
        return html;
    }

    function renderEditor(containerId, conn, type){
        const container = document.getElementById(containerId);
        if (!container) return;
        if (!conn) { container.innerHTML = '<em style="color:#888">No connector selected.</em>'; return; }
        const bool = v => (v==1||v===true||v==='1') ? 'checked' : '';
        const val = k => (conn[k]===null||conn[k]===undefined? '' : String(conn[k]));
        if (type==='itt' || type==='diary'){
            container.innerHTML = `
                <div style=\"display:grid; grid-template-columns: 180px 1fr; gap:6px; align-items:center;\">
                    <div>Label</div><input name=\"label\" value=\"${escapeHtml(val('label'))}\">
                    <div>Driver</div><input name=\"driver\" value=\"${escapeHtml(val('driver'))}\">
                    <div>Metadata</div><input name=\"metadata\" value=\"${escapeHtml(val('metadata'))}\">
                </div>
                <div style=\"margin-top:8px; display:flex; gap:8px;\">
                    <button type=\"button\" class=\"action-button save\">Save</button>
                </div>
            `;
        } else if (type==='tts'){
            container.innerHTML = `
                <div style=\"display:grid; grid-template-columns: 180px 1fr; gap:6px; align-items:center;\">
                    <div>Label</div><input name=\"label\" value=\"${escapeHtml(val('label'))}\">
                    <div>Driver</div><input name=\"driver\" value=\"${escapeHtml(val('driver'))}\">
                    <div>URL</div><input name=\"url\" value=\"${escapeHtml(val('url'))}\">
                    <div>Voice Field</div><input name=\"voice_field\" value=\"${escapeHtml(val('voice_field'))}\">
                    <div>API Badge</div>${renderApiBadgeSelect('api_badge_id', val('api_badge_id'))}
                </div>
                <div style=\"margin-top:8px; display:flex; gap:8px;\">
                    <button type=\"button\" class=\"action-button save\">Save</button>
                </div>
            `;
        }
    }

    function renderApiBadgeSelect(name, selected){
        const options = <?= json_encode($apiBadgeRows ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
        let html = `<select name="${name}"><option value="">-- Select API Badge --</option>`;
        options.forEach(o => {
            const sel = String(o.id) === String(selected) ? ' selected' : '';
            html += `<option value="${String(o.id)}"${sel}>${escapeHtml(o.label)}</option>`;
        });
        html += '</select>';
        return html;
    }

    function wireEditorSave(editorEl, id, type){
        const saveBtn = editorEl.querySelector('button.save');
        if (!saveBtn) return;
        saveBtn.addEventListener('click', async ()=>{
            const formData = new FormData();
            formData.append('inline_update_connector','1');
            formData.append('id', id);
            editorEl.querySelectorAll('input,select,textarea').forEach(inp=>{
                const n = inp.name; if (!n) return;
                if (inp.type==='checkbox') formData.append(n, inp.checked ? '1' : '0'); else formData.append(n, inp.value);
            });
            const res = await fetch('core_profiles.php', { method:'POST', body: formData });
            let json = {}; try { json = await res.json(); } catch(_){}
            if (!json.ok){ alert('Save failed: '+(json.error||res.status)); return; }
            const updated = Object.fromEntries(Array.from(formData.entries()).filter(([k])=>k!=='inline_update_connector'&&k!=='id'));
            // Merge back into correct cache
            if (type==='llm') Object.assign(LLM_DETAILS[id] = (LLM_DETAILS[id]||{}), updated);
            if (type==='tts') Object.assign(TTS_DETAILS[id] = (TTS_DETAILS[id]||{}), updated);
            if (type==='itt') Object.assign(ITT_DETAILS[id] = (ITT_DETAILS[id]||{}), updated);
            // No separate preview; editor is the source of truth
        });
    }

    function refreshEditorFor(selectId, containerId, type){
        const sel = document.getElementById(selectId);
        const id = sel ? (sel.value||'') : '';
        let conn = null;
        if (type==='tts') conn = TTS_DETAILS[id];
        else if (type==='itt') conn = ITT_DETAILS[id];
        renderEditor(containerId, conn, type);
        const editorEl = document.getElementById(containerId);
        if (editorEl) wireEditorSave(editorEl, id, type);
    }

    // Embedded LLM editor via iframe (mirrors connectors UI 1:1)
    function refreshEmbeddedEditor(selectId, frameId){
        const sel = document.getElementById(selectId);
        const frame = document.getElementById(frameId);
        if (!frame) return;
        const id = sel ? (sel.value||'') : '';
        if (!id) { frame.src = 'about:blank'; return; }
        const base = <?= json_encode($webRoot) ?> + '/ui/core/llm_connectors.php?partial=editor&edit=' + encodeURIComponent(id);
        frame.src = base;
    }

    function initInlineEditors(){
        
        refreshEmbeddedEditor('diary_connector_id','frame_diary_connector_id');
        refreshEmbeddedEditor('llm_primary_id','frame_llm_primary_id');
        refreshEmbeddedEditor('llm_secondary_id','frame_llm_secondary_id');
        refreshEmbeddedEditor('llm_tertiary_id','frame_llm_tertiary_id');
        refreshEmbeddedEditor('llm_quaternary_id','frame_llm_quaternary_id');
        refreshEmbeddedEditor('llm_formatter_id','frame_llm_formatter_id');
        refreshEmbeddedEditor('llm_fallback_id','frame_llm_fallback_id');

        ['diary_connector_id','llm_primary_id','llm_secondary_id','llm_tertiary_id','llm_quaternary_id','llm_formatter_id','llm_fallback_id'].forEach(id=>{
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', ()=>{
                if (id==='diary_connector_id') refreshEmbeddedEditor(id,'frame_diary_connector_id');
                else if (id==='llm_primary_id') refreshEmbeddedEditor(id,'frame_llm_primary_id');
                else if (id==='llm_secondary_id') refreshEmbeddedEditor(id,'frame_llm_secondary_id');
                else if (id==='llm_tertiary_id') refreshEmbeddedEditor(id,'frame_llm_tertiary_id');
                else if (id==='llm_quaternary_id') refreshEmbeddedEditor(id,'frame_llm_quaternary_id');
                else if (id==='llm_formatter_id') refreshEmbeddedEditor(id,'frame_llm_formatter_id');
                else if (id==='llm_fallback_id') refreshEmbeddedEditor(id,'frame_llm_fallback_id');
            });
        });

        function updateLeftList(connectorField, connId){
            const li = document.querySelector('.llm-left .conn-li[data-id="'+String(CURRENT_PROFILE_ID)+'"]');
            if (!li) return;
            let label = '';
            if (connectorField==='tts_connector_id') label = (TTS_DETAILS[connId] && (TTS_DETAILS[connId].label||'')) || '';
            else if (connectorField==='itt_connector_id') label = (ITT_DETAILS[connId] && (ITT_DETAILS[connId].label||'')) || '';
            else label = (LLM_DETAILS[connId] && (LLM_DETAILS[connId].label||'')) || '';
            let key = '';
            if (connectorField==='llm_primary_id') key = 'LLM Standard';
            else if (connectorField==='llm_secondary_id') key = 'LLM Fast';
            else if (connectorField==='llm_tertiary_id') key = 'LLM Powerful';
            else if (connectorField==='llm_quaternary_id') key = 'LLM Experimental';
            else if (connectorField==='tts_connector_id') key = 'TTS';
            else if (connectorField==='itt_connector_id') key = 'ITT';
            else if (connectorField==='diary_connector_id') key = 'Diary';
            else if (connectorField==='llm_formatter_id') key = 'Formatter LLM';
            if (!key) return;
            const lines = li.querySelectorAll('.pf-line');
            lines.forEach(line=>{
                const k = line.querySelector('.pf-key');
                const v = line.querySelector('.pf-val');
                if (k && v && (k.textContent||'').trim()===key){ v.textContent = label || '—'; }
            });
        }

        document.querySelectorAll('.btn-apply[data-apply-select]').forEach(btn => {
            btn.addEventListener('click', ()=>{
                const selId = btn.getAttribute('data-apply-select');
                const sel = document.getElementById(selId);
                if (!sel) return;
                const value = sel.value || '';
                const formData = new FormData();
                formData.append('inline_update_profile','1');
                formData.append('id', <?= json_encode($editItem["id"] ?? 0) ?>);
                formData.append('field', selId);
                formData.append('value', value);
                fetch('core_profiles.php', { method:'POST', body: formData })
                    .then(r=>r.json()).then(json=>{
                        if (json && json.ok) {
                            showToast('Profile updated');
                            updateLeftList(selId, String(value));
                            if (selId==='llm_primary_id') refreshEmbeddedEditor(selId,'frame_llm_primary_id');
                            else if (selId==='llm_secondary_id') refreshEmbeddedEditor(selId,'frame_llm_secondary_id');
                            else if (selId==='llm_tertiary_id') refreshEmbeddedEditor(selId,'frame_llm_tertiary_id');
                            else if (selId==='llm_quaternary_id') refreshEmbeddedEditor(selId,'frame_llm_quaternary_id');
                            else if (selId==='diary_connector_id') refreshEmbeddedEditor(selId,'frame_diary_connector_id');
                            else if (selId==='llm_formatter_id') refreshEmbeddedEditor(selId,'frame_llm_formatter_id');
                            else if (selId==='llm_fallback_id') refreshEmbeddedEditor(selId,'frame_llm_fallback_id');
                        } else {
                            showToast('Update failed: ' + (json && json.error ? json.error : 'Unknown error'), true);
                        }
                    }).catch(e=>{
                        showToast('Update failed: ' + e.message, true);
                    });
            });
        });

        // Tabs wiring
        const tabs = document.querySelectorAll('.pf-tab');
        tabs.forEach(tb=>tb.addEventListener('click', ()=>{
            const target = tb.getAttribute('data-pane');
            document.querySelectorAll('.pf-tab').forEach(t=>t.classList.remove('active'));
            document.querySelectorAll('.pf-pane').forEach(p=>p.classList.remove('active'));
            tb.classList.add('active');
            const pane = document.getElementById(target);
            if (pane) pane.classList.add('active');
        }));
    }

    document.addEventListener('DOMContentLoaded', initInlineEditors);
    
    // Handle postMessage from embedded LLM connector iframes
    window.addEventListener('message', async function(event) {
        if (event.data && event.data.type === 'llm_connector_save') {
            try {
                const formData = new FormData();
                for (const [key, value] of Object.entries(event.data.data)) {
                    formData.append(key, value);
                }
                
                const response = await fetch('core_profiles.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                
                let result = {};
                try {
                    result = await response.json();
                } catch (e) {
                    result = { ok: false, error: 'Invalid response' };
                }
                
                // Send result back to iframe
                event.source.postMessage({
                    type: 'llm_connector_save_result',
                    success: result.ok === true,
                    error: result.error || null
                }, '*');
                
                // Show toast notification in parent
                if (result.ok === true) {
                    showToast('LLM connector saved successfully');
                    // Reload the originating iframe to reflect saved values
                    try {
                        const frames = document.querySelectorAll('iframe');
                        frames.forEach(f => { if (f && f.contentWindow === event.source) { f.src = f.src; } });
                    } catch(_e){}
                } else {
                    showToast('Save failed: ' + (result.error || 'Unknown error'), true);
                }
                
            } catch (error) {
                // Send error back to iframe
                event.source.postMessage({
                    type: 'llm_connector_save_result',
                    success: false,
                    error: error.message
                }, '*');
                
                showToast('Save failed: ' + error.message, true);
            }
        }
    });
    
    // Toast notification function
    function showToast(message, isError = false) {
        const toast = document.getElementById('toast');
        if (!toast) return;
        
        const messageEl = toast.querySelector('.message');
        if (messageEl) messageEl.textContent = message;
        
        toast.className = 'toast-notification show' + (isError ? ' error' : '');
        setTimeout(() => {
            toast.className = 'toast-notification';
        }, 3000);
    }
    
    // Collapse metadata by default and trigger resize like connectors
    (function(){
        var d = document.getElementById('metadata_section');
        if (!d) return;
        try { d.open = false; } catch(e){}
        d.addEventListener('toggle', function(){
            if (d.open) {
                try { window.dispatchEvent(new Event('resize')); } catch(e){}
                try { setTimeout(function(){ window.dispatchEvent(new Event('resize')); }, 50); } catch(e){}
            }
        });
    })();

    // Inline editor for LLM connectors
    function buildInlineEditorHTML(conn){
        if (!conn) return '<em style="color:#888">No connector selected.</em>';
        const bool = v => (v==1||v===true||v==='1') ? 'checked' : '';
        const val = k => (conn[k]===null||conn[k]===undefined? '' : String(conn[k]));
        return `
            <div style="display:grid; grid-template-columns: 80px 1fr; gap:6px; align-items:center;">
                <div>Label</div><input name="label" value="${escapeHtml(val('label'))}">
                <div>URL</div><input name="url" value="${escapeHtml(val('url'))}">
                <div>Provider</div><input name="provider" value="${escapeHtml(val('provider'))}">
                <div>Model</div><input name="model" value="${escapeHtml(val('model'))}">
                <div>Driver</div><input name="driver" value="${escapeHtml(val('driver'))}">
                <div>Temperature</div><input name="temperature" type="number" step="0.01" value="${escapeHtml(val('temperature'))}">
                <div>Max Tokens</div><input name="max_tokens" type="number" step="1" value="${escapeHtml(val('max_tokens'))}">
                <div>Presence Penalty</div><input name="presence_penalty" type="number" step="0.01" value="${escapeHtml(val('presence_penalty'))}">
                <div>Frequency Penalty</div><input name="frequency_penalty" type="number" step="0.01" value="${escapeHtml(val('frequency_penalty'))}">
                <div>Repetition Penalty</div><input name="repetition_penalty" type="number" step="0.01" value="${escapeHtml(val('repetition_penalty'))}">
                <div>top_p</div><input name="top_p" type="number" step="0.01" value="${escapeHtml(val('top_p'))}">
                <div>top_k</div><input name="top_k" type="number" step="1" value="${escapeHtml(val('top_k'))}">
                <div>min_p</div><input name="min_p" type="number" step="0.01" value="${escapeHtml(val('min_p'))}">
                <div>top_a</div><input name="top_a" type="number" step="0.01" value="${escapeHtml(val('top_a'))}">
                <div>Enforce JSON</div><input name="enforce_json" type="checkbox" value="1" ${bool(conn.enforce_json)}>
                <div>Prefill JSON</div><input name="prefill_json" type="checkbox" value="1" ${bool(conn.prefill_json)}>
                <div>Reasoning Model</div><input name="reasoning_model" type="checkbox" value="1" ${bool(conn.reasoning_model)}>
                <div>JSON Schema</div><input name="json_schema" type="checkbox" value="1" ${bool(conn.json_schema)}>
            </div>
            <div style="margin-top:8px; display:flex; gap:8px;">
                <button type="button" class="action-button save">Save</button>
                <button type="button" class="btn-secondary cancel">Cancel</button>
            </div>
        `;
    }

    function escapeHtml(s){
        return (s==null? '': String(s)).replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
    }

    function openInlineEditor(selectId){
        const sel = document.getElementById(selectId);
        const editor = document.getElementById('inline_'+selectId);
        if (!sel || !editor) return;
        const id = sel.value || '';
        const conn = LLM_DETAILS[id];
        editor.innerHTML = buildInlineEditorHTML(conn);
        editor.style.display = 'block';

        const saveBtn = editor.querySelector('button.save');
        const cancelBtn = editor.querySelector('button.cancel');
        if (cancelBtn) cancelBtn.addEventListener('click', ()=>{ editor.style.display='none'; editor.innerHTML=''; });
        if (saveBtn) saveBtn.addEventListener('click', async ()=>{
            const formData = new FormData();
            formData.append('inline_update_connector','1');
            formData.append('id', id);
            editor.querySelectorAll('input').forEach(inp=>{
                const n = inp.name; if (!n) return;
                if (inp.type==='checkbox') formData.append(n, inp.checked ? '1' : '0'); else formData.append(n, inp.value);
            });
            const res = await fetch('core_profiles.php', { method:'POST', body: formData });
            let json = {};
            try { json = await res.json(); } catch (_) {}
            if (!json.ok){ alert('Save failed: '+(json.error||res.status)); return; }
            // Update local cache and preview
            const updated = Object.fromEntries(Array.from(formData.entries()).filter(([k])=>k!=='inline_update_connector'&&k!=='id'));
            Object.assign(LLM_DETAILS[id] = (LLM_DETAILS[id]||{}), updated);
            updatePreview(selectId, 'preview_'+selectId, 'llm');
            editor.style.display='none'; editor.innerHTML='';
        });
    }

    document.querySelectorAll('button[data-edit-for]').forEach(btn=>{
        btn.addEventListener('click', ()=> openInlineEditor(btn.getAttribute('data-edit-for')));
    });
    </script>
    </script>

    </form>
    </div>
    </div>


<?php /* bottom table removed; use left list instead */ ?>

<!-- Profile Rules Modal -->
<div id="import_rules_modal" class="modal-backdrop">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">Profile Rules</h2>
            <div class="modal-actions">
                <button type="button" id="add_rule_btn" class="btn-save" style="margin-right: 10px;">+ New Rule</button>
                <button type="button" class="modal-close" id="close_rules_modal">Close</button>
            </div>
        </div>
        <div class="modal-body" style="padding: 16px; max-height: calc(85vh - 100px); overflow-y: auto;">
            <div class="connector-help" style="margin-bottom: 16px; padding: 12px; background: #1a1a1a; border: 1px solid #4a4a4a; border-radius: 8px;">
                <strong>About Profile Rules:</strong>
                    <p style="margin: 6px 0;">Profile Rules automatically apply when an NPC is Activated ingame. If an NPC that is activated matches the following ruleset, they will be assigned a custom profile of your choosing.</p>
                    <ul style="margin: 6px 0 0 16px; padding: 0;">
                    <li><strong>Match Fields:</strong> Use regex for name/race/base. Leave blank to match all. Gender is exact match.</li>
                    <li><strong>Regex examples:</strong>
                        <ul style="margin: 4px 0 0 16px; padding: 0;">
                            <li>
                                <strong>Name exact</strong>: <code>^lydia$</code>
                                <div style="color:#9fb1c9; font-size:12px; margin-top:2px;">matches: lydia &nbsp;|&nbsp; does not match: cicero</div>
                            </li>
                            <li>
                                <strong>Name starts with</strong>: <code>^mjoll</code>
                                <div style="color:#9fb1c9; font-size:12px; margin-top:2px;">matches: mjoll_the_lioness &nbsp;|&nbsp; does not match: lydia</div>
                            </li>
                            <li>
                                <strong>Name contains</strong>: <code>cicero</code>
                                <div style="color:#9fb1c9; font-size:12px; margin-top:2px;">matches: cicero &nbsp;|&nbsp; does not match: herika</div>
                            </li>
                            <li>
                                <strong>Names one of</strong>: <code>^(lydia|herika)$</code>
                                <div style="color:#9fb1c9; font-size:12px; margin-top:2px;">matches: lydia, herika &nbsp;|&nbsp; does not match: cicero</div>
                            </li>
                            <li>
                                <strong>Race one of</strong>: <code>^(argonian|imperial|nord|redguard|darkelf)$</code>
                                <div style="color:#9fb1c9; font-size:12px; margin-top:2px;">matches: argonian, nord &nbsp;|&nbsp; does not match: (any race not in the list)</div>
                            </li>
                        </ul>
                    </li>
                    <li><strong>Mods:</strong> Comma-separated list (e.g., "MyAwesomeFollower.esp,AiAgent.esp")</li>
                    <li><strong>Action:</strong> JSON object with NPC fields to set (e.g., <code>{"voiceid": "malenord", "refid": "A3000D67"}</code>)</li>
                    <li><strong>Priority:</strong> Higher numbers apply first. Use to override other rules.</li>
                </ul>
            </div>
            <div id="rules_list" style="display: flex; flex-direction: column; gap: 12px;">
                <!-- Rules will be loaded here -->
            </div>
        </div>
    </div>
</div>

<style>
/* Modal styling for Profile Rules */
#import_rules_modal.modal-backdrop { display:none; position:fixed; left:0; top:0; right:0; bottom:0; background:rgba(0,0,0,0.75); z-index:10000; }
#import_rules_modal.modal-backdrop.show { display:block; }
#import_rules_modal.modal-backdrop { opacity: 1 !important; backdrop-filter: none !important; filter: none !important; }
#import_rules_modal .modal-container {
    position:fixed;
    left:50%;
    top:50%;
    transform:translate(-50%, -50%);
    background:#2a2a2a;
    border:1px solid #4a4a4a;
    border-radius:10px;
    width: min(95vw, 1400px);
    max-height: 90vh;
    overflow: hidden;
    z-index: 10001;
}
#import_rules_modal .modal-header { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-bottom:1px solid #4a4a4a; background:#2a2a2a; position:sticky; top:0; z-index:2; }
#import_rules_modal .modal-title { margin:0; font-weight:700; color: rgb(242, 124, 17); font-family: 'MagicCards', serif; word-spacing: 6px; font-size: 1.6em; }
#import_rules_modal .modal-body { background:#2a2a2a; overflow:auto; max-height: calc(90vh - 60px); }
#import_rules_modal .modal-close { background:#3a3a3a; color:#fff; border:1px solid #4a4a4a; border-radius:6px; padding:6px 12px; cursor:pointer; }
#import_rules_modal .modal-close:hover { background:#4a4a4a; }
#import_rules_modal .modal-actions { display:flex; gap:8px; align-items:center; }

/* Rule card styling */
.rule-card { background: #2a2a2a; border: 1px solid #4a4a4a; border-radius: 10px; padding: 14px; }
.rule-card.editing { border-color: rgb(242, 124, 17); }
.rule-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.rule-title { font-weight: 700; color: rgb(242, 124, 17); font-size: 1.1em; }
.rule-actions { display: flex; gap: 6px; }
.rule-grid { display: grid; grid-template-columns: 180px 1fr; gap: 8px 12px; align-items: start; }
.rule-label { color: #9fb1c9; font-weight: 600; padding-top: 6px; }
.rule-value { color: #e9efff; }
.rule-value.empty { color: #666; font-style: italic; }
.rule-input { width: 100%; background: #1a1a1a; border: 1px solid #4a4a4a; border-radius: 6px; padding: 6px 8px; color: #e9efff; }
.rule-input:focus { border-color: rgb(242, 124, 17); outline: none; }
.rule-input.json { font-family: monospace; min-height: 60px; }
.rule-checkbox { accent-color: rgb(242, 124, 17); transform: scale(1.4); cursor: pointer; }
.btn-rule-edit { background: #3a3a3a; color: #e9efff; border: 1px solid #4a4a4a; border-radius: 6px; padding: 4px 10px; cursor: pointer; font-size: 12px; }
.btn-rule-edit:hover { background: #4a4a4a; }
.btn-rule-save { background: rgb(242, 124, 17); color: #111; border: 1px solid rgb(242, 124, 17); border-radius: 6px; padding: 4px 10px; cursor: pointer; font-weight: 700; font-size: 12px; }
.btn-rule-cancel { background: #3a3a3a; color: #e9efff; border: 1px solid #4a4a4a; border-radius: 6px; padding: 4px 10px; cursor: pointer; font-size: 12px; }
.btn-rule-delete { background: #8b0000; color: #fff; border: 1px solid #660000; border-radius: 6px; padding: 4px 10px; cursor: pointer; font-size: 12px; }
.btn-rule-delete:hover { background: #a00000; }
</style>

<script>
(function() {
    const modal = document.getElementById('import_rules_modal');
    const openBtn = document.getElementById('open_import_rules_btn');
    const closeBtn = document.getElementById('close_rules_modal');
    const addBtn = document.getElementById('add_rule_btn');
    const rulesList = document.getElementById('rules_list');
    
    const PROFILES = <?= json_encode($data ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
    
    let rulesData = [];
    let editingId = null;
    
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    
    function openModal() {
        modal.classList.add('show');
        loadRules();
    }
    
    function closeModal() {
        modal.classList.remove('show');
        editingId = null;
    }
    
    async function loadRules() {
        try {
            const res = await fetch('core_profiles.php?get_import_rules=1');
            const json = await res.json();
            if (json.ok && json.data) {
                rulesData = json.data;
                renderRules();
            }
        } catch (e) {
            console.error('Failed to load rules:', e);
        }
    }
    
    function renderRules() {
        if (rulesData.length === 0) {
            rulesList.innerHTML = '<div style="text-align:center; padding:40px; color:#888;">No import rules yet. Click "New Rule" to create one.</div>';
            return;
        }
        
        function parsePgTextArray(val){
            if (Array.isArray(val)) return val;
            if (typeof val !== 'string') return [];
            const s = val.trim();
            if (!(s.startsWith('{') && s.endsWith('}'))) return [];
            const inner = s.slice(1, -1);
            // Try JSON parse path first
            try {
                const json = '[' + inner + ']';
                const arr = JSON.parse(json);
                return Array.isArray(arr) ? arr : [];
            } catch(_e) {
                // Fallback: simple split handling quoted elements
                const items = [];
                let cur = '';
                let inQ = false;
                for (let i=0;i<inner.length;i++){
                    const ch = inner[i];
                    if (ch === '"') { inQ = !inQ; continue; }
                    if (ch === ',' && !inQ) { items.push(cur.trim()); cur=''; continue; }
                    cur += ch;
                }
                if (cur !== '') items.push(cur.trim());
                return items.map(x=>x.replace(/^\"|\"$/g,'').replace(/\\\"/g,'"').replace(/\\\\/g,'\\'));
            }
        }

        let html = '';
        rulesData.forEach(rule => {
            const isEditing = editingId === rule.id;
            const profileLabel = PROFILES.find(p => String(p.id) === String(rule.profile))?.label || '';
            
            // Parse match_mods array
            let modsStr = '';
            if (rule.match_mods) {
                if (Array.isArray(rule.match_mods)) {
                    modsStr = rule.match_mods.join(', ');
                } else if (typeof rule.match_mods === 'string') {
                    const arr = parsePgTextArray(rule.match_mods);
                    modsStr = arr.join(', ');
                }
            }
            
            html += `<div class="rule-card${isEditing ? ' editing' : ''}" data-id="${rule.id}">
                <div class="rule-header">
                    <div class="rule-title">${escapeHtml(rule.description || 'Untitled Rule')}</div>
                    <div class="rule-actions">
                        ${!isEditing ? `
                            <button type="button" class="btn-rule-edit" data-action="edit">Edit</button>
                            <button type="button" class="btn-rule-delete" data-action="delete">Delete</button>
                        ` : `
                            <button type="button" class="btn-rule-save" data-action="save">Save</button>
                            <button type="button" class="btn-rule-cancel" data-action="cancel">Cancel</button>
                        `}
                    </div>
                </div>
                <div class="rule-grid">
                    ${renderField('Description', 'description', rule.description || '', isEditing, 'text', true)}
                    ${renderField('Assign Profile', 'profile', rule.profile || '', isEditing, 'select', false, profileLabel)}
                    ${renderField('Priority', 'priority', rule.priority || 0, isEditing, 'number')}
                    ${renderField('Enabled', 'enabled', rule.enabled, isEditing, 'checkbox')}
                    ${renderField('Match Name (regex)', 'match_name', rule.match_name || '', isEditing, 'text')}
                    ${renderField('Match Race (regex)', 'match_race', rule.match_race || '', isEditing, 'text')}
                    ${renderField('Match Gender (regex)', 'match_gender', rule.match_gender || '', isEditing, 'text')}
                    ${renderField('Match Base (regex)', 'match_base', rule.match_base || '', isEditing, 'text')}
                    ${renderField('Match Mods (comma-separated)', 'match_mods', modsStr, isEditing, 'text')}
                    ${renderField('Action (JSON)', 'action', rule.action || '', isEditing, 'json')}
                </div>
            </div>`;
        });
        
        rulesList.innerHTML = html;
    }
    
    function renderField(label, name, value, isEditing, type = 'text', required = false, displayValue = null) {
        const displayVal = displayValue !== null ? displayValue : value;
        const isEmpty = !value && value !== 0 && value !== false;
        
        let fieldHtml = '';
        
        if (type === 'checkbox') {
            const checked = value === true || value === 't' || value === '1' || value === 1;
            if (isEditing) {
                fieldHtml = `<input type="checkbox" class="rule-checkbox rule-input-${name}" ${checked ? 'checked' : ''}>`;
            } else {
                fieldHtml = `<span class="rule-value">${checked ? '✓ Yes' : '✗ No'}</span>`;
            }
        } else if (type === 'select') {
            if (isEditing) {
                let options = '<option value="">-- None --</option>';
                PROFILES.forEach(p => {
                    const selected = String(p.id) === String(value) ? 'selected' : '';
                    options += `<option value="${p.id}" ${selected}>${escapeHtml(p.label || 'Profile #' + p.id)}</option>`;
                });
                fieldHtml = `<select class="rule-input rule-input-${name}">${options}</select>`;
            } else {
                fieldHtml = `<span class="rule-value${isEmpty ? ' empty' : ''}">${isEmpty ? '(none)' : escapeHtml(displayVal)}</span>`;
            }
        } else if (type === 'json') {
            if (isEditing) {
                fieldHtml = `<textarea class="rule-input rule-input-${name} json" ${required ? 'required' : ''}>${escapeHtml(value)}</textarea>`;
            } else {
                fieldHtml = `<span class="rule-value${isEmpty ? ' empty' : ''}">${isEmpty ? '(none)' : escapeHtml(value)}</span>`;
            }
        } else {
            if (isEditing) {
                const inputType = type === 'number' ? 'number' : 'text';
                fieldHtml = `<input type="${inputType}" class="rule-input rule-input-${name}" value="${escapeHtml(value)}" ${required ? 'required' : ''}>`;
            } else {
                fieldHtml = `<span class="rule-value${isEmpty ? ' empty' : ''}">${isEmpty ? '(none)' : escapeHtml(value)}</span>`;
            }
        }
        
        return `<div class="rule-label">${label}${required ? ' *' : ''}</div><div>${fieldHtml}</div>`;
    }
    
    window.IMPORT_RULES = {
        editRule: function(id) {
            editingId = id;
            renderRules();
        },
        
        cancelEdit: function() {
            editingId = null;
            renderRules();
        },
        
        saveRule: async function(id) {
            const card = document.querySelector(`.rule-card[data-id="${id}"]`);
            if (!card) return;
            
            const getData = (name) => {
                const el = card.querySelector(`.rule-input-${name}`);
                if (!el) return null;
                if (el.type === 'checkbox') return el.checked ? '1' : '0';
                return el.value.trim();
            };
            
            const formData = new FormData();
            formData.append('update_import_rule', '1');
            formData.append('id', id);
            formData.append('description', getData('description') || '');
            formData.append('match_name', getData('match_name') || '');
            formData.append('match_race', getData('match_race') || '');
            formData.append('match_gender', getData('match_gender') || '');
            formData.append('match_base', getData('match_base') || '');
            formData.append('match_mods', getData('match_mods') || '');
            // Validate JSON before sending; if invalid, show error and abort
            (function(){
                const raw = getData('action') || '';
                if (!raw) { formData.append('action', ''); return; }
                try {
                    const parsed = JSON.parse(raw);
                    formData.append('action', JSON.stringify(parsed));
                } catch (e) {
                    alert('Action must be valid JSON. Example: {"voiceid":"FemaleNord"}');
                    throw e;
                }
            })();
            formData.append('profile', getData('profile') || '');
            formData.append('priority', getData('priority') || '0');
            formData.append('enabled', getData('enabled') || '0');
            
            try {
                const res = await fetch('core_profiles.php', { method: 'POST', body: formData });
                const json = await res.json();
                if (json.ok) {
                    showToast('Rule updated successfully');
                    editingId = null;
                    loadRules();
                } else {
                    showToast('Failed to update rule', true);
                }
            } catch (e) {
                console.error('Save failed:', e);
                showToast('Error updating rule', true);
            }
        },
        
        deleteRule: async function(id) {
            if (!confirm('Delete this import rule?')) return;
            
            const formData = new FormData();
            formData.append('delete_import_rule', '1');
            formData.append('id', id);
            
            try {
                const res = await fetch('core_profiles.php', { method: 'POST', body: formData });
                const json = await res.json();
                if (json.ok) {
                    showToast('Rule deleted');
                    loadRules();
                } else {
                    showToast('Failed to delete rule', true);
                }
            } catch (e) {
                console.error('Delete failed:', e);
                showToast('Error deleting rule', true);
            }
        },
        
        createRule: async function() {
            const formData = new FormData();
            formData.append('create_import_rule', '1');
            formData.append('description', 'New Import Rule');
            formData.append('priority', '0');
            formData.append('enabled', '1');
            
            try {
                const res = await fetch('core_profiles.php', { method: 'POST', body: formData });
                const json = await res.json();
                if (json.ok) {
                    showToast('Rule created');
                    loadRules();
                    setTimeout(() => {
                        window.IMPORT_RULES.editRule(json.id);
                    }, 100);
                } else {
                    showToast('Failed to create rule', true);
                }
            } catch (e) {
                console.error('Create failed:', e);
                showToast('Error creating rule', true);
            }
        }
    };
    
    function showToast(message, isError = false) {
        if (typeof window.showToast === 'function') {
            window.showToast(message, isError);
        } else {
            alert(message);
        }
    }
    
    if (openBtn) openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (addBtn) addBtn.addEventListener('click', () => window.IMPORT_RULES.createRule());

    // Delegate clicks for rule action buttons to ensure handlers exist after re-render
    rulesList.addEventListener('click', (ev) => {
        const btn = ev.target.closest('button[data-action]');
        if (!btn) return;
        const card = btn.closest('.rule-card');
        if (!card) return;
        const id = card.getAttribute('data-id');
        const action = btn.getAttribute('data-action');
        if (action === 'edit') return window.IMPORT_RULES.editRule(id);
        if (action === 'delete') return window.IMPORT_RULES.deleteRule(id);
        if (action === 'save') return window.IMPORT_RULES.saveRule(id);
        if (action === 'cancel') return window.IMPORT_RULES.cancelEdit();
    });
    
    // Close modal when clicking outside
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
})();
</script>

</main>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
