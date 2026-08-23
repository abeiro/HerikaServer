<?php
/**
 * RELATIONSHIP SYSTEM - Async Queue
 *
 * Implements deferred relationship evaluation to prevent blocking the main request.
 *
 * FLOW:
 * 1. postrequest.php queues evaluation data (non-blocking)
 * 2. context.php (next request) processes the queue before building context
 * 3. Relationship context is ready for the AI prompt
 *
 * This means evaluations are processed on the NEXT conversation turn,
 * but before context injection - so the AI always has current relationship data.
 *
 * Queue storage: Database table for persistence across requests
 */

// Ensure Logger is available
require_once $GLOBALS["ENGINE_PATH"] . "lib/logger.php";

/**
 * Convert malformed or unsuccessful LLM results into a retryable queue failure.
 */
function _relRequireLlmSuccess($result, $operation) {
    if (is_array($result) && !empty($result['ok'])) {
        return;
    }

    $error = is_array($result) && is_string($result['error'] ?? null)
        ? substr($result['error'], 0, 300)
        : 'invalid result';
    throw new RuntimeException("{$operation} failed: {$error}");
}

/**
 * Queue a relationship evaluation for async processing
 *
 * @param array $evalData Array containing:
 *   - npc_id: The NPC ID
 *   - npc_name: The NPC name
 *   - npc_response: What the NPC said
 *   - context: Additional context (events, player action, etc)
 *   - is_npc2npc: Whether this is NPC-to-NPC conversation
 *   - listener_npc_id: For NPC-to-NPC conversations
 *   - listener_name: For NPC-to-NPC conversations
 */
