<?php
// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

$TITLE = "📜 CHIM - Descriptions";

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

// Connect to the database
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");

if (!$conn) {
    die("Failed to connect to database: " . pg_last_error());
}

//
// ────────────────────────────────────────────────────────────────────
//   HANDLE EXPORTS BEFORE ANY HTML OUTPUT
// ────────────────────────────────────────────────────────────────────
//

// EXPORT CUSTOM DESCRIPTIONS
if (isset($_GET['action']) && $_GET['action'] == 'export_custom_items') {
    $export_query = "SELECT plugin, baseid, name, description FROM {$schema}.descriptions_custom ORDER BY plugin ASC, baseid ASC";
    $export_result = pg_query($conn, $export_query);

    if ($export_result) {
        $filename = 'custom_descriptions_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, array('plugin', 'baseid', 'name', 'description'));
        
        while ($row = pg_fetch_assoc($export_result)) {
            fputcsv($output, array(
                $row['plugin'],
                $row['baseid'],
                $row['name'],
                $row['description']
            ));
        }
        
        fclose($output);
        exit;
    }
}

// DOWNLOAD EXAMPLE CSV
if (isset($_GET['action']) && $_GET['action'] == 'download_example') {
    $filename = 'example_descriptions.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, array('plugin', 'baseid', 'name', 'description'));
    
    // Weapons
    fputcsv($output, array('Skyrim.esm', '0001397E', 'Iron Sword', 'A standard iron sword with a worn leather grip.'));
    fputcsv($output, array('Skyrim.esm', '00013948', 'Steel Dagger', 'A sharp steel dagger with a simple crossguard.'));
    fputcsv($output, array('Skyrim.esm', '00013989', 'Iron War Axe', 'A crude but effective one-handed axe with a chipped blade.'));
    
    // Armor
    fputcsv($output, array('Skyrim.esm', '00013ED8', 'Iron Armor', 'Heavy iron cuirass with simple steel rivets and worn leather straps.'));
    fputcsv($output, array('Skyrim.esm', '00012E4D', 'Iron Helmet', 'A basic iron helmet with minimal decoration and a T-shaped face guard.'));
    fputcsv($output, array('Skyrim.esm', '0003619E', 'Leather Armor', 'Light armor made from tanned leather with reinforced shoulders.'));
    fputcsv($output, array('Skyrim.esm', '00013920', 'Leather Boots', 'Sturdy leather boots with reinforced soles for travel.'));
    fputcsv($output, array('Skyrim.esm', '000D7A8C', 'Hide Shield', 'A round wooden shield covered in thick animal hide.'));
    
    // Household Objects
    fputcsv($output, array('Skyrim.esm', '00064B3F', 'Apple', 'A fresh red apple with a crisp texture.'));
    fputcsv($output, array('Skyrim.esm', '00064B41', 'Bread', 'A round loaf of crusty bread, still slightly warm.'));
    fputcsv($output, array('Skyrim.esm', '00034CDF', 'Ale', 'A clay bottle of Nord ale with a strong, bitter smell.'));
    fputcsv($output, array('Skyrim.esm', '0003365B', 'Woodcutter\'s Axe', 'A simple iron axe designed for chopping wood, not combat.'));
    fputcsv($output, array('Skyrim.esm', '000211E6', 'Wooden Plate', 'A plain wooden plate, worn smooth from years of use.'));
    fputcsv($output, array('Skyrim.esm', '00032A07', 'Goblet', 'An ornate silver goblet with intricate engravings.'));
    
    fclose($output);
    exit;
}

// Initialize message variable
$message = '';

// NOW start output buffering for HTML
ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");
$isEmbed = (isset($_GET['embed']) && $_GET['embed'] == '1');

$debugPaneLink = false;

function normalizeDescriptionManagerBaseId($baseid): string
{
    $baseid = trim((string) $baseid);
    if (preg_match('/^(XX[0-9A-Fa-f]{6}|FEXXX[0-9A-Fa-f]{3}|[0-9A-Fa-f]{8})$/', $baseid)) {
        return strtoupper($baseid);
    }

    return $baseid;
}

