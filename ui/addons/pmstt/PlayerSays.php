<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// If this is a preflight request, end here
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

error_reporting(E_ERROR);
$enginePath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;;

require_once($enginePath . "lib".DIRECTORY_SEPARATOR."runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."rolemaster_helpers.php");

$db = $GLOBALS["db"];

if (isset($_GET["speech"]) && is_string($_GET["speech"]) && trim($_GET["speech"]) !== "") {
    $speech=$db->escape($_GET["speech"]);
} else if (isset($argv) && is_array($argv) && isset($argv[1])) {
    $speech=$db->escape($argv[1]);
} else
    die("No speech".PHP_EOL);



$db->insert(
    'responselog',
    array(
        'localts' => time(),
        'sent' => 0,
        'actor' => "rolemaster",
        'text' => "",
        'action' => "rolecommand|ImpersonatePlayer@$speech@inputtext",
        'tag' => ""
    )
);

?>
