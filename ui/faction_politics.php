<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'profile_loader.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'faction_politics.php';

$isEmbed = isset($_GET['embed']) && (string)$_GET['embed'] === '1';
$scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
$webRoot = rtrim(dirname(dirname($scriptPath)), '/');
if ($webRoot === '/' || $webRoot === '.') {
    $webRoot = '';
}
$db = $GLOBALS['db'];
$TITLE = 'Faction Politics';

if (empty($_SESSION['faction_politics_csrf'])) {
    $_SESSION['faction_politics_csrf'] = bin2hex(random_bytes(24));
}

function factionPoliticsUiH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function factionPoliticsUiRedirect(bool $isEmbed, string $notice, string $type = 'ok'): void
{
    $query = ['notice' => $notice, 'notice_type' => $type];
    if ($isEmbed) {
        $query['embed'] = '1';
    }
    header('Location: faction_politics.php?' . http_build_query($query));
    exit;
}

function factionPoliticsUiEscape($db, $value): string
{
    return $db->escape(chimFactionPoliticsText($value));
}

$tablesReady = chimFactionPoliticsTablesExist($db);
$catalog = $tablesReady ? chimFactionPoliticsDetectedCatalog($db) : [];
$states = $tablesReady ? $db->fetchAll('SELECT * FROM core_faction_politics_state ORDER BY lower(faction_name)') : [];
$relations = $tablesReady ? $db->fetchAll('SELECT * FROM core_faction_politics_relation ORDER BY lower(faction_a_name), lower(faction_b_name)') : [];
$developments = $tablesReady ? $db->fetchAll('SELECT * FROM core_faction_politics_development ORDER BY status, gamets DESC, id DESC') : [];
foreach ($states as $state) {
    $catalog[$state['faction_key']] = $state['faction_name'];
}
foreach ($relations as $relation) {
    $catalog[$relation['faction_a_key']] = $relation['faction_a_name'];
    $catalog[$relation['faction_b_key']] = $relation['faction_b_name'];
}
natcasesort($catalog);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$tablesReady) {
        factionPoliticsUiRedirect($isEmbed, 'Database update is not installed yet.', 'error');
    }
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals((string)$_SESSION['faction_politics_csrf'], $token)) {
        factionPoliticsUiRedirect($isEmbed, 'The form expired. Reload and try again.', 'error');
    }

    $action = (string)($_POST['action'] ?? '');
    $now = time();
    try {
        if ($action === 'save_state') {
            $key = substr(chimFactionPoliticsText($_POST['faction_key'] ?? ''), 0, 180);
            $name = substr(chimFactionPoliticsText($catalog[$key] ?? ''), 0, 180);
            if ($key === '' || $name === '') {
                throw new RuntimeException('Choose a faction.');
            }
            $status = chimFactionPoliticsEnum(
                $_POST['status'] ?? '',
                ['stable', 'rising', 'declining', 'fractured', 'dominant', 'disbanded'],
                'stable'
            );
            $influence = chimFactionPoliticsClamp($_POST['influence'] ?? 0);
            $agenda = substr(chimFactionPoliticsText($_POST['agenda'] ?? ''), 0, 2000);
            $summary = substr(chimFactionPoliticsText($_POST['summary'] ?? ''), 0, 4000);
            $gamets = max(0, (int)($_POST['gamets'] ?? 0));
            $db->execQuery(
                "INSERT INTO core_faction_politics_state
                    (faction_key, faction_name, status, influence, agenda, summary, gamets, created_at, updated_at)
                 VALUES ('" . factionPoliticsUiEscape($db, $key) . "', '" . factionPoliticsUiEscape($db, $name) . "', '"
                    . factionPoliticsUiEscape($db, $status) . "', {$influence}, '" . factionPoliticsUiEscape($db, $agenda) . "', '"
                    . factionPoliticsUiEscape($db, $summary) . "', {$gamets}, {$now}, {$now})
                 ON CONFLICT (faction_key) DO UPDATE SET
                    faction_name = EXCLUDED.faction_name,
                    status = EXCLUDED.status,
                    influence = EXCLUDED.influence,
                    agenda = EXCLUDED.agenda,
                    summary = EXCLUDED.summary,
                    gamets = EXCLUDED.gamets,
                    updated_at = EXCLUDED.updated_at"
            );
            factionPoliticsUiRedirect($isEmbed, 'Faction state saved.');
        }

        if ($action === 'delete_state') {
            $key = factionPoliticsUiEscape($db, $_POST['faction_key'] ?? '');
            $db->execQuery("DELETE FROM core_faction_politics_state WHERE faction_key = '{$key}'");
            factionPoliticsUiRedirect($isEmbed, 'Faction state deleted.');
        }

        if ($action === 'save_relation') {
            [$keyA, $nameA, $keyB, $nameB] = chimFactionPoliticsCanonicalPair(
                substr(chimFactionPoliticsText($_POST['faction_a_key'] ?? ''), 0, 180),
                substr(chimFactionPoliticsText($catalog[$_POST['faction_a_key'] ?? ''] ?? ''), 0, 180),
                substr(chimFactionPoliticsText($_POST['faction_b_key'] ?? ''), 0, 180),
                substr(chimFactionPoliticsText($catalog[$_POST['faction_b_key'] ?? ''] ?? ''), 0, 180)
            );
            if ($keyA === '' || $keyB === '' || $keyA === $keyB) {
                throw new RuntimeException('Choose two different factions.');
            }
            $stance = chimFactionPoliticsEnum(
                $_POST['stance'] ?? '',
                ['allied', 'friendly', 'neutral', 'tense', 'hostile', 'war'],
                'neutral'
            );
            $score = chimFactionPoliticsClamp($_POST['score'] ?? 0);
            $summary = substr(chimFactionPoliticsText($_POST['summary'] ?? ''), 0, 4000);
            $gamets = max(0, (int)($_POST['gamets'] ?? 0));
            $db->execQuery(
                "INSERT INTO core_faction_politics_relation
                    (faction_a_key, faction_a_name, faction_b_key, faction_b_name, stance, score, summary, gamets, created_at, updated_at)
                 VALUES ('" . factionPoliticsUiEscape($db, $keyA) . "', '" . factionPoliticsUiEscape($db, $nameA) . "', '"
                    . factionPoliticsUiEscape($db, $keyB) . "', '" . factionPoliticsUiEscape($db, $nameB) . "', '"
                    . factionPoliticsUiEscape($db, $stance) . "', {$score}, '" . factionPoliticsUiEscape($db, $summary)
                    . "', {$gamets}, {$now}, {$now})
                 ON CONFLICT (faction_a_key, faction_b_key) DO UPDATE SET
                    faction_a_name = EXCLUDED.faction_a_name,
                    faction_b_name = EXCLUDED.faction_b_name,
                    stance = EXCLUDED.stance,
                    score = EXCLUDED.score,
                    summary = EXCLUDED.summary,
                    gamets = EXCLUDED.gamets,
                    updated_at = EXCLUDED.updated_at"
            );
            factionPoliticsUiRedirect($isEmbed, 'Faction relation saved.');
        }

        if ($action === 'delete_relation') {
            [$keyA, , $keyB] = chimFactionPoliticsCanonicalPair(
                $_POST['faction_a_key'] ?? '',
                '',
                $_POST['faction_b_key'] ?? '',
                ''
            );
            $db->execQuery(
                "DELETE FROM core_faction_politics_relation WHERE faction_a_key = '"
                . factionPoliticsUiEscape($db, $keyA) . "' AND faction_b_key = '"
                . factionPoliticsUiEscape($db, $keyB) . "'"
            );
            factionPoliticsUiRedirect($isEmbed, 'Faction relation deleted.');
        }

        if ($action === 'save_development') {
            $title = substr(chimFactionPoliticsText($_POST['title'] ?? ''), 0, 240);
            $summary = substr(chimFactionPoliticsText($_POST['summary'] ?? ''), 0, 5000);
            $keys = array_values(array_unique(array_filter(array_map(
                static fn($key) => substr(chimFactionPoliticsText($key), 0, 180),
                is_array($_POST['faction_keys'] ?? null) ? $_POST['faction_keys'] : []
            ))));
            if ($title === '' || $summary === '' || empty($keys)) {
                throw new RuntimeException('A title, summary, and at least one faction are required.');
            }
            $status = chimFactionPoliticsEnum($_POST['status'] ?? '', ['active', 'resolved'], 'active');
            $gamets = max(0, (int)($_POST['gamets'] ?? 0));
            $json = factionPoliticsUiEscape($db, json_encode($keys, JSON_UNESCAPED_SLASHES));
            $db->execQuery(
                "INSERT INTO core_faction_politics_development
                    (title, summary, faction_keys, status, gamets, created_at, updated_at)
                 VALUES ('" . factionPoliticsUiEscape($db, $title) . "', '" . factionPoliticsUiEscape($db, $summary)
                    . "', '{$json}'::jsonb, '" . factionPoliticsUiEscape($db, $status) . "', {$gamets}, {$now}, {$now})"
            );
            factionPoliticsUiRedirect($isEmbed, 'Political development added.');
        }

        if ($action === 'delete_development') {
            $id = max(0, (int)($_POST['id'] ?? 0));
            $db->execQuery("DELETE FROM core_faction_politics_development WHERE id = {$id}");
            factionPoliticsUiRedirect($isEmbed, 'Political development deleted.');
        }

        if ($action === 'resolve_development') {
            $id = max(0, (int)($_POST['id'] ?? 0));
            $db->execQuery("UPDATE core_faction_politics_development SET status = 'resolved', updated_at = {$now} WHERE id = {$id}");
            factionPoliticsUiRedirect($isEmbed, 'Political development resolved.');
        }
    } catch (Throwable $e) {
        factionPoliticsUiRedirect($isEmbed, $e->getMessage(), 'error');
    }
}

ob_start();
include __DIR__ . DIRECTORY_SEPARATOR . 'tmpl' . DIRECTORY_SEPARATOR . 'head.html';
if (!$isEmbed) {
    include __DIR__ . DIRECTORY_SEPARATOR . 'tmpl' . DIRECTORY_SEPARATOR . 'navbar.php';
}
?>
<link rel="stylesheet" href="<?php echo factionPoliticsUiH($webRoot); ?>/ui/css/main.css">
<style>
main { padding: <?php echo $isEmbed ? '18px 10px 36px' : '100px 10px 36px'; ?>; max-width: 1500px; margin: 0 auto; }
.politics-header { text-align:center; margin-bottom:18px; }
.politics-header h1 { margin:0 0 6px; color:#e6b76c; }
.politics-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; align-items:start; }
.politics-card { background:#222; border:1px solid #444; border-radius:8px; padding:16px; }
.politics-card h2 { margin:0 0 12px; color:#e6b76c; font-size:1.2rem; }
.politics-card label { display:block; margin:9px 0 4px; color:#ddd; font-weight:600; }
.politics-card input,.politics-card select,.politics-card textarea { width:100%; box-sizing:border-box; background:#171717; color:#eee; border:1px solid #555; border-radius:5px; padding:8px; }
.politics-card textarea { min-height:76px; resize:vertical; }
.politics-card select[multiple] { min-height:125px; }
.politics-actions { display:flex; gap:8px; margin-top:12px; flex-wrap:wrap; }
.politics-list { margin-top:14px; display:grid; gap:8px; }
.politics-row { border:1px solid #3e3e3e; border-radius:6px; padding:10px; background:#1b1b1b; }
.politics-row strong { color:#fff; }
.politics-meta { color:#aaa; font-size:.86rem; margin-top:4px; }
.politics-row form { margin-top:8px; }
.politics-notice { padding:10px 12px; margin-bottom:14px; border-radius:5px; background:#173f28; border:1px solid #2d8a50; }
.politics-notice.error { background:#4b2020; border-color:#a94442; }
.politics-empty { color:#999; padding:8px 0; }
@media (max-width:1000px) { .politics-grid { grid-template-columns:1fr; } }
</style>
<main>
    <div class="politics-header">
        <h1>Faction Politics</h1>
        <p>Track faction influence, relationships, and current political developments. Only politics relevant to NPCs in the current scene enters AI context.</p>
    </div>
    <?php if (isset($_GET['notice'])): ?>
        <div class="politics-notice <?php echo ($_GET['notice_type'] ?? '') === 'error' ? 'error' : ''; ?>"><?php echo factionPoliticsUiH($_GET['notice']); ?></div>
    <?php endif; ?>
    <?php if (!$tablesReady): ?>
        <div class="politics-notice error">Faction politics tables are unavailable. Run the database updater, then reload this page.</div>
    <?php else: ?>
    <div class="politics-grid">
        <section class="politics-card">
            <h2>Faction State</h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo factionPoliticsUiH($_SESSION['faction_politics_csrf']); ?>">
                <input type="hidden" name="action" value="save_state">
                <label for="state-faction">Faction</label>
                <select id="state-faction" name="faction_key" required data-faction-select>
                    <option value="">Choose detected faction...</option>
                    <?php foreach ($catalog as $key => $name): ?><option value="<?php echo factionPoliticsUiH($key); ?>" data-name="<?php echo factionPoliticsUiH($name); ?>"><?php echo factionPoliticsUiH($name); ?></option><?php endforeach; ?>
                </select>
                <label>Status</label>
                <select name="status"><option>stable</option><option>rising</option><option>declining</option><option>fractured</option><option>dominant</option><option>disbanded</option></select>
                <label>Influence (-100 to 100)</label><input type="number" name="influence" min="-100" max="100" value="0">
                <label>Current agenda</label><input name="agenda" maxlength="2000">
                <label>Summary</label><textarea name="summary" maxlength="4000"></textarea>
                <label>Game timestamp</label><input type="number" name="gamets" min="0" value="0">
                <div class="politics-actions"><button class="btn-base btn-primary" type="submit">Save State</button></div>
            </form>
            <div class="politics-list">
                <?php foreach ($states as $state): ?><div class="politics-row">
                    <strong><?php echo factionPoliticsUiH($state['faction_name']); ?></strong>
                    <div><?php echo factionPoliticsUiH($state['status']); ?>, influence <?php echo (int)$state['influence']; ?></div>
                    <?php if ($state['agenda'] !== ''): ?><div class="politics-meta">Agenda: <?php echo factionPoliticsUiH($state['agenda']); ?></div><?php endif; ?>
                    <?php if ($state['summary'] !== ''): ?><div class="politics-meta"><?php echo factionPoliticsUiH($state['summary']); ?></div><?php endif; ?>
                    <form method="post" onsubmit="return confirm('Delete this faction state?');"><input type="hidden" name="csrf_token" value="<?php echo factionPoliticsUiH($_SESSION['faction_politics_csrf']); ?>"><input type="hidden" name="action" value="delete_state"><input type="hidden" name="faction_key" value="<?php echo factionPoliticsUiH($state['faction_key']); ?>"><button class="btn-base btn-danger" type="submit">Delete</button></form>
                </div><?php endforeach; ?>
                <?php if (empty($states)): ?><div class="politics-empty">No faction states configured.</div><?php endif; ?>
            </div>
        </section>

        <section class="politics-card">
            <h2>Faction Relations</h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo factionPoliticsUiH($_SESSION['faction_politics_csrf']); ?>"><input type="hidden" name="action" value="save_relation">
                <label>First faction</label><select id="relation-a" name="faction_a_key" required data-faction-select><option value="">Choose faction...</option><?php foreach ($catalog as $key => $name): ?><option value="<?php echo factionPoliticsUiH($key); ?>" data-name="<?php echo factionPoliticsUiH($name); ?>"><?php echo factionPoliticsUiH($name); ?></option><?php endforeach; ?></select>
                <label>Second faction</label><select id="relation-b" name="faction_b_key" required data-faction-select><option value="">Choose faction...</option><?php foreach ($catalog as $key => $name): ?><option value="<?php echo factionPoliticsUiH($key); ?>" data-name="<?php echo factionPoliticsUiH($name); ?>"><?php echo factionPoliticsUiH($name); ?></option><?php endforeach; ?></select>
                <label>Stance</label><select name="stance"><option>allied</option><option>friendly</option><option selected>neutral</option><option>tense</option><option>hostile</option><option>war</option></select>
                <label>Score (-100 to 100)</label><input type="number" name="score" min="-100" max="100" value="0">
                <label>Summary</label><textarea name="summary" maxlength="4000"></textarea>
                <label>Game timestamp</label><input type="number" name="gamets" min="0" value="0">
                <div class="politics-actions"><button class="btn-base btn-primary" type="submit">Save Relation</button></div>
            </form>
            <div class="politics-list">
                <?php foreach ($relations as $relation): ?><div class="politics-row">
                    <strong><?php echo factionPoliticsUiH($relation['faction_a_name']); ?> / <?php echo factionPoliticsUiH($relation['faction_b_name']); ?></strong>
                    <div><?php echo factionPoliticsUiH($relation['stance']); ?> (<?php echo (int)$relation['score']; ?>)</div>
                    <?php if ($relation['summary'] !== ''): ?><div class="politics-meta"><?php echo factionPoliticsUiH($relation['summary']); ?></div><?php endif; ?>
                    <form method="post" onsubmit="return confirm('Delete this faction relation?');"><input type="hidden" name="csrf_token" value="<?php echo factionPoliticsUiH($_SESSION['faction_politics_csrf']); ?>"><input type="hidden" name="action" value="delete_relation"><input type="hidden" name="faction_a_key" value="<?php echo factionPoliticsUiH($relation['faction_a_key']); ?>"><input type="hidden" name="faction_b_key" value="<?php echo factionPoliticsUiH($relation['faction_b_key']); ?>"><button class="btn-base btn-danger" type="submit">Delete</button></form>
                </div><?php endforeach; ?>
                <?php if (empty($relations)): ?><div class="politics-empty">No faction relations configured.</div><?php endif; ?>
            </div>
        </section>

        <section class="politics-card">
            <h2>Political Developments</h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo factionPoliticsUiH($_SESSION['faction_politics_csrf']); ?>"><input type="hidden" name="action" value="save_development">
                <label>Title</label><input name="title" maxlength="240" required>
                <label>Involved factions</label><select name="faction_keys[]" multiple required><?php foreach ($catalog as $key => $name): ?><option value="<?php echo factionPoliticsUiH($key); ?>"><?php echo factionPoliticsUiH($name); ?></option><?php endforeach; ?></select>
                <label>Status</label><select name="status"><option>active</option><option>resolved</option></select>
                <label>Summary</label><textarea name="summary" maxlength="5000" required></textarea>
                <label>Game timestamp</label><input type="number" name="gamets" min="0" value="0">
                <div class="politics-actions"><button class="btn-base btn-primary" type="submit">Add Development</button></div>
            </form>
            <div class="politics-list">
                <?php foreach ($developments as $development): ?><div class="politics-row">
                    <strong><?php echo factionPoliticsUiH($development['title']); ?></strong> <span class="politics-meta"><?php echo factionPoliticsUiH($development['status']); ?></span>
                    <div class="politics-meta"><?php echo factionPoliticsUiH($development['summary']); ?></div>
                    <div class="politics-actions">
                        <?php if ($development['status'] === 'active'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?php echo factionPoliticsUiH($_SESSION['faction_politics_csrf']); ?>"><input type="hidden" name="action" value="resolve_development"><input type="hidden" name="id" value="<?php echo (int)$development['id']; ?>"><button class="btn-base btn-secondary" type="submit">Resolve</button></form><?php endif; ?>
                        <form method="post" onsubmit="return confirm('Delete this political development?');"><input type="hidden" name="csrf_token" value="<?php echo factionPoliticsUiH($_SESSION['faction_politics_csrf']); ?>"><input type="hidden" name="action" value="delete_development"><input type="hidden" name="id" value="<?php echo (int)$development['id']; ?>"><button class="btn-base btn-danger" type="submit">Delete</button></form>
                    </div>
                </div><?php endforeach; ?>
                <?php if (empty($developments)): ?><div class="politics-empty">No political developments configured.</div><?php endif; ?>
            </div>
        </section>
    </div>
    <?php endif; ?>
</main>
<?php
if (!$isEmbed) {
    include __DIR__ . DIRECTORY_SEPARATOR . 'tmpl' . DIRECTORY_SEPARATOR . 'footer.html';
}
$buffer = ob_get_contents();
ob_end_clean();
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $TITLE . '$3', $buffer);
echo $buffer;
?>
