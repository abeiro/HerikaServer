<?php 
require_once(__DIR__ . '/../../../../lib/logger.php');

$GLOBALS["CURRENT_CONNECTOR"]=DMgetCurrentModel();
$GLOBALS["CHIM_NO_EXAMPLES"]=true; // When no assistant entry in history, will try ti provide a bogus example.


if (!isset($GLOBALS["CURRENT_CONNECTOR"]) || (!file_exists($enginePath."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CURRENT_CONNECTOR"]}.php"))) {
        logMsg("Choose a LLM model and connector. Used '{$GLOBALS["CURRENT_CONNECTOR"]}'",S_LOG_CRITICAL);

    } else {
        logMsg("Using {$GLOBALS["CURRENT_CONNECTOR"]}");
        require($enginePath."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CURRENT_CONNECTOR"]}.php");

        $contextDataHistoric = DataLastDataExpandedFor("", -15);    // Full context
        
        $contextDataHistoric =array_merge([["role"=>"user","content"=>"# HISTORIC DIALOGUE AND EVENTS IN CHRONOLOGICAL ORDER"]], $contextDataHistoric);

        $contextDataWorld = DataLastInfoFor("", -2,$addNPCDescriptions=false,$excludeBusy=true);
        $contextDataFull = array_merge($contextDataWorld, $contextDataHistoric);
        $historyData="";

            
        foreach ($contextDataFull as $element) {
        
            $historyData.=trim("{$element["content"]}").PHP_EOL.PHP_EOL;
            
        }

        
       
    // Database Prompt (Smart Impersonation)
$commonprompt='';
        if (!$GLOBALS["argv"][3]) {
            $sysprompt="Write dialogue for {$GLOBALS["PLAYER_NAME"]}";
            $userprompt="";
        } else {
            $sysprompt="Rewrite dialogue for {$GLOBALS["PLAYER_NAME"]}, using this text as source \"{$GLOBALS["PLAYER_NAME"]}:{$GLOBALS["argv"][3]}\". Pay attention to comments between brackets, that can guide you in length and verbosity.";
            $userprompt="";
        }
        
        $prompt[] = array('role' => 'system', 'content' => "You are an actor/actress roleplaying as {$GLOBALS["PLAYER_NAME"]}, and we are roleplaying Skyrim in the Tamriel universe. ");
        $prompt[] = array('role' => 'user', 'content' => "# Contextual data\n$historyData");
        $prompt[] = array('role' => 'user', 'content' =>"
$sysprompt
");
        
        
        
        $customParm["response_format"]=["type"=>"json_object"];
        $customParm["MAX_TOKENS"]=4000;
        
        $GLOBALS["HOOKS"]["JSON_TEMPLATE"][]=function() {
            $GLOBALS["responseTemplate"] = [
                "character"=>"{$GLOBALS["PLAYER_NAME"]}",
                "dialogue"=>"Dialogue for character",
                "scene_note"=>"Something other actors should know about the instruction, if the instruction also involves another actors"
            ];
        };
        $GLOBALS["CONNECTOR"][$GLOBALS["CURRENT_CONNECTOR"]]["json_schema"]=false;

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
            $instructionText = trim($response["dialogue"] ?? 'No instruction text');
        
        
            // Generate unique task ID
            $taskId = uniqid();
        
            // Format action string
            
            $roleMasterAction = make_replacements("rolecommand|ImpersonatePlayer@{$instructionText}@inputtext");
            
        
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
        
        

        
        $response=__jpd_decode_lazy($rawbuffer);
        
        if (isset($response[0]) && is_array($response[0])) {
            $response=$response[0];
        }
        //print_r($response);
        parseInstruction($response);
        parseSceneNote($response);
        
    }
    

    Logger::info("Successfully logged instruction command to responselog");

    
   
?>