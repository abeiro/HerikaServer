<?php
error_reporting(E_ERROR);
session_start();

// Define base paths
define('BASE_PATH', dirname(dirname(__DIR__)));
define('CONFIG_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'conf');
define('LIB_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'lib');

$configFilepath = CONFIG_PATH . DIRECTORY_SEPARATOR;

if (!file_exists($configFilepath."conf.php")) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration file not found']);
    exit;
}

// Load profiles through the centralized profile loader
require_once(dirname(__DIR__).DIRECTORY_SEPARATOR."profile_loader.php");

require_once(LIB_PATH .DIRECTORY_SEPARATOR."logger.php");
require_once(LIB_PATH .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once(LIB_PATH .DIRECTORY_SEPARATOR."utils_game_timestamp.php");

$db = new sql();

// Set JSON header
header('Content-Type: application/json');

// Determine which data to return
$mode = isset($_GET['list']) ? $_GET['list'] : null;
$person = isset($_GET['person']) ? $_GET['person'] : null;
$entryId = isset($_GET['entry']) ? intval($_GET['entry']) : null;

function diaryAudioEndpoint(int $entryId): string
{
    $scriptPath = strval($_SERVER['SCRIPT_NAME'] ?? '');
    $uiPosition = strpos($scriptPath, '/ui/');
    $webRoot = $uiPosition !== false ? substr($scriptPath, 0, $uiPosition) : '';
    $host = trim(strval($_SERVER['HTTP_HOST'] ?? ''));
    $isHttps = !empty($_SERVER['HTTPS']) && strtolower(strval($_SERVER['HTTPS'])) !== 'off';
    $origin = $host !== '' ? (($isHttps ? 'https' : 'http') . '://' . $host) : '';

    return $origin . rtrim($webRoot, '/') . '/ui/api/chim_diary_audio.php?entry=' . $entryId;
}

if ($mode === 'people') {
    // Return list of all people with diary entry counts
    $query = "
        WITH split_people AS (
            SELECT 
                d.rowid,
                trim(unnest(string_to_array(trim(d.people, '|'), '|'))) as person
            FROM diarylog d
            WHERE d.people IS NOT NULL AND d.people != ''
            AND d.topic NOT IN ('Sent Letter', 'Journal Note')
        )
        SELECT 
            person as name,
            COUNT(DISTINCT rowid) as count
        FROM split_people
        WHERE person != ''
        GROUP BY person
        ORDER BY count DESC, person ASC
    ";
    
    $result = $db->fetchAll($query);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'people' => $result
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to fetch people list'
        ]);
    }
    
} elseif ($person) {
    // Return all diary entries for a specific person (newest first)
    $personEsc = $db->escape($person);
    
    $query = "
        SELECT 
            rowid, 
            topic, 
            content, 
            people, 
            location, 
            localts, 
            gamets
        FROM diarylog
        WHERE people LIKE '%$personEsc%'
        AND topic NOT IN ('Sent Letter', 'Journal Note')
        ORDER BY localts DESC
    ";
    
    $results = $db->fetchAll($query);
    
    if ($results) {
        $entries = [];
        foreach ($results as $row) {
            // Create preview (first 80 characters)
            $content = $row['content'];
            $preview = strlen($content) > 80 ? substr($content, 0, 80) . '...' : $content;
            
            // Format Tamrielic date if gamets available
            $tamrielicDate = '';
            if (isset($row['gamets']) && $row['gamets'] > 0) {
                $tamrielicDate = convert_gamets2skyrim_long_date_no_time($row['gamets']);
            }
            
            // Extract location from the location string
            $locationStr = trim($row['location'], '()');
            $location = 'Unknown';
            if (preg_match('/Context new location:\s*([^,]+)/i', $locationStr, $matches)) {
                $location = trim($matches[1]);
            } elseif (preg_match('/Hold:\s*([^,]+)/i', $locationStr, $matches)) {
                $location = trim($matches[1]);
            }
            
            $entries[] = [
                'rowid' => intval($row['rowid']),
                'preview' => $preview,
                'date' => $tamrielicDate,
                'location' => $location,
                'localts' => intval($row['localts'])
            ];
        }
        
        echo json_encode([
            'success' => true,
            'person' => $person,
            'entries' => $entries
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'person' => $person,
            'entries' => []
        ]);
    }
    
} elseif ($entryId) {
    // Return a single diary entry by rowid
    $query = "
        SELECT 
            rowid, 
            topic, 
            content, 
            people, 
            location, 
            localts, 
            gamets
        FROM diarylog
        WHERE rowid = $entryId
        LIMIT 1
    ";
    
    $result = $db->fetchOne($query);
    
    if ($result) {
        // Format Tamrielic date
        $tamrielicDate = '';
        if (isset($result['gamets']) && $result['gamets'] > 0) {
            $tamrielicDate = convert_gamets2skyrim_long_date2($result['gamets']);
        }
        
        // Extract location
        $locationStr = trim($result['location'], '()');
        $location = 'Unknown';
        if (preg_match('/Context new location:\s*([^,]+)/i', $locationStr, $matches)) {
            $location = trim($matches[1]);
        } elseif (preg_match('/Hold:\s*([^,]+)/i', $locationStr, $matches)) {
            $location = trim($matches[1]);
        }
        
        // Get author from people field
        $peopleStr = trim($result['people'], '|');
        $peopleArray = !empty($peopleStr) ? explode('|', $peopleStr) : [];
        $author = !empty($peopleArray) ? $peopleArray[0] : 'Unknown';
        
        echo json_encode([
            'success' => true,
            'entry' => [
                'rowid' => intval($result['rowid']),
                'content' => $result['content'],
                'date' => $tamrielicDate,
                'author' => $author,
                'location' => $location,
                'audio_endpoint' => diaryAudioEndpoint(intval($result['rowid']))
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Entry not found'
        ]);
    }
    
} else {
    // Invalid request
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request. Use ?list=people, ?person=Name, or ?entry=123'
    ]);
}
