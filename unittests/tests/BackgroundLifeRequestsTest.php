<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/core/npc_master.class.php';
require_once __DIR__ . '/../../lib/background_life_requests.php';

final class BackgroundLifeNpcMasterStub extends NpcMaster
{
    public array $npcsById = [];
    public array $lookups = [];

    public function __construct()
    {
    }

    public function getById($id)
    {
        return $this->npcsById[(int)$id] ?? null;
    }

    public function getByName($npcName)
    {
        $this->lookups[] = ['name', $npcName];
        foreach ($this->npcsById as $npc) {
            if (($npc['npc_name'] ?? '') === $npcName) {
                return $npc;
            }
        }
        return null;
    }

    public function getByRefId($refid)
    {
        $this->lookups[] = ['refid', $refid];
        foreach ($this->npcsById as $npc) {
            if (($npc['refid'] ?? '') === $refid) {
                return $npc;
            }
        }
        return null;
    }

    public function updateByArray($data)
    {
        $this->npcsById[(int)$data['id']] = $data;
        return true;
    }
}

final class BackgroundLifeQueueDbStub
{
    public array $rows = [];

    public function upsertRowOnConflict(string $table, array $data, string $conflictColumn): bool
    {
        $this->rows[(string)$data['id']] = (string)$data['value'];
        return true;
    }
}

final class BackgroundLifeRequestsTest extends TestCase
{
    private function npc(array $overrides = []): array
    {
        return array_merge([
            'id' => 7,
            'npc_name' => 'Camilla Valerius',
            'refid' => '00013488',
            'extended_data' => json_encode([
                'background_life_enabled' => true,
                'background_life_commands' => false,
                'preserve_me' => 'extended',
            ], JSON_THROW_ON_ERROR),
            'metadata' => json_encode([
                'gps_track' => false,
                'preserve_me' => 'metadata',
            ], JSON_THROW_ON_ERROR),
        ], $overrides);
    }

    public function testRefIdIsNormalizedAndPreferredOverDuplicateName(): void
    {
        $npcMaster = new BackgroundLifeNpcMasterStub();
        $expected = $this->npc();
        $npcMaster->npcsById = [
            7 => $expected,
            8 => $this->npc([
                'id' => 8,
                'npc_name' => 'Camilla Valerius',
                'refid' => 'FF001234',
            ]),
        ];

        $resolved = chimBglResolveNpc($npcMaster, '0x13488', 'Camilla Valerius');

        $this->assertSame($expected, $resolved);
        $this->assertSame([['refid', '00013488']], $npcMaster->lookups);
    }

    public function testNpcStatusExposesBackgroundLifeControls(): void
    {
        $npcMaster = new BackgroundLifeNpcMasterStub();
        $npc = $this->npc([
            'extended_data' => json_encode([
                'background_life_enabled' => 't',
                'background_life_commands' => '1',
                'background_life_letters' => true,
            ], JSON_THROW_ON_ERROR),
            'metadata' => json_encode(['gps_track' => 'on'], JSON_THROW_ON_ERROR),
        ]);

        $status = chimBglNpcStatus($npcMaster, $npc);

        $this->assertSame('00013488', $status['refid']);
        $this->assertTrue($status['background_life_enabled']);
        $this->assertTrue($status['auto_actions']);
        $this->assertTrue($status['send_letters']);
        $this->assertTrue($status['hourly_tracking']);
    }

    public function testSettingUpdatePreservesUnrelatedMetadata(): void
    {
        $npcMaster = new BackgroundLifeNpcMasterStub();
        $npcMaster->npcsById[7] = $this->npc();

        $status = chimBglUpdateNpcSetting(
            $npcMaster,
            $npcMaster->npcsById[7],
            'hourly_tracking',
            true
        );
        $savedMetadata = json_decode(
            (string)$npcMaster->npcsById[7]['metadata'],
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertTrue($status['hourly_tracking']);
        $this->assertSame('metadata', $savedMetadata['preserve_me']);
    }

    public function testQueuedRequestCarriesStableNpcIdentity(): void
    {
        $db = new BackgroundLifeQueueDbStub();

        $queueId = chimBglQueueRequest($db, $this->npc(), 'letter');
        $payload = json_decode($db->rows[$queueId], true, 512, JSON_THROW_ON_ERROR);

        $this->assertStringStartsWith('background_life_request_queue_', $queueId);
        $this->assertSame('letter', $payload['request_type']);
        $this->assertSame(7, $payload['npc_id']);
        $this->assertSame('Camilla Valerius', $payload['npc_name']);
        $this->assertSame('00013488', $payload['refid']);
    }
}
