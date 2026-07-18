<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..'
    . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'narrator.class.php';

final class NarratorRoleplayNameTest extends TestCase
{
    private array $savedGlobals = [];

    protected function setUp(): void
    {
        foreach (['HERIKA_NAME', 'NARRATOR_ROLEPLAY_NAME'] as $key) {
            $this->savedGlobals[$key] = array_key_exists($key, $GLOBALS)
                ? ['exists' => true, 'value' => $GLOBALS[$key]]
                : ['exists' => false, 'value' => null];
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->savedGlobals as $key => $state) {
            if ($state['exists']) {
                $GLOBALS[$key] = $state['value'];
            } else {
                unset($GLOBALS[$key]);
            }
        }
    }

    public function testRoleplayNameDefaultsAndWhitespaceNormalization(): void
    {
        $this->assertSame('The Narrator', Narrator::normalizeRoleplayName(''));
        $this->assertSame('The Narrator', Narrator::normalizeRoleplayName("  \t\n"));
        $this->assertSame("Mara's Voice 2", Narrator::normalizeRoleplayName("  Mara's   Voice 2  "));
    }

    #[DataProvider('invalidRoleplayNameProvider')]
    public function testRoleplayNameRejectsUnsafeOrReservedValues(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        Narrator::normalizeRoleplayName($name);
    }

    public static function invalidRoleplayNameProvider(): array
    {
        return [
            'markup' => ['<Mercy>'],
            'routing delimiter' => ['Mercy|Voice'],
            'reserved player' => ['Player'],
            'reserved everyone' => ['everyone'],
            'too long' => [str_repeat('A', 65)],
        ];
    }

    public function testPromptNameChangesOnlyForCanonicalNarrator(): void
    {
        $GLOBALS['NARRATOR_ROLEPLAY_NAME'] = 'Mercy';
        $GLOBALS['HERIKA_NAME'] = 'The Narrator';
        $this->assertSame('Mercy', chimGetPromptCharacterName());

        $GLOBALS['HERIKA_NAME'] = 'Lydia';
        $this->assertSame('Lydia', chimGetPromptCharacterName());
        $this->assertSame('Mercy', chimGetNarratorRoleplayName());
    }

    public function testNarratorFieldsUseAliasWithoutChangingCanonicalRouting(): void
    {
        $GLOBALS['NARRATOR_ROLEPLAY_NAME'] = 'Mercy';

        $this->assertSame(
            'Mercy watches over the player.',
            chimRenderNarratorRoleplayText('The Narrator watches over the player.')
        );
        $this->assertSame('The Narrator', chimNormalizeNarratorRoleplayActorName('Mercy'));
        $this->assertSame('Lydia', chimNormalizeNarratorRoleplayActorName('Lydia'));
    }

    public function testDisplayNameHeaderValueIsTransportSafe(): void
    {
        $GLOBALS['NARRATOR_ROLEPLAY_NAME'] = 'The Dude';

        $this->assertSame('The Dude', base64_decode(chimGetNarratorDisplayNameHeaderValue(), true));
        $this->assertSame('The Dude: action completed', chimBuildNarratorContextLine('action completed'));
    }

    public function testContextAliasingChangesEveryNarratorIdentityReference(): void
    {
        $GLOBALS['NARRATOR_ROLEPLAY_NAME'] = 'Mercy';
        $context = "The Narrator: Welcome.\nLydia: They call him The Narrator. (Talking to The Narrator)\n"
            . '{"character":"The Narrator","listener":"The Narrator","message":"Ask The Narrator."}';

        $this->assertSame(
            "Mercy: Welcome.\nLydia: They call him Mercy. (Talking to Mercy)\n"
                . '{"character":"Mercy","listener":"Mercy","message":"Ask Mercy."}',
            chimRenderNarratorContextText($context)
        );

        $messages = chimApplyNarratorRoleplayNameToContext([
            ['role' => 'user', 'content' => 'The Narrator: Continue.'],
            ['role' => 'assistant', 'content' => 'Unchanged prose about The Narrator.'],
        ]);
        $this->assertSame('Mercy: Continue.', $messages[0]['content']);
        $this->assertSame('Unchanged prose about Mercy.', $messages[1]['content']);
    }
}
