<?php
// Function to process diary entries for all nearby NPCs (triggered by C++ with 400 unit range)
function processNearbyDiary($gameRequest, $eventType) {
    global $db;
    
    // Note: The C++ code handles the 400 unit range filtering and sends individual diary requests
    // This function processes the diary_nearby event type but won't actually be called for bulk processing
    // Individual diary requests will come through the normal diary handler
    
    Logger::info("DIARY_NEARBY: diary_nearby event received - C++ will handle individual NPCs within 400 units");
    
    // Just log that the nearby diary was triggered
    echo "The Narrator|rolecommand|DebugNotification@Checking for nearby NPCs within 400 units..." . PHP_EOL;
}

// Function to generate diary entry for a nearby NPC (similar to followers but for any NPC)
function generateNearbyDiary($npcName, $gameRequest, $eventType) {
    global $db;
    
    // Check if we have the diary connector configured
    if (!isset($GLOBALS["CONNECTORS_DIARY"]) || !file_exists(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CONNECTORS_DIARY"]}.php")) {
        Logger::info("DIARY_NEARBY: No diary connector configured for $npcName");
        return false;
    }
    
    // Temporarily switch context to this NPC
    $originalHerikaName = $GLOBALS["HERIKA_NAME"];
    $GLOBALS["HERIKA_NAME"] = $npcName;
    
    try {
        // Load NPC's profile if it exists
        $profileLoaded = false;
        $originalHerikaData = [];
        $NPC_CONF = [];
        
        // Try to load profile data for this NPC
        if (function_exists('getConfFileFor')) {
            $confFile = getConfFileFor($npcName);
            if (!empty($confFile) && file_exists($confFile)) {
                // Save original values for all extended fields
                $originalHerikaData = [
                    'HERIKA_PERS' => isset($GLOBALS["HERIKA_PERS"]) ? $GLOBALS["HERIKA_PERS"] : '',
                    'HERIKA_BACKGROUND' => isset($GLOBALS["HERIKA_BACKGROUND"]) ? $GLOBALS["HERIKA_BACKGROUND"] : '',
                    'HERIKA_PERSONALITY' => isset($GLOBALS["HERIKA_PERSONALITY"]) ? $GLOBALS["HERIKA_PERSONALITY"] : '',
                    'HERIKA_APPEARANCE' => isset($GLOBALS["HERIKA_APPEARANCE"]) ? $GLOBALS["HERIKA_APPEARANCE"] : '',
                    'HERIKA_RELATIONSHIPS' => isset($GLOBALS["HERIKA_RELATIONSHIPS"]) ? $GLOBALS["HERIKA_RELATIONSHIPS"] : '',
                    'HERIKA_OCCUPATION' => isset($GLOBALS["HERIKA_OCCUPATION"]) ? $GLOBALS["HERIKA_OCCUPATION"] : '',
                    'HERIKA_SKILLS' => isset($GLOBALS["HERIKA_SKILLS"]) ? $GLOBALS["HERIKA_SKILLS"] : '',
                    'HERIKA_SPEECHSTYLE' => isset($GLOBALS["HERIKA_SPEECHSTYLE"]) ? $GLOBALS["HERIKA_SPEECHSTYLE"] : '',
                    'HERIKA_GOALS' => isset($GLOBALS["HERIKA_GOALS"]) ? $GLOBALS["HERIKA_GOALS"] : '',
                    'HERIKA_DYNAMIC' => isset($GLOBALS["HERIKA_DYNAMIC"]) ? $GLOBALS["HERIKA_DYNAMIC"] : ''
                ];
                
                // Load NPC's profile
                $NPC_CONF = extract_assignments($confFile);
                $profileLoaded = true;
                Logger::info("DIARY_NEARBY: Loaded profile for $npcName");
            }
        }
        
        if (!$profileLoaded) {
            // Use default NPC personality if no specific profile exists
            $NPC_CONF = [
                "HERIKA_NAME" => $npcName,
                "PLAYER_NAME" => $GLOBALS["PLAYER_NAME"],
                "HERIKA_PERS" => "An NPC in the world of Skyrim.",
                "HERIKA_DYNAMIC" => "Currently encountered by " . $GLOBALS["PLAYER_NAME"] . ".",
                "PROMPT_HEAD" => isset($GLOBALS["PROMPT_HEAD"]) ? $GLOBALS["PROMPT_HEAD"] : "You are an NPC in the world of Skyrim.",
                "COMMAND_PROMPT" => isset($GLOBALS["COMMAND_PROMPT"]) ? $GLOBALS["COMMAND_PROMPT"] : "",
                "CONTEXT_HISTORY" => isset($GLOBALS["CONTEXT_HISTORY"]) ? $GLOBALS["CONTEXT_HISTORY"] : 25,
                "CONTEXT_HISTORY_DIARY" => isset($GLOBALS["CONTEXT_HISTORY_DIARY"]) ? $GLOBALS["CONTEXT_HISTORY_DIARY"] : 0,
                "CONNECTORS_DIARY" => $GLOBALS["CONNECTORS_DIARY"]
            ];
            Logger::info("DIARY_NEARBY: Using default profile for $npcName");
        }
        
        // Use centralized function from data_functions.php
        $dynamicBio = buildDynamicBiography($NPC_CONF);
        
        $head = [
            ["role" => "system", "content" => strtr(
                $NPC_CONF["PROMPT_HEAD"] . "\n" . $NPC_CONF["HERIKA_PERS"] . $dynamicBio . "\n" . $NPC_CONF["COMMAND_PROMPT"],
                ["#PLAYER_NAME#" => $NPC_CONF["PLAYER_NAME"]]
            )]
        ];
        
        // Use diary-specific context history if this is a diary request and CONTEXT_HISTORY_DIARY is set
        if (isset($NPC_CONF["CONTEXT_HISTORY_DIARY"]) && $NPC_CONF["CONTEXT_HISTORY_DIARY"] > 0) {
            $lastNDataForContext = $NPC_CONF["CONTEXT_HISTORY_DIARY"] + 0;
        } else {
            $lastNDataForContext = (isset($NPC_CONF["CONTEXT_HISTORY"])) ? ($NPC_CONF["CONTEXT_HISTORY"] + 0) : 25;
        }

        $sqlfilter = " and type<>'prechat'";
        $contextDataHistoric = DataLastDataExpandedFor("{$NPC_CONF["HERIKA_NAME"]}", $lastNDataForContext * -1, $sqlfilter);
        $historyData = "";
        foreach ($contextDataHistoric as $element) {
            $historyData .= trim("{$element["content"]}") . PHP_EOL . PHP_EOL;
        }

        // Build user prompt for diary generation (like regular diary)
        $prompt = [];
        if (!empty($contextDataHistoric)) {
            $prompt[] = ["role" => "user", "content" => "Recent context: " . $historyData];
        }

        $diaryPrompt = strtr($GLOBALS["DIARY_PROMPT"], ['{$GLOBALS["HERIKA_NAME"]}'=>$npcName,'{$GLOBALS["PLAYER_NAME"]}'=>$NPC_CONF["PLAYER_NAME"]]);
        $prompt[] = ["role" => "user", "content" => $diaryPrompt];

        $contextData = array_merge($head, $prompt);
        
        // Set the request type for diary so connector knows to use diary grammar
        $originalGameRequest = isset($GLOBALS["gameRequest"]) ? $GLOBALS["gameRequest"] : null;
        $GLOBALS["gameRequest"] = [0 => "diary", 1 => time(), 2 => $gameRequest[2], 3 => "Auto diary for " . $npcName];
        
        // Generate diary entry using LLM
        require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."connector".DIRECTORY_SEPARATOR."{$NPC_CONF["CONNECTORS_DIARY"]}.php");
        
        $connectionHandler = new $NPC_CONF["CONNECTORS_DIARY"];
        $maxTokens = isset($GLOBALS["CONNECTOR"][$NPC_CONF["CONNECTORS_DIARY"]]["MAX_TOKENS_MEMORY"]) 
            ? $GLOBALS["CONNECTOR"][$NPC_CONF["CONNECTORS_DIARY"]]["MAX_TOKENS_MEMORY"] 
            : 1500;
            
        $connectionHandler->open($contextData, ["max_tokens" => $maxTokens]);
        
        $buffer = "";
        $totalBuffer = "";
        $breakFlag = false;
        
        while (true) {
            if ($breakFlag) {
                break;
            }
            
            if ($connectionHandler->isDone()) {
                $breakFlag = true;
            }
            
            $buffer .= $connectionHandler->process();
            $totalBuffer .= $buffer;
        }
        
        $connectionHandler->close();
        
        // Restore original gameRequest after diary generation
        if ($originalGameRequest !== null) {
            $GLOBALS["gameRequest"] = $originalGameRequest;
        } else {
            unset($GLOBALS["gameRequest"]);
        }
        
        if (!empty(trim($buffer))) {
            // Save diary entry to database
            $topic = DataLastKnowDate();
            $location = DataLastKnownLocation();
            
            $db->insert(
                'diarylog',
                array(
                    'ts' => $gameRequest[1],
                    'gamets' => $gameRequest[2],
                    'topic' => $topic . " (Nearby diary: $eventType)",
                    'content' => trim($buffer),
                    'tags' => "Nearby-diary,$eventType",
                    'people' => $npcName,
                    'location' => $location,
                    'sess' => 'pending',
                    'localts' => time()
                )
            );
            
            // Log memory
            if (function_exists('logMemory')) {
                logMemory($npcName, $npcName, trim($buffer), time(), $gameRequest[2], 'nearby_diary', $gameRequest[1]);
            }
            
            return true;
        }
        
    } catch (Exception $e) {
        Logger::error("DIARY_NEARBY: Error generating diary for $npcName: " . $e->getMessage());
    } finally {
        // Restore original context
        $GLOBALS["HERIKA_NAME"] = $originalHerikaName;
        
        // Restore original profile data if we loaded an NPC profile
        if (!empty($originalHerikaData)) {
            $GLOBALS["HERIKA_PERS"] = $originalHerikaData['HERIKA_PERS'];
            $GLOBALS["HERIKA_BACKGROUND"] = $originalHerikaData['HERIKA_BACKGROUND'];
            $GLOBALS["HERIKA_PERSONALITY"] = $originalHerikaData['HERIKA_PERSONALITY'];
            $GLOBALS["HERIKA_APPEARANCE"] = $originalHerikaData['HERIKA_APPEARANCE'];
            $GLOBALS["HERIKA_RELATIONSHIPS"] = $originalHerikaData['HERIKA_RELATIONSHIPS'];
            $GLOBALS["HERIKA_OCCUPATION"] = $originalHerikaData['HERIKA_OCCUPATION'];
            $GLOBALS["HERIKA_SKILLS"] = $originalHerikaData['HERIKA_SKILLS'];
            $GLOBALS["HERIKA_SPEECHSTYLE"] = $originalHerikaData['HERIKA_SPEECHSTYLE'];
            $GLOBALS["HERIKA_GOALS"] = $originalHerikaData['HERIKA_GOALS'];
            $GLOBALS["HERIKA_DYNAMIC"] = $originalHerikaData['HERIKA_DYNAMIC'];
        }
    }
    
    return false;
}

