<?php

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . "../../";

require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");

require_once("{$enginePath}/lib/core/npc_master.class.php");

$CONF_SAMPLE_VARS=extract_assignments("$enginePath/conf/conf.php");

//function renderSelect($obj, $fieldName, $labelText, $selectedValue = "") 
//function include from below file
include(__DIR__."/tmpl/ui_utils.php");

// Determine web root and include site chrome like oghma_upload
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
$TITLE = "🧙 CHIM - NPC Master";
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
$npc = new NpcMaster();

// Handle Create
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create"])) {
    $npc->create($_POST);
    header("Location: npc_master.php");
    exit;
}

// Handle Update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update"])) {
    $npc->update($_POST["id"], $_POST);
    header("Location: npc_master.php");
    exit;
}

// Handle Delete
if (isset($_GET["delete"])) {
    $npc->delete($_GET["delete"]);
    header("Location: npc_master.php");
    exit;
}

// Fetch Data
$data = $npc->getAll("1=1 order by coalesce(gamets_last_updated,0) desc");
$editItem = null;

if (isset($_GET["edit"])) {
    $editItem = $npc->getById($_GET["edit"]);
}
?>

<h1>NPC Master</h1>

<?php if ($editItem): ?>
    <h2>Edit NPC (ID: <?= htmlspecialchars($editItem["id"]) ?>)</h2>
<?php else: ?>
    <h2 onclick='document.forms[0].style.display="block"'>Create New NPC</h2>
<?php endif; ?>

<div class="form-container">
<form method="post" onsubmit='return consolidation()' style='<?= $editItem!=null?"":"display:none"?>'>
    <?php if ($editItem): ?>
        <input type="hidden" name="id" value="<?= htmlspecialchars($editItem["id"]) ?>">
    <?php endif; ?>

    <label for="npc_name">NPC Name</label>
    <input type="text" id="npc_name" name="npc_name" value="<?= htmlspecialchars($editItem["npc_name"] ?? "") ?>">

    <label for="npc_favorite">
        <input type="checkbox" id="npc_favorite" name="npc_favorite" value="1" <?= !empty($editItem["npc_favorite"]) ? "checked" : "" ?>>
        Favorite
    </label><br>

    <label for="lock_profile">
        <input type="checkbox" id="lock_profile" name="lock_profile" value="1" <?= !empty($editItem["lock_profile"]) ? "checked" : "" ?>>
        Lock Profile
    </label><br>

    <label for="prompt_head">Prompt Head</label>
    <textarea id="prompt_head" name="prompt_head"><?= htmlspecialchars($editItem["prompt_head"] ?? "") ?></textarea>

    <label for="core">Core </label>
    <textarea id="core" name="core"><?= htmlspecialchars($editItem["core"] ?? "") ?></textarea>

    <label for="npc_static_bio">Static Bio</label>
    <textarea id="npc_static_bio" name="npc_static_bio"><?= htmlspecialchars($editItem["npc_static_bio"] ?? "") ?></textarea>

    <label for="oghma_knowledge_tags">OGHMA Knowledge Tags</label>
    <textarea id="oghma_knowledge_tags" name="oghma_knowledge_tags"><?= htmlspecialchars($editItem["oghma_knowledge_tags"] ?? "") ?></textarea>

    <label for="emote_moods">Emote Moods</label>
    <textarea id="emote_moods" name="emote_moods"><?= htmlspecialchars($editItem["emote_moods"] ?? "") ?></textarea>

    <label for="personality">Personality</label>
    <textarea id="personality" name="personality"><?= htmlspecialchars($editItem["personality"] ?? "") ?></textarea>

    <label for="relationships">Relationships</label>
    <textarea id="relationships" name="relationships"><?= htmlspecialchars($editItem["relationships"] ?? "") ?></textarea>

    <label for="occupation">Occupation</label>
    <textarea id="occupation" name="occupation"><?= htmlspecialchars($editItem["occupation"] ?? "") ?></textarea>

    <label for="skills">Skills</label>
    <textarea id="skills" name="skills"><?= htmlspecialchars($editItem["skills"] ?? "") ?></textarea>

    <label for="speechstyle">Speech Style</label>
    <textarea id="speechstyle" name="speechstyle"><?= htmlspecialchars($editItem["speechstyle"] ?? "") ?></textarea>

    <label for="goals">Goals</label>
    <textarea id="goals" name="goals"><?= htmlspecialchars($editItem["goals"] ?? "") ?></textarea>

    <label for="voiceid">Voice ID</label>
    <input type="text" id="voiceid" name="voiceid" value="<?= htmlspecialchars($editItem["voiceid"] ?? "") ?>">

    <label for="gender">Gender</label>
    <input type="text" id="gender" name="gender" value="<?= htmlspecialchars($editItem["gender"] ?? "") ?>">

    <label for="base">Base</label>
    <input type="text" id="base" name="base" value="<?= htmlspecialchars($editItem["base"] ?? "") ?>">

    <label for="race">Race</label>
    <input type="text" id="race" name="race" value="<?= htmlspecialchars($editItem["race"] ?? "") ?>">


    <label for="refid">Ref ID</label>
    <input type="text" id="refid" name="refid" value="<?= htmlspecialchars($editItem["refid"] ?? "") ?>">

    <label for="profile_id">Profile ID</label>
    <?= renderSelect($npc, "profile_id", "Profile", $editItem["profile_id"] ?? "") ?>

    <label for="dynamic_profile">
        <input type="checkbox" id="dynamic_profile" name="dynamic_profile" value="1" <?= !empty($editItem["dynamic_profile"]) ? "checked" : "" ?>>
        Dynamic Profile
    </label>

    <label for="tags">Tags</label>
    <input type="text" id="tags" name="tags" value="<?= htmlspecialchars($editItem["tags"] ?? "") ?>">


    <br/>
    <label for="metadata">Metadata (JSON)</label>
    <textarea name="metadata" style="display:none"><?= htmlspecialchars($editItem["metadata"] ?? "") ?></textarea><br>
    <div id="metadata"></div>

    <label for="extended_data">Extended data (JSON)</label>
    <textarea name="extended_data" style="display:none"><?= htmlspecialchars($editItem["extended_data"] ?? "") ?></textarea><br>
    <div id="extended_data"></div>

    <button type="submit" name="<?= $editItem ? "update" : "create" ?>" class="btn-save"><?= $editItem ? "Update" : "Create" ?></button>
