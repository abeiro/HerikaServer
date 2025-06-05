<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');

require_once(__DIR__.DIRECTORY_SEPARATOR."../profile_loader.php");
require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."logger.php");

// Database connection details
$host = 'localhost';
$port = '5432';
$dbname = 'dwemer';
$schema = 'public';
$username = 'dwemer';
$password = 'dwemer';

// Connect to the database
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");

$TITLE = "🌲 CHIM Server Logs";

ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/head.html");

$debugPaneLink = false;
include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/navbar.php");

$logPath = __DIR__ . '/../../log/';
$distroLogPath = $logPath . 'apache_error.log';
$chimLogPath = $logPath . 'chim.log';
$llmOutputPath = $logPath . 'output_from_llm.log';
$llmContextPath = $logPath . 'context_sent_to_llm.log';
$pluginOutputPath = $logPath . 'ouput_to_plugin.log';
$sttLogPath = $logPath . 'stt.log';
$visionLogPath = $logPath . 'vision.log';
$debugStreamLogPath = $logPath . 'debugstream.log';

// Function to get the last N lines of a file
function tail($filepath, $lines = 2000) {
    $file = @fopen($filepath, "r");
    if (!$file) {
        return [];
    }

    $buffer = 4096;
    $output = [];
    $chunk = "";

    fseek($file, -1, SEEK_END);
    $pos = ftell($file);

    while ($pos > 0 && count($output) < $lines) {
        $len = min($pos, $buffer);
        $pos -= $len;
        fseek($file, $pos);
        $chunk = fread($file, $len) . $chunk;
        
        while (($nl = strrpos($chunk, "\n")) !== false && count($output) < $lines) {
            array_unshift($output, substr($chunk, $nl + 1));
            $chunk = substr($chunk, 0, $nl);
        }
    }

    if ($chunk !== "" && count($output) < $lines) {
        array_unshift($output, $chunk);
    }

    fclose($file);
    // Return the last N lines (already in reverse order - newest first)
    return array_slice($output, 0, $lines);
}

// Function to read regular log files
function readRegularLog($logPath, $logName) {
    if (file_exists($logPath) && is_readable($logPath)) {
        $log = tail($logPath, 2000); // Get last 2000 lines
        $sanitizedId = sanitizeId($logName);

        echo '<div class="section-header">';
        echo "<h2>$logName</h2>";
        echo '<button class="expand-button" onclick="openModal(\'' . $sanitizedId . 'Modal\', \'' . $sanitizedId . 'Container\')">';
        echo '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>';
        echo '</button>';
        echo '</div>';
        echo '<div class="search-container">';
        echo '<input type="text" class="search-input" placeholder="Search in ' . htmlspecialchars($logName) . '..." data-target="' . $sanitizedId . 'Container">';
        echo '</div>';
        echo '<div class="log-container" id="' . $sanitizedId . 'Container">';

        $entries = [];
        foreach ($log as $line) {
            if (preg_match('/^\[(.*?)\]\s+\[(.*?)\](.*)$/', $line, $matches)) {
                $timestamp = $matches[1];
                $level = strtolower(trim($matches[2]));
                $message = trim($matches[3]);

                $entries[] = [
                    'timestamp' => $timestamp,
                    'level' => $level,
                    'message' => $message,
                    'raw_time' => strtotime($timestamp)
                ];
            } else {
                // For lines that don't match the expected format
                $entries[] = [
                    'message' => $line,
                    'raw_time' => 0  // Default timestamp for sorting
                ];
            }
        }

        // Sort entries by timestamp in descending order (newest first)
        usort($entries, function($a, $b) {
            return $b['raw_time'] - $a['raw_time'];
        });

        foreach ($entries as $entry) {
            $levelClass = '';
            if (isset($entry['level'])) {
                switch ($entry['level']) {
                    case 'error':
                        $levelClass = 'error-level';
                        break;
                    case 'warn':
                    case 'warning':
                        $levelClass = 'warn-level';
                        break;
                    case 'info':
                        $levelClass = 'info-level';
                        break;
                    case 'debug':
                        $levelClass = 'debug-level';
                        break;
                    case 'trace':
                        $levelClass = 'trace-level';
                        break;
                }

                echo '<div class="log-entry ' . $levelClass . '">';
                echo '<div class="timestamp">' . htmlspecialchars($entry['timestamp']) . '</div>';
                echo '<div class="log-level">' . htmlspecialchars($entry['level']) . '</div>';
                echo '<div class="log-message">' . htmlspecialchars($entry['message']) . '</div>';
                echo '</div>';
            } else {
                echo '<div class="log-entry">';
                echo '<div class="log-message">' . htmlspecialchars($entry['message']) . '</div>';
                echo '</div>';
            }
        }

        echo '</div>';
    } else {
        echo '<p class="error-message">Log file not generated yet for: ' . htmlspecialchars($logPath) . '</p>';
    }
}

