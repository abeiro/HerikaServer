<?php
require_once($GLOBALS["ENGINE_PATH"]."/lib/dynamic_update_util.php");
require_once($GLOBALS["ENGINE_PATH"]."/lib/utils_game_timestamp.php");
require_once($GLOBALS["ENGINE_PATH"]."/lib/playthrough_snapshot.php");

$MUST_END=false;

$gameRequest[3] = @mb_convert_encoding($gameRequest[3], 'UTF-8', 'UTF-8');


// Moved Dynamic Updates functions here
require_once($GLOBALS["ENGINE_PATH"]."/lib/dynamic_update_util.php");


if ($gameRequest[0] == "init") { // Reset responses if init sent (Think about this)
    // avoid a rare case where skyrim briefly reverts to level 1 Prisoner during load
    // Moved Dynamic Updates functions here
    if ($gameRequest[2] == "10000000") {
        Logger::warn("Ignoring init with a gamets of 10000000.");
        $MUST_END=true;
        return;
    }
    $now=time();

    // Dragon Break autosnapshot: detect large rollback and snapshot before pruning
    try {
        $prevGamets = DataLastKnownGameTS();
        $incomingGamets = intval($gameRequest[2]);
        $snapshotId = dragon_break_snapshot_if_needed($prevGamets, $incomingGamets);
        if ($snapshotId > 0) {
            Logger::info("DragonBreak: Created snapshot id {$snapshotId} prior to rollback prune");
        }
    } catch (Exception $e) {
        Logger::warn("DragonBreak: Snapshot attempt failed: ".$e->getMessage());
    }
    $db->delete("eventlog", "gamets>={$gameRequest[2]}  ");
    $db->delete("eventlog", "localts>$now ");
    //$db->delete("eventlog", "type='playerinfo'");
    //$db->delete("quests", "1=1");
    $db->delete("speech", "gamets>={$gameRequest[2]}  ");
    $db->delete("speech", "localts>$now ");
    $db->delete("currentmission", "gamets>={$gameRequest[2]}  ");
    $db->delete("currentmission", "localts>$now   ");
    $db->delete("diarylog", "gamets>={$gameRequest[2]}  ");
    $db->delete("diarylog", "localts>=0$now ");
    $db->delete("books", "gamets>=0{$gameRequest[2]}  ");
    $db->delete("books", "localts>$now ");
    $db->delete("responselog", " 1=1 ");
    $db->delete("rolemaster", " 1=1 ");
    $db->delete("actions_issued", "gamets>={$gameRequest[2]}  ");
    $db->delete("moods_issued", "gamets>={$gameRequest[2]}  ");

    /* This is obsolete */
    /*
    if ($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) {
        $results = $db->query("select gamets_truncated,uid from memory_summary where gamets_truncated>{$gameRequest[2]}");
        while ($memoryRow = $db->fetchArray($results)) {
            deleteElement($memoryRow["uid"]);
        }
    }
    */
    $db->delete("memory_summary", "gamets_truncated>{$gameRequest[2]}  ");
    $db->delete("memory", "gamets>{$gameRequest[2]}  ");

    //$db->delete("diarylogv2", "true");
    //$db->execQuery("insert into diarylogv2 select topic,content,tags,people,location from diarylog");
    //die(print_r($gameRequest,true));
    $db->update("responselog", "sent=0", "sent=1 and (action='AASPGDialogueHerika2Branch1Topic')");
    $db->insert(
        'eventlog',
        array(
            'ts' => $gameRequest[1],
            'gamets' => $gameRequest[2],
            'type' => $gameRequest[0],
            'data' => $gameRequest[3],
            'sess' => 'pending',
            'localts' => time()
        )
    );
    
    if (isset($gameRequest[3]) && $gameRequest[3]) {
        $db->upsertRowOnConflict(
            'conf_opts',
            array(
                'id' => "plugin_dll_version",
                'value' =>$gameRequest[3]
            ),
            "id"
        );
    }

    Logger::trace("INIT PROCESSING ".(time()-$now));
    // Delete TTS(STT cache
    $directory = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."soundcache";

    touch(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."soundcache".DIRECTORY_SEPARATOR.".placeholder");
    $sixHoursAgo = time() - (6 * 60 * 60);

    $handle = opendir($directory);
    if ($handle) {
        while (false !== ($file = readdir($handle))) {
            $filePath = $directory . DIRECTORY_SEPARATOR . $file;

            if (is_file($filePath)) {
                if (strpos($filePath, ".placeholder")!==false) {
                    continue;
                }
                $fileMTime = filemtime($filePath);
                if ($fileMTime < $sixHoursAgo) {
                    @unlink($filePath);
                }
            }
        }
        closedir($handle);
    }
    
    Logger::trace("POST INIT PROCESSING ".(time()-$now));
    $MUST_END=true;


}

