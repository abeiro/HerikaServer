<?php

$enginePath =__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

require_once($enginePath."lib".DIRECTORY_SEPARATOR."runtime_bootstrap.php");
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

$db = $GLOBALS["db"];

$db->execQuery("delete from {$_GET["table"]} where rowid={$_GET["rowid"]}");



if ($_GET["table"]=="memory") {
    
    

}


if ($_GET["table"]=="memory_summary") {
    
    require_once($enginePath . "lib/memory_helper_vectordb_txtai.php");
        
    $data=deleteElement($_GET["rowid"]);

}

header("Location: ../index.php?table={$_GET["table"]}");

?>

