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

//
// ────────────────────────────────────────────────────────────────────
//   INDIVIDUAL UPLOAD
// ────────────────────────────────────────────────────────────────────
//
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_individual'])) {
    $npc_name   = strtolower(trim($_POST['npc_name'] ?? ''));
    $npc_pers   = $_POST['npc_pers'] ?? '';
    $npc_dynamic = (isset($_POST['npc_dynamic']) && trim($_POST['npc_dynamic']) !== '')
        ? trim($_POST['npc_dynamic'])
        : null;
    $npc_misc = (isset($_POST['npc_misc']) && trim($_POST['npc_misc']) !== '')
        ? trim($_POST['npc_misc'])
        : '';
    $melotts_voiceid   = (!empty($_POST['melotts_voiceid']))   ? trim($_POST['melotts_voiceid'])   : null;
    $xtts_voiceid      = (!empty($_POST['xtts_voiceid']))      ? trim($_POST['xtts_voiceid'])      : null;
    $xvasynth_voiceid  = (!empty($_POST['xvasynth_voiceid']))  ? trim($_POST['xvasynth_voiceid'])  : null;

    if (!empty($npc_name) && !empty($npc_pers)) {
        $query = "
            INSERT INTO {$schema}.npc_templates_custom
                (npc_name, npc_dynamic, npc_pers, npc_misc, melotts_voiceid, xtts_voiceid, xvasynth_voiceid)
            VALUES ($1, $2, $3, $4, $5, $6, $7)
            ON CONFLICT (npc_name)
            DO UPDATE SET
                npc_dynamic = EXCLUDED.npc_dynamic,
                npc_pers = EXCLUDED.npc_pers,
                npc_misc = EXCLUDED.npc_misc,
                melotts_voiceid = EXCLUDED.melotts_voiceid,
                xtts_voiceid = EXCLUDED.xtts_voiceid,
                xvasynth_voiceid = EXCLUDED.xvasynth_voiceid
        ";

        $params = [
            $npc_name,
            $npc_dynamic,
            $npc_pers,
            $npc_misc,
            $melotts_voiceid,
            $xtts_voiceid,
            $xvasynth_voiceid
        ];

        $result = pg_query_params($conn, $query, $params);

        if ($result) {
            $message .= "<p>NPC data inserted/updated successfully!</p>";
        } else {
            $message .= "<p>Error inserting/updating NPC data: " . pg_last_error($conn) . "</p>";
        }
    } else {
        $message .= "<p>Please fill in all required fields: NPC Name and NPC Static Bio.</p>";
    }
}

