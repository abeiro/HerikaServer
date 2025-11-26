<?php
/**
 * Game Data Endpoint
 * 
 * Handles JSON POST requests for equipment, inventory, skills, and stats updates
 * for both NPCs and player data.
 * 
 * This endpoint does not trigger LLM requests - it only updates database metadata.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0'); // Don't output errors to response body
require_once(__DIR__ . "/conf/conf.php");
require_once(__DIR__ . "/lib/{$GLOBALS["DBDRIVER"]}.class.php");
$GLOBALS["db"] = new sql();
require_once(__DIR__ . "/lib/core/npc_master.class.php");
require_once(__DIR__ . "/lib/logger.php");

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

// Parse JSON body
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['type'])) {
    http_response_code(400);
    echo "Bad Request: Missing type field";
    Logger::error("[gamedata.php] Bad request - missing type field");
    exit;
}

// Validate required fields
if (!isset($data['actor_name']) || !isset($data['actor_type'])) {
    http_response_code(400);
    echo "Bad Request: Missing actor_name or actor_type";
    Logger::error("[gamedata.php] Bad request - missing actor_name or actor_type");
    exit;
}

$npcMaster = new NpcMaster($GLOBALS["db"]);

try {
    switch ($data['type']) {
        case 'equipment':
            handleEquipmentUpdate($data, $npcMaster);
            break;
        case 'inventory':
            handleInventoryUpdate($data, $npcMaster);
            break;
        case 'skills':
            handleSkillsUpdate($data, $npcMaster);
            break;
        case 'stats':
            handleStatsUpdate($data, $npcMaster);
            break;
        default:
            http_response_code(400);
            echo "Bad Request: Unknown type";
            Logger::error("[gamedata.php] Bad request - unknown type: {$data['type']}");
            exit;
    }
    
    echo "OK";
} catch (Exception $e) {
    http_response_code(500);
    echo "Internal Server Error";
    Logger::error("[gamedata.php] Error processing request: " . $e->getMessage());
}

/**
 * Handle equipment update
 */
function handleEquipmentUpdate(array $data, NpcMaster $npcMaster): void {
    $actorName = $data['actor_name'];
    $actorType = $data['actor_type'];
    
    if (!isset($data['equipment'])) {
        Logger::error("[gamedata.php] Equipment update missing equipment data");
        return;
    }
    
    $equipment = $data['equipment'];
    
    // Get or create NPC/Player record
    $currentData = $npcMaster->getByName($actorName);
    
    if (!$currentData) {
        // NPC/Player not in database yet - this is normal for NPCs not yet encountered
        if ($actorType === 'player') {
            Logger::warn("[gamedata.php] Player record not found, skipping equipment update for: {$actorName}");
        }
        // NPC not in database yet - this is normal, they haven't been encountered
        return;
    }
    
    // Get existing metadata
    $meta = [];
    if (!empty($currentData['metadata'])) {
        $meta = json_decode($currentData['metadata'], true);
        if (!is_array($meta)) {
            $meta = [];
        }
    }
    
    // Update equipment section
    $meta['equipment'] = [];
    foreach ($equipment as $slot => $item) {
        $meta['equipment'][$slot] = isset($item['name']) ? $item['name'] : '';
        $meta['equipment'][$slot . '_baseid'] = isset($item['baseid']) ? $item['baseid'] : '';
    }
    
    // Save back to database
    $currentData = $npcMaster->setMetadata($currentData, $meta);
    $npcMaster->updateByArray($currentData);
    
    Logger::debug("[gamedata.php] Updated equipment for {$actorType}: {$actorName}");
}

/**
 * Handle inventory update
 */
