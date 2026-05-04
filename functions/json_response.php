<?php
    require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."emote_moods.php");

    global $FUNC_LIST;
    global $responseTemplate;
    global $structuredOutputTemplate;
    global $grammar;
    $FUNC_LIST=[];
    $responseTemplate=[];
    $structuredOutputTemplate=array();
    $grammar = "";

    if (!function_exists('chimIsDirectNarratorDialogue')) {
        function chimIsDirectNarratorDialogue() {
            if (isset($GLOBALS["DIRECT_NARRATOR_DIALOGUE"])) {
                return (bool)$GLOBALS["DIRECT_NARRATOR_DIALOGUE"];
            }

            return isset($GLOBALS["gameRequest"][0]) && $GLOBALS["gameRequest"][0] === "narrator_inputtext";
        }
    }

    setActions();
    setResponseTemplate();
    setStructuredOutputTemplate();
    setGBNFGrammar();

    // allow for edits to the json templates by extensions
    requireFilesRecursively(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR,"json_response_custom.php");

    if (isset($GLOBALS["HOOKS"]) && isset($GLOBALS["HOOKS"]["JSON_TEMPLATE"]) && is_array($GLOBALS["HOOKS"]["JSON_TEMPLATE"])) {
        foreach ($GLOBALS["HOOKS"]["JSON_TEMPLATE"] as $hook) {
            call_user_func($hook);

        }
    }

    // specify the available actions which will be made available in the context
    Function setActions() {
        // Initialize actions list
        $GLOBALS["PROMPT_ACTIONS_LIST"] = "";
        
        // Skip actions list for narration events (The Narrator doesn't need action options for atmospheric descriptions)
        if (isset($GLOBALS["gameRequest"]) && $GLOBALS["gameRequest"][0] === "narration") {
            $GLOBALS["FUNC_LIST"] = ["Talk"];  // Only Talk action for narration
            return;
        }

        if (chimIsDirectNarratorDialogue()) {
            $GLOBALS["FUNC_LIST"] = ["Talk"];
            return;
        }
        
        // Build actions list separately (not in PROMPT_HEAD)
        if (isset($GLOBALS["FUNCTIONS_ARE_ENABLED"]) && $GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
            $GLOBALS["PROMPT_ACTIONS_LIST"] = "\n<available_actions_list>\n";
            $GLOBALS["PROMPT_ACTIONS_LIST"] .= $GLOBALS["COMMAND_PROMPT_FUNCTIONS"];
            
            foreach ($GLOBALS["FUNCTIONS"] as $index => $function) {
                if (!$function) {
                    continue;
                }
                
                $fname=getFunctionCodeName($function["name"]);

                if (!in_array($fname,$GLOBALS["ENABLED_FUNCTIONS"])) {
                    continue;
                }

                $GLOBALS["FUNC_LIST"][]=$function["name"];
                if ($function["name"]==$GLOBALS["F_NAMES"]["Attack"] || $function["name"]==$GLOBALS["F_NAMES"]["Brawl"] || $function["name"]==$GLOBALS["F_NAMES"]["AttackHunt"]) {
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$function["description"]})";
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="(available targets: ".implode(",",$GLOBALS["FUNCTION_PARM_INSPECT"]).")";
                } else if ($function["name"]==$GLOBALS["F_NAMES"]["SearchMemory"]) {
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]}(keywords to search ({$function["description"]})";
                } else if ($fname == "GiveGoldTo") {
                    require_once(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "data_functions.php");
                    $goldAmount = getGoldFromMetadata();
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$function["description"]}). You currently have {$goldAmount} gold. Put the amount in the 'item' field.";
                } else if ($fname == "HireCarriage") {
                    $majorDestinations = "Whiterun, Solitude, Markarth, Riften, Windhelm";
                    $minorDestinations = "Morthal, Dawnstar, Falkreath, Winterhold, Darkwater Crossing, Dragon Bridge, Ivarstead, Karthwasten, Kynesgrove, Old Hroldan, Riverwood, Rorikstead, Shor's Stone, Stonehills";
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$function["description"]}). Vanilla carriage costs: 20 gold for major destinations ({$majorDestinations}) and 50 gold for minor destinations ({$minorDestinations}). Put the destination in the 'target' field. Keep the spoken line short, accept payment, and do not ask questions.";
                } else if ($fname == "HireFerry") {
                    $fiftyGoldDestinations = "Windhelm, Dawnstar, Solitude, Giant's Tooth";
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$function["description"]}). Vanilla ferry costs: 50 gold for {$fiftyGoldDestinations}, 500 gold for Icewater Jetty, and free travel to Castle Volkihar. Put the destination in the 'target' field. Keep the spoken line short, accept payment when needed, and do not ask questions.";
                } else if ($fname == "AddBounty") {
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$function["description"]}). Crime types and vanilla bounty amounts: Assault=40 (violent), Murder=1000 (violent), Theft=100, Pickpocketing=25, Trespassing=5, Jailbreak=100, Custom (specify amount in 'item' field). Put the crime type in 'target'.";
                } else if ($fname == "PayBounty") {
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$function["description"]}). Use when the player agrees to pay now. Bounty payment and stolen-item confiscation happen immediately in one step. After using it, reply with a short confirmation and end the conversation.";
                } else if ($fname == "ArrestPlayer") {
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$function["description"]}). Use for serious crimes or if the player refuses to pay their bounty. The player gets a submit/resist popup. Submit sends them to jail with inventory confiscated. Resist makes guards attack.";
                } else if ($fname == "ForgiveCrime") {
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$function["description"]}). Use when the player successfully persuades, bribes, or invokes thane status to clear their bounty.";
                } else {
                    $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: {$function["name"]} ({$function["description"]})";
                }
            }
            
            $GLOBALS["PROMPT_ACTIONS_LIST"].="\nAVAILABLE ACTION: Talk\n</available_actions_list>";
            $GLOBALS["FUNC_LIST"][]="Talk";
            shuffle($GLOBALS["FUNC_LIST"]);
        }
    }

    // specify the json object that will be requested from the LLM (via prompt, not enforced)
    Function setResponseTemplate() {
        $moods=normalizeEmoteMoods($GLOBALS["EMOTEMOODS"] ?? "");
        shuffle($moods);
    
        // Auto-detect language from TTS config if LLM_LANG not set
        if (!isset($GLOBALS["LLM_LANG"]) && isset($GLOBALS["LANG_LLM_XTTS"]) && $GLOBALS["LANG_LLM_XTTS"]) {
            if (isset($GLOBALS["TTS"]["XTTSFASTAPI"]["language"])) {
                $GLOBALS["LLM_LANG"] = $GLOBALS["TTS"]["XTTSFASTAPI"]["language"];
            } elseif (isset($GLOBALS["TTS"]["CHATTERBOX"]["language"])) {
                $GLOBALS["LLM_LANG"] = $GLOBALS["TTS"]["CHATTERBOX"]["language"];
            } elseif (isset($GLOBALS["TTS"]["POCKETTTS"]["language"])) {
                $GLOBALS["LLM_LANG"] = $GLOBALS["TTS"]["POCKETTTS"]["language"];
            } elseif (isset($GLOBALS["TTS"]["MELOTTS"]["language"])) {
                $ttsLang = strtolower($GLOBALS["TTS"]["MELOTTS"]["language"]);
                $GLOBALS["LLM_LANG"] = $ttsLang;
            }
        }
    
        // Build listener description - for rechat events, encourage addressing the previous speaker
        $listenerDesc = "specify who {$GLOBALS["HERIKA_NAME"]} is talking to, comma separated, max two listeners, in addressing order";
        if (isset($GLOBALS["gameRequest"])) {
            if (in_array($GLOBALS["gameRequest"][0], ["inputtext", "inputtext_s", "ginputtext", "ginputtext_s"], true)) {
                $replyTargetName = extractSpeakerNameFromInputEvent($GLOBALS["gameRequest"][3] ?? "");
                if ($replyTargetName === "") {
                    $replyTargetName = trim((string)($GLOBALS["PLAYER_NAME"] ?? ""));
                }
                if ($replyTargetName !== "") {
                    $listenerDesc = "specify who {$GLOBALS["HERIKA_NAME"]} is talking to. Treat {$replyTargetName}, who just spoke, as the current addressee unless the reply clearly shifts to someone else.";
                }
            } elseif (in_array($GLOBALS["gameRequest"][0], ["rechat"], true)) {
                $listenerDesc = "specify who {$GLOBALS["HERIKA_NAME"]} is talking to. Address whoever just spoke - can be any person in the conversation.";
            }
        }
    
        // Determine message description based on inline narration mode.
        $inlineNarrationMode = strtolower(trim((string)($GLOBALS["INLINE_NARRATION_MODE"] ?? '')));
        if (!in_array($inlineNarrationMode, ['disabled', 'narrator', 'npc'], true)) {
            $inlineNarrationMode = (isset($GLOBALS["INLINE_NARRATION_ENABLED"]) && $GLOBALS["INLINE_NARRATION_ENABLED"]) ? 'narrator' : 'disabled';
        }
        if (chimIsDirectNarratorDialogue()) {
            $inlineNarrationMode = 'disabled';
        }
        $inlineNarrationEnabled = $inlineNarrationMode !== 'disabled';
        $messageDescription = "lines of dialogue";
        if ($inlineNarrationEnabled) {
            $messageDescription = "If needed, start with one brief third-person narration block in single asterisks, then put {$GLOBALS["HERIKA_NAME"]}'s spoken text after it. Example: *She smiles* It's good to see you again, my friend! Do not wrap the entire reply in asterisks, and keep spoken dialogue outside the asterisks.";
        } elseif (chimIsDirectNarratorDialogue()) {
            $messageDescription = "plain spoken dialogue addressed directly to {$GLOBALS["PLAYER_NAME"]}. Do not include third-person narration, scene description, stage directions, or text in asterisks.";
        }
    
        if (isset($GLOBALS["FEATURES"]["MISC"]["JSON_DIALOGUE_FORMAT_REORDER"])&&($GLOBALS["FEATURES"]["MISC"]["JSON_DIALOGUE_FORMAT_REORDER"])) {
            if (isset($GLOBALS["LANG_LLM_XTTS"])&&($GLOBALS["LANG_LLM_XTTS"])) {
                $GLOBALS["responseTemplate"] = [
                    "character"=>$GLOBALS["HERIKA_NAME"],
                    "listener"=>$listenerDesc,
                    "message"=>$messageDescription,
                    "mood"=>implode("|",$moods),
                    "action"=>implode("|",$GLOBALS["FUNC_LIST"]),
                    "target"=>"action target actor|action destination location name",
                    "item"=>"item name (REQUIRED when action is GiveItemTo or PickupItem or CastSpell - use exact item name from inventory or spell name from spells) OR amount of gold (REQUIRED when action is GiveGoldTo - number as string, e.g. '50')",
                    "lang"=>isset($GLOBALS["LLM_LANG"])?$GLOBALS["LLM_LANG"]:"en|es|fr|de|it|pt|ru|zh-cn|ja|ko|ar|pl|tr|cs|nl|hu|hi",
                ];
            } else {
                $GLOBALS["responseTemplate"] = [
                    "character"=>$GLOBALS["HERIKA_NAME"],
                    "listener"=>$listenerDesc,
                    "message"=>$messageDescription,
                    "mood"=>implode("|",$moods),
                    "action"=>implode("|",$GLOBALS["FUNC_LIST"]),
                    "target"=>"action target actor|action destination location name",
                    "item"=>"item name (REQUIRED when action is GiveItemTo or PickupItem or CastSpell - use exact item name from inventory or spell name from spells) OR amount of gold (REQUIRED when action is GiveGoldTo - number as string, e.g. '50')"
                ];
            }
        } else {
            if (isset($GLOBALS["LANG_LLM_XTTS"])&&($GLOBALS["LANG_LLM_XTTS"])) {
                $GLOBALS["responseTemplate"] = [
                    "character"=>$GLOBALS["HERIKA_NAME"],
                    "listener"=>$listenerDesc,
                    "mood"=>implode("|",$moods),
                    "action"=>implode("|",$GLOBALS["FUNC_LIST"]),
                    "target"=>"action target actor|action destination location name",
                    "item"=>"item name (REQUIRED when action is GiveItemTo or PickupItem or CastSpell - use exact item name from inventory or spell name from spells) OR amount of gold (REQUIRED when action is GiveGoldTo - number as string, e.g. '50')",
                    "lang"=>isset($GLOBALS["LLM_LANG"])?$GLOBALS["LLM_LANG"]:"en|es|fr|de|it|pt|ru|zh-cn|ja|ko|ar|pl|tr|cs|nl|hu|hi",
                    "message"=>$messageDescription
                ];
            } else {
                $GLOBALS["responseTemplate"] = [
                    "character"=>$GLOBALS["HERIKA_NAME"],
                    "listener"=>$listenerDesc,
                    "mood"=>implode("|",$moods),
                    "action"=>implode("|",$GLOBALS["FUNC_LIST"]),
                    "target"=>"action target actor|action destination location name",
                    "item"=>"item name (REQUIRED when action is GiveItemTo or PickupItem or CastSpell - use exact item name from inventory or spell name from spells)",
                    "message"=>$messageDescription
                ];
            }
        }

        // emotions expression:
        if ($GLOBALS['use_emotions_expression']) {
            if (!array_key_exists("emotion", $GLOBALS["responseTemplate"])) {
                $GLOBALS["responseTemplate"]["emotion"] = 
                "calm|surprised|aroused|desire|love|happy|amusement|gratitude|proud|anxious|fearful|panic|grieving|envious|jealous|sad|disappointed|ashamed|angry|offended|disgusted|sarcastic";
            }
            if (!array_key_exists("emotion_intensity", $GLOBALS["responseTemplate"])) {
                $GLOBALS["responseTemplate"]["emotion_intensity"] = "low|moderate|strong";
            }
        }
        
        // request speaking tones from the LLM when using zonos TTS
        if (zonosIsActive()) {
            $GLOBALS["responseTemplate"] = array_merge($GLOBALS["responseTemplate"], [
                "response_tone_happiness"=>"Value from 0-1",
                "response_tone_sadness"=>"Value from 0-1",
                "response_tone_disgust"=>"Value from 0-1",
                "response_tone_fear"=>"Value from 0-1",
                "response_tone_surprise"=>"Value from 0-1",
                "response_tone_anger"=>"Value from 0-1",
                "response_tone_other"=>"Value from 0-1",
                "response_tone_neutral"=>"Value from 0-1"
            ]);
        }
    }
    
    // for use with openai and openrouter providers that support structured outputs to enforce a json schema
    Function setStructuredOutputTemplate() {
        $moods=normalizeEmoteMoods($GLOBALS["EMOTEMOODS"] ?? "");
        shuffle($moods);

        // Determine message description based on inline narration mode.
        $inlineNarrationMode = strtolower(trim((string)($GLOBALS["INLINE_NARRATION_MODE"] ?? '')));
        if (!in_array($inlineNarrationMode, ['disabled', 'narrator', 'npc'], true)) {
            $inlineNarrationMode = (isset($GLOBALS["INLINE_NARRATION_ENABLED"]) && $GLOBALS["INLINE_NARRATION_ENABLED"]) ? 'narrator' : 'disabled';
        }
        if (chimIsDirectNarratorDialogue()) {
            $inlineNarrationMode = 'disabled';
        }
        $inlineNarrationEnabled = $inlineNarrationMode !== 'disabled';
        $messageDescription = "lines of {$GLOBALS["HERIKA_NAME"]}'s dialogue";
        if ($inlineNarrationEnabled) {
            $messageDescription = "If needed, start with one brief third-person narration block in single asterisks, then put {$GLOBALS["HERIKA_NAME"]}'s spoken text after it. Example: *She smiles* It's good to see you again, my friend! Do not wrap the entire reply in asterisks, and keep spoken dialogue outside the asterisks.";
        } elseif (chimIsDirectNarratorDialogue()) {
            $messageDescription = "plain spoken dialogue addressed directly to {$GLOBALS["PLAYER_NAME"]}. Do not include third-person narration, scene description, stage directions, or text in asterisks.";
        }

        $GLOBALS["structuredOutputTemplate"] = array(
            "type" => "json_schema",
            "json_schema" => array(
                "name" => "response",
                "schema" => array(
                    "type" => "object",
                    "properties" => array(
                        "character" => array(
                            "type" => "string",
                            "description" => $GLOBALS["HERIKA_NAME"]
                        ),
                        "listener" => array(
                            "type" => "string",
                            "description" => "specify who {$GLOBALS["HERIKA_NAME"]} is talking to, comma separated, max two listeners, in addressing order",
                        ),
                        "message" => array(
                            "type" => "string",
                            "description" => $messageDescription
                        ),
                        "mood" => empty($moods) ?
                            array(
                                "type" => "string",
                                "description" => "mood to use while speaking"
                            ) :
                            array(
                                "type" => "string",
                                "description" => "mood to use while speaking",
                                "enum" => $moods
                            ),
                        "action" => empty($GLOBALS["FUNC_LIST"]) ? 
                            array(
                                "type" => "string",
                                "description" => "a valid action (refer to available actions list)"
                            ) :
                            array(
                                "type" => "string",
                                "description" => "a valid action (refer to available actions list)",
                                "enum" => $GLOBALS["FUNC_LIST"]
                            ),
                        "target" => array(
                            "type" => "string",
                            "description" => "action target actor| action destination location name"
                        ),
                        "item" => array(
                            "type" => "string",
                            "description" => "item name (REQUIRED when action is GiveItemTo or PickupItem or CastSpell - use exact name from inventory, nearby_items, or spell name from spells) OR amount of gold (REQUIRED when action is GiveGoldTo - number as string, e.g. '50')"
                        )
                    ),
                    "required" => [
                        "character",
                        "listener",
                        "message",
                        "mood",
                        "action",
                        "target",
                        "item"
                    ],
                    "additionalProperties" => false
                ),
                "strict" => true
            )
        );

        if (isset($GLOBALS["LANG_LLM_XTTS"])&&($GLOBALS["LANG_LLM_XTTS"])) {
            if (isset($GLOBALS["LLM_LANG"])) {

                $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["properties"] = array_merge(
                    $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["properties"], array(
                        "lang" => array(
                            "type" => "string",
                            "description" => "Language to use. Must be {$GLOBALS["LLM_LANG"]}"
                        )
                    ));
                $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["required"][]="lang";
            } else {
                $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["properties"] = array_merge(
                    $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["properties"], array(
                        "lang" => array(
                            "type" => "string",
                            "description" => "Language to use"
                        )
                    ));
                $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["required"][]="lang";    
            }

        }

        // emotions expression:
        if ($GLOBALS['use_emotions_expression']) {
            $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["properties"] = array_merge(
                $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["properties"], array(
                    "emotion" => array(
                        "type" => "string",
                        "description" => "The emotion expressed."
                    ),
                    "emotion_intensity" => array(
                        "type" => "string",
                        "description" => "The intensity of the emotion expressed, possible values ​​'low', 'moderate' or 'strong'."
                    )
                ));
            $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["required"][]="emotion";
            $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["required"][]="emotion_intensity";
        }
        
        // request speaking tones from the LLM when using zonos TTS
        if (zonosIsActive()) {
            $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["properties"] = array_merge(
                $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["properties"], array(
                    "response_tone_happiness" => array(
                        "type" => "number",
                        "description" => "Value from 0-1",
                        "default" => 0
                    ),
                    "response_tone_sadness" => array(
                        "type" => "number",
                        "description" => "Value from 0-1",
                        "default" => 0
                    ),
                    "response_tone_disgust" => array(
                        "type" => "number",
                        "description" => "Value from 0-1",
                        "default" => 0
                    ),
                    "response_tone_fear" => array(
                        "type" => "number",
                        "description" => "Value from 0-1",
                        "default" => 0
                    ),
                    "response_tone_surprise" => array(
                        "type" => "number",
                        "description" => "Value from 0-1",
                        "default" => 0
                    ),
                    "response_tone_anger" => array(
                        "type" => "number",
                        "description" => "Value from 0-1",
                        "default" => 0
                    ),
                    "response_tone_other" => array(
                        "type" => "number",
                        "description" => "Value from 0-1",
                        "default" => 0
                    ),
                    "response_tone_neutral" => array(
                        "type" => "number",
                        "description" => "Value from 0-1",
                        "default" => 1.0
                    )
                )
            );
            $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["required"] = array_merge(
                $GLOBALS["structuredOutputTemplate"]["json_schema"]["schema"]["required"], [
                    "response_tone_happiness",
                    "response_tone_sadness",
                    "response_tone_disgust",
                    "response_tone_fear",
                    "response_tone_surprise",
                    "response_tone_anger",
                    "response_tone_other",
                    "response_tone_neutral"
                ]
            );
        }
    }

    // sets the grammar used by koboldcpp
    Function setGBNFGrammar() {
        // build the string for moods
        // should look like: ("\"playful\"" | "\"default\"" | ...)
        $moods=normalizeEmoteMoods($GLOBALS["EMOTEMOODS"] ?? "");
        shuffle($moods);

        $moods_quoted = [];
        foreach ($moods as $n=>$mood) {
            $moods_quoted[] = '"\"'.$mood.'\""';
        }
        $moods_str = "(".implode(' | ', $moods_quoted).")";

        if (sizeof($moods) == 0) {
            $moods_str = "string";
        }

        // build the string for actions
        // should look like: ("\"Talk\"" | "\"Attack\"" | ...)
        $actions_quoted = [];
        foreach ($GLOBALS["FUNC_LIST"] as $n=>$action) {
            $actions_quoted[] = '"\"'.$action.'\""';
        }
        $actions_str = "(".implode(' | ', $actions_quoted).")";

        if (sizeof($GLOBALS["FUNC_LIST"]) == 0) {
            $actions_str = "string";
        }

        // build the string for zonos tts tones
        $zonos_tones_str = zonosIsActive()
            ? '"," ws root-response-tone-happiness "," ws root-response-tone-sadness "," ws root-response-tone-disgust "," ws root-response-tone-fear ","'.
              ' ws root-response-tone-surprise "," ws root-response-tone-anger "," ws root-response-tone-other "," ws root-response-tone-neutral '
            : "";

        // using a quoted heredoc to avoid having to escape everything
        $GLOBALS["grammar"] = <<<'EOD'
        root ::= "{" ws root-character "," ws root-listener "," ws root-message "," ws root-mood "," ws root-action "," ws root-target {$ZONOS}"}" ws
        root-character ::= "\"character\"" ":" ws string
        root-listener ::= "\"listener\"" ":" ws string
        root-message ::= "\"message\"" ":" ws string
        root-mood ::= "\"mood\"" ":" ws {$MOODS}
        root-action ::= "\"action\"" ":" ws {$ACTIONS}
        root-target ::= "\"target\"" ":" ws string
        root-response-tone-happiness ::= "\"response-tone-happiness\"" ":" ws number
        root-response-tone-sadness ::= "\"response-tone-sadness\"" ":" ws number
        root-response-tone-disgust ::= "\"response-tone-disgust\"" ":" ws number
        root-response-tone-fear ::= "\"response-tone-fear\"" ":" ws number
        root-response-tone-surprise ::= "\"response-tone-surprise\"" ":" ws number
        root-response-tone-anger ::= "\"response-tone-anger\"" ":" ws number
        root-response-tone-other ::= "\"response-tone-other\"" ":" ws number
        root-response-tone-neutral ::= "\"response-tone-neutral\"" ":" ws number

        string ::=
        "\"" (
            [^"\\] |
            "\\" (["\\/bfnrt] | "u" [0-9a-fA-F] [0-9a-fA-F] [0-9a-fA-F] [0-9a-fA-F]) # escapes
        )* "\"" ws

        number ::= ("-"? ([0-9] | [1-9] [0-9]*)) ("." [0-9]+)? ([eE] [-+]? [0-9]+)? ws

        # Optional space: by convention, applied in this grammar after literal chars when allowed
        ws ::= ([ \t\n] ws)?
        EOD;

        // replace the mood and action templates with the strings built earlier
        $GLOBALS["grammar"]=str_replace('{$ZONOS}', $zonos_tones_str, $GLOBALS["grammar"]);
        $GLOBALS["grammar"]=str_replace('{$MOODS}', $moods_str, $GLOBALS["grammar"]);
        $GLOBALS["grammar"]=str_replace('{$ACTIONS}', $actions_str, $GLOBALS["grammar"]);
    }

    Function zonosIsActive() {
        return $GLOBALS["TTSFUNCTION"] == "zonos_gradio" && isset($GLOBALS["TTS"]["ZONOS_GRADIO"]["dynamic_tones"]) && $GLOBALS["TTS"]["ZONOS_GRADIO"]["dynamic_tones"];
    }

?>
