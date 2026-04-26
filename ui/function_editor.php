<?php
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath));
if ($webRoot == '/') {
    $webRoot = '';
}
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__ . DIRECTORY_SEPARATOR . "profile_loader.php");

$TITLE = "Action Editor";
$isEmbed = isset($_GET['embed']) && strval($_GET['embed']) === '1';

$enginePath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php");

if (!isset($GLOBALS["db"]) || !($GLOBALS["db"] instanceof sql)) {
    $GLOBALS["db"] = new sql();
}

require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "utils.php");
require_once($enginePath . "functions" . DIRECTORY_SEPARATOR . "functions.php");

function h($value)
{
    return htmlspecialchars(strval($value), ENT_QUOTES, 'UTF-8');
}

function functionEditorTrim($value)
{
    return trim(strval($value));
}

function functionEditorToBool($value)
{
    if (is_bool($value)) {
        return $value;
    }

    $text = strtolower(trim(strval($value)));
    return in_array($text, ['1', 'true', 'yes', 'on', 't'], true);
}

function functionEditorBuildUrl($params = [], $embed = false)
{
    $base = basename($_SERVER['PHP_SELF'] ?? 'function_editor.php');
    if ($embed) {
        $params['embed'] = '1';
    }
    $qs = http_build_query($params);
    return $base . ($qs !== '' ? ('?' . $qs) : '');
}

$message = '';
$messageType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_action') {
    $codeName = functionEditorTrim($_POST['code_name'] ?? '');
    $targetEnabled = functionEditorToBool($_POST['target_enabled'] ?? '0');

    if ($codeName === '') {
        $message = 'Missing action code name.';
        $messageType = 'err';
    } elseif (!function_exists('herikaActionCatalogDbReady') || !herikaActionCatalogDbReady()) {
        $message = 'Action catalog tables are not available yet. Run database updates first.';
        $messageType = 'err';
    } elseif (herikaActionCatalogUpsertCustomToggle($codeName, $targetEnabled)) {
        $message = sprintf('%s is now %s.', $codeName, $targetEnabled ? 'enabled' : 'disabled');
    } else {
        $message = 'Could not update the selected action.';
        $messageType = 'err';
    }
}

$search = functionEditorTrim($_GET['search'] ?? '');
$state = strtolower(functionEditorTrim($_GET['state'] ?? 'all'));
$scope = strtolower(functionEditorTrim($_GET['scope'] ?? 'all'));
if (!in_array($state, ['all', 'enabled', 'disabled'], true)) {
    $state = 'all';
}
if (!in_array($scope, ['all', 'npc', 'followers', 'dynamic'], true)) {
    $scope = 'all';
}

$rows = [];
$countAll = 0;
$countEnabled = 0;
$countNpc = 0;
$countFollowers = 0;
$countDynamic = 0;
$catalogReady = function_exists('herikaActionCatalogDbReady') && herikaActionCatalogDbReady();

