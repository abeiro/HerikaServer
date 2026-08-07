<?php
// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");
require_once(__DIR__.DIRECTORY_SEPARATOR."cmd".DIRECTORY_SEPARATOR."rumor_service.php");
$enginePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
$GLOBALS['ENGINE_PATH'] = $enginePath;

// ─── Includes ─────────────────────────────────────────────────────────────────

require_once $enginePath . 'lib/runtime_bootstrap.php';
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once $enginePath . 'lib/model_dynmodel.php';
require_once $enginePath . 'lib/chat_helper_functions.php';
require_once $enginePath . 'lib/data_functions.php';
require_once $enginePath . 'lib/logger.php';
require_once $enginePath . 'lib/utils_game_timestamp.php';
require_once $enginePath . 'lib/rolemaster_helpers.php';
require_once $enginePath . 'lib/scriptproxy_papyrus.php';
require_once $enginePath . 'lib/background_life_requests.php';
require_once $enginePath . 'lib/background_life_npc_creation.php';
require_once $enginePath . 'lib/background_life_dashboard.php';
require_once $enginePath . 'lib/core/player.class.php';
require_once $enginePath . 'lib/core/npc_master.class.php';
require_once $enginePath . 'lib/core/api_badge.class.php';
require_once $enginePath . 'lib/core/core_profiles.class.php';
require_once $enginePath . 'lib/core/llm_connector.class.php';
require_once $enginePath . 'lib/core/tts_connector.class.php';
require_once $enginePath . 'lib/lazy_xml.php';
require_once $enginePath . 'debug/background_action_handler.php';

require_once $enginePath . "lib/scriptproxy_papyrus.php";
require_once $enginePath . "lib/core/activity_status.php";

// ─── Database ─────────────────────────────────────────────────────────────────

$db = $GLOBALS["db"];

$TITLE = "🗺️ Background Life - Map Viewer";

ob_start();

$enginePath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "utils_game_timestamp.php";

$host     = 'localhost';
$port     = '5432';
$dbname   = 'dwemer';
$schema   = 'public';
$username = 'dwemer';
$password = 'dwemer';

$adminConn = @pg_connect("host={$host} port={$port} dbname={$dbname} user={$username} password={$password}");
if (! $adminConn) {
    echo json_encode(['ok' => false]);
    exit;
}

$rumorFlash = null;
$bglSettingsFlash = null;
$spawnNpcFlash = null;
$rumorFormData = [
    'hold' => '',
    'type' => '',
    'content' => '',
    'length_days' => '7',
];
$spawnNpcFormData = chimBglNpcCreationDefaults();
$editingRumorId = 0;

function getRumorPagePath() {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (!$path) {
        $path = $_SERVER['SCRIPT_NAME'];
    }
    return $path;
}

function redirectToRumorSection($status, $message, $anchor = 'create-rumor') {
    $path = getRumorPagePath();

    $query = http_build_query([
        'tab' => 'backgroundlife',
        'bgl_tab' => 'rumors',
        'rumor_status' => $status,
        'rumor_message' => $message,
    ]);

    $anchor = trim((string) $anchor);
    if ($anchor !== '') {
        $anchor = '#' . ltrim($anchor, '#');
    }

    header('Location: ' . $path . '?' . $query . $anchor);
    exit;
}

function redirectToBglSettings($status, $message) {
    $path = getRumorPagePath();
    $query = http_build_query([
        'tab' => 'backgroundlife',
        'bgl_tab' => 'background',
        'bgl_settings_status' => $status,
        'bgl_settings_message' => $message,
    ]);

    header('Location: ' . $path . '?' . $query . '#background-life-settings');
    exit;
}

function redirectToSpawnNpcSection($status, $message, $anchor = 'create-background-npc') {
    $path = getRumorPagePath();
    $query = http_build_query([
        'tab' => 'backgroundlife',
        'bgl_tab' => 'background',
        'spawn_npc_status' => $status,
        'spawn_npc_message' => $message,
    ]);

    $anchor = trim((string) $anchor);
    if ($anchor !== '') {
        $anchor = '#' . ltrim($anchor, '#');
    }

    header('Location: ' . $path . '?' . $query . $anchor);
    exit;
}

function fetchRumorById($rumorId) {
    global $adminConn;

    $rumorId = (int) $rumorId;
    if ($rumorId <= 0) {
        return null;
    }

    $result = pg_query_params(
        $adminConn,
        "SELECT id, hold, type, content, COALESCE(rumor_length_days, 7) AS rumor_length_days FROM rumors WHERE id = $1 LIMIT 1",
        [$rumorId]
    );

    if (!$result || pg_num_rows($result) === 0) {
        return null;
    }

    return pg_fetch_assoc($result);
}

function handleCreateRumor() {
    global $adminConn;

    $hold = trim((string)($_POST['rumor_hold'] ?? ''));
    $type = trim((string)($_POST['rumor_type'] ?? ''));
    $content = trim((string)($_POST['rumor_content'] ?? ''));
    $lengthDaysInput = trim((string)($_POST['rumor_length_days'] ?? ''));

    $formData = [
        'hold' => $hold,
        'type' => $type,
        'content' => $content,
        'length_days' => ($lengthDaysInput !== '') ? $lengthDaysInput : '7',
    ];

    $result = chimCreateRumorEntry($adminConn, $hold, $type, $content, $lengthDaysInput);
    if (!($result['ok'] ?? false)) {
        return [
            ['type' => 'error', 'message' => $result['message'] ?? 'Failed to create rumor.'],
            $formData,
        ];
    }

    redirectToRumorSection('success', $result['message'] ?? 'Rumor created successfully.');
}

function handleUpdateRumor() {
    global $adminConn;

    $rumorId = (int) ($_POST['rumor_id'] ?? 0);
    $hold = trim((string)($_POST['rumor_hold'] ?? ''));
    $type = trim((string)($_POST['rumor_type'] ?? ''));
    $content = trim((string)($_POST['rumor_content'] ?? ''));
    $lengthDaysInput = trim((string)($_POST['rumor_length_days'] ?? ''));

    $formData = [
        'hold' => $hold,
        'type' => $type,
        'content' => $content,
        'length_days' => ($lengthDaysInput !== '') ? $lengthDaysInput : '7',
        'id' => $rumorId,
    ];

    $result = chimUpdateRumorEntry($adminConn, $rumorId, $hold, $type, $content, $lengthDaysInput);
    if (!($result['ok'] ?? false)) {
        return [
            ['type' => 'error', 'message' => $result['message'] ?? 'Failed to update rumor.'],
            $formData,
            $rumorId,
        ];
    }

    redirectToRumorSection('success', $result['message'] ?? 'Rumor updated successfully.');
}

function handleDeleteRumor() {
    global $adminConn;

    $rumorId = (int) ($_POST['rumor_id'] ?? 0);
    $result = chimDeleteRumorEntry($adminConn, $rumorId);

    if (!($result['ok'] ?? false)) {
        return [
            'type' => 'error',
            'message' => $result['message'] ?? 'Failed to delete rumor.',
        ];
    }

    redirectToRumorSection('success', $result['message'] ?? 'Rumor deleted successfully.', 'rumors-section');
}

function handleCreateBackgroundNpc() {
    $result = chimBglCreateNpc($_POST);
    $formData = $result['form_data'] ?? chimBglNpcCreationFormData($_POST);
    if (!($result['ok'] ?? false)) {
        return [[
            'type' => 'error',
            'message' => $result['message'] ?? 'Failed to create NPC.',
        ], $formData];
    }

    redirectToSpawnNpcSection('success', $result['message'] ?? 'NPC created successfully.');
}

if (isset($_GET['rumor_status']) && isset($_GET['rumor_message'])) {
    $rumorFlash = [
        'type' => ($_GET['rumor_status'] === 'success') ? 'success' : 'error',
        'message' => trim((string) $_GET['rumor_message']),
    ];
}

if (isset($_GET['bgl_settings_status']) && isset($_GET['bgl_settings_message'])) {
    $bglSettingsFlash = [
        'type' => ($_GET['bgl_settings_status'] === 'success') ? 'success' : 'error',
        'message' => trim((string) $_GET['bgl_settings_message']),
    ];
}

if (isset($_GET['spawn_npc_status']) && isset($_GET['spawn_npc_message'])) {
    $spawnNpcFlash = [
        'type' => ($_GET['spawn_npc_status'] === 'success') ? 'success' : 'error',
        'message' => trim((string) $_GET['spawn_npc_message']),
    ];
}

$npcCreationOptions = chimBglNpcCreationOptions();

