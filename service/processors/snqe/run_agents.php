<?php

$ENGINE_PATH = __DIR__ . "/../../../";
echo "Using ENGINE_PATH: {$ENGINE_PATH}\n";
// Define paths
$basePath = dirname(__FILE__);
$stateFile = "{$ENGINE_PATH}/log/snqe_state.json";
$uiPath = dirname(dirname(dirname($basePath))) . DIRECTORY_SEPARATOR . "ui" . DIRECTORY_SEPARATOR . "addons" . DIRECTORY_SEPARATOR . "snqe";
$cmdPath = $uiPath . DIRECTORY_SEPARATOR . "cmd";


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
$needsEnd = isset($argv[2]) ? $argv[2] : 'end';
$baseUrl = "http://localhost/HerikaServer/ui/addons/snqe/cmd"; // Adjust base URL as needed, assuming localhost for internal calls or file inclusion if possible.
// However, the user asked to use fopen calls using set_stream_context to mimic fetch calls.
// Since this is running on the server, we might need the full URL.
// Let's assume the server is running on localhost port 80 or similar.
// If we can't determine the URL, we might need to include the files directly, but the prompt specifically asked for "fopen calls using set_stream_context to mimic the fetch calls".
// I will assume a default localhost URL structure.
// A better approach for a CLI script running on the same server might be to require the files, but the prompt is specific about mimicking fetch.
// Let's try to construct the URL.
$serverUrl = "http://127.0.0.1/HerikaServer/ui/addons/snqe/cmd";

echo "Running in mode: $mode\n";

// Step 1: Agent 0 (Scenario)
if ($mode === 'full' || $mode === '1') {
    echo "Step 1: Calling Agent 0...\n";
    if (isset($state['questtitle']) && !empty($state['questtitle']) && !isset($state['npclist'])) { 
        echo "Using existing quest title: {$state['questtitle']}. Skipping Agent 0.\n";
    } else {
        echo "No existing quest title found.\n";

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

            if (isset($data['locations'])) {
                updateList($state['locationlist'], $data['locations']);
            }

            echo "Agent 0 complete.\n";
            file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
            print_r($data);
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
        print_r($data);

    } else {
        echo "FATAL: Agent 1 failed. Interrupting process.\n";
        exit(1);
    }
}

// Step 3: Agent 2 (Miniquest)
if ($mode === 'full' || $mode === '3') {
    echo "Step 3: Calling Agent 2...\n";

    $lastJournalEntry = end($state['journallist']);
    if ($lastJournalEntry === false) {
        $lastJournalEntry = '';
    }

    $payload = [
        'sysprompt' => $state['sysprompt'],
        'userprompt' => $state['userprompt'],
        'airesponse' => $state['airesponse'],
        'lastJournalEntry' => $lastJournalEntry,
        'questType' => 'miniquest',
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
        print_r($data);

    } else {
        echo "FATAL: Agent 2 failed. Interrupting process.\n";
        exit(1);
    }
}

echo "Process finished.\n";
