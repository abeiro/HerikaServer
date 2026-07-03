<?php

require_once __DIR__ . "/../lib/api_doc.php";
require_once __DIR__ . "/../lib/api.php";
require_once __DIR__ . "/../lib/snqe.class.php";

error_log("===================================================================================== RUN ");

$latsRid                          = $GLOBALS["db"]->fetchAll("select *  from eventlog order by rowid desc LIMIT 1 OFFSET 0");
$res                              = $GLOBALS["db"]->fetchAll("select max(gamets)+1 as gamets,max(ts)+1 as ts,max(localts)+1 as localts from eventlog where rowid={$latsRid[0]["rowid"]}");
$res2                             = $GLOBALS["db"]->fetchAll("select max(localts) as localts from responselog");
$GLOBALS["gameRequest"][1]        = $res[0]["ts"] + 0;
$GLOBALS["gameRequest"][2]        = $res[0]["gamets"] + 0;
$GLOBALS["gameRequest"][0]        = "";
$GLOBALS["last_localts"]          = $res[0]["localts"] + 0;
$GLOBALS["last_gamets"]           = $res[0]["gamets"] + 0;
$GLOBALS["last_ts"]               = $res[0]["ts"] + 0;
$GLOBALS["last_instruction_sent"] = $res2[0]["localts"] + 0;
$GLOBALS["actors_present"]        = DataBeingsInCloseRange(true);

$lastChat  = $GLOBALS["db"]->fetchAll("select max(localts) as m from eventlog where type IN ('chat','prechat','rechat')");
$lastEvent = $GLOBALS["db"]->fetchAll("select max(localts) as n from eventlog ");
if (($lastEvent[0]["n"] - $lastChat[0]["m"]) > 20) { // 20 seconds of silence
    if (($GLOBALS["last_localts"] - $GLOBALS["last_instruction_sent"]) > 30) {
        $GLOBALS["NPCS_ARE_NOT_TALKING"] = 1;
        error_log("[SNQE MAIN] NPCS_ARE_NOT_TALKING");
    } else {
        $GLOBALS["NPCS_ARE_NOT_TALKING"] = 0;
        error_log("[SNQE MAIN] NPCS_ARE_NOT_TALKING, but instruction was sent " . ($GLOBALS["last_localts"] - $GLOBALS["last_instruction_sent"]) . " secs ago");
    }

} else {
    $GLOBALS["NPCS_ARE_NOT_TALKING"] = 0;
}

// An active sex scene reads as "silence" here (scene events are not chat/prechat/rechat), so the
// quest agent would keep pushing story instructions into NPCs who must stay on the SexLab/OStim.
$sceneActiveFile = sys_get_temp_dir() . "/nsfw_scene_active.txt";
$sceneEndedFile  = sys_get_temp_dir() . "/nsfw_scene_ended.txt";
$sceneActiveTs = is_file($sceneActiveFile) ? (int)(file_get_contents($sceneActiveFile) ?: 0) : 0;
$sceneEndedTs  = is_file($sceneEndedFile) ? (int)(file_get_contents($sceneEndedFile) ?: 0) : 0;
if ($sceneActiveTs > 0 && (time() - $sceneActiveTs) < 300 && $sceneActiveTs >= $sceneEndedTs) {
    error_log("[SNQE MAIN] Active scene - holding quest instruction generation");
    return;
}

$quests = $GLOBALS["db"]->fetchAll("select * from sneq_quests where quest_run_state IN ('not_running','not started','running')");

foreach ($quests as $questRow) {
    $quest_id = $questRow["quest_id"];
    $quest    = SNQEQuestManager::getQuest($quest_id);

    if ($questRow["quest_run_state"] != "running") {
        $quest["quest_run_state"] = "running";
        SNQEQuestManager::updateQuestState($quest_id, "running");

        $GLOBALS["db"]->insert(
            'responselog',
            [

                'localts' => time(),
                'sent'    => 0,
                'actor'   => "rolemaster",
                'text'    => "",
                'action'  => "rolecommand|StartQuest@{$quest["title"]}@{$quest["stage"]}",
                'tag' => "",

            ]
        );
    }
    if (! $quest) {
        SNQEQuestManager::createNewQuest($quest_id, '');
        $quest = SNQEQuestManager::getQuest($quest_id);
    } else {
        if ($quest["quest_run_state"] == "finished") {
            error_log("[SNQE MAIN] Quest ended" . PHP_EOL);
            return;
        }

    }

    if (isset($quest["quest_data"]["lastgamets"]) && $quest["quest_data"]["lastgamets"] >= $GLOBALS["last_gamets"]) {
        error_log("[GAME PAUSED?] last: {$GLOBALS["last_gamets"]} quest last:{$quest["quest_data"]["lastgamets"]}");
        // Do not run quest code if the last gamets we have processed is greater or equal to the last gamets processed by this quest, to avoid running quest code when game is not running.
        return;
    } else {
        if (isset($quest["quest_data"]["lastgamets"])) {
            error_log("[GAME PAUSED?] last: {$GLOBALS["last_gamets"]} quest last:{$quest["quest_data"]["lastgamets"]}");
        } else {
            error_log("[GAME PAUSED?] last: {$GLOBALS["last_gamets"]} ");
        }

    }

    $code = $questRow["code"];
    Logger::info("[SNQEQuestManager] Running {$questRow["quest_id"]}");
    file_put_contents(sys_get_temp_dir() . '/quest_buffer_' . md5($quest["quest_id"]) . '.php', $code);
    require sys_get_temp_dir() . '/quest_buffer_' . md5($quest["quest_id"]) . '.php';
}
