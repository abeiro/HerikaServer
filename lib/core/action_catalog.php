<?php

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'game_plugins.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'npc_master.class.php');

function herikaGetRetiredActionCodes()
{
    return [
        'AttackHunt',
        'LookAt',
        'GetDateTime',
        'SearchDiary',
        'SetCurrentTask',
        'ReadDiaryPage',
        'SearchMemory',
        'GiveItemToPlayer',
    ];
}

function herikaActionCatalogDecodeSqlQuotedText($value)
{
    $text = trim(strval($value));
    if ($text === '' || strcasecmp($text, 'NULL') === 0) {
        return null;
    }

    if (strlen($text) >= 2 && $text[0] === "'" && substr($text, -1) === "'") {
        $text = substr($text, 1, -1);
    }

    return str_replace("''", "'", $text);
}

function herikaActionCatalogSplitSqlTuple($tuple)
{
    $fields = [];
    $current = '';
    $inString = false;
    $length = strlen($tuple);

    for ($index = 0; $index < $length; $index++) {
        $char = $tuple[$index];

        if ($char === "'") {
            $current .= $char;
            if ($inString && $index + 1 < $length && $tuple[$index + 1] === "'") {
                $current .= "'";
                $index++;
                continue;
            }

            $inString = !$inString;
            continue;
        }

        if (!$inString && $char === ',') {
            $fields[] = trim($current);
            $current = '';
            continue;
        }

        $current .= $char;
    }

    if (trim($current) !== '') {
        $fields[] = trim($current);
    }

    return $fields;
}

function herikaActionCatalogSplitSqlInsertTuples($sql)
{
    $valuesPos = stripos($sql, 'VALUES');
    $conflictPos = stripos($sql, 'ON CONFLICT');
    if ($valuesPos === false || $conflictPos === false || $conflictPos <= $valuesPos) {
        return [];
    }

    $valuesSql = trim(substr($sql, $valuesPos + strlen('VALUES'), $conflictPos - ($valuesPos + strlen('VALUES'))));
    if ($valuesSql === '') {
        return [];
    }

    $tuples = [];
    $current = '';
    $depth = 0;
    $inString = false;
    $length = strlen($valuesSql);

    for ($index = 0; $index < $length; $index++) {
        $char = $valuesSql[$index];

        if ($char === "'") {
            $current .= $char;
            if ($inString && $index + 1 < $length && $valuesSql[$index + 1] === "'") {
                $current .= "'";
                $index++;
                continue;
            }

            $inString = !$inString;
            continue;
        }

        if (!$inString) {
            if ($char === '(') {
                if ($depth > 0) {
                    $current .= $char;
                }
                $depth++;
                continue;
            }

            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $tuples[] = $current;
                    $current = '';
                    continue;
                }
            }
        }

        if ($depth > 0) {
            $current .= $char;
        }
    }

    return $tuples;
}

function herikaLoadActionCatalogBaseSeedRowsFromSeedFile()
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $cache = [];
    $seedFile = herikaGetActionCatalogBaseSeedFilePath();
    if (!file_exists($seedFile)) {
        return $cache;
    }

    $sql = trim(strval(file_get_contents($seedFile)));
    if ($sql === '') {
        return $cache;
    }

    foreach (herikaActionCatalogSplitSqlInsertTuples($sql) as $tuple) {
        $fields = herikaActionCatalogSplitSqlTuple($tuple);
        if (count($fields) < 8) {
            continue;
        }

        $codeName = herikaActionCatalogDecodeSqlQuotedText($fields[0] ?? '');
        if ($codeName === null || trim($codeName) === '') {
            continue;
        }

        $cache[$codeName] = [
            'code_name' => $codeName,
            'action_name' => herikaActionCatalogDecodeSqlQuotedText($fields[1] ?? ''),
            'description' => herikaActionCatalogDecodeSqlQuotedText($fields[2] ?? ''),
            'return_message' => herikaActionCatalogDecodeSqlQuotedText($fields[3] ?? ''),
            'available_to_npc' => herikaActionCatalogToBool($fields[4] ?? false),
            'available_to_followers' => herikaActionCatalogToBool($fields[5] ?? false),
            'available_to_narrator' => herikaActionCatalogToBool($fields[6] ?? false),
            'is_activated' => herikaActionCatalogToBool($fields[7] ?? false),
        ];
    }

    return $cache;
}

function herikaActionCatalogSqlBool($value)
{
    return $value ? 'TRUE' : 'FALSE';
}

function herikaActionCatalogNormalizeImportVersion($value)
{
    if ($value === null) {
        return 0;
    }

    if (is_bool($value)) {
        return $value ? 1 : 0;
    }

    if (is_numeric($value)) {
        return max(0, intval(floor(floatval($value))));
    }

    $text = trim(strval($value));
    if ($text === '') {
        return 0;
    }

    if (is_numeric($text)) {
        return max(0, intval(floor(floatval($text))));
    }

    return 0;
}

function herikaActionCatalogShouldOverwriteImportVersion($incomingVersion, $existingVersion)
{
    return herikaActionCatalogNormalizeImportVersion($incomingVersion)
        > herikaActionCatalogNormalizeImportVersion($existingVersion);
}

function herikaActionCatalogSqlText($value)
{
    $text = strval($value);
    if ($text === '') {
        return "''";
    }

    return $GLOBALS["db"]->escapeLiteral($text);
}

function herikaActionCatalogSqlJson($value, $allowNull = false)
{
    if ($value === null) {
        return $allowNull ? 'NULL' : "'{}'::jsonb";
    }

    if (is_string($value)) {
        $json = trim($value);
        if ($json === '') {
            return $allowNull ? 'NULL' : "'{}'::jsonb";
        }
    } else {
        $json = herikaActionCatalogJsonEncode($value);
        if ($json === '') {
            return $allowNull ? 'NULL' : "'{}'::jsonb";
        }
    }

    return $GLOBALS["db"]->escapeLiteral($json) . '::jsonb';
}

function herikaActionCatalogJsonEncode($value)
{
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return is_string($json) ? $json : '';
}

function herikaActionCatalogDecodeJson($value, $default = [])
{
    if (is_array($value)) {
        return $value;
    }

    $text = trim(strval($value));
    if ($text === '') {
        return $default;
    }

    $decoded = json_decode($text, true);
    return is_array($decoded) ? $decoded : $default;
}

function herikaActionCatalogMergePreservedCustomMetadata($baseMetadata, $existingMetadata)
{
    $baseMetadata = herikaActionCatalogDecodeJson($baseMetadata, []);
    $existingMetadata = herikaActionCatalogDecodeJson($existingMetadata, []);

    if (isset($existingMetadata['custom_config']) && is_array($existingMetadata['custom_config']) && count($existingMetadata['custom_config']) > 0) {
        $baseMetadata['custom_config'] = $existingMetadata['custom_config'];
    }

    return $baseMetadata;
}

function herikaActionCatalogNormalizeEditorFieldOptions($options)
{
    if (!is_array($options)) {
        return [];
    }

    $normalized = [];
    foreach ($options as $key => $option) {
        if (is_array($option)) {
            $value = strval($option['value'] ?? '');
            if ($value === '') {
                $value = is_string($key) ? $key : '';
            }
            if ($value === '') {
                continue;
            }

            $normalized[] = [
                'value' => $value,
                'label' => strval($option['label'] ?? $value),
            ];
            continue;
        }

        if (is_string($key) && $key !== '') {
            $normalized[] = [
                'value' => $key,
                'label' => strval($option),
            ];
            continue;
        }

        $value = strval($option);
        if ($value === '') {
            continue;
        }

        $normalized[] = [
            'value' => $value,
            'label' => $value,
        ];
    }

    return $normalized;
}

function herikaActionCatalogGetSharedEditorFields()
{
    return [
        [
            'key' => 'followup_enabled',
            'label' => 'Follow-up Enabled',
            'type' => 'boolean',
            'default' => false,
            'metadata_default_path' => 'followup.enabled',
            'help' => 'If enabled, this action may trigger a follow-up LLM response when a funcret result arrives.',
        ],
        [
            'key' => 'followup_arg_name',
            'label' => 'Follow-up Argument Name',
            'type' => 'text',
            'default' => 'target',
            'metadata_default_path' => 'followup.arg_name',
            'placeholder' => 'target',
            'help' => 'Tool-call argument name to use in the synthetic follow-up context.',
        ],
        [
            'key' => 'followup_prompt',
            'label' => 'Follow-up Prompt',
            'type' => 'textarea',
            'default' => '',
            'metadata_default_path' => 'followup.prompt',
            'placeholder' => 'Reply with one short in-character line reacting to the tool result below. Do not ask follow-up questions.',
            'help' => 'The full instruction used to generate the follow-up response.',
        ],
        [
            'key' => 'followup_use_functions_again',
            'label' => 'Allow Follow-up Actions',
            'type' => 'boolean',
            'default' => false,
            'metadata_default_path' => 'followup.use_functions_again',
            'help' => 'If enabled, the follow-up response may call another action.',
        ],
    ];
}

function herikaActionCatalogGetDefaultConfirmationPolicy($codeName)
{
    $codeName = strtolower(trim(strval($codeName)));
    $askByDefault = [
        'arrestplayer',
        'acceptsex',
        'kiss',
        'makelove',
        'removeclothes',
        'sexaction',
        'takehelditem',
        'takegoldfromplayer',
    ];

    return in_array($codeName, $askByDefault, true) ? 'ask' : 'automatic';
}

function herikaActionCatalogRowSupportsConfirmation($row)
{
    if (!is_array($row) || !herikaActionCatalogToBool($row['game_function'] ?? false)) {
        return false;
    }

    $codeName = strtolower(trim(strval($row['code_name'] ?? '')));
    $readOnlyActions = [
        'checkinventory',
        'gettime',
        'gettopicinfo',
        'inspect',
        'inspectsurroundings',
        'listinventory',
        'readquestjournal',
        'talk',
    ];

    return $codeName !== '' && !in_array($codeName, $readOnlyActions, true);
}

function herikaActionCatalogGetConfirmationEditorField($row)
{
    $codeName = trim(strval($row['code_name'] ?? ''));

    return [
        'key' => 'confirmation_required',
        'label' => 'Require Confirmation',
        'type' => 'boolean',
        'default' => herikaActionCatalogGetDefaultConfirmationPolicy($codeName) === 'ask',
        'help' => 'When enabled, CHIM asks for permission before this action executes. Cancelling the prompt silently discards the action.',
    ];
}

function herikaActionCatalogGetConfirmationCommandChannel($row)
{
    if (!herikaActionCatalogRowSupportsConfirmation($row)) {
        return 'command';
    }

    $metadata = herikaActionCatalogDecodeJson($row['metadata'] ?? [], []);
    $customConfig = is_array($metadata['custom_config'] ?? null) ? $metadata['custom_config'] : [];

    if (array_key_exists('confirmation_required', $customConfig)) {
        return herikaActionCatalogToBool($customConfig['confirmation_required'])
            ? 'confirmcommand'
            : 'approvedcommand';
    }

    // Preserve settings saved by builds that exposed the earlier three-state selector.
    $selectedPolicy = strtolower(trim(strval($customConfig['confirmation_policy'] ?? 'default')));
    if (!in_array($selectedPolicy, ['default', 'ask', 'automatic'], true)) {
        $selectedPolicy = 'default';
    }

    $confirmationMetadata = is_array($metadata['confirmation'] ?? null) ? $metadata['confirmation'] : [];
    $defaultPolicy = strtolower(trim(strval($confirmationMetadata['default_policy'] ?? '')));
    if (!in_array($defaultPolicy, ['ask', 'automatic'], true)) {
        $defaultPolicy = herikaActionCatalogGetDefaultConfirmationPolicy($row['code_name'] ?? '');
    }

    $effectivePolicy = $selectedPolicy === 'default' ? $defaultPolicy : $selectedPolicy;
    if ($effectivePolicy === 'ask') {
        return 'confirmcommand';
    }

    // Explicit automatic permission bypasses older per-action confirmation prompts.
    return $selectedPolicy === 'automatic' ? 'approvedcommand' : 'command';
}

function herikaActionCatalogNormalizeEditorField($field)
{
    if (!is_array($field)) {
        return null;
    }

    $key = trim(strval($field['key'] ?? ''));
    if ($key === '') {
        return null;
    }

    $type = strtolower(trim(strval($field['type'] ?? 'text')));
    if (!in_array($type, ['text', 'textarea', 'integer', 'number', 'boolean', 'select'], true)) {
        $type = 'text';
    }

    $normalized = [
        'key' => $key,
        'label' => trim(strval($field['label'] ?? $key)),
        'type' => $type,
        'default' => $field['default'] ?? null,
        'global_default_key' => trim(strval($field['global_default_key'] ?? '')),
        'metadata_default_path' => trim(strval($field['metadata_default_path'] ?? '')),
        'minimum' => array_key_exists('minimum', $field) ? $field['minimum'] : null,
        'maximum' => array_key_exists('maximum', $field) ? $field['maximum'] : null,
        'step' => array_key_exists('step', $field) ? $field['step'] : null,
        'format' => trim(strval($field['format'] ?? '')),
        'placeholder' => strval($field['placeholder'] ?? ''),
        'help' => strval($field['help'] ?? ''),
        'options' => herikaActionCatalogNormalizeEditorFieldOptions($field['options'] ?? []),
    ];

    if ($normalized['label'] === '') {
        $normalized['label'] = $key;
    }

    return $normalized;
}

function herikaActionCatalogGetEditorFields($rowOrCode = null)
{
    $row = null;
    if (is_array($rowOrCode)) {
        $row = $rowOrCode;
    } elseif ($rowOrCode !== null) {
        $row = herikaGetActionCatalogRow($rowOrCode);
    }

    if (!is_array($row)) {
        return [];
    }

    $metadata = herikaActionCatalogDecodeJson($row['metadata'] ?? [], []);
    $fields = $metadata['editor_fields'] ?? [];
    if (!is_array($fields)) {
        return [];
    }

    $normalized = [];
    foreach (herikaActionCatalogGetSharedEditorFields() as $field) {
        $normalizedField = herikaActionCatalogNormalizeEditorField($field);
        if ($normalizedField === null) {
            continue;
        }

        $normalized[$normalizedField['key']] = $normalizedField;
    }

    if (herikaActionCatalogRowSupportsConfirmation($row)) {
        $confirmationField = herikaActionCatalogNormalizeEditorField(
            herikaActionCatalogGetConfirmationEditorField($row)
        );
        if ($confirmationField !== null) {
            $normalized[$confirmationField['key']] = $confirmationField;
        }
    }

    foreach ($fields as $field) {
        $normalizedField = herikaActionCatalogNormalizeEditorField($field);
        if ($normalizedField === null) {
            continue;
        }

        $normalized[$normalizedField['key']] = $normalizedField;
    }

    return array_values($normalized);
}

function herikaActionCatalogCastEditorFieldValue($field, $value)
{
    $field = herikaActionCatalogNormalizeEditorField($field);
    if ($field === null) {
        return $value;
    }

    $type = $field['type'];
    if ($type === 'boolean') {
        return herikaActionCatalogToBool($value);
    }

    if ($type === 'integer') {
        if (is_bool($value) || $value === null || trim(strval($value)) === '' || !is_numeric($value)) {
            $value = $field['default'] ?? 0;
        }

        $normalizedValue = intval(round(floatval($value)));
        if (is_numeric($field['minimum'])) {
            $normalizedValue = max($normalizedValue, intval($field['minimum']));
        }
        if (is_numeric($field['maximum'])) {
            $normalizedValue = min($normalizedValue, intval($field['maximum']));
        }
        return $normalizedValue;
    }

    if ($type === 'number') {
        if (is_bool($value) || $value === null || trim(strval($value)) === '' || !is_numeric($value)) {
            $value = $field['default'] ?? 0;
        }

        $normalizedValue = floatval($value);
        if (is_numeric($field['minimum'])) {
            $normalizedValue = max($normalizedValue, floatval($field['minimum']));
        }
        if (is_numeric($field['maximum'])) {
            $normalizedValue = min($normalizedValue, floatval($field['maximum']));
        }
        return $normalizedValue;
    }

    if ($type === 'select') {
        $textValue = trim(strval($value));
        foreach ($field['options'] as $option) {
            if ($textValue === strval($option['value'] ?? '')) {
                return $textValue;
            }
        }

        if (count($field['options']) > 0) {
            return strval($field['options'][0]['value'] ?? '');
        }

        return '';
    }

    return strval($value ?? '');
}

