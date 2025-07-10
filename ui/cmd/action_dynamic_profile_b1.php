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
        $OVERRIDES["DYNAMIC_PROMPT_RELATIONSHIPS"] = $GLOBALS["DYNAMIC_PROMPT_RELATIONSHIPS"] ?? '';
        $OVERRIDES["DYNAMIC_PROMPT_OCCUPATION"] = $GLOBALS["DYNAMIC_PROMPT_OCCUPATION"] ?? '';
        $OVERRIDES["DYNAMIC_PROMPT_SKILLS"] = $GLOBALS["DYNAMIC_PROMPT_SKILLS"] ?? '';
        $OVERRIDES["DYNAMIC_PROMPT_SPEECHSTYLE"] = $GLOBALS["DYNAMIC_PROMPT_SPEECHSTYLE"] ?? '';
        $OVERRIDES["DYNAMIC_PROMPT_GOALS"] = $GLOBALS["DYNAMIC_PROMPT_GOALS"] ?? '';

        if (file_exists($profile)) {
            require_once $profile;
        } else {
            Logger::info(__FILE__ . ". Using default profile because GET PROFILE NOT EXISTS");
        }
        $GLOBALS["CURRENT_CONNECTOR"] = DMgetCurrentModel();
        $GLOBALS["BOOK_EVENT_ALWAYS_NARRATOR"] = $OVERRIDES["BOOK_EVENT_ALWAYS_NARRATOR"];
        
        // Restore global dynamic prompt settings after loading character profile
        $GLOBALS["DYNAMIC_PROMPT_PERSONALITY"] = $OVERRIDES["DYNAMIC_PROMPT_PERSONALITY"];
        $GLOBALS["DYNAMIC_PROMPT_RELATIONSHIPS"] = $OVERRIDES["DYNAMIC_PROMPT_RELATIONSHIPS"];
        $GLOBALS["DYNAMIC_PROMPT_OCCUPATION"] = $OVERRIDES["DYNAMIC_PROMPT_OCCUPATION"];
        $GLOBALS["DYNAMIC_PROMPT_SKILLS"] = $OVERRIDES["DYNAMIC_PROMPT_SKILLS"];
        $GLOBALS["DYNAMIC_PROMPT_SPEECHSTYLE"] = $OVERRIDES["DYNAMIC_PROMPT_SPEECHSTYLE"];
        $GLOBALS["DYNAMIC_PROMPT_GOALS"] = $OVERRIDES["DYNAMIC_PROMPT_GOALS"];
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

        // Determine which fields to update
        $fieldsToUpdate = [];
        
        if (isset($jsonDataInput["DYNAMIC_PROFILE_FIELDS"]) && is_array($jsonDataInput["DYNAMIC_PROFILE_FIELDS"])) {
            $fieldsToUpdate = $jsonDataInput["DYNAMIC_PROFILE_FIELDS"];
        } elseif (isset($DYNAMIC_PROFILE_FIELDS) && is_array($DYNAMIC_PROFILE_FIELDS)) {
            $fieldsToUpdate = $DYNAMIC_PROFILE_FIELDS;
        } elseif (isset($GLOBALS["DYNAMIC_PROFILE_FIELDS"]) && is_array($GLOBALS["DYNAMIC_PROFILE_FIELDS"])) {
            $fieldsToUpdate = $GLOBALS["DYNAMIC_PROFILE_FIELDS"];
        } else {
            // Default fields if none configured
            $fieldsToUpdate = ["personality", "relationships"];
        }

        // Function to update a single field using the same logic as individual files
        function updateSingleField($field, $jsonDataInput, $enginePath) {
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
     
            foreach (json_decode(DataSpeechJournal($jsonDataInput["HERIKA_NAME"], $dynamicProfileContextHistory), true) as $element) {
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

            // Field mapping
            $fieldMapping = [
                'personality' => ['var' => 'HERIKA_PERSONALITY', 'prompt' => 'DYNAMIC_PROMPT_PERSONALITY', 'label' => 'personality traits'],
                'relationships' => ['var' => 'HERIKA_RELATIONSHIPS', 'prompt' => 'DYNAMIC_PROMPT_RELATIONSHIPS', 'label' => 'relationships'],
                'occupation' => ['var' => 'HERIKA_OCCUPATION', 'prompt' => 'DYNAMIC_PROMPT_OCCUPATION', 'label' => 'occupation and role'],
                'skills' => ['var' => 'HERIKA_SKILLS', 'prompt' => 'DYNAMIC_PROMPT_SKILLS', 'label' => 'skills and abilities'],
                'speechstyle' => ['var' => 'HERIKA_SPEECHSTYLE', 'prompt' => 'DYNAMIC_PROMPT_SPEECHSTYLE', 'label' => 'speech style'],
                'goals' => ['var' => 'HERIKA_GOALS', 'prompt' => 'DYNAMIC_PROMPT_GOALS', 'label' => 'goals and aspirations']
            ];

            if (!isset($fieldMapping[$field])) {
                return ["status" => "error", "message" => "Unknown field: {$field}"];
            }

            $varName = $fieldMapping[$field]['var'];
            $promptName = $fieldMapping[$field]['prompt'];
            $fieldLabel = $fieldMapping[$field]['label'];
            
            $currentValue = isset($jsonDataInput[$varName]) ? $jsonDataInput[$varName] : '';
            $updatePrompt = isset($GLOBALS[$promptName]) ? $GLOBALS[$promptName] : '';

            if (empty($updatePrompt)) {
                return ["status" => "error", "message" => "{$promptName} not configured"];
            }

            // Collect other profile fields for context (excluding the current field)
            $profileContext = [];
            $profileFields = [
                'HERIKA_PERS' => 'Basic Summary',
                'HERIKA_BACKGROUND' => 'Background',
                'HERIKA_PERSONALITY' => 'Personality Traits',
                'HERIKA_APPEARANCE' => 'Physical Appearance',
                'HERIKA_RELATIONSHIPS' => 'Relationships',
                'HERIKA_OCCUPATION' => 'Occupation & Role',
                'HERIKA_SKILLS' => 'Skills & Abilities',
                'HERIKA_SPEECHSTYLE' => 'Speech Style',
                'HERIKA_GOALS' => 'Goals & Aspirations'
            ];

            // Remove the current field from context
            unset($profileFields[$varName]);

            foreach ($profileFields as $fieldName => $fieldLabel) {
                if (isset($jsonDataInput[$fieldName]) && !empty(trim($jsonDataInput[$fieldName]))) {
                    $profileContext[] = "**{$fieldLabel}**: " . trim($jsonDataInput[$fieldName]);
                }
            }

            $profileContextString = !empty($profileContext) ? "\n\n* Current Character Profile:\n" . implode("\n\n", $profileContext) : '';

            // Get max tokens
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

            // Build prompt
            $head = [
                ["role" => "system", "content" => "You are an assistant. Analyze the dialogue history and character profile to update ONLY the {$fieldLabel} for the character named '{$jsonDataInput["HERIKA_NAME"]}'. Focus mostly on information about {$jsonDataInput["HERIKA_NAME"]} and ignore details about other characters mentioned in the dialogue."]
            ];

            $prompt = [
                ["role" => "user", "content" => "* Dialogue history:\n" . $historyData . ReplacePlayerNamePlaceholder($profileContextString)],
                ["role" => "user", "content" => "Character name: " . $jsonDataInput["HERIKA_NAME"] . "\nCurrent " . ucfirst($field) . ":\n" . ReplacePlayerNamePlaceholder($currentValue)],
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
                
                $profile = $jsonDataInput["profile"];
                $FOLLOWER_CONF = extract_assignments($profile);
                $FOLLOWER_CONF[$varName] = $buffer;
                
                if (write_php_assignments($FOLLOWER_CONF, $profile)) {
                    return [
                        "status" => "success", 
                        "message" => ucfirst($field) . " updated successfully!",
                        "updated_field" => $varName,
                        "new_value" => $buffer
                    ];
                } else {
                    return ["status" => "error", "message" => "Failed to save {$field} update to profile"];
                }
            } else {
                return ["status" => "error", "message" => "No {$field} update generated"];
            }
        }

        // Process each selected field
        $updatedFields = [];
        $failedFields = [];
        $successCount = 0;
        $results = [];

        foreach ($fieldsToUpdate as $field) {
            $result = updateSingleField($field, $jsonDataInput, $enginePath);
            
            if ($result["status"] === "success") {
                $updatedFields[] = $field;
                $results[$field] = $result;
                $successCount++;
            } else {
                $failedFields[] = $field . " (" . $result["message"] . ")";
            }
        }

        // Prepare final response
        $response = [
            "status" => $successCount > 0 ? "success" : "error",
            "updated_fields" => $updatedFields,
            "failed_fields" => $failedFields,
            "success_count" => $successCount,
            "total_requested" => count($fieldsToUpdate)
        ];

        if ($successCount > 0) {
            $response["message"] = "Successfully updated {$successCount} of " . count($fieldsToUpdate) . " field(s): " . implode(', ', $updatedFields);
            if (!empty($failedFields)) {
                $response["message"] .= ". Failed: " . implode(', ', $failedFields);
            }
        } else {
            $response["message"] = "No fields were updated. Failed: " . implode(', ', $failedFields);
        }

        $response["results"] = $results;
        
        echo json_encode($response);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Only POST method allowed"]);
}
?> 