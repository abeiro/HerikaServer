<?php
/**
 * Relationship event baseline helpers for HerikaServer.
 *
 * Builds concise event-history context for relationship analysis without
 * relying on legacy text relationship fields or current table rows.
 */

if (!function_exists('chimRelBaselineNormalizeName')) {
    function chimRelBaselineNormalizeName(string $raw): string
    {
        return trim($raw);
    }
}

if (!function_exists('chimRelBaselineTextLength')) {
    function chimRelBaselineTextLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }
        return strlen($value);
    }
}

if (!function_exists('chimRelBaselineTextSlice')) {
    function chimRelBaselineTextSlice(string $value, int $limit): string
    {
        if ($limit <= 0) {
            return '';
        }
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $limit, 'UTF-8');
        }
        return substr($value, 0, $limit);
    }
}

if (!function_exists('chimRelBaselineTruncate')) {
    function chimRelBaselineTruncate(string $value, int $maxLength): string
    {
        $clean = trim($value);
        if ($maxLength <= 0 || chimRelBaselineTextLength($clean) <= $maxLength) {
            return $clean;
        }
        if ($maxLength <= 3) {
            return chimRelBaselineTextSlice($clean, $maxLength);
        }
        return rtrim(chimRelBaselineTextSlice($clean, $maxLength - 3)) . '...';
    }
}

if (!function_exists('chimRelBaselineContainsWholeName')) {
    function chimRelBaselineContainsWholeName(string $haystack, string $name): bool
    {
        $source = trim($haystack);
        $needle = trim($name);
        if ($source === '' || $needle === '') {
            return false;
        }

        $quoted = preg_quote($needle, '/');
        return preg_match('/(?<![\p{L}\p{N}])' . $quoted . '(?![\p{L}\p{N}])/iu', $source) === 1;
    }
}

if (!function_exists('chimRelBaselineExtractPeople')) {
    function chimRelBaselineExtractPeople(string $people): array
    {
        $namesByKey = [];
        foreach (explode('|', $people) as $part) {
            $name = chimRelBaselineNormalizeName($part);
            if ($name === '' || strcasecmp($name, 'The Narrator') === 0) {
                continue;
            }
            $namesByKey[strtolower($name)] = $name;
        }
        return array_values($namesByKey);
    }
}

if (!function_exists('chimRelBaselineParseDialogueData')) {
    function chimRelBaselineParseDialogueData(string $data): array
    {
        $result = [];
        if (preg_match('/^\s*([^:]+):/', $data, $matches)) {
            $result[] = chimRelBaselineNormalizeName($matches[1]);
        }
        if (preg_match('/\((?:talking|Talking|shouting|Shouting|whispering|Whispering)\s+to\s+([^)]+)\)/u', $data, $matches)) {
            $result[] = chimRelBaselineNormalizeName($matches[1]);
        }
        return array_values(array_unique(array_filter($result, static fn($name) => $name !== '')));
    }
}

if (!function_exists('chimRelBaselineExtractParticipants')) {
    function chimRelBaselineExtractParticipants(array $row): array
    {
        $namesByKey = [];
        $add = static function ($raw) use (&$namesByKey): void {
            if (!is_scalar($raw) || $raw === null) {
                return;
            }
            $name = chimRelBaselineNormalizeName(strval($raw));
            if ($name === '' || strcasecmp($name, 'The Narrator') === 0) {
                return;
            }
            $namesByKey[strtolower($name)] = $name;
        };

        foreach (chimRelBaselineExtractPeople(strval($row['people'] ?? '')) as $name) {
            $add($name);
        }
        foreach (chimRelBaselineParseDialogueData(strval($row['data'] ?? '')) as $name) {
            $add($name);
        }

        return array_values($namesByKey);
    }
}

