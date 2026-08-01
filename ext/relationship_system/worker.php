#!/usr/bin/php
<?php
/**
 * RELATIONSHIP SYSTEM - Background Worker Daemon
 *
 * Processes queued relationship evaluations in the background.
 * Auto-started by context.php when RELLLM_CONNECTOR is configured.
 *
 * Usage:
 *   php worker.php              # Run once and exit
 *   php worker.php --daemon     # Run continuously (auto-started by context.php)
 *   php worker.php --interval=5 # Custom interval in seconds (default: 2)
 *
 * DAEMONIZATION:
 * When started with --daemon, this script forks to detach from the parent process.
 * This ensures it survives when Apache/PHP request terminates.
 * PID is written to log/relationship_worker.pid for tracking.
 *
 * CLEANUP:
 * The worker is automatically killed when the server stops via the trap in start.sh
 */

// Parse command line arguments
$daemon = in_array('--daemon', $argv);
$interval = 2; // Default 2 seconds between checks

foreach ($argv as $arg) {
    if (strpos($arg, '--interval=') === 0) {
        $interval = max(1, intval(substr($arg, 11)));
    }
}

// Resolve paths BEFORE any forking/chdir
$enginePath = realpath(__DIR__ . '/../../') . '/';
$pidFile = $enginePath . 'log/relationship_worker.pid';
$logFile = $enginePath . 'log/relationship_worker.log';

// EARLY TRACE: Log immediately to see if script even starts
@file_put_contents($logFile, date('[Y-m-d H:i:s]') . " TRACE: Script started, daemon=" . ($daemon ? 'yes' : 'no') . "\n", FILE_APPEND);

// DAEMONIZATION: If running in daemon mode, fork to detach from parent process
// This ensures the worker survives when Apache/PHP parent exits
if ($daemon && function_exists('pcntl_fork')) {
    // Fork once to detach from parent
    $pid = pcntl_fork();
    if ($pid < 0) {
        exit(1); // Fork failed
    }
    if ($pid > 0) {
        // Parent: write child PID and exit
        file_put_contents($pidFile, $pid);
        exit(0);
    }

    // Child: become session leader (fully detach from terminal)
    @file_put_contents($logFile, date('[Y-m-d H:i:s]') . " TRACE: Child before setsid, PID=" . getmypid() . "\n", FILE_APPEND);
    posix_setsid();
    @file_put_contents($logFile, date('[Y-m-d H:i:s]') . " TRACE: Child after setsid\n", FILE_APPEND);

    // Redirect stdio to log file
    fclose(STDIN);
    fclose(STDOUT);
    fclose(STDERR);

    $STDIN = fopen('/dev/null', 'r');
    $STDOUT = fopen($logFile, 'a');
    $STDERR = fopen($logFile, 'a');

    // If log file couldn't be opened, use /dev/null so we don't crash
    if ($STDOUT === false) {
        $STDOUT = fopen('/dev/null', 'a');
    }
    if ($STDERR === false) {
        $STDERR = fopen('/dev/null', 'a');
    }
    @file_put_contents($logFile, date('[Y-m-d H:i:s]') . " TRACE: Child stdio redirected\n", FILE_APPEND);
}

// Set up error handler to catch fatal errors and log them
set_error_handler(function($errno, $errstr, $errfile, $errline) use ($logFile) {
    $msg = date('[Y-m-d H:i:s]') . " PHP Error ({$errno}): {$errstr} in {$errfile}:{$errline}\n";
    @file_put_contents($logFile, $msg, FILE_APPEND);
    return false; // Let PHP handle it too
});

register_shutdown_function(function() use ($logFile, $pidFile) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $msg = date('[Y-m-d H:i:s]') . " FATAL: {$error['message']} in {$error['file']}:{$error['line']}\n";
        @file_put_contents($logFile, $msg, FILE_APPEND);
    }
    // Clean up PID file on exit
    @unlink($pidFile);
});

// Bootstrap the Herika environment
@file_put_contents($logFile, date('[Y-m-d H:i:s]') . " TRACE: Before bootstrap\n", FILE_APPEND);
$GLOBALS['ENGINE_PATH'] = $enginePath;

