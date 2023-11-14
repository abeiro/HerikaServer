<?php

$context="{$GLOBALS["HERIKA_NAME"]}'s Persona: {$GLOBALS["HERIKA_PERS"]}\n";
            $context.="{$GLOBALS["PLAYER_NAME"]}'s Persona: {$GLOBALS["PLAYER_NAME"]}\n";
            $context.="Scenario: {$GLOBALS["PROMPT_HEAD"]} \n";
            $context.="Play the role of {$GLOBALS["HERIKA_NAME"]}. You must engage in a roleplaying chat with {$GLOBALS["PLAYER_NAME"]} below this line.Do not write dialogues for {$GLOBALS["PLAYER_NAME"]} and don't write narration.\n";
            $context.="{$GLOBALS["COMMAND_PROMPT"]}\n";

            $contextHistory="";
            $n=0;
            foreach ($contextData as $s_role=>$s_msg) {	// Have to mangle context format

                if ($n==(sizeof($contextData)-1)) {   // Last prompt line
                    $instruction="### Input:\n".$s_msg["content"]."\n";
                    $GLOBALS["DEBUG_DATA"][]=$instruction;

                } else {
                    if ($s_msg["role"]=="user") {
                        $contextHistory.="### Input:\n".$s_msg["content"];
                        $GLOBALS["DEBUG_DATA"][]="### Input:\n".$s_msg["content"];
                    } elseif ($s_msg["role"]=="assistant") {
                        $contextHistory.="### Response:\n".$s_msg["content"];
                        $GLOBALS["DEBUG_DATA"][]="### Response:\n".$s_msg["content"];

                    } elseif ($s_msg["role"]=="system") {
                    }  // Must rebuild this
                }

                $n++;
            }

            $context.="$contextHistory $instruction ### Response\n{$GLOBALS["HERIKA_NAME"]}:";
            $GLOBALS["DEBUG_DATA"][]="ASSISTANT:";
            $GLOBALS["DEBUG_DATA"]["prompt"]=$context;
            
            ?>
