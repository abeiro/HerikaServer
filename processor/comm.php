<?php

$MUST_END=false;

$gameRequest[3] = @mb_convert_encoding($gameRequest[3], 'UTF-8', 'UTF-8');

if ($gameRequest[0] == "init") { // Reset responses if init sent (Think about this)
    $now=time();
    $db->delete("eventlog", "gamets>{$gameRequest[2]}  ");
    $db->delete("eventlog", "localts>$now ");
    //$db->delete("eventlog", "type='playerinfo'");
    //$db->delete("quests", "1=1");
    $db->delete("speech", "gamets>{$gameRequest[2]}  ");
    $db->delete("speech", "localts>$now ");
    $db->delete("currentmission", "gamets>{$gameRequest[2]}  ");
    $db->delete("currentmission", "localts>$now   ");
    $db->delete("diarylog", "gamets>{$gameRequest[2]}  ");
    $db->delete("diarylog", "localts>$now ");
    $db->delete("books", "gamets>{$gameRequest[2]}  ");
    $db->delete("books", "localts>$now ");
    $db->delete("responselog", " 1=1 ");

    if ($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) {
        $results = $db->query("select gamets_truncated,uid from memory_summary where gamets_truncated>{$gameRequest[2]}");
        while ($memoryRow = $db->fetchArray($results)) {
            deleteElement($memoryRow["uid"]);
        }
    }
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
    
    error_log("INIT PROCESSING ".(time()-$now).PHP_EOL);
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
    
    error_log("POST INIT PROCESSING ".(time()-$now).PHP_EOL);
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
    error_reporting(E_ALL);

    $questParsedData = explode("@",$gameRequest[3]);
    print_r($questParsedData);
    if (!empty($questParsedData[0])) {
        $data=array(
                'briefing' => $questParsedData[2],
                'data' => $questParsedData[2]
        );
        
        $db->updateRow('quests',$data," id_quest='{$questParsedData[0]}' ");

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
                'companions'=>(isset($speech["companions"])&&is_array($speech["companions"]))?implode(",",$speech["companions"]):"",
                'sess' => 'pending',
                'audios' => isset($speech["audios"])?$speech["audios"]:null,
                'topic' => isset($speech["debug"])?$speech["debug"]:null,
                'localts' => time()
            )
        );
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
    if (isset($GLOBALS["QUEST_COMMENT"])) 
        if ($GLOBALS["QUEST_COMMENT"]===false)
            $MUST_END=true;

} elseif ($gameRequest[0] == "location") {
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
    
    //logEvent($gameRequest);

    $vars=explode("@",$gameRequest[3]);
    $db->delete("conf_opts", "id='".$db->escape($vars[0])."'");
    $db->insert(
        'conf_opts',
        array(
                'id' => $vars[0],
                'value' => $vars[1]
            )
    );
    
    
    $MUST_END=true;
    
} elseif (strpos($gameRequest[0], "info")===0) {    // info_whatever commands

    logEvent($gameRequest);

    $MUST_END=true;

    
} elseif (strpos($gameRequest[0], "addnpc")===0) {    // info_whatever commands
    logEvent($gameRequest);
    
    if (!profile_exists($gameRequest[3]))
        AddFirstTimeMet($gameRequest[3], $momentum, $gameRequest[2],$gameRequest[1]);

    createProfile($gameRequest[3],[],false);
    
    $MUST_END=true;
    
    
} elseif (strpos($gameRequest[0], "updateprofile")===0) {    
    
    if (!$GLOBALS["DYNAMIC_PROFILE"]) {
        $gameRequest[3]="Dynamic profile updating disabled for {$GLOBALS["HERIKA_NAME"]}";
        
        logEvent($gameRequest);
        die();
    }
    
    
    if (!isset($GLOBALS["CONNECTORS_DIARY"]) || !file_exists($enginePath . "connector" . DIRECTORY_SEPARATOR . "{$GLOBALS["CONNECTORS_DIARY"]}.php")) {
            ;
	}
	 else {
		require_once $enginePath . "connector" . DIRECTORY_SEPARATOR . "{$GLOBALS["CONNECTORS_DIARY"]}.php";
        
        $historyData="";
        $lastPlace="";
        $lastListener="";
        foreach (json_decode(DataSpeechJournal($GLOBALS["HERIKA_NAME"],50),true) as $element) {
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
      
          $historyData.=trim("{$element["speaker"]}:".trim($element["speech"])." $listener $place").PHP_EOL;
          
        }
        
        $partyConf=DataGetCurrentPartyConf();
		$partyConfA=json_decode($partyConf,true);
		error_log($partyConf);
		if (isset($partyConfA["{$GLOBALS["HERIKA_NAME"]}"])) {
			$charDesc=print_r($partyConfA["{$GLOBALS["HERIKA_NAME"]}"],true).PHP_EOL.$GLOBALS["HERIKA_PERS"];
			$currentProfile=$charDesc;
		} else
            $currentProfile=$GLOBALS["HERIKA_PERS"];

		$head[]   = ["role"	=> "system", "content"	=> "You are an assistant. Will analyze a dialogue and then you will update a character profile based on that dialogue. ", ];
		$prompt[] = ["role"	=> "user", "content"	=> "* Dialogue history:\n" .$historyData ];
		$prompt[] = ["role"	=> "user", "content"	=> "Current character profile, for reference.:\n$currentProfile", ];
		$prompt[] = ["role"=> "user", "content"	=> "Use Dialogue history to update and summarize character profile. 
Mandatory Format:

* Personality,(concise description, 75 words).
* Bio: (birthplace, gender, race $SHORTER).
* Speech style (15 keywords).
* Current goal (15 keywords).
* Relation with {$GLOBALS["PLAYER_NAME"]} (75 words).
* Likes (15 keywords).
* Fears 15 keywords, pay atention to dramatic past events).
* Dislikes (15 keywords).
* Current mood (15 keywords, use last events to determine). 
* Relation with other followers if any.

Profile must start with the title: 'Roleplay as {$GLOBALS["HERIKA_NAME"]}'.", ];
		$contextData       = array_merge($head, $prompt);
		$connectionHandler = new connector();
        
		$connectionHandler->open($contextData, ["max_tokens"=>500]);
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
		
		
		$responseParsed["HERIKA_PERS"]=$buffer;
        
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
        
            
            file_put_contents($newFile, implode('', $file_lines));
            file_put_contents($newFile, PHP_EOL.'$HERIKA_PERS=\''.addslashes($responseParsed["HERIKA_PERS"]).'\';'.PHP_EOL, FILE_APPEND | LOCK_EX);
            file_put_contents($newFile, '?>'.PHP_EOL, FILE_APPEND | LOCK_EX);
            
        }
    
        //print_r($contextData);
        //print_r($responseParsed["HERIKA_PERS"]);
        $MUST_END=true;
    
    }
}
?>
