<?php



// used for openai_token_count table




function DataDequeue()
{
    global $db;
    //$results = $db->query("select  A.*,ROWID FROM  responselog a  order by ROWID asc");
    $results = $db->fetchAll("select  A.*,ROWID FROM  responselog a WHERE sent=0 order by ROWID asc");
    $finalData = array();
    foreach ($results as $row) {
        $finalData[] = $row;
    }
    if (sizeof($finalData) > 0) {
        $db->query("update responselog set sent=1 where sent=0 and 1=1");
    }

    return $finalData;

}

function DataLastDataFor($actor, $lastNelements = -10)
{
    global $db;
    $lastDialogFull = array();
    $results = $db->fetchAll("select  
    case 
      when type like 'info%' or type like 'death%' or  type like 'funcret%' or type like 'location%' or data like '%background chat%' then 'The Narrator:'
      when type='book' then 'The Narrator: ({$GLOBALS["PLAYER_NAME"]} took the book ' 
      else '' 
    end||a.data  as data 
    FROM  eventlog a WHERE data like '%$actor%' 
    and type<>'combatend'  
    and type<>'bored' and type<>'init' and type<>'lockpicked' and type<>'infonpc' and type<>'infoloc' and type<>'info' and type<>'funcret'  and type<>'quest'
    and type<>'funccall'  and type<>'togglemodel' order by gamets desc,ts desc,localts desc,rowid desc LIMIT 150 OFFSET 0");
    $lastData = "";


    foreach ($results as $row) {

        if ($lastData != md5($row["data"])) {
            if ((strpos($row["data"], "{$GLOBALS["HERIKA_NAME"]}:") !== false) || ((strpos($row["data"], "{$GLOBALS["PLAYER_NAME"]}:") !== false))) {
                $pattern = "/\([^)]*Context location[^)]*\)/"; // Remove (Context location.. from Herikas lines.
                $replacement = "";
                $row["data"] = preg_replace($pattern, $replacement, $row["data"]); // // assistant vs user war
                if ((strpos($row["data"], "{$GLOBALS["HERIKA_NAME"]}:") !== false)) {
                    $role = "assistant";
                } else {
                    $role = "user";
                }

                $lastDialogFull[] = array('role' => $role, 'content' => $row["data"]);

            } else {
                $lastDialogFull[] = array('role' => 'user', 'content' => $row["data"]);
            }

        }
        $lastData = md5($row["data"]);

    }

    // Date issues

    foreach ($lastDialogFull as $n => $line) {

        $pattern = '/(\w+), (\d{1,2}:\d{2} (?:AM|PM)), (\d{1,2})(?:st|nd|rd|th) of ([A-Za-z\ ]+), 4E (\d+)/';
        $replacement = 'Day name: $1, Hour: $2, Day Number: $3, Month: $4, 4th Era, Year: $5';
        $result = preg_replace($pattern, $replacement, $line["content"]);
        $lastDialogFull[$n]["content"] = $result;
    }


    // Clean context locations for Herikas dialog.


    $lastDialogFullReversed = array_reverse($lastDialogFull);
    $lastDialog = array_slice($lastDialogFullReversed, $lastNelements);
    $last_location = null;


    return $lastDialog;

}

function DataLastInfoFor($actor, $lastNelements = -2)
{
    global $db;
    $lastDialogFull = array();
    $results = $db->fetchAll("select  case when type like 'info%' then 'The Narrator:' else '' end||a.data  as data  FROM  eventlog a 
    WHERE data like '%$actor%' and type in ('infoloc','infonpc','location')  order by gamets desc,ts desc LIMIT 50 OFFSET 0");
    $lastData = "";
    foreach ($results as $row) {
        if ($lastData != md5($row["data"])) {
            $lastDialogFull[] = array('role' => 'user', 'content' => $row["data"]);
        }
        $lastData = md5($row["data"]);
    }

    $lastDialogFullReversed = array_reverse($lastDialogFull);
    $lastDialog = array_slice($lastDialogFullReversed, $lastNelements);
    $last_location = null;

    // Remove Context Location part when repeated
    foreach ($lastDialog as $k => $message) {
        preg_match('/\(Context location: (.*)\)/', $message['content'], $matches);
        $current_location = isset($matches[1]) ? $matches[1] : null;
        if ($current_location === $last_location) {
            $message['content'] = preg_replace('/\(Context location: (.*)\)/', '', $message['content']);
        } else {
            $last_location = $current_location;
        }
        $lastDialog[$k]["content"] = $message['content'];
    }


    foreach ($lastDialog as $n => $line) {

        $pattern = '/(\w+), (\d{1,2}:\d{2} (?:AM|PM)), (\d{1,2})(?:st|nd|rd|th) of ([A-Za-z\ ]+), 4E (\d+)/';
        $replacement = 'Day name: $1, Hour: $2, Day Number: $3, Month: $4, 4th Era, Year: $5';
        $result = preg_replace($pattern, $replacement, $line["content"]);
        $lastDialogFull[$n]["content"] = $result;
    }

    return $lastDialog;

}

function DataPosibleLocationsToGo()
{
    global $db;
    $lastDialogFull = array();
    $results = $db->fetchAll("select  a.data  as data  FROM  eventlog a 
    WHERE type in ('infoloc')  order by gamets desc,ts desc LIMIT 50 OFFSET 0");
    $lastData = "";
    $retData = [];
    foreach ($results as $row) {
        //$row = $results->fetchArray();

        $re = '/(to go:)(.+),,/';

        preg_match($re, $row["data"], $matches, PREG_OFFSET_CAPTURE, 0);
        if (isset($matches[2])) {
            $retData = explode(",", $matches[2][0]);
        }
        ;
        break;
    }

    //print_r($matches);

    $results = $db->fetchAll("select  a.data  as data  FROM  eventlog a 
    WHERE type in ('infonpc')  order by gamets desc,ts desc LIMIT 50 OFFSET 0");
    $lastData = "";
    $matches = [];
    foreach ($results as $row) {
        //$row = $results->fetchArray();

        $pattern = "/Herika can see this beings in range:(.*)/";
        preg_match_all($pattern, $row["data"], $matches);

        if (!empty($matches) && !empty($matches[1]) && isset($matches[1][0])) {
            $retData = array_merge($retData, explode(",", $matches[1][0]));
        }

        //print_r($matches);
        break;
    }

    foreach ($retData as $k => $v) {
        if (strlen($v) < 2) {
            unset($retData[$k]);
        } else {
            $retData[$k] = preg_replace("/\([^)]+\)/", '', $v);
            //$retData[$k]=$v;

        }

    }
    //return ["Goldenglow Estate","Faldar's Tooth","Goldenglow Estate Sewer","Pit Wolf(dead)","Pit Wolf(dead)","Herika"];
    return array_values($retData);
}

function DataPosibleInspectTargets($pack=true)
{
    global $db;
    $results = $db->fetchAll("select  a.data  as data  FROM  eventlog a 
    WHERE type in ('infonpc')  order by gamets desc,ts desc LIMIT 50 OFFSET 0");
    $lastData = "";
    $matches = [];
    foreach ($results as $row) {
        //$row = $results->fetchArray();

        $pattern = "/beings in range:(.*)/";
        preg_match_all($pattern, $row["data"], $matches);

        if (!empty($matches) && !empty($matches[1]) && isset($matches[1][0])) {
            $retData = explode(",", $matches[1][0]);
        }


        break;
    }

    
    
    if (!isset($retData)||!is_array($retData)) {
        $retData = [];
    }

    $compData=[];

    if ($pack) {
        foreach ($retData as $k => $v) {
            if (strlen($v) < 2) {
                unset($retData[$k]);
            } else {
                $retData[$k] = preg_replace("/\([^)]+\)/", '', $v);
                $retData[$k] = $v;
                if (!isset($compData[$v]))
                    $compData[$v]=0;
                $compData[$v]++; // Reduce same names (Chicken, Chicken -> Chicken)
                //$retData[$k]=$v;

            }

        }
        $retData=[];
        foreach ($compData as $l=>$n) {
            if ($n==1)
                $retData[]="$l";
            else
                $retData[]="$n $l";
        }

        
    }

    return array_values($retData);
}

function DataQuestJournal($quest)
{
    global $db;
    if (empty($quest)||($quest=="None")||true) {
        
        $results = $db->fetchAll("SElECT name,id_quest,briefing,'pending' as status FROM quests");
        $finalRow = [];
        foreach ($results as $row) {
            if (isset($finalRow[$row["id_quest"]])) {
                continue;
            } else {
                $finalRow[$row["id_quest"]] = $row;
            }
        }

        if (sizeof($finalRow) == 0) {
            $data[] = "no active quests";
        } else {
            $data = array_values($finalRow);
        }

        $extraData = DataGetCurrentTask();

        $data[] = ["side note" => "$extraData"];

        return json_encode($data);

    } else {
        $lastDialogFull = array();
        $results = $db->fetchAll("SElECT  name,id_quest,briefing,data
      FROM quests where lower(id_quest)=lower('$quest') or lower(name)=lower('$quest') ");
        $lastOne = -1;
        $data = array();
        if (!$results) {
            $data["error"] = "quest not found, make sure you use id_quest";
            return json_encode($data);

        }
        foreach ($results as $row) {
            $lastOne++;
            $data[] = $row;
        }
        if ($lastOne >= 0) {
            $data[$lastOne]["stage_completed"] = "no";
        }

        if (sizeof($data) == 0) {
            $data["error"] = "quest not found, make sure you use id_quest";

        }

        return json_encode($data);

    }
}

function removeTalkingToOccurrences($input) {
    $pattern = '/\(talking to [^()]+\)/';
    preg_match_all($pattern, $input, $matches, PREG_OFFSET_CAPTURE);

    // Get all positions of the matches
    $positions = $matches[0];

    // If there are no matches or only one match, return the input string as it is
    if (count($positions) <= 1) {
        return $input;
    }

    // Remove all but the last occurrence
    for ($i = 0; $i < count($positions) - 1; $i++) {
        $pos = $positions[$i][1];
        $input = substr_replace($input, '', $pos, strlen($positions[$i][0]));
        
        // After each removal, adjust the positions of subsequent matches
        for ($j = $i + 1; $j < count($positions); $j++) {
            $positions[$j][1] -= strlen($positions[$i][0]);
        }
    }

    return $input;
}


function DataLastDataExpandedFor($actor, $lastNelements = -10,$sqlfilter="")
{

    global $db;

    $currentGameTs=$GLOBALS["gameRequest"][2]+0;
    if ($GLOBALS["gameRequest"][0]=="chatnf_book") {
        $removeBooks="";
    } else {
        $removeBooks ="and type<>'contentbook' " ;
    }
    
    $lastDialogFull = array();
    $results = $db->fetchAll("select  
    case 
      when type like 'info%' or type like 'death%' or  type like 'funcret%' or type like 'location%'  then 'The Narrator:'
      when a.data like '%background chat%' then 'The Narrator: background dialogue: '
      when type='book' then 'The Narrator: ({$GLOBALS["PLAYER_NAME"]} took the book ' 
      else '' 
    end||a.data  as data , gamets,localts,type
    FROM  eventlog a WHERE data like '%$actor%' 
    and type<>'combatend'  
    and type<>'bored' and type<>'init' and type<>'infonpc' and type<>'infoloc' and type<>'info' and type<>'funcret' and type<>'book' and type<>'addnpc' 
    and type<>'updateprofile' and type<>'rechat' and type<>'setconf'
    and type<>'funccall' $removeBooks  and type<>'togglemodel' $sqlfilter  
    and gamets>".($currentGameTs-(60*60*60*60))."
    order by gamets desc,ts desc,rowid desc LIMIT 150 OFFSET 0");

    $rawData=[];
    foreach ($results as $row) {
        $rawData[md5($row["data"].$row["localts"])] = $row;
    }

    
    $orderedData = array_reverse($rawData);

    //$orderedData = array_slice($orderedData, $lastNelements);

    $currentLocation = "";
    $writeLocation = true;

    $currentSpeaker = "user";
    $buffer = [];
    $timeStampBuffer = [];

    foreach ($orderedData as $row) {
        $rowData = $row["data"];
        // Extract location
        $pattern = '/\(Context location: (.*?),(.*?)\)/';

        if (preg_match($pattern, str_replace(" background dialogue", "", $rowData), $matches)) {

            $contextLocation = $matches[0];
            if ($currentLocation != $contextLocation) {
                $currentLocation = $contextLocation;
                $writeLocation = true;
            } else {
                $writeLocation = false;
            }

        } else {

        }

        if (!$writeLocation) {
            $pattern = "/\([^)]*Context location[^)]*\)/";
            $rowData = preg_replace($pattern, "", $rowData); // Remove context location if repeated
        }

        // This is used for compacting.
        
        if (($row["type"]=="logaction") && (strpos($rowData, "{$GLOBALS["HERIKA_NAME"]}") !== false))  {
            $speaker = "assistant";
            
        } else if ($row["type"]=="vision") {
            $speaker = "user";
            
        } else if ((strpos($rowData, "{$GLOBALS["HERIKA_NAME"]}:") !== false)) {
            $speaker = "assistant";
            
        } 
         elseif ((strpos($rowData, "{$GLOBALS["PLAYER_NAME"]}:") !== false)) {
            $speaker = "player";
            
        } else {
            $speaker = "user";
            
        }



        if (($currentSpeaker == $speaker) && ($speaker == "assistant") && $row["type"]!="logaction") {
            $buffer[] = $rowData;
        } else {
            if (sizeof($buffer) > 0) {
                $lastDialogFull[] = array('role' => $currentSpeaker, 'content' => implode("\n", $buffer));
            }
            $buffer = [];
            $buffer[] = $rowData;
            $currentSpeaker = $speaker;
        }

        if ($GLOBALS["FEATURES"]["MISC"]["ADD_TIME_MARKS"]) {
            $hoursAgo=round(($currentGameTs-$row["gamets"])/ (60*60 * 20), 0);
            if ($hoursAgo>12) {
                if (!isset($timeStampBuffer[$hoursAgo])) {
                    if ($currentLocation) {
                        $timeStampBuffer[$hoursAgo]="set";
                        $lastDialogFull[] = array('role' => "user", 'content' => "The Narrator: SCENARIO CHANGE, $currentLocation, timeline mark: $hoursAgo hours ago  ");
                    }
                }
            }
        }

    }

    // if (($currentGameTs-$row["gamets"])>600) {


    //}

    $lastDialogFull[] = array('role' => $currentSpeaker, 'content' => implode("\n", $buffer));

    // Compact Herika's lines
    foreach ($lastDialogFull as $n => $line) {
        if ($line["role"] == "assistant") {
            $pattern = "/\([^)]*Context location[^)]*\)/";
            $cleanedText = trim(preg_replace($pattern, "", $line["content"])); // Remove context location always for assistant
            // This breaks with spaces?
            $re = '/[^(' . strtr($GLOBALS["HERIKA_NAME"],["-"=>'\-']) . ':)].*(' . strtr($GLOBALS["HERIKA_NAME"],["-"=>'\-']) . ':)/m';
            $subst = "";
            $cleanedText = preg_replace($re, $subst, $cleanedText);
            
            
            $cleanedText = removeTalkingToOccurrences($cleanedText);
            
            $lastDialogFull[$n]["content"] = $cleanedText;
        }

    }

    // Replace player for user.
    foreach ($lastDialogFull as $n => $line) {
        if ($line["role"] == "player") {
            $lastDialogFull[$n]["role"] = "user";
        }
    }

    // Date issues

    foreach ($lastDialogFull as $n => $line) {

        $pattern = '/(\w+), (\d{1,2}:\d{2} (?:AM|PM)), (\d{1,2})(?:st|nd|rd|th) of ([A-Za-z\ ]+), 4E (\d+)/';
        $replacement = 'Day name: $1, Hour: $2, Day Number: $3, Month: $4, 4th Era, Year: $5';
        $result = preg_replace($pattern, $replacement, $line["content"]);
        $lastDialogFull[$n]["content"] = $result;
    }


    $orderedData = array_slice($lastDialogFull, $lastNelements);

    return $orderedData;

}

function DataSpeechJournal($topic,$limit=25)
{

    global $db;

    $lastDialogFull = [];
    $results = $db->fetchAll("SElECT  speaker,speech,location,listener,topic as quest FROM speech
      where (speaker like '%$topic%' or  listener like '%$topic%' or location like '%$topic%' or  companions like '%$topic%') order by rowid desc");
    if (!$results) {
        return json_encode([]);
    }

    $data = [];

    foreach ($results as $row) {
        $data[] = $row;
    }

    if (sizeof($data) == 0) {
        return json_encode([]);
    } elseif (sizeof($data) < $limit) {
        $dataReversed = array_reverse($data);
    } else {
        $smalldata = array_slice($data, 0,$limit);
        $dataReversed = array_reverse($smalldata);
    }


    return json_encode($dataReversed);

}

/*
 * Diary functions are attached to FTS queries, Should be driver agnostic. work on this
 * */
function DataDiaryLog($topic)
{

    global $db;
    /*
    $results = $db->query("SElECT  topic,content,tags,people  FROM diarylog
    where (tags like '%$topic%' or topic like '%$topic%' or people like '%$topic%') order by gamets asc");
    */
    $topicTok = explode(" ", strtr($topic, array("'" => "")));
    $topicFmt = implode(" OR ", $topicTok);
    $results = $db->fetchAll(SQLite3::escapeString("SElECT  topic as page,content,tags,people  FROM diarylogv2
      where (tags MATCH \"$topicFmt\" or topic MATCH \"$topicFmt\" or content MATCH \"$topicFmt\" or people MATCH \"$topicFmt\") ORDER BY rank"));


    if (!$results) { // No match, will return a list of current memories
        $results = $db->fetchAll(SQLite3::escapeString("SElECT  topic as page,tags  FROM diarylogv2 order by rowid asc"));

        if (!$results) {
            return json_encode([]);
        }

        $data = [];

        foreach ($results as $row) {
            $data[] = $row;
        }

        return json_encode(["return value" => "Page not found", "similar pages" => $data]);


    } else { // Return best matching memory

        file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "data". DIRECTORY_SEPARATOR ."logquery.txt", SQLite3::escapeString("\nSElECT  topic,content,tags,people  FROM diarylogv2
        where (tags MATCH \"$topicFmt\" or topic MATCH \"$topicFmt\" or content MATCH \"$topicFmt\" or people MATCH \"$topicFmt\") ORDER BY rank"), FILE_APPEND);

        $data = [];
        foreach ($results as $row) {
            $data[] = $row;
            break; // Only space for one memory
        }

    }

    if (sizeof($data) == 0) { // No match, will return a list of current memories. Revise limits

        $results = $db->fetchAll(SQLite3::escapeString("SElECT  topic as page  FROM diarylogv2 order by rowid asc"));

        $data = [];

        foreach ($results as $row) {
            $data[] = $row;
        }

        return json_encode(["return value" => "Page not found", "available pages" => $data]);
    }

    return json_encode($data);


}


function DataDiaryLogIndex($topic)
{

    global $db;
    //$results = $db->query('SElECT  topic,tags  FROM diarylogv2 where tags  MATCH NEAR(\'one two\' \'three four\', 10) order by rank');
    $preData = $db->fetchAll("SElECT  topic as page,tags,people  FROM diarylogv2 where tags  MATCH 'NEAR(\"$topic\")' or topic  MATCH 'NEAR(\"$topic\")' or people  MATCH 'NEAR(\"$topic\")'  order by rank");
    //$preData=  self::fetchAll("SElECT  topic,tags,people  FROM diarylogv2 where tags  MATCH \"$topic\" order by rank");
    if (sizeof($preData) == 0) {
        $preData = $db->fetchAll("SElECT  topic as page,tags,people  FROM diarylogv2 where tags  like '%$topic%'  or topic  like '%$topic%' or people  like '%$topic%'");
        if (sizeof($preData) == 0) {
            $results = $db->fetchAll(SQLite3::escapeString("SElECT  topic as page,tags,people  FROM diarylogv2 order by rowid asc"));
            $data = [];

            foreach ($results as $row) {
                $data[] = $row;
            }
        } else {
            $data = $preData;
        }

    } else {

        $data = $preData;

    }


    return json_encode($data);

}


function DataGetCurrentTask()
{
    global $db;
    $results = $db->fetchAll("SElECT  distinct description as description,gamets FROM currentmission order by gamets desc");
    if (!$results) {
        $results=[];
    }

    $data = "";

    $n = 0;
    foreach ($results as $row) {

        if ($n == 0) {
            $data = "Last task/quest/plan: {$row["description"]}.";
        } elseif ($n == 1) {
            $data .= "Previous task/quest/plan: {$row["description"]}.";
        } else {
            break;
        }
        $n++;
    }

    $results = $db->fetchAll("SElECT  distinct name,briefing as description,gamets FROM quests order by gamets desc");
    if (!$results) {
        error_log("No quests ".__FILE__);
        return $data;
    }
    
    if (sizeof($results)>2) {
        error_log("Too much quests ".__FILE__);
        return $data;
    }

    $data = "";

    $n = 0;
    foreach ($results as $row) {

        if ($n == 0) {
            $data = "Current task/quest/plan: {$row["name"]}/{$row["description"]}.";
        } elseif ($n == 1) {
            $data .= "Previous task/quest/plan: {$row["name"]}/{$row["description"]}.";
        } else {
            break;
        }
        $n++;
    }
    
    return $data;

}


function DataLastRetFunc($actor, $lastNelements = -2)
{
    global $db;
    $lastDialogFull = array();
    $results = $db->fetchAll("select  a.data  as data  FROM  eventlog a 
    WHERE data like '%$actor%' and type in ('funcret')  order by gamets desc,ts desc LIMIT 1 OFFSET 0");
    $lastData = "";
    foreach ($results as $row) {
        $pattern = "/\{(.*?)\(/";
        preg_match($pattern, $row["data"], $matches);
        $functionName = $matches[1];
        $lastDialogFull[] = array('role' => 'function', 'name' => $functionName, 'content' => $row["data"]);

    }

    $lastDialogFullReversed = array_reverse($lastDialogFull);
    $lastDialog = array_slice($lastDialogFullReversed, $lastNelements);
    $last_location = null;

    // Remove Context Location part when repeated
    foreach ($lastDialog as $k => $message) {
        preg_match('/\(Context location: (.*)\)/', $message['content'], $matches);
        $current_location = isset($matches[1]) ? $matches[1] : null;
        if ($current_location === $last_location) {
            $message['content'] = preg_replace('/\(Context location: (.*)\)/', '', $message['content']);
        } else {
            $last_location = $current_location;
        }
        $lastDialog[$k]["content"] = $message['content'];
    }


    return $lastDialog;

}

function DataLastKnowDate()
{

    global $db;

    $lastLoc=$db->fetchAll("select  a.data  as data  FROM  eventlog a  WHERE type in ('infoloc')  order by gamets desc,ts desc LIMIT 1 OFFSET 0");
    if (!is_array($lastLoc) || sizeof($lastLoc)==0) {
        return "";
    }
    $re = '/(\w+), (\d{1,2}:\d{2} (?:AM|PM)), (\d{1,2})(?:st|nd|rd|th) of ([A-Za-z\ ]+), 4E (\d+)/';
    preg_match($re, $lastLoc[0]["data"], $matches, PREG_OFFSET_CAPTURE, 0);
    return $matches[0][0];

}

function DataLastKnownLocation()
{

    global $db;

    $lastLoc=$db->fetchAll("select  a.data  as data  FROM  eventlog a  WHERE type in ('infoloc') and data like '%(Context%'  order by gamets desc,ts desc LIMIT 1 OFFSET 0");
    if (!is_array($lastLoc) || sizeof($lastLoc)==0) {
        return "";
    }
    $re = '/Context location: ([\w\ \']*)/';
    preg_match($re, $lastLoc[0]["data"], $matches, PREG_OFFSET_CAPTURE, 0);
    return $matches[1][0];

}

function PackIntoSummary()
{

    global $db;

    $results = $db->fetchAll("select max(gamets_truncated) as gamets_truncated from memory_summary");

    $maxRow=$results[0]["gamets_truncated"]+0;

    $pfi=($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["AUTO_CREATE_SUMMARY_INTERVAL"]+0)*100000;

    $results = $db->query("insert into memory_summary select * from ( 
								select max(gamets) as gamets_truncated,count(*) as n,
                                STRING_AGG(message, chr(13) || chr(10) || chr(13) || chr(10)) AS packed_message,
                                NULL,'dialogue',max(uid) as uid
								from memory_v
								where 
								message not like 'Dear Diary%'
                                and NOT ( speaker like '%".SQLite3::escapeString($GLOBALS["HERIKA_NAME"])."%'
                                    OR (listener like '%".SQLite3::escapeString($GLOBALS["HERIKA_NAME"])."%' and speaker like '%".SQLite3::escapeString($GLOBALS["PLAYER_NAME"])."%' )
                                )
								group by round(gamets/$pfi ,0) HAVING max(uid)>0 order by round(gamets/$pfi ,0) ASC
							  ) where gamets_truncated>$maxRow
							");
    
 
    //$results = $db->query("delete from memory_summary  where classifier='dialogue' and packed_message not like '%Context%Location%'");
    
    $results = $db->query("insert into memory_summary (gamets_truncated,n,packed_message,summary,classifier,uid,companions)
								select gamets,1,message,message,'diary',uid,speaker
								from memory
								where event='diary'
								and gamets>$maxRow
							");

    return $maxRow;
}

function DataRechatHistory()
{

    global $db;

    $lastRechat=$db->fetchAll("select  a.data  as data  FROM  eventlog a  WHERE type in ('rechat','inputtext','inputtext_s') 
    and localts>".(time()-120)."  order by gamets desc,ts desc LIMIT 10 OFFSET 0");
    
    return $lastRechat;

}



 function extractDialogueTarget($string) {
        // Check if the string contains "(talking to"
        if (strpos($string, '(talking to') !== false) {
            // Extract the target's name using regular expression
            preg_match('/\(talking to ([^\)]+)\)/', $string, $matches);
            
            // Check if a match is found and extract the target's name
            if (isset($matches[1])) {
                $target = $matches[1];

                // Remove the "(talking to ...)" part from the original string
                $cleanedString = preg_replace('/\(talking to [^\)]+\)/', '', $string);
                if (strpos($cleanedString,"{$GLOBALS["HERIKA_NAME"]}:")===0) {
                    $cleanedString=str_replace("{$GLOBALS["HERIKA_NAME"]}:","",$cleanedString);
                }
                
                return ['target' => $target, 'cleanedString' => trim($cleanedString)];
            }
        }

        // Return the original string if no target is found
        return ['target' => null, 'cleanedString' => $string];
}

function DataGetLastReadedBook() {
    global $db;

    $results = $db->fetchAll("select content from books where content is not null
    order by gamets desc,ts desc,localts desc,rowid desc LIMIT 1 OFFSET 0");
    $lastData = "";
    
    $bookOnlyContext[] = array('role' => "user", 'content' => $results[0]["content"]);

    return $bookOnlyContext;
    
}


function GetAnimationHex($mood)
{

    //error_log("MOOD:".$mood);
    $ANIMATIONS=[
        "ArmsCrossed"=>"IdleExamine",        // Arms crossed
        "PointClose"=>"IdlePointClose",
        "HandsBehindBack"=>"IdleHandsBehindBack",    // 000B240A ? // Arms behind back
        //"DrawAttention"=>"0x0006FF15",     // Continous
        //"Cheer"=>"0x00066374",             // Continous
        "ApplauseSarcastic"=>"IdleApplaudSarcastic",  // Continous
        "WaveHand"=>"IdleWave",
        "Nervous"=>"IdleNervous",
        "ArmsRaised"=>"IdleSurrender",
        "NervousDialogue"=>"IdleDialogueMovingTalkA",
        "NervousDialogue1"=>"IdleDialogueMovingTalkB",
        "NervousDialogue2"=>"IdleDialogueMovingTalkC",
        "NervousDialogue3"=>"IdleDialogueMovingTalkD",
        "Cheer"=>"SpectatorCheer",
        "ComeThisWay"=>"IdleComeThisWay",
        "SarcasticMove"=>"IdleDialogueExpressiveStart",
        "Applause1"=>"IdleApplaud2",
        "Applause2"=>"IdleApplaud3",
        "Applause3"=>"IdleApplaud4",
        "Applause4"=>"IdleApplaud5",
        "DrinkPotion"=>"IdleDrinkPotion",        // Don't use while talking
        "PointFar"=>"IdlePointFar_01",
        "PointFar2"=>"IdlePointFar_02",
        "GiveSomething"=>"IdleGive",
        "TakeSomething"=>"IdleTake",
        "Salute"=>"IdleSalute",
        "CleanSweat"=>"IdleWipeBrow",
        "NoteRead"=>"IdleNoteRead",
        "LookFar"=>"IdleLookFar",
        "Laugh"=>"IdleLaugh",
        "CleanSword"=>"IdleCleanSword",
        "WarmArms"=>"IdleWarmArms",
        "Positive"=>"LooseDialogueResponsePositive",
        "Negative"=>"LooseDialogueResponseNegative",
        "HappyDialogue"=>"IdleDialogueHappyStart",
        "AngryDialogue"=>"IdleDialogueAngryStart",
        "Agitated"=>"IdleCiceroAgitated",
        "HandOnChinGesture"=>"IdleDialogueHandOnChinGesture",
        
    ];
    
    if ($mood=="sarcastic") {
        return array_rand(array_flip([$ANIMATIONS["SarcasticMove"],$ANIMATIONS["CleanSweat"],$ANIMATIONS["Agitated"],$ANIMATIONS["ApplauseSarcastic"]]), 1);
        
        
    } else if ($mood=="sassy") {
        return array_rand(array_flip([$ANIMATIONS["SarcasticMove"],$ANIMATIONS["CleanSweat"],$ANIMATIONS["Agitated"],$ANIMATIONS["ApplauseSarcastic"]]), 1);
        
        
    } else if ($mood=="sardonic") {
        return array_rand(array_flip([$ANIMATIONS["SarcasticMove"],$ANIMATIONS["CleanSweat"],$ANIMATIONS["Agitated"],$ANIMATIONS["ApplauseSarcastic"]]), 1);
        
        
    } else if ($mood=="irritated") {
        return array_rand(array_flip([$ANIMATIONS["PointClose"],$ANIMATIONS["Negative"],$ANIMATIONS["AngryDialogue"]]), 1);
       
        
    } else if ($mood=="mocking") {
        return array_rand(array_flip([$ANIMATIONS["Applause1"],$ANIMATIONS["Applause2"],$ANIMATIONS["Applause3"],$ANIMATIONS["Applause4"]]), 1);
        
        
    } else if ($mood=="playful") {
        return array_rand(array_flip([$ANIMATIONS["Cheer"],$ANIMATIONS["HappyDialogue"]]), 1);
            
    } else if ($mood=="teasing") {
        return array_rand(array_flip([$ANIMATIONS["NervousDialogue"],$ANIMATIONS["NervousDialogue1"],$ANIMATIONS["NervousDialogue2"],$ANIMATIONS["NervousDialogue3"]]), 1);
        
        
    } else if ($mood=="smug") {
        return $ANIMATIONS["Nervous"];
        
        
    } else if ($mood=="amused") {
        return $ANIMATIONS["ArmsRaised"];
        
    } else if ($mood=="smirking") {
        return $ANIMATIONS["Nervous"];
    
        
    } else if ($mood=="serious") {
        return array_rand(array_flip([$ANIMATIONS["CleanSweat"],$ANIMATIONS["PointClose"],$ANIMATIONS["HandOnChinGesture"]]), 1);
    
        
    } else if ($mood=="firm") {
        return array_rand(array_flip([$ANIMATIONS["CleanSweat"],$ANIMATIONS["PointClose"],$ANIMATIONS["HandOnChinGesture"]]), 1);
    
        
    } if ($mood=="neutral") {
        return array_rand(array_flip([$ANIMATIONS["HappyDialogue"]]), 1);
        
        
    }
                            
    
    
    return "";

}
?>

