<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'profile_loader.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'oghma_catalog.php';

function oghmaCatalogH($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$manager = new ChimOghmaCatalogManager($GLOBALS['db'], dirname(__DIR__));
$action = strtolower(trim((string) ($_POST['action'] ?? 'plan')));

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'apply') {
        $restoreHidden = isset($_POST['restore_hidden']) && chimOghmaBool($_POST['restore_hidden']);
        $result = $manager->provisionActivePackage($restoreHidden);
        $message = sprintf(
            'Factory catalog %s activated: %d factory rows, %d custom collisions preserved, %d hidden factory rows preserved.',
            (string) ($result['catalog_version'] ?? ''),
            intval($result['projected'] ?? 0),
            intval($result['custom_collisions'] ?? 0),
            intval($result['hidden'] ?? 0)
        );
        header('Location: oghma_upload.php?' . http_build_query(['message' => $message]));
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'rollback') {
        $result = $manager->rollback(trim((string) ($_POST['catalog_version'] ?? '')) ?: null);
        header('Location: oghma_upload.php?' . http_build_query([
            'message' => 'Rolled back to factory catalog ' . (string) ($result['catalog_version'] ?? '')
                . '; custom entries and factory hides were preserved.',
        ]));
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($action, ['plan', 'apply', 'rollback'], true)) {
        throw new InvalidArgumentException('Unknown catalog operation.');
    }

    $plan = $manager->plan($manager->activePackagePath());
    $status = $manager->status();
    $active = $manager->activeCatalog();
} catch (Throwable $error) {
    http_response_code(500);
    $failure = $error->getMessage();
}

?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>CHIM Oghma Catalog Plan</title><link rel="stylesheet" href="css/main.css">
<style>body{background:#171717;color:#eee;font-family:Arial,sans-serif}.wrap{max-width:920px;margin:auto;padding:24px}.panel{background:#242424;border:1px solid #444;border-radius:10px;padding:16px;margin:12px 0}code{overflow-wrap:anywhere}.actions{display:flex;gap:10px;flex-wrap:wrap}.danger{border-color:#9f5537}.error{color:#ff8f8f}</style>
</head><body><main class="wrap"><h1>Oghma Catalog Plan</h1>
<?php if (isset($failure)): ?>
    <div class="panel error"><strong>Validation failed.</strong><br><?= oghmaCatalogH($failure) ?></div>
<?php else: ?>
    <div class="panel">
        <strong>Validation:</strong> passed<br>
        <strong>Contract:</strong> <?= oghmaCatalogH(CHIM_OGHMA_PARITY_VERSION) ?><br>
        <strong>Packaged version:</strong> <?= oghmaCatalogH($plan['catalog_version']) ?><br>
        <strong>Rows:</strong> <?= count($plan['articles']) ?><br>
        <strong>Articles checksum:</strong> <code><?= oghmaCatalogH($plan['articles_sha256']) ?></code><br>
        <strong>Manifest checksum:</strong> <code><?= oghmaCatalogH($plan['manifest_sha256']) ?></code>
    </div>
    <div class="panel">
        <strong>Active version:</strong> <?= oghmaCatalogH($active['catalog_version'] ?? 'not activated') ?><br>
        <strong>Previous version:</strong> <?= oghmaCatalogH($active['previous_catalog_version'] ?? 'none') ?><br>
        <strong>Projection:</strong> <?= oghmaCatalogH(json_encode($status['projection_counts'], JSON_UNESCAPED_SLASHES)) ?>
    </div>
    <div class="panel danger">
        <p>The plan above is read-only. Apply imports and activates the validated package atomically. Custom rows and factory hides are preserved by default.</p>
        <div class="actions">
            <form method="post"><input type="hidden" name="action" value="apply"><button type="submit" onclick="return confirm('Activate this validated factory catalog?');">Apply catalog</button></form>
            <?php if (!empty($active['previous_catalog_version'])): ?>
                <form method="post"><input type="hidden" name="action" value="rollback"><button type="submit" onclick="return confirm('Rollback the factory projection?');">Rollback to <?= oghmaCatalogH($active['previous_catalog_version']) ?></button></form>
            <?php endif; ?>
            <a href="oghma_upload.php">Cancel</a>
        </div>
        <form method="post" style="margin-top:16px"><input type="hidden" name="action" value="apply"><label><input type="checkbox" name="restore_hidden" value="true"> Restore factory rows I previously hid</label> <button type="submit" onclick="return confirm('Activate the catalog and restore all hidden factory rows?');">Apply and restore hidden rows</button></form>
    </div>
<?php endif; ?>
</main></body></html>
