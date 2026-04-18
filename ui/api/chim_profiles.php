<?php
/**
 * CHIM Profiles API
 * Returns profile information with LLM connector details
 */

error_reporting(E_ERROR);
session_start();

define('BASE_PATH', dirname(dirname(__DIR__)));
define('CONFIG_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'conf');
define('LIB_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'lib');

$configFilepath = CONFIG_PATH . DIRECTORY_SEPARATOR;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!file_exists($configFilepath . "conf.php")) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Configuration file not found',
        'profile_slots' => []
    ]);
    exit;
}

require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR . "profile_loader.php");
require_once(LIB_PATH . DIRECTORY_SEPARATOR . "logger.php");
require_once(LIB_PATH . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php");

try {
    $db = new sql();
    
    // Get profile slots and their connectors (same structure as chim_overlay.php)
    $profileSlots = [];
    for ($slot = 1; $slot <= 4; $slot++) {
        $profile = $db->fetchOne("
            SELECT 
                p.id,
                p.label,
                p.slot,
                p.llm_primary_id,
                p.llm_secondary_id,
                p.llm_tertiary_id,
                p.llm_quaternary_id
            FROM core_profiles p
            WHERE p.slot = $slot
            LIMIT 1
        ");
        
        if ($profile) {
            // Get connector labels for each slot
            $slotNames = ['Standard LLM', 'Fast LLM', 'Powerful LLM', 'Experimental LLM'];
            $connectors = [];
            
            $connectorIds = [
                $profile['llm_primary_id'],
                $profile['llm_secondary_id'],
                $profile['llm_tertiary_id'],
                $profile['llm_quaternary_id']
            ];
            
            foreach ($connectorIds as $idx => $connectorId) {
                if ($connectorId) {
                    $connector = $db->fetchOne("
                        SELECT label, model, driver 
                        FROM core_llm_connector 
                        WHERE id = " . intval($connectorId) . "
                        LIMIT 1
                    ");
                    
                    if ($connector) {
                        $connectors[$slotNames[$idx]] = [
                            'label' => $connector['label'],
                            'model' => $connector['model'],
                            'driver' => $connector['driver']
                        ];
                    }
                }
            }
            
            $profileSlots[$slot] = [
                'profile_id' => intval($profile['id']),
                'profile_name' => $profile['label'],
                'connectors' => $connectors
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'profile_slots' => $profileSlots,
        'timestamp' => time()
    ]);
    
} catch (Throwable $e) {
    error_log("CHIM Profiles API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'profile_slots' => []
    ]);
}
