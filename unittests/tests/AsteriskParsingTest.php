<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."logger.php");

@define("MAXIMUM_SENTENCE_SIZE", 125);
@define("MINIMUM_SENTENCE_SIZE", 75);

require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."chat_helper_functions.php");

final class AsteriskParsingTest extends TestCase
{
    protected function tearDown(): void
    {
        unset(
            $GLOBALS['HERIKA_NAME'],
            $GLOBALS['PLAYER_NAME'],
            $GLOBALS['INLINE_NARRATION_MODE'],
            $GLOBALS['INLINE_NARRATION_ENABLED'],
            $GLOBALS['REMOVE_PLAYER_AUTOCHAT_ASTERISKS'],
            $GLOBALS['PLAYER_AUTOCHAT_ASTERISKS_ENABLED'],
            $GLOBALS['PRESERVE_ASTERISKS_IN_CONTEXT'],
            $GLOBALS['REMOVE_ASTERISKS_FROM_PLAYER_INPUT'],
            $GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'],
            $GLOBALS['REMOVE_ASTERISKS_FROM_OUTPUT'],
            $GLOBALS['strip_emotes_from_output'],
            $GLOBALS['PATCH_OVERRIDE_VOICE'],
            $GLOBALS['PATCH_OVERRIDE_VOICE_ID'],
            $GLOBALS['TTSFUNCTION'],
            $GLOBALS['TTS_FUNCTION'],
            $GLOBALS['CHIM_CORE_CURRENT_TTS_CONNECTOR_ID'],
            $GLOBALS['PATCH_OVERRIDE_TTS_LANGUAGE'],
            $GLOBALS['PATCH_OVERRIDE_TTS_OPTIONS'],
            $GLOBALS['CHIM_EXECUTION_MODE'],
            $GLOBALS['TTS']
        );
    }

    public function testChatModeShortcutsAreParsedServerSide(): void
    {
        $cases = [
            '| Keep this quiet' => ['WHISPER', 'Keep this quiet'],
            '|| Only you should hear this' => ['CLOSE', 'Only you should hear this'],
            '!! Everyone, run!' => ['SHOUT', 'Everyone, run!'],
            '@ Describe the room' => ['NARRATOR', 'Describe the room'],
            '> Have Lydia inspect the doorway' => ['DIRECTOR', 'Have Lydia inspect the doorway'],
            '# Give me 1000 gold' => ['CHEATMODE', 'Give me 1000 gold'],
            '** Warn them about the dragon' => ['AUTOCHAT', 'Warn them about the dragon'],
            '((A dragon lands nearby.))' => ['INJECTION_LOG', 'A dragon lands nearby.'],
            '(A dragon lands nearby.)' => ['INJECTION_CHAT', 'A dragon lands nearby.'],
        ];

        foreach ($cases as $input => [$mode, $content]) {
            $parsed = chimParseChatModeShortcut($input);

            $this->assertTrue($parsed['matched'], $input);
            $this->assertSame($mode, $parsed['mode'], $input);
            $this->assertSame($content, $parsed['content'], $input);
        }
    }

    public function testChatModeShortcutParserPreservesPlainAndSymbolOnlyInput(): void
    {
        $plain = chimParseChatModeShortcut('Hello there');
        $symbolOnly = chimParseChatModeShortcut('||   ');

        $this->assertFalse($plain['matched']);
        $this->assertSame('Hello there', $plain['content']);
        $this->assertTrue($symbolOnly['matched']);
        $this->assertSame('CLOSE', $symbolOnly['mode']);
        $this->assertSame('', $symbolOnly['content']);
    }

    public function testFullWrappedNarrationBlockDoesNotSplitMidReply(): void
    {
        $wrappedReply = "*A slight chuckle escapes me as I straighten a few more apples, my eyes crinkling at the corners. 'Wow,' you say? I hope that's a good 'wow,' Your Majesty. My produce is usually met with enthusiasm for its quality, not surprise. Though, I suppose a king might have seen grander displays of... apples.*";

        $splitPoint = findFastSentencePosition($wrappedReply);
        $chunks = split_sentences_stream($wrappedReply);
        $parts = extractNarrationAndDialogue($chunks[0]);

        $this->assertFalse($splitPoint);
        $this->assertCount(1, $chunks);
        $this->assertTrue($parts['has_narration']);
        $this->assertSame('', $parts['dialogue']);
        $this->assertSame(
            "A slight chuckle escapes me as I straighten a few more apples, my eyes crinkling at the corners. 'Wow,' you say? I hope that's a good 'wow,' Your Majesty. My produce is usually met with enthusiasm for its quality, not surprise. Though, I suppose a king might have seen grander displays of... apples.",
            $parts['narrations'][0]
        );
    }

