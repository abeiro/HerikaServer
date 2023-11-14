<?php

 $GLOBALS["more_stopseq"][]="<|im_start|>";
            $context="<|im_start|>system\n{$GLOBALS["PROMPT_HEAD"]}\n";
            $context.="{$GLOBALS["HERIKA_PERS"]}\n";
            $context.="{$GLOBALS["COMMAND_PROMPT"]}\n";

            $GLOBALS["DEBUG_DATA"][]=$context;

            $contextHistory="";
            $n=0;
            foreach ($contextData as $s_role=>$s_msg) {	// Have to mangle context format

                if ($n==(sizeof($contextData)-1)) {   // Last prompt line

                    $instruction="<|im_end|>\n<|im_start|>user\n".$s_msg["content"]."<|im_end|>\n";

                } else {
                    if ($s_msg["role"]=="user") {
                        $contextHistory.=$s_msg["content"]."\n";
                          $GLOBALS["DEBUG_DATA"][]=$s_msg["content"];
                    } elseif ($s_msg["role"]=="assistant") {
                         $GLOBALS["DEBUG_DATA"][]=$s_msg["content"];
                        $contextHistory.=$s_msg["content"]."\n";
                    } elseif ($s_msg["role"]=="system") {
                    }  // Must rebuild this


                }

                $n++;
            }

            $context.="$contextHistory  $instruction <|im_start|>assistant\n";
            $GLOBALS["DEBUG_DATA"][]="$instruction";
            $GLOBALS["DEBUG_DATA"]["prompt"]=$context;
            
            ?>