</form>
</div>

<h2>All NPCs</h2>
<div class="table-container">
<table id="npc_table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Favorite</th>
            <th>Locked</th>
            <th>Voice</th>
            <th>Race</th>
            <th>Core</th>
            <th>Base</th>
            <th>RefID</th>
            <th>Profile ID</th>
            <th>Last autoupdated</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($data as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row["id"]) ?></td>
            <td><?= htmlspecialchars($row["npc_name"]) ?></td>
            <td><?= !empty($row["npc_favorite"]) ? "✔" : "" ?></td>
            <td><?= !empty($row["lock_profile"]) ? "🔒" : "" ?></td>
            <td class="truncate-multiline"><?= htmlspecialchars($row["voiceid"]??"") ?></td>
            <td ><?= htmlspecialchars($row["race"]??"") ?></td>
            <td ><?= htmlspecialchars($row["core"]??"") ?></td>
            <td><?= htmlspecialchars($row["base"]??"") ?></td>
            <td><?= htmlspecialchars($row["refid"]??"") ?></td>
            <td><?= htmlspecialchars($row["profile_id"]??"") ?></td>
            <td><?= htmlspecialchars($row["gamets_last_updated"]??"") ?></td>
            
            <td class="actions">
                <a class="action-button edit" href="?edit=<?= $row["id"] ?>">Edit</a>
                <a class="btn-danger" href="?delete=<?= $row["id"] ?>" onclick="return confirm('Delete this NPC?');">Delete</a>
                <a class="action-button" href="?tag=<?= $row["id"] ?>">Tag</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php
 // Provides a JSON editor for metadata field and form consolidation function (only needed if metadata field is present)
 include(__DIR__."/tmpl/metadata_json_editor.php");
// Provides Datatables
 include(__DIR__."/tmpl/data_tables.php");
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