    public function testFullWrappedMixedReplySplitsNarrationFromDialogueWhenSpeechCueAppears(): void
    {
        $wrappedReply = "*A satisfied sigh escapes her lips as she surveys the fallen undead. Indeed, my Lord. A rather efficient clearing, if I do say so myself*";

        $parts = extractNarrationAndDialogue($wrappedReply);

        $this->assertTrue($parts['has_narration']);
        $this->assertSame(
            ['A satisfied sigh escapes her lips as she surveys the fallen undead.'],
            $parts['narrations']
        );
        $this->assertSame(
            'Indeed, my Lord. A rather efficient clearing, if I do say so myself',
            $parts['dialogue']
        );
    }

    public function testFullWrappedReplySplitsAfterNarrationLeadWhenDialogueStartsWithIM(): void
    {
        $wrappedReply = "*My grip tightens on my bowstring, the familiar tension a welcome sensation. I am ready, my lord.*";

        $parts = extractNarrationAndDialogue($wrappedReply);

        $this->assertTrue($parts['has_narration']);
        $this->assertSame(
            ['My grip tightens on my bowstring, the familiar tension a welcome sensation.'],
            $parts['narrations']
        );
        $this->assertSame('I am ready, my lord.', $parts['dialogue']);
    }

    public function testLegacyInlineNarrationToggleFallsBackToNarratorMode(): void
    {
        $GLOBALS['INLINE_NARRATION_ENABLED'] = true;

        $this->assertSame('narrator', getInlineNarrationMode());
        $this->assertTrue(shouldSplitInlineNarration());
    }

    public function testInlineNarrationModeSupportsNpcVoiceMode(): void
    {
        $GLOBALS['INLINE_NARRATION_MODE'] = 'npc';

        $this->assertSame('npc', getInlineNarrationMode());
        $this->assertFalse(shouldSplitInlineNarration());
        $this->assertTrue(isInlineNarrationEnabled());
    }

    public function testInlineNarrationModeSupportsTextOnlyMode(): void
    {
        $GLOBALS['INLINE_NARRATION_MODE'] = 'text_only';

        $this->assertSame('text_only', getInlineNarrationMode());
        $this->assertFalse(shouldSplitInlineNarration());
        $this->assertTrue(isInlineNarrationEnabled());
    }

    public function testNarratorInlineNarrationKeepsLoadedNarratorVoice(): void
    {
        $savedSettings = [
            'tts' => ['POCKETTTS' => ['voiceid' => 'alba']],
            'has_patch_override_voice' => false,
        ];
        $GLOBALS['TTS'] = ['POCKETTTS' => ['voiceid' => 'alisenvoice']];
        $GLOBALS['PATCH_OVERRIDE_VOICE'] = 'alisenvoice';

        restoreInlineNarrationSpeakerVoiceSettings($savedSettings, 'The Narrator');

        $this->assertSame('The Narrator', $GLOBALS['HERIKA_NAME']);
        $this->assertSame('alisenvoice', $GLOBALS['PATCH_OVERRIDE_VOICE']);
        $this->assertSame('alisenvoice', $GLOBALS['TTS']['POCKETTTS']['voiceid']);
    }

    public function testNpcInlineNarrationRestoresOriginalNpcVoice(): void
    {
        $savedSettings = [
            'tts' => ['POCKETTTS' => ['voiceid' => 'lydiavoice']],
            'has_patch_override_voice' => true,
            'patch_override_voice' => 'lydiavoice',
        ];
        $GLOBALS['TTS'] = ['POCKETTTS' => ['voiceid' => 'alisenvoice']];
        $GLOBALS['PATCH_OVERRIDE_VOICE'] = 'alisenvoice';

        restoreInlineNarrationSpeakerVoiceSettings($savedSettings, 'Lydia');

        $this->assertSame('Lydia', $GLOBALS['HERIKA_NAME']);
        $this->assertSame('lydiavoice', $GLOBALS['PATCH_OVERRIDE_VOICE']);
        $this->assertSame('lydiavoice', $GLOBALS['TTS']['POCKETTTS']['voiceid']);
    }

