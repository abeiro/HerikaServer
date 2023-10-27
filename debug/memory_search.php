<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
$file = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR.'CurrentModel.json';
$enginePath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;



$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."memory_helper_embeddings.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."memory_helper_vectordb.php");


		
$db = new sql();


$res=$db->fetchAll("select max(gamets) as last_gamets from eventlog");
$last_gamets=$res[0]["last_gamets"]+1;

$repeatedString = str_repeat(" ".$argv[1]." ", 3);

// Set up
$gameRequest=["inputtext","0",$last_gamets,$repeatedString];
$DIALOGUE_TARGET="(Talking to {$GLOBALS["HERIKA_NAME"]})";
$MEMORY_OFFERING="";

//echo lastKeyWords(2,['inputtext','inputtext_s']);

//print_r($gameRequest);
echo offerMemory($gameRequest, $DIALOGUE_TARGET);
print_r($GLOBALS["DEBUG_DATA"]["memories"]["selected"]);
print_r($GLOBALS["DEBUG_DATA"]["textToEmbedFinal"]);
//echo PHP_EOL."#####".PHP_EOL;
//echo offerMemoryNew($gameRequest, $DIALOGUE_TARGET);
//print_r($GLOBALS["DEBUG_DATA"]["memories"]["selected"]);
//print_r($GLOBALS["DEBUG_DATA"]["textToEmbedFinal"]);
?>


