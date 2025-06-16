<?php

$MUST_END=false;

$gameRequest[3] = @mb_convert_encoding($gameRequest[3], 'UTF-8', 'UTF-8');

if ($gameRequest[0] == "init") { // Reset responses if init sent (Think about this)
    // avoid a rare case where skyrim briefly reverts to level 1 Prisoner during load
    if ($gameRequest[2] == "10000000") {
        Logger::warn("Ignoring init with a gamets of 10000000.");
        $MUST_END=true;
        return;
    }
    $now=time();
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


} if ($gameRequest[0] == "wipe") { // Reset reponses if init sent (Think about this)
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
    $db->upsertRowOnConflict(
        'conf_opts',
        array(
            'id' => $vars[0],
            'value' => $vars[1]
        ),
        "id"
    );
    
    
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
    
    
} elseif (strpos($gameRequest[0], "updateprofiles_batch")===0) {
    
    // New batch processing for timer-based dynamic profile updates
    // Format: updateprofiles_batch|timestamp|gamestamp|NPC1,NPC2,NPC3,NPC4
    
    if (!isset($gameRequest[3]) || empty($gameRequest[3])) {
        Logger::debug("updateprofiles_batch: No NPCs provided");
        die();
    }
    
    $npcList = explode(',', $gameRequest[3]);
    $enabledNPCs = [];
    
    Logger::info("updateprofiles_batch: Checking " . count($npcList) . " NPCs for enabled dynamic profiles");
    
    // First pass: quickly check which NPCs have DYNAMIC_PROFILE enabled
    foreach ($npcList as $npcName) {
        $npcName = trim($npcName);
        if (empty($npcName)) continue;
        
        // Skip The Narrator
        if ($npcName === "The Narrator") {
            continue;
        }
        
        // Check if profile exists for this NPC
        $profilePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "conf" . DIRECTORY_SEPARATOR . "conf_" . md5($npcName) . ".php";
        if (!file_exists($profilePath)) {
            continue;
        }
        
        // Load the NPC's profile to check if DYNAMIC_PROFILE is enabled
        // Save current DYNAMIC_PROFILE state to avoid contamination
        $originalDynamicProfile = isset($DYNAMIC_PROFILE) ? $DYNAMIC_PROFILE : null;
        
        // Include the profile - this will set variables in current scope
        include($profilePath);
        
        // Check if DYNAMIC_PROFILE is enabled for this NPC
        $isDynamicEnabled = (isset($DYNAMIC_PROFILE) && $DYNAMIC_PROFILE);
        
        // Restore original state if it existed
        if ($originalDynamicProfile !== null) {
            $DYNAMIC_PROFILE = $originalDynamicProfile;
        } else {
            unset($DYNAMIC_PROFILE);
        }
        
        if ($isDynamicEnabled) {
            $enabledNPCs[] = $npcName;
        }
    }
    
    $enabledCount = count($enabledNPCs);
    
    // Send immediate ACK message back to plugin with count
    if ($enabledCount > 0) {
        echo "The Narrator|rolecommand|DebugNotification@Updating $enabledCount dynamic profile" . ($enabledCount == 1 ? "" : "s") . "..." . PHP_EOL;
        Logger::info("updateprofiles_batch: Will update $enabledCount profiles: " . implode(', ', $enabledNPCs));
    } else {
        Logger::info("updateprofiles_batch: No profiles to update - none had DYNAMIC_PROFILE enabled");
    }
    
    @ob_flush();
    @flush();
    
    // Second pass: actually process the enabled NPCs (this can take time)
    if ($enabledCount > 0) {
        $successCount = 0;
        foreach ($enabledNPCs as $npcName) {
            if (processSingleDynamicProfile($npcName, $gameRequest)) {
                $successCount++;
            }
        }
        Logger::info("updateprofiles_batch: Completed processing $enabledCount dynamic profiles");
        
        // Send single summary message
        if ($successCount > 0) {
            echo "The Narrator|rolecommand|DebugNotification@Dynamic Profile updated for $successCount character" . ($successCount == 1 ? "" : "s") . PHP_EOL;
        }
    }
    
    $MUST_END = true;
    
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
		$head[]   = ["role"	=> "system", "content"	=> "You are an assistant. Analyze this dialogue and then update the dynamic character profile based on the information provided. ", ];
		$prompt[] = ["role"	=> "user", "content"	=> "* Dialogue history:\n" .$historyData ];
		// Use centralized function from data_functions.php
		$currentDynamicProfile = buildDynamicProfileDisplay();
        
		$prompt[] = ["role" => "user", "content" => "Current character profile you are updating:\n" . "Character name:\n"  . $GLOBALS["HERIKA_NAME"] . "\nCharacter static biography:\n" . $GLOBALS["HERIKA_PERS"] . "\n" ."Character dynamic biography (this is what you are updating):\n" . $currentDynamicProfile];
		$prompt[] = ["role"=> "user", "content"	=> $updateProfilePrompt, ];
		$contextData       = array_merge($head, $prompt);
        $connectionHandler = new $GLOBALS["CONNECTORS_DIARY"];
        $GLOBALS["FORCE_MAX_TOKENS"]=1500;
		$connectionHandler->open($contextData, ["max_tokens"=>1500]);
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
            $escapedDynamic = var_export($responseParsed["HERIKA_DYNAMIC"], true);
            if (!is_string($responseParsed["HERIKA_DYNAMIC"]) || !$escapedDynamic) {
                $escapedDynamic = '';
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
        processAutoDiary($gameRequest, "waitstart");
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
    
    
}

// Function to process AUTO_DIARY for all current followers
function processAutoDiary($gameRequest, $eventType) {
    global $db;
    
    // Get current party data
    $partyConf = DataGetCurrentPartyConf();
    if (empty($partyConf)) {
        Logger::info("AUTO_DIARY: No current party data found");
        return;
    }
    
    // Parse party data
    $currentParty = json_decode($partyConf, true);
    if (!is_array($currentParty) || empty($currentParty)) {
        Logger::info("AUTO_DIARY: Failed to parse party data or party is empty");
        return;
    }
    
    $processedCount = 0;
    $generatedCount = 0;
    $diaryCooldownPeriod = isset($GLOBALS["DIARY_COOLDOWN"]) ? intval($GLOBALS["DIARY_COOLDOWN"]) : 30;
    
    Logger::info("AUTO_DIARY: Processing $eventType event for " . count($currentParty) . " followers");
    
    foreach ($currentParty as $followerName => $followerData) {
        if (empty($followerName) || !isset($followerData["name"])) {
            continue;
        }
        
        $processedCount++;
        
        // Check diary cooldown for this specific follower
        $npcName = preg_replace('/[^a-zA-Z0-9_]/', '_', $followerName);
        $cooldownKey = "DIARY_LAST_TIMESTAMP_" . $npcName;
        
        $diaryRecord = $db->fetchAll("SELECT value FROM conf_opts WHERE id='" . $db->escape($cooldownKey) . "'");
        
        if (!empty($diaryRecord)) {
            $lastTrigger = (int) $diaryRecord[0]['value'];
            $timeElapsed = time() - $lastTrigger;

            if ($timeElapsed < $diaryCooldownPeriod) {
                Logger::info("AUTO_DIARY: Skipping $followerName (cooldown active: " . ($diaryCooldownPeriod - $timeElapsed) . " seconds remaining)");
                continue;
            }
        }
        
        // Update cooldown timestamp for this follower
        $db->upsertRowOnConflict(
            'conf_opts',
            array(
                'id' => $cooldownKey,
                'value' => time()
            ),
            "id"
        );
        
        // Generate diary entry for this follower
        if (generateFollowerDiary($followerName, $gameRequest, $eventType)) {
            $generatedCount++;
            Logger::info("AUTO_DIARY: Generated diary entry for $followerName");
        } else {
            Logger::info("AUTO_DIARY: Failed to generate diary entry for $followerName");
        }
    }
    
    // Echo confirmation using the same format as other notifications
    if ($generatedCount > 0) {
        echo "The Narrator|rolecommand|DebugNotification@$confirmationMessage" . PHP_EOL;
    }
}

// Function to process a single NPC's dynamic profile
function processSingleDynamicProfile($npcName, $gameRequest) {
    global $db;
    
    // Skip The Narrator
    if ($npcName === "The Narrator") {
        Logger::debug("processSingleDynamicProfile: Skipping The Narrator");
        return false;
    }
    
    // Check if profile exists for this NPC
    $profilePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "conf" . DIRECTORY_SEPARATOR . "conf_" . md5($npcName) . ".php";
    if (!file_exists($profilePath)) {
        Logger::debug("processSingleDynamicProfile: No profile found for $npcName");
        return false;
    }
    
    // Load the NPC's profile to check if DYNAMIC_PROFILE is enabled
    $originalGlobals = $GLOBALS;
    
    try {
        // Include the NPC's profile
        include($profilePath);
        
        // Check if DYNAMIC_PROFILE is enabled for this NPC
        if (!isset($DYNAMIC_PROFILE) || !$DYNAMIC_PROFILE) {
            Logger::debug("processSingleDynamicProfile: DYNAMIC_PROFILE disabled for $npcName");
            return false;
        }
        
        // Check if diary connector is configured
        if (!isset($GLOBALS["CONNECTORS_DIARY"]) || !file_exists(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CONNECTORS_DIARY"]}.php")) {
            Logger::debug("processSingleDynamicProfile: No diary connector configured for $npcName");
            return false;
        }
        
        // Get dynamic profile fields to update
        $fieldsToUpdate = isset($DYNAMIC_PROFILE_FIELDS) && is_array($DYNAMIC_PROFILE_FIELDS) 
            ? $DYNAMIC_PROFILE_FIELDS 
            : ["personality", "relationships"]; // Default fields
        
        if (empty($fieldsToUpdate)) {
            Logger::debug("processSingleDynamicProfile: No fields selected for dynamic updates for $npcName");
            return false;
        }
        
        // Set context for this NPC
        $GLOBALS["HERIKA_NAME"] = $npcName;
        
        // Process each selected field
        require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CONNECTORS_DIARY"]}.php");
        
        $historyData = getDynamicProfileHistoryData($npcName);
        $updatedFields = [];
        $successCount = 0;
        
        foreach ($fieldsToUpdate as $field) {
            $result = updateDynamicProfileField($npcName, $field, $historyData);
            if ($result !== false) {
                $updatedFields[$field] = $result;
                $successCount++;
            }
        }
        
        if ($successCount > 0) {
            // Save the updated profile
            $success = saveDynamicProfileUpdates($npcName, $updatedFields, $db);
            if ($success) {
                Logger::info("processSingleDynamicProfile: Successfully updated $successCount fields for $npcName: " . implode(', ', array_keys($updatedFields)));
                return true;
            }
        }
        
    } catch (Exception $e) {
        Logger::error("processSingleDynamicProfile: Error processing $npcName: " . $e->getMessage());
        return false;
    } finally {
        // Restore original globals
        foreach ($originalGlobals as $key => $value) {
            $GLOBALS[$key] = $value;
        }
    }
    
    return false;
}

