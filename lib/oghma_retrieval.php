<?php

/**
 * Deterministic, catalog-grounded Oghma entity extraction.
 *
 * The extractor favors canonical topics and aliases, tolerates bounded STT
 * errors, keeps conversational mention order, and suppresses wrong-sense
 * homonyms before any lore is injected.
 */

function chimOghmaNormalizeTopicKey(string $value): string
{
    static $cache = [];
    if (isset($cache[$value])) {
        return $cache[$value];
    }
    if (count($cache) >= 8192) {
        $cache = [];
    }
    $original = $value;
    $value = mb_strtolower(trim($value), 'UTF-8');
    $value = str_replace('_', ' ', $value);
    $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
    return $cache[$original] = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
}

function chimOghmaStrictEntityPhrase(string $value): string
{
    static $cache = [];
    if (isset($cache[$value])) {
        return $cache[$value];
    }
    if (count($cache) >= 8192) {
        $cache = [];
    }
    $original = $value;
    $value = mb_strtolower(trim(str_replace('_', ' ', $value)), 'UTF-8');
    $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
    return $cache[$original] = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
}

function chimOghmaCompactEntityKey(string $value): string
{
    static $cache = [];
    if (isset($cache[$value])) {
        return $cache[$value];
    }
    if (count($cache) >= 8192) {
        $cache = [];
    }
    $original = $value;
    $normalized = chimOghmaStrictEntityPhrase($value);
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
    return $cache[$original] = preg_replace('/[^a-z0-9]+/', '', strtolower($ascii === false ? $normalized : $ascii)) ?? '';
}

function chimOghmaSplitAliasValues(string $value): array
{
    return array_values(array_filter(
        array_map('trim', preg_split('/\s*,\s*/u', $value) ?: []),
        static fn(string $alias): bool => $alias !== ''
    ));
}

// Limit history carry-over to short, explicitly referential follow-up lines.
function chimOghmaShouldUsePreviousExchange(string $text): bool
{
    $normalized = chimOghmaStrictEntityPhrase($text);
    if ($normalized === '' || mb_strlen($normalized, 'UTF-8') > 240) {
        return false;
    }
    if (preg_match(
        '/^(?:ok(?:ay)?|thanks?|thank you|sure|right|fine|good|got it|i see|never mind|nevermind|forget it|lets go|let us go)$/u',
        $normalized
    ) === 1) {
        return false;
    }
    if (preg_match(
        '/\b(?:tell me more|go on|what else|anything else|what happened next|why is that|how so)\b/u',
        $normalized
    ) === 1) {
        return true;
    }

    $hasReference = preg_match(
        '/\b(?:it|its|they|them|their|theirs|he|him|his|she|her|hers|this|that|these|those|there|former|latter)\b/u',
        $normalized
    ) === 1;
    $hasKnowledgeCue = preg_match(
        '/\b(?:who|what|where|when|why|how|which|leader|leaders|founder|founders|origin|origins|history|story|purpose|'
        . 'member|members|enemy|enemies|ally|allies|located|happened|mean|means|more|else|dangerous|safe|powerful|important)\b/u',
        $normalized
    ) === 1;
    return $hasReference && $hasKnowledgeCue;
}

function chimOghmaReviewedPhraseCanonicalization(): array
{
    static $canonicalization = null;
    if (is_array($canonicalization)) {
        return $canonicalization;
    }
    $path = dirname(__DIR__) . '/resources/oghma/skyrim-official/ontology.json';
    $ontology = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
    $canonicalization = [];
    foreach (($ontology['retrieval_phrase_canonicalization'] ?? []) as $variant => $canonical) {
        $variantKey = chimOghmaStrictEntityPhrase((string) $variant);
        $canonicalKey = chimOghmaStrictEntityPhrase((string) $canonical);
        if ($variantKey !== '' && $canonicalKey !== '') {
            $canonicalization[$variantKey] = $canonicalKey;
        }
    }
    return $canonicalization;
}

function chimOghmaCanonicalizeReviewedPhrase(string $phrase): string
{
    $normalized = chimOghmaStrictEntityPhrase($phrase);
    return chimOghmaReviewedPhraseCanonicalization()[$normalized] ?? $normalized;
}

// Build the per-request canonical, alias, compact-name, and prefix indexes.
function chimOghmaEntityLexicon($db): array
{
    static $objectCache = null;
    static $fallbackCache = null;
    if (is_object($db)) {
        $objectCache ??= new WeakMap();
        if (isset($objectCache[$db])) {
            return $objectCache[$db];
        }
    } elseif (is_array($fallbackCache)) {
        return $fallbackCache;
    }

    $rows = $db->fetchAll(
        "SELECT topic, coalesce(aliases, '') AS aliases,
                coalesce(retrieval_phrases, '') AS retrieval_phrases, coalesce(tags, '') AS tags,
                coalesce(category, '') AS category
           FROM public.oghma
          ORDER BY topic"
    );
    $phrases = [];
    $retrievalPhrases = [];
    $tagPhrases = [];
    $topicCategories = [];
    foreach ($rows as $row) {
        $topic = strval($row['topic'] ?? '');
        $topicCategories[chimOghmaNormalizeTopicKey($topic)] = strval($row['category'] ?? '');
        $canonical = chimOghmaStrictEntityPhrase($topic);
        if ($canonical !== '') {
            $phrases[$canonical]['owners'][$topic] = true;
            $phrases[$canonical]['canonical_owners'][$topic] = true;
        }
        foreach (chimOghmaSplitAliasValues(strval($row['aliases'] ?? '')) as $alias) {
            $phrase = chimOghmaStrictEntityPhrase($alias);
            if ($phrase !== '') {
                $phrases[$phrase]['owners'][$topic] = true;
                $phrases[$phrase]['alias_owners'][$topic] = true;
            }
        }
        foreach (chimOghmaSplitAliasValues(strval($row['retrieval_phrases'] ?? '')) as $retrievalPhrase) {
            $phrase = chimOghmaStrictEntityPhrase($retrievalPhrase);
            $canonicalPhrase = chimOghmaCanonicalizeReviewedPhrase($phrase);
            if ($phrase !== '' && $canonicalPhrase !== '') {
                $retrievalPhrases[$canonicalPhrase]['owners'][$topic] = true;
                $retrievalPhrases[$canonicalPhrase]['variants'][$phrase] = true;
                $retrievalPhrases[$canonicalPhrase]['variants'][$canonicalPhrase] = true;
            }
        }
        foreach (chimOghmaSplitAliasValues(strval($row['tags'] ?? '')) as $tag) {
            $phrase = chimOghmaStrictEntityPhrase($tag);
            $canonicalPhrase = chimOghmaCanonicalizeReviewedPhrase($phrase);
            if ($phrase !== '' && $canonicalPhrase !== '') {
                $tagPhrases[$canonicalPhrase]['owners'][$topic] = true;
                $tagPhrases[$canonicalPhrase]['variants'][$phrase] = true;
                $tagPhrases[$canonicalPhrase]['variants'][$canonicalPhrase] = true;
            }
        }
    }

    $entries = [];
    $concreteEntries = [];
    $genericCanonicalEntries = [];
    $buckets = [];
    $byTopic = [];
    $byCompact = [];
    $prefixOwners = [];
    foreach ($phrases as $phrase => $owners) {
        $ownerTopics = array_keys($owners['owners'] ?? []);
        $canonicalOwnerTopics = array_keys($owners['canonical_owners'] ?? []);
        $compactPhrase = chimOghmaCompactEntityKey($phrase);
        for ($length = 5; $length <= min(9, strlen($compactPhrase) - 1); $length++) {
            foreach ($ownerTopics as $ownerTopic) {
                $prefixOwners[substr($compactPhrase, 0, $length)][chimOghmaNormalizeTopicKey($ownerTopic)] = true;
            }
        }
        if (count($canonicalOwnerTopics) === 1) {
            $topic = $canonicalOwnerTopics[0];
        } elseif (count($ownerTopics) === 1) {
            $topic = $ownerTopics[0];
        } else {
            continue;
        }
        $entry = [
            'topic' => $topic,
            'phrase' => $phrase,
            'compact' => chimOghmaCompactEntityKey($phrase),
            'token_count' => count(preg_split('/\s+/u', $phrase) ?: []),
            'canonical' => isset($owners['canonical_owners'][$topic]),
            'category' => strtolower(trim(strval($topicCategories[chimOghmaNormalizeTopicKey($topic)] ?? ''))),
        ];
        $entries[] = $entry;
        if (in_array($entry['category'], ['artifact', 'artifacts', 'creature', 'creatures', 'equipment', 'item', 'items', 'object', 'objects'], true)) {
            $concreteEntries[] = $entry;
        }
        if ($entry['canonical'] && chimOghmaIsGenericSingleWord(['entity_phrase' => $entry['phrase']])) {
            $genericCanonicalEntries[] = $entry;
        }
        $buckets[$entry['token_count']][strlen($entry['compact'])][] = $entry;
        $byTopic[$topic][] = $entry;
        $byCompact[$entry['compact']][] = $entry;
    }
    $retrievalPhraseEntries = [];
    $maximumRetrievalPhraseTokens = 2;
    foreach ($retrievalPhrases as $canonicalPhrase => $owners) {
        $ownerTopics = array_keys($owners['owners'] ?? []);
        foreach (array_keys($owners['variants'] ?? []) as $phrase) {
            $tokenCount = count(preg_split('/\s+/u', $phrase) ?: []);
            if ($tokenCount < 2 || isset($phrases[$phrase])) {
                continue;
            }
            $retrievalPhraseEntries[$phrase] = [
                'phrase' => $phrase,
                'canonical_phrase' => $canonicalPhrase,
                'owners' => $ownerTopics,
                'owner_count' => count($ownerTopics),
                'token_count' => $tokenCount,
            ];
            $maximumRetrievalPhraseTokens = max($maximumRetrievalPhraseTokens, $tokenCount);
        }
    }
    $tagPhraseEntries = [];
    $maximumTagTokens = 2;
    foreach ($tagPhrases as $canonicalPhrase => $owners) {
        $ownerTopics = array_keys($owners['owners'] ?? []);
        foreach (array_keys($owners['variants'] ?? []) as $phrase) {
            $tokenCount = count(preg_split('/\s+/u', $phrase) ?: []);
            if ($tokenCount < 2 || isset($phrases[$phrase])) {
                continue;
            }
            $tagPhraseEntries[$phrase] = [
                'phrase' => $phrase,
                'canonical_phrase' => $canonicalPhrase,
                'owners' => $ownerTopics,
                'owner_count' => count($ownerTopics),
                'token_count' => $tokenCount,
            ];
            $maximumTagTokens = max($maximumTagTokens, $tokenCount);
        }
    }
    $cache = [
        'entries' => $entries,
        'concrete_entries' => $concreteEntries,
        'generic_canonical_entries' => $genericCanonicalEntries,
        'buckets' => $buckets,
        'by_topic' => $byTopic,
        'by_compact' => $byCompact,
        'prefix_owners' => $prefixOwners,
        'phrase_owners' => $phrases,
        'topic_categories' => $topicCategories,
        'retrieval_phrase_entries' => $retrievalPhraseEntries,
        'maximum_retrieval_phrase_tokens' => $maximumRetrievalPhraseTokens,
        'tag_phrase_entries' => $tagPhraseEntries,
        'maximum_tag_tokens' => $maximumTagTokens,
    ];
    if (is_object($db)) {
        $objectCache[$db] = $cache;
    } else {
        $fallbackCache = $cache;
    }
    return $cache;
}

