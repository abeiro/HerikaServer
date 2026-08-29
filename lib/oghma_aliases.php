<?php

/**
 * Shared normalization, validation, indexing, and seed-upgrade helpers for
 * static Oghma aliases. Canonical topics remain stable database identifiers.
 */

if (!function_exists('chimOghmaComparableAliasKey')) {
    function chimOghmaComparableAliasKey($value): string
    {
        $normalized = strtolower(trim((string) $value));
        $normalized = str_replace('_', ' ', $normalized);
        $normalized = preg_replace('/^(?:the|a|an)\s+/u', '', $normalized);
        $normalized = preg_replace('/\b1st\b/u', 'first', (string) $normalized);
        $normalized = preg_replace('/\b2nd\b/u', 'second', (string) $normalized);
        $normalized = preg_replace('/\b3rd\b/u', 'third', (string) $normalized);
        $normalized = preg_replace('/\b4th\b/u', 'fourth', (string) $normalized);
        return preg_replace('/[^a-z0-9]+/u', '', (string) $normalized) ?? '';
    }
}

if (!function_exists('chimOghmaSplitAliases')) {
    function chimOghmaSplitAliases($value): array
    {
        $value = preg_replace('/\s*[|;]\s*/u', ', ', (string) $value) ?? (string) $value;
        $parts = preg_split('/\s*,\s*/u', $value) ?: [];
        return array_values(array_filter(
            array_map('trim', $parts),
            static fn(string $part): bool => $part !== ''
        ));
    }
}

if (!function_exists('chimOghmaEncodeAliasName')) {
    /** Protect commas inside one alias before joining the comma-separated storage field. */
    function chimOghmaEncodeAliasName($value): string
    {
        $value = trim((string) $value);
        return preg_replace('/\s*,\s*/u', '_', $value) ?? $value;
    }
}

