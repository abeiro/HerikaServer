<?php

if (!function_exists('chimParseStableFormReference')) {
    require_once(__DIR__ . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'game_plugins.php');
}

if (!function_exists('quest_asset_dataset_signatures')) {
    function quest_asset_dataset_signatures()
    {
        return [
            'item_types' => ['ALCH', 'ARMO', 'BOOK', 'INGR', 'KEYM', 'MISC', 'SCRL', 'SLGM', 'WEAP'],
            'npc_templates' => ['NPC_'],
            'npc_own_templates' => ['NPC_'],
            'outfit' => ['OTFT'],
            'weapons' => ['WEAP'],
        ];
    }
}

if (!function_exists('quest_asset_library_has_db')) {
    function quest_asset_library_has_db()
    {
        return isset($GLOBALS['db']) && is_object($GLOBALS['db']);
    }
}
if (!function_exists('quest_asset_exec_or_throw')) {
    function quest_asset_exec_or_throw($sql)
    {
        $result = $GLOBALS['db']->execQuery($sql);
        if ($result === false) {
            $detail = method_exists($GLOBALS['db'], 'GetLastError')
                ? trim((string) $GLOBALS['db']->GetLastError())
                : '';
            throw new RuntimeException(
                'Quest asset database operation failed' . ($detail !== '' ? ": {$detail}" : '.')
            );
        }
        return $result;
    }
}