    public function testPlayerSpeechStripsAsteriskActionBlocksWhenPlayerFilterEnabled(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_PLAYER_INPUT'] = true;
        $GLOBALS['HERIKA_NAME'] = 'Player';

        $result = unmoodSentence('*draws close* Hello there *smiles softly*');

        $this->assertSame('Hello there', $result);
    }

    public function testPlayerSpeechKeepsAsteriskActionContentWhenPlayerFilterDisabled(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_PLAYER_INPUT'] = false;
        $GLOBALS['HERIKA_NAME'] = 'Player';

        $result = unmoodSentence('*draws close* Hello there *smiles softly*');

        $this->assertSame('draws close Hello there smiles softly', $result);
    }

    public function testPlayerSpeechStripsRepeatedInlineActionBlocks(): void
    {
        $this->assertSame(
            'hello how are you',
            stripPlayerAsteriskActions('*wave* hello *wave* how *wave* are *wave* you')
        );
    }

    public function testPlayerSpeechActionOnlyBecomesSilent(): void
    {
        $this->assertSame('', stripPlayerAsteriskActions('*wave*'));
    }

    public function testPlayerSubtitleTextKeepsAsteriskActionsWhenPlayerFilterEnabled(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_PLAYER_INPUT'] = true;

        $this->assertSame(
            'hello *wave* how are you *wave*',
            formatPlayerSubtitleText('hello *wave* how are you *wave* (Talking to Jon Battle-Born)')
        );
    }

    public function testPlayerSubtitleTextKeepsAsteriskActionsWhenPlayerFilterDisabled(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_PLAYER_INPUT'] = false;

        $this->assertSame(
            'hello *wave* how are you *wave*',
            formatPlayerSubtitleText('hello *wave* how are you *wave* (Talking to Jon Battle-Born)')
        );
    }

    // Regression note: PLAYER_RESPEECH/autochat can emit leading parenthetical narration
    // and a duplicated player-name prefix (for example "(A shiver...) Rangroo: ...").
    // Keep these tests server-side so rewritten player text entering context and subtitles
    // respects inline narration mode instead of stripping narration unconditionally.
    public function testPlayerSubtitleTextStripsPlayerSpeakerPrefixAndTalkingTag(): void
    {
        $GLOBALS['PLAYER_NAME'] = 'Rangroo';

        $this->assertSame(
            "Sven. The air bites today, doesn't it?",
            formatPlayerSubtitleText("Rangroo: Sven. The air bites today, doesn't it? (Talking to Sven)")
        );
    }

    public function testPlayerSubtitleTextStripsShoutTargetTag(): void
    {
        $GLOBALS['PLAYER_NAME'] = 'Rangroo';

        $this->assertSame(
            "Hello there",
            formatPlayerSubtitleText("Rangroo: Hello there (Shouting to Corpulus Vinius)")
        );
    }

    public function testPlayerSubtitleTextStripsWhisperTargetTag(): void
    {
        $GLOBALS['PLAYER_NAME'] = 'Rangroo';

        $this->assertSame(
            "Keep this quiet",
            formatPlayerSubtitleText("Rangroo: Keep this quiet (Whispering to Corpulus Vinius)")
        );
    }

    public function testWhisperPrivatePeopleIncludesOnlyPlayerAndTarget(): void
    {
        $GLOBALS['PLAYER_NAME'] = 'Rangroo';
        $GLOBALS['CHIM_EXECUTION_MODE'] = 'WHISPER';

        $this->assertTrue(isWhisperExecutionMode());
        $this->assertSame(
            '|Rangroo|Corpulus Vinius|',
            buildWhisperPrivatePeople('Corpulus Vinius')
        );
    }