// Resolve only explicitly curated retrieval phrases after ordinary entity extraction abstains.
function chimOghmaTagFallbackEntities(array $lexicon, string $text, int $amount, float $requestScore): array
{
    $normalized = chimOghmaStrictEntityPhrase($text);
    $speakerLabel = chimOghmaCatalogSpeakerLabelPhrase($lexicon, $text);
    if ($speakerLabel === '') {
        $speakerLabel = chimOghmaSpeakerLabelPhrase($text);
    }
    $speakerLabelEnd = $speakerLabel !== '' ? strlen($speakerLabel) : 0;
    preg_match_all('/[\p{L}\p{N}]+/u', $normalized, $wordMatches, PREG_OFFSET_CAPTURE);
    $words = $wordMatches[0] ?? [];
    $tagEntries = $lexicon['retrieval_phrase_entries'] ?? [];
    if ($tagEntries === [] || count($words) < 2) {
        return ['entities' => [], 'rejected' => [], 'decisions' => []];
    }
    $maximumTagTokens = max(2, intval($lexicon['maximum_retrieval_phrase_tokens'] ?? 2));
    $matchedEntries = [];
    for ($start = 0; $start < count($words); $start++) {
        $maximumLength = min($maximumTagTokens, count($words) - $start);
        for ($length = 2; $length <= $maximumLength; $length++) {
            $phrase = implode(' ', array_column(array_slice($words, $start, $length), 0));
            if (isset($tagEntries[$phrase])) {
                $position = intval($words[$start][1] ?? 0);
                if (!isset($matchedEntries[$phrase]) || $position < $matchedEntries[$phrase]['position']) {
                    $matchedEntries[$phrase] = [
                        'entry' => $tagEntries[$phrase],
                        'position' => $position,
                        'end' => intval($words[$start + $length - 1][1] ?? $position)
                            + strlen(strval($words[$start + $length - 1][0] ?? '')),
                    ];
                }
            }
        }
    }
    $orderedMatches = array_values($matchedEntries);
    usort($orderedMatches, static function (array $left, array $right): int {
        $leftSpan = intval($left['end']) - intval($left['position']);
        $rightSpan = intval($right['end']) - intval($right['position']);
        $spanDifference = $rightSpan <=> $leftSpan;
        return $spanDifference !== 0
            ? $spanDifference
            : intval($left['position']) <=> intval($right['position']);
    });
    $selectedMatches = [];
    foreach ($orderedMatches as $matched) {
        $overlaps = array_filter(
            $selectedMatches,
            static fn(array $selected): bool => intval($matched['position']) < intval($selected['end'])
                && intval($selected['position']) < intval($matched['end'])
        ) !== [];
        if (!$overlaps) {
            $selectedMatches[] = $matched;
        }
    }
    $byTopic = [];
    $rejected = [];
    $decisions = [];
    foreach ($selectedMatches as $matched) {
        $entry = $matched['entry'];
        $phrase = strval($entry['phrase'] ?? '');
        if ($phrase === '') {
            continue;
        }
        if (intval($entry['owner_count'] ?? 0) !== 1) {
            $decision = ['phrase' => $phrase, 'reason' => 'retrieval_phrase_ambiguous', 'source' => 'retrieval_phrase',
                'start' => intval($matched['position']), 'owner_count' => intval($entry['owner_count'] ?? 0),
                'topics' => array_values($entry['owners'] ?? [])];
            $rejected[] = $decision;
            $decisions[] = $decision;
            continue;
        }
        $position = intval($matched['position']);
        if ($speakerLabelEnd > 0 && $position < $speakerLabelEnd) {
            $decision = ['phrase' => $phrase, 'reason' => 'retrieval_phrase_speaker_label', 'source' => 'retrieval_phrase',
                'start' => $position, 'owner_count' => intval($entry['owner_count'] ?? 0),
                'topics' => array_values($entry['owners'] ?? [])];
            $rejected[] = $decision;
            $decisions[] = $decision;
            continue;
        }
        foreach ($entry['owners'] ?? [] as $topic) {
            $topicKey = chimOghmaNormalizeTopicKey(strval($topic));
            if (!isset($byTopic[$topicKey])) {
                $byTopic[$topicKey] = [
                    'topic' => strval($topic),
                    'phrases' => [],
                    'start' => $position,
                ];
            }
            $byTopic[$topicKey]['phrases'][$phrase] = intval($entry['owner_count'] ?? 0);
            $byTopic[$topicKey]['start'] = min(intval($byTopic[$topicKey]['start']), $position);
        }
    }

    $candidates = [];
    foreach ($byTopic as $candidate) {
        $ownerCounts = array_values($candidate['phrases']);
        $phrase = strval(array_key_first($candidate['phrases']));
        $candidates[] = [
            'topic' => strval($candidate['topic']),
            'phrase' => $phrase,
            'entity_phrase' => $phrase,
            'source' => 'exact safe retrieval phrase',
            'start' => intval($candidate['start']),
            'end' => intval($candidate['start']) + strlen($phrase),
            'score' => 0.82,
            'context_score' => 0.82,
            'mention_count' => 1,
            'retrieval_phrases' => array_keys($candidate['phrases']),
            'retrieval_phrase_owner_counts' => $ownerCounts,
        ];
    }
    usort($candidates, static function (array $left, array $right): int {
        $score = floatval($right['score']) <=> floatval($left['score']);
        return $score !== 0 ? $score : intval($left['start']) <=> intval($right['start']);
    });
    $candidates = array_slice($candidates, 0, max(1, $amount));
    foreach ($candidates as $candidate) {
        $decisions[] = ['topic' => $candidate['topic'], 'phrase' => $candidate['phrase'], 'reason' => 'retrieval_phrase_selected',
            'source' => $candidate['source'], 'start' => $candidate['start'],
            'support_count' => count($candidate['retrieval_phrases'] ?? [])];
    }
    return ['entities' => $candidates, 'rejected' => $rejected, 'decisions' => $decisions];
}

