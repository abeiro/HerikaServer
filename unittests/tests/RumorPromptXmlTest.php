<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."lazy_xml.php");

final class RumorPromptXmlTest extends TestCase
{
    // Regression note: runtime rumor injection used to emit type-named tags like
    // <crime>...</crime>, which made the prompt schema inconsistent with the rest of
    // the repo and harder to parse. Keep rumors wrapped in a stable <rumor> block.
    public function testBuildRumorPromptXmlUsesStableRumorWrapper(): void
    {
        $xml = build_rumor_prompt_xml([
            [
                'hold' => 'Winterhold',
                'type' => 'crime',
                'content' => 'RANGROO is wanted for stealing a polar bear from the college of winterhold.',
            ],
        ]);

        $this->assertStringContainsString('<rumor>', $xml);
        $this->assertStringContainsString('<type>crime</type>', $xml);
        $this->assertStringContainsString('<location>Winterhold</location>', $xml);
        $this->assertStringContainsString(
            '<content>RANGROO is wanted for stealing a polar bear from the college of winterhold.</content>',
            $xml
        );
        $this->assertStringNotContainsString('<crime>', $xml);
    }

    public function testBuildRumorPromptXmlDeduplicatesEquivalentRumors(): void
    {
        $xml = build_rumor_prompt_xml([
            [
                'hold' => 'Winterhold',
                'type' => 'crime',
                'content' => 'RANGROO is wanted for stealing a polar bear from the college of winterhold.',
            ],
            [
                'hold' => 'Winterhold',
                'type' => 'crime',
                'content' => 'Rangroo is wanted for stealing a polar bear from the college of winterhold.',
            ],
            [
                'hold' => 'Winterhold',
                'type' => 'crime',
                'content' => 'RANGROO is wanted for stealing a polar bear from the college of winterhold.',
            ],
        ]);

        $this->assertSame(1, substr_count($xml, '<rumor>'));
        $this->assertSame(1, substr_count($xml, '<type>crime</type>'));
        $this->assertSame(1, substr_count($xml, '<location>Winterhold</location>'));
    }
}
