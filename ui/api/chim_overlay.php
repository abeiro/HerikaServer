<?php
error_reporting(E_ERROR);
session_start();

// Define base paths
define('BASE_PATH', dirname(dirname(__DIR__)));
define('CONFIG_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'conf');
define('LIB_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'lib');

$configFilepath = CONFIG_PATH . DIRECTORY_SEPARATOR;

if (!file_exists($configFilepath."conf.php")) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration file not found']);
    exit;
}

// Load profiles through the centralized profile loader
require_once(dirname(__DIR__).DIRECTORY_SEPARATOR."profile_loader.php");

require_once(LIB_PATH .DIRECTORY_SEPARATOR."logger.php");
require_once(LIB_PATH .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once(LIB_PATH .DIRECTORY_SEPARATOR."core".DIRECTORY_SEPARATOR."player.class.php");
require_once(LIB_PATH .DIRECTORY_SEPARATOR."visual_context.php");

$db = new sql();

function chimOverlayActorStatusSuffixPattern()
{
    return '/\s*\((?:busy|hostile|in combat|far away|too far away|restrained|dead|disabled|unavailable|audible|narrator|checking(?: hearing|: [^)]+)?|can hear you(?:, muffled|: [^)]+)?|can[\'"]?t hear you(?: clearly)?(?:: [^)]+)?|no (?:target|crosshair target))\)\s*$/iu';
}

function chimOverlayCleanActorName($name)
{
    $name = trim((string)$name);
    if ($name === "") {
        return "";
    }

    $name = trim($name, "|/");
    $name = preg_replace(chimOverlayActorStatusSuffixPattern(), '', $name);
    return trim((string)$name);
}

// Set JSON header
header('Content-Type: application/json');

// Get current mode
$modeResult = $db->fetchOne("SELECT value FROM conf_opts WHERE id='chim_mode'");
$currentMode = isset($modeResult['value']) ? strtoupper(trim($modeResult['value'])) : 'STANDARD';

// Get Compact Chat setting
$compactChat = chimGetGeneralSettingBool('COMPACT_CHAT_ENABLED', true);

// Get the global server-side rechat responder mode.
$allowedRechatModes = ['tight', 'conversational', 'group', 'random'];
$rechatMode = strtolower(trim(chimGetGeneralSetting('RECHAT_MODE', 'random')));
if (!in_array($rechatMode, $allowedRechatModes, true)) {
    $rechatMode = 'random';
}

// Report whether the current location has prompt-eligible visual context without
// running table-creation DDL on the chatbox's five-second status polling path.
$visualContextAvailable = false;
$visualContextLocation = '';
$lastLocation = $db->fetchOne("SELECT data, to_regclass('public.visual_context') AS visual_context_table
    FROM eventlog
    WHERE type IN ('infoloc', 'location') AND data LIKE '%(Context%'
    ORDER BY gamets DESC, ts DESC, localts DESC
    LIMIT 1");
if ($lastLocation && !empty($lastLocation['visual_context_table'])) {
    $visualContextLocation = chimVisualContextLocationBase($lastLocation['data'] ?? '');
    if ($visualContextLocation !== '') {
        $ttlMinutes = max(1, min(chimGetGeneralSettingInt('VISUAL_CONTEXT_SCENE_TTL_MINUTES', 10), 1440));
        $locationLiteral = $db->escapeLiteral($visualContextLocation);
        $visualContextRow = $db->fetchOne("SELECT EXISTS (
            SELECT 1
            FROM public.visual_context
            WHERE active = TRUE
              AND description <> ''
              AND LOWER(BTRIM(REGEXP_REPLACE(
                    SPLIT_PART(location_name, ',', 1),
                    '^\\(?context[[:space:]]+(new[[:space:]]+)?location:[[:space:]]*',
                    '',
                    'i'
                  ), ' ()')) = LOWER({$locationLiteral})
              AND (locked = TRUE OR captured_at >= CURRENT_TIMESTAMP - INTERVAL '{$ttlMinutes} minutes')
        ) AS available");
        $availableValue = strtolower(trim((string)($visualContextRow['available'] ?? '')));
        $visualContextAvailable = in_array($availableValue, ['1', 't', 'true'], true);
    }
}

// Get active model slot
$modelSlotResult = $db->fetchOne("SELECT value FROM conf_opts WHERE id='chim_profile_model'");
$activeModelSlot = isset($modelSlotResult['value']) ? intval($modelSlotResult['value']) : 1;

// Get profile slots and their connectors
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
            'profile_name' => $profile['label'],
            'connectors' => $connectors
        ];
    }
}

