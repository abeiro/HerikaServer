<?php

require_once __DIR__ . '/../lib/vr_items.php';

// Functions to be provided to OpenAI
$startTime=$GLOBALS["startTime"] ?? microtime(true);

if (!function_exists('chimTraceFunctionsIncludePhase')) {
    function chimTraceFunctionsIncludePhase($line, $label, $startTime)
    {
        // error_log("TRACE:\t{$line}\t".__FILE__.":\t".(microtime(true) - $startTime)."\t{$label}");
    }
}

error_log("TRACE:\t".__LINE__. "\t".__FILE__.":\t".(microtime(true) - $startTime));

$ENABLED_FUNCTIONS_LOCAL = [
    'MoveTo',
    'OpenInventory',
    'OpenInventory2',
    'Attack',
    'Follow',
    'Inspect',
    'InspectSurroundings',
    'CheckInventory',
    'SheatheWeapon',
    'Relax',
    'TakeASeat',
    'ReadQuestJournal',
    'Surrender',
    'IncreaseWalkSpeed',
    'DecreaseWalkSpeed',
    'StopWalk',
    'TravelTo',
    'FollowPlayer',
    'ComeCloser',
    'Brawl',
    'ReturnBackHome',
    'GiveGoldTo',
    'GiveItemTo',
    'PickupItem',
    'CastSpell',
    'GoToSleep',
    'UseSoulGaze',
    'MakeFollower',
    'Toast',
    'Drink',
    'Consume',
    'StartRitualCeremony',
    'EndRitualCeremony',
    'Training',
    'RentRoom',
    'HireCarriage',
    'HireFerry',
    'SpawnItem',
    'SpawnGold',
    'SpawnNPC',
    'CreateNewNPC',
    'DirectorCommand',
    'TeleportNPC',
    'AddBounty',
    'PayBounty',
    'ArrestPlayer',
    'ForgiveCrime',
    'EndConversation'
    //    'WaitHere'
];

$GLOBALS["ENABLED_FUNCTIONS"] = $ENABLED_FUNCTIONS_LOCAL;

chimTraceFunctionsIncludePhase(__LINE__, 'enabled_functions_initialized', $startTime);

// Ensure PLAYER_NAME is defined before use in string templates below.
// Prefer database (conf_opts) value; fallback to existing global or 'Player'.
if (!isset($GLOBALS["PLAYER_NAME"]) || $GLOBALS["PLAYER_NAME"] === '') {
    $safePlayerName = 'Player';
    try {
        $rootPath = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
        chimTraceFunctionsIncludePhase(__LINE__, 'player_name_bootstrap_require_start', $startTime);
        require_once $rootPath . "lib" . DIRECTORY_SEPARATOR . "runtime_bootstrap.php";
        chimTraceFunctionsIncludePhase(__LINE__, 'player_name_bootstrap_require_done', $startTime);
        chimTraceFunctionsIncludePhase(__LINE__, 'player_name_bootstrap_run_start', $startTime);
        chimRuntimeBootstrapIfNeeded($rootPath, [
            'run_db_updates' => false,
            'load_general_settings' => false,
            'load_stt_connector' => false,
            'load_itt_connector' => false,
            'load_player_name' => true,
        ]);
        chimTraceFunctionsIncludePhase(__LINE__, 'player_name_bootstrap_run_done', $startTime);
        if (isset($GLOBALS["PLAYER_NAME"]) && $GLOBALS["PLAYER_NAME"] !== '') {
            $safePlayerName = (string)$GLOBALS["PLAYER_NAME"];
        }
    } catch (Throwable $_) {
        // ignore and use fallback
    }
    $GLOBALS["PLAYER_NAME"] = $safePlayerName;
}

chimTraceFunctionsIncludePhase(__LINE__, 'player_name_ready', $startTime);

require_once __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "action_catalog.php";

chimTraceFunctionsIncludePhase(__LINE__, 'action_catalog_required', $startTime);

function decodeFunctionExecutionParameterPayload($parameter)
{
    if (is_array($parameter)) {
        return $parameter;
    }

    $text = trim(strval($parameter));
    if ($text === '' || $text[0] !== '{') {
        return null;
    }

    $decoded = json_decode($text, true);
    return is_array($decoded) ? $decoded : null;
}

