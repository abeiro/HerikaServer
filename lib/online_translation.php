<?php

class Translation {
    public static $response;
    public static $sentences;

    public static function isEnabled() {
        return isset($GLOBALS["TRANSLATION_FUNCTION"]) && $GLOBALS["TRANSLATION_FUNCTION"] != "none";
    }

    public static function isTextEnabled() {
        return self::isEnabled() && !self::isPlayerTTS() && $GLOBALS["TRANSLATION"]["settings"]["translate_text"];
    }

    public static function isAudioEnabled() {
        return self::isEnabled() && !self::isPlayerTTS() && $GLOBALS["TRANSLATION"]["settings"]["translate_audio"];
    }

    public static function isSaveTranslationEnabled() {
        return (self::isTextEnabled() || self::isAudioEnabled()) && $GLOBALS["TRANSLATION"]["settings"]["save_translated_text"];
    }

    public static function isPlayerAudioEnabled() {
        return self::isEnabled() && self::isPlayerTTS() && $GLOBALS["TRANSLATION"]["settings"]["translate_player_audio"];
    }

    public static function isSavePlayerTranslationEnabled() {
        return self::isPlayerAudioEnabled() && $GLOBALS["TRANSLATION"]["settings"]["save_translated_player_text"];
    }

    private static function isPlayerTTS() {
        return isset($GLOBALS["PATCH_OVERRIDE_VOICE"]);
    }

    public static function reset() {
        self::$response = null;
        self::$sentences = null;
    }

    // Normalize the sentence arrays to have the same number of elements
    public static function normalizeArrays(& $array1, & $array2) {
        if (count($array1) > count($array2)) {
            $smaller_array_size = count($array2);
            $max_smaller_index = $smaller_array_size - 1;

            // Append remaining elements of the larger array to the last element of the larger array
            for ($i = $smaller_array_size; $i < count($array1); $i++) {
                $array1[$max_smaller_index] .= " " . $array1[$i];
            }

            // Truncate the larger array to the size of the smaller array
            $array1 = array_slice($array1, 0, $smaller_array_size);

        } else if (count($array2) > count($array1)) {
            $smaller_array_size = count($array1);
            $max_smaller_index = $smaller_array_size - 1;

            // Append remaining elements of the larger array to the last element of the larger array
            for ($i = $smaller_array_size; $i < count($array2); $i++) {
                $array2[$max_smaller_index] .= " " . $array2[$i];
            }

            // Truncate the larger array to the size of the smaller array
            $array2 = array_slice($array2, 0, $smaller_array_size);
        }
    }

    public static function translate($message) {
        if (self::isTextEnabled() || self::isAudioEnabled() || self::isPlayerAudioEnabled()) {
            // remove character name from start of the message
            $message = preg_replace("/{$GLOBALS["HERIKA_NAME"]}\s*:\s*/", '', $message);

            // get translation from the selected service
            if ($GLOBALS["TRANSLATION_FUNCTION"] == "DeepL") {
                self::$response = self::getDeepLTranslation($message);
            }
        }
    }

