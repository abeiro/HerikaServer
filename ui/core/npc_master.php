<?php

$enginePath = __DIR__ . DIRECTORY_SEPARATOR . "../../";

require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");

require_once("{$enginePath}/lib/core/npc_master.class.php");

//function renderSelect($obj, $fieldName, $labelText, $selectedValue = "") 
//function include from below file
include(__DIR__."/tmpl/ui_utils.php");


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
$data = $npc->getAll();
$editItem = null;

if (isset($_GET["edit"])) {
    $editItem = $npc->getById($_GET["edit"]);
}

$pageTitle = "NPC Master";
include("tmpl/header.php");
?>

<h1>NPC Master</h1>

<?php if ($editItem): ?>
    <h2>Edit NPC (ID: <?= htmlspecialchars($editItem["id"]) ?>)</h2>
<?php else: ?>
    <h2 onclick='document.forms[0].style.display="block"'>Create New NPC</h2>
<?php endif; ?>

<form method="post" onsubmit='return consolidation()' style='<?= $editItem!=null?'':"display:none"?>'>
    <?php if ($editItem): ?>
        <input type="hidden" name="id" value="<?= htmlspecialchars($editItem["id"]) ?>">
    <?php endif; ?>

    <label>NPC Name</label>
    <input type="text" name="npc_name" value="<?= htmlspecialchars($editItem["npc_name"] ?? "") ?>">

    <label>
        <input type="checkbox" name="npc_favorite" value="1" <?= !empty($editItem["npc_favorite"]) ? "checked" : "" ?>>
        Favorite
    </label><br>

    <label>
        <input type="checkbox" name="lock_profile" value="1" <?= !empty($editItem["lock_profile"]) ? "checked" : "" ?>>
        Lock Profile
    </label><br>

    <label>Prompt Head</label>
    <input type="text" name="prompt_head" value="<?= htmlspecialchars($editItem["prompt_head"] ?? "") ?>">

    <label>Static Bio</label>
    <textarea name="npc_static_bio"><?= htmlspecialchars($editItem["npc_static_bio"] ?? "") ?></textarea>

    <label>Dynamic Bio</label>
    <textarea name="npc_dynamic_bio"><?= htmlspecialchars($editItem["npc_dynamic_bio"] ?? "") ?></textarea>

    <label>OGHMA Knowledge Tags</label>
    <textarea name="oghma_knowledge_tags"><?= htmlspecialchars($editItem["oghma_knowledge_tags"] ?? "") ?></textarea>

    <label>Emote Moods</label>
    <textarea name="emote_moods"><?= htmlspecialchars($editItem["emote_moods"] ?? "") ?></textarea>

    <label>Personality</label>
    <textarea name="personality"><?= htmlspecialchars($editItem["personality"] ?? "") ?></textarea>

    <label>Relationships</label>
    <textarea name="relationships"><?= htmlspecialchars($editItem["relationships"] ?? "") ?></textarea>

    <label>Occupation</label>
    <textarea name="occupation"><?= htmlspecialchars($editItem["occupation"] ?? "") ?></textarea>

    <label>Skills</label>
    <textarea name="skills"><?= htmlspecialchars($editItem["skills"] ?? "") ?></textarea>

    <label>Speech Style</label>
    <textarea name="speechstyle"><?= htmlspecialchars($editItem["speechstyle"] ?? "") ?></textarea>

    <label>Goals</label>
    <textarea name="goals"><?= htmlspecialchars($editItem["goals"] ?? "") ?></textarea>

    <label>Voice ID</label>
    <input type="text" name="voiceid" value="<?= htmlspecialchars($editItem["voiceid"] ?? "") ?>">

    <label>Gender</label>
    <input type="text" name="gender" value="<?= htmlspecialchars($editItem["gender"] ?? "") ?>">

    <label>Race</label>
    <input type="text" name="race" value="<?= htmlspecialchars($editItem["race"] ?? "") ?>">

    <label>Ref ID</label>
    <input type="text" name="refid" value="<?= htmlspecialchars($editItem["refid"] ?? "") ?>">

    <label>Profile ID</label>
    <?= renderSelect($npc, "profile_id", "Profile", $editItem["profile_id"] ?? "") ?>

    <label>
        <input type="checkbox" name="dynamic_profile" value="1" <?= !empty($editItem["dynamic_profile"]) ? "checked" : "" ?>>
        Dynamic Profile
    </label>

    <br/>
    <label>Metadata (JSON)</label>
    <textarea name="metadata" style="display:none"><?= htmlspecialchars($editItem["metadata"] ?? "") ?></textarea><br>
    <div id="metadata"></div>

    <input type="submit" name="<?= $editItem ? "update" : "create" ?>" value="<?= $editItem ? "Update" : "Create" ?>">
</form>

<h2>All NPCs</h2>
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Favorite</th>
            <th>Locked</th>
            <th>Voice</th>
            <th>Prompt</th>
            <th>Occupation</th>
            <th>Goals</th>
            <th>Race</th>
            <th>RefID</th>
            <th>Profile ID</th>
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
            <td class="truncate-multiline"><?= htmlspecialchars($row["voiceid"]) ?></td>
            <td class="truncate-multiline"><?= htmlspecialchars($row["prompt_head"]) ?></td>
            <td class="truncate-multiline"><?= htmlspecialchars($row["occupation"]) ?></td>
            <td class="truncate-multiline"><?= htmlspecialchars($row["goals"]) ?></td>
            <td ><?= htmlspecialchars($row["race"]) ?></td>
            <td><?= htmlspecialchars($row["refid"]) ?></td>
            <td><?= htmlspecialchars($row["profile_id"]) ?></td>
            <td class="actions">
                <a href="?edit=<?= $row["id"] ?>">Edit</a>
                <a href="?delete=<?= $row["id"] ?>" onclick="return confirm('Delete this NPC?');">Delete</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>

<?php
 // Provides a JSON editor for metadata field and form consolidation function (only needed if metadata field is present)
 include(__DIR__."/tmpl/metadata_json_editor.php");
 ?>
</html>
