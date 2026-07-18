<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'tts' . DIRECTORY_SEPARATOR . 'tts-omnivoice.php');

final class OmniVoiceLanguageTest extends TestCase
{
    private array $originalGlobals = [];

    protected function setUp(): void
    {
        foreach (['PATCH_OVERRIDE_TTS_LANGUAGE', 'LANG_LLM_XTTS', 'LLM_LANG', 'TTS'] as $key) {
            $this->originalGlobals[$key] = [
                'exists' => array_key_exists($key, $GLOBALS),
                'value' => $GLOBALS[$key] ?? null,
            ];
            unset($GLOBALS[$key]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalGlobals as $key => $original) {
            if ($original['exists']) {
                $GLOBALS[$key] = $original['value'];
            } else {
                unset($GLOBALS[$key]);
            }
        }
    }

    public function testConfiguredLanguageIgnoresLegacyLlmLanguageSelection(): void
    {
        $GLOBALS['LANG_LLM_XTTS'] = true;
        $GLOBALS['LLM_LANG'] = 'en';
        $GLOBALS['TTS']['OMNIVOICE']['language'] = 'ja';

        $this->assertSame('ja', omnivoice_resolve_language());
    }

    public function testExplicitLanguageOverrideStillWins(): void
    {
        $GLOBALS['PATCH_OVERRIDE_TTS_LANGUAGE'] = 'pt-BR';
        $GLOBALS['TTS']['OMNIVOICE']['language'] = 'ja';

        $this->assertSame('pt-br', omnivoice_resolve_language());
    }

    public function testDevelopmentLanguageOverrideStillWins(): void
    {
        $GLOBALS['TTS']['FORCED_LANG_DEV'] = 'de';
        $GLOBALS['TTS']['OMNIVOICE']['language'] = 'ja';

        $this->assertSame('de', omnivoice_resolve_language());
    }
}
