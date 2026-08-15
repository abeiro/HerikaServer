<?php

require_once(__DIR__ . DIRECTORY_SEPARATOR . "prompt_composition.php");
require_once __DIR__ . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'physical_npc_diaries.php';

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

    $defaultDiaryConnector = function_exists('chimResolveDiaryConnectorName') ? chimResolveDiaryConnectorName() : null;
    if ($defaultDiaryConnector === null) {
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
                "CONNECTORS_DIARY" => $defaultDiaryConnector
            ];
            Logger::info("DIARY_NEARBY: Using default profile for $npcName");
        }

        $resolvedDiaryConnector = function_exists('chimResolveDiaryConnectorName')
            ? chimResolveDiaryConnectorName($NPC_CONF["CONNECTORS_DIARY"] ?? $defaultDiaryConnector)
            : $defaultDiaryConnector;
        if ($resolvedDiaryConnector === null) {
            Logger::warn("DIARY_NEARBY: Unable to resolve diary connector for $npcName");
            return false;
        }
        $NPC_CONF["CONNECTORS_DIARY"] = $resolvedDiaryConnector;

        // Always enforce PLAYER_NAME from database-synced global, overriding any stale value in profiles
        // Ensures all '#PLAYER_NAME#' replacements resolve to current in-game name
        $NPC_CONF["PLAYER_NAME"] = $GLOBALS["PLAYER_NAME"];
        
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

        $diaryPrompt = strtr($GLOBALS["DIARY_PROMPT"], ['#HERIKA_NAME#'=>$npcName,'#PLAYER_NAME#'=>$NPC_CONF["PLAYER_NAME"]]);
        $prompt[] = ["role" => "user", "content" => $diaryPrompt];

        $contextData = array_merge($head, $prompt);
        
        // Set the request type for diary so connector knows to use diary grammar
        $originalGameRequest = isset($GLOBALS["gameRequest"]) ? $GLOBALS["gameRequest"] : null;
        $GLOBALS["gameRequest"] = [0 => "diary", 1 => time(), 2 => $gameRequest[2], 3 => "Auto diary for " . $npcName];
        
        // Generate diary entry using LLM
        require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."connector".DIRECTORY_SEPARATOR."{$NPC_CONF["CONNECTORS_DIARY"]}.php");
        
        $connectionHandler = new $resolvedDiaryConnector();
        // Prefer connector's configured max_tokens; fallback to legacy MAX_TOKENS_MEMORY; then a sane default
        $maxTokens = null;
        if (isset($GLOBALS["CONNECTOR"][$resolvedDiaryConnector]["max_tokens"]) && $GLOBALS["CONNECTOR"][$resolvedDiaryConnector]["max_tokens"] !== '') {
            $maxTokens = (int)$GLOBALS["CONNECTOR"][$resolvedDiaryConnector]["max_tokens"];
        } elseif (isset($GLOBALS["CONNECTOR"][$resolvedDiaryConnector]["MAX_TOKENS_MEMORY"])) {
            $maxTokens = (int)$GLOBALS["CONNECTOR"][$resolvedDiaryConnector]["MAX_TOKENS_MEMORY"];
        } else {
            $maxTokens = 2048;
        }

        chimLogPromptComposition(
            'diary',
            [
                'character_head' => $head,
                'history' => !empty($contextDataHistoric) ? $prompt[0] : [],
                'diary_instruction' => $diaryPrompt,
            ],
            $contextData
        );

        // Pass provider-agnostic MAX_TOKENS override so connectors map to correct API field
        $connectionHandler->open($contextData, ["MAX_TOKENS" => $maxTokens]);
        
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
        
        $connectionHandler->close("generateNearbyDiary");
        
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
            $momentum=time();
            $diarySaved = $db->insert(
                'diarylog',
                array(
                    'ts' => $gameRequest[1],
                    'gamets' => $gameRequest[2],
                    'topic' => $topic . " (Nearby diary: $eventType)",
                    'content' => trim($buffer),
                    'tags' => "Nearby-diary,$eventType",
                    'people' => $npcName,
                    'location' => $location,
                    'sess' => $momentum,
                    'localts' => time()
                )
            );

            if ($diarySaved !== false) {
                chimPhysicalDiarySyncForNpc($npcName, (int)$gameRequest[2]);
            }
            
            // Log memory
            if (function_exists('logMemory')) {
                logMemory($npcName, $npcName, trim($buffer), $momentum, $gameRequest[2], 'nearby_diary', $gameRequest[1]);
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
            $GLOBALS["HERIKA_OCCUPATION"] = $originalHerikaData['HERIKA_OCCUPATION'];
            $GLOBALS["HERIKA_SKILLS"] = $originalHerikaData['HERIKA_SKILLS'];
            $GLOBALS["HERIKA_SPEECHSTYLE"] = $originalHerikaData['HERIKA_SPEECHSTYLE'];
            $GLOBALS["HERIKA_GOALS"] = $originalHerikaData['HERIKA_GOALS'];
            $GLOBALS["HERIKA_DYNAMIC"] = $originalHerikaData['HERIKA_DYNAMIC'];
        }
    }
    
    return false;
}

// Function to process AUTO_DIARY for nearby NPCs with auto_diary_enabled
function buildPlayerDiaryPrompt(string $playerName): string
{
    $template = '';
    try {
        if (isset($GLOBALS["db"]) && is_object($GLOBALS["db"])) {
            $promptData = $GLOBALS["db"]->fetchOne("SELECT custom_prompt, default_prompt FROM prompts WHERE prompt_key = 'player_diary_prompt'");
            if (is_array($promptData)) {
                $template = trim((string)(!empty($promptData['custom_prompt']) ? $promptData['custom_prompt'] : ($promptData['default_prompt'] ?? '')));
            }
        }
    } catch (Throwable $e) {
        Logger::warn("PLAYER_DIARY: Failed to load player_diary_prompt from Prompt Manager: " . $e->getMessage());
    }

    if ($template === '') {
        $template = trim((string)($GLOBALS["PLAYER_DIARY_PROMPT"] ?? ''));
    }
    if ($template === '') {
        $template = "Please write a short summary of #PLAYER_NAME#'s recent dialogues and events written above into #PLAYER_NAME#'s diary. WRITE AS IF YOU WERE #PLAYER_NAME#. Use first person and start the diary entry with the current date and time.";
    }

    return strtr($template, [
        '#PLAYER_NAME#' => $playerName,
        '{PLAYER_NAME}' => $playerName
    ]);
}

function getConfiguredPlayerDiaryName(Player $player): string
{
    $playerName = trim((string)($player->get('player_name') ?? ($GLOBALS["PLAYER_NAME"] ?? '')));
    if ($playerName === '') {
        $playerName = 'Player';
    }

    return $playerName;
}

function isPlayerDiaryEnabled(): bool
{
    if (!class_exists('Player')) {
        require_once(__DIR__ . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "player.class.php");
    }

    try {
        $player = new Player();
        return $player->getBool('diary_enabled', false);
    } catch (Throwable $e) {
        Logger::error("PLAYER_DIARY: Failed to read player diary setting: " . $e->getMessage());
        return false;
    }
}

function isPlayerAutoDiaryEnabled(): bool
{
    if (!class_exists('Player')) {
        require_once(__DIR__ . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "player.class.php");
    }

    try {
        $player = new Player();
        return $player->getBool('auto_diary_enabled', false);
    } catch (Throwable $e) {
        Logger::error("PLAYER_DIARY: Failed to read player auto diary setting: " . $e->getMessage());
        return false;
    }
}

function isPlayerAutoDiaryWaitEnabled(): bool
{
    if (!class_exists('Player')) {
        require_once(__DIR__ . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "player.class.php");
    }

    try {
        $player = new Player();
        return $player->getBool('auto_diary_wait_enabled', false);
    } catch (Throwable $e) {
        Logger::error("PLAYER_DIARY: Failed to read player auto diary wait setting: " . $e->getMessage());
        return false;
    }
}

