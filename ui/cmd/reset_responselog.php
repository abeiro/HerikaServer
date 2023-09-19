<?php


$db = new SQLite3('mysqlitedb.db');

$db->exec("UPDATE responselog set sent=0");
$db->exec("DELETE FROM responselog where  text=''");


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
