<?php
$enginePath = __DIR__ . "/../../";

require_once($enginePath . "conf/conf.php");
require_once($enginePath . "lib/model_dynmodel.php");
require_once($enginePath . "lib/{$GLOBALS["DBDRIVER"]}.class.php");
if (!isset($GLOBALS["db"])) { $GLOBALS["db"] = new sql(); }
require_once($enginePath . "lib/chat_helper_functions.php");
require_once($enginePath . "lib/data_functions.php");
require_once($enginePath . "lib/logger.php");
require_once("{$enginePath}/lib/core/llm_connector.class.php");

//function renderSelect($obj, $fieldName, $labelText, $selectedValue = "") 
//function include from bewlow file
include(__DIR__."/tmpl/ui_utils.php");

// Determine web root and bring in site chrome like oghma_upload
$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
if ($uiPos !== false) {
    $webRoot = substr($scriptPath, 0, $uiPos);
} else {
    $webRoot = '';
}
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__.DIRECTORY_SEPARATOR."../profile_loader.php");
$GLOBALS["db"] = $GLOBALS["db"] ?? null;

// Load connector version information
$cachedConnectorVersion = '';
$cachedConnectorVersionVerbose = '';
if (file_exists($enginePath . 'connector/openrouterjsoncached.php')) {
    require_once($enginePath . 'connector/openrouterjsoncached.php');
    if (class_exists('openrouterjsoncached')) {
        $cachedConnectorVersion = defined('openrouterjsoncached::VERSION') ? openrouterjsoncached::VERSION : '';
    }
}
if (file_exists($enginePath . 'connector/openrouterjsoncached_verbose.php')) {
    require_once($enginePath . 'connector/openrouterjsoncached_verbose.php');
    if (class_exists('openrouterjsoncached_verbose')) {
        $cachedConnectorVersionVerbose = defined('openrouterjsoncached_verbose::VERSION') ? openrouterjsoncached_verbose::VERSION : '';
    }
}

