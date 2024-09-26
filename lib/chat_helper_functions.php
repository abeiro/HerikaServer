<?php

define("_MINIMAL_DISTANCE_TO_BE_THE_SAME", 0.0);
define("_MAXIMAL_DISTANCE_TO_BE_RELATED", 0.8);
define("_MINIMAL_ELEMENTS_TO_TRIGGER_MESSAGE", 3);



function randomReplaceShortWordsWithPoints($inputString, $distance)
{
    // Split the input string into words
    $words = explode(' ', str_replace("Dear Diary", "", $inputString));

    $limit=round(30-($distance*30), 0);

    // Iterate through each word and replace short words with points
    foreach ($words as &$word) {

        if (preg_match('/^[A-Z]/', trim($word))) { // Skip names
            continue;
        }

        if ((rand(0, round($limit/2, 0))==0) && true) {
            $word = "[gap]";
        }
    }

    // Join the words back into a string
    $outputString = implode(' ', $words);

    return $outputString;
}

function cleanResponse($rawResponse)
{
    // Remove Context Location between parenthesys
    $pattern = '/\(C[^)]*\)/';
    $replacement = '';
    $rawResponse = preg_replace($pattern, $replacement, $rawResponse);

    // Remove {*}
    $pattern = '/\{.*?\}/';
    $replacement = '';
    $rawResponse = preg_replace($pattern, $replacement, $rawResponse);

    $rawResponse = strtr($rawResponse,["The Narrator: background dialogue:"=>""]);
    
    // Remove [*]]
    $pattern = '/\[.*?\]/';
    $replacement = '';
    $rawResponse = preg_replace($pattern, $replacement, $rawResponse);

    // Any bracket { or }]
    $rawResponse = strtr($rawResponse, array("{" => "", "}" => ""));

    if (strpos($rawResponse, "(Context location") !== false) {
        $rawResponseSplited = explode(":", $rawResponse);
        $toSplit = $rawResponseSplited[2];
    } elseif (strpos($rawResponse, "(Context new location") !== false) {
        $rawResponseSplited = explode(":", $rawResponse);
        $toSplit = $rawResponseSplited[2];
    } else {
        $toSplit = $rawResponse;
    }

    if (stripos($toSplit, "{$GLOBALS["HERIKA_NAME"]}:") !== false) {
        $rawResponseSplited = explode(":", $toSplit);
        array_shift($rawResponseSplited);
        $toSplit = implode(":", $rawResponseSplited);
    }

    //$toSplit = preg_replace("/{$GLOBALS["HERIKA_NAME"]}\s*:\s*/", '', $toSplit);

    $sentences = split_sentences($toSplit);

    $sentence = trim((implode(".", $sentences)));

    $sentenceX = strtr(
        $sentence,
        array(
            ",." => ","
        )
    );

    // Strip no ascii.
    $sentenceXX = str_replace(
        array('á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú', '¿', '¡'),
        array('a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U', '', ''),
        $sentenceX
    );


    return $sentenceXX;
}

function findDotPosition($string)
{
    
    $lastChar = substr($string, -1);

    if ($lastChar === '.')  // Dont eval on .. wait till next tokens
        return false;
    
    $dotPosition = strrpos($string, ".");

    if ($dotPosition !== false && strpos($string, ".", $dotPosition + 1) === false && substr($string, $dotPosition - 3, 3) !== "...") {
        return $dotPosition;
    }

    return false;
}

function br2nl($string)
{
    return preg_replace('/[\r\n]+/', '', preg_replace('/\<br(\s*)?\/?\>/i', "", $string));
}

function split_sentences($paragraph)
{
    $paragraph=strtr($paragraph, array('\n'=>".","\n"=>"."));

    if (strlen($paragraph)<=MAXIMUM_SENTENCE_SIZE) {
        return [$paragraph];
    }
    
    $paragraphNcr = br2nl($paragraph); // Some BR detected sometimes in response
    // Split the paragraph into an array of sentences using a regular expression
    preg_match_all('/[^\n?.!]+[?.!]/', $paragraphNcr, $matches);
    //print_r($matches);
    $sentences = $matches[0];
    // Check if the last sentence is truncated (i.e., doesn't end with a period)
    /*$last_sentence = end($sentences);
    if (!preg_match('/[.?|]$/', $last_sentence)) {
        // Remove the last sentence if it's truncated
        array_pop($sentences);
    }*/

    if (is_array($sentences)) {
        /*if (sizeof($sentences)==0)
             return array($paragraphNcr);
        else*/
        return $sentences;
    } else {
        return array($sentences);
    }
}

