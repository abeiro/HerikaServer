<?php


/* POST book  entry point */


$path = dirname((__FILE__)) . DIRECTORY_SEPARATOR;
require_once($path . "conf".DIRECTORY_SEPARATOR."conf.php"); // API KEY must be there
require_once($path . "lib" .DIRECTORY_SEPARATOR."model_dynmodel.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."data_functions.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."chat_helper_functions.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."auditing.php");
require_once($path . "lib" .DIRECTORY_SEPARATOR."logger.php");



$startTime = microtime(true);
Logger::trace("Audit run ID: " . $GLOBALS["AUDIT_RUNID"]. " (BOOK) started: ".$startTime);
$GLOBALS["AUDIT_RUNID_REQUEST"]="BOOK";

$finalName=__DIR__.DIRECTORY_SEPARATOR."soundcache/_book_".md5($_FILES["file"]["tmp_name"]).".txt";

if (!$_FILES["file"]["tmp_name"]) {
    Logger::error("BOOK error, no data given: ".print_r($_POST,true));
    die("BOOK error, no data given");
    
}
@copy($_FILES["file"]["tmp_name"] ,$finalName);

$db=new sql();

$db->insert(
    'books',
    array(
        'ts' => $_GET["ts"],
        'gamets' => $_GET["gamets"],
        'content' => strip_tags(file_get_contents($finalName)),
        'sess' => 'pending',
        'localts' => time(),
        'title'=>$_GET["title"]
    )
);

$db->insert(
    'eventlog',
    array(
        'ts' => $_GET["ts"],
        'gamets' => $_GET["gamets"],
        'type' => "contentbook",
        'data' => strip_tags(file_get_contents($finalName)),
        'sess' => 'pending',
        'localts' => time()
    )
);



?>
