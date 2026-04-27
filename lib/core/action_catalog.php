<?php

function herikaGetRetiredActionCodes()
{
    return [
        'AttackHunt',
        'Inspect',
        'InspectSurroundings',
        'LookAt',
        'Surrender',
        'ReadQuestJournal',
        'GetDateTime',
        'SearchDiary',
        'SetCurrentTask',
        'ReadDiaryPage',
        'SearchMemory',
    ];
}

function herikaGetNpcDefaultActionCodes()
{
    return [
        'MoveTo',
        'OpenInventory',
        'OpenInventory2',
        'Attack',
        'TravelTo',
        'Follow',
        'CheckInventory',
        'Relax',
        'TakeASeat',
        'IncreaseWalkSpeed',
        'DecreaseWalkSpeed',
        'WaitHere',
        'ComeCloser',
        'TakeGoldFromPlayer',
        'RentRoom',
        'HireCarriage',
        'HireFerry',
        'AddBounty',
        'PayBounty',
        'ArrestPlayer',
        'ForgiveCrime',
        'FollowPlayer',
        'Brawl',
        'GiveGoldTo',
        'GiveItemTo',
        'PickupItem',
        'GoToSleep',
        'UseSoulGaze',
        'CastSpell',
        'MakeFollower',
        'Drink',
        'Toast',
        'StartRitualCeremony',
        'EndRitualCeremony',
        'Training',
        'EndConversation',
    ];
}

function herikaGetFollowerDefaultActionCodes()
{
    return [
        'OpenInventory',
        'OpenInventory2',
        'Attack',
        'TravelTo',
        'Follow',
        'CheckInventory',
        'SheatheWeapon',
        'Relax',
        'TakeASeat',
        'IncreaseWalkSpeed',
        'DecreaseWalkSpeed',
        'WaitHere',
        'ComeCloser',
        'TakeGoldFromPlayer',
        'RentRoom',
        'HireCarriage',
        'HireFerry',
        'AddBounty',
        'PayBounty',
        'ArrestPlayer',
        'ForgiveCrime',
        'Brawl',
        'GiveGoldTo',
        'GiveItemTo',
        'PickupItem',
        'GoToSleep',
        'UseSoulGaze',
        'CastSpell',
        'Drink',
        'Toast',
        'Training',
        'StartRitualCeremony',
        'EndRitualCeremony',
    ];
}

function herikaActionCatalogSqlBool($value)
{
    return $value ? 'TRUE' : 'FALSE';
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

function herikaActionCatalogGetEditorFieldDefaultValue($field)
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
        } else {
            $resolvedConfig[$fieldKey] = herikaActionCatalogGetEditorFieldDefaultValue($field);
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

    return $parameters;
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
                'key' => 'rent_room_cost',
                'label' => 'Room Cost',
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'format' => 'gold',
                'help' => 'How much gold the player pays to rent a room from this NPC.',
            ],
        ],
        'HireCarriage' => [
            [
                'key' => 'hire_carriage_cost',
                'label' => 'Carriage Fare',
                'type' => 'integer',
                'default' => 20,
                'minimum' => 1,
                'format' => 'gold',
                'help' => 'How much gold the player pays for carriage travel.',
            ],
        ],
        'HireFerry' => [
            [
                'key' => 'hire_ferry_cost',
                'label' => 'Ferry Fare',
                'type' => 'integer',
                'default' => 50,
                'minimum' => 1,
                'format' => 'gold',
                'help' => 'How much gold the player pays for ferry travel.',
            ],
        ],
    ];

    return $fields[$codeName] ?? [];
}

function herikaActionCatalogGetBuiltinParameterTemplate($codeName)
{
    $templates = [
        'RentRoom' => [
            'amount' => '{{config.rent_room_cost}}',
        ],
        'HireCarriage' => [
            'target' => '{{parameter_target}}',
            'amount' => '{{config.hire_carriage_cost}}',
        ],
        'HireFerry' => [
            'target' => '{{parameter_target}}',
            'amount' => '{{config.hire_ferry_cost}}',
        ],
    ];

    return $templates[$codeName] ?? null;
}

