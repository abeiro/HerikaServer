<?php
// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

$TITLE = "📙CHIM - Oghma Infinium Management";

ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");

$debugPaneLink = false;
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/navbar.php");

// Enable error reporting (for development purposes)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Paths
$rootPath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
$enginePath = dirname($rootPath) . DIRECTORY_SEPARATOR;
$configFilepath = $rootPath . "conf" . DIRECTORY_SEPARATOR;

// Database connection details
$host = 'localhost';
$port = '5432';
$dbname = 'dwemer';
$schema = 'public';
$username = 'dwemer';
$password = 'dwemer';


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
    $filePath = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'oghma_example.csv');
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
 *  3.5) DYNAMIC CSV UPLOAD AND EXAMPLE
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_dynamic_csv'])) {
    if (isset($_FILES['dynamic_csv_file']) && $_FILES['dynamic_csv_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['dynamic_csv_file']['tmp_name'];
        $fileName    = $_FILES['dynamic_csv_file']['name'];

        $allowedfileExtensions = array('csv');
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

        if (in_array($fileExtension, $allowedfileExtensions)) {
            if (($handle = fopen($fileTmpPath, 'r')) !== false) {
                // Skip header row
                fgetcsv($handle, 1000, ',');

                $rowCount = 0;
                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    $id_quest             = trim($data[0] ?? '');
                    $stage                = intval($data[1] ?? 0);
                    $topic                = strtolower(trim($data[2] ?? ''));
                    $topic_desc           = $data[3] ?? '';
                    $knowledge_class      = $data[4] ?? '';
                    $topic_desc_basic     = $data[5] ?? '';
                    $knowledge_class_basic= $data[6] ?? '';
                    $tags                 = $data[7] ?? '';
                    $category             = $data[8] ?? '';

                    if (!empty($id_quest) && !empty($topic)) {
                        $query = "
                            INSERT INTO $schema.oghma_dynamic (
                                id_quest,
                                stage,
                                topic,
                                topic_desc,
                                knowledge_class,
                                topic_desc_basic,
                                knowledge_class_basic,
                                tags,
                                category
                            )
                            VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)
                        ";
                        
                        $result = pg_query_params($conn, $query, [
                            $id_quest,
                            $stage,
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
                        } else {
                            $message .= "<p>Error processing row with quest ID '$id_quest': " . pg_last_error($conn) . "</p>";
                        }
                    } else {
                        $message .= "<p>Skipping empty or invalid row (Quest ID/Topic missing).</p>";
                    }
                }
                fclose($handle);

                $message .= "<p>$rowCount dynamic entries inserted successfully from the CSV file.</p>";
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

if (isset($_GET['action']) && $_GET['action'] === 'download_dynamic_example') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="oghma_dynamic_example.csv"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // Example content with header and two sample entries
    echo "id_quest,stage,topic,topic_desc,knowledge_class,topic_desc_basic,knowledge_class_basic,tags,category\n";
    echo "TutorialBlacksmithing,1,blacksmithing,The art of blacksmithing involves crafting weapons and armor at a forge.,blacksmith;craftsman,Basic knowledge about forging metal items.,,,Skills\n";
    echo "MQ101,10,helgen_attack,A dragon attacked the town of Helgen during an Imperial execution.,guard;soldier,A dragon destroyed Helgen.,,,Events\n";
    exit;
}

/********************************************************************
 *  4) DELETE ALL
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_all') {
    $truncateQuery = "TRUNCATE TABLE {$schema}.oghma RESTART IDENTITY";
    $truncateResult = pg_query($conn, $truncateQuery);

    if ($truncateResult) {
        $message .= "<p style='color: #ff6464; font-weight: bold;'>All Oghma entries have been deleted successfully.</p>";
    } else {
        $message .= "<p>Error deleting entries: " . pg_last_error($conn) . "</p>";
    }
}

/********************************************************************
 *  4.5) DELETE SINGLE TOPIC
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_single') {
    $topic = $_POST['topic'] ?? '';
    
    if (!empty($topic)) {
        $query = "DELETE FROM {$schema}.oghma WHERE topic = $1";
        $result = pg_query_params($conn, $query, [$topic]);

        if ($result) {
            $message .= "<p>Entry '$topic' has been deleted successfully.</p>";
            
            // Redirect to maintain filters
            $redirectUrl = '?' . http_build_query([
                'cat' => $_GET['cat'] ?? '',
                'letter' => $_GET['letter'] ?? '',
                'order' => $_GET['order'] ?? 'asc'
            ]) . '#entries';
            header('Location: ' . $redirectUrl);
            exit;
        } else {
            $message .= "<p>Error deleting entry: " . pg_last_error($conn) . "</p>";
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
    $topic_new           = htmlspecialchars_decode($_POST['topic_new'] ?? '');
    $topic_desc_new      = htmlspecialchars_decode($_POST['topic_desc_new'] ?? '');
    $knowledge_class_new = htmlspecialchars_decode($_POST['knowledge_class_new'] ?? '');
    $topic_desc_basic_new = htmlspecialchars_decode($_POST['topic_desc_basic_new'] ?? '');
    $knowledge_class_basic_new = htmlspecialchars_decode($_POST['knowledge_class_basic_new'] ?? '');
    $tags_new            = htmlspecialchars_decode($_POST['tags_new'] ?? '');
    $category_new        = htmlspecialchars_decode($_POST['category_new'] ?? '');

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
            $topic_desc_basic_new,
            $knowledge_class_basic_new,
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
            ]) . '#entries';
            header('Location: ' . $redirectUrl);
            exit;
        } else {
            $message .= "<p>Error updating row: " . pg_last_error($conn) . "</p>";
        }
    } else {
        $message .= '<p>Topic and Topic Description cannot be empty when saving.</p>';
    }
}

/********************************************************************
 *  ADD NEW DYNAMIC ENTRY
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_dynamic'])) {
    // Collect and sanitize form inputs
    $id_quest             = htmlspecialchars($_POST['id_quest']             ?? '');
    $stage                = intval($_POST['stage']                          ?? 0);
    $topic                = htmlspecialchars($_POST['dynamic_topic']        ?? '');
    $topic_desc           = htmlspecialchars($_POST['dynamic_topic_desc']   ?? '');
    $knowledge_class      = htmlspecialchars($_POST['dynamic_knowledge_class']      ?? '');
    $topic_desc_basic     = htmlspecialchars($_POST['dynamic_topic_desc_basic']     ?? '');
    $knowledge_class_basic= htmlspecialchars($_POST['dynamic_knowledge_class_basic']?? '');
    $tags                 = htmlspecialchars($_POST['dynamic_tags']                 ?? '');
    $category             = htmlspecialchars($_POST['dynamic_category']             ?? '');

    if (!empty($id_quest) && !empty($topic)) {
        $query = "
            INSERT INTO $schema.oghma_dynamic (
                id_quest,
                stage,
                topic,
                topic_desc,
                knowledge_class,
                topic_desc_basic,
                knowledge_class_basic,
                tags,
                category
            )
            VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)
        ";
        
        $result = pg_query_params($conn, $query, [
            $id_quest,
            $stage,
            $topic,
            $topic_desc,
            $knowledge_class,
            $topic_desc_basic,
            $knowledge_class_basic,
            $tags,
            $category
        ]);

        if ($result) {
            $message .= "<p>Dynamic entry added successfully!</p>";
        } else {
            $message .= "<p>Error adding dynamic entry: " . pg_last_error($conn) . "</p>";
        }
    } else {
        $message .= '<p>Please fill in at least the "Quest ID" and "Topic" fields.</p>';
    }
}

/********************************************************************
 *  DELETE DYNAMIC ENTRY
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_dynamic') {
    $id = intval($_POST['dynamic_id'] ?? 0);
    
    if ($id > 0) {
        $query = "DELETE FROM {$schema}.oghma_dynamic WHERE id = $1";
        $result = pg_query_params($conn, $query, [$id]);

        if ($result) {
            $message .= "<p>Dynamic entry has been deleted successfully.</p>";
        } else {
            $message .= "<p>Error deleting dynamic entry: " . pg_last_error($conn) . "</p>";
        }
    } else {
        $message .= "<p>Invalid dynamic entry ID specified for deletion.</p>";
    }
}

/********************************************************************
 *  UPDATE DYNAMIC ENTRY
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_dynamic') {
    $id = intval($_POST['dynamic_id'] ?? 0);
    $id_quest = htmlspecialchars($_POST['dynamic_quest_new'] ?? '');
    $stage = intval($_POST['dynamic_stage_new'] ?? 0);
    $topic = htmlspecialchars($_POST['dynamic_topic_new'] ?? '');
    $topic_desc = htmlspecialchars($_POST['dynamic_topic_desc_new'] ?? '');
    $knowledge_class = htmlspecialchars($_POST['dynamic_knowledge_class_new'] ?? '');
    $topic_desc_basic = htmlspecialchars($_POST['dynamic_topic_desc_basic_new'] ?? '');
    $knowledge_class_basic = htmlspecialchars($_POST['dynamic_knowledge_class_basic_new'] ?? '');
    $tags = htmlspecialchars($_POST['dynamic_tags_new'] ?? '');
    $category = htmlspecialchars($_POST['dynamic_category_new'] ?? '');

    if ($id > 0 && !empty($id_quest) && !empty($topic)) {
        $query = "
            UPDATE $schema.oghma_dynamic 
            SET id_quest = $1,
                stage = $2,
                topic = $3,
                topic_desc = $4,
                knowledge_class = $5,
                topic_desc_basic = $6,
                knowledge_class_basic = $7,
                tags = $8,
                category = $9
            WHERE id = $10
        ";

        $result = pg_query_params($conn, $query, [
            $id_quest,
            $stage,
            $topic,
            $topic_desc,
            $knowledge_class,
            $topic_desc_basic,
            $knowledge_class_basic,
            $tags,
            $category,
            $id
        ]);

        if ($result) {
            $message .= "<p>Dynamic entry updated successfully!</p>";
        } else {
            $message .= "<p>Error updating dynamic entry: " . pg_last_error($conn) . "</p>";
        }
    } else {
        $message .= "<p>Please ensure all required fields are filled in.</p>";
    }
}

/********************************************************************
 *  DELETE ALL DYNAMIC ENTRIES
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_all_dynamic') {
    $truncateQuery = "TRUNCATE TABLE {$schema}.oghma_dynamic RESTART IDENTITY";
    $truncateResult = pg_query($conn, $truncateQuery);

    if ($truncateResult) {
        $message .= "<p style='color: #ff6464; font-weight: bold;'>All Dynamic Oghma entries have been deleted successfully.</p>";
    } else {
        $message .= "<p>Error deleting dynamic entries: " . pg_last_error($conn) . "</p>";
    }
}

?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
    /* Override main container styles */
    main {
        padding-top: 160px; /* Space for navbar */
        padding-bottom: 40px; /* Reduced space for footer */
        padding-left: 10px;
    }
    
    /* Override footer styles */
    footer {
        position: fixed;
        bottom: 0;
        width: 100%;
        height: 20px; /* Reduced footer height */
        background: #031633;
        z-index: 100;
    }

    /* Modal specific overrides */
    .modal-backdrop {
        overflow-y: auto !important;
        padding: 20px 0;
    }

    .modal-container {
        position: relative !important;
        top: auto !important;
        left: auto !important;
        transform: none !important;
        margin: 160px auto 40px auto !important;
        max-width: 800px !important;
        width: 90% !important;
    }

    .modal-body {
        max-height: calc(100vh - 300px);
        overflow-y: auto;
        padding-right: 15px;
    }

    /* Form field spacing */
    .modal-body label {
        display: block;
        margin-top: 15px;
        color: rgb(242, 124, 17);
        font-weight: bold;
    }

    .modal-body small {
        display: block;
        color: #888;
        margin-bottom: 5px;
    }

    .modal-body input[type="text"],
    .modal-body textarea {
        width: 100%;
        margin-bottom: 15px;
    }

    .modal-footer {
        position: sticky;
        bottom: 0;
        background: #3a3a3a;
        padding: 15px 0;
        margin-top: 20px;
        border-top: 1px solid #4a4a4a;
    }

    /* Table container height adjustment */
    .table-container {
        max-height: calc(100vh - 400px) !important;
    }
</style>

<main>
<div class="indent5">
<h1><img src="<?php echo $webRoot; ?>/ui/images/oghma_infinium.png" alt="Oghma Infinium" style="vertical-align:bottom;" width="32" height="32"> Oghma Infinium Management</h1>

    <div id="toast" class="toast-notification">
        <span class="message"></span>
    </div>

    <p>The <b>Oghma Infinium</b> is a "Skyrim Encyclopedia" that AI NPC's will use to help them roleplay.</p>
    <p>This is done by detecting topics during conversations, and injecting the appropiate information into the AI's prompt.</p>
    <p>To use it you must have <b>[MINIME_T5]</b> and <b></b>[OGHMA_INFINIUM]</b> enabled in the default profile. You also need Minime-T5 installed and running.</p>
    <br>
    <h3><strong>Ensure all topic titles are lowercase and spaces are replaced with underscores (_).</strong></h3>

    <h4>Example: "Fishy Stick" becomes "fishy_stick"</h4>
    <p>For Knowledge Class, we recommend you read this: <a href="https://docs.google.com/spreadsheets/d/1dcfctU-iOqprwy2BOc7___4Awteczgdlv8886KalPsQ/edit?pli=1&gid=338893641#gid=338893641" style="color: yellow;" target="_blank" rel="noopener noreferrer">Project Oghma</a></p>
    <br>
    <p>
    <b>Logic for searching articles:</b> <br>
    1. NPC will search for oghma article based on most relevant keyword. <br>
    2. Check knowledge_class to see if they access to the advanced article (topic_desc). <br>
    3. Check knowledge_class_basic to see if they access to the basic article (topic_desc_basic). <br>
    4. If all above fails, send "You do not know about X" to the prompt.
</p>
    <?php
    // Display messages - REMOVING THIS BLOCK
    /*if (!empty($message)) {
        echo '<div class="message">';
        echo $message;
        echo '</div>';
    }*/
    ?>
    <br>
    <h1>Batch Upload</h1>
    <div class="form-container">
        <form action="" method="post" enctype="multipart/form-data">
            <div>
                <label for="csv_file">Select .csv file to upload:</label>
                <br>
                <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
            </div>
            <div class="button-group">
                <input type="submit" name="submit_csv" value="Upload CSV" class="action-button upload-csv">
                <a href="?action=download_example" class="action-button download-csv">Download Example CSV</a>
            </div>
        </form>

        <p>You can verify that the entry has been uploaded successfully by navigating to <br><b>Server Actions -> Database Manager -> dwemer -> public -> oghma</b></p>
        <p>You can see how it picks a relevant article during conversation by navigating to <br><b>Server Actions -> Database Manager -> dwemer -> public -> audit_memory</b></p>
        <p>All uploaded topics will be saved into the <code>oghma</code> table. This overwrites any existing entries with the same topic.</p>
        <br>

        <form action="" method="post">
            <input type="hidden" name="action" value="delete_all">
            <input type="submit" class="btn-danger" value="Delete All Oghma Entries" 
                   onclick="return confirm('Are you sure you want to delete ALL entries? This cannot be undone!');">
        </form>
        <br>
        <form action="<?php echo $webRoot; ?>/ui/oghma_reset.php" method="post">
            <input type="submit" class="btn-danger" value="Factory Reset Oghma Database" 
                   onclick="return confirm('Are you sure you want to reset the Oghma database to factory settings? This will delete all current entries and restore the default ones.');">
        </form>
        <p>You can download a backup of the full Oghma database in the <a href="https://discord.gg/NDn9qud2ug" target="_blank" rel="noopener">csv files channel in our discord</a>.</p>
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
echo '<br>';
// Category buttons
echo '<div>';
echo '<h1 id="entries">Oghma Infinium Entries</h1>';
echo '<div class="action-container">';
echo '<button onclick="openNewEntryModal()" class="action-button add-new">Add New Entry</button>';
echo '<div class="search-container">';
echo '<input type="text" id="searchBox" placeholder="Search topics..." style="flex-grow: 1; padding: 8px; border-radius: 4px; border: 1px solid #555555; background-color: #4a4a4a; color: #f8f9fa;">';
echo '<button onclick="applySearch()" class="action-button edit ">Search</button>';
echo '</div>';
echo '</div>';
echo '<br>';

// Filter buttons
echo '<div class="filter-buttons">';
echo '<a class="alphabet-button" href="?#entries">All Categories</a>';
foreach ($categories as $cat) {
    $catEncoded = urlencode($cat);
    $style = ($selectedCategory === $cat) ? 'style="background-color:#0056b3;"' : '';
    echo "<a class=\"alphabet-button\" $style href=\"?cat=$catEncoded#entries\">" . htmlspecialchars($cat) . "</a>";
}
echo '<br>';
// Sorting links
$baseUrl = '?';
if ($selectedCategory) $baseUrl .= 'cat=' . urlencode($selectedCategory) . '&';
if ($letter) $baseUrl .= 'letter=' . urlencode($letter) . '&';

echo '</div>';
echo '<a class="alphabet-button" href="' . $baseUrl . 'order=asc#entries">🔼 Ascending</a>';
echo '<a class="alphabet-button" href="' . $baseUrl . 'order=desc#entries">🔽 Descending</a>';
// Build query
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

if ($selectedCategory && $letter && $searchTerm) {
    $query = "
        SELECT topic, topic_desc, knowledge_class, topic_desc_basic,
               knowledge_class_basic, tags, category
        FROM $schema.oghma
        WHERE category = $1
          AND topic ILIKE $2
          AND topic ILIKE $3
        ORDER BY topic $order
    ";
    $params = [$selectedCategory, $letter . '%', '%' . $searchTerm . '%'];
} elseif ($selectedCategory && $searchTerm) {
    $query = "
        SELECT topic, topic_desc, knowledge_class, topic_desc_basic,
               knowledge_class_basic, tags, category
        FROM $schema.oghma
        WHERE category = $1
          AND topic ILIKE $2
        ORDER BY topic $order
    ";
    $params = [$selectedCategory, '%' . $searchTerm . '%'];
} elseif ($letter && $searchTerm) {
    $query = "
        SELECT topic, topic_desc, knowledge_class, topic_desc_basic,
               knowledge_class_basic, tags, category
        FROM $schema.oghma
        WHERE topic ILIKE $1
          AND topic ILIKE $2
        ORDER BY topic $order
    ";
    $params = [$letter . '%', '%' . $searchTerm . '%'];
} elseif ($searchTerm) {
    $query = "
        SELECT topic, topic_desc, knowledge_class, topic_desc_basic,
               knowledge_class_basic, tags, category
        FROM $schema.oghma
        WHERE topic ILIKE $1
        ORDER BY topic $order
    ";
    $params = ['%' . $searchTerm . '%'];
} elseif ($selectedCategory && $letter) {
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

echo '<a id="entries"></a>';
echo '<div class="table-container">';
echo '<table>';
echo '<tr>
        <th>Topic</th>
        <th>Topic Description</th>
        <th>Knowledge Class</th>
        <th>Topic Description (Basic)</th>
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
        echo '<button onclick="openEditModal(' . 
            htmlspecialchars(json_encode([
                'topic' => $topic,
                'topic_desc' => $topic_desc,
                'knowledge_class' => $knowledge_class,
                'topic_desc_basic' => $topic_desc_basic,
                'knowledge_class_basic' => $knowledge_class_basic,
                'tags' => $tags,
                'category' => $category
            ]), ENT_QUOTES, 'UTF-8') . 
            ')" class="action-button edit">Edit</button>';
        
        echo '</div>';
        echo '</td>';
        echo '</tr>';

        $rowCount++;
    }

    echo '</table>';
    echo '</div>';

    if ($rowCount === 0) {
        echo '<p>No entries found.</p>';
    }
} else {
    echo '<p>Error fetching Oghma entries: ' . pg_last_error($conn) . '</p>';
}

// Add Dynamic Oghma Entries Section
echo '<br><br>';
echo '<h1>Dynamic Oghma</h1>';
echo '<p>Entires in the Dynamic Oghma table will update the Oghma table above whenever the quest ID & stage ID for a quest is reached.</p>';
echo '<p>Any changes from a topic in this table will override whatever is in the Oghma table.</p>';
echo '<p>You can leave cells empty so they do not overwrite specific cells from the Oghma table.</p>';
echo '<p>If a cell has the text "clearall" in it, it will clear that cell in the Oghma table.</p>';
echo '<p>You also can introduce new topics to the Oghma table as well.</p>';
echo '<p>It is currently empty by default. We need your help adding more entries!</p>';
echo '<p><a href="https://docs.google.com/spreadsheets/d/1dcfctU-iOqprwy2BOc7___4Awteczgdlv8886KalPsQ/edit?gid=243486711#gid=243486711" style="color: yellow;" target="_blank" rel="noopener noreferrer">Would you like to know more?</a></p>';
echo '<br>';
echo '<h1>Batch Upload</h1>';
echo '<div class="form-container">';
echo '<form action="" method="post" enctype="multipart/form-data">';
echo '<div>';
echo '<label for="dynamic_csv_file">Select .csv file to upload dynamic entries:</label>';
echo '<br>';
echo '<input type="file" name="dynamic_csv_file" id="dynamic_csv_file" accept=".csv" required>';
echo '</div>';
echo '<div class="button-group">';
echo '<input type="submit" name="submit_dynamic_csv" value="Upload CSV" class="action-button upload-csv">';
echo '<a href="../data/oghma_dynamic_example.csv" class="action-button download-csv">Download Example CSV</a>';
echo '</div>';
echo '</form>';
echo '<p>You can verify that the entries have been uploaded successfully by navigating to <br><b>Server Actions -> Database Manager -> dwemer -> public -> oghma_dynamic</b></p>';
echo '<p>You see what quests CHIM have detected by navigating to <br><b>Server Actions -> Database Manager -> dwemer -> public -> questlog</b></p>';
echo '<p>All uploaded entries will be saved into the <code>oghma_dynamic</code> table.</p>';
echo '<br>';
echo '<form action="" method="post">';
echo '<input type="hidden" name="action" value="delete_all_dynamic">';
echo '<input type="submit" class="btn-danger" value="Delete All Dynamic Entries" onclick="return confirm(\'Are you sure you want to delete ALL dynamic entries? This cannot be undone!\');">';
echo '</form>';
echo '<br>';
echo '</div>';
echo '<br>';
echo '<br>';
echo '<h1>Dynamic Oghma Entries</h1>';
echo '<div class="action-container">';
echo '<button onclick="openNewDynamicEntryModal()" class="action-button add-new">Add New Dynamic Entry</button>';
echo '</div>';

// Fetch categories for dynamic entries
$dynamicCatQuery = "SELECT DISTINCT category FROM $schema.oghma_dynamic WHERE category IS NOT NULL AND category <> '' ORDER BY category";
$dynamicCatResult = pg_query($conn, $dynamicCatQuery);
$dynamicCategories = [];
if ($dynamicCatResult) {
    while ($row = pg_fetch_assoc($dynamicCatResult)) {
        $dynamicCategories[] = $row['category'];
    }
}

// Get selected category for dynamic entries
$selectedDynamicCategory = $_GET['dynamic_cat'] ?? '';

// Filter buttons for dynamic entries
echo '<div class="filter-buttons">';
echo '<a class="alphabet-button" href="?' . (isset($_GET['cat']) ? 'cat=' . urlencode($_GET['cat']) . '&' : '') . '#dynamic">All Categories</a>';
foreach ($dynamicCategories as $cat) {
    $catEncoded = urlencode($cat);
    $style = ($selectedDynamicCategory === $cat) ? 'style="background-color:#0056b3;"' : '';
    echo "<a class=\"alphabet-button\" $style href=\"?" . (isset($_GET['cat']) ? 'cat=' . urlencode($_GET['cat']) . '&' : '') . "dynamic_cat=$catEncoded#dynamic\">" . htmlspecialchars($cat) . "</a>";
}
echo '</div><br>';

// Query for dynamic entries with category filter
$dynamicQuery = "
    SELECT id, id_quest, stage, topic, topic_desc, knowledge_class, topic_desc_basic,
           knowledge_class_basic, tags, category
    FROM $schema.oghma_dynamic
";

if ($selectedDynamicCategory) {
    $dynamicQuery .= " WHERE category = $1
    ORDER BY id_quest, stage ASC";
    $dynamicResult = pg_query_params($conn, $dynamicQuery, [$selectedDynamicCategory]);
} else {
    $dynamicQuery .= " ORDER BY id_quest, stage ASC";
    $dynamicResult = pg_query($conn, $dynamicQuery);
}

echo '<div class="table-container">';
echo '<table>';
echo '<tr>
        <th>Quest ID</th>
        <th>Stage</th>
        <th>Topic</th>
        <th>Topic Description</th>
        <th>Knowledge Class</th>
        <th>Topic Description (Basic)</th>
        <th>Knowledge Class (Basic)</th>
        <th>Tags</th>
        <th>Category</th>
        <th>Action</th>
      </tr>';

if ($dynamicResult) {
    $rowCount = 0;
    while ($row = pg_fetch_assoc($dynamicResult)) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['id_quest'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['stage'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['topic'] ?? '') . '</td>';
        echo '<td>' . nl2br(htmlspecialchars($row['topic_desc'] ?? '')) . '</td>';
        echo '<td>' . nl2br(htmlspecialchars($row['knowledge_class'] ?? '')) . '</td>';
        echo '<td>' . nl2br(htmlspecialchars($row['topic_desc_basic'] ?? '')) . '</td>';
        echo '<td>' . nl2br(htmlspecialchars($row['knowledge_class_basic'] ?? '')) . '</td>';
        echo '<td>' . nl2br(htmlspecialchars($row['tags'] ?? '')) . '</td>';
        echo '<td>' . nl2br(htmlspecialchars($row['category'] ?? '')) . '</td>';
        
        // Add edit button column
        echo '<td style="white-space: nowrap;">';
        echo '<div style="display: flex; gap: 4px;">';
        echo '<button onclick="openDynamicEditModal(' . 
            htmlspecialchars(json_encode([
                'id' => $row['id'],
                'id_quest' => $row['id_quest'],
                'stage' => $row['stage'],
                'topic' => $row['topic'],
                'topic_desc' => $row['topic_desc'],
                'knowledge_class' => $row['knowledge_class'],
                'topic_desc_basic' => $row['topic_desc_basic'],
                'knowledge_class_basic' => $row['knowledge_class_basic'],
                'tags' => $row['tags'],
                'category' => $row['category']
            ]), ENT_QUOTES, 'UTF-8') . 
            ')" class="action-button edit">Edit</button>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
        $rowCount++;
    }

    echo '</table>';
    echo '</div>';

    if ($rowCount === 0) {
        echo '<p>No dynamic entries found.</p>';
    }
} else {
    echo '<p>Error fetching Dynamic Oghma entries: ' . pg_last_error($conn) . '</p>';
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
                <textarea name="topic_desc_basic_new" id="edit_topic_desc_basic" rows="8"></textarea>
                

                <label for="edit_knowledge_class_basic">Knowledge Class (Basic):</label>
                <small>Who should have access to this basic knowledge. Leave empty to allow all NPCs to know this. Separate tags by commas. <a href="https://docs.google.com/spreadsheets/d/1dcfctU-iOqprwy2BOc7___4Awteczgdlv8886KalPsQ/edit?pli=1&gid=338893641" style="color: yellow;" target="_blank" rel="noopener noreferrer"> More information can be found here</a>.</small>
                <input type="text" name="knowledge_class_basic_new" id="edit_knowledge_class_basic">

                <label for="edit_tags">Tags:</label>
                <small>Not currently in use.</small>
                <input type="text" name="tags_new" id="edit_tags">

                <label for="edit_category">Category:</label>
                <small>Category for database searching.</small>
                <input type="text" name="category_new" id="edit_category">

                <div class="modal-footer">
                    <button type="submit" name="submit" value="update" class="btn-save">Save Changes</button>
                    <button type="button" onclick="deleteEntry()" class="btn-danger">Delete</button>
                    <button type="button" onclick="closeEditModal()" class="btn-base btn-cancel">Cancel</button>
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
                <small>Who should have access to this basic knowledge. Leave empty to allow all NPCs to know this. It is recommended for most basic articles to leave it blank. Separate tags by commas. <a href="https://docs.google.com/spreadsheets/d/1dcfctU-iOqprwy2BOc7___4Awteczgdlv8886KalPsQ/edit?pli=1&gid=338893641" style="color: yellow;" target="_blank" rel="noopener noreferrer"> More information can be found here</a>.</small>
                <input type="text" name="knowledge_class_basic" id="knowledge_class_basic">

                <label for="tags">Tags:</label>
                <small>Not currently in use.</small>
                <input type="text" name="tags" id="tags">

                <label for="category">Category:</label>
                <small>Category for database searching.</small>
                <input type="text" name="category" id="category">

                <div class="modal-footer">
                    <button type="submit" class="btn-save">Save</button>
                    <button type="button" onclick="closeNewEntryModal()" class="btn-base btn-cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="newDynamicEntryModal" class="modal-backdrop">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">Add New Dynamic Oghma Entry</h2>
            <p>You can leave sections blank so it does not overwrite the existing info in the Oghma Table!</p>
        </div>
        <div class="modal-body">
            <form action="" method="post">
                <input type="hidden" name="submit_dynamic" value="1">

                <label for="id_quest">Quest ID (required):</label>
                <small>The quest ID to trigger the dynamic entry.</small>
                <input type="text" name="id_quest" id="id_quest" required>

                <label for="stage">Quest Stage (required):</label>
                <small>The stage ID from the quest to trigger the dynamic entry.</small>
                <input type="number" name="stage" id="stage" value="0">

                <label for="dynamic_topic">Topic (required):</label>
                <small>Topic that will be updated or added in the main Oghma table.</small>
                <input type="text" name="dynamic_topic" id="dynamic_topic" required>

                <label for="dynamic_topic_desc">Topic Description:</label>
                <small>Advanced knowledge information on the subject.</small>
                <textarea name="dynamic_topic_desc" id="dynamic_topic_desc" rows="5"></textarea>

                <label for="dynamic_knowledge_class">Knowledge Class:</label>
                <small>Who should have access to this advanced knowledge. Must be comma seperated.</small>
                <input type="text" name="dynamic_knowledge_class" id="dynamic_knowledge_class">

                <label for="dynamic_topic_desc_basic">Topic Description (Basic):</label>
                <small>Basic information about the subject.</small>
                <textarea name="dynamic_topic_desc_basic" id="dynamic_topic_desc_basic" rows="5"></textarea>

                <label for="dynamic_knowledge_class_basic">Knowledge Class (Basic):</label>
                <small>Who should have access to this basic knowledge. Leave blank to allow all NPCs to know this. Must be comma seperated.</small>
                <input type="text" name="dynamic_knowledge_class_basic" id="dynamic_knowledge_class_basic">

                <label for="dynamic_tags">Tags:</label>
                <small>Additional search tags.</small>
                <input type="text" name="dynamic_tags" id="dynamic_tags">

                <label for="dynamic_category">Category:</label>
                <small>Category for organization.</small>
                <input type="text" name="dynamic_category" id="dynamic_category">

                <div class="modal-footer">
                    <button type="submit" class="btn-save">Save</button>
                    <button type="button" onclick="closeNewDynamicEntryModal()" class="btn-base btn-cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="editDynamicModal" class="modal-backdrop">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">Edit Dynamic Oghma Entry</h2>
            <p>You can leave sections blank so it does not overwrite the existing info in the Oghma Table!</p>
        </div>
        <div class="modal-body">
            <form action="" method="post">
                <input type="hidden" name="action" value="update_dynamic">
                <input type="hidden" name="dynamic_id" id="edit_dynamic_id">

                <label for="edit_dynamic_quest">Quest ID (required):</label>
                <small>The quest ID to trigger the dynamic entry.</small>
                <input type="text" name="dynamic_quest_new" id="edit_dynamic_quest" required>

                <label for="edit_dynamic_stage">Quest Stage (required):</label>
                <small>The stage ID from the quest to trigger the dynamic entry.</small>
                <input type="number" name="dynamic_stage_new" id="edit_dynamic_stage" value="0" required>

                <label for="edit_dynamic_topic">Topic (required):</label>
                <small>Topic that will be updated or added in the main Oghma table.</small>
                <input type="text" name="dynamic_topic_new" id="edit_dynamic_topic" required>

                <label for="edit_dynamic_topic_desc">Topic Description:</label>
                <small>Advanced knowledge information on the subject.</small>
                <textarea name="dynamic_topic_desc_new" id="edit_dynamic_topic_desc" rows="5"></textarea>

                <label for="edit_dynamic_knowledge_class">Knowledge Class:</label>
                <small>Who should have access to this advanced knowledge. Must be comma separated.</small>
                <input type="text" name="dynamic_knowledge_class_new" id="edit_dynamic_knowledge_class">

                <label for="edit_dynamic_topic_desc_basic">Topic Description (Basic):</label>
                <small>Basic information about the subject.</small>
                <textarea name="dynamic_topic_desc_basic_new" id="edit_dynamic_topic_desc_basic" rows="5"></textarea>

                <label for="edit_dynamic_knowledge_class_basic">Knowledge Class (Basic):</label>
                <small>Who should have access to this basic knowledge. Leave blank to allow all NPCs to know this. Must be comma separated.</small>
                <input type="text" name="dynamic_knowledge_class_basic_new" id="edit_dynamic_knowledge_class_basic">

                <label for="edit_dynamic_tags">Tags:</label>
                <small>Additional search tags.</small>
                <input type="text" name="dynamic_tags_new" id="edit_dynamic_tags">

                <label for="edit_dynamic_category">Category:</label>
                <small>Category for organization.</small>
                <input type="text" name="dynamic_category_new" id="edit_dynamic_category">

                <div class="modal-footer">
                    <button type="submit" class="btn-save">Save Changes</button>
                    <button type="button" onclick="deleteDynamicEntry()" class="btn-danger">Delete</button>
                    <button type="button" onclick="closeDynamicEditModal()" class="btn-base btn-cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Define webRoot for JavaScript
var webRoot = '<?php echo $webRoot; ?>';

function openEditModal(data) {
    try {
        const decodeHTML = (html) => {
            const txt = document.createElement('textarea');
            txt.innerHTML = html;
            return txt.value;
        };

        document.getElementById("edit_topic_original").value = decodeHTML(data.topic);
        document.getElementById("edit_topic").value = decodeHTML(data.topic);
        document.getElementById("edit_topic_desc").value = decodeHTML(data.topic_desc);
        document.getElementById("edit_knowledge_class").value = decodeHTML(data.knowledge_class);
        document.getElementById("edit_topic_desc_basic").value = decodeHTML(data.topic_desc_basic);
        document.getElementById("edit_knowledge_class_basic").value = decodeHTML(data.knowledge_class_basic);
        document.getElementById("edit_tags").value = decodeHTML(data.tags);
        document.getElementById("edit_category").value = decodeHTML(data.category);
        
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

function openNewEntryModal() {
    document.getElementById("newEntryModal").style.display = "block";
    document.body.style.overflow = "hidden";
}

function closeNewEntryModal() {
    document.getElementById("newEntryModal").style.display = "none";
    document.body.style.overflow = "auto";
}

function deleteEntry() {
    const topic = document.getElementById('edit_topic_original').value;
    if (confirm("Are you sure you want to delete: " + topic + "?")) {
        const form = document.createElement('form');
        form.method = 'POST';
        const currentCategory = new URLSearchParams(window.location.search).get('cat');
        form.action = currentCategory ? `?cat=${currentCategory}#entries` : '?#entries';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_single">
            <input type="hidden" name="topic" value="${topic}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function applySearch() {
    const searchTerm = document.getElementById("searchBox").value.trim();
    let url = new URL(window.location.href);
    const urlParams = new URLSearchParams(window.location.search);
    
    // Update or add search parameter
    if (searchTerm) {
        urlParams.set("search", searchTerm);
    } else {
        urlParams.delete("search");
    }
    
    // Preserve existing parameters if they exist
    const currentCategory = urlParams.get("cat");
    const currentLetter = urlParams.get("letter");
    const currentOrder = urlParams.get("order");
    
    if (currentCategory) urlParams.set("cat", currentCategory);
    if (currentLetter) urlParams.set("letter", currentLetter);
    if (currentOrder) urlParams.set("order", currentOrder);
    
    // Create the new URL
    window.location.href = "?" + urlParams.toString() + "#entries";
}

// Add enter key support for the search box
document.getElementById("searchBox").addEventListener("keypress", function(e) {
    if (e.key === "Enter") {
        e.preventDefault();
        applySearch();
    }
});

// Set initial search box value from URL
window.addEventListener("load", function() {
    const urlParams = new URLSearchParams(window.location.search);
    const searchTerm = urlParams.get("search");
    if (searchTerm) {
        document.getElementById("searchBox").value = searchTerm;
    }
});

// Add toast notification JavaScript function
function showToast(message, duration = 5000) {
    const toast = document.getElementById('toast');
    const messageSpan = toast.querySelector('.message');
    messageSpan.textContent = message;
    toast.classList.add('show');
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, duration);
}

// Update PHP message handling
<?php if (!empty($message)): ?>
document.addEventListener('DOMContentLoaded', function() {
    showToast(<?php echo json_encode(strip_tags($message)); ?>);
});
<?php endif; ?>

function openNewDynamicEntryModal() {
    document.getElementById("newDynamicEntryModal").style.display = "block";
    document.body.style.overflow = "hidden";
}

function closeNewDynamicEntryModal() {
    document.getElementById("newDynamicEntryModal").style.display = "none";
    document.body.style.overflow = "auto";
}

function openDynamicEditModal(data) {
    try {
        const decodeHTML = (html) => {
            const txt = document.createElement('textarea');
            txt.innerHTML = html;
            return txt.value;
        };

        document.getElementById("edit_dynamic_id").value = decodeHTML(data.id);
        document.getElementById("edit_dynamic_quest").value = decodeHTML(data.id_quest);
        document.getElementById("edit_dynamic_stage").value = decodeHTML(data.stage);
        document.getElementById("edit_dynamic_topic").value = decodeHTML(data.topic);
        document.getElementById("edit_dynamic_topic_desc").value = decodeHTML(data.topic_desc);
        document.getElementById("edit_dynamic_knowledge_class").value = decodeHTML(data.knowledge_class);
        document.getElementById("edit_dynamic_topic_desc_basic").value = decodeHTML(data.topic_desc_basic);
        document.getElementById("edit_dynamic_knowledge_class_basic").value = decodeHTML(data.knowledge_class_basic);
        document.getElementById("edit_dynamic_tags").value = decodeHTML(data.tags);
        document.getElementById("edit_dynamic_category").value = decodeHTML(data.category);
        
        document.getElementById("editDynamicModal").style.display = "block";
        document.body.style.overflow = "hidden";
    } catch (error) {
        console.error('Error in openDynamicEditModal:', error);
        alert('There was an error opening the edit form. Please try again.');
    }
}

function closeDynamicEditModal() {
    document.getElementById("editDynamicModal").style.display = "none";
    document.body.style.overflow = "auto";
}

function deleteDynamicEntry() {
    const id = document.getElementById('edit_dynamic_id').value;
    if (confirm("Are you sure you want to delete this dynamic entry?")) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_dynamic">
            <input type="hidden" name="dynamic_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
</main>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
