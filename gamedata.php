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
    Logger::error("[gamedata.php] Bad request - missing type field <$json>");
    exit;
}

// Types that operate on global data and do not require an actor
$actorlessTypes = ['market_stock'];

// Validate required fields (skipped for actorless types)
if (!in_array($data['type'], $actorlessTypes)) {
    if (!isset($data['actor_name']) || !isset($data['actor_type'])) {
        http_response_code(400);
        echo "Bad Request: Missing actor_name or actor_type";
        Logger::error("[gamedata.php] Bad request - missing actor_name or actor_type");
        exit;
    }
}

$npcMaster = new NpcMaster();

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
        case 'spells':
            // Only handle NPC spells, not player spells (deprecated for player)
            if ($data['actor_type'] !== 'player') {
                handleSpellsUpdate($data, $npcMaster);
            }
            break;
        case 'skyrim_stats':
            handleSkyrimStatsUpdate($data);
            break;
         case 'furniture':
            $currentData = $npcMaster->getByName($data["actor_name"]);
            if ($currentData) {
                $meta = $npcMaster->getMetadata($currentData);
                $meta['furniture'] = $data['furniture'];    
                            $currentData = $npcMaster->setMetadata($currentData, $meta);
                $npcMaster->updateByArray($currentData);
            }
            break;
        case 'market_stock':
            handleMarketStockUpdate($data);
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
    
    // If this is a player, save directly to core_player table (player doesn't need NPC record)
    if ($actorType === 'player') {
        try {
            require_once(__DIR__ . "/lib/core/player.class.php");
            $player = new Player();
            
            // Format equipment data for storage
            $equipmentData = [];
            foreach ($equipment as $slot => $item) {
                $equipmentData[$slot] = isset($item['name']) ? $item['name'] : '';
                $equipmentData[$slot . '_baseid'] = isset($item['baseid']) ? $item['baseid'] : '';
            }
            
            $player->setJson('equipment', $equipmentData);
            Logger::debug("[gamedata.php] Saved player equipment to core_player table");
        } catch (Exception $e) {
            Logger::warn("[gamedata.php] Could not save player equipment to core_player: " . $e->getMessage());
        }
        
        // For backward compatibility, also try to update NPC record if it exists
        $currentData = $npcMaster->getByName($actorName);
        if ($currentData) {
            $meta = [];
            if (!empty($currentData['metadata'])) {
                $meta = json_decode($currentData['metadata'], true);
                if (!is_array($meta)) {
                    $meta = [];
                }
            }
            
            $meta['equipment'] = [];
            foreach ($equipment as $slot => $item) {
                $meta['equipment'][$slot] = isset($item['name']) ? $item['name'] : '';
                $meta['equipment'][$slot . '_baseid'] = isset($item['baseid']) ? $item['baseid'] : '';
            }
            
            $currentData = $npcMaster->setMetadata($currentData, $meta);
            $npcMaster->updateByArray($currentData);
        }
        
        return; // Done with player, exit early
    }
    
    // Handle NPC equipment
    $currentData = $npcMaster->getByName($actorName);
    
    if (!$currentData) {
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
    
    // If this is a player, save directly to core_player table (player doesn't need NPC record)
    if ($actorType === 'player') {
        try {
            require_once(__DIR__ . "/lib/core/player.class.php");
            $player = new Player();
            
            // Format inventory data for storage
            $inventoryData = [];
            foreach ($items as $item) {
                if (isset($item['name']) && isset($item['baseid']) && isset($item['count'])) {
                    $inventoryData[] = [
                        'name' => $item['name'],
                        'baseid' => $item['baseid'],
                        'count' => intval($item['count'])
                    ];
                }
            }
            
            $player->setJson('inventory', $inventoryData);
            Logger::debug("[gamedata.php] Saved player inventory to core_player table");
        } catch (Exception $e) {
            Logger::warn("[gamedata.php] Could not save player inventory to core_player: " . $e->getMessage());
        }
        
        // For backward compatibility, also try to update NPC record if it exists
        $currentData = $npcMaster->getByName($actorName);
        if ($currentData) {
            $meta = [];
            if (!empty($currentData['metadata'])) {
                $meta = json_decode($currentData['metadata'], true);
                if (!is_array($meta)) {
                    $meta = [];
                }
            }
            
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
            
            $currentData = $npcMaster->setMetadata($currentData, $meta);
            $npcMaster->updateByArray($currentData);
        }
        
        $itemCount = count($items);
        Logger::debug("[gamedata.php] Updated inventory for player: {$actorName} ({$itemCount} items)");
        return; // Done with player, exit early
    }
    
    // Handle NPC inventory
    $currentData = $npcMaster->getByName($actorName);
    
    if (!$currentData) {
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
    
    // If this is a player, save directly to core_player table (player doesn't need NPC record)
    if ($actorType === 'player') {
        try {
            require_once(__DIR__ . "/lib/core/player.class.php");
            $player = new Player();
            
            // Format skills data for storage
            $skillsData = [];
            foreach ($skills as $skillName => $skillValue) {
                $skillsData[$skillName] = floatval($skillValue);
            }
            
            $player->setJson('skills', $skillsData);
            Logger::debug("[gamedata.php] Saved player skills to core_player table");
        } catch (Exception $e) {
            Logger::warn("[gamedata.php] Could not save player skills to core_player: " . $e->getMessage());
        }
        
        // For backward compatibility, also try to update NPC record if it exists
        $currentData = $npcMaster->getByName($actorName);
        if ($currentData) {
            $meta = [];
            if (!empty($currentData['metadata'])) {
                $meta = json_decode($currentData['metadata'], true);
                if (!is_array($meta)) {
                    $meta = [];
                }
            }
            
            $meta['skills'] = [];
            foreach ($skills as $skillName => $skillValue) {
                $meta['skills'][$skillName] = floatval($skillValue);
            }
            
            $currentData = $npcMaster->setMetadata($currentData, $meta);
            $npcMaster->updateByArray($currentData);
        }
        
        Logger::debug("[gamedata.php] Updated skills for player: {$actorName}");
        return; // Done with player, exit early
    }
    
    // Handle NPC skills
    $currentData = $npcMaster->getByName($actorName);
    
    if (!$currentData) {
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
    
    // If this is a player, save directly to core_player table (player doesn't need NPC record)
    if ($actorType === 'player') {
        try {
            require_once(__DIR__ . "/lib/core/player.class.php");
            $player = new Player();
            
            // Format stats data for storage
            $statsData = [
                'level' => isset($stats['level']) ? intval($stats['level']) : 1,
                'health' => isset($stats['health']) ? floatval($stats['health']) : 0,
                'health_max' => isset($stats['health_max']) ? floatval($stats['health_max']) : 0,
                'magicka' => isset($stats['magicka']) ? floatval($stats['magicka']) : 0,
                'magicka_max' => isset($stats['magicka_max']) ? floatval($stats['magicka_max']) : 0,
                'stamina' => isset($stats['stamina']) ? floatval($stats['stamina']) : 0,
                'stamina_max' => isset($stats['stamina_max']) ? floatval($stats['stamina_max']) : 0,
                'scale' => isset($stats['scale']) ? floatval($stats['scale']) : 1.0
            ];
            
            $player->setJson('stats', $statsData);
            Logger::debug("[gamedata.php] Saved player stats to core_player table");
        } catch (Exception $e) {
            Logger::warn("[gamedata.php] Could not save player stats to core_player: " . $e->getMessage());
        }
        
        // For backward compatibility, also try to update NPC record if it exists
        $currentData = $npcMaster->getByName($actorName);
        if ($currentData) {
            $meta = [];
            if (!empty($currentData['metadata'])) {
                $meta = json_decode($currentData['metadata'], true);
                if (!is_array($meta)) {
                    $meta = [];
                }
            }
            
            $meta['stats'] = [
                'level' => isset($stats['level']) ? intval($stats['level']) : 1,
                'health' => isset($stats['health']) ? floatval($stats['health']) : 0,
                'health_max' => isset($stats['health_max']) ? floatval($stats['health_max']) : 0,
                'magicka' => isset($stats['magicka']) ? floatval($stats['magicka']) : 0,
                'magicka_max' => isset($stats['magicka_max']) ? floatval($stats['magicka_max']) : 0,
                'stamina' => isset($stats['stamina']) ? floatval($stats['stamina']) : 0,
                'stamina_max' => isset($stats['stamina_max']) ? floatval($stats['stamina_max']) : 0,
                'scale' => isset($stats['scale']) ? floatval($stats['scale']) : 1.0
            ];
            
            $currentData = $npcMaster->setMetadata($currentData, $meta);
            $npcMaster->updateByArray($currentData);
        }
        
        Logger::debug("[gamedata.php] Updated stats for player: {$actorName}");
        return; // Done with player, exit early
    }
    
    // Handle NPC stats
    $currentData = $npcMaster->getByName($actorName);
    
    if (!$currentData) {
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
        'stamina_max' => isset($stats['stamina_max']) ? floatval($stats['stamina_max']) : 0,
        'scale' => isset($stats['scale']) ? floatval($stats['scale']) : 1.0
    ];
    
    // Save back to database
    $currentData = $npcMaster->setMetadata($currentData, $meta);
    $npcMaster->updateByArray($currentData);
    
    Logger::debug("[gamedata.php] Updated stats for {$actorType}: {$actorName}");
}

/**
 * Handle spells update (NPCs only - player spells deprecated)
 */
function handleSpellsUpdate(array $data, NpcMaster $npcMaster): void {
    $actorName = $data['actor_name'];
    $actorType = $data['actor_type'];
    
    // Player spells are deprecated - skip
    if ($actorType === 'player') {
        Logger::debug("[gamedata.php] Skipping player spells update (deprecated)");
        return;
    }
    
    if (!isset($data['spells'])) {
        Logger::error("[gamedata.php] Spells update missing spells data for {$actorName}");
        return;
    }
    
    $spells = $data['spells'];
    
    // Handle NPC spells
    $currentData = $npcMaster->getByName($actorName);
    
    if (!$currentData) {
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
    
    // Update spells section - store as array
    $meta['spells'] = [];
    foreach ($spells as $spell) {
        if (isset($spell['name']) && isset($spell['baseid'])) {
            $meta['spells'][] = [
                'name' => $spell['name'],
                'baseid' => $spell['baseid'],
                'casting_type' => isset($spell['casting_type']) ? intval($spell['casting_type']) : 0,
                'delivery' => isset($spell['delivery']) ? intval($spell['delivery']) : 0
            ];
        }
    }
    $meta['spells_updated'] = time();
    
    // Save back to database
    $currentData = $npcMaster->setMetadata($currentData, $meta);
    $npcMaster->updateByArray($currentData);
    
    Logger::debug("[gamedata.php] Updated spells for NPC: {$actorName}");
}

/**
 * Handle Skyrim statistics update (player only)
 * This handles the ~40 Skyrim stats like Quests Completed, Days Passed, etc.
 */
function handleSkyrimStatsUpdate(array $data): void {
    if (!isset($data['stats']) || !is_array($data['stats'])) {
        Logger::error("[gamedata.php] Skyrim stats update missing stats data");
        return;
    }
    
    try {
        require_once(__DIR__ . "/lib/core/player.class.php");
        $player = new Player();
        
        $stats = $data['stats'];
        
        // Save each stat to core_player table
        foreach ($stats as $statKey => $statValue) {
            $player->set($statKey, (string)$statValue);
        }
        
        // Also save to conf_opts for backward compatibility
        $db = $GLOBALS["db"];
        foreach ($stats as $statKey => $statValue) {
            $escapedKey = $db->escape($statKey);
            $escapedValue = $db->escape((string)$statValue);
            $db->execQuery("
                INSERT INTO public.conf_opts (id, value) 
                VALUES ('{$escapedKey}', '{$escapedValue}')
                ON CONFLICT (id) DO UPDATE SET value = EXCLUDED.value
            ");
        }
        
        Logger::debug("[gamedata.php] Updated " . count($stats) . " Skyrim stats to core_player and conf_opts");
        
    } catch (Exception $e) {
        Logger::error("[gamedata.php] Failed to save Skyrim stats: " . $e->getMessage());
    }
}

/**
 * Handle market stock update
 * Updates the stock JSONB column on the factions table for the given faction formid.
 * Expected payload:
 *   { "type": "market_stock", "faction": "0x01", "list": [ { "itemid": "0x02", "name": "item_name", "count": 2 } ] }
 */
function handleMarketStockUpdate(array $data): void {
    if (!isset($data['faction']) || !isset($data['list']) || !is_array($data['list'])) {
        Logger::error("[gamedata.php] market_stock update missing faction or list data");
        return;
    }

    $db = $GLOBALS['db'];
    $factionFormId = $db->escape(strtoupper($data['faction']));

    // Build normalised stock array from the incoming list
    $stock = [];
    $gold=0;
    $rank=isset($data['player_rank']) ? intval($data['player_rank']) : -1;
    foreach ($data['list'] as $item) {
        if (isset($item['itemid'], $item['name'], $item['count'])) {
            $stock[] = [
                'itemid' => $item['itemid'],
                'name'   => trim($item['name']),
                'count'  => intval($item['count']),
                'gold'  => intval($item['gold']),
                'enchantment' => isset($item['enchantment']) ? $item['enchantment'] : []
                
            ];
            if (isset($item['gold']) && $item['itemid'] === '0000000F') { // Gold item  
                $gold=intval($item['count']);
            } 

        }
    }

    $stockJson = $db->escape(json_encode($stock));

    $sql = "UPDATE public.factions
               SET stock = '{$stockJson}'::jsonb,gold=$gold,player_rank=$rank
             WHERE formid = '{$factionFormId}'";

    $result = $db->execQuery($sql);

    if ($result) {
        
        Logger::debug("[gamedata.php] Updated market stock for faction '{$data['faction']}': "
            . count($stock) . " item(s)");
    }
}

