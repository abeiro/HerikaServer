<?php

$db = new SQLite3(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'mysqlitedb.db');

$db->exec("delete from {$_GET["table"]} where rowid={$_GET["rowid"]}");

if ($_GET["table"]=="diarylog") {
    $db->exec("delete from diarylogv2");
    $db->exec("insert into diarylogv2 select topic,content,tags,people,location from diarylog");
}

if ($_GET["table"]=="memory") {
    $path = __DIR__ . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
    require_once("{$path}conf.php");
    require_once("{$path}lib/vectordb.php");
    require_once("{$path}lib/embeddings.php");    
    
    $data=deleteElement($_GET["rowid"]);

}




header("Location: ../index.php?table={$_GET["table"]}");

?>

