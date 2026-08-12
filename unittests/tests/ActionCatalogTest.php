<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'core'.DIRECTORY_SEPARATOR.'action_catalog.php';

if (!function_exists('getFunctionCodeName')) {
    function getFunctionCodeName($key)
    {
        return $GLOBALS['TEST_FUNCTION_CODE_MAP'][$key] ?? false;
    }
}

final class ActionCatalogTest extends TestCase
{
    public function testActionConfirmationDefaultsAndEditorSupport(): void
    {
        $this->assertSame('ask', herikaActionCatalogGetDefaultConfirmationPolicy('TakeGoldFromPlayer'));
        $this->assertSame('ask', herikaActionCatalogGetDefaultConfirmationPolicy('ArrestPlayer'));
        $this->assertSame('automatic', herikaActionCatalogGetDefaultConfirmationPolicy('FollowPlayer'));

        $this->assertTrue(herikaActionCatalogRowSupportsConfirmation([
            'code_name' => 'FollowPlayer',
            'game_function' => true,
        ]));
        $this->assertFalse(herikaActionCatalogRowSupportsConfirmation([
            'code_name' => 'Inspect',
            'game_function' => true,
        ]));
        $this->assertFalse(herikaActionCatalogRowSupportsConfirmation([
            'code_name' => 'DirectorCommand',
            'game_function' => false,
        ]));

        $field = herikaActionCatalogGetConfirmationEditorField([
            'code_name' => 'TakeGoldFromPlayer',
        ]);
        $this->assertSame('confirmation_required', $field['key']);
        $this->assertSame('boolean', $field['type']);
        $this->assertTrue($field['default']);

        $this->assertSame('confirmcommand', herikaActionCatalogGetConfirmationCommandChannel([
            'code_name' => 'TakeGoldFromPlayer',
            'game_function' => true,
            'metadata' => [],
        ]));
        $this->assertSame('approvedcommand', herikaActionCatalogGetConfirmationCommandChannel([
            'code_name' => 'TakeGoldFromPlayer',
            'game_function' => true,
            'metadata' => ['custom_config' => ['confirmation_policy' => 'automatic']],
        ]));
        $this->assertSame('confirmcommand', herikaActionCatalogGetConfirmationCommandChannel([
            'code_name' => 'FollowPlayer',
            'game_function' => true,
            'metadata' => ['custom_config' => ['confirmation_policy' => 'ask']],
        ]));
        $this->assertSame('confirmcommand', herikaActionCatalogGetConfirmationCommandChannel([
            'code_name' => 'FollowPlayer',
            'game_function' => true,
            'metadata' => ['custom_config' => ['confirmation_required' => true]],
        ]));
        $this->assertSame('approvedcommand', herikaActionCatalogGetConfirmationCommandChannel([
            'code_name' => 'TakeGoldFromPlayer',
            'game_function' => true,
            'metadata' => ['custom_config' => ['confirmation_required' => false]],
        ]));
        $this->assertSame('command', herikaActionCatalogGetConfirmationCommandChannel([
            'code_name' => 'Inspect',
            'game_function' => true,
            'metadata' => ['custom_config' => ['confirmation_policy' => 'ask']],
        ]));
    }

    public function testBaseSeedFileDefinesActionAvailabilityAndActivation(): void
    {
        $this->assertNotContains('ReadQuestJournal', herikaGetRetiredActionCodes());
        $this->assertNotContains('Inspect', herikaGetRetiredActionCodes());
        $this->assertNotContains('InspectSurroundings', herikaGetRetiredActionCodes());
        $this->assertNotContains('Surrender', herikaGetRetiredActionCodes());

        $rows = herikaLoadActionCatalogBaseSeedRowsFromSeedFile();

        $this->assertTrue($rows['ReadQuestJournal']['available_to_npc']);
        $this->assertTrue($rows['ReadQuestJournal']['available_to_followers']);
        $this->assertTrue($rows['ReadQuestJournal']['available_to_narrator']);
        $this->assertTrue($rows['ReadQuestJournal']['is_activated']);

        $this->assertTrue($rows['Inspect']['available_to_npc']);
        $this->assertTrue($rows['Inspect']['available_to_followers']);
        $this->assertFalse($rows['Inspect']['available_to_narrator']);
        $this->assertTrue($rows['Inspect']['is_activated']);

        $this->assertTrue($rows['InspectSurroundings']['available_to_npc']);
        $this->assertTrue($rows['InspectSurroundings']['available_to_followers']);
        $this->assertFalse($rows['InspectSurroundings']['available_to_narrator']);
        $this->assertTrue($rows['InspectSurroundings']['is_activated']);

        $this->assertTrue($rows['Surrender']['available_to_npc']);
        $this->assertTrue($rows['Surrender']['available_to_followers']);
        $this->assertFalse($rows['Surrender']['available_to_narrator']);
        $this->assertTrue($rows['Surrender']['is_activated']);

        $this->assertTrue($rows['KillTarget']['available_to_narrator']);
        $this->assertFalse($rows['KillTarget']['is_activated']);
        $this->assertTrue($rows['SpawnNPC']['available_to_narrator']);
        $this->assertFalse($rows['SpawnNPC']['is_activated']);
        $this->assertTrue($rows['SpawnItem']['available_to_narrator']);
        $this->assertFalse($rows['SpawnItem']['is_activated']);
        $this->assertTrue($rows['SpawnGold']['available_to_narrator']);
        $this->assertFalse($rows['SpawnGold']['is_activated']);
        $this->assertTrue($rows['CreateNewNPC']['available_to_narrator']);
        $this->assertFalse($rows['CreateNewNPC']['is_activated']);
        $this->assertTrue($rows['DirectorCommand']['available_to_narrator']);
        $this->assertFalse($rows['DirectorCommand']['is_activated']);
        $this->assertTrue($rows['TeleportNPC']['available_to_narrator']);
        $this->assertFalse($rows['TeleportNPC']['is_activated']);
    }

