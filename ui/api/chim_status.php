<?php
/**
 * CHIM Pipeline Status API
 * 
 * Returns real-time pipeline processing status for the Prisma Status HUD.
 * Includes STT, Player TTS, LLM, and TTS stage states, plus mode and connector info.
 */

error_reporting(E_ERROR);
session_start();

// Define base paths
define('BASE_PATH', dirname(dirname(__DIR__)));
define('LIB_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'lib');

// Load pipeline status helper
require_once(LIB_PATH . DIRECTORY_SEPARATOR . 'pipeline_status.php');

// Set JSON header
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Get pipeline status
    $status = pipeline_status_get();
    
    // Auto-clear stale pipeline states (>30 seconds old = likely stuck)
    $staleness = time() - ($status['updated'] ?? 0);
    if ($staleness > 30 && ($status['stt'] || $status['player_tts'] || $status['llm'] || $status['tts'])) {
        pipeline_status_clear();
        $status = pipeline_status_get();
    }
    
    // Always fetch from database for accurate real-time data
    require_once(LIB_PATH . DIRECTORY_SEPARATOR . 'logger.php');
    
    // Load configuration
    $configPath = BASE_PATH . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'conf.php';
    if (file_exists($configPath)) {
        require_once($configPath);
        
        if (isset($GLOBALS["DBDRIVER"])) {
            require_once(LIB_PATH . DIRECTORY_SEPARATOR . $GLOBALS["DBDRIVER"] . '.class.php');
            $db = new sql();
            $GLOBALS['db'] = $db;
            require_once(LIB_PATH . DIRECTORY_SEPARATOR . 'settings.php');
            
            // Get current mode
            $modeResult = $db->fetchOne("SELECT value FROM conf_opts WHERE id='chim_mode'");
            $status['mode'] = isset($modeResult['value']) ? strtoupper(trim($modeResult['value'])) : 'STANDARD';
            
            // Get Compact Chat setting
            $status['compact_chat'] = chimGetGeneralSettingBool('COMPACT_CHAT_ENABLED', true);
            
            // Get active profile slot
            $modelSlotResult = $db->fetchOne("SELECT value FROM conf_opts WHERE id='chim_profile_model'");
            $activeModelSlot = isset($modelSlotResult['value']) ? intval($modelSlotResult['value']) : 1;
            
            // Get profile and connector info
            $profile = $db->fetchOne("
                SELECT llm_primary_id, llm_secondary_id, llm_tertiary_id, llm_quaternary_id
                FROM core_profiles
                WHERE slot = {$activeModelSlot}
                LIMIT 1
            ");
            
            if ($profile) {
                // Map slot to the correct connector ID column
                $slotColumns = [
                    1 => 'llm_primary_id',
                    2 => 'llm_secondary_id', 
                    3 => 'llm_tertiary_id',
                    4 => 'llm_quaternary_id'
                ];
                $column = $slotColumns[$activeModelSlot] ?? 'llm_primary_id';
                $connectorId = $profile[$column];
                
                if ($connectorId) {
                    $connector = $db->fetchOne("
                        SELECT label, model
                        FROM core_llm_connector
                        WHERE id = " . intval($connectorId) . "
                        LIMIT 1
                    ");
                    
                    if ($connector) {
                        $status['connector_label'] = $connector['label'] ?? '';
                        $status['connector_model'] = $connector['model'] ?? '';
                    }
                }
            }
            
            // Store slot label
            $slotLabels = [1 => 'Standard', 2 => 'Fast', 3 => 'Powerful', 4 => 'Experimental'];
            $status['model_slot_label'] = $slotLabels[$activeModelSlot] ?? 'Standard';
        }
    }
    
    // Build response
    $response = [
        'success' => true,
        'data' => [
            'pipeline' => [
                'stt' => $status['stt'],
                'player_tts' => $status['player_tts'],
                'llm' => $status['llm'],
                'tts' => $status['tts']
            ],
            'mode' => $status['mode'] ?? 'STANDARD',
            'compact_chat' => $status['compact_chat'] ?? true,
            'model_slot_label' => $status['model_slot_label'] ?? 'Standard',
            'connector' => [
                'label' => $status['connector_label'] ?? '',
                'model' => $status['connector_model'] ?? ''
            ],
            'updated' => $status['updated']
        ],
        'timestamp' => time()
    ];
    
    echo json_encode($response);
    
} catch (Throwable $e) {
    error_log("CHIM Status API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'data' => [
            'pipeline' => [
                'stt' => false,
                'player_tts' => false,
                'llm' => false,
                'tts' => false
            ],
            'mode' => 'STANDARD',
            'connector' => [
                'label' => '',
                'model' => ''
            ],
            'updated' => time()
        ]
    ]);
}
