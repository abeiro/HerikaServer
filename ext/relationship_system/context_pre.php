<?php
/**
 * RELATIONSHIP SYSTEM - Context Injection
 *
 * This file is automatically loaded by main.php at line 1792:
 *   requireFilesRecursively(__DIR__."/ext/","context.php");
 *
 * It injects relationship context into the AI prompt.
 *
 * Relationship evaluations are queued after completed responses and handled by
 * the background worker. Prompt construction only reads persisted state.
 *
 * TWO MODES:
 * 1. RELLLM_CONNECTOR set: Token-efficient mode
 *    - Only injects tier labels (Fond, Wary, etc.)
 *    - NO #REL: command instructions (RelationshipLLM handles scoring)
 *
 * 2. RELLLM_CONNECTOR not set: Full mode
 *    - Injects numbers and tiers
 *    - Adds #REL: command instructions for conversation model
 */

error_log("TRACE:\t".__LINE__. "\t".__FILE__.":\t".(microtime(true) - $GLOBALS["startTime"]));

// Master toggle - if disabled, skip everything in this file
if (empty($GLOBALS['RELATIONSHIP_SYSTEM_ENABLED'])) {
    return;
}

require_once $GLOBALS["ENGINE_PATH"] . "lib/relationship_manager.php";
require_once $GLOBALS["ENGINE_PATH"] . "lib/logger.php";

// Get the current NPC name
$npcName = $GLOBALS["HERIKA_NAME"] ?? null;

Logger::info("[REL-CONTEXT] npcName=" . ($npcName ?? 'NULL') . ", CACHE_PEOPLE=" . substr($GLOBALS["CACHE_PEOPLE"] ?? 'NULL', 0, 100));

if ($npcName) {
    // Parse nearby NPCs from CACHE_PEOPLE
    $nearbyNpcs = [];
    if (!empty($GLOBALS["CACHE_PEOPLE"])) {
        // CACHE_PEOPLE is a comma-separated string of NPC names
        $nearbyNpcs = array_map('trim', explode(',', $GLOBALS["CACHE_PEOPLE"]));
    }

    // Load once for mention filtering and prompt construction.
    $knownRels = RelationshipManager::getRelationships($npcName);

    // Also include NPCs mentioned in recent dialogue
    // This ensures relationships are shown for NPCs being discussed, not just physically present
    $mentionedNpcs = [];
    if (!empty($GLOBALS["HERIKA_CONTEXT"])) {
        $knownNames = array_keys($knownRels);

        // Scan recent context for mentions of known NPCs
        $contextLower = strtolower($GLOBALS["HERIKA_CONTEXT"]);
        foreach ($knownNames as $knownNpc) {
            if ($knownNpc === 'Player') continue; // Player always included
            if (stripos($contextLower, strtolower($knownNpc)) !== false) {
                $mentionedNpcs[] = $knownNpc;
            }
        }
    }

    // Merge nearby + mentioned, remove duplicates
    $relevantNpcs = array_unique(array_merge($nearbyNpcs, $mentionedNpcs));

    // Build the relationship context block
    // This automatically uses tier-only mode if RELLLM_CONNECTOR is set
    $relationshipContext = RelationshipManager::buildContext($npcName, $relevantNpcs, $knownRels);

    Logger::debug("[REL-CONTEXT] buildContext returned " . strlen($relationshipContext) . " chars for " . $npcName);

    // Inject into the character section of the prompt
    // We append to HERIKA_PERS which gets included in the <character> block
    if (!empty($relationshipContext)) {
        $GLOBALS["HERIKA_PERS"] .= "\n\n" . $relationshipContext;
        Logger::debug("[REL-CONTEXT] Injected " . strlen($relationshipContext) . " chars for {$npcName}");
    } else {
        Logger::warn("[REL-CONTEXT] No context to inject for {$npcName}");
    }

    // Only add #REL: command instructions if NOT using dedicated RelationshipLLM
    // When RELLLM_CONNECTOR is set, the relationship model handles all scoring
    // and the conversation model doesn't need to embed commands
    $useRelLLM = !empty($GLOBALS['RELLLM_CONNECTOR']) && $GLOBALS['RELLLM_CONNECTOR'] > 0;

    if (!$useRelLLM) {
        // Add the relationship system instructions to COMMAND_PROMPT
        // This teaches the AI how to use #REL: and #TYPE: commands
        $relationshipInstructions = RelationshipManager::getSystemPromptAddition();
        if (!empty($GLOBALS["COMMAND_PROMPT"])) {
            $GLOBALS["COMMAND_PROMPT"] .= "\n\n" . $relationshipInstructions;
        }
    }
}

error_log("TRACE:\t".__LINE__. "\t".__FILE__.":\t".(microtime(true) - $GLOBALS["startTime"]));

?>
