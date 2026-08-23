<?php
/**
 * RELATIONSHIP MANAGEMENT LLM
 *
 * A dedicated small/fast LLM connector for relationship processing.
 * Uses a cheap model (24B class) at low temperature for consistent, factual output.
 *
 * Features:
 * - Dynamic evaluation of structured JSON relationships
 * - Relationship inference: If A loves B and B hates C, A becomes wary of C
 * - Group inference: "Imperial soldier" auto-adds faction biases
 * - Consistency checking: Ensure reciprocal relationships make sense
 *
 * Uses GLOBALS['RELLLM_CONNECTOR'] for the connector ID (set in conf.php)
 * Falls back to the profile's LLM connector if not configured.
 */

// Ensure Logger is available
require_once $GLOBALS["ENGINE_PATH"] . "lib/logger.php";
require_once $GLOBALS["ENGINE_PATH"] . "lib/relationship_manager.php";

class RelationshipLLM {

    private $db;
    private $connector;
    private $driver;
    private $modelName;
    private $promptCache = [];

    public function __construct() {
        $this->db = $GLOBALS['db'];
        $this->initConnector();
    }

    /**
     * Safely decode JSON with proper error handling
     * CRITICAL: Prevents data loss if extended_data is corrupted
     *
     * If JSON decode fails, logs the error and returns null (NOT empty array).
     * Callers must check for null before proceeding with writes.
     *
     * @param string|null $json The JSON string to decode
     * @param string $context Description for error logging
     * @return array|null Decoded array, or null if decode failed
     */
    private function safeJsonDecode($json, $context = 'unknown') {
        if ($json === null || $json === '') {
            return []; // Empty/null is valid - start fresh
        }

        $decoded = json_decode($json, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            // JSON was present but corrupted - DO NOT return empty array!
            $error = json_last_error_msg();
            Logger::error("[REL-LLM] CRITICAL: JSON decode failed for {$context}: {$error}");
            Logger::error("[REL-LLM] Corrupted JSON (first 200 chars): " . substr($json, 0, 200));
            return null; // Return null to signal failure - caller must abort write
        }

        return $decoded ?: [];
    }