function generatePlayerDiary($gameRequest, $eventType)
{
    global $db;

    if (!class_exists('Player')) {
        require_once(__DIR__ . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "player.class.php");
    }
    if (!class_exists('LLMConnector')) {
        require_once(__DIR__ . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "llm_connector.class.php");
    }
    if (!function_exists('chimResolvePlayerDiaryConnectorFromDefaultProfile')) {
        require_once(__DIR__ . DIRECTORY_SEPARATOR . "player_diary_connector.php");
    }

    $player = new Player();
    $playerName = getConfiguredPlayerDiaryName($player);
    $allPlayerData = $player->getAll();

    $playerAppearance = trim((string)($allPlayerData['appearance'] ?? ''));
    $playerBackground = trim((string)($allPlayerData['background'] ?? ''));
    if ($playerBackground === '') {
        $playerBackground = trim((string)($allPlayerData['bio'] ?? ''));
    }
    $playerSpeechStyle = trim((string)($allPlayerData['speech_style'] ?? ''));

    if ($playerBackground === '' && !empty(trim((string)($GLOBALS["PLAYER_BIOS"] ?? '')))) {
        $playerBackground = trim((string)$GLOBALS["PLAYER_BIOS"]);
    }

    $playerAppearance = strtr($playerAppearance, ['#PLAYER_NAME#' => $playerName]);
    $playerBackground = strtr($playerBackground, ['#PLAYER_NAME#' => $playerName]);
    $playerSpeechStyle = strtr($playerSpeechStyle, ['#PLAYER_NAME#' => $playerName]);

    $systemParts = [
        "You are writing {$playerName}'s private diary in the world of Skyrim.",
        "Write in first person as {$playerName}.",
        "Do not write as The Narrator or as any NPC companion.",
        "Ground the entry in the recent dialogue and events provided.",
        "Use the provided background and speech style details to keep the entry consistent with {$playerName}'s history and voice."
    ];

    $resolvedPromptHead = trim(strtr((string)($GLOBALS["PROMPT_HEAD"] ?? ''), [
        '#PLAYER_NAME#' => $playerName,
        '#HERIKA_NAME#' => $playerName
    ]));
    if ($resolvedPromptHead !== '') {
        $systemParts[] = $resolvedPromptHead;
    }

    $playerDetails = [];
    if ($playerAppearance !== '') {
        $playerDetails[] = "#Appearance\n{$playerAppearance}";
    }
    if ($playerBackground !== '') {
        $playerDetails[] = "#Background\n{$playerBackground}";
    }
    if ($playerSpeechStyle !== '') {
        $playerDetails[] = "#Speech Style\n{$playerSpeechStyle}";
    }
    if (!empty($playerDetails)) {
        $systemParts[] = "#Character Details\n" . implode("\n\n", $playerDetails);
    }

    $head[] = [
        'role' => 'system',
        'content' => implode("\n\n", $systemParts)
    ];

    if (isset($GLOBALS["CONTEXT_HISTORY_DIARY"]) && $GLOBALS["CONTEXT_HISTORY_DIARY"] > 0) {
        $lastNDataForContext = $GLOBALS["CONTEXT_HISTORY_DIARY"] + 0;
    } else {
        $lastNDataForContext = isset($GLOBALS["CONTEXT_HISTORY"]) ? ($GLOBALS["CONTEXT_HISTORY"] + 0) : 25;
    }

    $sqlfilter = " and type<>'prechat' and type<>'itemfound' and type<>'infoaction' and type<>'npcspellcast' ";
    $contextDataHistoric = DataLastDataExpandedFor($playerName, $lastNDataForContext * -1, $sqlfilter);
    $historyData = "";
    foreach ($contextDataHistoric as $element) {
        $historyData .= trim((string)($element["content"] ?? '')) . PHP_EOL . PHP_EOL;
    }

    $prompt = [];
    if (!empty($contextDataHistoric)) {
        $prompt[] = ["role" => "user", "content" => "Recent context: " . $historyData];
    }

    $prompt[] = [
        "role" => "user",
        "content" => buildPlayerDiaryPrompt($playerName)
    ];

    $contextData = array_merge($head, $prompt);

    $originalGameRequest = $GLOBALS["gameRequest"] ?? null;
    $GLOBALS["gameRequest"] = [0 => "diary_player", 1 => time(), 2 => $gameRequest[2], 3 => "Diary for " . $playerName];

    $hadForce = array_key_exists('FORCE_MAX_TOKENS', $GLOBALS);
    $prevForce = $hadForce ? $GLOBALS['FORCE_MAX_TOKENS'] : null;
    if ($hadForce) {
        unset($GLOBALS['FORCE_MAX_TOKENS']);
    }

    $buffer = "";

    try {
        $connector = new LLMConnector();
        $defaultDiaryConnector = chimResolvePlayerDiaryConnectorFromDefaultProfile();
        $coreConnectorId = intval($defaultDiaryConnector['connector_id'] ?? 0);
        $currentConnectorData = $defaultDiaryConnector['connector_data'] ?? null;

        chimLogPromptComposition(
            'diary_player',
            [
                'character_head' => $head,
                'history' => !empty($contextDataHistoric) ? $prompt[0] : [],
                'diary_instruction' => $prompt[count($prompt) - 1] ?? [],
            ],
            $contextData
        );

        if (!empty($currentConnectorData)) {
            $profileLabel = trim((string)($defaultDiaryConnector['profile_label'] ?? 'Default Profile'));
            Logger::info("PLAYER_DIARY: Using default profile diary connector {$coreConnectorId} ({$currentConnectorData["driver"]}/{$currentConnectorData["model"]}) from {$profileLabel}");
            $connector->setOldGlobals($currentConnectorData);
            $GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"] = $currentConnectorData;
            unset($GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]["stop"]);

            $maxTokens = null;
            if (isset($currentConnectorData["max_tokens"]) && $currentConnectorData["max_tokens"] !== null && $currentConnectorData["max_tokens"] !== '') {
                $maxTokens = (int)$currentConnectorData["max_tokens"];
            } elseif (isset($GLOBALS["CONNECTOR"][$currentConnectorData["driver"]]["max_tokens"])) {
                $maxTokens = (int)$GLOBALS["CONNECTOR"][$currentConnectorData["driver"]]["max_tokens"];
            } elseif (isset($GLOBALS["CONNECTOR"][$currentConnectorData["driver"]]["MAX_TOKENS_MEMORY"])) {
                $maxTokens = (int)$GLOBALS["CONNECTOR"][$currentConnectorData["driver"]]["MAX_TOKENS_MEMORY"];
            } else {
                $maxTokens = 2048;
            }

            $connectionHandler = $connector->getConnector($currentConnectorData);
            $buffer = (string)$connectionHandler->fast_request($contextData, ["MAX_TOKENS" => $maxTokens], "diary");
        } else {
            $defaultDiaryConnectorError = trim((string)($defaultDiaryConnector['error'] ?? ''));
            if ($defaultDiaryConnectorError !== '') {
                Logger::warn("PLAYER_DIARY: Default profile diary connector unavailable: {$defaultDiaryConnectorError}");
            }

            $diaryConnector = function_exists('chimResolveDiaryConnectorName') ? chimResolveDiaryConnectorName() : null;
            if ($diaryConnector === null) {
                Logger::warn("PLAYER_DIARY: No diary connector configured");
                return false;
            }

            Logger::warn("PLAYER_DIARY: Falling back to legacy diary connector '{$diaryConnector}'");
            require_once(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "connector" . DIRECTORY_SEPARATOR . "{$diaryConnector}.php");

            $maxTokens = null;
            if (isset($GLOBALS["CONNECTOR"][$diaryConnector]["max_tokens"]) && $GLOBALS["CONNECTOR"][$diaryConnector]["max_tokens"] !== '') {
                $maxTokens = (int)$GLOBALS["CONNECTOR"][$diaryConnector]["max_tokens"];
            } elseif (isset($GLOBALS["CONNECTOR"][$diaryConnector]["MAX_TOKENS_MEMORY"])) {
                $maxTokens = (int)$GLOBALS["CONNECTOR"][$diaryConnector]["MAX_TOKENS_MEMORY"];
            } else {
                $maxTokens = 2048;
            }

            $connectionHandler = new $diaryConnector();
            $connectionHandler->open($contextData, ["MAX_TOKENS" => $maxTokens]);

            while (true) {
                $chunk = $connectionHandler->process();
                if ($chunk !== null && $chunk !== false) {
                    $buffer .= $chunk;
                }

                if ($connectionHandler->isDone()) {
                    break;
                }
            }
        }
    } catch (Throwable $e) {
        Logger::error("PLAYER_DIARY: Error generating diary for {$playerName}: " . $e->getMessage());
    } finally {
        if (isset($connectionHandler) && is_object($connectionHandler) && method_exists($connectionHandler, 'close')) {
            try {
                $connectionHandler->close("generatePlayerDiary");
            } catch (Throwable $closeError) {
                Logger::debug("PLAYER_DIARY: Connector close warning for {$playerName}: " . $closeError->getMessage());
            }
        }

        if ($hadForce) {
            $GLOBALS['FORCE_MAX_TOKENS'] = $prevForce;
        }

        if ($originalGameRequest !== null) {
            $GLOBALS["gameRequest"] = $originalGameRequest;
        } else {
            unset($GLOBALS["gameRequest"]);
        }
    }

    if (!empty(trim($buffer))) {
        $topic = DataLastKnowDate();
        $location = DataLastKnownLocation();
        $momentum = time();

        $db->insert(
            'diarylog',
            array(
                'ts' => $gameRequest[1],
                'gamets' => $gameRequest[2],
                'topic' => $topic . " (Player diary: $eventType)",
                'content' => trim($buffer),
                'tags' => "Player-diary,$eventType",
                'people' => $playerName,
                'location' => $location,
                'sess' => $momentum,
                'localts' => time()
            )
        );

        if (function_exists('logMemory')) {
            logMemory($playerName, $playerName, trim($buffer), $momentum, $gameRequest[2], 'player_diary', $gameRequest[1]);
        }

        echo $playerName . "|rolecommand|DebugNotification@Diary Entry Written for " . $playerName . PHP_EOL;
        @ob_flush();
        @flush();

        return true;
    }

    return false;
}

