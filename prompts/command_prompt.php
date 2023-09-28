<?php


$COMMAND_PROMPT = "


";


$COMMAND_PROMPT_FUNCTIONS = "

Use functions to control {$GLOBALS["HERIKA_NAME"]}'s actions.
Use functions if {$GLOBALS["PLAYER_NAME"]} commands an order.

";



$DIALOGUE_TARGET="(Talking to {$GLOBALS["HERIKA_NAME"]})";
$MEMORY_OFFERING="{$GLOBALS["HERIKA_NAME"]} has some potentially related memories:";

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
