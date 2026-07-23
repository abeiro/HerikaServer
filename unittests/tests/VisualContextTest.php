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

    public function testLocationBaseNormalizesEventAndStoredFormats(): void
    {
        $this->assertSame(
            'Riverwood outdoors',
            chimVisualContextLocationBase("(Context location: Riverwood outdoors ,Hold: Whiterun, Buildings to go:Riverwood Trader)")
        );
        $this->assertSame(
            'Riverwood outdoors',
            chimVisualContextLocationBase('Riverwood outdoors ,Hold: Whiterun')
        );
    }

    public function testGalleryFilenameUsesLocationAndSkyrimTime(): void
    {
        $this->assertSame(
            'Riverwood_outdoors__Tirdas_7_19_AM_19th_of_Last_Seed_4E_201.jpg',
            chimVisualContextGalleryFilename(
                'Riverwood outdoors ,Hold: Whiterun',
                'Tirdas, 7:19 AM, 19th of Last Seed, 4E 201'
            )
        );
    }

    public function testCurrentLocationContextIsAlwaysAvailableWithoutAnEnableSetting(): void
    {
        $db = new class {
            public string $lastQuery = '';

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
                $this->lastQuery = $query;
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
        $GLOBALS['db'] = $db;

        try {
            $prompt = chimBuildVisualContextPrompt('(Context location: Riverwood, Hold: Whiterun)');
            $this->assertStringContainsString('<visual_context>', $prompt);
            $this->assertStringContainsString('Lantern light falls across the wet road.', $prompt);
            $this->assertStringContainsString("LOWER('Riverwood')", $db->lastQuery);
        } finally {
            unset($GLOBALS['db']);
        }
    }
}