function checkOAIComplains($responseTextUnmooded)
{

    
    if (isset($GLOBALS["OPENAI_FILTER_DISABLED"]))
        return 0;
    
    $scoring = 0;
    
    if (stripos($responseTextUnmooded, "can't") !== false) {
        $scoring++;
    }
    if (stripos($responseTextUnmooded, "apologi") !== false) {
        $scoring++;
    }
    if (stripos($responseTextUnmooded, "sorry") !== false) {
        $scoring++;
    }
    if (stripos($responseTextUnmooded, "not able") !== false) {
        $scoring++;
    }
    if (stripos($responseTextUnmooded, "won't be able") !== false) {
        $scoring++;
    }
    if (stripos($responseTextUnmooded, "that direction") !== false) {
        $scoring += 2;
    }
    if (stripos($responseTextUnmooded, "AI language model") !== false) {
        $scoring += 4;
    }
    if (stripos($responseTextUnmooded, "openai") !== false) {
        $scoring += 3;
    }
    if (stripos($responseTextUnmooded, "generate") !== false) {
        $scoring += 1;
    }
    if (stripos($responseTextUnmooded, "request") !== false) {
        $scoring += 1;
    }
    if (stripos($responseTextUnmooded, "policy") !== false) {
        $scoring += 1;
    }
    if (stripos($responseTextUnmooded, "to provide") !== false) {
        $scoring += 1;
    }
    if (stripos($responseTextUnmooded, "context") !== false) {
        $scoring += 1;
    }
    if (stripos($responseTextUnmooded, "unable") !== false) {
        $scoring += 1;
    }
    if (stripos($responseTextUnmooded, "assist") !== false) {
        $scoring += 1;
    }
    if (stripos($responseTextUnmooded, "inappropriate") !== false) {
        $scoring += 1;
    }
    if (stripos($responseTextUnmooded, "explicit") !== false) {
        $scoring += 1;
    }
    if (stripos($responseTextUnmooded, "roleplay") !== false) {
        $scoring += 1;
    }
    if (stripos($responseTextUnmooded, "please provide an alternative scenario") !== false) {
        $scoring += 3;
    }

    return $scoring;
}


function split_sentences_stream($paragraph)
{
    if (strlen($paragraph)<=MAXIMUM_SENTENCE_SIZE) {
        return [$paragraph];
    }

    $sentences = preg_split('/(?<=[.!?])\s+/', $paragraph, -1, PREG_SPLIT_NO_EMPTY);

    $splitSentences = [];
    $currentSentence = '';

    foreach ($sentences as $sentence) {
        $currentSentence .= ' ' . $sentence;
        if (strlen($currentSentence) > 120) {
            $splitSentences[] = trim($currentSentence);
            $currentSentence = '';
        } elseif (strlen($currentSentence) >= 60 && strlen($currentSentence) <= MAXIMUM_SENTENCE_SIZE) {
            $splitSentences[] = trim($currentSentence);
            $currentSentence = '';
        }
    }

    if (!empty($currentSentence)) {
        $splitSentences[] = trim($currentSentence);
    }

    error_log("<$paragraph> => ".implode("|", $splitSentences));
    return $splitSentences;
}


