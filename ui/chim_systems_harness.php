<?php

$enginePath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
$scriptPath = (string)($_SERVER['SCRIPT_NAME'] ?? '');
$uiPos = strpos($scriptPath, '/ui/');
$webRoot = $uiPos !== false ? substr($scriptPath, 0, $uiPos) : '';
$webRoot = rtrim($webRoot === '/' ? '' : $webRoot, '/');
require_once $enginePath . 'lib' . DIRECTORY_SEPARATOR . 'runtime_bootstrap.php';
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);
require_once $enginePath . 'lib' . DIRECTORY_SEPARATOR . 'logger.php';
require_once $enginePath . 'lib' . DIRECTORY_SEPARATOR . 'background_processor.php';
require_once $enginePath . 'lib' . DIRECTORY_SEPARATOR . 'chim_systems_harness.php';

$db = $GLOBALS['db'];
$isEmbedded = isset($_GET['embed']) && $_GET['embed'] === '1';
$privateRequest = chimHarnessIsPrivateRequest();
$flash = '';
$flashType = 'info';

chimHarnessEnsureSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$privateRequest) {
        $flash = 'Harness controls are only available from the local/private network.';
        $flashType = 'error';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'start') {
            if (empty($_POST['test_save_ack'])) {
                $result = ['ok' => false, 'message' => 'Confirm that you are using a disposable test save.'];
            } else {
                $result = chimHarnessStartRun(
                    (string)($_POST['scenario'] ?? ''),
                    (string)($_POST['mode'] ?? 'live'),
                    intval($_POST['duration_minutes'] ?? 30),
                    is_array($_POST['existing_npcs'] ?? null) ? $_POST['existing_npcs'] : []
                );
                if (!empty($result['ok'])) {
                    herikaEnsureBackgroundProcessorRunning();
                }
            }
            $flash = (string)($result['message'] ?? 'Request processed.');
            $flashType = !empty($result['ok']) ? 'success' : 'error';
        } elseif ($action === 'stop') {
            $result = chimHarnessRequestStop(intval($_POST['run_id'] ?? 0));
            $flash = (string)$result['message'];
            $flashType = !empty($result['ok']) ? 'success' : 'error';
        } elseif ($action === 'tick') {
            chimHarnessTick();
            $flash = 'Advanced one harness service tick.';
            $flashType = 'success';
        }
    }
}

$activeRun = chimHarnessGetActiveRun();
$requestedRunId = intval($_GET['run_id'] ?? 0);
$selectedRun = $requestedRunId > 0 ? chimHarnessGetRun($requestedRunId) : $activeRun;
if (!$selectedRun) {
    $selectedRun = $db->fetchOne('SELECT * FROM chim_harness_run ORDER BY id DESC LIMIT 1');
}
$selectedRunId = intval($selectedRun['id'] ?? 0);
$actors = $selectedRunId > 0 ? chimHarnessGetActors($selectedRunId) : [];
$events = $selectedRunId > 0 ? chimHarnessGetEvents($selectedRunId, 120) : [];
$recentRuns = chimHarnessGetRecentRuns(15);
$scenarios = chimHarnessScenarios();
$heartbeat = chimHarnessGameHeartbeat();
$processorRunning = herikaBackgroundProcessorIsRunning();
$availableNpcs = $db->fetchAll(
    "SELECT id, npc_name, race, refid,
            COALESCE((extended_data->>'background_life_enabled')::boolean, false) AS bgl_enabled
     FROM core_npc_master
     WHERE refid IS NOT NULL AND btrim(refid)<>''
     ORDER BY npc_name
     LIMIT 750"
);

