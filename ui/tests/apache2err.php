<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');

require_once(__DIR__.DIRECTORY_SEPARATOR."../profile_loader.php");

$TITLE = "🌲 CHIM Server Logs";

ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/head.html");

$debugPaneLink = false;
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/navbar.php");

$logPath = __DIR__ . '/../../log/';
$distroLogPath = $logPath . 'apache_error.log';
$llmOutputPath = $logPath . 'output_from_llm.log';
$llmContextPath = $logPath . 'context_sent_to_llm.log';
$pluginOutputPath = $logPath . 'ouput_to_plugin.log';
$sttLogPath = $logPath . 'stt.log';
$visionLogPath = $logPath . 'vision.log';
$debugStreamLogPath = $logPath . 'debugstream.log';

// Function to read and filter the error log from a given path
function readErrorLog($errorLogPath, $logType) {
    if (file_exists($errorLogPath) && is_readable($errorLogPath)) {
        $errorLog = file($errorLogPath);
        $errorLog = array_reverse($errorLog);

        echo '<div class="section-header">';
        echo "<h2>$logType</h2>";
        echo '<button class="expand-button" onclick="openModal(\'errorLogModal\', \'errorLogContainer\')">';
        echo '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>';
        echo '</button>';
        echo '</div>';
        echo '<div class="search-container">';
        echo '<input type="text" class="search-input" placeholder="Search in Apache Error Log..." data-target="errorLogContainer">';
        echo '</div>';
        echo '<div class="log-container" id="errorLogContainer">';
        
        foreach ($errorLog as $line) {
            if (strpos($line, '[php:error]') !== false && stripos($line, 'warning') === false) {
                // Extract timestamp if it exists
                $timestamp = '';
                if (preg_match('/\[(.*?)\]/', $line, $matches)) {
                    $timestamp = $matches[1];
                    try {
                        $date = new DateTime($timestamp);
                        $timestamp = $date->format('Y-m-d H:i:s');
                    } catch (Exception $e) {
                        // Keep original timestamp if parsing fails
                    }
                }

                // Format the log entry
                $logEntry = '<div class="log-entry error-entry">';
                if ($timestamp) {
                    $logEntry .= '<div class="timestamp">' . htmlspecialchars($timestamp) . '</div>';
                }
                
                $message = preg_replace('/\[(.*?)\]/', '', $line);
                $message = trim($message);
                
                $logEntry .= '<div class="error-message">' . htmlspecialchars($message) . '</div>';
                $logEntry .= '</div>';
                
                echo $logEntry;
            }
        }
        echo '</div>';
    } else {
        echo '<p class="error-message">Error log file not found or not readable at: ' . htmlspecialchars($errorLogPath) . '</p>';
    }
}

// Function to read regular log files
function readRegularLog($logPath, $logName) {
    if (file_exists($logPath) && is_readable($logPath)) {
        $log = file_get_contents($logPath);
        $sanitizedId = sanitizeId($logName);

        echo '<div class="section-header">';
        echo "<h2>$logName</h2>";
        echo '<button class="expand-button" onclick="openModal(\'' . $sanitizedId . 'Modal\', \'' . $sanitizedId . 'Container\')">';
        echo '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>';
        echo 'Expand';
        echo '</button>';
        echo '</div>';
        echo '<div class="search-container">';
        echo '<input type="text" class="search-input" placeholder="Search in ' . htmlspecialchars($logName) . '..." data-target="' . $sanitizedId . 'Container">';
        echo '</div>';
        echo '<div class="log-container" id="' . $sanitizedId . 'Container">';
        echo '<div class="log-entry regular-entry">';
        
        if (strpos($logName, 'LLM Context') !== false) {
            echo '<pre class="log-content">' . htmlspecialchars($log) . '</pre>';
        } else {
            echo '<pre class="log-content">' . htmlspecialchars($log) . '</pre>';
        }
        
        echo '</div>';
        echo '</div>';
    } else {
        echo '<p class="error-message">Log file not found or not readable at: ' . htmlspecialchars($logPath) . '</p>';
    }
}

