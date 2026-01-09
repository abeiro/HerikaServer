<?php
/**
 * RELATIONSHIP SYSTEM - Post-Request Processing
 *
 * This file is automatically loaded AFTER the AI response has been sent.
 * Processes relationship evaluations DIRECTLY here - no queue, no delay.
 *
 * WHY DIRECT PROCESSING WORKS:
 * - This runs AFTER voice/TTS response is already sent to the game
 * - No input lag because player already got their response
 * - Uses scoped global swapping in RelationshipLLM to avoid connector corruption
 *
 * INITIALIZATION STRATEGY:
 * - NEW NPCs: Proactive init in comm.php when addnpc event fires
 * - EXISTING SAVES: Lazy init here on first conversation
 *
 * TWO MODES:
 * 1. RELLLM_CONNECTOR set: Uses dedicated Relationship LLM for dynamic evaluation
 *    - Processes directly after response (no blocking because response already sent)
 *    - No #REL: commands needed from conversation model
 *    - More token-efficient for the main conversation
 *
 * 2. RELLLM_CONNECTOR not set: Falls back to parsing #REL: commands
 *    - Conversation model embeds #REL:Player=+5# in responses
 *    - Traditional approach (still synchronous, but fast)
 *
 * NPC-TO-NPC RELATIONSHIPS:
 * When one NPC talks to another NPC (not the Player), both NPCs form/update
 * relationships with each other. This allows followers, companions, and NPCs
 * who interact to develop natural bonds over time.
 *
 * NOTE: This needs to be called from processor/postrequest.php
 * Add this line at the end of processor/postrequest.php:
 *
 *   require_once(__DIR__."/../ext/relationship_system/postrequest.php");
 */

// Ensure Logger is available
require_once $GLOBALS["ENGINE_PATH"] . "lib/logger.php";

// Entry-level logging - track when this file is loaded and for which NPC
$_relEntryNpcName = $GLOBALS["HERIKA_NAME"] ?? 'NULL';
Logger::debug("[REL-ENTRY] postrequest.php loaded for HERIKA_NAME='{$_relEntryNpcName}'");

/**
 * Helper: Get the listener from the most recent speech entry for this NPC
 * Returns the listener name, or null if not found/not applicable
 */
function _relGetConversationListener($speakerName) {
    $escapedSpeaker = $GLOBALS["db"]->escape($speakerName);
    $row = $GLOBALS["db"]->fetchOne(
        "SELECT listener FROM speech WHERE speaker = '{$escapedSpeaker}' ORDER BY localts DESC LIMIT 1"
    );
    return $row ? $row['listener'] : null;
}

/**
 * Helper: Check if a name is the Player (compare against PLAYER_NAME global)
 */
function _relIsPlayer($name) {
    if (empty($name)) return false;
    $playerName = $GLOBALS["PLAYER_NAME"] ?? "Player";
    // Case-insensitive comparison
    return strcasecmp($name, $playerName) === 0 || strcasecmp($name, "Player") === 0;
}

/**
 * Helper: Check if a name is a valid NPC for relationship tracking
 * Returns false for Narrator, Player, empty, or system entities
 */
function _relIsValidNpcTarget($name) {
    if (empty($name)) return false;
    if ($name === "The Narrator") return false;
    if (_relIsPlayer($name)) return false;
    return true;
}

/**
 * Helper: Get NPC ID by name
 */
function _relGetNpcIdByName($npcName) {
    $escapedName = $GLOBALS["db"]->escape($npcName);
    $npcRow = $GLOBALS["db"]->fetchOne(
        "SELECT id FROM core_npc_master WHERE npc_name = '" . $escapedName . "' LIMIT 1"
    );
    return $npcRow ? intval($npcRow['id']) : null;
}

/**
 * Helper: Find NPCs mentioned in dialogue content (overhearing/being addressed)
 * Returns array of NPC names found in the dialogue that exist in core_npc_master
 */
function _relFindMentionedNpcs($dialogue, $excludeNames = []) {
    if (empty($dialogue)) return [];

    // Handle array input (talkedSoFar is an array of dialogue lines)
    if (is_array($dialogue)) {
        $dialogue = implode(" ", $dialogue);
    }

    // Get all NPC names from database
    $npcs = $GLOBALS["db"]->fetchAll(
        "SELECT npc_name FROM core_npc_master WHERE npc_name != 'The Narrator' ORDER BY LENGTH(npc_name) DESC"
    );

    if (empty($npcs)) return [];

    $mentioned = [];
    $dialogueLower = strtolower($dialogue);

    foreach ($npcs as $npc) {
        $npcName = $npc['npc_name'];

        // Skip excluded names (like the speaker themselves)
        if (in_array($npcName, $excludeNames)) continue;

        // Skip player
        if (_relIsPlayer($npcName)) continue;

        // Check if NPC name appears in dialogue (case-insensitive)
        $npcNameLower = strtolower($npcName);
        if (strpos($dialogueLower, $npcNameLower) !== false) {
            $mentioned[] = $npcName;
        }
    }

    return $mentioned;
}