// Function to read LLM output logs with timestamp grouping
function readLLMOutputLog($logPath, $logName) {
    if (file_exists($logPath) && is_readable($logPath)) {
        $log = tail($logPath, 2000); // Ensure we're getting 2000 lines
        $sanitizedId = sanitizeId($logName);

        echo '<div class="section-header">';
        echo "<h2>$logName</h2>";
        echo '<button class="expand-button" onclick="openModal(\'' . $sanitizedId . 'Modal\', \'' . $sanitizedId . 'Container\')">';
        echo '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>';
        echo '</button>';
        echo '</div>';
        echo '<div class="search-container">';
        echo '<input type="text" class="search-input" placeholder="Search in ' . htmlspecialchars($logName) . '..." data-target="' . $sanitizedId . 'Container">';
        echo '</div>';
        echo '<div class="log-container" id="' . $sanitizedId . 'Container">';

        $currentBlock = [];
        $inBlock = false;
        $blocks = [];

        foreach ($log as $line) {
            $line = trim($line);
            
            // Check for timestamp block start
            if (preg_match('/^(?:==\s+)?(\d{4}-\d{2}-\d{2}T[\d:]+\+\d{2}:\d{2})\s+START$/', $line, $matches)) {
                if ($inBlock && !empty($currentBlock)) {
                    $blocks[] = $currentBlock;
                }
                $currentBlock = ['start_time' => $matches[1], 'content' => []];
                $inBlock = true;
                continue;
            }
            
            // Check for end timestamp
            if (preg_match('/^(\d{4}-\d{2}-\d{2}T[\d:]+\+\d{2}:\d{2})\s+END$/', $line, $matches)) {
                if ($inBlock && !empty($currentBlock)) {
                    $currentBlock['end_time'] = $matches[1];
                    $blocks[] = $currentBlock;
                }
                $inBlock = false;
                $currentBlock = [];
                continue;
            }
            
            // Skip the == markers
            if ($line === '==' || empty($line)) {
                continue;
            }
            
            // Add content to current block
            if ($inBlock && !empty($line)) {
                $currentBlock['content'][] = $line;
            }
        }
        
        // Add any remaining block
        if ($inBlock && !empty($currentBlock)) {
            $blocks[] = $currentBlock;
        }

        // Output blocks in reverse order (newest first)
        foreach (array_reverse($blocks) as $block) {
            outputLLMBlock($block);
        }

        echo '</div>';
    } else {
        echo '<p class="error-message">Log file not generated yet for: ' . htmlspecialchars($logPath) . '</p>';
    }
}

// Helper function to output an LLM block
function outputLLMBlock($block) {
    if (empty($block) || empty($block['content'])) return;
    
    echo '<div class="log-entry llm-block">';
    echo '<div class="timestamp">';
    echo '<span class="time-label">Start:</span> ' . htmlspecialchars($block['start_time']);
    if (isset($block['end_time'])) {
        echo ' <span class="time-separator">→</span> ';
        echo '<span class="time-label">End:</span> ' . htmlspecialchars($block['end_time']);
    }
    echo '<span class="copy-llm-btn" title="Copy to clipboard">📋</span>';
    echo '</div>';
    echo '<div class="log-message">';
    foreach ($block['content'] as $line) {
        if (trim($line) !== '') {
            echo '<div class="llm-content">' . htmlspecialchars($line) . '</div>';
        }
    }
    echo '</div>';
    echo '</div>';
}