function processAutoDiary($gameRequest, $eventType) {
    global $db;
    
    Logger::info("AUTO_DIARY: Function called for event type: $eventType");
    $playerDiaryEnabled = isPlayerDiaryEnabled();
    $playerAutoDiaryEnabled = isPlayerAutoDiaryEnabled();
    $playerAutoDiaryWaitEnabled = isPlayerAutoDiaryWaitEnabled();
    $shouldProcessPlayerDiary = ($eventType === "goodnight" && $playerDiaryEnabled && $playerAutoDiaryEnabled)
        || ($eventType === "waitstart" && $playerDiaryEnabled && $playerAutoDiaryEnabled && $playerAutoDiaryWaitEnabled);
    
    // Get nearby NPCs
    $nearbyNpcsStr = DataBeingsInCloseRange();
    Logger::info("AUTO_DIARY: DataBeingsInCloseRange returned: " . var_export($nearbyNpcsStr, true));
    
    $nearbyNpcs = [];
    
    if (!empty($nearbyNpcsStr)) {
        Logger::debug("AUTO_DIARY: Raw nearby data: " . $nearbyNpcsStr);
        
        // Parse nearby NPCs (pipe-delimited string like "|Lydia|Serana|")
        $nearbyNpcsStr = trim($nearbyNpcsStr, '|');
        if (!empty($nearbyNpcsStr)) {
            $nearbyNpcs = explode('|', $nearbyNpcsStr);
            $nearbyNpcs = array_filter(array_map('trim', $nearbyNpcs));
        }
    }
    
    // Add The Narrator if auto diary is enabled for narrator
    // The Narrator is always "nearby" (conceptually omnipresent)
    if (!empty($GLOBALS["NARRATOR_AUTO_DIARY_ENABLED"])) {
        $nearbyNpcs[] = "The Narrator";
        Logger::info("AUTO_DIARY: Added The Narrator to auto diary processing (auto toggle enabled)");
    }
    
    if (empty($nearbyNpcs) && !$shouldProcessPlayerDiary) {
        Logger::info("AUTO_DIARY: No nearby NPCs, narrator, or player diary entries to process");
        return;
    }
    
    $processedCount = 0;
    $generatedCount = 0;
    $diaryCooldownPeriod = isset($GLOBALS["DIARY_COOLDOWN"]) ? intval($GLOBALS["DIARY_COOLDOWN"]) : 30;
    
    if ($shouldProcessPlayerDiary) {
        try {
            if (!class_exists('Player')) {
                require_once(__DIR__ . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "player.class.php");
            }

            $player = new Player();
            $playerName = getConfiguredPlayerDiaryName($player);
            $playerNameSafe = preg_replace('/[^a-zA-Z0-9_]/', '_', $playerName);
            $cooldownKey = "DIARY_LAST_TIMESTAMP_PLAYER_" . $playerNameSafe;

            $diaryRecord = $db->fetchAll("SELECT value FROM conf_opts WHERE id='" . $db->escape($cooldownKey) . "'");
            if (!empty($diaryRecord) && isset($diaryRecord[0]['value'])) {
                $lastTimestamp = intval($diaryRecord[0]['value']);
                $timeSinceLastDiary = time() - $lastTimestamp;

                if ($timeSinceLastDiary < $diaryCooldownPeriod) {
                    $remaining = $diaryCooldownPeriod - $timeSinceLastDiary;
                    Logger::debug("AUTO_DIARY: {$playerName} is on diary cooldown (remaining: {$remaining}s), skipping");
                } else {
                    $processedCount++;
                    if (generatePlayerDiary($gameRequest, $eventType)) {
                        $generatedCount++;
                        $db->query("DELETE FROM conf_opts WHERE id='" . $db->escape($cooldownKey) . "'");
                        $db->insert('conf_opts', [
                            'id' => $cooldownKey,
                            'value' => (string)time()
                        ]);
                        Logger::info("AUTO_DIARY: Successfully generated player diary for {$playerName}");
                    } else {
                        Logger::warn("AUTO_DIARY: Failed to generate player diary for {$playerName}");
                    }
                }
            } else {
                $processedCount++;
                if (generatePlayerDiary($gameRequest, $eventType)) {
                    $generatedCount++;
                    $db->insert('conf_opts', [
                        'id' => $cooldownKey,
                        'value' => (string)time()
                    ]);
                    Logger::info("AUTO_DIARY: Successfully generated player diary for {$playerName}");
                } else {
                    Logger::warn("AUTO_DIARY: Failed to generate player diary for {$playerName}");
                }
            }
        } catch (Throwable $e) {
            Logger::error("AUTO_DIARY: Error processing player diary: " . $e->getMessage());
        }
    }

    Logger::info("AUTO_DIARY: Processing $eventType event for " . count($nearbyNpcs) . " nearby NPCs/entities");
    
    foreach ($nearbyNpcs as $npcName) {
        if (empty($npcName)) {
            continue;
        }
        
        // Special handling for The Narrator
        if ($npcName === "The Narrator") {
            // Check if narrator auto diary is enabled
            if (empty($GLOBALS["NARRATOR_AUTO_DIARY_ENABLED"])) {
                Logger::debug("AUTO_DIARY: The Narrator auto diary is disabled, skipping");
                continue;
            }
            
            // Check narrator's diary cooldown
            $cooldownKey = "DIARY_LAST_TIMESTAMP_The_Narrator";
            $diaryRecord = $db->fetchAll("SELECT value FROM conf_opts WHERE id='" . $db->escape($cooldownKey) . "'");
            
            if (!empty($diaryRecord) && isset($diaryRecord[0]['value'])) {
                $lastTimestamp = intval($diaryRecord[0]['value']);
                $timeSinceLastDiary = time() - $lastTimestamp;
                
                if ($timeSinceLastDiary < $diaryCooldownPeriod) {
                    $remaining = $diaryCooldownPeriod - $timeSinceLastDiary;
                    Logger::debug("AUTO_DIARY: The Narrator is on diary cooldown (remaining: {$remaining}s), skipping");
                    continue;
                }
            }
            
            $processedCount++;
            
            // Generate diary for The Narrator
            Logger::info("AUTO_DIARY: Generating diary for The Narrator");
            $success = generateFollowerDiary("The Narrator", $gameRequest, $eventType);
            
            if ($success) {
                $generatedCount++;
                // Update cooldown timestamp
                $db->query("DELETE FROM conf_opts WHERE id='" . $db->escape($cooldownKey) . "'");
                $db->insert('conf_opts', [
                    'id' => $cooldownKey,
                    'value' => (string)time()
                ]);
                Logger::info("AUTO_DIARY: Successfully generated diary for The Narrator");
            } else {
                Logger::warn("AUTO_DIARY: Failed to generate diary for The Narrator");
            }
            
            continue; // Skip to next NPC
        }
        
        // Regular NPC processing
        // Get NPC data from database
        $npcMaster = new NpcMaster();
        $currentNpcData = $npcMaster->getByName($npcName);
        
        if (empty($currentNpcData)) {
            Logger::debug("AUTO_DIARY: NPC '$npcName' not found in database, skipping");
            continue;
        }
        
        // Load profile first so we can check profile defaults
        $profile = new CoreProfile();
        $currentProfileData = $profile->getById($currentNpcData["profile_id"]);
        $profile->setOldGlobals($currentProfileData);
        $npcMaster->setOldGlobalsFromCurrentNpcData($currentNpcData);
        
        // Check auto_diary_enabled with proper profile inheritance
        $autoDiaryEnabled = false;
        
        // First, get the profile default from metadata
        try {
            if (!empty($currentProfileData['metadata'])) {
                $profileMeta = json_decode($currentProfileData['metadata'], true);
                if (is_array($profileMeta) && isset($profileMeta['AUTO_DIARY_ENABLED'])) {
                    $autoDiaryEnabled = ($profileMeta['AUTO_DIARY_ENABLED'] === '1' || 
                                        $profileMeta['AUTO_DIARY_ENABLED'] === 1 || 
                                        $profileMeta['AUTO_DIARY_ENABLED'] === true);
                }
            }
        } catch (Throwable $e) {
            Logger::debug("AUTO_DIARY: Error reading profile metadata for $npcName: " . $e->getMessage());
        }
        
        // Then, check for NPC-specific override in extended_data
        if (!empty($currentNpcData['extended_data'])) {
            $extendedData = json_decode($currentNpcData['extended_data'], true);
            if (is_array($extendedData) && 
                array_key_exists('auto_diary_enabled', $extendedData) && 
                $extendedData['auto_diary_enabled'] !== null && 
                $extendedData['auto_diary_enabled'] !== '') {
                // NPC has explicit override
                $autoDiaryEnabled = !empty($extendedData['auto_diary_enabled']);
                Logger::debug("AUTO_DIARY: NPC '$npcName' has explicit override: " . ($autoDiaryEnabled ? 'enabled' : 'disabled'));
            }
        }
        
        if (!$autoDiaryEnabled) {
            Logger::debug("AUTO_DIARY: NPC '$npcName' does not have auto_diary_enabled (checked both NPC override and profile default), skipping");
            continue;
        }
        
        $processedCount++;
        
        // Check diary cooldown for this specific NPC
        $npcNameSafe = preg_replace('/[^a-zA-Z0-9_]/', '_', $npcName);
        $cooldownKey = "DIARY_LAST_TIMESTAMP_" . $npcNameSafe;
        
        $diaryRecord = $db->fetchAll("SELECT value FROM conf_opts WHERE id='" . $db->escape($cooldownKey) . "'");
        
        if (!empty($diaryRecord)) {
            $lastTrigger = (int) $diaryRecord[0]['value'];
            $timeElapsed = time() - $lastTrigger;

            if ($timeElapsed < $diaryCooldownPeriod) {
                Logger::info("AUTO_DIARY: Skipping $npcName (cooldown active: " . ($diaryCooldownPeriod - $timeElapsed) . " seconds remaining)");
                continue;
            }
        }

        // Check AUTO_DIARY_WAIT with profile inheritance
        $autoDiaryWait = false;
        
        // Get profile default for AUTO_DIARY_WAIT
        try {
            if (!empty($currentProfileData['metadata'])) {
                $profileMeta = json_decode($currentProfileData['metadata'], true);
                if (is_array($profileMeta) && isset($profileMeta['AUTO_DIARY_WAIT_ENABLED'])) {
                    $autoDiaryWait = ($profileMeta['AUTO_DIARY_WAIT_ENABLED'] === '1' || 
                                     $profileMeta['AUTO_DIARY_WAIT_ENABLED'] === 1 || 
                                     $profileMeta['AUTO_DIARY_WAIT_ENABLED'] === true);
                }
            }
        } catch (Throwable $e) {}
        
        // Check for NPC override
        if (!empty($currentNpcData['extended_data'])) {
            $extendedData = json_decode($currentNpcData['extended_data'], true);
            if (is_array($extendedData) && 
                array_key_exists('auto_diary_wait_enabled', $extendedData) && 
                $extendedData['auto_diary_wait_enabled'] !== null && 
                $extendedData['auto_diary_wait_enabled'] !== '') {
                $autoDiaryWait = !empty($extendedData['auto_diary_wait_enabled']);
            }
        }
        
        // For goodnight events, always generate. For waitstart events, check the setting.
        $shouldGenerate = ($eventType === "goodnight") || ($eventType === "waitstart" && $autoDiaryWait);
        
        Logger::info("AUTO_DIARY: $npcName - eventType=$eventType, autoDiaryWait=" . 
                    ($autoDiaryWait ? 'true' : 'false') . 
                    ", shouldGenerate=" . ($shouldGenerate ? 'true' : 'false'));

        if ($shouldGenerate) {
            // Update cooldown timestamp for this NPC
            $db->upsertRowOnConflict(
                'conf_opts',
                array(
                    'id' => $cooldownKey,
                    'value' => time()
                ),
                "id"
            );
            
            // Generate diary entry for this NPC
            if (generateFollowerDiary($npcName, $gameRequest, $eventType)) {
                $generatedCount++;
                Logger::info("AUTO_DIARY: Generated diary entry for $npcName");
            } else {
                Logger::info("AUTO_DIARY: Failed to generate diary entry for $npcName");
            }
        } else {
            Logger::info("AUTO_DIARY: Skipped $npcName - AUTO_DIARY_WAIT disabled for this profile");
        }
    }
    
    Logger::info("AUTO_DIARY: Processed $processedCount NPCs with auto_diary_enabled, generated $generatedCount diary entries");
}

