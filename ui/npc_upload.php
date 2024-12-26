
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

// Function to compare modification dates
function compareFileModificationDate($a, $b) {
    return filemtime($b) - filemtime($a);
}

// Sort the profiles by modification date descending
if (is_array($GLOBALS["PROFILES"])) {
    usort($GLOBALS["PROFILES"], 'compareFileModificationDate');
} else {
    $GLOBALS["PROFILES"] = [];
}

$GLOBALS["PROFILES"] = array_merge(["default" => "$configFilepath/conf.php"], $GLOBALS["PROFILES"]);

if (isset($_SESSION["PROFILE"]) && in_array($_SESSION["PROFILE"], $GLOBALS["PROFILES"])) {
    require_once($_SESSION["PROFILE"]);
} else {
    $_SESSION["PROFILE"] = "$configFilepath/conf.php";
}

// Initialize message variable
$message = '';

// Connect to the database
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");

if (!$conn) {
    echo "<div class='message'>Failed to connect to database: " . pg_last_error() . "</div>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_individual'])) {
    $npc_name = strtolower(trim($_POST['npc_name'] ?? ''));
    $npc_pers = $_POST['npc_pers'] ?? '';

    // Handle voice IDs: if field is empty, set to NULL, otherwise use the trimmed value.
    $melotts_voiceid = (isset($_POST['melotts_voiceid']) && trim($_POST['melotts_voiceid']) !== '') ? trim($_POST['melotts_voiceid']) : null;
    $xtts_voiceid = (isset($_POST['xtts_voiceid']) && trim($_POST['xtts_voiceid']) !== '') ? trim($_POST['xtts_voiceid']) : null;
    $xvasynth_voiceid = (isset($_POST['xvasynth_voiceid']) && trim($_POST['xvasynth_voiceid']) !== '') ? trim($_POST['xvasynth_voiceid']) : null;

    if (!empty($npc_name) && !empty($npc_pers)) {
        // Set npc_misc to an empty string to avoid NULL
        $npc_misc = '';

        // Prepare and execute the INSERT statement with ON CONFLICT
        $query = "
            INSERT INTO $schema.npc_templates_custom (npc_name, npc_pers, npc_misc, melotts_voiceid, xtts_voiceid, xvasynth_voiceid)
            VALUES ($1, $2, $3, $4, $5, $6)
            ON CONFLICT (npc_name)
            DO UPDATE SET
                npc_pers = EXCLUDED.npc_pers,
                npc_misc = EXCLUDED.npc_misc,
                melotts_voiceid = EXCLUDED.melotts_voiceid,
                xtts_voiceid = EXCLUDED.xtts_voiceid,
                xvasynth_voiceid = EXCLUDED.xvasynth_voiceid;
        ";

        $params = array($npc_name, $npc_pers, $npc_misc, $melotts_voiceid, $xtts_voiceid, $xvasynth_voiceid);
        $result = pg_query_params($conn, $query, $params);

        if ($result) {
            $message .= "<p>Data inserted successfully!</p>";
        } else {
            $message .= "<p>An error occurred while inserting or updating data: " . pg_last_error($conn) . "</p>";
        }
    } else {
        $message .= '<p>Please fill in all required fields.</p>';
    }
}

