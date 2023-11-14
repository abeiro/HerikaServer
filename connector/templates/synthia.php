<?php

$context="SYSTEM: {$GLOBALS["PROMPT_HEAD"]}.";
            $context.="{$GLOBALS["HERIKA_PERS"]}\n";
            $context.="{$GLOBALS["COMMAND_PROMPT"]}\nUSER={$GLOBALS["PLAYER_NAME"]}";

            //$context = preg_replace('/^(The Narrator:)(.*)/m', '[Author\'s notes: $2 ]', $context);


            $GLOBALS["DEBUG_DATA"][]=$context;

            $contextHistory="\nSCENARIO:\n";
            $n=0;
            foreach ($contextData as $s_role=>$s_msg) {	// Have to mangle context format

                $s_msg_p=$s_msg["content"];

                if ($n==(sizeof($contextData)-1)) {   // Last prompt line

                    $instruction="USER: ".$s_msg["content"]."\n";

                } else {
                    if ($s_msg["role"]=="user") {

                       // $s_msg_p = preg_replace('/^(The Narrator:)(.*)/m', '[Author\'s notes: $2 ]', $s_msg["content"]);

                        $s_msg_p=$s_msg["content"]; // Overwrite

                        $contextHistory.="$s_msg_p\n";
                        $GLOBALS["DEBUG_DATA"][]="$s_msg_p\n";

                    } elseif ($s_msg["role"]=="assistant") {

                        $contextHistory.="".$s_msg["content"]."\n";
                        $GLOBALS["DEBUG_DATA"][]="".$s_msg["content"]."\n";

                    } elseif ($s_msg["role"]=="system") {
                    }  // Must rebuild this


                }

                $n++;
            }

            $context.="{$contextHistory}{$instruction}ASSISTANT:";
            $GLOBALS["DEBUG_DATA"][]="$instruction";
            $GLOBALS["DEBUG_DATA"][]="ASSISTANT:";

            $GLOBALS["DEBUG_DATA"]["prompt"]=$context;
            
            ?>
