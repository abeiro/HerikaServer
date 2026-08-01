<?php

if (!function_exists('chimNormalizePluginName')) {
    function chimNormalizePluginName($value): string
    {
        return trim((string) $value);
    }
}

if (!function_exists('chimNormalizeHexIdentifier')) {
    function chimNormalizeHexIdentifier($value, int $padLength = 0): string
    {
        $value = strtoupper(trim((string) $value));
        if ($value === '') {
            return '';
        }

        if (strpos($value, '0X') === 0) {
            $value = substr($value, 2);
        }

        $value = preg_replace('/[^0-9A-F]/', '', $value) ?? '';
        if ($value === '') {
            return '';
        }

        if ($padLength > 0 && strlen($value) < $padLength) {
            $value = str_pad($value, $padLength, '0', STR_PAD_LEFT);
        }

        return $value;
    }
}

if (!function_exists('chimNormalizeRuntimeFormId')) {
    function chimNormalizeRuntimeFormId($value): string
    {
        return chimNormalizeHexIdentifier($value, 8);
    }
}

if (!function_exists('chimNormalizeLocalFormId')) {
    function chimNormalizeLocalFormId($value): string
    {
        return chimNormalizeHexIdentifier($value, 8);
    }
}

if (!function_exists('chimNormalizeFormIdPrefix')) {
    function chimNormalizeFormIdPrefix($value): string
    {
        $prefix = chimNormalizeHexIdentifier($value);
        if ($prefix === '') {
            return '';
        }

        if (strlen($prefix) <= 2) {
            return str_pad(substr($prefix, -2), 2, '0', STR_PAD_LEFT);
        }

        return str_pad(substr($prefix, -5), 5, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('chimNormalizeLoadedGamePluginManifest')) {
    function chimNormalizeLoadedGamePluginManifest(array $plugins): array
    {
        $normalizedPlugins = [];
        foreach ($plugins as $pluginRow) {
            if (!is_array($pluginRow)) {
                continue;
            }

            $pluginName = chimNormalizePluginName($pluginRow['plugin_name'] ?? '');
            $formIdPrefix = chimNormalizeFormIdPrefix($pluginRow['formid_prefix'] ?? '');
            if ($pluginName === '' || $formIdPrefix === '') {
                continue;
            }

            $normalizedPlugins[strtolower($pluginName)] = [
                'plugin_name' => $pluginName,
                'is_light' => !empty($pluginRow['is_light']),
                'compile_index' => isset($pluginRow['compile_index']) ? intval($pluginRow['compile_index']) : 0,
                'small_file_compile_index' => isset($pluginRow['small_file_compile_index']) ? intval($pluginRow['small_file_compile_index']) : 0,
                'partial_index' => isset($pluginRow['partial_index']) ? intval($pluginRow['partial_index']) : 0,
                'formid_prefix' => $formIdPrefix,
            ];
        }

        return array_values($normalizedPlugins);
    }
}

if (!function_exists('chimIndexLoadedGamePluginsByPrefix')) {
    function chimIndexLoadedGamePluginsByPrefix(array $plugins): array
    {
        $pluginsByPrefix = [];
        foreach (chimNormalizeLoadedGamePluginManifest($plugins) as $pluginRow) {
            $pluginsByPrefix[strtoupper($pluginRow['formid_prefix'])] = $pluginRow;
        }

        return $pluginsByPrefix;
    }
}

if (!function_exists('chimIndexLoadedGamePluginsByName')) {
    function chimIndexLoadedGamePluginsByName(array $plugins): array
    {
        $pluginsByName = [];
        foreach (chimNormalizeLoadedGamePluginManifest($plugins) as $pluginRow) {
            $pluginsByName[strtolower($pluginRow['plugin_name'])] = $pluginRow;
        }

        return $pluginsByName;
    }
}

if (!function_exists('chimBuildStableFormReference')) {
    function chimBuildStableFormReference($pluginName, $localFormId): string
    {
        $pluginName = chimNormalizePluginName($pluginName);
        $localFormId = chimNormalizeLocalFormId($localFormId);
        if ($pluginName === '' || $localFormId === '') {
            return '';
        }

        return $pluginName . '|' . $localFormId;
    }
}

if (!function_exists('chimParseStableFormReference')) {
    function chimParseStableFormReference($value): ?array
    {
        $value = trim((string) $value);
        if ($value === '' || strpos($value, '|') === false) {
            return null;
        }

        $parts = explode('|', $value, 2);
        $pluginName = chimNormalizePluginName($parts[0] ?? '');
        $localFormId = chimNormalizeLocalFormId($parts[1] ?? '');
        if ($pluginName === '' || $localFormId === '') {
            return null;
        }

        return [
            'plugin_name' => $pluginName,
            'local_formid' => $localFormId,
            'stable_key' => chimBuildStableFormReference($pluginName, $localFormId),
        ];
    }
}

if (!function_exists('chimStableFormReferenceEquals')) {
    function chimStableFormReferenceEquals($left, $right): bool
    {
        $leftParsed = chimParseStableFormReference($left);
        $rightParsed = chimParseStableFormReference($right);
        if (!$leftParsed || !$rightParsed) {
            return false;
        }

        return strcasecmp($leftParsed['plugin_name'], $rightParsed['plugin_name']) === 0
            && $leftParsed['local_formid'] === $rightParsed['local_formid'];
    }
}

if (!function_exists('chimFactionEntryMatchesStableFormReference')) {
    function chimFactionEntryMatchesStableFormReference(array $factionEntry, $stableReference): bool
    {
        $parsedReference = chimParseStableFormReference($stableReference);
        if (!$parsedReference) {
            return false;
        }

        if (!empty($factionEntry['stable_key']) && chimStableFormReferenceEquals($factionEntry['stable_key'], $parsedReference['stable_key'])) {
            return true;
        }

        $pluginName = chimNormalizePluginName($factionEntry['plugin'] ?? '');
        $localFormId = chimNormalizeLocalFormId($factionEntry['local_formid'] ?? '');
        if ($pluginName === '' || $localFormId === '') {
            return false;
        }

        return strcasecmp($pluginName, $parsedReference['plugin_name']) === 0
            && $localFormId === $parsedReference['local_formid'];
    }
}

if (!function_exists('chimComputeRuntimeFormIdFromPrefix')) {
    function chimComputeRuntimeFormIdFromPrefix($formIdPrefix, $localFormId): ?string
    {
        $formIdPrefix = chimNormalizeFormIdPrefix($formIdPrefix);
        $localFormId = chimNormalizeLocalFormId($localFormId);
        if ($formIdPrefix === '' || $localFormId === '') {
            return null;
        }

        $localValue = hexdec($localFormId);

        if (strlen($formIdPrefix) === 2) {
            $runtimeValue = ((hexdec($formIdPrefix) & 0xFF) << 24) | ($localValue & 0x00FFFFFF);
            return sprintf('%08X', $runtimeValue & 0xFFFFFFFF);
        }

        if (strlen($formIdPrefix) === 5 && strpos($formIdPrefix, 'FE') === 0) {
            $lightIndex = hexdec(substr($formIdPrefix, 2));
            $runtimeValue = 0xFE000000 | (($lightIndex & 0x0FFF) << 12) | ($localValue & 0x00000FFF);
            return sprintf('%08X', $runtimeValue & 0xFFFFFFFF);
        }

        return null;
    }
}

if (!function_exists('chimGetLoadedGamePluginByName')) {
    function chimGetLoadedGamePluginByName($pluginName): ?array
    {
        static $pluginCache = [];

        $pluginName = chimNormalizePluginName($pluginName);
        if ($pluginName === '') {
            return null;
        }

        $cacheKey = strtolower($pluginName);
        if (array_key_exists($cacheKey, $pluginCache)) {
            return $pluginCache[$cacheKey];
        }

        global $db;
        if (!isset($db)) {
            return null;
        }

        $escapedPluginName = $db->escape($pluginName);
        $pluginRow = $db->fetchOne("
            SELECT plugin_name, is_light, compile_index, small_file_compile_index, partial_index, formid_prefix, updated_at
            FROM public.game_plugins
            WHERE LOWER(plugin_name) = LOWER('{$escapedPluginName}')
            LIMIT 1
        ");

        $pluginCache[$cacheKey] = is_array($pluginRow) ? $pluginRow : null;
        return $pluginCache[$cacheKey];
    }
}

if (!function_exists('chimGetLoadedGamePluginByFormIdPrefix')) {
    function chimGetLoadedGamePluginByFormIdPrefix($formIdPrefix): ?array
    {
        static $pluginCache = [];

        $formIdPrefix = chimNormalizeFormIdPrefix($formIdPrefix);
        if ($formIdPrefix === '') {
            return null;
        }

        $cacheKey = strtoupper($formIdPrefix);
        if (array_key_exists($cacheKey, $pluginCache)) {
            return $pluginCache[$cacheKey];
        }

        global $db;
        if (!isset($db)) {
            return null;
        }

        $escapedFormIdPrefix = $db->escape($formIdPrefix);
        $pluginRow = $db->fetchOne("
            SELECT plugin_name, is_light, compile_index, small_file_compile_index, partial_index, formid_prefix, updated_at
            FROM public.game_plugins
            WHERE formid_prefix = '{$escapedFormIdPrefix}'
            LIMIT 1
        ");

        $pluginCache[$cacheKey] = is_array($pluginRow) ? $pluginRow : null;
        return $pluginCache[$cacheKey];
    }
}

if (!function_exists('chimGetLoadedGamePluginByRuntimeFormId')) {
    function chimGetLoadedGamePluginByRuntimeFormId($runtimeFormId): ?array
    {
        $runtimeFormId = chimNormalizeRuntimeFormId($runtimeFormId);
        if ($runtimeFormId === '') {
            return null;
        }

        $formIdPrefix = (strpos($runtimeFormId, 'FE') === 0)
            ? substr($runtimeFormId, 0, 5)
            : substr($runtimeFormId, 0, 2);

        return chimGetLoadedGamePluginByFormIdPrefix($formIdPrefix);
    }
}

if (!function_exists('chimExtractLocalFormIdFromRuntimeFormId')) {
    function chimExtractLocalFormIdFromRuntimeFormId($runtimeFormId): string
    {
        $runtimeFormId = chimNormalizeRuntimeFormId($runtimeFormId);
        if ($runtimeFormId === '') {
            return '';
        }

        $runtimeValue = hexdec($runtimeFormId);
        if (strpos($runtimeFormId, 'FE') === 0) {
            return sprintf('%08X', $runtimeValue & 0x00000FFF);
        }

        return sprintf('%08X', $runtimeValue & 0x00FFFFFF);
    }
}

if (!function_exists('chimConvertRuntimeFormIdToStableReference')) {
    function chimConvertRuntimeFormIdToStableReference($runtimeFormId, ?array $pluginsByPrefix = null): ?string
    {
        $runtimeFormId = chimNormalizeRuntimeFormId($runtimeFormId);
        if (strlen($runtimeFormId) !== 8 || strpos($runtimeFormId, 'FF') === 0) {
            return null;
        }

        $formIdPrefix = (strpos($runtimeFormId, 'FE') === 0)
            ? substr($runtimeFormId, 0, 5)
            : substr($runtimeFormId, 0, 2);

        if ($pluginsByPrefix === null) {
            $pluginRow = chimGetLoadedGamePluginByFormIdPrefix($formIdPrefix);
        } else {
            $pluginRow = $pluginsByPrefix[strtoupper($formIdPrefix)] ?? null;
        }

        $localFormId = chimExtractLocalFormIdFromRuntimeFormId($runtimeFormId);
        if (!is_array($pluginRow) || empty($pluginRow['plugin_name']) || $localFormId === '') {
            return null;
        }

        $stableReference = chimBuildStableFormReference($pluginRow['plugin_name'], $localFormId);
        return $stableReference !== '' ? $stableReference : null;
    }
}

if (!function_exists('chimBuildLegacyDescriptionBaseId')) {
    function chimBuildLegacyDescriptionBaseId($runtimeFormId, ?array $pluginRow = null): string
    {
        $runtimeFormId = chimNormalizeRuntimeFormId($runtimeFormId);
        if ($runtimeFormId === '') {
            return '';
        }

        if (strpos($runtimeFormId, 'FE') === 0) {
            return 'FEXXX' . substr($runtimeFormId, -3);
        }

        if ($pluginRow === null) {
            $pluginRow = chimGetLoadedGamePluginByRuntimeFormId($runtimeFormId);
        }

        if (is_array($pluginRow) && !empty($pluginRow['plugin_name'])) {
            if (strcasecmp((string) $pluginRow['plugin_name'], 'Skyrim.esm') === 0) {
                return '';
            }
        } elseif (substr($runtimeFormId, 0, 2) === '00') {
            return '';
        }

        return 'XX' . substr($runtimeFormId, -6);
    }
}

if (!function_exists('chimBuildDescriptionBaseIdCandidates')) {
    function chimBuildDescriptionBaseIdCandidates($value): array
    {
        $candidates = [];
        $pushCandidate = function ($candidate) use (&$candidates): void {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                return;
            }

            if (strpos($candidate, '|') !== false) {
                $parsedStable = chimParseStableFormReference($candidate);
                if ($parsedStable) {
                    $candidate = $parsedStable['stable_key'];
                }
            } else {
                $candidate = strtoupper($candidate);
            }

            if (!in_array($candidate, $candidates, true)) {
                $candidates[] = $candidate;
            }
        };

        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }

        $parsedStableReference = chimParseStableFormReference($value);
        if ($parsedStableReference) {
            $pushCandidate($parsedStableReference['stable_key']);

            $pluginRow = chimGetLoadedGamePluginByName($parsedStableReference['plugin_name']);
            $runtimeFormId = null;
            if ($pluginRow && !empty($pluginRow['formid_prefix'])) {
                $runtimeFormId = chimComputeRuntimeFormIdFromPrefix(
                    $pluginRow['formid_prefix'],
                    $parsedStableReference['local_formid']
                );
            }

            if ($runtimeFormId) {
                $pushCandidate($runtimeFormId);
                $pushCandidate(chimBuildLegacyDescriptionBaseId($runtimeFormId, $pluginRow));
            }

            return $candidates;
        }

        $upperValue = strtoupper($value);
        if (strpos($upperValue, 'XX') === 0 || strpos($upperValue, 'FEXXX') === 0) {
            $pushCandidate($upperValue);
            return $candidates;
        }

        $runtimeFormId = chimNormalizeRuntimeFormId($value);
        if ($runtimeFormId === '') {
            return [];
        }

        $pushCandidate($runtimeFormId);

        $pluginRow = chimGetLoadedGamePluginByRuntimeFormId($runtimeFormId);
        $localFormId = chimExtractLocalFormIdFromRuntimeFormId($runtimeFormId);
        if ($pluginRow && !empty($pluginRow['plugin_name']) && $localFormId !== '') {
            $pushCandidate(chimBuildStableFormReference($pluginRow['plugin_name'], $localFormId));
        }

        $pushCandidate(chimBuildLegacyDescriptionBaseId($runtimeFormId, $pluginRow));

        return $candidates;
    }
}

if (!function_exists('chimResolveStableFormReferenceToRuntimeFormId')) {
    function chimResolveStableFormReferenceToRuntimeFormId($stableReference): ?string
    {
        $parsedReference = chimParseStableFormReference($stableReference);
        if (!$parsedReference) {
            return null;
        }

        $pluginRow = chimGetLoadedGamePluginByName($parsedReference['plugin_name']);
        if (!$pluginRow || empty($pluginRow['formid_prefix'])) {
            return null;
        }

        return chimComputeRuntimeFormIdFromPrefix($pluginRow['formid_prefix'], $parsedReference['local_formid']);
    }
}

if (!function_exists('chimReplaceLoadedGamePlugins')) {
    function chimReplaceLoadedGamePlugins(array $plugins): int
    {
        global $db;
        if (!isset($db)) {
            return 0;
        }

        $normalizedPlugins = chimNormalizeLoadedGamePluginManifest($plugins);

        if (count($normalizedPlugins) === 0) {
            return 0;
        }

        $db->execQuery('DELETE FROM public.game_plugins');

        foreach ($normalizedPlugins as $pluginRow) {
            $escapedPluginName = $db->escape($pluginRow['plugin_name']);
            $escapedFormIdPrefix = $db->escape($pluginRow['formid_prefix']);
            $isLight = $pluginRow['is_light'] ? 'TRUE' : 'FALSE';
            $compileIndex = intval($pluginRow['compile_index']);
            $smallFileCompileIndex = intval($pluginRow['small_file_compile_index']);
            $partialIndex = intval($pluginRow['partial_index']);

            $db->execQuery("
                INSERT INTO public.game_plugins (
                    plugin_name,
                    is_light,
                    compile_index,
                    small_file_compile_index,
                    partial_index,
                    formid_prefix,
                    updated_at
                ) VALUES (
                    '{$escapedPluginName}',
                    {$isLight},
                    {$compileIndex},
                    {$smallFileCompileIndex},
                    {$partialIndex},
                    '{$escapedFormIdPrefix}',
                    CURRENT_TIMESTAMP
                )
                ON CONFLICT (plugin_name) DO UPDATE SET
                    is_light = EXCLUDED.is_light,
                    compile_index = EXCLUDED.compile_index,
                    small_file_compile_index = EXCLUDED.small_file_compile_index,
                    partial_index = EXCLUDED.partial_index,
                    formid_prefix = EXCLUDED.formid_prefix,
                    updated_at = CURRENT_TIMESTAMP
            ");
        }

        return count($normalizedPlugins);
    }
}