// Function to process a single NPC's dynamic profile
function processSingleDynamicProfile($npcName, $gameRequest) {
    global $db;
    
    // Ensure required dependencies are loaded
    if (!function_exists('DataSpeechJournal') || !function_exists('buildDynamicProfileDisplay')) {
        require_once(__DIR__ . "/../lib/data_functions.php");
    }
    
    // Handle The Narrator separately
    if ($npcName === "The Narrator") {
        return processNarratorDynamicProfile($db);
    }
    
    // Check if profile exists for this NPC
    $npcMaster=new NpcMaster();
    $npcData=$npcMaster->getByName($npcName);

    // Load profile, maybe DYNAMIC_PROFILE_FIELDS is defined there
    $profile=new CoreProfile();
    $currentProfileData=$profile->getById($npcData["profile_id"]);

    $npcDataMetadata=json_decode($npcData["metadata"],true);
    $profileMetadata=json_decode($currentProfileData["metadata"],true);
    if (empty($npcDataMetadata["DYNAMIC_PROFILE_FIELDS"])) {
        $npcData["DYNAMIC_PROFILE_FIELDS"]=$profileMetadata["DYNAMIC_PROFILE_FIELDS"];
    } else
        $npcData["DYNAMIC_PROFILE_FIELDS"]=$npcDataMetadata["DYNAMIC_PROFILE_FIELDS"];
    

    if (!$npcData) {
        Logger::debug("processSingleDynamicProfile: No profile found for $npcName");
        return false;
    }

   
    try {
        $characterDynamicProfile = $npcData["dynamic_profile"] ?? $GLOBALS["DYNAMIC_PROFILE"] ?? false;
        
        if (isset($profileMetadata["DYNAMIC_PROFILE_ENABLED"]) && $profileMetadata["DYNAMIC_PROFILE_ENABLED"]==1) {
            $characterDynamicProfile=true;
        }

        // restores logic now that DYNAMIC_PROFILE_FIELDS is in npcData
        $characterDynamicProfileFields = $npcData["DYNAMIC_PROFILE_FIELDS"] ??
            $GLOBALS["DYNAMIC_PROFILE_FIELDS"] ?? // use default conf.php settings
            ["personality", "relationships"]; //fallback

        // Check if DYNAMIC_PROFILE is enabled for this NPC
        if (!$characterDynamicProfile) {
            Logger::debug("processSingleDynamicProfile: DYNAMIC_PROFILE disabled for $npcName");
            return false;
        }
        
       
        // Check if core connector is configured for dynamic profile updates
        $connector = new LLMConnector();
        $currentConnectorData = $connector->getById($GLOBALS["CORE_CONNECTOR_PROFILES"]);
        if (!$currentConnectorData) {
            Logger::debug("processSingleDynamicProfile: No core connector configured while updating profile for $npcName");
            return false;
        }
        
        // Get dynamic profile fields to update
        $fieldsToUpdate = $characterDynamicProfileFields;
        
        if (empty($fieldsToUpdate)) {
            Logger::debug("processSingleDynamicProfile: No fields selected for dynamic updates for $npcName");
            return false;
        }
        
        $historyData = getDynamicProfileHistoryData($npcName);
        $updatedFields = [];
        $successCount = 0;
        
        foreach ($fieldsToUpdate as $field) {
            error_log("[processSingleDynamicProfile] Updating $npcName $field");

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
    }
    
    return false;
}

/**
 * Process dynamic profile updates for The Narrator
 * @param object $db Database connection
 * @return bool Success status
 */
function processNarratorDynamicProfile($db) {
    require_once(__DIR__ . "/core/narrator.class.php");
    require_once(__DIR__ . "/core/core_profiles.class.php");
    require_once(__DIR__ . "/core/llm_connector.class.php");
    
    if (!function_exists('DataSpeechJournal') || !function_exists('buildDynamicProfileDisplay')) {
        require_once(__DIR__ . "/../lib/data_functions.php");
    }
    
    $narrator = new Narrator();
    
    // Check if dynamic profile is enabled for narrator
    if (!$narrator->getBool('dynamic_profile', false)) {
        Logger::debug("processNarratorDynamicProfile: DYNAMIC_PROFILE disabled for The Narrator");
        return false;
    }
    
    // Get dynamic profile fields
    $fieldsToUpdate = $narrator->getDynamicProfileFields();
    
    if (empty($fieldsToUpdate)) {
        Logger::debug("processNarratorDynamicProfile: No fields selected for dynamic updates for The Narrator");
        return false;
    }
    
    try {
        // Check if core connector is configured
        $connector = new LLMConnector();
        $currentConnectorData = $connector->getById($GLOBALS["CORE_CONNECTOR_PROFILES"]);
        if (!$currentConnectorData) {
            Logger::debug("processNarratorDynamicProfile: No core connector configured");
            return false;
        }
        
        // Get history data for narrator
        $historyData = getDynamicProfileHistoryData("The Narrator");
        $updatedFields = [];
        $successCount = 0;
        
        foreach ($fieldsToUpdate as $field) {
            error_log("[processNarratorDynamicProfile] Updating The Narrator $field");
            
            $result = updateDynamicProfileField("The Narrator", $field, $historyData);
            
            if ($result !== false) {
                $updatedFields[$field] = $result;
                $successCount++;
            }
        }
        
        if ($successCount > 0) {
            // Save the updated profile to narrator table
            $success = saveNarratorDynamicProfileUpdates($updatedFields);
            if ($success) {
                Logger::info("processNarratorDynamicProfile: Successfully updated $successCount fields for The Narrator: " . implode(', ', array_keys($updatedFields)));
                return true;
            }
        }
        
    } catch (Exception $e) {
        Logger::error("processNarratorDynamicProfile: Error processing The Narrator: " . $e->getMessage());
        return false;
    }
    
    return false;
}

/**
 * Save narrator dynamic profile updates to core_narrator table
 * @param array $updatedFields Associative array of field => value
 * @return bool Success status
 */
function saveNarratorDynamicProfileUpdates($updatedFields) {
    require_once(__DIR__ . "/core/narrator.class.php");
    
    try {
        $narrator = new Narrator();
        $success = true;
        
        foreach ($updatedFields as $field => $value) {
            // Map field names to narrator table keys
            $fieldKey = $field; // personality, speechstyle, goals are stored directly
            
            if (!$narrator->set($fieldKey, $value)) {
                $success = false;
                Logger::error("saveNarratorDynamicProfileUpdates: Failed to save field '$field'");
            } else {
                Logger::info("saveNarratorDynamicProfileUpdates: Saved field '$field' for The Narrator");
            }
        }
        
        // Update timestamp
        $narrator->set('gamets_last_updated', (string)time());
        
        return $success;
        
    } catch (Exception $e) {
        Logger::error("saveNarratorDynamicProfileUpdates: Error saving updates: " . $e->getMessage());
        return false;
    }
}

// Function to generate diary entry for a specific follower
function generateFollowerDiary($followerName, $gameRequest, $eventType) {
    global $db;
    
    error_log("generateFollowerDiary called for $followerName");
    
    // Special handling for The Narrator
    if ($followerName === "The Narrator") {
        // Load narrator data
        require_once(__DIR__ . "/core/narrator.class.php");
        $narrator = new Narrator();
        
        // Load settings into GLOBALS first
        $narrator->loadIntoGlobals();
        
        // Get narrator data for profile/connector lookup
        $narratorData = $narrator->getNarratorData();
        
        if (empty($narratorData) || !isset($narratorData["profile_id"])) {
            Logger::warn("generateFollowerDiary: Failed to load narrator data or no profile_id");
            return false;
        }
        
        // Load narrator character data (HERIKA_NAME, HERIKA_PERS, etc.)
        $narrator->loadCharacterIntoGlobals();
        
        // Load the profile to get connector information
        $profile = new CoreProfile();
        $currentProfileData = $profile->getById($narratorData["profile_id"]);
        
        if (empty($currentProfileData)) {
            Logger::warn("generateFollowerDiary: Failed to load narrator profile");
            return false;
        }
        
        // Load the narrator's connector for diary generation
        $connector = new LLMConnector();
        
        // Use diary_connector_id if set, otherwise fall back to regular connector_id
        $connectorId = null;
        $resolvedDiaryConnectorId = class_exists('LLMRandomizer')
            ? LLMRandomizer::getConnectorIdForField($currentProfileData, "diary_connector_id")
            : ($currentProfileData["diary_connector_id"] ?? null);
        if (!empty($resolvedDiaryConnectorId)) {
            $connectorId = $resolvedDiaryConnectorId;
            Logger::info("generateFollowerDiary: Using diary_connector_id: {$connectorId} for The Narrator");
        } elseif (isset($currentProfileData["connector_id"]) && !empty($currentProfileData["connector_id"])) {
            $connectorId = $currentProfileData["connector_id"];
            Logger::info("generateFollowerDiary: Using connector_id: {$connectorId} for The Narrator");
        } else {
            Logger::warn("generateFollowerDiary: No connector configured in narrator profile");
            return false;
        }
        
        $currentConnectorData = $connector->getById($connectorId);
        
        if (empty($currentConnectorData)) {
            Logger::warn("generateFollowerDiary: Failed to load connector data for The Narrator");
            return false;
        }
        
        $connector->setOldGlobals($currentConnectorData);
        $profile->setOldGlobals($currentProfileData);
        $GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"] = $currentConnectorData;
        unset($GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]["stop"]);
        
    } else {
        // Regular NPC processing
        $npcMaster = new NpcMaster();
        $currentNpcData = $npcMaster->getByName($followerName);
        
        if (empty($currentNpcData)) {
            Logger::warn("generateFollowerDiary: NPC '$followerName' not found in database");
            return false;
        }
        
        $profile = new CoreProfile();
        $currentProfileData = $profile->getById($currentNpcData["profile_id"]);
            
        $connector = new LLMConnector();
        $diaryConnectorId = class_exists('LLMRandomizer')
            ? LLMRandomizer::getConnectorIdForField($currentProfileData, "diary_connector_id")
            : ($currentProfileData["diary_connector_id"] ?? null);
        $currentConnectorData = $connector->getById($diaryConnectorId);
       
        $connector->setOldGlobals($currentConnectorData);
        $profile->setOldGlobals($currentProfileData);
        $npcMaster->setOldGlobalsFromCurrentNpcData($currentNpcData);

        $GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"] = $currentConnectorData;
        unset($GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]["stop"]);
    }

        
    // Use the same prompt system as regular diary entries
    // Build standard system prompt like main.php does
        
    // Use centralized function from data_functions.php
    $dynamicBiography = buildDynamicBiography($GLOBALS);
    $promptCharacterName = function_exists('chimGetPromptCharacterName')
        ? chimGetPromptCharacterName()
        : $GLOBALS["HERIKA_NAME"];
        
    $head[] = array('role' => 'system', 'content' =>  
    strtr($GLOBALS["PROMPT_HEAD"] . "\n\n#Character details\n".$GLOBALS["HERIKA_PERS"] . $dynamicBiography . "\n\n#General Instructions\n". $GLOBALS["COMMAND_PROMPT"],
        [
            "#PLAYER_NAME#" => $GLOBALS["PLAYER_NAME"],
            "#HERIKA_NAME#" => $promptCharacterName,
            "#NARRATOR_NAME#" => function_exists('chimGetNarratorRoleplayName') ? chimGetNarratorRoleplayName() : 'The Narrator',
        ])
    );
        
    // Use diary-specific context history if this is a diary request and CONTEXT_HISTORY_DIARY is set
    if (isset($GLOBALS["CONTEXT_HISTORY_DIARY"]) && $GLOBALS["CONTEXT_HISTORY_DIARY"] > 0) {
        $lastNDataForContext = $GLOBALS["CONTEXT_HISTORY_DIARY"]+0;
    } else {
        $lastNDataForContext = (isset($GLOBALS["CONTEXT_HISTORY"])) ? ($GLOBALS["CONTEXT_HISTORY"]+0) : 25;
    }

    $sqlfilter=" and type<>'prechat' and type<>'itemfound' and type<>'infoaction' and type<>'npcspellcast' ";
    $contextDataHistoric = DataLastDataExpandedFor("{$GLOBALS["HERIKA_NAME"]}", $lastNDataForContext * -1,$sqlfilter);
    $contextDataHistoric = filterHistoricContextForNarratorVisibility(
        $contextDataHistoric,
        $GLOBALS["HERIKA_NAME"] ?? ""
    );
    $historyData="";
    foreach ($contextDataHistoric as $element) {
    
        $historyData.=trim("{$element["content"]}").PHP_EOL.PHP_EOL;
        
    }


    // Build user prompt for diary generation (like regular diary)
    
    if (!empty($contextDataHistoric)) {
        $prompt[] = ["role" => "user", "content" => "Recent context: " . $historyData];
    }

    if (function_exists('chimRenderNarratorContextText')) {
        $historyData = chimRenderNarratorContextText($historyData);
    }

    $diaryPrompt=strtr($GLOBALS["DIARY_PROMPT"],['{$GLOBALS["HERIKA_NAME"]}'=>$promptCharacterName,'{$GLOBALS["PLAYER_NAME"]}'=>$GLOBALS["PLAYER_NAME"],"#PLAYER_NAME#"=>$GLOBALS["PLAYER_NAME"],"#HERIKA_NAME#"=>$promptCharacterName,"#NARRATOR_NAME#"=>function_exists('chimGetNarratorRoleplayName') ? chimGetNarratorRoleplayName() : 'The Narrator']);

    $prompt[] = 
        ["role" => "user", "content" => $diaryPrompt
        ]
    ;
    

    $contextData = array_merge($head, $prompt);
    if (function_exists('chimApplyNarratorRoleplayNameToContext')) {
        $contextData = chimApplyNarratorRoleplayNameToContext($contextData);
    }
    
    // Set the request type for diary so connector knows to use diary grammar
    $originalGameRequest = isset($GLOBALS["gameRequest"]) ? $GLOBALS["gameRequest"] : null;
    $GLOBALS["gameRequest"] = [0 => "diary", 1 => time(), 2 => $gameRequest[2], 3 => "Auto diary for " . $followerName];
    
    // Determine max tokens: prefer DB connector setting; fallback to driver config; then legacy memory; then default
    $maxTokens = null;
    if (isset($currentConnectorData["max_tokens"]) && $currentConnectorData["max_tokens"] !== null && $currentConnectorData["max_tokens"] !== '') {
        $maxTokens = (int)$currentConnectorData["max_tokens"];
    } elseif (isset($GLOBALS["CONNECTOR"][$currentConnectorData["driver"]]["max_tokens"])) {
        $maxTokens = (int)$GLOBALS["CONNECTOR"][$currentConnectorData["driver"]]["max_tokens"];
    } elseif (isset($GLOBALS["CONNECTOR"][$currentConnectorData["driver"]]["MAX_TOKENS_MEMORY"])) {
        $maxTokens = (int)$GLOBALS["CONNECTOR"][$currentConnectorData["driver"]]["MAX_TOKENS_MEMORY"];
    } else {
        $maxTokens = 2048;
    }

    // Temporarily disable any global FORCE_MAX_TOKENS to avoid accidental clamping
    $hadForce = array_key_exists('FORCE_MAX_TOKENS', $GLOBALS);
    $prevForce = $hadForce ? $GLOBALS['FORCE_MAX_TOKENS'] : null;
    if ($hadForce) { unset($GLOBALS['FORCE_MAX_TOKENS']); }

    $overrideParameters["MAX_TOKENS"] = $maxTokens;
    $connectionHandler = $connector->getConnector($GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]);
    chimLogPromptComposition(
        'diary',
        [
            'character_head' => $head,
            'history' => !empty($contextDataHistoric) ? $prompt[0] : [],
            'diary_instruction' => $diaryPrompt,
        ],
        $contextData
    );
    $buffer=$connectionHandler->fast_request($contextData,$overrideParameters,"diary");

    // Restore previous FORCE_MAX_TOKENS if it existed
    if ($hadForce) { $GLOBALS['FORCE_MAX_TOKENS'] = $prevForce; }

    
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
        $momentum=time();
        $diarySaved = $db->insert(
            'diarylog',
            array(
                'ts' => $gameRequest[1],
                'gamets' => $gameRequest[2],
                'topic' => $topic . " (Auto-diary: $eventType)",
                'content' => trim($buffer),
                'tags' => "Auto-diary,$eventType",
                'people' => $followerName,
                'location' => $location,
                'sess' => $momentum,
                'localts' => time()
            )
        );

        if ($diarySaved !== false && strcasecmp($followerName, 'The Narrator') !== 0) {
            chimPhysicalDiarySyncForNpc($followerName, (int)$gameRequest[2]);
        }
            
        // Log memory
        if (function_exists('logMemory')) {
            logMemory($followerName, $followerName, trim($buffer),  $momentum, $gameRequest[2], 'auto_diary', $gameRequest[1]);
        }

        // Send notification to plugin for this follower (same format as manual diary)
        echo $followerName."|rolecommand|DebugNotification@Diary Entry Written for ".$followerName.PHP_EOL;
        @ob_flush();
        @flush();
        
        return true;
    }
    
    
    return false;
}

