<?php
function aiff_audit_end() {
    $endTime = microtime(true);
    $startTime = $GLOBALS["AUDIT_START_TIME"];
    $elapsedTime = $endTime - $startTime;

    error_log("Audit run ID: " . $GLOBALS["AUDIT_RUNID"]);
    error_log("Audit Elapsed time: " . $elapsedTime . " seconds");
}

$GLOBALS["AUDIT_RUNID"] = strrev(uniqid());
$GLOBALS["AUDIT_START_TIME"] = microtime(true);

register_shutdown_function('aiff_audit_end');

?>