    /**
     * Acquire advisory lock for NPC relationship updates
     * Prevents race conditions when multiple requests update the same NPC
     *
     * @param int $npcId The NPC ID to lock
     * @return bool True if lock acquired
     */
    private function acquireNpcLock($npcId) {
        try {
            // Use pg_advisory_lock with a namespace (1001) + npc_id to avoid collisions
            $lockId = 1001000000 + intval($npcId);
            $this->db->execQuery("SELECT pg_advisory_lock({$lockId})");
            return true;
        } catch (Exception $e) {
            Logger::error("[REL-LLM] Failed to acquire advisory lock for NPC {$npcId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Release advisory lock for NPC relationship updates
     *
     * @param int $npcId The NPC ID to unlock
     */
    private function releaseNpcLock($npcId) {
        try {
            $lockId = 1001000000 + intval($npcId);
            $this->db->execQuery("SELECT pg_advisory_unlock({$lockId})");
        } catch (Exception $e) {
            Logger::error("[REL-LLM] Failed to release advisory lock for NPC {$npcId}: " . $e->getMessage());
        }
    }

    /**
     * Load a prompt from the database prompts table
     * Falls back to hardcoded default if not found
     *
     * @param string $promptKey The prompt_key to load
     * @param string $fallback Hardcoded fallback if DB lookup fails
     * @return string The prompt text
     */
    private function loadPrompt($promptKey, $fallback) {
        // Check cache first
        if (isset($this->promptCache[$promptKey])) {
            return $this->promptCache[$promptKey];
        }

        try {
            if ($this->db) {
                $escapedKey = $this->db->escape($promptKey);
                $row = $this->db->fetchOne(
                    "SELECT custom_prompt, default_prompt FROM prompts WHERE prompt_key = '{$escapedKey}'"
                );
                if ($row) {
                    // Use custom_prompt if set, otherwise default_prompt
                    $prompt = !empty($row['custom_prompt']) ? $row['custom_prompt'] : $row['default_prompt'];
                    $this->promptCache[$promptKey] = $prompt;
                    return $prompt;
                }
            }
        } catch (Exception $e) {
            Logger::warn("[REL-LLM] Failed to load prompt '{$promptKey}': " . $e->getMessage());
        }

        // Fallback to hardcoded
        $this->promptCache[$promptKey] = $fallback;
        return $fallback;
    }

    /**
     * Initialize the LLM connector
     * NOTE: Does NOT call setOldGlobals() here - that happens in makeSafeRequest()
     * to avoid corrupting the main chat connector's globals
     */
    // Extension-provided character facts. Extensions register fact sources in
    // conf_opts id='chim_character_facts_sources' (JSON array of
    // {table, name_column, facts:{label: sql_expression}, skip_values:{label:[values]}}).
    // Data-driven (no code hooks) so the standalone worker daemon sees registrations too.
    // No registrations -> empty string; unknown tables/columns fail silently per source.
    private function extensionCharacterFacts($npcName) {
        try {
            if (empty($GLOBALS['db']) || (string)$npcName === '') { return ''; }
            static $sources = null;
            if ($sources === null) {
                $sources = [];
                $row = $GLOBALS['db']->fetchOne("SELECT value FROM conf_opts WHERE id = 'chim_character_facts_sources'");
                if ($row && !empty($row['value'])) {
                    $decoded = json_decode($row['value'], true);
                    if (is_array($decoded)) { $sources = $decoded; }
                }
            }
            if (empty($sources)) { return ''; }
            $bits = [];
            $e = $GLOBALS['db']->escape($npcName);
            foreach ($sources as $src) {
                $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($src['table'] ?? ''));
                $nameCol = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($src['name_column'] ?? ''));
                $facts = is_array($src['facts'] ?? null) ? $src['facts'] : [];
                if ($table === '' || $nameCol === '' || empty($facts)) { continue; }
                $selects = [];
                $i = 0;
                $aliasByLabel = [];
                foreach ($facts as $label => $expr) {
                    $alias = 'f' . $i++;
                    $aliasByLabel[$label] = $alias;
                    $selects[] = "({$expr}) AS {$alias}";
                }
                $frow = $GLOBALS['db']->fetchOne("SELECT " . implode(', ', $selects) . " FROM {$table} WHERE {$nameCol} = '{$e}'");
                if (!$frow) { continue; }
                $skip = is_array($src['skip_values'] ?? null) ? $src['skip_values'] : [];
                foreach ($aliasByLabel as $label => $alias) {
                    $val = trim((string)($frow[$alias] ?? ''));
                    if ($val === '') { continue; }
                    if (isset($skip[$label]) && in_array(strtolower($val), array_map('strtolower', (array)$skip[$label]), true)) { continue; }
                    $bits[] = "{$label}: {$val}";
                }
            }
            if (!$bits) { return ''; }
            return "Character facts for {$npcName} (persistent profile - respect these when judging relationship changes): " . implode('; ', $bits) . "\n";
        } catch (\Throwable $t) { return ''; }
    }
    private function initConnector() {
        require_once $GLOBALS['ENGINE_PATH'] . "lib/core/llm_connector.class.php";

        $llmConnector = new LLMConnector();
        $connectorId = $GLOBALS['RELLLM_CONNECTOR'] ?? 0;

        if ($connectorId > 0) {
            $this->connector = $llmConnector->readOne($connectorId);
        }

        // Fallback to first available connector
        if (empty($this->connector)) {
            $connectors = $llmConnector->readAll();
            if (!empty($connectors)) {
                $this->connector = $connectors[0];
            }
        }

        if ($this->connector) {
            $this->driver = $llmConnector->getConnector($this->connector);
            $this->modelName = $this->connector['model'] ?? $this->connector['driver'] ?? 'unknown';
        }
    }

    /**
     * Make a safe LLM request with scoped global swapping
     *
     * CRITICAL: This prevents the relationship connector from corrupting
     * the main chat connector's globals. We:
     * 1. Save the current $GLOBALS["CONNECTOR"]
     * 2. Call setOldGlobals() to configure for relationship LLM
     * 3. Make the request
     * 4. Restore the original $GLOBALS["CONNECTOR"] in finally block
     *
     * @param array $messages The messages to send
     * @param array $params Request parameters (MAX_TOKENS, etc)
     * @param string $context Context identifier for logging
     * @return string|null The response, or null on failure
     */
    private function makeSafeRequest($messages, $params, $context) {
        if (!$this->driver || !$this->connector) {
            return null;
        }

        require_once $GLOBALS['ENGINE_PATH'] . "lib/core/llm_connector.class.php";
        $llmConnector = new LLMConnector();

        // Save current connector globals (the main chat connector's settings)
        $savedGlobals = isset($GLOBALS["CONNECTOR"]) ? $GLOBALS["CONNECTOR"] : null;

        try {
            // SWAP IN: Configure globals for relationship LLM
            $llmConnector->setOldGlobals($this->connector);

            // Make the request
            return $this->driver->fast_request($messages, $params, $context);
        } catch (Exception $e) {
            Logger::error("[REL-LLM] Request failed ({$context}): " . $e->getMessage());
            return null;
        } finally {
            // SWAP BACK: Restore main chat connector's globals
            if ($savedGlobals !== null) {
                $GLOBALS["CONNECTOR"] = $savedGlobals;
            }
        }
    }

    /**
     * Check if the LLM is available
     */
    public function isAvailable() {
        return $this->driver !== null;
    }

    /**
     * Log request to audit_request table for UI visibility
     */
    private function logToAudit($request, $response, $callType = 'relationship') {
        if (!$this->db) return;

        $connectorLabel = $this->connector['label'] ?? 'RelationshipLLM';
        $model = $this->modelName ?? 'unknown';

        // The audit viewer infers status from the result text: "OK|" prefix = success.
        $resultText = is_string($response) ? $response : json_encode($response);
        $ok = is_string($response) && trim($response) !== '';
        if ($ok) {
            $decoded = json_decode(trim($response), true);
            if (is_array($decoded) && isset($decoded['error'])) {
                $ok = false;
            }
        }

        $this->db->insert('audit_request', [
            'request' => json_encode([
                'type' => $callType,
                'model' => $model,
                'messages' => $request
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'result' => ($ok ? 'OK|' : '') . substr((string)$resultText, 0, 2000),
            'connector' => "RelationshipLLM ({$connectorLabel})",
            'url' => "ext/relationship_system/{$callType}"
        ]);
    }

    /**
     * Keep legacy initialization callers harmless without sending the retired
     * text relationship field to an LLM. New relationship state is JSON-only.
     */
    public function analyzeNpc($npcId, $forceReanalyze = false) {
        require_once $GLOBALS['ENGINE_PATH'] . "lib/core/npc_master.class.php";

        $npcMaster = new NpcMaster();
        $npc = $npcMaster->getById($npcId);

        if (!$npc) {
            return ['ok' => false, 'error' => 'NPC not found'];
        }

        $extended = $this->safeJsonDecode($npc['extended_data'] ?? null, "analyzeNpc:{$npc['npc_name']}");
        if ($extended === null) {
            return ['ok' => false, 'error' => 'Corrupted extended_data - refusing to overwrite'];
        }

        return [
            'ok' => true,
            'skipped' => true,
            'reason' => !empty($extended['relationships'])
                ? 'Already has structured relationships'
                : 'No structured relationships yet'
        ];
    }

    /**
     * Run the actual LLM analysis for an NPC
     */
    private function runAnalysis($npc, $replaceExisting = false) {
        if (!$this->isAvailable()) {
            return ['ok' => false, 'error' => 'LLM not available'];
        }

        $npcName = $npc['npc_name'];
        $relationshipsText = $npc['relationships'];

        // Get player name from config/bootstrap.
        $playerName = $GLOBALS['PLAYER_NAME'] ?? 'the Player';

        // Replace player placeholder
        $relationshipsText = str_replace('#PLAYER_NAME#', $playerName, $relationshipsText);

        // Build NPC context
        $npcContext = "";
        if (!empty($npc['npc_static_bio'])) {
            $npcContext .= "Background: " . $npc['npc_static_bio'] . "\n";
        }
        if (!empty($npc['personality'])) {
            $npcContext .= "Personality: " . $npc['personality'] . "\n";
        }
        if (!empty($npc['occupation'])) {
            $npcContext .= "Occupation: " . $npc['occupation'] . "\n";
        }
        if (!empty($npc['race'])) {
            $npcContext .= "Race: " . $npc['race'] . "\n";
        }
        $npcContext .= $this->extensionCharacterFacts($npcName); // extension character facts (see extensionCharacterFacts)

        // Build prompt
        $systemPrompt = $this->getAnalysisPrompt($playerName);

        $userPrompt = "NPC: {$npcName}\n\n";
        if (!empty($npcContext)) {
            $userPrompt .= "NPC Context:\n{$npcContext}\n";
        }
        $userPrompt .= "Relationship Descriptions:\n{$relationshipsText}\n\n";
        $userPrompt .= "Analyze these relationships and infer any faction/group biases from context. Return JSON.";

        $contextData = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ];

        Logger::info("[REL-LLM] Analyzing {$npcName} using {$this->modelName}");

        // Make LLM request with scoped global swapping
        // This prevents corrupting the main chat connector's globals
        $response = $this->makeSafeRequest(
            $contextData,
            ["MAX_TOKENS" => 1024],
            "relationship_llm"
        );

        // Log to audit_request for UI visibility
        $this->logToAudit($contextData, $response, "analyze_{$npcName}");

        // Parse response
        $relationships = $this->parseResponse($response);

        if ($relationships === null) {
            Logger::warn("[REL-LLM] Failed to parse response for {$npcName} (response length " . strlen($response) . ")");
            return ['ok' => false, 'error' => 'Failed to parse response'];
        }

        // Save to NPC
        $this->saveRelationships($npc['id'], $relationships, $replaceExisting);

        Logger::info("[REL-LLM] Saved " . count($relationships) . " relationships for {$npcName}");

        return [
            'ok' => true,
            'npc_name' => $npcName,
            'relationships' => $relationships,
            'count' => count($relationships),
            'model' => $this->modelName
        ];
    }

    /**
     * Get the system prompt for relationship analysis
     * Loads from database prompts table, falls back to hardcoded default
     */
    private function getAnalysisPrompt($playerName) {
        $fallback = <<<'PROMPT'
You are a relationship analyzer for Skyrim NPCs. Analyze relationship descriptions and output JSON.

AFFINITY SCALE (-100 to +100, bell curve - extremes are RARE):
+91 to +100: Bonded (soulmates, unbreakable)
+76 to +90: Devoted (deep loyalty/love)
+56 to +75: Fond (genuine affection)
+31 to +55: Friendly (pleasant, helpful)
+6 to +30: Acquaintance (polite nod)
-5 to +5: Neutral (stranger)
-6 to -30: Wary (distrustful)
-31 to -55: Cold (unfriendly)
-56 to -75: Resentful (bitter, grudges)
-76 to -90: Hateful (active malice)
-91 to -100: Hostile (kill on sight)

TYPES: romantic, platonic, familial, professional, rival, enemy, neutral, nemesis, estranged, transactional, protective, indebted, fanatical, mentor, student, servant, client, patron, crush, ex, betrayed, suspicious, admirer, jealous, fearful, obsessed, awed, contempt, pitying, grateful, curious, dismissive

INFERENCE RULES:
1. FACTION: Imperial → add "Stormcloak": -60 enemy. Stormcloak → add "Imperial": -60 enemy.
2. RACIAL: If NPC shows racial attitudes, add race as target (e.g., "Khajit": -40 contempt)
3. OCCUPATION: Thieves Guild → "Guard": -40 rival. Companions → "Silver Hand": -70 enemy.
4. "{PLAYER_NAME}" = Player character. Store as "Player".

OUTPUT (JSON only):
{"relationships": {"Target": {"aff": 50, "type": "professional", "note": "works together"}}}
PROMPT;

        $prompt = $this->loadPrompt('rel_llm_analysis', $fallback);

        // Replace {PLAYER_NAME} placeholder
        return str_replace('{PLAYER_NAME}', $playerName, $prompt);
    }

    /**
     * Parse LLM response into relationships array
     */
    private function logMalformedResponseField($context, $value) {
        $type = function_exists('get_debug_type') ? get_debug_type($value) : gettype($value);
        Logger::warn("[REL-LLM] Ignoring malformed {$context} ({$type})");
    }

    /**
     * Normalize one dynamic relationship change without trusting model-provided field types.
     */
    private function normalizeChangeData($change, $context) {
        if (!is_array($change) || (!empty($change) && array_is_list($change))) {
            $this->logMalformedResponseField("{$context} change", $change);
            return [];
        }

        $normalized = [];
        if (array_key_exists('delta', $change)) {
            if (is_numeric($change['delta']) && !is_bool($change['delta'])) {
                $normalized['delta'] = intval($change['delta']);
            } else {
                $this->logMalformedResponseField("{$context} delta", $change['delta']);
            }
        }

        foreach (['type', 'reason', 'relation'] as $field) {
            if (!array_key_exists($field, $change)) {
                continue;
            }
            if (!is_string($change[$field])) {
                $this->logMalformedResponseField("{$context} {$field}", $change[$field]);
                continue;
            }

            $value = trim($change[$field]);
            if ($value !== '') {
                $normalized[$field] = $field === 'reason' ? $value : strtolower($value);
            }
        }

        if (empty($normalized)) {
            return [];
        }
        $normalized['delta'] = $normalized['delta'] ?? 0;
        return $normalized;
    }

    /**
     * Normalize a target-keyed map of dynamic relationship changes.
     */
    private function normalizeChangeMap($changes, $context) {
        if (!is_array($changes) || (!empty($changes) && array_is_list($changes))) {
            $this->logMalformedResponseField("{$context} changes", $changes);
            return [];
        }

        $normalized = [];
        foreach ($changes as $target => $change) {
            if (!is_string($target) || trim($target) === '') {
                $this->logMalformedResponseField("{$context} target", $target);
                continue;
            }
            $normalizedChange = $this->normalizeChangeData($change, $context);
            if (!empty($normalizedChange)) {
                $normalized[trim($target)] = $normalizedChange;
            }
        }
        return $normalized;
    }

    private function parseResponse($response) {
        // Handle markdown code blocks
        $jsonResponse = $response;
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $response, $matches)) {
            $jsonResponse = trim($matches[1]);
        }

        // Fix common LLM JSON issues
        $jsonResponse = str_replace(
            ["\xe2\x80\x9c", "\xe2\x80\x9d", "\xe2\x80\x98", "\xe2\x80\x99", "*"],
            ['"', '"', "'", "'", ""],
            $jsonResponse
        );
        $jsonResponse = preg_replace('/[\x00-\x1F\x7F]/u', '', $jsonResponse);

        $parsed = json_decode($jsonResponse, true);

        // Fallback: extract JSON object
        if ($parsed === null) {
            if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
                $cleanJson = str_replace(
                    ["\xe2\x80\x9c", "\xe2\x80\x9d", "\xe2\x80\x98", "\xe2\x80\x99", "*"],
                    ['"', '"', "'", "'", ""],
                    $matches[0]
                );
                $cleanJson = preg_replace('/[\x00-\x1F\x7F]/u', '', $cleanJson);
                $parsed = json_decode($cleanJson, true);
            }
        }

