<?php


// New structure
// $PROMPTS["event"]["cue"] => array containing cues. This is the last text sent to LLM, should be an guided instruction
// $PROMPTS["event"]["player_request"] => array containing requirements. This is what is the player requesting for (a question, a comment...)
// $PROMPTS["event"]["extra"] =>  enable/disable, force mod, change token limit or define a transformer (non IA related) function.
// Full Prompt then is $PROMPT_HEAD + $HERIKA_PERS + $COMMAND_PROMPT + CONTEXT + requirement + cue

// Common patterns to use in most functions
$MAXIMUM_WORDS=($GLOBALS["MAX_WORDS_LIMIT"]>0)?"(Maximum {$GLOBALS["MAX_WORDS_LIMIT"]} words)":"";
$promptCharacterName = function_exists('chimGetPromptCharacterName')
    ? chimGetPromptCharacterName()
    : ($GLOBALS["HERIKA_NAME"] ?? 'The Narrator');
$narratorRoleplayName = function_exists('chimGetNarratorRoleplayName')
    ? chimGetNarratorRoleplayName()
    : 'The Narrator';

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

if (!function_exists('chimNormalizePromptActorName')) {
    function chimNormalizePromptActorName(string $name): string
    {
        return strtolower(trim($name));
    }
}

if (!function_exists('chimExtractDirectedListenerNamesFromText')) {
    function chimExtractDirectedListenerNamesFromText(string $text): array
    {
        if ($text === '') {
            return [];
        }

        if (!preg_match('/\((talking|whispering|shouting|speaking privately)\s+to\s+([^)]+)\)/i', $text, $matches)) {
            return [];
        }

        $raw = trim((string)($matches[2] ?? ''));
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/\s*(?:,| and )\s*/i', $raw);
        $names = [];
        foreach ($parts as $part) {
            $part = trim((string)$part);
            if ($part !== '') {
                $names[] = $part;
            }
        }

        return array_values(array_unique($names));
    }
}

