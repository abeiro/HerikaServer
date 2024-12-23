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

// Include templates if available
// include("tmpl/head.html");
// $debugPaneLink = false;
// include("tmpl/navbar.php");

// Begin output buffering if necessary
// ob_start();

// Initialize message variable
$message = '';

// Connect to the database
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");

if (!$conn) {
    echo "<div class='message'>Failed to connect to database: " . pg_last_error() . "</div>";
    exit;
}

// Check if the individual form has been submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_individual'])) {
    $topic = strtolower(trim($_POST['topic'] ?? ''));
    $topic_desc = $_POST['topic_desc'] ?? '';

    if (!empty($topic) && !empty($topic_desc)) {
        // Prepare and execute the INSERT statement with ON CONFLICT
        $query = "
            INSERT INTO $schema.oghma (topic, topic_desc)
            VALUES ($1, $2)
            ON CONFLICT (topic)
            DO UPDATE SET
                topic_desc = EXCLUDED.topic_desc;
        ";
        $result = pg_query_params($conn, $query, array($topic, $topic_desc));

        if ($result) {
            $message .= "<p>Data inserted successfully!</p>";

            // Now run the update command
            $update_query = "
                UPDATE $schema.oghma
                SET native_vector = setweight(to_tsvector(coalesce(topic, '')), 'A') || setweight(to_tsvector(coalesce(topic_desc, '')), 'B')
            ";
            $update_result = pg_query($conn, $update_query);

            if ($update_result) {
                $message .= "<p>Vectors updated successfully.</p>";
            } else {
                $message .= "<p>Error updating vectors: " . pg_last_error($conn) . "</p>";
            }
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
                    // Assuming CSV columns are in order: topic, topic_desc
                    $topic = strtolower(trim($data[0]));
                    $topic_desc = trim($data[1]);

                    if (!empty($topic) && !empty($topic_desc)) {
                        // Prepare and execute the INSERT statement with ON CONFLICT
                        $query = "
                            INSERT INTO $schema.oghma (topic, topic_desc)
                            VALUES ($1, $2)
                            ON CONFLICT (topic)
                            DO UPDATE SET
                                topic_desc = EXCLUDED.topic_desc;
                        ";
                        $result = pg_query_params($conn, $query, array($topic, $topic_desc));

                        if ($result) {
                            $rowCount++;
                        } else {
                            $message .= "<p>Error processing row with topic '$topic': " . pg_last_error($conn) . "</p>";
                        }
                    } else {
                        $message .= "<p>Skipping empty or invalid row in CSV.</p>";
                    }
                }
                fclose($handle);

                $message .= "<p>$rowCount records inserted or updated successfully from the CSV file.</p>";

                // Run the SQL command to update 'native_vector' after data insertion
                $update_query = "
                    UPDATE $schema.oghma
                    SET native_vector = 
                        setweight(to_tsvector(coalesce(topic, '')), 'A') ||
                        setweight(to_tsvector(coalesce(topic_desc, '')), 'B');
                ";
                $update_result = pg_query($conn, $update_query);

                if ($update_result) {
                    $message .= "<p>Vectors updated successfully.</p>";
                } else {
                    $message .= "<p>Error updating vectors:" . pg_last_error($conn) . "</p>";
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

// Handle the download request for the example CSV
if (isset($_GET['action']) && $_GET['action'] === 'download_example') {
    // Define the path to the example CSV file
    $filePath = realpath(__DIR__ . '/../data/oghma_example.csv');

    if (file_exists($filePath)) {
        // Set headers to initiate file download
        header('Content-Description: File Transfer');
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="oghma_example.csv"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));

        // Clear output buffering to avoid any additional output
        if (ob_get_length()) ob_end_clean();
        flush();

        // Read the file and send it to the output buffer
        readfile($filePath);
        exit;
    } else {
        $message .= '<p>Example CSV file not found.</p>';
    }
}