function returnLines($lines,$writeOutput=true)
{

    global $db, $startTime, $forceMood, $staticMood, $talkedSoFar, $FORCED_STOP, $TRANSFORMER_FUNCTION,$receivedData;
    foreach ($lines as $n => $sentence) {

        if ($FORCED_STOP) {
            return;
        }
        
        if (is_array($sentence))
            return;
        
        // Remove actions
        $elapsedTimeAI= microtime(true) - $startTime;
        //error_log("PRE LLM STATUS DONE 2: ". (microtime(true) - $startTime));

        $pattern = '/<[^>]+>/';
        $output = str_replace("#CHAT#", "", preg_replace($pattern, '', $sentence));

        // This should be reworked
        //$sentence = preg_replace('/[[:^print:]]/', '', $output); // Remove non ASCII chracters


        $sentence=$output;

        //$output = preg_replace('/\*([^*]+)\*/', '', $sentence); // Remove text bewteen * *
        $output = preg_replace('/\*(\w+\s+\w+.*)\*/', '', $sentence); // Remove text bewteen * * if two or more words inside
        $sentence=$output;
        $output = strtr($sentence,[
                        "*Smirks*"=>"","*smirks*"=>"",
                        "*winks*"=>"","*wink*"=>"","*smirk*"=>"","*gasps*"=>"",
                        "*gasp*"=>"","*moans*"=>"","*whispers*"=>"","*moan*"=>"",
                        "*pant*"=>"",
                        "*whimper*"=>""
                        ]
                        ); // Manual cases
        
        $sentence = preg_replace('/"/', '', $output); // Remove "

        preg_match_all('/\((.*?)\)/', $sentence, $matches);

        $responseTextUnmooded = trim(preg_replace('/\((.*?)\)/', '', $sentence));

        if (stripos($responseTextUnmooded, "whispering:") !== false) { // Very very nasty, but solves lots of isses. We must keep log clean.
            $responseTextUnmooded = str_ireplace("whispering:", "", $responseTextUnmooded);
            $forceMood = "whispering";
        }


        $scoring = checkOAIComplains($responseTextUnmooded);

        if ($scoring >= 3) { // Catch OpenAI brekaing policies stuff
            $responseTextUnmooded = $GLOBALS["ERROR_OPENAI_POLICY"]; // Key phrase to indicate OpenAI triggered warning
            $ERROR_TRIGGERED=true;
            $FORCED_STOP = true;
        } else {
            if (isset($TRANSFORMER_FUNCTION)) {
                $responseTextUnmooded = $TRANSFORMER_FUNCTION($responseTextUnmooded);
            }

        }



        if (isset($forceMood)) {
            $mood = $forceMood;
        } elseif (!empty($matches) && !empty($matches[1]) && isset($matches[1][0])) {
            $mood = $matches[1][0];
        } else {
            $mood = "default";
        }

        if (isset($staticMood)) {
            $mood = $staticMood;
        } else {
            $staticMood = $mood;
        }

        if (isset($GLOBALS["FORCE_MOOD"])) {
            $mood = $GLOBALS["FORCE_MOOD"];
        }


        if (strlen($responseTextUnmooded) < 2) { // Avoid too short reponses
            return;
        }


        if (strpos($responseTextUnmooded, "The Narrator:") !== false) { // Force not impersonating the narrator.
            return;
        }

        $responseTextUnmooded = preg_replace("/{$GLOBALS["HERIKA_NAME"]}\s*:\s*/", '', $responseTextUnmooded);	// Should not happen

        $responseText = $responseTextUnmooded;


        if ($responseText) {
            if ($GLOBALS["TTSFUNCTION"] == "azure") {

                require_once(__DIR__."/../tts/tts-azure.php");
                $GLOBALS["TRACK"]["FILES_GENERATED"][]=tts($responseTextUnmooded, $mood, $responseText);

            } else if ($GLOBALS["TTSFUNCTION"] == "mimic3") {

                require_once(__DIR__."/../tts/tts-mimic3.php");
                $GLOBALS["TRACK"]["FILES_GENERATED"][]=ttsMimic($responseTextUnmooded, $mood, $responseText);

            } else if ($GLOBALS["TTSFUNCTION"] == "11labs") {

                require_once(__DIR__."/../tts/tts-11labs.php");
                $GLOBALS["TRACK"]["FILES_GENERATED"][]=tts($responseTextUnmooded, $mood, $responseText);

            } else if ($GLOBALS["TTSFUNCTION"] == "gcp") {

                require_once(__DIR__."/../tts/tts-gcp.php");
                $GLOBALS["TRACK"]["FILES_GENERATED"][]=tts($responseTextUnmooded, $mood, $responseText);

            } else if ($GLOBALS["TTSFUNCTION"] == "coqui-ai") {

                require_once(__DIR__."/../tts/tts-coqui-ai.php");
                $GLOBALS["TRACK"]["FILES_GENERATED"][]=tts($responseTextUnmooded, $mood, $responseText);

            } else if ($GLOBALS["TTSFUNCTION"] == "xvasynth") {

                require_once(__DIR__."/../tts/tts-xvasynth.php");
                $GLOBALS["TRACK"]["FILES_GENERATED"][]=tts($responseTextUnmooded, $mood, $responseText);

            } else if ($GLOBALS["TTSFUNCTION"] == "openai") {

                require_once(__DIR__."/../tts/tts-openai.php");
                $GLOBALS["TRACK"]["FILES_GENERATED"][]=tts($responseTextUnmooded, $mood, $responseText);

            } else if ($GLOBALS["TTSFUNCTION"] == "convai") {

                require_once(__DIR__."/../tts/tts-convai.php");
                $GLOBALS["TRACK"]["FILES_GENERATED"][]=tts($responseTextUnmooded, $mood, $responseText);

            } else if ($GLOBALS["TTSFUNCTION"] == "xtts") {

                require_once(__DIR__."/../tts/tts-xtts.php");
                $GLOBALS["TRACK"]["FILES_GENERATED"][]=tts($responseTextUnmooded, $mood, $responseText);

            } else if ($GLOBALS["TTSFUNCTION"] == "stylettsv2") {

                require_once(__DIR__."/../tts/tts-stylettsv2-2.php");
                $GLOBALS["TRACK"]["FILES_GENERATED"][]=tts($responseTextUnmooded, $mood, $responseText);

            } else if ($GLOBALS["TTSFUNCTION"] == "stylettsv2") {

                require_once(__DIR__."/../tts/tts-stylettsv2-2.php");
                $GLOBALS["TRACK"]["FILES_GENERATED"][]=tts($responseTextUnmooded, $mood, $responseText);

            } else {
                if (file_exists(__DIR__."/../tts/tts-".$GLOBALS["TTSFUNCTION"].".php")) {
                    require_once(__DIR__."/../tts/tts-".$GLOBALS["TTSFUNCTION"].".php");
                    $GLOBALS["TRACK"]["FILES_GENERATED"][]=tts($responseTextUnmooded, $mood, $responseText);
                }
            }
            
            if (trim($responseText)) {
                $talkedSoFar[] = $responseText;
            }
        }

        $elapsedTimeTTS=microtime(true) - $startTime;

        $outBuffer = array(
            'localts' => time(),
            'sent' => 1,
            'text' => trim(preg_replace('/\s\s+/', ' ', $responseTextUnmooded)),
            'actor' => $GLOBALS["HERIKA_NAME"],
            'action' => "AASPGQuestDialogue2Topic1B1Topic",
            'tag' => (isset($tag) ? $tag : "")
        );
        
        $GLOBALS["DEBUG"]["BUFFER"][] = "{$outBuffer["actor"]}|{$outBuffer["action"]}|$responseTextUnmooded\r\n";
        
        if ($writeOutput) {
            //if (isset($GLOBALS["NEWQUEUE"]) && $GLOBALS["NEWQUEUE"]) {
            if (true) {
                 if (isset($GLOBALS["SCRIPTLINE_ANIMATION_SENT"]) && $GLOBALS["SCRIPTLINE_ANIMATION_SENT"]) 
                     $GLOBALS["SCRIPTLINE_ANIMATION"]="";
                else {
                    if ((rand(0,4)!==0) ){ // Will disable animations, 20% chance to trigger
                        $GLOBALS["SCRIPTLINE_ANIMATION"]="IdleDialogueExpressiveStart";
                    }
                    $GLOBALS["SCRIPTLINE_ANIMATION_SENT"]=true;
                }

                if (!$GLOBALS["HERIKA_ANIMATIONS"]) {
                    $GLOBALS["SCRIPTLINE_ANIMATION"]="";
                    $GLOBALS["SCRIPTLINE_ANIMATION_SENT"]=true;
                }
                
                $listenerFix=explode(" and ",$GLOBALS["SCRIPTLINE_LISTENER"]);
                if (is_array($listenerFix) && (sizeof($listenerFix)>1)) {
                    $GLOBALS["SCRIPTLINE_LISTENER"]=$listenerFix[0];
                }
                
                $listenerFix2=explode(",",$GLOBALS["SCRIPTLINE_LISTENER"]);
                if (is_array($listenerFix2) && (sizeof($listenerFix2)>1)) {
                    $GLOBALS["SCRIPTLINE_LISTENER"]=$listenerFix[0];
                }
                
                echo "{$outBuffer["actor"]}|ScriptQueue|$responseTextUnmooded/{$GLOBALS["SCRIPTLINE_EXPRESSION"]}/{$GLOBALS["SCRIPTLINE_LISTENER"]}/{$GLOBALS["SCRIPTLINE_ANIMATION"]}\r\n";
                $GLOBALS["DEBUG_DATA"]["OUTPUT_LOG"]="{$outBuffer["actor"]}|ScriptQueue|$responseTextUnmooded/{$GLOBALS["SCRIPTLINE_EXPRESSION"]}/{$GLOBALS["SCRIPTLINE_LISTENER"]}/{$GLOBALS["SCRIPTLINE_ANIMATION"]}\r\n";
                file_put_contents(__DIR__."/../log/ouput_to_plugin.log",$GLOBALS["DEBUG_DATA"]["OUTPUT_LOG"], FILE_APPEND | LOCK_EX);
            }
            else
                echo "{$outBuffer["actor"]}|{$outBuffer["action"]}|$responseTextUnmooded\r\n";
            
            @ob_flush();
            @flush();
        }
        $db->insert(
            'log',
            array(
                'localts' => time(),
                'prompt' => nl2br(SQLite3::escapeString(json_encode($GLOBALS["DEBUG_DATA"], JSON_PRETTY_PRINT))),
                'response' => (SQLite3::escapeString($responseTextUnmooded)),
                'url' => nl2br(SQLite3::escapeString("$receivedData [AI secs] $elapsedTimeAI  [TTS secs] $elapsedTimeTTS"))


            )
        );
        
        // RECHAT
        $originalRequest=$GLOBALS["gameRequest"];
        $originalRequest[0]="prechat";
        $originalRequest[1]++;
        $originalRequest[2]++;
        if ($GLOBALS["SCRIPTLINE_LISTENER"])
            $addonlistener="(talking to {$GLOBALS["SCRIPTLINE_LISTENER"]})";
        else
            $addonlistener="";
        $originalRequest[3]="{$outBuffer["actor"]}: $responseTextUnmooded $addonlistener";
        logEvent($originalRequest);
        
        // Log chat here, because  function return comes back out of sync.
        $originalRequest[0]="chat";
        $originalRequest[1]++;
        $originalRequest[2]++;
        if ($GLOBALS["SCRIPTLINE_LISTENER"])
            $addonlistener="(talking to {$GLOBALS["SCRIPTLINE_LISTENER"]})";
        else
            $addonlistener="";
        $originalRequest[3]="{$outBuffer["actor"]}: $responseTextUnmooded $addonlistener";
        logEvent($originalRequest);
        
    }

}

