<?php
// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

$TITLE = "📝CHIM - NPC Biography Management";

ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");

$debugPaneLink = false;
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/navbar.php");

// Enable error reporting (for development purposes)
error_reporting(E_ALL);
ini_set('display_errors', '1');

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
    
    // New extended profile fields
    $npc_background    = (!empty($_POST['npc_background']))    ? trim($_POST['npc_background'])    : null;
    $npc_personality   = (!empty($_POST['npc_personality']))   ? trim($_POST['npc_personality'])   : null;
    $npc_appearance    = (!empty($_POST['npc_appearance']))    ? trim($_POST['npc_appearance'])    : null;
    $npc_relationships = (!empty($_POST['npc_relationships'])) ? trim($_POST['npc_relationships']) : null;
    $npc_occupation    = (!empty($_POST['npc_occupation']))    ? trim($_POST['npc_occupation'])    : null;
    $npc_skills        = (!empty($_POST['npc_skills']))        ? trim($_POST['npc_skills'])        : null;
    $npc_speechstyle   = (!empty($_POST['npc_speechstyle']))   ? trim($_POST['npc_speechstyle'])   : null;
    $npc_quest         = (!empty($_POST['npc_quest']))         ? trim($_POST['npc_quest'])         : null;
    $npc_goals         = (!empty($_POST['npc_goals']))         ? trim($_POST['npc_goals'])         : null;

    if (!empty($npc_name) && !empty($npc_pers)) {
        $query = "
            INSERT INTO {$schema}.npc_templates_custom
                (npc_name, npc_dynamic, npc_pers, npc_misc, melotts_voiceid, xtts_voiceid, xvasynth_voiceid,
                 npc_background, npc_personality, npc_appearance, npc_relationships, npc_occupation, npc_skills, npc_speechstyle, npc_quest, npc_goals)
            VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16)
            ON CONFLICT (npc_name)
            DO UPDATE SET
                npc_dynamic = EXCLUDED.npc_dynamic,
                npc_pers = EXCLUDED.npc_pers,
                npc_misc = EXCLUDED.npc_misc,
                melotts_voiceid = EXCLUDED.melotts_voiceid,
                xtts_voiceid = EXCLUDED.xtts_voiceid,
                xvasynth_voiceid = EXCLUDED.xvasynth_voiceid,
                npc_background = EXCLUDED.npc_background,
                npc_personality = EXCLUDED.npc_personality,
                npc_appearance = EXCLUDED.npc_appearance,
                npc_relationships = EXCLUDED.npc_relationships,
                npc_occupation = EXCLUDED.npc_occupation,
                npc_skills = EXCLUDED.npc_skills,
                npc_speechstyle = EXCLUDED.npc_speechstyle,
                npc_quest = EXCLUDED.npc_quest,
                npc_goals = EXCLUDED.npc_goals
        ";

        $params = [
            $npc_name,
            $npc_dynamic,
            $npc_pers,
            $npc_misc,
            $melotts_voiceid,
            $xtts_voiceid,
            $xvasynth_voiceid,
            $npc_background,
            $npc_personality,
            $npc_appearance,
            $npc_relationships,
            $npc_occupation,
            $npc_skills,
            $npc_speechstyle,
            $npc_quest,
            $npc_goals
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
                    // * Extended profile fields (all optional):
                    //   - npc_background, npc_personality, npc_appearance, npc_relationships
                    //   - npc_occupation, npc_skills, npc_speechstyle, npc_quest
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

                        // New extended profile fields
                        $npc_background = null;
                        if (isset($headerMap['npc_background']) && isset($data[$headerMap['npc_background']])) {
                            $temp = trim($data[$headerMap['npc_background']]);
                            $npc_background = ($temp !== '') ? $temp : null;
                        }

                        $npc_personality = null;
                        if (isset($headerMap['npc_personality']) && isset($data[$headerMap['npc_personality']])) {
                            $temp = trim($data[$headerMap['npc_personality']]);
                            $npc_personality = ($temp !== '') ? $temp : null;
                        }

                        $npc_appearance = null;
                        if (isset($headerMap['npc_appearance']) && isset($data[$headerMap['npc_appearance']])) {
                            $temp = trim($data[$headerMap['npc_appearance']]);
                            $npc_appearance = ($temp !== '') ? $temp : null;
                        }

                        $npc_relationships = null;
                        if (isset($headerMap['npc_relationships']) && isset($data[$headerMap['npc_relationships']])) {
                            $temp = trim($data[$headerMap['npc_relationships']]);
                            $npc_relationships = ($temp !== '') ? $temp : null;
                        }

                        $npc_occupation = null;
                        if (isset($headerMap['npc_occupation']) && isset($data[$headerMap['npc_occupation']])) {
                            $temp = trim($data[$headerMap['npc_occupation']]);
                            $npc_occupation = ($temp !== '') ? $temp : null;
                        }

                        $npc_skills = null;
                        if (isset($headerMap['npc_skills']) && isset($data[$headerMap['npc_skills']])) {
                            $temp = trim($data[$headerMap['npc_skills']]);
                            $npc_skills = ($temp !== '') ? $temp : null;
                        }

                        $npc_speechstyle = null;
                        if (isset($headerMap['npc_speechstyle']) && isset($data[$headerMap['npc_speechstyle']])) {
                            $temp = trim($data[$headerMap['npc_speechstyle']]);
                            $npc_speechstyle = ($temp !== '') ? $temp : null;
                        }

                        $npc_quest = null;
                        if (isset($headerMap['npc_quest']) && isset($data[$headerMap['npc_quest']])) {
                            $temp = trim($data[$headerMap['npc_quest']]);
                            $npc_quest = ($temp !== '') ? $temp : null;
                        }

                        $npc_goals = null;
                        if (isset($headerMap['npc_goals']) && isset($data[$headerMap['npc_goals']])) {
                            $temp = trim($data[$headerMap['npc_goals']]);
                            $npc_goals = ($temp !== '') ? $temp : null;
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
                            $npc_background     = ($npc_background !== null)
                                                    ? iconv('Windows-1252', 'UTF-8//IGNORE', $npc_background)
                                                    : null;
                            $npc_personality    = ($npc_personality !== null)
                                                    ? iconv('Windows-1252', 'UTF-8//IGNORE', $npc_personality)
                                                    : null;
                            $npc_appearance     = ($npc_appearance !== null)
                                                    ? iconv('Windows-1252', 'UTF-8//IGNORE', $npc_appearance)
                                                    : null;
                            $npc_relationships  = ($npc_relationships !== null)
                                                    ? iconv('Windows-1252', 'UTF-8//IGNORE', $npc_relationships)
                                                    : null;
                            $npc_occupation     = ($npc_occupation !== null)
                                                    ? iconv('Windows-1252', 'UTF-8//IGNORE', $npc_occupation)
                                                    : null;
                            $npc_skills         = ($npc_skills !== null)
                                                    ? iconv('Windows-1252', 'UTF-8//IGNORE', $npc_skills)
                                                    : null;
                            $npc_speechstyle    = ($npc_speechstyle !== null)
                                                    ? iconv('Windows-1252', 'UTF-8//IGNORE', $npc_speechstyle)
                                                    : null;
                            $npc_quest          = ($npc_quest !== null)
                                                    ? iconv('Windows-1252', 'UTF-8//IGNORE', $npc_quest)
                                                    : null;
                            $npc_goals          = ($npc_goals !== null)
                                                    ? iconv('Windows-1252', 'UTF-8//IGNORE', $npc_goals)
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
                                 melotts_voiceid, xtts_voiceid, xvasynth_voiceid,
                                 npc_background, npc_personality, npc_appearance, npc_relationships, npc_occupation, npc_skills, npc_speechstyle, npc_quest, npc_goals)
                            VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16)
                            ON CONFLICT (npc_name)
                            DO UPDATE SET
                                npc_dynamic       = EXCLUDED.npc_dynamic,
                                npc_pers          = EXCLUDED.npc_pers,
                                npc_misc          = EXCLUDED.npc_misc,
                                melotts_voiceid   = EXCLUDED.melotts_voiceid,
                                xtts_voiceid      = EXCLUDED.xtts_voiceid,
                                xvasynth_voiceid  = EXCLUDED.xvasynth_voiceid,
                                npc_background    = EXCLUDED.npc_background,
                                npc_personality   = EXCLUDED.npc_personality,
                                npc_appearance    = EXCLUDED.npc_appearance,
                                npc_relationships = EXCLUDED.npc_relationships,
                                npc_occupation    = EXCLUDED.npc_occupation,
                                npc_skills        = EXCLUDED.npc_skills,
                                npc_speechstyle   = EXCLUDED.npc_speechstyle,
                                npc_quest         = EXCLUDED.npc_quest,
                                npc_goals         = EXCLUDED.npc_goals
                        ";

                        $params = [
                            $npc_name,
                            $npc_dynamic,
                            $npc_pers,
                            $npc_misc,
                            $melotts_voiceid,
                            $xtts_voiceid,
                            $xvasynth_voiceid,
                            $npc_background,
                            $npc_personality,
                            $npc_appearance,
                            $npc_relationships,
                            $npc_occupation,
                            $npc_skills,
                            $npc_speechstyle,
                            $npc_quest,
                            $npc_goals
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
        header('Content-Disposition: attachment; filename="example_bios.csv"');
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
    
    // New extended profile fields
    $npc_background    = (!empty($_POST['npc_background']))    ? trim($_POST['npc_background'])    : null;
    $npc_personality   = (!empty($_POST['npc_personality']))   ? trim($_POST['npc_personality'])   : null;
    $npc_appearance    = (!empty($_POST['npc_appearance']))    ? trim($_POST['npc_appearance'])    : null;
    $npc_relationships = (!empty($_POST['npc_relationships'])) ? trim($_POST['npc_relationships']) : null;
    $npc_occupation    = (!empty($_POST['npc_occupation']))    ? trim($_POST['npc_occupation'])    : null;
    $npc_skills        = (!empty($_POST['npc_skills']))        ? trim($_POST['npc_skills'])        : null;
    $npc_speechstyle   = (!empty($_POST['npc_speechstyle']))   ? trim($_POST['npc_speechstyle'])   : null;
    $npc_quest         = (!empty($_POST['npc_quest']))         ? trim($_POST['npc_quest'])         : null;
    $npc_goals         = (!empty($_POST['npc_goals']))         ? trim($_POST['npc_goals'])         : null;

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
                xvasynth_voiceid = $7,
                npc_background = $8,
                npc_personality = $9,
                npc_appearance = $10,
                npc_relationships = $11,
                npc_occupation = $12,
                npc_skills = $13,
                npc_speechstyle = $14,
                npc_quest = $15,
                npc_goals = $16
            WHERE npc_name = $17
        ";

        $params = [
            $npc_name,
            $npc_pers,
            $npc_dynamic,
            $npc_misc,
            $melotts_voiceid,
            $xtts_voiceid,
            $xvasynth_voiceid,
            $npc_background,
            $npc_personality,
            $npc_appearance,
            $npc_relationships,
            $npc_occupation,
            $npc_skills,
            $npc_speechstyle,
            $npc_quest,
            $npc_goals,
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
        <h1>📝NPC Biography Management</h1>
        <h3><strong>Make sure that all names with spaces are replaced with underscores _ and all names are lowercase!</strong></h3>
        <h4>Example: Mjoll the Lioness becomes mjoll_the_lioness</h4>

        <div id="toast" class="toast-notification">
            <span class="message"></span>
        </div>
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
                <p>You can verify that NPC data has been uploaded successfully by going to 
                <b>Server Actions -> Database Manager -> dwemer -> public -> npc_templates_custom</b>.</p>
                <p>All uploaded biographies will be saved into the <code>npc_templates_custom</code> table. This overwrites any entries in the regular table.</p>
                <p>Also you can check the merged table at 
                <b>Server Actions -> Database Manager -> dwemer -> public -> Views (Top bar) -> combined_npc_templates</b>.</p>
            </form>
            <form action="" method="post">
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
            <a href="https://discord.gg/NDn9qud2ug" target="_blank" rel="noopener">
                csv files channel in our discord
            </a>.
            </p>
        </div>
    </div>

    <br>
    <?php
    $letter = isset($_GET['letter']) ? strtoupper($_GET['letter']) : '';
    $searchTerm = isset($_GET['search']) ? strtolower(trim($_GET['search'])) : '';

    // Build query based on filters
    if (!empty($letter) && ctype_alpha($letter) && strlen($letter) === 1) {
        if (!empty($searchTerm)) {
            // Filter by both letter and search term
            $query_combined = "
                SELECT *
                FROM {$schema}.combined_npc_templates
                WHERE LOWER(npc_name) LIKE LOWER($1) 
                AND LOWER(npc_name) LIKE LOWER($2)
                ORDER BY npc_name ASC
            ";
            $params_combined = [$letter . '%', '%' . $searchTerm . '%'];
        } else {
            // Filter by letter only
            $query_combined = "
                SELECT *
                FROM {$schema}.combined_npc_templates
                WHERE LOWER(npc_name) LIKE LOWER($1)
                ORDER BY npc_name ASC
            ";
            $params_combined = [$letter . '%'];
        }
    } else {
        if (!empty($searchTerm)) {
            // Filter by search term only
            $query_combined = "
                SELECT *
                FROM {$schema}.combined_npc_templates
                WHERE LOWER(npc_name) LIKE LOWER($1)
                ORDER BY npc_name ASC
            ";
            $params_combined = ['%' . $searchTerm . '%'];
        } else {
            // No filters
            $query_combined = "
                SELECT *
                FROM {$schema}.combined_npc_templates
                ORDER BY npc_name ASC
            ";
            $params_combined = [];
        }
    }

    $result_combined = !empty($params_combined) 
        ? pg_query_params($conn, $query_combined, $params_combined)
        : pg_query($conn, $query_combined);

    echo '<br>';
    // Wrap the NPC Templates Database section in a div for indentation
    echo '<div class="indent5" id="table">';
    echo '<h1>NPC Templates Database</h1>';
    echo '<div class="action-container">';
    echo '<button onclick="openNewEntryModal()" class="action-button add-new">Add New Entry</button>';
    echo '<div class="search-container">';
    echo '<input type="text" id="searchBox" placeholder="Search NPC names..." style="flex-grow: 1; padding: 8px; border-radius: 4px; border: 1px solid #555555; background-color: #4a4a4a; color: #f8f9fa;">';
    echo '<button onclick="applySearch()" class="action-button edit">Search</button>';
    echo '</div>';
    echo '</div>';
    echo '<h3>Note: This is just for editing an NPC entry before they are activated ingame. Any further edits should be done in the configuration wizard.</h3>';
    echo '<p>You can not delete an NPC entry. You can simply make another one with the correct name if you make a mistake.</p>';

    echo '<br>';

    // Alphabetic filter
    echo '<div class="filter-buttons">';
    echo '<a href="?#table" class="alphabet-button">All</a>';
    foreach (range('A', 'Z') as $char) {
        echo '<a href="?letter=' . $char . '#table" class="alphabet-button">' . $char . '</a>';
    }
    echo '</div>';

    if ($result_combined) {
        echo '<div id="npc-table-container" class="table-container">';
        echo '<table>';
        echo '<tr>';
        echo '  <th>Name</th>';
        echo '  <th>Summary Bio</th>';
        echo '  <th>Extended Profiles</th>';
        echo '  <th>Voice Overrides</th>';
        echo '  <th>Actions</th>';
        echo '</tr>';

        $rowCountCombined = 0;
        while ($row = pg_fetch_assoc($result_combined)) {
            echo '<tr>';
            echo '  <td>' . htmlspecialchars($row['npc_name'] ?? '') . '</td>';
            echo '  <td style="max-width: 250px; word-wrap: break-word;">' . nl2br(htmlspecialchars(substr($row['npc_pers'] ?? '', 0, 200))) . (strlen($row['npc_pers'] ?? '') > 200 ? '...' : '') . '</td>';
            
            // Extended Profile summary
            $extendedFields = [
                'Background' => $row['npc_background'] ?? '',
                'Personality' => $row['npc_personality'] ?? '',
                'Appearance' => $row['npc_appearance'] ?? '',
                'Relationships' => $row['npc_relationships'] ?? '',
                'Occupation' => $row['npc_occupation'] ?? '',
                'Skills' => $row['npc_skills'] ?? '',
                'Speech Style' => $row['npc_speechstyle'] ?? '',
                'Goals' => $row['npc_goals'] ?? '',
                'Quests' => $row['npc_quest'] ?? ''
            ];
            $extendedCount = count(array_filter($extendedFields));
            echo '  <td style="cursor: pointer; color: #4a9eff;" onclick="showExtendedProfile(\'' . 
                htmlspecialchars($row['npc_name'], ENT_QUOTES) . '\', ' . 
                htmlspecialchars(json_encode($extendedFields), ENT_QUOTES) . ')">';
            echo '<span style="color: #888; font-size: 0.9em;">' . $extendedCount . ' of 9 fields completed</span>';
            echo '<br><small style="color: #4a9eff; font-size: 0.8em;">Click to view details</small>';
            echo '</td>';
            
            // Voice Overrides summary
            $voiceFields = [
                'MeloTTS' => $row['melotts_voiceid'] ?? '',
                'XTTS' => $row['xtts_voiceid'] ?? '',
                'xVASynth' => $row['xvasynth_voiceid'] ?? ''
            ];
            echo '  <td style="font-size: 0.85em; line-height: 1.4;">';
            foreach ($voiceFields as $type => $voice) {
                $displayValue = !empty(trim($voice)) ? htmlspecialchars($voice) : '<span style="color: #888; font-style: italic;">None</span>';
                echo '<div style="margin-bottom: 2px;"><strong>' . $type . ':</strong> ' . $displayValue . '</div>';
            }
            echo '</td>';
            
            // Add Edit button
            echo '<td>';
            echo '<div class="button-group">';
            $jsData = [
                'npc_name' => $row['npc_name'],
                'npc_pers' => $row['npc_pers'],
                'npc_dynamic' => $row['npc_dynamic'] ?? '',
                'npc_misc' => $row['npc_misc'] ?? '',
                'melotts_voiceid' => $row['melotts_voiceid'] ?? '',
                'xtts_voiceid' => $row['xtts_voiceid'] ?? '',
                'xvasynth_voiceid' => $row['xvasynth_voiceid'] ?? '',
                'npc_background' => $row['npc_background'] ?? '',
                'npc_personality' => $row['npc_personality'] ?? '',
                'npc_appearance' => $row['npc_appearance'] ?? '',
                'npc_relationships' => $row['npc_relationships'] ?? '',
                'npc_occupation' => $row['npc_occupation'] ?? '',
                'npc_skills' => $row['npc_skills'] ?? '',
                'npc_speechstyle' => $row['npc_speechstyle'] ?? '',
                'npc_quest' => $row['npc_quest'] ?? '',
                'npc_goals' => $row['npc_goals'] ?? ''
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
            echo '<p>No NPCs found.</p>';
        }
    } else {
        echo '<p>Error fetching combined NPC templates: ' . pg_last_error($conn) . '</p>';
    }

    echo '</div>';
    ?>
</main>

<div id="editModal" class="modal-backdrop" style="display: none;">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">Edit NPC Entry</h2>
        </div>
        <div class="modal-body">
            <form action="<?php echo $formAction; ?>" method="post">
                <input type="hidden" name="action" value="update_single">
                <input type="hidden" name="npc_name_original" id="edit_npc_name_original">

                <label for="edit_npc_name">NPC Name:</label>
                <small>NPC names cannot be changed after creation. If you need to change a name, create a new entry.</small>
                <input type="text" name="npc_name" id="edit_npc_name" readonly style="background-color: #2a2a2a; cursor: not-allowed;" required>

                <label for="edit_npc_pers">NPC Static Bio:</label>
                <small>Static traits and background of the NPC.</small>
                <textarea name="npc_pers" id="edit_npc_pers" rows="8" required></textarea>

                <label for="edit_npc_dynamic">NPC Dynamic Bio:</label>
                <small>Optional: Dynamic personality traits.</small>
                <textarea name="npc_dynamic" id="edit_npc_dynamic" rows="8"></textarea>

                <label for="edit_npc_misc">NPC Misc:</label>
                <small>Optional: Oghma Knowledge Tags. Make sure to seperate with commas. <a href="https://dwemerdynamics.hostwiki.io/en/Oghma-Infinium-(RAG)" target="_blank" rel="noopener">Read more here!</a></small>
                <input type="text" name="npc_misc" id="edit_npc_misc">

                <!-- Extended Profile Fields -->
                <h3 style="color: rgb(242, 124, 17); margin-top: 25px; margin-bottom: 15px; border-bottom: 1px solid #444;">Extended Profile</h3>
                
                <label for="edit_npc_background">Background:</label>
                <small>Detailed history, origins, and past experiences that shaped this character.</small>
                <textarea name="npc_background" id="edit_npc_background" rows="4"></textarea>

                <label for="edit_npc_personality">Personality:</label>
                <small>Detailed character traits, behavioral patterns, and psychological characteristics.</small>
                <textarea name="npc_personality" id="edit_npc_personality" rows="4"></textarea>

                <label for="edit_npc_appearance">Appearance:</label>
                <small>Detailed description of physical features, clothing, and distinguishing characteristics.</small>
                <textarea name="npc_appearance" id="edit_npc_appearance" rows="4"></textarea>

                <label for="edit_npc_relationships">Relationships:</label>
                <small>Important relationships with other characters, family, friends, enemies, and social connections.</small>
                <textarea name="npc_relationships" id="edit_npc_relationships" rows="4"></textarea>

                <label for="edit_npc_occupation">Occupation & Role:</label>
                <small>Current job, profession, duties, and position in society or organizations.</small>
                <textarea name="npc_occupation" id="edit_npc_occupation" rows="3"></textarea>

                <label for="edit_npc_skills">Skills & Abilities:</label>
                <small>Special talents, combat abilities, magical knowledge, and areas of expertise.</small>
                <textarea name="npc_skills" id="edit_npc_skills" rows="3"></textarea>

                <label for="edit_npc_speechstyle">Speech Style:</label>
                <small>How this character speaks, including vocabulary, accent, mannerisms, and communication patterns.</small>
                <textarea name="npc_speechstyle" id="edit_npc_speechstyle" rows="3"></textarea>

                <label for="edit_npc_goals">Goals & Aspirations:</label>
                <small>Long-term objectives, personal ambitions, and life goals that drive this character.</small>
                <textarea name="npc_goals" id="edit_npc_goals" rows="3"></textarea>

                <label for="edit_npc_quest">Quest Information:</label>
                <small>Current quests, objectives, and story-related information for this character.</small>
                <textarea name="npc_quest" id="edit_npc_quest" rows="3"></textarea>

                <!-- Voice Overrides Section -->
                <h3 style="color: rgb(242, 124, 17); margin-top: 25px; margin-bottom: 15px; border-bottom: 1px solid #444;">Voice Overrides</h3>

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
                    <button type="submit" name="submit_individual" value="1" class="btn-save">Save Changes</button>
                    <button type="button" onclick="closeEditModal()" class="btn-base btn-cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="newEntryModal" class="modal-backdrop" style="display: none;">
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
                <small>Optional: Oghma Knowledge Tags. Make sure to seperate with commas. <a href="https://docs.google.com/spreadsheets/d/1dcfctU-iOqprwy2BOc7___4Awteczgdlv8886KalPsQ/edit?pli=1&gid=338893641#gid=338893641" target="_blank" rel="noopener">Read more here!</a></small>
                <input type="text" name="npc_misc" id="new_npc_misc">

                <!-- Extended Profile Fields -->
                <h3 style="color: rgb(242, 124, 17); margin-top: 25px; margin-bottom: 15px; border-bottom: 1px solid #444;">Extended Profile</h3>
                
                <label for="new_npc_background">Background:</label>
                <small>Detailed history, origins, and past experiences that shaped this character.</small>
                <textarea name="npc_background" id="new_npc_background" rows="4"></textarea>

                <label for="new_npc_personality">Personality:</label>
                <small>Detailed character traits, behavioral patterns, and psychological characteristics.</small>
                <textarea name="npc_personality" id="new_npc_personality" rows="4"></textarea>

                <label for="new_npc_appearance">Appearance:</label>
                <small>Detailed description of physical features, clothing, and distinguishing characteristics.</small>
                <textarea name="npc_appearance" id="new_npc_appearance" rows="4"></textarea>

                <label for="new_npc_relationships">Relationships:</label>
                <small>Important relationships with other characters, family, friends, enemies, and social connections.</small>
                <textarea name="npc_relationships" id="new_npc_relationships" rows="4"></textarea>

                <label for="new_npc_occupation">Occupation & Role:</label>
                <small>Current job, profession, duties, and position in society or organizations.</small>
                <textarea name="npc_occupation" id="new_npc_occupation" rows="3"></textarea>

                <label for="new_npc_skills">Skills & Abilities:</label>
                <small>Special talents, combat abilities, magical knowledge, and areas of expertise.</small>
                <textarea name="npc_skills" id="new_npc_skills" rows="3"></textarea>

                <label for="new_npc_speechstyle">Speech Style:</label>
                <small>How this character speaks, including vocabulary, accent, mannerisms, and communication patterns.</small>
                <textarea name="npc_speechstyle" id="new_npc_speechstyle" rows="3"></textarea>

                <label for="new_npc_goals">Goals & Aspirations:</label>
                <small>Long-term objectives, personal ambitions, and life goals that drive this character.</small>
                <textarea name="npc_goals" id="new_npc_goals" rows="3"></textarea>

                <label for="new_npc_quest">Quest Information:</label>
                <small>Current quests, objectives, and story-related information for this character.</small>
                <textarea name="npc_quest" id="new_npc_quest" rows="3"></textarea>

                <!-- Voice Overrides Section -->
                <h3 style="color: rgb(242, 124, 17); margin-top: 25px; margin-bottom: 15px; border-bottom: 1px solid #444;">Voice Overrides</h3>

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
                    <button type="submit" name="submit_individual" value="1" class="btn-save">Save</button>
                    <button type="button" onclick="closeNewEntryModal()" class="btn-base btn-cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Extended Profile View Modal -->
<div id="extendedProfileModal" class="modal-backdrop" style="display: none;">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">Extended Profiles: <span id="extended-profile-npc-name"></span></h2>
        </div>
        <div class="modal-body">
            <div class="extended-profile-grid" style="display: grid; gap: 20px;">
                
                <div class="profile-field">
                    <h4 style="color: rgb(242, 124, 17); margin: 0 0 8px 0; border-bottom: 1px solid #444; padding-bottom: 4px;">Background</h4>
                    <div id="profile-background" style="background: #2a2a2a; padding: 12px; border-radius: 4px; min-height: 40px; white-space: pre-wrap;"></div>
                </div>

                <div class="profile-field">
                    <h4 style="color: rgb(242, 124, 17); margin: 0 0 8px 0; border-bottom: 1px solid #444; padding-bottom: 4px;">Personality</h4>
                    <div id="profile-personality" style="background: #2a2a2a; padding: 12px; border-radius: 4px; min-height: 40px; white-space: pre-wrap;"></div>
                </div>

                <div class="profile-field">
                    <h4 style="color: rgb(242, 124, 17); margin: 0 0 8px 0; border-bottom: 1px solid #444; padding-bottom: 4px;">Appearance</h4>
                    <div id="profile-appearance" style="background: #2a2a2a; padding: 12px; border-radius: 4px; min-height: 40px; white-space: pre-wrap;"></div>
                </div>

                <div class="profile-field">
                    <h4 style="color: rgb(242, 124, 17); margin: 0 0 8px 0; border-bottom: 1px solid #444; padding-bottom: 4px;">Relationships</h4>
                    <div id="profile-relationships" style="background: #2a2a2a; padding: 12px; border-radius: 4px; min-height: 40px; white-space: pre-wrap;"></div>
                </div>

                <div class="profile-field">
                    <h4 style="color: rgb(242, 124, 17); margin: 0 0 8px 0; border-bottom: 1px solid #444; padding-bottom: 4px;">Occupation & Role</h4>
                    <div id="profile-occupation" style="background: #2a2a2a; padding: 12px; border-radius: 4px; min-height: 40px; white-space: pre-wrap;"></div>
                </div>

                <div class="profile-field">
                    <h4 style="color: rgb(242, 124, 17); margin: 0 0 8px 0; border-bottom: 1px solid #444; padding-bottom: 4px;">Skills & Abilities</h4>
                    <div id="profile-skills" style="background: #2a2a2a; padding: 12px; border-radius: 4px; min-height: 40px; white-space: pre-wrap;"></div>
                </div>

                <div class="profile-field">
                    <h4 style="color: rgb(242, 124, 17); margin: 0 0 8px 0; border-bottom: 1px solid #444; padding-bottom: 4px;">Speech Style</h4>
                    <div id="profile-speechstyle" style="background: #2a2a2a; padding: 12px; border-radius: 4px; min-height: 40px; white-space: pre-wrap;"></div>
                </div>

                <div class="profile-field">
                    <h4 style="color: rgb(242, 124, 17); margin: 0 0 8px 0; border-bottom: 1px solid #444; padding-bottom: 4px;">Goals & Aspirations</h4>
                    <div id="profile-goals" style="background: #2a2a2a; padding: 12px; border-radius: 4px; min-height: 40px; white-space: pre-wrap;"></div>
                </div>

                <div class="profile-field">
                    <h4 style="color: rgb(242, 124, 17); margin: 0 0 8px 0; border-bottom: 1px solid #444; padding-bottom: 4px;">Quest Information</h4>
                    <div id="profile-quest" style="background: #2a2a2a; padding: 12px; border-radius: 4px; min-height: 40px; white-space: pre-wrap;"></div>
                </div>

            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="closeExtendedProfileModal()" class="btn-base btn-cancel">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function showToast(message, duration = 5000) {
    const toast = document.getElementById('toast');
    const messageSpan = toast.querySelector('.message');
    messageSpan.textContent = message;
    toast.classList.add('show');
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, duration);
}

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
        
        // Extended profile fields
        document.getElementById("edit_npc_background").value = decodeHTML(data.npc_background || '');
        document.getElementById("edit_npc_personality").value = decodeHTML(data.npc_personality || '');
        document.getElementById("edit_npc_appearance").value = decodeHTML(data.npc_appearance || '');
        document.getElementById("edit_npc_relationships").value = decodeHTML(data.npc_relationships || '');
        document.getElementById("edit_npc_occupation").value = decodeHTML(data.npc_occupation || '');
        document.getElementById("edit_npc_skills").value = decodeHTML(data.npc_skills || '');
        document.getElementById("edit_npc_speechstyle").value = decodeHTML(data.npc_speechstyle || '');
        document.getElementById("edit_npc_quest").value = decodeHTML(data.npc_quest || '');
        document.getElementById("edit_npc_goals").value = decodeHTML(data.npc_goals || '');
        
        // Voice overrides
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

function openNewEntryModal() {
    document.getElementById("newEntryModal").style.display = "block";
    document.body.style.overflow = "hidden";
}

function closeNewEntryModal() {
    document.getElementById("newEntryModal").style.display = "none";
    document.body.style.overflow = "auto";
}

function showExtendedProfile(npcName, profileData) {
    try {
        // Set the NPC name in the modal title
        document.getElementById("extended-profile-npc-name").textContent = npcName;
        
        // Populate each field, showing "Not specified" for empty fields
        const fields = {
            'Background': 'profile-background',
            'Personality': 'profile-personality', 
            'Appearance': 'profile-appearance',
            'Relationships': 'profile-relationships',
            'Occupation': 'profile-occupation',
            'Skills': 'profile-skills',
            'Speech Style': 'profile-speechstyle',
            'Goals': 'profile-goals',
            'Quests': 'profile-quest'
        };
        
        Object.keys(fields).forEach(fieldName => {
            const elementId = fields[fieldName];
            const content = profileData[fieldName] || '';
            const element = document.getElementById(elementId);
            
            if (content.trim()) {
                element.textContent = content;
                element.style.color = '#d4d4d4';
                element.style.fontStyle = 'normal';
            } else {
                element.textContent = 'Not specified';
                element.style.color = '#888';
                element.style.fontStyle = 'italic';
            }
        });
        
        // Show the modal
        document.getElementById("extendedProfileModal").style.display = "block";
        document.body.style.overflow = "hidden";
    } catch (error) {
        console.error("Error in showExtendedProfile:", error);
        alert("There was an error displaying the extended profile. Please try again.");
    }
}

function closeExtendedProfileModal() {
    document.getElementById("extendedProfileModal").style.display = "none";
    document.body.style.overflow = "auto";
}

// Update PHP message handling
<?php if (!empty($message)): ?>
document.addEventListener('DOMContentLoaded', function() {
    showToast(<?php echo json_encode(strip_tags($message)); ?>);
});
<?php endif; ?>

// Add new AJAX filtering function
function filterByLetter(letter) {
    fetch(`npc_table.php?letter=${letter}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('npc-table-container').innerHTML = html;
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error loading data. Please try again.');
        });
}

// Replace the existing applySearch function with this updated version
function applySearch() {
    const searchTerm = document.getElementById("searchBox").value.trim();
    const currentUrl = new URL(window.location.href);
    const urlParams = new URLSearchParams(currentUrl.search);
    
    // Update or add search parameter
    if (searchTerm) {
        urlParams.set("search", searchTerm);
    } else {
        urlParams.delete("search");
    }
    
    // Preserve existing parameters if they exist
    const currentLetter = urlParams.get("letter");
    if (currentLetter) {
        urlParams.set("letter", currentLetter);
    }
    
    // Create the new URL with the base path and updated parameters
    const newUrl = `${window.location.pathname}?${urlParams.toString()}#table`;
    window.location.href = newUrl;
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
        document.getElementById("searchBox").value = decodeURIComponent(searchTerm);
    }
});

// Close modals when clicking outside of them
window.onclick = function(event) {
    const editModal = document.getElementById('editModal');
    const newEntryModal = document.getElementById('newEntryModal');
    const extendedProfileModal = document.getElementById('extendedProfileModal');
    
    if (event.target == editModal) {
        closeEditModal();
    } else if (event.target == newEntryModal) {
        closeNewEntryModal();
    } else if (event.target == extendedProfileModal) {
        closeExtendedProfileModal();
    }
}
</script>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>