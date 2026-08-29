<?php

declare(strict_types=1);

const CHIM_OGHMA_PARITY_VERSION = 'oghma-parity-v1';
const CHIM_OGHMA_STATUSES = [
    'grounded','no_match','fallback_succeeded','fallback_unresolved','fallback_failed','fallback_disabled',
    'fallback_unconfigured','disabled','ineligible','unavailable','not_run','legacy',
];

if (!function_exists('chimOghmaBool')) {
    function chimOghmaBool($value, bool $default = false): bool
    {
        if ($value === null || $value === '') return $default;
        if (is_bool($value)) return $value;
        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) return true;
        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) return false;
        return $default;
    }
}

if (!function_exists('chimOghmaSettingSource')) {
    /** Report the highest-precedence layer that supplied an effective Oghma setting. */
    function chimOghmaSettingSource(string $name): string
    {
        $npc = is_array($GLOBALS['CHIM_CORE_CURRENT_NPC_DATA'] ?? null)
            ? $GLOBALS['CHIM_CORE_CURRENT_NPC_DATA']
            : [];
        foreach (['extended_data', 'metadata'] as $field) {
            $values = $npc[$field] ?? [];
            if (is_string($values)) $values = json_decode($values, true);
            if (is_array($values) && array_key_exists($name, $values)) return 'npc';
        }

        $profile = is_array($GLOBALS['CHIM_CORE_CURRENT_PROFILE_DATA'] ?? null)
            ? $GLOBALS['CHIM_CORE_CURRENT_PROFILE_DATA']
            : [];
        $metadata = $profile['metadata'] ?? [];
        if (is_string($metadata)) $metadata = json_decode($metadata, true);
        return is_array($metadata) && array_key_exists($name, $metadata) ? 'core_profile' : 'global';
    }
}

if (!function_exists('chimOghmaEffectiveSettings')) {
    /** Resolve the CHIM Global -> Core Profile -> NPC Oghma settings already applied to legacy globals. */
    function chimOghmaEffectiveSettings(): array
    {
        $topicCount = max(1, min(3, intval($GLOBALS['OGHMA_AMOUNT'] ?? 1)));
        $resultLimit = max(1, min(5, intval($GLOBALS['OGHMA_RESULT_LIMIT'] ?? 3)));
        $fallbackSetting = array_key_exists('OGHMA_EXTRACTOR_FALLBACK', $GLOBALS)
            ? $GLOBALS['OGHMA_EXTRACTOR_FALLBACK']
            : ($GLOBALS['OGHMA_CUSTOM'] ?? false);
        $timeoutMs = max(250, min(3000, intval($GLOBALS['OGHMA_EXTRACTOR_TIMEOUT_MS'] ?? 1500)));
        $values = [
            'enabled' => chimOghmaBool($GLOBALS['OGHMA_INFINIUM'] ?? true, true),
            'extractor_fallback_enabled' => chimOghmaBool($fallbackSetting, false),
            'topic_count' => $topicCount,
            'result_limit' => $resultLimit,
            'racial_context_enabled' => chimOghmaBool($GLOBALS['RACIAL_OGHMA'] ?? true, true),
            'location_context_enabled' => chimOghmaBool($GLOBALS['LOCATION_OGHMA'] ?? true, true),
            'extractor_timeout_ms' => $timeoutMs,
            'connector_id' => trim((string) ($GLOBALS['CORE_CONNECTOR_OGHMA_CUSTOM'] ?? '')),
        ];
        $sourceKeys = [
            'enabled' => 'OGHMA_INFINIUM',
            'extractor_fallback_enabled' => array_key_exists('OGHMA_EXTRACTOR_FALLBACK', $GLOBALS)
                ? 'OGHMA_EXTRACTOR_FALLBACK'
                : 'OGHMA_CUSTOM',
            'topic_count' => 'OGHMA_AMOUNT',
            'result_limit' => 'OGHMA_RESULT_LIMIT',
            'racial_context_enabled' => 'RACIAL_OGHMA',
            'location_context_enabled' => 'LOCATION_OGHMA',
            'extractor_timeout_ms' => 'OGHMA_EXTRACTOR_TIMEOUT_MS',
            'connector_id' => 'CORE_CONNECTOR_OGHMA_CUSTOM',
        ];
        $sources = [];
        foreach ($sourceKeys as $field => $key) $sources[$field] = chimOghmaSettingSource($key);
        $canonical = $values;
        ksort($canonical, SORT_STRING);
        return [
            'contract' => CHIM_OGHMA_PARITY_VERSION,
            'values' => $values,
            'sources' => $sources,
            'sha256' => hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        ];
    }
}