if (!function_exists('chimIsStrictDirectedPlayerResponseContext')) {
    function chimIsStrictDirectedPlayerResponseContext(): bool
    {
        if (empty($GLOBALS["ENFORCE_STRICT_RECHAT_RESPONSE"])) {
            return false;
        }

        if (!isset($GLOBALS["gameRequest"]) || !is_array($GLOBALS["gameRequest"])) {
            return false;
        }

        $eventType = strtolower(trim((string)($GLOBALS["gameRequest"][0] ?? '')));
        if (!in_array($eventType, ['inputtext', 'inputtext_s', 'ginputtext', 'ginputtext_s'], true)) {
            return false;
        }

        if (($GLOBALS["OVERRIDE_DIALOGUE_TARGET"] ?? false) === false) {
            return true;
        }

        $actorName = trim((string)($GLOBALS["HERIKA_NAME"] ?? ''));
        $requestText = trim((string)($GLOBALS["gameRequest"][3] ?? ''));
        if ($actorName === '' || $requestText === '') {
            return false;
        }

        $targetNames = chimExtractDirectedListenerNamesFromText($requestText);
        if (empty($targetNames)) {
            return false;
        }

        $actorNormalized = chimNormalizePromptActorName($actorName);
        foreach ($targetNames as $targetName) {
            if (chimNormalizePromptActorName($targetName) === $actorNormalized) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('chimGetRechatPreviousSpeakerName')) {
    function chimGetRechatPreviousSpeakerName(): string
    {
        if (chimIsStrictDirectedPlayerResponseContext()) {
            return trim((string)($GLOBALS["PLAYER_NAME"] ?? "Player"));
        }

        $speaker = trim((string)($GLOBALS["RECHAT_PREVIOUS_SPEAKER"] ?? ""));
        if ($speaker === "") {
            try {
                global $db;
                if (isset($db)) {
                    $row = $db->fetchOne("SELECT speaker FROM speech ORDER BY rowid DESC LIMIT 1");
                    $speaker = trim((string)($row["speaker"] ?? ""));
                }
            } catch (\Throwable $e) {
                $speaker = "";
            }
        }
        if ($speaker === "") {
            $speaker = trim((string)($GLOBALS["PLAYER_NAME"] ?? ""));
        }

        return $speaker;
    }
}

if (!function_exists('chimIsStrictRechatResponseEnabled')) {
    function chimIsStrictRechatResponseEnabled(): bool
    {
        return !empty($GLOBALS["ENFORCE_STRICT_RECHAT_RESPONSE"]);
    }
}

if (!function_exists('chimIsStrictRechatPromptContext')) {
    function chimIsStrictRechatPromptContext(): bool
    {
        if (!chimIsStrictRechatResponseEnabled()) {
            return false;
        }

        if (!isset($GLOBALS["gameRequest"]) || !is_array($GLOBALS["gameRequest"])) {
            return false;
        }

        return in_array(($GLOBALS["gameRequest"][0] ?? ""), ["rechat", "continue", "continue_group"], true);
    }
}

if (!function_exists('chimIsStrictResponsePromptContext')) {
    function chimIsStrictResponsePromptContext(): bool
    {
        return chimIsStrictRechatPromptContext() || chimIsStrictDirectedPlayerResponseContext();
    }
}

if (!function_exists('chimLoadManagedRechatCuePrompts')) {
    function chimLoadManagedRechatCuePrompts(): array
    {
        $previousSpeaker = chimGetRechatPreviousSpeakerName();
        $replacements = [
            "{HERIKA_NAME}" => chimGetPromptCharacterName(),
            "{NARRATOR_NAME}" => chimGetNarratorRoleplayName(),
            "{TEMPLATE_DIALOG}" => $GLOBALS["TEMPLATE_DIALOG"],
            "{PREVIOUS_SPEAKER}" => $previousSpeaker,
        ];

        $strictFallback = "Dialogue turn for {HERIKA_NAME}. The previous speaker was {PREVIOUS_SPEAKER}. You must respond directly to {PREVIOUS_SPEAKER}.";
        $relaxedFallbacks = [
            "Dialogue turn for {HERIKA_NAME}. Respond naturally to whoever just spoke. Address the previous speaker directly. {TEMPLATE_DIALOG}",
            "Dialogue turn for {HERIKA_NAME}. Continue the conversation naturally. Address whoever you're actually responding to. {TEMPLATE_DIALOG}",
            "Dialogue turn for {HERIKA_NAME}. Focus on one actor - respond to whoever just spoke. {TEMPLATE_DIALOG}",
        ];

        if (chimIsStrictResponsePromptContext()) {
            $strictPrompts = [];
            for ($i = 1; $i <= 3; $i++) {
                $strictPrompts[] = chimLoadManagedPromptTemplate(
                    "rechat_response_prompt_strict_{$i}",
                    $strictFallback,
                    $replacements,
                    "RECHAT_RESPONSE_PROMPT_STRICT"
                );
            }
            return $strictPrompts;
        }

        $relaxedPrompts = [];
        foreach ($relaxedFallbacks as $index => $fallbackPrompt) {
            $relaxedPrompts[] = chimLoadManagedPromptTemplate(
                "rechat_response_prompt_relaxed_" . ($index + 1),
                $fallbackPrompt,
                $replacements,
                "RECHAT_RESPONSE_PROMPT_RELAXED"
            );
        }

        return $relaxedPrompts;
    }
}

if (!function_exists('chimLoadManagedRechatListenerPrompt')) {
    function chimLoadManagedRechatListenerPrompt(): string
    {
        $replacements = [
            "{HERIKA_NAME}" => chimGetPromptCharacterName(),
            "{NARRATOR_NAME}" => chimGetNarratorRoleplayName(),
            "{PREVIOUS_SPEAKER}" => chimGetRechatPreviousSpeakerName(),
        ];

        if (chimIsStrictResponsePromptContext()) {
            return chimLoadManagedPromptTemplate(
                'rechat_listener_prompt_strict',
                "specify who {HERIKA_NAME} is talking to. The listener must be exactly {PREVIOUS_SPEAKER}. Address the person who just spoke.",
                $replacements,
                "RECHAT_LISTENER_PROMPT_STRICT"
            );
        }

        return chimLoadManagedPromptTemplate(
            'rechat_listener_prompt_relaxed',
            "specify who {HERIKA_NAME} is talking to. Address whoever just spoke - can be any person in the conversation.",
            $replacements,
            "RECHAT_LISTENER_PROMPT_RELAXED"
        );
    }
}

if (!function_exists('chimLoadManagedContinueCuePrompts')) {
    function chimLoadManagedContinueCuePrompts(string $mode = 'continue'): array
    {
        if (chimIsStrictResponsePromptContext()) {
            return chimLoadManagedRechatCuePrompts();
        }

        $fallback = ($mode === 'continue_group')
            ? "Dialogue turn for {HERIKA_NAME}. Continue the ongoing group discussion. Build on what was just said and stay with the current topic. {TEMPLATE_DIALOG}"
            : "Dialogue turn for {HERIKA_NAME}. Continue the ongoing discussion. Build on what was just said. {TEMPLATE_DIALOG}";

        return [
            strtr($fallback, [
                "{HERIKA_NAME}" => chimGetPromptCharacterName(),
                "{NARRATOR_NAME}" => chimGetNarratorRoleplayName(),
                "{TEMPLATE_DIALOG}" => $GLOBALS["TEMPLATE_DIALOG"],
            ]),
        ];
    }
}


// Add narration instruction when inline narration mode expects leading asterisk narration blocks.
$inlineNarrationMode = strtolower(trim((string)($GLOBALS["INLINE_NARRATION_MODE"] ?? '')));
if (!in_array($inlineNarrationMode, ['disabled', 'narrator', 'npc', 'text_only'], true)) {
    $inlineNarrationMode = (isset($GLOBALS["INLINE_NARRATION_ENABLED"]) && $GLOBALS["INLINE_NARRATION_ENABLED"]) ? 'narrator' : 'disabled';
}
$inlineNarrationMode = $directNarratorDialogue ? 'disabled' : $inlineNarrationMode;
$inlineNarrationEnabled = $inlineNarrationMode !== 'disabled';
if ($inlineNarrationEnabled) {
    if (in_array($inlineNarrationMode, ['npc', 'text_only'], true)) {
        $inlineDialoguePromptKey = 'dialogue_line_inline_response_npc';
        $inlineDialogueFallback = " Write {HERIKA_NAME}'s next dialogue line."
            . " If needed, you may include one brief third-person narration block in single asterisks before the dialogue."
            . " Keep any spoken dialogue outside the asterisks, and do not wrap the entire reply in asterisks."
            . " Be original, creative, knowledgeable, use your own thoughts."
            . " Review context history to focus on conversation topic and to avoid repeating sentences and phraseology from previous lines.{MAXIMUM_WORDS}";
        $inlineNarrationPromptKey = 'inline_narration_prompt_npc';
        $inlineNarrationFallback = "You may include one brief third-person narration block in single asterisks before the dialogue (e.g., *She smiles softly*). Keep any spoken dialogue outside the asterisks. Do not wrap the entire reply in asterisks.";
    } else {
        $inlineDialoguePromptKey = 'dialogue_line_inline_response_narrator';
        $inlineDialogueFallback = " Write {HERIKA_NAME}'s next prose/narration."
            . " Be original, creative, knowledgeable, use your own thoughts. "
            . " Review context history to focus on conversation topic and to avoid repeating sentences and phraseology from previous lines.{MAXIMUM_WORDS}";
        $inlineNarrationPromptKey = 'inline_narration_prompt_narrator';
        $inlineNarrationFallback = "You may include one brief third-person narration block in single asterisks before the dialogue (e.g., *She smiles*). Do not wrap the entire reply in asterisks; keep any spoken dialogue outside the asterisks.";
    }

    $TEMPLATE_DIALOG = chimLoadManagedPromptTemplate(
        $inlineDialoguePromptKey,
        $inlineDialogueFallback,
        [
            "{HERIKA_NAME}" => $promptCharacterName,
            "{NARRATOR_NAME}" => $narratorRoleplayName,
            "{MAXIMUM_WORDS}" => $MAXIMUM_WORDS,
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
        " Write {HERIKA_NAME}'s next dialogue line." .
        " Be original, creative, knowledgeable, use your own thoughts. " .
        " Review context history to focus on conversation topic and to avoid repeating sentences and phraseology from previous lines.{MAXIMUM_WORDS}",
        [
            "{HERIKA_NAME}" => $promptCharacterName,
            "{NARRATOR_NAME}" => $narratorRoleplayName,
            "{MAXIMUM_WORDS}" => $MAXIMUM_WORDS,
        ],
        "DIALOGUE_LINE_RESPONSE"
    );
}

if ($directNarratorDialogue) {
    $TEMPLATE_DIALOG .= " Reply directly to {$GLOBALS["PLAYER_NAME"]} in spoken dialogue." .
        " If an narrator action matches the request, use it and keep the spoken line consistent with that action." .
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