if ($gameRequest[0] == "wipe") { // Reset reponses if init sent (Think about this)
    $now=time();
    $db->delete("eventlog", " 1=1");
    $db->delete("quests", " 1=1");
    $db->delete("speech", " 1=1 ");
    $db->delete("currentmission", " 1=1 ");
    $db->delete("diarylog", " 1=1 ");
    $db->delete("books", " 1=1 ");

    if ($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) {
        $results = $db->query("select gamets_truncated,uid from memory_summary where gamets_truncated>{$gameRequest[2]}");
        while ($memoryRow = $db->fetchArray($results)) {
            deleteElement($memoryRow["uid"]);
        }
    }
    $db->delete("memory_summary", " 1=1 ");
    $db->delete("memory", " 1=1 ");

    //$db->delete("diarylogv2", "true");
    //$db->execQuery("insert into diarylogv2 select topic,content,tags,people,location from diarylog");
    //die(print_r($gameRequest,true));
    $db->update("responselog", "sent=0", "sent=1 and (action='AASPGDialogueHerika2Branch1Topic')");
    $db->insert(
        'eventlog',
        array(
            'ts' => $gameRequest[1],
            'gamets' => $gameRequest[2],
            'type' => $gameRequest[0],
            'data' => $gameRequest[3],
            'sess' => 'pending',
            'localts' => time()
        )
    );

    // Delete TTS(STT cache
    $directory = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."soundcache";

    touch(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."soundcache".DIRECTORY_SEPARATOR.".placeholder");
    $sixHoursAgo = time() - (6 * 60 * 60);

    $handle = opendir($directory);
    if ($handle) {
        while (false !== ($file = readdir($handle))) {
            $filePath = $directory . DIRECTORY_SEPARATOR . $file;

            if (is_file($filePath)) {
                if (strpos($filePath, ".placeholder")!==false) {
                    continue;
                }
                $fileMTime = filemtime($filePath);
                if ($fileMTime < $sixHoursAgo) {
                    @unlink($filePath);
                }
            }
        }
        closedir($handle);
    }
    

    $MUST_END=true;


} elseif ($gameRequest[0] == "request") { // Just requested response
    // Do nothing
    $responseDataMl = DataDequeue();
    foreach ($responseDataMl as $responseData) {
        echo "{$responseData["actor"]}|{$responseData["action"]}|{$responseData["text"]}\r\n";
    }
    
    if (time()%5==0)
        logEvent($gameRequest);
    
    $MUST_END=true;

    // NEW METHODS FROM HERE
} elseif ($gameRequest[0] == "_quest") {
    error_reporting(E_ALL);

    $questParsedData = json_decode($gameRequest[3], true);
    //print_r($questParsedData);
    if (!empty($questParsedData["currentbrief"])) {
        $db->delete('quests', "id_quest='{$questParsedData["formId"]}' ");
        $db->insert(
            'quests',
            array(
                'ts' => $gameRequest[1],
                'gamets' => $gameRequest[2],
                'name' => $questParsedData["name"],
                'briefing' => $questParsedData["currentbrief"],
                'data' => json_encode($questParsedData["currentbrief2"]),
                'stage' => $questParsedData["stage"],
                'giver_actor_id' => isset($questParsedData["data"]["questgiver"]) ? $questParsedData["data"]["questgiver"] : "",
                'id_quest' => $questParsedData["formId"],
                'sess' => 'pending',
                'status' => isset($questParsedData["status"]) ? $questParsedData["status"] : "",
                'localts' => time()
            )
        );

    }
    $MUST_END=true;



} elseif ($gameRequest[0] == "_uquest") {
    
    $questParsedData = explode("@",$gameRequest[3]);
    
    if (!empty($questParsedData[0])) {
        $data=array(
            'ts' => $gameRequest[1],
            'gamets' => $gameRequest[2],
            'localts' => time(),
            'briefing' => $questParsedData[2],
            'data' => $questParsedData[2],
            'id_quest'=>$questParsedData[0],
            'stage'=>($questParsedData[3] ?? null)
        );
        
        $db->insert('questlog',$data);
        
        // Include and call dynamicoghma.php after questlog entry
        require_once(__DIR__.DIRECTORY_SEPARATOR."dynamicoghma.php");
        syncQuestWithOghma($questParsedData[0], ($questParsedData[3] ?? null));
    }
    $MUST_END=true;



} elseif ($gameRequest[0] == "_questdata") {
    

    $questParsedData = explode("@",$gameRequest[3]);
    
    if (!empty($questParsedData[0])) {
        $data=array(
                'briefing2' => $questParsedData[1],
        );
        
        $db->updateRow('quests',$data," id_quest='{$questParsedData[0]}' ");

    }
    $MUST_END=true;


} elseif ($gameRequest[0] == "updateequipment") {
    // Live equipment update from TESEquipEvent
    $updateData = explode("@",$gameRequest[3]);
    
    if (!empty($updateData[0])) {
        $npcName = $updateData[0];
        
        // Parse equipment (8 slots)
        $equipment = [
            'helmet' => isset($updateData[1]) ? $updateData[1] : '',
            'armor' => isset($updateData[2]) ? $updateData[2] : '',
            'boots' => isset($updateData[3]) ? $updateData[3] : '',
            'gloves' => isset($updateData[4]) ? $updateData[4] : '',
            'amulet' => isset($updateData[5]) ? $updateData[5] : '',
            'ring' => isset($updateData[6]) ? $updateData[6] : '',
            'left_hand' => isset($updateData[7]) ? $updateData[7] : '',
            'right_hand' => isset($updateData[8]) ? $updateData[8] : ''
        ];
        
        // Get current NPC
        $currentNpcData = $npcMaster->getByName($npcName);
        
        if ($currentNpcData) {
            // Get existing metadata
            $meta = [];
            if (!empty($currentNpcData['metadata'])) {
                $meta = json_decode($currentNpcData['metadata'], true);
                if (!is_array($meta)) {
                    $meta = [];
                }
            }
            
            // Update equipment section
            $meta['equipment'] = $equipment;
            
            // Save back to database
            $currentNpcData = $npcMaster->setMetadata($currentNpcData, $meta);
            $npcMaster->updateByArray($currentNpcData);
            
            Logger::info("Updated equipment for {$npcName}");
        }
    }
    $MUST_END=true;


} elseif ($gameRequest[0] == "updateinventory") {
    // Live inventory update from TESContainerChangedEvent
    Logger::info("RECEIVED updateinventory command: ".$gameRequest[3]);
    
    $updateData = explode("@",$gameRequest[3]);
    
    if (!empty($updateData[0])) {
        $npcName = $updateData[0];
        $inventoryRaw = isset($updateData[1]) ? $updateData[1] : '';
        
        Logger::info("Processing inventory for NPC: {$npcName}, Raw data length: ".strlen($inventoryRaw));
        
        // Parse inventory (format: ItemName::Count~ItemName2::Count2~...)
        $inventory = [];
        if (!empty($inventoryRaw)) {
            $items = explode("~", $inventoryRaw); 
            Logger::info("Found ".count($items)." items to process");
            
            foreach ($items as $itemData) {
                $parts = explode("::", $itemData);
                if (count($parts) === 2) {
                    $itemName = $parts[0];
                    $count = intval($parts[1]);
                    if (!empty($itemName) && $count > 0) {
                        $inventory[] = [
                            'name' => $itemName,
                            'count' => $count
                        ];
                    }
                }
            }
        }
        
        Logger::info("Parsed ".count($inventory)." valid inventory items");
        
        if (count($inventory) > 0) {
            Logger::info("Sample items: ".$inventory[0]['name']." x".$inventory[0]['count']);
        }
        
        // Get current NPC
        $currentNpcData = $npcMaster->getByName($npcName);
        
        if ($currentNpcData) {
            Logger::info("Found NPC in database: {$npcName}, NPC ID: ".$currentNpcData['id']);
            
            // Get existing metadata
            $meta = [];
            if (!empty($currentNpcData['metadata'])) {
                $meta = json_decode($currentNpcData['metadata'], true);
                if (!is_array($meta)) {
                    $meta = [];
                }
            }
            
            Logger::info("Existing metadata keys: ".implode(", ", array_keys($meta)));
            
            // Update inventory section
            $meta['inventory'] = $inventory;
            $meta['inventory_updated'] = time();
            
            Logger::info("Setting inventory with ".count($inventory)." items, timestamp: ".$meta['inventory_updated']);
            
            // Save back to database
            $currentNpcData = $npcMaster->setMetadata($currentNpcData, $meta);
            $updateResult = $npcMaster->updateByArray($currentNpcData);
            
            Logger::info("Database update result: ".var_export($updateResult, true));
            
            // Verify the update by reading it back
            $verifyNpc = $npcMaster->getByName($npcName);
            if ($verifyNpc && !empty($verifyNpc['metadata'])) {
                $verifyMeta = json_decode($verifyNpc['metadata'], true);
                if (isset($verifyMeta['inventory'])) {
                    Logger::info("VERIFICATION: Inventory in database has ".count($verifyMeta['inventory'])." items");
                } else {
                    Logger::error("VERIFICATION: Inventory NOT FOUND in metadata after update!");
                }
            }
            
            Logger::info("Updated inventory for {$npcName} (".count($inventory)." items) - SUCCESS");
        } else {
            Logger::warn("NPC not found in database: {$npcName}");
        }
    } else {
        Logger::warn("updateinventory: No NPC name in data");
    }
    $MUST_END=true;

} elseif ($gameRequest[0] == "updateskills") {
    // Live skills update (periodic, every 5 minutes)
    $updateData = explode("@",$gameRequest[3]);
    
    if (!empty($updateData[0])) {
        $npcName = $updateData[0];
        
        // Skills array (18 Skyrim skills)
        $skills = [
            'archery' => isset($updateData[1]) ? floatval($updateData[1]) : 0,
            'block' => isset($updateData[2]) ? floatval($updateData[2]) : 0,
            'onehanded' => isset($updateData[3]) ? floatval($updateData[3]) : 0,
            'twohanded' => isset($updateData[4]) ? floatval($updateData[4]) : 0,
            'conjuration' => isset($updateData[5]) ? floatval($updateData[5]) : 0,
            'destruction' => isset($updateData[6]) ? floatval($updateData[6]) : 0,
            'illusion' => isset($updateData[7]) ? floatval($updateData[7]) : 0,
            'restoration' => isset($updateData[8]) ? floatval($updateData[8]) : 0,
            'alteration' => isset($updateData[9]) ? floatval($updateData[9]) : 0,
            'enchanting' => isset($updateData[10]) ? floatval($updateData[10]) : 0,
            'smithing' => isset($updateData[11]) ? floatval($updateData[11]) : 0,
            'heavyarmor' => isset($updateData[12]) ? floatval($updateData[12]) : 0,
            'lightarmor' => isset($updateData[13]) ? floatval($updateData[13]) : 0,
            'pickpocket' => isset($updateData[14]) ? floatval($updateData[14]) : 0,
            'lockpicking' => isset($updateData[15]) ? floatval($updateData[15]) : 0,
            'sneak' => isset($updateData[16]) ? floatval($updateData[16]) : 0,
            'alchemy' => isset($updateData[17]) ? floatval($updateData[17]) : 0,
            'speechcraft' => isset($updateData[18]) ? floatval($updateData[18]) : 0
        ];
        
        $currentNpcData = $npcMaster->getByName($npcName);
        if ($currentNpcData) {
            $meta = [];
            if (!empty($currentNpcData['metadata'])) {
                $meta = json_decode($currentNpcData['metadata'], true);
                if (!is_array($meta)) { $meta = []; }
            }
            
            $meta['skills'] = $skills;
            
            $currentNpcData = $npcMaster->setMetadata($currentNpcData, $meta);
            $npcMaster->updateByArray($currentNpcData);
            
            Logger::info("Updated skills for {$npcName}");
        }
    }
    $MUST_END=true;

} elseif ($gameRequest[0] == "updatestats") {
    // Live stats update (combat-aware, every 3s in combat or on hit)
    $updateData = explode("@",$gameRequest[3]);
    
    if (!empty($updateData[0])) {
        $npcName = $updateData[0];
        
        // Stats (level, health, magicka, stamina with current/max)
        $stats = [
            'level' => isset($updateData[1]) ? intval($updateData[1]) : 1,
            'health' => isset($updateData[2]) ? floatval($updateData[2]) : 0,
            'health_max' => isset($updateData[3]) ? floatval($updateData[3]) : 0,
            'magicka' => isset($updateData[4]) ? floatval($updateData[4]) : 0,
            'magicka_max' => isset($updateData[5]) ? floatval($updateData[5]) : 0,
            'stamina' => isset($updateData[6]) ? floatval($updateData[6]) : 0,
            'stamina_max' => isset($updateData[7]) ? floatval($updateData[7]) : 0
        ];
        
        $currentNpcData = $npcMaster->getByName($npcName);
        if ($currentNpcData) {
            $meta = [];
            if (!empty($currentNpcData['metadata'])) {
                $meta = json_decode($currentNpcData['metadata'], true);
                if (!is_array($meta)) { $meta = []; }
            }
            
            $meta['stats'] = $stats;
            $meta['stats_updated'] = time();  // Track last stats update
            
            $currentNpcData = $npcMaster->setMetadata($currentNpcData, $meta);
            $npcMaster->updateByArray($currentNpcData);
            
            Logger::info("Updated stats for {$npcName} (HP:{$stats['health']}/{$stats['health_max']}, MP:{$stats['magicka']}/{$stats['magicka_max']}, SP:{$stats['stamina']}/{$stats['stamina_max']})");
        }
    }
    $MUST_END=true;

}  elseif ($gameRequest[0] == "_questreset") {
    error_reporting(E_ALL);
    $db->delete("quests", "1=1");
    $MUST_END=true;


} elseif ($gameRequest[0] == "_speech") {
    error_reporting(E_ALL);
    $speech = json_decode($gameRequest[3], true);
   
    // error_log(print_r($speech,true));
    if (is_array($speech)) {
        $db->insert(
            'speech',
            array(
                'ts' => $gameRequest[1],
                'gamets' => $gameRequest[2],
                'listener' => $speech["listener"],
                'speaker' => $speech["speaker"],
                'speech' => $speech["speech"],
                'location' => $speech["location"],
                'companions'=>(isset($speech["companions"])&&is_array($speech["companions"]))?implode(",",$speech["companions"]):DataBeingsInCloseRange(),
                'sess' => 'pending',
                'audios' => isset($speech["audios"])?$speech["audios"]:null,
                'topic' => isset($speech["debug"])?$speech["debug"]:null,
                'localts' => time()
            )
        );
    } else {
        Logger::error(__FILE__." data was not an array");

    }
    $MUST_END=true;

} elseif ($gameRequest[0] == "book") {
    $db->insert(
        'books',
        array(
            'ts' => $gameRequest[1],
            'gamets' => $gameRequest[2],
            'title' => $gameRequest[3],
            'sess' => 'pending',
            'localts' => time()
        )
    );

    $db->insert(
        'eventlog',
        array(
            'ts' => $gameRequest[1],
            'gamets' => $gameRequest[2],
            'type' => $gameRequest[0],
            'data' => $gameRequest[3],
            'sess' => 'pending',
            'localts' => time()
        )
    );

    $MUST_END=true;

} elseif ($gameRequest[0] == "contentbook") {
    // This should be deprecated once version 1.2.0 is released
    $db->insert(
        'books',
        array(
            'ts' => $gameRequest[1],
            'gamets' => $gameRequest[2],
            'content' => strip_tags($gameRequest[3]),
            'sess' => 'pending',
            'localts' => time()
        )
    );

    $db->insert(
        'eventlog',
        array(
            'ts' => $gameRequest[1],
            'gamets' => $gameRequest[2],
            'type' => $gameRequest[0],
            'data' => $gameRequest[3],
            'sess' => 'pending',
            'localts' => time()
        )
    );

    $MUST_END=true;

} elseif ($gameRequest[0] == "togglemodel") {

    $newModel=DMtoggleModel();
    echo "{$GLOBALS["HERIKA_NAME"]}|command|ToggleModel@$newModel\r\n";
    while(@ob_end_flush());

    $db->insert(
        'eventlog',
        array(
            'ts' => $gameRequest[1],
            'gamets' => $gameRequest[2],
            'type' => "togglemodel",
            'data' => $newModel,
            'sess' => 'pending',
            'localts' => time()
        )
    );

    $MUST_END=true;

} elseif ($gameRequest[0] == "death") {

    $MUST_END=true;

} elseif ($gameRequest[0] == "quest") {
    //13333334
    if (($gameRequest[2]>13333334)||($gameRequest[2]<13333332)) {  // ?? How this works.
        
        if (strpos($gameRequest[3],'New quest ""')) {
          // plugin couldn't get quest name  
            $MUST_END=true;
        } else {
            logEvent($gameRequest);
            
        }
    } else
        $MUST_END=true;
    /*
    if (isset($GLOBALS["FEATURES"]["MISC"]["QUEST_COMMENT"]))
        if ($GLOBALS["FEATURES"]["MISC"]["QUEST_COMMENT"]===false)
            $MUST_END=true;
    */
    if (isset($GLOBALS["QUEST_COMMENT"])) {
        // Remove the '%' from the value and convert it to an integer
        $questCommentChance = (int)str_replace('%', '', $GLOBALS["QUEST_COMMENT_CHANCE"]);
    
        // Generate a random integer between 1 and 100 (inclusive).
        $randomChance = random_int(1, 100);
    
        // Adjust the logic to reverse the chance
        if ($randomChance > $questCommentChance || $GLOBALS["QUEST_COMMENT"] === false) {
            $MUST_END = true;
        }
    }
} elseif ($gameRequest[0] == "location") {
    $GLOBALS["CACHE_LOCATION"]=$gameRequest[3];
    logEvent($gameRequest);
    $MUST_END=true;

} elseif ($gameRequest[0] == "force_current_task") {
    $db->insert(
        'currentmission',
        array(
                'ts' => $gameRequest[1],
                'gamets' => $gameRequest[2],
                'description' => $gameRequest[3],
                'sess' => 'pending',
                'localts' => time()
            )
    );
    $MUST_END=true;

    
} elseif ($gameRequest[0] == "recover_last_task") {

    $db->delete("currentmission", "rowid=(select max(rowid) from currentmission)");

    $MUST_END=true;

    
} elseif ($gameRequest[0] == "just_say") {
    
    returnLines([trim($gameRequest[3])]);
    
    $MUST_END=true;
    
} elseif ($gameRequest[0] == "playerdied") {
    
    
    // Dragon Break autosnapshot: detect large rollback and snapshot before pruning
    try {
        $prevGamets = DataLastKnownGameTS();
        $incomingGamets = intval($gameRequest[2]);
        $snapshotId = dragon_break_snapshot_if_needed($prevGamets, $incomingGamets);
        if ($snapshotId > 0) {
            Logger::info("DragonBreak: Created snapshot id {$snapshotId} prior to death rollback prune");
        }
    } catch (Exception $e) {
        Logger::warn("DragonBreak: Snapshot attempt (playerdied) failed: ".$e->getMessage());
    }

    $lastSaveHistory=$db->fetchAll("select gamets from eventlog where type='infosave' order by ts desc limit 1 offset 0");
    if (isset($lastSaveHistory[0]["ts"])) {
        $lastSave=$lastSaveHistory[0]["ts"];
        
        $db->delete("eventlog", "gamets>$lastSave ");
        
        $db->delete("speech", "gamets>$lastSave  ");
        $db->delete("currentmission", "gamets>$lastSave  ");
        $db->delete("diarylog", "gamets>$lastSave  ");
        $db->delete("books", "gamets>$lastSave");

        if ($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) {
            $results = $db->query("select gamets_truncated,uid from memory_summary where gamets_truncated>$lastSave");
            while ($memoryRow = $db->fetchArray($results)) {
                deleteElement($memoryRow["uid"]);
            }
        }
        $db->delete("memory_summary", "gamets_truncated>$lastSave  ");
        $db->delete("memory", "gamets>$lastSave  ");

        //$db->delete("diarylogv2", "true");
        //$db->execQuery("insert into diarylogv2 select topic,content,tags,people,location from diarylog");
        //die(print_r($gameRequest,true));
        $db->update("responselog", "sent=0", "sent=1 and (action='AASPGDialogueHerika2Branch1Topic')");
        $db->insert(
            'eventlog',
            array(
                'ts' => $gameRequest[1],
                'gamets' => $gameRequest[2],
                'type' => $gameRequest[0],
                'data' => $gameRequest[3],
                'sess' => 'pending',
                'localts' => time()
            )
        );
    }
    
    
    $MUST_END=true;
    
} elseif ($gameRequest[0] == "setconf") {
    
    // logEvent($gameRequest);

    $vars=explode("@",$gameRequest[3]);
    if ($vars[0]=="chim_context_mode") {
        $cRw=$db->fetchOne("select value from conf_opts where id='{$vars[0]}'");
        $vars[1]=(isset($cRw["value"])&&$cRw["value"]=="1")?"0":"1";
        $GLOBALS["db"]->insert(
            'responselog',
                array(
                    'localts' => time(),
                    'sent' => 0,
                    'actor' => "rolemaster",
                    'text' => '',
                    'action' => "rolecommand|DebugNotification@Focus on Chat mode ".($vars[1]?"enabled":"disabled"),
                    'tag' => ""
                )
            );
    }

    $db->upsertRowOnConflict(
        'conf_opts',
        array(
            'id' => $vars[0],
            'value' => $vars[1]
        ),
        "id"
    );
    
    
    $MUST_END=true;
    
} elseif (strpos($gameRequest[0], "infosave")===0) {    // user saves. lets backup all NPC state.

    error_log("[INFOSAVE] Backup all profiles");
    
    $npcMaster=new NpcMaster();
    $npcMaster->backupAllNpcs($gameRequest[2]);
    $MUST_END=true;
    
} elseif (strpos($gameRequest[0], "info")===0) {    // info_whatever requests

    logEvent($gameRequest);

    $MUST_END=true;

    
} elseif (strpos($gameRequest[0], "addnpc")===0) {    // addnpc 
    logEvent($gameRequest);
    
    $splitNameBase=explode("@",$gameRequest[3]);
    if (sizeof($splitNameBase)>1) {
        $localName=$splitNameBase[0];
        $baseProfile=$splitNameBase[1];
    } else {
        $localName=$splitNameBase[0];
        $baseProfile="";
    }

    if ($localName==$baseProfile)
        $baseProfile="";

    if (!profile_exists($localName))
        AddFirstTimeMet($localName, $momentum, $gameRequest[2],$gameRequest[1]);

    
    createProfile($localName,[],false,$baseProfile);
    audit_log("comm.php addnpc $localName");

    // Update new data
    $npcMaster=new NpcMaster();
    $currentNpcData=$npcMaster->getByName($localName);
    if ($currentNpcData) {
        $currentNpcData["base"]=$splitNameBase[1];
        $currentNpcData["gender"]=$splitNameBase[2];
        $currentNpcData["race"]=$splitNameBase[3];
        $currentNpcData["refid"]=$splitNameBase[4];
        

        $meta=$npcMaster->getMetadata($currentNpcData);
        // NPC skills
        $meta["skills"]["archery"]=$splitNameBase[5];
        $meta["skills"]["block"]=$splitNameBase[6];
        $meta["skills"]["onehanded"]=$splitNameBase[7];
        $meta["skills"]["twohanded"]=$splitNameBase[8];
        $meta["skills"]["conjuration"]=$splitNameBase[9];
        $meta["skills"]["destruction"]=$splitNameBase[10];
        $meta["skills"]["restoration"]=$splitNameBase[11];
        $meta["skills"]["alteration"]=$splitNameBase[12];
        $meta["skills"]["illusion"]=$splitNameBase[13];
        $meta["skills"]["heavyarmor"]=$splitNameBase[14];
        $meta["skills"]["lightarmor"]=$splitNameBase[15];
        $meta["skills"]["lockpicking"]=$splitNameBase[16];
        $meta["skills"]["pickpocket"]=$splitNameBase[17];
        $meta["skills"]["sneak"]=$splitNameBase[18];
        $meta["skills"]["speech"]=$splitNameBase[19];
        $meta["skills"]["smithing"]=$splitNameBase[20];
        $meta["skills"]["alchemy"]=$splitNameBase[21];
        $meta["skills"]["enchanting"]=$splitNameBase[22];
        
        // NPC equipment (8 slots from Skyrim)
        $meta["equipment"]["helmet"]=isset($splitNameBase[23]) ? $splitNameBase[23] : '';
        $meta["equipment"]["armor"]=isset($splitNameBase[24]) ? $splitNameBase[24] : '';
        $meta["equipment"]["boots"]=isset($splitNameBase[25]) ? $splitNameBase[25] : '';
        $meta["equipment"]["gloves"]=isset($splitNameBase[26]) ? $splitNameBase[26] : '';
        $meta["equipment"]["amulet"]=isset($splitNameBase[27]) ? $splitNameBase[27] : '';
        $meta["equipment"]["ring"]=isset($splitNameBase[28]) ? $splitNameBase[28] : '';
        $meta["equipment"]["left_hand"]=isset($splitNameBase[29]) ? $splitNameBase[29] : '';
        $meta["equipment"]["right_hand"]=isset($splitNameBase[30]) ? $splitNameBase[30] : '';
        
        // NPC stats (core attributes)
        $meta["stats"]["level"]=isset($splitNameBase[31]) ? intval($splitNameBase[31]) : 1;
        $meta["stats"]["health"]=isset($splitNameBase[32]) ? floatval($splitNameBase[32]) : 0;
        $meta["stats"]["health_max"]=isset($splitNameBase[33]) ? floatval($splitNameBase[33]) : 0;
        $meta["stats"]["magicka"]=isset($splitNameBase[34]) ? floatval($splitNameBase[34]) : 0;
        $meta["stats"]["magicka_max"]=isset($splitNameBase[35]) ? floatval($splitNameBase[35]) : 0;
        $meta["stats"]["stamina"]=isset($splitNameBase[36]) ? floatval($splitNameBase[36]) : 0;
        $meta["stats"]["stamina_max"]=isset($splitNameBase[37]) ? floatval($splitNameBase[37]) : 0;

        $meta["mods"]=isset($splitNameBase[38]) ?explode("#",$splitNameBase[38]):null;

       
        // Importing rules
        $npcName = $GLOBALS["db"]->escape($localName);
        $npcRace = $GLOBALS["db"]->escape($currentNpcData["race"]);
        $npcGender = $GLOBALS["db"]->escape($currentNpcData["gender"]);
        $npcBase = $GLOBALS["db"]->escape($currentNpcData["base"]);
        $npcMods = $meta["mods"]; 

        if (is_array($npcMods)) {
            $modsArray = "ARRAY['" . implode("','", array_map([$GLOBALS["db"], 'escape'], $npcMods)) . "']";
        } else {
            $modsArray = "ARRAY['']";
        }

        $sql = "
            SELECT *
            FROM import_rules r
            WHERE r.enabled = TRUE
            AND (r.match_name IS NULL OR '$npcName' ~ r.match_name)
            AND (r.match_race IS NULL OR '$npcRace' ~ r.match_race)
            AND (r.match_gender IS NULL OR '$npcGender' = r.match_gender)
            AND (r.match_base IS NULL OR '$npcBase' ~ r.match_base)
            AND (r.match_mods IS NULL OR r.match_mods <@ $modsArray)
            ORDER BY r.priority DESC
        ";


        $rules = $db->fetchAll($sql);
        error_log("[ADDNPC IMPORTING RULES] Matching rules for $npcName: ".sizeof($rules));
        foreach ($rules as $rule) {


            if (!empty($rule["profile"])) {
                $currentNpcData["profile_id"] = (int)$rule["profile"];
            }


            if (!empty($rule["action"])) {
                $actions = json_decode($rule["action"], true);
                if (is_array($actions)) {
                    foreach ($actions as $key=>$value) {
                        error_log("[ADDNPC IMPORTING RULES] Matching rules for $npcName: {$key}:".print_r($value,true));
                        // ejemplo: guardar en $currentNpcData["properties"]
                        if ($key=="metadata")
                            $meta=array_merge($meta,$value);
                        else
                            $currentNpcData[$key] = $value;
                    
                    }
                }
            }
        }

        $currentNpcData=$npcMaster->setMetadata($currentNpcData,$meta);

        $npcMaster->updateByArray($currentNpcData);
        
        
    }

    $MUST_END=true;
    
    
} elseif (strpos($gameRequest[0], "util_location_name")===0) {    // addnpc 
    
    
    $splitNameBase=explode("/",$gameRequest[3]);
    if ($splitNameBase[0] && $splitNameBase[1]) {
        $db->insert(
            'locations',
            array(
                'name' => $splitNameBase[0],
                'formid' => $splitNameBase[1]
            )
        );
    }

    $MUST_END=true;
    
    
} elseif (strpos($gameRequest[0], "updateprofiles_batch_async")===0) {
    
    // Async batch processing for timer-based dynamic profile updates
    // Format: updateprofiles_batch_async|timestamp|gamestamp|NPC1,NPC2,NPC3,NPC4
    
    if (!isset($gameRequest[3]) || empty($gameRequest[3])) {
        Logger::debug("updateprofiles_batch_async: No NPCs provided");
        die();
    }
    
    $npcList = explode(',', $gameRequest[3]);
    $enabledNPCs = [];
    
    Logger::info("updateprofiles_batch_async: Checking " . count($npcList) . " NPCs for enabled dynamic profiles");
    
    // First pass: quickly check which NPCs have DYNAMIC_PROFILE enabled
    foreach ($npcList as $npcName) {
        $npcName = trim($npcName);
        if (empty($npcName)) continue;
        
        // Skip The Narrator
        if ($npcName === "The Narrator") {
            continue;
        }
        
        // Check if profile exists for this NPC
        $npcMaster=new NpcMaster();
        $npcData=$npcMaster->getByName($npcName);
        if (!$npcData) {
            continue;
        }
        
        // Check if DYNAMIC_PROFILE is enabled for this NPC
        $isDynamicEnabled = $npcData["dynamic_profile"] ?? $GLOBALS["DYNAMIC_PROFILE"] ?? false;

        if ($isDynamicEnabled) {
            $enabledNPCs[] = $npcName;
        }
    }
    
    $enabledCount = count($enabledNPCs);
    
    // Send immediate ACK message back to plugin with count - ONLY notification we send
    if ($enabledCount > 0) {
        echo "The Narrator|rolecommand|DebugNotification@Updating $enabledCount dynamic profile" . ($enabledCount == 1 ? "" : "s") . "..." . PHP_EOL;
        Logger::info("updateprofiles_batch_async: Will update $enabledCount profiles in background: " . implode(', ', $enabledNPCs));
    } else {
        Logger::info("updateprofiles_batch_async: No profiles to update - none had DYNAMIC_PROFILE enabled");
    }
    
    @ob_flush();
    @flush();
    
    // Process in background if we have enabled NPCs
    if ($enabledCount > 0) {
        // Try to fork process for background processing
        if (function_exists('pcntl_fork')) {
            $pid = pcntl_fork();
            if ($pid == 0) {
                // Child process - do the background work
                Logger::info("updateprofiles_batch_async: Child process started for background processing");
                
                $successCount = 0;
                foreach ($enabledNPCs as $npcName) {
                    try {
                        if (processSingleDynamicProfile($npcName, $gameRequest)) {
                            $successCount++;
                        }
                    } catch (Exception $e) {
                        Logger::error("updateprofiles_batch_async: Error processing profile for $npcName: " . $e->getMessage());
                    }
                }
                
                Logger::info("updateprofiles_batch_async: Background processing completed. Updated $successCount of $enabledCount profiles");
                exit(0);
            } elseif ($pid > 0) {
                // Parent process - continue normally
                Logger::info("updateprofiles_batch_async: Forked background process with PID $pid");
            } else {
                // Fork failed - fall back to database queue method
                Logger::warn("updateprofiles_batch_async: Fork failed, using database queue fallback");
                $queueData = [
                    'timestamp' => time(),
                    'npcs' => $enabledNPCs,
                    'gameRequest' => $gameRequest
                ];
                $queueId = 'dynamic_profiles_queue_' . time() . '_' . uniqid();
                
                try {
                    $db->upsertRowOnConflict('conf_opts', array(
                        'id' => $queueId,
                        'value' => json_encode($queueData)
                    ), 'id');
                    Logger::info("updateprofiles_batch_async: Queued $enabledCount profiles for background processing in database");
                } catch (Exception $e) {
                    Logger::error("updateprofiles_batch_async: Failed to write to database queue: " . $e->getMessage());
                }
            }
        } else {
            // No fork available - use database queue method
            Logger::info("updateprofiles_batch_async: pcntl_fork not available, using database queue method");
            $queueData = [
                'timestamp' => time(),
                'npcs' => $enabledNPCs,
                'gameRequest' => $gameRequest
            ];
            $queueId = 'dynamic_profiles_queue_' . time() . '_' . uniqid();
            
            try {
                $db->upsertRowOnConflict('conf_opts', array(
                    'id' => $queueId,
                    'value' => json_encode($queueData)
                ), 'id');
                Logger::info("updateprofiles_batch_async: Queued $enabledCount profiles for background processing in database");
            } catch (Exception $e) {
                Logger::error("updateprofiles_batch_async: Failed to write to database queue: " . $e->getMessage());
            }
        }
        
        // Trigger immediate background processing
        close();
        triggerImmediateProfileProcessing();
    }
    
    terminate();
    //die("X-CUSTOM-CLOSE");
    
} elseif (strpos($gameRequest[0], "updateprofile")===0) {    
    
    // Legacy single profile update (kept for backwards compatibility)
    // Check if DYNAMIC_PROFILE is enabled globally in default profile
    // Load default profile to check the global setting
    $defaultProfilePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "conf" . DIRECTORY_SEPARATOR . "conf.php";
    $globalDynamicProfile = false;
    if (file_exists($defaultProfilePath)) {
        // Capture current variables to restore later
        $originalVars = get_defined_vars();
        include($defaultProfilePath);
        $globalDynamicProfile = isset($DYNAMIC_PROFILE) ? $DYNAMIC_PROFILE : false;
        // Clean up any variables that might have been set by the include
        foreach (get_defined_vars() as $key => $value) {
            if (!array_key_exists($key, $originalVars) && $key !== 'globalDynamicProfile') {
                unset($$key);
            }
        }
    }
    
    // If dynamic profiles are disabled globally, silently ignore the request without logging
    if (!$globalDynamicProfile) {
        Logger::debug("DYNAMIC_PROFILE is disabled globally, ignoring updateprofile request for {$GLOBALS["HERIKA_NAME"]}");
        die();
    }
    
    // Check if DYNAMIC_PROFILE is enabled for this specific NPC profile
    if (!$GLOBALS["DYNAMIC_PROFILE"]) {
        $gameRequest[3]="Dynamic profile updating disabled for {$GLOBALS["HERIKA_NAME"]}";
        
        logEvent($gameRequest);
        die();
    }
    
    
    if (!isset($GLOBALS["CONNECTORS_DIARY"]) || !file_exists(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CONNECTORS_DIARY"]}.php")) {
            ;
	}
	 else {
		require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CONNECTORS_DIARY"]}.php");
        
        $historyData="";
        $lastPlace="";
        $lastListener="";
        $lastDateTime = "";

        // Determine how much context history to use for dynamic profiles
        $dynamicProfileContextHistory = 50; // Default value
        if (isset($GLOBALS["CONTEXT_HISTORY_DYNAMIC_PROFILE"]) && $GLOBALS["CONTEXT_HISTORY_DYNAMIC_PROFILE"] > 0) {
            $dynamicProfileContextHistory = $GLOBALS["CONTEXT_HISTORY_DYNAMIC_PROFILE"];
        } elseif (isset($GLOBALS["CONTEXT_HISTORY"]) && $GLOBALS["CONTEXT_HISTORY"] > 0) {
            $dynamicProfileContextHistory = $GLOBALS["CONTEXT_HISTORY"];
        }
        
        foreach (json_decode(DataSpeechJournal($GLOBALS["HERIKA_NAME"], $dynamicProfileContextHistory),true) as $element) {
          if ($element["listener"]=="The Narrator") {
                continue;
          }
          if ($lastListener!=$element["listener"]) {
            
            $listener=" (talking to {$element["listener"]})";
            $lastListener=$element["listener"];
          }
          else
            $listener="";
      
          if ($lastPlace!=$element["location"]){
            $place=" (at {$element["location"]})";
            $lastPlace=$element["location"];
          }
          else
            $place="";

            if ($lastDateTime != substr($element["sk_date"], 0, 15)) {
                $date = substr($element["sk_date"], 0, 10);
                $time = substr($element["sk_date"], 11);
                $dateTime = "(on date {$date} at {$time})";
                $lastDateTime = substr($element["sk_date"], 0, 15); 
            } else {
                $dateTime = "";
            }
      
          $historyData.=trim("{$element["speaker"]}:".trim($element["speech"])." $listener $place $dateTime").PHP_EOL;
          
        }
        
        $partyConf=DataGetCurrentPartyConf();
		$partyConfA=json_decode($partyConf,true);
		Logger::debug($partyConf);
		// Use the global DYNAMIC_PROMPT
        $updateProfilePrompt = $GLOBALS["DYNAMIC_PROMPT"];
		// Database Prompt (Dynamic Profile Head)    
		$head[]   = ["role"	=> "system", "content"	=> "You are an assistant. Analyze this dialogue for {$GLOBALS["HERIKA_NAME"]} and then update the profile for {$GLOBALS["HERIKA_NAME"]} based on the information provided. " ];
		$prompt[] = ["role"	=> "user", "content"	=> "* Dialogue history:\n" .$historyData ];
		// Use centralized function from data_functions.php
		// Log the dynamic profile update event
        $gameRequest[0] = 'updateprofile';
        logEvent($gameRequest);
        // Re-fetch the dynamic profile after logging
        $currentDynamicProfile = buildDynamicProfileDisplay();
        $prompt[] = ["role" => "user", "content" => "Character to update:"  . $GLOBALS["HERIKA_NAME"] . "\nCharacter biography information:\n" . $GLOBALS["HERIKA_PERS"] . "\n" ."Character dynamic biography (this is what you are updating):\n" . $currentDynamicProfile];
		$prompt[] = ["role"=> "user", "content"	=> $updateProfilePrompt, ];
		$contextData       = array_merge($head, $prompt);
        $connectionHandler = new $GLOBALS["CONNECTORS_DIARY"];
        // Prefer connector-configured max_tokens for diary; then legacy memory; else default
        $maxTokens = null;
        if (isset($GLOBALS["CONNECTOR"][DMgetCurrentModel()]["max_tokens"])) {
            $maxTokens = (int)$GLOBALS["CONNECTOR"][DMgetCurrentModel()]["max_tokens"];
        } elseif (isset($GLOBALS["CONNECTOR"][DMgetCurrentModel()]["MAX_TOKENS_MEMORY"])) {
            $maxTokens = (int)$GLOBALS["CONNECTOR"][DMgetCurrentModel()]["MAX_TOKENS_MEMORY"];
        } else {
            $maxTokens = 2048;
        }
        $connectionHandler->open($contextData, ["MAX_TOKENS"=>$maxTokens]);
		$buffer      = "";
		$totalBuffer = "";
		$breakFlag   = false;
		while (true) {
			
			if ($breakFlag) {
				break;
			}
			
			if ($connectionHandler->isDone()) {
				$breakFlag = true;
			}
			
			$buffer.= $connectionHandler->process();
			$totalBuffer.= $buffer;
			//$bugBuffer[]=$buffer;
			
			
		}
		$connectionHandler->close();
		
		$actions = $connectionHandler->processActions();
		
		
		$responseParsed["HERIKA_DYNAMIC"]=$buffer;
        
        $newConfFile=$_GET["profile"];

                
        $gameRequest[3]="{$GLOBALS["HERIKA_NAME"]} / conf_$newConfFile ";
        logEvent($gameRequest);

        $path = dirname((__FILE__)) . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
        
        if (!file_exists($path . "conf".DIRECTORY_SEPARATOR."conf_$newConfFile.php") ) { 
            
            
        } else {
            
            // Do customizations here
            $newFile=$path . "conf".DIRECTORY_SEPARATOR."conf_$newConfFile.php";
            copy($path . "conf".DIRECTORY_SEPARATOR."conf_$newConfFile.php",$path . "conf".DIRECTORY_SEPARATOR.".conf_{$newConfFile}_".time().".php");

            $backup=file_get_contents($path . "conf".DIRECTORY_SEPARATOR."conf_$newConfFile.php");

            $backupFmtd=$db->escape($backup);

            $db->insert(
                'npc_profile_backup',
                array(
                        'name' => $db->escape($GLOBALS["HERIKA_NAME"]),
                        'data' => $backupFmtd
                )
            );

            $file_lines = file($newFile);

            for ($i = count($file_lines) - 1; $i >= 0; $i--) {
                // If the line is not empty, break the loop // Will remove first entry 
                if (trim($file_lines[$i]) !== '') {
                    unset($file_lines[$i]);
                    break;
                }
                unset($file_lines[$i]);
            }

            if(array_key_exists("CustomUpdateProfileFunction", $GLOBALS) && is_callable($GLOBALS["CustomUpdateProfileFunction"])) {
                $responseParsed["HERIKA_DYNAMIC"] = $GLOBALS["CustomUpdateProfileFunction"]($buffer);
            }

            file_put_contents($newFile, implode('', $file_lines));
            
            // Sanitize AI-generated dynamic content to prevent PHP syntax errors
            $dynamicContent = $responseParsed["HERIKA_DYNAMIC"];
            if (is_string($dynamicContent)) {
                $dynamicContent = str_replace("\0", '', $dynamicContent); // Remove null bytes
                $dynamicContent = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $dynamicContent); // Remove control chars
                if (!mb_check_encoding($dynamicContent, 'UTF-8')) {
                    $dynamicContent = mb_convert_encoding($dynamicContent, 'UTF-8', 'UTF-8'); // Fix encoding
                }
                if (strlen($dynamicContent) > 50000) {
                    $dynamicContent = substr($dynamicContent, 0, 50000) . '... [truncated]'; // Limit length
                }
                $dynamicContent = str_replace(['<?php', '<?', '?>'], ['&lt;?php', '&lt;?', '?&gt;'], $dynamicContent); // Escape PHP tags
                
                // Additional sanitization for var_export compatibility
                $dynamicContent = str_replace('\\', '\\\\', $dynamicContent); // Escape backslashes
                $dynamicContent = str_replace("\r\n", "\n", $dynamicContent); // Normalize line endings
                $dynamicContent = str_replace("\r", "\n", $dynamicContent); // Convert Mac line endings
                $dynamicContent = preg_replace('/\n{3,}/', "\n\n", $dynamicContent); // Limit consecutive newlines
                
                $escapedDynamic = var_export($dynamicContent, true);
            } else {
                $escapedDynamic = var_export('', true);
            }
            
            if (!$escapedDynamic) {
                $escapedDynamic = var_export('', true);
            }
            file_put_contents($newFile, PHP_EOL.'$HERIKA_DYNAMIC='.$escapedDynamic.';'.PHP_EOL, FILE_APPEND | LOCK_EX);
            file_put_contents($newFile, '?>'.PHP_EOL, FILE_APPEND | LOCK_EX);
            
        }
    
        //print_r($contextData);
        //print_r($responseParsed["HERIKA_DYNAMIC"]);
        $MUST_END=true;
    
    }
} elseif (strpos($gameRequest[0], "waitstart")===0) {    // addnpc 
    
    
    if (isset($gameRequest[3]) && $gameRequest[3]) {
        $db->upsertRowOnConflict(
            'conf_opts',
            array(
                'id' => "last_waitstart",
                'value' =>$gameRequest[2]
            ),
            "id"
        );
    }
    
    // AUTO_DIARY functionality - trigger diary entries for all current followers
    if (isset($GLOBALS["AUTO_DIARY"]) && $GLOBALS["AUTO_DIARY"]) {
        // Check if AUTO_DIARY_WAIT is enabled for wait events
        if (isset($GLOBALS["AUTO_DIARY_WAIT"]) && $GLOBALS["AUTO_DIARY_WAIT"]) {
            processAutoDiary($gameRequest, "waitstart");
        }
    }
    
    $MUST_END=true;
    
    
} elseif (strpos($gameRequest[0], "goodnight")===0) {    // goodnight event
    
    // Log the goodnight event
    $db->insert(
        'eventlog',
        array(
            'ts' => $gameRequest[1],
            'gamets' => $gameRequest[2],
            'type' => $gameRequest[0],
            'data' => isset($gameRequest[3]) ? $gameRequest[3] : '',
            'sess' => 'pending',
            'localts' => time()
        )
    );
    
    // AUTO_DIARY functionality - trigger diary entries for all current followers
    if (isset($GLOBALS["AUTO_DIARY"]) && $GLOBALS["AUTO_DIARY"]) {
        processAutoDiary($gameRequest, "goodnight");
    }
    
    $MUST_END=true;
    
    
} elseif (strpos($gameRequest[0], "waitstop")===0) {    // addnpc 
    
    $lastgameTs=$db->fetchOne("select value from conf_opts where id='last_waitstart'");
    
    $elapsed=($gameRequest[2]-$lastgameTs["value"])* 0.0000024;
    $db->insert(
        'eventlog',
        array(
            'ts' => $gameRequest[1],
            'gamets' => $gameRequest[2],
            'type' => "info_timeforward",
            'data' => "$elapsed hours have passed. Current date/time: ".convert_gamets2skyrim_long_date($gameRequest[2]),
            'sess' => 'pending',
            'localts' => time()
        )
    );

    $MUST_END=true;
    
    
} elseif (strpos($gameRequest[0], "diary_nearby")===0) {    // diary_nearby event - manual trigger for all NPCs in range
    
    // Process diary entries for all nearby NPCs (not just followers)
    processNearbyDiary($gameRequest, "manual_nearby");
    
    $MUST_END=true;
    
    
} elseif (strpos($gameRequest[0], "core_profile_assign")===0) {    // diary_nearby event - manual trigger for all NPCs in range
    
    logEvent($gameRequest);

    if (isset($_GET["profile"])) {
        $npcMaster=new NpcMaster();
        $currentNpcData=$npcMaster->getByMD5($_GET["profile"]);
        
        if (is_array($currentNpcData)) {
            $currentNpcData["profile_id"]=$gameRequest[3];
            $npcMaster->updateByArray($currentNpcData);
            
        } else {
            error_log("[CORE SYSTEM] No valid NPC found {$_GET["profile"]}");
        }
    } else {
        error_log("[CORE SYSTEM] No valid profile specified");
    }
    
    $MUST_END=true;
    
    
} elseif (strpos($gameRequest[0], "switchrace")===0) {    // diary_nearby event - manual trigger for all NPCs in range
    
    logEvent($gameRequest);
    
    $MUST_END=true;
    
    
} 

?>