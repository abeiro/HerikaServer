<?php

error_reporting(E_ALL);

$enginePath = dirname((__FILE__)) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once $enginePath . "lib/runtime_bootstrap.php";
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_player_name' => true,
    'load_narrator' => true,
]);

require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php";
require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php";
require_once $enginePath . "prompts" . DIRECTORY_SEPARATOR . "command_prompt.php"; // OpenAI complains

$GLOBALS["ENGINE_PATH"] = $enginePath;

$db = $GLOBALS["db"];

require_once $enginePath . "lib/core/npc_master.class.php";
require_once $enginePath . "lib/core/api_badge.class.php";
require_once $enginePath . "lib/core/core_profiles.class.php";
require_once $enginePath . "lib/core/llm_connector.class.php";

function convertSignedToUnsignedHex($signedInt)
{
    // Convert signed to unsigned using bitwise AND
    $unsignedInt = $signedInt & 0xFFFFFFFF;
    return "0x" . str_pad(dechex($unsignedInt),8,"0",STR_PAD_LEFT);

}

if (sizeof($argv) < 4 && sizeof($argv) != 2) {
    die("Usage: " . __FILE__ . " <old name> <new name> [replace in game:yes|no])".PHP_EOL);
}

if (sizeof($argv) == 2) {
    $npcMaster  = new NpcMaster();
    $newNpcData = $npcMaster->getByRefId($argv[1]);

    if (! $newNpcData) {
        $newNpcDataHisRow=$db->fetchOne("select * from core_npc_master_history where refid='{$argv[1]}' order by created desc");
        $newNpcData=$newNpcDataHisRow;
        if (!$newNpcData)
            die("NPC {$argv[1]} not found ");
        
    }
    error_log("Renaming {$newNpcData["npc_name"]}");
    $db->insert(
        'responselog',
        [
            'localts' => time(),
            'sent'    => 0,
            'actor'   => "rolemaster",
            'text'    => "",
            'action'  => 'rolecommand|RenameNPC@0x' . ($argv[1]) . '@' . $db->escape($newNpcData["npc_name"]),
            'tag'     => '',
        ]
    );

} else {

    $npcMaster  = new NpcMaster();
    $oldNpcData = $npcMaster->getByName($argv[1]);
    $newNpcData = $npcMaster->getByName($argv[2]);

    if (! $newNpcData) {
        createProfile($argv[2]);
        $newNpcData = $npcMaster->getByName($argv[2]);
    }

    $npcMaster->renameNPC($argv[1], $argv[2]);

    if (isset($argv[3])) {

        $db->insert(
            'responselog',
            [
                'localts' => time(),
                'sent'    => 0,
                'actor'   => "rolemaster",
                'text'    => "",
                'action'  => 'rolecommand|RenameNPC@0x' . ($argv[3]) . '@' . $db->escape($argv[2]),
                'tag'     => '',
            ]
        );
    }
}