//
// ────────────────────────────────────────────────────────────────────
//   CSV UPLOAD
// ────────────────────────────────────────────────────────────────────
//
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
            // Try to detect file encoding
            $encoding = mb_detect_encoding(file_get_contents($fileTmpPath), 'UTF-8', true);

            if (($handle = fopen($fileTmpPath, 'r')) !== false) {
                //
                // ──────────────────────────────────────────────────────────────────
                //   Read header row to map columns
                // ──────────────────────────────────────────────────────────────────
                //
                $header = fgetcsv($handle, 1000, ',');
                if (!$header) {
                    $message .= '<p>Could not read the header row from the CSV.</p>';
                    fclose($handle);
                } else {
                    // Normalize header labels (lowercase, trim, etc.)
                    $headerMap = [];
                    foreach ($header as $i => $colName) {
                        $normalized = strtolower(trim($colName));
                        $headerMap[$normalized] = $i;
                    }

                    // Check relevant columns by name
                    //
                    // * npc_name (required)
                    // * npc_dynamic (optional)
                    // * npc_pers (required)
                    // * npc_misc (optional if you want to skip it, set it to "")
                    // * melotts_voiceid, xtts_voiceid, xvasynth_voiceid (optional)
                    //

                    $rowCount = 0;
                    while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                        // Use null or empty string if column does not exist or row data is missing
                        $npc_name = '';
                        if (isset($headerMap['npc_name']) && isset($data[$headerMap['npc_name']])) {
                            $npc_name = strtolower(trim($data[$headerMap['npc_name']]));
                        }

                        $npc_pers = '';
                        if (isset($headerMap['npc_pers']) && isset($data[$headerMap['npc_pers']])) {
                            $npc_pers = trim($data[$headerMap['npc_pers']]);
                        }

                        // npc_dynamic is optional
                        $npc_dynamic = null;
                        if (isset($headerMap['npc_dynamic']) && isset($data[$headerMap['npc_dynamic']])) {
                            $temp = trim($data[$headerMap['npc_dynamic']]);
                            $npc_dynamic = ($temp !== '') ? $temp : null;
                        }

                        // npc_misc is not used, but we can store it or default to ''
                        $npc_misc = '';
                        if (isset($headerMap['npc_misc']) && isset($data[$headerMap['npc_misc']])) {
                            $npc_misc = trim($data[$headerMap['npc_misc']]);
                        }

                        // Voice IDs are optional, so store null if missing/empty
                        $melotts_voiceid = null;
                        if (isset($headerMap['melotts_voiceid']) && isset($data[$headerMap['melotts_voiceid']])) {
                            $temp = trim($data[$headerMap['melotts_voiceid']]);
                            $melotts_voiceid = ($temp !== '') ? $temp : null;
                        }

                        $xtts_voiceid = null;
                        if (isset($headerMap['xtts_voiceid']) && isset($data[$headerMap['xtts_voiceid']])) {
                            $temp = trim($data[$headerMap['xtts_voiceid']]);
                            $xtts_voiceid = ($temp !== '') ? $temp : null;
                        }

                        $xvasynth_voiceid = null;
                        if (isset($headerMap['xvasynth_voiceid']) && isset($data[$headerMap['xvasynth_voiceid']])) {
                            $temp = trim($data[$headerMap['xvasynth_voiceid']]);
                            $xvasynth_voiceid = ($temp !== '') ? $temp : null;
                        }

                        // Convert to UTF-8 if not already
                        if ($encoding !== 'UTF-8') {
                            $npc_name           = iconv('Windows-1252', 'UTF-8//IGNORE', $npc_name);
                            $npc_pers           = iconv('Windows-1252', 'UTF-8//IGNORE', $npc_pers);
                            $npc_dynamic        = ($npc_dynamic !== null)
                                                    ? iconv('Windows-1252', 'UTF-8//IGNORE', $npc_dynamic)
                                                    : null;
                            $npc_misc           = iconv('Windows-1252', 'UTF-8//IGNORE', $npc_misc);
                            $melotts_voiceid    = ($melotts_voiceid !== null)
                                                    ? iconv('Windows-1252', 'UTF-8//IGNORE', $melotts_voiceid)
                                                    : null;
                            $xtts_voiceid       = ($xtts_voiceid !== null)
                                                    ? iconv('Windows-1252', 'UTF-8//IGNORE', $xtts_voiceid)
                                                    : null;
                            $xvasynth_voiceid   = ($xvasynth_voiceid !== null)
                                                    ? iconv('Windows-1252', 'UTF-8//IGNORE', $xvasynth_voiceid)
                                                    : null;
                        }

                        // Skip if either required field is empty
                        if (empty($npc_name) || empty($npc_pers)) {
                            $message .= "<p>Skipping row with missing npc_name or npc_pers.</p>";
                            continue;
                        }

                        // Insert or Update
                        $query = "
                            INSERT INTO $schema.npc_templates_custom 
                                (npc_name, npc_dynamic, npc_pers, npc_misc, 
                                 melotts_voiceid, xtts_voiceid, xvasynth_voiceid)
                            VALUES ($1, $2, $3, $4, $5, $6, $7)
                            ON CONFLICT (npc_name)
                            DO UPDATE SET
                                npc_dynamic       = EXCLUDED.npc_dynamic,
                                npc_pers          = EXCLUDED.npc_pers,
                                npc_misc          = EXCLUDED.npc_misc,
                                melotts_voiceid   = EXCLUDED.melotts_voiceid,
                                xtts_voiceid      = EXCLUDED.xtts_voiceid,
                                xvasynth_voiceid  = EXCLUDED.xvasynth_voiceid
                        ";

                        $params = [
                            $npc_name,
                            $npc_dynamic,
                            $npc_pers,
                            $npc_misc,
                            $melotts_voiceid,
                            $xtts_voiceid,
                            $xvasynth_voiceid
                        ];

                        $result = pg_query_params($conn, $query, $params);

                        if ($result) {
                            $rowCount++;
                        } else {
                            $message .= "<p>Error processing row (npc_name: '$npc_name'): " . pg_last_error($conn) . "</p>";
                        }
                    } // end while

                    fclose($handle);
                    $message .= "<p>$rowCount records inserted or updated successfully from the CSV file.</p>";
                }
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

