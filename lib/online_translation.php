<?php

class Translation {
    public static $response;
    public static $sentences;

    public static function isEnabled() {
        return isset($GLOBALS["TRANSLATION_FUNCTION"]) && $GLOBALS["TRANSLATION_FUNCTION"] != "none";
    }

    public static function isTextEnabled() {
        return self::isEnabled() && $GLOBALS["TRANSLATION"][$GLOBALS["TRANSLATION_FUNCTION"]]["translate_text"];
    }

    public static function isAudioEnabled() {
        return self::isEnabled() && $GLOBALS["TRANSLATION"][$GLOBALS["TRANSLATION_FUNCTION"]]["translate_audio"];
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
        if (self::isTextEnabled() || self::isAudioEnabled()) {
            if ($GLOBALS["TRANSLATION_FUNCTION"] == "DeepL") {
                self::$response = self::getDeepLTranslation($message);
            }
        }
    }

    private static function getDeepLTranslation($message) {
        // Data to be sent in the POST request
        $data = [
            'text' => [$message],
            'target_lang' => $GLOBALS["TRANSLATION"]["DeepL"]["target_language"],
            'source_lang' => $GLOBALS["TRANSLATION"]["DeepL"]["source_language"]
        ];
    
        // Convert data to JSON format
        $jsonData = json_encode($data);
    
        // Create headers
        $options = [
            'http' => [
                'header' => [
                    'Authorization: DeepL-Auth-Key ' . $GLOBALS["TRANSLATION"]["DeepL"]["API_KEY"],
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
            return $responseData['translations'][0]['text'];
        } else {
            Logger::warn("DeepL response did not contain a translation.");
            return $message;
        }
    }
}

?>