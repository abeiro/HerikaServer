<?php

if (!defined("MAXIMUM_SENTENCE_SIZE")) {
    define("MAXIMUM_SENTENCE_SIZE", 125);
}
if (!defined("MINIMUM_SENTENCE_SIZE")) {
    define("MINIMUM_SENTENCE_SIZE", 15);
}

$aiProfileEnginePath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once $aiProfileEnginePath . "lib" . DIRECTORY_SEPARATOR . "model_dynmodel.php";
require_once $aiProfileEnginePath . "lib" . DIRECTORY_SEPARATOR . "chat_helper_functions.php";
require_once $aiProfileEnginePath . "lib" . DIRECTORY_SEPARATOR . "data_functions.php";
require_once $aiProfileEnginePath . "lib" . DIRECTORY_SEPARATOR . "logger.php";
require_once $aiProfileEnginePath . "lib" . DIRECTORY_SEPARATOR . "utils_game_timestamp.php";
require_once $aiProfileEnginePath . "lib" . DIRECTORY_SEPARATOR . "rolemaster_helpers.php";
require_once $aiProfileEnginePath . "lib" . DIRECTORY_SEPARATOR . "dynamic_update_util.php";
require_once $aiProfileEnginePath . "lib/core/npc_master.class.php";
require_once $aiProfileEnginePath . "lib/core/core_profiles.class.php";
require_once $aiProfileEnginePath . "lib/core/api_badge.class.php";
require_once $aiProfileEnginePath . "lib/core/llm_connector.class.php";
require_once $aiProfileEnginePath . "lib" . DIRECTORY_SEPARATOR . "lazy_xml.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "ai_profile_generation_helper.php";

function aiProfileTargetFields(): array
{
    return [
        'core'           => 'Core Identity',
        'npc_static_bio' => 'Backstory',
        'personality'    => 'Personality Traits',
        'occupation'     => 'Occupation & Role',
        'skills'         => 'Skills & Abilities',
        'speechstyle'    => 'Speech Style',
        'goals'          => 'Goals & Aspirations',
    ];
}

function aiProfileHasMeaningfulAutofillData(array $currentNpcData): bool
{
    foreach (array_keys(aiProfileTargetFields()) as $field) {
        if (trim((string)($currentNpcData[$field] ?? '')) !== '') {
            return true;
        }
    }

    return false;
}

function aiProfileMarkPendingAutofill(array $currentNpcData, NpcMaster $npcMaster, int $trigger): array
{
    $extended = $npcMaster->getExtendedData($currentNpcData);
    $extended['auto_profile_pending'] = true;
    $extended['auto_profile_source'] = 'import_blank_profile';
    $extended['auto_profile_trigger'] = max(10, min(100, $trigger));
    $extended['auto_profile_generated'] = false;
    $extended['auto_profile_generated_by'] = null;
    $extended['auto_profile_generated_at_gamets'] = null;
    unset($extended['auto_profile_last_error'], $extended['auto_profile_last_attempt_ts']);

    return $npcMaster->setExtendedData($currentNpcData, $extended);
}

function aiProfileClearPendingAutofill(array $currentNpcData, NpcMaster $npcMaster): array
{
    $extended = $npcMaster->getExtendedData($currentNpcData);
    $extended['auto_profile_pending'] = false;
    unset($extended['auto_profile_last_error'], $extended['auto_profile_last_attempt_ts']);
    return $npcMaster->setExtendedData($currentNpcData, $extended);
}

function aiProfileShouldAttemptAutofill(array $currentNpcData, NpcMaster $npcMaster): bool
{
    if (($currentNpcData['npc_name'] ?? '') === 'The Narrator') {
        return false;
    }

    if (!empty($currentNpcData['lock_profile'])) {
        return false;
    }

    $extended = $npcMaster->getExtendedData($currentNpcData);
    if (empty($extended['auto_profile_pending'])) {
        return false;
    }

    if (aiProfileHasMeaningfulAutofillData($currentNpcData)) {
        $currentNpcData = aiProfileClearPendingAutofill($currentNpcData, $npcMaster);
        $npcMaster->updateByArray($currentNpcData);
        return false;
    }

    $lastAttemptTs = intval($extended['auto_profile_last_attempt_ts'] ?? 0);
    if ($lastAttemptTs > 0 && (time() - $lastAttemptTs) < 300) {
        return false;
    }

    return true;
}

