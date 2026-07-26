<?php

$GLOBALS['TASKS']['systemsharness'] = [];
$GLOBALS['TASKS']['systemsharness']['fn'] = function (): void {
    $enginePath = $GLOBALS['ENGINE_ROOT'];
    if (!isset($GLOBALS['db'])) {
        $GLOBALS['db'] = new sql();
    }

    require_once $enginePath . 'lib' . DIRECTORY_SEPARATOR . 'chim_systems_harness.php';
    chimHarnessTick();
};