    public function testPlayerSubtitleTextStripsPrivateTargetTag(): void
    {
        $GLOBALS['PLAYER_NAME'] = 'Rangroo';

        $this->assertSame(
            "Keep this between us",
            formatPlayerSubtitleText("Rangroo: Keep this between us (Speaking privately to Corpulus Vinius)")
        );
    }

    public function testCloseModeUsesPrivateTargetAndPeopleScope(): void
    {
        $GLOBALS['PLAYER_NAME'] = 'Rangroo';
        $GLOBALS['CHIM_EXECUTION_MODE'] = 'CLOSE';

        $this->assertTrue(isCloseExecutionMode());
        $this->assertTrue(isPrivateConversationExecutionMode());
        $this->assertSame(
            '(speaking privately to Corpulus Vinius)',
            buildDialogueTargetSuffix('Corpulus Vinius')
        );
        $this->assertSame(
            '|Rangroo|Corpulus Vinius|',
            buildPrivateConversationPeople('Corpulus Vinius')
        );
    }

    public function testPrivateTagConversionAndTargetExtraction(): void
    {
        $converted = convertTalkingTagsToPrivately(
            'Rangroo: Keep this quiet (Talking to Corpulus Vinius)'
        );
        $this->assertSame(
            'Rangroo: Keep this quiet (Speaking privately to Corpulus Vinius)',
            $converted
        );

        $metadata = extractTalkTargetMetadata($converted);
        $this->assertTrue($metadata['hasExplicitTarget']);
        $this->assertSame(['Corpulus Vinius'], $metadata['targets']);
    }

    public function testSanitizePlayerRespeechTextStripsLeadingNarrationAndPlayerPrefix(): void
    {
        $GLOBALS['PLAYER_NAME'] = 'Rangroo';
        $GLOBALS['INLINE_NARRATION_MODE'] = 'narrator';
        $GLOBALS['REMOVE_PLAYER_AUTOCHAT_ASTERISKS'] = true;

        $this->assertSame(
            "Sven. The air bites today, doesn't it? (Talking to Sven)",
            sanitizePlayerRespeechText(
                "(A shiver runs down Rangroo's spine, despite his heavy furs.) Rangroo: Sven. The air bites today, doesn't it? (Talking to Sven)",
                $GLOBALS['PLAYER_NAME']
            )
        );
    }

    public function testSanitizePlayerRespeechTextStripsLeadingInlineAsterisksWhenDisabled(): void
    {
        $GLOBALS['PLAYER_NAME'] = 'Rangroo';
        $GLOBALS['REMOVE_PLAYER_AUTOCHAT_ASTERISKS'] = true;

        $this->assertSame(
            "Sven. The air bites today, doesn't it? (Talking to Sven)",
            sanitizePlayerRespeechText(
                "*A shiver runs down Rangroo's spine, despite his heavy furs.* Rangroo: Sven. The air bites today, doesn't it? (Talking to Sven)",
                $GLOBALS['PLAYER_NAME']
            )
        );
    }

    public function testSanitizePlayerRespeechTextUnwrapsEchoedAutochatMarker(): void
    {
        $GLOBALS['PLAYER_NAME'] = 'Anna';
        $GLOBALS['REMOVE_PLAYER_AUTOCHAT_ASTERISKS'] = true;

        $this->assertSame(
            "Oh, yeah... I feel very relaxed!",
            sanitizePlayerRespeechText(
                "Anna:**(Oh, yeah... I feel very relaxed!)",
                $GLOBALS['PLAYER_NAME']
            )
        );

        $this->assertSame(
            "Oh, yeah... I feel very relaxed!",
            sanitizePlayerRespeechText(
                "Anna: **(Oh, yeah... I feel very relaxed!)**",
                $GLOBALS['PLAYER_NAME']
            )
        );

        $this->assertSame(
            "Oh (yeah)... I feel very relaxed!",
            sanitizePlayerRespeechText(
                "Anna: **(Oh (yeah)... I feel very relaxed!)**",
                $GLOBALS['PLAYER_NAME']
            )
        );
    }

    public function testSanitizePlayerRespeechTextStripsLeadingDoubleStarNarration(): void
    {
        $GLOBALS['PLAYER_NAME'] = 'Anna';
        $GLOBALS['REMOVE_PLAYER_AUTOCHAT_ASTERISKS'] = true;

        $this->assertSame(
            "Oh, yeah... I feel very relaxed!",
            sanitizePlayerRespeechText(
                "Anna: **(smiles softly)** Oh, yeah... I feel very relaxed!",
                $GLOBALS['PLAYER_NAME']
            )
        );
    }

