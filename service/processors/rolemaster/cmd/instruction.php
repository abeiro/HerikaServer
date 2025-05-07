<?php 
require_once(__DIR__ . '/../../../../lib/logger.php');

$GLOBALS["CURRENT_CONNECTOR"]=DMgetCurrentModel();


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

        
        // Function stuff        
        require($enginePath . "functions/functions_instruction.php");
        $fnames=[];

        foreach ($GLOBALS["F_NAMES"] as $functionCode=>$functionName) {
            if (in_array($functionCode,$GLOBALS["ENABLED_FUNCTIONS"])) {
                if ($functionCode!="OpenInventory" && $functionCode!="OpenInventory2") {
                    $function=findFunctionByName($functionName);
                    if ($function) {
                        $fnames[]=$GLOBALS["F_NAMES"]["$functionCode"]." ({$function["description"]})";
                        
                    } else 
                        $fnames[]=$GLOBALS["F_NAMES"]["$functionCode"];
                    $GLOBALS["FUNCTION_SHORT_LIST"][]=$GLOBALS["F_NAMES"]["$functionCode"];
                }
            }
        }

        
        if (!$GLOBALS["argv"][3]) {
            $sysprompt="You are a game director, you must create a instruction for any actor to generate new content/event.";
        } else {
            $sysprompt="You are a game director, the user has just made a wish: \"{$GLOBALS["argv"][3]}\". As game director, you now must issue an instruction to any actor to make this wish happen"; 
        }
        
        $prompt[] = array('role' => 'system', 'content' => "I want you to analyze this gameplay transcription from Skyrim in the Tamriel universe.");
        $prompt[] = array('role' => 'user', 'content' => $historyData);
        $prompt[] = array('role' => 'user', 'content' =>"
$sysprompt
({$GLOBALS["PLAYER_NAME"]},busy actors and far away actors are EXCLUDED!)
Just provide an instruction! (example:'Instruction for X: X should talk to Y about ...'). 
In addition, follow these general scene rules as a game director:
 * Fufill the players request no matter what, even if it means breaking roleplay.
 * If a character reuses the same argument too often, nudge the scene towards a new topic.
 * Occasionally introduce subtle foreshadowing or hint at future events, dangers, or quests.
 * Do not resolve everything neatly—keep room for ongoing tension or future continuation.
 * You must always provide dialogue instructions for the character, as every request requires a dialogue response.
 * Here are a list of actions that can be used: \n  ** ".implode("\n** ", $fnames)." 
 * Add a Scene Note: A brief description of the topic, mood, or idea introduced by the instruction. Should serve to guide the desired instruction to become reality.
");
        
        
        
        $customParm["response_format"]=["type"=>"json_object"];
        $customParm["MAX_TOKENS"]=4000;
        
        $GLOBALS["HOOKS"]["JSON_TEMPLATE"][]=function() {
            $GLOBALS["responseTemplate"] = [
                "character"=>"selected actor's full name",
                "instruction"=>"the instruction for the actor, what should be said or done. Use 3rd person here.",
                "action"=>implode("|",$GLOBALS["FUNCTION_SHORT_LIST"]),
                "target"=>"action's target",
                "scene_note"=>""
            ];
        };

        $connectionHandler = new $GLOBALS["CURRENT_CONNECTOR"];
        $connectionHandler->open($prompt,$customParm);

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
        
        function parseInstruction($response) {
            // Extract the character name and the instruction line
            
            $characterName = trim($response["character"] ?? 'Unknown');
            $instructionText = trim($response["instruction"] ?? 'No instruction text');
            $action = $response["action"]?"{$response["action"]} {$response["target"]}":"";
        
            // Generate unique task ID
            $taskId = uniqid();
        
            // Format action string
            $roleMasterAction = make_replacements("rolecommand|Instruction@{$characterName}@{$instructionText} (must use ACTION $action)@$taskId");
        
            // Insert into database
            $GLOBALS["db"]->insert(
                'responselog',
                array(
                    'localts' => time(),
                    'sent' => 0,
                    'actor' => "rolemaster",
                    'text' => '',
                    'action' => $roleMasterAction,
                    'tag' => ""
                )
            );
        }

        function parseSceneNote($response) {
            // Extract scene note after "Scene Note:"
            $characterName = trim($response["character"] ?? 'Unknown');
            $noteContent = trim($response["scene_note"] ?? 'No instruction text');
            
        
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
        
        

        
        $response=json_decode($rawbuffer,true);
        
        parseInstruction($response);
        parseSceneNote($response);
        
    }


    Logger::info("Successfully logged instruction command to responselog");
?>