function logMemory($speaker, $listener, $message, $momentum, $gamets,$event,$ts)
{
    global $db;

    $db->insert(
        'memory',
        array(
                'localts' => time(),
                'speaker' => $speaker,
                'listener' => $listener,
                'message' => $message,
                'gamets' => $gamets,
                'session' => "pending",
                'momentum'=>$momentum,
                'event'=>$event,
                'ts'=>$ts
        )
    );
    /*
    if (isset($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) && $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) {
        $insertedSeq=$db->fetchAll("SELECT SEQ from sqlite_sequence WHERE name='memory'");
        $embeddings=getEmbedding($message);
        storeMemory($embeddings, $message, $insertedSeq[0]["seq"]);
    }
    */


}

function lastNames($n, $eventypes)
{

    global $db;
    
    $m=$n+1;
    
    $lastRecords = $db->fetchAll("SELECT data from eventlog where type in ('".implode("','",$eventypes)."') order by gamets desc limit $m offset 0");
    
    $uppercaseWords=[];
    
    foreach ($lastRecords as $record) {
        $pattern = '/\([^)]+\)/';
        $string = preg_replace($pattern, '', $record["data"]);

        $pattern = '/ ([A-Z][a-z\-]{4,}){1,}/';
        preg_match_all($pattern, $string, $matches);

        $uppercaseWords = array_merge($uppercaseWords, $matches[0]);
    }
    
    
    $repeatedWords = array();
    $wordCount = array_count_values($uppercaseWords);

    foreach ($wordCount as $word => $count) {
        if ($count > 1) {
            $repeatedWords[] = $word;
        }
    }
   

    //die(print_r($uppercaseWords,true));
    if (sizeof($repeatedWords)>0) {
        return " ".implode(" ", $repeatedWords);
    } else {
        return "";
    }
}

