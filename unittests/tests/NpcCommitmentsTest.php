<?php declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

require_once(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'npc_commitments.php');

final class CommitmentFakeDb
{
    public array $rows = [];
    public array $queries = [];

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
            return ['id' => 17, 'due_gamets' => 110416667];
        }
        if (str_contains($query, 'UPDATE public.npc_commitments')) {
            return ['id' => 17];
        }
        return [];
    }

    public function execQuery(string $query): bool
    {
        $this->queries[] = $query;
        return true;
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
    }

    public function testCreateCommitmentUsesAnInGameDueTimestamp(): void
    {
        $result = chimCommitmentCreate('Lydia', [
            'type' => 'meeting',
            'subject' => 'Meet the player at the Bannered Mare',
            'location' => 'The Bannered Mare',
            'due_in_hours' => 24,
        ], 100416667);

        $this->assertTrue($result['ok']);
        $this->assertSame(17, $result['id']);
        $this->assertStringContainsString('100416667', $GLOBALS['db']->queries[1]);
        $this->assertStringContainsString('110416667', $GLOBALS['db']->queries[1]);
    }

    public function testContextMarksOverduePromisesAsDueNow(): void
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
        ]];

        $context = chimCommitmentFormatContext('Lydia', 300);

        $this->assertStringContainsString('<commitments>', $context);
        $this->assertStringContainsString('#17 [message delivery, DUE NOW]', $context);
        $this->assertStringContainsString('with Balgruuf, at Dragonsreach', $context);
    }

    public function testOnlyTheOwningActorCanResolveACommitment(): void
    {
        $result = chimCommitmentSetStatus('Lydia', 17, 'completed', 'Delivered', 500);

        $this->assertTrue($result['ok']);
        $query = $GLOBALS['db']->queries[1];
        $this->assertStringContainsString("lower(actor_name) = lower('Lydia')", $query);
        $this->assertStringContainsString("status IN ('scheduled', 'due')", $query);
    }

    public function testUpgradeMigrationSeedsCommitmentActions(): void
    {
        $updates = file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'debug' . DIRECTORY_SEPARATOR . 'db_updates.php'
        );

        $this->assertIsString($updates);
        $this->assertStringContainsString('$checkVersion("core_action") < 20260719001', $updates);
        $this->assertStringContainsString("'CreateCommitment'", $updates);
        $this->assertStringContainsString("'ResolveCommitment'", $updates);
        $this->assertStringContainsString("'CancelCommitment'", $updates);
        $this->assertStringContainsString('$updateVersion("core_action", 20260719001)', $updates);
    }
}
