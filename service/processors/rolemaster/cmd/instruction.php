<?php 
require_once(__DIR__ . '/../../../../lib/logger.php');

require_once($GLOBALS["ENGINE_ROOT"] . "/lib/{$GLOBALS["DBDRIVER"]}.class.php");
if (!isset($GLOBALS["db"])) { $GLOBALS["db"] = new sql(); }

require_once($GLOBALS["ENGINE_ROOT"] . "/lib/core/api_badge.class.php");
require_once($GLOBALS["ENGINE_ROOT"] . "/lib/core/llm_connector.class.php");
require_once($GLOBALS["ENGINE_ROOT"] . "/lib/core/npc_master.class.php");
require_once($GLOBALS["ENGINE_ROOT"] . "/lib/core/core_profiles.class.php");
require_once($GLOBALS["ENGINE_ROOT"] . "/lib/relationship_manager.php");

$GLOBALS["ENGINE_PATH"]=$GLOBALS["ENGINE_ROOT"]; // Todo, make this uniform

$GLOBALS["active_profile"]=md5("The Narrator");
$GLOBALS["CURRENT_CONNECTOR"]=DMgetCurrentModel();
$GLOBALS["CHIM_NO_EXAMPLES"]=true; // When no assistant entry in history, will try ti provide a bogus example.

$connector=new LLMConnector();
$currentConnectorData = $connector->getById(intval($GLOBALS["CORE_CONNECTOR_DIRECTOR"] ?? 0));
$connectionHandler = $connector->getConnector($currentConnectorData);

$GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]=$currentConnectorData;
$GLOBALS["CURRENT_CONNECTOR"]=$currentConnectorData["driver"];

$connector->setOldGlobals($currentConnectorData);

$isBoredInstruction = (($GLOBALS["argv"][4] ?? "") === "bored");
$boredSeedActor = trim((string)($GLOBALS["argv"][5] ?? ""));
$GLOBALS["ROLEMASTER_BORED_MODE"] = $isBoredInstruction;
$GLOBALS["ROLEMASTER_BORED_SEED"] = $boredSeedActor;
$GLOBALS["ROLEMASTER_BORED_ALLOWED_ACTORS"] = [];

if (!isset($GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]) ) {
        logMsg("Choose a LLM model and connector. Used connector: '{$GLOBALS["CORE_CONNECTOR_DIRECTOR"]}'",S_LOG_CRITICAL);

    } else {
        logMsg("Using {$GLOBALS["CURRENT_CONNECTOR"]}");
    
        $sqlfilter=" and type not in ('prechat','backgroundaction','addnpc','addbgnpc','travelcancel','innerchat') ";

        $historyActor = ($isBoredInstruction && $boredSeedActor !== "") ? $boredSeedActor : $GLOBALS["PLAYER_NAME"];
        $contextDataHistoric = DataLastDataExpandedFor($historyActor, -50,$sqlfilter);    // Full context
        
        foreach ($contextDataHistoric as $element) {
            // We should clean here background events entries
        }
        
        $contextDataHistoric =array_merge([["role"=>"user","content"=>"# HISTORIC DIALOGUE AND EVENTS IN CHRONOLOGICAL ORDER"]], $contextDataHistoric);

        $GLOBALS["PROMPT_NEARBY_SECTIONS"] = "";
        $contextDataWorld = DataLastInfoFor(
            "",
            -2,
            $addNPCDescriptions=true,
            $excludeBusy=true,
            $excludeFarAway=$isBoredInstruction
        )??[];
        $nearbySceneContext = trim((string)($GLOBALS["PROMPT_NEARBY_SECTIONS"] ?? ""));
        $contextDataFull = array_merge($contextDataWorld, $contextDataHistoric);
        $historyData="";

            
        foreach ($contextDataFull as $element) {
        
            $historyData.=trim("{$element["content"]}").PHP_EOL.PHP_EOL;
            
        }
        if ($nearbySceneContext !== "") {
            $historyData .= $nearbySceneContext . PHP_EOL.PHP_EOL;
        }
        
        $recap=$GLOBALS["db"]->fetchOne("SELECT * FROM rolemaster where type='story_summary' ORDER BY rowid DESC LIMIT 1");
        if (isset($recap["data"])) {
            $historyData=$recap["data"]."\n".$historyData;

        }

        // Inject relationship context for director awareness
        // Get nearby NPCs from DataBeingsInCloseRange (same source as DataLastInfoFor uses)
        $nearbyNpcsRaw = DataBeingsInCloseRange($isBoredInstruction);
        $nearbyNpcsList = array_filter(array_map('trim', explode('|', $nearbyNpcsRaw)));
        if ($isBoredInstruction) {
            $allowedActorMap = chimRolemasterBoredActorMap(
                $nearbyNpcsRaw,
                (string)$GLOBALS["PLAYER_NAME"],
                $boredSeedActor
            );
            $GLOBALS["ROLEMASTER_BORED_ALLOWED_ACTORS"] = $allowedActorMap;
            $historyData .= "# BORED EVENT SCENE\n";
            $historyData .= "Selected initiating actor: " . ($boredSeedActor !== "" ? $boredSeedActor : "not provided") . "\n";
            $historyData .= "Nearby eligible actors: " . implode(", ", array_values($allowedActorMap)) . "\n\n";
        }

        // Build relationship context for these NPCs
        $relContext = RelationshipManager::buildDirectorContext($nearbyNpcsList);
        if (!empty($relContext)) {
            $historyData .= "\n" . $relContext . "\n";
        }

        // Function stuff
        require($enginePath . "functions/functions_instruction.php");

        if (!function_exists('herikaActionCatalogIsActionEnabled') || herikaActionCatalogIsActionEnabled("ReturnBackHome")) {
            $GLOBALS["ENABLED_FUNCTIONS"][]="ReturnBackHome";
            $GLOBALS["FUNCTIONS"][]=$GLOBALS["BASE_FUNCTIONS"]["ReturnBackHome"];
        }

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

// Load director examples prompt from database with fallback
$commonprompt = '';
if (!$isBoredInstruction) {
    try {
        $promptData = $GLOBALS["db"]->fetchOne("SELECT custom_prompt, default_prompt FROM prompts WHERE prompt_key = 'director_examples_prompt'");
        if ($promptData) {
            $commonprompt = (!empty($promptData['custom_prompt'])) ? $promptData['custom_prompt'] : $promptData['default_prompt'];
        }
    } catch (Exception $e) {
        Logger::warn("Failed to load director_examples_prompt from database, using hardcoded fallback: " . $e->getMessage());
    }

    // Hardcoded fallback if database query failed
    if (!$commonprompt) {
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
  "scene_note": "Is a sad moment, generally speaking."
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
  "scene_note": "actor a looks disheveled but charming, half-joking and half-serious. Actor b is unsure whether to laugh, help, or walk away. Other actors watch this two guys with curiosity"
 }
]
}