if ($catalogReady) {
    $whereParts = [];
    if ($search !== '') {
        $searchLiteral = herikaActionCatalogSqlText('%' . $search . '%');
        $whereParts[] = "(v.code_name ILIKE {$searchLiteral} OR v.action_name ILIKE {$searchLiteral} OR v.description ILIKE {$searchLiteral})";
    }
    if ($state === 'enabled') {
        $whereParts[] = "v.is_activated = TRUE";
    } elseif ($state === 'disabled') {
        $whereParts[] = "v.is_activated = FALSE";
    }
    if ($scope === 'npc') {
        $whereParts[] = "v.available_to_npc = TRUE";
    } elseif ($scope === 'followers') {
        $whereParts[] = "v.available_to_followers = TRUE";
    } elseif ($scope === 'dynamic') {
        $whereParts[] = "v.available_to_npc = FALSE AND v.available_to_followers = FALSE";
    }

    $whereSql = count($whereParts) > 0 ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
    $rows = $GLOBALS["db"]->fetchAll("
        SELECT
            v.code_name,
            v.action_name,
            v.description,
            v.return_message,
            v.available_to_npc,
            v.available_to_followers,
            v.is_activated,
            EXISTS (
                SELECT 1
                FROM public.core_action_custom c
                WHERE LOWER(c.code_name) = LOWER(v.code_name)
            ) AS is_custom
        FROM public.combined_core_action v
        {$whereSql}
        ORDER BY LOWER(v.action_name), LOWER(v.code_name)
        LIMIT 2000
    ");

    $countAll = intval($GLOBALS["db"]->fetchOne("SELECT COUNT(*) AS c FROM public.combined_core_action")['c'] ?? 0);
    $countEnabled = intval($GLOBALS["db"]->fetchOne("SELECT COUNT(*) AS c FROM public.combined_core_action WHERE is_activated = TRUE")['c'] ?? 0);
    $countNpc = intval($GLOBALS["db"]->fetchOne("SELECT COUNT(*) AS c FROM public.combined_core_action WHERE available_to_npc = TRUE")['c'] ?? 0);
    $countFollowers = intval($GLOBALS["db"]->fetchOne("SELECT COUNT(*) AS c FROM public.combined_core_action WHERE available_to_followers = TRUE")['c'] ?? 0);
    $countDynamic = intval($GLOBALS["db"]->fetchOne("SELECT COUNT(*) AS c FROM public.combined_core_action WHERE available_to_npc = FALSE AND available_to_followers = FALSE")['c'] ?? 0);
}

ob_start();
include(__DIR__ . DIRECTORY_SEPARATOR . "tmpl/head.html");
if (!$isEmbed) {
    include(__DIR__ . DIRECTORY_SEPARATOR . "tmpl/navbar.php");
}
?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
    main {
        padding-top: 80px;
        padding-bottom: 40px;
        padding-left: 8%;
        padding-right: 8%;
        width: 100%;
        margin: 0;
    }

    footer {
        position: fixed;
        bottom: 0;
        width: 100%;
        height: 20px;
        background: #031633;
        z-index: 100;
    }

    .page-header,
    .content-section {
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        border: 1px solid #3a3a3a;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
    }

    .page-header {
        text-align: center;
        margin-bottom: 24px;
        padding: 20px;
    }

    .page-header h1 {
        margin: 0 0 8px 0;
        font-family: 'MagicCards', serif;
        word-spacing: 8px;
        font-size: 2.1em;
        color: rgb(242, 124, 17);
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
    }

    .page-subtitle {
        margin: 0;
        color: #bbb;
        line-height: 1.6;
    }

    .content-section {
        padding: 24px;
        margin-bottom: 20px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }

    .stat-card {
        padding: 14px 16px;
        border-radius: 8px;
        border: 1px solid #3a3a3a;
        background: rgba(20, 20, 20, 0.78);
    }

    .stat-label {
        color: #9fa8b2;
        font-size: 0.9em;
        margin-bottom: 6px;
    }

    .stat-value {
        color: #f2c280;
        font-size: 1.5em;
        font-weight: 700;
    }

    .toolbar {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        align-items: end;
        margin-bottom: 18px;
    }

    .toolbar-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 180px;
    }

    .toolbar label {
        color: #c5cbd3;
        font-size: 0.9em;
    }

    .toolbar input,
    .toolbar select {
        background: rgba(14, 14, 14, 0.95);
        color: #f0f2f5;
        border: 1px solid #444;
        border-radius: 6px;
        padding: 10px 12px;
    }

    .toolbar button,
    .toolbar-link,
    .toggle-button {
        background: linear-gradient(180deg, rgba(242, 124, 17, 0.95), rgba(201, 92, 0, 0.98));
        color: #111;
        border: none;
        border-radius: 6px;
        padding: 10px 14px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .toolbar button.secondary,
    .toolbar-link.secondary,
    .toggle-button.disabled {
        background: linear-gradient(180deg, rgba(108, 108, 108, 0.95), rgba(74, 74, 74, 0.98));
        color: #f3f3f3;
    }

    .message {
        padding: 12px 14px;
        border-radius: 8px;
        margin-bottom: 16px;
    }

    .message.ok {
        background: rgba(30, 86, 52, 0.45);
        border: 1px solid rgba(78, 176, 115, 0.5);
        color: #c8f0d6;
    }

    .message.err {
        background: rgba(101, 32, 32, 0.45);
        border: 1px solid rgba(205, 93, 93, 0.45);
        color: #ffd7d7;
    }

    .action-list {
        display: grid;
        gap: 14px;
    }

    .action-card {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 16px;
        padding: 16px 18px;
        border-radius: 8px;
        border: 1px solid #3a3a3a;
        background: rgba(15, 15, 15, 0.86);
        align-items: start;
    }

    .action-card.disabled-card {
        opacity: 0.78;
    }

    .action-title {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
        margin-bottom: 8px;
    }

    .action-title strong {
        color: #f4c37b;
        font-size: 1.05em;
    }

    .action-code {
        color: #9fa8b2;
        font-family: monospace;
        font-size: 0.95em;
    }

    .badge-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 8px;
    }

    .badge {
        display: inline-block;
        padding: 3px 9px;
        border-radius: 999px;
        border: 1px solid #4a4a4a;
        font-size: 12px;
    }

    .badge.enabled {
        color: #8de0af;
        border-color: rgba(90, 174, 117, 0.45);
        background: rgba(33, 86, 54, 0.28);
    }

    .badge.disabled {
        color: #ffb8b8;
        border-color: rgba(205, 93, 93, 0.4);
        background: rgba(96, 32, 32, 0.28);
    }

    .badge.scope {
        color: #d5d9de;
        background: rgba(35, 46, 61, 0.32);
    }

    .badge.custom {
        color: #f7d28e;
        background: rgba(98, 73, 27, 0.3);
        border-color: rgba(217, 162, 72, 0.4);
    }

    .action-description {
        color: #d0d6df;
        line-height: 1.55;
        margin-bottom: 10px;
    }

    .action-return {
        color: #9fa8b2;
        font-size: 0.95em;
        line-height: 1.45;
    }

    .empty-state {
        text-align: center;
        color: #c5cbd3;
        padding: 30px 16px;
        border: 1px dashed #525252;
        border-radius: 8px;
        background: rgba(12, 12, 12, 0.45);
    }

    @media (max-width: 900px) {
        .action-card {
            grid-template-columns: 1fr;
        }
    }
