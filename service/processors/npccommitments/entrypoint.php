<?php

$GLOBALS['TASKS']['npccommitments'] = [];
$GLOBALS['TASKS']['npccommitments']['fn'] = function () {
    $enginePath = $GLOBALS['ENGINE_ROOT'];
    $GLOBALS['ENGINE_PATH'] = $enginePath;

    if (!isset($GLOBALS['db'])) {
        $GLOBALS['db'] = new sql();
    }

    require_once $enginePath . 'lib/core/npc_commitment_worker.php';
    chimCommitmentProcessQueue();
};
