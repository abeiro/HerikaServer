<?php 
require_once(__DIR__ . '/../../../../lib/logger.php');


require_once($GLOBALS["ENGINE_ROOT"] . "/lib/core/api_badge.class.php");
require_once($GLOBALS["ENGINE_ROOT"] . "/lib/core/llm_connector.class.php");
require_once($GLOBALS["ENGINE_ROOT"] . "/lib/core/npc_master.class.php");
require_once($GLOBALS["ENGINE_ROOT"]  . "lib/core/core_profiles.class.php");

$GLOBALS["ENGINE_PATH"]=$GLOBALS["ENGINE_ROOT"]; // Todo, make this uniform

$GLOBALS["active_profile"]=md5("The Narrator");
$GLOBALS["CURRENT_CONNECTOR"]=DMgetCurrentModel();
$GLOBALS["CHIM_NO_EXAMPLES"]=true; // When no assistant entry in history, will try ti provide a bogus example.

$connector=new LLMConnector();
$npcMaster=new NpcMaster();

$currentConnectorData = $connector->getById(intval($GLOBALS["CORE_CONNECTOR_DIRECTOR"] ?? 0));
$connectionHandler = $connector->getConnector($currentConnectorData);

$GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]=$currentConnectorData;
$GLOBALS["CURRENT_CONNECTOR"]=$currentConnectorData["driver"];

$connector->setOldGlobals($currentConnectorData);


if (!isset($GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]) ) {
        logMsg("Choose a LLM model and connector. Used connector: '{$GLOBALS["CORE_CONNECTOR_DIRECTOR"]}'",S_LOG_CRITICAL);

    } else {
        logMsg("Using {$GLOBALS["CURRENT_CONNECTOR"]}");

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

        $GLOBALS["custom_json_template"]=[
                "name" => "Name Surname/Name Nickname",
                "gender" => "male|female",
                "class" => "beggar|warrior|assassin|mage|farmer|soldier|merchant|noble|priest",
                "race" => "Nord|Imperial|Argonian|RedGuard|Orc|Breton",
                "location" => "current location name|nearby",
                "appearance" => "(describe actor)",
                "background" => "(give a background for the actor)",
                "speechStyle" => "(describe speech style)",
                "traits"=>"traits",
                "disposition" => "defiant|submissive|friendly|serious|sad|aggressive|cheerful|distrustful|furious|drunk|high",
                "goal"=>"NPC's goal in life."
        ];
        
        $GLOBALS["custom_json_template_as_text"]=json_encode($GLOBALS["custom_json_template"]);

        if (!$GLOBALS["argv"][3]) {
            $sysprompt="You are a game director, you must create a new NPC/actor";
        } else {
            $sysprompt="You are a game director, you must create a new NPC/actor following this directive: \"{$GLOBALS["argv"][3]}\"."; 
        }
        
        // Name randomizer
        $randomLetter = chr(rand(65, 90)); // ASCII A-Z
        $nameRandom="start by letter \"$randomLetter\"";

        // Database Prompt (Spawn Character)
        $prompt[] = array('role' => 'system', 'content' => $GLOBALS["PROMPT_HEAD"]."\n$sysprompt");
        $prompt[] = array('role' => 'user', 'content' =>"

 * Use Tamrielic names. Use Name and Surname (example Hans Ulfon) or name nickname (Example: Orik Stormbreaker, Nidia the Witch)
 * If not specified by directive, Name should $nameRandom
 * Human Races are Nord, Imperial RedGuard and Breton.
 * Give your answer as JSON object:
 {$GLOBALS["custom_json_template_as_text"]}
        
 * Use classes and races specified in the above JSON. Non existans classes or races will throw error.
 $sysprompt
");
        
        $customParm["response_format"]=["type"=>"json_object"];
        $customParm["MAX_TOKENS"]=4000;
        
        $GLOBALS["HOOKS"]["JSON_TEMPLATE"][]=function() {
            $GLOBALS["responseTemplate"] = $GLOBALS["custom_json_template"];
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
        
        $rawbuffer=$connectionHandler->close("spawncharacter");
        
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
            
            $currentData = $npcMaster->GetByName($response["name"]);

            $currentData["npc_static_bio"]=$response["background"];
            $currentData["goals"]=$response["goal"];
            $currentData["personality"]=$response["traits"];
            $currentData["appearance"]=$response["appearance"];
            $currentData["core"]="{$response["name"]} ({$response["race"]} {$response["gender"]}";
            $currentData["speechstyle"]=$response["speechStyle"];
            $currentMetadata = $npcMaster->getMetadata($currentData);
            $currentMetadata["is_rolemastered"] = true;
            $currentData = $npcMaster->setMetadata($currentData, $currentMetadata);
        
            $npcMaster->updateByArray($currentData);

            // This should be on new npc profile table
            $codename = npcNameToCodename($response["name"]);
           
            sleep(5);
            $GLOBALS["db"]->insert(
                'responselog',
                array(
                    'localts' => time(),
                    'sent' => 0,
                    'actor' => "rolemaster",
                    'text' => "",
                    'action' => "rolecommand|moveToPlayer@{$response["name"]}@$taskId@3",
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
