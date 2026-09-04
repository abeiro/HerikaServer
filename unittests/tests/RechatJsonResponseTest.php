<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/logger.php';
Logger::setCustomLog(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'chim-rechat-json-response-test.log');
$rechatJsonBootstrapKeys = [
    'HERIKA_NAME',
    'PLAYER_NAME',
    'EMOTEMOODS',
    'TTSFUNCTION',
    'TTS',
    'FEATURES',
    'FUNCTIONS_ARE_ENABLED',
];
$rechatJsonBootstrapState = [];
foreach ($rechatJsonBootstrapKeys as $bootstrapKey) {
    $rechatJsonBootstrapState[$bootstrapKey] = [
        'exists' => array_key_exists($bootstrapKey, $GLOBALS),
        'value' => $GLOBALS[$bootstrapKey] ?? null,
    ];
}
$GLOBALS['HERIKA_NAME'] = 'Jaryra';
$GLOBALS['PLAYER_NAME'] = 'Player';
$GLOBALS['EMOTEMOODS'] = 'neutral';
$GLOBALS['TTSFUNCTION'] = '';
$GLOBALS['TTS'] = [];
$GLOBALS['FEATURES'] = ['MISC' => ['JSON_DIALOGUE_FORMAT_REORDER' => false]];
$GLOBALS['FUNCTIONS_ARE_ENABLED'] = false;
require_once __DIR__ . '/../../lib/chat_helper_functions.php';
require_once __DIR__ . '/../../functions/json_response.php';
foreach ($rechatJsonBootstrapState as $bootstrapKey => $bootstrapState) {
    if ($bootstrapState['exists']) {
        $GLOBALS[$bootstrapKey] = $bootstrapState['value'];
    } else {
        unset($GLOBALS[$bootstrapKey]);
    }
}
unset($rechatJsonBootstrapKeys, $rechatJsonBootstrapState, $bootstrapKey, $bootstrapState);

final class RechatJsonResponseTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['HERIKA_NAME'] = 'Jaryra';
        $GLOBALS['PLAYER_NAME'] = 'Player';
        $GLOBALS['EMOTEMOODS'] = 'neutral';
        $GLOBALS['FUNC_LIST'] = ['Talk'];
        $GLOBALS['TTSFUNCTION'] = '';
        $GLOBALS['TTS'] = [];
        $GLOBALS['FEATURES'] = ['MISC' => ['JSON_DIALOGUE_FORMAT_REORDER' => false]];
        $GLOBALS['FUNCTIONS_ARE_ENABLED'] = false;
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['gameRequest'],
            $GLOBALS['RECHAT_RESOLVED_TARGET'],
            $GLOBALS['HERIKA_NAME'],
            $GLOBALS['PLAYER_NAME'],
            $GLOBALS['EMOTEMOODS'],
            $GLOBALS['FUNC_LIST'],
            $GLOBALS['TTSFUNCTION'],
            $GLOBALS['TTS'],
            $GLOBALS['FEATURES'],
            $GLOBALS['FUNCTIONS_ARE_ENABLED'],
            $GLOBALS['responseTemplate'],
            $GLOBALS['structuredOutputTemplate'],
            $GLOBALS['grammar']
        );
    }

    private function buildResponseFormats(string $rechatMode): void
    {
        $GLOBALS['gameRequest'] = ['rechat'];
        $GLOBALS['RECHAT_RESOLVED_TARGET'] = ['mode' => $rechatMode];
        $GLOBALS['responseTemplate'] = [];
        $GLOBALS['structuredOutputTemplate'] = [];
        $GLOBALS['grammar'] = '';

        setResponseTemplate();
        setStructuredOutputTemplate();
        setGBNFGrammar();
    }

    public function testConversationalRechatAddsSpeakerWeightsToEveryJsonFormat(): void
    {
        $this->buildResponseFormats('conversational');

        $this->assertArrayHasKey('speaker_weights', $GLOBALS['responseTemplate']);
        $this->assertSame(
            'array',
            $GLOBALS['structuredOutputTemplate']['json_schema']['schema']['properties']['speaker_weights']['type']
        );
        $this->assertContains(
            'speaker_weights',
            $GLOBALS['structuredOutputTemplate']['json_schema']['schema']['required']
        );
        $this->assertStringContainsString('root-speaker-weights', $GLOBALS['grammar']);
        $this->assertStringContainsString('"," ws root-speaker-weights', $GLOBALS['grammar']);
        $this->assertStringNotContainsString('{$SPEAKER_WEIGHTS}', $GLOBALS['grammar']);
    }

    public function testNonConversationalRechatKeepsExistingJsonShape(): void
    {
        $this->buildResponseFormats('tight');

        $this->assertArrayNotHasKey('speaker_weights', $GLOBALS['responseTemplate']);
        $this->assertArrayNotHasKey(
            'speaker_weights',
            $GLOBALS['structuredOutputTemplate']['json_schema']['schema']['properties']
        );
        $rootRule = strtok($GLOBALS['grammar'], "\n");
        $this->assertIsString($rootRule);
        $this->assertStringNotContainsString('root-speaker-weights', $rootRule);
        $this->assertStringNotContainsString('{$SPEAKER_WEIGHTS}', $GLOBALS['grammar']);
    }
}