//
// ────────────────────────────────────────────────────────────────────
//   TRUNCATE NPC TABLE
// ────────────────────────────────────────────────────────────────────
//
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['truncate_npc'])) {
    $truncateQuery = "TRUNCATE TABLE $schema.npc_templates_custom RESTART IDENTITY CASCADE";
    $truncateResult = pg_query($conn, $truncateQuery);

    if ($truncateResult) {
        $message .= "<p style='color: #ff6464; font-weight: bold;'>The npc_templates_custom table has been emptied successfully.</p>";
    } else {
        $message .= "<p>Error emptying npc_templates_custom table: " . pg_last_error($conn) . "</p>";
    }
}

//
// ────────────────────────────────────────────────────────────────────
//   DOWNLOAD EXAMPLE
// ────────────────────────────────────────────────────────────────────
//
if (isset($_GET['action']) && $_GET['action'] === 'download_example') {
    // Define the path to the example CSV file
    $filePath = realpath(__DIR__ . '/../data/example_bios_format.csv');

    if (file_exists($filePath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="example.csv"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        ob_end_clean();
        flush();
        readfile($filePath);
        exit;
    } else {
        $message .= '<p>Example CSV file not found.</p>';
    }
}

// 1. Update the edit modal form to match the Oghma styling:
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_single') {
    $npc_name_original = $_POST['npc_name_original'] ?? '';
    $npc_name = strtolower(trim($_POST['npc_name'] ?? ''));
    $npc_pers = $_POST['npc_pers'] ?? '';
    $npc_dynamic = (isset($_POST['npc_dynamic']) && trim($_POST['npc_dynamic']) !== '') 
        ? trim($_POST['npc_dynamic']) 
        : null;
    $npc_misc = (isset($_POST['npc_misc']) && trim($_POST['npc_misc']) !== '') 
        ? trim($_POST['npc_misc']) 
        : '';
    $melotts_voiceid = (!empty($_POST['melotts_voiceid'])) ? trim($_POST['melotts_voiceid']) : null;
    $xtts_voiceid = (!empty($_POST['xtts_voiceid'])) ? trim($_POST['xtts_voiceid']) : null;
    $xvasynth_voiceid = (!empty($_POST['xvasynth_voiceid'])) ? trim($_POST['xvasynth_voiceid']) : null;

    if (!empty($npc_name) && !empty($npc_pers)) {
        $query = "
            UPDATE {$schema}.npc_templates_custom 
            SET 
                npc_name = $1,
                npc_pers = $2,
                npc_dynamic = $3,
                npc_misc = $4,
                melotts_voiceid = $5,
                xtts_voiceid = $6,
                xvasynth_voiceid = $7
            WHERE npc_name = $8
        ";

        $params = [
            $npc_name,
            $npc_pers,
            $npc_dynamic,
            $npc_misc,
            $melotts_voiceid,
            $xtts_voiceid,
            $xvasynth_voiceid,
            $npc_name_original
        ];

        $result = pg_query_params($conn, $query, $params);

        if ($result) {
            $message .= "<p>NPC data updated successfully!</p>";
        } else {
            $message .= "<p>Error updating NPC data: " . pg_last_error($conn) . "</p>";
        }
    } else {
        $message .= "<p>Please fill in all required fields: NPC Name and NPC Static Bio.</p>";
    }
}

