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

/**
 * Queue a relationship evaluation for async processing
 *
 * @param int $npcId The NPC ID
 * @param string $npcName The NPC name
 * @param string $dialogue What was said
 * @param array $context Additional context (events, player action, etc)
 * @param int|null $listenerNpcId For NPC-to-NPC conversations
 * @param string|null $listenerName For NPC-to-NPC conversations
 */
function _relQueueEvaluation($npcId, $npcName, $dialogue, $context = [], $listenerNpcId = null, $listenerName = null) {
    if (!isset($GLOBALS['db']) || !$GLOBALS['db']) {
        error_log("[REL-ASYNC] Cannot queue: no database connection");
        return false;
    }

    $queueData = [
        'npc_id' => $npcId,
        'npc_name' => $npcName,
        'dialogue' => $dialogue,
        'context' => $context,
        'listener_npc_id' => $listenerNpcId,
        'listener_name' => $listenerName,
        'queued_at' => date('Y-m-d H:i:s')
    ];

    $jsonData = json_encode($queueData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Use a simple key-value approach in a cache table or create our own
    // For simplicity, use the existing infrastructure
    try {
        $escapedJson = $GLOBALS['db']->escape($jsonData);
        $escapedNpcId = intval($npcId);

        // Upsert: Replace any existing queue for this NPC (only latest matters)
        $GLOBALS['db']->query(
            "INSERT INTO relationship_eval_queue (npc_id, eval_data, created_at)
             VALUES ({$escapedNpcId}, '{$escapedJson}', NOW())
             ON CONFLICT (npc_id) DO UPDATE SET eval_data = '{$escapedJson}', created_at = NOW()"
        );

        error_log("[REL-ASYNC] Queued evaluation for {$npcName} (NPC {$npcId})" .
                  ($listenerNpcId ? " + NPC-to-NPC with {$listenerName}" : ""));
        return true;

    } catch (Exception $e) {
        // Table might not exist yet - try to create it
        if (strpos($e->getMessage(), 'relation "relationship_eval_queue" does not exist') !== false ||
            strpos($e->getMessage(), 'does not exist') !== false) {
            _relCreateQueueTable();
            // Retry once
            try {
                $GLOBALS['db']->query(
                    "INSERT INTO relationship_eval_queue (npc_id, eval_data, created_at)
                     VALUES ({$escapedNpcId}, '{$escapedJson}', NOW())
                     ON CONFLICT (npc_id) DO UPDATE SET eval_data = '{$escapedJson}', created_at = NOW()"
                );
                return true;
            } catch (Exception $e2) {
                error_log("[REL-ASYNC] Failed to queue after table creation: " . $e2->getMessage());
                return false;
            }
        }
        error_log("[REL-ASYNC] Failed to queue: " . $e->getMessage());
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
function _relProcessQueue($limit = 5) {
    if (!isset($GLOBALS['db']) || !$GLOBALS['db']) {
        return ['processed' => 0, 'error' => 'no database'];
    }

    $results = ['processed' => 0, 'errors' => []];

    try {
        // Get pending evaluations (oldest first)
        $rows = $GLOBALS['db']->fetchAll(
            "SELECT id, npc_id, eval_data FROM relationship_eval_queue
             ORDER BY created_at ASC LIMIT {$limit}"
        );

        if (empty($rows)) {
            return $results;
        }

        require_once __DIR__ . "/relationship_llm.php";
        $relLLM = new RelationshipLLM();

        if (!$relLLM->isAvailable()) {
            error_log("[REL-ASYNC] LLM not available, skipping queue processing");
            return ['processed' => 0, 'error' => 'LLM not available'];
        }

        $processedIds = [];

        // Track which NPCs we've already lazy-initialized this session
        static $lazyInitChecked = [];

        foreach ($rows as $row) {
            $data = json_decode($row['eval_data'], true);
            if (!$data) {
                $processedIds[] = $row['id'];
                continue;
            }

            try {
                // Lazy init for speaker NPC if not already done
                if (!isset($lazyInitChecked[$data['npc_id']])) {
                    $initResult = $relLLM->analyzeNpc($data['npc_id'], false);
                    if (!empty($initResult['ok']) && empty($initResult['skipped'])) {
                        error_log("[REL-ASYNC] Lazy-initialized {$data['npc_name']}");
                    }
                    $lazyInitChecked[$data['npc_id']] = true;
                }

                // Lazy init for listener NPC if applicable
                if (!empty($data['listener_npc_id']) && !isset($lazyInitChecked[$data['listener_npc_id']])) {
                    $initResult = $relLLM->analyzeNpc($data['listener_npc_id'], false);
                    if (!empty($initResult['ok']) && empty($initResult['skipped'])) {
                        error_log("[REL-ASYNC] Lazy-initialized {$data['listener_name']}");
                    }
                    $lazyInitChecked[$data['listener_npc_id']] = true;
                }

                // Check if this is NPC-to-NPC conversation
                $isNpcToNpc = !empty($data['listener_npc_id']);

                // Check if Player actually did something in this context
                // If there's no player_action, the Player wasn't involved - skip Player eval
                $playerActed = !empty($data['context']['player_action']);

                // Only evaluate NPC->Player if:
                // 1. This is NOT an NPC-to-NPC conversation, AND
                // 2. The Player actually said/did something
                if (!$isNpcToNpc && $playerActed) {
                    $evalResult = $relLLM->evaluateContext(
                        $data['npc_id'],
                        $data['dialogue'],
                        $data['context']
                    );

                    if ($evalResult['ok'] && !empty($evalResult['changes'])) {
                        error_log("[REL-ASYNC] Processed {$data['npc_name']}: " .
                                  count($evalResult['changes']) . " changes");
                    }
                } else if ($isNpcToNpc) {
                    error_log("[REL-ASYNC] Skipping Player eval for NPC-to-NPC: {$data['npc_name']} -> {$data['listener_name']}");
                } else if (!$playerActed) {
                    error_log("[REL-ASYNC] Skipping Player eval - no player action: {$data['npc_name']}");
                }

                // NPC-to-NPC evaluation
                if ($isNpcToNpc) {
                    $npcToNpcResult = $relLLM->evaluateNpcToNpcContext(
                        $data['npc_id'],
                        $data['listener_npc_id'],
                        $data['dialogue'],
                        $data['context']
                    );

                    if ($npcToNpcResult['ok']) {
                        $changes = count($npcToNpcResult['speaker']['changes'] ?? []) +
                                   count($npcToNpcResult['listener']['changes'] ?? []);
                        if ($changes > 0) {
                            error_log("[REL-ASYNC] NPC-to-NPC {$data['npc_name']} <-> {$data['listener_name']}: {$changes} changes");
                        }
                    }
                }

                $processedIds[] = $row['id'];
                $results['processed']++;

            } catch (Exception $e) {
                $results['errors'][] = "NPC {$data['npc_id']}: " . $e->getMessage();
                // Still mark as processed to avoid infinite retry
                $processedIds[] = $row['id'];
            }
        }

        // Delete processed entries
        if (!empty($processedIds)) {
            $idList = implode(',', array_map('intval', $processedIds));
            $GLOBALS['db']->query("DELETE FROM relationship_eval_queue WHERE id IN ({$idList})");
        }

    } catch (Exception $e) {
        // Table might not exist - that's fine, nothing to process
        if (strpos($e->getMessage(), 'does not exist') === false) {
            error_log("[REL-ASYNC] Queue processing error: " . $e->getMessage());
        }
    }

    return $results;
}

/**
 * Create the queue table if it doesn't exist
 */
function _relCreateQueueTable() {
    try {
        $GLOBALS['db']->query("
            CREATE TABLE IF NOT EXISTS relationship_eval_queue (
                id SERIAL PRIMARY KEY,
                npc_id INTEGER NOT NULL UNIQUE,
                eval_data JSONB NOT NULL,
                created_at TIMESTAMP DEFAULT NOW()
            )
        ");
        error_log("[REL-ASYNC] Created relationship_eval_queue table");
    } catch (Exception $e) {
        error_log("[REL-ASYNC] Failed to create queue table: " . $e->getMessage());
    }
}

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
function _relProcessInitQueue($limit = 5) {
    if (!isset($GLOBALS['db']) || !$GLOBALS['db']) {
        return ['processed' => 0];
    }

    $results = ['processed' => 0];

    try {
        $rows = $GLOBALS['db']->fetchAll(
            "SELECT id, npc_id, init_data FROM relationship_init_queue
             ORDER BY created_at ASC LIMIT {$limit}"
        );

        if (empty($rows)) {
            return $results;
        }

        require_once __DIR__ . "/relationship_llm.php";
        $relLLM = new RelationshipLLM();

        if (!$relLLM->isAvailable()) {
            return ['processed' => 0, 'error' => 'LLM not available'];
        }

        $processedIds = [];

        foreach ($rows as $row) {
            $data = json_decode($row['init_data'], true);
            if (!$data) {
                $processedIds[] = $row['id'];
                continue;
            }

            try {
                // Parse TEXT relationships to JSONB
                $initResult = $relLLM->analyzeNpc($data['npc_id'], false);
                if (!empty($initResult['ok']) && empty($initResult['skipped'])) {
                    error_log("[REL-ASYNC] Initialized relationships for {$data['npc_name']}");
                }
                $processedIds[] = $row['id'];
                $results['processed']++;
            } catch (Exception $e) {
                // Still mark as processed to avoid infinite retry
                $processedIds[] = $row['id'];
            }
        }

        if (!empty($processedIds)) {
            $idList = implode(',', array_map('intval', $processedIds));
            $GLOBALS['db']->query("DELETE FROM relationship_init_queue WHERE id IN ({$idList})");
        }

    } catch (Exception $e) {
        // Table might not exist - that's fine
        if (strpos($e->getMessage(), 'does not exist') === false) {
            error_log("[REL-ASYNC] Init queue error: " . $e->getMessage());
        }
    }

    return $results;
}

/**
 * Create the init queue table
 */
function _relCreateInitQueueTable() {
    try {
        $GLOBALS['db']->query("
            CREATE TABLE IF NOT EXISTS relationship_init_queue (
                id SERIAL PRIMARY KEY,
                npc_id INTEGER NOT NULL UNIQUE,
                init_data JSONB NOT NULL,
                created_at TIMESTAMP DEFAULT NOW()
            )
        ");
    } catch (Exception $e) {
        error_log("[REL-ASYNC] Failed to create init queue table: " . $e->getMessage());
    }
}
