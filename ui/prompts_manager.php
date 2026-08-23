<?php
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
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Initialize message variable
$message = '';

/********************************************************************
 *  EXPORT CUSTOM PROMPTS TO CSV
 ********************************************************************/
if (isset($_GET['action']) && $_GET['action'] === 'export_custom') {
    $query = "
        SELECT prompt_key, custom_prompt
        FROM $schema.prompts
        WHERE custom_prompt IS NOT NULL AND custom_prompt != ''
        ORDER BY prompt_key ASC
    ";
    $result = pg_query($conn, $query);
    
    if ($result) {
        $filename = 'custom_prompts_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // Write header
        fputcsv($output, ['prompt_key', 'custom_prompt']);
        
        // Write data
        while ($row = pg_fetch_assoc($result)) {
            fputcsv($output, [$row['prompt_key'], $row['custom_prompt']]);
        }
        
        fclose($output);
        exit;
    }
}

/********************************************************************
 *  HANDLE AJAX REQUESTS BEFORE OUTPUT BUFFER
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    
    // UPDATE CUSTOM PROMPT
    if (isset($_POST['action']) && $_POST['action'] === 'update_custom') {
        $prompt_key = $_POST['prompt_key'] ?? '';
        $custom_prompt = $_POST['custom_prompt'] ?? '';
        
        if (!empty($prompt_key)) {
            // If custom_prompt is empty, set it to NULL to revert to default
            if (trim($custom_prompt) === '') {
                $custom_prompt = null;
            }
            
            $query = "
                UPDATE $schema.prompts 
                SET custom_prompt = $1, 
                    updated_at = CURRENT_TIMESTAMP 
                WHERE prompt_key = $2
            ";
            $result = pg_query_params($conn, $query, [$custom_prompt, $prompt_key]);
            
            if ($result) {
                $message = $custom_prompt === null ? "Custom prompt cleared" : "Custom prompt updated successfully";
                $success = true;
            } else {
                $message = "Error updating prompt: " . pg_last_error($conn);
                $success = false;
            }
        } else {
            $message = "No prompt key specified";
            $success = false;
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'message' => $message]);
        exit;
    }
    
    // CLEAR CUSTOM PROMPT
    if (isset($_POST['action']) && $_POST['action'] === 'clear_custom') {
        $prompt_key = $_POST['prompt_key'] ?? '';
        
        if (!empty($prompt_key)) {
            $query = "
                UPDATE $schema.prompts 
                SET custom_prompt = NULL, 
                    updated_at = CURRENT_TIMESTAMP 
                WHERE prompt_key = $1
            ";
            $result = pg_query_params($conn, $query, [$prompt_key]);
            
            $success = (bool)$result;
            $message = $success ? "Custom prompt cleared" : "Error clearing prompt: " . pg_last_error($conn);
        } else {
            $success = false;
            $message = "No prompt key specified";
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'message' => $message]);
        exit;
    }
}

// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

$TITLE = "⚙️CHIM - Prompts Manager";

ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");
$isEmbed = (isset($_GET['embed']) && $_GET['embed'] == '1');

$debugPaneLink = false;

/********************************************************************
 *  IMPORT CUSTOM PROMPTS FROM CSV
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_custom') {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['csv_file']['tmp_name'];
        $fileExtension = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
        
        if ($fileExtension === 'csv') {
            if (($handle = fopen($fileTmpPath, 'r')) !== false) {
                // Skip header row
                fgetcsv($handle, 1000, ',');
                
                $importCount = 0;
                $errorCount = 0;
                $errors = [];
                
                while (($data = fgetcsv($handle, 10000, ',')) !== false) {
                    if (count($data) < 2) {
                        continue;
                    }
                    
                    $prompt_key = trim($data[0]);
                    $custom_prompt = $data[1];
                    
                    if (empty($prompt_key)) {
                        continue;
                    }
                    
                    // Check if prompt key exists
                    $checkQuery = "SELECT prompt_key FROM $schema.prompts WHERE prompt_key = $1";
                    $checkResult = pg_query_params($conn, $checkQuery, [$prompt_key]);
                    
                    if ($checkResult && pg_num_rows($checkResult) > 0) {
                        // Update custom prompt
                        $updateQuery = "
                            UPDATE $schema.prompts 
                            SET custom_prompt = $1, 
                                updated_at = CURRENT_TIMESTAMP 
                            WHERE prompt_key = $2
                        ";
                        $updateResult = pg_query_params($conn, $updateQuery, [$custom_prompt, $prompt_key]);
                        
                        if ($updateResult) {
                            $importCount++;
                        } else {
                            $errorCount++;
                            $errors[] = "Failed to update: $prompt_key";
                        }
                    } else {
                        $errorCount++;
                        $errors[] = "Prompt key not found: $prompt_key";
                    }
                }
                
                fclose($handle);
                
                if ($importCount > 0) {
                    $message = "Successfully imported $importCount custom prompt(s).";
                    if ($errorCount > 0) {
                        $message .= " $errorCount error(s) occurred.";
                    }
                } else {
                    $message = "No prompts were imported. " . ($errorCount > 0 ? "$errorCount error(s) occurred." : "");
                }
                
                if (!empty($errors) && count($errors) <= 5) {
                    $message .= "<br>" . implode("<br>", $errors);
                }
            } else {
                $message = "Error reading CSV file.";
            }
        } else {
            $message = "Invalid file type. Please upload a CSV file.";
        }
    } else {
        $message = "No file uploaded or upload error occurred.";
    }
}

/********************************************************************
 *  HANDLE NON-AJAX POST REQUESTS
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_custom') {
    $prompt_key = $_POST['prompt_key'] ?? '';
    $custom_prompt = $_POST['custom_prompt'] ?? '';
    
    if (!empty($prompt_key)) {
        if (trim($custom_prompt) === '') {
            $custom_prompt = null;
        }
        
        $query = "
            UPDATE $schema.prompts 
            SET custom_prompt = $1, 
                updated_at = CURRENT_TIMESTAMP 
            WHERE prompt_key = $2
        ";
        $result = pg_query_params($conn, $query, [$custom_prompt, $prompt_key]);
        
        if ($result) {
            $message = $custom_prompt === null ? "Custom prompt cleared. Using default prompt for <strong>$prompt_key</strong>." : "Custom prompt updated successfully for <strong>$prompt_key</strong>.";
        } else {
            $message = "Error updating prompt: " . pg_last_error($conn);
        }
    } else {
        $message = "No prompt key specified.";
    }
}

/********************************************************************
 *  2) CLEAR CUSTOM PROMPT (REVERT TO DEFAULT)
 ********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_custom') {
    $prompt_key = $_POST['prompt_key'] ?? '';
    
    if (!empty($prompt_key)) {
        $query = "
            UPDATE $schema.prompts 
            SET custom_prompt = NULL, 
                updated_at = CURRENT_TIMESTAMP 
            WHERE prompt_key = $1
        ";
        $result = pg_query_params($conn, $query, [$prompt_key]);
        
        if ($result) {
            $message = "Custom prompt cleared. Reverted to default prompt for <strong>$prompt_key</strong>.";
        } else {
            $message = "Error clearing prompt: " . pg_last_error($conn);
        }
    }
}

?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
    /* Override main container styles */
    main {
        padding: 10px clamp(10px, 2.5vw, 34px) 24px;
        width: 100%;
        margin: 0;
        max-width: 100%;
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

    /* Info boxes */
    .info-box, .warning-box {
        margin-bottom: 25px;
        padding: 20px;
        border-radius: 10px;
        border: 1px solid;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.02);
    }

    .info-box {
        background: linear-gradient(135deg, rgba(100, 149, 237, 0.1), rgba(100, 149, 237, 0.05));
        border-color: rgba(100, 149, 237, 0.3);
    }

    .warning-box {
        background: linear-gradient(135deg, rgba(242, 124, 17, 0.1), rgba(242, 124, 17, 0.05));
        border-color: rgba(242, 124, 17, 0.3);
    }

    .info-box h3, .warning-box h3 {
        margin-top: 0;
        color: rgb(242, 124, 17);
        font-family: 'MagicCards', serif;
    }

    .info-box p, .warning-box p {
        margin: 8px 0;
        color: #d0d0d0;
    }

    /* Table container */
    .table-container {
        background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        border-radius: 10px;
        border: 1px solid #3a3a3a;
        max-height: 600px;
        overflow-y: auto;
        overflow-x: auto;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
    }

    .prompts-search-section {
        background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        border-radius: 10px;
        padding: 12px 14px;
        margin-bottom: 10px;
        border: 1px solid #3a3a3a;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
    }

    .prompts-search-label {
        display: block;
        margin-bottom: 4px;
        color: rgb(242, 124, 17);
        font-family: 'MagicCards', serif;
        font-size: 1.15em;
        letter-spacing: 1px;
        word-spacing: 6px;
    }

    .prompts-search-help {
        color: #b0b0b0;
        margin: 0 0 8px 0;
        font-size: 0.95em;
    }

    .prompts-search-input {
        width: 100%;
        max-width: 540px;
        padding: 10px 12px;
        background: rgba(26, 26, 26, 0.85);
        border: 1px solid #3a3a3a;
        border-radius: 6px;
        color: #e0e0e0;
        font-size: 1em;
        transition: border-color 0.3s ease, box-shadow 0.3s ease, background 0.3s ease;
    }

    .prompts-search-input:focus {
        outline: none;
        border-color: rgb(242, 124, 17);
        box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
        background: rgba(26, 26, 26, 0.95);
    }

    .prompts-search-empty {
        display: none;
        margin-top: 14px;
        padding: 14px 16px;
        border: 1px solid rgba(242, 124, 17, 0.25);
        border-radius: 6px;
        background: rgba(242, 124, 17, 0.06);
        color: #d8d8d8;
    }

    .prompts-search-empty.show {
        display: block;
    }
    
    /* Sticky table header */
    .table-container thead {
        position: sticky;
        top: 0;
        z-index: 10;
    }

    /* Table styles */
    .prompts-table {
        width: 100%;
        border-collapse: collapse;
    }

    .prompts-table thead {
        background: linear-gradient(180deg, rgba(26, 26, 26, 0.95), rgba(20, 20, 20, 0.98));
        border-bottom: 2px solid rgba(242, 124, 17, 0.5);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .prompts-table th {
        padding: 15px 12px;
        text-align: left;
        color: rgb(242, 124, 17);
        font-family: 'MagicCards', serif;
        font-size: 1.1em;
        font-weight: normal;
        letter-spacing: 1px;
    }

    .prompts-table tbody tr {
        border-bottom: 1px solid #3a3a3a;
        transition: background-color 0.2s ease, box-shadow 0.2s ease;
    }

    .prompts-table tbody tr:hover {
        background: rgba(242, 124, 17, 0.05);
        box-shadow: inset 0 0 10px rgba(242, 124, 17, 0.1);
    }

    .prompts-table td {
        padding: 12px;
        color: #e0e0e0;
        vertical-align: top;
    }

    .prompt-key-cell {
        font-family: 'Courier New', monospace;
        color: rgb(100, 149, 237);
        font-size: 0.9em;
        min-width: 180px;
    }

    .prompt-description-cell {
        color: #b0b0b0;
        font-style: italic;
        font-size: 0.9em;
        min-width: 250px;
    }

    .prompt-content-cell {
        max-width: 400px;
    }

    .prompt-preview {
        background: #1a1a1a;
        padding: 10px;
        border-radius: 4px;
        border: 1px solid #3a3a3a;
        font-family: 'Courier New', monospace;
        font-size: 0.85em;
        white-space: pre-wrap;
        max-height: 100px;
        overflow-y: auto;
        color: #ccc;
        line-height: 1.4;
    }

    .prompt-preview.custom {
        border-color: rgb(242, 124, 17);
        background: rgba(242, 124, 17, 0.05);
    }

    .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.8em;
        font-weight: bold;
        text-align: center;
    }

    .status-badge.custom {
        background: rgba(242, 124, 17, 0.2);
        color: rgb(242, 124, 17);
        border: 1px solid rgb(242, 124, 17);
    }

    .status-badge.default {
        background: rgba(100, 149, 237, 0.2);
        color: rgb(100, 149, 237);
        border: 1px solid rgb(100, 149, 237);
    }

    .actions-cell {
        white-space: nowrap;
        text-align: center;
        min-width: 120px;
    }

    .btn {
        padding: 6px 12px;
        border: 1px solid rgba(58, 58, 58, 0.5);
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.85em;
        transition: all 0.2s ease;
        margin: 2px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
    }

    .btn-edit {
        background: linear-gradient(135deg, rgba(100, 149, 237, 0.9), rgba(80, 129, 217, 0.9));
        color: white;
        border-color: rgba(100, 149, 237, 0.3);
    }

    .btn-edit:hover {
        background: linear-gradient(135deg, rgba(80, 129, 217, 1), rgba(60, 109, 197, 1));
        border-color: rgba(100, 149, 237, 0.5);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.25);
    }

    .btn-clear {
        background: linear-gradient(135deg, rgba(242, 124, 17, 0.9), rgba(222, 104, 0, 0.9));
        color: white;
        border-color: rgba(242, 124, 17, 0.3);
    }

    .btn-clear:hover {
        background: linear-gradient(135deg, rgba(222, 104, 0, 1), rgba(202, 84, 0, 1));
        border-color: rgba(242, 124, 17, 0.5);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.25);
    }

    .btn-save {
        background: linear-gradient(135deg, rgba(76, 175, 80, 0.9), rgba(69, 160, 73, 0.9));
        color: white;
        border-color: rgba(76, 175, 80, 0.3);
    }

    .btn-save:hover {
        background: linear-gradient(135deg, rgba(69, 160, 73, 1), rgba(62, 145, 66, 1));
        border-color: rgba(76, 175, 80, 0.5);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.25);
    }

    .btn-cancel {
        background: linear-gradient(135deg, rgba(136, 136, 136, 0.9), rgba(102, 102, 102, 0.9));
        color: white;
        border-color: rgba(136, 136, 136, 0.3);
    }

    .btn-cancel:hover {
        background: linear-gradient(135deg, rgba(102, 102, 102, 1), rgba(85, 85, 85, 1));
        border-color: rgba(136, 136, 136, 0.5);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.25);
    }

    /* Import/Export section */
    .import-export-section {
        background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
        border-radius: 10px;
        padding: 12px 14px;
        margin-bottom: 10px;
        border: 1px solid #3a3a3a;
        display: grid;
        grid-template-columns: minmax(190px, 0.8fr) minmax(320px, 1.2fr) minmax(300px, 1.3fr);
        gap: 14px;
        align-items: start;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }
    
    .import-export-section:hover {
        border-color: rgba(242, 124, 17, 0.3);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25), inset 0 1px rgba(255, 255, 255, 0.05);
    }

    .export-section, .import-section {
        min-width: 0;
    }

    .export-section p, .import-section p {
        color: #b0b0b0;
        margin: 4px 0 8px;
        font-size: 0.9em;
    }

    .export-section > p:first-child,
    .import-section > p:first-child {
        margin-top: 0;
        color: #f27c11;
    }

    .prompt-guidance {
        min-width: 0;
        margin: 0;
        padding: 10px 12px;
        border: 1px solid rgba(100, 149, 237, 0.3);
        border-radius: 8px;
        background: linear-gradient(135deg, rgba(100, 149, 237, 0.1), rgba(100, 149, 237, 0.05));
    }

    .prompt-guidance p {
        margin: 0 0 6px;
        color: #d0d0d0;
        font-size: 0.82em;
        line-height: 1.4;
    }

    .prompt-guidance p:last-child {
        margin-bottom: 0;
    }

    .file-input-wrapper {
        position: relative;
        display: inline-block;
        cursor: pointer;
    }

    .file-input-wrapper input[type="file"] {
        position: absolute;
        left: -9999px;
    }

    .file-input-label {
        display: inline-block;
        padding: 10px 14px;
        background: #4a4a4a;
        color: white;
        border-radius: 4px;
        cursor: pointer;
        transition: background 0.2s ease;
    }

    .file-input-label:hover {
        background: #5a5a5a;
    }

    .selected-file {
        display: inline-block;
        margin-left: 10px;
        color: rgb(242, 124, 17);
        font-size: 0.9em;
    }

    .btn-export {
        background: linear-gradient(135deg, rgba(100, 149, 237, 0.9), rgba(80, 129, 217, 0.9));
        color: white;
        padding: 10px 14px;
        border: 1px solid rgba(100, 149, 237, 0.3);
        border-radius: 6px;
        cursor: pointer;
        font-size: 1em;
        transition: all 0.2s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
        text-decoration: none;
        display: inline-block;
    }

    .btn-export:hover {
        background: linear-gradient(135deg, rgba(80, 129, 217, 1), rgba(60, 109, 197, 1));
        border-color: rgba(100, 149, 237, 0.5);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.25);
    }

    .btn-import {
        background: linear-gradient(135deg, rgba(242, 124, 17, 0.9), rgba(222, 104, 0, 0.9));
        color: white;
        padding: 10px 14px;
        border: 1px solid rgba(242, 124, 17, 0.3);
        border-radius: 6px;
        cursor: pointer;
        font-size: 1em;
        transition: all 0.2s ease;
        margin-top: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
    }

    .btn-import:hover {
        background: linear-gradient(135deg, rgba(222, 104, 0, 1), rgba(202, 84, 0, 1));
        border-color: rgba(242, 124, 17, 0.5);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.25);
    }

    .btn-import:disabled {
        background: linear-gradient(135deg, rgba(102, 102, 102, 0.6), rgba(85, 85, 85, 0.6));
        cursor: not-allowed;
        opacity: 0.5;
        border-color: rgba(102, 102, 102, 0.3);
    }

    /* Modal styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.8);
    }

    .modal-content {
        background: linear-gradient(135deg, rgba(42, 42, 42, 0.98), rgba(34, 34, 34, 0.98));
        margin: 2% auto;
        padding: 0;
        border: 2px solid rgba(242, 124, 17, 0.5);
        border-radius: 10px;
        width: 90%;
        max-width: 1200px;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5), inset 0 1px rgba(255, 255, 255, 0.03);
    }

    .modal-header {
        padding: 20px;
        background: linear-gradient(180deg, rgba(26, 26, 26, 0.95), rgba(20, 20, 20, 0.98));
        border-bottom: 1px solid rgba(242, 124, 17, 0.3);
        border-radius: 8px 8px 0 0;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .modal-header h2 {
        margin: 0;
        color: rgb(242, 124, 17);
        font-family: 'MagicCards', serif;
        font-size: 1.8em;
    }

    .modal-body {
        padding: 20px;
        overflow-y: auto;
        flex: 1;
    }

    .modal-footer {
        padding: 15px 20px;
        background: linear-gradient(180deg, rgba(26, 26, 26, 0.95), rgba(20, 20, 20, 0.98));
        border-top: 1px solid rgba(242, 124, 17, 0.3);
        text-align: right;
        border-radius: 0 0 8px 8px;
        box-shadow: 0 -2px 4px rgba(0, 0, 0, 0.2);
    }

    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        line-height: 20px;
        cursor: pointer;
    }

    .close:hover,
    .close:focus {
        color: rgb(242, 124, 17);
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: rgb(242, 124, 17);
        font-weight: bold;
    }

    .form-control {
        width: 100%;
        padding: 12px;
        background: rgba(26, 26, 26, 0.8);
        border: 1px solid #3a3a3a;
        border-radius: 6px;
        color: #e0e0e0;
        font-family: 'Courier New', monospace;
        font-size: 14px;
        line-height: 1.5;
        transition: border-color 0.3s ease, box-shadow 0.3s ease, background 0.3s ease;
    }

    .form-control:focus {
        outline: none;
        border-color: rgb(242, 124, 17);
        box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
        background: rgba(26, 26, 26, 0.95);
    }

    textarea.form-control {
        min-height: 300px;
        resize: vertical;
    }

    .readonly-content {
        background: linear-gradient(135deg, rgba(37, 37, 37, 0.8), rgba(32, 32, 32, 0.9));
        padding: 15px;
        border-radius: 6px;
        border: 1px solid #3a3a3a;
        font-family: 'Courier New', monospace;
        color: #999;
        white-space: pre-wrap;
        max-height: 200px;
        overflow-y: auto;
        line-height: 1.5;
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    /* Toast notification */
    .toast-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: #4CAF50;
        color: white;
        padding: 15px 25px;
        border-radius: 4px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        z-index: 2000;
        display: none;
        animation: slideIn 0.3s ease;
    }

    .toast-notification.show {
        display: block;
    }

    .toast-notification.error {
        background: #f44336;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    /* Responsive */
    @media (max-width: 1100px) {
        .import-export-section {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .prompt-guidance {
            grid-column: 1 / -1;
        }
    }

    @media (max-width: 768px) {
        .import-export-section {
            grid-template-columns: 1fr;
        }

        .prompt-guidance {
            grid-column: auto;
        }
        
        .prompts-table {
            font-size: 0.9em;
        }
        
        .modal-content {
            width: 95%;
            margin: 5% auto;
        }
    }
</style>

<?php if ($isEmbed): ?>
<style>
    main { padding-top: 10px; }
</style>
<?php endif; ?>

<main>
    <div id="toast" class="toast-notification">
        <span class="message"></span>
    </div>

    <div class="page-header chim-page-head">
        <h1 class="chim-page-head-title">Prompts Manager</h1>
        <p class="page-subtitle chim-page-head-note">Manage system and custom prompts used throughout CHIM</p>
    </div>

    <div class="import-export-section">      
        <div class="export-section">
            <p><strong>📤 Export Custom Prompts</strong></p>
            <p>Download all your custom prompts as a CSV file to share with others.</p>
            <a href="?action=export_custom" class="btn-export">⬇️ Export Custom Prompts</a>
        </div>
        
        <div class="import-section">
            <p><strong>📥 Import Custom Prompts</strong></p>
            <p>Upload a CSV file to import custom prompts shared by others.</p>
            <form method="post" enctype="multipart/form-data" id="importForm">
                <input type="hidden" name="action" value="import_custom">
                <div class="file-input-wrapper">
                    <input type="file" name="csv_file" id="csvFile" accept=".csv" onchange="handleFileSelect(this)">
                    <label for="csvFile" class="file-input-label">📁 Choose CSV File</label>
                    <span class="selected-file" id="selectedFileName"></span>
                </div>
                <br>
                <button type="submit" class="btn-import" id="importBtn" disabled>⬆️ Import Custom Prompts</button>
            </form>
        </div>

        <aside class="prompt-guidance">
            <p><strong>Note:</strong> Recommended for advanced users only. Changing prompts can cause unexpected behavior that may worsen the roleplay experience.</p>
            <p><strong>Default Prompt:</strong> System-maintained baseline that updates with CHIM. <strong>Custom Prompt:</strong> Your personalized override that takes precedence when set.</p>
            <p>Click <strong>Edit</strong> to view and modify prompts. Click <strong>Clear</strong> to revert to default.</p>
        </aside>
    </div>

    <div class="prompts-search-section">
        <label class="prompts-search-label" for="promptsSearch">Search Prompts</label>
        <p class="prompts-search-help">Filter by prompt key, description, status, or preview text.</p>
        <input
            type="text"
            id="promptsSearch"
            class="prompts-search-input"
            placeholder="Search prompts..."
            autocomplete="off"
            oninput="filterPromptsTable(this.value)"
        >
        <div id="promptsSearchEmpty" class="prompts-search-empty">No prompts match your current search.</div>
    </div>

    <?php
    /********************************************************************
     *  3) DISPLAY ALL PROMPTS IN TABLE
     ********************************************************************/
    $query = "
        SELECT prompt_key, default_prompt, custom_prompt, description, 
               created_at, updated_at
        FROM $schema.prompts
        ORDER BY prompt_key ASC
    ";
    $result = pg_query($conn, $query);

    if ($result && pg_num_rows($result) > 0):
    ?>
    
    <div class="table-container">
        <table class="prompts-table">
            <thead>
                <tr>
                    <th>Prompt Key</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Preview</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = pg_fetch_assoc($result)): 
                    $prompt_key = htmlspecialchars($row['prompt_key']);
                    $default_prompt = $row['default_prompt'];
                    $custom_prompt = $row['custom_prompt'];
                    $description = htmlspecialchars($row['description'] ?? '');
                    $is_custom = !empty($custom_prompt);
                    $active_prompt = $is_custom ? $custom_prompt : $default_prompt;
                    $preview = strlen($active_prompt) > 150 ? substr($active_prompt, 0, 150) . '...' : $active_prompt;
                    $search_text = strtolower(
                        $row['prompt_key'] . ' ' .
                        ($row['description'] ?? '') . ' ' .
                        ($is_custom ? 'custom' : 'default') . ' ' .
                        $preview
                    );
                ?>
                <tr data-search-text="<?php echo htmlspecialchars($search_text, ENT_QUOTES); ?>">
                    <td class="prompt-key-cell">
                        <code><?php echo $prompt_key; ?></code>
                    </td>
                    <td class="prompt-description-cell">
                        <?php echo $description; ?>
                    </td>
                    <td>
                        <?php if ($is_custom): ?>
                            <span class="status-badge custom">🎨 Custom</span>
                        <?php else: ?>
                            <span class="status-badge default">📋 Default</span>
                        <?php endif; ?>
                    </td>
                    <td class="prompt-content-cell">
                        <div class="prompt-preview <?php echo $is_custom ? 'custom' : ''; ?>">
                            <?php echo htmlspecialchars($preview); ?>
                        </div>
                    </td>
                    <td class="actions-cell">
                        <button class="btn btn-edit" onclick="openEditModal('<?php echo $prompt_key; ?>')">
                            ✏️ Edit
                        </button>
                        <?php if ($is_custom): ?>
                        <button class="btn btn-clear" onclick="clearCustomPrompt('<?php echo $prompt_key; ?>')">
                            🔄 Clear
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <?php else: ?>
        <div class="warning-box">
            <h3>⚠️ No Prompts Found</h3>
            <p>The prompts table appears to be empty. Please run the database migration to initialize the prompts system.</p>
        </div>
    <?php endif; ?>

</main>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>✏️ Edit Prompt: <span id="modalPromptKey"></span></h2>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>📝 Description & File Location</label>
                <p id="modalDescription" style="color: #b0b0b0;"></p>
            </div>
            
            <div class="form-group">
                <label>📋 Default Prompt (Read-Only)</label>
                <div id="modalDefaultPrompt" class="readonly-content"></div>
            </div>
            
            <div class="form-group">
                <label>🎨 Custom Prompt (Optional - Leave empty to use default)</label>
                <textarea id="modalCustomPrompt" class="form-control" placeholder="Enter your custom prompt here, or leave empty to use the default prompt..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-cancel" onclick="closeEditModal()">Cancel</button>
            <button class="btn btn-save" onclick="savePrompt()">💾 Save Custom Prompt</button>
        </div>
    </div>
</div>

<script>
// Define webRoot for JavaScript
var webRoot = '<?php echo $webRoot; ?>';

// Store prompt data
var promptsData = {};

<?php
// Generate JavaScript data object
$result = pg_query($conn, $query);
if ($result) {
    echo "promptsData = {\n";
    $first = true;
    while ($row = pg_fetch_assoc($result)) {
        if (!$first) echo ",\n";
        $first = false;
        echo "    '" . $row['prompt_key'] . "': {\n";
        echo "        default_prompt: " . json_encode($row['default_prompt']) . ",\n";
        echo "        custom_prompt: " . json_encode($row['custom_prompt']) . ",\n";
        echo "        description: " . json_encode($row['description']) . "\n";
        echo "    }";
    }
    echo "\n};\n";
}
?>

function openEditModal(promptKey) {
    const data = promptsData[promptKey];
    if (!data) return;
    
    document.getElementById('modalPromptKey').textContent = promptKey;
    document.getElementById('modalDescription').innerHTML = data.description || 'No description available.';
    document.getElementById('modalDefaultPrompt').textContent = data.default_prompt;
    
    // Check if this is a JSON prompt (like height_descriptions)
    const isJsonPrompt = promptKey === 'height_descriptions';
    const customPromptValue = data.custom_prompt || '';
    
    if (isJsonPrompt) {
        // Pretty-print JSON for editing
        try {
            const defaultJson = JSON.parse(data.default_prompt);
            document.getElementById('modalDefaultPrompt').textContent = JSON.stringify(defaultJson, null, 2);
            
            if (customPromptValue) {
                const customJson = JSON.parse(customPromptValue);
                document.getElementById('modalCustomPrompt').value = JSON.stringify(customJson, null, 2);
            } else {
                document.getElementById('modalCustomPrompt').value = '';
            }
        } catch (e) {
            // If JSON parsing fails, display as-is
            document.getElementById('modalDefaultPrompt').textContent = data.default_prompt;
            document.getElementById('modalCustomPrompt').value = customPromptValue;
        }
    } else {
        // Regular text prompt
        document.getElementById('modalCustomPrompt').value = customPromptValue;
    }
    
    document.getElementById('editModal').style.display = 'block';
    
    // Store current prompt key
    document.getElementById('editModal').dataset.promptKey = promptKey;
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function savePrompt() {
    const promptKey = document.getElementById('editModal').dataset.promptKey;
    let customPrompt = document.getElementById('modalCustomPrompt').value;
    
    // Validate JSON for height_descriptions
    if (promptKey === 'height_descriptions' && customPrompt.trim() !== '') {
        try {
            // Validate that it's valid JSON
            const parsed = JSON.parse(customPrompt);
            // Minify JSON for storage (remove pretty-printing)
            customPrompt = JSON.stringify(parsed);
        } catch (e) {
            showToast('Invalid JSON format. Please check your syntax.', 'error');
            return;
        }
    }
    
    const formData = new FormData();
    formData.append('action', 'update_custom');
    formData.append('prompt_key', promptKey);
    formData.append('custom_prompt', customPrompt);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Update the local data store
            promptsData[promptKey].custom_prompt = customPrompt || null;
            
            // Update the table row
            updateTableRow(promptKey);
            
            // Close the modal
            closeEditModal();
            
            showToast('Prompt saved successfully!', 'success');
        } else {
            showToast('Error saving prompt: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Save error:', error);
        showToast('Error: ' + error.message, 'error');
    });
}