function herikaActionCatalogGetEditorFieldDefaultValue($field, $row = null)
{
    $field = herikaActionCatalogNormalizeEditorField($field);
    if ($field === null) {
        return null;
    }

    $defaultValue = $field['default'] ?? null;
    $globalDefaultKey = trim(strval($field['global_default_key'] ?? ''));
    if ($globalDefaultKey !== '' && array_key_exists($globalDefaultKey, $GLOBALS)) {
        $defaultValue = $GLOBALS[$globalDefaultKey];
    }

    $metadataDefaultPath = trim(strval($field['metadata_default_path'] ?? ''));
    if ($metadataDefaultPath !== '' && is_array($row)) {
        $rowMetadata = herikaActionCatalogDecodeJson($row['metadata'] ?? [], []);
        $metadataDefaultValue = herikaActionCatalogResolveContextPath($rowMetadata, $metadataDefaultPath);
        if ($metadataDefaultValue !== null) {
            $defaultValue = $metadataDefaultValue;
        }
    }

    return herikaActionCatalogCastEditorFieldValue($field, $defaultValue);
}

function herikaActionCatalogGetResolvedCustomConfig($codeName, $row = null)
{
    $codeName = trim(strval($codeName));
    if ($codeName === '') {
        return [];
    }

    if (!is_array($row)) {
        $row = herikaGetActionCatalogRow($codeName);
    }
    if (!is_array($row)) {
        return [];
    }

    $metadata = herikaActionCatalogDecodeJson($row['metadata'] ?? [], []);
    $customConfig = is_array($metadata['custom_config'] ?? null) ? $metadata['custom_config'] : [];
    $resolvedConfig = [];

    foreach (herikaActionCatalogGetEditorFields($row) as $field) {
        $fieldKey = $field['key'];
        if (array_key_exists($fieldKey, $customConfig)) {
            $resolvedConfig[$fieldKey] = herikaActionCatalogCastEditorFieldValue($field, $customConfig[$fieldKey]);
        } elseif ($fieldKey === 'confirmation_required' && array_key_exists('confirmation_policy', $customConfig)) {
            $legacyPolicy = strtolower(trim(strval($customConfig['confirmation_policy'])));
            $resolvedConfig[$fieldKey] = $legacyPolicy === 'ask';
        } else {
            $resolvedConfig[$fieldKey] = herikaActionCatalogGetEditorFieldDefaultValue($field, $row);
        }
    }

    foreach ($customConfig as $fieldKey => $fieldValue) {
        if (!array_key_exists($fieldKey, $resolvedConfig)) {
            $resolvedConfig[$fieldKey] = $fieldValue;
        }
    }

    return $resolvedConfig;
}

function herikaActionCatalogToBool($value)
{
    if (is_bool($value)) {
        return $value;
    }

    $text = strtolower(trim(strval($value)));
    return in_array($text, ['1', 'true', 't', 'yes', 'on'], true);
}

function herikaNormalizeActionCatalogDisplayToken($text, $token, $replacement)
{
    $token = trim(strval($token));
    if ($token === '') {
        return $text;
    }

    $quotedToken = preg_quote($token, '/');
    $text = preg_replace('/\b[Tt]he\s+' . $quotedToken . '\b/u', $replacement, $text);
    return str_replace($token, $replacement, $text);
}

function herikaNormalizeActionCatalogDisplayText($text)
{
    $text = strval($text);
    if ($text === '') {
        return '';
    }

    $text = herikaNormalizeActionCatalogDisplayToken($text, $GLOBALS["HERIKA_NAME"] ?? '', 'NPC');
    $text = herikaNormalizeActionCatalogDisplayToken($text, $GLOBALS["PLAYER_NAME"] ?? '', 'PLAYER');
    $text = herikaNormalizeActionCatalogDisplayToken($text, 'The Narrator', 'NPC');
    $text = herikaNormalizeActionCatalogDisplayToken($text, 'Narrator', 'NPC');

    $text = preg_replace('/\b[Tt]he\s+NPC\b/u', 'NPC', $text);
    $text = preg_replace('/\b[Tt]he\s+PLAYER\b/u', 'PLAYER', $text);

    return $text;
}

function herikaNormalizeActionCatalogDisplayActionName($text)
{
    $text = strval($text);
    if ($text === '') {
        return '';
    }

    $text = herikaNormalizeActionCatalogDisplayToken($text, $GLOBALS["HERIKA_NAME"] ?? '', 'Npc');
    $text = herikaNormalizeActionCatalogDisplayToken($text, $GLOBALS["PLAYER_NAME"] ?? '', 'Player');
    $text = herikaNormalizeActionCatalogDisplayToken($text, 'The Narrator', 'Npc');
    $text = herikaNormalizeActionCatalogDisplayToken($text, 'Narrator', 'Npc');

    $text = preg_replace('/\b[Tt]he\s+Npc\b/u', 'Npc', $text);
    $text = preg_replace('/\b[Tt]he\s+Player\b/u', 'Player', $text);

    $text = preg_replace('/[\s\-]+/u', '_', $text);
    $text = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/u', '_', $text);
    $text = preg_replace('/(?<=[A-Z])(?=[A-Z][a-z])/u', '_', $text);
    $text = preg_replace('/(?<=[A-Za-z])(?=\d)/u', '_', $text);
    $text = preg_replace('/(?<=\d)(?=[A-Za-z])/u', '_', $text);
    $text = preg_replace('/_+/u', '_', $text);
    $text = trim($text, '_');

    return $text;
}

function herikaActionCatalogNormalizeParameterSchema($parameters)
{
    if (!is_array($parameters)) {
        return [
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ];
    }

    if (($parameters['type'] ?? '') !== 'object') {
        $parameters['type'] = 'object';
    }

    if (!isset($parameters['properties']) || !is_array($parameters['properties'])) {
        $parameters['properties'] = [];
    }

    if (!isset($parameters['required']) || !is_array($parameters['required'])) {
        $parameters['required'] = [];
    }

    $normalizedRequired = [];
    foreach ($parameters['required'] as $requiredField) {
        $requiredField = trim(strval($requiredField));
        if ($requiredField !== '' && !in_array($requiredField, $normalizedRequired, true)) {
            $normalizedRequired[] = $requiredField;
        }
    }
    $parameters['required'] = $normalizedRequired;

    return $parameters;
}

function herikaActionCatalogApplyCompatibilityOverrides($row)
{
    if (!is_array($row)) {
        return $row;
    }

    $codeName = trim(strval($row['code_name'] ?? ''));
    if ($codeName !== 'ReturnBackHome') {
        return $row;
    }

    $row['parameters_json'] = herikaActionCatalogNormalizeParameterSchema($row['parameters_json'] ?? null);
    $row['parameters_json']['required'] = [];

    $metadata = is_array($row['metadata'] ?? null)
        ? $row['metadata']
        : herikaActionCatalogDecodeJson($row['metadata'] ?? [], []);
    $requirements = is_array($metadata['requirements'] ?? null) ? $metadata['requirements'] : [];
    if (
        herikaActionCatalogToBool($requirements['requires_rolemaster'] ?? false)
        && empty($row['available_to_npc'])
        && empty($row['available_to_followers'])
        && empty($row['available_to_narrator'])
    ) {
        $row['available_to_npc'] = true;
        $row['available_to_followers'] = true;
    }

    $row['metadata'] = $metadata;
    return $row;
}

function herikaActionCatalogGetBaseScriptProxyPrograms()
{
    static $programs = null;
    if ($programs !== null) {
        return $programs;
    }

    $programs = [
        'Drink' => [
            'switch_on' => 'actor_furniture',
            'cases' => [
                'Chair' => [
                    'commands' => [
                        [
                            'cmd_id' => 34,
                            'args' => [
                                'targetObjectFormId' => '{{actor_refid}}',
                                'akIdle' => '0x00065d07',
                            ],
                        ],
                    ],
                ],
                '__default' => [
                    'commands' => [
                        [
                            'cmd_id' => 34,
                            'args' => [
                                'targetObjectFormId' => '{{actor_refid}}',
                                'akIdle' => '0x00103656',
                            ],
                        ],
                    ],
                ],
            ],
            'db_inserts' => [
                [
                    'table' => 'actions_issued',
                    'data' => [
                        'action' => 'Drink',
                        'fullcall' => '{{full_call}}',
                        'actorname' => '{{actor_name}}',
                        'ts' => '{{request_ts}}',
                        'gamets' => '{{game_ts}}',
                        'localts' => '{{local_ts}}',
                        'original' => '',
                    ],
                ],
            ],
        ],
        'Toast' => [
            'commands' => [
                [
                    'cmd_id' => 34,
                    'args' => [
                        'targetObjectFormId' => '{{actor_refid}}',
                        'akIdle' => '0x0010528a',
                    ],
                ],
                [
                    'cmd_id' => 34,
                    'args' => [
                        'targetObjectFormId' => '{{actor_refid}}',
                        'akIdle' => '0x00103656',
                    ],
                    'delay_seconds' => '{{toast_delay_seconds}}',
                ],
            ],
            'db_inserts' => [
                [
                    'table' => 'actions_issued',
                    'data' => [
                        'action' => 'Toast',
                        'fullcall' => '{{full_call}}',
                        'actorname' => '{{actor_name}}',
                        'ts' => '{{request_ts}}',
                        'gamets' => '{{game_ts}}',
                        'localts' => '{{local_ts}}',
                        'original' => '',
                    ],
                ],
            ],
        ],
        'StartRitualCeremony' => [
            'switch_on' => 'parameter_target',
            'cases' => [
                'Magical' => [
                    'commands' => [
                        [
                            'cmd_id' => 34,
                            'args' => [
                                'targetObjectFormId' => '{{actor_refid}}',
                                'akIdle' => '0x000f11e2',
                            ],
                        ],
                        [
                            'cmd_id' => 300,
                            'args' => [
                                'targetObjectFormId' => '0x0005fb82',
                                'akObject' => '{{actor_refid}}',
                                'afDuration' => 20,
                            ],
                        ],
                    ],
                ],
                'Blood' => [
                    'commands' => [
                        [
                            'cmd_id' => 34,
                            'args' => [
                                'targetObjectFormId' => '{{actor_refid}}',
                                'akIdle' => '0x000af886',
                            ],
                        ],
                        [
                            'cmd_id' => 300,
                            'args' => [
                                'targetObjectFormId' => '0x0010f505',
                                'akObject' => '{{actor_refid}}',
                                'afDuration' => 20,
                            ],
                        ],
                        [
                            'cmd_id' => 34,
                            'args' => [
                                'targetObjectFormId' => '{{actor_refid}}',
                                'akIdle' => '0x0006f300',
                            ],
                            'delay_seconds' => 10,
                        ],
                    ],
                ],
                'Religious' => [
                    'commands' => [],
                ],
                'Cultural' => [
                    'commands' => [],
                ],
                'Personal' => [
                    'commands' => [],
                ],
                '__default' => [
                    'commands' => [
                        [
                            'cmd_id' => 34,
                            'args' => [
                                'targetObjectFormId' => '{{actor_refid}}',
                                'akIdle' => '0x000f11e1',
                            ],
                        ],
                        [
                            'cmd_id' => 300,
                            'args' => [
                                'targetObjectFormId' => '0x00050f02',
                                'akObject' => '{{actor_refid}}',
                                'afDuration' => 20,
                            ],
                        ],
                    ],
                ],
            ],
            'npc_metadata_updates' => [
                'ritual_state' => [
                    'active' => true,
                    'type' => '{{parameter_target}}',
                    'started_at' => '{{local_ts}}',
                    'gamets' => '{{game_ts}}',
                ],
                'activity_status' => [
                    'current_action' => 'ritual',
                    'current_use' => '{{parameter_target}}',
                    'use_type' => 'ritual',
                    'timestamp' => '{{local_ts_ms}}',
                    'gamets' => '{{game_ts}}',
                ],
            ],
            'db_inserts' => [
                [
                    'table' => 'rolemaster',
                    'data' => [
                        'localts' => '{{local_ts}}',
                        'ttl' => 60,
                        'type' => 'scenenote',
                        'data' => '{{actor_name}} is celebrating a ritual',
                    ],
                ],
                [
                    'table' => 'actions_issued',
                    'data' => [
                        'action' => 'StartRitualCeremony',
                        'fullcall' => '{{full_call}}',
                        'actorname' => '{{actor_name}}',
                        'ts' => '{{request_ts}}',
                        'gamets' => '{{game_ts}}',
                        'localts' => '{{local_ts}}',
                        'original' => '',
                    ],
                ],
            ],
        ],
        'EndRitualCeremony' => [
            'commands' => [
                [
                    'cmd_id' => 34,
                    'args' => [
                        'targetObjectFormId' => '{{actor_refid}}',
                        'akIdle' => '0x000f11e3',
                    ],
                ],
            ],
            'npc_metadata_updates' => [
                'ritual_state' => null,
                'activity_status' => [
                    'current_action' => 'idle',
                    'current_use' => '',
                    'use_type' => '',
                    'furniture_name' => '',
                    'timestamp' => '{{local_ts_ms}}',
                    'gamets' => '{{game_ts}}',
                ],
            ],
            'db_inserts' => [
                [
                    'table' => 'rolemaster',
                    'data' => [
                        'localts' => '{{local_ts}}',
                        'ttl' => 30,
                        'type' => 'scenenote',
                        'data' => '{{actor_name}} just ended the ritual celebration',
                    ],
                ],
                [
                    'table' => 'actions_issued',
                    'data' => [
                        'action' => 'EndRitualCeremony',
                        'fullcall' => '{{full_call}}',
                        'actorname' => '{{actor_name}}',
                        'ts' => '{{request_ts}}',
                        'gamets' => '{{game_ts}}',
                        'localts' => '{{local_ts}}',
                        'original' => '',
                    ],
                ],
            ],
        ],
    ];

    return $programs;
}

function herikaActionCatalogGetBuiltinEditorFields($codeName)
{
    $fields = [
        'RentRoom' => [
            [
                'key' => 'cost_gold',
                'label' => 'Gold Cost',
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'format' => 'gold',
                'help' => 'How much gold this action costs.',
            ],
        ],
        'HireCarriage' => [
            [
                'key' => 'cost_gold',
                'label' => 'Gold Cost',
                'type' => 'integer',
                'default' => 20,
                'minimum' => 1,
                'format' => 'gold',
                'help' => 'How much gold this action costs.',
            ],
            [
                'key' => 'allowed_npc_names',
                'label' => 'Allowed NPCs',
                'type' => 'textarea',
                'default' => "Bjorlam\nAlfarinn\nKibell\nSigaar\nThaer\nEngar\nGunjar\nMarkus",
                'format' => 'name_list',
                'placeholder' => "One NPC name per line",
                'help' => 'Only these NPC names will offer carriage travel.',
            ],
        ],
        'HireFerry' => [
            [
                'key' => 'cost_gold',
                'label' => 'Gold Cost',
                'type' => 'integer',
                'default' => 50,
                'minimum' => 1,
                'format' => 'gold',
                'help' => 'How much gold this action costs.',
            ],
            [
                'key' => 'allowed_npc_names',
                'label' => 'Allowed NPCs',
                'type' => 'textarea',
                'default' => "Gort\nHarlaug\nJolf",
                'format' => 'name_list',
                'placeholder' => "One NPC name per line",
                'help' => 'Only these NPC names will offer ferry travel.',
            ],
        ],
    ];

    return $fields[$codeName] ?? [];
}

function herikaActionCatalogGetBuiltinParameterTemplate($codeName)
{
    $templates = [
        'RentRoom' => [
            'amount' => '{{config.cost_gold}}',
        ],
        'HireCarriage' => [
            'target' => '{{parameter_target}}',
            'amount' => '{{config.cost_gold}}',
        ],
        'HireFerry' => [
            'target' => '{{parameter_target}}',
            'amount' => '{{config.cost_gold}}',
        ],
    ];

    return $templates[$codeName] ?? null;
}

function herikaActionCatalogGetBuiltinCooldownSeconds($codeName)
{
    $cooldowns = [
        'ComeCloser' => 120,
        'WaitHere' => 300,
        'UseSoulGaze' => 300,
        'Relax' => 180,
        'MakeAToast' => 60,
        'Toast' => 60,
        'StartRitualCeremony' => 60,
        'Follow' => 60,
        'FollowPlayer' => 60,
        'ReturnBackHome' => 60,
        'PickupItem' => 60
    ];

    // Cooldowns are overriden by core_actions metadata (even if cooldown_seconds property is not present)
    // Check BOTH on the core_actions table (metadata column) - and core_actions_custom table (metadata column) for cooldown_seconds value

    return $cooldowns[$codeName] ?? null;
}

