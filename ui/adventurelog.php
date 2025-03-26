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

$TITLE = "📆CHIM Adventure Log";

ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");
?>
<!-- Ensure main.css is loaded after any reboot.css -->
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<?php

$debugPaneLink = false;
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/navbar.php");

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
 * Function to process a single event row into formatted data.
 *
 * @param array $row The associative array representing a database row.
 * @param bool $for_csv Indicates whether the output is for CSV (true) or HTML (false).
 * @return array|null An associative array with keys: Context, Nearby People, Location & Tamrielic Time, Time(UTC).
 */
function process_event_row($row, $for_csv = false) {
    // **Format 'localts' into a readable UTC date format**
    $timestamp = (int)$row['localts'];

    if ($timestamp > 0) {
        // Using DateTime for better control
        $dt = new DateTime("@$timestamp"); // The @ symbol tells DateTime to interpret as Unix timestamp
        $dt->setTimezone(new DateTimeZone('UTC'));
        $timeDisplay = $dt->format('d-m-Y H:i:s');
    } else {
        $timeDisplay = $row['localts'];
    }

    // Add debug logging for gamets conversion
    if (isset($row['gamets']) && $row['gamets'] > 0) {
        error_log("Debug - Raw gamets: " . $row['gamets']);
        error_log("Debug - Converted time: " . convert_gamets2skyrim_long_date2($row['gamets']));
        error_log("Debug - Raw location: " . $row['location']);
    }

    // **Step 1: Check the 'type' column**
    $type = $row['type'];

    // Define the allowed types
    $allowedTypes = ['im_alive', 'chat', 'infoaction','rpg_word', 'rpg_lvlup', 'rechat', 'quest', 'itemfound', 'inputtext', 'goodnight', 'goodmorning', 'ginputtext', 'death', 'combatendmighty', 'combatend'];

    // If the type is not in the allowed list, return null to skip
    if (!in_array($type, $allowedTypes)) {
        return null;
    }

    // **Raw values**
    $rawData = $row['data'];
    $rawPeople = $row['people'];
    $rawLocation = $row['location'];
    $rawLocalts = $row['localts']; // Original localts timestamp

    // Step 1: Clean the raw location by removing surrounding parentheses
    $cleanLocation = trim($rawLocation, "()");

    // Step 2: Initialize the variable to hold the combined display
    $locationDisplay = '';

    // Step 3: Extract the Date and Time
    // Updated regex to match 'current date' followed by multiple date components
    $datePattern = '/current date\s*([^,]+),\s*([^,]+),\s*([^,]+),\s*([^,]+)/i';
    if (preg_match($datePattern, $cleanLocation, $dateMatch)) {
        // Combine the captured groups to form the complete date string
        // $dateMatch[1] = Loredas
        // $dateMatch[2] = 11:15 PM
        // $dateMatch[3] = 14th of First Seed
        // $dateMatch[4] = 4E 202
        $dateDisplay = trim("{$dateMatch[1]}, {$dateMatch[2]}, {$dateMatch[3]}, {$dateMatch[4]}");
    } else {
        // Handle cases where date/time information is missing
        $dateDisplay = 'Unknown Date';
    }

    // Step 4: Extract the Location and Combine with Date/Time
    // Updated regex to match 'Context new location:'
    $locationPattern = '/Context new location:\s*([^,]+)/i';
    if (preg_match($locationPattern, $cleanLocation, $locationMatch)) {
        // Successfully matched 'Context new location'
        $location = trim($locationMatch[1]);
        $locationDisplay = "{$location} - {$dateDisplay}";
    } else {
        // Fallback to 'Hold' if 'Context new location' is not found
        $holdPattern = '/Hold:\s*([^,]+)/i';
        if (preg_match($holdPattern, $cleanLocation, $holdMatch)) {
            $hold = trim($holdMatch[1]);
            $locationDisplay = "{$hold} - {$dateDisplay}";
        } else {
            // Fallback to the entire cleanLocation if both extractions fail
            $locationDisplay = "{$cleanLocation} - {$dateDisplay}";
        }
    }

    // **Transform people**
    // Remove leading/trailing pipes and spaces, then split by '|'
    $cleanPeople = trim($rawPeople, "|() ");
    $peopleList = array_filter(explode("|", $cleanPeople), 'strlen');
    $people = implode(", ", $peopleList);

    // Remove the '(Context location: ...)' substring
    $data = preg_replace('/\(Context location:[^)]+\)/i', '', $rawData);
    $data = trim($data);

    if (!$for_csv) {
        // **Escape HTML for safety only if not exporting to CSV**
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        $people = htmlspecialchars($people, ENT_QUOTES, 'UTF-8');
        $locationDisplay = htmlspecialchars($locationDisplay, ENT_QUOTES, 'UTF-8');
        $timeDisplay = htmlspecialchars($timeDisplay, ENT_QUOTES, 'UTF-8');
    }

    // Return the processed data
    return [
        'Context' => $data,
        'Nearby People' => $people,
        'Location & Tamrielic Time' => $locationDisplay,
        'Time(UTC)' => $timeDisplay
    ];
}