    public function testBuildActionCatalogSeedRows_AssignsScopesAndSkipsRetiredActions(): void
    {
        $seedDefaultsByCode = [
            'MoveTo' => [
                'available_to_npc' => true,
                'available_to_followers' => false,
                'available_to_narrator' => false,
                'is_activated' => true,
            ],
            'Drink' => [
                'available_to_npc' => true,
                'available_to_followers' => true,
                'available_to_narrator' => false,
                'is_activated' => true,
            ],
            'TeleportNPC' => [
                'available_to_npc' => false,
                'available_to_followers' => false,
                'available_to_narrator' => true,
                'is_activated' => false,
            ],
            'KillTarget' => [
                'available_to_npc' => false,
                'available_to_followers' => false,
                'available_to_narrator' => true,
                'is_activated' => false,
            ],
            'SpawnNPC' => [
                'available_to_npc' => false,
                'available_to_followers' => false,
                'available_to_narrator' => true,
                'is_activated' => false,
            ],
            'SpawnItem' => [
                'available_to_npc' => false,
                'available_to_followers' => false,
                'available_to_narrator' => true,
                'is_activated' => false,
            ],
            'SpawnGold' => [
                'available_to_npc' => false,
                'available_to_followers' => false,
                'available_to_narrator' => true,
                'is_activated' => false,
            ],
            'CreateNewNPC' => [
                'available_to_npc' => false,
                'available_to_followers' => false,
                'available_to_narrator' => true,
                'is_activated' => false,
            ],
            'DirectorCommand' => [
                'available_to_npc' => false,
                'available_to_followers' => false,
                'available_to_narrator' => true,
                'is_activated' => false,
            ],
        ];

        $rows = herikaBuildActionCatalogSeedRows(
            [
                'MoveTo' => 'MoveTo',
                'Drink' => 'Drink',
                'KillTarget' => 'KillTarget',
                'SpawnNPC' => 'SpawnNPC',
                'SpawnItem' => 'SpawnItem',
                'SpawnGold' => 'SpawnGold',
                'CreateNewNPC' => 'CreateNewNPC',
                'DirectorCommand' => 'DirectorCommand',
                'TeleportNPC' => 'TeleportNPC',
                'AttackHunt' => 'Hunt',
            ],
            [],
            [],
            [],
            [],
            [
                'MoveTo' => [
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'target' => ['type' => 'string'],
                        ],
                        'required' => ['target'],
                    ],
                ],
                'Drink' => [
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'target' => ['type' => 'string'],
                        ],
                        'required' => [],
                    ],
                ],
                'TeleportNPC' => [
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'target' => ['type' => 'string'],
                            'item' => ['type' => 'string'],
                        ],
                        'required' => ['item'],
                    ],
                ],
                'KillTarget' => [
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'target' => ['type' => 'string'],
                        ],
                        'required' => ['target'],
                    ],
                ],
                'SpawnNPC' => [
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'target' => ['type' => 'string'],
                            'amount' => ['type' => 'integer'],
                        ],
                        'required' => ['target'],
                    ],
                ],
                'SpawnItem' => [
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'target' => ['type' => 'string'],
                            'item' => ['type' => 'string'],
                            'amount' => ['type' => 'integer'],
                        ],
                        'required' => ['item'],
                    ],
                ],
                'SpawnGold' => [
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'target' => ['type' => 'string'],
                            'amount' => ['type' => 'integer'],
                        ],
                        'required' => ['amount'],
                    ],
                ],
                'CreateNewNPC' => [
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'target' => ['type' => 'string'],
                        ],
                        'required' => ['target'],
                    ],
                ],
                'DirectorCommand' => [
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'target' => ['type' => 'string'],
                        ],
                        'required' => ['target'],
                    ],
                ],
            ],
            $seedDefaultsByCode
        );

        $this->assertArrayNotHasKey('AttackHunt', $rows);
        $this->assertTrue($rows['MoveTo']['available_to_npc']);
        $this->assertFalse($rows['MoveTo']['available_to_followers']);
        $this->assertFalse($rows['MoveTo']['available_to_narrator']);
        $this->assertTrue($rows['MoveTo']['is_activated']);

        $this->assertTrue($rows['Drink']['available_to_npc']);
        $this->assertTrue($rows['Drink']['available_to_followers']);
        $this->assertFalse($rows['Drink']['available_to_narrator']);
        $this->assertTrue($rows['Drink']['is_activated']);

        $this->assertFalse($rows['TeleportNPC']['available_to_npc']);
        $this->assertFalse($rows['TeleportNPC']['available_to_followers']);
        $this->assertTrue($rows['TeleportNPC']['available_to_narrator']);
        $this->assertFalse($rows['TeleportNPC']['is_activated']);

        $this->assertFalse($rows['KillTarget']['available_to_npc']);
        $this->assertFalse($rows['KillTarget']['available_to_followers']);
        $this->assertTrue($rows['KillTarget']['available_to_narrator']);
        $this->assertFalse($rows['KillTarget']['is_activated']);

        $this->assertFalse($rows['SpawnNPC']['available_to_npc']);
        $this->assertFalse($rows['SpawnNPC']['available_to_followers']);
        $this->assertTrue($rows['SpawnNPC']['available_to_narrator']);
        $this->assertFalse($rows['SpawnNPC']['is_activated']);

        $this->assertFalse($rows['SpawnItem']['available_to_npc']);
        $this->assertFalse($rows['SpawnItem']['available_to_followers']);
        $this->assertTrue($rows['SpawnItem']['available_to_narrator']);
        $this->assertFalse($rows['SpawnItem']['is_activated']);

        $this->assertFalse($rows['SpawnGold']['available_to_npc']);
        $this->assertFalse($rows['SpawnGold']['available_to_followers']);
        $this->assertTrue($rows['SpawnGold']['available_to_narrator']);
        $this->assertFalse($rows['SpawnGold']['is_activated']);

        $this->assertFalse($rows['CreateNewNPC']['available_to_npc']);
        $this->assertFalse($rows['CreateNewNPC']['available_to_followers']);
        $this->assertTrue($rows['CreateNewNPC']['available_to_narrator']);
        $this->assertFalse($rows['CreateNewNPC']['is_activated']);

        $this->assertFalse($rows['DirectorCommand']['available_to_npc']);
        $this->assertFalse($rows['DirectorCommand']['available_to_followers']);
        $this->assertTrue($rows['DirectorCommand']['available_to_narrator']);
        $this->assertFalse($rows['DirectorCommand']['is_activated']);
    }

    public function testBuildActionCatalogSeedRows_SeedsParametersMetadataAndScriptProxyProgram(): void
    {
        $rows = herikaBuildActionCatalogSeedRows(
            [
                'MoveTo' => 'MoveTo',
                'Drink' => 'Drink',
                'KillTarget' => 'KillTarget',
                'SpawnNPC' => 'SpawnNPC',
                'SpawnItem' => 'SpawnItem',
                'SpawnGold' => 'SpawnGold',
                'DirectorCommand' => 'DirectorCommand',
                'TeleportNPC' => 'TeleportNPC',
            ],
            [],
            [],
            [],
            [],
            [
                'MoveTo' => [
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'target' => ['type' => 'string'],
                        ],
                        'required' => ['target'],
                    ],
                ],
                'Drink' => [
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'target' => ['type' => 'string'],
                        ],
                        'required' => [],
                    ],
                ],
                'TeleportNPC' => [
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'target' => ['type' => 'string'],
                            'item' => ['type' => 'string'],
                        ],
                        'required' => ['item'],
                    ],
                ],
                'KillTarget' => [
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'target' => ['type' => 'string'],
                        ],
                        'required' => ['target'],
                    ],
                ],
                'SpawnNPC' => [
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'target' => ['type' => 'string'],
                            'amount' => ['type' => 'integer'],
                        ],
                        'required' => ['target'],
                    ],
                ],
                'SpawnItem' => [
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'target' => ['type' => 'string'],
                            'item' => ['type' => 'string'],
                            'amount' => ['type' => 'integer'],
                        ],
                        'required' => ['item'],
                    ],
                ],
                'SpawnGold' => [
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'target' => ['type' => 'string'],
                            'amount' => ['type' => 'integer'],
                        ],
                        'required' => ['amount'],
                    ],
                ],
                'DirectorCommand' => [
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'target' => ['type' => 'string'],
                        ],
                        'required' => ['target'],
                    ],
                ],
            ]
        );

        $this->assertSame('object', $rows['MoveTo']['parameters_json']['type']);
        $this->assertSame(['target'], $rows['MoveTo']['parameters_json']['required']);
        $this->assertSame('plugin_command', $rows['MoveTo']['metadata']['dispatch']);
        $this->assertTrue($rows['MoveTo']['game_function']);
        $this->assertNull($rows['MoveTo']['script_proxy_program']);

        $this->assertSame('script_proxy', $rows['Drink']['metadata']['dispatch']);
        $this->assertTrue($rows['Drink']['game_function']);
        $this->assertIsArray($rows['Drink']['script_proxy_program']);
        $this->assertNotEmpty($rows['Drink']['script_proxy_program']['cases']);

        $this->assertSame('rolecommand', $rows['TeleportNPC']['metadata']['dispatch']);
        $this->assertTrue($rows['TeleportNPC']['game_function']);
        $this->assertNull($rows['TeleportNPC']['script_proxy_program']);

        $this->assertSame('rolecommand', $rows['KillTarget']['metadata']['dispatch']);
        $this->assertTrue($rows['KillTarget']['game_function']);
        $this->assertNull($rows['KillTarget']['script_proxy_program']);

        $this->assertSame('rolecommand', $rows['SpawnNPC']['metadata']['dispatch']);
        $this->assertTrue($rows['SpawnNPC']['game_function']);
        $this->assertNull($rows['SpawnNPC']['script_proxy_program']);

        $this->assertSame('rolecommand', $rows['SpawnItem']['metadata']['dispatch']);
        $this->assertTrue($rows['SpawnItem']['game_function']);
        $this->assertNull($rows['SpawnItem']['script_proxy_program']);

        $this->assertSame('rolecommand', $rows['SpawnGold']['metadata']['dispatch']);
        $this->assertTrue($rows['SpawnGold']['game_function']);
        $this->assertNull($rows['SpawnGold']['script_proxy_program']);

        $this->assertSame('server_action', $rows['DirectorCommand']['metadata']['dispatch']);
        $this->assertFalse($rows['DirectorCommand']['game_function']);
        $this->assertNull($rows['DirectorCommand']['script_proxy_program']);
    }

    public function testActionCatalogMetadataFlagEnabled_ReadsBooleanFlagsFromCatalogRows(): void
    {
        $GLOBALS['HERIKA_ACTION_CATALOG_ROWS_BY_CODE'] = [
            'ExtCmdCHIMNFF_TeachRightHandSpell' => [
                'code_name' => 'ExtCmdCHIMNFF_TeachRightHandSpell',
                'metadata' => [
                    'suppress_placeholder_infoaction' => true,
                ],
            ],
        ];

        $this->assertTrue(
            herikaActionCatalogMetadataFlagEnabled(
                'ExtCmdCHIMNFF_TeachRightHandSpell',
                'suppress_placeholder_infoaction'
            )
        );
        $this->assertFalse(
            herikaActionCatalogMetadataFlagEnabled(
                'ExtCmdCHIMNFF_TeachRightHandSpell',
                'missing_flag'
            )
        );

        unset($GLOBALS['HERIKA_ACTION_CATALOG_ROWS_BY_CODE']);
    }

    public function testResolveNpcRolemasterState_FallsBackToLegacyConfOptWhenMetadataIsMissing(): void
    {
        $previousDb = $GLOBALS['db'] ?? null;
        $hadDb = array_key_exists('db', $GLOBALS);
        $previousRolemaster = $GLOBALS['is_rolemastered'] ?? null;
        $hadRolemaster = array_key_exists('is_rolemastered', $GLOBALS);

        $GLOBALS['db'] = new class {
            public function escape($value)
            {
                return str_replace("'", "''", strval($value));
            }

            public function fetchOne($sql)
            {
                return ['value' => '1'];
            }
        };

        unset($GLOBALS['is_rolemastered']);
        herikaRolemasterStateResetCache();

        try {
            $this->assertTrue(herikaResolveNpcRolemasterState('Mallory Mucklow', [
                'metadata' => [],
                'extended' => [],
                'load_lookup' => false,
                'use_global' => false,
            ]));
        } finally {
            herikaRolemasterStateResetCache();

            if ($hadDb) {
                $GLOBALS['db'] = $previousDb;
            } else {
                unset($GLOBALS['db']);
            }

            if ($hadRolemaster) {
                $GLOBALS['is_rolemastered'] = $previousRolemaster;
            } else {
                unset($GLOBALS['is_rolemastered']);
            }
        }
    }

    public function testBuildActionCatalogSeedRows_SeedsBuiltinRequirementsAndCooldownMetadata(): void
    {
        $rows = herikaBuildActionCatalogSeedRows(
            [
                'RentRoom' => 'RentRoom',
                'WaitHere' => 'WaitHere',
                'SheatheWeapon' => 'SheatheWeapon',
                'Training' => 'Training',
                'HireCarriage' => 'HireCarriage',
                'HireFerry' => 'HireFerry',
            ],
            [],
            [],
            [],
            [],
            [
                'RentRoom' => ['parameters' => ['type' => 'object', 'properties' => [], 'required' => []]],
                'WaitHere' => ['parameters' => ['type' => 'object', 'properties' => [], 'required' => []]],
                'SheatheWeapon' => ['parameters' => ['type' => 'object', 'properties' => [], 'required' => []]],
                'Training' => ['parameters' => ['type' => 'object', 'properties' => [], 'required' => []]],
                'HireCarriage' => ['parameters' => ['type' => 'object', 'properties' => [], 'required' => []]],
                'HireFerry' => ['parameters' => ['type' => 'object', 'properties' => [], 'required' => []]],
            ]
        );

        $this->assertSame(['0005091B'], $rows['RentRoom']['metadata']['requirements']['npc_factions_any']);
        $this->assertSame(300, $rows['WaitHere']['metadata']['cooldown_seconds']);
        $this->assertTrue($rows['SheatheWeapon']['metadata']['requirements']['activity']['is_weapon_drawn']);
        $this->assertTrue($rows['Training']['metadata']['requirements']['requires_training_service']);
        $this->assertSame(
            'allowed_npc_names',
            $rows['HireCarriage']['metadata']['requirements']['npc_name_in_action_config_list']['config_key']
        );
        $this->assertSame(
            "Bjorlam\nAlfarinn\nKibell\nSigaar\nThaer\nEngar\nGunjar\nMarkus",
            $rows['HireCarriage']['metadata']['editor_fields'][1]['default']
        );
        $this->assertSame(
            "Gort\nHarlaug\nJolf",
            $rows['HireFerry']['metadata']['editor_fields'][1]['default']
        );
    }

    public function testBuildActionCatalogSeedRows_NormalizesDisplayTextToGenericNpcAndPlayerLabels(): void
    {
        $hadHerikaName = array_key_exists('HERIKA_NAME', $GLOBALS);
        $hadPlayerName = array_key_exists('PLAYER_NAME', $GLOBALS);
        $originalHerikaName = $GLOBALS['HERIKA_NAME'] ?? null;
        $originalPlayerName = $GLOBALS['PLAYER_NAME'] ?? null;

        $GLOBALS['HERIKA_NAME'] = 'Narrator';
        $GLOBALS['PLAYER_NAME'] = 'RANGROO';

        try {
            $rows = herikaBuildActionCatalogSeedRows(
                [
                    'TakeGoldFromPlayer' => 'TakeGoldFromRANGROO',
                    'MakeFollower' => 'JoinRANGROOParty',
                ],
                [
                    'TakeGoldFromPlayer' => 'The Narrator takes amount (property target) of gold from RANGROO, once RANGROO is agree. infer amount from context.',
                    'MakeFollower' => 'The Narrator joins RANGROO party and travels with RANGROO as an ally.',
                ],
                [
                    'TakeGoldFromPlayer' => 'RANGROO gave #TARGET# coins to The Narrator. If this a transaction, maybe GiveItemTo is needed.',
                    'MakeFollower' => 'The Narrator is now part of RANGROO party.',
                ]
            );

            $this->assertSame('Take_Gold_From_Player', $rows['TakeGoldFromPlayer']['action_name']);
            $this->assertSame(
                'NPC takes amount (property target) of gold from PLAYER, once PLAYER is agree. infer amount from context.',
                $rows['TakeGoldFromPlayer']['description']
            );
            $this->assertSame(
                'PLAYER gave #TARGET# coins to NPC. If this a transaction, maybe GiveItemTo is needed.',
                $rows['TakeGoldFromPlayer']['return_message']
            );
            $this->assertSame('Join_Player_Party', $rows['MakeFollower']['action_name']);
            $this->assertSame(
                'NPC joins PLAYER party and travels with PLAYER as an ally.',
                $rows['MakeFollower']['description']
            );
            $this->assertSame(
                'NPC is now part of PLAYER party.',
                $rows['MakeFollower']['return_message']
            );
        } finally {
            if ($hadHerikaName) {
                $GLOBALS['HERIKA_NAME'] = $originalHerikaName;
            } else {
                unset($GLOBALS['HERIKA_NAME']);
            }

            if ($hadPlayerName) {
                $GLOBALS['PLAYER_NAME'] = $originalPlayerName;
            } else {
                unset($GLOBALS['PLAYER_NAME']);
            }
        }
    }

    public function testApplyRowsToRuntimeFunctions_UsesCatalogRowsAsBaselineSourceOfTruth(): void
    {
        $previousRows = $GLOBALS['HERIKA_ACTION_CATALOG_ROWS_BY_CODE'] ?? null;
        $previousFunctions = $GLOBALS['FUNCTIONS'] ?? null;
        $previousBaseFunctions = $GLOBALS['BASE_FUNCTIONS'] ?? null;
        $previousFallbackBaseFunctions = $GLOBALS['HERIKA_BASE_FUNCTIONS_FALLBACK'] ?? null;
        $previousNames = $GLOBALS['F_NAMES'] ?? null;
        $previousTranslations = $GLOBALS['F_TRANSLATIONS'] ?? null;
        $previousReturnMessages = $GLOBALS['F_RETURNMESSAGES'] ?? null;
        $previousPreferredCodes = $GLOBALS['HERIKA_ACTION_NAME_PREFERRED_CODE'] ?? null;
        $previousCodeMap = $GLOBALS['TEST_FUNCTION_CODE_MAP'] ?? null;

        $GLOBALS['HERIKA_ACTION_CATALOG_ROWS_BY_CODE'] = [
            'Toast' => [
                'code_name' => 'Toast',
                'action_name' => 'Make_a_Toast',
                'description' => 'Table-owned toast description.',
                'return_message' => 'Table-owned toast return.',
                'available_to_npc' => true,
                'available_to_followers' => true,
                'is_activated' => true,
                'parameters_json' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
                'metadata' => [
                    'builtin' => true,
                    'dispatch' => 'plugin_command',
                ],
                'game_function' => true,
                'script_proxy_program' => null,
            ],
        ];

        $GLOBALS['FUNCTIONS'] = [
            [
                'name' => 'Fallback_Toast',
                'description' => 'Fallback toast description.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ],
            [
                'name' => 'Ext_Action',
                'description' => 'Extension action.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ],
        ];

        $GLOBALS['HERIKA_BASE_FUNCTIONS_FALLBACK'] = [
            'Toast' => $GLOBALS['FUNCTIONS'][0],
        ];
        $GLOBALS['F_NAMES'] = [
            'Toast' => 'Fallback_Toast',
            'ExtCode' => 'Ext_Action',
        ];
        $GLOBALS['F_TRANSLATIONS'] = [
            'Toast' => 'Fallback toast description.',
            'ExtCode' => 'Extension action.',
        ];
        $GLOBALS['F_RETURNMESSAGES'] = [
            'Toast' => 'Fallback toast return.',
            'ExtCode' => '',
        ];
        $GLOBALS['TEST_FUNCTION_CODE_MAP'] = [
            'Fallback_Toast' => 'Toast',
            'Make_a_Toast' => 'Toast',
            'Ext_Action' => 'ExtCode',
        ];

        try {
            herikaActionCatalogApplyRowsToRuntimeFunctions();

            $this->assertSame('Make_a_Toast', $GLOBALS['F_NAMES']['Toast']);
            $this->assertSame('Table-owned toast description.', $GLOBALS['F_TRANSLATIONS']['Toast']);
            $this->assertSame('Table-owned toast return.', $GLOBALS['F_RETURNMESSAGES']['Toast']);
            $this->assertArrayHasKey('Toast', $GLOBALS['BASE_FUNCTIONS']);
            $this->assertSame('Make_a_Toast', $GLOBALS['BASE_FUNCTIONS']['Toast']['name']);
            $this->assertSame('Table-owned toast description.', $GLOBALS['BASE_FUNCTIONS']['Toast']['description']);
            $this->assertArrayHasKey('ExtCode', $GLOBALS['BASE_FUNCTIONS']);
            $this->assertSame('Ext_Action', $GLOBALS['BASE_FUNCTIONS']['ExtCode']['name']);
        } finally {
            if ($previousRows !== null) {
                $GLOBALS['HERIKA_ACTION_CATALOG_ROWS_BY_CODE'] = $previousRows;
            } else {
                unset($GLOBALS['HERIKA_ACTION_CATALOG_ROWS_BY_CODE']);
            }

            if ($previousFunctions !== null) {
                $GLOBALS['FUNCTIONS'] = $previousFunctions;
            } else {
                unset($GLOBALS['FUNCTIONS']);
            }

            if ($previousBaseFunctions !== null) {
                $GLOBALS['BASE_FUNCTIONS'] = $previousBaseFunctions;
            } else {
                unset($GLOBALS['BASE_FUNCTIONS']);
            }

            if ($previousFallbackBaseFunctions !== null) {
                $GLOBALS['HERIKA_BASE_FUNCTIONS_FALLBACK'] = $previousFallbackBaseFunctions;
            } else {
                unset($GLOBALS['HERIKA_BASE_FUNCTIONS_FALLBACK']);
            }

            if ($previousNames !== null) {
                $GLOBALS['F_NAMES'] = $previousNames;
            } else {
                unset($GLOBALS['F_NAMES']);
            }

            if ($previousTranslations !== null) {
                $GLOBALS['F_TRANSLATIONS'] = $previousTranslations;
            } else {
                unset($GLOBALS['F_TRANSLATIONS']);
            }

            if ($previousReturnMessages !== null) {
                $GLOBALS['F_RETURNMESSAGES'] = $previousReturnMessages;
            } else {
                unset($GLOBALS['F_RETURNMESSAGES']);
            }

            if ($previousPreferredCodes !== null) {
                $GLOBALS['HERIKA_ACTION_NAME_PREFERRED_CODE'] = $previousPreferredCodes;
            } else {
                unset($GLOBALS['HERIKA_ACTION_NAME_PREFERRED_CODE']);
            }

            if ($previousCodeMap !== null) {
                $GLOBALS['TEST_FUNCTION_CODE_MAP'] = $previousCodeMap;
            } else {
                unset($GLOBALS['TEST_FUNCTION_CODE_MAP']);
            }
        }
    }

    public function testActionCatalogRowIsAvailableInCurrentMode_UsesNarratorScopeForNarrator(): void
    {
        $previousHerikaName = $GLOBALS['HERIKA_NAME'] ?? null;
        $hadHerikaName = array_key_exists('HERIKA_NAME', $GLOBALS);
        $previousIsNpc = $GLOBALS['IS_NPC'] ?? null;
        $hadIsNpc = array_key_exists('IS_NPC', $GLOBALS);

        $GLOBALS['HERIKA_NAME'] = 'The Narrator';
        $GLOBALS['IS_NPC'] = false;

        try {
            $this->assertTrue(herikaActionCatalogRowIsAvailableInCurrentMode([
                'available_to_npc' => false,
                'available_to_followers' => false,
                'available_to_narrator' => true,
            ]));

            $this->assertFalse(herikaActionCatalogRowIsAvailableInCurrentMode([
                'available_to_npc' => true,
                'available_to_followers' => true,
                'available_to_narrator' => false,
            ]));
        } finally {
            if ($hadHerikaName) {
                $GLOBALS['HERIKA_NAME'] = $previousHerikaName;
            } else {
                unset($GLOBALS['HERIKA_NAME']);
            }

            if ($hadIsNpc) {
                $GLOBALS['IS_NPC'] = $previousIsNpc;
            } else {
                unset($GLOBALS['IS_NPC']);
            }
        }
    }

    public function testVanillaActionGroupsCompactEligibleRuntimeFunctions(): void
    {
        $trackedGlobals = [
            'FUNCTIONS', 'ENABLED_FUNCTIONS', 'BASE_FUNCTIONS', 'F_NAMES', 'F_TRANSLATIONS',
            'F_RETURNMESSAGES', 'TEST_FUNCTION_CODE_MAP', 'HERIKA_ACTION_GROUP_CUSTOM_CODE_SET',
            'HERIKA_GROUPED_ACTION_NAME_TO_CODE', 'HERIKA_GROUPED_ACTION_SPECS',
        ];
        $previousGlobals = [];
        foreach ($trackedGlobals as $globalName) {
            $previousGlobals[$globalName] = [
                'exists' => array_key_exists($globalName, $GLOBALS),
                'value' => $GLOBALS[$globalName] ?? null,
            ];
        }

        $legacyCodes = [
            'AddBounty', 'ArrestPlayer', 'ForgiveCrime', 'PayBounty',
            'Attack', 'Brawl',
            'Follow', 'FollowPlayer', 'ComeCloser',
            'IncreaseWalkSpeed', 'DecreaseWalkSpeed',
            'GiveItemTo', 'GiveGoldTo',
            'OpenInventory', 'OpenInventory2',
            'Drink', 'Toast',
        ];
        $GLOBALS['FUNCTIONS'] = [];
        $GLOBALS['TEST_FUNCTION_CODE_MAP'] = [];
        foreach (array_merge($legacyCodes, ['KeepAction']) as $codeName) {
            $GLOBALS['FUNCTIONS'][] = [
                'name' => $codeName,
                'description' => $codeName,
                'parameters' => ['type' => 'object', 'properties' => [], 'required' => []],
            ];
            $GLOBALS['TEST_FUNCTION_CODE_MAP'][$codeName] = $codeName;
        }
        $GLOBALS['ENABLED_FUNCTIONS'] = array_merge($legacyCodes, ['KeepAction']);
        $GLOBALS['BASE_FUNCTIONS'] = [];
        $GLOBALS['F_NAMES'] = [];
        $GLOBALS['F_TRANSLATIONS'] = [];
        $GLOBALS['F_RETURNMESSAGES'] = [];
        $GLOBALS['HERIKA_ACTION_GROUP_CUSTOM_CODE_SET'] = [];

        try {
            $this->assertSame(7, herikaActionGroupsApplyToRuntime());
            $this->assertCount(8, $GLOBALS['FUNCTIONS']);
            $this->assertContains('KeepAction', array_column($GLOBALS['FUNCTIONS'], 'name'));
            $this->assertContains('Handle_Crime', array_column($GLOBALS['FUNCTIONS'], 'name'));
            $this->assertContains('Start_Combat', array_column($GLOBALS['FUNCTIONS'], 'name'));
            $this->assertContains('Follow', array_column($GLOBALS['FUNCTIONS'], 'name'));
            $this->assertContains('Set_Pace', array_column($GLOBALS['FUNCTIONS'], 'name'));
            $this->assertContains('Give', array_column($GLOBALS['FUNCTIONS'], 'name'));
            $this->assertContains('Exchange', array_column($GLOBALS['FUNCTIONS'], 'name'));
            $this->assertContains('Perform_Gesture', array_column($GLOBALS['FUNCTIONS'], 'name'));
            $this->assertNotContains('Attack', array_column($GLOBALS['FUNCTIONS'], 'name'));
            $this->assertNotContains('GiveGoldTo', $GLOBALS['ENABLED_FUNCTIONS']);
            $this->assertContains('GroupedGive', $GLOBALS['ENABLED_FUNCTIONS']);
            $this->assertSame(
                ['actor', 'player', 'approach_player'],
                $GLOBALS['BASE_FUNCTIONS']['GroupedFollow']['parameters']['properties']['item']['enum']
            );
        } finally {
            foreach ($previousGlobals as $globalName => $previous) {
                if ($previous['exists']) {
                    $GLOBALS[$globalName] = $previous['value'];
                } else {
                    unset($GLOBALS[$globalName]);
                }
            }
        }
    }

    public function testCustomizedVanillaActionsRemainIndividual(): void
    {
        $GLOBALS['FUNCTIONS'] = [
            ['name' => 'Drink', 'description' => '', 'parameters' => []],
            ['name' => 'Toast', 'description' => '', 'parameters' => []],
        ];
        $GLOBALS['ENABLED_FUNCTIONS'] = ['Drink', 'Toast'];
        $GLOBALS['TEST_FUNCTION_CODE_MAP'] = ['Drink' => 'Drink', 'Toast' => 'Toast'];
        $GLOBALS['HERIKA_ACTION_GROUP_CUSTOM_CODE_SET'] = ['Toast' => true];

        try {
            $this->assertSame(0, herikaActionGroupsApplyToRuntime());
            $this->assertSame(['Drink', 'Toast'], array_column($GLOBALS['FUNCTIONS'], 'name'));
            $this->assertSame(['Drink', 'Toast'], $GLOBALS['ENABLED_FUNCTIONS']);
        } finally {
            unset(
                $GLOBALS['FUNCTIONS'],
                $GLOBALS['ENABLED_FUNCTIONS'],
                $GLOBALS['TEST_FUNCTION_CODE_MAP'],
                $GLOBALS['HERIKA_ACTION_GROUP_CUSTOM_CODE_SET']
            );
        }
    }

    public function testVanillaActionGroupsResolveLegacyCodesAndPayloads(): void
    {
        $GLOBALS['HERIKA_GROUPED_ACTION_SPECS'] = herikaActionGroupsGetSpecs();

        try {
            $combat = herikaActionGroupsResolveExecution('GroupedStartCombat', [
                'target' => 'Bandit',
                'item' => 'non-lethal',
            ]);
            $this->assertTrue($combat['valid']);
            $this->assertSame('Brawl', $combat['code_name']);
            $this->assertSame('Bandit', $combat['parameter_value']);

            $crime = herikaActionGroupsResolveExecution('GroupedHandleCrime', [
                'target' => 'add_bounty',
                'item' => 'Custom',
                'amount' => 250,
            ]);
            $this->assertTrue($crime['valid']);
            $this->assertSame('AddBounty', $crime['code_name']);
            $this->assertSame('Custom@250', $crime['parameter_value']);

            $gold = herikaActionGroupsResolveExecution('GroupedGive', [
                'target' => 'Player',
                'item' => 'Gold',
                'amount' => 25,
            ]);
            $this->assertTrue($gold['valid']);
            $this->assertSame('GiveGoldTo', $gold['code_name']);
            $this->assertSame(['target' => 'Player', 'item' => '25'], $gold['parameter_value']);

            $gift = herikaActionGroupsResolveExecution('GroupedExchange', ['target' => 'accept_gift']);
            $this->assertTrue($gift['valid']);
            $this->assertSame('OpenInventory2', $gift['code_name']);

            $pace = herikaActionGroupsResolveExecution('GroupedSetPace', ['target' => 'slower']);
            $this->assertTrue($pace['valid']);
            $this->assertSame('DecreaseWalkSpeed', $pace['code_name']);
            $this->assertSame('', $pace['parameter_value']);

            $gesture = herikaActionGroupsResolveExecution('GroupedGesture', ['target' => 'toast']);
            $this->assertTrue($gesture['valid']);
            $this->assertSame('Toast', $gesture['code_name']);

            $legacyFollow = herikaActionGroupsResolveExecution('GroupedFollow', ['target' => 'Lydia']);
            $this->assertTrue($legacyFollow['valid']);
            $this->assertSame('Follow', $legacyFollow['code_name']);
            $this->assertSame('Lydia', $legacyFollow['parameter_value']);

            $invalidFollow = herikaActionGroupsResolveExecution('GroupedFollow', ['mode' => 'actor']);
            $this->assertFalse($invalidFollow['valid']);
            $this->assertContains('target', $invalidFollow['missing_required']);
        } finally {
            unset($GLOBALS['HERIKA_GROUPED_ACTION_SPECS']);
        }
    }
}
