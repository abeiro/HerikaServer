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

/********************************************************************
 *  1) SINGLE TOPIC UPLOAD
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_individual'])) {
    // Collect and sanitize form inputs from $_POST (NOT $row!)
    $topic                = htmlspecialchars($_POST['topic']                ?? '');
    $topic_desc           = htmlspecialchars($_POST['topic_desc']           ?? '');
    $knowledge_class      = htmlspecialchars($_POST['knowledge_class']      ?? '');
    $topic_desc_basic     = htmlspecialchars($_POST['topic_desc_basic']     ?? '');
    $knowledge_class_basic= htmlspecialchars($_POST['knowledge_class_basic']?? '');
    $tags                 = htmlspecialchars($_POST['tags']                 ?? '');
    $category             = htmlspecialchars($_POST['category']             ?? '');

    if (!empty($topic) && !empty($topic_desc)) {
        // Insert or update (ON CONFLICT) the new columns
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

            // Update native_vector to include new columns if desired:
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
    // Check if a file was uploaded without errors
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['csv_file']['tmp_name'];
        $fileName    = $_FILES['csv_file']['name'];

        // Allowed file extensions
        $allowedfileExtensions = array('csv');
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

        if (in_array($fileExtension, $allowedfileExtensions)) {
            // Open the file for reading
            if (($handle = fopen($fileTmpPath, 'r')) !== false) {
                // Skip the header row (assuming the CSV has a header)
                fgetcsv($handle, 1000, ',');

                $rowCount = 0;
                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    /**
                     *  Adjust to match your CSV column order:
                     *  0) topic
                     *  1) topic_desc
                     *  2) knowledge_class
                     *  3) topic_desc_basic
                     *  4) knowledge_class_basic
                     *  5) tags
                     *  6) category
                     */
                    $topic                = strtolower(trim($data[0] ?? ''));
                    $topic_desc           = $data[1] ?? '';
                    $knowledge_class      = $data[2] ?? '';
                    $topic_desc_basic     = $data[3] ?? '';
                    $knowledge_class_basic= $data[4] ?? '';
                    $tags                 = $data[5] ?? '';
                    $category             = $data[6] ?? '';

                    // Only proceed if topic and topic_desc are not empty
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
                            // Optional: For large files, you might do one mass UPDATE after the loop
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
                        $message .= "<p>Skipping empty or invalid row in CSV (topic/topic_desc missing).</p>";
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
    // Define the path to the example CSV file
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
    // Perform the DELETE query
    $delete_query = "DELETE FROM $schema.oghma";
    $delete_result = pg_query($conn, $delete_query);

    if ($delete_result) {
        $message .= "<p>All entries in the Oghma Infinium have been deleted successfully.</p>";
    } else {
        $message .= "<p>Error deleting entries: " . pg_last_error($conn) . "</p>";
    }
}

