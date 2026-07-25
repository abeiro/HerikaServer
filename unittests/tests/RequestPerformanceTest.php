<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/request_performance.php';

final class RequestPerformanceTest extends TestCase
{
    protected function tearDown(): void
    {
        unset(
            $GLOBALS['CHIM_REQUEST_PERFORMANCE'],
            $GLOBALS['CHIM_REQUEST_PERFORMANCE_CLOCK'],
            $GLOBALS['DB_EXECUTION_TIME'],
            $GLOBALS['runid'],
            $GLOBALS['ERROR_TRIGGERED']
        );
    }

    public function testRecordsPhaseDeltasAndTotalTime(): void
    {
        $times = [10.0, 10.125, 10.400, 10.500];
        chimRequestPerformanceInitialize(static function () use (&$times): float {
            return array_shift($times);
        });
        chimRequestPerformanceSetRequestType('InputText');
        chimRequestPerformanceMark('lock_acquired');
        chimRequestPerformanceMark('context_ready');

        $GLOBALS['DB_EXECUTION_TIME'] = 0.032;
        $GLOBALS['runid'] = 'run_test';
        $payload = chimRequestPerformanceFinish('complete', false);

        $this->assertSame('run_test', $payload['run_id']);
        $this->assertSame('inputtext', $payload['request_type']);
        $this->assertSame(500.0, $payload['total_ms']);
        $this->assertSame(32.0, $payload['sql_ms']);
        $this->assertSame('lock_acquired', $payload['phases'][0]['name']);
        $this->assertSame(125.0, $payload['phases'][0]['delta_ms']);
        $this->assertSame(400.0, $payload['phases'][1]['total_ms']);
    }

    public function testFinishIsIdempotent(): void
    {
        $times = [2.0, 2.25, 9.0];
        chimRequestPerformanceInitialize(static function () use (&$times): float {
            return array_shift($times);
        });

        $first = chimRequestPerformanceFinish('complete', false);
        $second = chimRequestPerformanceFinish('failed', false);

        $this->assertSame($first, $second);
        $this->assertSame('complete', $second['status']);
    }

    public function testTerminalStatusRecognizesFatalErrorsButNotWarnings(): void
    {
        $this->assertSame('error', chimRequestPerformanceTerminalStatus(['type' => E_ERROR]));
        $this->assertSame('error', chimRequestPerformanceTerminalStatus(['type' => E_PARSE]));
        $this->assertSame('complete', chimRequestPerformanceTerminalStatus(['type' => E_WARNING]));
        $this->assertSame('complete', chimRequestPerformanceTerminalStatus(null));

        $GLOBALS['ERROR_TRIGGERED'] = true;
        $this->assertSame('error', chimRequestPerformanceTerminalStatus(null));
    }
}
