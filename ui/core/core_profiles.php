<?php

$enginePath = __DIR__.DIRECTORY_SEPARATOR."../../";

require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."logger.php");

require_once "{$enginePath}/lib/core/core_profiles.class.php";

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
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/navbar.php");
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
.wide-centered { max-width: 1100px; margin: 0 auto; }
.two-col-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
.connector-card { background: #2a2a2a; border: 1px solid #4a4a4a; border-radius: 8px; padding: 12px; }
.connector-title { font-family: 'MagicCards', serif; color: rgb(242, 124, 17); margin-bottom: 8px; font-size: 1.1em; }
@media (max-width: 1000px) { .two-col-grid { grid-template-columns: 1fr; } }
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
?>

<h1>Core Profiles</h1>

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

    <?php
    // Preload connector details for reactive previews
    $llmRows = $GLOBALS["db"]->fetchAll("SELECT c.*, b.label AS api_badge_label FROM core_llm_connector c LEFT JOIN core_api_badge b ON b.id=c.api_badge_id ORDER BY c.id ASC");
    $ttsRows = $GLOBALS["db"]->fetchAll("SELECT t.*, b.label AS api_badge_label FROM core_tts_connector t LEFT JOIN core_api_badge b ON b.id=t.api_badge_id ORDER BY t.id ASC");
    $ittRows = $GLOBALS["db"]->fetchAll("SELECT * FROM core_itt_connector ORDER BY id ASC");

    $byId = function($rows){
        $out = [];
        foreach ($rows as $r) { $out[(string)($r['id']??'')] = $r; }
        return $out;
    };
    $llmById = $byId($llmRows);
    $ttsById = $byId($ttsRows);
    $ittById = $byId($ittRows);
    ?>

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
            <div id="preview_diary_connector_id"></div>
        </div>
        <div class="connector-card">
            <div class="connector-title">LLM Primary</div>
            <?= renderSelect($profiles, "llm_primary_id", "LLM Primary", $editItem["llm_primary_id"] ?? "") ?>
            <div id="preview_llm_primary_id"></div>
        </div>
        <div class="connector-card">
            <div class="connector-title">LLM Secondary</div>
            <?= renderSelect($profiles, "llm_secondary_id", "LLM Secondary", $editItem["llm_secondary_id"] ?? "") ?>
            <div id="preview_llm_secondary_id"></div>
        </div>
        <div class="connector-card">
            <div class="connector-title">LLM Tertiary</div>
            <?= renderSelect($profiles, "llm_tertiary_id", "LLM Tertiary", $editItem["llm_tertiary_id"] ?? "") ?>
            <div id="preview_llm_tertiary_id"></div>
        </div>
        <div class="connector-card">
            <div class="connector-title">LLM Quaternary</div>
            <?= renderSelect($profiles, "llm_quaternary_id", "LLM Quaternary", $editItem["llm_quaternary_id"] ?? "") ?>
            <div id="preview_llm_quaternary_id"></div>
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

    function updatePreview(selectId, containerId, type){
        const sel = document.getElementById(selectId);
        const container = document.getElementById(containerId);
        if (!sel || !container) return;
        const id = sel.value || '';
        if (type==='tts'){
            const o = TTS_DETAILS[id];
            container.innerHTML = renderKVList(o, ['label','driver','url','voice_field','api_badge_label'], ['Label','Driver','URL','Voice Field','API Badge']);
        } else if (type==='itt'){
            const o = ITT_DETAILS[id];
            container.innerHTML = renderKVList(o, ['label','driver','metadata'], ['Label','Driver','Metadata']);
        } else if (type==='llm'){
            const o = LLM_DETAILS[id];
            container.innerHTML = renderKVList(o, ['label','provider','model','driver','url','api_badge_label','temperature','max_tokens','enforce_json','prefill_json','reasoning_model'], ['Label','Provider','Model','Driver','URL','API Badge','Temperature','Max Tokens','Enforce JSON','Prefill JSON','Reasoning Model']);
        }
    }

    function initConnectorPreviews(){
        updatePreview('tts_connector_id','preview_tts_connector_id','tts');
        updatePreview('itt_connector_id','preview_itt_connector_id','itt');
        updatePreview('diary_connector_id','preview_diary_connector_id','llm');
        updatePreview('llm_primary_id','preview_llm_primary_id','llm');
        updatePreview('llm_secondary_id','preview_llm_secondary_id','llm');
        updatePreview('llm_tertiary_id','preview_llm_tertiary_id','llm');
        updatePreview('llm_quaternary_id','preview_llm_quaternary_id','llm');

        ['tts_connector_id','itt_connector_id','diary_connector_id','llm_primary_id','llm_secondary_id','llm_tertiary_id','llm_quaternary_id'].forEach(id=>{
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', ()=>{
                if (id==='tts_connector_id') updatePreview(id,'preview_tts_connector_id','tts');
                else if (id==='itt_connector_id') updatePreview(id,'preview_itt_connector_id','itt');
                else if (id==='diary_connector_id') updatePreview(id,'preview_diary_connector_id','llm');
                else if (id==='llm_primary_id') updatePreview(id,'preview_llm_primary_id','llm');
                else if (id==='llm_secondary_id') updatePreview(id,'preview_llm_secondary_id','llm');
                else if (id==='llm_tertiary_id') updatePreview(id,'preview_llm_tertiary_id','llm');
                else if (id==='llm_quaternary_id') updatePreview(id,'preview_llm_quaternary_id','llm');
            });
        });
    }

    document.addEventListener('DOMContentLoaded', initConnectorPreviews);
    </script>

    </form>
