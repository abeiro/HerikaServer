<?php

$enginePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once $enginePath . "ui" . DIRECTORY_SEPARATOR . "profile_loader.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php";
chimRuntimeBootstrapIfNeeded($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "quest_asset_library.php";

$db = $GLOBALS['db'];
$isEmbed = isset($_GET['embed']) && $_GET['embed'] === '1';
$scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
$uiPos = strpos($scriptPath, '/ui/');
$webRoot = $uiPos !== false ? substr($scriptPath, 0, $uiPos) : '';
$webRoot = rtrim($webRoot, '/');

function quest_asset_ui_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function quest_asset_ui_redirect_url($message, $error = false)
{
    $params = ['embed' => '1', $error ? 'error' : 'message' => $message];
    return ($_SERVER['PHP_SELF'] ?? 'quest_asset_library.php') . '?' . http_build_query($params);
}

if (isset($_GET['download_pack'])) {
    $manifest = quest_asset_export_manifest($_GET['download_pack']);
    if ($manifest === null) {
        http_response_code(404);
        echo 'Asset pack not found.';
        exit;
    }
    $filename = preg_replace('/[^a-z0-9_.-]+/i', '_', $manifest['pack']['key']) . '.json';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    exit;
}

$message = trim((string) ($_GET['message'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'import_manifest') {
            $upload = $_FILES['manifest'] ?? null;
            if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Choose a readable JSON manifest to import.');
            }
            if (($upload['size'] ?? 0) > 25 * 1024 * 1024) {
                throw new RuntimeException('Manifest exceeds the 25 MB import limit.');
            }
            $decoded = json_decode(file_get_contents($upload['tmp_name']), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Invalid JSON: ' . json_last_error_msg());
            }
            $result = quest_asset_import_manifest($decoded, basename((string) ($upload['name'] ?? 'upload.json')));
            if (empty($result['success'])) {
                throw new RuntimeException(implode('; ', $result['errors'] ?? ['Manifest import failed.']));
            }
            $message = "Imported {$result['assets']} assets and {$result['groups']} groups into {$result['pack_key']}.";
        } elseif ($action === 'toggle_pack') {
            $packKey = quest_asset_normalize_key($_POST['pack_key'] ?? '');
            if ($packKey === null) {
                throw new RuntimeException('Invalid pack key.');
            }
            $packKeyCn = $db->escape($packKey);
            $activeSql = !empty($_POST['active']) ? 'true' : 'false';
            quest_asset_exec_or_throw("UPDATE public.quest_asset_packs SET active = {$activeSql}, updated_at = now() WHERE pack_key = '{$packKeyCn}'");
            $message = 'Asset pack status updated.';
        } elseif ($action === 'update_asset') {
            $packKey = quest_asset_normalize_key($_POST['pack_key'] ?? '');
            $stableRef = quest_asset_normalize_stable_ref($_POST['stable_ref'] ?? '');
            $safety = strtolower(trim((string) ($_POST['safety_status'] ?? 'review')));
            if ($packKey === null || $stableRef === null || !in_array($safety, ['approved', 'review', 'rejected'], true)) {
                throw new RuntimeException('Invalid asset update.');
            }
            $packKeyCn = $db->escape($packKey);
            $stableRefCn = $db->escape($stableRef);
            $safetyCn = $db->escape($safety);
            $activeSql = !empty($_POST['active']) ? 'true' : 'false';
            quest_asset_exec_or_throw("
                UPDATE public.quest_assets
                   SET safety_status = '{$safetyCn}', active = {$activeSql}, updated_at = now()
                 WHERE source_pack = '{$packKeyCn}' AND stable_ref = '{$stableRefCn}'
            ");
            $message = 'Asset review status updated.';
        } elseif ($action === 'toggle_group') {
            $packKey = quest_asset_normalize_key($_POST['pack_key'] ?? '');
            $dataset = strtolower(trim((string) ($_POST['dataset'] ?? '')));
            $groupKey = quest_asset_normalize_key($_POST['group_key'] ?? '');
            if ($packKey === null || $groupKey === null || !isset(quest_asset_dataset_signatures()[$dataset])) {
                throw new RuntimeException('Invalid group update.');
            }
            $packKeyCn = $db->escape($packKey);
            $datasetCn = $db->escape($dataset);
            $groupKeyCn = $db->escape($groupKey);
            $activeSql = !empty($_POST['active']) ? 'true' : 'false';
            quest_asset_exec_or_throw("
                UPDATE public.quest_asset_groups
                   SET active = {$activeSql}, updated_at = now()
                 WHERE source_pack = '{$packKeyCn}'
                   AND dataset_name = '{$datasetCn}'
                   AND group_key = '{$groupKeyCn}'
            ");
            $message = 'Asset group status updated.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    header('Location: ' . quest_asset_ui_redirect_url($error !== '' ? $error : $message, $error !== ''));
    exit;
}

$tablesReady = quest_asset_library_tables_ready();
$packs = [];
$assets = [];
$groups = [];
$selectedPack = quest_asset_normalize_key($_GET['pack'] ?? '') ?: '';
$selectedSafety = strtolower(trim((string) ($_GET['safety'] ?? '')));
$selectedDataset = strtolower(trim((string) ($_GET['dataset'] ?? '')));
$search = trim((string) ($_GET['q'] ?? ''));

if ($tablesReady) {
    $packs = $db->fetchAll("
        SELECT p.*,
               (SELECT count(*) FROM public.quest_assets a WHERE a.source_pack = p.pack_key) AS asset_count,
               (SELECT count(*) FROM public.quest_assets a WHERE a.source_pack = p.pack_key AND a.safety_status = 'approved') AS approved_count,
               (SELECT count(*) FROM public.quest_assets a WHERE a.source_pack = p.pack_key AND a.safety_status = 'review') AS review_count,
               (SELECT count(*) FROM public.quest_asset_groups g WHERE g.source_pack = p.pack_key) AS group_count
        FROM public.quest_asset_packs p
        ORDER BY p.label, p.pack_key
    ");

    $where = [];
    if ($selectedPack !== '') {
        $where[] = "a.source_pack = '" . $db->escape($selectedPack) . "'";
    }
    if (in_array($selectedSafety, ['approved', 'review', 'rejected'], true)) {
        $where[] = "a.safety_status = '" . $db->escape($selectedSafety) . "'";
    }
    if ($search !== '') {
        $searchCn = $db->escape($search);
        $where[] = "(a.stable_ref ILIKE '%{$searchCn}%' OR a.editor_id ILIKE '%{$searchCn}%' OR a.display_name ILIKE '%{$searchCn}%')";
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $assets = $db->fetchAll("
        SELECT a.*
        FROM public.quest_assets a
        {$whereSql}
        ORDER BY CASE a.safety_status WHEN 'review' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END,
                 a.source_pack, a.signature, a.editor_id, a.stable_ref
        LIMIT 300
    ");

    $groupWhere = [];
    if ($selectedPack !== '') {
        $groupWhere[] = "g.source_pack = '" . $db->escape($selectedPack) . "'";
    }
    if (isset(quest_asset_dataset_signatures()[$selectedDataset])) {
        $groupWhere[] = "g.dataset_name = '" . $db->escape($selectedDataset) . "'";
    }
    $groupWhereSql = $groupWhere ? 'WHERE ' . implode(' AND ', $groupWhere) : '';
    $groups = $db->fetchAll("
        SELECT g.*,
               (SELECT count(*) FROM public.quest_asset_group_members m
                 WHERE m.source_pack = g.source_pack
                   AND m.dataset_name = g.dataset_name
                   AND m.group_key = g.group_key) AS member_count
        FROM public.quest_asset_groups g
        {$groupWhereSql}
        ORDER BY g.source_pack, g.dataset_name, g.group_key
        LIMIT 400
    ");
}

$TITLE = 'Quest Asset Library';
ob_start();
include $enginePath . 'ui' . DIRECTORY_SEPARATOR . 'tmpl' . DIRECTORY_SEPARATOR . 'head.html';
?>
<link rel="stylesheet" href="<?php echo quest_asset_ui_h($webRoot); ?>/ui/css/main.css">
<style>
main { padding: <?php echo $isEmbed ? '18px' : '80px 18px 30px'; ?>; }
.asset-shell { color: #ececec; }
.asset-header, .asset-panel, .pack-card { border: 1px solid #414141; background: #232323; border-radius: 8px; }
.asset-header { padding: 16px 18px; margin-bottom: 12px; }
.asset-header h1 { margin: 0 0 5px; color: #f27c11; font-family: MagicCards, sans-serif; letter-spacing: 1px; }
.asset-header p, .muted { color: #aeb5c0; }
.asset-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(290px, 1fr)); gap: 10px; margin-bottom: 12px; }
.asset-panel { padding: 13px; margin-bottom: 12px; }
.asset-panel h2 { margin: 0 0 10px; font-size: 1.05rem; color: #f0d5b8; }
.pack-card { padding: 12px; }
.pack-title { display: flex; justify-content: space-between; gap: 10px; align-items: center; }
.pack-title strong { color: #fff; }
.pack-meta { margin: 7px 0; color: #aeb5c0; font-size: .84rem; line-height: 1.5; }
.pill { display: inline-flex; border: 1px solid #555; border-radius: 999px; padding: 2px 7px; font-size: .75rem; color: #ddd; }
.pill.approved { border-color: #328a57; color: #76d49c; }
.pill.review { border-color: #b88231; color: #f0bb64; }
.pill.rejected { border-color: #99404a; color: #ef8994; }
.compact-form { display: flex; flex-wrap: wrap; gap: 7px; align-items: center; }
.compact-form input[type="text"], .compact-form select, .compact-form input[type="file"] { min-height: 34px; background: #191919; border: 1px solid #4b4b4b; color: #eee; border-radius: 5px; padding: 6px 8px; }
.btn { display: inline-flex; align-items: center; min-height: 32px; border: 1px solid #555; border-radius: 5px; background: #333; color: #eee; padding: 5px 10px; text-decoration: none; cursor: pointer; }
.btn:hover { border-color: #f27c11; color: #f27c11; text-decoration: none; }
.btn-primary { background: #165a35; border-color: #237e4b; }
.notice { border-radius: 6px; padding: 9px 11px; margin-bottom: 10px; }
.notice.ok { background: #193827; border: 1px solid #2f7650; }
.notice.error { background: #411e23; border: 1px solid #8f3b46; }
.table-wrap { overflow: auto; max-height: 520px; border: 1px solid #3c3c3c; border-radius: 6px; }
table { width: 100%; border-collapse: collapse; font-size: .84rem; }
th { position: sticky; top: 0; z-index: 2; background: #292929; color: #e8c7a2; text-align: left; }
th, td { padding: 7px 8px; border-bottom: 1px solid #383838; vertical-align: top; }
tr:hover td { background: #282828; }
code { color: #d6e5f5; font-size: .79rem; }
.asset-name { min-width: 190px; }
.asset-actions { min-width: 225px; }
.empty { padding: 18px; text-align: center; color: #aeb5c0; }
@media (max-width: 720px) { main { padding-left: 8px; padding-right: 8px; } .compact-form > * { width: 100%; } }
</style>
<?php if (!$isEmbed): ?>
<?php include $enginePath . 'ui' . DIRECTORY_SEPARATOR . 'tmpl' . DIRECTORY_SEPARATOR . 'navbar.php'; ?>
<?php endif; ?>
<main class="asset-shell">
    <section class="asset-header">
        <h1>Quest Asset Library</h1>
        <p>Plugin-aware records used by AI Quest Manager. Imported candidates stay inactive or under review until you approve them.</p>
    </section>

    <?php if ($message !== ''): ?><div class="notice ok"><?php echo quest_asset_ui_h($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice error"><?php echo quest_asset_ui_h($error); ?></div><?php endif; ?>
    <?php if (!$tablesReady): ?>
        <div class="notice error">Quest asset tables are not installed yet. Run database updates, then reload this page.</div>
    <?php else: ?>
        <section class="asset-panel">
            <h2>Import Pack</h2>
            <form method="post" enctype="multipart/form-data" class="compact-form">
                <input type="hidden" name="action" value="import_manifest">
                <input type="file" name="manifest" accept="application/json,.json" required>
                <button class="btn btn-primary" type="submit">Import JSON</button>
                <span class="muted">Format: <code>chim.quest-assets.v1</code>. Use the extractor for your current load order.</span>
            </form>
        </section>

        <section class="asset-grid">
            <?php foreach ($packs as $pack): ?>
                <?php $packActive = quest_asset_db_bool($pack['active'] ?? false); ?>
                <article class="pack-card">
                    <div class="pack-title">
                        <strong><?php echo quest_asset_ui_h($pack['label']); ?></strong>
                        <span class="pill <?php echo $packActive ? 'approved' : 'rejected'; ?>"><?php echo $packActive ? 'Active' : 'Inactive'; ?></span>
                    </div>
                    <div class="pack-meta">
                        <code><?php echo quest_asset_ui_h($pack['pack_key']); ?></code><br>
                        <?php echo intval($pack['asset_count']); ?> assets, <?php echo intval($pack['group_count']); ?> groups,
                        <?php echo intval($pack['approved_count']); ?> approved, <?php echo intval($pack['review_count']); ?> review<br>
                        Required: <?php echo quest_asset_ui_h(implode(', ', json_decode((string) $pack['required_plugins_json'], true) ?: [])); ?>
                    </div>
                    <form method="post" class="compact-form">
                        <input type="hidden" name="action" value="toggle_pack">
                        <input type="hidden" name="pack_key" value="<?php echo quest_asset_ui_h($pack['pack_key']); ?>">
                        <label><input type="checkbox" name="active" value="1" <?php echo $packActive ? 'checked' : ''; ?>> Enabled</label>
                        <button class="btn" type="submit">Save</button>
                        <a class="btn" href="?embed=1&amp;download_pack=<?php echo rawurlencode($pack['pack_key']); ?>">Export</a>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="asset-panel">
            <h2>Assets</h2>
            <form method="get" class="compact-form" style="margin-bottom:10px">
                <input type="hidden" name="embed" value="1">
                <select name="pack"><option value="">All packs</option><?php foreach ($packs as $pack): ?><option value="<?php echo quest_asset_ui_h($pack['pack_key']); ?>" <?php echo $selectedPack === $pack['pack_key'] ? 'selected' : ''; ?>><?php echo quest_asset_ui_h($pack['label']); ?></option><?php endforeach; ?></select>
                <select name="safety"><option value="">All review states</option><?php foreach (['approved', 'review', 'rejected'] as $state): ?><option value="<?php echo $state; ?>" <?php echo $selectedSafety === $state ? 'selected' : ''; ?>><?php echo ucfirst($state); ?></option><?php endforeach; ?></select>
                <input type="text" name="q" value="<?php echo quest_asset_ui_h($search); ?>" placeholder="FormID, EditorID, or name">
                <button class="btn" type="submit">Filter</button>
            </form>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Asset</th><th>Record</th><th>Source</th><th>Review</th></tr></thead>
                    <tbody>
                    <?php foreach ($assets as $asset): ?>
                        <?php $assetActive = quest_asset_db_bool($asset['active'] ?? false); ?>
                        <tr>
                            <td class="asset-name"><strong><?php echo quest_asset_ui_h($asset['display_name'] ?: $asset['editor_id'] ?: '(unnamed)'); ?></strong><br><code><?php echo quest_asset_ui_h($asset['stable_ref']); ?></code></td>
                            <td><span class="pill"><?php echo quest_asset_ui_h($asset['signature']); ?></span><br><?php echo quest_asset_ui_h($asset['editor_id']); ?></td>
                            <td><?php echo quest_asset_ui_h($asset['source_pack']); ?><br><span class="muted"><?php echo quest_asset_ui_h($asset['source_plugin']); ?> -> <?php echo quest_asset_ui_h($asset['winning_plugin']); ?></span></td>
                            <td class="asset-actions">
                                <form method="post" class="compact-form">
                                    <input type="hidden" name="action" value="update_asset">
                                    <input type="hidden" name="pack_key" value="<?php echo quest_asset_ui_h($asset['source_pack']); ?>">
                                    <input type="hidden" name="stable_ref" value="<?php echo quest_asset_ui_h($asset['stable_ref']); ?>">
                                    <select name="safety_status"><?php foreach (['approved', 'review', 'rejected'] as $state): ?><option value="<?php echo $state; ?>" <?php echo $asset['safety_status'] === $state ? 'selected' : ''; ?>><?php echo ucfirst($state); ?></option><?php endforeach; ?></select>
                                    <label><input type="checkbox" name="active" value="1" <?php echo $assetActive ? 'checked' : ''; ?>> Active</label>
                                    <button class="btn" type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$assets): ?><tr><td colspan="4" class="empty">No assets match this filter.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="muted">Showing at most 300 assets. Filter by pack or review state for large generated packs.</p>
        </section>

        <section class="asset-panel">
            <h2>Runtime Groups</h2>
            <form method="get" class="compact-form" style="margin-bottom:10px">
                <input type="hidden" name="embed" value="1">
                <select name="pack"><option value="">All packs</option><?php foreach ($packs as $pack): ?><option value="<?php echo quest_asset_ui_h($pack['pack_key']); ?>" <?php echo $selectedPack === $pack['pack_key'] ? 'selected' : ''; ?>><?php echo quest_asset_ui_h($pack['label']); ?></option><?php endforeach; ?></select>
                <select name="dataset"><option value="">All datasets</option><?php foreach (array_keys(quest_asset_dataset_signatures()) as $dataset): ?><option value="<?php echo $dataset; ?>" <?php echo $selectedDataset === $dataset ? 'selected' : ''; ?>><?php echo quest_asset_ui_h($dataset); ?></option><?php endforeach; ?></select>
                <button class="btn" type="submit">Filter</button>
            </form>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Dataset</th><th>Group</th><th>Pack</th><th>Members</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($groups as $group): ?>
                        <?php $groupActive = quest_asset_db_bool($group['active'] ?? false); ?>
                        <tr>
                            <td><code><?php echo quest_asset_ui_h($group['dataset_name']); ?></code></td>
                            <td><strong><?php echo quest_asset_ui_h($group['group_key']); ?></strong><br><span class="muted"><?php echo quest_asset_ui_h($group['description']); ?></span></td>
                            <td><?php echo quest_asset_ui_h($group['source_pack']); ?></td>
                            <td><?php echo intval($group['member_count']); ?></td>
                            <td>
                                <form method="post" class="compact-form">
                                    <input type="hidden" name="action" value="toggle_group">
                                    <input type="hidden" name="pack_key" value="<?php echo quest_asset_ui_h($group['source_pack']); ?>">
                                    <input type="hidden" name="dataset" value="<?php echo quest_asset_ui_h($group['dataset_name']); ?>">
                                    <input type="hidden" name="group_key" value="<?php echo quest_asset_ui_h($group['group_key']); ?>">
                                    <label><input type="checkbox" name="active" value="1" <?php echo $groupActive ? 'checked' : ''; ?>> Active</label>
                                    <button class="btn" type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$groups): ?><tr><td colspan="5" class="empty">No groups match this filter.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php
include $enginePath . 'ui' . DIRECTORY_SEPARATOR . 'tmpl' . DIRECTORY_SEPARATOR . 'footer.html';
$buffer = ob_get_contents();
ob_end_clean();
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $TITLE . '$3', $buffer);
echo $buffer;
?>
