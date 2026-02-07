<?php
/**
 * CHIM Profiles API
 * Returns profile information with LLM connector details
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once(__DIR__ . "/../../conf/conf.php");
require_once(__DIR__ . "/../../lib/db/{$GLOBALS["DBDRIVER"]}.class.php");

try {
    $db = new Sql();
    
    // Get all profiles with slots 1-4
    $profiles = [];
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
                        $connectors[] = [
                            'slot_name' => $slotNames[$idx],
                            'label' => $connector['label'],
                            'model' => $connector['model'],
                            'driver' => $connector['driver']
                        ];
                    }
                }
            }
            
            $profiles[] = [
                'slot' => $slot,
                'profile_name' => $profile['label'],
                'connectors' => $connectors
            ];
        } else {
            // Slot not configured
            $profiles[] = [
                'slot' => $slot,
                'profile_name' => 'Unconfigured',
                'connectors' => []
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'profiles' => $profiles,
        'timestamp' => time()
    ]);
    
} catch (Throwable $e) {
    error_log("CHIM Profiles API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'profiles' => []
    ]);
}