function _relQueueEvaluation($evalData) {
    if (!isset($GLOBALS['db']) || !$GLOBALS['db']) {
        Logger::warn("[REL-ASYNC] Cannot queue: no database connection");
        return false;
    }

    $npcId = $evalData['npc_id'] ?? 0;
    $npcName = $evalData['npc_name'] ?? 'Unknown';
    $listenerNpcId = $evalData['listener_npc_id'] ?? null;
    $listenerName = $evalData['listener_name'] ?? null;

    // Track whether this evaluation has player action (for smart upsert priority)
    $hasPlayerAction = !empty($evalData['has_player_action']);

    $queueData = [
        'npc_id' => $npcId,
        'npc_name' => $npcName,
        'dialogue' => $evalData['npc_response'] ?? '',
        'context' => $evalData['context'] ?? [],
        'is_npc2npc' => $evalData['is_npc2npc'] ?? false,
        'listener_npc_id' => $listenerNpcId,
        'listener_name' => $listenerName,
        'has_player_action' => $hasPlayerAction,
        'queued_at' => date('Y-m-d H:i:s')
    ];

    $jsonData = json_encode($queueData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Use a simple key-value approach in a cache table or create our own
    // For simplicity, use the existing infrastructure
    try {
        $escapedJson = $GLOBALS['db']->escape($jsonData);
        $escapedNpcId = intval($npcId);
        $hasPlayerActionSql = $hasPlayerAction ? 'true' : 'false';

        // SMART UPSERT: Player action evaluations have priority over NPC-initiated ones
        // - If new request has player action: ALWAYS replace (player is interacting)
        // - If existing request has player action: DON'T replace (preserve player interaction)
        // - If neither has player action: Replace with new data (latest NPC-initiated)
        // This prevents NPC chatter from overwriting pending Player relationship evaluations
        $GLOBALS['db']->query(
            "INSERT INTO relationship_eval_queue (npc_id, eval_data, created_at)
             VALUES ({$escapedNpcId}, '{$escapedJson}', NOW())
             ON CONFLICT (npc_id) DO UPDATE SET
                eval_data = CASE
                    WHEN {$hasPlayerActionSql} THEN '{$escapedJson}'
                    WHEN (relationship_eval_queue.eval_data->>'has_player_action')::boolean IS NOT TRUE THEN '{$escapedJson}'
                    ELSE relationship_eval_queue.eval_data
                END,
                created_at = CASE
                    WHEN {$hasPlayerActionSql} THEN NOW()
                    WHEN (relationship_eval_queue.eval_data->>'has_player_action')::boolean IS NOT TRUE THEN NOW()
                    ELSE relationship_eval_queue.created_at
                END"
        );

        Logger::info("[REL-ASYNC] Queued evaluation for {$npcName} (NPC {$npcId})" .
                  ($listenerNpcId ? " + NPC-to-NPC with {$listenerName}" : ""));
        return true;

    } catch (Exception $e) {
        // Table might not exist yet - try to create it
        if (strpos($e->getMessage(), 'relation "relationship_eval_queue" does not exist') !== false ||
            strpos($e->getMessage(), 'does not exist') !== false) {
            _relCreateQueueTable();
            // Retry once with same smart upsert logic
            try {
                $GLOBALS['db']->query(
                    "INSERT INTO relationship_eval_queue (npc_id, eval_data, created_at)
                     VALUES ({$escapedNpcId}, '{$escapedJson}', NOW())
                     ON CONFLICT (npc_id) DO UPDATE SET
                        eval_data = CASE
                            WHEN {$hasPlayerActionSql} THEN '{$escapedJson}'
                            WHEN (relationship_eval_queue.eval_data->>'has_player_action')::boolean IS NOT TRUE THEN '{$escapedJson}'
                            ELSE relationship_eval_queue.eval_data
                        END,
                        created_at = CASE
                            WHEN {$hasPlayerActionSql} THEN NOW()
                            WHEN (relationship_eval_queue.eval_data->>'has_player_action')::boolean IS NOT TRUE THEN NOW()
                            ELSE relationship_eval_queue.created_at
                        END"
                );
                return true;
            } catch (Exception $e2) {
                Logger::error("[REL-ASYNC] Failed to queue after table creation: " . $e2->getMessage());
                return false;
            }
        }
        Logger::error("[REL-ASYNC] Failed to queue: " . $e->getMessage());
        return false;
    }
}

/**
 * Process all pending evaluations in the queue
 * Called at the start of each request (from context.php)
 *
 * @param int $limit Max number of evaluations to process (default 5)
 * @return array Results of processing
 */
function _relProcessQueue($limit = 5, $relLLM = null) {
    if (!isset($GLOBALS['db']) || !$GLOBALS['db']) {
        return ['processed' => 0, 'error' => 'no database'];
    }

    $results = ['processed' => 0, 'errors' => [], 'retried' => 0, 'abandoned' => 0];

    try {
        // Get pending evaluations (oldest first, prioritize items with fewer retries)
        $rows = $GLOBALS['db']->fetchAll(
            "SELECT id, npc_id, eval_data, COALESCE(retry_count, 0) as retry_count
             FROM relationship_eval_queue
             ORDER BY COALESCE(retry_count, 0) ASC, created_at ASC
             LIMIT {$limit}"
        );

        if (empty($rows)) {
            return $results;
        }

        if ($relLLM === null) {
            require_once __DIR__ . "/relationship_llm.php";
            $relLLM = new RelationshipLLM();
        }

        if (!$relLLM->isAvailable()) {
            Logger::warn("[REL-ASYNC] LLM not available, skipping queue processing");
            return ['processed' => 0, 'error' => 'LLM not available'];
        }

        $successIds = [];      // Fully processed - delete
        $retryIds = [];        // Failed but will retry - increment retry_count
        $abandonIds = [];      // Exceeded max retries - delete with warning

        // Track which NPCs we've already lazy-initialized this session
        static $lazyInitChecked = [];

        foreach ($rows as $row) {
            $data = json_decode($row['eval_data'], true);
            $retryCount = intval($row['retry_count']);

            if (!$data) {
                $successIds[] = $row['id']; // Invalid data, just delete
                continue;
            }

            try {
                // Lazy init for speaker NPC if not already done
                if (!isset($lazyInitChecked[$data['npc_id']])) {
                    $initResult = $relLLM->analyzeNpc($data['npc_id'], false);
                    _relRequireLlmSuccess($initResult, 'Speaker relationship initialization');
                    if (!empty($initResult['ok']) && empty($initResult['skipped'])) {
                        Logger::info("[REL-ASYNC] Lazy-initialized {$data['npc_name']}");
                    }
                    $lazyInitChecked[$data['npc_id']] = true;
                }

                // Lazy init for listener NPC if applicable
                if (!empty($data['listener_npc_id']) && !isset($lazyInitChecked[$data['listener_npc_id']])) {
                    $initResult = $relLLM->analyzeNpc($data['listener_npc_id'], false);
                    _relRequireLlmSuccess($initResult, 'Listener relationship initialization');
                    if (!empty($initResult['ok']) && empty($initResult['skipped'])) {
                        Logger::info("[REL-ASYNC] Lazy-initialized {$data['listener_name']}");
                    }
                    $lazyInitChecked[$data['listener_npc_id']] = true;
                }

                // Check if this is NPC-to-NPC conversation
                $isNpcToNpc = !empty($data['is_npc2npc']) || !empty($data['listener_npc_id']);

                // Check if Player was involved in this conversation
                // Player is involved if:
                // 1. Player explicitly said/did something (player_action set), OR
                // 2. NPC was talking TO the Player (not NPC-to-NPC)
                // This ensures NPCs form opinions about the Player even for NPC-initiated greetings
                $playerInvolved = !empty($data['context']['player_action']) || !$isNpcToNpc;

                // Only evaluate NPC->Player if Player was involved in the conversation
                if ($playerInvolved && !$isNpcToNpc) {
                    $evalResult = $relLLM->evaluateContext(
                        $data['npc_id'],
                        $data['dialogue'],
                        $data['context']
                    );
                    _relRequireLlmSuccess($evalResult, 'Relationship evaluation');

                    if ($evalResult['ok'] && !empty($evalResult['changes'])) {
                        Logger::info("[REL-ASYNC] Processed {$data['npc_name']}: " .
                                  count($evalResult['changes']) . " changes");
                    }
                } else if ($isNpcToNpc) {
                    Logger::debug("[REL-ASYNC] Skipping Player eval for NPC-to-NPC: {$data['npc_name']} -> {$data['listener_name']}");
                }

                // NPC-to-NPC evaluation
                if ($isNpcToNpc) {
                    $npcToNpcResult = $relLLM->evaluateNpcToNpcContext(
                        $data['npc_id'],
                        $data['listener_npc_id'],
                        $data['dialogue'],
                        $data['context']
                    );
                    _relRequireLlmSuccess($npcToNpcResult, 'NPC-to-NPC relationship evaluation');

                    if ($npcToNpcResult['ok']) {
                        $changes = count($npcToNpcResult['speaker']['changes'] ?? []) +
                                   count($npcToNpcResult['listener']['changes'] ?? []);
                        if ($changes > 0) {
                            Logger::info("[REL-ASYNC] NPC-to-NPC {$data['npc_name']} <-> {$data['listener_name']}: {$changes} changes");
                        }
                    }
                }

                // Success! Delete from queue
                $successIds[] = $row['id'];
                $results['processed']++;

            } catch (Throwable $e) {
                $errorMsg = $e->getMessage();
                $errorClass = get_class($e);
                $results['errors'][] = "NPC {$data['npc_id']}: {$errorClass}: " . $errorMsg;

                // RETRY LOGIC: Don't delete on first error!
                // Only delete after REL_QUEUE_MAX_RETRIES attempts
                $maxRetries = defined('REL_QUEUE_MAX_RETRIES') ? REL_QUEUE_MAX_RETRIES : 3;

                if ($retryCount >= $maxRetries) {
                    // Exceeded max retries - log critical event and abandon
                    Logger::error("[REL-ASYNC] ABANDONED after {$retryCount} retries: NPC {$data['npc_name']} - {$errorClass}: {$errorMsg}");
                    $abandonIds[] = $row['id'];
                    $results['abandoned']++;
                } else {
                    // Increment retry count for next attempt
                    $retryIds[] = ['id' => $row['id'], 'error' => substr("{$errorClass}: {$errorMsg}", 0, 500)];
                    $results['retried']++;
                    Logger::warn("[REL-ASYNC] Retry {$retryCount}/" . $maxRetries . " for NPC {$data['npc_name']}: {$errorClass}: {$errorMsg}");
                }
            }
        }

        // Delete successfully processed entries
        if (!empty($successIds)) {
            $idList = implode(',', array_map('intval', $successIds));
            $GLOBALS['db']->query("DELETE FROM relationship_eval_queue WHERE id IN ({$idList})");
        }

        // Delete abandoned entries (exceeded max retries)
        if (!empty($abandonIds)) {
            $idList = implode(',', array_map('intval', $abandonIds));
            $GLOBALS['db']->query("DELETE FROM relationship_eval_queue WHERE id IN ({$idList})");
        }

        // Increment retry count for failed entries (will try again later)
        if (!empty($retryIds)) {
            _relEnsureRetryColumns('relationship_eval_queue');
        }
        foreach ($retryIds as $retry) {
            $id = intval($retry['id']);
            $escapedError = $GLOBALS['db']->escape($retry['error']);
            $GLOBALS['db']->query(
                "UPDATE relationship_eval_queue
                 SET retry_count = COALESCE(retry_count, 0) + 1,
                     last_error = '{$escapedError}'
                 WHERE id = {$id}"
            );
        }

    } catch (Throwable $e) {
        // Table might not exist - that's fine, nothing to process
        if (strpos($e->getMessage(), 'does not exist') === false) {
            Logger::error("[REL-ASYNC] Queue processing error: " . $e->getMessage());
        }
    }

    return $results;
}

/**
 * Ensure retry_count and last_error exist before the retry UPDATE writes to them.
 *
 * The ALTERs in _relCreateQueueTable() only run when the table is missing, so an install
 * whose table predates last_error never gets the column. Idempotent, memoised per process,
 * and only called when there is something to retry.
 */
function _relEnsureRetryColumns($table) {
    static $ensured = [];
    if (isset($ensured[$table])) {
        return;
    }
    $ensured[$table] = true;
    try {
        $GLOBALS['db']->query("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS retry_count INTEGER DEFAULT 0");
        $GLOBALS['db']->query("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS last_error TEXT");
    } catch (Throwable $e) {
        Logger::warn("[REL-ASYNC] Could not ensure retry columns on {$table}: " . $e->getMessage());
    }
}

/**
 * Create the queue table if it doesn't exist
 * Includes retry_count for retry logic (prevents Alzheimer's bug)
 */
function _relCreateQueueTable() {
    try {
        $GLOBALS['db']->query("
            CREATE TABLE IF NOT EXISTS relationship_eval_queue (
                id SERIAL PRIMARY KEY,
                npc_id INTEGER NOT NULL UNIQUE,
                eval_data JSONB NOT NULL,
                created_at TIMESTAMP DEFAULT NOW(),
                retry_count INTEGER DEFAULT 0,
                last_error TEXT
            )
        ");
        Logger::info("[REL-ASYNC] Created relationship_eval_queue table");

        // Add columns if table exists but is missing them (migration)
        $GLOBALS['db']->query("
            ALTER TABLE relationship_eval_queue
            ADD COLUMN IF NOT EXISTS retry_count INTEGER DEFAULT 0
        ");
        $GLOBALS['db']->query("
            ALTER TABLE relationship_eval_queue
            ADD COLUMN IF NOT EXISTS last_error TEXT
        ");
    } catch (Exception $e) {
        Logger::error("[REL-ASYNC] Failed to create queue table: " . $e->getMessage());
    }
}

// Maximum retries before deleting a failed queue item
define('REL_QUEUE_MAX_RETRIES', 3);

/**
 * Get queue status (for debugging)
 */
function _relGetQueueStatus() {
    try {
        $count = $GLOBALS['db']->fetchOne(
            "SELECT COUNT(*) as cnt FROM relationship_eval_queue"
        );
        return ['pending' => intval($count['cnt'] ?? 0)];
    } catch (Exception $e) {
        return ['pending' => 0, 'error' => $e->getMessage()];
    }
}

/**
 * Queue an NPC for relationship initialization (TEXT->JSONB parsing)
 * Called from addnpc handler to avoid blocking map load
 *
 * @param int $npcId The NPC ID
 * @param string $npcName The NPC name
 */
function _relQueueNpcInit($npcId, $npcName) {
    if (!isset($GLOBALS['db']) || !$GLOBALS['db']) {
        return false;
    }

    $queueData = [
        'npc_id' => $npcId,
        'npc_name' => $npcName,
        'type' => 'init',  // Mark as init-only (no conversation to evaluate)
        'queued_at' => date('Y-m-d H:i:s')
    ];

    $jsonData = json_encode($queueData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    try {
        $escapedJson = $GLOBALS['db']->escape($jsonData);
        $escapedNpcId = intval($npcId);

        // Use relationship_init_queue for init requests (separate from eval queue)
        $GLOBALS['db']->query(
            "INSERT INTO relationship_init_queue (npc_id, init_data, created_at)
             VALUES ({$escapedNpcId}, '{$escapedJson}', NOW())
             ON CONFLICT (npc_id) DO NOTHING"  // Don't replace - first request wins
        );

        return true;

    } catch (Exception $e) {
        // Table might not exist - create it
        if (strpos($e->getMessage(), 'does not exist') !== false) {
            _relCreateInitQueueTable();
            try {
                $GLOBALS['db']->query(
                    "INSERT INTO relationship_init_queue (npc_id, init_data, created_at)
                     VALUES ({$escapedNpcId}, '{$escapedJson}', NOW())
                     ON CONFLICT (npc_id) DO NOTHING"
                );
                return true;
            } catch (Exception $e2) {
                return false;
            }
        }
        return false;
    }
}

/**
 * Process pending NPC inits from queue
 * Called at the start of each request (from context.php)
 *
 * @param int $limit Max number to process per request
 */
function _relProcessInitQueue($limit = 5, $relLLM = null) {
    if (!isset($GLOBALS['db']) || !$GLOBALS['db']) {
        return ['processed' => 0];
    }

    $results = ['processed' => 0, 'retried' => 0, 'abandoned' => 0];

    try {
        $rows = $GLOBALS['db']->fetchAll(
            "SELECT id, npc_id, init_data, COALESCE(retry_count, 0) as retry_count
             FROM relationship_init_queue
             ORDER BY COALESCE(retry_count, 0) ASC, created_at ASC
             LIMIT {$limit}"
        );

        if (empty($rows)) {
            return $results;
        }

        if ($relLLM === null) {
            require_once __DIR__ . "/relationship_llm.php";
            $relLLM = new RelationshipLLM();
        }

        if (!$relLLM->isAvailable()) {
            return ['processed' => 0, 'error' => 'LLM not available'];
        }

        $successIds = [];
        $retryIds = [];
        $abandonIds = [];

        foreach ($rows as $row) {
            $data = json_decode($row['init_data'], true);
            $retryCount = intval($row['retry_count']);

            if (!$data) {
                $successIds[] = $row['id'];
                continue;
            }

            try {
                // Analyze available relationship source data into JSONB.
                $initResult = $relLLM->analyzeNpc($data['npc_id'], false);
                _relRequireLlmSuccess($initResult, 'Queued relationship initialization');
                if (!empty($initResult['ok']) && empty($initResult['skipped'])) {
                    Logger::info("[REL-ASYNC] Initialized relationships for {$data['npc_name']}");
                }
                $successIds[] = $row['id'];
                $results['processed']++;
            } catch (Throwable $e) {
                $errorMsg = $e->getMessage();
                $errorClass = get_class($e);
                $maxRetries = defined('REL_QUEUE_MAX_RETRIES') ? REL_QUEUE_MAX_RETRIES : 3;

                if ($retryCount >= $maxRetries) {
                    Logger::error("[REL-ASYNC] ABANDONED init after {$retryCount} retries: {$data['npc_name']} - {$errorClass}: {$errorMsg}");
                    $abandonIds[] = $row['id'];
                    $results['abandoned']++;
                } else {
                    $retryIds[] = ['id' => $row['id'], 'error' => substr("{$errorClass}: {$errorMsg}", 0, 500)];
                    $results['retried']++;
                    Logger::warn("[REL-ASYNC] Init retry {$retryCount}/" . $maxRetries . " for {$data['npc_name']}: {$errorClass}: {$errorMsg}");
                }
            }
        }

        if (!empty($successIds)) {
            $idList = implode(',', array_map('intval', $successIds));
            $GLOBALS['db']->query("DELETE FROM relationship_init_queue WHERE id IN ({$idList})");
        }

        if (!empty($abandonIds)) {
            $idList = implode(',', array_map('intval', $abandonIds));
            $GLOBALS['db']->query("DELETE FROM relationship_init_queue WHERE id IN ({$idList})");
        }

        if (!empty($retryIds)) {
            _relEnsureRetryColumns('relationship_init_queue');
        }
        foreach ($retryIds as $retry) {
            $id = intval($retry['id']);
            $escapedError = $GLOBALS['db']->escape($retry['error']);
            $GLOBALS['db']->query(
                "UPDATE relationship_init_queue
                 SET retry_count = COALESCE(retry_count, 0) + 1,
                     last_error = '{$escapedError}'
                 WHERE id = {$id}"
            );
        }

    } catch (Throwable $e) {
        // Table might not exist - that's fine
        if (strpos($e->getMessage(), 'does not exist') === false) {
            Logger::error("[REL-ASYNC] Init queue error: " . $e->getMessage());
        }
    }

    return $results;
}

/**
 * Create the init queue table
 * Includes retry_count for retry logic (prevents Alzheimer's bug)
 */
function _relCreateInitQueueTable() {
    try {
        $GLOBALS['db']->query("
            CREATE TABLE IF NOT EXISTS relationship_init_queue (
                id SERIAL PRIMARY KEY,
                npc_id INTEGER NOT NULL UNIQUE,
                init_data JSONB NOT NULL,
                created_at TIMESTAMP DEFAULT NOW(),
                retry_count INTEGER DEFAULT 0,
                last_error TEXT
            )
        ");

        // Add columns if table exists but is missing them (migration)
        $GLOBALS['db']->query("
            ALTER TABLE relationship_init_queue
            ADD COLUMN IF NOT EXISTS retry_count INTEGER DEFAULT 0
        ");
        $GLOBALS['db']->query("
            ALTER TABLE relationship_init_queue
            ADD COLUMN IF NOT EXISTS last_error TEXT
        ");
    } catch (Exception $e) {
        Logger::error("[REL-ASYNC] Failed to create init queue table: " . $e->getMessage());
    }
}
