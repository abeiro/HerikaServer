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

// Profile selection
$GLOBALS["PROFILES"] = []; // Initialize the PROFILES array
foreach (glob($configFilepath . 'conf_????????????????????????????????.php') as $mconf) {
    if (file_exists($mconf)) {
        $filename = basename($mconf);
        $pattern = '/conf_([a-f0-9]+)\.php/';
        if (preg_match($pattern, $filename, $matches)) {
            $hash = $matches[1];
            $GLOBALS["PROFILES"][$hash] = $mconf;
        }
    }
}

function compareFileModificationDate($a, $b) {
    return filemtime($b) - filemtime($a);
}

// Connect to the database
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");

if (!$conn) {
    echo "<div class='message'>Failed to connect to database: " . pg_last_error() . "</div>";
    exit;
}

// Pagination settings
$limit = 200;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $limit;

// Count total rows
$countQuery = "SELECT COUNT(*) FROM {$schema}.eventlog";
$countResult = pg_query($conn, $countQuery);
$totalRows = 0;
if ($countResult) {
    $totalRows = pg_fetch_result($countResult, 0, 0);
}

// Fetch the entries
$query = "
    SELECT type, data, people, location, gamets
    FROM {$schema}.eventlog
    WHERE type IN ('im_alive', 'chat', 'rpg_word', 'rpg_lvlup', 'rechat', 'quest', 'itemfound', 'inputtext', 'goodnight', 'goodmorning', 'ginputtext', 'death', 'combatendmighty', 'combatend')
    ORDER BY gamets DESC
    LIMIT $limit OFFSET $offset
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
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <title>CHIM Adventure Log</title>
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
        }

        th, td {
            border: 1px solid #555555; /* Darker borders for table cells */
            padding: 12px;
            text-align: left;
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

        /* Pagination Styles */
        .pagination {
            margin-top: 20px;
        }

        .pagination a {
            color: #f8f9fa;
            padding: 8px 16px;
            text-decoration: none;
            background-color: #007bff;
            border-radius: 5px;
            margin-right: 5px;
            transition: background-color 0.3s ease;
        }

        .pagination a:hover {
            background-color: #0056b3;
        }

        /* Responsive Design */
        @media screen and (max-width: 768px) {
            table, th, td {
                font-size: 14px;
            }

            .pagination a {
                padding: 6px 12px;
                font-size: 14px;
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
    <h1>CHIM Adventure Log</h1>
    <table>
        <tr>
            <th>Data</th>
            <th>People</th>
            <th>Location</th>
            <th>Time</th>
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
            $rawGamets = $row['gamets']; // Original gamets timestamp

            // **Extract gamets from location**
            // Remove leading/trailing parentheses
            $cleanLocation = trim($rawLocation, "()");

            // **Extract 'Current Date in Skyrim World' for gametsDisplay**
            if (preg_match('/Current Date in Skyrim World:\s*([^,]+(?:, [^,]+){3})/i', $cleanLocation, $dateMatch)) {
                // Assign only the captured date part
                $gametsDisplay = trim($dateMatch[1]);
            } else {
                // Fallback to original gamets timestamp if extraction fails
                $gametsDisplay = @date('Y-m-d H:i:s', $rawGamets);
                if (!$gametsDisplay) {
                    $gametsDisplay = htmlspecialchars($rawGamets);
                }
            }

            // **Extract 'Context location' for location**
            if (preg_match('/Context location:\s*([^,]+)/i', $cleanLocation, $locationMatch)) {
                $locationDisplay = trim($locationMatch[1]);
            } else {
                // Fallback to 'Hold' if 'Context location' is not found
                if (preg_match('/Hold:\s*([^,]+)/i', $cleanLocation, $holdMatch)) {
                    $locationDisplay = trim($holdMatch[1]);
                } else {
                    // Fallback to the entire cleanLocation if both extractions fail
                    $locationDisplay = $cleanLocation;
                }
            }

            // **Transform people**
            // Remove leading/trailing pipes and spaces, then split by '|'
            $cleanPeople = trim($rawPeople, "|() ");
            $peopleList = array_filter(explode("|", $cleanPeople), 'strlen');
            $people = implode(", ", $peopleList);

            // **Transform data**
            // Remove the '(Context location: ...)' substring
            $data = preg_replace('/\(Context location:[^)]+\)/i', '', $rawData);
            $data = trim($data);

            // **Escape HTML for safety**
            $data = htmlspecialchars($data);
            $people = htmlspecialchars($people);
            $location = htmlspecialchars($locationDisplay); // Display only the main location
            $gametsDisplay = htmlspecialchars($gametsDisplay);

            // **Output the table row**
            echo "<tr><td>{$data}</td><td>{$people}</td><td>{$location}</td><td>{$gametsDisplay}</td></tr>";
        }
        ?>
    </table>

    <?php
    // **Pagination Controls**
    $totalPages = ceil($totalRows / $limit);

    echo "<div class='pagination'>";
    if ($page > 1) {
        $prev = $page - 1;
        echo "<a href='?page={$prev}'>&laquo; Previous</a> ";
    }

    if ($page < $totalPages) {
        $next = $page + 1;
        echo "<a href='?page={$next}'>Next &raquo;</a>";
    }
    echo "</div>";

    // **Close Database Connection**
    pg_close($conn);
    ?>
</body>
</html>
