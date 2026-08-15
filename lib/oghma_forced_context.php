<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'oghma_aliases.php';

if (!function_exists('chimOghmaNormalizeLookupLabel')) {
    function chimOghmaNormalizeLookupLabel($value): string
    {
        $value = preg_replace('/([a-z0-9])([A-Z])/u', '$1 $2', trim((string) $value));
        $value = strtolower(str_replace('_', ' ', (string) $value));
        $value = preg_replace('/\braces?\s*$/u', '', $value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value);
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}

if (!function_exists('chimOghmaUniqueSignals')) {
    function chimOghmaUniqueSignals(array $signals): array
    {
        $result = [];
        foreach ($signals as $signal) {
            $normalized = chimOghmaNormalizeLookupLabel($signal);
            if ($normalized !== '' && !isset($result[$normalized])) {
                $result[$normalized] = $normalized;
            }
        }
        return array_values($result);
    }
}

if (!function_exists('chimOghmaRaceSignals')) {
    function chimOghmaRaceSignals($race): array
    {
        $race = chimOghmaNormalizeLookupLabel($race);
        $aliases = [
            'high elf' => ['high elf', 'altmer'],
            'altmer' => ['altmer', 'high elf'],
            'wood elf' => ['wood elf', 'bosmer'],
            'bosmer' => ['bosmer', 'wood elf'],
            'dark elf' => ['dark elf', 'dunmer'],
            'dunmer' => ['dunmer', 'dark elf'],
            'orc' => ['orc', 'orsimer'],
            'orsimer' => ['orsimer', 'orc'],
            'snow elf' => ['snow elf', 'falmer'],
        ];
        $supported = [
            'nord', 'imperial', 'breton', 'redguard', 'high elf', 'altmer',
            'wood elf', 'bosmer', 'dark elf', 'dunmer', 'orc', 'orsimer',
            'khajiit', 'argonian', 'snow elf', 'falmer', 'dremora',
        ];

        if (!in_array($race, $supported, true)) {
            return [];
        }

        return chimOghmaUniqueSignals($aliases[$race] ?? [$race]);
    }
}

if (!function_exists('chimOghmaPeopleNames')) {
    function chimOghmaPeopleNames($people): array
    {
        if (is_array($people)) {
            return array_values(array_filter(array_map('trim', $people)));
        }
        return array_values(array_filter(array_map('trim', explode('|', (string) $people))));
    }
}

if (!function_exists('chimOghmaCollectRaceSignals')) {
    function chimOghmaCollectRaceSignals(array $currentNpcData, $people, $npcMaster = null, int $limit = 4): array
    {
        $signals = chimOghmaRaceSignals($currentNpcData['race'] ?? '');
        $skipNames = array_filter([
            strtolower(trim((string) ($GLOBALS['PLAYER_NAME'] ?? ''))),
            strtolower(trim((string) ($GLOBALS['HERIKA_NAME'] ?? ''))),
            'the narrator',
        ]);

        foreach (chimOghmaPeopleNames($people) as $name) {
            if (count($signals) >= $limit || in_array(strtolower($name), $skipNames, true)) {
                continue;
            }
            if (!$npcMaster || !method_exists($npcMaster, 'getByName')) {
                continue;
            }
            $npcData = $npcMaster->getByName($name);
            if (!is_array($npcData)) {
                continue;
            }
            $signals = array_merge($signals, chimOghmaRaceSignals($npcData['race'] ?? ''));
            $signals = array_slice(chimOghmaUniqueSignals($signals), 0, $limit);
        }

        return array_slice(chimOghmaUniqueSignals($signals), 0, $limit);
    }
}

if (!function_exists('chimOghmaHoldSignals')) {
    function chimOghmaHoldSignals($hold): array
    {
        $signals = [$hold];
        if (function_exists('getCanonicalHoldAliases')) {
            $signals = array_merge($signals, getCanonicalHoldAliases($hold));
        } else {
            $normalized = chimOghmaNormalizeLookupLabel($hold);
            if (str_ends_with($normalized, ' hold')) {
                $signals[] = preg_replace('/\s+hold$/u', '', $normalized);
            }
        }

        return chimOghmaUniqueSignals($signals);
    }
}

if (!function_exists('chimOghmaLocationNameSignals')) {
    function chimOghmaLocationNameSignals($location): array
    {
        $normalized = chimOghmaNormalizeLookupLabel($location);
        if ($normalized === '') {
            return [];
        }

        $signals = [$normalized];
        $genericSuffixes = [
            'trading post', 'general goods', 'dry goods', 'trader', 'market',
            'inn', 'tavern', 'house', 'home', 'farm', 'mill', 'temple',
            'hall', 'shop', 'store', 'mine', 'cave', 'camp', 'tower',
        ];
        foreach ($genericSuffixes as $suffix) {
            if ($normalized === $suffix || !str_ends_with($normalized, ' ' . $suffix)) {
                continue;
            }
            $base = trim(substr($normalized, 0, -strlen($suffix)));
            if ($base !== '') {
                $signals[] = $base;
            }
        }

        return chimOghmaUniqueSignals($signals);
    }
}

if (!function_exists('chimOghmaBuildLocationSignalGroups')) {
    function chimOghmaBuildLocationSignalGroups($location, array $rows, $canonicalHold, $reportedHold): array
    {
        $locationSignals = chimOghmaLocationNameSignals($location);
        $holdSignals = [];

        foreach ($rows as $row) {
            $locationSignals[] = $row['name'] ?? '';
            $locationSignals[] = $row['region'] ?? '';
            $holdSignals[] = $row['hold'] ?? '';
        }
        $holdSignals = array_merge($holdSignals, chimOghmaHoldSignals($canonicalHold));
        $holdSignals = array_merge($holdSignals, chimOghmaHoldSignals($reportedHold));

        return [
            'location' => chimOghmaUniqueSignals($locationSignals),
            'hold' => chimOghmaUniqueSignals($holdSignals),
        ];
    }
}

if (!function_exists('chimOghmaCollectLocationSignalGroups')) {
    function chimOghmaCollectLocationSignalGroups($db): array
    {
        $parts = function_exists('DataLastKnownLocationContextParts')
            ? DataLastKnownLocationContextParts(false)
            : [];
        $location = trim((string) ($parts['location_base'] ?? $parts['location'] ?? ''));
        $rows = [];

        if ($location !== '' && $db && method_exists($db, 'fetchAll')) {
            $locationKey = chimOghmaNormalizeLookupLabel($location);
            $locationEsc = $db->escape($locationKey);
            $rows = $db->fetchAll(
                "SELECT name, region, hold FROM public.locations
                  WHERE regexp_replace(lower(coalesce(name, '')), '[^a-z0-9]+', ' ', 'g') = '{$locationEsc}'
                     OR regexp_replace(lower(coalesce(region, '')), '[^a-z0-9]+', ' ', 'g') = '{$locationEsc}'
                  LIMIT 20"
            );
        }

        $canonicalHold = function_exists('DataLastKnownCanonicalHoldHuman')
            ? DataLastKnownCanonicalHoldHuman(false)
            : '';
        return chimOghmaBuildLocationSignalGroups(
            $location,
            is_array($rows) ? $rows : [],
            $canonicalHold,
            trim((string) ($parts['hold_raw'] ?? ''))
        );
    }
}

if (!function_exists('chimOghmaCollectLocationSignals')) {
    function chimOghmaCollectLocationSignals($db): array
    {
        $groups = chimOghmaCollectLocationSignalGroups($db);
        return chimOghmaUniqueSignals(array_merge($groups['location'], $groups['hold']));
    }
}

if (!function_exists('chimOghmaTopicAliases')) {
    function chimOghmaTopicAliases($topic, $aliases = ''): array
    {
        return chimOghmaUniqueSignals(array_merge(
            preg_split('/\s*,\s*/u', (string) $topic) ?: [],
            chimOghmaSplitAliases($aliases)
        ));
    }
}

if (!function_exists('chimOghmaFindRowsForSignals')) {
    function chimOghmaFindRowsForSignals($db, array $signals): array
    {
        $signals = chimOghmaUniqueSignals($signals);
        if (!$db || empty($signals) || !method_exists($db, 'fetchAll')) {
            return [];
        }

        $quoted = array_map(static fn($signal) => "'" . $db->escape($signal) . "'", $signals);
        $rows = $db->fetchAll(
            "SELECT topic, aliases, topic_desc, knowledge_class, topic_desc_basic, knowledge_class_basic
               FROM public.oghma
              WHERE EXISTS (
                    SELECT 1
                      FROM regexp_split_to_table(
                            concat_ws(',', topic, coalesce(aliases, '')),
                            E'\\\\s*,\\\\s*'
                      ) AS topic_alias
                     WHERE regexp_replace(replace(lower(topic_alias), '_', ' '), '[^a-z0-9]+', ' ', 'g')
                           IN (" . implode(',', $quoted) . ")
              )"
        );

        $rows = is_array($rows) ? $rows : [];
        $priorities = array_flip($signals);
        usort($rows, static function ($left, $right) use ($priorities) {
            $leftPriority = PHP_INT_MAX;
            foreach (chimOghmaTopicAliases($left['topic'] ?? '', $left['aliases'] ?? '') as $alias) {
                $leftPriority = min($leftPriority, $priorities[$alias] ?? PHP_INT_MAX);
            }
            $rightPriority = PHP_INT_MAX;
            foreach (chimOghmaTopicAliases($right['topic'] ?? '', $right['aliases'] ?? '') as $alias) {
                $rightPriority = min($rightPriority, $priorities[$alias] ?? PHP_INT_MAX);
            }
            return $leftPriority <=> $rightPriority;
        });

        return $rows;
    }
}

if (!function_exists('chimOghmaKnowledgeClassAllows')) {
    function chimOghmaKnowledgeClassAllows($classes, array $knowledgeTags): bool
    {
        $classes = array_values(array_filter(array_map(
            static fn($value) => strtolower(trim((string) $value)),
            explode(',', (string) $classes)
        )));
        if (empty($classes)) {
            return true;
        }

        $knowledgeTags = array_values(array_filter(array_map(
            static fn($value) => strtolower(trim((string) $value)),
            $knowledgeTags
        )));
        $denied = array_map(
            static fn($value) => substr($value, 1),
            array_filter($classes, static fn($value) => str_starts_with($value, '!'))
        );
        if (!empty(array_intersect($denied, $knowledgeTags))) {
            return false;
        }

        $allowed = array_filter($classes, static fn($value) => !str_starts_with($value, '!'));
        return !empty(array_intersect($allowed, $knowledgeTags));
    }
}

if (!function_exists('chimOghmaResolveKnowledgePayload')) {
    function chimOghmaResolveKnowledgePayload(array $row, array $knowledgeTags): ?array
    {
        $normalizedTags = array_map(static fn($value) => strtolower(trim((string) $value)), $knowledgeTags);
        $advancedAllowed = in_array('knowall', $normalizedTags, true)
            || chimOghmaKnowledgeClassAllows($row['knowledge_class'] ?? '', $knowledgeTags);
        if ($advancedAllowed && trim((string) ($row['topic_desc'] ?? '')) !== '') {
            return ['level' => 'advanced', 'description' => trim((string) $row['topic_desc'])];
        }

        if (chimOghmaKnowledgeClassAllows($row['knowledge_class_basic'] ?? '', $knowledgeTags)
            && trim((string) ($row['topic_desc_basic'] ?? '')) !== '') {
            return ['level' => 'basic', 'description' => trim((string) $row['topic_desc_basic'])];
        }

        return null;
    }
}

if (!function_exists('chimOghmaTopicWasInjected')) {
    function chimOghmaTopicWasInjected($topic): bool
    {
        foreach (chimOghmaTopicAliases($topic) as $alias) {
            if (!empty($GLOBALS['OGHMA_INJECTED_TOPICS'][$alias])) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('chimOghmaMarkTopicInjected')) {
    function chimOghmaMarkTopicInjected($topic): void
    {
        foreach (chimOghmaTopicAliases($topic) as $alias) {
            $GLOBALS['OGHMA_INJECTED_TOPICS'][$alias] = true;
        }
    }
}

if (!function_exists('chimOghmaPayloadFingerprint')) {
    function chimOghmaPayloadFingerprint($description): string
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', trim((string) $description)));
        return $normalized === '' ? '' : hash('sha256', strtolower($normalized));
    }
}

if (!function_exists('chimOghmaPayloadWasInjected')) {
    function chimOghmaPayloadWasInjected($description): bool
    {
        $fingerprint = chimOghmaPayloadFingerprint($description);
        return $fingerprint !== '' && !empty($GLOBALS['OGHMA_INJECTED_PAYLOADS'][$fingerprint]);
    }
}

if (!function_exists('chimOghmaMarkPayloadInjected')) {
    function chimOghmaMarkPayloadInjected($description): void
    {
        $fingerprint = chimOghmaPayloadFingerprint($description);
        if ($fingerprint !== '') {
            $GLOBALS['OGHMA_INJECTED_PAYLOADS'][$fingerprint] = true;
        }
    }
}

if (!function_exists('chimOghmaAppendForcedRows')) {
    function chimOghmaAppendForcedRows(array $rows, array $knowledgeTags, string $source, int $limit): int
    {
        $added = 0;
        foreach ($rows as $row) {
            if ($added >= $limit || chimOghmaTopicWasInjected($row['topic'] ?? '')) {
                continue;
            }
            $payload = chimOghmaResolveKnowledgePayload($row, $knowledgeTags);
            if ($payload === null) {
                continue;
            }

            $topic = trim((string) ($row['topic'] ?? ''));
            if (chimOghmaPayloadWasInjected($payload['description'])) {
                chimOghmaMarkTopicInjected($topic);
                if (class_exists('Logger')) {
                    Logger::info("[OGHMA] Skipped duplicate {$source} article content: {$topic}");
                }
                continue;
            }

            $levelText = $payload['level'] === 'advanced'
                ? 'You have advanced knowledge on this subject, you can use it in your dialogue'
                : 'You only have basic knowledge on this subject, you can use it in your dialogue';
            $GLOBALS['OGHMA_HINT'] .= " \n#Lore Information ({$levelText}): {$topic}\n\"{$payload['description']}\"";
            chimOghmaMarkPayloadInjected($payload['description']);
            chimOghmaMarkTopicInjected($topic);
            $added++;
            if (class_exists('Logger')) {
                Logger::info("[OGHMA] Forced {$source} article: {$topic}");
            }
        }
        return $added;
    }
}

if (!function_exists('chimOghmaInjectForcedContext')) {
    function chimOghmaInjectForcedContext($db, $npcMaster = null): int
    {
        $knowledgeTags = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($GLOBALS['OGHMA_KNOWLEDGE'] ?? ''))
        )));
        $knowledgeTags[] = (string) ($GLOBALS['HERIKA_NAME'] ?? '');
        $added = 0;

        $racialEnabled = isOghmaEnabled($GLOBALS['RACIAL_OGHMA'] ?? true);
        if ($racialEnabled) {
            $currentNpcData = is_array($GLOBALS['CHIM_CORE_CURRENT_NPC_DATA'] ?? null)
                ? $GLOBALS['CHIM_CORE_CURRENT_NPC_DATA']
                : [];
            $raceSignals = chimOghmaCollectRaceSignals(
                $currentNpcData,
                $GLOBALS['CACHE_PEOPLE'] ?? '',
                $npcMaster,
                4
            );
            $added += chimOghmaAppendForcedRows(
                chimOghmaFindRowsForSignals($db, $raceSignals),
                $knowledgeTags,
                'race',
                4
            );
        }

        $locationEnabled = isOghmaEnabled($GLOBALS['LOCATION_OGHMA'] ?? true);
        if ($locationEnabled) {
            $locationSignals = chimOghmaCollectLocationSignalGroups($db);
            $added += chimOghmaAppendForcedRows(
                chimOghmaFindRowsForSignals($db, $locationSignals['location']),
                $knowledgeTags,
                'location',
                1
            );
            $added += chimOghmaAppendForcedRows(
                chimOghmaFindRowsForSignals($db, $locationSignals['hold']),
                $knowledgeTags,
                'hold',
                1
            );
        }

        return $added;
    }
}