    public function testSanitizePlayerRespeechTextConvertsLeadingNarrationToInlineAsterisksWhenEnabled(): void
    {
        $GLOBALS['PLAYER_NAME'] = 'Rangroo';
        $GLOBALS['INLINE_NARRATION_MODE'] = 'disabled';
        $GLOBALS['REMOVE_PLAYER_AUTOCHAT_ASTERISKS'] = false;

        $this->assertSame(
            "*A shiver runs down Rangroo's spine, despite his heavy furs.* Sven. The air bites today, doesn't it? (Talking to Sven)",
            sanitizePlayerRespeechText(
                "(A shiver runs down Rangroo's spine, despite his heavy furs.) Rangroo: Sven. The air bites today, doesn't it? (Talking to Sven)",
                $GLOBALS['PLAYER_NAME']
            )
        );
    }

    public function testNpcSpeechFiltersKnownAsteriskEmotesWhenNpcOutputFilterEnabled(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'] = true;
        $GLOBALS['HERIKA_NAME'] = 'Carlotta Valentia';

        $result = unmoodSentence('*smiles* Hello there');

        $this->assertSame('Hello there', $result);
    }

    public function testNpcSpeechKeepsAsteriskActionContentWhenNpcOutputFilterDisabled(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'] = false;
        $GLOBALS['HERIKA_NAME'] = 'Carlotta Valentia';

        $result = unmoodSentence('*smiles* Hello there');

        $this->assertSame('smiles Hello there', $result);
    }

    public function testNpcSpeechKeepsInlineEmphasisTextWhenNpcOutputFilterEnabled(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'] = true;
        $GLOBALS['HERIKA_NAME'] = 'Carlotta Valentia';

        $result = unmoodSentence("You find *my* humble produce stall 'wow-worthy,' Your Majesty?");

        $this->assertSame("You find my humble produce stall 'wow-worthy,' Your Majesty?", $result);
    }

    public function testNpcSubtitleFiltersAsteriskTextWhenNpcOutputFilterEnabled(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'] = true;
        $GLOBALS['HERIKA_NAME'] = 'Carlotta Valentia';

        $this->assertSame('Hello there', formatNpcSubtitleText('*smiles* Hello there'));
    }

    public function testNpcSubtitlePreservesAsteriskTextWhenNpcOutputFilterDisabled(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'] = false;
        $GLOBALS['HERIKA_NAME'] = 'Carlotta Valentia';

        $this->assertSame('*smiles* Hello there', formatNpcSubtitleText('*smiles* Hello there'));
    }

    public function testTextOnlyNarrationKeepsSubtitleAndSpeaksOnlyDialogue(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'] = true;
        $GLOBALS['HERIKA_NAME'] = 'Carlotta Valentia';
        $line = '*She smiles softly* Hello *my* friend';

        $this->assertSame($line, formatTextOnlyInlineNarrationSubtitleText($line));
        $this->assertSame('Hello my friend', formatTextOnlyInlineNarrationSpeechText($line));
    }

    public function testTextOnlyNarrationOnlyLineProducesNoSpeech(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'] = true;
        $GLOBALS['HERIKA_NAME'] = 'Carlotta Valentia';
        $line = '*She looks toward the door*';

        $this->assertSame($line, formatTextOnlyInlineNarrationSubtitleText($line));
        $this->assertSame('', formatTextOnlyInlineNarrationSpeechText($line));
    }

    public function testInlineNarrationDialogueSubtitleRespectsNpcOutputFilter(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'] = true;
        $GLOBALS['HERIKA_NAME'] = 'Carlotta Valentia';

        $this->assertSame('Hello my friend', formatInlineNarrationDialogueSubtitleText('. Hello *my* friend'));
    }

    public function testInlineNarrationDialogueSubtitleKeepsAsteriskTextWhenNpcOutputFilterDisabled(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'] = false;
        $GLOBALS['HERIKA_NAME'] = 'Carlotta Valentia';

        $this->assertSame('Hello *my* friend', formatInlineNarrationDialogueSubtitleText('. Hello *my* friend'));
    }

