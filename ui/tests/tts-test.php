<?php

$localPath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

$enginePath = $localPath;

require_once($enginePath . "conf/conf.php");
require_once($enginePath . "lib/$DBDRIVER.class.php");
require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php"); // API KEY must be there
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");


error_reporting(E_ALL);

$testString="In Skyrim's land of snow and ice, Where dragons soar and souls entwine, Heroes rise, their fate unveiled, As ancient tales, the land does bind.";

error_reporting(E_ALL);
$db=new sql();

$GLOBALS["AVOID_TTS_CACHE"]=true;

$DEBUG_DATA=[];

$soundFile=returnLines([$testString]);

//print_r($GLOBALS["TRACK"]["FILES_GENERATED"]);

$file=basename($GLOBALS["TRACK"]["FILES_GENERATED"][0]);
if ($file) {
	echo "<h3>$testString</h3>
	<audio controls>
	<source src='../../soundcache/$file' type='audio/wav'>
	Your browser does not support the audio element.
	</audio>
	";
} else {
	echo "Error<br/>";
	echo file_get_contents(".."  . DIRECTORY_SEPARATOR . "..".DIRECTORY_SEPARATOR . "soundcache" . DIRECTORY_SEPARATOR.md5(trim($testString)) . ".err");

}




?>
