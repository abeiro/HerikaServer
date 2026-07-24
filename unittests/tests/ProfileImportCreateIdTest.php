<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'api_badge.class.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'core_profiles.class.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'llm_connector.class.php';

final class ProfileImportCreateIdDatabaseStub
{
    public array $inserts = [];
    private array $ids;

    public function __construct(array $ids)
    {
        $this->ids = $ids;
    }

    public function insertReturningId(string $table, array $data): int
    {
        $this->inserts[] = ['table' => $table, 'data' => $data];
        return (int)array_shift($this->ids);
    }

    public function fetchOne(string $query): array
    {
        return [];
    }

    public function GetLastError(): string
    {
        return '';
    }
}

final class ProfileImportCreateIdTest extends TestCase
{
    private $previousDb;

    protected function setUp(): void
    {
        $this->previousDb = $GLOBALS['db'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->previousDb === null) {
            unset($GLOBALS['db']);
            return;
        }

        $GLOBALS['db'] = $this->previousDb;
    }

    public function testImportCreatePathsReturnDatabaseIds(): void
    {
        $db = new ProfileImportCreateIdDatabaseStub([41, 42, 43]);
        $GLOBALS['db'] = $db;

        $badgeId = (new ApiBadge())->create([
            'label' => 'Imported OpenRouter',
            'api_key' => '',
        ]);
        $llmId = (new LLMConnector())->create([
            'label' => 'Imported Model',
            'driver' => 'openrouterjson',
            'model' => 'example/model',
            'api_badge_id' => $badgeId,
        ]);
        $profileId = (new CoreProfile())->create([
            'label' => 'Imported Profile',
            'default_npc' => 0,
            'default_narrator' => 0,
            'llm_primary_id' => $llmId,
            'slot' => null,
        ]);

        $this->assertSame(41, $badgeId);
        $this->assertSame(42, $llmId);
        $this->assertSame(43, $profileId);
        $this->assertSame(
            ['core_api_badge', 'core_llm_connector', 'core_profiles'],
            array_column($db->inserts, 'table')
        );
        $this->assertSame(42, $db->inserts[2]['data']['llm_primary_id']);
    }
}
