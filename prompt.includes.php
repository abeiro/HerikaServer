<?php




// IF GLOBAL INPUTCHAT (NO TARGET)
$GLOBALS["OVERRIDE_DIALOGUE_TARGET"]=false;
if (in_array($gameRequest[0],["ginputtext"])) {
    $gameRequest[0]="inputtext";
    $GLOBALS["OVERRIDE_DIALOGUE_TARGET"]=true;
}
if (in_array($gameRequest[0],["ginputtext_s"])) {
    $gameRequest[0]="inputtext_s";
    $GLOBALS["OVERRIDE_DIALOGUE_TARGET"]=true;
}

require_once(__DIR__ . DIRECTORY_SEPARATOR . "prompts/prompts.php");
$PROMPT_HEAD = ($GLOBALS["PROMPT_HEAD"]) ? $GLOBALS["PROMPT_HEAD"] : "Let\'s roleplay in the Universe of Skyrim. I\'m {$GLOBALS["PLAYER_NAME"]} ";

/* 
 * Info gathering to mangle function definitions. This will enforce some parameters to be fixed-
 */

$FUNCTION_PARM_MOVETO=DataPosibleLocationsToGo();		// To avoid moving to non existant target, lets limit available targets to the real ones in function definition
if (!isset($FUNCTION_PARM_MOVETO))
	$FUNCTION_PARM_MOVETO=[];
$FUNCTION_PARM_MOVETO[]=$GLOBALS["PLAYER_NAME"];


$FUNCTION_PARM_INSPECT=DataPosibleInspectTargets();	// To avoid moving to non existant target, lets limit available targets to the real ones in function definition
if (!isset($FUNCTION_PARM_INSPECT))
	$FUNCTION_PARM_INSPECT=[];
$FUNCTION_PARM_INSPECT[]=$GLOBALS["PLAYER_NAME"];


require_once(__DIR__.DIRECTORY_SEPARATOR."prompts".DIRECTORY_SEPARATOR."command_prompt.php");

if ($GLOBALS["OVERRIDE_DIALOGUE_TARGET"]) {
    if (isset($GLOBALS["USING_DEFAULT_PROFILE"])&&($GLOBALS["USING_DEFAULT_PROFILE"]))
        $DIALOGUE_TARGET="(Talking to Narrator)";
    else
        $DIALOGUE_TARGET="(Talking to everyone)";
}
require_once(__DIR__.DIRECTORY_SEPARATOR . "functions" . DIRECTORY_SEPARATOR . "functions.php");


/* This will use the extra key from PROMPTS array to do some things 
 (enable/disable, force mod, change token limit oe define a transformer (non IA related) function.
 */

if (isset($PROMPTS[$gameRequest[0]]["extra"])) {
	if (isset($PROMPTS[$gameRequest[0]]["extra"]["mood"]))
		$GLOBALS["FORCE_MOOD"] = $PROMPTS[$gameRequest[0]]["extra"]["mood"];
	if (isset($PROMPTS[$gameRequest[0]]["extra"]["force_tokens_max"]))
		$GLOBALS["FORCE_MAX_TOKENS"] = $PROMPTS[$gameRequest[0]]["extra"]["force_tokens_max"];
	if (isset($PROMPTS[$gameRequest[0]]["extra"]["transformer"]))
		$GLOBALS["TRANSFORMER_FUNCTION"] = $PROMPTS[$gameRequest[0]]["extra"]["transformer"];
	if (isset($PROMPTS[$gameRequest[0]]["extra"]["dontuse"]))
		if (($PROMPTS[$gameRequest[0]]["extra"]["dontuse"]))
			return "";


}

?>