function buildTravelExecutionParameter($parameter, $amount)
{
    $payload = decodeFunctionExecutionParameterPayload($parameter);
    if (!is_array($payload)) {
        $payload = [];
    }

    if (!isset($payload["target"]) || trim(strval($payload["target"])) === "") {
        $payload["target"] = is_array($parameter) ? "" : trim(strval($parameter));
    }

    $payload["amount"] = intval($amount);

    return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function buildConfiguredActionParameterFromMetadata($functionCodeName, $parameter)
{
    if (!function_exists('herikaGetActionCatalogRow') || !function_exists('herikaActionCatalogResolveTemplateValue')) {
        return null;
    }

    $row = herikaGetActionCatalogRow($functionCodeName);
    if (!is_array($row)) {
        return null;
    }

    $metadata = herikaActionCatalogDecodeJson($row['metadata'] ?? [], []);
    $parameterTemplate = $metadata['parameter_template'] ?? null;
    if ($parameterTemplate === null || $parameterTemplate === '') {
        return null;
    }

    $parameterData = decodeFunctionExecutionParameterPayload($parameter);
    if (!is_array($parameterData)) {
        $parameterData = [];
    }

    $parameterTarget = strval($parameterData['target'] ?? (is_array($parameter) ? '' : trim(strval($parameter))));
    $context = [
        'action_name' => $functionCodeName,
        'parameter_raw' => is_array($parameter)
            ? json_encode($parameter, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : strval($parameter),
        'parameter_target' => $parameterTarget,
        'parameters' => $parameterData,
        'config' => function_exists('herikaActionCatalogGetResolvedCustomConfig')
            ? herikaActionCatalogGetResolvedCustomConfig($functionCodeName, $row)
            : [],
    ];

    $resolved = herikaActionCatalogResolveTemplateValue($parameterTemplate, $context);
    if (is_array($resolved)) {
        return json_encode($resolved, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    if ($resolved === null) {
        return '';
    }

    return is_string($resolved)
        ? $resolved
        : json_encode($resolved, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function herikaNormalizePositiveActionAmount($rawAmount, $defaultAmount = 1, $maxAmount = 1000)
{
    $amount = intval($rawAmount);
    if ($amount <= 0) {
        $amount = intval($defaultAmount);
    }

    if ($amount <= 0) {
        $amount = 1;
    }

    if ($amount > intval($maxAmount)) {
        $amount = intval($maxAmount);
    }

    return $amount;
}

function herikaResolveSpawnItemBaseIdToRuntimeFormId($baseId)
{
    $baseId = trim(strval($baseId));
    if ($baseId === '') {
        return null;
    }

    $parsedStableReference = function_exists('chimParseStableFormReference')
        ? chimParseStableFormReference($baseId)
        : null;
    if ($parsedStableReference) {
        $runtimeFormId = function_exists('chimResolveStableFormReferenceToRuntimeFormId')
            ? chimResolveStableFormReferenceToRuntimeFormId($parsedStableReference['stable_key'])
            : null;
        $runtimeFormId = trim(strval($runtimeFormId));
        return $runtimeFormId !== '' ? strtoupper($runtimeFormId) : null;
    }

    $upperBaseId = strtoupper($baseId);
    if (strpos($upperBaseId, 'XX') === 0 || strpos($upperBaseId, 'FEXXX') === 0) {
        return null;
    }

    $runtimeFormId = function_exists('chimNormalizeRuntimeFormId')
        ? chimNormalizeRuntimeFormId($baseId)
        : '';
    $runtimeFormId = trim(strval($runtimeFormId));

    return $runtimeFormId !== '' ? strtoupper($runtimeFormId) : null;
}

function herikaResolveSpawnItemDescriptionMatch($requestedItemName)
{
    if (!isset($GLOBALS["db"])) {
        return ['ok' => false, 'error' => 'database_unavailable'];
    }

    $requestedItemName = trim(strval($requestedItemName));
    if ($requestedItemName === '') {
        return ['ok' => false, 'error' => 'missing_item_name'];
    }

    $escapedName = $GLOBALS["db"]->escape($requestedItemName);
    $buildCandidates = function ($rows, $reason,) use ($requestedItemName) {
        $candidates = [];
        foreach ((array) $rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $baseId = trim(strval($row['baseid'] ?? ''));
            $plugin = trim(strval($row['plugin'] ?? ''));
            $lookupBaseId = ($plugin !== '' && $baseId !== '')
                ? chimBuildStableFormReference($plugin, $baseId)
                : $baseId;
            $runtimeFormId = herikaResolveSpawnItemBaseIdToRuntimeFormId($lookupBaseId);
            if ($runtimeFormId === null) {
                continue;
            }

            $candidate = [
                'baseid' => $baseId,
                'plugin' => $plugin,
                'runtime_formid' => $runtimeFormId,
                'name' => trim(strval($row['name'] ?? $requestedItemName)),
                'description' => trim(strval($row['description'] ?? '')),
                'match_reason' => $reason,
                'similarity' => isset($row['sim']) ? floatval($row['sim']) : 1.0,
            ];

            $candidateKey = strtoupper($runtimeFormId) . '|' . strtolower($candidate['name']);
            $candidates[$candidateKey] = $candidate;
        }

        return array_values($candidates);
    };

    $selectPreferredCandidate = function (array $candidates) use ($requestedItemName) {
        if (count($candidates) === 0) {
            return null;
        }

        $requestedLower = strtolower(trim(strval($requestedItemName)));
        usort($candidates, function ($left, $right) use ($requestedLower) {
            $leftName = strtolower(trim(strval($left['name'] ?? '')));
            $rightName = strtolower(trim(strval($right['name'] ?? '')));

            $leftExact = ($leftName === $requestedLower) ? 1 : 0;
            $rightExact = ($rightName === $requestedLower) ? 1 : 0;
            if ($leftExact !== $rightExact) {
                return ($leftExact > $rightExact) ? -1 : 1;
            }

            $leftRuntime = strtoupper(trim(strval($left['runtime_formid'] ?? '')));
            $rightRuntime = strtoupper(trim(strval($right['runtime_formid'] ?? '')));

            $leftVanillaLike = preg_match('/^00[0-9A-F]{6}$/', $leftRuntime) ? 1 : 0;
            $rightVanillaLike = preg_match('/^00[0-9A-F]{6}$/', $rightRuntime) ? 1 : 0;
            if ($leftVanillaLike !== $rightVanillaLike) {
                return ($leftVanillaLike > $rightVanillaLike) ? -1 : 1;
            }

            if ($leftRuntime !== $rightRuntime) {
                return strcmp($leftRuntime, $rightRuntime);
            }

            return strcmp($leftName, $rightName);
        });

        return $candidates[0];
    };

    $exactRows = $GLOBALS["db"]->fetchAll("
        SELECT plugin, baseid, name, description
        FROM combined_descriptions
        WHERE LOWER(name) = LOWER('{$escapedName}')
        ORDER BY plugin ASC, baseid ASC
        LIMIT 12
    ");
    $exactCandidates = $buildCandidates($exactRows, 'exact_name');
    if (count($exactCandidates) === 1) {
        return ['ok' => true] + $exactCandidates[0];
    }
    if (count($exactCandidates) > 1) {
        $preferredExactCandidate = $selectPreferredCandidate($exactCandidates);
        if ($preferredExactCandidate !== null) {
            $preferredExactCandidate['match_reason'] = 'preferred_exact_name';
            $preferredExactCandidate['duplicate_count'] = count($exactCandidates);
            return ['ok' => true] + $preferredExactCandidate;
        }

        return ['ok' => false, 'error' => 'ambiguous_exact_name', 'candidates' => $exactCandidates];
    }

    $similarityRows = $GLOBALS["db"]->fetchAll("
        SELECT plugin, baseid, name, description, similarity(name, '{$escapedName}') AS sim
        FROM combined_descriptions
        WHERE similarity(name, '{$escapedName}') > 0.55
        ORDER BY sim DESC, name ASC, plugin ASC, baseid ASC
        LIMIT 8
    ");
    $similarityCandidates = $buildCandidates($similarityRows, 'similar_name');
    if (count($similarityCandidates) === 0) {
        return ['ok' => false, 'error' => 'no_spawn_safe_match'];
    }

    usort($similarityCandidates, function ($left, $right) {
        $leftSimilarity = floatval($left['similarity'] ?? 0);
        $rightSimilarity = floatval($right['similarity'] ?? 0);
        if ($leftSimilarity === $rightSimilarity) {
            return strcmp(strtolower(strval($left['name'] ?? '')), strtolower(strval($right['name'] ?? '')));
        }
        return ($leftSimilarity > $rightSimilarity) ? -1 : 1;
    });

    $bestCandidate = $similarityCandidates[0];
    $bestSimilarity = floatval($bestCandidate['similarity'] ?? 0);
    $runnerUpSimilarity = isset($similarityCandidates[1]) ? floatval($similarityCandidates[1]['similarity'] ?? 0) : 0.0;

    if ($bestSimilarity < 0.72) {
        return ['ok' => false, 'error' => 'low_confidence_match', 'candidates' => $similarityCandidates];
    }

    if (isset($similarityCandidates[1]) && ($bestSimilarity - $runnerUpSimilarity) < 0.05) {
        return ['ok' => false, 'error' => 'ambiguous_similar_name', 'candidates' => $similarityCandidates];
    }

    return ['ok' => true] + $bestCandidate;
}

function herikaNormalizeSpawnNpcTemplateKey($value)
{
    $value = strtolower(trim(strval($value)));
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^a-z0-9]+/u', '_', $value);
    $value = preg_replace('/_+/u', '_', $value);
    $value = trim(strval($value), '_');
    $value = preg_replace('/^(actor|creature|npc)_+/u', '', $value);
    $value = preg_replace('/^(actor|creature|npc)(?=[a-z0-9])/u', '', $value);
    return trim(strval($value), '_');
}

function herikaGetSpawnNpcTemplateGenderVariant($normalizedKey)
{
    $normalizedKey = herikaNormalizeSpawnNpcTemplateKey($normalizedKey);
    if (strpos($normalizedKey, 'male_') === 0) {
        return 'male';
    }

    if (strpos($normalizedKey, 'female_') === 0) {
        return 'female';
    }

    return '';
}

function herikaStripSpawnNpcTemplateGenderPrefix($normalizedKey)
{
    $normalizedKey = herikaNormalizeSpawnNpcTemplateKey($normalizedKey);
    if (strpos($normalizedKey, 'male_') === 0) {
        return substr($normalizedKey, 5);
    }

    if (strpos($normalizedKey, 'female_') === 0) {
        return substr($normalizedKey, 7);
    }

    return $normalizedKey;
}

function herikaTokenizeSpawnNpcTemplateKey($normalizedKey)
{
    $normalizedKey = herikaNormalizeSpawnNpcTemplateKey($normalizedKey);
    if ($normalizedKey === '') {
        return [];
    }

    $tokens = preg_split('/_+/u', $normalizedKey);
    $tokens = array_values(array_filter(array_map('trim', (array) $tokens), function ($token) {
        return $token !== '';
    }));

    return array_values(array_unique($tokens));
}

function herikaDetectSpawnNpcGenderHint($text)
{
    $normalizedText = herikaNormalizeSpawnNpcTemplateKey($text);
    if ($normalizedText === '') {
        return '';
    }

    $tokens = herikaTokenizeSpawnNpcTemplateKey($normalizedText);
    if (in_array('female', $tokens, true) || strpos($normalizedText, 'female_') === 0) {
        return 'female';
    }

    if (in_array('male', $tokens, true) || strpos($normalizedText, 'male_') === 0) {
        return 'male';
    }

    return '';
}

function herikaGetSpawnNpcTemplateKeys()
{
    if (!function_exists('quest_reference_active_keys')) {
        require_once(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'quest_reference_data.php');
    }

    $keys = [];
    foreach (['npc_own_templates', 'npc_templates'] as $datasetName) {
        foreach ((array) quest_reference_active_keys($datasetName) as $templateKey) {
            $templateKey = trim(strval($templateKey));
            if ($templateKey === '') {
                continue;
            }
            $keys[$templateKey] = true;
        }
    }

    $result = array_keys($keys);
    natcasesort($result);
    return array_values($result);
}

function herikaResolveSpawnNpcTemplateMatch($requestedTemplateKey, $contextText = '')
{
    if (!function_exists('quest_reference_load_dataset')) {
        require_once(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'quest_reference_data.php');
    }

    $requestedTemplateKey = trim(strval($requestedTemplateKey));
    if ($requestedTemplateKey === '') {
        return ['ok' => false, 'error' => 'missing_template_key'];
    }

    $normalizedRequestedKey = herikaNormalizeSpawnNpcTemplateKey($requestedTemplateKey);
    if ($normalizedRequestedKey === '') {
        return ['ok' => false, 'error' => 'missing_template_key'];
    }

    $requestedAliasKey = herikaStripSpawnNpcTemplateGenderPrefix($normalizedRequestedKey);
    $requestedTokens = herikaTokenizeSpawnNpcTemplateKey($normalizedRequestedKey);
    $requestedAliasTokens = herikaTokenizeSpawnNpcTemplateKey($requestedAliasKey);
    $genderHint = herikaDetectSpawnNpcGenderHint($contextText);
    if ($genderHint === '') {
        $genderHint = herikaGetSpawnNpcTemplateGenderVariant($normalizedRequestedKey);
    }

    $datasets = [
        'npc_own_templates' => (array) quest_reference_load_dataset('npc_own_templates', true),
        'npc_templates' => (array) quest_reference_load_dataset('npc_templates', true),
    ];

    $exactCandidate = null;
    $fallbackCandidates = [];

    foreach ($datasets as $datasetName => $datasetRows) {
        foreach ($datasetRows as $templateKey => $formIds) {
            $templateKey = trim(strval($templateKey));
            if ($templateKey === '' || !is_array($formIds) || count($formIds) === 0) {
                continue;
            }

            $normalizedTemplateKey = herikaNormalizeSpawnNpcTemplateKey($templateKey);
            if ($normalizedTemplateKey === '') {
                continue;
            }

            $runtimeFormIds = [];
            foreach ($formIds as $formId) {
                $normalizedFormId = function_exists('quest_reference_normalize_formid')
                    ? quest_reference_normalize_formid($formId)
                    : null;
                if ($normalizedFormId === null || intval($normalizedFormId) < 0) {
                    continue;
                }
                $runtimeFormIds[] = intval($normalizedFormId);
            }

            $runtimeFormIds = array_values(array_unique($runtimeFormIds));
            if (count($runtimeFormIds) === 0) {
                continue;
            }

            $candidate = [
                'dataset' => $datasetName,
                'template_key' => $templateKey,
                'normalized_key' => $normalizedTemplateKey,
                'alias_key' => herikaStripSpawnNpcTemplateGenderPrefix($normalizedTemplateKey),
                'gender_variant' => herikaGetSpawnNpcTemplateGenderVariant($normalizedTemplateKey),
                'runtime_formids' => $runtimeFormIds,
                'runtime_formid' => sprintf('%08X', $runtimeFormIds[0] & 0xFFFFFFFF),
                'match_reason' => '',
                'score' => 0,
            ];

            $isOwnTemplate = ($datasetName === 'npc_own_templates');
            if ($normalizedTemplateKey === $normalizedRequestedKey) {
                $candidate['match_reason'] = 'exact_key';
                $candidate['score'] = $isOwnTemplate ? 1000 : 950;
                if ($exactCandidate === null || $candidate['score'] > $exactCandidate['score']) {
                    $exactCandidate = $candidate;
                }
                continue;
            }

            $score = 0;
            $reason = '';

            $aliasKey = strval($candidate['alias_key']);
            $aliasTokens = herikaTokenizeSpawnNpcTemplateKey($aliasKey);
            $candidateTokens = herikaTokenizeSpawnNpcTemplateKey($normalizedTemplateKey);

            $matchedRequestedTokenCount = 0;
            foreach ($requestedAliasTokens as $token) {
                if (in_array($token, $aliasTokens, true) || in_array($token, $candidateTokens, true)) {
                    $matchedRequestedTokenCount++;
                }
            }

            if ($aliasKey !== '' && $aliasKey === $requestedAliasKey) {
                $score = $isOwnTemplate ? 980 : 970;
                $reason = 'exact_alias';
            } elseif (
                count($requestedAliasTokens) > 0
                && $matchedRequestedTokenCount === count($requestedAliasTokens)
            ) {
                $score = $isOwnTemplate ? 910 : 870;
                $reason = 'token_subset';
            } elseif ($matchedRequestedTokenCount > 0) {
                $score = ($isOwnTemplate ? 860 : 820) + min(30, $matchedRequestedTokenCount * 10);
                $reason = 'token_overlap';
            } elseif (strpos($normalizedTemplateKey, $normalizedRequestedKey) !== false || strpos($normalizedRequestedKey, $normalizedTemplateKey) !== false) {
                $score = $isOwnTemplate ? 860 : 820;
                $reason = 'partial_key';
            } elseif ($aliasKey !== '' && (strpos($aliasKey, $requestedAliasKey) !== false || strpos($requestedAliasKey, $aliasKey) !== false)) {
                $score = $isOwnTemplate ? 850 : 810;
                $reason = 'partial_alias';
            } else {
                similar_text($normalizedTemplateKey, $normalizedRequestedKey, $similarityPercent);
                if ($similarityPercent >= 72.0) {
                    $score = intval(round($similarityPercent)) + ($isOwnTemplate ? 40 : 0);
                    $reason = 'similar_key';
                }
            }

            if ($score <= 0) {
                continue;
            }

            if ($genderHint !== '' && strval($candidate['gender_variant']) === $genderHint) {
                $score += 20;
                $reason .= '_gender_hint';
            }

            $candidate['match_reason'] = $reason;
            $candidate['score'] = $score;
            $fallbackCandidates[] = $candidate;
        }
    }

    if (is_array($exactCandidate)) {
        return ['ok' => true] + $exactCandidate;
    }

    if (count($fallbackCandidates) === 0) {
        return ['ok' => false, 'error' => 'no_template_match'];
    }

    usort($fallbackCandidates, function ($left, $right) {
        $leftScore = intval($left['score'] ?? 0);
        $rightScore = intval($right['score'] ?? 0);
        if ($leftScore !== $rightScore) {
            return ($leftScore > $rightScore) ? -1 : 1;
        }

        $leftDataset = strval($left['dataset'] ?? '');
        $rightDataset = strval($right['dataset'] ?? '');
        if ($leftDataset !== $rightDataset) {
            return ($leftDataset === 'npc_own_templates') ? -1 : 1;
        }

        $leftGender = strval($left['gender_variant'] ?? '');
        $rightGender = strval($right['gender_variant'] ?? '');
        if ($leftGender !== $rightGender) {
            if ($leftGender === 'male') {
                return -1;
            }
            if ($rightGender === 'male') {
                return 1;
            }
        }

        return strcasecmp(strval($left['template_key'] ?? ''), strval($right['template_key'] ?? ''));
    });

    if (count($fallbackCandidates) > 1) {
        $bestScore = intval($fallbackCandidates[0]['score'] ?? 0);
        $runnerUpScore = intval($fallbackCandidates[1]['score'] ?? 0);
        if (($bestScore - $runnerUpScore) < 5) {
            $bestAlias = strval($fallbackCandidates[0]['alias_key'] ?? '');
            $runnerUpAlias = strval($fallbackCandidates[1]['alias_key'] ?? '');
            $bestFormId = strval($fallbackCandidates[0]['runtime_formid'] ?? '');
            $runnerUpFormId = strval($fallbackCandidates[1]['runtime_formid'] ?? '');
            if (
                ($bestAlias !== '' && $bestAlias === $runnerUpAlias)
                || ($bestFormId !== '' && $bestFormId === $runnerUpFormId)
            ) {
                return ['ok' => true] + $fallbackCandidates[0];
            }
            return ['ok' => false, 'error' => 'ambiguous_template_match', 'candidates' => array_slice($fallbackCandidates, 0, 5)];
        }
    }

    return ['ok' => true] + $fallbackCandidates[0];
}

function herikaNormalizeNarratorActorTargetForRoleCommand($targetName, $defaultToPlayer = true)
{
    $targetName = trim(strval($targetName));
    $playerName = trim(strval($GLOBALS["PLAYER_NAME"] ?? "Player"));
    $narratorName = trim(strval($GLOBALS["HERIKA_NAME"] ?? ""));
    $normalizedTarget = strtolower($targetName);

    if ($targetName === '') {
        return $defaultToPlayer ? 'PLAYER' : '';
    }

    if ($normalizedTarget === 'player' || $normalizedTarget === 'me' || $normalizedTarget === 'the narrator' || $normalizedTarget === 'narrator') {
        return 'PLAYER';
    }

    if ($playerName !== '' && strcasecmp($targetName, $playerName) === 0) {
        return 'PLAYER';
    }

    if ($narratorName !== '' && strcasecmp($targetName, $narratorName) === 0) {
        return 'PLAYER';
    }

    return $targetName;
}

function herikaNormalizeNarratorActorTargetInferenceText($text)
{
    $text = trim(strval($text));
    if ($text === '') {
        return '';
    }

    $text = preg_replace('/\([^)]+\)/u', ' ', $text);
    $text = preg_replace('/[^[:alnum:]\s\'#_-]+/u', ' ', $text);
    $text = strtolower(trim(preg_replace('/\s+/u', ' ', $text)));

    return $text;
}

function herikaGetNarratorActorTargetCandidates($peoplePipe = '')
{
    $candidatePipes = [];
    $peoplePipe = trim(strval($peoplePipe));
    if ($peoplePipe !== '') {
        $candidatePipes[] = $peoplePipe;
    }

    $cachePeople = trim(strval($GLOBALS["CACHE_PEOPLE"] ?? ''));
    if ($cachePeople !== '') {
        $candidatePipes[] = $cachePeople;
    }

    if (isset($GLOBALS["db"])) {
        $latestPeopleRows = $GLOBALS["db"]->fetchAll("
            SELECT people
            FROM public.eventlog
            WHERE type IN ('infonpc', 'infonpc_close')
              AND COALESCE(people, '') <> ''
            ORDER BY gamets DESC, ts DESC
            LIMIT 3
        ");
        if (is_array($latestPeopleRows)) {
            foreach ($latestPeopleRows as $row) {
                $rowPeople = trim(strval($row['people'] ?? ''));
                if ($rowPeople !== '') {
                    $candidatePipes[] = $rowPeople;
                }
            }
        }
    }

    if (empty($candidatePipes) && function_exists('DataBeingsInCloseRange')) {
        $candidatePipes[] = trim(strval(DataBeingsInCloseRange(true)));
    }

    $uniqueCandidates = [];
    foreach ($candidatePipes as $candidatePipe) {
        $tokens = array_values(array_filter(array_map('trim', explode('|', strval($candidatePipe)))));
        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }

            $baseToken = trim(preg_replace('/\s*\([^)]+\)\s*/u', '', $token));
            foreach (array_unique([$token, $baseToken]) as $candidate) {
                $candidate = trim(strval($candidate));
                if ($candidate === '') {
                    continue;
                }
                $normalizedCandidate = herikaNormalizeNarratorActorTargetInferenceText($candidate);
                if ($normalizedCandidate === '') {
                    continue;
                }
                $uniqueCandidates[$candidate] = $normalizedCandidate;
            }
        }
    }

    return $uniqueCandidates;
}

function herikaGetLatestNarratorInputText()
{
    if (!isset($GLOBALS["db"])) {
        return '';
    }

    $rows = $GLOBALS["db"]->fetchAll("
        SELECT data
        FROM public.eventlog
        WHERE type = 'narrator_inputtext'
          AND COALESCE(data, '') <> ''
        ORDER BY gamets DESC, ts DESC
        LIMIT 1
    ");

    if (!is_array($rows) || empty($rows[0])) {
        return '';
    }

    $latestInput = trim(strval($rows[0]['data'] ?? ''));
    if ($latestInput === '') {
        return '';
    }

    return preg_replace('/^[^:]+:\s*/u', '', $latestInput) ?? $latestInput;
}

function herikaQueueNarratorCreateNewNpc($creationBrief)
{
    $creationBrief = trim(strval($creationBrief));
    if ($creationBrief === '') {
        error_log("[ACTION POSTFILTER CreateNewNPC] Missing creation brief before spawn helper");
        return false;
    }

    return herikaInvokeRolemasterCliCommand('spawn', $creationBrief, 'CreateNewNPC');
}

function herikaInvokeRolemasterCliCommand($subcommand, $directive, $logLabel = 'Rolemaster')
{
    $subcommand = trim(strval($subcommand));
    $directive = trim(strval($directive));
    $logLabel = trim(strval($logLabel));
    if ($logLabel === '') {
        $logLabel = 'Rolemaster';
    }

    if ($subcommand === '') {
        error_log("[ACTION POSTFILTER {$logLabel}] Missing rolemaster subcommand");
        return false;
    }

    if ($directive === '') {
        error_log("[ACTION POSTFILTER {$logLabel}] Missing directive before rolemaster helper");
        return false;
    }

    $managerPath = realpath(__DIR__ . '/../service/manager.php');
    if ($managerPath === false || !file_exists($managerPath)) {
        error_log("[ACTION POSTFILTER {$logLabel}] Could not resolve manager path from " . __DIR__);
        return false;
    }

    $phpBinaryCandidates = [];
    $phpBindir = rtrim(strval(PHP_BINDIR ?? ''), "/\\");
    if ($phpBindir !== '') {
        $phpBinaryCandidates[] = $phpBindir . DIRECTORY_SEPARATOR . (stripos(PHP_OS, 'WIN') === 0 ? 'php.exe' : 'php');
    }

    $phpBinary = trim(strval(PHP_BINARY ?? ''));
    if ($phpBinary !== '') {
        $phpBinaryBase = strtolower(basename(str_replace('\\', '/', $phpBinary)));
        if (strpos($phpBinaryBase, 'php') === 0) {
            $phpBinaryCandidates[] = $phpBinary;
        }
    }

    if (!in_array('php', $phpBinaryCandidates, true)) {
        $phpBinaryCandidates[] = 'php';
    }

    $escapedManagerPath = escapeshellarg($managerPath);
    $escapedSubcommand = escapeshellarg($subcommand);
    $escapedDirective = escapeshellarg($directive);
    $attemptLogs = [];

    foreach (array_values(array_unique($phpBinaryCandidates)) as $phpBinaryCandidate) {
        $output = [];
        $returnCode = 0;
        $escapedPhpBinary = escapeshellarg($phpBinaryCandidate);
        exec("{$escapedPhpBinary} {$escapedManagerPath} rolemaster {$escapedSubcommand} {$escapedDirective} 2>&1", $output, $returnCode);
        if ($returnCode === 0) {
            return true;
        }

        $attemptLogs[] = "php={$phpBinaryCandidate} rc={$returnCode} output=" . implode(" || ", $output);
    }

    error_log("[ACTION POSTFILTER {$logLabel}] rolemaster {$subcommand} command failed manager={$managerPath} attempts=" . implode(" ### ", $attemptLogs));
    return false;
}

function herikaQueueNarratorDirectorCommand($directive)
{
    $directive = trim(strval($directive));
    if ($directive === '') {
        error_log("[ACTION POSTFILTER DirectorCommand] Missing directive before instruction helper");
        return false;
    }

    return herikaInvokeRolemasterCliCommand('instruction', $directive, 'DirectorCommand');
}

function herikaInferNarratorActorTargetFromText($sourceText, $peoplePipe = '')
{
    $normalizedSource = herikaNormalizeNarratorActorTargetInferenceText($sourceText);
    if ($normalizedSource === '') {
        return '';
    }

    $playerName = trim(strval($GLOBALS["PLAYER_NAME"] ?? 'Player'));
    $normalizedPlayerName = herikaNormalizeNarratorActorTargetInferenceText($playerName);
    $playerAliasMatched = preg_match('/\b(player|me|myself)\b/u', $normalizedSource) === 1
        || ($normalizedPlayerName !== '' && preg_match('/\b' . preg_quote($normalizedPlayerName, '/') . '\b/u', $normalizedSource) === 1);

    $bestCandidate = '';
    $bestScore = -1;
    foreach (herikaGetNarratorActorTargetCandidates($peoplePipe) as $candidate => $normalizedCandidate) {
        if ($normalizedCandidate === '') {
            continue;
        }

        if (preg_match('/\b' . preg_quote($normalizedCandidate, '/') . '\b/u', $normalizedSource) !== 1) {
            continue;
        }

        $score = strlen($normalizedCandidate);
        if ($normalizedCandidate === $normalizedSource) {
            $score += 1000;
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestCandidate = $candidate;
        }
    }

    if ($bestCandidate !== '') {
        return $bestCandidate;
    }

    return $playerAliasMatched ? 'PLAYER' : '';
}

function herikaExtractActionArgumentTargetValue($arguments)
{
    if (!is_array($arguments)) {
        return trim(strval($arguments));
    }

    foreach (['target', 'item', 'amount'] as $preferredKey) {
        if (array_key_exists($preferredKey, $arguments)) {
            $value = $arguments[$preferredKey];
            if (is_scalar($value) || $value === null) {
                return trim(strval($value));
            }
        }
    }

    $firstValue = reset($arguments);
    if (is_scalar($firstValue) || $firstValue === null) {
        return trim(strval($firstValue));
    }

    return '';
}

// We must use internal keys here.

$F_TRANSLATIONS_LOCAL["MoveTo"] = "Move to a visible nearby actor or NPC. Use TravelTo for places, buildings, cities, doors, or locations.";
$F_TRANSLATIONS_LOCAL["OpenInventory"] = "Initiates trading or exchange items with #PLAYER_NAME#.";
$F_TRANSLATIONS_LOCAL["OpenInventory2"] = "Initiates trading; #PLAYER_NAME# must give items to #HERIKA_NAME#.";
$F_TRANSLATIONS_LOCAL["Attack"] = "Attack with intention to kill a target actor or entity.";
$F_TRANSLATIONS_LOCAL["Follow"] = "Move to and follow the specified target actor";
$F_TRANSLATIONS_LOCAL["Inspect"] = "Inspect a nearby actor or being to get a closer read on their visible equipment, condition, and state.";
$F_TRANSLATIONS_LOCAL["InspectSurroundings"] = "Look around and assess who or what is nearby, including people, creatures, and possible threats.";
$F_TRANSLATIONS_LOCAL["CheckInventory"] = "Search in #HERIKA_NAME#'s inventory, backpack, or pocket. List their inventory contents.";
$F_TRANSLATIONS_LOCAL["SheatheWeapon"] = "Sheathes/put away current weapon";
$F_TRANSLATIONS_LOCAL["Relax"] = "Stop whatever you are doing and relax at the current location.Used to Unwind,Loosen Up,Enjoy Moment,Chill";
$F_TRANSLATIONS_LOCAL["TravelTo"] = "Travel long distance to a building, city, door or other location. Also known as lead the way.";
$F_TRANSLATIONS_LOCAL["TakeASeat"] = "#HERIKA_NAME# takes a seat at a nearby seating location.";
$F_TRANSLATIONS_LOCAL["ReadQuestJournal"] = "Only use if #PLAYER_NAME# explicitly asks about a quest. Read the quest journal and get information about current quests.";
$F_TRANSLATIONS_LOCAL["Surrender"] = "#HERIKA_NAME# yields, raises their hands, and stops resisting.";
$F_TRANSLATIONS_LOCAL["IncreaseWalkSpeed"] = "Increase #HERIKA_NAME#'s speed when moving or travelling.";
$F_TRANSLATIONS_LOCAL["DecreaseWalkSpeed"] = "Decrease #HERIKA_NAME#'s speed when moving or travelling.";
$F_TRANSLATIONS_LOCAL["StopWalk"] = "Stop all of #HERIKA_NAME#'s actions immediately.";
$F_TRANSLATIONS_LOCAL["TravelTo"] = "Travel long distance to a building, city, door or other location. Also known as lead the way.";
$F_TRANSLATIONS_LOCAL["WaitHere"] = "#HERIKA_NAME# waits and loiters at the current location.";
$F_TRANSLATIONS_LOCAL["TakeGoldFromPlayer"] = "#HERIKA_NAME# takes the amount in property target of gold from #PLAYER_NAME#, once #PLAYER_NAME# agrees. Infer the amount from context.";
$F_TRANSLATIONS_LOCAL["RentRoom"] = "#HERIKA_NAME# rents a room to #PLAYER_NAME# for {{config.cost_gold}} gold. Only innkeepers can use this action and it only applies to #PLAYER_NAME#.";
$F_TRANSLATIONS_LOCAL["HireCarriage"] = "#HERIKA_NAME# accepts {{config.cost_gold}} gold for carriage travel and transports #PLAYER_NAME# to the specified destination. Reply with one short acceptance line, do not ask follow-up questions, then end the conversation.";
$F_TRANSLATIONS_LOCAL["HireFerry"] = "#HERIKA_NAME# accepts {{config.cost_gold}} gold for ferry travel and transports #PLAYER_NAME# to the specified destination. Reply with one short acceptance line, do not ask follow-up questions, then end the conversation.";
$F_TRANSLATIONS_LOCAL["SpawnItem"] = "Create a named item from the descriptions database and give it to a target actor or #PLAYER_NAME#.";
$F_TRANSLATIONS_LOCAL["SpawnGold"] = "Create gold and give it to a target actor or #PLAYER_NAME#.";
$F_TRANSLATIONS_LOCAL["SpawnNPC"] = "Spawn one or more NPCs near #PLAYER_NAME# from the SNQE NPC template datasets. Put the template key in the target field and the spawn count in amount.";
$F_TRANSLATIONS_LOCAL["CreateNewNPC"] = "Create and spawn a brand-new nearby NPC from a short creation brief. Put the creation brief in the target field and leave item and amount blank.";
$F_TRANSLATIONS_LOCAL["DirectorCommand"] = "Issue a freeform director instruction for the server-side director mode to turn into scene actions. Put the director brief in the target field and leave item and amount blank.";
$F_TRANSLATIONS_LOCAL["TeleportNPC"] = "Teleport a chosen NPC, actor, or #PLAYER_NAME# to a named location from the location database.";
$F_TRANSLATIONS_LOCAL["KillTarget"] = "Kill a chosen NPC, actor, or #PLAYER_NAME# immediately.";
$F_TRANSLATIONS_LOCAL["AddBounty"] = "#HERIKA_NAME# adds a crime bounty to #PLAYER_NAME# for a witnessed or reported crime. Guard-only action.";
$F_TRANSLATIONS_LOCAL["PayBounty"] = "#PLAYER_NAME# pays off their bounty to #HERIKA_NAME#. Stolen items are confiscated and the matter is resolved immediately. Guard-only action.";
$F_TRANSLATIONS_LOCAL["ArrestPlayer"] = "#HERIKA_NAME# attempts to arrest #PLAYER_NAME#. #PLAYER_NAME# can submit or resist. Guard-only action for serious crimes or refusal to pay.";
$F_TRANSLATIONS_LOCAL["ForgiveCrime"] = "#HERIKA_NAME# forgives #PLAYER_NAME#'s crimes and clears their bounty. Guard-only action for persuasion, bribe, or thane status.";
$F_TRANSLATIONS_LOCAL["FollowPlayer"] = "#HERIKA_NAME# follows #PLAYER_NAME#.";
$F_TRANSLATIONS_LOCAL["ComeCloser"] = "#HERIKA_NAME# approaches #PLAYER_NAME#.";
$F_TRANSLATIONS_LOCAL["Brawl"] = "#HERIKA_NAME# engages in non-lethal combat with another actor, using weapons.";
$F_TRANSLATIONS_LOCAL["ReturnBackHome"] = "#HERIKA_NAME# travels to their home or place of origin. Returns home.";
$F_TRANSLATIONS_LOCAL["GiveGoldTo"] = "#HERIKA_NAME# gives gold, coins, or septims to another actor or #PLAYER_NAME#. REQUIRED: Must include 'target' field with recipient name and 'item' field with amount as a number string.";
$F_TRANSLATIONS_LOCAL["GiveItemTo"] = "#HERIKA_NAME# gives a specific item from inventory to another actor or #PLAYER_NAME#. REQUIRED: Must include 'item' field with exact item name from <inventory> tag, and 'target' field with recipient name.";
$F_TRANSLATIONS_LOCAL["PickupItem"] = "#HERIKA_NAME# picks up a specific item from the ground. Use the exact RefID:ItemName format from nearby_items or from the representative RefID shown in ITEM DESCRIPTIONS when the nearby item list is grouped (e.g. 0x12345:Iron Sword).";
$F_TRANSLATIONS_LOCAL["GoToSleep"] = "#HERIKA_NAME# takes a nap.";
$F_TRANSLATIONS_LOCAL["UseSoulGaze"] = "Use the spell SoulGaze, a powerful incantation that allows #HERIKA_NAME# to perceive surroundings in vivid detail through #PLAYER_NAME#'s eyes. The spell, however, causes some disturbance to the caster.";
$F_TRANSLATIONS_LOCAL["CastSpell"] = "#HERIKA_NAME# casts a spell on a target actor. Must specify spell name from <spells> and target actor name. Use 'self' as target for self-targeted spells.";
$F_TRANSLATIONS_LOCAL["MakeFollower"] = "#HERIKA_NAME# joins #PLAYER_NAME#, forming a squad or adventuring party.";

$F_TRANSLATIONS_LOCAL["Toast"] = "Raises a glass in celebration or honor.";
$F_TRANSLATIONS_LOCAL["Drink"] = "Drinks a beverage to quench thirst or enjoy flavor.";
$F_TRANSLATIONS_LOCAL["Consume"] = "#HERIKA_NAME# consumes a food, drink, or potion from inventory. Use the exact BaseID:ItemName inventory identifier in the target field.";
$F_TRANSLATIONS_LOCAL["StartRitualCeremony"] = "Participates in a ritual or ceremony, following its customs and practices.";
$F_TRANSLATIONS_LOCAL["EndRitualCeremony"] = "Concludes a ritual or ceremony, marking its completion.";
    
$F_TRANSLATIONS_LOCAL["Training"] = "Opens training menu to improve skills with a trainer.";
$F_TRANSLATIONS_LOCAL["EndConversation"] = "#HERIKA_NAME# ends the conversation and becomes unavailable to talk for a short time.";

$F_RETURNMESSAGES_LOCAL["MoveTo"] = "#HERIKA_NAME# moves to #TARGET#.";
$F_RETURNMESSAGES_LOCAL["OpenInventory"] = "Initiates trading or exchange items with #PLAYER_NAME#.";
$F_RETURNMESSAGES_LOCAL["OpenInventory2"] = "#PLAYER_NAME# gives items to #HERIKA_NAME#. Accept gift.";
$F_RETURNMESSAGES_LOCAL["Attack"] = "#HERIKA_NAME# attacks #TARGET#.";
$F_RETURNMESSAGES_LOCAL["Follow"] = "#HERIKA_NAME# follows #TARGET#.";
$F_RETURNMESSAGES_LOCAL["Inspect"] = "#HERIKA_NAME# inspects #TARGET# and see this: #RESULT#";
$F_RETURNMESSAGES_LOCAL["InspectSurroundings"] = "#HERIKA_NAME# takes a look around and see this: #RESULT#";
$F_RETURNMESSAGES_LOCAL["CheckInventory"] = "#HERIKA_NAME#'s INVENTORY:#RESULT#";
$F_RETURNMESSAGES_LOCAL["SheatheWeapon"] = "Sheathes/put away current weapon";
$F_RETURNMESSAGES_LOCAL["Relax"] = "#HERIKA_NAME# is relaxed. Time to enjoy life.";
$F_RETURNMESSAGES_LOCAL["TakeASeat"] = "#HERIKA_NAME# sits in a nearby chair or piece of furniture.";
$F_RETURNMESSAGES_LOCAL["ReadQuestJournal"] = "";
$F_RETURNMESSAGES_LOCAL["Surrender"] = "#HERIKA_NAME# surrenders and raises their hands.";
$F_RETURNMESSAGES_LOCAL["IncreaseWalkSpeed"] = "Increases #HERIKA_NAME#'s speed or pace when moving or travelling.";
$F_RETURNMESSAGES_LOCAL["DecreaseWalkSpeed"] = "Decreases #HERIKA_NAME#'s speed or pace when moving or travelling.";
$F_RETURNMESSAGES_LOCAL["StopWalk"] = "Stop all of #HERIKA_NAME#'s actions immediately.";
$F_RETURNMESSAGES_LOCAL["TravelTo"] = "#HERIKA_NAME# begins travelling to #TARGET#.";
$F_RETURNMESSAGES_LOCAL["WaitHere"] = "#HERIKA_NAME# waits and stands at the place.";
$F_RETURNMESSAGES_LOCAL["TakeGoldFromPlayer"] = "#PLAYER_NAME# gave #TARGET# coins to #HERIKA_NAME#. If this is a transaction, maybe GiveItemTo is needed.";
$F_RETURNMESSAGES_LOCAL["RentRoom"] = "#HERIKA_NAME# rented a room to #PLAYER_NAME# for {{config.cost_gold}} gold.";
$F_RETURNMESSAGES_LOCAL["HireCarriage"] = "#HERIKA_NAME# accepted the {{config.cost_gold}} gold carriage fare to #TARGET# and ended the conversation.";
$F_RETURNMESSAGES_LOCAL["HireFerry"] = "#HERIKA_NAME# accepted the {{config.cost_gold}} gold ferry fare to #TARGET# and ended the conversation.";
$F_RETURNMESSAGES_LOCAL["SpawnItem"] = "#TARGET# receives #ITEM#.";
$F_RETURNMESSAGES_LOCAL["SpawnGold"] = "#TARGET# receives #AMOUNT# gold.";
$F_RETURNMESSAGES_LOCAL["SpawnNPC"] = "Spawned #AMOUNT# #TARGET# near #PLAYER_NAME#.";
$F_RETURNMESSAGES_LOCAL["CreateNewNPC"] = "A new NPC is being created nearby.";
$F_RETURNMESSAGES_LOCAL["DirectorCommand"] = "The director is preparing a scene instruction.";
$F_RETURNMESSAGES_LOCAL["TeleportNPC"] = "#TARGET# teleports to #ITEM#.";
$F_RETURNMESSAGES_LOCAL["KillTarget"] = "#TARGET# is killed.";
$F_RETURNMESSAGES_LOCAL["AddBounty"] = "#HERIKA_NAME# added a bounty for #TARGET# to #PLAYER_NAME#.";
$F_RETURNMESSAGES_LOCAL["PayBounty"] = "#PLAYER_NAME# paid off their bounty to #HERIKA_NAME#, and stolen items were removed from inventory.";
$F_RETURNMESSAGES_LOCAL["ArrestPlayer"] = "#HERIKA_NAME# attempted to arrest #PLAYER_NAME#.";
$F_RETURNMESSAGES_LOCAL["ForgiveCrime"] = "#HERIKA_NAME# forgave #PLAYER_NAME#'s crimes and cleared their bounty.";
$F_RETURNMESSAGES_LOCAL["FollowPlayer"] = "#HERIKA_NAME# follows #PLAYER_NAME#.";
$F_RETURNMESSAGES_LOCAL["Brawl"] = "#HERIKA_NAME# starts a brawl with #TARGET#.";
$F_RETURNMESSAGES_LOCAL["ReturnBackHome"] = "#HERIKA_NAME# goes back home.";
$F_RETURNMESSAGES_LOCAL["GiveGoldTo"] = "#HERIKA_NAME# gives #ITEM# gold to #TARGET#.";
$F_RETURNMESSAGES_LOCAL["GiveItemTo"] = "#HERIKA_NAME# gives #ITEM# to #TARGET#.";
$F_RETURNMESSAGES_LOCAL["PickupItem"] = "#HERIKA_NAME# picks up #ITEM#.";
$F_RETURNMESSAGES_LOCAL["GoToSleep"] = "#HERIKA_NAME# takes a nap.";
$F_RETURNMESSAGES_LOCAL["UseSoulGaze"] = "#HERIKA_NAME# used Soul Gaze.";
$F_RETURNMESSAGES_LOCAL["CastSpell"] = "#HERIKA_NAME# casts #ITEM# on #TARGET#.";
$F_RETURNMESSAGES_LOCAL["MakeFollower"] = "#HERIKA_NAME# is now part of the adventuring party.";

$F_RETURNMESSAGES_LOCAL["Toast"] = "#HERIKA_NAME# raises a glass in celebration or honor.";      
$F_RETURNMESSAGES_LOCAL["Drink"] = "#HERIKA_NAME# drinks a beverage to quench thirst or enjoy flavor.";
$F_RETURNMESSAGES_LOCAL["Consume"] = "#HERIKA_NAME# consumes an item from inventory.";
$F_RETURNMESSAGES_LOCAL["StartRitualCeremony"] = "#HERIKA_NAME# begins a ritual or ceremony, following its customs and practices.";
$F_RETURNMESSAGES_LOCAL["EndRitualCeremony"] = "#HERIKA_NAME# concludes a ritual or ceremony, marking its completion.";
$F_RETURNMESSAGES_LOCAL["Training"] = "#HERIKA_NAME# opens the training menu.";

// What is this?. We can translate functions or give them a custom name.
// This array will handle translations. Plugin must receive the codename always.

$F_NAMES_LOCAL["MoveTo"] = "MoveTo";
$F_NAMES_LOCAL["OpenInventory"] = "TradeItems";
$F_NAMES_LOCAL["OpenInventory2"] = "AcceptGift";
$F_NAMES_LOCAL["Attack"] = "Attack";
$F_NAMES_LOCAL["Follow"] = "Follow";
$F_NAMES_LOCAL["Inspect"] = "Inspect";
$F_NAMES_LOCAL["InspectSurroundings"] = "InspectSurroundings";
$F_NAMES_LOCAL["CheckInventory"] = "CheckInventory";
$F_NAMES_LOCAL["SheatheWeapon"] = "SheatheWeapon";
$F_NAMES_LOCAL["Relax"] = "Relax";
$F_NAMES_LOCAL["TakeASeat"] = "TakeASeat";
$F_NAMES_LOCAL["ReadQuestJournal"] = "ReadQuestJournal";
$F_NAMES_LOCAL["Surrender"] = "Surrender";
$F_NAMES_LOCAL["IncreaseWalkSpeed"] = "IncreaseWalkSpeed";
$F_NAMES_LOCAL["DecreaseWalkSpeed"] = "DecreaseWalkSpeed";
$F_NAMES_LOCAL["StopWalk"] = "StopWalk";
$F_NAMES_LOCAL["TravelTo"] = "TravelTo";
$F_NAMES_LOCAL["WaitHere"] = "WaitHere";
$F_NAMES_LOCAL["TakeGoldFromPlayer"] = "Take_Gold_From_#PLAYER_NAME#";
$F_NAMES_LOCAL["RentRoom"] = "RentRoom";
$F_NAMES_LOCAL["HireCarriage"] = "HireCarriage";
$F_NAMES_LOCAL["HireFerry"] = "HireFerry";
$F_NAMES_LOCAL["SpawnItem"] = "SpawnItem";
$F_NAMES_LOCAL["SpawnGold"] = "SpawnGold";
$F_NAMES_LOCAL["SpawnNPC"] = "SpawnNPC";
$F_NAMES_LOCAL["CreateNewNPC"] = "CreateNewNPC";
$F_NAMES_LOCAL["DirectorCommand"] = "DirectorCommand";
$F_NAMES_LOCAL["TeleportNPC"] = "TeleportNPC";
$F_NAMES_LOCAL["KillTarget"] = "KillTarget";
$F_NAMES_LOCAL["AddBounty"] = "AddBounty";
$F_NAMES_LOCAL["PayBounty"] = "PayBounty";
$F_NAMES_LOCAL["ArrestPlayer"] = "Arrest_#PLAYER_NAME#";
$F_NAMES_LOCAL["ForgiveCrime"] = "ForgiveCrime";
$F_NAMES_LOCAL["FollowPlayer"] = "Follow_#PLAYER_NAME#";
$F_NAMES_LOCAL["ComeCloser"] = "ComeCloser";
$F_NAMES_LOCAL["Brawl"] = "Brawl";
$F_NAMES_LOCAL["ReturnBackHome"] = "ReturnHome";
$F_NAMES_LOCAL["GiveGoldTo"] = "GiveGoldTo";
$F_NAMES_LOCAL["GiveItemTo"] = "GiveItemTo";
$F_NAMES_LOCAL["PickupItem"] = "PickupItem";
$F_NAMES_LOCAL["GoToSleep"] = "GoToSleep";
$F_NAMES_LOCAL["UseSoulGaze"] = "UseSoulGaze";
$F_NAMES_LOCAL["CastSpell"] = "CastSpell";
$F_NAMES_LOCAL["MakeFollower"] = "Join_#PLAYER_NAME#_Party";

$F_NAMES_LOCAL["Toast"] = "MakeAToast";
$F_NAMES_LOCAL["Drink"] = "Drink";
$F_NAMES_LOCAL["Consume"] = "Consume";
$F_NAMES_LOCAL["StartRitualCeremony"] = "StartRitualCeremony";
$F_NAMES_LOCAL["EndRitualCeremony"] = "EndRitualCeremony";

$F_NAMES_LOCAL["Training"] = "Training";
$F_NAMES_LOCAL["EndConversation"] = "EndConversation";

if (function_exists('herikaNormalizeActionCatalogDisplayActionName')) {
    foreach ($F_NAMES_LOCAL as $functionCode => $functionName) {
        $F_NAMES_LOCAL[$functionCode] = herikaNormalizeActionCatalogDisplayActionName($functionName);
    }
}

if (isset($GLOBALS["CORE_LANG"])) {
    if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "lang" . DIRECTORY_SEPARATOR . $GLOBALS["CORE_LANG"] . DIRECTORY_SEPARATOR . "functions.php")) {
        require_once __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "lang" . DIRECTORY_SEPARATOR . $GLOBALS["CORE_LANG"] . DIRECTORY_SEPARATOR . "functions.php";
    }
}

$herikaRetiredActionCodes = [
    'AttackHunt',
    'LookAt',
    'GetDateTime',
    'SearchDiary',
    'SetCurrentTask',
    'ReadDiaryPage',
    'SearchMemory',
];
$herikaRetiredActionNames = [];
foreach ($herikaRetiredActionCodes as $herikaRetiredActionCode) {
    if (isset($F_NAMES_LOCAL[$herikaRetiredActionCode])) {
        $herikaRetiredActionNames[] = $F_NAMES_LOCAL[$herikaRetiredActionCode];
    }
}

$GLOBALS["F_TRANSLATIONS"] = $F_TRANSLATIONS_LOCAL;
$GLOBALS["F_RETURNMESSAGES"] = $F_RETURNMESSAGES_LOCAL;
$GLOBALS["F_NAMES"] = $F_NAMES_LOCAL;
$GLOBALS["F_TRANSLATIONS_BASE"] = $F_TRANSLATIONS_LOCAL;
$GLOBALS["F_RETURNMESSAGES_BASE"] = $F_RETURNMESSAGES_LOCAL;

$hireCarriageDestinations = [
    "Whiterun",
    "Solitude",
    "Markarth",
    "Riften",
    "Windhelm",
    "Morthal",
    "Dawnstar",
    "Falkreath",
    "Winterhold",
    "Darkwater Crossing",
    "Dragon Bridge",
    "Ivarstead",
    "Karthwasten",
    "Kynesgrove",
    "Old Hroldan",
    "Riverwood",
    "Rorikstead",
    "Shor's Stone",
    "Stonehills",
];

$hireFerryDestinations = [
    "Windhelm",
    "Dawnstar",
    "Solitude",
    "Icewater Jetty",
    "Castle Volkihar",
    "Giant's Tooth",
];

$crimeTypes = ["Assault", "Murder", "Theft", "Pickpocketing", "Trespassing", "Jailbreak", "Custom"];

chimTraceFunctionsIncludePhase(__LINE__, 'function_catalog_build_start', $startTime);

$GLOBALS["FUNCTIONS"] = [
    [
        "name" => $F_NAMES_LOCAL["MoveTo"],
        "description" => $F_TRANSLATIONS_LOCAL["MoveTo"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Visible nearby target NPC, actor, or being. Do not use this for places, buildings, cities, doors, or locations.",
                    "enum" => isset($GLOBALS['FUNCTION_PARM_MOVETO']) ? $GLOBALS['FUNCTION_PARM_MOVETO'] : [],
                ],
            ],
            "required" => ["target"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["OpenInventory"],
        "description" => $F_TRANSLATIONS_LOCAL["OpenInventory"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ],
            ],
            "required" => [],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["OpenInventory2"],
        "description" => $F_TRANSLATIONS_LOCAL["OpenInventory2"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ],
            ],
            "required" => [],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["Attack"],
        "description" => $F_TRANSLATIONS_LOCAL["Attack"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Target NPC, actor, or being. Prefer exact Name [RefID: XXXXXXXX] from people_present; otherwise use the actor name.",
                ],
            ],
            "required" => ["target"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["Follow"],
        "description" => $F_TRANSLATIONS_LOCAL["Follow"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Target NPC, actor, or being. Prefer exact Name [RefID: XXXXXXXX] from people_present; otherwise use the actor name.",
                ],
            ],
            "required" => ["target"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["Inspect"],
        "description" => $F_TRANSLATIONS_LOCAL["Inspect"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Nearby NPC, actor, or being to inspect more closely",
                    "enum" => isset($GLOBALS['FUNCTION_PARM_INSPECT']) ? $GLOBALS['FUNCTION_PARM_INSPECT'] : [],
                ],
            ],
            "required" => ["target"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["InspectSurroundings"],
        "description" => $F_TRANSLATIONS_LOCAL["InspectSurroundings"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ],
            ],
            "required" => [],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["CheckInventory"],
        "description" => $F_TRANSLATIONS_LOCAL["CheckInventory"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "item to look for, if empty all items will be returned",
                ],
            ],
            "required" => [],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["SheatheWeapon"],
        "description" => $F_TRANSLATIONS_LOCAL["SheatheWeapon"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ],
            ],
            "required" => [],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["Relax"],
        "description" => $F_TRANSLATIONS_LOCAL["Relax"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ],
            ],
            "required" => [],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["TravelTo"],
        "description" => $F_TRANSLATIONS_LOCAL["TravelTo"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "location" => [
                    "type" => "string",
                    "description" => "Building, city, door, or other location to travel to.",

                ],
            ],
            "required" => ["location"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["TakeASeat"],
        "description" => $F_TRANSLATIONS_LOCAL["TakeASeat"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ],
            ],
            "required" => [""],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["ReadQuestJournal"],
        "description" => $F_TRANSLATIONS_LOCAL["ReadQuestJournal"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "id_quest" => [
                    "type" => "string",
                    "description" => "Specific quest to read. Leave blank to read current quests.",
                ],
            ],
            "required" => [""],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["Surrender"],
        "description" => $F_TRANSLATIONS_LOCAL["Surrender"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ],
            ],
            "required" => [""],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["IncreaseWalkSpeed"],
        "description" => $F_TRANSLATIONS_LOCAL["IncreaseWalkSpeed"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "speed" => [
                    "type" => "string",
                    "description" => "Speed",
                    "enum" => ["run", "jog"],
                ],

            ],
            "required" => [],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["DecreaseWalkSpeed"],
        "description" => $F_TRANSLATIONS_LOCAL["DecreaseWalkSpeed"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "speed" => [
                    "type" => "string",
                    "description" => "Speed",
                    "enum" => ["jog", "walk"],
                ],

            ],
            "required" => [],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["StopWalk"],
        "description" => $F_TRANSLATIONS_LOCAL["StopWalk"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "action",
                ],
            ],
            "required" => [""],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["WaitHere"],
        "description" => $F_TRANSLATIONS_LOCAL["WaitHere"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "",
                ],
            ],
            "required" => [""],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["TakeGoldFromPlayer"],
        "description" => $F_TRANSLATIONS_LOCAL["TakeGoldFromPlayer"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "",
                ],
            ],
            "required" => ["target"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["RentRoom"],
        "description" => $F_TRANSLATIONS_LOCAL["RentRoom"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ],
            ],
            "required" => [],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["HireCarriage"],
        "description" => $F_TRANSLATIONS_LOCAL["HireCarriage"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Vanilla carriage destination for {$GLOBALS["PLAYER_NAME"]}",
                    "enum" => $hireCarriageDestinations,
                ],
            ],
            "required" => ["target"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["HireFerry"],
        "description" => $F_TRANSLATIONS_LOCAL["HireFerry"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Vanilla ferry destination for {$GLOBALS["PLAYER_NAME"]}",
                    "enum" => $hireFerryDestinations,
                ],
            ],
            "required" => ["target"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["SpawnItem"],
        "description" => $F_TRANSLATIONS_LOCAL["SpawnItem"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Recipient actor. Use #PLAYER_NAME#, PLAYER, or me to give the item to the player.",
                ],
                "item" => [
                    "type" => "string",
                    "description" => "REQUIRED: item name from the descriptions database.",
                ],
                "amount" => [
                    "type" => "integer",
                    "description" => "Quantity to spawn and give (default: 1).",
                ],
            ],
            "required" => ["item"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["SpawnGold"],
        "description" => $F_TRANSLATIONS_LOCAL["SpawnGold"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Recipient actor. Use #PLAYER_NAME#, PLAYER, or me to give the gold to the player.",
                ],
                "amount" => [
                    "type" => "integer",
                    "description" => "REQUIRED: positive integer amount of gold to create and give (max: 1000000).",
                ],
            ],
            "required" => ["amount"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["SpawnNPC"],
        "description" => $F_TRANSLATIONS_LOCAL["SpawnNPC"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "REQUIRED: SNQE NPC template key from npc_templates or npc_own_templates.",
                    "enum" => herikaGetSpawnNpcTemplateKeys(),
                ],
                "amount" => [
                    "type" => "integer",
                    "description" => "How many NPCs to spawn from that template key (default: 1, max: 10).",
                ],
            ],
            "required" => ["target"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["CreateNewNPC"],
        "description" => $F_TRANSLATIONS_LOCAL["CreateNewNPC"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "REQUIRED: short creation brief for the new nearby NPC, such as race, role, temperament, or purpose.",
                ],
            ],
            "required" => ["target"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["DirectorCommand"],
        "description" => $F_TRANSLATIONS_LOCAL["DirectorCommand"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "REQUIRED: short freeform director brief describing the scene change, instruction, or event to stage.",
                ],
            ],
            "required" => ["target"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["TeleportNPC"],
        "description" => $F_TRANSLATIONS_LOCAL["TeleportNPC"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Actor to teleport. Use #PLAYER_NAME#, PLAYER, or me to teleport the player.",
                ],
                "item" => [
                    "type" => "string",
                    "description" => "REQUIRED: destination location name from the location database.",
                ],
            ],
            "required" => ["item"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["KillTarget"],
        "description" => $F_TRANSLATIONS_LOCAL["KillTarget"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "REQUIRED: actor to kill. Use #PLAYER_NAME#, PLAYER, or me to kill the player.",
                ],
            ],
            "required" => ["target"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["AddBounty"],
        "description" => $F_TRANSLATIONS_LOCAL["AddBounty"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Crime type for the bounty",
                    "enum" => $crimeTypes,
                ],
                "item" => [
                    "type" => "string",
                    "description" => "Custom gold amount (only used when crime_type is Custom)",
                ],
            ],
            "required" => ["target"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["PayBounty"],
        "description" => $F_TRANSLATIONS_LOCAL["PayBounty"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ],
            ],
            "required" => [""],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["ArrestPlayer"],
        "description" => $F_TRANSLATIONS_LOCAL["ArrestPlayer"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ],
            ],
            "required" => [""],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["ForgiveCrime"],
        "description" => $F_TRANSLATIONS_LOCAL["ForgiveCrime"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ],
            ],
            "required" => [""],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["FollowPlayer"],
        "description" => $F_TRANSLATIONS_LOCAL["FollowPlayer"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "",
                ],
            ],
            "required" => [""],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["ComeCloser"],
        "description" => $F_TRANSLATIONS_LOCAL["ComeCloser"],
        "properties" => [
            "target" => [
                "type" => "string",
                "description" => "Keep it blank",
            ],
        ],
        "required" => [""],
    ],
    [
        "name" => $F_NAMES_LOCAL["Brawl"],
        "description" => $F_TRANSLATIONS_LOCAL["Brawl"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Target NPC, actor, or being. Prefer exact Name [RefID: XXXXXXXX] from people_present; otherwise use the actor name.",
                ],
            ],
            "required" => ["target"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["ReturnBackHome"],
        "description" => $F_TRANSLATIONS_LOCAL["ReturnBackHome"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ],
            ],
            "required" => [],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["GiveGoldTo"],
        "description" => $F_TRANSLATIONS_LOCAL["GiveGoldTo"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Target NPC, actor, or being to receive gold. Prefer exact Name [RefID: XXXXXXXX] from people_present; otherwise use the actor name.",
                ],
                "item" => [
                    "type" => "string",
                    "description" => "Amount of gold to give (number as string)",
                ]
            ],
            "required" => ["target", "item"],
        ]
    ],
    [
        "name" => $F_NAMES_LOCAL["GiveItemTo"],
        "description" => $F_TRANSLATIONS_LOCAL["GiveItemTo"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Target NPC, actor, or being to receive the item. Prefer exact Name [RefID: XXXXXXXX] from people_present; otherwise use the actor name.",
                ],
                "item" => [
                    "type" => "string",
                    "description" => "REQUIRED: Exact name of item from <inventory> tag. Must match item name exactly.",
                ],
                "amount" => [
                    "type" => "integer",
                    "description" => "Number of items to give (default: 1). Cannot exceed quantity in <inventory>.",
                ],
            ],
            "required" => ["target", "item"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["PickupItem"],
        "description" => $F_TRANSLATIONS_LOCAL["PickupItem"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Target actor (leave empty for PickupItem)",
                ],
                "item" => [
                    "type" => "string",
                    "description" => "REQUIRED: Exact RefID:ItemName from <nearby_items> or from the representative RefID shown in ITEM DESCRIPTIONS when nearby items are grouped (e.g., 0x12345:Iron Sword). Must match format exactly.",
                ],
            ],
            "required" => ["item"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["GoToSleep"],
        "description" => $F_TRANSLATIONS_LOCAL["GoToSleep"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ],
            ],
            "required" => [""],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["UseSoulGaze"],
        "description" => $F_TRANSLATIONS_LOCAL["UseSoulGaze"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ],
            ],
            "required" => [],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["CastSpell"],
        "description" => $F_TRANSLATIONS_LOCAL["CastSpell"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Target actor. Prefer exact Name [RefID: XXXXXXXX] from people_present; otherwise use the actor name. Use 'self' for self-cast spells.",
                ],
                "item" => [
                    "type" => "string",
                    "description" => "REQUIRED: Spell name from <spells> tag (exact name)",
                ],
            ],
            "required" => ["target", "item"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["MakeFollower"],
        "description" => $F_TRANSLATIONS_LOCAL["MakeFollower"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ],
            ],
            "required" => [""],
        ],
    ],
     [
        "name" => $F_NAMES_LOCAL["Toast"],
        "description" => $F_TRANSLATIONS_LOCAL["Toast"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ],
            ],
            "required" => [""],
        ],
    ],
     [
        "name" => $F_NAMES_LOCAL["Drink"],
        "description" => $F_TRANSLATIONS_LOCAL["Drink"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ],
            ],
            "required" => [""],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["Consume"],
        "description" => $F_TRANSLATIONS_LOCAL["Consume"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "REQUIRED: Exact BaseID:ItemName identifier of the food, drink, or potion from <inventory> to consume.",
                ],
                "item" => [
                    "type" => "string",
                    "description" => "Optional fallback copy of the same inventory item name if target is empty.",
                ],
            ],
            "required" => ["target"],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["Training"],
        "description" => $F_TRANSLATIONS_LOCAL["Training"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ],
            ],
            "required" => [""],
        ],
    ],

    [
        "name" => $F_NAMES_LOCAL["StartRitualCeremony"],
        "description" => $F_TRANSLATIONS_LOCAL["StartRitualCeremony"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Type of ceremony or ritual to start:Religious, Magical, Cultural, Personal, Blood",
                ],
            ],
            "required" => [""],
        ],
    ],
     [
        "name" => $F_NAMES_LOCAL["EndRitualCeremony"],
        "description" => $F_TRANSLATIONS_LOCAL["EndRitualCeremony"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ],
            ],
            "required" => [""],
        ],
    ],
    [
        "name" => $F_NAMES_LOCAL["EndConversation"],
        "description" => $F_TRANSLATIONS_LOCAL["EndConversation"],
        "parameters" => [
            "type" => "object",
            "properties" => [
                "target" => [
                    "type" => "string",
                    "description" => "Keep it blank",
                ],
            ],
            "required" => [""],
        ],
    ],
];