//
// ────────────────────────────────────────────────────────────────────
//   INDIVIDUAL UPLOAD
// ────────────────────────────────────────────────────────────────────
//
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_individual'])) {
    $plugin = trim($_POST['plugin'] ?? '');
    $baseid = trim($_POST['baseid'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!empty($baseid)) {
        $baseid = normalizeDescriptionManagerBaseId($baseid);
        // Truncate baseid to 128 characters
        if (strlen($baseid) > 128) {
            $baseid = substr($baseid, 0, 128);
        }

        $query = "
            INSERT INTO {$schema}.descriptions_custom
                (plugin, baseid, name, description)
            VALUES ($1, $2, $3, $4)
            ON CONFLICT (plugin, baseid)
            DO UPDATE SET
                name = EXCLUDED.name,
                description = EXCLUDED.description
        ";

        $params = [$plugin, $baseid, $name, $description];
        $result = pg_query_params($conn, $query, $params);

        if ($result) {
            $message .= "<p>Item data inserted/updated successfully!</p>";
        } else {
            $message .= "<p>Error inserting/updating item data: " . pg_last_error($conn) . "</p>";
        }
    } else {
        $message .= "<p>Please fill in the required field: Base ID.</p>";
    }
}

//
// ────────────────────────────────────────────────────────────────────
//   CSV UPLOAD
// ────────────────────────────────────────────────────────────────────
//
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['csv_file']['tmp_name'];
        $fileName    = $_FILES['csv_file']['name'];

        $allowedfileExtensions = array('csv');
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

        if (in_array($fileExtension, $allowedfileExtensions)) {
            if (($handle = fopen($fileTmpPath, 'r')) !== false) {
                $header = fgetcsv($handle, 1000, ',');
                $hasPluginColumn = is_array($header) && strtolower(trim($header[0] ?? '')) === 'plugin';
                $csvHeaderValid = $hasPluginColumn;
                if (!$hasPluginColumn) {
                    $message .= '<p>Invalid CSV header. Expected: plugin, baseid, name, description.</p>';
                    fclose($handle);
                    $handle = false;
                }

                $rowCount = 0;
                while ($handle !== false && ($data = fgetcsv($handle, 1000, ',')) !== false) {
                    $plugin = trim($data[0] ?? '');
                    $baseid = trim($data[1] ?? '');
                    $name = $data[2] ?? '';
                    $description = $data[3] ?? '';

                    if (!empty($baseid)) {
                        $baseid = normalizeDescriptionManagerBaseId($baseid);
                        // Truncate baseid to 128 characters
                        if (strlen($baseid) > 128) {
                            $baseid = substr($baseid, 0, 128);
                        }

                        $query = "
                            INSERT INTO $schema.descriptions_custom (
                                plugin,
                                baseid,
                                name,
                                description
                            )
                            VALUES ($1, $2, $3, $4)
                            ON CONFLICT (plugin, baseid)
                            DO UPDATE SET
                                name = EXCLUDED.name,
                                description = EXCLUDED.description
                        ";
                        $result = pg_query_params($conn, $query, [
                            $plugin,
                            $baseid,
                            $name,
                            $description
                        ]);

                        if ($result) {
                            $rowCount++;
                        } else {
                            $message .= "<p>Error processing row with baseid '$baseid': " . pg_last_error($conn) . "</p>";
                        }
                    } else {
                        $message .= "<p>Skipping empty or invalid row (baseid missing).</p>";
                    }
                }
                if ($handle !== false) {
                    fclose($handle);
                }

                if ($csvHeaderValid) {
                    $message .= "<p>$rowCount records inserted/updated successfully from the CSV file.</p>";
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
//   TRUNCATE TABLE
// ────────────────────────────────────────────────────────────────────
//
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['truncate_items'])) {
    $truncate_query = "TRUNCATE TABLE {$schema}.descriptions_custom";
    $result = pg_query($conn, $truncate_query);

    if ($result) {
        $message .= "<p>All custom item entries have been deleted successfully.</p>";
    } else {
        $message .= "<p>Error truncating table: " . pg_last_error($conn) . "</p>";
    }
}

//
// ────────────────────────────────────────────────────────────────────
//   UPDATE SINGLE ENTRY
// ────────────────────────────────────────────────────────────────────
//
$formAction = '?#table';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_single') {
    $plugin = trim($_POST['plugin'] ?? '');
    $baseid = trim($_POST['baseid'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!empty($baseid)) {
        $baseid = normalizeDescriptionManagerBaseId($baseid);
        if (strlen($baseid) > 128) {
            $baseid = substr($baseid, 0, 128);
        }

        $query = "
            INSERT INTO {$schema}.descriptions_custom
                (plugin, baseid, name, description)
            VALUES ($1, $2, $3, $4)
            ON CONFLICT (plugin, baseid)
            DO UPDATE SET
                name = EXCLUDED.name,
                description = EXCLUDED.description
        ";

        $params = [$plugin, $baseid, $name, $description];
        $result = pg_query_params($conn, $query, $params);

        if ($result) {
            $message .= "<p>Item entry updated successfully!</p>";
        } else {
            $message .= "<p>Error updating item entry: " . pg_last_error($conn) . "</p>";
        }
    }
}

?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
    /* ==========================================================================
       Item Descriptions Page Styles
       Base styles imported from main.css, custom overrides below
       ========================================================================== */
    
    /* Override main container styles */
    main {
        /* Compact responsive gutter instead of a fixed 10% on every viewport. */
        padding: 10px clamp(10px, 2.5vw, 34px) 24px;
        width: 100%;
        margin: 0;
    }
    
    /* Override footer styles */
    footer {
        position: fixed;
        bottom: 0;
        width: 100%;
        height: 20px;
        background: #031633;
        z-index: 100;
    }

    /* Font Face Declaration */
    @font-face {
        font-family: 'MagicCards';
        src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
    }

    /* Header Styling - compact inline row, see .chim-page-head in chim-theme.css */
    #title-text {
        font-family: 'MagicCards', serif;
    }

    /* Content Layout Improvements */
    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }

    .content-section {
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        padding: 25px;
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }
    
    .content-section:hover {
        border-color: #4a4a4a;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2), inset 0 1px rgba(255, 255, 255, 0.05);
    }

    .content-section h2 {
        font-family: 'MagicCards', serif;
        color: rgb(242, 124, 17);
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        word-spacing: 6px;
        margin-bottom: 15px;
        margin-top: 0;
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
    label {
        display: block;
        margin-top: 15px;
        margin-bottom: 5px;
        color: rgb(242, 124, 17);
        font-weight: bold;
    }

    input[type="text"],
    input[type="file"],
    textarea {
        width: 100%;
        padding: 10px 12px;
        margin-bottom: 10px;
        border-radius: 6px;
        border: 1px solid #3a3a3a;
        background: rgba(26, 26, 26, 0.8);
        color: #e9efff;
        box-sizing: border-box;
        transition: all 0.2s ease;
    }
    
    input[type="text"]:focus,
    textarea:focus {
        border-color: rgba(242, 124, 17, 0.5);
        outline: none;
        box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
        background: rgba(34, 34, 34, 0.9);
    }

    textarea {
        resize: vertical;
        min-height: 80px;
    }

    .button-group {
        display: flex;
        gap: 15px;
        margin-top: 15px;
        flex-wrap: wrap;
    }

    /* Button styles imported from main.css */
    .action-button.export-csv {
        background-color: rgba(242, 124, 17, 0.8);
    }

    .action-button.export-csv:hover {
        background-color: rgba(242, 124, 17, 1);
    }

    /* Table container height adjustment */
    .table-container {
        max-height: calc(100vh - 450px);
        margin-top: 20px;
        width: 100%;
        overflow-x: auto;
    }

    /* Table styling improvements */
    .table-container table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
    }

    th, td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #3a3a3a;
        word-wrap: break-word;
        overflow-wrap: break-word;
        hyphens: auto;
        vertical-align: top;
    }

    th {
        background: linear-gradient(135deg, rgba(58, 58, 58, 0.9), rgba(48, 48, 48, 0.9));
        color: rgb(242, 124, 17);
        font-weight: bold;
        font-family: 'MagicCards', serif;
        word-spacing: 4px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    }

    tr:hover {
        background: rgba(58, 58, 58, 0.5);
    }

    /* Column width optimization */
    .table-container th:nth-child(1),
    .table-container td:nth-child(1) {
        width: 14%;
        min-width: 120px;
    }

    .table-container th:nth-child(2),
    .table-container td:nth-child(2) {
        width: 16%;
        min-width: 140px;
    }

    .table-container th:nth-child(3),
    .table-container td:nth-child(3) {
        width: 18%;
        min-width: 150px;
    }

    .table-container th:nth-child(4),
    .table-container td:nth-child(4) {
        width: 40%;
        min-width: 250px;
    }

    .table-container th:nth-child(5),
    .table-container td:nth-child(5) {
        width: 12%;
        min-width: 100px;
    }

    /* Filter improvements */
    .filter-section {
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        padding: 20px;
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
    }

    .filter-buttons {
        margin: 10px 0;
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
    }

    .alphabet-button {
        padding: 8px 12px;
        background-color: #3a3a3a;
        color: #f8f9fa;
        text-decoration: none;
        border-radius: 4px;
        transition: background-color 0.3s;
        font-size: 0.9em;
    }

    .alphabet-button:hover {
        background-color: rgb(242, 124, 17);
        color: #000;
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

    /* Modal specific overrides - match Oghma styling exactly */
    .modal-backdrop {
        overflow-y: auto !important;
        padding: 20px 0;
    }

    .modal-container {
        position: relative !important;
        top: auto !important;
        left: auto !important;
        transform: none !important;
        margin: 80px auto 40px auto !important;
        max-width: 800px !important;
        width: 90% !important;
        background-color: #2a2a2a !important;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
    }

    .modal-header {
        background-color: #3a3a3a;
        padding: 20px;
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
        border-bottom: 2px solid rgb(242, 124, 17);
    }

    .modal-title {
        margin: 0;
        color: rgb(242, 124, 17);
        font-family: 'MagicCards', serif;
        word-spacing: 6px;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
    }

    .modal-body {
        padding: 30px;
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
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .toast-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background-color: rgb(242, 124, 17);
        color: white;
        padding: 15px 25px;
        border-radius: 4px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        z-index: 2000;
        display: none;
    }

    .toast-notification.show {
        display: block;
        animation: slideIn 0.3s ease-in;
    }

    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .content-grid {
            grid-template-columns: 1fr;
        }
        
        .search-container {
            min-width: 200px;
        }
        
        .action-container {
            flex-direction: column;
            align-items: stretch;
        }
        
        .content-section {
            padding: 15px;
        }
    }