// Check if the CSV upload form has been submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_csv'])) {
    // Check if a file was uploaded without errors
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['csv_file']['tmp_name'];
        $fileName = $_FILES['csv_file']['name'];

        // Allowed file extensions
        $allowedfileExtensions = array('csv');

        // Get file extension
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

        if (in_array($fileExtension, $allowedfileExtensions)) {
            // Open the file for reading
            if (($handle = fopen($fileTmpPath, 'r')) !== false) {
                // Skip the header row
                fgetcsv($handle, 1000, ',');

                // Process each row in the CSV
                $rowCount = 0;
                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    // Assuming CSV columns are:
                    // npc_name, npc_pers, melotts_voiceid, xtts_voiceid, xvasynth_voiceid
                    $npc_name = isset($data[0]) ? strtolower(trim($data[0])) : '';
                    $npc_pers = isset($data[1]) ? trim($data[1]) : '';
                    $npc_misc = '';

                    // Handle voice IDs: if cell not empty, set value; else null
                    $melotts_voiceid = (isset($data[3]) && trim($data[3]) !== '') ? trim($data[3]) : null;
                    $xtts_voiceid = (isset($data[4]) && trim($data[4]) !== '') ? trim($data[4]) : null;
                    $xvasynth_voiceid = (isset($data[5]) && trim($data[5]) !== '') ? trim($data[5]) : null;

                    // Convert to UTF-8 to avoid invalid byte sequences
                    $npc_name = iconv('Windows-1252', 'UTF-8//IGNORE', $npc_name);
                    $npc_pers = iconv('Windows-1252', 'UTF-8//IGNORE', $npc_pers);
                    $npc_misc = iconv('Windows-1252', 'UTF-8//IGNORE', $npc_misc);

                    if ($melotts_voiceid !== null) {
                        $melotts_voiceid = iconv('Windows-1252', 'UTF-8//IGNORE', $melotts_voiceid);
                    }

                    if ($xtts_voiceid !== null) {
                        $xtts_voiceid = iconv('Windows-1252', 'UTF-8//IGNORE', $xtts_voiceid);
                    }

                    if ($xvasynth_voiceid !== null) {
                        $xvasynth_voiceid = iconv('Windows-1252', 'UTF-8//IGNORE', $xvasynth_voiceid);
                    }

                    if (!empty($npc_name) && !empty($npc_pers)) {
                        // Prepare and execute the INSERT statement with ON CONFLICT
                        $query = "
                            INSERT INTO $schema.npc_templates_custom 
                            (npc_name, npc_pers, npc_misc, melotts_voiceid, xtts_voiceid, xvasynth_voiceid)
                            VALUES ($1, $2, $3, $4, $5, $6)
                            ON CONFLICT (npc_name)
                            DO UPDATE SET
                                npc_pers = EXCLUDED.npc_pers,
                                npc_misc = EXCLUDED.npc_misc,
                                melotts_voiceid = EXCLUDED.melotts_voiceid,
                                xtts_voiceid = EXCLUDED.xtts_voiceid,
                                xvasynth_voiceid = EXCLUDED.xvasynth_voiceid;
                        ";

                        $params = array($npc_name, $npc_pers, $npc_misc, $melotts_voiceid, $xtts_voiceid, $xvasynth_voiceid);
                        $result = pg_query_params($conn, $query, $params);

                        if ($result) {
                            $rowCount++;
                        } else {
                            $message .= "<p>Error processing row with npc_name '$npc_name': " . pg_last_error($conn) . "</p>";
                        }
                    } else {
                        $message .= "<p>Skipping empty or invalid row in CSV.</p>";
                    }
                }
                fclose($handle);

                $message .= "<p>$rowCount records inserted or updated successfully from the CSV file.</p>";
            } else {
                $message .= '<p>Error opening the CSV file.</p>';
            }
        } else {
            $message .= '<p>Upload failed. Allowed file types: ' . implode(',', $allowedfileExtensions) . '</p>';
        }
    } else {
        $message .= '<p>No file uploaded or there was an upload error.</p>';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['truncate_npc'])) {
    $truncateQuery = "TRUNCATE TABLE $schema.npc_templates_custom RESTART IDENTITY CASCADE";
    $truncateResult = pg_query($conn, $truncateQuery);

    if ($truncateResult) {
        $message .= "<p style='color: #ff6464; font-weight: bold;'>The npc_templates_custom table has been emptied successfully.</p>";
    } else {
        $message .= "<p>Error emptying npc_templates_custom table: " . pg_last_error($conn) . "</p>";
    }
}