// Function to process AUTO_DIARY for all current followers
function processAutoDiary($gameRequest, $eventType) {
    global $db;
    
    // Get current party data
    $partyConf = DataGetCurrentPartyConf();
    if (empty($partyConf)) {
        Logger::info("AUTO_DIARY: No current party data found");
        return;
    }
    
    Logger::debug("AUTO_DIARY: Raw party data: " . $partyConf);
    
    // Parse party data
    $currentParty = json_decode($partyConf, true);
    if (!is_array($currentParty) || empty($currentParty)) {
        Logger::info("AUTO_DIARY: Failed to parse party data or party is empty. Data was: " . $partyConf);
        return;
    }
    
    $processedCount = 0;
    $generatedCount = 0;
    $diaryCooldownPeriod = isset($GLOBALS["DIARY_COOLDOWN"]) ? intval($GLOBALS["DIARY_COOLDOWN"]) : 30;
    
    Logger::info("AUTO_DIARY: Processing $eventType event for " . count($currentParty) . " followers");
    
    foreach ($currentParty as $followerName => $followerData) {
        if (empty($followerName) || !isset($followerData["name"])) {
            continue;
        }
        
        $processedCount++;
        
        // Check diary cooldown for this specific follower
        $npcName = preg_replace('/[^a-zA-Z0-9_]/', '_', $followerName);
        $cooldownKey = "DIARY_LAST_TIMESTAMP_" . $npcName;
        
        $diaryRecord = $db->fetchAll("SELECT value FROM conf_opts WHERE id='" . $db->escape($cooldownKey) . "'");
        
        if (!empty($diaryRecord)) {
            $lastTrigger = (int) $diaryRecord[0]['value'];
            $timeElapsed = time() - $lastTrigger;

            if ($timeElapsed < $diaryCooldownPeriod) {
                Logger::info("AUTO_DIARY: Skipping $followerName (cooldown active: " . ($diaryCooldownPeriod - $timeElapsed) . " seconds remaining)");
                continue;
            }
        }
        
        // Update cooldown timestamp for this follower
        $db->upsertRowOnConflict(
            'conf_opts',
            array(
                'id' => $cooldownKey,
                'value' => time()
            ),
            "id"
        );
        
        // Generate diary entry for this follower
        if (generateFollowerDiary($followerName, $gameRequest, $eventType)) {
            $generatedCount++;
            Logger::info("AUTO_DIARY: Generated diary entry for $followerName");
        } else {
            Logger::info("AUTO_DIARY: Failed to generate diary entry for $followerName");
        }
    }
    
}

