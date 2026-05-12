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

// Resolve MCP host from conf_opts (fallback to localhost)
$mcpHost = 'localhost';
if ($conn) {
    $mcpRes = @pg_query($conn, "SELECT value FROM conf_opts WHERE id='Network/WSL_IP' LIMIT 1");
    if ($mcpRes && pg_num_rows($mcpRes) > 0) {
        $mcpRow = pg_fetch_assoc($mcpRes);
        if (!empty($mcpRow['value'])) {
            $mcpHost = trim((string)$mcpRow['value']);
        }
    }
}

$TITLE = "🌲 CHIM Server Logs";

// Auto-trim logs setting (chim_meta.settings)
$autoTrimEnabled = true; // default enabled

// Handle toggle request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_auto_trim_logs') {
    try {
        // Ensure chim_meta.settings exists
        @pg_query($conn, "CREATE SCHEMA IF NOT EXISTS chim_meta");
        @pg_query($conn, "CREATE TABLE IF NOT EXISTS chim_meta.settings (key TEXT PRIMARY KEY, value TEXT)");

        // Read current value
        $res = @pg_query($conn, "SELECT value FROM chim_meta.settings WHERE key='AUTO_TRIM_LOGS_ENABLED' LIMIT 1");
        $val = 'true';
        if ($res && pg_num_rows($res) > 0) {
            $row = pg_fetch_assoc($res);
            $current = strtolower(trim((string)$row['value']));
            $val = in_array($current, ['true','1','yes','on']) ? 'false' : 'true';
        }
        // Upsert
        @pg_query($conn, "INSERT INTO chim_meta.settings(key,value) VALUES ('AUTO_TRIM_LOGS_ENABLED', '".$val."') ON CONFLICT (key) DO UPDATE SET value=EXCLUDED.value");
    } catch (Throwable $e) { /* ignore */ }
    // Redirect back (PRG)
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

// Read current value (default true if missing)
try {
    @pg_query($conn, "CREATE SCHEMA IF NOT EXISTS chim_meta");
    @pg_query($conn, "CREATE TABLE IF NOT EXISTS chim_meta.settings (key TEXT PRIMARY KEY, value TEXT)");
    $res = @pg_query($conn, "SELECT value FROM chim_meta.settings WHERE key='AUTO_TRIM_LOGS_ENABLED' LIMIT 1");
    if ($res && pg_num_rows($res) > 0) {
        $row = pg_fetch_assoc($res);
        $autoTrimEnabled = in_array(strtolower(trim((string)$row['value'])), ['true','1','yes','on']);
    } else {
        // Persist default true
        @pg_query($conn, "INSERT INTO chim_meta.settings(key,value) VALUES ('AUTO_TRIM_LOGS_ENABLED','true') ON CONFLICT (key) DO NOTHING");
        $autoTrimEnabled = true;
    }
} catch (Throwable $e) { /* ignore */ }

ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/head.html");

$debugPaneLink = false;
$isEmbedded = (isset($_GET['embed']) && $_GET['embed']);
if (!$isEmbedded) {
    include(__DIR__.DIRECTORY_SEPARATOR."../tmpl/navbar.php");
}

$logPath = __DIR__ . '/../../log/';
$distroLogPath = $logPath . 'apache_error.log';
$chimLogPath = $logPath . 'chim.log';
$llmOutputPath = $logPath . 'output_from_llm.log';
$llmContextPath = $logPath . 'context_sent_to_llm.log';
$pluginOutputPath = $logPath . 'output_to_plugin.log';
$sttLogPath = $logPath . 'stt.log';
$visionLogPath = $logPath . 'vision.log';
$debugStreamLogPath = $logPath . 'debugStream.log';
$llmContextFastPath = $logPath . 'context_sent_to_llm_fast.log';
$monitorLogPath = $logPath . 'monitor.log';
$serviceLogPath = $logPath . 'service.log';

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
    // Clear file stat cache to ensure fresh file status
    clearstatcache(true, $logPath);
    
    // Attempt to create log file if it doesn't exist
    if (!file_exists($logPath)) {
        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        @touch($logPath);
        @chmod($logPath, 0644);
        clearstatcache(true, $logPath);
    }
    
    $fileExists = file_exists($logPath);
    
    // Attempt to fix permissions if file exists but isn't readable
    if ($fileExists && !is_readable($logPath)) {
        @chmod($logPath, 0644);
        clearstatcache(true, $logPath);
    }
    
    $fileReadable = $fileExists && is_readable($logPath);
    $fileSize = $fileExists ? @filesize($logPath) : 0;
    
    if ($fileExists && $fileReadable) {
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
        
        // Add log level filter controls
        echo '<div class="log-filter-container" id="' . $sanitizedId . 'FilterContainer">';
        echo '<div class="filter-header">Filter by Level:</div>';
        echo '<div class="filter-controls">';
        echo '<button class="filter-btn filter-btn-sm" onclick="selectAllLevels(\'' . $sanitizedId . '\')">All</button>';
        echo '<button class="filter-btn filter-btn-sm" onclick="selectNoLevels(\'' . $sanitizedId . '\')">None</button>';
        echo '</div>';
        echo '<div class="filter-checkboxes">';
        echo '<label class="filter-checkbox"><input type="checkbox" class="level-filter" data-container="' . $sanitizedId . '" data-level="error" checked><span class="filter-badge error-badge">Error <span class="level-count" id="' . $sanitizedId . '-error-count">0</span></span></label>';
        echo '<label class="filter-checkbox"><input type="checkbox" class="level-filter" data-container="' . $sanitizedId . '" data-level="warn" checked><span class="filter-badge warn-badge">Warn <span class="level-count" id="' . $sanitizedId . '-warn-count">0</span></span></label>';
        echo '<label class="filter-checkbox"><input type="checkbox" class="level-filter" data-container="' . $sanitizedId . '" data-level="info"><span class="filter-badge info-badge">Info <span class="level-count" id="' . $sanitizedId . '-info-count">0</span></span></label>';
        echo '<label class="filter-checkbox"><input type="checkbox" class="level-filter" data-container="' . $sanitizedId . '" data-level="debug"><span class="filter-badge debug-badge">Debug <span class="level-count" id="' . $sanitizedId . '-debug-count">0</span></span></label>';
        echo '<label class="filter-checkbox"><input type="checkbox" class="level-filter" data-container="' . $sanitizedId . '" data-level="trace"><span class="filter-badge trace-badge">Trace <span class="level-count" id="' . $sanitizedId . '-trace-count">0</span></span></label>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="log-container" id="' . $sanitizedId . 'Container">';

        if (empty($log)) {
            echo '<p class="info-message" style="color: #888; padding: 20px;">Log file is empty (size: ' . $fileSize . ' bytes)</p>';
        } else {
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

                    echo '<div class="log-entry ' . $levelClass . '" data-level="' . htmlspecialchars($entry['level']) . '">';
                    $isoTimestamp = timestampToISO8601($entry['timestamp']);
                    if ($isoTimestamp !== null) {
                        echo '<div class="timestamp" data-utc="' . htmlspecialchars($isoTimestamp) . '">' . htmlspecialchars($entry['timestamp']) . '</div>';
                    } else {
                        echo '<div class="timestamp">' . htmlspecialchars($entry['timestamp']) . '</div>';
                    }
                    echo '<div class="log-level">' . htmlspecialchars($entry['level']) . '</div>';
                    echo '<div class="log-message">' . htmlspecialchars($entry['message']) . '</div>';
                    echo '</div>';
                } else {
                    echo '<div class="log-entry">';
                    echo '<div class="log-message">' . htmlspecialchars($entry['message']) . '</div>';
                    echo '</div>';
                }
            }
        }

        echo '</div>';
    } else {
        // Detailed error message
        $errorDetails = [];
        if (!$fileExists) {
            $errorDetails[] = 'File does not exist';
        } else {
            $errorDetails[] = 'File exists';
            if (!$fileReadable) {
                $perms = @fileperms($logPath);
                $errorDetails[] = 'Not readable (permissions: ' . ($perms ? sprintf('%o', $perms & 0777) : 'unknown') . ')';
                $errorDetails[] = 'Attempted automatic permission fix (0644)';
            }
        }
        echo '<p class="error-message">Log file not accessible: ' . htmlspecialchars($logPath) . '<br>';
        echo '<small style="color: #aaa;">Details: ' . implode(', ', $errorDetails) . '</small><br>';
        echo '<small style="color: #ff9800;">Try refreshing the page. If the issue persists, manually run: <code>chmod 644 ' . htmlspecialchars($logPath) . '</code></small></p>';
    }
}

