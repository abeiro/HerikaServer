<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/rolemaster_bored.php';

final class RolemasterBoredRoutingTest extends TestCase
{
    public function testActorMapExcludesPlayerAndKeepsSeed(): void
    {
        $actors = chimRolemasterBoredActorMap(
            '|Camilla Valerius|RANGROO|Lucan Valerius|',
            'RANGROO',
            'Camilla Valerius'
        );

        $this->assertSame([
            'camilla valerius' => 'Camilla Valerius',
            'lucan valerius' => 'Lucan Valerius',
        ], $actors);
    }

    public function testInstructionsRejectInventedActorsAndRequireSeed(): void
    {
        $actors = chimRolemasterBoredActorMap('|Camilla Valerius|Lucan Valerius|', 'RANGROO', 'Camilla Valerius');
        $instructions = [
            ['character' => 'Siddgeir', 'instruction' => 'Appear from nowhere'],
            ['character' => 'lucan valerius', 'instruction' => 'Ask about the shop'],
            ['character' => 'camilla valerius', 'instruction' => 'Answer Lucan'],
        ];

        $filtered = chimRolemasterFilterBoredInstructions($instructions, $actors, 'Camilla Valerius');

        $this->assertCount(2, $filtered);
        $this->assertSame('Camilla Valerius', $filtered[0]['character']);
        $this->assertSame('Lucan Valerius', $filtered[1]['character']);
        $this->assertSame(
            [],
            chimRolemasterFilterBoredInstructions(
                [['character' => 'Lucan Valerius', 'instruction' => 'Speak alone']],
                $actors,
                'Camilla Valerius'
            )
        );
    }

    public function testListenerRequirementOnlyUsesKnownNearbyActor(): void
    {
        $actors = chimRolemasterBoredActorMap('|Camilla Valerius|Lucan Valerius|', 'RANGROO', 'Camilla Valerius');

        $this->assertSame(
            ' The dialogue listener must be Lucan Valerius.',
            chimRolemasterBoredListenerRequirement('lucan valerius', $actors)
        );
        $this->assertSame('', chimRolemasterBoredListenerRequirement('RANGROO', $actors));
        $this->assertSame('', chimRolemasterBoredListenerRequirement('everyone', $actors));
    }

    public function testBoredEventRulesRenderPromptManagerPlaceholders(): void
    {
        $actors = chimRolemasterBoredActorMap(
            '|Camilla Valerius|Lucan Valerius|',
            'RANGROO',
            'Camilla Valerius'
        );

        $rules = chimRolemasterRenderBoredEventRules(
            chimRolemasterDefaultBoredEventRules(),
            'Camilla Valerius',
            'RANGROO',
            $actors
        );

        $this->assertStringContainsString(
            'The first instruction must use the selected initiating actor: Camilla Valerius.',
            $rules
        );
        $this->assertStringContainsString(
            'Camilla Valerius, Lucan Valerius',
            $rules
        );
        $this->assertStringContainsString(
            'Do not target or comment on RANGROO',
            $rules
        );
        $this->assertStringNotContainsString('{SEED_ACTOR_RULE}', $rules);
        $this->assertStringNotContainsString('{NEARBY_ACTORS}', $rules);
        $this->assertStringNotContainsString('{PLAYER_NAME}', $rules);
    }

    public function testBoredEventRulesAllowCustomPromptWithoutSeed(): void
    {
        $rules = chimRolemasterRenderBoredEventRules(
            "Actors: {NEARBY_ACTORS}\n{SEED_ACTOR_RULE}\nPlayer: {PLAYER_NAME}",
            '',
            'RANGROO',
            ['lucan valerius' => 'Lucan Valerius']
        );

        $this->assertSame("Actors: Lucan Valerius\n\nPlayer: RANGROO", $rules);
    }
}
