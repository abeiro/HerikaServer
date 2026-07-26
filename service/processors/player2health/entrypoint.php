<?php

$GLOBALS['TASKS']['player2health'] = [];
$GLOBALS['TASKS']['player2health']['fn'] = function () {
    $enginePath = $GLOBALS['ENGINE_ROOT'];
    if (!isset($GLOBALS['db'])) {
        $GLOBALS['db'] = new sql();
    }

    require_once $enginePath . 'lib/player2_health.php';
    chimPlayer2HealthTick();
};
