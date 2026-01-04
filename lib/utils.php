<?php

function npcNameToCodename($npcName)
{
    $codename = mb_convert_encoding($npcName, 'UTF-8', mb_detect_encoding($npcName));
    $codename = strtr(strtolower(trim($codename)), [" " => "_", "'" => "+"]);
    $codename = preg_replace('/[^\w+-]/u', '', $codename);
    return $codename;
}

function isNonEmptyArray($var)
{
    if (! isset($var)) {
        return false;
    }

    return is_array($var) && count($var) > 0;
}

function getBaseUrlForSpeech(): string
{
    $protocol = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    //$host = $_SERVER['HTTP_HOST']; // host could contain port for some configurations
    $host = $_SERVER['SERVER_ADDR'];
    $port = intval($_SERVER['SERVER_PORT']); // under Apache 2, UseCanonicalName = On as well as UseCanonicalPhysicalPort = On must be set in order to get the real port, otherwise, this value can be spoofed.

    if (empty($port) || ($port == 80)) {
        $port = 8081;
    }
    // Seems this is not being autodetected

    // Check if the port is non-standard for the protocol
    $isDefaultPort = ($protocol === "http://" && $port == 80) || ($protocol === "https://" && $port == 443);
    //error_log(" getBaseUrlForSpeech: $protocol - $host  -  $port "); //debug

    return $protocol . $host . ($isDefaultPort ? '' : ':' . $port);
}

function internalDumbTranslatorOld($inputSentence)
{
    if ((isset($GLOBALS["LLM_LANG"]) && $GLOBALS["LLM_LANG"] != "en")||true) {
        $localStartTime = microtime(true);
        // Use word by word translation
        $sense = (isset($GLOBALS["LLM_LANG"]) ? $GLOBALS["LLM_LANG"] : "en") . "-en";
        //$sense="en-en";
        $translatedKeywords=[];
        foreach (explode(" ", $inputSentence) as $word) {
            $word    = str_replace(['?', '!', '.', '-', '¿', '¡', "'", ',', '_'], '', $word); // Other languages may need to add more symbols
            $trlData = $GLOBALS["db"]->fetchOne("select translated_word,expansion from translations where source_word='" . $GLOBALS["db"]->escape($word) . "' and sense='$sense'");
            error_log("select translated_word,expansion from translations where source_word='" . $GLOBALS["db"]->escape($word) . "' and sense='$sense'");
            if (isset($trlData["translated_word"])) {
                $translatedKeywords[] = $trlData["translated_word"];
            } else
                $translatedKeywords[] = $word;
        }
        // Update context keywords with translated words
        $contextKeywords = implode(" ", $translatedKeywords);
        error_log("[internalDumbTranslator] used <{$GLOBALS["LLM_LANG"]}-en.txt>. End: " . (microtime(true) - $localStartTime) . " seconds. <$inputSentence> <$contextKeywords>");
        return $contextKeywords;

    } else {
        error_log("[internalDumbTranslator] GLOBALS[LLM_LANG] is <{$GLOBALS["LLM_LANG"]}> " . (microtime(true) - $localStartTime) . " seconds");
        return $inputSentence;
    }
}

function internalDumbTranslator($inputSentence)
{
    if ((isset($GLOBALS["LLM_LANG"]) && $GLOBALS["LLM_LANG"] != "en") || true) {
        $localStartTime = microtime(true);

        $lang = isset($GLOBALS["LLM_LANG"]) ? $GLOBALS["LLM_LANG"] : "en";
        $sense = $lang . "-en";

        // Split sentence into words and clean them
        $words = array_map(function($word) {
            return str_replace(['?', '!', '.', '-', '¿', '¡', "'", ',', '_',',',')','('], ' ', $word);
        }, explode(" ", $inputSentence));

        if (empty($words)) {
            return $inputSentence;
        }

        // Build safe IN clause
        $escapedWords = array_map(function($w) { return "'" . $GLOBALS["db"]->escape($w) . "'"; }, $words);
        $inClause = implode(',', $escapedWords);

        // Fetch all translations in one query
        $translations = $GLOBALS["db"]->fetchAll(
            "SELECT  source_word,
            string_agg(translated_word, ', ') AS translated_word,
            string_agg(expansion, ', ') AS expansion 
            FROM translations WHERE source_word IN ($inClause) AND sense = '$sense' 
            GROUP BY source_word;
"
        );

        // Map translations by source_word
        $translationMap = [];
        foreach ($translations as $t) {
            $translationMap[$t['source_word']] = (isset($translationMap[$t['source_word']])?$translationMap[$t['source_word']]." ":"").$t['translated_word'];
        }

        // Build translated sentence
        $translatedKeywords = array_map(function($word) use ($translationMap) {
            return $translationMap[$word] ?? $word;
        }, $words);

        $contextKeywords = implode(" ", $translatedKeywords);

        error_log("[internalDumbTranslator] used <$sense>. End: " . (microtime(true) - $localStartTime) . " seconds. <$inputSentence> <$contextKeywords>");

        return $contextKeywords;
    } else {
        $localStartTime = microtime(true);
        error_log("[internalDumbTranslator] GLOBALS[LLM_LANG] is <{$GLOBALS["LLM_LANG"]}> " . (microtime(true) - $localStartTime) . " seconds");
        return $inputSentence;
    }
}