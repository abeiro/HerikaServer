<?php
/**
 * RELATIONSHIP SYSTEM - Context Injection
 *
 * This file is automatically loaded by main.php at line 1792:
 *   requireFilesRecursively(__DIR__."/ext/","context.php");
 *
 * It injects relationship context into the AI prompt.
 *
 * ASYNC QUEUE PROCESSING:
 * Before injecting context, we process any pending relationship evaluations
 * that were queued by the PREVIOUS request. This ensures relationship data
 * is up-to-date before building the prompt, without blocking the response.
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

require_once $GLOBALS["ENGINE_PATH"] . "lib/relationship_manager.php";
require_once $GLOBALS["ENGINE_PATH"] . "lib/logger.php";

// Process any pending relationship work from previous requests
// This runs the LLM calls NOW (at start of request) instead of blocking at end of previous request
// The tradeoff: 1-turn delay in relationship updates, but zero blocking on voice response
$_relUseRelLLM = !empty($GLOBALS['RELLLM_CONNECTOR']) && $GLOBALS['RELLLM_CONNECTOR'] > 0;
if ($_relUseRelLLM) {
    require_once __DIR__ . "/async_queue.php";

    // Process pending NPC inits (TEXT->JSONB parsing from addnpc events)
    $_relInitResult = _relProcessInitQueue(3);
    if ($_relInitResult['processed'] > 0) {
        Logger::info("[REL-ASYNC] Processed {$_relInitResult['processed']} queued NPC inits at context injection");
    }

    // Process pending conversation evaluations
    $_relQueueResult = _relProcessQueue(3);
    if ($_relQueueResult['processed'] > 0) {
        Logger::info("[REL-ASYNC] Processed {$_relQueueResult['processed']} queued evaluations at context injection");
    }
}

// Get the current NPC name
$npcName = $GLOBALS["HERIKA_NAME"] ?? null;

if ($npcName) {
    // Parse nearby NPCs from CACHE_PEOPLE
    $nearbyNpcs = [];
    if (!empty($GLOBALS["CACHE_PEOPLE"])) {
        // CACHE_PEOPLE is a comma-separated string of NPC names
        $nearbyNpcs = array_map('trim', explode(',', $GLOBALS["CACHE_PEOPLE"]));
    }

    // Also include NPCs mentioned in recent dialogue
    // This ensures relationships are shown for NPCs being discussed, not just physically present
    $mentionedNpcs = [];
    if (!empty($GLOBALS["HERIKA_CONTEXT"])) {
        // Get this NPC's known relationships to check for mentions
        $knownRels = RelationshipManager::getRelationships($npcName);
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
    $relationshipContext = RelationshipManager::buildContext($npcName, $relevantNpcs);

    // Inject into the character section of the prompt
    // We append to HERIKA_PERS which gets included in the <character> block
    if (!empty($relationshipContext)) {
        $GLOBALS["HERIKA_PERS"] .= "\n\n" . $relationshipContext;
        // Debug: Log what we're injecting (truncated for log readability)
        Logger::debug("[REL-CONTEXT] Injected for {$npcName}: " . substr(str_replace("\n", " | ", $relationshipContext), 0, 200));
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