// Function to handle CSV export
function handle_csv_export($conn, $schema) {
    if (isset($_GET['export'])) {
        $exportType = $_GET['export'];

        if (($exportType === 'csv' && isset($_GET['date'])) || $exportType === 'all_csv') {
            // Clear any existing output buffer
            while (ob_get_level()) {
                ob_end_clean();
            }

            $is_specific_date = ($exportType === 'csv' && isset($_GET['date']));

            if ($is_specific_date) {
                // Export CSV for the selected date
                $selectedDate = $_GET['date'];

                // Validate the selected date format (YYYY-MM-DD)
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
                    // Invalid date format
                    header("HTTP/1.1 400 Bad Request");
                    echo "Invalid date format.";
                    exit;
                }

                // Calculate the start and end timestamps for the selected day in UTC
                $dtSelected = new DateTime($selectedDate . ' 00:00:00', new DateTimeZone('UTC'));
                $startOfDay = $dtSelected->getTimestamp();
                $dtSelectedEnd = clone $dtSelected;
                $dtSelectedEnd->modify('+1 day')->modify('-1 second');
                $endOfDay = $dtSelectedEnd->getTimestamp();

                // Prepare the SQL query with explicit casting to double precision
                $query = "
                    SELECT type, data, people, location, localts, gamets
                    FROM {$schema}.eventlog
                    WHERE type IN ('im_alive', 'chat','infoaction', 'rpg_word', 'rpg_lvlup', 'rechat', 'quest', 'itemfound', 'inputtext', 'goodnight', 'goodmorning', 'ginputtext', 'death', 'combatendmighty', 'combatend')
                    AND to_timestamp(localts::double precision) BETWEEN to_timestamp($startOfDay) AND to_timestamp($endOfDay)
                    ORDER BY localts ASC
                ";
            } elseif ($exportType === 'all_csv') {
                // Export CSV for all data without date filtering
                $query = "
                    SELECT type, data, people, location, localts, gamets
                    FROM {$schema}.eventlog
                    WHERE type IN ('im_alive', 'chat','infoaction', 'rpg_word', 'rpg_lvlup', 'rechat', 'quest', 'itemfound', 'inputtext', 'goodnight', 'goodmorning', 'ginputtext', 'death', 'combatendmighty', 'combatend')
                    ORDER BY localts ASC
                ";
            }

            $result = pg_query($conn, $query);

            if (!$result) {
                header("HTTP/1.1 500 Internal Server Error");
                echo "Error fetching data: " . pg_last_error($conn);
                exit;
            }

            // Set headers to prompt file download
            header('Content-Type: text/csv; charset=utf-8');
            if ($is_specific_date) {
                header('Content-Disposition: attachment; filename=adventure_log_' . $selectedDate . '.csv');
            } else {
                header('Content-Disposition: attachment; filename=adventure_log_full.csv');
            }

            // Add BOM for Excel compatibility
            fprintf($output = fopen('php://output', 'w'), chr(0xEF).chr(0xBB).chr(0xBF));

            // Open the output stream
            $output = fopen('php://output', 'w');

            // Output the column headings matching the table
            fputcsv($output, ['Context', 'Nearby People', 'Location & Tamrielic Time', 'Time(UTC)']);

            // Initialize previous location for tracking changes
            $previousLocation = null;

            // Fetch and process each row, then write to the CSV
            while ($row = pg_fetch_assoc($result)) {
                $processed_row = process_event_row($row, true); // true indicates CSV context
                if ($processed_row !== null) { // Only include allowed types
                    // Check for location change
                    if ($previousLocation !== null && $previousLocation !== $processed_row['Location & Tamrielic Time']) {
                        // Extract just the location name without date/time
                        $locationPattern = '/Context new location:\s*([^,]+)/i';
                        $cleanLocation = trim($row['location'], "()");
                        if (preg_match($locationPattern, $cleanLocation, $locationMatch)) {
                            $locationName = trim($locationMatch[1]);
                        } else {
                            $holdPattern = '/Hold:\s*([^,]+)/i';
                            if (preg_match($holdPattern, $cleanLocation, $holdMatch)) {
                                $locationName = trim($holdMatch[1]);
                            } else {
                                $locationName = $cleanLocation;
                            }
                        }
                        // Write location change as a special row
                        fputcsv($output, ['', '', 'Location Change: ' . $locationName, '']);
                    }
                    
                    // Update previous location
                    $previousLocation = $processed_row['Location & Tamrielic Time'];

                    // Write the actual event row
                    fputcsv($output, [
                        $processed_row['Context'],
                        $processed_row['Nearby People'],
                        $processed_row['Location & Tamrielic Time'],
                        $processed_row['Time(UTC)']
                    ]);
                }
            }

            fclose($output);
            exit; // Terminate the script after exporting CSV
        }
    }
}

