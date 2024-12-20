<?php

    setResponseTemplate($contextData);
    setStructuredOutputTemplate();
    requireFilesRecursively("..".DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR,"json_response_custom.php");

    Function setResponseTemplate($contextData) {
        $action_array=[];
        $action_array[]="Talk";
        $FUNC_LIST[]="Talk";
        if (isset($GLOBALS["FUNCTIONS_ARE_ENABLED"]) && $GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
            $contextData[0]["content"].="\nAVAILABLE ACTION: Talk";
            foreach ($GLOBALS["FUNCTIONS"] as $function) {
                //$data["tools"][]=["type"=>"function","function"=>$function];
                $action_array[]=$function["name"];
                if (strpos($function["name"],"Attack")!==false) {   // Every command starting with Attack
                    $contextData[0]["content"].="\nAVAILABLE ACTION: {$function["name"]} : {$function["description"]}";
                    $contextData[0]["content"].="(available targets: ".implode(",",$GLOBALS["FUNCTION_PARM_INSPECT"]).")";
                } /*else if ($function["name"]==$GLOBALS["F_NAMES"]["SetSpeed"]) {
                    $contextData[0]["content"].="\nAVAILABLE ACTION: {$function["name"]}(available speeds: run|fastwalk|jog|walk) ";
                    $contextData[0]["content"].="({$function["description"]})";
                }*/  else if ($function["name"]==$GLOBALS["F_NAMES"]["SearchMemory"]) {
                    $contextData[0]["content"].="\nAVAILABLE ACTION: {$function["name"]} : {$function["description"]})";
                    
                } else
                    $contextData[0]["content"].="\nAVAILABLE ACTION: {$function["name"]} : {$function["description"]}";
                
                $FUNC_LIST[]=$function["name"];
            }
        }
    
        if (isset($GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]) && $GLOBALS["PATCH_PROMPT_ENFORCE_ACTIONS"]) {
        $prefix="{$GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]}";
        }
        $prefix="{$GLOBALS["COMMAND_PROMPT_ENFORCE_ACTIONS"]}";
    
        //$FUNC_LIST[]="None";
        shuffle($FUNC_LIST);
    
        $moods=explode(",",$GLOBALS["EMOTEMOODS"]);
        shuffle($moods);
    
        $formatJsonTemplate = [];
    
        if (isset($GLOBALS["FEATURES"]["MISC"]["JSON_DIALOGUE_FORMAT_REORDER"])&&($GLOBALS["FEATURES"]["MISC"]["JSON_DIALOGUE_FORMAT_REORDER"])) {
            if (isset($GLOBALS["LANG_LLM_XTTS"])&&($GLOBALS["LANG_LLM_XTTS"])) {
                $GLOBALS["responseTemplate"] = [
                    "character"=>$GLOBALS["HERIKA_NAME"],
                    "listener"=>"specify who {$GLOBALS["HERIKA_NAME"]} is talking to",
                    "message"=>'dialogues lines',
                    "mood"=>implode("|",$moods),
                    "action"=>implode("|",$FUNC_LIST),
                    "target"=>"action's target|destination name",
                    "lang"=>"en|es",
                ];
            } else {
                $GLOBALS["responseTemplate"] = [
                    "character"=>$GLOBALS["HERIKA_NAME"],
                    "listener"=>"specify who {$GLOBALS["HERIKA_NAME"]} is talking to",
                    "message"=>'dialogues lines',
                    "mood"=>implode("|",$moods),
                    "action"=>implode("|",$FUNC_LIST),
                    "target"=>"action's target|destination name",
                ];
            }
        } else {
            if (isset($GLOBALS["LANG_LLM_XTTS"])&&($GLOBALS["LANG_LLM_XTTS"])) {
                $GLOBALS["responseTemplate"] = [
                    "character"=>$GLOBALS["HERIKA_NAME"],
                    "listener"=>"specify who {$GLOBALS["HERIKA_NAME"]} is talking to",
                    "mood"=>implode("|",$moods),
                    "action"=>implode("|",$FUNC_LIST),
                    "target"=>"action's target|destination name",
                    "lang"=>"en|es",
                    "message"=>'dialogues lines',
                ];
            } else {
                $GLOBALS["responseTemplate"] = [
                    "character"=>$GLOBALS["HERIKA_NAME"],
                    "listener"=>"specify who {$GLOBALS["HERIKA_NAME"]} is talking to",
                    "mood"=>implode("|",$moods),
                    "action"=>implode("|",$FUNC_LIST),
                    "target"=>"action's target|destination name",
                    "message"=>'dialogues lines',
                ];
            }
        }
    }
    
    Function setStructuredOutputTemplate() {
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
                            "description" => "lines of dialogue"
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
                        "action" => empty($action_array) ? 
                            array(
                                "type" => "string",
                                "description" => "a valid action (refer to available actions list)"
                            ) :
                            array(
                                "type" => "string",
                                "description" => "a valid action (refer to available actions list)",
                                "enum" => $action_array
                            ),
                        "target" => array(
                            "type" => "string",
                            "description" => "action's target"
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
?>
