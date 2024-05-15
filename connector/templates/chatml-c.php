<?php

$GLOBALS["more_stopseq"][]="<|im_start|>";
            $context="<|im_start|>system\n{$GLOBALS["PROMPT_HEAD"]}\n";
            $context.="{$GLOBALS["HERIKA_PERS"]}\n";
            $context.="{$GLOBALS["COMMAND_PROMPT"]}<|im_end|>\n";

            $GLOBALS["DEBUG_DATA"][]=$context;

            $contextHistory="";
            $n=0;
            foreach ($contextData as $s_role=>$s_msg) {	// Have to mangle context format

                if ($n==(sizeof($contextData)-2)) {   // Last prompt line

                    $instruction="<|im_start|>user\n".$s_msg["content"];

                }else if ($n==(sizeof($contextData)-1)) {   // Last prompt line

                    $instruction.=$s_msg["content"]."<|im_end|>\n";

                }  else {
                    if ($s_msg["role"]=="user") {
                        $contextHistory.="<|im_start|>user\n".$s_msg["content"]."<|im_end|>\n";
                          $GLOBALS["DEBUG_DATA"][]="<|im_start|>user\n".$s_msg["content"]."<|im_end|>\n";
                    } elseif ($s_msg["role"]=="assistant") {
                         $GLOBALS["DEBUG_DATA"][]="<|im_start|>assistant\n".$s_msg["content"]."<|im_end|>\n";
                        $contextHistory."<|im_start|>assistant\n".$s_msg["content"]."<|im_end|>\n";
                    } elseif ($s_msg["role"]=="system") {
                    }  // Must rebuild this


                }

                $n++;
            }

            $context.="$contextHistory  $instruction <|im_start|>assistant";
            $GLOBALS["DEBUG_DATA"][]="$instruction";
            $GLOBALS["DEBUG_DATA"]["prompt"]=$context;
            
            ?>
