<?php
/**
 * CHIM AI View API
 * Returns comprehensive NPC profile data for the AI View HUD overlay
 * 
 * Params:
 *   - npc_name: NPC name (optional if refid provided)
 *   - refid: NPC reference ID (optional if npc_name provided)
 */

error_reporting(E_ERROR);
session_start();

// Define base paths
define('BASE_PATH', dirname(dirname(__DIR__)));
define('CONFIG_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'conf');
define('LIB_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'lib');

$configFilepath = CONFIG_PATH . DIRECTORY_SEPARATOR;

if (!file_exists($configFilepath."conf.php")) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Configuration file not found']);
    exit;
}

// Load profiles through the centralized profile loader
require_once(dirname(__DIR__).DIRECTORY_SEPARATOR."profile_loader.php");

require_once(LIB_PATH .DIRECTORY_SEPARATOR."logger.php");
require_once(LIB_PATH .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once(LIB_PATH .DIRECTORY_SEPARATOR."core".DIRECTORY_SEPARATOR."activity_status.php");

$db = new sql();

header('Content-Type: application/json');

function chimNormalizeRelationshipAffinities($rawRelationships) {
    if (!is_array($rawRelationships)) {
        return [];
    }

    $normalized = [];

    foreach ($rawRelationships as $key => $value) {
        $name = '';
        $affinity = 0;
        $description = '';

        if (is_array($value)) {
            $name = trim((string)($value['name'] ?? $value['npc_name'] ?? $value['npc'] ?? $key));
            $affinityRaw = $value['affinity'] ?? $value['aff'] ?? $value['value'] ?? $value['score'] ?? $value['rank'] ?? 0;
            $affinity = is_numeric($affinityRaw) ? intval($affinityRaw) : 0;
            $description = trim((string)($value['description'] ?? $value['summary'] ?? ''));
            if ($description === '') {
                $descriptionParts = [];
                foreach (['relation', 'type', 'note', 'best', 'worst'] as $detailKey) {
                    if (!empty($value[$detailKey])) {
                        $detailLabel = ucfirst($detailKey);
                        $descriptionParts[] = "{$detailLabel}: " . trim((string)$value[$detailKey]);
                    }
                }
                $description = implode(' | ', $descriptionParts);
            }
        } else {
            $name = trim((string)$key);
            $affinity = is_numeric($value) ? intval($value) : 0;
        }

        if ($name === '') {
            continue;
        }

        $normalized[] = [
            'name' => $name,
            'affinity' => $affinity,
            'description' => $description
        ];
    }

    usort($normalized, function ($left, $right) {
        $affinityDelta = abs($right['affinity']) <=> abs($left['affinity']);
        if ($affinityDelta !== 0) {
            return $affinityDelta;
        }

        return strcasecmp($left['name'], $right['name']);
    });

    return $normalized;
}

function chimBuildRelationshipAffinitySummary(array $affinities) {
    if (empty($affinities)) {
        return '';
    }

    $count = count($affinities);
    $summaryParts = ["Tracked affinities with {$count} NPC" . ($count === 1 ? '' : 's') . "."];

    $strongestPositive = null;
    $strongestNegative = null;

    foreach ($affinities as $affinity) {
        if ($affinity['affinity'] > 0 && ($strongestPositive === null || $affinity['affinity'] > $strongestPositive['affinity'])) {
            $strongestPositive = $affinity;
        }

        if ($affinity['affinity'] < 0 && ($strongestNegative === null || $affinity['affinity'] < $strongestNegative['affinity'])) {
            $strongestNegative = $affinity;
        }
    }

    if ($strongestPositive !== null) {
        $summaryParts[] = "Strongest positive: {$strongestPositive['name']} (+{$strongestPositive['affinity']}).";
    }

    if ($strongestNegative !== null) {
        $summaryParts[] = "Strongest negative: {$strongestNegative['name']} ({$strongestNegative['affinity']}).";
    }

    return implode(' ', $summaryParts);
}

try {
    $npcName = isset($_GET['npc_name']) ? trim($_GET['npc_name']) : '';
    $refId = isset($_GET['refid']) ? trim($_GET['refid']) : '';

    error_log("CHIM AI View: Request received - npc_name: '{$npcName}', refid: '{$refId}'");

    if (empty($npcName) && empty($refId)) {
        echo json_encode([
            'success' => false,
            'error' => 'Missing npc_name or refid parameter'
        ]);
        exit;
    }

    // Test database connection
    $testQuery = "SELECT COUNT(*) as total FROM core_npc_master";
    $testResult = $db->fetchOne($testQuery);
    error_log("CHIM AI View: Total NPCs in database: " . ($testResult['total'] ?? '0'));
    
    // Show some sample NPCs for debugging
    $sampleQuery = "SELECT npc_name, refid FROM core_npc_master ORDER BY gamets_last_updated DESC NULLS LAST LIMIT 5";
    $sampleNpcs = $db->fetchAll($sampleQuery);
    error_log("CHIM AI View: Sample NPCs: " . json_encode($sampleNpcs));
    
    // Query NPC data - PRIORITIZE NAME SEARCH (refid format might not match)
    $npcData = null;
    
    // STEP 1: Try by name first (most reliable)
    if (!empty($npcName)) {
        // Extract base name if brackets present (e.g., "Gisli [Riften Guard]" -> "Gisli")
        $baseName = $npcName;
        if (preg_match('/^(.+?)\s*\[/', $npcName, $matches)) {
            $baseName = trim($matches[1]);
        }
        
        $escapedName = $db->escape($baseName);
        $query = "SELECT * FROM core_npc_master WHERE npc_name = '{$escapedName}' ORDER BY gamets_last_updated DESC NULLS LAST LIMIT 1";
        error_log("CHIM AI View: Searching by name: " . $baseName);
        error_log("CHIM AI View: Query: " . $query);
        $npcData = $db->fetchOne($query);
        
        if ($npcData) {
            error_log("CHIM AI View: Found by name '{$baseName}'");
        } else {
            error_log("CHIM AI View: Not found by name '{$baseName}'");
        }
    }
    
    // STEP 2: If name search failed and we have a refid, try refid as fallback
    if (!$npcData && !empty($refId)) {
        $escapedRefId = $db->escape($refId);
        $query = "SELECT * FROM core_npc_master WHERE refid = '{$escapedRefId}' ORDER BY gamets_last_updated DESC NULLS LAST LIMIT 1";
        error_log("CHIM AI View: Searching by refid: " . $refId);
        error_log("CHIM AI View: Query: " . $query);
        $npcData = $db->fetchOne($query);
        
        if ($npcData) {
            error_log("CHIM AI View: Found by refid '{$refId}'");
        } else {
            error_log("CHIM AI View: Not found by refid '{$refId}'");
        }
    }

    if (!$npcData) {
        error_log("CHIM AI View: NPC not found - searched_name: '{$npcName}', searched_refid: '{$refId}'");
        echo json_encode([
            'success' => false,
            'error' => 'NPC not found in database',
            'searched_name' => $npcName,
            'searched_refid' => $refId
        ]);
        exit;
    }

    error_log("CHIM AI View: NPC found - " . $npcData['npc_name'] . " (refid in DB: " . ($npcData['refid'] ?? 'NULL') . ")");

    // Get profile data if profile_id exists
    $profileData = null;
    $llmConnectors = [];
    $profileMetadata = [];
    
    if (!empty($npcData['profile_id'])) {
        $profileQuery = "SELECT * FROM core_profiles WHERE id = '{$db->escape($npcData['profile_id'])}' LIMIT 1";
        $profileData = $db->fetchOne($profileQuery);
        
        if ($profileData) {
            // Parse metadata JSON
            $profileMetadata = json_decode($profileData['metadata'] ?? '{}', true) ?: [];
            
            // Get LLM connectors
            $connectorIds = [
                'primary' => $profileData['llm_primary_id'] ?? null,
                'secondary' => $profileData['llm_secondary_id'] ?? null,
                'tertiary' => $profileData['llm_tertiary_id'] ?? null,
                'quaternary' => $profileData['llm_quaternary_id'] ?? null,
                'formatter' => $profileData['llm_formatter_id'] ?? null,
                'diary' => $profileData['diary_connector_id'] ?? null
            ];
            
            foreach ($connectorIds as $slot => $connectorId) {
                if (!empty($connectorId)) {
                    $connectorQuery = "SELECT * FROM core_llm_connector WHERE id = '{$db->escape($connectorId)}' LIMIT 1";
                    $connectorData = $db->fetchOne($connectorQuery);
                    if ($connectorData) {
                        $llmConnectors[$slot] = [
                            'id' => $connectorData['id'],
                            'label' => $connectorData['label'] ?? 'Unnamed',
                            'model' => $connectorData['model'] ?? '',
                            'type' => $connectorData['type'] ?? 'openai'
                        ];
                    }
                }
            }
        }
    }

    // Parse JSON columns
    $metadata = json_decode($npcData['metadata'] ?? '{}', true) ?: [];
    $extendedData = json_decode($npcData['extended_data'] ?? '{}', true) ?: [];
    $activityStatus = chimNormalizeActivityStatus($metadata);

    // Determine toggle states - show actual state AND source (NPC override vs inherited)
    
    // Dynamic Profile - stored in NPC column, NULL = inherit from profile
    $dynamicProfile = ['value' => false, 'source' => 'default'];
    if (isset($npcData['dynamic_profile']) && $npcData['dynamic_profile'] !== null && $npcData['dynamic_profile'] !== '') {
        // NPC has explicit override
        $dynamicProfile = ['value' => !empty($npcData['dynamic_profile']), 'source' => 'npc'];
    } else if (isset($profileMetadata['DYNAMIC_PROFILE_ENABLED'])) {
        // Inherit from profile
        $dynamicProfile = ['value' => !empty($profileMetadata['DYNAMIC_PROFILE_ENABLED']), 'source' => 'profile'];
    }
    
    // Middle Term Memory - stored in extended_data JSON
    $middleTermEnabled = ['value' => false, 'source' => 'default'];
    if (isset($extendedData['middle_term_enabled']) && $extendedData['middle_term_enabled'] !== null && $extendedData['middle_term_enabled'] !== '') {
        // NPC has explicit override
        $middleTermEnabled = ['value' => !empty($extendedData['middle_term_enabled']), 'source' => 'npc'];
    } else if (isset($profileMetadata['MIDDLE_TERM_MEMORY_ENABLED'])) {
        // Inherit from profile
        $middleTermEnabled = ['value' => !empty($profileMetadata['MIDDLE_TERM_MEMORY_ENABLED']), 'source' => 'profile'];
    }
    
    // Auto Diary - stored in extended_data JSON
    $autoDiary = ['value' => false, 'source' => 'default'];
    if (isset($extendedData['auto_diary_enabled']) && $extendedData['auto_diary_enabled'] !== null && $extendedData['auto_diary_enabled'] !== '') {
        // NPC has explicit override
        $autoDiary = ['value' => !empty($extendedData['auto_diary_enabled']), 'source' => 'npc'];
    } else if (isset($profileMetadata['AUTO_DIARY_ENABLED'])) {
        // Inherit from profile
        $autoDiary = ['value' => !empty($profileMetadata['AUTO_DIARY_ENABLED']), 'source' => 'profile'];
    }
    
    // Auto Diary Wait - stored in extended_data JSON
    $autoDiaryWait = ['value' => false, 'source' => 'default'];
    if (isset($extendedData['auto_diary_wait_enabled']) && $extendedData['auto_diary_wait_enabled'] !== null && $extendedData['auto_diary_wait_enabled'] !== '') {
        // NPC has explicit override
        $autoDiaryWait = ['value' => !empty($extendedData['auto_diary_wait_enabled']), 'source' => 'npc'];
    } else if (isset($profileMetadata['AUTO_DIARY_WAIT_ENABLED'])) {
        // Inherit from profile
        $autoDiaryWait = ['value' => !empty($profileMetadata['AUTO_DIARY_WAIT_ENABLED']), 'source' => 'profile'];
    }
    
    // Auto Greeting - stored in extended_data JSON
    $autoGreeting = ['value' => false, 'source' => 'default'];
    if (isset($extendedData['salutation_after_a_while']) && $extendedData['salutation_after_a_while'] !== null && $extendedData['salutation_after_a_while'] !== '') {
        // NPC has explicit override
        $autoGreeting = ['value' => !empty($extendedData['salutation_after_a_while']), 'source' => 'npc'];
    } else if (isset($profileMetadata['SALUTATION_AFTER_A_WHILE'])) {
        // Inherit from profile
        $autoGreeting = ['value' => !empty($profileMetadata['SALUTATION_AFTER_A_WHILE']), 'source' => 'profile'];
    }

    // Get relationship affinity data
    $relationships = [];
    if (isset($extendedData['relationships']) && is_array($extendedData['relationships'])) {
        $relationships = chimNormalizeRelationshipAffinities($extendedData['relationships']);
    }

    // Get recent middle term memory
    $recentMemory = '';
    if (isset($extendedData['middle_term_memory']) && is_array($extendedData['middle_term_memory']) && !empty($extendedData['middle_term_memory'])) {
        $memoryEntries = array_values($extendedData['middle_term_memory']);
        $recentMemory = end($memoryEntries);
    }

    // Build response
    $response = [
        'success' => true,
        'data' => [
            // Basic info
            'npc_name' => $npcData['npc_name'] ?? '',
            'gender' => $npcData['gender'] ?? '',
            'race' => $npcData['race'] ?? '',
            'base' => $npcData['base'] ?? '',
            'refid' => $npcData['refid'] ?? '',
            'voiceid' => $npcData['voiceid'] ?? '',
            'oghma_tags' => $npcData['oghma_knowledge_tags'] ?? '',
            
            // Profile info
             'profile' => [
                'id' => $profileData['id'] ?? null,
                'label' => $profileData['label'] ?? 'Default Profile',
                'connectors' => $llmConnectors
             ],

            'activity_status' => $activityStatus,
             
             // Toggle states
             'settings' => [
                'dynamic_profile' => $dynamicProfile,
                'middle_term_memory' => $middleTermEnabled,
                'auto_diary' => $autoDiary,
                'auto_diary_wait' => $autoDiaryWait,
                'auto_greeting' => $autoGreeting,
                'auto_salutations' => $autoGreeting // Backward compatibility for existing UI/API consumers.
            ],
            
            // Bio fields
            'bio' => [
                'prompt_head' => $npcData['prompt_head'] ?? '',
                'core' => $npcData['core'] ?? '',
                'static_bio' => $npcData['npc_static_bio'] ?? '',
                'appearance' => $npcData['appearance'] ?? '',
                'personality' => $npcData['personality'] ?? '',
                'occupation' => $npcData['occupation'] ?? '',
                'skills' => $npcData['skills'] ?? '',
                'speechstyle' => $npcData['speechstyle'] ?? '',
                'goals' => $npcData['goals'] ?? ''
            ],
            
            // Relationships and affinity
            'relationships' => [
                'summary' => chimBuildRelationshipAffinitySummary($relationships),
                'text' => '',
                'affinities' => $relationships
            ],
            
            // Recent memory
            'recent_memory' => $recentMemory
        ]
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    error_log("CHIM AI View API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
}
