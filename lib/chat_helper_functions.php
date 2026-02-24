<?php

define("_MINIMAL_DISTANCE_TO_BE_THE_SAME", 0.0);
define("_MAXIMAL_DISTANCE_TO_BE_RELATED", 0.8);
define("_MINIMAL_ELEMENTS_TO_TRIGGER_MESSAGE", 3);
//prevent in-game buffer overflow (does not truncate tts only subtitles. Fixes long player input playertts (auto-chat / manual player scene setting)) - ideally player input needs splitting for tts but returnLines is not appropriate
define("_MAX_SUBTITLE_LENGTH", 1000);

require_once(__DIR__."/online_translation.php");
require_once(__DIR__."/utils_game_timestamp.php");
require_once(__DIR__."/pipeline_status.php");

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
    
    // Conditionally preserve paralinguistic tags based on provider settings
    // This feature works for any TTS provider that defines PARALINGUISTIC_TAGS_ENABLED
    $shouldPreserveTags = false;
    $eventTags = [];

    if (isset($GLOBALS["TTSFUNCTION"]) && !empty($GLOBALS["TTSFUNCTION"])) {
        // Map TTSFUNCTION to TTS array key
        $ttsMap = [
            'melotts' => 'MELOTTS',
            'xtts-fastapi' => 'XTTSFASTAPI',
            'chatterbox' => 'CHATTERBOX',
            'pockettts' => 'POCKETTTS',
            'mimic3' => 'MIMIC3',
            'xvasynth' => 'XVASYNTH',
            'azure' => 'AZURE',
            '11labs' => 'ELEVEN_LABS',
            'openai' => 'openai',
            'kokoro' => 'KOKORO',
            'koboldcpp' => 'koboldcpp',
            'zonos_gradio' => 'ZONOS_GRADIO',
            'piper-tts' => 'PIPERTTS',
            'deepgram' => 'DEEPGRAM',
            'cartesia' => 'CARTESIA',
            'inworld' => 'INWORLD'
        ];

        $ttsKey = $ttsMap[$GLOBALS["TTSFUNCTION"]] ?? strtoupper($GLOBALS["TTSFUNCTION"]);

        if (isset($GLOBALS["TTS"][$ttsKey]["PARALINGUISTIC_TAGS_ENABLED"]) &&
            (bool)$GLOBALS["TTS"][$ttsKey]["PARALINGUISTIC_TAGS_ENABLED"]) {
            $shouldPreserveTags = true;

            // Parse the configurable tag list
            if (isset($GLOBALS["TTS"][$ttsKey]["PARALINGUISTIC_TAGS_LIST"]) &&
                !empty($GLOBALS["TTS"][$ttsKey]["PARALINGUISTIC_TAGS_LIST"])) {
                $tagsList = $GLOBALS["TTS"][$ttsKey]["PARALINGUISTIC_TAGS_LIST"];
                $eventTags = array_map('trim', explode(',', $tagsList));
            }
        }
    }

    if ($shouldPreserveTags && !empty($eventTags)) {
        $rawResponse = preg_replace_callback('/\[.*?\]/', function($matches) use ($eventTags) {
            // Convert to lowercase to ensure case-insensitive matching
            foreach ($eventTags as $tag) {
                if (strtolower($matches[0]) === strtolower($tag)) {
                    return $matches[0]; // Return the tag as-is
                }
            }
            return ''; // Delete the tag
        }, $rawResponse);
    } else {
        // Remove all square brackets content
        $rawResponse = preg_replace('/\[.*?\]/', '', $rawResponse);
    }

    // Any bracket { or }]
    //$rawResponse = strtr($rawResponse, array("{" => "", "}" => ""));
    
    // clean { , fix ellipsis and unicode punctuation etc
    $rawResponse = str_replace(
        ["\0", "‐", "‑", " — ",  "—",  "‘", "’", "‚", "‛", "。。。", "…",   "{", "}" ],  //, " U.S.A. ", " U.S. " ],
        [''  , "-", "-", " - ", " - ", "'", "'", "'", "'", "...", "...", "",  ""  ], //,  " USA ",    " US "    ],
        $rawResponse);

    if (strpos($rawResponse, "(Context location") !== false || strpos($rawResponse, "(Context new location") !== false) {
        $rawResponseSplited = explode(":", $rawResponse);
        if (!isset($rawResponseSplited[2])) {
            Logger::warn("Could not extract speech from raw response: $rawResponse");
        }
        $toSplit = $rawResponseSplited[2];
    } else {
        $toSplit = $rawResponse;
    }

    $herikaNameShort = $GLOBALS["HERIKA_NAME"];
    $matches = [];
    // Some LLM's will omit part of the name in brackets (Eg, Fred [Solitude Guard] becomes Fred in the response.
    // This avoids reading off the abbreviated name in the TTS.
    if (preg_match('/^(.+?) \[(.+)\]$/', $GLOBALS["HERIKA_NAME"], $matches)) {
        $herikaNameShort = $matches[1];
    }
    
    if (stripos($toSplit, "{$GLOBALS["HERIKA_NAME"]}:") !== false || preg_match("/{$herikaNameShort}\s*:/", $toSplit, $matches)) {
        $rawResponseSplited = explode(":", $toSplit);
        array_shift($rawResponseSplited);
        $toSplit = implode(":", $rawResponseSplited);
    }

    //$toSplit = preg_replace("/{$GLOBALS["HERIKA_NAME"]}\s*:\s*/", '', $toSplit);

    $sentences = split_sentences($toSplit);

    $sentence = trim((implode(" ", $sentences)));

    $sentenceX = strtr(
        $sentence,
        array(
            ",." => ","
        )
    );

    // Strip no ascii.
    /*
    $sentenceXX = str_replace(
        array('á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú', '¿', '¡'),
        array('a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U', '', ''),
        $sentenceX
    );
    */

    // convert to half-width numbers (to avoid display issues with japanese font)
    $sentenceXX = str_replace(
        array('１', '２', '３', '４', '５', '６', '７', '８', '９', '０'),
        array('1', '2', '3', '4', '5', '6', '7', '8', '9', '0'),
        $sentenceX
    );

    return $sentenceXX;
}

// replace findDotPosition with first EOS split detection - same logic as split_at_end_of_sentence
function findFastSentencePosition($s_string) {
    // Find the position of the first sentence-ending punctuation followed by a space
    // This preserves ellipsis (...) because we require a space after the punctuation
    $eosPunc = preg_quote(getEndOfSentencePunctuation(), '/'); // .?!。？！

    // Match the EOS punctuation character followed by spaces
    // Negative lookbehind ensures we don't match after ellipsis (..)
    // "Don't split after ellipses either... Thanks :-)" -for example
    $splitSentenceRegex = "/([" . $eosPunc . "])(?<!\.\.)(?<!\.\.\.)\s+/u";

    // Find the first match and return the position of the EOS punctuation
    if (preg_match($splitSentenceRegex, $s_string, $matches, PREG_OFFSET_CAPTURE)) {
        // Return the position of the EOS punctuation character (to match findDotPosition behavior)
        return $matches[1][1];
    }

    return false;
}

function findDotPosition($s_string) {
    
    $lastChar = substr($s_string, -1);

    // Only skip if it ends with ellipsis (...), not regular sentence endings
    // This allows streaming to work properly for complete sentences
    if ($lastChar === ".") {
        // Check if it's an ellipsis (...)
        $last3Chars = substr($s_string, -3);
        if ($last3Chars === "...") {
            return false; // Don't process ellipsis yet
        }
        // Otherwise, allow processing of regular sentence endings
    }
    
    $dotPosition = strrpos($s_string, "."); // last dot in string
    
    /* // old version
    if (($dotPosition !== false) && (strpos($s_string, ".", $dotPosition + 1) === false) && (substr($s_string, $dotPosition - 3, 3) !== "...")) {
        return $dotPosition;
    } */
    
    if ($dotPosition !== false) {// found last dot
        // check for ...
        if (substr($s_string, ($dotPosition - 1), 1) !== ".") { 
            return $dotPosition;
        }
    }

    return false;
}

function br2nl($string)
{
    return trim(preg_replace('/\s+/', ' ', preg_replace('/[\r\n]+/', ' ', preg_replace('/\<br(\s*)?\/?\>/i', " ", $string))));
}

