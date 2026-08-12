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
            'description' => 'Choose one guard response to the player\'s crime with mode. For add_bounty, put the crime type in item and a Custom gold value in amount.',
            'selector' => 'mode',
            'variants' => [
                'add_bounty' => 'AddBounty',
                'arrest' => 'ArrestPlayer',
                'forgive' => 'ForgiveCrime',
                'collect_bounty_payment' => 'PayBounty',
            ],
            'parameters' => [
                'type' => 'object',
                'required' => ['mode'],
                'properties' => [
                    'mode' => [
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
                'additionalProperties' => false,
            ],
        ],
        'GroupedStartCombat' => [
            'action_name' => 'Start_Combat',
            'description' => 'Start combat with the actor in target. Select lethal or brawl with mode; brawl uses protected non-lethal behavior.',
            'selector' => 'mode',
            'variants' => [
                'lethal' => 'Attack',
                'brawl' => 'Brawl',
            ],
            'parameters' => [
                'type' => 'object',
                'required' => ['mode', 'target'],
                'properties' => [
                    'mode' => [
                        'type' => 'string',
                        'description' => 'Whether combat is lethal or a protected brawl.',
                    ],
                    'target' => [
                        'type' => 'string',
                        'description' => 'Target NPC, actor, or being.',
                    ],
                ],
                'additionalProperties' => false,
            ],
        ],
        'GroupedFollow' => [
            'action_name' => 'Follow',
            'description' => 'Select follow_actor, follow_player, or approach_player with mode. For follow_actor, put the nearby actor in target.',
            'selector' => 'mode',
            'variants' => [
                'follow_actor' => 'Follow',
                'follow_player' => 'FollowPlayer',
                'approach_player' => 'ComeCloser',
            ],
            'parameters' => [
                'type' => 'object',
                'required' => ['mode'],
                'properties' => [
                    'mode' => [
                        'type' => 'string',
                        'description' => 'Following behavior to use.',
                    ],
                    'target' => [
                        'type' => 'string',
                        'description' => 'Required only for follow_actor; the nearby actor to follow.',
                    ],
                ],
                'additionalProperties' => false,
            ],
        ],
        'GroupedSetPace' => [
            'action_name' => 'Set_Pace',
            'description' => 'Adjust the NPC\'s movement pace by selecting faster or slower with mode.',
            'selector' => 'mode',
            'variants' => [
                'faster' => 'IncreaseWalkSpeed',
                'slower' => 'DecreaseWalkSpeed',
            ],
            'parameters' => [
                'type' => 'object',
                'required' => ['mode'],
                'properties' => [
                    'mode' => [
                        'type' => 'string',
                        'description' => 'Whether to move faster or slower.',
                    ],
                ],
                'additionalProperties' => false,
            ],
        ],
        'GroupedGive' => [
            'action_name' => 'Give',
            'description' => 'Give an inventory item or gold to the actor in target. Select item or gold with mode; use item only for an inventory item and amount for the quantity.',
            'selector' => 'mode',
            'variants' => [
                'item' => 'GiveItemTo',
                'gold' => 'GiveGoldTo',
            ],
            'parameters' => [
                'type' => 'object',
                'required' => ['mode', 'target'],
                'properties' => [
                    'mode' => [
                        'type' => 'string',
                        'description' => 'Whether to give an inventory item or gold.',
                    ],
                    'target' => [
                        'type' => 'string',
                        'description' => 'Actor who will receive the item or gold.',
                    ],
                    'item' => [
                        'type' => 'string',
                        'description' => 'Required for item mode; use the exact item identifier from inventory. Leave blank for gold.',
                    ],
                    'amount' => [
                        'type' => 'integer',
                        'minimum' => 1,
                        'description' => 'Required for gold. Optional for an item and defaults to 1.',
                    ],
                ],
                'additionalProperties' => false,
            ],
        ],
        'GroupedExchange' => [
            'action_name' => 'Exchange',
            'description' => 'Open normal trading or let the player give a gift to the NPC. Select trade or receive_gift with mode.',
            'selector' => 'mode',
            'variants' => [
                'trade' => 'OpenInventory',
                'receive_gift' => 'OpenInventory2',
            ],
            'parameters' => [
                'type' => 'object',
                'required' => ['mode'],
                'properties' => [
                    'mode' => [
                        'type' => 'string',
                        'description' => 'Use trade for normal exchange or receive_gift when the player is giving items.',
                    ],
                ],
                'additionalProperties' => false,
            ],
        ],
        'GroupedGesture' => [
            'action_name' => 'Perform_Gesture',
            'description' => 'Perform a visual drinking or toast gesture by selecting drink_gesture or toast with mode. This does not consume an inventory item.',
            'selector' => 'mode',
            'variants' => [
                'drink_gesture' => 'Drink',
                'toast' => 'Toast',
            ],
            'parameters' => [
                'type' => 'object',
                'required' => ['mode'],
                'properties' => [
                    'mode' => [
                        'type' => 'string',
                        'description' => 'Visual gesture to perform.',
                    ],
                ],
                'additionalProperties' => false,
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
        $outcome = herikaActionGroupsNormalizeChoice($payload['mode'] ?? ($payload['outcome'] ?? ($payload['target'] ?? '')));
        if ($outcome === 'pay_bounty') {
            $outcome = 'collect_bounty_payment';
        }
        $selectedCode = strval($variants[$outcome] ?? '');
        if ($outcome === '') {
            $missing[] = 'mode';
        } elseif ($selectedCode === '') {
            $missing[] = 'available mode';
        } elseif ($outcome === 'add_bounty') {
            $crimeType = trim(strval($payload['crime_type'] ?? ($payload['item'] ?? '')));
            if ($crimeType === '') {
                $missing[] = 'item';
            } else {
                $allowedCrimeTypes = $spec['parameters']['properties']['item']['enum'] ?? [];
                $canonicalCrimeType = '';
                foreach ($allowedCrimeTypes as $allowedCrimeType) {
                    if (strcasecmp($crimeType, strval($allowedCrimeType)) === 0) {
                        $canonicalCrimeType = strval($allowedCrimeType);
                        break;
                    }
                }
                if ($canonicalCrimeType === '') {
                    $missing[] = 'valid item';
                }
                $legacyParameter = $canonicalCrimeType;
                if ($canonicalCrimeType === 'Custom') {
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
        if (in_array($mode, ['nonlethal', 'non_lethal'], true)) {
            $mode = 'brawl';
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
        if ($mode === 'actor') {
            $mode = 'follow_actor';
        } elseif ($mode === 'player') {
            $mode = 'follow_player';
        } elseif ($mode === '' && $target !== '') {
            $mode = 'follow_actor';
        }
        $selectedCode = strval($variants[$mode] ?? '');
        if ($selectedCode === '') {
            $missing[] = 'mode';
        } elseif ($mode === 'follow_actor') {
            $legacyParameter = $target;
            if ($target === '') {
                $missing[] = 'target';
            }
        }
    } elseif ($groupCode === 'GroupedSetPace') {
        $pace = herikaActionGroupsNormalizeChoice($payload['mode'] ?? ($payload['pace'] ?? ($payload['target'] ?? '')));
        $selectedCode = strval($variants[$pace] ?? '');
        if ($selectedCode === '') {
            $missing[] = 'mode';
        }
    } elseif ($groupCode === 'GroupedGive') {
        $mode = herikaActionGroupsNormalizeChoice($payload['mode'] ?? '');
        $target = trim(strval($payload['target'] ?? ''));
        $item = trim(strval($payload['item'] ?? ''));
        $legacyGoldSentinel = in_array(strtolower($item), ['gold', 'coins', 'septims'], true);
        if ($mode === '') {
            $mode = $legacyGoldSentinel ? 'gold' : ($item !== '' ? 'item' : '');
        }
        $selectedCode = strval($variants[$mode] ?? '');
        if ($target === '') {
            $missing[] = 'target';
        }
        if ($selectedCode === '') {
            $missing[] = 'mode';
        } elseif ($mode === 'gold') {
            $amount = intval($payload['amount'] ?? 0);
            if ($amount <= 0) {
                $missing[] = 'amount';
            } else {
                $legacyParameter = ['target' => $target, 'item' => strval($amount)];
            }
        } elseif ($mode === 'item') {
            if ($item === '') {
                $missing[] = 'item';
            }
            $amount = array_key_exists('amount', $payload) ? intval($payload['amount']) : 1;
            if ($amount <= 0) {
                $missing[] = 'amount';
            }
            $legacyParameter = ['target' => $target, 'item' => $item, 'amount' => $amount];
        }
    } elseif ($groupCode === 'GroupedExchange') {
        $mode = herikaActionGroupsNormalizeChoice($payload['mode'] ?? ($payload['target'] ?? ''));
        if ($mode === 'accept_gift') {
            $mode = 'receive_gift';
        }
        $selectedCode = strval($variants[$mode] ?? '');
        if ($selectedCode === '') {
            $missing[] = 'mode';
        }
    } elseif ($groupCode === 'GroupedGesture') {
        $gesture = herikaActionGroupsNormalizeChoice($payload['mode'] ?? ($payload['gesture'] ?? ($payload['target'] ?? '')));
        if ($gesture === 'drink') {
            $gesture = 'drink_gesture';
        }
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
