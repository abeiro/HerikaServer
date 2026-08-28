<?php

$GLOBALS["ENGINE_ROOT"] = __DIR__ . DIRECTORY_SEPARATOR;
$GLOBALS["ENGINE_PATH"] = $GLOBALS["ENGINE_ROOT"];
$enginePath             = $GLOBALS["ENGINE_ROOT"];

require_once $enginePath . "lib/runtime_bootstrap.php";
chimRuntimeBootstrap($enginePath, [
    'load_general_settings' => true,
    'load_stt_connector' => false,
    'load_itt_connector' => false,
    'load_player_name' => true,
    'load_narrator' => true,
]);

if (!chimIsGlobalLlmConnectorEnabled('CORE_CONNECTOR_PLAYER')) {
    exit(0);
}
require_once $enginePath . "lib/logger.php";
require_once $enginePath . "lib/model_dynmodel.php";
require_once $enginePath . "prompts/command_prompt.php";
require_once $enginePath . "lib/chat_helper_functions.php";
require_once $enginePath . "lib/data_functions.php";
require_once $enginePath . "lib/rolemaster_helpers.php";

$GLOBALS["db"] = $GLOBALS["db"] ?? new sql();

function chimPlayerRewritePromptFromManager(string $promptKey, string $fallback, array $replacements): string
{
    try {
        $db = $GLOBALS["db"] ?? null;
        if (!$db) {
            return strtr($fallback, $replacements);
        }

        $escapedPromptKey = $db->escape($promptKey);
        $row = $db->fetchOne("SELECT COALESCE(custom_prompt, default_prompt) AS prompt FROM prompts WHERE prompt_key='{$escapedPromptKey}' LIMIT 1");
        $prompt = trim((string)($row['prompt'] ?? ''));
        if ($prompt === '') {
            $prompt = $fallback;
        }

        return strtr($prompt, $replacements);
    } catch (Throwable $e) {
        Logger::warn("Player rewrite prompt lookup failed for {$promptKey}: " . $e->getMessage());
        return strtr($fallback, $replacements);
    }
}

// New profile system
require_once $enginePath . "lib/core/api_badge.class.php";
require_once $enginePath . "lib/core/llm_connector.class.php";
require_once $enginePath . "lib/core/tts_connector.class.php";
require_once $enginePath . "lib/core/npc_master.class.php";
require_once $enginePath . "lib/core/core_profiles.class.php";

SaveOriginalHerikaName();
$GLOBALS["HERIKA_NAME"] = "(actor)";

// Initialize function parameters before requiring functions.php
$GLOBALS["FUNCTION_PARM_INSPECT"] = [];
$GLOBALS["FUNCTION_PARM_MOVETO"]  = [];
$GLOBALS["F_NAMES"]               = [];

require $enginePath . "functions/functions.php";

$connector            = new LLMConnector();
$currentConnectorData = $connector->getById($GLOBALS["CORE_CONNECTOR_PLAYER"]);
$connectionHandler    = $connector->getConnector($currentConnectorData);

$GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"] = $currentConnectorData;
$GLOBALS["CURRENT_CONNECTOR"]                = $currentConnectorData["driver"];

$connector->setOldGlobals($currentConnectorData);

// Make functions.php data global

$GLOBALS["FUNCTIONS_ARE_ENABLED"] = false;

// Some functions need this setted */
$res                       = $GLOBALS["db"]->fetchAll("select max(gamets)+1 as gamets,max(ts)+1 as ts  from eventlog order by gamets desc limit 1 offset 0");
$GLOBALS["gameRequest"]    = ["inputtext"];
$GLOBALS["gameRequest"][2] = $res[0]["gamets"] + 1;

$GLOBALS["CHIM_NO_EXAMPLES"] = true; // When no assistant entry in history, will try ti provide a bogus example.

if (empty($_GET["speech"])) {
    if (!empty($argv[1])) {
        $_GET["speech"] = $argv[1];
    }
}
$targetNpc = !empty($argv[2]) ? trim($argv[2]) : '';