function herikaActionCatalogGetBuiltinRequirements($codeName)
{
    $requirements = [
        'RentRoom' => [
            'npc_factions_any' => ['0005091B'],
            'activity' => [
                'current_action_not_in' => ['dead', 'unconscious', 'sleeping'],
            ],
        ],
        'HireCarriage' => [
            'npc_name_in_action_config_list' => [
                'config_key' => 'allowed_npc_names',
            ],
            'activity' => [
                'current_action_not_in' => ['dead', 'unconscious', 'sleeping', 'combat', 'attacking'],
            ],
        ],
        'HireFerry' => [
            'npc_name_in_action_config_list' => [
                'config_key' => 'allowed_npc_names',
            ],
            'activity' => [
                'current_action_not_in' => ['dead', 'unconscious', 'sleeping', 'combat', 'attacking'],
            ],
        ],
        'AddBounty' => [
            'npc_factions_any' => ['00086EEE', '00028848', '00028849'],
        ],
        'PayBounty' => [
            'npc_factions_any' => ['00086EEE', '00028848', '00028849'],
        ],
        'ArrestPlayer' => [
            'npc_factions_any' => ['00086EEE', '00028848', '00028849'],
        ],
        'ForgiveCrime' => [
            'npc_factions_any' => ['00086EEE', '00028848', '00028849'],
        ],
        'ReturnBackHome' => [
            'requires_rolemaster' => true,
        ],
        'Training' => [
            'requires_training_service' => true,
        ],
        'SheatheWeapon' => [
            'activity' => [
                'require_fresh' => true,
                'is_weapon_drawn' => true,
                'current_action_not_in' => ['dead', 'unconscious', 'sleeping'],
            ],
        ],
        'TakeASeat' => [
            'activity' => [
                'current_action_not_in' => ['dead', 'unconscious', 'sleeping', 'sitting', 'using', 'leaning'],
            ],
        ],
        'GoToSleep' => [
            'activity' => [
                'current_action_not_in' => ['dead', 'unconscious', 'sleeping', 'combat', 'attacking'],
            ],
        ],
        'Relax' => [
            'activity' => [
                'current_action_not_in' => ['dead', 'unconscious', 'sleeping', 'combat', 'attacking'],
            ],
        ],
        'Drink' => [
            'activity' => [
                'current_action_not_in' => ['sitting'],
            ],
        ],
        'Toast' => [
            'activity' => [
                'current_action_not_in' => ['sitting'],
            ],
        ],
        'StartRitualCeremony' => [
            'activity' => [
                'current_action_not_in' => ['dead', 'unconscious', 'sleeping', 'combat', 'attacking', 'ritual', 'sitting'],
            ],
        ],
        'EndRitualCeremony' => [
            'activity' => [
                'current_action_in' => ['ritual'],
            ],
        ],
    ];

    return $requirements[$codeName] ?? [];
}

function herikaActionCatalogBuildBaseMetadata($codeName, $scriptProxyProgram = null)
{
    $dispatch = 'plugin_command';
    if ($scriptProxyProgram !== null) {
        $dispatch = 'script_proxy';
    } elseif (in_array($codeName, ['CreateNewNPC', 'DirectorCommand', 'CreateTasks', 'ResolveTask', 'CancelTask'], true)) {
        $dispatch = 'server_action';
    } elseif (in_array($codeName, ['Training', 'TeleportNPC', 'SpawnItem', 'SpawnGold', 'SpawnNPC', 'KillTarget'], true)) {
        $dispatch = 'rolecommand';
    }

    $metadata = [
        'dispatch' => $dispatch,
        'builtin' => true,
        'status' => 'active',
        'source' => 'functions.php',
        'confirmation' => [
            'default_policy' => herikaActionCatalogGetDefaultConfirmationPolicy($codeName),
        ],
    ];

    $editorFields = herikaActionCatalogGetBuiltinEditorFields($codeName);
    if (count($editorFields) > 0) {
        $metadata['editor_fields'] = $editorFields;
    }

    $parameterTemplate = herikaActionCatalogGetBuiltinParameterTemplate($codeName);
    if ($parameterTemplate !== null) {
        $metadata['parameter_template'] = $parameterTemplate;
    }

    $requirements = herikaActionCatalogGetBuiltinRequirements($codeName);
    if (count($requirements) > 0) {
        $metadata['requirements'] = $requirements;
    }

    $cooldownSeconds = herikaActionCatalogGetBuiltinCooldownSeconds($codeName);
    if ($cooldownSeconds !== null) {
        $metadata['cooldown_seconds'] = intval($cooldownSeconds);
    }

    $followupConfig = herikaActionCatalogBuildBaseFollowupConfig($codeName);
    if (count($followupConfig) > 0) {
        $metadata['followup'] = $followupConfig;
    }

    return $metadata;
}

function herikaActionCatalogNormalizeFollowupConfig($config)
{
    if (!is_array($config)) {
        return [];
    }

    $normalized = [];
    if (array_key_exists('enabled', $config)) {
        $normalized['enabled'] = herikaActionCatalogToBool($config['enabled']);
    }

    $prompt = trim(strval($config['prompt'] ?? ''));
    if ($prompt !== '') {
        $normalized['prompt'] = $prompt;
    }

    $argName = trim(strval($config['arg_name'] ?? ''));
    if ($argName !== '') {
        $normalized['arg_name'] = $argName;
    }

    if (array_key_exists('use_functions_again', $config)) {
        $normalized['use_functions_again'] = herikaActionCatalogToBool($config['use_functions_again']);
    }

    return $normalized;
}

function herikaActionCatalogGetFollowupChainLimit()
{
    return 1;
}

function herikaActionCatalogGetFollowupChainMarkerPrefix()
{
    return '__chim_followup_chain__';
}

function herikaActionCatalogParseActionsIssuedOriginalValue($value)
{
    $value = strval($value);
    $parsed = [
        'is_followup_chain' => false,
        'followup_chain_depth' => 0,
        'original' => $value,
    ];

    if ($value === '') {
        return $parsed;
    }

    $prefix = herikaActionCatalogGetFollowupChainMarkerPrefix();
    if (strpos($value, $prefix) !== 0) {
        return $parsed;
    }

    $payload = json_decode(substr($value, strlen($prefix)), true);
    if (!is_array($payload)) {
        return $parsed;
    }

    $depth = max(0, intval($payload['depth'] ?? 0));
    $parsed['is_followup_chain'] = $depth > 0;
    $parsed['followup_chain_depth'] = $depth;
    $parsed['original'] = strval($payload['original'] ?? '');

    return $parsed;
}

