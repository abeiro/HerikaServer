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

    public function testCurrentLocationContextIsAlwaysAvailableWithoutAnEnableSetting(): void
    {
        $GLOBALS['db'] = new class {
            public function execQuery(string $query): bool
            {
                return true;
            }

            public function escapeLiteral($value): string
            {
                return "'" . str_replace("'", "''", strval($value)) . "'";
            }

            public function fetchOne(string $query): array
            {
                return [];
            }

            public function fetchAll(string $query): array
            {
                if (strpos($query, 'FROM public.visual_context') === false) {
                    return [];
                }

                return [[
                    'subject_type' => 'scene',
                    'subject_name' => 'Riverwood square',
                    'description' => 'Lantern light falls across the wet road.',
                    'captured_at' => '2026-07-19 12:00:00+00',
                ]];
            }
        };

        try {
            $prompt = chimBuildVisualContextPrompt('Riverwood');
            $this->assertStringContainsString('<visual_context>', $prompt);
            $this->assertStringContainsString('Lantern light falls across the wet road.', $prompt);
        } finally {
            unset($GLOBALS['db']);
        }
    }
}
