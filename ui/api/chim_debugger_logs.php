<?php
/**
 * CHIM Debugger - Log Reader API
 * Returns log file contents (last N lines)
 * 
 * Params:
 *   - type: Log type (chim, apache, context, output)
 *   - lines: Number of lines to return (default: 100, max: 2000)
 */

error_reporting(E_ERROR);
session_start();

// Define base paths
define('BASE_PATH', dirname(dirname(__DIR__)));
define('LOG_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'log');

header('Content-Type: application/json; charset=utf-8');

try {
    $type = isset($_GET['type']) ? trim($_GET['type']) : 'chim';
    $lines = isset($_GET['lines']) ? min(max(10, intval($_GET['lines'])), 2000) : 100;
    
    // Map log types to file paths
    // Apache error log needs special handling - try multiple locations
    $apacheLogPaths = [
        '/var/log/apache2/error.log',           // Linux default
        '/var/log/httpd/error_log',             // CentOS/RHEL
        BASE_PATH . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . 'apache_error.log',  // Local copy
        LOG_PATH . DIRECTORY_SEPARATOR . 'error.log'  // Fallback
    ];
    
    $apacheLog = null;
    foreach ($apacheLogPaths as $path) {
        if (file_exists($path) && is_readable($path)) {
            $apacheLog = $path;
            break;
        }
    }
    
    $logFiles = [
        'chim' => LOG_PATH . DIRECTORY_SEPARATOR . 'chim.log',
        'apache' => $apacheLog,
        'context' => LOG_PATH . DIRECTORY_SEPARATOR . 'context_sent_to_llm.log',
        'output' => LOG_PATH . DIRECTORY_SEPARATOR . 'output_from_llm.log'
    ];
    
    if (!isset($logFiles[$type])) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid log type'
        ]);
        exit;
    }
    
    $logFile = $logFiles[$type];
    
    // Special handling for Apache log which might not be accessible
    if ($type === 'apache' && ($logFile === null || !file_exists($logFile))) {
        echo json_encode([
            'success' => true,
            'lines' => [['text' => 'Apache error log not accessible from Docker container. Check your host system logs.', 'type' => 'warning']],
            'message' => 'Apache log not found in standard locations'
        ]);
        exit;
    }
    
    error_log("CHIM Debugger: Fetching log type '{$type}' from: {$logFile}");
    
    if (!file_exists($logFile)) {
        echo json_encode([
            'success' => true,
            'lines' => [],
            'message' => 'Log file not found: ' . basename($logFile)
        ]);
        exit;
    }
    
    // Read last N lines efficiently
    $logLines = tailFile($logFile, $lines);
    
    // Parse lines and classify by type (error, warning, info)
    $parsedLines = [];
    foreach ($logLines as $line) {
        // Clean the line thoroughly for JSON encoding
        $cleanLine = cleanForJson($line);
        
        if (empty(trim($cleanLine))) {
            continue;
        }
        
        $lineType = 'info';
        
        // Detect log level
        if (stripos($cleanLine, 'error') !== false || stripos($cleanLine, '[ERROR]') !== false) {
            $lineType = 'error';
        } elseif (stripos($cleanLine, 'warn') !== false || stripos($cleanLine, '[WARN]') !== false) {
            $lineType = 'warning';
        } elseif (stripos($cleanLine, 'debug') !== false || stripos($cleanLine, '[DEBUG]') !== false) {
            $lineType = 'debug';
        }
        
        $parsedLines[] = [
            'text' => $cleanLine,
            'type' => $lineType
        ];
    }
    
    $result = [
        'success' => true,
        'lines' => $parsedLines,
        'count' => count($parsedLines),
        'file' => basename($logFile)
    ];
    
    // First attempt with standard flags
    $json = @json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    
    // Check if json_encode failed
    if ($json === false || $json === null) {
        $jsonError = json_last_error_msg();
        error_log("CHIM Debugger: JSON encode failed: {$jsonError}");
        
        // Try again with aggressive ASCII-only approach
        $asciiLines = [];
        foreach ($parsedLines as $line) {
            // Strip all non-ASCII characters
            $asciiText = preg_replace('/[^\x20-\x7E\t]/', '?', $line['text']);
            $asciiLines[] = [
                'text' => $asciiText ?: '[unreadable]',
                'type' => $line['type']
            ];
        }
        
        $result['lines'] = $asciiLines;
        $result['encoding_note'] = 'ASCII fallback due to encoding issues';
        
        $json = json_encode($result, JSON_UNESCAPED_SLASHES);
        
        if ($json === false || $json === null) {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to encode log data after ASCII fallback'
            ]);
            exit;
        }
    }
    
    echo $json;
    
} catch (Exception $e) {
    error_log("CHIM Debugger Log API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
}

/**
 * Clean a string to be safe for JSON encoding
 * Uses aggressive cleaning to handle any encoding issues
 */
function cleanForJson($str) {
    if (empty($str)) {
        return '';
    }
    
    // Remove BOM if present
    $str = preg_replace('/^\xEF\xBB\xBF/', '', $str);
    
    // Remove null bytes
    $str = str_replace("\0", '', $str);
    
    // Remove carriage returns
    $str = str_replace("\r", '', $str);
    
    // Use iconv to strip any non-UTF8 characters aggressively
    // The //IGNORE flag will silently discard characters that cannot be represented
    $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $str);
    if ($cleaned === false) {
        // If iconv fails, try a different approach
        $cleaned = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $str);
        if ($cleaned === false) {
            // Last resort: strip all non-ASCII characters
            $cleaned = preg_replace('/[^\x20-\x7E\t\n]/', '', $str);
        }
    }
    $str = $cleaned ?: '';
    
    // Remove remaining control characters except tab and newline
    $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $str);
    
    return trim($str);
}

/**
 * Read last N lines from a file efficiently
 */
function tailFile($filepath, $lines = 100) {
    if (!file_exists($filepath)) {
        return [];
    }
    
    $file = @fopen($filepath, 'r');
    if ($file === false) {
        error_log("CHIM Debugger: Failed to open file: {$filepath}");
        return [];
    }
    
    // Get file size
    fseek($file, 0, SEEK_END);
    $fileSize = ftell($file);
    
    if ($fileSize == 0) {
        fclose($file);
        return [];
    }
    
    // Read in chunks from the end
    $buffer = min($fileSize, 8192);
    $position = $fileSize;
    $lineCount = 0;
    $content = '';
    
    while ($position > 0 && $lineCount <= $lines) {
        $position = max(0, $position - $buffer);
        fseek($file, $position);
        $chunk = fread($file, min($buffer, $fileSize - $position));
        $content = $chunk . $content;
        $lineCount = substr_count($content, "\n");
    }
    
    fclose($file);
    
    $allLines = explode("\n", $content);
    $lastLines = array_slice($allLines, -$lines);
    
    // Remove empty lines
    return array_filter($lastLines, function($line) {
        return trim($line) !== '';
    });
}
