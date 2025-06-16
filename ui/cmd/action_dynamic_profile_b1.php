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

        if (file_exists($profile)) {
            require_once $profile;
        } else {
            Logger::info(__FILE__ . ". Using default profile because GET PROFILE NOT EXISTS");
        }
        $GLOBALS["CURRENT_CONNECTOR"] = DMgetCurrentModel();
        $GLOBALS["BOOK_EVENT_ALWAYS_NARRATOR"] = $OVERRIDES["BOOK_EVENT_ALWAYS_NARRATOR"];
    } else {
        Logger::info(__FILE__ . ". Using default profile because NO GET PROFILE SPECIFIED");
        $GLOBALS["USING_DEFAULT_PROFILE"] = true;
    }
    $db = new sql();

    if (!$db) {
        die("DB error");
    }

    $FUNCTIONS_ARE_ENABLED = false;

    if (!isset($GLOBALS["CONNECTORS_DIARY"]) || !file_exists($enginePath . "connector" . DIRECTORY_SEPARATOR . "{$GLOBALS["CONNECTORS_DIARY"]}.php")) {
        die("{$GLOBALS["HERIKA_NAME"]}|AASPGQuestDialogue2Topic1B1Topic|I'm mindless. Choose a LLM model and connector." . PHP_EOL);
    } else {
        require $enginePath . "connector" . DIRECTORY_SEPARATOR . "{$GLOBALS["CONNECTORS_DIARY"]}.php";

        $historyData = "";
        $lastPlace = "";
        $lastListener = "";
        $lastDateTime = "";
 
        foreach (json_decode(DataSpeechJournal($jsonDataInput["HERIKA_NAME"], 100), true) as $element) {
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
                $lastDateTime = substr($element["sk_date"], 0, 15); //0201-11-23 16:29:43
            } else {
                $dateTime = "";
            }

            $historyData .= trim("{$element["speaker"]}:" . trim($element["speech"]) . " $listener $place $dateTime") . PHP_EOL;
        }
        if ($_GET["short"] == "yes") {
            $SHORT = "25 keywords";
            $SHORTER = "5 keywords";
            $REMINDER = "SHORT";
            $SUMMARIZE = ",AND SUMMARIZE INTO 250 TOKENS,";
        } else {
            $SHORT = "75 words";
            $SHORTER = "15 keywords";
            $REMINDER = "";
            $SUMMARIZE = " and summarize";
        }

        $partyConf = DataGetCurrentPartyConf();
        $partyConfA = json_decode($partyConf, true);
        Logger::debug($partyConf);

		// Check if we're using the new field-based system or legacy HERIKA_DYNAMIC
        // First check if it's in the JSON input, otherwise get it from the loaded profile
        $fieldsToUpdate = [];
        
        // Debug: Log all available variables
        Logger::debug("Manual dynamic profile update - Debug info:");
        Logger::debug("jsonDataInput keys: " . json_encode(array_keys($jsonDataInput)));
        Logger::debug("DYNAMIC_PROFILE_FIELDS isset: " . (isset($DYNAMIC_PROFILE_FIELDS) ? "yes" : "no"));
        Logger::debug("GLOBALS DYNAMIC_PROFILE_FIELDS isset: " . (isset($GLOBALS["DYNAMIC_PROFILE_FIELDS"]) ? "yes" : "no"));
        
        if (isset($jsonDataInput["DYNAMIC_PROFILE_FIELDS"]) && is_array($jsonDataInput["DYNAMIC_PROFILE_FIELDS"])) {
            $fieldsToUpdate = $jsonDataInput["DYNAMIC_PROFILE_FIELDS"];
            Logger::debug("Using fieldsToUpdate from jsonDataInput: " . json_encode($fieldsToUpdate));
        } elseif (isset($DYNAMIC_PROFILE_FIELDS) && is_array($DYNAMIC_PROFILE_FIELDS)) {
            $fieldsToUpdate = $DYNAMIC_PROFILE_FIELDS;
            Logger::debug("Using fieldsToUpdate from local DYNAMIC_PROFILE_FIELDS: " . json_encode($fieldsToUpdate));
        } elseif (isset($GLOBALS["DYNAMIC_PROFILE_FIELDS"]) && is_array($GLOBALS["DYNAMIC_PROFILE_FIELDS"])) {
            $fieldsToUpdate = $GLOBALS["DYNAMIC_PROFILE_FIELDS"];
            Logger::debug("Using fieldsToUpdate from GLOBALS: " . json_encode($fieldsToUpdate));
        } else {
            // Force use of default fields since user has configured the new system
            $fieldsToUpdate = ["personality", "relationships"];
            Logger::debug("No DYNAMIC_PROFILE_FIELDS found, using default: " . json_encode($fieldsToUpdate));
        }
        
        // Always use new field-based system (no legacy fallback)
        $useLegacyMode = false;
        Logger::debug("Using new field-based system with fields: " . json_encode($fieldsToUpdate));
        
        // New field-based system - update all selected fields
        $fieldMapping = [
            'personality' => ['var' => 'HERIKA_PERSONALITY', 'prompt' => 'DYNAMIC_PROMPT_PERSONALITY'],
            'relationships' => ['var' => 'HERIKA_REALTIONSHIPS', 'prompt' => 'DYNAMIC_PROMPT_RELATIONSHIPS'],
            'occupation' => ['var' => 'HERIKA_OCCUPATION', 'prompt' => 'DYNAMIC_PROMPT_OCCUPATION'],
            'skills' => ['var' => 'HERIKA_SKILLS', 'prompt' => 'DYNAMIC_PROMPT_SKILLS'],
            'speechstyle' => ['var' => 'HERIKA_SPEECHSTYLE', 'prompt' => 'DYNAMIC_PROMPT_SPEECHSTYLE'],
            'goals' => ['var' => 'HERIKA_GOALS', 'prompt' => 'DYNAMIC_PROMPT_GOALS']
        ];
        
        $updatedFields = [];
        $responseParsed = [];
        $successCount = 0;
        
        // Get max tokens for field updates (smaller than legacy)
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
        
        // Process each selected field
        foreach ($fieldsToUpdate as $field) {
            if (!isset($fieldMapping[$field])) {
                continue;
            }
            
            $varName = $fieldMapping[$field]['var'];
            $promptName = $fieldMapping[$field]['prompt'];
            $currentValue = isset($jsonDataInput[$varName]) ? $jsonDataInput[$varName] : '';
            $updatePrompt = isset($GLOBALS[$promptName]) ? $GLOBALS[$promptName] : '';
            
            if (empty($updatePrompt)) {
                continue; // Skip if no prompt configured
            }
            
            // Build prompt for this specific field
            $head = [
                ["role" => "system", "content" => "You are an assistant. Analyze the dialogue history and update the specific character profile field based on the information provided."]
            ];
            
            $prompt = [
                ["role" => "user", "content" => "* Dialogue history:\n" . $historyData],
                ["role" => "user", "content" => "Character name: " . $jsonDataInput["HERIKA_NAME"] . "\nCurrent " . ucfirst($field) . ":\n" . $currentValue],
                ["role" => "user", "content" => $updatePrompt]
            ];
            
            $contextData = array_merge($head, $prompt);
            
            // Process this field
            $connectionHandler = new $GLOBALS["CONNECTORS_DIARY"];
            $connectionHandler->open($contextData, ["max_tokens" => $maxTokens]);
            
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
                $updatedFields[$field] = $buffer;
                $responseParsed[$varName] = $buffer;
                $successCount++;
            }
        }
        
        // Save all updated fields to the profile file
        if (!empty($updatedFields)) {
            require_once($enginePath . "processor" . DIRECTORY_SEPARATOR . "comm.php");
            
            if (saveDynamicProfileUpdates($jsonDataInput["HERIKA_NAME"], $updatedFields, $db)) {
                $responseParsed["success"] = true;
                $responseParsed["updated_fields"] = array_keys($updatedFields);
                $responseParsed["message"] = "Successfully updated " . $successCount . " field(s): " . implode(', ', array_keys($updatedFields));
            } else {
                $responseParsed["success"] = false;
                $responseParsed["message"] = "Failed to save profile updates";
            }
        } else {
            $responseParsed["success"] = false;
            $responseParsed["message"] = "No fields were updated";
        }
        
        echo json_encode($responseParsed);
    }
}
?>
