<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

$GLOBALS['ENGINE_PATH'] = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
require_once $GLOBALS['ENGINE_PATH'] . 'lib' . DIRECTORY_SEPARATOR . 'relationship_manager.php';
require_once $GLOBALS['ENGINE_PATH'] . 'ext' . DIRECTORY_SEPARATOR . 'relationship_system'
    . DIRECTORY_SEPARATOR . 'relationship_llm.php';

final class RelationshipTypeValidationTest extends TestCase
{
    public function testBuiltInTypesAndKnownAliasesAreCanonicalized(): void
    {
        $this->assertSame('romantic', RelationshipManager::canonicalizeRelationshipType('Romantic'));
        $this->assertSame('romantic', RelationshipManager::canonicalizeRelationshipType('Romance'));
        $this->assertSame('betrayed', RelationshipManager::canonicalizeRelationshipType('Betrayal'));
        $this->assertSame('enemy', RelationshipManager::canonicalizeRelationshipType('Enemies'));
    }

    public function testModelCannotCreateANewRelationshipType(): void
    {
        $this->assertNull(RelationshipManager::canonicalizeRelationshipType('soulmate'));
        $this->assertNull(RelationshipManager::canonicalizeRelationshipType('new made up type'));
        $this->assertNull(RelationshipManager::canonicalizeRelationshipType(42));
    }

    public function testExistingPlayerCustomTypeCanBeSelectedButNotRecreatedAfterRemoval(): void
    {
        $this->assertSame(
            'trusted',
            RelationshipManager::canonicalizeRelationshipType('Trusted', ['trusted'])
        );
        $this->assertNull(RelationshipManager::canonicalizeRelationshipType('trusted'));
    }

    public function testCustomTypeExtractionExcludesBuiltInsAliasesAndMalformedValues(): void
    {
        $relationships = [
            ['type' => 'trusted'],
            ['type' => 'Romance'],
            ['type' => 'enemy'],
            ['type' => 'bad type'],
        ];

        $this->assertSame(['trusted'], RelationshipManager::getCustomRelationshipTypes($relationships));
    }

    public function testRelationshipMapRepairsLegacyAliasesWithoutDestroyingCustomTypes(): void
    {
        $normalized = RelationshipManager::normalizeRelationshipMap([
            'Player' => ['aff' => 60, 'type' => 'Romance'],
            'Lydia' => ['aff' => 20, 'type' => 'Trusted'],
        ]);

        $this->assertSame('romantic', $normalized['Player']['type']);
        $this->assertSame('trusted', $normalized['Lydia']['type']);
    }

    public function testAutomaticEvaluationChanceHonorsBoundariesAndDeterministicRolls(): void
    {
        $this->assertFalse(RelationshipManager::shouldRunAutomaticEvaluation(0, 1));
        $this->assertTrue(RelationshipManager::shouldRunAutomaticEvaluation(100, 100));
        $this->assertTrue(RelationshipManager::shouldRunAutomaticEvaluation(25, 25));
        $this->assertFalse(RelationshipManager::shouldRunAutomaticEvaluation(25, 26));
        $this->assertTrue(RelationshipManager::shouldRunAutomaticEvaluation('invalid', 100));
    }

    public function testLegacyRelationshipTextIsNotSentForInitialization(): void
    {
        $hadDatabase = array_key_exists('db', $GLOBALS);
        $previousDatabase = $hadDatabase ? $GLOBALS['db'] : null;
        $GLOBALS['db'] = new class {
            public function fetchOne(string $query): array
            {
                return [
                    'id' => 7,
                    'npc_name' => 'Lydia',
                    'relationships' => str_repeat('legacy relationship text ', 200),
                    'extended_data' => '{}',
                ];
            }
        };

        try {
            $llm = (new ReflectionClass(RelationshipLLM::class))->newInstanceWithoutConstructor();
            $result = $llm->analyzeNpc(7, true);
        } finally {
            if ($hadDatabase) {
                $GLOBALS['db'] = $previousDatabase;
            } else {
                unset($GLOBALS['db']);
            }
        }

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['skipped']);
        $this->assertSame('No structured relationships yet', $result['reason']);
    }

    public function testInitialLlmAnalysisCannotPersistAnInventedType(): void
    {
        $llm = (new ReflectionClass(RelationshipLLM::class))->newInstanceWithoutConstructor();
        $parse = new ReflectionMethod(RelationshipLLM::class, 'parseResponse');
        $parsed = $parse->invoke($llm, json_encode([
            'relationships' => [
                'Player' => ['aff' => 60, 'type' => 'Soulmate'],
                'Lydia' => ['aff' => 60, 'type' => 'Romance'],
            ],
        ]));

        $this->assertSame('neutral', $parsed['Player']['type']);
        $this->assertSame('romantic', $parsed['Lydia']['type']);
    }

    public function testConcurrentRebaseRevalidatesTheRequestedType(): void
    {
        $llm = (new ReflectionClass(RelationshipLLM::class))->newInstanceWithoutConstructor();
        $rebase = new ReflectionMethod(RelationshipLLM::class, 'rebaseRelationshipChange');
        $rebased = $rebase->invoke(
            $llm,
            ['aff' => 20, 'type' => 'platonic'],
            ['aff' => 25, 'type' => 'soulmate'],
            ['delta' => 5, 'requested_type' => 'soulmate'],
            []
        );

        $this->assertSame(25, $rebased['aff']);
        $this->assertSame('platonic', $rebased['type']);
    }
}
