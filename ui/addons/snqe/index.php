<?php

// Common headers
$enginePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once $enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php";

$GLOBALS["ENGINE_PATH"] = $enginePath;
$stateFile = "{$ENGINE_PATH}/log/snqe_state.json";

$db = new sql();

// Function to read last N lines from a file
function getTailOfFile($filepath, $lines = 100) {
    if (!file_exists($filepath)) {
        return "File not found: " . htmlspecialchars($filepath);
    }
    
    $content = file_get_contents($filepath);
    if ($content === false) {
        return "Cannot read file: " . htmlspecialchars($filepath);
    }
    
    $allLines = explode("\n", $content);
    $lastLines = array_slice($allLines, -$lines);
    return implode("\n", $lastLines);
}

$logRunAgent = getTailOfFile("{$ENGINE_PATH}/log/log_run_agent.log", 100);
$serviceLog = getTailOfFile("{$ENGINE_PATH}/log/service.log", 100);

// Fetch running quests
function getRunningQuests($db) {
    try {
        $query = "SELECT quest_id, title, stage, created_at, updated_at FROM sneq_quests WHERE quest_run_state = 'running' ORDER BY updated_at DESC";
        $runningQuests = $db->fetchAll($query);
        return $runningQuests;
    } catch (Exception $e) {
        Logger::error("Failed to fetch running quests: " . $e->getMessage());
        return [];
    }
}

$runningQuests = getRunningQuests($db);

// Handle AJAX request for running quests
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_running_quests') {
    header('Content-Type: application/json');
    echo json_encode(['quests' => $runningQuests]);
    exit;
}

// Handle AJAX request for logs
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_logs') {
    header('Content-Type: application/json');
    echo json_encode([
        'agentLog' => getTailOfFile("{$ENGINE_PATH}/log/log_run_agent.log", 100),
        'serviceLog' => getTailOfFile("{$ENGINE_PATH}/log/service.log", 100)
    ]);
    exit;
}

// Handle form submission
$response = array();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_form') {
    $userprompt = isset($_POST['userprompt']) ? ($_POST['userprompt']) : '';
    $questtitle = isset($_POST['questtitle']) ? ($_POST['questtitle']) : '';
    $briefing = isset($_POST['briefing']) ? ($_POST['briefing']) : '';
    $suggested = isset($_POST['suggested']) ? ($_POST['suggested']) : '';
    $locations = isset($_POST['locationlist']) ? json_decode($_POST['locationlist'], true) : [];


    $state['userprompt'] = $userprompt;
    $state['briefing'] = $briefing;
    $state['questtitle'] = $questtitle;
    $state['suggested'] = $suggested;
    $state['locationlist'] = $locations;



    file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
    // Log the submission
    error_log("[SNQE Handler] Form submitted - Title: " . $questtitle . ", Briefing: " . $briefing);

    // Return success response
    $response = array(
        'status' => 'success',
        'message' => 'Form data received successfully',
        'data' => array(
            'userprompt' => $userprompt,
            'questtitle' => $questtitle,
            'briefing' => $briefing,
            'suggested' => $suggested
        ),
        'timestamp' => date('Y-m-d H:i:s')
    );

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

