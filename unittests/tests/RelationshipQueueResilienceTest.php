<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

$GLOBALS['ENGINE_PATH'] = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
require_once $GLOBALS['ENGINE_PATH'] . 'ext' . DIRECTORY_SEPARATOR . 'relationship_system' . DIRECTORY_SEPARATOR . 'async_queue.php';

final class RelationshipQueueResilienceTest extends TestCase
{
    protected function setUp(): void
    {
        Logger::setCustomLog(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'chim-relationship-queue-test.log');
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['db']);
        Logger::unsetCustomLog();
    }

    public function testTypeErrorRetriesPoisonEvaluationAndProcessesNextRow(): void
    {
        $GLOBALS['db'] = new RelationshipQueueFakeDb([
            $this->evalRow(1, 10001, 0),
            $this->evalRow(2, 10002, 0),
        ]);
        $llm = new RelationshipQueueFakeLlm([10001]);

        $result = _relProcessQueue(5, $llm);

        $this->assertSame(1, $result['processed']);
        $this->assertSame(1, $result['retried']);
        $this->assertSame(0, $result['abandoned']);
        $this->assertStringContainsString('TypeError', implode(' ', $result['errors']));
        $this->assertTrue($GLOBALS['db']->hasQuery('DELETE FROM relationship_eval_queue WHERE id IN (2)'));
        $this->assertTrue($GLOBALS['db']->hasQuery('UPDATE relationship_eval_queue'));
        $this->assertTrue($GLOBALS['db']->hasQuery('WHERE id = 1'));
    }

    public function testTypeErrorAtRetryLimitIsAbandonedWithoutBlockingNextRow(): void
    {
        $GLOBALS['db'] = new RelationshipQueueFakeDb([
            $this->evalRow(3, 10003, 3),
            $this->evalRow(4, 10004, 0),
        ]);
        $llm = new RelationshipQueueFakeLlm([10003]);

        $result = _relProcessQueue(5, $llm);

        $this->assertSame(1, $result['processed']);
        $this->assertSame(0, $result['retried']);
        $this->assertSame(1, $result['abandoned']);
        $this->assertTrue($GLOBALS['db']->hasQuery('DELETE FROM relationship_eval_queue WHERE id IN (4)'));
        $this->assertTrue($GLOBALS['db']->hasQuery('DELETE FROM relationship_eval_queue WHERE id IN (3)'));
    }

    public function testUnsuccessfulLlmResultIsRetriedInsteadOfDiscarded(): void
    {
        $GLOBALS['db'] = new RelationshipQueueFakeDb([
            $this->evalRow(7, 10007, 0),
        ]);
        $llm = new RelationshipQueueFakeLlm([], [10007]);

        $result = _relProcessQueue(5, $llm);

        $this->assertSame(0, $result['processed']);
        $this->assertSame(1, $result['retried']);
        $this->assertTrue($GLOBALS['db']->hasQuery('UPDATE relationship_eval_queue'));
        $this->assertFalse($GLOBALS['db']->hasQuery('DELETE FROM relationship_eval_queue WHERE id IN (7)'));
    }

    public function testTypeErrorRetriesPoisonInitializationAndProcessesNextRow(): void
    {
        $GLOBALS['db'] = new RelationshipQueueFakeDb([], [
            $this->initRow(5, 10005, 0),
            $this->initRow(6, 10006, 0),
        ]);
        $llm = new RelationshipQueueFakeLlm([10005]);

        $result = _relProcessInitQueue(5, $llm);

        $this->assertSame(1, $result['processed']);
        $this->assertSame(1, $result['retried']);
        $this->assertSame(0, $result['abandoned']);
        $this->assertTrue($GLOBALS['db']->hasQuery('DELETE FROM relationship_init_queue WHERE id IN (6)'));
        $this->assertTrue($GLOBALS['db']->hasQuery('UPDATE relationship_init_queue'));
        $this->assertTrue($GLOBALS['db']->hasQuery('WHERE id = 5'));
    }

    private function evalRow(int $id, int $npcId, int $retryCount): array
    {
        return [
            'id' => $id,
            'npc_id' => $npcId,
            'retry_count' => $retryCount,
            'eval_data' => json_encode([
                'npc_id' => $npcId,
                'npc_name' => "NPC {$npcId}",
                'dialogue' => 'Hello',
                'context' => ['player_action' => 'Hello'],
                'is_npc2npc' => false,
            ], JSON_THROW_ON_ERROR),
        ];
    }

    private function initRow(int $id, int $npcId, int $retryCount): array
    {
        return [
            'id' => $id,
            'npc_id' => $npcId,
            'retry_count' => $retryCount,
            'init_data' => json_encode([
                'npc_id' => $npcId,
                'npc_name' => "NPC {$npcId}",
            ], JSON_THROW_ON_ERROR),
        ];
    }
}

final class RelationshipQueueFakeDb
{
    public array $queries = [];

    public function __construct(
        private array $evalRows,
        private array $initRows = []
    ) {
    }

    public function fetchAll(string $query): array
    {
        return str_contains($query, 'relationship_init_queue') ? $this->initRows : $this->evalRows;
    }

    public function query(string $query): bool
    {
        $this->queries[] = preg_replace('/\s+/', ' ', trim($query));
        return true;
    }

    public function escape(string $value): string
    {
        return addslashes($value);
    }

    public function hasQuery(string $fragment): bool
    {
        foreach ($this->queries as $query) {
            if (str_contains($query, $fragment)) {
                return true;
            }
        }
        return false;
    }
}

final class RelationshipQueueFakeLlm
{
    public function __construct(
        private array $throwNpcIds,
        private array $failNpcIds = []
    ) {
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function analyzeNpc(int $npcId, bool $force): array
    {
        if (in_array($npcId, $this->throwNpcIds, true)) {
            throw new TypeError('Malformed relationship response');
        }
        if (in_array($npcId, $this->failNpcIds, true)) {
            return ['ok' => false, 'error' => 'Failed to parse response'];
        }
        return ['ok' => true, 'skipped' => true];
    }

    public function evaluateContext(int $npcId, string $dialogue, array $context): array
    {
        return ['ok' => true, 'changes' => []];
    }

    public function evaluateNpcToNpcContext(int $speakerId, int $listenerId, string $dialogue, array $context): array
    {
        return [
            'ok' => true,
            'speaker' => ['changes' => []],
            'listener' => ['changes' => []],
        ];
    }
}
