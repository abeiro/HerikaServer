<?php


/* Legacy actions entry point */



$path = dirname((__FILE__)) . DIRECTORY_SEPARATOR;
require_once($path . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($path . "lib".DIRECTORY_SEPARATOR."model_dynmodel.php");

if (DMgetCurrentModel()!="openai") {
	$FUNCTIONS_ARE_ENABLED=false;
	require($path . "main.php");
	die();
} else {
	$FUNCTIONS_ARE_ENABLED=true;
	require($path . "main.php");
	die();
	
}

?>
