<?php

/*
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
            
*/            


$context="{$GLOBALS["PROMPT_HEAD"]}\n";
$context.="{$GLOBALS["HERIKA_PERS"]}\n";
$context.="{$GLOBALS["COMMAND_PROMPT"]}\n";

$context.="#EXAMPLE
{$GLOBALS["PLAYER_NAME"]}: What is Trancos wearing?
{
  \"character\": \"{$GLOBALS["HERIKA_NAME"]}\",
  \"listener\": \"{$GLOBALS["PLAYER_NAME"]}\",
  \"mood\": \"sardonic\",
  \"action\": \"Inspect\",
  \"target\": \"Trancos\",
  \"message\": \"Let me check \"
}
";

$context.="\n#CONTEXT\n";

$GLOBALS["DEBUG_DATA"][]=$context;
$contextHistory="";
$n=0;

foreach ($contextData as $s_role=>$s_msg) {	// Have to mangle context format

    if ($n==(sizeof($contextData)-1)) {   // Last prompt line

        $instruction.=$s_msg["content"]."";

    } else if ($n==(sizeof($contextData)-2)) {   // Last prompt line

        $instruction="\n### Instruction:\n".$s_msg["content"];

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
                    $contextHistory.="{\"character\": \"{$GLOBALS["HERIKA_NAME"]}\", \"listener\": \"{$dialogueTarget["target"]}\", \"mood\": \"\", \"action\": \"\", 
                                        \"target\": \"\", \"message\": \"".trim($dialogueTarget["cleanedString"])."\"}";
                }
                
        } else if ($s_msg["role"]=="tool") {
            $pb["system"].=$element["content"]."\n";
            $contextHistory.="The Narrator:".strtr($lastAction,["#RESULT#"=>$s_msg["content"]]);
            $GLOBALS["PATCH_STORE_FUNC_RES"]=strtr($lastAction,["#RESULT#"=>$s_msg["content"]]);
            
        } elseif ($s_msg["role"]=="system") {
        
            
        }  // Must rebuild this


    }

    $n++;
}

$context.="$contextHistory  $instruction \n### Response:";
$GLOBALS["DEBUG_DATA"][]="$instruction";
$GLOBALS["DEBUG_DATA"]["prompt"]=$context;

?>
