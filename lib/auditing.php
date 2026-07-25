<?php
require_once __DIR__ . '/request_performance.php';
chimRequestPerformanceInitialize();

function aiff_audit_end() {
    chimRequestPerformanceFinish(chimRequestPerformanceTerminalStatus(error_get_last()));

    $endTime = microtime(true);
    $startTime = $GLOBALS["AUDIT_START_TIME"];
    $elapsedTime = $endTime - $startTime;

    if ($elapsedTime>1)
        Logger::trace("Audit {$GLOBALS["AUDIT_RUNID"]}, {$GLOBALS["AUDIT_RUNID_REQUEST"]}, elapsed time: " . $elapsedTime . " seconds");
}


function audit_log($fromFile='') {
    $endTime = microtime(true);
    $startTime = $GLOBALS["AUDIT_START_TIME"];
    $elapsedTime = $endTime - $startTime;

    
    Logger::trace("Audit {$GLOBALS["AUDIT_RUNID"]}, {$GLOBALS["AUDIT_RUNID_REQUEST"]}, $fromFile, elapsed time: " . $elapsedTime . " seconds");
}

function terminate() {
    echo 'X-CUSTOM-CLOSE'.PHP_EOL;

    if (!getenv("PHPUNIT_TEST")) {
        while (@ob_get_level() > 0) 
            @ob_end_flush();
        @flush();
    }
    
    $i_level = error_reporting(0);
    try {
        // Release any acquired semaphores
        foreach (["MAIN", "ADDNPC", "VSX"] as $semaphore_id) {
            if (isset($GLOBALS["SEMAPHORES"][$semaphore_id]) && $GLOBALS["SEMAPHORES"][$semaphore_id]) {
                @SemaphoreManager::release($semaphore_id);
            }
        }
    } finally {
        error_reporting($i_level);
    }

    die();
}


function close() {
    echo 'X-CUSTOM-CLOSE'.PHP_EOL;

    if (!getenv("PHPUNIT_TEST")) {
        while (@ob_get_level() > 0) 
            @ob_end_flush();
        @flush();
    }    
}

$GLOBALS["AUDIT_RUNID"] = strrev(uniqid("di_",true));
$GLOBALS["AUDIT_START_TIME"] = microtime(true);

register_shutdown_function('aiff_audit_end');

?>
