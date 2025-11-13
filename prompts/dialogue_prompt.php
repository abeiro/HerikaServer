<?php


// New structure
// $PROMPTS["event"]["cue"] => array containing cues. This is the last text sent to LLM, should be an guided instruction
// $PROMPTS["event"]["player_request"] => array containing requirements. This is what is the player requesting for (a question, a comment...)
// $PROMPTS["event"]["extra"] =>  enable/disable, force mod, change token limit or define a transformer (non IA related) function.
// Full Prompt then is $PROMPT_HEAD + $HERIKA_PERS + $COMMAND_PROMPT + CONTEXT + requirement + cue

// Common patterns to use in most functions
$MAXIMUM_WORDS=($GLOBALS["MAX_WORDS_LIMIT"]>0)?"(Maximum {$GLOBALS["MAX_WORDS_LIMIT"]} words)":"";

// Database Prompt (Dialogue)
// Determine which template to use based on minimize_quality_prompt setting
// Default to minimized (recommended for advanced models, matches UI default)
$useMinimizedTemplate = true;

if (function_exists('DMgetCurrentModel')) {
    $currentModel = DMgetCurrentModel();

    // Debug logging to verify setting is being read
    if (function_exists('logMessage')) {
        logMessage("[dialogue_prompt] Current model from DMgetCurrentModel: " . var_export($currentModel, true));
        if (isset($GLOBALS["CONNECTOR"][$currentModel])) {
            logMessage("[dialogue_prompt] Connector config exists for: " . $currentModel);
            if (isset($GLOBALS["CONNECTOR"][$currentModel]["minimize_quality_prompt"])) {
                $rawValue = $GLOBALS["CONNECTOR"][$currentModel]["minimize_quality_prompt"];
                logMessage("[dialogue_prompt] minimize_quality_prompt raw value: " . var_export($rawValue, true) . " (type: " . gettype($rawValue) . ")");
                $useMinimizedTemplate = (bool)$rawValue;
                logMessage("[dialogue_prompt] minimize_quality_prompt after (bool) cast: " . var_export($useMinimizedTemplate, true));
            } else {
                logMessage("[dialogue_prompt] minimize_quality_prompt setting NOT FOUND in connector config, using default: true");
            }
        } else {
            logMessage("[dialogue_prompt] No connector config found for model: " . var_export($currentModel, true));
        }
    }

    // Set template based on setting (fallback to checking if setting exists)
    if (isset($GLOBALS["CONNECTOR"][$currentModel]["minimize_quality_prompt"])) {
        // Cast to bool for proper string "1"/"0" handling
        $useMinimizedTemplate = (bool)$GLOBALS["CONNECTOR"][$currentModel]["minimize_quality_prompt"];
    }
}

if ($useMinimizedTemplate) {
    // Minimized template - just the core instruction (recommended for advanced models)
    $TEMPLATE_DIALOG = " Write {$GLOBALS["HERIKA_NAME"]}'s next dialogue line.";
    if (function_exists('logMessage')) {
        logMessage("[dialogue_prompt] Using MINIMIZED template");
    }
} else {
    // Full template with explicit quality instructions (for older/smaller models)
    $TEMPLATE_DIALOG = " Write {$GLOBALS["HERIKA_NAME"]}'s next dialogue line as a casual direct reaction to what was just said. Avoid narrations, be original, creative, knowledgeable, use your own thoughts. Review dialogue history to focus on conversation topic and to avoid repeating sentences and phraseology from previous dialog lines.";
    if (function_exists('logMessage')) {
        logMessage("[dialogue_prompt] Using FULL template with quality instructions");
    }
}

// Database Prompt (Dialogue)
// "should be a casual direct reaction to what was just said" is not always true, maybe last line was the same NPC,
// and is repeating (not copying) this same line, because is the 'direct reaction to what was just said'
// Example:
// Morgan|ScriptQueue|Though I suppose we could always settle it with a little *wrestling*.//Volkur//
// (a funcrec event comes, which just write  something into context. )
// Morgan|ScriptQueue|Wrestling, you say? Now *that* sounds like a fun way to get acquainted.//Vixi Talax//
//

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
