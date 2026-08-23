<?php

$enginePath = __DIR__.DIRECTORY_SEPARATOR."../../";

require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
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
        /* Compact responsive gutter instead of a fixed 10% on every viewport. */
        padding: 10px clamp(10px, 2.5vw, 34px) 24px;
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

    /* Page header is the shared compact inline row (.chim-page-head in chim-theme.css). */

    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }
    .content-section {
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        padding: 25px;
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }
    .content-section:hover {
        border-color: #4a4a4a;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2), inset 0 1px rgba(255, 255, 255, 0.05);
    }
    .content-section h2, .content-section h3 {
        font-family: 'MagicCards', serif;
        color: rgb(242, 124, 17);
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        word-spacing: 6px;
        margin-bottom: 15px;
        font-size: 1.4em;
    }
    .section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 15px;
    }
    .section-header h2 {
        margin: 0;
    }
    .full-width-section { grid-column: 1 / -1; }
    @media (max-width: 900px) {
        .content-grid { grid-template-columns: 1fr; }
    }

    /* Cards and grids styled like oghma sections */
    .provider-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:12px; }
    .provider-card {
        background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        border:1px solid #3a3a3a;
        border-radius:10px;
        padding:14px;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
        transition: all 0.2s ease;
    }
    .provider-card:hover {
        border-color: #4a4a4a;
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.2);
        transform: translateY(-1px);
    }
    .provider-card .provider-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px; }
    .provider-card .provider-title { display:flex; align-items:center; gap:10px; color:#e0e0e0; }
    .provider-card .provider-icon { 
        width:28px; 
        height:28px; 
        border-radius:6px; 
        background: linear-gradient(135deg, rgba(58, 58, 58, 0.9), rgba(48, 48, 48, 0.9)); 
        display:flex; 
        align-items:center; 
        justify-content:center; 
        font-size:16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    }
    .provider-card .provider-links { display:flex; gap:10px; }
    .provider-card .provider-links a { font-size:12px; color: rgb(242,124,17); text-decoration: underline; }
    .provider-card .provider-body { display:flex; gap:8px; align-items:center; }
    .provider-card input[type="password"], .provider-card input[type="text"] { 
        flex:1; 
        background: rgba(26, 26, 26, 0.8); 
        color: #e9efff; 
        border: 1px solid #3a3a3a; 
        border-radius: 6px; 
        padding: 10px 12px;
        transition: all 0.2s ease;
    }
    .provider-card input[type="password"]:focus, .provider-card input[type="text"]:focus {
        border-color: rgba(242, 124, 17, 0.5);
        outline: none;
        box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
        background: rgba(34, 34, 34, 0.9);
    }
    .provider-card .provider-subtext { margin-top:8px; color:#bdbdbd; font-size:12px; }
    .provider-card .provider-subtext ul { margin:4px 0 0 18px; padding:0; }
    .provider-card .provider-subtext li { line-height:1.4; }
    .provider-card .provider-subtext .desc { margin:0 0 2px 0; color:#cfcfcf; }

    #custom-keys { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:12px; }
    .custom-card { 
        background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98)); 
        border:1px solid #3a3a3a; 
        border-radius:10px; 
        padding:14px;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
        transition: all 0.2s ease;
    }
    .custom-card:hover {
        border-color: #4a4a4a;
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.2);
        transform: translateY(-1px);
    }
    .custom-card.has-key { 
        box-shadow: 0 0 0 1px rgba(45,106,79,0.35) inset, 0 1px 4px rgba(0, 0, 0, 0.1); 
        border-color:#2d6a4f; 
    }
    .custom-card.has-key:hover {
        box-shadow: 0 0 0 1px rgba(45,106,79,0.5) inset, 0 3px 8px rgba(0, 0, 0, 0.2);
    }
    .custom-card .provider-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px; }
    .custom-card .provider-title { display:flex; align-items:center; gap:10px; color:#e0e0e0; }
    .custom-card .provider-icon { 
        width:28px; 
        height:28px; 
        border-radius:6px; 
        background: linear-gradient(135deg, rgba(58, 58, 58, 0.9), rgba(48, 48, 48, 0.9)); 
        display:flex; 
        align-items:center; 
        justify-content:center; 
        font-size:16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    }
    .custom-card .provider-body { display:flex; gap:8px; align-items:center; }
    .custom-card input[type="password"], .custom-card input[type="text"] { 
        flex:1; 
        background: rgba(26, 26, 26, 0.8); 
        color: #e9efff; 
        border: 1px solid #3a3a3a; 
        border-radius: 6px; 
        padding: 10px 12px;
        transition: all 0.2s ease;
    }
    .custom-card input[type="password"]:focus, .custom-card input[type="text"]:focus {
        border-color: rgba(242, 124, 17, 0.5);
        outline: none;
        box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
        background: rgba(34, 34, 34, 0.9);
    }
    .custom-card label {
        color: rgb(242, 124, 17);
        font-weight: 600;
        display: block;
        margin-top: 10px;
        margin-bottom: 6px;
    }

    @media (max-width: 900px) {
        .provider-grid { grid-template-columns: 1fr; }
        #custom-keys { grid-template-columns: 1fr; }
    }
</style>

<main>
    <div class="page-header chim-page-head">
        <h1 class="api-title chim-page-head-title">API Keys</h1>
        <p class="page-subtitle chim-page-head-note">Manage API keys for LLM, TTS, and other service connectors</p>
    </div>
    
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
    'elevenlabs'  => 'ElevenLabs',
    'cartesia'    => 'Cartesia',
    'inworld'     => 'Inworld',
    'replicate'   => 'Replicate',
    'groq'        => 'Groq',
    'nano-gpt'    => 'Nano-GPT',
    'deepl'       => 'DeepL'
];

