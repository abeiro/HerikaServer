<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
$file = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR.'CurrentModel.json';
$enginePath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;



$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php");

$GLOBALS["DBDRIVER"]="postgresql";


require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."memory_helper_vectordb_txtai.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");

$GREEN="\033[0;32m";
$YELLOW="\033[1;33m";
$RED="\033[1;31m";
$NC="\033[0m"; # No Color

if (false) {
    $db = new sql();
    $results = $db->fetchAll(" SELECT * FROM eventlog where type like '%input%' and data like 'Volk%' and data like '%give%'");
    $counter=0;
   
    $results[]=["data"=>"Volkur:Hey Vayne . . let me give you a drink"];
    $results[]=["data"=>"Volkur:Hey Vayne . . inspect area"];
    $results[]=["data"=>"Volkur:Cheers ladies!"];
        
    foreach ($results as $row) {

        $TEST_TEXT=$row["data"];
        $pattern = '/\(talking to [^()]+\)/i';
        $TEST_TEXT = preg_replace($pattern, '', $TEST_TEXT);

        $command=file_get_contents("http://127.0.0.1:8082/command?text=".urlencode($TEST_TEXT));
        $preCommand=json_decode($command,true);
        
        if ($preCommand["is_command"]=="ExchangeItems")
            echo $GREEN;
        else
            echo $NC;

        echo(implode("|",$preCommand));
            
        echo PHP_EOL;
    }
}

if (true) {
    
    $db = new sql();
    $results = $db->fetchAll("select data from eventlog where type like '%inputtext%'  order by gamets desc limit 50 offset 0");
    $counter=0;
        
    $results[]=["data"=>"Do you remember when we killed Melka?"];
    $results[]=["data"=>"Do you remember the name of the cave where we killed Melka?"];
    $results[]=["data"=>"Lydia and me agreed a salary some time ago...do you remember the amount?"];
    $results[]=["data"=>"I forgot our secret code. Can you remind me?"];
    $results[]=["data"=>"I remember when we fought that some conjurors in a cave"];
    $results[]=["data"=>"Let's go to the nearest inn"];
        
    foreach ($results as $row) {

        $TEST_TEXT=$row["data"];
        $pattern = "/\([^)]*Context location[^)]*\)/"; // Remove (Context location..
        $replacement = "";
        $TEST_TEXT = preg_replace($pattern, $replacement,$TEST_TEXT); 
        
        $pattern = '/\(talking to [^()]+\)/i';
        $TEST_TEXT=preg_replace($pattern, "",$TEST_TEXT); 

        
        $TEST_TEXT=strtr($TEST_TEXT,["..."=>". ",".."=>". "]);
        $res=DataSearchMemory($TEST_TEXT,'',"");

        
        if (is_array($res) && is_array($res[0])) {
            echo "MEMORY: $TEST_TEXT".PHP_EOL;
            
            
        } else
            echo "NO MEMORY: $TEST_TEXT".PHP_EOL;

        //break;
    }
}

if (false) {
    $db = new sql();
    $results = $db->fetchAll("select speaker,speech,listener,gamets from speech where companions is not null order by gamets asc ");
    $counter=0;
    $lastSpeaker="";
    $previousTs=0;
    $time_threshold=50000;

    foreach ($results as $row) {
        
        if ((($row["gamets"]-$previousTs)>$time_threshold)&&($previousTs!=0)) {
            $counter=15;
            $previousTs=$row["gamets"];
        }
        
        $previousTs=$row["gamets"];
        
        
        if ($counter<15) {
            
            if ($lastSpeaker!=$row["speaker"]) {
                $TEST_TEXT.=PHP_EOL.trim("{$row["speaker"]}:{$row["speech"]}");
                $lastSpeaker=$row["speaker"];
            } else {
                $TEST_TEXT.=trim("{$row["speech"]}");
            }
                    
            
            $counter++;
            continue;
        }
        $lastSpeaker="";
        $counter=0;
        $topic=file_get_contents("http://127.0.0.1:8000/topic?text=".urlencode($TEST_TEXT));
        $reponse=json_decode($topic,true);
        echo "Size of input:".strlen($TEST_TEXT).PHP_EOL;
        print_r($reponse);
        $TEST_TEXT="";
    }

    if (!empty($TEXT)) {
        $counter=0;
        $topic=file_get_contents("http://127.0.0.1:8000/topic?text=".urlencode($TEST_TEXT));
        $reponse=json_decode($topic,true);
        echo "Size of input:".strlen($TEST_TEXT).PHP_EOL;
        print_r($reponse);
        $TEST_TEXT="";
    }
            

}

if (false) {
    
    // Test tiny model to improve action handling.
    $db = new sql();
    $results = $db->fetchAll("select data from eventlog where type like '%inputtext%' order by gamets desc limit 10 offset 0");
    $counter=0;
        
    $results[]=["data"=>"Do you remember when we killed Melka?"];
    $results[]=["data"=>"Do you remember the name of the cave where we killed Melka?"];
    $results[]=["data"=>"Lydia and me agreed a salary some time ago...do you remember the amount?"];
    $results[]=["data"=>"I forgot our secret code. Can you remind me?"];
    $results[]=["data"=>"I remember when we fought that some conjurors in a cave"];
        
    foreach ($results as $row) {

        $TEST_TEXT=$row["data"];
        $pattern = "/\([^)]*Context location[^)]*\)/"; // Remove (Context location..
        $replacement = "";
        $TEST_TEXT = preg_replace($pattern, $replacement, $TEST_TEXT); // // assistant vs user war
        
        $pattern = '/\(talking to [^()]+\)/i';
        $TEST_TEXT = preg_replace($pattern, '', $TEST_TEXT);
    
        
        $keywords=file_get_contents("http://127.0.0.1:8000/command?text=".urlencode($TEST_TEXT));
        $reponse=json_decode($keywords,true);
        
        echo "{$reponse["is_command"]}\t{$reponse["elapsed_time"]}\t$TEST_TEXT".PHP_EOL;
        
        
    }
}