function split_at_end_of_sentence($paragraph) {
    // Split only at sentence-ending punctuation followed by a space
    // This preserves ellipsis (...) because we require a space after the punctuation
    $eosPunc = preg_quote(getEndOfSentencePunctuation(), '/'); // .?!。？！

    // Split at any end-of-sentence punctuation followed by one or more spaces
    // Negative lookahead (?!\.) ensures we don't split after a dot if another dot follows (ellipsis)
    // "Don't split after ellipses either... Thanks :-)" -for example
    $splitSentenceRegex = "/(?<=[" . $eosPunc . "])(?<!\.\.)(?<!\.\.\.)\s+/u";

    $sentences = preg_split($splitSentenceRegex, $paragraph, -1, PREG_SPLIT_NO_EMPTY);

    return $sentences;
}

function split_sentences($paragraph)
{
    // Normalize newlines to periods -dont know what this is fixing, but i can see what it is breaking (.\n becomes .. is that useful?)- matt
//    $paragraph = strtr($paragraph, array(" \n\n" => ".", " \n" => ".", "\n\n" => ".", '\n' => ".", "\n" => "."));

    if (strlen($paragraph) <= MAXIMUM_SENTENCE_SIZE) {
        return [$paragraph];
    }

    $paragraphNcr = br2nl($paragraph); // Remove any BR tags

    $sentences = split_at_end_of_sentence($paragraphNcr);

    return $sentences;
}

function split_sentences_stream($paragraph)
{
    if (strlen($paragraph) <= MAXIMUM_SENTENCE_SIZE) {
        return [$paragraph];
    }

    // Split at sentence boundaries
    $sentences = split_at_end_of_sentence($paragraph);

    // Combine sentences to fit within MINIMUM_SENTENCE_SIZE and MAXIMUM_SENTENCE_SIZE
    $splitSentences = [];
    $currentSentence = '';

    foreach ($sentences as $sentence) {
        $sentence = trim($sentence);
        if (empty($sentence)) {
            continue;
        }

        if (empty($currentSentence)) {
            // Start a new chunk
            $currentSentence = $sentence;
        } else {
            $combined = $currentSentence . ' ' . $sentence;

            // If adding this sentence would exceed maximum, flush current and start new
            if (strlen($combined) > MAXIMUM_SENTENCE_SIZE) {
                $splitSentences[] = $currentSentence;
                $currentSentence = $sentence;
            } else {
                // Add to current sentence
                $currentSentence = $combined;

                // If we've reached minimum size and we're between min and max, we can flush
                //EXPERIMENT: talk more in one go (longer subtitle text, fewer TTS calls)
                $talkMore = true; //false to split as soon as we reach min len (old behavior), true to pack up to max
                if (!$talkMore && strlen($currentSentence) >= MINIMUM_SENTENCE_SIZE) {
                    $splitSentences[] = $currentSentence;
                    $currentSentence = '';
                }
            }
        }
    }

    // Flush the last accumulated chunk
    if (!empty($currentSentence)) {
        $splitSentences[] = $currentSentence;
    }

    return $splitSentences;
}

function getEndOfSentencePunctuation() {
    $en='.?!';
    $cjk='。？！';

    return $en.$cjk;
}

