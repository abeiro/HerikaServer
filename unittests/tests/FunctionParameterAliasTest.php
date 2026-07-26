<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__
    .DIRECTORY_SEPARATOR.'..'
    .DIRECTORY_SEPARATOR.'..'
    .DIRECTORY_SEPARATOR.'lib'
    .DIRECTORY_SEPARATOR.'function_parameter_normalization.php';

final class FunctionParameterAliasTest extends TestCase
{
    private array $travelProperties = [
        'location' => [
            'type' => 'string',
        ],
    ];

    public function testGenericTargetMapsToSingleNamedLocationParameter(): void
    {
        $normalized = herikaNormalizeFunctionResponseArguments(
            $this->travelProperties,
            ['action' => 'Travel_To', 'target' => 'Riverwood']
        );

        $this->assertSame('Riverwood', $normalized['location']);
        $this->assertSame('Riverwood', $normalized['target']);
    }

    public function testEmptyGenericTargetRemainsEmptyAfterMapping(): void
    {
        $normalized = herikaNormalizeFunctionResponseArguments(
            $this->travelProperties,
            ['action' => 'Travel_To', 'target' => '']
        );

        $this->assertSame('', $normalized['location']);
    }

    public function testExplicitNamedParameterIsNotOverwritten(): void
    {
        $normalized = herikaNormalizeFunctionResponseArguments(
            $this->travelProperties,
            ['target' => 'Riverwood', 'location' => 'Whiterun']
        );

        $this->assertSame('Whiterun', $normalized['location']);
    }

    public function testMultiParameterSchemaKeepsGenericResponseUnchanged(): void
    {
        $response = ['target' => 'RANGROO', 'amount' => 50];
        $normalized = herikaNormalizeFunctionResponseArguments(
            [
                'target' => ['type' => 'string'],
                'amount' => ['type' => 'integer'],
            ],
            $response
        );

        $this->assertSame($response, $normalized);
    }
}
