<?php
session_start();

// Enable error reporting (for development purposes)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Paths
$rootPath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
$enginePath = $rootPath . ".." . DIRECTORY_SEPARATOR;
$configFilepath = $rootPath . "conf" . DIRECTORY_SEPARATOR;

// Database connection details
$host = 'localhost';
$port = '5432';
$dbname = 'dwemer';
$schema = 'public';
$username = 'dwemer';
$password = 'dwemer';

function compareFileModificationDate($a, $b) {
    return filemtime($b) - filemtime($a);
}

// Connect to the database
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");

if (!$conn) {
    echo "<div class='message'>Failed to connect to database: " . pg_last_error() . "</div>";
    exit;
}

// Get the selected date from the URL parameter, default to today if not set
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Validate the selected date format (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d'); // Fallback to today if invalid
}

// Calculate the start and end timestamps for the selected day
$startOfDay = strtotime($selectedDate . ' 00:00:00');
$endOfDay = strtotime($selectedDate . ' 23:59:59');

// Modify the SQL query to fetch records for the selected day
$query = "
    SELECT type, data, people, location, localts
    FROM {$schema}.eventlog
    WHERE type IN ('im_alive', 'chat', 'rpg_word', 'rpg_lvlup', 'rechat', 'quest', 'itemfound', 'inputtext', 'goodnight', 'goodmorning', 'ginputtext', 'death', 'combatendmighty', 'combatend')
    AND localts BETWEEN $startOfDay AND $endOfDay
    ORDER BY localts ASC
";

$result = pg_query($conn, $query);

if (!$result) {
    echo "<div class='message'>Query error: " . pg_last_error($conn) . "</div>";
    exit;
}

/**
 * Function to render navigation buttons and calendar within the same container.
 * 
 * @param string $currentDate The currently selected date in 'Y-m-d' format.
 */
function renderHeader($currentDate) {
    // Compute previous and next dates
    $prevDate = date('Y-m-d', strtotime($currentDate . ' -1 day'));
    $nextDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));

    // Format the display date
    $displayDate = date('d-M-y', strtotime($currentDate));

    echo "<div class='calendar-container'>";

    // Previous Day Button
    echo "<a href='?date={$prevDate}' class='button'>&laquo; Previous Day</a>";

    // Calendar
    echo "<div class='calendar-navigation'>";
    echo "<form method='GET' action='' id='dateForm'>";
    echo "<label for='datePicker'>Select Date: </label>";
    echo "<input type='date' id='datePicker' name='date' value='{$currentDate}' max='" . date('Y-m-d') . "' />";
    echo "<noscript><input type='submit' value='Go'></noscript>";
    echo "</form>";
    echo "</div>";

    // Next Day Button
    echo "<a href='?date={$nextDate}' class='button'>Next Day &raquo;</a>";

    echo "</div>";

    // Automatically submit the form when a date is selected
    echo "
    <script>
        document.getElementById('datePicker').addEventListener('change', function() {
            document.getElementById('dateForm').submit();
        });
    </script>
    ";
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

        tr:hover {
            background-color: #555555; /* Highlight on hover */
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

        /* Navigation Styles */
        .pagination {
            /* Removed as we are using a new combined header */
        }

        .pagination .button {
            /* Removed as we are using a new combined header */
        }

        .pagination span {
            /* Removed as we are using a new combined header */
        }

        /* Calendar Navigation Styles */
        .calendar-navigation {
            margin: 0 20px;
        }

        /* New styles for the calendar container */
        .calendar-container {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 20px 0;
        }

        .calendar-container .button {
            margin: 0 10px;
        }

        /* Responsive Design */
        @media screen and (max-width: 768px) {
            table, th, td {
                font-size: 14px;
            }

            .calendar-container {
                flex-direction: column;
            }

            .calendar-container .button,
            .calendar-navigation {
                margin: 10px 0;
            }

            .calendar-navigation input[type="date"] {
                width: 100%;
                box-sizing: border-box;
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
    </style>
</head>
<body>
    <h1>📆CHIM Adventure Log</h1>
    <h2>All time and dates are in UTC</h2>

    <?php
    // Render Combined Navigation and Calendar at the Top
    renderHeader($selectedDate);
    ?>

    <table>
        <!-- Define column widths using <colgroup> -->
        <colgroup>
            <col style="width: 50%;">
            <col style="width: 25%;">
            <col style="width: 19%;">
            <col style="width: 6%;"> <!-- Adjusted width for Time column -->
        </colgroup>
        <tr>
            <th>Context</th>
            <th>Nearby People</th>
            <th>Location & <a href="https://en.uesp.net/wiki/Lore:Calendar" target="_blank">Tamriel Time</a></th>
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
                // Change the date format to 'H:i:s'
                $timeDisplay = date('H:i:s', $timestamp);
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
    // Render Combined Navigation and Calendar at the Bottom
    renderHeader($selectedDate);

    // **Close Database Connection**
    pg_close($conn);
    ?>
</body>
</html>