// Relational tags can strengthen an already identified topic but never create one.
function chimOghmaApplyRelationalTagSupport(array $lexicon, string $text, array $entities): array
{
    if ($entities === [] || ($lexicon['tag_phrase_entries'] ?? []) === []) {
        return $entities;
    }
    $normalized = ' ' . chimOghmaStrictEntityPhrase($text) . ' ';
    foreach ($entities as &$entity) {
        $topic = strval($entity['topic'] ?? '');
        $matched = [];
        foreach ($lexicon['tag_phrase_entries'] as $phrase => $entry) {
            if (str_contains($normalized, ' ' . $phrase . ' ')
                && in_array($topic, $entry['owners'] ?? [], true)) {
                $matched[] = $phrase;
            }
        }
        if ($matched !== []) {
            $entity['relational_tag_phrases'] = array_values(array_unique($matched));
            $bonus = min(0.08, count($entity['relational_tag_phrases']) * 0.04);
            $entity['score'] = floatval($entity['score'] ?? 0.0) + $bonus;
            $entity['context_score'] = floatval($entity['context_score'] ?? 0.0) + $bonus;
        }
    }
    unset($entity);
    return $entities;
}

// Produce bounded word windows used to compare STT fragments with catalog names.
function chimOghmaTranscriptTokenWindows(string $text, int $maximumTokens = 7): array
{
    $normalized = chimOghmaStrictEntityPhrase($text);
    preg_match_all('/[\p{L}\p{N}]+/u', $normalized, $matches, PREG_OFFSET_CAPTURE);
    $tokens = $matches[0] ?? [];
    $windows = [];
    $tokenCount = count($tokens);
    for ($start = 0; $start < $tokenCount; $start++) {
        for ($length = 1; $length <= $maximumTokens && $start + $length <= $tokenCount; $length++) {
            $parts = [];
            for ($offset = 0; $offset < $length; $offset++) {
                $parts[] = strval($tokens[$start + $offset][0]);
            }
            $phrase = implode(' ', $parts);
            $startOffset = intval($tokens[$start][1]);
            $lastToken = $tokens[$start + $length - 1];
            $endOffset = intval($lastToken[1]) + strlen(strval($lastToken[0]));
            $windows[] = [
                'phrase' => $phrase,
                'compact' => chimOghmaCompactEntityKey($phrase),
                'start' => $startOffset,
                'end' => $endOffset,
                'token_count' => $length,
            ];
        }
    }
    return $windows;
}

function chimOghmaSpansOverlap(array $left, array $right): bool
{
    return intval($left['start']) < intval($right['end']) && intval($right['start']) < intval($left['end']);
}

function chimOghmaKnowledgeRequestScore(string $text): float
{
    $normalized = chimOghmaStrictEntityPhrase($text);
    if (preg_match('/\b(?:do not|dont|never mind|forget)\b.{0,30}\b(?:explain|describe|discuss|tell|teach)\b/u', $normalized)) {
        return 0.0;
    }
    if (preg_match(
        '/\b(?:tell|explain|describe|discuss|teach|learn|background|overview|information|details|'
        . 'story|history|significance|facts|questions?|heard|understand|curious|who|what|where|why|how|compare)\b/u',
        $normalized
    )) {
        return 1.0;
    }
    if (preg_match('/\b(?:do|did|would|could|should|can)\s+(?:you|we|they|i)\s+know\b|\bknow\s+(?:about|of)\b/u', $normalized)) {
        return 1.0;
    }
    return 0.0;
}

// Score how central an exact or compact entity mention is to the current message.
function chimOghmaCandidateSalience(string $text, array $candidate, float $requestScore): float
{
    $normalized = chimOghmaStrictEntityPhrase($text);
    $phrase = strval($candidate['phrase'] ?? '');
    $entityPhrase = strval($candidate['entity_phrase'] ?? $phrase);
    $tokenCount = count(preg_split('/\s+/u', $entityPhrase) ?: []);
    $score = floatval($candidate['entity_score'] ?? 0.80);
    if ($tokenCount > 1) {
        $score += 0.08;
    }
    $score += $requestScore * 0.15;
    $occurrences = preg_match_all(
        '/(?<![a-z0-9])' . preg_quote($phrase, '/') . '(?![a-z0-9])/u',
        $normalized
    );
    if ($occurrences > 1) {
        $score += min(0.16, ($occurrences - 1) * 0.08);
    }
    $prefix = substr($normalized, 0, max(0, intval($candidate['start'] ?? 0)));
    $prefixTokens = count(array_filter(preg_split('/\s+/u', trim($prefix)) ?: []));
    if ($prefixTokens <= 5) {
        $score += 0.06;
    }
    if (preg_match('/\b(?:about|regarding|concerning|of|through|to|at|in|near|with|against)\s*$/u', $prefix)) {
        $score += 0.05;
    }
    $commonShort = [
        'fast', 'light', 'hell', 'dragon', 'armor', 'poison', 'house', 'deer', 'elk', 'fish',
        'human', 'magic', 'fire', 'frost', 'storm', 'fear', 'calm', 'healing', 'health', 'home',
    ];
    if ($tokenCount === 1 && in_array($entityPhrase, $commonShort, true) && $requestScore < 0.5) {
        $score -= 0.30;
    }
    return $score;
}

function chimOghmaSpeakerLabelPhrase(string $text): string
{
    if (!preg_match('/^\s*([^:\r\n]{1,60}):\s+/u', $text, $match)) {
        return '';
    }
    $label = chimOghmaStrictEntityPhrase(strval($match[1]));
    $tokens = preg_split('/\s+/u', $label) ?: [];
    if ($label === '' || count($tokens) > 4) {
        return '';
    }
    if (preg_match('/\b(?:era|volume|book|chapter|part|act)\b/u', $label)) {
        return '';
    }
    if (preg_match('/^(?:i|we|they|he|she|you|it|there)\b/u', $label)) {
        return '';
    }
    if (preg_match('/^(?:remember|note|consider|compare|start|first|although|while|for|to|source|reference)\b/u', $label)) {
        return '';
    }
    if (preg_match('/^(?:remember|note|consider|compare|start|first|although|while|for|to|source|reference)\b/u', $label)) {
        return '';
    }
    return $label;
}

function chimOghmaPhoneticKey(string $value): string
{
    $value = chimOghmaCompactEntityKey($value);
    $value = strtr($value, [
        'ph' => 'f',
        'ck' => 'k',
        'qu' => 'kw',
        'th' => 't',
        'ee' => 'i',
        'ea' => 'i',
        'y' => 'i',
    ]);
    return preg_replace('/(.)\1+/u', '$1', $value) ?? $value;
}

function chimOghmaTranspositionAwareDistance(string $left, string $right): int
{
    $distance = levenshtein($left, $right);
    if ($distance !== 2 || strlen($left) !== strlen($right)) {
        return $distance;
    }
    $mismatches = [];
    for ($index = 0; $index < strlen($left); $index++) {
        if ($left[$index] !== $right[$index]) {
            $mismatches[] = $index;
            if (count($mismatches) > 2) {
                return $distance;
            }
        }
    }
    if (count($mismatches) === 2
        && $mismatches[1] === $mismatches[0] + 1
        && $left[$mismatches[0]] === $right[$mismatches[1]]
        && $left[$mismatches[1]] === $right[$mismatches[0]]) {
        return 1;
    }
    return $distance;
}

function chimOghmaEntityCueStrength(string $text, array $candidate, float $requestScore): float
{
    if ($requestScore >= 0.5) {
        return 1.0;
    }
    $normalized = chimOghmaStrictEntityPhrase($text);
    $start = max(0, intval($candidate['start'] ?? 0));
    $end = max($start, intval($candidate['end'] ?? $start));
    $prefix = trim(substr($normalized, 0, $start));
    $suffix = trim(substr($normalized, $end));
    $phrase = chimOghmaStrictEntityPhrase(strval($candidate['entity_phrase'] ?? $candidate['phrase'] ?? ''));
    if (preg_match('/\bonly\s+passed\s*$/u', $prefix)) {
        return 0.2;
    }
    if (preg_match('/\b(?:about|of|near|toward|towards|at|into|from|through|visited|saw|passed|reached|entered|left)\s*$/u', $prefix)) {
        return 1.0;
    }
    if (preg_match('/\b(?:name|names|named|call|calls|called|describe|describes|described|recognize|recognizes|recognized|'
        . 'report|reports|reported|identify|identifies|identified|mention|mentions|mentioned|discuss|discusses|discussed|'
        . 'depict|depicts|depicted|show|shows|showed|list|lists|listed|spot|spots|spotted|sight|sights|sighted|'
        . 'encounter|encounters|encountered|record|records|recorded|observe|observes|observed|'
        . 'report(?:s|ed)?\s+(?:see|sees|saw|seen|seeing)|reference\s+to)\s*$/u', $prefix)) {
        return 1.0;
    }
    if (preg_match('/\b(?:called|named|said|says|was|is|as|like|words?)\s*$/u', $prefix)) {
        return 0.8;
    }
    if (function_exists('chimOghmaHasTranscriptCue') && chimOghmaHasTranscriptCue($text)) {
        return 0.8;
    }
    if (preg_match('/\b(?:voice\s+recognition\s+returned|transcript\s+returned|recording\s+rendered)\s*$/u', $prefix)) {
        return 0.8;
    }
    if ($start <= 1 && preg_match('/^(?:appeared|stood|waited|looked|seemed|was|were)\b/u', $suffix)) {
        return 0.8;
    }
    if (preg_match('/\b(?:searching|looking|asking|warned|spoke|talking|heard|read|journal|map|guide)\b/u', $normalized)) {
        return 0.55;
    }
    return 0.0;
}

