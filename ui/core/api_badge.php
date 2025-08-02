<?php

$enginePath = __DIR__.DIRECTORY_SEPARATOR."../../";

require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."logger.php");

require_once "{$enginePath}/lib/core/api_badge.class.php";

$GLOBALS["db"] = new sql();
$apiBadge = new ApiBadge();

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
    $apiBadge->delete($_GET["delete"]);
    header("Location: api_badge.php");
    exit;
}

// Fetch Data
$data = $apiBadge->getAll();
$editItem = null;

if (isset($_GET["edit"])) {
    $editItem = $apiBadge->getById($_GET["edit"]);
}

$pageTitle = "NPC Master";
include("tmpl/header.php");
?>

<h1>API Badges</h1>

<?php if ($editItem): ?>
    <h2>Edit Badge (ID: <?= htmlspecialchars($editItem["id"]) ?>)</h2>
<?php else: ?>
    <h2 onclick='document.forms[0].style.display="block"'>Create New Badge</h2>
<?php endif; ?>

<form method="post" onsubmit='return consolidation()' style='<?= $editItem!=null?'':"display:none"?>'>
    <?php if ($editItem): ?>
        <input type="hidden" name="id" value="<?= htmlspecialchars($editItem["id"]) ?>">
    <?php endif; ?>

    <label for='label'>Label</label><br>
    <input type="text" name="label" placeholder="Label" value="<?= htmlspecialchars($editItem["label"] ?? "") ?>"><br>

    <label for='api_key'>API Key</label><br>
    <input type="text" name="api_key" placeholder="API Key" value="<?= htmlspecialchars($editItem["api_key"] ?? "") ?>"><br>

    <input type="submit" name="<?= $editItem ? "update" : "create" ?>" value="<?= $editItem ? "Update" : "Create" ?>">
</form>

<h2>All API Badges</h2>
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Label</th>
            <th>API Key</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($data as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row["id"]) ?></td>
                <td><?= htmlspecialchars($row["label"]) ?></td>
                <td style='filter:blur(3px)'><?= htmlspecialchars($row["api_key"]) ?></td>
                <td class="actions">
                    <a href="?edit=<?= $row["id"] ?>">Edit</a>
                    <a href="?delete=<?= $row["id"] ?>" onclick="return confirm('Delete this badge?');">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