// 1. Update the edit modal form action to include the current letter:
$currentLetter = isset($_GET['letter']) ? htmlspecialchars($_GET['letter']) : '';
$formAction = $currentLetter ? "?letter={$currentLetter}#table" : "?#table";
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
            max-height: 900px;
            overflow-y: auto;
            overflow-x: auto;
            margin-bottom: 20px;
            max-width: 100%;
        }

        .table-container table {
            width: 100%;
            border-collapse: collapse; /* Ensure borders collapse into a single border */
            background-color: #3a3a3a; /* Base background color */
        }

        .table-container th, .table-container td {
            padding: 8px;
            text-align: left;
            word-wrap: break-word;
            overflow-wrap: break-word;
            color: #f8f9fa; /* Text color */
            border: 1px solid #555555; /* Border color for cells */
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
            width: 150px; /* e.g., for npc_name */
        }

        .table-container th:nth-child(2),
        .table-container td:nth-child(2) {
            width: 600px; /* e.g., for npc_pers */
        }

        .table-container th:nth-child(3),
        .table-container td:nth-child(3) {
            width: 400px; /* small or adjust as needed */
        }

        .table-container th:nth-child(4),
        .table-container td:nth-child(4) {
            width: 120px; /* e.g., for npc_misc */
        }
        .table-container th:nth-child(5),
        .table-container td:nth-child(5),
        .table-container th:nth-child(6),
        .table-container td:nth-child(6) {
            width: 100px; 
        }

        .table-container th:nth-child(7),
        .table-container td:nth-child(7) {
            width: 120px; 
        }

        .table-container th:nth-child(8),
        .table-container td:nth-child(8) {
            width: 100px; 
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

        /* Modal styles */
        .modal-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 1000;
        }

        .modal-container {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: #3a3a3a;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            width: 60%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            z-index: 1001;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            margin: 0;
            color: #fff;
            font-size: 1.5em;
        }

        .modal-body form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #4a4a4a;
        }

        /* Button styles */
        .action-button {
            display: inline-block;
            padding: 8px 12px;
            color: #fff;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease;
            border: none;
        }

        .action-button.add-new {
            background-color: #28a745;
        }
        .action-button.add-new:hover {
            background-color: #218838;
        }

        .action-button.edit {
            background-color: #17a2b8;
        }
        .action-button.edit:hover {
            background-color: #138496;
        }

        /* Form field styles */
        .modal-body small {
            display: block;
            color: #aaa;
            margin-bottom: 5px;
        }

        .modal-body input[type="text"],
        .modal-body textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #555;
            border-radius: 4px;
            background-color: #4a4a4a;
            color: #fff;
            margin-bottom: 10px;
        }

        .modal-body textarea {
            resize: vertical;
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

    <h2>Batch Upload</h2>
    <div style="
        background-color: #3a3a3a;
        padding: 15px;
        border-radius: 5px;
        border: 1px solid #4a4a4a;
        max-width: 600px;
    ">
        <form action="" method="post" enctype="multipart/form-data" style="
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin: 0;
            padding: 0;
            background: none;
            border: none;
        ">
            <div>
                <label for="csv_file" style="display: block; margin-bottom: 5px; font-weight: bold;">Select .csv file to upload:</label>
                <input type="file" name="csv_file" id="csv_file" accept=".csv" required style="
                    width: 100%;
                    padding: 6px;
                    margin-bottom: 10px;
                    border: 1px solid #555555;
                    border-radius: 3px;
                    background-color: #4a4a4a;
                    color: #f8f9fa;
                ">
            </div>
            <div style="display: flex; gap: 10px;">
                <input type="submit" name="submit_csv" value="Upload CSV" class="action-button" style="background-color: #28a745; color: white;">
                <a href="?action=download_example" class="action-button" style="background-color: #007bff; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none;">Download Example CSV</a>
            </div>
                <p>You can verify that NPC data has been uploaded successfully by going to 
                <b>Server Actions -> Database Manager -> dwemer -> public -> npc_templates_custom</b>.</p>
                <p>All uploaded biographies will be saved into the <code>npc_templates_custom</code> table. This overwrites any entries in the regular table.</p>
                <p>Also you can check the merged table at 
                <b>Server Actions -> Database Manager -> dwemer -> public -> Views (Top bar) -> combined_npc_templates</b>.</p>
        </form>
        <form action="" method="post" style="
            border: none; /* Remove border */
        ">
            <input 
                type="submit" 
                name="truncate_npc" 
                value="Factory Reset NPC Override Table"
                class="btn-danger"
                onclick="return confirm('Are you sure you want to DELETE ALL ENTRIES in npc_templates_custom? This action is IRREVERSIBLE!');"
            >
        </form>
        <p>This will just delete any custom NPC entires you have uploaded.</p>
        <p>You can download a backup of the full character database in the 
        <a href="https://discord.gg/NDn9qud2ug" style="color: yellow;" target="_blank" rel="noopener">
            csv files channel in our discord
        </a>.
    </div>
</div>


<br>
<?php
$letter = isset($_GET['letter']) ? strtoupper($_GET['letter']) : '';

// Build query based on optional filter
if (!empty($letter) && ctype_alpha($letter) && strlen($letter) === 1) {
    // Filter by first letter
    $query_combined = "
        SELECT npc_name, npc_dynamic, npc_pers, npc_misc, melotts_voiceid, xtts_voiceid, xvasynth_voiceid
        FROM {$schema}.combined_npc_templates
        WHERE npc_name ILIKE $1
        ORDER BY npc_name ASC
    ";
    $params_combined = [$letter . '%'];
    $result_combined = pg_query_params($conn, $query_combined, $params_combined);
} else {
    // No filter: show all
    $query_combined = "
        SELECT npc_name, npc_dynamic, npc_pers, npc_misc, melotts_voiceid, xtts_voiceid, xvasynth_voiceid
        FROM {$schema}.combined_npc_templates
        ORDER BY npc_name ASC
    ";
    $result_combined = pg_query($conn, $query_combined);
}

// Wrap the NPC Templates Database section in a div for indentation
echo '<div style="margin-left: 3em;" id="table">'; // Add id="table" here
echo '<h2>NPC Templates Database</h2>';
echo '<button onclick="openNewEntryModal()" class="action-button add-new">Add New Entry</button>';
echo '<h3>Note: This is just for editing an NPC entry before they are activated ingame. Any further edits should be done in the configuration wizard.</h3>';
echo '<br>';

// Alphabetic filter
echo '<div class="filter-buttons">';
echo '<a href="?#table" class="alphabet-button">All</a>'; // Add #table to the All link
foreach (range('A', 'Z') as $char) {
    echo '<a href="?letter=' . $char . '#table" class="alphabet-button">' . $char . '</a>'; // Add #table to each letter link
}
echo '</div>';

if ($result_combined) {
    echo '<div class="table-container">';
    echo '<table>';
    echo '<tr>';
    echo '  <th>npc_name</th>';
    echo '  <th>npc_pers</th>';
    echo '  <th>npc_dynamic</th>';
    echo '  <th>npc_misc</th>';
    echo '  <th>melotts_voiceid</th>';
    echo '  <th>xtts_voiceid</th>';
    echo '  <th>xvasynth_voiceid</th>';
    echo '  <th>Actions</th>';
    echo '</tr>';

    $rowCountCombined = 0;
    while ($row = pg_fetch_assoc($result_combined)) {
        echo '<tr>';
        echo '  <td>' . htmlspecialchars($row['npc_name'] ?? '') . '</td>';
        echo '  <td>' . nl2br(htmlspecialchars($row['npc_pers'] ?? '')) . '</td>';
        echo '  <td>' . ($row['npc_dynamic'] !== null ? nl2br(htmlspecialchars($row['npc_dynamic'])) : '') . '</td>';
        echo '  <td>' . ($row['npc_misc'] !== null ? nl2br(htmlspecialchars($row['npc_misc'])) : '') . '</td>';
        echo '  <td>' . htmlspecialchars($row['melotts_voiceid'] ?? '') . '</td>';
        echo '  <td>' . htmlspecialchars($row['xtts_voiceid'] ?? '') . '</td>';
        echo '  <td>' . htmlspecialchars($row['xvasynth_voiceid'] ?? '') . '</td>';
        
        // Add Edit button
        echo '<td style="white-space: nowrap;">';
        echo '<div style="display: flex; gap: 4px;">';
        $jsData = [
            'npc_name' => $row['npc_name'],
            'npc_pers' => $row['npc_pers'],
            'npc_dynamic' => $row['npc_dynamic'] ?? '',
            'npc_misc' => $row['npc_misc'] ?? '',
            'melotts_voiceid' => $row['melotts_voiceid'] ?? '',
            'xtts_voiceid' => $row['xtts_voiceid'] ?? '',
            'xvasynth_voiceid' => $row['xvasynth_voiceid'] ?? ''
        ];
        echo '<button onclick="openEditModal(' . 
            htmlspecialchars(str_replace(
                ["\r", "\n", "'"],
                [' ', ' ', "\\'"],
                json_encode($jsData)
            ), ENT_QUOTES, 'UTF-8') . 
            ')" class="action-button edit">Edit</button>';
        echo '</div>';
        echo '</td>';
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

echo '</div>'; // Close the indentation div

pg_close($conn);
?>

<div id="editModal" class="modal-backdrop">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">Edit NPC Entry</h2>
        </div>
        <div class="modal-body">
            <form action="<?php echo $formAction; ?>" method="post">
                <input type="hidden" name="action" value="update_single">
                <input type="hidden" name="npc_name_original" id="edit_npc_name_original">

                <label for="edit_npc_name">NPC Name:</label>
                <small>Make sure name is lowercase with underscores instead of spaces.</small>
                <input type="text" name="npc_name" id="edit_npc_name" required>

                <label for="edit_npc_pers">NPC Static Bio:</label>
                <small>Static tratits and background of the NPC.</small>
                <textarea name="npc_pers" id="edit_npc_pers" rows="8" required></textarea>

                <label for="edit_npc_dynamic">NPC Dynamic Bio:</label>
                <small>Optional: Dynamic personality traits.</small>
                <textarea name="npc_dynamic" id="edit_npc_dynamic" rows="8"></textarea>

                <label for="edit_npc_misc">NPC Misc:</label>
                <small>Optional: Oghma Knowledge Tags. Make sure to seperate with commas. <a href="https://docs.google.com/spreadsheets/d/1dcfctU-iOqprwy2BOc7___4Awteczgdlv8886KalPsQ/edit?pli=1&gid=338893641#gid=338893641" target="_blank" rel="noopener" style="color: yellow;"> Read more here !</a></small>
                <input type="text" name="npc_misc" id="edit_npc_misc">

                <label for="edit_melotts_voiceid">Melotts Voice ID:</label>
                <small>Optional: Custom voice override for Melotts.</small>
                <input type="text" name="melotts_voiceid" id="edit_melotts_voiceid">

                <label for="edit_xtts_voiceid">XTTS Voice ID:</label>
                <small>Optional: Custom voice override for XTTS.</small>
                <input type="text" name="xtts_voiceid" id="edit_xtts_voiceid">

                <label for="edit_xvasynth_voiceid">xVASynth Voice ID:</label>
                <small>Optional: Custom voice override for xVASynth.</small>
                <input type="text" name="xvasynth_voiceid" id="edit_xvasynth_voiceid">

                <div class="modal-footer">
                    <button type="submit" name="submit_individual" value="1" class="action-button" style="background-color: #28a745;">Save Changes</button>
                    <button type="button" onclick="closeEditModal()" class="action-button" style="background-color: #6c757d;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="newEntryModal" class="modal-backdrop">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">Add New NPC Entry</h2>
        </div>
        <div class="modal-body">
            <form action="<?php echo $formAction; ?>" method="post">
                <input type="hidden" name="submit_individual" value="1">

                <label for="new_npc_name">NPC Name:</label>
                <small>Make sure name is lowercase with underscores instead of spaces.</small>
                <input type="text" name="npc_name" id="new_npc_name" required>

                <label for="new_npc_pers">NPC Static Bio:</label>
                <small>Static tratits and background of the NPC.</small> 
                <textarea name="npc_pers" id="new_npc_pers" rows="8" required></textarea>

                <label for="new_npc_dynamic">NPC Dynamic Bio:</label>
                <small>Optional: Dynamic personality traits.</small>
                <textarea name="npc_dynamic" id="new_npc_dynamic" rows="8"></textarea>

                <label for="new_npc_misc">NPC Misc:</label>
                <small>Optional: Oghma Knowledge Tags. Make sure to seperate with commas. <a href="https://docs.google.com/spreadsheets/d/1dcfctU-iOqprwy2BOc7___4Awteczgdlv8886KalPsQ/edit?pli=1&gid=338893641#gid=338893641" target="_blank" rel="noopener" style="color: yellow;"> Read more here !</a></small>
                <input type="text" name="npc_misc" id="new_npc_misc">

                <label for="new_melotts_voiceid">Melotts Voice ID:</label>
                <small>Optional: Custom voice override for Melotts.</small>
                <input type="text" name="melotts_voiceid" id="new_melotts_voiceid">

                <label for="new_xtts_voiceid">XTTS Voice ID:</label>
                <small>Optional: Custom voice override for XTTS.</small>
                <input type="text" name="xtts_voiceid" id="new_xtts_voiceid">

                <label for="new_xvasynth_voiceid">xVASynth Voice ID:</label>
                <small>Optional: Custom voice override for xVASynth.</small>
                <input type="text" name="xvasynth_voiceid" id="new_xvasynth_voiceid">

                <div class="modal-footer">
                    <button type="submit" name="submit_individual" value="1" class="action-button" style="background-color: #28a745;">Save</button>
                    <button type="button" onclick="closeNewEntryModal()" class="action-button" style="background-color: #6c757d;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditModal(data) {
    try {
        const decodeHTML = (html) => {
            const txt = document.createElement("textarea");
            txt.innerHTML = html;
            return txt.value;
        };

        document.getElementById("edit_npc_name_original").value = decodeHTML(data.npc_name);
        document.getElementById("edit_npc_name").value = decodeHTML(data.npc_name);
        document.getElementById("edit_npc_pers").value = decodeHTML(data.npc_pers);
        document.getElementById("edit_npc_dynamic").value = decodeHTML(data.npc_dynamic);
        document.getElementById("edit_npc_misc").value = decodeHTML(data.npc_misc);
        document.getElementById("edit_melotts_voiceid").value = decodeHTML(data.melotts_voiceid);
        document.getElementById("edit_xtts_voiceid").value = decodeHTML(data.xtts_voiceid);
        document.getElementById("edit_xvasynth_voiceid").value = decodeHTML(data.xvasynth_voiceid);
        
        document.getElementById("editModal").style.display = "block";
        document.body.style.overflow = "hidden";
    } catch (error) {
        console.error("Error in openEditModal:", error);
        alert("There was an error opening the edit form. Please try again.");
    }
}

function closeEditModal() {
    document.getElementById("editModal").style.display = "none";
    document.body.style.overflow = "auto";
}

function deleteEntry() {
    const npcName = document.getElementById("edit_npc_name_original").value;
    if (confirm("Are you sure you want to delete: " + npcName + "?")) {
        const form = document.createElement("form");
        form.method = "POST";
        const currentLetter = new URLSearchParams(window.location.search).get('letter');
        form.action = currentLetter ? `?letter=${currentLetter}#table` : '?#table';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_single">
            <input type="hidden" name="npc_name" value="${npcName}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function openNewEntryModal() {
    document.getElementById("newEntryModal").style.display = "block";
    document.body.style.overflow = "hidden";
}

function closeNewEntryModal() {
    document.getElementById("newEntryModal").style.display = "none";
    document.body.style.overflow = "auto";
}
</script>

</body>
</html>