function chimOghmaEntitySalience(string $text, array $candidate, float $requestScore): float
{
    $score = chimOghmaCandidateSalience($text, $candidate, $requestScore);
    $cueStrength = chimOghmaEntityCueStrength($text, $candidate, $requestScore);
    $source = strval($candidate['source'] ?? '');
    if (str_contains($source, 'exact canonical')) {
        $score += 0.12;
    } elseif (str_contains($source, 'compact canonical')) {
        $score += 0.09;
    } elseif (str_contains($source, 'exact alias')) {
        $score += 0.06;
    } elseif (str_contains($source, 'compact alias')) {
        $score += 0.03;
    }
    if ($cueStrength >= 0.8) {
        $score += 0.12;
    } elseif ($cueStrength >= 0.5) {
        $score += 0.05;
    }
    $normalized = chimOghmaStrictEntityPhrase($text);
    $end = max(0, intval($candidate['end'] ?? 0));
    if (preg_match('/^\s*is\s+our\s+real\s+concern\b/u', substr($normalized, $end))) {
        $score += 0.25;
    }
    return $score;
}

// Find bounded phonetic/edit-distance matches without accepting ambiguous neighbors.
function chimOghmaFuzzyEntities(array $lexicon, string $text, array $overlapSpans = []): array
{
    static $lengthBuckets = null;
    if (!is_array($lengthBuckets)) {
        $lengthBuckets = [];
        foreach ($lexicon['entries'] as $entry) {
            $entry['phonetic'] = chimOghmaPhoneticKey(strval($entry['compact']));
            $lengthBuckets[strlen(strval($entry['compact']))][] = $entry;
        }
    }

    $windows = chimOghmaTranscriptTokenWindows($text);
    if ($overlapSpans !== []) {
        $windows = array_values(array_filter($windows, static function (array $window) use ($overlapSpans): bool {
            foreach ($overlapSpans as $span) {
                if (chimOghmaSpansOverlap($window, $span)) {
                    return true;
                }
            }
            return false;
        }));
    }

    $requestScore = chimOghmaKnowledgeRequestScore($text);
    $byTopic = [];
    foreach ($windows as $window) {
        $windowCompact = strval($window['compact']);
        $windowLength = strlen($windowCompact);
        $windowPhonetic = chimOghmaPhoneticKey($windowCompact);
        if ($windowLength < 6) {
            continue;
        }
        for ($length = max(6, $windowLength - 3); $length <= $windowLength + 3; $length++) {
            foreach ($lengthBuckets[$length] ?? [] as $entry) {
                $entityCompact = strval($entry['compact']);
                $literalDistance = chimOghmaTranspositionAwareDistance($windowCompact, $entityCompact);
                $entityPhonetic = strval($entry['phonetic']);
                $phoneticDistance = chimOghmaTranspositionAwareDistance($windowPhonetic, $entityPhonetic);
                $maximumDistance = max($windowLength, strlen($entityCompact)) <= 8 ? 2 : 3;
                $distance = min($literalDistance, $phoneticDistance);
                if ($literalDistance === 0 || $distance > $maximumDistance) {
                    continue;
                }
                $denominator = max(
                    strlen($windowPhonetic),
                    strlen($entityPhonetic),
                    1
                );
                $similarity = max(
                    1.0 - ($literalDistance / max($windowLength, strlen($entityCompact))),
                    1.0 - ($phoneticDistance / $denominator) - 0.02
                );
                if ($similarity < 0.78) {
                    continue;
                }
                $candidate = [
                    'topic' => strval($entry['topic']),
                    'phrase' => strval($window['phrase']),
                    'entity_phrase' => strval($entry['phrase']),
                    'source' => 'guarded phonetic entity',
                    'start' => intval($window['start']),
                    'end' => intval($window['end']),
                    'entity_score' => $similarity,
                    'distance' => $distance,
                    'literal_distance' => $literalDistance,
                ];
                $cueStrength = chimOghmaEntityCueStrength($text, $candidate, $requestScore);
                $isTruncatedPrefix = str_starts_with($entityCompact, $windowCompact)
                    && strlen($entityCompact) - strlen($windowCompact) <= 2;
                if ($isTruncatedPrefix && $cueStrength < 0.8) {
                    continue;
                }
                $windowWords = preg_split('/\s+/u', strval($window['phrase'])) ?: [];
                $commonWords = ['a', 'an', 'the', 'and', 'of', 'on', 'at', 'in', 'to', 'for', 'with', 'water', 'soon', 'stone'];
                $distinctiveWords = array_filter(
                    $windowWords,
                    static fn(string $word): bool => strlen($word) >= 5 && !in_array($word, $commonWords, true)
                );
                if ($cueStrength < 0.5 && $distinctiveWords === [] && $similarity < 0.92) {
                    continue;
                }
                $candidate['score'] = chimOghmaEntitySalience($text, $candidate, $requestScore);
                if ($cueStrength < 0.5 && $similarity < 0.84) {
                    continue;
                }
                $topicKey = chimOghmaNormalizeTopicKey($candidate['topic']);
                if (!isset($byTopic[$topicKey]) || $candidate['score'] > $byTopic[$topicKey]['score']) {
                    $byTopic[$topicKey] = $candidate;
                }
            }
        }
    }
    return array_values($byTopic);
}