function lastKeyWordsNew($n, $eventypes='')
{

    global $db;
    
    $m=$n+1;
    
    $lastRecords = $db->fetchAll("SELECT speaker,location,companions,speech from speech order by gamets desc limit $m offset 0");
    $words=[];
    $uniqueArray=[];
    $uppercaseWords = [];
    foreach ($lastRecords as $record) {
        $pattern = '/[A-Za-z\-]{4,}/';
        $matches=[];
        preg_match_all($pattern,  $record["speaker"]." ".$record["location"]." ".$record["companions"],$matches);
        $uppercaseWords1 = array_merge($uppercaseWords, $matches[0]);

        // Get words>4 chars starting with upercase, not in the beginning of string and not after .?
        $pattern = '/(?<!^|[.?]\s)(\b[A-Z][a-zA-Z\-]{4,}\b)/';
        $matches=[];
        preg_match_all($pattern,  $record["speech"],$matches);
        $uppercaseWords = array_merge($uppercaseWords1, $matches[0]);

    }
    foreach ($uppercaseWords as $n=>$e) {
        if (stripos($e, $GLOBALS["PLAYER_NAME"])!==false) {
          
        } else if (stripos($e, $GLOBALS["HERIKA_NAME"])!==false) {
            
        } else {
            if (!isset($words[$e]))
                $words[$e]=0;
            $words[$e]++;
            if ( preg_match('~^\p{Lu}~u', $e) ) {
                $words[$e]++;
                
            }

            
        }
        
    }

    function startsWithUppercase($string) {
        return preg_match('/^[A-Z]/', $string);
    }

    unset($words["Yeah"]);
    unset($words["Wouldn"]);
    unset($words["What"]);
    unset($words["Well"]);
    unset($words["Those"]);
    unset($words["This"]);
    unset($words["These"]);
    unset($words["There"]);
    unset($words["That"]);
    unset($words["Seems"]);
    unset($words["Shall"]);
    unset($words["Maybe"]);
    unset($words["Looks"]);
    unset($words["Just"]);
    
    
    foreach ($words as $n=>$e) {
        if ($e>1)
           if (startsWithUppercase($n))
                $uniqueArray[]=$n;
    }
    $GLOBALS["DEBUG_DATA"]["textToEmbedFinalKwywords"]=implode(" ",$uniqueArray);
    
    rsort($uniqueArray);
    return $uniqueArray;
    
}

function lastKeyWords($n, $eventypes='')
{

    global $db;
    
    $m=$n+1;
    
    $lastRecords = $db->fetchAll("SELECT message from memory order by gamets desc limit $m offset 0");
    $words=[];
    $uniqueArray=[];
    $uppercaseWords = [];
    foreach ($lastRecords as $record) {
        $pattern = '/\([^)]+\)/';
        $string = preg_replace($pattern, '', $record["message"]);

        $pattern = '/[A-Za-z\-]{4,}/';
        preg_match_all($pattern, $string, $matches);

        $uppercaseWords = array_merge($uppercaseWords, $matches[0]);

    }
    foreach ($uppercaseWords as $n=>$e) {
        if (stripos($e, $GLOBALS["PLAYER_NAME"])!==false) {
          
        } else if (stripos($e, $GLOBALS["HERIKA_NAME"])!==false) {
            
        } else {
            if (!isset($words[$e]))
                $words[$e]=0;
            $words[$e]++;
            if ( preg_match('~^\p{Lu}~u', $e) ) {
                $words[$e]++;
                
            }

            
        }
        
    }

    
    foreach ($words as $n=>$e) {
        if ($e>1)
            $uniqueArray[]=$n;
    }
    $GLOBALS["DEBUG_DATA"]["textToEmbedFinalKwywords"]=implode(" ",$uniqueArray);
    
    //$uniqueArray = array_unique($uppercaseWords);

    //die(print_r($uppercaseWords,true));
    if (sizeof($uniqueArray)>0) {
        return " ".implode(" ", $uniqueArray);
    } else {
        return "";
    }
}

function hashtagify($input) {
    // Remove all punctuation
    $input = preg_replace('/[^\w\s]/u', ' ', $input);

    // Split the string into words
    $words = explode(' ', $input);

    // Filter out words shorter than 2 characters
    $words = array_filter($words, function($word) {
        return mb_strlen(trim($word)) >= 2;
    });

    // Join adjacent words that both start with an uppercase letter
    $result = [];
    $buffer = '';

    foreach ($words as $word) {
        if (ctype_upper(mb_substr($word, 0, 1))) {
            if ($buffer !== '') {
                $buffer .= $word;
            } else {
                $buffer = $word;
            }
        } else {
            if ($buffer !== '') {
                $result[] = "#".ucfirst($buffer);
                $buffer = '';
            }
            $result[] =  "#".ucfirst($word);
        }
    }

    if ($buffer !== '') {
        $result[] = "#$buffer";
    }

    // Convert words to camel case
    /*$result = array_map(function($word, $index) {
        return $index === 0 ? strtolower($word) : ucfirst(strtolower($word));
    }, $result, array_keys($result));*/

    $hashtag = implode(' ', $result);

    return $hashtag;
}

function hashtagifySentences($input) {
    // Remove all punctuation
    $input = preg_replace('/[^\w\s]/u', ' ', $input);

    // Split the string into words
    $words = explode(' ', $input);

    // Filter out words shorter than 2 characters
    $words = array_filter($words, function($word) {
        return mb_strlen(trim($word)) >= 2;
    });

    // Join adjacent words that both start with an uppercase letter
    $result = [];
    $buffer = '';

    foreach ($words as $word) {
        if (ctype_upper(mb_substr($word, 0, 1))) {
            if ($buffer !== '') {
                $buffer .= $word;
            } else {
                $buffer = $word;
            }
        } else {
            if ($buffer !== '') {
                $result[] = ucfirst($buffer);
                $buffer = '';
            }
            $result[] = ucfirst($word);
        }
    }

    if ($buffer !== '') {
        $result[] = "#$buffer";
    }

    // Convert words to camel case
    $result = array_map(function($word, $index) {
        return $index === 0 ? strtolower($word) : ucfirst(strtolower($word));
    }, $result, array_keys($result));

    $hashtag = implode(' ', $result);

    return $hashtag;
}


