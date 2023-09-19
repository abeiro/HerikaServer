<?php

class sql
{
    private static $link = null;


    public function __construct()
    {
        self::$link = new SQLite3(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR ."data". DIRECTORY_SEPARATOR . "mysqlitedb.db");
        self::$link->busyTimeOut(5000);

    }

    public function __destruct()
    {
        self::$link->close();
    }


    public function insert($table, $data)
    {

        self::$link->exec("INSERT INTO $table (" . implode(",", array_keys($data)) . ") VALUES ('" . implode("','", $data) . "')");


    }

    // used for openai_token_count table
    function insert_and_calc_totals($table, $data)
    {
        // Fetch the last row
        $latestRowQuery = "SELECT * FROM $table ORDER BY ROWID DESC LIMIT 1";
        $latestRowResult = self::fetchAll($latestRowQuery);

        if (!empty($latestRowResult)) {
            // If the table is not empty
            $latestRow = $latestRowResult[0];

            // Calculate totals
            $data['total_tokens_so_far'] = $latestRow['total_tokens_so_far'] + $data['input_tokens'] + $data['output_tokens'];
            $data['total_cost_so_far_USD'] = $latestRow['total_cost_so_far_USD'] + $data['cost_USD'];
        } else {
            // If the table is empty
            $data['total_tokens_so_far'] = $data['input_tokens'] + $data['output_tokens'];
            $data['total_cost_so_far_USD'] = $data['cost_USD'];
        }

        // Insert new row
        self::$link->exec("INSERT INTO $table (" . implode(",", array_keys($data)) . ") VALUES ('" . implode("','", $data) . "')");
    }

    function query($query)
    {

        return self::$link->query($query);
    }

    public function delete($table, $where = " false ")
    {
        self::$link->exec("DELETE FROM  $table WHERE $where");
    }

    public function update($table, $set, $where = " false ")
    {
        self::$link->exec("UPDATE  $table set $set WHERE $where");
    }

    public function execQuery($sqlquery)
    {
        self::$link->exec($sqlquery);
    }


    public function fetchAll($q)
    {

        $results = self::$link->query("$q");
        $finalData = array();
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $finalData[] = $row;
        }