if (!function_exists('chimRelBaselineRowIncludesNpc')) {
    function chimRelBaselineRowIncludesNpc(array $row, string $npcName): bool
    {
        $safeNpc = chimRelBaselineNormalizeName($npcName);
        if ($safeNpc === '') {
            return false;
        }
        $npcLower = strtolower($safeNpc);

        foreach (chimRelBaselineExtractParticipants($row) as $participant) {
            if (strtolower(chimRelBaselineNormalizeName($participant)) === $npcLower) {
                return true;
            }
        }

        $people = strval($row['people'] ?? '');
        if ($people !== '' && chimRelBaselineContainsWholeName($people, $safeNpc)) {
            return true;
        }

        $data = strval($row['data'] ?? '');
        if ($data !== '' && chimRelBaselineContainsWholeName($data, $safeNpc)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('chimRelBaselineFormatLine')) {
    function chimRelBaselineFormatLine(array $row, int $maxDataLength = 180): string
    {
        $historyType = strtolower(trim(strval($row['type'] ?? 'event')));
        if ($historyType === '') {
            $historyType = 'event';
        }

        $historyData = trim(strval($row['data'] ?? ''));
        if ($historyData === '') {
            return '';
        }

        $historyData = chimRelBaselineTruncate($historyData, max(60, $maxDataLength));
        if ($historyData === '') {
            return '';
        }

        $gamets = max(0, intval($row['gamets'] ?? 0));
        if ($gamets > 0) {
            return '[' . $historyType . ' @ gamets=' . strval($gamets) . '] ' . $historyData;
        }
        return '[' . $historyType . '] ' . $historyData;
    }
}

if (!function_exists('chimRelBuildEventBaseline')) {
    function chimRelBuildEventBaseline(string $npcName, int $eventLimit = 200, int $scanLimit = 0): array
    {
        $db = $GLOBALS['db'] ?? null;
        if (!$db) {
            return [
                'ok' => false,
                'npc_name' => '',
                'event_count' => 0,
                'history' => '',
                'lines' => [],
                'counterparts' => [],
                'error' => 'Database connection unavailable.',
            ];
        }

        $safeNpc = chimRelBaselineNormalizeName($npcName);
        if ($safeNpc === '') {
            return [
                'ok' => false,
                'npc_name' => '',
                'event_count' => 0,
                'history' => '',
                'lines' => [],
                'counterparts' => [],
                'error' => 'NPC name is required.',
            ];
        }

        if ($eventLimit < 25) {
            $eventLimit = 25;
        } elseif ($eventLimit > 400) {
            $eventLimit = 400;
        }

        if ($scanLimit <= 0) {
            $scanLimit = $eventLimit * 8;
        }
        if ($scanLimit < 300) {
            $scanLimit = 300;
        } elseif ($scanLimit > 3500) {
            $scanLimit = 3500;
        }

        $excludeTypes = "'prechat','setconf','status_msg','user_input','npc_snapshot','playerinfo'";
        $safeNpcEscaped = $db->escape($safeNpc);
        $rows = $db->fetchAll(
            "SELECT rowid, type, data, gamets, localts, ts, people, location
             FROM eventlog
             WHERE type NOT IN ({$excludeTypes})
               AND (
                    POSITION(LOWER('{$safeNpcEscaped}') IN LOWER(COALESCE(people, ''))) > 0
                    OR POSITION(LOWER('{$safeNpcEscaped}') IN LOWER(COALESCE(data, ''))) > 0
                   )
             ORDER BY rowid DESC
             LIMIT " . intval($scanLimit)
        );

        $linesDesc = [];
        $counterparts = [];
        $npcLower = strtolower($safeNpc);
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (!is_array($row) || !chimRelBaselineRowIncludesNpc($row, $safeNpc)) {
                    continue;
                }

                $line = chimRelBaselineFormatLine($row, 180);
                if ($line === '') {
                    continue;
                }
                $linesDesc[] = $line;

                foreach (chimRelBaselineExtractParticipants($row) as $participant) {
                    $name = chimRelBaselineNormalizeName($participant);
                    if ($name === '' || strtolower($name) === $npcLower) {
                        continue;
                    }
                    $counterparts[strtolower($name)] = $name;
                }

                if (count($linesDesc) >= $eventLimit) {
                    break;
                }
            }
        }

        $lines = array_reverse($linesDesc);
        $history = implode("\n", $lines);
        $counterpartList = array_values($counterparts);
        sort($counterpartList, SORT_NATURAL | SORT_FLAG_CASE);

        if (count($lines) === 0) {
            return [
                'ok' => false,
                'npc_name' => $safeNpc,
                'event_count' => 0,
                'history' => '',
                'lines' => [],
                'counterparts' => [],
                'error' => 'No recent event history found for this NPC.',
            ];
        }

        return [
            'ok' => true,
            'npc_name' => $safeNpc,
            'event_count' => count($lines),
            'history' => $history,
            'lines' => $lines,
            'counterparts' => $counterpartList,
            'error' => '',
        ];
    }
}
