<?php
session_start();

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

// Determine the month and year to display
if (isset($_GET['month']) && isset($_GET['year'])) {
    $month = (int)$_GET['month'];
    $year = (int)$_GET['year'];
    
    // Validate month and year
    if ($month < 1 || $month > 12) $month = date('n');
    if ($year < 1970 || $year > 2100) $year = date('Y');
} else {
    // Default to current month and year
    $month = date('n');
    $year = date('Y');
}

// Fetch all unique dates with events for the selected month and year
$startOfMonth = strtotime("$year-$month-01 00:00:00");
$endOfMonth = strtotime("+1 month", $startOfMonth) - 1;

$allEventDates = [];

// Query to get all unique dates with events within the selected month
$allDatesQuery = "
    SELECT DISTINCT to_char(to_timestamp(localts), 'YYYY-MM-DD') as event_date
    FROM {$schema}.eventlog
    WHERE type IN ('im_alive', 'chat', 'rpg_word', 'rpg_lvlup', 'rechat', 'quest', 'itemfound', 'inputtext', 'goodnight', 'goodmorning', 'ginputtext', 'death', 'combatendmighty', 'combatend')
    AND to_timestamp(localts) BETWEEN to_timestamp($startOfMonth) AND to_timestamp($endOfMonth)
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
    $currentCsvQuery = http_build_query($currentCsvParams);
    echo "<a href='?" . htmlspecialchars($currentCsvQuery) . "' class='button'>Download Current Date</a>";

    // Build the current query parameters for all data CSV download
    $allCsvParams = ['export' => 'all_csv'];
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
    $firstDayTimestamp = strtotime("$year-$month-01");
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
    $lastDayOfWeek = (date('w', strtotime("$year-$month-$daysInMonth")));
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

// Calculate the start and end timestamps for the selected day
$startOfDay = strtotime($selectedDate . ' 00:00:00');
$endOfDay = strtotime($selectedDate . ' 23:59:59');

// Modify the SQL query to fetch records for the selected day
$query = "
    SELECT type, data, people, location, localts
    FROM {$schema}.eventlog
    WHERE type IN ('im_alive', 'chat', 'rpg_word', 'rpg_lvlup', 'rechat', 'quest', 'itemfound', 'inputtext', 'goodnight', 'goodmorning', 'ginputtext', 'death', 'combatendmighty', 'combatend')
    AND to_timestamp(localts) BETWEEN to_timestamp($startOfDay) AND to_timestamp($endOfDay)
    ORDER BY localts ASC
";

$result = pg_query($conn, $query);

if (!$result) {
    echo "<div class='message'>Query error: " . pg_last_error($conn) . "</div>";
    exit;
}
?> 

<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="../../images/favicon.ico">
    <title>📆CHIM Adventure Log</title>
    <style>
        /* Updated CSS for Dark Grey Background Theme */
        body {
            font-family: Arial, sans-serif;
            background-color: #2c2c2c; /* Dark grey background */
            color: #f8f9fa; /* Light grey text for readability */
            margin: 0;
            padding: 20px;
        }

        h1, h2 {
            color: #ffffff; /* White color for headings */
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
            table-layout: fixed; /* Enforce fixed table layout */
        }

        .bold-name {
            font-weight: bold;
        }

        /* Define column widths using <colgroup> */
        colgroup col:nth-child(1) { /* Context */
            width: 50%;
        }

        colgroup col:nth-child(2) { /* Nearby People */
            width: 25%;
        }

        colgroup col:nth-child(3) { /* Location & Tamriel Time */
            width: 19%;
        }

        colgroup col:nth-child(4) { /* Time */
            width: 6%;
        }

        th, td {
            border: 1px solid #555555; /* Darker borders for table cells */
            padding: 12px;
            text-align: left;
            word-wrap: break-word; /* Ensure long words break to maintain layout */
            overflow: hidden; /* Hide overflow content */
            white-space: normal; /* Allow content to wrap */
        }

        th {
            background-color: #3a3a3a; /* Slightly lighter grey for table headers */
            color: #f8f9fa;
        }

        tr:nth-child(even) {
            background-color: #3a3a3a; /* Zebra striping for table rows */
        }

        .message {
            background-color: #444444; /* Darker background for messages */
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #555555;
            max-width: 600px;
            margin-bottom: 20px;
            color: #f8f9fa; /* Light text in messages */
        }

        .message p {
            margin: 0;
        }

        /* CSV Buttons Container */
        .csv-buttons {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap; /* Allow buttons to wrap on smaller screens */
            margin-top: 10px;
        }

        .csv-buttons .button {
            margin: 5px 10px; /* Consistent spacing between CSV buttons */
        }

        /* Responsive Design */
        @media screen and (max-width: 768px) {
            table, th, td {
                font-size: 14px;
            }

            .csv-buttons .button {
                margin: 10px 0;
            }
        }

        /* Additional Styles from Provided CSS */
        form {
            margin-bottom: 20px;
            background-color: #3a3a3a; /* Slightly lighter grey for form backgrounds */
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #555555; /* Darker border for contrast */
            max-width: 600px;
        }

        label {
            font-weight: bold;
            color: #f8f9fa; /* Ensure labels are readable */
        }

        input[type="text"], input[type="file"], textarea {
            width: 100%;
            padding: 6px;
            margin-top: 5px;
            margin-bottom: 15px;
            border: 1px solid #555555; /* Darker borders */
            border-radius: 3px;
            background-color: #4a4a4a; /* Dark input backgrounds */
            color: #f8f9fa; /* Light text inside inputs */
            resize: vertical; /* Allows users to resize vertically if needed */
            font-family: Arial, sans-serif; /* Ensures consistent font */
            font-size: 14px; /* Sets a readable font size */
        }

        input[type="submit"], .button {
            background-color: #007bff;
            border: none;
            color: white;
            border-radius: 5px; /* Slightly larger border radius */
            cursor: pointer;
            padding: 8px 16px; /* Increased padding for larger button */
            font-size: 16px;    /* Increased font size */
            font-weight: bold;  /* Bold text for better visibility */
            transition: background-color 0.3s ease; /* Smooth hover transition */
            text-decoration: none;
            display: inline-block;
        }

        input[type="submit"]:hover, .button:hover {
            background-color: #0056b3; /* Darker shade on hover */
        }

        .response-container {
            margin-top: 20px;
        }

        .indent {
            padding-left: 10ch; /* 10 character spaces */
        }

        .indent5 {
            padding-left: 5ch; /* 5 character spaces */
        }

        /* Highlighted date style */
        .has-event a {
            background-color: #007bff !important; /* Blue background matching buttons */
            color: white !important;
            border-radius: 50%;
        }

        /* Calendar Styles */
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
        }

        .calendar td.has-event {
            background-color: #007bff; /* Blue highlight */
            color: #ffffff; /* White text for contrast */
        }

        .calendar td.has-event a {
            color: #ffffff;
            text-decoration: none;
            font-weight: bold;
        }

        .calendar td a {
            display: block;
            width: 100%;
            height: 100%;
            text-decoration: none;
            color: inherit;
        }

        /* Tooltip text */
        .calendar td a::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 120%; /* Position above the date */
            left: 50%;
            transform: translateX(-50%);
            background-color: #333333;
            color: #ffffff;
            padding: 5px 8px;
            border-radius: 4px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease;
            font-size: 12px;
            z-index: 10;
        }


    </style>
