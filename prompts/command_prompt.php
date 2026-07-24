<?php

$rootPath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;

// Ensure PLAYER_NAME is defined before using in prompt strings
if (!isset($GLOBALS["PLAYER_NAME"]) || $GLOBALS["PLAYER_NAME"] === '') {
    $safePlayerName = 'Player';
    try {
        require_once($rootPath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php");
        chimRuntimeBootstrapIfNeeded($rootPath, [
            'run_db_updates' => false,
            'load_general_settings' => false,
            'load_stt_connector' => false,
            'load_itt_connector' => false,
            'load_player_name' => true,
        ]);
        if (isset($GLOBALS["PLAYER_NAME"]) && $GLOBALS["PLAYER_NAME"] !== '') {
            $safePlayerName = (string)$GLOBALS["PLAYER_NAME"];
        }
    } catch (Throwable $_) {
        // Ignore and use fallback
    }
    $GLOBALS["PLAYER_NAME"] = $safePlayerName;
}

$COMMAND_PROMPT = "";

// Database Prompt (Command Prompt)
$COMMAND_PROMPT_FUNCTIONS="\n\n#Available Actions\nUse if your character needs to perform an action:";
/*
$COMMAND_PROMPT_FUNCTIONS = "
Use tool calling to control {$GLOBALS["HERIKA_NAME"]}'s actions.
Use tool calling if {$GLOBALS["PLAYER_NAME"]} commands an order.
Only perform actions and tool calling if your character would find it necessary or must have to, even if it contradicts {$GLOBALS["PLAYER_NAME"]}'s requests.
";
*/
// Database Prompt (Command Coherent Prompt)
$COMMAND_PROMPT_ENFORCE_ACTIONS="Choose coherent ACTION to obey {$GLOBALS["PLAYER_NAME"]}.";

if (!function_exists('chimGetDialogueTargetVerb')) {
    function chimGetDialogueTargetVerb()
    {
        $mode = strtoupper((string)($GLOBALS["CHIM_EXECUTION_MODE"] ?? ""));
        if ($mode === "WHISPER") {
            return "Whispering to";
        }
        if ($mode === "CLOSE") {
            return "Speaking privately to";
        }
        if ($mode === "SHOUT") {
            return "Shouting to";
        }

        return "Talking to";
    }
}

$dialogueTargetVerb = chimGetDialogueTargetVerb();
$DIALOGUE_TARGET="({$dialogueTargetVerb} {$GLOBALS["HERIKA_NAME"]})";
$MEMORY_OFFERING="";

$RESPONSE_OK_NOTED="Okay, noted.";

$ERROR_OPENAI="Didn't hear you, can you repeat?";								// Say something logical, as this response will be pushed in next call.
$ERROR_OPENAI_REQLIMIT="Be quiet, I'm having a flashback, give me a minute";	// Say something logical, as this response will be pushed in next call.
$ERROR_OPENAI_POLICY="I can't think clearly now...";							// Say something logical, as this response will be pushed in next call. 


if (isset($GLOBALS["CORE_LANG"]))
	if (file_exists(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$GLOBALS["CORE_LANG"].DIRECTORY_SEPARATOR."command_prompt.php")) 
		require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$GLOBALS["CORE_LANG"].DIRECTORY_SEPARATOR."command_prompt.php");
	
// You can override prompts here

if (file_exists(__DIR__.DIRECTORY_SEPARATOR."command_prompt_custom.php"))
    require_once(__DIR__.DIRECTORY_SEPARATOR."command_prompt_custom.php");

?>
