<?php declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

require_once(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'npc_commitments.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'npc_commitment_worker.php');

final class CommitmentFakeDb
{
    public array $rows = [];
    public array $queries = [];
    public array $inserts = [];
    public array $activeRow = [
        'id' => 17,
        'due_gamets' => 110416667,
        'repeat_interval_gamets' => 0,
        'occurrence_count' => 0,
    ];

    public function escape(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    public function fetchOne(string $query): array
    {
        $this->queries[] = $query;
        if (str_contains($query, 'to_regclass')) {
            return ['table_name' => 'npc_commitments'];
        }
        if (str_contains($query, 'INSERT INTO public.npc_commitments')) {
            return ['id' => 17, 'due_gamets' => 110416667, 'repeat_interval_gamets' => 0];
        }
        if (str_contains($query, 'SELECT id, due_gamets, repeat_interval_gamets')) {
            return $this->activeRow;
        }
        if (str_contains($query, 'UPDATE public.npc_commitments')) {
            $dueGamets = 120416667;
            if (preg_match('/due_gamets = (\d+)/', $query, $matches)) {
                $dueGamets = (int)$matches[1];
            }
            return [
                'id' => 17,
                'due_gamets' => $dueGamets,
                'occurrence_count' => ((int)$this->activeRow['occurrence_count']) + 1,
            ];
        }
        return [];
    }

    public function execQuery(string $query): bool
    {
        $this->queries[] = $query;
        return true;
    }

    public function insert(string $table, array $data): void
    {
        $this->inserts[] = ['table' => $table, 'data' => $data];
    }

    public function fetchAll(string $query): array
    {
        $this->queries[] = $query;
        return $this->rows;
    }
}
#[RunTestsInSeparateProcesses]
final class NpcCommitmentsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['db'] = new CommitmentFakeDb();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['db']);
    }

    public function testHoursAreClampedAndConvertedToGameTimestampUnits(): void
    {
        $this->assertSame(0.25, chimCommitmentNormalizeHours(0));
        $this->assertSame(8760.0, chimCommitmentNormalizeHours(99999));
        $this->assertSame(10000000, chimCommitmentHoursToGamets(24));
        $this->assertSame(0.0, chimCommitmentNormalizeRepeatHours(0));
        $this->assertSame(24.0, chimCommitmentNormalizeRepeatHours(24));
    }

    public function testCreateTaskUsesAnInGameDueTimestampAndOptionalRepeatInterval(): void
    {
        $result = chimCommitmentCreate('Lydia', [
            'type' => 'meeting',
            'subject' => 'Meet the player at the Bannered Mare',
            'location' => 'The Bannered Mare',
            'due_in_hours' => 24,
            'repeat_every_hours' => 24,
        ], 100416667);

        $this->assertTrue($result['ok']);
        $this->assertSame(17, $result['id']);
        $this->assertStringContainsString('100416667', $GLOBALS['db']->queries[1]);
        $this->assertStringContainsString('110416667', $GLOBALS['db']->queries[1]);
        $this->assertStringContainsString('10000000', $GLOBALS['db']->queries[1]);
        $this->assertSame(24.0, $result['repeat_every_hours']);
    }

    public function testContextMarksOverdueTasksAndRepeatSchedule(): void
    {
        $GLOBALS['db']->rows = [[
            'id' => 17,
            'commitment_type' => 'message_delivery',
            'subject' => 'Deliver the warning',
            'counterparty' => 'Balgruuf',
            'location_name' => 'Dragonsreach',
            'status' => 'due',
            'created_gamets' => 100,
            'due_gamets' => 200,
            'repeat_interval_gamets' => 10000000,
            'occurrence_count' => 2,
            'last_resolved_gamets' => 150,
        ]];

        $context = chimCommitmentFormatContext('Lydia', 300);

        $this->assertStringContainsString('<tasks>', $context);
        $this->assertStringContainsString('# ACTIVE TASKS FOR Lydia', $context);
        $this->assertStringContainsString('#17 [message delivery, DUE NOW]', $context);
        $this->assertStringContainsString('with Balgruuf, at Dragonsreach', $context);
        $this->assertStringContainsString('repeats every about 24 in-game hour(s)', $context);
        $this->assertStringContainsString('completed 2 time(s)', $context);
    }

    public function testOnlyTheOwningActorCanResolveACommitment(): void
    {
        $result = chimCommitmentSetStatus('Lydia', 17, 'completed', 'Delivered', 500);

        $this->assertTrue($result['ok']);
        $query = $GLOBALS['db']->queries[1];
        $this->assertStringContainsString("lower(actor_name) = lower('Lydia')", $query);
        $this->assertStringContainsString("status IN ('scheduled', 'due')", $query);
    }

    public function testRepeatingTaskAdvancesToTheNextFutureOccurrence(): void
    {
        $GLOBALS['db']->activeRow = [
            'id' => 17,
            'due_gamets' => 100,
            'repeat_interval_gamets' => 50,
            'occurrence_count' => 2,
        ];

        $result = chimCommitmentSetStatus('Lydia', 17, 'completed', 'Patrol completed', 225);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['repeated']);
        $this->assertSame(250, $result['next_due_gamets']);
        $this->assertSame(3, $result['occurrence_count']);
        $this->assertStringContainsString("status = 'scheduled'", $GLOBALS['db']->queries[2]);
        $this->assertStringContainsString('due_gamets = 250', $GLOBALS['db']->queries[2]);
    }

    public function testNextOccurrenceKeepsItsOriginalScheduleAnchor(): void
    {
        $this->assertSame(150, chimCommitmentNextDueGamets(100, 90, 50));
        $this->assertSame(150, chimCommitmentNextDueGamets(100, 100, 50));
        $this->assertSame(250, chimCommitmentNextDueGamets(100, 225, 50));
    }

    public function testCancellingStopsARepeatingTask(): void
    {
        $GLOBALS['db']->activeRow['repeat_interval_gamets'] = 50;

        $result = chimCommitmentSetStatus('Lydia', 17, 'cancelled', 'No longer needed', 225);

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['repeated']);
        $this->assertStringContainsString("status = 'cancelled'", $GLOBALS['db']->queries[2]);
        $this->assertStringContainsString('occurrence_count = occurrence_count + 0', $GLOBALS['db']->queries[2]);
    }

    public function testPartialTaskPayloadIsCompletedFromPlayerRequest(): void
    {
        $payload = chimCommitmentPrepareCreatePayload(
            ['type' => 'other'],
            'RANGROO: Remember a task to check the town gate every two hours. (Talking to Danica Pure-Spring)'
        );

        $this->assertSame('other', $payload['type']);
        $this->assertSame('Check the town gate', $payload['subject']);
        $this->assertSame(2.0, $payload['due_in_hours']);
        $this->assertSame(2.0, $payload['repeat_every_hours']);
    }

    public function testOneTimeTaskDueTimeIsInferredFromPlayerRequest(): void
    {
        $payload = chimCommitmentPrepareCreatePayload(
            [],
            'Please remember to deliver a message to Balgruuf in 6 hours'
        );

        $this->assertSame('message_delivery', $payload['type']);
        $this->assertSame('Deliver a message to Balgruuf', $payload['subject']);
        $this->assertSame(6.0, $payload['due_in_hours']);
        $this->assertArrayNotHasKey('repeat_every_hours', $payload);
    }

    public function testFormatterResponseJsonIsExtractedFromCodeFence(): void
    {
        $payload = chimCommitmentExtractJsonObject("```json\n{\"type\":\"escort\",\"subject\":\"Escort the merchant\",\"due_in_hours\":4}\n```");

        $this->assertIsArray($payload);
        $this->assertSame('escort', $payload['type']);
        $this->assertSame('Escort the merchant', $payload['subject']);
        $this->assertSame(4, $payload['due_in_hours']);
    }

    public function testCreatedTaskNotificationUsesTheExistingChimDebugCommand(): void
    {
        chimCommitmentQueueCreatedNotification(
            "Danica|Pure-Spring",
            "Check the town gate@midnight\nwithout delay"
        );

        $this->assertCount(1, $GLOBALS['db']->inserts);
        $queued = $GLOBALS['db']->inserts[0];
        $this->assertSame('responselog', $queued['table']);
        $this->assertSame(0, $queued['data']['sent']);
        $this->assertSame('rolemaster', $queued['data']['actor']);
        $this->assertSame(
            'rolecommand|DebugNotification@Task created for Danica Pure-Spring: Check the town gate midnight without delay',
            $queued['data']['action']
        );
    }

    public function testUpgradeMigrationSeedsCommitmentActions(): void
    {
        $updates = file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'debug' . DIRECTORY_SEPARATOR . 'db_updates.php'
        );

        $this->assertIsString($updates);
        $this->assertStringContainsString('$checkVersion("core_action") < 20260719002', $updates);
        $this->assertStringContainsString("'CreateTasks'", $updates);
        $this->assertStringContainsString("'ResolveTask'", $updates);
        $this->assertStringContainsString("'CancelTask'", $updates);
        $this->assertStringContainsString('$updateVersion("core_action", 20260719002)', $updates);
    }
}