// Function to read LLM context logs with timestamp grouping
function readLLMContextLog($logPath, $logName) {
    if (file_exists($logPath) && is_readable($logPath)) {
        $log = tail($logPath, 2000); // Get last 2000 lines
        $sanitizedId = sanitizeId($logName);

        echo '<div class="section-header">';
        echo "<h2>$logName</h2>";
        echo '<button class="expand-button" onclick="openModal(\'' . $sanitizedId . 'Modal\', \'' . $sanitizedId . 'Container\')">';
        echo '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>';
        echo '</button>';
        echo '</div>';
        echo '<div class="search-container">';
        echo '<input type="text" class="search-input" placeholder="Search in ' . htmlspecialchars($logName) . '..." data-target="' . $sanitizedId . 'Container">';
        echo '</div>';
        echo '<div class="log-container" id="' . $sanitizedId . 'Container">';

        $blocks = [];
        $currentBlock = null;
        $currentContent = '';
        $lastTimestamp = null;
        $tempBlock = [];

        // First pass: collect all blocks
        foreach ($log as $line) {
            $line = rtrim($line);
            
            if ($line === '=') {
                if ($currentBlock && !empty($currentContent)) {
                    $currentBlock['content'] = $currentContent;
                    $tempBlock[] = $currentBlock;
                    $currentContent = '';
                }
                continue;
            }
            
            if (preg_match('/^\d{4}-\d{2}-\d{2}T[\d:]+\+\d{2}:\d{2}$/', $line)) {
                if ($lastTimestamp !== $line) {
                    if ($currentBlock && !empty($currentContent)) {
                        $currentBlock['content'] = $currentContent;
                        $tempBlock[] = $currentBlock;
                    }
                    $currentBlock = ['timestamp' => $line];
                    $currentContent = '';
                    $lastTimestamp = $line;
                }
                continue;
            }
            
            if ($currentBlock && !empty($line)) {
                $currentContent .= $line . "\n";
            }
        }
        
        // Add final block if exists
        if ($currentBlock && !empty($currentContent)) {
            $currentBlock['content'] = $currentContent;
            $tempBlock[] = $currentBlock;
        }

        // Sort blocks by timestamp in descending order (newest first)
        usort($tempBlock, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        // Output sorted blocks
        foreach ($tempBlock as $block) {
            outputLLMContextBlock($block);
        }

        echo '</div>';
    } else {
        echo '<p class="error-message">Log file not found or not readable at: ' . htmlspecialchars($logPath) . '</p>';
    }
}

// Helper function to output an LLM context block
function outputLLMContextBlock($block) {
    if (empty($block) || empty($block['content'])) return;
    
    echo '<div class="log-entry llm-block">';
    echo '<div class="timestamp">';
    echo '<span class="time-label">Time:</span> ' . htmlspecialchars($block['timestamp']);
    echo '<span class="copy-llm-btn" title="Copy to clipboard">📋</span>';
    echo '</div>';
    echo '<div class="log-message">';
    
    // Format the content without syntax highlighting
    $content = trim($block['content']);
    echo '<pre class="llm-content">' . htmlspecialchars($content) . '</pre>';
    
    echo '</div>';
    echo '</div>';
}

// Function to read and filter the error log from a given path
function readErrorLog($errorLogPath, $logType, $showAllEntries = false) {
    if (file_exists($errorLogPath) && is_readable($errorLogPath)) {
        $errorLog = tail($errorLogPath, 2000); // Get last 2000 lines

        echo '<div class="section-header">';
        echo "<h2>$logType</h2>";
        echo '<button class="expand-button" onclick="openModal(\'errorLogModal\', \'errorLogContainer\')">';
        echo '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>';
        echo '</button>';
        echo '</div>';
        echo '<div class="search-container">';
        echo '<input type="text" class="search-input" placeholder="Search in Apache Error Log..." data-target="errorLogContainer">';
        echo '<label class="toggle-switch-inline">';
        echo '<input type="checkbox" id="apacheLogToggle" ' . ($showAllEntries ? 'checked' : '') . '>';
        echo '<span class="toggle-slider-inline"></span>';
        echo '<span class="toggle-label-inline">Show All</span>';
        echo '</label>';
        echo '</div>';
        echo '<div class="log-container" id="errorLogContainer">';
        
        $entries = [];
        $lineNumber = 0; // Used for fallback sorting when timestamps can't be parsed
        
        foreach ($errorLog as $line) {
            $lineNumber++;
            // Match any Apache log entry with timestamp and module
            if (preg_match('/^\[(.*?)\]\s+\[(.*?)\]/', $line, $matches)) {
                $timestamp = $matches[1];
                $module = $matches[2];
                $message = preg_replace('/^\[.*?\]\s+\[.*?\]\s+\[.*?\]\s+/', '', $line);

                // Parse Apache timestamp - handle multiple formats
                $rawTime = parseApacheTimestamp($timestamp);
                // If parsing fails, use current time minus line number for ordering
                if ($rawTime === false) {
                    $rawTime = time() - (count($errorLog) - $lineNumber);
                }

                // Determine log level
                $level = 'info'; // default
                $levelClass = '';
                
                if (stripos($line, ':error]') !== false || stripos($line, ' error:') !== false) {
                    $level = 'error';
                    $levelClass = 'error-level';
                } elseif (stripos($line, ':warn]') !== false || stripos($line, ' warn:') !== false || stripos($line, 'warning') !== false) {
                    $level = 'warn';
                    $levelClass = 'warn-level';
                } elseif (stripos($line, ':notice]') !== false || stripos($line, ' notice:') !== false) {
                    $level = 'notice';
                    $levelClass = 'notice-level';
                } elseif (stripos($line, ':info]') !== false || stripos($line, ' info:') !== false) {
                    $level = 'info';
                    $levelClass = 'info-level';
                } elseif (stripos($line, ':debug]') !== false || stripos($line, ' debug:') !== false) {
                    $level = 'debug';
                    $levelClass = 'debug-level';
                }

                // Show entry based on toggle state
                if ($showAllEntries || $level === 'error') {
                    $entries[] = [
                        'timestamp' => $timestamp,
                        'module' => $module,
                        'message' => $message,
                        'level' => $level,
                        'level_class' => $levelClass,
                        'raw_time' => $rawTime,
                        'line_order' => $lineNumber // Preserve file order as secondary sort
                    ];
                }
            }
        }

        // Sort entries by timestamp in descending order (newest first)
        // Use line order as secondary sort for entries with same timestamp
        usort($entries, function($a, $b) {
            if ($a['raw_time'] == $b['raw_time']) {
                return $b['line_order'] - $a['line_order']; // Later in file = more recent
            }
            return $b['raw_time'] - $a['raw_time'];
        });

        foreach ($entries as $entry) {
            echo '<div class="log-entry ' . $entry['level_class'] . '">';
            echo '<div class="timestamp">' . htmlspecialchars($entry['timestamp']) . '</div>';
            echo '<div class="log-level">' . strtoupper(htmlspecialchars($entry['level'])) . '</div>';
            echo '<div class="log-module">' . htmlspecialchars($entry['module']) . '</div>';
            echo '<div class="log-message">' . htmlspecialchars($entry['message']) . '</div>';
            echo '</div>';
        }
        
        echo '</div>';
    } else {
        echo '<p class="error-message">Error log file not found or not readable at: ' . htmlspecialchars($errorLogPath) . '</p>';
    }
}

// Helper function to parse Apache timestamp formats
function parseApacheTimestamp($timestamp) {
    // Common Apache timestamp formats
    $formats = [
        'D M d H:i:s.u Y',           // Wed Dec 25 12:34:56.789123 2024
        'D M d H:i:s Y',             // Wed Dec 25 12:34:56 2024
        'Y-m-d H:i:s.u',             // 2024-12-25 12:34:56.789123
        'Y-m-d H:i:s',               // 2024-12-25 12:34:56
        'd/M/Y:H:i:s O',             // 25/Dec/2024:12:34:56 +0000
        'd/M/Y H:i:s',               // 25/Dec/2024 12:34:56
        'M d H:i:s',                 // Dec 25 12:34:56 (current year assumed)
    ];
    
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $timestamp);
        if ($date !== false) {
            return $date->getTimestamp();
        }
    }
    
    // Try strtotime as fallback
    $time = strtotime($timestamp);
    if ($time !== false) {
        return $time;
    }
    
    return false;
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
        Logger::error("Failed to create zip file");
        return false;
    }

    // Add all .log files from the log directory
    $files = glob($logPath . DIRECTORY_SEPARATOR . '*.log');
    if (empty($files)) {
        Logger::warn("No log files found in " . $logPath);
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
                Logger::warn("Failed to add file to zip: " . $file);
            }
        } else {
            Logger::warn("File not readable: " . $file);
        }
    }

    $zip->close();

    // Check if we actually added any files
    if ($addedFiles === 0) {
        Logger::warn("No files were added to the zip");
        if (file_exists($zipPath)) {
            unlink($zipPath);
        }
        return false;
    }

    // Verify the zip file exists and is readable
    if (!file_exists($zipPath) || !is_readable($zipPath)) {
        Logger::error("Created zip file is not accessible");
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

        /* Updated color scheme for a more mellow dark theme */
        body {
            background-color: #1e1e1e;
            color: #d4d4d4;
        }

        h1, h2 {
            color: #ffffff;
        }

        .grid-container {
            display: grid;
            gap: 20px;
            width: 100%;
            margin: 0 auto;
            box-sizing: border-box;
            grid-template-columns: repeat(3, 1fr);
        }

        .log-section {
            background-color: #252526;
            border-color: #333333;
            border: 1px solid #444;
            border-radius: 8px;
            padding: 15px;
            display: flex;
            flex-direction: column;
            min-width: 0;
            position: relative;
            min-height: 300px;
            min-width: 300px;
        }

        .log-section::after {
            content: none;
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
            background-color: #1e1e1e;
            border-color: #333333;
            overflow-y: auto;
            overflow-x: hidden;
            color: #d4d4d4;
            font-size: 13px;
            padding: 10px;
            border: 1px solid #555555;
            border-radius: 5px;
            height: 600px;
            max-height: 600px;
            width: 100%;
            box-sizing: border-box;
            text-align: left;
        }

        .log-entry {
            background-color: #252526;
            border-left: none;
            margin-bottom: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 4px;
            font-family: monospace;
            text-align: left;
        }

        .timestamp {
            color: #888;
            white-space: nowrap;
        }

        .log-level {
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.85em;
            min-width: 50px;
            text-align: center;
        }

        .log-message {
            flex: 1;
            word-break: break-word;
        }

        .error-level {
            border-left: 4px solid #dc3545;
        }
        .error-level .log-level {
            background-color: #dc3545;
            color: white;
        }

        .warn-level {
            border-left: 4px solid #ffc107;
        }
        .warn-level .log-level {
            background-color: #ffc107;
            color: black;
        }

        .info-level {
            border-left: 4px solid #17a2b8;
        }
        .info-level .log-level {
            background-color: #17a2b8;
            color: white;
        }

        .debug-level {
            border-left: 4px solid #6c757d;
        }
        .debug-level .log-level {
            background-color: #6c757d;
            color: white;
        }

        .trace-level {
            border-left: 4px solid #28a745;
        }
        .trace-level .log-level {
            background-color: #28a745;
            color: white;
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
            background-color: #1e1e1e;
            color: #d4d4d4;
            font-family: monospace;
            font-size: 14px;
        }

        .search-input:focus {
            outline: none;
            border-color: #454545;
        }

        /* Inline toggle switch styles (smaller version) */
        .toggle-switch-inline {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            user-select: none;
            white-space: nowrap;
        }

        .toggle-switch-inline input[type="checkbox"] {
            position: relative;
            width: 28px;
            height: 14px;
            appearance: none;
            background-color: #444;
            border-radius: 7px;
            outline: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .toggle-switch-inline input[type="checkbox"]:checked {
            background-color: #17a2b8;
        }

        .toggle-switch-inline input[type="checkbox"]::before {
            content: '';
            position: absolute;
            top: 1px;
            left: 1px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: white;
            transition: transform 0.3s;
        }

        .toggle-switch-inline input[type="checkbox"]:checked::before {
            transform: translateX(14px);
        }

        .toggle-label-inline {
            color: #d4d4d4;
            font-size: 12px;
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
            background-color: rgba(255, 255, 0, 0.3);
            border-radius: 2px;
            padding: 0 2px;
            margin: 0 -2px;
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
            background-color: #1e1e1e;
            color: #d4d4d4;
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
            background-color: #252526;
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
            background-color: #1e1e1e;
            border-radius: 4px;
            border: 1px solid #555555;
        }

        .modal-search-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #444;
            border-radius: 4px;
            background-color: #1e1e1e;
            color: #d4d4d4;
            font-family: monospace;
            font-size: 14px;
        }

        .modal-search-input:focus {
            outline: none;
            border-color: #454545;
        }

        .modal-body {
            background-color: #1e1e1e;
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

        .log-module {
            color: #aaa;
            padding: 2px 6px;
            background-color: #333;
            border-radius: 3px;
            font-size: 0.85em;
            white-space: nowrap;
        }

        /* Audit request table specific styles */
        #requestErrorsContainer .log-entry {
            flex-direction: column;
            gap: 4px;
        }

        #requestErrorsContainer .timestamp {
            color: #888;
            font-size: 0.9em;
        }

        #requestErrorsContainer .error-message {
            width: 100%;
            word-break: break-word;
            white-space: normal;
            line-height: 1.4;
        }

        #requestErrorsContainer .error-message br {
            margin-top: 4px;
        }

        .llm-block {
            background-color: #252526;
            border: 1px solid #333333;
            margin-bottom: 12px;
            padding: 10px;
            text-align: left;
            position: relative;
        }

        .llm-block .timestamp {
            background-color: #1e1e1e;
            border-color: #333333;
            color: #808080;
            font-size: 0.85em;
            padding: 4px 0;
            border-radius: 4px;
            margin-bottom: 12px;
            border: 1px solid #444;
            text-align: center;
            line-height: 1.2;
            width: 100%;
            display: block;
        }

        .time-label {
            color: #808080;
            font-weight: bold;
        }

        .time-separator {
            color: #666;
            margin: 0 4px;
        }

        .llm-content {
            font-family: monospace;
            white-space: pre-wrap;
            margin: 5px 0;
            text-align: left;
            padding-left: 0;
            font-size: 1em;
            line-height: 1.4;
        }

        .llm-block .log-message {
            margin-top: 8px;
            text-align: left;
            padding-left: 0;
            border-top: 1px solid #444;
            padding-top: 12px;
        }

        /* PHP array formatting styles */
        .php-array {
            background-color: #1e1e1e !important;
            padding: 10px !important;
            border-radius: 4px;
            margin: 0 !important;
            font-family: monospace;
            font-size: 0.9em;
            line-height: 1.4;
            border: 1px solid #333333;
        }

        .php-array .html {
            color: #d4d4d4 !important;
            background-color: transparent !important;
        }

        /* More mellow syntax highlighting colors */
        .php-array .default { color: #d4d4d4 !important; }
        .php-array .keyword { color: #c586c0 !important; }
        .php-array .string { color: #9cdcfe !important; }
        .php-array .comment { color: #6a9955 !important; }
        .php-array .number { color: #b5cea8 !important; }

        .copy-llm-btn {
            cursor: pointer;
            margin-left: 10px;
            font-size: 1.2em; /* Adjust size as needed */
            display: inline-block;
            vertical-align: middle;
            user-select: none; /* Prevent text selection on click */
        }

        .copy-llm-btn:hover {
            opacity: 0.7;
        }

        /* Apache log controls */
        .apache-log-controls {
            margin: 10px 0;
            padding: 8px;
            background-color: #1e1e1e;
            border: 1px solid #444;
            border-radius: 4px;
        }

        /* Toggle switch styles */
        .toggle-switch {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            user-select: none;
        }

        .toggle-switch input[type="checkbox"] {
            position: relative;
            width: 40px;
            height: 20px;
            appearance: none;
            background-color: #444;
            border-radius: 10px;
            outline: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .toggle-switch input[type="checkbox"]:checked {
            background-color: #17a2b8;
        }

        .toggle-switch input[type="checkbox"]::before {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background-color: white;
            transition: transform 0.3s;
        }

        .toggle-switch input[type="checkbox"]:checked::before {
            transform: translateX(20px);
        }

        .toggle-label {
            color: #d4d4d4;
            font-size: 14px;
        }

        /* Notice level styling */
        .notice-level {
            border-left: 4px solid #20c997;
        }
        .notice-level .log-level {
            background-color: #20c997;
            color: white;
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
    <h2>Last 2000 lines from each log are displayed here. The full logs can be found in the /log folder of the CHIM server. <a href="/HerikaServer/log" target="_blank">View the log folder.</a></h2>

    <div class="grid-container" id="logGrid">
        <div class="log-section">
            <?php
            // Check if we should show all entries based on GET parameter
            $showAllApacheEntries = isset($_GET['show_all_apache']) && $_GET['show_all_apache'] === '1';
            // Display Apache error log
            $apacheLogTitle = $showAllApacheEntries ? "Apache Log [All Entries] (apache_error.log)" : "Apache Log [Errors Only] (apache_error.log)";
            readErrorLog($distroLogPath, $apacheLogTitle, $showAllApacheEntries);
            ?>
        </div>

        <div class="log-section">
            <?php
            // Display CHIM log
            readRegularLog($chimLogPath, "CHIM Log (chim.log)");
            ?>
        </div>

        <div class="log-section">
            <?php
            // Display LLM output log
            readLLMOutputLog($llmOutputPath, "LLM Output (output_from_llm.log)");
            ?>
        </div>

        <div class="log-section">
            <?php
            // Display LLM context log
            if (file_exists($llmContextPath) && is_readable($llmContextPath)) {
                readLLMContextLog($llmContextPath, "LLM Context (context_sent_to_llm.log)");
            } else {
                echo '<p class="error-message">Log file not found or not readable at: ' . htmlspecialchars($llmContextPath) . '</p>';
            }
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
            // Display Request Errors from audit_request table
            if ($conn) {
                $result = pg_query($conn, "
                    SELECT request, result, created_at
                    FROM {$schema}.audit_request
                    WHERE result != 'OK'
                    ORDER BY created_at DESC
                    LIMIT 100
                ");

                if ($result) {
                    echo '<div class="section-header">';
                    echo "<h2>Request Errors (audit_request Table)</h2>";
                    echo '<button class="expand-button" onclick="openModal(\'requestErrorsModal\', \'requestErrorsContainer\')">';
                    echo '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>';
                    echo '</button>';
                    echo '</div>';
                    echo '<div class="search-container">';
                    echo '<input type="text" class="search-input" placeholder="Search in Request Errors..." data-target="requestErrorsContainer">';
                    echo '</div>';
                    echo '<div class="log-container" id="requestErrorsContainer">';
                    
                    while ($error = pg_fetch_assoc($result)) {
                        $time = new DateTime($error['created_at']);
                        $time->setTimezone(new DateTimeZone('UTC'));
                        $timestamp = $time->format('Y-m-d H:i:s');
                        
                        echo '<div class="log-entry error-entry">';
                        echo '<div class="timestamp">' . htmlspecialchars($timestamp) . ' UTC</div>';
                        echo '<div class="error-message">';
                        echo '<strong>Request:</strong> ' . htmlspecialchars($error['request']) . '<br>';
                        echo '<strong>Result:</strong> ' . htmlspecialchars($error['result']);
                        echo '</div>';
                        echo '</div>';
                    }
                    
                    echo '</div>';
                }
            }
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

<div id="CHIMLogchimlogModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">CHIM Log</h2>
            <button class="close-modal" onclick="closeModal('CHIMLogchimlogModal')">&times;</button>
        </div>
        <div class="modal-search-container">
            <input type="text" class="modal-search-input" placeholder="Search in CHIM Log..." data-target="CHIMLogchimlogModalContent">
        </div>
        <div class="modal-body">
            <div id="CHIMLogchimlogModalContent"></div>
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

<!-- Add Request Errors Modal -->
<div id="requestErrorsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Request Errors</h2>
            <button class="close-modal" onclick="closeModal('requestErrorsModal')">&times;</button>
        </div>
        <div class="modal-search-container">
            <input type="text" class="modal-search-input" placeholder="Search in Request Errors..." data-target="requestErrorsModalContent">
        </div>
        <div class="modal-body">
            <div id="requestErrorsModalContent"></div>
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
document.querySelectorAll('.search-input, .modal-search-input').forEach(input => {
    const targetId = input.getAttribute('data-target');
    const container = document.getElementById(targetId);
    let originalContent = '';
    let originalEntries = [];

    // Store original content when the page loads
    if (container) {
        originalContent = container.innerHTML;
        // Handle different types of entries
        if (targetId === 'requestErrorsContainer' || targetId === 'requestErrorsModalContent') {
            originalEntries = Array.from(container.querySelectorAll('.error-entry'));
        } else {
            originalEntries = Array.from(container.querySelectorAll('.log-entry'));
        }
    }

    function performSearch() {
        if (!container) return;

        const searchTerm = input.value.trim().toLowerCase();
        
        // If search is empty, restore original content
        if (!searchTerm) {
            container.innerHTML = originalContent;
            return;
        }

        // Clear container but maintain original structure
        container.innerHTML = '';
        let hasMatches = false;

        originalEntries.forEach(entry => {
            const clone = entry.cloneNode(true);
            const entryText = entry.textContent.toLowerCase();
            
            if (entryText.includes(searchTerm)) {
                hasMatches = true;
                if (targetId === 'requestErrorsContainer' || targetId === 'requestErrorsModalContent') {
                    // Special handling for request errors
                    const elements = clone.querySelectorAll('.timestamp, .error-message');
                    elements.forEach(element => {
                        const text = element.textContent;
                        if (text.toLowerCase().includes(searchTerm)) {
                            const regex = new RegExp(`(${searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
                            element.innerHTML = text.replace(regex, '<span class="highlight">$1</span>');
                        }
                    });
                } else {
                    // Regular log entries
                    const elements = clone.querySelectorAll('.log-message, .timestamp, .log-level, .log-module');
                    elements.forEach(element => {
                        const text = element.textContent;
                        if (text.toLowerCase().includes(searchTerm)) {
                            const regex = new RegExp(`(${searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
                            element.innerHTML = text.replace(regex, '<span class="highlight">$1</span>');
                        }
                    });
                }
                container.appendChild(clone);
            }
        });

        if (!hasMatches) {
            container.innerHTML = '<div class="no-results">No matches found</div>';
        }
    }

    // Add event listeners for both input and keyup events
    input.addEventListener('input', performSearch);
    input.addEventListener('keyup', performSearch);
});

// Function to open modal with special handling for LLM output
function openModal(modalId, sourceId) {
    const modal = document.getElementById(modalId);
    const contentId = modalId + 'Content';
    const content = document.getElementById(contentId);
    const sourceContainer = document.getElementById(sourceId);
    
    if (sourceContainer && modal) {
        // Special handling for LLM output log
        if (modalId === 'LLMOutputoutputfromllmlogModal') {
            // Clone the content but preserve the block structure
            content.innerHTML = sourceContainer.innerHTML;
        } else {
            content.innerHTML = sourceContainer.innerHTML;
        }
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

                if (modalId === 'LLMOutputoutputfromllmlogModal') {
                    // Special search handling for LLM output blocks
                    const blocks = content.querySelectorAll('.llm-block');
                    blocks.forEach(block => {
                        const blockText = block.textContent.toLowerCase();
                        if (blockText.includes(searchTerm.toLowerCase())) {
                            block.style.display = '';
                            // Highlight the matching text
                            const regex = new RegExp(searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
                            const messages = block.querySelectorAll('.llm-content');
                            messages.forEach(msg => {
                                const text = msg.textContent;
                                if (text.match(regex)) {
                                    msg.innerHTML = text.replace(regex, match => `<span class="highlight">${match}</span>`);
                                }
                            });
                        } else {
                            block.style.display = 'none';
                        }
                    });
                } else {
                    // Regular search for other logs
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
                }
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

// Update the highlight style to be more subtle
const style = document.createElement('style');
style.textContent = `
    .highlight {
        background-color: rgba(255, 255, 0, 0.3);
        border-radius: 2px;
        padding: 0 2px;
        margin: 0 -2px;
    }
    .log-entry, .error-entry {
        width: 100%;
    }
    .log-message, .error-message {
        width: auto;
        flex: 1;
    }
    .error-entry .error-message strong {
        margin-right: 5px;
    }
`;
document.head.appendChild(style);

// JavaScript for copy to clipboard
document.addEventListener('DOMContentLoaded', () => {
    document.body.addEventListener('click', function(event) {
        if (event.target.classList.contains('copy-llm-btn')) {
            const llmBlock = event.target.closest('.llm-block');
            if (llmBlock) {
                let contentToCopy = '';
                // Try to find LLM output content (multiple divs)
                const outputMessages = llmBlock.querySelectorAll('.log-message .llm-content');
                if (outputMessages.length > 0) {
                    outputMessages.forEach(msg => {
                        contentToCopy += msg.textContent.trim() + '\n';
                    });
                } else {
                    // Try to find LLM context content (preformatted text)
                    const contextMessage = llmBlock.querySelector('.log-message pre.llm-content');
                    if (contextMessage) {
                        contentToCopy = contextMessage.textContent;
                    }
                }

                contentToCopy = contentToCopy.trim();

                if (contentToCopy) {
                    navigator.clipboard.writeText(contentToCopy)
                        .then(() => {
                            event.target.textContent = '✅'; // Copied!
                            setTimeout(() => {
                                event.target.textContent = '📋'; // Reset icon
                            }, 1500);
                        })
                        .catch(err => {
                            console.error('Failed to copy text: ', err);
                            event.target.textContent = '❌'; // Error
                             setTimeout(() => {
                                event.target.textContent = '📋'; // Reset icon
                            }, 1500);
                        });
                } else {
                    console.warn('No content found to copy in LLM block:', llmBlock);
                    event.target.textContent = '❓'; // No content
                     setTimeout(() => {
                        event.target.textContent = '📋'; // Reset icon
                    }, 1500);
                }
            }
        }
    });
});

// Apache log toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const apacheToggle = document.getElementById('apacheLogToggle');
    if (apacheToggle) {
        apacheToggle.addEventListener('change', function() {
            const url = new URL(window.location);
            if (this.checked) {
                url.searchParams.set('show_all_apache', '1');
            } else {
                url.searchParams.delete('show_all_apache');
            }
            // Remove download parameter if it exists
            url.searchParams.delete('download_logs');
            window.location.href = url.toString();
        });
    }
});
</script>

<?php
// Close database connection if it exists
if (isset($conn)) {
    pg_close($conn);
}

include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
</body>
</html>
