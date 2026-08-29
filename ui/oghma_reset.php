<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'profile_loader.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'oghma_catalog.php';

function oghmaCatalogH($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Render the projection row counts as "factory 1234 - custom 12" instead of raw JSON. */
function oghmaCatalogCounts(array $counts): string
{
    $parts = [];
    foreach ($counts as $row) {
        $parts[] = (string) ($row['source_type'] ?? '') . ' ' . intval($row['count'] ?? 0);
    }
    return $parts ? implode(' &middot; ', array_map('oghmaCatalogH', $parts)) : 'none';
}

$manager = new ChimOghmaCatalogManager($GLOBALS['db'], dirname(__DIR__));
$action = strtolower(trim((string) ($_POST['action'] ?? 'plan')));

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'apply') {
        $restoreHidden = isset($_POST['restore_hidden']) && chimOghmaBool($_POST['restore_hidden']);
        $result = $manager->provisionActivePackage($restoreHidden);
        $message = sprintf(
            'Factory catalog %s synced: %d factory rows, %d custom topic collisions preserved, %d hidden factory rows preserved.',
            (string) ($result['catalog_version'] ?? ''),
            intval($result['projected'] ?? 0),
            intval($result['custom_collisions'] ?? 0),
            intval($result['hidden'] ?? 0)
        );
        header('Location: oghma_upload.php?' . http_build_query(['message' => $message]));
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($action, ['plan', 'apply'], true)) {
        throw new InvalidArgumentException('Unknown catalog operation.');
    }

    $plan = $manager->plan($manager->activePackagePath());
    $status = $manager->status();
    $active = $manager->activeCatalog();
    $inSync = isset($active['catalog_version'])
        && (string) $active['catalog_version'] === (string) $plan['catalog_version']
        && (string) ($active['articles_sha256'] ?? '') === (string) $plan['articles_sha256']
        && (string) ($active['manifest_sha256'] ?? '') === (string) $plan['manifest_sha256'];
} catch (Throwable $error) {
    http_response_code(500);
    $failure = $error->getMessage();
}

?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>CHIM Oghma Factory Catalog</title><link rel="stylesheet" href="css/main.css">
<style>body{background:#171717;color:#eee;font-family:Arial,sans-serif}.wrap{max-width:920px;margin:auto;padding:24px}.panel{background:#242424;border:1px solid #444;border-radius:10px;padding:16px;margin:12px 0}code{overflow-wrap:anywhere}.actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.danger{border-color:#9f5537}.error{color:#ff8f8f}</style>
</head><body><main class="wrap"><h1>Oghma Factory Catalog</h1>
<?php if (isset($failure)): ?>
    <div class="panel error"><strong>Validation failed.</strong><br><?= oghmaCatalogH($failure) ?></div>
<?php else: ?>
    <div class="panel">
        <p>The catalog packaged with this install is the only factory dataset. There is no separate catalog history to choose from.</p>
        <strong>Validation:</strong> passed<br>
        <strong>Contract:</strong> <?= oghmaCatalogH(CHIM_OGHMA_PARITY_VERSION) ?><br>
        <strong>Packaged version:</strong> <?= oghmaCatalogH($plan['catalog_version']) ?><br>
        <strong>Rows:</strong> <?= count($plan['articles']) ?><br>
        <strong>Articles checksum:</strong> <code><?= oghmaCatalogH($plan['articles_sha256']) ?></code><br>
        <strong>Manifest checksum:</strong> <code><?= oghmaCatalogH($plan['manifest_sha256']) ?></code><br>
        <strong>Database:</strong>
        <?php if ($inSync): ?>
            already in sync with the packaged catalog
        <?php elseif (isset($active['catalog_version'])): ?>
            not in sync with the packaged catalog
        <?php else: ?>
            packaged catalog not applied yet
        <?php endif; ?>
        <br>
        <strong>Projected rows:</strong> <?= oghmaCatalogCounts($status['projection_counts']) ?>
    </div>
    <div class="panel danger">
        <p>Sync imports and projects the validated packaged catalog atomically. <strong>Your custom entries are always preserved</strong>, along with any factory rows you chose to hide.</p>
        <form method="post" onsubmit="return confirm(this.restore_hidden.checked ? 'Sync the packaged factory catalog and restore every factory row you previously hid? Custom entries are preserved.' : 'Sync the packaged factory catalog? Custom entries are preserved.');">
            <input type="hidden" name="action" value="apply">
            <p><label><input type="checkbox" name="restore_hidden" value="true"> Also restore factory rows I previously hid</label></p>
            <div class="actions">
                <button type="submit">Sync factory catalog</button>
                <a href="oghma_upload.php">Cancel</a>
            </div>
        </form>
    </div>
<?php endif; ?>
</main></body></html>
