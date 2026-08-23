<?php
// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/')
    $webRoot = '';
$webRoot = rtrim($webRoot, '/');

require_once(__DIR__ . DIRECTORY_SEPARATOR . "profile_loader.php");
require_once(__DIR__ . DIRECTORY_SEPARATOR . "cmd" . DIRECTORY_SEPARATOR . "rumor_service.php");
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

$host = 'localhost';
$port = '5432';
$dbname = 'dwemer';
$schema = 'public';
$username = 'dwemer';
$password = 'dwemer';

$adminConn = @pg_connect("host={$host} port={$port} dbname={$dbname} user={$username} password={$password}");
if (!$adminConn) {
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
$spawnNpcFormData = [
    'name' => '',
    'gender' => 'male',
    'class' => 'farmer',
    'race' => 'Nord',
    'location' => '',
    'appearance' => '',
    'background' => '',
    'speech_style' => '',
    'disposition' => 'friendly',
    'goal' => '',
    'starting_point' => '',
    'gold_qty' => '100',
    'iron_ore_qty' => '10',
    'gold_ore_qty' => '5',
];
$editingRumorId = 0;

function getRumorPagePath()
{
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (!$path) {
        $path = $_SERVER['SCRIPT_NAME'];
    }
    return $path;
}

function redirectToRumorSection($status, $message, $anchor = 'create-rumor')
{
    $path = getRumorPagePath();

    $query = http_build_query([
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

function redirectToBglSettings($status, $message)
{
    $path = getRumorPagePath();
    $query = http_build_query([
        'bgl_settings_status' => $status,
        'bgl_settings_message' => $message,
    ]);

    header('Location: ' . $path . '?' . $query . '#background-life-settings');
    exit;
}

function redirectToSpawnNpcSection($status, $message, $anchor = 'create-background-npc')
{
    $path = getRumorPagePath();
    $query = http_build_query([
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

function fetchRumorById($rumorId)
{
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

function handleCreateRumor()
{
    global $adminConn;

    $hold = trim((string) ($_POST['rumor_hold'] ?? ''));
    $type = trim((string) ($_POST['rumor_type'] ?? ''));
    $content = trim((string) ($_POST['rumor_content'] ?? ''));
    $lengthDaysInput = trim((string) ($_POST['rumor_length_days'] ?? ''));

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

function handleUpdateRumor()
{
    global $adminConn;

    $rumorId = (int) ($_POST['rumor_id'] ?? 0);
    $hold = trim((string) ($_POST['rumor_hold'] ?? ''));
    $type = trim((string) ($_POST['rumor_type'] ?? ''));
    $content = trim((string) ($_POST['rumor_content'] ?? ''));
    $lengthDaysInput = trim((string) ($_POST['rumor_length_days'] ?? ''));

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

function handleDeleteRumor()
{
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

function handleCreateBackgroundNpc()
{
    global $enginePath;

    $name = trim((string) ($_POST['npc_name'] ?? ''));
    $gender = trim((string) ($_POST['npc_gender'] ?? ''));
    $class = trim((string) ($_POST['npc_class'] ?? ''));
    $race = trim((string) ($_POST['npc_race'] ?? ''));
    $location = trim((string) ($_POST['npc_location'] ?? ''));
    $appearance = trim((string) ($_POST['npc_appearance'] ?? ''));
    $background = trim((string) ($_POST['npc_background'] ?? ''));
    $speechStyle = trim((string) ($_POST['npc_speech_style'] ?? ''));
    $disposition = trim((string) ($_POST['npc_disposition'] ?? ''));
    $goal = trim((string) ($_POST['npc_goal'] ?? ''));
    $startingPoint = trim((string) ($_POST['npc_starting_point'] ?? ''));

    $goldQty = (int) ($_POST['npc_inventory_gold'] ?? 0);
    $ironOreQty = (int) ($_POST['npc_inventory_iron_ore'] ?? 0);
    $goldOreQty = (int) ($_POST['npc_inventory_gold_ore'] ?? 0);

    $formData = [
        'name' => $name,
        'gender' => $gender,
        'class' => $class,
        'race' => $race,
        'location' => $location,
        'appearance' => $appearance,
        'background' => $background,
        'speech_style' => $speechStyle,
        'disposition' => $disposition,
        'goal' => $goal,
        'starting_point' => $startingPoint,
        'gold_qty' => (string) $goldQty,
        'iron_ore_qty' => (string) $ironOreQty,
        'gold_ore_qty' => (string) $goldOreQty,
    ];

    if ($name === '' || $gender === '' || $class === '' || $race === '' || $location === '' || $background === '' || $speechStyle === '' || $goal === '') {
        return [
            [
                'type' => 'error',
                'message' => 'Missing required fields for NPC creation.',
            ],
            $formData
        ];
    }

    $locationName = $GLOBALS['db']->fetchOne("select name from locations where formid='$location' limit 1")['name'] ?? '';
    $npcProfile = [
        'name' => $name,
        'gender' => $gender,
        'class' => $class,
        'race' => $race,
        'location' => $locationName,
        'appearance' => $appearance,
        'background' => $background,
        'speechStyle' => $speechStyle,
        'disposition' => $disposition,
        'goal' => $goal,
    ];

    $inventoryItems = [];
    if ($goldQty > 0) {
        $inventoryItems[] = ['refid' => '0x0000000F', 'qty' => $goldQty];
    }
    if ($ironOreQty > 0) {
        $inventoryItems[] = ['refid' => '0x00071cf3', 'qty' => $ironOreQty];
    }
    if ($goldOreQty > 0) {
        $inventoryItems[] = ['refid' => '0x0005acde', 'qty' => $goldOreQty];
    }

    if (!$startingPoint) {
        $startingPoint = resolveFormIdToDecimal($location);
    }

    spawnBackgroundLifeNpc($npcProfile, $startingPoint, $inventoryItems);

    redirectToSpawnNpcSection('success', "NPC '$name' spawned successfully.");
}

function resolveFormIdToDecimal($formId)
{
    if (is_int($formId)) {
        return $formId;
    }

    $raw = trim((string) $formId);
    if ($raw === '') {
        return 0;
    }

    if (stripos($raw, '0x') === 0) {
        return (int) hexdec(substr($raw, 2));
    }

    return (int) $raw;
}

function spawnBackgroundLifeNpc($npc_profile, $startingPoint, $inventoryItems)
{
    if (!is_array($npc_profile) || empty($npc_profile['name'])) {
        error_log('[ERROR] Invalid npc profile provided to spawnBackgroundLifeNpc');
        return false;
    }

    $startingPointDec = resolveFormIdToDecimal($startingPoint);
    if ($startingPointDec <= 0) {
        error_log('[ERROR] Invalid starting point provided for ' . ($npc_profile['name'] ?? 'unknown npc'));
        return false;
    }

    npcProfileBase(
        $npc_profile['name'],
        $npc_profile['class'],
        $npc_profile['race'],
        $npc_profile['gender'],
        $npc_profile['location'],
        '0',
        $npc_profile['additional_data'] ?? []
    );

    $spawned = false;
    $cnName = $GLOBALS['db']->escape($npc_profile['name']);
    $last_gamets = null;
    $last_ts = null;

    while (!$spawned) {
        sleep(1);
        error_log('[DEBUG] Checking if ' . $npc_profile['name'] . ' spawned: ' . time() . PHP_EOL);
        $res = $GLOBALS['db']->fetchOne("select count(*) as n, max(gamets) as gamets,max(ts) as ts from eventlog where type='status_msg' and data like '%spawned@$cnName@%'");
        $spawned = $res['n'] > 0;
        $last_gamets = $res['gamets'];
        $last_ts = $res['ts'];
    }

    $npcMaster = new NpcMaster();
    $npc = $npcMaster->getByName($npc_profile['name']);
    $npc['core'] = "{$npc_profile['name']}. {$npc_profile['gender']} {$npc_profile['class']} {$npc_profile['race']}";
    $npc['npc_static_bio'] = "{$npc_profile['name']}. {$npc_profile['background']}";
    $npc['speechstyle'] = $npc_profile['speechStyle'];
    $npc['goals'] = $npc_profile['goal'];
    $npc['lock_profile'] = null;

    $metadata = $npcMaster->getExtendedData($npc);
    $metadata['gps_track'] = true;
    $npc = $npcMaster->setMetadata($npc, $metadata);
    $npcMaster->updateByArray($npc);

    $refid = isset($npc['refid']) ? $npc['refid'] : null;
    if (empty($refid)) {
        error_log('[DEBUG] Waiting to refid to be populated for ' . $npc_profile['name'] . '...' . PHP_EOL);

        $maxRetries = 30;
        $retryCount = 0;
        while (empty($refid) && $retryCount < $maxRetries) {
            sleep(1);
            $retryCount++;
            $npcMaster = new NpcMaster();
            $npc = $npcMaster->getByName($npc_profile['name']);
            $refid = isset($npc['refid']) ? $npc['refid'] : null;
            error_log('[DEBUG] Waiting to refid to be populated for ' . $npc_profile['name'] . "... $retryCount of $maxRetries" . PHP_EOL);
        }

        if (empty($refid)) {
            error_log('[ERROR] Refid was not populated for ' . $npc_profile['name'] . " after {$maxRetries} retries. Exiting." . PHP_EOL);
            return false;
        }

        $npcMaster = new NpcMaster();
        $npc = $npcMaster->getByName($npc_profile['name']);
    }

    sleep(1);
    $GLOBALS['db']->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => 'rolemaster',
            'text' => '',
            'action' => "rolecommand|RenameNPC@0x$refid@$cnName",
            'tag' => '',
        ]
    );

    sleep(1);
    $npcMaster = new NpcMaster();
    $npc = $npcMaster->getByName($npc_profile['name']);
    $extended_data = $npcMaster->getExtendedData($npc);
    $extended_data['background_life_commands'] = true;
    $extended_data['background_life_enabled'] = true;
    $extended_data['background_life_last_updated'] = $last_gamets;
    $extended_data['background_life_player_unattached'] = true;
    $extended_data['middle_term_enabled'] = 1;


    $npc['core'] = "{$npc_profile['name']}. {$npc_profile['gender']} {$npc_profile['class']} {$npc_profile['race']}";
    $npc['npc_static_bio'] = "{$npc_profile['name']}. {$npc_profile['background']}";
    $npc['speechstyle'] = $npc_profile['speechStyle'];
    $npc['goals'] = $npc_profile['goal'];
    $npc['lock_profile'] = null;

    $metadata = $npcMaster->getExtendedData($npc);
    $metadata['gps_track'] = true;
    $npc = $npcMaster->setMetadata($npc, $metadata);
    $npc = $npcMaster->setExtendedData($npc, $extended_data);
    $npcMaster->updateByArray($npc);

    $skyrimCmd = new SkyrimCommandBuilder();
    foreach ($inventoryItems as $itemEntry) {
        if (!is_array($itemEntry)) {
            continue;
        }

        $itemRefId = isset($itemEntry['refid']) ? (string) $itemEntry['refid'] : '';
        $itemQty = isset($itemEntry['qty']) ? (int) $itemEntry['qty'] : 0;
        if ($itemRefId === '' || $itemQty <= 0) {
            continue;
        }

        $json = $skyrimCmd->ObjectReference->AddItem("0x{$npc['refid']}", $itemRefId, $itemQty, true);
        $skyrimCmd->send(cmd: $json);
    }

    $GLOBALS['db']->insert(
        'responselog',
        [
            'localts' => time(),
            'sent' => 0,
            'actor' => 'rolemaster',
            'text' => '',
            'action' => "rolecommand|BackgroundCmd@$refid@TravelTo/$startingPointDec",
            'tag' => __FILE__ . ':' . __LINE__,
        ]
    );

    $res = $GLOBALS['db']->fetchOne('select max(gamets) as gamets,max(ts) as ts from eventlog order by gamets desc,ts desc limit 1');
    $last_gamets = $res['gamets'];
    $last_ts = $res['ts'];

    $GLOBALS['db']->insert('actions_issued', [
        'action' => 'TravelTo',
        'fullcall' => 'TravelTo',
        'actorname' => $npc['npc_name'],
        'ts' => $last_ts,
        'gamets' => $last_gamets,
        'localts' => time(),
        'original' => 'backgroundaction',
    ]);

    return true;
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

$npcLocationOptions = [];
$npcLocationResult = pg_query($adminConn, 'SELECT formid,name,is_interior,region,hold FROM "public"."locations" ORDER BY name ASC');
if ($npcLocationResult) {
    while ($locationRow = pg_fetch_assoc($npcLocationResult)) {
        $locationName = trim((string) ($locationRow['name'] ?? ''));
        if ($locationName !== '') {
            $npcLocationOptions[] = [$locationRow['formid'], $locationName, (bool) $locationRow['is_interior'], $locationRow['region'], $locationRow['hold']];
        }
    }
}

// Helper function to resolve NPC portrait path (same as npc_master.php)
if (!function_exists('race_icon_web_path')) {
    function race_icon_web_path($race, $webRoot, $refid, $md5 = '', $npcName = '', $portraitRel = '')
    {
        global $enginePath;
        // 0) If metadata specifies a portrait relative path under data/pictures, use it first
        $portraitRel = trim((string) $portraitRel);
        if ($portraitRel !== '') {
            $portraitRel = ltrim(str_replace(['\\'], '/', $portraitRel), '/');
            $picturesRootFs = rtrim("{$enginePath}/data/pictures/", '/\\') . DIRECTORY_SEPARATOR;
            $picturesRootUrl = rtrim($webRoot . '/data/pictures/', '/');
            $fs = realpath($picturesRootFs . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $portraitRel));
            $picturesRootFs = realpath($picturesRootFs);
            if ($fs !== false && strpos($fs, $picturesRootFs) === 0 && is_file($fs)) {
                return $picturesRootUrl . '/' . str_replace('%2F', '/', rawurlencode($portraitRel));
            }
        }
        // 1) Prefer per-NPC portrait from data/pictures/profile
        $refid = strtoupper($refid);
        $profileDirFs = rtrim("{$enginePath}/data/pictures/profile/", '/\\') . DIRECTORY_SEPARATOR;
        $profileDirUrl = rtrim($webRoot . '/data/pictures/profile/', '/');
        $exts = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
        $candidates = [];
        if (!empty($md5)) {
            $candidates[] = $md5;
        }
        if (!empty($refid)) {
            $candidates[] = $refid;
        }
        if (!empty($npcName)) {
            $in = strtolower((string) $npcName);
            $words = preg_split('/[^a-z0-9]+/', $in, -1, PREG_SPLIT_NO_EMPTY);
            if (!empty($words)) {
                $candidates[] = implode('', $words);
                $candidates[] = implode('-', $words);
                $candidates[] = implode('_', $words);
            }
        }
        $seen = [];
        foreach ($candidates as $base) {
            $base = trim((string) $base);
            if ($base === '' || isset($seen[$base]))
                continue;
            $seen[$base] = true;
            foreach ($exts as $ext) {
                $fs = $profileDirFs . $base . '.' . $ext;
                if (file_exists($fs)) {
                    return $profileDirUrl . '/' . rawurlencode($base . '.' . $ext);
                }
            }
        }

        // 2) Fallback to race icon pack
        $in = strtolower((string) $race);
        $words = preg_split('/[^a-z0-9]+/', $in, -1, PREG_SPLIT_NO_EMPTY);
        $slug = implode('', $words);
        if ($slug === '') {
            $words = [];
        }
        $aliases = [
            'highelf' => 'altmer',
            'altmer' => 'altmer',
            'woodelf' => 'bosmer',
            'bosmer' => 'bosmer',
            'darkelf' => 'dunmer',
            'dunmer' => 'dunmer',
            'orsimer' => 'orc',
            'orc' => 'orc',
            'argonian' => 'argonian',
            'khajiit' => 'khajiit',
            'khajit' => 'khajiit',
            'breton' => 'breton',
            'imperial' => 'imperial',
            'nord' => 'nord',
            'redguard' => 'redguard',
            'oldpeople' => 'nord',
            'oldpeoplerace' => 'nord',
        ];
        $base = $aliases[$slug] ?? $slug;
        $variants = [];
        $variants[] = $base;
        if (!empty($words)) {
            $variants[] = implode('', $words);
            $variants[] = implode('-', $words);
            $variants[] = implode('_', $words);
        }
        // Synonyms/misspellings
        if ($base === 'khajiit') {
            $variants[] = 'khajit';
        }
        if ($base === 'khajit') {
            $variants[] = 'khajiit';
        }
        $variants = array_values(array_unique(array_filter($variants, function ($v) {
            return $v !== '';
        })));
        $fsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'ui' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'races' . DIRECTORY_SEPARATOR;
        $exts2 = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'];
        foreach ($variants as $name) {
            foreach ($exts2 as $ext) {
                $fs = $fsDir . $name . '.' . $ext;
                if (file_exists($fs))
                    return $webRoot . '/ui/images/races/' . $name . '.' . $ext;
            }
        }
        $defaultFs = $fsDir . 'default.png';
        if (file_exists($defaultFs)) {
            return $webRoot . '/ui/images/races/default.png';
        }
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

function handleRequestAction()
{
    global $enginePath;
    $npcName = isset($_POST['npc_name']) ? $_POST['npc_name'] : null;

    if (!$npcName) {
        echo json_encode(['ok' => false, 'message' => 'NPC name required']);
        return;
    }
    $npcMaster = new NpcMaster();
    $npcData = $npcMaster->getByName($npcName);
    $extendedData = $npcMaster->getExtendedData($npcData);
    if (!isset($extendedData['background_life_commands']) || $extendedData['background_life_commands'] === false) {
        `php $enginePath/debug/simple_llm_request_with_context_life.php "$npcName" full forceaction`;
    } else {
        `php $enginePath/debug/simple_llm_request_with_context_life_v2.php "$npcName" full forceaction`;
    }

    // Add your handler code here


    echo json_encode(['ok' => true, 'message' => "Action request processed for $npcName"]);
}

function handleRequestReporting()
{
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

function handleUpdateCoords()
{
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

function handleUpdateAllCoords()
{
    global $enginePath;

    // Update coordinates for all NPCs
    `php $enginePath/debug/simple_llm_request_with_context_life_command.php "The Narrator" TrackAll`;
    echo json_encode(['ok' => true, 'message' => 'All NPC coords update processed']);
}

function handleToggleBgLifeSetting()
{
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

function handleSaveBglSettings()
{
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
$mapWidth = 1950;
$mapHeight = 1625;

// Map file path (relative to web root)
$mapImageUrl = '../data/maps/Map_of_Skyrim.png';

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

$result = pg_query($adminConn, "select max(gamets) as last_gamets from eventlog");
$res = pg_fetch_assoc($result);
$last_gamets = $res["last_gamets"];
$currentDate = convert_gamets2skyrim_date($last_gamets);
$bglTriggerHours = chimGetBackgroundLifeTriggerHours();

// Filter mode: show all NPCs with tracked coords, or only BG-Life enabled ones
$showAllCoords = isset($_GET['show_all_coords']) && $_GET['show_all_coords'] === '1';
$whereClause = $showAllCoords
    ? "metadata->>'last_coords' IS NOT NULL"
    : "extended_data->>'background_life_enabled' = 'true'";

$query = "
    select A.*,B.content,C.category as last_action_cat FROM 
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
    LEFT JOIN  (
    SELECT category,npc
        FROM (
            SELECT
                category,npc,
                ROW_NUMBER() OVER (
                    PARTITION BY npc
                    ORDER BY gamets DESC
                ) AS rn
            FROM public.bgl_history
        ) t
        WHERE rn = 1
    ) C ON (C.npc=A.npc_name)
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
$nearbyPOIs = [];

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
            $x = $WORLD_X_MIN;
            $y = $WORLD_Y_MIN;
            $coordsData[3] .= " missing coords";
        }

        $meta = json_decode($row['metadata'], true);
        $extData = json_decode($row['extended_data'], true);

        // Parse background life settings
        $bgLifeCommands = isset($extData['background_life_commands']) ? (bool) $extData['background_life_commands'] : false;
        $bgLifeLetters = isset($extData['background_life_letters']) ? (bool) $extData['background_life_letters'] : false;
        $gpsTrack = isset($meta['gps_track']) ? (bool) $meta['gps_track'] : false;

        // Parse history coordinates
        $coordsHistory = [];
        if (!empty($row['last_coords_history'])) {
            $historyData = json_decode($row['last_coords_history'], true);
            if (is_array($historyData)) {
                foreach ($historyData as $historicalCoord) {

                    if (isset($historicalCoord[0]) && isset($historicalCoord[1])) {
                        if (($last_gamets - $historicalCoord['last_updated']) * 0.0000024 < 24) { // Only if last coord is recent
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

        $markers[] = [
            'name' => $row['npc_name'],
            'ingame_x' => (int) $x,
            'ingame_y' => (int) $y,
            'ingame_z' => (int) $z,
            'color' => generateRandomColor($row['npc_name']),
            'size' => 15,
            'tag' => $coordsData[3],
            'portrait' => isset($meta["portrait"]) ? $meta["portrait"] : '',
            'race' => $row['race'],
            'id' => $row["id"],
            'refid' => $row["refid"],
            'last_pos_ts' => $coordsData["last_updated"] ? convert_gamets2skyrim_date($coordsData["last_updated"]) . ",hours ago:" . round(($last_gamets - $coordsData["last_updated"]) * 0.0000024, 0) : null,
            'last_report' => convert_gamets2skyrim_date($row["last_report"]) . ",hours ago:" . round(($last_gamets - $row["last_report"]) * 0.0000024, 0),
            'coords_history' => $coordsHistory,
            'bg_life_commands' => $bgLifeCommands,
            'bg_life_letters' => $bgLifeLetters,
            'gps_track' => $gpsTrack,
            'last_letter' => $row["content"],
            'diary_letters' => $diaryLetters,
            'diary_thoughts' => $diaryThoughts,
            'interior' => $coordsData['in_interior'],
            'last_action_cat' => strtolower($row['last_action_cat'] ?? '')
        ];

        // nearby POIs for this NPC
        $poiQuery = "SELECT
                   A.name,A.region,A.hold,A.tags,
                    coords <-> '($x,$y)'::point AS distance,
                    coords[0] AS ingame_x,
                    coords[1] AS ingame_y
                    
                FROM locations A
                WHERE coords IS NOT NULL 
                and (
                    ((is_interior & 3) = 0 AND (is_interior & 3) != 2) OR
                    ((is_interior & 12) = 0 AND (is_interior & 12) != 8) OR
                    ((is_interior & 48) = 0 AND (is_interior & 48) != 32) OR
                    ((is_interior & 192) = 0 AND (is_interior & 192) != 128)
                    )
                and name<>'Abandoned Shack'
                and world='Skyrim'
                ORDER BY distance ASC
                LIMIT 20";

        $poiResult = pg_query($adminConn, $poiQuery);
        if ($poiResult) {
            while ($poi = pg_fetch_assoc($poiResult)) {
                $symbol = '';
                if (strpos(strtolower($poi['tags']), 'mine') !== false) {
                    $symbol = '⛏️';
                }
                if (strpos(strtolower($poi['tags']), 'settlement') !== false) {
                    $symbol .= '🏠';
                }
                if (strpos(strtolower($poi['tags']), 'cave') !== false) {
                    $symbol .= '🕳️';
                }
                if (strpos(strtolower($poi['tags']), 'dungeon') !== false) {
                    $symbol .= '🗡️';
                }
                if (strpos(strtolower($poi['tags']), 'military') !== false) {
                    $symbol .= '🏰';
                }

                if (strpos(strtolower($poi['tags']), 'house') !== false) {
                    $symbol .= '🏠';
                }

                if (strpos(strtolower($poi['tags']), 'giant') !== false) {
                    $symbol .= '🗿';
                }

                if (strpos(strtolower($poi['tags']), 'draugr') !== false) {
                    $symbol .= '🧟‍♂️';
                }

                if (strpos(strtolower($poi['tags']), 'nordic ruin') !== false) {
                    $symbol .= '🪨';
                }

                if (strpos(strtolower($poi['tags']), 'ship') !== false) {
                    $symbol .= '🚢';
                }

                if (strpos(strtolower($poi['tags']), 'mill') !== false) {
                    $symbol .= '⚙️';
                }

                if (strpos(strtolower($poi['tags']), 'animal den') !== false) {
                    $symbol .= '🐾';
                }

                if (strpos(strtolower($poi['tags']), 'warlock') !== false) {
                    $symbol .= '🧙‍♂️';
                }

                if (strpos(strtolower($poi['tags']), 'dwarven ruin') !== false) {
                    $symbol .= '🏛️';
                }

                if (strpos(strtolower($poi['tags']), 'bandit camp') !== false) {
                    $symbol .= '🥷';
                }

                if (strpos(strtolower($poi['tags']), 'town') !== false) {
                    $symbol .= '🏘️';
                }

                if (strpos(strtolower($poi['tags']), 'falmer') !== false) {
                    $symbol .= '👽';
                }

                if (strpos(strtolower($poi['tags']), 'guild') !== false) {
                    $symbol .= '🛡️';
                }

                if (strpos(strtolower($poi['tags']), 'habitation') !== false) {
                    $symbol .= '🏡';
                }

                if (strpos(strtolower($poi['tags']), 'dwelling') !== false) {
                    $symbol .= '🛏️';
                }

                if (strpos(strtolower($poi['tags']), 'forsworn') !== false) {
                    $symbol .= '🛖';
                }

                if (strpos(strtolower($poi['tags']), 'carriage') !== false) {
                    $symbol .= '🐴';
                }

                if (strpos(strtolower($poi['tags']), 'dock') !== false) {
                    $symbol .= '⚓';
                }

                if (strpos(strtolower($poi['tags']), 'farm') !== false) {
                    $symbol .= '🌾';
                }

                $symbol = $symbol ?: '📍';
                $nearbyPOIs[] = [
                    'name' => $poi['name'],
                    'region' => $poi['region'],
                    'hold' => $poi['hold'],
                    'distance' => round(floatval($poi['distance']), 2),
                    'ingame_x' => $poi['ingame_x'],
                    'ingame_y' => $poi['ingame_y'],
                    'ingame_z' => 0,
                    'symbol' => $symbol ?? '📍',
                    'tags' => $poi['tags']

                ];
            }
        }

    }
}

// Translate all marker coordinates
$translatedMarkers = [];
$locationMap = []; // Track markers at each location


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
            'interior' => $histCoord['in_interior'] ?? false,
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
        'name' => $marker['name'],
        'x' => $coords['x'],
        'y' => $coords['y'],
        'color' => $marker['color'],
        'size' => $marker['size'],
        'ingame_x' => $marker['ingame_x'],
        'ingame_z' => $marker['ingame_z'],
        'ingame_y' => $marker['ingame_y'],
        'tag' => $marker['tag'],
        'figure' => $figureUrl,
        'id' => $marker['id'],
        'refid' => $marker['refid'],
        'last_pos_ts' => $marker["last_pos_ts"],
        'last_report' => $marker["last_report"],
        'coords_history' => $translatedHistory,
        'bg_life_commands' => $marker['bg_life_commands'],
        'bg_life_letters' => $marker['bg_life_letters'],
        'gps_track' => $marker['gps_track'],
        'last_letter' => $marker['last_letter'],
        'diary_letters' => $marker['diary_letters'],
        'diary_thoughts' => $marker['diary_thoughts'],
        'interior' => $marker['interior'],
        'last_action_cat' => $marker['last_action_cat']
    ];
}

foreach ($nearbyPOIs as $poimarker) {
    $coords = translateCoords(
        $poimarker['ingame_x'],
        $poimarker['ingame_y'],
        $mapWidth,
        $mapHeight,
        $WORLD_X_MIN,
        $WORLD_X_MAX,
        $WORLD_Y_MIN,
        $WORLD_Y_MAX
    );
    if (!isset($translatedPOIMarkers)) {
        $translatedPOIMarkers = [];
    }

    $translatedPOIMarkers[] = [
        'name' => $poimarker['name'],
        'x' => $coords['x'],
        'y' => $coords['y'],
        'color' => '#06835d',
        'size' => 15,
        'ingame_x' => $poimarker['ingame_x'],
        'ingame_z' => $poimarker['ingame_z'] ?? 0,
        'ingame_y' => $poimarker['ingame_y'],
        'tag' => 'poi',
        'tags' => $poimarker['tags'] ?? '',
        'figure' => null,
        'id' => null,
        'refid' => null,
        'last_pos_ts' => null,
        'last_report' => null,
        'coords_history' => [],
        'bg_life_commands' => null,
        'bg_life_letters' => null,
        'gps_track' => null,
        'last_letter' => null,
        'diary_letters' => null,
        'diary_thoughts' => null,
        'interior' => false,
        'last_action_cat' => null,
        'symbol' => $poimarker['symbol'] ?? '📍'
    ];
}

// Apply grid offset to markers at the same location
$locationKey = [];
foreach ($translatedMarkers as $n => $marker) {
    $key = $translatedMarkers[$n]['x'] . ',' . $translatedMarkers[$n]['y'];

    if (!isset($locationKey[$key])) {
        $locationKey[$key] = 0;
    } else {
        $locationKey[$key]++;
    }

    // Calculate grid position (3 columns, rows as needed)
    $cols = 3;
    $row = intdiv($locationKey[$key], $cols);
    $col = $locationKey[$key] % $cols;

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
include(__DIR__ . DIRECTORY_SEPARATOR . "tmpl/head.html");
?>

<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<style>
    /* Override main container styles */
    main {
        padding-top: 10px;
        padding-bottom: 20px;
        
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
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
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
        flex-direction: row-reverse;
    }

    .map-section {
        margin-left:480px;
    }

    .sidebar-section {
        flex: 0 0 calc(25% - 20px);
        display: flex;
        flex-direction: column;
        gap: 10px;
        left: 10px;
        width: 400px;
        z-index: 2000;
        position:fixed;
    }

    .map-container {
        position: relative;
        display: inline-block;
        background: #1a1a1a;
        padding: 15px;
        border: 3px solid rgb(242, 124, 17);
        box-shadow: 0 0 20px rgba(242, 124, 17, 0.3);
        margin: 0 auto;
        width: 200%;
        box-sizing: border-box;
        border-radius: 8px;
        overflow: visible;
        left: 0px;
    }

    .map-container img {
        display: block;
        width: 100%;
        height: auto;
        border: 1px solid #4a4a4a;
        border-radius: 4px;
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
        align-items: center;
        justify-content: center;
        z-index: 10;
        position: relative;
        font-size: 14px;
        text-align: center;
        vertical-align: middle;
        padding-top: 3px;
    }

    .history-marker {
        position: absolute;
        transform: translate(-50%, -50%);
        border-radius: 50%;
        border: 1px solid rgba(255, 255, 255, 0.6);
        box-shadow: 0 0 5px rgba(255, 255, 255, 0.3);
        z-index: 5;
        opacity: 0.7;
        transition: opacity 0.2s ease;
    }

    .history-marker:hover {
        opacity: 1;
        box-shadow: 0 0 10px rgba(255, 255, 255, 0.6);
        z-index: 15;
    }

    .info-section {
        margin-left:480px;
    }
    .poi-marker-icon {
        width: 60px;
        height: 60px;
        /* background-color: #42502B; */
        opacity: 1;
        /* border-radius: 10px; */
        /* border: 1px solid grey; */
        text-wrap-mode: nowrap;
        display: flex;
        flex-direction: row;
        flex-wrap: wrap;
        align-content: center;
        justify-content: space-evenly;
        font-size: larger;
    }

    .history-marker-label {
        position: absolute;
        background: rgba(0, 0, 0, 0.9);
        color: white;
        padding: 6px 10px;
        border-radius: 4px;
        white-space: nowrap;
        font-size: 12px;
        top: 12px;
        left: 50%;
        transform: translateX(-50%);
        margin-top: 3px;
        border: 1px solid rgba(255, 255, 255, 0.4);
        display: none;
        z-index: 20;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.4);
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
        top: 15px;
        left: 50%;
        transform: translateX(-50%);
        margin-top: 5px;
        border: 2px solid rgb(242, 124, 17);
        display: none;
        z-index: 20;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    }

    .marker:hover {
        z-index: 200;
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
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
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
        max-height: calc(100vh - 450px);
        overflow-y: auto;
        overflow-x: hidden;
    }

    .npc-list-container h3 {
        color: rgb(242, 124, 17);
        font-family: 'MagicCards', serif;
        margin-top: 0;
        margin-bottom: 10px;
        word-spacing: 6px;
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
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
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        transition: all 0.3s ease;
    }

    .update-all-coords-btn:hover {
        background: #55ff55;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(68, 255, 68, 0.4);
    }

    .update-all-coords-btn:active {
        transform: translateY(0);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .marker-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .marker-item {
        background-color: #1a1a1a;
        padding: 15px;
        border-left: 4px solid;
        border-radius: 8px;
        background-size: 100px;
        background-repeat: no-repeat;
        background-position: right 10px center;
        border: 1px solid #4a4a4a;
        width: 100%;
        box-sizing: border-box;
        position: relative;

    }

    .marker-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(to right, #1a1a1a 60%, transparent 100%);
        border-radius: 8px;
        pointer-events: none;
        z-index: 1;
    }

    .marker-item>* {
        position: relative;
        z-index: 2;
    }

    .marker-item-color {
        display: inline-block;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        vertical-align: middle;
        margin-right: 10px;
        border: 2px solid white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .marker-item h4 {
        margin: 8px 0;
        color: rgb(242, 124, 17);
        font-family: 'MagicCards', serif;
        display: inline-block;
        vertical-align: text-top;
    }

    .marker-item-coords {
        font-size: 12px;
        color: #bbb;
        margin: 5px 0;
    }

    .marker-item-coords ul {
        padding-left: 15px;
    }

    .marker-item-coords li {
        margin: 3px 0;
    }

    #mapImage {
        opacity: 1;
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
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
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

    .collapsible-panel .collapsible-body {
        display: block;
    }

    .collapsible-panel.collapsed .collapsible-body {
        display: none;
    }

    .toggle-panel-btn {
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

    .toggle-panel-btn:hover {
        background: #4a4a4a;
        color: rgb(242, 124, 17);
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
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .map-controls button:hover {
        background: rgb(255, 140, 30);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(242, 124, 17, 0.4);
    }

    .map-controls button:active {
        transform: translateY(0);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .map-controls span {
        color: rgb(242, 124, 17);
        margin: 5px;
        font-weight: bold;
        font-size: 14px;
        display: block;
        margin-top: 10px;
    }

    .map-width-controls {
        display: flex;
        gap: 12px;
        align-items: center;
        margin-bottom: 15px;
        padding: 12px;
        background: #2a2a2a;
        border-radius: 8px;
        border: 1px solid #4a4a4a;

    }

    .map-width-controls label {
        color: rgb(242, 124, 17);
        font-weight: bold;
        font-size: 12px;
        white-space: nowrap;
    }

    .map-width-slider {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 10px;

    }

    .map-width-slider input[type="range"] {
        flex: 1;
        height: 6px;
        border-radius: 3px;
        background: #3a3a3a;
        outline: none;
        -webkit-appearance: none;
        appearance: none;
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

    .map-width-slider input[type="range"]::-webkit-slider-thumb {
        -webkit-appearance: none;
        appearance: none;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: rgb(242, 124, 17);
        cursor: pointer;
        box-shadow: 0 2px 4px rgba(242, 124, 17, 0.5);
        transition: all 0.2s ease;
    }

    .map-width-slider input[type="range"]::-webkit-slider-thumb:hover {
        box-shadow: 0 0 8px rgba(242, 124, 17, 0.8);
        transform: scale(1.2);
    }

    .map-width-slider input[type="range"]::-moz-range-thumb {
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: rgb(242, 124, 17);
        cursor: pointer;
        border: none;
        box-shadow: 0 2px 4px rgba(242, 124, 17, 0.5);
        transition: all 0.2s ease;
    }

    .map-width-slider input[type="range"]::-moz-range-thumb:hover {
        box-shadow: 0 0 8px rgba(242, 124, 17, 0.8);
        transform: scale(1.2);
    }

    .map-width-value {
        color: #ddd;
        font-weight: bold;
        font-size: 12px;
        min-width: 35px;
        text-align: right;
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
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .marker-action-btn-trans {
        background: rgba(242, 124, 17, 0);
        color: #000;
        border: none;
        padding: 8px 14px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
        font-size: 12px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
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
    }

    .location-marker-icon img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.5));
        border: none;
    }

    .location-marker:hover .location-marker-icon {
        opacity: 1;
        transform: scale(1.3);
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
        top: 15px;
        left: 50%;
        transform: translateX(-50%);
        margin-top: 5px;
        border: none;
        display: none;
        z-index: 30;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    }

    .location-marker:hover {
        z-index: 250;
    }

    .location-marker:hover .location-marker-label {
        display: block;
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
        z-index: 100000
    }
</style>

<main>
    <div class="container">
        <div class="content-wrapper">
            <div class="sidebar-section">
                <div class="bgl-instructions-box collapsed">
                    <h3>📖 How Background Life Works</h3>
                    <div class="bgl-instructions-content">
                        <div class="instruction-section">
                            <strong>Getting Started:</strong>
                            <ol>
                                <li><b>Make sure you the game is running and that the NPC is not a current follower
                                        (dismiss them).</b></li>
                                <li>Look at an NPC in-game and press the <strong>Roleplay Wheel Hotkey (CHIM MCM
                                        Menu)</strong> to add them to Background Life <strong>You must save your game
                                        for changes to take effect</strong></li>
                                <li>You can either:
                                    <ul>
                                        <li>Talk to the NPC and give commands like: <em>"Go to Riften and then to
                                                Whiterun to do X and Y"</em></li>
                                        <li>Wait for the configured trigger period (Global Settings, default: 5 in-game
                                            days) for them to automatically trigger background life.</li>
                                        <li>Define a [Life Goals] Section at NPC's profile goals, and let them wander
                                            according to their objectives.</li>
                                    </ul>
                                </li>
                                <li>They shall now travel around Skyrim.</li>
                                <li>LLM used for BgL is the one defined as CORE_CONNECTOR_DIRECTOR</li>
                            </ol>
                        </div>

                        <div class="instruction-section">
                            <strong>NPC Settings:</strong>
                            <ul>
                                <li><strong>🎮 Auto Actions:</strong> Based on the configured trigger period (Global
                                    Settings, default: 24 in-game hours), NPC generates inner thoughts. When enabled,
                                    they can autonomously travel to new locations. When disabled, only thoughts are
                                    generated.</li>
                                <li><strong>📍 Hourly Tracking:</strong> Tracks NPC coordinates every in-game hour
                                    (default is daily) for detailed movement history.</li>
                            </ul>
                        </div>

                        <div class="instruction-section">
                            <strong>Control Buttons:</strong>
                            <ul>
                                <li><strong>Trigger Action:</strong> Forces the NPC to generate an action immediately
                                    (may move or stay based on AI decision).</li>
                                <li><strong>Request Letter:</strong> Forces the NPC to send you a letter about their
                                    current activities. You will need to be in a town so the courier can deliver it.
                                </li>
                                <li><strong>Update All NPC Coords:</strong> Refreshes position tracking for all
                                    Background Life NPC.s</li>
                            </ul>
                        </div>

                        <div class="instruction-note">
                            <strong>💡 Note:</strong> Events are triggered automatically based on the configured trigger
                            period (Global Settings, default: 24 in-game hours). The buttons are mainly for testing or
                            forcing immediate updates.
                        </div>
                    </div>
                    <button class="toggle-instructions-btn" onclick="toggleInstructions()">Show Instructions</button>
                </div>
                <div class="map-width-controls">
                    <label>Map Width:</label>
                    <div class="map-width-slider">
                        <input type="range" id="mapWidthSlider" min="50" max="400" value="400"
                            onchange="updateMapWidthFromSlider()" oninput="updateMapWidthFromSlider()">
                        <span class="map-width-value"><span id="widthValue">400</span>%</span>
                    </div>
                </div>
                <form id="background-life-settings" class="bgl-settings-card" method="post">
                    <input type="hidden" name="action" value="save_bgl_settings">
                    <h3>Background Life Settings</h3>
                    <?php if ($bglSettingsFlash): ?>
                        <div
                            class="bgl-settings-message <?php echo $bglSettingsFlash['type'] === 'error' ? 'error' : ''; ?>">
                            <?php echo htmlspecialchars($bglSettingsFlash['message']); ?>
                        </div>
                    <?php endif; ?>
                    <div class="bgl-settings-row">
                        <label for="bglTriggerHours">Hours Cooldown</label>
                        <input id="bglTriggerHours" type="number" name="bgl_trigger_hours" min="1" max="720" step="0.1"
                            value="<?php echo htmlspecialchars((string) $bglTriggerHours); ?>">
                    </div>
                    <div class="bgl-settings-help">Controls how many in-game hours pass before eligible Background Life
                        NPCs automatically run their next update.</div>
                    <button type="submit" class="bgl-settings-save">Save</button>
                </form>
                <div class="npc-list-header">
                    <h3>📍 NPC Markers</h3>
                    <div style="color: #bbb; font-size: 13px; padding-bottom: 10px; border-bottom: 1px solid #4a4a4a;">
                        <strong>Tracked NPCs:</strong> <?php echo sizeof($translatedMarkers); ?><br />
                        <strong>Current Ingame Date:</strong> <?php echo $currentDate ?><br />
                        <em style="color: #ffa500;">For traveling to work you must click "Send All Locations" in the
                            CHIM MCM under Tools to add them to Background Life<br /></em>
                    </div>
                    <label class="toggle-label-inline" style="margin-top:8px;"
                        title="When enabled, shows all NPCs that have tracked coordinates, regardless of Background Life status">
                        <input type="checkbox" class="toggle-checkbox" id="showAllCoordsChk" <?php echo $showAllCoords ? 'checked' : ''; ?> onchange="toggleShowAllCoords(this.checked)">
                        <span class="toggle-text">🗺️ Show all NPCs with coords</span>
                    </label>
                    <button onclick="updateAllCoords()" class="update-all-coords-btn">📍 Update All NPC Coords</button>
                </div>
                <div class="npc-list-container">

                    <div class="marker-list">
                        <?php foreach ($translatedMarkers as $marker) { ?>
                            <div id="dtl_<?php echo $marker['id'] ?>" class="marker-item"
                                style="border-left-color:<?php echo $marker['color']; ?>;background-image:url(<?php echo $marker['figure']; ?>);background-position-y: top;">
                                <h4>
                                    <span class="marker-item-color"
                                        style="background-color:                                                                                                                                                                         <?php echo $marker['color']; ?>;"></span>
                                    <!--<a href="#mkr_<?php echo $marker['id'] ?>"><?php echo $marker['name']; ?> &nbsp; ↗️</a> -->
                                    <span onclick="pulseAnimation('mkr_<?php echo $marker['id'] ?>')"
                                        style="cursor:pointer"><?php echo $marker['name']; ?> &nbsp; ↗️</span>
                                </h4>
                                <div class="marker-item-coords">
                                    <ul>
                                        <li><strong>In-game:</strong> x=<?php echo $marker['ingame_x']; ?>,
                                            y=<?php echo $marker['ingame_y']; ?>, z=<?php echo $marker['ingame_z']; ?></li>
                                        <li><strong>Map:</strong> (<?php echo $marker['x']; ?>,<?php echo $marker['y']; ?>)
                                        </li>
                                        <li><strong>RefId:</strong> (<?php echo $marker['refid']; ?>)</li>
                                        <li><strong>Last Position Timestamp:</strong>
                                            (<?php echo $marker['last_pos_ts']; ?>)</li>
                                        <li><strong>Last Reported:</strong>
                                            <span title="<?php echo htmlentities($marker['last_letter']); ?>">
                                                (<?php echo $marker['last_report']; ?>)
                                                <?php if (isset($marker['last_letter']))
                                                    echo "<span style='cursor:help'>📜</span>"; ?>
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                                <div style="margin-top: 10px; display: flex; gap: 5px; flex-wrap: wrap;">
                                    <button onclick="requestAction('<?php echo addslashes($marker['name']); ?>')"
                                        class="marker-action-btn">🚶‍➡️Trigger Action</button>
                                    <button onclick="requestReporting('<?php echo addslashes($marker['name']); ?>')"
                                        class="marker-action-btn" style="background: #4488ff;">✉️ Request Letter</button>
                                    <button onclick="updateCoords('<?php echo addslashes($marker['name']); ?>')"
                                        title="Request coords update now" class="marker-action-btn-trans"
                                        style="border: 2px solid #00ff00; background: #44ff44;">📍</button>
                                    <button onclick="viewDiary('<?php echo addslashes($marker['name']); ?>')"
                                        class="marker-action-btn" style="background: #8844ff;"
                                        title="View letters & inner thoughts">✉️💭 View</button>
                                </div>
                                <div style="margin-top: 8px; display: flex; gap: 8px; flex-wrap: wrap;">
                                    <label class="toggle-label-inline">
                                        <input type="checkbox" class="toggle-checkbox"
                                            data-npc-id="<?php echo $marker['id']; ?>" data-setting="bg_life_commands" <?php echo $marker['bg_life_commands'] ? 'checked' : ''; ?>
                                            onchange="toggleBgLifeSetting(this)">
                                        <span class="toggle-text">🎮 Auto Actions</span>
                                    </label>
                                    <label class="toggle-label-inline">
                                        <input type="checkbox" class="toggle-checkbox"
                                            data-npc-id="<?php echo $marker['id']; ?>" data-setting="bg_life_letters" <?php echo $marker['bg_life_letters'] ? 'checked' : ''; ?>
                                            onchange="toggleBgLifeSetting(this)">
                                        <span class="toggle-text">✉️ Send Letters</span>
                                    </label>
                                    <label class="toggle-label-inline">
                                        <input type="checkbox" class="toggle-checkbox"
                                            data-npc-id="<?php echo $marker['id']; ?>" data-setting="gps_track" <?php echo $marker['gps_track'] ? 'checked' : ''; ?> onchange="toggleBgLifeSetting(this)">
                                        <span class="toggle-text">📍 Hourly Tracking</span>
                                    </label>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <div class="map-section">

                <div class="map-container" style="width: 200%;">
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
                        if ($marker['last_action_cat'] == 'error') {
                            $symbol = '❌';
                        } else if ($marker['last_action_cat'] == 'work') {
                            $symbol = '👷‍♀️';
                        } else if ($marker['last_action_cat'] == 'travel') {
                            $symbol = '👣';
                        } else if ($marker['last_action_cat'] == 'sleep') {
                            $symbol = '😴';
                        } else if ($marker['last_action_cat'] == 'produce_consume') {
                            $symbol = '🧠';
                        } else if ($marker['last_action_cat'] == 'socialize') {
                            $symbol = '🥂';
                        } else if ($marker['last_action_cat'] == 'dialogue') {
                            $symbol = '💬';
                        } else if ($marker['last_action_cat'] == 'relax') {
                            $symbol = '💆‍♂️';
                        } else {
                            $symbol = '';
                        }

                        echo '<div class="marker" style="left: ' . $percentX . '%; top: ' . $percentY . '%; transform: translate(calc(-50% + ' . $offsetX . 'px), calc(-50% + ' . $offsetY . 'px));">';
                        echo '<div class="marker-dot" id="mkr_' . $marker['id'] . '" style="width: ' . ($marker['size'] * 2) . 'px; height: ' . ($marker['size'] * 2) . 'px; background-color: ' . $marker['color'] . '; opacity: 0.8;">';
                        echo ($symbol ?? '') . '</div>';
                        echo '<div class="marker-label">' . PHP_EOL;
                        echo "<a style='color:white;text-decoration:none' href='#dtl_{$marker["id"]}'>{$marker["name"]} &nbsp; ↗️</a></br>";
                        echo '<small>(' . $marker['x'] . ', ' . $marker['y'] . '),' . $marker['tag'] . ' ' . ($marker['interior'] == 1 ? 'Interior' : '') . '</small>';
                        echo '<img class="thumb" src="' . $marker['figure'] . '" />';
                        echo '<br/><small>Last reported:' . $marker['last_report'] . '</small>';
                        echo '<br/><small>Last tracked:' . $marker['last_pos_ts'] . '</small>';
                        echo '<br/><small>Last activity:' . ($symbol ?? '') . ' ' . $marker['last_action_cat'] . '</small>';
                        echo '</div>';
                        echo '</div>' . PHP_EOL;

                        // Render history markers
                        if (!empty($marker['coords_history'])) {
                            $size_modifier = 2;
                            foreach ($marker['coords_history'] as $index => $histCoord) {
                                if ($histCoord['x'] == $marker['x'] && $histCoord['y'] == $marker['y'])
                                    continue; // Skip same place
                    
                                $histPercentX = ($histCoord['x'] / $mapWidth) * 100;
                                $histPercentY = ($histCoord['y'] / $mapHeight) * 100;
                                // Make history markers smaller: 5px radius instead of 10px
                                $size_modifier += 0.5;
                                $histSize = round($size_modifier, 0);


                                echo '<div class="history-marker" style="left: ' . $histPercentX . '%; top: ' . $histPercentY . '%; width: ' . ($histSize * 2) . 'px; height: ' . ($histSize * 2) . 'px; background-color: ' . $marker['color'] . '; opacity: 0.3;">';
                                echo '<div class="history-marker-label">' . PHP_EOL;
                                echo "<strong>" . $marker['name'] . "</strong><br/>";
                                echo "In-game: (" . $histCoord['ingame_x'] . ", " . $histCoord['ingame_y'] . ")<br/>";
                                if (!empty($histCoord['location'])) {
                                    echo "Location: " . $histCoord['location'] . ($histCoord['interior'] == 1 ? ' Interior' : '') . "<br/>";
                                }
                                echo "Tracked: " . $histCoord['last_updated'] . "<br/>";
                                echo "</div>";
                                echo '</div>' . PHP_EOL;
                            }
                        }
                    }

                    // Render POI markers
                    foreach ($translatedPOIMarkers as $location) {
                        $percentX = ($location['x'] / $mapWidth) * 100;
                        $percentY = ($location['y'] / $mapHeight) * 100;


                        echo '<div class="location-marker" style="left: ' . $percentX . '%; top: ' . $percentY . '%;">';
                        echo '<div class="poi-marker-icon">';
                        echo htmlspecialchars($location['symbol'] ?? '📍');
                        echo '</div>';
                        echo '<div class="location-marker-label">';
                        echo '<div class="location-name">' . htmlspecialchars($location['name']) . '</div>';
                        echo '<div class="location-desc">' . htmlspecialchars($location['description']) . '</div>';
                        echo '<div class="location-tags">' . htmlspecialchars($location['tags']) . '</div>';
                        echo '<div class="location-coords">';
                        echo 'Type: ' . htmlspecialchars($location['type']) . '<br/>';
                        echo 'Coords: ' . $location['ingame_x'] . ', ' . $location['ingame_y'] . '<br/>';
                        echo 'FormID: ' . htmlspecialchars($location['formID']) . '<br/>';
                        echo 'Tags: ' . htmlspecialchars($location['tags']) . '<br/>';
                        echo '' . htmlspecialchars($location['symbol']) . '<br/>';
                        echo '</div>';
                        echo '</div>';
                        echo '</div>' . PHP_EOL;
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
            </div>

        
        </div>
    </div>
    <div class="info-section">
        <span class="open-new-window" onclick="openInNewWindow()" title="Open in new window">↗️</span>
        <span class="open-new-window-2" onclick="location.href='mapview.php'" title="Refresh">🔄</span>
        <script>
            // NPC Diary Data - embedded directly in page
            const npcDiaryData = <?php echo json_encode(array_combine(
                array_column($translatedMarkers, 'name'),
                array_map(function ($m) {
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

            function updateMapWidthFromSlider() {
                const slider = document.getElementById('mapWidthSlider');
                setMapWidth(slider.value + '%');
            }

            function setMapWidth(width) {
                const mapContainer = document.querySelector('.map-container');
                const numericValue = Math.min(400, Math.max(50, parseInt(width, 10) || 400));
                const normalizedWidth = numericValue + '%';

                mapContainer.style.width = normalizedWidth;

                const slider = document.getElementById('mapWidthSlider');
                if (slider) {
                    slider.value = numericValue;
                    document.getElementById('widthValue').textContent = numericValue;
                }

                localStorage.setItem('mapViewWidth', normalizedWidth);
            }

            document.addEventListener('DOMContentLoaded', function () {
                const savedWidth = localStorage.getItem('mapViewWidth');
                setMapWidth(savedWidth || '200%');
            });

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

            function toggleBgLifeSetting(checkbox) {
                const npcId = checkbox.getAttribute('data-npc-id');
                const setting = checkbox.getAttribute('data-setting');
                const value = checkbox.checked;

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
                            // Show success message without alert
                            console.log(data.message);
                            // Optional: show a brief toast notification
                        } else {
                            alert('Error: ' + (data.message || 'Unknown error'));
                            // Revert checkbox on error
                            checkbox.checked = !value;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Request failed');
                        // Revert checkbox on error
                        checkbox.checked = !value;
                    });
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

            function togglePanel(panelId, button) {
                const panel = document.getElementById(panelId);
                if (!panel) {
                    return;
                }

                panel.classList.toggle('collapsed');
                if (button) {
                    button.textContent = panel.classList.contains('collapsed') ? 'Show Form' : 'Hide Form';
                }
            }

            function showProcessing() {

                processingMessage = document.createElement('div');
                processingMessage.textContent = 'Processing...';
                processingMessage.style.position = 'fixed';
                processingMessage.style.top = '50%';
                processingMessage.style.left = '50%';
                processingMessage.style.transform = 'translate(-50%, -50%)';
                processingMessage.style.backgroundColor = '#000';
                processingMessage.style.color = '#fff';
                processingMessage.style.padding = '10px 20px';
                processingMessage.style.borderRadius = '8px';
                processingMessage.style.zIndex = '10001';
                processingMessage.id = "processing_wheel";
                document.body.appendChild(processingMessage);
            }
            function hideProcessing() {
                processingMessage.innerHTML = '';
                processingMessage.style.zIndex = '-10001';

            }

            var processingMessage;
            function pulseAnimation(id) {
                const el = document.getElementById(id);

                el.classList.add("pulsing");
                el.scrollIntoView({ behavior: "smooth", block: "center"});        


                setTimeout(() => {
                    el.classList.remove("pulsing");
                }, 5000); // 3 seconds
            }

            // Letters & Thoughts Modal Functions
            let currentDiaryTab = 'letters';

            function viewDiary(npcName) {
                const modal = document.getElementById('diaryModal');
                const modalContent = document.getElementById('diaryModalContent');
                const modalTitle = document.getElementById('diaryModalTitle');

                // Show modal
                modal.style.display = 'block';
                modalTitle.textContent = npcName + "'s Letters & Thoughts";

                // Get data from embedded npcDiaryData
                const data = npcDiaryData[npcName];

                if (data) {
                    renderDiaryContent(data);
                } else {
                    modalContent.innerHTML = '<div style="text-align: center; padding: 40px; color: #888;"><p style="font-size: 1.2em;">💭</p><p>No data found for this NPC</p></div>';
                }
            }

            function renderDiaryContent(data) {
                const modalContent = document.getElementById('diaryModalContent');

                let html = '';

                // Tab buttons
                html += '<div style="display: flex; border-bottom: 2px solid #3a3a3a; margin-bottom: 20px;">';
                html += '<button id="lettersTab" onclick="switchDiaryTab(\'letters\')" style="flex: 1; padding: 12px; background: ' + (currentDiaryTab === 'letters' ? '#8844ff' : '#2a2a2a') + '; border: none; color: white; cursor: pointer; font-size: 1em; transition: background 0.3s;">✉️ Letters (' + data.letter_count + ')</button>';
                html += '<button id="thoughtsTab" onclick="switchDiaryTab(\'thoughts\')" style="flex: 1; padding: 12px; background: ' + (currentDiaryTab === 'thoughts' ? '#8844ff' : '#2a2a2a') + '; border: none; color: white; cursor: pointer; font-size: 1em; transition: background 0.3s;">💭 Inner Thoughts (' + data.thought_count + ')</button>';
                html += '</div>';

                // Letters content
                html += '<div id="lettersContent" style="display: ' + (currentDiaryTab === 'letters' ? 'block' : 'none') + '; max-height: 55vh; overflow-y: auto;">';
                if (data.letters && data.letters.length > 0) {
                    data.letters.forEach((entry) => {
                        html += renderEntry(entry, '#4488ff');
                    });
                } else {
                    html += '<div style="text-align: center; padding: 40px; color: #888;"><p style="font-size: 1.2em;">✉️</p><p>No letters found</p></div>';
                }
                html += '</div>';

                // Thoughts content
                html += '<div id="thoughtsContent" style="display: ' + (currentDiaryTab === 'thoughts' ? 'block' : 'none') + '; max-height: 55vh; overflow-y: auto;">';
                if (data.thoughts && data.thoughts.length > 0) {
                    data.thoughts.forEach((entry) => {
                        html += renderEntry(entry, '#8844ff');
                    });
                } else {
                    html += '<div style="text-align: center; padding: 40px; color: #888;"><p style="font-size: 1.2em;">💭</p><p>No inner thoughts found</p></div>';
                }
                html += '</div>';

                modalContent.innerHTML = html;
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

            function switchDiaryTab(tab) {
                currentDiaryTab = tab;

                const lettersContent = document.getElementById('lettersContent');
                const thoughtsContent = document.getElementById('thoughtsContent');
                const lettersTab = document.getElementById('lettersTab');
                const thoughtsTab = document.getElementById('thoughtsTab');

                if (tab === 'letters') {
                    lettersContent.style.display = 'block';
                    thoughtsContent.style.display = 'none';
                    lettersTab.style.background = '#8844ff';
                    thoughtsTab.style.background = '#2a2a2a';
                } else {
                    lettersContent.style.display = 'none';
                    thoughtsContent.style.display = 'block';
                    lettersTab.style.background = '#2a2a2a';
                    thoughtsTab.style.background = '#8844ff';
                }
            }

            function closeDiaryModal() {
                const modal = document.getElementById('diaryModal');
                modal.style.display = 'none';
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Close modal when clicking outside
            window.onclick = function (event) {
                const modal = document.getElementById('diaryModal');
                if (event.target == modal) {
                    closeDiaryModal();
                }
            }
        </script>

        <!-- Letters & Thoughts Modal -->
        <div id="diaryModal"
            style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); backdrop-filter: blur(5px);">
            <div
                style="background-color: #1a1a1a; margin: 5% auto; padding: 0; border: 2px solid #8844ff; width: 80%; max-width: 900px; border-radius: 12px; box-shadow: 0 4px 20px rgba(136, 68, 255, 0.3);">
                <div
                    style="background: linear-gradient(135deg, #8844ff 0%, #6622cc 100%); padding: 20px; border-radius: 10px 10px 0 0; display: flex; justify-content: space-between; align-items: center;">
                    <h2 id="diaryModalTitle"
                        style="margin: 0; color: white; font-family: 'MagicCards', sans-serif; letter-spacing: 1.5px;">
                        💭✉️
                        Letters & Thoughts</h2>
                    <span onclick="closeDiaryModal()"
                        style="color: white; font-size: 32px; font-weight: bold; cursor: pointer; transition: transform 0.2s;"
                        onmouseover="this.style.transform='scale(1.2)'"
                        onmouseout="this.style.transform='scale(1)'">&times;</span>
                </div>
                <div id="diaryModalContent" style="padding: 20px; color: #fff;">
                    Loading...
                </div>
            </div>
        </div>

        <style>
            .loading-spinner {
                border: 4px solid #2a2a2a;
                border-top: 4px solid #8844ff;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                animation: spin 1s linear infinite;
                margin: 0 auto 15px;
            }

            @keyframes spin {
                0% {
                    transform: rotate(0deg);
                }

                100% {
                    transform: rotate(360deg);
                }
            }
        </style>

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

        // Query Background Life history entries
        $bglHistoryQuery = "SELECT rowid,npc,gamets,category,convert_gamets2skyrim_date(gamets) as gamedate,to_timestamp(localts) as localdate,data FROM \"public\".\"bgl_history\" order by gamets desc,ts desc,rowid desc limit 50";
        $bglHistoryResult = pg_query($adminConn, $bglHistoryQuery);
        $bglHistoryRows = [];
        if ($bglHistoryResult) {
            while ($row = pg_fetch_assoc($bglHistoryResult)) {
                $bglHistoryRows[] = $row;
            }
        }
        ?>

        <!-- Background Life History -->
        <div class="info-panel" style="margin-top: 30px;">
            <h3>📚 Background Life History</h3>
            <?php if (empty($bglHistoryRows)): ?>
                <p style="color: #888; font-style: italic;">No history rows found</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                        <thead>
                            <tr style="background: #1a1a1a; border-bottom: 2px solid rgb(242, 124, 17);">
                                <th style="padding: 12px; text-align: left; color: rgb(242, 124, 17); font-weight: bold;">
                                    rowid
                                </th>
                                <th style="padding: 12px; text-align: left; color: rgb(242, 124, 17); font-weight: bold;">
                                    npc
                                </th>
                                <th style="padding: 12px; text-align: left; color: rgb(242, 124, 17); font-weight: bold;">
                                    gamets
                                </th>
                                <th style="padding: 12px; text-align: left; color: rgb(242, 124, 17); font-weight: bold;">
                                    gamedate</th>
                                <th style="padding: 12px; text-align: left; color: rgb(242, 124, 17); font-weight: bold;">
                                    localdate</th>
                                <th style="padding: 12px; text-align: left; color: rgb(242, 124, 17); font-weight: bold;">
                                    category</th>
                                <th style="padding: 12px; text-align: left; color: rgb(242, 124, 17); font-weight: bold;">
                                    data
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bglHistoryRows as $historyRow): ?>
                                <tr style="border-bottom: 1px solid #333;">
                                    <td style="padding: 12px; color: #ddd; white-space: nowrap;">
                                        <?php echo htmlspecialchars((string) ($historyRow['rowid'] ?? '')); ?>
                                    </td>
                                    <td style="padding: 12px; color: #ddd; white-space: nowrap;">
                                        <?php echo htmlspecialchars((string) ($historyRow['npc'] ?? '')); ?>
                                    </td>
                                    <td style="padding: 12px; color: #bbb; white-space: nowrap;">
                                        <?php echo htmlspecialchars((string) ($historyRow['gamets'] ?? '')); ?>
                                    </td>
                                    <td style="padding: 12px; color: #bbb; white-space: nowrap;">
                                        <?php echo htmlspecialchars((string) ($historyRow['gamedate'] ?? '')); ?>
                                    </td>
                                    <td style="padding: 12px; color: #bbb; white-space: nowrap;">
                                        <?php echo htmlspecialchars((string) ($historyRow['localdate'] ?? '')); ?>
                                    </td>
                                    <td style="padding: 12px; color: #bbb; white-space: nowrap;">
                                        <?php echo htmlspecialchars((string) strtolower($historyRow['category'] ?? '')); ?>
                                    </td>
                                    <td style="padding: 12px; color: #fff;">
                                        <?php echo nl2br(htmlspecialchars((string) ($historyRow['data'] ?? ''))); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="info-panel collapsible-panel <?php echo !empty($spawnNpcFlash['message']) ? '' : 'collapsed'; ?>"
            id="create-background-npc" style="margin-top: 30px;">
            <h3>🧬 Create Background Life NPC</h3>
            <button type="button" class="toggle-panel-btn"
                onclick="togglePanel('create-background-npc', this)"><?php echo !empty($spawnNpcFlash['message']) ? 'Hide Form' : 'Show Form'; ?></button>
            <div class="collapsible-body">
                <?php if (!empty($spawnNpcFlash['message'])): ?>
                    <?php
                    $isSpawnSuccess = ($spawnNpcFlash['type'] ?? '') === 'success';
                    $spawnFlashBg = $isSpawnSuccess ? 'rgba(42, 122, 59, 0.22)' : 'rgba(122, 42, 42, 0.24)';
                    $spawnFlashBorder = $isSpawnSuccess ? '#4caf50' : '#d65c5c';
                    $spawnFlashText = $isSpawnSuccess ? '#d6ffd9' : '#ffd6d6';
                    ?>
                    <div class="info-panel"
                        style="margin-bottom: 20px; background: <?php echo $spawnFlashBg; ?>; border: 1px solid <?php echo $spawnFlashBorder; ?>; color: <?php echo $spawnFlashText; ?>;">
                        <?php echo nl2br(htmlspecialchars($spawnNpcFlash['message'])); ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="">
                    <input type="hidden" name="action" value="create_background_npc">
                    <div
                        style="display: grid; grid-template-columns: repeat(3, minmax(180px, 1fr)); gap: 14px; margin-top: 16px;">
                        <div>
                            <label for="npc_name"
                                style="display: block; margin-bottom: 8px; color: #f2c48f; font-weight: 600;">Name</label>
                            <input id="npc_name" name="npc_name" type="text" required
                                value="<?php echo htmlspecialchars($spawnNpcFormData['name'] ?? ''); ?>"
                                style="width: 100%; padding: 10px 12px; background: #171717; color: #f5f5f5; border: 1px solid #444; border-radius: 8px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label for="npc_gender"
                                style="display: block; margin-bottom: 8px; color: #f2c48f; font-weight: 600;">Gender</label>
                            <select id="npc_gender" name="npc_gender" required
                                style="width: 100%; padding: 10px 12px; background: #171717; color: #f5f5f5; border: 1px solid #444; border-radius: 8px; box-sizing: border-box;">
                                <?php $npcGenderValue = (string) ($spawnNpcFormData['gender'] ?? 'male'); ?>
                                <option value="male" <?php echo ($npcGenderValue === 'male') ? 'selected' : ''; ?>>male
                                </option>
                                <option value="female" <?php echo ($npcGenderValue === 'female') ? 'selected' : ''; ?>>
                                    female
                                </option>
                            </select>
                        </div>
                        <div>
                            <label for="npc_class"
                                style="display: block; margin-bottom: 8px; color: #f2c48f; font-weight: 600;">Class</label>
                            <select id="npc_class" name="npc_class" required
                                style="width: 100%; padding: 10px 12px; background: #171717; color: #f5f5f5; border: 1px solid #444; border-radius: 8px; box-sizing: border-box;">
                                <?php
                                $npcClassValue = (string) ($spawnNpcFormData['class'] ?? 'farmer');
                                $npcClassOptions = ['beggar', 'warrior', 'assassin', 'mage', 'farmer', 'soldier', 'merchant', 'noble', 'forsworn'];
                                foreach ($npcClassOptions as $npcClassOption):
                                    ?>
                                    <option value="<?php echo htmlspecialchars($npcClassOption); ?>" <?php echo ($npcClassValue === $npcClassOption) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($npcClassOption); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="npc_race"
                                style="display: block; margin-bottom: 8px; color: #f2c48f; font-weight: 600;">Race</label>
                            <select id="npc_race" name="npc_race" required
                                style="width: 100%; padding: 10px 12px; background: #171717; color: #f5f5f5; border: 1px solid #444; border-radius: 8px; box-sizing: border-box;">
                                <?php
                                $npcRaceValue = (string) ($spawnNpcFormData['race'] ?? 'Nord');
                                $npcRaceOptions = ['Nord', 'Imperial', 'Argonian', 'RedGuard', 'Orc', 'Breton'];
                                foreach ($npcRaceOptions as $npcRaceOption):
                                    ?>
                                    <option value="<?php echo htmlspecialchars($npcRaceOption); ?>" <?php echo ($npcRaceValue === $npcRaceOption) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($npcRaceOption); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="npc_location"
                                style="display: block; margin-bottom: 8px; color: #f2c48f; font-weight: 600;">Location</label>
                            <input id="npc_location" name="npc_location" type="text" list="npc-location-options"
                                required value="<?php echo htmlspecialchars($spawnNpcFormData['location'] ?? ''); ?>"
                                style="width: 100%; padding: 10px 12px; background: #171717; color: #f5f5f5; border: 1px solid #444; border-radius: 8px; box-sizing: border-box;">
                            <datalist id="npc-location-options">
                                <?php foreach ($npcLocationOptions as $npcLocationOption): ?>
                                    <option value="<?php echo htmlspecialchars($npcLocationOption[0]); ?>"
                                        label="<?php echo htmlspecialchars($npcLocationOption[1] . ' (' . ($npcLocationOption[2] ? 'Interior' : 'Exterior') . ' ' . $npcLocationOption[3] . ', ' . $npcLocationOption[4] . ')'); ?>">
                                    </option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div>
                            <label for="npc_disposition"
                                style="display: block; margin-bottom: 8px; color: #f2c48f; font-weight: 600;">Disposition</label>
                            <input id="npc_disposition" name="npc_disposition" type="text"
                                value="<?php echo htmlspecialchars($spawnNpcFormData['disposition'] ?? 'friendly'); ?>"
                                style="width: 100%; padding: 10px 12px; background: #171717; color: #f5f5f5; border: 1px solid #444; border-radius: 8px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label for="npc_starting_point"
                                style="display: block; margin-bottom: 8px; color: #f2c48f; font-weight: 600;">Starting
                                Point
                                (FormID) (will use location if empty)</label>
                            <input id="npc_starting_point" name="npc_starting_point" type="text"
                                value="<?php echo htmlspecialchars($spawnNpcFormData['starting_point'] ?? '0x0002b0dd'); ?>"
                                style="width: 100%; padding: 10px 12px; background: #171717; color: #f5f5f5; border: 1px solid #444; border-radius: 8px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label for="npc_inventory_gold"
                                style="display: block; margin-bottom: 8px; color: #f2c48f; font-weight: 600;">Gold Qty
                                (0x0000000F)</label>
                            <input id="npc_inventory_gold" name="npc_inventory_gold" type="number" min="0" step="1"
                                value="<?php echo htmlspecialchars($spawnNpcFormData['gold_qty'] ?? '100'); ?>"
                                style="width: 100%; padding: 10px 12px; background: #171717; color: #f5f5f5; border: 1px solid #444; border-radius: 8px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label for="npc_inventory_iron_ore"
                                style="display: block; margin-bottom: 8px; color: #f2c48f; font-weight: 600;">Iron Ore
                                Qty
                                (0x00071cf3)</label>
                            <input id="npc_inventory_iron_ore" name="npc_inventory_iron_ore" type="number" min="0"
                                step="1"
                                value="<?php echo htmlspecialchars($spawnNpcFormData['iron_ore_qty'] ?? '10'); ?>"
                                style="width: 100%; padding: 10px 12px; background: #171717; color: #f5f5f5; border: 1px solid #444; border-radius: 8px; box-sizing: border-box;">
                        </div>
                    </div>

                    <div style="margin-top: 16px;">
                        <label for="npc_appearance"
                            style="display: block; margin-bottom: 8px; color: #f2c48f; font-weight: 600;">Appearance</label>
                        <input id="npc_appearance" name="npc_appearance" type="text"
                            value="<?php echo htmlspecialchars($spawnNpcFormData['appearance'] ?? ''); ?>"
                            style="width: 100%; padding: 10px 12px; background: #171717; color: #f5f5f5; border: 1px solid #444; border-radius: 8px; box-sizing: border-box;">
                    </div>

                    <div style="margin-top: 16px;">
                        <label for="npc_speech_style"
                            style="display: block; margin-bottom: 8px; color: #f2c48f; font-weight: 600;">Speech
                            Style</label>
                        <input id="npc_speech_style" name="npc_speech_style" type="text" required
                            value="<?php echo htmlspecialchars($spawnNpcFormData['speech_style'] ?? ''); ?>"
                            style="width: 100%; padding: 10px 12px; background: #171717; color: #f5f5f5; border: 1px solid #444; border-radius: 8px; box-sizing: border-box;">
                    </div>

                    <div style="margin-top: 16px;">
                        <label for="npc_background"
                            style="display: block; margin-bottom: 8px; color: #f2c48f; font-weight: 600;">Background</label>
                        <textarea id="npc_background" name="npc_background" rows="3" required
                            style="width: 100%; padding: 12px; background: #171717; color: #f5f5f5; border: 1px solid #444; border-radius: 8px; box-sizing: border-box; resize: vertical;"><?php echo htmlspecialchars($spawnNpcFormData['background'] ?? ''); ?></textarea>
                    </div>

                    <div style="margin-top: 16px;">
                        <label for="npc_goal"
                            style="display: block; margin-bottom: 8px; color: #f2c48f; font-weight: 600;">Goals</label>
                        <textarea id="npc_goal" name="npc_goal" rows="12" required
                            style="width: 100%; padding: 12px; background: #171717; color: #f5f5f5; border: 1px solid #444; border-radius: 8px; box-sizing: border-box; resize: vertical;"><?php echo htmlspecialchars($spawnNpcFormData['goal'] ?? ''); ?></textarea>
                    </div>

                    <div style="margin-top: 18px; display: flex; justify-content: flex-end;">
                        <button type="submit"
                            style="padding: 10px 18px; border-radius: 8px; border: 1px solid rgb(242, 124, 17); background: rgb(242, 124, 17); color: #121212; font-weight: 700; cursor: pointer;">
                            Spawn Background NPC
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div id="rumors-section" style="margin-top: 40px;">
            <div class="page-header" style="margin-bottom: 20px;">
                <h1>📰 Rumors</h1>
            </div>

            <?php if (!empty($rumorFlash['message'])): ?>
                <?php
                $isSuccessFlash = ($rumorFlash['type'] ?? '') === 'success';
                $flashBg = $isSuccessFlash ? 'rgba(42, 122, 59, 0.22)' : 'rgba(122, 42, 42, 0.24)';
                $flashBorder = $isSuccessFlash ? '#4caf50' : '#d65c5c';
                $flashText = $isSuccessFlash ? '#d6ffd9' : '#ffd6d6';
                ?>
                <div class="info-panel"
                    style="margin-bottom: 20px; background: <?php echo $flashBg; ?>; border: 1px solid <?php echo $flashBorder; ?>; color: <?php echo $flashText; ?>;">
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
                                    <th
                                        style="padding: 12px; text-align: left; color: rgb(242, 124, 17); font-weight: bold;">
                                        Hold</th>
                                    <th
                                        style="padding: 12px; text-align: left; color: rgb(242, 124, 17); font-weight: bold;">
                                        Type</th>
                                    <th
                                        style="padding: 12px; text-align: left; color: rgb(242, 124, 17); font-weight: bold;">
                                        Lasts</th>
                                    <th
                                        style="padding: 12px; text-align: left; color: rgb(242, 124, 17); font-weight: bold;">
                                        Content</th>
                                    <th
                                        style="padding: 12px; text-align: left; color: rgb(242, 124, 17); font-weight: bold;">
                                        In-Game Date</th>
                                    <th
                                        style="padding: 12px; text-align: left; color: rgb(242, 124, 17); font-weight: bold;">
                                        Age</th>
                                    <th
                                        style="padding: 12px; text-align: left; color: rgb(242, 124, 17); font-weight: bold;">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($currentRumors as $rumor): ?>
                                    <?php
                                    $rumorDate = convert_gamets2skyrim_date($rumor['gamets']);
                                    $hoursAgo = round(($last_gamets - $rumor['gamets']) * 0.0000024, 1);
                                    ?>
                                    <tr style="border-bottom: 1px solid #333;">
                                        <td style="padding: 12px; color: #ddd;">
                                            <?php echo htmlspecialchars($rumor['hold'] ?? 'Unknown'); ?>
                                        </td>
                                        <td style="padding: 12px; color: #bbb; font-size: 12px;">
                                            <?php echo htmlspecialchars($rumor['type'] ?? 'General'); ?>
                                        </td>
                                        <td style="padding: 12px; color: #bbb; font-size: 12px; white-space: nowrap;">
                                            <?php echo (int) ($rumor['rumor_length_days'] ?? 7); ?> days
                                        </td>
                                        <td style="padding: 12px; color: #fff;">
                                            <?php echo htmlspecialchars($rumor['content']); ?>
                                        </td>
                                        <td style="padding: 12px; color: #bbb; font-size: 12px; white-space: nowrap;">
                                            <?php echo htmlspecialchars($rumorDate); ?>
                                        </td>
                                        <td style="padding: 12px; color: #888; font-size: 12px; white-space: nowrap;">
                                            <?php echo $hoursAgo; ?> hours ago
                                        </td>
                                        <td style="padding: 12px;">
                                            <a href="<?php echo htmlspecialchars(getRumorPagePath() . '?edit_rumor_id=' . urlencode((string) ($rumor['id'] ?? '')) . '#create-rumor'); ?>"
                                                style="color: rgb(242, 124, 17); font-weight: 600; text-decoration: none;">Edit</a>
                                            <form method="post" action="" style="display: inline; margin-left: 12px;"
                                                onsubmit="return confirm('Delete this rumor?');">
                                                <input type="hidden" name="action" value="delete_rumor">
                                                <input type="hidden" name="rumor_id"
                                                    value="<?php echo (int) ($rumor['id'] ?? 0); ?>">
                                                <button type="submit"
                                                    style="background: none; border: none; padding: 0; color: #d65c5c; font-weight: 600; text-decoration: none; cursor: pointer;">Delete</button>
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
                                    <th style="padding: 12px; text-align: left; color: #888; font-weight: bold;">Content
                                    </th>
                                    <th style="padding: 12px; text-align: left; color: #888; font-weight: bold;">In-Game
                                        Date
                                    </th>
                                    <th style="padding: 12px; text-align: left; color: #888; font-weight: bold;">Age</th>
                                    <th style="padding: 12px; text-align: left; color: #888; font-weight: bold;">Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($outdatedRumors as $rumor): ?>
                                    <?php
                                    $rumorDate = convert_gamets2skyrim_date($rumor['gamets']);
                                    $hoursAgo = round(($last_gamets - $rumor['gamets']) * 0.0000024, 1);
                                    ?>
                                    <tr style="border-bottom: 1px solid #333; opacity: 0.6;">
                                        <td style="padding: 12px; color: #888;">
                                            <?php echo htmlspecialchars($rumor['hold'] ?? 'Unknown'); ?>
                                        </td>
                                        <td style="padding: 12px; color: #777; font-size: 12px;">
                                            <?php echo htmlspecialchars($rumor['type'] ?? 'General'); ?>
                                        </td>
                                        <td style="padding: 12px; color: #777; font-size: 12px; white-space: nowrap;">
                                            <?php echo (int) ($rumor['rumor_length_days'] ?? 7); ?> days
                                        </td>
                                        <td style="padding: 12px; color: #999;">
                                            <?php echo htmlspecialchars($rumor['content']); ?>
                                        </td>
                                        <td style="padding: 12px; color: #777; font-size: 12px; white-space: nowrap;">
                                            <?php echo htmlspecialchars($rumorDate); ?>
                                        </td>
                                        <td style="padding: 12px; color: #666; font-size: 12px; white-space: nowrap;">
                                            <?php echo $hoursAgo; ?> hours ago
                                        </td>
                                        <td style="padding: 12px;">
                                            <a href="<?php echo htmlspecialchars(getRumorPagePath() . '?edit_rumor_id=' . urlencode((string) ($rumor['id'] ?? '')) . '#create-rumor'); ?>"
                                                style="color: #bbb; font-weight: 600; text-decoration: none;">Edit</a>
                                            <form method="post" action="" style="display: inline; margin-left: 12px;"
                                                onsubmit="return confirm('Delete this rumor?');">
                                                <input type="hidden" name="action" value="delete_rumor">
                                                <input type="hidden" name="rumor_id"
                                                    value="<?php echo (int) ($rumor['id'] ?? 0); ?>">
                                                <button type="submit"
                                                    style="background: none; border: none; padding: 0; color: #d65c5c; font-weight: 600; text-decoration: none; cursor: pointer;">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>



            <div class="info-panel collapsible-panel <?php echo ($editingRumorId > 0 || (($rumorFlash['type'] ?? '') === 'error')) ? '' : 'collapsed'; ?>"
                id="create-rumor" style="margin-top: 30px;">
                <h3><?php echo ($editingRumorId > 0) ? 'Edit Rumor' : 'Create Rumor'; ?></h3>
                <button type="button" class="toggle-panel-btn"
                    onclick="togglePanel('create-rumor', this)"><?php echo ($editingRumorId > 0 || (($rumorFlash['type'] ?? '') === 'error')) ? 'Hide Form' : 'Show Form'; ?></button>
                <div class="collapsible-body">
                    <form method="post" action="">
                        <input type="hidden" name="action"
                            value="<?php echo ($editingRumorId > 0) ? 'update_rumor' : 'create_rumor'; ?>">
                        <?php if ($editingRumorId > 0): ?>
                            <input type="hidden" name="rumor_id" value="<?php echo (int) $editingRumorId; ?>">
                        <?php endif; ?>
                        <div
                            style="display: grid; grid-template-columns: minmax(220px, 280px) minmax(200px, 1fr) minmax(160px, 180px); gap: 18px; margin-top: 16px;">
                            <div>
                                <label for="rumor_hold"
                                    style="display: block; margin-bottom: 8px; color: #f2c48f; font-weight: 600;">Hold</label>
                                <select id="rumor_hold" name="rumor_hold" required
                                    style="width: 100%; padding: 10px 12px; background: #171717; color: #f5f5f5; border: 1px solid #444; border-radius: 8px;">
                                    <option value="">Select hold</option>
                                    <?php foreach (chimGetRumorHoldOptions() as $holdOption): ?>
                                        <option value="<?php echo htmlspecialchars($holdOption); ?>" <?php echo (($rumorFormData['hold'] ?? '') === $holdOption) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($holdOption); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="rumor_type"
                                    style="display: block; margin-bottom: 8px; color: #f2c48f; font-weight: 600;">Type</label>
                                <input id="rumor_type" name="rumor_type" type="text"
                                    value="<?php echo htmlspecialchars($rumorFormData['type'] ?? ''); ?>"
                                    placeholder="General"
                                    style="width: 100%; padding: 10px 12px; background: #171717; color: #f5f5f5; border: 1px solid #444; border-radius: 8px; box-sizing: border-box;">
                            </div>
                            <div>
                                <label for="rumor_length_days"
                                    style="display: block; margin-bottom: 8px; color: #f2c48f; font-weight: 600;">Rumor
                                    Length (Days)</label>
                                <input id="rumor_length_days" name="rumor_length_days" type="number" min="1" step="1"
                                    value="<?php echo htmlspecialchars($rumorFormData['length_days'] ?? '7'); ?>"
                                    placeholder="7"
                                    style="width: 100%; padding: 10px 12px; background: #171717; color: #f5f5f5; border: 1px solid #444; border-radius: 8px; box-sizing: border-box;">
                            </div>
                        </div>
                        <div style="margin-top: 18px;">
                            <label for="rumor_content"
                                style="display: block; margin-bottom: 8px; color: #f2c48f; font-weight: 600;">Content</label>
                            <textarea id="rumor_content" name="rumor_content" rows="4" required
                                placeholder="Write the rumor text here..."
                                style="width: 100%; padding: 12px; background: #171717; color: #f5f5f5; border: 1px solid #444; border-radius: 8px; box-sizing: border-box; resize: vertical;"><?php echo htmlspecialchars($rumorFormData['content'] ?? ''); ?></textarea>
                        </div>
                        <div style="margin-top: 18px; display: flex; justify-content: flex-end; gap: 12px;">
                            <?php if ($editingRumorId > 0): ?>
                                <a href="<?php echo htmlspecialchars(getRumorPagePath() . '#create-rumor'); ?>"
                                    style="padding: 10px 18px; border-radius: 8px; border: 1px solid #555; background: #242424; color: #f2f2f2; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center;">
                                    Cancel Edit
                                </a>
                            <?php endif; ?>
                            <button type="submit"
                                style="padding: 10px 18px; border-radius: 8px; border: 1px solid rgb(242, 124, 17); background: rgb(242, 124, 17); color: #121212; font-weight: 700; cursor: pointer;">
                                <?php echo ($editingRumorId > 0) ? 'Save Rumor' : 'Create Rumor'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
include(__DIR__ . DIRECTORY_SEPARATOR . "tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>