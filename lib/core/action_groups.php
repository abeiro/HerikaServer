<?php

// Defines the compact model-facing tools while preserving legacy action codes for execution.
function herikaActionGroupsGetSpecs()
{
    static $specs = null;
    if (is_array($specs)) {
        return $specs;
    }

    $specs = [
        'GroupedHandleCrime' => [
            'action_name' => 'Handle_Crime',
            'description' => 'Choose one guard response to the player\'s crime. Put add_bounty, arrest, forgive, or pay_bounty in target. For add_bounty, put the crime type in item and a Custom gold value in amount.',
            'selector' => 'target',
            'variants' => [
                'add_bounty' => 'AddBounty',
                'arrest' => 'ArrestPlayer',
                'forgive' => 'ForgiveCrime',
                'pay_bounty' => 'PayBounty',
            ],
            'parameters' => [
                'type' => 'object',
                'required' => ['target'],
                'properties' => [
                    'target' => [
                        'type' => 'string',
                        'description' => 'Guard response to perform.',
                    ],
                    'item' => [
                        'type' => 'string',
                        'enum' => ['Assault', 'Murder', 'Theft', 'Pickpocketing', 'Trespassing', 'Jailbreak', 'Custom'],
                        'description' => 'Required for add_bounty. Select the witnessed or reported crime.',
                    ],
                    'amount' => [
                        'type' => 'integer',
                        'minimum' => 1,
                        'description' => 'Required only for a Custom bounty; the gold amount to add.',
                    ],
                ],
            ],
        ],
        'GroupedStartCombat' => [
            'action_name' => 'Start_Combat',
            'description' => 'Start combat with the actor in target. Put lethal or nonlethal in item; nonlethal uses protected brawl behavior.',
            'selector' => 'item',
            'variants' => [
                'lethal' => 'Attack',
                'nonlethal' => 'Brawl',
            ],
            'parameters' => [
                'type' => 'object',
                'required' => ['target', 'item'],
                'properties' => [
                    'target' => [
                        'type' => 'string',
                        'description' => 'Target NPC, actor, or being.',
                    ],
                    'item' => [
                        'type' => 'string',
                        'description' => 'Whether combat is lethal or non-lethal.',
                    ],
                ],
            ],
        ],
        'GroupedFollow' => [
            'action_name' => 'Follow',
            'description' => 'Put actor, player, or approach_player in item. For actor mode, put the nearby actor to follow in target; otherwise leave target blank.',
            'selector' => 'item',
            'variants' => [
                'actor' => 'Follow',
                'player' => 'FollowPlayer',
                'approach_player' => 'ComeCloser',
            ],
            'parameters' => [
                'type' => 'object',
                'required' => ['item'],
                'properties' => [
                    'target' => [
                        'type' => 'string',
                        'description' => 'Required only for actor mode; the nearby actor to follow.',
                    ],
                    'item' => [
                        'type' => 'string',
                        'description' => 'Following behavior to use.',
                    ],
                ],
            ],
        ],
        'GroupedSetPace' => [
            'action_name' => 'Set_Pace',
            'description' => 'Adjust the NPC\'s movement pace. Put faster or slower in target and leave item blank.',
            'selector' => 'target',
            'variants' => [
                'faster' => 'IncreaseWalkSpeed',
                'slower' => 'DecreaseWalkSpeed',
            ],
            'parameters' => [
                'type' => 'object',
                'required' => ['target'],
                'properties' => [
                    'target' => [
                        'type' => 'string',
                        'description' => 'Whether to move faster or slower.',
                    ],
                    'item' => [
                        'type' => 'string',
                        'description' => 'Keep it blank.',
                    ],
                ],
            ],
        ],
        'GroupedGive' => [
            'action_name' => 'Give',
            'description' => 'Give an exact inventory item or Gold to another actor or the player.',
            'variants' => [
                'item' => 'GiveItemTo',
                'gold' => 'GiveGoldTo',
            ],
            'parameters' => [
                'type' => 'object',
                'required' => ['target', 'item'],
                'properties' => [
                    'target' => [
                        'type' => 'string',
                        'description' => 'Actor who will receive the item.',
                    ],
                    'item' => [
                        'type' => 'string',
                        'description' => 'Use Gold for currency, otherwise use the exact item name from inventory.',
                    ],
                    'amount' => [
                        'type' => 'integer',
                        'minimum' => 1,
                        'description' => 'Number of items or gold pieces to give. Defaults to 1.',
                    ],
                ],
            ],
        ],
        'GroupedExchange' => [
            'action_name' => 'Exchange',
            'description' => 'Open normal trading or let the player give a gift to the NPC. Put trade or accept_gift in target and leave item blank.',
            'selector' => 'target',
            'variants' => [
                'trade' => 'OpenInventory',
                'accept_gift' => 'OpenInventory2',
            ],
            'parameters' => [
                'type' => 'object',
                'required' => ['target'],
                'properties' => [
                    'target' => [
                        'type' => 'string',
                        'description' => 'Use trade for normal exchange or accept_gift when the player is giving items.',
                    ],
                    'item' => [
                        'type' => 'string',
                        'description' => 'Keep it blank.',
                    ],
                ],
            ],
        ],
        'GroupedGesture' => [
            'action_name' => 'Perform_Gesture',
            'description' => 'Perform a visual drinking or toast gesture. Put drink or toast in target and leave item blank. This does not consume an inventory item.',
            'selector' => 'target',
            'variants' => [
                'drink' => 'Drink',
                'toast' => 'Toast',
            ],
            'parameters' => [
                'type' => 'object',
                'required' => ['target'],
                'properties' => [
                    'target' => [
                        'type' => 'string',
                        'description' => 'Visual gesture to perform.',
                    ],
                    'item' => [
                        'type' => 'string',
                        'description' => 'Keep it blank.',
                    ],
                ],
            ],
        ],
    ];

    return $specs;
}