chimTraceFunctionsIncludePhase(__LINE__, 'function_catalog_build_done', $startTime);

foreach ($herikaRetiredActionCodes as $herikaRetiredActionCode) {
    unset($F_TRANSLATIONS_LOCAL[$herikaRetiredActionCode], $F_RETURNMESSAGES_LOCAL[$herikaRetiredActionCode], $F_NAMES_LOCAL[$herikaRetiredActionCode]);
}

$GLOBALS["F_TRANSLATIONS"] = $F_TRANSLATIONS_LOCAL;
$GLOBALS["F_RETURNMESSAGES"] = $F_RETURNMESSAGES_LOCAL;
$GLOBALS["F_NAMES"] = $F_NAMES_LOCAL;
$GLOBALS["FUNCTIONS"] = array_values(array_filter($GLOBALS["FUNCTIONS"], function ($functionEntry) use ($herikaRetiredActionNames) {
    return !in_array($functionEntry["name"] ?? "", $herikaRetiredActionNames, true);
}));

// Mantain a copy of all functions defined here
foreach ($GLOBALS["FUNCTIONS"] as $n => $functionEntry) {
    $GLOBALS["BASE_FUNCTIONS"][getFunctionCodeName($functionEntry["name"])] = $GLOBALS["FUNCTIONS"][$n];
}
$HERIKA_BASE_FUNCTIONS_LOCAL = $GLOBALS["BASE_FUNCTIONS"];
$GLOBALS["HERIKA_BASE_FUNCTIONS_FALLBACK"] = $GLOBALS["BASE_FUNCTIONS"];