function herikaActionCatalogBuildBaseMetadata($codeName, $scriptProxyProgram = null)
{
    $dispatch = 'plugin_command';
    if ($scriptProxyProgram !== null) {
        $dispatch = 'script_proxy';
    } elseif ($codeName === 'Training') {
        $dispatch = 'rolecommand';
    }

    $metadata = [
        'dispatch' => $dispatch,
        'builtin' => true,
        'status' => 'active',
        'source' => 'functions.php',
    ];

    $editorFields = herikaActionCatalogGetBuiltinEditorFields($codeName);
    if (count($editorFields) > 0) {
        $metadata['editor_fields'] = $editorFields;
    }

    $parameterTemplate = herikaActionCatalogGetBuiltinParameterTemplate($codeName);
    if ($parameterTemplate !== null) {
        $metadata['parameter_template'] = $parameterTemplate;
    }

    return $metadata;
}

function herikaActionCatalogIsGameFunction($metadata)
{
    $dispatch = strtolower(trim(strval($metadata['dispatch'] ?? 'plugin_command')));
    return !in_array($dispatch, ['server_action', 'server_query'], true);
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

function herikaBuildActionCatalogSeedRows($actionNames, $descriptions, $returnMessages, $currentEnabledCodes = [], $defaultEnabledCodes = [], $functionDefinitionsByCode = [])
{
    $npcDefaults = herikaGetNpcDefaultActionCodes();
    $followerDefaults = herikaGetFollowerDefaultActionCodes();
    $activationDefaults = count($defaultEnabledCodes) > 0 ? $defaultEnabledCodes : array_unique(array_merge($npcDefaults, $followerDefaults));
    $allCodeNames = array_unique(array_merge(
        array_keys(is_array($actionNames) ? $actionNames : []),
        array_keys(is_array($descriptions) ? $descriptions : []),
        array_keys(is_array($returnMessages) ? $returnMessages : []),
        is_array($currentEnabledCodes) ? $currentEnabledCodes : [],
        $activationDefaults,
        $npcDefaults,
        $followerDefaults,
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

        $availableToNpc = in_array($codeName, $npcDefaults, true);
        $availableToFollowers = in_array($codeName, $followerDefaults, true);
        $isActivated = in_array($codeName, $activationDefaults, true) || in_array($codeName, $currentEnabledCodes, true);
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
            'is_activated' => $isActivated,
            'parameters_json' => $parameters,
            'metadata' => $metadata,
            'game_function' => herikaActionCatalogIsGameFunction($metadata),
            'script_proxy_program' => $scriptProxyProgram,
        ];
    }

    return $rows;
}

function herikaDeleteRetiredActionCatalogRows()
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
    $GLOBALS["db"]->execQuery("DELETE FROM public.core_action_custom WHERE code_name IN ({$inList})");
    $GLOBALS["db"]->execQuery("DELETE FROM public.core_action WHERE code_name IN ({$inList})");
}

