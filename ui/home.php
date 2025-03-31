<?php 
session_start();

date_default_timezone_set('UTC');
// Enable error reporting (for development purposes)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Database connection details
$host = 'localhost';
$port = '5432';
$dbname = 'dwemer';
$schema = 'public';
$username = 'dwemer';
$password = 'dwemer';

// Include game timestamp utilities
require_once(dirname(__DIR__).DIRECTORY_SEPARATOR."lib/utils_game_timestamp.php");

// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

$TITLE = "📊 Dwemer Dashboard";

// Connect to the database
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");

if (!$conn) {
    echo "<div class='message'>Failed to connect to database: " . pg_last_error() . "</div>";
    exit;
}

// Create database wrapper class for db_updates.php
class sql {
    private $conn;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }
    
    public function fetchAll($query) {
        $result = pg_query($this->conn, $query);
        if (!$result) {
            return [];
        }
        $rows = [];
        while ($row = pg_fetch_assoc($result)) {
            $rows[] = $row;
        }
        return $rows;
    }
    
    public function execQuery($query) {
        return pg_query($this->conn, $query);
    }
}

$db = new sql();

/* Check for database updates only in index.php with no parms*/
if (sizeof($_GET)==0) {
    require_once(__DIR__."/../debug/db_updates.php");
    require_once(__DIR__."/../debug/npc_removal.php");
}
/* END of check database for updates */

// Function to sanitize and validate integers
function sanitize_int($value, $default) {
    $value = filter_var($value, FILTER_VALIDATE_INT);
    return ($value !== false) ? $value : $default;
}

/**
 * Function to render a widget  
 * 
 * @param string $title The widget title
 * @param string $content The widget content
 * @param string $type The widget type (default, chart, table, etc.)
 * @param array $options Additional options for the widget
 * @return string HTML string representing the widget
 */
function render_widget($title, $content, $type = 'default', $options = []) {
    $widgetClass = "widget widget-{$type}";
    if (isset($options['class'])) {
        $widgetClass .= " " . $options['class'];
    }
    
    $html = "<div class='{$widgetClass}'>";
    $html .= "<div class='widget-header'>";
    $html .= "<h3>{$title}</h3>";
    if (isset($options['actions'])) {
        $html .= "<div class='widget-actions'>{$options['actions']}</div>";
    }
    $html .= "</div>";
    $html .= "<div class='widget-content'>{$content}</div>";
    $html .= "</div>";
    
    return $html;
}

/**
 * Function to fetch and format stats for a widget
 * 
 * @param string $query The SQL query to fetch stats
 * @param array $options Formatting options
 * @return array Formatted stats data
 */
function fetch_widget_stats($conn, $query, $options = []) {
    $result = pg_query($conn, $query);
    if (!$result) {
        return ['error' => pg_last_error($conn)];
    }
    
    $stats = [];
    while ($row = pg_fetch_assoc($result)) {
        $stats[] = $row;
    }
    
    return $stats;
}

// Start output buffering
ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");
?>
<style>
@font-face {
    font-family: 'SkyrimBooks_Handwritten_Bold';
    src: url('/HerikaServer/ui/css/font/SkyrimBooks_Handwritten_Bold-Regular.ttf') format('truetype');
    font-weight: normal;
    font-style: normal;
    font-display: swap;
}
</style>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<?php

