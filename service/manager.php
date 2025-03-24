<?php 


$GLOBALS["ENGINE_ROOT"] = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

require_once("{$GLOBALS["ENGINE_ROOT"]}/service/lib/core_utils.php");
require_once("{$GLOBALS["ENGINE_ROOT"]}/conf/conf.php");


// $GLOBALS["LOG_LEVEL"]=S_LOG_CRITICAL;


logMsg("Run started / ".date("Y-m-d H:i:s"),S_LOG_INIT);

requireFilesRecursivelyByPattern($GLOBALS["ENGINE_ROOT"]."/service/processors/", '/^entrypoint\.php$/');

?>