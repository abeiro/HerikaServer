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

function descriptionManagerImageUrl(string $relativePath): string
{
    $relativePath = str_replace('\\', '/', ltrim($relativePath, "/\\"));
    if ($relativePath === '') {
        return '';
    }

    $segments = array_map('rawurlencode', explode('/', $relativePath));
    return '../' . implode('/', $segments);
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
    select,
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
    select:focus,
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

    .description-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 24px;
        border-bottom: 1px solid rgba(242, 124, 17, 0.25);
    }

    .description-tab-button {
        border: 1px solid #3a3a3a;
        border-bottom: none;
        background: linear-gradient(180deg, rgba(42, 42, 42, 0.9), rgba(34, 34, 34, 0.95));
        color: #f8f9fa;
        padding: 10px 16px;
        cursor: pointer;
        border-top-left-radius: 6px;
        border-top-right-radius: 6px;
        font-family: 'MagicCards', serif;
        word-spacing: 5px;
    }

    .description-tab-button.active {
        color: rgb(242, 124, 17);
        border-color: rgba(242, 124, 17, 0.45);
        background: linear-gradient(180deg, rgba(52, 52, 52, 0.95), rgba(38, 38, 38, 1));
    }

    .description-tab-panel {
        display: none;
    }

    .description-tab-panel.active {
        display: block;
    }

    .creator-status-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(120px, 1fr));
        gap: 12px;
        margin: 16px 0;
    }

    .creator-stat {
        background: rgba(26, 26, 26, 0.75);
        border: 1px solid #3a3a3a;
        border-radius: 6px;
        padding: 12px;
    }

    .creator-stat strong {
        display: block;
        color: rgb(242, 124, 17);
        font-size: 1.4em;
        line-height: 1.2;
    }

    .creator-progress {
        height: 16px;
        background: rgba(20, 20, 20, 0.9);
        border: 1px solid #3a3a3a;
        border-radius: 4px;
        overflow: hidden;
        margin: 12px 0;
    }

    .creator-progress-bar {
        height: 100%;
        width: 0%;
        background: rgb(242, 124, 17);
        transition: width 0.2s ease;
    }

    .creator-preview-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin: 14px 0 10px;
        flex-wrap: wrap;
    }

    .creator-preview-pager {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .creator-preview-filter {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 280px;
    }

    .creator-preview-filter label {
        color: #ddd;
        margin: 0;
        white-space: nowrap;
    }

    .creator-preview-filter select {
        min-width: 220px;
    }

    .creator-preview-page-label {
        color: #ddd;
        min-width: 96px;
        text-align: center;
    }

    .creator-preview-groups {
        display: flex;
        flex-direction: column;
        gap: 18px;
        margin-top: 12px;
    }

    .creator-preview-group-title {
        color: rgb(242, 124, 17);
        font-weight: bold;
        margin: 0 0 10px;
        overflow-wrap: anywhere;
    }

    .creator-preview-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 14px;
    }

    .creator-preview-card {
        background: rgba(26, 26, 26, 0.75);
        border: 1px solid #3a3a3a;
        border-radius: 6px;
        padding: 10px;
        min-height: 235px;
    }

    .creator-preview-card img {
        width: 100%;
        aspect-ratio: 1 / 1;
        object-fit: contain;
        background: #222;
        border-radius: 4px;
        border: 1px solid #333;
    }

    .creator-preview-card div {
        margin-top: 8px;
        color: #ddd;
        font-size: 0.9em;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .creator-preview-card-actions {
        margin-top: 10px;
        display: flex;
        justify-content: flex-end;
    }

    .creator-preview-card-actions .btn-danger {
        padding: 6px 10px;
        font-size: 0.85em;
    }

    .creator-log {
        background: rgba(12, 12, 12, 0.85);
        border: 1px solid #3a3a3a;
        border-radius: 6px;
        padding: 12px;
        min-height: 80px;
        max-height: 180px;
        overflow-y: auto;
        color: #d7d7d7;
        font-family: Consolas, monospace;
        font-size: 0.9em;
        white-space: pre-wrap;
    }

    .creator-csv-list {
        margin-top: 16px;
        overflow-x: auto;
    }

    .creator-csv-list table,
    .creator-csv-modal-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: auto;
    }

    .creator-csv-list th,
    .creator-csv-list td,
    .creator-csv-modal-table th,
    .creator-csv-modal-table td {
        padding: 10px;
        border-bottom: 1px solid #3a3a3a;
        vertical-align: top;
    }

    .creator-csv-list td:first-child,
    .creator-csv-modal-table td {
        overflow-wrap: anywhere;
    }

    .creator-csv-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .creator-csv-modal-container {
        max-width: 1100px !important;
    }

    .creator-csv-modal-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
        flex-wrap: wrap;
    }

    .creator-csv-modal-table-wrap {
        max-height: 55vh;
        overflow: auto;
        border: 1px solid #3a3a3a;
        border-radius: 6px;
    }

    .creator-csv-description-edit {
        width: 100%;
        min-width: 320px;
        resize: vertical;
        font-family: inherit;
        font-size: 0.95em;
        line-height: 1.4;
    }

    .creator-csv-modal-note {
        color: #bbb;
        margin: 8px 0 0;
    }

    .creator-generate-confirm-container {
        max-width: 1180px !important;
    }

    .creator-generate-confirm-toolbar {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
        margin-bottom: 12px;
    }

    .creator-generate-confirm-content {
        max-height: 62vh;
        overflow: auto;
        border: 1px solid #3a3a3a;
        border-radius: 6px;
        padding: 12px;
        background: rgba(12, 12, 12, 0.35);
    }

    .creator-help {
        color: #bbb;
        line-height: 1.55;
    }

    .creator-help ol {
        margin: 10px 0 0 20px;
        padding: 0;
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
        width: 7%;
        min-width: 68px;
    }

    .table-container th:nth-child(2),
    .table-container td:nth-child(2) {
        width: 13%;
        min-width: 120px;
    }

    .table-container th:nth-child(3),
    .table-container td:nth-child(3) {
        width: 16%;
        min-width: 140px;
    }

    .table-container th:nth-child(4),
    .table-container td:nth-child(4) {
        width: 18%;
        min-width: 150px;
    }

    .table-container th:nth-child(5),
    .table-container td:nth-child(5) {
        width: 34%;
        min-width: 250px;
    }

    .table-container th:nth-child(6),
    .table-container td:nth-child(6) {
        width: 12%;
        min-width: 100px;
    }

    .description-image-cell {
        text-align: center;
        vertical-align: middle;
    }

    .description-image-thumb {
        width: 44px;
        height: 44px;
        object-fit: contain;
        display: inline-block;
        background: #202020;
        border: 1px solid #444;
        border-radius: 4px;
    }

    .description-image-empty {
        color: #777;
        font-size: 0.9em;
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

        .creator-status-grid {
            grid-template-columns: 1fr 1fr;
        }

        .creator-preview-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
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

    <div class="description-tabs">
        <button type="button" class="description-tab-button active" data-description-tab="manager">Description Manager</button>
        <button type="button" class="description-tab-button" data-description-tab="creator">Description Creator</button>
    </div>

    <div id="description-tab-manager" class="description-tab-panel active">
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
                    SELECT cd.*, ii.image_path AS item_image_path
                    FROM {$schema}.combined_descriptions cd
                    LEFT JOIN {$schema}.item_images ii
                      ON ii.plugin = cd.plugin
                     AND ii.baseid = cd.baseid
                    WHERE LOWER(cd.name) LIKE LOWER($1)
                    AND (LOWER(cd.plugin) LIKE LOWER($2) OR LOWER(cd.baseid) LIKE LOWER($2) OR LOWER(cd.name) LIKE LOWER($2))
                    ORDER BY cd.name ASC
                ";
                $params_combined = [$letter . '%', '%' . $searchTerm . '%'];
            } else {
                $query_combined = "
                    SELECT cd.*, ii.image_path AS item_image_path
                    FROM {$schema}.combined_descriptions cd
                    LEFT JOIN {$schema}.item_images ii
                      ON ii.plugin = cd.plugin
                     AND ii.baseid = cd.baseid
                    WHERE LOWER(cd.name) LIKE LOWER($1)
                    ORDER BY cd.name ASC
                ";
                $params_combined = [$letter . '%'];
            }
        } else {
            if (!empty($searchTerm)) {
                $query_combined = "
                    SELECT cd.*, ii.image_path AS item_image_path
                    FROM {$schema}.combined_descriptions cd
                    LEFT JOIN {$schema}.item_images ii
                      ON ii.plugin = cd.plugin
                     AND ii.baseid = cd.baseid
                    WHERE LOWER(cd.plugin) LIKE LOWER($1) OR LOWER(cd.baseid) LIKE LOWER($1) OR LOWER(cd.name) LIKE LOWER($1)
                    ORDER BY cd.name ASC
                ";
                $params_combined = ['%' . $searchTerm . '%'];
            } else {
                $query_combined = "
                    SELECT cd.*, ii.image_path AS item_image_path
                    FROM {$schema}.combined_descriptions cd
                    LEFT JOIN {$schema}.item_images ii
                      ON ii.plugin = cd.plugin
                     AND ii.baseid = cd.baseid
                    ORDER BY cd.name ASC
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
            echo '  <th>Image</th>';
            echo '  <th>Base ID</th>';
            echo '  <th>Plugin</th>';
            echo '  <th>Name</th>';
            echo '  <th>Description</th>';
            echo '  <th>Actions</th>';
            echo '</tr>';

            $rowCountCombined = 0;
            while ($row = pg_fetch_assoc($result_combined)) {
                echo '<tr>';
                $imageUrl = descriptionManagerImageUrl($row['item_image_path'] ?? '');
                $imageAlt = trim(($row['name'] ?? '') !== '' ? ($row['name'] ?? '') : ($row['baseid'] ?? 'Item image'));
                echo '  <td class="description-image-cell">';
                if ($imageUrl !== '') {
                    echo '<img class="description-image-thumb" src="' . htmlspecialchars($imageUrl) . '" alt="' . htmlspecialchars($imageAlt) . '" loading="lazy">';
                } else {
                    echo '<span class="description-image-empty">-</span>';
                }
                echo '</td>';
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
    </div>

    <div id="description-tab-creator" class="description-tab-panel">
        <div class="content-grid">
            <div class="content-section">
                <h2>Description Creator</h2>
                <div class="creator-help">
                    <p>Generate Description Manager CSV files from captured Prisma item model images.</p>
                    <ol>
                        <li>Capture item images in game from the CHIM Prisma item image capture tool.</li>
                        <li>Select the ITT vision connector and plugin scope here.</li>
                        <li>Generate a CSV, then use the Created CSVs actions to download, import, or package it as a CHIM mod zip.</li>
                    </ol>
                </div>
            </div>

            <div class="content-section">
                <h2>Prompt</h2>
                <p class="creator-help">This is the same <code>item_description_creator</code> prompt shown in Prompts Manager. Custom text saved here updates that prompt entry.</p>
                <label for="creatorPrompt">Visual Description Prompt</label>
                <textarea id="creatorPrompt" rows="9"></textarea>
                <div class="button-group">
                    <button type="button" id="creatorSavePromptBtn" class="action-button edit">Save Prompt</button>
                    <button type="button" id="creatorResetPromptBtn" class="action-button download-csv">Reset to Default</button>
                </div>
            </div>
        </div>

        <div class="content-section full-width-section">
            <h2>Description Generator</h2>
            <label for="creatorConnector">ITT Vision Connector</label>
            <select id="creatorConnector"></select>

            <label for="creatorPlugin">Plugin</label>
            <select id="creatorPlugin"></select>

            <div class="button-group">
                <button type="button" id="creatorGenerateBtn" class="action-button add-new">Generate CSV</button>
                <button type="button" id="creatorCancelBtn" class="btn-danger" disabled>Cancel</button>
            </div>

            <div class="creator-status-grid">
                <div class="creator-stat"><span>Total</span><strong id="creatorTotal">0</strong></div>
                <div class="creator-stat"><span>Processed</span><strong id="creatorProcessed">0</strong></div>
                <div class="creator-stat"><span>Generated</span><strong id="creatorGenerated">0</strong></div>
                <div class="creator-stat"><span>Errors</span><strong id="creatorErrors">0</strong></div>
            </div>
            <div class="creator-progress">
                <div id="creatorProgressBar" class="creator-progress-bar"></div>
            </div>
            <p id="creatorCurrent" class="creator-help">Idle.</p>
            <div id="creatorLog" class="creator-log"></div>
        </div>

        <div class="content-section full-width-section">
            <h2>Created CSVs</h2>
            <p class="creator-help">Saved Description Manager CSVs are stored on the server and can be reviewed before download or mod packaging.</p>
            <div class="button-group">
                <button type="button" id="creatorRefreshCsvsBtn" class="action-button edit">Refresh CSV List</button>
            </div>
            <div id="creatorCsvList" class="creator-csv-list"></div>
        </div>

        <div class="content-section full-width-section">
            <h2>Image Preview</h2>
            <p class="creator-help">Preview shows every captured image for the selected plugin in a 5-column grid. Titles use the exact format <code>0000000A.jpg - Imperial Sword</code> so the item name can be checked before generation.</p>
            <div class="creator-preview-toolbar">
                <div id="creatorPreviewMeta" class="creator-help">No images loaded.</div>
                <div class="creator-preview-filter">
                    <label for="creatorPreviewPlugin">Preview Plugin</label>
                    <select id="creatorPreviewPlugin"></select>
                </div>
            </div>
            <div id="creatorPreview" class="creator-preview-groups"></div>
        </div>
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

<div id="creatorCsvModal" class="modal-backdrop">
    <div class="modal-container creator-csv-modal-container">
        <div class="modal-header">
            <h2 id="creatorCsvModalTitle" class="modal-title">CSV Contents</h2>
        </div>
        <div class="modal-body">
            <div class="creator-csv-modal-toolbar">
                <div id="creatorCsvModalMeta" class="creator-help"></div>
            </div>
            <div id="creatorCsvModalContent" class="creator-csv-modal-table-wrap"></div>
            <p id="creatorCsvModalNote" class="creator-csv-modal-note"></p>
            <div class="modal-footer">
                <button type="button" id="creatorCsvSaveBtn" class="btn-save" disabled>Save Changes</button>
                <button type="button" onclick="closeCreatorCsvModal()" class="btn-cancel">Close</button>
            </div>
        </div>
    </div>
</div>

<div id="creatorGenerateConfirmModal" class="modal-backdrop">
    <div class="modal-container creator-generate-confirm-container">
        <div class="modal-header">
            <h2 class="modal-title">Confirm CSV Generation</h2>
        </div>
        <div class="modal-body">
            <div class="creator-generate-confirm-toolbar">
                <div id="creatorGenerateConfirmMeta" class="creator-help">Loading images...</div>
            </div>
            <div id="creatorGenerateConfirmContent" class="creator-generate-confirm-content"></div>
            <div class="modal-footer">
                <button type="button" id="creatorConfirmGenerateBtn" class="btn-save">Generate CSV</button>
                <button type="button" onclick="closeCreatorGenerateConfirmModal()" class="btn-cancel">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
const descriptionCreator = {
    api: 'api/description_creator.php',
    job: null,
    running: false,
    cancelRequested: false,
    pendingGeneratePlugin: '',
    openCsvFilename: '',
    csvDirtyRows: {},
    counts: { total: 0, plugins: [] },
    csvs: []
};

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function switchDescriptionTab(tabName) {
    document.querySelectorAll('.description-tab-button').forEach(function(button) {
        button.classList.toggle('active', button.dataset.descriptionTab === tabName);
    });
    document.querySelectorAll('.description-tab-panel').forEach(function(panel) {
        panel.classList.toggle('active', panel.id === 'description-tab-' + tabName);
    });
    if (tabName === 'creator') {
        window.history.replaceState({}, '', '#creator');
    }
}

async function descriptionCreatorRequest(action, formData) {
    const options = {};
    let url = descriptionCreator.api;
    if (formData) {
        formData.append('action', action);
        options.method = 'POST';
        options.body = formData;
    } else {
        url += '?action=' + encodeURIComponent(action);
    }

    const response = await fetch(url, options);
    const data = await response.json();
    if (!data.success) {
        throw new Error(data.error || 'Description creator request failed');
    }
    return data;
}

function descriptionCreatorFormatBytes(bytes) {
    const size = Number(bytes || 0);
    if (size < 1024) return size + ' B';
    if (size < 1024 * 1024) return (size / 1024).toFixed(1) + ' KB';
    return (size / (1024 * 1024)).toFixed(1) + ' MB';
}

function descriptionCreatorFormatDate(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleString();
}

function descriptionCreatorRenderCsvList(csvs) {
    descriptionCreator.csvs = Array.isArray(csvs) ? csvs : [];
    const container = document.getElementById('creatorCsvList');
    if (!descriptionCreator.csvs.length) {
        container.innerHTML = '<p class="creator-help">No generated CSVs found yet.</p>';
        return;
    }

    let html = '<table><thead><tr>' +
        '<th>Filename</th><th>Rows</th><th>Size</th><th>Modified</th><th>Actions</th>' +
        '</tr></thead><tbody>';
    descriptionCreator.csvs.forEach(function(csv) {
        html += '<tr>' +
            '<td>' + escapeHtml(csv.filename) + '</td>' +
            '<td>' + escapeHtml(csv.row_count) + '</td>' +
            '<td>' + escapeHtml(descriptionCreatorFormatBytes(csv.size)) + '</td>' +
            '<td>' + escapeHtml(descriptionCreatorFormatDate(csv.modified_at)) + '</td>' +
            '<td><div class="creator-csv-actions">' +
            '<button type="button" class="action-button edit creator-csv-view" data-filename="' + escapeHtml(csv.filename) + '">View</button>' +
            '<a class="action-button export-csv" href="' + escapeHtml(csv.download_url) + '">Download</a>' +
            '<a class="action-button download-csv" href="' + escapeHtml(csv.zip_url) + '">Mod Zip</a>' +
            '<button type="button" class="action-button add-new creator-csv-import" data-filename="' + escapeHtml(csv.filename) + '">Import</button>' +
            '<button type="button" class="btn-danger creator-csv-delete" data-filename="' + escapeHtml(csv.filename) + '">Delete</button>' +
            '</div></td>' +
            '</tr>';
    });
    html += '</tbody></table>';
    container.innerHTML = html;

    container.querySelectorAll('.creator-csv-view').forEach(function(button) {
        button.addEventListener('click', function() {
            descriptionCreatorOpenCsv(button.dataset.filename).catch(function(error) {
                showToast(error.message || String(error));
            });
        });
    });

    container.querySelectorAll('.creator-csv-delete').forEach(function(button) {
        button.addEventListener('click', function() {
            descriptionCreatorDeleteCsv(button.dataset.filename).catch(function(error) {
                showToast(error.message || String(error));
            });
        });
    });

    container.querySelectorAll('.creator-csv-import').forEach(function(button) {
        button.addEventListener('click', function() {
            descriptionCreatorImportCsv(button.dataset.filename).catch(function(error) {
                showToast(error.message || String(error));
            });
        });
    });
}

function descriptionCreatorPopulatePluginSelects(counts, selectedGenerator, selectedPreview) {
    descriptionCreator.counts = counts || { total: 0, plugins: [] };

    const pluginSelect = document.getElementById('creatorPlugin');
    pluginSelect.innerHTML = '';
    const allOption = document.createElement('option');
    allOption.value = '';
    allOption.textContent = 'All plugins (' + (descriptionCreator.counts.total || 0) + ')';
    pluginSelect.appendChild(allOption);
    (descriptionCreator.counts.plugins || []).forEach(function(row) {
        const option = document.createElement('option');
        option.value = row.plugin;
        option.textContent = (row.plugin || '(blank plugin)') + ' (' + row.count + ')';
        pluginSelect.appendChild(option);
    });
    pluginSelect.value = Array.from(pluginSelect.options).some(function(option) {
        return option.value === selectedGenerator;
    }) ? selectedGenerator : '';

    const previewPluginSelect = document.getElementById('creatorPreviewPlugin');
    previewPluginSelect.innerHTML = '';
    (descriptionCreator.counts.plugins || []).forEach(function(row) {
        const option = document.createElement('option');
        option.value = row.plugin;
        option.textContent = (row.plugin || '(blank plugin)') + ' (' + row.count + ')';
        previewPluginSelect.appendChild(option);
    });
    previewPluginSelect.disabled = previewPluginSelect.options.length === 0;
    if (previewPluginSelect.options.length > 0) {
        const nextPreview = Array.from(previewPluginSelect.options).some(function(option) {
            return option.value === selectedPreview;
        }) ? selectedPreview : previewPluginSelect.options[0].value;
        previewPluginSelect.value = nextPreview;
    }
}

async function descriptionCreatorLoadCsvs() {
    const data = await descriptionCreatorRequest('list_csvs');
    descriptionCreatorRenderCsvList(data.csvs || []);
}

async function descriptionCreatorDeleteCsv(filename) {
    if (!filename) {
        return;
    }

    if (!window.confirm('Delete ' + filename + '?')) {
        return;
    }

    const formData = new FormData();
    formData.append('filename', filename);
    const data = await descriptionCreatorRequest('delete_csv', formData);
    descriptionCreatorRenderCsvList(data.csvs || []);
    showToast('Deleted ' + (data.deleted_filename || filename) + '.');
}

async function descriptionCreatorImportCsv(filename) {
    if (!filename) {
        return;
    }

    if (!window.confirm('Import ' + filename + ' into Custom Descriptions? Existing plugin/baseid rows will be updated.')) {
        return;
    }

    const formData = new FormData();
    formData.append('filename', filename);
    const data = await descriptionCreatorRequest('import_csv', formData);
    descriptionCreatorRenderCsvList(data.csvs || []);

    let message = 'Imported ' + Number(data.imported_count || 0) + ' descriptions from ' + (data.filename || filename) + '.';
    if (Number(data.skipped_count || 0) > 0) {
        message += ' Skipped ' + Number(data.skipped_count || 0) + '.';
    }
    if (Number(data.error_count || 0) > 0) {
        message += ' Errors: ' + Number(data.error_count || 0) + '.';
    }
    showToast(message, 8000);
}

async function descriptionCreatorOpenCsv(filename) {
    const url = descriptionCreator.api + '?action=csv_contents&limit=500&filename=' + encodeURIComponent(filename);
    const response = await fetch(url);
    const data = await response.json();
    if (!data.success) {
        throw new Error(data.error || 'Could not open CSV');
    }

    const csv = data.csv || {};
    descriptionCreator.openCsvFilename = csv.filename || filename;
    descriptionCreator.csvDirtyRows = {};
    document.getElementById('creatorCsvSaveBtn').disabled = true;
    document.getElementById('creatorCsvModalTitle').textContent = csv.filename || 'CSV Contents';
    document.getElementById('creatorCsvModalMeta').textContent =
        String(csv.row_count || 0) + ' rows, ' +
        descriptionCreatorFormatBytes(csv.size) + ', modified ' +
        descriptionCreatorFormatDate(csv.modified_at);
    const header = Array.isArray(csv.header) ? csv.header : [];
    const rows = Array.isArray(csv.rows) ? csv.rows : [];
    const maxRowColumns = rows.reduce(function(max, row) {
        return Math.max(max, Array.isArray(row) ? row.length : 0);
    }, 0);
    const columnCount = Math.max(header.length, maxRowColumns, 1);
    const normalizedHeader = header.length ? header : Array.from({ length: columnCount }, function(_, index) {
        return 'Column ' + (index + 1);
    });
    const lowerHeader = normalizedHeader.map(function(value) {
        return String(value || '').trim().toLowerCase();
    });
    const pluginIndex = lowerHeader.indexOf('plugin');
    const baseidIndex = lowerHeader.indexOf('baseid');
    const descriptionIndex = lowerHeader.indexOf('description');
    const editable = pluginIndex >= 0 && baseidIndex >= 0 && descriptionIndex >= 0;

    let html = '<table class="creator-csv-modal-table"><thead><tr>';
    for (let i = 0; i < columnCount; i++) {
        html += '<th>' + escapeHtml(normalizedHeader[i] || ('Column ' + (i + 1))) + '</th>';
    }
    html += '</tr></thead><tbody>';

    rows.forEach(function(row) {
        html += '<tr>';
        const plugin = Array.isArray(row) ? (row[pluginIndex] ?? '') : '';
        const baseid = Array.isArray(row) ? (row[baseidIndex] ?? '') : '';
        for (let i = 0; i < columnCount; i++) {
            const value = Array.isArray(row) ? (row[i] ?? '') : '';
            if (editable && i === descriptionIndex) {
                html += '<td><textarea class="creator-csv-description-edit" rows="3" data-plugin="' + escapeHtml(plugin) + '" data-baseid="' + escapeHtml(baseid) + '">' + escapeHtml(value) + '</textarea></td>';
            } else {
                html += '<td>' + escapeHtml(value) + '</td>';
            }
        }
        html += '</tr>';
    });

    if (!rows.length) {
        html += '<tr><td colspan="' + columnCount + '">No rows found.</td></tr>';
    }
    html += '</tbody></table>';

    document.getElementById('creatorCsvModalContent').innerHTML = html;
    document.getElementById('creatorCsvModalNote').textContent = editable
        ? (csv.truncated ? 'Descriptions are editable. Showing the first 500 rows.' : 'Descriptions are editable. Save changes before importing or downloading.')
        : (csv.truncated ? 'Showing the first 500 rows.' : '');
    document.querySelectorAll('.creator-csv-description-edit').forEach(function(input) {
        input.addEventListener('input', function() {
            const key = input.dataset.plugin + "\u0000" + input.dataset.baseid;
            descriptionCreator.csvDirtyRows[key] = {
                plugin: input.dataset.plugin || '',
                baseid: input.dataset.baseid || '',
                description: input.value
            };
            document.getElementById('creatorCsvSaveBtn').disabled = false;
        });
    });
    document.getElementById('creatorCsvModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeCreatorCsvModal() {
    document.getElementById('creatorCsvModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

async function descriptionCreatorSaveCsvEdits() {
    const updates = Object.values(descriptionCreator.csvDirtyRows || {});
    if (!descriptionCreator.openCsvFilename || updates.length === 0) {
        return;
    }

    const formData = new FormData();
    formData.append('filename', descriptionCreator.openCsvFilename);
    formData.append('updates', JSON.stringify(updates));
    const data = await descriptionCreatorRequest('update_csv_descriptions', formData);
    descriptionCreatorRenderCsvList(data.csvs || []);
    showToast('Saved ' + Number(data.updated_count || 0) + ' description edits.');
    descriptionCreator.csvDirtyRows = {};
    closeCreatorCsvModal();
}

function closeCreatorGenerateConfirmModal() {
    document.getElementById('creatorGenerateConfirmModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function descriptionCreatorSelectedCount() {
    const plugin = document.getElementById('creatorPlugin').value;
    if (!plugin) {
        return descriptionCreator.counts.total || 0;
    }
    const row = (descriptionCreator.counts.plugins || []).find(function(item) {
        return item.plugin === plugin;
    });
    return row ? Number(row.count || 0) : 0;
}

function descriptionCreatorSetButtons(running) {
    document.getElementById('creatorGenerateBtn').disabled = running;
    document.getElementById('creatorCancelBtn').disabled = !running;
    document.getElementById('creatorSavePromptBtn').disabled = running;
    document.getElementById('creatorResetPromptBtn').disabled = running;
    document.getElementById('creatorConnector').disabled = running;
    document.getElementById('creatorPlugin').disabled = running;
}

function descriptionCreatorUpdateProgress(job) {
    const total = Number(job?.total ?? descriptionCreatorSelectedCount() ?? 0);
    const processed = Number(job?.processed_count ?? 0);
    const generated = Number(job?.generated_count ?? 0);
    const errors = Number(job?.error_count ?? 0);
    const pct = total > 0 ? Math.max(0, Math.min(100, Math.round((processed / total) * 100))) : 0;

    document.getElementById('creatorTotal').textContent = String(total);
    document.getElementById('creatorProcessed').textContent = String(processed);
    document.getElementById('creatorGenerated').textContent = String(generated);
    document.getElementById('creatorErrors').textContent = String(errors);
    document.getElementById('creatorProgressBar').style.width = pct + '%';

    let statusText = 'Idle.';
    if (job) {
        if (job.status === 'running') {
            statusText = 'Current: ' + (job.current_title || 'starting next item...');
        } else if (job.status === 'complete') {
            statusText = 'Complete. CSV saved on the server as ' + job.csv_name + '.';
        } else if (job.status === 'canceled') {
            statusText = 'Canceled. Partial CSV saved on the server as ' + job.csv_name + '.';
        } else if (job.status === 'failed') {
            statusText = 'Failed. Check errors below.';
        }
    }
    document.getElementById('creatorCurrent').textContent = statusText;

    const errorLines = (job?.errors || []).map(function(error) {
        return (error.title || 'Item') + ': ' + (error.error || 'unknown error');
    });
    document.getElementById('creatorLog').textContent = errorLines.length ? errorLines.join("\n") : 'No errors.';
}

function descriptionCreatorPopulateState(data) {
    const connectorSelect = document.getElementById('creatorConnector');
    connectorSelect.innerHTML = '';
    (data.connectors || []).forEach(function(connector) {
        const option = document.createElement('option');
        option.value = connector.id;
        option.textContent = connector.label + ' (' + connector.driver + ')';
        if (connector.active || Number(connector.id) === Number(data.active_connector_id || 0)) {
            option.selected = true;
        }
        connectorSelect.appendChild(option);
    });

    descriptionCreatorPopulatePluginSelects(
        data.counts || { total: 0, plugins: [] },
        document.getElementById('creatorPlugin').value || '',
        document.getElementById('creatorPreviewPlugin').value || ''
    );

    document.getElementById('creatorPrompt').value = data.prompt?.active_prompt || '';
    descriptionCreatorRenderCsvList(data.csvs || []);
    descriptionCreatorUpdateProgress(null);
}

function descriptionCreatorRenderPreview(data) {
    const preview = document.getElementById('creatorPreview');
    const items = Array.isArray(data.items) ? data.items : [];
    const total = Number(data.total || 0);
    const plugin = document.getElementById('creatorPreviewPlugin').value;
    document.getElementById('creatorPreviewMeta').textContent = total > 0
        ? 'Showing all ' + total + ' captured item images for ' + (plugin || 'this plugin') + '.'
        : 'No captured item images found for this plugin.';

    preview.innerHTML = '';
    if (!items.length) {
        preview.innerHTML = '<p class="creator-help">No captured item images found for this plugin.</p>';
        return;
    }

    const groups = [];
    const groupLookup = {};
    items.forEach(function(item) {
        const folder = item.mod_folder || item.plugin || '(blank plugin)';
        if (!groupLookup[folder]) {
            groupLookup[folder] = [];
            groups.push({ folder: folder, items: groupLookup[folder] });
        }
        groupLookup[folder].push(item);
    });

    groups.forEach(function(group) {
        const section = document.createElement('section');
        section.className = 'creator-preview-group';

        const title = document.createElement('h3');
        title.className = 'creator-preview-group-title';
        title.textContent = group.folder;
        section.appendChild(title);

        const grid = document.createElement('div');
        grid.className = 'creator-preview-grid';
        group.items.forEach(function(item) {
            const card = document.createElement('div');
            card.className = 'creator-preview-card';
            card.innerHTML =
                '<img src="' + escapeHtml(item.image_url) + '" alt="' + escapeHtml(item.image_title) + '">' +
                '<div>' + escapeHtml(item.image_title) + '</div>' +
                '<div class="creator-preview-card-actions">' +
                '<button type="button" class="btn-danger creator-preview-delete" data-plugin="' + escapeHtml(item.plugin) + '" data-baseid="' + escapeHtml(item.baseid) + '" data-title="' + escapeHtml(item.image_title) + '">Delete</button>' +
                '</div>';
            grid.appendChild(card);
        });

        section.appendChild(grid);
        preview.appendChild(section);
    });

    preview.querySelectorAll('.creator-preview-delete').forEach(function(button) {
        button.addEventListener('click', function() {
            descriptionCreatorDeleteImage(button.dataset.plugin, button.dataset.baseid, button.dataset.title).catch(function(error) {
                showToast(error.message || String(error));
            });
        });
    });
}

async function descriptionCreatorLoadPreview() {
    const previewPlugin = document.getElementById('creatorPreviewPlugin');
    if (!previewPlugin || previewPlugin.options.length === 0) {
        descriptionCreatorRenderPreview({ items: [], total: 0 });
        return;
    }

    const plugin = previewPlugin.value;
    document.getElementById('creatorTotal').textContent = String(descriptionCreatorSelectedCount());
    const url = descriptionCreator.api
        + '?action=list&all=1'
        + '&plugin=' + encodeURIComponent(plugin);
    const response = await fetch(url);
    const data = await response.json();
    if (!data.success) {
        throw new Error(data.error || 'Could not load image preview');
    }

    descriptionCreatorRenderPreview(data);
}

async function descriptionCreatorDeleteImage(plugin, baseid, title) {
    if (!plugin || !baseid) {
        return;
    }

    if (!window.confirm('Delete image ' + (title || baseid) + '? This removes it from item image generation.')) {
        return;
    }

    const generatorPlugin = document.getElementById('creatorPlugin').value;
    const previewPlugin = document.getElementById('creatorPreviewPlugin').value;
    const formData = new FormData();
    formData.append('plugin', plugin);
    formData.append('baseid', baseid);
    const data = await descriptionCreatorRequest('delete_image', formData);

    descriptionCreatorPopulatePluginSelects(data.counts || { total: 0, plugins: [] }, generatorPlugin, previewPlugin);

    const activePreviewPlugin = document.getElementById('creatorPreviewPlugin').value;
    if (activePreviewPlugin === plugin) {
        descriptionCreatorRenderPreview({
            items: data.items || [],
            total: Number(data.total || 0)
        });
    } else {
        await descriptionCreatorLoadPreview();
    }

    showToast('Deleted item image ' + (title || baseid) + '.');
}

function descriptionCreatorRenderConfirmImages(items) {
    const content = document.getElementById('creatorGenerateConfirmContent');
    content.innerHTML = '';
    if (!items.length) {
        content.innerHTML = '<p class="creator-help">No captured item images found for this selection.</p>';
        return;
    }

    const groups = [];
    const groupLookup = {};
    items.forEach(function(item) {
        const folder = item.mod_folder || item.plugin || '(blank plugin)';
        if (!groupLookup[folder]) {
            groupLookup[folder] = [];
            groups.push({ folder: folder, items: groupLookup[folder] });
        }
        groupLookup[folder].push(item);
    });

    groups.forEach(function(group) {
        const section = document.createElement('section');
        section.className = 'creator-preview-group';

        const title = document.createElement('h3');
        title.className = 'creator-preview-group-title';
        title.textContent = group.folder + ' (' + group.items.length + ')';
        section.appendChild(title);

        const grid = document.createElement('div');
        grid.className = 'creator-preview-grid';
        group.items.forEach(function(item) {
            const card = document.createElement('div');
            card.className = 'creator-preview-card';
            card.innerHTML =
                '<img src="' + escapeHtml(item.image_url) + '" alt="' + escapeHtml(item.image_title) + '">' +
                '<div>' + escapeHtml(item.image_title) + '</div>';
            grid.appendChild(card);
        });

        section.appendChild(grid);
        content.appendChild(section);
    });
}

async function descriptionCreatorOpenGenerateConfirm() {
    if (descriptionCreator.running) {
        return;
    }

    const connectorId = document.getElementById('creatorConnector').value;
    if (!connectorId) {
        showToast('Select an ITT vision connector first.');
        return;
    }

    const plugin = document.getElementById('creatorPlugin').value;
    descriptionCreator.pendingGeneratePlugin = plugin;
    document.getElementById('creatorGenerateConfirmMeta').textContent = 'Loading images...';
    document.getElementById('creatorGenerateConfirmContent').innerHTML = '';
    document.getElementById('creatorConfirmGenerateBtn').disabled = true;
    document.getElementById('creatorGenerateConfirmModal').style.display = 'block';
    document.body.style.overflow = 'hidden';

    try {
        const url = descriptionCreator.api
            + '?action=list&all=1'
            + '&plugin=' + encodeURIComponent(plugin);
        const response = await fetch(url);
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.error || 'Could not load images for confirmation');
        }

        const items = Array.isArray(data.items) ? data.items : [];
        const scope = plugin ? plugin : 'all plugins';
        document.getElementById('creatorGenerateConfirmMeta').textContent =
            'Generate descriptions for ' + items.length + ' captured item images from ' + scope + '?';
        document.getElementById('creatorConfirmGenerateBtn').disabled = items.length === 0;
        descriptionCreatorRenderConfirmImages(items);
    } catch (error) {
        document.getElementById('creatorGenerateConfirmMeta').textContent = error.message || String(error);
        document.getElementById('creatorConfirmGenerateBtn').disabled = true;
        document.getElementById('creatorGenerateConfirmContent').innerHTML = '';
    }
}

async function descriptionCreatorInit() {
    try {
        const data = await descriptionCreatorRequest('state');
        descriptionCreatorPopulateState(data);
        await descriptionCreatorLoadPreview();
    } catch (error) {
        showToast(error.message || String(error));
    }
}

async function descriptionCreatorSavePrompt() {
    const formData = new FormData();
    formData.append('prompt', document.getElementById('creatorPrompt').value);
    const data = await descriptionCreatorRequest('save_prompt', formData);
    document.getElementById('creatorPrompt').value = data.prompt.active_prompt || '';
    showToast('Description creator prompt saved.');
}

async function descriptionCreatorResetPrompt() {
    const data = await descriptionCreatorRequest('reset_prompt', new FormData());
    document.getElementById('creatorPrompt').value = data.prompt.active_prompt || '';
    showToast('Description creator prompt reset to default.');
}

async function descriptionCreatorStart() {
    if (descriptionCreator.running) {
        return;
    }

    const connectorId = document.getElementById('creatorConnector').value;
    if (!connectorId) {
        showToast('Select an ITT vision connector first.');
        return;
    }

    descriptionCreator.running = true;
    descriptionCreator.cancelRequested = false;
    descriptionCreatorSetButtons(true);
    document.getElementById('creatorLog').textContent = 'Starting generation...';

    try {
        const formData = new FormData();
        formData.append('connector_id', connectorId);
        formData.append('plugin', document.getElementById('creatorPlugin').value);
        formData.append('prompt', document.getElementById('creatorPrompt').value);
        const started = await descriptionCreatorRequest('start', formData);
        descriptionCreator.job = started.job;
        descriptionCreatorUpdateProgress(descriptionCreator.job);

        while (descriptionCreator.job && descriptionCreator.job.status === 'running' && !descriptionCreator.cancelRequested) {
            const batch = new FormData();
            batch.append('job_id', descriptionCreator.job.job_id);
            batch.append('batch_size', '2');
            const processed = await descriptionCreatorRequest('process', batch);
            descriptionCreator.job = processed.job;
            descriptionCreatorUpdateProgress(descriptionCreator.job);
            await new Promise(function(resolve) { setTimeout(resolve, 150); });
        }

        if (descriptionCreator.cancelRequested && descriptionCreator.job && descriptionCreator.job.status === 'running') {
            const cancelData = new FormData();
            cancelData.append('job_id', descriptionCreator.job.job_id);
            const canceled = await descriptionCreatorRequest('cancel', cancelData);
            descriptionCreator.job = canceled.job;
            descriptionCreatorUpdateProgress(descriptionCreator.job);
        }

        if (descriptionCreator.job && descriptionCreator.job.status === 'complete') {
            showToast('Description CSV generated.');
        }
    } catch (error) {
        showToast(error.message || String(error));
    } finally {
        descriptionCreator.running = false;
        descriptionCreatorSetButtons(false);
        descriptionCreatorLoadCsvs().catch(function(error) {
            showToast(error.message || String(error));
        });
    }
}

async function descriptionCreatorCancel() {
    descriptionCreator.cancelRequested = true;
    document.getElementById('creatorCancelBtn').disabled = true;
    if (descriptionCreator.job && descriptionCreator.job.status === 'running') {
        document.getElementById('creatorCurrent').textContent = 'Cancel requested. Finishing current batch...';
    }
}

document.querySelectorAll('.description-tab-button').forEach(function(button) {
    button.addEventListener('click', function() {
        switchDescriptionTab(button.dataset.descriptionTab);
    });
});

document.getElementById('creatorPlugin').addEventListener('change', function() {
    const plugin = document.getElementById('creatorPlugin').value;
    const previewPlugin = document.getElementById('creatorPreviewPlugin');
    const hasPreviewPlugin = Array.from(previewPlugin.options).some(function(option) {
        return option.value === plugin;
    });
    if (plugin && hasPreviewPlugin) {
        previewPlugin.value = plugin;
        descriptionCreatorLoadPreview().catch(function(error) {
            showToast(error.message || String(error));
        });
    }
});
document.getElementById('creatorPreviewPlugin').addEventListener('change', function() {
    descriptionCreatorLoadPreview().catch(function(error) {
        showToast(error.message || String(error));
    });
});
document.getElementById('creatorGenerateBtn').addEventListener('click', function() {
    descriptionCreatorOpenGenerateConfirm().catch(function(error) {
        showToast(error.message || String(error));
    });
});
document.getElementById('creatorConfirmGenerateBtn').addEventListener('click', function() {
    closeCreatorGenerateConfirmModal();
    descriptionCreatorStart();
});
document.getElementById('creatorCancelBtn').addEventListener('click', descriptionCreatorCancel);
document.getElementById('creatorSavePromptBtn').addEventListener('click', function() {
    descriptionCreatorSavePrompt().catch(function(error) { showToast(error.message || String(error)); });
});
document.getElementById('creatorResetPromptBtn').addEventListener('click', function() {
    descriptionCreatorResetPrompt().catch(function(error) { showToast(error.message || String(error)); });
});
document.getElementById('creatorRefreshCsvsBtn').addEventListener('click', function() {
    descriptionCreatorLoadCsvs().catch(function(error) { showToast(error.message || String(error)); });
});
document.getElementById('creatorCsvSaveBtn').addEventListener('click', function() {
    descriptionCreatorSaveCsvEdits().catch(function(error) { showToast(error.message || String(error)); });
});

if (window.location.hash === '#creator') {
    switchDescriptionTab('creator');
}
descriptionCreatorInit();

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
    const creatorCsvModal = document.getElementById('creatorCsvModal');
    const creatorGenerateConfirmModal = document.getElementById('creatorGenerateConfirmModal');
    if (event.target === editModal) {
        closeEditModal();
    }
    if (event.target === newEntryModal) {
        closeNewEntryModal();
    }
    if (event.target === creatorCsvModal) {
        closeCreatorCsvModal();
    }
    if (event.target === creatorGenerateConfirmModal) {
        closeCreatorGenerateConfirmModal();
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
