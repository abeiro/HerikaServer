<?php

use PHPUnit\Framework\TestCase;

final class BackgroundLifeDiaryPersistenceTest extends TestCase
{
    public function testMemoryUsesTheSameDiaryCooldownGuardAsDiaryLog(): void
    {
        $source = file_get_contents(__DIR__ . '/../../debug/simple_llm_request_with_context_life_v2.php');

        self::assertIsString($source);

        $guardStart = strrpos($source, 'if ($recordDiaryEntry) {');
        $nextSection = strpos(
            $source,
            '$currentNpcData = $npcMaster->getByName($npcName);',
            $guardStart
        );

        self::assertNotFalse($guardStart);
        self::assertNotFalse($nextSection);

        $guardedPersistence = substr($source, $guardStart, $nextSection - $guardStart);

        self::assertStringContainsString("'topic' => 'Journal Note'", $guardedPersistence);
        self::assertStringContainsString("logMemory(\$GLOBALS['HERIKA_NAME']", $guardedPersistence);
    }
}