// Handle the download request for the example CSV
if (isset($_GET['action']) && $_GET['action'] === 'download_example') {
    // Define the path to the example CSV file
    $filePath = realpath(__DIR__ . '/../data/example_bios_format.csv');

    if (file_exists($filePath)) {
        // Set headers to initiate file download
        header('Content-Description: File Transfer');
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="example.csv"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));

        // Clear output buffering to avoid any additional output
        ob_end_clean();
        flush();

        // Read the file and send it to the output buffer
        readfile($filePath);
        exit;
    } else {
        $message .= '<p>Example CSV file not found.</p>';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <title>📝CHIM - NPC Biography Management</title>
    <style>
        /* Updated CSS for Dark Grey Background Theme */
        body {
            font-family: Arial, sans-serif;
            background-color: #2c2c2c; /* Dark grey background */
            color: #f8f9fa; /* Light grey text for readability */
        }

        h1, h2 {
            color: #ffffff; /* White color for headings */
        }

        form {
            margin-bottom: 20px;
            background-color: #3a3a3a; /* Slightly lighter grey for form backgrounds */
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #4a4a4a; /* Darker border for contrast */
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
            border: 1px solid #4a4a4a; /* Darker borders */
            border-radius: 3px;
            background-color: #4a4a4a; /* Dark input backgrounds */
            color: #f8f9fa; /* Light text inside inputs */
            resize: vertical; /* Allows users to resize vertically if needed */
            font-family: Arial, sans-serif; /* Ensures consistent font */
            font-size: 16px; /* Sets a readable font size */
        }

        input[type="submit"] {
            background-color: #007bff;
            border: none;
            color: white;
            border-radius: 5px; /* Slightly larger border radius */
            cursor: pointer;
            padding: 5px 15px; /* Increased padding for larger button */
            font-size: 18px;    /* Increased font size */
            font-weight: bold;  /* Bold text for better visibility */
            transition: background-color 0.3s ease; /* Smooth hover transition */
        }

        input[type="submit"]:hover {
            background-color: #0056b3; /* Darker shade on hover */
        }

        .message {
            background-color: #444444; /* Darker background for messages */
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #4a4a4a;
            max-width: 600px;
            margin-bottom: 20px;
            color: #f8f9fa; /* Light text in messages */
        }

        .message p {
            margin: 0;
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

        .button {
            padding: 8px 16px;
            margin-top: 10px;
            cursor: pointer;
            background-color: #007bff;
            border: none;
            color: white;
            border-radius: 3px;
        }

        .button:hover {
            background-color: #0056b3;
        }

        .filter-buttons {
            margin: 1em 0;
        }

        .alphabet-button {
            display: inline-block;
            margin-right: 5px;
            padding: 6px 10px;
            color: #fff;
            background-color: #007bff;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
        }

        .alphabet-button:hover {
            background-color: #0056b3;
        }

        .table-container {
            max-height: 800px;
            overflow-y: auto;
            margin-bottom: 20px;
            max-width: 1600px;
        }

        .table-container table {
            width: 100%;
            border-collapse: collapse;
            background-color: #3a3a3a; /* Base background color */
        }

        .table-container th, .table-container td {
            border: 1px solid #555555; /* Border color */
            padding: 8px;
            text-align: left;
            word-wrap: break-word;
            overflow-wrap: break-word;
            color: #f8f9fa; /* Text color */
        }

        .table-container th {
            background-color: #4a4a4a; /* Header background color */
            font-weight: bold;
        }

        /* Alternating row colors */
        .table-container tr:nth-child(even) {
            background-color: #2c2c2c; /* Dark grey for even rows */
        }

        .table-container tr:nth-child(odd) {
            background-color: #3a3a3a; /* Slightly lighter grey for odd rows */
        }

        /* Specific column widths */
        .table-container th:nth-child(1),
        .table-container td:nth-child(1) {
            width: 150px; /* Small */
        }

        .table-container th:nth-child(2),
        .table-container td:nth-child(2) {
            width: 600px; /* Large */
        }

        .table-container th:nth-child(3),
        .table-container td:nth-child(3) {
            width: 80px; /* Small */
        }

        .table-container th:nth-child(4),
        .table-container td:nth-child(4),
        .table-container th:nth-child(5),
        .table-container td:nth-child(5) {
            width: 100px; 
        }

        .table-container th:nth-child(6),
        .table-container td:nth-child(6) {
            width: 180px; 
        }

        input[type="submit"].btn-danger {
        background-color: rgb(200, 53, 69); 
        color: #fff;
        border: 1px solid rgb(255, 255, 255);
        padding: 10px 20px;
        cursor: pointer;
        font-size: 16px;
        border-radius: 4px;
        transition: background-color 0.3s ease; 
        font-weight: bold;
        }

        input[type="submit"].btn-danger:hover {
        background-color: rgb(200, 35, 51); 
        }

    </style>
</head>
<body>

<div class="indent5">
    <h1>📝NPC Biography Management</h1>
    <h3><strong>Make sure that all names with spaces are replaced with underscores _ and all names are lowercase!</strong></h3>
    <h4>Example: Mjoll the Lioness becomes mjoll_the_lioness</h4>

    <?php
    if (!empty($message)) {
        echo '<div class="message">';
        echo $message;
        echo '</div>';
    }
    ?>

    <h2>Single NPC Upload</h2>
    <form action="" method="post">
        <label for="npc_name">NPC Name:</label>
        <input type="text" name="npc_name" id="npc_name" required>

        <label for="npc_pers">NPC Personality:</label>
        <textarea name="npc_pers" id="npc_pers" rows="5" required></textarea>

        <label for="melotts_voiceid">Melotts Voice ID (optional):</label>
        <input type="text" name="melotts_voiceid" id="melotts_voiceid">

        <label for="xtts_voiceid">XTTS Voice ID (optional):</label>
        <input type="text" name="xtts_voiceid" id="xtts_voiceid">

        <label for="xvasynth_voiceid">xVASynth Voice ID (optional):</label>
        <input type="text" name="xvasynth_voiceid" id="xvasynth_voiceid">

        <input type="submit" name="submit_individual" value="Submit">
    </form>
    <p>You do not need to fill in the Voice ID fields. To understand the logic of how they work, read <a href="https://docs.google.com/document/d/12KBar_VTn0xuf2pYw9MYQd7CKktx4JNr_2hiv4kOx3Q/edit?tab=t.0#heading=h.dg9vyldrq648" style="color:yellow;" target="_blank">the manual page here</a>.</p>

    <h2>Batch Upload</h2>
    <form action="" method="post" enctype="multipart/form-data">
        <label for="csv_file">Select .csv file to upload:</label>
        <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
        <input type="submit" name="submit_csv" value="Upload CSV">
    </form>
    <p>Do not change the CSV column format. You can ignore the npc_misc column, it does not do anything currently.</p>
    <form action="" method="get">
        <input type="hidden" name="action" value="download_example">
        <input type="submit" value="Download Example CSV">
    </form>
</div>
<p>You can verify that NPC data has been uploaded successfully by going to 
   <b>Server Actions -> Database Manager -> dwemer -> public -> npc_templates_custom</b></p>
<p>All uploaded biographies will be saved into the <code>npc_templates_custom</code> table. This overwrites any entries in the regular table.</p>
<p>Also you can check the merged table at 
   <b>Server Actions -> Database Manager -> dwemer -> public -> Views (Top bar) -> combind_npc_templates</b>
</p>
<br>
<div class="indent5">
<h2>Delete All Custom Character Entries</h2>
<p>You can download a backup of the full character database in the<a href="https://discord.gg/NDn9qud2ug" style="color: yellow;" target="_blank" rel="noopener"> csv files channel in our discord</a>.</p>
<form action="" method="post">
    <input 
        type="submit" 
        name="truncate_npc" 
        value="Factory Reset NPC Overide Table"
        class="btn-danger"
        onclick="return confirm('Are you absolutely sure you want to DELETE ALL ENTRIES the npc_templates_custom table? This action is IRREVERSIBLE!');"
    >
</form>
</div>
<br>
<?php
$letter = isset($_GET['letter']) ? strtoupper($_GET['letter']) : '';

// Build query based on optional filter
if (!empty($letter) && ctype_alpha($letter) && strlen($letter) === 1) {
    // Filter by first letter
    $query_combined = "
        SELECT npc_name, npc_pers, npc_misc, melotts_voiceid, xtts_voiceid, xvasynth_voiceid
        FROM {$schema}.combined_npc_templates
        WHERE npc_name ILIKE $1
        ORDER BY npc_name ASC
    ";
    $params_combined = [$letter . '%'];
    $result_combined = pg_query_params($conn, $query_combined, $params_combined);
} else {
    // No filter: show all
    $query_combined = "
        SELECT npc_name, npc_pers, npc_misc, melotts_voiceid, xtts_voiceid, xvasynth_voiceid
        FROM {$schema}.combined_npc_templates
        ORDER BY npc_name ASC
    ";
    $result_combined = pg_query($conn, $query_combined);
}

// ----------------------------------------------
// Display the filter buttons + Table
// ----------------------------------------------
echo '<h2>NPC Templates Database</h2>';
echo '<p>These are the current biographies in the CHIM database that will be pulled when a new profile is created for a character.</p>';
echo '<p>Once a character has been activated, use their profile in the Configuration Wizard to make further changes.</p>';
echo '<p><b>It is OK that melotts_voiceid and xtts_voiceid columns are empty!</b> They are just for custom voice overrides as we use code to automatically assign them a voice if it is empty. <a href="https://docs.google.com/document/d/12KBar_VTn0xuf2pYw9MYQd7CKktx4JNr_2hiv4kOx3Q/edit?tab=t.0#heading=h.dg9vyldrq648" style="color:yellow;" target="_blank">Read this to learn how voice IDs are assigned.</a></p>';


echo '<div class="filter-buttons">';
echo '<a href="?" class="alphabet-button">All</a>';
foreach (range('A', 'Z') as $char) {
    echo '<a href="?letter=' . $char . '" class="alphabet-button">' . $char . '</a>';
}
echo '</div>';

// Start table container
if ($result_combined) {
    echo '<div class="table-container">';
    echo '<table>';
    echo '<tr>';
    echo '  <th>npc_name</th>';
    echo '  <th>npc_pers</th>';
    echo '  <th>npc_misc</th>';
    echo '  <th>melotts_voiceid</th>';
    echo '  <th>xtts_voiceid</th>';
    echo '  <th>xvasynth_voiceid</th>';
    echo '</tr>';

    $rowCountCombined = 0;
    while ($row = pg_fetch_assoc($result_combined)) {
        echo '<tr>';
        // Use ?? '' to avoid passing null to htmlspecialchars
        echo '  <td>' . htmlspecialchars($row['npc_name'] ?? '') . '</td>';
        echo '  <td>' . nl2br(htmlspecialchars($row['npc_pers'] ?? '')) . '</td>';
        echo '  <td>' . nl2br(htmlspecialchars($row['npc_misc'] ?? '')) . '</td>';
        echo '  <td>' . htmlspecialchars($row['melotts_voiceid'] ?? '') . '</td>';
        echo '  <td>' . htmlspecialchars($row['xtts_voiceid'] ?? '') . '</td>';
        echo '  <td>' . htmlspecialchars($row['xvasynth_voiceid'] ?? '') . '</td>';
        echo '</tr>';
        
        $rowCountCombined++;
    }
    echo '</table>';
    echo '</div>';

    if ($rowCountCombined === 0) {
        echo '<p>No combined NPC templates found.</p>';
    }
} else {
    echo '<p>Error fetching combined NPC templates: ' . pg_last_error($conn) . '</p>';
}

echo '</div>'; 

// Close the database connection
pg_close($conn);
?>

</body>
</html>
