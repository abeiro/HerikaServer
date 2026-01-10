#!/usr/bin/php
<?php
/**
 * RELATIONSHIP SYSTEM - Background Worker
 *
 * Processes queued relationship evaluations in the background.
 * Run this as a systemd service or cron job.
 *
 * Usage:
 *   php worker.php              # Run once and exit
 *   php worker.php --daemon     # Run continuously (for systemd)
 *   php worker.php --interval=5 # Custom interval in seconds (default: 2)
 *
 * Systemd service example (/etc/systemd/system/relationship-worker.service):
 *   [Unit]
 *   Description=CHIM Relationship System Worker
 *   After=postgresql.service apache2.service
 *
 *   [Service]
 *   Type=simple
 *   User=www-data
 *   WorkingDirectory=/var/www/html/HerikaServer/ext/relationship_system
 *   ExecStart=/usr/bin/php worker.php --daemon
 *   Restart=always
 *   RestartSec=5
 *
 *   [Install]
 *   WantedBy=multi-user.target
 *
 * Then: systemctl enable relationship-worker && systemctl start relationship-worker
 */

// Parse command line arguments
$daemon = in_array('--daemon', $argv);
$interval = 2; // Default 2 seconds between checks

foreach ($argv as $arg) {
    if (strpos($arg, '--interval=') === 0) {
        $interval = max(1, intval(substr($arg, 11)));
    }
}

// Bootstrap the Herika environment
$enginePath = realpath(__DIR__ . '/../../') . '/';
$GLOBALS['ENGINE_PATH'] = $enginePath;

// Load config
require_once $enginePath . 'conf/conf.php';

// Load database
require_once $enginePath . 'lib/postgresql.class.php';
$GLOBALS['db'] = new postgresql($PGSQL_CONNECTION);

// Load required classes
require_once $enginePath . 'lib/core/api_badge.class.php';
require_once $enginePath . 'lib/logger.php';

// Set up minimal globals that LLM connector needs
$GLOBALS['HERIKA_NAME'] = 'Worker';
$GLOBALS['PLAYER_NAME'] = 'Player';

Logger::info("[REL-WORKER] Starting relationship worker" . ($daemon ? " in daemon mode" : ""));

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
    Logger::info("[REL-WORKER] Running in daemon mode, interval: {$interval}s");

    while (true) {
        try {
            $processed = processOneBatch();

            // If we processed something, check again immediately
            // Otherwise wait the interval
            if ($processed === 0) {
                sleep($interval);
            }
        } catch (Exception $e) {
            Logger::error("[REL-WORKER] Error: " . $e->getMessage());
            sleep($interval * 2); // Wait longer on error
        }
    }
} else {
    // One-shot mode - process once and exit
    $processed = processOneBatch();
    echo "Processed: {$processed}\n";
    exit($processed > 0 ? 0 : 1);
}
