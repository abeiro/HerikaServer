<?php

if (!function_exists('chimOghmaRuleValues')) {
    function chimOghmaRuleValues($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = preg_split('/\s*,\s*/u', $value) ?: [];
            }
        }
        if (!is_array($value)) {
            return [];
        }

        $values = [];
        foreach ($value as $entry) {
            $normalized = chimOghmaNormalizeLookupLabel($entry);
            if ($normalized !== '') {
                $values[$normalized] = $normalized;
            }
        }
        return array_values($values);
    }
}

if (!function_exists('chimOghmaRuleExtendedDataFactions')) {
    function chimOghmaRuleExtendedDataFactions($npcMaster, array $currentNpcData): array
    {
        if (!$npcMaster || !method_exists($npcMaster, 'getExtendedData')) {
            return [];
        }
        $extendedData = $npcMaster->getExtendedData($currentNpcData);
        if (!is_array($extendedData) || !is_array($extendedData['factions'] ?? null)) {
            return [];
        }

        $factions = [];
        foreach ($extendedData['factions'] as $faction) {
            if (!is_array($faction)) {
                continue;
            }
            foreach (['name', 'editorid', 'formid'] as $field) {
                if (!empty($faction[$field])) {
                    $factions[] = $faction[$field];
                }
            }
            if (!empty($faction['formid']) && function_exists('lookupDescriptionByFormID')) {
                $description = lookupDescriptionByFormID($faction['formid']);
                if (is_array($description) && !empty($description['name'])) {
                    $factions[] = $description['name'];
                }
            }
        }
        return chimOghmaUniqueSignals($factions);
    }
}

if (!function_exists('chimOghmaWeatherSignals')) {
    function chimOghmaWeatherSignals($weather): array
    {
        $weather = trim((string) $weather);
        if ($weather === '') {
            return [];
        }
        $signals = [$weather];
        $withoutPrefix = preg_replace('/^outdoors\s+it\s+is\s+/iu', '', $weather);
        $signals[] = $withoutPrefix;
        $signals = array_merge($signals, preg_split('/\s*,\s*/u', (string) $withoutPrefix) ?: []);
        return chimOghmaUniqueSignals($signals);
    }
}

if (!function_exists('chimOghmaBuildContextRuleContext')) {
    function chimOghmaBuildContextRuleContext($db, $npcMaster = null): array
    {
        $currentNpcData = is_array($GLOBALS['CHIM_CORE_CURRENT_NPC_DATA'] ?? null)
            ? $GLOBALS['CHIM_CORE_CURRENT_NPC_DATA']
            : [];
        $locationGroups = chimOghmaCollectLocationSignalGroups($db);
        $locationParts = function_exists('DataLastKnownLocationContextParts')
            ? DataLastKnownLocationContextParts(false)
            : [];
        $rawLocation = trim((string) ($locationParts['location'] ?? ''));
        $environment = '';
        if (preg_match('/\s+(interior|outdoors)\s*$/iu', $rawLocation, $matches)) {
            $environment = strtolower($matches[1]) === 'interior' ? 'interior' : 'exterior';
        }

        $gameRequest = is_array($GLOBALS['gameRequest'] ?? null) ? $GLOBALS['gameRequest'] : [];
        $npcNames = array_filter([
            $currentNpcData['npc_name'] ?? '',
            $GLOBALS['HERIKA_NAME'] ?? '',
        ]);

        return [
            'npc' => chimOghmaUniqueSignals($npcNames),
            'nearby_actor' => chimOghmaUniqueSignals(chimOghmaPeopleNames($GLOBALS['CACHE_PEOPLE'] ?? '')),
            'race' => chimOghmaRaceSignals($currentNpcData['race'] ?? ''),
            'faction' => chimOghmaRuleExtendedDataFactions($npcMaster, $currentNpcData),
            'profile' => chimOghmaRuleValues([$currentNpcData['profile_id'] ?? '']),
            'location' => chimOghmaUniqueSignals($locationGroups['location'] ?? []),
            'hold' => chimOghmaUniqueSignals($locationGroups['hold'] ?? []),
            'environment' => chimOghmaRuleValues([$environment]),
            'weather' => chimOghmaWeatherSignals(
                function_exists('DataLastKnownWeatherHuman') ? DataLastKnownWeatherHuman() : ''
            ),
            'event_type' => chimOghmaRuleValues([$gameRequest[0] ?? '']),
        ];
    }
}

if (!function_exists('chimOghmaContextRuleMatches')) {
    function chimOghmaContextRuleMatches(array $conditions, array $context, ?array &$reasons = null): bool
    {
        $reasons = [];
        foreach ($conditions as $field => $expectedValues) {
            $expected = chimOghmaRuleValues($expectedValues);
            if (empty($expected)) {
                continue;
            }
            $actual = chimOghmaRuleValues($context[$field] ?? []);
            $matched = array_values(array_intersect($expected, $actual));
            if (empty($matched)) {
                return false;
            }
            $reasons[] = $field . '=' . implode('|', $matched);
        }
        return true;
    }
}

