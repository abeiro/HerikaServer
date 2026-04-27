<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'core'.DIRECTORY_SEPARATOR.'action_catalog.php';

final class ActionCatalogTest extends TestCase
{
    public function testBuildActionCatalogSeedRows_AssignsScopesAndSkipsRetiredActions(): void
    {
        $rows = herikaBuildActionCatalogSeedRows(
            [
                'MoveTo' => 'MoveTo',
                'Drink' => 'Drink',
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
            ]
        );

        $this->assertArrayNotHasKey('AttackHunt', $rows);
        $this->assertArrayNotHasKey('Surrender', $rows);

        $this->assertTrue($rows['MoveTo']['available_to_npc']);
        $this->assertFalse($rows['MoveTo']['available_to_followers']);
        $this->assertTrue($rows['MoveTo']['is_activated']);

        $this->assertTrue($rows['Drink']['available_to_npc']);
        $this->assertTrue($rows['Drink']['available_to_followers']);
        $this->assertTrue($rows['Drink']['is_activated']);
    }

    public function testBuildActionCatalogSeedRows_SeedsParametersMetadataAndScriptProxyProgram(): void
    {
        $rows = herikaBuildActionCatalogSeedRows(
            [
                'MoveTo' => 'MoveTo',
                'Drink' => 'Drink',
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
                    'TakeGoldFromPlayer' => 'RANGROO gave #TARGET# coins to The Narrator. If this a transaction, maybe GiveItemToPlayer is needed.',
                    'MakeFollower' => 'The Narrator is now part of RANGROO party.',
                ]
            );

            $this->assertSame('TakeGoldFromPlayer', $rows['TakeGoldFromPlayer']['action_name']);
            $this->assertSame(
                'NPC takes amount (property target) of gold from PLAYER, once PLAYER is agree. infer amount from context.',
                $rows['TakeGoldFromPlayer']['description']
            );
            $this->assertSame(
                'PLAYER gave #TARGET# coins to NPC. If this a transaction, maybe GiveItemToPlayer is needed.',
                $rows['TakeGoldFromPlayer']['return_message']
            );
            $this->assertSame('JoinPlayerParty', $rows['MakeFollower']['action_name']);
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
}
