<?php

if (!chimIsGlobalLlmConnectorEnabled('CORE_CONNECTOR_MEDIUMTERM')) {
    Logger::debug('[MIDDLETERM] Generation skipped because Background & Memory Tasks are disabled globally');
    return;
}

if (isset($GLOBALS["ONCE_PER_RUN"]) && $GLOBALS["ONCE_PER_RUN"]==true) {
    return;

}
$startTime = microtime(true);

$selectedNpc=$GLOBALS["SELECTED_NPC"];

$npcMaster = new NpcMaster();
$connector = new LLMConnector();
$currentConnectorData = $connector->getById($GLOBALS["CORE_CONNECTOR_MEDIUMTERM"]);
$currentNpcData = $npcMaster->getByName($selectedNpc);

$connector->setOldGlobals($currentConnectorData);
$npcMaster->setOldGlobalsFromCurrentNpcData($currentNpcData);

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
$scopeConditionSql = dataGetMemoryScopeConditionSql($selectedNpc);

$query="SELECT summary as content,gamets_truncated FROM memory_summary where summary is not null and $scopeConditionSql and (companions like '%|$dbNpcName|%' or companions='$dbNpcName') and gamets_truncated>$gametsfrom order by gamets_truncated desc LIMIT 100";

$contextDataFull=$GLOBALS["db"]->fetchAll($query);

error_log("[MIDDLETERM] Retrieved " . count($contextDataFull) . " memory entries for $selectedNpc since gamets $gametsfrom");
// $task=DataGetCurrentTask();
$limit=10;

if ($gametsfrom==0) // Lower limit for first one
    $limit=5;

if (sizeof($contextDataFull)==0 ||sizeof($contextDataFull)<$limit ) {
    // Logger::info("\tNo memories to summarize for $selectedNpc");
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

// Load prompt from database with fallback to hardcoded default
// Query prompts table: use custom_prompt if set, otherwise default_prompt, otherwise hardcoded fallback
$promptContent = null;
try {
    $promptData = $GLOBALS["db"]->fetchOne("SELECT custom_prompt, default_prompt FROM prompts WHERE prompt_key = 'middleterm_narrative_summarizer'");
    if ($promptData) {
        // Use custom_prompt if set, otherwise use default_prompt
        $promptContent = (!empty($promptData['custom_prompt'])) ? $promptData['custom_prompt'] : $promptData['default_prompt'];
    }
} catch (Exception $e) {
    Logger::warn("Failed to load prompt from database, using hardcoded fallback: " . $e->getMessage());
}

// Hardcoded fallback if database query failed or returned no results
if (!$promptContent) {
    $promptContent = 
        "You are a long-term narrative continuity summarizer for an improvised Skyrim universe chronicle.\n".
        "- Always read ALL provided materials.\n".
        "- Treat any **Previous Context History Summary** as the canonical prior unless anything in the new Context History explicitly supersedes it.\n".
        "- Maintain in-universe tone and correct chronology. Do not invent facts outside the supplied context.\n".
        "- When combining prior and new histories, you may compress the earlier parts of the prior summary.\n".
        "- Maintain roughly 20–25 bullet points total in **Notable Events**. Older portions should be condensed into broader, grouped statements unless they describe major quest milestones, major character life events (e.g., death, intimacy, severe injury, transformation), or other pivotal story turns.\n".
        "- Preserve continuity and references to major quests even when compressing earlier material.";
}

$head[] = [
    'role' => 'system',
    'content' => $promptContent
];

// Load request prompt from database with fallback to hardcoded default
$requestPrompt = null;
try {
    $promptData = $GLOBALS["db"]->fetchOne("SELECT custom_prompt, default_prompt FROM prompts WHERE prompt_key = 'middleterm_narrative_request'");
    if ($promptData) {
        // Use custom_prompt if set, otherwise use default_prompt
        $requestPrompt = (!empty($promptData['custom_prompt'])) ? $promptData['custom_prompt'] : $promptData['default_prompt'];
    }
} catch (Exception $e) {
    Logger::warn("Failed to load request prompt from database, using hardcoded fallback: " . $e->getMessage());
}

// Hardcoded fallback if database query failed or returned no results
if (!$requestPrompt) {
    $requestPrompt = 
        "Main character in this logbook is {HERIKA_NAME}.\n".
        "Task: Read **Context History** (newest session) and, if present, the **Previous Context History Summary** (prior canon). ".
        "Integrate them to produce an updated broad narrative strokes summary that preserves continuity. Summary sections:\n\n".
        "- **Notable Events in Chronological Order:**\n".
        "  - Provide ~10 bullet points from earliest to latest, reflecting the story so far.\n".
        "  - Prefer facts already established in the previous summary; only revise if the new context clearly changes them.\n\n".
        "- **Current Quest Progression and background:**\n".
        "  - Name questlines, stages/milestones if stated, objectives completed/active, and motivations.\n".
        "When generating entries, ensure that {HERIKA_NAME} — the protagonist — is actively present in the scene. ".
        "Any narrative content that occurs before {HERIKA_NAME}'s arrival or outside {HERIKA_NAME}'s perspective should be omitted, ".
        "reflect only events {HERIKA_NAME} directly witness or participate in.\n".
        "If the resulting summary would exceed roughly 25 bullet points, merge or generalise older entries into broader grouped events. ".
        "Always retain explicit entries for major quest milestones, major character life events, or turning points.";
}

// Replace {HERIKA_NAME} placeholder with actual character name
$request = str_replace('{HERIKA_NAME}', $GLOBALS['HERIKA_NAME'], $requestPrompt);

if (!empty($previous))
    $prompt[] = ['role' => 'user', 'content' => "# Previous Context History Summary:\n$previous"];

$prompt[] = ['role' => 'user', 'content' => "# Context History\n$history\n$task"];
$prompt[] = ['role' => 'user', 'content' => $request];
$prompt[] = ['role' => 'user', 'content' => "Begin your answer with `### Notable Events in Chronological Order` and complete sections as instructed."];

$contextData = array_merge($head, $prompt);

Logger::debug(__LINE__ . " " . (microtime(true) - $startTime));

$connectionHandler =$connector->getConnector($currentConnectorData);
$buffer=$connectionHandler->fast_request($contextData,["MAX_TOKENS"=>2048],"middleterm");

Logger::debug(__LINE__ . " " . (microtime(true) - $startTime));

print_r($buffer).PHP_EOL;

if ($buffer) {
    $extended_data=$npcMaster->getExtendedData($currentNpcData);
    $extended_data["middle_term_memory"][$lastgamets]=$buffer;
    $currentNpcData=$npcMaster->setExtendedData($currentNpcData,$extended_data);
    $npcMaster->updateByArray($currentNpcData);
    $GLOBALS["ONCE_PER_RUN"]=true;
} else 
    Logger::error(__LINE__ . " Buffer was empty. not writing middleterm " . (microtime(true) - $startTime));

?>
