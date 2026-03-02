<?php

$enginePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once $enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php";

$GLOBALS["ENGINE_PATH"] = $enginePath;
$stateFile = "{$enginePath}log/snqe_state.json";

$db = new sql();

$isEmbed = (isset($_GET['embed']) && $_GET['embed'] == '1');

// Determine web root
$scriptPath = $_SERVER['SCRIPT_NAME'];
$uiPos = strpos($scriptPath, '/ui/');
$webRoot = ($uiPos !== false) ? substr($scriptPath, 0, $uiPos) : '';
$webRoot = rtrim($webRoot, '/');

// Function to read last N lines from a file
function getTailOfFile($filepath, $lines = 100) {
    if (!file_exists($filepath)) {
        return "File not found: " . htmlspecialchars($filepath);
    }
    $file = fopen($filepath, 'r');
    if ($file === false) {
        return "Cannot read file: " . htmlspecialchars($filepath);
    }
    fseek($file, 0, SEEK_END);
    $fileSize = ftell($file);
    $buffer = min($fileSize, 8192);
    $position = $fileSize;
    $lineCount = 0;
    $content = '';
    while ($position > 0 && $lineCount <= $lines) {
        $position = max(0, $position - $buffer);
        fseek($file, $position);
        $chunk = fread($file, min($buffer, $fileSize - $position));
        $content = $chunk . $content;
        $lineCount = substr_count($content, "\n");
    }
    fclose($file);
    $allLines = explode("\n", $content);
    $lastLines = array_slice($allLines, -$lines);
    return implode("\n", $lastLines);
}

$logRunAgent = getTailOfFile("{$enginePath}log/log_run_agent.log", 100);
$serviceLog  = getTailOfFile("{$enginePath}log/service.log", 100);

function getRunningQuests($db) {
    try {
        $query = "SELECT quest_id, title, stage, created_at, updated_at FROM sneq_quests WHERE quest_run_state = 'running' ORDER BY updated_at DESC";
        return $db->fetchAll($query);
    } catch (Exception $e) {
        Logger::error("Failed to fetch running quests: " . $e->getMessage());
        return [];
    }
}

$runningQuests = getRunningQuests($db);

// Fetch current pending step
function getPendingStep($db) {
    try {
        $query = "SELECT * FROM conf_opts WHERE id='snqe_pending_step'";
        $result = $db->fetchAll($query);
        if (!empty($result)) {
            return $result[0]['value'];
        }
        return 'No pending step';
    } catch (Exception $e) {
        Logger::error("Failed to fetch pending step: " . $e->getMessage());
        return 'Error fetching pending step';
    }
}

$pendingStep = getPendingStep($db);

// Handle AJAX request for running quests
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_running_quests') {
    header('Content-Type: application/json');
    echo json_encode(['quests' => $runningQuests, 'pendingStep' => getPendingStep($GLOBALS['db'])]);
    exit;
}

// AJAX: logs
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_logs') {
    header('Content-Type: application/json');
    echo json_encode([
        'agentLog'   => getTailOfFile("{$enginePath}log/log_run_agent.log", 100),
        'serviceLog' => getTailOfFile("{$enginePath}log/service.log", 100),
    ]);
    exit;
}