// Collect exact, alias, compact, and guarded fuzzy candidates from the message.
function chimOghmaBaseEntities($db, string $text, int $amount): array
{
    $lexicon = chimOghmaEntityLexicon($db);
    $normalized = chimOghmaStrictEntityPhrase($text);
    $requestScore = chimOghmaKnowledgeRequestScore($text);
    $speakerLabel = chimOghmaSpeakerLabelPhrase($text);
    $speakerLabelEnd = $speakerLabel !== '' ? strlen($speakerLabel) : 0;
    $exact = [];

    foreach ($lexicon['entries'] as $entry) {
        $entryPhrase = strval($entry['phrase']);
        if (!str_contains($normalized, $entryPhrase)) {
            continue;
        }
        $pattern = '/(?<![a-z0-9])' . preg_quote($entryPhrase, '/') . '(?![a-z0-9])/u';
        if (!preg_match_all($pattern, $normalized, $matches, PREG_OFFSET_CAPTURE)) {
            continue;
        }
        foreach ($matches[0] as $match) {
            $start = intval($match[1]);
            if ($speakerLabelEnd > 0 && $start < $speakerLabelEnd) {
                continue;
            }
            $candidate = [
                'topic' => strval($entry['topic']),
                'phrase' => strval($entry['phrase']),
                'entity_phrase' => strval($entry['phrase']),
                'source' => $entry['canonical'] ? 'exact canonical' : 'exact alias',
                'start' => $start,
                'end' => $start + strlen(strval($match[0])),
                'entity_score' => $entry['canonical'] ? 0.90 : 0.86,
                'distance' => 0,
            ];
            $candidate['score'] = chimOghmaEntitySalience($text, $candidate, $requestScore);
            $exact[] = $candidate;
        }
    }

    if (preg_match('/^\s*([^:\r\n]{1,80}):\s*(.+)$/u', $text, $titleMatch)) {
        $titlePrefix = chimOghmaStrictEntityPhrase(strval($titleMatch[1]));
        $owners = array_keys($lexicon['phrase_owners'][$titlePrefix]['owners'] ?? []);
        $bookOwners = array_values(array_filter($owners, static function (string $topic) use ($lexicon): bool {
            return strval($lexicon['topic_categories'][chimOghmaNormalizeTopicKey($topic)] ?? '') === 'books';
        }));
        if (count($bookOwners) === 1) {
            $candidate = [
                'topic' => $bookOwners[0],
                'phrase' => $titlePrefix,
                'entity_phrase' => $titlePrefix,
                'source' => 'exact alias title',
                'start' => 0,
                'end' => strlen($titlePrefix),
                'entity_score' => 1.04,
                'distance' => 0,
            ];
            $candidate['score'] = chimOghmaEntitySalience($text, $candidate, $requestScore);
            $exact[] = $candidate;
        }
    }

    foreach (chimOghmaTranscriptTokenWindows($text) as $window) {
        $entries = $lexicon['by_compact'][strval($window['compact'])] ?? [];
        if ($entries === []) {
            continue;
        }
        $topics = array_values(array_unique(array_map(
            static fn(array $entry): string => chimOghmaNormalizeTopicKey(strval($entry['topic'])),
            $entries
        )));
        if (count($topics) !== 1) {
            continue;
        }
        usort($entries, static fn(array $left, array $right): int => intval($right['canonical']) <=> intval($left['canonical']));
        $entry = $entries[0];
        if ($speakerLabelEnd > 0 && intval($window['start']) < $speakerLabelEnd) {
            continue;
        }
        $candidate = [
            'topic' => strval($entry['topic']),
            'phrase' => strval($window['phrase']),
            'entity_phrase' => strval($entry['phrase']),
            'source' => $entry['canonical'] ? 'compact canonical' : 'compact alias',
            'start' => intval($window['start']),
            'end' => intval($window['end']),
            'entity_score' => $entry['canonical'] ? 0.88 : 0.84,
            'distance' => 0,
        ];
        $candidate['score'] = chimOghmaEntitySalience($text, $candidate, $requestScore);
        $exact[] = $candidate;
    }

    usort($exact, static function (array $left, array $right): int {
        $position = intval($left['start']) <=> intval($right['start']);
        if ($position !== 0) {
            return $position;
        }
        $span = (intval($right['end']) - intval($right['start']))
            <=> (intval($left['end']) - intval($left['start']));
        return $span !== 0 ? $span : floatval($right['score']) <=> floatval($left['score']);
    });

    $candidates = [];
    foreach ($exact as $candidate) {
        $overlapIndex = null;
        foreach ($candidates as $index => $existing) {
            if (chimOghmaSpansOverlap($candidate, $existing)) {
                $overlapIndex = $index;
                break;
            }
        }
        if ($overlapIndex === null) {
            $candidates[] = $candidate;
        } else {
            $existing = $candidates[$overlapIndex];
            $candidateSpan = intval($candidate['end']) - intval($candidate['start']);
            $existingSpan = intval($existing['end']) - intval($existing['start']);
            if ($candidateSpan >= $existingSpan
                && floatval($candidate['score']) > floatval($existing['score']) + 0.04) {
                $candidates[$overlapIndex] = $candidate;
            }
        }
    }

    $hasShortExact = array_filter(
        $candidates,
        static fn(array $candidate): bool => strlen(chimOghmaCompactEntityKey(strval($candidate['entity_phrase']))) <= 6
    ) !== [];
    $fuzzy = count($candidates) < $amount
        ? chimOghmaFuzzyEntities($lexicon, $text)
        : ($hasShortExact ? chimOghmaFuzzyEntities($lexicon, $text, $candidates) : []);
    usort($fuzzy, static fn(array $left, array $right): int => floatval($right['score']) <=> floatval($left['score']));
    foreach ($fuzzy as $candidate) {
        if (floatval($candidate['score']) < 0.84) {
            continue;
        }
        if ($speakerLabelEnd > 0 && intval($candidate['start']) < $speakerLabelEnd) {
            continue;
        }
        $overlapIndex = null;
        foreach ($candidates as $index => $existing) {
            if (chimOghmaSpansOverlap($candidate, $existing)) {
                $overlapIndex = $index;
                break;
            }
        }
        if ($overlapIndex !== null) {
            $existing = $candidates[$overlapIndex];
            $existingCompactLength = strlen(chimOghmaCompactEntityKey(strval($existing['entity_phrase'])));
            $candidateContainsExisting = str_contains(
                chimOghmaStrictEntityPhrase(strval($candidate['entity_phrase'])),
                chimOghmaStrictEntityPhrase(strval($existing['entity_phrase']))
            );
            $candidateSpan = intval($candidate['end']) - intval($candidate['start']);
            $existingSpan = intval($existing['end']) - intval($existing['start']);
            if ($existingCompactLength <= 6 && $candidateContainsExisting && $candidateSpan > $existingSpan
                && intval($candidate['literal_distance'] ?? PHP_INT_MAX) <= 3) {
                $candidates[$overlapIndex] = $candidate;
            } elseif ($existingCompactLength <= 6
                && floatval($candidate['score']) > floatval($existing['score']) + 0.04) {
                $candidates[$overlapIndex] = $candidate;
            }
            continue;
        }
        $runnerUpScore = 0.0;
        foreach ($fuzzy as $other) {
            if (chimOghmaNormalizeTopicKey(strval($other['topic'])) !== chimOghmaNormalizeTopicKey(strval($candidate['topic']))
                && chimOghmaSpansOverlap($candidate, $other)) {
                $runnerUpScore = max($runnerUpScore, floatval($other['score']));
            }
        }
        if (floatval($candidate['score']) - $runnerUpScore >= 0.04) {
            $candidates[] = $candidate;
        }
    }

    $byTopic = [];
    foreach ($candidates as $candidate) {
        if (floatval($candidate['score']) < 0.72) {
            continue;
        }
        $topicKey = chimOghmaNormalizeTopicKey(strval($candidate['topic']));
        if (!isset($byTopic[$topicKey]) || floatval($candidate['score']) > floatval($byTopic[$topicKey]['score'])) {
            $byTopic[$topicKey] = $candidate;
        }
    }
    $selected = array_values($byTopic);
    usort($selected, static function (array $left, array $right): int {
        $scoreDifference = floatval($right['score']) - floatval($left['score']);
        if (abs($scoreDifference) >= 0.15) {
            return $scoreDifference <=> 0.0;
        }
        return intval($left['start']) <=> intval($right['start']);
    });
    return [
        'entities' => array_slice($selected, 0, max(1, $amount)),
        'fuzzy_candidates' => $fuzzy,
        'minime_calls' => 0,
        'minime_responses' => [],
    ];
}

function chimOghmaEntityMentionCount(string $text, array $candidate): int
{
    $normalized = chimOghmaStrictEntityPhrase($text);
    $phrases = array_values(array_unique(array_filter([
        chimOghmaStrictEntityPhrase(strval($candidate['entity_phrase'] ?? '')),
        chimOghmaStrictEntityPhrase(strval($candidate['phrase'] ?? '')),
    ])));
    $best = 0;
    foreach ($phrases as $phrase) {
        $count = preg_match_all('/(?<![a-z0-9])' . preg_quote($phrase, '/') . '(?![a-z0-9])/u', $normalized);
        $best = max($best, intval($count));
    }
    return $best;
}

function chimOghmaIsGenericSingleWord(array $candidate): bool
{
    $phrase = chimOghmaStrictEntityPhrase(strval($candidate['entity_phrase'] ?? ''));
    if ($phrase === '' || str_contains($phrase, ' ')) {
        return false;
    }
    $generic = [
        'armor', 'arrow', 'bear', 'blades', 'blizzard', 'calm', 'conjure', 'cure', 'damage',
        'dark', 'dawn', 'deer', 'dragon', 'elk', 'fast', 'fear', 'fire', 'fish', 'frost',
        'fury', 'glass', 'goat', 'healing', 'health', 'hell', 'home', 'house', 'human',
        'light', 'magic', 'muffle', 'night', 'pale', 'pearl', 'poison', 'reach', 'reanimate',
        'regenerate', 'resist', 'restore', 'rune', 'skeever', 'storm', 'sunder', 'teleport',
        'trolls', 'turn', 'water', 'wheat',
    ];
    return in_array($phrase, $generic, true);
}

function chimOghmaBackgroundPenalty(string $text, array $candidate): float
{
    $normalized = chimOghmaStrictEntityPhrase($text);
    $phrase = chimOghmaStrictEntityPhrase(strval($candidate['entity_phrase'] ?? $candidate['phrase'] ?? ''));
    if ($phrase === '') {
        return 0.0;
    }
    $quoted = preg_quote($phrase, '/');
    if (preg_match('/\b(?:forget|ignore|leave)\s+' . $quoted . '\s+(?:for\s+now|aside|behind)\b/u', $normalized)) {
        return 0.45;
    }
    if (preg_match('/\balthough\s+(?:we\s+)?(?:crossed|passed|saw|left)\s+' . $quoted . '\b/u', $normalized)) {
        return 0.35;
    }
    if (preg_match('/\b(?:only|merely|just)\s+(?:passed|crossed|saw|mentioned)\s+' . $quoted . '\b/u', $normalized)
        || preg_match('/\b' . $quoted . '\s+(?:was|is)\s+(?:only|merely|just)\b/u', $normalized)) {
        return 0.35;
    }
    if (preg_match('/\b(?:briefly|incidentally)\s+(?:referenced|mentioned|discussed)\s+' . $quoted . '\b/u', $normalized)) {
        return 0.15;
    }
    return 0.0;
}

