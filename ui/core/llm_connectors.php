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
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/navbar.php");
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">

<main>
    <div id="toast" class="toast-notification">
        <span class="message"></span>
    </div>

<?php
$GLOBALS["db"] = new sql();
$llm = new LLMConnector();

// Handle Create
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create"])) {
    $llm->create($_POST);
    header("Location: llm_connectors.php");
    exit;
}

// Handle Update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update"])) {
    $llm->update($_POST["id"], $_POST);
    header("Location: llm_connectors.php");
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

<h1>LLM Connectors</h1>

<?php if ($editItem): ?>
    <h2>Edit Connector (ID: <?= htmlspecialchars($editItem["id"]) ?>)</h2>
<?php else: ?>
    <h2 onclick='document.forms[0].style.display="block"'>Create New Connector</h2>
<?php endif; ?>

<div class="form-container wide-centered">
<style>
.wide-centered { max-width: 1100px; margin: 0 auto; }
.two-col-llm { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 1000px) { .two-col-llm { grid-template-columns: 1fr; } }
.kv-grid { display: grid; grid-template-columns: 220px 1fr; gap: 8px 12px; align-items: center; }
.inline-num { width: 90px; }
.service-picker { display:flex; align-items:center; gap:12px; margin: 6px 0 12px; }
.service-icons { display:flex; gap:8px; align-items:center; }
.service-icon { width:56px; height:56px; border:1px solid rgba(138,155,182,0.3); border-radius:8px; cursor:pointer; opacity:0.8; }
.service-icon.active { outline:2px solid rgb(242,124,17); opacity:1; }
</style>
<form method="post" onsubmit='return consolidation()' style='<?= $editItem!=null?"":"display:none"?>'>
    <?php if ($editItem): ?>
        <input type="hidden" name="id" value="<?= $editItem["id"] ?>">
    <?php endif; ?>

    <div class="two-col-llm">
        <div>
            <label for='label'>Label</label><br>
            <input type="text" name="label" value="<?= htmlspecialchars($editItem["label"] ?? "") ?>"><br>

            <label>Service</label>
            <div class="service-picker">
                <select id="service_select">
                    <option value="openrouter">OpenRouter</option>
                    <option value="openai">OpenAI</option>
                    <option value="google">Google</option>
                    <option value="kobold">Kobold</option>
                </select>
                <div class="service-icons">
                    <img src="<?= $webRoot; ?>/ui/images/core/icons/openrouter.png" alt="OpenRouter" class="service-icon" data-service="openrouter" />
                    <img src="<?= $webRoot; ?>/ui/images/core/icons/openai.png" alt="OpenAI" class="service-icon" data-service="openai" />
                    <img src="<?= $webRoot; ?>/ui/images/core/icons/google.png" alt="Google" class="service-icon" data-service="google" />
                    <img src="<?= $webRoot; ?>/ui/images/core/icons/kobold.png" alt="Kobold" class="service-icon" data-service="kobold" />
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

            <label for='driver'>Driver</label><br>
            <input type="text" name="driver" value="<?= htmlspecialchars($editItem["driver"] ?? "") ?>"><br>

            <?= renderSelect($llm, "api_badge_id", "API Badge", $editItem["api_badge_id"] ?? "") ?>
        </div>

        <div>
            <?php
            // Numeric controls with ranges based on conf schema/common defaults
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

            echo "<div class='kv-grid'>";
            foreach ($ranges as $field => $conf) {
                $label = ucfirst(str_replace('_',' ', $field));
                $rid = "rng_{$field}";
                $nid = "num_{$field}";
                $val = htmlspecialchars($editItem[$field] ?? "");
                $min = $conf['min'];
                $max = $conf['max'];
                $step = $conf['step'];
                echo "<div><label for='{$rid}'>{$label}</label></div>";
                echo "<div>";
                echo "<input type='range' id='{$rid}' min='{$min}' max='{$max}' step='{$step}' value='{$val}' oninput=\"document.getElementById('{$nid}').value=this.value\">";
                echo "<div style='margin-top:6px;'><input type='number' class='inline-num' id='{$nid}' name='{$field}' min='{$min}' max='{$max}' step='{$step}' value='{$val}' oninput=\"llmClamp('{$rid}','{$nid}',{$min},{$max})\"></div>";
                echo "</div>";
            }
            echo "</div>";

            echo "<label for='max_tokens' style='margin-top:10px; display:block;'>Max Tokens</label>";
            echo "<input type='number' name='max_tokens' value='" . htmlspecialchars($editItem["max_tokens"] ?? "") . "' min='0' step='1'>";
            ?>
        </div>
    </div>

    <label>Is reasoning model:</label>
    
    <input type="radio" name="reasoning_model" value="1" <?= isset($editItem["reasoning_model"]) && $editItem["reasoning_model"] == 1 ? "checked" : "" ?>>
    True
    <input type="radio" name="reasoning_model" value="0" <?= isset($editItem["reasoning_model"]) && $editItem["reasoning_model"] == 0 ? "checked" : "" ?>>
    False
    <br/>

    <label>Enforce JSON:</label><br>
    <input type="radio" name="enforce_json" value="1" <?= isset($editItem["enforce_json"]) && $editItem["enforce_json"] == 1 ? "checked" : "" ?>>
    True
    <input type="radio" name="enforce_json" value="0" <?= isset($editItem["enforce_json"]) && $editItem["enforce_json"] == 0 ? "checked" : "" ?>>
    False


    <label>JSON schema:</label><br>

    <input type="radio" name="json_schema" value="1" <?= isset($editItem["json_schema"]) && $editItem["json_schema"] == 1 ? "checked" : "" ?>>
    True
    <input type="radio" name="json_schema" value="0" <?= isset($editItem["json_schema"]) && $editItem["json_schema"] == 0 ? "checked" : "" ?>>
    False


    <label>Prefill JSON</label><br>
    
    <input type="radio" name="prefill_json" value="1" <?= isset($editItem["prefill_json"]) && $editItem["prefill_json"] == 1 ? "checked" : "" ?>>
    True

    <input type="radio" name="prefill_json" value="0" <?= isset($editItem["prefill_json"]) && $editItem["prefill_json"] == 0 ? "checked" : "" ?>>
    False
    

    <br/>
    <br/>
    <label for='metadata'>Metadata</label><br>
    <textarea name="metadata" style="display:none"><?= htmlspecialchars($editItem["metadata"] ?? "") ?></textarea><br>
    <div id="metadata"></div>

    <button type="submit" name="<?= $editItem ? "update" : "create" ?>" class="btn-save"><?= $editItem ? "Update" : "Create" ?></button>
