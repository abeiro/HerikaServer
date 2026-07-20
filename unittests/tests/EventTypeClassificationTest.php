<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/core/event_type.php';
require_once __DIR__ . '/../../lib/chat_helper_functions.php';

final class EventTypeClassificationFakeDb
{
    public array $inserts = [];

    public function insert(string $table, array $row): bool
    {
        $this->inserts[] = ['table' => $table, 'row' => $row];
        return true;
    }
}

final class EventTypeClassificationTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['db'] = new EventTypeClassificationFakeDb();
        $GLOBALS['CACHE_PEOPLE_LIMITED'] = '|Lydia|';
        $GLOBALS['CACHE_LOCATION'] = 'Whiterun';
        $GLOBALS['CACHE_PARTY'] = '[]';
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['db'],
            $GLOBALS['CACHE_PEOPLE_LIMITED'],
            $GLOBALS['CACHE_LOCATION'],
            $GLOBALS['CACHE_PARTY']
        );
    }

    public function testBackgroundMarkerIsStoredAsDedicatedEventType(): void
    {
        logEvent([
            'chat',
            100,
            100,
            '(Context location: Whiterun background chat) Lydia: Fine weather today.',
            'pending',
        ], '|Lydia|');

        $this->assertCount(1, $GLOBALS['db']->inserts);
        $this->assertSame('eventlog', $GLOBALS['db']->inserts[0]['table']);
        $this->assertSame('chat_background', $GLOBALS['db']->inserts[0]['row']['type']);
    }

    public function testOrdinaryChatRemainsOrdinaryChat(): void
    {
        logEvent(['chat', 100, 100, 'Lydia: Fine weather today.', 'pending'], '|Lydia|');

        $this->assertSame('chat', $GLOBALS['db']->inserts[0]['row']['type']);
    }

    public function testBackgroundFilterHandlesNewAndLegacyRows(): void
    {
        $rows = [
            ['type' => 'chat', 'data' => 'Lydia: Ordinary dialogue.'],
            ['type' => 'chat_background', 'data' => 'Lydia: New classified dialogue.'],
            [
                'type' => 'chat',
                'data' => '(Context location: Whiterun background chat) Lydia: Legacy dialogue.',
            ],
        ];

        $filtered = array_values(chimFilterRowsByEventType($rows, 'chat_background'));

        $this->assertCount(1, $filtered);
        $this->assertSame('Lydia: Ordinary dialogue.', $filtered[0]['data']);
    }

    public function testChatFilterDoesNotRemoveBackgroundDialogue(): void
    {
        $rows = [
            ['type' => 'chat', 'data' => 'Lydia: Ordinary dialogue.'],
            ['type' => 'chat_background', 'data' => 'Lydia: Background dialogue.'],
        ];

        $filtered = array_values(chimFilterRowsByEventType($rows, 'chat'));

        $this->assertCount(1, $filtered);
        $this->assertSame('chat_background', $filtered[0]['type']);
    }
}
