<?php



function split_sentences($paragraph)
{
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
        return $sentences;
    } else {
        return array($sentences);
    }
}

function br2nl($string)
{
    return preg_replace('/[\r\n]+/', '', preg_replace('/\<br(\s*)?\/?\>/i', "", $string));
}

function cleanReponse($rawResponse)
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

    if (strpos($toSplit, "{$GLOBALS["HERIKA_NAME"]}:") !== false) {
        $rawResponseSplited = explode(":", $toSplit);
        array_shift($rawResponseSplited);
        $toSplit = implode(":",$rawResponseSplited);
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

function print_array_as_table($data)
{
    // Start the HTML table

    if (sizeof($data) < 1) {
        return;
    }
    echo "<div class='datatable'>";
    echo "<table border='1' width='100%' class='table table-striped table-bordered table-sm'>";


    // Print the first row with array keys
    echo "<tr class='primary'>";
    foreach (array_keys($data[0]) as $key) {
        echo "<th>" . $key . "</th>";
    }
    echo "</tr>";

    // Print the remaining rows with array values
    foreach ($data as $i => $row) {
        $colorClass = "";

        // if we have an "url" column, paint the rows different colors
        if (isset($row["url"])) {
            $colorClasses = ["table-primary", "table-secondary", "table-info", "table-light", "table-dark"];
            $colorIndex = abs(crc32(preg_replace('/in \d+ secs/', '', $row["url"]))) % 5;
            $colorClass = $colorClasses[$colorIndex];
        }

        echo "<tr>";
        foreach ($row as $n => $cell) {
            if ($n == "prompt") {
                /* This is fucking slow
                 * echo "<td class='{$colorClass}'>
                    <span data-bs-toggle='collapse' data-bs-target='.prompt-$i' style='cursor:pointer'>[+]</span>
                    <pre class='collapse prompt-$i'>" . $cell . "</pre>
                </td>";
                */
                echo "<td><span class='foldableCtl' onclick='togglePre(this)' style='cursor:pointer'>[+]</span><pre class='foldable'>" . $cell . "</pre></td>";

            } elseif ($n == "rowid") {
                echo "<td class='$colorClass'>
                    <a class='icon-link' href='cmd/deleteRow.php?table={$_GET["table"]}&rowid=$cell'>
                        " . $cell . "
                        <i class='bi-trash'></i>
                    </a>
                </td>";
            } elseif (strpos($cell, 'background chat') !== false) {
                echo "<td class='$colorClass'><em>" . $cell . "</em></td>";
            } elseif (strpos($cell, $GLOBALS["PLAYER_NAME"] . ':') !== false) {
                echo "<td class='$colorClass'>" . $cell . "</td>";
            } elseif (strpos($cell, 'obtains a quest') !== false) {
                echo "<td class='$colorClass'><strong>" . $cell . "</strong></td>";
            } elseif (strpos($cell, "{$GLOBALS["HERIKA_NAME"]}:") !== false) {
                echo "<td  class='$colorClass'>" . $cell . "</td>";

            } elseif ($n == "cost_USD" || $n == "total_cost_so_far_USD") {
                $formatted_cell = (is_numeric($cell)) ? number_format($cell, 6) : $cell;
                echo "<td class='$colorClass'>" . $formatted_cell . "</td>";
            } elseif ($n == "rowid") {
                echo "<td class='$colorClass'>
                    <a class='icon-link' href='cmd/deleteRow.php?table={$_GET["table"]}&rowid=$cell'>
                        " . $cell . "
                        <i class='bi-trash'></i>
                    </a>
                </td>";

            } else {
                echo "<td class='$colorClass'>" . $cell . "</td>";
            }
        }
        echo "</tr>";
    }

    // End the HTML table
    echo "</table></div>";
}



function parseResponseV2($responseText, $forceMood = "", $topicQueue = "")
{

    global $db, $startTime;


    /* Split into sentences for better timing in-game */
    $sentences = preg_split('/(?<=[.!?])\s+/', $responseText, -1, PREG_SPLIT_NO_EMPTY);

    $splitSentences = [];
    $currentSentence = '';

    foreach ($sentences as $sentence) {
        $currentSentence .= ' ' . $sentence;
        if (strlen($currentSentence) > 120) {
            $splitSentences[] = trim($currentSentence);
            $currentSentence = '';
        } elseif (strlen($currentSentence) >= 60 && strlen($currentSentence) <= 120) {
            $splitSentences[] = trim($currentSentence);
            $currentSentence = '';
        }
    }

    if (!empty($currentSentence)) {
        $splitSentences[] = trim($currentSentence);
    }



    /*****************************/


    foreach ($splitSentences as $n => $sentence) {
        preg_match_all('/\((.*?)\)/', $sentence, $matches);

        $responseTextUnmooded = preg_replace('/\((.*?)\)/', '', $sentence);

        if ($forceMood) {
            $mood = $forceMood;
        } else {
            $mood = $matches[1][0];
        }

        $responseText = $responseTextUnmooded;

        if ($n == 0) { // TTS stuff for first sentence
            if ($GLOBALS["TTSFUNCTION"] == "azure") {
                if ($GLOBALS["AZURE_API_KEY"]) {
                    require_once("tts/tts-azure.php");
                    tts($responseTextUnmooded, $mood, $responseText);
                }
            }

            if ($GLOBALS["TTSFUNCTION"] == "mimic3") {
                if ($GLOBALS["MIMIC3"]) {
                    require_once("tts/tts-mimic3.php");
                    ttsMimic($responseTextUnmooded, $mood, $responseText);
                }
            }

            if ($GLOBALS["TTSFUNCTION"] == "11labs") {
                if ($GLOBALS["ELEVENLABS_API_KEY"]) {
                    require_once("tts/tts-11labs.php");
                    tts($responseTextUnmooded, $mood, $responseText);
                }
            }

            if ($GLOBALS["TTSFUNCTION"] == "gcp") {
                if ($GLOBALS["GCP_SA_FILEPATH"]) {
                    require_once("tts/tts-gcp.php");
                    tts($responseTextUnmooded, $mood, $responseText);
                }
            }
        }

        if ($sentence) {
            if (!$errorFlag) {
                $db->insert(
                    'responselog',
                    array(
                        'localts' => time(),
                        'sent' => 0,
                        'text' => trim(preg_replace('/\s\s+/', ' ', SQLite3::escapeString($responseTextUnmooded))),
                        'actor' => "Herika",
                        'action' => $topicQueue,
                        'tag' => $tag
                    )
                );
                $outBuffer[] = array(
                    'localts' => time(),
                    'sent' => 0,
                    'text' => trim(preg_replace('/\s\s+/', ' ', $responseTextUnmooded)),
                    'actor' => "Herika",
                    'action' => $topicQueue,
                    'tag' => $tag
                );
            }
            $db->insert(
                'log',
                array(
                    'localts' => time(),
                    'prompt' => nl2br(SQLite3::escapeString(print_r($GLOBALS["DEBUG_DATA"], true))),
                    'response' => (SQLite3::escapeString(print_r($rawResponse, true) . $responseTextUnmooded)),
                    'url' => nl2br(SQLite3::escapeString(print_r(base64_decode(stripslashes($_POST["preprompt"])), true) . " in " . (time() - $startTime) . " secs "))


                )
            );
        } else {
            $db->insert(
                'log',
                array(
                    'localts' => time(),
                    'prompt' => nl2br(SQLite3::escapeString(print_r($parms, true))),
                    'response' => (SQLite3::escapeString(print_r($rawResponse, true))),
                    'url' => nl2br(SQLite3::escapeString(print_r(base64_decode(stripslashes($_GET["DATA"])), true) . " in " . (time() - $startTime) . " secs with ERROR STATE"))


                )
            );
        }
    }

    $responseDataMl = $outBuffer;
    //foreach ($responseDataMl as $responseData)
    //echo "{$responseData["actor"]}|{$responseData["action"]}|{$responseData["text"]}\r\n";

    //echo 'X-CUSTOM-CLOSE';
    ob_end_flush();
    ob_flush();
    flush();
    //header('Content-Encoding: none');
    //header('Content-Length: ' . ob_get_length());
    //header('Connection: close');

    foreach ($splitSentences as $n => $sentence) {

        preg_match_all('/\((.*?)\)/', $sentence, $matches);
        $responseTextUnmooded = preg_replace('/\((.*?)\)/', '', $sentence);

        if ($forceMood) {
            $mood = $forceMood;
        } else {
            $mood = $matches[1][0];
        }

        $responseText = $responseTextUnmooded;

        if ($n == 0) { //First sentence was genetared
            continue;
        }

        if ($GLOBALS["TTSFUNCTION"] == "azure") {
            if ($GLOBALS["AZURE_API_KEY"]) {
                require_once("tts/tts-azure.php");
                tts($responseTextUnmooded, $mood, $responseText);
            }
        }

        if ($GLOBALS["TTSFUNCTION"] == "mimic3") {
            if ($GLOBALS["MIMIC3"]) {
                require_once("tts/tts-mimic3.php");
                ttsMimic($responseTextUnmooded, $mood, $responseText);
            }
        }


        if ($GLOBALS["TTSFUNCTION"] == "11labs") {
            if ($GLOBALS["ELEVENLABS_API_KEY"]) {
                require_once("tts/tts-11labs.php");
                tts($responseTextUnmooded, $mood, $responseText);
            }
        }

        if ($GLOBALS["TTSFUNCTION"] == "gcp") {
            if ($GLOBALS["GCP_SA_FILEPATH"]) {
                require_once("tts/tts-gcp.php");
                tts($responseTextUnmooded, $mood, $responseText);
            }
        }
    }
}

function getCurrentURL()
{
    $currentURL = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
    $currentURL .= $_SERVER["SERVER_NAME"];
 
    if($_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443")
    {
        $currentURL .= ":".$_SERVER["SERVER_PORT"];
    } 
 
    //$currentURL .= ":8081";   // Need to fix this. ¿Works with uwamp?"
    return $currentURL;
}

function getCostPerThousandInputTokens()
{
    $costPerThousandTokens = 0;
    if ($GLOBALS["GPTMODEL"] == 'gpt-3.5-turbo') {
        $costPerThousandTokens = 0.0015;
    } elseif ($GLOBALS["GPTMODEL"] == 'gpt-3.5-turbo-16k') {
        $costPerThousandTokens = 0.003;
    } elseif ($GLOBALS["GPTMODEL"] == 'gpt-3.5-turbo-0613') {
        $costPerThousandTokens = 0.0015;
    } elseif ($GLOBALS["GPTMODEL"] == 'gpt-3.5-turbo-16k-0613') {
        $costPerThousandTokens = 0.003;
    } elseif ($GLOBALS["GPTMODEL"] == 'gpt-4') {
        $costPerThousandTokens = 0.03;
    } elseif ($GLOBALS["GPTMODEL"] == 'gpt-4-0613') {
        $costPerThousandTokens = 0.03;
    } elseif ($GLOBALS["GPTMODEL"] == 'gpt-4-32k') {
        $costPerThousandTokens = 0.06;
    } elseif ($GLOBALS["GPTMODEL"] == 'gpt-4-32k-0613') {
        $costPerThousandTokens = 0.06;
    } else {
        error_log("Cannot tokenize - unrecognized model {$GLOBALS["GPTMODEL"]}");
        $costPerThousandTokens = 0; // model unknown
    }

    return $costPerThousandTokens;
}

function getCostPerThousandOutputTokens()
{
    $costPerThousandTokens = 0;
    if ($GLOBALS["GPTMODEL"] == 'gpt-3.5-turbo') {
        $costPerThousandTokens = 0.002;
    } elseif ($GLOBALS["GPTMODEL"] == 'gpt-3.5-turbo-16k') {
        $costPerThousandTokens = 0.004;
    } elseif ($GLOBALS["GPTMODEL"] == 'gpt-3.5-turbo-0613') {
        $costPerThousandTokens = 0.002;
    } elseif ($GLOBALS["GPTMODEL"] == 'gpt-3.5-turbo-16k-0613') {
        $costPerThousandTokens = 0.004;
    } elseif ($GLOBALS["GPTMODEL"] == 'gpt-4') {
        $costPerThousandTokens = 0.06;
    } elseif ($GLOBALS["GPTMODEL"] == 'gpt-4-0613') {
        $costPerThousandTokens = 0.06;
    } elseif ($GLOBALS["GPTMODEL"] == 'gpt-4-32k') {
        $costPerThousandTokens = 0.12;
    } elseif ($GLOBALS["GPTMODEL"] == 'gpt-4-32k-0613') {
        $costPerThousandTokens = 0.12;
    } else {
        error_log("Cannot tokenize - unrecognized model {$GLOBALS["GPTMODEL"]}");
        $costPerThousandTokens = 0; // model unknown
    }

    return $costPerThousandTokens;
}

function tokenizePrompt($jsonEncodedData)
{
    // This function goes to background.php. We pass arguments as post request
    // Will by call limiting reponse buffer, so request will return in "no time"
    
    /*
    global $db;

    if (isset($GLOBALS["GPTMODEL"]) && isset($GLOBALS["COST_MONITOR_ENABLED"]) && $GLOBALS["COST_MONITOR_ENABLED"]) {
        $costPerThousandTokens = getCostPerThousandInputTokens();
        // connect to local Python server servicing tokenizing requests
        $tokenizer_url = 'http://127.0.0.1:8090';
        $tokenizer_headers = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => $jsonEncodedData,
                'timeout' => 2
            )
        );
        $tokenizer_context = stream_context_create($tokenizer_headers);
        $tokenizer_buffer = file_get_contents('http://127.0.0.1:8090', false, $tokenizer_context);
        if ($tokenizer_buffer !== false) {
            $tokenizer_buffer = trim($tokenizer_buffer);
            if (ctype_digit($tokenizer_buffer)) { // make sure the response from tokenizer is a number (num of tokens)
                $numTokens = intval($tokenizer_buffer);
                $cost = $numTokens * $costPerThousandTokens * 0.001;
                $db->insert_and_calc_totals(
                    'openai_token_count',
                    array(
                        'input_tokens' => $tokenizer_buffer,
                        'output_tokens' => '0',
                        'cost_USD' => $cost,
                        'localts' => time(),
                        'datetime' => date("Y-m-d H:i:s"),
                        'model' => $GLOBALS["GPTMODEL"]
                    )
                );
            }
        } else {
            error_log("error: tokenizer buf false\n");
        }
    }
    */
    if (isset($GLOBALS["GPTMODEL"]) && isset($GLOBALS["COST_MONITOR_ENABLED"]) && $GLOBALS["COST_MONITOR_ENABLED"] && $GLOBALS["MODEL"] == "openai") {
        $data =http_build_query(
            array(
                'jsonEncodedData' => $jsonEncodedData,
            )
        );

        
        $headers = array(
                'Content-Type: application/x-www-form-urlencoded',
                "Content-Length: " . strlen($data) . "\r\n",
        );

        $options = array(
                'http' => array(
                    'method' => 'POST',
                    'header' => implode("\r\n", $headers),
                    'content' => $data
                )
            );

        $context = stream_context_create($options);

        $url=getCurrentURL();
        // Send the request and forget about
        $response = file_get_contents("{$url}/saig-gwserver/background.php?action=tokenizePrompt", false, $context, 0, 0);
    }

}

function tokenizeResponse($numOutputTokens)
{
    global $db;

    if (isset($GLOBALS["GPTMODEL"]) && isset($GLOBALS["COST_MONITOR_ENABLED"]) && $GLOBALS["COST_MONITOR_ENABLED"]  && $GLOBALS["MODEL"] == "openai") {
        $costPerThousandTokens = getCostPerThousandOutputTokens();
        $cost = $numOutputTokens * $costPerThousandTokens * 0.001;
        $db->insert_and_calc_totals(
            'openai_token_count',
            array(
                'input_tokens' => '0',
                'output_tokens' => $numOutputTokens,
                'cost_USD' => $cost,
                'localts' => time(),
                'datetime' => date("Y-m-d H:i:s"),
                'model' => $GLOBALS["GPTMODEL"]
            )
        );
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

function consoleLog($msg) {
    
 if (php_sapi_name()=="cli")
     echo "$msg".PHP_EOL;
    
}
