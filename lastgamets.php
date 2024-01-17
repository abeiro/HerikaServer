<?php

/* Definitions and main includes */
error_reporting(E_ALL);

$path = dirname((__FILE__)) . DIRECTORY_SEPARATOR;
require_once($path . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."memory_helper_vectordb.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."memory_helper_embeddings.php");

$db = new sql();

$response = '';

try {
    // Attempt to fetch the newest entry from eventlog based on the highest 'gamets' and 'ts'
    //$query = "SELECT ts, gamets FROM eventlog ORDER BY gamets DESC LIMIT 1";
    $query = "SELECT ts, gamets FROM eventlog ORDER BY gamets DESC, ts DESC LIMIT 1";
    $result = $db->fetchAll($query);

    // Check if the result has at least one row
    if (count($result) > 0) {
        // Grab the 'ts' and 'gamets' from the newest entry in eventlog
        $ts = $result[0]['ts'];
        $gamets = $result[0]['gamets'];
        $response = $ts . '|' . $gamets;
    } else {
        // If the result is empty, send a unique message indicating this
        $response = 'DB_EMPTY';
        http_response_code(200);
    }
} catch (Exception $e) {
    // Send an appropriate error
    $response = 'Error: ' . $e->getMessage();
    http_response_code(500); // Internal Server Error
}

// Output the response
echo $response;
?>