// Function to process a single NPC's dynamic profile
function processSingleDynamicProfile($npcName, $gameRequest) {
    global $db;
    
    // Ensure required dependencies are loaded
    if (!function_exists('DataSpeechJournal')) {
        require_once(__DIR__ . "/../lib/data_functions.php");
    }
    if (!function_exists('buildDynamicProfileDisplay')) {
        require_once(__DIR__ . "/../lib/model_dynmodel.php");
    }
    
    // Skip The Narrator
    if ($npcName === "The Narrator") {
        Logger::debug("processSingleDynamicProfile: Skipping The Narrator");
        return false;
    }
    
    // Check if profile exists for this NPC
    $profilePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "conf" . DIRECTORY_SEPARATOR . "conf_" . md5($npcName) . ".php";
    if (!file_exists($profilePath)) {
        Logger::debug("processSingleDynamicProfile: No profile found for $npcName");
        return false;
    }
    
    // Load the NPC's profile to check if DYNAMIC_PROFILE is enabled
    $originalGlobals = $GLOBALS;
    
    try {
        // Include the NPC's profile
        include($profilePath);
        
        // After loading character profile, ensure we use global dynamic prompts from main conf.php
        // Character profiles should not override these global prompt settings
        // BUT preserve character-specific DYNAMIC_PROFILE setting
        $characterDynamicProfile = isset($DYNAMIC_PROFILE) ? $DYNAMIC_PROFILE : false;
        $characterDynamicProfileFields = isset($DYNAMIC_PROFILE_FIELDS) ? $DYNAMIC_PROFILE_FIELDS : [];
        
        $mainConfPath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "conf" . DIRECTORY_SEPARATOR . "conf.php";
        if (file_exists($mainConfPath)) {
            // Save current state before loading main config
            $tempGlobals = $GLOBALS;
            include($mainConfPath); // This will load the global prompts
            
            // Extract only the DYNAMIC_PROMPT_* variables from main config
            $globalPrompts = [
                'DYNAMIC_PROMPT_PERSONALITY' => $DYNAMIC_PROMPT_PERSONALITY ?? '',
                'DYNAMIC_PROMPT_RELATIONSHIPS' => $DYNAMIC_PROMPT_RELATIONSHIPS ?? '',
                'DYNAMIC_PROMPT_OCCUPATION' => $DYNAMIC_PROMPT_OCCUPATION ?? '',
                'DYNAMIC_PROMPT_SKILLS' => $DYNAMIC_PROMPT_SKILLS ?? '',
                'DYNAMIC_PROMPT_SPEECHSTYLE' => $DYNAMIC_PROMPT_SPEECHSTYLE ?? '',
                'DYNAMIC_PROMPT_GOALS' => $DYNAMIC_PROMPT_GOALS ?? ''
            ];
            
            // Restore character-specific globals but override with global prompts
            foreach ($tempGlobals as $globalKey => $globalValue) {
                $GLOBALS[$globalKey] = $globalValue;
            }
            foreach ($globalPrompts as $key => $value) {
                if (!empty($value)) {
                    $GLOBALS[$key] = $value;
                }
            }
            
            // Restore character-specific DYNAMIC_PROFILE settings
            $DYNAMIC_PROFILE = $characterDynamicProfile;
            $GLOBALS['DYNAMIC_PROFILE'] = $characterDynamicProfile;
            if (!empty($characterDynamicProfileFields)) {
                $DYNAMIC_PROFILE_FIELDS = $characterDynamicProfileFields;
                $GLOBALS['DYNAMIC_PROFILE_FIELDS'] = $characterDynamicProfileFields;
            }
        }
        
        // Check if DYNAMIC_PROFILE is enabled for this NPC
        if (!isset($DYNAMIC_PROFILE) || !$DYNAMIC_PROFILE) {
            Logger::debug("processSingleDynamicProfile: DYNAMIC_PROFILE disabled for $npcName");
            return false;
        }
        
        // Check if diary connector is configured
        if (!isset($GLOBALS["CONNECTORS_DIARY"]) || !file_exists(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CONNECTORS_DIARY"]}.php")) {
            Logger::debug("processSingleDynamicProfile: No diary connector configured for $npcName");
            return false;
        }
        
        // Get dynamic profile fields to update
        $fieldsToUpdate = isset($DYNAMIC_PROFILE_FIELDS) && is_array($DYNAMIC_PROFILE_FIELDS) 
            ? $DYNAMIC_PROFILE_FIELDS 
            : ["personality", "relationships"]; // Default fields
        
        if (empty($fieldsToUpdate)) {
            Logger::debug("processSingleDynamicProfile: No fields selected for dynamic updates for $npcName");
            return false;
        }
        
        // Set context for this NPC
        $GLOBALS["HERIKA_NAME"] = $npcName;
        
        // Process each selected field
        require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CONNECTORS_DIARY"]}.php");
        
        $historyData = getDynamicProfileHistoryData($npcName);
        $updatedFields = [];
        $successCount = 0;
        
        foreach ($fieldsToUpdate as $field) {
            $result = updateDynamicProfileField($npcName, $field, $historyData);

            if ($field=="skills") {
                $skillsData=getInGameSkillDataFor($npcName);
                $result.="\n$skillsData";
            }

            if ($result !== false) {
                $updatedFields[$field] = $result;
                $successCount++;
            }
        }
        
        if ($successCount > 0) {
            // Save the updated profile
            $success = saveDynamicProfileUpdates($npcName, $updatedFields, $db);
            if ($success) {
                Logger::info("processSingleDynamicProfile: Successfully updated $successCount fields for $npcName: " . implode(', ', array_keys($updatedFields)));
                return true;
            }
        }
        
    } catch (Exception $e) {
        Logger::error("processSingleDynamicProfile: Error processing $npcName: " . $e->getMessage());
        return false;
    } finally {
        // Restore original globals
        foreach ($originalGlobals as $key => $value) {
            $GLOBALS[$key] = $value;
        }
    }
    
    return false;
}