/********************************************************************
 *  4.5) DELETE SINGLE TOPIC
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_single') {
    // Safety check: ensure we have a non-empty topic
    if (!empty($_POST['topic'])) {
        $topicToDelete = trim($_POST['topic']);

        // Perform the DELETE query
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
    </style>
</head>
<body>
<div class="indent5">
    <h1><img src="images/oghma_infinium.png" alt="Oghma Infinium" style="vertical-align:bottom;" width="32" height="32"> Oghma Infinium Management</h1>
    <p>The <b>Oghma Infinium</b> is a "Skyrim Encyclopedia" that AI NPC's will use to help them roleplay.
    <p>To use it you must have [MINIME_T5] and [OGHMA_INFINIUM] enabled in the default profile. You also need Minime-T5 installed and running.</p>
    <h3><strong>Ensure all topic titles are lowercase and spaces are replaced with underscores (_).</strong></h3>
    <h4>Example: "Fishy Stick" becomes "fishy_stick"</h4>
    <p>For Knowledge Class, we recommend you read this: <a href="https://docs.google.com/spreadsheets/d/1dcfctU-iOqprwy2BOc7___4Awteczgdlv8886KalPsQ/edit?pli=1&gid=338893641#gid=338893641" style="color: yellow;" target="_blank" rel="noopener noreferrer">Project Oghma</a></p>
    <p>
    Logic for searching articles: <br>
    1. NPC will search for oghma article based on most relevant keyword. <br>
    2. Check knowledge_class to see if they access to the advanced article. <br>
    3. Check knowledge_class_basic to see if they access to the basic article. <br>
    4. If all above fails, send "You do not know about X" to the prompt.
</p>
    <?php
    // Display any messages
    if (!empty($message)) {
        echo '<div class="message">';
        echo $message;
        echo '</div>';
    }
    ?>

    <h2>Single Topic Upload</h2>
    <form action="" method="post">
        <label for="topic">Topic (required):</label>
        <input type="text" name="topic" id="topic" required>

        <label for="topic_desc">Topic Description (required):</label>
        <textarea name="topic_desc" id="topic_desc" rows="5" required></textarea>

        <label for="knowledge_class">Knowledge Class:</label>
        <input type="text" name="knowledge_class" id="knowledge_class">

        <label for="topic_desc_basic">Topic Description (Basic):</label>
        <textarea name="topic_desc_basic" id="topic_desc_basic" rows="5"></textarea>

        <label for="knowledge_class_basic">Knowledge Class (Basic):</label>
        <input type="text" name="knowledge_class_basic" id="knowledge_class_basic">

        <label for="tags">Tags:</label>
        <input type="text" name="tags" id="tags">

        <label for="category">Category:</label>
        <input type="text" name="category" id="category">

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
<p>You can see how it picks a relevant article during conversation by navigating to <b>Server Actions -> Database Manager -> dwemer -> public -> audit_memory</b></p>
<p>All uploaded topics will be saved into the <code>oghma</code> table. This overwrites any existing entries with the same topic.</p>

<br>
<div class="indent5">
    <h2>Delete All Oghma Infinium Entries</h2>
    <p>You can download a backup of the full oghma database in the <a href="https://discord.gg/NDn9qud2ug" style="color: yellow;" target="_blank" rel="noopener"> csv files channel in our discord</a>.</p>
    <form action="" method="post" 
          onsubmit="return confirm('Are you sure you want to delete ALL entries in the Oghma Infinium? This action CANNOT be undone! You can download the full CSV from our discord.');">
        <input type="hidden" name="action" value="delete_all">
        <input type="submit" class="btn-danger" value="Delete All Oghma Entries">
    </form>
</div>
<br>

<?php
/********************************************************************
 *  5) DISPLAY THE OGHMA ENTRIES
 ********************************************************************/
// First, get distinct categories (except possibly empty ones)
$catQuery = "SELECT DISTINCT category FROM $schema.oghma WHERE category IS NOT NULL AND category <> '' ORDER BY category";
$catResult = pg_query($conn, $catQuery);
$categories = [];
if ($catResult) {
    while ($row = pg_fetch_assoc($catResult)) {
        $categories[] = $row['category'];
    }
}

// Grab the chosen category from GET (if any)
$selectedCategory = isset($_GET['cat']) ? $_GET['cat'] : '';

// Grab the letter filter from GET (if any)
$letter = isset($_GET['letter']) ? strtoupper($_GET['letter']) : '';

// Show category buttons
echo '<h2>Oghma Infinium Entries</h2>';
echo '<div class="filter-buttons">';
// "All Categories" button
echo '<a class="alphabet-button" href="?">All Categories</a>';

foreach ($categories as $cat) {
    // URL-encode category for safe GET param
    $catEncoded = urlencode($cat);
    // Highlight if currently selected
    $style = ($selectedCategory === $cat) ? 'style="background-color:#0056b3;"' : '';
    echo "<a class=\"alphabet-button\" $style href=\"?cat=$catEncoded\">$cat</a>";
}
echo '</div>';