// Provider key/dashboard links
$providerLinks = [
    'openrouter' => 'https://openrouter.ai/keys',
    'openai' => 'https://platform.openai.com/api-keys',
    'deepgram' => 'https://console.deepgram.com/',
    'google' => 'https://console.cloud.google.com/apis/credentials',
    'azure' => 'https://ai.azure.com/',
    'elevenlabs' => 'https://elevenlabs.io/app/settings/api-keys',
    'cartesia' => 'https://play.cartesia.ai/console',
    'inworld' => 'https://studio.inworld.ai/',
    'replicate' => 'https://replicate.com/account/api-tokens',
    'groq' => 'https://console.groq.com/keys',
    'nano-gpt' => 'https://nano-gpt.com/',
    'deepl' => 'https://www.deepl.com/en/pro-api'
];

// Subtext bullet points per provider
$providerSubtext = [
    'openrouter' => ['LLM', 'ITT'],
    'deepgram' => ['STT'],
    'openai' => ['LLM', 'TTS', 'STT', 'ITT'],
    'azure' => ['TTS', 'STT'],
    'google' => ['LLM', 'TTS', 'ITT'],
    'elevenlabs' => ['TTS'],
    'cartesia' => ['TTS'],
    'inworld' => ['TTS'],
    'replicate' => ['Soulgaze Gallery Processor'],
    'groq' => ['LLM'],
    'nano-gpt' => ['LLM'],
    'deepl' => ['Translation'],
];

// Reserved labels (both slugs and pretty names), used to keep presets distinct from customs
$reservedLabelsLower = array_values(array_unique(array_merge(
    array_keys($presetMap),
    array_map('strtolower', array_values($presetMap))
)));

// Seed presets if missing (one-time check per label)
$existing = $apiBadge->getAll();
$existingLabelsLower = array_map(function($row){ return strtolower($row['label'] ?? ''); }, $existing);
foreach ($presetMap as $key => $pretty) {
    $lowerPretty = strtolower($pretty);
    if (!in_array($lowerPretty, $existingLabelsLower)) {
        $apiBadge->create([ 'label' => $pretty, 'api_key' => '' ]);
        $existingLabelsLower[] = $lowerPretty; // Prevent duplicate creation in same request
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
    $incomingLabel = trim($_POST['label'] ?? '');
    if (in_array(strtolower($incomingLabel), $reservedLabelsLower, true)) {
        header("Location: api_badge.php?err=reserved_label");
        exit;
    }
    // Assign custom IDs starting from 100 to leave room for preset IDs
    $allRows = $apiBadge->getAll();
    $maxId = 0;
    foreach ($allRows as $r) {
        $rid = isset($r['id']) ? intval($r['id']) : 0;
        if ($rid > $maxId) { $maxId = $rid; }
    }
    $newId = max(100, $maxId + 1);
    $apiBadge->create([
        'id' => $newId,
        'label' => $incomingLabel !== '' ? $incomingLabel : 'Custom Key',
        'api_key' => trim($_POST['api_key'] ?? '')
    ]);
    header("Location: api_badge.php?ok=saved");
    exit;
}

// Handle Update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update"])) {
    $uid = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $current = $uid ? $apiBadge->getById($uid) : null;
    $currentIsPreset = false;
    if (is_array($current)) {
        $currentIsPreset = in_array(strtolower($current['label'] ?? ''), $reservedLabelsLower, true);
    }
    $newLabel = isset($_POST['label']) ? trim($_POST['label']) : null;
    if ($newLabel !== null) {
        $newIsReserved = in_array(strtolower($newLabel), $reservedLabelsLower, true);
        // Block turning a custom row into a reserved preset label
        if (!$currentIsPreset && $newIsReserved) {
            header("Location: api_badge.php?err=reserved_label");
            exit;
        }
    }
    $apiBadge->update($uid, $_POST);
    header("Location: api_badge.php?ok=saved");
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
        <div class="section-header">
            <h2>Preset Keys (Saves Automatically)</h2>
            <button type="submit" name="save_all" value="1" class="button btn-save">Save Keys</button>
        </div>
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
                    <?php if (!empty($providerSubtext[$slug])): ?>
                        <div class="provider-subtext">
                            <div class="desc">This key can be used for: <?= htmlspecialchars(implode(', ', $providerSubtext[$slug])) ?></div>
                        </div>
                    <?php endif; ?>
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
    const RESERVED_LABELS = <?php echo json_encode($reservedLabelsLower); ?>;
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
        if (!id) {
            if (RESERVED_LABELS.indexOf(labelVal.toLowerCase()) !== -1) {
                alert('That label is reserved for presets. Please use a different name.');
                return;
            }
        }
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

// Lightweight feedback based on query params
(function(){
    try {
        const sp = new URLSearchParams(window.location.search);
        if (sp.get('err') === 'reserved_label') {
            alert('That label is reserved for a preset provider. Please choose another.');
        } else if (sp.get('ok') === 'saved') {
            // no-op to avoid noisy alerts; keep silent success
        }
    } catch(_e){}
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
