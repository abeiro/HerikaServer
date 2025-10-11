<?php 

@ob_end_clean();

$GLOBALS["ENGINE_ROOT"] = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

error_reporting(E_ALL);
echo "[MANAGER] START".PHP_EOL;


require_once("{$GLOBALS["ENGINE_ROOT"]}/service/lib/core_utils.php");
require_once("{$GLOBALS["ENGINE_ROOT"]}/conf/conf.php");
require_once("{$GLOBALS["ENGINE_ROOT"]}/lib/logger.php");

Logger::setCustomLog($GLOBALS["ENGINE_ROOT"]."log/manager.log");
Logger::deleteLogIfTooLarge($GLOBALS["ENGINE_ROOT"]."log/manager.log");

Logger::info("[SERVICE MANAGER] Run started / ".date("Y-m-d H:i:s"));

requireFilesRecursivelyByPattern($GLOBALS["ENGINE_ROOT"]."/service/processors/", '/^entrypoint\.php$/');

if (isset($argv[1])) {
    $taskname=$argv[1];
    Logger::debug("Attempting to run task: $taskname");
    if (isset($GLOBALS["TASKS"][$taskname])) {
        $task=$GLOBALS["TASKS"][$taskname];
        echo "Running task $taskname ".PHP_EOL;
        Logger::info("Starting task execution: $taskname");
        $task["fn"]();
        Logger::info("Completed task execution: $taskname");
        echo "Ended task $taskname ".PHP_EOL;
    } else {
        Logger::error("Task not found: $taskname");
        echo "Task not found $taskname ".PHP_EOL;
    }

} else {
    Logger::debug("No specific task requested, running all tasks");
    foreach ($GLOBALS["TASKS"] as $taskname=>$task)  {
        echo "[MANAGER] Running task $taskname ".PHP_EOL;
        Logger::info("Starting task execution: $taskname");
        try {
            $task["fn"]();
        } catch (Exception $e) {
            Logger::error("Error while executing task $taskname: " . $e->getMessage());
            echo "[MANAGER] Error while executing task $taskname: " . $e->getMessage() . PHP_EOL;
        }
        Logger::info("Completed task execution: $taskname");
        echo "[MANAGER] Ended task $taskname ".PHP_EOL;
    }

}
echo "[MANAGER] END".PHP_EOL;
?>