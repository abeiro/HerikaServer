<?php 
$GLOBALS["ENGINE_ROOT"] = __DIR__.DIRECTORY_SEPARATOR;
$enginePath = $GLOBALS["ENGINE_ROOT"];


require_once("{$GLOBALS["ENGINE_ROOT"]}/conf/conf.php");
require_once("{$GLOBALS["ENGINE_ROOT"]}/lib/logger.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "prompts" .DIRECTORY_SEPARATOR."command_prompt.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($enginePath . "lib/rolemaster_helpers.php");

$file = $GLOBALS["ENGINE_ROOT"].'/data/CurrentModel_.json';
$modelContents = file_get_contents($file);
Logger::info("Current AI Model is set to $modelContents.");


$GLOBALS["db"]=new sql();

$GLOBALS["HERIKA_NAME"]="(actor)";

// Initialize function parameters before requiring functions.php
$GLOBALS["FUNCTION_PARM_INSPECT"] = [];
$GLOBALS["FUNCTION_PARM_MOVETO"] = [];
$GLOBALS["F_NAMES"] = [];


require($enginePath . "functions/functions.php");

// Make functions.php data global

$GLOBALS["FUNCTIONS_ARE_ENABLED"]=false;

$GLOBALS["CURRENT_CONNECTOR"]=$GLOBALS["CONNECTORS_DIARY"];

// Some functions need this setted */
$res=$GLOBALS["db"]->fetchAll("select max(gamets)+1 as gamets,max(ts)+1 as ts  from eventlog order by gamets desc limit 1 offset 0");
$GLOBALS["gameRequest"]=["inputtext"];
$GLOBALS["gameRequest"][2]=$res[0]["gamets"]+1;


$GLOBALS["CURRENT_CONNECTOR"]=DMgetCurrentModel();
$GLOBALS["CHIM_NO_EXAMPLES"]=true; // When no assistant entry in history, will try ti provide a bogus example.


if (!isset($GLOBALS["CURRENT_CONNECTOR"]) || (!file_exists($enginePath."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CURRENT_CONNECTOR"]}.php"))) {
        error_log("Choose a LLM model and connector. Used '{$GLOBALS["CURRENT_CONNECTOR"]}'");

    } else {
        error_log("Using {$GLOBALS["CURRENT_CONNECTOR"]}");
        require($enginePath."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CURRENT_CONNECTOR"]}.php");

        $contextDataHistoric = DataLastDataExpandedFor("", -15);    // Full context
        
        $contextDataHistoric =array_merge([["role"=>"user","content"=>"# HISTORIC DIALOGUE AND EVENTS IN CHRONOLOGICAL ORDER"]], $contextDataHistoric);

        $contextDataWorld = DataLastInfoFor("", -2,$addNPCDescriptions=false,$excludeBusy=true);
        $contextDataFull = array_merge($contextDataWorld, $contextDataHistoric);
        $historyData="";

            
        foreach ($contextDataFull as $element) {
        
            $historyData.=trim("{$element["content"]}").PHP_EOL.PHP_EOL;
            
        }

        
       

        $commonprompt='';
        if (!$_GET["speech"]) {
            $sysprompt="Write dialogue for {$GLOBALS["PLAYER_NAME"]}";
            $userprompt="";
        } else {
            $sysprompt="Rewrite dialogue for {$GLOBALS["PLAYER_NAME"]}, using this text as source \"{$GLOBALS["PLAYER_NAME"]}:{$_GET["speech"]}\". Pay attention to comments between brackets, that can guide you in length and verbosity.";
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
        
            echo  $instructionText.PHP_EOL;
            while(@ob_end_flush());
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