// Helper function to create valid IDs from log names
function sanitizeId($name) {
    return preg_replace('/[^a-zA-Z0-9]/', '', $name);
}

// Function to create and download zip of all logs
function createLogsZip() {
    $logPath = realpath(__DIR__ . '/../../log/');
    $zipName = 'CHIM-Logs.zip';
    $zipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zipName;

    // Create new zip archive
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        error_log("Failed to create zip file");
        return false;
    }

    // Add all .log files from the log directory
    $files = glob($logPath . DIRECTORY_SEPARATOR . '*.log');
    if (empty($files)) {
        error_log("No log files found in " . $logPath);
        $zip->close();
        return false;
    }

    $addedFiles = 0;
    foreach ($files as $file) {
        if (is_readable($file)) {
            $relativePath = basename($file);
            if ($zip->addFile($file, $relativePath)) {
                $addedFiles++;
            } else {
                error_log("Failed to add file to zip: " . $file);
            }
        } else {
            error_log("File not readable: " . $file);
        }
    }

    $zip->close();

    // Check if we actually added any files
    if ($addedFiles === 0) {
        error_log("No files were added to the zip");
        if (file_exists($zipPath)) {
            unlink($zipPath);
        }
        return false;
    }

    // Verify the zip file exists and is readable
    if (!file_exists($zipPath) || !is_readable($zipPath)) {
        error_log("Created zip file is not accessible");
        return false;
    }

    // Send the file to the browser
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipName . '"');
    header('Content-Length: ' . filesize($zipPath));
    header('Pragma: public');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');

    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Read file in chunks to handle large files
    if ($fp = fopen($zipPath, 'rb')) {
        while (!feof($fp)) {
            echo fread($fp, 8192);
            flush();
        }
        fclose($fp);
        unlink($zipPath); // Delete the temporary zip file
        return true;
    }

    return false;
}