function aiProfileGetAutofillTrigger(array $currentNpcData, NpcMaster $npcMaster): int
{
    $extended = $npcMaster->getExtendedData($currentNpcData);
    $trigger = intval($extended['auto_profile_trigger'] ?? ($GLOBALS['AUTOFILL_CUSTOM_PROFILES_TRIGGER'] ?? 20));
    return max(10, min(100, $trigger));
}

function aiProfileLoadPromptTemplate($db): string
{
    $profilePrompt = null;
    try {
        $promptData = $db->fetchOne("SELECT custom_prompt, default_prompt FROM prompts WHERE prompt_key = 'character_profile_generation'");
        if ($promptData) {
            $profilePrompt = !empty($promptData['custom_prompt']) ? $promptData['custom_prompt'] : $promptData['default_prompt'];
        }
    } catch (Exception $e) {
        Logger::warn("Failed to load profile generation prompt from database, using hardcoded fallback: " . $e->getMessage());
    }

    if ($profilePrompt) {
        return $profilePrompt;
    }

    return
        "The main character in this logbook is {HERIKA_NAME}.{CHARACTER_SEED}\n" .
        "Read the context history (context_history) and the recent memories (middle_term_memory).\n" .
        "Based on all this information, generate a concise character sheet for {HERIKA_NAME}.\n\n" .
        "Return XML with ONLY these fields:\n" .
        "<core> Text. Core identity, name/race/gender, and most remarkable role.\n" .
        "<npc_static_bio> Text. Backstory, history, and important background facts.\n" .
        "<personality> Text. Personality traits, behavior, outlook, and important emotional patterns.\n" .
        "<occupation> Text. Current occupation, role, and duties.\n" .
        "<skills> Text. Skills, training, talents, combat or magical abilities.\n" .
        "<speechstyle> Text. How the character speaks and communicates.\n" .
        "<goals> Text. Long-term goals, motivations, and aspirations.\n";
}

function aiProfileBuildPromptInstructions(string $npcName, array $currentNpcData, $db, string $userCustomPrompt = ''): string
{
    $npcGender = trim((string)($currentNpcData['gender'] ?? ''));
    $npcRace = trim((string)($currentNpcData['race'] ?? ''));
    $characterSeed = '';
    if ($npcGender !== '' && $npcRace !== '') {
        $characterSeed = " This character is {$npcRace} {$npcGender}.";
    } elseif ($npcGender !== '') {
        $characterSeed = " This character is {$npcGender}.";
    } elseif ($npcRace !== '') {
        $characterSeed = " This character is {$npcRace}.";
    }

    $profilePromptProcessed = str_replace(
        ['{HERIKA_NAME}', '{CHARACTER_SEED}'],
        [$npcName, $characterSeed],
        aiProfileLoadPromptTemplate($db)
    );

    $requiredTags = implode(', ', array_map(function ($field) {
        return "<{$field}>";
    }, array_keys(aiProfileTargetFields())));

    $profilePromptProcessed .= "\n\nReturn ONLY these XML tags and no others: {$requiredTags}.";

    if ($userCustomPrompt !== '') {
        $profilePromptProcessed .= "\n\nADDITIONAL INSTRUCTIONS FROM USER:\n{$userCustomPrompt}";
    }

    return $profilePromptProcessed;
}

function aiProfileParseResponseFields(string $buffer): array
{
    $parsed = [];
    $parsedFieldCount = 0;
    foreach (array_keys(aiProfileTargetFields()) as $field) {
        $fieldContent = trim((string)strip_tags((string)current(manual_get_all_tag_contents($buffer, $field))));
        $parsed[$field] = $fieldContent;
        if ($fieldContent !== '') {
            $parsedFieldCount++;
        }
    }

    return [$parsed, $parsedFieldCount];
}