// Handle CSV export if requested - move this before any HTML output
handle_csv_export($conn, $schema);

// Start output buffering after CSV handling
ob_start();

// Determine the month and year to display
$month = isset($_GET['month']) && isset($_GET['year']) 
    ? sanitize_int($_GET['month'], date('n')) 
    : date('n');
$year = isset($_GET['month']) && isset($_GET['year']) 
    ? sanitize_int($_GET['year'], date('Y')) 
    : date('Y');

// Validate month and year
$month = ($month >= 1 && $month <= 12) ? $month : date('n');
$year = ($year >= 1970 && $year <= 2100) ? $year : date('Y');

// Create DateTime objects in UTC
$dtStartOfMonth = new DateTime("{$year}-{$month}-01 00:00:00", new DateTimeZone('UTC'));
$startOfMonth = $dtStartOfMonth->getTimestamp();
$dtEndOfMonth = clone $dtStartOfMonth;
$dtEndOfMonth->modify('+1 month')->modify('-1 second');
$endOfMonth = $dtEndOfMonth->getTimestamp();

$allEventDates = [];

// Prepare the SQL query with explicit casting to double precision
$allDatesQuery = "
    SELECT DISTINCT to_char(to_timestamp(localts::double precision) AT TIME ZONE 'UTC', 'YYYY-MM-DD') as event_date
    FROM {$schema}.eventlog
    WHERE type IN ('im_alive', 'chat', 'infoaction', 'rpg_word', 'rpg_lvlup', 'rechat', 'quest', 'itemfound', 'inputtext', 'goodnight', 'goodmorning', 'ginputtext', 'death', 'combatendmighty', 'combatend')
    AND to_timestamp(localts::double precision) BETWEEN to_timestamp($startOfMonth) AND to_timestamp($endOfMonth)
    ORDER BY event_date ASC
";

$allDatesResult = pg_query($conn, $allDatesQuery);

if ($allDatesResult) {
    while ($dateRow = pg_fetch_assoc($allDatesResult)) {
        $allEventDates[] = $dateRow['event_date'];
    }
} else {
    // Handle query error
    echo "<div class='message'>Error fetching event dates: " . pg_last_error($conn) . "</div>";
}

/**
 * Function to render a calendar for a given month and year, highlighting dates with events.
 *
 * @param int $month The month for the calendar (1-12).
 * @param int $year The year for the calendar (e.g., 2024).
 * @param array $eventDates Array of dates (YYYY-MM-DD) that have events.
 * @return string HTML string representing the calendar.
 */