chimTraceFunctionsIncludePhase(__LINE__, 'base_functions_indexed', $startTime);

function getFunctionNameAliases()
{
    static $cachedAliases = null;
    if (is_array($cachedAliases)) {
        return $cachedAliases;
    }

    $playerName = strval($GLOBALS["PLAYER_NAME"] ?? "Player");

    $aliases = [
        'ExchangeItems' => 'OpenInventory',
        'ListInventory' => 'CheckInventory',
        'LetsRelax' => 'Relax',
        "TakeMoneyFrom{$playerName}" => 'TakeGoldFromPlayer',
        'Fight' => 'Brawl',
        'ReturnBackHome' => 'ReturnBackHome',
        "JoinTo{$playerName}Squad" => 'MakeFollower',
        'MakeAToast' => 'Toast',
    ];

    if (function_exists('herikaNormalizeActionCatalogDisplayActionName')) {
        foreach ($aliases as $legacyActionName => $codeName) {
            $normalizedLegacyActionName = herikaNormalizeActionCatalogDisplayActionName($legacyActionName);
            if ($normalizedLegacyActionName !== '' && !isset($aliases[$normalizedLegacyActionName])) {
                $aliases[$normalizedLegacyActionName] = $codeName;
            }
        }
    }

    $cachedAliases = $aliases;
    return $cachedAliases;
}

