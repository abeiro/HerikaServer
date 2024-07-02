<?php

            
            $context="<start_of_turn>user\n{$GLOBALS["PROMPT_HEAD"]}\n";
            $context.=strtr($GLOBALS["HERIKA_PERS"],["#PLAYER_NAME#"=>$GLOBALS["PLAYER_NAME"]]);
            $context.="{$GLOBALS["COMMAND_PROMPT"]}\n#CONTEXT\n";
            $context.="<end_of_turn><start_of_turn>model\nOk, i will roleplay as {$GLOBALS["HERIKA_NAME"]}<end_of_turn><start_of_turn>user\n";
            $GLOBALS["DEBUG_DATA"][]=$context;

            $contextHistory="";
            $n=0;
            foreach ($contextData as $s_role=>$s_msg) {	// Have to mangle context format

                if ($n==(sizeof($contextData)-1)) {   // Last prompt line

                    $instruction.=$s_msg["content"]."<end_of_turn>\n";

                } else if ($n==(sizeof($contextData)-2)) {   // Last prompt line

                    $instruction="<end_of_turn>\n<start_of_turn>user\n".$s_msg["content"];

                }  else {
                        if ($s_msg["role"]=="user") {
                            $contextHistory.=$s_msg["content"]."\n";
                            $GLOBALS["DEBUG_DATA"][]=$s_msg["content"];
                    
                            
                        } else if ($s_msg["role"]=="assistant") {
                
                            if (isset($s_msg["tool_calls"])) {
                                    $pb["system"].="{$GLOBALS["HERIKA_NAME"]} issued ACTION {$s_msg["tool_calls"][0]["function"]["name"]}";
                                    $lastAction="{$GLOBALS["HERIKA_NAME"]} issued ACTION {$s_msg["tool_calls"][0]["function"]["name"]} {$s_msg["tool_calls"][0]["function"]["arguments"]}";
                                    
                                    $localFuncCodeName=getFunctionCodeName($s_msg["tool_calls"][0]["function"]["name"]);
                                    $localArguments=json_decode($s_msg["tool_calls"][0]["function"]["arguments"],true);
                                    $lastAction=strtr($GLOBALS["F_RETURNMESSAGES"][$localFuncCodeName],[
                                                    "#TARGET#"=>current($localArguments),
                                                    ]);
                                    
                                    
                                } else {
                                    $GLOBALS["DEBUG_DATA"][]=$s_msg["content"];
                                    //$contextHistory.=$s_msg["content"]."\n";
                                    
                                    $dialogueTarget=extractDialogueTarget($s_msg["content"]);
                                    // Trying to provide examples
                                    $contextHistory.="<end_of_turn>\n<start_of_turn>model\n{\"character\": \"{$GLOBALS["HERIKA_NAME"]}\", \"listener\": \"{$dialogueTarget["target"]}\", \"mood\": \"\", \"action\": \"\", 
                                                        \"target\": \"\", \"message\": \"".trim($dialogueTarget["cleanedString"])."\"}<end_of_turn>\n <start_of_turn>user\n";
                                }
                    
                    } else if ($s_msg["role"]=="tool") {
                        $pb["system"].=$element["content"]."\n";
                        $contextHistory.="The Narrator:".strtr($lastAction,["#RESULT#"=>$s_msg["content"]]);
                        $GLOBALS["PATCH_STORE_FUNC_RES"]=strtr($lastAction,["#RESULT#"=>$s_msg["content"]]);
                        
                    } else if ($s_msg["role"]=="system") {
                        }  // Must rebuild this


                }

                $n++;
            }

            $context.="$contextHistory  $instruction <start_of_turn>model\n";
            $GLOBALS["DEBUG_DATA"][]="$instruction";
            $GLOBALS["DEBUG_DATA"]["prompt"]=$context;
            
            ?>