function updateTableRow(promptKey) {
    const data = promptsData[promptKey];
    const isCustom = !!(data.custom_prompt && data.custom_prompt.trim());
    const activePrompt = isCustom ? data.custom_prompt : data.default_prompt;
    const preview = activePrompt.length > 150 ? activePrompt.substring(0, 150) + '...' : activePrompt;
    
    // Find the table row
    const rows = document.querySelectorAll('.prompts-table tbody tr');
    rows.forEach(row => {
        const codeElement = row.querySelector('.prompt-key-cell code');
        if (codeElement && codeElement.textContent === promptKey) {
            // Update status badge
            const statusCell = row.cells[2];
            if (isCustom) {
                statusCell.innerHTML = '<span class="status-badge custom">🎨 Custom</span>';
            } else {
                statusCell.innerHTML = '<span class="status-badge default">📋 Default</span>';
            }
            
            // Update preview
            const previewCell = row.cells[3];
            const previewDiv = previewCell.querySelector('.prompt-preview');
            if (previewDiv) {
                previewDiv.textContent = preview;
                if (isCustom) {
                    previewDiv.classList.add('custom');
                } else {
                    previewDiv.classList.remove('custom');
                }
            }
            
            // Update actions cell
            const actionsCell = row.cells[4];
            if (isCustom) {
                actionsCell.innerHTML = `
                    <button class="btn btn-edit" onclick="openEditModal('${promptKey}')">
                        ✏️ Edit
                    </button>
                    <button class="btn btn-clear" onclick="clearCustomPrompt('${promptKey}')">
                        🔄 Clear
                    </button>
                `;
            } else {
                actionsCell.innerHTML = `
                    <button class="btn btn-edit" onclick="openEditModal('${promptKey}')">
                        ✏️ Edit
                    </button>
                `;
            }

            row.dataset.searchText = buildPromptSearchText(promptKey, data, isCustom, preview);
        }
    });

    const searchInput = document.getElementById('promptsSearch');
    if (searchInput && searchInput.value.trim() !== '') {
        filterPromptsTable(searchInput.value);
    }
}

