<?php


$enginePath = __DIR__ . "/../../../";
$GLOBALS["ENGINE_PATH"] = $ENGINE_PATH = $enginePath;

echo "Using ENGINE_PATH: {$ENGINE_PATH}\n";
// Define paths
$basePath = dirname(__FILE__);
$stateFile = "{$ENGINE_PATH}/log/snqe_state.json";
$uiPath = dirname(dirname(dirname($basePath))) . DIRECTORY_SEPARATOR . "ui" . DIRECTORY_SEPARATOR . "addons" . DIRECTORY_SEPARATOR . "snqe";
$cmdPath = $uiPath . DIRECTORY_SEPARATOR . "cmd";

require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php";
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "lazy_xml.php";


$db = $GLOBALS["db"];

require_once $enginePath . "lib/core/npc_master.class.php";
require_once $enginePath . "lib/core/api_badge.class.php";
require_once $enginePath . "lib/core/core_profiles.class.php";
require_once $enginePath . "lib/core/llm_connector.class.php";

// Ensure state file exists
if (!file_exists($stateFile)) {
    $initialState = [
        'userprompt' => '',
        'questtitle' => '',
        'briefing' => '',
        'airesponse' => '',
        'miniquest' => '',
        'locationlist' => [],
        'npclist' => [],
        'spawneditemslist' => [],
        'journallist' => [],
        'rumorlist' => [],
        'nextlist' => [],
        'sysprompt' => file_exists($uiPath . "/prompts/agent1.txt") ? file_get_contents($uiPath . "/prompts/agent1.txt") : "",
    ];
    file_put_contents($stateFile, json_encode($initialState, JSON_PRETTY_PRINT));
    chmod($stateFile, permissions: 0777);
}

// Load state
$state = json_decode(file_get_contents($stateFile), true);

// Ensure sysprompt is loaded
if (empty($state['sysprompt'])) {
    $promptFile = $uiPath . "/prompts/agent1.txt";
    if (file_exists($promptFile)) {
        $state['sysprompt'] = file_get_contents($promptFile);
    } else {
        $state['sysprompt'] = "";
    }
}

// Helper function to make HTTP requests (mimicking fetch)
function callAgent($url, $data)
{
    $options = [
        'http' => [
            'header' => "Content-type: application/json\r\n",
            'method' => 'POST',
            'content' => json_encode($data),
            'timeout' => 300,
        ],
    ];
    $context = stream_context_create($options);

    // Suppress warnings temporarily to capture error details
    set_error_handler(function ($errno, $errstr) {
        throw new Exception($errstr, $errno);
    });

    try {
        $fp = fopen($url, 'r', false, $context);
        if (!$fp) {
            throw new Exception("Failed to open stream to $url");
        }
        $result = stream_get_contents($fp);
        fclose($fp);
        restore_error_handler();

        if ($result === false) {
            throw new Exception("Failed to read from stream: $url");
        }

        $decoded = json_decode($result, true);
        if ($decoded === null && $result !== 'null') {
            throw new Exception("Invalid JSON response from $url: " . substr($result, 0, 200));
        }

        return $decoded;
    } catch (Exception $e) {
        restore_error_handler();
        echo "ERROR: " . $e->getMessage() . "\n";
        return null;
    }
}

// Helper to update lists ensuring uniqueness
function updateList(&$list, $newItems)
{
    if (!is_array($newItems)) {
        return;
    }
    if (!is_array($list)) {
        $list = [];
    }

    foreach ($newItems as $item) {
        if (!in_array($item, $list)) {
            $list[] = $item;
        }
    }
}

