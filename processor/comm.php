<?php
require_once($GLOBALS["ENGINE_PATH"]."/lib/dynamic_update_util.php");
require_once($GLOBALS["ENGINE_PATH"]."/lib/utils_game_timestamp.php");
require_once($GLOBALS["ENGINE_PATH"]."/lib/playthrough_snapshot.php");

$MUST_END=false;

$gameRequest[3] = @mb_convert_encoding($gameRequest[3], 'UTF-8', 'UTF-8');

if ($gameRequest[0] == "init") { // Reset responses if init sent (Think about this)
    // avoid a rare case where skyrim briefly reverts to level 1 Prisoner during load
    // Moved Dynamic Updates functions here
    if ($gameRequest[2] == "10000000") {
        Logger::warn("Ignoring init with a gamets of 10000000.");
        $MUST_END=true;
        return;
    }
    $now=time();

    error_log("[INIT] Should delete everthing after {$gameRequest[2]}");
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
    $db->delete("diarylog", "localts>=$now ");
    $db->delete("books", "gamets>=0{$gameRequest[2]}  ");
    $db->delete("books", "localts>$now ");
    $db->delete("responselog", " 1=1 ");
    $db->delete("rolemaster", " 1=1 ");
    $db->delete("actions_issued", "gamets>={$gameRequest[2]}  ");
    $db->delete("moods_issued", "gamets>={$gameRequest[2]}  ");
    $db->delete("rumors", "gamets>={$gameRequest[2]}  ");
    $db->delete("named_cell", "gamets>={$gameRequest[2]}  ");
    $db->delete("named_cell", "gamets<=({$gameRequest[2]} - 30000000) "); //((24 * 3) / 0.0000024)
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
    
    /* Restore NPCs state */
      
    $npcMaster=new NpcMaster();
    $npcMaster->restoreNPC($gameRequest[2]);
    Logger::trace("POST INIT PROCESSING ".(time()-$now));
    
    // RELATIONSHIP SYSTEM: Clear async queues on game load (Paradox Prevention)
    // Stale evaluations from a previous session could corrupt the restored state
    try {
        $db->execQuery("DELETE FROM relationship_eval_queue WHERE 1=1");
        $db->execQuery("DELETE FROM relationship_init_queue WHERE 1=1");
        error_log("[INIT] Cleared relationship async queues for paradox prevention");
    } catch (Exception $e) {
        // Tables may not exist yet - that's fine
    }

    require_once __DIR__ . "/../service/processors/snqe/lib/snqe.class.php";
    SNQEQuestManager::load_quests($gameRequest[2]);
    
    // Narrator Welcome Message on Load
    try {
        require_once(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "narrator.class.php");
        $narrator = new Narrator();
        
        // Check if narrator is enabled and welcome message is enabled
        if ($narrator->getBool('enabled', true) && $narrator->getBool('welcome_enabled', false)) {
            // Get cooldown from narrator settings (in minutes, default 10)
            $cooldownMinutes = $narrator->getInt('welcome_cooldown', 10);
            $cooldownSeconds = $cooldownMinutes * 60;
            
            // Check cooldown
            $lastWelcomeTs = $db->fetchOne("SELECT value FROM conf_opts WHERE id='last_narrator_welcome'");
            $currentTime = time();
            
            $canTrigger = true;
            if ($lastWelcomeTs && isset($lastWelcomeTs['value'])) {
                $timeSinceLastWelcome = $currentTime - intval($lastWelcomeTs['value']);
                if ($timeSinceLastWelcome < $cooldownSeconds) {
                    $canTrigger = false;
                    Logger::debug("Narrator welcome message on cooldown. {$timeSinceLastWelcome}s since last, need {$cooldownSeconds}s");
                }
            }
            
            if ($canTrigger) {
                // Queue the event in eventlog so it shows up in context
                $db->insert(
                    'eventlog',
                    array(
                        'ts' => $gameRequest[1],
                        'gamets' => $gameRequest[2],
                        'type' => 'narrator_welcome',
                        'data' => 'Narrator welcome message triggered on game load',
                        'sess' => 'complete', // Mark as complete so it doesn't get processed again
                        'localts' => $currentTime
                    )
                );
                
                // Update last welcome timestamp
                $db->upsertRowOnConflict(
                    'conf_opts',
                    array(
                        'id' => 'last_narrator_welcome',
                        'value' => (string)$currentTime
                    ),
                    'id'
                );
                
                // Store flag to trigger narrator after init processing
                $GLOBALS["TRIGGER_NARRATOR_WELCOME"] = true;
                
                Logger::info("Narrator welcome message will be triggered");
            }
        }
    } catch (Exception $e) {
        Logger::warn("Could not trigger narrator welcome message: " . $e->getMessage());
    }
    
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
    $responseDataMl = DataDequeue(time()-1);// Allow responses queued up to 1 second in the future
    foreach ($responseDataMl as $responseData) {
        echo "{$responseData["actor"]}|{$responseData["action"]}|{$responseData["text"]}\r\n";
    }
    
    if (time()%5==0) {
        logEvent($gameRequest);
    }
    
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
    // DEPRECATED: Equipment updates now handled by gamedata.php with JSON POST
    Logger::warn("[DEPRECATED] updateequipment event - use gamedata.php endpoint instead");
    $MUST_END=true;

} elseif ($gameRequest[0] == "updateinventory") {
    // DEPRECATED: Inventory updates now handled by gamedata.php with JSON POST
    Logger::warn("[DEPRECATED] updateinventory event - use gamedata.php endpoint instead");
    $MUST_END=true;

} elseif ($gameRequest[0] == "itemtransfer") {
    // Item transfer event: update both source and destination NPC inventories
    Logger::info("RECEIVED itemtransfer command: ".$gameRequest[3]);
    
    // Parse: "SourceNPC gave ItemCount ItemName to DestNPC"
    $message = $gameRequest[3];
    
    // Extract source NPC, destination NPC, item name and count
    // Format: "Lydia gave 2 Health Potion to Faendal"
    if (preg_match('/^(.+?) gave (\d+) (.+?) to (.+)$/', $message, $matches)) {
        $sourceNpcName = trim($matches[1]);
        $itemCount = intval($matches[2]);
        $itemName = trim($matches[3]);
        $destNpcName = trim($matches[4]);
        
        Logger::info("Item transfer: {$sourceNpcName} -> {$destNpcName}: {$itemCount}x {$itemName}");
        
        // Update source NPC inventory (decrement)
        $sourceNpc = $npcMaster->getByName($sourceNpcName);
        if ($sourceNpc) {
            $sourceMeta = json_decode($sourceNpc['metadata'] ?? '{}', true);
            if (!is_array($sourceMeta)) $sourceMeta = [];
            
            if (isset($sourceMeta['inventory']) && is_array($sourceMeta['inventory'])) {
                foreach ($sourceMeta['inventory'] as $key => $item) {
                    if ($item['name'] === $itemName) {
                        $sourceMeta['inventory'][$key]['count'] -= $itemCount;
                        if ($sourceMeta['inventory'][$key]['count'] <= 0) {
                            unset($sourceMeta['inventory'][$key]);
                        }
                        break;
                    }
                }
                $sourceMeta['inventory'] = array_values($sourceMeta['inventory']); // Re-index
                $sourceNpc = $npcMaster->setMetadata($sourceNpc, $sourceMeta);
                $npcMaster->updateByArray($sourceNpc);
                Logger::info("Updated {$sourceNpcName} inventory (removed {$itemCount}x {$itemName})");
            }
        }
        
        // Update destination NPC inventory (increment)
        $destNpc = $npcMaster->getByName($destNpcName);
        if ($destNpc) {
            $destMeta = json_decode($destNpc['metadata'] ?? '{}', true);
            if (!is_array($destMeta)) $destMeta = [];
            
            if (!isset($destMeta['inventory'])) $destMeta['inventory'] = [];
            
            // Check if item already exists
            $itemExists = false;
            foreach ($destMeta['inventory'] as $key => $item) {
                if ($item['name'] === $itemName) {
                    $destMeta['inventory'][$key]['count'] += $itemCount;
                    $itemExists = true;
                    break;
                }
            }
            
            // Add new item if it doesn't exist
            if (!$itemExists) {
                $destMeta['inventory'][] = ['name' => $itemName, 'count' => $itemCount];
            }
            
            $destMeta['inventory_updated'] = time();
            $destNpc = $npcMaster->setMetadata($destNpc, $destMeta);
            $npcMaster->updateByArray($destNpc);
            Logger::info("Updated {$destNpcName} inventory (added {$itemCount}x {$itemName})");
        }
    } else {
        Logger::warn("itemtransfer: Could not parse message format: {$message}");
    }
    
    $MUST_END=true;

} elseif ($gameRequest[0] == "updateskills") {
    // DEPRECATED: Skills updates now handled by gamedata.php with JSON POST
    Logger::warn("[DEPRECATED] updateskills event - use gamedata.php endpoint instead");
    $MUST_END=true;

} elseif ($gameRequest[0] == "updatestats") {
    // Live stats update (combat-aware, every 3s in combat or on hit)
    $updateData = explode("@",$gameRequest[3]);
    
    if (!empty($updateData[0])) {
        $npcName = $updateData[0];
        
        // Stats (level, health, magicka, stamina with current/max, and scale)
        $stats = [
            'level' => isset($updateData[1]) ? intval($updateData[1]) : 1,
            'health' => isset($updateData[2]) ? floatval($updateData[2]) : 0,
            'health_max' => isset($updateData[3]) ? floatval($updateData[3]) : 0,
            'magicka' => isset($updateData[4]) ? floatval($updateData[4]) : 0,
            'magicka_max' => isset($updateData[5]) ? floatval($updateData[5]) : 0,
            'stamina' => isset($updateData[6]) ? floatval($updateData[6]) : 0,
            'stamina_max' => isset($updateData[7]) ? floatval($updateData[7]) : 0,
            'scale' => isset($updateData[8]) ? floatval($updateData[8]) : 1.0
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
        if (isset($speech["companions"])&&!empty($speech["companions"])&&is_array($speech["companions"])) {
            // Ensure companion field has same format as DataBeingsInCloseRange
            $companionsReformatStr="|".(implode("|",$speech["companions"]))."|";
        }
        
        // Store distance for shouting detection
        $distance = isset($speech["distance"]) ? floatval($speech["distance"]) : 0.0;
        
        // Store distance globally for context building
        $GLOBALS["LAST_SPEECH_DISTANCE"] = $distance;
        
        $db->insert(
            'speech',
            array(
                'ts' => $gameRequest[1],
                'gamets' => $gameRequest[2],
                'listener' => $speech["listener"],
                'speaker' => $speech["speaker"],
                'speech' => $speech["speech"],
                'location' => $speech["location"],
                'companions'=>(isset($companionsReformatStr))?$companionsReformatStr:DataBeingsInCloseRange(),
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
        } else if (stripos($gameRequest[3],'Storyline Tracker')!==false) {
            // AIAgent quests - ignore
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
    // Check if quest comments are enabled for narrator
    try {
        require_once(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "narrator.class.php");
        $narrator = new Narrator();
        
        if ($narrator->getBool('enabled', true) && $narrator->getBool('quest_comment_enabled', false)) {
            $questCommentChance = $narrator->getInt('quest_comment_chance', 10);
            $randomChance = random_int(1, 100);
            
            if ($randomChance > $questCommentChance) {
                $MUST_END = true;
            } else {
                // Chance check passed, now check cooldown
                $cooldownMinutes = $narrator->getInt('quest_comment_cooldown', 3);
                $cooldownSeconds = $cooldownMinutes * 60;
                
                // Fetch last quest comment timestamp
                $lastQuestCommentTs = $db->fetchOne("SELECT value FROM conf_opts WHERE id='QUEST_COMMENT_LAST_TIMESTAMP'");
                $currentTime = time();
                
                $canTrigger = true;
                if ($lastQuestCommentTs && isset($lastQuestCommentTs['value'])) {
                    $timeSinceLastComment = $currentTime - intval($lastQuestCommentTs['value']);
                    if ($timeSinceLastComment < $cooldownSeconds) {
                        $canTrigger = false;
                        Logger::info("Quest comment on cooldown. {$timeSinceLastComment}s since last, need {$cooldownSeconds}s");
                    }
                }
                
                if (!$canTrigger) {
                    $MUST_END = true;
                } else {
                    // Queue the event in eventlog so it shows up in context
                    $db->insert(
                        'eventlog',
                        array(
                            'ts' => $gameRequest[1],
                            'gamets' => $gameRequest[2],
                            'type' => 'narrator_quest_comment',
                            'data' => $gameRequest[3],
                            'sess' => 'complete', // Mark as complete so it doesn't get processed again
                            'localts' => $currentTime
                        )
                    );
                    
                    // Update timestamp for successful quest comment
                    $db->upsertRowOnConflict(
                        "conf_opts",
                        array(
                            "id"    => "QUEST_COMMENT_LAST_TIMESTAMP",
                            "value" => $currentTime
                        ),
                        'id'
                    );
                    
                    // Store flag to trigger narrator after init processing
                    $GLOBALS["TRIGGER_NARRATOR_QUEST_COMMENT"] = true;
                    
                    Logger::info("Narrator quest comment will be triggered");
                }
            }
        } else {
            $MUST_END = true;
        }
    } catch (Exception $e) {
        Logger::warn("Could not check narrator quest comment settings: " . $e->getMessage());
        $MUST_END = true;
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
    } else if ($vars[0]=="chim_renamenpc") {
        // Convert signed to unsigned using bitwise AND
        $unsignedInt = ($vars[3]+0) & 0xFFFFFFFF;
        // Represent as 8-digit zero-padded hex with 0x prefix
        $unsignedIntHex = '0x' . strtoupper(str_pad(dechex($unsignedInt), 8, '0', STR_PAD_LEFT));
            
        $npcMaster=new NpcMaster();
        $oldNpcData=$npcMaster->getByName($vars[1]);
        $newNpcData=$npcMaster->getByName($vars[2]);
        
        if (!$newNpcData) {
            createProfile($vars[2]);
            $newNpcData=$npcMaster->getByName($vars[2]);
        }

        $npcMaster->renameNPC($vars[1],$vars[2]);

            $db->insert(
                'responselog',
                [
                    'localts' => time(),
                    'sent'    => 0,
                    'actor'   => "rolemaster",
                    'text'    => "",
                    'action'  => 'rolecommand|RenameNPC@'.$unsignedIntHex.'@'.$db->escape($vars[2]),
                    'tag'     => '',
                ]
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
    logEvent($gameRequest);

    $npcMaster=new NpcMaster();
    $npcMaster->backupAllNpcs($gameRequest[2]);
    require_once __DIR__ . "/../service/processors/snqe/lib/snqe.class.php";
    SNQEQuestManager::save_quests($gameRequest[2]);

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


    $retVal=createProfile($localName,[],false,$baseProfile); //1-NEW PROFILE, 2-PROFILE ALREADY EXISTS
    audit_log("comm.php addnpc $localName");

     if ($retVal==1)
        AddFirstTimeMet($localName, $momentum, $gameRequest[2],$gameRequest[1]);


    // Update new data
    $npcMaster=new NpcMaster();
    $currentNpcData=$npcMaster->getByName($localName);
    
    if (isset($splitNameBase[4]) && $retVal==1) {
        $currentNpcDataAlt=$npcMaster->getByRefId($splitNameBase[4]);
        if ($currentNpcDataAlt && $currentNpcDataAlt["npc_name"]!=$currentNpcData["npc_name"] ) {
            // Seems an NPC has changed name.
            // It's disabled, but could be useful fot the Bujold quest e.g
            // But seems bandit spawn are reusing refids
            error_log("[ADDNPC] detected name change refid:{$splitNameBase[4]} {$currentNpcDataAlt["npc_name"]}!={$currentNpcData["npc_name"]}");
            
            if (false) {
                $newId=$currentNpcData["id"];
                $newName=$currentNpcData["npc_name"];

                $npcMaster->renameNPC($oldName,$newName);
            }      
        }
    }

    if ($currentNpcData) {
        $currentNpcData["base"]=$splitNameBase[1];
        if (sizeof($splitNameBase)>1) {
      
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
            
            // NPC equipment (10 slots from Skyrim) - format: name^baseid
            $equipmentSlots = [
                23 => 'helmet',
                24 => 'armor',
                25 => 'boots',
                26 => 'gloves',
                27 => 'amulet',
                28 => 'ring',
                29 => 'cape',
                30 => 'backpack',
                31 => 'left_hand',
                32 => 'right_hand'
            ];
            
            foreach ($equipmentSlots as $index => $slotName) {
                $slotData = isset($splitNameBase[$index]) ? $splitNameBase[$index] : '';
                if (!empty($slotData)) {
                    $parts = explode("^", $slotData);
                    $meta["equipment"][$slotName] = isset($parts[0]) ? $parts[0] : '';
                    $meta["equipment"][$slotName . '_baseid'] = isset($parts[1]) ? $parts[1] : '';
                } else {
                    $meta["equipment"][$slotName] = '';
                    $meta["equipment"][$slotName . '_baseid'] = '';
                }
            }
            
            // NPC stats (core attributes)
            $meta["stats"]["level"]=isset($splitNameBase[33]) ? intval($splitNameBase[33]) : 1;
            $meta["stats"]["health"]=isset($splitNameBase[34]) ? floatval($splitNameBase[34]) : 0;
            $meta["stats"]["health_max"]=isset($splitNameBase[35]) ? floatval($splitNameBase[35]) : 0;
            $meta["stats"]["magicka"]=isset($splitNameBase[36]) ? floatval($splitNameBase[36]) : 0;
            $meta["stats"]["magicka_max"]=isset($splitNameBase[37]) ? floatval($splitNameBase[37]) : 0;
            $meta["stats"]["stamina"]=isset($splitNameBase[38]) ? floatval($splitNameBase[38]) : 0;
            $meta["stats"]["stamina_max"]=isset($splitNameBase[39]) ? floatval($splitNameBase[39]) : 0;
            $meta["stats"]["scale"]=isset($splitNameBase[40]) ? floatval($splitNameBase[40]) : 1.0;

            $meta["mods"]=isset($splitNameBase[41]) ?explode("#",$splitNameBase[41]):null;

            // NPC factions - format: formID1:rank1#formID2:rank2#...
            $factionString = isset($splitNameBase[42]) ? $splitNameBase[42] : '';
            $factionList = [];
            $formIds=[];
            error_log("*TRACE: [ADDNPC] Processing factions for $localName: {$factionString}");
            if (!empty($factionString)) {
                $factionPairs = explode("#", $factionString);
                foreach ($factionPairs as $pair) {
                    $parts = explode(":", $pair);
                    if (count($parts) >= 2) {
                        $formId = $parts[0];
                        $formIds[]=$formId;// Collect form IDs to fetch names later
                        $rank = intval($parts[1]);
                        $factionList[] = [
                            'formid' => $formId,
                            'rank' => $rank,
                            'name'=>'' // Placeholder, will be filled after fetching faction names from DB
                        ];
                    }
                }
            }
            // Fetch only the faction names we need in a single query to avoid multiple DB hits
            $arrFormIdNames=[];
            if (sizeof($formIds)>0) {
                $arrFormIdNames=$factionNames=$db->fetchAll("select formid,name from factions where formid in ('".implode("','", $formIds)."')");
            }
            
            // Now map the arrFormIdNames to  mapFormIdNames 
            $mapFormIdNames=[];
            foreach ($arrFormIdNames as $factionInfo) {
                $mapFormIdNames[($factionInfo['formid'])]=$factionInfo['name'];
            }
            // Finally, fill the faction names in the factionList
            foreach ($factionList as &$faction) {
                $faction["name"]=$mapFormIdNames[$faction["formid"]] ?? 'Unknown Faction';
            }

        }
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
            AND (r.match_gender IS NULL OR '$npcGender' ~ r.match_gender)
            AND (r.match_base IS NULL OR '$npcBase' ~ r.match_base)
            AND (r.match_mods IS NULL OR r.match_mods <@ $modsArray)
            ORDER BY r.priority DESC
        ";


        $rules = $db->fetchAll($sql);
        error_log("[ADDNPC IMPORTING RULES] Matching rules for $npcName: ".sizeof($rules));

        foreach ($rules as $rule) {

            if (!empty($rule["profile"])) {
                $currentNpcData["profile_id"] = (int)$rule["profile"];
                error_log("[ADDNPC IMPORTING RULES] Matching rule for $npcName: Profile {$rule["profile"]}");

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

        // Store factions in extended_data
        $extended = $npcMaster->getExtendedData($currentNpcData);
        $extended['factions'] = $factionList;
        
        // NPC class - format: className:formID:trainSkill:trainLevel
        $classString = isset($splitNameBase[43]) ? $splitNameBase[43] : '';
        $classData = null;
        if (!empty($classString)) {
            $parts = explode(":", $classString);
            if (count($parts) >= 2) {
                $classData = [
                    'name' => $parts[0],
                    'formid' => $parts[1]
                ];
                // Add training data if present
                if (count($parts) >= 4 && !empty($parts[2])) {
                    $classData['teaches'] = $parts[2];
                    $classData['max_training_level'] = intval($parts[3]);
                }
            }
        }
        $extended['class'] = $classData;
        
        $currentNpcData = $npcMaster->setExtendedData($currentNpcData, $extended);

        $npcMaster->updateByArray($currentNpcData);
        
        $profile=new CoreProfile();
        $profData=json_decode($profile->getById($currentNpcData["profile_id"])["metadata"],true);

        $doSalute=(isset($profData["SALUTATION_AFTER_1_DAY"]) && $profData["SALUTATION_AFTER_1_DAY"] || isset($meta["SALUTATION_AFTER_1_DAY"]) && $meta["SALUTATION_AFTER_1_DAY"] );
        if ($doSalute) {
            error_log("[salutation_after_a_while] enabled for {$currentNpcData["npc_name"]}, profile:{$profData["SALUTATION_AFTER_1_DAY"]} ,npc:{$meta["SALUTATION_AFTER_1_DAY"]}");
            $lit=GetLastInteraction($GLOBALS["PLAYER_NAME"],$currentNpcData["npc_name"]);
            if (gamets2days_between($lit,$gameRequest[2]) > 1) {
                // If salutation_after_a_while is enable for this NPC, if 1 day has passed between last iteration, force a salutation.
                $instructionText="should salutate {$GLOBALS["PLAYER_NAME"]}, as more than 1 day passed with no talking.";
                $roleMasterAction = "rolecommand|Instruction@{$currentNpcData["npc_name"]}@{$instructionText}@0";
        
                // Insert into database
                $GLOBALS["db"]->insert(
                    'responselog',
                    array(
                        'localts' => time(),
                        'sent' => 0,
                        'actor' => "rolemaster",
                        'text' => '',
                        'action' => $roleMasterAction,
                        'tag' => ""
                    )
                );
            } else {
                error_log("[salutation_after_a_while] {$currentNpcData["npc_name"]} gamets2days_between($lit,$gameRequest[2]) > 1");
            }
        } else {
            error_log("[salutation_after_a_while] disabled for {$currentNpcData["npc_name"]}");
        }
    }

    // RELATIONSHIP SYSTEM: Queue NPC for relationship initialization
    // This parses TEXT relationships into JSONB without blocking map load
    if ($currentNpcData && !empty($currentNpcData['id'])) {
        $relAsyncFile = $GLOBALS["ENGINE_PATH"] . "ext/relationship_system/async_queue.php";
        if (file_exists($relAsyncFile)) {
            require_once $relAsyncFile;
            if (function_exists('_relQueueNpcInit')) {
                _relQueueNpcInit($currentNpcData['id'], $localName);
            }
        }
    }

    $MUST_END=true;
    
    
} elseif (strpos($gameRequest[0], "util_location_name")===0) {    // util_location_name 
    
    $splitNameBase=explode("/",$gameRequest[3]);
    if (strtoupper($splitNameBase[0])=="__CLEAR_ALL__")
        $db->query("truncate table locations");
    else {
        
        if ($splitNameBase[0] && $splitNameBase[1]) {
            $existingRecord = $db->fetchOne("SELECT * FROM locations WHERE formid = '{$splitNameBase[1]}'");
            
            if ($existingRecord) {
            $db->updateRow(
                'locations',
                array(
                'name' => $splitNameBase[0],
                'region' => $splitNameBase[2],
                'hold' => $splitNameBase[3],
                'tags' => $splitNameBase[4],
                'is_interior' => intval($splitNameBase[5]),
                'vanilla_location' => intval($splitNameBase[1]) < 77175193 ? "TRUE" : "FALSE",
                'factions' => $splitNameBase[6] ?? '',
                'coords' => (isset($splitNameBase[7]) && isset($splitNameBase[8]) && $splitNameBase[7] && $splitNameBase[8]) ? "(" . floatval($splitNameBase[7]) . "," . floatval($splitNameBase[8]) . ")" : NULL
                ),
                "formid = '{$splitNameBase[1]}'"
            );
            } else {
            $db->insert(
                'locations',
                array(
                'name' => $splitNameBase[0],
                'formid' => $splitNameBase[1],
                'region' => $splitNameBase[2],
                'hold' => $splitNameBase[3],
                'tags' => $splitNameBase[4],
                'is_interior' => intval($splitNameBase[5]),
                'vanilla_location' => intval($splitNameBase[1]) < 77175193 ? "TRUE" : "FALSE",
                'factions' => $splitNameBase[6] ?? '',
                'coords' => (isset($splitNameBase[7]) && isset($splitNameBase[8]) && $splitNameBase[7] && $splitNameBase[8]) ? "(" . floatval($splitNameBase[7]) . "," . floatval($splitNameBase[8]) . ")" : NULL
                )
            );
            }
        }
    }
    $MUST_END=true;
    
    
} elseif (strpos($gameRequest[0], "util_faction_name")===0) {    // util_location_name 
    
    $splitNameBase=explode("/",$gameRequest[3]);
    if (strtoupper($splitNameBase[0])=="__CLEAR_ALL__")
        $db->query("truncate table factions");
    else {
        
        if ($splitNameBase[0] && $splitNameBase[1]) {
            $db->insert(
                'factions',
                array(
                    'name' => $splitNameBase[1],
                    'formid' => strtoupper($splitNameBase[0]),


                )
            );
        }
    }
    $MUST_END=true;
    
    
} elseif (strpos($gameRequest[0], "util_location_npc")===0) {    // util_location_name 
    
    
    $splitNameBase=explode("/",$gameRequest[3]);
    if ($splitNameBase[0] && $splitNameBase[1]) {
        $npcMaster=new NpcMaster();
        $currentNpcData = $npcMaster->getByName($splitNameBase[0]);
        
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
            if (isset($meta["last_coords"])) {
                $meta["last_coords_history"][]=$meta['last_coords'];
                // Keep only last 10 elements
                if (count($meta["last_coords_history"]) > 10) {
                    $meta["last_coords_history"] = array_slice($meta["last_coords_history"], -5);
                }

            }

            $meta['last_coords'] = [$splitNameBase[1],$splitNameBase[2],$splitNameBase[3],$splitNameBase[4],"last_updated"=>$gameRequest[2]];
            
            // Save back to database
            $currentNpcData = $npcMaster->setMetadata($currentNpcData, $meta);
            $npcMaster->updateByArray($currentNpcData);
            
            Logger::info("Updated last_coords for {$currentNpcData["npc_name"]}");

            // Experiment
            if (false) {
                try {
                    $db->insert(
                        'point_cloud',
                        array(
                            'x' => $splitNameBase[1],
                            'y' => $splitNameBase[2],
                            'z' => $splitNameBase[3],
                            'tag' => $splitNameBase[4],
                            'gamets'=>$gameRequest[2]
                        )
                    );
                } catch (Exception $e) {
                    Logger::warn("Failed to insert cloud point location data: " . $e->getMessage());
                }
            }
        }
    }

    $MUST_END=true;
    
    
}  elseif (strpos($gameRequest[0], "enable_bg")===0) {    // util_location_name 
    
    $npcMaster = new NpcMaster();
    $splitNameBase=explode("/",$gameRequest[3]);
    if ($splitNameBase[0] && $splitNameBase[1]) {
        $currentNpcData = $npcMaster->getByName($splitNameBase[0]);
        
        if ($currentNpcData) {
            // Get existing metadata
            $meta = [];
            if (!empty($currentNpcData['extended_data'])) {
                $meta = json_decode($currentNpcData['extended_data'], true);
                if (!is_array($meta)) {
                    $meta = [];
                }
            }
            $currentNpcData["refid"]=$splitNameBase[1];
            // Update equipment section
            $meta['background_life_enabled'] = true;
            
            // Save back to database
            $currentNpcData = $npcMaster->setExtendedData($currentNpcData, $meta);
            $npcMaster->updateByArray($currentNpcData);
            error_log("Updated background_life_enabled for {$currentNpcData["npc_name"]}");
            Logger::info("Updated background_life_enabled for {$currentNpcData["npc_name"]}");
        }
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
    
    Logger::info("updateprofiles_batch_async: Checking " . count($npcList) . ",{$gameRequest[3]} NPCs for enabled dynamic profiles");
    
    // First pass: quickly check which NPCs have DYNAMIC_PROFILE enabled
    foreach ($npcList as $npcName) {
        $npcName = trim($npcName);
        if (empty($npcName)) continue;
        
        // Handle The Narrator separately
        if ($npcName === "The Narrator") {
            require_once(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "narrator.class.php");
            $narrator = new Narrator();
            
            // Check if narrator has dynamic profile enabled
            if ($narrator->getBool('dynamic_profile', false)) {
                $enabledNPCs[] = $npcName;
                Logger::debug("updateprofiles_batch_async: The Narrator has dynamic profile enabled");
            }
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

        // Check  if DYNAMIC_PROFILE is enabled for NPC's profile.
        $profile=new CoreProfile();
        $currentProfileData=$profile->getById($npcData["profile_id"]);
        $profile_metadata=json_decode($currentProfileData["metadata"],true);
        if ($profile_metadata["DYNAMIC_PROFILE_ENABLED"])
            $isDynamicEnabled=true;
        

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
    
    // AUTO_DIARY functionality - trigger diary entries for nearby NPCs with auto_diary_enabled
    Logger::info("WAITSTART event: Processing auto-diary for nearby NPCs");
    processAutoDiary($gameRequest, "waitstart");
    
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
    
    // AUTO_DIARY functionality - trigger diary entries for nearby NPCs with auto_diary_enabled
    Logger::info("GOODNIGHT event: Processing auto-diary for nearby NPCs");
    processAutoDiary($gameRequest, "goodnight");
    
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
    
    // logEvent($gameRequest);

    if (isset($_GET["profile"])) {
        $npcMaster=new NpcMaster();
        $currentNpcData=$npcMaster->getByMD5($_GET["profile"]);
        $profileMgr=new CoreProfile();
        $profileData=$profileMgr->getBySlot($gameRequest[3]);
        if (is_array($currentNpcData)) {
            $currentNpcData["profile_id"]=$profileData["id"];
            $npcMaster->updateByArray($currentNpcData);
            error_log("[CORE SYSTEM] <{$currentNpcData["npc_name"]}> asigned to slot <{$profileData["label"]}>");
            
        } else {
            error_log("[CORE SYSTEM] No valid NPC found {$_GET["profile"]}");
        }
    } else {
        error_log("[CORE SYSTEM] No valid profile specified");
    }
    
    $MUST_END=true;
    
    
} elseif (strpos($gameRequest[0], "named_cell_static")===0) {    // diary_nearby event - manual trigger for all NPCs in range
    
    // logEvent($gameRequest);

    $localData=explode("/",$gameRequest[3]);
    $staticListRaw=explode(",",$localData[1]);
    foreach ($staticListRaw as $key => $value) {
        if ($value) {
            $nameRefIdPair = explode("@",$value);
            if (!empty($nameRefIdPair[0])) {
                $unsignedInt = (intval($nameRefIdPair[1]) + 0) & 0xFFFFFFFF;
                $hexRefId = '0x' . strtoupper(str_pad(dechex($unsignedInt), 8, '0', STR_PAD_LEFT));
                $nameRefIdPair[1] = $hexRefId;
                $inCellItems[]=implode(":",$nameRefIdPair);
            }
        }
    }
    $static_list=implode("\n",$inCellItems);
    $db->upsertRowOnConflict(
            'named_cell',
            array(
                'id' => intval($localData[0]),
                'door_id'=>0,
                'statics_list'=> $static_list,
                'gamets'=> intval($gameRequest[2]),
            ),
            "id,door_id"
        );


    $MUST_END=true;
    
    
} elseif (strpos($gameRequest[0], "named_cell")===0) {    // diary_nearby event - manual trigger for all NPCs in range
    
    // logEvent($gameRequest);

    $localData=explode("/",$gameRequest[3]);
    if ($localData) {
        // Lets check first if already exists a record with same id, same door_id and dest_door_cell_id is not 0, in that case, don't update as we already have better info on the database
        $existingRecord = $db->fetchOne("SELECT * FROM named_cell WHERE id = " . intval($localData[1]) . " AND door_id = " . intval($localData[6]) . " AND dest_door_cell_id != 0 and door_id<>0 and location_id<>0");
        
        if (!$existingRecord) {
            $db->upsertRowOnConflict(
                    'named_cell',
                    array(
                        'id' => intval($localData[1]),
                        'cell_name' =>$localData[0],
                        'location_id'=>intval($localData[2]),
                        'interior'=>intval($localData[3]),
                        'dest_door_cell_id'=>intval($localData[4]),
                        'dest_door_exterior'=>intval($localData[5]),
                        'door_id'=>intval($localData[6]),
                        'vanilla_cell'=>(intval($localData[1])<77175193) ? true : false,// IDs below 77175193 are vanilla cells 0x04999999
                        'worldspace'=> $localData[7],
                        'closed'=>intval($localData[8]),
                        'door_name'=> $localData[9],
                        'door_x'=> floatval($localData[10]),
                        'door_y'=> floatval($localData[11]),
                        'gamets'=> intval($gameRequest[2]),
                    ),
                    "id,door_id"
                );
        } else {
            error_log("Skipping named_cell update for id:{$localData[1]} door_id:{$localData[6]} as better data already exists.");
        }
    } else {
        error_log("named_cell: No data provided");
    }
    $MUST_END=true;
    
    
}  elseif (strpos($gameRequest[0], "switchrace")===0) {    // diary_nearby event - manual trigger for all NPCs in range
    
    logEvent($gameRequest);
    
    $MUST_END=true;
    
    
} elseif (strpos($gameRequest[0], "snqe")===0) {    // Quest event - SNEQ related event
    
    $localData=explode("@",$gameRequest[3]);
    if (strtoupper($localData[0]) == "START") {
        // Execute background SNQE agent processing with proper error handling
        $enginePath = escapeshellarg($GLOBALS["ENGINE_PATH"]);
        $cmd = "php {$enginePath}/service/processors/snqe/run_agents.php full > {$enginePath}/log/log_run_agent.log 2>&1 &";
        $output = shell_exec($cmd);
        $output = trim($output);    
        if ($output === null) {
            Logger::error("[SNQE] Failed to start background agent processing");
        } else {
            Logger::info("[SNQE] Background agent processing started successfully");
        }
    } else  if (strtoupper($localData[0]) == "END") {
        // Execute background SNQE agent processing with proper error handling
        $enginePath = escapeshellarg($GLOBALS["ENGINE_PATH"]);
        $cmd = "php {$enginePath}/service/processors/snqe/run_agents.php full end> {$enginePath}/log/log_run_agent.log 2>&1 &";
        $output = shell_exec($cmd);
        $output = trim($output);    
        if ($output === null) {
            Logger::error("[SNQE] Failed to start background agent processing");
        } else {
            Logger::info("[SNQE] Background agent processing started successfully");
        }
    } else if (strtoupper($localData[0]) == "CLEAN") {
        // Execute SNQE manager clean command
        $enginePath = escapeshellarg($GLOBALS["ENGINE_PATH"]);
        $cmd = "php {$enginePath}/service/manager.php snqe clean 2>&1";
        
        try {
            $output = shell_exec($cmd);
            Logger::info("[SNQE] Clean command executed: " . trim($output ?? ""));
        } catch (Exception $e) {
            Logger::error("[SNQE] Clean command failed: " . $e->getMessage());
        }
        
        // Remove state file if it exists
        $stateFile = "{$GLOBALS["ENGINE_PATH"]}/log/snqe_state.json";
        if (file_exists($stateFile)) {
            if (!unlink($stateFile)) {
                Logger::warn("[SNQE] Failed to delete state file: {$stateFile}");
            } else {
                Logger::info("[SNQE] State file deleted successfully");
            }
        }
    } else if (strtoupper($localData[0]) == "RESTART") {
        // Clean and restart from context
        // Execute SNQE manager clean command
        $enginePath = escapeshellarg($GLOBALS["ENGINE_PATH"]);
        $cmd = "php {$enginePath}/service/manager.php snqe clean 2>&1";
        
        try {
            $output = shell_exec($cmd);
            Logger::info("[SNQE] Clean command executed: " . trim($output ?? ""));
        } catch (Exception $e) {
            Logger::error("[SNQE] Clean command failed: " . $e->getMessage());
        }
        
        // Remove state file if it exists
        $stateFile = "{$GLOBALS["ENGINE_PATH"]}/log/snqe_state.json";
        if (file_exists($stateFile)) {
            if (!unlink($stateFile)) {
                Logger::warn("[SNQE] Failed to delete state file: {$stateFile}");
            } else {
                Logger::info("[SNQE] State file deleted successfully");
            }
        }

        $enginePath = escapeshellarg($GLOBALS["ENGINE_PATH"]);
        $cmd = "php {$enginePath}/service/processors/snqe/run_agents.php start_from_context> {$enginePath}/log/log_run_agent.log 2>&1 &";
        $output = shell_exec($cmd);
        $output = trim($output);    
        if ($output === null) {
            Logger::error("[SNQE] Failed to start background agent processing");
        } else {
            Logger::info("[SNQE] Background agent processing started successfully");
        }


    } else {
        Logger::warn("[SNQE] Unknown action: " . ($localData[0] ?? "unknown"));
    }
    
    $MUST_END=true;
    
    
} 

// Trigger narrator welcome message if flagged during init
if (isset($GLOBALS["TRIGGER_NARRATOR_WELCOME"]) && $GLOBALS["TRIGGER_NARRATOR_WELCOME"]) {
    // Change the request type to narrator_welcome so main.php processes it
    $gameRequest[0] = "narrator_welcome";
    $MUST_END = false; // Don't end, continue to main.php
    
    // Load narrator profile
    require_once(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "narrator.class.php");
    $narrator = new Narrator();
    
    // Get narrator profile ID
    $narratorProfileId = $narrator->getProfileId();
    if (!$narratorProfileId) {
        // Try to find The Narrator profile
        $narratorProfile = $db->fetchOne("SELECT id FROM core_profiles WHERE name = 'The Narrator' LIMIT 1");
        if ($narratorProfile && isset($narratorProfile['id'])) {
            $narratorProfileId = $narratorProfile['id'];
        }
    }
    
    if ($narratorProfileId) {
        $_GET["profile"] = $narratorProfileId;
    } else {
        Logger::warn("[NARRATOR_WELCOME] Could not find narrator profile, welcome message cancelled");
        $MUST_END = true;
    }
}

// Trigger narrator quest comment if flagged during quest event
if (isset($GLOBALS["TRIGGER_NARRATOR_QUEST_COMMENT"]) && $GLOBALS["TRIGGER_NARRATOR_QUEST_COMMENT"]) {
    // Change the request type to narrator_quest_comment so main.php processes it
    $gameRequest[0] = "narrator_quest_comment";
    $MUST_END = false; // Don't end, continue to main.php
    
    // Load narrator profile
    require_once(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "narrator.class.php");
    $narrator = new Narrator();
    
    // Get narrator profile ID
    $narratorProfileId = $narrator->getProfileId();
    if (!$narratorProfileId) {
        // Try to find The Narrator profile
        $narratorProfile = $db->fetchOne("SELECT id FROM core_profiles WHERE name = 'The Narrator' LIMIT 1");
        if ($narratorProfile && isset($narratorProfile['id'])) {
            $narratorProfileId = $narratorProfile['id'];
        }
    }
    
    if ($narratorProfileId) {
        $_GET["profile"] = $narratorProfileId;
    } else {
        Logger::warn("[NARRATOR_QUEST_COMMENT] Could not find narrator profile, quest comment cancelled");
        $MUST_END = true;
    }
}

?>