if (!function_exists('chimOghmaMergeAliases')) {
    function chimOghmaMergeAliases(string $topic, $existing, $approved): string
    {
        $topicKey = chimOghmaComparableAliasKey($topic);
        $merged = [];
        $seen = [];

        foreach (array_merge(chimOghmaSplitAliases($approved), chimOghmaSplitAliases($existing)) as $alias) {
            $key = chimOghmaComparableAliasKey($alias);
            if ($key === '' || $key === $topicKey || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $merged[] = $alias;
        }

        return implode(', ', $merged);
    }
}

if (!function_exists('chimOghmaReadAliasSeed')) {
    function chimOghmaReadAliasSeed(string $seedPath): array
    {
        $handle = @fopen($seedPath, 'r');
        if (!$handle) {
            throw new RuntimeException('Unable to open Oghma alias seed: ' . $seedPath);
        }

        try {
            $header = fgetcsv($handle);
            if (!is_array($header)) {
                throw new RuntimeException('Oghma alias seed has no CSV header.');
            }
            $columns = [];
            foreach ($header as $index => $name) {
                $name = preg_replace('/^\xEF\xBB\xBF/', '', (string) $name);
                $columns[strtolower(trim((string) $name))] = (int) $index;
            }
            if (!isset($columns['topic'], $columns['aliases'])) {
                throw new RuntimeException('Oghma alias seed must contain topic and aliases columns.');
            }

            $rows = [];
            while (($row = fgetcsv($handle)) !== false) {
                $topic = trim((string) ($row[$columns['topic']] ?? ''));
                if ($topic === '') {
                    continue;
                }
                $rows[] = [
                    'topic' => $topic,
                    'aliases' => trim((string) ($row[$columns['aliases']] ?? '')),
                ];
            }
            return $rows;
        } finally {
            fclose($handle);
        }
    }
}

if (!function_exists('chimOghmaBuildAliasOwnerMaps')) {
    function chimOghmaBuildAliasOwnerMaps(array $rows): array
    {
        $canonicalOwners = [];
        $aliasOwners = [];
        foreach ($rows as $row) {
            $topic = trim((string) ($row['topic'] ?? ''));
            $topicKey = chimOghmaComparableAliasKey($topic);
            if ($topicKey !== '') {
                $canonicalOwners[$topicKey] = $topic;
            }
            foreach (chimOghmaSplitAliases($row['aliases'] ?? '') as $alias) {
                $key = chimOghmaComparableAliasKey($alias);
                if ($key !== '') {
                    $aliasOwners[$key][$topic] = true;
                }
            }
        }
        return [$canonicalOwners, $aliasOwners];
    }
}

if (!function_exists('chimOghmaFilterAliases')) {
    function chimOghmaFilterAliases(
        string $topic,
        $aliases,
        array $canonicalOwners,
        array $aliasOwners = []
    ): array {
        $topicKey = chimOghmaComparableAliasKey($topic);
        $accepted = [];
        $rejected = [];
        $seen = [];

        foreach (chimOghmaSplitAliases($aliases) as $alias) {
            $key = chimOghmaComparableAliasKey($alias);
            $reason = '';
            if ($key === '' || $key === $topicKey || isset($seen[$key])) {
                $reason = 'duplicate or canonical variant';
            } elseif (isset($canonicalOwners[$key]) && chimOghmaComparableAliasKey($canonicalOwners[$key]) !== $topicKey) {
                $reason = 'matches canonical topic ' . $canonicalOwners[$key];
            } else {
                $otherOwners = array_filter(
                    array_keys($aliasOwners[$key] ?? []),
                    static fn(string $owner): bool => chimOghmaComparableAliasKey($owner) !== $topicKey
                );
                if (!empty($otherOwners)) {
                    $reason = 'already used by ' . implode(', ', $otherOwners);
                }
            }

            if ($reason !== '') {
                $rejected[] = ['alias' => $alias, 'reason' => $reason];
                continue;
            }
            $seen[$key] = true;
            $accepted[] = $alias;
        }

        return ['aliases' => implode(', ', $accepted), 'rejected' => $rejected];
    }
}

if (!function_exists('chimOghmaNativeVectorSql')) {
    function chimOghmaNativeVectorSql(): string
    {
        // Keep related-concept tags out of legacy full-text ranking; guarded retrieval handles them separately.
        return "
            setweight(to_tsvector('simple', coalesce(topic, '')), 'A')
            || setweight(to_tsvector('simple', coalesce(aliases, '')), 'A')
            || setweight(to_tsvector(coalesce(topic_desc, '')), 'B')
            || setweight(to_tsvector(coalesce(topic_desc_basic, '')), 'C')
        ";
    }
}

if (!function_exists('chimOghmaApplyAliasSeed')) {
    function chimOghmaApplyAliasSeed($db, string $seedPath, bool $manageTransaction = true): array
    {
        $stats = ['matched' => 0, 'updated' => 0, 'reindexed' => 0, 'rejected' => 0];
        $seedRows = chimOghmaReadAliasSeed($seedPath);
        $databaseRows = $db->fetchAll("SELECT topic, coalesce(aliases, '') AS aliases FROM public.oghma");
        [$canonicalOwners, $aliasOwners] = chimOghmaBuildAliasOwnerMaps(is_array($databaseRows) ? $databaseRows : []);
        $byTopic = [];
        $byComparableTopic = [];
        foreach ($databaseRows as $row) {
            $byTopic[strtolower((string) ($row['topic'] ?? ''))] = $row;
            $comparableKey = chimOghmaComparableAliasKey($row['topic'] ?? '');
            if ($comparableKey === '') {
                continue;
            }
            if (!array_key_exists($comparableKey, $byComparableTopic)) {
                $byComparableTopic[$comparableKey] = $row;
            } else {
                // Never guess when two stored topics normalize to the same key.
                $byComparableTopic[$comparableKey] = null;
            }
        }

        if ($manageTransaction) {
            $db->execQuery('BEGIN');
        }
        try {
            foreach ($seedRows as $seedRow) {
                $lookup = strtolower($seedRow['topic']);
                $existing = $byTopic[$lookup]
                    ?? $byComparableTopic[chimOghmaComparableAliasKey($seedRow['topic'])]
                    ?? null;
                if (!is_array($existing)) {
                    continue;
                }
                $stats['matched']++;
                $merged = chimOghmaMergeAliases(
                    (string) $existing['topic'],
                    $existing['aliases'] ?? '',
                    $seedRow['aliases'] ?? ''
                );
                $filtered = chimOghmaFilterAliases(
                    (string) $existing['topic'],
                    $merged,
                    $canonicalOwners,
                    $aliasOwners
                );
                $stats['rejected'] += count($filtered['rejected']);
                $aliases = $filtered['aliases'];
                $topicEscaped = $db->escape((string) $existing['topic']);
                if ($aliases !== (string) ($existing['aliases'] ?? '')) {
                    $db->execQuery(
                        "UPDATE public.oghma SET aliases = '" . $db->escape($aliases)
                        . "' WHERE topic = '{$topicEscaped}'"
                    );
                    $stats['updated']++;
                }
                $db->execQuery(
                    'UPDATE public.oghma SET native_vector = ' . chimOghmaNativeVectorSql()
                    . " WHERE topic = '{$topicEscaped}'"
                );
                $stats['reindexed']++;
                $storedLookup = strtolower((string) $existing['topic']);
                $byTopic[$storedLookup]['aliases'] = $aliases;
                $comparableKey = chimOghmaComparableAliasKey($existing['topic']);
                if (isset($byComparableTopic[$comparableKey])) {
                    $byComparableTopic[$comparableKey]['aliases'] = $aliases;
                }
                foreach (chimOghmaSplitAliases($aliases) as $alias) {
                    $aliasKey = chimOghmaComparableAliasKey($alias);
                    if ($aliasKey !== '') {
                        $aliasOwners[$aliasKey][(string) $existing['topic']] = true;
                    }
                }
            }
            if ($manageTransaction) {
                $db->execQuery('COMMIT');
            }
        } catch (Throwable $exception) {
            if ($manageTransaction) {
                $db->execQuery('ROLLBACK');
            }
            throw $exception;
        }

        return $stats;
    }
}
