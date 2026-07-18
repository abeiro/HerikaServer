<?php

$GLOBALS['TASKS']['dynamicprofile'] = [];
$GLOBALS['TASKS']['dynamicprofile']['fn'] = function () {
    $enginePath = $GLOBALS['ENGINE_ROOT'];
    $GLOBALS['ENGINE_PATH'] = $enginePath;

    if (!isset($GLOBALS['db'])) {
        $GLOBALS['db'] = new sql();
    }

    require_once $enginePath . 'prompts/command_prompt.php';
    require_once $enginePath . 'lib/chat_helper_functions.php';
    require_once $enginePath . 'lib/data_functions.php';
    require_once $enginePath . 'lib/dynamic_update_util.php';
    require_once $enginePath . 'lib/core/npc_master.class.php';
    require_once $enginePath . 'lib/core/core_profiles.class.php';
    require_once $enginePath . 'lib/core/llm_connector.class.php';

    triggerImmediateProfileProcessing();
};

