<?php

/**
 * Compact formatting for NPC conversation history while Compact Chat is enabled.
 *
 * Live response JSON and action schemas are intentionally handled elsewhere.
 */

if (!function_exists('chimCompactChatEnabled')) {
    function chimCompactChatEnabled(): bool
    {
        $value = $GLOBALS['COMPACT_CHAT_ENABLED'] ?? true;
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('chimShouldCompactNpcContextHistory')) {
    function chimShouldCompactNpcContextHistory(?string $actorName = null): bool
    {
        if (!chimCompactChatEnabled()) {
            return false;
        }

        $actorName = trim($actorName ?? (string)($GLOBALS['HERIKA_NAME'] ?? ''));
        return $actorName !== '' && strcasecmp($actorName, 'The Narrator') !== 0;
    }
}

if (!function_exists('chimCompactHistoryWhitespace')) {
    function chimCompactHistoryWhitespace(string $text): string
    {
        return trim((string)preg_replace('/\s+/u', ' ', $text));
    }
}

if (!function_exists('chimCompactHistoryDialogue')) {
    function chimCompactHistoryDialogue(string $content, string $fallbackSpeaker, bool $acceptAnySpeakerPrefix = true): string
    {
        $content = trim($content);
        $speaker = trim($fallbackSpeaker);
        $listener = '';

        if (preg_match('/\s*\((?:talking|whispering|shouting)\s+to\s+([^\)]+)\)\s*\.?\s*$/iu', $content, $match)) {
            $listener = chimCompactHistoryWhitespace($match[1]);
            $content = trim((string)preg_replace('/\s*\((?:talking|whispering|shouting)\s+to\s+[^\)]+\)\s*\.?\s*$/iu', '', $content));
        }

        if (preg_match('/^([^:\r\n]{1,100}):\s*(.+)$/us', $content, $match)) {
            $candidateSpeaker = chimCompactHistoryWhitespace($match[1]);
            if ($acceptAnySpeakerPrefix || $speaker === '' || strcasecmp($candidateSpeaker, $speaker) === 0) {
                $speaker = $candidateSpeaker;
                $content = $match[2];
            }
        }

        $content = chimCompactHistoryWhitespace($content);
        if ($speaker === '') {
            return $content;
        }

        return $listener !== ''
            ? "{$speaker}, speaking to {$listener}: {$content}"
            : "{$speaker}: {$content}";
    }
}

if (!function_exists('chimCompactAssistantHistoryEntry')) {
    function chimCompactAssistantHistoryEntry(string $content, string $actorName): string
    {
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return chimCompactHistoryDialogue($content, $actorName, false);
        }

        $speaker = chimCompactHistoryWhitespace((string)($decoded['character'] ?? $actorName));
        $listener = chimCompactHistoryWhitespace((string)($decoded['listener'] ?? ''));
        $message = chimCompactHistoryWhitespace((string)($decoded['message'] ?? ''));
        $action = chimCompactHistoryWhitespace((string)($decoded['action'] ?? ''));
        $target = chimCompactHistoryWhitespace((string)($decoded['target'] ?? ''));

        $line = $listener !== '' ? "{$speaker}, speaking to {$listener}" : $speaker;
        if ($message !== '') {
            $line .= ': ' . $message;
        }
        if ($action !== '' && strcasecmp($action, 'Talk') !== 0 && strcasecmp($action, 'JustTalk') !== 0) {
            $line .= ' [Action: ' . $action . ($target !== '' ? ", targeting {$target}" : '') . ']';
        }

        return trim($line);
    }
}

if (!function_exists('chimCompactUserHistoryEntry')) {
    function chimCompactUserHistoryEntry(string $content): string
    {
        $content = trim($content);

        if (preg_match('/^LOCATION CHANGE to (.*?),\s*hold:\s*(.*?),\s*timeline mark:\s*([^\r\n]+)$/iu', $content, $match)) {
            $location = chimCompactHistoryWhitespace($match[1]);
            $hold = chimCompactHistoryWhitespace($match[2]);
            $time = chimCompactHistoryWhitespace($match[3]);
            if (preg_match('/^0(?:\.0+)?\s+hours?\s+ago$/iu', $time)) {
                return "The current scene is at {$location} in {$hold} Hold.";
            }

            return "The scene {$time} took place at {$location} in {$hold} Hold.";
        }

        if (preg_match('/^([0-9.]+) hours have passed\.\s*Current date\/time:\s*Day name:\s*([^,]+),\s*Hour:\s*([^,]+),\s*Day Number:\s*([^,]+),\s*Month:\s*([^,]+),\s*4th Era,\s*Year:\s*([^\r\n]+)$/iu', $content, $match)) {
            $hours = rtrim(rtrim(number_format((float)$match[1], 1, '.', ''), '0'), '.');
            return sprintf(
                'After %s hours, it is now %s, %s, %s %s, 4E %s.',
                $hours,
                chimCompactHistoryWhitespace($match[2]),
                chimCompactHistoryWhitespace($match[3]),
                chimCompactHistoryWhitespace($match[4]),
                chimCompactHistoryWhitespace($match[5]),
                chimCompactHistoryWhitespace($match[6])
            );
        }

        if (preg_match('/^\(minor timelapse of about ([^)]+)\)$/iu', $content, $match)) {
            return 'About ' . chimCompactHistoryWhitespace($match[1]) . ' later.';
        }

        if (preg_match('/^\(\.\.\.\s*(.*?)\s*\.\.\.\)$/us', $content, $match)) {
            return 'Ambient dialogue: ' . chimCompactHistoryDialogue($match[1], '');
        }

        if (preg_match('/\((?:talking|whispering|shouting)\s+to\s+[^\)]+\)\s*\.?\s*$/iu', $content)) {
            return chimCompactHistoryDialogue($content, '');
        }

        $content = chimCompactHistoryWhitespace($content);
        if (preg_match('/\b(?:traded with|sold:|gave .* septims|issued ACTION|casts? |uses? )\b/iu', $content)) {
            return 'Event: ' . $content;
        }

        return $content;
    }
}