    private static function getDeepLTranslation($message) {
        // Data to be sent in the POST request
        $context = "{$GLOBALS["HERIKA_NAME"]} is roleplaying in a game of Skyrim.\n";

        // disabled for now, as this is causing artifacts in the translation. Might revisit it later
        /*
        $historical = [];
        if (($GLOBALS["HERIKA_NAME"]=="The Narrator" || $GLOBALS["HERIKA_NAME"]=="Player")) {
            $historical = buildHistoricContext("", -5);
        } else {
            $historical = buildHistoricContext("{$GLOBALS["HERIKA_NAME"]}", -5);
        }
        $cnt = 0;
        foreach ($historical as $record) {
            if (isset($record["content"]) && $record["content"] && $cnt <= 5) {
                $context .= $record["content"]."\n";
                $cnt++;
            }
        }
        */

        $target_lang = self::isPlayerTTS() ? $GLOBALS["TRANSLATION"]["DeepL"]["player_target_language"] : $GLOBALS["TRANSLATION"]["DeepL"]["target_language"];
        $source_lang = self::isPlayerTTS() ? $GLOBALS["TRANSLATION"]["DeepL"]["player_source_language"] : $GLOBALS["TRANSLATION"]["DeepL"]["source_language"];
        $data = [
            'text' => [$message],
            'context' => $context,
            'target_lang' => $target_lang,
            'source_lang' => $source_lang
        ];
    
        // Convert data to JSON format
        $jsonData = json_encode($data);
    
        // Create headers
        // Resolve API key via API Badge (preferred). Fallback to config for backward compatibility.
        $deeplKey = '';
        try {
            if (!isset($GLOBALS['db']) || !$GLOBALS['db']) {
                if (isset($GLOBALS['DBDRIVER'])) { @require_once($GLOBALS['ENGINE_PATH'] . 'lib' . DIRECTORY_SEPARATOR . $GLOBALS['DBDRIVER'] . '.class.php'); }
                $GLOBALS['db'] = new sql();
            }
            $row = $GLOBALS['db']->fetchOne("SELECT api_key FROM core_api_badge WHERE lower(label)='deepl' LIMIT 1");
            if (is_array($row) && isset($row['api_key'])) { $deeplKey = (string)$row['api_key']; }
        } catch (Throwable $_e) { $deeplKey = ''; }
        if ($deeplKey === '' && isset($GLOBALS['TRANSLATION']['DeepL']['API_KEY'])) {
            $deeplKey = (string)$GLOBALS['TRANSLATION']['DeepL']['API_KEY'];
        }
        $options = [
            'http' => [
                'header' => [
                    'Authorization: DeepL-Auth-Key ' . $deeplKey,
                    'Content-Type: application/json',
                ],
                'method' => 'POST',
                'content' => $jsonData
            ]
        ];
    
        // Create a stream context with the options
        $context = stream_context_create($options);
    
        // Make the POST request
        $url = $GLOBALS["TRANSLATION"]["DeepL"]["url"];
        $response = file_get_contents($url, false, $context);
    
        // Handle errors (if any)
        if ($response === FALSE) {
            Logger::warn("DeepL translation failed.");
            return $message;
        }
    
        // Decode the JSON response
        $responseData = json_decode($response, true);
    
        // Return the translated text
        if (isset($responseData['translations'][0]['text'])) {
            Logger::info("DeepL translation|{$message}|{$responseData['translations'][0]['text']}");
            return $responseData['translations'][0]['text'];
        } else {
            Logger::warn("DeepL response did not contain a translation.");
            return $message;
        }
    }

    public static function containsCyrillic($string) {
        $pattern = '/[\p{Cyrillic}]/u';
        return preg_match($pattern, $string);
    }
    
    public static function convertCyrillicTextToLatin($cyrillicText) {
        return transliterator_transliterate('Russian-Latin/BGN', $cyrillicText);
    }
    
    public static function containsJapanese($string) {
        $pattern = '/[\p{Hiragana}\p{Katakana}\p{Han}]/u';
        return preg_match($pattern, $string);
    }
    
    public static function convertJapaneseTextToLatin($jpText) {
        if (!file_exists("/home/dwemer/kakasi/")) {
            Logger::warn("Error: could not convert Japanese to Romaji because Kakasi is not installed. Lip sync will not work.");
            return "";
        }
        $venvPath = "/home/dwemer/kakasi/kakasi_env/bin/python3";
        $scriptPath = "/home/dwemer/kakasi/convert_to_romaji.py";
    
        // Escape the Japanese text to avoid issues with special characters
        $escapedText = escapeshellarg($jpText);
    
        // Run the Python script using the virtual environment
        $command = "$venvPath $scriptPath $escapedText";
        $output = shell_exec($command);
        $romaji = trim($output);
        return $romaji;
    }
}

?>