    public function testInlineNarrationDialogueSubtitleKeepsLeadingEmphasisWhenNpcOutputFilterDisabled(): void
    {
        $GLOBALS['REMOVE_ASTERISKS_FROM_NPC_OUTPUT'] = false;
        $GLOBALS['HERIKA_NAME'] = 'Carlotta Valentia';

        $this->assertSame('*My* friend', formatInlineNarrationDialogueSubtitleText('*My* friend'));
    }

    public function testNarrationSubtitleFormatterWrapsNarrationInSingleAsterisks(): void
    {
        $this->assertSame('*She smiles softly*', formatNarrationSubtitleText('She smiles softly'));
    }

    public function testCleanContextFirstPassKeepsAsterisksWhenNarratorModeEnabled(): void
    {
        $GLOBALS['INLINE_NARRATION_MODE'] = 'narrator';
        $GLOBALS['PRESERVE_ASTERISKS_IN_CONTEXT'] = false;

        $this->assertFalse(shouldStripAsterisksFromCleanContextBuffer());
    }

    public function testCleanContextFirstPassKeepsAsterisksWhenNpcModeEnabled(): void
    {
        $GLOBALS['INLINE_NARRATION_MODE'] = 'npc';
        $GLOBALS['PRESERVE_ASTERISKS_IN_CONTEXT'] = false;

        $this->assertFalse(shouldStripAsterisksFromCleanContextBuffer());
    }

    public function testCleanContextFirstPassKeepsAsterisksWhenContextPreservationIsEnabled(): void
    {
        $GLOBALS['INLINE_NARRATION_MODE'] = 'disabled';
        $GLOBALS['PRESERVE_ASTERISKS_IN_CONTEXT'] = true;

        $this->assertFalse(shouldStripAsterisksFromCleanContextBuffer());
    }

    public function testCleanContextFirstPassStripsAsterisksOnlyWhenNarrationIsDisabledAndNotPreserved(): void
    {
        $GLOBALS['INLINE_NARRATION_MODE'] = 'disabled';
        $GLOBALS['PRESERVE_ASTERISKS_IN_CONTEXT'] = false;

        $this->assertTrue(shouldStripAsterisksFromCleanContextBuffer());
    }

    public function testApplyVoiceIdToTtsGlobalsSetsNarratorOverrideAndClearsSpeakerId(): void
    {
        $GLOBALS['PATCH_OVERRIDE_VOICE_ID'] = 12;

        applyVoiceIdToTtsGlobals('TheNarrator');

        $this->assertSame('TheNarrator', $GLOBALS['PATCH_OVERRIDE_VOICE']);
        $this->assertFalse(array_key_exists('PATCH_OVERRIDE_VOICE_ID', $GLOBALS));
        $this->assertSame('TheNarrator', $GLOBALS['TTS']['XTTSFASTAPI']['voiceid']);
        $this->assertSame('TheNarrator', $GLOBALS['TTS']['PIPERTTS']['voiceid']);
    }

    public function testRestoreVoiceSettingsRestoresNpcVoiceOverridesAfterNarratorSwap(): void
    {
        $GLOBALS['TTS'] = [
            'XTTSFASTAPI' => ['voiceid' => 'lydia'],
            'PIPERTTS' => ['voiceid' => 'lydia', 'speaker_id' => 9],
        ];
        $GLOBALS['PATCH_OVERRIDE_VOICE'] = 'lydia';
        $GLOBALS['PATCH_OVERRIDE_VOICE_ID'] = 9;

        $savedSettings = saveCurrentVoiceSettings();

        applyVoiceIdToTtsGlobals('TheNarrator');
        restoreVoiceSettings($savedSettings);

        $this->assertSame('lydia', $GLOBALS['PATCH_OVERRIDE_VOICE']);
        $this->assertSame(9, $GLOBALS['PATCH_OVERRIDE_VOICE_ID']);
        $this->assertSame('lydia', $GLOBALS['TTS']['XTTSFASTAPI']['voiceid']);
        $this->assertSame('lydia', $GLOBALS['TTS']['PIPERTTS']['voiceid']);
    }
}
