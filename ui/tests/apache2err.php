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

// Function to read and filter the error log from a given path
function readErrorLog($errorLogPath, $logType) {
    if (file_exists($errorLogPath) && is_readable($errorLogPath)) {
        $errorLog = file($errorLogPath);
        $errorLog = array_reverse($errorLog);

        echo "<h2>$logType</h2>";
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

        echo "<h2>$logName</h2>";
        echo '<div class="log-container" id="' . sanitizeId($logName) . 'Container">';
        echo '<div class="log-entry regular-entry">';
        echo '<pre class="log-content">' . htmlspecialchars($log) . '</pre>';
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
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            width: 100%;
            margin: 0 auto;
            box-sizing: border-box;
        }

        .log-section {
            border: 1px solid #444;
            border-radius: 8px;
            padding: 15px;
            background-color: #2a2a2a;
            display: flex;
            flex-direction: column;
            min-width: 0;
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

        @media (max-width: 1200px) {
            .grid-container {
                grid-template-columns: 1fr;
            }
            .log-container {
                height: 400px;
                max-height: 400px;
            }
            main {
                padding-left: 5px;
                padding-right: 5px;
            }
            .log-section {
                padding: 10px;
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
    </div>
    <h2>Logs can be found in the /log folder of the CHIM server. <a href="/HerikaServer/log" target="_blank">Click here to view the log folder.</a></h2>

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
    </div>
</div>
</main>

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