function aiProfileStampGenerationFailure(array $currentNpcData, NpcMaster $npcMaster, string $error): void
{
    $extended = $npcMaster->getExtendedData($currentNpcData);
    $extended['auto_profile_last_error'] = $error;
    $extended['auto_profile_last_attempt_ts'] = time();
    $currentNpcData = $npcMaster->setExtendedData($currentNpcData, $extended);
    $npcMaster->updateByArray($currentNpcData);
}

function aiProfileGenerate(array $options): array
{
    $startTime = microtime(true);
    $db = $options['db'];
    $name = trim((string)($options['name'] ?? ''));
    $source = trim((string)($options['source'] ?? 'manual'));
    $eventLimit = isset($options['event_limit']) ? intval($options['event_limit']) : 100;
    $eventLimit = max(10, min(200, $eventLimit));
    $userCustomPrompt = trim((string)($options['user_prompt'] ?? ''));
    $selectedEvents = $options['selected_events'] ?? [];
    $selectedEventsProvided = (bool)($options['selected_events_provided'] ?? false);
    if (!is_array($selectedEvents)) {
        $selectedEvents = [];
    }

    if ($name === '') {
        return [
            'done' => false,
            'error' => 'Missing NPC name.',
            'error_type' => 'missing_name',
        ];
    }

    $npcMaster = new NpcMaster();
    $currentNpcData = $npcMaster->getByName($name);
    if (!$currentNpcData) {
        return [
            'done' => false,
            'error' => 'NPC not found.',
            'error_type' => 'npc_missing',
        ];
    }

    if ($selectedEventsProvided) {
        $selectedEvents = aiProfileNormalizeSelectedEvents($selectedEvents);
    } elseif (empty($selectedEvents)) {
        $previewBundle = aiProfileBuildPreviewEvents($name, $currentNpcData, $db, $eventLimit);
        $selectedEvents = $previewBundle['events'];
    } else {
        $selectedEvents = aiProfileNormalizeSelectedEvents($selectedEvents);
    }

    $connector = new LLMConnector();
    if (isset($options['connector_id']) && trim((string)$options['connector_id']) !== '') {
        $connectorId = intval($options['connector_id']);
    } elseif ($source === 'auto') {
        $connectorId = intval($GLOBALS['CORE_CONNECTOR_DIARY'] ?? 0);
        if ($connectorId <= 0) {
            $connectorId = intval($GLOBALS['CORE_CONNECTOR_PROFILES'] ?? 0);
        }
    } else {
        $connectorId = intval($GLOBALS['CORE_CONNECTOR_PROFILES'] ?? 0);
    }
    $currentConnectorData = $connector->getById($connectorId);
    if (!$currentConnectorData) {
        return [
            'done' => false,
            'error' => 'Failed to initialize LLM connector. Check your connector configuration.',
            'error_type' => 'connector_init',
        ];
    }

    $profile = new CoreProfile();
    $currentProfileData = $profile->getById($currentNpcData['profile_id']);
    $connector->setOldGlobals($currentConnectorData);
    $npcMaster->setOldGlobalsFromCurrentNpcData($currentNpcData);

    $GLOBALS['HERIKA_NAME'] = $name;
    $GLOBALS["SCRIPTLINE_EXPRESSION"] = "";
    $GLOBALS["SCRIPTLINE_LISTENER"] = "";
    $GLOBALS["SCRIPTLINE_ANIMATION"] = "";

    $res = $db->fetchAll("select max(gamets) as last_gamets from eventlog");
    $res2 = $db->fetchAll("select max(ts) as ts from eventlog where gamets='{$res[0]["last_gamets"]}'");
    $last_gamets = $res[0]["last_gamets"] + 1;
    $gameRequest = ["inputtext", "0", $last_gamets, $name];
    $last_ts = $res2[0]["ts"] ?? time();
    $GLOBALS['gameRequest'] = $gameRequest;

    $dynamicBiography = buildDynamicBiography($GLOBALS);
    $extendedData = $npcMaster->getExtendedData($currentNpcData);
    if (isset($extendedData['middle_term_memory']) && is_array($extendedData['middle_term_memory']) && !empty($extendedData['middle_term_memory'])) {
        $middle_term_memory = end($extendedData['middle_term_memory']);
        $dynamicBiography .= "\n\n<middle_term_memory>\nPast events\n{$middle_term_memory}\n</middle_term_memory>";
    }

    $history = aiProfileBuildHistoryFromSelectedEvents($selectedEvents, $name);
    $instructions = aiProfileBuildPromptInstructions($name, $currentNpcData, $db, $userCustomPrompt);

    $prompt = [];
    $head = [[
        'role' => 'system',
        'content' => "You are a writing assistant. Examine this character sheet and any dialogue or events provided from the fictional universe of Skyrim (The Elder Scrolls)."
    ]];

    $prompt[] = ['role' => 'user', 'content' => "<character_sheet>\n{$name}:\n{$dynamicBiography}\n</character_sheet>"];
    $prompt[] = ['role' => 'user', 'content' => "<context_history>\nContext History\n{$history}\n</context_history>"];
    $prompt[] = ['role' => 'user', 'content' => $instructions];
    $contextData = array_merge($head, $prompt);

    Logger::debug(__LINE__ . " " . (microtime(true) - $startTime));

    $connectionHandler = $connector->getConnector($currentConnectorData);
    if (!$connectionHandler) {
        return [
            'done' => false,
            'error' => 'Failed to initialize LLM connector. Check your connector configuration.',
            'error_type' => 'connector_init',
        ];
    }

    $buffer = $connectionHandler->fast_request($contextData, ["MAX_TOKENS" => 2048, "temperature" => 0.9], "profile_generation");
    Logger::debug(__LINE__ . " " . (microtime(true) - $startTime));

    if (empty($buffer)) {
        $lastError = '';
        try {
            $auditResult = $db->fetchOne("SELECT result, url, connector FROM audit_request ORDER BY id DESC LIMIT 1");
            if ($auditResult && strpos($auditResult['result'], 'ERROR') === 0) {
                $lastError = $auditResult['result'];
            }
        } catch (Exception $e) {
        }

        $errorMessage = 'LLM did not return a response.';
        if ($lastError !== '') {
            $errorParts = explode('|', $lastError);
            if (count($errorParts) >= 3 && trim($errorParts[2]) !== '') {
                $errorMessage = trim($errorParts[2]);
            }
        }

        if ($source === 'auto') {
            aiProfileStampGenerationFailure($currentNpcData, $npcMaster, $errorMessage);
        }

        return [
            'done' => false,
            'error' => $errorMessage,
            'error_type' => 'no_response',
            'audit_detail' => $lastError,
        ];
    }

    [$parsedFields, $parsedFieldCount] = aiProfileParseResponseFields((string)$buffer);
    if ($parsedFieldCount === 0) {
        $errorMessage = 'The LLM response did not contain the expected profile fields.';
        if ($source === 'auto') {
            aiProfileStampGenerationFailure($currentNpcData, $npcMaster, $errorMessage);
        }

        return [
            'done' => false,
            'error' => $errorMessage,
            'error_type' => 'parse_error',
            'raw_length' => strlen((string)$buffer),
        ];
    }

    saveDynamicProfileUpdates($name, $parsedFields, $db, false, $source);

    Logger::info("AI Profile Generation: Successfully generated profile for NPC '{$name}' with {$parsedFieldCount} fields");

    return [
        'done' => true,
        'fields_updated' => $parsedFieldCount,
        'npc_name' => $name,
        'events_used' => count($selectedEvents),
        'source' => $source,
        'last_ts' => $last_ts,
        'profile_fields' => array_keys(array_filter($parsedFields, function ($value) {
            return trim((string)$value) !== '';
        })),
    ];
}
