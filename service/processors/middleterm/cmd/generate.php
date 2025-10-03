<?php 

$startTime = microtime(true);

$selectedNpc=$GLOBALS["SELECTED_NPC"];

$npcMaster = new NpcMaster();
$connector = new LLMConnector();
$currentConnectorData = $connector->getById($GLOBALS["CORE_CONNECTOR_MEDIUMTERM"]);
$currentNpcData = $npcMaster->getByName($selectedNpc);

$connector->setOldGlobals($currentConnectorData);
$npcMaster->setOldGlobalsFromCurrentNpcData($currentNpcData);

$CLEAN_CONTEXT_FOCUS_CHAT = false;

$COMMAND_PROMPT = '';

$extended_data=$npcMaster->getExtendedData($currentNpcData);

if (isset($extended_data["middle_term_memory"])&&sizeof($extended_data["middle_term_memory"])>0) {
    $gametsfrom = array_key_last($extended_data["middle_term_memory"]);
    $previous = end($extended_data["middle_term_memory"]);
} else {
    $gametsfrom=0;
    $previous="";
}


$dbNpcName=$GLOBALS["db"]->escape($selectedNpc);

$contextDataFull=$GLOBALS["db"]->fetchAll("SELECT summary as content,gamets_truncated FROM memory_summary where summary is not null and companions like '%$dbNpcName%' and gamets_truncated>$gametsfrom order by gamets_truncated desc LIMIT 100");
// $task=DataGetCurrentTask();

if (sizeof($contextDataFull)==0 ||sizeof($contextDataFull)<10 ) {
    error_log("No memories to summarize\n");
    return;
}

$task    = "";
$history = "";
foreach (array_reverse($contextDataFull) as $entry) {
    if ($entry["content"]) {
        $history .= "===\nMemory entry, date " . convert_gamets2skyrim_date($entry["gamets_truncated"]).PHP_EOL;
        $history .= trim($entry["content"]) . PHP_EOL.PHP_EOL;
        $lastgamets=$entry["gamets_truncated"];
    }
}

$head[] = ['role' => 'system', 'content' => "You're an AI assistant. Examine this memory logbook from a story in the Skyrim universe."];

$request = "
Read the context history, paying attention to character names,  and fill the following info.

**Context History Summary:**

- **Notable Events:**
  -
  -
  -

**Main Topics/Events Covered:**

- **Current Quest Progression and background:**
- **Notable Combat Encounters:**
- **Notable Loot Acquisition:**
- **Character Dynamics:**
- **Current Topic** ";

$request = "Main character in this logbook is {$GLOBALS["HERIKA_NAME"]}.
Read the context history, paying attention to character names and notable events, and fill the following info.

- **Notable Events in chronological order:**
  - (This must be a bulleted list of about 10 elements). Keep elements showing progression from the very beginning to the end. You will have to choose important ones.

- **Current Quest Progression and background:**

";

if (!empty($previous))
    $prompt[] = ['role' => 'user', 'content' => "# Previous Context History Summary:\n$previous"];    

$prompt[] = ['role' => 'user', 'content' => "# Context History\n$history\n$task"];
$prompt[] = ['role' => 'user', 'content' => $request];
$prompt[] = ['role' => 'assistant', 'content' => "### Notable Events in Chronological Order"];

$contextData = array_merge($head, $prompt);

Logger::debug(__LINE__ . " " . (microtime(true) - $startTime));

$connectionHandler =$connector->getConnector($currentConnectorData);
$buffer=$connectionHandler->fast_request($contextData,["MAX_TOKENS"=>2048]);

Logger::debug(__LINE__ . " " . (microtime(true) - $startTime));

print_r($buffer);


$extended_data=$npcMaster->getExtendedData($currentNpcData);
$extended_data["middle_term_memory"][$lastgamets]=$buffer;
$currentNpcData=$npcMaster->setExtendedData($currentNpcData,$extended_data);
$npcMaster->updateByArray($currentNpcData);

?>