// Form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_form') {
    $userprompt = isset($_POST['userprompt']) ? $_POST['userprompt'] : '';
    $questtitle = isset($_POST['questtitle']) ? $_POST['questtitle'] : '';
    $briefing   = isset($_POST['briefing'])   ? $_POST['briefing']   : '';
    $suggested  = isset($_POST['suggested'])  ? $_POST['suggested']  : '';
    $locations  = isset($_POST['locationlist']) ? json_decode($_POST['locationlist'], true) : [];

    $state = [
        'userprompt'   => $userprompt,
        'briefing'     => $briefing,
        'questtitle'   => $questtitle,
        'suggested'    => $suggested,
        'locationlist' => $locations,
    ];
    file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
    chmod($stateFile, 0777);

    header('Content-Type: application/json');
    echo json_encode([
        'status'    => 'success',
        'message'   => 'Form data received successfully',
        'data'      => compact('userprompt', 'questtitle', 'briefing', 'suggested'),
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
    exit;
}

$uiPath = $enginePath . "ui" . DIRECTORY_SEPARATOR;
$TITLE = "🧭 AI Quest Manager";

ob_start();
include($uiPath . "tmpl/head.html");
require_once($uiPath . "profile_loader.php");
?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
@font-face {
    font-family: "MagicCards";
    src: url("<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf") format("truetype");
}

main {
    padding-top: <?php echo $isEmbed ? '20px' : '80px'; ?>;
    padding-bottom: 40px;
    padding-left: 16px;
    padding-right: 16px;
}

footer { position: fixed; bottom: 0; width: 100%; height: 20px; background: #031633; z-index: 100; }

.snqe-layout {
    display: flex;
    gap: 20px;
    align-items: flex-start;
    flex-wrap: wrap;
}

.snqe-main {
    flex: 1;
    min-width: 360px;
    background: linear-gradient(135deg, rgba(42,42,42,0.95), rgba(34,34,34,0.98));
    border: 1px solid #3a3a3a;
    border-radius: 8px;
    padding: 28px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

.snqe-sidebar {
    flex: 0 0 280px;
    background: linear-gradient(135deg, rgba(42,42,42,0.95), rgba(34,34,34,0.98));
    border: 1px solid #3a3a3a;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

.snqe-page-title {
    font-family: "MagicCards", serif;
    font-size: 1.8em;
    color: rgb(242,124,17);
    text-shadow: 1px 1px 3px rgba(0,0,0,0.6);
    word-spacing: 6px;
    margin: 0 0 24px 0;
}

.snqe-section-title {
    font-family: "MagicCards", serif;
    font-size: 1.1em;
    color: rgb(242,124,17);
    margin: 0 0 14px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.pulse-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    background: #10b981;
    border-radius: 50%;
    animation: pulse 2s infinite;
    flex-shrink: 0;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.4; }
}

.form-group { margin-bottom: 18px; }

.form-group label {
    display: block;
    margin-bottom: 6px;
    color: #cfd9ea;
    font-weight: 600;
    font-size: 0.9em;
}

.form-group textarea,
.form-group input[type="text"] {
    width: 100%;
    padding: 10px 12px;
    background: #1e1e1e;
    border: 1px solid #444;
    border-radius: 6px;
    color: #e0e0e0;
    font-family: 'Courier New', monospace;
    font-size: 0.88em;
    resize: vertical;
    transition: border-color 0.2s;
    box-sizing: border-box;
}

.form-group textarea { min-height: 100px; }

.form-group textarea:focus,
.form-group input[type="text"]:focus {
    outline: none;
    border-color: rgb(242,124,17);
    box-shadow: 0 0 0 2px rgba(242,124,17,0.15);
}

.form-group input[readonly] {
    background: #161616;
    color: #999;
    cursor: default;
}

.log-box {
    width: 100%;
    padding: 10px 12px;
    background: #000;
    border: 1px solid #333;
    border-radius: 6px;
    color: #00ff88;
    font-family: 'Courier New', monospace;
    font-size: 0.82em;
    overflow-y: auto;
    max-height: 260px;
    white-space: pre-wrap;
    word-wrap: break-word;
    line-height: 1.4;
    resize: vertical;
    box-sizing: border-box;
}

.btn-group { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; }

.btn-snqe {
    flex: 1;
    min-width: 160px;
    padding: 10px 18px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.95em;
    cursor: pointer;
    transition: all 0.2s ease;
    letter-spacing: 0.3px;
    color: #fff;
}

.btn-snqe-generate {
    background: linear-gradient(135deg, rgba(37,99,235,0.85), rgba(37,99,235,0.65));
    border: 1px solid rgba(138,155,182,0.3);
}
.btn-snqe-generate:hover { background: linear-gradient(135deg, rgba(47,109,245,0.9), rgba(47,109,245,0.75)); transform: translateY(-1px); }

.btn-snqe-create {
    background: linear-gradient(135deg, rgba(16,185,129,0.85), rgba(5,150,105,0.75));
    border: 1px solid rgba(52,211,153,0.3);
}
.btn-snqe-create:hover { background: linear-gradient(135deg, rgba(16,185,129,1), rgba(5,150,105,0.9)); transform: translateY(-1px); }

.btn-snqe-clear {
    background: linear-gradient(135deg, rgba(185,28,28,0.75), rgba(153,27,27,0.65));
    border: 1px solid rgba(239,68,68,0.3);
}
.btn-snqe-clear:hover { background: linear-gradient(135deg, rgba(220,38,38,0.9), rgba(185,28,28,0.8)); transform: translateY(-1px); }

.loading-msg {
    display: none;
    margin-top: 10px;
    color: rgb(242,124,17);
    font-weight: 600;
    font-size: 0.9em;
}
.loading-msg.active { display: block; }

.quest-item {
    background: rgba(30,30,30,0.8);
    border: 1px solid #3a3a3a;
    border-left: 3px solid rgb(242,124,17);
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 12px;
    transition: border-left-color 0.2s;
}
.quest-item:hover { border-left-color: #f0a040; }

.quest-item-title { color: rgb(242,124,17); font-weight: 600; margin-bottom: 5px; word-break: break-word; }
.quest-item-id    { font-size: 0.78em; color: #666; margin-bottom: 4px; font-family: 'Courier New', monospace; word-break: break-all; }
.quest-item-stage { color: #aaa; font-size: 0.88em; margin-bottom: 5px; }
.quest-item-time  { color: #666; font-size: 0.75em; font-style: italic; }

.no-quests { color: #666; text-align: center; padding: 30px 10px; font-style: italic; }

@media (max-width: 900px) {
    .snqe-layout { flex-direction: column; }
    .snqe-sidebar { flex: 1 1 100%; }
}
</style>

<?php if (!$isEmbed): ?>
<?php include($uiPath . "tmpl/navbar.php"); ?>
<?php endif; ?>

<main>
    <div class="snqe-layout">
        <div class="snqe-main">
            <div class="snqe-page-title">🧭 AI Quest Manager</div>

            <form id="snqeForm">
                <div class="btn-group">
                    <button type="button" class="btn-snqe btn-snqe-generate" onclick="generateScenario()">Generate Scenario</button>
                    <button type="button" class="btn-snqe btn-snqe-create" onclick="submitFormData()">Create 1st Step</button>
                    <button type="button" class="btn-snqe btn-snqe-clear" onclick="clearAllData()">Clear Data</button>
                </div>

                <div class="loading-msg" id="loading">Generating scenario...</div>


                <div class="form-group">
                    <label for="suggested">User suggestions</label>
                    <textarea name="suggested" id="suggested" placeholder="Enter suggestions here..."></textarea>
                </div>

                <div class="form-group">
                    <label for="userprompt">User Prompt</label>
                    <textarea name="userprompt" id="userprompt" placeholder="Enter your quest scenario prompt here..."></textarea>
                </div>

                <div class="form-group">
                    <label for="questtitle">Quest Title</label>
                    <input type="text" name="questtitle" id="questtitle" placeholder="Quest title will appear here..."  readonly />
                </div>

                <div class="form-group">
                    <label for="briefing">Quest Briefing</label>
                    <input type="text" name="briefing" id="briefing" placeholder="Briefing will appear here..." readonly />
                </div>

                <div class="form-group">
                    <label>Agent Log <small style="color:#666;">(last 100 lines)</small></label>
                    <div class="log-box" id="agentLog"><?php echo htmlspecialchars($logRunAgent); ?></div>
                </div>

                <div class="form-group">
                    <label>Service Log <small style="color:#666;">(last 100 lines)</small></label>
                    <div class="log-box" id="serviceLog"><?php echo htmlspecialchars($serviceLog); ?></div>
                </div>

                <div style="display:none;">
                    <select name="locationlist" id="locationlist" multiple></select>
                </div>

            </form>
        </div>

        <div class="snqe-sidebar">
            <div class="snqe-section-title">
                <span class="pulse-dot"></span>
                Running Quests
            </div>
            <div id="runningQuestsList">
                <?php if (empty($runningQuests)): ?>
                    <div class="no-quests">No running quests</div>
                <?php else: ?>
                    <?php foreach ($runningQuests as $quest): ?>
                        <div class="quest-item">
                            <div class="quest-item-title"><?php echo htmlspecialchars($quest['title'] ?? 'Untitled Quest'); ?></div>
                            <div class="quest-item-id">ID: <?php echo htmlspecialchars(substr($quest['quest_id'], 0, 8)); ?>...</div>
                            <div class="quest-item-stage">Stage: <?php echo htmlspecialchars($quest['stage'] ?? 'N/A'); ?></div>
                            <div class="quest-item-time">Updated: <?php echo date('H:i:s', strtotime($quest['updated_at'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="pending-step-container">
                <h3>⏳ Current Pending Step</h3>
                <div class="pending-step-value" id="pendingStepValue">
                    <?php echo htmlspecialchars($pendingStep); ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
const SNQE_SELF = '<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>';

function escapeHtml(text) {
    const map = { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

function formatTime(ts) {
    return new Date(ts).toLocaleTimeString('en-US', { hour12: false });
}

function refreshRunningQuests() {
    fetch(SNQE_SELF + '?action=get_running_quests')
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('runningQuestsList');
            if (!data.quests || data.quests.length === 0) {
                el.innerHTML = '<div class="no-quests">No running quests</div>';
                return;
            }
            el.innerHTML = data.quests.map(q => `
                <div class="quest-item">
                    <div class="quest-item-title">${escapeHtml(q.title || 'Untitled Quest')}</div>
                    <div class="quest-item-id">ID: ${escapeHtml(q.quest_id.substring(0, 8))}...</div>
                    <div class="quest-item-stage">Stage: ${escapeHtml(q.stage || 'N/A')}</div>
                    <div class="quest-item-time">Updated: ${formatTime(q.updated_at)}</div>
                </div>`).join('');
        })
        .catch(() => {});
}

function refreshLogs() {
    fetch(SNQE_SELF + '?action=get_logs')
        .then(r => r.json())
        .then(data => {
            const al = document.getElementById('agentLog');
            const sl = document.getElementById('serviceLog');
            if (data.agentLog)   { al.textContent = data.agentLog;   al.scrollTop = al.scrollHeight; }
            if (data.serviceLog) { sl.textContent = data.serviceLog; sl.scrollTop = sl.scrollHeight; }
        })
        .catch(() => {});
}

function updateSelectBox(elementId, items) {
    const select = document.getElementById(elementId);
    const existing = Array.from(select.options).map(o => o.textContent);
    items.forEach((item, i) => {
        if (!existing.includes(item)) {
            const opt = document.createElement('option');
            opt.value = existing.length + i;
            opt.textContent = item;
            opt.selected = true;
            select.appendChild(opt);
        }
    });
}

function generateScenario() {
    const userprompt = document.getElementById('userprompt').value;
    const questTitle = document.getElementById('questtitle').value;
    const briefing   = document.getElementById('briefing').value;
    const suggested  = document.getElementById('suggested').value;
    const loadingEl  = document.getElementById('loading');

    loadingEl.textContent = 'Generating scenario...';
    loadingEl.classList.add('active');

    fetch('cmd/agent0.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            prompt: userprompt, locationlist: [], npclist: [],
            spawneditemslist: [], journallist: [], rumorlist: [], nextlist: [],
            questtitle: questTitle, briefing, suggested
        })
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('userprompt').value = data.response || '';
        if (data.briefing)   document.getElementById('briefing').value   = data.briefing;
        if (data.questtitle) document.getElementById('questtitle').value = data.questtitle;
        if (data.locations)  updateSelectBox('locationlist', data.locations);
        loadingEl.classList.remove('active');
    })
    .catch(() => loadingEl.classList.remove('active'));
}

function submitFormData() {
    const userprompt  = document.getElementById('userprompt').value;
    const questTitle  = document.getElementById('questtitle').value;
    const briefing    = document.getElementById('briefing').value;
    const suggested   = document.getElementById('suggested').value;
    const locations   = Array.from(document.getElementById('locationlist').options).map(o => o.textContent);
    const loadingEl   = document.getElementById('loading');

    loadingEl.textContent = 'Submitting form data...';
    loadingEl.classList.add('active');

    const fd = new FormData();
    fd.append('action', 'submit_form');
    fd.append('userprompt', userprompt);
    fd.append('questtitle', questTitle);
    fd.append('briefing', briefing);
    fd.append('suggested', suggested);
    fd.append('locationlist', JSON.stringify(locations));

    fetch(SNQE_SELF, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                localStorage.setItem('snqe_questtitle', questTitle);
                localStorage.setItem('snqe_briefing', briefing);
                try { alert('Submitted!\n\nQuest: ' + data.data.questtitle + '\nBriefing: ' + data.data.briefing + '\n' + data.timestamp); } catch(_) {}
            } else {
                try { alert('Error: ' + data.message); } catch(_) {}
            }
            loadingEl.classList.remove('active');
            loadingEl.textContent = 'Generating scenario...';
        })
        .catch(() => { loadingEl.classList.remove('active'); });
}

function clearAllData() {
    if (!confirm('Clear all data?')) return;
    localStorage.removeItem('snqe_questtitle');
    localStorage.removeItem('snqe_briefing');
    document.getElementById('userprompt').value = '';
    document.getElementById('questtitle').value = '';
    document.getElementById('briefing').value   = '';
    document.getElementById('suggested').value  = '';
}

window.addEventListener('DOMContentLoaded', function() {
    const t = localStorage.getItem('snqe_questtitle');
    const b = localStorage.getItem('snqe_briefing');
    if (t) document.getElementById('questtitle').value = t;
    if (b) document.getElementById('briefing').value   = b;

    refreshRunningQuests();
    refreshLogs();
    setInterval(refreshRunningQuests, 5000);
    setInterval(refreshLogs, 10000);
});
</script>

<?php
include($uiPath . "tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $TITLE . '$3', $buffer);
echo $buffer;
?>
