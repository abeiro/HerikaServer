<?php
// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

$TITLE = "📙CHIM - Oghma Infinium";

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
                    || setweight(to_tsvector(coalesce(topic_desc_basic, '')), 'C')
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
                                    || setweight(to_tsvector(coalesce(topic_desc_basic, '')), 'C')
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
                        // Check if record with same id_quest, stage, and topic already exists
                        $checkQuery = "
                            SELECT id FROM $schema.oghma_dynamic 
                            WHERE id_quest = $1 AND stage = $2 AND topic = $3
                        ";
                        $checkResult = pg_query_params($conn, $checkQuery, [$id_quest, $stage, $topic]);
                        
                        if ($checkResult && pg_num_rows($checkResult) > 0) {
                            // Update existing record
                            $existingRow = pg_fetch_assoc($checkResult);
                            $updateQuery = "
                                UPDATE $schema.oghma_dynamic 
                                SET topic_desc = $1,
                                    knowledge_class = $2,
                                    topic_desc_basic = $3,
                                    knowledge_class_basic = $4,
                                    tags = $5,
                                    category = $6
                                WHERE id = $7
                            ";
                            
                            $result = pg_query_params($conn, $updateQuery, [
                                $topic_desc,
                                $knowledge_class,
                                $topic_desc_basic,
                                $knowledge_class_basic,
                                $tags,
                                $category,
                                $existingRow['id']
                            ]);
                        } else {
                            // Insert new record
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
                        }

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
                    || setweight(to_tsvector(coalesce(topic_desc_basic, '')), 'C')
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
        // Check if record with same id_quest, stage, and topic already exists
        $checkQuery = "
            SELECT id FROM $schema.oghma_dynamic 
            WHERE id_quest = $1 AND stage = $2 AND topic = $3
        ";
        $checkResult = pg_query_params($conn, $checkQuery, [$id_quest, $stage, $topic]);
        
        if ($checkResult && pg_num_rows($checkResult) > 0) {
            $message .= "<p style='color: orange;'>A dynamic entry with Quest ID '$id_quest', Stage '$stage', and Topic '$topic' already exists. Please edit the existing entry or use different values.</p>";
        } else {
            // Insert new record
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
        padding-left: 10%;
        padding-right: 10%;
        width: 100%;
        margin: 0;
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

    /* Tab Navigation */
    .tab-navigation {
        display: flex;
        border-bottom: 2px solid #4a4a4a;
        margin-bottom: 30px;
        background: #2a2a2a;
        border-radius: 8px 8px 0 0;
    }

    .tab-button {
        flex: 1;
        padding: 15px 20px;
        background: #3a3a3a;
        color: rgb(242, 124, 17);
        border: none;
        cursor: pointer;
        font-family: 'MagicCards', serif;
        font-size: 18px;
        font-weight: bold;
        word-spacing: 8px;
        transition: all 0.3s ease;
        border-radius: 8px 8px 0 0;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
    }

    .tab-button:first-child {
        border-right: 1px solid #4a4a4a;
    }

    .tab-button.active {
        background: rgb(242, 124, 17);
        color: #000;
        font-weight: bold;
        text-shadow: 1px 1px 2px rgba(255,255,255,0.3);
    }

    .tab-button:hover:not(.active) {
        background: #4a4a4a;
        color: rgb(255, 140, 30);
    }

    /* Tab Content */
    .tab-content {
        display: none;
        animation: fadeIn 0.3s ease-in;
    }

    .tab-content.active {
        display: block;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    /* Content Layout Improvements */
    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }

    .content-section {
        background: #2a2a2a;
        padding: 25px;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
    }

    .content-section h2 {
        font-family: 'MagicCards', serif;
        color: rgb(242, 124, 17);
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        word-spacing: 6px;
        margin-bottom: 15px;
        font-size: 1.4em;
    }

    .full-width-section {
        grid-column: 1 / -1;
    }

    .full-width-section h2 {
        font-family: 'MagicCards', serif;
        color: rgb(242, 124, 17);
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        word-spacing: 6px;
        margin-bottom: 15px;
        font-size: 1.6em;
        text-align: center;
    }

    /* Form Improvements */
    .form-container {
        background: #2a2a2a;
        padding: 25px;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
        margin-bottom: 20px;
    }

    .button-group {
        display: flex;
        gap: 15px;
        margin-top: 15px;
        flex-wrap: wrap;
    }

    /* Font Face Declaration */
    @font-face {
        font-family: 'MagicCards';
        src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
    }

    /* Header Styling */
    .page-header {
        text-align: center;
        margin-bottom: 30px;
        padding: 20px;
        background: #2a2a2a;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
    }

    .page-header h1 {
        margin-bottom: 15px;
        font-family: 'MagicCards', serif;
        word-spacing: 8px;
        font-size: 2.2em;
        color: rgb(242, 124, 17);
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
    }

    #title-text {
        font-family: 'MagicCards', serif;
    }

    /* Header content transitions */
    #header-content > div {
        transition: opacity 0.3s ease-in-out;
        opacity: 1;
    }

    #title-text {
        transition: all 0.3s ease-in-out;
    }

    #dynamic-header-content {
        opacity: 0;
    }

    /* Logic Section Styling */
    .logic-section {
        margin: 25px 0;
        padding: 20px;
        background: #1a1a1a;
        border-radius: 8px;
        border: 2px solid rgb(242, 124, 17);
        box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    }

    .logic-title {
        text-align: center;
        color: rgb(242, 124, 17);
        margin-bottom: 20px;
        font-size: 1.3em;
        font-weight: bold;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        font-family: 'MagicCards', serif;
        word-spacing: 6px;
    }

    .logic-steps {
        display: grid;
        gap: 15px;
    }

    .logic-step {
        display: flex;
        align-items: flex-start;
        gap: 15px;
        padding: 15px;
        background: #2a2a2a;
        border-radius: 6px;
        border-left: 4px solid rgb(242, 124, 17);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .logic-step:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(242, 124, 17, 0.2);
    }

    .step-number {
        flex-shrink: 0;
        width: 30px;
        height: 30px;
        background: rgb(242, 124, 17);
        color: #000;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 14px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }

    .step-content {
        flex: 1;
    }

    .step-content strong {
        color: rgb(242, 124, 17);
        display: block;
        margin-bottom: 5px;
        font-size: 1.1em;
    }

    .step-content p {
        margin: 0;
        line-height: 1.4;
        color: #e0e0e0;
    }

    .step-content code {
        background: #4a4a4a;
        padding: 2px 6px;
        border-radius: 3px;
        color: #ffeb3b;
        font-family: 'Courier New', monospace;
        font-size: 0.9em;
    }

    .step-content em {
        color: #81c784;
        font-style: italic;
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
        max-height: calc(100vh - 450px) !important;
        margin-top: 20px;
        width: 100%;
        overflow-x: auto;
    }

    /* Table styling improvements */
    .table-container table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
    }

    /* Column width optimization */
    .table-container th:nth-child(1), /* Topic */
    .table-container td:nth-child(1) {
        width: 12%;
        min-width: 120px;
    }

    .table-container th:nth-child(2), /* Topic Description */
    .table-container td:nth-child(2) {
        width: 25%;
        min-width: 200px;
    }

    .table-container th:nth-child(3), /* Knowledge Class */
    .table-container td:nth-child(3) {
        width: 12%;
        min-width: 120px;
    }

    .table-container th:nth-child(4), /* Topic Description (Basic) */
    .table-container td:nth-child(4) {
        width: 20%;
        min-width: 180px;
    }

    .table-container th:nth-child(5), /* Knowledge Class (Basic) */
    .table-container td:nth-child(5) {
        width: 12%;
        min-width: 120px;
    }

    .table-container th:nth-child(6), /* Tags */
    .table-container td:nth-child(6) {
        width: 8%;
        min-width: 80px;
    }

    .table-container th:nth-child(7), /* Category */
    .table-container td:nth-child(7) {
        width: 8%;
        min-width: 80px;
    }

    .table-container th:nth-child(8), /* Action */
    .table-container td:nth-child(8) {
        width: 8%;
        min-width: 80px;
    }

    /* Text wrapping and overflow handling */
    .table-container td {
        word-wrap: break-word;
        overflow-wrap: break-word;
        hyphens: auto;
        vertical-align: top;
        padding: 8px;
        line-height: 1.4;
    }

    .table-container th {
        padding: 10px 8px;
        font-weight: bold;
        text-align: left;
        vertical-align: top;
    }

    /* Responsive table for smaller screens */
    @media (max-width: 1200px) {
        .table-container {
            font-size: 0.9em;
        }
        
        .table-container th:nth-child(2), /* Topic Description */
        .table-container td:nth-child(2) {
            width: 30%;
        }
        
        .table-container th:nth-child(4), /* Topic Description (Basic) */
        .table-container td:nth-child(4) {
            width: 25%;
        }
    }

    @media (max-width: 900px) {
        .table-container {
            font-size: 0.8em;
        }
        
        .table-container th,
        .table-container td {
            padding: 6px 4px;
        }
    }

    /* Filter improvements */
    .filter-section {
        background: #2a2a2a;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
        margin-bottom: 20px;
    }

    .action-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 20px;
    }

    .search-container {
        display: flex;
        gap: 10px;
        min-width: 300px;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        main {
            padding-left: 5%;
            padding-right: 5%;
        }
        
        .content-grid {
            grid-template-columns: 1fr;
        }
        
        .tab-button {
            padding: 12px 15px;
            font-size: 16px;
            color: rgb(242, 124, 17);
        }
        
        .search-container {
            min-width: 200px;
        }
        
        .action-container {
            flex-direction: column;
            align-items: stretch;
        }
        
        .page-header {
            padding: 15px;
        }
        
        .content-section {
            padding: 15px;
        }
        
        .logic-section {
            padding: 15px;
            margin: 15px 0;
        }
        
        .logic-step {
            padding: 12px;
            gap: 12px;
        }
        
        .step-number {
            width: 25px;
            height: 25px;
            font-size: 12px;
        }
    }

    @media (max-width: 480px) {
        main {
            padding-left: 2%;
            padding-right: 2%;
        }
        
        .page-header h1 {
            font-size: 1.5em;
        }
        
        .tab-button {
            padding: 10px 12px;
            font-size: 15px;
            color: rgb(242, 124, 17);
        }
        
        .logic-section {
            padding: 10px;
            margin: 10px 0;
        }
        
        .logic-step {
            padding: 10px;
            gap: 10px;
            flex-direction: column;
            text-align: center;
        }
        
        .step-number {
            align-self: center;
        }
    }
