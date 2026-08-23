<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/chat_helper_functions.php';

final class PlayerPresenceSnapshotTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['CHIM_TURN_PRESENT_ACTORS_SNAPSHOT']);
    }

    public function testPresenceIsDecodedSeparatelyFromManagedAudience(): void
    {
        $payload = [
            'source' => 'plugin_player_routing_v2',
            'companions' => ['Lydia', 'RANGROO'],
            'present_actors' => [
                [
                    'form_id' => 0x100,
                    'name' => 'Alvor',
                    'distance' => 120.0,
                    'managed' => false,
                    'creature' => false,
                ],
                [
                    'form_id' => 0x101,
                    'name' => 'Chicken',
                    'distance' => 130.0,
                    'managed' => false,
                    'creature' => true,
                ],
                [
                    'form_id' => 0x102,
                    'name' => 'chicken',
                    'distance' => 140.0,
                    'managed' => false,
                    'creature' => true,
                ],
                [
                    'form_id' => 0x103,
                    'name' => 'Lydia',
                    'distance' => 150.0,
                    'managed' => true,
                    'creature' => false,
                ],
            ],
        ];
        $encoded = base64_encode((string)json_encode($payload));

        $snapshot = chimDecodePlayerRoutingSnapshotField($encoded);

        $this->assertSame('|Lydia|RANGROO|', $snapshot['audience']);
        $this->assertSame(['Alvor', 'Chicken', 'Lydia'], array_column($snapshot['present_actors'], 'name'));
        $this->assertSame('|Alvor|Chicken|Lydia|', chimPresentActorsPeoplePipe($snapshot['present_actors']));
        $this->assertSame(
            '|Lydia|RANGROO|Alvor|Chicken|',
            chimMergePeoplePipeLists($snapshot['audience'], chimPresentActorsPeoplePipe($snapshot['present_actors']))
        );
    }

    public function testPresencePromptAllowsUnmanagedActorActionTargets(): void
    {
        chimSetCurrentTurnPresentActorsSnapshot([
            ['form_id' => 0x13475, 'name' => 'Alvor', 'managed' => false],
            ['form_id' => 0xA2C8E, 'name' => 'Lydia', 'managed' => true],
        ]);

        $prompt = chimBuildCurrentTurnPresentPeoplePrompt();

        $this->assertStringContainsString('<people_present>', $prompt);
        $this->assertStringContainsString('## Alvor [RefID: 00013475] (present, not CHIM-active)', $prompt);
        $this->assertStringContainsString('## Lydia [RefID: 000A2C8E]', $prompt);
        $this->assertStringContainsString('cannot respond, but may be targeted by gameplay actions', $prompt);
        $this->assertStringContainsString('Prefer the displayed RefID', $prompt);
    }

    public function testLegacyAudienceSnapshotRemainsSupported(): void
    {
        $encoded = base64_encode((string)json_encode([
            'people' => '|Lydia|RANGROO|',
        ]));

        $snapshot = chimDecodePlayerRoutingSnapshotField($encoded);

        $this->assertSame('|Lydia|RANGROO|', $snapshot['audience']);
        $this->assertSame([], $snapshot['present_actors']);
    }

    public function testChatShortcutRoutingMarkerIsDecodedFromRoutingSnapshot(): void
    {
        $encoded = base64_encode((string)json_encode([
            'source' => 'plugin_player_routing_v2',
            'chat_shortcut_routed' => true,
        ]));

        $snapshot = chimDecodePlayerRoutingSnapshotField($encoded);

        $this->assertTrue($snapshot['chat_shortcut_routed']);
        $this->assertSame('', $snapshot['audience']);
    }

    public function testRequestExecutionModeIsIgnored(): void
    {
        $encoded = base64_encode((string)json_encode([
            'execution_mode' => 'whisper',
        ]));

        $snapshot = chimDecodePlayerRoutingSnapshotField($encoded);

        $this->assertArrayNotHasKey('execution_mode', $snapshot);
    }

    public function testDirectivePeopleIncludeSelectedSpeakerAndExplicitListener(): void
    {
        $people = chimBuildDirectivePeoplePipe(
            '|Lisette|Vivienne Onis|RANGROO|',
            'Dorian',
            'Dorian should ask about a remedy. The dialogue listener must be Vivienne Onis. (must use ACTION JustTalk Vivienne Onis)'
        );

        $this->assertSame(
            '|Lisette|Vivienne Onis|RANGROO|Dorian|',
            $people
        );
    }

    public function testDirectivePeopleStillIncludeSpeakerWithoutExplicitListener(): void
    {
        $people = chimBuildDirectivePeoplePipe(
            '|Lisette|RANGROO|',
            'Jorn',
            'Jorn should inspect the room. (must use ACTION Inspect_Surroundings)'
        );

        $this->assertSame('|Lisette|RANGROO|Jorn|', $people);
    }

    public function testDirectivePeopleUseTalkActionTargetWhenListenerRequirementIsAbsent(): void
    {
        $people = chimBuildDirectivePeoplePipe(
            '|Lisette|RANGROO|',
            'Belrand',
            'Belrand should ask about work. (must use ACTION JustTalk Vivienne Onis)'
        );

        $this->assertSame(
            '|Lisette|RANGROO|Belrand|Vivienne Onis|',
            $people
        );
    }

    public function testDirectivePeopleDoNotAddEveryoneAsAnActor(): void
    {
        $people = chimBuildDirectivePeoplePipe(
            '|Lisette|RANGROO|',
            'Eris',
            'Eris should address the room. (must use ACTION JustTalk everyone)'
        );

        $this->assertSame('|Lisette|RANGROO|Eris|', $people);
    }

    public function testDialogueEventPeopleAlwaysIncludeActualSpeakerAndListener(): void
    {
        $people = chimBuildDialogueEventPeoplePipe(
            '|Octieve San|Lisette|RANGROO|',
            'Octieve San',
            'Minette Vinius'
        );

        $this->assertSame(
            '|Octieve San|Lisette|RANGROO|Minette Vinius|',
            $people
        );
    }
}