if (!function_exists('quest_asset_encode_json_object')) {
    function quest_asset_encode_json_object($value)
    {
        $normalized = quest_asset_normalize_json_object($value);
        return json_encode(empty($normalized) ? (object) [] : $normalized, JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('quest_asset_required_plugins_available')) {
    function quest_asset_required_plugins_available($requiredPlugins)
    {
        if (is_string($requiredPlugins)) {
            $requiredPlugins = json_decode($requiredPlugins, true);
        }
        if (!is_array($requiredPlugins)) {
            return false;
        }
        foreach ($requiredPlugins as $pluginName) {
            if (chimGetLoadedGamePluginByName((string) $pluginName) === null) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('quest_asset_library_tables_ready')) {
    function quest_asset_library_tables_ready()
    {
        if (!quest_asset_library_has_db()) {
            return false;
        }

        foreach (['quest_asset_packs', 'quest_assets', 'quest_asset_groups', 'quest_asset_group_members'] as $table) {
            try {
                $tableCn = $GLOBALS['db']->escape($table);
                $row = $GLOBALS['db']->fetchOne("SELECT to_regclass('public.{$tableCn}') AS table_name");
                if (empty($row['table_name'])) {
                    return false;
                }
            } catch (Throwable $e) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('quest_asset_normalize_key')) {
    function quest_asset_normalize_key($value)
    {
        $key = strtolower(trim((string) $value));
        return preg_match('/^[a-z0-9_]+$/', $key) ? $key : null;
    }
}

if (!function_exists('quest_asset_normalize_stable_ref')) {
    function quest_asset_normalize_stable_ref($value)
    {
        $parsed = chimParseStableFormReference(trim((string) $value));
        return $parsed ? $parsed['stable_key'] : null;
    }
}

if (!function_exists('quest_asset_normalize_json_object')) {
    function quest_asset_normalize_json_object($value)
    {
        return is_array($value) && (empty($value) || array_is_list($value) === false) ? $value : [];
    }
}

if (!function_exists('quest_asset_is_json_object')) {
    function quest_asset_is_json_object($value)
    {
        return is_array($value) && (empty($value) || array_is_list($value) === false);
    }
}

if (!function_exists('quest_asset_manifest_validate')) {
    function quest_asset_manifest_validate($manifest)
    {
        $errors = [];
        if (!is_array($manifest) || array_is_list($manifest)) {
            return ['valid' => false, 'errors' => ['Manifest must be a JSON object.'], 'manifest' => null];
        }

        if (($manifest['schema'] ?? '') !== 'chim.quest-assets.v1') {
            $errors[] = "schema must be 'chim.quest-assets.v1'.";
        }

        $packInput = $manifest['pack'] ?? null;
        if (!quest_asset_is_json_object($packInput)) {
            $errors[] = 'pack must be a JSON object.';
            $packInput = [];
        }
        $packKey = quest_asset_normalize_key($packInput['key'] ?? '');
        if ($packKey === null) {
            $errors[] = 'pack.key must contain only lowercase letters, numbers, and underscores.';
        }

        $requiredPlugins = [];
        $requiredPluginsInput = $packInput['required_plugins'] ?? [];
        if (!is_array($requiredPluginsInput) || !array_is_list($requiredPluginsInput)) {
            $errors[] = 'pack.required_plugins must be a JSON array.';
            $requiredPluginsInput = [];
        }
        foreach ($requiredPluginsInput as $pluginIndex => $plugin) {
            $plugin = trim((string) $plugin);
            if ($plugin === '' || !preg_match('/\.(esm|esp|esl)$/i', $plugin)) {
                $errors[] = "pack.required_plugins[{$pluginIndex}] must be an ESM, ESP, or ESL filename.";
                continue;
            }
            $requiredPlugins[strtolower($plugin)] = $plugin;
        }

        $assetsInput = $manifest['assets'] ?? null;
        if (!is_array($assetsInput) || !array_is_list($assetsInput)) {
            $errors[] = 'assets must be a JSON array.';
            $assetsInput = [];
        }
        $groupsInput = $manifest['groups'] ?? null;
        if (!is_array($groupsInput) || !array_is_list($groupsInput)) {
            $errors[] = 'groups must be a JSON array.';
            $groupsInput = [];
        }
        $assets = [];
        $assetSignatures = [];
        foreach ($assetsInput as $index => $assetInput) {
            if (!quest_asset_is_json_object($assetInput)) {
                $errors[] = "assets[{$index}] must be an object.";
                continue;
            }

            $stableRef = quest_asset_normalize_stable_ref($assetInput['stable_ref'] ?? '');
            $signature = strtoupper(trim((string) ($assetInput['signature'] ?? '')));
            $safetyStatus = strtolower(trim((string) ($assetInput['safety_status'] ?? 'review')));
            if ($stableRef === null) {
                $errors[] = "assets[{$index}].stable_ref is invalid.";
                continue;
            }
            if (!preg_match('/^[A-Z0-9_]{4}$/', $signature)) {
                $errors[] = "assets[{$index}].signature must be a four-character record signature.";
            }
            if (!in_array($safetyStatus, ['approved', 'review', 'rejected'], true)) {
                $errors[] = "assets[{$index}].safety_status is invalid.";
            }
            if (isset($assets[strtolower($stableRef)])) {
                $errors[] = "Duplicate asset stable_ref '{$stableRef}'.";
                continue;
            }

            $parsed = chimParseStableFormReference($stableRef);
            $sourcePlugin = trim((string) ($assetInput['source_plugin'] ?? ($parsed['plugin_name'] ?? '')));
            if ($sourcePlugin === '' || strcasecmp($sourcePlugin, (string) ($parsed['plugin_name'] ?? '')) !== 0) {
                $errors[] = "assets[{$index}].source_plugin must match the plugin in stable_ref.";
            }
            $winningPlugin = trim((string) ($assetInput['winning_plugin'] ?? $sourcePlugin));
            if ($winningPlugin === '' || !preg_match('/\.(esm|esp|esl)$/i', $winningPlugin)) {
                $errors[] = "assets[{$index}].winning_plugin must be an ESM, ESP, or ESL filename.";
            }
            $metadata = $assetInput['metadata'] ?? [];
            if (!quest_asset_is_json_object($metadata)) {
                $errors[] = "assets[{$index}].metadata must be a JSON object.";
                $metadata = [];
            }

            $assets[strtolower($stableRef)] = [
                'stable_ref' => $stableRef,
                'signature' => $signature,
                'editor_id' => trim((string) ($assetInput['editor_id'] ?? '')),
                'display_name' => trim((string) ($assetInput['display_name'] ?? '')),
                'source_plugin' => $sourcePlugin,
                'winning_plugin' => $winningPlugin,
                'metadata' => $metadata,
                'safety_status' => $safetyStatus,
                'active' => !array_key_exists('active', $assetInput) || (bool) $assetInput['active'],
            ];
            $assetSignatures[strtolower($stableRef)] = $signature;
        }

        $allowedSignatures = quest_asset_dataset_signatures();
        $groups = [];
        $groupIdentities = [];
        foreach ($groupsInput as $index => $groupInput) {
            if (!quest_asset_is_json_object($groupInput)) {
                $errors[] = "groups[{$index}] must be an object.";
                continue;
            }

            $dataset = strtolower(trim((string) ($groupInput['dataset'] ?? '')));
            $groupKey = quest_asset_normalize_key($groupInput['key'] ?? '');
            if (!isset($allowedSignatures[$dataset])) {
                $errors[] = "groups[{$index}].dataset is unsupported.";
                continue;
            }
            if ($groupKey === null) {
                $errors[] = "groups[{$index}].key is invalid.";
                continue;
            }
            $groupIdentity = $dataset . '|' . $groupKey;
            if (isset($groupIdentities[$groupIdentity])) {
                $errors[] = "Duplicate group '{$groupIdentity}'.";
                continue;
            }
            $groupIdentities[$groupIdentity] = true;

            $selectionPolicy = $groupInput['selection_policy'] ?? [];
            if (!quest_asset_is_json_object($selectionPolicy)) {
                $errors[] = "groups[{$index}].selection_policy must be a JSON object.";
                $selectionPolicy = [];
            }
            if (array_key_exists('fallback_group', $selectionPolicy)) {
                $fallbackGroup = quest_asset_normalize_key($selectionPolicy['fallback_group']);
                if ($fallbackGroup === null || $fallbackGroup === $groupKey) {
                    $errors[] = "groups[{$index}].selection_policy.fallback_group must be a different valid group key.";
                } else {
                    $selectionPolicy['fallback_group'] = $fallbackGroup;
                }
            }

            $members = [];
            $memberIdentities = [];
            $membersInput = $groupInput['members'] ?? [];
            if (!is_array($membersInput) || !array_is_list($membersInput)) {
                $errors[] = "groups[{$index}].members must be a JSON array.";
                $membersInput = [];
            }
            foreach ($membersInput as $memberIndex => $memberInput) {
                $memberInput = is_array($memberInput) ? $memberInput : ['stable_ref' => $memberInput];
                $stableRef = quest_asset_normalize_stable_ref($memberInput['stable_ref'] ?? '');
                if ($stableRef === null || !isset($assets[strtolower($stableRef)])) {
                    $errors[] = "groups[{$index}].members[{$memberIndex}] references an asset missing from this manifest.";
                    continue;
                }

                $signature = $assetSignatures[strtolower($stableRef)] ?? '';
                if (!in_array($signature, $allowedSignatures[$dataset], true)) {
                    $errors[] = "Asset '{$stableRef}' ({$signature}) cannot be used in dataset '{$dataset}'.";
                    continue;
                }
                $memberIdentity = strtolower($stableRef);
                if (isset($memberIdentities[$memberIdentity])) {
                    $errors[] = "Duplicate member '{$stableRef}' in group '{$groupIdentity}'.";
                    continue;
                }
                $memberIdentities[$memberIdentity] = true;

                $weight = intval($memberInput['weight'] ?? 1);
                if ($weight < 1 || $weight > 100) {
                    $errors[] = "groups[{$index}].members[{$memberIndex}].weight must be between 1 and 100.";
                    $weight = 1;
                }
                $constraints = $memberInput['constraints'] ?? [];
                if (!quest_asset_is_json_object($constraints)) {
                    $errors[] = "groups[{$index}].members[{$memberIndex}].constraints must be a JSON object.";
                    $constraints = [];
                }
                $members[] = [
                    'stable_ref' => $stableRef,
                    'weight' => $weight,
                    'constraints' => $constraints,
                    'note' => trim((string) ($memberInput['note'] ?? '')),
                    'active' => !array_key_exists('active', $memberInput) || (bool) $memberInput['active'],
                ];
            }

            $groups[] = [
                'dataset' => $dataset,
                'key' => $groupKey,
                'label' => trim((string) ($groupInput['label'] ?? ucwords(str_replace('_', ' ', $groupKey)))),
                'description' => trim((string) ($groupInput['description'] ?? '')),
                'selection_policy' => $selectionPolicy,
                'active' => !array_key_exists('active', $groupInput) || (bool) $groupInput['active'],
                'members' => $members,
            ];
        }

        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors, 'manifest' => null];
        }

        $normalized = [
            'schema' => 'chim.quest-assets.v1',
            'pack' => [
                'key' => $packKey,
                'label' => trim((string) ($packInput['label'] ?? ucwords(str_replace('_', ' ', $packKey)))),
                'game' => trim((string) ($packInput['game'] ?? 'SkyrimSE')),
                'version' => trim((string) ($packInput['version'] ?? '1')),
                'required_plugins' => array_values($requiredPlugins),
                'source' => trim((string) ($packInput['source'] ?? '')),
                'note' => trim((string) ($packInput['note'] ?? '')),
                'active' => !array_key_exists('active', $packInput) || (bool) $packInput['active'],
            ],
            'assets' => array_values($assets),
            'groups' => $groups,
        ];

        return ['valid' => true, 'errors' => [], 'manifest' => $normalized];
    }
}

if (!function_exists('quest_asset_manifest_hash')) {
    function quest_asset_manifest_hash(array $manifest)
    {
        return hash('sha256', json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}

if (!function_exists('quest_asset_import_manifest')) {
    function quest_asset_import_manifest($manifest, $sourceFile = '')
    {
        $validation = quest_asset_manifest_validate($manifest);
        if (!$validation['valid']) {
            return ['success' => false, 'errors' => $validation['errors']];
        }
        if (!quest_asset_library_tables_ready()) {
            return ['success' => false, 'errors' => ['Quest asset library tables are not available.']];
        }

        $manifest = $validation['manifest'];
        $pack = $manifest['pack'];
        $db = $GLOBALS['db'];
        $hash = quest_asset_manifest_hash($manifest);
        $assetCount = count($manifest['assets']);
        $groupCount = count($manifest['groups']);
        $memberCount = 0;

        try {
            quest_asset_exec_or_throw('BEGIN');
            $packKeyCn = $db->escape($pack['key']);
            $labelCn = $db->escape($pack['label']);
            $gameCn = $db->escape($pack['game']);
            $versionCn = $db->escape($pack['version']);
            $pluginsCn = $db->escape(json_encode($pack['required_plugins']));
            $sourceCn = $db->escape($pack['source']);
            $hashCn = $db->escape($hash);
            $noteCn = $db->escape($pack['note']);
            $activeSql = $pack['active'] ? 'true' : 'false';
            quest_asset_exec_or_throw("\n                INSERT INTO public.quest_asset_packs\n                    (pack_key, label, game, manifest_version, required_plugins_json, source, manifest_hash, active, note)\n                VALUES\n                    ('{$packKeyCn}', '{$labelCn}', '{$gameCn}', '{$versionCn}', '{$pluginsCn}'::jsonb, '{$sourceCn}', '{$hashCn}', {$activeSql}, '{$noteCn}')\n                ON CONFLICT (pack_key) DO UPDATE SET\n                    label = EXCLUDED.label,\n                    game = EXCLUDED.game,\n                    manifest_version = EXCLUDED.manifest_version,\n                    required_plugins_json = EXCLUDED.required_plugins_json,\n                    source = EXCLUDED.source,\n                    manifest_hash = EXCLUDED.manifest_hash,\n                    note = EXCLUDED.note,\n                    imported_at = now(),\n                    updated_at = now()\n            ");

            $groupPredicates = [];
            foreach ($manifest['groups'] as $group) {
                $datasetCn = $db->escape($group['dataset']);
                $groupKeyCn = $db->escape($group['key']);
                $groupPredicates[] = "(dataset_name = '{$datasetCn}' AND group_key = '{$groupKeyCn}')";
            }
            $groupKeepSql = empty($groupPredicates) ? '' : ' AND NOT (' . implode(' OR ', $groupPredicates) . ')';
            quest_asset_exec_or_throw(
                "DELETE FROM public.quest_asset_groups WHERE source_pack = '{$packKeyCn}'{$groupKeepSql}"
            );

            $assetRefs = [];
            foreach ($manifest['assets'] as $asset) {
                $assetRefs[] = "'" . $db->escape($asset['stable_ref']) . "'";
            }
            $assetKeepSql = empty($assetRefs) ? '' : ' AND stable_ref NOT IN (' . implode(', ', $assetRefs) . ')';
            quest_asset_exec_or_throw(
                "DELETE FROM public.quest_assets WHERE source_pack = '{$packKeyCn}'{$assetKeepSql}"
            );

            foreach ($manifest['assets'] as $asset) {
                $refCn = $db->escape($asset['stable_ref']);
                $signatureCn = $db->escape($asset['signature']);
                $editorCn = $db->escape($asset['editor_id']);
                $nameCn = $db->escape($asset['display_name']);
                $sourcePluginCn = $db->escape($asset['source_plugin']);
                $winningPluginCn = $db->escape($asset['winning_plugin']);
                $metadataCn = $db->escape(quest_asset_encode_json_object($asset['metadata']));
                $safetyCn = $db->escape($asset['safety_status']);
                $assetActiveSql = $asset['active'] ? 'true' : 'false';
                quest_asset_exec_or_throw("\n                    INSERT INTO public.quest_assets\n                        (stable_ref, signature, editor_id, display_name, source_plugin, winning_plugin, metadata_json, safety_status, source_pack, active)\n                    VALUES\n                        ('{$refCn}', '{$signatureCn}', '{$editorCn}', '{$nameCn}', '{$sourcePluginCn}', '{$winningPluginCn}', '{$metadataCn}'::jsonb, '{$safetyCn}', '{$packKeyCn}', {$assetActiveSql})\n                    ON CONFLICT (source_pack, stable_ref) DO UPDATE SET\n                        signature = EXCLUDED.signature,\n                        editor_id = EXCLUDED.editor_id,\n                        display_name = EXCLUDED.display_name,\n                        source_plugin = EXCLUDED.source_plugin,\n                        winning_plugin = EXCLUDED.winning_plugin,\n                        metadata_json = EXCLUDED.metadata_json,\n                        updated_at = now()\n                ");
            }

            foreach ($manifest['groups'] as $group) {
                $datasetCn = $db->escape($group['dataset']);
                $groupKeyCn = $db->escape($group['key']);
                $groupLabelCn = $db->escape($group['label']);
                $descriptionCn = $db->escape($group['description']);
                $policyCn = $db->escape(quest_asset_encode_json_object($group['selection_policy']));
                $groupActiveSql = $group['active'] ? 'true' : 'false';
                quest_asset_exec_or_throw("\n                    INSERT INTO public.quest_asset_groups\n                        (dataset_name, group_key, label, description, selection_policy_json, source_pack, active)\n                    VALUES\n                        ('{$datasetCn}', '{$groupKeyCn}', '{$groupLabelCn}', '{$descriptionCn}', '{$policyCn}'::jsonb, '{$packKeyCn}', {$groupActiveSql})\n                    ON CONFLICT (source_pack, dataset_name, group_key) DO UPDATE SET\n                        label = EXCLUDED.label,\n                        description = EXCLUDED.description,\n                        selection_policy_json = EXCLUDED.selection_policy_json,\n                        updated_at = now()\n                ");

                $memberRefs = [];
                foreach ($group['members'] as $member) {
                    $memberRefs[] = "'" . $db->escape($member['stable_ref']) . "'";
                }
                $memberKeepSql = empty($memberRefs) ? '' : ' AND stable_ref NOT IN (' . implode(', ', $memberRefs) . ')';
                quest_asset_exec_or_throw("
                    DELETE FROM public.quest_asset_group_members
                    WHERE source_pack = '{$packKeyCn}'
                      AND dataset_name = '{$datasetCn}'
                      AND group_key = '{$groupKeyCn}'{$memberKeepSql}
                ");

                foreach ($group['members'] as $member) {
                    $memberCount++;
                    $memberRefCn = $db->escape($member['stable_ref']);
                    $constraintsCn = $db->escape(quest_asset_encode_json_object($member['constraints']));
                    $memberNoteCn = $db->escape($member['note']);
                    $memberActiveSql = $member['active'] ? 'true' : 'false';
                    $weight = intval($member['weight']);
                    quest_asset_exec_or_throw("\n                        INSERT INTO public.quest_asset_group_members\n                            (dataset_name, group_key, stable_ref, weight, constraints_json, note, source_pack, active)\n                        VALUES\n                            ('{$datasetCn}', '{$groupKeyCn}', '{$memberRefCn}', {$weight}, '{$constraintsCn}'::jsonb, '{$memberNoteCn}', '{$packKeyCn}', {$memberActiveSql})\n                        ON CONFLICT (source_pack, dataset_name, group_key, stable_ref) DO UPDATE SET\n                            weight = EXCLUDED.weight,\n                            constraints_json = EXCLUDED.constraints_json,\n                            note = EXCLUDED.note,\n                            updated_at = now()\n                    ");
                }
            }

            $sourceFileCn = $db->escape((string) $sourceFile);
            quest_asset_exec_or_throw("\n                INSERT INTO public.quest_asset_imports\n                    (pack_key, manifest_version, manifest_hash, source_file, asset_count, group_count, member_count)\n                VALUES\n                    ('{$packKeyCn}', '{$versionCn}', '{$hashCn}', '{$sourceFileCn}', {$assetCount}, {$groupCount}, {$memberCount})\n            ");
            quest_asset_exec_or_throw('COMMIT');
        } catch (Throwable $e) {
            try {
                quest_asset_exec_or_throw('ROLLBACK');
            } catch (Throwable $_rollbackError) {
            }
            return ['success' => false, 'errors' => [$e->getMessage()]];
        }

        return [
            'success' => true,
            'errors' => [],
            'pack_key' => $pack['key'],
            'manifest_hash' => $hash,
            'assets' => $assetCount,
            'groups' => $groupCount,
            'members' => $memberCount,
        ];
    }
}

if (!function_exists('quest_asset_import_manifest_file')) {
    function quest_asset_import_manifest_file($path)
    {
        if (!is_file($path) || !is_readable($path)) {
            return ['success' => false, 'errors' => ["Manifest file is not readable: {$path}"]];
        }
        $manifest = json_decode(file_get_contents($path), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'errors' => ['Invalid JSON: ' . json_last_error_msg()]];
        }
        return quest_asset_import_manifest($manifest, basename($path));
    }
}

if (!function_exists('quest_asset_db_bool')) {
    function quest_asset_db_bool($value)
    {
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower(trim((string) $value)), ['1', 't', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('quest_asset_load_dataset')) {
    function quest_asset_load_dataset($datasetName)
    {
        $datasetName = strtolower(trim((string) $datasetName));
        if (!isset(quest_asset_dataset_signatures()[$datasetName]) || !quest_asset_library_tables_ready()) {
            return [];
        }

        $datasetCn = $GLOBALS['db']->escape($datasetName);
        try {
            $rows = $GLOBALS['db']->fetchAll("\n                SELECT g.group_key, m.stable_ref, m.weight, p.required_plugins_json\n                FROM public.quest_asset_groups g\n                JOIN public.quest_asset_packs p ON p.pack_key = g.source_pack\n                JOIN public.quest_asset_group_members m\n                  ON m.source_pack = g.source_pack\n                 AND m.dataset_name = g.dataset_name\n                 AND m.group_key = g.group_key\n                JOIN public.quest_assets a\n                  ON a.source_pack = m.source_pack AND a.stable_ref = m.stable_ref\n                WHERE g.dataset_name = '{$datasetCn}'\n                  AND g.active = true\n                  AND p.active = true\n                  AND m.active = true\n                  AND a.active = true\n                  AND a.safety_status = 'approved'\n                ORDER BY g.group_key, a.stable_ref\n            ");
        } catch (Throwable $e) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            if (!quest_asset_required_plugins_available($row['required_plugins_json'] ?? '[]')) {
                continue;
            }
            $key = quest_asset_normalize_key($row['group_key'] ?? '');
            $runtimeFormId = chimResolveStableFormReferenceToRuntimeFormId((string) ($row['stable_ref'] ?? ''));
            if ($key === null || $runtimeFormId === null) {
                continue;
            }
            $value = hexdec($runtimeFormId);
            $weight = max(1, min(100, intval($row['weight'] ?? 1)));
            if (!isset($result[$key])) {
                $result[$key] = [];
            }
            for ($i = 0; $i < $weight; $i++) {
                $result[$key][] = $value;
            }
        }

        return $result;
    }
}

if (!function_exists('quest_asset_merge_dataset')) {
    function quest_asset_merge_dataset($legacy, $library)
    {
        $result = is_array($legacy) ? $legacy : [];
        foreach ((is_array($library) ? $library : []) as $key => $values) {
            if (!isset($result[$key]) || !is_array($result[$key])) {
                $result[$key] = [];
            }
            foreach ($values as $value) {
                $result[$key][] = intval($value);
            }
        }
        return $result;
    }
}

if (!function_exists('quest_asset_apply_fallback_map')) {
    function quest_asset_apply_fallback_map($dataset, $fallbackMap, $maxPasses = 32)
    {
        $result = is_array($dataset) ? $dataset : [];
        $pending = is_array($fallbackMap) ? $fallbackMap : [];
        $maxPasses = max(1, intval($maxPasses));

        for ($pass = 0; $pass < $maxPasses && !empty($pending); $pass++) {
            $progress = false;
            foreach ($pending as $rawKey => $fallback) {
                $key = quest_asset_normalize_key($rawKey);
                $fallback = quest_asset_normalize_key($fallback);
                if ($key === null || $fallback === null || $key === $fallback) {
                    unset($pending[$rawKey]);
                    continue;
                }
                if (isset($result[$fallback]) && is_array($result[$fallback]) && !empty($result[$fallback])) {
                    if (!isset($result[$key]) || empty($result[$key])) {
                        $result[$key] = $result[$fallback];
                    }
                    unset($pending[$rawKey]);
                    $progress = true;
                }
            }
            if (!$progress) {
                break;
            }
        }

        return $result;
    }
}

if (!function_exists('quest_asset_apply_group_fallbacks')) {
    function quest_asset_apply_group_fallbacks($datasetName, $dataset)
    {
        if (!quest_asset_library_tables_ready() || !is_array($dataset)) {
            return is_array($dataset) ? $dataset : [];
        }
        $datasetCn = $GLOBALS['db']->escape(strtolower(trim((string) $datasetName)));
        try {
            $rows = $GLOBALS['db']->fetchAll("\n                SELECT g.group_key, g.selection_policy_json, p.required_plugins_json\n                FROM public.quest_asset_groups g\n                JOIN public.quest_asset_packs p ON p.pack_key = g.source_pack\n                WHERE g.dataset_name = '{$datasetCn}' AND g.active = true AND p.active = true\n            ");
        } catch (Throwable $e) {
            return $dataset;
        }

        $pending = [];
        foreach ($rows as $row) {
            if (!quest_asset_required_plugins_available($row['required_plugins_json'] ?? '[]')) {
                continue;
            }
            $key = quest_asset_normalize_key($row['group_key'] ?? '');
            $policy = json_decode((string) ($row['selection_policy_json'] ?? '{}'), true);
            $fallback = quest_asset_normalize_key(is_array($policy) ? ($policy['fallback_group'] ?? '') : '');
            if ($key !== null && $fallback !== null) {
                $pending[$key] = $fallback;
            }
        }

        return quest_asset_apply_fallback_map($dataset, $pending);
    }
}
