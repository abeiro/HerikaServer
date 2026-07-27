<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

$GLOBALS['ENGINE_PATH'] = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
require_once $GLOBALS['ENGINE_PATH'] . 'ext' . DIRECTORY_SEPARATOR . 'relationship_system' . DIRECTORY_SEPARATOR . 'relationship_llm.php';

final class RelationshipLlmResponseValidationTest extends TestCase
{
    private RelationshipLLM $relationshipLlm;

    protected function setUp(): void
    {
        $reflection = new ReflectionClass(RelationshipLLM::class);
        $this->relationshipLlm = $reflection->newInstanceWithoutConstructor();
        Logger::setCustomLog(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'chim-relationship-response-test.log');
    }

    protected function tearDown(): void
    {
        Logger::unsetCustomLog();
    }

    public function testInitialParserKeepsValidEntriesAndIgnoresMalformedFields(): void
    {
        $response = json_encode([
            'relationships' => [
                'Player' => [
                    'aff' => 12,
                    'type' => ['romantic'],
                    'note' => ['not scalar'],
                ],
                'Lydia' => [
                    'aff' => '7',
                    'type' => 'PLATONIC',
                    'note' => '  Trusted ally  ',
                ],
                'Broken affinity' => [
                    'aff' => ['99'],
                    'type' => 'romantic',
                ],
                'Broken entry' => 'not an object',
            ],
        ], JSON_THROW_ON_ERROR);

        $parsed = $this->invokePrivate('parseResponse', [$response]);

        $this->assertSame(
            [
                'Player' => ['aff' => 12, 'type' => 'neutral'],
                'Lydia' => ['aff' => 7, 'type' => 'platonic', 'note' => 'Trusted ally'],
            ],
            $parsed
        );
    }

    public function testInitialParserRejectsMalformedRelationshipContainer(): void
    {
        $response = json_encode(['relationships' => 'not an object'], JSON_THROW_ON_ERROR);

        $this->assertNull($this->invokePrivate('parseResponse', [$response]));
    }

    public function testDynamicParserNormalizesMixedChanges(): void
    {
        $response = json_encode([
            'changes' => [
                'Lydia' => [
                    'delta' => '3',
                    'type' => 'PLATONIC',
                    'reason' => '  Helpful advice  ',
                    'relation' => ' FRIEND ',
                ],
                'Type only' => ['type' => 'RIVAL'],
                'Partly malformed' => [
                    'delta' => 2,
                    'type' => ['rival'],
                    'reason' => ['not scalar'],
                ],
                'Entirely malformed' => [
                    'delta' => ['5'],
                    'type' => ['romantic'],
                ],
                'Broken entry' => 7,
            ],
        ], JSON_THROW_ON_ERROR);

        $parsed = $this->invokePrivate('parseEvalResponse', [$response]);

        $this->assertSame(
            [
                'Lydia' => [
                    'delta' => 3,
                    'type' => 'platonic',
                    'reason' => 'Helpful advice',
                    'relation' => 'friend',
                ],
                'Type only' => [
                    'type' => 'rival',
                    'delta' => 0,
                ],
                'Partly malformed' => [
                    'delta' => 2,
                ],
            ],
            $parsed
        );
    }

    public function testDynamicParserRejectsMalformedChangesContainer(): void
    {
        $response = json_encode(['changes' => ['not', 'an', 'object']], JSON_THROW_ON_ERROR);

        $this->assertSame([], $this->invokePrivate('parseEvalResponse', [$response]));
    }

    public function testNpcToNpcParserNormalizesBothSidesIndependently(): void
    {
        $response = json_encode([
            'speaker' => ['delta' => 2, 'reason' => 'Built rapport'],
            'listener' => ['delta' => 1, 'type' => ['friend'], 'reason' => ['bad']],
        ], JSON_THROW_ON_ERROR);

        $parsed = $this->invokePrivate('parseNpcToNpcResponse', [$response, 'Lydia', 'Player']);

        $this->assertSame(
            [
                'speaker' => ['delta' => 2, 'reason' => 'Built rapport'],
                'listener' => ['delta' => 1],
            ],
            $parsed
        );
    }

    public function testNpcToNpcParserRejectsScalarSides(): void
    {
        $response = json_encode([
            'speaker' => 'bad',
            'listener' => 4,
        ], JSON_THROW_ON_ERROR);

        $this->assertSame(
            ['speaker' => [], 'listener' => []],
            $this->invokePrivate('parseNpcToNpcResponse', [$response, 'Lydia', 'Player'])
        );
    }

    private function invokePrivate(string $method, array $arguments): mixed
    {
        $reflection = new ReflectionMethod(RelationshipLLM::class, $method);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($this->relationshipLlm, $arguments);
    }
}
