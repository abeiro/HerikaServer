<?php


$enginePath =__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

require_once($enginePath."conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath."lib".DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");

$db->execQuery("UPDATE responselog set sent=0");
$db->execQuery("DELETE FROM responselog where  text=''");


/*
$db->exec("
DROP TABLE `responselog`;");

$db->exec("
CREATE TABLE `responselog` (
  `localts` bigint NOT NULL,
  `sent` bigint NOT NULL,
  `actor` varchar(128) ,
  `text` text
);");
*/

?>