        if (!is_array($parsed)
            || !array_key_exists('relationships', $parsed)
            || !is_array($parsed['relationships'])
            || (!empty($parsed['relationships']) && array_is_list($parsed['relationships']))) {
            $this->logMalformedResponseField('initial relationships container', $parsed['relationships'] ?? $parsed);
            return null;
        }

        // Validate and normalize. Initial analysis may select only built-in types;
        // custom types do not exist in this initialization context.
        $relationships = [];

        foreach ($parsed['relationships'] as $target => $data) {
            if (!is_string($target) || trim($target) === '') {
                $this->logMalformedResponseField('initial relationship target', $target);
                continue;
            }
            if (!is_array($data) || (!empty($data) && array_is_list($data))) {
                $this->logMalformedResponseField('initial relationship entry', $data);
                continue;
            }

            if (array_key_exists('aff', $data) && (!is_numeric($data['aff']) || is_bool($data['aff']))) {
                $this->logMalformedResponseField('initial relationship affinity', $data['aff']);
                continue;
            }
            $aff = array_key_exists('aff', $data) ? intval($data['aff']) : 0;

            $rawType = 'neutral';
            if (array_key_exists('type', $data)) {
                if (is_string($data['type'])) {
                    $rawType = strtolower(trim($data['type']));
                } else {
                    $this->logMalformedResponseField('initial relationship type', $data['type']);
                }
            }
            $type = RelationshipManager::canonicalizeRelationshipType($rawType);

            $note = '';
            if (array_key_exists('note', $data)) {
                if (is_string($data['note'])) {
                    $note = trim($data['note']);
                } else {
                    $this->logMalformedResponseField('initial relationship note', $data['note']);
                }
            }
            $aff = max(-100, min(100, $aff));
            if ($type === null) {
                Logger::info("[REL-LLM] REJECTED invented initial relationship type '{$rawType}' for {$target}; using neutral");
                $type = 'neutral';
            }

            // Normalize player references
            $target = trim($target);
            $targetLower = strtolower($target);
            if (in_array($targetLower, ['narrator', 'the narrator'], true)) {
                continue; // never track the narrator as a relationship target
            }
            $target = RelationshipManager::normalizeTargetName($target);

            $rel = ['aff' => $aff, 'type' => $type];
            if (!empty($note)) {
                $rel['note'] = $note;
            }
            $relationships[$target] = $rel;
        }