function chimOghmaGenericCueStrength(string $text, array $candidate, float $requestScore): float
{
    $strength = chimOghmaEntityCueStrength($text, $candidate, $requestScore);
    $normalized = chimOghmaStrictEntityPhrase($text);
    $start = max(0, intval($candidate['start'] ?? 0));
    $end = max($start, intval($candidate['end'] ?? $start));
    $prefix = trim(substr($normalized, 0, $start));
    $suffix = trim(substr($normalized, $end));
    $phrase = chimOghmaStrictEntityPhrase(strval($candidate['entity_phrase'] ?? $candidate['phrase'] ?? ''));
    if (preg_match('/\b(?:encountered|found|recognized|named|called|discussed|mentioned|lists?|listed|contains?|contained|includes?|included|shows?|showed|depicts?|depicted)\s*$/u', $prefix)
        || preg_match('/\b(?:recognized\s+the\s+name|entry\s+for|name|subject|topic|entry)\s*$/u', $prefix)) {
        return 1.0;
    }
    if ($start <= 1 && preg_match('/^(?:reminds?|matters?|explains?|caused|appeared|was|is)\b/u', $suffix)) {
        return 0.9;
    }
    if ($phrase !== '' && preg_match('/^both\s+' . preg_quote($phrase, '/') . '\s+and\b/u', $normalized)) {
        return 0.9;
    }
    if (preg_match('/\b(?:then|and|with|before|followed\s+by|moving\s+to)\s*$/u', $prefix)) {
        return 0.9;
    }
    return $strength;
}

// Reconsider exact generic words so explicit lore senses can survive suppression.
function chimOghmaRecoverGenericExactEntities(array $lexicon, string $text): array
{
    $normalized = chimOghmaStrictEntityPhrase($text);
    $speakerLabel = chimOghmaSpeakerLabelPhrase($text);
    $speakerLabelEnd = $speakerLabel !== '' ? strlen($speakerLabel) : 0;
    $requestScore = chimOghmaKnowledgeRequestScore($text);
    $recovered = [];
    foreach ($lexicon['generic_canonical_entries'] ?? [] as $entry) {
        $entryPhrase = strval($entry['phrase']);
        if (!str_contains($normalized, $entryPhrase)) {
            continue;
        }
        $pattern = '/(?<![a-z0-9])' . preg_quote($entryPhrase, '/') . '(?![a-z0-9])/u';
        if (!preg_match_all($pattern, $normalized, $matches, PREG_OFFSET_CAPTURE)) {
            continue;
        }
        foreach ($matches[0] as $match) {
            $start = intval($match[1]);
            if ($speakerLabelEnd > 0 && $start < $speakerLabelEnd) {
                continue;
            }
            $candidate = [
                'topic' => strval($entry['topic']),
                'phrase' => strval($entry['phrase']),
                'entity_phrase' => strval($entry['phrase']),
                'source' => 'recovered exact canonical',
                'start' => $start,
                'end' => $start + strlen(strval($match[0])),
                'entity_score' => 0.90,
                'distance' => 0,
            ];
            $candidate['score'] = chimOghmaEntitySalience($text, $candidate, $requestScore);
            $recovered[] = $candidate;
        }
    }
    return $recovered;
}

// Ignore catalog-shaped speaker labels while preserving title-like content prefixes.
function chimOghmaCatalogSpeakerLabelPhrase(array $lexicon, string $text): string
{
    if (!preg_match('/^\s*([^:\r\n]{1,80}):\s+/u', $text, $match)) {
        return '';
    }
    $label = chimOghmaStrictEntityPhrase(strval($match[1]));
    if (preg_match('/[.!?;]/u', strval($match[1]))) {
        return '';
    }
    $tokens = preg_split('/\s+/u', $label) ?: [];
    if ($label === '' || count($tokens) > 12) {
        return '';
    }
    if (preg_match('/\b(?:era|volume|book|chapter|part|act)\b/u', $label)) {
        return '';
    }
    if (preg_match('/^(?:i|we|they|he|she|you|it|there)\b/u', $label)) {
        return '';
    }
    if (preg_match('/^(?:remember|note|consider|compare|start|first|although|while|for|to|source|reference)\b/u', $label)) {
        return '';
    }
    $owners = array_keys($lexicon['phrase_owners'][$label]['owners'] ?? []);
    if ($owners === []) {
        return '';
    }
    foreach ($owners as $topic) {
        if (strval($lexicon['topic_categories'][chimOghmaNormalizeTopicKey(strval($topic))] ?? '') === 'books') {
            return '';
        }
    }
    return $label;
}

function chimOghmaHasConcreteNounContext(string $text, array $candidate): bool
{
    $category = strtolower(trim(strval($candidate['category'] ?? '')));
    if (!in_array($category, ['artifact', 'artifacts', 'creature', 'creatures', 'equipment', 'item', 'items', 'object', 'objects'], true)) {
        return false;
    }
    $source = strval($candidate['source'] ?? '');
    if (!str_contains($source, 'exact')) {
        return false;
    }
    $phrase = chimOghmaStrictEntityPhrase(strval($candidate['entity_phrase'] ?? $candidate['phrase'] ?? ''));
    if ($phrase === '') {
        return false;
    }
    if (str_contains($phrase, ' ')) {
        return true;
    }

    $normalized = chimOghmaStrictEntityPhrase($text);
    $start = max(0, intval($candidate['start'] ?? 0));
    $end = max($start, intval($candidate['end'] ?? $start));
    $prefix = trim(substr($normalized, 0, $start));
    $suffix = trim(substr($normalized, $end));
    if (($phrase === 'fish' && preg_match('/^(?:for|around)\b/u', $suffix))
        || ($phrase === 'poison' && preg_match('/^(?:him|her|them|it|us|me|you|the|this|that|my|your|his|our|their)\b/u', $suffix))) {
        return false;
    }
    if (preg_match('/\b(?:a|an|the|this|that|these|those|my|your|his|her|its|our|their|some|any|each|every|another|one|two|three|several|many|few|of)\s*$/u', $prefix)) {
        return true;
    }
    if (preg_match('/\b(?:reference|drawing|sketch|picture|entry|information|details|report)\s+(?:to|of|for|on)\s*$/u', $prefix)) {
        return true;
    }
    $rawPrefix = substr(strtolower($text), 0, $start);
    if (preg_match('/:\s*$/u', $rawPrefix)) {
        return true;
    }
    if (preg_match('/\b(?:saw|see|seen|spot|spotted|encounter|encountered|find|found|recognize|recognized|notice|noticed|hear|heard|hunt|hunted|kill|killed|buy|bought|sell|sold|use|used|need|needed|want|wanted|have|has|had|carry|carries|carried|collect|collected|harvest|harvested|avoid|avoided|mention|mentioned|discuss|discussed|lists?|listed|contains?|contained|includes?|included|shows?|showed|depicts?|depicted|pairs?|paired|links?|linked|compares?|compared|picked\s+up|looking\s+at|talking\s+about)\s*$/u', $prefix)) {
        return true;
    }
    if ($start <= 1 && preg_match('/^(?:is|are|was|were|has|have|had|can|could|will|would|should|must|might|may|seems?|looks?|appeared|stood|waited|growled|attacked|crossed|fled|ran|fell|died|moved|blocked)\b/u', $suffix)) {
        return true;
    }
    return false;
}

function chimOghmaHasAmbiguousShortPrefix(array $lexicon, array $candidate): bool
{
    if (!str_contains(strval($candidate['source'] ?? ''), 'phonetic')) {
        return false;
    }
    $compact = chimOghmaCompactEntityKey(strval($candidate['phrase'] ?? ''));
    $length = strlen($compact);
    if ($length < 5 || $length > 9) {
        return false;
    }
    return count($lexicon['prefix_owners'][$compact] ?? []) > 1;
}

function chimOghmaContextBackgroundPenalty(string $text, array $candidate): float
{
    $penalty = chimOghmaBackgroundPenalty($text, $candidate);
    $phrase = chimOghmaStrictEntityPhrase(strval($candidate['entity_phrase'] ?? $candidate['phrase'] ?? ''));
    if ($phrase !== '') {
        $quoted = preg_quote($phrase, '/');
        $normalized = chimOghmaStrictEntityPhrase($text);
        if (preg_match('/\b(?:passed|crossed|saw|left|visited)\s+(?:the\s+)?' . $quoted . '\s+(?:earlier|before|previously)\b/u', $normalized)) {
            $penalty = max($penalty, 0.45);
        }
        if (preg_match('/\b(?:noticed|mentioned|saw)\s+(?:the\s+)?' . $quoted . '\s+but\b/u', $normalized)) {
            $penalty = max($penalty, 0.45);
        }
    }
    return $penalty;
}