if (!function_exists('chimOghmaFindRowsForRuleSelector')) {
    function chimOghmaFindRowsForRuleSelector($db, string $selectorType, string $selectorValue, int $limit): array
    {
        if (!$db || !method_exists($db, 'fetchAll')) {
            return [];
        }
        $selectorType = strtolower(trim($selectorType));
        $selectorValue = chimOghmaNormalizeLookupLabel($selectorValue);
        if ($selectorValue === '' || !in_array($selectorType, ['topic', 'tag', 'category'], true)) {
            return [];
        }

        $escaped = $db->escape($selectorValue);
        if ($selectorType === 'topic') {
            $condition = "EXISTS (
                SELECT 1
                  FROM regexp_split_to_table(topic, E'\\\\s*,\\\\s*') AS selector_value
                 WHERE regexp_replace(replace(lower(selector_value), '_', ' '), '[^a-z0-9]+', ' ', 'g') = '{$escaped}'
            )";
        } elseif ($selectorType === 'tag') {
            $condition = "EXISTS (
                SELECT 1
                  FROM regexp_split_to_table(coalesce(tags, ''), E'\\\\s*,\\\\s*') AS selector_value
                 WHERE regexp_replace(replace(lower(selector_value), '_', ' '), '[^a-z0-9]+', ' ', 'g') = '{$escaped}'
            )";
        } else {
            $condition = "regexp_replace(replace(lower(coalesce(category, '')), '_', ' '), '[^a-z0-9]+', ' ', 'g') = '{$escaped}'";
        }

        $limit = max(1, min(5, $limit));
        $rows = $db->fetchAll(
            "SELECT topic, topic_desc, knowledge_class, topic_desc_basic, knowledge_class_basic
               FROM public.oghma
              WHERE {$condition}
              ORDER BY topic
              LIMIT {$limit}"
        );
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('chimOghmaLoadContextRules')) {
    function chimOghmaLoadContextRules($db): array
    {
        if (!$db || !method_exists($db, 'fetchAll')) {
            return [];
        }
        try {
            $rules = $db->fetchAll(
                "SELECT id, label, priority, selector_type, selector_value, conditions, max_articles
                   FROM public.oghma_context_rule
                  WHERE enabled = TRUE
                  ORDER BY priority, id"
            );
            return is_array($rules) ? $rules : [];
        } catch (Throwable $exception) {
            if (class_exists('Logger')) {
                Logger::warn('[OGHMA] Context rules unavailable: ' . $exception->getMessage());
            }
            return [];
        }
    }
}

if (!function_exists('chimOghmaInjectContextRules')) {
    function chimOghmaInjectContextRules($db, $npcMaster = null): int
    {
        $rules = chimOghmaLoadContextRules($db);
        if (empty($rules)) {
            return 0;
        }

        $knowledgeTags = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($GLOBALS['OGHMA_KNOWLEDGE'] ?? ''))
        )));
        $knowledgeTags[] = (string) ($GLOBALS['HERIKA_NAME'] ?? '');
        $context = chimOghmaBuildContextRuleContext($db, $npcMaster);
        $added = 0;

        foreach ($rules as $rule) {
            $conditions = $rule['conditions'] ?? [];
            if (is_string($conditions)) {
                $conditions = json_decode($conditions, true);
            }
            if (!is_array($conditions)) {
                $conditions = [];
            }
            $reasons = [];
            if (!chimOghmaContextRuleMatches($conditions, $context, $reasons)) {
                continue;
            }

            $limit = max(1, min(5, (int) ($rule['max_articles'] ?? 1)));
            $rows = chimOghmaFindRowsForRuleSelector(
                $db,
                (string) ($rule['selector_type'] ?? 'topic'),
                (string) ($rule['selector_value'] ?? ''),
                $limit
            );
            $ruleAdded = chimOghmaAppendForcedRows(
                $rows,
                $knowledgeTags,
                'context rule ' . (int) ($rule['id'] ?? 0),
                $limit
            );
            $added += $ruleAdded;

            if (class_exists('Logger')) {
                $label = trim((string) ($rule['label'] ?? 'Unnamed rule'));
                $reasonText = empty($reasons) ? 'always' : implode(', ', $reasons);
                Logger::info(
                    "[OGHMA] Context rule " . (int) ($rule['id'] ?? 0)
                    . " '{$label}' matched ({$reasonText}); added {$ruleAdded} article(s)"
                );
            }
        }
        return $added;
    }
}
