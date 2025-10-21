<?php

    error_reporting(E_ALL);

    $enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
    require_once $enginePath . "conf/conf.php";
    require_once $enginePath . "lib/$DBDRIVER.class.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php";
    require_once $enginePath . "prompts" . DIRECTORY_SEPARATOR . "command_prompt.php"; // OpenAI complains

    $GLOBALS["ENGINE_PATH"] = $enginePath;

    $db = new sql();

    require_once $enginePath . "lib/core/npc_master.class.php";
    require_once $enginePath . "lib/core/api_badge.class.php";
    require_once $enginePath . "lib/core/core_profiles.class.php";
    require_once $enginePath . "lib/core/llm_connector.class.php";

    function convertSignedToUnsignedHex($signedInt)
    {
        // Convert signed to unsigned using bitwise AND
        $unsignedInt = $signedInt & 0xFFFFFFFF;
        return "0x" . dechex($unsignedInt);

    }

    if (sizeof($argv) < 3) {
        die("Usage: " . __FILE__ . " <old name> <new name> [replace in game:yes|no])");
    }

    $npcMaster=new NpcMaster();
    $oldNpcData=$npcMaster->getByName($argv[1]);
    $newNpcData=$npcMaster->getByName($argv[2]);
    
    if (!$newNpcData) {
        createProfile($argv[2]);
        $newNpcData=$npcMaster->getByName($argv[2]);
    }

    $npcMaster->renameNPC($argv[1],$argv[2]);

    if (isset($argv[3])) {

        $db->insert(
            'responselog',
            [
                'localts' => time(),
                'sent'    => 0,
                'actor'   => "rolemaster",
                'text'    => "",
                'action'  => 'rolecommand|RenameNPC@0x' . ($argv[3]) . '@'.$db->escape($argv[2]),
                'tag'     => '',
            ]
        );
}