// Function to generate diary entry for a specific follower
function generateFollowerDiary($followerName, $gameRequest, $eventType) {
    global $db;
    
    // Check if we have the diary connector configured
    if (!isset($GLOBALS["CONNECTORS_DIARY"]) || !file_exists(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CONNECTORS_DIARY"]}.php")) {
        Logger::info("AUTO_DIARY: No diary connector configured for $followerName");
        return false;
    }
    
    // Temporarily switch context to this follower
    $originalHerikaName = $GLOBALS["HERIKA_NAME"];
    $GLOBALS["HERIKA_NAME"] = $followerName;
    
    try {
        // Load follower's profile if it exists
        $profileLoaded = false;
        $originalHerikaData = [];
        
        // Try to load profile data for this follower
        if (function_exists('getConfFileFor')) {
            $confFile = getConfFileFor($followerName);
            if (!empty($confFile) && file_exists($confFile)) {
                // Save original values
                $originalHerikaData = [
                    'HERIKA_PERS' => isset($GLOBALS["HERIKA_PERS"]) ? $GLOBALS["HERIKA_PERS"] : '',
                    'HERIKA_DYNAMIC' => isset($GLOBALS["HERIKA_DYNAMIC"]) ? $GLOBALS["HERIKA_DYNAMIC"] : ''
                ];
                
                // Load follower's profile
                include($confFile);
                $profileLoaded = true;
                Logger::info("AUTO_DIARY: Loaded profile for $followerName");
            }
        }
        
        if (!$profileLoaded) {
            // Use default follower personality if no specific profile exists
            $GLOBALS["HERIKA_PERS"] = "A loyal companion and follower of " . $GLOBALS["PLAYER_NAME"] . ".";
            $GLOBALS["HERIKA_DYNAMIC"] = "Currently traveling and adventuring alongside " . $GLOBALS["PLAYER_NAME"] . ".";
        }
        
        // Use the same prompt system as regular diary entries
        // Build standard system prompt like main.php does
        
        // Use centralized function from data_functions.php
        $dynamicBio = buildDynamicBiography();
        
        $head = [
            ["role" => "system", "content" => strtr(
                $GLOBALS["PROMPT_HEAD"] . "\n" . $GLOBALS["HERIKA_PERS"] . $dynamicBio . "\n" . $GLOBALS["COMMAND_PROMPT"],
                ["#PLAYER_NAME#" => $GLOBALS["PLAYER_NAME"]]
            )]
        ];
        
        // Build user prompt for diary generation (like regular diary)
        $prompt = [
            ["role" => "user", "content" => "diary"]
        ];
        
        if (!empty($contextDataHistoric)) {
            $prompt[] = ["role" => "user", "content" => "Recent context: " . $contextDataHistoric];
        }
        
        $contextData = array_merge($head, $prompt);
        
        // Generate diary entry using LLM
        require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CONNECTORS_DIARY"]}.php");
        
        $connectionHandler = new $GLOBALS["CONNECTORS_DIARY"];
        $maxTokens = isset($GLOBALS["CONNECTOR"][$GLOBALS["CONNECTORS_DIARY"]]["MAX_TOKENS_MEMORY"]) 
            ? $GLOBALS["CONNECTOR"][$GLOBALS["CONNECTORS_DIARY"]]["MAX_TOKENS_MEMORY"] 
            : 1500;
            
        $connectionHandler->open($contextData, ["max_tokens" => $maxTokens]);
        
        $buffer = "";
        $totalBuffer = "";
        $breakFlag = false;
        
        while (true) {
            if ($breakFlag) {
                break;
            }
            
            if ($connectionHandler->isDone()) {
                $breakFlag = true;
            }
            
            $buffer .= $connectionHandler->process();
            $totalBuffer .= $buffer;
        }
        
        $connectionHandler->close();
        
        if (!empty(trim($buffer))) {
            // Save diary entry to database
            $topic = DataLastKnowDate();
            $location = DataLastKnownLocation();
            
            $db->insert(
                'diarylog',
                array(
                    'ts' => $gameRequest[1],
                    'gamets' => $gameRequest[2],
                    'topic' => $topic . " (Auto-diary: $eventType)",
                    'content' => trim($buffer),
                    'tags' => "Auto-diary,$eventType",
                    'people' => $followerName,
                    'location' => $location,
                    'sess' => 'pending',
                    'localts' => time()
                )
            );
            
            // Log memory
            if (function_exists('logMemory')) {
                logMemory($followerName, $followerName, trim($buffer), time(), $gameRequest[2], 'auto_diary', $gameRequest[1]);
            }
            
            // Send notification to plugin for this follower (same format as manual diary)
            echo $followerName."|rolecommand|DebugNotification@Diary Entry Written for ".$followerName.PHP_EOL;
            @ob_flush();
            @flush();
            
            return true;
        }
        
    } catch (Exception $e) {
        Logger::error("AUTO_DIARY: Error generating diary for $followerName: " . $e->getMessage());
    } finally {
        // Restore original context
        $GLOBALS["HERIKA_NAME"] = $originalHerikaName;
        
        // Restore original profile data if we loaded a follower profile
        if (!empty($originalHerikaData)) {
            $GLOBALS["HERIKA_PERS"] = $originalHerikaData['HERIKA_PERS'];
            $GLOBALS["HERIKA_DYNAMIC"] = $originalHerikaData['HERIKA_DYNAMIC'];
        }
    }
    
    return false;
}

