<?php

// Loaded - will use Logger class for runtime logging
require_once __DIR__ . DIRECTORY_SEPARATOR . 'profile_llm_mode.php';

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
    private const PLAYER2_FORCE_ENABLED_KEY = 'PLAYER2_FORCE_ALL_LLM';
    private const PLAYER2_FORCE_CONNECTOR_KEY = 'PLAYER2_FORCE_CONNECTOR_ID';
    private const PLAYER2_DEFAULT_LABEL = 'Player2 Local';
    private const PLAYER2_DEFAULT_URL = 'http://127.0.0.1:4315/v1/chat/completions';
    private const NARRATOR_STATE_KEY = 'LLM_RANDOMIZER_NARRATOR_STATE';

    private static $player2ForceEnabled = null;
    private static $player2ForceConnectorId = null;

    private static function isTruthy($value) {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return ((int)$value) === 1;
        }

        $raw = strtolower(trim((string)$value));
        return in_array($raw, ['1', 'true', 'yes', 'on'], true);
    }
    
    /**
     * Determine which connector slot to use (1-4)
     * 
     * @param array $profileData Profile data with metadata
     * @param array $npcData NPC data with metadata
     * @param NpcMaster $npcMaster NPC manager instance for updates
     * @return int Connector slot to use (1-4)
     */
    public static function getConnectorSlot($profileData, &$npcData, $npcMaster) {
        $randomizerEnabled = ProfileLLMMode::isRandomEnabled(is_array($profileData) ? $profileData : []);
        
        if (!$randomizerEnabled) {
            // Randomizer disabled, use global setting (no logging)
            return self::getGlobalConnectorSlot();
        }

        $configuredSlots = ProfileLLMMode::getConfiguredSlots(is_array($profileData) ? $profileData : []);
        if (empty($configuredSlots)) {
            Logger::warn("[LLM_RANDOMIZER] Profile has no configured LLM connector slots; using global slot");
            return self::getGlobalConnectorSlot();
        }
        
        // Randomizer is enabled - log activity
        Logger::info("[LLM_RANDOMIZER] Active for NPC: " . ($npcData['npc_name'] ?? 'unknown'));
        
        // Randomizer enabled - apply exponential probability logic
        
        $isNarrator = strcasecmp((string)($npcData['npc_name'] ?? ''), 'The Narrator') === 0;
        $npcMeta = $isNarrator
            ? self::getNarratorRandomizerState()
            : ProfileLLMMode::decodeMetadata($npcData['metadata'] ?? null);
        
        $globalSlot = self::getGlobalConnectorSlot();
        $defaultSlot = in_array($globalSlot, $configuredSlots, true) ? $globalSlot : $configuredSlots[0];
        $currentSlot = isset($npcMeta['randomizer_current_slot']) ? (int)$npcMeta['randomizer_current_slot'] : $defaultSlot;
        $consecutiveUses = isset($npcMeta['randomizer_consecutive_uses']) ? (int)$npcMeta['randomizer_consecutive_uses'] : 0;
        
        if (!in_array($currentSlot, $configuredSlots, true)) {
            $currentSlot = $defaultSlot;
            $consecutiveUses = 0;
        }

        if (count($configuredSlots) === 1) {
            $npcMeta['randomizer_current_slot'] = $currentSlot;
            $npcMeta['randomizer_consecutive_uses'] = min($consecutiveUses + 1, self::MAX_USES);
            self::saveRandomizerState($npcData, $npcMeta, $npcMaster, $isNarrator);
            Logger::info("[LLM_RANDOMIZER] Only slot {$currentSlot} is configured; using it without rotation");
            return $currentSlot;
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
            $otherSlots = array_values(array_diff($configuredSlots, [$currentSlot]));
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
        
        self::saveRandomizerState($npcData, $npcMeta, $npcMaster, $isNarrator);
        
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

    private static function getNarratorRandomizerState() {
        $db = $GLOBALS['db'] ?? null;
        if (!$db) {
            return [];
        }

        $row = $db->fetchOne("SELECT value FROM conf_opts WHERE id='" . self::NARRATOR_STATE_KEY . "' LIMIT 1");
        return ProfileLLMMode::decodeMetadata($row['value'] ?? null);
    }

    private static function saveRandomizerState(&$npcData, array $state, $npcMaster, bool $isNarrator) {
        $encoded = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($isNarrator) {
            self::upsertConfOpt(self::NARRATOR_STATE_KEY, $encoded);
            return;
        }

        $npcData['metadata'] = $encoded;
        if (!empty($npcData['id']) && $npcMaster) {
            $npcMaster->updateByArray(['id' => $npcData['id'], 'metadata' => $encoded]);
        }
    }
    
    /**
     * Get connector ID for a given slot from profile data
     * 
     * @param array $profileData Profile data
     * @param int $slot Slot number (1-4)
     * @return int|null Connector ID or null
     */
    public static function getConnectorIdForSlot($profileData, $slot) {
        $forcedConnectorId = self::getForcedPlayer2ConnectorId();
        if (!empty($forcedConnectorId)) {
            return (int)$forcedConnectorId;
        }

        if (!is_array($profileData) || empty($profileData)) {
            return null;
        }

        $slotMap = [
            1 => 'llm_primary_id',
            2 => 'llm_secondary_id',
            3 => 'llm_tertiary_id',
            4 => 'llm_quaternary_id'
        ];

        $fieldName = $slotMap[$slot] ?? 'llm_primary_id';
        $selectedConnectorId = isset($profileData[$fieldName]) ? (int)$profileData[$fieldName] : 0;
        if ($selectedConnectorId > 0) {
            return $selectedConnectorId;
        }

        // Fallback: chosen slot is empty/unset, use first configured connector in profile.
        foreach (['llm_primary_id', 'llm_secondary_id', 'llm_tertiary_id', 'llm_quaternary_id'] as $fallbackField) {
            $candidateId = isset($profileData[$fallbackField]) ? (int)$profileData[$fallbackField] : 0;
            if ($candidateId > 0) {
                return $candidateId;
            }
        }

        return null;
    }

    public static function getConnectorIdForField($profileData, $fieldName) {
        $forcedConnectorId = self::getForcedPlayer2ConnectorId();
        if (!empty($forcedConnectorId)) {
            return (int)$forcedConnectorId;
        }

        if (!is_array($profileData) || empty($fieldName)) {
            return null;
        }

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

    public static function isPlayer2ForceEnabled() {
        if (self::$player2ForceEnabled !== null) {
            return self::$player2ForceEnabled;
        }

        $db = $GLOBALS['db'] ?? null;
        if (!$db) {
            self::$player2ForceEnabled = false;
            return false;
        }

        $row = $db->fetchOne("SELECT value FROM conf_opts WHERE id='" . self::PLAYER2_FORCE_ENABLED_KEY . "' LIMIT 1");
        self::$player2ForceEnabled = self::isTruthy($row['value'] ?? '0');
        return self::$player2ForceEnabled;
    }

    public static function setPlayer2ForceEnabled($enabled) {
        self::upsertConfOpt(self::PLAYER2_FORCE_ENABLED_KEY, $enabled ? '1' : '0');
        self::$player2ForceEnabled = (bool)$enabled;

        if ($enabled) {
            return self::ensurePlayer2ConnectorId();
        }

        return self::getStoredPlayer2ConnectorId();
    }

    public static function ensurePlayer2ConnectorId() {
        $existingId = self::getStoredPlayer2ConnectorId();
        if (!empty($existingId)) {
            return (int)$existingId;
        }

        $db = $GLOBALS['db'] ?? null;
        if (!$db) {
            return null;
        }

        $row = $db->fetchOne(
            "SELECT id FROM core_llm_connector " .
            "WHERE driver='player2json' " .
            "ORDER BY CASE WHEN lower(coalesce(label,''))='player2 local' THEN 0 ELSE 1 END, id ASC LIMIT 1"
        );

        $connectorId = isset($row['id']) ? intval($row['id']) : 0;

        $player2ApiBadgeId = self::ensurePlayer2ApiBadgeId();

        if ($connectorId <= 0) {
            $metadata = json_encode(['player2_game_key' => 'CHIM'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $insertedConnectorId = $db->insert('core_llm_connector', [
                'label' => self::PLAYER2_DEFAULT_LABEL,
                'metadata' => $metadata,
                'url' => self::PLAYER2_DEFAULT_URL,
                'model' => 'player2-app-selected',
                'driver' => 'player2json',
                'service' => 'player2',
                'max_tokens' => 750,
                'enforce_json' => 1,
                'prefill_json' => 0,
                'json_schema' => 1,
                'api_badge_id' => $player2ApiBadgeId,
                'temperature' => 1,
                'presence_penalty' => 0,
                'frequency_penalty' => 0,
                'repetition_penalty' => 1,
                'top_p' => 1,
                'top_k' => 0,
                'min_p' => 0,
                'top_a' => 0
            ]);

            $connectorId = !empty($insertedConnectorId) ? intval($insertedConnectorId) : 0;
            if ($connectorId <= 0) {
                $row = $db->fetchOne(
                    "SELECT id FROM core_llm_connector " .
                    "WHERE driver='player2json' " .
                    "ORDER BY CASE WHEN lower(coalesce(label,''))='player2 local' THEN 0 ELSE 1 END, id ASC LIMIT 1"
                );
                $connectorId = isset($row['id']) ? intval($row['id']) : 0;
            }
        } elseif (!empty($player2ApiBadgeId)) {
            $connectorRow = $db->fetchOne("SELECT api_badge_id FROM core_llm_connector WHERE id=" . intval($connectorId) . " LIMIT 1");
            if (empty($connectorRow['api_badge_id'])) {
                $db->updateRow('core_llm_connector', ['api_badge_id' => $player2ApiBadgeId], 'id=' . intval($connectorId));
            }
        }

        if (!empty($connectorId)) {
            self::upsertConfOpt(self::PLAYER2_FORCE_CONNECTOR_KEY, (string)$connectorId);
            self::$player2ForceConnectorId = (int)$connectorId;
            return (int)$connectorId;
        }

        return null;
    }

    private static function getForcedPlayer2ConnectorId() {
        if (!self::isPlayer2ForceEnabled()) {
            return null;
        }

        $connectorId = self::getStoredPlayer2ConnectorId();
        if (!empty($connectorId)) {
            return (int)$connectorId;
        }

        return self::ensurePlayer2ConnectorId();
    }

    private static function getStoredPlayer2ConnectorId() {
        if (self::$player2ForceConnectorId !== null) {
            return self::$player2ForceConnectorId ?: null;
        }

        $db = $GLOBALS['db'] ?? null;
        if (!$db) {
            self::$player2ForceConnectorId = 0;
            return null;
        }

        $row = $db->fetchOne("SELECT value FROM conf_opts WHERE id='" . self::PLAYER2_FORCE_CONNECTOR_KEY . "' LIMIT 1");
        $connectorId = intval($row['value'] ?? 0);

        if ($connectorId > 0) {
            $connectorRow = $db->fetchOne("SELECT id FROM core_llm_connector WHERE id={$connectorId} AND driver='player2json' LIMIT 1");
            if (!empty($connectorRow['id'])) {
                self::$player2ForceConnectorId = (int)$connectorRow['id'];
                return self::$player2ForceConnectorId;
            }
        }

        self::$player2ForceConnectorId = 0;
        return null;
    }

    private static function upsertConfOpt($id, $value) {
        $db = $GLOBALS['db'] ?? null;
        if (!$db) {
            return false;
        }

        $escapedId = $db->escape($id);
        $row = $db->fetchOne("SELECT id FROM conf_opts WHERE id='{$escapedId}' LIMIT 1");
        if ($row && isset($row['id'])) {
            return $db->updateRow('conf_opts', ['value' => (string)$value], "id='" . $escapedId . "'");
        }

        return $db->insert('conf_opts', ['id' => $id, 'value' => (string)$value]);
    }

    private static function ensurePlayer2ApiBadgeId() {
        $db = $GLOBALS['db'] ?? null;
        if (!$db) {
            return null;
        }

        $row = $db->fetchOne("SELECT id FROM core_api_badge WHERE lower(label)='player2' LIMIT 1");
        $badgeId = isset($row['id']) ? intval($row['id']) : 0;
        if ($badgeId > 0) {
            $badgeRow = $db->fetchOne("SELECT api_key FROM core_api_badge WHERE id=" . $badgeId . " LIMIT 1");
            if (empty($badgeRow['api_key'])) {
                $db->updateRow('core_api_badge', ['api_key' => 'CHIM'], 'id=' . $badgeId);
            }
            return $badgeId;
        }

        $insertedBadgeId = $db->insert('core_api_badge', [
            'label' => 'Player2',
            'api_key' => 'CHIM'
        ]);

        $badgeId = !empty($insertedBadgeId) ? intval($insertedBadgeId) : 0;
        if ($badgeId <= 0) {
            $row = $db->fetchOne("SELECT id FROM core_api_badge WHERE lower(label)='player2' LIMIT 1");
            $badgeId = isset($row['id']) ? intval($row['id']) : 0;
        }

        return !empty($badgeId) ? (int)$badgeId : null;
    }
}

