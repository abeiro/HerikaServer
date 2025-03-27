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
<!-- Ensure main.css is loaded after any reboot.css -->
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
            color: #007bff;
        }

        .stat-label {
            font-size: 0.9em;
            color: #6c757d;
            margin-top: 5px;
        }

        /* Quest list styles */
        .quest-list {
            margin-top: 20px;
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
    </style>
</head>
<body>
    <main class="container">
        <h1>📊 Dwemer Dashboard</h1>

        <div class="dashboard-container">
            <?php
            // Example widgets - these can be moved to separate files later
            
            // 1. System Status Widget
            $systemStatus = fetch_widget_stats($conn, "
                SELECT 
                    COUNT(*) FILTER (WHERE type = 'chat') as total_context_events,
                    MAX(localts) as last_event,
                    MAX(gamets) as last_gamets
                FROM {$schema}.eventlog
            ");
            
            if (!isset($systemStatus['error'])) {
                $lastEvent = new DateTime("@{$systemStatus[0]['last_event']}");
                $lastEvent->setTimezone(new DateTimeZone('UTC'));
                
                // Format last played time in a more readable way
                $lastPlayed = $lastEvent->format('M j, Y g:i A');
                
                // Get in-game time using the last gamets
                $inGameTime = '';
                if (isset($systemStatus[0]['last_gamets']) && $systemStatus[0]['last_gamets'] > 0) {
                    $inGameTime = convert_gamets2skyrim_long_date2($systemStatus[0]['last_gamets']);
                }

                // Get quest information
                $questTable = fetch_widget_stats($conn, "
                    SELECT name as quest_name, briefing
                    FROM {$schema}.quests
                    ORDER BY name
                ");
                
                $questsContent = "";
                if (!isset($questTable['error']) && !empty($questTable)) {
                    $questsContent = "<div class='quest-list'>
                        <h4>Current Quests</h4>
                        <table class='widget-table'>
                            <tr><th>Quest Name</th><th>Briefing</th></tr>";
                    
                    foreach ($questTable as $quest) {
                        $questsContent .= "<tr>
                            <td>" . htmlspecialchars($quest['quest_name']) . "</td>
                            <td>" . htmlspecialchars($quest['briefing']) . "</td>
                        </tr>";
                    }
                    
                    $questsContent .= "</table></div>";
                }
                
                echo render_widget('Playthrough Stats', "
                    <div class='widget-stats'>
                        <div class='stat-card'>
                            <div class='stat-value'>{$lastPlayed}</div>
                            <div class='stat-label'>Last Played</div>
                        </div>
                        <div class='stat-card'>
                            <div class='stat-value'>{$inGameTime}</div>
                            <div class='stat-label'> Current In-Game Time</div>
                        </div>
                    </div>
                    {$questsContent}
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
                    <tr><th>Dialogue</th><th>Time</th><th><a href='https://en.uesp.net/wiki/Lore:Calendar' target='_blank'>Tamrielic Time</a></th></tr>";
                
                foreach ($recentEvents as $event) {
                    $time = new DateTime("@{$event['localts']}");
                    $time->setTimezone(new DateTimeZone('UTC'));
                    $tamrielicTime = '';
                    if (isset($event['gamets']) && $event['gamets'] > 0) {
                        $tamrielicTime = convert_gamets2skyrim_long_date2($event['gamets']);
                    }
                    $eventsTable .= "<tr>
                        <td>" . htmlspecialchars($event['data']) . "</td>
                        <td>{$time->format('H:i:s')}</td>
                        <td>{$tamrielicTime}</td>
                    </tr>";
                }
                
                $eventsTable .= "</table>";
                
                echo render_widget('Recent Dialogue', $eventsTable, 'table');
            }

            // 3. Stats Widget
            // First check which tables exist
            $tableCheck = fetch_widget_stats($conn, "
                SELECT table_name 
                FROM information_schema.tables 
                WHERE table_schema = '{$schema}'
                AND table_name IN ('diarylog', 'oghma', 'eventlog', 'memory_summary', 'book', 'quests')
            ");
            
            if (!isset($tableCheck['error'])) {
                $existingTables = array_column($tableCheck, 'table_name');
                
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
                }
                if (in_array('memory_summary', $existingTables)) {
                    $countQueries[] = "(SELECT COALESCE(COUNT(*), 0) FROM {$schema}.memory_summary) as memory_summaries";
                }
                if (in_array('book', $existingTables)) {
                    $countQueries[] = "(SELECT COALESCE(COUNT(*), 0) FROM {$schema}.book) as books_read";
                }
                if (in_array('quests', $existingTables)) {
                    $countQueries[] = "(SELECT COALESCE(COUNT(*), 0) FROM {$schema}.quests) as current_quests";
                }
                
                // Add location stats from eventlog
                if (in_array('eventlog', $existingTables)) {
                    $countQueries[] = "(SELECT COALESCE(COUNT(DISTINCT location), 0) FROM {$schema}.eventlog) as total_locations";
                }
                
                if (!empty($countQueries)) {
                    $stats = fetch_widget_stats($conn, "SELECT " . implode(", ", $countQueries));
                    
                    if (!isset($stats['error'])) {
                        echo render_widget('CHIM Stats', "
                            <div class='widget-stats'>
                                " . (in_array('diarylog', $existingTables) ? "
                                <div class='stat-card'>
                                    <div class='stat-value'>{$stats[0]['diary_entries']}</div>
                                    <div class='stat-label'>Diary Entries</div>
                                </div>" : "") . "
                                " . (in_array('oghma', $existingTables) ? "
                                <div class='stat-card'>
                                    <div class='stat-value'>{$stats[0]['oghma_entries']}</div>
                                    <div class='stat-label'>Oghma Entries</div>
                                </div>" : "") . "
                                " . (in_array('eventlog', $existingTables) ? "
                                <div class='stat-card'>
                                    <div class='stat-value'>{$stats[0]['total_events']}</div>
                                    <div class='stat-label'>Total Events</div>
                                </div>
                                <div class='stat-card'>
                                    <div class='stat-value'>{$stats[0]['total_deaths']}</div>
                                    <div class='stat-label'>Entity Deaths</div>
                                </div>" : "") . "
                                " . (in_array('memory_summary', $existingTables) ? "
                                <div class='stat-card'>
                                    <div class='stat-value'>{$stats[0]['memory_summaries']}</div>
                                    <div class='stat-label'>Memory Summaries</div>
                                </div>" : "") . "
                                " . (in_array('book', $existingTables) ? "
                                <div class='stat-card'>
                                    <div class='stat-value'>{$stats[0]['books_read']}</div>
                                    <div class='stat-label'>Books Read</div>
                                </div>" : "") . "
                                " . (in_array('quests', $existingTables) ? "
                                <div class='stat-card'>
                                    <div class='stat-value'>{$stats[0]['current_quests']}</div>
                                    <div class='stat-label'>Current Quests</div>
                                </div>" : "") . "
                                " . (in_array('eventlog', $existingTables) ? "
                                <div class='stat-card'>
                                    <div class='stat-value'>{$stats[0]['total_locations']}</div>
                                    <div class='stat-label'>Locations Visited</div>
                                </div>" : "") . "
                            </div>
                        ");
                    } else {
                        error_log("Stats count error: " . print_r($stats['error'], true));
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