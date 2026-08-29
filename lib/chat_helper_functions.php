<?php

define("_MINIMAL_DISTANCE_TO_BE_THE_SAME", 0.0);
define("_MAXIMAL_DISTANCE_TO_BE_RELATED", 0.8);
define("_MINIMAL_ELEMENTS_TO_TRIGGER_MESSAGE", 3);
//prevent in-game buffer overflow (does not truncate tts only subtitles. Fixes long player input playertts (auto-chat / manual player scene setting)) - ideally player input needs splitting for tts but returnLines is not appropriate
define("_MAX_SUBTITLE_LENGTH", 1000);

require_once(__DIR__."/online_translation.php");
require_once(__DIR__."/utils_game_timestamp.php");
require_once(__DIR__."/pipeline_status.php");
require_once(__DIR__."/emote_moods.php");
require_once(__DIR__."/core/event_type.php");

function chimBuildLatestDiaryContextBlock(string $npcName, array $profileData): string
{
    $safeNpcName = trim($npcName);
    if ($safeNpcName === '') {
        return '';
    }

    $metadata = json_decode(strval($profileData['metadata'] ?? '{}'), true);
    if (!is_array($metadata)
        || !filter_var($metadata['LATEST_DIARY_CONTEXT_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
        return '';
    }

    $db = $GLOBALS['db'] ?? null;
    if (!is_object($db) || !method_exists($db, 'fetchOne') || !method_exists($db, 'escape')) {
        return '';
    }

    try {
        $escapedName = $db->escape($safeNpcName);
        $entry = $db->fetchOne(
            "SELECT topic, content
             FROM diarylog
             WHERE lower(trim(people)) = lower('{$escapedName}')
             ORDER BY gamets DESC, localts DESC, rowid DESC
             LIMIT 1"
        );
    } catch (Throwable $e) {
        Logger::warn("[LATEST_DIARY_CONTEXT] Unable to load diary context for {$safeNpcName}: " . $e->getMessage());
        return '';
    }

    $content = trim(strval($entry['content'] ?? ''));
    if ($content === '') {
        return '';
    }

    $topic = trim(strval($entry['topic'] ?? ''));
    $diaryText = $topic !== '' ? "Date: {$topic}\n{$content}" : $content;
    $escapedText = htmlspecialchars(
        $diaryText,
        ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE,
        'UTF-8'
    );

    return "\n<latest_diary_entry>\n{$escapedText}\n</latest_diary_entry>\n";
}

function callConfiguredTts($textString, $mood, $stringforhash)
{
    $ttsFunction = strval($GLOBALS["TTSFUNCTION"] ?? '');
    if ($ttsFunction === '') {
        return false;
    }

    if (strcasecmp($ttsFunction, 'none') === 0) {
        return false;
    }

    $specialFiles = [
        'stylettsv2' => __DIR__ . "/../tts/tts-stylettsv2-2.php",
    ];

    $ttsFile = $specialFiles[$ttsFunction] ?? (__DIR__ . "/../tts/tts-" . $ttsFunction . ".php");
    if (!file_exists($ttsFile)) {
        return false;
    }

    require_once($ttsFile);
    if (!isset($GLOBALS["TTS_IN_USE"]) || !is_callable($GLOBALS["TTS_IN_USE"])) {
        return false;
    }

    return $GLOBALS["TTS_IN_USE"]($textString, $mood, $stringforhash);
}

function getNpcTtsFallbackCandidates(): array
{
    $configured = $GLOBALS["TTS_NPC_FALLBACK_VOICES"] ?? [];
    if (!is_array($configured)) {
        $configured = [$configured];
    }
    if (empty($configured)) {
        $configured = [$GLOBALS["TTS_NPC_FALLBACK_VOICE"] ?? ''];
    }

    $currentVoice = trim(strval(
        $GLOBALS["PATCH_OVERRIDE_VOICE"]
        ?? $GLOBALS["TTS_NPC_RESOLVED_VOICE"]
        ?? ''
    ));
    $candidates = [];
    foreach ($configured as $voice) {
        $voice = trim(strval($voice));
        if ($voice === '' || strcasecmp($voice, $currentVoice) === 0) {
            continue;
        }
        $duplicate = array_filter(
            $candidates,
            fn($candidate) => strcasecmp($candidate, $voice) === 0
        );
        if (empty($duplicate)) {
            $candidates[] = $voice;
        }
    }

    return $candidates;
}

function canRetryNpcTtsWithFallback(): bool
{
    $currentNpcData = $GLOBALS["CHIM_CORE_CURRENT_NPC_DATA"] ?? null;
    $currentName = trim(strval($GLOBALS["HERIKA_NAME"] ?? ''));

    if (!is_array($currentNpcData) || $currentName === '') {
        return false;
    }
    if (strcasecmp($currentName, 'The Narrator') === 0) {
        return false;
    }
    if (strcasecmp(trim(strval($currentNpcData['npc_name'] ?? '')), $currentName) !== 0) {
        return false;
    }

    return !empty(getNpcTtsFallbackCandidates());
}

function callNpcTtsWithFallback($textString, $mood, $stringforhash)
{
    $ttsOutput = callConfiguredTts($textString, $mood, $stringforhash);
    if ($ttsOutput) {
        return $ttsOutput;
    }

    if (!canRetryNpcTtsWithFallback()) {
        return $ttsOutput;
    }

    $originalVoice = $GLOBALS["PATCH_OVERRIDE_VOICE"] ?? null;
    foreach (getNpcTtsFallbackCandidates() as $fallbackVoice) {
        Logger::warn("[TTS FALLBACK] Retrying NPC TTS for {$GLOBALS["HERIKA_NAME"]} with fallback voice '{$fallbackVoice}' after synthesis failure.");

        $GLOBALS["PATCH_OVERRIDE_VOICE"] = $fallbackVoice;
        $retryOutput = callConfiguredTts($textString, $mood, $stringforhash);
        if ($retryOutput) {
            $GLOBALS["TTS_NPC_RESOLVED_VOICE"] = $fallbackVoice;
            return $retryOutput;
        }
    }

    if ($originalVoice === null || trim(strval($originalVoice)) === '') {
        unset($GLOBALS["PATCH_OVERRIDE_VOICE"]);
    } else {
        $GLOBALS["PATCH_OVERRIDE_VOICE"] = $originalVoice;
    }

    return $ttsOutput;
}

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
            'omnivoice' => 'OMNIVOICE',
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
// This sentence will never be splitted: "It is, my Thane. The crisp air here is better than the soot of Whiterun. I've been honing my blade since dawn."

function findFastSentencePosition($s_string,$min_sentence_size=0) {
    // Find the position of the first sentence-ending punctuation followed by a space
    // This preserves ellipsis (...) because we require a space after the punctuation
    $eosPunc = preg_quote(getEndOfSentencePunctuation(), '/'); // .?!。？！

    // Match the EOS punctuation character followed by spaces
    // Negative lookbehind ensures we don't match after ellipsis (..)
    // "Don't split after ellipses either... Thanks :-)" -for example
    $splitSentenceRegex = "/([" . $eosPunc . "])(?<!\.\.)(?<!\.\.\.)\s+/u";

    // Find the first safe match and return the position of the EOS punctuation.
    // Do not split while a single-asterisk narration span is still open.
    if (preg_match_all($splitSentenceRegex, $s_string, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[1] as $match) {
            $position = $match[1];
            // Use the end of the matched punctuation so that multi-byte characters
            // (e.g. Japanese 。！？ which are 3 bytes in UTF-8) are not split mid-character.
            $endPosition = $position + strlen($match[0]) - 1;
            if ($min_sentence_size > 0 && $endPosition <= $min_sentence_size) {
                continue;
            }

            $candidate = substr($s_string, 0, $endPosition + 1);
            if (hasUnclosedSingleAsteriskBlock($candidate)) {
                continue;
            }

            return $endPosition;
        }
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

    if (!preg_match_all($splitSentenceRegex, $paragraph, $matches, PREG_OFFSET_CAPTURE)) {
        return [$paragraph];
    }

    $sentences = [];
    $chunkStart = 0;

    foreach ($matches[0] as $match) {
        $splitOffset = $match[1];
        $candidate = substr($paragraph, 0, $splitOffset);
        if (hasUnclosedSingleAsteriskBlock($candidate)) {
            continue;
        }

        $sentence = trim(substr($paragraph, $chunkStart, $splitOffset - $chunkStart));
        if ($sentence !== '') {
            $sentences[] = $sentence;
        }

        $chunkStart = $splitOffset + strlen($match[0]);
    }

    $tail = trim(substr($paragraph, $chunkStart));
    if ($tail !== '') {
        $sentences[] = $tail;
    }

    return $sentences ?: [$paragraph];
}

function split_sentences($paragraph)
{
    // Normalize newlines to periods -dont know what this is fixing, but i can see what it is breaking (.\n becomes .. is that useful?)- matt
//    $paragraph = strtr($paragraph, array(" \n\n" => ".", " \n" => ".", "\n\n" => ".", '\n' => ".", "\n" => "."));

    if (strlen($paragraph) <= MAXIMUM_SENTENCE_SIZE) {
        return [$paragraph];
    }

    $paragraphNcr = br2nl($paragraph); // Remove any BR tags
    $paragraphNcr = preg_replace('/([。！？])(?=\S)/u', '$1 ', $paragraphNcr);

    $sentences = split_at_end_of_sentence($paragraphNcr);

    return $sentences;
}

function split_sentences_stream($paragraph)
{
    if (strlen($paragraph) <= MAXIMUM_SENTENCE_SIZE) {
        return [$paragraph];
    }

    $paragraph = preg_replace('/([。！？])(?=\S)/u', '$1 ', $paragraph);
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
            $currentHasOpenAsteriskBlock = hasUnclosedSingleAsteriskBlock($currentSentence);

            // Keep multi-sentence *...* spans intact even if they temporarily exceed the normal max size.
            if (strlen($combined) > MAXIMUM_SENTENCE_SIZE && !$currentHasOpenAsteriskBlock) {
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
 * Count standalone single-asterisk markers while ignoring double-asterisk markup.
 */
function countSingleAsteriskMarkers($text) {
    if (!preg_match_all('/(?<!\*)\*(?!\*)/', (string)$text, $matches)) {
        return 0;
    }

    return count($matches[0]);
}

/**
 * Detect whether a single-asterisk span is still open.
 */
function hasUnclosedSingleAsteriskBlock($text) {
    return (countSingleAsteriskMarkers($text) % 2) !== 0;
}

/**
 * Normalize the leading text from a candidate dialogue segment salvaged out of
 * a malformed fully wrapped *...* reply.
 */
function normalizeWrappedDialogueLead($text) {
    $normalized = trim((string)$text);
    return preg_replace('/^[\s"\'\(\[\{]+/', '', $normalized);
}

/**
 * Detect whether the text after the first sentence in a malformed fully
 * wrapped *...* reply looks like spoken dialogue rather than more narration.
 */
function isLikelyWrappedDialogueLead($text) {
    $lead = normalizeWrappedDialogueLead($text);
    if ($lead === '') {
        return false;
    }

    if (preg_match('/\b(?:you|he|she|they)\s+(?:say|says|said|ask|asks|asked|reply|replies|replied|whisper|whispers|whispered|murmur|murmurs|murmured)\b/i', $lead)) {
        return false;
    }

    if (preg_match('/^(?:indeed|yes|no|of course|certainly|very well|as you wish|understood|ready|i am|i\'m|i will|i\'ll|i can|i cannot|i do|i did|i have|i\'ve|i understand|i obey|i serve|forgive me|thank you)\b/i', $lead)) {
        return true;
    }

    if (preg_match('/,\s*(?:my lord|milord|my lady|your majesty|your highness|my friend|sister|brother)\b/i', $lead)) {
        return true;
    }

    if (preg_match('/\b(?:if i do say so myself|i suppose|i think|i know|i dare say)\b/i', $lead)) {
        return true;
    }

    return false;
}

/**
 * Salvage a malformed reply where the model wrapped both narration and speech
 * inside a single *...* block. This only splits after the first sentence when
 * the remaining text strongly resembles spoken dialogue.
 */
function trySplitFullyWrappedNarrationReply($text) {
    $wrappedText = trim((string)$text);
    if ($wrappedText === '') {
        return null;
    }

    $sentences = split_at_end_of_sentence($wrappedText);
    if (count($sentences) < 2) {
        return null;
    }

    $narrationCandidate = trim($sentences[0]);
    $dialogueCandidate = trim(implode(' ', array_slice($sentences, 1)));
    if ($narrationCandidate === '' || $dialogueCandidate === '') {
        return null;
    }

    if (!isLikelyWrappedDialogueLead($dialogueCandidate)) {
        return null;
    }

    Logger::info("[extractNarrationAndDialogue] Salvaged malformed wrapped reply into narration + dialogue: " . substr($wrappedText, 0, 100));

    return [
        'narrations' => [$narrationCandidate],
        'dialogue' => $dialogueCandidate,
        'has_narration' => true
    ];
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
    else if (preg_match('/^\*([^*]+)\*(?:\s*[.!?,;:-])?\s*(.*)$/s', $text, $matches)) {
        // Only extract narration if it's at the beginning, followed by dialogue.
        // If the entire reply is wrapped in one *...* block, try to salvage the
        // first sentence as narration when the remainder clearly looks spoken.
        $wrappedText = trim($matches[1]);
        $remainingText = trim($matches[2]);

        if ($remainingText === '') {
            $wrappedSplit = trySplitFullyWrappedNarrationReply($wrappedText);
            if ($wrappedSplit !== null) {
                return $wrappedSplit;
            }
        }

        $narrations = [$wrappedText];

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

function stripPlayerAsteriskActions($text) {
    $strippedText = preg_replace('/\*[^*]*\*/', ' ', $text);
    $strippedText = str_replace('*', '', $strippedText);
    return trim(preg_replace('/\s+/', ' ', $strippedText));
}

function getInlineNarrationMode() {
    $mode = strtolower(trim((string)($GLOBALS["INLINE_NARRATION_MODE"] ?? "")));
    if (in_array($mode, ['disabled', 'narrator', 'npc', 'text_only'], true)) {
        return $mode;
    }

    if (isset($GLOBALS["INLINE_NARRATION_ENABLED"])) {
        return $GLOBALS["INLINE_NARRATION_ENABLED"] ? 'narrator' : 'disabled';
    }

    return 'disabled';
}

function isInlineNarrationEnabled() {
    return getInlineNarrationMode() !== 'disabled';
}

function shouldRemovePlayerAutochatAsterisks() {
    if (isset($GLOBALS['REMOVE_PLAYER_AUTOCHAT_ASTERISKS'])) {
        return (bool)$GLOBALS['REMOVE_PLAYER_AUTOCHAT_ASTERISKS'];
    }

    if (isset($GLOBALS['PLAYER_AUTOCHAT_ASTERISKS_ENABLED'])) {
        return !(bool)$GLOBALS['PLAYER_AUTOCHAT_ASTERISKS_ENABLED'];
    }

    return true;
}

function shouldSplitInlineNarration() {
    return getInlineNarrationMode() === 'narrator';
}

function shouldStripPlayerInputAsterisks() {
    if (isset($GLOBALS['REMOVE_ASTERISKS_FROM_PLAYER_INPUT'])) {
        return (bool)$GLOBALS['REMOVE_ASTERISKS_FROM_PLAYER_INPUT'];
    }

    if (isset($GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT'])) {
        return (bool)$GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT'];
    }

    return true;
}

function shouldStripNpcOutputAsterisks() {
    if (array_key_exists('strip_emotes_from_output', $GLOBALS)) {
        return (bool)$GLOBALS['strip_emotes_from_output'];
    }

    if (isset($GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'])) {
        return (bool)$GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'];
    }

    if (isset($GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT'])) {
        return (bool)$GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT'];
    }

    return true;
}

function normalizeAsteriskTextForSpeech($text) {
    // This just removes the asterisks (twice)
    $normalizedText = preg_replace('/\*([^*]+)\*/', '$1', (string)$text);
    return str_replace('*', '', $normalizedText);
}

function filterKnownNpcAsteriskTokens($text) {
    return strtr((string)$text, [
        "*Smirks*" => "", "*smirks*" => "",
        "*smiles*" => "", "*Smile*" => "", "*smile*" => "",
        "*winks*" => "", "*wink*" => "", "*smirk*" => "", "*gasps*" => "", "*chuckles*" => "", "*giggles*" => "", "*Giggles*" => "", "*laughs*" => "",
        "*gasp*" => "", "*moans*" => "", "*whispers*" => "", "*moan*" => "",
        "*pant*" => "", "*cough*" => "", "*hiccup*" => "", "*whimper*" => ""
    ]);
}

function formatPlayerSpeechText($text) {
    if (shouldStripPlayerInputAsterisks()) {
        return stripPlayerAsteriskActions($text);
    }

    return trim(preg_replace('/\s+/', ' ', normalizeAsteriskTextForSpeech($text)));
}

function formatNpcSpeechText($text) {
    $speechText = (string)$text;
    if (shouldStripNpcOutputAsterisks()) {
        $speechText = filterKnownNpcAsteriskTokens($speechText);
    }

    $speechText = normalizeAsteriskTextForSpeech($speechText);
    return trim(preg_replace('/\s+/', ' ', $speechText));
}

function stripOutputSpeakerPrefix($text, $speakerName = null) {
    $speakerName = $speakerName ?? ($GLOBALS["HERIKA_NAME"] ?? "");
    if ($speakerName === '') {
        return (string)$text;
    }

    return preg_replace('/^' . preg_quote((string)$speakerName, '/') . '\s*:\s*/i', '', (string)$text);
}

function stripOutputSpeakerPrefixAfterInlineNarration($text, $speakerName = null) {
    $speakerName = $speakerName ?? ($GLOBALS["HERIKA_NAME"] ?? "");
    if ($speakerName === '') {
        return (string)$text;
    }

    return preg_replace(
        '/^(\s*(?:\*[^*]+\*\s*)+)' . preg_quote((string)$speakerName, '/') . '\s*:\s*/i',
        '$1',
        (string)$text
    );
}

function stripLeadingParentheticalBlocks($text) {
    return preg_replace('/^\s*(?:\([^)]*\)\s*)+/', '', (string)$text);
}

function stripLeadingPlayerRespeechNarration($text) {
    $speechText = (string)$text;
    do {
        $previousText = $speechText;
        if (preg_match('/^\s*\*\*\((.*)\)\*\*\s*$/s', $speechText, $matches)
            || preg_match('/^\s*\*\*\((.*)\)\s*$/s', $speechText, $matches)) {
            $speechText = $matches[1];
        }
        $speechText = preg_replace('/^\s*\*\*\([^)]*\)\*\*\s*/', '', $speechText);
        $speechText = preg_replace('/^\s*\*\*\([^)]*\)\s*/', '', $speechText);
        $speechText = preg_replace('/^\s*(?:(?:\([^)]*\))|(?:\*[^*]+\*))\s*/', '', $speechText);
    } while ($speechText !== $previousText);

    return $speechText;
}

function convertLeadingParentheticalBlocksToInlineNarration($text) {
    $speechText = (string)$text;
    if (!preg_match('/^\s*((?:\([^)]*\)\s*)+)/', $speechText, $matches)) {
        return $speechText;
    }

    preg_match_all('/\(([^)]*)\)/', $matches[1], $narrationMatches);
    $narrationBlocks = [];
    foreach ($narrationMatches[1] as $block) {
        $block = trim((string)$block);
        if ($block !== '') {
            $narrationBlocks[] = "*{$block}*";
        }
    }

    if (empty($narrationBlocks)) {
        return stripLeadingParentheticalBlocks($speechText);
    }

    $remainingText = preg_replace('/^\s*(?:\([^)]*\)\s*)+/', '', $speechText);
    $inlineNarration = implode(' ', $narrationBlocks);
    return trim($inlineNarration . ' ' . ltrim((string)$remainingText));
}

function sanitizePlayerRespeechText($text, $speakerName = null) {
    $removeNarration = shouldRemovePlayerAutochatAsterisks();
    $speechText = str_replace(["\r", "\n"], ' ', (string)$text);
    $speechText = $removeNarration
        ? stripLeadingPlayerRespeechNarration($speechText)
        : convertLeadingParentheticalBlocksToInlineNarration($speechText);
    if (!$removeNarration) {
        $speechText = stripOutputSpeakerPrefixAfterInlineNarration($speechText, $speakerName);
    }
    $speechText = stripOutputSpeakerPrefix($speechText, $speakerName);
    $speechText = $removeNarration
        ? stripLeadingPlayerRespeechNarration($speechText)
        : convertLeadingParentheticalBlocksToInlineNarration($speechText);
    return trim(preg_replace('/\s+/', ' ', $speechText));
}

function cleanupDisplayText($text, $speakerName = null) {
    $displayText = stripOutputSpeakerPrefix((string)$text, $speakerName);
    $displayText = strtr($displayText, [
        "#SpeechStyle" => "",
        "#SpeechStyle:" => ""
    ]);
    $displayText = preg_replace('/^\*\*\([^)]*\)\*\*\s*/i', '', $displayText);
    $displayText = preg_replace('/"/', '', $displayText);
    $displayText = preg_replace('/\s*# ?ACTIONS.*/', '', $displayText);
    $displayText = preg_replace('/#[A-Za-z]+/', '', $displayText);
    return trim(preg_replace('/\s+/', ' ', $displayText));
}

function formatPlayerSubtitleText($text, $speakerName = null) {
    $speakerName = $speakerName ?? ($GLOBALS["PLAYER_NAME"] ?? null);
    $subtitleText = preg_replace(
        '/\s*\((?:(?:Talking|Whispering|Shouting) to [^)]+|speaking (?:loudly|privately) to [^)]+(?: from far away)?)\)\s*$/i',
        '',
        $text
    );
    return cleanupDisplayText($subtitleText, $speakerName);
}

function formatNpcSubtitleText($text) {
    $subtitleText = shouldStripNpcOutputAsterisks() ? formatNpcSpeechText($text) : (string)$text;
    return cleanupDisplayText($subtitleText, $GLOBALS["HERIKA_NAME"] ?? null);
}

function formatInlineNarrationDialogueSubtitleText($text) {
    $subtitleText = shouldStripNpcOutputAsterisks() ? formatNpcSpeechText($text) : (string)$text;
    $subtitleText = cleanupDisplayText($subtitleText, $GLOBALS["HERIKA_NAME"] ?? null);
    $subtitleText = ltrim($subtitleText, ".!?,;:- \t\n\r\0\x0B");
    return trim($subtitleText);
}

function formatNarrationSubtitleText($text) {
    $narrationText = trim((string)$text, " \t\n\r\0\x0B*");
    return "*{$narrationText}*";
}

function formatTextOnlyInlineNarrationSubtitleText($text) {
    return cleanupDisplayText($text, $GLOBALS["HERIKA_NAME"] ?? null);
}

function formatTextOnlyInlineNarrationSpeechText($text, $narrationParts = null) {
    $narrationParts = $narrationParts ?? extractNarrationAndDialogue($text);
    $speechText = $narrationParts['has_narration'] ? $narrationParts['dialogue'] : $text;
    return unmoodSentence($speechText);
}

function shouldStripAsterisksFromCleanContextBuffer() {
    $preserveAsterisksInContext = isset($GLOBALS["PRESERVE_ASTERISKS_IN_CONTEXT"]) ? (bool)$GLOBALS["PRESERVE_ASTERISKS_IN_CONTEXT"] : false;
    return getInlineNarrationMode() === 'disabled' && !$preserveAsterisksInContext;
}

/**
 * Save current TTS voice settings
 * @return array Current TTS settings
 */
function saveCurrentVoiceSettings() {
    return [
        'tts' => isset($GLOBALS['TTS']) ? $GLOBALS['TTS'] : [],
        'has_tts_function' => array_key_exists('TTSFUNCTION', $GLOBALS),
        'tts_function' => $GLOBALS['TTSFUNCTION'] ?? null,
        'has_tts_function_alias' => array_key_exists('TTS_FUNCTION', $GLOBALS),
        'tts_function_alias' => $GLOBALS['TTS_FUNCTION'] ?? null,
        'has_current_tts_connector_id' => array_key_exists('CHIM_CORE_CURRENT_TTS_CONNECTOR_ID', $GLOBALS),
        'current_tts_connector_id' => $GLOBALS['CHIM_CORE_CURRENT_TTS_CONNECTOR_ID'] ?? null,
        'has_patch_override_voice' => array_key_exists('PATCH_OVERRIDE_VOICE', $GLOBALS),
        'patch_override_voice' => $GLOBALS['PATCH_OVERRIDE_VOICE'] ?? null,
        'has_patch_override_voice_id' => array_key_exists('PATCH_OVERRIDE_VOICE_ID', $GLOBALS),
        'patch_override_voice_id' => $GLOBALS['PATCH_OVERRIDE_VOICE_ID'] ?? null,
        'has_patch_override_tts_language' => array_key_exists('PATCH_OVERRIDE_TTS_LANGUAGE', $GLOBALS),
        'patch_override_tts_language' => $GLOBALS['PATCH_OVERRIDE_TTS_LANGUAGE'] ?? null,
        'has_patch_override_tts_options' => array_key_exists('PATCH_OVERRIDE_TTS_OPTIONS', $GLOBALS),
        'patch_override_tts_options' => $GLOBALS['PATCH_OVERRIDE_TTS_OPTIONS'] ?? null,
    ];
}

function applyVoiceIdToTtsGlobals(string $voiceid): void
{
    $GLOBALS['PATCH_OVERRIDE_VOICE'] = $voiceid;
    unset($GLOBALS['PATCH_OVERRIDE_VOICE_ID']);

    $GLOBALS['TTS']['XTTSFASTAPI']['voiceid']  = $voiceid;
    $GLOBALS['TTS']['CHATTERBOX']['voiceid']   = $voiceid;
    $GLOBALS['TTS']['POCKETTTS']['voiceid']    = $voiceid;
    $GLOBALS['TTS']['OMNIVOICE']['voiceid']    = $voiceid;
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
 * Load The Narrator's voice settings into GLOBALS
 */
function loadNarratorVoiceSettings() {
    require_once(__DIR__ . "/core/narrator.class.php");
    require_once(__DIR__ . "/core/core_profiles.class.php");
    require_once(__DIR__ . "/core/tts_connector.class.php");

    $narrator = new Narrator();
    $profileId = $narrator->getProfileId();
    if ($profileId) {
        $profileManager = new CoreProfile();
        $profileData = $profileManager->getById($profileId);
        if (is_array($profileData) && !empty($profileData)) {
            $ttsConnectorId = intval($profileData['tts_connector_id'] ?? 0);
            if ($ttsConnectorId > 0) {
                $ttsConnector = new TTSConnector();
                $connectorData = $ttsConnector->getById($ttsConnectorId);
                if (is_array($connectorData) && !empty($connectorData)) {
                    $ttsConnector->setOldGlobals($connectorData);
                }
            }
        }
    }

    unset($GLOBALS['PATCH_OVERRIDE_TTS_LANGUAGE']);
    unset($GLOBALS['PATCH_OVERRIDE_TTS_OPTIONS']);

    $voiceid = $narrator->get('voiceid');

    if (!$voiceid) {
        $voiceid = 'TheNarrator'; // Fallback default
    }

    applyVoiceIdToTtsGlobals($voiceid);
}

/**
 * Restore previously saved voice settings
 * @param array $savedSettings The settings to restore
 */
function restoreVoiceSettings($savedSettings) {
    if (!is_array($savedSettings)) {
        return;
    }

    if (array_key_exists('tts', $savedSettings)) {
        $GLOBALS['TTS'] = $savedSettings['tts'];
    } elseif (!empty($savedSettings)) {
        // Backward compatibility with older callers that saved only the TTS array.
        $GLOBALS['TTS'] = $savedSettings;
    }

    if (!empty($savedSettings['has_tts_function'])) {
        $GLOBALS['TTSFUNCTION'] = $savedSettings['tts_function'];
    } else {
        unset($GLOBALS['TTSFUNCTION']);
    }

    if (!empty($savedSettings['has_tts_function_alias'])) {
        $GLOBALS['TTS_FUNCTION'] = $savedSettings['tts_function_alias'];
    } else {
        unset($GLOBALS['TTS_FUNCTION']);
    }

    if (!empty($savedSettings['has_current_tts_connector_id'])) {
        $GLOBALS['CHIM_CORE_CURRENT_TTS_CONNECTOR_ID'] = $savedSettings['current_tts_connector_id'];
    } else {
        unset($GLOBALS['CHIM_CORE_CURRENT_TTS_CONNECTOR_ID']);
    }

    if (!empty($savedSettings['has_patch_override_voice'])) {
        $GLOBALS['PATCH_OVERRIDE_VOICE'] = $savedSettings['patch_override_voice'];
    } else {
        unset($GLOBALS['PATCH_OVERRIDE_VOICE']);
    }

    if (!empty($savedSettings['has_patch_override_voice_id'])) {
        $GLOBALS['PATCH_OVERRIDE_VOICE_ID'] = $savedSettings['patch_override_voice_id'];
    } else {
        unset($GLOBALS['PATCH_OVERRIDE_VOICE_ID']);
    }

    if (!empty($savedSettings['has_patch_override_tts_language'])) {
        $GLOBALS['PATCH_OVERRIDE_TTS_LANGUAGE'] = $savedSettings['patch_override_tts_language'];
    } else {
        unset($GLOBALS['PATCH_OVERRIDE_TTS_LANGUAGE']);
    }

    if (!empty($savedSettings['has_patch_override_tts_options'])) {
        $GLOBALS['PATCH_OVERRIDE_TTS_OPTIONS'] = $savedSettings['patch_override_tts_options'];
    } else {
        unset($GLOBALS['PATCH_OVERRIDE_TTS_OPTIONS']);
    }
}

/**
 * Restore the original speaker after inline narration uses the narrator voice.
 *
 * Narrator-originated events can enter this path before their voice override is
 * loaded. Restoring that stale snapshot would replace the configured narrator
 * voice with the connector fallback for the dialogue that follows.
 */
function restoreInlineNarrationSpeakerVoiceSettings($savedSettings, $speakerName): void {
    $GLOBALS["HERIKA_NAME"] = $speakerName;

    if (strcasecmp(trim((string)$speakerName), "The Narrator") === 0) {
        return;
    }

    restoreVoiceSettings($savedSettings);
}


function unmoodSentence($sentence) {
    global $forceMood;

    $output = $sentence;
    $isPlayerSpeech = isset($GLOBALS["HERIKA_NAME"]) && strcasecmp((string)$GLOBALS["HERIKA_NAME"], "Player") === 0;

    if ($isPlayerSpeech) {
        $output = formatPlayerSpeechText($output);
    }

    // Determine whether to process asterisks:
    // This function prepares text for TTS/log text. Player speech uses its own toggle.
    $processAsterisks = true; // Default to stripping asterisks for TTS

    if (!$isPlayerSpeech) {
        $processAsterisks = shouldStripNpcOutputAsterisks();
        if (isset($GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'])) {
            error_log("[unmoodSentence] REMOVE_ASTERISKS_FROM_NPC_OUTPUT is setted to <{$GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT']}>" );
        } elseif (isset($GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT'])) {
            error_log("[unmoodSentence] REMOVE_ASTERISKS_FROM_OUTPUT is setted to <{$GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT']}>" );
        }
    }
    

    if (!isInlineNarrationEnabled()) {// Removes ALL text between asterisks.
        error_log("[unmoodSentence] Narration is disabled. Removing all asterisked content from output: $output");
        $output = preg_replace('/\*([^*]+)\*/', '', (string)$output);
    }
    
    if (!$isPlayerSpeech && $processAsterisks === true ) {
        error_log("[unmoodSentence] NPC output asterisk filtering is active! $sentence <" . ($GLOBALS['strip_emotes_from_output'] ?? 'N/A') . "> <" . ($GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'] ?? $GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT'] ?? 'N/A') . ">" );
        
        $output = formatNpcSpeechText($output);
    }

    else if (!$isPlayerSpeech) {
        error_log("[unmoodSentence] NPC output asterisk filtering is disabled; keeping asterisk content in speech");
        $output = formatNpcSpeechText($output);
    }

 

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

    $output = preg_replace('/\[mood: [^\]]*\]/i', '', $output);       // Removes "[mood: <text>]"

    // Remove quotes
    $output = preg_replace('/"/', '', $output);

    // Remove parenthesized content and trim the result
    $output = preg_replace('/\((.*?)\)/i', '', $output);
    $responseTextUnmooded = trim(preg_replace('/\s+/', ' ', $output));

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

    $inlineNarrationMode = getInlineNarrationMode();
    $inlineNarrationEnabled = $inlineNarrationMode !== 'disabled';
    $preserveAsterisksInContext = isset($GLOBALS["PRESERVE_ASTERISKS_IN_CONTEXT"]) ? (bool)$GLOBALS["PRESERVE_ASTERISKS_IN_CONTEXT"] : false;
    $chimQuestDialogueParts = array();

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
            continue;
        
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
        $inlineNarrationMode = getInlineNarrationMode();
        $inlineNarrationEnabled = $inlineNarrationMode !== 'disabled';
        $sentenceForSubtitles = $sentence; // Keep the original with narration

        // Strip trailing directed-dialogue tags from player speech for cleaner subtitles,
        // regardless of inline narration mode.
        $isPlayerSpeech = isset($GLOBALS["HERIKA_NAME"]) && strcasecmp((string)$GLOBALS["HERIKA_NAME"], "Player") === 0;
        if ($inlineNarrationEnabled || $isPlayerSpeech) {
            $sentence = preg_replace('/\s*\((?:(?:Talking|Whispering|Shouting)|Speaking privately)\s+to\s+[^)]+\)\s*$/i', '', $sentence);
            $sentenceForSubtitles = preg_replace('/\s*\((?:(?:Talking|Whispering|Shouting)|Speaking privately)\s+to\s+[^)]+\)\s*$/i', '', $sentenceForSubtitles);
        }

        // Check if we should split narration to The Narrator BEFORE unmoodSentence strips asterisks
        $splitNarration = false;
        $textOnlyNarration = false;
        $narrationParts = null;
        if ($inlineNarrationEnabled && !$isPlayerSpeech) {
            $narrationParts = extractNarrationAndDialogue($sentenceForSubtitles);
            $splitNarration = shouldSplitInlineNarration() && $narrationParts['has_narration'];
            $textOnlyNarration = $inlineNarrationMode === 'text_only' && $narrationParts['has_narration'];

            // Debug logging
            Logger::info("[INLINE_NARRATION] Mode: " . $inlineNarrationMode);
            Logger::info("[INLINE_NARRATION] Original sentence: " . $sentenceForSubtitles);
            Logger::info("[INLINE_NARRATION] Has narration: " . ($splitNarration ? 'yes' : 'no'));
            if ($splitNarration) {
                Logger::info("[INLINE_NARRATION] Narrations: " . json_encode($narrationParts['narrations']));
                Logger::info("[INLINE_NARRATION] Dialogue: " . $narrationParts['dialogue']);
            }
        } else {
            Logger::info("[INLINE_NARRATION] Disabled, not set, or skipped for player speech");
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
        $mood = extractFirstEmoteMood($mood, "default");


        if (strlen($responseTextUnmooded) < 2 && !($splitNarration && $narrationParts && !empty($narrationParts['narrations']))) { // Avoid too short responses
            continue;
        }


        if (strpos($responseTextUnmooded, "The Narrator:") !== false) { // Force not impersonating the narrator.
            continue;
        }

        $responseTextUnmooded = stripOutputSpeakerPrefix($responseTextUnmooded);	// Should not happen

        $responseText = $responseTextUnmooded;
        $responseForTTS = $responseTextUnmooded; // TTS gets the "unmooded" version (narration stripped)

        // Set up subtitles based on whether inline narration is enabled
        if ($isPlayerSpeech) {
            $responseForSubtitles = formatPlayerSubtitleText($sentenceForSubtitles);
        } elseif ($textOnlyNarration) {
            $responseForSubtitles = formatTextOnlyInlineNarrationSubtitleText($sentenceForSubtitles);
            if (strlen($responseForSubtitles) > _MAX_SUBTITLE_LENGTH) {
                $responseForSubtitles = substr($responseForSubtitles, 0, _MAX_SUBTITLE_LENGTH);
            }
        } elseif (!$splitNarration && $inlineNarrationEnabled) {
            $responseForSubtitles = formatNpcSubtitleText($sentenceForSubtitles);
            if (strlen($responseForSubtitles) > _MAX_SUBTITLE_LENGTH) {
                $responseForSubtitles = substr($responseForSubtitles, 0, _MAX_SUBTITLE_LENGTH);
            }
        } else {
            // If narration is disabled or will be split, use the same text as TTS (narration stripped)
            $responseForSubtitles = strlen($responseTextUnmooded) > _MAX_SUBTITLE_LENGTH ?
            substr($responseTextUnmooded, 0, _MAX_SUBTITLE_LENGTH) :
            $responseTextUnmooded;
        }

        $responseForContext = $responseTextUnmooded;
        if ($preserveAsterisksInContext) {
            $responseForContext = cleanupDisplayText($sentenceForSubtitles, $GLOBALS["HERIKA_NAME"] ?? null);
        }

        $ttsOutput = null;

        if (Translation::$response) {
            Translation::$sentences[$n] = unmoodSentence(Translation::$sentences[$n]);
            Translation::$sentences[$n] = stripOutputSpeakerPrefix(Translation::$sentences[$n]);

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
                $responseForContext = Translation::$sentences[$n];
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

        $hasNarrationBlocks = $splitNarration && $narrationParts && !empty($narrationParts['narrations']);
        $hasTextOnlyNarration = $textOnlyNarration && $narrationParts && !empty($narrationParts['narrations']);
        $shouldEmitNpcLine = false;

        if ($responseTextUnmooded || $hasNarrationBlocks || $hasTextOnlyNarration) {
            $shouldEmitNpcLine = true;

            if ($hasTextOnlyNarration) {
                $responseForSubtitles = formatTextOnlyInlineNarrationSubtitleText($sentenceForSubtitles);
                if (strlen($responseForSubtitles) > _MAX_SUBTITLE_LENGTH) {
                    $responseForSubtitles = substr($responseForSubtitles, 0, _MAX_SUBTITLE_LENGTH);
                }

                if (!empty($narrationParts['dialogue'])) {
                    $responseForTTS = formatTextOnlyInlineNarrationSpeechText($sentenceForSubtitles, $narrationParts);
                    $responseText = $responseForTTS;
                    $responseTextUnmooded = $responseForTTS;
                    if (!$preserveAsterisksInContext) {
                        $responseForContext = $responseForTTS;
                    }
                } else {
                    // Do not queue a speech line when there is no audio file to download.
                    $shouldEmitNpcLine = false;
                    $responseForTTS = "";
                    $responseText = "";
                    $responseTextUnmooded = "";
                }
            // Check if we need to split narration to The Narrator
            } elseif ($hasNarrationBlocks) {
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
                    $narrationForSubtitles = formatNarrationSubtitleText($narrationText);

                    Logger::info("[INLINE_NARRATION] Generating TTS with function: " . $GLOBALS["TTSFUNCTION"]);

                    // Generate TTS for narration using the configured TTS function
                    $narratorTtsOutput = callConfiguredTts($narrationForTTS, "default", $narrationForSubtitles);

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

                // Restore NPC settings, but keep the narrator settings that were
                // just loaded when the original speaker is already The Narrator.
                restoreInlineNarrationSpeakerVoiceSettings($savedVoiceSettings, $savedHerikaName);

                // Now generate TTS for the NPC's dialogue (if any)
                if (!empty($narrationParts['dialogue'])) {
                    $responseForTTS = unmoodSentence($narrationParts['dialogue']);
                    $responseForSubtitles = formatInlineNarrationDialogueSubtitleText($narrationParts['dialogue']);
                    // IMPORTANT: Also update the main response variables so the output buffer uses dialogue only
                    $responseText = $responseForTTS;
                    $responseTextUnmooded = $responseForTTS;
                    if (!$preserveAsterisksInContext) {
                        $responseForContext = $responseForTTS;
                    }
                } else {
                    // Narration-only line: narrator speech already emitted above, skip NPC speech output.
                    $shouldEmitNpcLine = false;
                    $responseForTTS = "";
                    $responseForSubtitles = "";
                    $responseText = "";
                    $responseTextUnmooded = "";
                }
            }

            if ($shouldEmitNpcLine && trim((string)$responseForTTS) !== "") {
                // Set TTS processing status
                pipeline_status_set('tts', true);

                // Generate regular TTS (either full text if no narration, or just dialogue after narration)
                $ttsOutput = callNpcTtsWithFallback($responseForTTS, $mood, $responseForSubtitles);
                if (!$ttsOutput) {
                    if (isset($GLOBALS["TTS_FALLBACK_FNCT"]))
                        $ttsOutput = $GLOBALS["TTS_FALLBACK_FNCT"]($responseForTTS, $mood, $responseForSubtitles);
                }

                // Clear TTS processing status
                pipeline_status_set('tts', false);

                if ($ttsOutput) {
                    $GLOBALS["TRACK"]["FILES_GENERATED"][] = $ttsOutput;
                }
                if (trim($responseText)) {
                    $talkedSoFar[] = $responseText;
                }
            }
        }

        if ($shouldEmitNpcLine) {
            $responseForContextCn = trim((string)$responseForContext);
            if ($responseForContextCn !== '') {
                $chimQuestDialogueParts[] = $responseForContextCn;
            }
            Logger::info("Speech sent for {$GLOBALS["HERIKA_NAME"]}, generator {$GLOBALS["TTSFUNCTION"]}, size: ".strlen($responseText). "  '".substr($responseText,0,10)."'");
        } else {
            Logger::info("[INLINE_NARRATION] No NPC dialogue line queued.");
        }
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
       

        if ($writeOutput && $shouldEmitNpcLine) {
            
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

                $strictRechatListener = chimGetStrictRechatListenerName();
                if ($strictRechatListener !== "") {
                    $GLOBALS["SCRIPTLINE_LISTENER"] = $strictRechatListener;
                }

                $strictDirectedPlayerListener = chimGetStrictDirectedPlayerListenerName();
                if ($strictDirectedPlayerListener !== "") {
                    $GLOBALS["SCRIPTLINE_LISTENER"] = $strictDirectedPlayerListener;
                }


                $listenerFix=explode(" and ",$GLOBALS["SCRIPTLINE_LISTENER"]);
                // Don't touch original one
                $GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"]=$GLOBALS["SCRIPTLINE_LISTENER"];
                $GLOBALS["SCRIPTLINE_RECHAT_TARGET"]=$GLOBALS["SCRIPTLINE_LISTENER"];

                if (is_array($listenerFix) && (sizeof($listenerFix)>1)) {
                    $GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"]=trim($listenerFix[0]);
                }
                
                $listenerFix2=parseDialogueListenerNames($GLOBALS["SCRIPTLINE_LISTENER"]);
                if (!is_array($listenerFix2)) {
                    $listenerFix2 = [];
                }
                $listenerFix2 = array_values(array_unique(array_filter(array_map('normalizeDialogueListenerName', $listenerFix2))));

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

                    $GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"]=normalizeDialogueListenerName($GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"]);
                    //$GLOBALS["SCRIPTLINE_LISTENER"]=trim($listenerFix2[ $GLOBALS["SCRIPTLINE_LISTENER_CYCLE"]]);
                    // $GLOBALS["SCRIPTLINE_LISTENER"] = trim($listenerFix2[array_rand($listenerFix2)]); // Random
                    

                }
                else {
                    $GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"] = normalizeDialogueListenerName($GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"]);
                }

                $speakerName = normalizeDialogueListenerName($outBuffer["actor"] ?? "");
                $normalizedNearby = getNormalizedNearbyDialogueListenerNames(true);
                $rechatCandidates = $listenerFix2;
                if (empty($rechatCandidates) && $GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"] !== "") {
                    $rechatCandidates[] = $GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"];
                }
                if (isOpenRechatEnabled()) {
                    $rechatCandidates = buildOpenRechatListenerCandidates($rechatCandidates, $speakerName);
                }

                $rechatTarget = "";
                foreach ($rechatCandidates as $candidateName) {
                    $candidateName = normalizeDialogueListenerName($candidateName);
                    if ($candidateName === "" || isPlayerDialogueListenerName($candidateName)) {
                        continue;
                    }
                    if ($speakerName !== "" && strcasecmp($candidateName, $speakerName) === 0) {
                        continue;
                    }
                    if (strcasecmp($candidateName, "The Narrator") === 0) {
                        continue;
                    }
                    if (in_array($candidateName, $normalizedNearby, true)) {
                        $rechatTarget = $candidateName;
                        break;
                    }
                }

                if ($rechatTarget === "" &&
                    !isPlayerDialogueListenerName($GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"]) &&
                    ($speakerName === "" || strcasecmp($GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"], $speakerName) !== 0)) {
                    $rechatTarget = $GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"];
                }

                if (!in_array($GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"], $normalizedNearby, true) &&
                    !isPlayerDialogueListenerName($GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"])) {
                    Logger::info("Atomic listener {$GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"]} not nearby; preserving playback target. Raw listener={$GLOBALS["SCRIPTLINE_LISTENER"]}");
                }

                if ($rechatTarget !== "") {
                    $GLOBALS["SCRIPTLINE_RECHAT_TARGET"] = $rechatTarget;
                } else {
                    $GLOBALS["SCRIPTLINE_RECHAT_TARGET"] = $GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"];
                }

                $currentUtteranceId = chimGenerateUtteranceId();
                $GLOBALS["SCRIPTLINE_UTTERANCE_ID"] = $currentUtteranceId;

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
                
                // We should check here is responseForSubtitles is different from responseTextUnmooded
                // If so, and no responseTextPhonetic, responseTextPhonetic should be responseTextUnmooded
                // 

                if (empty($responseTextPhonetic) && $responseForSubtitles !== $responseTextUnmooded) {
                    $responseTextPhonetic = $responseTextUnmooded;
                    Logger::debug("No phonetic conversion available; using unmooded text for phonetic output: $responseTextPhonetic");
                }

                $volumeBoost = 1.0;

                // Output here with volumeBoost appended
                echo "{$outBuffer["actor"]}|ScriptQueue|$responseForSubtitles/{$GLOBALS["SCRIPTLINE_EXPRESSION"]}/{$GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"]}/{$GLOBALS["SCRIPTLINE_ANIMATION"]}/$responseTextPhonetic/$volumeBoost/{$GLOBALS["SCRIPTLINE_RECHAT_TARGET"]}/{$currentUtteranceId}\r\n";

                
                $GLOBALS["DEBUG_DATA"]["OUTPUT_LOG"]="{$outBuffer["actor"]}|ScriptQueue|$responseForSubtitles/{$GLOBALS["SCRIPTLINE_EXPRESSION"]}/{$GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"]}/{$GLOBALS["SCRIPTLINE_ANIMATION"]}/$responseTextPhonetic/$volumeBoost/{$GLOBALS["SCRIPTLINE_RECHAT_TARGET"]}/{$currentUtteranceId}\r\n";
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
                    'response' => (SQLite3::escapeString($responseForContext)),
                    'url' => nl2br(SQLite3::escapeString("$receivedData [AI secs] $elapsedTimeAI  [TTS secs] $elapsedTimeTTS"))


                )
            );
            
            // CHAT LOGGING
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
                $incomingSpatialVolume = isset($GLOBALS["LAST_SPEECH_VOLUME"]) ? floatval($GLOBALS["LAST_SPEECH_VOLUME"]) : null;
                if (($incomingSpatialVolume !== null && $incomingSpatialVolume < 0.35) || $distance > SHOUTING_DISTANCE_THRESHOLD) {
                    $addonlistener = buildDialogueTargetSuffix($GLOBALS["SCRIPTLINE_LISTENER"], true);
                } else {
                    $addonlistener = buildDialogueTargetSuffix($GLOBALS["SCRIPTLINE_LISTENER"], false);
                }
            } else {
                $addonlistener="";
            }
            $originalRequest[3]="{$outBuffer["actor"]}: $responseForContext $addonlistener";
            $dialogueEventPeople = chimBuildDialogueEventPeoplePipe(
                chimGetCurrentTurnPeopleSnapshot(),
                $outBuffer["actor"] ?? "",
                $GLOBALS["SCRIPTLINE_LISTENER_ATOMIC"] ?? ""
            );
            logEvent($originalRequest, $dialogueEventPeople);
            
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
                    $addonlistener = buildDialogueTargetSuffix($GLOBALS["SCRIPTLINE_LISTENER"], true);
                } else {
                    $addonlistener = buildDialogueTargetSuffix($GLOBALS["SCRIPTLINE_LISTENER"], false);
                }
            } else {
                $addonlistener="";
            }
            $originalRequest[3]="{$outBuffer["actor"]}: $responseForContext $addonlistener";
            $originalRequest[5] = [
                'utterance_id' => $GLOBALS["SCRIPTLINE_UTTERANCE_ID"] ?? chimGenerateUtteranceId(),
                'delivery_state' => 'emitted'
            ];
            logEvent($originalRequest, $dialogueEventPeople);
        }
        
    }

    if (!empty($chimQuestDialogueParts) && function_exists('chimQuestEngineHandleLiveDialogueTurn')) {
        chimQuestEngineHandleLiveDialogueTurn(
            $GLOBALS["HERIKA_NAME"] ?? '',
            implode(' ', $chimQuestDialogueParts),
            $GLOBALS["gameRequest"] ?? array()
        );
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

    $visibleChatStateSql = chimBuildChatDeliveryStateSql('delivery_state');
    $query = "
        SELECT 
            (MAX(gamets) - MIN(gamets)) * 0.0000024 AS hour_threshold
        FROM (
            SELECT gamets 
            FROM eventlog 
            WHERE type='chat'
            AND {$visibleChatStateSql}
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



function chimMemorySearchInputFromRequest(array $gameRequest): string
{
    $rawInput = trim((string)($gameRequest[3] ?? ''));
    if (($gameRequest[0] ?? '') !== 'rechat') {
        return $rawInput;
    }

    $payload = chimParseServerSideRechatPayload($rawInput);
    return trim((string)($payload['origin_line'] ?? ''));
}

function offerMemory($gameRequest, $useLocationContext = false)
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
    $memorySearchInput = chimMemorySearchInputFromRequest($gameRequest);

    error_log("[DataSearchMemoryByVector] Using timeThreshold $timeThreshold");
    $contextKeywords  = implode(" ", lastKeyWordsContext(5,$npc));

    if ($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["USE_TEXT2VEC"]) {
        $localStartTime = microtime(true);
        error_log("[DataSearchMemoryByVector calling]  : " . (microtime(true) - $localStartTime) . " seconds");
        $res = DataSearchMemoryByVector($memorySearchInput, $npc, true,$timeThreshold);
        error_log("[DataSearchMemoryByVector called 1]  : " . (microtime(true) - $localStartTime) . " seconds");
        $res2 = DataSearchMemoryByVector($memorySearchInput, $npc,false,$timeThreshold);
        error_log("[DataSearchMemoryByVector called 2]  : " . (microtime(true) - $localStartTime) . " seconds");
        if ($useLocationContext) {
            $location=DataLastKnownLocationHuman();
            $res2 = DataSearchMemoryByVector("$memorySearchInput $location", $npc,false,$timeThreshold);
            error_log("[DataSearchMemoryByVector called 2]  : " . (microtime(true) - $localStartTime) . " seconds");
        }

        if (isset($res[0]) && isset($res2[0])) {
            $resFinal = ($res[0]['rank_any'] >= $res2[0]['rank_any']) ? $res : $res2;
        } else {
            $resFinal = isset($res[0]['rank_any']) ? $res : (isset($res2[0]['rank_any']) ? $res2 : []);
        }
        $memories = $resFinal;
        
    } else {
        $memories=DataSearchMemory($memorySearchInput,$npc);
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

function normalizePeoplePipeList($peopleNames)
{
    if (!is_array($peopleNames) || empty($peopleNames)) {
        return "";
    }

    $cleanPeople = [];
    foreach ($peopleNames as $name) {
        $name = trim((string)$name);
        $name = trim($name, "|");
        if ($name === "") {
            continue;
        }
        if (!shouldIncludeActorNameInPeopleList($name)) {
            continue;
        }
        if (!in_array($name, $cleanPeople, true)) {
            $cleanPeople[] = $name;
        }
    }

    if (empty($cleanPeople)) {
        return "";
    }

    return "|" . implode("|", $cleanPeople) . "|";
}

function parsePeoplePipeList($peoplePipe)
{
    $peoplePipe = trim((string)$peoplePipe);
    if ($peoplePipe === "") {
        return [];
    }

    $tokens = explode("|", $peoplePipe);
    $cleanPeople = [];
    foreach ($tokens as $token) {
        $token = trim((string)$token);
        if ($token === "") {
            continue;
        }
        if (!in_array($token, $cleanPeople, true)) {
            $cleanPeople[] = $token;
        }
    }

    return $cleanPeople;
}

function chimNormalizePresentActors($actors)
{
    if (!is_array($actors)) {
        return [];
    }

    $normalized = [];
    $seenNames = [];
    foreach ($actors as $actor) {
        if (is_string($actor)) {
            $actor = ["name" => $actor];
        }
        if (!is_array($actor)) {
            continue;
        }

        $name = trim((string)($actor["name"] ?? ""));
        $name = trim($name, "|");
        if ($name === "" || !shouldIncludeActorNameInPeopleList($name)) {
            continue;
        }

        $nameKey = mb_strtolower($name, "UTF-8");
        if (isset($seenNames[$nameKey])) {
            continue;
        }
        $seenNames[$nameKey] = true;

        $normalized[] = [
            "name" => $name,
            "form_id" => (int)($actor["form_id"] ?? 0),
            "managed" => !empty($actor["managed"]),
            "creature" => !empty($actor["creature"]),
            "distance" => (float)($actor["distance"] ?? 0.0),
        ];
        if (count($normalized) >= 32) {
            break;
        }
    }

    return $normalized;
}

// Parse a one-shot chat shortcut while leaving the saved CHIM mode unchanged.
function chimParseChatModeShortcut($message)
{
    $message = (string)$message;
    $rules = [
        ["prefix" => "((", "mode" => "INJECTION_LOG", "suffix" => "))"],
        ["prefix" => "||", "mode" => "CLOSE", "suffix" => ""],
        ["prefix" => "!!", "mode" => "SHOUT", "suffix" => ""],
        ["prefix" => "**", "mode" => "AUTOCHAT", "suffix" => ""],
        ["prefix" => "|", "mode" => "WHISPER", "suffix" => ""],
        ["prefix" => "@", "mode" => "NARRATOR", "suffix" => ""],
        ["prefix" => ">", "mode" => "DIRECTOR", "suffix" => ""],
        ["prefix" => "#", "mode" => "CHEATMODE", "suffix" => ""],
        ["prefix" => "(", "mode" => "INJECTION_CHAT", "suffix" => ")"],
    ];

    foreach ($rules as $rule) {
        if (!str_starts_with($message, $rule["prefix"])) {
            continue;
        }

        $content = trim(substr($message, strlen($rule["prefix"])));
        if ($rule["suffix"] !== "" && str_ends_with($content, $rule["suffix"])) {
            $content = trim(substr($content, 0, -strlen($rule["suffix"])));
        }

        return [
            "matched" => true,
            "mode" => $rule["mode"],
            "symbol" => $rule["prefix"],
            "content" => $content,
        ];
    }

    return [
        "matched" => false,
        "mode" => "",
        "symbol" => "",
        "content" => $message,
    ];
}

function chimDecodePlayerRoutingSnapshotField($rawField)
{
    $result = [
        "audience" => "",
        "present_actors" => [],
        "chat_shortcut_routed" => false,
        "player_mood" => "",
        "player_mood_custom" => "",
    ];
    $rawField = trim((string)$rawField);
    if ($rawField === "") {
        return $result;
    }

    $decoded = base64_decode($rawField, true);
    if ($decoded === false || $decoded === "") {
        return $result;
    }

    $payload = json_decode($decoded, true);
    if (!is_array($payload)) {
        return $result;
    }

    if (!empty($payload["people"]) && is_string($payload["people"])) {
        $result["audience"] = normalizePeoplePipeList(parsePeoplePipeList($payload["people"]));
    } elseif (!empty($payload["companions"]) && is_array($payload["companions"])) {
        $result["audience"] = normalizePeoplePipeList($payload["companions"]);
    }

    $result["present_actors"] = chimNormalizePresentActors($payload["present_actors"] ?? []);
    $result["chat_shortcut_routed"] =
        ($payload["source"] ?? "") === "plugin_player_routing_v2" &&
        ($payload["chat_shortcut_routed"] ?? false) === true;
    if (($payload["source"] ?? "") === "plugin_player_routing_v2") {
        $playerMood = chimNormalizePlayerMood($payload["player_mood"] ?? "");
        if ($playerMood !== "") {
            $result["player_mood"] = $playerMood;
            if ($playerMood === "custom") {
                $result["player_mood_custom"] = chimNormalizeCustomPlayerMood(
                    $payload["player_mood_custom"] ?? ""
                );
            }
        }
    }
    return $result;
}

// Normalize the supported Prisma mood enum shared by decoding and history formatting.
function chimNormalizePlayerMood($playerMood)
{
    $playerMood = strtolower(trim((string)$playerMood));
    return in_array($playerMood, [
        "happy",
        "sad",
        "angry",
        "annoyed",
        "scared",
        "surprised",
        "confused",
        "suspicious",
        "playful",
        "flirty",
        "custom",
    ], true)
        ? $playerMood
        : "";
}

// Append the resolved mood phrase to the player line that enters persistent dialogue history.
function chimAppendPlayerMoodToHistoryLine($playerDialogue, $moodPrompt)
{
    $playerDialogue = (string)$playerDialogue;
    $moodPrompt = trim((string)$moodPrompt);
    if ($playerDialogue === "" || $moodPrompt === "") {
        return $playerDialogue;
    }
    $playerDialogue = rtrim($playerDialogue);
    if ($playerDialogue === "") {
        return "";
    }
    return "{$playerDialogue} {$moodPrompt}";
}

// Keep player playback separate from routing and mood cues added only for persistent history.
function chimResolvePlayerTtsSourceText($fallbackDialogue)
{
    if (array_key_exists("PLAYER_TTS_SOURCE_TEXT", $GLOBALS)) {
        return (string)$GLOBALS["PLAYER_TTS_SOURCE_TEXT"];
    }
    return (string)$fallbackDialogue;
}

// Keep custom delivery directions short and single-line before prompt insertion.
function chimNormalizeCustomPlayerMood($customMood)
{
    if (!is_string($customMood)) {
        return "";
    }

    $customMood = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string)$customMood);
    if (!is_string($customMood)) {
        return "";
    }
    $customMood = preg_replace('/\s+/u', ' ', trim($customMood));
    if (!is_string($customMood) || $customMood === "") {
        return "";
    }

    if (function_exists('mb_substr')) {
        return mb_substr($customMood, 0, 80, 'UTF-8');
    }
    if (preg_match_all('/./us', $customMood, $characters) === false) {
        return "";
    }
    return implode('', array_slice($characters[0], 0, 80));
}

function chimDecodeAudienceSnapshotField($rawField)
{
    $snapshot = chimDecodePlayerRoutingSnapshotField($rawField);
    return (string)($snapshot["audience"] ?? "");
}

function chimDecodePresentActorsSnapshotField($rawField)
{
    $snapshot = chimDecodePlayerRoutingSnapshotField($rawField);
    return chimNormalizePresentActors($snapshot["present_actors"] ?? []);
}

function chimPresentActorsPeoplePipe($actors)
{
    $names = [];
    foreach (chimNormalizePresentActors($actors) as $actor) {
        $names[] = $actor["name"];
    }
    return normalizePeoplePipeList($names);
}

function chimMergePeoplePipeLists()
{
    $names = [];
    foreach (func_get_args() as $peoplePipe) {
        foreach (parsePeoplePipeList($peoplePipe) as $name) {
            $names[] = $name;
        }
    }
    return normalizePeoplePipeList($names);
}

function chimBuildDirectivePeoplePipe($nearbyPeople, $speakerName, $instructionText)
{
    $speakerPeople = normalizePeoplePipeList([(string)$speakerName]);
    $listenerPeople = "";
    $listenerName = "";

    if (preg_match(
        '/\bThe dialogue listener must be\s+([^\r\n.]+)\.(?:\s|$)/iu',
        (string)$instructionText,
        $matches
    )) {
        $listenerName = html_entity_decode(
            trim((string)($matches[1] ?? "")),
            ENT_QUOTES | ENT_HTML5,
            "UTF-8"
        );
    } elseif (preg_match(
        '/\(must use ACTION\s+(?:JustTalk|Talk)\s+([^)]+)\)/iu',
        (string)$instructionText,
        $matches
    )) {
        $listenerName = html_entity_decode(
            trim((string)($matches[1] ?? "")),
            ENT_QUOTES | ENT_HTML5,
            "UTF-8"
        );
    }

    if ($listenerName !== "" && strcasecmp($listenerName, "everyone") !== 0) {
        $listenerPeople = normalizePeoplePipeList([$listenerName]);
    }

    return chimMergePeoplePipeLists($nearbyPeople, $speakerPeople, $listenerPeople);
}

function chimBuildDialogueEventPeoplePipe($turnPeople, $speakerName, $listenerName)
{
    return chimMergePeoplePipeLists(
        $turnPeople,
        normalizePeoplePipeList([(string)$speakerName, (string)$listenerName])
    );
}

function chimSetCurrentTurnPresentActorsSnapshot($actors)
{
    $normalized = chimNormalizePresentActors($actors);
    if (empty($normalized)) {
        unset($GLOBALS["CHIM_TURN_PRESENT_ACTORS_SNAPSHOT"]);
        return [];
    }

    $GLOBALS["CHIM_TURN_PRESENT_ACTORS_SNAPSHOT"] = $normalized;
    return $normalized;
}

function chimGetCurrentTurnPresentActorsSnapshot()
{
    return chimNormalizePresentActors($GLOBALS["CHIM_TURN_PRESENT_ACTORS_SNAPSHOT"] ?? []);
}

function chimBuildCurrentTurnPresentPeoplePrompt()
{
    $actors = chimGetCurrentTurnPresentActorsSnapshot();
    if (empty($actors)) {
        return "";
    }

    $lines = [];
    foreach ($actors as $actor) {
        $name = htmlspecialchars($actor["name"], ENT_QUOTES | ENT_XML1, "UTF-8");
        $formId = (int)($actor["form_id"] ?? 0);
        if ($formId > 0) {
            $name .= " [RefID: " . strtoupper(str_pad(dechex($formId), 8, "0", STR_PAD_LEFT)) . "]";
        }
        if (empty($actor["managed"])) {
            $name .= " (present, not CHIM-active)";
        }
        $lines[] = "## " . $name;
    }

    return "<people_present>\n"
        . "# Physically present actors. Entries marked not CHIM-active cannot respond, but may be targeted by gameplay actions. Prefer the displayed RefID when selecting an actor target.\n"
        . implode("\n", $lines)
        . "\n</people_present>";
}

function chimSetCurrentTurnPeopleSnapshot($peoplePipe)
{
    $normalized = normalizePeoplePipeList(parsePeoplePipeList($peoplePipe));
    if ($normalized === "") {
        unset($GLOBALS["CHIM_TURN_PEOPLE_SNAPSHOT"]);
        return "";
    }

    $GLOBALS["CHIM_TURN_PEOPLE_SNAPSHOT"] = $normalized;
    return $normalized;
}

function chimGetCurrentTurnPeopleSnapshot()
{
    if (!isset($GLOBALS["CHIM_TURN_PEOPLE_SNAPSHOT"])) {
        return "";
    }

    return normalizePeoplePipeList(parsePeoplePipeList($GLOBALS["CHIM_TURN_PEOPLE_SNAPSHOT"]));
}

function normalizeDialogTextForComparison($text)
{
    $text = trim((string)$text);
    if ($text === "") {
        return "";
    }

    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim((string)$text);
    if ($text === "") {
        return "";
    }

    return mb_strtolower($text, 'UTF-8');
}

function chimActorStatusSuffixPattern()
{
    return '/\s*\((?:busy|hostile|in combat|far away|too far away|restrained|dead|disabled|unavailable|audible|narrator|checking(?: hearing|: [^)]+)?|can hear you(?:, muffled|: [^)]+)?|can[\'"]?t hear you(?: clearly)?(?:: [^)]+)?|no (?:target|crosshair target))\)\s*$/iu';
}

function stripActorStateSuffix($name)
{
    $name = trim((string)$name);
    if ($name === "") {
        return "";
    }

    $name = trim($name, "|");
    $name = preg_replace(chimActorStatusSuffixPattern(), '', $name);
    return trim((string)$name);
}

function normalizeActorNameForComparison($name)
{
    $name = stripActorStateSuffix($name);
    if ($name === "") {
        return "";
    }

    return strtolower($name);
}

function isSystemContextLabel($name)
{
    $name = trim((string)$name);
    if ($name === "") {
        return true;
    }

    $nameLower = mb_strtolower($name, 'UTF-8');
    if ($nameLower[0] === '(' || $nameLower[0] === '[') {
        return true;
    }

    if (strpos($nameLower, "context ") === 0 || strpos($nameLower, "(context ") === 0) {
        return true;
    }

    return false;
}

function isLikelyGenericAudienceLabel($name)
{
    $normalized = normalizeActorNameForComparison($name);
    $normalized = preg_replace('/\s+/u', ' ', $normalized);
    $normalized = trim((string)$normalized);
    if ($normalized === "") {
        return true;
    }

    $blockedExact = [
        "everyone",
        "people",
        "the people",
        "local people",
        "nearby people",
        "locals",
        "the locals",
        "patrons",
        "local patrons",
        "the patrons",
        "crowd",
        "the crowd",
        "audience",
        "the audience",
        "bystanders",
        "onlookers",
        "townsfolk",
        "npcs",
        "nearby npcs",
        "nearby npc",
        "other patrons",
        "other people",
    ];
    if (in_array($normalized, $blockedExact, true)) {
        return true;
    }

    if (preg_match('/^(?:all\s+)?(?:local|nearby|other|the)?\s*(?:patrons?|people|locals?|npcs?|crowd|audience|bystanders?|onlookers?|townsfolk)$/u', $normalized)) {
        return true;
    }

    return false;
}

function shouldIncludeActorNameInPeopleList($name)
{
    $name = trim((string)$name);
    if ($name === "") {
        return false;
    }

    if (isSystemContextLabel($name)) {
        return false;
    }

    if (isLikelyGenericAudienceLabel($name)) {
        return false;
    }

    return true;
}

function isWhisperExecutionMode()
{
    $mode = isset($GLOBALS["CHIM_EXECUTION_MODE"]) ? strtoupper(trim((string)$GLOBALS["CHIM_EXECUTION_MODE"])) : "";
    return ($mode === "WHISPER");
}

function isCloseExecutionMode()
{
    $mode = isset($GLOBALS["CHIM_EXECUTION_MODE"]) ? strtoupper(trim((string)$GLOBALS["CHIM_EXECUTION_MODE"])) : "";
    return ($mode === "CLOSE");
}

// Close permits audience-scoped NPC replies, but still excludes random Narrator interjections.
function chimExecutionModeAllowsRechatEvent(string $mode, string $eventType): bool
{
    $mode = strtoupper(trim($mode));
    return in_array($eventType, ['rechat', 'narration'], true)
        && $mode !== 'WHISPER'
        && !($mode === 'CLOSE' && $eventType === 'narration');
}

function isPrivateConversationExecutionMode()
{
    return isWhisperExecutionMode() || isCloseExecutionMode();
}

function buildPrivateConversationPeople($listenerName = "")
{
    $participants = [];

    if (!empty($GLOBALS["PLAYER_NAME"])) {
        appendUniqueActorName($participants, $GLOBALS["PLAYER_NAME"]);
    }

    $listenerName = trim((string)$listenerName);
    if ($listenerName !== "") {
        appendUniqueActorName($participants, $listenerName);
    }

    return normalizePeoplePipeList($participants);
}

function buildWhisperPrivatePeople($listenerName = "")
{
    return buildPrivateConversationPeople($listenerName);
}

function buildDialogueTargetSuffix($listenerName, $isSpeakingLoudly = false)
{
    $listenerName = trim((string)$listenerName);
    if ($listenerName === "") {
        return "";
    }

    $listenerNames = parseDialogueListenerNames($listenerName);
    if (!empty($listenerNames)) {
        $listenerName = trim((string)$listenerNames[0]);
    }

    if ($listenerName === "") {
        return "";
    }

    if ($isSpeakingLoudly) {
        return "(speaking loudly to {$listenerName} from far away)";
    }

    if (isCloseExecutionMode()) {
        return "(speaking privately to {$listenerName})";
    }

    if (isWhisperExecutionMode()) {
        return "(whispering to {$listenerName})";
    }

    return "(talking to {$listenerName})";
}

function parseDialogueListenerNames($listenerName)
{
    $listenerName = trim((string)$listenerName);
    if ($listenerName === "") {
        return [];
    }

    $parts = preg_split('/\s*(?:,|&|\band\b)\s*/iu', $listenerName);
    if (!is_array($parts)) {
        return [$listenerName];
    }

    $normalized = [];
    foreach ($parts as $part) {
        $part = trim((string)$part);
        if ($part !== "") {
            $normalized[] = $part;
        }
    }

    return array_values(array_unique($normalized));
}

function chimGetRechatMode()
{
    $mode = strtolower(trim((string)($GLOBALS["RECHAT_MODE"] ?? "")));
    if (in_array($mode, ["tight", "conversational", "group", "random"], true)) {
        return $mode;
    }

    if (array_key_exists("OPEN_RECHAT", $GLOBALS)) {
        return !empty($GLOBALS["OPEN_RECHAT"]) ? "conversational" : "tight";
    }

    return "random";
}

function isOpenRechatEnabled()
{
    return chimGetRechatMode() !== "tight";
}

function chimBuildRechatModeSessionKey(array $members)
{
    $members = chimNormalizeRechatActorList($members);
    sort($members, SORT_NATURAL | SORT_FLAG_CASE);

    if (empty($members)) {
        $members[] = trim((string)($GLOBALS["HERIKA_NAME"] ?? "rechat"));
    }

    return md5(implode("|", $members) . "_" . floor(time() / 120));
}

function chimResolveEffectiveRechatMode($configuredMode, array $members)
{
    $configuredMode = strtolower(trim((string)$configuredMode));
    if ($configuredMode !== "random") {
        return $configuredMode;
    }

    $sessionKey = chimBuildRechatModeSessionKey($members);
    $modeFile = sys_get_temp_dir() . "/chim_rechat_mode_" . $sessionKey . ".json";

    if (file_exists($modeFile)) {
        $data = json_decode(file_get_contents($modeFile), true);
        $storedMode = strtolower(trim((string)($data["mode"] ?? "")));
        if (in_array($storedMode, ["tight", "conversational", "group"], true)) {
            return $storedMode;
        }
    }

    $randomModes = ["tight", "conversational", "group"];
    $rolledMode = $randomModes[array_rand($randomModes)];
    @file_put_contents($modeFile, json_encode([
        "mode" => $rolledMode,
        "configured_mode" => "random",
        "ts" => time(),
    ]));

    return $rolledMode;
}

function normalizeDialogueListenerName($listenerName)
{
    $listenerName = stripActorStateSuffix($listenerName);
    if ($listenerName === "") {
        return "";
    }

    if (strcasecmp($listenerName, "Dragonborn") === 0 && !empty($GLOBALS["PLAYER_NAME"])) {
        return trim((string)$GLOBALS["PLAYER_NAME"]);
    }

    if (function_exists('chimNormalizeNarratorRoleplayActorName')) {
        return chimNormalizeNarratorRoleplayActorName($listenerName);
    }

    return $listenerName;
}

function isPlayerDialogueListenerName($listenerName)
{
    $listenerName = normalizeDialogueListenerName($listenerName);
    if ($listenerName === "") {
        return false;
    }

    $playerName = isset($GLOBALS["PLAYER_NAME"]) ? trim((string)$GLOBALS["PLAYER_NAME"]) : "";
    if ($playerName !== "" && strcasecmp($listenerName, $playerName) === 0) {
        return true;
    }

    return in_array(strtolower($listenerName), ["player", "me"], true);
}

function chimGetStrictRechatListenerName()
{
    if (empty($GLOBALS["ENFORCE_STRICT_RECHAT_RESPONSE"])) {
        return "";
    }

    if (
        !isset($GLOBALS["gameRequest"]) ||
        !is_array($GLOBALS["gameRequest"]) ||
        !in_array(($GLOBALS["gameRequest"][0] ?? ""), ["rechat", "continue", "continue_group"], true)
    ) {
        return "";
    }

    $listenerName = normalizeDialogueListenerName($GLOBALS["RECHAT_PREVIOUS_SPEAKER"] ?? "");
    if ($listenerName === "") {
        return "";
    }

    return $listenerName;
}

function chimGetStrictDirectedPlayerListenerName()
{
    if (empty($GLOBALS["ENFORCE_STRICT_RECHAT_RESPONSE"])) {
        return "";
    }

    if (!isset($GLOBALS["gameRequest"]) || !is_array($GLOBALS["gameRequest"])) {
        return "";
    }

    if (!in_array(($GLOBALS["gameRequest"][0] ?? ""), ["inputtext", "inputtext_s", "ginputtext", "ginputtext_s"], true)) {
        return "";
    }

    if (!function_exists('chimIsStrictDirectedPlayerResponseContext') || !chimIsStrictDirectedPlayerResponseContext()) {
        return "";
    }

    return normalizeDialogueListenerName($GLOBALS["PLAYER_NAME"] ?? "");
}

function getNormalizedNearbyDialogueListenerNames($excludeFarAway=true)
{
    $npcList = DataBeingsInCloseRange($excludeFarAway);
    if (!is_string($npcList) || $npcList === "") {
        return [];
    }

    $npcs = explode("|", $npcList);
    $normalizedNearby = [];
    foreach ($npcs as $nearbyNpcName) {
        $nearbyNpcName = normalizeDialogueListenerName($nearbyNpcName);
        if ($nearbyNpcName !== "") {
            $normalizedNearby[] = $nearbyNpcName;
        }
    }

    return array_values(array_unique($normalizedNearby));
}

function buildOpenRechatListenerCandidates(array $listenerNames, $speakerName)
{
    $speakerName = normalizeDialogueListenerName($speakerName);
    $candidates = [];

    foreach ($listenerNames as $listenerName) {
        $listenerName = normalizeDialogueListenerName($listenerName);
        if ($listenerName !== "") {
            $candidates[] = $listenerName;
        }
    }

    foreach (getNormalizedNearbyDialogueListenerNames(true) as $nearbyNpcName) {
        if ($nearbyNpcName === "") {
            continue;
        }
        if ($speakerName !== "" && strcasecmp($nearbyNpcName, $speakerName) === 0) {
            continue;
        }
        if (strcasecmp($nearbyNpcName, "The Narrator") === 0) {
            continue;
        }
        $candidates[] = $nearbyNpcName;
    }

    return array_values(array_unique($candidates));
}

function chimNormalizeRechatActorList(array $names)
{
    $normalized = [];
    foreach ($names as $name) {
        $name = normalizeDialogueListenerName($name);
        if ($name !== "") {
            $normalized[] = $name;
        }
    }

    return array_values(array_unique($normalized));
}

function chimExtractPeopleListFromPipeString($peoplePipe)
{
    $peoplePipe = trim((string)$peoplePipe);
    if ($peoplePipe === "") {
        return [];
    }

    $parts = explode("|", $peoplePipe);
    $names = [];
    foreach ($parts as $part) {
        $part = stripActorStateSuffix($part);
        if ($part !== "") {
            $names[] = $part;
        }
    }

    return chimNormalizeRechatActorList($names);
}

function chimParseServerSideRechatPayload($rawData)
{
    $payload = [
        "speaker" => trim((string)($GLOBALS["HERIKA_NAME"] ?? "")),
        "listener_hint" => "",
        "rechat_target_hint" => "",
        "origin_line" => trim((string)$rawData),
        "rechat_depth" => 0,
        "chain_id" => "",
    ];

    $rawData = trim((string)$rawData);
    if ($rawData === "" || $rawData[0] !== "{") {
        return $payload;
    }

    $decoded = json_decode($rawData, true);
    if (!is_array($decoded)) {
        return $payload;
    }

    if (!empty($decoded["speaker"])) {
        $payload["speaker"] = normalizeDialogueListenerName($decoded["speaker"]);
    }
    if (!empty($decoded["listener_hint"])) {
        $payload["listener_hint"] = normalizeDialogueListenerName($decoded["listener_hint"]);
    }
    if (!empty($decoded["rechat_target_hint"])) {
        $payload["rechat_target_hint"] = normalizeDialogueListenerName($decoded["rechat_target_hint"]);
    }
    if (!empty($decoded["origin_line"])) {
        $payload["origin_line"] = trim((string)$decoded["origin_line"]);
    }
    if (!empty($decoded["rechat_depth"])) {
        $payload["rechat_depth"] = max(0, intval($decoded["rechat_depth"]));
    }
    if (!empty($decoded["chain_id"])) {
        $payload["chain_id"] = trim((string)$decoded["chain_id"]);
    }

    return $payload;
}

// Build a fresh rechat-only state map from the latest plugin actor scan.
function chimLatestRechatActorStateMap($maxAgeSeconds = 45)
{
    $db = $GLOBALS["db"] ?? null;
    if (!$db || !method_exists($db, "fetchOne")) {
        return [];
    }

    $maxAgeSeconds = max(1, intval($maxAgeSeconds));
    $cutoff = time() - $maxAgeSeconds;
    $row = $db->fetchOne(
        "SELECT data, localts
         FROM eventlog
         WHERE type='infonpc' AND localts > {$cutoff}
         ORDER BY rowid DESC
         LIMIT 1"
    );
    if (!is_array($row)) {
        return [];
    }

    $eventLocalTs = intval($row["localts"] ?? 0);
    $ageSeconds = $eventLocalTs > 0 ? max(0, time() - $eventLocalTs) : PHP_INT_MAX;
    if ($ageSeconds > $maxAgeSeconds) {
        return [];
    }

    $actorList = trim((string)($row["data"] ?? ""));
    $actorList = preg_replace('/^\s*\(beings in range:/iu', '', $actorList);
    $actorList = preg_replace('/\)\s*$/u', '', (string)$actorList);
    if (trim((string)$actorList) === "") {
        return [];
    }

    $stateMap = [];
    foreach (explode(",", (string)$actorList) as $actorToken) {
        $actorToken = trim((string)$actorToken);
        if (!preg_match('/^(.*?)\s*\((busy|sleeping|unconscious)\)\s*$/iu', $actorToken, $matches)) {
            continue;
        }

        $actorName = normalizeDialogueListenerName($matches[1]);
        if ($actorName === "") {
            continue;
        }

        $stateMap[mb_strtolower($actorName, "UTF-8")] = strtolower((string)$matches[2]);
    }

    return $stateMap;
}

function chimRechatActorStateBlockReason($actorName, array $stateMap, $directlyAddressed = false)
{
    $actorName = normalizeDialogueListenerName($actorName);
    if ($actorName === "") {
        return "";
    }

    $state = $stateMap[mb_strtolower($actorName, "UTF-8")] ?? "";
    if ($state === "busy" || $state === "unconscious") {
        return $state;
    }
    if ($state === "sleeping" && !$directlyAddressed) {
        return $state;
    }

    return "";
}

function chimResolveServerSideRechatTarget(array $payload)
{
    $speakerName = normalizeDialogueListenerName($payload["speaker"] ?? ($GLOBALS["HERIKA_NAME"] ?? ""));
    $listenerHint = normalizeDialogueListenerName($payload["listener_hint"] ?? "");
    $rechatTargetHint = normalizeDialogueListenerName($payload["rechat_target_hint"] ?? "");
    $configuredRechatMode = chimGetRechatMode();

    $peoplePipe = "";
    foreach ([$rechatTargetHint, $listenerHint] as $scopeTarget) {
        if ($scopeTarget === "") {
            continue;
        }
        $peoplePipe = lookupConversationPeopleSourceOfTruth($speakerName, $scopeTarget);
        if ($peoplePipe !== "") {
            break;
        }
    }
    $audience = chimExtractPeopleListFromPipeString($peoplePipe);

    $rechatMode = chimResolveEffectiveRechatMode($configuredRechatMode, array_merge(
        [$speakerName, $listenerHint, $rechatTargetHint],
        $audience
    ));

    $candidates = [];
    $addCandidate = function ($candidateName) use (&$candidates, $audience) {
        $candidateName = normalizeDialogueListenerName($candidateName);
        if ($candidateName === "") {
            return;
        }
        foreach ($audience as $audienceName) {
            if (strcasecmp($candidateName, $audienceName) === 0) {
                $candidates[] = $candidateName;
                return;
            }
        }
    };

    if ($rechatMode === "tight") {
        $addCandidate($listenerHint);
    } elseif ($rechatMode === "conversational") {
        $addCandidate($rechatTargetHint);
        $addCandidate($listenerHint);
        foreach ($audience as $audienceName) {
            $addCandidate($audienceName);
        }
    } else {
        foreach ($audience as $audienceName) {
            if ($rechatTargetHint !== "" && strcasecmp($audienceName, $rechatTargetHint) === 0) {
                continue;
            }
            if ($listenerHint !== "" && strcasecmp($audienceName, $listenerHint) === 0) {
                continue;
            }
            $addCandidate($audienceName);
        }
        $addCandidate($rechatTargetHint);
        $addCandidate($listenerHint);
        foreach ($audience as $audienceName) {
            $addCandidate($audienceName);
        }
    }

    $candidates = chimNormalizeRechatActorList($candidates);
    $npcMaster = new NpcMaster();
    $selected = "";
    $actorStateMap = chimLatestRechatActorStateMap();
    $speakerBlockReason = chimRechatActorStateBlockReason($speakerName, $actorStateMap, false);

    if ($speakerBlockReason !== "") {
        Logger::info("[RECHAT_SELECT] Terminating rechat for {$speakerName}: {$speakerBlockReason}");
    }

    foreach ($speakerBlockReason === "" ? $candidates : [] as $candidate) {
        if ($candidate === "") {
            continue;
        }
        if ($speakerName !== "" && strcasecmp($candidate, $speakerName) === 0) {
            continue;
        }
        if (strcasecmp($candidate, "The Narrator") === 0) {
            continue;
        }
        if (isPlayerDialogueListenerName($candidate)) {
            continue;
        }
        if (!$npcMaster->getByName($candidate)) {
            continue;
        }

        $directlyAddressed = (
            ($rechatTargetHint !== "" && strcasecmp($candidate, $rechatTargetHint) === 0) ||
            ($listenerHint !== "" && strcasecmp($candidate, $listenerHint) === 0)
        );
        $candidateBlockReason = chimRechatActorStateBlockReason(
            $candidate,
            $actorStateMap,
            $directlyAddressed
        );
        if ($candidateBlockReason !== "") {
            Logger::info("[RECHAT_SELECT] Skipping {$candidate}: {$candidateBlockReason}");
            continue;
        }

        $selected = $candidate;
        break;
    }

    return [
        "speaker" => $speakerName,
        "listener_hint" => $listenerHint,
        "rechat_target_hint" => $rechatTargetHint,
        "audience" => $audience,
        "people_pipe" => $peoplePipe,
        "candidates" => $candidates,
        "selected" => $selected,
        "mode" => $rechatMode,
        "configured_mode" => $configuredRechatMode,
        "origin_line" => trim((string)($payload["origin_line"] ?? "")),
        "chain_id" => trim((string)($payload["chain_id"] ?? "")),
    ];
}

function chimBuildServerSideRechatSessionKey(array $resolvedTarget)
{
    $chainId = trim((string)($resolvedTarget["chain_id"] ?? ""));
    if ($chainId !== "") {
        return md5("chain_" . $chainId);
    }

    $members = array_merge(
        [$resolvedTarget["speaker"] ?? "", $resolvedTarget["listener_hint"] ?? "", $resolvedTarget["rechat_target_hint"] ?? ""],
        $resolvedTarget["audience"] ?? []
    );

    $members = chimNormalizeRechatActorList($members);
    sort($members, SORT_NATURAL | SORT_FLAG_CASE);

    if (empty($members)) {
        $members[] = trim((string)($resolvedTarget["speaker"] ?? $GLOBALS["HERIKA_NAME"] ?? "rechat"));
    }

    return md5("members_" . implode("|", $members));
}

function chimSwitchActiveNpcProfile($npcName)
{
    $npcName = normalizeDialogueListenerName($npcName);
    if ($npcName === "" || strcasecmp($npcName, "The Narrator") === 0) {
        return false;
    }

    $npcMaster = new NpcMaster();
    $currentNpcData = $npcMaster->getByName($npcName);
    if (!$currentNpcData) {
        Logger::warn("[RECHAT_SELECT] Could not load NPC profile for {$npcName}");
        return false;
    }

    $profile = new CoreProfile();
    if (empty($currentNpcData["profile_id"])) {
        $defaultProfile = $profile->getDefaultNpc();
        if ($defaultProfile) {
            $currentNpcData["profile_id"] = intval($defaultProfile["id"]);
            $npcMaster->updateByArray($currentNpcData);
        }
    }

    if (empty($currentNpcData["profile_id"])) {
        Logger::warn("[RECHAT_SELECT] NPC {$npcName} has no profile_id and no default fallback");
        return false;
    }

    $currentProfileData = $profile->getById(intval($currentNpcData["profile_id"]));
    if (!$currentProfileData) {
        Logger::warn("[RECHAT_SELECT] Could not load profile slot {$currentNpcData["profile_id"]} for {$npcName}");
        return false;
    }

    if (!class_exists("LLMRandomizer")) {
        require_once(__DIR__ . "/llm_randomizer.php");
    }

    $connector = new LLMConnector();
    $connectorSlot = null;
    $connectorId = 0;
    if (class_exists("LLMRandomizer")) {
        $connectorSlot = LLMRandomizer::getConnectorSlot($currentProfileData, $currentNpcData, $npcMaster);
        $connectorId = intval(LLMRandomizer::getConnectorIdForSlot($currentProfileData, $connectorSlot));
    }
    if ($connectorId <= 0 && !empty($currentProfileData["connector_id"])) {
        $connectorId = intval($currentProfileData["connector_id"]);
    }

    $currentConnectorData = ($connectorId > 0) ? $connector->getById($connectorId) : null;
    if (!$currentConnectorData) {
        $slotLabel = ($connectorSlot !== null) ? " slot {$connectorSlot}" : "";
        Logger::warn("[RECHAT_SELECT] Could not load connector id {$connectorId}{$slotLabel} for {$npcName}");
        return false;
    }

    $connector->setOldGlobals($currentConnectorData);
    $profile->setOldGlobals($currentProfileData);
    $npcMaster->setOldGlobalsFromCurrentNpcData($currentNpcData);

    $GLOBALS["CHIM_CORE_CURRENT_NPC_DATA"] = $currentNpcData;
    $GLOBALS["STOBE_CORE_CURRENT_NPC_DATA"] = $currentNpcData;
    $GLOBALS["CHIM_CORE_CURRENT_PROFILE_DATA"] = $currentProfileData;
    $GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"] = $currentConnectorData;
    $GLOBALS["active_profile"] = md5($npcName);
    $_GET["profile"] = md5($npcName);

    $partyConf = isset($GLOBALS["CACHE_PARTY"]) ? $GLOBALS["CACHE_PARTY"] : DataGetCurrentPartyConf();
    $GLOBALS["CACHE_PARTY"] = $partyConf;
    $currentParty = json_decode($partyConf, true);
    $GLOBALS["IS_NPC"] = !(is_array($currentParty) && in_array($npcName, array_keys($currentParty), true));

    return true;
}

function convertDirectedDialogueTagsToVerb($eventData, $verb)
{
    $eventData = (string)$eventData;
    if ($eventData === "") {
        return $eventData;
    }

    return preg_replace_callback(
        '/\(\s*([Tt]alking|[Ww]hispering|[Ss]houting|[Ss]peaking\s+privately)\s+to\s+([^()]+?)\s*\)/u',
        static function ($matches) use ($verb) {
            $prefix = ctype_upper(substr((string)$matches[1], 0, 1)) ? $verb : strtolower($verb);
            $target = trim((string)$matches[2]);
            return "({$prefix} to {$target})";
        },
        $eventData
    );
}

function convertTalkingTagsToWhispering($eventData)
{
    return convertDirectedDialogueTagsToVerb($eventData, 'Whispering');
}

function convertTalkingTagsToPrivately($eventData)
{
    return convertDirectedDialogueTagsToVerb($eventData, 'Speaking privately');
}

function convertTalkingTagsToShouting($eventData)
{
    return convertDirectedDialogueTagsToVerb($eventData, 'Shouting');
}

function extractTalkTargetMetadata($eventData)
{
    $metadata = [
        "hasExplicitTarget" => false,
        "isBroadcast" => false,
        "targets" => [],
    ];

    $eventData = (string)$eventData;
    if ($eventData === "") {
        return $metadata;
    }

    if (!preg_match('/\(\s*(?:(?:talking|whispering|shouting)\s+to|speaking\s+(?:loudly|privately)\s+to)\s+([^()]+?)(?:\s+from\s+far\s+away)?\s*\)/i', $eventData, $matches)) {
        return $metadata;
    }

    $metadata["hasExplicitTarget"] = true;
    $targetHint = trim($matches[1]);
    if ($targetHint === "") {
        return $metadata;
    }

    if (strcasecmp($targetHint, "everyone") === 0) {
        $metadata["isBroadcast"] = true;
        return $metadata;
    }

    $splitTargets = preg_split('/\s*(?:,|&| and )\s*/i', $targetHint);
    if (is_array($splitTargets)) {
        foreach ($splitTargets as $splitTarget) {
            $splitTarget = trim((string)$splitTarget);
            if ($splitTarget !== "") {
                $metadata["targets"][] = $splitTarget;
            }
        }
    }

    if (empty($metadata["targets"])) {
        $metadata["targets"][] = $targetHint;
    }

    return $metadata;
}

function talkTargetsIncludeName($targetNames, $candidateName)
{
    $candidateNormalized = normalizeActorNameForComparison($candidateName);
    if ($candidateNormalized === "") {
        return false;
    }

    if (!is_array($targetNames) || empty($targetNames)) {
        return false;
    }

    foreach ($targetNames as $targetName) {
        if (normalizeActorNameForComparison($targetName) === $candidateNormalized) {
            return true;
        }
    }

    return false;
}

function extractSpeakerNameFromInputEvent($eventData)
{
    $eventData = trim((string)$eventData);
    if ($eventData === "") {
        return "";
    }

    if (!preg_match('/^\s*([^:]{1,128})\s*:/u', $eventData, $matches)) {
        return "";
    }

    $speakerName = trim($matches[1]);
    $speakerName = trim($speakerName, "|");
    if ($speakerName === "") {
        return "";
    }
    if (isSystemContextLabel($speakerName)) {
        return "";
    }

    return $speakerName;
}

function extractCoreUtteranceFromInputEvent($eventData)
{
    $eventData = trim((string)$eventData);
    if ($eventData === "") {
        return "";
    }

    if (preg_match('/^\s*[^:]{1,128}\s*:\s*(.*)$/us', $eventData, $matches)) {
        $eventData = trim((string)$matches[1]);
    }

    $eventData = preg_replace('/\s*\(\s*(?:(?:talking|whispering)\s+to|speaking\s+loudly\s+to)\s+[^)]*\)\s*$/iu', '', $eventData);
    return trim((string)$eventData);
}

function extractSpeakerNameFromChatEvent($eventData)
{
    $eventData = trim((string)$eventData);
    if ($eventData === "") {
        return "";
    }

    // Strip optional "(Context ...)" prefix used by background chatter.
    $eventData = preg_replace('/^\s*\(\s*context[^)]*\)\s*/iu', '', $eventData);
    $eventData = trim((string)$eventData);
    if ($eventData === "") {
        return "";
    }

    if (!preg_match('/^\s*([^:]{1,128})\s*:/u', $eventData, $matches)) {
        return "";
    }

    $speakerName = trim($matches[1]);
    $speakerName = trim($speakerName, "|");
    if (isSystemContextLabel($speakerName)) {
        return "";
    }
    return ($speakerName === "") ? "" : $speakerName;
}

function extractCoreUtteranceFromChatEvent($eventData)
{
    $eventData = trim((string)$eventData);
    if ($eventData === "") {
        return "";
    }

    // Strip optional "(Context ...)" prefix used by ambient lines.
    $eventData = preg_replace('/^\s*\(\s*context[^)]*\)\s*/iu', '', $eventData);
    $eventData = trim((string)$eventData);

    if (preg_match('/^\s*[^:]{1,128}\s*:\s*(.*)$/us', $eventData, $matches)) {
        $eventData = trim((string)$matches[1]);
    }

    $eventData = preg_replace('/\s*\(\s*(?:(?:talking|whispering|shouting)\s+to|speaking\s+(?:loudly|privately)\s+to)\s+[^)]*\)\s*$/iu', '', $eventData);
    return trim((string)$eventData);
}

function chimGetVisibleChatDeliveryStates()
{
    return ['emitted', 'spoken'];
}

function chimGetInFlightChatDeliveryStates()
{
    return ['pending', 'emitted'];
}

function chimBuildChatDeliveryStateSql($column = 'delivery_state', ?array $states = null, $legacyDefault = 'spoken')
{
    $column = trim((string)$column);
    if ($column === '') {
        $column = 'delivery_state';
    }

    if ($states === null) {
        $states = chimGetVisibleChatDeliveryStates();
    }

    $normalizedStates = array_values(array_unique(array_filter(array_map(static function ($state) {
        return trim((string)$state);
    }, $states))));
    if (empty($normalizedStates)) {
        $normalizedStates = ['spoken'];
    }

    $legacyDefault = trim((string)$legacyDefault);
    if ($legacyDefault === '') {
        $legacyDefault = 'spoken';
    }

    $quotedStates = array_map(static function ($state) {
        return "'" . str_replace("'", "''", $state) . "'";
    }, $normalizedStates);

    return "COALESCE({$column}, '{$legacyDefault}') IN (" . implode(', ', $quotedStates) . ")";
}

function lookupSpatialCompanionsFromSpeech($speakerName, $listenerName = "", $utterance = "")
{
    global $db;

    $speakerName = trim((string)$speakerName);
    if ($speakerName === "") {
        return [];
    }

    $speakerEscaped = $db->escape($speakerName);
    $rows = $db->fetchAll(
        "SELECT rowid, companions, listener, speaker, speech, topic, localts
         FROM speech
         WHERE LOWER(speaker)=LOWER('{$speakerEscaped}')
           AND localts > " . (time() - 900) . "
         ORDER BY rowid DESC
         LIMIT 80"
    );
    if (!is_array($rows) || empty($rows)) {
        return [];
    }

    $wantedListener = normalizeActorNameForComparison($listenerName);
    $wantedUtterance = normalizeDialogTextForComparison($utterance);

    $bestRow = null;
    $bestScore = -1;

    foreach ($rows as $row) {
        $topic = trim((string)($row["topic"] ?? ""));
        if ($topic === "" || stripos($topic, "spatial:") === false) {
            continue;
        }

        $rowListener = normalizeActorNameForComparison($row["listener"] ?? "");
        $rowUtterance = normalizeDialogTextForComparison($row["speech"] ?? "");

        $score = 0;
        if ($wantedListener !== "" && $rowListener === $wantedListener) {
            $score += 4;
        }

        if ($wantedUtterance !== "" && $rowUtterance !== "") {
            if ($rowUtterance === $wantedUtterance) {
                $score += 8;
            } elseif (strpos($rowUtterance, $wantedUtterance) !== false || strpos($wantedUtterance, $rowUtterance) !== false) {
                $score += 3;
            }
        }

        if ($wantedListener === "" && $wantedUtterance === "") {
            $score = 1;
        }

        if ($score <= 0) {
            continue;
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestRow = $row;
            if ($score >= 12) {
                break;
            }
        }
    }

    if (!is_array($bestRow)) {
        return [];
    }

    $names = parsePeoplePipeList($bestRow["companions"] ?? "");
    $rowListenerRaw = trim((string)($bestRow["listener"] ?? ""));
    if ($rowListenerRaw !== "") {
        $names[] = $rowListenerRaw;
    }
    $rowSpeakerRaw = trim((string)($bestRow["speaker"] ?? ""));
    if ($rowSpeakerRaw !== "") {
        $names[] = $rowSpeakerRaw;
    }

    return array_values(array_unique(array_filter(array_map(static function ($name) {
        return trim((string)$name);
    }, $names))));
}

function extractActorNameFromInfoActionEvent($eventData)
{
    $eventData = trim((string)$eventData);
    if ($eventData === "") {
        return "";
    }

    $patterns = [
        '/^\s*([^:]{1,128}?)\s+uses\s+/iu',
        '/^\s*([^:]{1,128}?)\s+receives\s+/iu',
        '/^\s*([^:]{1,128}?)\s+teleports?\s+to\s+/iu',
        '/^\s*([^:]{1,128}?)\s+is\s+killed\b/iu',
        '/^\s*Could not teleport\s+([^:]{1,128}?)\s+to\s+/iu',
        '/^\s*Could not kill\s+([^:]{1,128}?)(?:\.|\s|$)/iu',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $eventData, $matches)) {
            $actorName = trim($matches[1]);
            $actorName = trim($actorName, "|");
            return ($actorName === "") ? "" : $actorName;
        }
    }

    return "";
}

function appendUniqueActorName(&$names, $name)
{
    $name = trim((string)$name);
    $name = trim($name, "|");
    if ($name === "") {
        return;
    }
    if (!shouldIncludeActorNameInPeopleList($name)) {
        return;
    }

    $normalizedCandidate = normalizeActorNameForComparison($name);
    if ($normalizedCandidate === "") {
        return;
    }

    foreach ($names as $existingName) {
        if (normalizeActorNameForComparison($existingName) === $normalizedCandidate) {
            return;
        }
    }

    $names[] = $name;
}

function isStrictSpatialPeopleModeEnabled()
{
    // Strict mode defaults to enabled: never fall back to broad nearby lists when spatial
    // evidence is missing. Events are scoped to known participants/listener only.
    return true;
}

function sanitizeActorTokenFromEventPayload($token)
{
    $token = trim((string)$token);
    if ($token === "") {
        return "";
    }

    $token = trim($token, "|");
    $token = trim($token, " \t\n\r\0\x0B,;/");
    if ($token === "") {
        return "";
    }

    $token = preg_replace('/^\(\s*beings in range:\s*/iu', '', $token);
    $token = preg_replace('/^\s*beings in range:\s*/iu', '', $token);
    $token = trim((string)$token);
    if ($token === "") {
        return "";
    }

    $token = trim($token, " \t\n\r\0\x0B,;/");
    if ($token === "") {
        return "";
    }

    if (!shouldIncludeActorNameInPeopleList($token)) {
        return "";
    }

    return $token;
}

function extractActorNamesFromDelimitedEventPayload($eventData, $delimiterPattern)
{
    $eventData = trim((string)$eventData);
    if ($eventData === "") {
        return [];
    }

    $eventData = preg_replace('/^\(\s*beings in range:\s*/iu', '', $eventData);
    $eventData = preg_replace('/^\s*beings in range:\s*/iu', '', $eventData);
    $eventData = trim((string)$eventData);
    $eventData = preg_replace('/\)\s*$/u', '', $eventData);
    $eventData = trim((string)$eventData);
    if ($eventData === "") {
        return [];
    }

    $tokens = preg_split($delimiterPattern, $eventData);
    if (!is_array($tokens) || empty($tokens)) {
        return [];
    }

    $names = [];
    foreach ($tokens as $token) {
        $candidate = sanitizeActorTokenFromEventPayload($token);
        if ($candidate === "") {
            continue;
        }
        appendUniqueActorName($names, $candidate);
    }

    return $names;
}

function extractEventPayloadParticipants($eventType, $eventData)
{
    $eventType = strtolower(trim((string)$eventType));
    $eventData = trim((string)$eventData);
    if ($eventData === "") {
        return [];
    }

    if ($eventType === "infonpc_close") {
        return extractActorNamesFromDelimitedEventPayload($eventData, '/\s*[\/|]\s*/u');
    }

    if ($eventType === "infonpc") {
        return extractActorNamesFromDelimitedEventPayload($eventData, '/\s*,\s*/u');
    }

    if ($eventType === "addnpc") {
        $nameToken = trim((string)$eventData);
        if (strpos($nameToken, "@") !== false) {
            $nameToken = explode("@", $nameToken, 2)[0];
        }
        $nameToken = sanitizeActorTokenFromEventPayload($nameToken);
        if ($nameToken !== "") {
            return [$nameToken];
        }
        return [];
    }

    return [];
}

function isSpellcastEventType($eventType)
{
    $eventType = strtolower(trim((string)$eventType));
    return in_array($eventType, ["spellcast", "npcspellcast"], true);
}

function extractSpellcastParticipants($eventData)
{
    $eventData = trim((string)$eventData);
    if ($eventData === "") {
        return [];
    }

    $participants = [];

    if (preg_match('/^\s*([^:]{1,128}?)\s+casts\b/iu', $eventData, $matches)) {
        $casterName = sanitizeActorTokenFromEventPayload($matches[1] ?? "");
        if ($casterName !== "") {
            appendUniqueActorName($participants, $casterName);
        }
    }

    if (preg_match('/^\s*[^:]{1,128}?\s+casts\b.*?\bon\s+([^.,!?()]{1,128}?)(?:\s*\([^)]*\)\s*|[.,!?]|$)/iu', $eventData, $matches)) {
        $targetName = sanitizeActorTokenFromEventPayload($matches[1] ?? "");
        if ($targetName !== "") {
            appendUniqueActorName($participants, $targetName);
        }
    }

    return $participants;
}

function mergeParticipantsWithPeoplePipe($participantNames, $peoplePipe)
{
    $mergedPeople = parsePeoplePipeList($peoplePipe);
    if (is_array($participantNames)) {
        foreach ($participantNames as $participantName) {
            appendUniqueActorName($mergedPeople, $participantName);
        }
    }

    return normalizePeoplePipeList($mergedPeople);
}

function isNarratorPrivateActionName($actionName)
{
    $actionName = trim((string)$actionName);
    if ($actionName === "") {
        return false;
    }

    if (function_exists('herikaFindActionCatalogRowByNameOrCode')) {
        $row = herikaFindActionCatalogRowByNameOrCode($actionName, false);
        if (is_array($row)) {
            return !empty($row['available_to_narrator'])
                && empty($row['available_to_npc'])
                && empty($row['available_to_followers']);
        }
    }

    $normalizedActionName = strtolower(str_replace([" ", "-"], "", $actionName));
    return in_array($normalizedActionName, ["spawnitem", "spawn_item", "spawnnpc", "spawn_npc", "teleportnpc", "teleport_npc", "killtarget", "kill_target", "createnewnpc", "create_new_npc"], true);
}

function isNarratorPrivatePlayerAlias($targetName, $listenerName = "")
{
    $targetName = trim((string)$targetName);
    $listenerName = trim((string)$listenerName);
    if ($targetName === "") {
        return true;
    }

    $normalizedTarget = normalizeActorNameForComparison($targetName);
    if ($normalizedTarget === "" || $normalizedTarget === "player" || $normalizedTarget === "me" || $normalizedTarget === "the narrator" || $normalizedTarget === "narrator") {
        return true;
    }

    $playerName = trim((string)($GLOBALS["PLAYER_NAME"] ?? "Player"));
    if ($playerName !== "" && strcasecmp($targetName, $playerName) === 0) {
        return true;
    }

    if ($listenerName !== "" && strcasecmp($targetName, $listenerName) === 0) {
        return true;
    }

    return false;
}

function extractNarratorPrivateActionTargetName($eventType, $eventData)
{
    $eventType = strtolower(trim((string)$eventType));
    $eventData = trim((string)$eventData);
    if ($eventData === "") {
        return "";
    }

    if ($eventType === "logaction") {
        $payload = json_decode($eventData, true);
        if (is_array($payload) && isNarratorPrivateActionName($payload["action"] ?? "")) {
            return trim((string)($payload["target"] ?? ""));
        }
        return "";
    }

    if ($eventType === "funcret") {
        if (preg_match('/^command@([^@]+)@([^@]*)@/iu', $eventData, $matches) && isNarratorPrivateActionName($matches[1] ?? '')) {
            return trim((string)($matches[2] ?? ""));
        }
        return "";
    }

    if ($eventType === "infoaction") {
        return extractActorNameFromInfoActionEvent($eventData);
    }

    return "";
}

function shouldScopeNarratorPrivateActionEvent($eventType, $eventData, $listenerName = "")
{
    $eventType = strtolower(trim((string)$eventType));
    $listenerNormalized = normalizeActorNameForComparison($listenerName);
    $eventData = trim((string)$eventData);

    if (!in_array($eventType, ["logaction", "funcret", "infoaction"], true) || $eventData === "") {
        return false;
    }

    if ($eventType === "logaction") {
        $payload = json_decode($eventData, true);
        if (!is_array($payload) || !isNarratorPrivateActionName($payload["action"] ?? "")) {
            return false;
        }

        $characterName = trim((string)($payload["character"] ?? ""));
        return $listenerNormalized === "the narrator" || strcasecmp($characterName, "The Narrator") === 0;
    }

    if ($eventType === "funcret") {
        return preg_match('/^command@([^@]+)@/iu', $eventData, $matches) === 1
            && isNarratorPrivateActionName($matches[1] ?? '');
    }

    if ($listenerNormalized !== "the narrator") {
        return false;
    }

    return preg_match('/^\s*([^:]{1,128}?)\s+(?:receives|teleports?\s+to|is\s+killed)\b/iu', $eventData) === 1
        || preg_match('/^\s*Could not teleport\s+/iu', $eventData) === 1
        || preg_match('/^\s*Could not kill\s+/iu', $eventData) === 1;
}

function buildNarratorPrivatePeopleForEvent($eventType, $eventData, $listenerName = "")
{
    if (!shouldScopeNarratorPrivateActionEvent($eventType, $eventData, $listenerName)) {
        return "";
    }

    $playerName = trim((string)($GLOBALS["PLAYER_NAME"] ?? "Player"));
    $participants = ["The Narrator"];
    if ($playerName !== "") {
        $participants[] = $playerName;
    }

    return normalizePeoplePipeList($participants);
}

function buildCanonicalAudiencePeople($fallbackPeople = "", $participants = [])
{
    $audienceNames = [];
    foreach (parsePeoplePipeList($fallbackPeople) as $audienceName) {
        $audienceName = stripActorStateSuffix($audienceName);
        if ($audienceName !== "") {
            appendUniqueActorName($audienceNames, $audienceName);
        }
    }

    if (is_array($participants)) {
        foreach ($participants as $participantName) {
            $participantName = stripActorStateSuffix($participantName);
            if ($participantName !== "") {
                appendUniqueActorName($audienceNames, $participantName);
            }
        }
    }

    return normalizePeoplePipeList($audienceNames);
}

function buildNarratorSharedPeopleForEvent($eventType, $eventData, $listenerName = "", $fallbackPeople = "")
{
    if (!empty($GLOBALS["HIDE_NARRATOR_DIALOGUE"])) {
        return "";
    }

    $eventType = strtolower((string)$eventType);
    $participants = [];

    if ($eventType === "narrator_inputtext") {
        $speakerName = extractSpeakerNameFromInputEvent($eventData);
        if ($speakerName === "" && !empty($GLOBALS["PLAYER_NAME"])) {
            $speakerName = trim((string)$GLOBALS["PLAYER_NAME"]);
        }
        if ($speakerName !== "") {
            appendUniqueActorName($participants, $speakerName);
        }
        appendUniqueActorName($participants, ($listenerName !== "") ? $listenerName : "The Narrator");

        $sharedPeople = buildCanonicalAudiencePeople($fallbackPeople, $participants);
        return ($sharedPeople !== "") ? $sharedPeople : normalizePeoplePipeList($participants);
    }

    if ($eventType !== "chat") {
        return "";
    }

    $speakerName = extractSpeakerNameFromChatEvent($eventData);
    if (normalizeActorNameForComparison($speakerName) !== "the narrator") {
        return "";
    }

    if (stripos((string)$eventData, '(whispering to ') !== false ||
        stripos((string)$eventData, '(speaking privately to ') !== false) {
        return "";
    }

    appendUniqueActorName($participants, $speakerName);
    $targetMeta = extractTalkTargetMetadata($eventData);
    foreach ($targetMeta["targets"] as $targetName) {
        appendUniqueActorName($participants, $targetName);
    }

    $sharedPeople = buildCanonicalAudiencePeople($fallbackPeople, $participants);
    return ($sharedPeople !== "") ? $sharedPeople : normalizePeoplePipeList($participants);
}

function extractGenericEventParticipants($eventType, $eventData)
{
    $participants = [];
    $eventType = strtolower((string)$eventType);
    $eventData = (string)$eventData;

    $speakerName = extractSpeakerNameFromInputEvent($eventData);
    if ($speakerName === "") {
        $speakerName = extractSpeakerNameFromChatEvent($eventData);
    }
    if ($speakerName !== "") {
        appendUniqueActorName($participants, $speakerName);
    }

    if (isSpellcastEventType($eventType)) {
        $spellParticipants = extractSpellcastParticipants($eventData);
        foreach ($spellParticipants as $spellParticipant) {
            appendUniqueActorName($participants, $spellParticipant);
        }
    }

    $targetMeta = extractTalkTargetMetadata($eventData);
    if (!empty($targetMeta["targets"])) {
        foreach ($targetMeta["targets"] as $targetName) {
            appendUniqueActorName($participants, $targetName);
        }
    }

    $infoActionActor = extractActorNameFromInfoActionEvent($eventData);
    if ($infoActionActor !== "") {
        appendUniqueActorName($participants, $infoActionActor);
    }

    if ($eventType === "narrator_inputtext") {
        appendUniqueActorName($participants, "The Narrator");
    }

    $payloadParticipants = extractEventPayloadParticipants($eventType, $eventData);
    if (!empty($payloadParticipants)) {
        foreach ($payloadParticipants as $payloadName) {
            appendUniqueActorName($participants, $payloadName);
        }
    }

    return $participants;
}

function buildStrictFallbackPeopleForEvent($eventType, $eventData, $listenerName, $fallbackPeople = "")
{
    $eventType = strtolower((string)$eventType);
    $eventData = (string)$eventData;

    $names = extractGenericEventParticipants($eventType, $eventData);

    if (shouldAutoAppendListenerToPeople($eventType, $eventData, $listenerName)) {
        appendUniqueActorName($names, $listenerName);
    }

    if (isSpellcastEventType($eventType) && $fallbackPeople !== "") {
        $spellScopedFallback = mergeParticipantsWithPeoplePipe($names, $fallbackPeople);
        if ($spellScopedFallback !== "") {
            return $spellScopedFallback;
        }
    }

    if (empty($names) && $fallbackPeople !== "") {
        // Strict mode still allows a narrowed fallback token if caller already provided
        // a single/limited scoped list.
        $fallbackNames = parsePeoplePipeList($fallbackPeople);
        if (count($fallbackNames) === 1) {
            appendUniqueActorName($names, $fallbackNames[0]);
        }
    }

    return normalizePeoplePipeList($names);
}

function lookupLatestSpatialCompanionsByParticipant($participantName, $maxAgeSeconds = 900)
{
    global $db;

    $participantName = trim((string)$participantName);
    if ($participantName === "") {
        return [];
    }

    $participantEscaped = $db->escape($participantName);
    $participantPipeToken = "|" . strtolower($participantName) . "|";
    $participantPipeEscaped = $db->escape($participantPipeToken);
    $ageSeconds = max(30, intval($maxAgeSeconds));
    $cutoff = time() - $ageSeconds;

    $rows = $db->fetchAll(
        "SELECT rowid, companions, listener, speaker, topic
         FROM speech
         WHERE localts > {$cutoff}
           AND topic LIKE '%spatial:%'
           AND (
                LOWER(speaker)=LOWER('{$participantEscaped}')
                OR LOWER(listener)=LOWER('{$participantEscaped}')
                OR LOWER(companions) LIKE '%{$participantPipeEscaped}%'
           )
         ORDER BY rowid DESC
         LIMIT 20"
    );

    if (!is_array($rows) || empty($rows)) {
        return [];
    }

    $row = $rows[0];
    $names = parsePeoplePipeList($row["companions"] ?? "");
    $listenerName = trim((string)($row["listener"] ?? ""));
    if ($listenerName !== "") {
        $names[] = $listenerName;
    }
    $speakerName = trim((string)($row["speaker"] ?? ""));
    if ($speakerName !== "") {
        $names[] = $speakerName;
    }

    $deduped = [];
    foreach ($names as $name) {
        appendUniqueActorName($deduped, $name);
    }

    return $deduped;
}

function lookupConversationPeopleSourceOfTruth($speakerName, $targetName, $maxAgeSeconds = 300)
{
    global $db;

    $speakerName = trim((string)$speakerName);
    $targetName = trim((string)$targetName);
    if ($speakerName === "" || $targetName === "") {
        return "";
    }

    $speakerNormalized = normalizeActorNameForComparison($speakerName);
    $targetNormalized = normalizeActorNameForComparison($targetName);
    if ($speakerNormalized === "" || $targetNormalized === "") {
        return "";
    }

    $ageSeconds = max(30, intval($maxAgeSeconds));
    $cutoff = time() - $ageSeconds;
    $rows = $db->fetchAll(
        "SELECT rowid, type, data, people
         FROM eventlog
         WHERE localts > {$cutoff}
           AND type IN ('chat','prechat','inputtext','inputtext_s','ginputtext','ginputtext_s','narrator_inputtext')
           AND people IS NOT NULL
           AND TRIM(people) <> ''
         ORDER BY rowid DESC
         LIMIT 120"
    );

    if (!is_array($rows) || empty($rows)) {
        return "";
    }

    foreach ($rows as $row) {
        $rowType = strtolower((string)($row["type"] ?? ""));
        $rowData = (string)($row["data"] ?? "");
        $rowPeople = normalizePeoplePipeList(parsePeoplePipeList($row["people"] ?? ""));
        if ($rowType === "" || $rowData === "" || $rowPeople === "") {
            continue;
        }

        if (in_array($rowType, ["inputtext", "inputtext_s", "ginputtext", "ginputtext_s", "narrator_inputtext"], true)) {
            $rowSpeaker = extractSpeakerNameFromInputEvent($rowData);
        } else {
            $rowSpeaker = extractSpeakerNameFromChatEvent($rowData);
        }
        if ($rowSpeaker === "") {
            continue;
        }

        $rowSpeakerNormalized = normalizeActorNameForComparison($rowSpeaker);
        if ($rowSpeakerNormalized === "") {
            continue;
        }

        $rowTargetMeta = extractTalkTargetMetadata($rowData);
        $rowTargets = $rowTargetMeta["targets"] ?? [];

        $directMatch = (
            $rowSpeakerNormalized === $speakerNormalized &&
            !empty($rowTargets) &&
            talkTargetsIncludeName($rowTargets, $targetName)
        );
        $reverseMatch = (
            $rowSpeakerNormalized === $targetNormalized &&
            !empty($rowTargets) &&
            talkTargetsIncludeName($rowTargets, $speakerName)
        );
        if (!$directMatch && !$reverseMatch) {
            continue;
        }

        $rowNames = parsePeoplePipeList($rowPeople);
        appendUniqueActorName($rowNames, $speakerName);
        appendUniqueActorName($rowNames, $targetName);
        $candidatePipe = normalizePeoplePipeList($rowNames);
        if ($candidatePipe === "") {
            continue;
        }

        return $candidatePipe;
    }

    return "";
}

function buildScopedPeopleFromSpatialEvidence($eventType, $eventData, $listenerName, $fallbackPeople = "")
{
    $eventType = strtolower((string)$eventType);
    $participants = extractGenericEventParticipants($eventType, $eventData);
    $lookupCandidates = $participants;
    appendUniqueActorName($lookupCandidates, $listenerName);

    if (empty($lookupCandidates)) {
        return $fallbackPeople;
    }

    $targetMeta = extractTalkTargetMetadata($eventData);
    $primaryTarget = "";
    if (!empty($targetMeta["targets"])) {
        $primaryTarget = trim((string)$targetMeta["targets"][0]);
    }
    $speakerName = extractSpeakerNameFromInputEvent($eventData);
    if ($speakerName === "") {
        $speakerName = extractSpeakerNameFromChatEvent($eventData);
    }

    $bestSpatialPeople = [];
    foreach ($lookupCandidates as $candidateName) {
        $spatialPeople = [];
        if ($speakerName !== "" && normalizeActorNameForComparison($candidateName) === normalizeActorNameForComparison($speakerName)) {
            $spatialPeople = lookupSpatialCompanionsFromSpeech($speakerName, $primaryTarget);
        }

        if (empty($spatialPeople)) {
            $spatialPeople = lookupLatestSpatialCompanionsByParticipant($candidateName);
        }

        if (!empty($spatialPeople)) {
            $bestSpatialPeople = $spatialPeople;
            break;
        }
    }

    if (!empty($bestSpatialPeople)) {
        foreach ($participants as $participantName) {
            $bestSpatialPeople[] = $participantName;
        }

        $scopedFromSpatial = normalizePeoplePipeList($bestSpatialPeople);
        if ($scopedFromSpatial !== "") {
            return $scopedFromSpatial;
        }
    }

    if (isSpellcastEventType($eventType) && $fallbackPeople !== "") {
        $spellFallbackScoped = mergeParticipantsWithPeoplePipe($participants, $fallbackPeople);
        if ($spellFallbackScoped !== "") {
            return $spellFallbackScoped;
        }
    }

    if (!empty($participants)) {
        $participantScoped = normalizePeoplePipeList($participants);
        if ($participantScoped !== "") {
            return $participantScoped;
        }
    }

    return $fallbackPeople;
}

function buildScopedPeopleForChatEvent($eventData, $fallbackPeople = "")
{
    $eventData = (string)$eventData;
    if ($eventData === "") {
        return $fallbackPeople;
    }

    $hasContextLocationPrefix = preg_match('/^\s*\(\s*context location:/iu', $eventData) === 1;

    if (stripos($eventData, ' background chat)') !== false) {
        $speakerName = extractSpeakerNameFromChatEvent($eventData);
        $backgroundNames = parsePeoplePipeList(DataBeingsInRange());
        if (empty($backgroundNames)) {
            $backgroundNames = parsePeoplePipeList($fallbackPeople);
        }
        if ($speakerName !== "") {
            appendUniqueActorName($backgroundNames, $speakerName);
        }
        $backgroundScoped = normalizePeoplePipeList($backgroundNames);
        if ($backgroundScoped !== "") {
            return $backgroundScoped;
        }
    }

    $targetMeta = extractTalkTargetMetadata($eventData);
    if ($hasContextLocationPrefix) {
        $speakerName = extractSpeakerNameFromChatEvent($eventData);
        $contextNames = parsePeoplePipeList(DataBeingsInRange());
        if (empty($contextNames)) {
            $contextNames = parsePeoplePipeList($fallbackPeople);
        }
        if ($speakerName !== "") {
            appendUniqueActorName($contextNames, $speakerName);
        }
        if (!empty($targetMeta["targets"])) {
            foreach ($targetMeta["targets"] as $targetName) {
                $targetName = trim((string)$targetName);
                if ($targetName !== "") {
                    appendUniqueActorName($contextNames, $targetName);
                }
            }
        }
        $contextScoped = normalizePeoplePipeList($contextNames);
        if ($contextScoped !== "") {
            return $contextScoped;
        }
    }

    if ($targetMeta["isBroadcast"]) {
        return $fallbackPeople;
    }

    $participants = [];
    $speakerName = extractSpeakerNameFromChatEvent($eventData);
    if ($speakerName !== "") {
        $participants[] = $speakerName;
    }

    if (!empty($targetMeta["targets"])) {
        foreach ($targetMeta["targets"] as $targetName) {
            $targetName = trim((string)$targetName);
            if ($targetName !== "") {
                $participants[] = $targetName;
            }
        }
    }

    $primaryTarget = "";
    if (!empty($targetMeta["targets"])) {
        $primaryTarget = trim((string)$targetMeta["targets"][0]);
    }

    // Explicit targeted chat: use the latest conversation SOT people list first
    // (player input or initial speaker chat), then fail-closed to participants.
    if ($targetMeta["hasExplicitTarget"] && !$targetMeta["isBroadcast"]) {
        if ($speakerName !== "" && $primaryTarget !== "") {
            $sotPeople = lookupConversationPeopleSourceOfTruth($speakerName, $primaryTarget, 300);
            if ($sotPeople !== "") {
                return $sotPeople;
            }
        }

        $scopedPeople = normalizePeoplePipeList($participants);
        if ($scopedPeople !== "") {
            return $scopedPeople;
        }
    }

    $coreUtterance = extractCoreUtteranceFromChatEvent($eventData);
    $spatialPeople = lookupSpatialCompanionsFromSpeech($speakerName, $primaryTarget, $coreUtterance);
    if (empty($spatialPeople) && $speakerName !== "" && !$targetMeta["hasExplicitTarget"]) {
        // For ambient/untargeted lines, allow recent speaker spatial context fallback.
        // For explicit "(talking to X)" lines, keep strict participant-only scope until
        // direct speech-row match/backfill arrives to avoid stale broad leakage.
        $spatialPeople = lookupSpatialCompanionsFromSpeech($speakerName, $primaryTarget, "");
        if (empty($spatialPeople)) {
            $spatialPeople = lookupLatestSpatialCompanionsByParticipant($speakerName, 180);
        }
    }
    if (!empty($spatialPeople)) {
        foreach ($participants as $participantName) {
            $participantName = trim((string)$participantName);
            if ($participantName !== "") {
                $spatialPeople[] = $participantName;
            }
        }

        $spatialScoped = normalizePeoplePipeList($spatialPeople);
        if ($spatialScoped !== "") {
            return $spatialScoped;
        }
    }

    $scopedPeople = normalizePeoplePipeList($participants);
    if ($scopedPeople !== "") {
        return $scopedPeople;
    }

    return $fallbackPeople;
}

function buildScopedPeopleForInfoActionEvent($eventData, $fallbackPeople = "")
{
    $actorName = extractActorNameFromInfoActionEvent($eventData);
    $fallbackNames = parsePeoplePipeList($fallbackPeople);
    $fallbackNames = array_values(array_filter($fallbackNames, static function ($name) {
        return normalizeActorNameForComparison($name) !== "the narrator";
    }));
    $fallbackScoped = normalizePeoplePipeList($fallbackNames);
    if ($fallbackScoped !== "") {
        error_log("[SCOPE_INFOACTION] actor='{$actorName}' initial_detection_scoped='{$fallbackScoped}'");
        return $fallbackScoped;
    }

    if ($actorName !== "") {
        $scopedPeople = normalizePeoplePipeList([$actorName]);
        if ($scopedPeople !== "") {
            error_log("[SCOPE_INFOACTION] actor='{$actorName}' fallback_actor_scoped='{$scopedPeople}'");
            return $scopedPeople;
        }
    }

    return $fallbackPeople;
}

function shouldAutoAppendListenerToPeople($eventType, $eventData, $listenerName)
{
    $eventType = strtolower((string)$eventType);
    $listenerName = trim((string)$listenerName);
    if ($listenerName === "") {
        return false;
    }

    $listenerNormalized = normalizeActorNameForComparison($listenerName);
    if ($listenerNormalized === "the narrator" && $eventType !== "narrator_inputtext") {
        return false;
    }

    if (!in_array($eventType, ["inputtext", "inputtext_s", "ginputtext", "ginputtext_s", "narrator_inputtext"], true)) {
        return true;
    }

    if ($eventType === "narrator_inputtext") {
        return (strcasecmp(normalizeActorNameForComparison($listenerName), "the narrator") === 0);
    }

    $targetMeta = extractTalkTargetMetadata($eventData);
    if (!$targetMeta["hasExplicitTarget"] || $targetMeta["isBroadcast"]) {
        return true;
    }

    if (empty($targetMeta["targets"])) {
        return false;
    }

    return talkTargetsIncludeName($targetMeta["targets"], $listenerName);
}

function buildScopedPeopleForPlayerInput($eventType, $eventData, $listenerName, $fallbackPeople = "")
{
    $eventType = strtolower((string)$eventType);
    if (!in_array($eventType, ["inputtext", "inputtext_s", "ginputtext", "ginputtext_s", "narrator_inputtext"], true)) {
        return $fallbackPeople;
    }

    if ($eventType === "narrator_inputtext") {
        $participants = [];
        $speakerName = extractSpeakerNameFromInputEvent($eventData);
        if ($speakerName === "" && !empty($GLOBALS["PLAYER_NAME"])) {
            $speakerName = trim((string)$GLOBALS["PLAYER_NAME"]);
        }
        if ($speakerName !== "") {
            appendUniqueActorName($participants, $speakerName);
        }
        appendUniqueActorName($participants, ($listenerName !== "") ? $listenerName : "The Narrator");

        if (!empty($GLOBALS["HIDE_NARRATOR_DIALOGUE"])) {
            return normalizePeoplePipeList($participants);
        }

        $sharedPeople = buildCanonicalAudiencePeople($fallbackPeople, $participants);
        return ($sharedPeople !== "") ? $sharedPeople : normalizePeoplePipeList($participants);
    }

    $targetMeta = extractTalkTargetMetadata($eventData);
    if ($targetMeta["isBroadcast"]) {
        error_log("[SCOPE] Broadcast target detected for {$eventType}; keeping fallback people");
        return $fallbackPeople;
    }

    $targetNames = $targetMeta["targets"];

    // Keep private conversations private: only include the listener when it is an explicit target.
    if (!empty($listenerName) && talkTargetsIncludeName($targetNames, $listenerName) && !in_array($listenerName, $targetNames, true)) {
        $targetNames[] = $listenerName;
    }

    // Include the speaker so player-directed lines remain attributable in people context.
    $speakerName = extractSpeakerNameFromInputEvent($eventData);
    if ($speakerName === "" && !empty($GLOBALS["PLAYER_NAME"])) {
        $speakerName = trim((string)$GLOBALS["PLAYER_NAME"]);
    }
    if ($speakerName !== "" && !in_array($speakerName, $targetNames, true)) {
        $targetNames[] = $speakerName;
    }

    if (empty($targetNames) && !empty($listenerName)) {
        $targetNames[] = $listenerName;
    }

    $scopedPeople = normalizePeoplePipeList($targetNames);
    if ($scopedPeople !== "") {
        $debugTargets = implode(",", $targetNames);
        error_log("[SCOPE] {$eventType} listener='{$listenerName}' targets='{$debugTargets}' scoped='{$scopedPeople}'");
        return $scopedPeople;
    }

    error_log("[SCOPE] {$eventType} produced empty scoped people; using fallback");
    return $fallbackPeople;
}

function buildScopedPeopleForEvent($eventType, $eventData, $listenerName, $fallbackPeople = "")
{
    $eventType = strtolower((string)$eventType);

    $narratorPrivatePeople = buildNarratorPrivatePeopleForEvent($eventType, $eventData, $listenerName);
    if ($narratorPrivatePeople !== "") {
        return $narratorPrivatePeople;
    }

    $narratorSharedPeople = buildNarratorSharedPeopleForEvent($eventType, $eventData, $listenerName, $fallbackPeople);
    if ($narratorSharedPeople !== "") {
        return $narratorSharedPeople;
    }

    if ($eventType === "infoloc") {
        // Keep legacy infoloc behavior: do not apply strict spatial scoping.
        return $fallbackPeople;
    }

    $effectiveFallback = $fallbackPeople;
    if (isStrictSpatialPeopleModeEnabled() && !in_array($eventType, ["infoaction", "funcret"], true)) {
        $strictFallback = buildStrictFallbackPeopleForEvent($eventType, $eventData, $listenerName, $fallbackPeople);
        if ($strictFallback !== "") {
            $effectiveFallback = $strictFallback;
        } else {
            // Keep empty in strict mode instead of widening to broad nearby people.
            $effectiveFallback = "";
        }
    }

    if (in_array($eventType, ["inputtext", "inputtext_s", "ginputtext", "ginputtext_s", "narrator_inputtext"], true)) {
        return buildScopedPeopleForPlayerInput($eventType, $eventData, $listenerName, $effectiveFallback);
    }

    if (in_array($eventType, ["chat", "chat_background"], true)) {
        return buildScopedPeopleForChatEvent($eventData, $effectiveFallback);
    }

    if (in_array($eventType, ["infoaction", "funcret"], true)) {
        return buildScopedPeopleForInfoActionEvent($eventData, $effectiveFallback);
    }

    return buildScopedPeopleFromSpatialEvidence($eventType, $eventData, $listenerName, $effectiveFallback);
}

function isNarratorHistoricContextLine($content)
{
    $content = trim((string)$content);
    if (strpos($content, 'The Narrator:') !== 0) {
        return false;
    }

    return preg_match('/^The Narrator:\s*\(/', $content) === 1
        || strpos($content, 'The Narrator: background dialogue:') === 0
        || strpos($content, 'The Narrator: action moved to new location:') === 0
        || strpos($content, 'The Narrator: SCENARIO CHANGE') === 0
        || preg_match('/^The Narrator:\s*about\s+\d+\s+hours\s+later/i', $content) === 1;
}

function filterHistoricContextForNarratorVisibility(array $contextDataHistoric, $actorName)
{
    if (normalizeActorNameForComparison($actorName) === "the narrator") {
        return array_values($contextDataHistoric);
    }

    $hideNarratorDialogue = !empty($GLOBALS["HIDE_NARRATOR_DIALOGUE"]);

    return array_values(array_filter($contextDataHistoric, static function ($entry) use ($hideNarratorDialogue) {
        if (!is_array($entry)) {
            return true;
        }

        $content = isset($entry["content"]) ? (string)$entry["content"] : "";
        if (preg_match('/\(\s*(?:Talking|Whispering|Shouting|Speaking loudly|Speaking privately)\s+to\s+The Narrator(?:\s+from\s+far\s+away)?\s*\)/i', $content) === 1) {
            return false;
        }

        if (!$hideNarratorDialogue) {
            return true;
        }

        if (strpos($content, 'The Narrator:') === 0) {
            return isNarratorHistoricContextLine($content);
        }

        return true;
    }));
}

function chimGenerateUtteranceId()
{
    try {
        return "utt_" . bin2hex(random_bytes(10));
    } catch (Exception $e) {
        return "utt_" . substr(md5(uniqid((string)mt_rand(), true)), 0, 20);
    }
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

        $dataArray[0] = chimNormalizeLoggedEventType(
            $dataArray[0] ?? "",
            $dataArray[3] ?? ""
        );
        $eventType = strtolower((string)$dataArray[0]);
        $defaultPeopleFallback = $GLOBALS["CACHE_PEOPLE_LIMITED"];
        
        if (in_array($eventType, ["infoaction", "funcret"], true)) {
            $actionPeopleFallback = $GLOBALS["CACHE_PEOPLE"] ?? DataBeingsInCloseRange(false);
            if ($actionPeopleFallback !== "") {
                $defaultPeopleFallback = $actionPeopleFallback;
            }
        }

        if ($eventType === "infoloc") {
            $defaultPeopleFallback = DataBeingsInRange();
            if ($defaultPeopleFallback === "") {
                $defaultPeopleFallback = DataBeingsInCloseRange(false);
            }
        }


        if (isSpellcastEventType($dataArray[0] ?? "")) {
            $defaultPeopleFallback = DataBeingsInRange();
        }

        $eventPeople = ($forcePeople) ? $forcePeople : $defaultPeopleFallback;
        $hasForcedPeople = !empty($forcePeople);
        if (!$hasForcedPeople) {
            $turnPeopleSnapshot = chimGetCurrentTurnPeopleSnapshot();
            if (in_array($eventType, ["prechat", "chat"], true) && $turnPeopleSnapshot !== "") {
                $eventPeople = $turnPeopleSnapshot;
            } else {
                $eventPeople = buildScopedPeopleForEvent(
                    $dataArray[0] ?? "",
                    $dataArray[3] ?? "",
                    $GLOBALS["HERIKA_NAME"] ?? "",
                    $eventPeople
                );
            }
        }

        $extraColumns = [];
        if (isset($dataArray[5]) && is_array($dataArray[5])) {
            $extraColumns = $dataArray[5];
        }

        // Fixes. This should not be here.
        //if ($dataArray[0]=="funcret") {
        //    $eventPeople=DataBeingsInCloseRange(true);
        //}

        if ($dataArray[0]=="death") {
            $eventPeople=DataBeingsInCloseRange(false);
        }

        if ($dataArray[0]=="itemfound") {
            $eventPeople=DataBeingsInCloseRange(false);
        }

        if ($dataArray[0]=="chat_background") {
            $eventPeople=DataBeingsInCloseRange();
        }

        
        $insertData = array(
            'ts' => $dataArray[1],
            'gamets' => $dataArray[2],
            'type' => $dataArray[0],
            'data' => $dataArray[3],
            'sess' => $dataArray[4]??'pending',
            'localts' => time(),
            'people'=> $eventPeople,
            'location'=>$GLOBALS["CACHE_LOCATION"],
            'party'=>$GLOBALS["CACHE_PARTY"]
        );

        if (!empty($extraColumns)) {
            $insertData = array_merge($insertData, $extraColumns);
        }

        $insertResult = $db->insert('eventlog', $insertData);
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
    $promptCharacterName = function_exists('chimGetPromptCharacterName')
        ? chimGetPromptCharacterName()
        : $GLOBALS["HERIKA_NAME"];
    $narratorRoleplayName = function_exists('chimGetNarratorRoleplayName')
        ? chimGetNarratorRoleplayName()
        : 'The Narrator';
    return strtr($text, [
        "{LOCATION}" => DataLastKnownLocationHuman(),
        "{PLAYER_NAME}"   => $GLOBALS["PLAYER_NAME"],
        "{HERIKA_NAME}"   => $promptCharacterName,
        "{NARRATOR_NAME}" => $narratorRoleplayName,
        "{TEMPLATE_DIALOG}"   => $GLOBALS["TEMPLATE_DIALOG"],
    ]);
}