function sanitize_input($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quest Scenario Generator</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            padding: 40px 20px;
            color: #e0e0e0;
        }

        .main-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }

        .container {
            flex: 1;
            min-width: 400px;
            background: #0f3460;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
            padding: 40px;
            border: 1px solid #16213e;
        }

        .sidebar {
            flex: 0 0 300px;
            background: #0f3460;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
            padding: 25px;
            border: 1px solid #16213e;
            max-height: 600px;
            overflow-y: auto;
        }

        .sidebar h2 {
            color: #00d4ff;
            margin-bottom: 20px;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quest-status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            background: #10b981;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }

        .running-quests-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .quest-item {
            background: #1a1a2e;
            border: 1px solid #16213e;
            border-left: 3px solid #00d4ff;
            border-radius: 6px;
            padding: 15px;
            transition: all 0.3s ease;
        }

        .quest-item:hover {
            border-left-color: #7c3aed;
            box-shadow: 0 4px 12px rgba(0, 212, 255, 0.2);
        }

        .quest-item-title {
            color: #00d4ff;
            font-weight: 600;
            margin-bottom: 8px;
            word-break: break-word;
        }

        .quest-item-id {
            font-size: 0.8em;
            color: #888;
            margin-bottom: 5px;
            font-family: 'Courier New', monospace;
            word-break: break-all;
        }

        .quest-item-stage {
            color: #b0b0b0;
            font-size: 0.9em;
            margin-bottom: 8px;
        }

        .quest-item-time {
            color: #666;
            font-size: 0.75em;
            font-style: italic;
        }

        .no-running-quests {
            color: #666;
            text-align: center;
            padding: 40px 20px;
            font-style: italic;
        }

        h1 {
            color: #e0e0e0;
            margin-bottom: 30px;
            text-align: center;
            font-size: 2.5em;
            background: linear-gradient(135deg, #00d4ff 0%, #7c3aed 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #b0b0b0;
            font-weight: 600;
            font-size: 0.95em;
        }

        textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #16213e;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            resize: vertical;
            min-height: 120px;
            transition: all 0.3s ease;
            background-color: #1a1a2e;
            color: #e0e0e0;
        }

        textarea:focus {
            outline: none;
            border-color: #00d4ff;
            box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.2);
        }

        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #16213e;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            transition: all 0.3s ease;
            background-color: #1a1a2e;
            color: #e0e0e0;
        }

        input[type="text"]:focus {
            outline: none;
            border-color: #00d4ff;
            box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.2);
        }

        input[type="text"]:read-only {
            background-color: #16213e;
            cursor: default;
        }

        .log-box {
            width: 100%;
            padding: 12px;
            border: 2px solid #16213e;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
            background-color: #000000;
            color: #00ff00;
            overflow-y: auto;
            max-height: 300px;
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.4;
            resize: vertical;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        button {
            flex: 1;
            min-width: 200px;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 1em;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #00d4ff 0%, #7c3aed 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 212, 255, 0.4);
        }

        .btn-clear {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .btn-clear:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(239, 68, 68, 0.4);
        }

        button:active {
            transform: translateY(0);
        }

        .loading {
            display: none;
            text-align: center;
            color: #00d4ff;
            font-weight: 600;
            margin-top: 10px;
        }

        .loading.active {
            display: block;
        }

        @media (max-width: 1200px) {
            .main-wrapper {
                flex-wrap: wrap-reverse;
            }
            
            .sidebar {
                flex: 1;
                min-width: 350px;
                max-height: none;
            }
        }
    </style>
</head>

<body>
    <div class="main-wrapper">
        <div class="container">
            <h1>📖 Quest Scenario Generator</h1>

            <form>
                <div class="form-group">
                    <label for="suggested">Suggested</label>
                    <textarea name="suggested" id="suggested" placeholder="Enter suggestions here..."></textarea>
                </div>

                <div class="form-group">
                    <label for="userprompt">User Prompt</label>
                    <textarea name="userprompt" id="userprompt"
                        placeholder="Enter your quest scenario prompt here..."></textarea>
                </div>

                <div class="form-group">
                    <label for="questtitle">Quest Title</label>
                    <input type="text" name="questtitle" id="questtitle" placeholder="Quest title will appear here..."/>
                </div>

                <div class="form-group">
                    <label for="briefing">Quest Briefing</label>
                    <input type="text" name="briefing" id="briefing" placeholder="Briefing will appear here..." readonly />
                </div>

                <div class="form-group">
                    <label>Agent Log (last 100 lines)</label>
                    <div class="log-box" id="agentLog"><?php echo htmlspecialchars($logRunAgent); ?></div>
                </div>

                <div class="form-group">
                    <label>Service Log (last 100 lines)</label>
                    <div class="log-box" id="serviceLog"><?php echo htmlspecialchars($serviceLog); ?></div>
                </div>

                <div class="form-group" style="display:none;">
                    <label for="locationlist">Locations</label>
                    <select name="locationlist" id="locationlist" multiple readonly></select>
                </div>

                <div class="button-group">
                    <button type="button" class="btn-primary" onclick="generateScenario()">Generate Scenario</button>
                    <button type="button" class="btn-primary" onclick="submitFormData()"
                        style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">Create 1st step</button>
                    <button type="button" class="btn-clear" onclick="clearAllData()">Clear Data</button>
                </div>

                <div class="loading" id="loading">Generating scenario...</div>
            </form>
        </div>

        <div class="sidebar">
            <h2>
                <span class="quest-status-indicator"></span>
                Running Quests
            </h2>
            <div class="running-quests-list" id="runningQuestsList">
                <?php if (empty($runningQuests)): ?>
                    <div class="no-running-quests">No running quests</div>
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
        </div>
    </div>

    <script>

        // Auto-refresh running quests every 5 seconds
        function refreshRunningQuests() {
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>?action=get_running_quests', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    const questsList = document.getElementById('runningQuestsList');
                    
                    if (data.quests && data.quests.length > 0) {
                        let html = '';
                        data.quests.forEach(quest => {
                            html += `
                                <div class="quest-item">
                                    <div class="quest-item-title">${escapeHtml(quest.title || 'Untitled Quest')}</div>
                                    <div class="quest-item-id">ID: ${escapeHtml(quest.quest_id.substring(0, 8))}...</div>
                                    <div class="quest-item-stage">Stage: ${escapeHtml(quest.stage || 'N/A')}</div>
                                    <div class="quest-item-time">Updated: ${formatTime(quest.updated_at)}</div>
                                </div>
                            `;
                        });
                        questsList.innerHTML = html;
                    } else {
                        questsList.innerHTML = '<div class="no-running-quests">No running quests</div>';
                    }
                })
                .catch(error => {
                    console.error('Error refreshing quests:', error);
                });
        }

        // Auto-refresh logs every 10 seconds
        function refreshLogs() {
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>?action=get_logs', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.agentLog) {
                        document.getElementById('agentLog').textContent = data.agentLog;
                        document.getElementById('agentLog').scrollTop = document.getElementById('agentLog').scrollHeight;
                    }
                    if (data.serviceLog) {
                        document.getElementById('serviceLog').textContent = data.serviceLog;
                        document.getElementById('serviceLog').scrollTop = document.getElementById('serviceLog').scrollHeight;
                    }
                })
                .catch(error => {
                    console.error('Error refreshing logs:', error);
                });
        }

        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        // Format timestamp to HH:mm:ss
        function formatTime(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleTimeString('en-US', { hour12: false });
        }

        // Load quest title from localStorage
        function loadQuestTitleFromStorage() {
            const savedTitle = localStorage.getItem('snqe_questtitle');
            if (savedTitle) {
                document.querySelector('input[name="questtitle"]').value = savedTitle;
            }
        }

        // Load briefing from localStorage
        function loadBriefingFromStorage() {
            const savedBriefing = localStorage.getItem('snqe_briefing');
            if (savedBriefing) {
                document.querySelector('input[name="briefing"]').value = savedBriefing;
            }
        }

        // Start auto-refresh interval
        let refreshInterval;
        let logsRefreshInterval;
        window.addEventListener('DOMContentLoaded', function () {
            loadQuestTitleFromStorage();
            loadBriefingFromStorage();
            
            // Refresh immediately on load, then every 5 seconds
            refreshRunningQuests();
            refreshInterval = setInterval(refreshRunningQuests, 5000);
            
            // Refresh logs immediately on load, then every 10 seconds
            refreshLogs();
            logsRefreshInterval = setInterval(refreshLogs, 10000);
        });

        // Clean up interval on page unload
        window.addEventListener('beforeunload', function () {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
            if (logsRefreshInterval) {
                clearInterval(logsRefreshInterval);
            }
        });

        function updateSelectBox(elementId, items) {
            const select = document.getElementById(elementId);
            const existingItems = Array.from(select.options).map(opt => opt.textContent);

            items.forEach((item, index) => {
                // Only add if not already in the list
                if (!existingItems.includes(item)) {
                    const option = document.createElement('option');
                    option.value = existingItems.length + index;
                    option.textContent = item;
                    option.selected = true;
                    select.appendChild(option);
                }
            });

        }

        function submitToAgent0() {
            const userprompt = document.querySelector('textarea[name="userprompt"]').value;
            const questTitle = document.querySelector('input[name="questtitle"]').value;
            const briefing = document.querySelector('input[name="briefing"]').value;
            const suggested = document.querySelector('textarea[name="suggested"]').value;

            const loadingEl = document.getElementById('loading');

            loadingEl.classList.add('active');

            fetch('cmd/agent0.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    prompt: userprompt,
                    locationlist: [],
                    npclist: [],
                    spawneditemslist: [],
                    journallist: [],
                    rumorlist: [],
                    nextlist: [],
                    questtitle: questTitle,
                    briefing: briefing,
                    suggested: suggested
                })
            })
                .then(response => response.json())
                .then(data => {
                    // Populate userprompt with the scenario response
                    document.querySelector('textarea[name="userprompt"]').value = data.response || '';

                    // Update briefing if returned
                    if (data.briefing) {
                        document.querySelector('input[name="briefing"]').value = data.briefing;

                    }

                    // Update questtitle if returned
                    if (data.questtitle) {
                        document.querySelector('input[name="questtitle"]').value = data.questtitle || '';

                    }

                    if (data.locations) {
                        updateSelectBox('locationlist', data.locations);
                    }
                    loadingEl.classList.remove('active');
                })
                .catch(error => {
                    console.error('Error:', error);
                    loadingEl.classList.remove('active');
                });
        }

        function generateScenario() {
            submitToAgent0();
        }

        function submitFormData() {
            const userprompt = document.querySelector('textarea[name="userprompt"]').value;
            const questTitle = document.querySelector('input[name="questtitle"]').value;
            const briefing = document.querySelector('input[name="briefing"]').value;
            const suggested = document.querySelector('textarea[name="suggested"]').value;
            const locationListItems = Array.from(document.getElementById('locationlist').options).map(opt => opt.textContent);

            const loadingEl = document.getElementById('loading');
            loadingEl.textContent = 'Submitting form data...';
            loadingEl.classList.add('active');

            const formData = new FormData();
            formData.append('action', 'submit_form');
            formData.append('userprompt', userprompt);
            formData.append('questtitle', questTitle);
            formData.append('briefing', briefing);
            formData.append('suggested', suggested);
            formData.append('locationlist', JSON.stringify(locationListItems));

            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Save to localStorage
                        localStorage.setItem('snqe_questtitle', questTitle);
                        localStorage.setItem('snqe_briefing', briefing);
                        
                        alert('Form submitted successfully!\n\nQuest Title: ' + data.data.questtitle + '\nBriefing: ' + data.data.briefing + '\n\nTimestamp: ' + data.timestamp);
                    } else {
                        alert('Error: ' + data.message);
                    }
                    loadingEl.classList.remove('active');
                    loadingEl.textContent = 'Generating scenario...';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error submitting form data');
                    loadingEl.classList.remove('active');
                    loadingEl.textContent = 'Generating scenario...';
                });
        }

        function generateScenario() {
            submitToAgent0();
        }

        function clearAllData() {
            if (confirm('Are you sure you want to clear all data?')) {
                localStorage.removeItem('snqe_questtitle');
                localStorage.removeItem('snqe_briefing');

                document.querySelector('textarea[name="userprompt"]').value = '';
                document.querySelector('input[name="questtitle"]').value = '';
                document.querySelector('input[name="briefing"]').value = '';
            }
        }
    </script>
</body>

</html>