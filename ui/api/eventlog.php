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
require_once(LIB_PATH .DIRECTORY_SEPARATOR."eventlog_helper.php");

$db = new sql();

// Set JSON header
header('Content-Type: application/json');

$limit = isset($_GET["limit"]) ? intval($_GET["limit"]) : 100;
$page = isset($_GET["page"]) ? max(1, intval($_GET["page"])) : 1;
$offset = ($page - 1) * $limit;
$sinceRowId = isset($_GET["since_rowid"]) ? intval($_GET["since_rowid"]) : 0;
$sinceGamets = isset($_GET["since_gamets"]) ? intval($_GET["since_gamets"]) : 0;
$selectedEventType = isset($_GET["event_type"]) ? trim((string)$_GET["event_type"]) : '';
$applySavedFilters = isset($_GET["use_saved_filters"]) && $_GET["use_saved_filters"];
$savedHiddenTypes = $applySavedFilters ? chimGetPersistedEventLogHiddenTypes($db) : [];

// Base event type filter for the HerikaServer Events page.
$typeFilter = chimBuildVisibleEventLogWhereClause($db, $selectedEventType, $savedHiddenTypes);

// If specific event types are requested (for MCM conversation history panel)
if (isset($_GET["event_types"]) && !empty($_GET["event_types"])) {
    $allowedTypes = explode(',', $_GET["event_types"]);
    $allowedTypes = array_map('trim', $allowedTypes);
    $allowedTypes = array_filter($allowedTypes);
    
    if (!empty($allowedTypes)) {
        // Sanitize and quote each type for SQL
        $quotedTypes = array_map(function($type) use ($db) {
            return "'" . $db->escape($type) . "'";
        }, $allowedTypes);
        
        $typeFilter = "type IN (" . implode(',', $quotedTypes) . ")";
        // Note: Background chat filtering is handled by JavaScript on the client side
    }
}

// Build query based on filtering options
if ($sinceGamets > 0) {
    // Filter by game timestamp - get events since a specific in-game time
    $results = $db->fetchAll(
        "SELECT type, data, people, gamets, localts, ts, rowid
         FROM eventlog a
         WHERE $typeFilter
         AND gamets >= $sinceGamets
         ORDER BY gamets DESC, ts DESC, localts DESC, rowid DESC
         LIMIT $limit"
    );
} else if ($sinceRowId > 0) {
    // Filter by rowid - for incremental updates
    $results = $db->fetchAll(
        "SELECT type, data, people, gamets, localts, ts, rowid
         FROM eventlog a
         WHERE $typeFilter
         AND rowid > $sinceRowId
         ORDER BY gamets DESC, ts DESC, localts DESC, rowid DESC
         LIMIT 50"
    );
} else {
    // Normal paginated query - get most recent events by game timestamp (gamets)
    $results = $db->fetchAll(
        "SELECT type, data, people, gamets, localts, ts, rowid
         FROM eventlog a
         WHERE $typeFilter
         ORDER BY gamets DESC, ts DESC, localts DESC, rowid DESC
         LIMIT $limit OFFSET $offset"
    );
}

// Check if raw format is requested (for in-game UI)
$rawFormat = isset($_GET["format"]) && $_GET["format"] === "raw";

$columnHeaders = [
    'type' => 'Event',
    'data' => 'Events',
    'gamets' => $rawFormat ? 'Tamrielic Time' : '<a href="https://en.uesp.net/wiki/Lore:Calendar" target="_blank" style="color: yellow;">Tamrielic Time</a>',
    'localts' => 'Time (UTC)',
    'ts' => 'TS',
];

$mappedResults = array_map(function ($row) use ($columnHeaders, $rawFormat) {
    $mappedRow = [];
    
    // Derive People Present from JSON in data if people field is empty
    $peoplePresent = trim((string)($row['people'] ?? ''));
    $rawData = $row['data'] ?? '';
    if ($peoplePresent === '' && is_string($rawData) && $rawData !== '') {
        $decoded = json_decode($rawData, true);
        if (is_array($decoded)) {
            if (!empty($decoded['people'])) {
                $peoplePresent = is_array($decoded['people']) ? implode(', ', $decoded['people']) : (string)$decoded['people'];
            } else if (!empty($decoded['companions'])) {
                $peoplePresent = is_array($decoded['companions']) ? implode(', ', $decoded['companions']) : (string)$decoded['companions'];
            } else if (!empty($decoded['speaker'])) {
                $peoplePresent = (string)$decoded['speaker'];
            }
        }
    }
    if (function_exists('chimRenderNarratorRoleplayText')) {
        $peoplePresent = chimRenderNarratorRoleplayText($peoplePresent);
    }
    
    foreach ($row as $key => $value) {
        if ($key === 'data' && function_exists('chimRenderNarratorRoleplayText')) {
            $value = chimRenderNarratorRoleplayText($value);
        }
        if ($key === 'gamets' && !empty($value)) {
            // Convert gamets to Skyrim date format
            $value = convert_gamets2skyrim_long_date2($value);
        }
        else if ($key === 'localts' && !empty($value)) {
            // Format localts to match adventure log format
            $dt = new DateTime("@$value");
            $dt->setTimezone(new DateTimeZone('UTC'));
            $value = $dt->format('d-m-Y H:i:s');
        }
        
        // Special handling for chat events (only add HTML styling for web view, not raw)
        if (!$rawFormat && $row['type'] === 'chat' && ($key === 'data' || $key === 'type')) {
            $value = '<span style="color:rgb(255, 255, 255);">' . htmlspecialchars($value) . '</span>';
        } else {
            $value = htmlspecialchars($value);
        }
        
        // Skip raw people field
        if ($key === 'people') {
            continue;
        }
        
        // Map rowid to uppercase ROWID for consistency with frontend
        $outputKey = ($key === 'rowid') ? 'ROWID' : ($columnHeaders[$key] ?? $key);
        $mappedRow[$outputKey] = $value;
    }
    
    // Add People Present field
    $mappedRow['People Present'] = htmlspecialchars($peoplePresent);
    
    return $mappedRow;
}, $results);

// Get total count for pagination info - also exclude location types
$countQuery = "SELECT COUNT(*) as total FROM eventlog WHERE $typeFilter";
$countResult = $db->fetchAll($countQuery);
$totalRecords = $countResult[0]['total'];
$totalPages = ceil($totalRecords / $limit);

// Get the latest gamets from the results for reference
$latestGamets = 0;
if (!empty($results) && isset($results[0]['gamets'])) {
    $latestGamets = intval($results[0]['gamets']);
}

$response = [
    'success' => true,
    'data' => $mappedResults,
    'narrator_name' => function_exists('chimGetNarratorRoleplayName')
        ? chimGetNarratorRoleplayName()
        : 'The Narrator',
    'timestamp' => time(),
    'new_count' => count($mappedResults),
    'latest_gamets' => $latestGamets
];

// Only include pagination if not doing incremental update
if ($sinceRowId === 0) {
    $response['pagination'] = [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_records' => $totalRecords,
        'limit' => $limit
    ];
}

echo json_encode($response);