// Determine execution mode
$mode = isset($argv[1]) ? $argv[1] : 'full';
$needsEnd = isset($argv[2]) ? $argv[2] == "end" : false;
$baseUrl = "http://localhost/HerikaServer/ui/addons/snqe/cmd"; // Adjust base URL as needed, assuming localhost for internal calls or file inclusion if possible.
// However, the user asked to use fopen calls using set_stream_context to mimic fetch calls.
// Since this is running on the server, we might need the full URL.
// Let's assume the server is running on localhost port 80 or similar.
// If we can't determine the URL, we might need to include the files directly, but the prompt specifically asked for "fopen calls using set_stream_context to mimic the fetch calls".
// I will assume a default localhost URL structure.
// A better approach for a CLI script running on the same server might be to require the files, but the prompt is specific about mimicking fetch.
// Let's try to construct the URL.

    
$hostname = gethostname();
if ($hostname === false || $hostname === '') {
    $hostname = php_uname('n');
}
if ($hostname == "absws")        
    $serverUrl = "http://127.0.0.1/HerikaServer/ui/addons/snqe/cmd";
else
    $serverUrl = "http://127.0.0.1:8081/HerikaServer/ui/addons/snqe/cmd";

echo "Running in mode: $mode\n";

// Step 1: Agent 0 (Scenario)
if ($mode === 'start_from_context') {
    // Get around rolemastered NPCs 
    $rolemasteredNpcList = [];
    $npcList = DataBeingsInCloseRange(true);
    foreach (explode("|", $npcList) as $npc) {
        if ($npc) {
            $npcMaster = new NpcMaster();
            $npcData = $npcMaster->getByName(trim($npc));
            $npcMetadata = $npcMaster->getMetadata($npcData);
            if (herikaResolveNpcRolemasterState(trim($npc), [
                'npc_data' => $npcData,
                'metadata' => $npcMetadata,
                'load_lookup' => false,
                'use_global' => false,
            ])) {
                $rolemasteredNpcList[] = $npc;
            }
        }
    }

    updateList($state['npclist'], $rolemasteredNpcList);
    $payload = [
        'prompt' => "Given the context, generate a brief quest scenario summary title.\n\n",
        'npclist' => $rolemasteredNpcList,
    ];

    $data = callAgent($serverUrl . "/agent0.php", $payload);

    if ($data) {
        if (isset($data['response'])) {
            $state['userprompt'] = $data['response'];
        }

        if (isset($data['briefing'])) {
            $state['briefing'] = $data['briefing'];
        }

        if (isset($data['questtitle'])) {
            $state['questtitle'] = $data['questtitle']??($rolemasteredNpcList[0]."'s quest");
        } else {
            $state['questtitle'] = ($rolemasteredNpcList[0]??"NPC")."'s quest";
        }

        if (isset($data['last_step'])) {
            $state['last_step'] = $data['last_step'];
        }

        if (isset($data['locations'])) {
            updateList($state['locationlist'], $data['locations']);
        }

        print_r($data);
        echo "Agent 0 complete.\n";
        file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
        chmod($stateFile, 0777);
        print_r($data);
        echo date("d/M/y H:i:s") . PHP_EOL;
        $mode = "full"; // Continue with full mode after context initialization
        
    } else {
        echo "FATAL: Agent 0 failed. Interrupting process.\n";
        exit(1);
    }
} else if ($mode === 'full' || $mode === '1') { // Step 1: Agent 0 (Scenario)
    echo "Step 1: Calling Agent 0...\n";
    if (isset($state['questtitle']) && !empty($state['questtitle']) && !isset($state['npclist'])) {
        echo "Using existing quest title: {$state['questtitle']}. Skipping Agent 0.\n";
    } else {
        echo "Running Agent 0... at least 1 NPCs spawned.\n";

        $payload = [
            'prompt' => $state['userprompt'],
            'locationlist' => $state['locationlist'] ?? [],
            'npclist' => $state['npclist'] ?? [],
            'spawneditemslist' => $state['spawneditemslist'] ?? [],
            'journallist' => $state['journallist'] ?? [],
            'rumorlist' => $state['rumorlist'] ?? [],
            'nextlist' => $state['nextlist'] ?? [],
            'questtitle' => $state['questtitle'],
            'briefing' => $state['briefing'],
        ];

        if ($needsEnd) {
            $payload['needs_end'] = true;
        }
        $data = callAgent($serverUrl . "/agent0.php", $payload);

        if ($data) {
            if (isset($data['response'])) {
                $state['userprompt'] = $data['response'];
            }

            if (isset($data['briefing'])) {
                $state['briefing'] = $data['briefing'];
            }

            if (isset($data['questtitle'])) {
                $state['questtitle'] = $data['questtitle'];
            }

            if (isset($data['last_step'])) {
                $state['last_step'] = $data['last_step'];
            }

            if (isset($data['locations'])) {
                updateList($state['locationlist'], $data['locations']);
            }

            echo "Agent 0 complete.\n";
            file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
            chmod($stateFile, 0777);
            print_r($data);
            echo date("d/M/y H:i:s") . PHP_EOL;

        } else {
            echo "FATAL: Agent 0 failed. Interrupting process.\n";
            exit(1);
        }
    }
}

