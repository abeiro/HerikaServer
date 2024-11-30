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

?>

<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <title>Oghma Topic Upload</title>
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
// Include navbar and other templates if available
// include("tmpl/head.html");
// $debugPaneLink = false;
// include("tmpl/navbar.php");

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
                $message .= "<p>Index updated successfully!</p>";
            } else {
                $message .= "<p>An error occurred while updating the index: " . pg_last_error($conn) . "</p>";
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
                    $message .= "<p>Vector updated successfully.</p>";
                } else {
                    $message .= "<p>Error updating 'native_vector' column: " . pg_last_error($conn) . "</p>";
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

// Close the database connection
pg_close($conn);

// Display the forms and messages
?>

<div class="indent5">
    <h1><img src="images/oghma_infinium.png" alt="Oghma Infinium" style="vertical-align:bottom;" width="32" height="32"> Oghma Infinium Entry Upload</h1>
    <p>The <b>Oghma Infinium</b> is a "Skyrim Encyclopedia" that AI NPC's will use to help them roleplay.</p>
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

    <form action="" method="get">
        <input type="hidden" name="action" value="download_example">
        <input type="submit" value="Download Example CSV">
    </form>
</div>
<p>You can verify that the entry has been uploaded successfully by navigating to <b>Server Actions -> Database Manager -> dwemer -> public -> oghma</b></p>
<p>All uploaded topics will be saved into the <code>oghma</code> table. This overwrites any existing entries with the same topic.</p>
</body>
</html>
