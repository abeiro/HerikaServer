<?php

$GLOBALS['TASKS']['backgroundlife_requests'] = [];
$GLOBALS['TASKS']['backgroundlife_requests']['fn'] = function () {
    $enginePath = $GLOBALS['ENGINE_ROOT'];
    if (!isset($GLOBALS['db'])) {
        $GLOBALS['db'] = new sql();
    }

    require_once $enginePath . 'lib/core/npc_master.class.php';
    require_once $enginePath . 'lib/background_life_requests.php';

    chimBglProcessRequestQueue($GLOBALS['db'], $enginePath);
};