function herikaSyncActionCatalogBaseRows($rowsByCode)
{
    if (!herikaActionCatalogDbReady()) {
        return;
    }

    herikaDeleteRetiredActionCatalogRows();
    herikaDeleteUnexpectedBaseActionCatalogRows($rowsByCode);

    $existingCustomMetadataByCode = [];
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
                is_activated,
                parameters_json,
                metadata,
                game_function,
                script_proxy_program
            ) VALUES (
                " . herikaActionCatalogSqlText($row['code_name']) . ",
                " . herikaActionCatalogSqlText($row['action_name'] ?? '') . ",
                " . herikaActionCatalogSqlText($row['description'] ?? '') . ",
                " . herikaActionCatalogSqlText($row['return_message'] ?? '') . ",
                " . herikaActionCatalogSqlBool(!empty($row['available_to_npc'])) . ",
                " . herikaActionCatalogSqlBool(!empty($row['available_to_followers'])) . ",
                " . herikaActionCatalogSqlBool(!empty($row['is_activated'])) . ",
                " . herikaActionCatalogSqlJson($row['parameters_json'] ?? []) . ",
                " . herikaActionCatalogSqlJson($row['metadata'] ?? []) . ",
                " . herikaActionCatalogSqlBool(!empty($row['game_function'])) . ",
                " . herikaActionCatalogSqlJson($row['script_proxy_program'] ?? null, true) . "
            )
            ON CONFLICT (code_name) DO UPDATE SET
                action_name = EXCLUDED.action_name,
                description = EXCLUDED.description,
                return_message = EXCLUDED.return_message,
                available_to_npc = EXCLUDED.available_to_npc,
                available_to_followers = EXCLUDED.available_to_followers,
                is_activated = EXCLUDED.is_activated,
                parameters_json = EXCLUDED.parameters_json,
                metadata = EXCLUDED.metadata,
                game_function = EXCLUDED.game_function,
                script_proxy_program = EXCLUDED.script_proxy_program,
                updated_at = NOW()
        ");

        $GLOBALS["db"]->execQuery("
            UPDATE public.core_action_custom
            SET
                action_name = " . herikaActionCatalogSqlText($row['action_name'] ?? '') . ",
                description = " . herikaActionCatalogSqlText($row['description'] ?? '') . ",
                return_message = " . herikaActionCatalogSqlText($row['return_message'] ?? '') . ",
                available_to_npc = " . herikaActionCatalogSqlBool(!empty($row['available_to_npc'])) . ",
                available_to_followers = " . herikaActionCatalogSqlBool(!empty($row['available_to_followers'])) . ",
                parameters_json = " . herikaActionCatalogSqlJson($row['parameters_json'] ?? []) . ",
                metadata = " . herikaActionCatalogSqlJson($preservedCustomMetadata) . ",
                game_function = " . herikaActionCatalogSqlBool(!empty($row['game_function'])) . ",
                script_proxy_program = " . herikaActionCatalogSqlJson($row['script_proxy_program'] ?? null, true) . ",
                updated_at = NOW()
            WHERE LOWER(code_name) = LOWER(" . herikaActionCatalogSqlText($row['code_name']) . ")
        ");
    }

    herikaActionCatalogResetCache();
}

