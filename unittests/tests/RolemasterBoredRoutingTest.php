<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/rolemaster_bored.php';

final class RolemasterBoredRoutingTest extends TestCase
{
    public function testBoredEventChanceUsesZeroBasedPercentageBoundary(): void
    {
        $this->assertFalse(chimBoredEventChancePasses(0, 0));
        $this->assertTrue(chimBoredEventChancePasses(35, 34));
        $this->assertFalse(chimBoredEventChancePasses(35, 35));
        $this->assertTrue(chimBoredEventChancePasses(100, 99));
        $this->assertFalse(chimBoredEventChancePasses(100, 100));
        $this->assertFalse(chimBoredEventChancePasses(-1, 0));
        $this->assertTrue(chimBoredEventChancePasses(101, 99));
    }

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

        $this->assertCount(1, $filtered);
        $this->assertSame('Camilla Valerius', $filtered[0]['character']);
        $this->assertSame('Answer Lucan', $filtered[0]['instruction']);
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
            'Return exactly one instruction, using the selected initiating actor: Camilla Valerius.',
            $rules
        );
        $this->assertStringContainsString(
            "Do not generate the listener's reply.",
            $rules
        );
        $this->assertStringContainsString(
            'override general Director suggestions to invent new content, plot twists, foreshadowing, drama, or tension',
            $rules
        );
        $this->assertStringContainsString(
            'does not need to introduce a new topic or advance the plot',
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
