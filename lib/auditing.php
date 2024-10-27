<?php
function aiff_audit_end() {
    $endTime = microtime(true);
    $startTime = $GLOBALS["AUDIT_START_TIME"];
    $elapsedTime = $endTime - $startTime;

    if ($elapsedTime>1)
        error_log("Audit {$GLOBALS["AUDIT_RUNID"]}, {$GLOBALS["AUDIT_RUNID_REQUEST"]}, elapsed time: " . $elapsedTime . " seconds");
}


function audit_log($fromFile='') {
    $endTime = microtime(true);
    $startTime = $GLOBALS["AUDIT_START_TIME"];
    $elapsedTime = $endTime - $startTime;

    
    error_log("Audit {$GLOBALS["AUDIT_RUNID"]}, {$GLOBALS["AUDIT_RUNID_REQUEST"]}, $fromFile, elapsed time: " . $elapsedTime . " seconds");
}


$GLOBALS["AUDIT_RUNID"] = strrev(uniqid("di_",true));
$GLOBALS["AUDIT_START_TIME"] = microtime(true);

register_shutdown_function('aiff_audit_end');

?>
