<?php
/**
 * Power Awareness System
 * 
 * Provides functions to compare character power levels and generate
 * contextual descriptions for AI NPCs to be aware of relative threats.
 */

require_once(__DIR__ . "/logger.php");

/**
 * Calculate power comparison descriptor based on level difference
 * 
 * @param int $npcLevel The NPC making the assessment
 * @param int $targetLevel The target being assessed
 * @return string Descriptor of relative power
 */
function calculatePowerComparison(int $npcLevel, int $targetLevel): string {
    $levelDiff = $targetLevel - $npcLevel;
    
    if ($levelDiff >= 10) {
        return "appears overwhelmingly powerful";
    } elseif ($levelDiff >= 5) {
        return "appears considerably stronger";
    } elseif ($levelDiff >= 2) {
        return "appears somewhat stronger";
    } elseif ($levelDiff >= -1) {
        return "appears evenly matched";
    } elseif ($levelDiff >= -4) {
        return "appears somewhat weaker";
    } elseif ($levelDiff >= -9) {
        return "appears considerably weaker";
    } else {
        return "appears far beneath you";
    }
}

/**
 * Get the level of an NPC from their metadata
 * 
 * @param string $npcName The NPC name
 * @return int|null The NPC's level, or null if not found
 */
function getNpcLevel(string $npcName): ?int {
    try {
        if (!isset($GLOBALS["db"])) {
            return null;
        }
        
        $db = $GLOBALS["db"];
        $escapedName = $db->escape($npcName);
        
        $npcData = $db->fetchOne("SELECT metadata FROM core_npc_master WHERE npc_name = '{$escapedName}' LIMIT 1");
        
        if (!$npcData || empty($npcData['metadata'])) {
            return null;
        }
        
        $metadata = json_decode($npcData['metadata'], true);
        if (!is_array($metadata)) {
            return null;
        }
        
        if (isset($metadata['stats']['level'])) {
            return intval($metadata['stats']['level']);
        }
        
        return null;
        
    } catch (Exception $e) {
        Logger::warn("[Power Awareness] Error getting NPC level for {$npcName}: " . $e->getMessage());
        return null;
    }
}

/**
 * Get the player's level from core_player table
 * 
 * @return int|null The player's level, or null if not found
 */
function getPlayerLevel(): ?int {
    try {
        require_once(__DIR__ . "/core/player.class.php");
        $player = new Player();
        
        $stats = $player->getJson('stats');
        if (is_array($stats) && isset($stats['level'])) {
            return intval($stats['level']);
        }
        
        return null;
        
    } catch (Exception $e) {
        Logger::warn("[Power Awareness] Error getting player level: " . $e->getMessage());
        return null;
    }
}