function buildPromptSearchText(promptKey, data, isCustom, preview) {
    return [
        promptKey,
        data.description || '',
        isCustom ? 'custom' : 'default',
        preview || ''
    ].join(' ').toLowerCase();
}

function filterPromptsTable(query) {
    const normalizedQuery = (query || '').trim().toLowerCase();
    const rows = document.querySelectorAll('.prompts-table tbody tr[data-search-text]');
    const emptyState = document.getElementById('promptsSearchEmpty');
    let visibleCount = 0;

    rows.forEach(row => {
        const haystack = row.dataset.searchText || '';
        const shouldShow = normalizedQuery === '' || haystack.indexOf(normalizedQuery) !== -1;
        row.style.display = shouldShow ? 'table-row' : 'none';
        if (shouldShow) {
            visibleCount++;
        }
    });

    if (emptyState) {
        emptyState.classList.toggle('show', normalizedQuery !== '' && visibleCount === 0);
    }
}

function clearCustomPrompt(promptKey) {
    if (!confirm('Are you sure you want to clear the custom prompt and revert to the default? This cannot be undone.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'clear_custom');
    formData.append('prompt_key', promptKey);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.text(); // Changed to text since this endpoint doesn't return JSON yet
    })
    .then(() => {
        // Update the local data store
        promptsData[promptKey].custom_prompt = null;
        
        // Update the table row
        updateTableRow(promptKey);
        
        // If modal is open for this prompt, update it
        const modal = document.getElementById('editModal');
        if (modal.style.display === 'block' && modal.dataset.promptKey === promptKey) {
            document.getElementById('modalCustomPrompt').value = '';
        }
        
        showToast('Custom prompt cleared. Reverted to default.', 'success');
    })
    .catch(error => {
        console.error('Clear error:', error);
        showToast('Error: ' + error.message, 'error');
    });
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const messageSpan = toast.querySelector('.message');
    messageSpan.textContent = message;
    
    toast.className = 'toast-notification show';
    if (type === 'error') {
        toast.classList.add('error');
    }
    
    setTimeout(() => {
        toast.classList.remove('show');
        toast.classList.remove('error');
    }, 5000);
}

// Show PHP message if exists
<?php if (!empty($message)): ?>
document.addEventListener('DOMContentLoaded', function() {
    showToast(<?php echo json_encode(strip_tags($message)); ?>);
});
<?php endif; ?>

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditModal();
    }
});

// Handle file selection for import
function handleFileSelect(input) {
    const fileNameSpan = document.getElementById('selectedFileName');
    const importBtn = document.getElementById('importBtn');
    
    if (input.files && input.files[0]) {
        fileNameSpan.textContent = input.files[0].name;
        importBtn.disabled = false;
    } else {
        fileNameSpan.textContent = '';
        importBtn.disabled = true;
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