try {
    @file_put_contents($logFile, date('[Y-m-d H:i:s]') . " TRACE: Loading conf.php\n", FILE_APPEND);
    // Load config
    require_once $enginePath . 'conf/conf.php';

    @file_put_contents($logFile, date('[Y-m-d H:i:s]') . " TRACE: Loading postgresql.class.php\n", FILE_APPEND);
    // Load database - class is 'sql' not 'postgresql'
    require_once $enginePath . 'lib/postgresql.class.php';
    @file_put_contents($logFile, date('[Y-m-d H:i:s]') . " TRACE: Creating db connection\n", FILE_APPEND);
    $GLOBALS['db'] = new sql();

    @file_put_contents($logFile, date('[Y-m-d H:i:s]') . " TRACE: Loading api_badge and logger\n", FILE_APPEND);
    // Load required classes
    require_once $enginePath . 'lib/core/api_badge.class.php';
    require_once $enginePath . 'lib/logger.php';
    require_once $enginePath . 'lib/settings.php';
    chimLoadGeneralSettingsIntoGlobals();

    // Set up minimal globals that LLM connector needs
    $GLOBALS['HERIKA_NAME'] = 'Worker';
    $GLOBALS['PLAYER_NAME'] = 'Player';
    try {
        $playerRow = $GLOBALS['db']->fetchOne("SELECT value FROM conf_opts WHERE id='PLAYER_NAME' LIMIT 1");
        if ($playerRow && !empty($playerRow['value'])) {
            $GLOBALS['PLAYER_NAME'] = trim((string)$playerRow['value']);
        }
    } catch (Throwable $e) {
        @file_put_contents($logFile, date('[Y-m-d H:i:s]') . " WARN: Failed to load PLAYER_NAME: " . $e->getMessage() . "\n", FILE_APPEND);
    }

    Logger::info("[REL-WORKER] Starting relationship worker" . ($daemon ? " in daemon mode" : ""));
} catch (Throwable $e) {
    $msg = date('[Y-m-d H:i:s]') . " BOOTSTRAP ERROR: " . get_class($e) . ": " . $e->getMessage() . "\n";
    @file_put_contents($logFile, $msg, FILE_APPEND);
    exit(1);
}

// Load queue functions
require_once __DIR__ . '/async_queue.php';

/**
 * Process one batch of queued evaluations
 * @return int Number of items processed
 */
function processOneBatch() {
    $evalResults = _relProcessQueue(10);  // Process up to 10 evals per batch
    $initResults = _relProcessInitQueue(5); // Process up to 5 inits per batch

    $total = ($evalResults['processed'] ?? 0) + ($initResults['processed'] ?? 0);

    if ($total > 0) {
        Logger::info("[REL-WORKER] Processed {$evalResults['processed']} evals, {$initResults['processed']} inits");
    }

    return $total;
}

if ($daemon) {
    // Daemon mode - run continuously
    Logger::info("[REL-WORKER] Running in daemon mode, interval: {$interval}s, PID: " . getmypid());

    // Log that we're entering the main loop
    @file_put_contents($logFile, date('[Y-m-d H:i:s]') . " DAEMON: Entering main loop\n", FILE_APPEND);

    $iteration = 0;
    while (true) {
        $iteration++;
        @file_put_contents($logFile, date('[Y-m-d H:i:s]') . " DAEMON: Iteration {$iteration} starting\n", FILE_APPEND);

        try {
            $processed = processOneBatch();
            @file_put_contents($logFile, date('[Y-m-d H:i:s]') . " DAEMON: Iteration {$iteration} processed {$processed}\n", FILE_APPEND);

            // If we processed something, check again immediately
            // Otherwise wait the interval
            if ($processed === 0) {
                sleep($interval);
            }
        } catch (Throwable $e) {
            $error = get_class($e) . ": " . $e->getMessage();
            @file_put_contents($logFile, date('[Y-m-d H:i:s]') . " DAEMON: Failure: " . $error . "\n", FILE_APPEND);
            Logger::error("[REL-WORKER] Error: " . $error);
            sleep($interval * 2); // Wait longer on error
        }
    }
} else {
    // One-shot mode - process once and exit
    try {
        $processed = processOneBatch();
        echo "Processed: {$processed}\n";
        exit($processed > 0 ? 0 : 1);
    } catch (Throwable $e) {
        $error = get_class($e) . ": " . $e->getMessage();
        @file_put_contents($logFile, date('[Y-m-d H:i:s]') . " ONE-SHOT: Failure: " . $error . "\n", FILE_APPEND);
        exit(2);
    }
}
