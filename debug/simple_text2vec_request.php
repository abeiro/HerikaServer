<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
$file = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR.'CurrentModel_72dc4b1c501563d149fec99eb45b45f1.json';
$enginePath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;



$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."memory_helper_embeddings.php");

$memory=array();
		
$textToEmbed=$argv[1];
$pattern = '/\([^)]+\)/';
$textToEmbedFinal = preg_replace($pattern, '', $textToEmbed);
$textToEmbedFinal=str_replace("{$GLOBALS["PLAYER_NAME"]}:","",$textToEmbedFinal);

$embeddings=getEmbedding($textToEmbedFinal);

print_r($embeddings);

?>