function chimHarnessHtml($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function chimHarnessTime($value): string
{
    $timestamp = intval($value);
    return $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : '-';
}

function chimHarnessStatusClass(string $status): string
{
    if (in_array($status, ['running', 'active', 'completed', 'restored'], true)) {
        return 'ok';
    }
    if (in_array($status, ['failed'], true)) {
        return 'bad';
    }
    if (in_array($status, ['stopping', 'restoring', 'cleanup_queued'], true)) {
        return 'warn';
    }
    return 'idle';
}

$TITLE = 'CHIM Systems Harness';
ob_start();
include __DIR__ . DIRECTORY_SEPARATOR . 'tmpl' . DIRECTORY_SEPARATOR . 'head.html';
?>
<link rel="stylesheet" href="<?php echo chimHarnessHtml(($webRoot ?? '') . '/ui/css/main.css'); ?>">
<style>
body { background:#171717; }
main { padding:<?php echo $isEmbedded ? '10px' : '70px'; ?> 10px 30px; color:#eee; }
footer { display:<?php echo $isEmbedded ? 'none' : 'block'; ?>; }
.harness-page { max-width:1500px; margin:0 auto; }
.harness-head { display:flex; justify-content:space-between; gap:14px; align-items:flex-start; margin-bottom:12px; }
.harness-head h1 { margin:0 0 3px; font-size:1.45rem; }
.muted { color:#a9a9a9; font-size:.9rem; }
.health { display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end; }
.pill { display:inline-flex; align-items:center; gap:5px; border:1px solid #505050; border-radius:999px; padding:4px 9px; background:#242424; font-size:.8rem; }
.pill.ok { color:#69d38b; border-color:#356c46; }
.pill.warn { color:#f0b45d; border-color:#735529; }
.pill.bad { color:#ff7f88; border-color:#7a363c; }
.pill.idle { color:#b8c3d3; }
.grid { display:grid; grid-template-columns:minmax(300px, 390px) minmax(0, 1fr); gap:12px; }
.panel { background:#212121; border:1px solid #3d3d3d; border-radius:7px; padding:12px; }
.panel h2 { font-size:1rem; margin:0 0 10px; color:#f27c11; }
.field { margin-bottom:10px; }
.field label { display:block; font-weight:700; margin-bottom:4px; }
.field small { display:block; color:#999; margin-top:3px; }
select, input[type=number], input[type=text] { width:100%; box-sizing:border-box; background:#171717; color:#eee; border:1px solid #555; border-radius:4px; padding:7px; }
select[multiple] { min-height:190px; }
.actions { display:flex; gap:7px; flex-wrap:wrap; align-items:center; }
button { border:1px solid #555; border-radius:4px; padding:7px 11px; color:#fff; background:#333; cursor:pointer; font-weight:700; }
button.primary { background:#246b3a; border-color:#33844b; }
button.danger { background:#7a292f; border-color:#a43b44; }
button:disabled { opacity:.45; cursor:not-allowed; }
.warning { border-left:3px solid #d18a2c; padding:7px 9px; background:#2a241b; margin-bottom:10px; font-size:.88rem; }
.flash { padding:8px 10px; border-radius:4px; margin-bottom:10px; border:1px solid #555; }
.flash.success { background:#1c3424; border-color:#356c46; }
.flash.error { background:#3a2022; border-color:#7a363c; }
.run-title { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:10px; }
.run-title strong { font-size:1.05rem; }
.metrics { display:grid; grid-template-columns:repeat(auto-fit, minmax(100px, 1fr)); gap:7px; margin-bottom:10px; }
.metric { border:1px solid #3c3c3c; border-radius:5px; padding:8px; background:#1a1a1a; }
.metric span { display:block; color:#999; font-size:.72rem; text-transform:uppercase; }
.metric strong { display:block; margin-top:2px; font-size:1.15rem; }
table { width:100%; border-collapse:collapse; font-size:.84rem; }
th, td { text-align:left; border-bottom:1px solid #393939; padding:7px 6px; vertical-align:top; }
th { color:#cfcfcf; background:#1b1b1b; position:sticky; top:0; }
.table-wrap { max-height:285px; overflow:auto; border:1px solid #393939; border-radius:5px; margin-top:8px; }
.actor-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:8px; margin-bottom:10px; }
.actor { border:1px solid #3c3c3c; border-radius:5px; padding:9px; background:#1a1a1a; }
.actor-head { display:flex; justify-content:space-between; gap:8px; margin-bottom:6px; }
.actor dl { display:grid; grid-template-columns:auto 1fr; gap:2px 7px; margin:0; font-size:.82rem; }
.actor dt { color:#999; }
.actor dd { margin:0; }
.history { margin-top:12px; }
.run-links { display:flex; gap:6px; flex-wrap:wrap; }
.run-links a { color:#d5d5d5; border:1px solid #444; border-radius:4px; padding:4px 7px; text-decoration:none; font-size:.8rem; }
.run-links a:hover { color:#f27c11; border-color:#7b4a22; }
@media(max-width:1000px){ .grid{grid-template-columns:1fr}.metrics{grid-template-columns:repeat(3,1fr)} }
@media(max-width:600px){ .metrics{grid-template-columns:repeat(2,1fr)}.harness-head{display:block}.health{justify-content:flex-start;margin-top:8px} }
</style>
<main>
<div class="harness-page">
    <div class="harness-head">
        <div>
            <h1>CHIM Systems Harness</h1>
            <div class="muted">Drive real Skyrim actors through Background Life, memory, and Oghma soak tests.</div>
        </div>
        <div class="health">
            <span class="pill <?php echo $processorRunning ? 'ok' : 'bad'; ?>">Processor <?php echo $processorRunning ? 'online' : 'offline'; ?></span>
            <span class="pill <?php echo $heartbeat['fresh'] ? 'ok' : 'warn'; ?>">Game event <?php echo $heartbeat['fresh'] ? chimHarnessHtml($heartbeat['age_seconds'] . 's ago') : 'stale'; ?></span>
            <span class="pill <?php echo $privateRequest ? 'ok' : 'bad'; ?>"><?php echo $privateRequest ? 'Private network' : 'Controls locked'; ?></span>
        </div>
    </div>

    <?php if ($flash !== ''): ?>
        <div class="flash <?php echo chimHarnessHtml($flashType); ?>"><?php echo chimHarnessHtml($flash); ?></div>
    <?php endif; ?>

    <div class="grid">
        <section class="panel">
            <h2>New Run</h2>
            <div class="warning">
                Use a disposable test save. Generated actors remain in that save after cleanup; the harness removes their BgL tracking but does not delete live game references.
            </div>
            <form method="post">
                <input type="hidden" name="action" value="start">
                <div class="field">
                    <label for="scenario">Scenario</label>
                    <select id="scenario" name="scenario">
                        <?php foreach ($scenarios as $key => $scenario): ?>
                            <option value="<?php echo chimHarnessHtml($key); ?>"><?php echo chimHarnessHtml($scenario['label'] ?? $key); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="mode">Runtime mode</label>
                    <select id="mode" name="mode">
                        <option value="live">Live game control</option>
                    </select>
                    <small>Live mode waits for a current Skyrim heartbeat before changing actors.</small>
                </div>
                <div class="field">
                    <label for="duration">Soak duration (minutes)</label>
                    <input id="duration" type="number" name="duration_minutes" min="5" max="480" value="30">
                </div>
                <div class="field">
                    <label for="npc-filter">Existing NPC filter</label>
                    <input id="npc-filter" type="text" placeholder="Filter by name, race, or RefID">
                </div>
                <div class="field">
                    <label for="existing-npcs">Existing NPCs (optional, max 6)</label>
                    <select id="existing-npcs" name="existing_npcs[]" multiple>
                        <?php foreach ($availableNpcs as $npc): ?>
                            <option value="<?php echo intval($npc['id']); ?>"
                                data-search="<?php echo chimHarnessHtml(strtolower(($npc['npc_name'] ?? '') . ' ' . ($npc['race'] ?? '') . ' ' . ($npc['refid'] ?? ''))); ?>">
                                <?php echo chimHarnessHtml($npc['npc_name']); ?> | <?php echo chimHarnessHtml($npc['race'] ?? 'unknown'); ?> | <?php echo chimHarnessHtml($npc['refid']); ?><?php echo !empty($npc['bgl_enabled']) ? ' | BgL active' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label><input type="checkbox" name="test_save_ack" value="1"> I am using a disposable test save.</label>
                </div>
                <button class="primary" type="submit" <?php echo ($activeRun || !$privateRequest) ? 'disabled' : ''; ?>>Start Harness Run</button>
            </form>

            <div class="history">
                <h2>Recent Runs</h2>
                <div class="run-links">
                    <?php foreach ($recentRuns as $run): ?>
                        <a href="?embed=<?php echo $isEmbedded ? '1' : '0'; ?>&amp;run_id=<?php echo intval($run['id']); ?>">
                            #<?php echo intval($run['id']); ?> <?php echo chimHarnessHtml($run['scenario']); ?> | <?php echo chimHarnessHtml($run['status']); ?>
                        </a>
                    <?php endforeach; ?>
                    <?php if (!$recentRuns): ?><span class="muted">No runs yet.</span><?php endif; ?>
                </div>
            </div>
        </section>

        <section class="panel">
            <?php if ($selectedRun): ?>
                <?php $runMetrics = chimHarnessDecodeJson($selectedRun['metrics'] ?? null); ?>
                <div class="run-title">
                    <div>
                        <strong>#<?php echo intval($selectedRun['id']); ?> <?php echo chimHarnessHtml($selectedRun['name']); ?></strong>
                        <div class="muted"><?php echo chimHarnessHtml($selectedRun['mode']); ?> | started <?php echo chimHarnessHtml(chimHarnessTime($selectedRun['started_at'] ?? null)); ?></div>
                    </div>
                    <span class="pill <?php echo chimHarnessStatusClass((string)$selectedRun['status']); ?>"><?php echo chimHarnessHtml($selectedRun['status']); ?></span>
                </div>

                <?php if (!empty($selectedRun['error'])): ?><div class="flash error"><?php echo chimHarnessHtml($selectedRun['error']); ?></div><?php endif; ?>

                <div class="metrics">
                    <?php foreach ([
                        'active_actors' => 'Active actors',
                        'bgl_events' => 'BgL events',
                        'actions' => 'Actions',
                        'memory_events' => 'Memory events',
                        'memory_summaries' => 'Summaries',
                        'oghma_hits' => 'Oghma hits',
                        'oghma_unique_contexts' => 'Oghma contexts',
                        'data_quality_alerts' => 'Data alerts',
                        'llm_requests' => 'LLM requests',
                    ] as $key => $label): ?>
                        <div class="metric"><span><?php echo chimHarnessHtml($label); ?></span><strong><?php echo intval($runMetrics[$key] ?? 0); ?></strong></div>
                    <?php endforeach; ?>
                </div>

                <?php if (in_array($selectedRun['status'], chimHarnessActiveStatuses(), true) && intval($activeRun['id'] ?? 0) === intval($selectedRun['id'])): ?>
                    <form method="post" class="actions">
                        <input type="hidden" name="action" value="stop">
                        <input type="hidden" name="run_id" value="<?php echo intval($selectedRun['id']); ?>">
                        <button class="danger" type="submit">Stop and Restore</button>
                    </form>
                <?php endif; ?>

                <h2 style="margin-top:12px">Actors</h2>
                <div class="actor-grid">
                    <?php foreach ($actors as $actor): $metrics = chimHarnessDecodeJson($actor['metrics'] ?? null); ?>
                        <article class="actor">
                            <div class="actor-head">
                                <strong><?php echo chimHarnessHtml($actor['actor_name']); ?></strong>
                                <span class="pill <?php echo chimHarnessStatusClass((string)$actor['status']); ?>"><?php echo chimHarnessHtml($actor['status']); ?></span>
                            </div>
                            <dl>
                                <dt>Source</dt><dd><?php echo chimHarnessHtml($actor['source']); ?></dd>
                                <dt>RefID</dt><dd><?php echo chimHarnessHtml($actor['refid'] ?: '-'); ?></dd>
                                <dt>BgL</dt><dd><?php echo intval($metrics['bgl_events'] ?? 0); ?></dd>
                                <dt>Actions</dt><dd><?php echo intval($metrics['actions'] ?? 0); ?></dd>
                                <dt>Memory</dt><dd><?php echo intval($metrics['memory_events'] ?? 0); ?> events / <?php echo intval($metrics['memory_summaries'] ?? 0); ?> summaries</dd>
                                <dt>Oghma</dt><dd><?php echo intval($metrics['oghma_hits'] ?? 0); ?> selected topics</dd>
                                <dt>Requests</dt><dd><?php echo intval($metrics['llm_requests'] ?? 0); ?></dd>
                            </dl>
                            <?php if (!empty($actor['error'])): ?><div class="muted" style="color:#ff7f88;margin-top:6px"><?php echo chimHarnessHtml($actor['error']); ?></div><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>

                <h2>Timeline</h2>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Time</th><th>Stage</th><th>Level</th><th>Message</th></tr></thead>
                        <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td><?php echo chimHarnessHtml(date('H:i:s', intval($event['localts']))); ?></td>
                                <td><?php echo chimHarnessHtml($event['stage']); ?></td>
                                <td><span class="pill <?php echo $event['level'] === 'error' ? 'bad' : ($event['level'] === 'warn' ? 'warn' : 'idle'); ?>"><?php echo chimHarnessHtml($event['level']); ?></span></td>
                                <td><?php echo chimHarnessHtml($event['message']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <h2>No Harness Run</h2>
                <p class="muted">Choose a scenario to start collecting real runtime evidence.</p>
            <?php endif; ?>
        </section>
    </div>
</div>
</main>
<script>
const npcFilter = document.getElementById('npc-filter');
const npcSelect = document.getElementById('existing-npcs');
if (npcFilter && npcSelect) {
    npcFilter.addEventListener('input', () => {
        const query = npcFilter.value.trim().toLowerCase();
        Array.from(npcSelect.options).forEach(option => {
            option.hidden = query !== '' && !option.dataset.search.includes(query);
        });
    });
    npcSelect.addEventListener('change', () => {
        const selected = Array.from(npcSelect.selectedOptions);
        if (selected.length > 6) selected.slice(6).forEach(option => option.selected = false);
    });
}
<?php if ($activeRun): ?>
setTimeout(() => {
    if (!document.hidden && !document.querySelector('select:focus, input:focus')) {
        window.location.reload();
    }
}, 5000);
<?php endif; ?>
</script>
<?php
include __DIR__ . DIRECTORY_SEPARATOR . 'tmpl' . DIRECTORY_SEPARATOR . 'footer.html';
$buffer = ob_get_clean();
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $TITLE . '$3', $buffer);
echo $buffer;