function handleInventoryUpdate(array $data, NpcMaster $npcMaster): void {
    $actorName = $data['actor_name'];
    $actorType = $data['actor_type'];
    
    if (!isset($data['items'])) {
        Logger::error("[gamedata.php] Inventory update missing items data");
        return;
    }
    
    $items = $data['items'];
    
    // Get or create NPC/Player record
    $currentData = $npcMaster->getByName($actorName);
    
    if (!$currentData) {
        // NPC/Player not in database yet - this is normal for NPCs not yet encountered
        if ($actorType === 'player') {
            Logger::warn("[gamedata.php] Player record not found, skipping inventory update for: {$actorName}");
        }
        // NPC not in database yet - this is normal, they haven't been encountered
        return;
    }
    
    // Get existing metadata
    $meta = [];
    if (!empty($currentData['metadata'])) {
        $meta = json_decode($currentData['metadata'], true);
        if (!is_array($meta)) {
            $meta = [];
        }
    }
    
    // Update inventory section - store as array for easier processing
    $meta['inventory'] = [];
    foreach ($items as $item) {
        if (isset($item['name']) && isset($item['baseid']) && isset($item['count'])) {
            $meta['inventory'][] = [
                'name' => $item['name'],
                'baseid' => $item['baseid'],
                'count' => intval($item['count'])
            ];
        }
    }
    
    // Save back to database
    $currentData = $npcMaster->setMetadata($currentData, $meta);
    $npcMaster->updateByArray($currentData);
    
    $itemCount = count($items);
    Logger::debug("[gamedata.php] Updated inventory for {$actorType}: {$actorName} ({$itemCount} items)");
}

/**
 * Handle skills update
 */
function handleSkillsUpdate(array $data, NpcMaster $npcMaster): void {
    $actorName = $data['actor_name'];
    $actorType = $data['actor_type'];
    
    if (!isset($data['skills'])) {
        Logger::error("[gamedata.php] Skills update missing skills data");
        return;
    }
    
    $skills = $data['skills'];
    
    // Get or create NPC/Player record
    $currentData = $npcMaster->getByName($actorName);
    
    if (!$currentData) {
        // NPC/Player not in database yet - this is normal for NPCs not yet encountered
        if ($actorType === 'player') {
            Logger::warn("[gamedata.php] Player record not found, skipping skills update for: {$actorName}");
        }
        // NPC not in database yet - this is normal, they haven't been encountered
        return;
    }
    
    // Get existing metadata
    $meta = [];
    if (!empty($currentData['metadata'])) {
        $meta = json_decode($currentData['metadata'], true);
        if (!is_array($meta)) {
            $meta = [];
        }
    }
    
    // Update skills section
    $meta['skills'] = [];
    foreach ($skills as $skillName => $skillValue) {
        $meta['skills'][$skillName] = floatval($skillValue);
    }
    
    // Save back to database
    $currentData = $npcMaster->setMetadata($currentData, $meta);
    $npcMaster->updateByArray($currentData);
    
    Logger::debug("[gamedata.php] Updated skills for {$actorType}: {$actorName}");
}

/**
 * Handle stats update
 */
function handleStatsUpdate(array $data, NpcMaster $npcMaster): void {
    $actorName = $data['actor_name'];
    $actorType = $data['actor_type'];
    
    if (!isset($data['stats'])) {
        Logger::error("[gamedata.php] Stats update missing stats data");
        return;
    }
    
    $stats = $data['stats'];
    
    // Get or create NPC/Player record
    $currentData = $npcMaster->getByName($actorName);
    
    if (!$currentData) {
        // NPC/Player not in database yet - this is normal for NPCs not yet encountered
        if ($actorType === 'player') {
            Logger::warn("[gamedata.php] Player record not found, skipping stats update for: {$actorName}");
        }
        // NPC not in database yet - this is normal, they haven't been encountered
        return;
    }
    
    // Get existing metadata
    $meta = [];
    if (!empty($currentData['metadata'])) {
        $meta = json_decode($currentData['metadata'], true);
        if (!is_array($meta)) {
            $meta = [];
        }
    }
    
    // Update stats section
    $meta['stats'] = [
        'level' => isset($stats['level']) ? intval($stats['level']) : 1,
        'health' => isset($stats['health']) ? floatval($stats['health']) : 0,
        'health_max' => isset($stats['health_max']) ? floatval($stats['health_max']) : 0,
        'magicka' => isset($stats['magicka']) ? floatval($stats['magicka']) : 0,
        'magicka_max' => isset($stats['magicka_max']) ? floatval($stats['magicka_max']) : 0,
        'stamina' => isset($stats['stamina']) ? floatval($stats['stamina']) : 0,
        'stamina_max' => isset($stats['stamina_max']) ? floatval($stats['stamina_max']) : 0
    ];
    
    // Save back to database
    $currentData = $npcMaster->setMetadata($currentData, $meta);
    $npcMaster->updateByArray($currentData);
    
    Logger::debug("[gamedata.php] Updated stats for {$actorType}: {$actorName}");
}