function offerMemoryOld($gameRequest, $DIALOGUE_TARGET)
{
    global $db;
    if (isset($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) && $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) {

        if (($gameRequest[0] == "inputtext") || ($gameRequest[0] == "inputtext_s")) {
            $memory=array();

            $textToEmbed=str_replace($DIALOGUE_TARGET, "", $gameRequest[3]);
            $pattern = '/\([^)]+\)/';
            $textToEmbedFinal = preg_replace($pattern, '', $textToEmbed);
            $textToEmbedFinal=str_replace("{$GLOBALS["PLAYER_NAME"]}:", "", $textToEmbedFinal);
            $textToEmbedFinal=str_replace("{$GLOBALS["PLAYER_NAME"]} :", "", $textToEmbedFinal);

            
            // Give more weight to player's input and add last keywords to generate embedding.
            $weightedTextToEmbedFinal = str_repeat(" $textToEmbedFinal ", 3).lastKeyWords(2,['inputtext','inputtext_s']);


            
            $GLOBALS["DEBUG_DATA"]["textToEmbedFinal"]=$weightedTextToEmbedFinal;
            $embeddings=getEmbedding($weightedTextToEmbedFinal);
            $memories=queryMemory($embeddings);


            if (isset($memories["content"])) {
                $ncn=0;

                // Analize
                $tooManyMsg=false;

                $outputMemory = array_slice($memories["content"], 0, $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["MEMORY_CONTEXT_SIZE"]);
                $outLocalBuffer="";
                $GLOBALS["USE_MEMORY_STATEMENT_DELETE"]=true;
                if (isset($outputMemory)&&(sizeof($outputMemory)>0)) {
                    foreach ($outputMemory as $singleMemory) {

                        // Memory fuzz
                        $fuzzMemoryElement="".randomReplaceShortWordsWithPoints($singleMemory["briefing"], $singleMemory["distance"])."";

                        $outLocalBuffer.=round(($gameRequest[2]-$singleMemory["timestamp"])/ (60*60*24*20), 0)." days ago. {$fuzzMemoryElement}";

                    }
                    $GLOBALS["DEBUG_DATA"]["memories"][]=$textToEmbedFinal;
                    $GLOBALS["DEBUG_DATA"]["memories"][]=$outLocalBuffer;


                    if ($singleMemory["distance"]<($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["MEMORY_BIAS_B"]/100)) {
                        $GLOBALS["DEBUG_DATA"]["memories"]["selected"]=[$singleMemory];
                        $GLOBALS["USE_MEMORY_STATEMENT_DELETE"]=false;
                        return $GLOBALS["MEMORY_OFFERING"].$outLocalBuffer;

                    } elseif ($singleMemory["distance"]<($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["MEMORY_BIAS_A"]/100)) {
                        $GLOBALS["DEBUG_DATA"]["memories"]["selected"]=[$singleMemory];
                        return $GLOBALS["MEMORY_OFFERING"].$outLocalBuffer;

                    } else {
                        return "";
                    }

                    //$GLOBALS["DEBUG_DATA"]["memories_anz"][]=$ncn;


                } else {
                    return "";
                }
            }
        } elseif (($gameRequest[0] == "funcret")) {	//$gameRequest[3] will not contain last user chat, we must query database

            $memory=array();
            $lastPlayerLine=$db->fetchAll("SELECT data from eventlog where type in ('inputtext','inputtext_s') order by gamets desc limit 1 offset 0");

            $textToEmbed=str_replace($DIALOGUE_TARGET, "", $lastPlayerLine[0]["data"]);
            $pattern = '/\([^)]+\)/';
            $textToEmbedFinal = preg_replace($pattern, '', $textToEmbed);
            $textToEmbedFinal=str_replace("{$GLOBALS["PLAYER_NAME"]}:", "", $textToEmbedFinal);
            $textToEmbedFinal=str_replace("{$GLOBALS["PLAYER_NAME"]} :", "", $textToEmbedFinal);

            $textToEmbedFinal.=lastKeyWords(2,['inputtext','inputtext_s']);

            $GLOBALS["DEBUG_DATA"]["textToEmbedFinal"]=$textToEmbedFinal;
            $embeddings=getEmbedding($textToEmbedFinal);
            $memories=queryMemory($embeddings);


            if (isset($memories["content"])) {
                $ncn=0;

                // Analize
                $tooManyMsg=false;

                $outputMemory = array_slice($memories["content"], 0, $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["MEMORY_CONTEXT_SIZE"]);
                $outLocalBuffer="";
                $GLOBALS["USE_MEMORY_STATEMENT_DELETE"]=true;
                if (isset($outputMemory)&&(sizeof($outputMemory)>0)) {
                    foreach ($outputMemory as $singleMemory) {

                        // Memory fuzz
                        $fuzzMemoryElement="".randomReplaceShortWordsWithPoints($singleMemory["briefing"], $singleMemory["distance"])."";

                        $outLocalBuffer.=round(($gameRequest[2]-$singleMemory["timestamp"])/ (60*60*24*20), 0)." days ago. {$fuzzMemoryElement}";

                    }
                    $GLOBALS["DEBUG_DATA"]["memories"][]=$textToEmbedFinal;
                    $GLOBALS["DEBUG_DATA"]["memories"][]=$outLocalBuffer;
                    $GLOBALS["DEBUG_DATA"]["memories"]["selected"]=[$singleMemory];
                   
                    
                    if ($singleMemory["distance"]<($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["MEMORY_BIAS_B"]/100)) {
                        $GLOBALS["DEBUG_DATA"]["memories"]["selected"]=[$singleMemory];
                        $GLOBALS["USE_MEMORY_STATEMENT_DELETE"]=false;
                        return $GLOBALS["MEMORY_OFFERING"].$outLocalBuffer;

                    } elseif ($singleMemory["distance"]<($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["MEMORY_BIAS_A"]/100)) {
                        $GLOBALS["DEBUG_DATA"]["memories"]["selected"]=[$singleMemory];
                        return $GLOBALS["MEMORY_OFFERING"].$outLocalBuffer;

                    } else {
                        return "";
                    }
                    

                    //$GLOBALS["DEBUG_DATA"]["memories_anz"][]=$ncn;


                } else {
                    return "";
                }
            }
        }

        return "";
    }


    
    
}

