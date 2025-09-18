<?php

$enginePath = __DIR__.DIRECTORY_SEPARATOR."../../";

require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."logger.php");

require_once "{$enginePath}/lib/core/core_profiles.class.php";
require_once "{$enginePath}/lib/core/llm_connector.class.php";

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
$TITLE = "👤 CHIM - Core Profiles";
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

    <h1 class="api-title">Core Profiles</h1>

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
        foreach ((array)$_POST['meta_vis'] as $k=>$v) $base[$k] = $v;
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
        foreach ((array)$_POST['meta_vis'] as $k=>$v) $base[$k] = $v;
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
            'label','url','model','provider','driver','max_tokens','temperature','presence_penalty','frequency_penalty','repetition_penalty','top_p','top_k','min_p','top_a','enforce_json','prefill_json','reasoning_model','json_schema','api_badge_id'
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
            'llm_primary_id','llm_secondary_id','llm_tertiary_id','llm_quaternary_id',
            'diary_connector_id','tts_connector_id','itt_connector_id'
        ];
        if (!in_array($field, $allowed, true)) { echo json_encode(["ok"=>false, "error"=>"Invalid field"]); exit; }
        $raw = $_POST['value'] ?? '';
        $val = ($raw === '' ? null : intval($raw));
        $data = [ $field => $val ];
        $ok = $profiles->update($id, $data);
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
    $profiles->clone($_GET["clone"]);
    header("Location: core_profiles.php");
    exit;
}

