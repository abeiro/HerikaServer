<?php


// New structure
// $PROMPTS["event"]["cue"] => array containing cues. This is the last text sent to LLM, should be an guided instruction
// $PROMPTS["event"]["player_request"] => array containing requirements. This is what is the player requesting for (a question, a comment...)
// $PROMPTS["event"]["extra"] =>  enable/disable, force mod, change token limit or define a transformer (non IA related) function.
// Full Prompt then is $PROMPT_HEAD + $HERIKA_PERS + $COMMAND_PROMPT + CONTEXT + requirement + cue

// Common patterns to use in most functions
$MAXIMUM_WORDS=($GLOBALS["MAX_WORDS_LIMIT"]>0)?"(Maximum {$GLOBALS["MAX_WORDS_LIMIT"]} words)":"";

$directNarratorDialogue = false;
if (isset($GLOBALS["DIRECT_NARRATOR_DIALOGUE"])) {
    $directNarratorDialogue = (bool)$GLOBALS["DIRECT_NARRATOR_DIALOGUE"];
} elseif (isset($GLOBALS["gameRequest"][0])) {
    $directNarratorDialogue = ($GLOBALS["gameRequest"][0] === 'narrator_inputtext');
} elseif (isset($gameRequest[0])) {
    $directNarratorDialogue = ($gameRequest[0] === 'narrator_inputtext');
}

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

if (!function_exists('chimResolveImmediateReplyTargetName')) {
    function chimResolveImmediateReplyTargetName()
    {
        $gameRequest = $GLOBALS["gameRequest"] ?? [];
        $eventType = $gameRequest[0] ?? "";
        if (!in_array($eventType, ["inputtext", "inputtext_s", "ginputtext", "ginputtext_s"], true)) {
            return "";
        }

        $speakerName = extractSpeakerNameFromInputEvent($gameRequest[3] ?? "");
        if ($speakerName === "") {
            $speakerName = trim((string)($GLOBALS["PLAYER_NAME"] ?? ""));
        }

        $herikaName = trim((string)($GLOBALS["HERIKA_NAME"] ?? ""));
        if ($speakerName !== "" && $herikaName !== "" && strcasecmp($speakerName, $herikaName) === 0) {
            return "";
        }

        return $speakerName;
    }
}

if (!function_exists('chimApplyResponseTargetContextToPrompt')) {
    function chimApplyResponseTargetContextToPrompt($promptText, $speakerName)
    {
        $promptText = (string)$promptText;
        $speakerName = trim((string)$speakerName);
        if ($promptText === "" || $speakerName === "") {
            return $promptText;
        }

        $herikaName = trim((string)($GLOBALS["HERIKA_NAME"] ?? ""));
        if ($herikaName === "") {
            return $promptText;
        }

        $dialogueLead = "Write {$herikaName}'s next dialogue line.";
        $dialogueLeadDirected = "Write {$herikaName}'s next dialogue line responding to {$speakerName}.";
        if (strpos($promptText, $dialogueLead) !== false) {
            return str_replace($dialogueLead, $dialogueLeadDirected, $promptText);
        }

        $proseLead = "Write {$herikaName}'s next prose/narration.";
        $proseLeadDirected = "Write {$herikaName}'s next prose/narration responding to {$speakerName}.";
        if (strpos($promptText, $proseLead) !== false) {
            return str_replace($proseLead, $proseLeadDirected, $promptText);
        }

        return rtrim($promptText) . " Respond directly to {$speakerName}, the last person.";
    }
}

$responseTargetName = chimResolveImmediateReplyTargetName();
$responseTargetContext = ($responseTargetName !== "") ? " responding to {$responseTargetName}." : "";