function herikaActionCatalogEncodeActionsIssuedOriginalValue($originalValue, $depth)
{
    $depth = max(0, intval($depth));
    if ($depth <= 0) {
        return strval($originalValue);
    }

    $payload = [
        'depth' => $depth,
    ];

    $originalValue = strval($originalValue);
    if ($originalValue !== '') {
        $payload['original'] = $originalValue;
    }

    return herikaActionCatalogGetFollowupChainMarkerPrefix()
        . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function herikaActionCatalogApplyFollowupChainToActionsIssuedOriginal($originalValue)
{
    $depth = intval($GLOBALS["FOLLOWUP_CHAIN_NEXT_DEPTH"] ?? 0);
    if ($depth <= 0) {
        return strval($originalValue);
    }

    return herikaActionCatalogEncodeActionsIssuedOriginalValue($originalValue, $depth);
}

function herikaActionCatalogBuildBaseFollowupConfig($codeName)
{
    $disabledFollowUpCodes = [
        'Attack',
        'Consume',
        'FollowPlayer',
        'ForgiveCrime',
        'GiveGoldTo',
        'GiveItemTo',
        'HireCarriage',
        'HireFerry',
        'MoveTo',
        'RentRoom',
        'TakeGoldFromPlayer',
    ];

    $promptMap = [
        'GetTopicInfo' => ['arg_name' => 'topic', 'prompt' => 'Reply with one short in-character line about the requested topic using the tool result below. Do not ask follow-up questions.'],
        'MoveTo' => ['arg_name' => 'target', 'prompt' => 'Reply with one short in-character line acknowledging that you moved to the target. Do not ask follow-up questions.'],
        'Attack' => ['arg_name' => 'target', 'prompt' => 'Reply with one short in-character combat line reacting to the attack outcome. Do not ask follow-up questions.'],
        'Inspect' => ['arg_name' => 'target', 'prompt' => 'Reply with one short in-character observation using the inspect result below. Do not ask follow-up questions.'],
        'InspectSurroundings' => ['arg_name' => 'target', 'prompt' => 'Reply with one short in-character observation about the surroundings using the tool result below. Do not ask follow-up questions.'],
        'GetTime' => ['arg_name' => 'datestring', 'prompt' => 'Reply with one short in-character line acknowledging the reported time. Do not ask follow-up questions.'],
        'get_current_mission' => ['arg_name' => 'description', 'prompt' => 'Reply with one short in-character line about the current mission using the tool result below. Do not ask follow-up questions.'],
        'CheckInventory' => ['arg_name' => 'target', 'prompt' => 'Reply with one short in-character line about the inventory result below. Do not ask follow-up questions.'],
        'ReadQuestJournal' => ['arg_name' => 'id_quest', 'prompt' => 'Reply with one short in-character line about the quest journal result below. Do not ask follow-up questions.'],
        'GiveItemTo' => ['arg_name' => 'target', 'prompt' => 'Reply with one short in-character line reacting to the item handoff result below. Do not ask follow-up questions.'],
        'GiveGoldTo' => ['arg_name' => 'target', 'prompt' => 'Reply with one short in-character line reacting to the gold transfer result below. Do not ask follow-up questions.'],
        'RentRoom' => ['arg_name' => 'target', 'prompt' => 'Reply with one short in-character confirmation that the room rental is complete. Do not ask follow-up questions.'],
        'HireCarriage' => ['arg_name' => 'target', 'prompt' => 'Reply with one short in-character line accepting payment and ending the conversation. Do not ask follow-up questions.'],
        'HireFerry' => ['arg_name' => 'target', 'prompt' => 'Reply with one short in-character line accepting payment and ending the conversation. Do not ask follow-up questions.'],
        'AddBounty' => ['arg_name' => 'target', 'prompt' => 'You just added a bounty to #PLAYER_NAME#. React in character. You may follow up with another action if appropriate.', 'use_functions_again' => true],
        'PayBounty' => ['arg_name' => 'target', 'prompt' => '#PLAYER_NAME# has already paid the bounty and stolen items were removed from inventory. This action is fully complete. Reply with one short confirmation line, do not ask follow-up questions, and end the conversation.'],
        'ArrestPlayer' => ['arg_name' => 'target', 'prompt' => 'You attempted to arrest #PLAYER_NAME#. They get a submit or resist prompt; resist starts combat. Reply with one short stern final line. Do not ask follow-up questions.'],
        'ForgiveCrime' => ['arg_name' => 'target', 'prompt' => 'You forgave #PLAYER_NAME#\'s crimes and cleared their bounty. Reply with one short in-character acknowledgment, warning, or blessing. Do not ask follow-up questions.'],
    ];

    if (in_array($codeName, $disabledFollowUpCodes, true)) {
        $config = $promptMap[$codeName] ?? [];
        return herikaActionCatalogNormalizeFollowupConfig([
            'enabled' => false,
        ] + $config);
    }

    if (isset($promptMap[$codeName])) {
        return herikaActionCatalogNormalizeFollowupConfig([
            'enabled' => true,
        ] + $promptMap[$codeName]);
    }

    return [];
}

function herikaActionCatalogGetResolvedFollowupConfig($codeName, $row = null)
{
    $codeName = trim(strval($codeName));
    if ($codeName === '') {
        return [];
    }

    if ($codeName === 'UseSoulGaze') {
        return herikaActionCatalogNormalizeFollowupConfig([
            'enabled' => false,
        ]);
    }

    if (!is_array($row)) {
        $row = herikaGetActionCatalogRow($codeName);
    }
    if (!is_array($row)) {
        return [];
    }

    $metadata = herikaActionCatalogDecodeJson($row['metadata'] ?? [], []);
    $resolvedConfig = herikaActionCatalogNormalizeFollowupConfig($metadata['followup'] ?? []);

    $customConfig = is_array($metadata['custom_config'] ?? null) ? $metadata['custom_config'] : [];
    $resolvedCustomConfig = herikaActionCatalogGetResolvedCustomConfig($codeName, $row);
    $customKeyToConfigKeyMap = [
        'followup_enabled' => 'enabled',
        'followup_arg_name' => 'arg_name',
        'followup_prompt' => 'prompt',
        'followup_use_functions_again' => 'use_functions_again',
    ];

    foreach ($customKeyToConfigKeyMap as $customKey => $configKey) {
        if (!array_key_exists($customKey, $customConfig) || !array_key_exists($customKey, $resolvedCustomConfig)) {
            continue;
        }

        $resolvedConfig[$configKey] = $resolvedCustomConfig[$customKey];
    }

    if (!empty($resolvedConfig['prompt']) && function_exists('herikaFormatActionPromptTemplate')) {
        $resolvedConfig['prompt'] = herikaFormatActionPromptTemplate(
            strval($resolvedConfig['prompt']),
            [],
            $row
        );
    }

    return herikaActionCatalogNormalizeFollowupConfig($resolvedConfig);
}

function herikaActionCatalogGetLastIssuedActionFollowupChainDepth($codeName)
{
    $codeName = trim(strval($codeName));
    if ($codeName === '') {
        return 0;
    }

    $rows = herikaActionCatalogGetLastActionsIssuedMap();
    $row = is_array($rows) ? ($rows[$codeName] ?? null) : null;
    if (!is_array($row)) {
        return 0;
    }

    $parsed = herikaActionCatalogParseActionsIssuedOriginalValue($row['original'] ?? '');
    return max(0, intval($parsed['followup_chain_depth'] ?? 0));
}

function herikaActionCatalogIsGameFunction($metadata)
{
    $dispatch = strtolower(trim(strval($metadata['dispatch'] ?? 'plugin_command')));
    return !in_array($dispatch, ['server_action', 'server_query'], true);
}

function herikaActionCatalogNormalizeRequirementStringList($values)
{
    if (is_string($values)) {
        $values = explode(',', $values);
    }

    if (!is_array($values)) {
        return [];
    }

    $normalized = [];
    foreach ($values as $value) {
        $text = strtolower(trim(strval($value)));
        if ($text === '') {
            continue;
        }

        $normalized[] = $text;
    }

    return array_values(array_unique($normalized));
}

function herikaActionCatalogRequirementListContains($needle, $values)
{
    $needle = strtolower(trim(strval($needle)));
    if ($needle === '') {
        return false;
    }

    return in_array($needle, herikaActionCatalogNormalizeRequirementStringList($values), true);
}

function herikaActionCatalogGetCurrentNpcLookup()
{
    static $cachedKey = null;
    static $cachedLookup = null;

    $herikaName = trim(strval($GLOBALS["HERIKA_NAME"] ?? ''));
    if ($cachedKey === $herikaName && is_array($cachedLookup)) {
        return $cachedLookup;
    }

    $cachedKey = $herikaName;
    $cachedLookup = [
        'npc_master' => null,
        'npc_data' => [],
        'metadata' => [],
        'extended' => [],
    ];

    if ($herikaName === '' || $herikaName === '(actor)' || !class_exists('NpcMaster')) {
        return $cachedLookup;
    }

    $npcMaster = new NpcMaster();
    $npcData = $npcMaster->getByName($herikaName);
    if (!is_array($npcData) || count($npcData) === 0) {
        return $cachedLookup;
    }

    $cachedLookup['npc_master'] = $npcMaster;
    $cachedLookup['npc_data'] = $npcData;
    $cachedLookup['metadata'] = $npcMaster->getMetadata($npcData);
    $cachedLookup['extended'] = $npcMaster->getExtendedData($npcData);

    return $cachedLookup;
}

function herikaActionCatalogGetRuntimeRequirementContext()
{
    static $cachedKey = null;
    static $cachedContext = null;

    $requestType = strtolower(trim(strval($GLOBALS["gameRequest"][0] ?? '')));
    $cacheKey = implode('|', [
        trim(strval($GLOBALS["HERIKA_NAME"] ?? '')),
        trim(strval($GLOBALS["PLAYER_NAME"] ?? '')),
        !empty($GLOBALS["IS_NPC"]) ? '1' : '0',
        $requestType,
        strval($GLOBALS["gameRequest"][2] ?? ''),
        !empty($GLOBALS["is_rolemastered"]) ? '1' : '0',
    ]);

    if ($cachedKey === $cacheKey && is_array($cachedContext)) {
        return $cachedContext;
    }

    require_once __DIR__ . DIRECTORY_SEPARATOR . 'activity_status.php';

    $lookup = herikaActionCatalogGetCurrentNpcLookup();
    $metadata = is_array($lookup['metadata']) ? $lookup['metadata'] : [];
    $extended = is_array($lookup['extended']) ? $lookup['extended'] : [];
    $activityStatus = chimNormalizeActivityStatus($metadata);

    $cachedKey = $cacheKey;
    $cachedContext = [
        'npc_name' => trim(strval($GLOBALS["HERIKA_NAME"] ?? '')),
        'player_name' => trim(strval($GLOBALS["PLAYER_NAME"] ?? '')),
        'request_type' => $requestType,
        'is_rechat' => in_array($requestType, ['rechat', 'narration'], true),
        'is_npc_mode' => !empty($GLOBALS["IS_NPC"]),
        'is_rolemastered' => herikaResolveNpcRolemasterState($GLOBALS["HERIKA_NAME"] ?? '', [
            'metadata' => $metadata,
            'extended' => $extended,
            'npc_data' => $lookup['npc_data'],
            'load_lookup' => false,
        ]),
        'npc_master' => $lookup['npc_master'],
        'npc_data' => $lookup['npc_data'],
        'npc_metadata' => $metadata,
        'npc_extended' => $extended,
        'activity_status' => $activityStatus,
    ];

    return $cachedContext;
}

function herikaActionCatalogGetConfigListValues($definition)
{
    $configKey = '';
    $fallbackCsv = '';
    $fallbackValues = [];

    if (is_string($definition)) {
        $configKey = trim($definition);
    } elseif (is_array($definition)) {
        $configKey = trim(strval($definition['config_key'] ?? ''));
        $fallbackCsv = trim(strval($definition['fallback_csv'] ?? ''));
        $fallbackValues = $definition['fallback_values'] ?? [];
    }

    $rawValues = '';
    if ($configKey !== '' && isset($GLOBALS[$configKey])) {
        $rawValues = trim(strval($GLOBALS[$configKey]));
    }
    if ($rawValues === '') {
        $rawValues = $fallbackCsv;
    }

    $values = herikaActionCatalogNormalizeRequirementStringList($rawValues);
    if (count($fallbackValues) > 0) {
        $values = array_values(array_unique(array_merge(
            $values,
            herikaActionCatalogNormalizeRequirementStringList($fallbackValues)
        )));
    }

    return $values;
}

function herikaActionCatalogGetActionConfigListValues($config, $definition)
{
    $config = is_array($config) ? $config : [];
    $configKey = '';
    $fallbackCsv = '';
    $fallbackValues = [];

    if (is_string($definition)) {
        $configKey = trim($definition);
    } elseif (is_array($definition)) {
        $configKey = trim(strval($definition['config_key'] ?? ''));
        $fallbackCsv = trim(strval($definition['fallback_csv'] ?? ''));
        $fallbackValues = $definition['fallback_values'] ?? [];
    }

    $rawValues = '';
    if ($configKey !== '' && array_key_exists($configKey, $config)) {
        $rawValues = strval($config[$configKey]);
    }
    if (trim($rawValues) === '') {
        $rawValues = $fallbackCsv;
    }

    $values = herikaActionCatalogNormalizeRequirementStringList(preg_split('/[\r\n,]+/', $rawValues) ?: []);
    if (count($fallbackValues) > 0) {
        $values = array_values(array_unique(array_merge(
            $values,
            herikaActionCatalogNormalizeRequirementStringList($fallbackValues)
        )));
    }

    return $values;
}

function herikaActionCatalogNpcMatchesFactionRequirement($npcMaster, $npcData, $factionIds, $requireAll = false)
{
    $factionIds = herikaActionCatalogNormalizeRequirementStringList($factionIds);
    if (count($factionIds) === 0) {
        return true;
    }

    if (!$npcMaster || !is_array($npcData) || count($npcData) === 0) {
        return false;
    }

    $npcFactions = $npcMaster->getNpcFactions($npcData, true);

    foreach ($factionIds as $factionId) {
        $matches = false;
        $stableReference = chimParseStableFormReference($factionId);

        if ($stableReference) {
            foreach ($npcFactions as $npcFaction) {
                if (chimFactionEntryMatchesStableFormReference($npcFaction, $stableReference['stable_key'])) {
                    $matches = true;
                    break;
                }
            }

            if (!$matches) {
                $runtimeFormId = chimResolveStableFormReferenceToRuntimeFormId($stableReference['stable_key']);
                if ($runtimeFormId !== null) {
                    $matches = $npcMaster->isNpcInFaction($npcData, $runtimeFormId);
                }
            }
        } else {
            $matches = $npcMaster->isNpcInFaction($npcData, strtoupper($factionId));
        }

        if ($requireAll && !$matches) {
            return false;
        }
        if (!$requireAll && $matches) {
            return true;
        }
    }

    return $requireAll;
}

function herikaActionCatalogMatchesActivityRequirements($requirements, $status)
{
    $requirements = herikaActionCatalogDecodeJson($requirements, []);
    if (!is_array($requirements) || count($requirements) === 0) {
        return true;
    }

    $status = is_array($status) ? $status : [];
    $available = !empty($status['available']);
    $fresh = !empty($status['fresh']);

    if (!empty($requirements['require_available']) && !$available) {
        return false;
    }
    if (!empty($requirements['require_fresh']) && !$fresh) {
        return false;
    }

    $boolKeys = [
        'is_in_combat',
        'is_attacking',
        'is_moving',
        'is_running',
        'is_sneaking',
        'is_sitting',
        'is_sleeping',
        'is_unconscious',
        'is_dead',
        'is_weapon_drawn',
    ];

    foreach ($boolKeys as $boolKey) {
        if (!array_key_exists($boolKey, $requirements)) {
            continue;
        }

        $expected = herikaActionCatalogToBool($requirements[$boolKey]);
        if (!$available) {
            if ($expected) {
                return false;
            }
            continue;
        }

        if (herikaActionCatalogToBool($status[$boolKey] ?? false) !== $expected) {
            return false;
        }
    }

    $currentAction = strtolower(trim(strval($status['current_action'] ?? '')));
    $useType = strtolower(trim(strval($status['use_type'] ?? '')));

    if (isset($requirements['current_action'])) {
        $expectedAction = strtolower(trim(strval($requirements['current_action'])));
        if ($expectedAction !== '' && $currentAction !== $expectedAction) {
            return false;
        }
    }

    $currentActionIn = herikaActionCatalogNormalizeRequirementStringList($requirements['current_action_in'] ?? []);
    if (count($currentActionIn) > 0) {
        if ($currentAction === '' || !in_array($currentAction, $currentActionIn, true)) {
            return false;
        }
    }

    $currentActionNotIn = herikaActionCatalogNormalizeRequirementStringList($requirements['current_action_not_in'] ?? []);
    if ($currentAction !== '' && in_array($currentAction, $currentActionNotIn, true)) {
        return false;
    }

    if (isset($requirements['use_type'])) {
        $expectedUseType = strtolower(trim(strval($requirements['use_type'])));
        if ($expectedUseType !== '' && $useType !== $expectedUseType) {
            return false;
        }
    }

    $useTypeIn = herikaActionCatalogNormalizeRequirementStringList($requirements['use_type_in'] ?? []);
    if (count($useTypeIn) > 0) {
        if ($useType === '' || !in_array($useType, $useTypeIn, true)) {
            return false;
        }
    }

    $useTypeNotIn = herikaActionCatalogNormalizeRequirementStringList($requirements['use_type_not_in'] ?? []);
    if ($useType !== '' && in_array($useType, $useTypeNotIn, true)) {
        return false;
    }

    return true;
}

function herikaActionCatalogRequirementsMatch($requirements, $context)
{
    $requirements = herikaActionCatalogDecodeJson($requirements, []);
    if (!is_array($requirements) || count($requirements) === 0) {
        return true;
    }

    $context = is_array($context) ? $context : herikaActionCatalogGetRuntimeRequirementContext();

    if (isset($requirements['requires_rolemaster'])) {
        $expectedRolemaster = herikaActionCatalogToBool($requirements['requires_rolemaster']);
        if (herikaActionCatalogToBool($context['is_rolemastered'] ?? false) !== $expectedRolemaster) {
            return false;
        }
    }

    if (isset($requirements['requires_training_service'])) {
        $hasTrainingService = !empty($context['npc_extended']['class']['teaches']);
        if ($hasTrainingService !== herikaActionCatalogToBool($requirements['requires_training_service'])) {
            return false;
        }
    }

    if (!empty($requirements['hide_in_rechat']) && !empty($context['is_rechat'])) {
        return false;
    }
    if (!empty($requirements['show_only_in_rechat']) && empty($context['is_rechat'])) {
        return false;
    }

    $requestTypesAny = herikaActionCatalogNormalizeRequirementStringList($requirements['request_types_any'] ?? []);
    if (count($requestTypesAny) > 0 && !in_array(strtolower(trim(strval($context['request_type'] ?? ''))), $requestTypesAny, true)) {
        return false;
    }

    $requestTypesNone = herikaActionCatalogNormalizeRequirementStringList($requirements['request_types_none'] ?? []);
    if (count($requestTypesNone) > 0 && in_array(strtolower(trim(strval($context['request_type'] ?? ''))), $requestTypesNone, true)) {
        return false;
    }

    $npcNamesAny = herikaActionCatalogNormalizeRequirementStringList($requirements['npc_names_any'] ?? []);
    if (count($npcNamesAny) > 0 && !in_array(strtolower(trim(strval($context['npc_name'] ?? ''))), $npcNamesAny, true)) {
        return false;
    }

    if (isset($requirements['npc_name_in_config_list'])) {
        $allowedNpcNames = herikaActionCatalogGetConfigListValues($requirements['npc_name_in_config_list']);
        if (count($allowedNpcNames) > 0 && !in_array(strtolower(trim(strval($context['npc_name'] ?? ''))), $allowedNpcNames, true)) {
            return false;
        }
    }

    if (isset($requirements['npc_name_in_action_config_list'])) {
        $allowedNpcNames = herikaActionCatalogGetActionConfigListValues(
            $context['action_config'] ?? [],
            $requirements['npc_name_in_action_config_list']
        );
        if (count($allowedNpcNames) > 0 && !in_array(strtolower(trim(strval($context['npc_name'] ?? ''))), $allowedNpcNames, true)) {
            return false;
        }
    }

    if (!herikaActionCatalogNpcMatchesFactionRequirement(
        $context['npc_master'] ?? null,
        $context['npc_data'] ?? [],
        $requirements['npc_factions_any'] ?? [],
        false
    )) {
        return false;
    }

    if (!herikaActionCatalogNpcMatchesFactionRequirement(
        $context['npc_master'] ?? null,
        $context['npc_data'] ?? [],
        $requirements['npc_factions_all'] ?? [],
        true
    )) {
        return false;
    }

    if (!herikaActionCatalogMatchesActivityRequirements($requirements['activity'] ?? [], $context['activity_status'] ?? [])) {
        return false;
    }

    return true;
}

function herikaActionCatalogGetLastActionsIssuedMap()
{
    static $cachedKey = null;
    static $cachedRows = null;

    if (!isset($GLOBALS["db"]) || !($GLOBALS["db"] instanceof sql)) {
        return [];
    }

    $localActorName = trim(strval($GLOBALS["HERIKA_NAME"] ?? ''));
    if ($localActorName === '') {
        return [];
    }

    if ($cachedKey === $localActorName && is_array($cachedRows)) {
        return $cachedRows;
    }

    $escapedActorName = $GLOBALS["db"]->escape($localActorName);
    $rows = $GLOBALS["db"]->fetchAll(
        "SELECT * FROM (
            SELECT DISTINCT ON (action) *
            FROM actions_issued
            WHERE (actorname = '$escapedActorName' or actorname like '%$escapedActorName,%' or actorname='*')
            ORDER BY action, gamets DESC, ts DESC
        ) AS sub
        ORDER BY gamets DESC, ts DESC"
    );

    $cachedKey = $localActorName;
    $cachedRows = [];
    foreach ($rows as $row) {
        $actionCode = trim(strval($row['action'] ?? ''));
        if ($actionCode === '') {
            continue;
        }

        $cachedRows[$actionCode] = $row;
    }

    return $cachedRows;
}

function herikaActionCatalogIsActionOnCooldown($codeName, $cooldownSeconds)
{
    $codeName = trim(strval($codeName));
    $cooldownSeconds = intval($cooldownSeconds);
    if ($codeName === '' || $cooldownSeconds <= 0 || empty($GLOBALS["gameRequest"][2])) {
        return false;
    }

    require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utils_game_timestamp.php';

    $lastActionsIssuedMap = herikaActionCatalogGetLastActionsIssuedMap();
    if (!isset($lastActionsIssuedMap[$codeName])) {
        return false;
    }

    $ingameNow = convert_gamets2seconds($GLOBALS["gameRequest"][2]);
    $lastTriggered = convert_gamets2seconds($lastActionsIssuedMap[$codeName]["gamets"] ?? 0);
    if ($ingameNow <= 0 || $lastTriggered <= 0) {
        return false;
    }

    return ($ingameNow - $lastTriggered) < $cooldownSeconds;
}

function herikaActionCatalogRowMatchesRequirements($row, $context = null)
{
    if (!is_array($row)) {
        return true;
    }

    $metadata = herikaActionCatalogDecodeJson($row['metadata'] ?? [], []);
    $context = is_array($context) ? $context : herikaActionCatalogGetRuntimeRequirementContext();
    $context['action_config'] = function_exists('herikaActionCatalogGetResolvedCustomConfig')
        ? herikaActionCatalogGetResolvedCustomConfig($row['code_name'] ?? '', $row)
        : [];

    if (!herikaActionCatalogRequirementsMatch($metadata['requirements'] ?? [], $context)) {
        // error_log("[FUNCTIONS COOLDOWN] Action '{$row['code_name']}' did not match requirements.");
        return false;
    }

    $cooldownSeconds = intval($metadata['cooldown_seconds'] ?? 0);
    if ($cooldownSeconds > 0 && herikaActionCatalogIsActionOnCooldown($row['code_name'] ?? '', $cooldownSeconds)) {
        error_log("[FUNCTIONS COOLDOWN] Action '{$row['code_name']}' is on cooldown for {$cooldownSeconds} seconds.");
        return false;
    } else {
        // error_log("[FUNCTIONS COOLDOWN] Action '{$row['code_name']}' is not on cooldown. ($cooldownSeconds)");
    }

    return true;
}

function herikaActionCatalogResetCache()
{
    unset($GLOBALS["HERIKA_ACTION_CATALOG_DB_READY"]);
    unset($GLOBALS["HERIKA_ACTION_CATALOG_ROWS_BY_CODE"]);
}

