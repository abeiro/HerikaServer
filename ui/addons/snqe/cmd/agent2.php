<?php

$enginePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php";
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

if (!chimIsGlobalLlmConnectorEnabled('CORE_CONNECTOR_MEDIUMTERM')) {
    http_response_code(409);
    echo json_encode(['error' => 'Background & Memory Tasks are turned off in Global Settings.']);
    exit;
}

require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php";

$GLOBALS["ENGINE_PATH"] = $enginePath;

$db = $GLOBALS["db"];

require_once $enginePath . "lib/core/npc_master.class.php";
require_once $enginePath . "lib/core/api_badge.class.php";
require_once $enginePath . "lib/core/core_profiles.class.php";
require_once $enginePath . "lib/core/llm_connector.class.php";
require_once $enginePath . "service/processors/snqe/lib/snqe.class.php";

$connector = new LLMConnector();
$currentConnectorData = $connector->getById($GLOBALS["CORE_CONNECTOR_MEDIUMTERM"]);
$connector->setOldGlobals($currentConnectorData);

$method = $_SERVER['REQUEST_METHOD'];

$formInput = json_decode(file_get_contents("php://input"), true);

$MODEL = "google/gemini-3-flash-preview";
$questType = $formInput["questType"] ?? "miniquest";

$spawnedItemArray = $formInput["spawneditemslist"];

foreach ($spawnedItemArray as $n => $itemName) {
    $cn = $GLOBALS["db"]->escape($itemName);
    $rows = $GLOBALS["db"]->fetchAll("select count(*) as n from eventlog where type='itemfound' and data like '%$cn%'");
    if ($rows && $rows[0]["n"]>0) {
        $spawnedItemArray[$n] = "$itemName (already recovered)";
    } else {
        continue;
        
    }
}

$spawnedItemList = arrayToBulletedList($spawnedItemArray);

$npcList = [];
foreach ($formInput["npclist"] as $npc) {
    
    $isDead = DataActorHasDied($npc);
    if ($isDead) {
        $npcList[] = " * $npc (dead)";
    } else {
        if (strpos(DataBeingsOrDeathsInRangeExcluding(), $npc) == false) {
            $outofscene = "(out of scene)";
            continue;
        } else {
            $outofscene = "";
        }
    }
}

$npcListFinal = implode("\n", $npcList);

$prompt[] = ['role' => 'system', 'content' => file_get_contents("{$enginePath}/service/processors/snqe/lib/api_doc.php")];
$prompt[] = [
    'role' => 'user',
    'content' => "
Using api, create a miniquest to accomplish this xml instructions, AND ONLY this instructions:
{$formInput["airesponse"]}

Notes, Already spawned items in previous sessions.
$spawnedItemList
Already spawned NPCs in previous sessions:
$npcListFinal

Player Name: {$GLOBALS["PLAYER_NAME"]}

After SPAWN, you must decided wether to move the NPC or make it stay at it's location.

Important notes about XML elements.
<spawn></spawn> this element at root level involves BOTH creating and spawning the NPC.  <spawn> = CreateNPC and need to SpawnNPC 
<instruction></instruction> element at root level denotes NPC is already spawned, just create it for reference, but DO NOT SPAWN IT. <instruction> = only CreateNPC for reference, NPC is already spawned from previous session, or previously in the code
<item></item>this element at root level involves BOTH creating and spawning the item.  <item> = CreateItem and SpawnItem.

* Notes about order of operations:
1. Create functions (CreateNPC, CreateItem, CreateTopic) must be at the top of the code.
2. Besides create functions, (Spawn*, Wait*,TellTopic,*..) functions should appear following original instructions order. **very important**.
3. Respect instructions order.


Write very short comments on code. Code should finish with CompleteQuest call.
"
];


$contextData = $prompt;

$connectionHandler = $connector->getConnector($currentConnectorData);
$buffer = $connectionHandler->fast_request(
    $contextData,
    ["MAX_TOKENS" => 4096, "model" => $MODEL, "temperature" => 0.3],
    "questcoder"
);

header('Content-Type: application/json');