if (!function_exists('chimOghmaRequestEligible')) {
    /** Keep Oghma off timer, combat, action-result, and per-frame request families. */
    function chimOghmaRequestEligible(array $gameRequest): bool
    {
        return in_array(strtolower(trim((string) ($gameRequest[0] ?? ''))), [
            'inputtext', 'inputtext_s', 'ginputtext', 'ginputtext_s', 'rechat', 'continue', 'instruction', 'suggestion',
        ], true);
    }
}

if (!function_exists('chimOghmaKnowledgeValues')) {
    function chimOghmaKnowledgeValues($value): array
    {
        $values = is_array($value) ? $value : preg_split('/\s*[,;|]\s*/u', (string) $value);
        $result = [];
        foreach ($values ?: [] as $item) {
            $item = strtolower(trim((string) $item));
            if ($item === '') continue;
            $negative = str_starts_with($item, '!');
            $raw = $negative ? substr($item, 1) : $item;
            $aliases = [
                'smith'=>['blacksmith'], 'darkelf'=>['dunmer'], 'dark_elf'=>['dunmer'],
                'highelf'=>['altmer'], 'high_elf'=>['altmer'], 'woodelf'=>['bosmer'],
                'wood_elf'=>['bosmer'], 'thievesguild'=>['thieves_guild'],
                'legion'=>['imperial_legion'], 'darkbrotherhood'=>['dark_brotherhood'],
                'eastempirecompany'=>['east_empire_company'], 'moragtong'=>['morag_tong'],
                'househlaalu'=>['house_hlaalu'], 'houseredoran'=>['house_redoran'],
                'housetelvanni'=>['house_telvanni'], 'collegeofwinterhold'=>['college_of_winterhold'],
                'collegeofwinterold'=>['college_of_winterhold'], 'magesguild'=>['mages_guild'],
                'fightersguild'=>['fighters_guild'], 'temple'=>['tribunal_temple'],
                'telvanni'=>['house_telvanni'], 'redoran'=>['house_redoran'],
                'hlaalu'=>['house_hlaalu'], 'sixth_house'=>['house_dagoth'],
                'hands_of_almalexia'=>['tribunal_temple'], 'miraakcult'=>['miraak_cult'],
                'psijic'=>['psijic_order'], 'stormcloak'=>['stormcloaks'],
                'snowelf'=>['snow_elf'], 'skall'=>['skaal'], 'daedric'=>['daedra'],
                'skyrimall'=>['common'], 'legion. skyrimall'=>['imperial_legion', 'common'],
                'stands-in-shallows'=>['stands_in_shallows'], 'talen-jei'=>['talen_jei'],
            ];
            $key = array_key_exists($raw, $aliases)
                ? $raw
                : trim((string) preg_replace('/[^a-z0-9]+/u', '_', $raw), '_');
            foreach ($aliases[$key] ?? [$key] as $canonical) {
                $canonical = $negative ? '!' . $canonical : $canonical;
                if ($canonical !== '' && !in_array($canonical, $result, true)) $result[] = $canonical;
            }
        }
        return $result;
    }
}

if (!function_exists('chimOghmaNpcKnowledgeTags')) {
    /** Normalize NPC permissions while removing article-only access markers. */
    function chimOghmaNpcKnowledgeTags($value): string
    {
        $tags = array_values(array_filter(
            chimOghmaKnowledgeValues($value),
            static fn(string $tag): bool => !in_array(ltrim($tag, '!'), ['common', 'esoteric'], true)
        ));
        return implode(', ', $tags);
    }
}

if (!function_exists('chimOghmaKnowledgeClassDecision')) {
    /** Negative classes deny before positive matching; common is public only for basic knowledge. */
    function chimOghmaKnowledgeClassDecision($classes, array $knowledgeTags, bool $allowCommon = false): array
    {
        $classValues = chimOghmaKnowledgeValues($classes);
        $tags = chimOghmaKnowledgeValues($knowledgeTags);
        if ($classValues === []) return ['allowed' => true, 'reason' => 'unrestricted', 'matched' => []];
        $denied = array_map(
            static fn(string $value): string => substr($value, 1),
            array_filter($classValues, static fn(string $value): bool => str_starts_with($value, '!'))
        );
        $negativeMatches = array_values(array_intersect($denied, $tags));
        if ($negativeMatches !== []) return ['allowed' => false, 'reason' => 'negative_class', 'matched' => $negativeMatches];
        $allowed = array_values(array_filter($classValues, static fn(string $value): bool => !str_starts_with($value, '!')));
        if ($allowCommon && in_array('common', $allowed, true)) {
            return ['allowed' => true, 'reason' => 'common', 'matched' => ['common']];
        }
        $positiveMatches = array_values(array_intersect($allowed, $tags));
        return [
            'allowed' => $positiveMatches !== [],
            'reason' => $positiveMatches !== [] ? 'positive_class' : 'missing_class',
            'matched' => $positiveMatches,
        ];
    }
}

