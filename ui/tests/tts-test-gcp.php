<?php

$localPath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
require_once($localPath . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($localPath . "lib".DIRECTORY_SEPARATOR."Misc.php");
require_once($localPath . "tts/tts-gcp.php");

error_reporting(E_ALL);

// Delete TTS(STT cache
$directory = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."soundcache".DIRECTORY_SEPARATOR; 

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
$mood="";

try {
	$file=tts($testString,$mood,$testString);
} catch (Exception $e) {
	
	echo "<pre>".print_r($e,true)."</pre>";
	die();
	
}

if ($file) {
	echo "<h3>$testString</h3>
	<audio controls>
	<source src='../../$file' type='audio/wav'>
	Your browser does not support the audio element.
	</audio>
	";
} else {
	echo "Error<br/>";
	echo file_get_contents(".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR."soundcache" . DIRECTORY_SEPARATOR.md5(trim($testString)) . ".err");

}




?>
