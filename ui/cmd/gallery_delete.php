<?php

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {

    $jsonDataInput = json_decode(file_get_contents("php://input"), true);

    $startTime = microtime(true);

    error_reporting(0);
    ini_set('display_errors', 0);

    $enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php";
    chimRuntimeBootstrap($enginePath, [
        'load_general_settings' => true,
        'load_player_name' => true,
        'load_narrator' => true,
    ]);

    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "visual_context.php";

    $GLOBALS["ENGINE_PATH"] = $enginePath;

    $source = $jsonDataInput["source"] ?? '';
    $filename = '';
    $parsedUrl = parse_url($source);
    $path = $parsedUrl['path'];
    $pathParts = explode('/', trim($path, '/'));
    $lastFolder = $pathParts[count($pathParts) - 2]; // Get the second last part of the path
    
    if ($lastFolder=="gallery")
        $filename  = "$enginePath/data/pictures/gallery/" .basename($source);
    else if ($lastFolder=="uploads")
        $filename  = "$enginePath/data/pictures/gallery/uploads/" .basename($source);

    error_log("Will delete $filename");
    if ($filename) {
        unlink($filename);
        $relativeImagePath = 'data/pictures/gallery/' . (($lastFolder === 'uploads') ? 'uploads/' : '') . basename($source);
        $db = $GLOBALS['db'] ?? null;
        if ($db && chimEnsureVisualContextTable()) {
            $db->delete('public.visual_context', 'image_path=' . $db->escapeLiteral($relativeImagePath));
        }
    }
    

    $source = $jsonDataInput["sourceVid"] ?? '';
    $filename = '';
    $parsedUrl = parse_url($source);
    $path = $parsedUrl['path'];
    $pathParts = explode('/', trim($path, '/'));
    $lastFolder = $pathParts[count($pathParts) - 2]; // Get the second last part of the path
    
    if ($lastFolder=="gallery")
        $filename  = "$enginePath/data/pictures/gallery/" .basename($source);
    else if ($lastFolder=="uploads")
        $filename  = "$enginePath/data/pictures/gallery/uploads/" .basename($source);

    error_log("Will delete $filename");
    if ($filename)
        unlink($filename);

    die(json_encode(["status" => "success"]));

}
die(json_encode(["status" => "error"]));
