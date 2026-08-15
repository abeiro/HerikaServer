<?php

declare(strict_types=1);

$GLOBALS['OGHMA_HINT'] = '';
$GLOBALS['OGHMA_INJECTED_TOPICS'] = [];
$GLOBALS['OGHMA_INJECTED_PAYLOADS'] = [];

require_once __DIR__ . '/../lib/oghma_parity.php';
require_once __DIR__ . '/../lib/oghma_retrieval.php';
require_once __DIR__ . '/../lib/oghma_forced_context.php';
require_once __DIR__ . '/../lib/eventlog_helper.php';

if (!function_exists('isOghmaEnabled')) {
    function isOghmaEnabled($value): bool
    {
        return chimOghmaBool($value, false);
    }
}

if (!function_exists('chimOghmaInputText')) {
    /** Remove transport-only labels while preserving the player's bounded dialogue text. */
    function chimOghmaSanitizeInputText(string $input): string
    {
        $input = preg_replace('/\([^)]*Context location[^)]*\)/iu', '', $input) ?? $input;
        $input = preg_replace('/\((?:(?:talking|whispering|shouting)|speaking privately)\s+to\s+[^()]+\)/iu', '', $input) ?? $input;
        $player = trim((string) ($GLOBALS['PLAYER_NAME'] ?? ''));
        if ($player !== '') $input = preg_replace('/^' . preg_quote($player, '/') . ':\s*/iu', '', $input) ?? $input;
        return trim(mb_strcut($input, 0, 16384, 'UTF-8'));
    }

    /** Extract only bounded player/conversation text from an eligible CHIM request. */
    function chimOghmaInputText(array $gameRequest, $db): string
    {
        if (($gameRequest[0] ?? '') === 'rechat') {
            $lastChat = $db && method_exists($db, 'fetchOne')
                ? $db->fetchOne("SELECT data FROM eventlog WHERE type IN ('chat') ORDER BY gamets DESC LIMIT 1")
                : null;
            $input = is_array($lastChat) ? (string) ($lastChat['data'] ?? '') : '';
        } else {
            $input = (string) ($gameRequest[3] ?? '');
        }
        return chimOghmaSanitizeInputText($input);
    }

    /** Return at most the immediately preceding two distinct dialogue lines. */
    function chimOghmaPreviousExchangeText($db, string $currentInput): string
    {
        $npcName = trim((string) ($GLOBALS['HERIKA_NAME'] ?? ''));
        if (!$db || $npcName === '' || !method_exists($db, 'fetchAll') || !method_exists($db, 'escape')
            || !function_exists('chimBuildNpcEventLogPeopleWhereClause')) return '';
        try {
            $peopleWhere = chimBuildNpcEventLogPeopleWhereClause($db, $npcName);
            $rows = $db->fetchAll(
                "SELECT type, data FROM eventlog WHERE type IN ('inputtext','chat','rechat')"
                . " AND {$peopleWhere} ORDER BY rowid DESC LIMIT 6"
            );
        } catch (Throwable) {
            return '';
        }
        $currentKey = chimOghmaStrictEntityPhrase($currentInput);
        $seen = [];
        $parts = [];
        foreach ($rows as $row) {
            $text = chimOghmaSanitizeInputText((string) ($row['data'] ?? ''));
            $key = chimOghmaStrictEntityPhrase($text);
            if ($key === '' || $key === $currentKey || isset($seen[$key])) continue;
            $seen[$key] = true;
            $parts[] = $text;
            if (count($parts) >= 2) break;
        }
        return mb_strcut(implode("\n", array_reverse($parts)), 0, 4096, 'UTF-8');
    }
}

$db = $GLOBALS['db'] ?? ($db ?? null);
$request = is_array($gameRequest ?? null) ? $gameRequest : [];
$settings = chimOghmaEffectiveSettings();
$eligible = chimOghmaRequestEligible($request);
$inputText = $eligible ? chimOghmaInputText($request, $db) : '';
$status = !$settings['values']['enabled'] ? 'disabled' : ($eligible ? 'no_match' : 'ineligible');
$GLOBALS['OGHMA_PARITY_RESULT'] = chimOghmaNewResult($status, $settings, $eligible, $inputText);
$result =& $GLOBALS['OGHMA_PARITY_RESULT'];
$result['request_type'] = (string) ($request[0] ?? '');
$totalStarted = hrtime(true);

try {
    if ($db && method_exists($db, 'fetchOne')) {
        $activeCatalog = $db->fetchOne(
            "SELECT catalog_version, manifest_sha256 FROM public.oghma_catalogs WHERE state = 'active'"
        );
        if (is_array($activeCatalog) && $activeCatalog !== []) {
            $result['catalog'] = [
                'version' => (string) ($activeCatalog['catalog_version'] ?? ''),
                'manifest_sha256' => (string) ($activeCatalog['manifest_sha256'] ?? ''),
            ];
        }
    }
} catch (Throwable $error) {
    $result['catalog']['status'] = 'unavailable';
}