// Early Export CSV handler (must run before any output)
if (isset($_GET["export"])) {
    if (!$GLOBALS["db"]) { $GLOBALS["db"] = new sql(); }
    $llmEarly = new LLMConnector();
    $idEarly = $_GET['export'];
    $rowEarly = $llmEarly->getById($idEarly);
    if (!$rowEarly) { header('HTTP/1.1 404 Not Found'); echo 'Not found'; exit; }
    $colsEarly = [
        'id','label','service','url','model','provider','driver','api_badge_id','max_tokens','temperature','presence_penalty','frequency_penalty','repetition_penalty','top_p','top_k','min_p','top_a','enforce_json','json_schema','prefill_json','reasoning_model','metadata'
    ];
    $boolKeysEarly = ['enforce_json','json_schema','prefill_json','reasoning_model'];
    $filenameBase = (string)($rowEarly['label'] ?? ('connector_'.$idEarly));
    if ($filenameBase==='') { $filenameBase = 'connector_'.$idEarly; }
    $filename = $filenameBase . '.csv';
    $asciiName = str_replace(["\r","\n","\""], '', $filename);
    header('Content-Type: text/csv; charset=utf-8');
    $cd = 'attachment; filename="'.str_replace(['\\','"'], '', $asciiName).'"; filename*=UTF-8\'\'' . rawurlencode($filename);
    header('Content-Disposition: ' . $cd);
    $outEarly = fopen('php://output','w');
    fputcsv($outEarly, $colsEarly);
    $valsEarly = [];
    foreach ($colsEarly as $k){
        $v = $rowEarly[$k] ?? '';
        if (in_array($k, $boolKeysEarly, true)) {
            if ($v === '' || $v === null) { $v = ''; }
            else { $v = ((int)$v) ? 'true' : 'false'; }
        }
        if ($k==='metadata' && is_array($v)) { $v = json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
        $valsEarly[] = $v;
    }
    fputcsv($outEarly, $valsEarly);
    fclose($outEarly);
    exit;
}
$TITLE = "🧠 CHIM - LLM Connectors";
ob_start();
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/head.html");
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
/* Match Oghma page spacing and title styling */
@font-face {
    font-family: 'MagicCards';
    src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype');
    font-weight: normal;
    font-style: normal;
}
main { padding: 30px 5px 5px; }
h1.api-title {
    margin: 0 0 20px 0;
    font-family: 'MagicCards', serif;
    word-spacing: 8px;
    font-size: 2.2em;
    color: rgb(242, 124, 17);
    text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
    text-align: center;
}
.llm-left .llm-title { font-family: 'MagicCards', serif; word-spacing: 6px; }
.toast-notification { position: fixed; top: 20px; right: 20px; padding: 12px 20px; border-radius: 8px; color: #fff; font-weight: 500; z-index: 10000; opacity: 0; transform: translateX(400px); transition: all 0.3s ease; max-width: 400px; }
.toast-notification.show { opacity: 1; transform: translateX(0); }
.toast-notification:not(.error) { background: linear-gradient(135deg, #6dd19c, #5bb377); border: 1px solid rgba(109, 209, 156, 0.3); }
.toast-notification.error { background: linear-gradient(135deg, #ff6b6b, #e55a5a); border: 1px solid rgba(255, 107, 107, 0.3); }
</style>

<main class="d-flex flex-column">
<?php
$noticeMsg = '';
if (isset($_GET['notice']) && $_GET['notice'] !== '') {
    $noticeMsg = (string)$_GET['notice'];
}
$GLOBALS["db"] = new sql();
$llm = new LLMConnector();

// Read network IPs for Custom service helper buttons
$WSL_IP = '';
$HOST_IP = '';
try {
    $row = $GLOBALS["db"]->fetchOne("SELECT value FROM conf_opts WHERE id='Network/WSL_IP' LIMIT 1");
    if (isset($row["value"])) $WSL_IP = $row["value"]; 
} catch (Exception $e) {}
try {
    $row = $GLOBALS["db"]->fetchOne("SELECT value FROM conf_opts WHERE id='Network/HOST_IP' LIMIT 1");
    if (isset($row["value"])) $HOST_IP = $row["value"]; 
} catch (Exception $e) {}

// Lightweight partial to render only the editor UI (for embedding)
if (isset($_GET["partial"]) && $_GET["partial"] === "editor") {
    $id = $_GET["edit"] ?? '';
    $editItem = null;
    if ($id !== '') { $editItem = $llm->getById($id); }
    // Determine web root for assets
    $scriptPath = $_SERVER['SCRIPT_NAME'];
    $uiPos = strpos($scriptPath, '/ui/');
    if ($uiPos !== false) { $webRoot = substr($scriptPath, 0, $uiPos); } else { $webRoot = ''; }
    if ($webRoot == '/') $webRoot = '';
    $webRoot = rtrim($webRoot, '/');
    // Fetch API badges for select
    $apiRows = $GLOBALS["db"]->fetchAll("SELECT id, label, api_key FROM core_api_badge ORDER BY label ASC");
    $selectedApi = $editItem["api_badge_id"] ?? "";
    $withKey = [];
    $noKey = [];
    $isEmptyKey = function($v){
        $raw = trim((string)$v);
        if ($raw === '') return true;
        if (preg_match('/^(?:\*+|null|none|n\\/a)$/i', $raw)) return true;
        if (preg_match('/^[^A-Za-z0-9]+$/', $raw)) return true;
        return false;
    };
    foreach ($apiRows as $r){ if (!$isEmptyKey($r['api_key'] ?? '')) $withKey[]=$r; else $noKey[]=$r; }
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <style>
    .two-col-llm { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    @media (max-width: 1000px) { .two-col-llm { grid-template-columns: 1fr; } }
    .kv-grid { display: grid; grid-template-columns: 220px 1fr; gap: 8px 12px; align-items: center; }
    .inline-num { width: 90px; }
    .service-picker { display:flex; align-items:center; gap:12px; margin: 6px 0 12px; }
    .service-icons { display:flex; gap:8px; align-items:center; }
    .service-icon { width:56px; height:56px; border:1px solid rgba(138,155,182,0.3); border-radius:8px; cursor:pointer; opacity:0.8; }
    .service-icon.active { outline:2px solid rgb(242,124,17); opacity:1; }
    .tip-label { position: relative; cursor: help; }
    .tip-label::after { content: attr(data-tip); position: absolute; left: 0; top: 120%; max-width: 560px; padding: 8px 10px; background: #0c0f14; color: #cfe0ff; border: 1px solid rgba(138,155,182,0.35); border-radius: 8px; box-shadow: 0 6px 18px rgba(0,0,0,0.35); white-space: normal; line-height: 1.3; font-size: 12px; opacity: 0; transform: translateY(-4px); transition: opacity .12s ease, transform .12s ease; pointer-events: none; z-index: 9999; }
    .tip-label:hover::after { opacity: 1; transform: translateY(0); }
    .api-key-notice { margin-top:6px; font-size:12px; }
    .api-key-notice.warn { color:#ffb862; }
    .api-key-notice.ok { color:#6dd19c; }
    .orm-dropdown { position:absolute; z-index: 9999; max-height: 360px; overflow:auto; background:#111; border:1px solid rgba(138,155,182,0.3); border-radius:8px; box-shadow: 0 6px 18px rgba(0,0,0,0.35); display:none; min-width: 420px; }
    .orm-item { padding:8px 10px; cursor:pointer; border-bottom:1px solid rgba(138,155,182,0.15); }
    .orm-item:last-child { border-bottom:none; }
    .orm-item:hover { background:#1a1f29; }
    .orm-head { padding:8px 10px; font-weight:bold; position:sticky; top:0; background:#0c0f14; border-bottom:1px solid rgba(138,155,182,0.3); }
    .orm-note { padding:6px 10px; font-size:12px; color:#97a6ba; border-bottom:1px dashed rgba(138,155,182,0.25); background:#0c0f14; }
    .orm-muted { color:#97a6ba; }
    .orm-err { color:#ff6b6b; padding:8px 10px; }
    .orm-info-box { border:1px solid rgba(138,155,182,0.3); background:#0d1117; border-radius:8px; padding:8px 10px; margin-top:8px; max-width: 800px; }
    /* Inline title + toggle styling */
    .label-with-toggle { display:flex; align-items:center; gap:10px; }
    .label-with-toggle input[type="checkbox"] { accent-color:#176529; transform: scale(1.8); transform-origin:center; cursor:pointer; }
    </style>
    <script>
    // Define consolidation() if not present (embedded partial doesn't include metadata editor)
    if (typeof window.consolidation !== 'function') {
        window.consolidation = function(){ return true; };
    }
    
    // Check if we're in an iframe (embedded in profiles)
    const isInIframe = (()=>{ try { return window.parent && window.parent !== window; } catch(_){ return true; } })();
    // Expose for inline handler check
    window.isInIframe = isInIframe;
    
    // Show test button only when NOT embedded in iframe
    if (!isInIframe) {
        const testBtn = document.getElementById('btn_test_connector');
        const testNote = document.getElementById('test_note');
        if (testBtn) testBtn.style.display = '';
        if (testNote) testNote.style.display = '';
    }
    
    // If in iframe, override form submission to use postMessage
    if (isInIframe) {
        window.handleEmbeddedSave = async function() {
            const form = document.querySelector('form[method="post"]');
            if (!form) return;
            
            // Disable save button during request
            const saveBtn = form.querySelector('button[name="save"]');
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';
            }
            
            const formData = new FormData(form);
            const data = {};
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            
            // Add inline_update_connector flag for parent page handler
            data.inline_update_connector = '1';
            
            // Try direct POST to parent endpoint to avoid postMessage failures
            try {
                const fd = new FormData();
                Object.entries(data).forEach(([k,v])=>fd.append(k, v));
                const res = await fetch('core_profiles.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
                let json = {};
                try { json = await res.json(); } catch(_){ json = { ok:false, error:'Invalid response' }; }
                // Re-enable button and show local toast
                if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save'; }
                try { if (typeof window.showToast==='function') { window.showToast(json.ok ? 'Saved successfully' : ('Save failed: ' + (json.error||'Unknown error')), json.ok?false:true); } else { const toast = document.getElementById('toast'); if (toast){ const msg = toast.querySelector('.message'); if (msg) msg.textContent = json.ok ? 'Saved successfully' : ('Save failed: ' + (json.error||'Unknown error')); toast.classList.add('show'); setTimeout(()=>{ toast.classList.remove('show'); }, 2000); } } } catch(_e){}
                if (json.ok) { try { window.location.reload(); } catch(_e){} }
                // Notify parent as well
                if (window.parent && window.parent.postMessage) {
                    window.parent.postMessage({ type:'llm_connector_save_result', success: json.ok===true, error: json.error||null }, '*');
                }
            } catch (e) {
                if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save'; }
                try { if (typeof window.showToast==='function') { window.showToast('Save failed: ' + e.message, true); } else { const toast = document.getElementById('toast'); if (toast){ const msg = toast.querySelector('.message'); if (msg) msg.textContent = 'Save failed: ' + e.message; toast.classList.add('show'); setTimeout(()=>{ toast.classList.remove('show'); }, 2000); } } } catch(_e){}
                if (window.parent && window.parent.postMessage) {
                    window.parent.postMessage({ type:'llm_connector_save_result', success:false, error: e.message }, '*');
                }
            }
        };
        
        // Listen for save results from parent
        window.addEventListener('message', function(event) {
            if (event.data && event.data.type === 'llm_connector_save_result') {
                const saveBtn = document.querySelector('button[name="save"]');
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save';
                }
                
                const message = event.data.success ? 'Saved successfully' : ('Save failed: ' + (event.data.error || 'Unknown error'));
                try { if (typeof window.showToast==='function') { window.showToast(message, event.data.success?false:true); } else { const toast = document.getElementById('toast'); if (toast&&toast.querySelector('.message')){ toast.querySelector('.message').textContent = message; toast.classList.add('show'); setTimeout(()=>{ toast.classList.remove('show'); }, 2500); } } } catch(_e){}
            }
        });
    }
    </script>
    <form method="post" onsubmit='if (window.isInIframe) { window.handleEmbeddedSave(); return false; } return consolidation();' style='<?= $editItem!=null?"":"display:none"?>'>
        <?php if ($editItem): ?>
            <input type="hidden" name="id" value="<?= $editItem["id"] ?>">
        <?php endif; ?>
        <input type="hidden" name="partial" value="editor">
        <textarea name="metadata" style="display:none"><?= htmlspecialchars($editItem["metadata"] ?? "{}") ?></textarea>
        <div class="two-col-llm">
            <div>
                <div class="top-actions" style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">
                    <?php if ($editItem): ?>
                        <button type="submit" name="save" class="btn-save">Save</button>
                        <button type="button" id="btn_test_connector" class="btn-primary" style="display:none;">Test</button>
                        <div class="orm-note" id="test_note" style="margin-top:6px; display:none;">Please save any changes before testing to ensure the latest settings are used.</div>
                    <?php else: ?>
                        <button type="submit" name="create" class="btn-save">Create</button>
                    <?php endif; ?>
                </div>
                <label for='label'>Name</label><br>
                <input type="text" name="label" value="<?= htmlspecialchars($editItem["label"] ?? "") ?>"><br>

                <label id="service_label">Service</label>
                <div class="service-picker">
                    <div class="service-icons">
                        <img src="<?= $webRoot; ?>/ui/images/core/icons/openrouter.jpg" alt="OpenRouter" class="service-icon" data-service="openrouter" />
                        <img src="<?= $webRoot; ?>/ui/images/core/icons/openai.jpg" alt="OpenAI" class="service-icon" data-service="openai" />
                        <img src="<?= $webRoot; ?>/ui/images/core/icons/google.jpg" alt="Google" class="service-icon" data-service="google" />
                    <img src="<?= $webRoot; ?>/ui/images/core/icons/nanogpt.jpg" alt="NanoGPT" class="service-icon" data-service="nanogpt" />
                        <img src="<?= $webRoot; ?>/ui/images/core/icons/custom.jpg" alt="Custom" class="service-icon" data-service="custom" />
                    </div>
                </div>
                <input type="hidden" id="service_input" name="service" value="<?= htmlspecialchars($editItem["service"] ?? "") ?>">

                <div id="custom_note" class="orm-muted" style="font-size:12px; display:none; margin:-6px 0 8px 0;">
                    Custom allows you to build your own connector setting using one of our API drivers to use non-supported services with CHIM. For advanced users only
                </div>

                <div id="url_row">
                    <label for='url'>URL</label><br>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <input type="text" name="url" value="<?= htmlspecialchars($editItem["url"] ?? "") ?>" style="flex:1 1 auto;">
                        <button type="button" id="btn_wsl_ip" class="btn-primary" title="Use WSL IP" style="display:none;" data-ip="<?= htmlspecialchars($WSL_IP) ?>">WSL IP</button>
                        <button type="button" id="btn_host_ip" class="btn-primary" title="Use HOST IP" style="display:none;" data-ip="<?= htmlspecialchars($HOST_IP) ?>">PC IP</button>
                    </div>
                </div>

                <label for='model'>Model</label><br>
                <input type="text" name="model" value="<?= htmlspecialchars($editItem["model"] ?? "") ?>"><br>

                <div id="provider_row">
                    <label for='provider'>Provider</label><br>
                    <input type="text" name="provider" placeholder="(Optional) leave empty to use recommended provider" value="<?= htmlspecialchars($editItem["provider"] ?? "") ?>"><br>
                </div>

                <div id="driver_row" style="display:none;">
                    <label for='driver'>Driver</label><br>
                    <input type="text" id="driver_input" name="driver" value="<?= htmlspecialchars($editItem["driver"] ?? "") ?>" style="display:none"><br>
                    <select id="driver_select" style="display:none">
                        <option value="openrouterjson">OpenRouter JSON</option>
                        <option value="openrouterjsoncached">OpenRouter JSON (Cached)</option>
                        <option value="openrouterjsoncached_verbose">OpenRouter JSON (Cached + Verbose Logging)</option>
                        <option value="openaijson">OpenAI JSON</option>
                        <option value="google_openaijson">Google OpenAI JSON</option>
                    </select>
                </div>

                <div id="api_key_row">
                <?php
                echo "<label for='api_badge_id'>API Key</label><br>";
                echo "<select id='api_badge_id' name='api_badge_id'>";
                echo "<option value=''>-- Select API Key --</option>";
                foreach ($withKey as $r){
                    $id = htmlspecialchars($r['id']);
                    $labRaw = ($r['label'] ?? ('Key #'.$r['id']));
                    $disp = '🟢 ' . $labRaw;
                    $lab = htmlspecialchars($disp);
                    $sel = (string)$selectedApi === (string)$r['id'] ? ' selected' : '';
                    echo "<option value='{$id}' data-empty='0'{$sel}>{$lab}</option>";
                }
                if (!empty($noKey)){
                    echo "<option value='' disabled>— Missing Key —</option>";
                    foreach ($noKey as $r){
                        $id = htmlspecialchars($r['id']);
                        $labRaw = ($r['label'] ?? ('Key #'.$r['id'])) . ' — No key';
                        $disp = '🔴 ' . $labRaw;
                        $lab = htmlspecialchars($disp);
                        $sel = (string)$selectedApi === (string)$r['id'] ? ' selected' : '';
                        echo "<option value='{$id}' data-empty='1'{$sel}>{$lab}</option>";
                    }
                }
                echo "</select>";
                echo "<div id='api_key_notice' class='api-key-notice'></div>";
                ?>
                </div>
                <?php
                // Note: The old "reasoning_model" checkbox has been deprecated and removed.
                // It has been replaced with toggle_thinking, thinking_tokens, and effort_level below.
                // Parse metadata to get reasoning-related fields
                $metadataArr = [];
                if (isset($editItem["metadata"]) && !empty($editItem["metadata"])) {
                    $tmpMeta = json_decode($editItem["metadata"], true);
                    if (is_array($tmpMeta)) $metadataArr = $tmpMeta;
                }
                $toggleThinking = isset($metadataArr["toggle_thinking"]) && ($metadataArr["toggle_thinking"] === true || $metadataArr["toggle_thinking"] === 'true' || $metadataArr["toggle_thinking"] === 1);
                $thinkingTokens = $metadataArr["thinking_tokens"] ?? '';
                $effortLevel = $metadataArr["effort_level"] ?? '';
                ?>
                <div id="reasoning_details" style="margin-top:8px; margin-left:20px; padding:8px; border-left:2px solid #444;">
                    <label class="label-with-toggle"><span class='tip-label' data-tip='Enable thinking/reasoning for supported models (like o1, DeepSeek-R1). Shows model internal reasoning process.'>Toggle Thinking</span>
                        <input type="hidden" name="metadata[toggle_thinking]" value="0">
                        <input type="checkbox" name="metadata[toggle_thinking]" id="toggle_thinking" value="1" <?= $toggleThinking ? "checked" : "" ?>>
                        <span class="toggle-text">On</span>
                    </label>
                    <div style="height:6px;"></div>
                    <label for='thinking_tokens'><span class='tip-label' data-tip='Maximum tokens for thinking/reasoning output (Anthropic/Gemini only). OpenAI uses effort_level instead. Leave empty to use default.'>Thinking Tokens</span></label>
                    <input type="number" name="metadata[thinking_tokens]" id="thinking_tokens" value="<?= htmlspecialchars($thinkingTokens) ?>" min="0" step="1" placeholder="Optional">
                    <div style="height:6px;"></div>
                    <label for='effort_level'><span class='tip-label' data-tip='Reasoning effort level for OpenAI reasoning models (o1, o3, o4, gpt-5). minimal=Quick (gpt-5+), low=Basic, medium=Balanced, high=Thorough. Leave empty for default.'>Effort Level</span></label>
                    <select name="metadata[effort_level]" id="effort_level">
                        <option value="">-- select --</option>
                        <option value="minimal" <?= $effortLevel === 'minimal' ? 'selected' : '' ?>>Minimal</option>
                        <option value="low" <?= $effortLevel === 'low' ? 'selected' : '' ?>>Low</option>
                        <option value="medium" <?= $effortLevel === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="high" <?= $effortLevel === 'high' ? 'selected' : '' ?>>High</option>
                    </select>
                </div>
                <div id="json_toggles" style="margin-top:8px;">
                    <label class="label-with-toggle"><span class='tip-label' data-tip='Force responses to be strict JSON. Non‑JSON output may be rejected or auto‑retried.'>Enforce JSON</span>
                        <input type="hidden" name="enforce_json" value="0">
                        <input type="checkbox" name="enforce_json" value="1" <?= isset($editItem["enforce_json"]) && $editItem["enforce_json"] == 1 ? "checked" : "" ?>>
                        <span class="toggle-text">On</span>
                    </label>
                    <div style="height:6px;"></div>
                    <label class="label-with-toggle"><span class='tip-label' data-tip='Guide/validate the JSON structure with a schema. Best used with Enforce JSON.'>JSON Schema</span>
                        <input type="hidden" name="json_schema" value="0">
                        <input type="checkbox" name="json_schema" value="1" <?= isset($editItem["json_schema"]) && $editItem["json_schema"] == 1 ? "checked" : "" ?>>
                        <span class="toggle-text">On</span>
                    </label>
                    <div style="height:6px;"></div>
                    <label class="label-with-toggle"><span class='tip-label' data-tip='Send a starter JSON object to steer field names/shape in the response.'>Prefill JSON</span>
                        <input type="hidden" name="prefill_json" value="0">
                        <input type="checkbox" name="prefill_json" value="1" <?= isset($editItem["prefill_json"]) && $editItem["prefill_json"] == 1 ? "checked" : "" ?>>
                        <span class="toggle-text">On</span>
                    </label>
                </div>

                <?php
                // Decode metadata for caching settings
                $metadata = [];
                if (isset($editItem['metadata'])) {
                    if (is_string($editItem['metadata'])) {
                        $metadata = json_decode($editItem['metadata'], true) ?: [];
                    } elseif (is_array($editItem['metadata'])) {
                        $metadata = $editItem['metadata'];
                    }
                }
                ?>

                <!-- Caching Settings (shown only for cached connectors) -->
                <div id="caching_settings" style="display:none; margin-top:16px; padding:12px; border:1px solid #4a4a4a; border-radius:8px; background:#1a1a1a;">
                    <div style="font-weight:600; color:#e9efff; margin-bottom:12px;">🔄 Caching Settings</div>
                    <div id="connector_version" style="font-size:0.85em; color:#999; margin-bottom:12px; font-style:italic;"></div>

                    <label for='provider_caching'>Provider Caching Type</label><br>
                    <select name="metadata[provider_caching]" id="provider_caching">
                        <option value="Anthropic" <?= ($metadata['provider_caching'] ?? 'Anthropic') === 'Anthropic' ? 'selected' : '' ?>>Anthropic</option>
                        <option value="OpenAI" <?= ($metadata['provider_caching'] ?? '') === 'OpenAI' ? 'selected' : '' ?>>OpenAI</option>
                        <option value="Gemini" <?= ($metadata['provider_caching'] ?? '') === 'Gemini' ? 'selected' : '' ?>>Gemini</option>
                    </select><br>

                    <label for='response_format'>Response Format</label><br>
                    <select name="metadata[response_format]" id="response_format">
                        <option value="json" <?= ($metadata['response_format'] ?? 'json') === 'json' ? 'selected' : '' ?>>JSON (structured)</option>
                        <option value="simple" <?= ($metadata['response_format'] ?? '') === 'simple' ? 'selected' : '' ?>>Simple (natural language)</option>
                    </select><br>

                    <label for='dialogue_cache_uncached_count'><span class='tip-label' data-tip='Number of most recent dialogue entries to keep uncached (0-10)'>Uncached Dialogue Count</span></label><br>
                    <input type='number' name='metadata[dialogue_cache_uncached_count]' id='dialogue_cache_uncached_count' value='<?= htmlspecialchars($metadata['dialogue_cache_uncached_count'] ?? '4') ?>' min='0' max='10' step='1'><br>

                    <div id="verbose_logging_option" style="display:none; margin-top:12px;">
                        <label class="label-with-toggle"><span class='tip-label' data-tip='Enable detailed logging for testing (verbose connector only)'>Verbose Logging</span>
                            <input type="hidden" name="metadata[verbose_logging]" value="0">
                            <input type="checkbox" name="metadata[verbose_logging]" value="1" <?= (!isset($metadata['verbose_logging']) || $metadata['verbose_logging']) ? 'checked' : '' ?>>
                            <span class="toggle-text">On</span>
                        </label>
                    </div>

                    <div style="margin-top:12px;">
                        <label class="label-with-toggle"><span class='tip-label' data-tip='Recommended ON for advanced models (Claude 4.5, GPT-4, Gemini 2.0). Uses minimal quality instructions. Turn OFF for older/smaller models that benefit from explicit guidance.'>Minimize Quality Instructions (Recommended)</span>
                            <input type="hidden" name="metadata[minimize_quality_prompt]" value="0">
                            <input type="checkbox" name="metadata[minimize_quality_prompt]" value="1" <?= (!isset($metadata['minimize_quality_prompt']) || $metadata['minimize_quality_prompt']) ? 'checked' : '' ?>>
                            <span class="toggle-text">On</span>
                        </label>
                    </div>

                    <!-- Response Format Section (Collapsible) -->
                    <div style="margin-top:16px; border:1px solid #4a4a4a; border-radius:8px; background:#252525;">
                        <div class="collapsible-header" data-target="response_format_section" style="padding:10px; cursor:pointer; user-select:none; font-weight:600; color:#e9efff; display:flex; justify-content:space-between; align-items:center;">
                            <span>📝 Response Format</span>
                            <span class="collapse-arrow">▼</span>
                        </div>
                        <div id="response_format_section" class="collapsible-content" style="padding:10px; display:none;">
                            <div style="margin-top:12px;">
                                <label class="label-with-toggle"><span class='tip-label' data-tip='Include action selection in response format. Required for NPCs to perform actions.'>Include Actions</span>
                                    <input type="hidden" name="metadata[include_actions_list]" value="0">
                                    <input type="checkbox" name="metadata[include_actions_list]" value="1" <?= (!isset($metadata['include_actions_list']) || $metadata['include_actions_list']) ? 'checked' : '' ?>>
                                </label><br>
                                <label class="label-with-toggle"><span class='tip-label' data-tip='Include mood/emotion in response. Used for NPC animations and expressions.'>Include Mood</span>
                                    <input type="hidden" name="metadata[include_mood_requirement]" value="0">
                                    <input type="checkbox" name="metadata[include_mood_requirement]" value="1" <?= (!isset($metadata['include_mood_requirement']) || $metadata['include_mood_requirement']) ? 'checked' : '' ?>>
                                </label><br>
                                <label class="label-with-toggle"><span class='tip-label' data-tip='Include action target (who/what the action is directed at).'>Include Target</span>
                                    <input type="hidden" name="metadata[include_target_requirement]" value="0">
                                    <input type="checkbox" name="metadata[include_target_requirement]" value="1" <?= (!isset($metadata['include_target_requirement']) || $metadata['include_target_requirement']) ? 'checked' : '' ?>>
                                </label><br>
                                <label class="label-with-toggle"><span class='tip-label' data-tip='Include listener field (who the NPC is talking to). Useful for multi-NPC conversations.'>Include Listener</span>
                                    <input type="hidden" name="metadata[include_listener_requirement]" value="0">
                                    <input type="checkbox" name="metadata[include_listener_requirement]" value="1" <?= (!isset($metadata['include_listener_requirement']) || $metadata['include_listener_requirement']) ? 'checked' : '' ?>>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Advanced Settings Section (Collapsible) -->
                    <div style="margin-top:16px; border:1px solid #4a4a4a; border-radius:8px; background:#252525;">
                        <div class="collapsible-header" data-target="advanced_settings_section" style="padding:10px; cursor:pointer; user-select:none; font-weight:600; color:#e9efff; display:flex; justify-content:space-between; align-items:center;">
                            <span>⚙️ Advanced Settings</span>
                            <span class="collapse-arrow">▼</span>
                        </div>
                        <div id="advanced_settings_section" class="collapsible-content" style="padding:10px; display:none;">
                            <label for='max_dialogue_cache_context_size'><span class='tip-label' data-tip='Maximum number of dialogue entries to cache in temp files. Higher = more context but larger cache files. Recommended: 93'>Max Dialogue Cache Context Size</span></label><br>
                            <input type='number' name='metadata[max_dialogue_cache_context_size]' id='max_dialogue_cache_context_size' value='<?= htmlspecialchars($metadata['max_dialogue_cache_context_size'] ?? '93') ?>' min='0' step='1'><br>

                            <label for='custom_system_instruction'><span class='tip-label' data-tip='Additional instruction added to the system prompt (after character bio, before dialogue history). Does NOT replace other instructions.'>Custom System Instruction</span></label><br>
                            <textarea name='metadata[custom_system_instruction]' id='custom_system_instruction' rows='3' style='width:100%; box-sizing:border-box;'><?= htmlspecialchars($metadata['custom_system_instruction'] ?? '') ?></textarea><br>

                            <label for='custom_last_instruction'><span class='tip-label' data-tip='Custom text inserted as second-to-last element in dialogue history (current user message is always last). Appears right before user current request.'>Custom Last Instruction</span></label><br>
                            <textarea name='metadata[custom_last_instruction]' id='custom_last_instruction' rows='3' style='width:100%; box-sizing:border-box;'><?= htmlspecialchars($metadata['custom_last_instruction'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div>
                <?php
                $tipMaxTokens = 'Maximum tokens the model can generate for a response. Higher = longer answers; may increase cost/latency. [>= 0]';
                echo "<label for='max_tokens' style='margin-top:10px; display:block;'><span class='tip-label' data-tip='" . htmlspecialchars($tipMaxTokens, ENT_QUOTES) . "'>Max Tokens</span></label>";
                echo "<input type='number' name='max_tokens' value='" . htmlspecialchars($editItem["max_tokens"] ?? "") . "' min='0' step='1'>";
                $ranges = [
                    'temperature' => ['min'=>0,'max'=>2,'step'=>0.0001],
                    'presence_penalty' => ['min'=>-2,'max'=>2,'step'=>0.0001],
                    'frequency_penalty' => ['min'=>-2,'max'=>2,'step'=>0.0001],
                    'repetition_penalty' => ['min'=>0,'max'=>2,'step'=>0.0001],
                    'top_p' => ['min'=>0,'max'=>1,'step'=>0.0001],
                    'top_k' => ['min'=>0,'max'=>100,'step'=>1],
                    'min_p' => ['min'=>0,'max'=>1,'step'=>0.0001],
                    'top_a' => ['min'=>0,'max'=>1,'step'=>0.0001],
                ];
                $displayDefaults = [
                    'temperature' => 1,
                    'presence_penalty' => 0,
                    'frequency_penalty' => 0,
                    'repetition_penalty' => 0,
                    'top_p' => 0,
                    'top_k' => 0,
                    'min_p' => 0,
                    'top_a' => 0,
                ];
                $tips = [
                    'temperature' => 'Controls randomness; higher = more creative, lower = more focused. [0-2]',
                    'presence_penalty' => 'Reduces repetition by discouraging repeated topics. [(-2)-2]',
                    'frequency_penalty' => 'Reduces repeated words or phrases. [0-2]',
                    'repetition_penalty' => 'Stops the model from repeating itself. [0-2]',
                    'top_p' => 'Chooses tokens with a combined probability up to p. [0-1]',
                    'top_k' => 'Picks from the top k most likely words. [0-100]',
                    'min_p' => 'Ignore words with very low probability. [0-1]',
                    'top_a' => 'Adjusts word probabilities for better balance. [0-1]'
                ];
                // Temperature only
                echo "<div class='kv-grid'>";
                $field = 'temperature';
                if (isset($ranges[$field])){
                    $conf = $ranges[$field];
                    $label = ucfirst(str_replace('_',' ', $field));
                    $rid = "rng_{$field}";
                    $nid = "num_{$field}";
                    $raw = $editItem[$field] ?? '';
                    $use = ($raw === '' || $raw === null) ? ($displayDefaults[$field] ?? '') : $raw;
                    $val = htmlspecialchars($use);
                    $min = $conf['min'];
                    $max = $conf['max'];
                    $step = $conf['step'];
                    $tip = isset($tips[$field]) ? $tips[$field] : '';
                    $labelHtml = $tip ? ("<span class='tip-label' data-tip='" . htmlspecialchars($tip, ENT_QUOTES) . "'>" . $label . "</span>") : $label;
                    echo "<div><label for='{$rid}'>{$labelHtml}</label></div>";
                    echo "<div>";
                    echo "<input type='range' id='{$rid}' min='{$min}' max='{$max}' step='{$step}' value='{$val}' oninput=\"document.getElementById('{$nid}').value=this.value\">";
                    echo "<div style='margin-top:6px;'><input type='number' class='inline-num' id='{$nid}' name='{$field}' min='{$min}' max='{$max}' step='{$step}' value='{$val}' oninput=\"llmClamp('{$rid}','{$nid}',{$min},{$max})\" data-null='" . (($raw === '' || $raw === null) ? '1' : '0') . "'></div>";
                    echo "</div>";
                }
                echo "</div>";

                // Advanced block
                echo "<div style=\"border:1px solid #4a4a4a; border-radius:10px; padding:10px; margin-top:12px;\">";
                echo "<div style=\"font-weight:600; color:#e9efff; margin-bottom:8px;\">Advanced LLM Settings Override</div>";
                echo "<div class='kv-grid'>";
                $advancedFields = ['presence_penalty','frequency_penalty','repetition_penalty','top_p','top_k','min_p','top_a'];
                foreach ($advancedFields as $advField) {
                    if (!isset($ranges[$advField])) continue;
                    $conf = $ranges[$advField];
                    $label = ucfirst(str_replace('_',' ', $advField));
                    $rid = "rng_{$advField}";
                    $nid = "num_{$advField}";
                    $raw = $editItem[$advField] ?? '';
                    $use = ($raw === '' || $raw === null) ? '' : $raw;
                    $val = htmlspecialchars($use);
                    $min = $conf['min'];
                    $max = $conf['max'];
                    $step = $conf['step'];
                    $tip = isset($tips[$advField]) ? $tips[$advField] : '';
                    $labelHtml = $tip ? ("<span class='tip-label' data-tip='" . htmlspecialchars($tip, ENT_QUOTES) . "'>" . $label . "</span>") : $label;
                    echo "<div><label for='{$rid}'>{$labelHtml}</label></div>";
                    echo "<div>";
                    echo "<input type='range' id='{$rid}' min='{$min}' max='{$max}' step='{$step}' value='{$val}' oninput=\"document.getElementById('{$nid}').value=this.value; try{document.getElementById('{$nid}_null').value='0';}catch(e){}\">";
                    echo "<div style='margin-top:6px;'>";
                    echo "<input type='number' class='inline-num' id='{$nid}' name='{$advField}' min='{$min}' max='{$max}' step='{$step}' value='{$val}' data-null='" . (($raw === '' || $raw === null) ? '1' : '0') . "'>";
                    echo "<input type='hidden' id='{$nid}_null' name='{$advField}_is_null' value='" . (($raw === '' || $raw === null) ? '1' : '0') . "'>";
                    echo "</div>";
                    echo "</div>";
                }
                echo "</div>";
                echo "<div style='margin-top:10px; display:flex; gap:8px; align-items:center;'>";
                // Seems not working on profiles tab, so not print
                //echo "<button type='button' id='btn_clear_adv' class='btn-danger'>Clear advanced settings</button>";
                echo "</div>";
                echo "</div>";
                ?>
            </div>
        </div>
    </form>
    <script>
    (function(){
        // Service selection logic for embedded editor
        const defaults = {
            openrouter: 'https://openrouter.ai/api/v1/chat/completions',
            openai: 'https://api.openai.com/v1/chat/completions',
            google: 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
            nanogpt: 'https://nano-gpt.com/api/v1/chat/completions'
        };
        const serviceInput = document.getElementById('service_input');
        const urlInput = document.querySelector('input[name="url"]');
        const providerRow = document.getElementById('provider_row');
        const driverRow = document.getElementById('driver_row');
        const driverInput = document.getElementById('driver_input');
        const driverSelect = document.getElementById('driver_select');
        const urlRow = document.getElementById('url_row');
        const icons = document.querySelectorAll('.service-icon');
        const serviceLabelEl = document.getElementById('service_label');
        const displayNames = { openrouter: 'OpenRouter', openai: 'OpenAI', google: 'Google', nanogpt: 'Nano-GPT', custom: 'Custom' };

        function setActive(service){ icons.forEach(ic=>{ if (ic.dataset.service === service) ic.classList.add('active'); else ic.classList.remove('active'); }); }

        function applyService(service, fromUser){
            try {
                if (serviceInput) serviceInput.value = service;
                // Defaults for non-custom
                if (service !== 'custom' && defaults[service]) {
                    const currentUrl = urlInput ? String(urlInput.value||'') : '';
                    if (fromUser || currentUrl === '') {
                        if (urlInput) urlInput.value = defaults[service];
                    }
                }
                if (providerRow) providerRow.style.display = (service === 'openrouter') ? '' : 'none';
                if (driverRow) driverRow.style.display = (service === 'custom') ? '' : 'none';
                if (driverSelect) driverSelect.style.display = (service === 'custom') ? '' : 'none';
                if (driverInput) driverInput.style.display = (service === 'custom') ? 'none' : '';
                // Preserve saved driver; only set defaults when empty
                if (service === 'custom') {
                    const savedDriver = driverInput ? String(driverInput.value || '') : '';
                    if (driverSelect) {
                        if (savedDriver) {
                            driverSelect.value = savedDriver;
                        } else if (!driverSelect.value) {
                            driverSelect.value = 'openaijson';
                        }
                    }
                    if (driverInput && !savedDriver) {
                        driverInput.value = driverSelect ? driverSelect.value : 'openaijson';
                    }
                } else {
                    if (driverInput && !driverInput.value) {
                        driverInput.value = (service === 'openrouter') ? 'openrouterjson' : (service === 'openai' ? 'openaijson' : (service === 'google' ? 'google_openaijson' : (service === 'nanogpt' ? 'openrouterjson' : driverInput.value)));
                    }
                }
                if (urlRow) urlRow.style.display = (service === 'custom') ? '' : 'none';
                if (serviceLabelEl) serviceLabelEl.textContent = 'Service: ' + (displayNames[service] || '');
                setActive(service);
            } catch (e) {
                console.log(e);

            }
        }

        function detectService(){
            const u = (urlInput && String(urlInput.value||'').toLowerCase()) || '';
            if (u){
                if (u.includes('openai.com')) return 'openai';
                if (u.includes('generativelanguage.googleapis.com')) return 'google';
                if (u.includes('openrouter.ai')) return 'openrouter';
                if (u.includes('nano-gpt.com')) return 'nanogpt';
                return 'custom';
            }
            const sValRaw = (serviceInput && String(serviceInput.value||'')) || '';
            const sVal = sValRaw.toLowerCase();
            if (['openrouter','openai','google','nanogpt','custom'].includes(sVal)) return sVal;
            const d = (driverInput && String(driverInput.value||'').toLowerCase()) || '';
            if (d.includes('openai')) return 'openai';
            if (d.includes('google')) return 'google';
            if (d.includes('openrouter')) return 'openrouter';
            if (d.includes('nanogpt')) return 'nanogpt';
            return 'openrouter';
        }

        (function init(){ const svc = detectService(); applyService(svc, false);
            // Expose WSL/PC IP helpers if values present
            try {
                const btnWSL = document.getElementById('btn_wsl_ip');
                const btnHost = document.getElementById('btn_host_ip');
                function fillFrom(buttonEl, ip){ if (!buttonEl) return; const form = buttonEl.closest('form'); const urlEl = form ? form.querySelector('input[name="url"]') : document.querySelector('input[name="url"]'); if (!ip || !urlEl) return; urlEl.value = 'http://' + ip + ':5001'; try { urlEl.dispatchEvent(new Event('input', { bubbles:true })); } catch(_){} try { urlEl.dispatchEvent(new Event('change', { bubbles:true })); } catch(_){} try { urlEl.focus(); } catch(_){} }
                if (btnWSL){ const ip=(btnWSL.getAttribute('data-ip')||'').trim(); btnWSL.style.display = ip? '' : 'none'; btnWSL.addEventListener('click', function(){ let v = this.getAttribute('data-ip')||''; if (!v) return; fillFrom(this, String(v).trim()); }); }
                if (btnHost){ const ip=(btnHost.getAttribute('data-ip')||'').trim(); btnHost.style.display = ip? '' : 'none'; btnHost.addEventListener('click', function(){ let v = this.getAttribute('data-ip')||''; if (!v) return; fillFrom(this, String(v).trim()); }); }
            } catch(_e){}
        })();
        icons.forEach(ic=>{ ic.addEventListener('click', ()=> applyService(ic.dataset.service, true)); });
        if (driverSelect){ driverSelect.addEventListener('change', function(){ if (driverInput) driverInput.value = this.value; updateCachingSettings(); }); }

        // Caching settings visibility logic
        function updateCachingSettings(){
            const driver = driverInput ? driverInput.value : (driverSelect ? driverSelect.value : '');
            const cachingSettings = document.getElementById('caching_settings');
            const simpleFormatOptions = document.getElementById('simple_format_options');
            const verboseLoggingOption = document.getElementById('verbose_logging_option');
            const responseFormatSelect = document.getElementById('response_format');

            // Show caching settings only for cached drivers
            const isCachedDriver = driver === 'openrouterjsoncached' || driver === 'openrouterjsoncached_verbose';
            if (cachingSettings) cachingSettings.style.display = isCachedDriver ? '' : 'none';

            // Update connector version display
            const versionDiv = document.getElementById('connector_version');
            if (versionDiv) {
                if (driver === 'openrouterjsoncached') {
                    versionDiv.textContent = <?= json_encode($cachedConnectorVersion) ?>;
                } else if (driver === 'openrouterjsoncached_verbose') {
                    versionDiv.textContent = <?= json_encode($cachedConnectorVersionVerbose) ?>;
                } else {
                    versionDiv.textContent = '';
                }
            }

            // Show verbose logging option only for verbose driver
            if (verboseLoggingOption) verboseLoggingOption.style.display = (driver === 'openrouterjsoncached_verbose') ? '' : 'none';

            // Show simple format options based on response_format selection
            if (responseFormatSelect && simpleFormatOptions) {
                simpleFormatOptions.style.display = (responseFormatSelect.value === 'simple') ? '' : 'none';
            }
        }

        // Listen for response format changes
        const responseFormatSelect = document.getElementById('response_format');
        if (responseFormatSelect) {
            responseFormatSelect.addEventListener('change', updateCachingSettings);
        }

        // Initial call to set correct visibility
        updateCachingSettings();
    })();

    // Collapsible section handlers with localStorage persistence
    (function(){
        const collapsibleHeaders = document.querySelectorAll('.collapsible-header');

        collapsibleHeaders.forEach(header => {
            const targetId = header.getAttribute('data-target');
            const content = document.getElementById(targetId);
            const arrow = header.querySelector('.collapse-arrow');

            if (!content || !arrow) return;

            // Restore state from localStorage
            const storageKey = 'llm_collapse_' + targetId;
            const isCollapsed = localStorage.getItem(storageKey) === 'true';

            if (isCollapsed) {
                content.style.display = 'none';
                arrow.textContent = '▶';
            } else {
                content.style.display = 'block';
                arrow.textContent = '▼';
            }

            // Add click handler
            header.addEventListener('click', function() {
                const isCurrentlyVisible = content.style.display !== 'none';

                if (isCurrentlyVisible) {
                    content.style.display = 'none';
                    arrow.textContent = '▶';
                    localStorage.setItem(storageKey, 'true');
                } else {
                    content.style.display = 'block';
                    arrow.textContent = '▼';
                    localStorage.setItem(storageKey, 'false');
                }
            });
        });
    })();
    </script>
    <div id="toast" class="toast-notification" style="position:static; margin: 8px auto 12px; display:block; opacity:0; transform:none; max-width:960px; width: calc(100% - 20px);"><span class="message"></span></div>
    <script>
    // Toast helper for embedded editor
    if (typeof window.showToast !== 'function') {
        window.showToast = function(message, isError){
            var toast = document.getElementById('toast');
            if (!toast) return;
            var msg = toast.querySelector('.message');
            if (msg) msg.textContent = String(message||'');
            toast.className = 'toast-notification show' + (isError? ' error' : '');
            setTimeout(function(){ toast.className = 'toast-notification'; }, 2500);
        };
    }
    // Sync On/Off labels for checkboxes
    (function(){
        const names = ['enforce_json','json_schema','prefill_json'];
        names.forEach(n=>{
            const cb = document.querySelector(`input[type="checkbox"][name="${n}"]`);
            if (!cb) return;
            const label = cb.closest('label');
            const span = label ? label.querySelector('.toggle-text') : null;
            function sync(){ if (span) span.textContent = cb.checked ? 'On' : 'Off'; }
            sync();
            cb.addEventListener('change', sync);
        });
    })();
    // Service selection and defaults
    (function(){
        const defaults = {
            openrouter: 'https://openrouter.ai/api/v1/chat/completions',
            openai: 'https://api.openai.com/v1/chat/completions',
            google: 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
            nanogpt: 'https://nano-gpt.com/api/v1/chat/completions'
        };
        // No dropdown; selection by icons only
        const providerRow = document.getElementById('provider_row');
        const urlInput = document.querySelector('input[name="url"]');
        const driverInput = document.querySelector('input[name="driver"]');
        const driverRow = document.getElementById('driver_row');
        const apiBadgeSelect = document.getElementById('api_badge_id');
        const icons = document.querySelectorAll('.service-icon');
        const apiKeyRow = document.getElementById('api_key_row');
        const serviceLabelEl = document.getElementById('service_label');
        const displayNames = { openrouter: 'OpenRouter', openai: 'OpenAI', google: 'Google', nanogpt: 'Nano-GPT' };
        function setActive(service){ icons.forEach(ic=>{ if (ic.dataset.service === service) ic.classList.add('active'); else ic.classList.remove('active'); }); }
        const driverDefaults = { openrouter: 'openrouterjson', openai: 'openaijson', google: 'google_openaijson', nanogpt: 'openrouterjson', custom: '' };
        const apiBadgeLabelMatch = { openrouter: ['openrouter'], openai: ['openai'], google: ['google'], nanogpt: ['nano-gpt','nanogpt'] };
        function syncApiBadge(service){ if (!apiBadgeSelect) return; const targets = (apiBadgeLabelMatch[service] || []).map(s => s.toLowerCase()); if (targets.length === 0) return; let selectedVal = ''; for (let i = 0; i < apiBadgeSelect.options.length; i++) { const opt = apiBadgeSelect.options[i]; const label = (opt.textContent || opt.innerText || '').toLowerCase(); if (targets.some(t => label.includes(t))) { selectedVal = opt.value; break; } } if (selectedVal !== '') apiBadgeSelect.value = selectedVal; else apiBadgeSelect.value = ''; }
        function applyService(service){ try {const serviceInput = document.getElementById('service_input'); if (serviceInput) serviceInput.value = service; if (defaults[service]) { const currentUrl = String((urlInput && urlInput.value) || ''); if (currentUrl === '' || currentUrl === 'about:blank') { urlInput.value = defaults[service]; } } providerRow.style.display = (service === 'openrouter') ? '' : 'none'; const savedDriver = driverInput ? String(driverInput.value || '') : ''; if (service === 'custom') { if (driverRow) driverRow.style.display = ''; if (driverSelect) driverSelect.style.display = ''; if (driverInput) driverInput.style.display = 'none'; if (driverSelect) { if (savedDriver) { driverSelect.value = savedDriver; } else if (!driverSelect.value) { driverSelect.value = 'openaijson'; } } if (driverInput && !savedDriver) { driverInput.value = driverSelect ? driverSelect.value : 'openaijson'; } } else { if (driverRow) driverRow.style.display = 'none'; if (driverSelect) driverSelect.style.display = 'none'; if (driverInput) driverInput.style.display = ''; if (driverInput && !savedDriver && driverDefaults[service]) { driverInput.value = driverDefaults[service]; } } syncApiBadge(service); setActive(service); if (apiKeyRow) apiKeyRow.style.display = ''; if (serviceLabelEl) serviceLabelEl.textContent = 'Service: ' + (displayNames[service] || ''); document.querySelectorAll('.orm-dropdown').forEach(function(el){ el.style.display='none'; }); } catch (e) {console.log(e);console.log("Check this bug")}}
        function detectService(){ const u=(urlInput&&String(urlInput.value||'').toLowerCase())||''; if (u){ if (u.includes('openai.com')) return 'openai'; if (u.includes('generativelanguage.googleapis.com')) return 'google'; if (u.includes('openrouter.ai')) return 'openrouter'; if (u.includes('nano-gpt.com')) return 'nanogpt'; return 'custom'; } const sVal=(document.getElementById('service_input')&&String(document.getElementById('service_input').value||'').toLowerCase())||''; if (['openrouter','openai','google','nanogpt','custom'].includes(sVal)) return sVal; const d=(driverInput&&String(driverInput.value||'').toLowerCase())||''; if (d.includes('openai')) return 'openai'; if (d.includes('google')) return 'google'; if (d.includes('openrouter')) return 'openrouter'; if (d.includes('nanogpt')) return 'nanogpt'; return 'openrouter'; }
        (function init(){ const service = detectService(); applyService(service); })();
        icons.forEach(ic=>{ ic.addEventListener('click', ()=> applyService(ic.dataset.service)); });
        if (driverInput){ driverInput.addEventListener('input', ()=> applyService(detectService())); driverInput.addEventListener('change', ()=> applyService(detectService())); }
        if (urlInput){ urlInput.addEventListener('change', ()=> { const sEl=document.getElementById('service_input'); const sVal = sEl ? String(sEl.value||'').toLowerCase() : ''; if (sVal==='custom') return; applyService(detectService()); }); }
    })();
    function llmClamp(rangeId, numberId, min, max){ const r = document.getElementById(rangeId); const n = document.getElementById(numberId); if (!r || !n) return; let v = parseFloat(n.value); if (isNaN(v)) v = min; if (v < min) v = min; if (v > max) v = max; n.value = v; r.value = v; }
    // LLM Test Modal
    (function(){
        const MODAL_ID = 'llmtest_modal';
        const modal = document.createElement('div');
        modal.id = MODAL_ID;
        modal.style.cssText = 'position:fixed; inset:0; display:none; align-items:center; justify-content:center; background:rgba(0,0,0,0.65); z-index:10000;';
        modal.innerHTML = `
            <div style="width:90%; max-width:1200px; height:80vh; background:#2a2a2a; border:1px solid #4a4a4a; border-radius:10px; box-shadow:0 10px 30px rgba(0,0,0,0.6); position:relative; overflow:hidden;">
                <button id=\"llmtest_close\" style=\"position:absolute; top:8px; right:10px; background:#3a3a3a; color:#fff; border:1px solid #4a4a4a; border-radius:6px; padding:4px 10px; cursor:pointer; z-index:3;\">Close</button>
                <div id=\"llmtest_loading\" style=\"position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.4); z-index:2;\">
                    <div style=\"width:48px; height:48px; border:4px solid rgba(255,255,255,0.25); border-top-color:#ffb862; border-radius:50%; animation: llmspin 1s linear infinite;\"></div>
                </div>
                <iframe id=\"llmtest_iframe\" src=\"about:blank\" style=\"width:100%; height:100%; border:0; background:transparent; position:relative; z-index:1;\"></iframe>
            </div>
            <style>@keyframes llmspin{to{transform:rotate(360deg)}}</style>`;
        document.body.appendChild(modal);
        function openModal(url){ const iframe = document.getElementById('llmtest_iframe'); const loader = document.getElementById('llmtest_loading'); if (loader) loader.style.display = 'flex'; iframe.onload = function(){ if (loader) loader.style.display = 'none'; }; iframe.src = url; modal.style.display = 'flex'; }
        function closeModal(){ modal.style.display = 'none'; try { document.getElementById('llmtest_iframe').src='about:blank'; } catch(_){} }
        document.addEventListener('click', function(e){ if (e.target && e.target.id==='llmtest_close') closeModal(); });
        modal.addEventListener('click', function(e){ if (e.target===modal) closeModal(); });
        document.addEventListener('keydown', function(e){ if (e.key==='Escape') closeModal(); });
        const testBtn = document.getElementById('btn_test_connector');
        if (testBtn){
            testBtn.addEventListener('click', async function(){
                const form = document.querySelector('form[method="post"]');
                if (!form) return;
                try {
                    const fd = new FormData(form);
                    if (!fd.has('update') && !fd.has('create')) fd.append('update','1');
                    const idInputEarly = form.querySelector('input[name="id"]');
                    const cidEarly = idInputEarly ? idInputEarly.value : '';
                    openModal('about:blank');
                    await fetch('llm_connectors.php', { method:'POST', body: fd });
                } catch (e) {}
                const idInput = form.querySelector('input[name="id"]');
                const cid = idInput ? idInput.value : '';
                openModal('<?= $webRoot; ?>/ui/core/tests/llmtest.php' + (cid ? ('?connector_id='+encodeURIComponent(cid)) : ''));
            });
        }
    })();
    // API key notice
    (function(){
        const sel = document.getElementById('api_badge_id');
        const note = document.getElementById('api_key_notice');
        if (!sel || !note) return;
        function update(){
            const opt = sel.options[sel.selectedIndex];
            const empty = opt ? opt.getAttribute('data-empty') === '1' : true;
            if (!opt || sel.value === ''){ note.className = 'api-key-notice warn'; note.textContent = 'No API key selected. Some services require a key.'; return; }
            if (empty){ note.className = 'api-key-notice warn'; } else { note.className = 'api-key-notice ok'; }
        }
        sel.addEventListener('change', update);
        update();
    })();
    // OpenRouter model dropdown
    (function(){
        const modelInput = document.querySelector('input[name="model"]');
        if (!modelInput) return;
        let cache = null, dropdown = null, isOpen = false;
        function ensureDropdown(){ if (dropdown) return dropdown; dropdown = document.createElement('div'); dropdown.className = 'orm-dropdown'; document.body.appendChild(dropdown); dropdown.addEventListener('mousedown', (e)=>{ e.preventDefault(); }); return dropdown; }
        function positionDropdown(){ const rect = modelInput.getBoundingClientRect(); const style = dropdown.style; style.left = (rect.left + window.scrollX) + 'px'; style.top = (rect.bottom + window.scrollY + 4) + 'px'; style.minWidth = Math.max(rect.width, 420) + 'px'; style.display = 'block'; isOpen = true; }
        function closeDropdown(){ if (!dropdown) return; dropdown.style.display = 'none'; isOpen = false; }
        function formatPrice(n){ if (n === undefined || n === null || n === '' || isNaN(parseFloat(n))) return 'N/A'; const perTok = parseFloat(n); const perM = perTok * 1000000.0; return '$' + perM.toFixed(4) + ' / 1M tokens'; }
        function escapeHtml(s){ return (s==null? '': String(s)).replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])); }
        function encodeHtmlAttr(s){ return (s==null? '': String(s)).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
        function formatContext(val){ const num = Number(val); return isFinite(num) ? num.toLocaleString('en-US') : (val||''); }
        function renderList(models, filterText){
            ensureDropdown();
            const q = (filterText || '').toLowerCase();
            const list = (models || []).filter(m => { if (!q) return true; const id = (m.id || '').toLowerCase(); const name = (m.name || '').toLowerCase(); return id.includes(q) || name.includes(q); });
            let html = '';
            html += '<div class="orm-head">OpenRouter Models</div>';
            html += '<div class="orm-note">Click to select. Pricing shown per 1M tokens.</div>';
            if (list.length === 0){ html += '<div class="orm-muted" style="padding:8px 10px;">No matches</div>'; }
            else { list.forEach(m => { const prompt = formatPrice(m.pricing && m.pricing.prompt); const completion = formatPrice(m.pricing && m.pricing.completion); const ctxRaw = (m.top_provider && m.top_provider.context_length) || m.context_length || ''; const ctx = formatContext(ctxRaw); const name = m.name ? ' — ' + escapeHtml(m.name) : ''; const line = `${escapeHtml(m.id)}${name}`; const sub = `Pricing (per 1M tokens): input ${prompt} • output ${completion}` + (ctx? ` • context ${ctx}` : ''); html += `<div class=\"orm-item\" data-id=\"${encodeHtmlAttr(m.id)}\" title=\"${encodeHtmlAttr(m.description||m.name||m.id)}\"><div>${line}</div><div class=\"orm-muted\" style=\"font-size:12px; margin-top:2px;\">${sub}</div></div>`; }); }
            dropdown.innerHTML = html;
            dropdown.querySelectorAll('.orm-item').forEach(el => { el.addEventListener('click', () => { const id = el.getAttribute('data-id') || ''; modelInput.value = id; try { modelInput.dispatchEvent(new Event('input', { bubbles: true })); } catch (_) {} try { modelInput.dispatchEvent(new Event('change', { bubbles: true })); } catch (_) {} closeDropdown(); }); });
            positionDropdown();
        }
        async function loadModels(){ if (cache) return cache; ensureDropdown(); dropdown.innerHTML = '<div class="orm-head">OpenRouter Models</div><div class="orm-note">Loading…</div>'; positionDropdown(); try { const res = await fetch('https://openrouter.ai/api/v1/models'); if (!res.ok) throw new Error('HTTP '+res.status); const json = await res.json(); const data = Array.isArray(json && json.data) ? json.data : []; cache = data.map(m => ({ id: m.id || m.canonical_slug || '', name: m.name || '', pricing: m.pricing || {}, top_provider: m.top_provider || {}, context_length: m.context_length || undefined, description: m.description || '' })); cache.sort((a,b)=> (a.name||'').localeCompare(b.name||'') || (a.id||'').localeCompare(b.id||'')); return cache; } catch (e) { dropdown.innerHTML = '<div class="orm-head">OpenRouter Models</div><div class="orm-err">Failed to load models. Check network/CORS.</div>'; positionDropdown(); throw e; } }
        function isOpenRouter(){ const svc = ((document.getElementById('service_input')||{}).value||'').toLowerCase(); if (svc !== 'openrouter') return false; const url = (document.querySelector('input[name="url"]').value||''); const driver = (document.querySelector('input[name="driver"]').value||''); return url.includes('openrouter.ai') || /openrouter/.test(driver); }
        async function maybeOpenDropdown(){ if (!isOpenRouter()) return; try { const models = await loadModels(); renderList(models, modelInput.value); } catch (_e) {} }
        modelInput.addEventListener('focus', () => { if (isOpenRouter()) maybeOpenDropdown(); });
        modelInput.addEventListener('click', () => { if (isOpenRouter()) maybeOpenDropdown(); });
        modelInput.addEventListener('input', () => { if (isOpen && cache) renderList(cache, modelInput.value); });
        modelInput.addEventListener('blur', () => { setTimeout(closeDropdown, 120); });
        window.addEventListener('resize', () => { if (isOpen) positionDropdown(); });
        window.addEventListener('scroll', () => { if (isOpen) positionDropdown(); }, true);
        document.addEventListener('keydown', (e)=>{ if (e.key==='Escape') closeDropdown(); });
        document.querySelector('input[name="url"]').addEventListener('change', () => closeDropdown());
        document.querySelector('input[name="driver"]').addEventListener('change', () => closeDropdown());

        // Info box under Model input (embedded editor)
        (function(){
            const infoId = 'orm_model_info';
            let infoEl = document.getElementById(infoId);
            if (!infoEl){
                infoEl = document.createElement('div');
                infoEl.id = infoId;
                infoEl.className = 'orm-info-box';
                infoEl.style.display = 'none';
                const anchor = modelInput;
                if (anchor && anchor.parentNode) anchor.parentNode.insertBefore(infoEl, anchor.nextSibling);
            }
            function ctxOf(m){ return (m.top_provider && m.top_provider.context_length) || m.context_length || ''; }
            function renderInfo(m){ const prompt = formatPrice(m.pricing && m.pricing.prompt); const completion = formatPrice(m.pricing && m.pricing.completion); const ctx = formatContext(ctxOf(m)); return `<div style=\"font-weight:600; margin-bottom:4px;\">OpenRouter model info</div><div class=\"orm-muted\" style=\"font-size:12px;\">Pricing (per 1M tokens): Input ${prompt} • Output ${completion}${ctx? ` • Context ${ctx}`: ''}</div>`; }
            async function update(){
                const val = (modelInput.value||'').trim();
                const url = (document.querySelector('input[name=\"url\"]').value||'');
                const driver = (document.querySelector('input[name=\"driver\"]').value||'');
                const svc = ((document.getElementById('service_input')||{}).value||'').toLowerCase();
                const isOR = (svc === 'openrouter') && (url.includes('openrouter.ai') || /openrouter/.test(driver));
                if (!val || !isOR){ infoEl.style.display='none'; infoEl.innerHTML=''; return; }
                try {
                    if (!cache){ await loadModels(); }
                    const m = (cache||[]).find(x => String(x.id||'') === val);
                    if (m){ infoEl.innerHTML = renderInfo(m); infoEl.style.display='block'; }
                    else { infoEl.innerHTML = `<div class=\"orm-muted\" style=\"font-size:12px;\">No model info available</div>`; infoEl.style.display='block'; }
                } catch(_e){ infoEl.style.display='none'; }
            }
            modelInput.addEventListener('change', update);
            modelInput.addEventListener('input', update);
            // removed on-load trigger
            document.querySelector('input[name="url"]').addEventListener('change', update);
            document.querySelector('input[name="driver"]').addEventListener('change', update);
        })();
    })();
    // Providers dropdown
    (function(){
        const providerInput = document.querySelector('input[name="provider"]');
        const modelInput = document.querySelector('input[name="model"]');
        if (!providerInput || !modelInput) return;
        let providersCache = null, dropdown = null, isOpen = false;
        function ensureDropdown(){ if (dropdown) return dropdown; dropdown = document.createElement('div'); dropdown.className = 'orm-dropdown'; document.body.appendChild(dropdown); dropdown.addEventListener('mousedown', (e)=>{ e.preventDefault(); }); return dropdown; }
        function positionDropdown(){ const rect = providerInput.getBoundingClientRect(); const style = dropdown.style; style.left = (rect.left + window.scrollX) + 'px'; style.top = (rect.bottom + window.scrollY + 4) + 'px'; style.minWidth = Math.max(rect.width, 420) + 'px'; style.display = 'block'; isOpen = true; }
        function closeDropdown(){ if (!dropdown) return; dropdown.style.display = 'none'; isOpen = false; }
        function escapeHtml(s){ return (s==null? '': String(s)).replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])); }
        function encodeHtmlAttr(s){ return (s==null? '': String(s)).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
        function renderList(items, filterText, relevantSlugs){ ensureDropdown(); const q = (filterText || '').toLowerCase(); const slugAllow = Array.isArray(relevantSlugs) ? new Set(relevantSlugs.filter(Boolean)) : null; const filteredByRelevance = (items || []).filter(p => { if (slugAllow && slugAllow.size>0) return slugAllow.has((p.slug||'')); return true; }); const list = filteredByRelevance.filter(p => { if (!q) return true; const slug = (p.slug || '').toLowerCase(); const name = (p.name || '').toLowerCase(); return slug.includes(q) || name.includes(q); }); let html = ''; html += '<div class="orm-head">OpenRouter Providers</div>'; html += '<div class="orm-note">Click to select. Value set to provider slug.</div>'; if (list.length === 0){ const hasRelevantFilter = (slugAllow && slugAllow.size>0); const note = hasRelevantFilter ? 'No relevant providers for the selected model.' : 'No matches'; html += `<div class="orm-muted" style="padding:8px 10px;">${note}</div>`; } else { list.forEach(p => { const name = p.name ? ` — ${escapeHtml(p.name)}` : ''; html += `<div class=\"orm-item\" data-slug=\"${encodeHtmlAttr(p.slug)}\" title=\"${encodeHtmlAttr(p.name||p.slug)}\"><div>${escapeHtml(p.slug)}${name}</div><div class=\"orm-muted\" style=\"font-size:12px; margin-top:2px;\">${p.privacy_policy_url? 'Privacy: '+escapeHtml(p.privacy_policy_url): ''}${p.terms_of_service_url? (p.privacy_policy_url? ' • ': '')+'TOS: '+escapeHtml(p.terms_of_service_url): ''}</div></div>`; }); }
            dropdown.innerHTML = html;
            dropdown.querySelectorAll('.orm-item').forEach(el => { el.addEventListener('click', () => { const slug = el.getAttribute('data-slug') || ''; providerInput.value = slug; try { providerInput.dispatchEvent(new Event('input', { bubbles: true })); } catch (_) {} try { providerInput.dispatchEvent(new Event('change', { bubbles: true })); } catch (_) {} closeDropdown(); }); });
            positionDropdown(); }
        async function loadProviders(){ if (providersCache) return providersCache; ensureDropdown(); dropdown.innerHTML = '<div class="orm-head">OpenRouter Providers</div><div class="orm-note">Loading…</div>'; positionDropdown(); try { const res = await fetch('https://openrouter.ai/api/v1/providers'); if (!res.ok) throw new Error('HTTP '+res.status); const json = await res.json(); const data = Array.isArray(json && json.data) ? json.data : []; providersCache = data.map(p => ({ name: p.name || '', slug: p.slug || '', privacy_policy_url: p.privacy_policy_url || '', terms_of_service_url: p.terms_of_service_url || '', status_page_url: p.status_page_url || '' })).filter(p => p.slug); providersCache.sort((a,b)=> (a.slug||'').localeCompare(b.slug||'')); return providersCache; } catch (e) { dropdown.innerHTML = '<div class="orm-head">OpenRouter Providers</div><div class="orm-err">Failed to load providers. Check network/CORS.</div>'; positionDropdown(); throw e; } }
        function getRelevantProviderSlugs(){ const val = (modelInput.value || '').trim(); const ix = val.indexOf('/'); if (ix > 0){ const slug = val.slice(0, ix).trim(); if (slug) return [slug]; } return []; }
        function isOpenRouter(){ const svc = ((document.getElementById('service_input')||{}).value||'').toLowerCase(); if (svc !== 'openrouter') return false; const url = (document.querySelector('input[name="url"]').value||''); const driver = (document.querySelector('input[name="driver"]').value||''); return url.includes('openrouter.ai') || /openrouter/.test(driver); }
        async function maybeOpenDropdown(){ if (!isOpenRouter()) return; try { const items = await loadProviders(); renderList(items, providerInput.value, getRelevantProviderSlugs()); } catch (_e) {} }
        function extractProviderSlugFromModel(val){ if (!val) return ''; const s = String(val); const ix = s.indexOf('/'); if (ix <= 0) return ''; return s.slice(0, ix).trim(); }
        function maybeAutofillProvider(){ const url = (document.querySelector('input[name="url"]').value||''); const driver = (document.querySelector('input[name="driver"]').value||''); if (!(url.includes('openrouter.ai') || /openrouter/.test(driver))) return; const slug = extractProviderSlugFromModel(modelInput.value); if (!slug) return; providerInput.value = slug; try { providerInput.dispatchEvent(new Event('input', { bubbles: true })); } catch (_) {} try { providerInput.dispatchEvent(new Event('change', { bubbles: true })); } catch (_) {} }
        providerInput.addEventListener('focus', () => { if (isOpenRouter()) maybeOpenDropdown(); });
        providerInput.addEventListener('click', () => { if (isOpenRouter()) maybeOpenDropdown(); });
        providerInput.addEventListener('input', () => { if (isOpen && providersCache) renderList(providersCache, providerInput.value, getRelevantProviderSlugs()); });
        providerInput.addEventListener('blur', () => { setTimeout(closeDropdown, 120); });
        window.addEventListener('resize', () => { if (isOpen) positionDropdown(); });
        window.addEventListener('scroll', () => { if (isOpen) positionDropdown(); }, true);
        document.addEventListener('keydown', (e)=>{ if (e.key==='Escape') closeDropdown(); });
        document.querySelector('input[name="url"]').addEventListener('change', () => closeDropdown());
        document.querySelector('input[name="driver"]').addEventListener('change', () => closeDropdown());

        // Hook model changes
        function clearProviderIfOpenRouter(){
            const url = (document.querySelector('input[name="url"]').value||'');
            const driver = (document.querySelector('input[name="driver"]').value||'');
            if (url.includes('openrouter.ai') || /openrouter/.test(driver)){
                providerInput.value = '';
                try { providerInput.dispatchEvent(new Event('input', { bubbles:true })); } catch(_){ }
                try { providerInput.dispatchEvent(new Event('change', { bubbles:true })); } catch(_){ }
            }
        }
        modelInput.addEventListener('change', () => { clearProviderIfOpenRouter(); maybeAutofillProvider(); if (isOpen && providersCache) renderList(providersCache, providerInput.value, getRelevantProviderSlugs()); });
        modelInput.addEventListener('input', () => { clearProviderIfOpenRouter(); maybeAutofillProvider(); if (isOpen && providersCache) renderList(providersCache, providerInput.value, getRelevantProviderSlugs()); });
    })();
    </script>
    <?php if ($noticeMsg !== ''): ?>
    <script>
    (function(){
        var t = document.getElementById('toast');
        if (!t) return;
        var m = t.querySelector('.message');
        if (m) m.textContent = <?= json_encode($noticeMsg, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
        t.style.display = 'block';
        setTimeout(function(){ t.style.display = 'none'; }, 3000);
    })();
    </script>
    <?php endif; ?>
    <?php
    exit;
}

// Handle Create
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create"])) {
    // Seed required defaults for new connector (force values on create)
    $payload = $_POST;
    $payload['driver'] = 'openrouterjson';
    $payload['temperature'] = 1;
    $payload['url'] = 'https://openrouter.ai/api/v1/chat/completions';
    $payload['reasoning_model'] = 0;
    $payload['max_tokens'] = 250;
    $payload['api_badge_id'] = 1;
    $payload['enforce_json'] = 1;
    $payload['json_schema'] = 1;
    $payload['service'] = 'openrouter';

    $newId = $llm->create($payload);
    if (!$newId) {
        $last = $GLOBALS["db"]->fetchOne("SELECT id FROM core_llm_connector ORDER BY id DESC LIMIT 1");
        $newId = $last['id'] ?? '';
    }
    header("Location: llm_connectors.php" . ($newId ? ('?edit=' . urlencode($newId)) : ''));
    exit;
}

// Handle Import CSV (create new connector from CSV)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["import"])) {
    $redir = 'llm_connectors.php';
    try {
        if (!isset($_FILES['import_file']) || !is_uploaded_file($_FILES['import_file']['tmp_name'])) {
            header("Location: $redir");
            exit;
        }
        $tmp = $_FILES['import_file']['tmp_name'];
        $fh = fopen($tmp, 'r');
        if (!$fh) { header("Location: $redir"); exit; }
        $header = fgetcsv($fh);
        if ($header === false) { fclose($fh); header("Location: $redir"); exit; }
        $cols = array_map(function($v){ return strtolower(trim((string)$v)); }, $header);
        $row = fgetcsv($fh);
        fclose($fh);
        if ($row === false) { header("Location: $redir"); exit; }
        $dataMap = [];
        for ($i=0; $i<count($cols); $i++) { $k = $cols[$i] ?? ''; if ($k==='') continue; $dataMap[$k] = $row[$i] ?? ''; }

        $getString = function($k) use ($dataMap){ $v = isset($dataMap[$k]) ? (string)$dataMap[$k] : ''; return trim($v); };
        $getInt = function($k) use ($dataMap){ $v = isset($dataMap[$k]) ? $dataMap[$k] : ''; $v = trim((string)$v); if ($v==='') return null; return (int)$v; };
        $getFloat = function($k) use ($dataMap){ $v = isset($dataMap[$k]) ? $dataMap[$k] : ''; $v = trim((string)$v); if ($v==='') return null; return (float)$v; };
        $getBool = function($k) use ($dataMap){ $v = isset($dataMap[$k]) ? $dataMap[$k] : ''; $s = strtolower(trim((string)$v)); if ($s==='') return null; if ($s==='1'||$s==='true'||$s==='yes'||$s==='y'||$s==='on') return 1; if ($s==='0'||$s==='false'||$s==='no'||$s==='n'||$s==='off') return 0; return null; };

        $payload = [];
        $payload['label'] = $getString('label');
        $payload['service'] = $getString('service');
        $payload['url'] = $getString('url');
        $payload['model'] = $getString('model');
        $payload['provider'] = $getString('provider');
        $payload['driver'] = $getString('driver');
        $payload['api_badge_id'] = $getInt('api_badge_id');
        $payload['max_tokens'] = $getInt('max_tokens');
        $payload['temperature'] = $getFloat('temperature');
        $payload['presence_penalty'] = $getFloat('presence_penalty');
        $payload['frequency_penalty'] = $getFloat('frequency_penalty');
        $payload['repetition_penalty'] = $getFloat('repetition_penalty');
        $payload['top_p'] = $getFloat('top_p');
        $payload['top_k'] = $getInt('top_k');
        $payload['min_p'] = $getFloat('min_p');
        $payload['top_a'] = $getFloat('top_a');
        $b = $getBool('enforce_json'); if ($b!==null) $payload['enforce_json'] = $b; else unset($payload['enforce_json']);
        $b = $getBool('json_schema'); if ($b!==null) $payload['json_schema'] = $b; else unset($payload['json_schema']);
        $b = $getBool('prefill_json'); if ($b!==null) $payload['prefill_json'] = $b; else unset($payload['prefill_json']);
        $b = $getBool('reasoning_model'); if ($b!==null) $payload['reasoning_model'] = $b; else unset($payload['reasoning_model']);
        $meta = $getString('metadata'); if ($meta !== '') { $payload['metadata'] = $meta; }

        // Infer service if missing
        if (($payload['service'] ?? '') === '') {
            $d = strtolower((string)($payload['driver'] ?? ''));
            $u = strtolower((string)($payload['url'] ?? ''));
            if (strpos($d,'openai')!==false || strpos($u,'openai.com')!==false) $payload['service']='openai';
            elseif (strpos($d,'google')!==false || strpos($u,'generativelanguage.googleapis.com')!==false) $payload['service']='google';
            elseif (strpos($u,'nano-gpt.com')!==false) $payload['service']='nanogpt';
            elseif (strpos($d,'openrouter')!==false || strpos($u,'openrouter.ai')!==false) $payload['service']='openrouter';
            else $payload['service']='custom';
        }
        // Seed minimal defaults similar to create_blank when missing
        if (!isset($payload['driver']) || $payload['driver']==='') {
            $svc = $payload['service'] ?? 'openrouter';
            $payload['driver'] = ($svc==='openrouter') ? 'openrouterjson' : (($svc==='openai') ? 'openaijson' : (($svc==='google') ? 'google_openaijson' : (($svc==='nanogpt') ? 'openrouterjson' : 'openaijson')));
        }
        if (!isset($payload['temperature']) || $payload['temperature']===null) $payload['temperature'] = 1;
        if (!isset($payload['max_tokens']) || $payload['max_tokens']===null) $payload['max_tokens'] = 250;

        // Ensure label present
        if ($payload['label'] === '') { $payload['label'] = 'Imported Connector'; }
        // Keep label as-is (no import suffix)

        $newId = $llm->create($payload);
        if (!$newId) {
            $last = $GLOBALS["db"]->fetchOne("SELECT id FROM core_llm_connector ORDER BY id DESC LIMIT 1");
            $newId = $last['id'] ?? '';
        }
        $redir = 'llm_connectors.php' . ($newId ? ('?edit=' . urlencode($newId)) : '');
    } catch (Exception $_e) {
        $redir = 'llm_connectors.php';
    }
    header("Location: $redir");
    exit;
}

// (moved) export handler is now at top before any output

// Create a blank LLM connector and open it for editing
if (isset($_GET["create_blank"])) {
    $newId = $llm->create([
        "label" => "New Connector",
        'driver' => 'openrouterjson',
        'temperature' => 1,
        'url' => 'https://openrouter.ai/api/v1/chat/completions',
        'reasoning_model' => 0,
        'max_tokens' => 250,
        'api_badge_id' => 1,
        'enforce_json' => 1,
        'json_schema' => 1,
        'service' => 'openrouter'
    ]);
    $redir = 'llm_connectors.php' . ($newId ? ('?edit=' . urlencode($newId)) : '');
    header("Location: $redir");
    exit;
}

// Handle Save (update without leaving current connector)
if ($_SERVER["REQUEST_METHOD"] === "POST" && (isset($_POST["save"]) || isset($_POST["update"])) ) {
    $id = $_POST["id"] ?? '';
    $llm->update($id, $_POST);
    $redir = 'llm_connectors.php' . ($id !== '' ? ('?edit=' . urlencode($id)) : '');
    if (isset($_POST['partial']) && $_POST['partial'] === 'editor') {
        $redir .= ($id !== '' ? '&' : '?') . 'partial=editor';
    }
    header("Location: $redir");
    exit;
}

// Handle Delete
if (isset($_GET["delete"])) {
    $id = intval($_GET["delete"]);
    // Prevent deletion if any profile references this connector
    try {
        $cntRow = $GLOBALS["db"]->fetchOne(
            "SELECT COUNT(*) AS cnt FROM core_profiles WHERE " .
            "llm_primary_id={$id} OR llm_secondary_id={$id} OR llm_tertiary_id={$id} OR " .
            "llm_quaternary_id={$id} OR llm_formatter_id={$id}"
        );
        $inUse = intval($cntRow['cnt'] ?? 0);
    } catch (Exception $e) {
        $inUse = 0;
    }
    if ($inUse > 0) {
        $msg = "Cannot delete: connector is used by ${inUse} profile" . ($inUse>1? 's' : '') . ". Remove from all profiles first.";
        header("Location: llm_connectors.php?notice=" . urlencode($msg));
        exit;
    }
    $llm->delete($id);
    header("Location: llm_connectors.php");
    exit;
}

// Add a new action for cloning a connector
if (isset($_GET["clone"])) {
    $llm->clone($_GET["clone"]);
    header("Location: llm_connectors.php");
    exit;
}

// Fetch Data
$data = $llm->readAll();
$editItem = null;
if (isset($_GET["edit"])) {
    $editItem = $llm->getById($_GET["edit"]);
}
?>

<h1 class="api-title">LLM Connectors</h1>
<div id="toast" class="toast-notification" style="position:static; margin: 8px auto 12px; display:block; opacity:0; transform:none; max-width:960px; width: calc(100% - 20px);"><span class="message"></span></div>

<div class="llm-layout">
    <div class="llm-left position-sticky">
        <div class="llm-title" style="margin: 4px 0 6px 2px; font-weight: 600; color: rgb(242,124,17);">Connectors</div>
        <div style="margin: 6px 0 10px 4px; display:flex; gap:8px; flex-wrap:wrap;">
            <form method="get" style="display:inline" action="llm_connectors.php">
                <input type="hidden" name="create_blank" value="1">
                <button type="submit" class="btn-save">New Connector</button>
            </form>
            <form method="post" style="display:inline" action="llm_connectors.php" enctype="multipart/form-data" id="llm_import_form">
                <input type="hidden" name="import" value="1">
                <input type="file" name="import_file" id="llm_import_file" accept=".csv" style="display:none">
                <button type="button" class="btn-primary" id="llm_import_btn">Import</button>
            </form>
        </div>
        <div id="llm_list" class="conn-list"></div>
        <script>
        (function(){
            const RAW = <?= json_encode($data ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
            const ACTIVE_ID = <?= json_encode($_GET['edit'] ?? '') ?>;
            const list = document.getElementById('llm_list');
            function escapeHtml(s){ return (s==null?'':String(s)).replace(/[&<>]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])); }
            function pass(_row){ return true; }
            function render(){
                const rows=(RAW||[]).filter(pass);
                let html='';
                rows.forEach(r=>{
                    const active = String(r.id)===String(ACTIVE_ID) ? ' active' : '';
                    html += `
                        <div class="conn-li${active}" data-id="${String(r.id)}">
                            <div class="head">
                                <div class="title">${escapeHtml(r.label||('Connector #'+r.id))}</div>
                                <div class="badge">${escapeHtml(r.driver||'')}</div>
                            </div>
                            <div class="sub">${escapeHtml(r.model||'')}</div>
                            <div class="actions">
                                <form method="get" action="llm_connectors.php" onsubmit="return confirm('Are you sure you want to delete this connector?');" style="display:inline">
                                    <input type="hidden" name="delete" value="${String(r.id)}">
                                    <button type="submit" class="btn-danger">Delete</button>
                                </form>
                                <form method="get" action="llm_connectors.php" style="display:inline">
                                    <input type="hidden" name="clone" value="${String(r.id)}">
                                    <button type="submit" class="btn-primary">Clone</button>
                                </form>
                            </div>
                        </div>`;
                });
                list.innerHTML = html || '<div class="conn-li"><em>No connectors match filters.</em></div>';
                // Make rows clickable to open editor, but ignore clicks on action links
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
        <script>
        (function(){
            const btn = document.getElementById('llm_import_btn');
            const file = document.getElementById('llm_import_file');
            const form = document.getElementById('llm_import_form');
            if (!btn || !file || !form) return;
            btn.addEventListener('click', function(){ file.click(); });
            file.addEventListener('change', function(){ if (file.files && file.files.length>0) { form.submit(); }});
        })();
        </script>
    </div>
    <div class="llm-right">
<div class="form-container wide-centered">
<style>
.wide-centered { max-width: 1300px; margin: 0 auto; padding-bottom: calc((2.2em * 1.2) + 50px); /*bottom padding fixes scroll issue with sticky */ }
.two-col-llm { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 1000px) { .two-col-llm { grid-template-columns: 1fr; } }
.kv-grid { display: grid; grid-template-columns: 220px 1fr; gap: 8px 12px; align-items: center; }
.inline-num { width: 90px; }
.service-picker { display:flex; align-items:center; gap:12px; margin: 6px 0 12px; }
.service-icons { display:flex; gap:8px; align-items:center; }
.service-icon { width:56px; height:56px; border:1px solid rgba(138,155,182,0.3); border-radius:8px; cursor:pointer; opacity:0.8; }
.service-icon.active { outline:2px solid rgb(242,124,17); opacity:1; }
/* Fancy tooltip for slider labels */
.tip-label { position: relative; cursor: help; }
.tip-label::after { content: attr(data-tip); position: absolute; left: 0; top: 120%; max-width: 560px; padding: 8px 10px; background: #0c0f14; color: #cfe0ff; border: 1px solid rgba(138,155,182,0.35); border-radius: 8px; box-shadow: 0 6px 18px rgba(0,0,0,0.35); white-space: normal; line-height: 1.3; font-size: 12px; opacity: 0; transform: translateY(-4px); transition: opacity .12s ease, transform .12s ease; pointer-events: none; z-index: 9999; }
.tip-label:hover::after { opacity: 1; transform: translateY(0); }
/* API key notice */
.api-key-notice { margin-top:6px; font-size:12px; }
.api-key-notice.warn { color:#ffb862; }
.api-key-notice.ok { color:#6dd19c; }
/* OpenRouter model dropdown */
.orm-dropdown { position:absolute; z-index: 9999; max-height: 360px; overflow:auto; background:#111; border:1px solid rgba(138,155,182,0.3); border-radius:8px; box-shadow: 0 6px 18px rgba(0,0,0,0.35); display:none; }
.orm-item { padding:8px 10px; cursor:pointer; border-bottom:1px solid rgba(138,155,182,0.15); }
.orm-item:last-child { border-bottom:none; }
.orm-item:hover { background:#1a1f29; }
.orm-head { padding:8px 10px; font-weight:bold; position:sticky; top:0; background:#0c0f14; border-bottom:1px solid rgba(138,155,182,0.3); }
.orm-note { padding:6px 10px; font-size:12px; color:#97a6ba; border-bottom:1px dashed rgba(138,155,182,0.25); background:#0c0f14; }
.orm-muted { color:#97a6ba; }
.orm-err { color:#ff6b6b; padding:8px 10px; }
.orm-info-box { border:1px solid rgba(138,155,182,0.3); background:#0d1117; border-radius:8px; padding:8px 10px; margin-top:8px; }
/* Split layout: list left, editor right */
.llm-layout { display:grid; grid-template-columns: minmax(240px, 340px) 1fr; gap:16px; align-items:stretch; }
/* Keep two-column layout even on narrower screens so half-screen works */
@media (max-width: 1100px) { .llm-layout { grid-template-columns: minmax(220px, 300px) 1fr; } }
@media (max-width: 860px) { .llm-layout { grid-template-columns: minmax(200px, 260px) 1fr; } }
.llm-left { display:flex; flex-direction:column; height:calc(100vh - (2.2em*1.2) - 55px); overflow:hidden; padding:8px; padding-right:8px; border:1px solid #4a4a4a; border-radius:8px; background:#2a2a2a; top: calc((2.2em*1.2) + 50px)}
.llm-left .llm-title { margin: 6px 0 10px 4px; font-size: 20px; color: #e9efff; }
.llm-right { min-width: 0; }
.list-filters { display:flex; gap:8px; align-items:center; margin:6px 0 10px; flex-wrap:wrap; }
.list-filters input[type="text"]{ width: 100%; max-width: 260px; }
.list-filters select { max-width: 200px; }
.conn-list { display:flex; flex-direction:column; gap:8px; flex:1 1 auto; overflow:auto; }
.conn-li { border:1px solid #4a4a4a; background:#2a2a2a; border-radius:10px; padding:10px; cursor:pointer; transition:transform .08s ease, background .12s ease; }
.conn-li:hover { background:#3a3a3a; transform: translateY(-1px); }
.conn-li.active { outline:2px solid rgb(242,124,17); }
.conn-li .head { display:flex; justify-content:space-between; gap:8px; align-items:center; }
.conn-li .title { font-weight:600; color:#e9efff; }
.conn-li .badge { font-size:11px; padding:2px 6px; border:1px solid #4a4a4a; border-radius:999px; color:#9fb1c9; }
.conn-li .sub { font-size:12px; color:#9fb1c9; margin-top:3px; overflow-wrap:anywhere; }
.conn-li .actions { display:flex; gap:6px; margin-top:6px; justify-content:flex-end; }
/* Collapsible block for Metadata */
.collapsible { margin-top: 8px; border:1px solid #4a4a4a; border-radius:10px; background:#2a2a2a; }
.collapsible-header { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:10px; cursor:pointer; user-select:none; color:#e9efff; font-weight:600; }
.collapsible-header::after { content:'\25BE'; font-size:12px; color:#9fb1c9; transition: transform .12s ease; }
.collapsible[open] .collapsible-header { border-bottom:1px solid #4a4a4a; }
.collapsible[open] .collapsible-header::after { transform: rotate(180deg); }
.collapsible-content { padding:10px; }
/* Inline title + toggle styling */
.label-with-toggle { display:flex; align-items:center; gap:10px; }
.label-with-toggle input[type="checkbox"] { accent-color:#176529; transform: scale(1.8); transform-origin:center; cursor:pointer; }
</style>
<?php if (!$editItem): ?>
    <div class="connector-placeholder" style="border:1px dashed #4a4a4a; background:#2a2a2a; color:#9fb1c9; border-radius:10px; padding:18px; margin-bottom:10px;">
        <div style="font-weight:600; color:#e9efff; margin-bottom:6px;">No connector selected</div>
        <div>Select a connector from the list on the left to view and edit its settings.</div>
    </div>
<?php endif; ?>
<script>
// Ensure consolidation() exists even without metadata editor
if (typeof window.consolidation !== 'function') {
    window.consolidation = function(){ return true; };
}
</script>
<form method="post" onsubmit='return consolidation()' style='<?= $editItem!=null?"":"display:none"?>'>
    <?php if ($editItem): ?>
        <input type="hidden" name="id" value="<?= $editItem["id"] ?>">
    <?php endif; ?>

    <div class="two-col-llm">
        <div>
            <div class="top-actions" style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">
                <?php if ($editItem): ?>
                    <button type="submit" name="save" class="btn-save">Save</button>
                    <button type="button" id="btn_test_connector_main" class="btn-primary">Test</button>
                    <button type="submit" formmethod="get" formaction="llm_connectors.php" name="export" value="<?= htmlspecialchars($editItem['id'] ?? '') ?>" class="btn-primary">Export</button>
                    <div class="orm-note" style="margin-top:6px;">Please save any changes before testing to ensure the latest settings are used.</div>
                <?php else: ?>
                    <button type="submit" name="create" class="btn-save">Create</button>
                <?php endif; ?>
            </div>
            <label for='label'>Name</label><br>
            <input type="text" name="label" value="<?= htmlspecialchars($editItem["label"] ?? "") ?>"><br>

            <label id="service_label">Service</label>
            <div class="service-picker">
                <div class="service-icons">
                    <img src="<?= $webRoot; ?>/ui/images/core/icons/openrouter.jpg" alt="OpenRouter" class="service-icon" data-service="openrouter" />
                    <img src="<?= $webRoot; ?>/ui/images/core/icons/openai.jpg" alt="OpenAI" class="service-icon" data-service="openai" />
                    <img src="<?= $webRoot; ?>/ui/images/core/icons/google.jpg" alt="Google" class="service-icon" data-service="google" />
                    <img src="<?= $webRoot; ?>/ui/images/core/icons/nanogpt.jpg" alt="NanoGPT" class="service-icon" data-service="nanogpt" />
                    <img src="<?= $webRoot; ?>/ui/images/core/icons/custom.jpg" alt="Custom" class="service-icon" data-service="custom" />                </div>
                </div>
            <input type="hidden" id="service_input" name="service" value="<?= htmlspecialchars($editItem["service"] ?? "") ?>">

            <div id="custom_note" class="orm-muted" style="font-size:12px; display:none; margin:-6px 0 8px 0;">
                Custom allows you to build your own connector setting using one of our API drivers to use non-supported services with CHIM. Depending on the service you may not need to fill out all fields. For advanced users only
            </div>

            <div id="url_row">
            <label for='url'>URL</label><br>
                <div style="display:flex; gap:8px; align-items:center;">
                    <input type="text" name="url" value="<?= htmlspecialchars($editItem["url"] ?? "") ?>" style="flex:1 1 auto;">
                    <button type="button" id="btn_wsl_ip" class="btn-primary" title="Use WSL IP" style="display:none;" data-ip="<?= htmlspecialchars($WSL_IP) ?>">WSL IP</button>
                    <button type="button" id="btn_host_ip" class="btn-primary" title="Use HOST IP" style="display:none;" data-ip="<?= htmlspecialchars($HOST_IP) ?>">PC IP</button>
                </div>
            </div>
            <label for='model'>Model</label><br>
            <input type="text" name="model" value="<?= htmlspecialchars($editItem["model"] ?? "") ?>"><br>

            <div id="provider_row">
                <label for='provider'>Provider</label><br>
                <input type="text" name="provider" placeholder="(Optional) leave empty to use recommended provider" value="<?= htmlspecialchars($editItem["provider"] ?? "") ?>"><br>
            </div>

            <div id="driver_row" style="display:none;">
                <label for='driver'>Driver</label><br>
                <input type="text" id="driver_input" name="driver" value="<?= htmlspecialchars($editItem["driver"] ?? "") ?>" style="display:none"><br>
                <select id="driver_select">
                    <option value="openrouterjson">OpenRouter JSON</option>
                    <option value="openrouterjsoncached">OpenRouter JSON (Cached)</option>
                    <option value="openrouterjsoncached_verbose">OpenRouter JSON (Cached + Verbose Logging)</option>
                    <option value="openaijson">OpenAI JSON</option>
                    <option value="google_openaijson">Google OpenAI JSON</option>
                </select>
            </div>

            <div id="api_key_row">
            <?php
            // API Key select with non-empty keys first + notice
            $apiRows = $GLOBALS["db"]->fetchAll("SELECT id, label, api_key FROM core_api_badge ORDER BY label ASC");
            $selectedApi = $editItem["api_badge_id"] ?? "";
            $withKey = [];
            $noKey = [];
            $isEmptyKey = function($v){
                $raw = trim((string)$v);
                if ($raw === '') return true;
                if (preg_match('/^(?:\*+|null|none|n\\/a)$/i', $raw)) return true;
                if (preg_match('/^[^A-Za-z0-9]+$/', $raw)) return true; // only punctuation/whitespace
                return false;
            };
            foreach ($apiRows as $r){ if (!$isEmptyKey($r['api_key'] ?? '')) $withKey[]=$r; else $noKey[]=$r; }
            echo "<label for='api_badge_id'>API Key</label><br>";
            echo "<select id='api_badge_id' name='api_badge_id'>";
            echo "<option value=''>-- Select API Key --</option>";
            foreach ($withKey as $r){
                $id = htmlspecialchars($r['id']);
                $labRaw = ($r['label'] ?? ('Key #'.$r['id']));
                $disp = '🟢 ' . $labRaw;
                $lab = htmlspecialchars($disp);
                $sel = (string)$selectedApi === (string)$r['id'] ? ' selected' : '';
                echo "<option value='{$id}' data-empty='0'{$sel}>{$lab}</option>";
            }
            if (!empty($noKey)){
                echo "<option value='' disabled>— Missing Key —</option>";
                foreach ($noKey as $r){
                    $id = htmlspecialchars($r['id']);
                    $labRaw = ($r['label'] ?? ('Key #'.$r['id'])) . ' — No key';
                    $disp = '🔴 ' . $labRaw;
                    $lab = htmlspecialchars($disp);
                    $sel = (string)$selectedApi === (string)$r['id'] ? ' selected' : '';
                    echo "<option value='{$id}' data-empty='1'{$sel}>{$lab}</option>";
                }
            }
            echo "</select>";
            echo "<div id='api_key_notice' class='api-key-notice'></div>";
            ?>
            </div>
            <div id="reasoning_details_modal" style="margin-top:8px; padding:8px; border-left:2px solid #444;">
                <label class="label-with-toggle"><span class='tip-label' data-tip='Enable thinking/reasoning for supported models (like o1, DeepSeek-R1). Shows model internal reasoning process.'>Toggle Thinking</span>
                    <input type="hidden" name="metadata[toggle_thinking]" value="0">
                    <input type="checkbox" name="metadata[toggle_thinking]" id="toggle_thinking_modal" value="1" <?= $toggleThinking ? "checked" : "" ?>>
                    <span class="toggle-text">On</span>
                </label>
                <div style="height:6px;"></div>
                <label for='thinking_tokens_modal'><span class='tip-label' data-tip='Maximum tokens for thinking/reasoning output (Anthropic/Gemini only). OpenAI uses effort_level instead. Leave empty to use default.'>Thinking Tokens</span></label>
                <input type="number" name="metadata[thinking_tokens]" id="thinking_tokens_modal" value="<?= htmlspecialchars($thinkingTokens) ?>" min="0" step="1" placeholder="Optional">
                <div style="height:6px;"></div>
                <label for='effort_level_modal'><span class='tip-label' data-tip='Reasoning effort level for OpenAI reasoning models (o1, o3, o4, gpt-5). minimal=Quick (gpt-5+), low=Basic, medium=Balanced, high=Thorough. Leave empty for default.'>Effort Level</span></label>
                <select name="metadata[effort_level]" id="effort_level_modal">
                    <option value="">-- select --</option>
                    <option value="minimal" <?= $effortLevel === 'minimal' ? 'selected' : '' ?>>Minimal</option>
                    <option value="low" <?= $effortLevel === 'low' ? 'selected' : '' ?>>Low</option>
                    <option value="medium" <?= $effortLevel === 'medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="high" <?= $effortLevel === 'high' ? 'selected' : '' ?>>High</option>
                </select>
            </div>
            <div id="json_toggles" style="margin-top:8px;">
                <label class="label-with-toggle"><span class='tip-label' data-tip='Force responses to be strict JSON. Non‑JSON output may be rejected or auto‑retried.'>Enforce JSON</span>
                    <input type="hidden" name="enforce_json" value="0">
                    <input type="checkbox" name="enforce_json" value="1" <?= isset($editItem["enforce_json"]) && $editItem["enforce_json"] == 1 ? "checked" : "" ?>>
                    <span class="toggle-text">On</span>
                </label>

                <div style="height:6px;"></div>
                <label class="label-with-toggle"><span class='tip-label' data-tip='Guide/validate the JSON structure with a schema. Best used with Enforce JSON.'>JSON Schema</span>
                    <input type="hidden" name="json_schema" value="0">
                    <input type="checkbox" name="json_schema" value="1" <?= isset($editItem["json_schema"]) && $editItem["json_schema"] == 1 ? "checked" : "" ?>>
                    <span class="toggle-text">On</span>
                </label>

                <div style="height:6px;"></div>
                <label class="label-with-toggle"><span class='tip-label' data-tip='Send a starter JSON object to steer field names/shape in the response.'>Prefill JSON</span>
                    <input type="hidden" name="prefill_json" value="0">
                    <input type="checkbox" name="prefill_json" value="1" <?= isset($editItem["prefill_json"]) && $editItem["prefill_json"] == 1 ? "checked" : "" ?>>
                    <span class="toggle-text">On</span>
                </label>
            </div>

            <?php
            // Decode metadata for caching settings (main editor)
            $metadata_main = [];
            if (isset($editItem['metadata'])) {
                if (is_string($editItem['metadata'])) {
                    $metadata_main = json_decode($editItem['metadata'], true) ?: [];
                } elseif (is_array($editItem['metadata'])) {
                    $metadata_main = $editItem['metadata'];
                }
            }
            ?>

            <!-- Caching Settings (shown only for cached connectors) - MAIN EDITOR -->
            <div id="caching_settings_main" style="display:none; margin-top:16px; padding:12px; border:1px solid #4a4a4a; border-radius:8px; background:#1a1a1a;">
                <div style="font-weight:600; color:#e9efff; margin-bottom:12px;">🔄 Caching Settings</div>
                <div id="connector_version_main" style="font-size:0.85em; color:#999; margin-bottom:12px; font-style:italic;"></div>

                <label for='provider_caching_main'>Provider Caching Type</label><br>
                <select name="metadata[provider_caching]" id="provider_caching_main">
                    <option value="Anthropic" <?= ($metadata_main['provider_caching'] ?? 'Anthropic') === 'Anthropic' ? 'selected' : '' ?>>Anthropic</option>
                    <option value="OpenAI" <?= ($metadata_main['provider_caching'] ?? '') === 'OpenAI' ? 'selected' : '' ?>>OpenAI</option>
                    <option value="Gemini" <?= ($metadata_main['provider_caching'] ?? '') === 'Gemini' ? 'selected' : '' ?>>Gemini</option>
                </select><br>

                <label for='response_format_main'>Response Format</label><br>
                <select name="metadata[response_format]" id="response_format_main">
                    <option value="json" <?= ($metadata_main['response_format'] ?? 'json') === 'json' ? 'selected' : '' ?>>JSON (structured)</option>
                    <option value="simple" <?= ($metadata_main['response_format'] ?? '') === 'simple' ? 'selected' : '' ?>>Simple (natural language)</option>
                </select><br>

                <label for='dialogue_cache_uncached_count_main'><span class='tip-label' data-tip='Number of most recent dialogue entries to keep uncached (0-10)'>Uncached Dialogue Count</span></label><br>
                <input type='number' name='metadata[dialogue_cache_uncached_count]' id='dialogue_cache_uncached_count_main' value='<?= htmlspecialchars($metadata_main['dialogue_cache_uncached_count'] ?? '4') ?>' min='0' max='10' step='1'><br>

                <div id="simple_format_options_main" style="display:none; margin-top:12px; padding:8px; border-left:3px solid #176529;">
                    <div style="font-size:13px; font-weight:600; margin-bottom:8px;">Simple Format Content Options:</div>
                    <label class="label-with-toggle"><span>Include Mood</span>
                        <input type="hidden" name="metadata[include_mood_requirement]" value="0">
                        <input type="checkbox" name="metadata[include_mood_requirement]" value="1" <?= (!isset($metadata_main['include_mood_requirement']) || $metadata_main['include_mood_requirement']) ? 'checked' : '' ?>>
                    </label><br>
                    <label class="label-with-toggle"><span>Include Listener</span>
                        <input type="hidden" name="metadata[include_listener_requirement]" value="0">
                        <input type="checkbox" name="metadata[include_listener_requirement]" value="1" <?= (!isset($metadata_main['include_listener_requirement']) || $metadata_main['include_listener_requirement']) ? 'checked' : '' ?>>
                    </label><br>
                    <label class="label-with-toggle"><span>Include Actions</span>
                        <input type="hidden" name="metadata[include_actions_list]" value="0">
                        <input type="checkbox" name="metadata[include_actions_list]" value="1" <?= (!isset($metadata_main['include_actions_list']) || $metadata_main['include_actions_list']) ? 'checked' : '' ?>>
                    </label><br>
                    <label class="label-with-toggle"><span>Include Target</span>
                        <input type="hidden" name="metadata[include_target_requirement]" value="0">
                        <input type="checkbox" name="metadata[include_target_requirement]" value="1" <?= (!isset($metadata_main['include_target_requirement']) || $metadata_main['include_target_requirement']) ? 'checked' : '' ?>>
                    </label>
                </div>

                <div id="verbose_logging_option_main" style="display:none; margin-top:12px;">
                    <label class="label-with-toggle"><span class='tip-label' data-tip='Enable detailed logging for testing (verbose connector only)'>Verbose Logging</span>
                        <input type="hidden" name="metadata[verbose_logging]" value="0">
                        <input type="checkbox" name="metadata[verbose_logging]" value="1" <?= (!isset($metadata_main['verbose_logging']) || $metadata_main['verbose_logging']) ? 'checked' : '' ?>>
                        <span class="toggle-text">On</span>
                    </label>
                </div>

                <div style="margin-top:12px;">
                    <label class="label-with-toggle"><span class='tip-label' data-tip='Recommended ON for advanced models (Claude 4.5, GPT-4, Gemini 2.0). Uses minimal quality instructions. Turn OFF for older/smaller models that benefit from explicit guidance.'>Minimize Quality Instructions (Recommended)</span>
                        <input type="hidden" name="metadata[minimize_quality_prompt]" value="0">
                        <input type="checkbox" name="metadata[minimize_quality_prompt]" value="1" <?= (!isset($metadata_main['minimize_quality_prompt']) || $metadata_main['minimize_quality_prompt']) ? 'checked' : '' ?>>
                        <span class="toggle-text">On</span>
                    </label>
                </div>

                <!-- Advanced Settings Section (Collapsible) -->
                <div style="margin-top:16px; border:1px solid #4a4a4a; border-radius:8px; background:#252525;">
                    <div class="collapsible-header" data-target="advanced_settings_section_main" style="padding:10px; cursor:pointer; user-select:none; font-weight:600; color:#e9efff; display:flex; justify-content:space-between; align-items:center;">
                        <span>⚙️ Advanced Settings</span>
                        <span class="collapse-arrow">▼</span>
                    </div>
                    <div id="advanced_settings_section_main" class="collapsible-content" style="padding:10px; display:none;">
                        <label for='max_dialogue_cache_context_size_main'><span class='tip-label' data-tip='Maximum number of dialogue entries to cache in temp files. Higher = more context but larger cache files. Recommended: 93'>Max Dialogue Cache Context Size</span></label><br>
                        <input type='number' name='metadata[max_dialogue_cache_context_size]' id='max_dialogue_cache_context_size_main' value='<?= htmlspecialchars($metadata_main['max_dialogue_cache_context_size'] ?? '93') ?>' min='0' step='1'><br>

                        <label for='custom_system_instruction_main'><span class='tip-label' data-tip='Additional instruction added to the system prompt (after character bio, before dialogue history). Does NOT replace other instructions.'>Custom System Instruction</span></label><br>
                        <textarea name='metadata[custom_system_instruction]' id='custom_system_instruction_main' rows='3' style='width:100%; box-sizing:border-box;'><?= htmlspecialchars($metadata_main['custom_system_instruction'] ?? '') ?></textarea><br>

                        <label for='custom_last_instruction_main'><span class='tip-label' data-tip='Custom text inserted as second-to-last element in dialogue history (current user message is always last). Appears right before user current request.'>Custom Last Instruction</span></label><br>
                        <textarea name='metadata[custom_last_instruction]' id='custom_last_instruction_main' rows='3' style='width:100%; box-sizing:border-box;'><?= htmlspecialchars($metadata_main['custom_last_instruction'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <?php
            $tipMaxTokens = 'Maximum tokens the model can generate for a response. Higher = longer answers; may increase cost/latency. [>= 0]';
            echo "<label for='max_tokens' style='margin-top:10px; display:block;'><span class='tip-label' data-tip='" . htmlspecialchars($tipMaxTokens, ENT_QUOTES) . "'>Max Tokens</span></label>";
            echo "<input type='number' name='max_tokens' value='" . htmlspecialchars($editItem["max_tokens"] ?? "") . "' min='0' step='1'>";
            $ranges = [
                'temperature' => ['min'=>0,'max'=>2,'step'=>0.0001],
                'presence_penalty' => ['min'=>-2,'max'=>2,'step'=>0.0001],
                'frequency_penalty' => ['min'=>-2,'max'=>2,'step'=>0.0001],
                'repetition_penalty' => ['min'=>0,'max'=>2,'step'=>0.0001],
                'top_p' => ['min'=>0,'max'=>1,'step'=>0.0001],
                'top_k' => ['min'=>0,'max'=>100,'step'=>1],
                'min_p' => ['min'=>0,'max'=>1,'step'=>0.0001],
                'top_a' => ['min'=>0,'max'=>1,'step'=>0.0001],
            ];
            $displayDefaults = [
                'temperature' => 1,
                'presence_penalty' => 0,
                'frequency_penalty' => 0,
                'repetition_penalty' => 1,
                'top_p' => 0,
                'top_k' => 0,
                'min_p' => 0,
                'top_a' => 0,
            ];
            $tips = [
                'temperature' => 'Controls randomness; higher = more creative, lower = more focused. [0-2]',
                'presence_penalty' => 'Reduces repetition by discouraging repeated topics. [(-2)-2]',
                'frequency_penalty' => 'Reduces repeated words or phrases. [0-2]',
                'repetition_penalty' => 'Stops the model from repeating itself. [0-2]',
                'top_p' => 'Chooses tokens with a combined probability up to p. [0-1]',
                'top_k' => 'Picks from the top k most likely words. [0-100]',
                'min_p' => 'Ignore words with very low probability. [0-1]',
                'top_a' => 'Adjusts word probabilities for better balance. [0-1]'
            ];
            // Render Temperature control only
            echo "<div class='kv-grid'>";
            $field = 'temperature';
            if (isset($ranges[$field])){
                $conf = $ranges[$field];
                $label = ucfirst(str_replace('_',' ', $field));
                $rid = "rng_{$field}";
                $nid = "num_{$field}";
                $raw = $editItem[$field] ?? '';
                $use = ($raw === '' || $raw === null) ? ($displayDefaults[$field] ?? '') : $raw;
                $val = htmlspecialchars($use);
                $min = $conf['min'];
                $max = $conf['max'];
                $step = $conf['step'];
                $tip = isset($tips[$field]) ? $tips[$field] : '';
                $labelHtml = $tip ? ("<span class='tip-label' data-tip='" . htmlspecialchars($tip, ENT_QUOTES) . "'>" . $label . "</span>") : $label;
                echo "<div><label for='{$rid}'>{$labelHtml}</label></div>";
                echo "<div>";
                echo "<input type='range' id='{$rid}' min='{$min}' max='{$max}' step='{$step}' value='{$val}' oninput=\"document.getElementById('{$nid}').value=this.value\">";
                echo "<div style='margin-top:6px;'><input type='number' class='inline-num' id='{$nid}' name='{$field}' min='{$min}' max='{$max}' step='{$step}' value='{$val}' oninput=\"llmClamp('{$rid}','{$nid}',{$min},{$max})\" data-null='" . (($raw === '' || $raw === null) ? '1' : '0') . "'></div>";
                echo "</div>";
            }
            echo "</div>";

            // Advanced block
            echo "<div style=\"border:1px solid #4a4a4a; border-radius:10px; padding:10px; margin-top:12px;\">";
                echo "<div style=\"font-weight:600; color:#e9efff; margin-bottom:4px;\">Advanced LLM Settings Override</div>";
                echo "<small class=\"hint\" style=\"display:block; margin-bottom:8px; color:#9fb1c9;\">If a value is left empty, the API provider's recommended default will be used.</small>";
            echo "<div class='kv-grid'>";
            $advancedFields = ['presence_penalty','frequency_penalty','repetition_penalty','top_p','top_k','min_p','top_a'];
            foreach ($advancedFields as $advField) {
                if (!isset($ranges[$advField])) continue;
                $conf = $ranges[$advField];
                $label = ucfirst(str_replace('_',' ', $advField));
                $rid = "rng_{$advField}";
                $nid = "num_{$advField}";
                $raw = $editItem[$advField] ?? '';
                // For advanced fields, if DB is null/empty, show empty box so it saves as NULL
                $use = ($raw === '' || $raw === null) ? '' : $raw;
                $val = htmlspecialchars($use);
                $min = $conf['min'];
                $max = $conf['max'];
                $step = $conf['step'];
                $tip = isset($tips[$advField]) ? $tips[$advField] : '';
                $labelHtml = $tip ? ("<span class='tip-label' data-tip='" . htmlspecialchars($tip, ENT_QUOTES) . "'>" . $label . "</span>") : $label;
                echo "<div><label for='{$rid}'>{$labelHtml}</label></div>";
                echo "<div>";
                echo "<input type='range' id='{$rid}' min='{$min}' max='{$max}' step='{$step}' value='{$val}' oninput=\"document.getElementById('{$nid}').value=this.value; try{document.getElementById('{$nid}_null').value='0';}catch(e){}\">";
                // No clamp on number so empty stays empty (gets saved as NULL). Range still writes number when moved.
                echo "<div style='margin-top:6px;'>";
                echo "<input type='number' class='inline-num' id='{$nid}' name='{$advField}' min='{$min}' max='{$max}' step='{$step}' value='{$val}' data-null='" . (($raw === '' || $raw === null) ? '1' : '0') . "'>";
                echo "<input type='hidden' id='{$nid}_null' name='{$advField}_is_null' value='" . (($raw === '' || $raw === null) ? '1' : '0') . "'>";
                echo "</div>";
                echo "</div>";
            }
            echo "</div>";
            echo "<div style='margin-top:10px; display:flex; gap:8px; align-items:center;'>";
            echo "<button type='button' id='btn_clear_adv' class='btn-danger'>Clear advanced settings</button>";
            echo "</div>";
            echo "</div>";
            ?>
        </div>
    </div>

    
</form>
</div>
    </div>
</div>

<script>
// Sync On/Off labels for checkboxes
(function(){
    const names = ['enforce_json','json_schema','prefill_json'];
    names.forEach(n=>{
        const cb = document.querySelector(`input[type="checkbox"][name="${n}"]`);
        if (!cb) return;
        const label = cb.closest('label');
        const span = label ? label.querySelector('.toggle-text') : null;
        function sync(){ if (span) span.textContent = cb.checked ? 'On' : 'Off'; }
        sync();
        cb.addEventListener('change', sync);
    });
})();
</script>

<script>
// Service selection and defaults
(function(){
    const defaults = {
        openrouter: 'https://openrouter.ai/api/v1/chat/completions',
        openai: 'https://api.openai.com/v1/chat/completions',
        google: 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
        nanogpt: 'https://nano-gpt.com/api/v1/chat/completions'
    };
    // No dropdown; selection by icons only
    const providerRow = document.getElementById('provider_row');
    const urlInput = document.querySelector('input[name="url"]');
    const driverInput = document.querySelector('input[name="driver"]');
    const driverRow = document.getElementById('driver_row');
    const apiBadgeSelect = document.getElementById('api_badge_id');
    const icons = document.querySelectorAll('.service-icon');
    const apiKeyRow = document.getElementById('api_key_row');
    const serviceLabelEl = document.getElementById('service_label');
    const displayNames = { openrouter: 'OpenRouter', openai: 'OpenAI', google: 'Google', nanogpt: 'Nano-GPT', custom: 'Custom' };
    function setActive(service){ icons.forEach(ic=>{ if (ic.dataset.service === service) ic.classList.add('active'); else ic.classList.remove('active'); }); }
    const driverDefaults = { openrouter: 'openrouterjson', openai: 'openaijson', google: 'google_openaijson', nanogpt: 'openrouterjson', custom: 'openaijson' };
    const apiBadgeLabelMatch = { openrouter: ['openrouter'], openai: ['openai'], google: ['google'], nanogpt: ['nano-gpt','nanogpt'] };
    function syncApiBadge(service){ if (!apiBadgeSelect) return; const targets = (apiBadgeLabelMatch[service] || []).map(s => s.toLowerCase()); if (targets.length === 0) return; let selectedVal = ''; for (let i = 0; i < apiBadgeSelect.options.length; i++) { const opt = apiBadgeSelect.options[i]; const label = (opt.textContent || opt.innerText || '').toLowerCase(); if (targets.some(t => label.includes(t))) { selectedVal = opt.value; break; } } if (selectedVal !== '') apiBadgeSelect.value = selectedVal; else apiBadgeSelect.value = ''; }
    function applyService(service, fromUser){ const serviceInput = document.getElementById('service_input'); if (serviceInput) serviceInput.value = service; if (service !== 'custom' && defaults[service]) { const currentUrl = urlInput ? String(urlInput.value||'') : ''; if (fromUser || currentUrl === '' || currentUrl === 'about:blank') { urlInput.value = defaults[service]; } } const urlRow = document.getElementById('url_row'); if (urlRow) urlRow.style.display = (service==='custom') ? '' : 'none'; providerRow.style.display = (service === 'openrouter' || service === 'custom') ? '' : 'none'; const driverSelect = document.getElementById('driver_select'); const currentDriver = driverInput ? String(driverInput.value || '') : ''; if (service === 'custom') { if (driverSelect) { driverSelect.style.display = ''; } if (driverInput) { driverInput.style.display = 'none'; } // reflect saved driver in select; default only if empty
        if (driverSelect) {
            if (currentDriver) {
                driverSelect.value = currentDriver;
            } else if (!driverSelect.value) {
                driverSelect.value = 'openaijson';
            }
        }
        if (driverInput && !currentDriver) {
            driverInput.value = driverSelect ? driverSelect.value : 'openaijson';
        }
    } else {
        if (driverSelect) { driverSelect.style.display = 'none'; }
        if (driverInput) { driverInput.style.display = ''; }
        // Only apply default driver if user initiated the change or empty
        const nextDefault = driverDefaults[service] || '';
        if (driverInput && (fromUser || !currentDriver)) {
            driverInput.value = nextDefault;
        }
    }
    const btnWSL = document.getElementById('btn_wsl_ip'); const btnHost = document.getElementById('btn_host_ip'); const isCustom = (service==='custom'); if (btnWSL) btnWSL.style.display = isCustom ? '' : 'none'; if (btnHost) btnHost.style.display = isCustom ? '' : 'none'; const customNote = document.getElementById('custom_note'); if (customNote) customNote.style.display = isCustom ? '' : 'none'; syncApiBadge(service); setActive(service); if (apiKeyRow) apiKeyRow.style.display = ''; if (driverRow) driverRow.style.display = (service === 'custom') ? '' : 'none'; if (serviceLabelEl) serviceLabelEl.textContent = 'Service: ' + (displayNames[service] || ''); }
    function detectService(){ const sValRaw=(document.getElementById('service_input')&&String(document.getElementById('service_input').value||''))||''; const sVal=sValRaw.toLowerCase(); if (['openrouter','openai','google','nanogpt','custom'].includes(sVal)) return sVal; const u=(urlInput&&String(urlInput.value||'').toLowerCase())||''; if (u){ if (u.includes('openai.com')) return 'openai'; if (u.includes('generativelanguage.googleapis.com')) return 'google'; if (u.includes('openrouter.ai')) return 'openrouter'; if (u.includes('nano-gpt.com')) return 'nanogpt'; return 'custom'; } const d=(driverInput&&String(driverInput.value||'').toLowerCase())||''; if (d.includes('openai')) return 'openai'; if (d.includes('google')) return 'google'; if (d.includes('nanogpt')) return 'nanogpt'; if (d.includes('openrouter')) return 'openrouter'; return 'openrouter'; }
    (function init(){ const service = detectService(); applyService(service, false); const driverSelect = document.getElementById('driver_select'); if (driverSelect) { driverSelect.addEventListener('change', function(){ if (driverInput) driverInput.value = this.value; }); if (driverInput && driverInput.value) driverSelect.value = driverInput.value; } const btnWSL = document.getElementById('btn_wsl_ip'); const btnHost = document.getElementById('btn_host_ip'); function fillFrom(buttonEl, ip){ if (!buttonEl) return; const form = buttonEl.closest('form'); const urlEl = form ? form.querySelector('input[name="url"]') : document.querySelector('input[name="url"]'); if (!ip || !urlEl) return; urlEl.value = 'http://' + ip + ':5001'; try { urlEl.dispatchEvent(new Event('input', { bubbles:true })); } catch(_e){} try { urlEl.dispatchEvent(new Event('change', { bubbles:true })); } catch(_e){} try { urlEl.focus(); } catch(_e){} } if (btnWSL) btnWSL.addEventListener('click', function(){ let ip = this.getAttribute('data-ip')||''; if (!ip) { ip = '<?= htmlspecialchars($WSL_IP) ?>'; } fillFrom(this, String(ip).trim()); }); if (btnHost) btnHost.addEventListener('click', function(){ let ip = this.getAttribute('data-ip')||''; if (!ip) { ip = '<?= htmlspecialchars($HOST_IP) ?>'; } fillFrom(this, String(ip).trim()); }); })();
    icons.forEach(ic=>{ ic.addEventListener('click', ()=> applyService(ic.dataset.service, true)); });
    if (driverInput){ driverInput.addEventListener('input', ()=> { applyService(detectService(), false); updateCachingSettingsMain(); }); driverInput.addEventListener('change', ()=> { applyService(detectService(), false); updateCachingSettingsMain(); }); }
    if (urlInput){ urlInput.addEventListener('change', ()=> { const sEl=document.getElementById('service_input'); const sVal = sEl ? String(sEl.value||'').toLowerCase() : ''; if (sVal==='custom') return; applyService(detectService(), false); }); }

    // Also listen to driver_select changes for caching settings
    const driverSelect = document.getElementById('driver_select');
    if (driverSelect) {
        driverSelect.addEventListener('change', function() {
            if (driverInput) driverInput.value = this.value;
            updateCachingSettingsMain();
        });
    }
})();

// Caching settings visibility logic for main editor
function updateCachingSettingsMain(){
    const driverInput = document.querySelector('input[name="driver"]');
    const driverSelect = document.getElementById('driver_select');
    const driver = driverInput ? driverInput.value : (driverSelect ? driverSelect.value : '');
    const cachingSettings = document.getElementById('caching_settings_main');
    const simpleFormatOptions = document.getElementById('simple_format_options_main');
    const verboseLoggingOption = document.getElementById('verbose_logging_option_main');
    const responseFormatSelect = document.getElementById('response_format_main');

    // Show caching settings only for cached drivers
    const isCachedDriver = driver === 'openrouterjsoncached' || driver === 'openrouterjsoncached_verbose';
    if (cachingSettings) cachingSettings.style.display = isCachedDriver ? '' : 'none';

    // Update connector version display
    const versionDiv = document.getElementById('connector_version_main');
    if (versionDiv) {
        if (driver === 'openrouterjsoncached') {
            versionDiv.textContent = <?= json_encode($cachedConnectorVersion) ?>;
        } else if (driver === 'openrouterjsoncached_verbose') {
            versionDiv.textContent = <?= json_encode($cachedConnectorVersionVerbose) ?>;
        } else {
            versionDiv.textContent = '';
        }
    }

    // Show verbose logging option only for verbose driver
    if (verboseLoggingOption) verboseLoggingOption.style.display = (driver === 'openrouterjsoncached_verbose') ? '' : 'none';

    // Show simple format options based on response_format selection
    if (responseFormatSelect && simpleFormatOptions) {
        simpleFormatOptions.style.display = (responseFormatSelect.value === 'simple') ? '' : 'none';
    }
}

// Listen for response format changes in main editor
(function(){
    const responseFormatSelect = document.getElementById('response_format_main');
    if (responseFormatSelect) {
        responseFormatSelect.addEventListener('change', updateCachingSettingsMain);
    }
    // Initial call to set correct visibility
    updateCachingSettingsMain();
})();
function llmClamp(rangeId, numberId, min, max){ const r = document.getElementById(rangeId); const n = document.getElementById(numberId); if (!r || !n) return; let v = parseFloat(n.value); if (isNaN(v)) v = min; if (v < min) v = min; if (v > max) v = max; n.value = v; r.value = v; }
// Clear advanced settings (all below Temperature)
(function(){
    const btn = document.getElementById('btn_clear_adv');
    if (!btn) return;
    btn.addEventListener('click', function(){
        
        const pairs = [
            ['rng_presence_penalty','num_presence_penalty'],
            ['rng_frequency_penalty','num_frequency_penalty'],
            ['rng_repetition_penalty','num_repetition_penalty'],
            ['rng_top_p','num_top_p'],
            ['rng_top_k','num_top_k'],
            ['rng_min_p','num_min_p'],
            ['rng_top_a','num_top_a']
        ];
        pairs.forEach(([rid,nid])=>{
            const r = document.getElementById(rid);
            const n = document.getElementById(nid);
            const h = document.getElementById(nid + '_null');
            if (n) {
                n.value=''; // empty => server maps to NULL
                n.setAttribute('data-null','1');
                try { n.dispatchEvent(new Event('change', { bubbles:true })); } catch(_){}
            }
            if (h) { h.value = '1'; }
            if (r) { r.value = r.getAttribute('min') || '0'; }
        });
    });
})();
// LLM Test Modal
(function(){
    const MODAL_ID = 'llmtest_modal';
    const modal = document.createElement('div');
    modal.id = MODAL_ID;
    modal.style.cssText = 'position:fixed; inset:0; display:none; align-items:center; justify-content:center; background:rgba(0,0,0,0.65); z-index:10000;';
    modal.innerHTML = `
        <div style="width:90%; max-width:1200px; height:80vh; background:#111; border:1px solid rgba(138,155,182,0.4); border-radius:10px; box-shadow:0 10px 30px rgba(0,0,0,0.6); position:relative; overflow:hidden;">
            <button id=\"llmtest_close\" style=\"position:absolute; top:8px; right:10px; background:#300; color:#fff; border:1px solid rgba(255,255,255,0.2); border-radius:6px; padding:4px 10px; cursor:pointer; z-index:3;\">Close</button>
            <div id=\"llmtest_loading\" style=\"position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.4); z-index:2;\">
                <div style=\"width:48px; height:48px; border:4px solid rgba(255,255,255,0.25); border-top-color:#ffb862; border-radius:50%; animation: llmspin 1s linear infinite;\"></div>
            </div>
            <iframe id=\"llmtest_iframe\" src=\"about:blank\" style=\"width:100%; height:100%; border:0; background:#0e1624; position:relative; z-index:1;\"></iframe>
        </div>
        <style>@keyframes llmspin{to{transform:rotate(360deg)}}</style>`;
    document.body.appendChild(modal);
    function openModal(url){ const iframe = document.getElementById('llmtest_iframe'); const loader = document.getElementById('llmtest_loading'); if (loader) loader.style.display = 'flex'; iframe.onload = function(){ if (loader) loader.style.display = 'none'; }; iframe.src = url; modal.style.display = 'flex'; }
    function closeModal(){ modal.style.display = 'none'; try { document.getElementById('llmtest_iframe').src='about:blank'; } catch(_){} }
    document.addEventListener('click', function(e){ if (e.target && e.target.id==='llmtest_close') closeModal(); });
    modal.addEventListener('click', function(e){ if (e.target===modal) closeModal(); });
    document.addEventListener('keydown', function(e){ if (e.key==='Escape') closeModal(); });
    const testBtn = document.getElementById('btn_test_connector_main');
    if (testBtn){
        testBtn.addEventListener('click', async function(){
            const form = document.querySelector('form[method="post"]');
            if (!form) return;
            try {
                const fd = new FormData(form);
                if (!fd.has('update') && !fd.has('create')) fd.append('update','1');
                const idInputEarly = form.querySelector('input[name="id"]');
                const cidEarly = idInputEarly ? idInputEarly.value : '';
                openModal('about:blank');
                await fetch('llm_connectors.php', { method:'POST', body: fd });
            } catch (e) {}
            const idInput = form.querySelector('input[name="id"]');
            const cid = idInput ? idInput.value : '';
            openModal('<?= $webRoot; ?>/ui/core/tests/llmtest.php' + (cid ? ('?connector_id='+encodeURIComponent(cid)) : ''));
        });
    }
})();
// API key notice
(function(){
    const sel = document.getElementById('api_badge_id');
    const note = document.getElementById('api_key_notice');
    if (!sel || !note) return;
    function update(){
        const opt = sel.options[sel.selectedIndex];
        const empty = opt ? opt.getAttribute('data-empty') === '1' : true;
        if (!opt || sel.value === ''){ note.className = 'api-key-notice warn'; note.textContent = 'No API key selected. Some services require a key.'; return; }
        if (empty){ note.className = 'api-key-notice warn'; } else { note.className = 'api-key-notice ok'; }
    }
    sel.addEventListener('change', update);
    update();
})();
// OpenRouter model dropdown
(function(){
    const modelInput = document.querySelector('input[name="model"]');
    if (!modelInput) return;
    let cache = null, dropdown = null, isOpen = false;
    function ensureDropdown(){ if (dropdown) return dropdown; dropdown = document.createElement('div'); dropdown.className = 'orm-dropdown'; document.body.appendChild(dropdown); dropdown.addEventListener('mousedown', (e)=>{ e.preventDefault(); }); return dropdown; }
    function positionDropdown(){ const rect = modelInput.getBoundingClientRect(); const style = dropdown.style; style.left = (rect.left + window.scrollX) + 'px'; style.top = (rect.bottom + window.scrollY + 4) + 'px'; style.width = rect.width + 'px'; style.display = 'block'; isOpen = true; }
    function closeDropdown(){ if (!dropdown) return; dropdown.style.display = 'none'; isOpen = false; }
    function formatPrice(n){ if (n === undefined || n === null || n === '' || isNaN(parseFloat(n))) return 'N/A'; const perTok = parseFloat(n); const perM = perTok * 1000000.0; return '$' + perM.toFixed(4) + ' / 1M tokens'; }
    function escapeHtml(s){ return (s==null? '': String(s)).replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])); }
    function encodeHtmlAttr(s){ return (s==null? '': String(s)).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
    function formatContext(val){ const num = Number(val); return isFinite(num) ? num.toLocaleString('en-US') : (val||''); }
    function renderList(models, filterText){
        ensureDropdown();
        const q = (filterText || '').toLowerCase();
        const list = (models || []).filter(m => { if (!q) return true; const id = (m.id || '').toLowerCase(); const name = (m.name || '').toLowerCase(); return id.includes(q) || name.includes(q); });
        let html = '';
        html += '<div class="orm-head">OpenRouter Models</div>';
        html += '<div class="orm-note">Click to select. Pricing shown per 1M tokens.</div>';
        if (list.length === 0){ html += '<div class="orm-muted" style="padding:8px 10px;">No matches</div>'; }
        else { list.forEach(m => { const prompt = formatPrice(m.pricing && m.pricing.prompt); const completion = formatPrice(m.pricing && m.pricing.completion); const ctxRaw = (m.top_provider && m.top_provider.context_length) || m.context_length || ''; const ctx = formatContext(ctxRaw); const name = m.name ? ' — ' + escapeHtml(m.name) : ''; const line = `${escapeHtml(m.id)}${name}`; const sub = `Pricing (per 1M tokens): input ${prompt} • output ${completion}` + (ctx? ` • context ${ctx}` : ''); html += `<div class=\"orm-item\" data-id=\"${encodeHtmlAttr(m.id)}\" title=\"${encodeHtmlAttr(m.description||m.name||m.id)}\"><div>${line}</div><div class=\"orm-muted\" style=\"font-size:12px; margin-top:2px;\">${sub}</div></div>`; }); }
        dropdown.innerHTML = html;
        dropdown.querySelectorAll('.orm-item').forEach(el => { el.addEventListener('click', () => { const id = el.getAttribute('data-id') || ''; modelInput.value = id; try { modelInput.dispatchEvent(new Event('input', { bubbles: true })); } catch (_) {} try { modelInput.dispatchEvent(new Event('change', { bubbles: true })); } catch (_) {} closeDropdown(); }); });
        positionDropdown();
    }
    async function loadModels(){ if (cache) return cache; ensureDropdown(); dropdown.innerHTML = '<div class="orm-head">OpenRouter Models</div><div class="orm-note">Loading…</div>'; positionDropdown(); try { const res = await fetch('https://openrouter.ai/api/v1/models'); if (!res.ok) throw new Error('HTTP '+res.status); const json = await res.json(); const data = Array.isArray(json && json.data) ? json.data : []; cache = data.map(m => ({ id: m.id || m.canonical_slug || '', name: m.name || '', pricing: m.pricing || {}, top_provider: m.top_provider || {}, context_length: m.context_length || undefined, description: m.description || '' })); cache.sort((a,b)=> (a.name||'').localeCompare(b.name||'') || (a.id||'').localeCompare(b.id||'')); return cache; } catch (e) { dropdown.innerHTML = '<div class="orm-head">OpenRouter Models</div><div class="orm-err">Failed to load models. Check network/CORS.</div>'; positionDropdown(); throw e; } }
    function isOpenRouter(){ const svc = ((document.getElementById('service_input')||{}).value||'').toLowerCase(); if (svc !== 'openrouter') return false; const url = (document.querySelector('input[name="url"]').value||''); const driver = (document.querySelector('input[name="driver"]').value||''); return url.includes('openrouter.ai') || /openrouter/.test(driver); }
    async function maybeOpenDropdown(){ if (!isOpenRouter()) return; try { const models = await loadModels(); renderList(models, modelInput.value); } catch (_e) {} }
    modelInput.addEventListener('focus', () => { if (isOpenRouter()) maybeOpenDropdown(); });
    modelInput.addEventListener('click', () => { if (isOpenRouter()) maybeOpenDropdown(); });
    modelInput.addEventListener('input', () => { if (isOpen && cache) renderList(cache, modelInput.value); });
    modelInput.addEventListener('blur', () => { setTimeout(closeDropdown, 120); });
    window.addEventListener('resize', () => { if (isOpen) positionDropdown(); });
    window.addEventListener('scroll', () => { if (isOpen) positionDropdown(); }, true);
    document.addEventListener('keydown', (e)=>{ if (e.key==='Escape') closeDropdown(); });
    document.querySelector('input[name="url"]').addEventListener('change', () => closeDropdown());
    document.querySelector('input[name="driver"]').addEventListener('change', () => closeDropdown());

    // Info box under Model input (embedded editor)
    (function(){
        const infoId = 'orm_model_info';
        let infoEl = document.getElementById(infoId);
        if (!infoEl){
            infoEl = document.createElement('div');
            infoEl.id = infoId;
            infoEl.className = 'orm-info-box';
            infoEl.style.display = 'none';
            const anchor = modelInput;
            if (anchor && anchor.parentNode) anchor.parentNode.insertBefore(infoEl, anchor.nextSibling);
        }
        function ctxOf(m){ return (m.top_provider && m.top_provider.context_length) || m.context_length || ''; }
        function renderInfo(m){ const prompt = formatPrice(m.pricing && m.pricing.prompt); const completion = formatPrice(m.pricing && m.pricing.completion); const ctx = formatContext(ctxOf(m)); return `<div style=\"font-weight:600; margin-bottom:4px;\">OpenRouter model info</div><div class=\"orm-muted\" style=\"font-size:12px;\">Pricing (per 1M tokens): input ${prompt} • output ${completion}${ctx? ` • context ${ctx}`: ''}</div>`; }
        async function update(){
            const val = (modelInput.value||'').trim();
            const url = (document.querySelector('input[name=\"url\"]').value||'');
            const driver = (document.querySelector('input[name=\"driver\"]').value||'');
            const svc = ((document.getElementById('service_input')||{}).value||'').toLowerCase();
            const isOR = (svc === 'openrouter') && (url.includes('openrouter.ai') || /openrouter/.test(driver));
            if (!val || !isOR){ infoEl.style.display='none'; infoEl.innerHTML=''; return; }
            try {
                if (!cache){ await loadModels(); }
                const m = (cache||[]).find(x => String(x.id||'') === val);
                if (m){ infoEl.innerHTML = renderInfo(m); infoEl.style.display='block'; }
                else { infoEl.innerHTML = `<div class=\"orm-muted\" style=\"font-size:12px;\">No model info available</div>`; infoEl.style.display='block'; }
            } catch(_e){ infoEl.style.display='none'; }
        }
        modelInput.addEventListener('change', update);
        modelInput.addEventListener('input', update);
        // removed on-load trigger
        document.querySelector('input[name="url"]').addEventListener('change', update);
        document.querySelector('input[name="driver"]').addEventListener('change', update);
    })();
})();
// Providers dropdown
(function(){
    const providerInput = document.querySelector('input[name="provider"]');
    const modelInput = document.querySelector('input[name="model"]');
    if (!providerInput || !modelInput) return;
    let providersCache = null, dropdown = null, isOpen = false;
    function ensureDropdown(){ if (dropdown) return dropdown; dropdown = document.createElement('div'); dropdown.className = 'orm-dropdown'; document.body.appendChild(dropdown); dropdown.addEventListener('mousedown', (e)=>{ e.preventDefault(); }); return dropdown; }
    function positionDropdown(){ const rect = providerInput.getBoundingClientRect(); const style = dropdown.style; style.left = (rect.left + window.scrollX) + 'px'; style.top = (rect.bottom + window.scrollY + 4) + 'px'; style.width = rect.width + 'px'; style.display = 'block'; isOpen = true; }
    function closeDropdown(){ if (!dropdown) return; dropdown.style.display = 'none'; isOpen = false; }
    function escapeHtml(s){ return (s==null? '': String(s)).replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])); }
    function encodeHtmlAttr(s){ return (s==null? '': String(s)).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
    function renderList(items, filterText, relevantSlugs){ ensureDropdown(); const q = (filterText || '').toLowerCase(); const slugAllow = Array.isArray(relevantSlugs) ? new Set(relevantSlugs.filter(Boolean)) : null; const filteredByRelevance = (items || []).filter(p => { if (slugAllow && slugAllow.size>0) return slugAllow.has((p.slug||'')); return true; }); const list = filteredByRelevance.filter(p => { if (!q) return true; const slug = (p.slug || '').toLowerCase(); const name = (p.name || '').toLowerCase(); return slug.includes(q) || name.includes(q); }); let html = ''; html += '<div class="orm-head">OpenRouter Providers</div>'; html += '<div class="orm-note">Click to select. Value set to provider slug.</div>'; if (list.length === 0){ const hasRelevantFilter = (slugAllow && slugAllow.size>0); const note = hasRelevantFilter ? 'No relevant providers for the selected model.' : 'No matches'; html += `<div class="orm-muted" style="padding:8px 10px;">${note}</div>`; } else { list.forEach(p => { const name = p.name ? ` — ${escapeHtml(p.name)}` : ''; html += `<div class=\"orm-item\" data-slug=\"${encodeHtmlAttr(p.slug)}\" title=\"${encodeHtmlAttr(p.name||p.slug)}\"><div>${escapeHtml(p.slug)}${name}</div><div class=\"orm-muted\" style=\"font-size:12px; margin-top:2px;\">${p.privacy_policy_url? 'Privacy: '+escapeHtml(p.privacy_policy_url): ''}${p.terms_of_service_url? (p.privacy_policy_url? ' • ': '')+'TOS: '+escapeHtml(p.terms_of_service_url): ''}</div></div>`; }); }
        dropdown.innerHTML = html;
        dropdown.querySelectorAll('.orm-item').forEach(el => { el.addEventListener('click', () => { const slug = el.getAttribute('data-slug') || ''; providerInput.value = slug; try { providerInput.dispatchEvent(new Event('input', { bubbles: true })); } catch (_) {} try { providerInput.dispatchEvent(new Event('change', { bubbles: true })); } catch (_) {} closeDropdown(); }); });
        positionDropdown(); }
    async function loadProviders(){ if (providersCache) return providersCache; ensureDropdown(); dropdown.innerHTML = '<div class="orm-head">OpenRouter Providers</div><div class="orm-note">Loading…</div>'; positionDropdown(); try { const res = await fetch('https://openrouter.ai/api/v1/providers'); if (!res.ok) throw new Error('HTTP '+res.status); const json = await res.json(); const data = Array.isArray(json && json.data) ? json.data : []; providersCache = data.map(p => ({ name: p.name || '', slug: p.slug || '', privacy_policy_url: p.privacy_policy_url || '', terms_of_service_url: p.terms_of_service_url || '', status_page_url: p.status_page_url || '' })).filter(p => p.slug); providersCache.sort((a,b)=> (a.slug||'').localeCompare(b.slug||'')); return providersCache; } catch (e) { dropdown.innerHTML = '<div class="orm-head">OpenRouter Providers</div><div class="orm-err">Failed to load providers. Check network/CORS.</div>'; positionDropdown(); throw e; } }
    function getRelevantProviderSlugs(){ const val = (modelInput.value || '').trim(); const ix = val.indexOf('/'); if (ix > 0){ const slug = val.slice(0, ix).trim(); if (slug) return [slug]; } return []; }
    function isOpenRouter(){ const svc = ((document.getElementById('service_input')||{}).value||'').toLowerCase(); if (svc !== 'openrouter') return false; const url = (document.querySelector('input[name="url"]').value||''); const driver = (document.querySelector('input[name="driver"]').value||''); return url.includes('openrouter.ai') || /openrouter/.test(driver); }
    function isOpenRouter(){ const svc = ((document.getElementById('service_input')||{}).value||'').toLowerCase(); if (svc !== 'openrouter') return false; const url = (document.querySelector('input[name="url"]').value||''); const driver = (document.querySelector('input[name="driver"]').value||''); return url.includes('openrouter.ai') || /openrouter/.test(driver); }
    async function maybeOpenDropdown(){ if (!isOpenRouter()) return; try { const items = await loadProviders(); renderList(items, providerInput.value, getRelevantProviderSlugs()); } catch (_e) {} }
    function extractProviderSlugFromModel(val){ if (!val) return ''; const s = String(val); const ix = s.indexOf('/'); if (ix <= 0) return ''; return s.slice(0, ix).trim(); }
    function maybeAutofillProvider(){ const url = (document.querySelector('input[name="url"]').value||''); const driver = (document.querySelector('input[name="driver"]').value||''); if (!(url.includes('openrouter.ai') || /openrouter/.test(driver))) return; const slug = extractProviderSlugFromModel(modelInput.value); if (!slug) return; providerInput.value = slug; try { providerInput.dispatchEvent(new Event('input', { bubbles: true })); } catch (_) {} try { providerInput.dispatchEvent(new Event('change', { bubbles: true })); } catch (_) {} }
    providerInput.addEventListener('focus', () => { if (isOpenRouter()) maybeOpenDropdown(); });
    providerInput.addEventListener('click', () => { if (isOpenRouter()) maybeOpenDropdown(); });
    providerInput.addEventListener('input', () => { if (isOpen && providersCache) renderList(providersCache, providerInput.value, getRelevantProviderSlugs()); });
    providerInput.addEventListener('blur', () => { setTimeout(closeDropdown, 120); });
    window.addEventListener('resize', () => { if (isOpen) positionDropdown(); });
    window.addEventListener('scroll', () => { if (isOpen) positionDropdown(); }, true);
    document.addEventListener('keydown', (e)=>{ if (e.key==='Escape') closeDropdown(); });
    document.querySelector('input[name="url"]').addEventListener('change', () => closeDropdown());
    document.querySelector('input[name="driver"]').addEventListener('change', () => closeDropdown());

    // Hook model changes
    function clearProviderIfOpenRouter(){
        const url = (document.querySelector('input[name="url"]').value||'');
        const driver = (document.querySelector('input[name="driver"]').value||'');
        if (url.includes('openrouter.ai') || /openrouter/.test(driver)){
            providerInput.value = '';
            try { providerInput.dispatchEvent(new Event('input', { bubbles:true })); } catch(_){ }
            try { providerInput.dispatchEvent(new Event('change', { bubbles:true })); } catch(_){ }
        }
    }
    modelInput.addEventListener('change', () => { clearProviderIfOpenRouter(); maybeAutofillProvider(); if (isOpen && providersCache) renderList(providersCache, providerInput.value, getRelevantProviderSlugs()); });
    modelInput.addEventListener('input', () => { clearProviderIfOpenRouter(); maybeAutofillProvider(); if (isOpen && providersCache) renderList(providersCache, providerInput.value, getRelevantProviderSlugs()); });
})();

// Collapsible section handlers for main form (must run after DOM is loaded)
(function(){
    const collapsibleHeaders = document.querySelectorAll('.collapsible-header');

    collapsibleHeaders.forEach(header => {
        const targetId = header.getAttribute('data-target');
        const content = document.getElementById(targetId);
        const arrow = header.querySelector('.collapse-arrow');

        if (!content || !arrow) return;

        // Restore state from localStorage
        const storageKey = 'llm_collapse_' + targetId;
        const isCollapsed = localStorage.getItem(storageKey) === 'true';

        if (isCollapsed) {
            content.style.display = 'none';
            arrow.textContent = '▶';
        } else {
            content.style.display = 'block';
            arrow.textContent = '▼';
        }

        // Add click handler
        header.addEventListener('click', function() {
            const isCurrentlyVisible = content.style.display !== 'none';

            if (isCurrentlyVisible) {
                content.style.display = 'none';
                arrow.textContent = '▶';
                localStorage.setItem(storageKey, 'true');
            } else {
                content.style.display = 'block';
                arrow.textContent = '▼';
                localStorage.setItem(storageKey, 'false');
            }
        });
    });
})();
</script>

<!-- list/grid moved to left pane -->

<!-- Advanced: Raw Metadata JSON Editor -->
<details class="collapsible" style="margin-top:16px;">
    <summary style="cursor:pointer; font-weight:600; padding:8px 0; color:#e9efff;">
        ⚙️ Advanced: Raw Metadata JSON Editor
    </summary>
    <div style="padding:8px 0; color:#bbb; font-size:12px; margin-bottom:8px;">
        Edit metadata as raw JSON. Changes here will override individual fields above. Use with caution.
    </div>
    <div id="metadata"></div>
</details>

<?php
 // Provides a JSON editor for metadata field and form consolidation function (only needed if metadata field is present)
 include(__DIR__."/tmpl/metadata_json_editor.php");
 ?>

<script>
// Sync On/Off labels for the reasoning toggle checkboxes
(function(){
    const toggleIds = ['toggle_thinking', 'toggle_thinking_modal'];
    toggleIds.forEach(id => {
        const cb = document.getElementById(id);
        if (!cb) return;
        const label = cb.closest('label');
        const span = label ? label.querySelector('.toggle-text') : null;
        function sync(){ if (span) span.textContent = cb.checked ? 'On' : 'Off'; }
        cb.addEventListener('change', sync);
        sync(); // Initial sync
    });
})();

// Override consolidation() to handle metadata fields for llm_connectors
window.consolidation = function() {
    console.log('[Consolidation] ===== CALLED =====');
    console.log('[Consolidation] Called from:', new Error().stack.split('\n')[1]);

    // Debug: Find ALL elements with IDs containing "toggle" or "thinking"
    const allElementsWithIds = document.querySelectorAll('[id]');
    const relevantIds = [];
    allElementsWithIds.forEach(el => {
        if (el.id.includes('toggle') || el.id.includes('thinking')) {
            relevantIds.push({
                id: el.id,
                tag: el.tagName,
                type: el.type,
                visible: el.offsetParent !== null,
                inForm: !!el.form
            });
        }
    });
    console.log('[Consolidation] Elements with toggle/thinking IDs:', relevantIds.length);
    relevantIds.forEach((info, i) => {
        console.log(`  [${i}] id="${info.id}" tag=${info.tag} type=${info.type} visible=${info.visible} inForm=${info.inForm}`);
    });

    // Try to find toggle_thinking
    const toggleThinkingEl = document.getElementById('toggle_thinking');
    console.log('[Consolidation] getElementById("toggle_thinking"):', toggleThinkingEl);

    if (!toggleThinkingEl) {
        console.log('[Consolidation] ERROR: No toggle_thinking field found');
        console.log('[Consolidation] Trying querySelectorAll instead...');
        const allToggleThinking = document.querySelectorAll('[id="toggle_thinking"]');
        console.log('[Consolidation] querySelectorAll found:', allToggleThinking.length, 'elements');
        allToggleThinking.forEach((el, i) => {
            console.log(`  [${i}]:`, el, 'form:', el.form, 'visible:', el.offsetParent !== null);
        });
        return true; // Can't continue without this field
    }

    // Get the form that contains the toggle_thinking field
    const form = toggleThinkingEl.form;
    if (!form) {
        console.log('[Consolidation] ERROR: toggle_thinking field has no parent form');
        return true;
    }
    console.log('[Consolidation] Found correct form via toggle_thinking field');

    // Find the metadata textarea in THIS form
    const metadataTextarea = form.querySelector('textarea[name="metadata"]');
    if (!metadataTextarea) {
        console.log('[Consolidation] ERROR: No metadata textarea in form, cannot save');
        return true;
    }
    console.log('[Consolidation] Found metadata textarea');

    try {
        // Parse existing metadata from textarea
        let metadata = {};
        try {
            const metaStr = metadataTextarea.value || '{}';
            metadata = JSON.parse(metaStr);
            console.log('[Consolidation] Starting with metadata:', metadata);
        } catch (_e) {
            metadata = {};
            console.log('[Consolidation] Failed to parse metadata, starting fresh');
        }

        // Collect other thinking toggle fields (toggle_thinking already found at start)
        const thinkingTokensEl = document.getElementById('thinking_tokens') || document.getElementById('thinking_tokens_modal');
        const effortLevelEl = document.getElementById('effort_level') || document.getElementById('effort_level_modal');

        // Add toggle_thinking (we already have toggleThinkingEl from above)
        metadata.toggle_thinking = toggleThinkingEl.checked;
        console.log('[Consolidation] Set toggle_thinking =', toggleThinkingEl.checked);

        // Add thinking_tokens (only if not empty)
        if (thinkingTokensEl) {
            const val = thinkingTokensEl.value.trim();
            if (val !== '') {
                metadata.thinking_tokens = parseInt(val, 10);
                console.log('[Consolidation] Set thinking_tokens =', parseInt(val, 10));
            } else {
                delete metadata.thinking_tokens;
                console.log('[Consolidation] Removed thinking_tokens (empty)');
            }
        }

        // Add effort_level (only if not empty)
        if (effortLevelEl) {
            const val = effortLevelEl.value.trim();
            if (val !== '') {
                metadata.effort_level = val;
                console.log('[Consolidation] Set effort_level =', val);
            } else {
                delete metadata.effort_level;
                console.log('[Consolidation] Removed effort_level (empty)');
            }
        }

        // Collect ALL OTHER metadata[...] fields
        const metadataInputs = form.querySelectorAll('[name^="metadata["]');
        metadataInputs.forEach(inp => {
            const match = inp.name.match(/^metadata\[([^\]]+)\]$/);
            if (!match) return;
            const key = match[1];

            // Skip the 3 thinking toggle fields we already handled
            if (key === 'toggle_thinking' || key === 'thinking_tokens' || key === 'effort_level') return;

            // Handle other metadata fields
            if (inp.type === 'checkbox') {
                if (inp.checked && inp.value !== '0') {
                    metadata[key] = inp.value === '1' || inp.value === 'true' ? true : inp.value;
                }
            } else if (inp.type === 'number') {
                const val = inp.value.trim();
                if (val !== '') {
                    metadata[key] = parseFloat(val);
                }
            } else if (inp.tagName.toLowerCase() === 'select' || inp.type === 'text') {
                const val = inp.value.trim();
                if (val !== '') {
                    metadata[key] = val;
                }
            }
        });

        // Update metadata textarea with final JSON
        metadataTextarea.value = JSON.stringify(metadata);
        console.log('[Consolidation] Final metadata JSON:', metadataTextarea.value);

        // CRITICAL: Remove name attributes from all metadata[...] fields
        // so only the textarea submits to PHP
        let removedCount = 0;
        metadataInputs.forEach(inp => {
            if (inp.name && inp.name.startsWith('metadata[')) {
                console.log('[Consolidation] Removing name from:', inp.name);
                inp.removeAttribute('name');
                removedCount++;
            }
        });
        console.log('[Consolidation] Removed', removedCount, 'name attributes');

        return true;
    } catch (err) {
        console.error('[Consolidation] ERROR:', err);
        return true; // Let it submit anyway
    }
};
</script>

</main>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
// Inject toast notice if present
if ($noticeMsg !== '') {
    $injection = "<script>(function(){var t=document.getElementById('toast');if(!t)return;var m=t.querySelector('.message');if(m)m.textContent=" . json_encode($noticeMsg, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . ";try{t.className='toast-notification show error';t.style.opacity='1';setTimeout(function(){t.className='toast-notification';t.style.opacity='0';},3000);}catch(_){t.style.display='block';setTimeout(function(){t.style.display='none';},3000);} })();</script>";
    $buffer = str_replace('</main>', $injection . '</main>', $buffer);
}
echo $buffer;
?>