</div>


<h2>All Profiles</h2>
<div class="table-container">
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Label</th>
            <th>Default NPC</th>
            <th>Default Narrator</th>
            <th>TTS ID</th>
            <th>ITT ID</th>
            <th>LLM 1</th>
            <th>LLM 2</th>
            <th>LLM 3</th>
            <th>LLM 4</th>
            <th>Diary ID</th>
            <th>Metadata</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($data as $row): ?>
            <tr>
                <td><?= $row["id"] ?></td>
                <td><?= htmlspecialchars($row["label"]??'') ?></td>
                <td><?= htmlspecialchars($row["default_npc"]??'') ?></td>
                <td><?= htmlspecialchars($row["default_narrator"]??'') ?></td>
                <td><?= $ttsOptions[array_search($row["tts_connector_id"], array_column($ttsOptions, 'id'))]['label'] ?? '' ?></td>
                <td><?= $ittOptions[array_search($row["itt_connector_id"], array_column($ittOptions, 'id'))]['label'] ?? '' ?></td>
                <td><?= $llmPrimaryOptions[array_search($row["llm_primary_id"], array_column($llmPrimaryOptions, 'id'))]['label'] ?? '' ?></td>
                <td><?= $llmSecondaryOptions[array_search($row["llm_secondary_id"], array_column($llmSecondaryOptions, 'id'))]['label'] ?? '' ?></td>
                <td><?= $llmTertiaryOptions[array_search($row["llm_tertiary_id"], array_column($llmTertiaryOptions, 'id'))]['label'] ?? '' ?></td>
                <td><?= $llmQuaternaryOptions[array_search($row["llm_quaternary_id"], array_column($llmQuaternaryOptions, 'id'))]['label'] ?? '' ?></td>
                <td><?= $diaryOptions[array_search($row["diary_connector_id"], array_column($diaryOptions, 'id'))]['label'] ?? '' ?></td>
                <td><?= substr(htmlspecialchars($row["metadata"]),0,50) ?></td>
                <td class="actions">
                    <a class="action-button edit" href="?edit=<?= $row["id"] ?>">Edit</a>
                    <a class="btn-danger" href="?delete=<?= $row["id"] ?>" onclick="return confirm('Delete this profile?');">Delete</a>
                    <a class="action-button" href="?clone=<?= $row["id"] ?>">Clone</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php /* moved metadata editor into the form above */ ?>

</main>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
