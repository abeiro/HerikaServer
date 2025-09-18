<?php

$enginePath = __DIR__.DIRECTORY_SEPARATOR."../../";

require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."logger.php");

require_once "{$enginePath}/lib/core/api_badge.class.php";

// Web root detection to match site-wide includes (consistent with oghma_upload)
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
$TITLE = "🔑 CHIM - API Keys";
ob_start();
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/head.html");
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
    /* Match oghma_upload page layout and colors */
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

    /* Title styling to match Oghma */
    h1.api-title {
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
    .content-section h2, .content-section h3 {
        font-family: 'MagicCards', serif;
        color: rgb(242, 124, 17);
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        word-spacing: 6px;
        margin-bottom: 15px;
        font-size: 1.4em;
    }
    .full-width-section { grid-column: 1 / -1; }
    @media (max-width: 900px) {
        main { padding-left: 5%; padding-right: 5%; }
        .content-grid { grid-template-columns: 1fr; }
    }

    /* Cards and grids styled like oghma sections */
    .provider-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:12px; }
    .provider-card {
        background:#2a2a2a;
        border:1px solid #4a4a4a;
        border-radius:8px;
        padding:12px;
    }
    .provider-card .provider-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px; }
    .provider-card .provider-title { display:flex; align-items:center; gap:10px; color:#e0e0e0; }
    .provider-card .provider-icon { width:28px; height:28px; border-radius:6px; background:#3a3a3a; display:flex; align-items:center; justify-content:center; font-size:16px; }
    .provider-card .provider-links { display:flex; gap:10px; }
    .provider-card .provider-links a { font-size:12px; color: rgb(242,124,17); text-decoration: underline; }
    .provider-card .provider-body { display:flex; gap:8px; align-items:center; }
    .provider-card input[type="password"], .provider-card input[type="text"] { flex:1; background-color:#333; color:#fff; border:1px solid #444; }

    #custom-keys { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:12px; }
    .custom-card { background:#2a2a2a; border:1px solid #4a4a4a; border-radius:8px; padding:12px; }
    .custom-card.has-key { box-shadow: 0 0 0 1px rgba(45,106,79,0.35) inset; border-color:#2d6a4f; }
    .custom-card .provider-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px; }
    .custom-card .provider-title { display:flex; align-items:center; gap:10px; color:#e0e0e0; }
    .custom-card .provider-icon { width:28px; height:28px; border-radius:6px; background:#3a3a3a; display:flex; align-items:center; justify-content:center; font-size:16px; }
    .custom-card .provider-body { display:flex; gap:8px; align-items:center; }
    .custom-card input[type="password"], .custom-card input[type="text"] { flex:1; background-color:#333; color:#fff; border:1px solid #444; }

    @media (max-width: 900px) {
        .provider-grid { grid-template-columns: 1fr; }
        #custom-keys { grid-template-columns: 1fr; }
    }
</style>

<main>
    <h1 class="api-title">API Keys</h1>
    <div id="toast" class="toast-notification">
        <span class="message"></span>
    </div>

    <form method="post" action="api_badge.php">

<?php
$GLOBALS["db"] = new sql();
$apiBadge = new ApiBadge();

// Preset providers (case-insensitive matching)
$presetMap = [
    'openrouter'  => 'OpenRouter',
    'openai'      => 'OpenAI',
    'deepgram'    => 'Deepgram',
    'google'      => 'Google',
    'azure'       => 'Azure',
    'elevenlabs'  => 'ElevenLabs'
];

// Provider key/dashboard links
$providerLinks = [
    'openrouter' => 'https://openrouter.ai/keys',
    'openai' => 'https://platform.openai.com/api-keys',
    'deepgram' => 'https://console.deepgram.com/',
    'google' => 'https://console.cloud.google.com/apis/credentials',
    'azure' => 'https://ai.azure.com/',
    'elevenlabs' => 'https://elevenlabs.io/app/settings/api-keys'
];

// Seed presets if missing
$existing = $apiBadge->getAll();
$existingLabelsLower = array_map(function($row){ return strtolower($row['label'] ?? ''); }, $existing);
foreach ($presetMap as $key => $pretty) {
    if (!in_array(strtolower($pretty), $existingLabelsLower)) {
        $apiBadge->create([ 'label' => $pretty, 'api_key' => '' ]);
    }
}

// Handle Save All (batch update)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_all"])) {
    // 1) Presets
    if (isset($_POST['presets']) && is_array($_POST['presets'])) {
        foreach ($presetMap as $slug => $pretty) {
            $posted = $_POST['presets'][$slug] ?? null;
            if (!$posted) continue;
            $id = isset($posted['id']) ? intval($posted['id']) : null;
            $apiKey = trim($posted['api_key'] ?? '');
            if ($id) {
                $apiBadge->update($id, [ 'label' => $pretty, 'api_key' => $apiKey ]);
            } else {
                $apiBadge->create([ 'label' => $pretty, 'api_key' => $apiKey ]);
            }
        }
    }

    // 2) Custom keys
    if (isset($_POST['custom']) && is_array($_POST['custom'])) {
        // Align arrays: ids[], labels[], api_keys[]
        $ids = $_POST['custom']['id'] ?? [];
        $labels = $_POST['custom']['label'] ?? [];
        $keys = $_POST['custom']['api_key'] ?? [];
        $count = max(count($ids), count($labels), count($keys));
        for ($i = 0; $i < $count; $i++) {
            $cid = isset($ids[$i]) && $ids[$i] !== '' ? intval($ids[$i]) : null;
            $clabel = trim($labels[$i] ?? '');
            $ckey = trim($keys[$i] ?? '');
            if ($clabel === '' && $ckey === '') continue; // skip empty rows
            // Prevent using preset labels for customs
            if (array_key_exists(strtolower($clabel), $presetMap)) continue;
            if ($cid) {
                $apiBadge->update($cid, [ 'label' => $clabel, 'api_key' => $ckey ]);
            } else {
                $apiBadge->create([ 'label' => $clabel, 'api_key' => $ckey ]);
            }
        }
    }

    header("Location: api_badge.php");
    exit;
}

// Handle Create
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create"])) {
    $apiBadge->create($_POST);
    header("Location: api_badge.php");
    exit;
}

// Handle Update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update"])) {
    $apiBadge->update($_POST["id"], $_POST);
    header("Location: api_badge.php");
    exit;
}

// Handle Delete
if (isset($_GET["delete"])) {
    $item = $apiBadge->getById($_GET["delete"]);
    $labelLower = strtolower($item["label"] ?? '');
    if (!array_key_exists($labelLower, $presetMap)) {
        $apiBadge->delete($_GET["delete"]);
    }
    header("Location: api_badge.php");
    exit;
}

// Fetch Data
$data = $apiBadge->getAll();
$editItem = null;

if (isset($_GET["edit"])) {
    $editItem = $apiBadge->getById($_GET["edit"]);
}
?>



<?php
// Build lookup by lower(label)
$byLabel = [];
foreach ($data as $row) {
    $byLabel[strtolower($row['label'] ?? '')] = $row;
}

// Split presets and customs
$presetRows = [];
foreach ($presetMap as $slug => $pretty) {
    $presetRows[$slug] = $byLabel[strtolower($pretty)] ?? [ 'id' => '', 'label' => $pretty, 'api_key' => '' ];
}
$customRows = array_filter($data, function($row) use ($presetMap) {
    return !array_key_exists(strtolower($row['label'] ?? ''), $presetMap);
});
?>



<div class="content-grid">
    <div class="content-section full-width-section">
        <h2>Preset Keys (Saves Automatically)</h2>
        <div class="provider-grid">
            <?php foreach ($presetRows as $slug => $row): ?>
                <div class="provider-card">
                    <div class="provider-head">
                        <div class="provider-title">
                            <div class="provider-icon">🔑</div>
                            <div><?= htmlspecialchars($presetMap[$slug]) ?></div>
                        </div>
                        <div class="provider-links">
                            <?php if (!empty($providerLinks[$slug])): ?>
                                <a href="<?= htmlspecialchars($providerLinks[$slug]) ?>" target="_blank" rel="noopener">Create Key</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <input type="hidden" name="presets[<?= htmlspecialchars($slug) ?>][id]" value="<?= htmlspecialchars($row['id']) ?>">
                    <div class="provider-body">
                        <input type="password" name="presets[<?= htmlspecialchars($slug) ?>][api_key]" value="<?= htmlspecialchars($row['api_key']) ?>" placeholder="Paste API key" data-autosave="preset" data-provider="<?= htmlspecialchars($slug) ?>" data-id="<?= htmlspecialchars($row['id']) ?>">
                        <button type="button" class="button" onclick="toggleVisibility(this)">Show</button>
                        <?php if (in_array($slug, ['openrouter','openai'])): ?>
                            <button type="button" class="btn-save" data-test-provider="<?= htmlspecialchars($slug) ?>" data-badge-id="<?= htmlspecialchars($row['id']) ?>">Test</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="content-section full-width-section">
        <h2>Custom Keys</h2>
        <div id="custom-keys" style="margin-bottom:10px;">
            <?php foreach ($customRows as $row): ?>
                <?php $hasKey = trim($row['api_key'] ?? '') !== ''; ?>
                <div class="custom-card <?= $hasKey ? 'has-key' : '' ?>">
                    <div class="provider-head">
                        <div class="provider-title">
                            <div class="provider-icon">🧩</div>
                            <div>Custom Key</div>
                        </div>
                        <div style="display:flex; gap:6px;">
                            <button type="button" class="button btn-save" data-save-id="<?= htmlspecialchars($row['id']) ?>">Save</button>
                            <button type="button" class="button btn-delete" data-delete-id="<?= htmlspecialchars($row['id']) ?>">Delete</button>
                        </div>
                    </div>
                    <input type="hidden" name="custom[id][]" value="<?= htmlspecialchars($row['id']) ?>">
                    <label>Label</label>
                    <input type="text" name="custom[label][]" value="<?= htmlspecialchars($row['label']) ?>" placeholder="Provider label (e.g., MyService)" data-autosave="custom" data-field="label" data-id="<?= htmlspecialchars($row['id']) ?>">
                    <label>API Key</label>
                    <div class="provider-body">
                        <input type="password" name="custom[api_key][]" value="<?= htmlspecialchars($row['api_key']) ?>" placeholder="Paste API key" data-autosave="custom" data-field="api_key" data-id="<?= htmlspecialchars($row['id']) ?>">
                        <button type="button" class="button" onclick="toggleVisibility(this)">Show</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="action-button add-new" onclick="addCustomKey()">Add Custom Key</button>
    </div>
</div>

    </form>

<script>
function toggleVisibility(btn){
    const input = btn.parentElement.querySelector('input');
    if(!input) return;
    if(input.type === 'password'){
        input.type = 'text';
        btn.textContent = 'Hide';
    } else {
        input.type = 'password';
        btn.textContent = 'Show';
    }
}
function addCustomKey(){
    const container = document.getElementById('custom-keys');
    const wrapper = document.createElement('div');
    wrapper.className = 'custom-card';
    wrapper.innerHTML = `
        <div class="provider-head">
            <div class="provider-title">
                <div class="provider-icon">🧩</div>
                <div>Custom Key</div>
            </div>
            <div style="display:flex; gap:6px;">
                <button type="button" class="button btn-save" data-save-id="">Save</button>
                <button type="button" class="button btn-delete" data-delete-id="">Delete</button>
            </div>
        </div>
        <input type="hidden" name="custom[id][]" value="">
        <label>Label</label>
        <input type="text" name="custom[label][]" value="" placeholder="Provider label (e.g., MyService)" data-autosave="custom" data-field="label" data-id="">
        <label>API Key</label>
        <div class="provider-body">
            <input type="password" name="custom[api_key][]" value="" placeholder="Paste API key" data-autosave="custom" data-field="api_key" data-id="">
            <button type="button" class="button" onclick="toggleVisibility(this)">Show</button>
        </div>
    `;
    container.appendChild(wrapper);
}
</script>

<script>
// Autosave for presets and custom keys
(function(){
    async function postUpdate(payload){
        try { await fetch('api_badge.php', { method:'POST', body: payload }); } catch(_e){}
    }
    document.addEventListener('input', function(e){
        const t = e.target;
        if (!t || !t.getAttribute) return;
        const mode = t.getAttribute('data-autosave');
        if (!mode) return;
        const formData = new FormData();
        if (mode === 'preset'){
            // We need an id to update the preset row
            const id = t.getAttribute('data-id') || '';
            if (!id) return;
            formData.append('update','1');
            formData.append('id', id);
            formData.append('label', t.getAttribute('data-provider') || '');
            formData.append('api_key', t.value || '');
        } else if (mode === 'custom'){
            const id = t.getAttribute('data-id') || '';
            const field = t.getAttribute('data-field') || '';
            if (!field) return;
            if (id){
                formData.append('update','1');
                formData.append('id', id);
                formData.append(field, t.value || '');
            } else {
                // Do not auto-create new rows; require explicit Save
                return;
            }
        }
        postUpdate(formData);
    }, { passive:true });
    // Per-card Save handler
    document.addEventListener('click', async function(e){
        const btn = e.target && e.target.closest && e.target.closest('.btn-save');
        if (!btn) return;
        const card = btn.closest('.custom-card');
        if (!card) return;
        const id = btn.getAttribute('data-save-id') || '';
        const labelInput = card.querySelector('input[name="custom[label][]"]');
        const keyInput = card.querySelector('input[name="custom[api_key][]"]');
        const labelVal = labelInput ? labelInput.value.trim() : '';
        const keyVal = keyInput ? keyInput.value.trim() : '';
        const fd = new FormData();
        if (id){
            fd.append('update','1');
            fd.append('id', id);
            fd.append('label', labelVal);
            fd.append('api_key', keyVal);
        } else {
            if (!labelVal && !keyVal) return; // nothing to save
            fd.append('create','1');
            fd.append('label', labelVal || 'Custom Key');
            fd.append('api_key', keyVal);
        }
        try { await fetch('api_badge.php', { method:'POST', body: fd }); } catch(_e){}
        // Reload to reflect persisted id/state
        try { window.location.reload(); } catch(_){}
    }, { passive:true });
    // Delete handler (existing rows: server delete; new rows: remove card)
    document.addEventListener('click', function(e){
        const btn = e.target && e.target.closest && e.target.closest('.btn-delete');
        if (!btn) return;
        const id = btn.getAttribute('data-delete-id') || '';
        const card = btn.closest('.custom-card');
        if (!confirm('Delete this custom API key?')) return;
        if (!id){
            if (card && card.parentNode) card.parentNode.removeChild(card);
            return;
        }
        try {
            const url = new URL('api_badge.php', window.location.href);
            url.searchParams.set('delete', id);
            window.location.href = url.toString();
        } catch(_e){
            window.location.href = 'api_badge.php?delete=' + encodeURIComponent(id);
        }
    }, { passive:true });
})();

// Modal for testing API keys
(function(){
    const MODAL_ID = 'apikeytest_modal';
    const modal = document.createElement('div');
    modal.id = MODAL_ID;
    modal.style.cssText = 'position:fixed; inset:0; display:none; align-items:center; justify-content:center; background:rgba(0,0,0,0.65); z-index:10000;';
    modal.innerHTML = `
        <div style="width:90%; max-width:900px; height:70vh; background:#111; border:1px solid rgba(138,155,182,0.4); border-radius:10px; box-shadow:0 10px 30px rgba(0,0,0,0.6); position:relative; overflow:hidden;">
            <button id="apikeytest_close" style="position:absolute; top:8px; right:10px; background:#300; color:#fff; border:1px solid rgba(255,255,255,0.2); border-radius:6px; padding:4px 10px; cursor:pointer; z-index:3;">Close</button>
            <div id="apikeytest_loading" style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.4); z-index:2;">
                <div style="width:48px; height:48px; border:4px solid rgba(255,255,255,0.25); border-top-color:#ffb862; border-radius:50%; animation: spin 1s linear infinite;"></div>
            </div>
            <iframe id="apikeytest_iframe" name="apikeytest_iframe" src="about:blank" style="width:100%; height:100%; border:0; background:#0e1624; position:relative; z-index:1;"></iframe>
        </div>
        <style>@keyframes spin{to{transform:rotate(360deg)}}</style>`;
    document.body.appendChild(modal);
    function openModal(url){ const iframe = document.getElementById('apikeytest_iframe'); const loader = document.getElementById('apikeytest_loading'); if (loader) loader.style.display = 'flex'; iframe.onload = function(){ if (loader) loader.style.display = 'none'; }; iframe.src = url; modal.style.display = 'flex'; }
    function closeModal(){ modal.style.display = 'none'; try { document.getElementById('apikeytest_iframe').src='about:blank'; } catch(_){} }
    document.addEventListener('click', function(e){ if (e.target && e.target.id==='apikeytest_close') closeModal(); });
    modal.addEventListener('click', function(e){ if (e.target===modal) closeModal(); });
    document.addEventListener('keydown', function(e){ if (e.key==='Escape') closeModal(); });

    function ensurePostForm(){
        let form = document.getElementById('apikeytest_form');
        if (form) return form;
        form = document.createElement('form');
        form.id = 'apikeytest_form';
        form.method = 'POST';
        form.target = 'apikeytest_iframe';
        form.action = 'tests/apikey_test.php';
        form.style.display = 'none';
        const inProv = document.createElement('input'); inProv.type='hidden'; inProv.name='provider'; form.appendChild(inProv);
        const inKey = document.createElement('input'); inKey.type='hidden'; inKey.name='api_key'; form.appendChild(inKey);
        document.body.appendChild(form);
        return form;
    }
    document.querySelectorAll('button[data-test-provider]').forEach(btn => {
        btn.addEventListener('click', () => {
            const provider = btn.getAttribute('data-test-provider');
            const row = btn.closest('.provider-card');
            const input = row ? row.querySelector('input[name^="presets"][name$="[api_key]"]') : null;
            const apiKey = input ? input.value.trim() : '';
            if (!apiKey){ alert('Please enter an API key first.'); return; }
            openModal('about:blank');
            const form = ensurePostForm();
            form.querySelector('input[name="provider"]').value = provider;
            form.querySelector('input[name="api_key"]').value = apiKey;
            try { form.submit(); } catch(_e){}
        });
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
