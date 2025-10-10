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

/*$head[] = ['role' => 'system', 'content' => "You're an AI assistant. Examine this memory logbook from a story in the Skyrim universe."];

$request = "
Read the context history, paying attention to character names, and fill the following info.

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

";*/

$head[] = [
    'role' => 'system',
    'content' =>
        "You are a long-term narrative continuity summarizer for an improvised Skyrim universe chronicle.\n".
        "- Always read ALL provided materials.\n".
        "- Treat any **Previous Context History Summary** as the canonical prior unless anything in the new Context History explicitly supersedes it.\n".
        "- Maintain in-universe tone and correct chronology. Do not invent facts outside the supplied context."
];

$request =
    "Main character in this logbook is {$GLOBALS['HERIKA_NAME']}.\n".
    "Task: Read **Context History** (newest session) and, if present, the **Previous Context History Summary** (prior canon). ".
    "Integrate them to produce an updated broad narrative strokes summary that preserves continuity. Summary sections:\n\n".

    "- **Notable Events in Chronological Order:**\n".
    "  - Provide ~10 bullet points from earliest to latest, reflecting the whole story so far.\n".
    "  - Prefer facts already established in the previous summary; only revise if the new context clearly changes them.\n\n".

    "- **Current Quest Progression and background:**\n".
    "  - Name questlines, stages/milestones if stated, objectives completed/active, and motivations.\n". 
    "When generating entries, ensure that {$GLOBALS['HERIKA_NAME']} — the protagonist — is actively present in the scene. ". 
    "Any narrative content that occurs before {$GLOBALS['HERIKA_NAME']}'s arrival or outside {$GLOBALS['HERIKA_NAME']}'s perspective should be omitted, ".
    "reflect only events {$GLOBALS['HERIKA_NAME']} directly witness or participate in\n";

if (!empty($previous))
    $prompt[] = ['role' => 'user', 'content' => "# Previous Context History Summary:\n$previous"];

$prompt[] = ['role' => 'user', 'content' => "# Context History\n$history\n$task"];
$prompt[] = ['role' => 'user', 'content' => $request];
$prompt[] = ['role' => 'user', 'content' => "Begin your answer with `### Notable Events in Chronological Order` and complete sections as instructed."];

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