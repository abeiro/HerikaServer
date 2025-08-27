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
$TITLE = "📛 CHIM - API Badges";
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
?>

<h1>API Badges</h1>

<?php if ($editItem): ?>
    <h2>Edit Badge (ID: <?= htmlspecialchars($editItem["id"]) ?>)</h2>
<?php else: ?>
    <h2 onclick='document.forms[0].style.display="block"'>Create New Badge</h2>
<?php endif; ?>

<div class="form-container">
<form method="post" onsubmit='return consolidation()' style='<?= $editItem!=null?"":"display:none"?>'>
    <?php if ($editItem): ?>
        <input type="hidden" name="id" value="<?= htmlspecialchars($editItem["id"]) ?>">
    <?php endif; ?>

    <label for='label'>Label</label><br>
    <input type="text" name="label" placeholder="Label" value="<?= htmlspecialchars($editItem["label"] ?? "") ?>"><br>

    <label for='api_key'>API Key</label><br>
    <input type="text" name="api_key" placeholder="API Key" value="<?= htmlspecialchars($editItem["api_key"] ?? "") ?>"><br>

    <button type="submit" name="<?= $editItem ? "update" : "create" ?>" class="btn-save"><?= $editItem ? "Update" : "Create" ?></button>
</form>
</div>

<h2>All API Badges</h2>
<div class="table-container">
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
                    <a class="action-button edit" href="?edit=<?= $row["id"] ?>">Edit</a>
                    <a class="btn-danger" href="?delete=<?= $row["id"] ?>" onclick="return confirm('Delete this badge?');">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

</main>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/footer.html");
$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
