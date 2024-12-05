<?php
/*

Post tasks.

*/

if ($GLOBALS["MINIME_T5"]) {
    if (isset($FEATURES["MISC"]["OGHMA_INFINITUM"])&&($FEATURES["MISC"]["OGHMA_INFINITUM"])) {
        if (in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s"])) {

            
            //$TEST_TEXT=lastSpeech($GLOBALS["HERIKA_NAME"]);
            //$TEST_TEXT="{$GLOBALS["HERIKA_NAME"]}:".implode(" ",$GLOBALS["talkedSoFar"]);
            $TEST_TEXT=implode(" ",$GLOBALS["talkedSoFar"]);

            $topic=json_decode(file_get_contents("http://127.0.0.1:8082/posttopic?text=".urlencode($TEST_TEXT)),true);
            if (is_array($topic) && isset($topic["generated_tags"])) {
                error_log("[OGMHA] Current Topic: {$topic["generated_tags"]}");
                $db->delete("conf_opts", "id='current_oghma_topic'");
                $db->insert(
                'conf_opts',
                    array(
                            'id' =>'current_oghma_topic',
                            'value' => $topic["generated_tags"]
                        )
                    );
            }

        }
    }
}


$configFilepath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."conf".DIRECTORY_SEPARATOR;
$GLOBALS["PROFILES"]["default"]="$configFilepath/conf.php";
foreach (glob($configFilepath . 'conf_????????????????????????????????.php') as $mconf ) {
    if (file_exists($mconf)) {
        $filename=basename($mconf);
        $pattern = '/conf_([a-f0-9]+)\.php/';
        preg_match($pattern, $filename, $matches);
        $hash = $matches[1];
        $GLOBALS["PROFILES"][$hash]=$mconf;
    }
}

require("$configFilepath/conf.php");

if ($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["AUTO_CREATE_SUMMARYS"]) {
    $results = $db->fetchAll("select max(gamets_truncated) as gamets_truncated from memory_summary");

    $maxRow=$results[0]["gamets_truncated"]+0;
    
    $pfi=($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["AUTO_CREATE_SUMMARY_INTERVAL"]+0)*100000;
    

    if (($gameRequest[2]-$maxRow)>($pfi)) {
        
        error_log(shell_exec("php ".__DIR__."/../debug/util_memory_subsystem.php compact noembed 2"));
        
    } else {
        
        
       

    }

}


if ($GLOBALS["MINIME_T5"]) {
    if (in_array($gameRequest[0],["inputtext","inputtext_s","ginputtext","ginputtext_s"])) {

        $pattern = "/\([^)]*Context location[^)]*\)/"; // Remove (Context location..
        $replacement = "";
        $TEST_TEXT = preg_replace($pattern, $replacement, $gameRequest[3]); // // assistant vs user war
        $pattern = '/\(talking to [^()]+\)/i';
        $TEST_TEXT = preg_replace($pattern, '', $TEST_TEXT);
            
        $command=json_decode(file_get_contents("http://127.0.0.1:8082/task?text=".urlencode($TEST_TEXT)),true);
        if (isset($command["is_command"])) {
            $prCmd=explode("@",$command["is_command"]);
            if ($prCmd[0]=="SetCurrentTask") {
                $db->insert(
                    'currentmission',
                    array(
                        'ts' => $gameRequest[1],
                        'gamets' => $gameRequest[2],
                        'description' => $prCmd[1],
                        'sess' => 'pending',
                        'localts' => time()
                    )
                );
                $db->insert(
                    'audit_memory',
                    array(
                        'input' => $TEST_TEXT,
                        'keywords' =>'auto added task',
                        'rank_any'=> -1,
                        'rank_all'=>-1,
                        'memory'=>$command["is_command"],
                        'time'=>$command["elapsed_time"]
                    )
                );
            }
        }
    }
}


