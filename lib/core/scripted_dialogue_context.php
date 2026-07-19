<?php

if (!function_exists('chimNormalizeScriptedDialogueContextMode')) {
    function chimNormalizeScriptedDialogueContextMode($mode): string
    {
        $normalized = strtolower(trim(strval($mode)));
        return in_array($normalized, ['scene', 'speaker', 'disabled'], true)
            ? $normalized
            : 'scene';
    }
}
if (!function_exists('chimNormalizeScriptedDialogueActorName')) {
    function chimNormalizeScriptedDialogueActorName($name): string
    {
        $normalized = trim(strval($name));
        $normalized = preg_replace('/\s+\((?:busy|hostile|in combat|restrained)\)\s*$/iu', '', $normalized);
        $normalized = preg_replace('/\s+/u', ' ', trim(strval($normalized)));

        return function_exists('mb_strtolower')
            ? mb_strtolower($normalized, 'UTF-8')
            : strtolower($normalized);
    }
}

if (!function_exists('chimIsScriptedDialogueContextRow')) {
    function chimIsScriptedDialogueContextRow(array $row): bool
    {
        if (strtolower(trim(strval($row['type'] ?? ''))) !== 'chat') {
            return false;
        }

        if (strtoupper(trim(strval($row['subtype'] ?? ''))) === 'BACKDIAG') {
            return true;
        }

        return preg_match(
            '/^\s*\(Context\s+location:.*?\bbackground\s+chat\)\s*/iu',
            strval($row['data'] ?? '')
        ) === 1;
    }
}

if (!function_exists('chimStripScriptedDialogueContextPrefix')) {
    function chimStripScriptedDialogueContextPrefix($data): string
    {
        return trim(strval(preg_replace(
            '/^\s*\(Context\s+location:.*?\bbackground\s+chat\)\s*/iu',
            '',
            strval($data),
            1
        )));
    }
}

if (!function_exists('chimExtractScriptedDialogueSpeaker')) {
    function chimExtractScriptedDialogueSpeaker(array $row): string
    {
        $line = chimStripScriptedDialogueContextPrefix($row['data'] ?? '');
        if (preg_match('/^([^:]+):\s*/u', $line, $match) !== 1) {
            return '';
        }

        return trim($match[1]);
    }
}

if (!function_exists('chimScriptedDialogueDedupeKey')) {
    function chimScriptedDialogueDedupeKey(array $row): string
    {
        $line = preg_replace('/\s+/u', ' ', chimStripScriptedDialogueContextPrefix($row['data'] ?? ''));
        $line = trim(strval($line));

        return function_exists('mb_strtolower')
            ? mb_strtolower($line, 'UTF-8')
            : strtolower($line);
    }
}

if (!function_exists('chimScriptedDialogueRowTimestamp')) {
    function chimScriptedDialogueRowTimestamp(array $row): ?float
    {
        foreach (['localts', 'ts'] as $field) {
            if (isset($row[$field]) && is_numeric($row[$field])) {
                return floatval($row[$field]);
            }
        }

        return null;
    }
}

if (!function_exists('chimGetScriptedDialogueContextOptions')) {
    function chimGetScriptedDialogueContextOptions(): array
    {
        return [
            'mode' => chimNormalizeScriptedDialogueContextMode($GLOBALS['SCRIPTED_DIALOGUE_CONTEXT_MODE'] ?? 'scene'),
            'dedup_seconds' => max(0, min(3600, intval($GLOBALS['SCRIPTED_DIALOGUE_DEDUP_SECONDS'] ?? 0))),
            'line_limit' => max(0, min(100, intval($GLOBALS['SCRIPTED_DIALOGUE_CONTEXT_LIMIT'] ?? 0))),
        ];
    }
}

if (!function_exists('chimFilterScriptedDialogueContextRows')) {
    function chimFilterScriptedDialogueContextRows(array $rows, string $actor, ?array $options = null): array
    {
        $options = $options ?? chimGetScriptedDialogueContextOptions();
        $mode = chimNormalizeScriptedDialogueContextMode($options['mode'] ?? 'scene');
        $dedupSeconds = max(0, min(3600, intval($options['dedup_seconds'] ?? 0)));
        $lineLimit = max(0, min(100, intval($options['line_limit'] ?? 0)));
        $normalizedActor = chimNormalizeScriptedDialogueActorName($actor);
        $seen = [];
        $scriptedCount = 0;
        $filtered = [];

        // Historic rows arrive newest first, so limits and duplicate removal retain the latest line.
        foreach ($rows as $key => $row) {
            if (!is_array($row) || !chimIsScriptedDialogueContextRow($row)) {
                $filtered[$key] = $row;
                continue;
            }

            if ($mode === 'disabled') {
                continue;
            }

            if ($mode === 'speaker') {
                $speaker = chimNormalizeScriptedDialogueActorName(chimExtractScriptedDialogueSpeaker($row));
                if ($speaker === '' || $speaker !== $normalizedActor) {
                    continue;
                }
            }

            if ($dedupSeconds > 0) {
                $dedupeKey = chimScriptedDialogueDedupeKey($row);
                $timestamp = chimScriptedDialogueRowTimestamp($row);
                if ($dedupeKey !== '' && array_key_exists($dedupeKey, $seen)) {
                    $newerTimestamp = $seen[$dedupeKey];
                    if ($timestamp === null || $newerTimestamp === null || abs($newerTimestamp - $timestamp) <= $dedupSeconds) {
                        continue;
                    }
                }
                if ($dedupeKey !== '') {
                    $seen[$dedupeKey] = $timestamp;
                }
            }

            if ($lineLimit > 0 && $scriptedCount >= $lineLimit) {
                continue;
            }

            $scriptedCount++;
            $filtered[$key] = $row;
        }

        return $filtered;
    }
}