// Get active AI agents (NPCs in close range)
$activeAgents = [];
$lastClose = $db->fetchOne("
    SELECT data 
    FROM eventlog 
    WHERE type = 'infonpc_close' 
    ORDER BY gamets DESC, ts DESC, localts DESC 
    LIMIT 1
");

if ($lastClose && isset($lastClose['data'])) {
    $npcData = $lastClose['data'];
    // Parse current slash-delimited DLL output and older pipe-delimited rows.
    $npcs = preg_split('/[\/|]/', trim($npcData, "|/"));
    if (!is_array($npcs)) {
        $npcs = [];
    }
    
    foreach ($npcs as $npc) {
        $cleanName = chimOverlayCleanActorName($npc);
        if ($cleanName === "" || strcasecmp($cleanName, 'The Narrator') === 0) {
            continue;
        }
        if (!empty($GLOBALS["PLAYER_NAME"]) && strcasecmp($cleanName, (string)$GLOBALS["PLAYER_NAME"]) === 0) {
            continue;
        }
        $activeAgents[] = $cleanName;
    }
    
    // Remove duplicates and limit to reasonable number
    $activeAgents = array_unique($activeAgents);
    $activeAgents = array_slice($activeAgents, 0, 20);
}

// Determine active model name from the current slot
$slotNames = ['Standard LLM', 'Fast LLM', 'Powerful LLM', 'Experimental LLM'];
$slotLabels = ['Standard', 'Fast', 'Powerful', 'Experimental'];
$activeModelName = 'Unknown';
$activeSlotLabel = 'Unknown';
$player = new Player();
$playerName = trim((string)($player->get('player_name') ?? ''));
if ($playerName === '') {
    $playerNameResult = $db->fetchOne("SELECT value FROM conf_opts WHERE id='PLAYER_NAME' LIMIT 1");
    $playerName = trim((string)($playerNameResult['value'] ?? ''));
}
if ($playerName === '') {
    $playerName = 'Player';
}

// Map the slot number (1-4) to the label
if ($activeModelSlot >= 1 && $activeModelSlot <= 4) {
    $slotIndex = $activeModelSlot - 1;
    $activeSlotLabel = $slotLabels[$slotIndex];
    
    // Get the connector name for this slot
    if (isset($profileSlots[$activeModelSlot])) {
        $profile = $profileSlots[$activeModelSlot];
        $slotName = $slotNames[$slotIndex];
        
        if (isset($profile['connectors'][$slotName])) {
            $connector = $profile['connectors'][$slotName];
            $activeModelName = $connector['label'] . ' (' . $connector['model'] . ')';
        }
    }
}

$response = [
    'success' => true,
    'data' => [
        'mode' => $currentMode,
        'player_name' => $playerName,
        'compact_chat' => $compactChat,
        'rechat_mode' => $rechatMode,
        'visual_context_available' => $visualContextAvailable,
        'visual_context_location' => $visualContextLocation,
        'active_model_slot' => $activeModelSlot,
        'active_model_label' => $activeSlotLabel,
        'active_model_name' => $activeModelName,
        'active_agents' => $activeAgents,
        'profile_slots' => $profileSlots
    ],
    'timestamp' => time()
];

echo json_encode($response);