$debugPaneLink = false;
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/navbar.php");
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="<?php echo $webRoot; ?>/ui/images/favicon.ico">
    <title>📊 Dwemer Dashboard</title>
    <style>
        /* Dashboard specific styles */
        .dashboard-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px;
            max-width: 1600px;
            margin: 0 auto;
        }

        .widget {
            background: #2d2d2d;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .widget-header {
            background: #1a1a1a;
            padding: 15px;
            border-bottom: 1px solid #3a3a3a;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .widget-header h3 {
            margin: 0;
            color: #f8f9fa;
            font-size: 1.2em;
        }

        .widget-actions {
            display: flex;
            gap: 10px;
        }

        .widget-content {
            padding: 15px;
            color: #d4d4d4;
        }

        /* Widget type specific styles */
        .widget-chart {
            min-height: 300px;
        }

        .widget-table {
            overflow-x: auto;
        }

        .widget-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .widget-table th,
        .widget-table td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #3a3a3a;
        }

        .widget-table th {
            background: #1a1a1a;
            color: #f8f9fa;
        }

        .widget-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .stat-card {
            background: #1a1a1a;
            padding: 15px;
            border-radius: 4px;
            text-align: center;
        }

        .stat-value {
            font-size: 1.5em;
            font-weight: bold;
            color: #ff00c6;
        }

        .stat-label {
            font-size: 0.9em;
            color: #6c757d;
            margin-top: 5px;
        }

        /* Quest list styles */
        .quest-list {
            border-top: 1px solid #3a3a3a;
            padding-top: 15px;
        }

        .quest-list h4 {
            color: #f8f9fa;
            margin: 0 0 15px 0;
            font-size: 1.1em;
        }

        .quest-list .widget-table {
            margin-top: 10px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
        }

        /* Add to the style section */
        .skyrim-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            width: 100%;
        }

        .stats-category {
            background: #2a2a2a;
            border-radius: 4px;
            padding: 10px;
            font-size: 0.9em;
        }

        .stats-category h4 {
            color: #f8f9fa;
            margin: 0 0 8px 0;
            font-size: 1em;
            border-bottom: 1px solid #3a3a3a;
            padding-bottom: 3px;
        }

        /* Add specific styling for Quest Statistics category */
        .stats-category:has(h4:contains('Quest Statistics')) {
            font-size: 0.8em;
        }

        .stats-category:has(h4:contains('Quest Statistics')) h4 {
            font-size: 0.9em;
        }

        .stats-category:has(h4:contains('Quest Statistics')) .stat-item {
            font-size: 0.75em;
        }

        .stats-category:has(h4:contains('Quest Statistics')) .stat-item .stat-label {
            font-size: 0.8em;
        }

        .stats-category:has(h4:contains('Quest Statistics')) .stat-item .stat-value {
            font-size: 0.8em;
        }

        .stats-category:has(h4:contains('Quest Statistics')) .sub-category {
            font-size: 0.8em;
        }

        .stats-category:has(h4:contains('Quest Statistics')) .sub-category h5 {
            font-size: 0.85em;
        }

        .stats-list {
            display: grid;
            gap: 4px;
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 2px 0;
            border-bottom: 1px solid #2a2a2a;
            font-size: 0.85em;
        }

        .stat-item:last-child {
            border-bottom: none;
        }

        .stat-item .stat-label {
            color: #6c757d;
            font-size: 0.9em;
            flex: 1;
            margin-right: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .stat-item .stat-value {
            color: #ff00c6;
            font-weight: bold;
            font-size: 0.9em;
            min-width: 40px;
            text-align: right;
        }

        /* Add styles for sub-categories */
        .sub-category {
            margin: 6px 0;
            padding: 6px;
            background: #2a2a2a;
            border-radius: 4px;
            font-size: 0.9em;
        }

        .sub-category h5 {
            color: #f8f9fa;
            margin: 0 0 6px 0;
            font-size: 0.95em;
            border-bottom: 1px solid #3a3a3a;
            padding-bottom: 3px;
        }

        .sub-category .stat-item {
            margin-left: 6px;
            font-size: 0.85em;
        }

        /* Make the Skyrim Stats widget full width */
        .widget-skyrim-stats {
            grid-column: 1 / -1;
            max-width: 100%;
        }

        /* Responsive adjustments for Skyrim Stats */
        @media (max-width: 1200px) {
            .skyrim-stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .skyrim-stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
            
            .stats-category {
                padding: 8px;
            }
            
            .stat-item {
                font-size: 0.8em;
            }
        }

        /* Dashboard specific styles */
        .dashboard-buttons {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
            padding: 0 20px;
        }

        .dashboard-btn {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            background: #2d2d2d;
            color: #f8f9fa;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.9em;
            transition: all 0.3s ease;
            border: 1px solid #3a3a3a;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            font-family: inherit;
        }

        .dashboard-btn:hover {
            background: #3a3a3a;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }

        .dashboard-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .dashboard-btn .btn-icon {
            margin-right: 8px;
            font-size: 1.1em;
        }

        @media (max-width: 768px) {
            .dashboard-buttons {
                gap: 8px;
            }
            
            .dashboard-btn {
                padding: 6px 12px;
                font-size: 0.8em;
            }
        }
    </style>
</head>
<body>
    <main class="container">
        <h1>📊 Dwemer Dashboard</h1>

        <div class="dashboard-buttons">
            <button onclick="window.location.href='<?php echo $webRoot; ?>/ui/index.php?table=eventlog'" class="dashboard-btn">
                <span class="btn-icon">📜</span> Events
            </button>
            <button onclick="window.location.href='<?php echo $webRoot; ?>/ui/index.php?table=log'" class="dashboard-btn">
                <span class="btn-icon">📋</span> Response Log
            </button>
            <button onclick="window.location.href='<?php echo $webRoot; ?>/ui/index.php?table=memory_summary'" class="dashboard-btn">
                <span class="btn-icon">🧠</span> Memory Summaries
            </button>
            <button onclick="window.location.href='<?php echo $webRoot; ?>/ui/conf_wizard.php'" class="dashboard-btn">
                <span class="btn-icon">🧙</span> Configuration Wizard
            </button>
            <button onclick="window.location.href='<?php echo $webRoot; ?>/ui/npc_upload.php'" class="dashboard-btn">
                <span class="btn-icon">🧑</span> NPC Biography Management
            </button>
            <button onclick="window.location.href='<?php echo $webRoot; ?>/ui/oghma_upload.php'" class="dashboard-btn">
                <span class="btn-icon">📙</span> Oghma Management
            </button>
            <button onclick="window.location.href='<?php echo $webRoot; ?>/ui/diarylog.php'" class="dashboard-btn">
                <span class="btn-icon">📖</span> Diaries
            </button>
            <button onclick="window.location.href='<?php echo $webRoot; ?>/ui/adventurelog.php'" class="dashboard-btn">
                <span class="btn-icon">⚔</span> Adventure Log
            </button>
            <button onclick="window.location.href='<?php echo $webRoot; ?>/ui/index.php?plugins_show=true'" class="dashboard-btn">
                <span class="btn-icon">🔌</span> Server Plugins
            </button>
            <button onclick="window.location.href='<?php echo $webRoot; ?>/ui/dwemer-diagnostics.php'" class="dashboard-btn">
                <span class="btn-icon">🤖</span> Dwemer AI Diagnostics (WIP)
            </button>
            <button onclick="window.location.href='<?php echo $webRoot; ?>/ui/tests/apache2err.php'" class="dashboard-btn">
                <span class="btn-icon">🌲</span> Server Logs
            </button>
        </div>

        <div class="dashboard-container">
            <?php
            // Example widgets - these can be moved to separate files later
            
            // 1. System Status Widget
            $systemStatus = fetch_widget_stats($conn, "
                SELECT 
                    COUNT(*) FILTER (WHERE type = 'chat') as total_context_events,
                    MAX(localts) as last_event,
                    MIN(localts) as first_event,
                    MAX(gamets) as last_gamets
                FROM {$schema}.eventlog
            ");
            
            if (!isset($systemStatus['error'])) {
                $lastEvent = new DateTime("@{$systemStatus[0]['last_event']}");
                $firstEvent = new DateTime("@{$systemStatus[0]['first_event']}");
                $lastEvent->setTimezone(new DateTimeZone('UTC'));
                $firstEvent->setTimezone(new DateTimeZone('UTC'));
                
                // Calculate real time elapsed
                $realTimeElapsed = $firstEvent->diff($lastEvent);
                $realTimeElapsedStr = '';
                if ($realTimeElapsed->y > 0) $realTimeElapsedStr .= $realTimeElapsed->y . ' years, ';
                if ($realTimeElapsed->m > 0) $realTimeElapsedStr .= $realTimeElapsed->m . ' months, ';
                if ($realTimeElapsed->d > 0) $realTimeElapsedStr .= $realTimeElapsed->d . ' days, ';
                if ($realTimeElapsed->h > 0) $realTimeElapsedStr .= $realTimeElapsed->h . ' hours, ';
                if ($realTimeElapsed->i > 0) $realTimeElapsedStr .= $realTimeElapsed->i . ' minutes';
                $realTimeElapsedStr = rtrim($realTimeElapsedStr, ', ');
                
                // Format last played time in a more readable way
                $lastPlayed = $lastEvent->format('jS F, Y, H:i');
                
                // Get in-game time using the last gamets
                $inGameTime = '';
                $totalTimeElapsed = '';
                if (isset($systemStatus[0]['last_gamets']) && $systemStatus[0]['last_gamets'] > 0) {
                    $inGameTime = convert_gamets2skyrim_long_date2($systemStatus[0]['last_gamets']);
                    // Calculate total time elapsed
                    $totalHours = convert_gamets2hours($systemStatus[0]['last_gamets']);
                    
                    // Convert hours to days and remaining hours
                    $days = floor($totalHours / 24);
                    $remainingHours = $totalHours % 24;
                    
                    $totalTimeElapsed = "{$days} days";
                    if ($remainingHours > 0) {
                        $totalTimeElapsed .= ", {$remainingHours} hours";
                    }
                }

                // Get quest information
                // First check if quests table exists and has data
                $questsCheck = fetch_widget_stats($conn, "
                    SELECT COUNT(*) as count
                    FROM {$schema}.quests
                ");
                
                error_log("Quests table check: " . print_r($questsCheck, true));
                
                if (!isset($questsCheck['error']) && isset($questsCheck[0]['count'])) {
                    $questTable = fetch_widget_stats($conn, "
                        SELECT name as quest_name, briefing
                        FROM {$schema}.quests
                        ORDER BY name
                    ");
                    
                    // Debug logging for quests
                    error_log("Quests Query Results: " . print_r($questTable, true));
                    
                    $questsContent = "<div class='quest-list'>
                        <h4>Current Quests</h4>
                        <table class='widget-table'>
                            <tr><th>Quest Name</th><th>Briefing</th></tr>";
                    
                    if (!isset($questTable['error']) && !empty($questTable)) {
                        error_log("Found " . count($questTable) . " quests");
                        foreach ($questTable as $quest) {
                            error_log("Processing quest: " . print_r($quest, true));
                            $questsContent .= "<tr>
                                <td>" . htmlspecialchars($quest['quest_name']) . "</td>
                                <td>" . htmlspecialchars($quest['briefing']) . "</td>
                            </tr>";
                        }
                    } else {
                        error_log("No quests found or error: " . print_r($questTable, true));
                        $questsContent .= "<tr><td colspan='2' style='text-align: center;'>No active quests</td></tr>";
                    }
                    
                    $questsContent .= "</table></div>";
                } else {
                    error_log("Quests table check error: " . print_r($questsCheck, true));
                    $questsContent = "<div class='quest-list'>
                        <h4>Current Quests</h4>
                        <table class='widget-table'>
                            <tr><th>Quest Name</th><th>Briefing</th></tr>
                            <tr><td colspan='2' style='text-align: center;'>No active quests</td></tr>
                        </table></div>";
                }
                
                // Get current AI objective information
                $currentMission = fetch_widget_stats($conn, "
                    SELECT description, localts, gamets
                    FROM {$schema}.currentmission
                    WHERE description IS NOT NULL
                    ORDER BY localts DESC
                ");
                
                // Debug logging
                error_log("Current Mission Query Results: " . print_r($currentMission, true));
                
                $currentMissionContent = "<div class='quest-list'>
                    <h4>Active AI Objectives</h4>
                    <table class='widget-table'>
                        <tr><th>Description</th><th>Time (UTC)</th><th><a href='https://en.uesp.net/wiki/Lore:Calendar' target='_blank'>Tamrielic Time</a></th></tr>";
                
                if (!isset($currentMission['error']) && !empty($currentMission)) {
                    foreach ($currentMission as $mission) {
                        $time = new DateTime("@{$mission['localts']}");
                        $time->setTimezone(new DateTimeZone('UTC'));
                        $tamrielicTime = '';
                        if (isset($mission['gamets']) && $mission['gamets'] > 0) {
                            $tamrielicTime = convert_gamets2skyrim_long_date2($mission['gamets']);
                        }
                        $currentMissionContent .= "<tr>
                            <td>" . htmlspecialchars($mission['description']) . "</td>
                            <td>{$time->format('jS F, Y, H:i')}</td>
                            <td>{$tamrielicTime}</td>
                        </tr>";
                    }
                } else {
                    error_log("Current Mission Error or Empty: " . print_r($currentMission, true));
                    $currentMissionContent .= "<tr><td colspan='3' style='text-align: center;'>No active objectives</td></tr>";
                }
                
                $currentMissionContent .= "</table></div>";
                
                echo render_widget('Current Playthrough', "
                    <div class='quest-list'>
                        <h4>World Information</h4>
                        <table class='widget-table'>
                            <tr><th>Stats</th><th>Value</th></tr>
                            <tr>
                                <td>Player Name</td>
                                <td>" . htmlspecialchars($PLAYER_NAME) . "</td>
                            </tr>
                            <tr>
                                <td>Last Played (UTC)</td>
                                <td>{$lastPlayed}</td>
                            </tr>
                            <tr>
                                <td>Current In-Game Time</td>
                                <td>{$inGameTime}</td>
                            </tr>
                            <tr>
                                <td>Tamrielic Days Elapsed</td>
                                <td>{$totalTimeElapsed}</td>
                            </tr>
                            <tr>
                                <td>Real Time Elapsed</td>
                                <td>{$realTimeElapsedStr}</td>
                            </tr>
                        </table>
                    </div>
                    {$questsContent}
                    {$currentMissionContent}
                ");
            }

            // 2. Recent Events Widget
            $recentEvents = fetch_widget_stats($conn, "
                SELECT type, data, localts, gamets
                FROM {$schema}.eventlog
                WHERE type IN ('chat', 'inputtext')
                ORDER BY localts DESC
                LIMIT 5
            ");
            
            if (!isset($recentEvents['error'])) {
                $eventsTable = "<table class='widget-table'>
                    <tr>
                        <th style='width: 50%;'>Dialogue</th>
                        <th style='width: 25%;'>Time (UTC)</th>
                        <th style='width: 25%;'><a href='https://en.uesp.net/wiki/Lore:Calendar' target='_blank'>Tamrielic Time</a></th>
                    </tr>";
                
                foreach ($recentEvents as $event) {
                    $time = new DateTime("@{$event['localts']}");
                    $time->setTimezone(new DateTimeZone('UTC'));
                    $tamrielicTime = '';
                    if (isset($event['gamets']) && $event['gamets'] > 0) {
                        $tamrielicTime = convert_gamets2skyrim_long_date2($event['gamets']);
                    }
                    $eventsTable .= "<tr>
                        <td style='width: 50%;'>" . htmlspecialchars($event['data']) . "</td>
                        <td style='width: 25%;'>{$time->format('jS F, Y, H:i')}</td>
                        <td style='width: 25%;'>{$tamrielicTime}</td>
                    </tr>";
                }
                
                $eventsTable .= "</table>";
                
                echo render_widget('Recent Dialogue', $eventsTable, 'table');
            }

            // 3. Stats Widget
            // First check which tables exist
            $tableCheckQuery = "
                SELECT table_name 
                FROM information_schema.tables 
                WHERE table_schema = '{$schema}'
                AND table_name IN ('diarylog', 'oghma', 'eventlog', 'memory_summary', 'book', 'quests', 'conf_opts', 'books', 'currentmission')";
            
            error_log("Table Check Query: " . $tableCheckQuery);
            
            $tableCheck = fetch_widget_stats($conn, $tableCheckQuery);
            error_log("Table Check Results: " . print_r($tableCheck, true));
            
            if (!isset($tableCheck['error'])) {
                $existingTables = array_column($tableCheck, 'table_name');
                error_log("Existing Tables: " . print_r($existingTables, true));
                
                // Build the count query only for existing tables
                $countQueries = [];
                if (in_array('diarylog', $existingTables)) {
                    $countQueries[] = "(SELECT COALESCE(COUNT(*), 0) FROM {$schema}.diarylog) as diary_entries";
                }
                if (in_array('oghma', $existingTables)) {
                    $countQueries[] = "(SELECT COALESCE(COUNT(*), 0) FROM {$schema}.oghma) as oghma_entries";
                }
                if (in_array('eventlog', $existingTables)) {
                    $countQueries[] = "(SELECT COALESCE(COUNT(*), 0) FROM {$schema}.eventlog) as total_events";
                    $countQueries[] = "(SELECT COALESCE(COUNT(*) FILTER (WHERE type = 'death'), 0) FROM {$schema}.eventlog) as total_deaths";
                    $countQueries[] = "(SELECT COALESCE(COUNT(*) FILTER (WHERE type = 'itemfound'), 0) FROM {$schema}.eventlog) as items_found";
                }
                if (in_array('memory_summary', $existingTables)) {
                    $countQueries[] = "(SELECT COALESCE(COUNT(*), 0) FROM {$schema}.memory_summary) as memory_summaries";
                }
                if (in_array('book', $existingTables)) {
                    $countQueries[] = "(SELECT COALESCE(COUNT(*), 0) FROM {$schema}.book) as books_read";
                }
                if (in_array('books', $existingTables)) {
                    $countQueries[] = "(SELECT COALESCE(COUNT(DISTINCT title), 0) FROM {$schema}.books WHERE content IS NOT NULL) as books_summarized";
                }
                if (in_array('quests', $existingTables)) {
                    $countQueries[] = "(SELECT COALESCE(COUNT(*), 0) FROM {$schema}.quests) as current_quests";
                }
                
                if (!empty($countQueries)) {
                    $stats = fetch_widget_stats($conn, "SELECT " . implode(", ", $countQueries) . ", 
                        (SELECT COALESCE(COUNT(*), 0) FROM {$schema}.eventlog WHERE type = 'inputtext') as player_inputs");
                    
                    if (!isset($stats['error'])) {
                        echo render_widget('CHIM Stats', "
                            <div class='widget-stats'>
                                " . (in_array('diarylog', $existingTables) ? "
                                <div class='stat-card'>
                                    <div class='stat-value'>{$stats[0]['total_events']}</div>
                                    <div class='stat-label'>Total Events</div>
                                </div>
                                " . (in_array('oghma', $existingTables) ? "
                                <div class='stat-card'>
                                    <div class='stat-value'>{$stats[0]['oghma_entries']}</div>
                                    <div class='stat-label'>Oghma Entries</div>
                                </div>" : "") . "
                                " . (in_array('memory_summary', $existingTables) ? "
                                <div class='stat-card'>
                                    <div class='stat-value'>{$stats[0]['memory_summaries']}</div>
                                    <div class='stat-label'>Memory Summaries</div>
                                </div>" : "") . "
                                <div class='stat-card'>
                                    <div class='stat-value'>{$stats[0]['diary_entries']}</div>
                                    <div class='stat-label'>Diary Entries</div>
                                </div>" : "") . "

                                " . (in_array('eventlog', $existingTables) ? "
                                <div class='stat-card'>
                                    <div class='stat-value'>{$stats[0]['total_deaths']}</div>
                                    <div class='stat-label'>Entity Deaths</div>
                                </div>
                                <div class='stat-card'>
                                    <div class='stat-value'>{$stats[0]['items_found']}</div>
                                    <div class='stat-label'>Items Found</div>
                                </div>" : "") . "
                                " . (in_array('book', $existingTables) ? "
                                <div class='stat-card'>
                                    <div class='stat-value'>{$stats[0]['books_read']}</div>
                                    <div class='stat-label'>Books Read</div>
                                </div>" : "") . "
                                " . (in_array('books', $existingTables) ? "
                                <div class='stat-card'>
                                    <div class='stat-value'>{$stats[0]['books_summarized']}</div>
                                    <div class='stat-label'>Books Read</div>
                                </div>" : "") . "
                                                                <div class='stat-card'>
                                    <div class='stat-value'>{$stats[0]['player_inputs']}</div>
                                    <div class='stat-label'>Player Messages</div>
                                </div>
                            </div>
                        ");
                    } else {
                        error_log("Stats count error: " . print_r($stats['error'], true));
                    }
                }

                // Latest Diary Entry Widget
                $latestDiary = fetch_widget_stats($conn, "
                SELECT topic, content, people as author, localts, gamets
                FROM {$schema}.diarylog
                ORDER BY localts DESC
                LIMIT 1
                ");

                $diaryContent = "";
                if (!isset($latestDiary['error']) && !empty($latestDiary)) {
                    $time = new DateTime("@{$latestDiary[0]['localts']}");
                    $time->setTimezone(new DateTimeZone('UTC'));
                    $tamrielicTime = '';
                    if (isset($latestDiary[0]['gamets']) && $latestDiary[0]['gamets'] > 0) {
                        $tamrielicTime = convert_gamets2skyrim_long_date2($latestDiary[0]['gamets']);
                    }
                    
                    $diaryContent = "
                        <div class='diary-entry' style='background: #1a1a1a; padding: 25px; border-radius: 8px; max-width: 1200px; margin: 0 auto;'>
                            <div style='background: url(\"/HerikaServer/ui/images/paper.jpg\") center/cover; padding: 40px; border-radius: 6px; box-shadow: 0 4px 8px rgba(0,0,0,0.5);'>
                                <div style='color: #000; line-height: 1.4; font-family: SkyrimBooks_Handwritten_Bold, Arial, sans-serif !important;'>
                                    <div style='font-size: 1.1em; margin-bottom: 15px; font-family: SkyrimBooks_Handwritten_Bold, Arial, sans-serif !important;'>" . htmlspecialchars($latestDiary[0]['author']) . "</div>
                                    <div style='font-size: 1.2em; padding-top: 15px; font-family: SkyrimBooks_Handwritten_Bold, Arial, sans-serif !important;'>" . nl2br($latestDiary[0]['content']) . "</div>
                                </div>
                            </div>
                        </div>";
                } else {
                    $diaryContent = "
                        <div class='diary-entry' style='background: #1a1a1a; padding: 25px; border-radius: 8px; max-width: 1200px; margin: 0 auto; text-align: center;'>
                            <div style='color: #6c757d; font-size: 1.2em; padding: 40px 20px;'>
                                No diary entries found yet. Your adventures will be recorded here as you journey through Tamriel.
                            </div>
                        </div>";
                }
                
                echo render_widget('Latest Diary Entry', $diaryContent, 'default', ['class' => 'widget-skyrim-stats']);

                // Word Map
                $generalStats = fetch_widget_stats($conn, "
                    SELECT data
                    FROM {$schema}.eventlog 
                    WHERE type = 'chat'
                ");

                if (!isset($generalStats['error']) && !empty($generalStats)) {
                    // Process the chat messages to extract just the dialogue
                    $processedText = [];
                    foreach ($generalStats as $row) {
                        $text = $row['data'];
                        
                        // Remove context information in parentheses
                        $text = preg_replace('/\([^)]+\)/', '', $text);
                        
                        // Remove character name before colon
                        $text = preg_replace('/^[^:]+:/', '', $text);
                        
                        // Clean up the text
                        $text = trim($text);
                        
                        // Convert to lowercase
                        $text = strtolower($text);
                        
                        // Remove all punctuation except apostrophes
                        $text = preg_replace('/[^\w\s\']/', '', $text);
                        
                        // Clean up any standalone or multiple apostrophes
                        $text = preg_replace('/\s\'|\'(\s|$)|(\'+)/', ' ', $text);
                        
                        // Split into words
                        $words = preg_split('/\s+/', $text);
                        
                        // Filter out stop words and short words
                        $words = array_filter($words, function($word) {
                            $stopWords = [
                                'the', 'be', 'to', 'of', 'and', 'a', 'in', 'that', 'have', 'i', 'it', 'for', 
                                'not', 'on', 'with', 'he', 'as', 'you', 'do', 'at', 'this', 'but', 'his', 
                                'by', 'from', 'they', 'we', 'say', 'her', 'she', 'or', 'an', 'will', 'my', 
                                'one', 'all', 'would', 'there', 'their', 'what', 'so', 'up', 'out', 'if', 
                                'about', 'who', 'get', 'which', 'go', 'me', 'when', 'make', 'can', 'like', 
                                'time', 'no', 'just', 'him', 'know', 'take', 'people', 'into', 'year', 'your', 
                                'good', 'some', 'could', 'them', 'see', 'other', 'than', 'then', 'now', 'look', 
                                'only', 'come', 'its', 'over', 'think', 'also', 'back', 'after', 'use', 'two', 
                                'how', 'our', 'work', 'first', 'well', 'way', 'even', 'new', 'want', 'because', 
                                'any', 'these', 'give', 'day', 'most', 'us', 'im', 'ive', 'are', 'was', 'been',
                                'had', 'has', 'yes', 'no', 'ok', 'okay', 'oh', 'ah', 'hmm', 'uh', 'er', 'um',
                                'whats', 'thats', 'youre', 'dont', 'cant', 'wont', 'shouldnt', 'couldnt',
                                'wouldnt', 'lets', 'theres', 'heres', 'wheres', 'whos', 'nobodys', 'everybodys',
                                'talking', 'talk', 'said', 'says', 'tell', 'told', 'went', 'gone', 'coming',
                                'going', 'doing', 'done', 'being', 'having', 'getting', 'putting', 'taking',
                                'making', 'finding', 'found', 'made', 'put', 'took', 'got', 'get', 'goes',
                                'went', 'come', 'came', 'goes', 'going'
                            ];
                            return strlen($word) > 2 && !in_array($word, $stopWords);
                        });
                        
                        if (!empty($words)) {
                            $processedText = array_merge($processedText, $words);
                        }
                    }

                    // Count word frequencies
                    $wordFrequencies = array_count_values($processedText);
                    arsort($wordFrequencies);
                    
                    // Take top 100 words
                    $wordFrequencies = array_slice($wordFrequencies, 0, 100, true);
                    
                    // Convert to format needed for word cloud
                    $words = array_map(function($word, $count) {
                        return ['text' => $word, 'size' => log($count * 5) * 8 + 20, 'count' => $count];
                    }, array_keys($wordFrequencies), array_values($wordFrequencies));

                    echo render_widget('Most Used Words', "
                        <script src='https://d3js.org/d3.v7.min.js'></script>
                        <script src='https://cdn.jsdelivr.net/gh/jasondavies/d3-cloud/build/d3.layout.cloud.js'></script>
                        <div class='word-cloud-container'>
                            <div id='word-count-display' style='text-align: center; padding: 10px; margin-bottom: 20px; font-size: 24px; color: #ff00c6; height: 30px; font-weight: bold;'></div>
                            <svg id='word-cloud' style='width: 100%; height: 500px;'></svg>
                        </div>
                        <style>
                            .word-cloud-container {
                                background: #1a1a1a;
                                border-radius: 8px;
                                padding: 20px;
                                position: relative;
                            }
                            .word-cloud-text {
                                font-family: 'Arial', sans-serif;
                                cursor: pointer;
                                transition: opacity 0.3s;
                            }
                            .word-cloud-text:hover {
                                opacity: 0.7;
                            }
                        </style>
                        <script>
                            const words = " . json_encode($words) . ";
                            const display = document.getElementById('word-count-display');
                            
                            // Color scale for words based on frequency
                            const color = d3.scaleOrdinal()
                                .range(['#ff00c6', '#ff33d1', '#ff66dc', '#ff99e7', '#ffccf2']);

                            // Create the word cloud layout
                            const layout = d3.layout.cloud()
                                .size([document.getElementById('word-cloud').clientWidth, 500])
                                .words(words)
                                .padding(5)
                                .rotate(() => 0)
                                .font('Arial')
                                .fontSize(d => d.size)
                                .on('end', draw);

                            // Function to draw the word cloud
                            function draw(words) {
                                d3.select('#word-cloud')
                                    .append('g')
                                    .attr('transform', 'translate(' + layout.size()[0] / 2 + ',' + layout.size()[1] / 2 + ')')
                                    .selectAll('text')
                                    .data(words)
                                    .enter()
                                    .append('text')
                                    .style('font-size', d => d.size + 'px')
                                    .style('font-family', 'Arial')
                                    .style('fill', (d, i) => color(i % 5))
                                    .attr('class', 'word-cloud-text')
                                    .attr('text-anchor', 'middle')
                                    .attr('transform', d => 'translate(' + [d.x, d.y] + ')')
                                    .text(d => d.text)
                                    .on('mouseover', function(event, d) {
                                        display.textContent = d.text + ' [' + d.count + ']';
                                        d3.select(this).style('opacity', 0.7);
                                    })
                                    .on('mouseout', function() {
                                        display.textContent = '';
                                        d3.select(this).style('opacity', 1);
                                    });
                            }

                            // Start the layout
                            layout.start();

                            // Resize handler
                            window.addEventListener('resize', () => {
                                const svg = document.getElementById('word-cloud');
                                if (svg) {
                                    svg.innerHTML = '';
                                    layout.size([svg.clientWidth, 500]).start();
                                }
                            });
                        </script>
                    ", 'default', ['class' => 'widget-skyrim-stats']);
                }

                // Add Skyrim Stats Widget
                if (in_array('conf_opts', $existingTables)) {
                    // Debug: Log the raw query
                    $query = "
                        SELECT id, value 
                        FROM {$schema}.conf_opts 
                        WHERE id IN (
                            'Mauls', 'Werewolf Transformations', 'Days As Werewolf',
                            'Necks Bitten', 'Days As Vampire', 'Locations Discovered',
                            'Dungeons Cleared', 'Days Passed', 'Hours Slept',
                            'Hours Waited', 'Standing Stones Found', 'Gold Found',
                            'Most Gold Carried', 'Chests Looted', 'Skill Increases',
                            'Skill Books Read', 'Food Eaten', 'Training Sessions',
                            'Books Read', 'Horses Owned', 'Houses Owned',
                            'Stores Invested In', 'Barters', 'Persuasions',
                            'Bribes', 'Intimidations', 'Diseases Contracted',
                            'Dragonborn Quests Completed DB', 'Dawnguard Quests Completed DG',
                            'Quests Completed', 'Misc Objectives Completed',
                            'Main Quests Completed', 'Side Quests Completed',
                            'The Companions Quests Completed', 'College of Winterhold Quests Completed',
                            'Thieves'' Guild Quests Completed', 'The Dark Brotherhood Quests Completed',
                            'Civil War Quests Completed', 'Daedric Quests Completed',
                            'Questlines Completed', 'Bard''s College Quests Completed',
                            'Blades Quests Completed', 'Forsworn Quests Completed',
                            'Imperial Legion Quests Completed', 'Stormcloaks Quests Completed',
                            'Thieves'' Guild Special Jobs Completed', 'Dark Brotherhood Contracts Completed',
                            'Dawnguard Side Quests Completed', 'Dragonborn Side Quests Completed',
                            'Main Questline Quests Completed', 'Side Questlines Completed',
                            'Spells Learned', 'Favorite Spell', 'Favorite School',
                            'Dragon Souls Collected', 'Words of Power Learned',
                            'Words of Power Unlocked', 'Shouts Learned',
                            'Shouts Mastered', 'Times Shouted', 'Favorite Shout',
                            'Soul Gems Used', 'Souls Trapped', 'Magic Items Made',
                            'Weapons Improved', 'Weapons Made', 'Armor Improved',
                            'Armor Made', 'Potions Mixed', 'Potions Used',
                            'Poisons Mixed', 'Poisons Used', 'Ingredients Harvested',
                            'Ingredients Eaten', 'Nirnroots Found', 'Wings Plucked',
                            'Total Lifetime Bounty', 'Largest Bounty', 'Locks Picked',
                            'Pockets Picked', 'Items Pickpocketed', 'Times Jailed',
                            'Days Jailed', 'Fines Paid', 'Jail Escapes',
                            'Items Stolen', 'Assaults', 'Murders',
                            'Horses Stolen', 'Trespasses'
                        )";
                    
                    // Debug: Log connection status
                    error_log("Database connection status: " . ($conn ? "Connected" : "Not connected"));
                    if (!$conn) {
                        error_log("Connection error: " . pg_last_error());
                    }
                    
                    error_log("Skyrim Stats Query: " . $query);
                    
                    $result = pg_query($conn, $query);
                    if (!$result) {
                        error_log("Query error: " . pg_last_error($conn));
                    }
                    
                    $skyrimStats = fetch_widget_stats($conn, $query);

                    // Debug: Log the raw results
                    error_log("Skyrim Stats Raw Results: " . print_r($skyrimStats, true));
                    
                    // Debug: Log if we got any results
                    error_log("Number of results: " . count($skyrimStats));

                    if (!isset($skyrimStats['error'])) {
                        $statsContent = "<div class='skyrim-stats-grid'>";
                        
                        // Group stats into categories
                        $categories = [
                            'Combat & Transformations' => ['Mauls', 'Werewolf Transformations', 'Days As Werewolf', 'Necks Bitten', 'Days As Vampire'],
                            'Exploration' => ['Locations Discovered', 'Dungeons Cleared', 'Standing Stones Found', 'Diseases Contracted'],
                            'Time & Activities' => ['Days Passed', 'Hours Slept', 'Hours Waited'],
                            'Wealth & Items' => ['Gold Found', 'Most Gold Carried', 'Chests Looted'],
                            'Skills & Knowledge' => ['Skill Increases', 'Skill Books Read', 'Training Sessions', 'Books Read'],
                            'Property & Social' => [
                                'Assets' => [
                                    'Horses Owned',
                                    'Houses Owned',
                                    'Stores Invested In'
                                ],
                                'Interactions' => [
                                    'Barters',
                                    'Persuasions',
                                    'Bribes',
                                    'Intimidations'
                                ]
                            ],
                            'Magic & Shouts' => [
                                'Spells' => [
                                    'Spells Learned',
                                    'Favorite Spell',
                                    'Favorite School'
                                ],
                                'Dragon Shouts' => [
                                    'Dragon Souls Collected',
                                    'Words of Power Learned',
                                    'Words of Power Unlocked',
                                    'Shouts Learned',
                                    'Shouts Mastered',
                                    'Times Shouted',
                                    'Favorite Shout'
                                ]
                            ],
                            'Crafting' => [
                                'Enchanting' => [
                                    'Soul Gems Used',
                                    'Souls Trapped',
                                    'Magic Items Made'
                                ],
                                'Smithing' => [
                                    'Weapons Improved',
                                    'Weapons Made',
                                    'Armor Improved',
                                    'Armor Made'
                                ],
                                'Alchemy' => [
                                    'Potions Mixed',
                                    'Potions Used',
                                    'Poisons Mixed',
                                    'Poisons Used',
                                    'Ingredients Harvested',
                                    'Ingredients Eaten',
                                    'Nirnroots Found',
                                    'Wings Plucked'
                                ]
                            ],
                            'Crime' => [
                                'Bounties' => [
                                    'Total Lifetime Bounty',
                                    'Largest Bounty'
                                ],
                                'Theft' => [
                                    'Locks Picked',
                                    'Pockets Picked',
                                    'Items Pickpocketed',
                                    'Items Stolen',
                                    'Horses Stolen',
                                    'Trespasses'
                                ],
                                'Violence' => [
                                    'Assaults',
                                    'Murders'
                                ],
                                'Punishment' => [
                                    'Times Jailed',
                                    'Days Jailed',
                                    'Fines Paid',
                                    'Jail Escapes'
                                ]
                            ],
                            'Quests Completed' => [
                                'Base Game Quests' => [
                                    'Main Questline ',
                                    'Main Quests',
                                    'Side Quests',
                                    'Side Questlines',
                                    'Misc Objectives',
                                    'Quests',
                                    'Questlines',
                                    'Daedric Quests'
                                ],
                                'Civil War' => [
                                    'Civil War Quests',
                                    'Imperial Legion Quests',
                                    'Stormcloaks Quests'
                                ],
                                'Faction Quests' => [
                                    'The Companions Quests',
                                    'College of Winterhold Quests',
                                    'Thieves\' Guild Quests',
                                    'Thieves\' Guild Special Jobs',
                                    'The Dark Brotherhood Quests',
                                    'Dark Brotherhood Contracts',
                                    'Bard\'s College Quests',
                                    'Blades Quests',
                                    'Forsworn Quests'
                                ],
                                'DLC Quests' => [
                                    'Dragonborn Quests',
                                    'Dragonborn Side Quests',
                                    'Dawnguard Quests',
                                    'Dawnguard Side Quests'
                                ]
                            ]
                        ];

                        // Create a map of id to value for easier lookup
                        $statsMap = [];
                        foreach ($skyrimStats as $stat) {
                            $statsMap[$stat['id']] = $stat['value'];
                            // Debug: Log each stat as we process it
                            error_log("Processing stat: {$stat['id']} = {$stat['value']}");
                        }

                        // Debug: Log the stats map
                        error_log("Skyrim Stats Map: " . print_r($statsMap, true));

                        foreach ($categories as $category => $statIds) {
                            $statsContent .= "<div class='stats-category'>
                                <h4>{$category}</h4>
                                <div class='stats-list'>";
                            
                            if (is_array($statIds)) {
                                foreach ($statIds as $subCategory => $subStats) {
                                    if (is_array($subStats)) {
                                        // This is a nested category
                                        $statsContent .= "<div class='sub-category'>
                                            <h5>{$subCategory}</h5>";
                                        foreach ($subStats as $statId) {
                                            if (isset($statsMap[$statId])) {
                                                $value = $statsMap[$statId];
                                            } else {
                                                $value = '0';
                                            }
                                            $displayName = $statId;
                                            $statsContent .= "<div class='stat-item'>
                                                <span class='stat-label'>{$displayName}</span>
                                                <span class='stat-value'>{$value}</span>
                                            </div>";
                                        }
                                        $statsContent .= "</div>";
                                    } else {
                                        // This is a direct stat
                                        if (isset($statsMap[$subStats])) {
                                            $value = $statsMap[$subStats];
                                        } else {
                                            $value = '0';
                                        }
                                        $displayName = $subStats;
                                        $statsContent .= "<div class='stat-item'>
                                            <span class='stat-label'>{$displayName}</span>
                                            <span class='stat-value'>{$value}</span>
                                        </div>";
                                    }
                                }
                            } else {
                                // This is a direct stat
                                if (isset($statsMap[$statIds])) {
                                    $value = $statsMap[$statIds];
                                } else {
                                    $value = '0';
                                }
                                $displayName = $statIds;
                                $statsContent .= "<div class='stat-item'>
                                    <span class='stat-label'>{$displayName}</span>
                                    <span class='stat-value'>{$value}</span>
                                </div>";
                            }
                            
                            $statsContent .= "</div></div>";
                        }
                        
                        $statsContent .= "</div>";
                        
                        echo render_widget('Skyrim Stats', $statsContent, 'default', ['class' => 'widget-skyrim-stats']);
                    } else {
                        error_log("Skyrim Stats error: " . print_r($skyrimStats['error'], true));
                    }
                }
            } else {
                error_log("Table check error: " . print_r($tableCheck['error'], true));
            }
            ?>
        </div>

        <?php
        // **Close Database Connection**
        pg_close($conn);
        ?>
    </main>
</body>
<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
</html>