<?php

$MUST_END=false;

$gameRequest[3] = @mb_convert_encoding($gameRequest[3], 'UTF-8', 'UTF-8');

if ($gameRequest[0] == "init") { // Reset reponses if init sent (Think about this)
  
    $db->delete("eventlog", "gamets>{$gameRequest[2]}  ");
    $db->delete("quests", "1=1");
    $db->delete("speech", "gamets>{$gameRequest[2]}  ");
    $db->delete("currentmission", "gamets>{$gameRequest[2]}  ");
    $db->delete("diarylog", "gamets>{$gameRequest[2]}  ");
    $db->delete("books", "gamets>{$gameRequest[2]}  ");
    
    if ($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) {
        $results = $db->query("select gamets_truncated,uid from memory_summary where gamets_truncated>{$gameRequest[2]}");
        while ($memoryRow = $results->fetchArray(SQLITE3_ASSOC)) {
            deleteElement($memoryRow["uid"]);
        }
    }
    $db->delete("memory_summary", "gamets_truncated>{$gameRequest[2]}  ");
    $db->delete("memory", "gamets>{$gameRequest[2]}  ");

    $db->delete("diarylogv2", "true");
    $db->execQuery("insert into diarylogv2 select topic,content,tags,people,location from diarylog");
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
                if (strpos($filePath,".placeholder")!==false)
                    continue;
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
                'giver_actor_id' => isset($questParsedData["data"]["questgiver"])?$questParsedData["data"]["questgiver"]:"",
                'id_quest' => $questParsedData["formId"],
                'sess' => 'pending',
                'status' => isset($questParsedData["status"])?$questParsedData["status"]:"",
                'localts' => time()
            )
        );

    }
    $MUST_END=true;



} elseif ($gameRequest[0] == "_questreset") {
    error_reporting(E_ALL);
    $db->delete("quests", "1=1");
    $MUST_END=true;


} elseif ($gameRequest[0] == "_speech") {
    error_reporting(E_ALL);
    $speech = json_decode($gameRequest[3], true);
    //print_r($questParsedData);

    $db->insert(
        'speech',
        array(
            'ts' => $gameRequest[1],
            'gamets' => $gameRequest[2],
            'listener' => $speech["listener"],
            'speaker' => $speech["speaker"],
            'speech' => $speech["speech"],
            'location' => $speech["location"],
            'sess' => 'pending',
            'localts' => time()
        )
    );
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

} else if ($gameRequest[0] == "togglemodel") {
	
	$newModel=DMtoggleModel();
	echo "Herika|command|ToggleModel@$newModel\r\n";
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

} else if ($gameRequest[0] == "death") {
	
    $MUST_END=true;

} else if ($gameRequest[0] == "quest") {
    //13333334
    if (($gameRequest[2]>13333334)||($gameRequest[2]<13333332))  // ?? How this works.
        logEvent($gameRequest);
    //$MUST_END=true;

} else if ($gameRequest[0] == "location") {
    logEvent($gameRequest);	
    $MUST_END=true;

} else if ($gameRequest[0] == "force_current_task") {
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
} else if (strpos($gameRequest[0],"info")===0) {    // info_whatever commands
	
   logEvent($gameRequest);
    
    $MUST_END=true;
}