function getDynamicProfileHistoryData($npcName) {
    $historyData = "";
    $lastPlace = "";
    $lastListener = "";
    $lastDateTime = "";
    $isNarratorTarget = ($npcName === "The Narrator");
    
    // Determine how much context history to use for dynamic profiles
    $dynamicProfileContextHistory = 50; // Default value
    if (isset($GLOBALS["CONTEXT_HISTORY_DYNAMIC_PROFILE"]) && $GLOBALS["CONTEXT_HISTORY_DYNAMIC_PROFILE"] > 0) {
        $dynamicProfileContextHistory = $GLOBALS["CONTEXT_HISTORY_DYNAMIC_PROFILE"];
    } elseif (isset($GLOBALS["CONTEXT_HISTORY"]) && $GLOBALS["CONTEXT_HISTORY"] > 0) {
        $dynamicProfileContextHistory = $GLOBALS["CONTEXT_HISTORY"];
    }
    
    foreach (json_decode(DataSpeechJournal($npcName, $dynamicProfileContextHistory), true) as $element) {
        $listenerName = trim((string)($element["listener"] ?? ""));
        $speakerName = trim((string)($element["speaker"] ?? ""));
        if (!$isNarratorTarget && ($listenerName == "The Narrator" || $speakerName == "The Narrator")) {
            continue;
        }
        if ($lastListener != $listenerName) {
            $listener = " (talking to {$listenerName})";
            $lastListener = $listenerName;
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
        
        $historyData .= trim("{$speakerName}:".trim((string)($element["speech"] ?? ""))." $listener $place $dateTime").PHP_EOL;
    }
    
    return $historyData;
}


function updateDynamicProfileField($npcName, $field, $historyData) {
    // Map field names to their corresponding HERIKA prompts
    $fieldMapping = [
        'personality' => 'DYNAMIC_PROMPT_PERSONALITY',
        'occupation' => 'DYNAMIC_PROMPT_OCCUPATION',
        'skills' => 'DYNAMIC_PROMPT_SKILLS',
        'speechstyle' =>'DYNAMIC_PROMPT_SPEECHSTYLE',
        'goals' => 'DYNAMIC_PROMPT_GOALS'
    ];
    
    if (!isset($fieldMapping[$field])) {
        Logger::warn("updateDynamicProfileField: Unknown field '$field' for $npcName");
        return false;
    }

    $isNarrator = ($npcName === "The Narrator");

    if ($isNarrator) {
        require_once(__DIR__ . "/core/narrator.class.php");
        $narrator = new Narrator();
        $npcData = $narrator->getNarratorData();
        $GLOBALS['NARRATOR_ROLEPLAY_NAME'] = $narrator->getRoleplayName();
        $promptNpcName = $narrator->getRoleplayName();
    } else {
        require_once(__DIR__ . "/core/npc_master.class.php");
        $npcMaster = new NpcMaster();
        $npcData = $npcMaster->getByName($npcName);
        $promptNpcName = $npcName;
    }

    if (!$npcData) {
        Logger::debug("updateDynamicProfileField: No profile found for $npcName");
        return false;
    }
    
    $promptName = $fieldMapping[$field];

    // Get current field value
    $currentValue = $npcData[$field] ?? '';
    
    // Map to database prompt keys (lowercase with underscores)
    $dbPromptKeyMapping = [
        'DYNAMIC_PROMPT_PERSONALITY' => 'dynamic_prompt_personality',
        'DYNAMIC_PROMPT_OCCUPATION' => 'dynamic_prompt_occupation',
        'DYNAMIC_PROMPT_SKILLS' => 'dynamic_prompt_skills',
        'DYNAMIC_PROMPT_SPEECHSTYLE' => 'dynamic_prompt_speechstyle',
        'DYNAMIC_PROMPT_GOALS' => 'dynamic_prompt_goals'
    ];
    
    // Load prompt from database with fallback to $GLOBALS
    $updatePrompt = null;
    if (isset($dbPromptKeyMapping[$promptName])) {
        try {
            $promptData = $GLOBALS["db"]->fetchOne("SELECT custom_prompt, default_prompt FROM prompts WHERE prompt_key = '{$dbPromptKeyMapping[$promptName]}'");
            if ($promptData) {
                $updatePrompt = (!empty($promptData['custom_prompt'])) ? $promptData['custom_prompt'] : $promptData['default_prompt'];
            }
        } catch (Exception $e) {
            Logger::warn("Failed to load {$promptName} from database, using fallback: " . $e->getMessage());
        }
    }
    
    // Fallback to $GLOBALS if database load failed
    if (empty($updatePrompt)) {
        $updatePrompt = $GLOBALS[$promptName] ?? '';
    }
    
    if (empty($updatePrompt)) {
        Logger::warn("updateDynamicProfileField: No prompt configured for field '$field' ($promptName)");
        return false;
    }
    
    // Replace placeholders in the prompt
    $updatePrompt = strtr($updatePrompt, [
        '{HERIKA_NAME}' => $promptNpcName,
        '{NARRATOR_NAME}' => function_exists('chimGetNarratorRoleplayName') ? chimGetNarratorRoleplayName() : 'The Narrator',
    ]);
    
    try {
        // Collect other profile fields for context (excluding the current field)
        $profileContext = [];
        if ($isNarrator) {
            $profileFields = [
                'core' => 'Core Identity',
                'npc_static_bio' => 'Basic Summary',
                'prompt_head' => 'Prompt Head / Style Rules',
                'personality' => 'Personality Traits',
                'speechstyle' => 'Speech Style',
                'goals' => 'Goals & Aspirations'
            ];
        } else {
            $profileFields = [
                'core' => 'Core Identity',
                'npc_static_bio' => 'Basic Summary',
                'personality' => 'Personality Traits',
                'appearance' => 'Physical Appearance',
                'occupation' => 'Occupation & Role',
                'skills' => 'Skills & Abilities',
                'speechstyle' => 'Speech Style',
                'goals' => 'Goals & Aspirations'
            ];
        }

        // Remove the current field from context
        unset($profileFields[$field]);

        foreach ($profileFields as $fieldName => $fieldLabel) {
            if (!empty(trim($npcData[$fieldName]))) {
                $profileValue = trim($npcData[$fieldName]);
                if ($isNarrator && function_exists('chimRenderNarratorRoleplayText')) {
                    $profileValue = chimRenderNarratorRoleplayText($profileValue);
                }
                $profileContext[] = "**{$fieldLabel}**: " . $profileValue;
            }
        }

        $profileContextString = !empty($profileContext) ? "\n\n* Current Character Profile:\n" . implode("\n\n", $profileContext) : '';

        // Build prompt for this specific field
        $head = [
            ["role" => "system", "content" => "You are an assistant. Analyze the dialogue history and character profile to update ONLY the " . ucfirst($field) . " for the character named '$promptNpcName'. Focus mostly on information about $promptNpcName and mostly ignore details about other characters mentioned in the dialogue."]
        ];

        $GLOBALS["HERIKA_NAME"] = $npcName; //note none of these prompts will contain #HERIKA_NAME, as the dialogue flow doesnt do this replacement (which may be a bug)
        $prompt = [
            ["role" => "user", "content" => "* Dialogue history:\n" . $historyData . ReplacePlayerNamePlaceholder($profileContextString)],
            ["role" => "user", "content" => "Character name: " . $promptNpcName . "\nCurrent " . ucfirst($field) . ":\n" . ReplacePlayerNamePlaceholder($isNarrator && function_exists('chimRenderNarratorRoleplayText') ? chimRenderNarratorRoleplayText($currentValue) : $currentValue)],
            ["role" => "user", "content" => ReplacePlayerNamePlaceholder($updatePrompt)]
        ];
        
        $contextData = array_merge($head, $prompt);
        if (function_exists('chimApplyNarratorRoleplayNameToContext')) {
            $contextData = chimApplyNarratorRoleplayNameToContext($contextData);
        }
        
        $connector=new LLMConnector();
        $currentConnectorData = $connector->getById($GLOBALS["CORE_CONNECTOR_PROFILES"]);
        $connectionHandler = $connector->getConnector($currentConnectorData);
        $GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"]=$currentConnectorData;
        $connector->setOldGlobals($currentConnectorData);

        // Get max tokens from core connector configuration (no hardcoded cap)
        $maxTokens = !empty($currentConnectorData["max_tokens"]) ? (int)$currentConnectorData["max_tokens"] : 4000;
        
        $buffer=$connectionHandler->fast_request($contextData, ["max_tokens" => $maxTokens],"profile");
        
        //$connectionHandler->close();
        
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

function saveDynamicProfileUpdates($npcName, $updatedFields, $db, $updateTimeStamp = true, $generationSource = null) {
    $newConfFile = md5($npcName);
    $path = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
    $configFile = $path . "conf" . DIRECTORY_SEPARATOR . "conf_$newConfFile.php";
    
    /*
    if (!file_exists($configFile)) {
        Logger::error("saveDynamicProfileUpdates: Config file not found for $npcName");
        return false;
    }
    */
    
    try {


        //

        $npcMaster=new NpcMaster();
        $currentNpcData=$npcMaster->getByName($npcName);
    
        if ($currentNpcData) {
            foreach ($updatedFields as $field => $newValue) {
                $currentNpcData[$field]=$newValue;
            }

            if ($generationSource === 'manual' || $generationSource === 'auto') {
                $extended = $npcMaster->getExtendedData($currentNpcData);
                $extended['auto_profile_pending'] = false;
                $extended['auto_profile_generated'] = true;
                $extended['auto_profile_generated_by'] = $generationSource;
                $extended['auto_profile_generated_at_gamets'] = DataLastKnownGameTS();
                unset($extended['auto_profile_last_error'], $extended['auto_profile_last_attempt_ts']);
                $currentNpcData = $npcMaster->setExtendedData($currentNpcData, $extended);
            }
            
            // Backup NPC.
            $npcMaster->backupNpcById($currentNpcData["id"]);

            if ($updateTimeStamp)
                $currentNpcData["gamets_last_updated"]=DataLastKnownGameTS();
            
            $npcMaster->updateByArray($currentNpcData);
            
        }
        
        /*
        if (file_exists($configFile)) {
            // Create backup
            @copy($configFile, $path . "conf" . DIRECTORY_SEPARATOR . ".conf_{$newConfFile}_" . time() . ".php");
            
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
                    if (strlen($newValue) > 50000) {
                        $newValue = substr($newValue, 0, 50000) . '... [truncated]'; // Limit length
                    }
                    $newValue = str_replace(['<?php', '<?', '?>'], ['&lt;?php', '&lt;?', '?&gt;'], $newValue); // Escape PHP tags
                    
                    // Additional sanitization for var_export compatibility
                    $newValue = str_replace('\\', '\\\\', $newValue); // Escape backslashes
                    $newValue = str_replace("\r\n", "\n", $newValue); // Normalize line endings
                    $newValue = str_replace("\r", "\n", $newValue); // Convert Mac line endings
                    $newValue = preg_replace('/\n{3,}/', "\n\n", $newValue); // Limit consecutive newlines
                }
                
                $currentConfContent[$fieldMapping[$field]]=$newValue;
                
         
            }
            
            // Write updated content back to file
            //file_put_contents($configFile, $content, LOCK_EX);
            write_php_assignments($currentConfContent,$configFile);
        }
        */
        
        Logger::info("saveDynamicProfileUpdates: Successfully saved updates for $npcName");
        return true;
        
    } catch (Exception $e) {
        Logger::error("saveDynamicProfileUpdates: Error saving updates for $npcName: " . $e->getMessage());
        return false;
    }
}

function queueDynamicProfileBatch(array $npcNames, array $gameRequest): string {
    global $db;

    $npcNames = array_values(array_unique(array_filter(array_map('trim', $npcNames), static fn($name) => $name !== '')));
    if (empty($npcNames)) {
        throw new InvalidArgumentException('Cannot queue an empty dynamic profile batch');
    }

    $queueData = [
        'timestamp' => time(),
        'npcs' => $npcNames,
        'gameRequest' => $gameRequest,
    ];
    $encoded = json_encode($queueData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $queueId = 'dynamic_profiles_queue_' . time() . '_' . uniqid();

    $db->upsertRowOnConflict('conf_opts', [
        'id' => $queueId,
        'value' => $encoded,
    ], 'id');

    return $queueId;
}

function chimPostgresBoolean($value): bool {
    return $value === true || $value === 1 || $value === '1' || $value === 't' || $value === 'true';
}

function triggerImmediateProfileProcessing(?callable $profileProcessor = null): array {
    global $db;

    $result = [
        'locked' => false,
        'jobs' => 0,
        'npcs' => 0,
        'updated' => 0,
    ];

    $lockRows = $db->fetchAll("SELECT pg_try_advisory_lock(hashtext('herika_dynamic_profile_worker')) AS acquired");
    if (empty($lockRows) || !chimPostgresBoolean($lockRows[0]['acquired'] ?? false)) {
        Logger::debug("triggerImmediateProfileProcessing: Another worker owns the queue lock");
        return $result;
    }

    $result['locked'] = true;
    $profileProcessor = $profileProcessor ?? 'processSingleDynamicProfile';

    try {
        $queueResults = $db->fetchAll("SELECT id, value FROM conf_opts WHERE id LIKE 'dynamic_profiles_queue_%' ORDER BY id LIMIT 5");
        if (empty($queueResults)) {
            Logger::debug("triggerImmediateProfileProcessing: No queue entries found");
            return $result;
        }

        Logger::info("triggerImmediateProfileProcessing: Processing " . count($queueResults) . " queue entries");

        foreach ($queueResults as $queueRow) {
            $queueId = $queueRow['id'];
            $queueJson = $queueRow['value'];
            $queueData = json_decode($queueJson, true);
            if (!$queueData || !isset($queueData['npcs']) || !isset($queueData['gameRequest'])) {
                Logger::error("triggerImmediateProfileProcessing: Invalid queue data for $queueId");
                $db->delete("conf_opts", "id = '" . $db->escape($queueId) . "'");
                continue;
            }

            $npcs = is_array($queueData['npcs']) ? $queueData['npcs'] : [];
            $gameRequest = $queueData['gameRequest'];
            Logger::info("triggerImmediateProfileProcessing: Processing " . count($npcs) . " NPCs");

            $successCount = 0;
            foreach ($npcs as $npcName) {
                try {
                    if ($profileProcessor($npcName, $gameRequest)) {
                        $successCount++;
                        Logger::debug("triggerImmediateProfileProcessing: Updated profile for $npcName");
                    }
                } catch (Throwable $e) {
                    Logger::error("triggerImmediateProfileProcessing: Error processing $npcName: " . $e->getMessage());
                }
            }

            // A claimed job is removed only after every NPC has been attempted. If the
            // worker process dies mid-job, the row remains and the next cycle retries it.
            $db->delete("conf_opts", "id = '" . $db->escape($queueId) . "'");
            Logger::info("triggerImmediateProfileProcessing: Completed job - updated $successCount of " . count($npcs) . " profiles");
            $result['jobs']++;
            $result['npcs'] += count($npcs);
            $result['updated'] += $successCount;
        }

        Logger::info("triggerImmediateProfileProcessing: Total processed: {$result['jobs']} jobs, {$result['npcs']} NPCs");
    } catch (Throwable $e) {
        Logger::error("triggerImmediateProfileProcessing: Fatal error: " . $e->getMessage());
    } finally {
        $db->fetchAll("SELECT pg_advisory_unlock(hashtext('herika_dynamic_profile_worker')) AS released");
    }

    return $result;
}
?>

