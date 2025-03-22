<?php 
session_start();

// Define base paths if not already defined
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(dirname(dirname(__DIR__))));
}
if (!defined('UI_PATH')) {
    define('UI_PATH', dirname(dirname(__DIR__)));
}

// Get the relative web path from document root to our application if not already defined
if (!isset($webRoot)) {
    $scriptPath = $_SERVER['SCRIPT_NAME'];
    $webRoot = dirname(dirname(dirname($scriptPath))); // Go up three levels from the script location
    if ($webRoot == '/') $webRoot = '';
    $webRoot = rtrim($webRoot, '/');
}

require_once(UI_PATH.DIRECTORY_SEPARATOR."profile_loader.php");

$TITLE = "📆CHIM Adventure Log";

ob_start();

include(UI_PATH.DIRECTORY_SEPARATOR."tmpl".DIRECTORY_SEPARATOR."head.html");

$debugPaneLink = false;
include(UI_PATH.DIRECTORY_SEPARATOR."tmpl".DIRECTORY_SEPARATOR."navbar.php");

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
        $timeDisplay = $dt->format('H:i:s - d-m-Y');
    } else {
        $timeDisplay = $row['localts'];
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
                    SELECT type, data, people, location, localts
                    FROM {$schema}.eventlog
                    WHERE type IN ('im_alive', 'chat','infoaction', 'rpg_word', 'rpg_lvlup', 'rechat', 'quest', 'itemfound', 'inputtext', 'goodnight', 'goodmorning', 'ginputtext', 'death', 'combatendmighty', 'combatend')
                    AND to_timestamp(localts::double precision) BETWEEN to_timestamp($startOfDay) AND to_timestamp($endOfDay)
                    ORDER BY localts ASC
                ";
            } elseif ($exportType === 'all_csv') {
                // Export CSV for all data without date filtering

                // Prepare the SQL query without date filters
                $query = "
                    SELECT type, data, people, location, localts
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

            // Fetch and process each row, then write to the CSV
            while ($row = pg_fetch_assoc($result)) {
                $processed_row = process_event_row($row, true); // true indicates CSV context
                if ($processed_row !== null) { // Only include allowed types
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

// Handle CSV export if requested
handle_csv_export($conn, $schema);

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

// Modified renderHeader function to remove day navigation and date selection
function renderHeader() {
    // Start the header container
    echo "<div class='csv-buttons'>";

    // Build the current query parameters for current date CSV download
    $currentCsvParams = [];
    if (isset($_GET['date'])) {
        $currentCsvParams['date'] = $_GET['date'];
    }
    $currentCsvParams['export'] = 'csv';
    // Preserve month and year if they exist
    if (isset($_GET['month'])) {
        $currentCsvParams['month'] = $_GET['month'];
    }
    if (isset($_GET['year'])) {
        $currentCsvParams['year'] = $_GET['year'];
    }
    $currentCsvQuery = http_build_query($currentCsvParams);
    echo "<a href='?" . htmlspecialchars($currentCsvQuery) . "' class='button'>Download Current Date</a>";

    // Build the current query parameters for all data CSV download
    $allCsvParams = ['export' => 'all_csv'];
    // Optionally preserve month and year
    if (isset($_GET['month'])) {
        $allCsvParams['month'] = $_GET['month'];
    }
    if (isset($_GET['year'])) {
        $allCsvParams['year'] = $_GET['year'];
    }
    $allCsvQuery = http_build_query($allCsvParams);
    echo "<a href='?" . htmlspecialchars($allCsvQuery) . "' class='button'>Download Entire Adventure Log</a>";

    echo "</div>"; // Close csv-buttons
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
    SELECT type, data, people, location, localts
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

// Start the HTML output
?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
    /* Only keep calendar-specific styles that aren't in main.css */
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

    .calendar td.has-event {
        background-color: #007bff;
    }

    .calendar td a {
        color: #ffffff;
        text-decoration: none;
        display: block;
        width: 100%;
        height: 100%;
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
        gap: 15px;
    }

    .calendar-navigation a {
        padding: 8px 16px;
        color: #ffffff;
        text-decoration: none;
        background-color: #007bff;
        border-radius: 4px;
        transition: background-color 0.3s;
    }

    .calendar-navigation a:hover {
        background-color: #0056b3;
        text-decoration: none;
    }

    .calendar-navigation span {
        color: #ffffff;
    }

    /* Override specific styles for this page */
    main {
        padding-top: 160px;
        padding-bottom: 40px;
        padding-left: 10px;
    }

    .csv-buttons {
        display: flex;
        gap: 10px;
        margin: 20px 0;
        justify-content: center;
    }

    .csv-buttons .button {
        margin: 0;
    }

    /* Table specific overrides */
    .table-container {
        margin-top: 20px;
        overflow-x: auto;
    }

    table {
        font-size: 14px;
    }

    th a {
        color: yellow;
    }
</style>

<main>
    <div class="indent5">
        <h1>📆CHIM Adventure Log</h1>
        <h2>All time and dates are in UTC. Tamrielic Time may be inconsistent.</h2>
        <h3>This is directly connected to the Event Log. It's just a nicer way to view it.</h3>

        <?php
        // Render Combined CSV Download Buttons at the Top
        renderHeader();

        // Calendar Navigation
        echo '<div class="calendar-navigation">';
        
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

        // Link to previous month
        echo "<a href='?month={$prevMonth}&year={$prevYear}'>&laquo; <b>Previous Month</b></a>";

        // Display current month and year
        $monthName = date('F', strtotime("$year-$month-01 UTC"));
        echo "<span style='padding: 0 15px; color: #f8f9fa; font-size: 1.5em;'><b>{$monthName} {$year}</b></span>";

        // Link to next month
        echo "<a href='?month={$nextMonth}&year={$nextYear}'><b>Next Month</b> &raquo;</a>";
        echo '</div>';

        // Render the Calendar
        echo renderCalendar($month, $year, $allEventDates);

        // Event Table
        ?>
        <table>
            <colgroup>
                <col style="width: 50%;">
                <col style="width: 25%;">
                <col style="width: 19%;">
                <col style="width: 6%;">
            </colgroup>
            <tr>
                <th>Context</th>
                <th>Nearby People</th>
                <th>Location & <a href="https://en.uesp.net/wiki/Lore:Calendar" target="_blank" style="color: yellow;">Tamrielic Time</a></th>
                <th>Time(UTC)</th>
            </tr>
            <?php
            // Reset the result pointer to the beginning for table rendering
            pg_result_seek($result, 0);

            // Fetch and display each row in the table
            while ($row = pg_fetch_assoc($result)) {
                $processed_row = process_event_row($row, false);
                if ($processed_row === null) {
                    continue;
                }

                echo "<tr>";
                echo "<td>{$processed_row['Context']}</td>";
                echo "<td>{$processed_row['Nearby People']}</td>";
                echo "<td>{$processed_row['Location & Tamrielic Time']}</td>";
                echo "<td>{$processed_row['Time(UTC)']}</td>";
                echo "</tr>";
            }
            ?>
        </table>

        <?php
        // Render Combined CSV Download Buttons at the Bottom
        renderHeader();

        // Close Database Connection
        pg_close($conn);
        ?>
    </div>
</main>

<?php
include(UI_PATH.DIRECTORY_SEPARATOR."tmpl".DIRECTORY_SEPARATOR."footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
