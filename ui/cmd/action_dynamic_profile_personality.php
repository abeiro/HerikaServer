<?php
$method = $_SERVER["REQUEST_METHOD"];

if ($method === "POST") {
    // Read JSON data from the request
    $jsonDataInput = json_decode(file_get_contents("php://input"), true);
    $profile = $jsonDataInput["profile"];
    error_reporting(0);
    ini_set("display_errors", 0);
    $enginePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . "../../" . DIRECTORY_SEPARATOR;
    require_once $enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "utils_game_timestamp.php";
    require_once $enginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php";

    $FEATURES["MEMORY_EMBEDDING"]["ENABLED"] = false;

    if (isset($profile)) {
        $OVERRIDES["BOOK_EVENT_ALWAYS_NARRATOR"] = $GLOBALS["BOOK_EVENT_ALWAYS_NARRATOR"];
        
        // Preserve global dynamic prompt settings before loading character profile
        $OVERRIDES["DYNAMIC_PROMPT_PERSONALITY"] = $GLOBALS["DYNAMIC_PROMPT_PERSONALITY"] ?? '';

        if (file_exists($profile)) {
            require_once $profile;
        } else {
            Logger::info(__FILE__ . ". Using default profile because GET PROFILE NOT EXISTS");
        }
        $GLOBALS["CURRENT_CONNECTOR"] = DMgetCurrentModel();
        $GLOBALS["BOOK_EVENT_ALWAYS_NARRATOR"] = $OVERRIDES["BOOK_EVENT_ALWAYS_NARRATOR"];
        
        // Restore global dynamic prompt settings after loading character profile
        $GLOBALS["DYNAMIC_PROMPT_PERSONALITY"] = $OVERRIDES["DYNAMIC_PROMPT_PERSONALITY"];
    } else {
        Logger::info(__FILE__ . ". Using default profile because NO GET PROFILE SPECIFIED");
        $GLOBALS["USING_DEFAULT_PROFILE"] = true;
    }
    $db = new sql();

    if (!$db) {
        echo json_encode(["status" => "error", "message" => "Database connection failed"]);
        exit;
    }

    $FUNCTIONS_ARE_ENABLED = false;

    if (!isset($GLOBALS["CONNECTORS_DIARY"]) || !file_exists($enginePath . "connector" . DIRECTORY_SEPARATOR . "{$GLOBALS["CONNECTORS_DIARY"]}.php")) {
        echo json_encode(["status" => "error", "message" => "CONNECTORS_DIARY not configured properly"]);
        exit;
    } else {
        require $enginePath . "connector" . DIRECTORY_SEPARATOR . "{$GLOBALS["CONNECTORS_DIARY"]}.php";

        // Determine how much context history to use for dynamic profiles
        $dynamicProfileContextHistory = 50; // Default value
        if (isset($GLOBALS["CONTEXT_HISTORY_DYNAMIC_PROFILE"]) && $GLOBALS["CONTEXT_HISTORY_DYNAMIC_PROFILE"] > 0) {
            $dynamicProfileContextHistory = $GLOBALS["CONTEXT_HISTORY_DYNAMIC_PROFILE"];
        } elseif (isset($GLOBALS["CONTEXT_HISTORY"]) && $GLOBALS["CONTEXT_HISTORY"] > 0) {
            $dynamicProfileContextHistory = $GLOBALS["CONTEXT_HISTORY"];
        }

        $historyData = "";
        $lastPlace = "";
        $lastListener = "";
        $lastDateTime = "";
 
        // Debug: Check what DataSpeechJournal returns
        $journalData = DataSpeechJournal($jsonDataInput["HERIKA_NAME"], $dynamicProfileContextHistory);
        $journalArray = json_decode($journalData, true);
        
        if (empty($journalArray)) {
            echo json_encode(["status" => "error", "message" => "No dialogue history found for " . $jsonDataInput["HERIKA_NAME"] . ". Make sure the character has some conversation history."]);
            exit;
        }

        foreach ($journalArray as $element) {
            if ($element["listener"] == "The Narrator") {
                continue;
            }
            if ($lastListener != $element["listener"]) {
                $listener = " (talking to {$element["listener"]})";
                $lastListener = $element["listener"];
            } else {
                $listener = "";
            }

            if ($lastPlace != $element["location"]) {
                $place = " (at {$element["location"]})";
                $lastPlace = $element["location"];
            } else {
                $place = "";
            }

            if ($lastDateTime != substr($element["sk_date"], 0, 15)) {
                $date = substr($element["sk_date"], 0, 10);
                $time = substr($element["sk_date"], 11);
                $dateTime = "(on date {$date} at {$time})";
                $lastDateTime = substr($element["sk_date"], 0, 15);
            } else {
                $dateTime = "";
            }

            $historyData .= trim("{$element["speaker"]}:" . trim($element["speech"]) . " $listener $place $dateTime") . PHP_EOL;
        }
        
        // Additional check after processing
        if (empty(trim($historyData))) {
            echo json_encode(["status" => "error", "message" => "No usable dialogue history found for " . $jsonDataInput["HERIKA_NAME"] . " after filtering. Character may only have interactions with The Narrator."]);
            exit;
        }

        // Get max tokens for personality update
        $maxTokens = 800;
        switch($GLOBALS["CONNECTORS_DIARY"]) {
            case "openrouter":
                $maxTokens = isset($GLOBALS["CONNECTOR"]["openrouter"]["MAX_TOKENS_MEMORY"]) ? 
                    min($GLOBALS["CONNECTOR"]["openrouter"]["MAX_TOKENS_MEMORY"], 800) : $maxTokens;
                break;
            case "openai":
                $maxTokens = isset($GLOBALS["CONNECTOR"]["openai"]["MAX_TOKENS_MEMORY"]) ? 
                    min($GLOBALS["CONNECTOR"]["openai"]["MAX_TOKENS_MEMORY"], 800) : $maxTokens;
                break;
            case "google_openaijson":
                $maxTokens = isset($GLOBALS["CONNECTOR"]["google_openaijson"]["MAX_TOKENS_MEMORY"]) ? 
                    min($GLOBALS["CONNECTOR"]["google_openaijson"]["MAX_TOKENS_MEMORY"], 800) : $maxTokens;
                break;
            case "koboldcpp":
                $maxTokens = isset($GLOBALS["CONNECTOR"]["koboldcpp"]["MAX_TOKENS_MEMORY"]) ? 
                    min($GLOBALS["CONNECTOR"]["koboldcpp"]["MAX_TOKENS_MEMORY"], 800) : $maxTokens;
                break;
        }

        // Get current personality value and prompt
        $currentPersonality = isset($jsonDataInput["HERIKA_PERSONALITY"]) ? $jsonDataInput["HERIKA_PERSONALITY"] : '';
        $updatePrompt = isset($GLOBALS["DYNAMIC_PROMPT_PERSONALITY"]) ? $GLOBALS["DYNAMIC_PROMPT_PERSONALITY"] : '';

        if (empty($updatePrompt)) {
            echo json_encode(["status" => "error", "message" => "DYNAMIC_PROMPT_PERSONALITY not configured"]);
            exit;
        }

        // Collect other profile fields for context
        $profileContext = [];
        $profileFields = [
            'HERIKA_PERS' => 'Basic Summary',
            'HERIKA_BACKGROUND' => 'Background',
            'HERIKA_APPEARANCE' => 'Physical Appearance',
            'HERIKA_RELATIONSHIPS' => 'Relationships',
            'HERIKA_OCCUPATION' => 'Occupation & Role',
            'HERIKA_SKILLS' => 'Skills & Abilities',
            'HERIKA_SPEECHSTYLE' => 'Speech Style',
            'HERIKA_GOALS' => 'Goals & Aspirations'
        ];

        foreach ($profileFields as $fieldName => $fieldLabel) {
            if (isset($jsonDataInput[$fieldName]) && !empty(trim($jsonDataInput[$fieldName]))) {
                $profileContext[] = "**{$fieldLabel}**: " . trim($jsonDataInput[$fieldName]);
            }
        }

        $profileContextString = !empty($profileContext) ? "\n\n* Current Character Profile:\n" . implode("\n\n", $profileContext) : '';

        // Build prompt for personality update
        $head = [
            ["role" => "system", "content" => "You are an assistant. Analyze the dialogue history and character profile to update ONLY the personality traits for the character named '{$jsonDataInput["HERIKA_NAME"]}'. Focus mostly on information about {$jsonDataInput["HERIKA_NAME"]} and ignore details about other characters mentioned in the dialogue."]
        ];

        $prompt = [
            ["role" => "user", "content" => "* Dialogue history:\n" . $historyData . ReplacePlayerNamePlaceholder($profileContextString)],
            ["role" => "user", "content" => "Character name: " . $jsonDataInput["HERIKA_NAME"] . "\nCurrent Personality:\n" . ReplacePlayerNamePlaceholder($currentPersonality)],
            ["role" => "user", "content" => ReplacePlayerNamePlaceholder($updatePrompt)]
        ];

        $contextData = array_merge($head, $prompt);

        // Process with streaming connector
        $connectionHandler = new $GLOBALS["CONNECTORS_DIARY"];
        $connectionHandler->open($contextData, ["MAX_TOKENS" => $maxTokens]);

        $buffer = "";
        $breakFlag = false;
        while (true) {
            if ($breakFlag) {
                break;
            }

            if ($connectionHandler->isDone()) {
                $breakFlag = true;
            }

            $buffer .= $connectionHandler->process();
        }
        $connectionHandler->close();

        $buffer = trim($buffer);
        if (!empty($buffer)) {
            // Simple sanitization before saving
            $buffer = str_replace("\0", '', $buffer); // Remove null bytes
            $buffer = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $buffer); // Remove control chars
            $buffer = str_replace(['<?php', '<?', '?>'], ['&lt;?php', '&lt;?', '?&gt;'], $buffer); // Escape PHP tags
            
            $FOLLOWER_CONF = extract_assignments($profile);
            $FOLLOWER_CONF["HERIKA_PERSONALITY"] = $buffer;
            
            if (write_php_assignments($FOLLOWER_CONF, $profile)) {
                echo json_encode([
                    "status" => "success", 
                    "message" => "Personality updated successfully!",
                    "updated_field" => "HERIKA_PERSONALITY",
                    "new_value" => $buffer
                ]);
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to save personality update to profile"]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "No personality update generated"]);
        }
    }
} else {
    echo json_encode(["status" => "error", "message" => "Only POST method allowed"]);
}
?> 