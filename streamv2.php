<?php


/* Legacy actions entry point */



$path = dirname((__FILE__)) . DIRECTORY_SEPARATOR;
require_once($path . "lib".DIRECTORY_SEPARATOR."runtime_bootstrap.php");
chimRuntimeBootstrap($path, [
    'load_general_settings' => true,
    'load_stt_connector' => false,
    'load_itt_connector' => false,
]);
require_once($path . "lib".DIRECTORY_SEPARATOR."model_dynmodel.php");

if (chimRuntimeBindActiveProfileFromRequest() !== null) {
    $GLOBALS["CURRENT_CONNECTOR"] = DMgetCurrentModel();
}

if (DMgetCurrentModel()=="openai") {
	$FUNCTIONS_ARE_ENABLED=true;
	require($path . "main.php");
	die();
} else if (DMgetCurrentModel()=="openaijson") {
	$FUNCTIONS_ARE_ENABLED=true;
	require($path . "main.php");
	die();
} else if (DMgetCurrentModel()=="google_openaijson") {
	$FUNCTIONS_ARE_ENABLED=true;
	require($path . "main.php");
	die();
}	else if (DMgetCurrentModel()=="web_connector") {
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
