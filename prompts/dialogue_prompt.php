<?php


// New structure
// $PROMPTS["event"]["cue"] => array containing cues. This is the last text sent to LLM, should be an guided instruction
// $PROMPTS["event"]["player_request"] => array containing requirements. This is what is the player requesting for (a question, a comment...)
// $PROMPTS["event"]["extra"] =>  enable/disable, force mod, change token limit or define a transformer (non IA related) function.
// Full Prompt then is $PROMPT_HEAD + $HERIKA_PERS + $COMMAND_PROMPT + CONTEXT + requirement + cue

// Common patterns to use in most functions
$MAXIMUM_WORDS=($GLOBALS["MAX_WORDS_LIMIT"]>0)?"(Maximum {$GLOBALS["MAX_WORDS_LIMIT"]} words)":"";

$TEMPLATE_DIALOG="write {$GLOBALS["HERIKA_NAME"]}'s next dialogue line using this format \"{$GLOBALS["HERIKA_NAME"]}: ";

if (@is_array($GLOBALS["TTS"]["AZURE"]["validMoods"]) &&  sizeof($GLOBALS["TTS"]["AZURE"]["validMoods"])>0) 
    if ($GLOBALS["TTSFUNCTION"]=="azure")
        $TEMPLATE_DIALOG.="(optional way of speaking from this list [" . implode(",", $GLOBALS["TTS"]["AZURE"]["validMoods"]) . "])";

$TEMPLATE_DIALOG.=" \"";



if (isset($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) && $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) {
    $GLOBALS["MEMORY_STATEMENT"]=".USE #MEMORY.";
} else
    $GLOBALS["MEMORY_STATEMENT"]="";


if ($GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
    $TEMPLATE_ACTION="call a function to control {$GLOBALS["HERIKA_NAME"]} or";
    $TEMPLATE_ACTION=".USE TOOL CALLING.";    // WIP
} else {
    $TEMPLATE_ACTION="";
}

if (DMgetCurrentModel()=="openaijson") {
    $TEMPLATE_DIALOG="write {$GLOBALS["HERIKA_NAME"]}'s next dialogue lines. Avoid narrations.";
    $TEMPLATE_ACTION="";
}

if (DMgetCurrentModel()=="koboldcppjson") {
    $TEMPLATE_DIALOG="write {$GLOBALS["HERIKA_NAME"]}'s next dialogue lines. Avoid narrations.";
    $TEMPLATE_ACTION="";
}

if (DMgetCurrentModel()=="openrouterjson") {
    $TEMPLATE_DIALOG="write {$GLOBALS["HERIKA_NAME"]}'s next dialogue lines. Avoid narrations.";
    $TEMPLATE_ACTION="";
}
    
requireFilesRecursively(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR,"dialogue_prompt.php");

?>