';
    }
}
        if (!$GLOBALS["argv"][3]) {
            $userprompt="";
            $sysprompt="";
        } else {
            $sysprompt="user request: \"{$GLOBALS["argv"][3]}\". (As game director, you now must issue an instruction to any actor to make this wish happen. If user is addressing an actor, use instruction on it)"; 
            $userprompt="* Fufill the user's request no matter what, even if it means breaking roleplay.";
        }
        
        // Smart Bored uses a dedicated system prompt instead of the creative Director contract.
        $directorSystemPromptKey = $isBoredInstruction
            ? 'director_bored_event_system_prompt'
            : 'director_system_prompt';
        $directorSystemPrompt = null;
        try {
            $promptData = $GLOBALS["db"]->fetchOne(
                "SELECT custom_prompt, default_prompt FROM prompts WHERE prompt_key = '{$directorSystemPromptKey}'"
            );
            if ($promptData) {
                $directorSystemPrompt = (!empty($promptData['custom_prompt'])) ? $promptData['custom_prompt'] : $promptData['default_prompt'];
            }
        } catch (Exception $e) {
            Logger::warn("Failed to load {$directorSystemPromptKey} from database, using hardcoded fallback: " . $e->getMessage());
        }
        
        if (!$directorSystemPrompt) {
            $directorSystemPrompt = $isBoredInstruction
                ? chimRolemasterDefaultBoredSystemPrompt()
                : "You are a game director, and we are roleplaying Skyrim in the Tamriel universe. You must create a instruction for an actor to generate new content/events on game.";
        }
        
        $prompt[] = array('role' => 'system', 'content' => "$directorSystemPrompt$commonprompt");
        $prompt[] = array('role' => 'user', 'content' => "# Contextual data\n$historyData");
        
        $functionList = "  ** " . implode("\n  ** ", $fnames);
        $directorInstructionRules = null;
        if ($isBoredInstruction) {
            $boredEventRules = null;
            try {
                $promptData = $GLOBALS["db"]->fetchOne(
                    "SELECT custom_prompt, default_prompt FROM prompts WHERE prompt_key = 'director_bored_event_rules'"
                );
                if ($promptData) {
                    $boredEventRules = !empty($promptData['custom_prompt'])
                        ? $promptData['custom_prompt']
                        : $promptData['default_prompt'];
                }
            } catch (Exception $e) {
                Logger::warn(
                    "Failed to load director_bored_event_rules from database, using hardcoded fallback: "
                    . $e->getMessage()
                );
            }

            $directorInstructionRules = chimRolemasterRenderBoredEventRules(
                (string)$boredEventRules,
                $boredSeedActor,
                (string)$GLOBALS["PLAYER_NAME"],
                $GLOBALS["ROLEMASTER_BORED_ALLOWED_ACTORS"] ?? [],
                $functionList
            );
        } else {
            try {
                $promptData = $GLOBALS["db"]->fetchOne("SELECT custom_prompt, default_prompt FROM prompts WHERE prompt_key = 'director_instruction_rules'");
                if ($promptData) {
                    $directorInstructionRules = (!empty($promptData['custom_prompt'])) ? $promptData['custom_prompt'] : $promptData['default_prompt'];
                }
            } catch (Exception $e) {
                Logger::warn("Failed to load director_instruction_rules from database, using hardcoded fallback: " . $e->getMessage());
            }

            if (!$directorInstructionRules) {
                $directorInstructionRules = "Just provide instructions! You can also provide more than one instruction, but one per actor (keep limit at  2 or 3 max actors)\nIn addition, follow these general scene rules as a game director:\n * Use any actor in NEARBY ACTORS/NPC IN THE SCENE list ({PLAYER_NAME},busy actors and far away actors are EXCLUDED!)\n * Continue the scene as naturally and fully as possible, unless the user explicitly requests a new one. You can specify actions to reinforce the actors' dialogue.\n * If there are more actors in the room, try to involve them in the conversation.\n * When dialogue becomes repetitive, make a plot twist.\n * If a character reuses the same argument too often, nudge the scene towards a new topic.\n * Occasionally introduce subtle foreshadowing or hint at future events, dangers, or quests.\n * Do not resolve everything neatly—keep room for ongoing tension or future continuation.\n * You must always provide dialogue instructions for the character, as every request requires a dialogue response.\n * Here are a list of actions that can be used: \n{FUNCTION_LIST}\n  ** JustTalk \n * Add a Scene Note: A brief description of the topic, mood, or idea introduced by the instruction. Should serve to guide the desired instruction to become reality. Other actors can see this to properly react.\n * If scene is getting boring/repetitive, add a plot twist";
            }

            $directorInstructionRules = str_replace(
                ['{PLAYER_NAME}', '{FUNCTION_LIST}'],
                [$GLOBALS["PLAYER_NAME"], $functionList],
                $directorInstructionRules
            );
        }
        
        // Database Prompt (Director)
        $prompt[] = array('role' => 'user', 'content' => "$sysprompt\n$directorInstructionRules\n$userprompt");
        
        
        
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

        
        // Force unset json schema
        $GLOBALS["CONNECTOR"][$GLOBALS["CURRENT_CONNECTOR"]]["json_schema"]=false;

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
        
        $rawbuffer=$connectionHandler->close("instruction");
        
        function parseInstruction($response) {
            // Extract the character name and the instruction line
            
            $characterName = trim($response["character"] ?? 'Unknown');
            $instructionText = trim($response["instruction"] ?? 'No instruction text');
            $action = !empty($response["action"]) ? "{$response["action"]} " . ($response["target"] ?? "") : "";
            if (!empty($GLOBALS["ROLEMASTER_BORED_MODE"])) {
                $canonicalListener = chimRolemasterBoredCanonicalActor(
                    (string)($response["target"] ?? ""),
                    $GLOBALS["ROLEMASTER_BORED_ALLOWED_ACTORS"] ?? []
                );
                $instructionText .= chimRolemasterBoredListenerRequirement(
                    (string)($response["target"] ?? ""),
                    $GLOBALS["ROLEMASTER_BORED_ALLOWED_ACTORS"] ?? []
                );
                Logger::info(
                    "Queued bored rolemaster instruction for '{$characterName}'"
                    . ($canonicalListener === null ? " without a valid listener" : " with listener '{$canonicalListener}'")
                );
            }
        
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
                    'tag' => __FILE__ . ":" . __LINE__
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
            if ($isBoredInstruction) {
                $originalInstructionCount = count($response["instructions"]);
                $response["instructions"] = chimRolemasterFilterBoredInstructions(
                    $response["instructions"],
                    $GLOBALS["ROLEMASTER_BORED_ALLOWED_ACTORS"] ?? [],
                    $boredSeedActor
                );
                if (empty($response["instructions"])) {
                    Logger::warn("Discarded bored rolemaster response because it omitted the selected actor or used no eligible nearby actors");
                } elseif (count($response["instructions"]) !== $originalInstructionCount) {
                    $discardedCount = $originalInstructionCount - count($response["instructions"]);
                    Logger::info(
                        "Discarded {$discardedCount} secondary or invalid bored rolemaster instruction(s); "
                        . "the listener will respond through normal dialogue routing"
                    );
                }
            }
            $allOk=!empty($response["instructions"]);
            foreach ($response["instructions"] as $r) {
                $allOk=$allOk && parseInstruction($r);
                parseSceneNote($r);
            }
        } else 
            $allOk=false;

        
        if (isset($GLOBALS["argv"][4]) && $GLOBALS["argv"][4]=="notify") {
            $pluginVersionRow = $GLOBALS['db']->fetchOne("SELECT value FROM conf_opts WHERE id='plugin_dll_version'");
            if ($pluginVersionRow && isset($pluginVersionRow['value'])) {
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
