<?php

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {

    $jsonDataInput = json_decode(file_get_contents("php://input"), true);

    $startTime = microtime(true);

    error_reporting(0);
    ini_set('display_errors', 0);

    $enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
    require_once $enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php";

    $GLOBALS["ENGINE_PATH"] = $enginePath;

    $source=$jsonDataInput["source"];
    $filename  = "$enginePath/data/pictures/gallery/" .basename($source);
    unlink($filename);
    
    die(json_encode(["status" => "success"]));

}
die(json_encode(["status" => "error"]));
