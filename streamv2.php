<?php


/* Legacy actions entry point */



$path = dirname((__FILE__)) . DIRECTORY_SEPARATOR;
require_once($path . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($path . "lib".DIRECTORY_SEPARATOR."model_dynmodel.php");

if (isset($_GET["profile"])) {
    if (file_exists($path . "conf".DIRECTORY_SEPARATOR."conf_{$_GET["profile"]}.php")) {
       // error_log("PROFILE: {$_GET["profile"]}");
        require_once($path . "conf".DIRECTORY_SEPARATOR."conf_{$_GET["profile"]}.php");

    }
    $GLOBALS["CURRENT_CONNECTOR"]=DMgetCurrentModel();

}

if (DMgetCurrentModel()=="openai") {
	$FUNCTIONS_ARE_ENABLED=true;
	require($path . "main.php");
	die();
} else if (DMgetCurrentModel()=="openaijson") {
	$FUNCTIONS_ARE_ENABLED=true;
	require($path . "main.php");
	die();
} else if (DMgetCurrentModel()=="koboldcppjson") {
	$FUNCTIONS_ARE_ENABLED=true;
	require($path . "main.php");
	die();
} else if (DMgetCurrentModel()=="openrouterjson") {
	$FUNCTIONS_ARE_ENABLED=true;
	require($path . "main.php");
	die();
} else if (DMgetCurrentModel()=="koboldcpp--disabled") {
	$FUNCTIONS_ARE_ENABLED=true;
	require($path . "main.php");
	die();
}  else {
	$FUNCTIONS_ARE_ENABLED=false;
	require($path . "main.php");
	die();
	
}

?>
