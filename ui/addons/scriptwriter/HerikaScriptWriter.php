<?php

error_reporting(E_ALL);

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($enginePath . "conf/conf.php");
require_once($enginePath . "lib/$DBDRIVER.class.php");
require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php"); // API KEY must be there
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");


if ((!$argv[2])||(!$argv[1] )) {
 die("\nUse. ".basename(__FILE__)." time_multiplier script.json\n".PHP_EOL);
}

$db=new sql();

$r=$db->fetchAll("select max(gamets)  as gamets from eventlog");

$latsts=$r[0]["gamets"]+1;
$dir=__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

$DEBUG_DATA=[];

$timelineCorrector=(isset($argv[1])?$argv[1]:1);

echo "\nUsing time corrector: $timelineCorrector.\n".PHP_EOL;


$cuelines=json_decode(file_get_contents($argv[2])) or die("Error opening {$argv[2]}".PHP_EOL);


foreach ($cuelines as $line) {
     $size=0;
     
    unset($GLOBALS["staticMood"]);  // Because returnLines will cache first mood.
    if (strpos($line, "*")===0) {
        // Issue animation
        // Now will wait 
      
        $GLOBALS["DEBUG"]["BUFFER"][]="Herika|animation|".strtr($line, ["*"=>""]);
        echo "Herika|animation|".strtr($line, ["*"=>""]).PHP_EOL;
       
        if (isset($GLOBALS["TRACK"]["FILES_GENERATED"])) {
            foreach ($GLOBALS["TRACK"]["FILES_GENERATED"] as $file) {
                    $size+=filesize($file);
            }
            unset($GLOBALS["TRACK"]["FILES_GENERATED"]);
        }
        
         if ($size>0) {
            $wait=$size/(5512.5*9)*$timelineCorrector; // Microsoft PCM, 16 bit, mono 22050 Hz
            usleep(round($wait * 1000000,0));
            //die("Size: $size");
        }
        

    } else if (strpos($line, "!")===0) {
        // Issue animation
        // Now will wait 
      
        $GLOBALS["DEBUG"]["BUFFER"][]="Herika|command|".strtr($line, ["!"=>""]);
        echo "Herika|command|".strtr($line, ["!"=>""]);
       
        if (isset($GLOBALS["TRACK"]["FILES_GENERATED"])) {
            foreach ($GLOBALS["TRACK"]["FILES_GENERATED"] as $file) {
                    $size+=filesize($file);
            }
            unset($GLOBALS["TRACK"]["FILES_GENERATED"]);
        }
        
         if ($size>0) {
            $wait=$size/(5512.5*9)*$timelineCorrector; // Microsoft PCM, 16 bit, mono 22050 Hz
            usleep(round($wait * 1000000,0));
            //die("Size: $size");
        }
        

    } elseif (strpos($line, "#Pause")===0) {
        // Issue animation
        sleep(strtr($line, ["#Pause "=>""])+0);
        unset($GLOBALS["TRACK"]["FILES_GENERATED"]);
        continue;

    } else {
        returnLines([$line]);
    }
    
    $lineb=$GLOBALS["DEBUG"]["BUFFER"];
    
    foreach ($lineb as $lineDB) {
        $cline=explode("|", $lineDB);
        $db->insert(
            'responselog',
            array(
                'localts' => time(),
                'sent' => 0,
                'actor' => $cline[0],
                'text' => $cline[2],
                'action' => $cline[1],
                'tag' => ''
            )
        );
    }
    //print_r($lineb);
    unset($GLOBALS["DEBUG"]["BUFFER"]);
     
    
}