</style>

<?php if ($isEmbed): ?>
<style>
    /* Embedded in hub: remove extra top padding since navbar is hidden */
    main { padding-top: 10px; }
</style>
<?php endif; ?>

<main>
    <div id="toast" class="toast-notification">
        <span class="message"></span>
    </div>

    <div class="page-header chim-page-head">
        <h1 id="page-title" class="chim-page-head-title">
            <span id="title-text">Description Manager</span>
        </h1>
        <p class="page-subtitle chim-page-head-note">Create custom descriptions for items and equipment that enhance NPC context</p>
    </div>

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
                    <a href="?action=export_custom_items" class="action-button export-csv">Export Custom Descriptions</a>
                </div>
                <p style="margin-top: 15px;">CSV format: plugin, baseid, name, description</p>
                <p style="margin-top: 10px; color: #bbb;">
                    Use the plugin filename in the first column, such as <code>Skyrim.esm</code>, and the local FormID in the baseid column, such as <code>000098A0</code>.
                    Leave plugin blank only for legacy wildcard keys like <code>XX0098A0</code> or <code>FEXXX822</code>.
                </p>
            </form>
        </div>

        <div class="content-section">
            <h2>Database Management</h2>
            <p>Verify uploads: <br><b>Server Actions → Database Manager → dwemer → public → descriptions_custom</b></p>
            <p>View merged data: <br><b>Server Actions → Database Manager → dwemer → public → Views → combined_descriptions</b></p>
            
            <div class="button-group" style="margin-top: 20px;">
                <form action="" method="post" style="display: inline;">
                    <input 
                        type="submit" 
                        name="truncate_items" 
                        value="Factory Reset Item Override Table"
                        class="btn-danger"
                        onclick="return confirm('Are you sure you want to DELETE ALL ENTRIES in descriptions_custom? This action is IRREVERSIBLE!');"
                    >
                </form>
            </div>
            <p style="margin-top: 15px;">This will delete all custom item entries you have uploaded.</p>
        </div>
    </div>

    <div class="full-width-section">
        <?php
        $letter = isset($_GET['letter']) ? strtoupper($_GET['letter']) : '';
        $searchTerm = isset($_GET['search']) ? strtolower(trim($_GET['search'])) : '';

        // Build query based on filters
        if (!empty($letter) && ctype_alpha($letter) && strlen($letter) === 1) {
            if (!empty($searchTerm)) {
                $query_combined = "
                    SELECT *
                    FROM {$schema}.combined_descriptions
                    WHERE LOWER(name) LIKE LOWER($1) 
                    AND (LOWER(plugin) LIKE LOWER($2) OR LOWER(baseid) LIKE LOWER($2) OR LOWER(name) LIKE LOWER($2))
                    ORDER BY name ASC
                ";
                $params_combined = [$letter . '%', '%' . $searchTerm . '%'];
            } else {
                $query_combined = "
                    SELECT *
                    FROM {$schema}.combined_descriptions
                    WHERE LOWER(name) LIKE LOWER($1)
                    ORDER BY name ASC
                ";
                $params_combined = [$letter . '%'];
            }
        } else {
            if (!empty($searchTerm)) {
                $query_combined = "
                    SELECT *
                    FROM {$schema}.combined_descriptions
                    WHERE LOWER(plugin) LIKE LOWER($1) OR LOWER(baseid) LIKE LOWER($1) OR LOWER(name) LIKE LOWER($1)
                    ORDER BY name ASC
                ";
                $params_combined = ['%' . $searchTerm . '%'];
            } else {
                $query_combined = "
                    SELECT *
                    FROM {$schema}.combined_descriptions
                    ORDER BY name ASC
                ";
                $params_combined = [];
            }
        }

        $result_combined = !empty($params_combined) 
            ? pg_query_params($conn, $query_combined, $params_combined)
            : pg_query($conn, $query_combined);
        ?>

        <h2 id="entries">📋 Descriptions Database</h2>
        
        <div class="action-container">
            <button onclick="openNewEntryModal()" class="action-button add-new">Add New Entry</button>
            <div class="search-container">
                <input type="text" id="searchBox" placeholder="Search descriptions..." style="flex-grow: 1; padding: 8px; border-radius: 4px; border: 1px solid #555555; background-color: #4a4a4a; color: #f8f9fa;">
                <button onclick="applySearch()" class="action-button edit">Search</button>
            </div>
        </div>

        <!-- Alphabetic filter -->
        <div class="filter-section">
            <strong>Filter by Name:</strong>
            <div class="filter-buttons">
                <a href="?#entries" class="alphabet-button">All</a>
                <?php
                foreach (range('A', 'Z') as $char) {
                    echo '<a href="?letter=' . $char . '#entries" class="alphabet-button">' . $char . '</a>';
                }
                ?>
            </div>
        </div>

        <?php
        if ($result_combined) {
            echo '<div id="item-table-container" class="table-container">';
            echo '<table>';
            echo '<tr>';
            echo '  <th>Base ID</th>';
            echo '  <th>Plugin</th>';
            echo '  <th>Name</th>';
            echo '  <th>Description</th>';
            echo '  <th>Actions</th>';
            echo '</tr>';

            $rowCountCombined = 0;
            while ($row = pg_fetch_assoc($result_combined)) {
                echo '<tr>';
                echo '  <td>' . htmlspecialchars($row['baseid'] ?? '') . '</td>';
                echo '  <td>' . htmlspecialchars($row['plugin'] ?? '') . '</td>';
                echo '  <td>' . htmlspecialchars($row['name'] ?? '') . '</td>';
                echo '  <td style="max-width: 400px; word-wrap: break-word;">' . nl2br(htmlspecialchars(substr($row['description'] ?? '', 0, 200))) . (strlen($row['description'] ?? '') > 200 ? '...' : '') . '</td>';
                
                echo '<td>';
                $jsData = [
                    'plugin' => $row['plugin'] ?? '',
                    'baseid' => $row['baseid'],
                    'name' => $row['name'] ?? '',
                    'description' => $row['description'] ?? ''
                ];
                echo '<button onclick="openEditModal(' . 
                    htmlspecialchars(json_encode($jsData), ENT_QUOTES, 'UTF-8') . 
                    ')" class="action-button edit">Edit</button>';
                echo '</td>';
                echo '</tr>';
                
                $rowCountCombined++;
            }
            echo '</table>';
            echo '</div>';

            if ($rowCountCombined === 0) {
                echo '<p>No descriptions found.</p>';
            }
        } else {
            echo '<p>Error fetching combined descriptions: ' . pg_last_error($conn) . '</p>';
        }
        ?>
    </div>
