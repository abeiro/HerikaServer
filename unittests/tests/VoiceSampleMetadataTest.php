<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'voice_sample_metadata.php');

final class VoiceSampleMetadataTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'chim-voice-metadata-' . bin2hex(random_bytes(6));
        mkdir($this->temporaryDirectory, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporaryDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($this->temporaryDirectory);
    }

    public function testDecodesMultipartJsonMetadata(): void
    {
        $metadata = chim_voice_sample_decode_metadata([
            'metadata' => json_encode([
                'schema' => 'chim.voice_sample.v1',
                'game' => 'skyrim',
                'actor_name' => 'Lydia',
                'original_name' => 'Sound\\Voice\\Skyrim.esm\\FemaleEvenToned\\line.fuz',
                'reference_text' => 'I am sworn to carry your burdens.',
            ]),
        ], []);

        $this->assertSame('Lydia', $metadata['actor_name']);
        $this->assertSame('I am sworn to carry your burdens.', $metadata['reference_text']);
        $this->assertSame('multipart_json', $metadata['protocol']);
    }

    public function testFallsBackToLegacyQueryMetadata(): void
    {
        $metadata = chim_voice_sample_decode_metadata([], [
            'codename' => 'Lydia',
            'oname' => 'Sound\\Voice\\Skyrim.esm\\FemaleEvenToned\\line.fuz',
        ]);

        $this->assertSame('Lydia', $metadata['actor_name']);
        $this->assertSame('', $metadata['reference_text']);
        $this->assertSame('legacy_query', $metadata['protocol']);
    }

    public function testRejectsUnsupportedSchema(): void
    {
        $this->expectException(InvalidArgumentException::class);
        chim_voice_sample_decode_metadata([
            'metadata' => json_encode([
                'schema' => 'dialectic.voice_sample.v1',
                'actor_name' => 'Lydia',
                'original_name' => 'line.fuz',
            ]),
        ], []);
    }

    public function testWritesAndReadsMatchingSidecar(): void
    {
        $wavPath = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'femaleeventoned.wav';
        file_put_contents($wavPath, str_repeat('W', 128));

        $written = chim_voice_sample_write_metadata($wavPath, 'femaleeventoned', [
            'actor_name' => 'Lydia',
            'original_name' => 'Sound\\Voice\\Skyrim.esm\\FemaleEvenToned\\line.fuz',
            'reference_text' => 'I am sworn to carry your burdens.',
            'game' => 'skyrim',
        ]);

        $this->assertTrue($written);
        $metadata = chim_voice_sample_read_metadata('femaleeventoned', $this->temporaryDirectory);
        $this->assertSame('chim.voice_sample.metadata.v1', $metadata['schema']);
        $this->assertSame('Lydia', $metadata['actor_name']);
        $this->assertSame(hash_file('sha256', $wavPath), $metadata['sha256']);
        $this->assertSame(128, $metadata['bytes']);
    }
}