// Function to generate diary entry for a specific follower
function generateFollowerDiary($followerName, $gameRequest, $eventType) {
    global $db;
    
    // Check if we have the diary connector configured
    if (!isset($GLOBALS["CONNECTORS_DIARY"]) || !file_exists(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."connector".DIRECTORY_SEPARATOR."{$GLOBALS["CONNECTORS_DIARY"]}.php")) {
        Logger::info("AUTO_DIARY: No diary connector configured for $followerName");
        return false;
    }
    
    // Temporarily switch context to this follower
    $originalHerikaName = $GLOBALS["HERIKA_NAME"];
    $GLOBALS["HERIKA_NAME"] = $followerName;
    
    try {
        // Load follower's profile if it exists
        $profileLoaded = false;
        $originalHerikaData = [];
        $FOLLOWER_CONF = [];
        
        // Try to load profile data for this follower
        if (function_exists('getConfFileFor')) {
            $confFile = getConfFileFor($followerName);
            if (!empty($confFile) && file_exists($confFile)) {
                // Save original values for all extended fields
                $originalHerikaData = [
                    'HERIKA_PERS' => isset($GLOBALS["HERIKA_PERS"]) ? $GLOBALS["HERIKA_PERS"] : '',
                    'HERIKA_BACKGROUND' => isset($GLOBALS["HERIKA_BACKGROUND"]) ? $GLOBALS["HERIKA_BACKGROUND"] : '',
                    'HERIKA_PERSONALITY' => isset($GLOBALS["HERIKA_PERSONALITY"]) ? $GLOBALS["HERIKA_PERSONALITY"] : '',
                    'HERIKA_APPEARANCE' => isset($GLOBALS["HERIKA_APPEARANCE"]) ? $GLOBALS["HERIKA_APPEARANCE"] : '',
                    'HERIKA_RELATIONSHIPS' => isset($GLOBALS["HERIKA_RELATIONSHIPS"]) ? $GLOBALS["HERIKA_RELATIONSHIPS"] : '',
                    'HERIKA_OCCUPATION' => isset($GLOBALS["HERIKA_OCCUPATION"]) ? $GLOBALS["HERIKA_OCCUPATION"] : '',
                    'HERIKA_SKILLS' => isset($GLOBALS["HERIKA_SKILLS"]) ? $GLOBALS["HERIKA_SKILLS"] : '',
                    'HERIKA_SPEECHSTYLE' => isset($GLOBALS["HERIKA_SPEECHSTYLE"]) ? $GLOBALS["HERIKA_SPEECHSTYLE"] : '',
                    'HERIKA_GOALS' => isset($GLOBALS["HERIKA_GOALS"]) ? $GLOBALS["HERIKA_GOALS"] : '',
                    'HERIKA_DYNAMIC' => isset($GLOBALS["HERIKA_DYNAMIC"]) ? $GLOBALS["HERIKA_DYNAMIC"] : ''
                ];
                
                // Load follower's profile
                $FOLLOWER_CONF = extract_assignments($confFile);
                $profileLoaded = true;
                Logger::info("AUTO_DIARY: Loaded profile for $followerName");
            }
        }
        
        if (!$profileLoaded) {
            // Create default follower configuration array if no specific profile exists
            $FOLLOWER_CONF = [
                "HERIKA_NAME" => $followerName,
                "PLAYER_NAME" => $GLOBALS["PLAYER_NAME"],
                "HERIKA_PERS" => "A loyal companion and follower of " . $GLOBALS["PLAYER_NAME"] . ".",
                "HERIKA_BACKGROUND" => "A trusted companion who has joined " . $GLOBALS["PLAYER_NAME"] . " on their adventures through Skyrim.",
                "HERIKA_PERSONALITY" => "Loyal, brave, and dependable. Shows dedication to their companions and faces challenges with determination.",
                "HERIKA_APPEARANCE" => "A capable-looking adventurer equipped for the dangers of Skyrim.",
                "HERIKA_RELATIONSHIPS" => "Close companion and trusted ally of " . $GLOBALS["PLAYER_NAME"] . ". Values friendship and loyalty above all else.",
                "HERIKA_OCCUPATION" => "Adventurer and companion, skilled in combat and survival.",
                "HERIKA_SKILLS" => "Proficient in combat, survival skills, and supporting allies in dangerous situations.",
                "HERIKA_SPEECHSTYLE" => "Speaks with loyalty and respect, often showing concern for companions' wellbeing.",
                "HERIKA_GOALS" => "To support " . $GLOBALS["PLAYER_NAME"] . " in their adventures and protect innocent people from harm.",
                "PROMPT_HEAD" => isset($GLOBALS["PROMPT_HEAD"]) ? $GLOBALS["PROMPT_HEAD"] : "You are a companion in the world of Skyrim.",
                "COMMAND_PROMPT" => isset($GLOBALS["COMMAND_PROMPT"]) ? $GLOBALS["COMMAND_PROMPT"] : "",
                "CONTEXT_HISTORY" => isset($GLOBALS["CONTEXT_HISTORY"]) ? $GLOBALS["CONTEXT_HISTORY"] : 25,
                "CONTEXT_HISTORY_DIARY" => isset($GLOBALS["CONTEXT_HISTORY_DIARY"]) ? $GLOBALS["CONTEXT_HISTORY_DIARY"] : 0,
                "CONNECTORS_DIARY" => $GLOBALS["CONNECTORS_DIARY"],
                "CONNECTOR" => isset($GLOBALS["CONNECTOR"]) ? $GLOBALS["CONNECTOR"] : []
            ];
            Logger::info("AUTO_DIARY: Using default configuration for $followerName");
        } else {
            // Ensure required fields exist in loaded configuration with fallbacks
            if (!isset($FOLLOWER_CONF["HERIKA_NAME"])) {
                $FOLLOWER_CONF["HERIKA_NAME"] = $followerName;
            }
            if (!isset($FOLLOWER_CONF["PLAYER_NAME"])) {
                $FOLLOWER_CONF["PLAYER_NAME"] = $GLOBALS["PLAYER_NAME"];
            }
            if (!isset($FOLLOWER_CONF["CONNECTORS_DIARY"])) {
                $FOLLOWER_CONF["CONNECTORS_DIARY"] = $GLOBALS["CONNECTORS_DIARY"];
            }
            if (!isset($FOLLOWER_CONF["CONNECTOR"])) {
                $FOLLOWER_CONF["CONNECTOR"] = isset($GLOBALS["CONNECTOR"]) ? $GLOBALS["CONNECTOR"] : [];
            }
            if (!isset($FOLLOWER_CONF["CONTEXT_HISTORY"])) {
                $FOLLOWER_CONF["CONTEXT_HISTORY"] = isset($GLOBALS["CONTEXT_HISTORY"]) ? $GLOBALS["CONTEXT_HISTORY"] : 25;
            }
            if (!isset($FOLLOWER_CONF["CONTEXT_HISTORY_DIARY"])) {
                $FOLLOWER_CONF["CONTEXT_HISTORY_DIARY"] = isset($GLOBALS["CONTEXT_HISTORY_DIARY"]) ? $GLOBALS["CONTEXT_HISTORY_DIARY"] : 0;
            }
            // Ensure extended profile fields have fallbacks if they don't exist
            if (!isset($FOLLOWER_CONF["HERIKA_BACKGROUND"])) {
                $FOLLOWER_CONF["HERIKA_BACKGROUND"] = "";
            }
            if (!isset($FOLLOWER_CONF["HERIKA_PERSONALITY"])) {
                $FOLLOWER_CONF["HERIKA_PERSONALITY"] = "";
            }
            if (!isset($FOLLOWER_CONF["HERIKA_APPEARANCE"])) {
                $FOLLOWER_CONF["HERIKA_APPEARANCE"] = "";
            }
            if (!isset($FOLLOWER_CONF["HERIKA_RELATIONSHIPS"])) {
                $FOLLOWER_CONF["HERIKA_RELATIONSHIPS"] = "";
            }
            if (!isset($FOLLOWER_CONF["HERIKA_OCCUPATION"])) {
                $FOLLOWER_CONF["HERIKA_OCCUPATION"] = "";
            }
            if (!isset($FOLLOWER_CONF["HERIKA_SKILLS"])) {
                $FOLLOWER_CONF["HERIKA_SKILLS"] = "";
            }
            if (!isset($FOLLOWER_CONF["HERIKA_SPEECHSTYLE"])) {
                $FOLLOWER_CONF["HERIKA_SPEECHSTYLE"] = "";
            }
            if (!isset($FOLLOWER_CONF["HERIKA_GOALS"])) {
                $FOLLOWER_CONF["HERIKA_GOALS"] = "";
            }
        }
        
        // Use the same prompt system as regular diary entries
        // Build standard system prompt like main.php does
        
        // Use centralized function from data_functions.php
        $dynamicBio = buildDynamicBiography($FOLLOWER_CONF);
        
        $head = [
            ["role" => "system", "content" => strtr(
                $FOLLOWER_CONF["PROMPT_HEAD"] . "\n" . $FOLLOWER_CONF["HERIKA_PERS"] . $dynamicBio . "\n" . $FOLLOWER_CONF["COMMAND_PROMPT"],
                ["#PLAYER_NAME#" => $FOLLOWER_CONF["PLAYER_NAME"]]
            )]
        ];
        
        // Use diary-specific context history if this is a diary request and CONTEXT_HISTORY_DIARY is set
        if (isset($FOLLOWER_CONF["CONTEXT_HISTORY_DIARY"]) && $FOLLOWER_CONF["CONTEXT_HISTORY_DIARY"] > 0) {
            $lastNDataForContext = $FOLLOWER_CONF["CONTEXT_HISTORY_DIARY"]+0;
        } else {
            $lastNDataForContext = (isset($FOLLOWER_CONF["CONTEXT_HISTORY"])) ? ($FOLLOWER_CONF["CONTEXT_HISTORY"]+0) : 25;
        }

        $sqlfilter=" and type<>'prechat'";
        $contextDataHistoric = DataLastDataExpandedFor("{$FOLLOWER_CONF["HERIKA_NAME"]}", $lastNDataForContext * -1,$sqlfilter);
        $historyData="";
        foreach ($contextDataHistoric as $element) {
        
            $historyData.=trim("{$element["content"]}").PHP_EOL.PHP_EOL;
            
        }


        // Build user prompt for diary generation (like regular diary)
       
        if (!empty($contextDataHistoric)) {
            $prompt[] = ["role" => "user", "content" => "Recent context: " . $historyData];
        }

        $diaryPrompt=strtr($GLOBALS["DIARY_PROMPT"],['{$GLOBALS["HERIKA_NAME"]}'=>$followerName,'{$GLOBALS["PLAYER_NAME"]}'=>$FOLLOWER_CONF["PLAYER_NAME"]]);

        $prompt[] = 
            ["role" => "user", "content" => $diaryPrompt
            ]
        ;
        

        $contextData = array_merge($head, $prompt);
        
        // Set the request type for diary so connector knows to use diary grammar
        $originalGameRequest = isset($GLOBALS["gameRequest"]) ? $GLOBALS["gameRequest"] : null;
        $GLOBALS["gameRequest"] = [0 => "diary", 1 => time(), 2 => $gameRequest[2], 3 => "Auto diary for " . $followerName];
        
        // Generate diary entry using LLM
        require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."connector".DIRECTORY_SEPARATOR."{$FOLLOWER_CONF["CONNECTORS_DIARY"]}.php");
        
        $connectionHandler = new $FOLLOWER_CONF["CONNECTORS_DIARY"];
        $maxTokens = isset($FOLLOWER_CONF["CONNECTOR"][$FOLLOWER_CONF["CONNECTORS_DIARY"]]["MAX_TOKENS_MEMORY"]) 
            ? $FOLLOWER_CONF["CONNECTOR"][$FOLLOWER_CONF["CONNECTORS_DIARY"]]["MAX_TOKENS_MEMORY"] 
            : 1500;
            
        $connectionHandler->open($contextData, ["max_tokens" => $maxTokens]);
        
        $buffer = "";
        $totalBuffer = "";
        $breakFlag = false;
        
        while (true) {
            if ($breakFlag) {
                break;
            }
            
            if ($connectionHandler->isDone()) {
                $breakFlag = true;
            }
            
            $buffer .= $connectionHandler->process();
            $totalBuffer .= $buffer;
        }
        
        $connectionHandler->close();
        
        // Restore original gameRequest after diary generation
        if ($originalGameRequest !== null) {
            $GLOBALS["gameRequest"] = $originalGameRequest;
        } else {
            unset($GLOBALS["gameRequest"]);
        }
        
        if (!empty(trim($buffer))) {
            // Save diary entry to database
            $topic = DataLastKnowDate();
            $location = DataLastKnownLocation();
            
            $db->insert(
                'diarylog',
                array(
                    'ts' => $gameRequest[1],
                    'gamets' => $gameRequest[2],
                    'topic' => $topic . " (Auto-diary: $eventType)",
                    'content' => trim($buffer),
                    'tags' => "Auto-diary,$eventType",
                    'people' => $followerName,
                    'location' => $location,
                    'sess' => 'pending',
                    'localts' => time()
                )
            );
            
            // Log memory
            if (function_exists('logMemory')) {
                logMemory($followerName, $followerName, trim($buffer), time(), $gameRequest[2], 'auto_diary', $gameRequest[1]);
            }
            
            // Send notification to plugin for this follower (same format as manual diary)
            echo $followerName."|rolecommand|DebugNotification@Diary Entry Written for ".$followerName.PHP_EOL;
            @ob_flush();
            @flush();
            
            return true;
        }
        
    } catch (Exception $e) {
        Logger::error("AUTO_DIARY: Error generating diary for $followerName: " . $e->getMessage());
    } finally {
        // Restore original context
        $GLOBALS["HERIKA_NAME"] = $originalHerikaName;
        
        // Restore original profile data if we loaded a follower profile
        if (!empty($originalHerikaData)) {
            $GLOBALS["HERIKA_PERS"] = $originalHerikaData['HERIKA_PERS'];
            $GLOBALS["HERIKA_BACKGROUND"] = $originalHerikaData['HERIKA_BACKGROUND'];
            $GLOBALS["HERIKA_PERSONALITY"] = $originalHerikaData['HERIKA_PERSONALITY'];
            $GLOBALS["HERIKA_APPEARANCE"] = $originalHerikaData['HERIKA_APPEARANCE'];
            $GLOBALS["HERIKA_RELATIONSHIPS"] = $originalHerikaData['HERIKA_RELATIONSHIPS'];
            $GLOBALS["HERIKA_OCCUPATION"] = $originalHerikaData['HERIKA_OCCUPATION'];
            $GLOBALS["HERIKA_SKILLS"] = $originalHerikaData['HERIKA_SKILLS'];
            $GLOBALS["HERIKA_SPEECHSTYLE"] = $originalHerikaData['HERIKA_SPEECHSTYLE'];
            $GLOBALS["HERIKA_GOALS"] = $originalHerikaData['HERIKA_GOALS'];
            $GLOBALS["HERIKA_DYNAMIC"] = $originalHerikaData['HERIKA_DYNAMIC'];
        }
    }
    
    return false;
}

