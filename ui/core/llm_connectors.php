<?php
$enginePath = __DIR__ . "/../../";

require_once($enginePath . "conf/conf.php");
require_once($enginePath . "lib/model_dynmodel.php");
require_once($enginePath . "lib/{$GLOBALS["DBDRIVER"]}.class.php");
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
$TITLE = "🧠 CHIM - LLM Connectors";
ob_start();
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/head.html");
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">

<main>
    <div id="toast" class="toast-notification">
        <span class="message"></span>
    </div>

<?php
$GLOBALS["db"] = new sql();
$llm = new LLMConnector();

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
    .orm-dropdown { position:absolute; z-index: 9999; max-height: 360px; overflow:auto; background:#111; border:1px solid rgba(138,155,182,0.3); border-radius:8px; box-shadow: 0 6px 18px rgba(0,0,0,0.35); display:none; }
    .orm-item { padding:8px 10px; cursor:pointer; border-bottom:1px solid rgba(138,155,182,0.15); }
    .orm-item:last-child { border-bottom:none; }
    .orm-item:hover { background:#1a1f29; }
    .orm-head { padding:8px 10px; font-weight:bold; position:sticky; top:0; background:#0c0f14; border-bottom:1px solid rgba(138,155,182,0.3); }
    .orm-note { padding:6px 10px; font-size:12px; color:#97a6ba; border-bottom:1px dashed rgba(138,155,182,0.25); background:#0c0f14; }
    .orm-muted { color:#97a6ba; }
    .orm-err { color:#ff6b6b; padding:8px 10px; }
    </style>
    <script>
    // Define consolidation() if not present (embedded partial doesn't include metadata editor)
    if (typeof window.consolidation !== 'function') {
        window.consolidation = function(){ return true; };
    }
    </script>
    <form method="post" onsubmit='return consolidation()' style='<?= $editItem!=null?"":"display:none"?>'>
        <?php if ($editItem): ?>
            <input type="hidden" name="id" value="<?= $editItem["id"] ?>">
        <?php endif; ?>
        <input type="hidden" name="partial" value="editor">
        <div class="two-col-llm">
            <div>
                <div class="top-actions" style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">
                    <?php if ($editItem): ?>
                        <button type="submit" name="save" class="btn-save">Save</button>
                        <button type="button" id="btn_test_connector" class="btn-save">Test</button>
                    <?php else: ?>
                        <button type="submit" name="create" class="btn-save">Create</button>
                    <?php endif; ?>
                </div>
                <label for='label'>Name</label><br>
                <input type="text" name="label" value="<?= htmlspecialchars($editItem["label"] ?? "") ?>"><br>

                <label>Service</label>
                <div class="service-picker">
                    <div class="service-icons">
                        <img src="<?= $webRoot; ?>/ui/images/core/icons/openrouter.jpg" alt="OpenRouter" class="service-icon" data-service="openrouter" />
                        <img src="<?= $webRoot; ?>/ui/images/core/icons/openai.jpg" alt="OpenAI" class="service-icon" data-service="openai" />
                        <img src="<?= $webRoot; ?>/ui/images/core/icons/google.jpg" alt="Google" class="service-icon" data-service="google" />
                        <img src="<?= $webRoot; ?>/ui/images/core/icons/kobold.jpg" alt="Kobold" class="service-icon" data-service="kobold" />
                        <img src="<?= $webRoot; ?>/ui/images/core/icons/custom.jpg" alt="Custom" class="service-icon" data-service="custom" />
                    </div>
                </div>

                <label for='url'>URL</label><br>
                <input type="text" name="url" value="<?= htmlspecialchars($editItem["url"] ?? "") ?>"><br>

                <label for='model'>Model</label><br>
                <input type="text" name="model" value="<?= htmlspecialchars($editItem["model"] ?? "") ?>"><br>

                <div id="provider_row">
                    <label for='provider'>Provider</label><br>
                    <input type="text" name="provider" value="<?= htmlspecialchars($editItem["provider"] ?? "") ?>"><br>
                </div>

                <div id="driver_row" style="display:none;">
                    <label for='driver'>Driver</label><br>
                    <input type="text" id="driver_input" name="driver" value="<?= htmlspecialchars($editItem["driver"] ?? "") ?>"><br>
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
                <div id="reasoning_row">
                    <label><span class='tip-label' data-tip='Use a reasoning-capable model. May be slower and cost more; can improve complex tasks.'>Reasoning Model</span></label><br>
                    <input type="hidden" name="reasoning_model" value="0">
                    <label><input type="checkbox" name="reasoning_model" value="1" <?= isset($editItem["reasoning_model"]) && $editItem["reasoning_model"] == 1 ? "checked" : "" ?>> <span class="toggle-text">On</span></label>
                </div>
                <div id="json_toggles" style="margin-top:8px;">
                    <label><span class='tip-label' data-tip='Force responses to be strict JSON. Non‑JSON output may be rejected or auto‑retried.'>Enforce JSON</span></label><br>
                    <input type="hidden" name="enforce_json" value="0">
                    <label><input type="checkbox" name="enforce_json" value="1" <?= isset($editItem["enforce_json"]) && $editItem["enforce_json"] == 1 ? "checked" : "" ?>> <span class="toggle-text">On</span></label>
                    <div style="height:6px;"></div>
                    <label><span class='tip-label' data-tip='Guide/validate the JSON structure with a schema. Best used with Enforce JSON.'>JSON Schema</span></label><br>
                    <input type="hidden" name="json_schema" value="0">
                    <label><input type="checkbox" name="json_schema" value="1" <?= isset($editItem["json_schema"]) && $editItem["json_schema"] == 1 ? "checked" : "" ?>> <span class="toggle-text">On</span></label>
                    <div style="height:6px;"></div>
                    <label><span class='tip-label' data-tip='Send a starter JSON object to steer field names/shape in the response.'>Prefill JSON</span></label><br>
                    <input type="hidden" name="prefill_json" value="0">
                    <label><input type="checkbox" name="prefill_json" value="1" <?= isset($editItem["prefill_json"]) && $editItem["prefill_json"] == 1 ? "checked" : "" ?>> <span class="toggle-text">On</span></label>
                </div>
            </div>
            <div>
                <?php
                $tipMaxTokens = 'Maximum tokens the model can generate for a response. Higher = longer answers; may increase cost/latency. [>= 0]';
                echo "<label for='max_tokens' style='margin-top:10px; display:block;'><span class='tip-label' data-tip='" . htmlspecialchars($tipMaxTokens, ENT_QUOTES) . "'>Max Tokens</span></label>";
                echo "<input type='number' name='max_tokens' value='" . htmlspecialchars($editItem["max_tokens"] ?? "") . "' min='0' step='1'>";
                $ranges = [
                    'temperature' => ['min'=>0,'max'=>2,'step'=>0.01],
                    'presence_penalty' => ['min'=>-2,'max'=>2,'step'=>0.01],
                    'frequency_penalty' => ['min'=>-2,'max'=>2,'step'=>0.01],
                    'repetition_penalty' => ['min'=>0,'max'=>2,'step'=>0.01],
                    'top_p' => ['min'=>0,'max'=>1,'step'=>0.01],
                    'top_k' => ['min'=>0,'max'=>100,'step'=>1],
                    'min_p' => ['min'=>0,'max'=>1,'step'=>0.01],
                    'top_a' => ['min'=>0,'max'=>1,'step'=>0.01],
                ];
                $displayDefaults = [
                    'temperature' => 1.05,
                    'presence_penalty' => 0,
                    'frequency_penalty' => 0,
                    'repetition_penalty' => 1,
                    'top_p' => 0.7,
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
                echo "<div class='kv-grid'>";
                foreach ($ranges as $field => $conf) {
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
                    echo "<div style='margin-top:6px;'><input type='number' class='inline-num' id='{$nid}' name='{$field}' min='{$min}' max='{$max}' step='{$step}' value='{$val}' oninput=\"llmClamp('{$rid}','{$nid}',{$min},{$max})\"></div>";
                    echo "</div>";
                }
                echo "</div>";
                ?>
            </div>
        </div>
    </form>
    <div id="toast" class="toast-notification" style="display:none"><span class="message"></span></div>
    <script>
    // Sync On/Off labels for checkboxes
    (function(){
        const names = ['reasoning_model','enforce_json','json_schema','prefill_json'];
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
            kobold: 'http://127.0.0.1:5001',
            custom: ''
        };
        const providerRow = document.getElementById('provider_row');
        const urlInput = document.querySelector('input[name="url"]');
        const driverInput = document.querySelector('input[name="driver"]');
        const driverRow = document.getElementById('driver_row');
        const apiBadgeSelect = document.getElementById('api_badge_id');
        const icons = document.querySelectorAll('.service-icon');
        const apiKeyRow = document.getElementById('api_key_row');
        function setActive(service){ icons.forEach(ic=>{ if (ic.dataset.service === service) ic.classList.add('active'); else ic.classList.remove('active'); }); }
        const driverDefaults = { openrouter: 'openrouterjson', openai: 'openaijson', google: 'google_openaijson', kobold: 'koboldcppjson', custom: '' };
        const apiBadgeLabelMatch = { openrouter: ['openrouter'], openai: ['openai'], google: ['google'] };
        function syncApiBadge(service){ if (!apiBadgeSelect) return; if (service === 'kobold') { apiBadgeSelect.value = ''; return; } const targets = (apiBadgeLabelMatch[service] || []).map(s => s.toLowerCase()); if (targets.length === 0) return; let selectedVal = ''; for (let i = 0; i < apiBadgeSelect.options.length; i++) { const opt = apiBadgeSelect.options[i]; const label = (opt.textContent || opt.innerText || '').toLowerCase(); if (targets.some(t => label.includes(t))) { selectedVal = opt.value; break; } } if (selectedVal !== '') apiBadgeSelect.value = selectedVal; else apiBadgeSelect.value = ''; }
        function applyService(service){ if (defaults[service]) urlInput.value = defaults[service]; providerRow.style.display = (service === 'openrouter') ? '' : 'none'; if (driverInput && driverDefaults[service]) driverInput.value = driverDefaults[service]; syncApiBadge(service); setActive(service); if (apiKeyRow) apiKeyRow.style.display = (service === 'kobold') ? 'none' : ''; if (driverRow) driverRow.style.display = (service === 'custom') ? '' : 'none'; }
        (function init(){ let service = 'openrouter'; const val = (urlInput && urlInput.value) ? urlInput.value : ''; if (val.includes('openai.com')) service = 'openai'; else if (val.includes('generativelanguage.googleapis.com')) service = 'google'; else if (val.includes('127.0.0.1') || val.includes('localhost')) service = 'kobold'; else if (driverInput && /openai/.test(String(driverInput.value||''))) service = 'openai'; else if (driverInput && /google/.test(String(driverInput.value||''))) service = 'google'; else if (driverInput && /kobold/.test(String(driverInput.value||''))) service = 'kobold'; else service = 'custom'; applyService(service); })();
        icons.forEach(ic=>{ ic.addEventListener('click', ()=> applyService(ic.dataset.service)); });
    })();
    function llmClamp(rangeId, numberId, min, max){ const r = document.getElementById(rangeId); const n = document.getElementById(numberId); if (!r || !n) return; let v = parseFloat(n.value); if (isNaN(v)) v = min; if (v < min) v = min; if (v > max) v = max; n.value = v; r.value = v; }
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
        function positionDropdown(){ const rect = modelInput.getBoundingClientRect(); const style = dropdown.style; style.left = (rect.left + window.scrollX) + 'px'; style.top = (rect.bottom + window.scrollY + 4) + 'px'; style.width = rect.width + 'px'; style.display = 'block'; isOpen = true; }
        function closeDropdown(){ if (!dropdown) return; dropdown.style.display = 'none'; isOpen = false; }
        function formatPrice(n){ if (n === undefined || n === null || n === '' || isNaN(parseFloat(n))) return 'N/A'; const perTok = parseFloat(n); const perK = perTok * 1000.0; return '$' + perK.toFixed(4) + ' / 1K tok'; }
        function escapeHtml(s){ return (s==null? '': String(s)).replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])); }
        function encodeHtmlAttr(s){ return (s==null? '': String(s)).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
        function renderList(models, filterText){
            ensureDropdown();
            const q = (filterText || '').toLowerCase();
            const list = (models || []).filter(m => { if (!q) return true; const id = (m.id || '').toLowerCase(); const name = (m.name || '').toLowerCase(); return id.includes(q) || name.includes(q); });
            let html = '';
            html += '<div class="orm-head">OpenRouter Models</div>';
            html += '<div class="orm-note">Click to select. Pricing shown per 1K tokens (prompt/completion).</div>';
            if (list.length === 0){ html += '<div class="orm-muted" style="padding:8px 10px;">No matches</div>'; }
            else { list.forEach(m => { const prompt = formatPrice(m.pricing && m.pricing.prompt); const completion = formatPrice(m.pricing && m.pricing.completion); const ctx = (m.top_provider && m.top_provider.context_length) || m.context_length || ''; const name = m.name ? ' — ' + escapeHtml(m.name) : ''; const line = `${escapeHtml(m.id)}${name}`; const sub = `Pricing: ${prompt} • ${completion}` + (ctx? ` • ctx ${ctx}` : ''); html += `<div class="orm-item" data-id="${encodeHtmlAttr(m.id)}" title="${encodeHtmlAttr(m.description||m.name||m.id)}"><div>${line}</div><div class=\"orm-muted\" style=\"font-size:12px; margin-top:2px;\">${sub}</div></div>`; }); }
            dropdown.innerHTML = html;
            dropdown.querySelectorAll('.orm-item').forEach(el => { el.addEventListener('click', () => { const id = el.getAttribute('data-id') || ''; modelInput.value = id; try { modelInput.dispatchEvent(new Event('input', { bubbles: true })); } catch (_) {} try { modelInput.dispatchEvent(new Event('change', { bubbles: true })); } catch (_) {} closeDropdown(); }); });
            positionDropdown();
        }
        async function loadModels(){ if (cache) return cache; ensureDropdown(); dropdown.innerHTML = '<div class="orm-head">OpenRouter Models</div><div class="orm-note">Loading…</div>'; positionDropdown(); try { const res = await fetch('https://openrouter.ai/api/v1/models'); if (!res.ok) throw new Error('HTTP '+res.status); const json = await res.json(); const data = Array.isArray(json && json.data) ? json.data : []; cache = data.map(m => ({ id: m.id || m.canonical_slug || '', name: m.name || '', pricing: m.pricing || {}, top_provider: m.top_provider || {}, context_length: m.context_length || undefined, description: m.description || '' })); cache.sort((a,b)=> (a.name||'').localeCompare(b.name||'') || (a.id||'').localeCompare(b.id||'')); return cache; } catch (e) { dropdown.innerHTML = '<div class="orm-head">OpenRouter Models</div><div class="orm-err">Failed to load models. Check network/CORS.</div>'; positionDropdown(); throw e; } }
        function isOpenRouter(){ const url = (document.querySelector('input[name="url"]').value||''); const driver = (document.querySelector('input[name="driver"]').value||''); return url.includes('openrouter.ai') || /openrouter/.test(driver); }
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
        function isOpenRouter(){ const url = (document.querySelector('input[name="url"]').value||''); const driver = (document.querySelector('input[name="driver"]').value||''); return url.includes('openrouter.ai') || /openrouter/.test(driver); }
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
        modelInput.addEventListener('change', () => { maybeAutofillProvider(); if (isOpen && providersCache) renderList(providersCache, providerInput.value, getRelevantProviderSlugs()); });
        modelInput.addEventListener('input', () => { maybeAutofillProvider(); if (isOpen && providersCache) renderList(providersCache, providerInput.value, getRelevantProviderSlugs()); });
    })();
    </script>
    <?php
    exit;
}

// Handle Create
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create"])) {
    $llm->create($_POST);
    header("Location: llm_connectors.php");
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
    $llm->delete($_GET["delete"]);
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

<?php /* Title moved into left panel; edit header removed */ ?>

<div class="llm-layout">
    <div class="llm-left">
        <h1 class="llm-title">LLM Connectors</h1>
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
                                <a class="btn-danger" href="?delete=${r.id}" onclick="return confirm('Are you sure you want to delete this connector?');">Delete</a>
                                <a class="btn-save" href="?clone=${r.id}">Clone</a>
                            </div>
                        </div>`;
                });
                list.innerHTML = html || '<div class="conn-li"><em>No connectors match filters.</em></div>';
                // Make rows clickable to open editor, but ignore clicks on action links
                list.querySelectorAll('.conn-li').forEach(li => {
                    li.addEventListener('click', (ev) => {
                        if (ev.target.closest('a')) return;
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
<style>
.wide-centered { max-width: 1300px; margin: 0 auto; }
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
/* Split layout: list left, editor right */
.llm-layout { display:grid; grid-template-columns: minmax(240px, 340px) 1fr; gap:16px; align-items:start; }
/* Keep two-column layout even on narrower screens so half-screen works */
@media (max-width: 1100px) { .llm-layout { grid-template-columns: minmax(220px, 300px) 1fr; } }
@media (max-width: 860px) { .llm-layout { grid-template-columns: minmax(200px, 260px) 1fr; } }
.llm-left { position: sticky; top: 16px; align-self:start; max-height: calc(100vh - 110px); overflow:auto; padding-right:4px; }
.llm-left .llm-title { margin: 6px 0 10px 4px; font-size: 20px; color: #e9efff; }
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
/* Collapsible block for Metadata */
.collapsible { margin-top: 8px; border:1px solid rgba(138,155,182,0.35); border-radius:10px; background:#0d1117; }
.collapsible-header { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:10px; cursor:pointer; user-select:none; color:#e9efff; font-weight:600; }
.collapsible-header::after { content:'\25BE'; font-size:12px; color:#9fb1c9; transition: transform .12s ease; }
.collapsible[open] .collapsible-header { border-bottom:1px solid rgba(138,155,182,0.35); }
.collapsible[open] .collapsible-header::after { transform: rotate(180deg); }
.collapsible-content { padding:10px; }
</style>
<?php if (!$editItem): ?>
    <div class="connector-placeholder" style="border:1px dashed rgba(138,155,182,0.4); background:#0d1117; color:#9fb1c9; border-radius:10px; padding:18px; margin-bottom:10px;">
        <div style="font-weight:600; color:#e9efff; margin-bottom:6px;">No connector selected</div>
        <div>Select a connector from the list on the left to view and edit its settings.</div>
    </div>
<?php endif; ?>
<form method="post" onsubmit='return consolidation()' style='<?= $editItem!=null?"":"display:none"?>'>
    <?php if ($editItem): ?>
        <input type="hidden" name="id" value="<?= $editItem["id"] ?>">
    <?php endif; ?>

    <div class="two-col-llm">
        <div>
            <div class="top-actions" style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">
                <?php if ($editItem): ?>
                    <button type="submit" name="save" class="btn-save">Save</button>
                    <button type="button" id="btn_test_connector" class="btn-save">Test</button>
                <?php else: ?>
                    <button type="submit" name="create" class="btn-save">Create</button>
                <?php endif; ?>
            </div>
            <label for='label'>Name</label><br>
            <input type="text" name="label" value="<?= htmlspecialchars($editItem["label"] ?? "") ?>"><br>

            <label>Service</label>
            <div class="service-picker">
                <div class="service-icons">
                    <img src="<?= $webRoot; ?>/ui/images/core/icons/openrouter.jpg" alt="OpenRouter" class="service-icon" data-service="openrouter" />
                    <img src="<?= $webRoot; ?>/ui/images/core/icons/openai.jpg" alt="OpenAI" class="service-icon" data-service="openai" />
                    <img src="<?= $webRoot; ?>/ui/images/core/icons/google.jpg" alt="Google" class="service-icon" data-service="google" />
                    <img src="<?= $webRoot; ?>/ui/images/core/icons/kobold.jpg" alt="Kobold" class="service-icon" data-service="kobold" />
                    <img src="<?= $webRoot; ?>/ui/images/core/icons/custom.jpg" alt="Custom" class="service-icon" data-service="custom" />
                </div>
            </div>

            <label for='url'>URL</label><br>
            <input type="text" name="url" value="<?= htmlspecialchars($editItem["url"] ?? "") ?>"><br>

            <label for='model'>Model</label><br>
            <input type="text" name="model" value="<?= htmlspecialchars($editItem["model"] ?? "") ?>"><br>

            <div id="provider_row">
                <label for='provider'>Provider</label><br>
                <input type="text" name="provider" value="<?= htmlspecialchars($editItem["provider"] ?? "") ?>"><br>
            </div>

            <div id="driver_row" style="display:none;">
                <label for='driver'>Driver</label><br>
                <input type="text" id="driver_input" name="driver" value="<?= htmlspecialchars($editItem["driver"] ?? "") ?>"><br>
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
            <div id="reasoning_row">
                <label><span class='tip-label' data-tip='Use a reasoning-capable model. May be slower and cost more; can improve complex tasks.'>Reasoning Model</span></label><br>
                <input type="hidden" name="reasoning_model" value="0">
                <label><input type="checkbox" name="reasoning_model" value="1" <?= isset($editItem["reasoning_model"]) && $editItem["reasoning_model"] == 1 ? "checked" : "" ?>> <span class="toggle-text">On</span></label>
            </div>
            <div id="json_toggles" style="margin-top:8px;">
                <label><span class='tip-label' data-tip='Force responses to be strict JSON. Non‑JSON output may be rejected or auto‑retried.'>Enforce JSON</span></label><br>
                <input type="hidden" name="enforce_json" value="0">
                <label><input type="checkbox" name="enforce_json" value="1" <?= isset($editItem["enforce_json"]) && $editItem["enforce_json"] == 1 ? "checked" : "" ?>> <span class="toggle-text">On</span></label>

                <div style="height:6px;"></div>
                <label><span class='tip-label' data-tip='Guide/validate the JSON structure with a schema. Best used with Enforce JSON.'>JSON Schema</span></label><br>
                <input type="hidden" name="json_schema" value="0">
                <label><input type="checkbox" name="json_schema" value="1" <?= isset($editItem["json_schema"]) && $editItem["json_schema"] == 1 ? "checked" : "" ?>> <span class="toggle-text">On</span></label>

                <div style="height:6px;"></div>
                <label><span class='tip-label' data-tip='Send a starter JSON object to steer field names/shape in the response.'>Prefill JSON</span></label><br>
                <input type="hidden" name="prefill_json" value="0">
                <label><input type="checkbox" name="prefill_json" value="1" <?= isset($editItem["prefill_json"]) && $editItem["prefill_json"] == 1 ? "checked" : "" ?>> <span class="toggle-text">On</span></label>
            </div>
        </div>

        <div>
            <?php
            $tipMaxTokens = 'Maximum tokens the model can generate for a response. Higher = longer answers; may increase cost/latency. [>= 0]';
            echo "<label for='max_tokens' style='margin-top:10px; display:block;'><span class='tip-label' data-tip='" . htmlspecialchars($tipMaxTokens, ENT_QUOTES) . "'>Max Tokens</span></label>";
            echo "<input type='number' name='max_tokens' value='" . htmlspecialchars($editItem["max_tokens"] ?? "") . "' min='0' step='1'>";
            $ranges = [
                'temperature' => ['min'=>0,'max'=>2,'step'=>0.01],
                'presence_penalty' => ['min'=>-2,'max'=>2,'step'=>0.01],
                'frequency_penalty' => ['min'=>-2,'max'=>2,'step'=>0.01],
                'repetition_penalty' => ['min'=>0,'max'=>2,'step'=>0.01],
                'top_p' => ['min'=>0,'max'=>1,'step'=>0.01],
                'top_k' => ['min'=>0,'max'=>100,'step'=>1],
                'min_p' => ['min'=>0,'max'=>1,'step'=>0.01],
                'top_a' => ['min'=>0,'max'=>1,'step'=>0.01],
            ];
            $displayDefaults = [
                'temperature' => 1.05,
                'presence_penalty' => 0,
                'frequency_penalty' => 0,
                'repetition_penalty' => 1,
                'top_p' => 0.7,
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
            echo "<div class='kv-grid'>";
            foreach ($ranges as $field => $conf) {
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
                echo "<div style='margin-top:6px;'><input type='number' class='inline-num' id='{$nid}' name='{$field}' min='{$min}' max='{$max}' step='{$step}' value='{$val}' oninput=\"llmClamp('{$rid}','{$nid}',{$min},{$max})\"></div>";
                echo "</div>";
            }
            echo "</div>";
            ?>
        </div>
    </div>

    
    

    <details id="metadata_section" class="collapsible">
        <summary class="collapsible-header">Metadata</summary>
        <div class="collapsible-content">
            <textarea name="metadata" style="display:none"><?= htmlspecialchars($editItem["metadata"] ?? "") ?></textarea>
            <div id="metadata"></div>
        </div>
    </details>
    <script>
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
    </script>

    
</form>
</div>
    </div>
</div>

<script>
// Sync On/Off labels for checkboxes
(function(){
    const names = ['reasoning_model','enforce_json','json_schema','prefill_json'];
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
        kobold: 'http://127.0.0.1:5001',
        custom: ''
    };
    // No dropdown; selection by icons only
    const providerRow = document.getElementById('provider_row');
    const urlInput = document.querySelector('input[name="url"]');
    const driverInput = document.querySelector('input[name="driver"]');
    const driverRow = document.getElementById('driver_row');
    const apiBadgeSelect = document.getElementById('api_badge_id');
    const icons = document.querySelectorAll('.service-icon');
    const apiKeyRow = document.getElementById('api_key_row');

    function setActive(service){
        icons.forEach(ic=>{
            if (ic.dataset.service === service) ic.classList.add('active'); else ic.classList.remove('active');
        });
    }

    const driverDefaults = {
        openrouter: 'openrouterjson',
        openai: 'openaijson',
        google: 'google_openaijson',
        kobold: 'koboldcppjson',
        custom: ''
    };

    const apiBadgeLabelMatch = {
        openrouter: ['openrouter'],
        openai: ['openai'],
        google: ['google']
        // kobold intentionally omitted (no API key)
    };

    function syncApiBadge(service){
        if (!apiBadgeSelect) return;
        if (service === 'kobold') { apiBadgeSelect.value = ''; return; }
        const targets = (apiBadgeLabelMatch[service] || []).map(s => s.toLowerCase());
        if (targets.length === 0) return;
        let selectedVal = '';
        for (let i = 0; i < apiBadgeSelect.options.length; i++) {
            const opt = apiBadgeSelect.options[i];
            const label = (opt.textContent || opt.innerText || '').toLowerCase();
            if (targets.some(t => label.includes(t))) { selectedVal = opt.value; break; }
        }
        if (selectedVal !== '') apiBadgeSelect.value = selectedVal; else apiBadgeSelect.value = '';
    }

    function applyService(service){
        if (defaults[service]) urlInput.value = defaults[service];
        providerRow.style.display = (service === 'openrouter') ? '' : 'none';
        if (driverInput && driverDefaults[service]) driverInput.value = driverDefaults[service];
        syncApiBadge(service);
        setActive(service);
        if (apiKeyRow) apiKeyRow.style.display = (service === 'kobold') ? 'none' : '';
        if (driverRow) driverRow.style.display = (service === 'custom') ? '' : 'none';
    }

    // Initialize from current URL heuristic
    (function init(){
        let service = 'openrouter';
        const val = (urlInput && urlInput.value) ? urlInput.value : '';
        if (val.includes('openai.com')) service = 'openai';
        else if (val.includes('generativelanguage.googleapis.com')) service = 'google';
        else if (val.includes('127.0.0.1') || val.includes('localhost')) service = 'kobold';
        else if (driverInput && /openai/.test(String(driverInput.value||''))) service = 'openai';
        else if (driverInput && /google/.test(String(driverInput.value||''))) service = 'google';
        else if (driverInput && /kobold/.test(String(driverInput.value||''))) service = 'kobold';
        else service = 'custom';
        applyService(service);
    })();

    icons.forEach(ic=>{
        ic.addEventListener('click', ()=> applyService(ic.dataset.service));
    });
})();

function llmClamp(rangeId, numberId, min, max){
    const r = document.getElementById(rangeId)
    const n = document.getElementById(numberId)
    if (!r || !n) return
    let v = parseFloat(n.value)
    if (isNaN(v)) v = min
    if (v < min) v = min
    if (v > max) v = max
    n.value = v
    r.value = v
}
</script>

<script>
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

    function openModal(url){
        const iframe = document.getElementById('llmtest_iframe');
        const loader = document.getElementById('llmtest_loading');
        if (loader) loader.style.display = 'flex';
        // attach onload to hide loader
        iframe.onload = function(){ if (loader) loader.style.display = 'none'; };
        iframe.src = url;
        modal.style.display = 'flex';
    }
    function closeModal(){ modal.style.display = 'none'; try { document.getElementById('llmtest_iframe').src='about:blank'; } catch(_){} }
    document.addEventListener('click', function(e){ if (e.target && e.target.id==='llmtest_close') closeModal(); });
    modal.addEventListener('click', function(e){ if (e.target===modal) closeModal(); });
    document.addEventListener('keydown', function(e){ if (e.key==='Escape') closeModal(); });

    const testBtn = document.getElementById('btn_test_connector');
    if (testBtn){
        testBtn.addEventListener('click', async function(){
            const form = document.querySelector('form[method="post"]');
            if (!form) return;
            // Save current edits first to ensure test uses latest config
            try {
                const fd = new FormData(form);
                // Ensure update action is present
                if (!fd.has('update') && !fd.has('create')) fd.append('update','1');
                // show modal early with loader while saving
                const idInputEarly = form.querySelector('input[name="id"]');
                const cidEarly = idInputEarly ? idInputEarly.value : '';
                openModal('about:blank');
                await fetch('llm_connectors.php', { method:'POST', body: fd });
            } catch (e) { /* ignore */ }
            // Open test page for this connector id (DB-backed)
            const idInput = form.querySelector('input[name="id"]');
            const cid = idInput ? idInput.value : '';
            openModal('<?= $webRoot; ?>/ui/core/tests/llmtest.php' + (cid ? ('?connector_id='+encodeURIComponent(cid)) : ''));
        });
    }
})();

// OpenRouter models dropdown for Model textbox
(function(){
    const modelInput = document.querySelector('input[name="model"]');
    if (!modelInput) return;

    let cache = null; // cached models
    let dropdown = null;
    let isOpen = false;

    function ensureDropdown(){
        if (dropdown) return dropdown;
        dropdown = document.createElement('div');
        dropdown.className = 'orm-dropdown';
        document.body.appendChild(dropdown);
        // Prevent blur-close when clicking inside
        dropdown.addEventListener('mousedown', (e)=>{ e.preventDefault(); });
        return dropdown;
    }

    function positionDropdown(){
        const rect = modelInput.getBoundingClientRect();
        const style = dropdown.style;
        style.left = (rect.left + window.scrollX) + 'px';
        style.top = (rect.bottom + window.scrollY + 4) + 'px';
        style.width = rect.width + 'px';
        style.display = 'block';
        isOpen = true;
    }

    function closeDropdown(){
        if (!dropdown) return;
        dropdown.style.display = 'none';
        isOpen = false;
    }

    function formatPrice(n){
        if (n === undefined || n === null || n === '' || isNaN(parseFloat(n))) return 'N/A';
        const perTok = parseFloat(n);
        const perK = perTok * 1000.0;
        return '$' + perK.toFixed(4) + ' / 1K tok';
    }

    function renderList(models, filterText){
        ensureDropdown();
        const q = (filterText || '').toLowerCase();
        const list = (models || []).filter(m => {
            if (!q) return true;
            const id = (m.id || '').toLowerCase();
            const name = (m.name || '').toLowerCase();
            return id.includes(q) || name.includes(q);
        });

        let html = '';
        html += '<div class="orm-head">OpenRouter Models</div>';
        html += '<div class="orm-note">Click to select. Pricing shown per 1K tokens (prompt/completion).</div>';

        if (list.length === 0){
            html += '<div class="orm-muted" style="padding:8px 10px;">No matches</div>';
        } else {
            list.forEach(m => {
                const prompt = formatPrice(m.pricing && m.pricing.prompt);
                const completion = formatPrice(m.pricing && m.pricing.completion);
                const ctx = (m.top_provider && m.top_provider.context_length) || m.context_length || '';
                const name = m.name ? ' — ' + escapeHtml(m.name) : '';
                const line = `${escapeHtml(m.id)}${name}`;
                const sub = `Pricing: ${prompt} • ${completion}` + (ctx? ` • ctx ${ctx}` : '');
                html += `<div class="orm-item" data-id="${encodeHtmlAttr(m.id)}" title="${encodeHtmlAttr(m.description||m.name||m.id)}">`+
                        `<div>${line}</div>`+
                        `<div class="orm-muted" style="font-size:12px; margin-top:2px;">${sub}</div>`+
                    `</div>`;
            });
        }

        dropdown.innerHTML = html;
        // Attach click handlers
        dropdown.querySelectorAll('.orm-item').forEach(el => {
            el.addEventListener('click', () => {
                const id = el.getAttribute('data-id') || '';
                modelInput.value = id;
                // notify listeners (e.g., provider auto-fill)
                try { modelInput.dispatchEvent(new Event('input', { bubbles: true })); } catch (_) {}
                try { modelInput.dispatchEvent(new Event('change', { bubbles: true })); } catch (_) {}
                closeDropdown();
            });
        });

        positionDropdown();
    }

    function escapeHtml(s){
        return (s==null? '': String(s)).replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
    }
    function encodeHtmlAttr(s){
        return (s==null? '': String(s)).replace(/[&<>"]+/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
    }

    async function loadModels(){
        if (cache) return cache;
        ensureDropdown();
        dropdown.innerHTML = '<div class="orm-head">OpenRouter Models</div><div class="orm-note">Loading…</div>';
        positionDropdown();
        try {
            const res = await fetch('https://openrouter.ai/api/v1/models');
            if (!res.ok) throw new Error('HTTP '+res.status);
            const json = await res.json();
            const data = Array.isArray(json && json.data) ? json.data : [];
            // Normalize important fields
            cache = data.map(m => ({
                id: m.id || m.canonical_slug || '',
                name: m.name || '',
                pricing: m.pricing || {},
                top_provider: m.top_provider || {},
                context_length: m.context_length || undefined,
                description: m.description || ''
            }));
            // Sort by name then id
            cache.sort((a,b)=> (a.name||'').localeCompare(b.name||'') || (a.id||'').localeCompare(b.id||''));
            return cache;
        } catch (e) {
            dropdown.innerHTML = '<div class="orm-head">OpenRouter Models</div><div class="orm-err">Failed to load models. Check network/CORS.</div>';
            positionDropdown();
            throw e;
        }
    }

    function isOpenRouter(){
        const url = (document.querySelector('input[name="url"]').value||'');
        const driver = (document.querySelector('input[name="driver"]').value||'');
        return url.includes('openrouter.ai') || /openrouter/.test(driver);
    }
    async function maybeOpenDropdown(){
        if (!isOpenRouter()) return;
        try {
            const models = await loadModels();
            renderList(models, modelInput.value);
        } catch (_e) {
            // already rendered error in dropdown
        }
    }

    // Events
    modelInput.addEventListener('focus', () => { if (isOpenRouter()) maybeOpenDropdown(); });
    modelInput.addEventListener('click', () => { if (isOpenRouter()) maybeOpenDropdown(); });
    modelInput.addEventListener('input', () => { if (isOpen && cache) renderList(cache, modelInput.value); });
    modelInput.addEventListener('blur', () => { setTimeout(closeDropdown, 120); });
    window.addEventListener('resize', () => { if (isOpen) positionDropdown(); });
    window.addEventListener('scroll', () => { if (isOpen) positionDropdown(); }, true);
    document.addEventListener('keydown', (e)=>{ if (e.key==='Escape') closeDropdown(); });

    // Close dropdown when service likely changed (url/driver edits)
    document.querySelector('input[name="url"]').addEventListener('change', () => closeDropdown());
    document.querySelector('input[name="driver"]').addEventListener('change', () => closeDropdown());
})();

// API key notice logic
(function(){
    const sel = document.getElementById('api_badge_id');
    const note = document.getElementById('api_key_notice');
    if (!sel || !note) return;
    function showToast(msg){
        try {
            const toast = document.getElementById('toast');
            if (toast){ toast.querySelector('.message').textContent = msg; toast.classList.add('show'); setTimeout(()=>toast.classList.remove('show'), 2500); }
        } catch (_e){}
    }
    function update(){
        const opt = sel.options[sel.selectedIndex];
        const empty = opt ? opt.getAttribute('data-empty') === '1' : true;
        if (!opt || sel.value === ''){
            note.className = 'api-key-notice warn';
            note.textContent = 'No API key selected. Some services require a key.';
            return;
        }
        if (empty){
            note.className = 'api-key-notice warn';
        } else {
            note.className = 'api-key-notice ok';
        }
    }
    sel.addEventListener('change', update);
    update();
})();
</script>

<script>
// OpenRouter providers dropdown for Provider textbox + auto-fill from model
(function(){
    const providerInput = document.querySelector('input[name="provider"]');
    const modelInput = document.querySelector('input[name="model"]');
    if (!providerInput || !modelInput) return;

    let providersCache = null;
    let dropdown = null;
    let isOpen = false;

    function ensureDropdown(){
        if (dropdown) return dropdown;
        dropdown = document.createElement('div');
        dropdown.className = 'orm-dropdown';
        document.body.appendChild(dropdown);
        dropdown.addEventListener('mousedown', (e)=>{ e.preventDefault(); });
        return dropdown;
    }

    function positionDropdown(){
        const rect = providerInput.getBoundingClientRect();
        const style = dropdown.style;
        style.left = (rect.left + window.scrollX) + 'px';
        style.top = (rect.bottom + window.scrollY + 4) + 'px';
        style.width = rect.width + 'px';
        style.display = 'block';
        isOpen = true;
    }

    function closeDropdown(){
        if (!dropdown) return;
        dropdown.style.display = 'none';
        isOpen = false;
    }

    function escapeHtml(s){
        return (s==null? '': String(s)).replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
    }
    function encodeHtmlAttr(s){
        return (s==null? '': String(s)).replace(/[&<>"]+/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
    }

    function renderList(items, filterText, relevantSlugs){
        ensureDropdown();
        const q = (filterText || '').toLowerCase();
        const slugAllow = Array.isArray(relevantSlugs) ? new Set(relevantSlugs.filter(Boolean)) : null;
        const filteredByRelevance = (items || []).filter(p => {
            if (slugAllow && slugAllow.size>0) return slugAllow.has((p.slug||''));
            return true;
        });
        const list = filteredByRelevance.filter(p => {
            if (!q) return true;
            const slug = (p.slug || '').toLowerCase();
            const name = (p.name || '').toLowerCase();
            return slug.includes(q) || name.includes(q);
        });

        let html = '';
        html += '<div class="orm-head">OpenRouter Providers</div>';
        html += '<div class="orm-note">Click to select. Value set to provider slug.</div>';

        if (list.length === 0){
            const hasRelevantFilter = (slugAllow && slugAllow.size>0);
            const note = hasRelevantFilter ? 'No relevant providers for the selected model.' : 'No matches';
            html += `<div class="orm-muted" style="padding:8px 10px;">${note}</div>`;
        } else {
            list.forEach(p => {
                const name = p.name ? ` — ${escapeHtml(p.name)}` : '';
                html += `<div class=\"orm-item\" data-slug=\"${encodeHtmlAttr(p.slug)}\" title=\"${encodeHtmlAttr(p.name||p.slug)}\">`+
                        `<div>${escapeHtml(p.slug)}${name}</div>`+
                        `<div class=\"orm-muted\" style=\"font-size:12px; margin-top:2px;\">`+
                        `${p.privacy_policy_url? 'Privacy: '+escapeHtml(p.privacy_policy_url): ''}`+
                        `${p.terms_of_service_url? (p.privacy_policy_url? ' • ': '')+'TOS: '+escapeHtml(p.terms_of_service_url): ''}`+
                        `</div>`+
                    `</div>`;
            });
        }

        dropdown.innerHTML = html;
        dropdown.querySelectorAll('.orm-item').forEach(el => {
            el.addEventListener('click', () => {
                const slug = el.getAttribute('data-slug') || '';
                providerInput.value = slug;
                try { providerInput.dispatchEvent(new Event('input', { bubbles: true })); } catch (_) {}
                try { providerInput.dispatchEvent(new Event('change', { bubbles: true })); } catch (_) {}
                closeDropdown();
            });
        });

        positionDropdown();
    }

    async function loadProviders(){
        if (providersCache) return providersCache;
        ensureDropdown();
        dropdown.innerHTML = '<div class="orm-head">OpenRouter Providers</div><div class="orm-note">Loading…</div>';
        positionDropdown();
        try {
            const res = await fetch('https://openrouter.ai/api/v1/providers');
            if (!res.ok) throw new Error('HTTP '+res.status);
            const json = await res.json();
            const data = Array.isArray(json && json.data) ? json.data : [];
            providersCache = data.map(p => ({
                name: p.name || '',
                slug: p.slug || '',
                privacy_policy_url: p.privacy_policy_url || '',
                terms_of_service_url: p.terms_of_service_url || '',
                status_page_url: p.status_page_url || ''
            })).filter(p => p.slug);
            providersCache.sort((a,b)=> (a.slug||'').localeCompare(b.slug||''));
            return providersCache;
        } catch (e) {
            dropdown.innerHTML = '<div class="orm-head">OpenRouter Providers</div><div class="orm-err">Failed to load providers. Check network/CORS.</div>';
            positionDropdown();
            throw e;
        }
    }

    function getRelevantProviderSlugs(){
        const val = (modelInput.value || '').trim();
        const ix = val.indexOf('/');
        if (ix > 0){
            const slug = val.slice(0, ix).trim();
            if (slug) return [slug];
        }
        return [];
    }

    function isOpenRouter(){
        const url = (document.querySelector('input[name="url"]').value||'');
        const driver = (document.querySelector('input[name="driver"]').value||'');
        return url.includes('openrouter.ai') || /openrouter/.test(driver);
    }
    async function maybeOpenDropdown(){
        if (!isOpenRouter()) return;
        try {
            const items = await loadProviders();
            renderList(items, providerInput.value, getRelevantProviderSlugs());
        } catch (_e) {}
    }

    function extractProviderSlugFromModel(val){
        if (!val) return '';
        const s = String(val);
        const ix = s.indexOf('/');
        if (ix <= 0) return '';
        return s.slice(0, ix).trim();
    }

    // Auto-fill provider when model looks like "provider/model"
    function maybeAutofillProvider(){
        if (serviceSelect.value !== 'openrouter') return;
        const slug = extractProviderSlugFromModel(modelInput.value);
        if (!slug) return;
        providerInput.value = slug;
        try { providerInput.dispatchEvent(new Event('input', { bubbles: true })); } catch (_) {}
        try { providerInput.dispatchEvent(new Event('change', { bubbles: true })); } catch (_) {}
    }

    // Events
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
    modelInput.addEventListener('change', () => { maybeAutofillProvider(); if (isOpen && providersCache) renderList(providersCache, providerInput.value, getRelevantProviderSlugs()); });
    modelInput.addEventListener('input', () => { maybeAutofillProvider(); if (isOpen && providersCache) renderList(providersCache, providerInput.value, getRelevantProviderSlugs()); });
})();
</script>

<!-- list/grid moved to left pane -->

<?php
 // Provides a JSON editor for metadata field and form consolidation function (only needed if metadata field is present)
 include(__DIR__."/tmpl/metadata_json_editor.php");
 ?>

</main>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>

