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
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/navbar.php");
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
.wide-centered { max-width: 900px; margin: 0 auto; }
</style>

<main>
    <div id="toast" class="toast-notification">
        <span class="message"></span>
    </div>

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

<style>
    /* Blur API keys until hover */
    td .api-key-blur {
        filter: blur(6px);
        transition: filter 0.2s ease;
        display: inline-block;
    }
    td:hover .api-key-blur { filter: none; }
</style>

<h1>API Keys</h1>

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

<?php if ($editItem): ?>
    <h2>Edit Key (ID: <?= htmlspecialchars($editItem["id"]) ?>)</h2>
<?php else: ?>
    <h2 onclick='document.forms[0].style.display="block"'>Add Custom Key</h2>
<?php endif; ?>

<div class="form-container wide-centered">
    <form method="post">
        <input type="hidden" name="save_all" value="1">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:10px;">
            <h2 style="margin:0;">Manage Keys</h2>
            <button type="submit" class="btn-save">Save All</button>
        </div>

        <h3>Preset Keys</h3>
        <div class="content-section wide-centered" style="margin-bottom:20px;">
            <?php foreach ($presetRows as $slug => $row): ?>
                <div class="conf-item" style="max-width:800px;">
                    <label><?= htmlspecialchars($presetMap[$slug]) ?></label>
                    <input type="hidden" name="presets[<?= htmlspecialchars($slug) ?>][id]" value="<?= htmlspecialchars($row['id']) ?>">
                    <div style="display:flex; gap:10px; align-items:center;">
                        <input type="password" name="presets[<?= htmlspecialchars($slug) ?>][api_key]" value="<?= htmlspecialchars($row['api_key']) ?>" placeholder="Paste API key" style="flex:1;">
                        <button type="button" class="button" onclick="toggleVisibility(this)">Show</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <h3>Custom Keys</h3>
        <div id="custom-keys" class="content-section wide-centered" style="margin-bottom:10px;">
            <?php foreach ($customRows as $row): ?>
                <div class="conf-item" style="max-width:800px;">
                    <input type="hidden" name="custom[id][]" value="<?= htmlspecialchars($row['id']) ?>">
                    <label>Label</label>
                    <input type="text" name="custom[label][]" value="<?= htmlspecialchars($row['label']) ?>" placeholder="Provider label (e.g., MyService)">
                    <label>API Key</label>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <input type="password" name="custom[api_key][]" value="<?= htmlspecialchars($row['api_key']) ?>" placeholder="Paste API key" style="flex:1;">
                        <button type="button" class="button" onclick="toggleVisibility(this)">Show</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="action-button add-new" onclick="addCustomKey()">Add Custom Key</button>

        <div style="margin-top:15px;">
            <button type="submit" class="btn-save">Save All</button>
        </div>
    </form>
</div>

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
    wrapper.className = 'conf-item';
    wrapper.style.maxWidth = '800px';
    wrapper.innerHTML = `
        <input type="hidden" name="custom[id][]" value="">
        <label>Label</label>
        <input type="text" name="custom[label][]" value="" placeholder="Provider label (e.g., MyService)">
        <label>API Key</label>
        <div style="display:flex; gap:10px; align-items:center;">
            <input type="password" name="custom[api_key][]" value="" placeholder="Paste API key" style="flex:1;">
            <button type="button" class="button" onclick="toggleVisibility(this)">Show</button>
        </div>
    `;
    container.appendChild(wrapper);
}
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
