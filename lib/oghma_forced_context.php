<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'oghma_aliases.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'oghma_parity.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'game_plugins.php';

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

if (!function_exists('chimOghmaStableFormKey')) {
    /** Normalize a runtime or plugin-local FormID into a load-order-independent key. */
    function chimOghmaStableFormKey($formId, $pluginName = ''): string
    {
        $parsed = chimParseStableFormReference($formId);
        if ($parsed) {
            return strtolower($parsed['plugin_name']) . '|' . $parsed['local_formid'];
        }
        $runtime = chimNormalizeRuntimeFormId($formId);
        if ($runtime === '') return '';
        $pluginName = trim((string) $pluginName);
        if ($pluginName !== '') {
            return strtolower($pluginName) . '|' . chimExtractLocalFormIdFromRuntimeFormId($runtime);
        }
        if (str_starts_with($runtime, '00')) {
            return 'skyrim.esm|' . chimExtractLocalFormIdFromRuntimeFormId($runtime);
        }
        $stable = chimConvertRuntimeFormIdToStableReference($runtime);
        $parsed = $stable === null ? null : chimParseStableFormReference($stable);
        return $parsed ? strtolower($parsed['plugin_name']) . '|' . $parsed['local_formid'] : '';
    }
}

if (!function_exists('chimOghmaRaceIdentitySignals')) {
    /** Prefer stable vanilla race identity, then retain text aliases as the compatibility fallback. */
    function chimOghmaRaceIdentitySignals(array $npcData): array
    {
        $stableRaceMap = [
            'skyrim.esm|00013740' => 'argonian',
            'skyrim.esm|00013741' => 'breton',
            'skyrim.esm|00013742' => 'dark elf',
            'skyrim.esm|00013743' => 'high elf',
            'skyrim.esm|00013744' => 'imperial',
            'skyrim.esm|00013745' => 'khajiit',
            'skyrim.esm|00013746' => 'nord',
            'skyrim.esm|00013747' => 'orc',
            'skyrim.esm|00013748' => 'redguard',
            'skyrim.esm|00013749' => 'wood elf',
        ];
        $stableKey = chimOghmaStableFormKey(
            $npcData['race_formid'] ?? $npcData['race_form_id'] ?? $npcData['race_stable_key'] ?? '',
            $npcData['race_plugin'] ?? ''
        );
        $race = $stableRaceMap[$stableKey] ?? ($npcData['race'] ?? '');
        return chimOghmaRaceSignals($race);
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
        $signals = chimOghmaRaceIdentitySignals($currentNpcData);
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
            $signals = array_merge($signals, chimOghmaRaceIdentitySignals($npcData));
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

if (!function_exists('chimOghmaLocationFormIdCandidates')) {
    /** Resolve stable and runtime location identities to the decimal values stored by Skyrim tables. */
    function chimOghmaLocationFormIdCandidates($value): array
    {
        $raw = trim((string) $value);
        if ($raw === '') return [];
        $parsed = chimParseStableFormReference($raw);
        if ($parsed) {
            $runtime = strcasecmp($parsed['plugin_name'], 'Skyrim.esm') === 0
                ? $parsed['local_formid']
                : chimResolveStableFormReferenceToRuntimeFormId($parsed['stable_key']);
            return $runtime === null || $runtime === '' ? [] : [hexdec(chimNormalizeRuntimeFormId($runtime))];
        }
        if (preg_match('/^0x[0-9a-f]+$/i', $raw)) return [hexdec(substr($raw, 2))];
        if (preg_match('/^[0-9]+$/D', $raw)) {
            $decimal = intval($raw);
            $hexValue = hexdec(chimNormalizeRuntimeFormId($raw));
            return array_values(array_unique(array_filter([$decimal, $hexValue], static fn(int $id): bool => $id > 0)));
        }
        $runtime = chimNormalizeRuntimeFormId($raw);
        return $runtime === '' ? [] : [hexdec($runtime)];
    }
}

if (!function_exists('chimOghmaResolveLocationRows')) {
    /** Prefer an exact FormID lookup and use normalized location text only when identity is unavailable. */
    function chimOghmaResolveLocationRows($db, array $parts, string $location): array
    {
        if (!$db || !method_exists($db, 'fetchAll')) return [];
        $formIds = [];
        foreach ([
            $parts['location_formid'] ?? '',
            $parts['location_stable_key'] ?? '',
            $GLOBALS['CHIM_CURRENT_LOCATION_FORMID'] ?? '',
        ] as $candidate) {
            $formIds = array_merge($formIds, chimOghmaLocationFormIdCandidates($candidate));
        }
        $formIds = array_values(array_unique($formIds));
        if ($formIds !== []) {
            $rows = $db->fetchAll(
                'SELECT formid, name, region, hold FROM public.locations WHERE formid IN ('
                . implode(',', array_map('intval', $formIds)) . ') LIMIT 20'
            );
            if (is_array($rows) && $rows !== []) return $rows;
        }
        if ($location === '') return [];
        $locationKey = chimOghmaNormalizeLookupLabel($location);
        $locationEsc = $db->escape($locationKey);
        $rows = $db->fetchAll(
            "SELECT formid, name, region, hold FROM public.locations
              WHERE regexp_replace(lower(coalesce(name, '')), '[^a-z0-9]+', ' ', 'g') = '{$locationEsc}'
                 OR regexp_replace(lower(coalesce(region, '')), '[^a-z0-9]+', ' ', 'g') = '{$locationEsc}'
              LIMIT 20"
        );
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('chimOghmaCollectLocationSignalGroups')) {
    function chimOghmaCollectLocationSignalGroups($db): array
    {
        $parts = function_exists('DataLastKnownLocationContextParts')
            ? DataLastKnownLocationContextParts(false)
            : [];
        $location = trim((string) ($parts['location_base'] ?? $parts['location'] ?? ''));
        $rows = chimOghmaResolveLocationRows($db, $parts, $location);

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
        return chimOghmaKnowledgeClassDecision($classes, $knowledgeTags)['allowed'];
    }
}

if (!function_exists('chimOghmaResolveKnowledgePayload')) {
    function chimOghmaResolveKnowledgePayload(array $row, array $knowledgeTags): ?array
    {
        $normalizedTags = chimOghmaKnowledgeValues($knowledgeTags);
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
        if (!is_array($GLOBALS['OGHMA_PARITY_RESULT'] ?? null)) {
            $GLOBALS['OGHMA_PARITY_RESULT'] = chimOghmaNewResult(
                'not_found',
                chimOghmaEffectiveSettings(),
                true
            );
        }
        $added = 0;
        foreach ($rows as $row) {
            $resultLimit = max(1, intval($GLOBALS['OGHMA_PARITY_RESULT']['settings']['values']['result_limit'] ?? 1));
            if ($added >= $limit || count($GLOBALS['OGHMA_PARITY_RESULT']['articles'] ?? []) >= $resultLimit) {
                break;
            }
            if (chimOghmaTopicWasInjected($row['topic'] ?? '')) {
                continue;
            }
            $topic = trim((string) ($row['topic'] ?? ''));
            if (function_exists('chimOghmaAddPromptArticle')) {
                $selected = chimOghmaAddPromptArticle($row, $knowledgeTags, $source, true);
                $decision = end($GLOBALS['OGHMA_PARITY_RESULT']['access_decisions']);
                if (!$selected) {
                    if (($decision['reason'] ?? '') === 'duplicate_content') {
                        chimOghmaMarkTopicInjected($topic);
                    }
                    continue;
                }
                $description = ($decision['level'] ?? '') === 'advanced'
                    ? trim((string) ($row['topic_desc'] ?? ''))
                    : trim((string) ($row['topic_desc_basic'] ?? ''));
                chimOghmaMarkPayloadInjected($description);
                chimOghmaMarkTopicInjected($topic);
                $GLOBALS['OGHMA_HINT'] = chimOghmaRenderKnowledgeFragment(
                    $GLOBALS['OGHMA_PARITY_RESULT']['articles'],
                    'matched'
                );
                $added++;
                if (class_exists('Logger')) Logger::info("[OGHMA] Forced {$source} article: {$topic}");
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
        $hasCapacity = static function (): bool {
            $resultLimit = max(1, intval($GLOBALS['OGHMA_PARITY_RESULT']['settings']['values']['result_limit'] ?? 1));
            return count($GLOBALS['OGHMA_PARITY_RESULT']['articles'] ?? []) < $resultLimit;
        };

        $locationEnabled = isOghmaEnabled($GLOBALS['LOCATION_OGHMA'] ?? true);
        if ($locationEnabled && $hasCapacity()) {
            $locationSignals = chimOghmaCollectLocationSignalGroups($db);
            $added += chimOghmaAppendForcedRows(
                chimOghmaFindRowsForSignals($db, $locationSignals['location']),
                $knowledgeTags,
                'location',
                1
            );
            if ($hasCapacity()) {
                $added += chimOghmaAppendForcedRows(
                    chimOghmaFindRowsForSignals($db, $locationSignals['hold']),
                    $knowledgeTags,
                    'hold',
                    1
                );
            }
        }

        $racialEnabled = isOghmaEnabled($GLOBALS['RACIAL_OGHMA'] ?? true);
        if ($racialEnabled && $hasCapacity()) {
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

        return $added;
    }
}