</main>

<div id="editModal" class="modal-backdrop">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">Edit Description</h2>
        </div>
        <div class="modal-body">
            <form action="<?php echo $formAction; ?>" method="post">
                <input type="hidden" name="action" value="update_single">

                <label for="edit_plugin">Plugin:</label>
                <small>Plugin cannot be changed after creation. Create a new entry if you need to move it.</small>
                <input type="text" name="plugin" id="edit_plugin" readonly style="background-color: #2a2a2a; cursor: not-allowed;">

                <label for="edit_baseid">Base ID:</label>
                <small>Base IDs cannot be changed after creation. If you need to change an ID, create a new entry.</small>
                <input type="text" name="baseid" id="edit_baseid" readonly style="background-color: #2a2a2a; cursor: not-allowed;" required>

                <label for="edit_name">Name:</label>
                <small>Display name for the entry (optional).</small>
                <input type="text" name="name" id="edit_name">

                <label for="edit_description">Description:</label>
                <small>Short escription to be injected into AI context.</small>
                <textarea name="description" id="edit_description" rows="6"></textarea>

                <div class="modal-footer">
                    <button type="submit" class="btn-save">Save Changes</button>
                    <button type="button" onclick="closeEditModal()" class="btn-cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="newEntryModal" class="modal-backdrop">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">Add New Description</h2>
        </div>
        <div class="modal-body">
            <form action="" method="post">
                <label for="new_plugin">Plugin:</label>
                <small>Use the source plugin filename, for example <code>Skyrim.esm</code>. Leave blank only for legacy wildcard keys.</small>
                <input type="text" name="plugin" id="new_plugin">

                <label for="new_baseid">Base ID (required):</label>
                <small>Use the local FormID, for example <code>0000ABCD</code>, or a legacy wildcard key like <code>XX000ABC</code>.</small>
                <input type="text" name="baseid" id="new_baseid" required>

                <label for="new_name">Name:</label>
                <small>Display name for the entry (optional).</small>
                <input type="text" name="name" id="new_name">

                <label for="new_description">Description:</label>
                <small>Short description to be injected into AI context.</small>
                <textarea name="description" id="new_description" rows="6"></textarea>

                <div class="modal-footer">
                    <button type="submit" name="submit_individual" class="btn-save">Add Entry</button>
                    <button type="button" onclick="closeNewEntryModal()" class="btn-cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditModal(data) {
    const modal = document.getElementById('editModal');
    document.getElementById('edit_plugin').value = data.plugin || '';
    document.getElementById('edit_baseid').value = data.baseid;
    document.getElementById('edit_name').value = data.name || '';
    document.getElementById('edit_description').value = data.description || '';
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function openNewEntryModal() {
    const modal = document.getElementById('newEntryModal');
    document.getElementById('new_plugin').value = '';
    document.getElementById('new_baseid').value = '';
    document.getElementById('new_name').value = '';
    document.getElementById('new_description').value = '';
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeNewEntryModal() {
    document.getElementById('newEntryModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function applySearch() {
    const searchTerm = document.getElementById('searchBox').value;
    window.location.href = '?search=' + encodeURIComponent(searchTerm) + '#entries';
}

document.getElementById('searchBox').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        applySearch();
    }
});

window.onclick = function(event) {
    const editModal = document.getElementById('editModal');
    const newEntryModal = document.getElementById('newEntryModal');
    if (event.target === editModal) {
        closeEditModal();
    }
    if (event.target === newEntryModal) {
        closeNewEntryModal();
    }
};

// Toast notification
function showToast(message, duration = 5000) {
    const toast = document.getElementById('toast');
    const messageSpan = toast.querySelector('.message');
    messageSpan.textContent = message;
    toast.classList.add('show');
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, duration);
}

<?php if (!empty($message)): ?>
document.addEventListener('DOMContentLoaded', function() {
    showToast(<?php echo json_encode(strip_tags($message)); ?>);
});
<?php endif; ?>

// Set initial search box value from URL
window.addEventListener("load", function() {
    const urlParams = new URLSearchParams(window.location.search);
    const searchTerm = urlParams.get("search");
    if (searchTerm) {
        document.getElementById("searchBox").value = searchTerm;
    }
});
</script>

</body>
</html>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