        return $finalData;

    }


    public function dequeue()
    {

        //$results = self::$link->query("select  A.*,ROWID FROM  responselog a  order by ROWID asc");
        $results = self::$link->query("select  A.*,ROWID FROM  responselog a WHERE sent=0 order by ROWID asc");
        $finalData = array();
        while ($row = $results->fetchArray()) {
            $finalData[] = $row;
        }
        if (sizeof($finalData) > 0) {
            self::$link->query("update responselog set sent=1 where sent=0 and 1=1");
        }

        return $finalData;

    }

    public function lastDataFor($actor, $lastNelements = -10)
    {
        $lastDialogFull = array();
        $results = self::$link->query("select  
    case 
      when type like 'info%' or type like 'death%' or  type like 'funcret%' or type like 'location%' or data like '%background chat%' then 'The Narrator:'
      when type='book' then 'The Narrator: ({$GLOBALS["PLAYER_NAME"]} took the book ' 
      else '' 
    end||a.data  as data 
    FROM  eventlog a WHERE data like '%$actor%' 
    and type<>'combatend'  
    and type<>'bored' and type<>'init' and type<>'lockpicked' and type<>'infonpc' and type<>'infoloc' and type<>'info' and type<>'funcret'  and type<>'quest'
    and type<>'funccall'  and type<>'togglemodel' order by gamets desc,ts desc,localts desc LIMIT 0,50");
        $lastData = "";


        while ($row = $results->fetchArray()) {

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

    public function lastInfoFor($actor, $lastNelements = -2)
    {
        $lastDialogFull = array();
        $results = self::$link->query("select  case when type like 'info%' then 'The Narrator:' else '' end||a.data  as data  FROM  eventlog a 
    WHERE data like '%$actor%' and type in ('infoloc','infonpc')  order by gamets desc,ts desc LIMIT 0,50");
        $lastData = "";
        while ($row = $results->fetchArray()) {
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

    public function posibleLocationsToGo()
    {
        $lastDialogFull = array();
        $results = self::$link->query("select  a.data  as data  FROM  eventlog a 
    WHERE type in ('infoloc')  order by gamets desc,ts desc LIMIT 0,50");
        $lastData = "";
        $retData = [];
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
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

        $results = self::$link->query("select  a.data  as data  FROM  eventlog a 
    WHERE type in ('infonpc')  order by gamets desc,ts desc LIMIT 0,50");
        $lastData = "";
        $matches = [];
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
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

    public function posibleInspectTargets()
    {
        $results = self::$link->query("select  a.data  as data  FROM  eventlog a 
    WHERE type in ('infonpc')  order by gamets desc,ts desc LIMIT 0,50");
        $lastData = "";
        $matches = [];
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            //$row = $results->fetchArray();

            $pattern = "/Herika can see this beings in range:(.*)/";
            preg_match_all($pattern, $row["data"], $matches);

            if (!empty($matches) && !empty($matches[1]) && isset($matches[1][0])) {
                $retData = explode(",", $matches[1][0]);
            }


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
        if (!is_array($retData)) {
            $retData = [];
        }
        return array_values($retData);
    }

    public function questJournal($quest)
    {
        global $db;
        if (empty($quest)) {
            /*$lastDialogFull = array();
            $results = self::$link->query("SElECT  distinct name,id_quest,briefing,giver_actor_id
                  FROM quests where coalesce(status,'pending')<>'completed' and stage<200 order by id_quest");
            if (!$results)
              return "no result";
            $data=[];
            while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
              $data[] = $row;
            }
            if (sizeof($data)==0)
              $data[] = "no active quests";

            return json_encode($data);
            */
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

            $extraData = $db->get_current_task();

            $data[] = ["side note" => "$extraData"];

            return json_encode($data);

        } else {
            $lastDialogFull = array();
            $results = self::$link->query("SElECT  name,id_quest,briefing,data
      FROM quests where lower(id_quest)=lower('$quest') or lower(name)=lower('$quest') ");
            $lastOne = -1;
            $data = array();
            if (!$results) {
                $data["error"] = "quest not found, make sure you use id_quest";
                return json_encode($data);

            }
            while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
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

    public function lastDataNewFor($actor, $lastNelements = -10)
    {

        $lastDialogFull = array();
        $results = self::$link->query("select  
    case 
      when type like 'info%' or type like 'death%' or  type like 'funcret%' or type like 'location%' then 'The Narrator:'
      when type='book' then 'The Narrator: ({$GLOBALS["PLAYER_NAME"]} took the book ' 
      else '' 
    end||a.data  as data 
    FROM  eventlog a WHERE data like '%$actor%' 
    and type<>'combatend'  
    and type<>'bored' and type<>'init' and type<>'infonpc' and type<>'infoloc' and type<>'info' and type<>'funcret'  and type<>'quest'
    and type<>'funccall'  and type<>'togglemodel'  order by gamets desc,ts desc LIMIT 0,100");

        while ($row = $results->fetchArray()) {
            $rawData[] = $row;
        }

        $orderedData = array_reverse($rawData);

        $orderedData = array_slice($orderedData, $lastNelements);

        $currentLocation = "";
        $writeLocation = true;

        $currentSpeaker = "user";
        $buffer = [];

        foreach ($orderedData as $row) {
            $rowData = $row["data"];
            // Extract location
            $pattern = '/\(Context location: (.*?),(.*?)\)/';

            if (preg_match($pattern, str_replace(" background chat", "", $rowData), $matches)) {

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


            if ((strpos($rowData, "{$GLOBALS["HERIKA_NAME"]}:") !== false)) {
                $speaker = "assistant";
            } elseif ((strpos($rowData, "{$GLOBALS["PLAYER_NAME"]}:") !== false)) {
                $speaker = "player";
            } else {
                $speaker = "user";
            }



            if (($currentSpeaker == $speaker) && ($speaker == "assistant")) {
                $buffer[] = $rowData;
            } else {
                if (sizeof($buffer) > 0) {
                    $lastDialogFull[] = array('role' => $currentSpeaker, 'content' => implode("\n", $buffer));
                }
                $buffer = [];
                $buffer[] = $rowData;
                $currentSpeaker = $speaker;
            }
        }

        $lastDialogFull[] = array('role' => $currentSpeaker, 'content' => implode("\n", $buffer));

        // Compact Herika's lines
        foreach ($lastDialogFull as $n => $line) {
            if ($line["role"] == "assistant") {
                $pattern = "/\([^)]*Context location[^)]*\)/";
                $cleanedText = trim(preg_replace($pattern, "", $line["content"])); // Remove context location always for assistant

                $re = '/[^(' . $GLOBALS["HERIKA_NAME"] . ':)].*(' . $GLOBALS["HERIKA_NAME"] . ':)/m';
                $subst = "";
                $cleanedText = preg_replace($re, $subst, $cleanedText);
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


        return $lastDialogFull;

    }

    public function speechJournal($topic)
    {


        $lastDialogFull = [];
        $results = self::$link->query("SElECT  speaker,speech,location,listener,topic as quest FROM speech
      where (speaker like '%$topic%' or  listener like '%$topic%' or location like '%$topic%' or  topic like '%$topic%') order by rowid desc");
        if (!$results) {
            return json_encode([]);
        }

        $data = [];

        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $data[] = $row;
        }

        if (sizeof($data) == 0) {
            return json_encode([]);
        } elseif (sizeof($data) < 25) {
            $dataReversed = array_reverse($data);
        } else {
            $smalldata = array_slice($data, 25);
            $dataReversed = array_reverse($smalldata);
        }


        return json_encode($dataReversed);

    }

    public function diaryLog($topic)
    {

        /*
        $results = self::$link->query("SElECT  topic,content,tags,people  FROM diarylog
        where (tags like '%$topic%' or topic like '%$topic%' or people like '%$topic%') order by gamets asc");
        */
        $topicTok = explode(" ", strtr($topic, array("'" => "")));
        $topicFmt = implode(" OR ", $topicTok);
        $results = self::$link->query(SQLite3::escapeString("SElECT  topic as page,content,tags,people  FROM diarylogv2
      where (tags MATCH \"$topicFmt\" or topic MATCH \"$topicFmt\" or content MATCH \"$topicFmt\" or people MATCH \"$topicFmt\") ORDER BY rank"));


        if (!$results) { // No match, will return a list of current memories
            $results = self::$link->query(SQLite3::escapeString("SElECT  topic as page,tags  FROM diarylogv2 order by gamets asc"));

            if (!$results) {
                return json_encode([]);
            }

            $data = [];

            while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
                $data[] = $row;
            }

            return json_encode(["return value" => "Page not found", "similar pages" => $data]);


        } else { // Return best matching memory

            file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "logquery.txt", SQLite3::escapeString("\nSElECT  topic,content,tags,people  FROM diarylogv2
        where (tags MATCH \"$topicFmt\" or topic MATCH \"$topicFmt\" or content MATCH \"$topicFmt\" or people MATCH \"$topicFmt\") ORDER BY rank"), FILE_APPEND);

            $data = [];
            while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
                $data[] = $row;
                break; // Only space for one memory
            }

        }

        if (sizeof($data) == 0) { // No match, will return a list of current memories. Revise limits

            $results = self::$link->query(SQLite3::escapeString("SElECT  topic as page  FROM diarylogv2 order by rowid asc"));

            $data = [];

            while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
                $data[] = $row;
            }

            return json_encode(["return value" => "Page not found", "available pages" => $data]);
        }

        return json_encode($data);


    }


    public function diaryLogIndex($topic)
    {

        //$results = self::$link->query('SElECT  topic,tags  FROM diarylogv2 where tags  MATCH NEAR(\'one two\' \'three four\', 10) order by rank');
        $preData = self::fetchAll("SElECT  topic as page,tags,people  FROM diarylogv2 where tags  MATCH 'NEAR(\"$topic\")' or topic  MATCH 'NEAR(\"$topic\")' or people  MATCH 'NEAR(\"$topic\")'  order by rank");
        //$preData=  self::fetchAll("SElECT  topic,tags,people  FROM diarylogv2 where tags  MATCH \"$topic\" order by rank");
        if (sizeof($preData) == 0) {
            $preData = self::fetchAll("SElECT  topic as page,tags,people  FROM diarylogv2 where tags  like '%$topic%'  or topic  like '%$topic%' or people  like '%$topic%'");
            if (sizeof($preData) == 0) {
                $results = self::$link->query(SQLite3::escapeString("SElECT  topic as page,tags,people  FROM diarylogv2 order by rowid asc"));
                $data = [];

                while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
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


    public function get_current_task()
    {

        $results = self::$link->query("SElECT  description  FROM currentmission order by gamets desc");
        if (!$results) {
            return json_encode([]);
        }

        $data = "";

        $n = 0;
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {

            if ($n == 0) {
                $data = "Current task/quest/plan: {$row["description"]}.";
            } elseif ($n == 1) {
                $data .= "Previous task/quest/plan: {$row["description"]}.";
            } else {
                break;
            }
            $n++;
        }

        return $data;

    }


    public function lastRetFunc($actor, $lastNelements = -2)
    {
        $lastDialogFull = array();
        $results = self::$link->query("select  a.data  as data  FROM  eventlog a 
    WHERE data like '%$actor%' and type in ('funcret')  order by gamets desc,ts desc LIMIT 0,1");
        $lastData = "";
        while ($row = $results->fetchArray()) {
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

    function lastKnowDate() {
        
        $lastLoc=self::fetchAll("select  a.data  as data  FROM  eventlog a  WHERE type in ('infoloc')  order by gamets desc,ts desc LIMIT 0,1");
        if (!is_array($lastLoc) || sizeof($lastLoc)==0)
            return "";
        $re = '/(\w+), (\d{1,2}:\d{2} (?:AM|PM)), (\d{1,2})(?:st|nd|rd|th) of ([A-Za-z\ ]+), 4E (\d+)/';
        preg_match($re, $lastLoc[0]["data"], $matches, PREG_OFFSET_CAPTURE, 0);
        return $matches[0][0];
        
    }
    
     function lastKnownLocation() {
        
        $lastLoc=self::fetchAll("select  a.data  as data  FROM  eventlog a  WHERE type in ('infoloc')  order by gamets desc,ts desc LIMIT 0,1");
        if (!is_array($lastLoc) || sizeof($lastLoc)==0)
            return "";
        $re = '/Context location: ([\w\ ]*)/';
        preg_match($re, $lastLoc[0]["data"], $matches, PREG_OFFSET_CAPTURE, 0);
        return $matches[1][0];
        
    }
    
}
