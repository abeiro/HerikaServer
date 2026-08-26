<?php

// Define the editable prompt entry and safe fallback for each supported Prisma player mood.
function chimPlayerMoodPromptCatalog()
{
    return [
        "happy" => [
            "prompt_key" => "player_mood_happy_prompt",
            "default_prompt" => "(speaks in a happy tone.)",
        ],
        "sad" => [
            "prompt_key" => "player_mood_sad_prompt",
            "default_prompt" => "(speaks in a sad tone.)",
        ],
        "angry" => [
            "prompt_key" => "player_mood_angry_prompt",
            "default_prompt" => "(speaks in an angry tone.)",
        ],
        "annoyed" => [
            "prompt_key" => "player_mood_annoyed_prompt",
            "default_prompt" => "(speaks in an annoyed tone.)",
        ],
        "scared" => [
            "prompt_key" => "player_mood_scared_prompt",
            "default_prompt" => "(speaks in a frightened tone.)",
        ],
        "surprised" => [
            "prompt_key" => "player_mood_surprised_prompt",
            "default_prompt" => "(speaks in a surprised tone.)",
        ],
        "confused" => [
            "prompt_key" => "player_mood_confused_prompt",
            "default_prompt" => "(speaks in a confused tone.)",
        ],
        "suspicious" => [
            "prompt_key" => "player_mood_suspicious_prompt",
            "default_prompt" => "(speaks in a suspicious tone.)",
        ],
        "playful" => [
            "prompt_key" => "player_mood_playful_prompt",
            "default_prompt" => "(speaks in a playful tone.)",
        ],
        "flirty" => [
            "prompt_key" => "player_mood_flirty_prompt",
            "default_prompt" => "(speaks in a flirtatious tone.)",
        ],
        "custom" => [
            "prompt_key" => "player_mood_custom_prompt",
            "default_prompt" => "(speaks {CUSTOM_MOOD}.)",
        ],
    ];
}

// Resolve the selected mood through its fixed Prompt Manager key without trusting client text as SQL.
function chimResolvePlayerMoodPrompt($playerMood, $playerName = null, $db = null, $customPlayerMood = "")
{
    $playerMood = strtolower(trim((string)$playerMood));
    $catalog = chimPlayerMoodPromptCatalog();
    if (!isset($catalog[$playerMood])) {
        return "";
    }

    $customPlayerMood = $playerMood === "custom"
        ? chimNormalizeCustomPlayerMood($customPlayerMood)
        : "";
    if ($playerMood === "custom" && $customPlayerMood === "") {
        return "";
    }

    $entry = $catalog[$playerMood];
    $prompt = $entry["default_prompt"];
    $db = $db ?? ($GLOBALS["db"] ?? null);

    if (is_object($db) && method_exists($db, "fetchOne")) {
        try {
            $promptData = $db->fetchOne(
                "SELECT custom_prompt, default_prompt FROM public.prompts "
                . "WHERE prompt_key = '" . $entry["prompt_key"] . "' LIMIT 1"
            );
            if (is_array($promptData)) {
                $customPrompt = trim((string)($promptData["custom_prompt"] ?? ""));
                $defaultPrompt = trim((string)($promptData["default_prompt"] ?? ""));
                if ($customPrompt !== "") {
                    $prompt = $customPrompt;
                } elseif ($defaultPrompt !== "") {
                    $prompt = $defaultPrompt;
                }
            }
        } catch (Throwable $e) {
            if (class_exists("Logger")) {
                Logger::warn("Failed to load player mood prompt: " . $e->getMessage());
            }
        }
    }

    $playerName = trim((string)($playerName ?? ($GLOBALS["PLAYER_NAME"] ?? "Player")));
    if ($playerName === "") {
        $playerName = "Player";
    }

    return strtr($prompt, [
        "{PLAYER_NAME}" => $playerName,
        "{MOOD}" => $playerMood,
        "{CUSTOM_MOOD}" => $customPlayerMood,
    ]);
}
