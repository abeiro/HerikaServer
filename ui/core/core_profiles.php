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
.wide-centered { max-width: 1100px; margin: 0 auto; }
.two-col-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
.connector-card { background: #2a2a2a; border: 1px solid #4a4a4a; border-radius: 8px; padding: 12px; }
.connector-title { font-family: 'MagicCards', serif; color: rgb(242, 124, 17); margin-bottom: 8px; font-size: 1.1em; }
@media (max-width: 1000px) { .two-col-grid { grid-template-columns: 1fr; } }
/* Split layout like LLM Connectors */
.llm-layout { display:grid; grid-template-columns: minmax(260px, 420px) 1fr; gap:16px; align-items:start; }
@media (max-width: 1100px) { .llm-layout { grid-template-columns: minmax(220px, 300px) 1fr; } }
@media (max-width: 860px) { .llm-layout { grid-template-columns: minmax(200px, 260px) 1fr; } }
.llm-left { position: sticky; top: 72px; align-self:start; max-height: calc(100vh - 110px); overflow:auto; padding-right:4px; }
.llm-right { min-width: 0; }
.list-filters { display:flex; gap:8px; align-items:center; margin:6px 0 10px; flex-wrap:wrap; }
.list-filters input[type="text"]{ width: 100%; max-width: 260px; }
.list-filters select { max-width: 200px; }
.conn-list { display:flex; flex-direction:column; gap:8px; }
.conn-li { border:1px solid rgba(138,155,182,0.35); background:#0d1117; border-radius:10px; padding:10px; cursor:pointer; transition:transform .08s ease, background .12s ease; }
.conn-li:hover { background:#121826; transform: translateY(-1px); }
.conn-li.active { outline:2px solid rgb(242,124,17); }
.conn-li .head { display:flex; justify-content:space-between; gap:8px; align-items:center; }
.conn-li .title { font-weight:600; color:#e9efff; }
.conn-li .badge { font-size:11px; padding:2px 6px; border:1px solid rgba(138,155,182,0.4); border-radius:999px; color:#9fb1c9; }
.conn-li .sub { font-size:12px; color:#9fb1c9; margin-top:3px; overflow-wrap:anywhere; }
.conn-li .actions { display:flex; gap:6px; margin-top:6px; justify-content:flex-end; }
.pf-badges { display:flex; gap:6px; align-items:center; }
.pf-flag { font-size:11px; padding:2px 6px; border:1px solid rgba(138,155,182,0.4); border-radius:999px; color:#9fb1c9; }
.pf-lines { display:flex; flex-direction:column; gap:4px; margin-top:6px; }
.pf-line { display:flex; align-items:center; gap:8px; font-size:12px; color:#cfd9ea; }
.pf-icon { width:18px; text-align:center; opacity:0.9; }
.pf-key { color:#9fb1c9; min-width:44px; font-weight:600; }
.pf-val { overflow-wrap:anywhere; }
</style>

<main>
    <div id="toast" class="toast-notification">
        <span class="message"></span>
    </div>

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
    $profiles->create($_POST);
    header("Location: core_profiles.php");
    exit;
}

// Handle Update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update"])) {
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
    header("Location: core_profiles.php");
    exit;
}

// Handle Delete
if (isset($_GET["delete"])) {
    $profiles->delete($_GET["delete"]);
    header("Location: core_profiles.php");
    exit;
}

// Inline update handler for LLM connectors (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["inline_update_connector"])) {
    header('Content-Type: application/json');
    try {
        $llm = new LLMConnector();
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(["ok"=>false, "error"=>"Invalid id"]); exit; }

        $allowed = [
            'label','url','model','provider','driver','max_tokens','temperature','presence_penalty','frequency_penalty','repetition_penalty','top_p','top_k','min_p','top_a','enforce_json','prefill_json','reasoning_model','json_schema'
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

// Add a new action for cloning a connector
if (isset($_GET["clone"])) {
    $profiles->clone($_GET["clone"]);
    header("Location: core_profiles.php");
    exit;
}

// Fetch Data
$data = $profiles->readAll();
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

<h1>Core Profiles</h1>

<div class="llm-layout">
    <div class="llm-left">
        <div class="list-filters">
            <input id="pflist_q" type="text" placeholder="Search profiles...">
            <span id="pflist_count" class="badge"></span>
        </div>
        <div id="profiles_list" class="conn-list"></div>
        <script>
        (function(){
            const RAW = <?= json_encode($data ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
            const ACTIVE_ID = <?= json_encode($_GET['edit'] ?? '') ?>;
            const list = document.getElementById('profiles_list');
            const q = document.getElementById('pflist_q');
            const count = document.getElementById('pflist_count');
            const LLM = <?= json_encode($llmById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
            const TTS = <?= json_encode($ttsById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
            const ITT = <?= json_encode($ittById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
            function escapeHtml(s){ return (s==null?'':String(s)).replace(/[&<>]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])); }
            function labelOf(map, id){ if (!id) return ''; const k=String(id); const row=map[k]; return row && (row.label||row.model||row.driver) ? (row.label||'') : ''; }
            function pass(row){
                const qq=(q.value||'').toLowerCase();
                if (!qq) return true;
                const hay=[row.label,row.llm_primary_id,row.llm_secondary_id,row.llm_tertiary_id,row.llm_quaternary_id,row.tts_connector_id,row.itt_connector_id,row.diary_connector_id]
                    .map(v=>String(v||'').toLowerCase()).join('\n');
                return hay.includes(qq);
            }
            function render(){
                const rows=(RAW||[]).filter(pass);
                count.textContent = rows.length+" shown";
                let html='';
                rows.forEach(r=>{
                    const active = String(r.id)===String(ACTIVE_ID) ? ' active' : '';
                    const llm1 = escapeHtml(labelOf(LLM, r.llm_primary_id));
                    const llm2 = escapeHtml(labelOf(LLM, r.llm_secondary_id));
                    const llm3 = escapeHtml(labelOf(LLM, r.llm_tertiary_id));
                    const llm4 = escapeHtml(labelOf(LLM, r.llm_quaternary_id));
                    const tts = escapeHtml(labelOf(TTS, r.tts_connector_id));
                    const itt = escapeHtml(labelOf(ITT, r.itt_connector_id));
                    const diary = escapeHtml(labelOf(LLM, r.diary_connector_id));
                    const flags = [];
                    if (String(r.default_npc)==='1') flags.push('<span class="pf-flag">NPC</span>');
                    if (String(r.default_narrator)==='1') flags.push('<span class="pf-flag">Narrator</span>');
                    html += `
                        <div class="conn-li${active}" data-id="${String(r.id)}">
                            <div class="head">
                                <div class="title">${escapeHtml(r.label||('Profile #'+r.id))}</div>
                                <div class="pf-badges">${flags.join(' ')}</div>
                            </div>
                            <div class="pf-lines">
                                <div class="pf-line"><span class="pf-icon">🧠</span><span class="pf-key">LLM1</span><span class="pf-val">${llm1||'—'}</span></div>
                                <div class="pf-line"><span class="pf-icon">🧠</span><span class="pf-key">LLM2</span><span class="pf-val">${llm2||'—'}</span></div>
                                <div class="pf-line"><span class="pf-icon">🧠</span><span class="pf-key">LLM3</span><span class="pf-val">${llm3||'—'}</span></div>
                                <div class="pf-line"><span class="pf-icon">🧠</span><span class="pf-key">LLM4</span><span class="pf-val">${llm4||'—'}</span></div>
                                <div class="pf-line"><span class="pf-icon">🔊</span><span class="pf-key">TTS</span><span class="pf-val">${tts||'—'}</span></div>
                                <div class="pf-line"><span class="pf-icon">🎙️</span><span class="pf-key">ITT</span><span class="pf-val">${itt||'—'}</span></div>
                                <div class="pf-line"><span class="pf-icon">📓</span><span class="pf-key">Diary</span><span class="pf-val">${diary||'—'}</span></div>
                            </div>
                            <div class="actions">
                                <a class="btn-danger" href="?delete=${r.id}" onclick="return confirm('Delete this profile?');">Delete</a>
                                <a class="action-button" href="?clone=${r.id}">Clone</a>
                            </div>
                        </div>`;
                });
                list.innerHTML = html || '<div class="conn-li"><em>No profiles match filters.</em></div>';
                list.querySelectorAll('.conn-li').forEach(li => {
                    li.addEventListener('click', (ev) => {
                        if (ev.target.closest('a')) return;
                        const id = li.getAttribute('data-id');
                        if (id) window.location.href = `?edit=${id}`;
                    });
                });
            }
            q.addEventListener('input', render);
            render();
        })();
        </script>
    </div>
    <div class="llm-right">
        <?php if ($editItem): ?>
            <h2>Edit Profile (ID: <?= htmlspecialchars($editItem["id"]) ?>)</h2>
        <?php else: ?>
            <h2 onclick='document.forms[0].style.display="block"'>Create New Profile</h2>
        <?php endif; ?>

        <div class="form-container wide-centered">
        <form id="core_profile_form" method="post" onsubmit='return consolidation(event, "core_profile_form")' style='<?= $editItem!=null?"":"display:none"?>'>
    <?php if ($editItem): ?>
        <input type="hidden" name="id" value="<?= $editItem["id"] ?>">
    <?php endif; ?>

    <label for='label'>Label</label><br>
    <input type="text" name="label" placeholder="Label" value="<?= htmlspecialchars($editItem["label"] ?? "") ?>">
    
    <label>Default NPC:</label><br>
    <label>
        <input type="radio" name="default_npc" value="1" <?= isset($editItem["default_npc"]) && $editItem["default_npc"] == 1 ? "checked" : "" ?>>
        True
    </label>
    <label>
        <input type="radio" name="default_narrator" value="0" <?= isset($editItem["default_narrator"]) && $editItem["default_narrator"] == 0 ? "checked" : "" ?>>
        False
    </label>
    <br>

    <?php /* connector details preloaded above for both panes */ ?>

    <div class="two-col-grid">
        <div class="connector-card">
            <div class="connector-title">TTS Connector</div>
            <?= renderSelect($profiles, "tts_connector_id", "TTS Connector", $editItem["tts_connector_id"] ?? "") ?>
            <div id="preview_tts_connector_id"></div>
        </div>
        <div class="connector-card">
            <div class="connector-title">ITT Connector</div>
            <?= renderSelect($profiles, "itt_connector_id", "ITT Connector", $editItem["itt_connector_id"] ?? "") ?>
            <div id="preview_itt_connector_id"></div>
        </div>
        <div class="connector-card">
            <div class="connector-title">Diary Connector</div>
            <?= renderSelect($profiles, "diary_connector_id", "Diary Connector", $editItem["diary_connector_id"] ?? "") ?>
            <div class="inline-editor" id="editor_diary_connector_id" style="margin-top:8px;"></div>
        </div>
        <div class="connector-card">
            <div class="connector-title">LLM Primary</div>
            <?= renderSelect($profiles, "llm_primary_id", "LLM Primary", $editItem["llm_primary_id"] ?? "") ?>
            <div class="inline-editor" id="editor_llm_primary_id" style="margin-top:8px;"></div>
        </div>
        <div class="connector-card">
            <div class="connector-title">LLM Secondary</div>
            <?= renderSelect($profiles, "llm_secondary_id", "LLM Secondary", $editItem["llm_secondary_id"] ?? "") ?>
            <div class="inline-editor" id="editor_llm_secondary_id" style="margin-top:8px;"></div>
        </div>
        <div class="connector-card">
            <div class="connector-title">LLM Tertiary</div>
            <?= renderSelect($profiles, "llm_tertiary_id", "LLM Tertiary", $editItem["llm_tertiary_id"] ?? "") ?>
            <div class="inline-editor" id="editor_llm_tertiary_id" style="margin-top:8px;"></div>
        </div>
        <div class="connector-card">
            <div class="connector-title">LLM Quaternary</div>
            <?= renderSelect($profiles, "llm_quaternary_id", "LLM Quaternary", $editItem["llm_quaternary_id"] ?? "") ?>
            <div class="inline-editor" id="editor_llm_quaternary_id" style="margin-top:8px;"></div>
        </div>
    </div>

    <label for="prompt">Profile Prompt</label>
    <textarea name="prompt" placeholder=""><?= htmlspecialchars($editItem["prompt"] ?? "") ?></textarea>

    <!-- Metadata visual + JSON editors -->
    <textarea name="metadata" style="display:none" placeholder="Metadata"><?= htmlspecialchars($editItem["metadata"] ?? "") ?></textarea>
    <?php include(__DIR__."/tmpl/metadata_json_editor.php");?>
    <div id="metadata"></div>

    <button type="submit" name="<?= $editItem ? "update" : "create" ?>" class="btn-save"><?= $editItem ? "Update" : "Create" ?></button>
    <script>
    // Connector details passed from PHP
    const LLM_DETAILS = <?= json_encode($llmById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
    const TTS_DETAILS = <?= json_encode($ttsById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
    const ITT_DETAILS = <?= json_encode($ittById ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;

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
        if (type==='llm'){
            container.innerHTML = `
                <div style="display:grid; grid-template-columns: 180px 1fr; gap:6px; align-items:center;">
                    <div>Label</div><input name="label" value="${escapeHtml(val('label'))}">
                    <div>URL</div><input name="url" value="${escapeHtml(val('url'))}">
                    <div>Provider</div><input name="provider" value="${escapeHtml(val('provider'))}">
                    <div>Model</div><input name="model" value="${escapeHtml(val('model'))}">
                    <div>Driver</div><input name="driver" value="${escapeHtml(val('driver'))}">
                    <div>API Badge</div>${renderApiBadgeSelect('api_badge_id', val('api_badge_id'))}
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
                </div>
            `;
        } else if (type==='itt' || type==='diary'){
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
        if (type==='llm') conn = LLM_DETAILS[id];
        else if (type==='tts') conn = TTS_DETAILS[id];
        else if (type==='itt') conn = ITT_DETAILS[id];
        renderEditor(containerId, conn, type);
        const editorEl = document.getElementById(containerId);
        if (editorEl) wireEditorSave(editorEl, id, type);
    }

    function initInlineEditors(){
        refreshEditorFor('tts_connector_id','preview_tts_connector_id','tts');
        refreshEditorFor('itt_connector_id','preview_itt_connector_id','itt');
        refreshEditorFor('diary_connector_id','editor_diary_connector_id','llm');
        refreshEditorFor('llm_primary_id','editor_llm_primary_id','llm');
        refreshEditorFor('llm_secondary_id','editor_llm_secondary_id','llm');
        refreshEditorFor('llm_tertiary_id','editor_llm_tertiary_id','llm');
        refreshEditorFor('llm_quaternary_id','editor_llm_quaternary_id','llm');

        ['tts_connector_id','itt_connector_id','diary_connector_id','llm_primary_id','llm_secondary_id','llm_tertiary_id','llm_quaternary_id'].forEach(id=>{
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', ()=>{
                if (id==='tts_connector_id') refreshEditorFor(id,'preview_tts_connector_id','tts');
                else if (id==='itt_connector_id') refreshEditorFor(id,'preview_itt_connector_id','itt');
                else if (id==='diary_connector_id') refreshEditorFor(id,'editor_diary_connector_id','llm');
                else if (id==='llm_primary_id') refreshEditorFor(id,'editor_llm_primary_id','llm');
                else if (id==='llm_secondary_id') refreshEditorFor(id,'editor_llm_secondary_id','llm');
                else if (id==='llm_tertiary_id') refreshEditorFor(id,'editor_llm_tertiary_id','llm');
                else if (id==='llm_quaternary_id') refreshEditorFor(id,'editor_llm_quaternary_id','llm');
            });
        });
    }

    document.addEventListener('DOMContentLoaded', initInlineEditors);

    // Inline editor for LLM connectors
    function buildInlineEditorHTML(conn){
        if (!conn) return '<em style="color:#888">No connector selected.</em>';
        const bool = v => (v==1||v===true||v==='1') ? 'checked' : '';
        const val = k => (conn[k]===null||conn[k]===undefined? '' : String(conn[k]));
        return `
            <div style="display:grid; grid-template-columns: 160px 1fr; gap:6px; align-items:center;">
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
