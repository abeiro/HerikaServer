<?php
/**
 * RELATIONSHIP SYSTEM - Batch Analysis Endpoint
 *
 * Processes multiple NPCs' relationships at once using the RelationshipLLM.
 * Can be called from the UI or CLI.
 *
 * GET/POST parameters:
 *   - limit: Max NPCs to process (default 50)
 *   - force: Set to 1 to re-analyze NPCs that already have relationships
 *   - infer: Set to 1 to also run transitive inference after analysis
 *
 * Usage from CLI:
 *   php ext/relationship_system/batch_analyze.php limit=100
 *
 * Usage from web:
 *   GET /ext/relationship_system/batch_analyze.php?limit=50&force=0
 */

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

// Handle CLI vs web
$isCli = php_sapi_name() === 'cli';

if ($isCli) {
    // Parse CLI arguments: script.php limit=50 force=1
    $params = [];
    foreach ($argv as $arg) {
        if (strpos($arg, '=') !== false) {
            list($key, $val) = explode('=', $arg, 2);
            $params[$key] = $val;
        }
    }
    $limit = intval($params['limit'] ?? 50);
    $force = !empty($params['force']);
    $infer = !empty($params['infer']);
} else {
    header('Content-Type: application/json');
    $limit = intval($_REQUEST['limit'] ?? 50);
    $force = !empty($_REQUEST['force']);
    $infer = !empty($_REQUEST['infer']);
}

// Check if RelationshipLLM is configured
if (empty($GLOBALS['RELLLM_CONNECTOR'])) {
    $msg = "RELLLM_CONNECTOR not set in conf.php. Cannot run batch analysis.";
    if ($isCli) {
        echo "ERROR: $msg\n";
        exit(1);
    } else {
        echo json_encode(['ok' => false, 'error' => $msg]);
        exit;
    }
}

require_once __DIR__ . "/relationship_llm.php";

$relLLM = new RelationshipLLM();

if (!$relLLM->isAvailable()) {
    $msg = "RelationshipLLM not available. Check connector configuration.";
    if ($isCli) {
        echo "ERROR: $msg\n";
        exit(1);
    } else {
        echo json_encode(['ok' => false, 'error' => $msg]);
        exit;
    }
}

if ($isCli) {
    echo "=== BATCH RELATIONSHIP ANALYSIS ===\n";
    echo "Limit: $limit | Force: " . ($force ? 'Yes' : 'No') . " | Infer: " . ($infer ? 'Yes' : 'No') . "\n\n";
}

// Run batch analysis
$result = $relLLM->batchAnalyze($limit, $force);

if ($isCli) {
    echo "Processed: {$result['processed']}\n";
    echo "Skipped:   {$result['skipped']}\n";
    echo "Errors:    {$result['errors']}\n";
    echo "\nDetails:\n";
    foreach ($result['details'] as $detail) {
        if (isset($detail['error'])) {
            echo "  ERROR: {$detail['npc']} - {$detail['error']}\n";
        } else {
            echo "  OK: {$detail['npc']} - {$detail['count']} relationships\n";
        }
    }
}

// Run inference if requested
if ($infer && $result['processed'] > 0) {
    if ($isCli) {
        echo "\n=== RUNNING TRANSITIVE INFERENCE ===\n";
    }

    $inferResults = [];

    // Get all NPCs with relationships
    $npcs = $GLOBALS["db"]->fetchAll(
        "SELECT id, npc_name FROM core_npc_master WHERE extended_data::text LIKE '%relationships%' LIMIT " . intval($limit)
    );

    foreach ($npcs as $npc) {
        $inferResult = $relLLM->inferTransitiveRelationships($npc['id']);
        if ($inferResult['ok'] && $inferResult['inferred'] > 0) {
            $inferResults[] = [
                'npc' => $npc['npc_name'],
                'inferred' => $inferResult['inferred']
            ];
            if ($isCli) {
                echo "  {$npc['npc_name']}: +{$inferResult['inferred']} inferred\n";
            }
        }
    }

    $result['inference'] = $inferResults;
}

if ($isCli) {
    echo "\n=== COMPLETE ===\n";
} else {
    $result['ok'] = true;
    echo json_encode($result, JSON_PRETTY_PRINT);
}