function chimOghmaContextCueStrength(string $text, array $candidate, float $requestScore): float
{
    $strength = chimOghmaGenericCueStrength($text, $candidate, $requestScore);
    $normalized = chimOghmaStrictEntityPhrase($text);
    $start = max(0, intval($candidate['start'] ?? 0));
    $prefix = trim(substr($normalized, 0, $start));
    if (preg_match('/\b(?:centered|focused)\s+on\s*$/u', $prefix)) {
        return 1.0;
    }
    return $strength;
}

// Allow exact catalog creatures and objects when they appear as ordinary nouns.
function chimOghmaRecoverConcreteExactEntities(array $lexicon, string $text): array
{
    $normalized = chimOghmaStrictEntityPhrase($text);
    $speakerLabel = chimOghmaCatalogSpeakerLabelPhrase($lexicon, $text);
    $speakerLabelEnd = $speakerLabel !== '' ? strlen($speakerLabel) : 0;
    $requestScore = chimOghmaKnowledgeRequestScore($text);
    $byTopic = [];
    foreach ($lexicon['concrete_entries'] ?? [] as $entry) {
        $entryPhrase = strval($entry['phrase']);
        if (!str_contains($normalized, $entryPhrase)) {
            continue;
        }
        $pattern = '/(?<![a-z0-9])' . preg_quote($entryPhrase, '/') . '(?![a-z0-9])/u';
        if (!preg_match_all($pattern, $normalized, $matches, PREG_OFFSET_CAPTURE)) {
            continue;
        }
        foreach ($matches[0] as $match) {
            $start = intval($match[1]);
            if ($speakerLabelEnd > 0 && $start < $speakerLabelEnd) {
                continue;
            }
            $candidate = [
                'topic' => strval($entry['topic']),
                'phrase' => strval($entry['phrase']),
                'entity_phrase' => strval($entry['phrase']),
                'category' => strval($entry['category'] ?? ''),
                'source' => $entry['canonical'] ? 'concrete exact canonical' : 'concrete exact alias',
                'start' => $start,
                'end' => $start + strlen(strval($match[0])),
                'entity_score' => $entry['canonical'] ? 0.90 : 0.86,
                'distance' => 0,
            ];
            if (chimOghmaIsGenericSingleWord($candidate) && !chimOghmaHasConcreteNounContext($text, $candidate)) {
                continue;
            }
            $candidate['score'] = max(0.73, chimOghmaEntitySalience($text, $candidate, $requestScore));
            $candidate['nominal_concrete'] = true;
            $topicKey = chimOghmaNormalizeTopicKey(strval($candidate['topic']));
            if (!isset($byTopic[$topicKey]) || ($entry['canonical'] && !str_contains(strval($byTopic[$topicKey]['source']), 'canonical'))) {
                $byTopic[$topicKey] = $candidate;
            }
        }
    }
    return array_values($byTopic);
}

function chimOghmaOverlapsLongerExact(array $candidatePool, array $candidate, string $text): bool
{
    $candidateLength = strlen(chimOghmaCompactEntityKey(strval($candidate['entity_phrase'] ?? '')));
    $candidatePhrase = chimOghmaStrictEntityPhrase(strval($candidate['entity_phrase'] ?? ''));
    $normalized = chimOghmaStrictEntityPhrase($text);
    foreach ($candidatePool as $existing) {
        if (!str_contains(strval($existing['source'] ?? ''), 'exact')) {
            continue;
        }
        $existingPhrase = chimOghmaStrictEntityPhrase(strval($existing['entity_phrase'] ?? ''));
        if (strlen(chimOghmaCompactEntityKey($existingPhrase)) > $candidateLength
            && (chimOghmaSpansOverlap($candidate, $existing)
                || ($candidatePhrase !== ''
                    && str_contains($existingPhrase, $candidatePhrase)
                    && str_contains($normalized, $existingPhrase)))) {
            return true;
        }
    }
    return false;
}

function chimOghmaHasTranscriptCue(string $text): bool
{
    $normalized = chimOghmaStrictEntityPhrase($text);
    if (preg_match(
        '/\b(?:sound(?:ed)?\s+like|called\s+it|may\s+have\s+said|might\s+have\s+said|(?:speech|voice)\s+recognition|speech\s+to\s+text|'
        . 'bad\s+recording|through\s+(?:the\s+)?static|pronounced|transcript\s+returned|recording\s+rendered|'
        . 'heard\s+the\s+words?|around\s+the\s+words?|(?:final\s+)?words?\s+(?:may|might)\s+have\s+been|'
        . 'transcribed\s+as|caught\s+something\s+resembling|interference.{0,20}heard\s+something\s+like)\b/u',
        $normalized
    ) === 1) {
        return true;
    }
    return preg_match(
        '/\b(?:transcript|transcription|transcribed|dictation|captioning|recognition|recording|audio|static|channel|speech\s+to\s+text)\b.{0,36}'
        . '\b(?:returned|produced|wrote|rendered|heard|sounded|read|output|printed|captured|as|like)\b/u',
        $normalized
    ) === 1;
}

function chimOghmaHasWrongSenseHomonym(string $text, array $candidate): bool
{
    $phrase = chimOghmaStrictEntityPhrase(strval($candidate['entity_phrase'] ?? $candidate['phrase'] ?? ''));
    $normalized = chimOghmaStrictEntityPhrase($text);
    $end = max(0, intval($candidate['end'] ?? 0));
    $suffix = trim(substr($normalized, $end));
    if (in_array($phrase, ['pale', 'the pale'], true)
        && preg_match('/^(?:light|skin|face|color|colour|blue|white)\b/u', $suffix)) {
        return true;
    }
    return false;
}

