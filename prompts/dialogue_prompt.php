<?php


// New structure
// $PROMPTS["event"]["cue"] => array containing cues. This is the last text sent to LLM, should be an guided instruction
// $PROMPTS["event"]["player_request"] => array containing requirements. This is what is the player requesting for (a question, a comment...)
// $PROMPTS["event"]["extra"] =>  enable/disable, force mod, change token limit or define a transformer (non IA related) function.
// Full Prompt then is $PROMPT_HEAD + $HERIKA_PERS + $COMMAND_PROMPT + CONTEXT + requirement + cue

// Common patterns to use in most functions
$MAXIMUM_WORDS=($GLOBALS["MAX_WORDS_LIMIT"]>0)?"(Maximum {$GLOBALS["MAX_WORDS_LIMIT"]} words)":"";

if (!function_exists('chimLoadManagedPromptTemplate')) {
    function chimLoadManagedPromptTemplate($promptKey, $fallbackPrompt, array $replacements = [], $logContext = "PROMPT")
    {
        global $db;

        $promptText = null;
        try {
            if (isset($db)) {
                $promptData = $db->fetchOne("SELECT custom_prompt, default_prompt FROM prompts WHERE prompt_key = '{$promptKey}'");
                if ($promptData) {
                    $promptText = (!empty($promptData['custom_prompt'])) ? $promptData['custom_prompt'] : $promptData['default_prompt'];
                }
            }
        } catch (Exception $e) {
            Logger::warn("[$logContext] Failed to load prompt from database, using hardcoded fallback: " . $e->getMessage());
        }

        if (!$promptText) {
            $promptText = $fallbackPrompt;
        }

        return strtr($promptText, $replacements);
    }
}


// Add narration instruction if inline narration is enabled (default to false if not set)
$inlineNarrationEnabled = isset($GLOBALS["INLINE_NARRATION_ENABLED"]) ? (bool)$GLOBALS["INLINE_NARRATION_ENABLED"] : false;
if ($inlineNarrationEnabled) {
    $TEMPLATE_DIALOG = chimLoadManagedPromptTemplate(
        'dialogue_line_inline_response',
        " Write {HERIKA_NAME}'s next prose/narration." .
        " Be original, creative, knowledgeable, use your own thoughts. " .
        " Review context history to focus on conversation topic and to avoid repeating sentences and phraseology from previous lines.{MAXIMUM_WORDS}",
        [
            "{HERIKA_NAME}" => $GLOBALS["HERIKA_NAME"],
            "{MAXIMUM_WORDS}" => $MAXIMUM_WORDS,
        ],
        "DIALOGUE_LINE_INLINE_RESPONSE"
    );

    $inlineNarrationPrompt = chimLoadManagedPromptTemplate(
        'inline_narration_prompt',
        "You may include brief third-person narration in asterisks (e.g., *She smiles*) before the dialogue.",
        [],
        "INLINE_NARRATION"
    );
    $TEMPLATE_DIALOG .= " " . $inlineNarrationPrompt;
} else {
    $TEMPLATE_DIALOG = chimLoadManagedPromptTemplate(
        'dialogue_line_response',
        " Write {HERIKA_NAME}'s next dialogue line." .
        " Be original, creative, knowledgeable, use your own thoughts. " .
        " Review context history to focus on conversation topic and to avoid repeating sentences and phraseology from previous lines.{MAXIMUM_WORDS}",
        [
            "{HERIKA_NAME}" => $GLOBALS["HERIKA_NAME"],
            "{MAXIMUM_WORDS}" => $MAXIMUM_WORDS,
        ],
        "DIALOGUE_LINE_RESPONSE"
    );
}

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
/* Legacy model-specific overrides removed - prose/narration now handled uniformly */
  
requireFilesRecursively(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."ext".DIRECTORY_SEPARATOR,"dialogue_prompt.php");

?>
