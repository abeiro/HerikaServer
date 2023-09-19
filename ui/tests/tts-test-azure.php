<?php

$path = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
require_once($path . "conf.php");
require_once($path . "lib/Misc.php");
require_once($path . "tts/tts-azure.php");

error_reporting(E_ALL);

// Delete TTS(STT cache
$directory = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."soundcache".DIRECTORY_SEPARATOR; 

$handle = opendir($directory);
if ($handle) {
	while (false !== ($file = readdir($handle))) {
		$filePath = $directory . DIRECTORY_SEPARATOR . $file;

		if (is_file($filePath)) {
			@unlink($filePath);//Deleting cache $filePath;
		}
	}
	closedir($handle);
}
		

$testString="In Skyrim's land of snow and ice, Where dragons soar and souls entwine, Heroes rise, their fate unveiled, As ancient tales, the land does bind.";

if (isset($GLOBALS["CORE_LANG"])) {
	require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."command_prompt.php");
	$testString=$GLOBALS["ERROR_OPENAI_REQLIMIT"];
}

$file=tts($testString,$mood,$testString);

if ($file) {
	echo "<h3>$testString</h3>
	<audio controls>
	<source src='../$file' type='audio/wav'>
	Your browser does not support the audio element.
	</audio>
	";
} else {
	echo "Error<br/>";
	echo file_get_contents(".." . DIRECTORY_SEPARATOR . "soundcache" . DIRECTORY_SEPARATOR.md5(trim($testString)) . ".err");

}




?>
