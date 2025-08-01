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
    $profiles->create($_POST);
    header("Location: core_profiles.php");
    exit;
}

// Handle Update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update"])) {
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

// Fetch Data
$data = $profiles->readAll();
$editItem = null;

if (isset($_GET["edit"])) {
    $editItem = $profiles->getById($_GET["edit"]);
}

$pageTitle = "NPC Master";
include("tmpl/header.php");
?>

<h1>Core Profiles</h1>

<?php if ($editItem): ?>
    <h2>Edit Profile (ID: <?= htmlspecialchars($editItem["id"]) ?>)</h2>
<?php else: ?>
    <h2 onclick='document.forms[0].style.display="block"'>Create New Profile</h2>
<?php endif; ?>

<form method="post" onsubmit='return consolidation()' style='<?= $editItem!=null?'':"display:none"?>'>
    <?php if ($editItem): ?>
        <input type="hidden" name="id" value="<?= $editItem["id"] ?>">
    <?php endif; ?>

    <label for='label'>Label</label><br>
    <input type="text" name="label" placeholder="Label" value="<?= htmlspecialchars($editItem["label"] ?? "") ?>">
    
    <label>
        <input type="checkbox" name="default_npc" value="1" <?= !empty($editItem["default_npc"]) ? "checked" : "" ?>>
        Default NPC
    </label>
    <br>
    <label>
        <input type="checkbox" name="default_narrator" value="1" <?= !empty($editItem["default_narrator"]) ? "checked" : "" ?>>
        Default Narrator
    </label>
    <br>

    <?= renderSelect($profiles, "tts_connector_id", "TTS Connector", $editItem["tts_connector_id"] ?? "") ?>
    <?= renderSelect($profiles, "itt_connector_id", "ITT Connector", $editItem["itt_connector_id"] ?? "") ?>
    <?= renderSelect($profiles, "diary_connector_id", "Diary Connector", $editItem["diary_connector_id"] ?? "") ?>
    <?= renderSelect($profiles, "llm_primary_id", "LLM Primary", $editItem["llm_primary_id"] ?? "") ?>
    <?= renderSelect($profiles, "llm_secondary_id", "LLM Secondary", $editItem["llm_secondary_id"] ?? "") ?>
    <?= renderSelect($profiles, "llm_tertiary_id", "LLM Tertiary", $editItem["llm_tertiary_id"] ?? "") ?>
    <?= renderSelect($profiles, "llm_quaternary_id", "LLM Quaternary", $editItem["llm_quaternary_id"] ?? "") ?>


    <!-- Metadata -->
    <textarea name="metadata" style="display:none" placeholder="Metadata"><?= htmlspecialchars($editItem["metadata"] ?? "") ?></textarea>
    <div id="metadata"></div>

    <input type="submit" name="<?= $editItem ? "update" : "create" ?>" value="<?= $editItem ? "Update" : "Create" ?>">
</form>


<h2>All Profiles</h2>
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
                <td><?= htmlspecialchars($row["label"]) ?></td>
                <td><?= htmlspecialchars($row["default_npc"]) ?></td>
                <td><?= htmlspecialchars($row["default_narrator"]) ?></td>
                <td><?= $ttsOptions[array_search($row["tts_connector_id"], array_column($ttsOptions, 'id'))]['label'] ?? '' ?></td>
                <td><?= $ittOptions[array_search($row["itt_connector_id"], array_column($ittOptions, 'id'))]['label'] ?? '' ?></td>
                <td><?= $llmPrimaryOptions[array_search($row["llm_primary_id"], array_column($llmPrimaryOptions, 'id'))]['label'] ?? '' ?></td>
                <td><?= $llmSecondaryOptions[array_search($row["llm_secondary_id"], array_column($llmSecondaryOptions, 'id'))]['label'] ?? '' ?></td>
                <td><?= $llmTertiaryOptions[array_search($row["llm_tertiary_id"], array_column($llmTertiaryOptions, 'id'))]['label'] ?? '' ?></td>
                <td><?= $llmQuaternaryOptions[array_search($row["llm_quaternary_id"], array_column($llmQuaternaryOptions, 'id'))]['label'] ?? '' ?></td>
                <td><?= $diaryOptions[array_search($row["diary_connector_id"], array_column($diaryOptions, 'id'))]['label'] ?? '' ?></td>
                <td><?= substr(htmlspecialchars($row["metadata"]),0,50) ?></td>
                <td class="actions">
                    <a href="?edit=<?= $row["id"] ?>">Edit</a>
                    <a href="?delete=<?= $row["id"] ?>" onclick="return confirm('Delete this profile?');">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>

<?php include(__DIR__."/tmpl/metadata_json_editor.php");?>

</html>
