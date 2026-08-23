<?php

$enginePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;

require_once($enginePath . 'lib' . DIRECTORY_SEPARATOR . 'runtime_bootstrap.php');
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_stt_connector' => false,
    'load_itt_connector' => false,
    'load_player_name' => false,
    'load_narrator' => false,
]);

require_once($enginePath . 'ext' . DIRECTORY_SEPARATOR . 'relationship_system' . DIRECTORY_SEPARATOR . 'batch_build.php');
