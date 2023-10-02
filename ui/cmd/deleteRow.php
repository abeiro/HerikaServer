<?php

$enginePath =__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

require_once($enginePath."conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath."lib".DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");

$db = new sql();

$db->execQuery("delete from {$_GET["table"]} where rowid={$_GET["rowid"]}");

if ($_GET["table"]=="diarylog") {
    $db->execQuery("delete from diarylogv2");
    $db->execQuery("insert into diarylogv2 select topic,content,tags,people,location from diarylog");
}

if ($_GET["table"]=="memory") {
    $path = __DIR__ . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
    
    require_once($enginePath . "lib/memory_helper_vectordb.php");
    require_once($enginePath . "lib/memory_helper_embeddings.php");    
    
    $data=deleteElement($_GET["rowid"]);

}




header("Location: ../index.php?table={$_GET["table"]}");

?>