function getDynamicProfileHistoryData($npcName) {
    $historyData = "";
    $lastPlace = "";
    $lastListener = "";
    $lastDateTime = "";
    
    // Determine how much context history to use for dynamic profiles
    $dynamicProfileContextHistory = 50; // Default value
    if (isset($GLOBALS["CONTEXT_HISTORY_DYNAMIC_PROFILE"]) && $GLOBALS["CONTEXT_HISTORY_DYNAMIC_PROFILE"] > 0) {
        $dynamicProfileContextHistory = $GLOBALS["CONTEXT_HISTORY_DYNAMIC_PROFILE"];
    } elseif (isset($GLOBALS["CONTEXT_HISTORY"]) && $GLOBALS["CONTEXT_HISTORY"] > 0) {
        $dynamicProfileContextHistory = $GLOBALS["CONTEXT_HISTORY"];
    }
    
    foreach (json_decode(DataSpeechJournal($npcName, $dynamicProfileContextHistory), true) as $element) {
        if ($element["listener"] == "The Narrator") {
            continue;
        }
        if ($lastListener != $element["listener"]) {
            $listener = " (talking to {$element["listener"]})";
            $lastListener = $element["listener"];
        } else {
            $listener = "";
        }
        
        if ($lastPlace != $element["location"]) {
            $place = " (at {$element["location"]})";
            $lastPlace = $element["location"];
        } else {
            $place = "";
        }

        if ($lastDateTime != substr($element["sk_date"], 0, 15)) {
            $date = substr($element["sk_date"], 0, 10);
            $time = substr($element["sk_date"], 11);
            $dateTime = "(on date {$date} at {$time})";
            $lastDateTime = substr($element["sk_date"], 0, 15); 
        } else {
            $dateTime = "";
        }
        
        $historyData .= trim("{$element["speaker"]}:".trim($element["speech"])." $listener $place $dateTime").PHP_EOL;
    }
    
    return $historyData;
}

