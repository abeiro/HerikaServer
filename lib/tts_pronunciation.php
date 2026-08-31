<?php

// Provide global Skyrim pronunciations when the database has not been migrated yet.
function chimDefaultTtsPronunciationEntries(): array
{
    return [
        ['source_text' => 'Jorrvaskr', 'spoken_text' => 'Yorvaskr'],
        ['source_text' => 'Ysgramor', 'spoken_text' => 'Eesgramor'],
        ['source_text' => 'Aela', 'spoken_text' => 'Aye-ella'],
        ['source_text' => 'Mjoll', 'spoken_text' => 'Mee-yoll'],
        ['source_text' => 'Sovngarde', 'spoken_text' => 'Sovn-gard'],
        ['source_text' => "Thu'um", 'spoken_text' => 'Thoom'],
    ];
}

// Create the dictionary table and add missing built-in entries without changing user data.
function chimEnsureTtsPronunciationDictionary(): bool
{
    if (!isset($GLOBALS['db']) || !$GLOBALS['db']) {
        return false;
    }

    $schemaPath = __DIR__ . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR
        . 'database_schema' . DIRECTORY_SEPARATOR . 'core_tts_pronunciation.sql';
    if (!is_readable($schemaPath) || $GLOBALS['db']->execQuery(file_get_contents($schemaPath)) === false) {
        return false;
    }

    foreach (chimDefaultTtsPronunciationEntries() as $entry) {
        $source = $GLOBALS['db']->escapeLiteral($entry['source_text']);
        $spoken = $GLOBALS['db']->escapeLiteral($entry['spoken_text']);
        $inserted = $GLOBALS['db']->execQuery(
            "INSERT INTO public.core_tts_pronunciation
                (source_text, spoken_text, oghma_tags, is_builtin, enabled, updated_at)
             VALUES ({$source}, {$spoken}, '', TRUE, TRUE, CURRENT_TIMESTAMP)
             ON CONFLICT DO NOTHING"
        );
        if ($inserted === false) {
            return false;
        }
    }

    return true;
}

final class TTSPronunciationDictionary
{
    private const TABLE = 'core_tts_pronunciation';

    public function isAvailable(): bool
    {
        if (!isset($GLOBALS['db']) || !$GLOBALS['db']) {
            return false;
        }

        $table = $GLOBALS['db']->escapeLiteral(self::TABLE);
        $row = $GLOBALS['db']->fetchOne(
            "SELECT 1 AS present
             FROM information_schema.tables
             WHERE table_schema = 'public' AND table_name = {$table}
             LIMIT 1"
        );

        return is_array($row) && intval($row['present'] ?? 0) === 1;
    }

    public function getRows(string $tagFilter = ''): array
    {
        if (!$this->isAvailable()) {
            return array_map(static function (array $entry): array {
                return $entry + [
                    'id' => 0,
                    'oghma_tags' => '',
                    'is_builtin' => true,
                    'enabled' => true,
                ];
            }, chimDefaultTtsPronunciationEntries());
        }

        $rows = $GLOBALS['db']->fetchAll(
            'SELECT id, source_text, spoken_text, oghma_tags, is_builtin, enabled, created_at, updated_at
             FROM public.' . self::TABLE . '
             ORDER BY is_builtin DESC, LOWER(source_text), id
             LIMIT 1024'
        );
        $rows = is_array($rows) ? $rows : [];
        $tagFilter = strtolower(trim($tagFilter));
        if ($tagFilter === '') {
            return $rows;
        }

        return array_values(array_filter($rows, static function (array $row) use ($tagFilter): bool {
            if (chimTtsPronunciationBoolean($row['is_builtin'] ?? false)) {
                return true;
            }
            return in_array($tagFilter, chimTtsPronunciationNormalizeTags($row['oghma_tags'] ?? ''), true);
        }));
    }

    public function getAvailableTags(): array
    {
        $tags = [];
        foreach ($this->getRows() as $row) {
            if (chimTtsPronunciationBoolean($row['is_builtin'] ?? false)) {
                continue;
            }
            foreach (chimTtsPronunciationNormalizeTags($row['oghma_tags'] ?? '') as $tag) {
                $tags[$tag] = $tag;
            }
        }
        natcasesort($tags);
        return array_values($tags);
    }

    public function saveCustom(?int $id, string $source, string $spoken, string $oghmaTags, bool $enabled): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $source = trim($source);
        $spoken = trim($spoken);
        if ($source === '' || $spoken === '' || strlen($source) > 120 || strlen($spoken) > 240) {
            return false;
        }

        $normalizedTags = implode(', ', array_slice(chimTtsPronunciationNormalizeTags($oghmaTags), 0, 32));
        $sourceValue = $GLOBALS['db']->escapeLiteral($source);
        $spokenValue = $GLOBALS['db']->escapeLiteral($spoken);
        $tagsValue = $GLOBALS['db']->escapeLiteral(substr($normalizedTags, 0, 512));
        $enabledValue = $enabled ? 'TRUE' : 'FALSE';

        if ($id !== null && $id > 0) {
            return $GLOBALS['db']->execQuery(
                "UPDATE public." . self::TABLE . "
                 SET source_text = {$sourceValue}, spoken_text = {$spokenValue}, oghma_tags = {$tagsValue},
                     enabled = {$enabledValue}, updated_at = CURRENT_TIMESTAMP
                 WHERE id = " . intval($id) . " AND is_builtin = FALSE"
            ) !== false;
        }

