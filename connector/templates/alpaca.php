<?php

foreach ($normalizedContext as $n=>$s_msg) {
                if ($n==(sizeof($normalizedContext)-1)) {   // Last prompt line
                    $context.="### Instruction: ".$s_msg.". Write a single reply only.\n";
                    $GLOBALS["DEBUG_DATA"][]="### Instruction: ".$s_msg."";

                } else {
                    $s_msg_p = preg_replace('/^(The Narrator:)(.*)/m', '[Author\'s notes: $2 ]', $s_msg);
                    $context.="$s_msg_p\n";
                    $GLOBALS["DEBUG_DATA"][]=$s_msg_p;
                }

            }

            $context.="### Response:";
            $GLOBALS["DEBUG_DATA"][]="### Response:";
            $GLOBALS["DEBUG_DATA"]["prompt"]=$context;
            
            
            ?>