// Function to read LLM output logs with timestamp grouping
function readLLMOutputLog($logPath, $logName) {
    // Clear file stat cache to ensure fresh file status
    clearstatcache(true, $logPath);
    
    // Attempt to create log file if it doesn't exist
    if (!file_exists($logPath)) {
        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        @touch($logPath);
        @chmod($logPath, 0644);
        clearstatcache(true, $logPath);
    }
    
    $fileExists = file_exists($logPath);
    
    // Attempt to fix permissions if file exists but isn't readable
    if ($fileExists && !is_readable($logPath)) {
        @chmod($logPath, 0644);
        clearstatcache(true, $logPath);
    }
    
    $fileReadable = $fileExists && is_readable($logPath);
    $fileSize = $fileExists ? @filesize($logPath) : 0;
    
    if ($fileExists && $fileReadable) {
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

        if (empty($log)) {
            echo '<p class="info-message" style="color: #888; padding: 20px;">Log file is empty (size: ' . $fileSize . ' bytes)</p>';
        } else {
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
        }

        echo '</div>';
    } else {
        // Detailed error message
        $errorDetails = [];
        if (!$fileExists) {
            $errorDetails[] = 'File does not exist';
        } else {
            $errorDetails[] = 'File exists';
            if (!$fileReadable) {
                $perms = @fileperms($logPath);
                $errorDetails[] = 'Not readable (permissions: ' . ($perms ? sprintf('%o', $perms & 0777) : 'unknown') . ')';
                $errorDetails[] = 'Attempted automatic permission fix (0644)';
            }
        }
        echo '<p class="error-message">Log file not accessible: ' . htmlspecialchars($logPath) . '<br>';
        echo '<small style="color: #aaa;">Details: ' . implode(', ', $errorDetails) . '</small><br>';
        echo '<small style="color: #ff9800;">Try refreshing the page. If the issue persists, manually run: <code>chmod 644 ' . htmlspecialchars($logPath) . '</code></small></p>';
    }
}

