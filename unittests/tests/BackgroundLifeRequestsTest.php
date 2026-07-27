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

    public function testDirectInstructionIsNormalizedAndQueued(): void
    {
        $db = new BackgroundLifeQueueDbStub();

        $queueId = chimBglQueueRequest(
            $db,
            $this->npc(),
            'instruction',
            "  Travel to Riverwood.\x00  "
        );
        $payload = json_decode($db->rows[$queueId], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('instruction', $payload['request_type']);
        $this->assertSame('Travel to Riverwood.', $payload['instruction']);
    }

    public function testDirectInstructionIsPassedToTheConfiguredRunner(): void
    {
        $enginePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR .
            'chim-bgl-request-' . bin2hex(random_bytes(6));
        $debugPath = $enginePath . DIRECTORY_SEPARATOR . 'debug';
        mkdir($debugPath, 0777, true);
        $runnerPath = $debugPath . DIRECTORY_SEPARATOR . 'simple_llm_request_with_context_life_v2.php';
        file_put_contents(
            $runnerPath,
            '<?php echo json_encode($argv, JSON_THROW_ON_ERROR);'
        );

        try {
            $npc = $this->npc([
                'extended_data' => json_encode([
                    'background_life_enabled' => true,
                    'background_life_commands' => true,
                ], JSON_THROW_ON_ERROR),
            ]);
            $result = chimBglRunQueuedRequest(
                $enginePath,
                $npc,
                'instruction',
                'Travel to Riverwood.'
            );
            $arguments = json_decode(
                $result['stdout'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            $this->assertSame(0, $result['exit_code']);
            $this->assertSame('Camilla Valerius', $arguments[1]);
            $this->assertSame('full', $arguments[2]);
            $this->assertSame('forceaction', $arguments[3]);
            $this->assertSame(
                'Travel to Riverwood.',
                base64_decode($arguments[4], true)
            );
        } finally {
            if (is_file($runnerPath)) {
                unlink($runnerPath);
            }
            if (is_dir($debugPath)) {
                rmdir($debugPath);
            }
            if (is_dir($enginePath)) {
                rmdir($enginePath);
            }
        }
    }

    public function testBackgroundLifeCanBeDisabledWithoutLosingOtherData(): void
    {
        $npcMaster = new BackgroundLifeNpcMasterStub();
        $npcMaster->npcsById[7] = $this->npc();

        $status = chimBglSetEnabled($npcMaster, $npcMaster->npcsById[7], false);
        $saved = json_decode(
            (string)$npcMaster->npcsById[7]['extended_data'],
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertFalse($status['background_life_enabled']);
        $this->assertSame('extended', $saved['preserve_me']);
    }
}
