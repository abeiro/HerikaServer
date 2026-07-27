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
}