function herikaActionGroupsGetCustomizedCodeSet()
{
    if (isset($GLOBALS['HERIKA_ACTION_GROUP_CUSTOM_CODE_SET']) && is_array($GLOBALS['HERIKA_ACTION_GROUP_CUSTOM_CODE_SET'])) {
        return $GLOBALS['HERIKA_ACTION_GROUP_CUSTOM_CODE_SET'];
    }

    $customCodeSet = [];
    if (function_exists('herikaActionCatalogDbReady') && herikaActionCatalogDbReady()) {
        $rows = $GLOBALS['db']->fetchAll("
            SELECT c.code_name
            FROM public.core_action_custom c
            INNER JOIN public.core_action b ON LOWER(b.code_name) = LOWER(c.code_name)
            WHERE c.action_name IS DISTINCT FROM b.action_name
               OR c.description IS DISTINCT FROM b.description
               OR c.parameters_json IS DISTINCT FROM b.parameters_json
               OR c.game_function IS DISTINCT FROM b.game_function
               OR COALESCE(c.metadata->>'dispatch', '') IS DISTINCT FROM COALESCE(b.metadata->>'dispatch', '')
               OR c.script_proxy_program IS DISTINCT FROM b.script_proxy_program
        ");
        foreach ($rows as $row) {
            $codeName = trim(strval($row['code_name'] ?? ''));
            if ($codeName !== '') {
                $customCodeSet[$codeName] = true;
            }
        }
    }

    $GLOBALS['HERIKA_ACTION_GROUP_CUSTOM_CODE_SET'] = $customCodeSet;
    return $customCodeSet;
}

function herikaActionGroupsBuildFunctionEntry($spec, $activeVariants)
{
    $parameters = $spec['parameters'];
    $selector = trim(strval($spec['selector'] ?? ''));
    if ($selector !== '' && isset($parameters['properties'][$selector])) {
        $parameters['properties'][$selector]['enum'] = array_keys($activeVariants);
    }

    return [
        'name' => $spec['action_name'],
        'description' => $spec['description'],
        'parameters' => $parameters,
    ];
}

// Replaces only simultaneously eligible, uncustomized vanilla actions with compact runtime definitions.
function herikaActionGroupsApplyToRuntime()
{
    if (!isset($GLOBALS['FUNCTIONS'], $GLOBALS['ENABLED_FUNCTIONS']) || !is_array($GLOBALS['FUNCTIONS']) || !is_array($GLOBALS['ENABLED_FUNCTIONS'])) {
        return 0;
    }

    $enabledSet = array_fill_keys(array_map('strval', $GLOBALS['ENABLED_FUNCTIONS']), true);
    $customCodeSet = herikaActionGroupsGetCustomizedCodeSet();
    $runtimeEntries = [];
    $runtimeCodeSet = [];
    foreach ($GLOBALS['FUNCTIONS'] as $index => $functionEntry) {
        $codeName = is_array($functionEntry) && !empty($functionEntry['name']) && function_exists('getFunctionCodeName')
            ? getFunctionCodeName($functionEntry['name'])
            : false;
        $runtimeEntries[$index] = [
            'code_name' => is_string($codeName) ? $codeName : '',
            'entry' => $functionEntry,
        ];
        if (is_string($codeName) && $codeName !== '') {
            $runtimeCodeSet[$codeName] = true;
        }
    }

    $codesToReplace = [];
    $groupEntries = [];
    $groupNameToCode = [];
    $activeSpecs = [];
    foreach (herikaActionGroupsGetSpecs() as $groupCode => $spec) {
        $activeVariants = [];
        foreach ($spec['variants'] as $variantName => $legacyCode) {
            if (isset($enabledSet[$legacyCode], $runtimeCodeSet[$legacyCode]) && !isset($customCodeSet[$legacyCode])) {
                $activeVariants[$variantName] = $legacyCode;
            }
        }

        $activeMemberCodes = array_values(array_unique(array_values($activeVariants)));
        if (count($activeMemberCodes) < 2) {
            continue;
        }

        $nameCollision = false;
        foreach ($runtimeEntries as $runtimeEntry) {
            if (in_array($runtimeEntry['code_name'], $activeMemberCodes, true)) {
                continue;
            }
            if (strcasecmp(trim(strval($runtimeEntry['entry']['name'] ?? '')), $spec['action_name']) === 0) {
                $nameCollision = true;
                break;
            }
        }
        if ($nameCollision) {
            continue;
        }

        foreach ($activeMemberCodes as $legacyCode) {
            $codesToReplace[$legacyCode] = true;
        }
        $groupEntry = herikaActionGroupsBuildFunctionEntry($spec, $activeVariants);
        $groupEntries[$groupCode] = $groupEntry;
        $activeSpecs[$groupCode] = array_merge($spec, ['variants' => $activeVariants]);
        $groupNameToCode[$spec['action_name']] = $groupCode;
        if (function_exists('herikaNormalizeActionCatalogDisplayActionName')) {
            $groupNameToCode[herikaNormalizeActionCatalogDisplayActionName($spec['action_name'])] = $groupCode;
        }
    }

    if (count($groupEntries) === 0) {
        return 0;
    }

    $filteredFunctions = [];
    foreach ($runtimeEntries as $runtimeEntry) {
        if (!isset($codesToReplace[$runtimeEntry['code_name']])) {
            $filteredFunctions[] = $runtimeEntry['entry'];
        }
    }
    foreach ($groupEntries as $groupEntry) {
        $filteredFunctions[] = $groupEntry;
    }
    $GLOBALS['FUNCTIONS'] = $filteredFunctions;

    $GLOBALS['ENABLED_FUNCTIONS'] = array_values(array_filter(
        $GLOBALS['ENABLED_FUNCTIONS'],
        static fn($codeName) => !isset($codesToReplace[strval($codeName)])
    ));
    foreach ($groupEntries as $groupCode => $groupEntry) {
        $GLOBALS['ENABLED_FUNCTIONS'][] = $groupCode;
        $GLOBALS['BASE_FUNCTIONS'][$groupCode] = $groupEntry;
        $GLOBALS['F_NAMES'][$groupCode] = $groupEntry['name'];
        $GLOBALS['F_TRANSLATIONS'][$groupCode] = $groupEntry['description'];
        $GLOBALS['F_RETURNMESSAGES'][$groupCode] = '';
    }
    $GLOBALS['ENABLED_FUNCTIONS'] = array_values(array_unique($GLOBALS['ENABLED_FUNCTIONS']));
    $GLOBALS['HERIKA_GROUPED_ACTION_NAME_TO_CODE'] = $groupNameToCode;
    $GLOBALS['HERIKA_GROUPED_ACTION_SPECS'] = $activeSpecs;

    return count($groupEntries);
}

function herikaActionGroupsNormalizeChoice($value)
{
    $value = strtolower(trim(strval($value)));
    return str_replace(['-', ' '], '_', $value);
}

function herikaActionGroupsDecodeParameters($parameter)
{
    if (is_array($parameter)) {
        return $parameter;
    }

    $decoded = json_decode(strval($parameter), true);
    return is_array($decoded) ? $decoded : [];
}

// Converts a compact model-facing action back into one eligible legacy action and payload.
function herikaActionGroupsResolveExecution($groupCode, $parameter)
{
    $spec = $GLOBALS['HERIKA_GROUPED_ACTION_SPECS'][$groupCode] ?? null;
    if (!is_array($spec)) {
        return null;
    }

    $payload = herikaActionGroupsDecodeParameters($parameter);
    $variants = $spec['variants'];
    $missing = [];
    $selectedCode = '';
    $legacyParameter = '';

    if ($groupCode === 'GroupedHandleCrime') {
        $outcome = herikaActionGroupsNormalizeChoice($payload['outcome'] ?? ($payload['target'] ?? ''));
        $selectedCode = strval($variants[$outcome] ?? '');
        if ($outcome === '') {
            $missing[] = 'outcome';
        } elseif ($selectedCode === '') {
            $missing[] = 'available outcome';
        } elseif ($outcome === 'add_bounty') {
            $crimeType = trim(strval($payload['crime_type'] ?? ($payload['item'] ?? '')));
            if ($crimeType === '') {
                $missing[] = 'crime_type';
            } else {
                $legacyParameter = $crimeType;
                if (strcasecmp($crimeType, 'Custom') === 0) {
                    $amount = intval($payload['amount'] ?? 0);
                    if ($amount <= 0) {
                        $missing[] = 'amount';
                    } else {
                        $legacyParameter .= '@' . $amount;
                    }
                }
            }
        }
    } elseif ($groupCode === 'GroupedStartCombat') {
        $mode = herikaActionGroupsNormalizeChoice($payload['mode'] ?? ($payload['item'] ?? ''));
        if ($mode === 'non_lethal') {
            $mode = 'nonlethal';
        }
        $selectedCode = strval($variants[$mode] ?? '');
        $legacyParameter = trim(strval($payload['target'] ?? ''));
        if ($mode === '' || $selectedCode === '') {
            $missing[] = 'mode';
        }
        if ($legacyParameter === '') {
            $missing[] = 'target';
        }
    } elseif ($groupCode === 'GroupedFollow') {
        $mode = herikaActionGroupsNormalizeChoice($payload['mode'] ?? ($payload['item'] ?? ''));
        $target = trim(strval($payload['target'] ?? ''));
        if ($mode === '') {
            $mode = $target !== '' ? 'actor' : 'player';
        }
        $selectedCode = strval($variants[$mode] ?? '');
        if ($selectedCode === '') {
            $missing[] = 'mode';
        } elseif ($mode === 'actor') {
            $legacyParameter = $target;
            if ($target === '') {
                $missing[] = 'target';
            }
        }
    } elseif ($groupCode === 'GroupedSetPace') {
        $pace = herikaActionGroupsNormalizeChoice($payload['pace'] ?? ($payload['target'] ?? ''));
        $selectedCode = strval($variants[$pace] ?? '');
        if ($selectedCode === '') {
            $missing[] = 'pace';
        }
    } elseif ($groupCode === 'GroupedGive') {
        $target = trim(strval($payload['target'] ?? ''));
        $item = trim(strval($payload['item'] ?? ''));
        $amount = max(1, intval($payload['amount'] ?? 1));
        $isGold = in_array(strtolower($item), ['gold', 'coins', 'septims'], true);
        $selectedCode = strval($variants[$isGold ? 'gold' : 'item'] ?? '');
        if ($target === '') {
            $missing[] = 'target';
        }
        if ($item === '') {
            $missing[] = 'item';
        }
        if ($selectedCode === '') {
            $missing[] = $isGold ? 'Gold transfer' : 'item transfer';
        } elseif ($isGold) {
            $legacyParameter = ['target' => $target, 'item' => strval($amount)];
        } else {
            $legacyParameter = ['target' => $target, 'item' => $item, 'amount' => $amount];
        }
    } elseif ($groupCode === 'GroupedExchange') {
        $mode = herikaActionGroupsNormalizeChoice($payload['mode'] ?? ($payload['target'] ?? ''));
        $selectedCode = strval($variants[$mode] ?? '');
        if ($selectedCode === '') {
            $missing[] = 'mode';
        }
    } elseif ($groupCode === 'GroupedGesture') {
        $gesture = herikaActionGroupsNormalizeChoice($payload['gesture'] ?? ($payload['target'] ?? ''));
        $selectedCode = strval($variants[$gesture] ?? '');
        if ($selectedCode === '') {
            $missing[] = 'gesture';
        }
    }

    $missing = array_values(array_unique($missing));
    return [
        'code_name' => $selectedCode,
        'parameter_value' => $legacyParameter,
        'missing_required' => $missing,
        'valid' => $selectedCode !== '' && count($missing) === 0,
    ];
}