function remove_between($marker, $s_input) {
    $s_res = $s_input;
    $p_start=null;
    $i_mk_len = strlen($marker);
    if ($i_mk_len > 0) {
        $i_str_len = strlen($s_input);
        if ($i_str_len > (2 * $i_mk_len)) {
            $p_first = strpos($s_input, $marker);
            if (!($p_first === false)) {
                $p_last = strrpos($s_input, $marker);
                if ((!($p_first === false)) && ($p_last > $p_first)) {
                    $s1 = substr($s_input, 0, $p_start);
                    $s2 = substr($s_input, $p_last);
                    $s_res = $s1 . $s2;
                }
            }
            
        }
    }
    return $s_res;
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

/**
 * Extract narration (text in asterisks) from dialogue
 * Handles multiple narration blocks throughout the text
 *
 * @param string $text The full text potentially containing narration
 * @return array ['narration' => array of narration texts, 'dialogue' => cleaned dialogue]
 */
function extractNarrationAndDialogue($text) {
    $narrations = [];
    $remainingText = $text;

    // IMPORTANT: Check for leftover "* " from sentence splitting FIRST, before checking paired asterisks
    // This prevents "* dialogue with *emphasis*" from being treated as narration
    if (preg_match('/^\*\s+(.+)$/s', $text, $matches)) {
        // This is likely dialogue that got a leftover "* " prefix from sentence splitting
        // Treat it as dialogue, not narration
        $remainingText = trim($matches[1]);
        Logger::info("[extractNarrationAndDialogue] Detected leftover asterisk from sentence split, treating as dialogue: " . substr($remainingText, 0, 50));
    }
    // Try to extract paired asterisks at the START of the sentence: *narration* dialogue
    else if (preg_match('/^\*([^*]+)\*\s*(.*)$/s', $text, $matches)) {
        // Only extract narration if it's at the beginning, followed by dialogue
        $narrations = [trim($matches[1])];
        $remainingText = trim($matches[2]);

        Logger::info("[extractNarrationAndDialogue] Found narration at start (paired asterisks): " . substr($narrations[0], 0, 50));
    }
    // If starts with asterisk and ends with period/punctuation, it's pure narration
    else if (preg_match('/^\*([^*]+)[.!?]\s*$/s', $text, $matches)) {
        // Single asterisk at start with punctuation at end - pure narration, no dialogue
        $narrations = [trim($matches[1], '. !?')];
        $remainingText = ''; // All narration, no dialogue

        Logger::info("[extractNarrationAndDialogue] Found 1 narration block (single asterisk, complete sentence)");
    }
    else {
        Logger::info("[extractNarrationAndDialogue] No narration found in: " . substr($text, 0, 100));
    }

    return [
        'narrations' => $narrations,
        'dialogue' => $remainingText,
        'has_narration' => !empty($narrations)
    ];
}

/**
 * Save current TTS voice settings
 * @return array Current TTS settings
 */
function saveCurrentVoiceSettings() {
    return isset($GLOBALS['TTS']) ? $GLOBALS['TTS'] : [];
}

/**
 * Load The Narrator's voice settings into GLOBALS
 */
function loadNarratorVoiceSettings() {
    require_once(__DIR__ . "/core/narrator.class.php");
    $narrator = new Narrator();
    $voiceid = $narrator->get('voiceid');

    if (!$voiceid) {
        $voiceid = 'TheNarrator'; // Fallback default
    }

    // Apply Narrator voice to all TTS providers
    $GLOBALS['TTS']['XTTSFASTAPI']['voiceid']  = $voiceid;
    $GLOBALS['TTS']['CHATTERBOX']['voiceid']   = $voiceid;
    $GLOBALS['TTS']['POCKETTTS']['voiceid']    = $voiceid;
    $GLOBALS['TTS']['MELOTTS']['voiceid']      = $voiceid;
    $GLOBALS['TTS']['MIMIC3']['voice']         = $voiceid;
    $GLOBALS['TTS']['XVASYNTH']['model']       = $voiceid;
    $GLOBALS['TTS']['ZONOS_GRADIO']['voiceid'] = $voiceid;
    $GLOBALS['TTS']['PIPERTTS']['voiceid']     = $voiceid;
    $GLOBALS['TTS']['ELEVEN_LABS']['voice_id'] = $voiceid;
    $GLOBALS['TTS']['AZURE']['voice']          = $voiceid;
    $GLOBALS['TTS']['KOKORO']['voiceid']       = $voiceid;
    $GLOBALS['TTS']['openai']['voice']         = $voiceid;
    $GLOBALS['TTS']['deepgram']['model']       = $voiceid;
    $GLOBALS['TTS']['CARTESIA']['voiceid']     = $voiceid;
    $GLOBALS['TTS']['INWORLD']['voiceid']      = $voiceid;
}

/**
 * Restore previously saved voice settings
 * @param array $savedSettings The settings to restore
 */
function restoreVoiceSettings($savedSettings) {
    if (!empty($savedSettings)) {
        $GLOBALS['TTS'] = $savedSettings;
    }
}


function unmoodSentence($sentence) {
    global $forceMood;

    $output = $sentence;

    // Determine whether to process asterisks:
    // This function is used to prepare text for TTS, so we ALWAYS want to strip asterisks
    // (narration should never be spoken by NPCs, even when inline narration is enabled)
    // The only exception is if explicitly disabled via strip_emotes_from_output or REMOVE_ASTERISKS_FROM_OUTPUT
    $processAsterisks = true; // Default to stripping asterisks for TTS

    if (array_key_exists('strip_emotes_from_output', $GLOBALS)) {
        $processAsterisks = (bool)$GLOBALS['strip_emotes_from_output'];
    } elseif (isset($GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT'])) {
        error_log("[unmoodSentence] REMOVE_ASTERISKS_FROM_OUTPUT is setted to <{$GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT']}>" );
        $processAsterisks=$GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT'];
    }
    

    if ($processAsterisks === true ) {
        error_log("[unmoodSentence] REMOVE_ASTERISKS_FROM_OUTPUT FULL is active! $sentence <" . ($GLOBALS['strip_emotes_from_output'] ?? 'N/A') . "> <" . ($GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT'] ?? 'N/A') . ">" );

        // If the entire message is wrapped in asterisks, strip them from both ends
        if (str_starts_with($output, '*') && str_ends_with($output, '*')) {
            $output = trim($output, '*'); // correct trimming of leading/trailing asterisks
        } else {
            // Remove text between single-pair asterisks
            $output = preg_replace('/\*([^*]+)\*/', '', $output);
        }
    }
    // is this the users intention if they set REMOVE_ASTERISKS false?
    else {
        // Remove text bewteen * * if two or more words inside
        error_log("[unmoodSentence] REMOVE_ASTERISKS_FROM_OUTPUT PARTIAL is active! $sentence" );
        $output = preg_replace('/\*(\w+\s+\w+.*?)\*/', '', $sentence);
    }

    // Remove common emote tokens wrapped in asterisks (user intention?)
    $output = strtr($output, [
        "*Smirks*" => "", "*smirks*" => "",
        "*winks*" => "", "*wink*" => "", "*smirk*" => "", "*gasps*" => "", "*chuckles*" => "", "*giggles*" => "", "*Giggles*" => "", "*laughs*" => "",
        "*gasp*" => "", "*moans*" => "", "*whispers*" => "", "*moan*" => "",
        "*pant*" => "", "*cough*" => "", "*hiccup*" => "", "*whimper*" => ""
    ]);

    // Non-asterisk-related cleanup always applies
    $output = strtr($output, [
        "#SpeechStyle" => "",
        "#SpeechStyle:" => ""
    ]);
    
    // Clean player name from output (for AUTOCHAT mode) - handles multiple repetitions
    if (isset($GLOBALS["PLAYER_NAME"])) {
        $playerName = preg_quote($GLOBALS["PLAYER_NAME"], '/');
        // Remove all leading occurrences of "PLAYERNAME:" or "PLAYERNAME: " (case-insensitive)
        $output = preg_replace('/^(?:' . $playerName . '\s*:\s*)+/i', '', $output);
    }
    
    // Remove AUTOCHAT mode wrapper pattern **(...)** (after player name is cleaned)
    $output = preg_replace('/^\*\*\([^)]*\)\*\*\s*/i', '', $output);

    $output = preg_replace('/\s*# ?ACTIONS.*/', '', $output);  // Remove "#ACTIONS ..."
    $output = preg_replace('/#[A-Za-z]+/', '', $output);       // Remove "#<text>"

    // Remove quotes
    $output = preg_replace('/"/', '', $output);

    // Remove parenthesized content and trim the result
    $output = preg_replace('/\((.*?)\)/i', '', $output);
    $responseTextUnmooded = trim($output);

    // Handle whispering mood marker
    if (stripos($responseTextUnmooded, "whispering:") !== false) {
        $responseTextUnmooded = str_ireplace("whispering:", "", $responseTextUnmooded);
        $forceMood = "whispering";
    }

    return $responseTextUnmooded;
}

function returnLines($lines,$writeOutput=true)
{
    global $db, $startTime, $forceMood, $staticMood, $talkedSoFar, $FORCED_STOP, $TRANSFORMER_FUNCTION,$receivedData;

    // Check if inline narration is enabled
    $inlineNarrationEnabled = isset($GLOBALS["INLINE_NARRATION_ENABLED"]) ? (bool)$GLOBALS["INLINE_NARRATION_ENABLED"] : false;

    // If inline narration is enabled, recombine split narration sentences
    if ($inlineNarrationEnabled) {
        $recombinedLines = [];
        $i = 0;
        while ($i < count($lines)) {
            $currentLine = trim($lines[$i]);

            // Check if this line looks like narration: starts with * and ends with period/no dialogue
            if (preg_match('/^\*[^*]+\.?\s*$/', $currentLine)) {
                // This is a narration-only sentence, check if next line is dialogue
                if ($i + 1 < count($lines) && !preg_match('/^\*/', trim($lines[$i + 1]))) {
                    // Next line doesn't start with *, combine them
                    $recombinedLines[] = $currentLine . ' ' . trim($lines[$i + 1]);
                    error_log("[returnLines] Recombined narration + dialogue: " . substr($currentLine . ' ' . trim($lines[$i + 1]), 0, 100));
                    $i += 2; // Skip next line since we combined it
                    continue;
                }
            }

            $recombinedLines[] = $currentLine;
            $i++;
        }
        $lines = $recombinedLines;
        error_log("[returnLines] After recombination: " . count($lines) . " lines");
    }

    foreach ($lines as $n => $sentence) {

        if ($FORCED_STOP) {
            return;
        }
        
        if (is_array($sentence))
            return;
        
        // Remove actions
        if (isset($GLOBALS["startTimeAfterPlayerTTTS"]))
            $elapsedTimeAI= microtime(true) - $GLOBALS["startTimeAfterPlayerTTTS"];
        else
            $elapsedTimeAI= microtime(true) - $startTime;
        //error_log("PRE LLM STATUS DONE 2: ". (microtime(true) - $startTime));

        $pattern = '/<[^>]+>/';
        $output = str_replace("#CHAT#", "", preg_replace($pattern, '', $sentence));

        // This should be reworked
        //$sentence = preg_replace('/[[:^print:]]/', '', $output); // Remove non ASCII chracters

        $sentence=$output;

        // Preserve the original sentence for subtitles BEFORE any processing
        // Check if inline narration is enabled (default to false if not set)
        $inlineNarrationEnabled = isset($GLOBALS["INLINE_NARRATION_ENABLED"]) ? (bool)$GLOBALS["INLINE_NARRATION_ENABLED"] : false;
        $sentenceForSubtitles = $sentence; // Keep the original with narration

        // Strip "(Talking to ...)" from player speech for cleaner subtitles
        if ($inlineNarrationEnabled) {
            $sentence = preg_replace('/\s*\(Talking to [^)]+\)\s*$/i', '', $sentence);
            $sentenceForSubtitles = preg_replace('/\s*\(Talking to [^)]+\)\s*$/i', '', $sentenceForSubtitles);
        }

        // Check if we should split narration to The Narrator BEFORE unmoodSentence strips asterisks
        $splitNarration = false;
        $narrationParts = null;
        if ($inlineNarrationEnabled) {
            $narrationParts = extractNarrationAndDialogue($sentenceForSubtitles);
            $splitNarration = $narrationParts['has_narration'];

            // Debug logging
            Logger::info("[INLINE_NARRATION] Enabled: true");
            Logger::info("[INLINE_NARRATION] Original sentence: " . $sentenceForSubtitles);
            Logger::info("[INLINE_NARRATION] Has narration: " . ($splitNarration ? 'yes' : 'no'));
            if ($splitNarration) {
                Logger::info("[INLINE_NARRATION] Narrations: " . json_encode($narrationParts['narrations']));
                Logger::info("[INLINE_NARRATION] Dialogue: " . $narrationParts['dialogue']);
            }
        } else {
            Logger::info("[INLINE_NARRATION] Disabled or not set");
        }

        $responseTextUnmooded=unmoodSentence($sentence);

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
        } elseif (isset($GLOBALS["LAST_LLM_RESPONSE"]["mood"]) && !empty($GLOBALS["LAST_LLM_RESPONSE"]["mood"])) {
            // Use mood from JSON response (set by connector)
            $mood = trim($GLOBALS["LAST_LLM_RESPONSE"]["mood"]);
        } elseif (!empty($matches) && !empty($matches[1]) && isset($matches[1][0])) {
            // Fallback to SSML-style mood extraction for backward compatibility
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
        $responseForTTS = $responseTextUnmooded; // TTS gets the "unmooded" version (narration stripped)

        // Set up subtitles based on whether inline narration is enabled
        if ($inlineNarrationEnabled && !$splitNarration) {
            // Preserve narration in subtitles - use the original sentence
            $responseForSubtitles = $sentenceForSubtitles;
            $responseForSubtitles = preg_replace("/{$GLOBALS["HERIKA_NAME"]}\s*:\s*/", '', $responseForSubtitles);
            // Remove quotes and other non-narration cleanup
            $responseForSubtitles = preg_replace('/"/', '', $responseForSubtitles);
            $responseForSubtitles = preg_replace('/\s*# ?ACTIONS.*/', '', $responseForSubtitles);
            $responseForSubtitles = preg_replace('/#[A-Za-z]+/', '', $responseForSubtitles);
            $responseForSubtitles = trim($responseForSubtitles);
        } else {
            // If narration is disabled or will be split, use the same text as TTS (narration stripped)
            $responseForSubtitles = strlen($responseTextUnmooded) > _MAX_SUBTITLE_LENGTH ?
            substr($responseTextUnmooded, 0, _MAX_SUBTITLE_LENGTH) :
            $responseTextUnmooded;
        }

        $ttsOutput = null;

        if (Translation::$response) {
            Translation::$sentences[$n] = unmoodSentence(Translation::$sentences[$n]);
            Translation::$sentences[$n] = preg_replace("/{$GLOBALS["HERIKA_NAME"]}\s*:\s*/", '', Translation::$sentences[$n]);

            if (Translation::isAudioEnabled() || Translation::isPlayerAudioEnabled()) {
                $responseForTTS = Translation::$sentences[$n]; // script for TTS to generate audio from
            }
            if (Translation::isTextEnabled()) {
                $responseForSubtitles = Translation::$sentences[$n]; // in-game subtitles
            }
            if (Translation::isSaveTranslationEnabled()) {
                // replace the original speech with the translated text in the context history
                $responseText = Translation::$sentences[$n];
                $responseTextUnmooded = Translation::$sentences[$n];
            }
        }

        if (isset($GLOBALS["FEATURES"]["MISC"]["TTS_RANDOM_PITCH"])&&($GLOBALS["FEATURES"]["MISC"]["TTS_RANDOM_PITCH"])) {
            $random_per_character=sprintf('%u', crc32($GLOBALS["HERIKA_NAME"])); // Unsigned integer
            $pitch=$random_per_character%5;

            if ($pitch==0)
                $GLOBALS["TTS_FFMPEG_FILTERS"]["rubberband"]="rubberband=pitch=1.02";
            if ($pitch==1)
                $GLOBALS["TTS_FFMPEG_FILTERS"]["rubberband"]="rubberband=pitch=0.98";
            if ($pitch==2)
                $GLOBALS["TTS_FFMPEG_FILTERS"]["rubberband"]="rubberband=pitch=1.01";
            if ($pitch==3)
                $GLOBALS["TTS_FFMPEG_FILTERS"]["rubberband"]="rubberband=pitch=0.99";
            if ($pitch==4)
                ;
        }

        if ($responseTextUnmooded) {
            // Check if we need to split narration to The Narrator
            if ($splitNarration && $narrationParts && !empty($narrationParts['narrations'])) {
                Logger::info("[INLINE_NARRATION] Splitting narration - processing " . count($narrationParts['narrations']) . " blocks");

                // Save the current NPC voice settings
                $savedVoiceSettings = saveCurrentVoiceSettings();
                $savedHerikaName = $GLOBALS["HERIKA_NAME"];

                Logger::info("[INLINE_NARRATION] Saved NPC name: " . $savedHerikaName);

                // Process each narration block with The Narrator's voice
                foreach ($narrationParts['narrations'] as $narrationText) {
                    if (empty(trim($narrationText))) {
                        continue; // Skip empty narrations
                    }

                    Logger::info("[INLINE_NARRATION] Processing narration: " . $narrationText);

                    // Switch to Narrator voice
                    loadNarratorVoiceSettings();
                    $GLOBALS["HERIKA_NAME"] = "The Narrator";

                    Logger::info("[INLINE_NARRATION] Switched to Narrator, voice settings loaded");

                    // Prepare narration for TTS (with asterisks for subtitle display)
                    $narrationForTTS = $narrationText;
                    $narrationForSubtitles = "*" . $narrationText . "*";

                    Logger::info("[INLINE_NARRATION] Generating TTS with function: " . $GLOBALS["TTSFUNCTION"]);

                    // Generate TTS for narration using the configured TTS function
                    $narratorTtsOutput = null;
                    if ($GLOBALS["TTSFUNCTION"] == "azure") {
                        require_once(__DIR__."/../tts/tts-azure.php");
                        $narratorTtsOutput = $GLOBALS["TTS_IN_USE"]($narrationForTTS, "default", $narrationForSubtitles);
                    } else if ($GLOBALS["TTSFUNCTION"] == "mimic3") {
                        require_once(__DIR__."/../tts/tts-mimic3.php");
                        $narratorTtsOutput = $GLOBALS["TTS_IN_USE"]($narrationForTTS, "default", $narrationForSubtitles);
                    } else if ($GLOBALS["TTSFUNCTION"] == "piper-tts") {
                        require_once(__DIR__."/../tts/tts-piper-tts.php");
                        $narratorTtsOutput = $GLOBALS["TTS_IN_USE"]($narrationForTTS, "default", $narrationForSubtitles);
                    } else if ($GLOBALS["TTSFUNCTION"] == "11labs") {
                        require_once(__DIR__."/../tts/tts-11labs.php");
                        $narratorTtsOutput = $GLOBALS["TTS_IN_USE"]($narrationForTTS, "default", $narrationForSubtitles);
                    } else if ($GLOBALS["TTSFUNCTION"] == "gcp") {
                        require_once(__DIR__."/../tts/tts-gcp.php");
                        $narratorTtsOutput = $GLOBALS["TTS_IN_USE"]($narrationForTTS, "default", $narrationForSubtitles);
                    } else if ($GLOBALS["TTSFUNCTION"] == "coqui-ai") {
                        require_once(__DIR__."/../tts/tts-coqui-ai.php");
                        $narratorTtsOutput = $GLOBALS["TTS_IN_USE"]($narrationForTTS, "default", $narrationForSubtitles);
                    } else if ($GLOBALS["TTSFUNCTION"] == "xvasynth") {
                        require_once(__DIR__."/../tts/tts-xvasynth.php");
                        $narratorTtsOutput = $GLOBALS["TTS_IN_USE"]($narrationForTTS, "default", $narrationForSubtitles);
                    } else if ($GLOBALS["TTSFUNCTION"] == "openai") {
                        require_once(__DIR__."/../tts/tts-openai.php");
                        $narratorTtsOutput = $GLOBALS["TTS_IN_USE"]($narrationForTTS, "default", $narrationForSubtitles);
                    } else if ($GLOBALS["TTSFUNCTION"] == "convai") {
                        require_once(__DIR__."/../tts/tts-convai.php");
                        $narratorTtsOutput = $GLOBALS["TTS_IN_USE"]($narrationForTTS, "default", $narrationForSubtitles);
                    } else if ($GLOBALS["TTSFUNCTION"] == "xtts") {
                        require_once(__DIR__."/../tts/tts-xtts.php");
                        $narratorTtsOutput = $GLOBALS["TTS_IN_USE"]($narrationForTTS, "default", $narrationForSubtitles);
                    } else if ($GLOBALS["TTSFUNCTION"] == "stylettsv2") {
                        require_once(__DIR__."/../tts/tts-stylettsv2-2.php");
                        $narratorTtsOutput = $GLOBALS["TTS_IN_USE"]($narrationForTTS, "default", $narrationForSubtitles);
                    } else if ($GLOBALS["TTSFUNCTION"] == "koboldcpp") {
                        require_once(__DIR__."/../tts/tts-koboldcpp.php");
                        $narratorTtsOutput = $GLOBALS["TTS_IN_USE"]($narrationForTTS, "default", $narrationForSubtitles);
                    } else if ($GLOBALS["TTSFUNCTION"] == "zonos_gradio") {
                        require_once(__DIR__."/../tts/tts-zonos_gradio.php");
                        $narratorTtsOutput = $GLOBALS["TTS_IN_USE"]($narrationForTTS, "default", $narrationForSubtitles);
                    } else if ($GLOBALS["TTSFUNCTION"] == "cartesia") {
                        require_once(__DIR__."/../tts/tts-cartesia.php");
                        $narratorTtsOutput = $GLOBALS["TTS_IN_USE"]($narrationForTTS, "default", $narrationForSubtitles);
                    } else if ($GLOBALS["TTSFUNCTION"] == "inworld") {
                        require_once(__DIR__."/../tts/tts-inworld.php");
                        $narratorTtsOutput = $GLOBALS["TTS_IN_USE"]($narrationForTTS, "default", $narrationForSubtitles);
                    } else {
                        if (file_exists(__DIR__."/../tts/tts-".$GLOBALS["TTSFUNCTION"].".php")) {
                            require_once(__DIR__."/../tts/tts-".$GLOBALS["TTSFUNCTION"].".php");
                            $narratorTtsOutput = $GLOBALS["TTS_IN_USE"]($narrationForTTS, "default", $narrationForSubtitles);
                        }
                    }

                    // Track narrator TTS output
                    if ($narratorTtsOutput) {
                        $GLOBALS["TRACK"]["FILES_GENERATED"][] = $narratorTtsOutput;
                        Logger::info("[INLINE_NARRATION] Narrator TTS generated: " . $narratorTtsOutput);

                        // Output narrator speech to game immediately
                        if ($writeOutput) {
                            // Use the same format as the main output at line 1093
                            $narratorListener = isset($GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"]) ? $GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"] : "";
                            $narratorExpression = ""; // No expression for narrator
                            $narratorAnimation = ""; // No animation for narrator

                            echo "The Narrator|ScriptQueue|{$narrationForSubtitles}/{$narratorExpression}/{$narratorListener}/{$narratorAnimation}/{$narrationText}\r\n";
                            if (ob_get_level()) @ob_flush();
                            @flush();
                            Logger::info("[INLINE_NARRATION] Narrator speech sent to game: " . $narrationForSubtitles);
                        }
                    } else {
                        Logger::warn("[INLINE_NARRATION] WARNING: Narrator TTS returned null/empty");
                    }
                }

                // Restore NPC voice settings
                restoreVoiceSettings($savedVoiceSettings);
                $GLOBALS["HERIKA_NAME"] = $savedHerikaName;

                // Now generate TTS for the NPC's dialogue (if any)
                if (!empty($narrationParts['dialogue'])) {
                    $responseForTTS = $narrationParts['dialogue'];
                    // Strip any remaining asterisks from NPC dialogue for subtitles
                    $responseForSubtitles = preg_replace('/\*/', '', $narrationParts['dialogue']);
                    $responseForSubtitles = trim($responseForSubtitles);
                    // Clean up any leading punctuation artifacts (., *, etc.)
                    $responseForSubtitles = ltrim($responseForSubtitles, '*.!? ');
                    $responseForSubtitles = trim($responseForSubtitles);
                    // IMPORTANT: Also update the main response variables so the output buffer uses dialogue only
                    $responseText = $responseForSubtitles;
                    $responseTextUnmooded = $responseForSubtitles;
                } else {
                    // No dialogue to speak, we're done
                    return;
                }
            }

            // Set TTS processing status
            pipeline_status_set('tts', true);

            // Generate regular TTS (either full text if no narration, or just dialogue after narration)
            if ($GLOBALS["TTSFUNCTION"] == "azure") {

                require_once(__DIR__."/../tts/tts-azure.php");
                $ttsOutput=$GLOBALS["TTS_IN_USE"]($responseForTTS, $mood, $responseForSubtitles);

            } else if ($GLOBALS["TTSFUNCTION"] == "mimic3") {

                require_once(__DIR__."/../tts/tts-mimic3.php");
                $ttsOutput=$GLOBALS["TTS_IN_USE"]($responseForTTS, $mood, $responseForSubtitles);

            } else if ($GLOBALS["TTSFUNCTION"] == "piper-tts") {

                require_once(__DIR__."/../tts/tts-piper-tts.php");
                $ttsOutput=$GLOBALS["TTS_IN_USE"]($responseForTTS, $mood, $responseForSubtitles);

            } else if ($GLOBALS["TTSFUNCTION"] == "11labs") {

                require_once(__DIR__."/../tts/tts-11labs.php");
                $ttsOutput=$GLOBALS["TTS_IN_USE"]($responseForTTS, $mood, $responseForSubtitles);

            } else if ($GLOBALS["TTSFUNCTION"] == "gcp") {

                require_once(__DIR__."/../tts/tts-gcp.php");
                $ttsOutput=$GLOBALS["TTS_IN_USE"]($responseForTTS, $mood, $responseForSubtitles);

            } else if ($GLOBALS["TTSFUNCTION"] == "coqui-ai") {

                require_once(__DIR__."/../tts/tts-coqui-ai.php");
                $ttsOutput=$GLOBALS["TTS_IN_USE"]($responseForTTS, $mood, $responseForSubtitles);

            } else if ($GLOBALS["TTSFUNCTION"] == "xvasynth") {

                require_once(__DIR__."/../tts/tts-xvasynth.php");
                $ttsOutput=$GLOBALS["TTS_IN_USE"]($responseForTTS, $mood, $responseForSubtitles);

            } else if ($GLOBALS["TTSFUNCTION"] == "openai") {

                require_once(__DIR__."/../tts/tts-openai.php");
                $ttsOutput=$GLOBALS["TTS_IN_USE"]($responseForTTS, $mood, $responseForSubtitles);

            } else if ($GLOBALS["TTSFUNCTION"] == "convai") {

                require_once(__DIR__."/../tts/tts-convai.php");
                $ttsOutput=$GLOBALS["TTS_IN_USE"]($responseForTTS, $mood, $responseForSubtitles);

            } else if ($GLOBALS["TTSFUNCTION"] == "xtts") {

                require_once(__DIR__."/../tts/tts-xtts.php");
                $ttsOutput=$GLOBALS["TTS_IN_USE"]($responseForTTS, $mood, $responseForSubtitles);

            } else if ($GLOBALS["TTSFUNCTION"] == "stylettsv2") {

                require_once(__DIR__."/../tts/tts-stylettsv2-2.php");
                $ttsOutput=$GLOBALS["TTS_IN_USE"]($responseForTTS, $mood, $responseForSubtitles);

            } else if ($GLOBALS["TTSFUNCTION"] == "stylettsv2") {

                require_once(__DIR__."/../tts/tts-stylettsv2-2.php");
                $ttsOutput=$GLOBALS["TTS_IN_USE"]($responseForTTS, $mood, $responseForSubtitles);

            } else if ($GLOBALS["TTSFUNCTION"] == "koboldcpp") {

                require_once(__DIR__."/../tts/tts-koboldcpp.php");
                $ttsOutput=$GLOBALS["TTS_IN_USE"]($responseForTTS, $mood, $responseForSubtitles);

            } else if ($GLOBALS["TTSFUNCTION"] == "zonos_gradio") {

                require_once(__DIR__."/../tts/tts-zonos_gradio.php");
                $ttsOutput=$GLOBALS["TTS_IN_USE"]($responseForTTS, $mood, $responseForSubtitles);

            } else if ($GLOBALS["TTSFUNCTION"] == "cartesia") {

                require_once(__DIR__."/../tts/tts-cartesia.php");
                $ttsOutput=$GLOBALS["TTS_IN_USE"]($responseForTTS, $mood, $responseForSubtitles);

            } else if ($GLOBALS["TTSFUNCTION"] == "inworld") {

                require_once(__DIR__."/../tts/tts-inworld.php");
                $ttsOutput=$GLOBALS["TTS_IN_USE"]($responseForTTS, $mood, $responseForSubtitles);

            }
            else {
                if (file_exists(__DIR__."/../tts/tts-".$GLOBALS["TTSFUNCTION"].".php")) {
                    require_once(__DIR__."/../tts/tts-".$GLOBALS["TTSFUNCTION"].".php");
                    $ttsOutput=$GLOBALS["TTS_IN_USE"]($responseForTTS, $mood, $responseForSubtitles);
                }
            }
            if (!$ttsOutput) {
                if (isset($GLOBALS["TTS_FALLBACK_FNCT"]))
                    $ttsOutput = $GLOBALS["TTS_FALLBACK_FNCT"]($responseForTTS, $mood, $responseForSubtitles);
            }
            
            // Clear TTS processing status
            pipeline_status_set('tts', false);
            
            $GLOBALS["TRACK"]["FILES_GENERATED"][] = $ttsOutput;
            if (trim($responseText)) {
                $talkedSoFar[] = $responseText;
            }
        }

        Logger::info("Speech sent for {$GLOBALS["HERIKA_NAME"]}, generator {$GLOBALS["TTSFUNCTION"]}, size: ".strlen($responseText). "  '".substr($responseText,0,10)."'");
        $elapsedTimeTTS=microtime(true) - $startTime;

        $outBuffer = array(
            'localts' => time(),
            'sent' => 1,
            'text' => trim(preg_replace('/\s\s+/', ' ', $responseText)),
            'actor' => $GLOBALS["HERIKA_NAME"],
            'action' => "AASPGQuestDialogue2Topic1B1Topic",
            'tag' => (isset($tag) ? $tag : "")
        );
        
        
        $GLOBALS["DEBUG"]["BUFFER"][] = "{$outBuffer["actor"]}|{$outBuffer["action"]}|$responseText\r\n";
       

        if ($writeOutput) {
            
            if (true) {
                 if (isset($GLOBALS["SCRIPTLINE_ANIMATION_SENT"]) && $GLOBALS["SCRIPTLINE_ANIMATION_SENT"]) 
                     $GLOBALS["SCRIPTLINE_ANIMATION"]="";
                else {
                    if ((rand(0,5)==0)){ // Will disable animations, 20% chance to trigger
                        $GLOBALS["SCRIPTLINE_ANIMATION"]="IdleDialogueExpressiveStart";
                    }
                    $GLOBALS["SCRIPTLINE_ANIMATION_SENT"]=true;
                }

                // HERIKA_ANIMATIONS is now always enabled (controlled via MCM in-game)
                // Removed profile configuration option as it's redundant with MCM control

                if (is_array($GLOBALS["SCRIPTLINE_LISTENER"]) && sizeof($GLOBALS["SCRIPTLINE_LISTENER"]) > 0 && is_string($GLOBALS["SCRIPTLINE_LISTENER"][0])) {
                    $GLOBALS["SCRIPTLINE_LISTENER"]=$GLOBALS["SCRIPTLINE_LISTENER"][0];
                    Logger::info("GLOBALS['SCRIPTLINE_LISTENER'] seems to be an array!");

                }


                $listenerFix=explode(" and ",$GLOBALS["SCRIPTLINE_LISTENER"]);
                // Don't touch original one
                $GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"]=$GLOBALS["SCRIPTLINE_LISTENER"];

                if (is_array($listenerFix) && (sizeof($listenerFix)>1)) {
                    $GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"]=trim($listenerFix[0]);
                }
                
                $listenerFix2=explode(",",$GLOBALS["SCRIPTLINE_LISTENER"]);
                if (is_array($listenerFix2) && (sizeof($listenerFix2)>1)) {
                    if (!isset($GLOBALS["SCRIPTLINE_LISTENER_CYCLE"])) {
                        $GLOBALS["SCRIPTLINE_LISTENER_CYCLE"]=0;
                    } else
                        $GLOBALS["SCRIPTLINE_LISTENER_CYCLE"]++;

                    if ($GLOBALS["SCRIPTLINE_LISTENER_CYCLE"]>(sizeof($listenerFix2)-1))
                        $GLOBALS["SCRIPTLINE_LISTENER_CYCLE"]=sizeof($listenerFix2)-1;

                    // Code to fix multiple listener issues
                    // Arrays to store positions of found names
                    $positions = [];           // For determining the first mentioned name
                    $positionsWithIndex = [];  // For determining the last mentioned name and its index

                    // Search for each name in the subtitle sentence
                    //$listenerFix2[]="Dragonborn";

                    foreach ($listenerFix2 as $index => $name) {
                        $pos = stripos($responseForSubtitles, trim($name)); // Case-insensitive search
                        if ($pos !== false) {
                            $positions[$name] = $pos;           // Save position for first-mention check
                            $positionsWithIndex[$index] = $pos; // Save index and position for last-mention check
                        }
                    }

                    if (!empty($positions)) {
                        // Sort positions to find the first mentioned name
                        asort($positions); // Ascending order by position
                        $listener = array_key_first($positions); // Get the name of the first mentioned
                        $GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"]=trim($listener);
                        // Sort positions to find the last mentioned index
                        arsort($positionsWithIndex); // Descending order by position
                        $nextListener = array_key_first($positionsWithIndex); // Get the index of the last mentioned name
                        if ($nextListener>0)
                            $GLOBALS["SCRIPTLINE_LISTENER_CYCLE"]=$nextListener-1;  // Next round will use this speaker if no refernce found.
                        else
                            $GLOBALS["SCRIPTLINE_LISTENER_CYCLE"]=$nextListener;
                        // Test
                        $GLOBALS["SCRIPTLINE_LISTENER_CYCLE"]=$nextListener;
                        // Output results
                        Logger::info("Applying smarter listenerFix2: $listener {$listenerFix2["$nextListener"]} {$GLOBALS["SCRIPTLINE_LISTENER"]} {$GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"]} {$GLOBALS["SCRIPTLINE_LISTENER_CYCLE"]}");

                    } else {
                        $listener=$listenerFix2[$GLOBALS["SCRIPTLINE_LISTENER_CYCLE"]];
                        $GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"]=trim($listener);
                    }

                    $GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"]=strtr($GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"],["Dragonborn"=>$GLOBALS["PLAYER_NAME"]]);

                    $npcList=DataBeingsInCloseRange();
                    $npcs=explode("|",$npcList);
                    if (is_array($npcs) && (!in_array($GLOBALS["SCRIPTLINE_LISTENER"],$npcs))) {
                        Logger::info("Listener {$GLOBALS["SCRIPTLINE_LISTENER"]} not around, forcing player: {$GLOBALS["SCRIPTLINE_LISTENER"]} {$GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"]} {$GLOBALS["SCRIPTLINE_LISTENER_CYCLE"]} {$npcList} ");
                        $GLOBALS["SCRIPTLINE_LISTENER"]=$GLOBALS["PLAYER_NAME"];

                    }

                    Logger::info("Applying listenerFix2: {$GLOBALS["SCRIPTLINE_LISTENER"]} {$GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"]}  {$GLOBALS["SCRIPTLINE_LISTENER_CYCLE"]}");
                    //$GLOBALS["SCRIPTLINE_LISTENER"]=trim($listenerFix2[ $GLOBALS["SCRIPTLINE_LISTENER_CYCLE"]]);
                    // $GLOBALS["SCRIPTLINE_LISTENER"] = trim($listenerFix2[array_rand($listenerFix2)]); // Random
                    

                }

                $responseTextPhonetic = "";
                if (Translation::isAudioEnabled() || Translation::isTextEnabled()) {
                    $responseTextPhonetic = $responseForTTS;
                }
                if (Translation::containsCyrillic($responseForTTS)) {
                    $responseTextPhonetic = Translation::convertCyrillicTextToLatin($responseForTTS);
                    Logger::debug("Transliterated Cyrillic text to: $responseTextPhonetic");
                }
                if (Translation::containsJapanese($responseForTTS)) {
                    $responseTextPhonetic = Translation::convertJapaneseTextToLatin($responseForTTS);
                    Logger::debug("Transliterated Japanese text to: $responseTextPhonetic");
                }
                
                // Calculate volume boost based on distance
                // Shouting distance threshold
                if (!defined('SHOUTING_DISTANCE_THRESHOLD')) {
                    define('SHOUTING_DISTANCE_THRESHOLD', 800);
                }
                if (!defined('SHOUTING_VOLUME_BOOST')) {
                    define('SHOUTING_VOLUME_BOOST', 1.3);
                }
                
                $volumeBoost = 1.0;
                $distance = isset($GLOBALS["LAST_SPEECH_DISTANCE"]) ? $GLOBALS["LAST_SPEECH_DISTANCE"] : 0.0;
                if ($distance > SHOUTING_DISTANCE_THRESHOLD) {
                    $volumeBoost = SHOUTING_VOLUME_BOOST; // 30% louder for shouting
                    Logger::info("Distance {$distance} > " . SHOUTING_DISTANCE_THRESHOLD . ", applying volume boost: {$volumeBoost}");
                }
                
                // Output here with volumeBoost appended
                echo "{$outBuffer["actor"]}|ScriptQueue|$responseForSubtitles/{$GLOBALS["SCRIPTLINE_EXPRESSION"]}/{$GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"]}/{$GLOBALS["SCRIPTLINE_ANIMATION"]}/$responseTextPhonetic/$volumeBoost\r\n";

                
                $GLOBALS["DEBUG_DATA"]["OUTPUT_LOG"]="{$outBuffer["actor"]}|ScriptQueue|$responseForSubtitles/{$GLOBALS["SCRIPTLINE_EXPRESSION"]}/{$GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"]}/{$GLOBALS["SCRIPTLINE_ANIMATION"]}/$responseTextPhonetic/$volumeBoost\r\n";
                if ($outBuffer["actor"]!="Player" && isset($GLOBALS["PATCH_ORIGINAL_MOOD_ISSUED"])) {
                    $GLOBALS["db"]->insert(
                        'moods_issued',
                        array(
                            'localts' => time(),
                            'ts' => $GLOBALS["gameRequest"][1],
                            'gamets' => $GLOBALS["gameRequest"][2],
                            'speaker' => $outBuffer["actor"],
                            'listener' =>$GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"],
                            'sess' => 'pending',
                            'mood' => $GLOBALS["PATCH_ORIGINAL_MOOD_ISSUED"]
        
        
                        )
                    );
                }

                file_put_contents(__DIR__."/../log/output_to_plugin.log",$GLOBALS["DEBUG_DATA"]["OUTPUT_LOG"], FILE_APPEND | LOCK_EX);
                
                //if (file_exists('/var/www/html/HerikaServer/lib/chat_helper_functions_custom_debug.php')) 
                //    include('/var/www/html/HerikaServer/lib/chat_helper_functions_custom_debug.php');                // debug 
            }
            else
                echo "{$outBuffer["actor"]}|{$outBuffer["action"]}|$responseForSubtitles\r\n";
            
            if (ob_get_level()) @ob_flush();
            @flush();
        }


        if (!isset($GLOBALS["PATCH_DONT_STORE_SPEECH_ON_DB"])) {

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
            if ($GLOBALS["SCRIPTLINE_LISTENER"]) {
                // Check if speaking from distance (shouting)
                if (!defined('SHOUTING_DISTANCE_THRESHOLD')) {
                    define('SHOUTING_DISTANCE_THRESHOLD', 800);
                }
                $distance = isset($GLOBALS["LAST_SPEECH_DISTANCE"]) ? $GLOBALS["LAST_SPEECH_DISTANCE"] : 0.0;
                if ($distance > SHOUTING_DISTANCE_THRESHOLD) {
                    $addonlistener="(speaking loudly to {$GLOBALS["SCRIPTLINE_LISTENER"]} from far away)";
                } else {
                    $addonlistener="(talking to {$GLOBALS["SCRIPTLINE_LISTENER"]})";
                }
            } else {
                $addonlistener="";
            }
            $originalRequest[3]="{$outBuffer["actor"]}: $responseTextUnmooded $addonlistener";
            logEvent($originalRequest);
            
            // Log chat here, because  function return comes back out of sync.
            $originalRequest[0]="chat";
            $originalRequest[1]++;
            $originalRequest[2]++;
            if ($GLOBALS["SCRIPTLINE_LISTENER"]) {
                // Check if speaking from distance (shouting)
                if (!defined('SHOUTING_DISTANCE_THRESHOLD')) {
                    define('SHOUTING_DISTANCE_THRESHOLD', 800);
                }
                $distance = isset($GLOBALS["LAST_SPEECH_DISTANCE"]) ? $GLOBALS["LAST_SPEECH_DISTANCE"] : 0.0;
                if ($distance > SHOUTING_DISTANCE_THRESHOLD) {
                    $addonlistener="(speaking loudly to {$GLOBALS["SCRIPTLINE_LISTENER"]} from far away)";
                } else {
                    $addonlistener="(talking to {$GLOBALS["SCRIPTLINE_LISTENER"]})";
                }
            } else {
                $addonlistener="";
            }
            $originalRequest[3]="{$outBuffer["actor"]}: $responseTextUnmooded $addonlistener";
            logEvent($originalRequest);
        }
        
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
                'session' => $momentum,
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