// Helper function to output an LLM block
function outputLLMBlock($block) {
    if (empty($block) || empty($block['content'])) return;
    
    echo '<div class="log-entry llm-block">';
    echo '<div class="timestamp">';
    echo '<span class="time-label">Start:</span> <span class="time-value" data-utc="' . htmlspecialchars($block['start_time']) . '">' . htmlspecialchars($block['start_time']) . '</span>';
    if (isset($block['end_time'])) {
        echo ' <span class="time-separator">→</span> ';
        echo '<span class="time-label">End:</span> <span class="time-value" data-utc="' . htmlspecialchars($block['end_time']) . '">' . htmlspecialchars($block['end_time']) . '</span>';
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
    echo '<span class="time-label">Time:</span> <span class="time-value" data-utc="' . htmlspecialchars($block['timestamp']) . '">' . htmlspecialchars($block['timestamp']) . '</span>';
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
function readErrorLog($errorLogPath, $logType) {
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
        echo '</div>';
        
        // Add log level filter controls for Apache error log
        echo '<div class="log-filter-container" id="errorLogFilterContainer">';
        echo '<div class="filter-header">Filter by Level:</div>';
        echo '<div class="filter-controls">';
        echo '<button class="filter-btn filter-btn-sm" onclick="selectAllLevels(\'errorLog\')">All</button>';
        echo '<button class="filter-btn filter-btn-sm" onclick="selectNoLevels(\'errorLog\')">None</button>';
        echo '</div>';
        echo '<div class="filter-checkboxes">';
        echo '<label class="filter-checkbox"><input type="checkbox" class="level-filter" data-container="errorLog" data-level="error" checked><span class="filter-badge error-badge">Error <span class="level-count" id="errorLog-error-count">0</span></span></label>';
        echo '<label class="filter-checkbox"><input type="checkbox" class="level-filter" data-container="errorLog" data-level="warn" checked><span class="filter-badge warn-badge">Warn <span class="level-count" id="errorLog-warn-count">0</span></span></label>';
        echo '<label class="filter-checkbox"><input type="checkbox" class="level-filter" data-container="errorLog" data-level="notice"><span class="filter-badge notice-badge">Notice <span class="level-count" id="errorLog-notice-count">0</span></span></label>';
        echo '<label class="filter-checkbox"><input type="checkbox" class="level-filter" data-container="errorLog" data-level="info"><span class="filter-badge info-badge">Info <span class="level-count" id="errorLog-info-count">0</span></span></label>';
        echo '<label class="filter-checkbox"><input type="checkbox" class="level-filter" data-container="errorLog" data-level="debug"><span class="filter-badge debug-badge">Debug <span class="level-count" id="errorLog-debug-count">0</span></span></label>';
        echo '</div>';
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

                // Always add entry to array (no filtering at PHP level)
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

        // Sort entries by timestamp in descending order (newest first)
        // Use line order as secondary sort for entries with same timestamp
        usort($entries, function($a, $b) {
            if ($a['raw_time'] == $b['raw_time']) {
                return $b['line_order'] - $a['line_order']; // Later in file = more recent
            }
            return $b['raw_time'] - $a['raw_time'];
        });

        foreach ($entries as $entry) {
            echo '<div class="log-entry ' . $entry['level_class'] . '" data-level="' . htmlspecialchars($entry['level']) . '">';
            $isoTimestamp = timestampToISO8601($entry['timestamp']);
            if ($isoTimestamp !== null) {
                echo '<div class="timestamp" data-utc="' . htmlspecialchars($isoTimestamp) . '">' . htmlspecialchars($entry['timestamp']) . '</div>';
            } else {
                echo '<div class="timestamp">' . htmlspecialchars($entry['timestamp']) . '</div>';
            }
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

// Helper function to convert timestamp string to ISO 8601 format for timezone conversion
function timestampToISO8601($timestamp) {
    // Try to parse the timestamp
    $time = parseApacheTimestamp($timestamp);
    if ($time !== false) {
        // Create DateTime object from timestamp (assumes server timezone)
        $date = new DateTime('@' . $time);
        // Convert to UTC and return ISO 8601
        $date->setTimezone(new DateTimeZone('UTC'));
        return $date->format('c'); // ISO 8601 format
    }
    
    // If parsing fails, try strtotime directly
    $time = strtotime($timestamp);
    if ($time !== false) {
        $date = new DateTime('@' . $time);
        $date->setTimezone(new DateTimeZone('UTC'));
        return $date->format('c');
    }
    
    // If all parsing fails, return null (won't add data-utc attribute)
    return null;
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

    // Ensure no output buffering or compression corrupts the binary download
    @set_time_limit(0);
    if (function_exists('ini_get') && ini_get('zlib.output_compression')) {
        @ini_set('zlib.output_compression', 'Off');
    }
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    // Send the file to the browser with robust headers
    header('Content-Description: File Transfer');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipName . '"');
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . filesize($zipPath));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Stream file in chunks to handle large files
    if ($fp = fopen($zipPath, 'rb')) {
        while (!feof($fp)) {
            $buffer = fread($fp, 8192);
            if ($buffer === false) { break; }
            echo $buffer;
        }
        fclose($fp);
        // Ensure output is sent immediately
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        } else {
            @flush();
        }
        @unlink($zipPath); // Delete the temporary zip file
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
        /* Font declaration */
        @font-face {
            font-family: 'MagicCards';
            src: url('../css/font/MagicCardsNormal.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        
        /* Override main container styles */
        main {
            padding-top: <?php echo $isEmbedded? '20' : '160'; ?>px;
            padding-bottom: 40px;
            padding-left: 10px;
            padding-right: 10px;
            width: 100%;
            box-sizing: border-box;
            overflow-x: hidden;
        }
        
        /* Override footer styles */
        footer { display: <?php echo $isEmbedded? 'none' : 'block'; ?>; }

        /* Updated color scheme for modern dark theme */
        body {
            background-color: #1e1e1e;
            color: #d4d4d4;
        }

        h1 {
            color: rgb(242, 124, 17);
            font-family: 'MagicCards', serif;
            word-spacing: 8px;
            font-size: 2.0em;
            font-weight: normal;
            letter-spacing: 0.5px;
        }
        
        h2 {
            color: rgb(242, 124, 17);
            font-family: 'Futura CondensedLight', Arial, sans-serif;
            font-size: 1.2em;
        }

        .grid-container {
            display: grid;
            gap: 20px;
            width: 100%;
            margin: 0 auto;
            box-sizing: border-box;
            grid-template-columns: repeat(2, 1fr);
        }

        .logs-kagrenac-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 16px;
            align-items: start;
        }

        .logs-column {
            min-width: 0;
        }

        .kagrenac-panel {
            position: sticky;
            top: 86px;
            align-self: start;
            background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
            border: 1px solid #3a3a3a;
            border-radius: 10px;
            min-height: 300px;
            height: calc(100vh - 110px);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .kagrenac-header {
            padding: 12px 14px;
            border-bottom: 1px solid #3a3a3a;
            background: rgba(30, 30, 30, 0.85);
        }

        .kagrenac-header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .kagrenac-header h2 {
            margin: 0 0 6px 0;
            padding: 0;
            border: 0;
            font-size: 1.1em;
        }

        .kagrenac-subtitle {
            color: #c8c8c8;
            font-size: 12px;
        }

        .kagrenac-toggle-settings {
            border: 1px solid #3a3a3a;
            border-radius: 6px;
            background: rgba(20, 20, 20, 0.85);
            color: #ddd;
            font-size: 11px;
            line-height: 1;
            padding: 6px 8px;
            cursor: pointer;
        }

        .kagrenac-toggle-settings:hover {
            border-color: rgba(242, 124, 17, 0.5);
            color: rgb(242, 124, 17);
        }

        .kagrenac-settings {
            padding: 10px 14px;
            border-bottom: 1px solid #3a3a3a;
            display: grid;
            gap: 8px;
            background: rgba(30, 30, 30, 0.6);
        }

        .kagrenac-settings.hidden {
            display: none;
        }

        .kagrenac-settings label {
            color: #d4d4d4;
            font-size: 12px;
            display: block;
            margin-bottom: 4px;
        }

        .kagrenac-settings input,
        .kagrenac-settings select,
        .kagrenac-settings textarea {
            width: 100%;
            padding: 7px 9px;
            border: 1px solid #3a3a3a;
            border-radius: 6px;
            background: rgba(26, 26, 26, 0.8);
            color: #d4d4d4;
            font-size: 12px;
            box-sizing: border-box;
        }

        .kagrenac-settings textarea {
            min-height: 64px;
            resize: vertical;
        }

        .kagrenac-settings .settings-actions {
            display: flex;
            gap: 8px;
        }

        .kagrenac-settings .settings-btn {
            border: 1px solid #3a3a3a;
            background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
            color: #f8f9fa;
            border-radius: 6px;
            padding: 7px 10px;
            font-size: 12px;
            cursor: pointer;
        }

        .kagrenac-settings .settings-btn:hover {
            border-color: rgba(242, 124, 17, 0.5);
            color: rgb(242, 124, 17);
        }

        .kagrenac-chat {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .kagrenac-chat-history {
            flex: 1;
            overflow-y: auto;
            padding: 12px;
            background: radial-gradient(circle at top left, rgba(40, 40, 40, 0.35), rgba(20, 20, 20, 0.9));
        }

        .kag-msg-row {
            display: flex;
            margin-bottom: 10px;
            align-items: flex-end;
            gap: 8px;
        }

        .kag-msg-row.user {
            justify-content: flex-end;
        }

        .kag-msg-row.assistant,
        .kag-msg-row.error {
            justify-content: flex-start;
        }

        .kag-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid rgba(242, 124, 17, 0.65);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.35);
            background: rgba(35, 35, 35, 0.9);
            flex: 0 0 30px;
        }

        .kag-msg {
            padding: 8px 10px;
            border-radius: 10px;
            font-size: 12px;
            line-height: 1.45;
            white-space: pre-wrap;
            word-break: break-word;
            max-width: 88%;
            border: 1px solid rgba(242, 124, 17, 0.35);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
        }

        .kag-msg.user {
            background: linear-gradient(135deg, rgba(66, 66, 155, 0.55), rgba(42, 42, 120, 0.65));
            border-color: rgba(110, 110, 190, 0.45);
        }

        .kag-msg.assistant {
            background: linear-gradient(135deg, rgba(242, 124, 17, 0.5), rgba(184, 92, 13, 0.55));
            border-color: rgba(242, 124, 17, 0.65);
            color: #fff4e8;
        }

        .kag-msg.error {
            background: linear-gradient(135deg, rgba(145, 40, 40, 0.5), rgba(95, 20, 20, 0.6));
            border-color: rgba(190, 70, 70, 0.5);
            color: #ffd0d0;
        }

        .kag-msg-meta {
            margin-top: 4px;
            font-size: 10px;
            color: #a0a0a0;
            text-align: right;
            opacity: 0.9;
        }

        .kag-msg-row.thinking .kag-msg {
            background: linear-gradient(135deg, rgba(58, 58, 58, 0.85), rgba(42, 42, 42, 0.9));
            border-style: dashed;
            color: #dddddd;
        }

        .kag-typing-dots {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-left: 6px;
            vertical-align: middle;
        }

        .kag-typing-dots span {
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: rgba(242, 124, 17, 0.95);
            animation: kagDotsPulse 1s infinite ease-in-out;
        }

        .kag-typing-dots span:nth-child(2) { animation-delay: 0.15s; }
        .kag-typing-dots span:nth-child(3) { animation-delay: 0.3s; }

        @keyframes kagDotsPulse {
            0%, 80%, 100% { transform: scale(0.7); opacity: 0.6; }
            40% { transform: scale(1); opacity: 1; }
        }

        .kagrenac-chat-input {
            border-top: 1px solid #3a3a3a;
            padding: 10px;
            display: grid;
            gap: 8px;
            background: rgba(34, 34, 34, 0.95);
        }

        .kagrenac-chat-input textarea {
            width: 100%;
            min-height: 58px;
            max-height: 150px;
            padding: 8px 10px;
            border: 1px solid #3a3a3a;
            border-radius: 6px;
            background: rgba(26, 26, 26, 0.8);
            color: #d4d4d4;
            font-size: 12px;
            resize: vertical;
            box-sizing: border-box;
        }

        .kag-processing-hint {
            display: none;
            align-items: center;
            gap: 8px;
            color: #bfbfbf;
            font-size: 11px;
        }

        .kag-processing-hint.active {
            display: inline-flex;
        }

        .kag-processing-spinner {
            width: 12px;
            height: 12px;
            border: 2px solid #555;
            border-top-color: rgba(242, 124, 17, 0.95);
            border-radius: 50%;
            animation: kagSpin 0.8s linear infinite;
        }

        @keyframes kagSpin {
            to { transform: rotate(360deg); }
        }

        .kagrenac-send {
            justify-self: end;
            border: 1px solid #3a3a3a;
            background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
            color: #f8f9fa;
            border-radius: 6px;
            padding: 7px 12px;
            font-size: 12px;
            cursor: pointer;
        }

        .kagrenac-send:hover:not(:disabled) {
            border-color: rgba(242, 124, 17, 0.5);
            color: rgb(242, 124, 17);
        }

        .kagrenac-send:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .log-section {
            background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
            border: 1px solid #3a3a3a;
            border-radius: 10px;
            padding: 15px;
            display: flex;
            flex-direction: column;
            min-width: 0;
            position: relative;
            min-height: 300px;
            min-width: 300px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px rgba(255, 255, 255, 0.03);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        
        .log-section:hover {
            border-color: rgba(242, 124, 17, 0.3);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25), inset 0 1px rgba(255, 255, 255, 0.05);
        }

        .log-section::after {
            content: none;
        }

        h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(242, 124, 17, 0.3);
            font-size: 1.2em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .log-container {
            background: rgba(26, 26, 26, 0.8);
            border: 1px solid #3a3a3a;
            overflow-y: auto;
            overflow-x: hidden;
            color: #d4d4d4;
            font-size: 13px;
            padding: 10px;
            border-radius: 6px;
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
            border-left: 4px solid #9370DB;
        }
        .trace-level .log-level {
            background-color: #9370DB;
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
            .logs-kagrenac-layout {
                grid-template-columns: 1fr;
            }
            .kagrenac-panel {
                position: static;
                height: auto;
                max-height: none;
            }
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
            background: linear-gradient(135deg, rgba(42, 42, 42, 0.95), rgba(34, 34, 34, 0.98));
            color: white;
            border: 1px solid #3a3a3a;
            border-radius: 6px;
            padding: 8px 16px;
            margin-left: 15px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2), inset 0 1px rgba(255, 255, 255, 0.05);
        }

        .refresh-button:hover {
            border-color: rgba(242, 124, 17, 0.5);
            color: rgb(242, 124, 17);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3), inset 0 1px rgba(255, 255, 255, 0.1);
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

        /* Compact per-panel header layout: title + search on one line */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: nowrap;
            margin-bottom: 6px;
        }

        .section-header h2 {
            margin: 0;
            flex: 1;
            min-width: 0;
        }

        .section-header .search-container {
            margin: 0;
            flex: 0 0 clamp(260px, 42%, 520px);
            min-width: 220px;
            justify-content: flex-end;
        }

        .section-header .search-input {
            width: 100%;
        }

        /* Log Filter Styles */
        .log-filter-container {
            background: #2a2a2a;
            border: 1px solid #3a3a3a;
            border-radius: 6px;
            padding: 12px 15px;
            margin: 10px 0;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 15px;
        }

        .filter-header {
            font-weight: 600;
            color: #fff;
            font-size: 14px;
            margin-right: 5px;
        }

        .filter-controls {
            display: flex;
            gap: 8px;
        }

        .filter-btn-sm {
            padding: 4px 12px;
            font-size: 12px;
            background: #3a3a3a;
            border: 1px solid #4a4a4a;
            color: #fff;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-btn-sm:hover {
            background: #4a4a4a;
            border-color: #5a5a5a;
        }

        .filter-checkboxes {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }

        .filter-checkbox {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            user-select: none;
        }

        .filter-checkbox input[type="checkbox"] {
            cursor: pointer;
            width: 16px;
            height: 16px;
        }

        .filter-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            transition: opacity 0.2s;
        }

        .filter-checkbox input[type="checkbox"]:not(:checked) + .filter-badge {
            opacity: 0.4;
        }

        .level-count {
            background: rgba(0, 0, 0, 0.3);
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            min-width: 20px;
            text-align: center;
        }

        .error-badge {
            background-color: #dc3545;
            color: white;
        }

        .warn-badge {
            background-color: #ffc107;
            color: black;
        }

        .notice-badge {
            background-color: #20c997;
            color: white;
        }

        .info-badge {
            background-color: #17a2b8;
            color: white;
        }

        .debug-badge {
            background-color: #6c757d;
            color: white;
        }

        .trace-badge {
            background-color: #9370DB;
            color: white;
        }

        /* Hide filtered log entries */
        .log-entry.hidden-by-filter,
        .log-entry.hidden-by-search {
            display: none !important;
        }

        .search-input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #3a3a3a;
            border-radius: 6px;
            background: rgba(26, 26, 26, 0.8);
            color: #d4d4d4;
            font-family: monospace;
            font-size: 14px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease, background 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: rgb(242, 124, 17);
            box-shadow: 0 0 0 3px rgba(242, 124, 17, 0.1);
            background: rgba(26, 26, 26, 0.95);
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
            background-color: rgb(242, 124, 17);
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

        mark.highlight {
            background-color: rgba(255, 193, 7, 0.5);
            color: inherit;
            border-radius: 2px;
            padding: 1px 2px;
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

        @media (max-width: 980px) {
            .section-header {
                flex-wrap: wrap;
            }
            .section-header .search-container {
                flex-basis: 100%;
                min-width: 100%;
            }
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

        main {
            transition: margin-right 0.3s ease;
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
        <form method="post" style="margin-left: 10px; display:inline;">
            <input type="hidden" name="action" value="toggle_auto_trim_logs">
            <button class="refresh-button" type="submit" title="Toggle auto-trim logs on server startup (keeps last 10,000 lines)">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" style="margin-right:6px;">
                    <path d="M8 3a5 5 0 1 0 0 10A5 5 0 0 0 8 3zm0-2a7 7 0 1 1 0 14A7 7 0 0 1 8 1z"/>
                </svg>
                Auto-trim: <?php echo $autoTrimEnabled ? 'On' : 'Off'; ?>
            </button>
        </form>
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
        <button class="refresh-button" id="timezoneToggle" style="margin-left: 10px;" title="Toggle between UTC and local browser time">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" style="margin-right:6px;">
                <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/>
                <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/>
            </svg>
            Timezone: UTC
        </button>
    </div>
    <div class="logs-kagrenac-layout">
        <div class="logs-column">
            <h2>Last 2000 lines from each log are displayed here. The full logs can be found in the /log folder of the CHIM server. <a href="/HerikaServer/log" target="_blank">View the log folder.</a></h2>
            <div class="grid-container" id="logGrid">
        <div class="log-section">
            <?php
            // Display Apache error log
            readErrorLog($distroLogPath, "Apache Log (apache_error.log)");
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
            // Display LLM context fast log
            if (file_exists($llmContextFastPath) && is_readable($llmContextFastPath)) {
                readLLMContextLog($llmContextFastPath, "LLM Context Fast (context_sent_to_llm_fast.log)");
            } else {
                echo '<p class="error-message">Log file not found or not readable at: ' . htmlspecialchars($llmContextFastPath) . '</p>';
            }
            ?>
        </div>

        <div class="log-section">
            <?php
            // Display plugin output log
            readRegularLog($pluginOutputPath, "Plugin Output (output_to_plugin.log)");
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
            // Display Monitor log
            readRegularLog($monitorLogPath, "Monitor Log (monitor.log)");
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
            // Display Service log
            readRegularLog($serviceLogPath, "Service Log (service.log)");
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
                        $isoTimestamp = $time->format('c'); // ISO 8601 format for conversion
                        
                        echo '<div class="log-entry error-entry">';
                        echo '<div class="timestamp" data-utc="' . htmlspecialchars($isoTimestamp) . '" data-timezone-label="UTC">' . htmlspecialchars($timestamp) . ' UTC</div>';
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
            readRegularLog($debugStreamLogPath, "Debug Stream Log (debugStream.log)");
            ?>
        </div>
            </div>
        </div>
        <aside class="kagrenac-panel" id="kagrenacPanel">
            <div class="kagrenac-header">
                <div class="kagrenac-header-row">
                    <h2>Ask Kagrenac</h2>
                    <button id="kagToggleSettingsBtn" type="button" class="kagrenac-toggle-settings">Settings</button>
                </div>
                <div class="kagrenac-subtitle">Analyze logs and files with MCP tools.</div>
            </div>
            <div class="kagrenac-settings">
                <div>
                    <label for="kagConnector">LLM Connector</label>
                    <select id="kagConnector"></select>
                </div>
                <div>
                    <label for="kagApiBadge">API Badge</label>
                    <select id="kagApiBadge"></select>
                </div>
                <div>
                    <label for="kagModel">Model</label>
                    <input id="kagModel" type="text" placeholder="anthropic/claude-sonnet-4">
                </div>
                <div>
                    <label for="kagMaxRounds">Max Tool Rounds</label>
                    <input id="kagMaxRounds" type="number" min="1" max="30" step="1" placeholder="10">
                </div>
                <div>
                    <label for="kagSystemPrompt">System Prompt Override</label>
                    <textarea id="kagSystemPrompt" placeholder="Leave blank to use server default prompt"></textarea>
                </div>
                <div class="settings-actions">
                    <button class="settings-btn" id="kagSaveSettingsBtn" type="button">Save Settings</button>
                    <button class="settings-btn" id="kagReloadSettingsBtn" type="button">Reload</button>
                </div>
            </div>
            <div class="kagrenac-chat">
                <div class="kagrenac-chat-history" id="kagChatHistory"></div>
                <div class="kagrenac-chat-input">
                    <textarea id="kagUserInput" placeholder="Ask about current errors, log patterns, or config issues..."></textarea>
                    <div id="kagProcessingHint" class="kag-processing-hint" aria-live="polite">
                        <span class="kag-processing-spinner"></span>
                        <span>Kagrenac is analyzing your request...</span>
                    </div>
                    <button class="kagrenac-send" id="kagSendBtn" type="button">Send</button>
                </div>
            </div>
        </aside>
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

<div id="LLMContextFastcontextsenttollmfastlogModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">LLM Context Fast Log</h2>
            <button class="close-modal" onclick="closeModal('LLMContextFastcontextsenttollmfastlogModal')">&times;</button>
        </div>
        <div class="modal-search-container">
            <input type="text" class="modal-search-input" placeholder="Search in LLM Context Fast Log..." data-target="LLMContextFastcontextsenttollmfastlogModalContent">
        </div>
        <div class="modal-body">
            <div id="LLMContextFastcontextsenttollmfastlogModalContent"></div>
        </div>
    </div>
</div>

<div id="MonitorLogmonitorlogModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Monitor Log</h2>
            <button class="close-modal" onclick="closeModal('MonitorLogmonitorlogModal')">&times;</button>
        </div>
        <div class="modal-search-container">
            <input type="text" class="modal-search-input" placeholder="Search in Monitor Log..." data-target="MonitorLogmonitorlogModalContent">
        </div>
        <div class="modal-body">
            <div id="MonitorLogmonitorlogModalContent"></div>
        </div>
    </div>
</div>

<div id="ServiceLogservicelogModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Service Log</h2>
            <button class="close-modal" onclick="closeModal('ServiceLogservicelogModal')">&times;</button>
        </div>
        <div class="modal-search-container">
            <input type="text" class="modal-search-input" placeholder="Search in Service Log..." data-target="ServiceLogservicelogModalContent">
        </div>
        <div class="modal-body">
            <div id="ServiceLogservicelogModalContent"></div>
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
            
            // Re-apply timezone conversion if in local mode
            const timezoneMode = localStorage.getItem('chim_logs_timezone') || 'utc';
            if (timezoneMode === 'local' && typeof window.convertTimestamps === 'function') {
                setTimeout(() => {
                    window.convertTimestamps(true);
                }, 100);
            }
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

// Search functionality - non-destructive, just highlights and hides/shows
document.querySelectorAll('.search-input, .modal-search-input').forEach(input => {
    const targetId = input.getAttribute('data-target');
    const container = document.getElementById(targetId);

    function performSearch() {
        if (!container) return;

        const searchTerm = input.value.trim();
        
        // Get all entries
        const entries = container.querySelectorAll('.log-entry, .error-entry, .llm-block');
        
        // If search is empty, show all entries (except those hidden by filters) and remove highlights
        if (!searchTerm) {
            entries.forEach(entry => {
                // Remove search hiding, but keep filter hiding
                entry.classList.remove('hidden-by-search');
                // Remove all highlights
                entry.querySelectorAll('.highlight').forEach(highlight => {
                    const text = highlight.textContent;
                    highlight.replaceWith(text);
                });
            });
            return;
        }

        const searchLower = searchTerm.toLowerCase();
        let hasMatches = false;

        entries.forEach(entry => {
            // Remove any existing highlights from this entry before checking/applying new ones
            entry.querySelectorAll('.highlight').forEach(highlight => {
                const text = highlight.textContent;
                highlight.replaceWith(text);
            });
            
            // Normalize the entry to merge adjacent text nodes back together
            entry.normalize();
            
            const entryText = entry.textContent.toLowerCase();
            
            if (entryText.includes(searchLower)) {
                entry.classList.remove('hidden-by-search');
                hasMatches = true;
                
                // Highlight matches in text nodes only, preserving structure
                highlightInElement(entry, searchTerm);
            } else {
                entry.classList.add('hidden-by-search');
            }
        });

        // Show "no results" message if needed
        const existingNoResults = container.querySelector('.no-results');
        if (!hasMatches) {
            if (!existingNoResults) {
                const noResults = document.createElement('div');
                noResults.className = 'no-results';
                noResults.textContent = 'No matches found';
                container.appendChild(noResults);
            }
        } else {
            if (existingNoResults) {
                existingNoResults.remove();
            }
        }
    }

    // Function to highlight text within an element without breaking structure
    function highlightInElement(element, searchTerm) {
        const escapedTerm = searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const regex = new RegExp('(' + escapedTerm + ')', 'gi');
        
        // Get all text nodes
        const walker = document.createTreeWalker(
            element,
            NodeFilter.SHOW_TEXT,
            {
                acceptNode: function(node) {
                    // Skip if parent already has highlight or is a script/style
                    if (node.parentElement.classList.contains('highlight') ||
                        node.parentElement.tagName === 'SCRIPT' ||
                        node.parentElement.tagName === 'STYLE') {
                        return NodeFilter.FILTER_REJECT;
                    }
                    // Only process nodes that contain the search term
                    if (node.textContent.toLowerCase().includes(searchTerm.toLowerCase())) {
                        return NodeFilter.FILTER_ACCEPT;
                    }
                    return NodeFilter.FILTER_REJECT;
                }
            }
        );

        const textNodes = [];
        let node;
        while (node = walker.nextNode()) {
            textNodes.push(node);
        }

        // Process each text node
        textNodes.forEach(textNode => {
            const text = textNode.textContent;
            const fragment = document.createDocumentFragment();
            let lastIndex = 0;
            let hasMatch = false;
            
            text.replace(regex, (match, p1, offset) => {
                hasMatch = true;
                // Add text before match
                if (offset > lastIndex) {
                    fragment.appendChild(document.createTextNode(text.substring(lastIndex, offset)));
                }
                // Add highlighted match
                const mark = document.createElement('mark');
                mark.className = 'highlight';
                mark.textContent = match;
                fragment.appendChild(mark);
                lastIndex = offset + match.length;
                return match; // Return value doesn't matter, we're just iterating
            });
            
            // Only replace the node if we found matches
            if (hasMatch) {
                // Add remaining text
                if (lastIndex < text.length) {
                    fragment.appendChild(document.createTextNode(text.substring(lastIndex)));
                }
                textNode.replaceWith(fragment);
            }
        });
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
        
        // Apply timezone conversion if in local mode
        const timezoneMode = localStorage.getItem('chim_logs_timezone') || 'utc';
        if (timezoneMode === 'local' && typeof window.convertTimestamps === 'function') {
            setTimeout(() => {
                window.convertTimestamps(true);
            }, 50);
        }
        
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
                            const escapedSearchTerm = searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                            const regex = new RegExp(escapedSearchTerm, 'gi');
                            const messages = block.querySelectorAll('.llm-content');
                            messages.forEach(msg => {
                                const text = msg.textContent;
                                if (text.match(regex)) {
                                    msg.innerHTML = text.replace(regex, match => '<span class="highlight">' + match + '</span>');
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
                            const escapedSearchTerm = searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                            regex = new RegExp(escapedSearchTerm, 'gi');
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

                    const highlightedContent = textContent.replace(regex, match => '<span class="highlight">' + match + '</span>');
                    content.innerHTML = '<pre class="log-content">' + highlightedContent + '</pre>';
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
    mark.highlight {
        background-color: rgba(255, 193, 7, 0.5);
        color: inherit;
        border-radius: 2px;
        padding: 1px 2px;
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

// JavaScript for copy to clipboard (with iframe fallback)
// Fallback copy function for iframes and older browsers
function copyToClipboardFallback(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.top = '0';
    textarea.style.left = '0';
    textarea.style.width = '2em';
    textarea.style.height = '2em';
    textarea.style.padding = '0';
    textarea.style.border = 'none';
    textarea.style.outline = 'none';
    textarea.style.boxShadow = 'none';
    textarea.style.background = 'transparent';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();
    
    let success = false;
    try {
        success = document.execCommand('copy');
    } catch (err) {
        console.error('Fallback copy failed:', err);
    }
    
    document.body.removeChild(textarea);
    return success;
}

// Main copy function with fallback
function copyToClipboard(text) {
    // Try modern clipboard API first
    if (navigator.clipboard && navigator.clipboard.writeText) {
        return navigator.clipboard.writeText(text)
            .then(() => true)
            .catch(err => {
                console.warn('Clipboard API failed, using fallback:', err);
                return copyToClipboardFallback(text);
            });
    } else {
        // Use fallback immediately if clipboard API not available
        return Promise.resolve(copyToClipboardFallback(text));
    }
}

// No need for DOMContentLoaded since this script runs at the end of the body
document.body.addEventListener('click', function(event) {
    // Use closest to handle clicks on the button or any child elements (like the emoji)
    const copyBtn = event.target.closest('.copy-llm-btn');
    if (copyBtn) {
        const llmBlock = copyBtn.closest('.llm-block');
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
                copyToClipboard(contentToCopy)
                    .then((success) => {
                        if (success) {
                            copyBtn.textContent = '✅'; // Copied!
                            setTimeout(() => {
                                copyBtn.textContent = '📋'; // Reset icon
                            }, 1500);
                        } else {
                            copyBtn.textContent = '❌'; // Error
                            setTimeout(() => {
                                copyBtn.textContent = '📋'; // Reset icon
                            }, 1500);
                        }
                    })
                    .catch(err => {
                        console.error('Failed to copy text: ', err);
                        copyBtn.textContent = '❌'; // Error
                        setTimeout(() => {
                            copyBtn.textContent = '📋'; // Reset icon
                        }, 1500);
                    });
            } else {
                console.warn('No content found to copy in LLM block:', llmBlock);
                copyBtn.textContent = '❓'; // No content
                setTimeout(() => {
                    copyBtn.textContent = '📋'; // Reset icon
                }, 1500);
            }
        }
    }
});

// Timezone conversion functionality
(function() {
    const TIMEZONE_KEY = 'chim_logs_timezone';
    const UTC_MODE = 'utc';
    const LOCAL_MODE = 'local';
    
    // Get current timezone preference from localStorage (default: UTC)
    function getTimezoneMode() {
        return localStorage.getItem(TIMEZONE_KEY) || UTC_MODE;
    }
    
    // Save timezone preference to localStorage
    function setTimezoneMode(mode) {
        localStorage.setItem(TIMEZONE_KEY, mode);
    }
    
    // Convert ISO 8601 timestamp to local time string
    function toLocalTime(isoString) {
        try {
            const date = new Date(isoString);
            if (isNaN(date.getTime())) {
                return isoString; // Return original if parsing fails
            }
            // Format as: YYYY-MM-DD HH:MM:SS (local timezone)
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const seconds = String(date.getSeconds()).padStart(2, '0');
            const tzOffset = -date.getTimezoneOffset();
            const tzHours = Math.floor(Math.abs(tzOffset) / 60);
            const tzMinutes = Math.abs(tzOffset) % 60;
            const tzSign = tzOffset >= 0 ? '+' : '-';
            const tzString = `${tzSign}${String(tzHours).padStart(2, '0')}:${String(tzMinutes).padStart(2, '0')}`;
            return `${year}-${month}-${day} ${hours}:${minutes}:${seconds} ${tzString}`;
        } catch (e) {
            return isoString;
        }
    }
    
    // Convert local time string back to UTC ISO 8601
    function toUTC(isoString) {
        try {
            const date = new Date(isoString);
            if (isNaN(date.getTime())) {
                return isoString;
            }
            // Return ISO 8601 format in UTC
            return date.toISOString();
        } catch (e) {
            return isoString;
        }
    }
    
    // Format UTC timestamp (YYYY-MM-DD HH:MM:SS format)
    function formatUTCTimestamp(isoString) {
        try {
            const date = new Date(isoString);
            if (isNaN(date.getTime())) {
                return isoString;
            }
            const year = date.getUTCFullYear();
            const month = String(date.getUTCMonth() + 1).padStart(2, '0');
            const day = String(date.getUTCDate()).padStart(2, '0');
            const hours = String(date.getUTCHours()).padStart(2, '0');
            const minutes = String(date.getUTCMinutes()).padStart(2, '0');
            const seconds = String(date.getUTCSeconds()).padStart(2, '0');
            return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
        } catch (e) {
            return isoString;
        }
    }
    
    // Store original timestamp text for restoration
    const originalTimestamps = new Map();
    
    // Convert all timestamps on the page
    function convertTimestamps(toLocal) {
        // Handle all timestamps with data-utc attribute (database errors, regular logs, Apache logs)
        document.querySelectorAll('.timestamp[data-utc]').forEach(el => {
            const utcValue = el.getAttribute('data-utc');
            const timezoneLabel = el.getAttribute('data-timezone-label') || '';
            
            // Store original text if not already stored
            if (!originalTimestamps.has(el)) {
                originalTimestamps.set(el, el.textContent);
            }
            
            if (toLocal) {
                const localTime = toLocalTime(utcValue);
                // Only add (Local) label if it was a database error (has timezone-label attribute)
                if (timezoneLabel) {
                    el.textContent = localTime + ' (Local)';
                } else {
                    el.textContent = localTime;
                }
            } else {
                // Restore original timestamp text
                el.textContent = originalTimestamps.get(el);
            }
        });
        
        // Handle LLM block timestamps (time-value spans)
        document.querySelectorAll('.time-value[data-utc]').forEach(el => {
            const utcValue = el.getAttribute('data-utc');
            
            // Store original text if not already stored
            if (!originalTimestamps.has(el)) {
                originalTimestamps.set(el, el.textContent);
            }
            
            if (toLocal) {
                const localTime = toLocalTime(utcValue);
                el.textContent = localTime;
            } else {
                // Restore original ISO 8601 format
                el.textContent = originalTimestamps.get(el);
            }
        });
    }
    
    // Expose convertTimestamps globally for refresh functionality
    window.convertTimestamps = convertTimestamps;
    
    // Initialize timezone toggle
    document.addEventListener('DOMContentLoaded', function() {
        const timezoneToggle = document.getElementById('timezoneToggle');
        if (!timezoneToggle) return;
        
        // Set initial state
        const currentMode = getTimezoneMode();
        const isLocal = currentMode === LOCAL_MODE;
        
        // Update button text
        timezoneToggle.textContent = isLocal ? 'Timezone: Local' : 'Timezone: UTC';
        if (isLocal) {
            timezoneToggle.innerHTML = '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" style="margin-right:6px;"><path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/></svg>Timezone: Local';
        }
        
        // Store original timestamps before any conversion
        document.querySelectorAll('.timestamp[data-utc], .time-value[data-utc]').forEach(el => {
            if (!originalTimestamps.has(el)) {
                originalTimestamps.set(el, el.textContent);
            }
        });
        
        // Convert timestamps on initial load if needed
        if (isLocal) {
            convertTimestamps(true);
        }
        
        // Handle toggle click
        timezoneToggle.addEventListener('click', function() {
            const currentMode = getTimezoneMode();
            const newMode = currentMode === UTC_MODE ? LOCAL_MODE : UTC_MODE;
            const toLocal = newMode === LOCAL_MODE;
            
            setTimezoneMode(newMode);
            convertTimestamps(toLocal);
            
            // Update button text
            if (toLocal) {
                this.innerHTML = '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" style="margin-right:6px;"><path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/></svg>Timezone: Local';
            } else {
                this.innerHTML = '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" style="margin-right:6px;"><path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/></svg>Timezone: UTC';
            }
        });
        
        // Re-convert timestamps when modals are opened (in case they were updated)
        const originalOpenModal = window.openModal;
        if (typeof originalOpenModal === 'function') {
            window.openModal = function(modalId, sourceId) {
                originalOpenModal(modalId, sourceId);
                const currentMode = getTimezoneMode();
                if (currentMode === LOCAL_MODE) {
                    setTimeout(() => {
                        if (typeof window.convertTimestamps === 'function') {
                            window.convertTimestamps(true);
                        }
                    }, 100);
                }
            };
        }
    });
})();

// Log Level Filtering System
(function() {
    const STORAGE_KEY_PREFIX = 'logLevelFilters_';
    
    // Initialize filter counts and set up listeners
    function initializeLogFilters() {
        // Get all unique container IDs from filter checkboxes
        const containers = new Set();
        document.querySelectorAll('.level-filter').forEach(checkbox => {
            containers.add(checkbox.dataset.container);
        });
        
        // Initialize each container
        containers.forEach(containerId => {
            updateLogCounts(containerId);
            loadFilterPreferences(containerId);
            applyFilters(containerId);
        });
        
        // Set up event listeners for all filter checkboxes
        document.querySelectorAll('.level-filter').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const containerId = this.dataset.container;
                applyFilters(containerId);
                saveFilterPreferences(containerId);
            });
        });
    }
    
    // Count log entries by level and update badges
    function updateLogCounts(containerId) {
        const container = document.getElementById(containerId + 'Container');
        if (!container) return;
        
        const counts = {
            error: 0,
            warn: 0,
            warning: 0,
            notice: 0,
            info: 0,
            debug: 0,
            trace: 0
        };
        
        // Count entries
        container.querySelectorAll('.log-entry[data-level]').forEach(entry => {
            const level = entry.dataset.level.toLowerCase();
            if (counts.hasOwnProperty(level)) {
                counts[level]++;
            }
        });
        
        // Combine warn and warning counts
        counts.warn += counts.warning;
        
        // Update count displays
        Object.keys(counts).forEach(level => {
            if (level !== 'warning') { // Skip warning as it's combined with warn
                const countEl = document.getElementById(`${containerId}-${level}-count`);
                if (countEl) {
                    countEl.textContent = counts[level];
                }
            }
        });
    }
    
    // Apply filters based on checkbox states
    function applyFilters(containerId) {
        const container = document.getElementById(containerId + 'Container');
        if (!container) return;
        
        // Get enabled levels
        const enabledLevels = new Set();
        document.querySelectorAll(`.level-filter[data-container="${containerId}"]`).forEach(checkbox => {
            if (checkbox.checked) {
                enabledLevels.add(checkbox.dataset.level.toLowerCase());
            }
        });
        
        // Apply filters to log entries
        let visibleCount = 0;
        container.querySelectorAll('.log-entry[data-level]').forEach(entry => {
            const level = entry.dataset.level.toLowerCase();
            // Handle both 'warn' and 'warning' levels
            const shouldShow = enabledLevels.has(level) || 
                             (level === 'warning' && enabledLevels.has('warn'));
            
            if (shouldShow) {
                entry.classList.remove('hidden-by-filter');
                visibleCount++;
            } else {
                entry.classList.add('hidden-by-filter');
            }
        });
        
        // Show "no results" message if all entries are filtered out
        const existingMessage = container.querySelector('.no-filter-results');
        if (visibleCount === 0 && container.querySelectorAll('.log-entry[data-level]').length > 0) {
            if (!existingMessage) {
                const message = document.createElement('p');
                message.className = 'no-filter-results info-message';
                message.style.cssText = 'color: #888; padding: 20px; text-align: center;';
                message.textContent = 'No log entries match the selected filters.';
                container.appendChild(message);
            }
        } else if (existingMessage) {
            existingMessage.remove();
        }
    }
    
    // Save filter preferences to localStorage
    function saveFilterPreferences(containerId) {
        const preferences = {};
        document.querySelectorAll(`.level-filter[data-container="${containerId}"]`).forEach(checkbox => {
            preferences[checkbox.dataset.level] = checkbox.checked;
        });
        localStorage.setItem(STORAGE_KEY_PREFIX + containerId, JSON.stringify(preferences));
    }
    
    // Load filter preferences from localStorage
    function loadFilterPreferences(containerId) {
        const stored = localStorage.getItem(STORAGE_KEY_PREFIX + containerId);
        if (!stored) return;
        
        try {
            const preferences = JSON.parse(stored);
            document.querySelectorAll(`.level-filter[data-container="${containerId}"]`).forEach(checkbox => {
                const level = checkbox.dataset.level;
                if (preferences.hasOwnProperty(level)) {
                    checkbox.checked = preferences[level];
                }
            });
        } catch (e) {
            console.error('Failed to load filter preferences:', e);
        }
    }
    
    // Global functions for select all/none buttons
    window.selectAllLevels = function(containerId) {
        document.querySelectorAll(`.level-filter[data-container="${containerId}"]`).forEach(checkbox => {
            checkbox.checked = true;
        });
        applyFilters(containerId);
        saveFilterPreferences(containerId);
    };
    
    window.selectNoLevels = function(containerId) {
        document.querySelectorAll(`.level-filter[data-container="${containerId}"]`).forEach(checkbox => {
            checkbox.checked = false;
        });
        applyFilters(containerId);
        saveFilterPreferences(containerId);
    };
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeLogFilters);
    } else {
        initializeLogFilters();
    }
})();
</script>

<!-- Ask Kagrenac embedded panel -->
<script>
(function() {
    const mcpServerUrl = 'http://<?php echo htmlspecialchars($mcpHost, ENT_QUOTES, 'UTF-8'); ?>:3100';
    const configApiUrl = '<?php echo $webRoot; ?>/ui/api/chim_mcp_config.php';
    const kagAvatarUrl = '<?php echo $webRoot; ?>/ui/images/metaphysics.png';
    const sendBtn = document.getElementById('kagSendBtn');
    const inputEl = document.getElementById('kagUserInput');
    const historyEl = document.getElementById('kagChatHistory');
    const connectorEl = document.getElementById('kagConnector');
    const apiBadgeEl = document.getElementById('kagApiBadge');
    const modelEl = document.getElementById('kagModel');
    const maxRoundsEl = document.getElementById('kagMaxRounds');
    const systemPromptEl = document.getElementById('kagSystemPrompt');
    const saveSettingsBtn = document.getElementById('kagSaveSettingsBtn');
    const reloadSettingsBtn = document.getElementById('kagReloadSettingsBtn');
    const toggleSettingsBtn = document.getElementById('kagToggleSettingsBtn');
    const settingsContainer = document.querySelector('.kagrenac-settings');
    const processingHintEl = document.getElementById('kagProcessingHint');

    if (!sendBtn || !inputEl || !historyEl) {
        return;
    }

    let isSending = false;
    let thinkingRow = null;

    function addMessage(role, text) {
        const row = document.createElement('div');
        row.className = 'kag-msg-row ' + role;

        if (role === 'assistant') {
            const avatar = document.createElement('img');
            avatar.className = 'kag-avatar';
            avatar.src = kagAvatarUrl;
            avatar.alt = 'Kagrenac';
            row.appendChild(avatar);
        }

        const msg = document.createElement('div');
        msg.className = 'kag-msg ' + role;
        const body = document.createElement('div');
        body.textContent = text;
        msg.appendChild(body);

        const meta = document.createElement('div');
        meta.className = 'kag-msg-meta';
        meta.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        msg.appendChild(meta);

        row.appendChild(msg);
        historyEl.appendChild(row);
        historyEl.scrollTop = historyEl.scrollHeight;
    }

    function setSendingState(sending) {
        isSending = sending;
        sendBtn.disabled = sending;
        inputEl.disabled = sending;
        sendBtn.textContent = sending ? 'Processing...' : 'Send';
        if (processingHintEl) {
            processingHintEl.classList.toggle('active', sending);
        }
        if (sending) {
            showThinkingMessage();
        } else {
            hideThinkingMessage();
        }
    }

    function showThinkingMessage() {
        if (thinkingRow) {
            return;
        }

        const row = document.createElement('div');
        row.className = 'kag-msg-row assistant thinking';

        const avatar = document.createElement('img');
        avatar.className = 'kag-avatar';
        avatar.src = kagAvatarUrl;
        avatar.alt = 'Kagrenac';
        row.appendChild(avatar);

        const msg = document.createElement('div');
        msg.className = 'kag-msg assistant';

        const body = document.createElement('div');
        body.textContent = 'Kagrenac is calibrating tonal resonators';

        const dots = document.createElement('span');
        dots.className = 'kag-typing-dots';
        dots.innerHTML = '<span></span><span></span><span></span>';
        body.appendChild(dots);
        msg.appendChild(body);

        row.appendChild(msg);
        historyEl.appendChild(row);
        historyEl.scrollTop = historyEl.scrollHeight;
        thinkingRow = row;
    }

    function hideThinkingMessage() {
        if (!thinkingRow) {
            return;
        }
        thinkingRow.remove();
        thinkingRow = null;
    }

    async function loadApiBadges() {
        if (!apiBadgeEl) {
            return;
        }
        apiBadgeEl.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Select API Badge';
        apiBadgeEl.appendChild(placeholder);

        const response = await fetch(configApiUrl + '?badges=1');
        const payload = await response.json();
        if (!payload || !payload.success || !payload.data || !Array.isArray(payload.data.badges)) {
            throw new Error('Failed to load API badges');
        }

        payload.data.badges.forEach((badge) => {
            const option = document.createElement('option');
            option.value = String(badge.id);
            option.textContent = badge.label;
            apiBadgeEl.appendChild(option);
        });
    }

    async function loadConnectors() {
        if (!connectorEl) {
            return;
        }
        connectorEl.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Use MCP default / API Badge';
        connectorEl.appendChild(placeholder);

        const response = await fetch(configApiUrl + '?connectors=1');
        const payload = await response.json();
        if (!payload || !payload.success || !payload.data || !Array.isArray(payload.data.connectors)) {
            throw new Error('Failed to load connectors');
        }

        payload.data.connectors.forEach((connector) => {
            const option = document.createElement('option');
            option.value = String(connector.id);
            const service = connector.service ? String(connector.service).toUpperCase() : 'UNKNOWN';
            const label = connector.label ? String(connector.label) : ('Connector #' + String(connector.id));
            option.textContent = '[' + service + '] ' + label;
            connectorEl.appendChild(option);
        });
    }

    async function loadSettings() {
        const response = await fetch(configApiUrl);
        const payload = await response.json();
        if (!payload || !payload.success || !payload.data) {
            throw new Error('Failed to load MCP settings');
        }
        const cfg = payload.data;
        if (connectorEl) {
            connectorEl.value = cfg.llm_connector_id || '';
        }
        if (apiBadgeEl) {
            apiBadgeEl.value = cfg.api_badge_id || '';
        }
        if (modelEl) {
            modelEl.value = cfg.model || '';
        }
        if (maxRoundsEl) {
            maxRoundsEl.value = cfg.max_tool_rounds || '10';
        }
        if (systemPromptEl) {
            systemPromptEl.value = cfg.system_prompt || '';
        }
    }

    async function saveSettings() {
        const body = {
            llm_connector_id: connectorEl ? connectorEl.value : '',
            api_badge_id: apiBadgeEl ? apiBadgeEl.value : '',
            model: modelEl ? modelEl.value.trim() : '',
            max_tool_rounds: maxRoundsEl ? maxRoundsEl.value : '10',
            system_prompt: systemPromptEl ? systemPromptEl.value : '',
        };

        const response = await fetch(configApiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        const payload = await response.json();
        if (!payload || !payload.success) {
            throw new Error(payload && payload.error ? payload.error : 'Failed to save settings');
        }
    }

    async function sendMessage() {
        const content = inputEl.value.trim();
        if (!content || isSending) {
            return;
        }

        addMessage('user', content);
        inputEl.value = '';
        setSendingState(true);

        try {
            const response = await fetch(mcpServerUrl + '/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: content }),
            });

            if (!response.ok) {
                const text = await response.text();
                throw new Error(text || ('MCP request failed (' + response.status + ')'));
            }

            const payload = await response.json();
            const answer = payload && payload.response ? String(payload.response) : 'No response received.';
            addMessage('assistant', answer);
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unknown error';
            addMessage('error', 'Error: ' + message);
        } finally {
            setSendingState(false);
        }
    }

    sendBtn.addEventListener('click', sendMessage);
    inputEl.addEventListener('keydown', function(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    });

    if (saveSettingsBtn) {
        saveSettingsBtn.addEventListener('click', async function() {
            try {
                await saveSettings();
                addMessage('assistant', 'Settings saved.');
            } catch (error) {
                const message = error instanceof Error ? error.message : 'Unknown error';
                addMessage('error', 'Error saving settings: ' + message);
            }
        });
    }

    if (reloadSettingsBtn) {
        reloadSettingsBtn.addEventListener('click', async function() {
            try {
                await loadConnectors();
                await loadApiBadges();
                await loadSettings();
                addMessage('assistant', 'Settings reloaded.');
            } catch (error) {
                const message = error instanceof Error ? error.message : 'Unknown error';
                addMessage('error', 'Error loading settings: ' + message);
            }
        });
    }

    if (toggleSettingsBtn && settingsContainer) {
        toggleSettingsBtn.addEventListener('click', function() {
            settingsContainer.classList.toggle('hidden');
            toggleSettingsBtn.textContent = settingsContainer.classList.contains('hidden') ? 'Settings' : 'Hide';
        });
    }

    (async function initKagrenacPanel() {
        addMessage('assistant', 'Ask Kagrenac is ready. I can inspect logs, MCP config, and HerikaServer files.');
        try {
            await loadConnectors();
            await loadApiBadges();
            await loadSettings();
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unknown error';
            addMessage('error', 'Settings unavailable: ' + message);
        }
    })();
})();
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