function getFunctionCodeName($key)
{
    $key = strval($key);
    static $resolvedCodeNames = [];

    if (array_key_exists($key, $resolvedCodeNames)) {
        return $resolvedCodeNames[$key];
    }

    if (!isset($GLOBALS["F_NAMES"]) || !is_array($GLOBALS["F_NAMES"])) {
        return $resolvedCodeNames[$key] = false;
    }

    if (isset($GLOBALS["F_NAMES"][$key])) {
        return $resolvedCodeNames[$key] = $key;
    }

    if (isset($GLOBALS["HERIKA_ACTION_NAME_PREFERRED_CODE"]) && is_array($GLOBALS["HERIKA_ACTION_NAME_PREFERRED_CODE"])) {
        $preferredCode = $GLOBALS["HERIKA_ACTION_NAME_PREFERRED_CODE"][$key] ?? false;
        if ($preferredCode !== false) {
            return $resolvedCodeNames[$key] = $preferredCode;
        }
    }

    $keysToTry = [$key];
    if (function_exists('herikaNormalizeActionCatalogDisplayActionName')) {
        $normalizedKey = herikaNormalizeActionCatalogDisplayActionName($key);
        if ($normalizedKey !== '' && !in_array($normalizedKey, $keysToTry, true)) {
            $keysToTry[] = $normalizedKey;
        }
    }

    foreach ($keysToTry as $candidateKey) {
        if (isset($GLOBALS["HERIKA_ACTION_NAME_PREFERRED_CODE"]) && is_array($GLOBALS["HERIKA_ACTION_NAME_PREFERRED_CODE"])) {
            $preferredCode = $GLOBALS["HERIKA_ACTION_NAME_PREFERRED_CODE"][$candidateKey] ?? false;
            if ($preferredCode !== false) {
                return $resolvedCodeNames[$key] = $preferredCode;
            }
        }

        $matchingCodes = [];
        foreach ($GLOBALS["F_NAMES"] as $functionCode => $functionName) {
            if ($functionName === $candidateKey) {
                $matchingCodes[] = $functionCode;
            }
        }

        if (count($matchingCodes) === 1) {
            return $resolvedCodeNames[$key] = $matchingCodes[0];
        }

        if (count($matchingCodes) > 1) {
            foreach ($matchingCodes as $matchingCode) {
                if (function_exists('herikaGetActionCatalogRow')) {
                    $row = herikaGetActionCatalogRow($matchingCode);
                    if (is_array($row) && herikaActionCatalogRowIsAvailableInCurrentMode($row) && !empty(($row['metadata'] ?? [])['builtin']) === false) {
                        return $resolvedCodeNames[$key] = $matchingCode;
                    }
                }
            }

            return $resolvedCodeNames[$key] = $matchingCodes[0];
        }
    }

    $aliases = getFunctionNameAliases();
    if (isset($aliases[$key])) {
        return $resolvedCodeNames[$key] = $aliases[$key];
    }

    if (function_exists('herikaResolveActionCatalogCodeName')) {
        $catalogCodeName = herikaResolveActionCatalogCodeName($key, true);
        if ($catalogCodeName !== false) {
            return $resolvedCodeNames[$key] = $catalogCodeName;
        }

        $catalogCodeName = herikaResolveActionCatalogCodeName($key, false);
        if ($catalogCodeName !== false) {
            return $resolvedCodeNames[$key] = $catalogCodeName;
        }
    }

    return $resolvedCodeNames[$key] = false;
}

function herikaBuildActionPromptTemplateContext($rowOrCode = null, array $extraContext = [])
{
    $row = null;
    $codeName = '';

    if (is_array($rowOrCode)) {
        $row = $rowOrCode;
        $codeName = trim(strval($row['code_name'] ?? ''));
    } else {
        $codeName = trim(strval($rowOrCode ?? ''));
        if ($codeName !== '' && function_exists('herikaGetActionCatalogRow')) {
            $row = herikaGetActionCatalogRow($codeName);
        }
    }

    if ($codeName === '' && is_array($row)) {
        $codeName = trim(strval($row['code_name'] ?? ''));
    }

    $context = [
        'code_name' => $codeName,
        'herika_name' => strval($GLOBALS["HERIKA_NAME"] ?? 'NPC'),
        'player_name' => strval($GLOBALS["PLAYER_NAME"] ?? 'Player'),
        'config' => [],
    ];

    if ($codeName !== '' && function_exists('herikaActionCatalogGetResolvedCustomConfig')) {
        $context['config'] = herikaActionCatalogGetResolvedCustomConfig($codeName, $row);
    }

    if (count($extraContext) > 0) {
        $context = array_replace_recursive($context, $extraContext);
    }

    return $context;
}

function herikaFormatReturnMessageTemplate($codeName, $primaryArgument = '', array $extraReplacements = [])
{
    $codeName = trim(strval($codeName));
    if ($codeName === '') {
        return '';
    }

    $actionRow = function_exists('herikaGetActionCatalogRow')
        ? herikaGetActionCatalogRow($codeName)
        : null;

    $template = '';
    if (is_array($actionRow)) {
        $template = strval($actionRow['return_message'] ?? '');
    }
    if ($template === '' && isset($GLOBALS["F_RETURNMESSAGES"][$codeName])) {
        $template = strval($GLOBALS["F_RETURNMESSAGES"][$codeName] ?? '');
    }
    if ($template === '') {
        return '';
    }

    $argumentData = [];
    if (is_array($primaryArgument)) {
        $argumentData = $primaryArgument;
        $primaryArgument = trim(strval($argumentData['target'] ?? ''));
        if ($primaryArgument === '') {
            $primaryArgument = herikaExtractActionArgumentTargetValue($argumentData);
        }
    } else {
        $primaryArgument = is_scalar($primaryArgument) || $primaryArgument === null
            ? strval($primaryArgument ?? '')
            : '';
    }

    $replacements = [
        '#TARGET#' => $primaryArgument,
        '#ITEM#' => trim(strval($argumentData['item'] ?? ($argumentData['location'] ?? ''))),
        '#AMOUNT#' => trim(strval($argumentData['amount'] ?? '')),
        '#LOCATION#' => trim(strval($argumentData['location'] ?? ($argumentData['item'] ?? ''))),
        '#HERIKA_NAME#' => strval($GLOBALS["HERIKA_NAME"] ?? 'NPC'),
        '#PLAYER_NAME#' => strval($GLOBALS["PLAYER_NAME"] ?? 'Player'),
    ];

    foreach ($extraReplacements as $key => $value) {
        $replacements[strval($key)] = is_scalar($value) || $value === null ? strval($value ?? '') : '';
    }

    $rendered = strtr($template, $replacements);
    return herikaFormatActionPromptTemplate(
        $rendered,
        [],
        is_array($actionRow) ? $actionRow : $codeName,
        [
            'parameter_target' => $primaryArgument,
            'parameters' => $argumentData,
        ]
    );
}

function herikaResolveFuncretArgumentName($codeName, array $followupConfig = [])
{
    $argName = trim(strval($followupConfig['arg_name'] ?? ''));
    if ($argName !== '') {
        return $argName;
    }

    $row = function_exists('herikaGetActionCatalogRow')
        ? herikaGetActionCatalogRow($codeName)
        : null;
    if (!is_array($row)) {
        return 'target';
    }

    $parameters = $row['parameters_json'] ?? [];
    if (!is_array($parameters)) {
        $decodedParameters = json_decode(strval($parameters), true);
        $parameters = is_array($decodedParameters) ? $decodedParameters : [];
    }

    $required = $parameters['required'] ?? [];
    if (!is_array($required)) {
        $required = [$required];
    }
    foreach ($required as $requiredName) {
        $requiredName = trim(strval($requiredName));
        if ($requiredName !== '') {
            return $requiredName;
        }
    }

    $properties = $parameters['properties'] ?? [];
    if (!is_array($properties)) {
        return 'target';
    }

    foreach (['target', 'location', 'item', 'amount', 'speed'] as $preferredName) {
        if (array_key_exists($preferredName, $properties)) {
            return $preferredName;
        }
    }

    foreach (array_keys($properties) as $propertyName) {
        $propertyName = trim(strval($propertyName));
        if ($propertyName !== '') {
            return $propertyName;
        }
    }

    return 'target';
}

function herikaBuildFuncretResultInfoActionMessage($codeName, $argName = 'target', $argValue = '', $resultText = '')
{
    $codeName = trim(strval($codeName));
    if ($codeName === '') {
        return '';
    }

    $argName = trim(strval($argName));
    if ($argName === '') {
        $argName = 'target';
    }

    $resultText = trim(strval($resultText));
    $herikaName = strval($GLOBALS["HERIKA_NAME"] ?? 'NPC');
    if ($resultText !== '' && stripos($resultText, 'error') === 0) {
        return "{$herikaName} issued ACTION, but {$resultText}";
    }

    $arguments = [];
    if (is_array($argValue)) {
        $arguments = $argValue;
    } elseif (is_scalar($argValue) || $argValue === null) {
        $arguments[$argName] = trim(strval($argValue ?? ''));
    }

    $message = herikaFormatReturnMessageTemplate(
        $codeName,
        $arguments,
        ['#RESULT#' => $resultText]
    );
    $message = trim(strval($message));
    if ($message !== '') {
        return $message;
    }

    if ($resultText === '') {
        return '';
    }

    $actionName = function_exists('getFunctionTrlName') ? getFunctionTrlName($codeName) : $codeName;
    return "{$herikaName} issued ACTION {$actionName}: {$resultText}";
}

function herikaFormatActionPromptTemplate($template, array $extraReplacements = [], $rowOrCode = null, array $extraContext = [])
{
    $template = strval($template);
    if ($template === '') {
        return '';
    }

    $replacements = [
        '#HERIKA_NAME#' => strval($GLOBALS["HERIKA_NAME"] ?? 'NPC'),
        '#PLAYER_NAME#' => strval($GLOBALS["PLAYER_NAME"] ?? 'Player'),
        '{$GLOBALS["HERIKA_NAME"]}' => strval($GLOBALS["HERIKA_NAME"] ?? 'NPC'),
        '{$GLOBALS["PLAYER_NAME"]}' => strval($GLOBALS["PLAYER_NAME"] ?? 'Player'),
    ];

    foreach ($extraReplacements as $key => $value) {
        $replacements[strval($key)] = is_scalar($value) || $value === null ? strval($value ?? '') : '';
    }

    $rendered = strtr($template, $replacements);

    if (function_exists('herikaActionCatalogResolveTemplateValue')) {
        $context = herikaBuildActionPromptTemplateContext($rowOrCode, $extraContext);
        $resolved = herikaActionCatalogResolveTemplateValue($rendered, $context);
        if (!is_array($resolved) && $resolved !== null) {
            $rendered = strval($resolved);
        }
    }

    // Some catalog/imported strings can still carry SQL-style doubled apostrophes.
    return str_replace("''", "'", $rendered);
}

function herikaGetPromptActionDescription($codeName, $fallbackDescription = '')
{
    $codeName = trim(strval($codeName));
    $description = '';

    if ($codeName !== '' && isset($GLOBALS["F_TRANSLATIONS"][$codeName])) {
        $description = strval($GLOBALS["F_TRANSLATIONS"][$codeName] ?? '');
    }

    if ($description === '') {
        $description = strval($fallbackDescription);
    }

    return herikaFormatActionPromptTemplate($description, [], $codeName);
}

function getFunctionTrlName($key)
{
    if (isset($GLOBALS["F_NAMES"][$key]) && !empty($GLOBALS["F_NAMES"][$key])) {
        return $GLOBALS["F_NAMES"][$key];
    } else {
        return $key;
    }

}

function getSingleFunctionParameterValue($functionDef, $parsedResponse)
{
    if (!is_array($parsedResponse)) {
        return "";
    }

    $properties = $functionDef["parameters"]["properties"] ?? [];
    if (is_array($properties) && count($properties) === 0) {
        return "";
    }

    if (is_array($properties) && count($properties) === 1) {
        $parameterName = array_key_first($properties);
        if (is_string($parameterName) && array_key_exists($parameterName, $parsedResponse)) {
            return $parsedResponse[$parameterName];
        }
    }

    return $parsedResponse["target"] ?? "";
}