function getDynamicProfileHistoryData($npcName) {
    $historyData = "";
    $lastPlace = "";
    $lastListener = "";
    $lastDateTime = "";
    
    // Determine how much context history to use for dynamic profiles
    $dynamicProfileContextHistory = 50; // Default value
    if (isset($GLOBALS["CONTEXT_HISTORY_DYNAMIC_PROFILE"]) && $GLOBALS["CONTEXT_HISTORY_DYNAMIC_PROFILE"] > 0) {
        $dynamicProfileContextHistory = $GLOBALS["CONTEXT_HISTORY_DYNAMIC_PROFILE"];
    } elseif (isset($GLOBALS["CONTEXT_HISTORY"]) && $GLOBALS["CONTEXT_HISTORY"] > 0) {
        $dynamicProfileContextHistory = $GLOBALS["CONTEXT_HISTORY"];
    }
    
    foreach (json_decode(DataSpeechJournal($npcName, $dynamicProfileContextHistory), true) as $element) {
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
        
        $historyData .= trim("{$element["speaker"]}:".trim($element["speech"])." $listener $place $dateTime").PHP_EOL;
    }
    
    return $historyData;
}



function updateDynamicProfileField($npcName, $field, $historyData) {
    // Map field names to their corresponding HERIKA variables and prompts
    $fieldMapping = [
        'personality' => ['var' => 'HERIKA_PERSONALITY', 'prompt' => 'DYNAMIC_PROMPT_PERSONALITY'],
        'relationships' => ['var' => 'HERIKA_RELATIONSHIPS', 'prompt' => 'DYNAMIC_PROMPT_RELATIONSHIPS'],
        'occupation' => ['var' => 'HERIKA_OCCUPATION', 'prompt' => 'DYNAMIC_PROMPT_OCCUPATION'],
        'skills' => ['var' => 'HERIKA_SKILLS', 'prompt' => 'DYNAMIC_PROMPT_SKILLS'],
        'speechstyle' => ['var' => 'HERIKA_SPEECHSTYLE', 'prompt' => 'DYNAMIC_PROMPT_SPEECHSTYLE'],
        'goals' => ['var' => 'HERIKA_GOALS', 'prompt' => 'DYNAMIC_PROMPT_GOALS']
    ];
    
    if (!isset($fieldMapping[$field])) {
        Logger::warning("updateDynamicProfileField: Unknown field '$field' for $npcName");
        return false;
    }
    
    $varName = $fieldMapping[$field]['var'];
    $promptName = $fieldMapping[$field]['prompt'];
    
    // Get current field value
    $currentValue = isset($GLOBALS[$varName]) ? $GLOBALS[$varName] : '';
    
    // Get field-specific prompt
    $updatePrompt = isset($GLOBALS[$promptName]) ? $GLOBALS[$promptName] : '';
    if (empty($updatePrompt)) {
        Logger::warning("updateDynamicProfileField: No prompt configured for field '$field' ($promptName)");
        return false;
    }
    
    try {
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
            if (isset($GLOBALS[$fieldName]) && !empty(trim($GLOBALS[$fieldName]))) {
                $profileContext[] = "**{$fieldLabel}**: " . trim($GLOBALS[$fieldName]);
            }
        }

        $profileContextString = !empty($profileContext) ? "\n\n* Current Character Profile:\n" . implode("\n\n", $profileContext) : '';
        
        // Build prompt for this specific field
        $head = [
            ["role" => "system", "content" => "You are an assistant. Analyze the dialogue history and character profile to update ONLY the " . ucfirst($field) . " for the character named '$npcName'. Focus mostly on information about $npcName and mostly ignore details about other characters mentioned in the dialogue."]
        ];
        
        $prompt = [
            ["role" => "user", "content" => "* Dialogue history:\n" . $historyData . ReplacePlayerNamePlaceholder($profileContextString)],
            ["role" => "user", "content" => "Character name: " . $npcName . "\nCurrent " . ucfirst($field) . ":\n" . ReplacePlayerNamePlaceholder($currentValue)],
            ["role" => "user", "content" => ReplacePlayerNamePlaceholder($updatePrompt)]
        ];
        
        $contextData = array_merge($head, $prompt);
        
        $connectionHandler = new $GLOBALS["CONNECTORS_DIARY"];
        
        // Get max tokens for this connector
        $maxTokens = 800; // Default for field updates
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
        
        // Clean up the response
        $buffer = trim($buffer);
        
        if (!empty($buffer)) {
            Logger::debug("updateDynamicProfileField: Updated $field for $npcName");
            return $buffer;
        } else {
            Logger::info("updateDynamicProfileField: Empty response for field '$field' for $npcName");
            return false;
        }
        
    } catch (Exception $e) {
        Logger::error("updateDynamicProfileField: Error updating field '$field' for $npcName: " . $e->getMessage());
        return false;
    }
}