function renderCalendar($month, $year, $eventDates) {
    // Days of the week
    $daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    // First day of the month
    $firstDayTimestamp = strtotime("$year-$month-01 UTC");
    $firstDayOfWeek = date('w', $firstDayTimestamp); // 0 (for Sunday) through 6 (for Saturday)

    // Number of days in the month
    $daysInMonth = date('t', $firstDayTimestamp);

    // Start building the HTML table
    $calendar = "<table class='calendar'>";

    // Table Header for Days of the Week
    $calendar .= "<tr>";
    foreach ($daysOfWeek as $day) {
        $calendar .= "<th>{$day}</th>";
    }
    $calendar .= "</tr><tr>";

    // Empty cells before the first day
    if ($firstDayOfWeek > 0) {
        for ($i = 0; $i < $firstDayOfWeek; $i++) {
            $calendar .= "<td></td>";
        }
    }

    // Populate the days of the month
    for ($day = 1; $day <= $daysInMonth; $day++) {
        // Current date in YYYY-MM-DD format
        $currentDate = sprintf("%04d-%02d-%02d", $year, $month, $day);

        // Check if the current date has an event
        $hasEvent = in_array($currentDate, $eventDates);

        // Add a CSS class if there's an event
        $class = $hasEvent ? "has-event" : "";

        // Link to view events for the selected date
        $link = "<a href='?date={$currentDate}&month={$month}&year={$year}'>{$day}</a>";

        // Highlight the day if it has an event
        $calendar .= "<td class='{$class}'>{$link}</td>";

        // If the current day is Saturday, start a new row
        if ((($day + $firstDayOfWeek) % 7) == 0 && $day != $daysInMonth) {
            $calendar .= "</tr><tr>";
        }
    }

    // Empty cells after the last day
    $lastDayOfWeek = (date('w', strtotime("$year-$month-$daysInMonth UTC")));
    if ($lastDayOfWeek < 6) {
        for ($i = $lastDayOfWeek + 1; $i <= 6; $i++) {
            $calendar .= "<td></td>";
        }
    }

    $calendar .= "</tr>";
    $calendar .= "</table>";

    return $calendar;
}

// Get the selected date from the URL parameter, default to today if not set
if (isset($_GET['date'])) {
    $selectedDate = $_GET['date'];

    // Validate the selected date format (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
        $selectedDate = date('Y-m-d'); // Fallback to today if invalid
    }
} else {
    $selectedDate = date('Y-m-d');
}

// Create DateTime objects in UTC for the selected day
$dtSelected = new DateTime($selectedDate . ' 00:00:00', new DateTimeZone('UTC'));
$startOfDay = $dtSelected->getTimestamp();
$dtSelectedEnd = clone $dtSelected;
$dtSelectedEnd->modify('+1 day')->modify('-1 second');
$endOfDay = $dtSelectedEnd->getTimestamp();

// Modify the SQL query to fetch records for the selected day with explicit casting
$query = "
    SELECT type, data, people, location, localts, gamets
    FROM {$schema}.eventlog
    WHERE type IN ('im_alive', 'chat', 'infoaction', 'rpg_word', 'rpg_lvlup', 'rechat', 'quest', 'itemfound', 'inputtext', 'goodnight', 'goodmorning', 'ginputtext', 'death', 'combatendmighty', 'combatend')
    AND to_timestamp(localts::double precision) BETWEEN to_timestamp($startOfDay) AND to_timestamp($endOfDay)
    ORDER BY localts ASC
";

$result = pg_query($conn, $query);

if (!$result) {
    echo "<div class='message'>Query error: " . pg_last_error($conn) . "</div>";
    exit;
}

// Add debug logging for the first row
$firstRow = pg_fetch_assoc($result);
if ($firstRow) {
    error_log("Debug - First row gamets: " . $firstRow['gamets']);
    error_log("Debug - First row location: " . $firstRow['location']);
    pg_result_seek($result, 0); // Reset the result pointer
}
?> 