function normalizeFunctionParameterValueFromSchema($parameterSchema, $value)
{
    if (!is_array($parameterSchema)) {
        return $value;
    }

    $parameterType = strtolower(trim(strval($parameterSchema["type"] ?? "")));
    if ($parameterType === "integer" && is_numeric($value)) {
        return intval(round(floatval($value)));
    }

    if ($parameterType === "number" && is_numeric($value)) {
        return floatval($value);
    }

    if ($parameterType === "boolean") {
        if (is_bool($value)) {
            return $value;
        }

        $text = strtolower(trim(strval($value)));
        if (in_array($text, ["1", "true", "yes", "on", "t"], true)) {
            return true;
        }
        if (in_array($text, ["0", "false", "no", "off", "f"], true)) {
            return false;
        }
    }

    return $value;
}

function functionDefinitionHasRequiredParameters($functionDef)
{
    if (!is_array($functionDef)) {
        return false;
    }

    foreach (($functionDef["parameters"]["required"] ?? []) as $requiredParameter) {
        if (trim(strval($requiredParameter)) !== "") {
            return true;
        }
    }

    return false;
}

function functionExecutionParameterValueIsEmpty($parameterValue)
{
    if (is_array($parameterValue)) {
        return count($parameterValue) === 0;
    }

    return trim(strval($parameterValue)) === "";
}

function buildFunctionParameterValueFromResponse($functionDef, $parsedResponse)
{
    $properties = $functionDef["parameters"]["properties"] ?? [];
    $requiredParameters = [];
    foreach (($functionDef["parameters"]["required"] ?? []) as $requiredParameter) {
        $requiredParameter = trim(strval($requiredParameter));
        if ($requiredParameter !== "") {
            $requiredParameters[] = $requiredParameter;
        }
    }

    $missingRequiredParameters = [];
    foreach ($requiredParameters as $requiredParameter) {
        if (!array_key_exists($requiredParameter, $parsedResponse) || $parsedResponse[$requiredParameter] === "" || $parsedResponse[$requiredParameter] === null) {
            $missingRequiredParameters[] = $requiredParameter;
        }
    }

    if (count($properties) > 1) {
        $parameters = [];
        foreach ($properties as $parameterName => $parameterSchema) {
            if (array_key_exists($parameterName, $parsedResponse)) {
                $parameters[$parameterName] = normalizeFunctionParameterValueFromSchema($parameterSchema, $parsedResponse[$parameterName]);
            }
        }

        return [
            "parameter_value" => $parameters,
            "missing_required" => $missingRequiredParameters,
        ];
    }

    return [
        "parameter_value" => getSingleFunctionParameterValue($functionDef, $parsedResponse),
        "missing_required" => $missingRequiredParameters,
    ];
}

function buildFunctionExecutionContextFromResponse($parsedResponse)
{
    $actionName = trim(strval($parsedResponse["action"] ?? ""));
    $resolvedCodeName = $actionName !== "" ? getFunctionCodeName($actionName) : false;
    $functionCodeName = is_string($resolvedCodeName) && $resolvedCodeName !== ""
        ? $resolvedCodeName
        : $actionName;
    $functionDef = null;
    if ($functionCodeName !== "") {
        $functionDef = findFunctionByName($functionCodeName);
    }
    if (!is_array($functionDef) && $actionName !== "" && $functionCodeName !== $actionName) {
        $functionDef = findFunctionByName($actionName);
    }
    $parameterValue = $parsedResponse["target"] ?? "";
    $missingRequired = [];

    if (is_array($functionDef)) {
        $parameterData = buildFunctionParameterValueFromResponse($functionDef, is_array($parsedResponse) ? $parsedResponse : []);
        $parameterValue = $parameterData["parameter_value"];
        $missingRequired = $parameterData["missing_required"];
    }

    if (strcasecmp($functionCodeName, 'TakeHeldItem') === 0) {
        $resolvedHeldItem = HeldItems::resolveHeldIdentifier(strval($parameterValue));
        if ($resolvedHeldItem === null) {
            Logger::warn('TakeHeldItem rejected because the requested RefID is not currently held by the player.');
            $parameterValue = '';
            if (!in_array('item', $missingRequired, true)) {
                $missingRequired[] = 'item';
            }
        } else {
            $parameterValue = $resolvedHeldItem;
        }
    }

    return [
        "action_name" => $actionName,
        "function_def" => $functionDef,
        "function_found" => is_array($functionDef),
        "function_code_name" => $functionCodeName,
        "parameter_value" => $parameterValue,
        "parameter_string" => buildFunctionExecutionParameter($functionCodeName, $parameterValue),
        "missing_required" => $missingRequired,
        "has_required_parameters" => functionDefinitionHasRequiredParameters($functionDef),
        "parameter_is_empty" => functionExecutionParameterValueIsEmpty($parameterValue),
    ];
}

function queueFunctionExecutionCommand(&$commandBuffer, &$alreadySent, $executionContext, $connectorName, $actorName = null)
{
    $actionName = trim(strval($executionContext["action_name"] ?? ""));
    if ($actionName === "") {
        return false;
    }

    if (empty($executionContext["function_found"])) {
        if ($actionName !== "Talk") {
            Logger::warn("{$connectorName}: Function not found for {$actionName}");
        }
        return false;
    }

    $missingRequired = $executionContext["missing_required"] ?? [];
    if (count($missingRequired) > 0) {
        Logger::warn("{$connectorName}: Missing required parameter(s) for " . strval($executionContext["function_code_name"] ?? $actionName) . ": " . implode(", ", $missingRequired));
    }

    if (!empty($executionContext["has_required_parameters"]) && !empty($executionContext["parameter_is_empty"])) {
        Logger::warn("{$connectorName}: Missing required parameter(s) for " . strval($executionContext["function_code_name"] ?? $actionName) . ": " . implode(", ", $missingRequired));
        return false;
    }

    $actorName = ($actorName !== null && trim(strval($actorName)) !== "") ? strval($actorName) : strval($GLOBALS["HERIKA_NAME"] ?? "Herika");
    $functionCodeName = strval($executionContext["function_code_name"] ?? "");
    $commandChannel = "command";

    if (function_exists('herikaGetActionCatalogRow') && function_exists('herikaActionCatalogGetConfirmationCommandChannel')) {
        $actionRow = herikaGetActionCatalogRow($functionCodeName);
        if (is_array($actionRow)) {
            $commandChannel = herikaActionCatalogGetConfirmationCommandChannel($actionRow);
        }
    }

    $commandStr = $actorName . "|" . $commandChannel . "|" . $functionCodeName . "@" . strval($executionContext["parameter_string"] ?? "") . "\r\n";
    $commandHash = md5($commandStr);

    if (isset($alreadySent[$commandHash])) {
        return false;
    }

    $commandBuffer[] = $commandStr;
    $alreadySent[$commandHash] = $commandStr;
    return true;
}

function chimPrepareActionsIssuedOriginalValue($originalValue)
{
    if (function_exists('herikaActionCatalogApplyFollowupChainToActionsIssuedOriginal')) {
        return herikaActionCatalogApplyFollowupChainToActionsIssuedOriginal($originalValue);
    }

    return strval($originalValue);
}

function buildFunctionExecutionParameter($functionCodeName, $parameter)
{
    $functionCodeName = trim(strval($functionCodeName));

    $configuredPayload = buildConfiguredActionParameterFromMetadata($functionCodeName, $parameter);
    if ($configuredPayload !== null) {
        return $configuredPayload;
    }

    if (is_array($parameter)) {
        return json_encode($parameter, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    return strval($parameter);
}

function findFunctionByName($name)
{
    $name = trim(strval($name));
    if (function_exists('herikaFindActionCatalogRowByNameOrCode') && function_exists('herikaActionCatalogBuildFunctionEntryFromRow')) {
        $row = herikaFindActionCatalogRowByNameOrCode($name, true);
        if (is_array($row) && !empty($row['is_activated'])) {
            $functionEntry = herikaActionCatalogBuildFunctionEntryFromRow($row);
            if (is_array($functionEntry) && !empty($functionEntry['name'])) {
                $functionEntry['description'] = function_exists('herikaFormatActionPromptTemplate')
                    ? herikaFormatActionPromptTemplate($row['description'] ?? '', [], $row)
                    : strval($row['description'] ?? '');
                return $functionEntry;
            }
        }
    }

    foreach ($GLOBALS["FUNCTIONS"] as $function) {
        if (($function['name'] ?? '') === $name) {
            return $function;
        }
    }

    $resolvedCodeName = getFunctionCodeName($name);
    if (is_string($resolvedCodeName) && $resolvedCodeName !== '') {
        foreach ($GLOBALS["FUNCTIONS"] as $function) {
            $functionName = trim(strval($function['name'] ?? ''));
            if ($functionName === '') {
                continue;
            }

            if (getFunctionCodeName($functionName) === $resolvedCodeName) {
                return $function;
            }
        }
    }

    if (function_exists('herikaGetActionCatalogRowsByCode') && function_exists('herikaActionCatalogBuildFunctionEntryFromRow')) {
        $rowsByCode = herikaGetActionCatalogRowsByCode();
        $candidateCodes = [];

        if ($name !== '') {
            $candidateCodes[] = $name;
        }
        if (is_string($resolvedCodeName) && $resolvedCodeName !== '' && !in_array($resolvedCodeName, $candidateCodes, true)) {
            $candidateCodes[] = $resolvedCodeName;
        }

        foreach ($candidateCodes as $candidateCode) {
            $row = $rowsByCode[$candidateCode] ?? null;
            if (!is_array($row) || empty($row['is_activated'])) {
                continue;
            }
            if (function_exists('herikaActionCatalogRowIsAvailableInCurrentMode') && !herikaActionCatalogRowIsAvailableInCurrentMode($row)) {
                continue;
            }

            $functionEntry = herikaActionCatalogBuildFunctionEntryFromRow($row);
            if (is_array($functionEntry) && !empty($functionEntry['name'])) {
                return $functionEntry;
            }
        }

        foreach ($rowsByCode as $row) {
            if (!is_array($row) || empty($row['code_name']) || empty($row['is_activated'])) {
                continue;
            }
            if (function_exists('herikaActionCatalogRowIsAvailableInCurrentMode') && !herikaActionCatalogRowIsAvailableInCurrentMode($row)) {
                continue;
            }

            $rowActionName = trim(strval($row['action_name'] ?? ''));
            $runtimeActionName = function_exists('herikaFormatActionPromptTemplate')
                ? trim(strval(herikaFormatActionPromptTemplate($rowActionName, [], $row)))
                : $rowActionName;
            $normalizedRuntimeActionName = function_exists('herikaNormalizeActionCatalogDisplayActionName')
                ? trim(strval(herikaNormalizeActionCatalogDisplayActionName($runtimeActionName)))
                : $runtimeActionName;

            if (!in_array($name, [$rowActionName, $runtimeActionName, $normalizedRuntimeActionName], true)) {
                continue;
            }

            $functionEntry = herikaActionCatalogBuildFunctionEntryFromRow($row);
            if (is_array($functionEntry) && !empty($functionEntry['name'])) {
                return $functionEntry;
            }
        }
    }

    return null; // Return null if function not found
}

function getFunctionByTrlName($searchValue)
{
    if (function_exists('herikaResolveActionCatalogCodeName')) {
        $catalogCodeName = herikaResolveActionCatalogCodeName($searchValue, true);
        if ($catalogCodeName !== false) {
            return $catalogCodeName;
        }
    }

    foreach ($GLOBALS["F_NAMES"] as $key => $value) {
        if ($value === $searchValue) {
            return $key;
        }
    }

}

function requireFunctionFilesRecursively($dir)
{
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $path = $dir . '/' . $file;

        if (is_dir($path)) {
            requireFunctionFilesRecursively($path);
        } elseif (is_file($path) && $file === 'functions.php') {
            require_once $path;
        }
    }
}

function unsetFunction($functionCodename)
{
    if (($key = array_search($functionCodename, $GLOBALS["ENABLED_FUNCTIONS"])) !== false) {
        unset($GLOBALS["ENABLED_FUNCTIONS"][$key]);

    }

    foreach ($GLOBALS["FUNCTIONS"] as $n => $v) {
        if (!in_array(getFunctionCodeName($v["name"]), $GLOBALS["ENABLED_FUNCTIONS"])) {
            // error_log("Removing {$GLOBALS["FUNCTIONS"][$n]["name"]}");
            unset($GLOBALS["FUNCTIONS"][$n]);
        }
    }

}

$seedActionRows = herikaBuildActionCatalogSeedRows(
    $F_NAMES_LOCAL ?? [],
    $F_TRANSLATIONS_LOCAL ?? [],
    $F_RETURNMESSAGES_LOCAL ?? [],
    [],
    $ENABLED_FUNCTIONS_LOCAL,
    herikaBuildActionCatalogFunctionDefinitionsByCode($HERIKA_BASE_FUNCTIONS_LOCAL ?? [])
);
chimTraceFunctionsIncludePhase(__LINE__, 'seed_rows_built', $startTime);
if (herikaActionCatalogDbReady()) {
    chimTraceFunctionsIncludePhase(__LINE__, 'seed_rows_db_sync_start', $startTime);
    herikaEnsureActionCatalogBaseRowsSeeded($seedActionRows);

    $legacyPreferenceRows = herikaGetActionCatalogRowsByCode();
    if (count($legacyPreferenceRows) === 0) {
        $legacyPreferenceRows = $seedActionRows;
    }

    herikaImportLegacyActionPreferences($legacyPreferenceRows);
    chimTraceFunctionsIncludePhase(__LINE__, 'seed_rows_db_sync_done', $startTime);
}

$isNpcMode = isset($GLOBALS["IS_NPC"]) && $GLOBALS["IS_NPC"];
$dbEnabledFunctions = herikaLoadEnabledActionCodesForMode($isNpcMode, true);
$GLOBALS["ENABLED_FUNCTIONS"] = herikaActionCatalogDbReady()
    ? $dbEnabledFunctions
    : array_values(array_unique($ENABLED_FUNCTIONS_LOCAL));

chimTraceFunctionsIncludePhase(__LINE__, 'enabled_functions_loaded_from_runtime', $startTime);

$folderPath = __DIR__ . DIRECTORY_SEPARATOR . "../ext/";
chimTraceFunctionsIncludePhase(__LINE__, 'ext_function_scan_start', $startTime);
requireFunctionFilesRecursively($folderPath);
chimTraceFunctionsIncludePhase(__LINE__, 'ext_function_scan_done', $startTime);

if (herikaActionCatalogDbReady()) {
    // Do not re-seed core_action from the live runtime list here.
    // Runtime functions may already include DB-backed custom actions that
    // intentionally share an action_name with shipped actions (for example
    // CHIM-Custom NFF wrappers like WaitHere / FollowMe / BehindMe). If we
    // write back from the runtime list, those custom rows can be mistaken for
    // built-in functions and get rewritten as source=function.php rows.
    chimTraceFunctionsIncludePhase(__LINE__, 'runtime_function_merge_start', $startTime);
    herikaActionCatalogApplyRowsToRuntimeFunctions();
    chimTraceFunctionsIncludePhase(__LINE__, 'runtime_function_merge_done', $startTime);
}

// Why is this here?
if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . "lang" . DIRECTORY_SEPARATOR . $GLOBALS["CORE_LANG"] . DIRECTORY_SEPARATOR . "prompts.php")) {
    require __DIR__ . DIRECTORY_SEPARATOR . "lang" . DIRECTORY_SEPARATOR . $GLOBALS["CORE_LANG"] . DIRECTORY_SEPARATOR . "prompts.php";
}

if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . "../prompts/prompts_custom.php")) {
    require __DIR__ . DIRECTORY_SEPARATOR . "../prompts/prompts_custom.php";
}

chimTraceFunctionsIncludePhase(__LINE__, 'prompt_overrides_loaded', $startTime);

// Delete non wanted functions

chimTraceFunctionsIncludePhase(__LINE__, 'enabled_function_filter_start', $startTime);
if (!HeldItems::hasHeldItems()) {
    $GLOBALS["ENABLED_FUNCTIONS"] = array_values(array_filter(
        $GLOBALS["ENABLED_FUNCTIONS"],
        static fn($codeName) => strcasecmp((string) $codeName, 'TakeHeldItem') !== 0
    ));
}

$enabledFunctionSet = array_fill_keys($GLOBALS["ENABLED_FUNCTIONS"], true);
foreach ($GLOBALS["FUNCTIONS"] as $n => $v) {
    $codeName = getFunctionCodeName($v["name"]);
    if ($codeName === false) {
        error_log("[FUNCTION] Warning: Could not get code name for function: {$v["name"]}");
        continue;
    }
    if (!isset($enabledFunctionSet[$codeName])) {
        error_log("[FUNCTION] Removing $n {$v["name"]}:$codeName");
        unset($GLOBALS["FUNCTIONS"][$n]);
    } 
    
    $GLOBALS["DEFINED_FUNCTIONS"][] = $codeName;
    
}

chimTraceFunctionsIncludePhase(__LINE__, 'enabled_function_filter_done', $startTime);