function saveDynamicProfileUpdates($npcName, $updatedFields, $db) {
    $newConfFile = md5($npcName);
    $path = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
    $configFile = $path . "conf" . DIRECTORY_SEPARATOR . "conf_$newConfFile.php";
    
    if (!file_exists($configFile)) {
        Logger::error("saveDynamicProfileUpdates: Config file not found for $npcName");
        return false;
    }
    
    try {
        // Create backup
        copy($configFile, $path . "conf" . DIRECTORY_SEPARATOR . ".conf_{$newConfFile}_" . time() . ".php");
        
        $backup = file_get_contents($configFile);
        $backupFmtd = $db->escape($backup);
        
        $db->insert(
            'npc_profile_backup',
            array(
                'name' => $db->escape($npcName),
                'data' => $backupFmtd
            )
        );
        
        // Read current file content
        $content = file_get_contents($configFile);
        $currentConfContent=extract_assignments($configFile);
        
        // Map field names to their corresponding HERIKA variables
        $fieldMapping = [
            'personality' => 'HERIKA_PERSONALITY',
            'relationships' => 'HERIKA_RELATIONSHIPS',
            'occupation' => 'HERIKA_OCCUPATION',
            'skills' => 'HERIKA_SKILLS',
            'speechstyle' => 'HERIKA_SPEECHSTYLE',
            'goals' => 'HERIKA_GOALS'
        ];
        
        // Update each field in the file
        foreach ($updatedFields as $field => $newValue) {
            if (!isset($fieldMapping[$field])) {
                continue;
            }
            
            // Sanitize AI-generated content to prevent PHP syntax errors
            if (is_string($newValue)) {
                $newValue = str_replace("\0", '', $newValue); // Remove null bytes
                $newValue = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $newValue); // Remove control chars
                if (!mb_check_encoding($newValue, 'UTF-8')) {
                    $newValue = mb_convert_encoding($newValue, 'UTF-8', 'UTF-8'); // Fix encoding
                }
                if (strlen($newValue) > 5000) {
                    $newValue = substr($newValue, 0, 5000) . '... [truncated]'; // Limit length
                }
                $newValue = str_replace(['<?php', '<?', '?>'], ['&lt;?php', '&lt;?', '?&gt;'], $newValue); // Escape PHP tags
                
                // Additional sanitization for var_export compatibility
                $newValue = str_replace('\\', '\\\\', $newValue); // Escape backslashes
                $newValue = str_replace("\r\n", "\n", $newValue); // Normalize line endings
                $newValue = str_replace("\r", "\n", $newValue); // Convert Mac line endings
                $newValue = preg_replace('/\n{3,}/', "\n\n", $newValue); // Limit consecutive newlines
            }
            
            $currentConfContent[$fieldMapping[$field]]=$newValue;
            
            /*
            $varName = $fieldMapping[$field];
            $escapedValue = var_export($newValue, true);
            
            // Check if variable already exists in file
            $pattern = '/\$' . preg_quote($varName, '/') . '\s*=\s*[^;]+;/';
            
            if (preg_match($pattern, $content)) {
                // Update existing variable
                $content = preg_replace($pattern, '$' . $varName . '=' . $escapedValue . ';', $content);
            } else {
                // Add new variable before the closing 
                $content = str_replace('?>', '$' . $varName . '=' . $escapedValue . ';' . PHP_EOL . '?>', $content);
            }
            */
        }
        
        // Write updated content back to file
        //file_put_contents($configFile, $content, LOCK_EX);
        write_php_assignments($currentConfContent,$configFile);
        
        
        Logger::info("saveDynamicProfileUpdates: Successfully saved updates for $npcName");
        return true;
        
    } catch (Exception $e) {
        Logger::error("saveDynamicProfileUpdates: Error saving updates for $npcName: " . $e->getMessage());
        return false;
    }
}