</style>

<main>
    <div id="toast" class="toast-notification">
        <span class="message"></span>
    </div>

    <div class="page-header">
        <h1 id="page-title">
            <img src="<?php echo $webRoot; ?>/ui/images/oghma_infinium.png" alt="Oghma Infinium" style="vertical-align:bottom;" width="32" height="32"> 
            <span id="title-text">Oghma Infinium</span>
            <a href="https://dwemerdynamics.hostwiki.io/en/Oghma-Infinium-(RAG)" target="_blank" rel="noopener" 
               style="display: inline-block; margin-left: 15px; color: rgb(242, 124, 17); text-decoration: none; font-size: 0.7em; vertical-align: top; border: 2px solid rgb(242, 124, 17); border-radius: 50%; width: 24px; height: 24px; text-align: center; line-height: 20px; transition: all 0.3s ease;" 
               title="View detailed documentation about Oghma Infinium"
               onmouseover="this.style.background='rgb(242, 124, 17)'; this.style.color='white';" 
               onmouseout="this.style.background='transparent'; this.style.color='rgb(242, 124, 17)';">ℹ</a>
        </h1>
        
        <div id="header-content">
            <!-- Regular Oghma Content -->
            <div id="oghma-header-content">
                <p>The <b>Oghma Infinium</b> is a "Skyrim Encyclopedia" that AI NPC's will use to help them roleplay.</p>
                <p>This is done by detecting topics during conversations, and injecting the appropriate information into the AI's prompt.</p>
                <p>To use it you must have <b>[MINIME_T5]</b> and <b>[OGHMA_INFINIUM]</b> enabled in the default profile. You also need Minime-T5 installed and running.</p>
                
                <h3><strong>Ensure all topic titles are lowercase and spaces are replaced with underscores (_).</strong></h3>
                <h4>Example: "Fishy Stick" becomes "fishy_stick"</h4>
                <p>For Knowledge Class, we recommend you read this: <a href="https://docs.google.com/spreadsheets/d/1dcfctU-iOqprwy2BOc7___4Awteczgdlv8886KalPsQ/edit?pli=1&gid=338893641#gid=338893641" style="color: yellow;" target="_blank" rel="noopener noreferrer">Project Oghma</a></p>
                
                <div class="logic-section">
                    <h3 class="logic-title">🔍 Article Search Logic</h3>
                    <div class="logic-steps">
                        <div class="logic-step">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <strong>Keyword Search</strong>
                                <p>NPC searches for oghma article based on most relevant keyword during conversations.</p>
                            </div>
                        </div>
                        <div class="logic-step">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <strong>Advanced Access Check</strong>
                                <p>Check <code>knowledge_class</code> to see if they have access to the advanced article (<code>topic_desc</code>)</p>
                            </div>
                        </div>
                        <div class="logic-step">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <strong>Basic Access Check</strong>
                                <p>Check <code>knowledge_class_basic</code> to see if they have access to the basic article (<code>topic_desc_basic</code>)</p>
                            </div>
                        </div>
                        <div class="logic-step">
                            <div class="step-number">4</div>
                            <div class="step-content">
                                <strong>Fallback Response</strong>
                                <p>If all above fails, send <em>"You do not know about X"</em> to the prompt</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Dynamic Oghma Content -->
            <div id="dynamic-header-content" style="display: none;">
                <p>Entries in the <b>Dynamic Oghma</b> table will update the Oghma table above whenever the quest ID & stage ID for a quest is reached.</p>
                <p>Any changes from a topic in this table will override whatever is in the Oghma table.</p>
                <p>You can leave cells empty so they do not overwrite specific cells from the Oghma table.</p>
                <p>If a cell has the text <b>"clearall"</b> in it, it will clear that cell in the Oghma table.</p>
                <p>You also can introduce new topics to the Oghma table as well.</p>
                <p>It is currently empty by default. We need your help adding more entries!</p>
                <p><a href="https://docs.google.com/spreadsheets/d/1dcfctU-iOqprwy2BOc7___4Awteczgdlv8886KalPsQ/edit?gid=243486711#gid=243486711" style="color: yellow;" target="_blank" rel="noopener noreferrer">Would you like to know more?</a></p>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="tab-navigation">
        <button class="tab-button active" onclick="switchTab('oghma-tab')">
            📚 Oghma Infinium
        </button>
        <button class="tab-button" onclick="switchTab('dynamic-tab')">
            ⚡ Dynamic Oghma
        </button>
    </div>

    <!-- Regular Oghma Tab -->
    <div id="oghma-tab" class="tab-content active">
        <div class="content-grid">
            <div class="content-section">
                <h2>Batch Upload</h2>
                <form action="" method="post" enctype="multipart/form-data">
                    <div>
                        <label for="csv_file">Select .csv file to upload:</label>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv" required style="margin-top: 10px;">
                    </div>
                    <div class="button-group">
                        <input type="submit" name="submit_csv" value="Upload CSV" class="action-button upload-csv">
                        <a href="?action=download_example" class="action-button download-csv">Download Example CSV</a>
                    </div>
                </form>
                
                <p style="margin-top: 15px;">All uploaded topics will be saved into the <code>oghma</code> table. This overwrites any existing entries with the same topic.</p>
            </div>

            <div class="content-section">
                <h2>Database Management</h2>
                <p>Verify uploads: <br><b>Server Actions → Database Manager → dwemer → public → oghma</b></p>
                <p>View conversation usage: <br><b>Server Actions → Database Manager → dwemer → public → audit_memory</b></p>
                
                <div class="button-group" style="margin-top: 20px;">
                    <form action="" method="post" style="display: inline;">
                        <input type="hidden" name="action" value="delete_all">
                        <input type="submit" class="btn-danger" value="Delete All Entries" 
                               onclick="return confirm('Are you sure you want to delete ALL entries? This cannot be undone!');">
                    </form>
                    
                    <form action="<?php echo $webRoot; ?>/ui/oghma_reset.php" method="post" style="display: inline;">
                        <input type="submit" class="btn-danger" value="Factory Reset Database" 
                               onclick="return confirm('Are you sure you want to reset the Oghma database to factory settings? This will delete all current entries and restore the default ones.');">
                    </form>
                </div>
                
                <p style="margin-top: 15px;">Download backup: <a href="https://discord.gg/NDn9qud2ug" target="_blank" rel="noopener" style="color: yellow;">Discord CSV files channel</a></p>
            </div>
        </div>
        <div class="full-width-section">
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
            ?>
            
            <h2 id="entries">📋 Oghma Infinium Entries</h2>
            
            <div class="action-container">
                <button onclick="openNewEntryModal()" class="action-button add-new">Add New Entry</button>
                <div class="search-container">
                    <input type="text" id="searchBox" placeholder="Search topics..." style="flex-grow: 1; padding: 8px; border-radius: 4px; border: 1px solid #555555; background-color: #4a4a4a; color: #f8f9fa;">
                    <button onclick="applySearch()" class="action-button edit">Search</button>
                </div>
            </div>

            <div class="filter-section">
                <div style="margin-bottom: 15px;">
                    <strong>Filter by Category:</strong><br>
                    <div class="filter-buttons" style="margin-top: 10px;">
                        <a class="alphabet-button" href="?#entries">All Categories</a>
                        <?php
                        foreach ($categories as $cat) {
                            $catEncoded = urlencode($cat);
                            $style = ($selectedCategory === $cat) ? 'style="background-color:#0056b3;"' : '';
                            echo "<a class=\"alphabet-button\" $style href=\"?cat=$catEncoded#entries\">" . htmlspecialchars($cat) . "</a>";
                        }
                        ?>
                    </div>
                </div>
                
                <div>
                    <strong>Sort Order:</strong><br>
                    <?php
                    $baseUrl = '?';
                    if ($selectedCategory) $baseUrl .= 'cat=' . urlencode($selectedCategory) . '&';
                    if ($letter) $baseUrl .= 'letter=' . urlencode($letter) . '&';
                    ?>
                    <div style="margin-top: 10px;">
                        <a class="alphabet-button" href="<?php echo $baseUrl; ?>order=asc#entries">🔼 Ascending</a>
                        <a class="alphabet-button" href="<?php echo $baseUrl; ?>order=desc#entries">🔽 Descending</a>
                    </div>
                </div>
            </div>

            <?php
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
                    
                    // Knowledge Class column with badge styling
                    echo '<td style="font-size: 1.5em; line-height: 1.4;">';
                    if (!empty(trim($knowledge_class))) {
                        $knowledgeClasses = array_map('trim', explode(',', $knowledge_class));
                        foreach ($knowledgeClasses as $class) {
                            if (!empty($class)) {
                                echo '<span style="display: inline-block; background: rgba(242, 124, 17, 0.2); color: rgb(242, 124, 17); padding: 3px 8px; margin: 2px; border-radius: 4px; font-size: 0.85em; font-weight: 500;">' . htmlspecialchars($class) . '</span>';
                            }
                        }
                    } else {
                        echo '<span style="color: #888; font-style: italic;">Everyone</span>';
                    }
                    echo '</td>';
                    
                    echo '<td>' . nl2br($topic_desc_basic) . '</td>';
                    
                    // Knowledge Class Basic column with badge styling
                    echo '<td style="font-size: 1.5em; line-height: 1.4;">';
                    if (!empty(trim($knowledge_class_basic))) {
                        $knowledgeClassesBasic = array_map('trim', explode(',', $knowledge_class_basic));
                        foreach ($knowledgeClassesBasic as $class) {
                            if (!empty($class)) {
                                echo '<span style="display: inline-block; background: rgba(242, 124, 17, 0.15); color: rgb(242, 124, 17); padding: 3px 8px; margin: 2px; border-radius: 4px; font-size: 0.85em; font-weight: 400;">' . htmlspecialchars($class) . '</span>';
                            }
                        }
                    } else {
                        echo '<span style="color: #888; font-style: italic;">Everyone</span>';
                    }
                    echo '</td>';
                    
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
            ?>
        </div>
    </div>

    <!-- Dynamic Oghma Tab -->
    <div id="dynamic-tab" class="tab-content">
        <div class="content-grid">
            <div class="content-section">
                <h2>Batch Upload</h2>
                <form action="" method="post" enctype="multipart/form-data">
                    <div>
                        <label for="dynamic_csv_file">Select .csv file to upload dynamic entries:</label>
                        <br>
                        <input type="file" name="dynamic_csv_file" id="dynamic_csv_file" accept=".csv" required>
                    </div>
                    <div class="button-group">
                        <input type="submit" name="submit_dynamic_csv" value="Upload CSV" class="action-button upload-csv">
                        <a href="../data/oghma_dynamic_example.csv" class="action-button download-csv">Download Example CSV</a>
                    </div>
                </form>
                <p>You can verify that the entries have been uploaded successfully by navigating to <br><b>Server Actions -> Database Manager -> dwemer -> public -> oghma_dynamic</b></p>
                <p>You see what quests CHIM have detected by navigating to <br><b>Server Actions -> Database Manager -> dwemer -> public -> questlog</b></p>
                <p>All uploaded entries will be saved into the <code>oghma_dynamic</code> table.</p>
            </div>

            <div class="content-section">
                <h2>Database Management</h2>
                <p>Verify uploads: <br><b>Server Actions → Database Manager → dwemer → public → oghma_dynamic</b></p>
                <p>View conversation usage: <br><b>Server Actions → Database Manager → dwemer → public → audit_memory</b></p>
                
                <div class="button-group" style="margin-top: 20px;">
                    <form action="" method="post" style="display: inline;">
                        <input type="hidden" name="action" value="delete_all_dynamic">
                        <input type="submit" class="btn-danger" value="Delete All Dynamic Entries" onclick="return confirm('Are you sure you want to delete ALL dynamic entries? This cannot be undone!');">
                    </form>
                </div>
            </div>
        </div>
        <div class="full-width-section">
            <h2 id="dynamic">📋 Dynamic Oghma Entries</h2>
            
            <div class="action-container">
                <button onclick="openNewDynamicEntryModal()" class="action-button add-new">Add New Dynamic Entry</button>
            </div>
            
            <?php
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
            ?>
            
            <div class="filter-section">
                <div>
                    <strong>Filter by Category:</strong><br>
                    <div class="filter-buttons" style="margin-top: 10px;">
                        <a class="alphabet-button" href="?#dynamic">All Categories</a>
                        <?php
                        foreach ($dynamicCategories as $cat) {
                            $catEncoded = urlencode($cat);
                            $style = ($selectedDynamicCategory === $cat) ? 'style="background-color:#0056b3;"' : '';
                            echo "<a class=\"alphabet-button\" $style href=\"?dynamic_cat=$catEncoded#dynamic\">" . htmlspecialchars($cat) . "</a>";
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <?php

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
            ?>
        </div>
    </div>

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

// Tab switching functionality
function switchTab(tabId) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all tab buttons
    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(button => {
        button.classList.remove('active');
    });
    
    // Show selected tab content
    const selectedTab = document.getElementById(tabId);
    if (selectedTab) {
        selectedTab.classList.add('active');
    }
    
    // Add active class to clicked button
    const clickedButton = event.target;
    clickedButton.classList.add('active');
    
    // Update header content based on active tab
    updateHeaderContent(tabId);
    
    // Store active tab in localStorage
    localStorage.setItem('activeOghmaTab', tabId);
}

