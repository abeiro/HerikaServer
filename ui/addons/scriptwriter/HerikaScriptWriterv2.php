<?php

error_reporting(E_ALL);

$enginePath =__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

require_once($enginePath . "conf/conf.php");
require_once($enginePath . "lib/$DBDRIVER.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($enginePath . "prompts" .DIRECTORY_SEPARATOR."command_prompt.php");    // OpenAI complains


if ((!$argv[2])||(!$argv[1] )) {
 die("\nUse. ".basename(__FILE__)." time_multiplier scriptdata.json\n".PHP_EOL);
}

$db=new sql();

$r=$db->fetchAll("select max(gamets)  as gamets from eventlog");

$latsts=$r[0]["gamets"]+1;
$dir=__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

$DEBUG_DATA=[];

$timelineCorrector=(isset($argv[1])?$argv[1]:1);

echo "\nUsing time corrector: $timelineCorrector.\n".PHP_EOL;


$cuelines=json_decode(file_get_contents($argv[2]),true) or die("Error opening {$argv[2]}".PHP_EOL);
$GLOBALS["TTS"]["XVASYNTH"]["DEVENV"]=true;
$GLOBALS["OPENAI_FILTER_DISABLED"]=true;    // To avoid get content filtered by ourselves.
//$GLOBALS["AVOID_TTS_CACHE"]="false";
/*    
 {
    "type": "line", // line or ctrl
    "data": ["", "", "", "IdleDialogueWelcomeHandGesture"] // Subtitle, Expression (*pending),Action,Animation
  },
  
  {
    "type": "line",
    "data": ["Herika: (angry) Lydia! that is my sweetroll!", "anger","Attack@Lydia",""]
  }
*/

/*
$cuelines=[
        [
        "type"=>"line",
        "data"=> ["Herika: What a fucking nasty weather", "", "", ""]
        ]
];

*/

$startTime=time();

foreach ($cuelines as $sline) {
     
    if ($sline["type"]=="line") { 
        $line=$sline["data"];
        unset($GLOBALS["staticMood"]);  // Because returnLines will cache first mood.
        
        if (isset($line[0])) {
            if (isset($line[4])) {
                $GLOBALS["TTS"]["FORCED_LANG_DEV"]=$line[4];
            }
            if (isset($line[5])) {
                $GLOBALS["TTS"]["FORCED_VOICE_DEV"]=$line[5];
            }
            returnLines([$line[0]],false);
        }
        
        
        if (@isset($GLOBALS["DEBUG"]["BUFFER"])) {
            foreach ($GLOBALS["DEBUG"]["BUFFER"] as $lineb) {
                $newformat=explode("|",$lineb);
                $output[]=["Herika","ScriptQueue",trim($newformat[2]),$line[1],$line[2],$line[3]];
            }
        } else {
            
            $output[]=["Herika","ScriptQueue","",$line[1],$line[2],$line[3]];
        }
        
        
        foreach ($output as $lineDB) {
                $lastFourElements = array_slice($lineDB, -4);
                
                $db->insert(
                    'responselog',
                    array(
                        'localts' => time(),
                        'sent' => 0,
                        'actor' => $lineDB[0],
                        'text' => implode("/",$lastFourElements),
                        'action' => $lineDB[1],
                        'tag' => ''
                    )
                );

                $difference = time()-$startTime;

                // Calculate hours, minutes, and seconds
                $hours = floor($difference / 3600);
                $minutes = floor(($difference % 3600) / 60);
                $seconds = $difference % 60;

                // Format the difference in HH:MM:SS
                $timeDifference = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

                echo $timeDifference." ".implode("|",$lineDB).PHP_EOL;
        }
        
        
        unset($GLOBALS["DEBUG"]["BUFFER"]);
        unset($output);
    
        
    } else if ($sline["type"]=="ctrl") { 
        $cmd=$sline["data"];
        if ($cmd[0]=="pause") {
                sleep($cmd[1]);
        }
    }
}
