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

?>

<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <title>📝CHIM - NPC Biography Upload</title>
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
            border: 1px solid #555555;
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
    </style>
</head>
<body>

<?php
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

// Close the database connection
pg_close($conn);

// Display the forms and messages
?>

<div class="indent5">
    <h1>📝NPC Biography Upload</h1>
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
    <p>You do not need to fill in the Voice ID fields. To understand the logic of how they work, read <a href="https://docs.google.com/document/d/12KBar_VTn0xuf2pYw9MYQd7CKktx4JNr_2hiv4kOx3Q/edit?tab=t.0#heading=h.dg9vyldrq648" target="_blank">the manual page here</a>.</p>

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
<p> You can verify that NPC data has been uploaded successfully by going to <b>Server Actions -> Database Manager -> dwemer -> public -> npc_templates_custom</b></p>
<p> All uploaded biographies will be saved into the <code>npc_templates_custom</code> table. This overwrites any entries in the regular table.</p>
</body>
</html>