if (!function_exists('chimOghmaAccessDecision')) {
    /** Resolve advanced, basic, or denied access with a complete auditable decision. */
    function chimOghmaAccessDecision(array $row, array $knowledgeTags): array
    {
        $tags = array_values(array_diff(chimOghmaKnowledgeValues($knowledgeTags), ['common', 'esoteric']));
        if (in_array('knowall', $tags, true) && trim((string) ($row['topic_desc'] ?? '')) !== '') {
            return ['level' => 'advanced', 'reason' => 'knowall', 'matched' => ['knowall']];
        }
        $advanced = chimOghmaKnowledgeClassDecision($row['knowledge_class'] ?? '', $tags);
        if ($advanced['allowed'] && trim((string) ($row['topic_desc'] ?? '')) !== '') {
            return ['level' => 'advanced', 'reason' => $advanced['reason'], 'matched' => $advanced['matched']];
        }
        $basic = chimOghmaKnowledgeClassDecision($row['knowledge_class_basic'] ?? '', $tags, true);
        if ($basic['allowed'] && trim((string) ($row['topic_desc_basic'] ?? '')) !== '') {
            return ['level' => 'basic', 'reason' => $basic['reason'], 'matched' => $basic['matched']];
        }
        return [
            'level' => 'denied',
            'reason' => $advanced['reason'] === 'negative_class' || $basic['reason'] === 'negative_class'
                ? 'negative_class'
                : 'knowledge_classes_not_authorized',
            'matched' => array_values(array_unique(array_merge($advanced['matched'], $basic['matched']))),
        ];
    }
}

if (!function_exists('chimOghmaXmlEscape')) {
    function chimOghmaXmlEscape($value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', "\u{FFFD}", (string) $value) ?? '';
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, 'UTF-8');
    }
}

if (!function_exists('chimOghmaRenderKnowledgeFragment')) {
    /** Render the only Oghma prompt payload shape permitted by oghma-parity-v1. */
    function chimOghmaRenderKnowledgeFragment(array $articles, string $status): string
    {
        if ($articles === []) return '';
        $lines = ['<oghma contract="' . CHIM_OGHMA_PARITY_VERSION . '" status="' . chimOghmaXmlEscape($status) . '">'];
        foreach ($articles as $article) {
            $topic = chimOghmaXmlEscape($article['topic'] ?? '');
            $source = chimOghmaXmlEscape($article['source'] ?? 'conversation');
            $access = chimOghmaXmlEscape($article['access'] ?? 'denied');
            $lines[] = '  <article topic="' . $topic . '" source="' . $source . '" access="' . $access . '">';
            if (($article['access'] ?? 'denied') === 'denied') {
                $lines[] = '    <denial reason="' . chimOghmaXmlEscape($article['reason'] ?? 'knowledge_classes_not_authorized') . '" />';
            } else {
                $lines[] = '    <content>' . chimOghmaXmlEscape($article['content'] ?? '') . '</content>';
            }
            $lines[] = '  </article>';
        }
        $lines[] = '</oghma>';
        return implode("\n", $lines);
    }
}

