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
require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."logger.php");

// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

$TITLE = "📆CHIM Adventure Log";

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

    // Get the speaker (first person in the list) for row grouping
    $speaker = !empty($peopleList) ? $peopleList[0] : 'Narrator';

    // Use raw data directly for Context, just remove location context if present
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
        'Speaker' => $speaker, // Keep speaker for row grouping
        'Nearby People' => $people,
        'Location & Tamrielic Time' => $locationDisplay,
        'Time(UTC)' => $timeDisplay
    ];
}

// Function to handle CSV export
function handle_csv_export($conn, $schema) {
    if (isset($_GET['export'])) {
        $exportType = $_GET['export'];

        if ($exportType === 'csv' || $exportType === 'all_csv') {
            // Clear any existing output buffer
            while (ob_get_level()) {
                ob_end_clean();
            }

            $is_specific_date = ($exportType === 'csv');

            if ($is_specific_date) {
                // Get the selected date from URL or latest date if not specified
                if (isset($_GET['date'])) {
                    $selectedDate = $_GET['date'];
                    // Validate the selected date format (YYYY-MM-DD)
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
                        // Invalid date format
                        header("HTTP/1.1 400 Bad Request");
                        echo "Invalid date format.";
                        exit;
                    }
                } else {
                    // Get the most recent date from the eventlog
                    $latestDateQuery = "
                        SELECT to_char(to_timestamp(localts::double precision) AT TIME ZONE 'UTC', 'YYYY-MM-DD') as event_date
                        FROM {$schema}.eventlog
                        WHERE type IN ('im_alive', 'chat','infoaction', 'rpg_word', 'rpg_lvlup', 'rechat', 'quest', 'itemfound', 'inputtext', 'goodnight', 'goodmorning', 'ginputtext', 'death', 'combatendmighty', 'combatend')
                        ORDER BY localts DESC
                        LIMIT 1
                    ";
                    
                    $latestDateResult = pg_query($conn, $latestDateQuery);
                    if (!$latestDateResult) {
                        header("HTTP/1.1 500 Internal Server Error");
                        echo "Error fetching latest date: " . pg_last_error($conn);
                        exit;
                    }
                    
                    $latestDateRow = pg_fetch_assoc($latestDateResult);
                    if (!$latestDateRow) {
                        header("HTTP/1.1 404 Not Found");
                        echo "No events found in the adventure log.";
                        exit;
                    }
                    
                    $selectedDate = $latestDateRow['event_date'];
                }

                // Calculate the start and end timestamps for the selected day in UTC
                $dtSelected = new DateTime($selectedDate . ' 00:00:00', new DateTimeZone('UTC'));
                $startOfDay = $dtSelected->getTimestamp();
                $dtSelectedEnd = clone $dtSelected;
                $dtSelectedEnd->modify('+1 day')->modify('-1 second');
                $endOfDay = $dtSelectedEnd->getTimestamp();

                // Prepare the SQL query with explicit casting
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
                if (isset($_GET['date'])) {
                    header('Content-Disposition: attachment; filename=adventure_log_' . $selectedDate . '.csv');
                } else {
                    header('Content-Disposition: attachment; filename=adventure_log_latest.csv');
                }
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
                if ($processed_row !== null) {
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

// Handle CSV export if requested - do this before any output buffering
handle_csv_export($conn, $schema);

// Start output buffering after CSV handling
ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");
?>
<!-- Ensure main.css is loaded after any reboot.css -->
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/diary_adventure.css">
<?php

$debugPaneLink = false;
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/navbar.php");

// Determine the month and year to display
$month = isset($_GET['month']) ? sanitize_int($_GET['month'], date('n')) : date('n');
$year = isset($_GET['year']) ? sanitize_int($_GET['year'], date('Y')) : date('Y');

// Add Tamrielic mode toggle
$useTamrielicTime = isset($_GET['tamrielic']) && $_GET['tamrielic'] === 'true';

// Set default values for Tamrielic mode
if ($useTamrielicTime) {
    if (!isset($_GET['month']) || !isset($_GET['year'])) {
        // Default to Last Seed 17th, 4E 201 when first switching to Tamrielic
        $month = 8; // Last Seed
        $year = 201; // 4E 201
    }
}

// Define Tamrielic month mapping
$tamrielicMonths = [
    1 => 'Morning Star',
    2 => "Sun's Dawn",
    3 => 'First Seed',
    4 => "Rain's Hand",
    5 => 'Second Seed',
    6 => 'Mid Year',
    7 => "Sun's Height",
    8 => 'Last Seed',
    9 => 'Hearthfire',
    10 => 'Frost Fall',
    11 => "Sun's Dusk",
    12 => 'Evening Star'
];

// Define Tamrielic month lengths
$tamrielicMonthLengths = [
    1 => 31, // Morning Star
    2 => 28, // Sun's Dawn
    3 => 31, // First Seed
    4 => 30, // Rain's Hand
    5 => 31, // Second Seed
    6 => 30, // Mid Year
    7 => 31, // Sun's Height
    8 => 31, // Last Seed
    9 => 30, // Hearthfire
    10 => 31, // Frost Fall
    11 => 30, // Sun's Dusk
    12 => 31  // Evening Star
];

$tamrielicMonthToNumber = array_flip($tamrielicMonths);

// Function to get days in a Tamrielic month
function get_tamrielic_days_in_month($month) {
    global $tamrielicMonthLengths;
    return $tamrielicMonthLengths[$month] ?? 30;
}

// Get current game timestamp if in Tamrielic mode
$currentGameDate = null;
$currentTamrielicMonth = $tamrielicMonths[$month] ?? 'Last Seed';
$currentTamrielicYear = $year;
$currentTamrielicDay = isset($_GET['day']) ? sanitize_int($_GET['day'], 17) : 17;

// Initialize the events array
$allEventDates = [];

if ($useTamrielicTime) {
    // If we have month and year parameters, use them to set the current Tamrielic month
    if (isset($_GET['month']) && isset($_GET['year'])) {
        $currentTamrielicMonth = $tamrielicMonths[$month] ?? 'Morning Star';
        $currentTamrielicYear = $year;
        error_log("Debug - Using URL parameters: month={$currentTamrielicMonth}, year={$currentTamrielicYear}");
    }
}

// Prepare the SQL query with explicit casting to double precision
$allDatesQuery = "
    SELECT DISTINCT 
        gamets,
        localts,
        type,
        data,
        people,
        location,
        to_char(to_timestamp(CAST(localts AS bigint)), 'YYYY-MM-DD') as date,
        CASE 
            WHEN " . ($useTamrielicTime ? 'true' : 'false') . " THEN
                gamets
            ELSE
                localts
        END as sort_field
    FROM {$schema}.eventlog
    WHERE type IN ('im_alive', 'chat', 'infoaction', 'rpg_word', 'rpg_lvlup', 'rechat', 'quest', 'itemfound', 'inputtext', 'goodnight', 'goodmorning', 'ginputtext', 'death', 'combatendmighty', 'combatend')
    AND gamets > 0
    ORDER BY sort_field ASC
";

error_log("Debug - SQL Query: {$allDatesQuery}");

$allDatesResult = pg_query($conn, $allDatesQuery);

if ($allDatesResult) {
    error_log("Debug - Processing events for month: {$tamrielicMonths[$month]}");
    error_log("Debug - Looking for events in year: {$year}");
    
    while ($dateRow = pg_fetch_assoc($allDatesResult)) {
        if (!$useTamrielicTime) {
            // Regular calendar mode - use localts
            if (isset($dateRow['localts']) && $dateRow['localts'] > 0) {
                $eventDate = new DateTime("@" . $dateRow['localts']);
                $eventDate->setTimezone(new DateTimeZone('UTC'));
                $eventDate->format('Y-m-d');
                
                if ($eventDate->format('n') == $month && $eventDate->format('Y') == $year) {
                    $allEventDates[] = [
                        'date' => $dateRow['date'],
                        'day' => $eventDate->format('j'),
                        'localts' => $dateRow['localts'],
                        'type' => $dateRow['type'],
                        'data' => $dateRow['data'],
                        'people' => $dateRow['people'],
                        'location' => $dateRow['location']
                    ];
                }
            }
        } else {
            // Tamrielic calendar mode - use gamets
            if (isset($dateRow['gamets']) && $dateRow['gamets'] > 0) {
                $gamets = floatval($dateRow['gamets']);
                $skyrim_start_timestamp = strtotime('0201-08-17 00:00:00');
                $f_seconds = $gamets * 0.00864;
                $ts_time = $skyrim_start_timestamp + intval($f_seconds);
                
                $eventDay = intval(date('d', $ts_time));
                $eventMonth = intval(date('m', $ts_time));
                $eventYear = intval(ltrim(date('Y', $ts_time), '0'));
                
                error_log("Debug - Event found: Month={$eventMonth}, Year={$eventYear}, Day={$eventDay}");
                error_log("Debug - Looking for: Month={$month}, Year={$year}");
                
                if ($eventMonth == $month && $eventYear == $year) {
                    error_log("Debug - Adding event for day {$eventDay}");
                    $allEventDates[] = [
                        'tamrielic_date' => convert_gamets2skyrim_long_date_no_time($gamets),
                        'tamrielic_month' => $tamrielicMonths[$eventMonth],
                        'gamets' => $gamets,
                        'localts' => $dateRow['localts'],
                        'day' => $eventDay,
                        'type' => $dateRow['type'],
                        'data' => $dateRow['data'],
                        'people' => $dateRow['people'],
                        'location' => $dateRow['location']
                    ];
                }
            }
        }
    }
} else {
    echo "<div class='message'>Error fetching event dates: " . pg_last_error($conn) . "</div>";
}

/**
 * Function to render a calendar for a given month and year, highlighting dates with events.
 *
 * @param int $month The month for the calendar (1-12).
 * @param int $year The year for the calendar (e.g., 2024).
 * @param array $eventDates Array of dates that have events.
 * @param bool $useTamrielicTime Whether to use Tamrielic time.
 * @param string|null $currentGameDate The current game date in Tamrielic format.
 * @return string HTML string representing the calendar.
 */
function renderCalendar($month, $year, $allEventDates, $useTamrielicTime, $tamrielicMonths) {
    $calendar = array();
    
    // Get the first day of the month
    if ($useTamrielicTime) {
        // For Tamrielic calendar, we calculate based on Last Seed 17th being Sundas
        $daysInMonth = get_tamrielic_days_in_month($month);
        $currentMonthName = $tamrielicMonths[$month] ?? 'Last Seed';
        
        // Calculate days since Last Seed 17th
        $daysSinceAnchor = 0;
        if ($month == 8) { // Last Seed
            $firstDay = 5; // 1st of Last Seed is always Fredas
        } else {
            // For other months, calculate based on Last Seed
            if ($month > 8) {
                // Count forward from Last Seed
                for ($i = 8; $i < $month; $i++) {
                    $daysSinceAnchor += get_tamrielic_days_in_month($i);
                }
            } else {
                // Count backward from Last Seed
                for ($i = 8; $i > $month; $i--) {
                    $daysSinceAnchor -= get_tamrielic_days_in_month($i);
                }
            }
            // Add the offset from Last Seed 1st (which is Fredas)
            $firstDay = ($daysSinceAnchor + 5) % 7;
            if ($firstDay < 0) $firstDay += 7;
        }
    } else {
        $firstDay = date('w', strtotime("$year-$month-01"));
        $daysInMonth = date('t', strtotime("$year-$month-01"));
    }

    // Create the calendar array
    $dayCount = 1;
    $weekCount = 0;
    
    while ($dayCount <= $daysInMonth) {
        for ($i = 0; $i < 7; $i++) {
            if ($weekCount === 0 && $i < $firstDay) {
                $calendar[$weekCount][$i] = "";
            } elseif ($dayCount <= $daysInMonth) {
                // Generate the date string and URL parameters
                if ($useTamrielicTime) {
                    $dateStr = sprintf("%dth of %s, 4E %d", $dayCount, $currentMonthName, $year);
                    $urlParams = sprintf("tamrielic=true&month=%d&year=%d&day=%d",
                        $month,
                        $year,
                        $dayCount
                    );
                } else {
                    $dateStr = sprintf("%04d-%02d-%02d", $year, $month, $dayCount);
                    $urlParams = sprintf("date=%s&month=%d&year=%d", 
                        $dateStr,
                        $month,
                        $year
                    );
                }

                // Check if there are events for this day
                $hasEvents = false;
                $eventCount = 0;
                foreach ($allEventDates as $eventDate) {
                    if ($useTamrielicTime) {
                        // Compare Tamrielic dates
                        $eventDay = isset($eventDate['day']) ? $eventDate['day'] : null;
                        if ($eventDay == $dayCount) {
                            error_log("Debug - Found event for day {$dayCount}");
                            $hasEvents = true;
                            $eventCount++;
                        }
                    } else {
                        // Compare Gregorian dates
                        if (isset($eventDate['localts'])) {
                            $eventDateTime = new DateTime("@{$eventDate['localts']}");
                            $eventDateTime->setTimezone(new DateTimeZone('UTC'));
                            $eventDateStr = $eventDateTime->format('Y-m-d');
                            
                            if ($eventDateStr === $dateStr) {
                                $hasEvents = true;
                                $eventCount++;
                                error_log("Debug - Found event for date {$dateStr}");
                            }
                        }
                    }
                }

                // Create the calendar cell with appropriate styling
                $calendar[$weekCount][$i] = array(
                    'day' => $dayCount,
                    'url' => "?$urlParams",
                    'hasEvents' => $hasEvents,
                    'eventCount' => $eventCount
                );
                
                $dayCount++;
            } else {
                $calendar[$weekCount][$i] = "";
            }
        }
        $weekCount++;
    }
    
    return $calendar;
}

// Days of the week arrays
$gregorianDays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$tamrielicDays = ['Sundas', 'Morndas', 'Tirdas', 'Middas', 'Turdas', 'Fredas', 'Loredas'];

function renderCalendarHTML($calendar, $useTamrielicTime) {
    global $gregorianDays, $tamrielicDays;
    
    $daysOfWeek = $useTamrielicTime ? $tamrielicDays : $gregorianDays;
    $html = "<table class='calendar'>";
    
    // Render header
    $html .= "<tr>";
    foreach ($daysOfWeek as $day) {
        $html .= "<th>{$day}</th>";
    }
    $html .= "</tr>";
    
    // Render calendar body
    foreach ($calendar as $week) {
        $html .= "<tr>";
        foreach ($week as $day) {
            if (empty($day)) {
                $html .= "<td></td>";
            } else {
                $class = $day['hasEvents'] ? 'has-event' : '';
                $dayNum = $day['day'];
                if ($day['hasEvents']) {
                    $html .= "<td class='{$class}'><a href='{$day['url']}#event-table' data-event-count='{$day['eventCount']}'>{$dayNum}</a></td>";
                } else {
                    $html .= "<td class='{$class}'><span>{$dayNum}</span></td>";
                }
            }
        }
        $html .= "</tr>";
    }
    
    $html .= "</table>";
    return $html;
}

// Get the selected date from the URL parameter, default to no date
$selectedDate = null;
if (isset($_GET['date'])) {
    $selectedDate = $_GET['date'];
    
    if ($useTamrielicTime) {
        // For Tamrielic dates, we'll use the anchor date and calculate the offset
        $skyrim_start_timestamp = strtotime('0201-08-17 00:00:00');
        $selectedDate = date('Y-m-d', $skyrim_start_timestamp);
    } else {
        // Validate the selected date format (YYYY-MM-DD) for Gregorian dates
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
            $selectedDate = null;
        }
    }
}

// Only proceed with event fetching if we have a selected date or specific Tamrielic parameters
$shouldFetchEvents = $selectedDate !== null || 
    ($useTamrielicTime && isset($_GET['month']) && isset($_GET['year']) && isset($_GET['day']));

if ($shouldFetchEvents) {
    // Create DateTime objects in UTC for the selected day
    if ($selectedDate !== null) {
        $dtSelected = new DateTime($selectedDate . ' 00:00:00', new DateTimeZone('UTC'));
        $startOfDay = $dtSelected->getTimestamp();
        $dtSelectedEnd = clone $dtSelected;
        $dtSelectedEnd->modify('+1 day')->modify('-1 second');
        $endOfDay = $dtSelectedEnd->getTimestamp();
    }

    // Modify the SQL query to fetch records for the selected day with explicit casting
    error_log("Debug - Selected date: " . $selectedDate);
    error_log("Debug - Start of day: " . $startOfDay);
    error_log("Debug - End of day: " . $endOfDay);

    $query = "
        SELECT type, data, people, location, localts, gamets
        FROM {$schema}.eventlog
        WHERE type IN ('im_alive', 'chat', 'infoaction', 'rpg_word', 'rpg_lvlup', 'rechat', 'quest', 'itemfound', 'inputtext', 'goodnight', 'goodmorning', 'ginputtext', 'death', 'combatendmighty', 'combatend')
        AND (
            CASE 
                WHEN " . ($useTamrielicTime ? 'true' : 'false') . " THEN
                    -- For Tamrielic mode, we'll filter in PHP instead of SQL
                    gamets > 0
                ELSE
                    -- For Gregorian mode, use localts with proper timestamp conversion
                    localts >= " . (isset($startOfDay) ? $startOfDay : 0) . " 
                    AND localts < " . (isset($endOfDay) ? $endOfDay : 0) . "
            END
        )
        ORDER BY localts ASC
    ";

    error_log("Debug - SQL Query: " . $query);
    $result = pg_query($conn, $query);

    if (!$result) {
        echo "<div class='message'>Query error: " . pg_last_error($conn) . "</div>";
        exit;
    }

    // Log the number of rows returned
    $numRows = pg_num_rows($result);
    error_log("Debug - Number of rows returned: " . $numRows);
} else {
    $result = false;
}
?> 

<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="<?php echo $webRoot; ?>/ui/images/favicon.ico">
    <title>📆CHIM Adventure Log</title>
</head>
<body>
    <main class="container">
        <h1>📆CHIM Adventure Log</h1>
        <h3>This is directly connected to the Event Log. It's just a nicer way to view it.</h3>

        <?php
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

        /**
         * Function to render calendar mode toggle buttons
         * @param bool $useTamrielicTime Whether Tamrielic time is currently active
         * @return void
         */
        function renderCalendarModeButtons($useTamrielicTime) {
            echo '<div class="calendar-mode-toggle">';
            
            // Regular Calendar button - always goes to base URL
            echo '<form method="get" style="display: inline; margin-right: 10px;">';
            echo '<button type="submit" class="btn-base ' . (!$useTamrielicTime ? 'btn-primary' : 'btn-secondary') . '">Regular Calendar</button>';
            echo '</form>';
            
            // Tamrielic Calendar button - just adds tamrielic=true
            echo '<form method="get" style="display: inline;">';
            echo '<input type="hidden" name="tamrielic" value="true">';
            echo '<button type="submit" class="btn-base ' . ($useTamrielicTime ? 'btn-primary' : 'btn-secondary') . '">Tamrielic Calendar</button>';
            echo '</form>';
            
            echo '</div>';
        }

        // Render Combined CSV Download Buttons at the Top
        renderHeader();
        ?>

        <!-- Add the toggle buttons before the calendar navigation -->
        <?php renderCalendarModeButtons($useTamrielicTime); ?>

        <!-- Calendar Navigation -->
        <div class="calendar-navigation">
            <?php
            // Calculate previous and next month and year
            if ($useTamrielicTime) {
                // For Tamrielic mode, we need to handle the month names
                $currentMonthNum = array_search($currentTamrielicMonth, $tamrielicMonths) ?: 8;
                
                // Calculate previous month
                $prevMonthNum = $currentMonthNum - 1;
                if ($prevMonthNum < 1) {
                    $prevMonthNum = 12;
                    $prevYear = $currentTamrielicYear - 1;
                } else {
                    $prevYear = $currentTamrielicYear;
                }
                $prevMonthName = $tamrielicMonths[$prevMonthNum];
                
                // Calculate next month
                $nextMonthNum = $currentMonthNum + 1;
                if ($nextMonthNum > 12) {
                    $nextMonthNum = 1;
                    $nextYear = $currentTamrielicYear + 1;
                } else {
                    $nextYear = $currentTamrielicYear;
                }
                $nextMonthName = $tamrielicMonths[$nextMonthNum];
                
                // Link to previous month
                echo "<a href='?month={$prevMonthNum}&year={$prevYear}&tamrielic=true' class='btn-primary'>&laquo; {$prevMonthName}</a>";
                
                // Display current month and year
                echo "<span><b>{$currentTamrielicMonth}, 4E {$currentTamrielicYear}</b></span>";
                
                // Link to next month
                echo "<a href='?month={$nextMonthNum}&year={$nextYear}&tamrielic=true' class='btn-primary'>{$nextMonthName} &raquo;</a>";
            } else {
                // Original Gregorian calendar navigation
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

                // Get month names for navigation
                $prevMonthName = date('F', strtotime("$prevYear-$prevMonth-01 UTC"));
                $nextMonthName = date('F', strtotime("$nextYear-$nextMonth-01 UTC"));
                $currentMonthName = date('F', strtotime("$year-$month-01 UTC"));

                echo "<a href='?month={$prevMonth}&year={$prevYear}' class='btn-primary'>&laquo; {$prevMonthName}</a>";
                echo "<span><b>{$currentMonthName} {$year}</b></span>";
                echo "<a href='?month={$nextMonth}&year={$nextYear}' class='btn-primary'>{$nextMonthName} &raquo;</a>";
            }
            ?>
        </div>

        <!-- Render the Calendar -->
        <?php
        $calendarArray = renderCalendar($month, $year, $allEventDates, $useTamrielicTime, $tamrielicMonths);
        echo renderCalendarHTML($calendarArray, $useTamrielicTime);
        ?>

        <!-- Event Table -->
        <table class="event-table" id="event-table">
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
            if ($shouldFetchEvents && $result) {
                // Reset the result pointer to the beginning for table rendering
                pg_result_seek($result, 0);

                // Initialize variables
                $hasEvents = false;
                $currentSpeaker = null;
                $speakerGroup = 0;
                $previousLocation = null;
                $locationHeader = '';

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
                        $locationHeader = "<tr class='location-change-row'><td colspan='4'>Current Location: {$initialLocation}</td></tr>";
                    }
                    // Reset the result pointer again for the main loop
                    pg_result_seek($result, 0);
                }

                // Buffer the output
                ob_start();

                // Fetch and display each row in the table
                while ($row = pg_fetch_assoc($result)) {
                    $processed_row = process_event_row($row, false);
                    if ($processed_row === null) {
                        continue;
                    }

                    // Extract processed data
                    $data = $processed_row['Context'];
                    $speaker = $processed_row['Speaker'];
                    $people = $processed_row['Nearby People'];
                    $location = $processed_row['Location & Tamrielic Time'];
                    $timeDisplay = $processed_row['Time(UTC)'];
                    
                    // For Tamrielic mode, check if the event matches the selected date
                    if ($useTamrielicTime && isset($row['gamets']) && $row['gamets'] > 0) {
                        $tamrielicDate = convert_gamets2skyrim_long_date_no_time($row['gamets']);
                        if (preg_match('/(\d+)th of ([^,]+), 4E (\d+)/', $tamrielicDate, $matches)) {
                            $eventDay = intval($matches[1]);
                            $eventMonth = $matches[2];
                            $eventYear = intval($matches[3]);
                            
                            // Only show events that match the current Tamrielic date
                            if ($eventMonth !== $tamrielicMonths[$month] || $eventYear !== $year || $eventDay !== intval($_GET['day'] ?? 0)) {
                                continue;
                            }
                        }
                    }
                    
                    // We have at least one event to display
                    if (!$hasEvents) {
                        $hasEvents = true;
                        // Output the location header only when we have events
                        echo $locationHeader;
                    }
                    
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
                    
                    // Check if speaker changed
                    // Extract speaker from the data content
                    $speakerFromData = '';
                    if (preg_match('/^([^:]+):/', $data, $matches)) {
                        $speakerFromData = trim($matches[1]);
                    }
                    
                    // Use extracted speaker if available, otherwise use the one from people list
                    $effectiveSpeaker = $speakerFromData ?: $speaker;
                    
                    if ($currentSpeaker !== $effectiveSpeaker) {
                        $currentSpeaker = $effectiveSpeaker;
                        $speakerGroup++;
                    }
                    
                    // Convert timestamp to game time
                    $gameTimeDisplay = "";
                    if (isset($row['gamets']) && $row['gamets'] > 0) {
                        $gameTimeDisplay = convert_gamets2skyrim_long_date2($row['gamets']);
                    }

                    // Output the table row with speaker-based styling
                    $rowClass = ($speakerGroup % 2 === 0) ? 'speaker-even' : 'speaker-odd';
                    echo "<tr class='{$rowClass}'><td>{$data}</td><td>{$people}</td><td>{$gameTimeDisplay}</td><td>{$timeDisplay}</td></tr>";
                }

                // If no events were found, display a message
                if (!$hasEvents) {
                    echo "<tr><td colspan='4' style='text-align: center; padding: 20px;'>No events found for this date.</td></tr>";
                }

                // Get the buffered content
                $tableContent = ob_get_clean();
                echo $tableContent;
            } else {
                echo "<tr><td colspan='4' style='text-align: center; padding: 20px;'>Select a date to view events.</td></tr>";
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