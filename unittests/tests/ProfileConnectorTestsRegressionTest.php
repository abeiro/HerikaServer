<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProfileConnectorTestsRegressionTest extends TestCase
{
    public function testLlmHealthCheckInitializesCommandPrompt(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'ui' . DIRECTORY_SEPARATOR . 'api' .
            DIRECTORY_SEPARATOR . 'profile_connector_tests.php'
        );

        $start = strpos($source, 'function profileConnectorTestsTestLlm');
        $end = strpos($source, 'function profileConnectorTestsTestTts', $start);
        $llmTestFunction = substr($source, $start, $end - $start);

        $this->assertStringContainsString('$GLOBALS["COMMAND_PROMPT"] = \'\';', $llmTestFunction);
    }
}