try {
if ($settings['values']['enabled'] && $eligible) {
$knowledgeTags = chimOghmaKnowledgeValues($GLOBALS['OGHMA_KNOWLEDGE'] ?? 'common');
    $characterName = strtolower(trim((string) ($GLOBALS['HERIKA_NAME'] ?? '')));
    if ($characterName !== '' && !in_array($characterName, $knowledgeTags, true)) $knowledgeTags[] = $characterName;

    $retrievalStarted = hrtime(true);
    $extraction = chimOghmaExtractEntities($db, $inputText, $settings['values']['topic_count']);
    $previousExchange = '';
    $contextFallback = [
        'eligible' => $extraction['entities'] === [] && chimOghmaShouldUsePreviousExchange($inputText),
        'attempted' => false,
        'used' => false,
    ];
    if ($contextFallback['eligible']) {
        $previousExchange = chimOghmaPreviousExchangeText($db, $inputText);
        if ($previousExchange !== '') {
            $contextFallback['attempted'] = true;
            $previousExtraction = chimOghmaExtractEntities($db, $previousExchange, 1);
            if ($previousExtraction['entities'] !== []) {
                foreach ($previousExtraction['entities'] as &$entity) $entity['context_source'] = 'previous_exchange';
                unset($entity);
                $extraction = $previousExtraction;
                $contextFallback['used'] = true;
            }
        }
    }
    $result['timing']['retrieval_ms'] = (hrtime(true) - $retrievalStarted) / 1_000_000;
    $result['context_fallback'] = $contextFallback;
    $result['matches'] = array_values($extraction['entities'] ?? []);
    $result['topics'] = array_values(array_unique(array_filter(array_map(
        static fn(array $entity): string => trim((string) ($entity['topic'] ?? '')),
        $result['matches']
    ))));
    $result['rejected'] = array_values($extraction['rejected'] ?? []);
    $result['tag_decisions'] = array_values($extraction['tag_decisions'] ?? []);
    $result['fallback']['eligible'] = ($extraction['fallback_eligible'] ?? false) === true;
    if ($result['topics'] !== []) $result['status'] = 'grounded';

    if ($result['topics'] === []
        && $result['fallback']['eligible']
        && $settings['values']['extractor_fallback_enabled']
        && $settings['values']['connector_id'] !== '') {
        $result['fallback']['attempted'] = true;
        $result['fallback']['status'] = 'fallback_failed';
        $fallbackStarted = hrtime(true);
        try {
            require_once __DIR__ . '/../lib/oghma_llm_service.php';
            $language = trim((string) ($GLOBALS['CORE_LANG'] ?? 'en')) ?: 'en';
            $fallbackText = $contextFallback['attempted'] && $previousExchange !== ''
                ? "Previous exchange:\n{$previousExchange}\nCurrent follow-up:\n{$inputText}"
                : $inputText;
            $response = LLMTopic($fallbackText, $language);
            $decoded = is_string($response) ? json_decode($response, true) : null;
            $suggestion = is_array($decoded) ? trim((string) ($decoded['generated_tags'] ?? '')) : '';
            if ($suggestion !== '') $result['fallback']['suggestions'][] = $suggestion;
            $resolved = $suggestion === '' ? null : chimOghmaResolveTopicName($db, $suggestion);
            if ($resolved !== null) {
                $result['topics'] = [$resolved];
                $result['fallback']['status'] = 'fallback_succeeded';
                $result['status'] = 'fallback_succeeded';
            } else {
                $result['fallback']['status'] = 'fallback_unresolved';
                $result['status'] = 'fallback_unresolved';
            }
        } catch (Throwable $error) {
            $result['fallback']['error'] = 'provider_unavailable';
            $result['status'] = 'fallback_failed';
        }
        $result['timing']['fallback_ms'] = (hrtime(true) - $fallbackStarted) / 1_000_000;
    } elseif ($result['topics'] === [] && $result['fallback']['eligible']) {
        $result['fallback']['status'] = $settings['values']['extractor_fallback_enabled']
            ? 'fallback_unconfigured'
            : 'fallback_disabled';
        $result['status'] = $result['fallback']['status'];
    }

    foreach (array_slice($result['topics'], 0, $settings['values']['topic_count']) as $topic) {
        $row = chimOghmaFetchTopic($db, $topic);
        if (!is_array($row)) {
            $result['rejected'][] = ['topic' => $topic, 'reason' => 'catalog_row_missing'];
            continue;
        }
        chimOghmaAddPromptArticle($row, $knowledgeTags, 'conversation', true);
    }

    if (count($result['articles']) < $settings['values']['result_limit']) {
        $forcedNpcMaster = isset($npcMaster) && $npcMaster instanceof NpcMaster
            ? $npcMaster
            : (class_exists('NpcMaster') ? new NpcMaster() : null);
        chimOghmaInjectForcedContext($db, $forcedNpcMaster);
    }

    if ($result['topics'] !== []) {
        try {
            $db->upsertRowOnConflict('conf_opts', ['id' => 'current_oghma_topic', 'value' => $result['topics'][0]], 'id');
        } catch (Throwable $error) {
            error_log('[OGHMA] Could not update current topic: ' . $error->getMessage());
        }
    }
}
} catch (Throwable $error) {
    $result['status'] = 'unavailable';
    $result['error'] = 'retrieval_unavailable';
    error_log('[OGHMA] Retrieval failed without blocking the game response: ' . $error->getMessage());
}

if ($result['status'] === 'no_match' && $result['articles'] !== []) {
    $result['status'] = 'grounded';
}

$result['timing']['total_ms'] = (hrtime(true) - $totalStarted) / 1_000_000;
$GLOBALS['OGHMA_HINT'] = chimOghmaRenderKnowledgeFragment($result['articles'], $result['status']);
$result['prompt_sha256'] = $GLOBALS['OGHMA_HINT'] === '' ? null : hash('sha256', $GLOBALS['OGHMA_HINT']);
chimOghmaRecordAudit($db, $result);

error_log('[OGHMA] contract=' . CHIM_OGHMA_PARITY_VERSION
    . ' status=' . $result['status']
    . ' topics=' . implode(',', $result['topics'])
    . ' articles=' . count($result['articles'])
    . ' retrieval_ms=' . number_format((float) $result['timing']['retrieval_ms'], 3, '.', ''));