// Helper function to resolve NPC portrait path (same as npc_master.php)
if (!function_exists('race_icon_web_path')) {
    function race_icon_web_path($race, $webRoot, $refid, $md5 = '', $npcName = '', $portraitRel = ''){
        global $enginePath;
        // 0) If metadata specifies a portrait relative path under data/pictures, use it first
        $portraitRel = trim((string)$portraitRel);
        if ($portraitRel !== '') {
            $portraitRel = ltrim(str_replace(['\\'], '/', $portraitRel), '/');
            $picturesRootFs = rtrim("{$enginePath}/data/pictures/", '/\\') . DIRECTORY_SEPARATOR;
            $picturesRootUrl = rtrim($webRoot . '/data/pictures/', '/');
            $fs = realpath($picturesRootFs . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $portraitRel));
            $picturesRootFs=realpath($picturesRootFs);
            if ($fs !== false && strpos($fs, $picturesRootFs) === 0 && is_file($fs)) {
                return $picturesRootUrl . '/' . str_replace('%2F','/', rawurlencode($portraitRel));
            }
        }
        // 1) Prefer per-NPC portrait from data/pictures/profile
        $refid = strtoupper($refid);
        $profileDirFs = rtrim("{$enginePath}/data/pictures/profile/", '/\\') . DIRECTORY_SEPARATOR;
        $profileDirUrl = rtrim($webRoot . '/data/pictures/profile/', '/');
        $exts = ['png','jpg','jpeg','webp','gif'];
        $candidates = [];
        if (!empty($md5)) { $candidates[] = $md5; }
        if (!empty($refid)) { $candidates[] = $refid; }
        if (!empty($npcName)) {
            $in = strtolower((string)$npcName);
            $words = preg_split('/[^a-z0-9]+/', $in, -1, PREG_SPLIT_NO_EMPTY);
            if (!empty($words)) {
                $candidates[] = implode('', $words);
                $candidates[] = implode('-', $words);
                $candidates[] = implode('_', $words);
            }
        }
        $seen = [];
        foreach ($candidates as $base){
            $base = trim((string)$base);
            if ($base === '' || isset($seen[$base])) continue;
            $seen[$base] = true;
            foreach ($exts as $ext){
                $fs = $profileDirFs . $base . '.' . $ext;
                if (file_exists($fs)) {
                    return $profileDirUrl . '/' . rawurlencode($base . '.' . $ext);
                }
            }
        }

        // 2) Fallback to race icon pack
        $in = strtolower((string)$race);
        $words = preg_split('/[^a-z0-9]+/', $in, -1, PREG_SPLIT_NO_EMPTY);
        $slug = implode('', $words);
        if ($slug === '') { $words = []; }
        $aliases = [
            'highelf'=>'altmer', 'altmer'=>'altmer',
            'woodelf'=>'bosmer', 'bosmer'=>'bosmer',
            'darkelf'=>'dunmer', 'dunmer'=>'dunmer',
            'orsimer'=>'orc', 'orc'=>'orc',
            'argonian'=>'argonian', 'khajiit'=>'khajiit', 'khajit'=>'khajiit',
            'breton'=>'breton', 'imperial'=>'imperial',
            'nord'=>'nord', 'redguard'=>'redguard',
            'oldpeople'=>'nord', 'oldpeoplerace'=>'nord',
        ];
        $base = $aliases[$slug] ?? $slug;
        $variants = [];
        $variants[] = $base;
        if (!empty($words)){
            $variants[] = implode('', $words);
            $variants[] = implode('-', $words);
            $variants[] = implode('_', $words);
        }
        // Synonyms/misspellings
        if ($base === 'khajiit') { $variants[] = 'khajit'; }
        if ($base === 'khajit') { $variants[] = 'khajiit'; }
        $variants = array_values(array_unique(array_filter($variants, function($v){ return $v !== ''; })));
        $fsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'ui' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'races' . DIRECTORY_SEPARATOR;
        $exts2 = ['png','jpg','jpeg','webp','gif','svg'];
        foreach ($variants as $name){
            foreach ($exts2 as $ext){
                $fs = $fsDir . $name . '.' . $ext;
                if (file_exists($fs)) return $webRoot . '/ui/images/races/' . $name . '.' . $ext;
            }
        }
        $defaultFs = $fsDir . 'default.png';
        if (file_exists($defaultFs)) { return $webRoot . '/ui/images/races/default.png'; }
        return '';
    }
}

    // Handle AJAX request update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'request_action') {
            handleRequestAction();
            exit;
        } elseif ($_POST['action'] === 'request_reporting') {
            handleRequestReporting();
            exit;
        } elseif ($_POST['action'] === 'update_coords') {
            handleUpdateCoords();
            exit;
        } elseif ($_POST['action'] === 'update_all_coords') {
            handleUpdateAllCoords();
            exit;
        } elseif ($_POST['action'] === 'toggle_bg_life_setting') {
            handleToggleBgLifeSetting();
            exit;
        } elseif ($_POST['action'] === 'toggle_all_bg_life_settings') {
            handleToggleAllBgLifeSettings();
            exit;
        } elseif ($_POST['action'] === 'create_rumor') {
            [$rumorFlash, $rumorFormData] = handleCreateRumor();
        } elseif ($_POST['action'] === 'update_rumor') {
            [$rumorFlash, $rumorFormData, $editingRumorId] = handleUpdateRumor();
        } elseif ($_POST['action'] === 'delete_rumor') {
            $rumorFlash = handleDeleteRumor();
        } elseif ($_POST['action'] === 'save_bgl_settings') {
            handleSaveBglSettings();
        } elseif ($_POST['action'] === 'create_background_npc') {
            [$spawnNpcFlash, $spawnNpcFormData] = handleCreateBackgroundNpc();
        }
    }

    if ($editingRumorId <= 0 && isset($_GET['edit_rumor_id'])) {
        $editingRumorId = (int) $_GET['edit_rumor_id'];
    }

    if ($editingRumorId > 0 && !isset($rumorFormData['id'])) {
        $editingRumor = fetchRumorById($editingRumorId);
        if ($editingRumor) {
            $rumorFormData = [
                'hold' => (string) ($editingRumor['hold'] ?? ''),
                'type' => (string) ($editingRumor['type'] ?? ''),
                'content' => (string) ($editingRumor['content'] ?? ''),
                'length_days' => (string) ($editingRumor['rumor_length_days'] ?? '7'),
                'id' => (int) ($editingRumor['id'] ?? 0),
            ];
        } else {
            $editingRumorId = 0;
            $rumorFlash = [
                'type' => 'error',
                'message' => 'Rumor not found for editing.',
            ];
        }
    } elseif (isset($rumorFormData['id'])) {
        $editingRumorId = (int) $rumorFormData['id'];
    }

    $activeBglTab = strtolower(trim((string) ($_GET['bgl_tab'] ?? 'background')));
    if (!in_array($activeBglTab, ['background', 'history', 'rumors'], true)) {
        $activeBglTab = 'background';
    }
    if ($editingRumorId > 0 || !empty($rumorFlash['message'])) {
        $activeBglTab = 'rumors';
    } elseif (!empty($spawnNpcFlash['message']) || !empty($bglSettingsFlash['message'])) {
        $activeBglTab = 'background';
    }

    $rumorsTabUrl = getRumorPagePath() . '?' . http_build_query(['tab' => 'backgroundlife', 'bgl_tab' => 'rumors']);
    $backgroundPageTabUrl = $webRoot . '/ui/events-memories.php?' . http_build_query(['tab' => 'backgroundlife', 'bgl_tab' => 'background']);
    $historyPageTabUrl = $webRoot . '/ui/events-memories.php?' . http_build_query(['tab' => 'backgroundlife', 'bgl_tab' => 'history']);
    $rumorsPageTabUrl = $webRoot . '/ui/events-memories.php?' . http_build_query(['tab' => 'backgroundlife', 'bgl_tab' => 'rumors']);

    function handleRequestAction() {
        global $enginePath;
        $npcName = isset($_POST['npc_name']) ? $_POST['npc_name'] : null;
        
        if (!$npcName) {
            echo json_encode(['ok' => false, 'message' => 'NPC name required']);
            return;
        }
        $npcMaster=new NpcMaster();
        $npcData=$npcMaster->getByName($npcName);
        $extendedData=$npcMaster->getExtendedData($npcData);
        if (!isset($extendedData['background_life_commands']) || $extendedData['background_life_commands']===false) {
            `php $enginePath/debug/simple_llm_request_with_context_life.php "$npcName" full forceaction`;
        } else {
            `php $enginePath/debug/simple_llm_request_with_context_life_v2.php "$npcName" full forceaction`;
        }

        // Add your handler code here
        

        echo json_encode(['ok' => true, 'message' => "Action request processed for $npcName"]);
    }

    function handleRequestReporting() {
        global $enginePath;
        $npcName = isset($_POST['npc_name']) ? $_POST['npc_name'] : null;
        
        if (!$npcName) {
            echo json_encode(['ok' => false, 'message' => 'NPC name required']);
            return;
        }
        
        // Add your handler code here
           // Add your handler code here
        `php $enginePath/debug/simple_llm_request_with_context_life.php "$npcName" forceletter`;
        echo json_encode(['ok' => true, 'message' => "Reporting request processed for $npcName"]);
    }

    function handleUpdateCoords() {
        global $enginePath;
        $npcName = isset($_POST['npc_name']) ? $_POST['npc_name'] : null;
        
        if (!$npcName) {
            echo json_encode(['ok' => false, 'message' => 'NPC name required']);
            return;
        }
        
        // Add your handler code here
        `php $enginePath/debug/simple_llm_request_with_context_life_command.php "$npcName" Track`;
        echo json_encode(['ok' => true, 'message' => "Coords update processed for $npcName"]);
    }

    function handleUpdateAllCoords() {
        global $enginePath;
        
        // Update coordinates for all NPCs
        `php $enginePath/debug/simple_llm_request_with_context_life_command.php "The Narrator" TrackAll`;
        echo json_encode(['ok' => true, 'message' => 'All NPC coords update processed']);
    }

    function handleToggleBgLifeSetting() {
        global $adminConn;
        
        $npcId = isset($_POST['npc_id']) ? intval($_POST['npc_id']) : 0;
        $setting = isset($_POST['setting']) ? $_POST['setting'] : '';
        $value = isset($_POST['value']) ? filter_var($_POST['value'], FILTER_VALIDATE_BOOLEAN) : false;
        
        if (!$npcId || !$setting) {
            echo json_encode(['ok' => false, 'message' => 'Invalid parameters']);
            return;
        }
        
        // Get current NPC data
        $query = "SELECT metadata, extended_data FROM core_npc_master WHERE id = $1";
        $result = pg_query_params($adminConn, $query, [$npcId]);
        
        if (!$result || pg_num_rows($result) === 0) {
            echo json_encode(['ok' => false, 'message' => 'NPC not found']);
            return;
        }
        
        $row = pg_fetch_assoc($result);
        
        if ($setting === 'bg_life_commands') {
            // Update extended_data
            $extData = json_decode($row['extended_data'], true) ?: [];
            $extData['background_life_commands'] = $value;
            $extDataJson = json_encode($extData);
            
            $updateQuery = "UPDATE core_npc_master SET extended_data = $1 WHERE id = $2";
            $updateResult = pg_query_params($adminConn, $updateQuery, [$extDataJson, $npcId]);
            
            if ($updateResult) {
                echo json_encode(['ok' => true, 'message' => 'Autonomous Actions ' . ($value ? 'enabled' : 'disabled')]);
            } else {
                echo json_encode(['ok' => false, 'message' => 'Update failed']);
            }
        } elseif ($setting === 'bg_life_letters') {
            // Update extended_data
            $extData = json_decode($row['extended_data'], true) ?: [];
            $extData['background_life_letters'] = $value;
            $extDataJson = json_encode($extData);
            
            $updateQuery = "UPDATE core_npc_master SET extended_data = $1 WHERE id = $2";
            $updateResult = pg_query_params($adminConn, $updateQuery, [$extDataJson, $npcId]);
            
            if ($updateResult) {
                echo json_encode(['ok' => true, 'message' => 'Send Letters ' . ($value ? 'enabled' : 'disabled')]);
            } else {
                echo json_encode(['ok' => false, 'message' => 'Update failed']);
            }
        } elseif ($setting === 'gps_track') {
            // Update metadata
            $metadata = json_decode($row['metadata'], true) ?: [];
            $metadata['gps_track'] = $value;
            $metadataJson = json_encode($metadata);
            
            $updateQuery = "UPDATE core_npc_master SET metadata = $1 WHERE id = $2";
            $updateResult = pg_query_params($adminConn, $updateQuery, [$metadataJson, $npcId]);
            
            if ($updateResult) {
                echo json_encode(['ok' => true, 'message' => 'Hourly Tracking ' . ($value ? 'enabled' : 'disabled')]);
            } else {
                echo json_encode(['ok' => false, 'message' => 'Update failed']);
            }
        } else {
            echo json_encode(['ok' => false, 'message' => 'Invalid setting']);
        }
    }

    // Apply one Background Life rule to every currently enrolled NPC.
    function handleToggleAllBgLifeSettings() {
        global $adminConn;

        $setting = trim((string) ($_POST['setting'] ?? ''));
        $value = filter_var($_POST['value'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $settings = [
            'bg_life_commands' => [
                'column' => 'extended_data',
                'key' => 'background_life_commands',
                'label' => 'Automatic Actions',
            ],
            'bg_life_letters' => [
                'column' => 'extended_data',
                'key' => 'background_life_letters',
                'label' => 'Letters',
            ],
            'gps_track' => [
                'column' => 'metadata',
                'key' => 'gps_track',
                'label' => 'Tracking',
            ],
        ];

        if (!isset($settings[$setting])) {
            echo json_encode(['ok' => false, 'message' => 'Invalid setting']);
            return;
        }

        $config = $settings[$setting];
        $column = $config['column'];
        $query = "
            UPDATE core_npc_master
            SET {$column} = jsonb_set(
                COALESCE({$column}, '{}'::jsonb),
                ARRAY[$1]::text[],
                to_jsonb($2::boolean),
                true
            )
            WHERE LOWER(COALESCE(extended_data->>'background_life_enabled', 'false'))
                IN ('true', '1', 't', 'on')
        ";
        $result = pg_query_params($adminConn, $query, [$config['key'], $value ? 'true' : 'false']);

        if (!$result) {
            echo json_encode(['ok' => false, 'message' => 'Update failed']);
            return;
        }

        $updatedCount = pg_affected_rows($result);
        echo json_encode([
            'ok' => true,
            'message' => sprintf(
                '%s %s for %d Background Life NPC%s',
                $config['label'],
                $value ? 'enabled' : 'disabled',
                $updatedCount,
                $updatedCount === 1 ? '' : 's'
            ),
            'updated_count' => $updatedCount,
        ]);
    }

    function handleSaveBglSettings() {
        $cooldownHours = isset($_POST['bgl_trigger_hours']) ? floatval($_POST['bgl_trigger_hours']) : 24;
        $cooldownHours = chimNormalizeBackgroundLifeTriggerHours($cooldownHours);
        $description = chimGetSchemaDescription('BGL_TRIGGER_HOURS');

        if (chimSetGeneralSetting('BGL_TRIGGER_HOURS', $cooldownHours, $description)) {
            redirectToBglSettings('success', "Background Life cooldown saved: {$cooldownHours} in-game hours.");
        }

        redirectToBglSettings('error', 'Could not save Background Life cooldown.');
    }

    // Coordinate translation constants (world bounds)
    // X: west (negative) to east (positive)
    // Y: south (negative) to north (positive)
    $WORLD_X_MIN = -225242;
    $WORLD_X_MAX = 217068;
    $WORLD_Y_MIN = -164195;  // SOUTH (negative)
    $WORLD_Y_MAX = 204675;   // NORTH (positive)

    // Map dimensions (from the actual image)
    $mapWidth  = 1950;
    $mapHeight = 1625;

    // Map file path (relative to web root)
$mapImageUrl = '../data/maps/Map_of_Skyrim.png?v=7';

    // Function to translate in-game coordinates to map coordinates
    function translateCoords($ingameX, $ingameY, $mapWidth, $mapHeight, $worldXMin, $worldXMax, $worldYMin, $worldYMax)
    {
        // Linear mapping from world coordinates to map coordinates
        $worldXRange = $worldXMax - $worldXMin;
        $worldYRange = $worldYMax - $worldYMin;

        // X: west to east, left to right on map
        $mapX = (($ingameX - $worldXMin) / $worldXRange) * $mapWidth;

        // Y: south (negative) to north (positive), but image Y is top to bottom
        // So we need to invert: north (high Y) maps to top of image (low Y)
        $mapY = (($worldYMax - $ingameY) / $worldYRange) * $mapHeight;

        // Clamp coordinates to map bounds
        $mapX = max(0, min($mapWidth, $mapX));
        $mapY = max(0, min($mapHeight, $mapY));

        return [
            'x' => round($mapX),
            'y' => round($mapY),
        ];
    }

    $result  =pg_query($adminConn,"select max(gamets) as last_gamets from eventlog");
    $res = pg_fetch_assoc($result);
    $last_gamets = $res["last_gamets"];
$currentDate=convert_gamets2skyrim_date($last_gamets);
$bglTriggerHours = chimGetBackgroundLifeTriggerHours();

$bglBulkState = [
    'total' => 0,
    'bg_life_commands' => 0,
    'bg_life_letters' => 0,
    'gps_track' => 0,
];
$bglBulkResult = pg_query($adminConn, "
    SELECT
        COUNT(*) AS total,
        COUNT(*) FILTER (
            WHERE LOWER(COALESCE(extended_data->>'background_life_commands', 'false'))
                IN ('true', '1', 't', 'on')
        ) AS bg_life_commands,
        COUNT(*) FILTER (
            WHERE LOWER(COALESCE(extended_data->>'background_life_letters', 'false'))
                IN ('true', '1', 't', 'on')
        ) AS bg_life_letters,
        COUNT(*) FILTER (
            WHERE LOWER(COALESCE(metadata->>'gps_track', 'false'))
                IN ('true', '1', 't', 'on')
        ) AS gps_track
    FROM core_npc_master
    WHERE LOWER(COALESCE(extended_data->>'background_life_enabled', 'false'))
        IN ('true', '1', 't', 'on')
");
if ($bglBulkResult && ($bglBulkRow = pg_fetch_assoc($bglBulkResult))) {
    foreach ($bglBulkState as $key => $unused) {
        $bglBulkState[$key] = (int) ($bglBulkRow[$key] ?? 0);
    }
}

    // Filter mode: show all NPCs with tracked coords, or only BG-Life enabled ones
    $showAllCoords = isset($_GET['show_all_coords']) && $_GET['show_all_coords'] === '1';
    $whereClause = $showAllCoords
        ? "metadata->>'last_coords' IS NOT NULL"
        : "extended_data->>'background_life_enabled' = 'true'";
    $categorySelect = chimBglHistoryCategorySelect($db);
    $latestCategorySelect = $categorySelect === ''
        ? 'NULL::text AS category'
        : 'history.category AS category';

    $query = "
    select A.*,B.content,C.data as last_activity,C.gamets as last_activity_gamets,C.category as last_action_cat FROM
    (SELECT
        npc_name,metadata,extended_data,id,refid,race,extended_data->>'background_life_last_updated' as last_report,
        metadata->>'last_coords' as last_coords,metadata->>'last_coords_history' as last_coords_history
    FROM core_npc_master
    WHERE {$whereClause}
    ) A
    LEFT JOIN  (
    SELECT topic, gamets, content, people
        FROM (
            SELECT
                topic,
                gamets,
                content,
                people,
                ROW_NUMBER() OVER (
                    PARTITION BY people
                    ORDER BY gamets DESC
                ) AS rn
            FROM public.diarylog
            WHERE topic = 'Sent Letter'
        ) t
        WHERE rn = 1
    ) B ON (B.people=A.npc_name)
    LEFT JOIN LATERAL (
        SELECT history.data, history.gamets, {$latestCategorySelect}
        FROM public.bgl_history history
        WHERE history.npc = A.npc_name
        ORDER BY history.gamets DESC, history.ts DESC, history.rowid DESC
        LIMIT 1
    ) C ON TRUE
    order by A.npc_name asc
";
    //error_log($query);
    $result = pg_query($adminConn, $query);

    // Generate random colors for markers
    function generateRandomColor($seed)
    {
        // Create a hash from the seed string
        $hash = crc32($seed);
        
        // Use the hash to generate a consistent color
        return sprintf('#%06X', abs($hash) % 0xFFFFFF);
    }

    // Build markers array from database results
    $markers = [];
    if ($result) {
        while ($row = pg_fetch_assoc($result)) {
            // Parse last_coords (assuming format like "x,y" or JSON)
            $coords = $row['last_coords'];

            // Try JSON format
            $coordsData = json_decode("{$coords}", true);
            if ($coordsData && isset($coordsData[0]) && isset($coordsData[1])) {
                $x = $coordsData[0];
                $y = $coordsData[1];
                $z = $coordsData[2];
            } else {
                error_log("[MAP] Skipping {$row["npc_name"]} {$coords}" . print_r($coordsData, true));
                //continue; // Skip invalid coordinates
                $x=$WORLD_X_MIN;
                $y=$WORLD_Y_MIN;
                $coordsData[3].=" missing coords";
            }

            $meta      = json_decode($row['metadata'], true);
            $extData   = json_decode($row['extended_data'], true);
            
            // Parse background life settings
            $backgroundLifeEnabled = isset($extData['background_life_enabled']) ? (bool)$extData['background_life_enabled'] : false;
            $bgLifeCommands = isset($extData['background_life_commands']) ? (bool)$extData['background_life_commands'] : false;
            $bgLifeLetters = isset($extData['background_life_letters']) ? (bool)$extData['background_life_letters'] : false;
            $gpsTrack = isset($meta['gps_track']) ? (bool)$meta['gps_track'] : false;
            
            // Parse history coordinates
            $coordsHistory = [];
            if (!empty($row['last_coords_history'])) {
                $historyData = json_decode($row['last_coords_history'], true);
                if (is_array($historyData)) {
                    foreach ($historyData as $historicalCoord) {
      
                        if (isset($historicalCoord[0]) && isset($historicalCoord[1])) {
                            if (($last_gamets-$historicalCoord['last_updated'])*0.0000024 < 24) { // Only if last coord is recent
                                $coordsHistory[] = [
                                    'x' => (int) $historicalCoord[0],
                                    'y' => (int) $historicalCoord[1],
                                    'z' => isset($historicalCoord[2]) ? (int) $historicalCoord[2] : 0,
                                    'location' => isset($historicalCoord[3]) ? $historicalCoord[3] : '',
                                    'last_updated' => isset($historicalCoord['last_updated']) ? $historicalCoord['last_updated'] : null,
                                ];
                            }
                        }
                    }
                }
            }
            
            // Fetch diary entries (letters and thoughts) for this NPC
            $diaryLetters = [];
            $diaryThoughts = [];
            
            $letterQuery = "SELECT topic, content, tags, location, localts, gamets 
                            FROM diarylog 
                            WHERE people LIKE $1 AND topic = 'Sent Letter'
                            ORDER BY gamets DESC, localts DESC
                            LIMIT 10";
            $letterResult = pg_query_params($adminConn, $letterQuery, ['%' . $row['npc_name'] . '%']);
            if ($letterResult) {
                while ($letter = pg_fetch_assoc($letterResult)) {
                    $letter['skyrim_date'] = !empty($letter['gamets']) ? convert_gamets2skyrim_long_date2($letter['gamets']) : 'Unknown date';
                    $diaryLetters[] = $letter;
                }
            }
            
            $thoughtQuery = "SELECT topic, content, tags, location, localts, gamets 
                             FROM diarylog 
                             WHERE people LIKE $1 AND (topic != 'Sent Letter' OR topic IS NULL)
                             ORDER BY gamets DESC, localts DESC
                             LIMIT 10";
            $thoughtResult = pg_query_params($adminConn, $thoughtQuery, ['%' . $row['npc_name'] . '%']);
            if ($thoughtResult) {
                while ($thought = pg_fetch_assoc($thoughtResult)) {
                    $thought['skyrim_date'] = !empty($thought['gamets']) ? convert_gamets2skyrim_long_date2($thought['gamets']) : 'Unknown date';
                    $diaryThoughts[] = $thought;
                }
            }
            
            $lastActivity = trim((string)($row['last_activity'] ?? ''));
            $lastActivityCategory = chimBglActivityCategory(
                (string)($row['last_action_cat'] ?? ''),
                $lastActivity
            );

            $markers[] = [
                'name'        => $row['npc_name'],
                'ingame_x'    => (int) $x,
                'ingame_y'    => (int) $y,
                'ingame_z'    => (int) $z,
                'color'       => generateRandomColor($row['npc_name']),
                'size'        => 10,
                'tag'         => $coordsData[3],
                'portrait'    => isset($meta["portrait"]) ? $meta["portrait"] : '',
                'race'        => $row['race'],
                'id'          => $row["id"],
                'refid'       => $row["refid"],
                'last_pos_ts' => $coordsData["last_updated"]?convert_gamets2skyrim_date($coordsData["last_updated"]).",hours ago:".round(($last_gamets-$coordsData["last_updated"]) *0.0000024,0):null,
                'last_report' => convert_gamets2skyrim_date($row["last_report"]).",hours ago:".round(($last_gamets-$row["last_report"]) *0.0000024,0),
                'last_activity' => $lastActivity,
                'last_action_cat' => $lastActivityCategory,
                'last_action_icon' => chimBglActivityIcon($lastActivityCategory, $lastActivity),
                'last_action_label' => chimBglActivityLabel($lastActivityCategory, $lastActivity),
                'coords_history' => $coordsHistory,
                'background_life_enabled' => $backgroundLifeEnabled,
                'bg_life_commands' => $bgLifeCommands,
                'bg_life_letters' => $bgLifeLetters,
                'gps_track' => $gpsTrack,
                'last_letter' => $row["content"],
                'diary_letters' => $diaryLetters,
                'diary_thoughts' => $diaryThoughts,
            ];

        }
    }

    // Translate all marker coordinates
    $translatedMarkers = [];
    $locationMap       = []; // Track markers at each location

    foreach ($markers as $marker) {
        $coords = translateCoords(
            $marker['ingame_x'],
            $marker['ingame_y'],
            $mapWidth,
            $mapHeight,
            $WORLD_X_MIN,
            $WORLD_X_MAX,
            $WORLD_Y_MIN,
            $WORLD_Y_MAX
        );

        // Translate history coordinates
        $translatedHistory = [];
        foreach ($marker['coords_history'] as $histCoord) {
            $histTranslated = translateCoords(
                $histCoord['x'],
                $histCoord['y'],
                $mapWidth,
                $mapHeight,
                $WORLD_X_MIN,
                $WORLD_X_MAX,
                $WORLD_Y_MIN,
                $WORLD_Y_MAX
            );
            $translatedHistory[] = [
                'x' => $histTranslated['x'],
                'y' => $histTranslated['y'],
                'ingame_x' => $histCoord['x'],
                'ingame_y' => $histCoord['y'],
                'location' => $histCoord['location'],
                'last_updated' => $histCoord['last_updated'] ? convert_gamets2skyrim_date($histCoord['last_updated']) . ", " . round(($last_gamets - $histCoord['last_updated']) * 0.0000024, 0) . " hours ago" : null,
            ];
        }

        // Resolve NPC portrait using the helper function
        $md5 = md5($marker['name']);
        $figureUrl = race_icon_web_path(
            $marker['race'],
            $webRoot,
            $marker['refid'],
            $md5,
            $marker['name'],
            $marker['portrait']
        );

        $translatedMarkers[] = [
            'name'     => $marker['name'],
            'x'        => $coords['x'],
            'y'        => $coords['y'],
            'color'    => $marker['color'],
            'size'     => $marker['size'],
            'ingame_x' => $marker['ingame_x'],
            'ingame_z' => $marker['ingame_z'],
            'ingame_y' => $marker['ingame_y'],
            'tag'      => $marker['tag'],
            'figure'   => $figureUrl,
            'id'          => $marker['id'],
            'refid'       => $marker['refid'],
            'last_pos_ts' => $marker["last_pos_ts"],
            'last_report' => $marker["last_report"],
            'last_activity' => $marker['last_activity'],
            'last_action_cat' => $marker['last_action_cat'],
            'last_action_icon' => $marker['last_action_icon'],
            'last_action_label' => $marker['last_action_label'],
            'coords_history' => $translatedHistory,
            'bg_life_commands' => $marker['bg_life_commands'],
            'bg_life_letters' => $marker['bg_life_letters'],
            'gps_track' => $marker['gps_track'],
            'last_letter' => $marker['last_letter'],
            'diary_letters' => $marker['diary_letters'],
            'diary_thoughts' => $marker['diary_thoughts'],
        ];
    }

    // Apply grid offset to markers at the same location
    $locationKey = [];
    foreach ($translatedMarkers as $n => $marker) {
        $key = $translatedMarkers[$n]['x'] . ',' . $translatedMarkers[$n]['y'];

        if (! isset($locationKey[$key])) {
            $locationKey[$key] = 0;
        } else {
            $locationKey[$key]++;
        }

        // Calculate grid position (3 columns, rows as needed)
        $cols = 3;
        $row  = intdiv($locationKey[$key], $cols);
        $col  = $locationKey[$key] % $cols;

        // Apply offset (15px spacing)
        $offsetX = ($col - 1) * 15;
        $offsetY = ($row) * 15;

        $translatedMarkers[$n]['offset_x'] = $offsetX;
        $translatedMarkers[$n]['offset_y'] = $offsetY;
    }
    unset($marker);

    // Load passive location markers from JSON file
    $locationMarkersFile = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'location_markers.json';
    $passiveMarkers = [];
    
    if (file_exists($locationMarkersFile)) {
        $locationData = json_decode(file_get_contents($locationMarkersFile), true);
        
        if ($locationData && isset($locationData['locations'])) {
            foreach ($locationData['locations'] as $location) {
                $coords = translateCoords(
                    $location['coords']['x'],
                    $location['coords']['y'],
                    $mapWidth,
                    $mapHeight,
                    $WORLD_X_MIN,
                    $WORLD_X_MAX,
                    $WORLD_Y_MIN,
                    $WORLD_Y_MAX
                );
                
                $passiveMarkers[] = [
                    'name' => $location['name'],
                    'x' => $coords['x'],
                    'y' => $coords['y'],
                    'ingame_x' => $location['coords']['x'],
                    'ingame_y' => $location['coords']['y'],
                    'icon' => $location['icon'],
                    'type' => $location['type'],
                    'description' => $location['description'],
                    'editorID' => $location['editorID'],
                    'formID' => $location['formID'],
                    'locationID' => $location['locationID'],
                ];
            }
        }
    }

?>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
    /* Override main container styles */
    main {
        padding-top: 10px;
        padding-bottom: 20px;
        padding-left: 5%;
        padding-right: 5%;
        width: 100%;
        margin: 0;
    }
    
    /* Override footer styles */
    footer {
        position: fixed;
        bottom: 0;
        width: 100%;
        height: 20px;
        background: #031633;
        z-index: 100;
    }

    /* MagicCards font import */
    @font-face {
        font-family: 'MagicCards';
        src: url('<?php echo $webRoot; ?>/ui/css/font/MagicCardsNormal.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
    }

    /* Header Styling */
    .page-header {
        text-align: center;
        margin-bottom: 30px;
        padding: 20px;
        background: #2a2a2a;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
        position: relative;
    }

    .page-header h1 {
        margin: 0;
        font-family: 'MagicCards', serif;
        word-spacing: 8px;
        font-size: 2.2em;
        color: rgb(242, 124, 17);
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
    }

    .open-new-window {
        position: absolute;
        top: 15px;
        right: 15px;
        font-size: 24px;
        cursor: pointer;
        transition: all 0.3s ease;
        padding: 8px 12px;
        border-radius: 6px;
    }

    .page-header .open-new-window:hover {
        background: rgba(242, 124, 17, 0.2);
        transform: scale(1.1);
    }

      .open-new-window-2 {
        position: absolute;
        top: 15px;
        right: 45px;
        font-size: 24px;
        cursor: pointer;
        transition: all 0.3s ease;
        padding: 8px 12px;
        border-radius: 6px;
    }

    .page-header .open-new-window-2:hover {
        background: rgba(242, 124, 17, 0.2);
        transform: scale(1.1);
    }
    .container {
        max-width: 100%;
        margin: 0 auto;
    }

    .content-wrapper {
        display: flex;
        gap: 20px;
        align-items: flex-start;
    }

    .map-section {
        order: 1;
        flex: 0 0 35%;
        min-width: 0;
    }

    .map-viewport {
        position: relative;
        width: 100%;
        height: clamp(520px, 72vh, 820px);
        overflow: hidden;
        background: #111;
        border: 3px solid rgb(242, 124, 17);
        border-radius: 8px;
        box-shadow: 0 0 20px rgba(242, 124, 17, 0.3);
        box-sizing: border-box;
        cursor: grab;
        touch-action: none;
        user-select: none;
    }

    .map-viewport.is-dragging {
        cursor: grabbing;
    }

    .sidebar-section {
        order: 3;
        flex: 0 0 20%;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .npc-list-section {
        order: 2;
        flex: 0 0 calc(45% - 40px);
        min-width: 0;
    }

    .map-container {
        position: relative;
        display: block;
        background: #1a1a1a;
        padding: 15px;
        border: 0;
        box-shadow: none;
        margin: 0;
        width: 100%;
        box-sizing: border-box;
        border-radius: 0;
        overflow: visible;
        transform-origin: 0 0;
        will-change: transform;
    }

    .map-container img {
        display: block;
        width: 100%;
        height: auto;
        border: 1px solid #4a4a4a;
        border-radius: 4px;
        pointer-events: none;
        -webkit-user-drag: none;
    }

    .map-navigation-controls {
        position: absolute;
        top: 12px;
        right: 12px;
        z-index: 500;
        display: grid;
        width: 42px;
        overflow: hidden;
        border: 1px solid rgba(242, 124, 17, 0.7);
        border-radius: 7px;
        background: rgba(24, 24, 24, 0.94);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.45);
    }

    .map-navigation-controls button,
    .map-navigation-controls output {
        display: grid;
        place-items: center;
        width: 42px;
        min-height: 38px;
        box-sizing: border-box;
        border: 0;
        border-bottom: 1px solid #454545;
        background: transparent;
        color: #f2f2f2;
        font-size: 18px;
        font-weight: 700;
    }

    .map-navigation-controls button {
        cursor: pointer;
    }

    .map-navigation-controls button:hover,
    .map-navigation-controls button:focus-visible {
        background: rgba(242, 124, 17, 0.22);
        color: #ffd2a8;
        outline: none;
    }

    .map-navigation-controls output {
        min-height: 30px;
        color: #bbb;
        font-size: 10px;
    }

    .map-navigation-controls > :last-child {
        border-bottom: 0;
    }

    .marker {
        position: absolute;
        transform: translate(-50%, -50%);
        cursor: pointer;
        z-index: 40;
    }

    .marker-dot {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        border: 2px solid white;
        box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        line-height: 1;
        z-index: 10;
        position: relative;
        transform-origin: center;
        transition: transform 0.15s ease;
    }

    .marker:hover .marker-dot {
        transform: scale(1.1);
    }

    .history-marker {
        position: absolute;
        transform: translate(-50%, -50%);
        transform-origin: center;
        border-radius: 50%;
        border: 1px solid rgba(255, 255, 255, 0.6);
        box-shadow: 0 0 5px rgba(255, 255, 255, 0.3);
        z-index: 5;
        opacity: 0.7;
        transition: opacity 0.2s ease, transform 0.15s ease;
    }

    .history-marker:hover {
        opacity: 1;
        box-shadow: 0 0 10px rgba(255, 255, 255, 0.6);
        z-index: 15;
        transform: translate(-50%, -50%) scale(1.1);
    }

    .history-marker-label {
        position: absolute;
        background: rgba(0, 0, 0, 0.9);
        color: white;
        padding: 6px 10px;
        border-radius: 4px;
        white-space: nowrap;
        font-size: 12px;
        top: calc(12px * var(--bgl-info-scale, 1));
        left: 50%;
        transform: translateX(-50%) scale(var(--bgl-info-scale, 1));
        transform-origin: top center;
        margin-top: calc(3px * var(--bgl-info-scale, 1));
        border: 1px solid rgba(255, 255, 255, 0.4);
        display: none;
        z-index: 20;
        box-shadow: 0 2px 6px rgba(0,0,0,0.4);
    }

    .history-marker:hover .history-marker-label {
        display: block;
    }

    .marker-label {
        position: absolute;
        background: rgba(0, 0, 0, 0.9);
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        white-space: nowrap;
        font-size: 14px;
        top: calc(15px * var(--bgl-info-scale, 1));
        left: 50%;
        transform: translateX(-50%) scale(var(--bgl-info-scale, 1));
        transform-origin: top center;
        margin-top: calc(5px * var(--bgl-info-scale, 1));
        border: 2px solid rgb(242, 124, 17);
        display: none;
        z-index: 20;
        box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    }

    .marker:hover {
        z-index: 300;
    }

    .marker:hover .marker-label {
        display: block;
    }

    .info-panel {
        background: #2a2a2a;
        padding: 20px;
        border-left: 4px solid rgb(242, 124, 17);
        margin: 20px 0;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
    }

    .info-panel h3 {
        color: rgb(242, 124, 17);
        font-family: 'MagicCards', serif;
        margin-top: 0;
        word-spacing: 6px;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
    }

    .info-panel strong {
        color: rgb(242, 124, 17);
    }

    .npc-list-container {
        background: #2a2a2a;
        padding: 20px;
        border-left: 4px solid rgb(242, 124, 17);
        border-radius: 8px;
        border: 1px solid #4a4a4a;
        max-height: calc(100vh - 300px);
        overflow-y: auto;
        overflow-x: hidden;
    }

    .npc-list-container h3 {
        color: rgb(242, 124, 17);
        font-family: 'MagicCards', serif;
        margin-top: 0;
        margin-bottom: 10px;
        word-spacing: 6px;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
    }

    .npc-list-header {
        position: sticky;
        top: 0;
        background: #2a2a2a;
        padding-bottom: 10px;
        z-index: 10;
    }

    .update-all-coords-btn {
        width: 100%;
        margin-top: 10px;
        background: #44ff44;
        color: #000;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: bold;
        font-size: 14px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        transition: all 0.3s ease;
    }

    .update-all-coords-btn:hover {
        background: #55ff55;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(68, 255, 68, 0.4);
    }

    .update-all-coords-btn:active {
        transform: translateY(0);
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }

    .marker-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        align-items: start;
    }

    .marker-item {
        background-color: #1a1a1a;
        padding: 10px;
        border-left: 4px solid;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
        width: 100%;
        min-width: 0;
        box-sizing: border-box;
        position: relative;
        cursor: pointer;
        transition: border-color 0.2s ease, background-color 0.2s ease;
    }

    .marker-item:hover,
    .marker-item:focus-visible {
        background-color: #202020;
        border-color: rgb(242, 124, 17);
        outline: none;
    }

    .marker-item::before {
        display: none;
    }

    .marker-item > * {
        position: relative;
        z-index: 2;
    }

    .marker-item-color {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        vertical-align: middle;
        margin-right: 0;
        border: 1px solid white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        flex: 0 0 auto;
    }

    .marker-card-identity {
        display: grid;
        grid-template-columns: 44px minmax(0, 1fr);
        gap: 8px;
        align-items: center;
        margin-bottom: 8px;
    }

    .marker-card-portrait {
        width: 44px;
        height: 44px;
        border: 1px solid #555;
        border-radius: 6px;
        object-fit: cover;
        background: #111;
    }

    .marker-item h4 {
        margin: 0;
        color: rgb(242, 124, 17);
        font-family: 'MagicCards', serif;
        display: flex;
        align-items: flex-start;
        gap: 6px;
        min-width: 0;
        font-size: 13px;
        line-height: 1.2;
    }

    .marker-npc-events,
    .marker-map-focus {
        display: block;
        padding: 0;
        border: 0;
        background: transparent;
        color: inherit;
        cursor: pointer;
        font: inherit;
    }

    .marker-npc-events {
        flex: 1 1 auto;
        overflow: hidden;
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
    }

    .marker-map-focus {
        margin-left: auto;
        flex: 0 0 auto;
        font-size: 16px;
        line-height: 1;
        transform-origin: center;
        transition: color 0.15s ease, transform 0.15s ease;
    }

    .marker-npc-events:hover,
    .marker-npc-events:focus-visible {
        color: #fff;
        outline: none;
        text-decoration: underline;
    }

    .marker-map-focus:hover,
    .marker-map-focus:focus-visible {
        color: #fff;
        outline: none;
        text-decoration: none;
        transform: scale(1.1);
    }

    #mapImage {
        opacity: 1;
    }

    .marker-card-row-label {
        margin: 6px 0 3px;
        color: #9a9aa2;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .marker-card-activity {
        display: grid;
        grid-template-columns: 28px minmax(0, 1fr);
        gap: 8px;
        align-items: center;
        margin: 2px 0 8px;
        padding: 7px 8px;
        border: 1px solid #3f3f46;
        border-radius: 6px;
        background: #222227;
    }

    .marker-card-activity-icon {
        font-size: 20px;
        line-height: 1;
        text-align: center;
    }

    .marker-card-activity-copy {
        min-width: 0;
    }

    .marker-card-activity-title {
        color: #fff;
        font-size: 11px;
        font-weight: 700;
    }

    .marker-card-activity-summary {
        margin-top: 2px;
        overflow: hidden;
        color: #aaaab2;
        font-size: 10px;
        line-height: 1.3;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .marker-card-actions {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto;
        gap: 4px;
    }

    .marker-card-actions .marker-action-btn,
    .marker-card-actions .marker-action-btn-trans {
        width: 100%;
        min-width: 0;
        padding: 6px 4px;
        font-size: 10px;
    }

    .marker-card-toggles {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 4px;
        margin-top: 6px;
    }

    .marker-setting-button {
        position: relative;
        min-width: 0;
        min-height: 30px;
        padding: 4px;
        border: 1px solid #4d4d4d;
        border-radius: 6px;
        background: #333;
        color: #fff;
        font-size: 15px;
        line-height: 1;
        cursor: pointer;
        transition: background-color 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
    }

    .marker-setting-button.is-enabled,
    .marker-setting-button.is-disabled {
        background: #333 !important;
        border-color: #4d4d4d !important;
    }

    .marker-setting-button::after {
        content: "";
        position: absolute;
        top: 5px;
        right: 5px;
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: #a95059;
    }

    .marker-setting-button.is-enabled::after {
        background: #4f9b68;
    }

    .marker-setting-button:hover,
    .marker-setting-button:focus-visible {
        border-color: rgb(242, 124, 17) !important;
        outline: none;
        transform: translateY(-1px);
    }

    .marker-setting-button:disabled {
        cursor: wait;
        opacity: 0.65;
        transform: none;
    }

    .marker-item a {
        color: white;
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .marker-item a:hover {
        color: rgb(242, 124, 17);
    }

    .toggle-label-inline {
        display: flex;
        align-items: center;
        cursor: pointer;
        gap: 6px;
        font-size: 12px;
        color: #ddd;
        transition: color 0.3s ease;
        padding: 6px 10px;
        background: rgba(0, 0, 0, 0.3);
        border-radius: 6px;
        border: 1px solid #333;
    }

    .toggle-label-inline:hover {
        color: rgb(242, 124, 17);
        background: rgba(242, 124, 17, 0.1);
    }

    .bgl-instructions-box {
        background: #2a2a2a;
        padding: 15px;
        border-left: 4px solid rgb(242, 124, 17);
        border-radius: 8px;
        border: 1px solid #4a4a4a;
        margin-bottom: 5px;
    }

    .bgl-instructions-box h3 {
        color: rgb(242, 124, 17);
        font-family: 'MagicCards', serif;
        margin-top: 0;
        margin-bottom: 12px;
        word-spacing: 6px;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        font-size: 1.2em;
    }

    .bgl-instructions-content {
        color: #ddd;
        font-size: 13px;
        line-height: 1.6;
    }

    .instruction-section {
        margin-bottom: 12px;
        padding-bottom: 10px;
        border-bottom: 1px solid #3a3a3a;
    }

    .instruction-section:last-of-type {
        border-bottom: none;
    }

    .instruction-section strong {
        color: rgb(242, 124, 17);
        display: block;
        margin-bottom: 6px;
    }

    .instruction-section ul,
    .instruction-section ol {
        margin: 6px 0;
        padding-left: 25px;
    }

    .instruction-section li {
        margin: 4px 0;
    }

    .instruction-section em {
        color: #aaa;
        font-style: italic;
    }

    .instruction-note {
        background: rgba(242, 124, 17, 0.1);
        padding: 8px 12px;
        border-radius: 6px;
        border-left: 3px solid rgb(242, 124, 17);
        margin-top: 10px;
        font-size: 12px;
    }

    .instruction-note strong {
        color: rgb(242, 124, 17);
    }

    .toggle-instructions-btn {
        width: 100%;
        margin-top: 10px;
        background: #3a3a3a;
        color: #ddd;
        border: none;
        padding: 8px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
        font-size: 12px;
        transition: all 0.3s ease;
    }

    .toggle-instructions-btn:hover {
        background: #4a4a4a;
        color: rgb(242, 124, 17);
    }

    .bgl-instructions-box.collapsed .bgl-instructions-content {
        display: none;
    }

    .bgl-action-toolbar {
        display: flex;
        margin: 0 0 12px;
    }

    .bgl-page-tabs {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
        max-width: 900px;
        margin: 0 auto 20px;
        padding: 8px;
        background: #1d1d1d;
        border: 1px solid #3d3d3d;
        border-radius: 8px;
    }

    .bgl-page-tab {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 42px;
        padding: 8px 12px;
        color: #fff !important;
        background: #2d2d2d;
        border: 1px solid #474747;
        border-radius: 6px;
        font-weight: 700;
        text-align: center;
        text-decoration: none;
        box-sizing: border-box;
    }

    .bgl-page-tab:hover {
        color: #fff;
        background: #363636;
        border-color: #777;
        text-decoration: none;
    }

    .bgl-page-tab.active {
        color: #fff;
        background: rgba(169, 81, 9, 0.28);
        border-color: rgb(242, 124, 17);
        box-shadow: inset 0 -2px 0 rgb(242, 124, 17);
    }

    .bgl-page-panel[hidden] {
        display: none !important;
    }

    .bgl-action-button {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid rgb(242, 124, 17);
        border-radius: 6px;
        background: rgb(242, 124, 17);
        color: #121212;
        cursor: pointer;
        font-weight: 700;
    }

    .bgl-action-button:hover {
        background: #ff9138;
    }

    .bgl-modal {
        display: none;
        position: fixed;
        z-index: 10020;
        inset: 0;
        align-items: center;
        justify-content: center;
        padding: 18px;
        background: rgba(0, 0, 0, 0.78);
        box-sizing: border-box;
    }

    .bgl-modal.open {
        display: flex;
    }

    .bgl-modal-dialog {
        width: min(1050px, 100%);
        max-height: calc(100vh - 36px);
        overflow-y: auto;
        padding: 20px;
        border: 1px solid #555;
        border-radius: 10px;
        background: #262626;
        box-shadow: 0 18px 60px rgba(0, 0, 0, 0.65);
        box-sizing: border-box;
    }

    .bgl-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 14px;
        padding-bottom: 12px;
        border-bottom: 1px solid #444;
    }

    .bgl-modal-header h3 {
        margin: 0;
    }

    .bgl-modal-close {
        width: 36px;
        height: 36px;
        border: 1px solid #555;
        border-radius: 6px;
        background: #333;
        color: #fff;
        cursor: pointer;
        font-size: 22px;
        line-height: 1;
    }

    .bgl-modal-close:hover {
        border-color: rgb(242, 124, 17);
        color: rgb(242, 124, 17);
    }

    .bgl-create-npc-notice {
        margin-bottom: 16px;
        padding: 11px 13px;
        border: 1px solid #9b6a24;
        border-radius: 6px;
        background: rgba(155, 106, 36, 0.2);
        color: #f5d59d;
        line-height: 1.45;
    }

    .bgl-create-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(220px, 1fr));
        gap: 14px;
        margin-top: 16px;
    }

    .bgl-create-field {
        min-width: 0;
    }

    .bgl-create-field-wide {
        grid-column: 1 / -1;
    }

    .bgl-create-field label {
        display: block;
        margin-bottom: 8px;
        color: #f2c48f;
        font-weight: 600;
    }

    .bgl-create-field input,
    .bgl-create-field select,
    .bgl-create-field textarea {
        width: 100%;
        box-sizing: border-box;
        padding: 10px 12px;
        border: 1px solid #444;
        border-radius: 8px;
        background: #171717;
        color: #f5f5f5;
    }

    .bgl-create-field textarea {
        resize: vertical;
    }

    .bgl-create-advanced {
        margin-top: 18px;
        border: 1px solid #3b3b3b;
        border-radius: 8px;
        background: #1b1b1b;
    }

    .bgl-create-advanced summary {
        padding: 12px 14px;
        color: #f2c48f;
        font-weight: 700;
        cursor: pointer;
        user-select: none;
    }

    .bgl-create-advanced[open] summary {
        border-bottom: 1px solid #333;
    }

    .bgl-create-advanced .bgl-create-form-grid {
        margin: 0;
        padding: 14px;
    }

    body.bgl-modal-open {
        overflow: hidden;
    }

    @media (max-width: 820px) {
        .bgl-create-form-grid {
            grid-template-columns: 1fr;
        }

        .bgl-modal {
            padding: 8px;
        }

        .bgl-modal-dialog {
            max-height: calc(100vh - 16px);
            padding: 14px;
        }

        .bgl-page-tabs {
            gap: 5px;
            padding: 5px;
        }

        .bgl-page-tab {
            min-height: 38px;
            padding: 7px 5px;
            font-size: 12px;
        }
    }

    .toggle-checkbox {
        width: 36px;
        height: 18px;
        appearance: none;
        background: #444;
        border-radius: 9px;
        position: relative;
        cursor: pointer;
        transition: background 0.3s ease;
        border: 1px solid #666;
        flex-shrink: 0;
    }

    .toggle-checkbox:checked {
        background: rgb(242, 124, 17);
    }

    .toggle-checkbox::before {
        content: '';
        position: absolute;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: white;
        top: 1px;
        left: 2px;
        transition: transform 0.3s ease;
    }

    .toggle-checkbox:checked::before {
        transform: translateX(18px);
    }

    .toggle-text {
        font-weight: 500;
        white-space: nowrap;
    }

    .map-controls {
        text-align: center;
        margin: 0 0 15px 0;
        padding: 15px;
        background: #2a2a2a;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
    }

    .map-controls button {
        background: rgb(242, 124, 17);
        color: #000;
        border: none;
        padding: 8px 12px;
        margin: 3px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
        font-size: 12px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }

    .map-controls button:hover {
        background: rgb(255, 140, 30);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(242, 124, 17, 0.4);
    }

    .map-controls button:active {
        transform: translateY(0);
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }

    .map-controls span {
        color: rgb(242, 124, 17);
        margin: 5px;
        font-weight: bold;
        font-size: 14px;
        display: block;
        margin-top: 10px;
    }

    .bgl-settings-card {
        display: grid;
        gap: 10px;
        margin-bottom: 15px;
        padding: 12px;
        background: #2a2a2a;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
    }

    .bgl-settings-card h3 {
        color: rgb(242, 124, 17);
        margin: 0;
        font-size: 14px;
        font-family: 'MagicCards', serif;
        word-spacing: 5px;
    }

    .bgl-settings-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 92px;
        gap: 10px;
        align-items: center;
    }

    .bgl-settings-row label {
        color: #ddd;
        font-size: 13px;
        font-weight: bold;
    }

    .bgl-settings-row input[type="number"] {
        width: 100%;
        box-sizing: border-box;
        background: #1f1f1f;
        border: 1px solid #555;
        border-radius: 6px;
        color: #fff;
        padding: 8px 10px;
        font-size: 14px;
    }

    .bgl-settings-help {
        color: #aaa;
        font-size: 12px;
        line-height: 1.35;
    }

    .bgl-settings-save {
        background: rgb(242, 124, 17);
        color: #111;
        border: none;
        padding: 9px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
    }

    .bgl-settings-save:hover {
        background: rgb(255, 145, 38);
    }

    .bgl-bulk-settings {
        display: grid;
        gap: 8px;
        padding-top: 10px;
        border-top: 1px solid #4a4a4a;
    }

    .bgl-bulk-settings-title {
        color: #ddd;
        font-size: 13px;
        font-weight: bold;
    }

    .bgl-bulk-settings-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 8px;
    }

    .bgl-bulk-toggle {
        min-height: 36px;
        padding: 7px 9px;
        border: 1px solid #555;
        border-radius: 6px;
        background: #353535;
        color: #fff;
        cursor: pointer;
        font-size: 12px;
        font-weight: bold;
    }

    .bgl-bulk-toggle:hover:not(:disabled) {
        border-color: rgb(242, 124, 17);
        background: #404040;
    }

    .bgl-bulk-toggle:disabled {
        cursor: not-allowed;
        opacity: 0.55;
    }

    .bgl-settings-message {
        padding: 8px 10px;
        border-radius: 6px;
        font-size: 12px;
        border: 1px solid rgba(68, 255, 68, 0.35);
        background: rgba(68, 255, 68, 0.1);
        color: #c8ffc8;
    }

    .bgl-settings-message.error {
        border-color: rgba(255, 80, 80, 0.45);
        background: rgba(255, 80, 80, 0.12);
        color: #ffb8b8;
    }

    img.thumb {
        max-width: 100px;
        border-radius: 4px;
        margin-top: 5px;
    }

    .marker-action-btn {
        background: rgb(242, 124, 17);
        color: #000;
        border: none;
        padding: 8px 14px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
        font-size: 12px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }

    .marker-action-btn-trans {
        background: rgba(242, 124, 17,0);
        color: #000;
        border: none;
        padding: 8px 14px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
        font-size: 12px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }

    .marker-action-btn:hover {
        background: rgb(255, 140, 30);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(242, 124, 17, 0.4);
    }

    .marker-action-btn:active {
        transform: translateY(0);
    }

    /* Passive Location Markers */
    .location-marker {
        position: absolute;
        transform: translate(-50%, -50%);
        cursor: pointer;
        z-index: 5;
        border: none;
    }

    .location-marker-icon {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0.8;
        transition: all 0.3s ease;
        border: none;
        transform-origin: center;
    }

    .location-marker-icon img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.5));
        border: none;
    }

    .location-marker-caption {
        position: absolute;
        top: 30px;
        left: 50%;
        transform: translateX(-50%);
        transform-origin: top center;
        color: #ece7dc;
        font-size: 10px;
        font-weight: 600;
        line-height: 1;
        white-space: nowrap;
        pointer-events: none;
        text-shadow:
            0 1px 2px #000,
            0 0 5px rgba(0, 0, 0, 0.95);
        opacity: 0.88;
    }

    .location-marker:hover .location-marker-icon {
        opacity: 1;
        transform: scale(1.1);
    }

    .location-marker:hover .location-marker-icon img {
        filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.8));
    }

    .location-marker-label {
        position: absolute;
        background: rgba(0, 0, 0, 0.9);
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        white-space: nowrap;
        font-size: 14px;
        top: calc(15px * var(--bgl-info-scale, 1));
        left: 50%;
        transform: translateX(-50%) scale(var(--bgl-info-scale, 1));
        transform-origin: top center;
        margin-top: calc(5px * var(--bgl-info-scale, 1));
        border: none;
        display: none;
        z-index: 30;
        box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    }

    .location-marker:hover {
        z-index: 20;
    }

    .location-marker:hover .location-marker-label {
        display: block;
    }

    .location-marker:hover .location-marker-caption {
        opacity: 0;
    }

    .location-marker-label .location-name {
        font-weight: bold;
        font-size: 14px;
        margin-bottom: 5px;
        color: rgb(242, 124, 17);
    }

    .location-marker-label .location-desc {
        font-size: 11px;
        color: #bbb;
        font-style: italic;
        margin-top: 5px;
    }

    .location-marker-label .location-coords {
        font-size: 10px;
        color: #888;
        margin-top: 6px;
        border-top: 1px solid #444;
        padding-top: 5px;
    }

    /* Scrollbar styling for NPC list */
    .npc-list-container::-webkit-scrollbar {
        width: 10px;
    }

    .npc-list-container::-webkit-scrollbar-track {
        background: #1a1a1a;
        border-radius: 5px;
    }

    .npc-list-container::-webkit-scrollbar-thumb {
        background: rgb(242, 124, 17);
        border-radius: 5px;
    }

    .npc-list-container::-webkit-scrollbar-thumb:hover {
        background: rgb(255, 140, 30);
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .content-wrapper {
            flex-direction: column;
        }

        .map-section {
            flex: 0 0 100%;
        }

        .sidebar-section {
            flex: 0 0 100%;
            width: 100%;
        }

        .npc-list-section {
            flex: 0 0 100%;
            width: 100%;
        }

        .npc-list-container {
            max-height: 600px;
        }
    }

    @media (max-width: 768px) {
        main {
            padding-left: 5%;
            padding-right: 5%;
        }

        .page-header h1 {
            font-size: 1.5em;
        }

        .npc-list-container {
            max-height: 400px;
        }

        .map-viewport {
            height: 62vh;
            min-height: 420px;
        }
    }

    @keyframes pulse {
        0% {
            transform: scale(1);
            opacity: 1;
        }
        50% {
            transform: scale(2);
            opacity: 0.75;
        }
        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    .pulsing {
        animation: pulse 1s ease-in-out infinite;
        z-index:100000
    }

</style>

<main>
    <nav class="bgl-page-tabs" aria-label="Background Life sections">
        <a
            class="bgl-page-tab <?php echo $activeBglTab === 'background' ? 'active' : ''; ?>"
            href="<?php echo htmlspecialchars($backgroundPageTabUrl); ?>"
            target="_parent"
            <?php echo $activeBglTab === 'background' ? 'aria-current="page"' : ''; ?>>
            🌍 Background Life
        </a>
        <a
            class="bgl-page-tab <?php echo $activeBglTab === 'history' ? 'active' : ''; ?>"
            href="<?php echo htmlspecialchars($historyPageTabUrl); ?>"
            target="_parent"
            <?php echo $activeBglTab === 'history' ? 'aria-current="page"' : ''; ?>>
            📚 History
        </a>
        <a
            class="bgl-page-tab <?php echo $activeBglTab === 'rumors' ? 'active' : ''; ?>"
            href="<?php echo htmlspecialchars($rumorsPageTabUrl); ?>"
            target="_parent"
            <?php echo $activeBglTab === 'rumors' ? 'aria-current="page"' : ''; ?>>
            📰 Rumors
        </a>
    </nav>

    <section class="bgl-page-panel" id="bgl-tab-background" <?php echo $activeBglTab === 'background' ? '' : 'hidden'; ?>>
    <div class="container">
        <div class="content-wrapper">
            <div class="map-section">
                <div
                    class="map-viewport"
                    id="bglMapViewport"
                    tabindex="0"
                    aria-label="Interactive Skyrim map. Drag to pan and use the mouse wheel or controls to zoom.">
                <div class="map-container" id="bglMapCanvas">
                    <img src="<?php echo $mapImageUrl; ?>" alt="Skyrim Map" id="mapImage">

            <?php
                // Render NPC markers
                foreach ($translatedMarkers as $marker) {
                    // Calculate position as percentage for responsive scaling
                    $percentX = ($marker['x'] / $mapWidth) * 100;
                    $percentY = ($marker['y'] / $mapHeight) * 100;
                    // Apply grid offset
                    $offsetX = $marker['offset_x'];
                    $offsetY = $marker['offset_y'];
                    $markerName = htmlspecialchars($marker['name'], ENT_QUOTES, 'UTF-8');

                    echo '<div class="marker" style="left: ' . $percentX . '%; top: ' . $percentY . '%; transform: translate(calc(-50% + ' . $offsetX . 'px), calc(-50% + ' . $offsetY . 'px));">';
                    echo '<div class="marker-dot" id="mkr_' . $marker['id'] . '" data-npc-name="' . $markerName . '" role="button" tabindex="0" title="View recent events for ' . $markerName . '" aria-label="View recent events for ' . $markerName . '" style="width: ' . ($marker['size'] * 2) . 'px; height: ' . ($marker['size'] * 2) . 'px; background-color: ' . $marker['color'] . '; opacity: 0.8;">';
                    echo htmlspecialchars($marker['last_action_icon'], ENT_QUOTES, 'UTF-8');
                    echo '</div>';
                    echo '<div class="marker-label">' . PHP_EOL;
                    echo "<a style='color:white;text-decoration:none' href='#dtl_{$marker["id"]}'>{$marker["name"]} &nbsp; ↗️</a></br>";
                    echo '<small>(' . $marker['x'] . ', ' . $marker['y'] . '),' . $marker['tag'] . '</small>';
                    echo '<img class="thumb" src="' . $marker['figure'] . '" />';
                    echo '<br/><small>Last reported:' . $marker['last_report'] . '</small>';
                    echo '<br/><small>Last tracked:' . $marker['last_pos_ts'] . '</small>';
                    echo '<br/><small>Last activity: ' . htmlspecialchars($marker['last_action_icon'] . ' ' . $marker['last_action_label'], ENT_QUOTES, 'UTF-8') . '</small>';
                    echo '</div>';
                    echo '</div>' . PHP_EOL;
                    
                    // Render history markers
                    if (!empty($marker['coords_history'])) {
                        $size_modifier=2;
                        foreach ($marker['coords_history'] as $index => $histCoord) {
                            if ($histCoord['x']== $marker['x'] && $histCoord['y']== $marker['y'] )
                                continue; // Skip same place
                            
                            $histPercentX = ($histCoord['x'] / $mapWidth) * 100;
                            $histPercentY = ($histCoord['y'] / $mapHeight) * 100;
                            // Make history markers smaller: 5px radius instead of 10px
                            $size_modifier+=0.5;
                            $histSize = round($size_modifier,0);

                            
                            echo '<div class="history-marker" style="left: ' . $histPercentX . '%; top: ' . $histPercentY . '%; width: ' . ($histSize * 2) . 'px; height: ' . ($histSize * 2) . 'px; background-color: ' . $marker['color'] . '; opacity: 0.3;">';
                            echo '<div class="history-marker-label">' . PHP_EOL;
                            echo "<strong>" . $marker['name'] . "</strong><br/>";
                            echo "In-game: (" . $histCoord['ingame_x'] . ", " . $histCoord['ingame_y'] . ")<br/>";
                            if (!empty($histCoord['location'])) {
                                echo "Location: " . $histCoord['location'] . "<br/>";
                            }
                            echo "Tracked: " . $histCoord['last_updated'] . "<br/>";
                            echo "</div>";
                            echo '</div>' . PHP_EOL;
                        }
                    }
                }

                // Render passive location markers
                foreach ($passiveMarkers as $location) {
                    $percentX = ($location['x'] / $mapWidth) * 100;
                    $percentY = ($location['y'] / $mapHeight) * 100;
                    $iconPath = $webRoot . '/ui/images/map icons/' . $location['icon'];

                    echo '<div class="location-marker" style="left: ' . $percentX . '%; top: ' . $percentY . '%;">';
                    echo '<div class="location-marker-icon">';
                    echo '<img src="' . htmlspecialchars($iconPath) . '" alt="' . htmlspecialchars($location['name']) . '" />';
                    echo '</div>';
                    echo '<div class="location-marker-caption">' . htmlspecialchars($location['name']) . '</div>';
                    echo '<div class="location-marker-label">';
                    echo '<div class="location-name">' . htmlspecialchars($location['name']) . '</div>';
                    echo '<div class="location-desc">' . htmlspecialchars($location['description']) . '</div>';
                    echo '<div class="location-coords">';
                    echo 'Type: ' . htmlspecialchars($location['type']) . '<br/>';
                    echo 'Coords: ' . $location['ingame_x'] . ', ' . $location['ingame_y'] . '<br/>';
                    echo 'FormID: ' . htmlspecialchars($location['formID']);
                    echo '</div>';
                    echo '</div>';
                    echo '</div>' . PHP_EOL;
                }
            ?>
                </div>
                <div class="map-navigation-controls" role="group" aria-label="Map controls">
                    <button type="button" data-map-zoom-in aria-label="Zoom in" title="Zoom in">+</button>
                    <output id="bglMapZoomValue" aria-live="polite">100%</output>
                    <button type="button" data-map-zoom-out aria-label="Zoom out" title="Zoom out">&minus;</button>
                    <button type="button" data-map-reset aria-label="Fit Skyrim in view" title="Fit Skyrim in view">&#8962;</button>
                </div>
                </div>
            </div>

            <div class="sidebar-section">
                <div class="bgl-instructions-box collapsed">
                    <h3>📖 How Background Life Works</h3>
                    <button class="toggle-instructions-btn" onclick="toggleInstructions()">Show Instructions</button>
                    <div class="bgl-instructions-content">
                        <div class="instruction-section">
                            <strong>Getting Started:</strong>
                            <ol>
                                <li><b>Make sure you the game is running and that the NPC is not a current follower (dismiss them).</b></li>
                                <li>Look at an NPC in-game and press the <strong>Roleplay Wheel Hotkey (CHIM MCM Menu)</strong> to add them to Background Life <strong>You must save your game for changes to take effect</strong></li>
                                <li>You can either:
                                    <ul>
                                        <li>Talk to the NPC and give commands like: <em>"Go to Riften and then to Whiterun to do X and Y"</em></li>
                                        <li>Wait for the configured trigger period (Global Settings, default: 5 in-game days) for them to automatically trigger background life.</li>
                                        <li>Define a [Life Goals] Section at NPC's profile goals, and let them wander according to their objectives.</li>
                                    </ul>
                                </li>
                                <li>They shall now travel around Skyrim.</li>
                                <li>LLM used for BgL is the one defined as CORE_CONNECTOR_DIRECTOR</li>
                            </ol>
                        </div>
                        
                        <div class="instruction-section">
                            <strong>NPC Settings:</strong>
                            <ul>
                                <li><strong>🎮 Auto Actions:</strong> Based on the configured trigger period (Global Settings, default: 24 in-game hours), NPC generates inner thoughts. When enabled, they can autonomously travel to new locations. When disabled, only thoughts are generated.</li>
                                <li><strong>📍 Hourly Tracking:</strong> Tracks NPC coordinates every in-game hour (default is daily) for detailed movement history.</li>
                            </ul>
                        </div>
                        
                        <div class="instruction-section">
                            <strong>Control Buttons:</strong>
                            <ul>
                                <li><strong>Trigger Action:</strong> Forces the NPC to generate an action immediately (may move or stay based on AI decision).</li>
                                <li><strong>Send Letter:</strong> Forces the NPC to send you a letter about their current activities. You will need to be in a town so the courier can deliver it.</li>
                                <li><strong>Update All NPC Coords:</strong> Refreshes position tracking for all Background Life NPC.s</li>
                            </ul>
                        </div>
                        
                        <div class="instruction-note">
                            <strong>💡 Note:</strong> Events are triggered automatically based on the configured trigger period (Global Settings, default: 24 in-game hours). The buttons are mainly for testing or forcing immediate updates.
                        </div>
                    </div>
                </div>
                <form id="background-life-settings" class="bgl-settings-card" method="post">
                    <input type="hidden" name="action" value="save_bgl_settings">
                    <h3>Background Life Settings</h3>
                    <?php if ($bglSettingsFlash): ?>
                        <div class="bgl-settings-message <?php echo $bglSettingsFlash['type'] === 'error' ? 'error' : ''; ?>">
                            <?php echo htmlspecialchars($bglSettingsFlash['message']); ?>
                        </div>
                    <?php endif; ?>
                    <div class="bgl-settings-row">
                        <label for="bglTriggerHours">Hours Cooldown</label>
                        <input id="bglTriggerHours" type="number" name="bgl_trigger_hours" min="1" max="720" step="0.1" value="<?php echo htmlspecialchars((string) $bglTriggerHours); ?>">
                    </div>
                    <div class="bgl-settings-help">Controls how many in-game hours pass before eligible Background Life NPCs automatically run their next update.</div>
                    <button type="submit" class="bgl-settings-save">Save</button>
                    <div class="bgl-bulk-settings">
                        <div class="bgl-bulk-settings-title">All Background Life NPCs</div>
                        <div class="bgl-bulk-settings-grid">
                            <?php
                            $bglBulkControls = [
                                'bg_life_commands' => 'Automatic Actions',
                                'bg_life_letters' => 'Letters',
                                'gps_track' => 'Tracking',
                            ];
                            foreach ($bglBulkControls as $setting => $label):
                                $enabledCount = $bglBulkState[$setting];
                                $totalCount = $bglBulkState['total'];
                                $allEnabled = $totalCount > 0 && $enabledCount === $totalCount;
                                $buttonText = ($allEnabled ? 'Disable All ' : 'Enable All ') . $label;
                            ?>
                                <button
                                    type="button"
                                    class="bgl-bulk-toggle"
                                    data-setting="<?php echo htmlspecialchars($setting, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-enabled-count="<?php echo $enabledCount; ?>"
                                    data-total="<?php echo $totalCount; ?>"
                                    title="<?php echo $enabledCount; ?> of <?php echo $totalCount; ?> enabled"
                                    onclick="toggleAllBgLifeSettings(this)"
                                    <?php echo $totalCount === 0 ? 'disabled' : ''; ?>><?php echo htmlspecialchars($buttonText); ?></button>
                            <?php endforeach; ?>
                        </div>
                        <div class="bgl-settings-help">Enable or disable each rule for every NPC currently enrolled in Background Life.</div>
                    </div>
                </form>
                <div class="bgl-settings-card">
                    <h3>📍 NPC Markers</h3>
                    <div style="color: #bbb; font-size: 13px; padding-bottom: 10px; border-bottom: 1px solid #4a4a4a;">
                        <strong>Tracked NPCs:</strong> <?php echo sizeof($translatedMarkers); ?><br/>
                        <strong>Current Ingame Date:</strong> <?php echo $currentDate?><br/>
                        <em style="color: #ffa500;">For traveling to work you must click "Send All Locations" in the CHIM MCM under Tools to add them to Background Life<br/></em>
                    </div>
                    <label class="toggle-label-inline" style="margin-top:8px;" title="When enabled, shows all NPCs that have tracked coordinates, regardless of Background Life status">
                        <input type="checkbox" class="toggle-checkbox" id="showAllCoordsChk"
                            <?php echo $showAllCoords ? 'checked' : ''; ?>
                            onchange="toggleShowAllCoords(this.checked)">
                        <span class="toggle-text">🗺️ Show all NPCs with coords</span>
                    </label>
                    <button onclick="updateAllCoords()" class="update-all-coords-btn">📍 Update All NPC Coords</button>
                </div>
                <div class="bgl-action-toolbar">
                    <button type="button" class="chim-btn-primary bgl-action-button" onclick="openCreateNpcModal()">Create NPC</button>
                </div>
            </div>
            <div class="npc-list-section">
                <div class="npc-list-container">
                    <div class="marker-list">
                        <?php foreach ($translatedMarkers as $marker) {?>
                            <div
                                id="dtl_<?php echo $marker['id'] ?>"
                                class="marker-item"
                                data-npc-name="<?php echo htmlspecialchars($marker['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-bgl-enrolled="<?php echo $marker['background_life_enabled'] ? '1' : '0'; ?>"
                                title="View recent events for <?php echo htmlspecialchars($marker['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                style="border-left-color:<?php echo $marker['color']; ?>;">
                                <div class="marker-card-identity">
                                    <img class="marker-card-portrait" src="<?php echo htmlspecialchars($marker['figure'], ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy">
                                    <h4>
                                        <span class="marker-item-color" style="background-color:                                                                                                                                                                         <?php echo $marker['color']; ?>;"></span>
                                        <span class="marker-npc-events" data-npc-events role="button" tabindex="0"><?php echo $marker['name']; ?></span>
                                        <span class="marker-map-focus" data-map-focus role="button" tabindex="0" onclick="event.stopPropagation(); pulseAnimation('mkr_<?php echo $marker['id'] ?>')" aria-label="Show <?php echo htmlspecialchars($marker['name'], ENT_QUOTES, 'UTF-8'); ?> on map" title="Show on map">👀</span>
                                        <span class="marker-map-focus" data-map-focus role="button" tabindex="0" onclick="window.open('https://gamemap.uesp.net/sr/?world=skyrim&layer=day&x=<?php echo $marker['ingame_x'] ?>&y=<?php echo $marker['ingame_y'] ?>&zoom=8', '_blank'); event.stopPropagation();" aria-label="Show <?php echo htmlspecialchars($marker['name'], ENT_QUOTES, 'UTF-8'); ?> on map" title="UESP Map">🗺️</span>
                                    </h4>
                                </div>
                                <div class="marker-card-activity">
                                    <span class="marker-card-activity-icon" aria-hidden="true"><?php echo htmlspecialchars($marker['last_action_icon'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <div class="marker-card-activity-copy">
                                        <div class="marker-card-activity-title">Last activity: <?php echo htmlspecialchars($marker['last_action_label'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="marker-card-activity-summary" title="<?php echo htmlspecialchars($marker['last_activity'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($marker['last_activity'] !== '' ? $marker['last_activity'] : 'No recent activity recorded.', ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                </div>
                                <div class="marker-card-row-label">Actions</div>
                                <div class="marker-card-actions">
                                    <button onclick="requestAction('<?php echo addslashes($marker['name']); ?>')" class="marker-action-btn" title="Trigger a Background Life action for <?php echo htmlspecialchars($marker['name'], ENT_QUOTES, 'UTF-8'); ?>">Trigger Action</button>
                                    <button onclick="requestReporting('<?php echo addslashes($marker['name']); ?>')" class="marker-action-btn" title="Send a letter from <?php echo htmlspecialchars($marker['name'], ENT_QUOTES, 'UTF-8'); ?>" style="background: #4488ff;">Send Letter</button>
                                    <button onclick="updateCoords('<?php echo addslashes($marker['name']); ?>')" title="Request coords update now" class="marker-action-btn-trans" style="border: 2px solid #00ff00; background: #44ff44;">📍</button>
                                </div>
                                <div class="marker-card-row-label">Rules</div>
                                <div class="marker-card-toggles">
                                    <button type="button"
                                            class="marker-setting-button <?php echo $marker['bg_life_commands'] ? 'is-enabled' : 'is-disabled'; ?>"
                                            aria-label="Automatic actions: <?php echo $marker['bg_life_commands'] ? 'enabled' : 'disabled'; ?>"
                                            aria-pressed="<?php echo $marker['bg_life_commands'] ? 'true' : 'false'; ?>"
                                            title="Automatic actions: <?php echo $marker['bg_life_commands'] ? 'enabled' : 'disabled'; ?>"
                                            data-label="Automatic actions"
                                            data-enabled="<?php echo $marker['bg_life_commands'] ? '1' : '0'; ?>"
                                            data-npc-id="<?php echo $marker['id']; ?>"
                                            data-setting="bg_life_commands"
                                            onclick="toggleBgLifeSetting(this)">🎮</button>
                                    <button type="button"
                                            class="marker-setting-button <?php echo $marker['bg_life_letters'] ? 'is-enabled' : 'is-disabled'; ?>"
                                            aria-label="Automatic letters: <?php echo $marker['bg_life_letters'] ? 'enabled' : 'disabled'; ?>"
                                            aria-pressed="<?php echo $marker['bg_life_letters'] ? 'true' : 'false'; ?>"
                                            title="Automatic letters: <?php echo $marker['bg_life_letters'] ? 'enabled' : 'disabled'; ?>"
                                            data-label="Automatic letters"
                                            data-enabled="<?php echo $marker['bg_life_letters'] ? '1' : '0'; ?>"
                                            data-npc-id="<?php echo $marker['id']; ?>"
                                            data-setting="bg_life_letters"
                                            onclick="toggleBgLifeSetting(this)">✉️</button>
                                    <button type="button"
                                            class="marker-setting-button <?php echo $marker['gps_track'] ? 'is-enabled' : 'is-disabled'; ?>"
                                            aria-label="Hourly tracking: <?php echo $marker['gps_track'] ? 'enabled' : 'disabled'; ?>"
                                            aria-pressed="<?php echo $marker['gps_track'] ? 'true' : 'false'; ?>"
                                            title="Hourly tracking: <?php echo $marker['gps_track'] ? 'enabled' : 'disabled'; ?>"
                                            data-label="Hourly tracking"
                                            data-enabled="<?php echo $marker['gps_track'] ? '1' : '0'; ?>"
                                            data-npc-id="<?php echo $marker['id']; ?>"
                                            data-setting="gps_track"
                                            onclick="toggleBgLifeSetting(this)">📍</button>
                                </div>
                            </div>
                        <?php }?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <span class="open-new-window" onclick="openInNewWindow()" title="Open in new window">↗️</span>
    <span class="open-new-window-2" onclick="location.href='mapview.php'" title="Refresh">🔄</span>
    </section>
    <script>
        // NPC Diary Data - embedded directly in page
        window.npcDiaryData = <?php echo json_encode(array_combine(
            array_column($translatedMarkers, 'name'),
            array_map(function($m) {
                return [
                    'letters' => $m['diary_letters'],
                    'thoughts' => $m['diary_thoughts'],
                    'letter_count' => count($m['diary_letters']),
                    'thought_count' => count($m['diary_thoughts'])
                ];
            }, $translatedMarkers)
        ), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        
        function openInNewWindow() {
            window.open(window.location.href, '_blank');
        }

        function toggleShowAllCoords(enabled) {
            const url = new URL(window.location.href);
            if (enabled) {
                url.searchParams.set('show_all_coords', '1');
            } else {
                url.searchParams.delete('show_all_coords');
            }
            window.location.href = url.toString();
        }

        function requestAction(npcName) {
            const formData = new FormData();
            formData.append('action', 'request_action');
            formData.append('npc_name', npcName);
            showProcessing()

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.ok) {
                    alert(data.message || 'Action request sent!');
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
                hideProcessing()
            })
            .catch(error => {
                console.error('Error:', error);
                hideProcessing()
                alert('Request failed');
            });
        }

        function requestReporting(npcName) {
            const formData = new FormData();
            formData.append('action', 'request_reporting');
            formData.append('npc_name', npcName);
            showProcessing()
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.ok) {
                    alert(data.message || 'Reporting request sent!');
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
                hideProcessing();
            })
            .catch(error => {
                console.error('Error:', error);
                hideProcessing();
                alert('Request failed');
            });
        }

        function updateCoords(npcName) {
            const formData = new FormData();
            formData.append('action', 'update_coords');
            formData.append('npc_name', npcName);
            showProcessing()
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.ok) {
                    alert(data.message || 'Coords update sent!');
                    // Reload the page after a short delay to see updates
                    setTimeout(() => {
                        location.reload();
                    }, 200);
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
                hideProcessing();
             
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Request failed');
                hideProcessing();
            });
        }

        function updateAllCoords() {
            const formData = new FormData();
            formData.append('action', 'update_all_coords');
            showProcessing()
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.ok) {
                    alert(data.message || 'All coords update sent!');
                    // Reload the page after a short delay to see updates
                    setTimeout(() => {
                        location.reload();
                    }, 200);
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
                hideProcessing();
             
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Request failed');
                hideProcessing();
            });
        }

        function updateBulkToggleState(setting, delta) {
            const button = document.querySelector('.bgl-bulk-toggle[data-setting="' + setting + '"]');
            if (!button) {
                return;
            }

            const total = Number(button.getAttribute('data-total')) || 0;
            const currentCount = Number(button.getAttribute('data-enabled-count')) || 0;
            const enabledCount = Math.max(0, Math.min(total, currentCount + delta));
            const allEnabled = total > 0 && enabledCount === total;
            const label = button.getAttribute('data-label') || 'Setting';

            button.setAttribute('data-enabled-count', String(enabledCount));
            button.setAttribute('title', enabledCount + ' of ' + total + ' enabled');
            button.textContent = (allEnabled ? 'Disable All ' : 'Enable All ') + label;
        }

        function toggleAllBgLifeSettings(button) {
            const setting = button.getAttribute('data-setting');
            const label = button.getAttribute('data-label') || 'setting';
            const total = Number(button.getAttribute('data-total')) || 0;
            const enabledCount = Number(button.getAttribute('data-enabled-count')) || 0;
            const value = !(total > 0 && enabledCount === total);
            const actionLabel = value ? 'enable' : 'disable';

            if (total === 0 || !window.confirm('Are you sure you want to ' + actionLabel + ' ' + label.toLowerCase() + ' for all Background Life NPCs?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'toggle_all_bg_life_settings');
            formData.append('setting', setting);
            formData.append('value', value ? '1' : '0');

            button.disabled = true;
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.ok) {
                    throw new Error(data.message || 'Update failed');
                }
                window.location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error: ' + error.message);
                button.disabled = false;
            });
        }

        function toggleBgLifeSetting(button) {
            const npcId = button.getAttribute('data-npc-id');
            const setting = button.getAttribute('data-setting');
            const label = button.getAttribute('data-label');
            const wasEnabled = button.getAttribute('data-enabled') === '1';
            const value = !wasEnabled;
            
            const formData = new FormData();
            formData.append('action', 'toggle_bg_life_setting');
            formData.append('npc_id', npcId);
            formData.append('setting', setting);
            formData.append('value', value ? '1' : '0');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.ok) {
                    console.log(data.message);
                    button.setAttribute('data-enabled', value ? '1' : '0');
                    button.setAttribute('aria-pressed', value ? 'true' : 'false');
                    button.classList.toggle('is-enabled', value);
                    button.classList.toggle('is-disabled', !value);
                    const stateText = value ? 'enabled' : 'disabled';
                    button.setAttribute('aria-label', label + ': ' + stateText);
                    button.setAttribute('title', label + ': ' + stateText);
                    const markerCard = button.closest('.marker-item');
                    if (markerCard && markerCard.getAttribute('data-bgl-enrolled') === '1') {
                        updateBulkToggleState(setting, value ? 1 : -1);
                    }
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Request failed');
            })
            .finally(() => {
                button.disabled = false;
            });

            button.disabled = true;
        }

        function toggleInstructions() {
            const box = document.querySelector('.bgl-instructions-box');
            const btn = document.querySelector('.toggle-instructions-btn');
            
            if (box.classList.contains('collapsed')) {
                box.classList.remove('collapsed');
                btn.textContent = 'Hide Instructions';
            } else {
                box.classList.add('collapsed');
                btn.textContent = 'Show Instructions';
            }
        }

        function openBglModal(modalId, focusId) {
            const modal = document.getElementById(modalId);
            if (!modal) {
                return;
            }

            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('bgl-modal-open');

            const focusTarget = document.getElementById(focusId);
            if (focusTarget) {
                focusTarget.focus();
            }
        }

        function closeBglModal(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) {
                return;
            }

            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            if (!document.querySelector('.bgl-modal.open')) {
                document.body.classList.remove('bgl-modal-open');
            }
        }

        function openCreateNpcModal() {
            openBglModal('create-background-npc', 'npc_name');
        }

        function closeCreateNpcModal() {
            closeBglModal('create-background-npc');
        }

        function openCreateRumorModal() {
            openBglModal('create-rumor', 'rumor_hold');
        }

        function closeCreateRumorModal() {
            closeBglModal('create-rumor');
        }

        document.addEventListener('DOMContentLoaded', function () {
            const npcModal = document.getElementById('create-background-npc');
            if (npcModal && npcModal.dataset.autoOpen === '1') {
                openCreateNpcModal();
            }

            const rumorModal = document.getElementById('create-rumor');
            if (rumorModal && rumorModal.dataset.autoOpen === '1') {
                openCreateRumorModal();
            }

            document.querySelectorAll('[data-map-focus]').forEach(function (control) {
                control.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        control.click();
                    }
                });
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeCreateNpcModal();
                closeCreateRumorModal();
            }
        });

        function showProcessing()
        {

            processingMessage                           = document . createElement('div');
            processingMessage . textContent             = 'Processing...';
            processingMessage . style . position        = 'fixed';
            processingMessage . style . top             = '50%';
            processingMessage . style . left            = '50%';
            processingMessage . style . transform       = 'translate(-50%, -50%)';
            processingMessage . style . backgroundColor = '#000';
            processingMessage . style . color           = '#fff';
            processingMessage . style . padding         = '10px 20px';
            processingMessage . style . borderRadius    = '8px';
            processingMessage . style . zIndex          = '10001';
            processingMessage . id                      = "processing_wheel";
            document . body . appendChild(processingMessage);
        }
        function hideProcessing()
        {
            processingMessage . innerHTML      = '';
            processingMessage . style . zIndex = '-10001';

        }

    var processingMessage;
        function pulseAnimation(id) {
            const el = document.getElementById(id);
            if (!el) {
                return;
            }

            if (typeof window.focusBglMapMarker === 'function') {
                window.focusBglMapMarker(el);
            }

            el.classList.add("pulsing");

            setTimeout(() => {
            el.classList.remove("pulsing");
            }, 5000); // 3 seconds
        }

        // Render written history into the dedicated letters and thoughts panels.
        function renderDiaryContent(data) {
            const lettersContent = document.getElementById('npc-letter-history-content');
            const thoughtsContent = document.getElementById('npc-thought-history-content');
            if (!lettersContent || !thoughtsContent) {
                return;
            }

            let lettersHtml = '';
            if (data.letters && data.letters.length > 0) {
                data.letters.forEach((entry) => {
                    lettersHtml += renderEntry(entry, '#4488ff');
                });
            } else {
                lettersHtml = '<div style="text-align: center; padding: 40px; color: #888;"><p style="font-size: 1.2em;">✉️</p><p>No letters found</p></div>';
            }

            let thoughtsHtml = '';
            if (data.thoughts && data.thoughts.length > 0) {
                data.thoughts.forEach((entry) => {
                    thoughtsHtml += renderEntry(entry, '#8844ff');
                });
            } else {
                thoughtsHtml = '<div style="text-align: center; padding: 40px; color: #888;"><p style="font-size: 1.2em;">💭</p><p>No inner thoughts found</p></div>';
            }

            lettersContent.innerHTML = lettersHtml;
            thoughtsContent.innerHTML = thoughtsHtml;
        }

        function renderEntry(entry, borderColor) {
            let html = '<div style="background: #2a2a2a; padding: 15px; margin-bottom: 15px; border-radius: 8px; border-left: 4px solid ' + borderColor + ';">';
            html += '<div style="display: flex; justify-content: space-between; margin-bottom: 10px;">';
            html += '<strong style="color: ' + borderColor + '; font-size: 1.1em;">' + escapeHtml(entry.topic || 'Journal Entry') + '</strong>';
            html += '<span style="color: #888; font-size: 0.9em;">' + escapeHtml(entry.skyrim_date || 'Unknown date') + '</span>';
            html += '</div>';
            
            if (entry.content) {
                html += '<div style="color: #ddd; margin-bottom: 10px; line-height: 1.6; white-space: pre-wrap;">' + escapeHtml(entry.content) + '</div>';
            }
            
            html += '<div style="display: flex; gap: 15px; color: #888; font-size: 0.85em; padding-top: 8px; border-top: 1px solid #3a3a3a;">';
            if (entry.location) {
                html += '<span>📍 ' + escapeHtml(entry.location) + '</span>';
            }
            if (entry.tags) {
                html += '<span>🏷️ ' + escapeHtml(entry.tags) + '</span>';
            }
            html += '</div>';
            html += '</div>';
            
            return html;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        window.renderNpcDiaryContent = renderDiaryContent;
    </script>

    <?php
    // Rumors section
    $rumorGametsPerDay = (int) round(24 / 0.0000024);
    
    // Query current rumors based on per-rumor duration
    $currentRumorsQuery = "SELECT id, gamets, ts, hold, content, type, COALESCE(rumor_length_days, 7) AS rumor_length_days FROM rumors WHERE (gamets + (COALESCE(rumor_length_days, 7) * $1)) > $2 ORDER BY gamets DESC";
    $currentRumorsResult = pg_query_params($adminConn, $currentRumorsQuery, [$rumorGametsPerDay, $last_gamets]);
    $currentRumors = [];
    if ($currentRumorsResult) {
        while ($row = pg_fetch_assoc($currentRumorsResult)) {
            $currentRumors[] = $row;
        }
    }
    
    // Query outdated rumors based on per-rumor duration
    $outdatedRumorsQuery = "SELECT id, gamets, ts, hold, content, type, COALESCE(rumor_length_days, 7) AS rumor_length_days FROM rumors WHERE (gamets + (COALESCE(rumor_length_days, 7) * $1)) <= $2 ORDER BY gamets DESC";
    $outdatedRumorsResult = pg_query_params($adminConn, $outdatedRumorsQuery, [$rumorGametsPerDay, $last_gamets]);
    $outdatedRumors = [];
    if ($outdatedRumorsResult) {
        while ($row = pg_fetch_assoc($outdatedRumorsResult)) {
            $outdatedRumors[] = $row;
        }
    }

    ?>

    <link rel="stylesheet" href="<?php echo htmlspecialchars($webRoot); ?>/ui/css/background_life_history.css?v=<?php echo (int) @filemtime(__DIR__ . '/css/background_life_history.css'); ?>">
    <section class="bgl-page-panel" id="bgl-tab-history" <?php echo $activeBglTab === 'history' ? '' : 'hidden'; ?>>
    <div
        class="info-panel bgl-history-panel"
        id="bgl-history-panel"
        data-api-url="<?php echo htmlspecialchars($webRoot); ?>/ui/api/background_life_history.php">
        <div class="bgl-history-header">
            <div>
                <h3>📚 Background Life History</h3>
                <div class="bgl-history-subtitle">Recent NPC activity, decisions, travel, and routines.</div>
            </div>
            <div class="bgl-history-status" id="bgl-history-status" aria-live="polite"></div>
        </div>

        <div class="bgl-history-toolbar">
            <select class="bgl-history-control" id="bgl-history-npc-filter" aria-label="Filter by NPC">
                <option value="">All NPCs</option>
            </select>
            <input
                class="bgl-history-control"
                id="bgl-history-search"
                type="search"
                placeholder="Search Background Life activity"
                aria-label="Search Background Life activity">
            <select class="bgl-history-control" id="bgl-history-limit" aria-label="Activities per page">
                <option value="20" selected>20 rows</option>
                <option value="50">50 rows</option>
                <option value="100">100 rows</option>
            </select>
            <div style="display: flex; gap: 8px;">
                <button class="bgl-history-button" id="bgl-history-refresh" type="button">Refresh</button>
                <button class="bgl-history-button" id="bgl-history-live" type="button">Auto Refresh</button>
            </div>
        </div>

        <div class="bgl-history-table-wrap">
            <table class="bgl-history-table">
                <thead>
                    <tr>
                        <th>Tamrielic Time</th>
                        <th>NPC</th>
                        <th>Activity</th>
                    </tr>
                </thead>
                <tbody id="bgl-history-body">
                    <tr>
                        <td class="bgl-history-empty" colspan="3">Loading Background Life history...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="bgl-history-pagination">
            <button class="bgl-history-button" id="bgl-history-previous" type="button">Previous</button>
            <span class="bgl-history-page-label" id="bgl-history-page-label">Page 1 of 1</span>
            <button class="bgl-history-button" id="bgl-history-next" type="button">Next</button>
        </div>
    </div>
    </section>
    <script src="<?php echo htmlspecialchars($webRoot); ?>/ui/js/background_life_history.js?v=<?php echo (int) @filemtime(__DIR__ . '/js/background_life_history.js'); ?>"></script>

    <div
        class="bgl-modal"
        id="npc-recent-events"
        data-api-url="<?php echo htmlspecialchars($webRoot); ?>/ui/api/background_life_history.php"
        aria-hidden="true"
        onclick="if (event.target === this) closeNpcRecentEvents()">
        <div class="bgl-modal-dialog bgl-recent-events-dialog" role="dialog" aria-modal="true" aria-labelledby="npc-recent-events-title">
            <div class="bgl-modal-header">
                <h3 id="npc-recent-events-title">NPC History</h3>
                <button type="button" class="bgl-modal-close" onclick="closeNpcRecentEvents()" aria-label="Close recent events modal">&times;</button>
            </div>
            <div class="bgl-npc-history-tabs" role="tablist" aria-label="NPC history sections">
                <button type="button" class="bgl-npc-history-tab active" data-npc-history-tab="events" role="tab" aria-selected="true">📚 Event History</button>
                <button type="button" class="bgl-npc-history-tab" data-npc-history-tab="letters" role="tab" aria-selected="false">✉️ Letters</button>
                <button type="button" class="bgl-npc-history-tab" data-npc-history-tab="thoughts" role="tab" aria-selected="false">💭 Inner Thoughts</button>
            </div>
            <section class="bgl-npc-history-panel" id="npc-event-history-panel">
                <div class="bgl-recent-events-status" id="npc-recent-events-status" aria-live="polite"></div>
                <div class="bgl-recent-events-list" id="npc-recent-events-list"></div>
            </section>
            <section class="bgl-npc-history-panel" id="npc-letters-history-panel" hidden>
                <div id="npc-letter-history-content"></div>
            </section>
            <section class="bgl-npc-history-panel" id="npc-thoughts-history-panel" hidden>
                <div id="npc-thought-history-content"></div>
            </section>
        </div>
    </div>

    <div
        class="bgl-modal"
        id="create-background-npc"
        data-auto-open="<?php echo !empty($spawnNpcFlash['message']) ? '1' : '0'; ?>"
        aria-hidden="true"
        onclick="if (event.target === this) closeCreateNpcModal()">
        <div class="bgl-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="create-background-npc-title">
        <div class="bgl-modal-header">
            <h3 id="create-background-npc-title">🧬 Create Background Life NPC</h3>
            <button type="button" class="bgl-modal-close" onclick="closeCreateNpcModal()" aria-label="Close Create NPC modal">&times;</button>
        </div>
        <div class="bgl-create-npc-notice">
            <strong>Skyrim must be running, connected to CHIM, and unpaused.</strong>
            Keep the game open while the NPC is created, renamed, moved to the selected location, and added to Background Life.
        </div>
        <?php if (!empty($spawnNpcFlash['message'])): ?>
            <?php
                $isSpawnSuccess = ($spawnNpcFlash['type'] ?? '') === 'success';
                $spawnFlashBg = $isSpawnSuccess ? 'rgba(42, 122, 59, 0.22)' : 'rgba(122, 42, 42, 0.24)';
                $spawnFlashBorder = $isSpawnSuccess ? '#4caf50' : '#d65c5c';
                $spawnFlashText = $isSpawnSuccess ? '#d6ffd9' : '#ffd6d6';
            ?>
            <div class="info-panel" style="margin-bottom: 20px; background: <?php echo $spawnFlashBg; ?>; border: 1px solid <?php echo $spawnFlashBorder; ?>; color: <?php echo $spawnFlashText; ?>;">
                <?php echo nl2br(htmlspecialchars($spawnNpcFlash['message'])); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="action" value="create_background_npc">
            <div class="bgl-create-form-grid">
                <div class="bgl-create-field">
                    <label for="npc_name">Name</label>
                    <input id="npc_name" name="npc_name" type="text" required value="<?php echo htmlspecialchars($spawnNpcFormData['name'] ?? ''); ?>">
                </div>
                <div class="bgl-create-field">
                    <label for="npc_gender">Gender</label>
                    <select id="npc_gender" name="npc_gender" required>
                        <?php $npcGenderValue = (string) ($spawnNpcFormData['gender'] ?? 'male'); ?>
                        <?php foreach ($npcCreationOptions['genders'] as $npcGenderOption): ?>
                            <option value="<?php echo htmlspecialchars($npcGenderOption); ?>" <?php echo ($npcGenderValue === $npcGenderOption) ? 'selected' : ''; ?>><?php echo htmlspecialchars($npcGenderOption); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bgl-create-field">
                    <label for="npc_race">Race</label>
                    <select id="npc_race" name="npc_race" required>
                        <?php
                            $npcRaceValue = (string) ($spawnNpcFormData['race'] ?? 'Nord');
                            foreach ($npcCreationOptions['races'] as $npcRaceOption):
                        ?>
                            <option value="<?php echo htmlspecialchars($npcRaceOption); ?>" <?php echo ($npcRaceValue === $npcRaceOption) ? 'selected' : ''; ?>><?php echo htmlspecialchars($npcRaceOption); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bgl-create-field">
                    <label for="npc_class">Class</label>
                    <select id="npc_class" name="npc_class" required>
                        <?php
                            $npcClassValue = (string) ($spawnNpcFormData['class'] ?? 'farmer');
                            foreach ($npcCreationOptions['classes'] as $npcClassOption):
                        ?>
                            <option value="<?php echo htmlspecialchars($npcClassOption); ?>" <?php echo ($npcClassValue === $npcClassOption) ? 'selected' : ''; ?>><?php echo htmlspecialchars($npcClassOption); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bgl-create-field bgl-create-field-wide">
                    <label for="npc_location">Location</label>
                    <select id="npc_location" name="npc_location" required>
                        <?php $npcLocationValue = (string) ($spawnNpcFormData['location'] ?? ''); ?>
                        <option value="">Select discovered location</option>
                        <?php foreach ($npcCreationOptions['locations'] as $npcLocationOption): ?>
                            <option value="<?php echo htmlspecialchars($npcLocationOption['formid']); ?>" <?php echo ($npcLocationValue === (string) $npcLocationOption['formid']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($npcLocationOption['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bgl-create-field bgl-create-field-wide">
                    <label for="npc_background">Background</label>
                    <textarea id="npc_background" name="npc_background" rows="3" required><?php echo htmlspecialchars($spawnNpcFormData['background'] ?? ''); ?></textarea>
                </div>
                <div class="bgl-create-field">
                    <label for="npc_speech_style">Speech Style</label>
                    <input id="npc_speech_style" name="npc_speech_style" type="text" required value="<?php echo htmlspecialchars($spawnNpcFormData['speech_style'] ?? ''); ?>">
                </div>
                <div class="bgl-create-field bgl-create-field-wide">
                    <label for="npc_goal">Goals</label>
                    <textarea id="npc_goal" name="npc_goal" rows="3" required><?php echo htmlspecialchars($spawnNpcFormData['goal'] ?? ''); ?></textarea>
                </div>
            </div>

            <details class="bgl-create-advanced">
                <summary>Advanced Options</summary>
                <div class="bgl-create-form-grid">
                    <div class="bgl-create-field bgl-create-field-wide">
                        <label for="npc_appearance">Appearance</label>
                        <input id="npc_appearance" name="npc_appearance" type="text" value="<?php echo htmlspecialchars($spawnNpcFormData['appearance'] ?? ''); ?>">
                    </div>
                    <div class="bgl-create-field">
                        <label for="npc_disposition">Disposition</label>
                        <input id="npc_disposition" name="npc_disposition" type="text" value="<?php echo htmlspecialchars($spawnNpcFormData['disposition'] ?? 'friendly'); ?>">
                    </div>
                    <div class="bgl-create-field">
                        <label for="npc_starting_point">Starting Point FormID</label>
                        <input id="npc_starting_point" name="npc_starting_point" type="text" value="<?php echo htmlspecialchars($spawnNpcFormData['starting_point'] ?? ''); ?>" placeholder="Uses location when empty">
                    </div>
                    <div class="bgl-create-field">
                        <label for="npc_inventory_gold">Gold</label>
                        <input id="npc_inventory_gold" name="npc_inventory_gold" type="number" min="0" step="1" value="<?php echo htmlspecialchars($spawnNpcFormData['gold_qty'] ?? '100'); ?>">
                    </div>
                </div>
            </details>

            <div style="margin-top: 18px; display: flex; justify-content: flex-end;">
                <button type="submit" style="padding: 10px 18px; border-radius: 8px; border: 1px solid rgb(242, 124, 17); background: rgb(242, 124, 17); color: #121212; font-weight: 700; cursor: pointer;">
                    Create NPC
                </button>
            </div>
        </form>
        </div>
    </div>

    <section
        class="bgl-page-panel"
        id="rumors-section"
        <?php echo $activeBglTab === 'rumors' ? '' : 'hidden'; ?>>
        <div class="page-header" style="margin-bottom: 20px;">
            <h1>📰 Rumors</h1>
        </div>
        <div class="bgl-action-toolbar">
            <button type="button" class="chim-btn-primary bgl-action-button" onclick="openCreateRumorModal()">
                <?php echo ($editingRumorId > 0) ? 'Edit Rumor' : 'Create Rumor'; ?>
            </button>
        </div>
        
        <?php if (!empty($rumorFlash['message'])): ?>
            <?php
                $isSuccessFlash = ($rumorFlash['type'] ?? '') === 'success';
                $flashBg = $isSuccessFlash ? 'rgba(42, 122, 59, 0.22)' : 'rgba(122, 42, 42, 0.24)';
                $flashBorder = $isSuccessFlash ? '#4caf50' : '#d65c5c';
                $flashText = $isSuccessFlash ? '#d6ffd9' : '#ffd6d6';
            ?>
            <div class="info-panel" style="margin-bottom: 20px; background: <?php echo $flashBg; ?>; border: 1px solid <?php echo $flashBorder; ?>; color: <?php echo $flashText; ?>;">
                <?php echo htmlspecialchars($rumorFlash['message']); ?>
            </div>
        <?php endif; ?>

        <!-- Current Rumors -->
        <div class="info-panel" style="margin-bottom: 30px;">
            <h3>🔥 Current Rumors</h3>
            <?php if (empty($currentRumors)): ?>
                <p style="color: #888; font-style: italic;">No current rumors</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                        <thead>
                            <tr style="background: #1a1a1a; border-bottom: 2px solid rgb(242, 124, 17);">
                                <th style="padding: 12px; text-align: left; color: rgb(242, 124, 17); font-weight: bold;">Hold</th>
                                <th style="padding: 12px; text-align: left; color: rgb(242, 124, 17); font-weight: bold;">Type</th>
                                <th style="padding: 12px; text-align: left; color: rgb(242, 124, 17); font-weight: bold;">Lasts</th>
                                <th style="padding: 12px; text-align: left; color: rgb(242, 124, 17); font-weight: bold;">Content</th>
                                <th style="padding: 12px; text-align: left; color: rgb(242, 124, 17); font-weight: bold;">In-Game Date</th>
                                <th style="padding: 12px; text-align: left; color: rgb(242, 124, 17); font-weight: bold;">Age</th>
                                <th style="padding: 12px; text-align: left; color: rgb(242, 124, 17); font-weight: bold;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($currentRumors as $rumor): ?>
                                <?php 
                                    $rumorDate = convert_gamets2skyrim_date($rumor['gamets']);
                                    $hoursAgo = round(($last_gamets - $rumor['gamets']) * 0.0000024, 1);
                                ?>
                                <tr style="border-bottom: 1px solid #333;">
                                    <td style="padding: 12px; color: #ddd;"><?php echo htmlspecialchars($rumor['hold'] ?? 'Unknown'); ?></td>
                                    <td style="padding: 12px; color: #bbb; font-size: 12px;"><?php echo htmlspecialchars($rumor['type'] ?? 'General'); ?></td>
                                    <td style="padding: 12px; color: #bbb; font-size: 12px; white-space: nowrap;"><?php echo (int)($rumor['rumor_length_days'] ?? 7); ?> days</td>
                                    <td style="padding: 12px; color: #fff;"><?php echo htmlspecialchars($rumor['content']); ?></td>
                                    <td style="padding: 12px; color: #bbb; font-size: 12px; white-space: nowrap;"><?php echo htmlspecialchars($rumorDate); ?></td>
                                    <td style="padding: 12px; color: #888; font-size: 12px; white-space: nowrap;"><?php echo $hoursAgo; ?> hours ago</td>
                                    <td style="padding: 12px;">
                                        <a href="<?php echo htmlspecialchars($rumorsTabUrl . '&edit_rumor_id=' . urlencode((string)($rumor['id'] ?? '')) . '#create-rumor'); ?>" style="color: rgb(242, 124, 17); font-weight: 600; text-decoration: none;">Edit</a>
                                        <form method="post" action="" style="display: inline; margin-left: 12px;" onsubmit="return confirm('Delete this rumor?');">
                                            <input type="hidden" name="action" value="delete_rumor">
                                            <input type="hidden" name="rumor_id" value="<?php echo (int) ($rumor['id'] ?? 0); ?>">
                                            <button type="submit" style="background: none; border: none; padding: 0; color: #d65c5c; font-weight: 600; text-decoration: none; cursor: pointer;">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Outdated Rumors -->
        <div class="info-panel">
            <h3>📜 Outdated Rumors</h3>
            <?php if (empty($outdatedRumors)): ?>
                <p style="color: #888; font-style: italic;">No outdated rumors</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                        <thead>
                            <tr style="background: #1a1a1a; border-bottom: 2px solid #666;">
                                <th style="padding: 12px; text-align: left; color: #888; font-weight: bold;">Hold</th>
                                <th style="padding: 12px; text-align: left; color: #888; font-weight: bold;">Type</th>
                                <th style="padding: 12px; text-align: left; color: #888; font-weight: bold;">Lasts</th>
                                <th style="padding: 12px; text-align: left; color: #888; font-weight: bold;">Content</th>
                                <th style="padding: 12px; text-align: left; color: #888; font-weight: bold;">In-Game Date</th>
                                <th style="padding: 12px; text-align: left; color: #888; font-weight: bold;">Age</th>
                                <th style="padding: 12px; text-align: left; color: #888; font-weight: bold;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($outdatedRumors as $rumor): ?>
                                <?php 
                                    $rumorDate = convert_gamets2skyrim_date($rumor['gamets']);
                                    $hoursAgo = round(($last_gamets - $rumor['gamets']) * 0.0000024, 1);
                                ?>
                                <tr style="border-bottom: 1px solid #333; opacity: 0.6;">
                                    <td style="padding: 12px; color: #888;"><?php echo htmlspecialchars($rumor['hold'] ?? 'Unknown'); ?></td>
                                    <td style="padding: 12px; color: #777; font-size: 12px;"><?php echo htmlspecialchars($rumor['type'] ?? 'General'); ?></td>
                                    <td style="padding: 12px; color: #777; font-size: 12px; white-space: nowrap;"><?php echo (int)($rumor['rumor_length_days'] ?? 7); ?> days</td>
                                    <td style="padding: 12px; color: #999;"><?php echo htmlspecialchars($rumor['content']); ?></td>
                                    <td style="padding: 12px; color: #777; font-size: 12px; white-space: nowrap;"><?php echo htmlspecialchars($rumorDate); ?></td>
                                    <td style="padding: 12px; color: #666; font-size: 12px; white-space: nowrap;"><?php echo $hoursAgo; ?> hours ago</td>
                                    <td style="padding: 12px;">
                                        <a href="<?php echo htmlspecialchars($rumorsTabUrl . '&edit_rumor_id=' . urlencode((string)($rumor['id'] ?? '')) . '#create-rumor'); ?>" style="color: #bbb; font-weight: 600; text-decoration: none;">Edit</a>
                                        <form method="post" action="" style="display: inline; margin-left: 12px;" onsubmit="return confirm('Delete this rumor?');">
                                            <input type="hidden" name="action" value="delete_rumor">
                                            <input type="hidden" name="rumor_id" value="<?php echo (int) ($rumor['id'] ?? 0); ?>">
                                            <button type="submit" style="background: none; border: none; padding: 0; color: #d65c5c; font-weight: 600; text-decoration: none; cursor: pointer;">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

       

        <div
            class="bgl-modal"
            id="create-rumor"
            data-auto-open="<?php echo ($editingRumorId > 0 || (($rumorFlash['type'] ?? '') === 'error')) ? '1' : '0'; ?>"
            aria-hidden="true"
            onclick="if (event.target === this) closeCreateRumorModal()">
            <div class="bgl-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="create-rumor-title">
            <div class="bgl-modal-header">
                <h3 id="create-rumor-title"><?php echo ($editingRumorId > 0) ? 'Edit Rumor' : 'Create Rumor'; ?></h3>
                <button type="button" class="bgl-modal-close" onclick="closeCreateRumorModal()" aria-label="Close rumor modal">&times;</button>
            </div>
            <form method="post" action="">
                <input type="hidden" name="action" value="<?php echo ($editingRumorId > 0) ? 'update_rumor' : 'create_rumor'; ?>">
                <?php if ($editingRumorId > 0): ?>
                    <input type="hidden" name="rumor_id" value="<?php echo (int) $editingRumorId; ?>">
                <?php endif; ?>
                <div class="bgl-create-form-grid">
                    <div>
                        <label for="rumor_hold" style="display: block; margin-bottom: 8px; color: #f2c48f; font-weight: 600;">Hold</label>
                        <select id="rumor_hold" name="rumor_hold" required style="width: 100%; padding: 10px 12px; background: #171717; color: #f5f5f5; border: 1px solid #444; border-radius: 8px;">
                            <option value="">Select hold</option>
                            <?php foreach (chimGetRumorHoldOptions() as $holdOption): ?>
                                <option value="<?php echo htmlspecialchars($holdOption); ?>" <?php echo (($rumorFormData['hold'] ?? '') === $holdOption) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($holdOption); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="rumor_type" style="display: block; margin-bottom: 8px; color: #f2c48f; font-weight: 600;">Type</label>
                        <input
                            id="rumor_type"
                            name="rumor_type"
                            type="text"
                            value="<?php echo htmlspecialchars($rumorFormData['type'] ?? ''); ?>"
                            placeholder="General"
                            style="width: 100%; padding: 10px 12px; background: #171717; color: #f5f5f5; border: 1px solid #444; border-radius: 8px; box-sizing: border-box;">
                    </div>
                    <div>
                        <label for="rumor_length_days" style="display: block; margin-bottom: 8px; color: #f2c48f; font-weight: 600;">Rumor Length (Days)</label>
                        <input
                            id="rumor_length_days"
                            name="rumor_length_days"
                            type="number"
                            min="1"
                            step="1"
                            value="<?php echo htmlspecialchars($rumorFormData['length_days'] ?? '7'); ?>"
                            placeholder="7"
                            style="width: 100%; padding: 10px 12px; background: #171717; color: #f5f5f5; border: 1px solid #444; border-radius: 8px; box-sizing: border-box;">
                    </div>
                </div>
                <div style="margin-top: 18px;">
                    <label for="rumor_content" style="display: block; margin-bottom: 8px; color: #f2c48f; font-weight: 600;">Content</label>
                    <textarea
                        id="rumor_content"
                        name="rumor_content"
                        rows="4"
                        required
                        placeholder="Write the rumor text here..."
                        style="width: 100%; padding: 12px; background: #171717; color: #f5f5f5; border: 1px solid #444; border-radius: 8px; box-sizing: border-box; resize: vertical;"><?php echo htmlspecialchars($rumorFormData['content'] ?? ''); ?></textarea>
                </div>
                <div style="margin-top: 18px; display: flex; justify-content: flex-end; gap: 12px;">
                    <?php if ($editingRumorId > 0): ?>
                        <a href="<?php echo htmlspecialchars($rumorsTabUrl . '#rumors-section'); ?>" style="padding: 10px 18px; border-radius: 8px; border: 1px solid #555; background: #242424; color: #f2f2f2; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center;">
                            Cancel Edit
                        </a>
                    <?php endif; ?>
                    <button type="submit" style="padding: 10px 18px; border-radius: 8px; border: 1px solid rgb(242, 124, 17); background: rgb(242, 124, 17); color: #121212; font-weight: 700; cursor: pointer;">
                        <?php echo ($editingRumorId > 0) ? 'Save Rumor' : 'Create Rumor'; ?>
                    </button>
                </div>
            </form>
            </div>
        </div>
    </section>
</main>

<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
