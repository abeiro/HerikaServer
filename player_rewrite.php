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

/* 'CurrentModel_.json' does not always contain the connector for the 'default' profile, 
because 'active_profile' is calculated in various places as md5(npcName) without testing the case where 'npcName' is 'The Narrator'. 
The convention that the connector is in the file 'CurrentModel_72dc4b1c501563d149fec99eb45b45f1.json' 
corresponding to 'active_profile' = md5('The narrator') is easier to implement and is mainly managed in 'model_dynmodel.php'.
*/
$file = $GLOBALS["ENGINE_ROOT"].'/data/CurrentModel_72dc4b1c501563d149fec99eb45b45f1.json';
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

        
       

        // Build context for player character
        $playerContext = "";
        
        // Ensure PLAYER_SPEECH_STYLE is available (it's a global config variable)
        if (!isset($GLOBALS["PLAYER_SPEECH_STYLE"])) {
            $GLOBALS["PLAYER_SPEECH_STYLE"] = "";
        }
        
        if (!empty($GLOBALS["PLAYER_BIOS"])) {
            $bio = strtr($GLOBALS["PLAYER_BIOS"],["#PLAYER_NAME#"=>$GLOBALS["PLAYER_NAME"]]);
            $playerContext .= "Player Character Background: " . $bio . "\n";
        }
        if (!empty($GLOBALS["PLAYER_SPEECH_STYLE"])) {
            $playerContext .= "Player Speech Style: " . $GLOBALS["PLAYER_SPEECH_STYLE"] . "\n";
        }
        
        $commonprompt='';
        if (!$_GET["speech"]) {
            $sysprompt="Write dialogue for {$GLOBALS["PLAYER_NAME"]}";
            if (!empty($playerContext)) {
                $sysprompt .= "\n\n# Character Context\n" . $playerContext;
            }
            $userprompt="";
        } else {
            $sysprompt="Rewrite dialogue for {$GLOBALS["PLAYER_NAME"]}, using this text as source \"{$GLOBALS["PLAYER_NAME"]}:{$_GET["speech"]}\". Pay attention to comments between brackets, that can guide you in length and verbosity.";
            if (!empty($playerContext)) {
                $sysprompt .= "\n\n# Character Context\n" . $playerContext;
            }
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

        // Log the player rewrite request to context_sent_to_llm.log (minimal logging)
        file_put_contents(__DIR__."/log/context_sent_to_llm.log", date(DATE_ATOM)."\n=PLAYER_REWRITE for {$GLOBALS["PLAYER_NAME"]}=\n".var_export($prompt,true)."\n=\n", FILE_APPEND);

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
		// Handle models that wrap JSON in Markdown code fences (e.g., DeepSeek)
		$startsFence = function_exists('str_starts_with') ? str_starts_with($rawbuffer, "```") : (substr($rawbuffer, 0, 3) === "```");
		$endsFence = function_exists('str_ends_with') ? str_ends_with($rawbuffer, "```") : (substr($rawbuffer, -3) === "```");
		if ($startsFence && !$endsFence) {
			$rawbuffer .= "```";
		}

		// Strip optional ```json fenced blocks to keep only the JSON payload
		$rawbuffer = preg_replace('/^```(?:json)?\s*(.*)\s*```$/s', '$1', $rawbuffer);
        
        function parseInstruction($response) {
            // Extract the character name and the instruction line
            
            $characterName = trim($response["character"] ?? 'Unknown');
            $instructionText = trim($response["dialogue"] ?? 'No instruction text');
			
			echo $instructionText . PHP_EOL;
			if (function_exists('ob_get_level') && ob_get_level() > 0) {
				@ob_flush();
			}
			flush();
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