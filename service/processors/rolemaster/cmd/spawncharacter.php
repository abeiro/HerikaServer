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
            $sysprompt="You are a game director, you must create a new NPC/actor";
        } else {
            $sysprompt="You are a game director, you must create a new NPC/actor following this directive: \"{$GLOBALS["argv"][3]}\"."; 
        }
        
        // Name randomizer
        $randomLetter = chr(rand(65, 90)); // ASCII A-Z
        $nameRandom="Start by letter \"$randomLetter\"";

        // Database Prompt (Spawn Character)
        $prompt[] = array('role' => 'system', 'content' => $GLOBALS["PROMPT_HEAD"]."\n$sysprompt");
        $prompt[] = array('role' => 'user', 'content' =>"

 * Use Tamrielic names. Use Name and Surname (example Hans Ulfon) or name nickname (Example: Orik Stormbreaker, Nidia the Witch)
 * Name should $nameRandom
 * Human Races are Nord, Imperial RedGuard and Breton.
 * Give your answer as JSON object
 $sysprompt
");
        
        
        
        $customParm["response_format"]=["type"=>"json_object"];
        $customParm["MAX_TOKENS"]=4000;
        
        $GLOBALS["HOOKS"]["JSON_TEMPLATE"][]=function() {
            $GLOBALS["responseTemplate"] = [
                "name" => "Name Surname/Name Nickname",
                "gender" => "male|female",
                "class" => "beggar|warrior|assassin|mage|farmer|soldier|merchant|noble",
                "race" => "Nord|Imperial|Argonian|RedGuard|Orc|Breton",
                "location" => "current location name|nearby",
                "appearance" => "(describe actor)",
                "background" => "(give a background for the actor)",
                "speechStyle" => "(describe speech style)",
                "traits"=>"traits",
                "disposition" => "defiant|submissive|friendly|serious|sad|aggressive|cheerful|distrustful|furious|drunk|high",
                "goal"=>"NPC's goal in life."
            ];
        };

        $GLOBALS["CONNECTOR"][$GLOBALS["CURRENT_CONNECTOR"]]["json_schema"]=false;
        $GLOBALS["CHIM_NO_EXAMPLES"]=false;

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
        
        unset($GLOBALS["_JSON_BUFFER"]);
        $response=__jpd_decode_lazy($rawbuffer);
        
        if (isset($response[0]["name"]))
            $response=$response[0];

        
        
        if (is_array($response)) {
            
            $namedKey="{$response["name"]}_is_rolemastered";
        
            $GLOBALS["db"]->delete("conf_opts", "id='".$GLOBALS["db"]->escape($namedKey)."'");
            $GLOBALS["db"]->insert(
                'conf_opts',
                array(
                    'id' => $namedKey,
                    'value' => true
                )
            );
            
            print_r($response);
            
            require_once($enginePath . "lib/rolemaster_helpers.php");
            // Spawn NPC
            $taskId=uniqid();
            npcProfileBase($response["name"],$response["class"],$response["race"],$response["gender"],"nearby",$taskId);

            $PARMS["HERIKA_PERS"]="Roleplay as {$response["name"]} ({$response["race"]} {$response["gender"]})\n".
            "{$response["appearance"]}\n".
            "{$response["background"]}\n".
            "#SpeechStyle\n{$response["speechStyle"]}\n".
            "#Goal\n{$response["goal"]}\n".
            "#Traits\n{$response["traits"]}";

            $PARMS["HERIKA_DYNAMIC"]="\Mood: {$response["disposition"]}, goal is {$response["goal"]}";
            // Create profile
            createProfile($response["name"],$PARMS,true);
            
            // This should be on new npc profile table
            $codename = npcNameToCodename($response["name"]);
            $GLOBALS["db"]->insert(
                'npc_templates_custom',
                array(
                    'npc_name' => $codename,
                    'npc_dynamic' => "Goal: {$response["goal"]}. Traits: {$response["traits"]}",
                    'npc_pers' => "{$response["name"]} {$response["race"]} {$response["gender"]} {$response["background"]}\n#SpeechStyle\n{$response["speechStyle"]}\n",
                    "npc_misc" =>"rolemastered"
                )
            );

            $GLOBALS["db"]->insert(
                'responselog',
                array(
                    'localts' => time(),
                    'sent' => 0,
                    'actor' => "rolemaster",
                    'text' => "",
                    'action' => "rolecommand|moveToPlayer@{$response["name"]}@$taskId",
                    'tag' => ""
                )
            );

            $GLOBALS["db"]->insert(
                'rolemaster',
                array(
                    'localts' => time(),
                    'ttl' => 120,
                    'type' => "scenenote",
                    'data' => "{$response["name"]} appears. Who is this?. This new and UNKNOWN character draws the villagers' attention. Welcome scene.",
                )
            );

        }
        
    }


    Logger::info("Successfully logged instruction command to responselog");
?>