if (! isset($GLOBALS["CHIM_CORE_CURRENT_CONNECTOR_DATA"])) {
    error_log("Choose a LLM model and connector. Used connector: '{$GLOBALS["CORE_CONNECTOR_DIRECTOR"]}'", S_LOG_CRITICAL);

} else {

    error_log("Using {$GLOBALS["CURRENT_CONNECTOR"]} <{$argv[1]}>");

    $contextDataHistoric = DataLastDataExpandedFor("", -15);
    $contextDataHistoric = array_merge([["role" => "user", "content" => "# HISTORIC DIALOGUE AND EVENTS IN CHRONOLOGICAL ORDER"]], $contextDataHistoric);

    $contextDataWorld = DataLastInfoFor("", -2, $addNPCDescriptions = true, $excludeBusy = true);
    $contextDataFull  = array_merge($contextDataWorld??[], $contextDataHistoric??[]);
    $historyData      = "";
    foreach ($contextDataFull as $element) {
        $historyData .= trim("{$element["content"]}") . PHP_EOL . PHP_EOL;
    }

    // Load player data from core_player table
    $playerAppearance = '';
    $playerBio = '';
    $playerSpeechStyle = '';
    try {
        require_once(__DIR__ . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "player.class.php");
        $player = new Player();
        $playerAppearance = trim((string)($player->get('appearance') ?? ''));
        $playerBio = ResolvePlayerBackstory($player);
        $playerSpeechStyle = trim((string)($player->get('speech_style') ?? ''));
    } catch (Exception $e) {
        error_log("Could not load player data from core_player: " . $e->getMessage());
    }
    if (empty($playerSpeechStyle) && isset($GLOBALS["PLAYER_SPEECH_STYLE"]) && !empty($GLOBALS["PLAYER_SPEECH_STYLE"])) {
        $playerSpeechStyle = $GLOBALS["PLAYER_SPEECH_STYLE"];
    }

    if (empty($playerBio) && !empty($playerAppearance)) {
        $playerBio = $playerAppearance;
    }

    // Build player character context block
    $playerContext = "";
    if (!empty($playerBio)) {
        $resolvedBio = strtr($playerBio, ["#PLAYER_NAME#" => $GLOBALS["PLAYER_NAME"]]);
        $playerContext .= "Player Backstory: " . $resolvedBio . "\n";
    }
    if (!empty($playerAppearance) && trim($playerAppearance) !== trim($playerBio)) {
        $resolvedAppearance = strtr($playerAppearance, ["#PLAYER_NAME#" => $GLOBALS["PLAYER_NAME"]]);
        $playerContext .= "Player Appearance: " . $resolvedAppearance . "\n";
    }
    if (!empty($playerSpeechStyle)) {
        $playerContext .= "Player Speech Style: " . $playerSpeechStyle . "\n";
    }

    // Build system message: simple identity + PROMPT_HEAD as roleplay ruleset
    $npcLine = (!empty($targetNpc) && $targetNpc !== '(actor)') ? " You are currently speaking to {$targetNpc}." : '';
    $promptHead = '';
    if (!empty($GLOBALS["PROMPT_HEAD"])) {
        $promptHead = strtr($GLOBALS["PROMPT_HEAD"], ["#HERIKA_NAME#" => $GLOBALS["PLAYER_NAME"]]);
    }
    $systemContent = "You are roleplaying as {$GLOBALS["PLAYER_NAME"]}.{$npcLine}";
    if (!empty($promptHead)) {
        $systemContent .= "\n\n" . $promptHead;
    }
    if (!empty($playerContext)) {
        $systemContent .= "\n\n# Character Context\n" . $playerContext;
    }

    $removePlayerAutochatAsterisks = shouldRemovePlayerAutochatAsterisks();

    // Build instruction
    if (!$_GET["speech"]) {
        $instruction = "Write dialogue for {$GLOBALS["PLAYER_NAME"]}.";
        $outputInstruction = "Output only the final spoken dialogue line. No narration. No stage directions. No speaker names. No bracketed comments.";
    } else {
        $sourceSpeech = $_GET["speech"];
        if ($removePlayerAutochatAsterisks) {
            $sourceSpeech = sanitizePlayerRespeechText($sourceSpeech, $GLOBALS["PLAYER_NAME"] ?? null);
        }

        $promptReplacements = [
            '{PLAYER_NAME}' => $GLOBALS["PLAYER_NAME"],
            '{SPEECH}' => $sourceSpeech,
        ];

        if ($removePlayerAutochatAsterisks) {
            $instruction = chimPlayerRewritePromptFromManager(
                'player_respeech_rewrite_strip_prompt',
                "Rewrite dialogue for {PLAYER_NAME}, using this text as source \"{PLAYER_NAME}:{SPEECH}\". Use comments between brackets only as guidance for tone, target, length, and verbosity. Do not repeat bracketed comments, stage directions, narration, asterisked narration, or speaker names in the output.",
                $promptReplacements
            );
            $outputInstruction = chimPlayerRewritePromptFromManager(
                'player_respeech_output_strip_prompt',
                "Output only the final spoken dialogue line. No narration. No stage directions. No asterisked narration. No speaker names. No bracketed comments.",
                $promptReplacements
            );
        } else {
            $instruction = chimPlayerRewritePromptFromManager(
                'player_respeech_rewrite_prompt',
                "Rewrite dialogue for {PLAYER_NAME}, using this text as source \"{PLAYER_NAME}:{SPEECH}\". Use comments between brackets only as guidance for tone, target, length, and verbosity. If the source includes brief narration or stage business before the spoken line, preserve it as one short third-person narration block in single asterisks before the dialogue. Do not repeat bracketed comments or speaker names in the output.",
                $promptReplacements
            );
            $outputInstruction = chimPlayerRewritePromptFromManager(
                'player_respeech_output_prompt',
                "Output only the rewritten line. If the source includes brief leading narration, keep at most one short leading narration block in single asterisks before the spoken dialogue. Keep spoken dialogue outside the asterisks. No speaker names. No bracketed comments.",
                $promptReplacements
            );
        }
    }

    $prompt[] = ['role' => 'system', 'content' => $systemContent];
    $prompt[] = ['role' => 'user',   'content' => "# Contextual data\n$historyData"];
    $prompt[] = ['role' => 'user',   'content' => $instruction];
    $prompt[] = ['role' => 'user',   'content' => $outputInstruction];
    $customParm["MAX_TOKENS"] = 4000;

    $buffer = $connectionHandler->fast_request($prompt, ["MAX_TOKENS" => 4000]);
    $buffer = str_replace("{$GLOBALS["PLAYER_NAME"]}:}", "", $buffer);
    echo trim($buffer) . PHP_EOL;

}

Logger::info("Successfully logged instruction command to responselog");