// Handle download request with error handling
if (isset($_GET['download_logs'])) {
    if (!createLogsZip()) {
        header('HTTP/1.1 500 Internal Server Error');
        echo "Failed to create zip file. Please check the server logs for details.";
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $TITLE; ?></title>
    <link rel="icon" type="image/x-icon" href="../images/favicon.ico">
    <link rel="stylesheet" href="../css/main.css">
    <style>
        /* Override main container styles */
        main {
            padding-top: 160px;
            padding-bottom: 40px;
            padding-left: 10px;
            padding-right: 10px;
            width: 100%;
            box-sizing: border-box;
            overflow-x: hidden;
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

        /* Updated CSS for Dark Grey Background Theme */
        body {
            font-family: Arial, sans-serif;
            background-color: #2c2c2c;
            color: #f8f9fa;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        h1, h2 {
            color: #ffffff;
        }

        .grid-container {
            display: grid;
            gap: 10px;
            width: 100%;
            margin: 0 auto;
            box-sizing: border-box;
            grid-template-columns: repeat(3, 1fr);
        }

        .log-section {
            border: 1px solid #444;
            border-radius: 8px;
            padding: 15px;
            background-color: #2a2a2a;
            display: flex;
            flex-direction: column;
            min-width: 0;
            position: relative;
            resize: both;
            overflow: auto;
            min-height: 300px;
            min-width: 300px;
        }

        .log-section::after {
            content: '';
            position: absolute;
            right: 0;
            bottom: 0;
            width: 15px;
            height: 15px;
            cursor: se-resize;
            background: linear-gradient(135deg, transparent 50%, #444 50%);
        }

        h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #444;
            font-size: 1.2em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .log-container {
            overflow-y: auto;
            overflow-x: hidden;
            background-color: #1a1a1a;
            color: #f8f9fa;
            font-size: 13px;
            padding: 10px;
            border: 1px solid #555555;
            border-radius: 5px;
            height: 600px;
            max-height: 600px;
            width: 100%;
            box-sizing: border-box;
        }

        .log-entry {
            padding: 8px;
            margin-bottom: 8px;
            border-radius: 4px;
            background-color: #2c2c2c;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .log-entry.error-entry {
            border-left: 4px solid #dc3545;
        }

        .log-entry.regular-entry {
            border-left: 4px solid #17a2b8;
        }

        .timestamp {
            color: #888;
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .error-message {
            color: #dc3545;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .log-content {
            font-family: monospace;
            white-space: pre-wrap;
            line-height: 1.4;
            margin: 0;
            color: #f8f9fa;
            height: 100%;
            word-wrap: break-word;
            overflow-wrap: break-word;
            font-size: 13px;
        }

        @media (max-width: 1200px) {
            .grid-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .grid-container {
                grid-template-columns: 1fr;
            }
        }

        /* Loading overlay styles */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .loading-content {
            background-color: #2a2a2a;
            padding: 20px 40px;
            border-radius: 8px;
            border: 1px solid #444;
            text-align: center;
        }

        .loading-spinner {
            border: 4px solid #444;
            border-top: 4px solid #17a2b8;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-text {
            color: #f8f9fa;
            font-size: 16px;
            margin: 0;
        }

        /* Hide content initially */
        .grid-container {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .grid-container.loaded {
            opacity: 1;
        }

        /* Refresh button styles */
        .refresh-button {
            display: inline-flex;
            align-items: center;
            background-color: #17a2b8;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 16px;
            margin-left: 15px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s;
        }

        .refresh-button:hover {
            background-color: #138496;
        }

        .refresh-button svg {
            margin-right: 8px;
        }

        .refresh-button.refreshing {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* Title container for flex layout */
        .title-container {
            display: flex;
            align-items: center;
        }

        /* Search bar styles */
        .search-container {
            margin: 10px 0;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .search-input {
            flex: 1;
            padding: 8px;
            border: 1px solid #444;
            border-radius: 4px;
            background-color: #1a1a1a;
            color: #f8f9fa;
            font-family: monospace;
            font-size: 14px;
        }

        .search-input:focus {
            outline: none;
            border-color: #17a2b8;
        }

        .regex-toggle {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #f8f9fa;
            font-size: 0.9em;
        }

        .regex-toggle input[type="checkbox"] {
            margin: 0;
        }

        .no-results {
            color: #888;
            text-align: center;
            padding: 20px;
            font-style: italic;
        }

        .highlight {
            background-color: #17a2b8;
            color: #ffffff;
            padding: 0 2px;
            border-radius: 2px;
        }

        /* Grid layout controls */
        .grid-controls {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            align-items: center;
        }

        .grid-controls select {
            padding: 8px;
            border: 1px solid #444;
            border-radius: 4px;
            background-color: #1a1a1a;
            color: #f8f9fa;
            font-size: 14px;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            overflow-y: auto;
            padding-top: 160px;
            padding-bottom: 40px;
        }

        .modal-content {
            position: relative;
            background-color: #2a2a2a;
            margin: 0 auto;
            padding: 20px;
            width: 95%;
            max-width: 1600px;
            border-radius: 8px;
            border: 1px solid #444;
            max-height: calc(100vh - 200px);
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #444;
        }

        .modal-title {
            margin: 0;
            font-size: 1.5em;
            color: #ffffff;
        }

        .close-modal {
            background: none;
            border: none;
            color: #f8f9fa;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }

        .close-modal:hover {
            background-color: #444;
        }

        .modal-search-container {
            margin: 0 0 15px 0;
            padding: 10px;
            background-color: #1a1a1a;
            border-radius: 4px;
            border: 1px solid #555555;
        }

        .modal-search-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #444;
            border-radius: 4px;
            background-color: #1a1a1a;
            color: #f8f9fa;
            font-family: monospace;
            font-size: 14px;
        }

        .modal-search-input:focus {
            outline: none;
            border-color: #17a2b8;
        }

        .modal-body {
            background-color: #1a1a1a;
            padding: 15px;
            border-radius: 4px;
            border: 1px solid #555555;
            overflow-y: auto;
            flex: 1;
            min-height: 0;
        }

        .expand-button {
            background: none;
            border: none;
            color: #17a2b8;
            cursor: pointer;
            padding: 4px 8px;
            margin-left: 10px;
            display: flex;
            align-items: center;
            border-radius: 4px;
        }

        .expand-button:hover {
            background-color: #444;
        }

        .expand-button svg {
            width: 16px;
            height: 16px;
            margin-right: 4px;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
    </style>
</head>
<body>
<main>
    <!-- Loading overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <p class="loading-text">Loading logs...</p>
        </div>
    </div>

<div class="indent5">
    <div class="title-container">
        <h1>🌲 CHIM Server Logs</h1>
        <button class="refresh-button" id="refreshLogs">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path d="M8 3a5 5 0 0 0-5 5H1l3.5 3.5L8 8H6a2 2 0 1 1 2 2v2a4 4 0 1 0-4-4H2a6 6 0 1 1 6 6v-2a4 4 0 0 0 0-8z"/>
            </svg>
            Refresh Logs
        </button>
        <button class="refresh-button" id="downloadLogs" style="margin-left: 10px;">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path d="M8 0a1 1 0 0 1 1 1v6h2.586l-2.293 2.293a1 1 0 0 1-1.414 0L5.586 7H8V1a1 1 0 0 1 1-1zM4 11h8a2 2 0 0 1 2 2v1a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-1a2 2 0 0 1 2-2z"/>
            </svg>
            Download All Logs
        </button>
    </div>
    <h2>Logs can be found in the /log folder of the CHIM server. <a href="/HerikaServer/log" target="_blank">View the log folder.</a></h2>

    <div class="grid-container" id="logGrid">
        <div class="log-section">
            <?php
            // Display Apache error log
            readErrorLog($distroLogPath, "Apache Error Log (apache_error.log)");
            ?>
        </div>

        <div class="log-section">
            <?php
            // Display LLM output log
            readRegularLog($llmOutputPath, "LLM Output (output_from_llm.log)");
            ?>
        </div>

        <div class="log-section">
            <?php
            // Display LLM context log
            readRegularLog($llmContextPath, "LLM Context (context_sent_to_llm.log)");
            ?>
        </div>

        <div class="log-section">
            <?php
            // Display plugin output log
            readRegularLog($pluginOutputPath, "Plugin Output (ouput_to_plugin.log)");
            ?>
        </div>

        <div class="log-section">
            <?php
            // Display STT log
            readRegularLog($sttLogPath, "Speech-to-Text Log (stt.log)");
            ?>
        </div>

        <div class="log-section">
            <?php
            // Display Vision log
            readRegularLog($visionLogPath, "Vision Log (vision.log)");
            ?>
        </div>

        <div class="log-section">
            <?php
            // Display Debug Stream log
            readRegularLog($debugStreamLogPath, "Debug Stream Log (debugstream.log)");
            ?>
        </div>
    </div>
</div>
</main>

<!-- Modals -->
<div id="errorLogModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Apache Error Log</h2>
            <button class="close-modal" onclick="closeModal('errorLogModal')">&times;</button>
        </div>
        <div class="modal-search-container">
            <input type="text" class="modal-search-input" placeholder="Search in Apache Error Log..." data-target="errorLogModalContent">
        </div>
        <div class="modal-body">
            <div id="errorLogModalContent"></div>
        </div>
    </div>
</div>

<div id="LLMOutputoutputfromllmlogModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">LLM Output Log</h2>
            <button class="close-modal" onclick="closeModal('LLMOutputoutputfromllmlogModal')">&times;</button>
        </div>
        <div class="modal-search-container">
            <input type="text" class="modal-search-input" placeholder="Search in LLM Output Log..." data-target="LLMOutputoutputfromllmlogModalContent">
        </div>
        <div class="modal-body">
            <div id="LLMOutputoutputfromllmlogModalContent"></div>
        </div>
    </div>
</div>

<div id="LLMContextcontextsenttollmlogModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">LLM Context Log</h2>
            <button class="close-modal" onclick="closeModal('LLMContextcontextsenttollmlogModal')">&times;</button>
        </div>
        <div class="modal-search-container">
            <input type="text" class="modal-search-input" placeholder="Search in LLM Context Log..." data-target="LLMContextcontextsenttollmlogModalContent">
        </div>
        <div class="modal-body">
            <div id="LLMContextcontextsenttollmlogModalContent"></div>
        </div>
    </div>
</div>

<div id="PluginOutputouputtopluginlogModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Plugin Output Log</h2>
            <button class="close-modal" onclick="closeModal('PluginOutputouputtopluginlogModal')">&times;</button>
        </div>
        <div class="modal-search-container">
            <input type="text" class="modal-search-input" placeholder="Search in Plugin Output Log..." data-target="PluginOutputouputtopluginlogModalContent">
        </div>
        <div class="modal-body">
            <div id="PluginOutputouputtopluginlogModalContent"></div>
        </div>
    </div>
</div>

<div id="SpeechtoTextLogsttlogModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Speech-to-Text Log</h2>
            <button class="close-modal" onclick="closeModal('SpeechtoTextLogsttlogModal')">&times;</button>
        </div>
        <div class="modal-search-container">
            <input type="text" class="modal-search-input" placeholder="Search in Speech-to-Text Log..." data-target="SpeechtoTextLogsttlogModalContent">
        </div>
        <div class="modal-body">
            <div id="SpeechtoTextLogsttlogModalContent"></div>
        </div>
    </div>
</div>

<div id="VisionLogvisionlogModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Vision Log</h2>
            <button class="close-modal" onclick="closeModal('VisionLogvisionlogModal')">&times;</button>
        </div>
        <div class="modal-search-container">
            <input type="text" class="modal-search-input" placeholder="Search in Vision Log..." data-target="VisionLogvisionlogModalContent">
        </div>
        <div class="modal-body">
            <div id="VisionLogvisionlogModalContent"></div>
        </div>
    </div>
</div>

<div id="DebugStreamLogdebugstreamlogModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Debug Stream Log</h2>
            <button class="close-modal" onclick="closeModal('DebugStreamLogdebugstreamlogModal')">&times;</button>
        </div>
        <div class="modal-search-container">
            <input type="text" class="modal-search-input" placeholder="Search in Debug Stream Log..." data-target="DebugStreamLogdebugstreamlogModalContent">
        </div>
        <div class="modal-body">
            <div id="DebugStreamLogdebugstreamlogModalContent"></div>
        </div>
    </div>
</div>

<script>
// Hide loading overlay and show content when everything is loaded
window.addEventListener('load', function() {
    // Small delay to ensure logs are rendered
    setTimeout(function() {
        document.getElementById('loadingOverlay').style.display = 'none';
        document.getElementById('logGrid').classList.add('loaded');
    }, 500);
});

// Function to refresh logs via AJAX
function refreshLogs() {
    const refreshButton = document.getElementById('refreshLogs');
    const logContainers = document.querySelectorAll('.log-container');
    const loadingOverlay = document.getElementById('loadingOverlay');
    
    // Prevent multiple refreshes
    if (refreshButton.classList.contains('refreshing')) {
        return;
    }
    
    // Add refreshing state and show loading overlay
    refreshButton.classList.add('refreshing');
    loadingOverlay.style.display = 'flex';
    
    // Make AJAX request to current page
    fetch(window.location.href)
        .then(response => response.text())
        .then(html => {
            // Create a temporary element to parse the HTML
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // Update each log container
            logContainers.forEach(container => {
                const containerId = container.id;
                const newContainer = doc.getElementById(containerId);
                if (newContainer) {
                    container.innerHTML = newContainer.innerHTML;
                }
            });
        })
        .catch(error => {
            console.error('Error refreshing logs:', error);
            alert('Failed to refresh logs. Please try again.');
        })
        .finally(() => {
            // Remove refreshing state and hide loading overlay
            refreshButton.classList.remove('refreshing');
            loadingOverlay.style.display = 'none';
        });
}

// Add click event listener to refresh button
document.getElementById('refreshLogs').addEventListener('click', refreshLogs);

// Add click event listener to download button
document.getElementById('downloadLogs').addEventListener('click', function() {
    window.location.href = window.location.pathname + '?download_logs=1';
});

// Search functionality
document.querySelectorAll('.search-input').forEach(input => {
    const targetId = input.getAttribute('data-target');
    const container = document.getElementById(targetId);
    let originalContent = '';

    // Store original content when the page loads
    if (container) {
        originalContent = container.innerHTML;
    }

    function performSearch() {
        if (!container) return;

        const searchTerm = input.value.trim();
        if (!searchTerm) {
            container.innerHTML = originalContent;
            return;
        }

        let regex;
        try {
            // Try to detect if the search term is a regex pattern
            const regexPattern = /^\/.+\/[gimuy]*$/;
            if (regexPattern.test(searchTerm)) {
                // If it looks like a regex pattern (starts and ends with /), use it directly
                const parts = searchTerm.split('/');
                const flags = parts.pop();
                const pattern = parts.slice(1).join('/');
                regex = new RegExp(pattern, flags);
            } else {
                // Otherwise, escape special characters and use as plain text
                regex = new RegExp(searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
            }
        } catch (e) {
            console.error('Invalid regex:', e);
            return;
        }

        const content = container.textContent;
        const matches = content.match(regex);
        
        if (!matches) {
            container.innerHTML = '<div class="no-results">No matches found</div>';
            return;
        }

        const highlightedContent = content.replace(regex, match => `<span class="highlight">${match}</span>`);
        container.innerHTML = `<pre class="log-content">${highlightedContent}</pre>`;
    }

    // Add event listener
    input.addEventListener('input', performSearch);
});

// Modal functionality
function openModal(modalId, sourceId) {
    const modal = document.getElementById(modalId);
    const contentId = modalId + 'Content';
    const content = document.getElementById(contentId);
    const sourceContainer = document.getElementById(sourceId);
    
    if (sourceContainer && modal) {
        content.innerHTML = sourceContainer.innerHTML;
        modal.style.display = 'block';
        
        // Initialize search functionality for the modal
        const modalSearchInput = modal.querySelector('.modal-search-input');
        if (modalSearchInput) {
            const originalContent = content.innerHTML;
            
            modalSearchInput.addEventListener('input', function() {
                const searchTerm = this.value.trim();
                if (!searchTerm) {
                    content.innerHTML = originalContent;
                    return;
                }

                let regex;
                try {
                    const regexPattern = /^\/.+\/[gimuy]*$/;
                    if (regexPattern.test(searchTerm)) {
                        const parts = searchTerm.split('/');
                        const flags = parts.pop();
                        const pattern = parts.slice(1).join('/');
                        regex = new RegExp(pattern, flags);
                    } else {
                        regex = new RegExp(searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
                    }
                } catch (e) {
                    console.error('Invalid regex:', e);
                    return;
                }

                const textContent = content.textContent;
                const matches = textContent.match(regex);
                
                if (!matches) {
                    content.innerHTML = '<div class="no-results">No matches found</div>';
                    return;
                }

                const highlightedContent = textContent.replace(regex, match => `<span class="highlight">${match}</span>`);
                content.innerHTML = `<pre class="log-content">${highlightedContent}</pre>`;
            });
        }
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.style.display = 'none';
        });
    }
});

// Add data-modal-target attributes to log containers
document.querySelectorAll('.log-container').forEach(container => {
    const modalId = container.id.replace('Container', 'Modal');
    container.setAttribute('data-modal-target', modalId);
});
</script>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
</body>
</html>