chimTraceFunctionsIncludePhase(__LINE__, 'bug_func_write_start', $startTime);
file_put_contents(__DIR__ . "/../log/bug_func.txt", print_r($GLOBALS["FUNCTIONS"], true));
file_put_contents(__DIR__ . "/../log/bug_func.txt", print_r($GLOBALS["ENABLED_FUNCTIONS"], true), FILE_APPEND);
file_put_contents(__DIR__ . "/../log/bug_func.txt", print_r($GLOBALS["ENABLED_FUNCTIONS"], true), FILE_APPEND);
chimTraceFunctionsIncludePhase(__LINE__, 'bug_func_write_done', $startTime);

$GLOBALS["FUNCTIONS"] = array_values($GLOBALS["FUNCTIONS"]); //Get rid of array keys

chimTraceFunctionsIncludePhase(__LINE__, 'functions_reindexed', $startTime);


// POST FILTER HOOK. Used for cleaning actions returned by LLM
// We are putting this here because we want this actions to be executed serverside via ScriptProxy
// They will NOT be sent to DLL for execution using the standard method

require_once __DIR__ . "/../lib/scriptproxy_papyrus.php";
require_once __DIR__ . "/../lib/core/activity_status.php";

chimTraceFunctionsIncludePhase(__LINE__, 'post_filter_dependencies_loaded', $startTime);

