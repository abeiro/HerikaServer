<?php

 $context="---\nstyle: roleplay\n";
            $context.="characters:\n   {$GLOBALS["HERIKA_NAME"]}:{$GLOBALS["HERIKA_PERS"]}\n   {$GLOBALS["PLAYER_NAME"]}:Human\n";
            $context.="summary: {$GLOBALS["PROMPT_HEAD"]} \n---\n";
            $context.="{$GLOBALS["COMMAND_PROMPT"]}\n";
            $contextHistory="";
            $n=0;
            foreach ($contextData as $s_role=>$s_msg) {	// Have to mangle context format

                if ($n==(sizeof($contextData)-1)) {   // Last prompt line

                    $instruction=$s_msg["content"];


                } else {
                    if ($s_msg["role"]=="user") {
                        $contextHistory.=$s_msg["content"]."\n";
                    } elseif ($s_msg["role"]=="assistant") {
                        $contextHistory.=$s_msg["content"]."\n";
                    } elseif ($s_msg["role"]=="system") {
                    }  // Must rebuild this
                }

                $GLOBALS["DEBUG_DATA"][]=$s_msg["content"];

                $n++;
            }

            $context.="$contextHistory Human: $instruction\n{$GLOBALS["HERIKA_NAME"]}:";

            $GLOBALS["DEBUG_DATA"]["prompt"]=$context;
            
            ?>