</head>
<body>
    <h1>📆CHIM Adventure Log</h1>
    <h2>Time and Date are in UTC</h2>

    <?php
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

        // Link to previous month
        echo "<a href='?month={$prevMonth}&year={$prevYear}'>&laquo; <b>Previous Month</b></a>";

        // Display current month and year
        $monthName = date('F', strtotime("$year-$month-01"));
        echo "<span style='padding: 0 15px; color: #f8f9fa; font-size: 1.5em;'><b>{$monthName} {$year}</b></span>";

        // Link to next month
        echo "<a href='?month={$nextMonth}&year={$nextYear}'><b>Next Month</b> &raquo;</a>";
        ?>
    </div>

    <!-- Render the Calendar -->
    <?php
    echo renderCalendar($month, $year, $allEventDates);
    ?>

    <!-- Event Table -->
    <table>
        <colgroup>
            <col style="width: 50%;">
            <col style="width: 25%;">
            <col style="width: 19%;">
            <col style="width: 6%;"> <!-- Adjusted width for Time column -->
        </colgroup>
        <tr>
            <th>Context</th>
            <th>Nearby People</th>
            <th>Location & <a href="https://en.uesp.net/wiki/Lore:Calendar" target="_blank" style="color: yellow;">Tamrelic Time</a></th>
            <th>Time(UTC)</th>
        </tr>
        <?php
        while ($row = pg_fetch_assoc($result)) {
            // **Step 1: Check the 'type' column**
            $type = $row['type'];

            // Define the allowed types
            $allowedTypes = ['im_alive', 'chat', 'rpg_word', 'rpg_lvlup', 'rechat', 'quest', 'itemfound', 'inputtext', 'goodnight', 'goodmorning', 'ginputtext', 'death', 'combatendmighty', 'combatend'];

            // If the type is not in the allowed list, skip this row
            if (!in_array($type, $allowedTypes)) {
                continue; // Skip to the next iteration of the loop
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

            // **Format 'localts' into a readable date format**
            // Assuming 'localts' is a Unix timestamp (integer)
            // Directly use it in date() without strtotime()
            // Step 1: Convert the raw timestamp to an integer
            $timestamp = (int)$rawLocalts;

            // Step 2: Validate and format the timestamp
            if ($timestamp > 0) { // Basic validation to ensure it's a valid timestamp
                // Change the date format to 'H:i:s - d-m-y'
                $timeDisplay = date('H:i:s - d-m-y', $timestamp);
            } else {
                // If 'localts' is invalid, display as-is with HTML special characters converted
                $timeDisplay = htmlspecialchars($rawLocalts);
            }

            // **Escape HTML for safety**
            $data = htmlspecialchars($data);
            $people = htmlspecialchars($people);
            $location = htmlspecialchars($locationDisplay); // Display only the main location
            $timeDisplay = htmlspecialchars($timeDisplay);

            // **Output the table row**
            echo "<tr><td>{$data}</td><td>{$people}</td><td>{$location}</td><td>{$timeDisplay}</td></tr>";
        }
        ?>
    </table>

    <?php
    // Render Combined CSV Download Buttons at the Bottom
    renderHeader();

    // **Close Database Connection**
    pg_close($conn);
    ?>
</body>
</html>