function ExtractKeywords($sourceText) {
    
    $uppercaseWords=[];
    
    $pattern = '/[A-Za-z\-]{4,}/';
    $matches=[];
    preg_match_all($pattern,  $sourceText,$matches);
    $uppercaseWords1 = array_merge($uppercaseWords, $matches[0]);
        
    $pattern = '/(?<!^|[.?]\s)(\b[A-Z][a-zA-Z\-]{4,}\b)/';
    $matches=[];
    preg_match_all($pattern,  $sourceText,$matches);
    $uppercaseWords = array_merge($uppercaseWords1, $matches[0]);
    foreach ($uppercaseWords as $n=>$e) {
        if (stripos($e, $GLOBALS["PLAYER_NAME"])!==false) {
          
        } else if (stripos($e, $GLOBALS["HERIKA_NAME"])!==false) {
            
        } else {
            if (!isset($words[$e]))
                $words[$e]=0;
            $words[$e]++;
            if ( preg_match('~^\p{Lu}~u', $e) ) {
                $words[$e]++;
                
            }

            
        }
        
    }

    function startsWithUppercase($string) {
        return preg_match('/^[A-Z]/', $string);
    }

    unset($words["Yeah"]);
    unset($words["Wouldn"]);
    unset($words["What"]);
    unset($words["Well"]);
    unset($words["Those"]);
    unset($words["This"]);
    unset($words["These"]);
    unset($words["There"]);
    unset($words["That"]);
    unset($words["Seems"]);
    unset($words["Shall"]);
    unset($words["Maybe"]);
    unset($words["Looks"]);
    unset($words["Just"]);
    
    
    foreach ($words as $n=>$e) {
        if ($e>1)
           if (startsWithUppercase($n))
                $uniqueArray[]=$n;
    }
    if (is_array($uniqueArray)) {
        rsort($uniqueArray);
    } else
        return [];
    
    return $uniqueArray;  
}

function offerMemory($gameRequest, $DIALOGUE_TARGET)
{
    global $db;
    if (isset($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) && !$GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"] ) {
        error_log("MEMORY_EMBEDDING disabled");
        return "";
    }

    // PostgreSQL full text Searching
   
  
    
    $npc=$GLOBALS["HERIKA_NAME"];
    if ($npc=="The Narrator") { // Narrator knows all
       $npc=""; 
    }
    $memories=DataSearchMemory($gameRequest[3],$npc);
   
    
    if (isset($memories[0])) {
        error_log(print_r($memories[0],true));

        if (($memories[0]["rank_any"]==$memories[0]["rank_all"])&&($memories[0]["rank_any"]>0.25)) {
            
            $memory=(isset($memories[0]["summary"])?$memories[0]["summary"]:"");
            
        } else if ((($memories[0]["rank_all"]+$memories[0]["rank_any"])/2)>0.25) {
            
            $memory=(isset($memories[0]["summary"])?$memories[0]["summary"]:"");
            
        }  else
            return "";
    } else
        return "";
    
    if (!empty($memory)) {
        $hoursAgo=round(($gameRequest[2]-$memories[0]["gamets_truncated"])/ (60 * 60 * 40 * 24), 0);
        $pattern = '/#Tags:.*/';
        $replacement = '';
        $output = preg_replace($pattern, $replacement, $memory);
        $memory="$hoursAgo days ago ....  $output";
    }
    // print_r($memories);
    return ($memory);
}