function updateDynamicProfileField($npcName, $field, $historyData) {
    // Map field names to their corresponding HERIKA variables and prompts
    $fieldMapping = [
        'personality' => ['var' => 'HERIKA_PERSONALITY', 'prompt' => 'DYNAMIC_PROMPT_PERSONALITY'],
                    'relationships' => ['var' => 'HERIKA_RELATIONSHIPS', 'prompt' => 'DYNAMIC_PROMPT_RELATIONSHIPS'],
        'occupation' => ['var' => 'HERIKA_OCCUPATION', 'prompt' => 'DYNAMIC_PROMPT_OCCUPATION'],
        'skills' => ['var' => 'HERIKA_SKILLS', 'prompt' => 'DYNAMIC_PROMPT_SKILLS'],
        'speechstyle' => ['var' => 'HERIKA_SPEECHSTYLE', 'prompt' => 'DYNAMIC_PROMPT_SPEECHSTYLE'],
        'goals' => ['var' => 'HERIKA_GOALS', 'prompt' => 'DYNAMIC_PROMPT_GOALS']
    ];
    
    if (!isset($fieldMapping[$field])) {
        Logger::warning("updateDynamicProfileField: Unknown field '$field' for $npcName");
        return false;
    }
    
    $varName = $fieldMapping[$field]['var'];
    $promptName = $fieldMapping[$field]['prompt'];
    
    // Get current field value
    $currentValue = isset($GLOBALS[$varName]) ? $GLOBALS[$varName] : '';
    
    // Get field-specific prompt
    $updatePrompt = isset($GLOBALS[$promptName]) ? $GLOBALS[$promptName] : '';
    if (empty($updatePrompt)) {
        Logger::warning("updateDynamicProfileField: No prompt configured for field '$field' ($promptName)");
        return false;
    }
    
    try {
        // Build prompt for this specific field
        $head = [
            ["role" => "system", "content" => "You are an assistant. Analyze the dialogue history and update the specific character profile field based on the information provided."]
        ];
        
        $prompt = [
            ["role" => "user", "content" => "* Dialogue history:\n" . $historyData],
            ["role" => "user", "content" => "Character name: " . $npcName . "\nCurrent " . ucfirst($field) . ":\n" . $currentValue],
            ["role" => "user", "content" => $updatePrompt]
        ];
        
        $contextData = array_merge($head, $prompt);
        
        $connectionHandler = new $GLOBALS["CONNECTORS_DIARY"];
        
        // Get max tokens for this connector
        $maxTokens = 800; // Default for field updates
        switch($GLOBALS["CONNECTORS_DIARY"]) {
            case "openrouter":
                $maxTokens = isset($GLOBALS["CONNECTOR"]["openrouter"]["MAX_TOKENS_MEMORY"]) ? 
                    min($GLOBALS["CONNECTOR"]["openrouter"]["MAX_TOKENS_MEMORY"], 800) : $maxTokens;
                break;
            case "openai":
                $maxTokens = isset($GLOBALS["CONNECTOR"]["openai"]["MAX_TOKENS_MEMORY"]) ? 
                    min($GLOBALS["CONNECTOR"]["openai"]["MAX_TOKENS_MEMORY"], 800) : $maxTokens;
                break;
            case "google_openaijson":
                $maxTokens = isset($GLOBALS["CONNECTOR"]["google_openaijson"]["MAX_TOKENS_MEMORY"]) ? 
                    min($GLOBALS["CONNECTOR"]["google_openaijson"]["MAX_TOKENS_MEMORY"], 800) : $maxTokens;
                break;
            case "koboldcpp":
                $maxTokens = isset($GLOBALS["CONNECTOR"]["koboldcpp"]["MAX_TOKENS_MEMORY"]) ? 
                    min($GLOBALS["CONNECTOR"]["koboldcpp"]["MAX_TOKENS_MEMORY"], 800) : $maxTokens;
                break;
        }
        
        $connectionHandler->open($contextData, ["max_tokens" => $maxTokens]);
        
        $buffer = "";
        $breakFlag = false;
        
        while (true) {
            if ($breakFlag) {
                break;
            }
            
            if ($connectionHandler->isDone()) {
                $breakFlag = true;
            }
            
            $buffer .= $connectionHandler->process();
        }
        
        $connectionHandler->close();
        
        // Clean up the response
        $buffer = trim($buffer);
        
        if (!empty($buffer)) {
            Logger::debug("updateDynamicProfileField: Updated $field for $npcName");
            return $buffer;
        } else {
            Logger::warning("updateDynamicProfileField: Empty response for field '$field' for $npcName");
            return false;
        }
        
    } catch (Exception $e) {
        Logger::error("updateDynamicProfileField: Error updating field '$field' for $npcName: " . $e->getMessage());
        return false;
    }
}

