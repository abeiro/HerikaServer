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

$pageTitle = "NPC Master";
include("tmpl/header.php");
?>

<h1>LLM Connectors</h1>

<?php if ($editItem): ?>
    <h2>Edit Connector (ID: <?= htmlspecialchars($editItem["id"]) ?>)</h2>
<?php else: ?>
    <h2 onclick='document.forms[0].style.display="block"'>Create New Connector</h2>
<?php endif; ?>

<form method="post" onsubmit='return consolidation()' style='<?= $editItem!=null?'':"display:none"?>'>
    <?php if ($editItem): ?>
        <input type="hidden" name="id" value="<?= $editItem["id"] ?>">
    <?php endif; ?>

    <label for='label'>Label</label><br>
    <input type="text" name="label" value="<?= htmlspecialchars($editItem["label"] ?? "") ?>"><br>

    <label for='url'>URL</label><br>
    <input type="text" name="url" value="<?= htmlspecialchars($editItem["url"] ?? "") ?>"><br>

    <label for='model'>Model</label><br>
    <input type="text" name="model" value="<?= htmlspecialchars($editItem["model"] ?? "") ?>"><br>

    <label for='provider'>Provider</label><br>
    <input type="text" name="provider" value="<?= htmlspecialchars($editItem["provider"] ?? "") ?>"><br>

    <label for='driver'>Driver</label><br>
    <input type="text" name="driver" value="<?= htmlspecialchars($editItem["driver"] ?? "") ?>"><br>

   

    <?= renderSelect($llm, "api_badge_id", "API Badge", $editItem["api_badge_id"] ?? "") ?>


    <?php
    // Numeric float fields
    $floats = ["temperature", "presence_penalty", "frequency_penalty", "repetition_penalty", "top_p", "top_k", "min_p", "top_a"];
    foreach ($floats as $field) {
        echo "<label for='{$field}'>" . ucfirst(str_replace("_", " ", $field)) . "</label><br>";
        echo "<input type='text' name='{$field}' value='" . htmlspecialchars($editItem[$field] ?? "") . "'><br>";
    }

    echo "<label for='max_tokens'>Max Tokens</label><br>";
    echo "<input type='text' name='max_tokens' value='" . htmlspecialchars($editItem["max_tokens"] ?? "") . "'><br>";
    ?>

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

    <input type="submit" name="<?= $editItem ? "update" : "create" ?>" value="<?= $editItem ? "Update" : "Create" ?>">
</form>

<h2>All LLM Connectors</h2>
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
                <a href="?edit=<?= $row["id"] ?>">Edit</a>
                <a href="?delete=<?= $row["id"] ?>" onclick="return confirm('Delete this connector?');">Delete</a>
                <a href="?clone=<?= $row["id"] ?>">Clone</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

</body>
</body>

<?php
 // Provides a JSON editor for metadata field and form consolidation function (only needed if metadata field is present)
 include(__DIR__."/tmpl/metadata_json_editor.php");
 ?>

</html>

