<?php
 //$GLOBALS["more_stopseq"][]="USER:";
            $context="GPT4 User: {$GLOBALS["PROMPT_HEAD"]}\n";
            $context.="{$GLOBALS["HERIKA_PERS"]}\n";
            $context.="{$GLOBALS["COMMAND_PROMPT"]}\n";
            //$context.="{$GLOBALS["HERIKA_NAME"]} IS THE ASSISTANT, {$GLOBALS["PLAYER_NAME"]} IS THE USER\n";


            $GLOBALS["DEBUG_DATA"][]=$context;

            $contextHistory="";
            $n=0;
            foreach ($contextData as $s_role=>$s_msg) {	// Have to mangle context format

                if ($n==(sizeof($contextData)-1)) {   // Last prompt line

                    $instruction="".$s_msg["content"]."<|end_of_turn|>\n";
                    $GLOBALS["DEBUG_DATA"][]=$instruction;
                } else {
                    if ($s_msg["role"]=="user") {
                        $contextHistory.="".$s_msg["content"]."\n";
                          $GLOBALS["DEBUG_DATA"][]="".$s_msg["content"]."\n";
                    } elseif ($s_msg["role"]=="assistant") {
                         $contextHistory.="".$s_msg["content"]."\n";
                         $GLOBALS["DEBUG_DATA"][]="".$s_msg["content"]."\n";
                        
                    } elseif ($s_msg["role"]=="system") {
                    }  // Must rebuild this


                }

                $n++;
            }

            $context.="$contextHistory  $instruction GPT4 Assistant:";
            $GLOBALS["DEBUG_DATA"][]=" GPT4 Assistant:";
            $GLOBALS["DEBUG_DATA"]["prompt"]=$context;
            
            ?>