// action_post_process_fnct_ex is an arrya containing functions that process the actions after they are generated by the LLM
// more working examples in data_functions.php
$GLOBALS["action_post_process_fnct_ex"][]=function($actions) {
    
    global $gameRequest;

    $actionsCopy=$actions;
    foreach ($actions as $n=>$action) {
        
        $actionParts=explode("|",$action);
        $actionParts2=explode("@",$actionParts[2]);
        
        if (isset($actionParts2[0])) {
            $actionCodeNameResolved = getFunctionCodeName($actionParts2[0]);
            if ($actionCodeNameResolved === false || trim(strval($actionCodeNameResolved)) === '') {
                $actionCodeNameResolved = $actionParts2[0];
            }

            if (function_exists('chimQuestEngineIsActionSuppressedForTurn') && chimQuestEngineIsActionSuppressedForTurn($actionCodeNameResolved)) {
                $reasons = $GLOBALS['CHIM_QUEST_SUPPRESSED_ACTION_REASONS'][$actionCodeNameResolved] ?? array();
                $reasonText = is_array($reasons) && !empty($reasons) ? implode(', ', $reasons) : 'current quest beat';
                error_log("[AI Quest] Dropping suppressed action {$actionCodeNameResolved}: {$reasonText}");
                unset($actionsCopy[$n]);
                continue;
            }

            if (herikaActionCatalogExecuteScriptProxyAction($action)) {
                unset($actionsCopy[$n]);
                continue;
            }

            // Parameter part 
            if ($actionCodeNameResolved=="Drink") {
               
                error_log("[ACTION POSTFILTER Drink] Executed server-side");
                // Make NPC to toast
                $npcMaster = new Npcmaster();
                $npcData   = $npcMaster->getByName($actionParts[0]);

                $metadata=$npcMaster->getMetadata($npcData);

                $activityStatus = chimNormalizeActivityStatus($metadata);
                if ((!empty($metadata["furniture"]) && $metadata["furniture"]=="Chair") ||
                    (!empty($activityStatus["use_type"]) && $activityStatus["use_type"] === "chair")) {
                    $animation="0x00065d07";//ChairDrinkingStart (0x00065d07)
                }  else 
                    $animation="0x00103656";//DrinkIdle (0x00065d07)

                $skyrimCmd = new SkyrimCommandBuilder();
                $json      = $skyrimCmd->Actor->PlayIdle("0x{$npcData["refid"]}", $animation);// DrinkIdle Start                $skyrimCmd->send($json);
                $skyrimCmd->send(cmd: $json);

                unset($actionsCopy[$n]);// Remove action from list, so client does not execute it
                
                $GLOBALS["db"]->insert(
                    'actions_issued',
                    array(
                        'action' => "Drink",
                        'fullcall' =>$actionParts[0]."|".$actionParts[1]."|".$actionParts[2],
                        'actorname'=> $actionParts[0],
                        'ts' => $gameRequest[1],
                        'gamets' => $gameRequest[2],
                        'localts'=>time(),
                        'original'=>chimPrepareActionsIssuedOriginalValue('')
                    )
                );

                error_log("[ACTION POSTFILTER Drink] Executed server-side");

            } else  if ($actionCodeNameResolved=="Toast") {
                
                $npcMaster = new Npcmaster();
                $npcData   = $npcMaster->getByName($actionParts[0]);

                $skyrimCmd = new SkyrimCommandBuilder();
                $json      = $skyrimCmd->Actor->PlayIdle("0x{$npcData["refid"]}", "0x0010528a");// Toast Start                $skyrimCmd->send($json);
                $skyrimCmd->send(cmd: $json);

                
                $totalChars = 0;
                if (isset($GLOBALS["DEBUG"]["BUFFER"]) && is_array($GLOBALS["DEBUG"]["BUFFER"])) {
                    foreach ($GLOBALS["DEBUG"]["BUFFER"] as $item) {
                        $str = is_string($item) ? $item : (string)$item;
                        $totalChars += mb_strlen($str, 'UTF-8');
                    }
                } 
                error_log("[POST-FILTER] Toast: Current buffer size before delay: " . $totalChars . " chars");
                $timeToWait= ceil($totalChars / 12); // 1 second per 12 chars
                
                $json      = $skyrimCmd->Actor->PlayIdle("0x{$npcData["refid"]}", "0x00103656");// DrinkIdle Start                $skyrimCmd->send($json);
                $skyrimCmd->send(cmd: $json, localts:time()+$timeToWait);  // 30 seconds later actually drink to avoid NPC stuck in toast animation

                unset($actionsCopy[$n]);// Remove action from list, so client does not execute it

                $GLOBALS["db"]->insert(
                    'actions_issued',
                    array(
                        'action' => "Toast",
                        'fullcall' =>$actionParts[0]."|".$actionParts[1]."|".$actionParts[2],
                        'actorname'=> $actionParts[0],
                        'ts' => $gameRequest[1],
                        'gamets' => $gameRequest[2],
                        'localts'=>time(),
                        'original'=>chimPrepareActionsIssuedOriginalValue('')
                    )
                );

                error_log("[ACTION POSTFILTER Toast] Executed server-side");

            } else if (preg_match('/^Train(.+)$/', $actionCodeNameResolved, $matches)) {
                // Training function called - send rolecommand to open training menu
                $GLOBALS["db"]->insert(
                    'responselog',
                    array(
                        'localts' => time(),
                        'sent' => 0,
                        'actor' => "rolemaster",
                        'text' => '',
                        'action' => "rolecommand|ShowTrainingMenu@{$actionParts[0]}",
                        'tag' => ""
                    )
                );
                
                $GLOBALS["db"]->insert(
                    'actions_issued',
                    array(
                        'action' => "Training",
                        'fullcall' =>$actionParts[0]."|".$actionParts[1]."|".$actionParts[2],
                        'actorname'=> $actionParts[0],
                        'ts' => $gameRequest[1],
                        'gamets' => $gameRequest[2],
                        'localts'=>time(),
                        'original'=>chimPrepareActionsIssuedOriginalValue('')
                    )
                );

                error_log("[ACTION POSTFILTER Train] Executed server-side");
                unset($actionsCopy[$n]);// Remove action from list, so client does not execute it

            } else if ($actionCodeNameResolved == "SpawnItem") {
                $rawParameter = implode("@", array_slice($actionParts2, 1));
                $payload = decodeFunctionExecutionParameterPayload($rawParameter);
                if (!is_array($payload)) {
                    $payload = [];
                }

                $playerName = trim(strval($GLOBALS["PLAYER_NAME"] ?? "Player"));
                $targetName = trim(strval($payload["target"] ?? ""));
                $itemName = trim(strval($payload["item"] ?? ""));
                $itemAmount = herikaNormalizePositiveActionAmount($payload["amount"] ?? 1, 1, 1000);

                $targetName = herikaNormalizeNarratorActorTargetForRoleCommand($targetName);

                if ($itemName === '') {
                    error_log("[ACTION POSTFILTER SpawnItem] Missing item name");
                    unset($actionsCopy[$n]);
                    continue;
                }

                $resolvedItem = herikaResolveSpawnItemDescriptionMatch($itemName);
                if (empty($resolvedItem['ok'])) {
                    $safeItemName = str_replace('@', '', $itemName);
                    $reason = strval($resolvedItem['error'] ?? 'unknown_error');
                    error_log("[ACTION POSTFILTER SpawnItem] Could not resolve '{$safeItemName}' ({$reason})");

                    $GLOBALS["db"]->insert(
                        'responselog',
                        array(
                            'localts' => time(),
                            'sent' => 0,
                            'actor' => "rolemaster",
                            'text' => '',
                            'action' => "rolecommand|DebugNotification@Could not resolve item {$safeItemName} for Spawn_Item",
                            'tag' => ""
                        )
                    );

                    unset($actionsCopy[$n]);
                    continue;
                }

                $targetNameEscaped = str_replace('@', '', $targetName);
                $resolvedItemName = str_replace('@', '', trim(strval($resolvedItem['name'] ?? $itemName)));
                $runtimeFormId = str_replace('@', '', trim(strval($resolvedItem['runtime_formid'] ?? '')));

                if ($runtimeFormId === '') {
                    error_log("[ACTION POSTFILTER SpawnItem] Resolved item missing runtime formid for {$resolvedItemName}");
                    unset($actionsCopy[$n]);
                    continue;
                }

                $roleCommand = "rolecommand|SpawnItemRaw@{$targetNameEscaped}@{$runtimeFormId}@{$itemAmount}@{$resolvedItemName}";

                $GLOBALS["db"]->insert(
                    'responselog',
                    array(
                        'localts' => time(),
                        'sent' => 0,
                        'actor' => "rolemaster",
                        'text' => '',
                        'action' => $roleCommand,
                        'tag' => ""
                    )
                );

                $GLOBALS["db"]->insert(
                    'actions_issued',
                    array(
                        'action' => "SpawnItem",
                        'fullcall' => $actionParts[0] . "|" . $actionParts[1] . "|" . $actionParts[2],
                        'actorname' => $actionParts[0],
                        'ts' => $gameRequest[1],
                        'gamets' => $gameRequest[2],
                        'localts' => time(),
                        'original' => chimPrepareActionsIssuedOriginalValue('')
                    )
                );

                error_log("[ACTION POSTFILTER SpawnItem] Queued {$roleCommand}");
                unset($actionsCopy[$n]);

            } else if ($actionCodeNameResolved == "SpawnGold") {
                $rawParameter = implode("@", array_slice($actionParts2, 1));
                $payload = decodeFunctionExecutionParameterPayload($rawParameter);
                if (!is_array($payload)) {
                    $payload = [];
                }

                $targetName = trim(strval($payload["target"] ?? ""));
                $goldAmountSource = $payload["amount"] ?? ($payload["item"] ?? 1);
                $goldAmount = herikaNormalizePositiveActionAmount($goldAmountSource, 1, 1000000);

                $targetName = herikaNormalizeNarratorActorTargetForRoleCommand($targetName);
                $targetNameEscaped = str_replace('@', '', $targetName);
                $roleCommand = "rolecommand|SpawnGoldRaw@{$targetNameEscaped}@{$goldAmount}";

                $GLOBALS["db"]->insert(
                    'responselog',
                    array(
                        'localts' => time(),
                        'sent' => 0,
                        'actor' => "rolemaster",
                        'text' => '',
                        'action' => $roleCommand,
                        'tag' => ""
                    )
                );

                $GLOBALS["db"]->insert(
                    'actions_issued',
                    array(
                        'action' => "SpawnGold",
                        'fullcall' => $actionParts[0] . "|" . $actionParts[1] . "|" . $actionParts[2],
                        'actorname' => $actionParts[0],
                        'ts' => $gameRequest[1],
                        'gamets' => $gameRequest[2],
                        'localts' => time(),
                        'original' => chimPrepareActionsIssuedOriginalValue('')
                    )
                );

                error_log("[ACTION POSTFILTER SpawnGold] Queued {$roleCommand}");
                unset($actionsCopy[$n]);

            } else if ($actionCodeNameResolved == "SpawnNPC") {
                $rawParameter = implode("@", array_slice($actionParts2, 1));
                $payload = decodeFunctionExecutionParameterPayload($rawParameter);
                if (!is_array($payload)) {
                    $payload = [];
                }

                $latestNarratorInputText = herikaGetLatestNarratorInputText();
                $requestedTemplateKey = trim(strval($payload["target"] ?? ''));
                $requestedTemplateItem = trim(strval($payload["item"] ?? ''));
                $normalizedRequestedTarget = herikaNormalizeNarratorActorTargetForRoleCommand($requestedTemplateKey, false);
                if (($requestedTemplateKey === '' || $normalizedRequestedTarget === 'PLAYER') && $requestedTemplateItem !== '') {
                    $requestedTemplateKey = $requestedTemplateItem;
                }
                if ($requestedTemplateKey === '') {
                    $requestedTemplateKey = $latestNarratorInputText;
                }

                $spawnAmount = herikaNormalizePositiveActionAmount($payload["amount"] ?? 1, 1, 10);
                $resolvedTemplate = herikaResolveSpawnNpcTemplateMatch($requestedTemplateKey, $latestNarratorInputText);

                if (empty($resolvedTemplate['ok'])) {
                    $safeTemplateKey = str_replace('@', '', trim(strval($requestedTemplateKey)));
                    $reason = strval($resolvedTemplate['error'] ?? 'unknown_error');
                    error_log("[ACTION POSTFILTER SpawnNPC] Could not resolve '{$safeTemplateKey}' ({$reason})");

                    $GLOBALS["db"]->insert(
                        'responselog',
                        array(
                            'localts' => time(),
                            'sent' => 0,
                            'actor' => "rolemaster",
                            'text' => '',
                            'action' => "rolecommand|DebugNotification@Could not resolve NPC template {$safeTemplateKey} for Spawn_NPC",
                            'tag' => ""
                        )
                    );

                    unset($actionsCopy[$n]);
                    continue;
                }

                $templateKey = str_replace('@', '', trim(strval($resolvedTemplate['template_key'] ?? $requestedTemplateKey)));
                $runtimeFormId = str_replace('@', '', trim(strval($resolvedTemplate['runtime_formid'] ?? '')));
                if ($runtimeFormId === '') {
                    error_log("[ACTION POSTFILTER SpawnNPC] Resolved template missing runtime formid for {$templateKey}");
                    unset($actionsCopy[$n]);
                    continue;
                }

                $roleCommand = "rolecommand|SpawnNPCRaw@{$templateKey}@{$runtimeFormId}@{$spawnAmount}";

                $GLOBALS["db"]->insert(
                    'responselog',
                    array(
                        'localts' => time(),
                        'sent' => 0,
                        'actor' => "rolemaster",
                        'text' => '',
                        'action' => $roleCommand,
                        'tag' => ""
                    )
                );

                $GLOBALS["db"]->insert(
                    'actions_issued',
                    array(
                        'action' => "SpawnNPC",
                        'fullcall' => $actionParts[0] . "|" . $actionParts[1] . "|" . $actionParts[2],
                        'actorname' => $actionParts[0],
                        'ts' => $gameRequest[1],
                        'gamets' => $gameRequest[2],
                        'localts' => time(),
                        'original' => chimPrepareActionsIssuedOriginalValue($templateKey)
                    )
                );

                error_log("[ACTION POSTFILTER SpawnNPC] Queued {$roleCommand}");
                unset($actionsCopy[$n]);

            } else if ($actionCodeNameResolved == "CreateNewNPC") {
                $rawParameter = implode("@", array_slice($actionParts2, 1));
                $payload = decodeFunctionExecutionParameterPayload($rawParameter);
                if (!is_array($payload)) {
                    $payload = [];
                }

                $creationBrief = trim(strval($payload["target"] ?? ''));
                if ($creationBrief === '') {
                    $creationBrief = herikaGetLatestNarratorInputText();
                }

                if ($creationBrief === '') {
                    error_log("[ACTION POSTFILTER CreateNewNPC] Missing creation brief");
                    $GLOBALS["db"]->insert(
                        'responselog',
                        array(
                            'localts' => time(),
                            'sent' => 0,
                            'actor' => "rolemaster",
                            'text' => '',
                            'action' => "rolecommand|DebugNotification@Create_New_NPC requires a short creation brief",
                            'tag' => ""
                        )
                    );
                    unset($actionsCopy[$n]);
                    continue;
                }

                $creationBrief = str_replace(["\r", "\n"], ' ', $creationBrief);
                $creationBrief = preg_replace('/\s+/u', ' ', $creationBrief) ?? $creationBrief;
                $creationBrief = trim(str_replace('@', '', $creationBrief));

                if (!herikaQueueNarratorCreateNewNpc($creationBrief)) {
                    error_log("[ACTION POSTFILTER CreateNewNPC] Failed to invoke rolemaster spawn for '{$creationBrief}'");
                    $GLOBALS["db"]->insert(
                        'responselog',
                        array(
                            'localts' => time(),
                            'sent' => 0,
                            'actor' => "rolemaster",
                            'text' => '',
                            'action' => "rolecommand|DebugNotification@Could not start Create_New_NPC",
                            'tag' => ""
                        )
                    );
                    unset($actionsCopy[$n]);
                    continue;
                }

                $GLOBALS["db"]->insert(
                    'actions_issued',
                    array(
                        'action' => "CreateNewNPC",
                        'fullcall' => $actionParts[0] . "|" . $actionParts[1] . "|" . $actionParts[2],
                        'actorname' => $actionParts[0],
                        'ts' => $gameRequest[1],
                        'gamets' => $gameRequest[2],
                        'localts' => time(),
                        'original' => chimPrepareActionsIssuedOriginalValue($creationBrief)
                    )
                );

                error_log("[ACTION POSTFILTER CreateNewNPC] Started rolemaster spawn for {$creationBrief}");
                unset($actionsCopy[$n]);

            } else if ($actionCodeNameResolved == "DirectorCommand") {
                $rawParameter = implode("@", array_slice($actionParts2, 1));
                $payload = decodeFunctionExecutionParameterPayload($rawParameter);
                if (!is_array($payload)) {
                    $payload = [];
                }

                $directorBrief = trim(strval($payload["target"] ?? ""));
                if ($directorBrief === '') {
                    $directorBrief = herikaGetLatestNarratorInputText();
                }

                if ($directorBrief === '') {
                    error_log("[ACTION POSTFILTER DirectorCommand] Missing director brief");
                    $GLOBALS["db"]->insert(
                        'responselog',
                        array(
                            'localts' => time(),
                            'sent' => 0,
                            'actor' => "rolemaster",
                            'text' => '',
                            'action' => "rolecommand|DebugNotification@Director_Command requires a short director brief",
                            'tag' => ""
                        )
                    );
                    unset($actionsCopy[$n]);
                    continue;
                }

                $directorBrief = str_replace(["\r", "\n"], ' ', $directorBrief);
                $directorBrief = preg_replace('/\s+/u', ' ', $directorBrief) ?? $directorBrief;
                $directorBrief = trim(str_replace('@', '', $directorBrief));

                if (!herikaQueueNarratorDirectorCommand($directorBrief)) {
                    error_log("[ACTION POSTFILTER DirectorCommand] Failed to invoke rolemaster instruction for '{$directorBrief}'");
                    $GLOBALS["db"]->insert(
                        'responselog',
                        array(
                            'localts' => time(),
                            'sent' => 0,
                            'actor' => "rolemaster",
                            'text' => '',
                            'action' => "rolecommand|DebugNotification@Could not start Director_Command",
                            'tag' => ""
                        )
                    );
                    unset($actionsCopy[$n]);
                    continue;
                }

                $GLOBALS["db"]->insert(
                    'actions_issued',
                    array(
                        'action' => "DirectorCommand",
                        'fullcall' => $actionParts[0] . "|" . $actionParts[1] . "|" . $actionParts[2],
                        'actorname' => $actionParts[0],
                        'ts' => $gameRequest[1],
                        'gamets' => $gameRequest[2],
                        'localts' => time(),
                        'original' => chimPrepareActionsIssuedOriginalValue($directorBrief)
                    )
                );

                error_log("[ACTION POSTFILTER DirectorCommand] Started rolemaster instruction for {$directorBrief}");
                unset($actionsCopy[$n]);

            } else if ($actionCodeNameResolved == "TeleportNPC") {
                $rawParameter = implode("@", array_slice($actionParts2, 1));
                $payload = decodeFunctionExecutionParameterPayload($rawParameter);
                if (!is_array($payload)) {
                    $payload = [];
                }

                $requestedTarget = trim(strval($payload["target"] ?? ""));
                $destinationName = trim(strval($payload["item"] ?? ""));
                $targetName = herikaNormalizeNarratorActorTargetForRoleCommand($requestedTarget);
                $playerName = trim(strval($GLOBALS["PLAYER_NAME"] ?? "Player"));

                if ($destinationName === '' && $requestedTarget !== '') {
                    $requestedTargetLower = strtolower($requestedTarget);
                    $looksLikePlayerAlias = $requestedTargetLower === 'player'
                        || $requestedTargetLower === 'me'
                        || ($playerName !== '' && strcasecmp($requestedTarget, $playerName) === 0);
                    if (!$looksLikePlayerAlias) {
                        $destinationName = $requestedTarget;
                        $targetName = 'PLAYER';
                    }
                }

                if ($targetName === '') {
                    $targetName = $playerName;
                }

                if ($destinationName === '') {
                    error_log("[ACTION POSTFILTER TeleportNPC] Missing destination");
                    unset($actionsCopy[$n]);
                    continue;
                }

                $targetNameEscaped = str_replace('@', '', $targetName);
                $destinationNameEscaped = str_replace('@', '', $destinationName);
                $destinationLiteral = $GLOBALS["db"]->escape($destinationNameEscaped);
                $dbDestination = $GLOBALS["db"]->fetchOne("SELECT name, similarity(name, '{$destinationLiteral}') AS sim, formid FROM locations ORDER BY sim DESC LIMIT 1");
                $dbDestinationRegion = $GLOBALS["db"]->fetchOne("SELECT name, similarity(region, '{$destinationLiteral}') AS sim, formid FROM locations ORDER BY sim DESC LIMIT 1");

                $destinationFormId = '';
                $destinationLabel = $destinationNameEscaped;
                if (is_array($dbDestination) && is_array($dbDestinationRegion)) {
                    $useRegion = floatval($dbDestinationRegion["sim"] ?? 0) > floatval($dbDestination["sim"] ?? 0);
                    $resolvedDestination = $useRegion ? $dbDestinationRegion : $dbDestination;
                    $destinationFormId = trim(strval($resolvedDestination["formid"] ?? ''));
                    $destinationLabel = trim(strval($resolvedDestination["name"] ?? $destinationNameEscaped));
                }
                $destinationLabel = str_replace('@', '', $destinationLabel);

                $roleCommand = $destinationFormId !== ''
                    ? "rolecommand|TeleportNPCRaw@{$targetNameEscaped}@{$destinationFormId}@{$destinationLabel}"
                    : "rolecommand|TeleportNPC@{$targetNameEscaped}@{$destinationNameEscaped}";

                $GLOBALS["db"]->insert(
                    'responselog',
                    array(
                        'localts' => time(),
                        'sent' => 0,
                        'actor' => "rolemaster",
                        'text' => '',
                        'action' => $roleCommand,
                        'tag' => ""
                    )
                );

                $GLOBALS["db"]->insert(
                    'actions_issued',
                    array(
                        'action' => "TeleportNPC",
                        'fullcall' => $actionParts[0] . "|" . $actionParts[1] . "|" . $actionParts[2],
                        'actorname' => $actionParts[0],
                        'ts' => $gameRequest[1],
                        'gamets' => $gameRequest[2],
                        'localts' => time(),
                        'original' => chimPrepareActionsIssuedOriginalValue('')
                    )
                );

                error_log("[ACTION POSTFILTER TeleportNPC] Queued {$roleCommand}");
                unset($actionsCopy[$n]);

            } else if ($actionCodeNameResolved == "KillTarget") {
                $rawParameter = implode("@", array_slice($actionParts2, 1));
                $payload = decodeFunctionExecutionParameterPayload($rawParameter);
                if (!is_array($payload)) {
                    $payload = [];
                }

                $requestedTarget = trim(strval($payload["target"] ?? ""));
                if ($requestedTarget === '') {
                    $requestedTarget = trim(strval($rawParameter));
                }

                $inferenceSources = [];
                $latestNarratorInput = herikaGetLatestNarratorInputText();
                if ($latestNarratorInput !== '') {
                    $inferenceSources[] = $latestNarratorInput;
                }
                if (isset($gameRequest[3]) && trim(strval($gameRequest[3])) !== '') {
                    $inferenceSources[] = trim(strval($gameRequest[3]));
                }

                $inferredTarget = '';
                foreach ($inferenceSources as $inferenceSource) {
                    $candidateTarget = herikaInferNarratorActorTargetFromText($inferenceSource);
                    if ($candidateTarget === '') {
                        continue;
                    }
                    $normalizedCandidateTarget = herikaNormalizeNarratorActorTargetForRoleCommand($candidateTarget, false);
                    if ($normalizedCandidateTarget !== '' && $normalizedCandidateTarget !== 'PLAYER') {
                        $inferredTarget = $candidateTarget;
                        break;
                    }
                    if ($inferredTarget === '') {
                        $inferredTarget = $candidateTarget;
                    }
                }

                $normalizedRequestedTarget = herikaNormalizeNarratorActorTargetForRoleCommand($requestedTarget, false);
                $normalizedInferredTarget = herikaNormalizeNarratorActorTargetForRoleCommand($inferredTarget, false);

                if ($normalizedRequestedTarget === '' && $normalizedInferredTarget !== '') {
                    $targetName = $normalizedInferredTarget;
                } else if ($normalizedRequestedTarget === 'PLAYER' && $normalizedInferredTarget !== '' && $normalizedInferredTarget !== 'PLAYER') {
                    $targetName = $normalizedInferredTarget;
                } else {
                    $targetName = $normalizedRequestedTarget;
                }

                if ($targetName === '') {
                    error_log("[ACTION POSTFILTER KillTarget] Missing target");
                    $GLOBALS["db"]->insert(
                        'responselog',
                        array(
                            'localts' => time(),
                            'sent' => 0,
                            'actor' => "rolemaster",
                            'text' => '',
                            'action' => "rolecommand|DebugNotification@Kill_Target requires a target",
                            'tag' => ""
                        )
                    );
                    unset($actionsCopy[$n]);
                    continue;
                }

                $targetNameEscaped = str_replace('@', '', $targetName);
                $roleCommand = "rolecommand|KillTargetRaw@{$targetNameEscaped}";

                $GLOBALS["db"]->insert(
                    'responselog',
                    array(
                        'localts' => time(),
                        'sent' => 0,
                        'actor' => "rolemaster",
                        'text' => '',
                        'action' => $roleCommand,
                        'tag' => ""
                    )
                );

                $GLOBALS["db"]->insert(
                    'actions_issued',
                    array(
                        'action' => "KillTarget",
                        'fullcall' => $actionParts[0] . "|" . $actionParts[1] . "|" . $actionParts[2],
                        'actorname' => $actionParts[0],
                        'ts' => $gameRequest[1],
                        'gamets' => $gameRequest[2],
                        'localts' => time(),
                        'original' => chimPrepareActionsIssuedOriginalValue('')
                    )
                );

                error_log("[ACTION POSTFILTER KillTarget] Queued {$roleCommand}");
                unset($actionsCopy[$n]);

            } else if ($actionCodeNameResolved=="StartRitualCeremony") {
                
                $npcMaster = new Npcmaster();
                $npcData   = $npcMaster->getByName($actionParts[0]);

                $defAnim="0x000f11e1";// IdleRitualSkull1
                $shader="0x00050f02";// RitualSkullShader
                if (isset($actionParts2[1])) {
                    //Religious, Magical, Cultural, Personal, Blood
                    if ($actionParts2[1]== "Religious") {
                        $defAnim="0x0006f300";// IdlePray
                    } else if ($actionParts2[1]== "Magical") {

                        $skyrimCmd = new SkyrimCommandBuilder();
                        $json      = $skyrimCmd->Actor->PlayIdle("0x{$npcData["refid"]}", "0x000f11e2");//IdleRitualSkull2
                        $skyrimCmd->send(cmd: $json);
                        $json = $skyrimCmd->EffectShader->Play("0x0005fb82", "0x{$npcData["refid"]}", 20);
                        $skyrimCmd->send(cmd: $json);
                        

                    } else if ($actionParts2[1]== "Cultural") {
                        $defAnim="0x000f11e4";// IdleCrouchedPray
                    } else if ($actionParts2[1]== "Personal") {
                        $defAnim="0x000f11e5";// IdleCrouchedPrayEnterInstant
                    } else if ($actionParts2[1]== "Blood") {
                        $skyrimCmd = new SkyrimCommandBuilder();
                        $json      = $skyrimCmd->Actor->PlayIdle("0x{$npcData["refid"]}", "0x000af886");//IdleHandCut
                        $skyrimCmd->send(cmd: $json);
                        $json = $skyrimCmd->EffectShader->Play("0x0010f505", "0x{$npcData["refid"]}", 20);
                        $skyrimCmd->send(cmd: $json);
                        $json      = $skyrimCmd->Actor->PlayIdle("0x{$npcData["refid"]}", "0x0006f300");//idlePray
                        $skyrimCmd->send(cmd: $json,    localts: time()+10);//10 seconds later
                        
                    } else {

                        $skyrimCmd = new SkyrimCommandBuilder();
                        $json      = $skyrimCmd->Actor->PlayIdle("0x{$npcData["refid"]}", $defAnim);
                        $skyrimCmd->send(cmd: $json);
                        $json = $skyrimCmd->EffectShader->Play($shader, "0x{$npcData["refid"]}", 20);
                        $skyrimCmd->send(cmd: $json);
                 }
                } else {

                    $skyrimCmd = new SkyrimCommandBuilder();
                    $json      = $skyrimCmd->Actor->PlayIdle("0x{$npcData["refid"]}", $defAnim);
                    $skyrimCmd->send(cmd: $json);
                    $json = $skyrimCmd->EffectShader->Play($shader, "0x{$npcData["refid"]}", 20);
                    $skyrimCmd->send(cmd: $json);
                }

                $GLOBALS["db"]->insert(
                    'rolemaster',
                    [
                        'localts' => time(),
                        'ttl' => 60,
                        'type' => "scenenote",
                        'data' => "{$actionParts[0]} is celebrating a ritual",
                    ]
                );
                unset($actionsCopy[$n]);// Remove action from list, so client does not execute it

                $GLOBALS["db"]->insert(
                    'actions_issued',
                    array(
                        'action' => "StartRitualCeremony",
                        'fullcall' =>$actionParts[0]."|".$actionParts[1]."|".$actionParts[2],
                        'actorname'=> $actionParts[0],
                        'ts' => $gameRequest[1],
                        'gamets' => $gameRequest[2],
                        'localts'=>time(),
                        'original'=>chimPrepareActionsIssuedOriginalValue('')
                    )
                );

                chimApplyNpcMetadataUpdatesByName($actionParts[0], [
                    'ritual_state' => [
                        'active' => true,
                        'type' => strval($actionParts2[1] ?? ''),
                        'started_at' => time(),
                        'gamets' => $gameRequest[2],
                    ],
                    'activity_status' => [
                        'current_action' => 'ritual',
                        'current_use' => strval($actionParts2[1] ?? ''),
                        'use_type' => 'ritual',
                        'timestamp' => (int) round(microtime(true) * 1000),
                        'gamets' => $gameRequest[2],
                    ],
                ]);

                error_log("[ACTION POSTFILTER StartRitualCeremony] Executed server-side");

            } else if ($actionCodeNameResolved=="EndRitualCeremony") {
                
                $npcMaster = new Npcmaster();
                $npcData   = $npcMaster->getByName($actionParts[0]);

                $skyrimCmd = new SkyrimCommandBuilder();
                $json      = $skyrimCmd->Actor->PlayIdle("0x{$npcData["refid"]}", "0x000f11e3");// IdleRitualSkull3
                $skyrimCmd->send(cmd: $json);

                $GLOBALS["db"]->insert(
                    'rolemaster',
                    [
                        'localts' => time(),
                        'ttl' => 30,
                        'type' => "scenenote",
                        'data' => "{$actionParts[0]} just ended the ritual celebration",
                    ]
                );
                unset($actionsCopy[$n]);// Remove action from list, so client does not execute it

                $GLOBALS["db"]->insert(
                    'actions_issued',
                    array(
                        'action' => "EndRitualCeremony",
                        'fullcall' =>$actionParts[0]."|".$actionParts[1]."|".$actionParts[2],
                        'actorname'=> $actionParts[0],
                        'ts' => $gameRequest[1],
                        'gamets' => $gameRequest[2],
                        'localts'=>time(),
                        'original'=>chimPrepareActionsIssuedOriginalValue('')
                    )
                );

                chimApplyNpcMetadataUpdatesByName($actionParts[0], [
                    'ritual_state' => null,
                    'activity_status' => [
                        'current_action' => 'idle',
                        'current_use' => '',
                        'use_type' => '',
                        'furniture_name' => '',
                        'timestamp' => (int) round(microtime(true) * 1000),
                        'gamets' => $gameRequest[2],
                    ],
                ]);

                error_log("[ACTION POSTFILTER Toast] Executed server-side");

            }
        }
    }

    return $actionsCopy;
};

error_log("TRACE:\t".__LINE__. "\t".__FILE__.":\t".(microtime(true) - $startTime));

?>