</style>

<main>
    <div class="page-header">
        <h1>CHIM Action Editor</h1>
        <p class="page-subtitle">
            Actions are now sourced from the database via <code>core_action</code>, <code>core_action_custom</code>, and <code>combined_core_action</code>.
            The PHP files still own schemas and runtime logic, but the editor and enable/disable state now live in the database.
        </p>
    </div>

    <div class="content-section">
        <?php if ($message !== ''): ?>
            <div class="message <?php echo h($messageType); ?>"><?php echo h($message); ?></div>
        <?php endif; ?>

        <?php if (!$catalogReady): ?>
            <div class="message err">Action catalog tables are not available yet. Run the HerikaServer database updates, then reload this page.</div>
        <?php else: ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">All Actions</div>
                    <div class="stat-value"><?php echo h($countAll); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Enabled</div>
                    <div class="stat-value"><?php echo h($countEnabled); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">NPC Scope</div>
                    <div class="stat-value"><?php echo h($countNpc); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Follower Scope</div>
                    <div class="stat-value"><?php echo h($countFollowers); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Dynamic Only</div>
                    <div class="stat-value"><?php echo h($countDynamic); ?></div>
                </div>
            </div>

            <form method="get" class="toolbar">
                <div class="toolbar-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" value="<?php echo h($search); ?>" placeholder="code, name, description">
                </div>
                <div class="toolbar-group">
                    <label for="state">State</label>
                    <select id="state" name="state">
                        <option value="all" <?php echo $state === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="enabled" <?php echo $state === 'enabled' ? 'selected' : ''; ?>>Enabled</option>
                        <option value="disabled" <?php echo $state === 'disabled' ? 'selected' : ''; ?>>Disabled</option>
                    </select>
                </div>
                <div class="toolbar-group">
                    <label for="scope">Scope</label>
                    <select id="scope" name="scope">
                        <option value="all" <?php echo $scope === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="npc" <?php echo $scope === 'npc' ? 'selected' : ''; ?>>NPC</option>
                        <option value="followers" <?php echo $scope === 'followers' ? 'selected' : ''; ?>>Followers</option>
                        <option value="dynamic" <?php echo $scope === 'dynamic' ? 'selected' : ''; ?>>Dynamic</option>
                    </select>
                </div>
                <?php if ($isEmbed): ?>
                    <input type="hidden" name="embed" value="1">
                <?php endif; ?>
                <button type="submit">Apply Filters</button>
                <a class="toolbar-link secondary" href="<?php echo h(functionEditorBuildUrl([], $isEmbed)); ?>">Reset</a>
            </form>

            <?php if (count($rows) === 0): ?>
                <div class="empty-state">No actions matched the current filters.</div>
            <?php else: ?>
                <div class="action-list">
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $isEnabled = herikaActionCatalogToBool($row['is_activated'] ?? false);
                        $isNpc = herikaActionCatalogToBool($row['available_to_npc'] ?? false);
                        $isFollowers = herikaActionCatalogToBool($row['available_to_followers'] ?? false);
                        $isCustom = herikaActionCatalogToBool($row['is_custom'] ?? false);
                        ?>
                        <div class="action-card <?php echo $isEnabled ? '' : 'disabled-card'; ?>">
                            <div>
                                <div class="action-title">
                                    <strong><?php echo h($row['action_name'] ?? ''); ?></strong>
                                    <span class="action-code"><?php echo h($row['code_name'] ?? ''); ?></span>
                                </div>
                                <div class="badge-row">
                                    <span class="badge <?php echo $isEnabled ? 'enabled' : 'disabled'; ?>">
                                        <?php echo $isEnabled ? 'Enabled' : 'Disabled'; ?>
                                    </span>
                                    <?php if ($isNpc): ?>
                                        <span class="badge scope">NPC</span>
                                    <?php endif; ?>
                                    <?php if ($isFollowers): ?>
                                        <span class="badge scope">Followers</span>
                                    <?php endif; ?>
                                    <?php if (!$isNpc && !$isFollowers): ?>
                                        <span class="badge scope">Dynamic</span>
                                    <?php endif; ?>
                                    <?php if ($isCustom): ?>
                                        <span class="badge custom">Custom Override</span>
                                    <?php endif; ?>
                                </div>
                                <div class="action-description"><?php echo h($row['description'] ?? ''); ?></div>
                                <?php if (trim(strval($row['return_message'] ?? '')) !== ''): ?>
                                    <div class="action-return">Return: <?php echo h($row['return_message'] ?? ''); ?></div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <form method="post">
                                    <input type="hidden" name="action" value="toggle_action">
                                    <input type="hidden" name="code_name" value="<?php echo h($row['code_name'] ?? ''); ?>">
                                    <input type="hidden" name="target_enabled" value="<?php echo $isEnabled ? '0' : '1'; ?>">
                                    <button type="submit" class="toggle-button <?php echo $isEnabled ? 'disabled' : ''; ?>">
                                        <?php echo $isEnabled ? 'Disable' : 'Enable'; ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

</body>
</html>
