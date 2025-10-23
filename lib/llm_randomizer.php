<?php

// Loaded - will use Logger class for runtime logging

/**
 * LLM Randomizer - Exponential probability-based connector rotation
 * 
 * Switches between the 4 LLM connectors with increasing probability
 * the longer a connector has been used consecutively.
 */
class LLMRandomizer {
    
    // Hardcoded settings optimized for 2-3 turn average
    private const BASE_CHANCE = 25;      // 25% initial chance
    private const MULTIPLIER = 2.0;       // 2x multiplier per use
    private const MAX_USES = 5;           // Force switch after 5 uses
    
    /**
     * Determine which connector slot to use (1-4)
     * 
     * @param array $profileData Profile data with metadata
     * @param array $npcData NPC data with metadata
     * @param NpcMaster $npcMaster NPC manager instance for updates
     * @return int Connector slot to use (1-4)
     */
    public static function getConnectorSlot($profileData, &$npcData, $npcMaster) {
        // Check if randomizer is enabled in profile
        $profileMeta = [];
        if (!empty($profileData['metadata'])) {
            $tmp = json_decode($profileData['metadata'], true);
            if (is_array($tmp)) {
                $profileMeta = $tmp;
            }
        }
        
        $randomizerEnabled = !empty($profileMeta['LLM_RANDOMIZER_ENABLED']);
        
        if (!$randomizerEnabled) {
            // Randomizer disabled, use global setting (no logging)
            return self::getGlobalConnectorSlot();
        }
        
        // Randomizer is enabled - log activity
        Logger::info("[LLM_RANDOMIZER] Active for NPC: " . ($npcData['npc_name'] ?? 'unknown'));
        
        // Randomizer enabled - apply exponential probability logic
        
        // Get NPC's randomizer state from metadata
        $npcMeta = [];
        if (!empty($npcData['metadata'])) {
            $tmp = json_decode($npcData['metadata'], true);
            if (is_array($tmp)) {
                $npcMeta = $tmp;
            }
        }
        
        $currentSlot = isset($npcMeta['randomizer_current_slot']) ? (int)$npcMeta['randomizer_current_slot'] : 1;
        $consecutiveUses = isset($npcMeta['randomizer_consecutive_uses']) ? (int)$npcMeta['randomizer_consecutive_uses'] : 0;
        
        // Ensure valid slot
        if ($currentSlot < 1 || $currentSlot > 4) {
            $currentSlot = 1;
        }
        
        // Check if we need to switch
        $shouldSwitch = false;
        
        // Force switch if max uses reached
        if ($consecutiveUses >= self::MAX_USES) {
            $shouldSwitch = true;
            Logger::info("[LLM_RANDOMIZER] Max uses ({" . self::MAX_USES . "}) reached for slot {$currentSlot}, forcing switch");
        } else {
            // Calculate probability: baseChance * (multiplier ^ consecutiveUses)
            $probability = self::BASE_CHANCE * pow(self::MULTIPLIER, $consecutiveUses);
            $probability = min($probability, 100); // Cap at 100%
            
            // Roll the dice
            $roll = mt_rand(1, 100);
            $shouldSwitch = $roll <= $probability;
            
            Logger::info("[LLM_RANDOMIZER] Slot {$currentSlot}, uses: {$consecutiveUses}, probability: " . round($probability, 1) . "%, roll: {$roll}, switch: " . ($shouldSwitch ? 'YES' : 'NO'));
        }
        
        if ($shouldSwitch) {
            // Pick a different random slot
            $availableSlots = [1, 2, 3, 4];
            $otherSlots = array_diff($availableSlots, [$currentSlot]);
            $newSlot = $otherSlots[array_rand($otherSlots)];
            
            // Update state
            $npcMeta['randomizer_current_slot'] = $newSlot;
            $npcMeta['randomizer_consecutive_uses'] = 1;
            
            $currentSlot = $newSlot;
            
            Logger::info("[LLM_RANDOMIZER] Switched to slot {$newSlot}");
        } else {
            // Increment consecutive uses
            $consecutiveUses++;
            $npcMeta['randomizer_consecutive_uses'] = $consecutiveUses;
        }
        
        // Save updated metadata back to NPC
        $npcData['metadata'] = json_encode($npcMeta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $npcMaster->updateByArray(['id' => $npcData['id'], 'metadata' => $npcData['metadata']]);
        
        return $currentSlot;
    }
    
    /**
     * Get the global connector slot setting (fallback when randomizer disabled)
     * 
     * @return int Connector slot (1-4)
     */
    private static function getGlobalConnectorSlot() {
        $db = $GLOBALS['db'];
        $result = $db->fetchOne("SELECT value FROM conf_opts WHERE id='chim_profile_model'");
        
        if (isset($result['value']) && $result['value'] >= 1 && $result['value'] <= 4) {
            return (int)$result['value'];
        }
        
        return 1; // Default to primary
    }
    
    /**
     * Get connector ID for a given slot from profile data
     * 
     * @param array $profileData Profile data
     * @param int $slot Slot number (1-4)
     * @return int|null Connector ID or null
     */
    public static function getConnectorIdForSlot($profileData, $slot) {
        $slotMap = [
            1 => 'llm_primary_id',
            2 => 'llm_secondary_id',
            3 => 'llm_tertiary_id',
            4 => 'llm_quaternary_id'
        ];
        
        $fieldName = $slotMap[$slot] ?? 'llm_primary_id';
        return isset($profileData[$fieldName]) ? (int)$profileData[$fieldName] : null;
    }
    
    /**
     * Get slot name for logging
     * 
     * @param int $slot Slot number (1-4)
     * @return string Slot name
     */
    public static function getSlotName($slot) {
        $names = [
            1 => 'Standard',
            2 => 'Fast',
            3 => 'Powerful',
            4 => 'Experimental'
        ];
        return $names[$slot] ?? 'Unknown';
    }
}