// Step 2: Agent 1 (Top Level)
if ($mode === 'full' || $mode === '2') {
    echo "Step 2: Calling Agent 1...\n";

    $payload = [
        'sysprompt' => $state['sysprompt'],
        'userprompt' => $state['userprompt'],
        'locations' => $state['locationlist'],
        'npclist' => $state['npclist'],
        'spawneditemslist' => $state['spawneditemslist'],
        'questType' => 'toplevel',
    ];

    if (!empty($state['briefing'])) {
        $payload['briefing'] = $state['briefing'];
    }

    if (!empty($state['questtitle'])) {
        $payload['questTitle'] = $state['questtitle'];
    }

    $data = callAgent($serverUrl . "/agent1.php", $payload);

    if ($data) {
        if (isset($data['filteredXml'])) {
            $state['airesponse'] = $data['filteredXml'];
        }

        if (isset($data['npc'])) {
            updateList($state['npclist'], $data['npc']);
        }

        if (isset($data['spawned_items'])) {
            updateList($state['spawneditemslist'], $data['spawned_items']);
        }

        if (isset($data['journal'])) {
            updateList($state['journallist'], $data['journal']);
        }

        if (isset($data['rumors'])) {
            updateList($state['rumorlist'], $data['rumors']);
        }

        if (isset($data['next'])) {
            updateList($state['nextlist'], $data['next']);
        }

        echo "Agent 1 complete.\n";
        file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
        chmod($stateFile, 0777);
        print_r($data);
        echo date("d/M/y H:i:s") . PHP_EOL;

    } else {
        echo "FATAL: Agent 1 failed. Interrupting process.\n";
        exit(1);
    }
}

// Step 3: Agent 2 (Miniquest)
if ($mode === 'full' || $mode === '3') {
    echo "Step 3: Calling Agent 2...\n";

    $journals = isset($state['journallist']) && is_array($state['journallist']) ? array_values($state['journallist']) : [];
    $journalCount = count($journals);

    if ($journalCount >= 2) {
        $lastJournalEntry = $journals[$journalCount - 1]; // penultimate
    } elseif ($journalCount === 1) {
        $lastJournalEntry = "A new quest starts";
    } else {
        $lastJournalEntry = "A new quest starts";
    }
    if ($lastJournalEntry === false) {
        $lastJournalEntry = '';
    }

    $payload = [
        'sysprompt' => $state['sysprompt'],
        'userprompt' => $state['userprompt'],
        'airesponse' => $state['airesponse'],
        'spawneditemslist' => $state['spawneditemslist'],
        'npclist' => $state['npclist'],
        'lastJournalEntry' => $lastJournalEntry,
        'questType' => 'miniquest',
        'briefing' => $state['briefing'] ?? '',
    ];

    if (!empty($state['questtitle'])) {
        $payload['questTitle'] = $state['questtitle'];
    }

    if (!empty($state['briefing'])) {
        $payload['briefing'] = $state['briefing'];
    }

    $data = callAgent($serverUrl . "/agent2.php", $payload);

    if ($data) {
        if (isset($data['response'])) {
            $state['miniquest'] = $data['response'];
        }

        echo "Agent 2 complete.\n";
        file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
        chmod($stateFile, permissions: 0777);
        print_r($data);
        echo date("d/M/y H:i:s") . PHP_EOL;

    } else {
        echo "FATAL: Agent 2 failed. Interrupting process.\n";
        exit(1);
    }
}

echo "Process finished.\n";
