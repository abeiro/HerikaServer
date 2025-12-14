<?php
/*

Post tasks.

*/

if ($GLOBALS["MINIME_T5"]) {
    if (isset($FEATURES["MISC"]["OGHMA_INFINIUM"]) && ($FEATURES["MISC"]["OGHMA_INFINIUM"])) {
        if (in_array($gameRequest[0], ["inputtext", "inputtext_s", "ginputtext", "ginputtext_s", "rechat"])) {

            //$TEST_TEXT=lastSpeech($GLOBALS["HERIKA_NAME"]);
            //$TEST_TEXT="{$GLOBALS["HERIKA_NAME"]}:".implode(" ",$GLOBALS["talkedSoFar"]);
            $TEST_TEXT = implode(" ", $GLOBALS["talkedSoFar"]);

            $topic = json_decode(minimePostTopic($TEST_TEXT), true);

        }
    }
}

// POST MEMORY
if ($GLOBALS["MINIME_T5"]) {
    if (in_array($gameRequest[0], ["inputtext", "inputtext_s", "ginputtext", "ginputtext_s"])) {
        if (sizeof($memoryInjectionCtx) == 0) {
            // In case main memory search didnt return resutls because minime activated and user is nt directly asking a question
            error_log("[POST MEMORY SEARCH]");
            $GLOBALS["PATCH_BYPASS_MINIME_EXTRACT"] = true;

            $GLOBALS["MEMORY_THRESHOLD_MODIFIER"] = 0.5;
            $memoryInjection                      = offerMemory($gameRequest);
            if ($memoryInjection) {

                $gameRequestCopy    = $gameRequest;
                $gameRequestCopy[0] = "infoaction";
                $gameRequestCopy[3] = "#MEMORY: {$GLOBALS["HERIKA_NAME"]} remembers this: [$memoryInjection]";
                error_log("[POST MEMORY SEARCH], memory found ($memoryInjection)");
                logEvent($gameRequestCopy, $GLOBALS["HERIKA_NAME"]); // Memory log only avaibale to current NPC.
            }

        }

        $historyData  = "";
        $lastPlace    = "";
        $lastListener = "";
        $lastDateTime = "";

        foreach (json_decode(DataSpeechJournal($GLOBALS["HERIKA_NAME"], 5), true) as $element) {
            if ($element["listener"] == "The Narrator") {
                continue;
            }
            if ($lastListener != $element["listener"]) {
                $listener     = " (talking to {$element["listener"]})";
                $lastListener = $element["listener"];
            } else {
                $listener = "";
            }

            if ($lastPlace != $element["location"]) {
                $place     = " (at {$element["location"]})";
                $lastPlace = $element["location"];
            } else {
                $place = "";
            }

            if ($lastDateTime != substr($element["sk_date"], 0, 15)) {
                $date         = substr($element["sk_date"], 0, 10);
                $time         = substr($element["sk_date"], 11);
                $dateTime     = "(on date {$date} at {$time})";
                $lastDateTime = substr($element["sk_date"], 0, 15);
            } else {
                $dateTime = "";
            }

            $historyData .= trim("{$element["speaker"]}:" . trim($element["speech"]) . " $listener $place $dateTime") . PHP_EOL;
        }

        $status = "default";
        $topic  = json_decode(minimePostScene($historyData), true);
        error_log("[minimePostScene] {$topic["generated_tags"]}");
        if ($topic["generated_tags"] == "relax") {
            $GLOBALS["db"]->insert(
                'rolemaster',
                [
                    'localts' => time(),
                    'ttl'     => 60,
                    'type'    => "scenenote",
                    'data'    => "Overall ambient seems relaxed. Actors should behave in a relaxed way",
                ]
            );

            $status = "relax";

        } else if ($topic["generated_tags"] == "intimate") {
            $GLOBALS["db"]->insert(
                'rolemaster',
                [
                    'localts' => time(),
                    'ttl'     => 60,
                    'type'    => "scenenote",
                    'data'    => "Overall ambient seems intimate. Actors should behave in a intimate way",
                ]
            );

            $status = "intimate";
        }

        $npcManager = new NpcMaster();
        $npcData    = $npcManager->getByName($GLOBALS["HERIKA_NAME"]);
        if ($npcData) {
            if (isset($npcData["extended_data"])) {
                $extended = json_decode($npcData["extended_data"], true);
            } else {
                $extended = [];
            }
            $extended["scene_status"] = $status;
            $npcData["extended_data"] = json_encode($extended);
            //$npcData["gamets_last_updated"]=$gameRequest[2];
            $npcManager->updateByArray($npcData);
        }

    }
}

$configFilepath                 = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "conf" . DIRECTORY_SEPARATOR;
$GLOBALS["PROFILES"]["default"] = "$configFilepath/conf.php";
foreach (glob($configFilepath . 'conf_????????????????????????????????.php') as $mconf) {
    if (file_exists($mconf)) {
        $filename = basename($mconf);
        $pattern  = '/conf_([a-f0-9]+)\.php/';
        preg_match($pattern, $filename, $matches);
        $hash                       = $matches[1];
        $GLOBALS["PROFILES"][$hash] = $mconf;
    }
}

require "$configFilepath/conf.php";

// Dynmci set current task
if ($GLOBALS["MINIME_T5"]) {
    if (in_array($gameRequest[0], ["inputtext", "inputtext_s", "ginputtext", "ginputtext_s"])) {

        $pattern     = "/\([^)]*Context location[^)]*\)/"; // Remove (Context location..
        $replacement = "";
        $TEST_TEXT   = preg_replace($pattern, $replacement, $gameRequest[3]); // // assistant vs user war
        $pattern     = '/\(talking to [^()]+\)/i';
        $TEST_TEXT   = preg_replace($pattern, '', $TEST_TEXT);

        $command = json_decode(minimeTask($TEST_TEXT), true);
        if (isset($command["is_command"])) {
            $prCmd = explode("@", $command["is_command"]);
            if ($prCmd[0] == "SetCurrentTask") {
                $db->insert(
                    'currentmission',
                    [
                        'ts'          => $gameRequest[1],
                        'gamets'      => $gameRequest[2],
                        'description' => $prCmd[1],
                        'sess'        => 'pending',
                        'localts'     => time(),
                    ]
                );
                $db->insert(
                    'audit_memory',
                    [
                        'input'    => $TEST_TEXT,
                        'keywords' => 'auto added task',
                        'rank_any' => -1,
                        'rank_all' => -1,
                        'memory'   => $command["is_command"],
                        'time'     => $command["elapsed_time"],
                    ]
                );
            }
        }
    }
}
