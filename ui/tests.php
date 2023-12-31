<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
</head>
<body>

<?php

$FUNCTIONS_ARE_ENABLED=false;


error_reporting(E_ALL);
ini_set('display_errors', 1);
$file = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR.'CurrentModel.json';
$enginePath = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

$modelContents = file_get_contents($file);

echo "Current AI Model is set to $modelContents.<br/>";

echo "Checking conf.php...";
if (!file_exists($enginePath."conf".DIRECTORY_SEPARATOR."conf.php")) {
    echo "not found<br>";
} else {
    echo "ok<br/>";
}

echo "Checking for database...";
if (!file_exists($enginePath."data".DIRECTORY_SEPARATOR."mysqlitedb.db")) {
    echo "not found<br/>";
} else {
    echo "ok<br>";
}

echo "Trying to instantiate...";
$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
require_once($enginePath . "conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($enginePath . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");

$FEATURES["MEMORY_EMBEDDING"]["ENABLED"]=false;

echo "ok<br/>";

echo "Opening database...";
$db = new sql();
if (!$db) {
    echo "error<br/>";
} else {
    echo "ok<br/>";
}


echo "Trying to make a request...<pre>";

$FUNCTIONS_ARE_ENABLED=false;
$gameRequest=["inputtext"];

if (!isset($GLOBALS["CURRENT_CONNECTOR"]) || (!file_exists($enginePath."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CURRENT_CONNECTOR"]}.php"))) {
    die("{$GLOBALS["HERIKA_NAME"]}|AASPGQuestDialogue2Topic1B1Topic|I'm mindless. Choose a LLM model and connector.".PHP_EOL);

} else {

    require($enginePath."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CURRENT_CONNECTOR"]}.php");

    $head[] = array('role' => 'system', 'content' => $GLOBALS["PROMPT_HEAD"] . $GLOBALS["HERIKA_PERS"] );
    $prompt[] = array('role' => 'user', 'content' => "Hey, {$GLOBALS["HERIKA_NAME"]}");
    $contextData = array_merge($head, $prompt);

    $connectionHandler=new connector();
    $connectionHandler->open($contextData,[]);

    $buffer="";
    $totalBuffer="";
    $breakFlag=false;
    
     while (true) {

        if ($breakFlag) {
            break;
        }

        $buffer.=$connectionHandler->process();
        $totalBuffer.=$buffer;

        if ($connectionHandler->isDone()) {
            $breakFlag=true;
        }
        
     }
     
     $connectionHandler->close();
     
     print_r($GLOBALS["DEBUG_DATA"]);  
}

echo "</pre>LLM Response:<strong>$buffer</strong>";


?>
