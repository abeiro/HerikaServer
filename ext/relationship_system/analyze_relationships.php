<?php
/**
 * RELATIONSHIP SYSTEM - AI Analysis Endpoint
 *
 * AJAX endpoint that uses the Relationship Management LLM to analyze recent
 * event history and generate proper affinity scores and types.
 *
 * Uses GLOBALS['RELLLM_CONNECTOR'] for the dedicated relationship LLM.
 * Falls back to first available connector if not configured.
 *
 * Uses full NPC context (bio, personality, etc.) for better analysis.
 * Replaces #PLAYER_NAME# with actual player name.
 *
 * POST parameters:
 *   - npc_id: NPC database ID (to get full context)
 *   - npc_name: Name of the NPC (fallback)
 *   - source: events
 *   - event_limit: Max eventlog entries to analyze (default 200)
 */

header('Content-Type: application/json');

// Initialize
$enginePath = __DIR__ . '/../../';
$GLOBALS["ENGINE_PATH"] = $enginePath;

// This /ext endpoint runs outside the main request pipeline, so bootstrap it locally.
require_once $enginePath . "lib/runtime_bootstrap.php";
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_stt_connector' => false,
    'load_itt_connector' => false,
    'load_player_name' => true,
]);
require_once $enginePath . "lib/core/llm_connector.class.php";
require_once $enginePath . "lib/core/api_badge.class.php";
require_once $enginePath . "lib/core/npc_master.class.php";
require_once $enginePath . "lib/core/core_profiles.class.php";
require_once $enginePath . "lib/logger.php";
require_once $enginePath . "lib/relationship_manager.php";
require_once __DIR__ . "/event_baseline.php";

// Get POST data
$npcId = intval($_POST['npc_id'] ?? 0);
$npcName = $_POST['npc_name'] ?? '';
$eventLimit = intval($_POST['event_limit'] ?? 200);
$userDirection = $_POST['direction'] ?? ''; // Optional user guidance for AI
$customTypesJson = $_POST['custom_types'] ?? ''; // Custom relationship types from UI
$customTypes = !empty($customTypesJson) ? json_decode($customTypesJson, true) : [];

if ($eventLimit < 25) {
    $eventLimit = 25;
} elseif ($eventLimit > 400) {
    $eventLimit = 400;
}

if ($npcId <= 0 && trim($npcName) === '') {
    echo json_encode(['ok' => false, 'error' => 'Missing npc_id or npc_name']);
    exit;
}

