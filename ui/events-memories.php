<?php
error_reporting(E_ERROR);
session_start();

// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

// Define base paths
define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'conf');
define('LIB_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'lib');

$configFilepath = CONFIG_PATH . DIRECTORY_SEPARATOR;

if (!file_exists($configFilepath."conf.php")) {
    @copy($configFilepath."conf.sample.php", $configFilepath."conf.php");   // Defaults
    die(header("Location: quickstart.php"));
}

// Load profiles through the centralized profile loader
require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

$TITLE = "Events & Memories";

ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");
?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
    /* Override main container styles */
    main {
        padding-top: 80px; /* Space for navbar */
        padding-bottom: 40px; /* Reduced space for footer */
        padding-left: 10px;
    }
    
    /* Override footer styles */
    footer {
        position: fixed;
        bottom: 0;
        width: 100%;
        height: 20px; /* Reduced footer height */
        background: #031633;
        z-index: 100
    }

    /* MagicCards font import */
    @font-face {
        font-family: 'MagicCards';
        src: url('css/font/MagicCardsNormal.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
    }

    /* Apply MagicCards font to titles */
    h1, h3 {
        font-family: 'MagicCards', sans-serif;
        letter-spacing: 1.5px;
    }

    /* Tab styles */
    .tab-container {
        margin: 20px 0;
    }

    .tab-buttons {
        display: flex;
        flex-wrap: wrap;
        margin-bottom: 20px;
        border-bottom: 2px solid #3a3a3a;
        gap: 5px;
        word-spacing: 5px;
    }

    .tab-button {
        background: #2a2a2a;
        border: none;
        padding: 12px 18px;
        color: #f8f9fa;
        cursor: pointer;
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
        transition: all 0.3s ease;
        font-size: 1em;
        white-space: nowrap;
        font-family: 'MagicCards', sans-serif;
        word-spacing: 5px;
        letter-spacing: 1.5px;
    }

    .tab-button:hover {
        background: #3a3a3a;
    }

    .tab-button.active {
        background: #1a1a1a;
        border-bottom: 2px solid rgb(212, 94, 0);
        margin-bottom: -2px;
    }

    .tab-content {
        display: none;
        background: #2a2a2a;
        padding: 20px;
        border-radius: 8px;
        border-top-left-radius: 0;
    }

    .tab-content.active {
        display: block;
    }

    /* Table Container Styles */
    .table-container {
        background-color: #2a2a2a;
        border-radius: 5px;
        padding: 15px;
        margin-bottom: 20px;
        overflow-x: auto;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    /* Table Styles */
    table {
        width: 100%;
        border-collapse: collapse;
        background-color: #3a3a3a;
        margin-bottom: 20px;
        font-size: small;
    }

    /* Header Cells */
    th {
        background-color: #1a1a1a;
        color: #fff;
        font-weight: bold;
        padding: 12px;
        text-align: left;
        border-bottom: 2px solid #444;
    }

    /* Data Cells */
    td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid #444;
        color: #f8f9fa;
    }

    /* Row Alternating Colors */
    tr:nth-child(even) {
        background-color:rgb(77, 77, 77);
    }

    /* Button Cell Alignment */
    td:has(button), td:has(.btn-base) {
        text-align: center;
    }

    /* Responsive Table */
    @media (max-width: 768px) {
        .table-container {
            margin: 10px -15px;
            border-radius: 0;
        }
        
        table {
            font-size: smaller;
        }
        
        th, td {
            padding: 8px;
        }

        .tab-button {
            padding: 10px 14px;
            font-size: 0.9em;
        }
    }

    /* Modal styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 100000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
    }

    .modal-content {
        background-color: #2a2a2a;
        margin: 3% auto;
        padding: 20px;
        border: 1px solid #444;
        width: 90%;
        max-width: 1600px;
        max-height: 90vh;
        overflow-y: auto;
        border-radius: 8px;
        color: #fff;
        position: relative;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
    }
    
    .view-contents-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        padding: 8px 16px;
        text-align: center;
        text-decoration: none;
        display: inline-block;
        font-size: 14px;
        margin: 2px;
        cursor: pointer;
        border-radius: 6px;
        transition: all 0.3s ease;
        font-weight: 600;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    
    .view-contents-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(102, 126, 234, 0.4);
    }

    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        position: sticky;
        z-index: 1;
    }

    .close:hover,
    .close:focus {
        color: #fff;
        text-decoration: none;
    }

    #modalText {
        white-space: pre-wrap;
        word-wrap: break-word;
        line-height: 1.8;
        padding: 20px;
        font-size: 13px;
        font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
        background: #1a1a1a;
        border-radius: 8px;
        color: #e0e0e0;
    }

    /* Prevent background interaction when modal is open */
    body.modal-open {
        overflow: hidden;
    }
</style>
<?php

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/navbar.php");

require_once(LIB_PATH .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once(LIB_PATH .DIRECTORY_SEPARATOR."misc_ui_functions.php");
require_once(LIB_PATH .DIRECTORY_SEPARATOR."chat_helper_functions.php");

// Include game timestamp utilities
require_once(dirname(__DIR__).DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."utils_game_timestamp.php");

$db = new sql();

// Handle actions
if ($_GET["clean"]) {
    $db->delete("responselog", "sent=1");
}
if ($_GET["reset"]) {
    $db->delete("eventlog", "true");
    header("Location: events-memories.php?tab=eventlog");
}
if ($_GET["cleanlog"]) {
    $db->delete("log", "true");
    header("Location: events-memories.php?tab=responselog");
}

// Handle delete_last for event log
if (isset($_GET['delete_last'])) {
    $delCount = (int)$_GET['delete_last'];
    if (in_array($delCount, [20, 50, 100])) {
        $db->query("
            DELETE FROM eventlog
            WHERE rowid IN (
                SELECT rowid
                FROM eventlog
                WHERE type NOT IN ('prechat','rechat','infonpc','request','infonpc_close','infoitems')
                ORDER BY gamets DESC, ts DESC, localts DESC, rowid DESC
                LIMIT $delCount
            )
        ");
        header("Location: events-memories.php?tab=eventlog");
        exit;
    }
}

// Handle memory summary save edits
if (isset($_POST['save_memory_edit'])) {
    $rowid = intval($_POST['rowid']);
    $summary = $_POST['summary'];
    $tags = $_POST['tags'];
    $companions = $_POST['companions'];
    
    $db->update(
        'memory_summary',
        "summary = '" . $db->escape($summary) . "', 
         tags = '" . $db->escape($tags) . "',
         companions = '" . $db->escape($companions) . "'",
        "rowid = " . $rowid
    );
    
    header("Location: events-memories.php?tab=memory&updated=1");
    exit;
}

// Handle memory summary delete
if (isset($_GET['delete_memory']) && !empty($_GET['delete_memory'])) {
    $rowid = intval($_GET['delete_memory']);
    $db->delete('memory_summary', "rowid = " . $rowid);
    header("Location: events-memories.php?tab=memory&deleted=1");
    exit;
}

// Get active tab from URL parameter, default to 'eventlog'
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'eventlog';

// Function to determine color based on time value
function getTimeColor($time) {
    if ($time <= 2) return "#88cc88"; // green
    if ($time <= 5) return "#ffff00"; // yellow
    if ($time <= 8) return "#ffa500"; // orange
    return "#ff6666"; // red
}
?>

<!-- Modal HTML -->
<div id="contentModal" class="modal">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h2 style="margin: 0; color: rgb(242, 124, 17); font-family: 'MagicCards', sans-serif;">📜 Prompt Viewer</h2>
            <div>
                <button id="copyPromptBtn" class="btn-base btn-primary" style="margin-right: 10px; padding: 8px 16px;">📋 Copy</button>
                <span class="close">&times;</span>
            </div>
        </div>
        <div id="modalText"></div>
    </div>
</div>

<div class="container-fluid">
    <h1 class='my-2'>Events & Memories 
        <a href="https://dwemerdynamics.hostwiki.io/en/Rechat" target="_blank" rel="noopener" 
           style="display: inline-block; margin-left: 15px; color: rgb(242, 124, 17); text-decoration: none; font-size: 0.7em; vertical-align: top; border: 2px solid rgb(242, 124, 17); border-radius: 50%; width: 24px; height: 24px; text-align: center; line-height: 20px; transition: all 0.3s ease;" 
           title="View detailed documentation about Events, Response Logs, and Rechat System"
           onmouseover="this.style.background='rgb(242, 124, 17)'; this.style.color='white';" 
           onmouseout="this.style.background='transparent'; this.style.color='rgb(242, 124, 17)';">ℹ</a>
    </h1>

    <div class="tab-container">
        <div class="tab-buttons">
            <button class="tab-button <?php echo $activeTab === 'eventlog' ? 'active' : ''; ?>" onclick="switchTab('eventlog')">
                📝 Events
            </button>
            <button class="tab-button <?php echo $activeTab === 'responselog' ? 'active' : ''; ?>" onclick="switchTab('responselog')">
                💬 AI Responses
            </button>
            <button class="tab-button <?php echo $activeTab === 'memory' ? 'active' : ''; ?>" onclick="switchTab('memory')">
                🧠 Memories 
                <a href="https://dwemerdynamics.hostwiki.io/en/diaries-memories" target="_blank" rel="noopener" 
                   style="display: inline-block; margin-left: 8px; color: rgb(242, 124, 17); text-decoration: none; font-size: 0.7em; vertical-align: top; border: 2px solid rgb(242, 124, 17); border-radius: 50%; width: 16px; height: 16px; text-align: center; line-height: 12px; transition: all 0.3s ease;" 
                   title="View detailed documentation about Diaries and Memory System"
                   onclick="event.stopPropagation();"
                   onmouseover="this.style.background='rgb(242, 124, 17)'; this.style.color='white';" 
                   onmouseout="this.style.background='transparent'; this.style.color='rgb(242, 124, 17)';">ℹ</a>
            </button>
            <button class="tab-button <?php echo $activeTab === 'quests' ? 'active' : ''; ?>" onclick="switchTab('quests')">
                🎯 Active Quests
            </button>
            <button class="tab-button <?php echo $activeTab === 'books' ? 'active' : ''; ?>" onclick="switchTab('books')">
                📚 Books
            </button>
            <button class="tab-button <?php echo $activeTab === 'mapview' ? 'active' : ''; ?>" onclick="switchTab('mapview')">
                🗺️ Background Life
            </button>
            
        </div>

        <!-- Event Log Tab -->
        <div id="eventlog-tab" class="tab-content <?php echo $activeTab === 'eventlog' ? 'active' : ''; ?>">
            <?php
            // Event Log title with integrated monitor toggle and delete buttons
            $isAutoRefresh = isset($_GET["autorefresh"]) && $_GET["autorefresh"];
            echo "<div style='display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin: 20px 0;'>";
            
            if ($isAutoRefresh) {
                echo "<button onclick=\"window.location.href='events-memories.php?tab=eventlog'\" class='btn-base btn-secondary' style='padding: 8px 12px; font-size: 0.9em;' title='Stop monitoring events'>⏸️ Stop Live</button>";
                echo "<span style='margin-left: 10px; color: #28a745; font-weight: bold; font-size: 0.9em;'>🔴 LIVE</span>";
            } else {
                echo "<button onclick=\"window.location.href='events-memories.php?tab=eventlog&autorefresh=true'\" class='btn-base btn-primary' style='padding: 8px 12px; font-size: 0.9em;' title='Start monitoring events with auto-refresh'>📡 Monitor Live</button>";
            }
            
            // Add delete buttons inline
            echo "<div style='margin-left: auto; display: flex; gap: 5px; flex-wrap: wrap;'>";
            echo "<button onclick=\"if(confirm('Are you sure you want to delete the last 20 events?')) window.location.href='events-memories.php?tab=eventlog&delete_last=20'\" class='btn-base btn-danger' style='padding: 6px 10px; font-size: 0.8em;'>Delete Latest 20</button>";
            echo "<button onclick=\"if(confirm('Are you sure you want to delete the last 50 events?')) window.location.href='events-memories.php?tab=eventlog&delete_last=50'\" class='btn-base btn-danger' style='padding: 6px 10px; font-size: 0.8em;'>Delete Latest 50</button>";
            echo "<button onclick=\"if(confirm('Are you sure you want to delete the last 100 events?')) window.location.href='events-memories.php?tab=eventlog&delete_last=100'\" class='btn-base btn-danger' style='padding: 6px 10px; font-size: 0.8em;'>Delete Latest 100</button>";
            echo "<button onclick=\"deleteAllEventsConfirm()\" class='btn-base btn-danger' style='padding: 6px 10px; font-size: 0.8em; background-color: #dc2626; font-weight: bold;'>⚠️ Delete ALL</button>";
            echo "</div>";
            echo "</div>";

            $limit = isset($_GET["limit"]) ? intval($_GET["limit"]) : 100;
            $page = isset($_GET["page"]) ? max(1, intval($_GET["page"])) : 1;
            $offset = ($page - 1) * $limit;
            
            $results = $db->fetchAll(
                "SELECT type, data, people, gamets, localts, ts, ROWID
                 FROM eventlog a
                 WHERE type NOT IN ('prechat','rechat','infonpc','request','infonpc_close','addnpc','user_input','infosave','init','playerinfo','oghma_import','biography_import','dynamic_oghma_import','infoitems')
                 ORDER BY gamets DESC, ts DESC, localts DESC, rowid DESC
                 LIMIT $limit OFFSET $offset"
            );
            
            $columnHeaders = [
                'type' => 'Event',
                'data' => 'Events',
                'gamets' => '<a href="https://en.uesp.net/wiki/Lore:Calendar" target="_blank" style="color: yellow;">Tamrielic Time</a>',
                'localts' => 'Time (UTC)',
                'ts' => 'TS',
            ];
            
            $mappedResults = array_map(function ($row) use ($columnHeaders) {
                $mappedRow = [];
                foreach ($row as $key => $value) {
                    if ($key === 'gamets' && !empty($value)) {
                        $value = convert_gamets2skyrim_long_date2($value);
                    }
                    else if ($key === 'localts' && !empty($value)) {
                        $dt = new DateTime("@$value");
                        $dt->setTimezone(new DateTimeZone('UTC'));
                        $value = $dt->format('d-m-Y H:i:s');
                    }
                    
                    // Special handling for chat events
                    if ($row['type'] === 'chat' && ($key === 'data' || $key === 'type')) {
                        $value = '<span style="color:rgb(255, 255, 255);">' . htmlspecialchars($value ?? '') . '</span>';
                    } else {
                        $value = htmlspecialchars($value ?? '');
                    }
                    
                    // Map ROWID to lowercase rowid for delete functionality
                    if ($key === 'ROWID') {
                        $mappedRow['rowid'] = $value;
                    } else if ($key === 'data') {
                        // Assign Events value
                        $mappedRow[$columnHeaders[$key] ?? $key] = $value;
                        // Derive People Present from JSON in original data if available
                        $peoplePresent = trim((string)($row['people'] ?? ''));
                        $raw = $row['data'] ?? '';
                        if ($peoplePresent === '' && is_string($raw) && $raw !== '') {
                            $j = json_decode($raw, true);
                            if (is_array($j)) {
                                if (!empty($j['people'])) {
                                    if (is_array($j['people'])) { $peoplePresent = implode(', ', array_map('strval', $j['people'])); }
                                    else { $peoplePresent = (string)$j['people']; }
                                } else if (!empty($j['companions'])) {
                                    if (is_array($j['companions'])) { $peoplePresent = implode(', ', array_map('strval', $j['companions'])); }
                                    else { $peoplePresent = (string)$j['companions']; }
                                } else if (!empty($j['speaker'])) {
                                    $peoplePresent = (string)$j['speaker'];
                                }
                            }
                        }
                        $mappedRow['People Present'] = htmlspecialchars($peoplePresent);
                    } else if ($key === 'people') {
                        // Skip rendering raw people column; we show only 'People Present'
                        continue;
                    } else {
                        $mappedRow[$columnHeaders[$key] ?? $key] = $value;
                    }
                }
                return $mappedRow;
            }, $results);
            
            // Set the table parameter for delete functionality
            $_GET["table"] = "eventlog";
            
            // Generate pagination buttons
            $prevPage = max(1, $page - 1);
            $nextPage = $page + 1;
            
            // Get total count for pagination
            $countQuery = "SELECT COUNT(*) as total FROM eventlog WHERE type NOT IN ('prechat','rechat','infonpc','request','infonpc_close','addnpc','user_input','infosave','init','infoitems')";
            $countResult = $db->fetchAll($countQuery);
            $totalRecords = $countResult[0]['total'];
            $totalPages = ceil($totalRecords / $limit);
            
            echo "<div class='pagination-buttons' style='margin: 10px 0;'>";
            
            if ($page > 1) {
                echo "<button onclick=\"window.location.href='events-memories.php?tab=eventlog&page=$prevPage&limit=$limit'\" class='btn-base btn-primary'>Previous</button> ";
            }
            
            for ($i = 1; $i <= 5 && $i <= $totalPages; $i++) {
                if ($i == $page) {
                    echo "<button onclick=\"window.location.href='events-memories.php?tab=eventlog&page=$i&limit=$limit'\" class='btn-base btn-secondary' style='background-color: #6c757d;'>$i</button> ";
                } else {
                    echo "<button onclick=\"window.location.href='events-memories.php?tab=eventlog&page=$i&limit=$limit'\" class='btn-base btn-primary'>$i</button> ";
                }
            }
            
            if ($totalPages > 10) {
                echo "<span style='margin: 0 5px; color: #fff;'>...</span>";
                
                $startLastPages = max(6, $totalPages - 4);
                for ($i = $startLastPages; $i <= $totalPages; $i++) {
                    if ($i == $page) {
                        echo "<button onclick=\"window.location.href='events-memories.php?tab=eventlog&page=$i&limit=$limit'\" class='btn-base btn-secondary' style='background-color: #6c757d;'>$i</button> ";
                    } else {
                        echo "<button onclick=\"window.location.href='events-memories.php?tab=eventlog&page=$i&limit=$limit'\" class='btn-base btn-primary'>$i</button> ";
                    }
                }
            }
            
            if ($page < $totalPages) {
                echo "<button onclick=\"window.location.href='events-memories.php?tab=eventlog&page=$nextPage&limit=$limit'\" class='btn-base btn-primary'>Next</button>";
            }
            
            echo "</div>";
            
            echo "<script>
            function deleteAllEventsConfirm() {
                var userInput = prompt('THIS WILL DELETE ALL EVENTS IN THE EVENT LOG!\\n\\nEvents are used for AI context. This action cannot be undone.\\n\\nTo confirm this dangerous operation, please type exactly: Delete');
                if (userInput === 'Delete') {
                    window.location.href = 'events-memories.php?reset=true&table=event';
                } else if (userInput !== null) {
                    alert('Operation cancelled. You must type exactly \"Delete\" to confirm.');
                }
            }
            </script>";
            
            print_array_as_table($mappedResults);
            
            if (isset($_GET["autorefresh"]) && $_GET["autorefresh"]) {
                header("Refresh:5");
            }
            ?>
        </div>

        <!-- Response Log Tab -->
        <div id="responselog-tab" class="tab-content <?php echo $activeTab === 'responselog' ? 'active' : ''; ?>">
            <?php
            // Helper to extract Oghma topic and level from the stored JSON in prompt
            if (!function_exists('extractOghmaTopicAndLevel')) {
                function extractOghmaTopicAndLevel($rawPromptValue) {
                    $result = [null, 'none'];
                    if (!is_string($rawPromptValue) || $rawPromptValue === '') {
                        return $result;
                    }

                    // Remove HTML breaks added by nl2br and any HTML tags
                    $clean = str_replace(["<br />", "<br>", "<br/>"], "\n", $rawPromptValue);
                    $clean = html_entity_decode(strip_tags($clean), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                    // Try to decode JSON payload recorded in prompt
                    $decoded = json_decode($clean, true);

                    $text = '';
                    if (is_array($decoded)) {
                        // Prefer messages inside ['full']['messages'] when available
                        if (isset($decoded['full']) && is_array($decoded['full']) && isset($decoded['full']['messages']) && is_array($decoded['full']['messages'])) {
                            foreach ($decoded['full']['messages'] as $msg) {
                                if (isset($msg['content']) && is_string($msg['content'])) {
                                    $text .= $msg['content'] . "\n";
                                }
                            }
                        } elseif (isset($decoded['messages']) && is_array($decoded['messages'])) {
                            foreach ($decoded['messages'] as $msg) {
                                if (isset($msg['content']) && is_string($msg['content'])) {
                                    $text .= $msg['content'] . "\n";
                                }
                            }
                        } else {
                            // Fallback: stringify decoded structure
                            $text = json_encode($decoded);
                        }
                    } else {
                        // Not JSON, use cleaned text
                        $text = $clean;
                    }

                    // Normalize newlines
                    $text = str_replace(["\r\n", "\r"], "\n", $text);

                    // 1) Advanced knowledge topic
                    if (preg_match('/#Lore Information\s*\((?=[^)]*advanced knowledge)[^)]*\):\s*([^"\<\r\n]+)/i', $text, $m)) {
                        $topic = trim(strip_tags($m[1]));
                        if ($topic !== '') {
                            return [$topic, 'advanced'];
                        }
                    }

                    // 2) Basic knowledge topic (handles phrasing like "You only have basic knowledge")
                    if (preg_match('/#Lore Information\s*\((?=[^)]*basic knowledge)[^)]*\):\s*([^"\<\r\n]+)/i', $text, $m)) {
                        $topic = trim(strip_tags($m[1]));
                        if ($topic !== '') {
                            return [$topic, 'basic'];
                        }
                    }

                    // 3) Explicit none case: do not know anything about <topic>
                    if (preg_match('/#Lore Information[^\n]*\nYou do not know ANYTHING about\s+([^"\<\r\n]+)/i', $text, $m)) {
                        $topic = trim(strip_tags($m[1]));
                        if ($topic !== '') {
                            return [$topic, 'none'];
                        }
                    }

                    return $result;
                }
            }

            $limit = isset($_GET["limit"]) ? intval($_GET["limit"]) : 50;
            $page = isset($_GET["page"]) ? max(1, intval($_GET["page"])) : 1;
            $offset = ($page - 1) * $limit;

            $results = $db->fetchAll(
                "SELECT A.*, ROWID 
                 FROM log a 
                 ORDER BY localts DESC, rowid DESC 
                 LIMIT $limit OFFSET $offset"
            );
        
            $columnHeaders = [
                'localts' => 'Time (UTC)',
                'response' => 'AI Response',
                'prompt' => 'Prompt',
                'url' => 'HTTP Request'
            ];
        
            $mappedResults = array_map(function ($row) use ($columnHeaders) {
                $mappedRow = [];
                foreach ($row as $key => $value) {
                    if ($key === 'prompt') {
                        // Parse and format like context_sent_to_llm with HTML for better display
                        $formattedPrompt = '';
                        $rawPrompt = $value ?? '';
                        
                        // Clean HTML artifacts first (fixes <br /> spam) and decode entities
                        $rawPromptHtmlClean = str_replace(["<br />","<br>","<br/>"] , "\n", (string)$rawPrompt);
                        $rawPromptDecoded = html_entity_decode($rawPromptHtmlClean, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        
                        // Try to decode JSON from cleaned/decoded content
                        $decoded = json_decode($rawPromptDecoded, true);
                        if (is_array($decoded)) {
                            // Extract model info
                            $model = isset($decoded['full']['model']) ? $decoded['full']['model'] : 'unknown';
                            
                            // Start with HTML formatted array
                            $formattedPrompt = '<div style="font-family: \'Consolas\', monospace; line-height: 1.6;">';
                            $formattedPrompt .= '<div style="color: #569cd6;">array</div> (';
                            $formattedPrompt .= '<div style="padding-left: 20px;">';
                            $formattedPrompt .= '<div style="color: #9cdcfe;">\'model\'</div> <span style="color: #d4d4d4;">=></span> <span style="color: #ce9178;">\''. htmlspecialchars($model) .'\'</span>,</div>';
                            $formattedPrompt .= '<div style="padding-left: 20px;"><div style="color: #9cdcfe;">\'messages\'</div> <span style="color: #d4d4d4;">=></span></div>';
                            $formattedPrompt .= '<div style="padding-left: 20px;"><div style="color: #569cd6;">array</div> (</div>';
                            
                            // Get messages
                            $messages = [];
                            if (isset($decoded['full']) && is_array($decoded['full'])) {
                                if (isset($decoded['full']['messages']) && is_array($decoded['full']['messages'])) {
                                    $messages = $decoded['full']['messages'];
                                }
                            } else if (isset($decoded['messages']) && is_array($decoded['messages'])) {
                                $messages = $decoded['messages'];
                            }
                            
                            // Format each message with collapsible sections
                            foreach ($messages as $msgIndex => $msg) {
                                if (isset($msg['role']) && isset($msg['content'])) {
                                    $role = $msg['role'];
                                    $content = $msg['content'];
                                    $escapedContent = htmlspecialchars($content);
                                    $contentPreview = mb_substr($content, 0, 100);
                                    $escapedPreview = htmlspecialchars($contentPreview);
                                    $isTruncated = strlen($content) > 100;
                                    
                                    $roleColor = $role === 'system' ? '#4ec9b0' : ($role === 'user' ? '#dcdcaa' : '#c586c0');
                                    
                                    $formattedPrompt .= '<div style="padding-left: 40px; margin: 10px 0; border-left: 3px solid ' . $roleColor . '; padding-left: 15px;">';
                                    $formattedPrompt .= '<div style="color: #b5cea8;">' . $msgIndex . '</div> <span style="color: #d4d4d4;">=></span>';
                                    $formattedPrompt .= '<div style="padding-left: 20px;"><div style="color: #569cd6;">array</div> (</div>';
                                    $formattedPrompt .= '<div style="padding-left: 40px;"><span style="color: #9cdcfe;">\'role\'</span> <span style="color: #d4d4d4;">=></span> <span style="color: #ce9178; font-weight: bold;">\'' . htmlspecialchars($role) . '\'</span>,</div>';
                                    $formattedPrompt .= '<div style="padding-left: 40px;">';
                                    $formattedPrompt .= '<span style="color: #9cdcfe;">\'content\'</span> <span style="color: #d4d4d4;">=></span> ';
                                    
                                    if ($isTruncated) {
                                        $uniqueId = 'msg_' . $msgIndex . '_' . md5($content);
                                        // Expanded by default
                                        $formattedPrompt .= '<button onclick="toggleContent(\'' . $uniqueId . '\')" style="background: #007acc; color: white; border: none; padding: 2px 8px; border-radius: 3px; cursor: pointer; font-size: 11px; margin-left: 5px;">▲ Collapse</button>';
                                        $formattedPrompt .= '<div id="' . $uniqueId . '_preview" style="display:none; color: #ce9178; white-space: pre-wrap; margin-top: 5px;">\'' . $escapedPreview . '...\'</div>';
                                        $formattedPrompt .= '<div id="' . $uniqueId . '_full" style="display: block; color: #ce9178; white-space: pre-wrap; margin-top: 5px; max-height: 400px; overflow-y: auto; background: #1e1e1e; padding: 10px; border-radius: 5px;">\'' . $escapedContent . '\'</div>';
                                    } else {
                                        $formattedPrompt .= '<div style="color: #ce9178; white-space: pre-wrap; margin-top: 5px;">\'' . $escapedContent . '\'</div>';
                                    }
                                    
                                    $formattedPrompt .= '</div>';
                                    $formattedPrompt .= '<div style="padding-left: 20px;">),</div>';
                                    $formattedPrompt .= '</div>';
                                }
                            }
                            
                            $formattedPrompt .= '<div style="padding-left: 20px;">),</div>';
                            $formattedPrompt .= '<div>)</div>';
                            $formattedPrompt .= '</div>';
                        }
                        
                        // Fallback to cleaned raw if parsing failed
                        if (empty($formattedPrompt)) {
                            $formattedPrompt = '<pre style="white-space: pre-wrap; word-wrap: break-word;">' . htmlspecialchars($rawPromptDecoded, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
                        }
                        
                        // Store formatted prompt in a hidden div and reference it by ID
                        $promptId = 'prompt_' . $row['ROWID'];
                        $hiddenPrompt = '<div id="' . $promptId . '" style="display:none;">' . $formattedPrompt . '</div>';
                        $mappedRow[$columnHeaders[$key] ?? $key] = $hiddenPrompt . '<button class="view-contents-btn" data-prompt-id="' . $promptId . '">🧾 View Prompt</button>';
                    } else if ($key === 'response') {
                        $mappedRow[$columnHeaders[$key] ?? $key] = '<div class="full-content">' . nl2br(htmlspecialchars($value ?? '')) . '</div>';
                        // Insert Oghma Topic column immediately after AI Response
                        list($oghmaTopic, $oghmaLevel) = extractOghmaTopicAndLevel($row['prompt'] ?? '');
                        if ($oghmaTopic) {
                            $mappedRow['Oghma Topic'] = htmlspecialchars($oghmaTopic) . ' (' . htmlspecialchars($oghmaLevel) . ')';
                        } else {
                            $mappedRow['Oghma Topic'] = 'None';
                        }
                    } else if ($key === 'localts' && !empty($value)) {
                        $dt = new DateTime("@$value");
                        $dt->setTimezone(new DateTimeZone('UTC'));
                        $mappedRow[$columnHeaders[$key]] = $dt->format('d-m-Y H:i:s');
                    } else if ($key === 'url') {
                        if (strpos($row['response'], 'Array') === 0) {
                            $mappedRow[$columnHeaders[$key] ?? $key] = preg_replace('/ in \d+\.?\d* secs$/', '', $value);
                        }
                        else if (strpos($value, '[AI secs]') !== false) {
                            $pattern = '/\[AI secs\]\s+([\d.]+)\s+\[TTS secs\]\s+([\d.]+)/';
                            if (preg_match($pattern, $value, $matches)) {
                                $aiTime = floatval($matches[1]);
                                $totalTtsTime = floatval($matches[2]);
                                $actualTtsTime = $totalTtsTime - $aiTime;
                                
                                $aiTimeFormatted = number_format($aiTime, 2);
                                $ttsTimeFormatted = number_format($actualTtsTime, 2);
                                
                                $aiColor = getTimeColor($aiTime);
                                $ttsColor = getTimeColor($actualTtsTime);
                                $totalColor = getTimeColor($totalTtsTime);
                                
                                $baseText = substr($value, 0, strpos($value, '[AI secs]'));
                                
                                $mappedRow[$columnHeaders[$key] ?? $key] = 
                                    $baseText . 
                                    "<br>[LLM] <span style='color: " . $aiColor . "'>" . $aiTimeFormatted . "</span>" .
                                    " [TTS] <span style='color: " . $ttsColor . "'>" . $ttsTimeFormatted . "</span>" .
                                    " [Total]: <span style='color: " . $totalColor . "'>" . $totalTtsTime . "</span>";
                            } else {
                                $mappedRow[$columnHeaders[$key] ?? $key] = $value;
                            }
                        } else {
                            $mappedRow[$columnHeaders[$key] ?? $key] = $value;
                        }
                    } else {
                        // Map ROWID to lowercase rowid for delete functionality
                        if ($key === 'ROWID') {
                            $mappedRow['rowid'] = $value;
                        } else {
                            $mappedRow[$columnHeaders[$key] ?? $key] = $value;
                        }
                    }
                }
                return $mappedRow;
            }, $results);
            
            // Set the table parameter for delete functionality
            $_GET["table"] = "log";
        
            // Response Log title with inline action buttons
            echo "<div style='display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin: 20px 0;'>";
            echo "<div style='margin-left: auto; display: flex; gap: 10px; flex-wrap: wrap;'>";
            echo "<button onclick=\"if(confirm('This will clear all the entries in the Response Log. ARE YOU SURE?')) window.location.href='events-memories.php?cleanlog=true'\" class='btn-base btn-danger' style='padding: 8px 12px; font-size: 0.9em;'>Clean Response Log</button>";
            echo "<button onclick=\"window.open('index.php?export=log', '_blank')\" class='btn-base btn-primary' style='padding: 8px 12px; font-size: 0.9em;'>Export Response Log</button>";
            echo "</div>";
            echo "</div>";
        
            $prevPage = max(1, $page - 1);
            $nextPage = $page + 1;
        
            echo "<div class='pagination-buttons' style='margin: 10px 0;'>";
            if ($page > 1) {
                echo "<button onclick=\"window.location.href='events-memories.php?tab=responselog&page=$prevPage&limit=$limit'\" class='btn-base btn-primary'>Previous</button> ";
            }
            echo "<button onclick=\"window.location.href='events-memories.php?tab=responselog&page=$nextPage&limit=$limit'\" class='btn-base btn-primary'>Next</button>";
            echo "</div>";
        
            print_array_as_table($mappedResults);
            ?>
        </div>

        
        <!-- Memory Summaries Tab -->
        <div id="memory-tab" class="tab-content <?php echo $activeTab === 'memory' ? 'active' : ''; ?>">
            <?php
            // Show success/delete messages
            if (isset($_GET['updated'])) {
                echo "<div style='background: #28a745; color: white; padding: 10px; border-radius: 5px; margin: 10px 0;'>Memory summary updated successfully!</div>";
            }
            if (isset($_GET['deleted'])) {
                echo "<div style='background: #dc3545; color: white; padding: 10px; border-radius: 5px; margin: 10px 0;'>Memory summary deleted successfully!</div>";
            }

            $results = $db->fetchAll("SELECT gamets_truncated, n, summary, companions, tags, classifier, uid, ROWID as rowid, packed_message, native_vec 
                                    FROM memory_summary 
                                    ORDER BY gamets_truncated DESC, rowid DESC 
                                    LIMIT 150");

            $processedResults = [];
            foreach ($results as $row) {
                $displayHtml = "<div id='display-{$row['rowid']}'>
                    <div class='summary-section'>
                        <span class='summary-content'>" . nl2br(htmlspecialchars($row['summary'] ?? '')) . "</span>
                    </div>
                    <div class='summary-section'>
                        <span class='summary-label'>People:</span>
                        <span class='summary-content'>" . htmlspecialchars($row['companions'] ?? '') . "</span>
                    </div>
                    <div class='subcategory-section'>
                        <span class='summary-label subcategory-label'>Tags:</span>
                        <span class='summary-content subcategory-content'>" . htmlspecialchars($row['tags'] ?? '') . "</span>
                    </div>
                    <div class='subcategory-section'>
                        <span class='summary-label subcategory-label'>Embedding:</span>
                        <span class='summary-content subcategory-content'>" . htmlspecialchars($row['native_vec'] ?? '') . "</span>
                    </div>
                    <div class='button-group' style='margin-top: 10px;'>
                        <button class='btn-base action-button edit' onclick='toggleEdit({$row['rowid']})'>Edit</button>
                        <button class='btn-base btn-danger' onclick=\"if(confirm('Are you sure you want to delete this memory summary?')) window.location.href='events-memories.php?tab=memory&delete_memory={$row['rowid']}'\">Delete</button>
                    </div>
                    <div class='mt-2'>
                        <span class='summary-label'>Packed Memory Content:</span>
                    </div>
                    <div class='memory-cell'>
                        <textarea readonly class='memory-content'>" . htmlspecialchars($row['packed_message'] ?? '') . "</textarea>
                    </div>
                </div>";
                
                $displayHtml .= "<form id='edit-form-{$row['rowid']}' class='edit-form' method='post' action='events-memories.php?tab=memory'>
                    <input type='hidden' name='rowid' value='{$row['rowid']}'>
                    <input type='hidden' name='save_memory_edit' value='1'>
                    <label>Summary:</label>
                    <textarea name='summary' class='edit-textarea form-control'>" . htmlspecialchars($row['summary'] ?? '') . "</textarea>
                    <label>Tags:</label>
                    <input type='text' name='tags' class='edit-input form-control' value='" . htmlspecialchars($row['tags'] ?? '') . "'>
                    <label>People:</label>
                    <input type='text' name='companions' class='edit-input form-control' value='" . htmlspecialchars($row['companions'] ?? '') . "'>
                    <div class='button-group' style='margin-top: 10px;'>
                        <button type='submit' class='btn-base action-button add-new'>Save</button>
                        <button type='button' class='btn-base btn-cancel' onclick='cancelEdit({$row['rowid']})'>Cancel</button>
                    </div>
                </form>";

                $processedRow = [
                    'RowID' => $row['rowid'],
                    '<a href="https://en.uesp.net/wiki/Lore:Calendar" target="_blank" style="color: yellow;">Tamrielic Time</a>' => !empty($row['gamets_truncated']) ? convert_gamets2skyrim_long_date2($row['gamets_truncated']) : '',
                    'ID' => $row['n'],
                    'Classifier' => $row['classifier'],
                    'Summary' => $displayHtml
                ];
                
                $processedResults[] = $processedRow;
            }
            
            // Add Memory Management buttons
            echo "<div class='memory-management-actions' style='margin: 15px 0;'>";
            echo "<button onclick=\"syncMemoriesConfirm()\" class='btn-base btn-primary' style='margin-right: 10px;'>🔄 Sync & Create Memory Summaries</button>";
            echo "<button onclick=\"deleteAllMemoriesConfirm()\" class='btn-base btn-danger' style='background-color: #dc2626; font-weight: bold;'>⚠️ Delete All Memory Summaries</button>";
            echo "<button onclick=\"fixCompanions()\" class='btn-base btn-primary' style='margin-right: 10px;'>🔄 Fix Companions field</button>";
            echo "</div>";
            
            // Add JavaScript functions for confirmations
            echo "<script>
            function syncMemoriesConfirm() {
                if (confirm('Will use tokens from your current AI connector. May take a few minutes to process. DO NOT REFRESH THE WEBPAGE!')) {
                    window.location.href = '" . $webRoot . "/ui/tests/vector-compact-chromadb.php';
                }
            }
            
            function fixCompanions() {
                if (confirm('Will NOT use tokens from your current AI connector. May take a few minutes to process. DO NOT REFRESH THE WEBPAGE!')) {
                    window.location.href = '" . $webRoot . "/ui/tests/fix_companins_field.php';
                }
            }

            function deleteAllMemoriesConfirm() {
                var userInput = prompt('THIS WILL DELETE ALL SUMMARIZED MEMORIES!\\n\\nThis action cannot be undone and will remove all AI memory summaries.\\n\\nTo confirm this dangerous operation, please type exactly: Delete');
                if (userInput === 'Delete') {
                    window.location.href = '" . $webRoot . "/ui/tests/vector-delete-memory_summary.php';
                } else if (userInput !== null) {
                    alert('Operation cancelled. You must type exactly \"Delete\" to confirm.');
                }
            }
            </script>";
            
            // Add the necessary styles
            echo "<style>
                .edit-form {
                    display: none;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 10px 0;
                    background-color: #2a2a2a;
                }
                .edit-textarea {
                    width: 100%;
                    min-height: 100px;
                    margin-bottom: 5px;
                    height: 300px;
                    background-color: #333;
                    color: #fff;
                    border: 1px solid #444;
                }
                .edit-input {
                    width: 100%;
                    margin-bottom: 5px;
                    background-color: #333;
                    color: #fff;
                    border: 1px solid #444;
                    padding: 5px;
                }
                .memory-content {
                    height: 100%;
                    min-height: 150px;
                    overflow-y: auto;
                    padding: 5px;
                    white-space: pre-wrap;
                    word-wrap: break-word;
                    border: 1px solid #444;
                    background-color: #333;
                    color: #fff;
                    width: 100%;
                }
                .summary-section {
                    max-width: 75vw;
                    margin-bottom: 8px;
                    padding: 5px;
                    border-bottom: 1px solid #444;
                }
                .subcategory-section {
                    margin-bottom: 6px;
                    padding: 3px 5px 3px 15px;
                    border-bottom: 1px dotted #555;
                    font-size: 0.85em;
                }
                .subcategory-label {
                    color: #aaa;
                    font-size: 0.9em;
                }
                .subcategory-content {
                    color: #ddd;
                    font-size: 0.9em;
                }
                .summary-label {
                    font-weight: bold;
                    margin-right: 5px;
                    color: #fff;
                }
                .summary-content {
                    color: #fff;
                }
            </style>";

            // Add the JavaScript for edit functionality
            echo "<script>
                function toggleEdit(rowid) {
                    const displayDiv = document.getElementById('display-' + rowid);
                    const editForm = document.getElementById('edit-form-' + rowid);
                    displayDiv.style.display = 'none';
                    editForm.style.display = 'block';
                }
                
                function cancelEdit(rowid) {
                    const displayDiv = document.getElementById('display-' + rowid);
                    const editForm = document.getElementById('edit-form-' + rowid);
                    displayDiv.style.display = 'block';
                    editForm.style.display = 'none';
                }
            </script>";

            if (!empty($processedResults)) {
                print_array_as_table($processedResults);
            } else {
                echo "<div class='table-container'>";
                echo "<p style='text-align: center; color: #6c757d; padding: 20px;'>No memory summaries found. Enable AUTO_CREATE_SUMMARYS to generate memory summaries.</p>";
                echo "</div>";
            }
            ?>
        </div>

        <!-- Active Quests Tab -->
        <div id="quests-tab" class="tab-content <?php echo $activeTab === 'quests' ? 'active' : ''; ?>">
            <?php
            $results = $db->fetchAll("SELECT name, id_quest, briefing, briefing2, data from quests");
            
            // Define column headers mapping
            $columnHeaders = [
                'name' => 'Name',
                'id_quest' => 'Quest ID',
                'briefing' => 'Briefing',
                'briefing2' => 'Briefing2',
                'data' => 'Data'
            ];
            
            $finalRow = [];
            foreach ($results as $row) {
                if (isset($finalRow[$row["id_quest"]]))
                    continue;
                else
                    $finalRow[$row["id_quest"]] = array_combine(
                        array_values($columnHeaders),
                        array_values($row)
                    );
            }
            
            echo "<p>Note: These quests are only known by your followers. We only track quests which you have active in your journal.</p>";

            if (!empty($finalRow)) {
                print_array_as_table(array_values($finalRow));
            } else {
                echo "<div class='table-container'>";
                echo "<p style='text-align: center; color: #6c757d; padding: 20px;'>No active quests found. Start some quests in-game to see them here!</p>";
                echo "</div>";
            }
            ?>
        </div>

        <!-- Book Log Tab -->
        <div id="books-tab" class="tab-content <?php echo $activeTab === 'books' ? 'active' : ''; ?>">
            <?php
            $results = $db->fetchAll("SELECT title, content, gamets, localts, ts, ROWID FROM books A ORDER BY gamets DESC, rowid DESC LIMIT 150 OFFSET 0");
            
            // Define column headers
            $columnHeaders = [
                'title' => 'Title',
                'content' => 'Content',
                'gamets' => '<a href="https://en.uesp.net/wiki/Lore:Calendar" target="_blank" style="color: yellow;">Tamrielic Time</a>',
                'localts' => 'Time (UTC)',
                'ts' => 'TS'
            ];

            // Map the results to format timestamps and apply headers
            $mappedResults = array_map(function($row) use ($columnHeaders) {
                $mappedRow = [];
                foreach ($row as $key => $value) {
                    if ($key === 'gamets' && !empty($value)) {
                        $value = convert_gamets2skyrim_long_date2($value);
                    }
                    else if ($key === 'localts' && !empty($value)) {
                        $dt = new DateTime("@$value");
                        $dt->setTimezone(new DateTimeZone('UTC'));
                        $value = $dt->format('d-m-Y H:i:s');
                    }
                    
                    // Map ROWID to lowercase rowid for delete functionality
                    if ($key === 'ROWID') {
                        $mappedRow['rowid'] = $value;
                    } else if (isset($columnHeaders[$key])) {
                        $mappedRow[$columnHeaders[$key]] = htmlspecialchars($value ?? '');
                    }
                }
                return $mappedRow;
            }, $results);
            
            // Set the table parameter for delete functionality
            $_GET["table"] = "books";

            echo "<p>Books that have been read and processed by the AI system.</p>";

            if (!empty($mappedResults)) {
                print_array_as_table($mappedResults);
            } else {
                echo "<div class='table-container'>";
                echo "<p style='text-align: center; color: #6c757d; padding: 20px;'>No books found. Read some books in-game to see them here!</p>";
                echo "</div>";
            }
            ?>
        </div>

        <!-- Background Life (Mapview) Tab -->
        <div id="mapview-tab" class="tab-content <?php echo $activeTab === 'mapview' ? 'active' : ''; ?>">
            <iframe src="<?php echo $webRoot; ?>/ui/mapview.php" style="width: 100%; height: 80vh; border: 1px solid #444; border-radius: 6px; background: #1a1a1a;"></iframe>
        </div>
    </div>
</div>

<script>
// Modal functionality
document.addEventListener("DOMContentLoaded", function() {
    var modal = document.getElementById("contentModal");
    var modalText = document.getElementById("modalText");
    var span = document.getElementsByClassName("close")[0];
    var copyBtn = document.getElementById("copyPromptBtn");

    // When the user clicks on <span> (x), close the modal
    span.onclick = function() {
        modal.style.display = "none";
        document.body.classList.remove("modal-open");
    };

    // When the user clicks anywhere outside of the modal, close it
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
            document.body.classList.remove("modal-open");
        }
    };

    // Copy button functionality
    copyBtn.onclick = function() {
        // Get the text content (not HTML)
        var textToCopy = modalText.innerText || modalText.textContent;
        
        // Use modern clipboard API
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(textToCopy).then(function() {
                // Show success feedback
                var originalText = copyBtn.innerHTML;
                copyBtn.innerHTML = '✅ Copied!';
                copyBtn.style.background = '#28a745';
                setTimeout(function() {
                    copyBtn.innerHTML = originalText;
                    copyBtn.style.background = '';
                }, 2000);
            }).catch(function(err) {
                console.error('Failed to copy: ', err);
                alert('Failed to copy to clipboard');
            });
        } else {
            // Fallback for older browsers
            var textArea = document.createElement("textarea");
            textArea.value = textToCopy;
            textArea.style.position = "fixed";
            textArea.style.left = "-999999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                var originalText = copyBtn.innerHTML;
                copyBtn.innerHTML = '✅ Copied!';
                copyBtn.style.background = '#28a745';
                setTimeout(function() {
                    copyBtn.innerHTML = originalText;
                    copyBtn.style.background = '';
                }, 2000);
            } catch (err) {
                console.error('Fallback copy failed: ', err);
                alert('Failed to copy to clipboard');
            }
            document.body.removeChild(textArea);
        }
    };

    // Add click handlers to all cell contents
    document.querySelectorAll(".view-contents-btn").forEach(function(element) {
        element.addEventListener("click", function() {
            var promptId = this.getAttribute("data-prompt-id");
            var promptDiv = document.getElementById(promptId);
            if (promptDiv) {
                modalText.innerHTML = promptDiv.innerHTML;
            } else {
                // Fallback to data-full-content for backwards compatibility
                modalText.innerHTML = this.getAttribute("data-full-content") || "Content not found";
            }
            modal.style.display = "block";
            document.body.classList.add("modal-open");
        });
    });
});

// Toggle content expand/collapse
function toggleContent(id) {
    var preview = document.getElementById(id + '_preview');
    var full = document.getElementById(id + '_full');
    var btn = event.target;
    
    if (full.style.display === 'none') {
        preview.style.display = 'none';
        full.style.display = 'block';
        btn.innerHTML = '▲ Collapse';
    } else {
        preview.style.display = 'block';
        full.style.display = 'none';
        btn.innerHTML = '▼ Expand';
    }
}

function switchTab(tabName) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => {
        content.classList.remove('active');
    });
    
    // Remove active class from all buttons
    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(button => {
        button.classList.remove('active');
    });
    
    // Show selected tab content
    document.getElementById(tabName + '-tab').classList.add('active');
    
    // Add active class to clicked button
    event.target.classList.add('active');
    
    // Update URL without page reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.pushState({}, '', url);
}
</script>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?> 