// Get NPC name - check multiple sources
// Priority: CHIM profile data > currentNpcData > HERIKA_NAME fallback
$npcName = null;
$npcNameSource = 'none';

if (isset($GLOBALS["CHIM_CORE_CURRENT_NPC_DATA"]["npc_name"])) {
    $npcName = $GLOBALS["CHIM_CORE_CURRENT_NPC_DATA"]["npc_name"];
    $npcNameSource = 'CHIM_CORE_CURRENT_NPC_DATA';
} elseif (isset($currentNpcData["npc_name"])) {
    $npcName = $currentNpcData["npc_name"];
    $npcNameSource = 'currentNpcData';
} elseif (!empty($GLOBALS["HERIKA_NAME"])) {
    $npcName = $GLOBALS["HERIKA_NAME"];
    $npcNameSource = 'HERIKA_NAME';
}

Logger::debug("[REL-DEBUG] NPC name resolution: '{$npcName}' from {$npcNameSource}");

if (!$npcName) {
    return; // No NPC context
}

// Skip The Narrator - relationships don't apply to the narrator
if ($npcName === "The Narrator") {
    return;
}

// Determine who the NPC was talking to (listener)
$listenerName = _relGetConversationListener($npcName);
$isNpcToNpcConversation = _relIsValidNpcTarget($listenerName);

// FALLBACK: If speech table says Player but dialogue mentions another NPC by name,
// they might be addressing or talking about that NPC (overhearing detection)
$mentionedNpcs = [];
if (!$isNpcToNpcConversation && !empty($GLOBALS["talkedSoFar"])) {
    $playerName = $GLOBALS["PLAYER_NAME"] ?? "Player";
    $mentionedNpcs = _relFindMentionedNpcs($GLOBALS["talkedSoFar"], [$npcName, $playerName]);

    // If we found mentioned NPCs and the speech table listener is the Player,
    // use the first mentioned NPC as the target (likely being addressed)
    if (!empty($mentionedNpcs)) {
        $listenerName = $mentionedNpcs[0];
        $isNpcToNpcConversation = true;
        Logger::debug("[REL-DEBUG] Fallback: Found NPC mentioned in dialogue: " . implode(", ", $mentionedNpcs));
    }
}

// Debug: Always log listener detection
Logger::debug("[REL-DEBUG] Speaker: {$npcName}, Listener from speech table: " . ($listenerName ?? 'NULL') . ", IsNpcToNpc: " . ($isNpcToNpcConversation ? 'YES' : 'NO'));

if ($isNpcToNpcConversation) {
    Logger::info("[REL] NPC-to-NPC conversation detected: {$npcName} -> {$listenerName}");
}

Logger::info("[REL] Processing relationship evaluation for NPC: {$npcName}");

// Get NPC ID
$npcId = null;
if (!empty($GLOBALS["HERIKA_ID"])) {
    $npcId = intval($GLOBALS["HERIKA_ID"]);
} else {
    // Look up by name
    $escapedName = $GLOBALS["db"]->escape($npcName);
    $npcRow = $GLOBALS["db"]->fetchOne(
        "SELECT id FROM core_npc_master WHERE npc_name = '" . $escapedName . "' LIMIT 1"
    );
    if ($npcRow) {
        $npcId = intval($npcRow['id']);
    }
}

// Check if RelationshipLLM is configured
$useRelLLM = !empty($GLOBALS['RELLLM_CONNECTOR']) && $GLOBALS['RELLLM_CONNECTOR'] > 0;

