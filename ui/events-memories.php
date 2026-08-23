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

$TITLE = "Roleplay";
$BODY_CLASS = 'hub-page';

ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");
?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
    /* Override main container styles */
    main {
        padding-top: 0;
        padding-bottom: 24px;
        padding-left: 10px;
    }

    .container-fluid {
        padding: 0 10px 8px;
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

    .tab-content {
        display: none;
        background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        padding: 10px 12px 12px;
        border-radius: 8px;
        border-top-left-radius: 0;
        border: 1px solid #3a3a3a;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
    }

    .tab-content.active {
        display: block;
    }

    .tab-content.embed-tab {
        padding: 0;
        overflow: hidden;
        border: 0;
        background: transparent;
        box-shadow: none;
    }

    .embed-frame {
        width: 100%;
        min-height: 78vh;
        border: 1px solid #3a3a3a;
        border-radius: 8px;
        background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
    }

    /* Table Container Styles */
    .table-container {
        max-height: calc(100vh - 310px) !important;
        margin-top: 8px;
        width: 100%;
        overflow-x: auto;
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15),
                    inset 0 1px rgba(255, 255, 255, 0.03);
        padding: 8px;
    }

    .event-log-intro,
    .event-log-note {
        padding: 9px 12px;
        border-radius: 5px;
        margin: 0 0 6px;
        font-size: 0.88em;
        line-height: 1.4;
    }

    .event-log-intro {
        background: #2a2a2a;
        border-left: 4px solid rgb(242, 124, 17);
    }

    .event-log-note {
        background: #1a4d6d;
        border-left: 4px solid #3b82f6;
        color: #e0f2ff;
    }

    .event-log-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        margin: 8px 0 6px;
    }

    /* Table Styles */
    .table-container table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
        margin-bottom: 0;
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
        font-size: small;
    }

    /* Header Cells */
    .table-container th {
        padding: 12px 10px;
        font-weight: bold;
        text-align: left;
        vertical-align: top;
        color: rgb(242, 124, 17);
        background: rgba(26, 26, 26, 0.6);
        border-bottom: 2px solid rgba(242, 124, 17, 0.3);
        font-size: 0.95em;
    }
    
    th {
        padding: 12px;
        font-weight: bold;
        text-align: left;
        color: rgb(242, 124, 17);
        background: rgba(26, 26, 26, 0.6);
        border-bottom: 2px solid rgba(242, 124, 17, 0.3);
    }

    /* Data Cells */
    .table-container td {
        word-wrap: break-word;
        overflow-wrap: break-word;
        hyphens: auto;
        vertical-align: top;
        padding: 10px;
        line-height: 1.5;
        border-bottom: 1px solid rgba(74, 74, 74, 0.3);
        color: #d0d0d0;
    }
    
    td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid rgba(74, 74, 74, 0.3);
        color: #f8f9fa;
    }

    /* Row hover effect */
    .table-container tr:hover td {
        background: rgba(242, 124, 17, 0.05);
    }
    
    tr:hover td {
        background: rgba(242, 124, 17, 0.05);
    }

    /* Row Alternating Colors - removed for consistency with Oghma */

    /* Button Cell Alignment */
    td:has(button), td:has(.btn-base) {
        text-align: center;
    }

    /* Event Log Table - People Present column should be 3x wider */
    #eventlog-tab table th:nth-child(4),
    #eventlog-tab table td:nth-child(4) {
        min-width: 300px;
        width: 20%;
    }

    /* Relationship history rows: compact per-change presentation.
       Same palette and density as the CHIM home dashboard widget. */
    .relationship-change-cell {
        display: grid;
        gap: 5px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .relationship-change-entry {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: 8px;
        align-items: baseline;
        min-width: 0;
    }

    .relationship-change-delta {
        min-width: 3.1em;
        padding: 1px 6px;
        border-radius: 4px;
        font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
        font-size: 0.92em;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        text-align: center;
        white-space: nowrap;
    }

    /* The sign carries the meaning, so colour is reinforcement only. */
    .relationship-change-delta.is-up {
        color: #7ee08a;
        background: rgba(76, 175, 80, 0.14);
        border: 1px solid rgba(126, 224, 138, 0.35);
    }

    .relationship-change-delta.is-down {
        color: #ff8a80;
        background: rgba(244, 67, 54, 0.14);
        border: 1px solid rgba(255, 138, 128, 0.35);
    }

    .relationship-change-delta.is-type {
        color: #f2bd7f;
        background: rgba(242, 124, 17, 0.14);
        border: 1px solid rgba(242, 189, 127, 0.35);
        font-family: inherit;
        font-size: 0.72em;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .relationship-change-entry-body {
        display: block;
        min-width: 0;
    }

    .relationship-change-reason {
        display: block;
        color: #e2e2e2;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .relationship-change-entry-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 3px 6px;
        margin-top: 2px;
        font-size: 0.85em;
        color: #929292;
    }

    .relationship-change-target {
        color: #bdbdbd;
        overflow-wrap: anywhere;
    }

    .relationship-change-arrow {
        color: #6f6f6f;
    }

    .relationship-change-tier {
        padding: 0 4px;
        border: 1px solid #4a4033;
        border-radius: 3px;
        color: #d9c39a;
    }

    .relationship-change-sr {
        position: absolute;
        width: 1px;
        height: 1px;
        margin: -1px;
        padding: 0;
        overflow: hidden;
        clip: rect(0 0 0 0);
        clip-path: inset(50%);
        white-space: nowrap;
        border: 0;
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

        #eventlog-tab table th:nth-child(4),
        #eventlog-tab table td:nth-child(4) {
            min-width: 150px;
            width: auto;
        }

        .embed-frame {
            min-height: 75vh;
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
        background: linear-gradient(135deg, rgba(42, 42, 42, 0.98), rgba(34, 34, 34, 0.98));
        margin: 3% auto;
        padding: 20px;
        border: 2px solid rgba(242, 124, 17, 0.5);
        width: 90%;
        max-width: 1600px;
        max-height: 90vh;
        overflow-y: auto;
        border-radius: 10px;
        color: #fff;
        position: relative;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5), inset 0 1px rgba(255, 255, 255, 0.03);
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

    /* Checkbox column styling */
    th:has(#selectAllCheckbox),
    td:has(.event-checkbox) {
        text-align: center !important;
        width: 40px !important;
        padding: 8px !important;
    }
</style>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/hub-navigation.css?v=<?php echo filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'hub-navigation.css'); ?>">
<?php

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/navbar.php");

require_once(LIB_PATH .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once(LIB_PATH .DIRECTORY_SEPARATOR."misc_ui_functions.php");
require_once(LIB_PATH .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once(LIB_PATH .DIRECTORY_SEPARATOR."eventlog_helper.php");

// Include game timestamp utilities
require_once(dirname(__DIR__).DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."utils_game_timestamp.php");

$db = new sql();

$eventLogLimit = isset($_GET["limit"]) ? intval($_GET["limit"]) : 100;
$eventLogPage = isset($_GET["page"]) ? max(1, intval($_GET["page"])) : 1;
$eventLogAutoRefresh = isset($_GET["autorefresh"]) && $_GET["autorefresh"];
$eventLogHiddenTypes = chimGetPersistedEventLogHiddenTypes($db);

if (isset($_GET['hide_event_type'])) {
    $typeToHide = trim((string)$_GET['hide_event_type']);
    if ($typeToHide !== '') {
        $eventLogHiddenTypes[] = $typeToHide;
        chimSavePersistedEventLogHiddenTypes($db, $eventLogHiddenTypes);
    }

    $redirectParams = [
        'tab' => 'eventlog',
        'page' => 1,
        'limit' => $eventLogLimit,
    ];
    if ($eventLogAutoRefresh) {
        $redirectParams['autorefresh'] = 'true';
    }
    header("Location: events-memories.php?" . http_build_query($redirectParams));
    exit;
}

if (isset($_GET['show_event_type'])) {
    $typeToShow = trim((string)$_GET['show_event_type']);
    $eventLogHiddenTypes = array_values(array_filter($eventLogHiddenTypes, function($type) use ($typeToShow) {
        return $type !== $typeToShow;
    }));
    chimSavePersistedEventLogHiddenTypes($db, $eventLogHiddenTypes);

    $redirectParams = [
        'tab' => 'eventlog',
        'page' => 1,
        'limit' => $eventLogLimit,
    ];
    if ($eventLogAutoRefresh) {
        $redirectParams['autorefresh'] = 'true';
    }
    header("Location: events-memories.php?" . http_build_query($redirectParams));
    exit;
}

if (isset($_GET['clear_hidden_event_types']) && $_GET['clear_hidden_event_types']) {
    chimSavePersistedEventLogHiddenTypes($db, []);
    $eventLogHiddenTypes = [];

    $redirectParams = [
        'tab' => 'eventlog',
        'page' => 1,
        'limit' => $eventLogLimit,
    ];
    if ($eventLogAutoRefresh) {
        $redirectParams['autorefresh'] = 'true';
    }
    header("Location: events-memories.php?" . http_build_query($redirectParams));
    exit;
}

$eventLogHiddenTypes = chimNormalizeEventLogTypeList($eventLogHiddenTypes);
$eventLogTypeOptions = chimGetVisibleEventLogTypes($db, $eventLogHiddenTypes);
$eventLogVisibleWhereClause = chimBuildVisibleEventLogWhereClause($db, '', $eventLogHiddenTypes);

$eventLogBaseParams = [
    'tab' => 'eventlog',
    'limit' => $eventLogLimit,
];
if ($eventLogAutoRefresh) {
    $eventLogBaseParams['autorefresh'] = 'true';
}

$eventLogCurrentPageParams = $eventLogBaseParams;
$eventLogCurrentPageParams['page'] = $eventLogPage;
$eventLogCurrentPageUrl = 'events-memories.php?' . http_build_query($eventLogCurrentPageParams);

// Handle actions
if (isset($_GET["clean"]) && $_GET["clean"]) {
    $db->delete("responselog", "sent=1");
}
if (isset($_GET["reset"]) && $_GET["reset"]) {
    $db->delete("eventlog", "true");
    header("Location: events-memories.php?tab=eventlog");
}
if (isset($_GET["cleanlog"]) && $_GET["cleanlog"]) {
    $db->delete("log", "true");
    header("Location: events-memories.php?tab=responselog");
}

// Handle delete_last for event log
if (isset($_GET['delete_last'])) {
    $delCount = (int)$_GET['delete_last'];
    if (in_array($delCount, [5, 10, 20, 50, 100], true)) {
        $deleteResult = chimDeleteLatestVisibleEventLogRows($db, $delCount, '', $eventLogHiddenTypes);
        $deletedCount = intval($deleteResult['deleted_count'] ?? 0);
        $redirectParams = $eventLogCurrentPageParams;
        $redirectParams['deleted'] = $deletedCount;
        header("Location: events-memories.php?" . http_build_query($redirectParams));
        exit;
    }
}

// Handle bulk delete of selected events
if (isset($_POST['delete_selected']) && !empty($_POST['rowids'])) {
    // Debug logging
    Logger::info("Bulk delete triggered. POST data: " . json_encode($_POST));
    
    $rowids = $_POST['rowids'];
    if (is_array($rowids)) {
        Logger::info("Rowids received as array: " . json_encode($rowids));
        
        // Sanitize and validate row IDs
        $sanitizedRowids = array_map('intval', $rowids);
        $sanitizedRowids = array_filter($sanitizedRowids, function($id) { return $id > 0; });
        
        Logger::info("Sanitized rowids: " . json_encode($sanitizedRowids));
        
        if (count($sanitizedRowids) > 0) {
            $rowidsStr = implode(',', $sanitizedRowids);
            $existingRows = $db->fetchAll("SELECT rowid FROM eventlog WHERE rowid IN ($rowidsStr)");
            $existingRowids = [];
            foreach ($existingRows as $existingRow) {
                $existingRowid = intval($existingRow['rowid'] ?? 0);
                if ($existingRowid > 0) {
                    $existingRowids[] = $existingRowid;
                }
            }

            Logger::info("Existing rowids before delete: " . json_encode($existingRowids));

            if (count($existingRowids) > 0) {
                $existingRowidsStr = implode(',', $existingRowids);
                $query = "DELETE FROM eventlog WHERE rowid IN ($existingRowidsStr)";
                Logger::info("Executing delete query: $query");
                $db->query($query);
            }

            $deletedCount = count($existingRowids);
            Logger::info("Bulk delete executed: $deletedCount events deleted.");
            
            $redirectParams = $eventLogCurrentPageParams;
            $redirectParams['deleted'] = $deletedCount;
            header("Location: events-memories.php?" . http_build_query($redirectParams));
            exit;
        } else {
            Logger::warn("Bulk delete attempted but no valid rowids after sanitization");
            $redirectParams = $eventLogCurrentPageParams;
            $redirectParams['error'] = 'invalid_ids';
            header("Location: events-memories.php?" . http_build_query($redirectParams));
            exit;
        }
    } else {
        Logger::warn("Bulk delete attempted but rowids is not an array: " . json_encode($rowids));
        $redirectParams = $eventLogCurrentPageParams;
        $redirectParams['error'] = 'invalid_format';
        header("Location: events-memories.php?" . http_build_query($redirectParams));
        exit;
    }
} else if (isset($_POST['delete_selected'])) {
    Logger::warn("Bulk delete triggered but rowids empty or not set. POST: " . json_encode($_POST));
}

// Handle memory summary save edits
if (isset($_POST['save_memory_edit'])) {
    $rowid = isset($_POST['rowid']) ? intval($_POST['rowid']) : 0;
    $summary = isset($_POST['summary']) ? $_POST['summary'] : '';
    $tags = isset($_POST['tags']) ? $_POST['tags'] : '';
    $companions = isset($_POST['companions']) ? $_POST['companions'] : '';
    
    if ($rowid > 0) {
        $db->update(
            'memory_summary',
            "summary = '" . $db->escape($summary) . "', 
             tags = '" . $db->escape($tags) . "',
             companions = '" . $db->escape($companions) . "'",
            "rowid = " . $rowid
        );
    }
    
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
$validTabs = ['eventlog', 'responselog', 'adventure', 'memory', 'diaries', 'books', 'soulgaze', 'quests', 'questgen', 'backgroundlife'];
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'eventlog';
}

$backgroundLifeSubTab = strtolower(trim((string) ($_GET['bgl_tab'] ?? 'background')));
if (!in_array($backgroundLifeSubTab, ['background', 'history', 'rumors'], true)) {
    $backgroundLifeSubTab = 'background';
}

if ($activeTab === 'responselog') {
    $redirectParams = [];
    if (isset($_GET['page'])) {
        $redirectParams['page'] = max(1, intval($_GET['page']));
    }
    if (isset($_GET['limit'])) {
        $redirectParams['limit'] = max(10, intval($_GET['limit']));
    }

    $redirectUrl = 'ai-response.php';
    if (!empty($redirectParams)) {
        $redirectUrl .= '?' . http_build_query($redirectParams);
    }
    header('Location: ' . $redirectUrl);
    exit;
}

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

    <div class="tab-container">
        <?php
        $eventsMemoriesActiveTab = $activeTab;
        include(__DIR__ . DIRECTORY_SEPARATOR . 'tmpl' . DIRECTORY_SEPARATOR . 'events_memories_navigation.php');
        ?>

        <!-- Event Log Tab -->
        <div id="eventlog-tab" class="tab-content <?php echo $activeTab === 'eventlog' ? 'active' : ''; ?>">
            <?php
            // Add subtitle description
            echo "<div class='event-log-intro'>";
            echo "<span style='color: rgb(242, 124, 17); font-weight: bold;'>📝 Events:</span> ";
            echo "<span style='color: #f8f9fa;'>Combined timeline of in-game events and relationship changes. Relationship history remains stored separately and is not copied into the Event Log or AI event context.</span>";
            echo "</div>";

            // Keep context guidance directly below the description so both scan as one compact introduction.
            echo "<div class='event-log-note'>";
            echo "ℹ️ <strong>Note:</strong> Not all events will show up in AI context. Any blacklist settings will not be used for context. This is a raw log of some of the more relevant events.";
            echo "</div>";
            
            // Show success message if events were deleted
            if (isset($_GET['deleted'])) {
                $deletedCount = intval($_GET['deleted']);
                echo "<div style='background: #28a745; color: white; padding: 10px; border-radius: 5px; margin: 10px 0;'>Successfully deleted $deletedCount event(s)!</div>";
            }
            
            // Event Log title with integrated monitor toggle and delete buttons
            $isAutoRefresh = $eventLogAutoRefresh;
            $eventLogUrlBuilder = function(array $overrides = []) use ($eventLogBaseParams) {
                return 'events-memories.php?' . http_build_query(array_merge($eventLogBaseParams, $overrides));
            };
            echo "<div class='event-log-toolbar'>";
            
            echo "<button id='live-toggle-btn-eventlog' onclick=\"toggleAutoRefreshEventLog()\" class='btn-base " . ($isAutoRefresh ? "btn-secondary" : "btn-primary") . "' style='padding: 8px 12px; font-size: 0.9em;' title='Toggle live monitoring'>";
            echo $isAutoRefresh ? "⏸️ Stop Live" : "Auto Refresh";
            echo "</button>";
            
            if ($isAutoRefresh) {
                echo "<span id='live-indicator-eventlog' style='margin-left: 10px; color: #28a745; font-weight: bold; font-size: 0.9em;'>🔴 LIVE</span>";
            } else {
                echo "<span id='live-indicator-eventlog' style='margin-left: 10px; color: #28a745; font-weight: bold; font-size: 0.9em; display: none;'>🔴 LIVE</span>";
            }
            
            // Add delete buttons inline
            echo "<div style='margin-left: auto; display: flex; gap: 5px; flex-wrap: wrap; align-items: center;'>";
            echo "<button id='deleteSelectedBtn' onclick='deleteSelectedEvents()' class='btn-base btn-danger' style='padding: 6px 10px; font-size: 0.8em; display: none;'>🗑️ Delete Selected (<span id='selectedCount'>0</span>)</button>";
            echo "<div style='display: inline-flex; gap: 5px; align-items: center; flex-wrap: nowrap; white-space: nowrap;'>";
            echo "<select id='delete-action-select' style='padding: 5px 8px; border-radius: 4px; border: 1px solid #666; background: #2a2a2a; color: #f8f9fa; min-width: 170px; font-size: 0.8em;'>";
            echo "<option value='5' selected>Delete Latest 5</option>";
            echo "<option value='10'>Delete Latest 10</option>";
            echo "<option value='20'>Delete Latest 20</option>";
            echo "<option value='50'>Delete Latest 50</option>";
            echo "<option value='100'>Delete Latest 100</option>";
            echo "<option value='all'>Delete ALL</option>";
            echo "</select>";
            echo "<button onclick='handleDeletePresetAction()' class='btn-base btn-danger' style='padding: 6px 10px; font-size: 0.8em;'>Delete</button>";
            echo "</div>";
            echo "</div>";
            echo "</div>";
            
            $limit = $eventLogLimit;
            $page = $eventLogPage;
            $offset = ($page - 1) * $limit;
            
            $sourceWindow = $limit + $offset;
            $eventResults = $db->fetchAll(
                "SELECT type, data, people, gamets, localts, ts, rowid
                 FROM eventlog a
                 WHERE $eventLogVisibleWhereClause
                 ORDER BY gamets DESC, ts DESC, localts DESC, rowid DESC
                 LIMIT $sourceWindow"
            );
            $showRelationshipHistory = !in_array('relationship', $eventLogHiddenTypes, true);
            $relationshipResults = $showRelationshipHistory
                ? chimFetchRelationshipHistoryTimelineRows($db, $sourceWindow, 0, 0, 0, true)
                : [];
            $results = chimMergeTimelineRows(
                $eventResults,
                $relationshipResults,
                $limit,
                $offset
            );
            
            $columnHeaders = [
                'type' => 'Event',
                'data' => 'Events',
                'gamets' => '<a href="https://en.uesp.net/wiki/Lore:Calendar" target="_blank" style="color: yellow;">Tamrielic Time</a>',
                'localts' => 'Time (UTC)',
            ];
            
            $mappedResults = array_map(function ($row) use ($columnHeaders) {
                $mappedRow = [];
                $isRelationshipHistory = ($row['source'] ?? '') === 'relationship_history';
                // Add checkbox column first (PostgreSQL returns rowid in lowercase)
                $mappedRow['☑'] = $isRelationshipHistory
                    ? ''
                    : '<input type="checkbox" class="event-checkbox" data-rowid="' . htmlspecialchars($row['rowid'] ?? '') . '" style="cursor: pointer; width: 18px; height: 18px;">';
                
                foreach ($row as $key => $value) {
                    if ($key === 'changes') {
                        // Structured relationship details back the compact Events cell below;
                        // they are not a column, and they are not a scalar to escape.
                        continue;
                    }
                    if ($key === 'data' && function_exists('chimRenderNarratorRoleplayText')) {
                        $value = chimRenderNarratorRoleplayText($value);
                    }
                    if ($key === 'gamets' && !empty($value)) {
                        $value = convert_gamets2skyrim_long_date2($value);
                    }
                    else if ($key === 'localts' && !empty($value)) {
                        $dt = new DateTime("@$value");
                        $dt->setTimezone(new DateTimeZone('UTC'));
                        $value = $dt->format('d-m-Y H:i:s');
                    }
                    
                    // Relationship history gets the compact per-change presentation in the
                    // web view only; the stored prose still backs the API and AI consumers.
                    if ($key === 'data' && $isRelationshipHistory) {
                        $value = chimRenderRelationshipChangeCellHtml($row['changes'] ?? [], (string)$value);
                    }
                    // Special handling for chat events
                    else if ($row['type'] === 'chat' && ($key === 'data' || $key === 'type')) {
                        $value = '<span style="color:rgb(255, 255, 255);">' . htmlspecialchars($value ?? '') . '</span>';
                    } else {
                        $value = htmlspecialchars($value ?? '');
                    }
                    
                    if ($key === 'data') {
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
                        if (function_exists('chimRenderNarratorRoleplayText')) {
                            $peoplePresent = chimRenderNarratorRoleplayText($peoplePresent);
                        }
                        $mappedRow['People Present'] = htmlspecialchars($peoplePresent);
                    } else if ($key === 'rowid') {
                        $mappedRow['Record'] = $isRelationshipHistory
                            ? 'Relationship #' . intval($row['relationship_history_id'] ?? 0)
                            : '<a class="icon-link" href="#" style="color: red !important;" onclick="deleteRowAndRefresh(\'eventlog\', ' . intval($value) . '); return false;">'
                                . intval($value) . ' <i class="bi-trash" style="color: red !important;"></i></a>';
                    } else if (in_array($key, ['people', 'ts', 'source', 'relationship_history_id'], true)) {
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
            $countQuery = "SELECT COUNT(*) as total FROM eventlog WHERE $eventLogVisibleWhereClause";
            $countResult = $db->fetchAll($countQuery);
            $totalRecords = intval($countResult[0]['total'] ?? 0);
            if ($showRelationshipHistory) {
                $totalRecords += chimCountRelationshipHistoryTimelineRows($db);
            }
            $totalPages = ceil($totalRecords / $limit);
            
            echo "<div class='pagination-buttons' style='margin: 6px 0; display: flex; gap: 8px; align-items: center; flex-wrap: wrap;'>";
            
            if ($page > 1) {
                echo "<button onclick=\"window.location.href='" . htmlspecialchars($eventLogUrlBuilder(['page' => $prevPage]), ENT_QUOTES) . "'\" class='btn-base btn-primary'>Previous</button> ";
            }
            
            // Smart pagination: show current page and surrounding pages
            if ($totalPages <= 10) {
                // Show all pages if 10 or fewer
                for ($i = 1; $i <= $totalPages; $i++) {
                    if ($i == $page) {
                        echo "<button onclick=\"window.location.href='" . htmlspecialchars($eventLogUrlBuilder(['page' => $i]), ENT_QUOTES) . "'\" class='btn-base btn-secondary' style='background-color: #6c757d;'>$i</button> ";
                    } else {
                        echo "<button onclick=\"window.location.href='" . htmlspecialchars($eventLogUrlBuilder(['page' => $i]), ENT_QUOTES) . "'\" class='btn-base btn-primary'>$i</button> ";
                    }
                }
            } else {
                // Always show first page
                if ($page == 1) {
                    echo "<button onclick=\"window.location.href='" . htmlspecialchars($eventLogUrlBuilder(['page' => 1]), ENT_QUOTES) . "'\" class='btn-base btn-secondary' style='background-color: #6c757d;'>1</button> ";
                } else {
                    echo "<button onclick=\"window.location.href='" . htmlspecialchars($eventLogUrlBuilder(['page' => 1]), ENT_QUOTES) . "'\" class='btn-base btn-primary'>1</button> ";
                }
                
                // Show ellipsis if current page is far from start
                if ($page > 4) {
                    echo "<span style='margin: 0 5px; color: #fff;'>...</span>";
                }
                
                // Show pages around current page
                $start = max(2, $page - 2);
                $end = min($totalPages - 1, $page + 2);
                
                for ($i = $start; $i <= $end; $i++) {
                    if ($i == $page) {
                        echo "<button onclick=\"window.location.href='" . htmlspecialchars($eventLogUrlBuilder(['page' => $i]), ENT_QUOTES) . "'\" class='btn-base btn-secondary' style='background-color: #6c757d;'>$i</button> ";
                    } else {
                        echo "<button onclick=\"window.location.href='" . htmlspecialchars($eventLogUrlBuilder(['page' => $i]), ENT_QUOTES) . "'\" class='btn-base btn-primary'>$i</button> ";
                    }
                }
                
                // Show ellipsis if current page is far from end
                if ($page < $totalPages - 3) {
                    echo "<span style='margin: 0 5px; color: #fff;'>...</span>";
                }
                
                // Always show last page
                if ($page == $totalPages) {
                    echo "<button onclick=\"window.location.href='" . htmlspecialchars($eventLogUrlBuilder(['page' => $totalPages]), ENT_QUOTES) . "'\" class='btn-base btn-secondary' style='background-color: #6c757d;'>$totalPages</button> ";
                } else {
                    echo "<button onclick=\"window.location.href='" . htmlspecialchars($eventLogUrlBuilder(['page' => $totalPages]), ENT_QUOTES) . "'\" class='btn-base btn-primary'>$totalPages</button> ";
                }
            }
            
            if ($page < $totalPages) {
                echo "<button onclick=\"window.location.href='" . htmlspecialchars($eventLogUrlBuilder(['page' => $nextPage]), ENT_QUOTES) . "'\" class='btn-base btn-primary'>Next</button>";
            }

            echo "<span style='margin-left: 8px; color: #9fb1c9; font-size: 0.85em;'>Hide:</span>";
            echo "<select id='event-type-filter' onchange='applyEventLogTypeFilter(this.value)' style='padding: 5px 8px; border-radius: 4px; border: 1px solid #666; background: #2a2a2a; color: #f8f9fa; min-width: 150px; max-width: 180px; font-size: 0.8em;'>";
            echo "<option value=''>Hide event...</option>";
            foreach ($eventLogTypeOptions as $eventLogTypeOption) {
                $eventTypeValue = (string)($eventLogTypeOption['type'] ?? '');
                if ($eventTypeValue === '') {
                    continue;
                }
                echo "<option value='" . htmlspecialchars($eventTypeValue, ENT_QUOTES) . "'>" . htmlspecialchars($eventTypeValue) . "</option>";
            }
            echo "</select>";

            if (!empty($eventLogHiddenTypes)) {
                foreach ($eventLogHiddenTypes as $hiddenEventType) {
                    echo "<button type='button' onclick='removeHiddenEventType(" . json_encode($hiddenEventType) . ")' class='btn-base' style='padding: 4px 8px; font-size: 0.75em; background: #3a3a3a; color: #f8f9fa; border-color: #555;'>" . htmlspecialchars($hiddenEventType) . " ×</button>";
                }
            }
            
            echo "</div>";
            
            echo "<script>
            function deleteAllEventsConfirm() {
                var userInput = prompt('THIS WILL DELETE ALL EVENTS IN THE EVENT LOG!\\n\\nEvents are used for AI context. This action cannot be undone.\\n\\nTo confirm this dangerous operation, please type exactly: Delete');
                if (userInput === 'Delete') {
                    window.location.href = " . json_encode($eventLogUrlBuilder(['page' => 1, 'reset' => 'true'])) . ";
                } else if (userInput !== null) {
                    alert('Operation cancelled. You must type exactly \"Delete\" to confirm.');
                }
            }
            </script>";
            
            echo "<div id='eventlog-table-container'>";
            print_array_as_table($mappedResults);
            echo "</div>";
            
            // Smart AJAX auto-refresh script
            echo "<script>
            let autoRefreshIntervalEventLog = null;
            let isLiveModeEventLog = " . ($isAutoRefresh ? 'true' : 'false') . ";
            let lastRowIdEventLog = 0;
            let lastRelationshipHistoryIdEventLog = " . chimGetLatestRelationshipHistoryId($db) . ";
            let totalNewEventsEventLog = 0;
            const currentPageEventLog = $page;
            const currentLimitEventLog = $limit;
            const headersEventLog = " . json_encode($columnHeaders) . ";
            const eventLogApiBaseUrl = " . json_encode($webRoot . "/ui/api/eventlog.php") . ";

            function buildEventLogPageUrl(overrides = {}) {
                const params = new URLSearchParams();
                params.set('tab', 'eventlog');
                params.set('page', String(overrides.page !== undefined ? overrides.page : currentPageEventLog));
                params.set('limit', String(overrides.limit !== undefined ? overrides.limit : currentLimitEventLog));

                const autoRefreshValue = overrides.autorefresh !== undefined ? overrides.autorefresh : isLiveModeEventLog;
                if (autoRefreshValue) {
                    params.set('autorefresh', 'true');
                }

                if (overrides.deleteLast !== undefined) {
                    params.set('delete_last', String(overrides.deleteLast));
                }

                if (overrides.reset) {
                    params.set('reset', 'true');
                }

                if (overrides.hideEventType) {
                    params.set('hide_event_type', overrides.hideEventType);
                }

                if (overrides.showEventType) {
                    params.set('show_event_type', overrides.showEventType);
                }

                if (overrides.clearHiddenEventTypes) {
                    params.set('clear_hidden_event_types', '1');
                }

                return 'events-memories.php?' + params.toString();
            }

            window.buildEventLogPageUrl = buildEventLogPageUrl;
            window.chimEventLogState = {
                currentPage: currentPageEventLog,
                currentLimit: currentLimitEventLog
            };
            
            function getLastRowIdEventLog() {
                const table = document.querySelector('#eventlog-table-container table');
                if (!table) return 0;
                
                const rows = table.querySelectorAll('tr');
                let maxRowId = 0;
                
                rows.forEach(row => {
                    const checkbox = row.querySelector('.event-checkbox');
                    if (checkbox) {
                        const rowId = parseInt(checkbox.getAttribute('data-rowid'));
                        if (!isNaN(rowId) && rowId > maxRowId) {
                            maxRowId = rowId;
                        }
                    }
                });
                
                return maxRowId;
            }
            
            function updateEventTableEventLog() {
                if (!isLiveModeEventLog) return;
                
                const liveIndicator = document.getElementById('live-indicator-eventlog');
                if (liveIndicator) {
                    liveIndicator.style.opacity = '0.5';
                }
                
                const sinceRowId = lastRowIdEventLog;

                const apiParams = new URLSearchParams();
                apiParams.set('since_rowid', String(sinceRowId));
                apiParams.set('since_relationship_id', String(lastRelationshipHistoryIdEventLog));
                apiParams.set('use_saved_filters', '1');

                fetch(eventLogApiBaseUrl + '?' + apiParams.toString())
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data.length > 0) {
                            const table = document.querySelector('#eventlog-table-container table');
                            if (!table) return;
                            
                            const tbody = table.querySelector('tbody') || table;
                            const headerRow = tbody.querySelector('tr:first-child');
                            
                            data.data.reverse().forEach(row => {
                                const newRow = document.createElement('tr');
                                newRow.style.backgroundColor = '#2d5a2d';
                                
                                const isRelationshipHistory = String(row['ROWID'] || '').startsWith('relationship:');

                                // Relationship history is read-only here; deleting events must not alter relationship state.
                                const checkboxTd = document.createElement('td');
                                checkboxTd.innerHTML = isRelationshipHistory
                                    ? ''
                                    : '<input type=\"checkbox\" class=\"event-checkbox\" data-rowid=\"' + (row['ROWID'] || '') + '\" style=\"cursor: pointer; width: 18px; height: 18px;\" onclick=\"updateDeleteButton()\">';
                                newRow.appendChild(checkboxTd);
                                
                                // Add data cells
                                const td1 = document.createElement('td');
                                td1.innerHTML = row['Event'] || '';
                                newRow.appendChild(td1);
                                
                                const td2 = document.createElement('td');
                                td2.innerHTML = row['Events'] || '';
                                newRow.appendChild(td2);
                                
                                // People Present column
                                const td3 = document.createElement('td');
                                td3.textContent = row['People Present'] || '';
                                newRow.appendChild(td3);
                                
                                const td4 = document.createElement('td');
                                td4.innerHTML = row[headersEventLog['gamets']] || '';
                                newRow.appendChild(td4);
                                
                                const td5 = document.createElement('td');
                                td5.innerHTML = row['Time (UTC)'] || '';
                                newRow.appendChild(td5);
                                
                                const td6 = document.createElement('td');
                                const rowId = row['ROWID'] || '';
                                td6.innerHTML = isRelationshipHistory
                                    ? 'Relationship #' + String(rowId).split(':').pop()
                                    : '<a class=\"icon-link\" href=\"#\" style=\"color: red !important;\" onclick=\"deleteRowAndRefresh(\'eventlog\', ' + JSON.stringify(rowId) + '); return false;\">' + rowId + ' <i class=\"bi-trash\" style=\"color: red !important;\"></i></a>';
                                newRow.appendChild(td6);
                                
                                if (headerRow && headerRow.nextSibling) {
                                    tbody.insertBefore(newRow, headerRow.nextSibling);
                                } else {
                                    tbody.appendChild(newRow);
                                }
                                
                                const rowIdNum = parseInt(row['ROWID']);
                                if (!isNaN(rowIdNum) && rowIdNum > lastRowIdEventLog) {
                                    lastRowIdEventLog = rowIdNum;
                                }
                                
                                setTimeout(() => {
                                    newRow.style.transition = 'background-color 1s';
                                    newRow.style.backgroundColor = '';
                                }, 3000);
                            });
                            
                            totalNewEventsEventLog += data.new_count;
                        }

                        const latestRelationshipId = Number(data.latest_relationship_id || 0);
                        if (Number.isFinite(latestRelationshipId) && latestRelationshipId > lastRelationshipHistoryIdEventLog) {
                            lastRelationshipHistoryIdEventLog = latestRelationshipId;
                        }
                        
                        if (liveIndicator) {
                            liveIndicator.style.opacity = '1';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching eventlog:', error);
                        const liveIndicator = document.getElementById('live-indicator-eventlog');
                        if (liveIndicator) {
                            liveIndicator.style.opacity = '1';
                        }
                    });
            }
            
            function toggleAutoRefreshEventLog() {
                isLiveModeEventLog = !isLiveModeEventLog;
                
                const btn = document.getElementById('live-toggle-btn-eventlog');
                const indicator = document.getElementById('live-indicator-eventlog');
                
                if (isLiveModeEventLog) {
                    // If not on page 1, navigate to page 1 with autorefresh enabled
                    if (currentPageEventLog !== 1) {
                        window.location.href = buildEventLogPageUrl({ page: 1, autorefresh: true });
                        return;
                    }
                    
                    btn.textContent = '⏸️ Stop Live';
                    btn.className = 'btn-base btn-secondary';
                    btn.style.padding = '8px 12px';
                    btn.style.fontSize = '0.9em';
                    
                    if (indicator) indicator.style.display = 'inline';
                    
                    lastRowIdEventLog = getLastRowIdEventLog();
                    totalNewEventsEventLog = 0;
                    
                    autoRefreshIntervalEventLog = setInterval(updateEventTableEventLog, 5000);
                } else {
                    btn.textContent = 'Auto Refresh';
                    btn.className = 'btn-base btn-primary';
                    btn.style.padding = '8px 12px';
                    btn.style.fontSize = '0.9em';
                    
                    if (indicator) indicator.style.display = 'none';
                    
                    if (autoRefreshIntervalEventLog) {
                        clearInterval(autoRefreshIntervalEventLog);
                        autoRefreshIntervalEventLog = null;
                    }
                }
            }
            
            if (isLiveModeEventLog) {
                lastRowIdEventLog = getLastRowIdEventLog();
                autoRefreshIntervalEventLog = setInterval(updateEventTableEventLog, 5000);
            }
            </script>";
            ?>
        </div>

        <!-- Response Log Tab -->
        <div id="responselog-tab" class="tab-content">
            <div style="background: #2a2a2a; border-left: 4px solid rgb(242, 124, 17); padding: 12px 15px; border-radius: 5px; margin: 15px 0; font-size: 0.9em;">
                <span style="color: rgb(242, 124, 17); font-weight: bold;">AI Responses:</span>
                <span style="color: #f8f9fa;">The AI response log now lives on the dedicated AI Responses page.</span>
                <a class="btn-base btn-primary" style="margin-left: 10px; padding: 6px 10px; font-size: 0.85em;" href="ai-response.php">Open AI Responses</a>
            </div>
        </div>

        <?php if (false): ?>
        <div id="responselog-tab" class="tab-content <?php echo $activeTab === 'responselog' ? 'active' : ''; ?>">
            <?php
            // Add subtitle description
            echo "<div style='background: #2a2a2a; border-left: 4px solid rgb(242, 124, 17); padding: 12px 15px; border-radius: 5px; margin: 15px 0; font-size: 0.9em;'>";
            echo "<span style='color: rgb(242, 124, 17); font-weight: bold;'>💬 AI Responses:</span> ";
            echo "<span style='color: #f8f9fa;'>Complete log of all AI-generated responses including the full context (prompts, events, memories) sent to the LLM. Use this to debug conversations and understand what information the AI had access to.</span>";
            echo "</div>";
            
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
                        $promptPayload = null;
                        if (isset($decoded['response_full']) && is_array($decoded['response_full'])) {
                            $promptPayload = $decoded['response_full'];
                        } elseif (isset($decoded['full']) && is_array($decoded['full'])) {
                            $promptPayload = $decoded['full'];
                        }

                        if (is_array($promptPayload) && isset($promptPayload['messages']) && is_array($promptPayload['messages'])) {
                            foreach ($promptPayload['messages'] as $msg) {
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

            if (!function_exists('normalizeResponseLogText')) {
                function normalizeResponseLogText($value) {
                    if ($value === null) {
                        return '';
                    }

                    if (!is_string($value)) {
                        if (is_array($value) || is_object($value)) {
                            $value = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        } else {
                            $value = (string)$value;
                        }
                    }

                    $value = str_replace(["<br />", "<br>", "<br/>"], "\n", $value);
                    $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $value = str_replace(['\/', '\r\n', '\n', '\r'], ['/', "\n", "\n", "\n"], $value);

                    return trim($value);
                }
            }

            if (!function_exists('normalizeDisplayedAiResponse')) {
                function normalizeDisplayedAiResponse($value) {
                    $value = normalizeResponseLogText($value);
                    if ($value === '') {
                        return '';
                    }

                    return trim(str_replace("''", "'", $value));
                }
            }

            if (!function_exists('decodeStoredDebugPayload')) {
                function decodeStoredDebugPayload($value) {
                    if ($value === null) {
                        return null;
                    }

                    if (!is_string($value)) {
                        if (is_array($value) || is_object($value)) {
                            return $value;
                        }
                        $value = (string)$value;
                    }

                    $clean = str_replace(["<br />", "<br>", "<br/>"], "\n", $value);
                    $clean = html_entity_decode(strip_tags($clean), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $decoded = json_decode($clean, true);

                    return is_array($decoded) ? $decoded : null;
                }
            }

            if (!function_exists('looksLikeStructuredResponsePayload')) {
                function looksLikeStructuredResponsePayload($value) {
                    $value = ltrim(normalizeResponseLogText($value));
                    if ($value === '') {
                        return true;
                    }

                    if (preg_match('/^(Array\s*\(|array\s*\(|\{|\[)/u', $value)) {
                        return true;
                    }

                    return strpos($value, '"response_connector"') !== false
                        || strpos($value, "'response_connector'") !== false
                        || strpos($value, '"OUTPUT_LOG"') !== false
                        || strpos($value, "'OUTPUT_LOG'") !== false;
                }
            }

            if (!function_exists('extractSubtitleFromScriptQueue')) {
                function extractSubtitleFromScriptQueue($value) {
                    $value = normalizeResponseLogText($value);
                    if ($value === '') {
                        return '';
                    }

                    if (preg_match('/\|ScriptQueue\|(.+?)(?:\/|\r|\n|$)/us', $value, $matches)) {
                        return normalizeDisplayedAiResponse($matches[1]);
                    }

                    if (preg_match('/\|Talk\|(.+?)(?:\r|\n|$)/us', $value, $matches)) {
                        return normalizeDisplayedAiResponse($matches[1]);
                    }

                    return '';
                }
            }

            if (!function_exists('extractTextFromDecodedResponsePayload')) {
                function extractTextFromDecodedResponsePayload($payload) {
                    if (!is_array($payload)) {
                        return '';
                    }

                    if (isset($payload['response']) && is_array($payload['response'])) {
                        $parts = [];
                        foreach ($payload['response'] as $responseEntry) {
                            if (is_array($responseEntry) && isset($responseEntry['processed'])) {
                                $processed = normalizeDisplayedAiResponse($responseEntry['processed']);
                                if ($processed !== '') {
                                    $parts[] = str_replace('|', "\n", $processed);
                                }
                            }
                        }

                        if (!empty($parts)) {
                            return implode("\n", array_values(array_unique($parts)));
                        }
                    }

                    if (isset($payload['OUTPUT_LOG'])) {
                        $subtitle = extractSubtitleFromScriptQueue($payload['OUTPUT_LOG']);
                        if ($subtitle !== '') {
                            return $subtitle;
                        }
                    }

                    $candidatePaths = [
                        ['choices', 0, 'message', 'content'],
                        ['full', 'choices', 0, 'message', 'content'],
                        ['response_full', 'choices', 0, 'message', 'content'],
                        ['candidates', 0, 'content', 'parts', 0, 'text'],
                        ['full', 'candidates', 0, 'content', 'parts', 0, 'text'],
                        ['response_full', 'candidates', 0, 'content', 'parts', 0, 'text'],
                        ['message'],
                        ['content'],
                        ['text'],
                    ];

                    foreach ($candidatePaths as $path) {
                        $cursor = $payload;
                        foreach ($path as $segment) {
                            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                                $cursor = null;
                                break;
                            }
                            $cursor = $cursor[$segment];
                        }

                        if (is_string($cursor)) {
                            $text = normalizeDisplayedAiResponse($cursor);
                            if ($text !== '' && !looksLikeStructuredResponsePayload($text)) {
                                return $text;
                            }
                        }
                    }

                    return '';
                }
            }

            if (!function_exists('extractBestAiResponseText')) {
                function extractBestAiResponseText($rawResponseValue, $rawPromptValue = '') {
                    $responseText = normalizeDisplayedAiResponse($rawResponseValue);
                    if ($responseText !== '' && !looksLikeStructuredResponsePayload($responseText)) {
                        return $responseText;
                    }

                    $subtitleFromResponse = extractSubtitleFromScriptQueue($rawResponseValue);
                    if ($subtitleFromResponse !== '') {
                        return $subtitleFromResponse;
                    }

                    $decodedResponse = decodeStoredDebugPayload($rawResponseValue);
                    if (is_array($decodedResponse)) {
                        $decodedText = extractTextFromDecodedResponsePayload($decodedResponse);
                        if ($decodedText !== '') {
                            return $decodedText;
                        }
                    }

                    $decodedPrompt = decodeStoredDebugPayload($rawPromptValue);
                    if (is_array($decodedPrompt)) {
                        $decodedPromptText = extractTextFromDecodedResponsePayload($decodedPrompt);
                        if ($decodedPromptText !== '') {
                            return $decodedPromptText;
                        }
                    }

                    $subtitleFromPrompt = extractSubtitleFromScriptQueue($rawPromptValue);
                    if ($subtitleFromPrompt !== '') {
                        return $subtitleFromPrompt;
                    }

                    return $responseText;
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
        
            $promptModalContentById = [];
            $mappedResults = array_map(function ($row) use ($columnHeaders, &$promptModalContentById) {
                $mappedRow = [];
                $handledKeys = ['localts' => true, 'response' => true, 'prompt' => true, 'url' => true, 'ROWID' => true, 'rowid' => true];

                $localts = $row['localts'] ?? null;
                if (!empty($localts)) {
                    $dt = new DateTime("@$localts");
                    $dt->setTimezone(new DateTimeZone('UTC'));
                    $mappedRow[$columnHeaders['localts']] = $dt->format('d-m-Y H:i:s');
                } else {
                    $mappedRow[$columnHeaders['localts']] = '';
                }

                $responseValue = (string)($row['response'] ?? '');
                $displayResponseValue = extractBestAiResponseText($responseValue, $row['prompt'] ?? '');
                $mappedRow[$columnHeaders['response']] = '<div class="full-content">' . nl2br(htmlspecialchars($displayResponseValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</div>';

                list($oghmaTopic, $oghmaLevel) = extractOghmaTopicAndLevel($row['prompt'] ?? '');
                if ($oghmaTopic) {
                    $mappedRow['Oghma Topic'] = htmlspecialchars($oghmaTopic) . ' (' . htmlspecialchars($oghmaLevel) . ')';
                } else {
                    $mappedRow['Oghma Topic'] = 'None';
                }

                $formattedPrompt = '';
                $rawPrompt = (string)($row['prompt'] ?? '');
                $rawPromptHtmlClean = str_replace(["<br />", "<br>", "<br/>"], "\n", $rawPrompt);
                $rawPromptDecoded = html_entity_decode($rawPromptHtmlClean, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                $decoded = json_decode($rawPromptDecoded, true);
                if (is_array($decoded)) {
                    $promptPayload = null;
                    if (isset($decoded['response_full']) && is_array($decoded['response_full'])) {
                        $promptPayload = $decoded['response_full'];
                    } elseif (isset($decoded['full']) && is_array($decoded['full'])) {
                        $promptPayload = $decoded['full'];
                    }

                    $responseConnector = isset($decoded['response_connector']) && is_array($decoded['response_connector'])
                        ? $decoded['response_connector']
                        : [];
                    $responseConnectorLabel = trim((string)($responseConnector['label'] ?? ''));
                    $responseConnectorDriver = trim((string)($responseConnector['driver'] ?? ''));
                    $model = (is_array($promptPayload) && isset($promptPayload['model']))
                        ? (string)$promptPayload['model']
                        : 'unknown';

                    $formattedPrompt = '<div style="font-family: \'Consolas\', monospace; line-height: 1.6;">';
                    $metaBits = [];
                    if ($responseConnectorLabel !== '') {
                        $metaBits[] = '<span style="display:inline-block; padding: 3px 8px; border-radius: 999px; background: #1f2937; color: #e5e7eb;">' . htmlspecialchars($responseConnectorLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
                    }
                    if ($responseConnectorDriver !== '') {
                        $metaBits[] = '<span style="display:inline-block; padding: 3px 8px; border-radius: 999px; background: #111827; color: #93c5fd;">' . htmlspecialchars($responseConnectorDriver, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
                    }
                    if ($model !== 'unknown') {
                        $metaBits[] = '<span style="display:inline-block; padding: 3px 8px; border-radius: 999px; background: #0f172a; color: #cbd5e1;">' . htmlspecialchars($model, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
                    }
                    if (!empty($metaBits)) {
                        $formattedPrompt .= '<div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:14px;">' . implode('', $metaBits) . '</div>';
                    }

                    $messages = [];
                    if (is_array($promptPayload) && isset($promptPayload['messages']) && is_array($promptPayload['messages'])) {
                        $messages = $promptPayload['messages'];
                    } elseif (isset($decoded['messages']) && is_array($decoded['messages'])) {
                        $messages = $decoded['messages'];
                    }

                    foreach ($messages as $msgIndex => $msg) {
                        if (!isset($msg['role']) || !isset($msg['content'])) {
                            continue;
                        }

                        $role = $msg['role'];
                        $content = $msg['content'];
                        if (is_array($content)) {
                            $content = json_encode($content, JSON_PRETTY_PRINT);
                        } else {
                            $content = (string)$content;
                        }

                        $escapedContent = htmlspecialchars($content);
                        $roleColor = $role === 'system' ? '#4ec9b0' : ($role === 'user' ? '#dcdcaa' : '#c586c0');

                        $formattedPrompt .= '<div style="margin: 10px 0; border-left: 3px solid ' . $roleColor . '; padding-left: 15px;">';
                        $formattedPrompt .= '<div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">';
                        $formattedPrompt .= '<span style="color: ' . $roleColor . '; font-weight: bold; text-transform: uppercase;">' . htmlspecialchars($role, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
                        $formattedPrompt .= '<span style="color: #6b7280; font-size: 12px;">#' . intval($msgIndex) . '</span>';
                        $formattedPrompt .= '</div>';
                        $formattedPrompt .= '<div style="color: #ce9178; white-space: pre-wrap; margin-top: 5px; max-height: 400px; overflow-y: auto; background: #1e1e1e; padding: 10px; border-radius: 5px;">' . $escapedContent . '</div>';

                        $formattedPrompt .= '</div>';
                    }

                    $formattedPrompt .= '</div>';
                }

                if ($formattedPrompt === '') {
                    $formattedPrompt = '<pre style="white-space: pre-wrap; word-wrap: break-word;">' . htmlspecialchars($rawPromptDecoded, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
                }

                $promptIdSeed = $row['ROWID'] ?? $row['rowid'] ?? md5($rawPrompt);
                $promptId = 'prompt_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$promptIdSeed);
                $promptModalContentById[$promptId] = $formattedPrompt;
                $mappedRow[$columnHeaders['prompt']] = '<button class="view-contents-btn" data-prompt-id="' . htmlspecialchars($promptId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">🧾 View Prompt</button>';

                $urlValue = (string)($row['url'] ?? '');
                if (strpos($responseValue, 'Array') === 0) {
                    $mappedRow[$columnHeaders['url']] = preg_replace('/ in \d+\.?\d* secs$/', '', $urlValue);
                } elseif (strpos($urlValue, '[AI secs]') !== false) {
                    $pattern = '/\[AI secs\]\s+([\d.]+)\s+\[TTS secs\]\s+([\d.]+)/';
                    if (preg_match($pattern, $urlValue, $matches)) {
                        $aiTime = floatval($matches[1]);
                        $totalTtsTime = floatval($matches[2]);
                        $actualTtsTime = $totalTtsTime - $aiTime;

                        $aiTimeFormatted = number_format($aiTime, 2);
                        $ttsTimeFormatted = number_format($actualTtsTime, 2);

                        $aiColor = getTimeColor($aiTime);
                        $ttsColor = getTimeColor($actualTtsTime);
                        $totalColor = getTimeColor($totalTtsTime);

                        $baseText = substr($urlValue, 0, strpos($urlValue, '[AI secs]'));
                        $mappedRow[$columnHeaders['url']] =
                            $baseText .
                            "<br>[LLM] <span style='color: " . $aiColor . "'>" . $aiTimeFormatted . "</span>" .
                            " [TTS] <span style='color: " . $ttsColor . "'>" . $ttsTimeFormatted . "</span>" .
                            " [Total]: <span style='color: " . $totalColor . "'>" . $totalTtsTime . "</span>";
                    } else {
                        $mappedRow[$columnHeaders['url']] = $urlValue;
                    }
                } else {
                    $mappedRow[$columnHeaders['url']] = $urlValue;
                }

                if (isset($row['ROWID']) || isset($row['rowid'])) {
                    $mappedRow['rowid'] = $row['ROWID'] ?? $row['rowid'];
                }

                foreach ($row as $key => $value) {
                    if (isset($handledKeys[$key])) {
                        continue;
                    }
                    $mappedRow[$columnHeaders[$key] ?? $key] = $value;
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
            if (!empty($promptModalContentById)) {
                echo '<div id="prompt-modal-store" style="display:none;">';
                foreach ($promptModalContentById as $promptId => $promptHtml) {
                    echo '<div id="' . htmlspecialchars($promptId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . $promptHtml . '</div>';
                }
                echo '</div>';
            }
            ?>
        </div>
        <?php endif; ?>

        
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

            // Display Memory Configuration Status
            // Get memory settings
            $memoryEnabled = $GLOBALS['FEATURES']['MEMORY_EMBEDDING']['ENABLED'] ?? false;
            $txtaiUrl = $GLOBALS['FEATURES']['MEMORY_EMBEDDING']['TXTAI_URL'] ?? 'Not set';
            $useText2Vec = $GLOBALS['FEATURES']['MEMORY_EMBEDDING']['USE_TEXT2VEC'] ?? false;

            $statusIcon = function ($enabled) {
                return $enabled
                    ? "<span style='color: #4caf50;'>Enabled</span>"
                    : "<span style='color: #f44336;'>Disabled</span>";
            };

            $results = $db->fetchAll(
                "SELECT gamets_truncated, n, summary, companions, tags, classifier, scope, ROWID as rowid, packed_message, native_vec
                 FROM memory_summary
                 ORDER BY gamets_truncated DESC, rowid DESC
                 LIMIT 150"
            );
            ?>
            <div style="background: #2a2a2a; border-left: 4px solid rgb(242, 124, 17); padding: 12px 15px; border-radius: 5px; margin: 15px 0; font-size: 0.9em;">
                <span style="color: rgb(242, 124, 17); font-weight: bold;">🧠 Memories:</span>
                <span style="color: #f8f9fa;">Complete log of memory summaries with scope, participants, and period coverage. Use this to verify memory continuity and long-term context quality.</span>
            </div>

            <div style="background: #1a1a1a; border: 1px solid #3a3a3a; border-radius: 8px; padding: 20px; margin: 15px 0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; gap: 10px; flex-wrap: wrap;">
                    <h3 style="margin: 0; color: rgb(242, 124, 17); word-spacing: 5px;">Memory System Configuration</h3>
                    <a href="<?php echo $webRoot; ?>/ui/core/config_hub.php?tab=globals" target="_blank" class="btn-base btn-primary" style="font-size: 13px; padding: 6px 12px;">Configure Settings</a>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
                    <div style="background: #2a2a2a; padding: 15px; border-radius: 5px; border: 1px solid #3a3a3a;">
                        <div style="font-weight: bold; margin-bottom: 8px; color: rgb(242, 124, 17); font-size: 14px;">Memory System</div>
                        <div style="font-size: 14px;"><?php echo $statusIcon($memoryEnabled); ?></div>
                    </div>

                    <div style="background: #2a2a2a; padding: 15px; border-radius: 5px; border: 1px solid #3a3a3a;">
                        <div style="font-weight: bold; margin-bottom: 8px; color: rgb(242, 124, 17); font-size: 14px;">TXT2VEC (Embeddings)</div>
                        <div style="font-size: 14px;"><?php echo $statusIcon($useText2Vec); ?></div>
                        <div style="font-size: 12px; color: #aaa; margin-top: 4px;">URL: <?php echo htmlspecialchars($txtaiUrl); ?></div>
                    </div>

                </div>

                <?php if (!$useText2Vec): ?>
                    <div style="background: #2a2a2a; border-left: 4px solid rgb(242, 124, 17); padding: 12px; margin-top: 15px; border-radius: 4px;">
                        <strong style="color: rgb(242, 124, 17);">Warning:</strong>
                        <span style="color: #f8f9fa;">TXT2VEC is disabled. Memory embeddings and vector search features are unavailable.</span>
                    </div>
                <?php endif; ?>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; margin: 15px 0; flex-wrap: wrap;">
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <button type="button" onclick="syncMemoriesConfirm()" class="btn-base action-button add-new" style="font-weight: bold;">Sync Memory Summaries Now</button>
                </div>
                <button type="button" onclick="deleteAllMemoriesConfirm()" class="btn-base btn-danger" style="background-color: #dc2626; font-weight: bold;">Delete All Memory Summaries</button>
            </div>

            <style>
                .edit-form {
                    display: none;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 10px 0;
                    background-color: #2a2a2a;
                }
                .edit-textarea {
                    width: 100%;
                    min-height: 120px;
                    margin-bottom: 5px;
                    background-color: #333;
                    color: #fff;
                    border: 1px solid #444;
                    padding: 8px;
                    border-radius: 4px;
                }
                .edit-input {
                    width: 100%;
                    margin-bottom: 5px;
                    background-color: #333;
                    color: #fff;
                    border: 1px solid #444;
                    padding: 8px;
                    border-radius: 4px;
                }
                .memory-content {
                    min-height: 120px;
                    width: 100%;
                    overflow-y: auto;
                    padding: 8px;
                    white-space: pre-wrap;
                    word-wrap: break-word;
                    border: 1px solid #444;
                    background-color: #333;
                    color: #fff;
                    border-radius: 4px;
                }
                .summary-section {
                    margin-bottom: 8px;
                    padding: 5px;
                    border-bottom: 1px solid #444;
                }
                .subcategory-section {
                    margin-top: 6px;
                    padding: 6px 8px;
                    border: 1px solid #444;
                    border-radius: 4px;
                    font-size: 0.85em;
                    background: rgba(0, 0, 0, 0.15);
                }
                .subcategory-label {
                    color: #aaa;
                    font-size: 0.9em;
                    margin-right: 5px;
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
                    white-space: pre-wrap;
                }
                .memory-details {
                    margin-top: 8px;
                }
                .memory-details summary {
                    cursor: pointer;
                    color: #aaa;
                    user-select: none;
                }
            </style>

            <div class="table-container">
                <table>
                    <thead>
                    <tr>
                        <th style="width:6%">ID</th>
                        <th style="width:12%">Scope</th>
                        <th style="width:12%">People</th>
                        <th style="width:16%"><a href="https://en.uesp.net/wiki/Lore:Calendar" target="_blank" style="color: yellow;">Tamrielic Time</a></th>
                        <th style="width:54%">Summary</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($results)): ?>
                        <?php foreach ($results as $row): ?>
                            <?php
                            $rowId = intval($row['rowid'] ?? 0);
                            $scope = trim((string)($row['scope'] ?? ''));
                            $scope = $scope !== '' ? $scope : 'global';
                            $people = trim((string)($row['companions'] ?? ''));
                            $people = $people !== '' ? $people : '-';
                            $displayPeople = function_exists('chimRenderNarratorRoleplayText') ? chimRenderNarratorRoleplayText($people) : $people;
                            $displaySummary = function_exists('chimRenderNarratorRoleplayText') ? chimRenderNarratorRoleplayText($row['summary'] ?? '') : ($row['summary'] ?? '');
                            $displayPackedMessage = function_exists('chimRenderNarratorRoleplayText') ? chimRenderNarratorRoleplayText($row['packed_message'] ?? '') : ($row['packed_message'] ?? '');
                            $tamrielicTime = !empty($row['gamets_truncated']) ? convert_gamets2skyrim_long_date2($row['gamets_truncated']) : '-';
                            ?>
                            <tr>
                                <td><?php echo $rowId; ?></td>
                                <td><?php echo htmlspecialchars($scope); ?></td>
                                <td><?php echo htmlspecialchars($displayPeople); ?></td>
                                <td><?php echo htmlspecialchars($tamrielicTime); ?></td>
                                <td>
                                    <div id="display-<?php echo $rowId; ?>">
                                        <div class="summary-section">
                                            <span class="summary-content"><?php echo nl2br(htmlspecialchars($displaySummary)); ?></span>
                                        </div>

                                        <details class="memory-details">
                                            <summary>Details</summary>
                                            <div class="subcategory-section">
                                                <span class="summary-label subcategory-label">Tags:</span>
                                                <span class="summary-content subcategory-content"><?php echo htmlspecialchars($row['tags'] ?? ''); ?></span>
                                            </div>
                                            <div class="subcategory-section">
                                                <span class="summary-label subcategory-label">Embedding:</span>
                                                <span class="summary-content subcategory-content"><?php echo htmlspecialchars($row['native_vec'] ?? ''); ?></span>
                                            </div>
                                            <div class="subcategory-section">
                                                <span class="summary-label subcategory-label">Packed Memory Content:</span>
                                                <textarea readonly class="memory-content"><?php echo htmlspecialchars($displayPackedMessage); ?></textarea>
                                            </div>
                                        </details>

                                        <div style="margin-top: 10px; display: flex; gap: 8px; flex-wrap: wrap;">
                                            <button type="button" class="btn-base action-button edit" onclick="toggleEdit(<?php echo $rowId; ?>)">Edit</button>
                                            <button type="button" class="btn-base btn-danger" onclick="deleteMemoryRow(<?php echo $rowId; ?>)">Delete</button>
                                        </div>
                                    </div>

                                    <form id="edit-form-<?php echo $rowId; ?>" class="edit-form" method="post" action="events-memories.php?tab=memory">
                                        <input type="hidden" name="rowid" value="<?php echo $rowId; ?>">
                                        <input type="hidden" name="save_memory_edit" value="1">
                                        <label>Summary:</label>
                                        <textarea name="summary" class="edit-textarea form-control"><?php echo htmlspecialchars($row['summary'] ?? ''); ?></textarea>
                                        <label>Tags:</label>
                                        <input type="text" name="tags" class="edit-input form-control" value="<?php echo htmlspecialchars($row['tags'] ?? ''); ?>">
                                        <label>People:</label>
                                        <input type="text" name="companions" class="edit-input form-control" value="<?php echo htmlspecialchars($row['companions'] ?? ''); ?>">
                                        <div style="margin-top: 10px; display: flex; gap: 8px; flex-wrap: wrap;">
                                            <button type="submit" class="btn-base action-button add-new">Save</button>
                                            <button type="button" class="btn-base btn-cancel" onclick="cancelEdit(<?php echo $rowId; ?>)">Cancel</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #6c757d; padding: 20px;">No memory summaries found.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <script>
            function syncMemoriesConfirm() {
                if (confirm('Will use tokens from your current AI connector. This now syncs global memories and per-NPC individual memory banks for enabled NPCs. May take a few minutes to process. DO NOT REFRESH THE WEBPAGE!')) {
                    window.location.href = '<?php echo $webRoot; ?>/ui/tests/vector-compact-chromadb.php';
                }
            }

            function deleteAllMemoriesConfirm() {
                var userInput = prompt('THIS WILL DELETE ALL SUMMARIZED MEMORIES!\n\nThis action cannot be undone and will remove all AI memory summaries.\n\nTo confirm this dangerous operation, please type exactly: Delete');
                if (userInput === 'Delete') {
                    window.location.href = '<?php echo $webRoot; ?>/ui/tests/vector-delete-memory_summary.php';
                } else if (userInput !== null) {
                    alert('Operation cancelled. You must type exactly "Delete" to confirm.');
                }
            }

            function toggleEdit(rowid) {
                const displayDiv = document.getElementById('display-' + String(rowid));
                const editForm = document.getElementById('edit-form-' + String(rowid));
                if (!displayDiv || !editForm) {
                    return;
                }
                displayDiv.style.display = 'none';
                editForm.style.display = 'block';
            }

            function cancelEdit(rowid) {
                const displayDiv = document.getElementById('display-' + String(rowid));
                const editForm = document.getElementById('edit-form-' + String(rowid));
                if (!displayDiv || !editForm) {
                    return;
                }
                displayDiv.style.display = 'block';
                editForm.style.display = 'none';
            }

            function deleteMemoryRow(rowid) {
                if (confirm('Are you sure you want to delete this memory summary?')) {
                    window.location.href = 'events-memories.php?tab=memory&delete_memory=' + String(rowid);
                }
            }
            </script>
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

            echo "<p>Books that have been summarized by CHIM NPCs.</p>";

            if (!empty($mappedResults)) {
                print_array_as_table($mappedResults);
            } else {
                echo "<div class='table-container'>";
                echo "<p style='text-align: center; color: #6c757d; padding: 20px;'>No books found. Read some books in-game to see them here!</p>";
                echo "</div>";
            }
            ?>
        </div>

        <div id="adventure-tab" class="tab-content embed-tab <?php echo $activeTab === 'adventure' ? 'active' : ''; ?>">
            <iframe class="embed-frame" title="Adventure Log" <?php echo $activeTab === 'adventure' ? 'src' : 'data-src'; ?>="<?php echo $webRoot; ?>/ui/adventurelog.php?embed=1"></iframe>
        </div>

        <div id="diaries-tab" class="tab-content embed-tab <?php echo $activeTab === 'diaries' ? 'active' : ''; ?>">
            <iframe class="embed-frame" title="CHIM Diaries" <?php echo $activeTab === 'diaries' ? 'src' : 'data-src'; ?>="<?php echo $webRoot; ?>/ui/diarylog.php?embed=1"></iframe>
        </div>

        <div id="soulgaze-tab" class="tab-content embed-tab <?php echo $activeTab === 'soulgaze' ? 'active' : ''; ?>">
            <iframe class="embed-frame" title="Soulgaze Gallery" <?php echo $activeTab === 'soulgaze' ? 'src' : 'data-src'; ?>="<?php echo $webRoot; ?>/ui/soulgaze_gallery.php?embed=1"></iframe>
        </div>

        <div id="questgen-tab" class="tab-content embed-tab <?php echo $activeTab === 'questgen' ? 'active' : ''; ?>">
            <iframe class="embed-frame" title="AI Quest Manager" <?php echo $activeTab === 'questgen' ? 'src' : 'data-src'; ?>="<?php echo $webRoot; ?>/ui/addons/snqe/hub.php?embed=1"></iframe>
        </div>

        <div id="backgroundlife-tab" class="tab-content embed-tab <?php echo $activeTab === 'backgroundlife' ? 'active' : ''; ?>">
            <iframe class="embed-frame" title="Background Life" <?php echo $activeTab === 'backgroundlife' ? 'src' : 'data-src'; ?>="<?php echo $webRoot; ?>/ui/mapview.php?bgl_tab=<?php echo urlencode($backgroundLifeSubTab); ?>"></iframe>
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

function loadEmbeddedTab(tabContent) {
    const frame = tabContent ? tabContent.querySelector('iframe[data-src]') : null;
    if (!frame) return;

    frame.src = frame.dataset.src;
    frame.removeAttribute('data-src');
}

function switchTab(tabName, updateHistory = true) {
    if (tabName === 'responselog') {
        window.location.href = '<?php echo $webRoot; ?>/ui/ai-response.php';
        return;
    }

    const target = document.getElementById(tabName + '-tab');
    const clickedButton = document.querySelector('.events-memories-navigation .tab-button[data-tab="' + tabName + '"]');
    if (!target || !clickedButton) return;

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
    
    // Show selected tab content and load embedded tools on demand.
    target.classList.add('active');
    loadEmbeddedTab(target);
    
    // Highlight the selected page and its category.
    clickedButton.classList.add('active');
    document.querySelectorAll('.events-memories-navigation .tab-group').forEach(group => {
        group.classList.toggle('active', group.contains(clickedButton));
    });
    
    if (updateHistory) {
        window.history.pushState({}, '', clickedButton.href);
    }
}

document.querySelectorAll('.events-memories-navigation .tab-button[data-local-tab="true"]').forEach(button => {
    button.addEventListener('click', event => {
        event.preventDefault();
        switchTab(button.dataset.tab);
    });
});

window.addEventListener('popstate', () => {
    const tabName = new URL(window.location.href).searchParams.get('tab') || 'eventlog';
    switchTab(tabName, false);
});

// Checkbox selection functionality
function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.event-checkbox:checked');
    const count = checkboxes.length;
    const deleteBtn = document.getElementById('deleteSelectedBtn');
    const countSpan = document.getElementById('selectedCount');
    
    if (countSpan) {
        countSpan.textContent = count;
    }
    
    if (deleteBtn) {
        deleteBtn.style.display = count > 0 ? 'inline-block' : 'none';
    }
}

function applyEventLogTypeFilter(eventType) {
    if (!eventType) {
        return;
    }

    if (typeof window.buildEventLogPageUrl === 'function') {
        window.location.href = window.buildEventLogPageUrl({
            page: 1,
            hideEventType: eventType,
            autorefresh: window.location.search.includes('autorefresh=true')
        });
        return;
    }

    const fallbackUrl = new URL(window.location.href);
    fallbackUrl.searchParams.set('tab', 'eventlog');
    fallbackUrl.searchParams.set('page', '1');
    fallbackUrl.searchParams.set('hide_event_type', eventType);
    window.location.href = fallbackUrl.toString();
}

function handleDeletePresetAction() {
    const select = document.getElementById('delete-action-select');
    const action = select ? String(select.value || '') : '';
    if (!action) {
        return;
    }

    if (action === 'all') {
        if (select) {
            select.value = '';
        }
        deleteAllEventsConfirm();
        return;
    }

    const deleteCount = parseInt(action, 10);
    if (![5, 10, 20, 50, 100].includes(deleteCount)) {
        if (select) {
            select.value = '';
        }
        return;
    }

    if (confirm(`Are you sure you want to delete the last ${deleteCount} events?`)) {
        if (typeof window.buildEventLogPageUrl === 'function') {
            window.location.href = window.buildEventLogPageUrl({
                deleteLast: deleteCount,
                autorefresh: window.location.search.includes('autorefresh=true')
            });
            return;
        }

        const fallbackUrl = new URL(window.location.href);
        fallbackUrl.searchParams.set('tab', 'eventlog');
        fallbackUrl.searchParams.set('delete_last', String(deleteCount));
        window.location.href = fallbackUrl.toString();
    } else if (select) {
        select.value = '';
    }
}

function removeHiddenEventType(eventType) {
    if (!eventType) {
        return;
    }

    if (typeof window.buildEventLogPageUrl === 'function') {
        window.location.href = window.buildEventLogPageUrl({
            page: 1,
            showEventType: eventType,
            autorefresh: window.location.search.includes('autorefresh=true')
        });
        return;
    }

    const fallbackUrl = new URL(window.location.href);
    fallbackUrl.searchParams.set('tab', 'eventlog');
    fallbackUrl.searchParams.set('page', '1');
    fallbackUrl.searchParams.set('show_event_type', eventType);
    window.location.href = fallbackUrl.toString();
}

function clearHiddenEventTypes() {
    if (typeof window.buildEventLogPageUrl === 'function') {
        window.location.href = window.buildEventLogPageUrl({
            page: 1,
            clearHiddenEventTypes: true,
            autorefresh: window.location.search.includes('autorefresh=true')
        });
        return;
    }

    const fallbackUrl = new URL(window.location.href);
    fallbackUrl.searchParams.set('tab', 'eventlog');
    fallbackUrl.searchParams.set('page', '1');
    fallbackUrl.searchParams.set('clear_hidden_event_types', '1');
    window.location.href = fallbackUrl.toString();
}

function deleteSelectedEvents() {
    const checkboxes = document.querySelectorAll('.event-checkbox:checked');
    const rowids = Array.from(checkboxes).map(cb => cb.getAttribute('data-rowid'));
    
    if (rowids.length === 0) {
        alert('Please select at least one event to delete.');
        return;
    }
    
    if (!confirm(`Are you sure you want to delete ${rowids.length} selected event(s)?`)) {
        return;
    }
    
    // Create a form and submit it
    const form = document.createElement('form');
    form.method = 'POST';
    if (typeof window.buildEventLogPageUrl === 'function') {
        form.action = window.buildEventLogPageUrl();
    } else {
        form.action = 'events-memories.php?tab=eventlog';
    }
    
    const deleteInput = document.createElement('input');
    deleteInput.type = 'hidden';
    deleteInput.name = 'delete_selected';
    deleteInput.value = '1';
    form.appendChild(deleteInput);
    
    rowids.forEach(rowid => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'rowids[]';
        input.value = rowid;
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    form.submit();
}

// Toggle all checkboxes
function toggleAllCheckboxes(selectAllCheckbox) {
    const checkboxes = document.querySelectorAll('.event-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = selectAllCheckbox.checked;
    });
    updateSelectedCount();
}

// Add event listeners when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Add "Select All" checkbox to the table header
    const eventlogTab = document.getElementById('eventlog-tab');
    if (eventlogTab) {
        const tables = eventlogTab.querySelectorAll('table');
        if (tables.length > 0) {
            const firstTable = tables[0];
            const headerRow = firstTable.querySelector('tr.primary');
            if (headerRow) {
                const firstTh = headerRow.querySelector('th');
                if (firstTh && firstTh.textContent.trim() === '☑') {
                    firstTh.innerHTML = '<input type="checkbox" id="selectAllCheckbox" onchange="toggleAllCheckboxes(this)" style="cursor: pointer; width: 18px; height: 18px;" title="Select/Deselect All">';
                }
            }
        }
    }
    
    // Add change listeners to all checkboxes
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('event-checkbox')) {
            updateSelectedCount();
            
            // Update "Select All" checkbox state
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            if (selectAllCheckbox) {
                const allCheckboxes = document.querySelectorAll('.event-checkbox');
                const checkedCheckboxes = document.querySelectorAll('.event-checkbox:checked');
                selectAllCheckbox.checked = allCheckboxes.length > 0 && allCheckboxes.length === checkedCheckboxes.length;
            }
        }
    });
    
    // Initial count update
    updateSelectedCount();
});
</script>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?> 