// Create a blank profile and open it for editing
if (isset($_GET["create_blank"])) {
    try {
        $row = $GLOBALS["db"]->fetchOne("INSERT INTO core_profiles (label) VALUES ('New Profile') RETURNING id");
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
                    html += '<div class="connector-title">Profile Slots</div>';
                    [1,2,3,4].forEach(s=>{
                        const r = slotToProfile[s];
                        if (r){
                            const title = escapeHtml(r.label||('Profile #'+r.id));
                            html += `<div class=\"slot-row\" style=\"cursor:pointer;\" data-jump-id=\"${String(r.id)}\"><span class=\"slot-key\">Slot ${String(s)}</span><span class=\"slot-val\">${title}</span></div>`;
                        } else {
                            html += `<div class=\"slot-row\" style=\"opacity:.75;\"><span class=\"slot-key\">Slot ${String(s)}</span><span class=\"slot-val\">— Empty —</span></div>`;
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
        <label for='slot'>Slot</label><br>
        <?php
            $usedSlotsRows = $GLOBALS["db"]->fetchAll("SELECT id, slot FROM core_profiles WHERE slot IS NOT NULL ORDER BY slot ASC");
            $usedSlots = [];
            foreach ($usedSlotsRows as $r){ $s=intval($r['slot']??0); if ($s>=1 && $s<=4) $usedSlots[$s]=(int)$r['id']; }
            $currentId = (int)($editItem['id'] ?? 0);
            $currentSlot = isset($editItem['slot']) ? (int)$editItem['slot'] : 0;
        ?>
        <select name="slot" id="slot">
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
        <label class="label-with-toggle">Default NPC
            <input type="hidden" name="default_npc" value="0">
            <input type="checkbox" name="default_npc" value="1" <?= isset($editItem["default_npc"]) && $editItem["default_npc"] == 1 ? "checked" : "" ?>>
            <span class="toggle-text">On</span>
        </label>
        <small class="hint">When enabled, new NPCs will default to using this profile. Only 1 profile can be default.</small>

        <div style="height:6px;"></div>
        <label class="label-with-toggle">Default Narrator
            <input type="hidden" name="default_narrator" value="0">
            <input type="checkbox" name="default_narrator" value="1" <?= isset($editItem["default_narrator"]) && $editItem["default_narrator"] == 1 ? "checked" : "" ?>>
            <span class="toggle-text">On</span>
        </label>
        <small class="hint">When enabled, this profile is used for the narrator. Only 1 profile can be default narrator.</small>

        <div style="height:8px;"></div>
        <label for="prompt">Profile Prompt</label>
        <textarea name="prompt" placeholder="<?= htmlspecialchars('') ?>"><?= htmlspecialchars($editItem["prompt"] ?? "") ?></textarea>
        <small class="hint">Optional: profile-specific system instructions appended to requests. Example is using this to hold specific instructions for followers and assigning the profile only to followers.</small>

        <div style="margin-top:8px; display:flex; gap:8px;">
            <button type="button" id="btn_save_profile_settings" class="btn-save">Save Profile Settings</button>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function(){
        const names = ['default_npc','default_narrator'];
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
        if (basicBtn){ basicBtn.addEventListener('click', function(){ try{ saveProfileBasics(); }catch(_e){} }); }
        const metaBtn = document.getElementById('btn_save_meta_settings');
        if (metaBtn){ metaBtn.addEventListener('click', function(ev){ try{ if (typeof showToast==='function') showToast('Saving...'); saveProfileAjax(ev, 'core_profile_form'); }catch(_e){} }); }

        // Responsive iframe heights for embedded editors
        function sizeIframes(){
            try {
                const panes = ['frame_llm_primary_id','frame_llm_secondary_id','frame_llm_tertiary_id','frame_llm_quaternary_id','frame_diary_connector_id'];
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
        <div class="connector-title">Connector Selection</div>
        <div class="pf-tabs" id="pf_tabs">
            <button type="button" class="pf-tab active" data-pane="pane_llm1">🕹️ Standard LLM</button>
            <button type="button" class="pf-tab" data-pane="pane_llm2">🏃‍♂️‍➡️ Fast LLM</button>
            <button type="button" class="pf-tab" data-pane="pane_llm3">💪 Powerful LLM</button>
            <button type="button" class="pf-tab" data-pane="pane_llm4">🧪 Experimental LLM</button>
            <button type="button" class="pf-tab" data-pane="pane_diary">📓 Diary LLM</button>
            
        </div>
        <div class="pf-pane active" id="pane_llm1">
            <div class="select-row">
                <?= renderSelect($profiles, "llm_primary_id", "🕹️ Standard LLM", $editItem["llm_primary_id"] ?? "") ?>
                <button type="button" class="btn-apply btn-primary" data-apply-select="llm_primary_id">Set</button>
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
            <div style="margin-top:8px;">
                <iframe id="frame_llm_secondary_id" src="about:blank" style="width:100%; min-height:900px; border:1px solid #4a4a4a; border-radius:10px; background:transparent;"></iframe>
            </div>
        </div>
        <div class="pf-pane" id="pane_llm3">
            <div class="select-row">
                <?= renderSelect($profiles, "llm_tertiary_id", "💪 Powerful LLM", $editItem["llm_tertiary_id"] ?? "") ?>
                <button type="button" class="btn-apply btn-primary" data-apply-select="llm_tertiary_id">Set</button>
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
            <div style="margin-top:8px;">
                <iframe id="frame_llm_quaternary_id" src="about:blank" style="width:100%; min-height:900px; border:1px solid #4a4a4a; border-radius:10px; background:transparent;"></iframe>
            </div>
        </div>
        <div class="pf-pane" id="pane_diary">
            <div class="select-row">
                <?= renderSelect($profiles, "diary_connector_id", "📓 Diary LLM", $editItem["diary_connector_id"] ?? "") ?>
                <button type="button" class="btn-apply btn-primary" data-apply-select="diary_connector_id">Set</button>
            </div>
            <div style="margin-top:8px;">
                <iframe id="frame_diary_connector_id" src="about:blank" style="width:100%; min-height:900px; border:1px solid #4a4a4a; border-radius:10px; background:transparent;"></iframe>
            </div>
        </div>
        
    </div>

    <!-- Visual Profile Settings (first chunk) -->
    <div class="connector-card profile-settings-card" style="margin-bottom:10px;">
        <div class="connector-title">Profile  Settings</div>
        <?php include(__DIR__."/tmpl/metadata_json_editor.php");?>
        <div style="margin-top:8px; display:flex; gap:8px;">
            <button type="button" id="btn_save_meta_settings" class="btn-save">Save Profile Settings</button>
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
            const fd = new FormData();
            fd.append('update','1');
            fd.append('id', pid);
            fd.append('label', label);
            fd.append('default_npc', defNpc);
            fd.append('default_narrator', defNarr);
            fd.append('prompt', prompt);
            fd.append('slot', slotVal);
            const res = await fetch('core_profiles.php', { method:'POST', headers:{ 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
            let json={}; try { json = await res.json(); } catch(_){ json = { ok:false, error:'Invalid response' }; }
            if (json && json.ok){
                if (typeof showToast==='function') showToast('Profile settings saved');
                try { updateLeftListBasics(label, defNpc==='1', defNarr==='1'); } catch(_e){}
                try { window.location.reload(); } catch(_){}
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
        const frameIds = ['frame_llm_primary_id','frame_llm_secondary_id','frame_llm_tertiary_id','frame_llm_quaternary_id','frame_diary_connector_id'];
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

        ['diary_connector_id','llm_primary_id','llm_secondary_id','llm_tertiary_id','llm_quaternary_id'].forEach(id=>{
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', ()=>{
                if (id==='diary_connector_id') refreshEmbeddedEditor(id,'frame_diary_connector_id');
                else if (id==='llm_primary_id') refreshEmbeddedEditor(id,'frame_llm_primary_id');
                else if (id==='llm_secondary_id') refreshEmbeddedEditor(id,'frame_llm_secondary_id');
                else if (id==='llm_tertiary_id') refreshEmbeddedEditor(id,'frame_llm_tertiary_id');
                else if (id==='llm_quaternary_id') refreshEmbeddedEditor(id,'frame_llm_quaternary_id');
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

</main>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