// Add narration instruction when inline narration mode expects leading asterisk narration blocks.
$inlineNarrationMode = strtolower(trim((string)($GLOBALS["INLINE_NARRATION_MODE"] ?? '')));
if (!in_array($inlineNarrationMode, ['disabled', 'narrator', 'npc'], true)) {
    $inlineNarrationMode = (isset($GLOBALS["INLINE_NARRATION_ENABLED"]) && $GLOBALS["INLINE_NARRATION_ENABLED"]) ? 'narrator' : 'disabled';
}
$inlineNarrationMode = $directNarratorDialogue ? 'disabled' : $inlineNarrationMode;
$inlineNarrationEnabled = $inlineNarrationMode !== 'disabled';
if ($inlineNarrationEnabled) {
    if ($inlineNarrationMode === 'npc') {
        $inlineDialoguePromptKey = 'dialogue_line_inline_response_npc';
        $inlineDialogueFallback = " Write {HERIKA_NAME}'s next dialogue line{RESPONSE_TARGET_CONTEXT}."
            . " If needed, you may include one brief third-person narration block in single asterisks before the dialogue."
            . " Keep any spoken dialogue outside the asterisks, and do not wrap the entire reply in asterisks."
            . " Be original, creative, knowledgeable, use your own thoughts."
            . " Review context history to focus on conversation topic and to avoid repeating sentences and phraseology from previous lines.{MAXIMUM_WORDS}";
        $inlineNarrationPromptKey = 'inline_narration_prompt_npc';
        $inlineNarrationFallback = "You may include one brief third-person narration block in single asterisks before the dialogue (e.g., *She smiles softly*). Keep any spoken dialogue outside the asterisks. Do not wrap the entire reply in asterisks.";
    } else {
        $inlineDialoguePromptKey = 'dialogue_line_inline_response_narrator';
        $inlineDialogueFallback = " Write {HERIKA_NAME}'s next prose/narration{RESPONSE_TARGET_CONTEXT}."
            . " Be original, creative, knowledgeable, use your own thoughts. "
            . " Review context history to focus on conversation topic and to avoid repeating sentences and phraseology from previous lines.{MAXIMUM_WORDS}";
        $inlineNarrationPromptKey = 'inline_narration_prompt_narrator';
        $inlineNarrationFallback = "You may include one brief third-person narration block in single asterisks before the dialogue (e.g., *She smiles*). Do not wrap the entire reply in asterisks; keep any spoken dialogue outside the asterisks.";
    }

    $TEMPLATE_DIALOG = chimLoadManagedPromptTemplate(
        $inlineDialoguePromptKey,
        $inlineDialogueFallback,
        [
            "{HERIKA_NAME}" => $GLOBALS["HERIKA_NAME"],
            "{MAXIMUM_WORDS}" => $MAXIMUM_WORDS,
            "{RESPONSE_TARGET_CONTEXT}" => $responseTargetContext,
        ],
        "DIALOGUE_LINE_INLINE_RESPONSE"
    );

    $inlineNarrationPrompt = chimLoadManagedPromptTemplate(
        $inlineNarrationPromptKey,
        $inlineNarrationFallback,
        [],
        "INLINE_NARRATION"
    );
    $TEMPLATE_DIALOG .= " " . $inlineNarrationPrompt;
} else {
    $TEMPLATE_DIALOG = chimLoadManagedPromptTemplate(
        'dialogue_line_response',
        " Write {HERIKA_NAME}'s next dialogue line{RESPONSE_TARGET_CONTEXT}." .
        " Be original, creative, knowledgeable, use your own thoughts. " .
        " Review context history to focus on conversation topic and to avoid repeating sentences and phraseology from previous lines.{MAXIMUM_WORDS}",
        [
            "{HERIKA_NAME}" => $GLOBALS["HERIKA_NAME"],
            "{MAXIMUM_WORDS}" => $MAXIMUM_WORDS,
            "{RESPONSE_TARGET_CONTEXT}" => $responseTargetContext,
        ],
        "DIALOGUE_LINE_RESPONSE"
    );
}

$TEMPLATE_DIALOG = chimApplyResponseTargetContextToPrompt($TEMPLATE_DIALOG, $responseTargetName);

if ($directNarratorDialogue) {
    $TEMPLATE_DIALOG .= " Reply directly to {$GLOBALS["PLAYER_NAME"]} in plain spoken dialogue only." .
        " Do not include third-person narration, scene description, stage directions, or text in asterisks.";
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