try {
    // Get full NPC data for context
    $npcData = null;

    if ($npcId > 0) {
        $npcMaster = new NpcMaster();
        $npcData = $npcMaster->getById($npcId);
        if ($npcData) {
            $npcName = $npcData['npc_name'] ?? $npcName;
        }
    }

    $npcName = chimRelBaselineNormalizeName($npcName);
    if ($npcName === '') {
        echo json_encode(['ok' => false, 'error' => 'Unable to resolve NPC name']);
        exit;
    }

    $baseline = chimRelBuildEventBaseline($npcName, $eventLimit);
    if (empty($baseline['ok'])) {
        echo json_encode([
            'ok' => false,
            'error' => strval($baseline['error'] ?? 'No recent event history found for this NPC.')
        ]);
        exit;
    }

    $eventHistory = strval($baseline['history'] ?? '');
    $eventCount = intval($baseline['event_count'] ?? 0);
    $counterparts = is_array($baseline['counterparts'] ?? null) ? $baseline['counterparts'] : [];

    // Get player name from config/bootstrap.
    $playerName = $GLOBALS['PLAYER_NAME'] ?? 'the Player';

    // Get Relationship Management LLM connector
    // Priority: RELLLM_CONNECTOR global > first available connector
    $llmConnector = new LLMConnector();
    $connectorId = $GLOBALS['RELLLM_CONNECTOR'] ?? 0;
    $connectorData = null;

    if ($connectorId > 0) {
        $connectorData = $llmConnector->readOne($connectorId);
    }

    // Fallback to first connector if RELLLM_CONNECTOR not configured
    if (empty($connectorData)) {
        $connectors = $llmConnector->readAll();
        if (empty($connectors)) {
            echo json_encode(['ok' => false, 'error' => 'No LLM connectors configured. Set RELLLM_CONNECTOR in conf.php']);
            exit;
        }
        $connectorData = $connectors[0];
        Logger::warn("[REL-AI] Warning: RELLLM_CONNECTOR not set, using first available connector");
    }

    if (!$connectorData) {
        echo json_encode(['ok' => false, 'error' => 'LLM connector not found']);
        exit;
    }

    // Set up the connector globals
    $llmConnector->setOldGlobals($connectorData);

    // Get the driver instance
    $driver = $llmConnector->getConnector($connectorData);

    // Build context from NPC data
    $npcContext = "";
    if ($npcData) {
        if (!empty($npcData['npc_static_bio'])) {
            $npcContext .= "Background: " . $npcData['npc_static_bio'] . "\n";
        }
        if (!empty($npcData['personality'])) {
            $npcContext .= "Personality: " . $npcData['personality'] . "\n";
        }
        if (!empty($npcData['occupation'])) {
            $npcContext .= "Occupation: " . $npcData['occupation'] . "\n";
        }
        if (!empty($npcData['race'])) {
            $npcContext .= "Race: " . $npcData['race'] . "\n";
        }
    }

    // Build custom types section if any exist
    $customTypesSection = "";
    if (!empty($customTypes) && is_array($customTypes)) {
        $customTypesSection = "\n\nAdditional custom types available:\n";
        foreach ($customTypes as $ct) {
            $customTypesSection .= "   - {$ct}: Use when appropriate based on context\n";
        }
    }

    // Build the prompt
    $systemPrompt = <<<PROMPT
You are a relationship analyzer for a Skyrim RPG system. Given an NPC's context and recent event history, infer relationships and assign:

1. **Affinity Score** (-100 to +100, bell curve - extremes are RARE):
   - +91 to +100: Bonded (soulmates, unbreakable)
   - +76 to +90: Devoted (deep loyalty/love)
   - +56 to +75: Fond (genuine affection)
   - +31 to +55: Friendly (pleasant, helpful)
   - +6 to +30: Acquaintance (polite nod)
   - -5 to +5: Neutral (stranger)
   - -6 to -30: Wary (distrustful)
   - -31 to -55: Cold (unfriendly)
   - -56 to -75: Resentful (bitter, grudges)
   - -76 to -90: Hateful (active malice)
   - -91 to -100: Hostile (kill on sight)

2. **Relationship Type**:
   - romantic: Spouse, lover, romantic interest
   - familial: Parent, child, sibling, family
   - platonic: Friend, companion, ally
   - professional: Colleague, employer, guild member, commanding officer
   - rival: Competitor, nemesis (personal conflict, wants to outdo them)
   - enemy: Hostile opposition, war enemy, faction enemy (would kill on sight)
   - neutral: Acquaintance, stranger{$customTypesSection}

3. **Optional Extended Fields** (only include if clearly evident from the events):
   - relation: Specific relationship detail or role (e.g. "son", "ex-wife", "employer", "apprentice")
   - best: Notable positive memory/event (e.g. "opened gate for Ulfric", "saved life")
   - worst: Notable negative memory/event (e.g. "killed brother", "betrayed trust")

Consider the full context:
- The NPC's background, personality, and occupation inform their relationships
- Military ranks imply professional relationships with positive affinity
- Family terms imply familial with high positive affinity (extract the specific role as "relation")
- Words like "enemy", "rival", "hates" imply rival/hostile
- Faction allegiances affect relationships (Stormcloaks vs Imperials, etc.)
- "{$playerName}" refers to the player character
- Extract specific events from history as best/worst memories

Respond ONLY with valid JSON in this exact format:
{
  "relationships": {
    "TargetName1": {"aff": 50, "type": "professional"},
    "TargetName2": {"aff": 85, "type": "familial", "relation": "son"},
    "TargetName3": {"aff": -60, "type": "enemy", "worst": "killed brother"}
  }
}

Note: Only include relation/best/worst fields when they are clearly stated or strongly implied in the events. Omit them if not evident.
PROMPT;

    $userPrompt = "NPC: {$npcName}\n\n";
    if (!empty($npcContext)) {
        $userPrompt .= "NPC Context:\n{$npcContext}\n";
    }
    if (!empty($counterparts)) {
        $userPrompt .= "Observed Counterparts: " . implode(', ', $counterparts) . "\n\n";
    }
    if (!empty($userDirection)) {
        $userPrompt .= "Special Direction: {$userDirection}\n\n";
    }
    $userPrompt .= "Recent Event History (oldest to newest, {$eventCount} entries):\n{$eventHistory}\n\nAnalyze this history and return JSON relationships.";

    $contextData = [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $userPrompt]
    ];

    // Log what we're doing
    $modelName = $connectorData['model'] ?? $connectorData['driver'] ?? 'unknown';
    Logger::info("[REL-AI] Analyzing relationships for {$npcName} using connector #{$connectorData['id']} ({$modelName}), events={$eventCount}");

    // Make the LLM request - uses connector's configured temperature
    $response = $driver->fast_request(
        $contextData,
        [
            "MAX_TOKENS" => 1024
        ],
        "relationship_analysis"
    );

    Logger::debug("[REL-AI] Raw response: " . substr($response, 0, 500));

    // Try to parse the JSON response
    // Handle potential markdown code blocks
    $jsonResponse = $response;
    if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $response, $matches)) {
        $jsonResponse = trim($matches[1]);
    }

    // Fix common LLM JSON issues:
    // - Curly quotes to straight quotes
    // - Smart apostrophes
    // - Stray asterisks from markdown formatting
    $jsonResponse = str_replace(["\xe2\x80\x9c", "\xe2\x80\x9d", "\xe2\x80\x98", "\xe2\x80\x99", "*"], ['"', '"', "'", "'", ""], $jsonResponse);

    // Remove any stray control characters
    $jsonResponse = preg_replace('/[\x00-\x1F\x7F]/u', '', $jsonResponse);

    $parsed = json_decode($jsonResponse, true);

    if ($parsed === null) {
        // Try to extract JSON object directly
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $cleanJson = str_replace(["\xe2\x80\x9c", "\xe2\x80\x9d", "\xe2\x80\x98", "\xe2\x80\x99", "*"], ['"', '"', "'", "'", ""], $matches[0]);
            $cleanJson = preg_replace('/[\x00-\x1F\x7F]/u', '', $cleanJson);
            $parsed = json_decode($cleanJson, true);
        }
    }

    if ($parsed === null || !isset($parsed['relationships'])) {
        Logger::warn("[REL-AI] Failed to parse response: " . $response);
        echo json_encode([
            'ok' => false,
            'error' => 'Failed to parse AI response',
            'raw_response' => $response
        ]);
        exit;
    }

    // Validate and sanitize the relationships
    $relationships = [];
    $validTypes = ['romantic', 'platonic', 'familial', 'professional', 'rival', 'enemy', 'neutral'];
    // Add any custom types that were passed in
    if (!empty($customTypes) && is_array($customTypes)) {
        $validTypes = array_merge($validTypes, $customTypes);
    }

    foreach ($parsed['relationships'] as $target => $data) {
        $aff = isset($data['aff']) ? intval($data['aff']) : 0;
        $type = isset($data['type']) ? strtolower($data['type']) : 'neutral';

        // Clamp affinity
        $aff = max(-100, min(100, $aff));

        // Validate type - accept default types, custom types, or any single-word type
        if (!in_array($type, $validTypes) && !preg_match('/^[a-z]+$/', $type)) {
            $type = 'neutral';
        }

        $target = RelationshipManager::normalizeTargetName($target);

        $relationships[$target] = [
            'aff' => $aff,
            'type' => $type
        ];

        // Include optional extended fields if present
        if (!empty($data['relation'])) {
            $relationships[$target]['relation'] = trim($data['relation']);
        }
        if (!empty($data['best'])) {
            $relationships[$target]['best'] = trim($data['best']);
        }
        if (!empty($data['worst'])) {
            $relationships[$target]['worst'] = trim($data['worst']);
        }

        $logExtra = '';
        if (!empty($data['relation'])) $logExtra .= ", relation={$data['relation']}";
        if (!empty($data['best'])) $logExtra .= ", best={$data['best']}";
        if (!empty($data['worst'])) $logExtra .= ", worst={$data['worst']}";
        Logger::info("[REL-AI] {$npcName} -> {$target}: aff={$aff}, type={$type}{$logExtra}");
    }

    echo json_encode([
        'ok' => true,
        'relationships' => $relationships,
        'count' => count($relationships),
        'model' => $modelName,
        'player_name' => $playerName,
        'event_count' => $eventCount
    ]);

} catch (Exception $e) {
    Logger::error("[REL-AI] Error: " . $e->getMessage());
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
