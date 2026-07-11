<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'data_functions.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'vr_items.php';

final class HeldItemMemoryDb
{
    public array $rows = [];

    public function escape($value): string
    {
        return str_replace("'", "''", (string) $value);
    }

    public function fetchOne(string $query): ?array
    {
        foreach ($this->rows as $key => $value) {
            if (str_contains($query, "id = '{$key}'")) {
                return ['value' => $value];
            }
        }

        return null;
    }

    public function upsertRowOnConflict(string $table, array $data, string $conflictColumn): void
    {
        $this->rows[(string) $data['id']] = (string) $data['value'];
    }
}

final class InventoryHeldItemContextTest extends TestCase
{
    private HeldItemMemoryDb $db;

    protected function setUp(): void
    {
        $this->db = new HeldItemMemoryDb();
        $GLOBALS['db'] = $this->db;
        $GLOBALS['PLAYER_NAME'] = 'Prisoner';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['db'], $GLOBALS['PLAYER_NAME']);
    }

    public function testInventoryMarkdownIncludesNormalizedBaseIdsCountsAndDescriptions(): void
    {
        $described = [];
        $lines = chimFormatInventoryPromptLines(
            [
                ['name' => 'Iron Sword', 'baseid' => '12eb7', 'count' => 1],
                ['name' => 'Daedra Heart', 'baseid' => '0x3ad5b', 'count' => 2],
            ],
            static fn(string $name, ?string $baseid): ?string => $name === 'Iron Sword' ? 'A basic iron weapon.' : null,
            $described
        );

        $this->assertSame('- `0x00012EB7:Iron Sword` (1) - A basic iron weapon.', $lines[0]);
        $this->assertSame('- `0x0003AD5B:Daedra Heart` (2)', $lines[1]);
    }

    public function testInventoryMarkdownKeepsLegacyItemsAndEscapesPromptSyntax(): void
    {
        $described = [];
        $lines = chimFormatInventoryPromptLines([
            ['name' => 'Odd `<Blade> & Relic', 'count' => 1],
        ], null, $described);

        $this->assertSame('- Odd &#96;&lt;Blade&gt; &amp; Relic (1)', $lines[0]);
        $this->assertStringNotContainsString('null:', $lines[0]);
    }

    public function testInventoryPromptContextUsesDocumentedMarkdownFormat(): void
    {
        $described = [];
        $context = chimBuildInventoryPromptContext([
            ['name' => 'Iron Sword', 'baseid' => '12eb7', 'count' => 1],
        ], null, $described);

        $this->assertSame(
            "<inventory>\n# INVENTORY\nFormat: BaseID:ItemName (quantity)\n\n- `0x00012EB7:Iron Sword` (1)\n</inventory>",
            $context
        );
    }

    public function testNewHeldItemProtocolStoresAndRendersRefId(): void
    {
        $pickup = HeldItems::processEventRequest(['ext_vr_item_raw', '0', '0', 'Iron Sword^pickup^left^ff001234']);

        $this->assertSame('ext_held_item_pickup', $pickup[0]);
        $this->assertStringContainsString('`0xFF001234:Iron Sword`', $pickup[3]);
        $this->assertStringContainsString('- Left: `0xFF001234:Iron Sword`', HeldItems::getHeldItemsContext());
    }

    public function testDropIncludesRememberedRefIdBeforeClearingState(): void
    {
        HeldItems::processEventRequest(['ext_vr_item_raw', '0', '0', 'Iron Sword^pickup^left^0xFF001234']);
        $drop = HeldItems::processEventRequest(['ext_vr_item_raw', '0', '0', 'Iron Sword^drop^left']);

        $this->assertSame('ext_held_item_drop', $drop[0]);
        $this->assertStringContainsString('`0xFF001234:Iron Sword`', $drop[3]);
        $this->assertSame('', HeldItems::getHeldItemsContext());
    }

    public function testLegacyAndMalformedRefIdEventsRemainValid(): void
    {
        $legacy = HeldItems::processEventRequest(['ext_vr_item_raw', '0', '0', 'Tankard^pickup^right']);
        $this->assertNotNull($legacy);
        $this->assertStringContainsString('- Right: Tankard', HeldItems::getHeldItemsContext());

        $invalid = HeldItems::processEventRequest(['ext_vr_item_raw', '0', '0', 'Goblet^pickup^left^not-a-form-id']);
        $this->assertNotNull($invalid);
        $this->assertStringContainsString('- Left: Goblet', HeldItems::getHeldItemsContext());
        $this->assertStringNotContainsString('not-a-form-id', HeldItems::getHeldItemsContext());
    }

    public function testLegacyStoredStringAndStructuredStateBothRender(): void
    {
        $this->db->rows['player_held_item_state'] = json_encode([
            'left' => 'Legacy Sword',
            'right' => ['name' => 'New Sword', 'refid' => 'abc'],
            'both' => null,
            'updated_at' => time(),
        ]);

        $context = HeldItems::getHeldItemsContext();
        $this->assertStringContainsString('- Left: Legacy Sword', $context);
        $this->assertStringContainsString('- Right: `0x00000ABC:New Sword`', $context);
    }

    public function testHeldItemNamesAreSafeForXmlAndMarkdown(): void
    {
        $pickup = HeldItems::processEventRequest([
            'ext_vr_item_raw',
            '0',
            '0',
            'Odd `<Sword> & Relic^pickup^both^1234',
        ]);

        $this->assertStringContainsString('`0x00001234:Odd &#96;&lt;Sword&gt; &amp; Relic`', $pickup[3]);
    }
}
