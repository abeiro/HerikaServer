<?php 

ob_start();

$GLOBALS["ENGINE_ROOT"] = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

require_once("{$GLOBALS["ENGINE_ROOT"]}/service/lib/core_utils.php");
require_once("{$GLOBALS["ENGINE_ROOT"]}/conf/conf.php");


// $GLOBALS["LOG_LEVEL"]=S_LOG_CRITICAL;


logMsg("Run started / ".date("Y-m-d H:i:s"),S_LOG_INIT);

requireFilesRecursivelyByPattern($GLOBALS["ENGINE_ROOT"]."/service/processors/", '/^entrypoint\.php$/');

if ($argv[1]) {
    $taskname=$argv[1];
    if (isset($GLOBALS["TASKS"][$taskname])) {
        $task=$GLOBALS["TASKS"][$taskname];
        echo "Running task $taskname ".PHP_EOL;
        $task["fn"]();
        echo "Ended task $taskname ".PHP_EOL;
    } else {
        echo "Task not found $taskname ".PHP_EOL;
    }

} else {
    foreach ($GLOBALS["TASKS"] as $taskname=>$task)  {
        echo "Running task $taskname ".PHP_EOL;
        $task["fn"]();
        echo "Ended task $taskname ".PHP_EOL;
    }

}

?>