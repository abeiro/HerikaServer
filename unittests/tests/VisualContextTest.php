<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'visual_context.php');

final class VisualContextTest extends TestCase
{
    public function testSubjectTypeFallsBackToScene(): void
    {
        $this->assertSame('actor', chimVisualContextSubjectType('Actor'));
        $this->assertSame('scene', chimVisualContextSubjectType('unsupported'));
    }

    public function testTextNormalizationRemovesControlCharactersAndLimitsLength(): void
    {
        $this->assertSame('abcdef', chimVisualContextText("abc\x00def", 20));
        $this->assertSame('abcd', chimVisualContextText('abcdef', 4));
    }

    public function testStructuredValuesAreSerializedWithoutArrayWarnings(): void
    {
        $this->assertSame('{"model":"vision"}', chimVisualContextText(['model' => 'vision'], 100));
    }
}

