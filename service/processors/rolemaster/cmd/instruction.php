<?php 
require_once(__DIR__ . '/../../../../lib/logger.php');


$GLOBALS["active_profile"]=md5("The Narrator");
$GLOBALS["CURRENT_CONNECTOR"]=DMgetCurrentModel();
$GLOBALS["CHIM_NO_EXAMPLES"]=true; // When no assistant entry in history, will try ti provide a bogus example.



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

        $GLOBALS["ENABLED_FUNCTIONS"][]="ReturnBackHome";
        $GLOBALS["FUNCTIONS"][]=$GLOBALS["BASE_FUNCTIONS"]["ReturnBackHome"];

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

$commonprompt='
# Examples

user request: actor \"a\" leaves the place 
{"instructions":[{
  "character": "actor a",
  "instruction": "actor a should say goodbye to everyone, hinting that they may not return for a long time",
  "action": "ExitLocation",
  "target": "everyone",
  "scene_note": "The mood is somber as actor a prepares to leave. Actor b watches in silence, perhaps with regret or longing."
},
{
  "character": "actor b",
  "instruction": "actor b should say goodbye to b",
  "action": "JustTalk",
  "target": "Actor a",
  "scene_note": ""
}
]
}

(no user request, randomly generated content)
{"instructions":[
 {
  "character": "actor a",
  "instruction": "actor a should ask actor b for a few coins, claiming they desperately need a drink.",
  "action": "Talk",
  "target": "actor b",
  "scene_note": "actor a looks disheveled but charming, half-joking and half-serious. Actor b is unsure whether to laugh, help, or walk away."
 }
]
}

';
        if (!$GLOBALS["argv"][3]) {
            $userprompt="";
            $sysprompt="";
        } else {
            $sysprompt="user request: \"{$GLOBALS["argv"][3]}\". (As game director, you now must issue an instruction to any actor to make this wish happen. If user is addressing an actor, use instruction on it)"; 
            $userprompt="* Fufill the user's request no matter what, even if it means breaking roleplay.";
        }
        
        $prompt[] = array('role' => 'system', 'content' => "You are a game director, and we are roleplaying Skyrim in the Tamriel universe. You must create a instruction for an actor to generate new content/events on game.$commonprompt");
        $prompt[] = array('role' => 'user', 'content' => "# Contextual data\n$historyData");
        // Database Prompt (Director)
        $prompt[] = array('role' => 'user', 'content' =>"
$sysprompt
Just provide instructions! You can also provide more than one instruction, but one per actor (keep limit at  2 or 3 max actors)
In addition, follow these general scene rules as a game director:
 $userprompt
 * Use any actor in NEARBY ACTORS/NPC IN THE SCENE list ({$GLOBALS["PLAYER_NAME"]},busy actors and far away actors are EXCLUDED!)
 * Continue the scene as naturally and fully as possible, unless the user explicitly requests a new one. You can specify actions to reinforce the actors' dialogue.
 * If there are more actors in the room, try to involve them in the conversation.
 * When dialogue becomes repetitive, make a plot twist.
 * If a character reuses the same argument too often, nudge the scene towards a new topic.
 * Occasionally introduce subtle foreshadowing or hint at future events, dangers, or quests.
 * Do not resolve everything neatly—keep room for ongoing tension or future continuation.
 * You must always provide dialogue instructions for the character, as every request requires a dialogue response.
 * Here are a list of actions that can be used: \n  ** ".implode("\n  ** ", $fnames)."\n  ** JustTalk 
 * Add a Scene Note: A brief description of the topic, mood, or idea introduced by the instruction. Should serve to guide the desired instruction to become reality.
 * If scene is getting boring, add a plot twist
");
        
        
        
        $customParm["response_format"]=["type"=>"json_object"];
        $customParm["MAX_TOKENS"]=4000;
        
        $GLOBALS["HOOKS"]["JSON_TEMPLATE"][]=function() {
            $GLOBALS["responseTemplate"] = ["instructions"=>[[
                "character"=>"selected actor's full name",
                "instruction"=>"the instruction for the actor, what should be said or done. Use 3rd person here.",
                "action"=>implode("|",$GLOBALS["FUNCTION_SHORT_LIST"]),
                "target"=>"action's target",
                "scene_note"=>"Something other actors should know about the instruction, if the instruction also involves another actors"
            ]]];
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
        
            if (!$characterName || !$instructionText) {
                return false;
            }

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

            return true;
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
                    'ttl' => 300,
                    'type' => "scenenote",
                    'data' => $action
                )
            );
        }
        
        

        
        
        $rawbuffer.=PHP_EOL;
        unset($GLOBALS["_JSON_BUFFER"]);
        $response=__jpd_decode_lazy($rawbuffer);
        
        
        if (isset($response[0]["instructions"]))
            $response=$response[0];

        if (isset($response["instructions"]) && is_array($response["instructions"])) {
            $allOk=true;
            foreach ($response["instructions"] as $r) {
                $allOk=$allOk && parseInstruction($r);
                parseSceneNote($r);
            }
        } else 
            $allOk=false;

        
        if (isset($GLOBALS["argv"][4]) && $GLOBALS["argv"][4]=="notify") {
            $pluginVersionRow = $GLOBALS['db']->fetchOne("SELECT value FROM conf_opts WHERE id='plugin_dll_version'");
            if ($pluginVersionRow && isset($pluginVersionRow['value']) && in_array(trim($pluginVersionRow['value']),['1.3.1','1.3.2'])) {
                if ($allOk)
                    $GLOBALS["db"]->insert(
                        'responselog',
                        array(
                            'localts' => time(),
                            'sent' => 0,
                            'actor' => "rolemaster",
                            'text' => '',
                            'action' => "rolecommand|DebugNotification@Director mode instruction processed",
                            'tag' => ""
                        )
                    );
                else 
                    $GLOBALS["db"]->insert(
                    'responselog',
                        array(
                            'localts' => time(),
                            'sent' => 0,
                            'actor' => "rolemaster",
                            'text' => '',
                            'action' => "rolecommand|DebugNotification@Director mode instruction failed",
                            'tag' => ""
                        )
                    );
            }
        }
        
        //print_r($response);
        
        
    }


    Logger::info("Successfully logged instruction command to responselog");
?>