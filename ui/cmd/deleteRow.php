<?php

$enginePath =__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

require_once($enginePath."conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath."lib".DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");

$db = new sql();

$db->execQuery("delete from {$_GET["table"]} where rowid={$_GET["rowid"]}");



if ($_GET["table"]=="memory") {
    
    

}


if ($_GET["table"]=="memory_summary") {
    
    require_once($enginePath . "lib/memory_helper_vectordb_txtai.php");
        
    $data=deleteElement($_GET["rowid"]);

}

header("Location: ../index.php?table={$_GET["table"]}");

?>