function saveDynamicProfileUpdates($npcName, $updatedFields, $db) {
    $newConfFile = md5($npcName);
    $path = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
    $configFile = $path . "conf" . DIRECTORY_SEPARATOR . "conf_$newConfFile.php";
    
    if (!file_exists($configFile)) {
        Logger::error("saveDynamicProfileUpdates: Config file not found for $npcName");
        return false;
    }
    
    try {
        // Create backup
        copy($configFile, $path . "conf" . DIRECTORY_SEPARATOR . ".conf_{$newConfFile}_" . time() . ".php");
        
        $backup = file_get_contents($configFile);
        $backupFmtd = $db->escape($backup);
        
        $db->insert(
            'npc_profile_backup',
            array(
                'name' => $db->escape($npcName),
                'data' => $backupFmtd
            )
        );
        
        // Read current file content
        $content = file_get_contents($configFile);
        
        // Map field names to their corresponding HERIKA variables
        $fieldMapping = [
            'personality' => 'HERIKA_PERSONALITY',
            'relationships' => 'HERIKA_RELATIONSHIPS',
            'occupation' => 'HERIKA_OCCUPATION',
            'skills' => 'HERIKA_SKILLS',
            'speechstyle' => 'HERIKA_SPEECHSTYLE',
            'goals' => 'HERIKA_GOALS'
        ];
        
        // Update each field in the file
        foreach ($updatedFields as $field => $newValue) {
            if (!isset($fieldMapping[$field])) {
                continue;
            }
            
            $varName = $fieldMapping[$field];
            $escapedValue = var_export($newValue, true);
            
            // Check if variable already exists in file
            $pattern = '/\$' . preg_quote($varName, '/') . '\s*=\s*[^;]+;/';
            
            if (preg_match($pattern, $content)) {
                // Update existing variable
                $content = preg_replace($pattern, '$' . $varName . '=' . $escapedValue . ';', $content);
            } else {
                // Add new variable before the closing 
                $content = str_replace('?>', '$' . $varName . '=' . $escapedValue . ';' . PHP_EOL . '?>', $content);
            }
        }
        
        // Write updated content back to file
        file_put_contents($configFile, $content, LOCK_EX);
        
        Logger::info("saveDynamicProfileUpdates: Successfully saved updates for $npcName");
        return true;
        
    } catch (Exception $e) {
        Logger::error("saveDynamicProfileUpdates: Error saving updates for $npcName: " . $e->getMessage());
        return false;
    }
}