// Handle update entry request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_entry') {
    $topic = strtolower(trim($_POST['topic'] ?? ''));
    $topic_desc = $_POST['topic_desc'] ?? '';

    if (!empty($topic) && !empty($topic_desc)) {
        // Prepare and execute the UPDATE statement
        $query = "
            UPDATE $schema.oghma
            SET topic_desc = $1,
                native_vector = setweight(to_tsvector(coalesce(topic, '')), 'A') || setweight(to_tsvector(coalesce($1, '')), 'B')
            WHERE topic = $2
        ";
        $result = pg_query_params($conn, $query, array($topic_desc, $topic));

        if ($result) {
            $message .= "<p>Entry updated successfully!</p>";
        } else {
            $message .= "<p>An error occurred while updating data: " . pg_last_error($conn) . "</p>";
        }
    } else {
        $message .= '<p>Please fill in all required fields.</p>';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <title>📙CHIM - Oghma Infinium Management</title>
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

        input[type="text"],
        input[type="file"] {
            width: 100%;
            padding: 6px;
            margin-top: 5px;
            margin-bottom: 15px;
            border: 1px solid #555555; /* Darker borders */
            border-radius: 3px;
            background-color: #4a4a4a; /* Dark input backgrounds */
            color: #f8f9fa; /* Light text inside inputs */
            font-family: Arial, sans-serif; /* Ensures consistent font */
            font-size: 14px; /* Sets a readable font size */
        }

        /* Styles specifically for textarea */
        textarea {
            width: 100%;
            padding: 6px;
            margin-top: 5px;
            margin-bottom: 15px;
            border: 1px solid #555555; 
            border-radius: 3px;
            background-color: #4a4a4a; 
            color: #f8f9fa; 
            resize: vertical; 
            font-family: Arial, sans-serif; 
            font-size: 14px; 
            height: 200px; 

        }

        input[type="submit"], button {
            background-color: #007bff;
            border: none;
            color: white;
            border-radius: 5px; 
            cursor: pointer;
            padding: 5px 15px; 
            font-size: 18px;   
            font-weight: bold; 
            transition: background-color 0.3s ease; 
            margin-top: 10px;
        }

        input[type="submit"]:hover, button:hover {
            background-color: #0056b3; 
        }

        .message {
            background-color: #444444; 
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #555555;
            max-width: 600px;
            margin-bottom: 20px;
            color: #f8f9fa; 
        }

        .message p {
            margin: 0;
        }

        .response-container {
            margin-top: 20px;
        }

        .indent {
            padding-left: 10ch;
        }

        .indent5 {
            padding-left: 5ch; 
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

        table {
            width: 100%;
            max-width: 1400px;
            border-collapse: collapse;
            background-color: #3a3a3a; 
            margin-bottom: 20px;
        }

        th, td {
            border: 1px solid #555555; 
            padding: 8px;
            text-align: left;
            vertical-align: top; 
            color: #f8f9fa; 
        }

        th {
            background-color: #4a4a4a; 
            font-weight: bold;
        }

        tr:nth-child(even) {
            background-color: #2c2c2c; 
        }

        .filter-buttons {
            margin-bottom: 20px;
            max-width: 1400px;
        }

        .filter-buttons form {
            display: inline-block;
            margin: 2px;
        }

        .filter-buttons button {
            background-color: #007bff;
            border: none;
            color: white;
            padding: 6px 10px;
            margin: 0;
            border-radius: 3px;
            cursor: pointer;
            font-size: 14px;
        }

        .filter-buttons button:hover {
            background-color: #0056b3;
        }

        .table-container {
            max-height: 800px; 
            overflow-y: auto;
            margin-bottom: 20px;
            max-width: 1400px;
        }


        .filter-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 5px; 
            max-width: 1400px; 
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

        .edit-button, .cancel-button {
            background-color: #28a745; 
            color: white;
            padding: 5px 10px;
            margin-left: 5px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 14px;
        }

        .edit-button:hover, .cancel-button:hover {
            background-color: #218838; 
        }

        .cancel-button {
            background-color: #dc3545; 
        }

        .cancel-button:hover {
            background-color: #c82333; 
        }

        .table-container th, .table-container td {
            border: 1px solid #555;
            padding: 8px;
            text-align: left;
            word-wrap: break-word;    
            overflow-wrap: break-word; 
        }

        .table-container th {
            background-color: #4a4a4a;
        }

        .table-container th:nth-child(1),
        .table-container td:nth-child(1) {
            width: 150px; 
        }

        /* 2nd Column: Large */
        .table-container th:nth-child(2),
        .table-container td:nth-child(2) {
            width: 600px; 
        }
    </style>
</head>
<div class="indent5">
    <h1><img src="images/oghma_infinium.png" alt="Oghma Infinium" style="vertical-align:bottom;" width="32" height="32">Oghma Infinium Management</h1>
    <p>The <b>Oghma Infinium</b> is a "Skyrim Encyclopedia" that AI NPC's will use to help them roleplay. <a href="https://www.youtube.com/watch?v=vY8cnwDtACs" target="_blank" title="Watch the explanation video" style="color: yellow;">This video explains it.</a></p>
    <p>To use it you must have [MINIME_T5] and [OGHMA_INFINIUM] enabled in the default profile. You also need Minime-T5 installed and running.</p>
    <p>We recommend to keep entries limited to "generic household knowledge" as ALL NPC's will have access to all entries.</p>
    <h3><strong>Ensure all topic titles are lowercase and spaces are replaced with underscores (_).</strong></h3>
    <h4>Example: "Fishy Stick" becomes "fishy_stick"</h4>
    <p>There is no format restriction for the description.</p>
    <?php
    if (!empty($message)) {
        echo '<div class="message">';
        echo $message;
        echo '</div>';
    }
    ?>

    <h2>Single Topic Upload</h2>
    <form action="" method="post">
        <label for="topic">Topic:</label>
        <input type="text" name="topic" id="topic" required>

        <label for="topic_desc">Topic Description:</label>
        <textarea name="topic_desc" id="topic_desc" rows="5" required></textarea>

        <input type="submit" name="submit_individual" value="Submit">
    </form>

    <h2>Batch Upload</h2>
    <form action="" method="post" enctype="multipart/form-data">
        <label for="csv_file">Select .csv file to upload:</label>
        <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
        <input type="submit" name="submit_csv" value="Upload CSV">
    </form>
    <p>You can download a backup of the full oghma database in the<a href="https://discord.gg/NDn9qud2ug" style="color: yellow;" target="_blank" rel="noopener">csv files channel in our discord</a>.</p>
    <form action="" method="get">
        <input type="hidden" name="action" value="download_example">
        <input type="submit" value="Download Example CSV">
    </form>

    <!-- Removed the View Oghma button -->
</div>
<p>You can verify that the entry has been uploaded successfully by navigating to <b>Server Actions -> Database Manager -> dwemer -> public -> oghma</b></p>
<p>You can see how it picks a relevant article during conversation by navigating to <b>Server Actions -> Database Manager -> dwemer -> public -> audit_memory</b></p>
<p>All uploaded topics will be saved into the <code>oghma</code> table. This overwrites any existing entries with the same topic.</p>
</div>
<br>
<?php
// Always display the Oghma entries with filtering

// Get the letter filter if set
$letter = isset($_GET['letter']) ? $_GET['letter'] : '';
$letter = strtoupper($letter); // Ensure the letter is uppercase

// Generate the alphabet buttons
echo '<h2>Oghma Infinium Entries</h2>';
echo '<div class="filter-buttons">';

// ALL button
echo '<a href="?" class="alphabet-button">All</a>';

// Generate A-Z buttons
foreach (range('A', 'Z') as $char) {
    echo '<a href="?letter=' . $char . '" class="alphabet-button">' . $char . '</a>';
}

echo '</div>';

// Prepare the SQL query with optional filtering
if (!empty($letter) && ctype_alpha($letter) && strlen($letter) === 1) {
    $query = "SELECT topic, topic_desc FROM $schema.oghma WHERE topic ILIKE $1 ORDER BY topic ASC";
    $params = array($letter . '%');
} else {
    $query = "SELECT topic, topic_desc FROM $schema.oghma ORDER BY topic ASC";
    $params = array();
}

$result = pg_query_params($conn, $query, $params);

if ($result) {
    echo '<div class="table-container">';
    echo '<table>';
    echo '<tr><th>Topic</th><th>Topic Description</th></tr>';
    $rowCount = 0;
    while ($row = pg_fetch_assoc($result)) {
        $topic = htmlspecialchars($row['topic']);
        $topic_desc = htmlspecialchars($row['topic_desc']);

        echo '<tr>';
        echo '<td>' . $topic . '</td>';

        echo '<td>';

        // The display div
        echo '<div class="topic-desc-display" id="display-' . $topic . '">';
        echo nl2br($topic_desc);
        echo '<br>';
        echo '</div>';

        echo '</td>';

        echo '</tr>';
        $rowCount++;
    }
    echo '</table>';
    echo '</div>';

    if ($rowCount === 0) {
        echo '<p>No entries found for the selected filter.</p>';
    }

    echo '</div>';
} else {
    echo '<p>Error fetching Oghma entries: ' . pg_last_error($conn) . '</p>';
}

// Close the database connection
pg_close($conn);
?>

</body>
</html>