if (!function_exists('chimCompactToolHistoryEntry')) {
    function chimCompactToolHistoryEntry(array $entry): string
    {
        $content = $entry['content'] ?? '';
        if (is_string($content) || is_scalar($content)) {
            $content = chimCompactHistoryWhitespace((string)$content);
            if ($content !== '') {
                return 'Tool result: ' . $content;
            }
        }

        $toolCalls = $entry['tool_calls'] ?? [];
        if (!is_array($toolCalls)) {
            return '';
        }

        $calls = [];
        foreach ($toolCalls as $toolCall) {
            if (!is_array($toolCall)) {
                continue;
            }

            $function = $toolCall['function'] ?? [];
            if (!is_array($function)) {
                continue;
            }

            $name = chimCompactHistoryWhitespace((string)($function['name'] ?? ''));
            if ($name !== '') {
                $calls[] = $name;
            }
        }

        return $calls === [] ? '' : 'Requested action: ' . implode(', ', $calls) . '.';
    }
}

if (!function_exists('chimFormatCompactNpcContextHistory')) {
    function chimFormatCompactNpcContextHistory(array $history, string $actorName): string
    {
        $lines = [];

        foreach ($history as $entry) {
            if (!is_array($entry) || !isset($entry['role'])) {
                continue;
            }

            $role = (string)$entry['role'];
            if ($role === 'assistant') {
                if (isset($entry['tool_calls'])) {
                    $content = chimCompactToolHistoryEntry($entry);
                    if ($content !== '') {
                        $lines[] = $content;
                    }
                    continue;
                }

                $entryContent = $entry['content'] ?? '';
                if (!is_string($entryContent) && !is_scalar($entryContent)) {
                    continue;
                }

                $content = chimCompactAssistantHistoryEntry((string)$entryContent, $actorName);
                if ($content !== '') {
                    $lines[] = $content;
                }
                continue;
            }

            if ($role === 'user') {
                $entryContent = $entry['content'] ?? '';
                if (!is_string($entryContent) && !is_scalar($entryContent)) {
                    continue;
                }

                $content = chimCompactUserHistoryEntry((string)$entryContent);
                if ($content !== '') {
                    $lastIndex = count($lines) - 1;
                    if (
                        str_starts_with($content, 'The current scene is ')
                        && $lastIndex >= 0
                        && str_starts_with($lines[$lastIndex], 'After ')
                    ) {
                        $lines[$lastIndex] = rtrim($lines[$lastIndex], '.')
                            . '; ' . lcfirst($content);
                        continue;
                    }

                    $lines[] = $content;
                }
                continue;
            }

            if ($role === 'tool') {
                $content = chimCompactToolHistoryEntry($entry);
            } else {
                $entryContent = $entry['content'] ?? '';
                $content = (is_string($entryContent) || is_scalar($entryContent))
                    ? chimCompactHistoryWhitespace((string)$entryContent)
                    : '';
            }

            if ($content !== '') {
                $lines[] = $content;
            }
        }

        return implode("\n", array_map(
            static fn(string $line): string => '# ' . $line,
            $lines
        ));
    }
}

if (!function_exists('chimAppendCompactHistoryToPrompt')) {
    function chimAppendCompactHistoryToPrompt(array $worldContext, string $historyBlock, bool $markdownEnabled = false): array
    {
        $historyBlock = trim($historyBlock);
        if ($historyBlock === '') {
            return $worldContext;
        }
        if ($markdownEnabled) {
            $historyBlock = "# Conversation History\n\n" . preg_replace('/^# /m', '- ', $historyBlock);
        }

        foreach ($worldContext as &$entry) {
            if (
                is_array($entry)
                && ($entry['role'] ?? '') === 'system'
                && isset($entry['content'])
                && (is_string($entry['content']) || is_scalar($entry['content']))
            ) {
                $entry['content'] = rtrim((string)$entry['content']) . "\n\n" . $historyBlock;
                unset($entry);
                return $worldContext;
            }
        }
        unset($entry);

        array_unshift($worldContext, [
            'role' => 'system',
            'content' => $historyBlock,
        ]);

        return $worldContext;
    }
}
