<?php

    global $FUNC_LIST;
    global $responseTemplate;
    global $structuredOutputTemplate;
    global $grammar;
    $FUNC_LIST=[];
    $responseTemplate=[];
    $structuredOutputTemplate=array();
    $grammar = "";

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
        if (isset($GLOBALS["FUNCTIONS_ARE_ENABLED"]) && $GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
            //$GLOBALS["COMMAND_PROMPT"].=$GLOBALS["COMMAND_PROMPT_FUNCTIONS"];
            foreach ($GLOBALS["FUNCTIONS"] as $function) {
                //$data["tools"][]=["type"=>"function","function"=>$function];
                if (!$function)
                    continue;
                $GLOBALS["FUNC_LIST"][]=$function["name"];
                if ($function["name"]==$GLOBALS["F_NAMES"]["Attack"] || $function["name"]==$GLOBALS["F_NAMES"]["Brawl"]) {
                    $GLOBALS["COMMAND_PROMPT"].="\nAVAILABLE ACTION: {$function["name"]} ({$function["description"]})";
                    $GLOBALS["COMMAND_PROMPT"].="(available targets: ".implode(",",$GLOBALS["FUNCTION_PARM_INSPECT"]).")";
                }/* else if ($function["name"]==$GLOBALS["F_NAMES"]["SetSpeed"]) {
                    $GLOBALS["COMMAND_PROMPT"].="\nAVAILABLE ACTION: {$function["name"]} ({$function["description"]})";
                    $GLOBALS["COMMAND_PROMPT"].="(run|fastwalk|jog|walk)";
                }*/  else if ($function["name"]==$GLOBALS["F_NAMES"]["SearchMemory"]) {
                    $GLOBALS["COMMAND_PROMPT"].="\nAVAILABLE ACTION: {$function["name"]}(keywords to search ({$function["description"]})";
                    
                } else {
                    $GLOBALS["COMMAND_PROMPT"].="\nAVAILABLE ACTION: {$function["name"]} ({$function["description"]})";
                }
            }

            $GLOBALS["COMMAND_PROMPT"].="\nAVAILABLE ACTION: Talk";
            $GLOBALS["FUNC_LIST"][]="Talk";
            shuffle($GLOBALS["FUNC_LIST"]);
        }
    }

    // specify the json object that will be requested from the LLM (via prompt, not enforced)
    Function setResponseTemplate() {
        $moods=explode(",",$GLOBALS["EMOTEMOODS"]);
        shuffle($moods);
    
        if (isset($GLOBALS["FEATURES"]["MISC"]["JSON_DIALOGUE_FORMAT_REORDER"])&&($GLOBALS["FEATURES"]["MISC"]["JSON_DIALOGUE_FORMAT_REORDER"])) {
            if (isset($GLOBALS["LANG_LLM_XTTS"])&&($GLOBALS["LANG_LLM_XTTS"])) {
                $GLOBALS["responseTemplate"] = [
                    "character"=>$GLOBALS["HERIKA_NAME"],
                    "listener"=>"specify who {$GLOBALS["HERIKA_NAME"]} is talking to, comma separated, max two listeners, in addressing order",
                    "message"=>"lines of dialogue",
                    "mood"=>implode("|",$moods),
                    "action"=>implode("|",$GLOBALS["FUNC_LIST"]),
                    "target"=>"action target actor|action destination location name",
                    "lang"=>"en|es"
                ];
            } else {
                $GLOBALS["responseTemplate"] = [
                    "character"=>$GLOBALS["HERIKA_NAME"],
                    "listener"=>"specify who {$GLOBALS["HERIKA_NAME"]} is talking to, comma separated, max two listeners, in addressing order",
                    "message"=>"lines of dialogue",
                    "mood"=>implode("|",$moods),
                    "action"=>implode("|",$GLOBALS["FUNC_LIST"]),
                    "target"=>"action target actor|action destination location name "
                ];
            }
        } else {
            if (isset($GLOBALS["LANG_LLM_XTTS"])&&($GLOBALS["LANG_LLM_XTTS"])) {
                $GLOBALS["responseTemplate"] = [
                    "character"=>$GLOBALS["HERIKA_NAME"],
                    "listener"=>"specify who {$GLOBALS["HERIKA_NAME"]} is talking to, comma separated, max two listeners, in addressing order",
                    "mood"=>implode("|",$moods),
                    "action"=>implode("|",$GLOBALS["FUNC_LIST"]),
                    "target"=>"action target actor|action destination location name",
                    "lang"=>"en|es",
                    "message"=>"lines of dialogue"
                ];
            } else {
                $GLOBALS["responseTemplate"] = [
                    "character"=>$GLOBALS["HERIKA_NAME"],
                    "listener"=>"specify who {$GLOBALS["HERIKA_NAME"]} is talking to, comma separated, max two listeners, in addressing order",
                    "mood"=>implode("|",$moods),
                    "action"=>implode("|",$GLOBALS["FUNC_LIST"]),
                    "target"=>"action target actor|action destination location name",
                    "message"=>"lines of dialogue"
                ];
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
        $moods=explode(",",$GLOBALS["EMOTEMOODS"]);
        shuffle($moods);

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
                            "description" => "lines of {$GLOBALS["HERIKA_NAME"]}'s dialogue"
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
                        )
                    ),
                    "required" => [
                        "character",
                        "listener",
                        "message",
                        "mood",
                        "action",
                        "target"
                    ],
                    "additionalProperties" => false
                ),
                "strict" => true
            )
        );

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
        $moods=explode(",",$GLOBALS["EMOTEMOODS"]);
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