function herikaActionCatalogDbReady()
{
    if (isset($GLOBALS["HERIKA_ACTION_CATALOG_DB_READY"])) {
        return $GLOBALS["HERIKA_ACTION_CATALOG_DB_READY"];
    }

    if (($GLOBALS["DBDRIVER"] ?? '') !== 'postgresql') {
        $GLOBALS["HERIKA_ACTION_CATALOG_DB_READY"] = false;
        return false;
    }

    if (!isset($GLOBALS["db"]) || !($GLOBALS["db"] instanceof sql)) {
        $GLOBALS["HERIKA_ACTION_CATALOG_DB_READY"] = false;
        return false;
    }

    $coreAction = $GLOBALS["db"]->fetchOne("
        SELECT 1 AS exists
        FROM information_schema.tables
        WHERE table_schema = 'public' AND table_name = 'core_action'
    ");
    $coreActionCustom = $GLOBALS["db"]->fetchOne("
        SELECT 1 AS exists
        FROM information_schema.tables
        WHERE table_schema = 'public' AND table_name = 'core_action_custom'
    ");
    $combinedView = $GLOBALS["db"]->fetchOne("
        SELECT 1 AS exists
        FROM information_schema.views
        WHERE table_schema = 'public' AND table_name = 'combined_core_action'
    ");

    $ready = isset($coreAction["exists"]) && isset($coreActionCustom["exists"]) && isset($combinedView["exists"]);
    $GLOBALS["HERIKA_ACTION_CATALOG_DB_READY"] = $ready;
    return $ready;
}

function herikaActionCatalogGetExistingCustomImportVersion($codeName)
{
    $codeName = trim(strval($codeName));
    if ($codeName === '' || !herikaActionCatalogDbReady()) {
        return null;
    }

    $row = $GLOBALS["db"]->fetchOne("
        SELECT import_version
        FROM public.core_action_custom
        WHERE LOWER(code_name) = LOWER(" . herikaActionCatalogSqlText($codeName) . ")
        LIMIT 1
    ");

    if (!is_array($row) || !array_key_exists('import_version', $row)) {
        return null;
    }

    return herikaActionCatalogNormalizeImportVersion($row['import_version']);
}

function herikaBuildActionCatalogFunctionDefinitionsByCode($runtimeFunctions = null)
{
    $definitions = [];
    $runtimeFunctions = is_array($runtimeFunctions) ? $runtimeFunctions : ($GLOBALS["FUNCTIONS"] ?? []);
    $retiredCodes = array_fill_keys(herikaGetRetiredActionCodes(), true);

    foreach ($runtimeFunctions as $functionEntry) {
        if (!is_array($functionEntry) || empty($functionEntry['name'])) {
            continue;
        }

        $codeName = function_exists('getFunctionCodeName') ? getFunctionCodeName($functionEntry['name']) : false;
        if ($codeName === false || isset($retiredCodes[$codeName])) {
            continue;
        }

        $definitions[$codeName] = $functionEntry;
    }

    return $definitions;
}

function herikaBuildActionCatalogSeedRows($actionNames, $descriptions, $returnMessages, $currentEnabledCodes = [], $defaultEnabledCodes = [], $functionDefinitionsByCode = [], $seedDefaultsByCode = null)
{
    $seedDefaultsByCode = is_array($seedDefaultsByCode) ? $seedDefaultsByCode : herikaLoadActionCatalogBaseSeedRowsFromSeedFile();
    $activationDefaults = count($defaultEnabledCodes) > 0
        ? $defaultEnabledCodes
        : array_unique(array_merge(
            array_keys($seedDefaultsByCode),
            is_array($currentEnabledCodes) ? $currentEnabledCodes : []
        ));
    $allCodeNames = array_unique(array_merge(
        array_keys(is_array($actionNames) ? $actionNames : []),
        array_keys(is_array($descriptions) ? $descriptions : []),
        array_keys(is_array($returnMessages) ? $returnMessages : []),
        is_array($currentEnabledCodes) ? $currentEnabledCodes : [],
        $activationDefaults,
        array_keys($seedDefaultsByCode),
        array_keys(is_array($functionDefinitionsByCode) ? $functionDefinitionsByCode : [])
    ));

    natcasesort($allCodeNames);

    $retiredCodes = array_fill_keys(herikaGetRetiredActionCodes(), true);
    $scriptProxyPrograms = herikaActionCatalogGetBaseScriptProxyPrograms();
    $rows = [];

    foreach ($allCodeNames as $codeName) {
        $codeName = trim(strval($codeName));
        if ($codeName === '' || isset($retiredCodes[$codeName])) {
            continue;
        }

        $seedDefaults = is_array($seedDefaultsByCode[$codeName] ?? null) ? $seedDefaultsByCode[$codeName] : [];
        $availableToNpc = herikaActionCatalogToBool($seedDefaults['available_to_npc'] ?? false);
        $availableToFollowers = herikaActionCatalogToBool($seedDefaults['available_to_followers'] ?? false);
        $availableToNarrator = herikaActionCatalogToBool($seedDefaults['available_to_narrator'] ?? false);
        $isActivated = array_key_exists('is_activated', $seedDefaults)
            ? herikaActionCatalogToBool($seedDefaults['is_activated'])
            : (in_array($codeName, $activationDefaults, true) || in_array($codeName, $currentEnabledCodes, true));
        $functionDefinition = is_array($functionDefinitionsByCode[$codeName] ?? null) ? $functionDefinitionsByCode[$codeName] : [];
        $parameters = herikaActionCatalogNormalizeParameterSchema($functionDefinition['parameters'] ?? null);
        $scriptProxyProgram = $scriptProxyPrograms[$codeName] ?? null;
        $metadata = herikaActionCatalogBuildBaseMetadata($codeName, $scriptProxyProgram);

        $rows[$codeName] = [
            'code_name' => $codeName,
            'action_name' => isset($actionNames[$codeName]) && trim(strval($actionNames[$codeName])) !== ''
                ? herikaNormalizeActionCatalogDisplayActionName($actionNames[$codeName])
                : $codeName,
            'description' => isset($descriptions[$codeName]) ? herikaNormalizeActionCatalogDisplayText($descriptions[$codeName]) : '',
            'return_message' => isset($returnMessages[$codeName]) ? herikaNormalizeActionCatalogDisplayText($returnMessages[$codeName]) : '',
            'available_to_npc' => $availableToNpc,
            'available_to_followers' => $availableToFollowers,
            'available_to_narrator' => $availableToNarrator,
            'is_activated' => $isActivated,
            'parameters_json' => $parameters,
            'metadata' => $metadata,
            'game_function' => herikaActionCatalogIsGameFunction($metadata),
            'import_version' => 0,
            'script_proxy_program' => $scriptProxyProgram,
        ];
    }

    return $rows;
}

function herikaDeleteRetiredActionCatalogRows($updateCustomRows = true)
{
    if (!herikaActionCatalogDbReady()) {
        return;
    }

    $retiredCodes = herikaGetRetiredActionCodes();
    if (count($retiredCodes) === 0) {
        return;
    }

    $literals = [];
    foreach ($retiredCodes as $retiredCode) {
        $literals[] = herikaActionCatalogSqlText($retiredCode);
    }

    $inList = implode(',', $literals);
    if ($updateCustomRows) {
        $GLOBALS["db"]->execQuery("DELETE FROM public.core_action_custom WHERE code_name IN ({$inList})");
    }
    $GLOBALS["db"]->execQuery("DELETE FROM public.core_action WHERE code_name IN ({$inList})");
}

function herikaSyncActionCatalogBaseRows($rowsByCode, $updateCustomRows = true)
{
    if (!herikaActionCatalogDbReady()) {
        return;
    }

    herikaDeleteRetiredActionCatalogRows($updateCustomRows);
    herikaDeleteUnexpectedBaseActionCatalogRows($rowsByCode, $updateCustomRows);

    $existingCustomMetadataByCode = [];
    if ($updateCustomRows) {
        $existingCustomRows = $GLOBALS["db"]->fetchAll("
            SELECT code_name, metadata
            FROM public.core_action_custom
        ");
        foreach ($existingCustomRows as $existingCustomRow) {
            $existingCodeName = strtolower(trim(strval($existingCustomRow['code_name'] ?? '')));
            if ($existingCodeName === '') {
                continue;
            }

            $existingCustomMetadataByCode[$existingCodeName] = herikaActionCatalogDecodeJson($existingCustomRow['metadata'] ?? [], []);
        }
    }

    foreach ($rowsByCode as $row) {
        if (!is_array($row) || empty($row['code_name'])) {
            continue;
        }

        $preservedCustomMetadata = herikaActionCatalogMergePreservedCustomMetadata(
            $row['metadata'] ?? [],
            $existingCustomMetadataByCode[strtolower(trim(strval($row['code_name'])))] ?? []
        );

        $GLOBALS["db"]->execQuery("
            INSERT INTO public.core_action (
                code_name,
                action_name,
                description,
                return_message,
                available_to_npc,
                available_to_followers,
                available_to_narrator,
                is_activated,
                parameters_json,
                metadata,
                game_function,
                import_version,
                script_proxy_program
            ) VALUES (
                " . herikaActionCatalogSqlText($row['code_name']) . ",
                " . herikaActionCatalogSqlText($row['action_name'] ?? '') . ",
                " . herikaActionCatalogSqlText($row['description'] ?? '') . ",
                " . herikaActionCatalogSqlText($row['return_message'] ?? '') . ",
                " . herikaActionCatalogSqlBool(!empty($row['available_to_npc'])) . ",
                " . herikaActionCatalogSqlBool(!empty($row['available_to_followers'])) . ",
                " . herikaActionCatalogSqlBool(!empty($row['available_to_narrator'])) . ",
                " . herikaActionCatalogSqlBool(!empty($row['is_activated'])) . ",
                " . herikaActionCatalogSqlJson($row['parameters_json'] ?? []) . ",
                " . herikaActionCatalogSqlJson($row['metadata'] ?? []) . ",
                " . herikaActionCatalogSqlBool(!empty($row['game_function'])) . ",
                " . herikaActionCatalogNormalizeImportVersion($row['import_version'] ?? 0) . ",
                " . herikaActionCatalogSqlJson($row['script_proxy_program'] ?? null, true) . "
            )
            ON CONFLICT (code_name) DO UPDATE SET
                action_name = EXCLUDED.action_name,
                description = EXCLUDED.description,
                return_message = EXCLUDED.return_message,
                available_to_npc = EXCLUDED.available_to_npc,
                available_to_followers = EXCLUDED.available_to_followers,
                available_to_narrator = EXCLUDED.available_to_narrator,
                is_activated = EXCLUDED.is_activated,
                parameters_json = EXCLUDED.parameters_json,
                metadata = EXCLUDED.metadata,
                game_function = EXCLUDED.game_function,
                import_version = EXCLUDED.import_version,
                script_proxy_program = EXCLUDED.script_proxy_program,
                updated_at = NOW()
        ");

        if ($updateCustomRows) {
            $GLOBALS["db"]->execQuery("
                UPDATE public.core_action_custom
                SET
                    action_name = " . herikaActionCatalogSqlText($row['action_name'] ?? '') . ",
                    description = " . herikaActionCatalogSqlText($row['description'] ?? '') . ",
                    return_message = " . herikaActionCatalogSqlText($row['return_message'] ?? '') . ",
                    available_to_npc = " . herikaActionCatalogSqlBool(!empty($row['available_to_npc'])) . ",
                    available_to_followers = " . herikaActionCatalogSqlBool(!empty($row['available_to_followers'])) . ",
                    available_to_narrator = " . herikaActionCatalogSqlBool(!empty($row['available_to_narrator'])) . ",
                    parameters_json = " . herikaActionCatalogSqlJson($row['parameters_json'] ?? []) . ",
                    metadata = " . herikaActionCatalogSqlJson($preservedCustomMetadata) . ",
                    game_function = " . herikaActionCatalogSqlBool(!empty($row['game_function'])) . ",
                    import_version = " . herikaActionCatalogNormalizeImportVersion($row['import_version'] ?? 0) . ",
                    script_proxy_program = " . herikaActionCatalogSqlJson($row['script_proxy_program'] ?? null, true) . ",
                    updated_at = NOW()
                WHERE LOWER(code_name) = LOWER(" . herikaActionCatalogSqlText($row['code_name']) . ")
            ");
        }
    }

    herikaActionCatalogResetCache();
}

function herikaDeleteUnexpectedBaseActionCatalogRows($rowsByCode, $updateCustomRows = true)
{
    if (!herikaActionCatalogDbReady()) {
        return;
    }

    $seedCodeLiterals = [];
    foreach ($rowsByCode as $row) {
        if (!is_array($row) || empty($row['code_name'])) {
            continue;
        }

        $seedCodeLiterals[] = herikaActionCatalogSqlText(strtolower(trim(strval($row['code_name']))));
    }

    if (count($seedCodeLiterals) === 0) {
        return;
    }

    $seedCodeList = implode(',', array_unique($seedCodeLiterals));
    $builtinFilter = "metadata @> '{\"source\":\"functions.php\",\"builtin\":true}'::jsonb";

    $GLOBALS["db"]->execQuery("
        DELETE FROM public.core_action
        WHERE {$builtinFilter}
          AND LOWER(code_name) NOT IN ({$seedCodeList})
    ");

    if ($updateCustomRows) {
        $GLOBALS["db"]->execQuery("
            DELETE FROM public.core_action_custom
            WHERE {$builtinFilter}
              AND LOWER(code_name) NOT IN ({$seedCodeList})
        ");
    }
}

function herikaMarkLegacyActionPreferencesImported()
{
    if (!isset($GLOBALS["db"]) || !($GLOBALS["db"] instanceof sql)) {
        return;
    }

    $GLOBALS["db"]->execQuery("
        INSERT INTO public.conf_opts (id, value)
        VALUES ('core_action_legacy_user_pref_imported', '1')
        ON CONFLICT (id) DO UPDATE SET value = EXCLUDED.value
    ");
}

function herikaLegacyActionPreferencesImported()
{
    if (!isset($GLOBALS["db"]) || !($GLOBALS["db"] instanceof sql)) {
        return false;
    }

    $row = $GLOBALS["db"]->fetchOne("
        SELECT value
        FROM public.conf_opts
        WHERE id = 'core_action_legacy_user_pref_imported'
        LIMIT 1
    ");

    return isset($row['value']) && trim(strval($row['value'])) === '1';
}

function herikaImportLegacyActionPreferences($rowsByCode)
{
    if (!herikaActionCatalogDbReady() || herikaLegacyActionPreferencesImported()) {
        return;
    }

    $userPrefPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'functions' . DIRECTORY_SEPARATOR . 'user_pref.json';
    if (!file_exists($userPrefPath)) {
        herikaMarkLegacyActionPreferencesImported();
        return;
    }

    $selectedCodes = json_decode(file_get_contents($userPrefPath), true);
    if (!is_array($selectedCodes) || count($selectedCodes) === 0) {
        herikaMarkLegacyActionPreferencesImported();
        return;
    }

    $selectedMap = array_fill_keys(array_map('strval', $selectedCodes), true);
    foreach ($rowsByCode as $row) {
        if (!is_array($row) || empty($row['code_name'])) {
            continue;
        }

        $GLOBALS["db"]->execQuery("
            INSERT INTO public.core_action_custom (
                code_name,
                action_name,
                description,
                return_message,
                available_to_npc,
                available_to_followers,
                available_to_narrator,
                is_activated,
                parameters_json,
                metadata,
                game_function,
                import_version,
                script_proxy_program
            ) VALUES (
                " . herikaActionCatalogSqlText($row['code_name']) . ",
                " . herikaActionCatalogSqlText($row['action_name'] ?? '') . ",
                " . herikaActionCatalogSqlText($row['description'] ?? '') . ",
                " . herikaActionCatalogSqlText($row['return_message'] ?? '') . ",
                " . herikaActionCatalogSqlBool(!empty($row['available_to_npc'])) . ",
                " . herikaActionCatalogSqlBool(!empty($row['available_to_followers'])) . ",
                " . herikaActionCatalogSqlBool(!empty($row['available_to_narrator'])) . ",
                " . herikaActionCatalogSqlBool(isset($selectedMap[$row['code_name']])) . ",
                " . herikaActionCatalogSqlJson($row['parameters_json'] ?? []) . ",
                " . herikaActionCatalogSqlJson($row['metadata'] ?? []) . ",
                " . herikaActionCatalogSqlBool(!empty($row['game_function'])) . ",
                " . herikaActionCatalogNormalizeImportVersion($row['import_version'] ?? 0) . ",
                " . herikaActionCatalogSqlJson($row['script_proxy_program'] ?? null, true) . "
            )
            ON CONFLICT (code_name) DO UPDATE SET
                action_name = EXCLUDED.action_name,
                description = EXCLUDED.description,
                return_message = EXCLUDED.return_message,
                available_to_npc = EXCLUDED.available_to_npc,
                available_to_followers = EXCLUDED.available_to_followers,
                available_to_narrator = EXCLUDED.available_to_narrator,
                is_activated = EXCLUDED.is_activated,
                parameters_json = EXCLUDED.parameters_json,
                metadata = EXCLUDED.metadata,
                game_function = EXCLUDED.game_function,
                import_version = EXCLUDED.import_version,
                script_proxy_program = EXCLUDED.script_proxy_program,
                updated_at = NOW()
        ");
    }

    herikaMarkLegacyActionPreferencesImported();
    herikaActionCatalogResetCache();
}

function herikaGetActionCatalogRowsByCode()
{
    if (isset($GLOBALS["HERIKA_ACTION_CATALOG_ROWS_BY_CODE"])) {
        return $GLOBALS["HERIKA_ACTION_CATALOG_ROWS_BY_CODE"];
    }

    $GLOBALS["HERIKA_ACTION_CATALOG_ROWS_BY_CODE"] = [];
    if (!herikaActionCatalogDbReady()) {
        return $GLOBALS["HERIKA_ACTION_CATALOG_ROWS_BY_CODE"];
    }

    $rows = $GLOBALS["db"]->fetchAll("
        SELECT
            code_name,
            action_name,
            description,
            return_message,
            available_to_npc,
            available_to_followers,
            available_to_narrator,
            is_activated,
            parameters_json,
            metadata,
            game_function,
            import_version,
            script_proxy_program
        FROM public.combined_core_action
    ");

    foreach ($rows as $row) {
        $codeName = trim(strval($row['code_name'] ?? ''));
        if ($codeName === '') {
            continue;
        }

        $normalizedRow = [
            'code_name' => $codeName,
            'action_name' => herikaNormalizeActionCatalogDisplayActionName(strval($row['action_name'] ?? $codeName)),
            'description' => strval($row['description'] ?? ''),
            'return_message' => strval($row['return_message'] ?? ''),
            'available_to_npc' => herikaActionCatalogToBool($row['available_to_npc'] ?? false),
            'available_to_followers' => herikaActionCatalogToBool($row['available_to_followers'] ?? false),
            'available_to_narrator' => herikaActionCatalogToBool($row['available_to_narrator'] ?? false),
            'is_activated' => herikaActionCatalogToBool($row['is_activated'] ?? false),
            'parameters_json' => herikaActionCatalogNormalizeParameterSchema(
                herikaActionCatalogDecodeJson($row['parameters_json'] ?? [], [])
            ),
            'metadata' => herikaActionCatalogDecodeJson($row['metadata'] ?? [], []),
            'game_function' => herikaActionCatalogToBool($row['game_function'] ?? false),
            'import_version' => herikaActionCatalogNormalizeImportVersion($row['import_version'] ?? 0),
            'script_proxy_program' => herikaActionCatalogDecodeJson($row['script_proxy_program'] ?? null, []),
        ];
        $GLOBALS["HERIKA_ACTION_CATALOG_ROWS_BY_CODE"][$codeName] = herikaActionCatalogApplyCompatibilityOverrides($normalizedRow);
    }

    return $GLOBALS["HERIKA_ACTION_CATALOG_ROWS_BY_CODE"];
}

function herikaGetActionCatalogRow($codeName)
{
    $codeName = trim(strval($codeName));
    if ($codeName === '') {
        return null;
    }

    $rowsByCode = herikaGetActionCatalogRowsByCode();
    return $rowsByCode[$codeName] ?? null;
}

function herikaActionCatalogMetadataFlagEnabled($codeName, $flagName): bool
{
    $flagName = trim(strval($flagName));
    if ($flagName === '') {
        return false;
    }

    $row = herikaGetActionCatalogRow($codeName);
    if (!is_array($row)) {
        return false;
    }

    $metadata = $row['metadata'] ?? [];
    if (!is_array($metadata) || !array_key_exists($flagName, $metadata)) {
        return false;
    }

    return herikaActionCatalogToBool($metadata[$flagName]);
}

function herikaFindActionCatalogRowByNameOrCode($actionNameOrCode, $requireCurrentMode = false)
{
    $actionNameOrCode = trim(strval($actionNameOrCode));
    if ($actionNameOrCode === '') {
        return null;
    }

    $rowsByCode = herikaGetActionCatalogRowsByCode();
    if (count($rowsByCode) === 0) {
        return null;
    }

    $normalizedSearchName = function_exists('herikaNormalizeActionCatalogDisplayActionName')
        ? trim(strval(herikaNormalizeActionCatalogDisplayActionName($actionNameOrCode)))
        : $actionNameOrCode;

    $matchedRow = null;
    foreach ($rowsByCode as $row) {
        if (!is_array($row) || empty($row['code_name'])) {
            continue;
        }
        if ($requireCurrentMode && !herikaActionCatalogRowIsAvailableInCurrentMode($row)) {
            continue;
        }

        $rowCodeName = trim(strval($row['code_name'] ?? ''));
        $rawActionName = trim(strval($row['action_name'] ?? ''));
        $runtimeActionName = function_exists('herikaFormatActionPromptTemplate')
            ? trim(strval(herikaFormatActionPromptTemplate($rawActionName, [], $row)))
            : $rawActionName;
        $normalizedRuntimeActionName = function_exists('herikaNormalizeActionCatalogDisplayActionName')
            ? trim(strval(herikaNormalizeActionCatalogDisplayActionName($runtimeActionName)))
            : $runtimeActionName;

        $isMatch = strcasecmp($rowCodeName, $actionNameOrCode) === 0
            || strcasecmp($rawActionName, $actionNameOrCode) === 0
            || strcasecmp($runtimeActionName, $actionNameOrCode) === 0
            || ($normalizedSearchName !== '' && strcasecmp($normalizedRuntimeActionName, $normalizedSearchName) === 0);
        if (!$isMatch) {
            continue;
        }

        if ($matchedRow === null || herikaActionCatalogShouldPreferRowForActionName($row, $matchedRow)) {
            $matchedRow = $row;
        }
    }

    return $matchedRow;
}

function herikaResolveActionCatalogCodeName($actionNameOrCode, $requireCurrentMode = false)
{
    $row = herikaFindActionCatalogRowByNameOrCode($actionNameOrCode, $requireCurrentMode);
    if (!is_array($row) || empty($row['code_name'])) {
        return false;
    }

    return trim(strval($row['code_name']));
}

function herikaFindActionCatalogActionNameConflict($actionName, $excludeCodeName = '')
{
    $actionName = trim(strval($actionName));
    $excludeCodeName = trim(strval($excludeCodeName));
    if ($actionName === '') {
        return null;
    }

    $rowsByCode = herikaGetActionCatalogRowsByCode();
    if (count($rowsByCode) === 0) {
        return null;
    }

    $normalizedSearchName = function_exists('herikaNormalizeActionCatalogDisplayActionName')
        ? trim(strval(herikaNormalizeActionCatalogDisplayActionName($actionName)))
        : $actionName;
    if ($normalizedSearchName === '') {
        return null;
    }

    foreach ($rowsByCode as $row) {
        if (!is_array($row) || empty($row['code_name'])) {
            continue;
        }

        $rowCodeName = trim(strval($row['code_name'] ?? ''));
        if ($rowCodeName === '') {
            continue;
        }
        if ($excludeCodeName !== '' && strcasecmp($rowCodeName, $excludeCodeName) === 0) {
            continue;
        }

        $rawActionName = trim(strval($row['action_name'] ?? ''));
        $runtimeActionName = function_exists('herikaFormatActionPromptTemplate')
            ? trim(strval(herikaFormatActionPromptTemplate($rawActionName, [], $row)))
            : $rawActionName;
        $normalizedCodeName = function_exists('herikaNormalizeActionCatalogDisplayActionName')
            ? trim(strval(herikaNormalizeActionCatalogDisplayActionName($rowCodeName)))
            : $rowCodeName;
        $normalizedRawActionName = function_exists('herikaNormalizeActionCatalogDisplayActionName')
            ? trim(strval(herikaNormalizeActionCatalogDisplayActionName($rawActionName)))
            : $rawActionName;
        $normalizedRuntimeActionName = function_exists('herikaNormalizeActionCatalogDisplayActionName')
            ? trim(strval(herikaNormalizeActionCatalogDisplayActionName($runtimeActionName)))
            : $runtimeActionName;

        if (
            ($normalizedCodeName !== '' && strcasecmp($normalizedCodeName, $normalizedSearchName) === 0) ||
            ($normalizedRawActionName !== '' && strcasecmp($normalizedRawActionName, $normalizedSearchName) === 0) ||
            ($normalizedRuntimeActionName !== '' && strcasecmp($normalizedRuntimeActionName, $normalizedSearchName) === 0)
        ) {
            return $row;
        }
    }

    return null;
}

function herikaActionCatalogGetCustomConfigValue($codeName, $configKey, $default = null)
{
    $codeName = trim(strval($codeName));
    $configKey = trim(strval($configKey));
    if ($codeName === '' || $configKey === '') {
        return $default;
    }

    $row = herikaGetActionCatalogRow($codeName);
    if (!is_array($row)) {
        return $default;
    }

    $config = herikaActionCatalogGetResolvedCustomConfig($codeName, $row);
    if (!array_key_exists($configKey, $config)) {
        return $default;
    }

    return $config[$configKey];
}

function herikaLoadEnabledActionCodesForMode($isNpc, $applyRequirements = false)
{
    $rowsByCode = herikaGetActionCatalogRowsByCode();
    if (count($rowsByCode) === 0) {
        return [];
    }

    $enabledCodes = [];
    foreach ($rowsByCode as $codeName => $row) {
        if (!$row['is_activated']) {
            continue;
        }

        if ($applyRequirements && !herikaActionCatalogRowMatchesRequirements($row)) {
            continue;
        }

        if (herikaActionCatalogIsNarratorMode()) {
            if (!empty($row['available_to_narrator'])) {
                $enabledCodes[] = $codeName;
            }
        } elseif ($isNpc && !empty($row['available_to_npc'])) {
            $enabledCodes[] = $codeName;
        } elseif (!$isNpc && !empty($row['available_to_followers'])) {
            $enabledCodes[] = $codeName;
        }
    }

    return array_values(array_unique($enabledCodes));
}

function herikaActionCatalogIsActionEnabled($codeName)
{
    $codeName = trim(strval($codeName));
    if ($codeName === '') {
        return false;
    }

    $rowsByCode = herikaGetActionCatalogRowsByCode();
    if (!isset($rowsByCode[$codeName])) {
        return true;
    }

    return !empty($rowsByCode[$codeName]['is_activated']);
}

function herikaActionCatalogIsNarratorMode()
{
    $requestType = strtolower(trim(strval($GLOBALS["gameRequest"][0] ?? '')));
    if (in_array($requestType, [
        'narrator_inputtext',
        'narration',
        'narrator_welcome',
        'narrator_quest_comment',
    ], true)) {
        return true;
    }

    if (!empty($GLOBALS["DIRECT_NARRATOR_DIALOGUE"])) {
        return true;
    }

    return strcasecmp(trim(strval($GLOBALS["HERIKA_NAME"] ?? '')), 'The Narrator') === 0;
}

function herikaActionCatalogBuildFunctionEntryFromRow($row)
{
    $row = herikaActionCatalogApplyCompatibilityOverrides($row);
    if (!is_array($row) || empty($row['code_name']) || trim(strval($row['action_name'] ?? '')) === '') {
        return null;
    }

    $runtimeActionName = function_exists('herikaFormatActionPromptTemplate')
        ? herikaFormatActionPromptTemplate(strval($row['action_name'] ?? ''), [], $row)
        : strval($row['action_name'] ?? '');
    $runtimeDescription = function_exists('herikaFormatActionPromptTemplate')
        ? herikaFormatActionPromptTemplate(strval($row['description'] ?? ''), [], $row)
        : strval($row['description'] ?? '');

    return [
        'name' => $runtimeActionName,
        'description' => $runtimeDescription,
        'parameters' => herikaActionCatalogNormalizeParameterSchema($row['parameters_json'] ?? null),
    ];
}

function herikaActionCatalogRowIsAvailableInCurrentMode($row)
{
    if (herikaActionCatalogIsNarratorMode()) {
        return !empty($row['available_to_narrator']);
    }

    $isNpcMode = !empty($GLOBALS["IS_NPC"]);
    if ($isNpcMode) {
        return !empty($row['available_to_npc']);
    }

    return !empty($row['available_to_followers']);
}

function herikaActionCatalogHasBaseRows()
{
    if (!herikaActionCatalogDbReady()) {
        return false;
    }

    $row = $GLOBALS["db"]->fetchOne("
        SELECT 1 AS has_row
        FROM public.core_action
        LIMIT 1
    ");

    return is_array($row) && !empty($row['has_row']);
}

function herikaGetActionCatalogBaseSeedFilePath()
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'core_action_seed.sql';
}

function herikaSeedActionCatalogBaseRowsFromSeedFile()
{
    if (!herikaActionCatalogDbReady()) {
        return false;
    }

    $seedFile = herikaGetActionCatalogBaseSeedFilePath();
    if (!file_exists($seedFile)) {
        return false;
    }

    $sql = trim(strval(file_get_contents($seedFile)));
    if ($sql === '') {
        return false;
    }

    try {
        $GLOBALS["db"]->execQuery($sql);
        herikaActionCatalogResetCache();
        return herikaActionCatalogHasBaseRows();
    } catch (Throwable $e) {
        if (class_exists('Logger')) {
            Logger::warn("core_action seed import failed: " . $e->getMessage());
        }
    }

    return false;
}

function herikaEnsureActionCatalogBaseRowsSeeded($rowsByCode)
{
    if (!herikaActionCatalogDbReady() || herikaActionCatalogHasBaseRows()) {
        return false;
    }

    if (herikaSeedActionCatalogBaseRowsFromSeedFile()) {
        return true;
    }

    herikaSyncActionCatalogBaseRows($rowsByCode, false);
    herikaActionCatalogResetCache();
    return true;
}

function herikaActionCatalogRowIsUsableInCurrentContext($row)
{
    if (!is_array($row) || empty($row['is_activated'])) {
        return false;
    }

    if (!herikaActionCatalogRowIsAvailableInCurrentMode($row)) {
        return false;
    }

    return herikaActionCatalogRowMatchesRequirements($row);
}

function herikaActionCatalogShouldPreferRowForActionName($candidateRow, $currentRow)
{
    $candidateAvailable = herikaActionCatalogRowIsUsableInCurrentContext($candidateRow);
    $currentAvailable = herikaActionCatalogRowIsUsableInCurrentContext($currentRow);
    if ($candidateAvailable !== $currentAvailable) {
        return $candidateAvailable;
    }

    $candidateEnabled = !empty($candidateRow['is_activated']);
    $currentEnabled = !empty($currentRow['is_activated']);
    if ($candidateEnabled !== $currentEnabled) {
        return $candidateEnabled;
    }

    $candidateBuiltin = !empty(($candidateRow['metadata'] ?? [])['builtin']);
    $currentBuiltin = !empty(($currentRow['metadata'] ?? [])['builtin']);
    if ($candidateBuiltin !== $currentBuiltin) {
        return !$candidateBuiltin;
    }

    $candidateDispatch = strtolower(trim(strval(($candidateRow['metadata'] ?? [])['dispatch'] ?? '')));
    $currentDispatch = strtolower(trim(strval(($currentRow['metadata'] ?? [])['dispatch'] ?? '')));
    if ($candidateDispatch !== $currentDispatch) {
        if ($candidateDispatch === 'script_proxy') {
            return true;
        }
        if ($currentDispatch === 'script_proxy') {
            return false;
        }
    }

    return false;
}

function herikaActionCatalogApplyRowsToRuntimeFunctions()
{
    $rowsByCode = herikaGetActionCatalogRowsByCode();
    if (count($rowsByCode) === 0) {
        return;
    }

    $runtimeFunctionMap = [];
    $fallbackBaseFunctionMap = is_array($GLOBALS["HERIKA_BASE_FUNCTIONS_FALLBACK"] ?? null)
        ? $GLOBALS["HERIKA_BASE_FUNCTIONS_FALLBACK"]
        : [];
    foreach ($GLOBALS["FUNCTIONS"] ?? [] as $functionEntry) {
        if (!is_array($functionEntry) || empty($functionEntry['name'])) {
            continue;
        }

        $codeName = function_exists('getFunctionCodeName') ? getFunctionCodeName($functionEntry['name']) : false;
        if ($codeName === false || in_array($codeName, herikaGetRetiredActionCodes(), true)) {
            continue;
        }

        if (isset($fallbackBaseFunctionMap[$codeName])) {
            continue;
        }

        $runtimeFunctionMap[$codeName] = $functionEntry;
    }

    foreach ($rowsByCode as $codeName => $row) {
        $runtimeActionName = function_exists('herikaFormatActionPromptTemplate')
            ? herikaFormatActionPromptTemplate(strval($row['action_name'] ?? ''), [], $row)
            : strval($row['action_name'] ?? '');
        $runtimeDescription = function_exists('herikaFormatActionPromptTemplate')
            ? herikaFormatActionPromptTemplate($row['description'] ?? '', [], $row)
            : strval($row['description'] ?? '');

        $GLOBALS["F_NAMES"][$codeName] = $runtimeActionName;
        $GLOBALS["F_TRANSLATIONS"][$codeName] = $runtimeDescription;
        $GLOBALS["F_RETURNMESSAGES"][$codeName] = strval($row['return_message'] ?? '');

        $catalogFunctionEntry = herikaActionCatalogBuildFunctionEntryFromRow($row);
        if ($catalogFunctionEntry === null) {
            continue;
        }

        $catalogFunctionEntry['description'] = $runtimeDescription;

        if (isset($runtimeFunctionMap[$codeName])) {
            $runtimeFunctionMap[$codeName]['name'] = $catalogFunctionEntry['name'];
            $runtimeFunctionMap[$codeName]['description'] = $runtimeDescription;
            $runtimeFunctionMap[$codeName]['parameters'] = $catalogFunctionEntry['parameters'];
        } else {
            $runtimeFunctionMap[$codeName] = $catalogFunctionEntry;
        }
    }

    $preferredCodeByActionName = [];
    foreach ($runtimeFunctionMap as $codeName => $functionEntry) {
        $actionName = trim(strval($functionEntry['name'] ?? ''));
        if ($actionName === '') {
            continue;
        }

        if (!isset($preferredCodeByActionName[$actionName])) {
            $preferredCodeByActionName[$actionName] = $codeName;
            continue;
        }

        $currentCode = $preferredCodeByActionName[$actionName];
        $candidateRow = $rowsByCode[$codeName] ?? ['metadata' => ['builtin' => true], 'is_activated' => true];
        $currentRow = $rowsByCode[$currentCode] ?? ['metadata' => ['builtin' => true], 'is_activated' => true];
        if (herikaActionCatalogShouldPreferRowForActionName($candidateRow, $currentRow)) {
            $preferredCodeByActionName[$actionName] = $codeName;
        }
    }

    $dedupedRuntimeFunctionMap = [];
    foreach ($preferredCodeByActionName as $actionName => $codeName) {
        if (isset($runtimeFunctionMap[$codeName])) {
            $dedupedRuntimeFunctionMap[$codeName] = $runtimeFunctionMap[$codeName];
        }
    }

    $GLOBALS["HERIKA_ACTION_NAME_PREFERRED_CODE"] = $preferredCodeByActionName;
    $GLOBALS["BASE_FUNCTIONS"] = $dedupedRuntimeFunctionMap;
    $GLOBALS["FUNCTIONS"] = array_values($dedupedRuntimeFunctionMap);
}

function herikaActionCatalogUpsertCustomToggle($codeName, $enabled)
{
    $codeName = trim(strval($codeName));
    if ($codeName === '' || !herikaActionCatalogDbReady()) {
        return false;
    }

    $literalCode = herikaActionCatalogSqlText($codeName);
    $row = $GLOBALS["db"]->fetchOne("
        SELECT
            code_name,
            action_name,
            description,
            return_message,
            available_to_npc,
            available_to_followers,
            available_to_narrator,
            parameters_json,
            metadata,
            game_function,
            import_version,
            script_proxy_program
        FROM public.combined_core_action
        WHERE code_name = {$literalCode}
        LIMIT 1
    ");

    if (!$row) {
        return false;
    }

    $actionName = herikaNormalizeActionCatalogDisplayActionName(strval($row['action_name'] ?? ''));

    $result = $GLOBALS["db"]->execQuery("
        INSERT INTO public.core_action_custom (
            code_name,
            action_name,
            description,
            return_message,
            available_to_npc,
            available_to_followers,
            available_to_narrator,
            is_activated,
            parameters_json,
            metadata,
            game_function,
            import_version,
            script_proxy_program
        ) VALUES (
            " . herikaActionCatalogSqlText($row['code_name'] ?? $codeName) . ",
            " . herikaActionCatalogSqlText($actionName) . ",
            " . herikaActionCatalogSqlText($row['description'] ?? '') . ",
            " . herikaActionCatalogSqlText($row['return_message'] ?? '') . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['available_to_npc'] ?? false)) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['available_to_followers'] ?? false)) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['available_to_narrator'] ?? false)) . ",
            " . herikaActionCatalogSqlBool((bool) $enabled) . ",
            " . herikaActionCatalogSqlJson($row['parameters_json'] ?? []) . ",
            " . herikaActionCatalogSqlJson($row['metadata'] ?? []) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['game_function'] ?? false)) . ",
            " . herikaActionCatalogNormalizeImportVersion($row['import_version'] ?? 0) . ",
            " . herikaActionCatalogSqlJson($row['script_proxy_program'] ?? null, true) . "
        )
        ON CONFLICT (code_name) DO UPDATE SET
            action_name = EXCLUDED.action_name,
            description = EXCLUDED.description,
            return_message = EXCLUDED.return_message,
            available_to_npc = EXCLUDED.available_to_npc,
            available_to_followers = EXCLUDED.available_to_followers,
            available_to_narrator = EXCLUDED.available_to_narrator,
            is_activated = EXCLUDED.is_activated,
            parameters_json = EXCLUDED.parameters_json,
            metadata = EXCLUDED.metadata,
            game_function = EXCLUDED.game_function,
            import_version = EXCLUDED.import_version,
            script_proxy_program = EXCLUDED.script_proxy_program,
            updated_at = NOW()
    ");

    herikaActionCatalogResetCache();
    return $result !== false;
}

function herikaActionCatalogDeleteCustomOverride($codeName)
{
    $codeName = trim(strval($codeName));
    if ($codeName === '' || !herikaActionCatalogDbReady()) {
        return false;
    }

    $result = $GLOBALS["db"]->execQuery("
        DELETE FROM public.core_action_custom
        WHERE LOWER(code_name) = LOWER(" . herikaActionCatalogSqlText($codeName) . ")
    ");

    herikaActionCatalogResetCache();
    return $result !== false;
}

function herikaActionCatalogUpsertCustomConfigValue($codeName, $configKey, $value)
{
    $codeName = trim(strval($codeName));
    $configKey = trim(strval($configKey));
    if ($codeName === '' || $configKey === '' || !herikaActionCatalogDbReady()) {
        return false;
    }

    $literalCode = herikaActionCatalogSqlText($codeName);
    $row = $GLOBALS["db"]->fetchOne("
        SELECT
            code_name,
            action_name,
            description,
            return_message,
            available_to_npc,
            available_to_followers,
            available_to_narrator,
            is_activated,
            parameters_json,
            metadata,
            game_function,
            import_version,
            script_proxy_program
        FROM public.combined_core_action
        WHERE code_name = {$literalCode}
        LIMIT 1
    ");

    if (!$row) {
        return false;
    }

    return herikaActionCatalogUpsertCustomConfig($codeName, [$configKey => $value]);
}

function herikaActionCatalogUpsertCustomConfig($codeName, $configValues)
{
    $codeName = trim(strval($codeName));
    if ($codeName === '' || !is_array($configValues) || !herikaActionCatalogDbReady()) {
        return false;
    }

    $literalCode = herikaActionCatalogSqlText($codeName);
    $row = $GLOBALS["db"]->fetchOne("
        SELECT
            code_name,
            action_name,
            description,
            return_message,
            available_to_npc,
            available_to_followers,
            available_to_narrator,
            is_activated,
            parameters_json,
            metadata,
            game_function,
            import_version,
            script_proxy_program
        FROM public.combined_core_action
        WHERE code_name = {$literalCode}
        LIMIT 1
    ");

    if (!$row) {
        return false;
    }

    $metadata = herikaActionCatalogDecodeJson($row['metadata'] ?? [], []);
    if (!isset($metadata['custom_config']) || !is_array($metadata['custom_config'])) {
        $metadata['custom_config'] = [];
    }

    foreach ($configValues as $configKey => $configValue) {
        $configKey = trim(strval($configKey));
        if ($configKey === '') {
            continue;
        }
        $metadata['custom_config'][$configKey] = $configValue;
    }

    $actionName = herikaNormalizeActionCatalogDisplayActionName(strval($row['action_name'] ?? ''));

    $result = $GLOBALS["db"]->execQuery("
        INSERT INTO public.core_action_custom (
            code_name,
            action_name,
            description,
            return_message,
            available_to_npc,
            available_to_followers,
            available_to_narrator,
            is_activated,
            parameters_json,
            metadata,
            game_function,
            import_version,
            script_proxy_program
        ) VALUES (
            " . herikaActionCatalogSqlText($row['code_name'] ?? $codeName) . ",
            " . herikaActionCatalogSqlText($actionName) . ",
            " . herikaActionCatalogSqlText($row['description'] ?? '') . ",
            " . herikaActionCatalogSqlText($row['return_message'] ?? '') . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['available_to_npc'] ?? false)) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['available_to_followers'] ?? false)) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['available_to_narrator'] ?? false)) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['is_activated'] ?? false)) . ",
            " . herikaActionCatalogSqlJson($row['parameters_json'] ?? []) . ",
            " . herikaActionCatalogSqlJson($metadata) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['game_function'] ?? false)) . ",
            " . herikaActionCatalogNormalizeImportVersion($row['import_version'] ?? 0) . ",
            " . herikaActionCatalogSqlJson($row['script_proxy_program'] ?? null, true) . "
        )
        ON CONFLICT (code_name) DO UPDATE SET
            action_name = EXCLUDED.action_name,
            description = EXCLUDED.description,
            return_message = EXCLUDED.return_message,
            available_to_npc = EXCLUDED.available_to_npc,
            available_to_followers = EXCLUDED.available_to_followers,
            available_to_narrator = EXCLUDED.available_to_narrator,
            is_activated = EXCLUDED.is_activated,
            parameters_json = EXCLUDED.parameters_json,
            metadata = EXCLUDED.metadata,
            game_function = EXCLUDED.game_function,
            import_version = EXCLUDED.import_version,
            script_proxy_program = EXCLUDED.script_proxy_program,
            updated_at = NOW()
    ");

    herikaActionCatalogResetCache();
    return $result !== false;
}

function herikaActionCatalogUpsertCustomParameters($codeName, $parameters)
{
    $codeName = trim(strval($codeName));
    if ($codeName === '' || !herikaActionCatalogDbReady()) {
        return false;
    }

    $literalCode = herikaActionCatalogSqlText($codeName);
    $row = $GLOBALS["db"]->fetchOne("
        SELECT
            code_name,
            action_name,
            description,
            return_message,
            available_to_npc,
            available_to_followers,
            available_to_narrator,
            is_activated,
            parameters_json,
            metadata,
            game_function,
            import_version,
            script_proxy_program
        FROM public.combined_core_action
        WHERE code_name = {$literalCode}
        LIMIT 1
    ");

    if (!$row) {
        return false;
    }

    $normalizedParameters = herikaActionCatalogNormalizeParameterSchema(
        herikaActionCatalogDecodeJson($parameters, [])
    );
    $actionName = herikaNormalizeActionCatalogDisplayActionName(strval($row['action_name'] ?? ''));

    $result = $GLOBALS["db"]->execQuery("
        INSERT INTO public.core_action_custom (
            code_name,
            action_name,
            description,
            return_message,
            available_to_npc,
            available_to_followers,
            available_to_narrator,
            is_activated,
            parameters_json,
            metadata,
            game_function,
            import_version,
            script_proxy_program
        ) VALUES (
            " . herikaActionCatalogSqlText($row['code_name'] ?? $codeName) . ",
            " . herikaActionCatalogSqlText($actionName) . ",
            " . herikaActionCatalogSqlText($row['description'] ?? '') . ",
            " . herikaActionCatalogSqlText($row['return_message'] ?? '') . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['available_to_npc'] ?? false)) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['available_to_followers'] ?? false)) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['available_to_narrator'] ?? false)) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['is_activated'] ?? false)) . ",
            " . herikaActionCatalogSqlJson($normalizedParameters) . ",
            " . herikaActionCatalogSqlJson($row['metadata'] ?? []) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['game_function'] ?? false)) . ",
            " . herikaActionCatalogNormalizeImportVersion($row['import_version'] ?? 0) . ",
            " . herikaActionCatalogSqlJson($row['script_proxy_program'] ?? null, true) . "
        )
        ON CONFLICT (code_name) DO UPDATE SET
            action_name = EXCLUDED.action_name,
            description = EXCLUDED.description,
            return_message = EXCLUDED.return_message,
            available_to_npc = EXCLUDED.available_to_npc,
            available_to_followers = EXCLUDED.available_to_followers,
            available_to_narrator = EXCLUDED.available_to_narrator,
            is_activated = EXCLUDED.is_activated,
            parameters_json = EXCLUDED.parameters_json,
            metadata = EXCLUDED.metadata,
            game_function = EXCLUDED.game_function,
            import_version = EXCLUDED.import_version,
            script_proxy_program = EXCLUDED.script_proxy_program,
            updated_at = NOW()
    ");

    herikaActionCatalogResetCache();
    return $result !== false;
}

function herikaActionCatalogUpsertCustomTextFields($codeName, $fieldValues)
{
    $codeName = trim(strval($codeName));
    if ($codeName === '' || !is_array($fieldValues) || !herikaActionCatalogDbReady()) {
        return false;
    }

    $literalCode = herikaActionCatalogSqlText($codeName);
    $row = $GLOBALS["db"]->fetchOne("
        SELECT
            code_name,
            action_name,
            description,
            return_message,
            available_to_npc,
            available_to_followers,
            available_to_narrator,
            is_activated,
            parameters_json,
            metadata,
            game_function,
            import_version,
            script_proxy_program
        FROM public.combined_core_action
        WHERE code_name = {$literalCode}
        LIMIT 1
    ");

    if (!$row) {
        return false;
    }

    $actionName = herikaNormalizeActionCatalogDisplayActionName(strval($fieldValues['action_name'] ?? ($row['action_name'] ?? '')));
    if ($actionName === '') {
        return false;
    }
    if (function_exists('herikaFindActionCatalogActionNameConflict')) {
        $conflictingRow = herikaFindActionCatalogActionNameConflict($actionName, $codeName);
        if (is_array($conflictingRow) && !empty($conflictingRow['code_name'])) {
            return false;
        }
    }

    $description = strval($fieldValues['description'] ?? ($row['description'] ?? ''));
    $returnMessage = strval($fieldValues['return_message'] ?? ($row['return_message'] ?? ''));

    $result = $GLOBALS["db"]->execQuery("
        INSERT INTO public.core_action_custom (
            code_name,
            action_name,
            description,
            return_message,
            available_to_npc,
            available_to_followers,
            available_to_narrator,
            is_activated,
            parameters_json,
            metadata,
            game_function,
            import_version,
            script_proxy_program
        ) VALUES (
            " . herikaActionCatalogSqlText($row['code_name'] ?? $codeName) . ",
            " . herikaActionCatalogSqlText($actionName) . ",
            " . herikaActionCatalogSqlText($description) . ",
            " . herikaActionCatalogSqlText($returnMessage) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['available_to_npc'] ?? false)) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['available_to_followers'] ?? false)) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['available_to_narrator'] ?? false)) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['is_activated'] ?? false)) . ",
            " . herikaActionCatalogSqlJson($row['parameters_json'] ?? []) . ",
            " . herikaActionCatalogSqlJson($row['metadata'] ?? []) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['game_function'] ?? false)) . ",
            " . herikaActionCatalogNormalizeImportVersion($row['import_version'] ?? 0) . ",
            " . herikaActionCatalogSqlJson($row['script_proxy_program'] ?? null, true) . "
        )
        ON CONFLICT (code_name) DO UPDATE SET
            action_name = EXCLUDED.action_name,
            description = EXCLUDED.description,
            return_message = EXCLUDED.return_message,
            available_to_npc = EXCLUDED.available_to_npc,
            available_to_followers = EXCLUDED.available_to_followers,
            available_to_narrator = EXCLUDED.available_to_narrator,
            is_activated = EXCLUDED.is_activated,
            parameters_json = EXCLUDED.parameters_json,
            metadata = EXCLUDED.metadata,
            game_function = EXCLUDED.game_function,
            import_version = EXCLUDED.import_version,
            script_proxy_program = EXCLUDED.script_proxy_program,
            updated_at = NOW()
    ");

    herikaActionCatalogResetCache();
    return $result !== false;
}

function herikaActionCatalogUpsertCustomRow($row)
{
    if (!is_array($row) || !herikaActionCatalogDbReady()) {
        return false;
    }

    $codeName = trim(strval($row['code_name'] ?? ''));
    $actionName = herikaNormalizeActionCatalogDisplayActionName(trim(strval($row['action_name'] ?? '')));
    if ($codeName === '' || $actionName === '') {
        return false;
    }

    $parameters = herikaActionCatalogNormalizeParameterSchema(
        herikaActionCatalogDecodeJson($row['parameters_json'] ?? [], [])
    );

    $metadata = herikaActionCatalogDecodeJson($row['metadata'] ?? [], []);
    if (!isset($metadata['dispatch']) || trim(strval($metadata['dispatch'])) === '') {
        $metadata['dispatch'] = !empty($row['script_proxy_program']) ? 'script_proxy' : 'plugin_command';
    }
    if (!array_key_exists('builtin', $metadata)) {
        $metadata['builtin'] = false;
    }
    if (!isset($metadata['status']) || trim(strval($metadata['status'])) === '') {
        $metadata['status'] = 'active';
    }
    if (!isset($metadata['source']) || trim(strval($metadata['source'])) === '') {
        $metadata['source'] = 'core_action_custom';
    }

    $scriptProxyProgram = $row['script_proxy_program'] ?? null;
    if ($scriptProxyProgram !== null) {
        $scriptProxyProgram = herikaActionCatalogDecodeJson($scriptProxyProgram, []);
    }

    $gameFunction = array_key_exists('game_function', $row)
        ? herikaActionCatalogToBool($row['game_function'])
        : herikaActionCatalogIsGameFunction($metadata);
    $importVersion = herikaActionCatalogNormalizeImportVersion($row['import_version'] ?? 0);

    $result = $GLOBALS["db"]->execQuery("
        INSERT INTO public.core_action_custom (
            code_name,
            action_name,
            description,
            return_message,
            available_to_npc,
            available_to_followers,
            available_to_narrator,
            is_activated,
            parameters_json,
            metadata,
            game_function,
            import_version,
            script_proxy_program
        ) VALUES (
            " . herikaActionCatalogSqlText($codeName) . ",
            " . herikaActionCatalogSqlText($actionName) . ",
            " . herikaActionCatalogSqlText($row['description'] ?? '') . ",
            " . herikaActionCatalogSqlText($row['return_message'] ?? '') . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['available_to_npc'] ?? false)) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['available_to_followers'] ?? false)) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['available_to_narrator'] ?? false)) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['is_activated'] ?? true)) . ",
            " . herikaActionCatalogSqlJson($parameters) . ",
            " . herikaActionCatalogSqlJson($metadata) . ",
            " . herikaActionCatalogSqlBool($gameFunction) . ",
            " . $importVersion . ",
            " . herikaActionCatalogSqlJson($scriptProxyProgram, true) . "
        )
        ON CONFLICT (code_name) DO UPDATE SET
            action_name = EXCLUDED.action_name,
            description = EXCLUDED.description,
            return_message = EXCLUDED.return_message,
            available_to_npc = EXCLUDED.available_to_npc,
            available_to_followers = EXCLUDED.available_to_followers,
            available_to_narrator = EXCLUDED.available_to_narrator,
            is_activated = EXCLUDED.is_activated,
            parameters_json = EXCLUDED.parameters_json,
            metadata = EXCLUDED.metadata,
            game_function = EXCLUDED.game_function,
            import_version = EXCLUDED.import_version,
            script_proxy_program = EXCLUDED.script_proxy_program,
            updated_at = NOW()
    ");

    herikaActionCatalogResetCache();
    return $result !== false;
}

function herikaActionCatalogNormalizeRefId($value)
{
    $text = trim(strval($value));
    if ($text === '') {
        return '';
    }

    return stripos($text, '0x') === 0 ? $text : ('0x' . $text);
}

function herikaActionCatalogGetBufferCharacterCount()
{
    $totalChars = 0;
    if (!isset($GLOBALS["DEBUG"]["BUFFER"]) || !is_array($GLOBALS["DEBUG"]["BUFFER"])) {
        return 0;
    }

    foreach ($GLOBALS["DEBUG"]["BUFFER"] as $item) {
        $text = is_string($item) ? $item : strval($item);
        if (function_exists('mb_strlen')) {
            $totalChars += mb_strlen($text, 'UTF-8');
        } else {
            $totalChars += strlen($text);
        }
    }

    return $totalChars;
}

function herikaActionCatalogResolveContextPath($context, $path)
{
    if (!is_array($context)) {
        return null;
    }

    $currentValue = $context;
    foreach (explode('.', strval($path)) as $segment) {
        if ($segment === '') {
            continue;
        }

        if (!is_array($currentValue) || !array_key_exists($segment, $currentValue)) {
            return null;
        }

        $currentValue = $currentValue[$segment];
    }

    return $currentValue;
}

function herikaActionCatalogResolveTemplateString($value, $context)
{
    if (!is_string($value) || strpos($value, '{{') === false) {
        return $value;
    }

    if (preg_match('/^\{\{\s*([^}]+)\s*\}\}$/', $value, $matches)) {
        $resolved = herikaActionCatalogResolveContextPath($context, trim($matches[1]));
        if (is_array($resolved)) {
            return herikaActionCatalogJsonEncode($resolved);
        }
        return $resolved;
    }

    return preg_replace_callback('/\{\{\s*([^}]+)\s*\}\}/', function ($matches) use ($context) {
        $resolved = herikaActionCatalogResolveContextPath($context, trim($matches[1]));
        if (is_array($resolved)) {
            return herikaActionCatalogJsonEncode($resolved);
        }
        return strval($resolved ?? '');
    }, $value);
}

function herikaActionCatalogResolveTemplateValue($value, $context)
{
    if (is_array($value)) {
        $resolved = [];
        foreach ($value as $key => $item) {
            $resolved[$key] = herikaActionCatalogResolveTemplateValue($item, $context);
        }
        return $resolved;
    }

    return herikaActionCatalogResolveTemplateString($value, $context);
}

function herikaActionCatalogBuildScriptProxyContext($actionParts, $actionParts2)
{
    $actionCodeName = trim(strval($actionParts2[0] ?? ''));
    $rawParameter = strval($actionParts2[1] ?? '');
    $parameterData = [];
    $trimmedParameter = trim($rawParameter);
    if ($trimmedParameter !== '' && in_array(substr($trimmedParameter, 0, 1), ['{', '['], true)) {
        $parameterData = herikaActionCatalogDecodeJson($trimmedParameter, []);
    }

    if (!is_array($parameterData)) {
        $parameterData = [];
    }

    $npcData = [];
    $npcMetadata = [];
    if (class_exists('NpcMaster')) {
        $npcMaster = new NpcMaster();
        $npcData = $npcMaster->getByName($actionParts[0]) ?: [];
        $npcMetadata = is_array($npcData) ? ($npcMaster->getMetadata($npcData) ?: []) : [];
    }

    $bufferCharacters = herikaActionCatalogGetBufferCharacterCount();
    $parameterTarget = strval($parameterData['target'] ?? $rawParameter);
    $actionRow = $actionCodeName !== '' ? herikaGetActionCatalogRow($actionCodeName) : null;
    $resolvedConfig = $actionCodeName !== '' ? herikaActionCatalogGetResolvedCustomConfig($actionCodeName, $actionRow) : [];

    return [
        'actor_name' => strval($actionParts[0] ?? ''),
        'actor_refid' => herikaActionCatalogNormalizeRefId($npcData['refid'] ?? ''),
        'actor_furniture' => strval($npcMetadata['furniture'] ?? ''),
        'action_name' => $actionCodeName,
        'full_call' => implode('|', $actionParts),
        'parameter_raw' => $rawParameter,
        'parameter_target' => $parameterTarget,
        'parameters' => $parameterData,
        'config' => $resolvedConfig,
        'request_ts' => $GLOBALS["gameRequest"][1] ?? time(),
        'game_ts' => $GLOBALS["gameRequest"][2] ?? 0,
        'local_ts' => time(),
        'player_name' => strval($GLOBALS["PLAYER_NAME"] ?? 'Player'),
        'player_refid' => defined('PLAYER_REFID') ? strval(PLAYER_REFID) : '0x00000014',
        'cache_people_limited' => strval($GLOBALS["CACHE_PEOPLE_LIMITED"] ?? ''),
        'cache_location' => strval($GLOBALS["CACHE_LOCATION"] ?? ''),
        'cache_party' => strval($GLOBALS["CACHE_PARTY"] ?? ''),
        'toast_delay_seconds' => intval(ceil($bufferCharacters / 12)),
        'local_ts_ms' => (int) round(microtime(true) * 1000),
    ];
}

function herikaActionCatalogExecuteScriptProxyCommands($commands, $context)
{
    if (!is_array($commands) || count($commands) === 0) {
        return false;
    }

    $skyrimCommandBuilder = new SkyrimCommandBuilder();
    $executed = false;

    foreach ($commands as $command) {
        if (!is_array($command) || !isset($command['cmd_id'])) {
            continue;
        }

        $args = herikaActionCatalogResolveTemplateValue($command['args'] ?? [], $context);
        if (!is_array($args)) {
            $args = [];
        }

        $delaySeconds = herikaActionCatalogResolveTemplateValue($command['delay_seconds'] ?? 0, $context);
        $localTs = null;
        if (is_numeric($delaySeconds) && floatval($delaySeconds) > 0) {
            $localTs = time() + intval(ceil(floatval($delaySeconds)));
        }

        $json = $skyrimCommandBuilder->build(intval($command['cmd_id']), $args);
        $skyrimCommandBuilder->send($json, $localTs);
        $executed = true;
    }

    return $executed;
}

function herikaActionCatalogExecuteScriptProxyDbInserts($dbInserts, $context)
{
    if (!is_array($dbInserts) || count($dbInserts) === 0) {
        return false;
    }

    $executed = false;
    foreach ($dbInserts as $dbInsert) {
        if (!is_array($dbInsert) || empty($dbInsert['table']) || !is_array($dbInsert['data'] ?? null)) {
            continue;
        }

        $data = herikaActionCatalogResolveTemplateValue($dbInsert['data'], $context);
        if (!is_array($data)) {
            continue;
        }

        if (strcasecmp(strval($dbInsert['table']), 'actions_issued') === 0 && array_key_exists('original', $data)) {
            $data['original'] = herikaActionCatalogApplyFollowupChainToActionsIssuedOriginal($data['original']);
        }

        $GLOBALS["db"]->insert($dbInsert['table'], $data);
        $executed = true;
    }

    return $executed;
}

function herikaActionCatalogExecuteScriptProxyNpcMetadataUpdates($npcMetadataUpdates, $context)
{
    if (!is_array($npcMetadataUpdates) || count($npcMetadataUpdates) === 0) {
        return false;
    }

    $resolvedUpdates = herikaActionCatalogResolveTemplateValue($npcMetadataUpdates, $context);
    if (!is_array($resolvedUpdates) || count($resolvedUpdates) === 0) {
        return false;
    }

    require_once __DIR__ . DIRECTORY_SEPARATOR . 'activity_status.php';
    return chimApplyNpcMetadataUpdatesByName(
        trim(strval($context['actor_name'] ?? '')),
        $resolvedUpdates
    );
}

function herikaActionCatalogBuildScriptProxyReturnArguments($context)
{
    $arguments = is_array($context['parameters'] ?? null) ? $context['parameters'] : [];
    $parameterTarget = trim(strval($context['parameter_target'] ?? ''));
    $parameterRaw = trim(strval($context['parameter_raw'] ?? ''));

    if (!array_key_exists('target', $arguments) && $parameterTarget !== '') {
        $arguments['target'] = $parameterTarget;
    }
    if (!array_key_exists('location', $arguments) && array_key_exists('target', $arguments)) {
        $arguments['location'] = $arguments['target'];
    }
    if (count($arguments) === 0 && $parameterRaw !== '') {
        $arguments['target'] = $parameterRaw;
        $arguments['location'] = $parameterRaw;
    }

    return $arguments;
}

function herikaActionCatalogBuildScriptProxyInfoActionMessage($codeName, $context, $row)
{
    $codeName = trim(strval($codeName));
    if ($codeName === '') {
        return '';
    }

    $arguments = herikaActionCatalogBuildScriptProxyReturnArguments($context);
    $actorName = trim(strval($context['actor_name'] ?? ''));
    $hadHerikaName = array_key_exists('HERIKA_NAME', $GLOBALS);
    $previousHerikaName = $GLOBALS['HERIKA_NAME'] ?? null;

    if ($actorName !== '') {
        $GLOBALS['HERIKA_NAME'] = $actorName;
    }

    if (function_exists('herikaBuildFuncretResultInfoActionMessage')) {
        $message = herikaBuildFuncretResultInfoActionMessage($codeName, 'target', $arguments, '');
    } else {
        $template = is_array($row) ? trim(strval($row['return_message'] ?? '')) : '';
        $message = strtr($template, [
            '#TARGET#' => trim(strval($arguments['target'] ?? '')),
            '#ITEM#' => trim(strval($arguments['item'] ?? ($arguments['location'] ?? ''))),
            '#AMOUNT#' => trim(strval($arguments['amount'] ?? '')),
            '#LOCATION#' => trim(strval($arguments['location'] ?? ($arguments['item'] ?? ''))),
            '#HERIKA_NAME#' => strval($GLOBALS['HERIKA_NAME'] ?? 'NPC'),
            '#PLAYER_NAME#' => strval($GLOBALS['PLAYER_NAME'] ?? 'Player'),
        ]);
    }

    if ($hadHerikaName) {
        $GLOBALS['HERIKA_NAME'] = $previousHerikaName;
    } else {
        unset($GLOBALS['HERIKA_NAME']);
    }

    return trim(strval($message));
}

function herikaActionCatalogShouldLogScriptProxyInfoAction($codeName, $row)
{
    $codeName = trim(strval($codeName));
    if ($codeName === '' || !is_array($row) || trim(strval($row['return_message'] ?? '')) === '') {
        return false;
    }

    if (function_exists('isNarratorPrivateActionName') && isNarratorPrivateActionName($codeName)) {
        return false;
    }

    $metadata = $row['metadata'] ?? [];
    if (!is_array($metadata)) {
        $metadata = herikaActionCatalogDecodeJson($metadata, []);
    }

    return empty($metadata['suppress_placeholder_infoaction']);
}

function herikaActionCatalogLogScriptProxyInfoAction($codeName, $context, $row)
{
    if (!function_exists('logEvent') || !herikaActionCatalogShouldLogScriptProxyInfoAction($codeName, $row)) {
        return false;
    }

    $message = herikaActionCatalogBuildScriptProxyInfoActionMessage($codeName, $context, $row);
    if ($message === '') {
        return false;
    }

    $gameRequestCopy = $GLOBALS['gameRequest'] ?? [];
    if (!is_array($gameRequestCopy)) {
        return false;
    }

    $gameRequestCopy[0] = 'infoaction';
    $gameRequestCopy[3] = $message;
    logEvent($gameRequestCopy);

    return true;
}

function herikaActionCatalogRunScriptProxyProgram($program, $context)
{
    if (!is_array($program) || count($program) === 0) {
        return false;
    }

    $executed = false;

    if (isset($program['switch_on']) && is_array($program['cases'] ?? null)) {
        $switchValue = strval(herikaActionCatalogResolveContextPath($context, $program['switch_on']) ?? '');
        $selectedProgram = $program['cases'][$switchValue] ?? ($program['cases']['__default'] ?? null);
        if (is_array($selectedProgram)) {
            $executed = herikaActionCatalogRunScriptProxyProgram($selectedProgram, $context) || $executed;
        }
    }

    $executed = herikaActionCatalogExecuteScriptProxyCommands($program['commands'] ?? [], $context) || $executed;
    $executed = herikaActionCatalogExecuteScriptProxyDbInserts($program['db_inserts'] ?? [], $context) || $executed;
    $executed = herikaActionCatalogExecuteScriptProxyNpcMetadataUpdates($program['npc_metadata_updates'] ?? [], $context) || $executed;

    return $executed;
}

function herikaActionCatalogExecuteScriptProxyAction($action)
{
    if (!herikaActionCatalogDbReady()) {
        return false;
    }

    $actionParts = explode('|', strval($action), 3);
    if (count($actionParts) < 3) {
        return false;
    }

    $actionParts2 = explode('@', $actionParts[2], 2);
    $codeName = trim(strval($actionParts2[0] ?? ''));
    if ($codeName === '') {
        return false;
    }

    $row = herikaGetActionCatalogRow($codeName);
    if (!is_array($row) || empty($row['script_proxy_program'])) {
        return false;
    }

    $context = herikaActionCatalogBuildScriptProxyContext($actionParts, $actionParts2);
    $executed = herikaActionCatalogRunScriptProxyProgram($row['script_proxy_program'], $context);
    if ($executed) {
        herikaActionCatalogLogScriptProxyInfoAction($codeName, $context, $row);
        error_log("[ACTION CATALOG {$codeName}] Executed server-side via ScriptProxy");
    }

    return $executed;
}
