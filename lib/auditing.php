<?php
function aiff_audit_end() {
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
    
    if (isset($GLOBALS["SEMAPHORES"])) {
        $i_level = error_reporting(0);
        try {
            $semaphore_main = $GLOBALS["SEMAPHORES"]["MAIN"] ?? null;
            $semaphore_addnpc = $GLOBALS["SEMAPHORES"]["ADDNPC"] ?? null;
            $semaphore_vsx = $GLOBALS["SEMAPHORES"]["VSX"] ?? null;

            if (isset($semaphore_main) && $semaphore_main) {
                @sem_release($semaphore_main);
                //Logger::warn("[terminate] semaphore_main released - exec trace " .__FILE__ . " " . __LINE__);
            }

            if (isset($semaphore_addnpc) && $semaphore_addnpc) {
                @sem_release($semaphore_addnpc);
                //Logger::warn("[terminate] semaphore_addnpc released - exec trace " .__FILE__ . " " . __LINE__);
            }

            if (isset($semaphore_vsx) && $semaphore_vsx) {
                @sem_release($semaphore_vsx);
                //Logger::warn("[terminate] semaphore_vsx released - exec trace " .__FILE__ . " " . __LINE__);
            }
        } finally {
            error_reporting($i_level);
        }
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