function lastSpeech($npcname)
{

    global $db;
    
    
    $speaker=$db->escape($npcname);
    $pj=$db->escape($GLOBALS["PLAYER_NAME"]);
    $lastRecords = $db->fetchAll("SELECT * from speech where (speaker ilike '$speaker' or speaker ilike '%$pj%' ) order by rowid desc LIMIT 5 OFFSET 0");
    $buffer="";
    foreach (array_reverse($lastRecords) as $record) {
        $buffer.="{$record["speaker"]}:{$record["speech"]}\n";
        
    }
    
    return $buffer;
    

}

function lastKeyWordsContext($n, $npcname='')
{

    global $db,$gameRequest;
    
    $m=$n+1;
    $speaker=$db->escape($npcname);
    $pj=$db->escape($GLOBALS["PLAYER_NAME"]);

    if (isset($gameRequest[2]))
        $whileago=round($gameRequest[2] - (2/ 0.0000024));
    else
        $whileago=0;
    
    $lastRecords = $db->fetchAll("SELECT speaker, location, companions, speech, gamets 
     from (select * from speech where  gamets>{$whileago}) AS sp 
     where ((speaker ilike '{$speaker}') or (speaker ilike '%{$pj}%' )) 
        order by gamets desc limit {$m} offset 0"); 
    
    $words=[];
    $uniqueArray=[];
    $uppercaseWords = [];
    foreach ($lastRecords as $record) {
        $pattern = '/[A-Za-z\-]{4,}/';
        $matches=[];
        preg_match_all($pattern,  $record["speech"],$matches);
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
    unset($words["Narrator"]);
    
    
    foreach ($words as $n=>$e) {
        if ($e>1)
           if (startsWithUppercase($n))
                $uniqueArray[]=$n;
    }
    $GLOBALS["DEBUG_DATA"]["textToEmbedFinalKwywords"]=implode(" ",$uniqueArray);
    
    rsort($uniqueArray);
    return $uniqueArray;
    
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

                        $outLocalBuffer.=round(($gameRequest[2]-$singleMemory["timestamp"]) * 0.0000001, 0)." days ago. {$fuzzMemoryElement}";

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

                        $outLocalBuffer.=round(($gameRequest[2]-$singleMemory["timestamp"]) * 0.0000001, 0)." days ago. {$fuzzMemoryElement}";

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
    $words=[];
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
    
    $uniqueArray=[];

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

// Returns how many in-game hours are needed to contain the last $limit events for $actor.
// This is used to dynamically adjust the memory window based on recent activity.

function getGametsLimitFor($actor) {
    if (isset($GLOBALS["GAMETS_LIMIT_FOR_ACTOR"][$actor])) {
        return $GLOBALS["GAMETS_LIMIT_FOR_ACTOR"][$actor];
    }
    global $db;

    $actorEscaped = $db->escape($actor);
    $limit = (int) $GLOBALS["CONTEXT_HISTORY"];

    $query = "
        SELECT 
            (MAX(gamets) - MIN(gamets)) * 0.0000024 AS hour_threshold
        FROM (
            SELECT gamets 
            FROM eventlog 
            WHERE type='chat'
            and people LIKE '%$actorEscaped%'
            ORDER BY gamets DESC
            LIMIT $limit
        ) AS recent_events
    ";

    $limitRow = $db->fetchOne($query);

    Logger::trace("MEMORY_EMBEDDING getGametsLimitFor($actor),CONTEXT_HISTORY: {$GLOBALS["CONTEXT_HISTORY"]} => {$limitRow["hour_threshold"]}");

    // If no data or result is too small, fall back to a sensible default (e.g. 72 in-game hours)
    $res = (isset($limitRow["hour_threshold"]) && $limitRow["hour_threshold"] > 0)
        ? $limitRow["hour_threshold"]
        : 72;

    $GLOBALS["GAMETS_LIMIT_FOR_ACTOR"][$actor] = $res;
    return $res;
}



function offerMemory($gameRequest)
{
    global $db;
    
    $startTime=microtime(true);

    if (isset($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) && !$GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"] ) {
        Logger::debug("MEMORY_EMBEDDING disabled");
        return "";
    }

    // PostgreSQL full text Searching
   
  
    
    $npc=$GLOBALS["HERIKA_NAME"];
    if ($npc=="The Narrator") { // Narrator knows all
       $npc=""; 
    }

    $timeThreshold=round($gameRequest[2]-(getGametsLimitFor($npc)/0.0000024),0)-1;

    error_log("[DataSearchMemoryByVector] Using timeThreshold $timeThreshold");
    $contextKeywords  = implode(" ", lastKeyWordsContext(5,$npc));

    if ($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["USE_TEXT2VEC"]) {
        $localStartTime = microtime(true);
        error_log("[DataSearchMemoryByVector calling]  : " . (microtime(true) - $localStartTime) . " seconds");
        $res = DataSearchMemoryByVector($gameRequest[3], $npc, true,$timeThreshold);
        error_log("[DataSearchMemoryByVector called 1]  : " . (microtime(true) - $localStartTime) . " seconds");
        $res2 = DataSearchMemoryByVector($gameRequest[3], $npc,false,$timeThreshold);
        error_log("[DataSearchMemoryByVector called 2]  : " . (microtime(true) - $localStartTime) . " seconds");

        if (isset($res[0]) && isset($res2[0])) {
            $resFinal = ($res[0]['rank_any'] >= $res2[0]['rank_any']) ? $res : $res2;
        } else {
            $resFinal = isset($res[0]['rank_any']) ? $res : (isset($res2[0]['rank_any']) ? $res2 : []);
        }
        $memories = $resFinal;
        
    } else {
        $memories=DataSearchMemory($gameRequest[3],$npc);
    }
   
    
    if (isset($memories[0])) {
        Logger::trace(print_r($memories[0],true));

        if (($memories[0]["rank_any"]==$memories[0]["rank_all"])&&($memories[0]["rank_any"]> (0.25+$GLOBALS["MEMORY_THRESHOLD_MODIFIER"]) )) {
            
            $memory=(isset($memories[0]["summary"])?$memories[0]["summary"]:"");
            
        } else if ((($memories[0]["rank_all"]+$memories[0]["rank_any"])/2)> (0.25+ $GLOBALS["MEMORY_THRESHOLD_MODIFIER"])) {
            
            $memory=(isset($memories[0]["summary"])?$memories[0]["summary"]:"");
            
        } else if ((($memories[0]["rank_all"]+$memories[0]["rank_any"])/2)>0.05 && false) {//This is too low
            
            $memory=(isset($memories[0]["summary"])?$memories[0]["summary"]:"");
            
        } else if (($memories[0]["rank_any"]> (0.50 + $GLOBALS["MEMORY_THRESHOLD_MODIFIER"])) && isset($memories[0]["mixed_distance"])) {// Search by mixed vector/fts .
            
            $memory=(isset($memories[0]["summary"])?$memories[0]["summary"]:"");
            
        } else {
           Logger::trace("[MEMORY] Memory discarded by scoring");
           error_log("[MEMORY] Memory discarded by scoring");
           return "";
        }
    } else {
        Logger::trace("[MEMORY] Memory not found");
        error_log("[MEMORY] Memory not found");
        return "";
    }
    
    if (!empty($memory)) {
        Logger::trace("adding date to memory <".substr($memory,0,25)."...>");
        $hoursAgo=round(($gameRequest[2]-$memories[0]["gamets_truncated"]) * 0.0000024, 0);
        if($hoursAgo > getGametsLimitFor($GLOBALS["HERIKA_NAME"])) {
            $daysAgo = floor(($gameRequest[2]-$memories[0]["gamets_truncated"]) * 0.0000001);
            $sk_date = gamets2str_format_date($memories[0]["gamets_truncated"], 'Y-m-d');    
            $s_prefix = "{$daysAgo} days ago, on {$sk_date} ... ";
        } else {
            $s_prefix = "{$hoursAgo} hours ago ... ";
            Logger::trace("Discarding memory because recent ($hoursAgo} hours ago ... )"); ////DataSearchMemoryByVector filter  by gamets, this should happend if using it
            error_log("[MEMORY] Discarding memory because recent ($hoursAgo} hours ago");
            return "";// Do not offer memory if its recent
        }
        $pattern = '/#Tags:.*/';
        $replacement = '';
        $output = preg_replace($pattern, $replacement, $memory);
        $memory = $s_prefix . $output;
        Logger::trace("Final memory <".substr($memory,0,25)."...>");
        error_log("Final memory <".substr($memory,0,25)."...>");

    }
    error_log("[MEMORY] Returning memory");
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
            $pattern = '/\([^)]+\)/';

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

                $outLocalBuffer.=round(($gameRequest[2]-$singleMemory["gamets_truncated"]) * 0.0000001, 0)." days ago. {$fuzzMemoryElement}";

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

function logEvent($dataArray,$forcePeople='')
{
    global $db;

    if (!isset($GLOBALS["CACHE_PEOPLE_LIMITED"])) {
        $GLOBALS["CACHE_PEOPLE_LIMITED"]=DataBeingsInCloseRange(true); // DataBeingsInRange() won't work as depends on user input
    } 
    
    if (!isset($GLOBALS["CACHE_LOCATION"])) {
        $GLOBALS["CACHE_LOCATION"]=DataLastKnownLocation();
    }
    
    if (!isset($GLOBALS["CACHE_PARTY"])) {
        $GLOBALS["CACHE_PARTY"]=DataGetCurrentPartyConf();
    }   

    if (!isset($dataArray)) { // function called without parameter values
        Logger::error("logEvent: undefined input parameter");
    } else {
        if( (!isset($dataArray[2])) || ($dataArray[2] < 5) ) { // wrong game timestamp. Sometime this function is called with gamets 0 or 1 then successive incremented values 
            $new_gts = DataLastKnownGameTS();    
            Logger::error("logEvent: wrong game timestamp " . ($dataArray[2] ?? 0) . " replaced with " . $new_gts);
            $dataArray[2] = $new_gts;
        }

        $insertResult = $db->insert(
            'eventlog',
            array(
                'ts' => $dataArray[1],
                'gamets' => $dataArray[2],
                'type' => $dataArray[0],
                'data' => $dataArray[3],
                'sess' => $dataArray[4]??'pending',
                'localts' => time(),
                'people'=> ($forcePeople)?$forcePeople:$GLOBALS["CACHE_PEOPLE_LIMITED"],
                'location'=>$GLOBALS["CACHE_LOCATION"],
                'party'=>$GLOBALS["CACHE_PARTY"]
            )
        );
    }
}

function selectRandomInArray($arraySource)
{
    $s_res = "";

    if (!isset($arraySource)||!is_array($arraySource)) {
        Logger::warn("chat_helper_functions selectRandomInArray: undefined array! ");
        return $s_res;
    }
    
    $n=sizeof($arraySource);
    
    if ($n>0) {
        if ($n==1) {
            $s_res = strtr($arraySource[0],["#HERIKA_NPC1#"=>$GLOBALS["HERIKA_NAME"]]);
        } else {
            $s_res = strtr($arraySource[rand(0, $n-1)],["#HERIKA_NPC1#"=>$GLOBALS["HERIKA_NAME"]]);
        }
        if (strlen(trim($s_res)) < 3) {
            Logger::warn("chat_helper_functions selectRandomInArray: wrong content - $s_res ");
        }
        return $s_res;
    } else {
        Logger::warn("chat_helper_functions selectRandomInArray: Empty array! ");
        return $s_res;
    }
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

function startsWithUppercase($string) {
    return preg_match('/^[A-Z]/', $string);
}

/**
 * Converts an array of strings into a bulleted list format.
 *
 * @param array $items Array of strings to convert into a bulleted list
 * @param string $bulletChar Character to use as bullet (default: "•")
 * @return string Formatted bulleted list with newlines
 */
function arrayToBulletedList($items, $bulletChar = " *") {
    if (!is_array($items) || empty($items)) {
       return "(none)";
    }
    
    $bulletedList = "";
    foreach ($items as $item) {
        if (trim($item))
            $bulletedList .= $bulletChar . " " . trim($item) . "\n";
    }
    
    if ($bulletedList)
        return rtrim($bulletedList);
    else
        return "(none)";
}

/**
 * Replace bracketed placeholder tokens in a string with runtime values.
 *
 * Scans the given text for the following placeholders and replaces them using
 * the current runtime values:
 *  - "{LOCATION}"       => DataLastKnownLocationHuman()
 *  - "{PLAYER_NAME}"    => $GLOBALS['PLAYER_NAME']
 *  - "{HERIKA_NAME}"    => $GLOBALS['HERIKA_NAME']
 *  - "{TEMPLATE_DIALOG}"=> $GLOBALS['TEMPLATE_DIALOG']
 *
 * The replacement is performed by strtr and returns the resulting string.
 * Non-string inputs will be converted to string before replacement.
 *
 * Note: replacements are performed in a single pass (no recursive expansion),
 * and values are inserted verbatim — sanitize or escape the result as needed
 * before output (e.g., to prevent XSS in HTML contexts).
 *
 * @param mixed $text The input text to process (will be cast to string).
 * @return string The text with bracketed placeholders replaced by their values.
 */
function make_replacements_bracketed($text)
{

    return strtr($text, [
        "{LOCATION}" => DataLastKnownLocationHuman(),
        "{PLAYER_NAME}"   => $GLOBALS["PLAYER_NAME"],
        "{HERIKA_NAME}"   => $GLOBALS["HERIKA_NAME"],
        "{TEMPLATE_DIALOG}"   => $GLOBALS["TEMPLATE_DIALOG"],
    ]);
}