        return $relationships;
    }

    /**
     * Save relationships to NPC's extended_data
     */
    private function saveRelationships($npcId, $relationships, $replaceExisting = false) {
        require_once $GLOBALS['ENGINE_PATH'] . "lib/core/npc_master.class.php";

        // Advisory lock to prevent race conditions
        $this->acquireNpcLock($npcId);

        try {
            $npcMaster = new NpcMaster();
            $npc = $npcMaster->getById($npcId);

            if (!$npc) {
                $this->releaseNpcLock($npcId);
                return false;
            }

            $extended = $this->safeJsonDecode($npc['extended_data'] ?? null, "saveRelationships:{$npc['npc_name']}");
            if ($extended === null) {
                // CRITICAL: Corrupted data - abort to prevent data loss
                Logger::error("[REL-LLM] ABORT: saveRelationships for {$npc['npc_name']} - corrupted extended_data");
                $this->releaseNpcLock($npcId);
                return false;
            }
            // USER LOCK: the editor lock checkbox was only honored by postrequest - this path
            // kept overwriting manual UI edits. Locked NPCs are user-curated; never machine-write their map.
            if (!empty($extended['relationships_locked'])) {
                Logger::info("[REL-LLM] SKIP saveRelationships for {$npc['npc_name']} - relationships_locked (manual edits protected)");
                $this->releaseNpcLock($npcId);
                return false;
            }

            $incomingRelationships = RelationshipManager::normalizeRelationshipMap($relationships);
            if ($replaceExisting) {
                $extended['relationships'] = $incomingRelationships;
            } else {
                $existingRelationships = RelationshipManager::normalizeRelationshipMap($extended['relationships'] ?? []);
                foreach ($incomingRelationships as $target => $relationship) {
                    if (!isset($existingRelationships[$target])) {
                        $existingRelationships[$target] = $relationship;
                    }
                }
                $extended['relationships'] = $existingRelationships;
            }
            $extended['relationships_analyzed'] = date('Y-m-d H:i:s');
            $extended['relationships_model'] = $this->modelName;

            $result = chimRunWithRelationshipExtendedDataWrite(function () use ($npcMaster, $npcId, $extended) {
                return $npcMaster->updateByArray([
                    'id' => $npcId,
                    'extended_data' => json_encode($extended, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                ]);
            });
            if ($result !== false && function_exists('chimRelationshipTimelineStamp')) {
                chimRelationshipTimelineStamp($npcId);
            }

            $this->releaseNpcLock($npcId);
            return $result;
        } catch (Exception $e) {
            $this->releaseNpcLock($npcId);
            throw $e;
        }
    }

    /**
     * Batch process all NPCs that need relationship analysis
     */
    public function batchAnalyze($limit = 50, $forceReanalyze = false) {
        $results = [
            'processed' => 0,
            'skipped' => 0,
            'errors' => 0,
            'details' => []
        ];

        // Get NPCs with TEXT relationships
        $query = "SELECT id, npc_name, relationships, extended_data
                  FROM core_npc_master
                  WHERE relationships IS NOT NULL AND relationships != ''
                  ORDER BY id
                  LIMIT " . intval($limit);

        $npcs = $this->db->fetchAll($query);

        foreach ($npcs as $npc) {
            // Check if already processed
            $extended = $this->safeJsonDecode($npc['extended_data'] ?? null, "batchAnalyze:{$npc['npc_name']}");
            if ($extended === null) {
                $results['errors']++;
                $results['details'][] = [
                    'npc' => $npc['npc_name'],
                    'error' => 'Corrupted extended_data'
                ];
                continue;
            }
            if (!empty($extended['relationships']) && !$forceReanalyze) {
                $results['skipped']++;
                continue;
            }

            $result = $this->analyzeNpc($npc['id'], $forceReanalyze);

            if ($result['ok'] && empty($result['skipped'])) {
                $results['processed']++;
                $results['details'][] = [
                    'npc' => $npc['npc_name'],
                    'count' => $result['count'] ?? 0
                ];
            } elseif (!$result['ok']) {
                $results['errors']++;
                $results['details'][] = [
                    'npc' => $npc['npc_name'],
                    'error' => $result['error'] ?? 'Unknown error'
                ];
            } else {
                $results['skipped']++;
            }

            // Small delay to avoid rate limiting
            usleep(100000); // 100ms
        }

        return $results;
    }

    /**
     * Infer transitive relationships
     * If A loves B (+80) and B hates C (-70), then A should be wary of C (-20 to -40)
     */
    public function inferTransitiveRelationships($npcId) {
        require_once $GLOBALS['ENGINE_PATH'] . "lib/core/npc_master.class.php";

        $npcMaster = new NpcMaster();
        $npc = $npcMaster->getById($npcId);

        if (!$npc) return ['ok' => false, 'error' => 'NPC not found'];

        $extended = $this->safeJsonDecode($npc['extended_data'] ?? null, "inferTransitive:{$npc['npc_name']}");
        if ($extended === null) {
            return ['ok' => false, 'error' => 'Corrupted extended_data'];
        }
        $myRels = $extended['relationships'] ?? [];

        if (empty($myRels)) {
            return ['ok' => true, 'inferred' => 0];
        }

        $inferred = [];

        // For each of my relationships
        foreach ($myRels as $targetName => $targetData) {
            $myAffinity = $targetData['aff'];

            // Skip weak relationships
            if (abs($myAffinity) < 30) continue;

            // Find the target NPC
            $escapedTarget = $this->db->escape($targetName);
            $targetNpc = $this->db->fetchOne(
                "SELECT id, extended_data FROM core_npc_master WHERE npc_name = '" . $escapedTarget . "' LIMIT 1"
            );

            if (!$targetNpc) continue;

            $targetExtended = $this->safeJsonDecode($targetNpc['extended_data'] ?? null, "inferTransitive:target:{$targetName}");
            if ($targetExtended === null) continue; // Skip corrupted target, don't abort
            $targetRels = $targetExtended['relationships'] ?? [];

            // Check target's relationships
            foreach ($targetRels as $thirdParty => $thirdData) {
                // Skip if I already have a relationship with this entity
                if (isset($myRels[$thirdParty])) continue;

                // Skip self-reference
                if ($thirdParty === $npc['npc_name']) continue;

                $theirAffinity = $thirdData['aff'];

                // Calculate transitive affinity
                // If I love someone (+80) who hates someone else (-70), I become wary (-30ish)
                // If I love someone (+80) who loves someone (+80), I become warm (+30ish)
                $transitiveAff = intval(($myAffinity * $theirAffinity) / 200);

                // Only infer if significant
                if (abs($transitiveAff) >= 15) {
                    $transitiveAff = max(-50, min(50, $transitiveAff)); // Cap at moderate levels

                    $inferred[$thirdParty] = [
                        'aff' => $transitiveAff,
                        'type' => $transitiveAff > 0 ? 'neutral' : 'rival',
                        'inferred_from' => $targetName
                    ];
                }
            }
        }

        if (!empty($inferred)) {
            // Advisory lock to prevent race conditions
            $this->acquireNpcLock($npcId);

            try {
                // Re-fetch to get latest state after acquiring lock
                $npc = $npcMaster->getById($npcId);
                $extended = $this->safeJsonDecode($npc['extended_data'] ?? null, "inferTransitive:save:{$npc['npc_name']}");
                if ($extended === null) {
                    // CRITICAL: Corrupted data - abort to prevent data loss
                    Logger::error("[REL-LLM] ABORT: inferTransitive save for {$npc['npc_name']} - corrupted extended_data");
                    $this->releaseNpcLock($npcId);
                    return ['ok' => false, 'error' => 'Corrupted extended_data during save'];
                }
                $myRels = $extended['relationships'] ?? [];

                // Merge with existing (don't overwrite explicit relationships)
                foreach ($inferred as $target => $data) {
                    if (!isset($myRels[$target])) {
                        $myRels[$target] = $data;
                    }
                }

                $extended['relationships'] = $myRels;
                $extended['relationships_inferred'] = date('Y-m-d H:i:s');

                $result = chimRunWithRelationshipExtendedDataWrite(function () use ($npcMaster, $npcId, $extended) {
                    return $npcMaster->updateByArray([
                        'id' => $npcId,
                        'extended_data' => json_encode($extended, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    ]);
                });
                if ($result !== false && function_exists('chimRelationshipTimelineStamp')) {
                    chimRelationshipTimelineStamp($npcId);
                }

                $this->releaseNpcLock($npcId);
                Logger::info("[REL-LLM] Inferred " . count($inferred) . " relationships for " . $npc['npc_name']);
            } catch (Exception $e) {
                $this->releaseNpcLock($npcId);
                throw $e;
            }
        }

        return [
            'ok' => true,
            'inferred' => count($inferred),
            'relationships' => $inferred
        ];
    }

    /**
     * DYNAMIC RELATIONSHIP EVALUATION
     *
     * Called after each conversation turn to evaluate if relationships should change.
     * Receives the full context (recent dialogue, events, actions) and decides
     * what affinity changes should occur.
     *
     * This is the "arbiter" function - it sees everything and makes the final call.
     *
     * @param int $npcId The NPC who is speaking
     * @param string $npcResponse The NPC's response text
     * @param array $context Full context including recent events, dialogue, player actions
     * @return array Changes to apply: ['Player' => ['delta' => +15, 'type' => null], ...]
     */
    public function evaluateContext($npcId, $npcResponse, $context = []) {
        if (!$this->isAvailable()) {
            return ['ok' => false, 'error' => 'LLM not available'];
        }

        require_once $GLOBALS['ENGINE_PATH'] . "lib/core/npc_master.class.php";

        $npcMaster = new NpcMaster();
        $npc = $npcMaster->getById($npcId);

        if (!$npc) {
            return ['ok' => false, 'error' => 'NPC not found'];
        }

        $npcName = $npc['npc_name'];

        // Get current relationships
        $extended = $this->safeJsonDecode($npc['extended_data'] ?? null, "evaluateContext:{$npcName}");
        if ($extended === null) {
            return ['ok' => false, 'error' => 'Corrupted extended_data'];
        }
        $currentRels = RelationshipManager::normalizeRelationshipMap($extended['relationships'] ?? []);

        // Build context string
        $contextStr = "";
        $contextStr .= $this->extensionCharacterFacts($npcName); // extension character facts

        // Director instruction context (rolemaster guidance)
        // This explains why an NPC might behave in ways that seem out of character
        // e.g., if instructed to "be rude", don't penalize the Player relationship
        if (!empty($context['director_instruction'])) {
            $contextStr .= "⚠ DIRECTOR INSTRUCTION (game master guidance that prompted this response):\n";
            $contextStr .= "\"" . $context['director_instruction'] . "\"\n";
            $contextStr .= "NOTE: The NPC's behavior below was DIRECTED by the game master. ";
            $contextStr .= "Attribute the behavior to following instructions, not to genuine feelings toward the other party.\n\n";
        }

        // Recent events
        if (!empty($context['events'])) {
            $contextStr .= "Recent Events:\n" . implode("\n", array_slice($context['events'], -10)) . "\n\n";
        }

        // Build EXPLICIT speaker/listener attribution
        // CRITICAL: The LLM must know unambiguously WHO is the speaker and WHO is the listener
        // to record the correct relationship direction
        //
        // In Player<->NPC conversations:
        // - SPEAKER: The NPC (whose feelings we're recording)
        // - LISTENER: The Player (who they're talking to)
        //
        // Note: $context['dialogue'] contains the NPC's previous lines (talkedSoFar)
        // $context['player_action'] is what the Player said/did
        // $npcResponse is the NPC's latest response being evaluated

        $playerName = $this->getPlayerName();
        $listenerName = $context['listener_name'] ?? 'Player';
        $listenerKey = ($listenerName === 'Player' || strcasecmp($listenerName, $playerName) === 0) ? 'Player' : $listenerName;

        // EXPLICIT SPEAKER/LISTENER HEADER
        $contextStr .= "═══════════════════════════════════════════════════════\n";
        $contextStr .= "SPEAKER: {$npcName} (the NPC whose feelings we are recording)\n";
        $contextStr .= "LISTENER: {$listenerKey} (who they were talking to)\n";
        $contextStr .= "═══════════════════════════════════════════════════════\n\n";

        $contextStr .= "Your task: Record how {$npcName} felt about this exchange with {$listenerKey}.\n";
        $contextStr .= "Your output MUST include \"{$listenerKey}\" - this is mandatory, not optional.\n\n";

        $contextStr .= "CONVERSATION:\n";

        // Player's action/speech (what triggered this NPC response)
        if (!empty($context['player_action'])) {
            $contextStr .= "[{$listenerKey} said]: " . $context['player_action'] . "\n";
        }

        // NPC's response to the Player
        $contextStr .= "[{$npcName} replied]: " . $npcResponse . "\n";

        // Recent dialogue history (for additional context, but clearly labeled)
        if (!empty($context['dialogue'])) {
            $recentLines = array_slice($context['dialogue'], -4);
            if (!empty($recentLines)) {
                $contextStr .= "\nPrevious exchanges (for context):\n";
                foreach ($recentLines as $line) {
                    // These are the NPC's previous lines
                    $contextStr .= "  [{$npcName} said earlier]: " . $line . "\n";
                }
            }
        }
        $contextStr .= "\n";

        // Current relationship state (for context)
        // Only include Player + nearby NPCs + mentioned NPCs (not ALL relationships)
        $relStateStr = "";

        // Always include Player
        if (isset($currentRels['Player'])) {
            $data = $currentRels['Player'];
            $relStateStr .= "  Player: {$data['aff']} ({$data['type']})\n";
        } else {
            $relStateStr .= "  Player: 0 (neutral)\n";
        }

        // Get nearby NPCs from context
        $nearbyNpcs = $context['nearby_npcs'] ?? [];

        // Scan dialogue/events for mentioned NPCs
        $mentionedNpcs = [];
        $textToScan = $npcResponse . ' ' . ($context['player_action'] ?? '');
        if (!empty($context['dialogue'])) {
            $textToScan .= ' ' . implode(' ', $context['dialogue']);
        }
        $textLower = strtolower($textToScan);

        foreach (array_keys($currentRels) as $knownNpc) {
            if ($knownNpc === 'Player') continue;
            if (stripos($textLower, strtolower($knownNpc)) !== false) {
                $mentionedNpcs[] = $knownNpc;
            }
        }

        // Combine nearby + mentioned, remove duplicates
        $relevantNpcs = array_unique(array_merge($nearbyNpcs, $mentionedNpcs));

        // Add relevant NPCs to relationship state
        foreach ($relevantNpcs as $npcTarget) {
            $npcTarget = trim($npcTarget);
            if (empty($npcTarget) || strtolower($npcTarget) === 'player') continue;
            if (isset($currentRels[$npcTarget])) {
                $data = $currentRels[$npcTarget];
                $relStateStr .= "  {$npcTarget}: {$data['aff']} ({$data['type']})\n";
            }
        }

        $systemPrompt = $this->getDynamicEvalPrompt();

        // Build output key instruction based on listener
        $outputKeyInstruction = "";
        if ($listenerKey === 'Player') {
            $outputKeyInstruction = "IMPORTANT: Use \"Player\" as the key (not \"{$playerName}\").";
        } else {
            $outputKeyInstruction = "IMPORTANT: Use \"{$listenerKey}\" as the key for the listener NPC.";
        }

        $userPrompt = <<<PROMPT
═══════════════════════════════════════════════════════
SPEAKER: {$npcName} (whose feelings we are recording)
LISTENER: {$listenerKey} (who they were talking to)
═══════════════════════════════════════════════════════

CURRENT RELATIONSHIPS:
{$relStateStr}

CONTEXT:
{$contextStr}

Based on this interaction, how did {$npcName}'s feelings toward {$listenerKey} change?
Consider: Was there kindness, insult, betrayal, gratitude, violence, romance, etc.?
Only suggest changes for SIGNIFICANT moments - not every interaction needs a change.

{$outputKeyInstruction}

Return JSON: {"changes": {"{$listenerKey}": {"delta": X, "reason": "brief"}}}
If (and ONLY if) this exchange changed the relationship's NATURE - a romance forming,
a betrayal, marriage, becoming enemies - also include "type". "type" must be EXACTLY one
of: romantic, platonic, familial, professional, rival, enemy, crush, ex, betrayed
(the value is "romantic", never "romance"), e.g.
{"changes": {"{$listenerKey}": {"delta": X, "type": "crush", "reason": "brief"}}}
Be conservative: one passing line never flips a type. The change must be backed by sustained
energy or real/implied history, and it must come from {$npcName}'s own expressed feelings -
{$listenerKey} pushing or flirting is not grounds for it.
Or if no changes: {"changes": {}}
PROMPT;

        $contextData = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ];

        Logger::info("[REL-LLM] Evaluating context for {$npcName}");

        // Make LLM request with scoped global swapping
        $response = $this->makeSafeRequest(
            $contextData,
            ["MAX_TOKENS" => 512],
            "relationship_eval"
        );

        // Log to audit_request for UI visibility
        $this->logToAudit($contextData, $response, "eval_{$npcName}");

        // Parse the response
        $changes = $this->parseEvalResponse($response);

        if (empty($changes)) {
            return ['ok' => true, 'changes' => [], 'reason' => 'No significant changes'];
        }

        // Apply the changes
        $applied = $this->applyChanges($npcId, $changes, $currentRels);

        Logger::info("[REL-LLM] Applied " . count($applied) . " changes for {$npcName}");

        return [
            'ok' => true,
            'changes' => $applied,
            'model' => $this->modelName
        ];
    }

    /**
     * Evaluate NPC-to-NPC conversation context (token-efficient bidirectional)
     *
     * When one NPC speaks to another, both may form impressions.
     * This method evaluates BOTH perspectives in a single LLM call,
     * saving ~50% tokens compared to two separate evaluateContext() calls.
     *
     * @param int $speakerNpcId The NPC who spoke
     * @param int $listenerNpcId The NPC who listened
     * @param string $dialogue What was said
     * @param array $context Additional context (events, etc)
     * @return array Results for both NPCs
     */
    public function evaluateNpcToNpcContext($speakerNpcId, $listenerNpcId, $dialogue, $context = []) {
        if (!$this->isAvailable()) {
            return ['ok' => false, 'error' => 'LLM not available'];
        }

        require_once $GLOBALS['ENGINE_PATH'] . "lib/core/npc_master.class.php";

        $npcMaster = new NpcMaster();
        $speaker = $npcMaster->getById($speakerNpcId);
        $listener = $npcMaster->getById($listenerNpcId);

        if (!$speaker || !$listener) {
            return ['ok' => false, 'error' => 'NPC(s) not found'];
        }

        $speakerName = $speaker['npc_name'];
        $listenerName = $listener['npc_name'];

        // Get current relationships for both NPCs
        $speakerExtended = $this->safeJsonDecode($speaker['extended_data'] ?? null, "npc2npc:speaker:{$speakerName}");
        $listenerExtended = $this->safeJsonDecode($listener['extended_data'] ?? null, "npc2npc:listener:{$listenerName}");

        // If either is corrupted, skip them but don't abort completely
        if ($speakerExtended === null) {
            Logger::warn("[REL-LLM] Skipping speaker {$speakerName} - corrupted extended_data");
            $speakerExtended = [];
        }
        if ($listenerExtended === null) {
            Logger::warn("[REL-LLM] Skipping listener {$listenerName} - corrupted extended_data");
            $listenerExtended = [];
        }

        $speakerRels = $speakerExtended['relationships'] ?? [];
        $listenerRels = $listenerExtended['relationships'] ?? [];

        // Build context string
        $contextStr = "";
        $contextStr .= $this->extensionCharacterFacts($speakerName);  // extension character facts, both parties
        $contextStr .= $this->extensionCharacterFacts($listenerName);

        // Director instruction context (rolemaster guidance)
        if (!empty($context['director_instruction'])) {
            $contextStr .= "⚠ DIRECTOR INSTRUCTION (game master guidance that prompted this response):\n";
            $contextStr .= "\"" . $context['director_instruction'] . "\"\n";
            $contextStr .= "NOTE: The speaker's behavior was DIRECTED by the game master, not driven by genuine feelings.\n\n";
        }

        if (!empty($context['events'])) {
            $contextStr .= "Recent Events:\n" . implode("\n", array_slice($context['events'], -5)) . "\n\n";
        }
        $contextStr .= "What was said: " . $dialogue . "\n";

        // Current relationship states
        $speakerRelWithListener = $speakerRels[$listenerName] ?? ['aff' => 0, 'type' => 'neutral'];
        $listenerRelWithSpeaker = $listenerRels[$speakerName] ?? ['aff' => 0, 'type' => 'neutral'];

        $systemPrompt = $this->getNpcToNpcEvalPrompt();

        $userPrompt = <<<PROMPT
NPC-TO-NPC INTERACTION:

SPEAKER (the one who said this): {$speakerName}
  Currently feels toward {$listenerName}: {$speakerRelWithListener['aff']} ({$speakerRelWithListener['type']})

LISTENER (the one who heard it): {$listenerName}
  Currently feels toward {$speakerName}: {$listenerRelWithSpeaker['aff']} ({$listenerRelWithSpeaker['type']})

{$contextStr}

{$speakerName} SAID the above dialogue. {$listenerName} HEARD it.

Evaluate:
- "speaker" = Did {$speakerName}'s feelings toward {$listenerName} change?
- "listener" = Did {$listenerName}'s feelings toward {$speakerName} change after hearing this?

Return JSON using exactly "speaker" and "listener" as keys:
PROMPT;

        $contextData = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ];

        Logger::info("[REL-LLM] Evaluating NPC-to-NPC: {$speakerName} <-> {$listenerName}");

        // Make LLM request with scoped global swapping
        $response = $this->makeSafeRequest(
            $contextData,
            ["MAX_TOKENS" => 512],
            "relationship_npc_to_npc"
        );

        // Log to audit
        $this->logToAudit($contextData, $response, "npc2npc_{$speakerName}_{$listenerName}");

        // Parse bidirectional response (pass names so it can handle NPC-name-as-key format)
        $parsed = $this->parseNpcToNpcResponse($response, $speakerName, $listenerName);

        // Debug: Log parsed response
        Logger::debug("[REL-LLM] NPC-to-NPC raw response: " . substr($response, 0, 500));
        Logger::debug("[REL-LLM] NPC-to-NPC parsed: speaker=" . json_encode($parsed['speaker']) . " listener=" . json_encode($parsed['listener']));

        $results = [
            'ok' => true,
            'speaker' => ['name' => $speakerName, 'changes' => []],
            'listener' => ['name' => $listenerName, 'changes' => []],
            'model' => $this->modelName
        ];

        // Apply speaker's changes (their feelings toward listener)
        if (!empty($parsed['speaker'])) {
            $speakerChanges = [$listenerName => $parsed['speaker']];
            $results['speaker']['changes'] = $this->applyChanges($speakerNpcId, $speakerChanges, $speakerRels);
        }

        // Apply listener's changes (their feelings toward speaker)
        if (!empty($parsed['listener'])) {
            $listenerChanges = [$speakerName => $parsed['listener']];
            $results['listener']['changes'] = $this->applyChanges($listenerNpcId, $listenerChanges, $listenerRels);
        }

        $totalChanges = count($results['speaker']['changes']) + count($results['listener']['changes']);
        Logger::info("[REL-LLM] NPC-to-NPC: Applied {$totalChanges} changes ({$speakerName} <-> {$listenerName})");

        return $results;
    }

    /**
     * Get the system prompt for NPC-to-NPC evaluation
     */
    private function getNpcToNpcEvalPrompt() {
        $fallback = <<<'PROMPT'
You are a behavioral psychologist. Evaluate NPC-to-NPC interaction briefly.

DIRECTION:
- speaker = NPC who SPOKE
- listener = NPC who HEARD
- speaker.delta = speaker's feelings toward listener changed?
- listener.delta = listener's feelings toward speaker changed?

SCALE: +/-1 typical, +/-2-3 notable, +/-5+ significant. Be conservative.

REASON FORMAT - Under 15 words:
✓ "Dark humor built rapport"
✓ "Bossy tone caused mild resentment"
✓ "Helpful advice appreciated"

OUTPUT - Use exactly "speaker" and "listener":
{"speaker": {"delta": 0, "reason": "brief"}, "listener": {"delta": 1, "reason": "brief"}}

No changes? Return empty objects: {}
PROMPT;

        return $this->loadPrompt('rel_llm_npc_to_npc', $fallback);
    }

    /**
     * Parse NPC-to-NPC evaluation response
     * Handles both "speaker"/"listener" format and NPC name keys
     */
    private function parseNpcToNpcResponse($response, $speakerName = '', $listenerName = '') {
        $jsonResponse = $response;
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $response, $matches)) {
            $jsonResponse = trim($matches[1]);
        }

        $jsonResponse = str_replace(
            ["\xe2\x80\x9c", "\xe2\x80\x9d", "\xe2\x80\x98", "\xe2\x80\x99", "*"],
            ['"', '"', "'", "'", ""],
            $jsonResponse
        );
        $jsonResponse = preg_replace('/[\x00-\x1F\x7F]/u', '', $jsonResponse);

        $parsed = json_decode($jsonResponse, true);

        if ($parsed === null) {
            if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
                $cleanJson = str_replace(
                    ["\xe2\x80\x9c", "\xe2\x80\x9d", "\xe2\x80\x98", "\xe2\x80\x99", "*"],
                    ['"', '"', "'", "'", ""],
                    $matches[0]
                );
                $cleanJson = preg_replace('/[\x00-\x1F\x7F]/u', '', $cleanJson);
                $parsed = json_decode($cleanJson, true);
            }
        }

        if (!is_array($parsed) || (!empty($parsed) && array_is_list($parsed))) {
            $this->logMalformedResponseField('NPC-to-NPC response', $parsed);
            return ['speaker' => [], 'listener' => []];
        }

        // Try standard format first
        $speakerData = $parsed['speaker'] ?? null;
        $listenerData = $parsed['listener'] ?? null;

        // If not found, try NPC name keys (LLM sometimes uses actual names)
        if ($speakerData === null && !empty($speakerName) && isset($parsed[$speakerName])) {
            $speakerData = $parsed[$speakerName];
        }
        if ($listenerData === null && !empty($listenerName) && isset($parsed[$listenerName])) {
            $listenerData = $parsed[$listenerName];
        }

        return [
            'speaker' => $speakerData === null ? [] : $this->normalizeChangeData($speakerData, 'NPC-to-NPC speaker'),
            'listener' => $listenerData === null ? [] : $this->normalizeChangeData($listenerData, 'NPC-to-NPC listener')
        ];
    }

    /**
     * Get the system prompt for dynamic evaluation
     * Loads from database prompts table, falls back to hardcoded default
     */
    private function getDynamicEvalPrompt() {
        $fallback = <<<'PROMPT'
You are a behavioral psychologist. Evaluate interactions and provide BRIEF insight.

SPEAKER ATTRIBUTION:
- [PLAYER] and [NPC] tags show who said what
- Only evaluate based on what PLAYER did, not the NPC's own words

AFFINITY SCALE (-100 to +100):
- +/-1: Normal chat
- +/-2-3: Notably friendly/rude, small favors
- +/-5-10: Meaningful help, gifts, insults
- +/-15-25: Saving life, violence, betrayal
- +/-50+: Extreme events (killing loved ones, marriage)

MOST INTERACTIONS = 0 or +/-1. Be conservative. Skip trivial exchanges.

REASON FORMAT - Keep it SHORT (under 15 words):
✓ "Teasing triggered defensiveness"
✓ "Genuine interest validates their experience"
✓ "Protective action builds trust"
✗ NOT: Long clinical explanations

TYPE CHANGES (rare - only for DEFINING moments):
- Add "type" ONLY when this exchange visibly changed the NATURE of the relationship:
  a romance forming, a betrayal, marriage, a family reveal, becoming enemies.
- "type" must be EXACTLY one of: romantic, platonic, familial, professional, rival,
  enemy, crush, ex, betrayed. Never invent other labels: the value is "romantic",
  NOT "romance"; "betrayed", NOT "betrayal"; "enemy", NOT "enemies".
- Be conservative. One passing compliment, joke, or flirty line NEVER flips a type.
  A type change must be backed by sustained energy in this exchange or by the history
  between them - repeated warmth, declarations, a shared past, stated or clearly implied.
- The evidence must come from the SPEAKER's own expressed feelings. The other party
  flirting, pushing, or asking is never grounds to flip a type.
- When in doubt, omit "type". Most interactions only adjust affinity.

OUTPUT (JSON only):
{"changes": {"Player": {"delta": 1, "reason": "brief insight"}}}
Defining type change: {"changes": {"Player": {"delta": 5, "type": "crush", "reason": "confessed her crush"}}}

No changes? Return: {"changes": {}}
PROMPT;

        $prompt = $this->loadPrompt('rel_llm_evaluation', $fallback);
        // Seeded defaults from before the type-change syntax existed teach a delta-only output;
        // upgrade them in place. Custom prompts that already know "type" are left alone.
        if (strpos($prompt, 'only for defining moments') !== false && strpos($prompt, '"type"') === false) {
            return $fallback;
        }
        // Older defaults listed the defining MOMENTS ("romance, betrayal, marriage...") right where
        // a type value was expected, so models copied them verbatim as types ("romance" instead of
        // "romantic") and the sex-eligibility gates never matched. Supersede any prompt (seeded
        // default or stale custom) still teaching that wording with the corrected fallback.
        if (strpos($prompt, 'romance, betrayal') !== false) {
            return $fallback;
        }
        return $prompt;
    }

    /**
     * Parse the evaluation response
     */
    private function parseEvalResponse($response) {
        $jsonResponse = $response;
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $response, $matches)) {
            $jsonResponse = trim($matches[1]);
        }

        $jsonResponse = str_replace(
            ["\xe2\x80\x9c", "\xe2\x80\x9d", "\xe2\x80\x98", "\xe2\x80\x99", "*"],
            ['"', '"', "'", "'", ""],
            $jsonResponse
        );
        $jsonResponse = preg_replace('/[\x00-\x1F\x7F]/u', '', $jsonResponse);

        $parsed = json_decode($jsonResponse, true);

        if ($parsed === null) {
            if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
                $cleanJson = str_replace(
                    ["\xe2\x80\x9c", "\xe2\x80\x9d", "\xe2\x80\x98", "\xe2\x80\x99", "*"],
                    ['"', '"', "'", "'", ""],
                    $matches[0]
                );
                $cleanJson = preg_replace('/[\x00-\x1F\x7F]/u', '', $cleanJson);
                $parsed = json_decode($cleanJson, true);
            }
        }

        if (!is_array($parsed)) {
            $this->logMalformedResponseField('dynamic response', $parsed);
            return [];
        }

        return $this->normalizeChangeMap($parsed['changes'] ?? [], 'dynamic response');
    }

    /**
     * Apply relationship changes
     */
    private function applyChanges($npcId, $changes, $currentRels) {
        require_once $GLOBALS['ENGINE_PATH'] . "lib/core/npc_master.class.php";

        if (!is_array($changes)) {
            $this->logMalformedResponseField('apply changes container', $changes);
            return [];
        }
        if (!is_array($currentRels)) {
            $currentRels = [];
        }

        $npcMaster = new NpcMaster();
        $npc = $npcMaster->getById($npcId);
        if (!$npc) return [];

        $applied = [];

        // Titles/roles that should NOT be stored as relationships
        // Factions/groups ARE allowed (Stormcloaks, Companions, Thieves Guild, etc.)
        $blockedTitles = [
            'dragonborn', 'the dragonborn',
            'arch mage', 'archmage', 'the arch mage',
            'harbinger', 'the harbinger',
            'listener', 'the listener',
            'guildmaster', 'guild master', 'the guildmaster',
            'thane', 'the thane',
            'stormblade', 'the stormblade',
            'legate', 'the legate',
            'hero', 'the hero',
            'champion', 'the champion',
            'chosen one', 'the chosen one',
            'nightingale', 'the nightingale',
            'narrator', 'the narrator'
        ];

        // Custom types become selectable only after the player has created and saved
        // one in the relationship editor. The shared manager owns canonicalization.
        $allowedCustomTypes = RelationshipManager::getCustomRelationshipTypes($currentRels);

        foreach ($changes as $target => $change) {
            if (!is_string($target) || trim($target) === '' || !is_array($change)) {
                $this->logMalformedResponseField('apply relationship change', $change);
                continue;
            }

            $rawType = $change['type'] ?? null;
            $newType = $rawType === null
                ? null
                : RelationshipManager::canonicalizeRelationshipType($rawType, $allowedCustomTypes);
            if ($rawType !== null && $newType === null) {
                $loggedType = is_scalar($rawType) ? (string)$rawType : gettype($rawType);
                Logger::info("[REL-LLM] REJECTED invented type '{$loggedType}' for {$npc['npc_name']} -> {$target}: not an available type (delta still applies)");
            }
            $target = trim($target);

            $deltaValue = $change['delta'] ?? 0;
            $delta = is_numeric($deltaValue) && !is_bool($deltaValue) ? intval($deltaValue) : 0;
            $reason = is_string($change['reason'] ?? null) ? trim($change['reason']) : '';
            $relation = is_string($change['relation'] ?? null) ? strtolower(trim($change['relation'])) : null;
            $relation = $relation === '' ? null : $relation;

            // Skip titles/roles (but allow factions/groups)
            if (in_array(strtolower(trim($target)), $blockedTitles)) {
                Logger::debug("[REL-LLM] Skipping title/role as relationship target: {$target}");
                continue;
            }

            // Normalize player name references to canonical "Player"
            $target = RelationshipManager::normalizeTargetName($target);

            $targetExists = isset($currentRels[$target]);
            if ($targetExists && !is_array($currentRels[$target])) {
                $this->logMalformedResponseField('stored relationship entry', $currentRels[$target]);
                continue;
            }

            // REL LLMs often emit {"delta":0,"type":"neutral"} as a generic
            // "no meaningful change" shape. Do not let that create or downgrade
            // Player/NPC rows to 0 neutral.
            if ($delta === 0) {
                if ($newType === null || $newType === 'neutral' || !$targetExists) {
                    Logger::debug("[REL-LLM] Ignoring zero-delta/no-op relationship output for {$npc['npc_name']} -> {$target}");
                    continue;
                }
            }

            // Initialize if doesn't exist
            if (!$targetExists) {
                $currentRels[$target] = ['aff' => 0, 'type' => 'neutral'];
            }

            $oldAffValue = $currentRels[$target]['aff'] ?? 0;
            if (!is_numeric($oldAffValue) || is_bool($oldAffValue)) {
                $this->logMalformedResponseField('stored relationship affinity', $oldAffValue);
                continue;
            }
            $oldAff = intval($oldAffValue);
            $oldType = is_string($currentRels[$target]['type'] ?? null)
                ? $currentRels[$target]['type']
                : 'neutral';
            $newAff = max(-100, min(100, $oldAff + $delta));
            $currentRels[$target]['aff'] = $newAff;

            $typeChanged = false;
            $finalType = $oldType;

            // EARNED-ROMANCE GATE: promotion INTO a romantic-leaning type requires Fond-tier
            // affinity (56+, matches getAffinityTierName) and an explicit reason from the model.
            // Below that, blocked - one flirty exchange must not flip a stranger to romantic.
            // Downgrades OUT of romantic and moves between non-romantic types are unaffected.
            $romanticTypes = ['romantic', 'crush', 'admirer', 'obsessed', 'infatuated', 'lover'];
            $oldIsRomantic = in_array(strtolower((string)$oldType), $romanticTypes, true);

            if ($newType) {
                // LLM explicitly set a type
                $newTypeLower = strtolower($newType);
                $promotesToRomantic = in_array($newTypeLower, $romanticTypes, true) && !$oldIsRomantic;
                if ($promotesToRomantic && ($newAff < 56 || trim((string)$reason) === '')) {
                    Logger::info("[REL-LLM] BLOCKED romantic promotion: {$npc['npc_name']} -> {$target}: {$oldType} => {$newTypeLower} (aff {$newAff} below fond tier or no reason; kept '{$oldType}')");
                } else {
                    if ($oldType !== $newTypeLower) {
                        Logger::info("[REL-LLM] TYPE CHANGE: {$npc['npc_name']} -> {$target}: {$oldType} => {$newTypeLower}");
                        $typeChanged = true;
                    }
                    $currentRels[$target]['type'] = $newTypeLower;
                    $finalType = $newTypeLower;
                }
            } else {
                // Auto-evolve type ONLY when leaving neutral
                // Once you've formed an opinion, you don't go back to neutral
                $currentType = $currentRels[$target]['type'];
                if ($currentType === 'neutral') {
                    $inferredType = $this->inferTypeFromAffinity($newAff);
                    if ($inferredType !== 'neutral' && !in_array($inferredType, $romanticTypes, true)) {
                        Logger::info("[REL-LLM] AUTO TYPE CHANGE: {$npc['npc_name']} -> {$target}: neutral => {$inferredType} (affinity: {$newAff})");
                        $currentRels[$target]['type'] = $inferredType;
                        $finalType = $inferredType;
                        $typeChanged = true;
                    }
                }
            }

            // Multi-field note system:
            // - note: Minor recent interactions (updates on delta >= 3)
            // - best: Most significant positive event (only replaced by larger positive)
            // - worst: Most significant negative event (only replaced by larger negative)
            // - best_delta/worst_delta: Track magnitude for comparison
            // - relation: Familial/role detail (son, father, mentor) - set once, rarely changes

            if (!empty($reason)) {
                // Always update 'note' for recent interactions (threshold: |delta| >= 3)
                if (abs($delta) >= 3 || empty($currentRels[$target]['note'] ?? '')) {
                    $currentRels[$target]['note'] = $reason;
                }

                // Track major positive events in 'best' (threshold: delta >= 10)
                if ($delta >= 10) {
                    $existingBestDelta = $currentRels[$target]['best_delta'] ?? 0;
                    // Only replace if this is more significant than previous best
                    if ($delta >= $existingBestDelta) {
                        $currentRels[$target]['best'] = $reason;
                        $currentRels[$target]['best_delta'] = $delta;
                    }
                }

                // Track major negative events in 'worst' (threshold: delta <= -10)
                if ($delta <= -10) {
                    $existingWorstDelta = $currentRels[$target]['worst_delta'] ?? 0;
                    // Only replace if this is more significant (more negative) than previous worst
                    // Note: We compare absolute values since both are negative
                    if ($delta <= $existingWorstDelta) {
                        $currentRels[$target]['worst'] = $reason;
                        $currentRels[$target]['worst_delta'] = $delta;
                    }
                }
            }

            // Handle relation field from LLM output (if provided)
            if ($relation !== null) {
                // Only set relation if not already set, or if explicitly changing
                if (empty($currentRels[$target]['relation'])) {
                    $currentRels[$target]['relation'] = $relation;
                }
            }

            $applied[$target] = [
                'old' => $oldAff,
                'new' => $newAff,
                'delta' => $delta,
                'type' => $finalType,
                'base_type' => $oldType,
                'requested_type' => $newType,
                'relation' => $relation,
                'reason' => $reason
            ];

            // Include type change info if type changed
            if ($typeChanged) {
                $applied[$target]['old_type'] = $oldType;
                $applied[$target]['type_changed'] = true;
            }

            Logger::info("[REL-LLM] {$npc['npc_name']} -> {$target}: " . sprintf("%+d", $delta) .
                      " (was {$oldAff}, now {$newAff})" . ($reason ? " - {$reason}" : ""));
        }

        if (!empty($applied)) {
            // Advisory lock to prevent race conditions
            $this->acquireNpcLock($npcId);

            try {
                // Re-fetch to get latest state after acquiring lock
                $npc = $npcMaster->getById($npcId);
                $extended = $this->safeJsonDecode($npc['extended_data'] ?? null, "applyChanges:{$npc['npc_name']}");
                if ($extended === null) {
                    // CRITICAL: Corrupted data - abort to prevent data loss
                    Logger::error("[REL-LLM] ABORT: applyChanges for {$npc['npc_name']} - corrupted extended_data");
                    $this->releaseNpcLock($npcId);
                    return []; // Return empty - changes not saved
                }
                // USER LOCK: honor the editor lock here too - this eval/rebase path was the
                // writer clobbering manual UI edits minutes after they were saved.
                if (!empty($extended['relationships_locked'])) {
                    Logger::info("[REL-LLM] SKIP applyChanges for {$npc['npc_name']} - relationships_locked (manual edits protected)");
                    $this->releaseNpcLock($npcId);
                    return []; // Return empty - changes not saved
                }

                // Merge only the relationship targets changed by this evaluation,
                // and rebase each delta onto the freshly fetched row. The evaluator
                // may have started from an old snapshot while the UI or another
                // worker changed the same target; copying the pre-eval target would
                // clobber that newer state.
                $existingRels = RelationshipManager::normalizeRelationshipMap($extended['relationships'] ?? []);
                $freshCustomTypes = RelationshipManager::getCustomRelationshipTypes($existingRels);
                foreach ($applied as $target => $change) {
                    $freshRel = $existingRels[$target] ?? [];
                    $rebasedRel = $this->rebaseRelationshipChange(
                        $freshRel,
                        $currentRels[$target] ?? [],
                        $change,
                        $freshCustomTypes
                    );
                    $freshAff = (int)($freshRel['aff'] ?? 0);
                    $staleAff = (int)($change['old'] ?? 0);
                    if ($freshAff !== $staleAff) {
                        Logger::info("[REL-LLM] Rebased {$npc['npc_name']} -> {$target}: fresh {$freshAff} + delta {$change['delta']} => {$rebasedRel['aff']}");
                    }
                    $existingRels[$target] = $rebasedRel;
                }

                $extended['relationships'] = $existingRels;
                $extended['relationships_last_eval'] = date('Y-m-d H:i:s');

                $jsonData = json_encode($extended, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                $result = chimRunWithRelationshipExtendedDataWrite(function () use ($npcMaster, $npcId, $jsonData) {
                    return $npcMaster->updateByArray([
                        'id' => $npcId,
                        'extended_data' => $jsonData
                    ]);
                });
                if ($result !== false && function_exists('chimRelationshipTimelineStamp')) {
                    chimRelationshipTimelineStamp($npcId);
                }

                $this->releaseNpcLock($npcId);
                Logger::debug("[REL-LLM] Database update for NPC {$npcId}: " . ($result === false ? "FAILED" : "OK") . " - relationships: " . json_encode($existingRels));
            } catch (Exception $e) {
                $this->releaseNpcLock($npcId);
                throw $e;
            }
        }

        return $applied;
    }

    /**
     * Apply an LLM relationship change to the latest DB value instead of the
     * stale value captured when the evaluation was queued.
     */
    private function rebaseRelationshipChange($freshRel, $computedRel, $change, $allowedCustomTypes = []) {
        if (!is_array($freshRel)) {
            $freshRel = [];
        }
        if (!is_array($computedRel)) {
            $computedRel = [];
        }

        $rebased = $freshRel;
        $freshAff = (int)($freshRel['aff'] ?? 0);
        $delta = (int)($change['delta'] ?? 0);
        $rebasedAff = max(-100, min(100, $freshAff + $delta));
        $rebased['aff'] = $rebasedAff;

        $freshType = strtolower(trim((string)($freshRel['type'] ?? 'neutral')));
        if ($freshType === '') {
            $freshType = 'neutral';
        }

        $requestedType = $change['requested_type'] ?? null;
        $requestedType = $requestedType === null
            ? null
            : RelationshipManager::canonicalizeRelationshipType($requestedType, $allowedCustomTypes);

        // ROMANTIC AUTO-PROMOTION GUARD (mirrors the main apply path): never let the concurrent-rebase apply a
        // romantic-leaning type on top of a non-romantic one. Romantic types are player-set, not model-assigned.
        $romanticTypes = ['romantic', 'crush', 'admirer', 'obsessed', 'infatuated', 'lover'];
        $freshIsRomantic = in_array($freshType, $romanticTypes, true);
        if (!empty($requestedType)) {
            // Never let a stale generic "neutral" response downgrade a newer UI
            // or worker type. Non-neutral model type changes can still apply.
            if (in_array($requestedType, $romanticTypes, true) && !$freshIsRomantic) {
                // blocked romantic auto-promotion: keep the existing (fresh) type
                $rebased['type'] = $freshType;
            } elseif ($requestedType !== 'neutral' || $freshType === 'neutral') {
                $rebased['type'] = $requestedType;
            } else {
                $rebased['type'] = $freshType;
            }
        } else {
            $rebased['type'] = $freshType;
            if ($freshType === 'neutral') {
                $inferredType = $this->inferTypeFromAffinity($rebasedAff);
                if ($inferredType !== 'neutral' && !in_array($inferredType, $romanticTypes, true)) {
                    $rebased['type'] = $inferredType;
                }
            }
        }

        $reason = trim((string)($change['reason'] ?? ''));
        if ($reason !== '') {
            if (abs($delta) >= 3 || empty($rebased['note'] ?? '')) {
                $rebased['note'] = $computedRel['note'] ?? $reason;
            }

            if ($delta >= 10) {
                $existingBestDelta = (int)($rebased['best_delta'] ?? 0);
                if ($delta >= $existingBestDelta) {
                    $rebased['best'] = $computedRel['best'] ?? $reason;
                    $rebased['best_delta'] = $delta;
                }
            }

            if ($delta <= -10) {
                $existingWorstDelta = (int)($rebased['worst_delta'] ?? 0);
                if ($delta <= $existingWorstDelta) {
                    $rebased['worst'] = $computedRel['worst'] ?? $reason;
                    $rebased['worst_delta'] = $delta;
                }
            }
        }

        $requestedRelation = $change['relation'] ?? null;
        if (is_string($requestedRelation)) {
            $requestedRelation = strtolower(trim($requestedRelation));
        }
        if (!empty($requestedRelation) && empty($rebased['relation'])) {
            $rebased['relation'] = $requestedRelation;
        }

        return $rebased;
    }

    /**
     * Get the player name
     */
    public function getPlayerName() {
        return $GLOBALS['PLAYER_NAME'] ?? 'the Player';
    }

    /**
     * Infer relationship type from affinity score
     * Auto-evolves "neutral" to appropriate type based on thresholds
     *
     * Only upgrades FROM neutral - doesn't downgrade other types
     * LLM can still set specific types (romantic, familial, etc.)
     *
     * Thresholds:
     * - +6 or higher → platonic (beyond neutral = some connection forming)
     * - -6 or lower → wary (beyond neutral = some distrust forming)
     * - -30 or lower → rival (active unfriendliness)
     * - -55 or lower → enemy (open hostility)
     */
    private function inferTypeFromAffinity($affinity) {
        // Positive thresholds - any positive relationship beyond neutral
        if ($affinity >= 6) {
            return 'platonic';  // Beyond neutral = connection forming
        }

        // Negative thresholds
        if ($affinity <= -55) {
            return 'enemy';     // Cold/Resentful tier = open hostility
        }
        if ($affinity <= -30) {
            return 'rival';     // Wary tier = active unfriendliness
        }
        if ($affinity <= -6) {
            return 'wary';      // Beyond neutral = distrust forming
        }

        // Still in neutral range (-5 to +5)
        return 'neutral';
    }
}