function herikaDeleteUnexpectedBaseActionCatalogRows($rowsByCode)
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

    $GLOBALS["db"]->execQuery("
        DELETE FROM public.core_action_custom
        WHERE {$builtinFilter}
          AND LOWER(code_name) NOT IN ({$seedCodeList})
    ");
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
                is_activated,
                parameters_json,
                metadata,
                game_function,
                script_proxy_program
            ) VALUES (
                " . herikaActionCatalogSqlText($row['code_name']) . ",
                " . herikaActionCatalogSqlText($row['action_name'] ?? '') . ",
                " . herikaActionCatalogSqlText($row['description'] ?? '') . ",
                " . herikaActionCatalogSqlText($row['return_message'] ?? '') . ",
                " . herikaActionCatalogSqlBool(!empty($row['available_to_npc'])) . ",
                " . herikaActionCatalogSqlBool(!empty($row['available_to_followers'])) . ",
                " . herikaActionCatalogSqlBool(isset($selectedMap[$row['code_name']])) . ",
                " . herikaActionCatalogSqlJson($row['parameters_json'] ?? []) . ",
                " . herikaActionCatalogSqlJson($row['metadata'] ?? []) . ",
                " . herikaActionCatalogSqlBool(!empty($row['game_function'])) . ",
                " . herikaActionCatalogSqlJson($row['script_proxy_program'] ?? null, true) . "
            )
            ON CONFLICT (code_name) DO UPDATE SET
                action_name = EXCLUDED.action_name,
                description = EXCLUDED.description,
                return_message = EXCLUDED.return_message,
                available_to_npc = EXCLUDED.available_to_npc,
                available_to_followers = EXCLUDED.available_to_followers,
                is_activated = EXCLUDED.is_activated,
                parameters_json = EXCLUDED.parameters_json,
                metadata = EXCLUDED.metadata,
                game_function = EXCLUDED.game_function,
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
            is_activated,
            parameters_json,
            metadata,
            game_function,
            script_proxy_program
        FROM public.combined_core_action
    ");

    foreach ($rows as $row) {
        $codeName = trim(strval($row['code_name'] ?? ''));
        if ($codeName === '') {
            continue;
        }

        $GLOBALS["HERIKA_ACTION_CATALOG_ROWS_BY_CODE"][$codeName] = [
            'code_name' => $codeName,
            'action_name' => strval($row['action_name'] ?? $codeName),
            'description' => strval($row['description'] ?? ''),
            'return_message' => strval($row['return_message'] ?? ''),
            'available_to_npc' => herikaActionCatalogToBool($row['available_to_npc'] ?? false),
            'available_to_followers' => herikaActionCatalogToBool($row['available_to_followers'] ?? false),
            'is_activated' => herikaActionCatalogToBool($row['is_activated'] ?? false),
            'parameters_json' => herikaActionCatalogNormalizeParameterSchema(
                herikaActionCatalogDecodeJson($row['parameters_json'] ?? [], [])
            ),
            'metadata' => herikaActionCatalogDecodeJson($row['metadata'] ?? [], []),
            'game_function' => herikaActionCatalogToBool($row['game_function'] ?? false),
            'script_proxy_program' => herikaActionCatalogDecodeJson($row['script_proxy_program'] ?? null, []),
        ];
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

function herikaLoadEnabledActionCodesForMode($isNpc)
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

        if ($isNpc && !empty($row['available_to_npc'])) {
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

function herikaActionCatalogBuildFunctionEntryFromRow($row)
{
    if (!is_array($row) || empty($row['code_name']) || trim(strval($row['action_name'] ?? '')) === '') {
        return null;
    }

    return [
        'name' => strval($row['action_name']),
        'description' => strval($row['description'] ?? ''),
        'parameters' => herikaActionCatalogNormalizeParameterSchema($row['parameters_json'] ?? null),
    ];
}

function herikaActionCatalogRowIsAvailableInCurrentMode($row)
{
    $isNpcMode = !empty($GLOBALS["IS_NPC"]);
    if ($isNpcMode) {
        return !empty($row['available_to_npc']);
    }

    return !empty($row['available_to_followers']);
}

function herikaActionCatalogShouldPreferRowForActionName($candidateRow, $currentRow)
{
    $candidateAvailable = herikaActionCatalogRowIsAvailableInCurrentMode($candidateRow);
    $currentAvailable = herikaActionCatalogRowIsAvailableInCurrentMode($currentRow);
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
    foreach ($GLOBALS["FUNCTIONS"] ?? [] as $functionEntry) {
        if (!is_array($functionEntry) || empty($functionEntry['name'])) {
            continue;
        }

        $codeName = function_exists('getFunctionCodeName') ? getFunctionCodeName($functionEntry['name']) : false;
        if ($codeName === false || in_array($codeName, herikaGetRetiredActionCodes(), true)) {
            continue;
        }

        $runtimeFunctionMap[$codeName] = $functionEntry;
    }

    foreach ($rowsByCode as $codeName => $row) {
        $GLOBALS["F_NAMES"][$codeName] = $row['action_name'];
        $GLOBALS["F_TRANSLATIONS"][$codeName] = $row['description'];
        $GLOBALS["F_RETURNMESSAGES"][$codeName] = $row['return_message'];

        $catalogFunctionEntry = herikaActionCatalogBuildFunctionEntryFromRow($row);
        if ($catalogFunctionEntry === null) {
            continue;
        }

        if (isset($runtimeFunctionMap[$codeName])) {
            $runtimeFunctionMap[$codeName]['name'] = $catalogFunctionEntry['name'];
            $runtimeFunctionMap[$codeName]['description'] = $catalogFunctionEntry['description'];
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
            parameters_json,
            metadata,
            game_function,
            script_proxy_program
        FROM public.combined_core_action
        WHERE code_name = {$literalCode}
        LIMIT 1
    ");

    if (!$row) {
        return false;
    }

    $result = $GLOBALS["db"]->execQuery("
        INSERT INTO public.core_action_custom (
            code_name,
            action_name,
            description,
            return_message,
            available_to_npc,
            available_to_followers,
            is_activated,
            parameters_json,
            metadata,
            game_function,
            script_proxy_program
        ) VALUES (
            " . herikaActionCatalogSqlText($row['code_name'] ?? $codeName) . ",
            " . herikaActionCatalogSqlText($row['action_name'] ?? '') . ",
            " . herikaActionCatalogSqlText($row['description'] ?? '') . ",
            " . herikaActionCatalogSqlText($row['return_message'] ?? '') . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['available_to_npc'] ?? false)) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['available_to_followers'] ?? false)) . ",
            " . herikaActionCatalogSqlBool((bool) $enabled) . ",
            " . herikaActionCatalogSqlJson($row['parameters_json'] ?? []) . ",
            " . herikaActionCatalogSqlJson($row['metadata'] ?? []) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['game_function'] ?? false)) . ",
            " . herikaActionCatalogSqlJson($row['script_proxy_program'] ?? null, true) . "
        )
        ON CONFLICT (code_name) DO UPDATE SET
            action_name = EXCLUDED.action_name,
            description = EXCLUDED.description,
            return_message = EXCLUDED.return_message,
            available_to_npc = EXCLUDED.available_to_npc,
            available_to_followers = EXCLUDED.available_to_followers,
            is_activated = EXCLUDED.is_activated,
            parameters_json = EXCLUDED.parameters_json,
            metadata = EXCLUDED.metadata,
            game_function = EXCLUDED.game_function,
            script_proxy_program = EXCLUDED.script_proxy_program,
            updated_at = NOW()
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
            is_activated,
            parameters_json,
            metadata,
            game_function,
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
            is_activated,
            parameters_json,
            metadata,
            game_function,
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

    $result = $GLOBALS["db"]->execQuery("
        INSERT INTO public.core_action_custom (
            code_name,
            action_name,
            description,
            return_message,
            available_to_npc,
            available_to_followers,
            is_activated,
            parameters_json,
            metadata,
            game_function,
            script_proxy_program
        ) VALUES (
            " . herikaActionCatalogSqlText($row['code_name'] ?? $codeName) . ",
            " . herikaActionCatalogSqlText($row['action_name'] ?? '') . ",
            " . herikaActionCatalogSqlText($row['description'] ?? '') . ",
            " . herikaActionCatalogSqlText($row['return_message'] ?? '') . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['available_to_npc'] ?? false)) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['available_to_followers'] ?? false)) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['is_activated'] ?? false)) . ",
            " . herikaActionCatalogSqlJson($row['parameters_json'] ?? []) . ",
            " . herikaActionCatalogSqlJson($metadata) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['game_function'] ?? false)) . ",
            " . herikaActionCatalogSqlJson($row['script_proxy_program'] ?? null, true) . "
        )
        ON CONFLICT (code_name) DO UPDATE SET
            action_name = EXCLUDED.action_name,
            description = EXCLUDED.description,
            return_message = EXCLUDED.return_message,
            available_to_npc = EXCLUDED.available_to_npc,
            available_to_followers = EXCLUDED.available_to_followers,
            is_activated = EXCLUDED.is_activated,
            parameters_json = EXCLUDED.parameters_json,
            metadata = EXCLUDED.metadata,
            game_function = EXCLUDED.game_function,
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
    $actionName = trim(strval($row['action_name'] ?? ''));
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

    $result = $GLOBALS["db"]->execQuery("
        INSERT INTO public.core_action_custom (
            code_name,
            action_name,
            description,
            return_message,
            available_to_npc,
            available_to_followers,
            is_activated,
            parameters_json,
            metadata,
            game_function,
            script_proxy_program
        ) VALUES (
            " . herikaActionCatalogSqlText($codeName) . ",
            " . herikaActionCatalogSqlText($actionName) . ",
            " . herikaActionCatalogSqlText($row['description'] ?? '') . ",
            " . herikaActionCatalogSqlText($row['return_message'] ?? '') . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['available_to_npc'] ?? false)) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['available_to_followers'] ?? false)) . ",
            " . herikaActionCatalogSqlBool(herikaActionCatalogToBool($row['is_activated'] ?? true)) . ",
            " . herikaActionCatalogSqlJson($parameters) . ",
            " . herikaActionCatalogSqlJson($metadata) . ",
            " . herikaActionCatalogSqlBool($gameFunction) . ",
            " . herikaActionCatalogSqlJson($scriptProxyProgram, true) . "
        )
        ON CONFLICT (code_name) DO UPDATE SET
            action_name = EXCLUDED.action_name,
            description = EXCLUDED.description,
            return_message = EXCLUDED.return_message,
            available_to_npc = EXCLUDED.available_to_npc,
            available_to_followers = EXCLUDED.available_to_followers,
            is_activated = EXCLUDED.is_activated,
            parameters_json = EXCLUDED.parameters_json,
            metadata = EXCLUDED.metadata,
            game_function = EXCLUDED.game_function,
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

        $GLOBALS["db"]->insert($dbInsert['table'], $data);
        $executed = true;
    }

    return $executed;
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
        error_log("[ACTION CATALOG {$codeName}] Executed server-side via ScriptProxy");
    }

    return $executed;
}
