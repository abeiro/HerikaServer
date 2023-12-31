<?php

$enginePath =__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

require_once($enginePath."conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath."lib".DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");

require_once($enginePath . "lib/memory_helper_vectordb.php");
require_once($enginePath . "lib/memory_helper_embeddings.php");

if ($_GET["delete"])
  $db->execQuery("DROP TABLE `eventlog`;");

$db->execQuery("
CREATE TABLE IF NOT EXISTS `eventlog` (
  `ts` text NOT NULL,
  `type` varchar(128) ,
  `data` text ,
  `sess` varchar(1024) ,
  `gamets` bigint NOT NULL,
  `localts` bigint NOT NULL
);");

$db->execQuery("
CREATE TABLE IF NOT EXISTS `openai_token_count` (
  `input_tokens` bigint NOT NULL,
  `output_tokens` bigint NOT NULL ,
  `total_tokens_so_far` bigint NOT NULL ,
  `cost_USD` float ,
  `total_cost_so_far_USD` float,
  `localts` bigint NOT NULL,
  `datetime` text,
  `model` text
);");

if ($_GET["delete"])
  $db->execQuery("DROP TABLE `responselog`;");

$db->execQuery("
CREATE TABLE IF NOT EXISTS `responselog` (
  `localts` bigint NOT NULL,
  `sent` bigint NOT NULL,
  `actor` varchar(128) ,
  `text` text,
  `action` varchar(256),
  `tag` varchar(256)

);");

if ($_GET["delete"])
  $db->execQuery("DROP TABLE `log`;");

$db->execQuery("
CREATE TABLE IF NOT EXISTS `log` (
  `localts` bigint NOT NULL,
  `prompt` text,
  `response` text,
  `url` text
);");

if ($_GET["delete"])
  $db->execQuery("DROP TABLE `quests`;");

$db->execQuery("
CREATE TABLE IF NOT EXISTS `quests` (
  `ts` text NOT NULL,
  `sess` varchar(1024) ,
  `id_quest` varchar(1024) NOT NULL,
  `name` text,
  `editor_id` text,
  `giver_actor_id` bigint,
  `reward` text,
  `target_id` text,
  `is_uniqe` bool,
  `mod` text,
  `stage` int,
  `briefing` text,
  `briefing2` text,
  `localts` bigint NOT NULL,
  `gamets` bigint NOT NULL,
  `data` text,
  `status` text
);");

if ($_GET["delete"])
  $db->execQuery("DROP TABLE `speech`;");

$db->execQuery("
CREATE TABLE IF NOT EXISTS `speech` (
  `ts` text NOT NULL,
  `sess` varchar(1024) ,
  `speaker` text,
  `speech` text,
  `location` text,
  `listener` text,
  `topic` text,
  `localts` bigint NOT NULL,
  `gamets` bigint NOT NULL
);");

if ($_GET["delete"])
  $db->execQuery("DROP TABLE `diarylog`;");

$db->execQuery("
CREATE TABLE IF NOT EXISTS `diarylog` (
  `ts` text NOT NULL,
  `sess` varchar(1024) ,
  `topic` text,
  `content` text,
  `tags` text,
  `people` text,
  `localts` bigint NOT NULL,
  `location` text,
  `gamets` bigint NOT NULL
);");

if ($_GET["delete"])
  $db->execQuery("DROP TABLE `books`;");


$db->execQuery("
CREATE TABLE IF NOT EXISTS `books` (
  `ts` text NOT NULL,
  `sess` varchar(1024) ,
  `title` text,
  `content` text,
  `localts` bigint NOT NULL,
  `gamets` bigint NOT NULL
);");

if ($_GET["delete"])
  $db->execQuery("DROP TABLE `currentmission`;");

$db->execQuery("
CREATE TABLE IF NOT EXISTS `currentmission` (
  `ts` text NOT NULL,
  `sess` varchar(1024) ,
  `description` text,
  `localts` bigint NOT NULL,
  `gamets` bigint NOT NULL
);");

if ($_GET["delete"])
  $db->execQuery("DROP TABLE `diarylogv2`;");


$db->execQuery("
CREATE VIRTUAL TABLE IF NOT EXISTS diarylogv2 
USING FTS5(topic,content,tags,people,location);");

if ($_GET["delete"])
  $db->execQuery("DROP TABLE `memory`;");

$db->execQuery("CREATE TABLE IF NOT EXISTS `memory` (
	`speaker`	TEXT,
	`message`	TEXT,
	`session`	TEXT,
	`uid`	INTEGER,
	`listener`	TEXT,
	`localts`	INTEGER,
    `gamets` bigint NOT NULL,
	`momentum`	TEXT,
	PRIMARY KEY(`uid` AUTOINCREMENT)
);");

if ($_GET["delete"])
  $db->execQuery("DROP TABLE `memory`;");

$db->execQuery("CREATE TABLE IF NOT EXISTS `memory` (
	`speaker`	TEXT,
	`message`	TEXT,
	`session`	TEXT,
	`uid`	INTEGER,
	`listener`	TEXT,
	`localts`	INTEGER,
    `gamets` bigint NOT NULL,
	`momentum`	TEXT,
	PRIMARY KEY(`uid` AUTOINCREMENT)
);");

if ($_GET["delete"])
  $db->execQuery("DROP TABLE `memory_summary`;");

$db->execQuery("CREATE TABLE IF NOT EXISTS memory_summary (
  `gamets_truncated`  bigint NOT NULL,
  `n` INTEGER,
  packed_message TEXT,
  summary TEXT,
  classifier TEXT,
  uid INTEGER
);");


$db->execQuery("DROP VIEW memory_v;");
$db->execQuery("CREATE VIEW memory_v
as
SELECT * FROM (
select message,uid,gamets,'-' as speaker,'-' as listener,999999999999999999 as ts from memory where message not like 'Dear Diary%' and message <>''
union
select '(Context Location:'||location||') '||speaker||': '||speech,0,gamets,speaker,listener,ts as ts  from speech where speech<>'' 
union
select data,0,gamets,'-','-' as listener,ts as ts from eventlog where type in ('death','location')
) ORDER BY gamets asc,ts asc
;");




if (isset($GLOBALS["MEMORY_EMBEDDING"]) && $GLOBALS["MEMORY_EMBEDDING"]) {
  deleteCollection();
  getCollectionUID();
}

@mkdir(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "soundcache");

?>