// Apply sense, ambiguity, repetition, and ordering rules to the candidate pool.
function chimOghmaExtractEntities($db, string $text, int $amount, bool $allowTagFallback = true): array
{
    $expandedAmount = max(8, $amount * 4);
    $expanded = chimOghmaBaseEntities($db, $text, $expandedAmount);
    $lexicon = chimOghmaEntityLexicon($db);
    $requestScore = chimOghmaKnowledgeRequestScore($text);
    $speakerLabel = chimOghmaCatalogSpeakerLabelPhrase($lexicon, $text);
    $speakerLabelEnd = $speakerLabel !== '' ? strlen($speakerLabel) : 0;
    $hasTranscriptCue = chimOghmaHasTranscriptCue($text);
    $candidatePool = [];
    $rejected = [];
    if ($speakerLabel !== '') {
        $speakerOwners = array_keys($lexicon['phrase_owners'][$speakerLabel]['owners'] ?? []);
        $rejected[] = ['topic' => count($speakerOwners) === 1 ? $speakerOwners[0] : null,
            'phrase' => $speakerLabel, 'reason' => 'speaker_label', 'start' => 0];
    }
    foreach ($expanded['entities'] as $candidate) {
        if ($speakerLabelEnd > 0 && intval($candidate['start'] ?? 0) < $speakerLabelEnd) {
            $rejected[] = ['topic' => $candidate['topic'] ?? null, 'phrase' => $candidate['phrase'] ?? null,
                'reason' => 'speaker_label', 'start' => intval($candidate['start'] ?? 0)];
            continue;
        }
        if (!$hasTranscriptCue && str_contains(strval($candidate['source'] ?? ''), 'phonetic')
            && chimOghmaEntityCueStrength($text, $candidate, $requestScore) < 0.8) {
            $rejected[] = ['topic' => $candidate['topic'] ?? null, 'phrase' => $candidate['phrase'] ?? null,
                'reason' => 'unguarded_fuzzy_match', 'start' => intval($candidate['start'] ?? 0)];
            continue;
        }
        $candidatePool[] = $candidate;
    }

    foreach ($expanded['fuzzy_candidates'] ?? [] as $candidate) {
        if (($speakerLabelEnd > 0 && intval($candidate['start'] ?? 0) < $speakerLabelEnd)
            || floatval($candidate['score'] ?? 0.0) < 0.84
            || (!$hasTranscriptCue && chimOghmaEntityCueStrength($text, $candidate, $requestScore) < 0.8)) {
            $rejected[] = ['topic' => $candidate['topic'] ?? null, 'phrase' => $candidate['phrase'] ?? null,
                'reason' => ($speakerLabelEnd > 0 && intval($candidate['start'] ?? 0) < $speakerLabelEnd)
                    ? 'speaker_label'
                    : 'unguarded_fuzzy_match',
                'start' => intval($candidate['start'] ?? 0), 'score' => floatval($candidate['score'] ?? 0.0)];
            continue;
        }
            $overlapIndex = null;
            foreach ($candidatePool as $index => $existing) {
                if (chimOghmaSpansOverlap($candidate, $existing)) {
                    $overlapIndex = $index;
                    break;
                }
            }
            if ($overlapIndex === null) {
                $competitors = array_values(array_filter(
                    $expanded['fuzzy_candidates'] ?? [],
                    static fn(array $other): bool => chimOghmaNormalizeTopicKey(strval($other['topic'] ?? ''))
                        !== chimOghmaNormalizeTopicKey(strval($candidate['topic'] ?? ''))
                        && chimOghmaSpansOverlap($candidate, $other)
                ));
                $candidateSpan = intval($candidate['end']) - intval($candidate['start']);
                $hasLongerCompetitor = array_filter(
                    $competitors,
                    static fn(array $other): bool => intval($other['end']) - intval($other['start']) > $candidateSpan
                ) !== [];
                $bestCompetitorScore = $competitors === [] ? 0.0 : max(array_map(
                    static fn(array $other): float => floatval($other['score'] ?? 0.0),
                    $competitors
                ));
                if (!$hasLongerCompetitor && floatval($candidate['score']) >= $bestCompetitorScore - 0.04) {
                    $candidatePool[] = $candidate;
                }
                continue;
            }
            $existing = $candidatePool[$overlapIndex];
            $candidateCompact = chimOghmaCompactEntityKey(strval($candidate['entity_phrase'] ?? ''));
            $existingCompact = chimOghmaCompactEntityKey(strval($existing['entity_phrase'] ?? ''));
            if (intval($candidate['end']) - intval($candidate['start'])
                    > intval($existing['end']) - intval($existing['start'])
                && (str_contains(strval($existing['source'] ?? ''), 'phonetic')
                    || chimOghmaNormalizeTopicKey(strval($candidate['topic'] ?? ''))
                        !== chimOghmaNormalizeTopicKey(strval($existing['topic'] ?? '')))
                && str_contains($candidateCompact, $existingCompact)
                && intval($candidate['literal_distance'] ?? PHP_INT_MAX) <= 3) {
                $candidatePool[$overlapIndex] = $candidate;
            }
    }

    foreach (chimOghmaRecoverGenericExactEntities($lexicon, $text) as $candidate) {
        if (($speakerLabelEnd > 0 && intval($candidate['start'] ?? 0) < $speakerLabelEnd)
            || chimOghmaOverlapsLongerExact($candidatePool, $candidate, $text)) {
            continue;
        }
        $topicKey = chimOghmaNormalizeTopicKey(strval($candidate['topic'] ?? ''));
        $candidatePool = array_values(array_filter(
            $candidatePool,
            static fn(array $existing): bool => chimOghmaNormalizeTopicKey(strval($existing['topic'] ?? '')) !== $topicKey
        ));
        $candidatePool[] = $candidate;
    }
    foreach (chimOghmaRecoverConcreteExactEntities($lexicon, $text) as $candidate) {
        if (chimOghmaOverlapsLongerExact($candidatePool, $candidate, $text)) {
            continue;
        }
        $topicKey = chimOghmaNormalizeTopicKey(strval($candidate['topic'] ?? ''));
        $candidatePool = array_values(array_filter(
            $candidatePool,
            static fn(array $existing): bool => chimOghmaNormalizeTopicKey(strval($existing['topic'] ?? '')) !== $topicKey
        ));
        $candidatePool[] = $candidate;
    }

    $entities = [];
    foreach ($candidatePool as $candidate) {
        if (chimOghmaHasWrongSenseHomonym($text, $candidate)) {
            $rejected[] = ['topic' => $candidate['topic'] ?? null, 'phrase' => $candidate['phrase'] ?? null,
                'reason' => 'wrong_sense', 'start' => intval($candidate['start'] ?? 0)];
            continue;
        }
        $mentions = chimOghmaEntityMentionCount($text, $candidate);
        $score = floatval($candidate['score'] ?? 0.0);
        if ($mentions > 1) {
            $score += min(0.60, ($mentions - 1) * 0.32);
        }
        $score -= chimOghmaContextBackgroundPenalty($text, $candidate);
        $cueStrength = chimOghmaContextCueStrength($text, $candidate, $requestScore);
        if (chimOghmaHasAmbiguousShortPrefix($lexicon, $candidate) && $requestScore < 0.5 && $cueStrength < 0.8) {
            $rejected[] = ['topic' => $candidate['topic'] ?? null, 'phrase' => $candidate['phrase'] ?? null,
                'reason' => 'ambiguous_short_prefix', 'start' => intval($candidate['start'] ?? 0), 'score' => $score];
            continue;
        }
        if (!boolval($candidate['nominal_concrete'] ?? false)
            && chimOghmaIsGenericSingleWord($candidate)
            && $mentions <= 1
            && $requestScore < 0.5) {
            $score -= $cueStrength >= 0.8 ? 0.0 : 0.52;
        }
        $candidate['context_score'] = $score;
        $candidate['mention_count'] = $mentions;
        if ($score >= 0.72) {
            $entities[] = $candidate;
        } else {
            $rejected[] = ['topic' => $candidate['topic'] ?? null, 'phrase' => $candidate['phrase'] ?? null,
                'reason' => 'low_context_score', 'start' => intval($candidate['start'] ?? 0), 'score' => $score];
        }
    }

    $entities = chimOghmaApplyRelationalTagSupport($lexicon, $text, $entities);
    usort($entities, static function (array $left, array $right): int {
        $mentionDifference = intval($right['mention_count']) <=> intval($left['mention_count']);
        if ($mentionDifference !== 0) {
            return $mentionDifference;
        }
        return intval($left['start']) <=> intval($right['start']);
    });
    $tagDecisions = [];
    if ($allowTagFallback && $entities === []) {
        $tagFallback = chimOghmaTagFallbackEntities($lexicon, $text, $amount, $requestScore);
        $entities = $tagFallback['entities'];
        $rejected = array_merge($rejected, $tagFallback['rejected']);
        $tagDecisions = $tagFallback['decisions'];
    }
    return [
        'entities' => array_slice($entities, 0, max(1, $amount)),
        'rejected' => $rejected,
        'tag_decisions' => $tagDecisions,
        'fallback_eligible' => $entities === [] && chimOghmaShouldUseTopicFallback($text),
        'minime_calls' => 0,
        'minime_responses' => [],
    ];
}

// Extract canonical Oghma topics in conversational relevance order.
function chimOghmaExtractTopics($db, string $text, int $amount = 1): array
{
    if (!$db || !method_exists($db, 'fetchAll') || trim($text) === '') {
        return [];
    }

    $result = chimOghmaExtractEntities($db, $text, max(1, $amount));
    return array_values(array_map(
        static fn(array $entity): string => strval($entity['topic'] ?? ''),
        array_filter(
            $result['entities'] ?? [],
            static fn(array $entity): bool => trim(strval($entity['topic'] ?? '')) !== ''
        )
    ));
}

function chimOghmaShouldUseTopicFallback(string $text): bool
{
    if (chimOghmaKnowledgeRequestScore($text) <= 0.0) {
        return false;
    }
    $normalized = chimOghmaStrictEntityPhrase($text);
    return preg_match(
        '/\b(?:(?:tell|teach|explain|describe|discuss)\s+(?:me\s+|us\s+)?(?:about|of)|'
        . 'what\s+(?:do\s+you\s+know|have\s+you\s+heard)\s+about|'
        . '(?:do|did)\s+you\s+know\s+(?:anything\s+)?about|'
        . '(?:history|lore|background|story|details|information)\s+(?:about|on|of))\b/u',
        $normalized
    ) === 1;
}

// Resolve an extractor suggestion only when it names one catalog entity unambiguously.
function chimOghmaResolveTopicName($db, string $value): ?string
{
    $lexicon = chimOghmaEntityLexicon($db);
    $phrase = chimOghmaStrictEntityPhrase($value);
    $owners = array_keys($lexicon['phrase_owners'][$phrase]['owners'] ?? []);
    if (count($owners) === 1) {
        return strval($owners[0]);
    }

    $entries = $lexicon['by_compact'][chimOghmaCompactEntityKey($value)] ?? [];
    $topics = array_values(array_unique(array_map(
        static fn(array $entry): string => strval($entry['topic'] ?? ''),
        $entries
    )));
    return count($topics) === 1 ? $topics[0] : null;
}

// Fetch the exact canonical row selected by the grounded extractor.
function chimOghmaFetchTopic($db, string $topic): ?array
{
    if (!$db || !method_exists($db, 'fetchOne') || trim($topic) === '') {
        return null;
    }

    $escapedTopic = $db->escape($topic);
    $row = $db->fetchOne(
        "SELECT topic_desc, topic, aliases, knowledge_class, knowledge_class_basic, topic_desc_basic, tags, category, "
        . "source_type, source_catalog_version, 1000.0 AS combined_rank "
        . "FROM public.oghma WHERE topic = '{$escapedTopic}' LIMIT 1"
    );
    return is_array($row) ? $row : null;
}