// Build query + params depending on category & letter
if ($selectedCategory && $letter) {
    // Category AND letter are chosen
    $query = "
        SELECT 
            topic, 
            topic_desc,
            knowledge_class,
            topic_desc_basic,
            knowledge_class_basic,
            tags,
            category
        FROM $schema.oghma
        WHERE category = $1
          AND topic ILIKE $2
        ORDER BY topic ASC
    ";
    $params = [$selectedCategory, $letter . '%'];
} elseif ($selectedCategory) {
    // Only category chosen
    $query = "
        SELECT 
            topic, 
            topic_desc,
            knowledge_class,
            topic_desc_basic,
            knowledge_class_basic,
            tags,
            category
        FROM $schema.oghma
        WHERE category = $1
        ORDER BY topic ASC
    ";
    $params = [$selectedCategory];
} elseif ($letter) {
    // Only letter chosen (no category)
    $query = "
        SELECT 
            topic, 
            topic_desc,
            knowledge_class,
            topic_desc_basic,
            knowledge_class_basic,
            tags,
            category
        FROM $schema.oghma
        WHERE topic ILIKE $1
        ORDER BY topic ASC
    ";
    $params = [$letter . '%'];
} else {
    // No category, no letter
    $query = "
        SELECT 
            topic, 
            topic_desc,
            knowledge_class,
            topic_desc_basic,
            knowledge_class_basic,
            tags,
            category
        FROM $schema.oghma
        ORDER BY topic ASC
    ";
    $params = [];
}

$result = pg_query_params($conn, $query, $params);

if ($result) {
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
    $rowCount = 0;
    while ($row = pg_fetch_assoc($result)) {
        // Use ?? '' to avoid passing null to htmlspecialchars()
        $topic                = htmlspecialchars($row['topic']                ?? '');
        $topic_desc           = htmlspecialchars($row['topic_desc']           ?? '');
        $knowledge_class      = htmlspecialchars($row['knowledge_class']      ?? '');
        $topic_desc_basic     = htmlspecialchars($row['topic_desc_basic']     ?? '');
        $knowledge_class_basic= htmlspecialchars($row['knowledge_class_basic']?? '');
        $tags                 = htmlspecialchars($row['tags']                 ?? '');
        $category             = htmlspecialchars($row['category']             ?? '');

        echo '<tr>';
        echo '<td>' . $topic . '</td>';
        echo '<td>' . nl2br($topic_desc) . '</td>';
        echo '<td>' . nl2br($knowledge_class) . '</td>';
        echo '<td>' . nl2br($topic_desc_basic) . '</td>';
        echo '<td>' . nl2br($knowledge_class_basic) . '</td>';
        echo '<td>' . nl2br($tags) . '</td>';
        echo '<td>' . nl2br($category) . '</td>';

        // New "Action" column with a Delete button
        echo '<td>
                <form action="" method="post" style="display:inline;">
                <input type="hidden" name="action" value="delete_single">
                <input type="hidden" name="topic" value="' . htmlspecialchars($topic, ENT_QUOTES) . '">
                <input type="submit"
                        value="Delete"
                        style="
                            all: unset; /* Removes all inherited styles */
                            display: inline-block; 
                            text-align: center;
                            margin-top: 10px;
                            font-weight: bold;
                            border: 1px solid #ffffff; 
                            padding: 10px 20px;
                            cursor: pointer;
                            border-radius: 4px;
                            font-size: 16px;
                            background-color: #dc3545; 
                            color: white;
                            transition: background-color 0.3s, color 0.3s;
                        "
                        onmouseover="this.style.backgroundColor=\'#c82333\';"  /* Darker red on hover */
                        onmouseout="this.style.backgroundColor=\'#dc3545\';"   /* Revert to original red */
                        onclick="return confirm(\'Are you sure you want to delete topic: ' . htmlspecialchars($topic, ENT_QUOTES) . '?\');"
                />
                </form>
            </td>';

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