if ($useRelLLM && $npcId) {
    // MODE 1: ASYNC - Queue evaluation for processing on next request
    // This prevents LLM calls from blocking the current response
    require_once __DIR__ . "/async_queue.php";

    // Build context from current conversation
    $context = [];

    // Get recent dialogue
    if (!empty($GLOBALS["talkedSoFar"])) {
        $context['dialogue'] = $GLOBALS["talkedSoFar"];
    }

    // Get recent events from buffer
    if (!empty($GLOBALS["BUFFER"])) {
        $context['events'] = [];
        foreach ($GLOBALS["BUFFER"] as $item) {
            if (isset($item['content'])) {
                $context['events'][] = $item['content'];
            }
        }
    }

    // Player's last input - only if this was a player-initiated request
    // inputtext/ginputtext = player spoke, other request types = NPC-initiated (Board Chat, etc.)
    $playerInputTypes = ["inputtext", "inputtext_s", "ginputtext", "ginputtext_s"];
    if (isset($gameRequest[0]) && in_array($gameRequest[0], $playerInputTypes) && !empty($gameRequest[3])) {
        $playerAction = $gameRequest[3];
        // Remove "PlayerName:" prefix if present (e.g., "Bannon:Hello" -> "Hello")
        $playerAction = preg_replace('/^[A-Za-z]+:\s*/', '', $playerAction);
        // Also remove "(Talking to everyone)" or similar tags
        $playerAction = preg_replace('/\s*\(Talking to [^)]+\)\s*$/i', '', $playerAction);
        $context['player_action'] = trim($playerAction);
    }
    // If request type is NOT player input (e.g., rechat, radiant, etc.), player_action stays empty
    // This prevents NPC-to-NPC exchanges from triggering Player relationship evals

    // Rolemaster instruction context - helps explain WHY the NPC behaved a certain way
    // Example: If Director instructed "be rude to everyone", the relationship model should know
    // this was directed behavior, not the NPC's natural disposition
    if (isset($gameRequest[0]) && $gameRequest[0] === "instruction" && !empty($gameRequest[3])) {
        $context['director_instruction'] = $gameRequest[3];
        Logger::debug("[REL-DEBUG] Captured director instruction: " . substr($gameRequest[3], 0, 100));
    }

    // Nearby NPCs (loaded AI agents) - for filtering relationship context
    if (!empty($GLOBALS["CACHE_PEOPLE"])) {
        $context['nearby_npcs'] = array_map('trim', explode(',', $GLOBALS["CACHE_PEOPLE"]));
    }

    // Get the NPC's response
    $npcResponse = !empty($GLOBALS["talkedSoFar"]) ? implode(" ", $GLOBALS["talkedSoFar"]) : "";
    
    // Clean up: If NPC response starts with player's text (echo bug), remove it
    // This happens when the main LLM accidentally echoes the player's input
    if (!empty($context['player_action']) && !empty($npcResponse)) {
        $playerText = $context['player_action'];
        // Remove "Name:" prefix from player text if present
        $playerText = preg_replace('/^[A-Za-z]+:\s*/', '', $playerText);
        // If NPC response starts with player's words, strip them
        if (stripos($npcResponse, substr($playerText, 0, 50)) === 0) {
            // Find where player text ends and NPC response begins
            $playerLen = strlen($playerText);
            if (strlen($npcResponse) > $playerLen) {
                $npcResponse = trim(substr($npcResponse, $playerLen));
                Logger::debug("[REL-DEBUG] Stripped echoed player text from NPC response");
            }
        }
    }

    // Get listener NPC ID if this is NPC-to-NPC conversation
    $listenerNpcId = null;
    if ($isNpcToNpcConversation) {
        $listenerNpcId = _relGetNpcIdByName($listenerName);
        if (!$listenerNpcId) {
            Logger::warn("[REL] NPC-to-NPC: Could not find NPC ID for listener '{$listenerName}'");
        }
    }

    // Debug: Log what we're processing
    $requestType = $gameRequest[0] ?? 'unknown';
    $hasPlayerAction = !empty($context['player_action']);
    Logger::info("[REL] Processing {$npcName}: request_type={$requestType}, has_player_action=" . ($hasPlayerAction ? 'YES' : 'NO') . ", is_npc2npc=" . ($isNpcToNpcConversation ? 'YES' : 'NO'));

    // Process evaluation directly - response is already sent, no input lag
    // RelationshipLLM uses scoped global swapping to avoid corrupting main connector
    require_once __DIR__ . "/relationship_llm.php";
    $relLLM = new RelationshipLLM();

    if ($relLLM->isAvailable()) {
        // Lazy init for speaker
        $relLLM->analyzeNpc($npcId, false);

        // Lazy init for listener if NPC-to-NPC
        if ($listenerNpcId) {
            $relLLM->analyzeNpc($listenerNpcId, false);
        }

        // Evaluate based on conversation type
        if ($isNpcToNpcConversation && $listenerNpcId) {
            $relLLM->evaluateNpcToNpcContext($npcId, $listenerNpcId, $npcResponse, $context);
        } elseif (!empty($context['player_action'])) {
            $relLLM->evaluateContext($npcId, $npcResponse, $context);
        }

        // Process any pending NPC inits from cell loading
        require_once __DIR__ . "/async_queue.php";
        _relProcessInitQueue(5);
    }
}

if (!$useRelLLM && !empty($GLOBALS["talkedSoFar"])) {
    // MODE 2: Parse #REL: and #TYPE: commands from AI response
    require_once $GLOBALS["ENGINE_PATH"] . "lib/relationship_manager.php";

    $fullResponse = implode(" ", $GLOBALS["talkedSoFar"]);

    // Parse and apply relationship changes
    // This also strips the commands from the response
    // Note: The TTS has already received the text, so stripping here is for logging only
    RelationshipManager::parseChanges($fullResponse, $npcName);
}