        return $GLOBALS['db']->execQuery(
            "INSERT INTO public." . self::TABLE . "
                (source_text, spoken_text, oghma_tags, is_builtin, enabled, updated_at)
             VALUES ({$sourceValue}, {$spokenValue}, {$tagsValue}, FALSE, {$enabledValue}, CURRENT_TIMESTAMP)"
        ) !== false;
    }

    public function setEnabled(int $id, bool $enabled): bool
    {
        if ($id <= 0 || !$this->isAvailable()) {
            return false;
        }

        return $GLOBALS['db']->execQuery(
            'UPDATE public.' . self::TABLE . '
             SET enabled = ' . ($enabled ? 'TRUE' : 'FALSE') . ', updated_at = CURRENT_TIMESTAMP
             WHERE id = ' . intval($id)
        ) !== false;
    }

    public function deleteCustom(int $id): bool
    {
        if ($id <= 0 || !$this->isAvailable()) {
            return false;
        }

        return $GLOBALS['db']->execQuery(
            'DELETE FROM public.' . self::TABLE . '
             WHERE id = ' . intval($id) . ' AND is_builtin = FALSE'
        ) !== false;
    }
}

function chimTtsPronunciationBoolean($value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    return in_array(strtolower(trim(strval($value))), ['1', 't', 'true', 'yes', 'on'], true);
}

function chimTtsPronunciationNormalizeTags($tags): array
{
    $normalized = [];
    foreach (explode(',', strval($tags)) as $tag) {
        $tag = strtolower(trim($tag));
        if ($tag === '' || strlen($tag) > 64) {
            continue;
        }
        $normalized[$tag] = $tag;
    }
    return array_values($normalized);
}

// Limit tagged custom entries to NPCs whose active Oghma knowledge tags match.
function chimTtsPronunciationEntryAllows(array $entry, ?array $knowledgeTags = null): bool
{
    $entryTags = chimTtsPronunciationNormalizeTags($entry['oghma_tags'] ?? '');
    if (empty($entryTags)) {
        return true;
    }

    $knowledgeTags = $knowledgeTags ?? chimTtsPronunciationNormalizeTags(
        $GLOBALS['OGHMA_KNOWLEDGE'] ?? ''
    );
    $knowledgeTags = array_values(array_unique(array_map(
        static fn($tag): string => strtolower(trim(strval($tag))),
        $knowledgeTags
    )));

    return in_array('knowall', $knowledgeTags, true)
        || !empty(array_intersect($entryTags, $knowledgeTags));
}

// Resolve active rows with custom scoped entries taking priority over global defaults.
function chimTtsPronunciationEntries(?array $rows = null, ?array $knowledgeTags = null): array
{
    if ($rows === null) {
        static $cachedRows = null;
        if ($cachedRows === null) {
            $cachedRows = (new TTSPronunciationDictionary())->getRows();
        }
        $rows = $cachedRows;
    }

    $resolved = [];
    foreach (array_slice($rows, 0, 1024) as $row) {
        if (!chimTtsPronunciationBoolean($row['enabled'] ?? true)
            || !chimTtsPronunciationEntryAllows($row, $knowledgeTags)) {
            continue;
        }

        $source = trim(strval($row['source_text'] ?? $row['source'] ?? ''));
        $spoken = trim(strval($row['spoken_text'] ?? $row['spoken'] ?? ''));
        if ($source === '' || $spoken === '' || strlen($source) > 120 || strlen($spoken) > 240) {
            continue;
        }

        $normalizedSource = function_exists('mb_strtolower')
            ? mb_strtolower($source, 'UTF-8')
            : strtolower($source);
        $isBuiltin = chimTtsPronunciationBoolean($row['is_builtin'] ?? false);
        $hasTags = !empty(chimTtsPronunciationNormalizeTags($row['oghma_tags'] ?? ''));
        $priority = ($isBuiltin ? 0 : 10) + ($hasTags ? 1 : 0);
        if (isset($resolved[$normalizedSource]) && $resolved[$normalizedSource]['priority'] > $priority) {
            continue;
        }

        $resolved[$normalizedSource] = [
            'source' => $source,
            'spoken' => $spoken,
            'priority' => $priority,
        ];
    }

    $entries = array_values($resolved);
    usort($entries, static function (array $left, array $right): int {
        return strlen($right['source']) <=> strlen($left['source']);
    });
    return array_slice($entries, 0, 256);
}

function chimApplyTtsPronunciationDictionary(
    string $text,
    ?array $rows = null,
    ?array $knowledgeTags = null
): string {
    if ($text === '') {
        return $text;
    }

    $entries = chimTtsPronunciationEntries($rows, $knowledgeTags);
    if (empty($entries)) {
        return $text;
    }

    $replacements = [];
    $patterns = [];
    foreach ($entries as $entry) {
        $source = strval($entry['source'] ?? '');
        if ($source === '') {
            continue;
        }
        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower($source, 'UTF-8')
            : strtolower($source);
        $replacements[$normalized] = strval($entry['spoken'] ?? '');
        $patterns[] = preg_quote($source, '~');
    }
    if (empty($patterns)) {
        return $text;
    }

    $pattern = '~(?<![\p{L}\p{N}_])(?:' . implode('|', $patterns) . ')(?![\p{L}\p{N}_])~iu';
    $replaced = preg_replace_callback($pattern, static function (array $match) use ($replacements): string {
        $matched = strval($match[0] ?? '');
        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower($matched, 'UTF-8')
            : strtolower($matched);
        return $replacements[$normalized] ?? $matched;
    }, $text);

    return is_string($replaced) ? $replaced : $text;
}