function offerMemoryNew($gameRequest, $DIALOGUE_TARGET)
{
    global $db;
    if (isset($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) && $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) {

        if (($gameRequest[0] == "inputtext") || ($gameRequest[0] == "inputtext_s")) {
            $memory=array();

            $textToEmbed=str_replace($DIALOGUE_TARGET, "", $gameRequest[3]);
            $pattern = '/\([^)]+\)/';
            $textToEmbedFinal = preg_replace($pattern, '', $textToEmbed);
            $textToEmbedFinal=str_replace("{$GLOBALS["PLAYER_NAME"]} :", "", $textToEmbedFinal);
            $textToEmbedFinal=str_replace("{$GLOBALS["PLAYER_NAME"]}:", "", $textToEmbedFinal);

        } elseif (($gameRequest[0] == "funcret")) {	//$gameRequest[3] will not contain last user chat, we must query database

            $memory=array();
            $lastPlayerLine=$db->fetchAll("SELECT data from eventlog where type in ('inputtext','inputtext_s') order by gamets desc limit 1 offset 0");

            $textToEmbed=str_replace($DIALOGUE_TARGET, "", $lastPlayerLine[0]["data"]);
            $textToEmbedFinal = preg_replace($pattern, '', $textToEmbed);
            $textToEmbedFinal=str_replace("{$GLOBALS["PLAYER_NAME"]} :", "", $textToEmbedFinal);
            $textToEmbedFinal=str_replace("{$GLOBALS["PLAYER_NAME"]}:", "", $textToEmbedFinal);
        } else {
            return "";
        }


        $GLOBALS["DEBUG_DATA"]["textToEmbedFinal"]=$textToEmbedFinal;
        $embeddings=getEmbedding($textToEmbedFinal);
        $memories=queryMemory($embeddings);

        $keywords=explode(" ", trim($textToEmbedFinal));
        $mostRelevantMemory=[];
        $npass=0;
        foreach ($keywords as $keyword) {

            if (strlen($keyword)<=3) {
                continue;
            }

            $lembeddings=getEmbedding($keyword);
            $lmemories=queryMemory($lembeddings);

            foreach ($lmemories["content"] as $lresults) {
                if (isset($lresults["memory_id"])) {
                    if (!isset($mostRelevantMemory[$lresults["memory_id"]])) {
                        $mostRelevantMemory[$lresults["memory_id"]]=["n"=>0,"d"=>0];
                    }

                    $mostRelevantMemory[$lresults["memory_id"]]["n"]++;
                    $mostRelevantMemory[$lresults["memory_id"]]["d"]+=($lresults["distance"]);


                } if (isset($lresults["classifier"])) {


                }
            }
            $npass++;

        }

        foreach ($mostRelevantMemory as $uid=>$ldata) {

            $mostRelevantMemoryResult[$uid]=($ldata["d"]/$ldata["n"])*($npass/$ldata["n"]);
        }

        asort($mostRelevantMemoryResult);

        $selectedOne=array_key_first($mostRelevantMemoryResult);


        $results = $db->fetchAll("select summary as content,uid,gamets_truncated,classifier from memory_summary where uid=$selectedOne order by uid asc");

        $outputMemory = array_slice($results, 0, $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["MEMORY_CONTEXT_SIZE"]);

        $GLOBALS["USE_MEMORY_STATEMENT_DELETE"]=true;


        $outLocalBuffer="";

        if (isset($outputMemory)&&(sizeof($outputMemory)>0)) {
            foreach ($outputMemory as $singleMemory) {

                // Memory fuzz
                $fuzzMemoryElement="".randomReplaceShortWordsWithPoints($singleMemory["content"], current($mostRelevantMemoryResult))."";

                $outLocalBuffer.=round(($gameRequest[2]-$singleMemory["gamets_truncated"])/ (60*60*24*20), 0)." days ago. {$fuzzMemoryElement}";

            }
            $GLOBALS["DEBUG_DATA"]["memories"][]=$textToEmbedFinal;
            $GLOBALS["DEBUG_DATA"]["memories"][]=$outLocalBuffer;
            $GLOBALS["DEBUG_DATA"]["memories"]["selected"]=[$singleMemory,$mostRelevantMemoryResult];

            if (current($mostRelevantMemoryResult)<0.55) {
                $GLOBALS["USE_MEMORY_STATEMENT_DELETE"]=false;

            } elseif (current($mostRelevantMemoryResult)<0.95) {
                return $GLOBALS["MEMORY_OFFERING"].$outLocalBuffer;

            } else {
                return "";
            }

            //$GLOBALS["DEBUG_DATA"]["memories_anz"][]=$ncn;


        } else {
            return "";
        }
    }


    return "";



}

function logEvent($dataArray)
{
    global $db;

    if (!isset($GLOBALS["CACHE_PEOPLE"])) {
        $GLOBALS["CACHE_PEOPLE"]=DataBeingsInRange();
    } 
    
    if (!isset($GLOBALS["CACHE_LOCATION"])) {
        $GLOBALS["CACHE_LOCATION"]=DataLastKnownLocation();
    }
    
    if (!isset($GLOBALS["CACHE_PARTY"])) {
        $GLOBALS["CACHE_PARTY"]=DataGetCurrentPartyConf();
    }   

    $db->insert(
        'eventlog',
        array(
            'ts' => $dataArray[1],
            'gamets' => $dataArray[2],
            'type' => $dataArray[0],
            'data' => $dataArray[3],
            'sess' => 'pending',
            'localts' => time(),
            'people'=> $GLOBALS["CACHE_PEOPLE"],
            'location'=>$GLOBALS["CACHE_LOCATION"],
            'party'=>$GLOBALS["CACHE_PARTY"]
        )
    );
}


function selectRandomInArray($arraySource)
{

    if (!isset($arraySource)||!is_array($arraySource))
        return "";
    
    $n=sizeof($arraySource);
    if ($n==1) {
        return strtr($arraySource[0],["#HERIKA_NPC1#"=>$GLOBALS["HERIKA_NAME"]]);
        //return $arraySource[0];
    }
    
    return strtr($arraySource[rand(0, $n-1)],["#HERIKA_NPC1#"=>$GLOBALS["HERIKA_NAME"]]);
    //return $arraySource[rand(0, $n-1)];



}

function prettyPrintJson($json )
{
    $data=json_decode($json,true);
    $result="";
    foreach ($data as $p=>$v) {
        if (is_array($v)) {
            foreach ($v as $pp=>$vv) 
                $result.="$pp: $vv\n";
        } else
            $result.="$p: $v\n";
    }

    return $result;
}

