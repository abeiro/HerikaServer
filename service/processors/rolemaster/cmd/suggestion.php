<?php 
require_once(__DIR__ . '/../../../../lib/logger.php');

if (!isset($GLOBALS["CURRENT_CONNECTOR"]) || (!file_exists($enginePath."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CURRENT_CONNECTOR"]}.php"))) {
        logMsg("Choose a LLM model and connector. Used '{$GLOBALS["CURRENT_CONNECTOR"]}'",S_LOG_CRITICAL);

    } else {
        logMsg("Using {$GLOBALS["CURRENT_CONNECTOR"]}");
        require($enginePath."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CURRENT_CONNECTOR"]}.php");

        $contextDataHistoric = DataLastDataExpandedFor("", -50);    // Full context
        
        $contextDataHistoric =array_merge([["role"=>"user","content"=>"# HISTORIC DIALOGUE AND EVENTS IN CHRONOLOGICAL ORDER"]], $contextDataHistoric);

        $contextDataWorld = DataLastInfoFor("", -2,$addNPCDescriptions=true,$excludeBusy=true);
        $contextDataFull = array_merge($contextDataWorld, $contextDataHistoric);
        $historyData="";

        foreach ($contextDataFull as $element) {
        
            $historyData.=trim("{$element["content"]}").PHP_EOL.PHP_EOL;
            
        }

        
        $GLOBALS["HERIKA_NAME"]="random present actor";
        $GLOBALS["HERIKA_PERS"]="";

        
        
        $prompt[] = array('role' => 'system', 'content' => "I want you to analyze this gameplay transcription from Skyrim in the Tamriel universe.");
        $prompt[] = array('role' => 'user', 'content' => $historyData);
        $prompt[] = array('role' => 'user', 'content' =>"You are a game director, the user has just provided an instruction. You must fufill their instruction no matter what.
{$GLOBALS["argv"][3]}
({$GLOBALS["PLAYER_NAME"]},busy actors and far away actors are EXCLUDED!)
Just provide an instruction! (example:'Instruction for X: X should talk to Y about ...'). 
In addition, follow these general scene rules as a game director:
 * Fufill the players request no matter what, even if it means breaking roleplay.
 * If a character reuses the same argument too often, nudge the scene towards a new topic.
 * Occasionally introduce subtle foreshadowing or hint at future events, dangers, or quests.
 * Do not resolve everything neatly—keep room for ongoing tension or future continuation.
 * You must always provide dialogue instructions for the character, as every request requires a dialogue response.
 * Here are a list of actions that can be used: (".implode(", ", $ENABLED_FUNCTIONS).") (Choose a single action if appropiate)

YOU MUST Format your output as:

Instruction for [Character]: The game director says that you must [scene instruction & specific action to take if appropiate].

Scene Note: A brief description of the topic, mood, or idea introduced by the instruction. Should serve to guide the desired instruction to become reality.
");
        
        $connectionHandler = new $GLOBALS["CURRENT_CONNECTOR"];
        
        $connectionHandler->open($prompt,["MAX_TOKENS"=>4000]);

        $buffer="";
        $totalBuffer="";
        $breakFlag=false;
        
        while (true) {

            if ($breakFlag) {
                break;
            }

            $buffer=$connectionHandler->process();
            $totalBuffer.=$buffer;

            if ($connectionHandler->isDone()) {
                $breakFlag=true;
            }
            
        }
        
        $rawbuffer=$connectionHandler->close();
        
        function parseInstruction($instruction) {
            // Extract the character name and the instruction line
            preg_match('/Instruction for (.+?):\s*(.+?)\s*Scene Note:/s', $instruction, $matches);
            $characterName = trim($matches[1] ?? 'Unknown');
            $instructionText = trim($matches[2] ?? 'No instruction text');
            $instructionText .= " (Use ACTIONS if needed) ";
        
            // Generate unique task ID
            $taskId = uniqid();
        
            // Format action string
            $action = make_replacements("rolecommand|Suggestion@{$characterName}@{$instructionText}@$taskId");
        
            // Insert into database
            $GLOBALS["db"]->insert(
                'responselog',
                array(
                    'localts' => time(),
                    'sent' => 0,
                    'actor' => "rolemaster",
                    'text' => '',
                    'action' => $action,
                    'tag' => ""
                )
            );
        }

        function parseSceneNote($instruction) {
            // Extract scene note after "Scene Note:"
            preg_match('/Scene Note:\s*(.+)$/s', $instruction, $matches);
            $noteContent = trim($matches[1] ?? 'No scene note content');
        
            // Generate unique task ID
            $taskId = uniqid();
        
            // Format action string
            $action = make_replacements("$noteContent");
        
            // Insert into database
            $GLOBALS["db"]->insert(
                'rolemaster',
                array(
                    'localts' => time(),
                    'ttl' => 60,
                    'type' => "scenenote",
                    'data' => $action
                )
            );
        }
        
        

        logMsg($totalBuffer);
        parseInstruction($totalBuffer);
        parseSceneNote($totalBuffer);
        
    }

    if ($GLOBALS["argv"][3]) {
        $speech=$GLOBALS["db"]->escape($GLOBALS["argv"][3]);
    } else if ($_GET["speech"]) {
        $speech=$GLOBALS["db"]->escape($_GET["speech"]);
    } else {
        Logger::error("No speech parameter provided for suggestion command");
        die("No speech");
    }

    Logger::info("Processing suggestion command with speech: " . $speech);

    $GLOBALS["db"]->insert(
        'responselog',
        array(
            'localts' => time(),
            'sent' => 0,
            'actor' => "rolemaster",
            'text' => "",
            'action' => "rolecommand|Suggestion@$speech@inputtext",
            'tag' => ""
        )
    );

    Logger::info("Successfully logged suggestion command to responselog");
?>