<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="<?php echo $webRoot; ?>/ui/images/favicon.ico">
    <title>📆CHIM Adventure Log</title>
    <style>
        /* Adventure Log specific styles */
        .calendar {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .calendar th, .calendar td {
            border: 1px solid #555555;
            padding: 10px;
            text-align: center;
            vertical-align: middle;
            position: relative;
        }

        .calendar th {
            background-color: #3a3a3a;
            color: #f8f9fa;
        }

        .calendar td.has-event {
            color: #ffffff;
        }

        .calendar td a {
            color: inherit;
            text-decoration: none;
            display: block;
            width: 100%;
            height: 100%;
            text-align: center;
            padding: 5px;
        }

        .calendar td.has-event a {
            background-color: #007bff;
            color: white;
            border: 2px solid white;
            border-radius: 5px;
            box-shadow: 0px 2px 4px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease-in-out;
        }

        .calendar td.has-event a:hover {
            background-color: #0056b3;
            color: #ffcc00;
        }

        .calendar-navigation {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 20px 0;
            gap: 10px;
        }

        .calendar-navigation span {
            padding: 0 15px;
            color: #f8f9fa;
            font-size: 1.5em;
        }

        /* CSV Buttons Container */
        .csv-buttons {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 10px;
            gap: 10px;
        }

        /* Event table specific styles */
        .event-table {
            width: 100%;
            margin-top: 20px;
        }

        .event-table th {
            background-color: #3a3a3a;
            color: #f8f9fa;
            padding: 12px;
        }

        .event-table td {
            padding: 12px;
            word-wrap: break-word;
        }

        .event-table tr:nth-child(even) {
            background-color: #3a3a3a;
        }

        /* Column widths for event table */
        .col-context { width: 50%; }
        .col-people { width: 20%; }
        .col-gamets { width: 15%; }
        .col-time { width: 15%; }

        /* Location change row styles */
        .location-change-row {
            background-color: #2c3e50 !important;
            color: #ecf0f1;
            font-weight: bold;
            text-align: center;
            padding: 8px;
        }

        .location-change-row td {
            padding: 8px;
            border-top: 2px solid #34495e;
            border-bottom: 2px solid #34495e;
        }

        /* Main container padding */
        main.container {
            padding-bottom: 40px; /* Reduced space for footer */
            padding-left: 10px;
            max-width: 1600px;
        }
    </style>
</head>
<body>
    <main class="container">
        <h1>📆CHIM Adventure Log</h1>
        <h2>All time and dates are in UTC. Tamrielic Time may be inconsistent.</h2>
        <h3>This is directly connected to the Event Log. It's just a nicer way to view it.</h3>

        <?php
        // Modified renderHeader function to use btn-save class
        function renderHeader() {
            echo "<div class='csv-buttons'>";
            
            $currentCsvParams = [];
            if (isset($_GET['date'])) {
                $currentCsvParams['date'] = $_GET['date'];
            }
            $currentCsvParams['export'] = 'csv';
            if (isset($_GET['month'])) {
                $currentCsvParams['month'] = $_GET['month'];
            }
            if (isset($_GET['year'])) {
                $currentCsvParams['year'] = $_GET['year'];
            }
            $currentCsvQuery = http_build_query($currentCsvParams);

            // Form for current date download
            echo "<form method='get' style='display: inline;'>";
            foreach ($currentCsvParams as $key => $value) {
                echo "<input type='hidden' name='" . htmlspecialchars($key) . "' value='" . htmlspecialchars($value) . "'>";
            }
            echo "<button type='submit' class='btn-base btn-save'>Download Current Date</button>";
            echo "</form>";

            $allCsvParams = ['export' => 'all_csv'];
            if (isset($_GET['month'])) {
                $allCsvParams['month'] = $_GET['month'];
            }
            if (isset($_GET['year'])) {
                $allCsvParams['year'] = $_GET['year'];
            }
            $allCsvQuery = http_build_query($allCsvParams);

            // Form for all data download
            echo "<form method='get' style='display: inline;'>";
            foreach ($allCsvParams as $key => $value) {
                echo "<input type='hidden' name='" . htmlspecialchars($key) . "' value='" . htmlspecialchars($value) . "'>";
            }
            echo "<button type='submit' class='btn-base btn-save'>Download Entire Adventure Log</button>";
            echo "</form>";

            echo "</div>";
        }

        // Render Combined CSV Download Buttons at the Top
        renderHeader();
        ?>

        <!-- Calendar Navigation -->
        <div class="calendar-navigation">
            <?php
            // Calculate previous and next month and year
            $prevMonth = $month - 1;
            $prevYear = $year;
            if ($prevMonth < 1) {
                $prevMonth = 12;
                $prevYear--;
            }

            $nextMonth = $month + 1;
            $nextYear = $year;
            if ($nextMonth > 12) {
                $nextMonth = 1;
                $nextYear++;
            }

            // Link to previous month with btn-primary class
            echo "<a href='?month={$prevMonth}&year={$prevYear}' class='btn-primary'>&laquo; Previous Month</a>";

            // Display current month and year
            $monthName = date('F', strtotime("$year-$month-01 UTC"));
            echo "<span><b>{$monthName} {$year}</b></span>";

            // Link to next month with btn-primary class
            echo "<a href='?month={$nextMonth}&year={$nextYear}' class='btn-primary'>Next Month &raquo;</a>";
            ?>
        </div>

        <!-- Render the Calendar -->
        <?php
        echo renderCalendar($month, $year, $allEventDates);
        ?>

        <!-- Event Table -->
        <table class="event-table">
            <colgroup>
                <col class="col-context">
                <col class="col-people">
                <col class="col-gamets">
                <col class="col-time">
            </colgroup>
            <tr>
                <th>Context</th>
                <th>Nearby People</th>
                <th><a href="https://en.uesp.net/wiki/Lore:Calendar" target="_blank" style="color: yellow;">Tamrielic Time</a></th>
                <th>Time (UTC)</th>
            </tr>
            <?php
            // Reset the result pointer to the beginning for table rendering
            pg_result_seek($result, 0);

            // Initialize previous location
            $previousLocation = null;

            // Get the first row to check initial location
            $firstRow = pg_fetch_assoc($result);
            if ($firstRow) {
                $firstProcessedRow = process_event_row($firstRow, false);
                if ($firstProcessedRow !== null) {
                    // Extract just the location name without date/time
                    $locationPattern = '/Context new location:\s*([^,]+)/i';
                    $cleanLocation = trim($firstRow['location'], "()");
                    if (preg_match($locationPattern, $cleanLocation, $locationMatch)) {
                        $initialLocation = trim($locationMatch[1]);
                    } else {
                        $holdPattern = '/Hold:\s*([^,]+)/i';
                        if (preg_match($holdPattern, $cleanLocation, $holdMatch)) {
                            $initialLocation = trim($holdMatch[1]);
                        } else {
                            $initialLocation = $cleanLocation;
                        }
                    }
                    echo "<tr class='location-change-row'><td colspan='4'>Current Location: {$initialLocation}</td></tr>";
                }
                // Reset the result pointer again for the main loop
                pg_result_seek($result, 0);
            }

            // Fetch and display each row in the table
            while ($row = pg_fetch_assoc($result)) {
                $processed_row = process_event_row($row, false); // false indicates HTML context
                if ($processed_row === null) {
                    continue; // Skip rows with types not in the allowed list
                }

                // Extract processed data
                $data = $processed_row['Context'];
                $people = $processed_row['Nearby People'];
                $location = $processed_row['Location & Tamrielic Time'];
                $timeDisplay = $processed_row['Time(UTC)'];
                
                // Check for location change
                if ($previousLocation !== null && $previousLocation !== $location) {
                    // Extract just the location name without date/time for the divider
                    $locationPattern = '/Context new location:\s*([^,]+)/i';
                    $cleanLocation = trim($row['location'], "()");
                    if (preg_match($locationPattern, $cleanLocation, $locationMatch)) {
                        $locationName = trim($locationMatch[1]);
                    } else {
                        $holdPattern = '/Hold:\s*([^,]+)/i';
                        if (preg_match($holdPattern, $cleanLocation, $holdMatch)) {
                            $locationName = trim($holdMatch[1]);
                        } else {
                            $locationName = $cleanLocation;
                        }
                    }
                    // Output location change row with simplified location
                    echo "<tr class='location-change-row'><td colspan='4'>Location Change: {$locationName}</td></tr>";
                }
                
                // Update previous location
                $previousLocation = $location;
                
                // Convert timestamp to game time
                $gameTimeDisplay = "";
                if (isset($row['gamets']) && $row['gamets'] > 0) {
                    $gameTimeDisplay = convert_gamets2skyrim_long_date2($row['gamets']);
                }

                // **Output the table row**
                echo "<tr><td>{$data}</td><td>{$people}</td><td>{$gameTimeDisplay}</td><td>{$timeDisplay}</td></tr>";
            }
            ?>
        </table>

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