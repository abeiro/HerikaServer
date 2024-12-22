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
    requireFilesRecursively("..".DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR,"json_response_custom.php");

    // specify the available actions which will be made available in the context
    Function setActions() {
        if (isset($GLOBALS["FUNCTIONS_ARE_ENABLED"]) && $GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
            //$GLOBALS["COMMAND_PROMPT"].=$GLOBALS["COMMAND_PROMPT_FUNCTIONS"];
            foreach ($GLOBALS["FUNCTIONS"] as $function) {
                //$data["tools"][]=["type"=>"function","function"=>$function];
                $GLOBALS["FUNC_LIST"][]=$function["name"];
                if ($function["name"]==$GLOBALS["F_NAMES"]["Attack"]) {
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
                    "listener"=>"specify who {$GLOBALS["HERIKA_NAME"]} is talking to",
                    "message"=>"lines of dialogue",
                    "mood"=>implode("|",$moods),
                    "action"=>implode("|",$GLOBALS["FUNC_LIST"]),
                    "target"=>"action's target|destination name",
                    "lang"=>"en|es",
                ];
            } else {
                $GLOBALS["responseTemplate"] = [
                    "character"=>$GLOBALS["HERIKA_NAME"],
                    "listener"=>"specify who {$GLOBALS["HERIKA_NAME"]} is talking to",
                    "message"=>"lines of dialogue",
                    "mood"=>implode("|",$moods),
                    "action"=>implode("|",$GLOBALS["FUNC_LIST"]),
                    "target"=>"action's target|destination name",
                ];
            }
        } else {
            if (isset($GLOBALS["LANG_LLM_XTTS"])&&($GLOBALS["LANG_LLM_XTTS"])) {
                $GLOBALS["responseTemplate"] = [
                    "character"=>$GLOBALS["HERIKA_NAME"],
                    "listener"=>"specify who {$GLOBALS["HERIKA_NAME"]} is talking to",
                    "mood"=>implode("|",$moods),
                    "action"=>implode("|",$GLOBALS["FUNC_LIST"]),
                    "target"=>"action's target",
                    "lang"=>"en|es",
                    "message"=>"action's target|destination name",
                ];
            } else {
                $GLOBALS["responseTemplate"] = [
                    "character"=>$GLOBALS["HERIKA_NAME"],
                    "listener"=>"specify who {$GLOBALS["HERIKA_NAME"]} is talking to",
                    "mood"=>implode("|",$moods),
                    "action"=>implode("|",$GLOBALS["FUNC_LIST"]),
                    "target"=>"action's target",
                    "message"=>"action's target|destination name",
                ];
            }
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
                            "description" => "specify who {$GLOBALS["HERIKA_NAME"]} is talking to"
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
                            "description" => "action's target|destination name"
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

        // using a quoted heredoc to avoid having to escape everything
        $GLOBALS["grammar"] = <<<'EOD'
        root ::= "{" ws root-character "," ws root-listener "," ws root-message "," ws root-mood "," ws root-action "," ws root-target "}" ws
        root-character ::= "\"character\"" ":" ws string
        root-listener ::= "\"listener\"" ":" ws string
        root-message ::= "\"message\"" ":" ws string
        root-mood ::= "\"mood\"" ":" ws {$MOODS}
        root-action ::= "\"action\"" ":" ws {$ACTIONS}
        root-target ::= "\"target\"" ":" ws string

        string ::=
        "\"" (
            [^"\\] |
            "\\" (["\\/bfnrt] | "u" [0-9a-fA-F] [0-9a-fA-F] [0-9a-fA-F] [0-9a-fA-F]) # escapes
        )* "\"" ws

        # Optional space: by convention, applied in this grammar after literal chars when allowed
        ws ::= ([ \t\n] ws)?
        EOD;

        // replace the mood and action templates with the strings built earlier
        $GLOBALS["grammar"]=str_replace('{$MOODS}', $moods_str, $GLOBALS["grammar"]);
        $GLOBALS["grammar"]=str_replace('{$ACTIONS}', $actions_str, $GLOBALS["grammar"]);
    }

?>