// Function to update header content based on tab
function updateHeaderContent(tabId) {
    const titleText = document.getElementById('title-text');
    const oghmaContent = document.getElementById('oghma-header-content');
    const dynamicContent = document.getElementById('dynamic-header-content');
    
    // Fade out current content
    oghmaContent.style.opacity = '0';
    dynamicContent.style.opacity = '0';
    
    setTimeout(() => {
        if (tabId === 'dynamic-tab') {
            // Switch to Dynamic Oghma
            titleText.textContent = 'Dynamic Oghma';
            oghmaContent.style.display = 'none';
            dynamicContent.style.display = 'block';
            
            // Fade in new content
            setTimeout(() => {
                dynamicContent.style.opacity = '1';
            }, 50);
        } else {
            // Switch to regular Oghma
            titleText.textContent = 'Oghma Infinium';
            oghmaContent.style.display = 'block';
            dynamicContent.style.display = 'none';
            
            // Fade in new content
            setTimeout(() => {
                oghmaContent.style.opacity = '1';
            }, 50);
        }
    }, 150);
}

// Restore active tab on page load
document.addEventListener('DOMContentLoaded', function() {
    const savedTab = localStorage.getItem('activeOghmaTab');
    if (savedTab) {
        // Manually switch to saved tab
        switchTabDirectly(savedTab);
    } else {
        // Default to oghma tab
        updateHeaderContent('oghma-tab');
    }
});

// Function to switch tab without event dependency
function switchTabDirectly(tabId) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all tab buttons
    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(button => {
        button.classList.remove('active');
    });
    
    // Show selected tab content
    const selectedTab = document.getElementById(tabId);
    if (selectedTab) {
        selectedTab.classList.add('active');
    }
    
    // Find and activate the corresponding button
    const buttons = document.querySelectorAll('.tab-button');
    buttons.forEach(button => {
        if (button.getAttribute('onclick') && button.getAttribute('onclick').includes(tabId)) {
            button.classList.add('active');
        }
    });
    
    // Update header content
    updateHeaderContent(tabId);
}

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
