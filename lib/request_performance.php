<?php

function chimRequestPerformanceNow(): float
{
    $clock = $GLOBALS['CHIM_REQUEST_PERFORMANCE_CLOCK'] ?? null;

    return is_callable($clock) ? (float)$clock() : microtime(true);
}

function chimRequestPerformanceInitialize(?callable $clock = null): void
{
    if ($clock !== null) {
        $GLOBALS['CHIM_REQUEST_PERFORMANCE_CLOCK'] = $clock;
    }

    $now = chimRequestPerformanceNow();
    $GLOBALS['CHIM_REQUEST_PERFORMANCE'] = [
        'started_at' => $now,
        'last_mark_at' => $now,
        'request_type' => 'unknown',
        'phases' => [],
        'finished' => false,
    ];
}

function chimRequestPerformanceSetRequestType(string $requestType): void
{
    if (!isset($GLOBALS['CHIM_REQUEST_PERFORMANCE'])) {
        chimRequestPerformanceInitialize();
    }

    $GLOBALS['CHIM_REQUEST_PERFORMANCE']['request_type'] = strtolower(trim($requestType)) ?: 'unknown';
}

function chimRequestPerformanceMark(string $phase): void
{
    if (!isset($GLOBALS['CHIM_REQUEST_PERFORMANCE'])) {
        chimRequestPerformanceInitialize();
    }

    $phase = strtolower(trim($phase));
    if ($phase === '') {
        return;
    }

    $now = chimRequestPerformanceNow();
    $state = &$GLOBALS['CHIM_REQUEST_PERFORMANCE'];
    $state['phases'][] = [
        'name' => $phase,
        'delta_ms' => round(($now - $state['last_mark_at']) * 1000, 2),
        'total_ms' => round(($now - $state['started_at']) * 1000, 2),
    ];
    $state['last_mark_at'] = $now;
}

function chimRequestPerformanceTerminalStatus(?array $lastError = null): string
{
    if (!empty($GLOBALS['ERROR_TRIGGERED'])) {
        return 'error';
    }

    $fatalTypes = [
        E_ERROR,
        E_PARSE,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
        E_USER_ERROR,
        E_RECOVERABLE_ERROR,
    ];
    $lastErrorType = intval($lastError['type'] ?? 0);

    return in_array($lastErrorType, $fatalTypes, true) ? 'error' : 'complete';
}

function chimRequestPerformanceFinish(string $status = 'complete', bool $emit = true): ?array
{
    if (!isset($GLOBALS['CHIM_REQUEST_PERFORMANCE'])) {
        return null;
    }

    $state = &$GLOBALS['CHIM_REQUEST_PERFORMANCE'];
    if (!empty($state['finished'])) {
        return $state['payload'] ?? null;
    }

    $now = chimRequestPerformanceNow();
    $payload = [
        'run_id' => (string)($GLOBALS['runid'] ?? $GLOBALS['AUDIT_RUNID'] ?? ''),
        'request_type' => (string)($state['request_type'] ?? 'unknown'),
        'status' => trim($status) ?: 'complete',
        'total_ms' => round(($now - $state['started_at']) * 1000, 2),
        'sql_ms' => round(((float)($GLOBALS['DB_EXECUTION_TIME'] ?? 0)) * 1000, 2),
        'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
        'phases' => $state['phases'],
    ];

    $state['finished'] = true;
    $state['payload'] = $payload;

    if ($emit) {
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded !== false && class_exists('Logger')) {
            Logger::trace('[PERF] ' . $encoded);
        }
    }

    return $payload;
}