function triggerImmediateProfileProcessing() {
    global $db;
    
    // Ensure required dependencies are loaded
    if (!function_exists('DataSpeechJournal')) {
        require_once(__DIR__ . "/../lib/data_functions.php");
    }
    if (!function_exists('buildDynamicProfileDisplay')) {
        require_once(__DIR__ . "/../lib/model_dynmodel.php");
    }
    
    // Check if there are any queue entries to process
    $queueResults = $db->fetchAll("SELECT id, value FROM conf_opts WHERE id LIKE 'dynamic_profiles_queue_%' ORDER BY id LIMIT 5");
    
    if (empty($queueResults)) {
        Logger::debug("triggerImmediateProfileProcessing: No queue entries found");
        return;
    }
    
    Logger::info("triggerImmediateProfileProcessing: Processing " . count($queueResults) . " queue entries immediately");
    
    // Check if already processing (lock exists)
    $lockId = 'dynamic_profiles_lock';
    $lockResult = $db->fetchAll("SELECT value FROM conf_opts WHERE id = '$lockId'");
    
    if (!empty($lockResult)) {
        $lockTime = intval($lockResult[0]['value']);
        // If lock is recent (less than 30 seconds), skip immediate processing
        if (time() - $lockTime < 30) {
            Logger::debug("triggerImmediateProfileProcessing: Processing already in progress, skipping");
            return;
        } else {
            // Remove stale lock
            $db->delete("conf_opts", "id = '$lockId'");
        }
    }
    
    // Create processing lock
    $db->upsertRowOnConflict('conf_opts', array('id' => $lockId, 'value' => time()), 'id');
    
    try {
        $processedJobs = 0;
        $totalNPCs = 0;
        
        foreach ($queueResults as $queueRow) {
            $queueId = $queueRow['id'];
            $queueJson = $queueRow['value'];
            
            // Delete this queue entry immediately
            $db->delete("conf_opts", "id = '" . $db->escape($queueId) . "'");
            
            $queueData = json_decode($queueJson, true);
            if (!$queueData || !isset($queueData['npcs']) || !isset($queueData['gameRequest'])) {
                Logger::error("triggerImmediateProfileProcessing: Invalid queue data for $queueId");
                continue;
            }

            $npcs = $queueData['npcs'];
            $gameRequest = $queueData['gameRequest'];
            
            Logger::info("triggerImmediateProfileProcessing: Processing " . count($npcs) . " NPCs");

            $successCount = 0;
            foreach ($npcs as $npcName) {
                try {
                    if (processSingleDynamicProfile($npcName, $gameRequest)) {
                        $successCount++;
                        Logger::debug("triggerImmediateProfileProcessing: Updated profile for $npcName");
                    }
                } catch (Exception $e) {
                    Logger::error("triggerImmediateProfileProcessing: Error processing $npcName: " . $e->getMessage());
                }
            }

            Logger::info("triggerImmediateProfileProcessing: Completed job - updated $successCount of " . count($npcs) . " profiles");
            $processedJobs++;
            $totalNPCs += count($npcs);
        }

        if ($processedJobs > 0) {
            Logger::info("triggerImmediateProfileProcessing: Total processed: $processedJobs jobs, $totalNPCs NPCs");
        }

    } catch (Exception $e) {
        Logger::error("triggerImmediateProfileProcessing: Fatal error: " . $e->getMessage());
    } finally {
        // Always remove lock
        $db->delete("conf_opts", "id = '$lockId'");
    }
}