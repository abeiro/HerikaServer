<?php

// Converts prompt section tags after XML-based context filtering has finished.
function chimFormatPromptHeadSection(string $systemPrompt, bool $markdownEnabled): string
{
    if (!$markdownEnabled) {
        return $systemPrompt;
    }

    $systemPrompt = str_replace(["\r\n", "\r"], "\n", $systemPrompt);
    $formatTagName = static function (string $tag): string {
        return ucwords(str_replace(['_', '-'], ' ', strtolower($tag)));
    };

    $formattedPrompt = preg_replace_callback(
        '/^[ \t]*<([A-Za-z][A-Za-z0-9_-]*)>[ \t]*(?:\n[ \t]*#{1,6}[ \t]*\1[ \t]*(?=\n|$))?/mi',
        static function (array $matches) use ($formatTagName): string {
            return '## ' . $formatTagName($matches[1]) . "\n";
        },
        $systemPrompt
    );
    if (!is_string($formattedPrompt)) {
        return $systemPrompt;
    }

    $formattedPrompt = preg_replace('/^[ \t]*<\/[A-Za-z][A-Za-z0-9_-]*>[ \t]*(?:\n|$)/m', '', $formattedPrompt);
    $formattedPrompt = preg_replace('/[ \t]*<\/[A-Za-z][A-Za-z0-9_-]*>/', '', (string) $formattedPrompt);
    $formattedPrompt = preg_replace_callback(
        '/<([A-Za-z][A-Za-z0-9_-]*)>/',
        static function (array $matches) use ($formatTagName): string {
            return '`' . $formatTagName($matches[1]) . '`';
        },
        (string) $formattedPrompt
    );
    $formattedPrompt = preg_replace('/\n{3,}/', "\n\n", (string) $formattedPrompt);

    return is_string($formattedPrompt) ? $formattedPrompt : $systemPrompt;
}

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
