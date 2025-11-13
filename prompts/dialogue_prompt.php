<?php


// New structure
// $PROMPTS["event"]["cue"] => array containing cues. This is the last text sent to LLM, should be an guided instruction
// $PROMPTS["event"]["player_request"] => array containing requirements. This is what is the player requesting for (a question, a comment...)
// $PROMPTS["event"]["extra"] =>  enable/disable, force mod, change token limit or define a transformer (non IA related) function.
// Full Prompt then is $PROMPT_HEAD + $HERIKA_PERS + $COMMAND_PROMPT + CONTEXT + requirement + cue

// Common patterns to use in most functions
$MAXIMUM_WORDS=($GLOBALS["MAX_WORDS_LIMIT"]>0)?"(Maximum {$GLOBALS["MAX_WORDS_LIMIT"]} words)":"";

// Legacy Database Prompt (Dialogue) - Commented out, now handled by configurable toggle below
// $TEMPLATE_DIALOG=" Write {$GLOBALS["HERIKA_NAME"]}'s next dialogue line should be a casual direct reaction to what was just said." .
// " Avoid narrations, be original, creative, knowledgeable, use your own thoughts. " .
// " Review dialogue history to focus on conversation topic and to avoid repeating sentences and phraseology from previous dialog lines." .
// " {$GLOBALS["HERIKA_NAME"]}'s next dialogue lines will use this format \"{$GLOBALS["HERIKA_NAME"]}: ";

// Database Prompt (Dialogue)
// "should be a casual direct reaction to what was just said" is not always true, maybe last line was the same NPC,
// and is repeating (not copying) this same line, because is the 'direct reaction to what was just said'
// Example:
// Morgan|ScriptQueue|Though I suppose we could always settle it with a little *wrestling*.//Volkur//
// (a funcrec event comes, which just write  something into context. )
// Morgan|ScriptQueue|Wrestling, you say? Now *that* sounds like a fun way to get acquainted.//Vixi Talax//
//

// Configurable quality instructions toggle
// Check connector-specific setting for minimize_quality_prompt (defaults to true/minimized for better advanced model performance)
$useMinimizedPrompt = true; // Default to minimized (recommended for advanced models like Claude 4.5, GPT-4, etc.)

if (function_exists('DMgetCurrentModel')) {
    $currentModel = DMgetCurrentModel();
    logMessage("[dialogue_prompt] Current model: " . $currentModel);
    if (isset($GLOBALS["CONNECTOR"][$currentModel]["minimize_quality_prompt"])) {
        $useMinimizedPrompt = (bool)$GLOBALS["CONNECTOR"][$currentModel]["minimize_quality_prompt"];
        logMessage("[dialogue_prompt] minimize_quality_prompt setting found: " . ($useMinimizedPrompt ? 'true' : 'false'));
    } else {
        logMessage("[dialogue_prompt] minimize_quality_prompt setting NOT found, using default: true");
    }
} else {
    logMessage("[dialogue_prompt] DMgetCurrentModel function not found");
}

if ($useMinimizedPrompt) {
    // Minimized version (recommended for advanced models)
    // Core instruction only - advanced models inherently understand to be creative and avoid repetition
    $TEMPLATE_DIALOG = " Write {$GLOBALS["HERIKA_NAME"]}'s next dialogue line.";
    logMessage("[dialogue_prompt] Using MINIMIZED prompt template");
} else {
    // Default/Legacy version (for older/smaller models that may benefit from explicit guidance)
    $TEMPLATE_DIALOG = " Write {$GLOBALS["HERIKA_NAME"]}'s next dialogue line." .
    " Avoid narrations, be original, creative, knowledgeable, use your own thoughts. " .
    " Review dialogue history to focus on conversation topic and to avoid repeating sentences and phraseology from previous dialog lines.";
    logMessage("[dialogue_prompt] Using FULL/LEGACY prompt template");
}

// Legacy commented versions preserved for reference
// $TEMPLATE_DIALOG="write {$GLOBALS["HERIKA_NAME"]}'s next dialogue line using this format \"{$GLOBALS["HERIKA_NAME"]}: ";



if (@is_array($GLOBALS["TTS"]["AZURE"]["validMoods"]) &&  sizeof($GLOBALS["TTS"]["AZURE"]["validMoods"])>0) 
    if ($GLOBALS["TTSFUNCTION"]=="azure")
        $TEMPLATE_DIALOG.="(optional way of speaking from this list [" . implode(",", $GLOBALS["TTS"]["AZURE"]["validMoods"]) . "])";

//$TEMPLATE_DIALOG.=" \"";



if (isset($GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) && $GLOBALS["FEATURES"]["MEMORY_EMBEDDING"]["ENABLED"]) {
    $GLOBALS["MEMORY_STATEMENT"]=".USE #MEMORY.";
} else
    $GLOBALS["MEMORY_STATEMENT"]="";


if ($GLOBALS["FUNCTIONS_ARE_ENABLED"]) {
    $TEMPLATE_ACTION="call a function to control {$GLOBALS["HERIKA_NAME"]} or";
    $TEMPLATE_ACTION="(Check #ACTIONS section to choose an appropiate action for this character if needed)";    // WIP
} else {
    $TEMPLATE_ACTION="";
}

// Database Prompt (Dialogue should all be one)
/* aren't these redundant?
if (DMgetCurrentModel()=="openaijson") {
    $TEMPLATE_DIALOG="write {$GLOBALS["HERIKA_NAME"]}'s next dialogue lines. Avoid narrations.";
    $TEMPLATE_ACTION="";
}

if (DMgetCurrentModel()=="google_openaijson") {
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
  */
  
requireFilesRecursively(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR,"dialogue_prompt.php");

?>