if (!function_exists('chimOghmaAddPromptArticle')) {
    /** Apply access, result-limit, topic, and payload deduplication before prompt rendering. */
    function chimOghmaAddPromptArticle(array $row, array $knowledgeTags, string $source, bool $includeDenied): bool
    {
        if (!is_array($GLOBALS['OGHMA_PARITY_RESULT'] ?? null)) return false;
        $result =& $GLOBALS['OGHMA_PARITY_RESULT'];
        $topic = trim((string) ($row['topic'] ?? ''));
        if ($topic === '') return false;
        $decision = chimOghmaAccessDecision($row, $knowledgeTags);
        $decision['topic'] = $topic;
        $decision['source'] = $source;
        $decision['selected'] = false;

        $topicKey = mb_strtolower(trim(str_replace('_', ' ', $topic)), 'UTF-8');
        foreach ($result['articles'] as $existing) {
            if (mb_strtolower(trim(str_replace('_', ' ', (string) ($existing['topic'] ?? ''))), 'UTF-8') === $topicKey) {
                $decision['reason'] = 'duplicate_topic';
                $result['access_decisions'][] = $decision;
                return false;
            }
        }
        if ($decision['level'] === 'denied' && !$includeDenied) {
            $result['access_decisions'][] = $decision;
            return false;
        }

        $content = $decision['level'] === 'advanced'
            ? trim((string) ($row['topic_desc'] ?? ''))
            : ($decision['level'] === 'basic' ? trim((string) ($row['topic_desc_basic'] ?? '')) : '');
        if ($content !== '') {
            $fingerprint = hash('sha256', mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $content)), 'UTF-8'));
            foreach ($result['articles'] as $existing) {
                if (($existing['content_sha256'] ?? '') === $fingerprint) {
                    $decision['reason'] = 'duplicate_content';
                    $result['access_decisions'][] = $decision;
                    return false;
                }
            }
        } else {
            $fingerprint = '';
        }
        $limit = max(1, intval($result['settings']['values']['result_limit'] ?? 1));
        if (count($result['articles']) >= $limit) {
            $decision['reason'] = 'result_limit';
            $result['access_decisions'][] = $decision;
            return false;
        }
        $decision['selected'] = true;
        $article = [
            'topic' => $topic,
            'source' => $source,
            'access' => $decision['level'],
            'reason' => $decision['reason'],
            'content' => $content,
            'content_sha256' => $fingerprint,
        ];
        $result['articles'][] = $article;
        $result['access_decisions'][] = $decision;
        return true;
    }
}

if (!function_exists('chimOghmaNewResult')) {
    /** Create the stable typed-array result used by runtime, prompt assembly, audit, and fixtures. */
    function chimOghmaNewResult(string $status, array $settings, bool $eligible, string $input = ''): array
    {
        return [
            'contract' => CHIM_OGHMA_PARITY_VERSION,
            'status' => $status,
            'request_type' => '',
            'request_eligible' => $eligible,
            'input' => $input,
            'topics' => [],
            'matches' => [],
            'rejected' => [],
            'tag_decisions' => [],
            'access_decisions' => [],
            'articles' => [],
            'fallback' => [
                'eligible' => false,
                'attempted' => false,
                'status' => 'not_attempted',
                'suggestions' => [],
                'connector_id' => $settings['values']['connector_id'] ?? '',
            ],
            'settings' => $settings,
            'catalog' => ['version' => null, 'manifest_sha256' => null],
            'timing' => ['retrieval_ms' => 0.0, 'fallback_ms' => 0.0, 'total_ms' => 0.0],
            'prompt_sha256' => null,
        ];
    }
}

if (!function_exists('chimOghmaRecordAudit')) {
    /** Persist one bounded audit row without allowing observability failure to break a game response. */
    function chimOghmaRecordAudit($db, array $result): void
    {
        if (!$db || !method_exists($db, 'insert')) return;
        try {
            $db->insert('oghma_audit', [
                'contract_version' => CHIM_OGHMA_PARITY_VERSION,
                'request_type' => (string) ($result['request_type'] ?? ''),
                'input' => mb_strcut((string) ($result['input'] ?? ''), 0, 16384, 'UTF-8'),
                'status' => (string) ($result['status'] ?? 'error'),
                'grounded' => json_encode([
                    'topics' => $result['topics'] ?? [],
                    'matches' => $result['matches'] ?? [],
                    'rejected' => $result['rejected'] ?? [],
                    'tag_decisions' => $result['tag_decisions'] ?? [],
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'access' => json_encode($result['access_decisions'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'fallback' => json_encode($result['fallback'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'settings' => json_encode($result['settings'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'catalog_version' => $result['catalog']['version'] ?? null,
                'catalog_manifest_sha256' => $result['catalog']['manifest_sha256'] ?? null,
                'latency_ms' => floatval($result['timing']['total_ms'] ?? 0.0),
                'prompt_sha256' => $result['prompt_sha256'] ?? null,
            ]);
        } catch (Throwable $error) {
            error_log('[OGHMA] Could not record parity audit: ' . $error->getMessage());
        }
    }
}