// Extract PHP code from markdown code block
$phpCode = $buffer;
if (preg_match('/```php\n(.*?)\n```/s', $buffer, $matches)) {
    $phpCode = $matches[1];
}
$first_code = $phpCode;
/*
// simple line-based diff: '  ' = same, '- ' = removed (first_code), '+ ' = added (second_code)
function line_diff($a, $b) {
    $A = explode("\n", str_replace(["\r\n","\r"], "\n", $a));
    $B = explode("\n", str_replace(["\r\n","\r"], "\n", $b));
    $i = $j = 0;
    $la = count($A); $lb = count($B);
    $out = '';
    while ($i < $la || $j < $lb) {
        $ai = $A[$i] ?? null;
        $bj = $B[$j] ?? null;
        if ($ai === $bj) {
            $out .= "  " . ($ai ?? '') . "\n"; $i++; $j++; continue;
        }
        if ($i + 1 < $la && $A[$i + 1] === $bj) {
            $out .= "- " . ($ai ?? '') . "\n"; $i++; continue;
        }
        if ($j + 1 < $lb && $ai === $B[$j + 1]) {
            $out .= "+ " . ($bj ?? '') . "\n"; $j++; continue;
        }
        if ($ai !== null) { $out .= "- " . $ai . "\n"; $i++; }
        if ($bj !== null) { $out .= "+ " . $bj . "\n"; $j++; }
    }
    return $out;
}

// Step 2: Validate and fix code

$contextData[]=['role' => 'assistant', 'content' => $phpCode];
$contextData[]=['role' => 'user', 'content' => "Please confirm the quest code is correct and complete. 
* Check waiting functions (Wait*) return values are checked. 
If there are any errors, fix them. Return the full corrected PHP code inside a single markdown code block with php syntax."];

$buffer            = $connectionHandler->fast_request($contextData,
    ["MAX_TOKENS" => 4096, "model" => $MODEL,"temperature"=>0.3],
    "questcoder_fixer");

// Extract PHP code from markdown code block
$phpCode = $buffer;
if (preg_match('/```php\n(.*?)\n```/s', $buffer, $matches)) {
    $phpCode = $matches[1];
}
$second_code=$phpCode;

if (isset($first_code) && isset($second_code)) {
    $corrected_errors=line_diff($first_code, $second_code);
}

*/
// The AI-generated code ($phpCode) contains a line like: $quest_id = "merchant_request";
// Extract the original quest_id from the PHP code
$questName = "";
if (preg_match('/\$quest_id\s*=\s*["\']([^"\']+)["\']/', $phpCode, $matches)) {
    $questName = $matches[1];
}

// Generate a unique quest_id by appending _ and uniqid to the original quest_id
$questId = $questName . "_" . uniqid();

// Create the new quest using SNQEQuestManager
$result = [
    'response' => $phpCode,
    'questId' => $questId,
    'success' => false,
    'message' => '',
    'lastJournalEntry' => $formInput["lastJournalEntry"]
];
//  Include corrected errors in the result
// $result["errors"] = $corrected_errors;

try {
    // The AI-generated code contains a line like: $quest_id = "merchant_request";
    // We need to replace it with the dynamically calculated questId before inserting into database
    $phpCode = preg_replace('/\$quest_id\s*=\s*["\'].*?["\'];/', "\$quest_id = \"{$questId}\";", $phpCode);
    $questdata["briefing"] = $formInput["briefing"] ?? "";  
    SNQEQuestManager::createNewQuest($questId, $phpCode, $questdata, "not_running", $formInput["questTitle"], $formInput["lastJournalEntry"]);
    // Store a copy of the generated PHP code in the snqe_editor/quests directory for reference/customizing.
    @file_put_contents("{$enginePath}/snqe_editor/quests/{$questId}.php", $phpCode);

    $result['success'] = true;
    $result['message'] = "Quest created successfully with ID: " . $questId;
} catch (\Exception $e) {
    $result['message'] = "Error creating quest: " . $e->getMessage();
}

// Return response with extracted PHP code and quest creation status
echo json_encode($result);

?>
