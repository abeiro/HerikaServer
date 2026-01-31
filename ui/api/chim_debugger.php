<?php
/**
 * CHIM Debugger API
 * Returns recent AI/TTS timing data from the log table
 * 
 * Params:
 *   - limit: Number of recent entries to return (default: 20)
 */

error_reporting(E_ERROR);
session_start();

// Define base paths
define('BASE_PATH', dirname(dirname(__DIR__)));
define('CONFIG_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'conf');
define('LIB_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'lib');

$configFilepath = CONFIG_PATH . DIRECTORY_SEPARATOR;

if (!file_exists($configFilepath."conf.php")) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Configuration file not found']);
    exit;
}

// Load profiles through the centralized profile loader
require_once(dirname(__DIR__).DIRECTORY_SEPARATOR."profile_loader.php");

require_once(LIB_PATH .DIRECTORY_SEPARATOR."logger.php");
require_once(LIB_PATH .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");

$db = new sql();

header('Content-Type: application/json');

try {
    $limit = isset($_GET['limit']) ? min(max(1, intval($_GET['limit'])), 50) : 20;
    
    // Query recent log entries that contain timing data
    $query = "SELECT localts, response, url FROM log WHERE url LIKE '%[AI secs]%' ORDER BY localts DESC LIMIT {$limit}";
    $results = $db->fetchAll($query);
    
    if (!$results) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'avg_ai_time' => 0.0,
            'avg_tts_time' => 0.0,
            'avg_total_time' => 0.0
        ]);
        exit;
    }
    
    $timingData = [];
    $totalAiTime = 0.0;
    $totalTtsTime = 0.0;
    $count = 0;
    
    foreach ($results as $row) {
        // Parse timing data from url field: "[AI secs] X [TTS secs] Y"
        $pattern = '/\[AI secs\]\s+([\d.]+)\s+\[TTS secs\]\s+([\d.]+)/';
        if (preg_match($pattern, $row['url'], $matches)) {
            $aiTime = floatval($matches[1]);
            $ttsTime = floatval($matches[2]);
            $totalTime = $aiTime + $ttsTime;
            
            $totalAiTime += $aiTime;
            $totalTtsTime += $ttsTime;
            $count++;
            
            // Get response snippet (first 50 chars)
            $responseSnippet = mb_substr($row['response'], 0, 50);
            if (mb_strlen($row['response']) > 50) {
                $responseSnippet .= '...';
            }
            
            // Determine status based on total time
            $status = 'ok';
            if ($totalTime > 5.0) {
                $status = 'slow';
            } elseif ($totalTime > 2.0) {
                $status = 'moderate';
            }
            
            $timingData[] = [
                'timestamp' => $row['localts'],
                'ai_time' => round($aiTime, 2),
                'tts_time' => round($ttsTime, 2),
                'total_time' => round($totalTime, 2),
                'response_snippet' => $responseSnippet,
                'status' => $status
            ];
        }
    }
    
    // Calculate averages
    $avgAiTime = $count > 0 ? round($totalAiTime / $count, 2) : 0.0;
    $avgTtsTime = $count > 0 ? round($totalTtsTime / $count, 2) : 0.0;
    $avgTotalTime = $count > 0 ? round(($totalAiTime + $totalTtsTime) / $count, 2) : 0.0;
    
    echo json_encode([
        'success' => true,
        'data' => $timingData,
        'avg_ai_time' => $avgAiTime,
        'avg_tts_time' => $avgTtsTime,
        'avg_total_time' => $avgTotalTime,
        'count' => $count
    ]);
    
} catch (Exception $e) {
    error_log("CHIM Debugger API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
}
