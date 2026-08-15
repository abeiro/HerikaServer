<?php

function chimPromptCompositionCharacterCount($value)
{
    if (is_string($value) || is_numeric($value)) {
        $text = strval($value);
        return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    }

    if (!is_array($value)) {
        return 0;
    }

    $characters = 0;
    foreach ($value as $item) {
        if (is_array($item) && array_key_exists('content', $item)) {
            $characters += chimPromptCompositionCharacterCount($item['content']);
            continue;
        }

        $characters += chimPromptCompositionCharacterCount($item);
    }

    return $characters;
}

function chimPromptCompositionMeasure($value)
{
    $characters = chimPromptCompositionCharacterCount($value);

    return [
        'characters' => $characters,
        'estimated_tokens' => $characters > 0 ? intval(ceil($characters / 4)) : 0,
    ];
}

function chimBuildPromptCompositionReport($requestType, array $sections, array $messages)
{
    $measuredSections = [];
    foreach ($sections as $name => $value) {
        $measuredSections[strval($name)] = chimPromptCompositionMeasure($value);
    }

    $messageMeasurement = chimPromptCompositionMeasure($messages);

    return [
        'request_type' => strval($requestType),
        'message_count' => count($messages),
        'total_characters' => $messageMeasurement['characters'],
        'estimated_total_tokens' => $messageMeasurement['estimated_tokens'],
        'sections' => $measuredSections,
    ];
}

function chimLogPromptComposition($requestType, array $sections, array $messages)
{
    $report = chimBuildPromptCompositionReport($requestType, $sections, $messages);
    $json = json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (is_string($json)) {
        Logger::debug('[PROMPT-COMPOSITION] ' . $json);
    }

    return $report;
}
