<?php

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
    $paragraph=strtr($paragraph,array('\n'=>".","\n"=>"."));
    
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

    return $splitSentences;
}


function returnLines($lines)
{

    global $db, $startTime, $forceMood, $staticMood, $talkedSoFar, $FORCED_STOP, $TRANSFORMER_FUNCTION,$receivedData;
    foreach ($lines as $n => $sentence) {

        if ($FORCED_STOP) {
            return;
        }
        // Remove actions
        $elapsedTimeAI=time() - $startTime;

        $pattern = '/<[^>]+>/';
        $output = str_replace("#CHAT#", "", preg_replace($pattern, '', $sentence));

        // This should be reworked
        //$sentence = preg_replace('/[[:^print:]]/', '', $output); // Remove non ASCII chracters


        $sentence=$output;

        $output = preg_replace('/\*([^*]+)\*/', '', $sentence); // Remove text bewteen * *

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
                tts($responseTextUnmooded, $mood, $responseText);

            }

            if ($GLOBALS["TTSFUNCTION"] == "mimic3") {

                require_once(__DIR__."/../tts/tts-mimic3.php");
                ttsMimic($responseTextUnmooded, $mood, $responseText);

            }

            if ($GLOBALS["TTSFUNCTION"] == "11labs") {

                require_once(__DIR__."/../tts/tts-11labs.php");
                tts($responseTextUnmooded, $mood, $responseText);

            }

            if ($GLOBALS["TTSFUNCTION"] == "gcp") {

                require_once(__DIR__."/../tts/tts-gcp.php");
                tts($responseTextUnmooded, $mood, $responseText);

            }
            
            if ($GLOBALS["TTSFUNCTION"] == "coqui-ai") {

                require_once(__DIR__."/../tts/tts-coqui-ai.php");
                tts($responseTextUnmooded, $mood, $responseText);

            }
            
            
            if (trim($responseText)) {
                $talkedSoFar[] = $responseText;
            }
        }

        $elapsedTimeTTS=time() - $startTime;

        $outBuffer = array(
            'localts' => time(),
            'sent' => 1,
            'text' => trim(preg_replace('/\s\s+/', ' ', $responseTextUnmooded)),
            'actor' => "Herika",
            'action' => "AASPGQuestDialogue2Topic1B1Topic",
            'tag' => (isset($tag) ? $tag : "")
        );
        $GLOBALS["DEBUG"]["BUFFER"][] = "{$outBuffer["actor"]}|{$outBuffer["action"]}|$responseTextUnmooded\r\n";
        echo "{$outBuffer["actor"]}|{$outBuffer["action"]}|$responseTextUnmooded\r\n";
        @ob_flush();
        @flush();

        $db->insert(
            'log',
            array(
                'localts' => time(),
                'prompt' => nl2br(SQLite3::escapeString(json_encode($GLOBALS["DEBUG_DATA"], JSON_PRETTY_PRINT))),
                'response' => (SQLite3::escapeString($responseTextUnmooded)),
                'url' => nl2br(SQLite3::escapeString("$receivedData [AI secs] $elapsedTimeAI  [TTS secs] $elapsedTimeTTS"))


            )
        );
    }

}

function logMemory($speaker, $listener, $message, $momentum, $gamets)
{
    global $db;
    $db->insert(
        'memory',
        array(
                'localts' => time(),
                'speaker' => (SQLite3::escapeString($speaker)),
                'listener' => (SQLite3::escapeString($listener)),
                'message' => (SQLite3::escapeString($message)),
                'gamets' => $gamets,
                'session' => "pending",
                'momentum'=>$momentum
        )
    );
    
    if (isset($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) && $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) {
        $insertedSeq=$db->fetchAll("SELECT SEQ from sqlite_sequence WHERE name='memory'");
        $embeddings=getEmbedding($message);
        storeMemory($embeddings, $message, $insertedSeq[0]["seq"]);
    }

}

function offerMemory($gameRequest, $DIALOGUE_TARGET)
{
    global $db;
    if (isset($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) && $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) {
        if (($gameRequest[0] == "inputtext") || ($gameRequest[0] == "inputtext_s")) {
            $memory=array();

            $textToEmbed=str_replace($DIALOGUE_TARGET, "", $gameRequest[3]);
            $pattern = '/\([^)]+\)/';
            $textToEmbedFinal = preg_replace($pattern, '', $textToEmbed);
            $textToEmbedFinal=str_replace("{$GLOBALS["PLAYER_NAME"]}:", "", $textToEmbedFinal);

            $embeddings=getEmbedding($textToEmbedFinal);
            $memories=queryMemory($embeddings);
            if (isset($memories["content"])) {
                $GLOBALS["DEBUG_DATA"]["memories"]=$textToEmbedFinal;
                return $GLOBALS["MEMORY_OFFERING"].json_encode($memories["content"]);
            }
        } elseif (($gameRequest[0] == "funcret")) {	//$gameRequest[3] will not contain last user chat, we must query database
            $memory=array();
            $lastPlayerLine=$db->fetchAll("SELECT data from eventlog where type in ('inputtext','inputtext_s') order by gamets desc limit 0,1");

            $textToEmbed=str_replace($DIALOGUE_TARGET, "", $lastPlayerLine);
            $pattern = '/\([^)]+\)/';
            $textToEmbedFinal = preg_replace($pattern, '', $textToEmbed);
            $textToEmbedFinal=str_replace("{$GLOBALS["PLAYER_NAME"]}:", "", $textToEmbedFinal);

            $embeddings=getEmbedding($textToEmbedFinal);
            $memories=queryMemory($embeddings);
            if ($memories["content"]) {
                $GLOBALS["DEBUG_DATA"]["memories"]=$textToEmbedFinal;
                return $GLOBALS["MEMORY_OFFERING"].json_encode($memories["content"]);
            }
        }

        return "";
    }


}

function logEvent($dataArray)
{
    global $db;

    $db->insert(
        'eventlog',
        array(
            'ts' => $dataArray[1],
            'gamets' => $dataArray[2],
            'type' => $dataArray[0],
            'data' => $dataArray[3],
            'sess' => 'pending',
            'localts' => time()
        )
    );
}


function selectRandomInArray($arraySource) {
    
    $n=sizeof($arraySource);
    if ($n==1)
        return $arraySource[0];
    
    return $arraySource[rand(0,$n-1)];
    
    
    
}