</form>
</div>

<script>
// Service selection and defaults
(function(){
    const defaults = {
        openrouter: 'https://openrouter.ai/api/v1/chat/completions',
        openai: 'https://api.openai.com/v1/chat/completions',
        google: 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
        kobold: 'http://127.0.0.1:5001'
    };
    const select = document.getElementById('service_select');
    const providerRow = document.getElementById('provider_row');
    const urlInput = document.querySelector('input[name="url"]');
    const icons = document.querySelectorAll('.service-icon');

    function setActive(service){
        icons.forEach(ic=>{
            if (ic.dataset.service === service) ic.classList.add('active'); else ic.classList.remove('active');
        });
    }

    function applyService(service){
        select.value = service;
        if (defaults[service]) urlInput.value = defaults[service];
        providerRow.style.display = (service === 'openrouter') ? '' : 'none';
        setActive(service);
    }

    // Initialize from current URL heuristic
    (function init(){
        let service = 'openrouter';
        const val = (urlInput && urlInput.value) ? urlInput.value : '';
        if (val.includes('openai.com')) service = 'openai';
        else if (val.includes('generativelanguage.googleapis.com')) service = 'google';
        else if (val.includes('127.0.0.1') || val.includes('localhost')) service = 'kobold';
        applyService(service);
    })();

    select.addEventListener('change', (e)=>{
        applyService(e.target.value);
    });

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

<h2>All LLM Connectors</h2>
<div class="table-container">
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Label</th>
            <th>Provider</th>
            <th>Model</th>
            <th>Driver</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($data as $row): ?>
        <tr>
            <td><?= $row["id"] ?></td>
            <td><?= htmlspecialchars($row["label"]) ?></td>
            <td><?= htmlspecialchars($row["provider"]) ?></td>
            <td><?= htmlspecialchars($row["model"]) ?></td>
            <td><?= htmlspecialchars($row["driver"]) ?></td>
            <td class="actions">
                <a class="action-button edit" href="?edit=<?= $row["id"] ?>">Edit</a>
                <a class="btn-danger" href="?delete=<?= $row["id"] ?>" onclick="return confirm('Delete this connector?');">Delete</a>
                <a class="action-button" href="?clone=<?= $row["id"] ?>">Clone</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

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

