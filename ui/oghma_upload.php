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
foreach (glob($configFilepath . 'conf_????????????????????????????????????????????????.php') as $mconf) {
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

/********************************************************************
 *  1) SINGLE TOPIC UPLOAD
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_individual'])) {
    // Collect and sanitize form inputs
    $topic                = htmlspecialchars($_POST['topic']                ?? '');
    $topic_desc           = htmlspecialchars($_POST['topic_desc']           ?? '');
    $knowledge_class      = htmlspecialchars($_POST['knowledge_class']      ?? '');
    $topic_desc_basic     = htmlspecialchars($_POST['topic_desc_basic']     ?? '');
    $knowledge_class_basic= htmlspecialchars($_POST['knowledge_class_basic']?? '');
    $tags                 = htmlspecialchars($_POST['tags']                 ?? '');
    $category             = htmlspecialchars($_POST['category']             ?? '');

    if (!empty($topic) && !empty($topic_desc)) {
        $query = "
            INSERT INTO $schema.oghma (
                topic, 
                topic_desc, 
                knowledge_class, 
                topic_desc_basic, 
                knowledge_class_basic, 
                tags, 
                category
            )
            VALUES ($1, $2, $3, $4, $5, $6, $7)
            ON CONFLICT (topic)
            DO UPDATE SET
                topic_desc           = EXCLUDED.topic_desc,
                knowledge_class      = EXCLUDED.knowledge_class,
                topic_desc_basic     = EXCLUDED.topic_desc_basic,
                knowledge_class_basic= EXCLUDED.knowledge_class_basic,
                tags                 = EXCLUDED.tags,
                category             = EXCLUDED.category
        ";
        $result = pg_query_params($conn, $query, [
            $topic,
            $topic_desc,
            $knowledge_class,
            $topic_desc_basic,
            $knowledge_class_basic,
            $tags,
            $category
        ]);

        if ($result) {
            $message .= "<p>Data inserted/updated successfully!</p>";

            // Update native_vector
            $update_query = "
                UPDATE $schema.oghma
                SET native_vector = 
                      setweight(to_tsvector(coalesce(topic, '')), 'A')
                    || setweight(to_tsvector(coalesce(topic_desc, '')), 'B')
                    || setweight(to_tsvector(coalesce(knowledge_class, '')), 'B')
                    || setweight(to_tsvector(coalesce(topic_desc_basic, '')), 'C')
                    || setweight(to_tsvector(coalesce(knowledge_class_basic, '')), 'C')
                    || setweight(to_tsvector(coalesce(tags, '')), 'D')
                    || setweight(to_tsvector(coalesce(category, '')), 'D')
                WHERE topic = $1
            ";
            $update_result = pg_query_params($conn, $update_query, [$topic]);

            if ($update_result) {
                $message .= "<p>Vectors updated successfully.</p>";
            } else {
                $message .= "<p>Error updating vectors: " . pg_last_error($conn) . "</p>";
            }
        } else {
            $message .= "<p>An error occurred while inserting/updating data: " . pg_last_error($conn) . "</p>";
        }
    } else {
        $message .= '<p>Please fill in at least the "topic" and "topic_desc" fields.</p>';
    }
}

/********************************************************************
 *  2) CSV UPLOAD (BATCH)
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['csv_file']['tmp_name'];
        $fileName    = $_FILES['csv_file']['name'];

        $allowedfileExtensions = array('csv');
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

        if (in_array($fileExtension, $allowedfileExtensions)) {
            if (($handle = fopen($fileTmpPath, 'r')) !== false) {
                // Skip header row
                fgetcsv($handle, 1000, ',');

                $rowCount = 0;
                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    $topic                = strtolower(trim($data[0] ?? ''));
                    $topic_desc           = $data[1] ?? '';
                    $knowledge_class      = $data[2] ?? '';
                    $topic_desc_basic     = $data[3] ?? '';
                    $knowledge_class_basic= $data[4] ?? '';
                    $tags                 = $data[5] ?? '';
                    $category             = $data[6] ?? '';

                    if (!empty($topic) && !empty($topic_desc)) {
                        $query = "
                            INSERT INTO $schema.oghma (
                                topic,
                                topic_desc,
                                knowledge_class,
                                topic_desc_basic,
                                knowledge_class_basic,
                                tags,
                                category
                            )
                            VALUES ($1, $2, $3, $4, $5, $6, $7)
                            ON CONFLICT (topic)
                            DO UPDATE SET
                                topic_desc           = EXCLUDED.topic_desc,
                                knowledge_class      = EXCLUDED.knowledge_class,
                                topic_desc_basic     = EXCLUDED.topic_desc_basic,
                                knowledge_class_basic= EXCLUDED.knowledge_class_basic,
                                tags                 = EXCLUDED.tags,
                                category             = EXCLUDED.category
                        ";
                        $result = pg_query_params($conn, $query, [
                            $topic,
                            $topic_desc,
                            $knowledge_class,
                            $topic_desc_basic,
                            $knowledge_class_basic,
                            $tags,
                            $category
                        ]);

                        if ($result) {
                            $rowCount++;
                            // Update the native_vector for this single row
                            $update_query = "
                                UPDATE $schema.oghma
                                SET native_vector = 
                                      setweight(to_tsvector(coalesce(topic, '')), 'A')
                                    || setweight(to_tsvector(coalesce(topic_desc, '')), 'B')
                                    || setweight(to_tsvector(coalesce(knowledge_class, '')), 'B')
                                    || setweight(to_tsvector(coalesce(topic_desc_basic, '')), 'C')
                                    || setweight(to_tsvector(coalesce(knowledge_class_basic, '')), 'C')
                                    || setweight(to_tsvector(coalesce(tags, '')), 'D')
                                    || setweight(to_tsvector(coalesce(category, '')), 'D')
                                WHERE topic = $1
                            ";
                            pg_query_params($conn, $update_query, [$topic]);
                        } else {
                            $message .= "<p>Error processing row with topic '$topic': " . pg_last_error($conn) . "</p>";
                        }
                    } else {
                        $message .= "<p>Skipping empty or invalid row (topic/topic_desc missing).</p>";
                    }
                }
                fclose($handle);

                $message .= "<p>$rowCount records inserted/updated successfully from the CSV file.</p>";
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

/********************************************************************
 *  3) DOWNLOAD EXAMPLE CSV
 ********************************************************************/
if (isset($_GET['action']) && $_GET['action'] === 'download_example') {
    $filePath = realpath(__DIR__ . '/../data/oghma_example.csv');
    if (file_exists($filePath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="oghma_example.csv"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        if (ob_get_length()) ob_end_clean();
        flush();
        readfile($filePath);
        exit;
    } else {
        $message .= '<p>Example CSV file not found.</p>';
    }
}

/********************************************************************
 *  4) DELETE ALL
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_all') {
    $delete_query = "DELETE FROM $schema.oghma";
    $delete_result = pg_query($conn, $delete_query);

    if ($delete_result) {
        $message .= "<p>All entries have been deleted successfully.</p>";
    } else {
        $message .= "<p>Error deleting entries: " . pg_last_error($conn) . "</p>";
    }
}

/********************************************************************
 *  4.5) DELETE SINGLE TOPIC
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_single') {
    if (!empty($_POST['topic'])) {
        $topicToDelete = trim($_POST['topic']);
        $delete_query = "DELETE FROM $schema.oghma WHERE topic = $1";
        $delete_result = pg_query_params($conn, $delete_query, [$topicToDelete]);

        if ($delete_result) {
            $message .= "<p>Topic <strong>$topicToDelete</strong> has been deleted successfully.</p>";
        } else {
            $message .= "<p>Error deleting <strong>$topicToDelete</strong>: " . pg_last_error($conn) . "</p>";
        }
    } else {
        $message .= "<p>No topic specified for deletion.</p>";
    }
}

/********************************************************************
 * (A) UPDATE SINGLE ROW (SAVE after Edit)
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_single') {
    // Sanitize and read posted fields - use htmlspecialchars_decode to convert HTML entities back
    $topic_original       = $_POST['topic_original'] ?? '';
    $topic_new           = htmlspecialchars_decode($_POST['topic_new']            ?? '');
    $topic_desc_new      = htmlspecialchars_decode($_POST['topic_desc_new']       ?? '');
    $knowledge_class_new = htmlspecialchars_decode($_POST['knowledge_class_new']  ?? '');
    $topic_basic_new     = htmlspecialchars_decode($_POST['topic_basic_new']      ?? '');
    $class_basic_new     = htmlspecialchars_decode($_POST['class_basic_new']      ?? '');
    $tags_new            = htmlspecialchars_decode($_POST['tags_new']             ?? '');
    $category_new        = htmlspecialchars_decode($_POST['category_new']         ?? '');

    if (!empty($topic_new) && !empty($topic_desc_new)) {
        // Perform the update
        $update_sql = "
            UPDATE $schema.oghma
            SET 
                topic = $1,
                topic_desc = $2,
                knowledge_class = $3,
                topic_desc_basic = $4,
                knowledge_class_basic = $5,
                tags = $6,
                category = $7
            WHERE topic = $8
        ";

        $update_result = pg_query_params($conn, $update_sql, [
            $topic_new,
            $topic_desc_new,
            $knowledge_class_new,
            $topic_basic_new,
            $class_basic_new,
            $tags_new,
            $category_new,
            $topic_original
        ]);

        if ($update_result) {
            $message .= "<p>Row updated successfully for topic <strong>$topic_original</strong>.</p>";

            // Update the native_vector
            $vector_sql = "
                UPDATE $schema.oghma
                SET native_vector = 
                      setweight(to_tsvector(coalesce(topic, '')), 'A')
                    || setweight(to_tsvector(coalesce(topic_desc, '')), 'B')
                    || setweight(to_tsvector(coalesce(knowledge_class, '')), 'B')
                    || setweight(to_tsvector(coalesce(topic_desc_basic, '')), 'C')
                    || setweight(to_tsvector(coalesce(knowledge_class_basic, '')), 'C')
                    || setweight(to_tsvector(coalesce(tags, '')), 'D')
                    || setweight(to_tsvector(coalesce(category, '')), 'D')
                WHERE topic = $1
            ";
            pg_query_params($conn, $vector_sql, [$topic_new]);

            // Redirect to exit edit mode while maintaining filters
            $redirectUrl = '?' . http_build_query([
                'cat' => $_GET['cat'] ?? '',
                'letter' => $_GET['letter'] ?? '',
                'order' => $_GET['order'] ?? 'asc'
            ]) . '#table';
            header('Location: ' . $redirectUrl);
            exit;
        } else {
            $message .= "<p>Error updating row: " . pg_last_error($conn) . "</p>";
        }
    } else {
        $message .= '<p>Topic and Topic Description cannot be empty when saving.</p>';
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
            background-color: #3a3a3a;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #4a4a4a;
            max-width: 600px;
        }
        label {
            font-weight: bold;
            color: #f8f9fa; /* Ensure labels are readable */
        }
        input[type="text"],
        input[type="file"],
        textarea {
            width: 100%;
            padding: 6px;
            margin-top: 5px;
            margin-bottom: 15px;
            border: 1px solid #555555; /* Darker borders */
            border-radius: 3px;
            background-color: #4a4a4a; /* Dark input backgrounds */
            color: #f8f9fa; /* Light text inside inputs */
            font-family: Arial, sans-serif; /* Ensures consistent font */
            font-size: 16px; /* Sets a readable font size */
        }
        textarea {
            resize: vertical;
            height: 120px;

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
        .indent5 {
            padding-left: 5ch;
        }
        table {
            width: 100%;
            max-width: 1600px;
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
            max-width: 1600px;
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
            font-size: 16px;
        }
        .filter-buttons button:hover {
            background-color: #0056b3;
        }
        .table-container {
            max-height: 800px;
            overflow-y: auto;
            margin-bottom: 20px;
            max-width: 1600px;
        }
        .table-container th, .table-container td {
            border: 1px solid #555;
            padding: 8px;
            text-align: left;
            word-wrap: break-word;
            overflow-wrap: break-word;
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
        .table-container th:nth-child(4),
        .table-container td:nth-child(4) {
            width: 600px;
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
        .alphabet-button {
            display: inline-block;
            margin-right: 5px;
            margin-bottom: 5px;
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
        /* Extra styling for inline forms in the Action column */
        .action-form {
            display: inline-block;
            margin: 0 2px;
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
            width: 60%; /* Reduced width */
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

        .modal-close {
            background: none;
            border: none;
            color: #fff;
            font-size: 1.5em;
            cursor: pointer;
            padding: 0;
            margin: 0;
        }

        .modal-close:hover {
            color: #dc3545;
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

        .modal-footer button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .modal-footer button[type="submit"] {
            background-color: #28a745;
        }
        .modal-footer button[type="submit"]:hover {
            background-color: #218838;
        }

        .modal-footer button[onclick*="delete"] {
            background-color: #dc3545;
        }
        .modal-footer button[onclick*="delete"]:hover {
            background-color: #c82333;
        }

        .modal-footer button[onclick*="close"] {
            background-color: #6c757d;
        }
        .modal-footer button[onclick*="close"]:hover {
            background-color: #5a6268;
        }

        /* Common button styles */
        .action-button {
            display: inline-block;
            padding: 8px 12px;
            color: #fff;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        /* Edit button */
        .action-button.edit {
            background-color: #17a2b8;
        }
        .action-button.edit:hover {
            background-color: #138496;
        }

        /* Add new entry button */
        .action-button.add-new {
            background-color: #28a745;
            border: none;
        }
        .action-button.add-new:hover {
            background-color: #218838;
        }

        /* Save button */
        button[type="submit"] {
            background-color: #28a745;
            transition: background-color 0.3s ease;
        }
        button[type="submit"]:hover {
            background-color: #218838;
        }

        /* Delete button */
        button[onclick*="delete"] {
            background-color: #dc3545;
            transition: background-color 0.3s ease;
        }
        button[onclick*="delete"]:hover {
            background-color: #c82333;
        }

        /* Cancel button */
        button[onclick*="close"] {
            background-color: #6c757d;
            transition: background-color 0.3s ease;
        }
        button[onclick*="close"]:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
<div class="indent5">
<h1><img src="images/oghma_infinium.png" alt="Oghma Infinium" style="vertical-align:bottom;" width="32" height="32"> Oghma Infinium Management</h1>
    <h2>OGHMA 2.0 is currently in beta! You can download the 2.0 CSV in our discord under the csv-fivles channel.</h2>
    <p>The <b>Oghma Infinium</b> is a "Skyrim Encyclopedia" that AI NPC's will use to help them roleplay.
    <p>To use it you must have [MINIME_T5] and [OGHMA_INFINIUM] enabled in the default profile. You also need Minime-T5 installed and running.</p>
    <h3><strong>Ensure all topic titles are lowercase and spaces are replaced with underscores (_).</strong></h3>
    <h4>Example: "Fishy Stick" becomes "fishy_stick"</h4>
    <p>For Knowledge Class, we recommend you read this: <a href="https://docs.google.com/spreadsheets/d/1dcfctU-iOqprwy2BOc7___4Awteczgdlv8886KalPsQ/edit?pli=1&gid=338893641#gid=338893641" style="color: yellow;" target="_blank" rel="noopener noreferrer">Project Oghma</a></p>
    <p>
    <b>Logic for searching articles:</b> <br>
    1. NPC will search for oghma article based on most relevant keyword. <br>
    2. Check knowledge_class to see if they access to the advanced article. <br>
    3. Check knowledge_class_basic to see if they access to the basic article. <br>
    4. If all above fails, send "You do not know about X" to the prompt.
</p>
    <?php
    // Display messages
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
            <input type="submit" name="submit_csv" value="Upload CSV" style="
                background-color: #28a745;
                color: white;
                border: none;
                border-radius: 4px;
                padding: 8px 12px;
                cursor: pointer;
                font-weight: bold;
                transition: background-color 0.3s ease;
            " onmouseover="this.style.backgroundColor='#218838';" onmouseout="this.style.backgroundColor='#28a745';">
            <a href="?action=download_example" style="
                background-color: #007bff; /* Change to blue */
                color: white;
                border: none;
                border-radius: 4px;
                padding: 8px 12px; /* Match padding */
                cursor: pointer;
                font-weight: bold;
                text-decoration: none;
                transition: background-color 0.3s ease;
                display: inline-block; /* Ensure it behaves like a button */
                margin-top: 10px; /* Add margin for spacing */
            " onmouseover="this.style.backgroundColor='#0056b3';" onmouseout="this.style.backgroundColor='#007bff';">Download Example CSV</a>
        </div>
    </form>
    <p>You can verify that the entry has been uploaded successfully by navigating to <br><b>Server Actions -> Database Manager -> dwemer -> public -> oghma</b></p>
    <p>You can see how it picks a relevant article during conversation by navigating to <br><b>Server Actions -> Database Manager -> dwemer -> public -> audit_memory</b></p>
    <p>All uploaded topics will be saved into the <code>oghma</code> table. This overwrites any existing entries with the same topic.</p>
    <form action="" method="post" style="
        border: none; /* Remove border */
        padding: 0; /* Remove padding */
        margin: 0; /* Remove margin */
    " onsubmit="return confirm('Are you sure you want to delete ALL entries? This cannot be undone!');">
    <input type="hidden" name="action" value="delete_all">
    <input type="submit" class="btn-danger" value="Delete All Oghma Entries">
    <p>You can download a backup of the full Oghma database in the <a href="https://discord.gg/NDn9qud2ug" style="color: yellow;" target="_blank" rel="noopener"> csv files channel in our discord</a>.</p>
</form>
</div>

<br>


<?php
/********************************************************************
 *  5) DISPLAY THE OGHMA ENTRIES
 ********************************************************************/
// Fetch categories
$catQuery = "SELECT DISTINCT category FROM $schema.oghma WHERE category IS NOT NULL AND category <> '' ORDER BY category";
$catResult = pg_query($conn, $catQuery);
$categories = [];
if ($catResult) {
    while ($row = pg_fetch_assoc($catResult)) {
        $categories[] = $row['category'];
    }
}

// Grab filters
$selectedCategory = $_GET['cat']   ?? '';
$letter          = strtoupper($_GET['letter'] ?? '');

// Sorting
$order = 'ASC';
if (isset($_GET['order'])) {
    $requestedOrder = strtolower($_GET['order']);
    if ($requestedOrder === 'asc' || $requestedOrder === 'desc') {
        $order = strtoupper($requestedOrder);
    }
}

// Category buttons
echo '<h2>Oghma Infinium Entries</h2>';
echo  '<button onclick="openNewEntryModal()" class="action-button add-new">Add New Entry</button>';
echo '<br>';
echo '<br>';
echo '<div class="filter-buttons">';
echo '<a class="alphabet-button" href="?#table">All Categories</a>';
foreach ($categories as $cat) {
    $catEncoded = urlencode($cat);
    $style = ($selectedCategory === $cat) ? 'style="background-color:#0056b3;"' : '';
    echo "<a class=\"alphabet-button\" $style href=\"?cat=$catEncoded#table\">" . htmlspecialchars($cat) . "</a>";
}
echo '</div>';

// Sorting links
$baseUrl = '?';
if ($selectedCategory) $baseUrl .= 'cat=' . urlencode($selectedCategory) . '&';
if ($letter) $baseUrl .= 'letter=' . urlencode($letter) . '&';

echo '<div class="filter-buttons">';
echo '<a class="alphabet-button" href="' . $baseUrl . 'order=asc#table">Sort Ascending</a>';
echo '<a class="alphabet-button" href="' . $baseUrl . 'order=desc#table">Sort Descending</a>';
echo '</div>';

// Build query
if ($selectedCategory && $letter) {
    $query = "
        SELECT topic, topic_desc, knowledge_class, topic_desc_basic,
               knowledge_class_basic, tags, category
        FROM $schema.oghma
        WHERE category = $1
          AND topic ILIKE $2
        ORDER BY topic $order
    ";
    $params = [$selectedCategory, $letter . '%'];
} elseif ($selectedCategory) {
    $query = "
        SELECT topic, topic_desc, knowledge_class, topic_desc_basic,
               knowledge_class_basic, tags, category
        FROM $schema.oghma
        WHERE category = $1
        ORDER BY topic $order
    ";
    $params = [$selectedCategory];
} elseif ($letter) {
    $query = "
        SELECT topic, topic_desc, knowledge_class, topic_desc_basic,
               knowledge_class_basic, tags, category
        FROM $schema.oghma
        WHERE topic ILIKE $1
        ORDER BY topic $order
    ";
    $params = [$letter . '%'];
} else {
    $query = "
        SELECT topic, topic_desc, knowledge_class, topic_desc_basic,
               knowledge_class_basic, tags, category
        FROM $schema.oghma
        ORDER BY topic $order
    ";
    $params = [];
}

$result = pg_query_params($conn, $query, $params);

echo '<a id="table"></a>';
echo '<div class="table-container">';
echo '<table>';
echo '<tr>
        <th>Topic</th>
        <th>Topic Description</th>
        <th>Knowledge Class</th>
        <th>Topic Desc (Basic)</th>
        <th>Knowledge Class (Basic)</th>
        <th>Tags</th>
        <th>Category</th>
        <th>Action</th> 
      </tr>';

if ($result) {
    $rowCount = 0;
    while ($row = pg_fetch_assoc($result)) {
        $topic                = htmlspecialchars($row['topic']                ?? '');
        $topic_desc           = htmlspecialchars($row['topic_desc']           ?? '');
        $knowledge_class      = htmlspecialchars($row['knowledge_class']      ?? '');
        $topic_desc_basic     = htmlspecialchars($row['topic_desc_basic']     ?? '');
        $knowledge_class_basic= htmlspecialchars($row['knowledge_class_basic']?? '');
        $tags                 = htmlspecialchars($row['tags']                 ?? '');
        $category             = htmlspecialchars($row['category']             ?? '');

        // Normal row display
        echo '<tr>';
        echo '<td>' . $topic . '</td>';
        echo '<td>' . nl2br($topic_desc) . '</td>';
        echo '<td>' . nl2br($knowledge_class) . '</td>';
        echo '<td>' . nl2br($topic_desc_basic) . '</td>';
        echo '<td>' . nl2br($knowledge_class_basic) . '</td>';
        echo '<td>' . nl2br($tags) . '</td>';
        echo '<td>' . nl2br($category) . '</td>';

        // Action column
        echo '<td style="white-space: nowrap;">';
        echo '<div style="display: flex; gap: 4px;">';
        
        // Edit button only
        echo '<a href="#" onclick="openEditModal(`' . 
            addslashes($topic) . '`,`' . 
            addslashes($topic_desc) . '`,`' . 
            addslashes($knowledge_class) . '`,`' . 
            addslashes($topic_desc_basic) . '`,`' . 
            addslashes($knowledge_class_basic) . '`,`' . 
            addslashes($tags) . '`,`' . 
            addslashes($category) . 
            '`);return false;" 
            class="action-button edit">
            Edit
        </a>';
        
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
} else {
    echo '<p>Error fetching Oghma entries: ' . pg_last_error($conn) . '</p>';
}

pg_close($conn);
?>

<div id="editModal" class="modal-backdrop">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">Edit Oghma Entry</h2>
        </div>
        <div class="modal-body">
            <form action="" method="post">
                <input type="hidden" name="action" value="update_single">
                <input type="hidden" name="topic_original" id="edit_topic_original">

                <label for="edit_topic">Topic:</label>
                <small>Topic name for keyword searching.</small>
                <input type="text" name="topic_new" id="edit_topic" required>
                

                <label for="edit_topic_desc">Topic Description:</label>
                <small>Advanced knowledge information on the subject.</small>
                <textarea name="topic_desc_new" id="edit_topic_desc" rows="8" required></textarea>
                

                <label for="edit_knowledge_class">Knowledge Class:</label>
                <small>Who should have access to this advanced knowledge. Separate tags by commas. <a href="https://docs.google.com/spreadsheets/d/1dcfctU-iOqprwy2BOc7___4Awteczgdlv8886KalPsQ/edit?pli=1&gid=338893641" style="color: yellow;" target="_blank" rel="noopener noreferrer"> More information can be found here</a>.</small>
                <input type="text" name="knowledge_class_new" id="edit_knowledge_class">

                <label for="edit_topic_desc_basic">Topic Description (Basic):</label>
                <small>Who should have basic information on the subject.</small>
                <textarea name="topic_basic_new" id="edit_topic_desc_basic" rows="8"></textarea>
                

                <label for="edit_knowledge_class_basic">Knowledge Class (Basic):</label>
                <small>Who should have access to this basic knowledge. Leave empty to allow all NPCs to know this, is recommended for most basic articles. Separate tags by commas. <a href="https://docs.google.com/spreadsheets/d/1dcfctU-iOqprwy2BOc7___4Awteczgdlv8886KalPsQ/edit?pli=1&gid=338893641" style="color: yellow;" target="_blank" rel="noopener noreferrer"> More information can be found here</a>.</small>
                <input type="text" name="class_basic_new" id="edit_knowledge_class_basic">

                <label for="edit_tags">Tags:</label>
                <small>Not currently in use.</small>
                <input type="text" name="tags_new" id="edit_tags">

                <label for="edit_category">Category:</label>
                <small>Category for database searching.</small>
                <input type="text" name="category_new" id="edit_category">

                <div class="modal-footer">
                    <button type="submit" style="background-color: #28a745;">Save Changes</button>
                    <button type="button" onclick="deleteEntry()" style="background-color: #dc3545;">Delete</button>
                    <button type="button" onclick="closeEditModal()" style="background-color: #6c757d;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="newEntryModal" class="modal-backdrop">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">Add New Oghma Entry</h2>
        </div>
        <div class="modal-body">
            <form action="" method="post">
                <input type="hidden" name="submit_individual" value="1">

                <label for="topic">Topic (required):</label>
                <small>Topic name for keyword searching.</small>
                <input type="text" name="topic" id="topic" required>

                <label for="topic_desc">Topic Description (required):</label>
                <small>Advanced knowledge information on the subject.</small>
                <textarea name="topic_desc" id="topic_desc" rows="5" required></textarea>

                <label for="knowledge_class">Knowledge Class:</label>
                <small>Who should have access to this advanced knowledge. Separate tags by commas. <a href="https://docs.google.com/spreadsheets/d/1dcfctU-iOqprwy2BOc7___4Awteczgdlv8886KalPsQ/edit?pli=1&gid=338893641" style="color: yellow;" target="_blank" rel="noopener noreferrer"> More information can be found here</a>.</small>
                <input type="text" name="knowledge_class" id="knowledge_class">

                <label for="topic_desc_basic">Topic Description (Basic):</label>
                <small>Who should have basic information on the subject.</small>
                <textarea name="topic_desc_basic" id="topic_desc_basic" rows="5"></textarea>

                <label for="knowledge_class_basic">Knowledge Class (Basic):</label>
                <small>Who should have access to this basic knowledge. Leave empty to allow all NPCs to know this, is recommended for most basic articles. Separate tags by commas. <a href="https://docs.google.com/spreadsheets/d/1dcfctU-iOqprwy2BOc7___4Awteczgdlv8886KalPsQ/edit?pli=1&gid=338893641" style="color: yellow;" target="_blank" rel="noopener noreferrer"> More information can be found here</a>.</small>
                <input type="text" name="knowledge_class_basic" id="knowledge_class_basic">

                <label for="tags">Tags:</label>
                <small>Not currently in use.</small>
                <input type="text" name="tags" id="tags">

                <label for="category">Category:</label>
                <small>Category for database searching.</small>
                <input type="text" name="category" id="category">

                <div class="modal-footer">
                    <button type="submit" style="background-color: #28a745;">Save</button>
                    <button type="button" onclick="closeNewEntryModal()" style="background-color: #6c757d;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditModal(topic, desc, klass, basicDesc, basicKlass, tags, category) {
    try {
        // Decode HTML entities in the data
        const decodeHTML = (html) => {
            const txt = document.createElement('textarea');
            txt.innerHTML = html;
            return txt.value;
        };

        document.getElementById("edit_topic_original").value = decodeHTML(topic);
        document.getElementById("edit_topic").value = decodeHTML(topic);
        document.getElementById("edit_topic_desc").value = decodeHTML(desc);
        document.getElementById("edit_knowledge_class").value = decodeHTML(klass);
        document.getElementById("edit_topic_desc_basic").value = decodeHTML(basicDesc);
        document.getElementById("edit_knowledge_class_basic").value = decodeHTML(basicKlass);
        document.getElementById("edit_tags").value = decodeHTML(tags);
        document.getElementById("edit_category").value = decodeHTML(category);
        
        document.getElementById("editModal").style.display = "block";
        document.body.style.overflow = "hidden";
    } catch (error) {
        console.error('Error in openEditModal:', error);
        alert('There was an error opening the edit form. Please try again.');
    }
}

function closeEditModal() {
    document.getElementById("editModal").style.display = "none";
    document.body.style.overflow = "auto";
}

function deleteEntry() {
    const topic = document.getElementById('edit_topic_original').value;
    if (confirm("Are you sure you want to delete: " + topic + "?")) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_single">
            <input type="hidden" name="topic" value="${topic}">
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
